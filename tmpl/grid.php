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
$columns = (int) ($displayData['columns'] ?? 4);

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
	<div class="simplegallery-grid" style="--simplegallery-columns: <?= $e((string) $columns); ?>;">
		<?php foreach ($items as $item) : ?>
			<div class="simplegallery-item">
				<a
					class="simplegallery-thumb-link"
					href="<?= $e((string) $item['fullImageUrl']); ?>"
					rel="Lightbox"
				>
					<img
						class="simplegallery-thumb"
						src="<?= $e((string) $item['thumbUrl']); ?>"
						alt="<?= $e((string) $item['caption']); ?>"
						loading="lazy"
					>
				</a>

				<?php if (!empty($item['showCaption'])) : ?>
					<div class="simplegallery-caption">
						<?= $e((string) $item['caption']); ?>
					</div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>