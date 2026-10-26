<?php
/**
 * Align Settings - Master Tab: Code & System Tools Hub
 *
 * Consolidates Custom CSS/JS/Tracking, Asset Loader per page, and Git Theme Updater.
 * Follows Align Settings Style Guide.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="align-master-tab-wrap" id="align-wrap-system-tools">
	<!-- Subnav Pills for Code & System Tools -->
	<div class="align-subnav-bar" role="tablist">
		<button type="button" class="align-subnav-pill is-active" data-subtab="sys-custom-code">
			<?php esc_html_e( 'Custom Code & Tracking', 'align' ); ?>
		</button>
		<button type="button" class="align-subnav-pill" data-subtab="sys-load-asset">
			<?php esc_html_e( 'Asset Loader', 'align' ); ?>
		</button>
	</div>

	<!-- Subtab 1: Custom Code & Tracking -->
	<div class="align-subtab-content is-active" id="subtab-sys-custom-code">
		<div class="align-settings-card">
			<div class="align-card-header">
				<div>
					<h3 class="align-card-title"><?php esc_html_e( 'Custom Code & Scripts', 'align' ); ?></h3>
					<p class="align-card-desc"><?php esc_html_e( 'Add custom CSS styles, JavaScript snippets, and tracking tags directly to your site.', 'align' ); ?></p>
				</div>
			</div>
			<div class="align-card-body">
				<?php include __DIR__ . '/tab-custom-code.php'; ?>
			</div>
		</div>
	</div>

	<!-- Subtab 2: Asset Loader -->
	<div class="align-subtab-content" id="subtab-sys-load-asset" style="display:none;">
		<?php include __DIR__ . '/tab-load-assets.php'; ?>
	</div>
</div>
