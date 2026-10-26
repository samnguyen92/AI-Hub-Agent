<?php
/**
 * Centralized AI Connection Engine supporting OpenAI SDK & OpenRouter Standards.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_AI_Connection {

	/**
	 * Default OpenRouter completion endpoint.
	 */
	const DEFAULT_OPENROUTER_BASE = 'https://openrouter.ai/api/v1';

	/**
	 * Default OpenAI completion endpoint.
	 */
	const DEFAULT_OPENAI_BASE = 'https://api.openai.com/v1';

	/**
	 * Default AI Model.
	 */
	const DEFAULT_MODEL = 'google/gemini-2.5-flash';

	/**
	 * Initialize AI Connection hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'migrate_legacy_settings' ) );
		add_action( 'wp_ajax_align_test_ai_connection', array( __CLASS__, 'ajax_test_ai_connection' ) );
	}

	/**
	 * Automatically migrate legacy settings to independent OpenRouter & OpenAI options.
	 */
	public static function migrate_legacy_settings() {
		// 1. OpenRouter migration
		$or_key = get_option( 'align_openrouter_api_key', '' );
		if ( empty( $or_key ) ) {
			$legacy_ai_key  = get_option( 'align_ai_api_key', '' );
			$legacy_or_key  = get_option( 'align_openrouter_api_key', '' );
			$legacy_provider = get_option( 'align_ai_provider', 'openrouter' );
			if ( ! empty( $legacy_ai_key ) && ( 'openrouter' === $legacy_provider || 0 === strpos( $legacy_ai_key, 'sk-or-' ) ) ) {
				update_option( 'align_openrouter_api_key', $legacy_ai_key );
			} elseif ( ! empty( $legacy_or_key ) ) {
				update_option( 'align_openrouter_api_key', $legacy_or_key );
			}
		}

		$or_model = get_option( 'align_openrouter_model', '' );
		if ( empty( $or_model ) ) {
			$legacy_ai_model = get_option( 'align_ai_model', '' );
			if ( ! empty( $legacy_ai_model ) && false !== strpos( $legacy_ai_model, '/' ) ) {
				update_option( 'align_openrouter_model', $legacy_ai_model );
			} else {
				update_option( 'align_openrouter_model', self::DEFAULT_MODEL );
			}
		}

		$or_base = get_option( 'align_openrouter_base_url', '' );
		if ( empty( $or_base ) ) {
			update_option( 'align_openrouter_base_url', self::DEFAULT_OPENROUTER_BASE );
		}

		// 2. OpenAI migration
		$openai_key = get_option( 'align_openai_api_key', '' );
		if ( empty( $openai_key ) ) {
			$legacy_ai_key  = get_option( 'align_ai_api_key', '' );
			$legacy_provider = get_option( 'align_ai_provider', 'openrouter' );
			if ( ! empty( $legacy_ai_key ) && 'openai' === $legacy_provider ) {
				update_option( 'align_openai_api_key', $legacy_ai_key );
			}
		}

		$openai_model = get_option( 'align_openai_model', '' );
		if ( empty( $openai_model ) ) {
			$legacy_ai_model = get_option( 'align_ai_model', '' );
			if ( ! empty( $legacy_ai_model ) && false === strpos( $legacy_ai_model, '/' ) && 'openrouter' !== get_option( 'align_ai_provider', '' ) ) {
				update_option( 'align_openai_model', $legacy_ai_model );
			} else {
				update_option( 'align_openai_model', 'gpt-4o-mini' );
			}
		}

		$openai_base = get_option( 'align_openai_base_url', '' );
		if ( empty( $openai_base ) ) {
			update_option( 'align_openai_base_url', self::DEFAULT_OPENAI_BASE );
		}
	}

	/**
	 * Register settings for AI & LLM integration.
	 */
	public static function register_settings() {
		// Active provider
		register_setting( 'align_settings_group', 'align_ai_provider', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_key',
			'default'           => 'openrouter',
		) );

		// OpenRouter credentials
		register_setting( 'align_settings_group', 'align_openrouter_base_url', array(
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => self::DEFAULT_OPENROUTER_BASE,
		) );
		register_setting( 'align_settings_group', 'align_openrouter_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
		register_setting( 'align_settings_group', 'align_openrouter_model', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => self::DEFAULT_MODEL,
		) );

		// OpenAI & Compatible credentials
		register_setting( 'align_settings_group', 'align_openai_base_url', array(
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => self::DEFAULT_OPENAI_BASE,
		) );
		register_setting( 'align_settings_group', 'align_openai_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
		register_setting( 'align_settings_group', 'align_openai_model', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'gpt-4o-mini',
		) );
		register_setting( 'align_settings_group', 'align_openai_organization', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );

		// Legacy fallbacks kept in sync
		register_setting( 'align_settings_group', 'align_ai_base_url', array(
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => self::DEFAULT_OPENROUTER_BASE,
		) );
		register_setting( 'align_settings_group', 'align_ai_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
		register_setting( 'align_settings_group', 'align_ai_model', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => self::DEFAULT_MODEL,
		) );
		register_setting( 'align_settings_group', 'align_ai_organization', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
	}

	/**
	 * Get unified AI configuration with legacy fallbacks.
	 *
	 * @param array $custom_override Optional runtime overrides.
	 * @return array Normalized config array.
	 */
	public static function get_config( array $custom_override = array() ): array {
		$provider = ! empty( $custom_override['provider'] )
			? sanitize_key( $custom_override['provider'] )
			: get_option( 'align_ai_provider', 'openrouter' );

		if ( 'openai' === $provider ) {
			// OpenAI credentials
			$base_url = ! empty( $custom_override['base_url'] )
				? esc_url_raw( $custom_override['base_url'] )
				: get_option( 'align_openai_base_url', '' );
			if ( empty( $base_url ) ) {
				$base_url = get_option( 'align_ai_base_url', self::DEFAULT_OPENAI_BASE );
			}

			$api_key = ! empty( $custom_override['api_key'] ) ? $custom_override['api_key'] : '';
			if ( empty( $api_key ) ) {
				$api_key = get_option( 'align_openai_api_key', '' );
			}
			if ( empty( $api_key ) ) {
				$api_key = get_option( 'align_ai_api_key', '' );
			}

			$model = ! empty( $custom_override['model'] ) ? $custom_override['model'] : '';
			if ( empty( $model ) ) {
				$model = get_option( 'align_openai_model', '' );
			}
			if ( empty( $model ) ) {
				$model = get_option( 'align_ai_model', 'gpt-4o-mini' );
			}

			$organization = ! empty( $custom_override['organization'] )
				? sanitize_text_field( $custom_override['organization'] )
				: get_option( 'align_openai_organization', get_option( 'align_ai_organization', '' ) );
		} else {
			// OpenRouter credentials (default)
			$provider = 'openrouter';
			$base_url = ! empty( $custom_override['base_url'] )
				? esc_url_raw( $custom_override['base_url'] )
				: get_option( 'align_openrouter_base_url', '' );
			if ( empty( $base_url ) ) {
				$base_url = get_option( 'align_ai_base_url', self::DEFAULT_OPENROUTER_BASE );
			}

			$api_key = ! empty( $custom_override['api_key'] ) ? $custom_override['api_key'] : '';
			if ( empty( $api_key ) ) {
				$api_key = get_option( 'align_openrouter_api_key', '' );
			}
			if ( empty( $api_key ) ) {
				$api_key = get_option( 'align_ai_api_key', '' );
			}
			if ( empty( $api_key ) && defined( 'ALIGN_OPENROUTER_API_KEY' ) ) {
				$api_key = ALIGN_OPENROUTER_API_KEY;
			}

			$model = ! empty( $custom_override['model'] ) ? $custom_override['model'] : '';
			if ( empty( $model ) ) {
				$model = get_option( 'align_openrouter_model', '' );
			}
			if ( empty( $model ) ) {
				$model = get_option( 'align_ai_model', '' );
			}
			if ( empty( $model ) && defined( 'ALIGN_OPENROUTER_MODEL' ) ) {
				$model = ALIGN_OPENROUTER_MODEL;
			}
			if ( empty( $model ) ) {
				$model = self::DEFAULT_MODEL;
			}

			$organization = '';
		}

		$base_url = rtrim( $base_url, '/' );

		return array(
			'provider'     => $provider,
			'base_url'     => $base_url,
			'endpoint'     => $base_url . '/chat/completions',
			'api_key'      => trim( $api_key ),
			'model'        => trim( $model ),
			'organization' => trim( $organization ),
			'timeout'      => 120,
		);
	}

	/**
	 * Build standardized HTTP request headers based on provider.
	 *
	 * @param array $config Normalized AI configuration.
	 * @return array HTTP headers.
	 */
	public static function build_headers( array $config ): array {
		$headers = array(
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $config['api_key'],
		);

		if ( 'openrouter' === $config['provider'] || strpos( $config['base_url'], 'openrouter.ai' ) !== false ) {
			$site_url = function_exists( 'site_url' ) ? site_url() : 'https://align-theme.local';
			$site_name = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'name' ) : 'Align Theme';
			$headers['HTTP-Referer'] = $site_url;
			$headers['X-Title']      = $site_name;
		}

		if ( ! empty( $config['organization'] ) ) {
			$headers['OpenAI-Organization'] = $config['organization'];
		}

		return $headers;
	}

	/**
	 * AJAX handler to test connection and verify credentials with the AI provider.
	 */
	public static function ajax_test_ai_connection() {
		$nonce = $_POST['security'] ?? $_POST['align_settings_nonce'] ?? '';
		if ( ! wp_verify_nonce( $nonce, 'align_settings_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed. Please reload the page.', 'align' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized user permissions.', 'align' ) ) );
		}

		$raw_provider = sanitize_key( $_POST['provider'] ?? 'openrouter' );
		$raw_base_url = esc_url_raw( trim( $_POST['base_url'] ?? '' ) );
		$raw_api_key  = sanitize_text_field( trim( $_POST['api_key'] ?? '' ) );
		$raw_model    = sanitize_text_field( trim( $_POST['model'] ?? '' ) );
		$raw_org      = sanitize_text_field( trim( $_POST['organization'] ?? '' ) );

		if ( empty( $raw_api_key ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please enter an API Key before testing.', 'align' ) ) );
		}

		$config = self::get_config( array(
			'provider'     => $raw_provider,
			'base_url'     => $raw_base_url,
			'api_key'      => $raw_api_key,
			'model'        => $raw_model,
			'organization' => $raw_org,
		) );

		error_log( sprintf( '[Align API] Testing AI connection for provider "%s" at "%s" with model "%s"', $config['provider'], $config['endpoint'], $config['model'] ) );

		$start_time = microtime( true );

		// Standard OpenAI chat completion test request with 1 token output
		$payload = array(
			'model'      => $config['model'],
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => 'Ping. Reply "ok".',
				),
			),
			'max_tokens' => 5,
		);

		$response = wp_remote_post(
			$config['endpoint'],
			array(
				'headers'     => self::build_headers( $config ),
				'body'        => wp_json_encode( $payload ),
				'timeout'     => 15,
				'data_format' => 'body',
			)
		);

		$latency_ms = (int) round( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $response ) ) {
			$error_msg = $response->get_error_message();
			error_log( sprintf( '[Align API] AI connection test failed: %s | Latency: %dms', $error_msg, $latency_ms ) );
			wp_send_json_error( array(
				'message'    => sprintf( esc_html__( 'Network Connection Error: %s', 'align' ), $error_msg ),
				'latency_ms' => $latency_ms,
			) );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code >= 200 && $status_code < 300 && is_array( $data ) ) {
			$ai_reply = $data['choices'][0]['message']['content'] ?? 'ok';
			$detected_model = $data['model'] ?? $config['model'];

			error_log( sprintf( '[Align API] AI connection test successful | HTTP %d | Latency: %dms | Model: %s', $status_code, $latency_ms, $detected_model ) );

			wp_send_json_success( array(
				'message'        => esc_html__( 'Connection verified successfully! OpenAI SDK & endpoint operational.', 'align' ),
				'status_code'    => $status_code,
				'latency_ms'     => $latency_ms,
				'detected_model' => $detected_model,
				'provider'       => $config['provider'],
				'reply'          => trim( $ai_reply ),
			) );
		}

		// Handle specific error codes
		$err_detail = 'HTTP ' . $status_code;
		if ( is_array( $data ) && ! empty( $data['error'] ) ) {
			if ( is_string( $data['error'] ) ) {
				$err_detail = $data['error'];
			} elseif ( is_array( $data['error'] ) && ! empty( $data['error']['message'] ) ) {
				$err_detail = $data['error']['message'];
			}
		}

		error_log( sprintf( '[Align API] AI connection test rejected | HTTP %d: %s | Latency: %dms', $status_code, $err_detail, $latency_ms ) );

		wp_send_json_error( array(
			'message'     => sprintf( esc_html__( 'Provider Error (%d): %s', 'align' ), $status_code, $err_detail ),
			'status_code' => $status_code,
			'latency_ms'  => $latency_ms,
		) );
	}
}
