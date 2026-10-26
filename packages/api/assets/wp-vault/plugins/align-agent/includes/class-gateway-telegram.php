<?php
/**
 * Align Agent Gateway - Telegram Integration Controller
 *
 * Manages Telegram bot credentials and dispatches notification messages
 * to Telegram channels, groups, or private chats via the Telegram Bot API.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Gateway_Telegram {

	/**
	 * Option key for Telegram bot token in WordPress database.
	 *
	 * @var string
	 */
	const TOKEN_OPTION = 'align_agent_telegram_bot_token';

	/**
	 * Option key for Telegram chat ID in WordPress database.
	 *
	 * @var string
	 */
	const CHAT_ID_OPTION = 'align_agent_telegram_chat_id';

	/**
	 * Option key for bound WordPress Administrator user ID executing Telegram actions.
	 *
	 * @var string
	 */
	const USER_ID_OPTION = 'align_agent_telegram_user_id';

	/**
	 * Initialize Telegram settings and hooks.
	 */
	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Sanitize and validate the bound administrator user ID.
	 *
	 * @param mixed $value Input value from settings form.
	 * @return int User ID if valid administrator, 0 otherwise.
	 */
	public static function sanitize_bound_user_id( $value ): int {
		$user_id = absint( $value );
		if ( $user_id > 0 && user_can( $user_id, 'manage_options' ) ) {
			return $user_id;
		}
		return 0;
	}

	/**
	 * Register Telegram settings in WordPress options table.
	 */
	public static function register_settings(): void {
		register_setting(
			'align_agent_settings_group',
			self::TOKEN_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			'align_agent_settings_group',
			self::CHAT_ID_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			'align_agent_settings_group',
			self::USER_ID_OPTION,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( __CLASS__, 'sanitize_bound_user_id' ),
				'default'           => 0,
			)
		);
	}

	/**
	 * Retrieve configured Telegram Bot Token.
	 * Checks constant ALIGN_AGENT_TELEGRAM_BOT_TOKEN first, then database option.
	 *
	 * @return string Bot Token if configured, empty string otherwise.
	 */
	public static function get_bot_token(): string {
		if ( defined( 'ALIGN_AGENT_TELEGRAM_BOT_TOKEN' ) && '' !== trim( (string) ALIGN_AGENT_TELEGRAM_BOT_TOKEN ) ) {
			return trim( (string) ALIGN_AGENT_TELEGRAM_BOT_TOKEN );
		}

		$option = get_option( self::TOKEN_OPTION, '' );
		if ( is_string( $option ) && '' !== trim( $option ) ) {
			return trim( $option );
		}

		return '';
	}

	/**
	 * Retrieve configured Telegram Chat ID.
	 * Checks constant ALIGN_AGENT_TELEGRAM_CHAT_ID first, then database option.
	 *
	 * @return string Chat ID if configured, empty string otherwise.
	 */
	public static function get_chat_id(): string {
		if ( defined( 'ALIGN_AGENT_TELEGRAM_CHAT_ID' ) && '' !== trim( (string) ALIGN_AGENT_TELEGRAM_CHAT_ID ) ) {
			return trim( (string) ALIGN_AGENT_TELEGRAM_CHAT_ID );
		}

		$option = get_option( self::CHAT_ID_OPTION, '' );
		if ( is_string( $option ) && '' !== trim( $option ) ) {
			return trim( $option );
		}

		return '';
	}

	/**
	 * Retrieve configured bound Administrator user ID for Telegram bot actions.
	 *
	 * @return int User ID if valid administrator, 0 otherwise.
	 */
	public static function get_bound_user_id(): int {
		$user_id = absint( get_option( self::USER_ID_OPTION, 0 ) );
		if ( $user_id > 0 && user_can( $user_id, 'manage_options' ) ) {
			return $user_id;
		}

		// Fallback to first available Administrator.
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		if ( ! empty( $admins ) && is_array( $admins ) ) {
			$first_admin_id = absint( $admins[0] );
			if ( $first_admin_id > 0 ) {
				update_option( self::USER_ID_OPTION, $first_admin_id );
				return $first_admin_id;
			}
		}

		return 0;
	}

	/**
	 * Check whether Telegram credentials are fully configured.
	 *
	 * @return bool True if both bot token and chat ID are present.
	 */
	public static function is_configured(): bool {
		return '' !== self::get_bot_token() && '' !== self::get_chat_id();
	}

	/**
	 * Send a notification message via Telegram Bot API.
	 *
	 * @param string $text Message content (HTML or Markdown).
	 * @param string $parse_mode Telegram parse mode ('HTML', 'MarkdownV2', etc.). Default 'HTML'.
	 * @param string $custom_token Optional custom bot token (overrides stored configuration).
	 * @param string $custom_chat_id Optional custom chat ID (overrides stored configuration).
	 * @return array{success: bool, message: string} Result status and response message.
	 */
	public static function send_message( string $text, string $parse_mode = 'HTML', string $custom_token = '', string $custom_chat_id = '' ): array {
		$token   = ! empty( $custom_token ) ? trim( $custom_token ) : self::get_bot_token();
		$chat_id = ! empty( $custom_chat_id ) ? trim( $custom_chat_id ) : self::get_chat_id();

		if ( empty( $token ) ) {
			error_log( '[Align Agent] Telegram send_message skipped: Bot Token is not configured.' );
			return array(
				'success' => false,
				'message' => __( 'Telegram Bot Token is not configured.', 'align-agent' ),
			);
		}

		if ( empty( $chat_id ) ) {
			error_log( '[Align Agent] Telegram send_message skipped: Chat ID is not configured.' );
			return array(
				'success' => false,
				'message' => __( 'Telegram Chat ID is not configured.', 'align-agent' ),
			);
		}

		$endpoint = 'https://api.telegram.org/bot' . $token . '/sendMessage';

		$payload = array(
			'chat_id'                  => $chat_id,
			'text'                     => $text,
			'parse_mode'               => $parse_mode,
			'disable_web_page_preview' => true,
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			error_log( '[Align Agent] Telegram send_message HTTP error: ' . $error_message );
			return array(
				'success' => false,
				'message' => $error_message,
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$body        = json_decode( $raw_body, true );

		if ( $status_code >= 200 && $status_code < 300 && ! empty( $body['ok'] ) ) {
			error_log( sprintf( '[Align Agent] Telegram message successfully delivered to chat %s.', $chat_id ) );
			return array(
				'success' => true,
				'message' => __( 'Message sent successfully.', 'align-agent' ),
			);
		}

		$error_desc = isset( $body['description'] ) ? (string) $body['description'] : sprintf( 'HTTP %d returned by Telegram API.', $status_code );
		error_log( sprintf( '[Align Agent] Telegram API error (HTTP %d): %s', $status_code, $error_desc ) );

		if ( ! empty( $parse_mode ) && ( false !== stripos( $error_desc, "can't parse entities" ) || false !== stripos( $error_desc, 'entity' ) || false !== stripos( $error_desc, 'bad request' ) ) ) {
			error_log( sprintf( '[Align Agent] Telegram parse failed ("%s"). Retrying with plain text.', $error_desc ) );
			return self::send_message( wp_strip_all_tags( $text ), '', $token, $chat_id );
		}

		return array(
			'success' => false,
			'message' => $error_desc,
		);
	}

	/**
	 * Retrieve bot identity and basic information via Telegram getMe endpoint.
	 *
	 * @param string $custom_token Optional custom bot token.
	 * @return array{success: bool, id?: string, username?: string, first_name?: string, message?: string}
	 */
	public static function get_bot_info( string $custom_token = '' ): array {
		$token = ! empty( $custom_token ) ? trim( $custom_token ) : self::get_bot_token();

		if ( empty( $token ) ) {
			return array(
				'success' => false,
				'message' => __( 'Telegram Bot Token is not configured.', 'align-agent' ),
			);
		}

		$endpoint = 'https://api.telegram.org/bot' . $token . '/getMe';

		$response = wp_remote_get(
			$endpoint,
			array(
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			error_log( '[Align Agent] Telegram getMe HTTP error: ' . $error_message );
			return array(
				'success' => false,
				'message' => $error_message,
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$body        = json_decode( $raw_body, true );

		if ( ! is_array( $body ) || empty( $body['ok'] ) ) {
			$error_desc = isset( $body['description'] ) ? (string) $body['description'] : sprintf( 'HTTP %d returned by Telegram API.', $status_code );
			error_log( sprintf( '[Align Agent] Telegram getMe error (HTTP %d): %s', $status_code, $error_desc ) );
			return array(
				'success' => false,
				'message' => $error_desc,
			);
		}

		$result = isset( $body['result'] ) && is_array( $body['result'] ) ? $body['result'] : array();

		return array(
			'success'    => true,
			'id'         => isset( $result['id'] ) ? (string) $result['id'] : '',
			'username'   => isset( $result['username'] ) ? (string) $result['username'] : '',
			'first_name' => isset( $result['first_name'] ) ? (string) $result['first_name'] : '',
		);
	}

	/**
	 * Retrieve the latest chat ID from Telegram updates.
	 *
	 * Queries Telegram getUpdates API endpoint to discover recent interactions.
	 *
	 * @param string $custom_token      Optional custom bot token.
	 * @param bool   $auto_send_confirm Whether to automatically dispatch a verification message once detected.
	 * @return array{success: bool, chat_id?: string, chat_title?: string, chat_type?: string, username?: string, confirmed?: bool, waiting?: bool, bot_username?: string, bot_link?: string, message: string}
	 */
	public static function get_latest_chat_id( string $custom_token = '', bool $auto_send_confirm = false ): array {
		$token = ! empty( $custom_token ) ? trim( $custom_token ) : self::get_bot_token();

		if ( empty( $token ) ) {
			return array(
				'success' => false,
				'message' => __( 'Telegram Bot Token is not configured.', 'align-agent' ),
			);
		}

		$endpoint = 'https://api.telegram.org/bot' . $token . '/getUpdates';

		$response = wp_remote_get(
			$endpoint,
			array(
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			error_log( '[Align Agent] Telegram getUpdates HTTP error: ' . $error_message );
			return array(
				'success' => false,
				'message' => $error_message,
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$body        = json_decode( $raw_body, true );

		if ( ! is_array( $body ) || empty( $body['ok'] ) ) {
			$error_desc = isset( $body['description'] ) ? (string) $body['description'] : sprintf( 'HTTP %d returned by Telegram API.', $status_code );
			error_log( sprintf( '[Align Agent] Telegram getUpdates error (HTTP %d): %s', $status_code, $error_desc ) );
			return array(
				'success' => false,
				'message' => $error_desc,
			);
		}

		if ( empty( $body['result'] ) || ! is_array( $body['result'] ) ) {
			$bot_info = self::get_bot_info( $token );
			if ( ! empty( $bot_info['success'] ) && ! empty( $bot_info['username'] ) ) {
				return array(
					'success'      => false,
					'waiting'      => true,
					'bot_username' => $bot_info['username'],
					'bot_link'     => 'https://t.me/' . $bot_info['username'],
					'message'      => sprintf( __( 'Bot @%s is active! Please open Telegram, send /start to it, and the Chat ID will be detected in realtime.', 'align-agent' ), $bot_info['username'] ),
				);
			}

			return array(
				'success' => false,
				'message' => __( 'No messages found for this bot. Please open Telegram, search your bot, send /start to it, and try again.', 'align-agent' ),
			);
		}

		$updates = $body['result'];
		$chat    = null;

		for ( $i = count( $updates ) - 1; $i >= 0; $i-- ) {
			$update = $updates[ $i ];
			if ( ! empty( $update['message']['chat'] ) ) {
				$chat = $update['message']['chat'];
				break;
			} elseif ( ! empty( $update['my_chat_member']['chat'] ) ) {
				$chat = $update['my_chat_member']['chat'];
				break;
			} elseif ( ! empty( $update['channel_post']['chat'] ) ) {
				$chat = $update['channel_post']['chat'];
				break;
			} elseif ( ! empty( $update['edited_message']['chat'] ) ) {
				$chat = $update['edited_message']['chat'];
				break;
			}
		}

		if ( ! $chat || empty( $chat['id'] ) ) {
			$bot_info = self::get_bot_info( $token );
			if ( ! empty( $bot_info['success'] ) && ! empty( $bot_info['username'] ) ) {
				return array(
					'success'      => false,
					'waiting'      => true,
					'bot_username' => $bot_info['username'],
					'bot_link'     => 'https://t.me/' . $bot_info['username'],
					'message'      => sprintf( __( 'Bot @%s is active! Please open Telegram, send /start to it, and the Chat ID will be detected in realtime.', 'align-agent' ), $bot_info['username'] ),
				);
			}

			return array(
				'success' => false,
				'message' => __( 'No chat information found in bot updates.', 'align-agent' ),
			);
		}

		$chat_id       = (string) $chat['id'];
		$chat_type     = isset( $chat['type'] ) ? (string) $chat['type'] : '';
		$chat_title    = ! empty( $chat['title'] ) ? (string) $chat['title'] : ( ! empty( $chat['first_name'] ) ? (string) $chat['first_name'] : '' );
		$username      = isset( $chat['username'] ) ? (string) $chat['username'] : '';
		$display_label = $chat_title ? $chat_title : $chat_type;

		$confirmed = false;
		if ( $auto_send_confirm ) {
			$confirm_text = "<b>Align Agent Connected!</b>\n\nSite: " . esc_html( get_bloginfo( 'name' ) ) . "\nChat ID: <code>" . esc_html( $chat_id ) . "</code>\nStatus: Connection verified in realtime.";
			$send_result  = self::send_message( $confirm_text, 'HTML', $token, $chat_id );
			$confirmed    = ! empty( $send_result['success'] );
			$message      = sprintf( __( 'Found Chat ID: %1$s (%2$s) & sent confirmation message to Telegram!', 'align-agent' ), $chat_id, $display_label );
		} else {
			$message = sprintf( __( 'Found Chat ID: %1$s (%2$s)', 'align-agent' ), $chat_id, $display_label );
		}

		error_log( sprintf( '[Align Agent] Successfully detected Telegram Chat ID %s (%s). Confirmed: %s', $chat_id, $display_label, $confirmed ? 'yes' : 'no' ) );

		return array(
			'success'    => true,
			'chat_id'    => $chat_id,
			'chat_title' => $chat_title,
			'chat_type'  => $chat_type,
			'username'   => $username,
			'confirmed'  => $confirmed,
			'message'    => $message,
		);
	}

	/**
	 * REST API handler to auto-detect latest Telegram Chat ID.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response REST response.
	 */
	public static function handle_rest_get_chat_id( WP_REST_Request $request ): WP_REST_Response {
		$bot_token = (string) $request->get_param( 'bot_token' );
		$auto_send = rest_sanitize_boolean( $request->get_param( 'auto_send' ) );
		$result    = self::get_latest_chat_id( $bot_token, $auto_send );

		return new WP_REST_Response( $result, ! empty( $result['success'] ) ? 200 : 400 );
	}
}
