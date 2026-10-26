<?php
/**
 * Align Settings - Master Tab: Performance & Media Hub
 *
 * Consolidates Media & WebP Converter, Scripts & Acceleration (Lenis, Splide, Delay JS/CSS),
 * and HTML Output Minification.
 * Follows Align Settings Style Guide.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="align-master-tab-wrap" id="align-wrap-performance">
	<!-- Subnav Pills for Performance & Media -->
	<div class="align-subnav-bar" role="tablist">
		<button type="button" class="align-subnav-pill is-active" data-subtab="perf-media">
			<?php esc_html_e( 'Media & WebP', 'align' ); ?>
		</button>
		<button type="button" class="align-subnav-pill" data-subtab="perf-plugins">
			<?php esc_html_e( 'Scripts Delay', 'align' ); ?>
		</button>
		<button type="button" class="align-subnav-pill" data-subtab="perf-minify">
			<?php esc_html_e( 'HTML Minify', 'align' ); ?>
		</button>
	</div>

	<!-- Subtab 1: Media & WebP -->
	<div class="align-subtab-content is-active" id="subtab-perf-media">
		<?php include __DIR__ . '/tab-media.php'; ?>
	</div>

	<!-- Subtab 2: Scripts & Acceleration -->
	<div class="align-subtab-content" id="subtab-perf-plugins" style="display:none;">
		<div class="align-settings-card">
			<div class="align-card-header">
				<div>
					<h3 class="align-card-title"><?php esc_html_e( 'Plugins & Enhancements', 'align' ); ?></h3>
					<p class="align-card-desc"><?php esc_html_e( 'Configure integrated libraries: Lenis Smooth Scroll, Splide Slider, and Delay JS/CSS optimization.', 'align' ); ?></p>
				</div>
			</div>
			<div class="align-card-body">
				<?php include __DIR__ . '/tab-plugins.php'; ?>
			</div>
		</div>
	</div>

	<!-- Subtab 3: Minify Output -->
	<div class="align-subtab-content" id="subtab-perf-minify" style="display:none;">
		<div class="align-settings-card">
			<div class="align-card-header">
				<div>
					<h3 class="align-card-title"><?php esc_html_e( 'HTML Output Minification', 'align' ); ?></h3>
					<p class="align-card-desc"><?php esc_html_e( 'Optimize frontend payload size by compressing rendered HTML output.', 'align' ); ?></p>
				</div>
			</div>
			<div class="align-card-body">
				<?php include __DIR__ . '/tab-minify.php'; ?>
			</div>
		</div>
	</div>
</div>
