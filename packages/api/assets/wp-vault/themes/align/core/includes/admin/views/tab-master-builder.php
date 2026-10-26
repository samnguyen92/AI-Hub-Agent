<?php
/**
 * Align Settings - Master Tab: Builder & AI Studio Hub
 *
 * Consolidates Visual Builder settings, Connect AI API, and HTML ZIP Importer.
 * Follows Align Settings Style Guide.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="align-master-tab-wrap" id="align-wrap-builder-studio">
	<!-- Subnav Pills for Builder & AI Studio -->
	<div class="align-subnav-bar" role="tablist">
		<button type="button" class="align-subnav-pill is-active" data-subtab="builder-settings">
			<?php esc_html_e( 'Builder Config', 'align' ); ?>
		</button>
		<button type="button" class="align-subnav-pill" data-subtab="builder-connect-api">
			<?php esc_html_e( 'AI & Cloud API', 'align' ); ?>
		</button>
		<button type="button" class="align-subnav-pill" data-subtab="builder-import-zip">
			<?php esc_html_e( 'ZIP Importer', 'align' ); ?>
		</button>
	</div>

	<!-- Subtab 1: Visual Builder Settings -->
	<div class="align-subtab-content is-active" id="subtab-builder-settings">
		<?php include __DIR__ . '/tab-builder.php'; ?>
	</div>

	<!-- Subtab 2: Connect API -->
	<div class="align-subtab-content" id="subtab-builder-connect-api" style="display:none;">
		<?php include __DIR__ . '/tab-connect-api.php'; ?>
	</div>

	<!-- Subtab 3: Import HTML ZIP -->
	<div class="align-subtab-content" id="subtab-builder-import-zip" style="display:none;">
		<?php include __DIR__ . '/tab-import-zip.php'; ?>
	</div>
</div>
