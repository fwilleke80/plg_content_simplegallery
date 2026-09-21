<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.PungaSimpleGallery
 *
 * @copyright   (C) 2026
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/**
 * @var array<string, mixed> $displayData
 */

$items = $displayData['items'] ?? [];
$columns = (int) ($displayData['columns'] ?? 4);
$lightboxMode = (string) ($displayData['lightboxMode'] ?? 'builtin');
$lightboxInfoPosition = (string) ($displayData['lightboxInfoPosition'] ?? 'side');
$galleryTitle = trim((string) ($displayData['galleryTitle'] ?? ''));

/**
 * Escapes a string for safe HTML output.
 *
 * @param[in] string $value Value to escape.
 *
 * @return string
 */
$e = static function (string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>

<div
	class="simplegallery-gallery"
	data-simplegallery-gallery
	data-simplegallery-label-close="<?= $e(Text::_('PLG_CONTENT_SIMPLEGALLERY_CLOSE_LIGHTBOX')); ?>"
	data-simplegallery-label-previous="<?= $e(Text::_('PLG_CONTENT_SIMPLEGALLERY_PREVIOUS_ITEM')); ?>"
	data-simplegallery-label-next="<?= $e(Text::_('PLG_CONTENT_SIMPLEGALLERY_NEXT_ITEM')); ?>"
	data-simplegallery-label-dialog="<?= $e(Text::_('PLG_CONTENT_SIMPLEGALLERY_LIGHTBOX_DIALOG')); ?>"
	data-simplegallery-info-position="<?= $e($lightboxInfoPosition); ?>"
	<?php if ($galleryTitle !== '') : ?>aria-label="<?= $e($galleryTitle); ?>"<?php endif; ?>
>
	<?php if (empty($items)) : ?>
		<div class="simplegallery-empty"><?= $e(Text::_('PLG_CONTENT_SIMPLEGALLERY_NO_MEDIA_FOUND')); ?></div>
	<?php else : ?>
		<div class="simplegallery-grid" style="--simplegallery-columns: <?= $e((string) $columns); ?>;">
			<?php foreach ($items as $item) : ?>
				<?php
				$payload = json_encode(
					$item['lightboxPayload'] ?? [],
					JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
				);
				$payload = $payload === false ? '{}' : $payload;
				$isVideo = ($item['type'] ?? '') === 'video';
				?>
				<div class="simplegallery-item">
					<a
						class="simplegallery-thumb-link<?= $isVideo ? ' simplegallery-video-link' : ''; ?>"
						href="<?= $e((string) $item['mediaUrl']); ?>"
						<?php if ($lightboxMode === 'external') : ?>rel="Lightbox"<?php endif; ?>
						<?php if ($lightboxMode === 'builtin') : ?>data-simplegallery-item data-simplegallery-payload="<?= $e($payload); ?>"<?php endif; ?>
						aria-label="<?= $e((string) ($item['captionText'] ?? $item['filename'] ?? '')); ?>"
					>
						<?php if (!empty($item['thumbUrl'])) : ?>
							<img
								class="simplegallery-thumb"
								src="<?= $e((string) $item['thumbUrl']); ?>"
								alt="<?= $e((string) ($item['alt'] ?? '')); ?>"
								loading="lazy"
							>
						<?php else : ?>
							<span class="simplegallery-video-placeholder" aria-hidden="true">
								<span class="simplegallery-video-placeholder-icon">&#9654;</span>
								<span class="simplegallery-video-placeholder-type"><?= $e((string) ($item['videoExtension'] ?? 'VIDEO')); ?></span>
							</span>
						<?php endif; ?>

						<?php if ($isVideo) : ?>
							<span class="simplegallery-play-overlay" aria-hidden="true">&#9654;</span>
						<?php endif; ?>
					</a>

					<?php if (!empty($item['showCaption'])) : ?>
						<div class="simplegallery-caption">
							<?= (string) $item['captionHtml']; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
