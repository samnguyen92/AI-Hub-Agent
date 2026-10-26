<?php
/**
 * Align Agent Gateway - Telegram Pairing Controller
 *
 * Handles the 6-digit pairing-code flow: the admin generates a code, sends it
 * to the bot in Telegram, and this controller matches the incoming message to
 * bind the correct chat ID automatically. Split from Align_Gateway_Telegram to
 * honor the 500-line-per-file limit (SRP).
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Gateway_Telegram_Pairing {

	/**
	 * Transient key holding the active 6-digit pairing code.
	 *
	 * @var string
	 */
	const CODE_TRANSIENT = 'align_agent_tg_pair_code';

	/**
	 * Lifetime (seconds) of a generated pairing code.
	 *
	 * @var int
	 */
	const CODE_TTL = 600;

	/**
	 * Generate (or reuse) a 6-digit pairing code and persist it as a transient.
	 *
	 * The user sends this code to the bot in Telegram; verify() then matches the
	 * incoming message text against it to bind the correct chat.
	 *
	 * @param bool $force Whether to force a brand-new code instead of reusing an active one.
	 * @return string The active 6-digit pairing code.
	 */
	public static function generate_code( bool $force = false ): string {
		$existing = get_transient( self::CODE_TRANSIENT );
		if ( ! $force && is_string( $existing ) && 6 === strlen( $existing ) ) {
			return $existing;
		}

		$code = str_pad( (string) wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );
		set_transient( self::CODE_TRANSIENT, $code, self::CODE_TTL );
		error_log( '[Align Agent] Generated new Telegram pairing code (length: ' . strlen( $code ) . ').' );

		return $code;
	}

	/**
	 * Poll Telegram getUpdates and bind the chat that sent the matching pairing code.
	 *
	 * @param string $custom_token Optional custom bot token.
	 * @param string $code         The 6-digit pairing code to match against incoming messages.
	 * @return array{success: bool, chat_id?: string, chat_title?: string, confirmed?: bool, waiting?: bool, bot_username?: string, bot_link?: string, message: string}
	 */
	public static function verify( string $custom_token = '', string $code = '' ): array {
		$token = ! empty( $custom_token ) ? trim( $custom_token ) : Align_Gateway_Telegram::get_bot_token();
		$code  = trim( $code );

		if ( empty( $token ) ) {
			return array(
				'success' => false,
				'message' => __( 'Telegram Bot Token is not configured.', 'align-agent' ),
			);
		}

		if ( 6 !== strlen( $code ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid pairing code. Please regenerate the code.', 'align-agent' ),
			);
		}

		$endpoint = 'https://api.telegram.org/bot' . $token . '/getUpdates';
		$response = wp_remote_get( $endpoint, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			error_log( '[Align Agent] Telegram pairing getUpdates HTTP error: ' . $error_message );
			return array(
				'success' => false,
				'message' => $error_message,
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || empty( $body['ok'] ) ) {
			$error_desc = isset( $body['description'] ) ? (string) $body['description'] : sprintf( 'HTTP %d returned by Telegram API.', $status_code );
			error_log( sprintf( '[Align Agent] Telegram pairing error (HTTP %d): %s', $status_code, $error_desc ) );
			return array(
				'success' => false,
				'message' => $error_desc,
			);
		}

		$matched_chat = self::find_matching_chat( $body, $code );

		if ( null === $matched_chat ) {
			$bot_info = Align_Gateway_Telegram::get_bot_info( $token );
			$waiting  = array(
				'success' => false,
				'waiting' => true,
				'message' => __( 'Waiting for the pairing code in Telegram. Send the code to your bot to connect.', 'align-agent' ),
			);
			if ( ! empty( $bot_info['success'] ) && ! empty( $bot_info['username'] ) ) {
				$waiting['bot_username'] = $bot_info['username'];
				$waiting['bot_link']     = 'https://t.me/' . $bot_info['username'];
			}
			return $waiting;
		}

		$chat_id    = (string) $matched_chat['id'];
		$chat_title = ! empty( $matched_chat['title'] ) ? (string) $matched_chat['title'] : ( ! empty( $matched_chat['first_name'] ) ? (string) $matched_chat['first_name'] : '' );

		// Persist the resolved chat ID so the connection survives across requests.
		update_option( Align_Gateway_Telegram::CHAT_ID_OPTION, $chat_id );
		update_option( Align_Gateway_Telegram::TOKEN_OPTION, $token );
		delete_transient( self::CODE_TRANSIENT );

		$bridge_cfg                     = get_option( 'align_agent_bridge_settings', array() );
		$bridge_cfg                     = is_array( $bridge_cfg ) ? $bridge_cfg : array();
		$bridge_cfg['bot_token']        = $token;
		$bridge_cfg['allowed_user_ids'] = $chat_id;
		update_option( 'align_agent_bridge_settings', $bridge_cfg );

		$auto_synced = class_exists( 'Align_Gateway_Admin' ) ? Align_Gateway_Admin::maybe_auto_sync_bridge() : false;

		$confirm_text = "<b>Align Agent Connected!</b>\n\nSite: " . esc_html( get_bloginfo( 'name' ) ) . "\nChat ID: <code>" . esc_html( $chat_id ) . "</code>\nStatus: Paired successfully via code.";
		$send_result  = Align_Gateway_Telegram::send_message( $confirm_text, 'HTML', $token, $chat_id );
		$confirmed    = ! empty( $send_result['success'] );

		error_log( sprintf( '[Align Agent] Telegram chat %s paired successfully via code. Confirmed: %s, AutoSynced: %s', $chat_id, $confirmed ? 'yes' : 'no', $auto_synced ? 'yes' : 'no' ) );

		return array(
			'success'     => true,
			'chat_id'     => $chat_id,
			'chat_title'  => $chat_title,
			'confirmed'   => $confirmed,
			'auto_synced' => $auto_synced,
			'message'     => sprintf( __( 'Connected! Chat ID %1$s (%2$s) paired and saved.', 'align-agent' ), $chat_id, $chat_title ? $chat_title : __( 'private chat', 'align-agent' ) ),
		);
	}

	/**
	 * Scan getUpdates results (newest first) for a message whose text contains the code.
	 *
	 * @param array  $body Decoded getUpdates response body.
	 * @param string $code The pairing code to match.
	 * @return array|null The matched chat array, or null if none found.
	 */
	private static function find_matching_chat( array $body, string $code ): ?array {
		if ( empty( $body['result'] ) || ! is_array( $body['result'] ) ) {
			return null;
		}

		$updates = $body['result'];
		for ( $i = count( $updates ) - 1; $i >= 0; $i-- ) {
			$message = isset( $updates[ $i ]['message'] ) ? $updates[ $i ]['message'] : ( isset( $updates[ $i ]['channel_post'] ) ? $updates[ $i ]['channel_post'] : null );
			if ( empty( $message['text'] ) || empty( $message['chat']['id'] ) ) {
				continue;
			}
			// Match code anywhere in the message text (e.g. "/start 123456" or "123456").
			if ( false !== strpos( (string) $message['text'], $code ) ) {
				return $message['chat'];
			}
		}

		return null;
	}
}
