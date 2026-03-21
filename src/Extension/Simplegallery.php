<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.Simplegallery
 *
 * @copyright   (C) 2026
 * @license     GNU General Public License version 2 or later
 */

namespace Joomla\Plugin\Content\Simplegallery\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Image\Image;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\SubscriberInterface;

/**
 * Content plugin which replaces {simplegallery}...{/simplegallery} tags
 * with a thumbnail table gallery.
 */
final class Simplegallery extends CMSPlugin implements SubscriberInterface
{
	/**
	 * Tracks whether CSS has already been injected for the current request.
	 *
	 * @var boolean
	 */
	private static bool $cssInjected = false;

	/**
	 * Returns the list of subscribed events.
	 *
	 * @return array<string, string>
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onContentPrepare' => 'onContentPrepare',
		];
	}

	/**
	 * Handles content preparation and replaces simplegallery tags.
	 *
	 * Supported form:
	 * {simplegallery}folder/name{/simplegallery}
	 *
	 * @param[in] ContentPrepareEvent $event The Joomla content event.
	 *
	 * @return void
	 */
	public function onContentPrepare(ContentPrepareEvent $event): void
	{
		$application = $this->getApplication();

		if ($application === null)
		{
			Log::add('Application is null.', Log::ERROR, 'plg_content_simplegallery');

			return;
		}

		if (!$application->isClient('site'))
		{
			return;
		}

		$item = $event->getItem();

		if (!\is_object($item))
		{
			Log::add('Content item is not an object.', Log::DEBUG, 'plg_content_simplegallery');

			return;
		}

		$textProperty = $this->DetectTextProperty($item);

		if ($textProperty === null)
		{
			Log::add('No usable text property found on content item.', Log::DEBUG, 'plg_content_simplegallery');

			return;
		}

		if (!\is_string($item->{$textProperty}) || $item->{$textProperty} === '')
		{
			Log::add('Text property exists but is empty or not a string.', Log::DEBUG, 'plg_content_simplegallery');

			return;
		}

		if (!\str_contains($item->{$textProperty}, '{simplegallery}'))
		{
			return;
		}

		Log::add(
			'Triggered onContentPrepare(), context: ' . $event->getContext() . ', property: ' . $textProperty,
			Log::DEBUG,
			'plg_content_simplegallery'
		);

		$this->InjectCss();

		$item->{$textProperty} = (string) preg_replace_callback(
			'#\{simplegallery\}\s*(.*?)\s*\{/simplegallery\}#si',
			function (array $matches): string
			{
				$folder = trim((string) ($matches[1] ?? ''));

				Log::add(
					'Found simplegallery tag with folder: "' . $folder . '"',
					Log::DEBUG,
					'plg_content_simplegallery'
				);

				return $this->RenderGalleryFromFolder($folder);
			},
			$item->{$textProperty}
		);
	}

	/**
	 * Determines which property of the content item should be modified.
	 *
	 * @param[in] object $item The content item.
	 *
	 * @return string|null
	 */
	private function DetectTextProperty(object $item): ?string
	{
		if (property_exists($item, 'text'))
		{
			return 'text';
		}

		if (property_exists($item, 'introtext'))
		{
			return 'introtext';
		}

		return null;
	}

	/**
	 * Loads the gallery CSS via Joomla WebAssetManager.
	 *
	 * @return void
	 */
	private function InjectCss(): void
	{
		if (self::$cssInjected)
		{
			return;
		}

		$application = $this->getApplication();

		if ($application === null)
		{
			return;
		}

		$document = $application->getDocument();
		$wa = $document->getWebAssetManager();

		// Register + use stylesheet
		$wa->registerAndUseStyle(
			'plg_content_simplegallery',
			'media/plg_content_simplegallery/css/simplegallery.css'
		);

		self::$cssInjected = true;
	}

	/**
	 * Renders a gallery for a folder relative to Joomla's images directory.
	 *
	 * @param[in] string $relativeFolder Relative folder below /images or /images/stories.
	 *
	 * @return string
	 */
	private function RenderGalleryFromFolder(string $relativeFolder): string
	{
		if ($relativeFolder === '')
		{
			Log::add('Gallery folder is empty.', Log::WARNING, 'plg_content_simplegallery');

			return '<!-- simplegallery: empty folder value -->';
		}

		$relativeFolder = str_replace('\\', '/', $relativeFolder);
		$relativeFolder = trim($relativeFolder, '/');

		if ($relativeFolder === '' || str_contains($relativeFolder, '..'))
		{
			Log::add(
				'Gallery folder rejected for security reasons: "' . $relativeFolder . '"',
				Log::WARNING,
				'plg_content_simplegallery'
			);

			return '<!-- simplegallery: invalid folder -->';
		}

		$imagesRoot = Path::clean(JPATH_ROOT . '/images');

		$galleryFolder = Path::clean($imagesRoot . '/' . $relativeFolder);
		$realGalleryFolder = realpath($galleryFolder);

		if ($realGalleryFolder === false)
		{
			$galleryFolder = Path::clean($imagesRoot . '/stories/' . $relativeFolder);
			$realGalleryFolder = realpath($galleryFolder);

			Log::add(
				'Fallback to /images/stories: ' . $galleryFolder,
				Log::DEBUG,
				'plg_content_simplegallery'
			);
		}

		$realImagesRoot = realpath($imagesRoot);

		Log::add('Images root: ' . $imagesRoot, Log::DEBUG, 'plg_content_simplegallery');
		Log::add('Gallery folder: ' . $galleryFolder, Log::DEBUG, 'plg_content_simplegallery');

		if ($realGalleryFolder === false)
		{
			Log::add(
				'Gallery folder does not exist: ' . $galleryFolder,
				Log::WARNING,
				'plg_content_simplegallery'
			);

			return '<!-- simplegallery: folder does not exist: ' . htmlspecialchars($galleryFolder, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' -->';
		}

		if ($realImagesRoot === false)
		{
			Log::add('Images root does not resolve.', Log::ERROR, 'plg_content_simplegallery');

			return '<!-- simplegallery: images root does not resolve -->';
		}

		$realGalleryFolder = Path::clean($realGalleryFolder);
		$realImagesRoot = Path::clean($realImagesRoot);

		if (!str_starts_with($realGalleryFolder, $realImagesRoot))
		{
			Log::add(
				'Gallery folder is outside images root: ' . $realGalleryFolder,
				Log::WARNING,
				'plg_content_simplegallery'
			);

			return '<!-- simplegallery: folder outside images root -->';
		}

		if (!is_dir($realGalleryFolder))
		{
			Log::add(
				'Resolved gallery folder is not a directory: ' . $realGalleryFolder,
				Log::WARNING,
				'plg_content_simplegallery'
			);

			return '<!-- simplegallery: resolved path is not a directory -->';
		}

		$imageFiles = Folder::files(
			$realGalleryFolder,
			'\.(jpe?g|png|gif|webp)$',
			false,
			true
		);

		Log::add(
			'Found ' . \count($imageFiles) . ' image files in ' . $realGalleryFolder,
			Log::DEBUG,
			'plg_content_simplegallery'
		);

		if (empty($imageFiles))
		{
			return '<div class="simplegallery-empty">No images found.</div>';
		}

		natcasesort($imageFiles);

		$columns = max(1, (int) $this->params->get('columns', 4));
		$thumbWidth = max(32, (int) $this->params->get('thumb_width', 240));
		$thumbHeight = max(32, (int) $this->params->get('thumb_height', 180));

		$html = [];
		$html[] = '<div class="simplegallery-grid" style="--simplegallery-columns: ' . $columns . ';">';

		foreach ($imageFiles as $imagePath)
		{
			$cellHtml = $this->RenderImageCell($imagePath, $relativeFolder, $thumbWidth, $thumbHeight);

			if ($cellHtml === '')
			{
				Log::add(
					'Failed to render image cell for: ' . $imagePath,
					Log::WARNING,
					'plg_content_simplegallery'
				);

				continue;
			}

			$html[] = '<div class="simplegallery-item">' . $cellHtml . '</div>';
		}

		$html[] = '</div>';

		return implode('', $html);
	}

	/**
	 * Renders a single image cell.
	 *
	 * @param[in] string $absoluteImagePath Absolute filesystem path to the image.
	 * @param[in] string $relativeFolder    Folder relative to /images.
	 * @param[in] int    $thumbWidth        Thumbnail width.
	 * @param[in] int    $thumbHeight       Thumbnail height.
	 *
	 * @return string
	 */
	private function RenderImageCell(
		string $absoluteImagePath,
		string $relativeFolder,
		int $thumbWidth,
		int $thumbHeight
	): string
	{
		$filename = basename($absoluteImagePath);

		if (!is_file($absoluteImagePath))
		{
			Log::add('Image file not found: ' . $absoluteImagePath, Log::WARNING, 'plg_content_simplegallery');

			return '';
		}

		$fullImageRelativePath = 'images/stories/' . trim($relativeFolder, '/') . '/' . $filename;
		$fullImageUrl = Uri::root() . str_replace('%2F', '/', rawurlencode($fullImageRelativePath));

		$thumbAbsolutePath = $this->GetOrCreateThumbnail($absoluteImagePath, $thumbWidth, $thumbHeight);

		if ($thumbAbsolutePath === null || !is_file($thumbAbsolutePath))
		{
			Log::add(
				'Thumbnail could not be created for: ' . $absoluteImagePath,
				Log::WARNING,
				'plg_content_simplegallery'
			);

			return '';
		}

		$thumbRelativePath = $this->AbsolutePathToRelativePath($thumbAbsolutePath);

		if ($thumbRelativePath === null)
		{
			Log::add(
				'Could not convert thumbnail path to relative path: ' . $thumbAbsolutePath,
				Log::WARNING,
				'plg_content_simplegallery'
			);

			return '';
		}

		$thumbUrl = Uri::root() . str_replace('%2F', '/', rawurlencode($thumbRelativePath));
		$alt = htmlspecialchars(pathinfo($filename, PATHINFO_FILENAME), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

		return
			'<a class="simplegallery-thumb-link" href="' . htmlspecialchars($fullImageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" rel="Lightbox">' .
				'<img class="simplegallery-thumb" src="' . htmlspecialchars($thumbUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" alt="' . $alt . '" loading="lazy">' .
			'</a>';
	}

	/**
	 * Creates or reuses a cached thumbnail for the given image.
	 *
	 * @param[in] string $absoluteImagePath Absolute source image path.
	 * @param[in] int    $thumbWidth        Thumbnail width.
	 * @param[in] int    $thumbHeight       Thumbnail height.
	 *
	 * @return string|null Absolute path to the thumbnail.
	 */
	private function GetOrCreateThumbnail(string $absoluteImagePath, int $thumbWidth, int $thumbHeight): ?string
	{
		$cacheFolderSetting = (string) $this->params->get('cache_folder', 'images/.simplegallery-cache');
		$cacheFolderSetting = trim(str_replace('\\', '/', $cacheFolderSetting), '/');

		if ($cacheFolderSetting === '' || str_contains($cacheFolderSetting, '..'))
		{
			$cacheFolderSetting = 'images/.simplegallery-cache';
		}

		$absoluteCacheFolder = Path::clean(JPATH_ROOT . '/' . $cacheFolderSetting);

		if (!Folder::exists($absoluteCacheFolder))
		{
			if (!Folder::create($absoluteCacheFolder))
			{
				Log::add(
					'Could not create cache folder: ' . $absoluteCacheFolder,
					Log::ERROR,
					'plg_content_simplegallery'
				);

				return null;
			}

			Log::add(
				'Created cache folder: ' . $absoluteCacheFolder,
				Log::DEBUG,
				'plg_content_simplegallery'
			);
		}

		$sourceHash = sha1($absoluteImagePath . '|' . filemtime($absoluteImagePath) . '|' . $thumbWidth . 'x' . $thumbHeight);
		$sourceInfo = pathinfo($absoluteImagePath);
		$thumbExtension = 'jpg';
		$thumbFilename = ($sourceInfo['filename'] ?? 'thumb') . '_' . $sourceHash . '.' . $thumbExtension;
		$thumbAbsolutePath = Path::clean($absoluteCacheFolder . '/' . $thumbFilename);

		if (is_file($thumbAbsolutePath))
		{
			return $thumbAbsolutePath;
		}

		try
		{
			$image = new Image($absoluteImagePath);
			$thumbnail = $image->resize($thumbWidth, $thumbHeight, true, Image::SCALE_INSIDE);
			$thumbnail->toFile($thumbAbsolutePath, IMAGETYPE_JPEG);
			$thumbnail->destroy();
			$image->destroy();

			Log::add(
				'Created thumbnail: ' . $thumbAbsolutePath,
				Log::DEBUG,
				'plg_content_simplegallery'
			);
		}
		catch (\Throwable $throwable)
		{
			Log::add(
				'Thumbnail generation failed for "' . $absoluteImagePath . '": ' . $throwable->getMessage(),
				Log::ERROR,
				'plg_content_simplegallery'
			);

			return null;
		}

		return is_file($thumbAbsolutePath) ? $thumbAbsolutePath : null;
	}

	/**
	 * Converts an absolute path below JPATH_ROOT into a relative path.
	 *
	 * @param[in] string $absolutePath Absolute filesystem path.
	 *
	 * @return string|null
	 */
	private function AbsolutePathToRelativePath(string $absolutePath): ?string
	{
		$root = Path::clean(JPATH_ROOT);
		$path = Path::clean($absolutePath);

		if (!str_starts_with($path, $root))
		{
			return null;
		}

		$relative = ltrim(substr($path, strlen($root)), DIRECTORY_SEPARATOR);

		return str_replace(DIRECTORY_SEPARATOR, '/', $relative);
	}
}