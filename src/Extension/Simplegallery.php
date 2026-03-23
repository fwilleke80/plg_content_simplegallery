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
 * Content plugin which replaces {simplegallery ...} tags
 * with a thumbnail gallery.
 */
final class Simplegallery extends CMSPlugin implements SubscriberInterface
{
	/**
	 * Tracks whether CSS has already been injected for the current request.
	 *
	 * @var boolean
	 */
	private static bool $assetsInjected = false;

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
	 * {simplegallery folder="folder/with/images" width="320" columns="2" layout="grid" sort="date" sortorder="ascending" showcaptions}
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

		if (!\str_contains($item->{$textProperty}, '{simplegallery'))
		{
			return;
		}

		Log::add(
			'Triggered onContentPrepare(), context: ' . $event->getContext() . ', property: ' . $textProperty,
			Log::DEBUG,
			'plg_content_simplegallery'
		);

		$this->InjectAssets();

		$item->{$textProperty} = (string) preg_replace_callback(
			'#\{simplegallery(?P<params>[^}]*)\}#i',
			function (array $matches): string
			{
				$rawParams = trim((string) ($matches['params'] ?? ''));

				Log::add(
					'Found simplegallery tag with raw params: "' . $rawParams . '"',
					Log::DEBUG,
					'plg_content_simplegallery'
				);

				$tagOptions = $this->ParseTagParameters($rawParams);
				$options = $this->ResolveGalleryOptions($tagOptions);

				Log::add(
					'Resolved options: ' . json_encode($options),
					Log::DEBUG,
					'plg_content_simplegallery'
				);

				return $this->RenderGallery($options);
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
	 * Loads gallery CSS and JavaScript via Joomla WebAssetManager.
	 *
	 * @return void
	 */
	private function InjectAssets(): void
	{
		if (self::$assetsInjected)
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

		$wa->registerAndUseStyle(
			'plg_content_simplegallery',
			'media/plg_content_simplegallery/css/simplegallery.css'
		);

		$wa->registerAndUseScript(
			'plg_content_simplegallery',
			'media/plg_content_simplegallery/js/simplegallery.js',
			[],
			['defer' => true]
		);

		self::$assetsInjected = true;
	}

	/**
	 * Parses tag parameters from the simplegallery tag.
	 *
	 * Supported forms:
	 * folder="urlaub/spanien"
	 * width="320"
	 * height="240"
	 * columns="2"
	 * layout="grid"
	 * sort="date"
	 * sortorder="ascending"
	 * showcaptions
	 *
	 * @param[in] string $parameterText Raw parameter text from the tag.
	 *
	 * @return array<string, mixed>
	 */
	private function ParseTagParameters(string $parameterText): array
	{
		$options = [];

		if ($parameterText === '')
		{
			return $options;
		}

		$pattern = '/([a-zA-Z][a-zA-Z0-9_-]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\']+)))?/';

		if (!preg_match_all($pattern, $parameterText, $matches, PREG_SET_ORDER))
		{
			return $options;
		}

		foreach ($matches as $match)
		{
			$key = strtolower((string) ($match[1] ?? ''));

			if ($key === '')
			{
				continue;
			}

			$hasValue = array_key_exists(2, $match) || array_key_exists(3, $match) || array_key_exists(4, $match);

			if (
				(!empty($match[2]) || $match[2] === '0')
				|| (!empty($match[3]) || $match[3] === '0')
				|| (!empty($match[4]) || $match[4] === '0')
			)
			{
				$value = (string) ($match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : $match[4]));
				$options[$key] = $value;
			}
			elseif ($hasValue)
			{
				$options[$key] = '';
			}
			else
			{
				$options[$key] = true;
			}
		}

		return $options;
	}

	/**
	 * Resolves final gallery options from tag overrides and plugin defaults.
	 *
	 * Resolution order:
	 * 1. tag parameter
	 * 2. plugin setting
	 * 3. hardcoded fallback
	 *
	 * @param[in] array<string, mixed> $tagOptions Parsed tag options.
	 *
	 * @return array<string, mixed>
	 */
	private function ResolveGalleryOptions(array $tagOptions): array
	{
		$folder = trim((string) ($tagOptions['folder'] ?? ''));
		$folder = str_replace('\\', '/', $folder);
		$folder = trim($folder, '/');

		$width = $tagOptions['width'] ?? $this->params->get('thumb_width', 240);
		$columns = $tagOptions['columns'] ?? $this->params->get('columns', 4);
		$height = $tagOptions['height'] ?? $this->params->get('thumb_height', 180);
		$layout = strtolower(trim((string) ($tagOptions['layout'] ?? $this->params->get('layout', 'grid'))));
		$sort = strtolower(trim((string) ($tagOptions['sort'] ?? $this->params->get('sort', 'filename'))));
		$sortOrder = strtolower(trim((string) ($tagOptions['sortorder'] ?? $this->params->get('sort_order', 'ascending'))));

		$showCaptionsDefault = $this->ToBoolean($this->params->get('show_captions', 0), false);

		if (array_key_exists('showcaptions', $tagOptions))
		{
			$showCaptions = $this->ToBoolean($tagOptions['showcaptions'], true);
		}
		else
		{
			$showCaptions = $showCaptionsDefault;
		}

		$width = max(32, (int) $width);
		$columns = max(1, (int) $columns);
		$height = max(32, (int) $height);

		if (!$this->IsValidLayout($layout))
		{
			$layout = 'grid';
		}

		if ($sort !== 'filename' && $sort !== 'date')
		{
			$sort = 'filename';
		}

		if ($sortOrder !== 'ascending' && $sortOrder !== 'descending')
		{
			$sortOrder = 'ascending';
		}

		return [
			'folder' => $folder,
			'width' => $width,
			'columns' => $columns,
			'height' => $height,
			'showCaptions' => $showCaptions,
			'layout' => $layout,
			'sort' => $sort,
			'sortOrder' => $sortOrder,
		];
	}

	/**
	 * Converts a mixed value to boolean.
	 *
	 * @param[in] mixed   $value        Value to convert.
	 * @param[in] boolean $defaultValue Default value if conversion is ambiguous.
	 *
	 * @return boolean
	 */
	private function ToBoolean(mixed $value, bool $defaultValue): bool
	{
		if (\is_bool($value))
		{
			return $value;
		}

		if (\is_int($value))
		{
			return $value !== 0;
		}

		$stringValue = strtolower(trim((string) $value));

		if ($stringValue === '')
		{
			return $defaultValue;
		}

		if (\in_array($stringValue, ['1', 'true', 'yes', 'on'], true))
		{
			return true;
		}

		if (\in_array($stringValue, ['0', 'false', 'no', 'off'], true))
		{
			return false;
		}

		return $defaultValue;
	}

	/**
	 * Checks whether a layout name is valid and available.
	 *
	 * @param[in] string $layoutName Layout name to validate.
	 *
	 * @return boolean
	 */
	private function IsValidLayout(string $layoutName): bool
	{
		$layoutName = strtolower(trim($layoutName));

		if ($layoutName === '')
		{
			return false;
		}

		if (!preg_match('/^[a-z0-9_-]+$/', $layoutName))
		{
			return false;
		}

		$templatePath = __DIR__ . '/../../tmpl/' . $layoutName . '.php';

		return is_file($templatePath);
	}

	/**
	 * Renders a gallery from resolved options.
	 *
	 * @param[in] array<string, mixed> $options Resolved gallery options.
	 *
	 * @return string
	 */
	private function RenderGallery(array $options): string
	{
		$relativeFolder = (string) ($options['folder'] ?? '');

		if ($relativeFolder === '')
		{
			Log::add('Gallery folder is empty.', Log::WARNING, 'plg_content_simplegallery');

			return '<!-- simplegallery: empty folder value -->';
		}

		if (str_contains($relativeFolder, '..'))
		{
			Log::add(
				'Gallery folder rejected for security reasons: "' . $relativeFolder . '"',
				Log::WARNING,
				'plg_content_simplegallery'
			);

			return '<!-- simplegallery: invalid folder -->';
		}

		$resolvedFolder = $this->ResolveGalleryFolder($relativeFolder);

		if ($resolvedFolder === null)
		{
			return '<!-- simplegallery: folder does not exist -->';
		}

		$realGalleryFolder = $resolvedFolder['absolutePath'];
		$publicFolderPath = $resolvedFolder['publicPath'];

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
			return $this->RenderTemplate(
				(string) $options['layout'],
				[
					'columns' => (int) $options['columns'],
					'items' => [],
				]
			);
		}

		$this->SortImageFiles(
			$imageFiles,
			(string) $options['sort'],
			(string) $options['sortOrder']
		);

		$items = [];

		foreach ($imageFiles as $imagePath)
		{
			$itemData = $this->BuildImageItemData(
				$imagePath,
				$publicFolderPath,
				(int) $options['width'],
				(int) $options['height'],
				(bool) $options['showCaptions']
			);

			if ($itemData === null)
			{
				Log::add(
					'Failed to build image item data for: ' . $imagePath,
					Log::WARNING,
					'plg_content_simplegallery'
				);

				continue;
			}

			$items[] = $itemData;
		}

		return $this->RenderTemplate(
			(string) $options['layout'],
			[
				'columns' => (int) $options['columns'],
				'items' => $items,
			]
		);
	}

	/**
	 * Resolves a gallery folder below /images or /images/stories.
	 *
	 * @param[in] string $relativeFolder Folder relative to /images or /images/stories.
	 *
	 * @return array<string, string>|null
	 */
	private function ResolveGalleryFolder(string $relativeFolder): ?array
	{
		$imagesRoot = Path::clean(JPATH_ROOT . '/images');
		$realImagesRoot = realpath($imagesRoot);

		Log::add('Images root: ' . $imagesRoot, Log::DEBUG, 'plg_content_simplegallery');

		if ($realImagesRoot === false)
		{
			Log::add('Images root does not resolve.', Log::ERROR, 'plg_content_simplegallery');

			return null;
		}

		$candidates = [
			[
				'filesystemPath' => Path::clean($imagesRoot . '/' . $relativeFolder),
				'publicPath' => 'images/' . $relativeFolder,
			],
			[
				'filesystemPath' => Path::clean($imagesRoot . '/stories/' . $relativeFolder),
				'publicPath' => 'images/stories/' . $relativeFolder,
			],
		];

		$realImagesRoot = Path::clean($realImagesRoot);

		foreach ($candidates as $candidate)
		{
			$realGalleryFolder = realpath($candidate['filesystemPath']);

			if ($realGalleryFolder === false)
			{
				continue;
			}

			$realGalleryFolder = Path::clean($realGalleryFolder);

			if (!str_starts_with($realGalleryFolder, $realImagesRoot))
			{
				Log::add(
					'Gallery folder is outside images root: ' . $realGalleryFolder,
					Log::WARNING,
					'plg_content_simplegallery'
				);

				continue;
			}

			if (!is_dir($realGalleryFolder))
			{
				Log::add(
					'Resolved gallery folder is not a directory: ' . $realGalleryFolder,
					Log::WARNING,
					'plg_content_simplegallery'
				);

				continue;
			}

			Log::add(
				'Resolved gallery folder: ' . $realGalleryFolder . ' (public path: ' . $candidate['publicPath'] . ')',
				Log::DEBUG,
				'plg_content_simplegallery'
			);

			return [
				'absolutePath' => $realGalleryFolder,
				'publicPath' => trim($candidate['publicPath'], '/'),
			];
		}

		Log::add(
			'Gallery folder does not exist for relative folder: ' . $relativeFolder,
			Log::WARNING,
			'plg_content_simplegallery'
		);

		return null;
	}

	/**
	 * Sorts image files according to the configured mode.
	 *
	 * @param[in,out] array<int, string> $imageFiles Image file paths.
	 * @param[in]     string             $sort       Sort mode.
	 * @param[in]     string             $sortOrder  Sort order.
	 *
	 * @return void
	 */
	private function SortImageFiles(array &$imageFiles, string $sort, string $sortOrder): void
	{
		usort(
			$imageFiles,
			function (string $left, string $right) use ($sort, $sortOrder): int
			{
				if ($sort === 'date')
				{
					$leftValue = filemtime($left) ?: 0;
					$rightValue = filemtime($right) ?: 0;
				}
				else
				{
					$leftValue = strtolower(basename($left));
					$rightValue = strtolower(basename($right));
				}

				$result = $leftValue <=> $rightValue;

				if ($sortOrder === 'descending')
				{
					$result *= -1;
				}

				return $result;
			}
		);
	}

	/**
	 * Builds the display data for a single image item.
	 *
	 * @param[in] string  $absoluteImagePath Absolute filesystem path to the image.
	 * @param[in] string  $publicFolderPath  Public folder path relative to Joomla root.
	 * @param[in] int     $thumbWidth        Thumbnail width.
	 * @param[in] int     $thumbHeight       Thumbnail height.
	 * @param[in] boolean $showCaptions      Whether captions should be shown.
	 *
	 * @return array<string, mixed>|null
	 */
	private function BuildImageItemData(
		string $absoluteImagePath,
		string $publicFolderPath,
		int $thumbWidth,
		int $thumbHeight,
		bool $showCaptions
	): ?array
	{
		$filename = basename($absoluteImagePath);

		if (!is_file($absoluteImagePath))
		{
			Log::add('Image file not found: ' . $absoluteImagePath, Log::WARNING, 'plg_content_simplegallery');

			return null;
		}

		$fullImageRelativePath = trim($publicFolderPath, '/') . '/' . $filename;
		$fullImageUrl = Uri::root() . str_replace('%2F', '/', rawurlencode($fullImageRelativePath));

		$thumbAbsolutePath = $this->GetOrCreateThumbnail($absoluteImagePath, $thumbWidth, $thumbHeight);

		if ($thumbAbsolutePath === null || !is_file($thumbAbsolutePath))
		{
			Log::add(
				'Thumbnail could not be created for: ' . $absoluteImagePath,
				Log::WARNING,
				'plg_content_simplegallery'
			);

			return null;
		}

		$thumbRelativePath = $this->AbsolutePathToRelativePath($thumbAbsolutePath);

		if ($thumbRelativePath === null)
		{
			Log::add(
				'Could not convert thumbnail path to relative path: ' . $thumbAbsolutePath,
				Log::WARNING,
				'plg_content_simplegallery'
			);

			return null;
		}

		$thumbUrl = Uri::root() . str_replace('%2F', '/', rawurlencode($thumbRelativePath));

		$caption = pathinfo($filename, PATHINFO_FILENAME);
		$caption = urldecode($caption);
		$caption = ucwords(trim(str_replace('_', ' ', $caption)));
		$caption = htmlspecialchars($caption, ENT_QUOTES, 'UTF-8');

		return [
			'filename' => $filename,
			'caption' => $caption,
			'fullImageUrl' => $fullImageUrl,
			'thumbUrl' => $thumbUrl,
			'showCaption' => $showCaptions,
		];
	}

	/**
	 * Renders a gallery template with prepared display data.
	 *
	 * @param[in] string               $layoutName  Template layout name.
	 * @param[in] array<string, mixed> $displayData Prepared display data.
	 *
	 * @return string
	 */
	private function RenderTemplate(string $layoutName, array $displayData): string
	{
		if (!$this->IsValidLayout($layoutName))
		{
			Log::add(
				'Invalid or unavailable template layout requested: ' . $layoutName,
				Log::ERROR,
				'plg_content_simplegallery'
			);

			return '<!-- simplegallery: invalid template layout -->';
		}

		$templatePath = __DIR__ . '/../../tmpl/' . $layoutName . '.php';

		if (!is_file($templatePath))
		{
			Log::add(
				'Template file not found: ' . $templatePath,
				Log::ERROR,
				'plg_content_simplegallery'
			);

			return '<!-- simplegallery: template not found -->';
		}

		ob_start();

		extract(['displayData' => $displayData], EXTR_SKIP);

		include $templatePath;

		return (string) ob_get_clean();
	}

	/**
	 * Creates or reuses a cached thumbnail for the given image.
	 *
	 * @param[in] string $absoluteImagePath Absolute source image path.
	 * @param[in] int    $thumbWidth        Thumbnail width
	 * @param[in] int    $thumbHeight       Thumbnail height
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