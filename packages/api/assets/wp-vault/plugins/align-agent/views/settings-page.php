<?php
/**
 * Align Agent Gateway - Admin Settings Page View
 *
 * Renders the License Activation Gate when unactivated, or the 4-tab dashboard
 * (Bridge, MCP Tools, SEO, Updates) when an active license key is bound.
 *
 * @package Align_Agent
 * @subpackage Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bridge_cfg       = get_option( 'align_agent_bridge_settings', array() );
$bridge_cfg       = is_array( $bridge_cfg ) ? $bridge_cfg : array();
$saved_mid_url    = (string) get_option( Align_Gateway_Admin::OPTION_MID_URL, '' );
$b_middleware_url = '' !== $saved_mid_url ? $saved_mid_url : ( ! empty( $bridge_cfg['middleware_url'] ) ? $bridge_cfg['middleware_url'] : 'http://localhost:3000' );
?>
<div class="wrap align-agent-wrap">
	<?php echo $notice_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<header class="align-agent-header">
		<h1 class="align-agent-main-title"><?php esc_html_e( 'Align Agent Gateway', 'align-agent' ); ?></h1>
		<p class="align-agent-sub-title">
			<?php esc_html_e( 'Autonomous AI Agent & Remote Bot Gateway for WordPress and Align Theme.', 'align-agent' ); ?>
		</p>
	</header>

	<main class="align-agent-main-column">
	<?php if ( ! $is_license_active ) : ?>
		<section class="align-agent-card align-license-gate-card" data-step="license-activation-gate">
			<header class="align-agent-card-header">
				<h2 class="align-agent-card-title">
					<span class="dashicons dashicons-lock"></span>
					<span><?php esc_html_e( 'Activate Align Agent License', 'align-agent' ); ?></span>
				</h2>
				<p class="align-agent-card-desc">
					<?php esc_html_e( 'Enter your Middleware Hub URL and Client API Key (ak_...) issued for this domain to unlock the Align Agent workspace and Global Chatbox.', 'align-agent' ); ?>
				</p>
			</header>

			<div class="align-agent-card-body">
				<div class="align-bridge-field">
					<label class="align-bridge-label" for="align_license_middleware_url"><?php esc_html_e( 'Middleware URL', 'align-agent' ); ?></label>
					<div class="align-agent-input-group">
						<input type="url" id="align_license_middleware_url" name="align_license_middleware_url" class="regular-text align-agent-mono-input" value="<?php echo esc_attr( $b_middleware_url ); ?>" placeholder="http://localhost:3000">
					</div>
					<p class="description"><?php esc_html_e( 'Base URL of the Align Middleware Hub service.', 'align-agent' ); ?></p>
				</div>

				<div class="align-bridge-field">
					<label class="align-bridge-label" for="align_license_key"><?php esc_html_e( 'License Key', 'align-agent' ); ?></label>
					<div class="align-agent-input-group">
						<input type="password" id="align_license_key" name="align_license_key" class="regular-text align-agent-mono-input" value="<?php echo esc_attr( $license_key ); ?>" autocomplete="new-password" placeholder="ak_...">
						<button type="button" class="button button-secondary align-agent-btn" id="align-license-toggle-key-btn" aria-label="<?php esc_attr_e( 'Toggle license key visibility', 'align-agent' ); ?>">
							<span class="dashicons dashicons-visibility"></span>
							<span id="align-license-toggle-key-text"><?php esc_html_e( 'Show', 'align-agent' ); ?></span>
						</button>
					</div>
					<p class="description"><?php esc_html_e( 'Paste the per-site key generated from the Middleware Hub dashboard.', 'align-agent' ); ?></p>
				</div>

				<div class="align-bridge-field">
					<span class="align-bridge-label"><?php esc_html_e( 'Site Domain', 'align-agent' ); ?></span>
					<div>
						<code class="align-agent-code-badge"><?php echo esc_html( $site_domain ); ?></code>
					</div>
					<p class="description"><?php esc_html_e( 'Your license key will be verified and bound to this normalized domain.', 'align-agent' ); ?></p>
				</div>

				<div class="align-agent-actions">
					<button type="button" id="align-agent-activate-license-btn" class="button button-primary align-agent-btn-primary" aria-label="<?php esc_attr_e( 'Activate License', 'align-agent' ); ?>" data-action="activate-license">
						<span class="dashicons dashicons-yes-alt"></span>
						<span><?php esc_html_e( 'Activate License', 'align-agent' ); ?></span>
					</button>
					<div id="align-agent-license-status" class="align-agent-status-msg" role="status" aria-live="polite" style="display: none;"></div>
				</div>
			</div>
		</section>
	<?php else : ?>
		<?php
		$b_bot_token        = ! empty( $bridge_cfg['bot_token'] ) ? $bridge_cfg['bot_token'] : $telegram_bot_token;
		$b_allowed_user_ids = isset( $bridge_cfg['allowed_user_ids'] ) ? trim( (string) $bridge_cfg['allowed_user_ids'] ) : '';
		if ( '' === $b_allowed_user_ids || '111222333, 444555666' === $b_allowed_user_ids ) {
			$b_allowed_user_ids = (string) Align_Gateway_Telegram::get_chat_id();
		}
		$raw_llm_base_url    = ! empty( $bridge_cfg['llm_base_url'] ) ? $bridge_cfg['llm_base_url'] : 'https://api.lsia.site/v1';
		$b_llm_base_url      = preg_replace( '#/chat/completions/?$#i', '', rtrim( $raw_llm_base_url, '/' ) );
		$b_llm_api_key       = $bridge_cfg['llm_api_key'] ?? $ai_api_key;
		$b_llm_model         = ! empty( $bridge_cfg['llm_model'] ) ? $bridge_cfg['llm_model'] : 'gpt-4o';
		$b_wp_username       = wp_get_current_user()->user_login;
		$b_wp_app_password   = $bridge_cfg['wp_app_password'] ?? '';
		$app_pass_guide_link = admin_url( 'profile.php#application-passwords-section' );

		$lic_site_id = ! empty( $license_data['siteId'] ) ? (string) $license_data['siteId'] : $site_domain;
		$lic_domain  = ! empty( $license_data['domain'] ) ? (string) $license_data['domain'] : $site_domain;
		$masked_key  = strlen( $license_key ) > 10
			? substr( $license_key, 0, 6 ) . '••••' . substr( $license_key, -4 )
			: 'ak_••••';
		?>
		<nav class="align-nav-tabs-bar" role="tablist" aria-label="<?php esc_attr_e( 'Align Agent Settings Tabs', 'align-agent' ); ?>">
			<button type="button" role="tab" class="align-nav-tab is-active" data-align-tab="bridge"><span class="dashicons dashicons-networking"></span><span><?php esc_html_e( 'Bridge', 'align-agent' ); ?></span></button>
			<button type="button" role="tab" class="align-nav-tab" data-align-tab="mcp"><span class="dashicons dashicons-admin-tools"></span><span><?php esc_html_e( 'MCP Tools', 'align-agent' ); ?></span></button>
			<button type="button" role="tab" class="align-nav-tab" data-align-tab="seo"><span class="dashicons dashicons-search"></span><span><?php esc_html_e( 'SEO', 'align-agent' ); ?></span></button>
			<button type="button" role="tab" class="align-nav-tab" data-align-tab="updates"><span class="dashicons dashicons-update"></span><span><?php esc_html_e( 'Updates', 'align-agent' ); ?></span></button>
		</nav>

		<!-- PANEL: Bridge -->
		<div class="align-agent-tab-panel is-active" role="tabpanel" data-align-panel="bridge">
			<div class="align-license-status-bar" data-step="license-status-bar">
				<div class="align-license-status-meta">
					<span class="align-badge align-badge-post"><?php esc_html_e( 'Active', 'align-agent' ); ?></span>
					<span class="align-license-meta-item"><strong><?php esc_html_e( 'Site ID:', 'align-agent' ); ?></strong> <code><?php echo esc_html( $lic_site_id ); ?></code></span>
					<span class="align-license-meta-item"><strong><?php esc_html_e( 'Domain:', 'align-agent' ); ?></strong> <code><?php echo esc_html( $lic_domain ); ?></code></span>
					<span class="align-license-meta-item"><strong><?php esc_html_e( 'Key:', 'align-agent' ); ?></strong> <code><?php echo esc_html( $masked_key ); ?></code></span>
				</div>
				<button type="button" class="button button-secondary align-agent-btn" id="align-agent-deactivate-license-btn" aria-label="<?php esc_attr_e( 'Change License Key', 'align-agent' ); ?>" data-action="deactivate-license">
					<span class="dashicons dashicons-admin-network"></span>
					<span><?php esc_html_e( 'Change Key', 'align-agent' ); ?></span>
				</button>
			</div>

			<div class="align-bridge-grid" data-step="bridge-config">
				<!-- Card 1 (Left Column): 1. Telegram Bot & Pairing -->
				<section class="align-agent-card align-bridge-card" data-step="bridge-telegram-card">
					<header class="align-agent-card-header">
						<h2 class="align-agent-card-title">
							<span class="dashicons dashicons-format-chat"></span>
							<span><?php esc_html_e( '1. Telegram Bot & Pairing', 'align-agent' ); ?></span>
						</h2>
						<p class="align-agent-card-desc">
							<?php esc_html_e( 'Connect your Telegram Bot and pair your Chat ID with a 6-digit verification code.', 'align-agent' ); ?>
						</p>
					</header>
					<div class="align-agent-card-body">
						<div class="align-bridge-field">
							<label class="align-bridge-label" for="align_bridge_bot_token"><?php esc_html_e( 'Telegram Bot Token', 'align-agent' ); ?></label>
							<div class="align-agent-input-group">
								<input type="password" id="align_bridge_bot_token" name="align_bridge_bot_token" class="regular-text align-agent-mono-input" value="<?php echo esc_attr( $b_bot_token ); ?>" autocomplete="new-password" placeholder="123456789:ABCdefGHIjklMNOpqrSTUvwxYZ">
								<button type="button" class="button button-secondary align-agent-btn" id="align-bridge-toggle-bot-token-btn" aria-label="<?php esc_attr_e( 'Toggle bot token visibility', 'align-agent' ); ?>">
									<span class="dashicons dashicons-visibility"></span>
									<span id="align-bridge-toggle-bot-token-text"><?php esc_html_e( 'Show', 'align-agent' ); ?></span>
								</button>
							</div>
						</div>

						<div class="align-bridge-field">
							<span class="align-bridge-label"><?php esc_html_e( 'Connect Bot (6-Digit Pairing)', 'align-agent' ); ?></span>
							<div class="align-agent-input-group">
								<button type="button" class="button button-secondary align-agent-btn" id="align-bridge-pair-btn" aria-label="<?php esc_attr_e( 'Generate Telegram pairing code', 'align-agent' ); ?>" data-action="generate-pair-code">
									<span class="dashicons dashicons-admin-links"></span>
									<span><?php esc_html_e( 'Generate Pairing Code', 'align-agent' ); ?></span>
								</button>
							</div>
							<div id="align-bridge-pair-box" class="align-agent-pair-box" style="display: none;">
								<div class="align-agent-pair-code" id="align-bridge-pair-code">------</div>
								<p class="align-agent-pair-hint" id="align-bridge-pair-hint"></p>
							</div>
							<div id="align-bridge-pair-status" class="align-agent-status-msg" role="status" aria-live="polite" style="display: none;"></div>
							<p class="description">
								<?php esc_html_e( 'Click Generate Pairing Code and send the 6-digit code to your bot in Telegram.', 'align-agent' ); ?>
							</p>
						</div>

						<div class="align-bridge-field">
							<label class="align-bridge-label" for="align_bridge_allowed_user_ids"><?php esc_html_e( 'Chat ID (Allowed User IDs)', 'align-agent' ); ?></label>
							<div class="align-agent-input-group">
								<input type="text" id="align_bridge_allowed_user_ids" name="align_bridge_allowed_user_ids" class="regular-text align-agent-mono-input" value="<?php echo esc_attr( $b_allowed_user_ids ); ?>" placeholder="Auto-filled after pairing (or comma-separated IDs)">
							</div>
							<p class="description"><?php esc_html_e( 'Auto-filled after pairing, or enter comma-separated numeric Telegram User IDs.', 'align-agent' ); ?></p>
						</div>
					</div>
				</section>

				<!-- Card 2 (Right Column): 2. AI / LLM Provider -->
				<section class="align-agent-card align-bridge-card" data-step="bridge-llm-card">
					<header class="align-agent-card-header">
						<h2 class="align-agent-card-title">
							<span class="dashicons dashicons-admin-plugins"></span>
							<span><?php esc_html_e( '2. AI / LLM Provider', 'align-agent' ); ?></span>
						</h2>
						<p class="align-agent-card-desc">
							<?php esc_html_e( 'Configure the OpenAI-compatible LLM provider used by both Middleware Bridge and WP Chatbox.', 'align-agent' ); ?>
						</p>
					</header>
					<div class="align-agent-card-body">
						<div class="align-bridge-field">
							<label class="align-bridge-label" for="align_bridge_llm_base_url"><?php esc_html_e( 'LLM Base URL', 'align-agent' ); ?></label>
							<div class="align-agent-input-group">
								<input type="text" id="align_bridge_llm_base_url" name="align_bridge_llm_base_url" class="regular-text align-agent-mono-input" value="<?php echo esc_attr( $b_llm_base_url ); ?>" placeholder="https://openrouter.ai/api/v1">
							</div>
							<p class="description">
								<?php esc_html_e( 'Base URL only (e.g. https://openrouter.ai/api/v1). /chat/completions is appended automatically.', 'align-agent' ); ?>
							</p>
						</div>

						<div class="align-bridge-field">
							<label class="align-bridge-label" for="align_bridge_llm_api_key"><?php esc_html_e( 'LLM API Key', 'align-agent' ); ?></label>
							<div class="align-agent-input-group">
								<input type="password" id="align_bridge_llm_api_key" name="align_bridge_llm_api_key" class="regular-text align-agent-mono-input" value="<?php echo esc_attr( $b_llm_api_key ); ?>" autocomplete="new-password" placeholder="<?php esc_attr_e( 'Enter LLM API Key', 'align-agent' ); ?>">
								<button type="button" class="button button-secondary align-agent-btn" id="align-bridge-toggle-llm-key-btn" aria-label="<?php esc_attr_e( 'Toggle LLM API key visibility', 'align-agent' ); ?>">
									<span class="dashicons dashicons-visibility"></span>
									<span id="align-bridge-toggle-llm-key-text"><?php esc_html_e( 'Show', 'align-agent' ); ?></span>
								</button>
							</div>
						</div>

						<div class="align-bridge-field">
							<label class="align-bridge-label" for="align_bridge_llm_model"><?php esc_html_e( 'LLM Model', 'align-agent' ); ?></label>
							<div class="align-agent-input-group">
								<input type="text" id="align_bridge_llm_model" name="align_bridge_llm_model" class="regular-text align-agent-mono-input" value="<?php echo esc_attr( $b_llm_model ); ?>" placeholder="gpt-4o">
								<button type="button" class="button button-secondary align-agent-btn" id="align-bridge-test-ai-btn" aria-label="<?php esc_attr_e( 'Test AI provider connection', 'align-agent' ); ?>" data-action="test-bridge-ai">
									<span class="dashicons dashicons-yes-alt"></span>
									<span><?php esc_html_e( 'Test AI', 'align-agent' ); ?></span>
								</button>
							</div>
							<div id="align-bridge-ai-status" class="align-agent-status-msg" role="status" aria-live="polite" style="display: none;"></div>
							<p class="description">
								<?php esc_html_e( 'Model identifier (e.g. gpt-4o, google/gemma-4-31b-it, anthropic/claude-3.5-sonnet).', 'align-agent' ); ?>
							</p>
						</div>
					</div>
				</section>

				<!-- Card 3 (Full Width): 3. Middleware Hub & WordPress MCP Auth -->
				<section class="align-agent-card align-bridge-card align-bridge-card-full" data-step="bridge-hub-card">
					<header class="align-agent-card-header">
						<h2 class="align-agent-card-title">
							<span class="dashicons dashicons-networking"></span>
							<span><?php esc_html_e( '3. Middleware Hub & WordPress MCP Auth', 'align-agent' ); ?></span>
						</h2>
						<p class="align-agent-card-desc">
							<?php esc_html_e( 'Configure the Middleware Hub endpoint, Telegram write approval policy, and WordPress Application Password for MCP tool execution.', 'align-agent' ); ?>
						</p>
					</header>
					<div class="align-agent-card-body">
						<div class="align-bridge-subgrid">
							<div class="align-bridge-subcol">
								<div class="align-bridge-field">
									<label class="align-bridge-label" for="align_bridge_middleware_url"><?php esc_html_e( 'Middleware URL', 'align-agent' ); ?></label>
									<div class="align-agent-input-group">
										<input type="text" id="align_bridge_middleware_url" name="align_bridge_middleware_url" class="regular-text align-agent-mono-input" value="<?php echo esc_attr( $b_middleware_url ); ?>" placeholder="http://localhost:3000">
									</div>
									<p class="description"><?php esc_html_e( 'Base URL of the Node.js Middleware Bridge service.', 'align-agent' ); ?></p>
								</div>

								<div class="align-bridge-field">
									<span class="align-bridge-label"><?php esc_html_e( 'Approval Mode', 'align-agent' ); ?></span>
									<fieldset>
										<legend class="screen-reader-text"><span><?php esc_html_e( 'Approval Mode', 'align-agent' ); ?></span></legend>
										<label style="display: block; margin-bottom: 6px;">
											<input type="radio" name="align_bridge_approval_mode" value="auto" <?php checked( $approval_mode, 'auto' ); ?>>
											<strong><?php esc_html_e( 'Autonomous with Instant Undo (Recommended)', 'align-agent' ); ?></strong>
										</label>
										<label style="display: block;">
											<input type="radio" name="align_bridge_approval_mode" value="manual" <?php checked( $approval_mode, 'manual' ); ?>>
											<strong><?php esc_html_e( 'Manual Approval (Legacy Gate)', 'align-agent' ); ?></strong>
										</label>
									</fieldset>
									<p class="description"><?php esc_html_e( 'Controls whether non-global Telegram write operations run automatically or require manual approval.', 'align-agent' ); ?></p>
								</div>
							</div>

							<div class="align-bridge-subcol">
								<div class="align-bridge-field">
									<label class="align-bridge-label" for="align_bridge_wp_username"><?php esc_html_e( 'WP Username', 'align-agent' ); ?></label>
									<div class="align-agent-input-group">
										<input type="text" id="align_bridge_wp_username" name="align_bridge_wp_username" class="regular-text align-agent-mono-input" value="<?php echo esc_attr( $b_wp_username ); ?>" readonly>
									</div>
									<p class="description"><?php esc_html_e( 'Current administrator account used for MCP REST authentication.', 'align-agent' ); ?></p>
								</div>

								<div class="align-bridge-field">
									<label class="align-bridge-label" for="align_bridge_wp_app_password"><?php esc_html_e( 'WP Application Password', 'align-agent' ); ?></label>
									<div class="align-agent-input-group">
										<input type="password" id="align_bridge_wp_app_password" name="align_bridge_wp_app_password" class="regular-text align-agent-mono-input" value="<?php echo esc_attr( $b_wp_app_password ); ?>" autocomplete="new-password" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx">
										<button type="button" class="button button-secondary align-agent-btn" id="align-bridge-toggle-app-pass-btn" aria-label="<?php esc_attr_e( 'Toggle application password visibility', 'align-agent' ); ?>">
											<span class="dashicons dashicons-visibility"></span>
											<span id="align-bridge-toggle-app-pass-text"><?php esc_html_e( 'Show', 'align-agent' ); ?></span>
										</button>
									</div>
									<p class="description">
										<a href="<?php echo esc_url( $app_pass_guide_link ); ?>" target="_blank" rel="noopener noreferrer">
											<?php esc_html_e( 'Generate or manage your WordPress Application Password in Users → Profile → Application Passwords.', 'align-agent' ); ?>
										</a>
									</p>
								</div>
							</div>
						</div>

						<div class="align-agent-actions">
							<button type="button" id="align-agent-register-bridge-btn" class="button button-primary align-agent-btn-primary" aria-label="<?php esc_attr_e( 'Save and Sync to Bridge', 'align-agent' ); ?>" data-action="register-bridge">
								<span class="dashicons dashicons-saved"></span>
								<span><?php esc_html_e( 'Save & Sync to Bridge', 'align-agent' ); ?></span>
							</button>
							<div id="align-agent-bridge-status" class="align-agent-status-msg" role="status" aria-live="polite" style="display: none; margin-top: 12px; padding: 10px 14px; border-radius: 4px; font-weight: 600;"></div>
						</div>
					</div>
				</section>
			</div>
		</div><!-- /PANEL bridge -->

		<!-- PANEL: MCP Tools -->
		<div class="align-agent-tab-panel" role="tabpanel" data-align-panel="mcp" style="display: none;">
			<?php require __DIR__ . '/tab-mcp-tools.php'; ?>
		</div><!-- /PANEL mcp -->

		<!-- PANEL: SEO -->
		<div class="align-agent-tab-panel" role="tabpanel" data-align-panel="seo" style="display: none;">
			<?php require __DIR__ . '/tab-seo.php'; ?>
		</div><!-- /PANEL seo -->

		<!-- PANEL: Updates -->
		<div class="align-agent-tab-panel" role="tabpanel" data-align-panel="updates" style="display: none;">
			<?php require __DIR__ . '/tab-updates.php'; ?>
		</div><!-- /PANEL updates -->
	<?php endif; ?>
	</main><!-- /.align-agent-main-column -->
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var tabButtons = document.querySelectorAll('.align-nav-tab[data-align-tab]');
	var tabPanels  = document.querySelectorAll('.align-agent-tab-panel[data-align-panel]');
	if (!tabButtons.length || !tabPanels.length) {
		return;
	}

	function switchTab(name) {
		var matched = false;
		tabButtons.forEach(function (btn) {
			btn.classList.toggle('is-active', btn.getAttribute('data-align-tab') === name);
		});
		tabPanels.forEach(function (panel) {
			var isTarget = panel.getAttribute('data-align-panel') === name;
			panel.classList.toggle('is-active', isTarget);
			panel.style.display = isTarget ? 'block' : 'none';
			if (isTarget) {
				matched = true;
			}
		});
		if (matched && history.replaceState) {
			history.replaceState(null, '', '#tab-' + name);
		}
		console.log('[Align UI] Switched settings tab: ' + name);
	}

	tabButtons.forEach(function (btn) {
		btn.addEventListener('click', function (e) {
			e.preventDefault();
			switchTab(btn.getAttribute('data-align-tab'));
		});
	});

	var hash = (window.location.hash || '').replace('#tab-', '');
	if (hash && document.querySelector('.align-nav-tab[data-align-tab="' + hash + '"]')) {
		switchTab(hash);
	}
});
</script>
