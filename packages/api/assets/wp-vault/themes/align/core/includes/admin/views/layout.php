<?php
/**
 * Main Layout for Align Admin Settings (Streamlined 8 Master Hubs Architecture).
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<style>
<?php readfile( __DIR__ . '/settings-layout.css' ); ?>
.align-status-badge{display:inline-block;font-size:11px;padding:3px 10px;background:#f8fafc;color:#64748b;border:1px solid #e2e8f0;border-radius:12px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;}
.align-status-badge.is-active{background:#f0fdf4;color:#16a34a;border-color:#bbf7d0;}
.align-status-badge.is-danger{background:#fff1f2;color:#dc2626;border-color:#fecaca;}
</style>

<div class="wrap align-settings-wrap">
	<h1 class="screen-reader-text"><?php esc_html_e( 'Align Theme Settings', 'align' ); ?></h1>
	<script>
		window.alignSettingsNonce = '<?php echo esc_js( wp_create_nonce( 'align_settings_nonce' ) ); ?>';
		window.alignRestNonce     = '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>';
	</script>

	<!-- Typography-First Segmented Nav Tabs (8 Master Hubs) -->
	<nav class="align-nav-tabs-bar" role="tablist">
		<a href="#site-protect" class="align-nav-tab is-active" data-tab="site-protect">
			<?php esc_html_e( 'Site Protection', 'align' ); ?>
		</a>
		<a href="#builder-studio" class="align-nav-tab" data-tab="builder-studio">
			<?php esc_html_e( 'Builder & AI', 'align' ); ?>
		</a>
		<a href="#seo-languages" class="align-nav-tab" data-tab="seo-languages">
			<?php esc_html_e( 'SEO & Multi-Language', 'align' ); ?>
		</a>
		<a href="#performance" class="align-nav-tab" data-tab="performance">
			<?php esc_html_e( 'Performance & Media', 'align' ); ?>
		</a>
		<a href="#system-tools" class="align-nav-tab" data-tab="system-tools">
			<?php esc_html_e( 'Developer Tools', 'align' ); ?>
		</a>
		<a href="#integrations" class="align-nav-tab" data-tab="integrations">
			<?php esc_html_e( 'Integrations', 'align' ); ?>
		</a>
		<a href="#api" class="align-nav-tab" data-tab="api">
			<?php esc_html_e( 'API', 'align' ); ?>
		</a>
		<a href="#updates" class="align-nav-tab" data-tab="updates">
			<?php esc_html_e( 'Updates', 'align' ); ?>
		</a>
	</nav>

	<!-- Main Settings Form -->
	<form method="post" action="options.php" id="align-settings-form" novalidate>
		<?php settings_fields( 'align_settings_group' ); ?>
		<?php wp_nonce_field( 'align_settings_nonce', 'align_settings_nonce' ); ?>

		<!-- Master Tab 1: Site Protection (Default Active) -->
		<div class="align-tab-content is-active" id="tab-site-protect">
			<?php include __DIR__ . '/tab-site-protection.php'; ?>
		</div>

		<!-- Master Tab 2: Builder & AI Studio -->
		<div class="align-tab-content" id="tab-builder-studio" style="display: none;">
			<?php include __DIR__ . '/tab-master-builder.php'; ?>
		</div>

		<!-- Master Tab 2: SEO & Multi-Language Hub (Combines Languages, Redirection, Matrix) -->
		<div class="align-tab-content" id="tab-seo-languages" style="display: none;">
			<?php include __DIR__ . '/tab-seo-languages.php'; ?>
		</div>

		<!-- Master Tab 3: Performance & Media -->
		<div class="align-tab-content" id="tab-performance" style="display: none;">
			<?php include __DIR__ . '/tab-master-performance.php'; ?>
		</div>

		<!-- Master Tab 4: Code & System Tools -->
		<div class="align-tab-content" id="tab-system-tools" style="display: none;">
			<?php include __DIR__ . '/tab-master-system.php'; ?>
		</div>

		<!-- Master Tab 6: Integrations -->
		<div class="align-tab-content" id="tab-integrations" style="display: none;">
			<?php include __DIR__ . '/tab-master-integrations.php'; ?>
		</div>

		<!-- Master Tab: Dedicated AI REST API -->
		<div class="align-tab-content" id="tab-api" style="display: none;">
			<?php include __DIR__ . '/tab-api.php'; ?>
		</div>

		<!-- Master Tab: Dedicated Git Updates Hub -->
		<div class="align-tab-content" id="tab-updates" style="display: none;">
			<?php include __DIR__ . '/tab-git-updater.php'; ?>
		</div>

		<!-- Sticky Save Bar -->
		<div class="align-sticky-save-bar">
			<button type="submit" name="submit" id="submit" class="button button-primary">
				<?php esc_html_e( 'Save Changes', 'align' ); ?>
			</button>
		</div>
	</form>
</div>

<script>
<?php
readfile( __DIR__ . '/settings-layout.js' );
readfile( __DIR__ . '/tab-redirections.js' );
readfile( __DIR__ . '/tab-translation-matrix.js' );
readfile( __DIR__ . '/tab-bulk-ai-translator.js' );
readfile( __DIR__ . '/tab-bulk-url-scanner.js' );
readfile( __DIR__ . '/tab-bulk-seo-generator.js' );
readfile( __DIR__ . '/tab-api.js' );
?>
</script>
