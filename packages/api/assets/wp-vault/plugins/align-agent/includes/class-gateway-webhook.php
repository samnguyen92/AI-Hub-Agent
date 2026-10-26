<?php
/**
 * Align Agent Gateway - Telegram Webhook Autonomous Controller
 *
 * Handles incoming Telegram Webhook updates, natural language conversational AI processing,
 * and autonomous generation and modification of WordPress pages matching the theme's design system.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Gateway_Webhook {

	/**
	 * Option key for Telegram Webhook secret token.
	 */
	const SECRET_OPTION = 'align_agent_telegram_webhook_secret';

	/**
	 * Option key for custom public Webhook URL.
	 */
	const WEBHOOK_URL_OPTION = 'align_agent_telegram_webhook_url';

	/**
	 * Initialize webhook component.
	 */
	public static function init(): void {
		self::get_secret_token();
	}

	/**
	 * Get the Webhook endpoint URL dynamically auto-detected from current domain or custom override.
	 */
	public static function get_webhook_url(): string {
		$endpoint = rest_url( ( defined( 'ALIGN_AGENT_REST_NS' ) ? ALIGN_AGENT_REST_NS : 'align-agent/v1' ) . '/telegram/webhook' );
		$custom   = get_option( self::WEBHOOK_URL_OPTION, '' );

		if ( ! empty( $custom ) && is_string( $custom ) ) {
			return trim( $custom );
		}

		return $endpoint;
	}

	/**
	 * Retrieve or automatically generate the 32-character webhook secret token.
	 */
	public static function get_secret_token(): string {
		$secret = get_option( self::SECRET_OPTION, '' );
		if ( empty( $secret ) || ! is_string( $secret ) ) {
			$secret = preg_replace( '/[^A-Za-z0-9_-]/', 'x', wp_generate_password( 32, false, false ) );
			update_option( self::SECRET_OPTION, $secret );
		}
		return $secret;
	}

	/**
	 * Register the Webhook with Telegram Bot API.
	 */
	public static function set_webhook( string $url = '' ): array {
		$token = Align_Gateway_Telegram::get_bot_token();
		if ( empty( $token ) ) {
			return array( 'success' => false, 'message' => __( 'Telegram Bot Token is not configured.', 'align-agent' ) );
		}

		if ( empty( $url ) ) {
			$webhook_url = self::get_webhook_url();
		} else {
			$raw_url   = trim( $url );
			$clean_url = esc_url_raw( $raw_url, array( 'http', 'https' ) );
			if ( empty( $clean_url ) ) {
				$clean_url = sanitize_text_field( $raw_url );
			}
			$webhook_url      = $clean_url;
			$default_endpoint = rest_url( ( defined( 'ALIGN_AGENT_REST_NS' ) ? ALIGN_AGENT_REST_NS : 'align-agent/v1' ) . '/telegram/webhook' );
			if ( $clean_url !== $default_endpoint ) {
				update_option( self::WEBHOOK_URL_OPTION, $clean_url );
			} else {
				delete_option( self::WEBHOOK_URL_OPTION );
			}
		}

		$response = wp_remote_post( 'https://api.telegram.org/bot' . $token . '/setWebhook', array(
			'timeout' => 20,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array(
				'url'             => $webhook_url,
				'secret_token'    => self::get_secret_token(),
				'allowed_updates' => array( 'message', 'callback_query' ),
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			$err = $response->get_error_message();
			error_log( '[Align Agent] Telegram setWebhook HTTP error: ' . $err );
			return array( 'success' => false, 'message' => $err );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$ok     = $status >= 200 && $status < 300 && ! empty( $body['ok'] );
		$msg    = $body['description'] ?? ( $ok ? __( 'Webhook registered successfully.', 'align-agent' ) : 'Registration failed' );

		$safe_log_url = preg_replace( '#://([^:@]+):([^@]+)@#', '://$1:***@', $webhook_url );
		error_log( sprintf( '[Align Agent] Telegram Webhook registration: %s (%s)', $ok ? 'success' : 'failed', $safe_log_url ) );
		return array( 'success' => $ok, 'message' => (string) $msg, 'raw' => is_array( $body ) ? $body : array() );
	}

	/**
	 * Remove the Webhook from Telegram Bot API.
	 */
	public static function delete_webhook(): array {
		delete_option( self::WEBHOOK_URL_OPTION );
		$token = Align_Gateway_Telegram::get_bot_token();
		if ( empty( $token ) ) {
			return array( 'success' => false, 'message' => __( 'Telegram Bot Token is not configured.', 'align-agent' ) );
		}

		$response = wp_remote_post( 'https://api.telegram.org/bot' . $token . '/deleteWebhook', array(
			'timeout' => 15,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array( 'drop_pending_updates' => false ) ),
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$ok   = wp_remote_retrieve_response_code( $response ) < 300 && ! empty( $body['ok'] );
		$msg  = $body['description'] ?? ( $ok ? __( 'Webhook removed successfully.', 'align-agent' ) : 'Removal failed' );

		error_log( '[Align Agent] Telegram Webhook removed.' );
		return array( 'success' => $ok, 'message' => (string) $msg, 'raw' => is_array( $body ) ? $body : array() );
	}

	/**
	 * Retrieve current Webhook status from Telegram Bot API with diagnostics.
	 */
	public static function get_webhook_info(): array {
		$token = Align_Gateway_Telegram::get_bot_token();
		if ( empty( $token ) ) {
			return array( 'success' => false, 'message' => __( 'Telegram Bot Token is not configured.', 'align-agent' ) );
		}

		$response = wp_remote_get( 'https://api.telegram.org/bot' . $token . '/getWebhookInfo', array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['ok'] ) ) {
			return array( 'success' => false, 'message' => $body['description'] ?? 'API error' );
		}

		$info          = isset( $body['result'] ) && is_array( $body['result'] ) ? $body['result'] : array();
		$url           = isset( $info['url'] ) ? (string) $info['url'] : '';
		$pending       = isset( $info['pending_update_count'] ) ? (int) $info['pending_update_count'] : 0;
		$last_err_msg  = isset( $info['last_error_message'] ) ? (string) $info['last_error_message'] : '';
		$last_err_date = ! empty( $info['last_error_date'] ) ? gmdate( 'Y-m-d H:i:s', (int) $info['last_error_date'] ) : '';

		$configured_url = self::get_webhook_url();
		$host           = (string) wp_parse_url( $configured_url, PHP_URL_HOST );
		$is_local       = preg_match( '/\.local$/i', $host ) || in_array( $host, array( 'localhost', '127.0.0.1' ), true ) || 0 === strpos( $configured_url, 'http://' );
		$diag_warning   = $is_local ? ' ' . __( '[Notice: Local (.local) or non-HTTPS URL detected. Telegram requires a public HTTPS URL like LocalWP Live Link.]', 'align-agent' ) : '';

		if ( false !== stripos( $last_err_msg, '401' ) || false !== stripos( $last_err_msg, 'Unauthorized' ) || preg_match( '/\.localsite\.io$/i', $host ) ) {
			$diag_warning .= ' ' . __( '[Notice: LocalWP Live Link Basic Auth detected. Switch to Polling Mode or use Cloudflare Tunnel for webhook.]', 'align-agent' );
		}

		if ( empty( $url ) ) {
			$msg = __( 'Webhook is not active on Telegram (Polling mode active).', 'align-agent' ) . $diag_warning;
		} else {
			$msg = sprintf( __( 'Webhook active at %1$s (Pending updates: %2$d).', 'align-agent' ), $url, $pending );
			if ( ! empty( $last_err_msg ) ) {
				$msg .= sprintf( __( ' Last error: "%1$s" at %2$s UTC.', 'align-agent' ), $last_err_msg, $last_err_date );
			}
			$msg .= $diag_warning;
		}

		return array( 'success' => true, 'message' => $msg, 'data' => $info );
	}

	/**
	 * REST API endpoint handler for POST /align-agent/v1/telegram/webhook.
	 */
	public static function handle_webhook( WP_REST_Request $request ) {
		$secret_token   = self::get_secret_token();
		$incoming_token = $request->get_header( 'x-telegram-bot-api-secret-token' );
		if ( empty( $incoming_token ) && isset( $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ) ) {
			$incoming_token = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ) );
		}

		if ( empty( $incoming_token ) || ! hash_equals( $secret_token, (string) $incoming_token ) ) {
			error_log( '[Align Agent] Telegram Webhook rejected: Invalid secret token header.' );
			return new WP_REST_Response( array( 'error' => 'Forbidden: Invalid secret token.' ), 403 );
		}

		$payload = $request->get_json_params();
		if ( empty( $payload ) || ! is_array( $payload ) ) {
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		// 1. Deduplicate by update_id with 1-hour transient TTL.
		$update_id = isset( $payload['update_id'] ) ? (int) $payload['update_id'] : 0;
		if ( $update_id > 0 ) {
			$dedup_key = 'align_agent_tg_upd_' . $update_id;
			if ( get_transient( $dedup_key ) ) {
				error_log( sprintf( '[Align Agent] Telegram Webhook duplicate update skipped: %d', $update_id ) );
				return new WP_REST_Response( array( 'ok' => true, 'duplicate' => true ), 200 );
			}
			set_transient( $dedup_key, 1, HOUR_IN_SECONDS );
		}

		// 2. Handle callback_query (inline button click for approval / cancel).
		if ( ! empty( $payload['callback_query'] ) && is_array( $payload['callback_query'] ) ) {
			$cb_query = $payload['callback_query'];
			$cb_id    = isset( $cb_query['id'] ) ? (string) $cb_query['id'] : '';
			$chat_id  = isset( $cb_query['message']['chat']['id'] ) ? (string) $cb_query['message']['chat']['id'] : '';

			// Verify authorized chat ID.
			$configured_chat_id = Align_Gateway_Telegram::get_chat_id();
			if ( empty( $configured_chat_id ) || (string) $chat_id !== (string) $configured_chat_id ) {
				error_log( sprintf( '[Align Agent] Callback query rejected: unauthorized chat %s (configured %s).', $chat_id, $configured_chat_id ) );
				if ( class_exists( 'Align_Gateway_Telegram_Approval' ) ) {
					Align_Gateway_Telegram_Approval::answer_callback_query( $cb_id, __( 'Unauthorized chat.', 'align-agent' ), true );
				}
				return new WP_REST_Response( array( 'ok' => true, 'error' => 'Unauthorized chat.' ), 200 );
			}

			// Immediate ACK to Telegram client to prevent UI button timeout.
			if ( class_exists( 'Align_Gateway_Telegram_Approval' ) ) {
				Align_Gateway_Telegram_Approval::answer_callback_query( $cb_id, __( 'Processing...', 'align-agent' ), false );
			}

			// Defer heavy execution to background shutdown action.
			add_action(
				'shutdown',
				function () use ( $cb_query ) {
					if ( function_exists( 'ignore_user_abort' ) ) {
						ignore_user_abort( true );
					}
					if ( function_exists( 'set_time_limit' ) ) {
						@set_time_limit( 0 );
					}
					if ( function_exists( 'fastcgi_finish_request' ) ) {
						fastcgi_finish_request();
						if ( class_exists( 'Align_Gateway_Telegram_Approval' ) ) {
							Align_Gateway_Telegram_Approval::handle_callback_query( $cb_query );
						}
						return;
					}
					if ( function_exists( 'litespeed_finish_request' ) ) {
						litespeed_finish_request();
						if ( class_exists( 'Align_Gateway_Telegram_Approval' ) ) {
							Align_Gateway_Telegram_Approval::handle_callback_query( $cb_query );
						}
						return;
					}
					if ( class_exists( 'Align_Gateway_Telegram_Approval' ) ) {
						Align_Gateway_Telegram_Approval::handle_callback_query( $cb_query );
					}
				}
			);

			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		// 3. Ignore edited messages to avoid repeating operations.
		if ( ! empty( $payload['edited_message'] ) ) {
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		$message = ! empty( $payload['message'] ) && is_array( $payload['message'] ) ? $payload['message'] : null;
		if ( empty( $message ) ) {
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		$chat_id     = isset( $message['chat']['id'] ) ? (string) $message['chat']['id'] : '';
		$text        = isset( $message['text'] ) ? trim( (string) $message['text'] ) : '';
		if ( empty( $text ) && isset( $message['caption'] ) ) {
			$text = trim( (string) $message['caption'] );
		}
		$sender_name = isset( $message['from']['first_name'] ) ? (string) $message['from']['first_name'] : 'User';

		$has_media = class_exists( 'Align_Gateway_Telegram_Media' ) && null !== Align_Gateway_Telegram_Media::extract( $message );

		if ( empty( $chat_id ) || ( empty( $text ) && ! $has_media ) ) {
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		$configured_chat_id = Align_Gateway_Telegram::get_chat_id();
		if ( empty( $configured_chat_id ) ) {
			error_log( '[Align Agent] Telegram operation rejected: Bot Chat ID is not configured in settings.' );
			return new WP_REST_Response( array( 'ok' => true, 'error' => 'Chat ID not configured.' ), 200 );
		}
		if ( (string) $chat_id !== (string) $configured_chat_id ) {
			error_log( sprintf( '[Align Agent] Telegram rejected unauthorized chat %s (configured %s).', $chat_id, $configured_chat_id ) );
			Align_Gateway_Telegram::send_message( "<b>Unauthorized Chat</b>\nChat ID <code>" . esc_html( $chat_id ) . "</code> is not permitted.", 'HTML', '', $chat_id );
			return new WP_REST_Response( array( 'ok' => true, 'error' => 'Unauthorized chat.' ), 200 );
		}

		// 4. Schedule background runner execution (shutdown hook with early 200 ACK).
		Align_Gateway_Telegram_Runner::schedule_run( $text, $chat_id, $sender_name, $message );
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Process natural language message via Autonomous AI Builder engine.
	 * Delegates to Align_Gateway_Agent::process_message().
	 *
	 * @param string $text        User message text.
	 * @param string $chat_id     Telegram chat ID.
	 * @param string $sender_name Sender display name.
	 * @return array<string, mixed>
	 */
	public static function process_natural_language_message( string $text, string $chat_id, string $sender_name ): array {
		return Align_Gateway_Agent::process_message( $text, $chat_id, $sender_name );
	}

	/**
	 * Fetch newest user message via getUpdates and process autonomously.
	 * Delegates to Align_Gateway_Agent::sync_and_process_latest().
	 *
	 * @return array<string, mixed>
	 */
	public static function sync_and_process_latest(): array {
		return Align_Gateway_Agent::sync_and_process_latest();
	}

	/**
	 * Verify admin permissions and nonce for AJAX actions.
	 */
	private static function check_admin_security(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized user.', 'align-agent' ) ), 403 );
		}
		$nonce = isset( $_POST['security'] ) ? sanitize_text_field( wp_unslash( $_POST['security'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'align_agent_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed - invalid nonce.', 'align-agent' ) ), 403 );
		}
	}

	/**
	 * AJAX endpoint to manually sync and process latest Telegram message.
	 */
	public static function ajax_sync_telegram(): void {
		self::check_admin_security();
		$result = self::sync_and_process_latest();
		empty( $result['success'] ) ? wp_send_json_error( array( 'message' => $result['message'] ), 400 ) : wp_send_json_success( $result );
	}

	/**
	 * AJAX endpoint to register Telegram Webhook.
	 */
	public static function ajax_set_webhook(): void {
		self::check_admin_security();
		$custom_url = ! empty( $_POST['webhook_url'] ) ? sanitize_url( wp_unslash( $_POST['webhook_url'] ) ) : self::get_webhook_url();
		$result     = self::set_webhook( $custom_url );
		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( array( 'message' => $result['message'], 'webhook_url' => self::get_webhook_url() ) );
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ), 400 );
		}
	}

	/**
	 * AJAX endpoint to remove Telegram Webhook.
	 */
	public static function ajax_delete_webhook(): void {
		self::check_admin_security();
		$result = self::delete_webhook();
		empty( $result['success'] ) ? wp_send_json_error( array( 'message' => $result['message'] ), 400 ) : wp_send_json_success( array( 'message' => $result['message'] ) );
	}

	/**
	 * AJAX endpoint to check Telegram Webhook status.
	 */
	public static function ajax_status_webhook(): void {
		self::check_admin_security();
		$result = self::get_webhook_info();
		empty( $result['success'] ) ? wp_send_json_error( array( 'message' => $result['message'] ), 400 ) : wp_send_json_success( $result );
	}
}


