<?php
/**
 * Tab: Dedicated AI REST API View.
 *
 * Provides API documentation, Application Passwords authentication guide,
 * endpoint specifications, and an interactive REST console for testing.
 * Strictly adheres to the Align Settings Style Guide (Zero Emojis, Transparent Buttons, Card System, Accordion Standard).
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$base_api_url     = rest_url( 'align-ai/v1' );
$current_user     = wp_get_current_user();
$current_username = $current_user ? $current_user->user_login : 'admin';
$app_pass_url     = admin_url( 'profile.php#application-passwords-section' );
?>

<style>
.align-settings-wrap.is-wide-layout {
	max-width: 1720px !important;
}
.align-api-tab-wrap {
	display: grid;
	grid-template-columns: minmax(0, 1.15fr) minmax(440px, 1fr);
	gap: 22px;
	align-items: start;
}
.align-api-col-left,
.align-api-col-right {
	display: flex;
	flex-direction: column;
	min-width: 0;
}
.align-api-col-right {
	position: sticky;
	top: 36px;
}
@media (max-width: 1320px) {
	.align-api-tab-wrap {
		grid-template-columns: 1fr;
	}
	.align-api-col-right {
		position: static;
	}
}
.align-api-pill-selector {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin-bottom: 20px;
}
.align-api-pill {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	padding: 6px 12px;
	border-radius: 6px;
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	color: #475569;
	font-size: 12.5px;
	font-weight: 600;
	cursor: pointer;
	transition: all 0.15s ease;
	user-select: none;
}
.align-api-pill:hover {
	background: #f1f5f9;
	border-color: #cbd5e1;
	color: #0f172a;
}
.align-api-pill.is-active {
	background: #ffffff;
	border-color: #0284c7;
	color: #0284c7;
	box-shadow: 0 1px 3px rgba(2, 132, 199, 0.12);
}
.align-method-badge {
	display: inline-block;
	padding: 2px 6px;
	border-radius: 4px;
	font-size: 10.5px;
	font-weight: 700;
	font-family: monospace;
	line-height: 1.2;
	text-transform: uppercase;
}
.align-method-get {
	background: #e0f2fe;
	color: #0284c7;
	border: 1px solid #bae6fd;
}
.align-method-post {
	background: #dcfce7;
	color: #15803d;
	border: 1px solid #bbf7d0;
}
.align-method-put {
	background: #fef9c3;
	color: #a16207;
	border: 1px solid #fef08a;
}
.align-method-delete {
	background: #fee2e2;
	color: #dc2626;
	border: 1px solid #fecaca;
}
</style>

<script>
window.alignAiBaseApiUrl = '<?php echo esc_url_raw( $base_api_url ); ?>';
window.alignAiUsername   = '<?php echo esc_js( $current_username ); ?>';
</script>

<div class="align-api-tab-wrap">
	<!-- LEFT COLUMN: Documentation & Specifications -->
	<div class="align-api-col-left">
		<!-- CARD 1: Overview & Authentication Guide -->
		<div class="align-settings-card is-accordion" id="align-api-overview-card">
		<div class="align-card-header" id="align-api-overview-header" style="cursor: pointer; user-select: none;" title="<?php esc_attr_e( 'Click to toggle Overview', 'align' ); ?>">
			<div>
				<h3 class="align-card-title"><?php esc_html_e( 'Dedicated AI REST API', 'align' ); ?></h3>
				<p class="align-card-desc">
					<?php esc_html_e( 'High-performance REST API endpoints designed for autonomous AI coding agents and headless tools to manage WordPress pages with decoupled HTML, CSS, and JS.', 'align' ); ?>
				</p>
			</div>
			<div class="align-accordion-toggle">
				<span class="align-accordion-toggle-label"><?php esc_html_e( 'Show Overview', 'align' ); ?></span>
				<span class="dashicons dashicons-arrow-down-alt2"></span>
			</div>
		</div>

		<div class="align-card-body" id="align-api-overview-body" style="display: none;">
			<!-- Base URL Display -->
			<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px 16px; margin-bottom: 22px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
				<div>
					<div style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 4px;">
						<?php esc_html_e( 'Endpoint Base URL', 'align' ); ?>
					</div>
					<code style="font-size: 13.5px; font-family: monospace; color: #0f172a; background: transparent; padding: 0;">
						<?php echo esc_url( $base_api_url ); ?>
					</code>
				</div>
				<div style="display: flex; gap: 8px;">
					<button type="button" class="button button-secondary align-copy-btn" data-clipboard="<?php echo esc_attr( $base_api_url ); ?>">
						<span class="dashicons dashicons-admin-page"></span>
						<span><?php esc_html_e( 'Copy Base URL', 'align' ); ?></span>
					</button>
				</div>
			</div>

			<!-- Authentication Guide (Autonomous AI & Postman) -->
			<div style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; background: #ffffff;">
				<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 8px;">
					<div style="display: flex; align-items: center; gap: 8px;">
						<span class="dashicons dashicons-shield-alt" style="color: #0284c7; font-size: 18px; width: 18px; height: 18px;"></span>
						<h4 style="margin: 0; font-size: 13.5px; font-weight: 700; color: #0f172a;">
							<?php esc_html_e( 'Autonomous AI & Postman Authentication', 'align' ); ?>
						</h4>
					</div>
					<a href="<?php echo esc_url( $app_pass_url ); ?>" class="button button-secondary" target="_blank" rel="noopener noreferrer">
						<span class="dashicons dashicons-external"></span>
						<span><?php esc_html_e( 'Manage Application Passwords', 'align' ); ?></span>
					</a>
				</div>
				<p style="font-size: 12.5px; color: #475569; margin: 0 0 12px 0; line-height: 1.45;">
					<?php esc_html_e( 'External AI agents and Postman authenticate via standard HTTP Basic Auth using WordPress Application Passwords. No CSRF nonce validation is required.', 'align' ); ?>
				</p>
				<div style="background: #f1f5f9; padding: 10px 12px; border-radius: 4px; font-family: monospace; font-size: 12px; color: #1e293b; word-break: break-all;">
					Authorization: Basic base64(<?php echo esc_html( $current_username ); ?>:YOUR_APPLICATION_PASSWORD)
				</div>
			</div>
		</div>
	</div>

	<!-- CARD 2: API Endpoints & Dynamic Details Viewer -->
	<div class="align-settings-card is-accordion" id="align-api-specs-card">
		<div class="align-card-header" id="align-api-specs-header" style="cursor: pointer; user-select: none;" title="<?php esc_attr_e( 'Click to toggle Specifications', 'align' ); ?>">
			<div>
				<h3 class="align-card-title"><?php esc_html_e( 'API Endpoints & Specifications', 'align' ); ?></h3>
				<p class="align-card-desc">
					<?php esc_html_e( 'Select an endpoint below to inspect route details, required HTTP headers, JSON request payload, and ready-to-use cURL commands.', 'align' ); ?>
				</p>
			</div>
			<div class="align-accordion-toggle">
				<span class="align-accordion-toggle-label"><?php esc_html_e( 'Show Specifications', 'align' ); ?></span>
				<span class="dashicons dashicons-arrow-down-alt2"></span>
			</div>
		</div>

		<div class="align-card-body" id="align-api-specs-body" style="display: none;">
			<!-- Endpoint Pill Selector -->
			<div class="align-api-pill-selector" role="tablist">
				<button type="button" class="align-api-pill is-active" data-endpoint="create_page">
					<span class="align-method-badge align-method-post">POST</span>
					<span><?php esc_html_e( 'Create Page', 'align' ); ?></span>
				</button>
				<button type="button" class="align-api-pill" data-endpoint="list_pages">
					<span class="align-method-badge align-method-get">GET</span>
					<span><?php esc_html_e( 'List Pages', 'align' ); ?></span>
				</button>
				<button type="button" class="align-api-pill" data-endpoint="get_page">
					<span class="align-method-badge align-method-get">GET</span>
					<span><?php esc_html_e( 'Get Page', 'align' ); ?></span>
				</button>
				<button type="button" class="align-api-pill" data-endpoint="update_page">
					<span class="align-method-badge align-method-put">PUT</span>
					<span><?php esc_html_e( 'Update Page', 'align' ); ?></span>
				</button>
				<button type="button" class="align-api-pill" data-endpoint="delete_page">
					<span class="align-method-badge align-method-delete">DELETE</span>
					<span><?php esc_html_e( 'Delete Page', 'align' ); ?></span>
				</button>
				<button type="button" class="align-api-pill" data-endpoint="ping">
					<span class="align-method-badge align-method-get">GET</span>
					<span><?php esc_html_e( 'Ping', 'align' ); ?></span>
				</button>
			</div>

			<!-- Dynamic Details Viewer -->
			<div id="align-endpoint-details-viewer" style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 18px; background: #ffffff;">
				<!-- Route Header -->
				<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 12px;">
					<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
						<span id="align-endpoint-badge" class="align-method-badge align-method-post">POST</span>
						<span id="align-endpoint-name" style="font-weight: 700; font-size: 14px; color: #0f172a;"></span>
						<code id="align-endpoint-route" style="font-family: monospace; font-size: 12.5px; color: #475569; background: #f1f5f9; padding: 2px 8px; border-radius: 4px;"></code>
					</div>
					<button type="button" id="align-btn-load-console" class="button button-primary">
						<span class="dashicons dashicons-controls-play"></span>
						<span><?php esc_html_e( 'Load in Console', 'align' ); ?></span>
					</button>
				</div>

				<p id="align-endpoint-desc" style="font-size: 12.5px; color: #475569; margin: 0 0 16px 0; line-height: 1.5;"></p>

				<!-- Full Request URL -->
				<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px;">
					<div style="min-width: 0; flex: 1;">
						<div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 3px;">
							<?php esc_html_e( 'Full Request URL', 'align' ); ?>
						</div>
						<code id="align-endpoint-url" style="font-size: 12.5px; font-family: monospace; color: #0f172a; word-break: break-all; background: transparent; padding: 0;"></code>
					</div>
					<button type="button" class="button button-secondary align-copy-btn" id="align-btn-copy-url" data-clipboard-target="#align-endpoint-url">
						<span class="dashicons dashicons-admin-page"></span>
						<span><?php esc_html_e( 'Copy URL', 'align' ); ?></span>
					</button>
				</div>

				<!-- Required HTTP Headers -->
				<div style="margin-bottom: 18px;">
					<div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 6px;">
						<?php esc_html_e( 'Required HTTP Headers', 'align' ); ?>
					</div>
					<div id="align-endpoint-headers" style="display: flex; flex-wrap: wrap; gap: 6px;"></div>
				</div>

				<!-- Two Code Panels: Payload & cURL -->
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px;">
					<!-- Request JSON Payload -->
					<div id="align-endpoint-payload-panel" style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; background: #ffffff;">
						<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
							<div style="font-weight: 700; font-size: 12.5px; color: #0f172a;">
								<?php esc_html_e( 'Request JSON Payload', 'align' ); ?>
							</div>
							<button type="button" class="button button-secondary align-copy-btn" id="align-btn-copy-json" data-clipboard-target="#align-endpoint-payload-code">
								<span class="dashicons dashicons-admin-page"></span>
								<span><?php esc_html_e( 'Copy JSON', 'align' ); ?></span>
							</button>
						</div>
						<pre id="align-endpoint-payload-code" style="background: #0f172a; color: #f8fafc; padding: 12px; border-radius: 6px; font-size: 11.5px; font-family: monospace; overflow-x: auto; margin: 0; min-height: 120px; max-height: 260px;"></pre>
					</div>

					<!-- Ready-to-use cURL -->
					<div id="align-endpoint-curl-panel" style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; background: #ffffff;">
						<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
							<div style="font-weight: 700; font-size: 12.5px; color: #0f172a;">
								<?php esc_html_e( 'Ready-to-use cURL', 'align' ); ?>
							</div>
							<button type="button" class="button button-secondary align-copy-btn" id="align-btn-copy-curl" data-clipboard-target="#align-endpoint-curl-code">
								<span class="dashicons dashicons-admin-page"></span>
								<span><?php esc_html_e( 'Copy cURL', 'align' ); ?></span>
							</button>
						</div>
						<pre id="align-endpoint-curl-code" style="background: #0f172a; color: #f8fafc; padding: 12px; border-radius: 6px; font-size: 11.5px; font-family: monospace; overflow-x: auto; margin: 0; min-height: 120px; max-height: 260px;"></pre>
					</div>
				</div>
			</div>
		</div>
	</div>
	</div><!-- /.align-api-col-left -->

	<!-- RIGHT COLUMN: Interactive REST Console -->
	<div class="align-api-col-right">
		<!-- CARD 3: Interactive REST Console (Open by default) -->
		<div class="align-settings-card is-accordion is-open" id="align-api-console-card">
			<div class="align-card-header" id="align-api-console-header" style="cursor: pointer; user-select: none;" title="<?php esc_attr_e( 'Click to toggle Interactive REST Console', 'align' ); ?>">
				<div>
					<h3 class="align-card-title"><?php esc_html_e( 'Interactive REST Console', 'align' ); ?></h3>
					<p class="align-card-desc">
						<?php esc_html_e( 'Execute live API requests directly against this WordPress instance with your authenticated session.', 'align' ); ?>
					</p>
				</div>
				<div class="align-accordion-toggle">
					<span class="align-accordion-toggle-label"><?php esc_html_e( 'Close Console', 'align' ); ?></span>
					<span class="dashicons dashicons-arrow-down-alt2"></span>
				</div>
			</div>

			<div class="align-card-body" id="align-api-console-body" style="display: block;">
			<!-- Console Toolbar -->
			<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 14px;">
				<div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
					<span style="font-size: 12.5px; font-weight: 600; color: #475569;">
						<?php esc_html_e( 'Quick Templates:', 'align' ); ?>
					</span>
					<button type="button" class="button button-secondary align-console-quick-btn" data-method="GET" data-path="<?php echo esc_url( rest_url( 'align-ai/v1/ping' ) ); ?>" data-body="">
						<?php esc_html_e( 'Ping (GET)', 'align' ); ?>
					</button>
					<button type="button" class="button button-secondary align-console-quick-btn" data-method="GET" data-path="<?php echo esc_url( rest_url( 'align-ai/v1/pages?per_page=5' ) ); ?>" data-body="">
						<?php esc_html_e( 'List Pages (GET)', 'align' ); ?>
					</button>
					<button type="button" class="button button-primary is-active align-console-quick-btn" data-method="POST" data-path="<?php echo esc_url( rest_url( 'align-ai/v1/pages' ) ); ?>" data-body-type="create">
						<?php esc_html_e( 'Create Page (POST)', 'align' ); ?>
					</button>
				</div>
			</div>

			<!-- Request Form -->
			<div style="display: grid; grid-template-columns: 120px 1fr auto; gap: 10px; margin-bottom: 14px;">
				<!-- Method -->
				<div>
					<label for="align-console-method" class="screen-reader-text"><?php esc_html_e( 'Method', 'align' ); ?></label>
					<select id="align-console-method" style="width: 100%; height: 34px; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 700; font-size: 13px; color: #0f172a;">
						<option value="GET">GET</option>
						<option value="POST" selected>POST</option>
						<option value="PUT">PUT</option>
						<option value="DELETE">DELETE</option>
					</select>
				</div>

				<!-- Path -->
				<div>
					<label for="align-console-path" class="screen-reader-text"><?php esc_html_e( 'Route Path', 'align' ); ?></label>
					<input type="text" id="align-console-path" value="<?php echo esc_attr( rest_url( 'align-ai/v1/pages' ) ); ?>" style="width: 100%; height: 34px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: monospace; font-size: 13px; color: #0f172a;">
				</div>

				<!-- Send Button -->
				<div>
					<button type="button" id="align-console-send-btn" class="button button-primary" style="height: 34px; padding: 0 16px;">
						<span class="dashicons dashicons-controls-play"></span>
						<span><?php esc_html_e( 'Send Request', 'align' ); ?></span>
					</button>
				</div>
			</div>

			<!-- Request Body Textarea (Shown for POST / PUT) -->
			<div id="align-console-body-wrap" style="margin-bottom: 16px;">
				<label for="align-console-body" style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">
					<?php esc_html_e( 'Request JSON Payload (for POST / PUT):', 'align' ); ?>
				</label>
				<?php
				$default_console_payload = wp_json_encode(
					array(
						'title'  => 'Sample AI Landing Page',
						'slug'   => 'sample-ai-landing',
						'status' => 'publish',
						'lang'   => 'en',
						'html'   => "<section class=\"hero-section\">\n  <h1>Autonomous Creation</h1>\n  <p>Engineered with Align Dedicated AI REST API.</p>\n</section>",
						'css'    => ".hero-section { padding: 80px 20px; text-align: center; }\n.hero-section h1 { font-size: 40px; color: #0f172a; font-weight: 800; }",
						'js'     => 'console.log("[Align AI] Page mounted dynamically.");',
					),
					JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
				);
				?>
				<textarea id="align-console-body" rows="6" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: monospace; font-size: 12px; color: #0f172a;" placeholder="<?php esc_attr_e( 'Enter JSON request payload for POST or PUT methods...', 'align' ); ?>"><?php echo esc_textarea( $default_console_payload ); ?></textarea>
			</div>

			<!-- Live Response Area -->
			<div style="border-top: 1px solid #e2e8f0; padding-top: 16px;">
				<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
					<div style="display: flex; align-items: center; gap: 10px;">
						<span style="font-size: 12.5px; font-weight: 700; color: #0f172a;">
							<?php esc_html_e( 'Response:', 'align' ); ?>
						</span>
						<span id="align-console-status-badge" style="display: none; font-size: 11px; padding: 2px 8px; border-radius: 4px; font-weight: 700; font-family: monospace;"></span>
						<span id="align-console-latency" style="font-size: 11.5px; color: #64748b; font-family: monospace;"></span>
					</div>
					<button type="button" class="button button-secondary align-copy-btn" data-clipboard-target="#align-console-response-text" id="align-console-copy-response" style="display: none;">
						<span class="dashicons dashicons-admin-page"></span>
						<span><?php esc_html_e( 'Copy Response', 'align' ); ?></span>
					</button>
				</div>

				<pre style="background: #0f172a; color: #f8fafc; padding: 14px; border-radius: 6px; font-family: monospace; font-size: 12px; max-height: 400px; overflow: auto; margin: 0;"><code id="align-console-response-text">// Ready. Click "Send Request" or choose a Quick Template above.</code></pre>
			</div>
		</div>
	</div>
	</div><!-- /.align-api-col-right -->
</div>
