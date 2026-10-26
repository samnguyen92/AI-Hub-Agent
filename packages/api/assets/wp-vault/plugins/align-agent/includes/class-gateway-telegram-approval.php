<?php
/**
 * Align Agent Gateway - Telegram Approval Controller
 *
 * Manages Telegram inline keyboard approval cards for queued write operations,
 * handles callback queries securely, updates Telegram message states, and resumes execution.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Gateway_Telegram_Approval {

	/**
	 * Send pending approval cards to Telegram chat with inline keyboard buttons.
	 * Groups multiple pending writes into a single batch card when count >= 2.
	 *
	 * @param string $chat_id           Target Telegram chat ID.
	 * @param int    $user_id           Bound WordPress user ID.
	 * @param array  $pending_approvals Array of pending write operations from Align_Agent_Loop.
	 */
	public static function send_pending_cards( string $chat_id, int $user_id, array $pending_approvals ): void {
		if ( empty( $pending_approvals ) ) {
			return;
		}

		$token = Align_Gateway_Telegram::get_bot_token();
		if ( empty( $token ) ) {
			return;
		}

		// Group into 1 combined batch card if 2 or more actions require approval.
		if ( count( $pending_approvals ) >= 2 ) {
			self::send_batch_card( $chat_id, $pending_approvals, $token );
			return;
		}

		// Single action card.
		foreach ( $pending_approvals as $pending ) {
			$pending_id = isset( $pending['pending_id'] ) ? (string) $pending['pending_id'] : ( isset( $pending['id'] ) ? (string) $pending['id'] : '' );
			if ( empty( $pending_id ) ) {
				continue;
			}

			$tool_name   = isset( $pending['tool_name'] ) ? (string) $pending['tool_name'] : 'unknown_tool';
			$summary_raw = isset( $pending['summary'] ) ? $pending['summary'] : '';
			$summary     = is_array( $summary_raw ) ? ( $summary_raw['title'] ?? $summary_raw['description'] ?? '' ) : (string) $summary_raw;
			$danger      = ! empty( $pending['danger'] );
			$diff_hint   = isset( $pending['diff_hint'] ) ? (string) $pending['diff_hint'] : '';

			$text  = $danger ? "⚠️ <b>DANGEROUS ACTION REQUIRES APPROVAL</b>\n\n" : "✍️ <b>ACTION REQUIRES APPROVAL</b>\n\n";
			$text .= "<b>Tool:</b> <code>" . esc_html( $tool_name ) . "</code>\n";
			$text .= "<b>Summary:</b> " . esc_html( $summary ) . "\n";

			if ( $danger ) {
				$text .= "<b>Warning:</b> This operation modifies sensitive structure or permissions.\n";
			}
			if ( '' !== $diff_hint ) {
				$text .= "<b>Target:</b> <code>" . esc_html( mb_substr( $diff_hint, 0, 300, 'UTF-8' ) ) . "</code>\n";
			}
			$text .= "\n<i>Please confirm to execute this change on your website.</i>";

			$reply_markup = array(
				'inline_keyboard' => array(
					array(
						array( 'text' => '✅ Approve', 'callback_data' => 'a|' . $pending_id ),
						array( 'text' => '❌ Cancel', 'callback_data' => 'c|' . $pending_id ),
					),
				),
			);

			$sent = self::send_inline_card( $chat_id, $text, $reply_markup, $token );
			if ( ! empty( $sent['success'] ) && ! empty( $sent['message_id'] ) && class_exists( 'Align_Agent_Pending_Store' ) ) {
				Align_Agent_Pending_Store::set_tg_message_id( $pending_id, (int) $sent['message_id'] );
			}
		}
	}

	/**
	 * Send a single combined batch card for multiple pending actions.
	 */
	private static function send_batch_card( string $chat_id, array $pending_approvals, string $token ): void {
		$batch_id    = 'b_' . wp_generate_password( 12, false, false );
		$pending_ids = array();
		$has_danger  = false;
		$items_text  = '';
		$index       = 1;

		foreach ( $pending_approvals as $pending ) {
			$pid = isset( $pending['pending_id'] ) ? (string) $pending['pending_id'] : ( isset( $pending['id'] ) ? (string) $pending['id'] : '' );
			if ( empty( $pid ) ) {
				continue;
			}
			$pending_ids[] = $pid;
			if ( ! empty( $pending['danger'] ) ) {
				$has_danger = true;
			}

			$tool_name   = isset( $pending['tool_name'] ) ? (string) $pending['tool_name'] : 'operation';
			$summary_raw = isset( $pending['summary'] ) ? $pending['summary'] : '';
			$summary     = is_array( $summary_raw ) ? ( $summary_raw['title'] ?? $summary_raw['description'] ?? '' ) : (string) $summary_raw;

			$items_text .= sprintf( "%d. <b>Tool:</b> <code>%s</code>\n   <b>Summary:</b> %s\n", $index, esc_html( $tool_name ), esc_html( $summary ) );
			$index++;
		}

		if ( empty( $pending_ids ) ) {
			return;
		}

		$total  = count( $pending_ids );
		$header = $has_danger
			? sprintf( "⚠️ <b>DANGEROUS ACTIONS REQUIRE APPROVAL</b> (%d operations)\n\n", $total )
			: sprintf( "✍️ <b>ACTIONS REQUIRE APPROVAL</b> (%d operations)\n\n", $total );

		$text = $header . $items_text;
		if ( $has_danger ) {
			$text .= "\n<b>Warning:</b> Includes sensitive structure or administrative changes.\n";
		}
		$text .= "\n<i>Please confirm to execute all operations on your website.</i>";

		if ( class_exists( 'Align_Agent_Pending_Store' ) ) {
			Align_Agent_Pending_Store::set_batch( $batch_id, $pending_ids );
		}

		$reply_markup = array(
			'inline_keyboard' => array(
				array(
					array( 'text' => sprintf( '✅ Approve all (%d)', $total ), 'callback_data' => 'A|' . $batch_id ),
					array( 'text' => '❌ Cancel all', 'callback_data' => 'C|' . $batch_id ),
				),
			),
		);

		$sent = self::send_inline_card( $chat_id, $text, $reply_markup, $token );
		if ( ! empty( $sent['success'] ) && ! empty( $sent['message_id'] ) && class_exists( 'Align_Agent_Pending_Store' ) ) {
			foreach ( $pending_ids as $pid ) {
				Align_Agent_Pending_Store::set_tg_message_id( $pid, (int) $sent['message_id'] );
			}
		}
	}

	/**
	 * Send a message with inline keyboard markup via Telegram Bot API.
	 */
	public static function send_inline_card( string $chat_id, string $text, array $reply_markup, string $token ): array {
		$endpoint = 'https://api.telegram.org/bot' . $token . '/sendMessage';
		$payload  = array(
			'chat_id'                  => $chat_id,
			'text'                     => $text,
			'parse_mode'               => 'HTML',
			'disable_web_page_preview' => true,
			'reply_markup'             => $reply_markup,
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( '[Align Agent] Failed to send Telegram inline card: ' . $response->get_error_message() );
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$msg_id = isset( $body['result']['message_id'] ) ? (int) $body['result']['message_id'] : 0;

		return array( 'success' => true, 'message_id' => $msg_id );
	}

	/**
	 * Answer a Telegram callback query (shows notification or alert to user).
	 */
	public static function answer_callback_query( string $callback_query_id, string $text = '', bool $show_alert = false ): void {
		$token = Align_Gateway_Telegram::get_bot_token();
		if ( empty( $token ) || empty( $callback_query_id ) ) {
			return;
		}

		wp_remote_post(
			'https://api.telegram.org/bot' . $token . '/answerCallbackQuery',
			array(
				'timeout'  => 5,
				'blocking' => false,
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'body'     => wp_json_encode(
					array(
						'callback_query_id' => $callback_query_id,
						'text'              => $text,
						'show_alert'        => $show_alert,
					)
				),
			)
		);
	}

	/**
	 * Edit original message to remove buttons and show confirmation status.
	 */
	public static function edit_message_text( string $chat_id, int $message_id, string $new_text ): void {
		$token = Align_Gateway_Telegram::get_bot_token();
		if ( empty( $token ) || empty( $chat_id ) || $message_id <= 0 ) {
			return;
		}

		wp_remote_post(
			'https://api.telegram.org/bot' . $token . '/editMessageText',
			array(
				'timeout' => 10,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'chat_id'                  => $chat_id,
						'message_id'               => $message_id,
						'text'                     => $new_text,
						'parse_mode'               => 'HTML',
						'disable_web_page_preview' => true,
						'reply_markup'             => array( 'inline_keyboard' => array() ),
					)
				),
			)
		);
	}

	/**
	 * Mark previous messages as superseded on Telegram and strip their inline buttons.
	 *
	 * @param int[] $message_ids Array of Telegram message IDs to mark superseded.
	 */
	public static function mark_superseded( array $message_ids ): void {
		if ( empty( $message_ids ) ) {
			return;
		}

		$token   = Align_Gateway_Telegram::get_bot_token();
		$chat_id = Align_Gateway_Telegram::get_chat_id();
		if ( empty( $token ) || empty( $chat_id ) ) {
			return;
		}

		$unique_ids = array_unique( array_map( 'intval', $message_ids ) );
		$endpoint   = 'https://api.telegram.org/bot' . $token . '/editMessageText';

		foreach ( $unique_ids as $mid ) {
			if ( $mid <= 0 ) {
				continue;
			}

			wp_remote_post(
				$endpoint,
				array(
					'timeout'  => 5,
					'blocking' => false,
					'headers'  => array( 'Content-Type' => 'application/json' ),
					'body'     => wp_json_encode(
						array(
							'chat_id'                  => $chat_id,
							'message_id'               => $mid,
							'text'                     => "⏭ <i>Superseded by a new message.</i>",
							'parse_mode'               => 'HTML',
							'disable_web_page_preview' => true,
							'reply_markup'             => array( 'inline_keyboard' => array() ),
						)
					),
				)
			);
			error_log( sprintf( '[Align Agent] Marked Telegram message #%d as superseded.', $mid ) );
		}
	}

	/**
	 * Handle incoming Telegram callback_query update under runner lock protection.
	 *
	 * @param array $callback Telegram callback_query object.
	 */
	public static function handle_callback_query( array $callback ): void {
		$cb_id      = isset( $callback['id'] ) ? (string) $callback['id'] : '';
		$data       = isset( $callback['data'] ) ? (string) $callback['data'] : '';
		$message    = isset( $callback['message'] ) && is_array( $callback['message'] ) ? $callback['message'] : array();
		$chat_id    = isset( $message['chat']['id'] ) ? (string) $message['chat']['id'] : '';
		$message_id = isset( $message['message_id'] ) ? (int) $message['message_id'] : 0;
		$orig_text  = isset( $message['text'] ) ? (string) $message['text'] : '';

		// Verify authorized chat ID.
		$configured_chat_id = Align_Gateway_Telegram::get_chat_id();
		if ( empty( $configured_chat_id ) || (string) $chat_id !== (string) $configured_chat_id ) {
			error_log( sprintf( '[Align Agent] Callback query rejected: unauthorized chat %s (configured %s).', $chat_id, $configured_chat_id ) );
			self::answer_callback_query( $cb_id, __( 'Unauthorized chat.', 'align-agent' ), true );
			return;
		}

		$parts = explode( '|', $data, 2 );
		if ( count( $parts ) < 2 ) {
			self::answer_callback_query( $cb_id, __( 'Invalid callback data.', 'align-agent' ), false );
			return;
		}

		$type      = $parts[0];
		$target_id = sanitize_text_field( $parts[1] );
		if ( empty( $type ) || empty( $target_id ) ) {
			self::answer_callback_query( $cb_id, __( 'Unknown action.', 'align-agent' ), false );
			return;
		}

		// Delegate Undo callbacks (u| standard, U| force) to dedicated controller.
		if ( ( 'u' === $type || 'U' === $type ) && class_exists( 'Align_Gateway_Telegram_Undo' ) ) {
			Align_Gateway_Telegram_Undo::handle_callback( $cb_id, $type, $target_id, $chat_id, $message_id, $orig_text );
			return;
		}

		// Delegate Continue callbacks (k| conversation_id) to dedicated controller.
		if ( 'k' === $type && class_exists( 'Align_Gateway_Telegram_Continue' ) ) {
			Align_Gateway_Telegram_Continue::handle_callback( $cb_id, $target_id, $chat_id, $message_id, $orig_text );
			return;
		}

		// Acquire process mutex lock.
		if ( class_exists( 'Align_Gateway_Telegram_Lock' ) && ! Align_Gateway_Telegram_Lock::acquire( 900 ) ) {
			self::answer_callback_query( $cb_id, __( 'Align Agent is currently busy. Please try again in a moment.', 'align-agent' ), true );
			return;
		}

		$bound_user_id = Align_Gateway_Telegram::get_bound_user_id();
		if ( $bound_user_id <= 0 ) {
			if ( class_exists( 'Align_Gateway_Telegram_Lock' ) ) {
				Align_Gateway_Telegram_Lock::release();
			}
			self::answer_callback_query( $cb_id, __( 'Bound admin not configured.', 'align-agent' ), true );
			return;
		}

		try {
			wp_set_current_user( $bound_user_id );
			Align_Agent_Conversation::set_channel( 'telegram' );

			if ( 'A' === $type || 'C' === $type ) {
				self::handle_batch_callback( $cb_id, $chat_id, $message_id, $orig_text, $target_id, 'A' === $type ? 'approve' : 'cancel', $bound_user_id );
			} elseif ( 'a' === $type || 'c' === $type ) {
				self::handle_single_callback( $cb_id, $chat_id, $message_id, $orig_text, $target_id, 'a' === $type ? 'approve' : 'cancel', $bound_user_id );
			} else {
				self::answer_callback_query( $cb_id, __( 'Unrecognized action type.', 'align-agent' ), false );
			}
		} finally {
			if ( class_exists( 'Align_Gateway_Telegram_Lock' ) ) {
				Align_Gateway_Telegram_Lock::release();
			}
		}
	}

	/**
	 * Process a batch callback decision ('A|' or 'C|').
	 */
	private static function handle_batch_callback( string $cb_id, string $chat_id, int $message_id, string $orig_text, string $batch_id, string $decision, int $bound_user_id ): void {
		$pending_ids = class_exists( 'Align_Agent_Pending_Store' ) ? Align_Agent_Pending_Store::get_batch( $batch_id ) : array();
		if ( empty( $pending_ids ) ) {
			self::answer_callback_query( $cb_id, __( 'Batch actions not found or already processed.', 'align-agent' ), true );
			if ( $message_id > 0 ) {
				$notice = "<s>" . esc_html( $orig_text ) . "</s>\n\n⚠️ <i>" . __( 'Batch actions not found or already processed.', 'align-agent' ) . "</i>";
				self::edit_message_text( $chat_id, $message_id, $notice );
			}
			return;
		}

		if ( class_exists( 'Align_Agent_Pending_Store' ) ) {
			Align_Agent_Pending_Store::delete_batch( $batch_id );
		}

		$last_result = array();
		foreach ( $pending_ids as $pid ) {
			$last_result = Align_Agent_Approval_Service::decide( $bound_user_id, $pid, $decision );
		}

		if ( 'approve' === $decision ) {
			self::answer_callback_query( $cb_id, __( 'Approved all actions!', 'align-agent' ) );
			$updated_card = esc_html( $orig_text ) . "\n\n✅ <b>ALL APPROVED & EXECUTED</b> by administrator.";
		} else {
			self::answer_callback_query( $cb_id, __( 'Cancelled all actions.', 'align-agent' ) );
			$updated_card = esc_html( $orig_text ) . "\n\n❌ <b>ALL CANCELLED</b> by administrator.";
		}

		if ( $message_id > 0 ) {
			self::edit_message_text( $chat_id, $message_id, $updated_card );
		}

		self::resume_runner( $chat_id, $bound_user_id, $last_result );
	}

	/**
	 * Process a single item callback decision ('a|' or 'c|').
	 */
	private static function handle_single_callback( string $cb_id, string $chat_id, int $message_id, string $orig_text, string $pending_id, string $decision, int $bound_user_id ): void {
		$result = Align_Agent_Approval_Service::decide( $bound_user_id, $pending_id, $decision );

		if ( empty( $result['success'] ) ) {
			$msg = $result['message'] ?? __( 'Action not found or expired.', 'align-agent' );
			self::answer_callback_query( $cb_id, $msg, true );
			if ( $message_id > 0 ) {
				$expired_notice = "<s>" . esc_html( $orig_text ) . "</s>\n\n⚠️ <i>" . esc_html( $msg ) . "</i>";
				self::edit_message_text( $chat_id, $message_id, $expired_notice );
			}
			return;
		}

		$tool_name = $result['tool_name'] ?? 'operation';
		if ( 'approve' === $decision ) {
			self::answer_callback_query( $cb_id, sprintf( __( 'Approved "%s"!', 'align-agent' ), $tool_name ) );
			$updated_card = esc_html( $orig_text ) . "\n\n✅ <b>APPROVED & EXECUTED</b> by administrator.";
		} else {
			self::answer_callback_query( $cb_id, sprintf( __( 'Cancelled "%s".', 'align-agent' ), $tool_name ) );
			$updated_card = esc_html( $orig_text ) . "\n\n❌ <b>CANCELLED</b> by administrator.";
		}

		if ( $message_id > 0 ) {
			self::edit_message_text( $chat_id, $message_id, $updated_card );
		}

		self::resume_runner( $chat_id, $bound_user_id, $result );
	}

	/**
	 * Resume the multi-turn agent loop after an approval or cancellation.
	 * Only continues LLM turns when the pending queue is fully resolved.
	 * Never re-sends pending cards for items that are already waiting.
	 *
	 * @param string $chat_id       Telegram chat ID.
	 * @param int    $bound_user_id Bound WordPress Administrator user ID.
	 * @param array  $result        Approval decision result array.
	 */
	public static function resume_runner( string $chat_id, int $bound_user_id, array $result ): void {
		Align_Agent_Conversation::set_channel( 'telegram' );

		// If queue still has unresolved pending writes, do NOT re-send cards (prevents duplicate cards).
		$pending_list = class_exists( 'Align_Agent_Pending_Store' )
			? Align_Agent_Pending_Store::list_for( $bound_user_id, 'telegram' )
			: ( Align_Agent_Conversation::get_state( $bound_user_id )['pending_writes'] ?? array() );
		if ( ! empty( $pending_list ) ) {
			return;
		}

		// Run continuation turn to let LLM formulate final response.
		Align_Gateway_Telegram_Runner::send_chat_action( 'typing', $chat_id );
		$step_result = Align_Agent_Loop::run_step( $bound_user_id, '', 1 );
		if ( class_exists( 'Align_Gateway_Telegram_Lock' ) ) {
			Align_Gateway_Telegram_Lock::refresh( 900 );
		}
		$turn_count = 1;

		while ( 'continue' === $step_result['status'] && $turn_count < Align_Agent_Loop::MAX_CONTINUE_TURNS ) {
			$turn_count++;
			Align_Gateway_Telegram_Runner::send_chat_action( 'typing', $chat_id );
			$step_result = Align_Agent_Loop::run_step( $bound_user_id, '', $turn_count );
			if ( class_exists( 'Align_Gateway_Telegram_Lock' ) ) {
				Align_Gateway_Telegram_Lock::refresh( 900 );
			}
		}

		if ( class_exists( 'Align_Gateway_Telegram_Continue' ) ) {
			Align_Gateway_Telegram_Continue::send_run_report( $chat_id );
		}

		if ( 'needs_approval' === $step_result['status'] ) {
			self::send_pending_cards( $chat_id, $bound_user_id, $step_result['pending_approvals'] ?? array() );
		} elseif ( ! empty( $step_result['reply'] ) ) {
			Align_Gateway_Telegram_Runner::send_formatted_reply( $step_result['reply'], $chat_id );
		}

		if ( ! empty( $step_result['limit_reached'] ) && class_exists( 'Align_Gateway_Telegram_Continue' ) ) {
			Align_Gateway_Telegram_Continue::send_button( $chat_id, (string) ( $step_result['conversation_id'] ?? '' ) );
		}
	}
}
