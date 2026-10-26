<?php
/**
 * Align Agent Gateway - Autonomous NLP Agent Bridge
 *
 * Delegates incoming Telegram natural language messages and polling sync
 * to Align_Gateway_Telegram_Runner using the unified Align_Agent_Loop engine.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Gateway_Agent {

	/**
	 * Initialize agent component.
	 */
	public static function init(): void {
		// Ready for future filters or scheduled triggers.
	}

	/**
	 * Process a natural language message via Telegram Runner.
	 *
	 * @param string $text        User message text.
	 * @param string $chat_id     Telegram chat ID.
	 * @param string $sender_name Sender display name.
	 * @return array<string, mixed>
	 */
	public static function process_message( string $text, string $chat_id, string $sender_name = 'User' ): array {
		return Align_Gateway_Telegram_Runner::run( $text, $chat_id, $sender_name );
	}

	/**
	 * Fetch newest user message via getUpdates and process autonomously.
	 *
	 * @return array<string, mixed>
	 */
	public static function sync_and_process_latest(): array {
		$token = Align_Gateway_Telegram::get_bot_token();
		if ( empty( $token ) ) {
			return array( 'success' => false, 'message' => __( 'Telegram Bot Token is not configured.', 'align-agent' ) );
		}

		$last_update_id = (int) get_option( 'align_agent_last_update_id', 0 );
		$offset         = ( $last_update_id > 0 ? $last_update_id + 1 : 0 );
		$url            = 'https://api.telegram.org/bot' . $token . '/getUpdates?limit=20' . ( $offset > 0 ? '&offset=' . $offset : '' );

		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 409 === $code ) {
			return array(
				'success' => false,
				'message' => __( 'Telegram Webhook is currently active. Remove Webhook first if you want to use manual Sync Runner.', 'align-agent' ),
			);
		}

		if ( empty( $body['ok'] ) || empty( $body['result'] ) || ! is_array( $body['result'] ) ) {
			return array( 'success' => false, 'message' => __( 'No updates or messages found in Telegram getUpdates.', 'align-agent' ) );
		}

		$target_update = null;
		$latest_msg    = null;
		foreach ( array_reverse( $body['result'] ) as $u ) {
			$uid = isset( $u['update_id'] ) ? (int) $u['update_id'] : 0;
			if ( $uid <= $last_update_id ) {
				continue;
			}
			// Handle callback_query in polling mode.
			if ( ! empty( $u['callback_query'] ) && is_array( $u['callback_query'] ) ) {
				if ( class_exists( 'Align_Gateway_Telegram_Approval' ) ) {
					Align_Gateway_Telegram_Approval::handle_callback_query( $u['callback_query'] );
				}
				update_option( 'align_agent_last_update_id', $uid );
				wp_remote_get( 'https://api.telegram.org/bot' . $token . '/getUpdates?offset=' . ( $uid + 1 ) . '&limit=1', array( 'timeout' => 5 ) );
				return array( 'success' => true, 'message' => 'Processed callback query.' );
			}

			$m = ! empty( $u['message'] ) ? $u['message'] : ( ! empty( $u['edited_message'] ) ? $u['edited_message'] : null );
			if ( empty( $m ) || empty( $m['chat']['id'] ) ) {
				continue;
			}

			$has_text  = ! empty( $m['text'] ) || ! empty( $m['caption'] );
			$has_media = class_exists( 'Align_Gateway_Telegram_Media' ) && null !== Align_Gateway_Telegram_Media::extract( $m );

			if ( $has_text || $has_media ) {
				$target_update = $u;
				$latest_msg    = $m;
				break;
			}
		}

		if ( ! $latest_msg || ! $target_update ) {
			return array( 'success' => false, 'message' => __( 'No new text messages found in Telegram updates.', 'align-agent' ) );
		}

		$target_uid = (int) $target_update['update_id'];
		update_option( 'align_agent_last_update_id', $target_uid );
		wp_remote_get( 'https://api.telegram.org/bot' . $token . '/getUpdates?offset=' . ( $target_uid + 1 ) . '&limit=1', array( 'timeout' => 5 ) );

		$chat_id     = (string) $latest_msg['chat']['id'];
		$text        = isset( $latest_msg['text'] ) ? trim( (string) $latest_msg['text'] ) : '';
		if ( empty( $text ) && isset( $latest_msg['caption'] ) ) {
			$text = trim( (string) $latest_msg['caption'] );
		}
		$sender_name = isset( $latest_msg['from']['first_name'] ) ? (string) $latest_msg['from']['first_name'] : 'User';

		$configured_chat_id = Align_Gateway_Telegram::get_chat_id();
		if ( empty( $configured_chat_id ) ) {
			error_log( '[Align Agent] Telegram operation rejected: Bot Chat ID is not configured in settings.' );
			return array( 'success' => false, 'message' => 'Chat ID not configured.' );
		}
		if ( (string) $chat_id !== (string) $configured_chat_id ) {
			error_log( sprintf( '[Align Agent] Telegram rejected unauthorized chat %s (configured %s).', $chat_id, $configured_chat_id ) );
			Align_Gateway_Telegram::send_message( "<b>Unauthorized Chat</b>\nChat ID <code>" . esc_html( $chat_id ) . "</code> is not permitted.", 'HTML', '', $chat_id );
			return array( 'success' => false, 'message' => 'Unauthorized chat.' );
		}

		$result                     = Align_Gateway_Telegram_Runner::run( $text, $chat_id, $sender_name, $latest_msg );
		$result['processed_text']   = $text;
		$result['sender_name']      = $sender_name;
		$result['sync_success_msg'] = sprintf( __( 'Processed message from %1$s: "%2$s"', 'align-agent' ), $sender_name, $text );

		return array( 'success' => true, 'message' => $result['sync_success_msg'], 'data' => $result );
	}
}
