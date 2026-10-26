<?php
/**
 * Tab: Connect API View (OpenRouter & OpenAI Official / Compatible AI Hub & Cloud Microservices).
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ai_provider = get_option( 'align_ai_provider', 'openrouter' );

// OpenRouter configuration
$or_base_url = get_option( 'align_openrouter_base_url', '' );
if ( empty( $or_base_url ) ) {
	$or_base_url = class_exists( 'Align_AI_Connection' ) ? Align_AI_Connection::DEFAULT_OPENROUTER_BASE : 'https://openrouter.ai/api/v1';
}
$or_key   = get_option( 'align_openrouter_api_key', '' );
$or_model = get_option( 'align_openrouter_model', '' );
if ( empty( $or_model ) ) {
	$or_model = class_exists( 'Align_AI_Connection' ) ? Align_AI_Connection::DEFAULT_MODEL : 'google/gemini-2.5-flash';
}

// Fallback to legacy options if OpenRouter options are empty
if ( empty( $or_key ) ) {
	$legacy_ai_key = get_option( 'align_ai_api_key', '' );
	if ( ! empty( $legacy_ai_key ) && ( 'openrouter' === $ai_provider || 0 === strpos( $legacy_ai_key, 'sk-or-' ) ) ) {
		$or_key = $legacy_ai_key;
	} else {
		$or_key = get_option( 'align_openrouter_api_key', '' );
	}
}

// OpenAI Official & Compatible configuration
$openai_base_url = get_option( 'align_openai_base_url', '' );
if ( empty( $openai_base_url ) ) {
	$openai_base_url = class_exists( 'Align_AI_Connection' ) ? Align_AI_Connection::DEFAULT_OPENAI_BASE : 'https://api.openai.com/v1';
}
$openai_key   = get_option( 'align_openai_api_key', '' );
$openai_model = get_option( 'align_openai_model', 'gpt-4o-mini' );
$openai_org   = get_option( 'align_openai_organization', '' );

if ( empty( $openai_key ) && 'openai' === $ai_provider ) {
	$openai_key = get_option( 'align_ai_api_key', '' );
}

$current_api = function_exists( 'align_get_api_converter_url' ) ? align_get_api_converter_url() : 'https://convertb.alignvn.cloud';
$sync_token  = defined( 'ALIGN_SYNC_TOKEN' ) ? ALIGN_SYNC_TOKEN : ( class_exists( 'Align_API_Sync' ) && defined( 'Align_API_Sync::DEFAULT_TOKEN' ) ? Align_API_Sync::DEFAULT_TOKEN : 'align_sync_secret_token_2026' );
?>

<div class="align-api-hub-wrap">
	<!-- CARD 1: AI & LLM Engine Hub (2 Tabs: OpenRouter vs OpenAI Official & Compatible - Accordion Default Closed) -->
	<div class="align-settings-card is-accordion" id="align-ai-hub-card">
		<div class="align-card-header" id="align-ai-hub-header" style="cursor: pointer; user-select: none;" title="<?php esc_attr_e( 'Click to toggle AI & LLM Connection Hub', 'align' ); ?>">
			<div>
				<h3 class="align-card-title"><?php esc_html_e( 'AI & LLM Connection Hub', 'align' ); ?></h3>
				<p class="align-card-desc">
					<?php esc_html_e( 'Configure OpenRouter or OpenAI Official & Compatible engines powering Dynamic Block Studio, AI Semantic Refactoring, and automated translations.', 'align' ); ?>
				</p>
			</div>
			<div class="align-accordion-toggle">
				<span id="align-ai-toggle-label"><?php esc_html_e( 'Show Details', 'align' ); ?></span>
				<span class="dashicons dashicons-arrow-down-alt2" style="font-size: 16px; width: 16px; height: 16px;"></span>
			</div>
		</div>

		<div class="align-card-body" id="align-ai-hub-body" style="display: none;">
			<!-- Hidden Active Provider Field -->
			<input type="hidden" name="align_ai_provider" id="align_ai_provider" value="<?php echo esc_attr( $ai_provider ); ?>">

			<!-- Legacy Sync Fields -->
			<input type="hidden" name="align_ai_base_url" id="align_ai_base_url" value="<?php echo esc_attr( 'openai' === $ai_provider ? $openai_base_url : $or_base_url ); ?>">
			<input type="hidden" name="align_ai_api_key" id="align_ai_api_key" value="<?php echo esc_attr( 'openai' === $ai_provider ? $openai_key : $or_key ); ?>">
			<input type="hidden" name="align_ai_model" id="align_ai_model" value="<?php echo esc_attr( 'openai' === $ai_provider ? $openai_model : $or_model ); ?>">
			<input type="hidden" name="align_ai_organization" id="align_ai_organization" value="<?php echo esc_attr( $openai_org ); ?>">

			<!-- Two-Tab Segmented Navigation -->
			<div class="align-ai-tabs-bar" role="tablist">
				<button type="button" class="align-ai-tab-btn <?php echo ( 'openrouter' === $ai_provider ) ? 'is-active' : ''; ?>" data-ai-tab="openrouter" id="tab-btn-openrouter">
					<span><?php esc_html_e( 'OpenRouter', 'align' ); ?></span>
				</button>
				<button type="button" class="align-ai-tab-btn <?php echo ( 'openai' === $ai_provider ) ? 'is-active' : ''; ?>" data-ai-tab="openai" id="tab-btn-openai">
					<span><?php esc_html_e( 'OpenAI (Official & Compatible)', 'align' ); ?></span>
				</button>
			</div>

			<!-- TAB PANE 1: OpenRouter -->
			<div class="align-ai-tab-pane" id="ai-pane-openrouter" style="<?php echo ( 'openrouter' === $ai_provider ) ? '' : 'display: none;'; ?>">

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="align_openrouter_base_url"><?php esc_html_e( 'API Base URL', 'align' ); ?></label>
						</th>
						<td>
							<input type="url" name="align_openrouter_base_url" id="align_openrouter_base_url" class="regular-text" style="width: 100%; max-width: 500px;" value="<?php echo esc_attr( $or_base_url ); ?>" placeholder="https://openrouter.ai/api/v1">
							<p class="description">
								<?php esc_html_e( 'Standard OpenRouter completions endpoint (default: https://openrouter.ai/api/v1).', 'align' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="align_openrouter_api_key"><?php esc_html_e( 'API Key', 'align' ); ?></label>
						</th>
						<td>
							<div style="display: flex; align-items: center; gap: 8px; max-width: 500px;">
								<input type="password" name="align_openrouter_api_key" id="align_openrouter_api_key" class="regular-text" style="flex: 1;" value="<?php echo esc_attr( $or_key ); ?>" placeholder="sk-or-v1-..." autocomplete="new-password">
								<button type="button" class="button" id="align-toggle-openrouter-key">
									<?php esc_html_e( 'Show', 'align' ); ?>
								</button>
							</div>
							<p class="description">
								<?php esc_html_e( 'Bearer token from openrouter.ai/keys. Stored independently and never overwritten.', 'align' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="align_openrouter_model"><?php esc_html_e( 'Default Model', 'align' ); ?></label>
						</th>
						<td>
							<input type="text" name="align_openrouter_model" id="align_openrouter_model" class="regular-text" style="width: 100%; max-width: 500px;" value="<?php echo esc_attr( $or_model ); ?>" placeholder="google/gemini-2.5-flash">
							<div class="align-chips-group">
								<button type="button" class="align-chip-btn" data-fill-target="align_openrouter_model" data-model-value="google/gemini-2.5-flash">google/gemini-2.5-flash (Recommended)</button>
								<button type="button" class="align-chip-btn" data-fill-target="align_openrouter_model" data-model-value="anthropic/claude-3.5-sonnet">anthropic/claude-3.5-sonnet</button>
								<button type="button" class="align-chip-btn" data-fill-target="align_openrouter_model" data-model-value="deepseek/deepseek-chat">deepseek/deepseek-chat</button>
								<button type="button" class="align-chip-btn" data-fill-target="align_openrouter_model" data-model-value="openai/gpt-4o">openai/gpt-4o</button>
								<button type="button" class="align-chip-btn" data-fill-target="align_openrouter_model" data-model-value="meta-llama/llama-3.3-70b-instruct">meta-llama/llama-3.3-70b-instruct</button>
							</div>
							<p class="description">
								<?php esc_html_e( 'Select or type any model slug supported by OpenRouter.', 'align' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Test Connection', 'align' ); ?></th>
						<td>
							<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
								<button type="button" class="button button-secondary" id="align-test-openrouter-btn">
									<?php esc_html_e( 'Test OpenRouter Connection', 'align' ); ?>
								</button>
								<span class="spinner" id="align-openrouter-spinner" style="float: none; margin: 0;"></span>
								<span id="align-openrouter-test-status" style="font-weight: 600; font-size: 13px;"></span>
							</div>
							<div id="align-openrouter-test-result-box" style="display: none; margin-top: 10px; max-width: 600px; padding: 12px 16px; border-radius: 6px; font-size: 13px; line-height: 1.5;"></div>
						</td>
					</tr>
				</table>
			</div>

			<!-- TAB PANE 2: OpenAI Official & Compatible -->
			<div class="align-ai-tab-pane" id="ai-pane-openai" style="<?php echo ( 'openai' === $ai_provider ) ? '' : 'display: none;'; ?>">

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Quick Presets', 'align' ); ?></th>
						<td>
							<div class="align-chips-group">
								<button type="button" class="align-chip-btn align-openai-preset" data-base="https://api.openai.com/v1" data-model="gpt-4o-mini">OpenAI Official (gpt-4o-mini)</button>
								<button type="button" class="align-chip-btn align-openai-preset" data-base="https://api.deepseek.com/v1" data-model="deepseek-chat">DeepSeek (deepseek-chat)</button>
								<button type="button" class="align-chip-btn align-openai-preset" data-base="https://api.groq.com/openai/v1" data-model="llama-3.3-70b-versatile">Groq (llama-3.3-70b)</button>
								<button type="button" class="align-chip-btn align-openai-preset" data-base="http://localhost:11434/v1" data-model="llama3">Ollama Local (llama3)</button>
							</div>
							<p class="description">
								<?php esc_html_e( 'One-click presets to auto-fill Base URL and default model.', 'align' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="align_openai_base_url"><?php esc_html_e( 'API Base URL', 'align' ); ?></label>
						</th>
						<td>
							<input type="url" name="align_openai_base_url" id="align_openai_base_url" class="regular-text" style="width: 100%; max-width: 500px;" value="<?php echo esc_attr( $openai_base_url ); ?>" placeholder="https://api.openai.com/v1">
							<p class="description">
								<?php esc_html_e( 'OpenAI-compliant base URL (e.g., https://api.openai.com/v1, https://api.deepseek.com/v1, or http://localhost:11434/v1).', 'align' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="align_openai_api_key"><?php esc_html_e( 'API Key', 'align' ); ?></label>
						</th>
						<td>
							<div style="display: flex; align-items: center; gap: 8px; max-width: 500px;">
								<input type="password" name="align_openai_api_key" id="align_openai_api_key" class="regular-text" style="flex: 1;" value="<?php echo esc_attr( $openai_key ); ?>" placeholder="sk-proj-... or provider token" autocomplete="new-password">
								<button type="button" class="button" id="align-toggle-openai-key">
									<?php esc_html_e( 'Show', 'align' ); ?>
								</button>
							</div>
							<p class="description">
								<?php esc_html_e( 'Bearer token for Authorization header. Leave empty for local Ollama without auth.', 'align' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="align_openai_model"><?php esc_html_e( 'Default Model', 'align' ); ?></label>
						</th>
						<td>
							<input type="text" name="align_openai_model" id="align_openai_model" class="regular-text" style="width: 100%; max-width: 500px;" value="<?php echo esc_attr( $openai_model ); ?>" placeholder="gpt-4o-mini">
							<div class="align-chips-group">
								<button type="button" class="align-chip-btn" data-fill-target="align_openai_model" data-model-value="gpt-4o-mini">gpt-4o-mini</button>
								<button type="button" class="align-chip-btn" data-fill-target="align_openai_model" data-model-value="gpt-4o">gpt-4o</button>
								<button type="button" class="align-chip-btn" data-fill-target="align_openai_model" data-model-value="o3-mini">o3-mini</button>
								<button type="button" class="align-chip-btn" data-fill-target="align_openai_model" data-model-value="deepseek-chat">deepseek-chat</button>
							</div>
							<p class="description">
								<?php esc_html_e( 'Exact model identifier served by your endpoint.', 'align' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="align_openai_organization"><?php esc_html_e( 'Organization ID', 'align' ); ?></label>
						</th>
						<td>
							<input type="text" name="align_openai_organization" id="align_openai_organization" class="regular-text" style="width: 100%; max-width: 500px;" value="<?php echo esc_attr( $openai_org ); ?>" placeholder="org-... (Optional)">
							<p class="description">
								<?php esc_html_e( 'Optional header OpenAI-Organization for multi-org OpenAI accounts.', 'align' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Test Connection', 'align' ); ?></th>
						<td>
							<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
								<button type="button" class="button button-secondary" id="align-test-openai-btn">
									<?php esc_html_e( 'Test OpenAI Connection', 'align' ); ?>
								</button>
								<span class="spinner" id="align-openai-spinner" style="float: none; margin: 0;"></span>
								<span id="align-openai-test-status" style="font-weight: 600; font-size: 13px;"></span>
							</div>
							<div id="align-openai-test-result-box" style="display: none; margin-top: 10px; max-width: 600px; padding: 12px 16px; border-radius: 6px; font-size: 13px; line-height: 1.5;"></div>
						</td>
					</tr>
				</table>
			</div>
		</div>
	</div>

	<!-- CARD 2: Align Cloud Microservices & Health Diagnostics (Accordion Default Closed) -->
	<div class="align-settings-card is-accordion" id="align-cloud-microservices-card">
		<div class="align-card-header" id="align-cloud-header" style="cursor: pointer; user-select: none;" title="<?php esc_attr_e( 'Click to toggle Align Cloud Microservices', 'align' ); ?>">
			<div>
				<h3 class="align-card-title"><?php esc_html_e( 'Align Cloud Microservices', 'align' ); ?></h3>
				<p class="align-card-desc">
					<?php esc_html_e( 'External microservice endpoints handling SCSS compilation, on-the-fly WebP conversion, payload minification, and HTML block parsing.', 'align' ); ?>
				</p>
			</div>
			<div class="align-accordion-toggle">
				<span id="align-cloud-toggle-label"><?php esc_html_e( 'Show Details', 'align' ); ?></span>
				<span class="dashicons dashicons-arrow-down-alt2" style="font-size: 16px; width: 16px; height: 16px;"></span>
			</div>
		</div>

		<div class="align-card-body" id="align-cloud-body" style="display: none;">
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="align_minify_api_url"><?php esc_html_e( 'API Converter URL', 'align' ); ?></label>
					</th>
					<td>
						<input type="url" name="align_minify_api_url" id="align_minify_api_url" class="regular-text" style="width: 100%; max-width: 500px;" value="<?php echo esc_attr( $current_api ); ?>" placeholder="https://convertb.alignvn.cloud">
						<p class="description">
							<?php esc_html_e( 'Root endpoint for SCSS compilation, WebP image conversion, code minification, and HTML block conversion.', 'align' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Endpoint Diagnostics', 'align' ); ?></th>
					<td>
						<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 12px;">
							<button type="button" class="button button-secondary" id="align-test-all-endpoints-btn">
								<?php esc_html_e( 'Test Endpoints', 'align' ); ?>
							</button>
							<span class="spinner" id="align-endpoint-spinner" style="float: none; margin: 0;"></span>
							<span id="align-endpoint-status-summary" style="font-weight: 600;"></span>
						</div>
						<div id="align-endpoints-table-wrap" style="display: none; max-width: 850px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.05); margin-top: 10px;">
							<table class="wp-list-table widefat striped" style="border: 0; margin: 0; box-shadow: none; border-collapse: collapse; width: 100%;">
								<thead>
									<tr>
										<th scope="col" style="font-weight: 600; width: 25%; padding: 10px 12px; text-align: left; vertical-align: middle; box-sizing: border-box;"><?php esc_html_e( 'Endpoint Name', 'align' ); ?></th>
										<th scope="col" style="font-weight: 600; width: 12%; padding: 10px 12px; text-align: left; vertical-align: middle; box-sizing: border-box;"><?php esc_html_e( 'Method', 'align' ); ?></th>
										<th scope="col" style="font-weight: 600; width: 28%; padding: 10px 12px; text-align: left; vertical-align: middle; box-sizing: border-box;"><?php esc_html_e( 'Path', 'align' ); ?></th>
										<th scope="col" style="font-weight: 600; width: 15%; padding: 10px 12px; text-align: left; vertical-align: middle; box-sizing: border-box;"><?php esc_html_e( 'Latency', 'align' ); ?></th>
										<th scope="col" style="font-weight: 600; width: 20%; padding: 10px 12px; text-align: left; vertical-align: middle; box-sizing: border-box;"><?php esc_html_e( 'Status', 'align' ); ?></th>
									</tr>
								</thead>
								<tbody id="align-endpoints-table-body"></tbody>
							</table>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'API Sync Token', 'align' ); ?></th>
					<td>
						<code style="padding: 4px 8px; font-size: 13px; background: #f1f5f9; border-radius: 4px; color: #0f172a; font-family: monospace;"><?php echo esc_html( $sync_token ); ?></code>
						<p class="description">
							<?php esc_html_e( 'Bearer token for REST API endpoint /wp-json/align-sync/v1/create-page.', 'align' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>
	</div>
</div>

<script>
<?php readfile( __DIR__ . '/tab-connect-api.js' ); ?>
</script>
