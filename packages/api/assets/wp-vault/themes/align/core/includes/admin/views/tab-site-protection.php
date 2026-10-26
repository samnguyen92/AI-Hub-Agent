<?php
/**
 * Align Settings - Subtab View: Site Protection & Access Control
 *
 * Provides settings to password-protect the site via HTTP Basic Authentication
 * and enforce a multi-tier search engine no-index barrier.
 * Strictly adheres to the Align Settings Style Guide (Zero Emojis, Transparent Buttons, Card System).
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$manual_enabled   = (bool) get_option( 'align_site_protect_enabled', 0 );
$is_enabled       = class_exists( 'Align_Site_Protection' ) ? Align_Site_Protection::is_enabled() : $manual_enabled;
$current_host     = class_exists( 'Align_Site_Protection' ) ? Align_Site_Protection::get_current_host() : '';
$current_env      = class_exists( 'Align_Site_Protection' ) ? Align_Site_Protection::get_current_environment() : 'custom';
$live_domain      = (string) get_option( 'align_site_protect_live_domain', '' );
$staging_domain   = (string) get_option( 'align_site_protect_staging_domain', '' );
$username         = (string) get_option( 'align_site_protect_user', 'staging' );
$password         = (string) get_option( 'align_site_protect_password', '' );
$auto_noindex     = (bool) get_option( 'align_site_protect_auto_noindex', 1 );
$bypass_localhost = (bool) get_option( 'align_site_protect_bypass_localhost', 1 );
$bypass_admins    = (bool) get_option( 'align_site_protect_bypass_admins', 1 );
$cookie_days      = absint( get_option( 'align_site_protect_cookie_days', 30 ) );
?>

<div class="align-settings-card">
	<div class="align-card-header">
		<div>
			<h3 class="align-card-title"><?php esc_html_e( 'Site Protection & Access Control', 'align' ); ?></h3>
			<p class="align-card-desc"><?php esc_html_e( 'HTTP Basic Authentication prompt and automated search engine no-index barrier.', 'align' ); ?></p>
		</div>
		<div>
			<?php if ( $is_enabled ) : ?>
				<span class="align-status-badge is-active"><?php esc_html_e( 'Active', 'align' ); ?></span>
			<?php else : ?>
				<span class="align-status-badge"><?php esc_html_e( 'Disabled', 'align' ); ?></span>
			<?php endif; ?>
		</div>
	</div>
	<div class="align-card-body">

		<?php /* Environment domain row */ ?>
		<?php if ( $current_host ) : ?>
		<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
			<div style="font-size:13px;color:#475569;">
				<strong><?php esc_html_e( 'Current Host:', 'align' ); ?></strong>
				<code style="background:#e2e8f0;padding:2px 8px;border-radius:4px;margin-left:6px;"><?php echo esc_html( $current_host ); ?></code>
			</div>
			<?php
			$env_labels = array(
				'live'    => array( 'label' => __( 'Live', 'align' ), 'color' => '#16a34a', 'bg' => '#f0fdf4', 'border' => '#bbf7d0' ),
				'staging' => array( 'label' => __( 'Staging', 'align' ), 'color' => '#d97706', 'bg' => '#fffbeb', 'border' => '#fde68a' ),
				'custom'  => array( 'label' => __( 'Manual', 'align' ), 'color' => '#64748b', 'bg' => '#f8fafc', 'border' => '#e2e8f0' ),
			);
			$env_info = $env_labels[ $current_env ] ?? $env_labels['custom'];
			?>
			<span style="font-size:11px;padding:3px 10px;background:<?php echo esc_attr( $env_info['bg'] ); ?>;color:<?php echo esc_attr( $env_info['color'] ); ?>;border:1px solid <?php echo esc_attr( $env_info['border'] ); ?>;border-radius:12px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">
				<?php echo esc_html( $env_info['label'] ); ?>
			</span>
		</div>
		<?php if ( 'custom' === $current_env ) : ?>
		<p style="font-size:12px;color:#64748b;margin:-10px 0 16px;padding:8px 14px;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;">
			<?php esc_html_e( 'No environment domains configured. Automatic switching is inactive. Site protection operates in Manual Mode — you can manually toggle protection on or off below to prevent conflicts.', 'align' ); ?>
		</p>
		<?php endif; ?>
		<?php endif; ?>

		<?php /* Domain config */ ?>
		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-bottom:24px;">
			<div>
				<label for="align_site_protect_live_domain" style="display:block;font-weight:600;font-size:13px;color:#1e293b;margin-bottom:6px;"><?php esc_html_e( 'Live Production Domain', 'align' ); ?></label>
				<input type="text" id="align_site_protect_live_domain" name="align_site_protect_live_domain" value="<?php echo esc_attr( $live_domain ); ?>" class="regular-text" style="width:100%;height:34px;padding:0 10px;border:1px solid #cbd5e1;border-radius:6px;" placeholder="example.com">
				<p class="description" style="font-size:12px;color:#64748b;margin-top:4px;"><?php esc_html_e( 'Production domain. Protection and no-index barriers will always be disabled on this host.', 'align' ); ?></p>
			</div>
			<div>
				<label for="align_site_protect_staging_domain" style="display:block;font-weight:600;font-size:13px;color:#1e293b;margin-bottom:6px;"><?php esc_html_e( 'Staging Domain', 'align' ); ?></label>
				<input type="text" id="align_site_protect_staging_domain" name="align_site_protect_staging_domain" value="<?php echo esc_attr( $staging_domain ); ?>" class="regular-text" style="width:100%;height:34px;padding:0 10px;border:1px solid #cbd5e1;border-radius:6px;" placeholder="staging.example.com">
				<p class="description" style="font-size:12px;color:#64748b;margin-top:4px;"><?php esc_html_e( 'Staging or preview domain. Protection and no-index barriers will automatically be enforced on this host.', 'align' ); ?></p>
			</div>
		</div>

		<div class="align-setting-row" style="padding-bottom:16px;border-bottom:1px solid #e2e8f0;margin-bottom:20px;">
			<div style="font-weight:600;font-size:14px;margin-bottom:8px;color:#0f172a;"><?php esc_html_e( 'Enable Site Protection', 'align' ); ?></div>
			<input type="hidden" name="align_site_protect_enabled" value="0">
			<label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
				<input type="checkbox" id="align_site_protect_enabled" name="align_site_protect_enabled" value="1" <?php checked( 1, $manual_enabled ); ?>>
				<span style="font-size:13.5px;color:#334155;"><?php esc_html_e( 'Prompt visitors with native HTTP Basic Authentication alert before loading any page or asset.', 'align' ); ?></span>
			</label>
		</div>

		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-bottom:24px;">
			<div>
				<label for="align_site_protect_user" style="display:block;font-weight:600;font-size:13px;color:#1e293b;margin-bottom:6px;"><?php esc_html_e( 'Protection Username', 'align' ); ?></label>
				<input type="text" id="align_site_protect_user" name="align_site_protect_user" value="<?php echo esc_attr( $username ); ?>" class="regular-text" style="width:100%;height:34px;padding:0 10px;border:1px solid #cbd5e1;border-radius:6px;" placeholder="staging">
				<p class="description" style="font-size:12px;color:#64748b;margin-top:4px;"><?php esc_html_e( 'The required username for the browser prompt (default: staging).', 'align' ); ?></p>
			</div>
			<div>
				<label for="align_site_protect_password" style="display:block;font-weight:600;font-size:13px;color:#1e293b;margin-bottom:6px;"><?php esc_html_e( 'Protection Password', 'align' ); ?></label>
				<div style="display:flex;gap:8px;">
					<input type="password" id="align_site_protect_password" name="align_site_protect_password" value="<?php echo esc_attr( $password ); ?>" class="regular-text" style="width:100%;height:34px;padding:0 10px;border:1px solid #cbd5e1;border-radius:6px;" placeholder="<?php esc_attr_e( 'Set access password', 'align' ); ?>" autocomplete="new-password">
					<button type="button" id="align-toggle-pass-btn" class="button button-secondary"><?php esc_html_e( 'Show', 'align' ); ?></button>
				</div>
				<p class="description" style="font-size:12px;color:#64748b;margin-top:4px;"><?php esc_html_e( 'Leaving password blank will keep protection inactive.', 'align' ); ?></p>
			</div>
		</div>

		<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:16px;margin-bottom:24px;">
			<div style="font-weight:600;font-size:13.5px;color:#0f172a;margin-bottom:12px;"><?php esc_html_e( 'Bypass & Protection Rules', 'align' ); ?></div>
			<div style="margin-bottom:10px;">
				<input type="hidden" name="align_site_protect_auto_noindex" value="0">
				<label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;">
					<input type="checkbox" name="align_site_protect_auto_noindex" value="1" <?php checked( 1, $auto_noindex ); ?> style="margin-top:2px;">
					<div>
						<span style="font-weight:500;font-size:13px;color:#1e293b;"><?php esc_html_e( 'Automated Full-Site No-Index Barrier', 'align' ); ?></span>
						<p style="margin:2px 0 0;font-size:12px;color:#64748b;"><?php esc_html_e( 'Sends X-Robots-Tag: noindex HTTP header, injects noindex meta tag, and updates robots.txt to prevent search engine indexing.', 'align' ); ?></p>
					</div>
				</label>
			</div>
			<div style="margin-bottom:10px;">
				<input type="hidden" name="align_site_protect_bypass_localhost" value="0">
				<label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;">
					<input type="checkbox" name="align_site_protect_bypass_localhost" value="1" <?php checked( 1, $bypass_localhost ); ?> style="margin-top:2px;">
					<div>
						<span style="font-weight:500;font-size:13px;color:#1e293b;"><?php esc_html_e( 'Bypass on Localhost & Dev Environments', 'align' ); ?></span>
						<p style="margin:2px 0 0;font-size:12px;color:#64748b;"><?php esc_html_e( 'Automatically skips password prompt on localhost, 127.0.0.1, .local, .test, .internal, and local dev containers.', 'align' ); ?></p>
					</div>
				</label>
			</div>
			<div>
				<input type="hidden" name="align_site_protect_bypass_admins" value="0">
				<label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;">
					<input type="checkbox" name="align_site_protect_bypass_admins" value="1" <?php checked( 1, $bypass_admins ); ?> style="margin-top:2px;">
					<div>
						<span style="font-weight:500;font-size:13px;color:#1e293b;"><?php esc_html_e( 'Bypass for Logged-in Administrators', 'align' ); ?></span>
						<p style="margin:2px 0 0;font-size:12px;color:#64748b;"><?php esc_html_e( 'Users already logged in with manage_options capability will not be prompted for password.', 'align' ); ?></p>
					</div>
				</label>
			</div>
		</div>

		<div style="max-width:320px;">
			<label for="align_site_protect_cookie_days" style="display:block;font-weight:600;font-size:13px;color:#1e293b;margin-bottom:6px;"><?php esc_html_e( 'Remember Access (Days)', 'align' ); ?></label>
			<input type="number" id="align_site_protect_cookie_days" name="align_site_protect_cookie_days" value="<?php echo esc_attr( $cookie_days ); ?>" min="1" max="365" style="height:34px;padding:0 10px;border:1px solid #cbd5e1;border-radius:6px;width:100px;">
			<p class="description" style="font-size:12px;color:#64748b;margin-top:4px;"><?php esc_html_e( 'Valid access cookie duration. Once authenticated, users will not be prompted again on this device until expired.', 'align' ); ?></p>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var passInput = document.getElementById('align_site_protect_password');
	var toggleBtn = document.getElementById('align-toggle-pass-btn');
	if (passInput && toggleBtn) {
		toggleBtn.addEventListener('click', function() {
			if (passInput.type === 'password') {
				passInput.type = 'text';
				toggleBtn.textContent = '<?php echo esc_js( __( 'Hide', 'align' ) ); ?>';
			} else {
				passInput.type = 'password';
				toggleBtn.textContent = '<?php echo esc_js( __( 'Show', 'align' ) ); ?>';
			}
		});
	}
});
</script>
