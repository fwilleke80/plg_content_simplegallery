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
$sliderId = 'simplegallery-slider-' . uniqid();

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
	<div class="simplegallery-slider" id="<?= $e($sliderId); ?>">

		<?php foreach ($items as $index => $item) : ?>
			<input
				type="radio"
				name="<?= $e($sliderId); ?>"
				id="<?= $e($sliderId . '-slide-' . $index); ?>"
				class="simplegallery-slider-radio"
				<?= $index === 0 ? 'checked="checked"' : ''; ?>
			>
		<?php endforeach; ?>

		<div class="simplegallery-slider-viewport">
			<div class="simplegallery-slider-track">
				<?php foreach ($items as $index => $item) : ?>
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

		<div class="simplegallery-slider-nav">
			<?php foreach ($items as $index => $item) : ?>
				<label
					for="<?= $e($sliderId . '-slide-' . $index); ?>"
					class="simplegallery-slider-dot"
					aria-label="<?= $e('Show slide ' . (string) ($index + 1)); ?>"
				></label>
			<?php endforeach; ?>
		</div>
	</div>
<?php endif; ?>