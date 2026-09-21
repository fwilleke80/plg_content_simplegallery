<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.PungaSimpleGallery
 *
 * @copyright   (C) 2026
 * @license     GNU General Public License version 2 or later
 */

namespace Punga\Plugin\Content\SimpleGallery\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Image\Image;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\SubscriberInterface;

/**
 * Content plugin which replaces {simplegallery ...} tags with media galleries.
 */
final class PungaSimpleGallery extends CMSPlugin implements SubscriberInterface
{
	/**
	 * Supported image extensions.
	 *
	 * @var array<int, string>
	 */
	private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

	/**
	 * Supported video extensions.
	 *
	 * @var array<int, string>
	 */
	private const VIDEO_EXTENSIONS = ['mp4', 'm4v', 'webm', 'ogv', 'ogg', 'mov'];

	/**
	 * Metadata inherited by media items from the gallery-level "." entry.
	 *
	 * @var array<int, string>
	 */
	private const INHERITED_GALLERY_METADATA_FIELDS = ['author', 'copyright', 'location'];

	/**
	 * Tracks whether CSS and JavaScript have already been injected.
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
	 * @param[in] ContentPrepareEvent $event The Joomla content event.
	 *
	 * @return void
	 */
	public function onContentPrepare(ContentPrepareEvent $event): void
	{
		$application = $this->getApplication();

		if ($application === null || !$application->isClient('site'))
		{
			return;
		}

		$item = $event->getItem();

		if (!\is_object($item))
		{
			return;
		}

		$textProperty = $this->DetectTextProperty($item);

		if ($textProperty === null || !\is_string($item->{$textProperty}) || $item->{$textProperty} === '')
		{
			return;
		}

		if (!\str_contains($item->{$textProperty}, '{simplegallery'))
		{
			return;
		}

		$this->InjectAssets();

		$item->{$textProperty} = (string) preg_replace_callback(
			'#\{simplegallery(?P<params>[^}]*)\}#i',
			function (array $matches): string
			{
				$rawParams = trim((string) ($matches['params'] ?? ''));
				$tagOptions = $this->NormalizeTagOptions($this->ParseTagParameters($rawParams));
				$options = $this->ResolveGalleryOptions($tagOptions);

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
			'media/plg_content_simplegallery/css/simplegallery.css',
			['version' => '1.3.4']
		);

		$wa->registerAndUseScript(
			'plg_content_simplegallery',
			'media/plg_content_simplegallery/js/simplegallery.js',
			['version' => '1.3.4'],
			['defer' => true]
		);

		self::$assetsInjected = true;
	}

	/**
	 * Parses parameters from a simplegallery tag.
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

			$doubleQuotedValue = array_key_exists(2, $match) ? (string) $match[2] : null;
			$singleQuotedValue = array_key_exists(3, $match) ? (string) $match[3] : null;
			$unquotedValue = array_key_exists(4, $match) ? (string) $match[4] : null;
			$hasValue = $doubleQuotedValue !== null || $singleQuotedValue !== null || $unquotedValue !== null;

			if (
				($doubleQuotedValue !== null && $doubleQuotedValue !== '')
				|| ($singleQuotedValue !== null && $singleQuotedValue !== '')
				|| ($unquotedValue !== null && $unquotedValue !== '')
			)
			{
				$value = $doubleQuotedValue !== null && $doubleQuotedValue !== ''
					? $doubleQuotedValue
					: ($singleQuotedValue !== null && $singleQuotedValue !== '' ? $singleQuotedValue : (string) $unquotedValue);
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
	 * Normalizes documented aliases while preserving backwards compatibility.
	 *
	 * @param[in] array<string, mixed> $tagOptions Parsed tag options.
	 *
	 * @return array<string, mixed>
	 */
	private function NormalizeTagOptions(array $tagOptions): array
	{
		$aliases = [
			'thumb_width' => 'width',
			'thumb_height' => 'height',
			'show_captions' => 'showcaptions',
			'sort_order' => 'sortorder',
			'media_types' => 'media',
			'lightbox_mode' => 'lightbox',
			'show_lightbox_metadata' => 'showmetadata',
			'show_metadata' => 'showmetadata',
		];

		foreach ($aliases as $alias => $canonical)
		{
			if (!array_key_exists($canonical, $tagOptions) && array_key_exists($alias, $tagOptions))
			{
				$tagOptions[$canonical] = $tagOptions[$alias];
			}
		}

		return $tagOptions;
	}

	/**
	 * Resolves final gallery options from tag overrides and plugin defaults.
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

		$width = max(32, (int) ($tagOptions['width'] ?? $this->params->get('thumb_width', 240)));
		$height = max(32, (int) ($tagOptions['height'] ?? $this->params->get('thumb_height', 180)));
		$columns = max(1, (int) ($tagOptions['columns'] ?? $this->params->get('columns', 4)));
		$layout = strtolower(trim((string) ($tagOptions['layout'] ?? $this->params->get('layout', 'grid'))));
		$sort = strtolower(trim((string) ($tagOptions['sort'] ?? $this->params->get('sort', 'filename'))));
		$sortOrder = strtolower(trim((string) ($tagOptions['sortorder'] ?? $this->params->get('sort_order', 'ascending'))));
		$media = strtolower(trim((string) ($tagOptions['media'] ?? $this->params->get('media_types', 'images'))));
		$lightbox = strtolower(trim((string) ($tagOptions['lightbox'] ?? $this->params->get('lightbox_mode', 'builtin'))));

		$showCaptions = $this->ResolveBooleanOption(
			$tagOptions,
			'showcaptions',
			$this->ToBoolean($this->params->get('show_captions', 0), false)
		);
		$lightboxInfoPosition = strtolower(trim((string) $this->params->get('lightbox_info_position', '')));

		if (!\in_array($lightboxInfoPosition, ['side', 'below', 'none'], true))
		{
			$legacyShowMetadata = $this->ToBoolean($this->params->get('show_lightbox_metadata', 1), true);
			$lightboxInfoPosition = $legacyShowMetadata ? 'side' : 'none';
		}

		$showMetadata = true;

		if (array_key_exists('showmetadata', $tagOptions))
		{
			$showMetadata = $this->ToBoolean($tagOptions['showmetadata'], true);
		}

		$readExifMetadata = $this->ToBoolean($this->params->get('read_exif_metadata', 0), false);

		if (!$this->IsValidLayout($layout))
		{
			$layout = 'grid';
		}

		if (!\in_array($sort, ['filename', 'date', 'random'], true))
		{
			$sort = 'filename';
		}

		if (!\in_array($sortOrder, ['ascending', 'descending'], true))
		{
			$sortOrder = 'ascending';
		}

		if (!\in_array($media, ['images', 'videos', 'both'], true))
		{
			$media = 'images';
		}

		if (!\in_array($lightbox, ['builtin', 'external', 'none'], true))
		{
			$lightbox = 'builtin';
		}

		return [
			'folder' => $folder,
			'width' => $width,
			'height' => $height,
			'columns' => $columns,
			'layout' => $layout,
			'sort' => $sort,
			'sortOrder' => $sortOrder,
			'media' => $media,
			'lightbox' => $lightbox,
			'lightboxInfoPosition' => $lightboxInfoPosition,
			'showCaptions' => $showCaptions,
			'showMetadata' => $showMetadata,
			'readExifMetadata' => $readExifMetadata,
			'metadataFields' => $this->ResolveMetadataFields(),
		];
	}

	/**
	 * Resolves a boolean tag override with a plugin-setting fallback.
	 *
	 * @param[in] array<string, mixed> $tagOptions   Parsed tag options.
	 * @param[in] string               $key          Canonical tag option name.
	 * @param[in] boolean              $defaultValue Plugin default value.
	 *
	 * @return boolean
	 */
	private function ResolveBooleanOption(array $tagOptions, string $key, bool $defaultValue): bool
	{
		if (!array_key_exists($key, $tagOptions))
		{
			return $defaultValue;
		}

		return $this->ToBoolean($tagOptions[$key], true);
	}

	/**
	 * Resolves configured metadata fields.
	 *
	 * @return array<int, string>
	 */
	private function ResolveMetadataFields(): array
	{
		$value = $this->params->get('metadata_fields', ['date', 'author', 'location', 'copyright', 'dimensions', 'filesize']);

		if (\is_string($value))
		{
			$value = preg_split('/\s*,\s*/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
		}

		if (!\is_array($value))
		{
			return [];
		}

		$allowed = ['date', 'author', 'location', 'copyright', 'dimensions', 'filesize', 'filename', 'camera', 'lens', 'exposure', 'aperture', 'iso', 'focal_length'];
		$result = [];

		foreach ($value as $field)
		{
			$field = strtolower(trim((string) $field));

			if (\in_array($field, $allowed, true) && !\in_array($field, $result, true))
			{
				$result[] = $field;
			}
		}

		return $result;
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

		if ($layoutName === '' || !preg_match('/^[a-z0-9_-]+$/', $layoutName))
		{
			return false;
		}

		return is_file(__DIR__ . '/../../tmpl/' . $layoutName . '.php');
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
			return '<!-- Punga Simple Gallery: empty folder value -->';
		}

		if (str_contains($relativeFolder, '..'))
		{
			return '<!-- Punga Simple Gallery: invalid folder -->';
		}

		$resolvedFolder = $this->ResolveGalleryFolder($relativeFolder);

		if ($resolvedFolder === null)
		{
			return '<!-- Punga Simple Gallery: folder does not exist -->';
		}

		$absoluteFolder = $resolvedFolder['absolutePath'];
		$publicFolderPath = $resolvedFolder['publicPath'];
		$galleryMetadata = $this->LoadGalleryMetadata($absoluteFolder);
		$allMediaFiles = $this->DiscoverMediaFiles($absoluteFolder, 'both');
		$metadataByFilename = [];
		$posterFilenames = [];

		foreach ($allMediaFiles as $mediaPath)
		{
			$filename = basename($mediaPath);
			$metadataByFilename[$filename] = $this->ResolveItemMetadata($mediaPath, $galleryMetadata, (bool) $options['readExifMetadata']);

			if ($this->DetectMediaType($mediaPath) === 'video')
			{
				$posterFilename = $this->ResolvePosterFilename(
					$absoluteFolder,
					$filename,
					$metadataByFilename[$filename]
				);

				if ($posterFilename !== null)
				{
					$posterFilenames[$posterFilename] = true;
				}
			}
		}

		$mediaMode = (string) $options['media'];
		$mediaFiles = array_values(array_filter(
			$allMediaFiles,
			function (string $path) use ($posterFilenames, $mediaMode): bool
			{
				if (isset($posterFilenames[basename($path)]))
				{
					return false;
				}

				$type = $this->DetectMediaType($path);

				return $mediaMode === 'both'
					|| ($mediaMode === 'images' && $type === 'image')
					|| ($mediaMode === 'videos' && $type === 'video');
			}
		));

		$this->SortMediaFiles($mediaFiles, (string) $options['sort'], (string) $options['sortOrder']);

		$items = [];

		foreach ($mediaFiles as $mediaPath)
		{
			$filename = basename($mediaPath);
			$metadata = $metadataByFilename[$filename] ?? $this->ResolveItemMetadata($mediaPath, $galleryMetadata, (bool) $options['readExifMetadata']);
			$itemData = $this->BuildMediaItemData(
				$mediaPath,
				$absoluteFolder,
				$publicFolderPath,
				$metadata,
				(int) $options['width'],
				(int) $options['height'],
				(bool) $options['showCaptions'],
				(bool) $options['showMetadata'],
				(array) $options['metadataFields'],
				(string) $options['lightbox'],
				(array) $galleryMetadata['gallery']
			);

			if ($itemData !== null)
			{
				$items[] = $itemData;
			}
		}

		return $this->RenderTemplate(
			(string) $options['layout'],
			[
				'columns' => (int) $options['columns'],
				'items' => $items,
				'lightboxMode' => (string) $options['lightbox'],
				'lightboxInfoPosition' => (string) $options['lightboxInfoPosition'],
				'galleryTitle' => trim((string) ($galleryMetadata['gallery']['title'] ?? '')),
				'galleryDescription' => trim((string) ($galleryMetadata['gallery']['description'] ?? '')),
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

		if ($realImagesRoot === false)
		{
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
			$rootPrefix = rtrim($realImagesRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

			if ($realGalleryFolder !== $realImagesRoot && !str_starts_with($realGalleryFolder, $rootPrefix))
			{
				continue;
			}

			if (!is_dir($realGalleryFolder))
			{
				continue;
			}

			return [
				'absolutePath' => $realGalleryFolder,
				'publicPath' => trim($candidate['publicPath'], '/'),
			];
		}

		return null;
	}

	/**
	 * Finds supported media files in a gallery folder.
	 *
	 * @param[in] string $absoluteFolder Absolute gallery folder.
	 * @param[in] string $mediaMode      images, videos, or both.
	 *
	 * @return array<int, string>
	 */
	private function DiscoverMediaFiles(string $absoluteFolder, string $mediaMode): array
	{
		$extensions = match ($mediaMode)
		{
			'videos' => self::VIDEO_EXTENSIONS,
			'both' => array_merge(self::IMAGE_EXTENSIONS, self::VIDEO_EXTENSIONS),
			default => self::IMAGE_EXTENSIONS,
		};

		$pattern = '\\.(' . implode('|', array_map('preg_quote', $extensions)) . ')$';

		return Folder::files($absoluteFolder, $pattern, false, true);
	}

	/**
	 * Detects whether a file is an image or video.
	 *
	 * @param[in] string $path File path.
	 *
	 * @return string|null
	 */
	private function DetectMediaType(string $path): ?string
	{
		$extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

		if (\in_array($extension, self::IMAGE_EXTENSIONS, true))
		{
			return 'image';
		}

		if (\in_array($extension, self::VIDEO_EXTENSIONS, true))
		{
			return 'video';
		}

		return null;
	}

	/**
	 * Loads optional gallery.json metadata.
	 *
	 * @param[in] string $absoluteFolder Absolute gallery folder.
	 *
	 * @return array{gallery: array<string, mixed>, items: array<string, array<string, mixed>>}
	 */
	private function LoadGalleryMetadata(string $absoluteFolder): array
	{
		$result = [
			'gallery' => [],
			'items' => [],
		];
		$path = Path::clean($absoluteFolder . '/gallery.json');

		if (!is_file($path))
		{
			return $result;
		}

		$data = $this->ReadJsonObject($path);

		if ($data === null)
		{
			return $result;
		}

		foreach ($data as $key => $value)
		{
			if (!\is_array($value))
			{
				continue;
			}

			if ((string) $key === '.')
			{
				$result['gallery'] = $this->NormalizeMetadataObject($value);
				continue;
			}

			$filename = basename((string) $key);

			if ($filename !== (string) $key)
			{
				continue;
			}

			$result['items'][$filename] = $this->NormalizeMetadataObject($value);
		}

		return $result;
	}

	/**
	 * Reads one JSON object safely.
	 *
	 * @param[in] string $path JSON file path.
	 *
	 * @return array<string, mixed>|null
	 */
	private function ReadJsonObject(string $path): ?array
	{
		$raw = file_get_contents($path);

		if ($raw === false)
		{
			Log::add('Unable to read JSON metadata file: ' . $path, Log::WARNING, 'plg_content_simplegallery');
			return null;
		}

		try
		{
			$data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
		}
		catch (\JsonException $exception)
		{
			Log::add(
				'Invalid JSON metadata in ' . $path . ': ' . $exception->getMessage(),
				Log::WARNING,
				'plg_content_simplegallery'
			);

			return null;
		}

		return \is_array($data) ? $data : null;
	}

	/**
	 * Normalizes metadata values to safe scalar strings.
	 *
	 * @param[in] array<string, mixed> $metadata Metadata object.
	 *
	 * @return array<string, mixed>
	 */
	private function NormalizeMetadataObject(array $metadata): array
	{
		$result = [];
		$allowed = ['title', 'description', 'date', 'author', 'location', 'copyright', 'alt', 'poster', 'camera', 'lens', 'exposure', 'aperture', 'iso', 'focal_length'];

		foreach ($allowed as $field)
		{
			if (!array_key_exists($field, $metadata) || !\is_scalar($metadata[$field]))
			{
				continue;
			}

			$result[$field] = trim((string) $metadata[$field]);
		}

		return $result;
	}

	/**
	 * Resolves metadata for one media file.
	 *
	 * Resolution order:
	 * automatic values -> inherited "." values -> gallery.json item -> per-file JSON sidecar.
	 *
	 * @param[in] string                                                                    $absoluteMediaPath Media file path.
	 * @param[in] array{gallery: array<string, mixed>, items: array<string, array<string, mixed>>} $galleryMetadata Gallery metadata.
	 * @param[in] boolean                                                                   $readExifMetadata Whether safe EXIF fields should be read.
	 *
	 * @return array<string, mixed>
	 */
	private function ResolveItemMetadata(string $absoluteMediaPath, array $galleryMetadata, bool $readExifMetadata): array
	{
		$filename = basename($absoluteMediaPath);
		$metadata = [
			'title' => $this->BuildFilenameCaptionText($absoluteMediaPath),
		];

		if ($readExifMetadata && $this->DetectMediaType($absoluteMediaPath) === 'image')
		{
			$metadata = array_replace($metadata, $this->ReadExifMetadata($absoluteMediaPath));
		}

		foreach (self::INHERITED_GALLERY_METADATA_FIELDS as $field)
		{
			$value = trim((string) ($galleryMetadata['gallery'][$field] ?? ''));

			if ($value !== '')
			{
				$metadata[$field] = $value;
			}
		}

		if (isset($galleryMetadata['items'][$filename]))
		{
			$metadata = array_replace($metadata, $galleryMetadata['items'][$filename]);
		}

		$sidecarPath = $absoluteMediaPath . '.json';

		if (is_file($sidecarPath))
		{
			$sidecarData = $this->ReadJsonObject($sidecarPath);

			if ($sidecarData !== null)
			{
				$metadata = array_replace($metadata, $this->NormalizeMetadataObject($sidecarData));
			}
		}

		return $metadata;
	}


	/**
	 * Reads a conservative subset of EXIF metadata from a JPEG image.
	 *
	 * GPS sections are deliberately not requested or exposed.
	 *
	 * @param[in] string $absoluteImagePath Absolute image path.
	 *
	 * @return array<string, string>
	 */
	private function ReadExifMetadata(string $absoluteImagePath): array
	{
		if (!\function_exists('exif_read_data'))
		{
			return [];
		}

		$extension = strtolower((string) pathinfo($absoluteImagePath, PATHINFO_EXTENSION));

		if (!\in_array($extension, ['jpg', 'jpeg'], true))
		{
			return [];
		}

		$data = @exif_read_data($absoluteImagePath, 'IFD0,EXIF', true, false);

		if (!\is_array($data))
		{
			return [];
		}

		$ifd0 = isset($data['IFD0']) && \is_array($data['IFD0']) ? $data['IFD0'] : [];
		$exif = isset($data['EXIF']) && \is_array($data['EXIF']) ? $data['EXIF'] : [];
		$result = [];
		$date = $this->FirstExifValue([$exif, $ifd0], ['DateTimeOriginal', 'DateTimeDigitized', 'DateTime']);

		if ($date !== '')
		{
			$result['date'] = preg_replace('/^(\\d{4}):(\\d{2}):(\\d{2})/', '$1-$2-$3', $date) ?? $date;
		}

		$author = $this->FirstExifValue([$ifd0, $exif], ['Artist', 'Author']);

		if ($author !== '')
		{
			$result['author'] = $author;
		}

		$copyright = $this->FirstExifValue([$ifd0, $exif], ['Copyright']);

		if ($copyright !== '')
		{
			$result['copyright'] = $copyright;
		}

		$make = $this->FirstExifValue([$ifd0], ['Make']);
		$model = $this->FirstExifValue([$ifd0], ['Model']);
		$camera = $model;

		if ($make !== '' && $model !== '' && stripos($model, $make) !== 0)
		{
			$camera = trim($make . ' ' . $model);
		}
		elseif ($camera === '')
		{
			$camera = $make;
		}

		if ($camera !== '')
		{
			$result['camera'] = $camera;
		}

		$lens = $this->FirstExifValue([$exif, $ifd0], ['LensModel', 'UndefinedTag:0xA434']);

		if ($lens !== '')
		{
			$result['lens'] = $lens;
		}

		$exposure = $this->FirstExifValue([$exif, $ifd0], ['ExposureTime']);

		if ($exposure !== '')
		{
			$result['exposure'] = rtrim($exposure) . ' s';
		}

		$aperture = $this->ExifRationalToFloat($this->FirstExifValue([$exif, $ifd0], ['FNumber']));

		if ($aperture !== null)
		{
			$result['aperture'] = 'f/' . rtrim(rtrim(number_format($aperture, 1, '.', ''), '0'), '.');
		}

		$iso = $this->FirstExifValue([$exif, $ifd0], ['PhotographicSensitivity', 'ISOSpeedRatings']);

		if ($iso !== '')
		{
			$result['iso'] = $iso;
		}

		$focalLength = $this->ExifRationalToFloat($this->FirstExifValue([$exif, $ifd0], ['FocalLength']));

		if ($focalLength !== null)
		{
			$result['focal_length'] = rtrim(rtrim(number_format($focalLength, 1, '.', ''), '0'), '.') . ' mm';
		}

		return $result;
	}

	/**
	 * Returns the first usable scalar value from EXIF sections and keys.
	 *
	 * @param[in] array<int, array<string, mixed>> $sections EXIF sections.
	 * @param[in] array<int, string>               $keys     Candidate keys.
	 *
	 * @return string
	 */
	private function FirstExifValue(array $sections, array $keys): string
	{
		foreach ($sections as $section)
		{
			foreach ($keys as $key)
			{
				if (!array_key_exists($key, $section))
				{
					continue;
				}

				$value = $section[$key];

				if (\is_array($value))
				{
					$parts = array_values($value);

					if (count($parts) >= 2 && is_numeric($parts[0]) && is_numeric($parts[1]))
					{
						$value = (string) $parts[0] . '/' . (string) $parts[1];
					}
					else
					{
						$value = reset($value);
					}
				}

				if (!\is_scalar($value))
				{
					continue;
				}

				$text = trim((string) $value);

				if ($text !== '')
				{
					return $text;
				}
			}
		}

		return '';
	}

	/**
	 * Converts an EXIF rational or decimal string to a floating-point value.
	 *
	 * @param[in] string $value EXIF numeric value.
	 *
	 * @return float|null
	 */
	private function ExifRationalToFloat(string $value): ?float
	{
		$value = trim($value);

		if ($value === '')
		{
			return null;
		}

		if (str_contains($value, '/'))
		{
			[$numerator, $denominator] = array_pad(explode('/', $value, 2), 2, '0');
			$denominatorValue = (float) $denominator;

			if ($denominatorValue == 0.0)
			{
				return null;
			}

			return (float) $numerator / $denominatorValue;
		}

		return is_numeric($value) ? (float) $value : null;
	}

	/**
	 * Resolves a video poster filename from metadata or the automatic sidecar convention.
	 *
	 * @param[in] string               $absoluteFolder Absolute gallery folder.
	 * @param[in] string               $videoFilename  Video filename.
	 * @param[in] array<string, mixed> $metadata       Resolved metadata.
	 *
	 * @return string|null
	 */
	private function ResolvePosterFilename(string $absoluteFolder, string $videoFilename, array $metadata): ?string
	{
		$explicitPoster = trim((string) ($metadata['poster'] ?? ''));

		if ($explicitPoster !== '')
		{
			$basename = basename($explicitPoster);

			if ($basename === $explicitPoster && $this->DetectMediaType($basename) === 'image')
			{
				$path = Path::clean($absoluteFolder . '/' . $basename);

				if (is_file($path))
				{
					return $basename;
				}
			}
		}

		foreach (self::IMAGE_EXTENSIONS as $extension)
		{
			$candidate = $videoFilename . '.' . $extension;

			if (is_file(Path::clean($absoluteFolder . '/' . $candidate)))
			{
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * Sorts media files according to the configured mode.
	 *
	 * @param[in,out] array<int, string> $mediaFiles Media file paths.
	 * @param[in]     string             $sort       Sort mode.
	 * @param[in]     string             $sortOrder  Sort order.
	 *
	 * @return void
	 */
	private function SortMediaFiles(array &$mediaFiles, string $sort, string $sortOrder): void
	{
		if ($sort === 'random')
		{
			shuffle($mediaFiles);
			return;
		}

		usort(
			$mediaFiles,
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
	 * Builds display data for one image or video.
	 *
	 * @param[in] string               $absoluteMediaPath Absolute media path.
	 * @param[in] string               $absoluteFolder    Absolute gallery folder.
	 * @param[in] string               $publicFolderPath  Public gallery path.
	 * @param[in] array<string, mixed> $metadata          Resolved metadata.
	 * @param[in] int                  $thumbWidth        Thumbnail width.
	 * @param[in] int                  $thumbHeight       Thumbnail height.
	 * @param[in] boolean              $showCaptions      Whether grid/slider captions are shown.
	 * @param[in] boolean              $showMetadata      Whether lightbox metadata is shown.
	 * @param[in] array<int, string>   $metadataFields    Configured metadata fields.
	 * @param[in] string               $lightboxMode      Lightbox mode.
	 * @param[in] array<string, mixed> $galleryInfo       Gallery-level metadata.
	 *
	 * @return array<string, mixed>|null
	 */
	private function BuildMediaItemData(
		string $absoluteMediaPath,
		string $absoluteFolder,
		string $publicFolderPath,
		array $metadata,
		int $thumbWidth,
		int $thumbHeight,
		bool $showCaptions,
		bool $showMetadata,
		array $metadataFields,
		string $lightboxMode,
		array $galleryInfo
	): ?array
	{
		if (!is_file($absoluteMediaPath))
		{
			return null;
		}

		$filename = basename($absoluteMediaPath);
		$mediaType = $this->DetectMediaType($absoluteMediaPath);

		if ($mediaType === null)
		{
			return null;
		}

		$mediaUrl = $this->BuildPublicUrl(trim($publicFolderPath, '/') . '/' . $filename);
		$posterFilename = $mediaType === 'video'
			? $this->ResolvePosterFilename($absoluteFolder, $filename, $metadata)
			: null;
		$thumbnailSource = $mediaType === 'image'
			? $absoluteMediaPath
			: ($posterFilename !== null ? Path::clean($absoluteFolder . '/' . $posterFilename) : null);
		$thumbUrl = null;
		$posterUrl = null;

		if ($thumbnailSource !== null && is_file($thumbnailSource))
		{
			$thumbAbsolutePath = $this->GetOrCreateThumbnail($thumbnailSource, $thumbWidth, $thumbHeight);

			if ($thumbAbsolutePath !== null)
			{
				$thumbRelativePath = $this->AbsolutePathToRelativePath($thumbAbsolutePath);

				if ($thumbRelativePath !== null)
				{
					$thumbUrl = $this->BuildPublicUrl($thumbRelativePath);
				}
			}
		}

		if ($posterFilename !== null)
		{
			$posterUrl = $this->BuildPublicUrl(trim($publicFolderPath, '/') . '/' . $posterFilename);
		}

		if ($mediaType === 'image' && $thumbUrl === null)
		{
			$thumbUrl = $mediaUrl;
		}

		$captionData = $this->ResolveCaptionData($absoluteMediaPath, $metadata);
		$title = trim((string) ($metadata['title'] ?? ''));

		if ($title === '')
		{
			$title = $captionData['text'];
		}

		$alt = trim((string) ($metadata['alt'] ?? ''));

		if ($alt === '')
		{
			$alt = $title;
		}

		$dimensions = $mediaType === 'image' ? $this->GetImageDimensions($absoluteMediaPath) : '';
		$fileSize = filesize($absoluteMediaPath);
		$fileSizeText = $fileSize !== false ? $this->FormatFileSize((int) $fileSize) : '';
		$lightboxMetadata = $showMetadata
			? $this->BuildLightboxMetadata($metadata, $metadataFields, $filename, $dimensions, $fileSizeText)
			: [];
		$galleryTitle = trim((string) ($galleryInfo['title'] ?? ''));

		return [
			'filename' => $filename,
			'type' => $mediaType,
			'mediaUrl' => $mediaUrl,
			'thumbUrl' => $thumbUrl,
			'posterUrl' => $posterUrl,
			'alt' => $alt,
			'captionHtml' => $captionData['html'],
			'captionText' => $captionData['text'],
			'showCaption' => $showCaptions && $captionData['show'],
			'videoExtension' => strtoupper((string) pathinfo($filename, PATHINFO_EXTENSION)),
			'lightboxMode' => $lightboxMode,
			'lightboxPayload' => [
				'type' => $mediaType,
				'src' => $mediaUrl,
				'poster' => $posterUrl ?? '',
				'title' => $title,
				'description' => trim((string) ($metadata['description'] ?? '')),
				'galleryTitle' => $galleryTitle,
				'metadata' => $lightboxMetadata,
			],
		];
	}

	/**
	 * Builds a public URL from a Joomla-root-relative path.
	 *
	 * @param[in] string $relativePath Relative path.
	 *
	 * @return string
	 */
	private function BuildPublicUrl(string $relativePath): string
	{
		$segments = array_map(
			static fn (string $segment): string => rawurlencode($segment),
			explode('/', str_replace('\\', '/', ltrim($relativePath, '/')))
		);

		return Uri::root() . implode('/', $segments);
	}

	/**
	 * Resolves caption data for a media item.
	 *
	 * Legacy .txt sidecars have priority for gallery captions. Otherwise the
	 * resolved JSON title is used, followed by the filename-derived title.
	 *
	 * @param[in] string               $absoluteMediaPath Absolute media path.
	 * @param[in] array<string, mixed> $metadata          Resolved metadata.
	 *
	 * @return array{html: string, text: string, show: bool}
	 */
	private function ResolveCaptionData(string $absoluteMediaPath, array $metadata): array
	{
		$txtSidecarPath = $absoluteMediaPath . '.txt';

		if (is_file($txtSidecarPath))
		{
			$raw = file_get_contents($txtSidecarPath);

			if ($raw === false)
			{
				return [
					'html' => '',
					'text' => $this->BuildFilenameCaptionText($absoluteMediaPath),
					'show' => false,
				];
			}

			$content = trim($raw);

			if ($content === '')
			{
				return [
					'html' => '',
					'text' => $this->BuildFilenameCaptionText($absoluteMediaPath),
					'show' => false,
				];
			}

			$lines = preg_split('/\R/u', $content) ?: [];
			$firstLine = trim((string) ($lines[0] ?? ''));

			if (strcasecmp($firstLine, '!HTML') === 0)
			{
				array_shift($lines);
				$htmlContent = trim(implode("\n", $lines));
				$text = preg_replace('/<\s*br\s*\/?>/i', "\n", $htmlContent);
				$text = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $text)));

				return [
					'html' => $htmlContent,
					'text' => $text,
					'show' => true,
				];
			}

			return [
				'html' => nl2br(htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
				'text' => $content,
				'show' => true,
			];
		}

		$text = trim((string) ($metadata['title'] ?? ''));

		if ($text === '')
		{
			$text = $this->BuildFilenameCaptionText($absoluteMediaPath);
		}

		return [
			'html' => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
			'text' => $text,
			'show' => $text !== '',
		];
	}

	/**
	 * Builds a fallback title from a media filename.
	 *
	 * @param[in] string $absoluteMediaPath Absolute media path.
	 *
	 * @return string
	 */
	private function BuildFilenameCaptionText(string $absoluteMediaPath): string
	{
		$filename = pathinfo(basename($absoluteMediaPath), PATHINFO_FILENAME);
		$filename = urldecode($filename);
		$filename = trim(str_replace(['_', '-'], ' ', $filename));

		return ucwords($filename);
	}

	/**
	 * Builds localized lightbox metadata rows.
	 *
	 * @param[in] array<string, mixed> $metadata       Resolved item metadata.
	 * @param[in] array<int, string>   $fields         Fields enabled in plugin settings.
	 * @param[in] string               $filename       Media filename.
	 * @param[in] string               $dimensions     Image dimensions.
	 * @param[in] string               $fileSizeText   Human-readable file size.
	 *
	 * @return array<int, array{label: string, value: string}>
	 */
	private function BuildLightboxMetadata(
		array $metadata,
		array $fields,
		string $filename,
		string $dimensions,
		string $fileSizeText
	): array
	{
		$values = [
			'date' => trim((string) ($metadata['date'] ?? '')),
			'author' => trim((string) ($metadata['author'] ?? '')),
			'location' => trim((string) ($metadata['location'] ?? '')),
			'copyright' => trim((string) ($metadata['copyright'] ?? '')),
			'dimensions' => $dimensions,
			'filesize' => $fileSizeText,
			'filename' => $filename,
			'camera' => trim((string) ($metadata['camera'] ?? '')),
			'lens' => trim((string) ($metadata['lens'] ?? '')),
			'exposure' => trim((string) ($metadata['exposure'] ?? '')),
			'aperture' => trim((string) ($metadata['aperture'] ?? '')),
			'iso' => trim((string) ($metadata['iso'] ?? '')),
			'focal_length' => trim((string) ($metadata['focal_length'] ?? '')),
		];
		$labels = [
			'date' => Text::_('PLG_CONTENT_SIMPLEGALLERY_METADATA_DATE'),
			'author' => Text::_('PLG_CONTENT_SIMPLEGALLERY_METADATA_AUTHOR'),
			'location' => Text::_('PLG_CONTENT_SIMPLEGALLERY_METADATA_LOCATION'),
			'copyright' => Text::_('PLG_CONTENT_SIMPLEGALLERY_METADATA_COPYRIGHT'),
			'dimensions' => Text::_('PLG_CONTENT_SIMPLEGALLERY_METADATA_DIMENSIONS'),
			'filesize' => Text::_('PLG_CONTENT_SIMPLEGALLERY_METADATA_FILESIZE'),
			'filename' => Text::_('PLG_CONTENT_SIMPLEGALLERY_METADATA_FILENAME'),
			'camera' => Text::_('PLG_CONTENT_SIMPLEGALLERY_METADATA_CAMERA'),
			'lens' => Text::_('PLG_CONTENT_SIMPLEGALLERY_METADATA_LENS'),
			'exposure' => Text::_('PLG_CONTENT_SIMPLEGALLERY_METADATA_EXPOSURE'),
			'aperture' => Text::_('PLG_CONTENT_SIMPLEGALLERY_METADATA_APERTURE'),
			'iso' => Text::_('PLG_CONTENT_SIMPLEGALLERY_METADATA_ISO'),
			'focal_length' => Text::_('PLG_CONTENT_SIMPLEGALLERY_METADATA_FOCAL_LENGTH'),
		];
		$result = [];

		foreach ($fields as $field)
		{
			$value = trim((string) ($values[$field] ?? ''));

			if ($value === '')
			{
				continue;
			}

			$result[] = [
				'label' => (string) ($labels[$field] ?? $field),
				'value' => $value,
			];
		}

		return $result;
	}

	/**
	 * Returns image dimensions as a compact string.
	 *
	 * @param[in] string $absoluteImagePath Absolute image path.
	 *
	 * @return string
	 */
	private function GetImageDimensions(string $absoluteImagePath): string
	{
		$size = @getimagesize($absoluteImagePath);

		if ($size === false)
		{
			return '';
		}

		return (int) $size[0] . ' × ' . (int) $size[1] . ' px';
	}

	/**
	 * Formats a file size using binary units.
	 *
	 * @param[in] int $bytes File size in bytes.
	 *
	 * @return string
	 */
	private function FormatFileSize(int $bytes): string
	{
		$units = ['B', 'KiB', 'MiB', 'GiB'];
		$value = max(0, $bytes);
		$unitIndex = 0;

		while ($value >= 1024 && $unitIndex < count($units) - 1)
		{
			$value /= 1024;
			$unitIndex++;
		}

		$precision = $unitIndex === 0 ? 0 : ($value >= 10 ? 1 : 2);

		return number_format($value, $precision, '.', '') . ' ' . $units[$unitIndex];
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
			return '<!-- Punga Simple Gallery: invalid template layout -->';
		}

		$templatePath = __DIR__ . '/../../tmpl/' . $layoutName . '.php';
		ob_start();
		extract(['displayData' => $displayData], EXTR_SKIP);
		include $templatePath;

		return (string) ob_get_clean();
	}

	/**
	 * Creates or reuses a cached thumbnail for an image.
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

		if (!Folder::exists($absoluteCacheFolder) && !Folder::create($absoluteCacheFolder))
		{
			return null;
		}

		$sourceHash = sha1($absoluteImagePath . '|' . filemtime($absoluteImagePath) . '|' . $thumbWidth . 'x' . $thumbHeight);
		$sourceInfo = pathinfo($absoluteImagePath);
		$thumbFilename = ($sourceInfo['filename'] ?? 'thumb') . '_' . $sourceHash . '.jpg';
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
		$rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		if ($path !== $root && !str_starts_with($path, $rootPrefix))
		{
			return null;
		}

		$relative = ltrim(substr($path, strlen($root)), DIRECTORY_SEPARATOR);

		return str_replace(DIRECTORY_SEPARATOR, '/', $relative);
	}
}
