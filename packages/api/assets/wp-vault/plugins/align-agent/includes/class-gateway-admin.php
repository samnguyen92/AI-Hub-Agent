<?php
/**
 * Align Agent Gateway - Admin Settings & Dashboard Controller
 *
 * Registers the WP Admin Settings page, enqueues admin styling/scripts, and processes
 * AJAX requests for License Activation, Telegram pairing, AI testing, and Bridge sync.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Gateway_Admin {

	const MENU_SLUG           = 'align-agent';
	const OPTION_LICENSE_KEY  = 'align_bridge_license_key';
	const OPTION_MID_URL      = 'align_bridge_middleware_url';
	const OPTION_LICENSE_DATA = 'align_agent_license_data';

	/**
	 * Initialize admin hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_footer', array( __CLASS__, 'render_global_chatbox' ) );
		add_action( 'wp_ajax_align_agent_activate_license', array( __CLASS__, 'ajax_activate_license' ) );
		add_action( 'wp_ajax_align_agent_deactivate_license', array( __CLASS__, 'ajax_deactivate_license' ) );
		add_action( 'wp_ajax_align_agent_pair_start', array( __CLASS__, 'ajax_pair_start' ) );
		add_action( 'wp_ajax_align_agent_pair_poll', array( __CLASS__, 'ajax_pair_poll' ) );
		add_action( 'wp_ajax_align_agent_test_ai', array( __CLASS__, 'ajax_test_ai' ) );
		add_action( 'wp_ajax_align_agent_test_tools', array( __CLASS__, 'ajax_test_tools' ) );
		add_action( 'wp_ajax_align_agent_register_bridge', array( __CLASS__, 'ajax_register_bridge' ) );
	}

	/**
	 * Return normalized hostname from home_url() (lowercase, without leading www.).
	 */
	public static function get_normalized_domain(): string {
		$host = strtolower( trim( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
		return rtrim( (string) preg_replace( '/^www\./i', '', $host ), '.' );
	}

	/**
	 * Retrieve stored license activation metadata.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_license_data(): array {
		$data = get_option( self::OPTION_LICENSE_DATA, array() );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Check whether the site has a valid activated license key.
	 */
	public static function is_license_active(): bool {
		$license_key = trim( (string) get_option( self::OPTION_LICENSE_KEY, '' ) );
		if ( '' === $license_key || 0 !== strpos( $license_key, 'ak_' ) ) {
			return false;
		}
		$data = self::get_license_data();
		return ! empty( $data['valid'] );
	}

	/**
	 * Register settings submenu with Context-Aware parent detection.
	 */
	public static function register_menu(): void {
		$parent_slug = defined( 'ALIGN_CORE_PATH' ) ? 'align-settings' : 'options-general.php';
		add_submenu_page(
			$parent_slug,
			__( 'Align Agent Gateway', 'align-agent' ),
			__( 'Align Agent', 'align-agent' ),
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Enqueue admin scripts and stylesheets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( string $hook ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( self::is_license_active() ) {
			wp_enqueue_media();
			wp_enqueue_style( 'align-agent-chatbox-css', ALIGN_AGENT_PLUGIN_URL . 'assets/chatbox.css', array( 'dashicons' ), ALIGN_AGENT_VERSION );
			wp_enqueue_style( 'align-agent-chatbox-content-css', ALIGN_AGENT_PLUGIN_URL . 'assets/chatbox-content.css', array( 'align-agent-chatbox-css' ), ALIGN_AGENT_VERSION );

			$scripts = array(
				array( 'align-agent-marked-js', 'assets/vendor/marked.min.js', array(), '12.0.2' ),
				array( 'align-agent-purify-js', 'assets/vendor/purify.min.js', array(), '3.1.6' ),
				array( 'align-agent-chatbox-render-enhance-js', 'assets/chatbox-render-enhance.js', array(), ALIGN_AGENT_VERSION ),
				array( 'align-agent-chatbox-render-js', 'assets/chatbox-render.js', array( 'align-agent-marked-js', 'align-agent-purify-js', 'align-agent-chatbox-render-enhance-js' ), ALIGN_AGENT_VERSION ),
				array( 'align-agent-chatbox-upload-js', 'assets/chatbox-upload.js', array(), ALIGN_AGENT_VERSION ),
				array( 'align-agent-chatbox-media-js', 'assets/chatbox-media.js', array( 'align-agent-chatbox-upload-js', 'media-views' ), ALIGN_AGENT_VERSION ),
				array( 'align-agent-chatbox-js', 'assets/chatbox.js', array( 'align-agent-chatbox-render-js', 'align-agent-chatbox-upload-js', 'align-agent-chatbox-media-js' ), ALIGN_AGENT_VERSION ),
				array( 'align-agent-chatbox-continue-js', 'assets/chatbox-continue.js', array( 'align-agent-chatbox-js' ), ALIGN_AGENT_VERSION ),
			);
			foreach ( $scripts as $s ) {
				wp_enqueue_script( $s[0], ALIGN_AGENT_PLUGIN_URL . $s[1], $s[2], $s[3], true );
			}

			wp_localize_script(
				'align-agent-chatbox-js',
				'alignChatboxData',
				array(
					'restUrl'        => esc_url_raw( rest_url( defined( 'ALIGN_AGENT_REST_NS' ) ? ALIGN_AGENT_REST_NS : 'align-agent/v1' ) ),
					'wpMediaRestUrl' => esc_url_raw( rest_url( 'wp/v2/media' ) ),
					'restNonce'      => wp_create_nonce( 'wp_rest' ),
					'builderActive'  => class_exists( 'Align_Builder_Crud_Handler' ),
					'userId'         => get_current_user_id(),
					'i18n'          => array(
						'thinkingStatus'       => __( 'Thinking...', 'align-agent' ),
						'runningToolStatus'    => __( 'Running tool...', 'align-agent' ),
						'executingWriteStatus' => __( 'Applying changes...', 'align-agent' ),
						'cancellingStatus'     => __( 'Cancelling...', 'align-agent' ),
						'rollingBackStatus'    => __( 'Restoring previous revision...', 'align-agent' ),
						'clearingStatus'       => __( 'Clearing session...', 'align-agent' ),
						'approveBtn'           => __( 'Approve', 'align-agent' ),
						'cancelBtn'            => __( 'Cancel', 'align-agent' ),
						'previewBtn'           => __( 'Preview HTML', 'align-agent' ),
						'viewCssBtn'           => __( 'View CSS', 'align-agent' ),
						'viewJsBtn'            => __( 'View JS', 'align-agent' ),
						'jsWarningsTitle'      => __( 'Security Warnings:', 'align-agent' ),
						'viewPageBtn'          => __( 'View Page', 'align-agent' ),
						'rollbackBtn'          => __( 'Rollback', 'align-agent' ),
						'confirmAction'        => __( 'Confirm Write Operation', 'align-agent' ),
						'continueLabel'        => __( 'Continue', 'align-agent' ),
						'maxTurnsError'        => sprintf( __( 'Reached the maximum limit of %d automatic tool turns.', 'align-agent' ), Align_Agent_Loop::MAX_CONTINUE_TURNS ),
					),
				)
			);
		}

		if ( ! str_contains( $hook, self::MENU_SLUG ) ) {
			return;
		}

		wp_enqueue_style( 'align-agent-admin-css', ALIGN_AGENT_PLUGIN_URL . 'assets/admin.css', array( 'dashicons' ), ALIGN_AGENT_VERSION );
		wp_enqueue_script( 'align-agent-admin-js', ALIGN_AGENT_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), ALIGN_AGENT_VERSION, true );
		wp_localize_script(
			'align-agent-admin-js',
			'alignAgentData',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'align_agent_admin_nonce' ),
				'testingAiMsg' => __( 'Testing connection to AI Provider...', 'align-agent' ),
				'aiSuccessMsg' => __( 'AI Provider connected successfully!', 'align-agent' ),
				'aiFailedMsg'  => __( 'Failed to connect to AI Provider.', 'align-agent' ),
			)
		);
	}

	/**
	 * Render global chatbox drawer markup in the admin footer when license is active.
	 */
	public static function render_global_chatbox(): void {
		if ( ! current_user_can( 'manage_options' ) || ! self::is_license_active() ) {
			return;
		}
		require ALIGN_AGENT_PLUGIN_DIR . 'views/chatbox-panel.php';
	}

	/**
	 * Verify AJAX capability and security nonce.
	 */
	private static function verify_ajax_request(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized user.', 'align-agent' ) ), 403 );
		}
		$nonce = isset( $_POST['security'] ) ? sanitize_text_field( wp_unslash( $_POST['security'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'align_agent_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed - invalid nonce.', 'align-agent' ) ), 403 );
		}
	}

	/**
	 * AJAX endpoint to activate the site license against the Middleware Hub.
	 */
	public static function ajax_activate_license(): void {
		self::verify_ajax_request();

		$middleware_url = isset( $_POST['middleware_url'] ) ? sanitize_url( wp_unslash( $_POST['middleware_url'] ) ) : '';
		$license_key    = isset( $_POST['license_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) ) : '';
		$middleware_url = '' !== $middleware_url ? rtrim( $middleware_url, '/' ) : 'http://localhost:3000';

		if ( '' === $license_key || 0 !== strpos( $license_key, 'ak_' ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid License Key starting with ak_.', 'align-agent' ) ), 400 );
		}

		$domain   = self::get_normalized_domain();
		$response = wp_remote_post(
			$middleware_url . '/api/license/activate',
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'X-Api-Key'     => $license_key,
					'X-Site-Domain' => $domain,
				),
				'body'    => wp_json_encode( array( 'key' => $license_key, 'domain' => $domain ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( '[Align Agent] License activation connection failed: ' . $response->get_error_message() );
			wp_send_json_error( array( 'message' => sprintf( __( 'Middleware connection failed: %s', 'align-agent' ), $response->get_error_message() ) ), 502 );
		}

		$status_code  = (int) wp_remote_retrieve_response_code( $response );
		$raw_body     = wp_remote_retrieve_body( $response );
		$decoded      = json_decode( $raw_body, true );
		$decoded      = is_array( $decoded ) ? $decoded : array();
		$payload_data = isset( $decoded['data'] ) && is_array( $decoded['data'] ) ? $decoded['data'] : $decoded;

		if ( $status_code >= 200 && $status_code < 300 && ( ! empty( $decoded['success'] ) || ! empty( $payload_data['valid'] ) ) ) {
			$license_data = array(
				'valid'        => true,
				'siteId'       => isset( $payload_data['siteId'] ) ? sanitize_text_field( (string) $payload_data['siteId'] ) : '',
				'domain'       => isset( $payload_data['domain'] ) ? sanitize_text_field( (string) $payload_data['domain'] ) : $domain,
				'status'       => isset( $payload_data['status'] ) ? sanitize_text_field( (string) $payload_data['status'] ) : 'license-only',
				'activated_at' => current_time( 'mysql' ),
			);

			update_option( self::OPTION_LICENSE_KEY, $license_key );
			update_option( self::OPTION_MID_URL, $middleware_url );
			update_option( self::OPTION_LICENSE_DATA, $license_data );

			$bridge_cfg                   = get_option( 'align_agent_bridge_settings', array() );
			$bridge_cfg                   = is_array( $bridge_cfg ) ? $bridge_cfg : array();
			$bridge_cfg['middleware_url'] = $middleware_url;
			update_option( 'align_agent_bridge_settings', $bridge_cfg );

			self::maybe_auto_sync_bridge();
			$license_data = self::get_license_data();

			error_log( sprintf( '[Align Agent] License activated for domain %s (siteId: %s, status: %s).', $license_data['domain'], $license_data['siteId'], $license_data['status'] ) );
			wp_send_json_success( array( 'message' => __( 'License activated successfully! Reloading dashboard...', 'align-agent' ), 'license' => $license_data ) );
		}

		$reason = isset( $decoded['error'] ) ? (string) $decoded['error'] : ( isset( $payload_data['reason'] ) ? (string) $payload_data['reason'] : '' );
		if ( 'domain_mismatch' === $reason ) {
			$err_msg = sprintf( __( 'License key is bound to another domain (current domain: %s).', 'align-agent' ), $domain );
		} elseif ( 'invalid_key' === $reason ) {
			$err_msg = __( 'Invalid or revoked License Key.', 'align-agent' );
		} else {
			$err_msg = '' !== $reason ? $reason : sprintf( 'HTTP %d: %s', $status_code, $raw_body );
		}

		error_log( sprintf( '[Align Agent] License activation rejected (HTTP %d): %s', $status_code, $err_msg ) );
		wp_send_json_error( array( 'message' => $err_msg ), $status_code >= 400 ? $status_code : 403 );
	}

	/**
	 * AJAX endpoint to clear stored license key and return to activation gate.
	 */
	public static function ajax_deactivate_license(): void {
		self::verify_ajax_request();
		delete_option( self::OPTION_LICENSE_DATA );
		delete_option( self::OPTION_LICENSE_KEY );
		error_log( sprintf( '[Align Agent] User #%d deactivated the site license key.', get_current_user_id() ) );
		wp_send_json_success( array( 'message' => __( 'License key removed. Reloading...', 'align-agent' ) ) );
	}

	/**
	 * AJAX endpoint to start a Telegram pairing session: generates a 6-digit code.
	 */
	public static function ajax_pair_start(): void {
		self::verify_ajax_request();
		$bot_token = isset( $_POST['bot_token'] ) ? sanitize_text_field( wp_unslash( $_POST['bot_token'] ) ) : '';
		$force     = ! empty( $_POST['force'] );
		$bot_info = Align_Gateway_Telegram::get_bot_info( $bot_token );
		if ( empty( $bot_info['success'] ) ) {
			wp_send_json_error( array( 'message' => ! empty( $bot_info['message'] ) ? $bot_info['message'] : __( 'Invalid Bot Token. Please check and try again.', 'align-agent' ) ), 400 );
		}

		$code         = Align_Gateway_Telegram_Pairing::generate_code( $force );
		$bot_username = ! empty( $bot_info['username'] ) ? $bot_info['username'] : '';
		error_log( '[Align Agent] Pairing session started for bot @' . $bot_username . '.' );

		wp_send_json_success( array( 'code' => $code, 'bot_username' => $bot_username, 'bot_link' => $bot_username ? 'https://t.me/' . $bot_username : '', 'message' => __( 'Pairing code generated. Send it to your bot in Telegram.', 'align-agent' ) ) );
	}

	/**
	 * AJAX endpoint polled by the browser to detect the chat that sent the pairing code.
	 */
	public static function ajax_pair_poll(): void {
		self::verify_ajax_request();
		$bot_token = isset( $_POST['bot_token'] ) ? sanitize_text_field( wp_unslash( $_POST['bot_token'] ) ) : '';
		$code      = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		$result    = Align_Gateway_Telegram_Pairing::verify( $bot_token, $code );
		! empty( $result['success'] ) ? wp_send_json_success( $result ) : wp_send_json_error( $result, 400 );
	}

	/**
	 * AJAX endpoint to test connection to OpenAI-compatible AI provider.
	 */
	public static function ajax_test_ai(): void {
		self::verify_ajax_request();
		$endpoint = isset( $_POST['endpoint'] ) ? sanitize_url( wp_unslash( $_POST['endpoint'] ) ) : '';
		$api_key  = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		$model    = isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '';
		$result   = Align_Gateway_AI::test_connection( $endpoint, $api_key, $model );
		! empty( $result['success'] ) ? wp_send_json_success( $result ) : wp_send_json_error( $result, 400 );
	}

	/**
	 * AJAX endpoint to test OpenAI tool/function calling support on the configured AI provider.
	 */
	public static function ajax_test_tools(): void {
		self::verify_ajax_request();
		$endpoint = isset( $_POST['endpoint'] ) ? sanitize_url( wp_unslash( $_POST['endpoint'] ) ) : '';
		$api_key  = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		$model    = isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '';
		$result   = Align_Gateway_AI::test_tool_calling( $endpoint, $api_key, $model );
		! empty( $result['success'] ) ? wp_send_json_success( $result ) : wp_send_json_error( $result, 400 );
	}

	/**
	 * Auto-sync stored WordPress Bridge configuration to Middleware if all required fields exist.
	 */
	public static function maybe_auto_sync_bridge(): bool {
		if ( ! self::is_license_active() ) {
			return false;
		}
		$cfg          = get_option( 'align_agent_bridge_settings', array() );
		$cfg          = is_array( $cfg ) ? $cfg : array();
		$mid_url      = rtrim( ! empty( $cfg['middleware_url'] ) ? (string) $cfg['middleware_url'] : (string) get_option( self::OPTION_MID_URL, 'http://localhost:3000' ), '/' );
		$bot_token    = ! empty( $cfg['bot_token'] ) ? trim( (string) $cfg['bot_token'] ) : trim( (string) Align_Gateway_Telegram::get_bot_token() );
		$allowed_raw  = ! empty( $cfg['allowed_user_ids'] ) ? trim( (string) $cfg['allowed_user_ids'] ) : trim( (string) Align_Gateway_Telegram::get_chat_id() );
		$llm_base_url = ! empty( $cfg['llm_base_url'] ) ? trim( (string) $cfg['llm_base_url'] ) : 'https://api.lsia.site/v1';
		$llm_base_url = (string) preg_replace( '#/chat/completions/?$#i', '', rtrim( $llm_base_url, '/' ) );
		$llm_api_key  = ! empty( $cfg['llm_api_key'] ) ? trim( (string) $cfg['llm_api_key'] ) : trim( (string) Align_Gateway_AI::get_api_key() );
		$llm_model    = ! empty( $cfg['llm_model'] ) ? trim( (string) $cfg['llm_model'] ) : trim( (string) get_option( Align_Gateway_AI::MODEL_OPTION, 'gpt-4o' ) );
		$wp_username  = ! empty( $cfg['wp_username'] ) ? trim( (string) $cfg['wp_username'] ) : 'admin';
		$wp_app_pass  = ! empty( $cfg['wp_app_password'] ) ? trim( (string) $cfg['wp_app_password'] ) : '';
		$allowed_ids  = array_values( array_map( 'intval', array_filter( array_map( 'trim', explode( ',', $allowed_raw ) ), 'is_numeric' ) ) );

		if ( '' === $bot_token || empty( $allowed_ids ) || '' === $llm_base_url || '' === $llm_api_key || '' === $llm_model || '' === $wp_username || '' === $wp_app_pass ) {
			return false;
		}
		$res = self::push_config_to_middleware( $mid_url, $bot_token, $allowed_ids, $llm_base_url, $llm_api_key, $llm_model, $wp_username, $wp_app_pass );
		return ! empty( $res['success'] );
	}

	/**
	 * Push site configuration payload to Middleware /api/license/config and update license status.
	 *
	 * @return array<string, mixed>
	 */
	private static function push_config_to_middleware( string $middleware_url, string $bot_token, array $allowed_ids, string $llm_base_url, string $llm_api_key, string $llm_model, string $wp_username, string $wp_app_password ): array {
		$license_key = trim( (string) get_option( self::OPTION_LICENSE_KEY, '' ) );
		$domain      = self::get_normalized_domain();
		$mcp_url     = get_rest_url( null, 'align-agent/v1/mcp' );
		$mcp_url     = preg_match( '/(\.local|\.test|^localhost$|^127\.0\.0\.1$)$/i', $domain ) ? set_url_scheme( $mcp_url, 'http' ) : $mcp_url;
		$payload     = array(
			'telegram' => array( 'botToken' => $bot_token, 'allowedUserIds' => $allowed_ids ),
			'mcp'      => array( 'url' => $mcp_url, 'username' => $wp_username, 'appPassword' => $wp_app_password ),
			'llm'      => array( 'baseUrl' => $llm_base_url, 'apiKey' => $llm_api_key, 'model' => $llm_model ),
		);
		$response    = wp_remote_post( $middleware_url . '/api/license/config', array( 'timeout' => 15, 'headers' => array( 'Content-Type' => 'application/json', 'X-Api-Key' => $license_key, 'X-Site-Domain' => $domain ), 'body' => wp_json_encode( $payload ) ) );
		if ( is_wp_error( $response ) ) {
			error_log( '[Align Agent] Bridge config sync failed: ' . $response->get_error_message() );
			return array( 'success' => false, 'status_code' => 502, 'message' => sprintf( __( 'Middleware connection failed: %s', 'align-agent' ), $response->get_error_message() ) );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $raw_body, true );
		$decoded     = is_array( $decoded ) ? $decoded : array();
		$res_data    = isset( $decoded['data'] ) && is_array( $decoded['data'] ) ? $decoded['data'] : ( isset( $decoded['site'] ) && is_array( $decoded['site'] ) ? $decoded['site'] : $decoded );

		if ( $status_code >= 200 && $status_code < 300 && ! empty( $decoded['success'] ) ) {
			$license_data           = self::get_license_data();
			$license_data['valid']  = true;
			$license_data['status'] = isset( $res_data['status'] ) ? sanitize_text_field( (string) $res_data['status'] ) : 'connected';
			if ( ! empty( $res_data['siteId'] ) ) {
				$license_data['siteId'] = sanitize_text_field( (string) $res_data['siteId'] );
			}
			update_option( self::OPTION_LICENSE_DATA, $license_data );
			$site_id = ! empty( $license_data['siteId'] ) ? $license_data['siteId'] : $domain;
			error_log( sprintf( '[Align Agent] Bridge configuration synced for site "%s" (status: %s).', $site_id, $license_data['status'] ) );
			return array( 'success' => true, 'site_id' => $site_id, 'status' => $license_data['status'], 'site' => $res_data );
		}

		$err_msg = isset( $decoded['error'] ) ? (string) $decoded['error'] : sprintf( 'HTTP %d: %s', $status_code, $raw_body );
		error_log( sprintf( '[Align Agent] Bridge config sync error (HTTP %d): %s', $status_code, $err_msg ) );
		return array( 'success' => false, 'status_code' => $status_code >= 400 ? $status_code : 400, 'message' => $err_msg, 'details' => $decoded );
	}

	/**
	 * AJAX endpoint to sync WordPress site configuration into the Middleware Bridge via License Key.
	 */
	public static function ajax_register_bridge(): void {
		self::verify_ajax_request();
		if ( ! self::is_license_active() ) {
			wp_send_json_error( array( 'message' => __( 'License is not active. Please activate your license key first.', 'align-agent' ) ), 403 );
		}

		$mid_url      = isset( $_POST['middleware_url'] ) ? sanitize_url( wp_unslash( $_POST['middleware_url'] ) ) : (string) get_option( self::OPTION_MID_URL, 'http://localhost:3000' );
		$approval     = isset( $_POST['approval_mode'] ) && 'manual' === $_POST['approval_mode'] ? 'manual' : 'auto';
		$bot_token    = isset( $_POST['bot_token'] ) ? sanitize_text_field( wp_unslash( $_POST['bot_token'] ) ) : '';
		$allowed_in   = isset( $_POST['allowed_user_ids'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['allowed_user_ids'] ) ) ) : '';
		$llm_base_url = isset( $_POST['llm_base_url'] ) ? sanitize_url( wp_unslash( $_POST['llm_base_url'] ) ) : 'https://api.lsia.site/v1';
		$llm_api_key  = isset( $_POST['llm_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['llm_api_key'] ) ) : '';
		$llm_model    = isset( $_POST['llm_model'] ) ? sanitize_text_field( wp_unslash( $_POST['llm_model'] ) ) : 'gpt-4o';
		$wp_username  = isset( $_POST['wp_username'] ) ? sanitize_user( wp_unslash( $_POST['wp_username'] ) ) : wp_get_current_user()->user_login;
		$wp_app_pass  = isset( $_POST['wp_app_password'] ) ? sanitize_text_field( wp_unslash( $_POST['wp_app_password'] ) ) : '';

		$mid_url      = '' !== $mid_url ? rtrim( $mid_url, '/' ) : 'http://localhost:3000';
		$llm_base_url = (string) preg_replace( '#/chat/completions/?$#i', '', rtrim( '' !== $llm_base_url ? $llm_base_url : 'https://api.lsia.site/v1', '/' ) );
		$llm_model    = '' !== $llm_model ? $llm_model : 'gpt-4o';
		$wp_username  = '' !== $wp_username ? $wp_username : wp_get_current_user()->user_login;
		$allowed_in   = '' !== $allowed_in ? $allowed_in : ( is_numeric( trim( (string) Align_Gateway_Telegram::get_chat_id() ) ) ? trim( (string) Align_Gateway_Telegram::get_chat_id() ) : '' );

		update_option( 'align_agent_approval_mode', $approval );
		update_option( self::OPTION_MID_URL, $mid_url );
		if ( '' !== $bot_token ) {
			update_option( Align_Gateway_Telegram::TOKEN_OPTION, $bot_token );
		}
		$first_id = trim( explode( ',', $allowed_in )[0] );
		if ( is_numeric( $first_id ) ) {
			update_option( Align_Gateway_Telegram::CHAT_ID_OPTION, $first_id );
		}
		update_option( Align_Gateway_AI::ENDPOINT_OPTION, $llm_base_url . '/chat/completions' );
		if ( '' !== $llm_api_key ) {
			update_option( Align_Gateway_AI::KEY_OPTION, $llm_api_key );
		}
		update_option( Align_Gateway_AI::MODEL_OPTION, $llm_model );
		update_option(
			'align_agent_bridge_settings',
			array(
				'middleware_url'   => $mid_url,
				'bot_token'        => $bot_token,
				'allowed_user_ids' => $allowed_in,
				'llm_base_url'     => $llm_base_url,
				'llm_api_key'      => $llm_api_key,
				'llm_model'        => $llm_model,
				'wp_username'      => $wp_username,
				'wp_app_password'  => $wp_app_pass,
			)
		);

		$allowed_ids = array_values( array_map( 'intval', array_filter( array_map( 'trim', explode( ',', $allowed_in ) ), 'is_numeric' ) ) );
		$sync_res    = self::push_config_to_middleware( $mid_url, $bot_token, $allowed_ids, $llm_base_url, $llm_api_key, $llm_model, $wp_username, $wp_app_pass );
		if ( ! empty( $sync_res['success'] ) ) {
			wp_send_json_success( array( 'message' => sprintf( __( 'Settings saved and synced to Bridge for "%s" (status: %s).', 'align-agent' ), $sync_res['site_id'], $sync_res['status'] ), 'site' => $sync_res['site'] ) );
		}
		wp_send_json_error( array( 'message' => $sync_res['message'], 'details' => $sync_res['details'] ?? array() ), $sync_res['status_code'] );
	}

	/**
	 * Render the Align Agent Gateway administrative dashboard.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$telegram_bot_token = Align_Gateway_Telegram::get_bot_token();
		$ai_api_key         = Align_Gateway_AI::get_api_key();
		$notice_html        = '';

		if ( isset( $_POST['align_agent_save_settings'] ) ) {
			check_admin_referer( 'align_agent_save_nonce', 'align_agent_nonce_field' );
			if ( isset( $_POST['align_agent_approval_mode'] ) ) {
				update_option( 'align_agent_approval_mode', 'manual' === $_POST['align_agent_approval_mode'] ? 'manual' : 'auto' );
			}
			if ( isset( $_POST['align_agent_git_token'] ) ) {
				update_option( Align_Agent_Git_Updater::OPTION_TOKEN, sanitize_text_field( wp_unslash( $_POST['align_agent_git_token'] ) ) );
			}
			$notice_html = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully.', 'align-agent' ) . '</p></div>';
			if ( class_exists( 'Align_Agent_Seo_Adapters' ) ) {
				Align_Agent_Seo_Adapters::handle_settings_save( $notice_html );
			}
		}

		$is_license_active = self::is_license_active();
		$license_data      = self::get_license_data();
		$license_key       = (string) get_option( self::OPTION_LICENSE_KEY, '' );
		$site_domain       = self::get_normalized_domain();
		$approval_mode     = get_option( 'align_agent_approval_mode', 'auto' );
		require ALIGN_AGENT_PLUGIN_DIR . 'views/settings-page.php';
	}
}
