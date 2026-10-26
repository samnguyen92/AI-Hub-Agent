<?php
/**
 * Tab: Media Settings View (Merged WebP Converter, Registered Sizes & Force Regenerate Thumbnails)
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="align-media-settings-wrap">
	<!-- 1. WebP Media Converter Section -->
	<?php include __DIR__ . '/part-webp-converter.php'; ?>

	<!-- 2. Registered Media Sizes Section -->
	<?php include __DIR__ . '/part-media-sizes-table.php'; ?>

	<!-- 3. Force Thumbnail Regenerator Accordion Section -->
	<?php include __DIR__ . '/part-thumbnail-regenerator.php'; ?>

	<!-- 4. Media Filename Normalizer Accordion Section -->
	<?php include __DIR__ . '/part-media-normalizer.php'; ?>
</div>

