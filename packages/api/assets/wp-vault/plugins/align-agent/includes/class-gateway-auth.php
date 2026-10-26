<?php
/**
 * Align Agent Gateway - Authentication & Authorization Controller
 *
 * Validates incoming REST API requests using configured secret keys via
 * HTTP Authorization Bearer headers or X-Align-Agent-Key headers.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Gateway_Auth {

	/**
	 * Option key used to store the agent secret key in WordPress database.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'align_agent_secret_key';

	/**
	 * Initialize authentication hooks.
	 */
	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Register agent secret key option in WordPress settings.
	 */
	public static function register_settings(): void {
		register_setting(
			'align_agent_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
	}

	/**
	 * Retrieve the configured secret key from wp-config constant or database option.
	 *
	 * @return string Secret key if configured, empty string otherwise.
	 */
	public static function get_secret_key(): string {
		if ( defined( 'ALIGN_AGENT_SECRET_KEY' ) && '' !== trim( (string) ALIGN_AGENT_SECRET_KEY ) ) {
			return trim( (string) ALIGN_AGENT_SECRET_KEY );
		}

		$option_key = get_option( self::OPTION_KEY, '' );
		if ( is_string( $option_key ) && '' !== trim( $option_key ) ) {
			return trim( $option_key );
		}

		return '';
	}

	/**
	 * Validate credentials for incoming REST API requests.
	 *
	 * Uses hash_equals to protect against timing side-channel attacks.
	 * Rejects requests without valid tokens before granting access.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return bool|WP_Error True if authorized, WP_Error otherwise.
	 */
	public static function check_permission( WP_REST_Request $request ) {
		$secret_key = self::get_secret_key();

		if ( '' === $secret_key ) {
			error_log( '[Align Agent] Authentication failed: No secret key configured on server.' );
			return new WP_Error(
				'gateway_not_configured',
				__( 'Align Agent Gateway secret key is not configured on this server.', 'align-agent' ),
				array( 'status' => 503 )
			);
		}

		$token = self::extract_token_from_request( $request );

		if ( '' === $token ) {
			error_log( '[Align Agent] Authentication failed: Missing credentials in request headers.' );
			return new WP_Error(
				'rest_unauthorized',
				__( 'Authorization token is required to access the Align Agent Gateway.', 'align-agent' ),
				array( 'status' => 401 )
			);
		}

		if ( ! hash_equals( $secret_key, $token ) ) {
			error_log( sprintf( '[Align Agent] Auth mismatch: secret_key=[%s], token=[%s]', bin2hex( $secret_key ), bin2hex( $token ) ) );
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid API key provided.', 'align-agent' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Extract token exclusively from Authorization header or X-Align-Agent-Key header.
	 * Query parameter tokens are intentionally not supported to prevent token leaks in server logs.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return string Extracted token string or empty string.
	 */
	private static function extract_token_from_request( WP_REST_Request $request ): string {
		$auth_header = (string) $request->get_header( 'authorization' );
		if ( '' !== $auth_header && preg_match( '/^Bearer\s+(.+)$/i', trim( $auth_header ), $matches ) ) {
			return trim( $matches[1] );
		}

		$custom_header = (string) $request->get_header( 'x-align-agent-key' );
		if ( '' !== $custom_header ) {
			return trim( $custom_header );
		}

		return '';
	}
}
