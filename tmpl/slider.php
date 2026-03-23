<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.Simplegallery
 *
 * @copyright   (C) 2026
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

/**
 * @var array<string, mixed> $displayData
 */

$items = $displayData['items'] ?? [];
$showNavigation = count($items) > 1;

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

<?php if (empty($items)) : ?>
	<div class="simplegallery-empty">No images found.</div>
<?php else : ?>
	<div
		class="simplegallery-slider"
		data-simplegallery-slider
		tabindex="0"
	>
		<div class="simplegallery-slider-main">
			<button
				type="button"
				class="simplegallery-slider-arrow simplegallery-slider-arrow-prev"
				aria-label="Previous image"
			>
				&#10094;
			</button>

			<div class="simplegallery-slider-viewport">
				<div class="simplegallery-slider-track">
					<?php foreach ($items as $item) : ?>
						<div class="simplegallery-slide">
							<a
								href="<?= $e((string) $item['fullImageUrl']); ?>"
								rel="Lightbox"
								class="simplegallery-slide-link"
							>
								<img
									src="<?= $e((string) $item['thumbUrl']); ?>"
									alt="<?= $e((string) $item['caption']); ?>"
									class="simplegallery-slide-image"
								>
							</a>

							<?php if (!empty($item['showCaption'])) : ?>
								<div class="simplegallery-slide-caption">
									<?= $e((string) $item['caption']); ?>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<button
				type="button"
				class="simplegallery-slider-arrow simplegallery-slider-arrow-next"
				aria-label="Next image"
			>
				&#10095;
			</button>
		</div>

		<div class="simplegallery-slider-nav">
			<?php foreach ($items as $index => $item) : ?>
				<button
					type="button"
					class="simplegallery-slider-dot"
					data-slide-index="<?= $e((string) $index); ?>"
					aria-label="<?= $e('Show slide ' . (string) ($index + 1)); ?>"
				></button>
			<?php endforeach; ?>
		</div>
	</div>
<?php endif; ?>