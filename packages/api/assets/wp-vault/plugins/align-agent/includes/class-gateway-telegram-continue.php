<?php
/**
 * Align Agent Gateway - Telegram Continue Controller
 *
 * Sends the inline '▶️ Continue' button when the automatic turn limit is reached,
 * validates session consistency on callback ('k|{conversation_id}'), and resumes
 * the autonomous agent loop for another batch of turns.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Gateway_Telegram_Continue {

	/**
	 * Send an inline '▶️ Continue' button to the specified Telegram chat.
	 *
	 * @param string $chat_id         Target Telegram chat ID.
	 * @param string $conversation_id Active conversation ID.
	 */
	public static function send_button( string $chat_id, string $conversation_id ): void {
		if ( '' === $chat_id || '' === $conversation_id ) {
			return;
		}

		$token = Align_Gateway_Telegram::get_bot_token();
		if ( '' === $token ) {
			return;
		}

		$reply_markup = array(
			'inline_keyboard' => array(
				array(
					array(
						'text'          => '▶️ Continue',
						'callback_data' => 'k|' . $conversation_id,
					),
				),
			),
		);

		if ( class_exists( 'Align_Gateway_Telegram_Approval' ) ) {
			Align_Gateway_Telegram_Approval::send_inline_card(
				$chat_id,
				__( 'Tap Continue below to run another batch of turns.', 'align-agent' ),
				$reply_markup,
				$token
			);
		}

		error_log( sprintf( '[Align Agent] Telegram continue button sent | chat: %s | conversation: %s', $chat_id, $conversation_id ) );
	}

	/**
	 * Send the active Run Report (with 1-click Undo button if applicable) and reset it.
	 *
	 * @param string $chat_id Target Telegram chat ID.
	 */
	public static function send_run_report( string $chat_id ): void {
		if ( ! class_exists( 'Align_Agent_Run_Report' ) ) {
			return;
		}

		$report_text = Align_Agent_Run_Report::render_plain();
		if ( '' !== $report_text ) {
			$reply_markup = array();
			$cs_id        = Align_Agent_Run_Report::get_instance()->get_change_set_id();

			if ( ! empty( $cs_id ) && class_exists( 'Align_Agent_Change_Set' ) ) {
				$cs = Align_Agent_Change_Set::get( $cs_id );
				if ( $cs && ! empty( $cs['entries'] ) ) {
					$undoable_count = 0;
					foreach ( $cs['entries'] as $e ) {
						if ( ! empty( $e['undoable'] ) ) {
							$undoable_count++;
						}
					}
					if ( $undoable_count > 0 ) {
						$btn_label    = 1 === $undoable_count ? '↩️ Undo' : sprintf( '↩️ Undo all (%d)', $undoable_count );
						$reply_markup = array(
							'inline_keyboard' => array(
								array(
									array(
										'text'          => $btn_label,
										'callback_data' => 'u|' . $cs_id,
									),
								),
							),
						);
					}
				}
			}

			$token = Align_Gateway_Telegram::get_bot_token();
			if ( ! empty( $reply_markup ) && '' !== $token && class_exists( 'Align_Gateway_Telegram_Approval' ) ) {
				$sent_report = Align_Gateway_Telegram_Approval::send_inline_card( $chat_id, esc_html( $report_text ), $reply_markup, $token );
				if ( ! empty( $cs_id ) && ! empty( $sent_report['success'] ) && ! empty( $sent_report['message_id'] ) ) {
					Align_Agent_Change_Set::set_tg_message_id( $cs_id, (int) $sent_report['message_id'] );
				}
			} else {
				Align_Gateway_Telegram::send_message( $report_text, '', '', $chat_id );
			}
		}

		Align_Agent_Run_Report::reset();
	}

	/**
	 * Remove inline keyboard markup from a Telegram message to prevent double-clicks.
	 *
	 * @param string $chat_id    Target Telegram chat ID.
	 * @param int    $message_id Telegram message ID.
	 */
	public static function remove_inline_keyboard( string $chat_id, int $message_id ): void {
		$token = Align_Gateway_Telegram::get_bot_token();
		if ( empty( $token ) || empty( $chat_id ) || $message_id <= 0 ) {
			return;
		}

		wp_remote_post(
			'https://api.telegram.org/bot' . $token . '/editMessageReplyMarkup',
			array(
				'timeout'  => 5,
				'blocking' => false,
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'body'     => wp_json_encode(
					array(
						'chat_id'      => $chat_id,
						'message_id'   => $message_id,
						'reply_markup' => array( 'inline_keyboard' => array() ),
					)
				),
			)
		);
	}

	/**
	 * Handle Telegram callback_query for 'k|{conversation_id}'.
	 *
	 * @param string $cb_id      Callback query ID.
	 * @param string $target_id  Target conversation ID from callback_data.
	 * @param string $chat_id    Telegram chat ID.
	 * @param int    $message_id Telegram message ID.
	 * @param string $orig_text  Original message text.
	 */
	public static function handle_callback( string $cb_id, string $target_id, string $chat_id, int $message_id, string $orig_text = '' ): void {
		error_log( sprintf( '[Align Agent] Telegram continue callback | chat: %s | conversation: %s', $chat_id, $target_id ) );

		if ( class_exists( 'Align_Gateway_Telegram_Approval' ) ) {
			Align_Gateway_Telegram_Approval::answer_callback_query( $cb_id, __( 'Continuing...', 'align-agent' ), false );
		}

		if ( $message_id > 0 ) {
			self::remove_inline_keyboard( $chat_id, $message_id );
		}

		$uid = Align_Gateway_Telegram::get_bound_user_id();
		if ( $uid <= 0 || ! user_can( $uid, 'manage_options' ) ) {
			Align_Gateway_Telegram::send_message( __( 'Bound admin not configured.', 'align-agent' ), '', '', $chat_id );
			return;
		}

		Align_Agent_Conversation::set_channel( 'telegram' );
		$state      = Align_Agent_Conversation::get_state( $uid );
		$current_id = (string) ( $state['conversation_id'] ?? '' );

		if ( '' === $target_id || $current_id !== $target_id ) {
			error_log( sprintf( '[Align Agent] Telegram continue rejected: session mismatch (expected %s, got %s).', $current_id, $target_id ) );
			Align_Gateway_Telegram::send_message( __( 'Session changed. Send a new instruction.', 'align-agent' ), '', '', $chat_id );
			return;
		}

		self::run_continuation( $uid, $chat_id, $target_id );
	}

	/**
	 * Execute the continuation turns under the Telegram runner mutex lock.
	 *
	 * @param int    $uid       Bound WordPress Administrator user ID.
	 * @param string $chat_id   Telegram chat ID.
	 * @param string $target_id Active conversation ID.
	 */
	public static function run_continuation( int $uid, string $chat_id, string $target_id ): void {
		if ( class_exists( 'Align_Gateway_Telegram_Lock' ) && ! Align_Gateway_Telegram_Lock::acquire( 900 ) ) {
			$busy_msg = __( 'Align Agent is currently processing a previous request. Please wait a moment before sending another message.', 'align-agent' );
			Align_Gateway_Telegram::send_message( esc_html( $busy_msg ), '', '', $chat_id );
			return;
		}

		try {
			wp_set_current_user( $uid );
			Align_Agent_Conversation::set_channel( 'telegram' );
			if ( class_exists( 'Align_Agent_Run_Report' ) ) {
				Align_Agent_Run_Report::reset();
			}

			Align_Gateway_Telegram_Runner::send_chat_action( 'typing', $chat_id );
			$step_result = Align_Agent_Loop::run_step( $uid, '', 1 );
			if ( class_exists( 'Align_Gateway_Telegram_Lock' ) ) {
				Align_Gateway_Telegram_Lock::refresh( 900 );
			}
			$turn_count = 1;

			while ( 'continue' === ( $step_result['status'] ?? '' ) && $turn_count < Align_Agent_Loop::MAX_CONTINUE_TURNS ) {
				++$turn_count;
				Align_Gateway_Telegram_Runner::send_chat_action( 'typing', $chat_id );
				$step_result = Align_Agent_Loop::run_step( $uid, '', $turn_count );
				if ( class_exists( 'Align_Gateway_Telegram_Lock' ) ) {
					Align_Gateway_Telegram_Lock::refresh( 900 );
				}
			}

			$all_approvals = $step_result['pending_approvals'] ?? array();
			if ( ! empty( $all_approvals ) && class_exists( 'Align_Gateway_Telegram_Approval' ) ) {
				Align_Gateway_Telegram_Approval::send_pending_cards( $chat_id, $uid, $all_approvals );
			}

			self::send_run_report( $chat_id );

			if ( ! empty( $step_result['reply'] ) ) {
				Align_Gateway_Telegram_Runner::send_formatted_reply( (string) $step_result['reply'], $chat_id );
			} elseif ( 'error' === ( $step_result['status'] ?? '' ) ) {
				$err = ! empty( $step_result['message'] ) ? (string) $step_result['message'] : 'An unexpected error occurred during execution.';
				Align_Gateway_Telegram::send_message( '<b>Error:</b> ' . esc_html( $err ), 'HTML', '', $chat_id );
			}

			if ( ! empty( $step_result['limit_reached'] ) ) {
				self::send_button( $chat_id, (string) ( $step_result['conversation_id'] ?? $target_id ) );
			}
		} catch ( Throwable $e ) {
			if ( class_exists( 'Align_Agent_Run_Report' ) ) {
				Align_Agent_Run_Report::reset();
			}
			error_log( '[Align Agent] Telegram continue unhandled exception: ' . $e->getMessage() );
			Align_Gateway_Telegram::send_message( '<b>Error:</b> ' . esc_html( $e->getMessage() ), 'HTML', '', $chat_id );
		} finally {
			if ( class_exists( 'Align_Gateway_Telegram_Lock' ) ) {
				Align_Gateway_Telegram_Lock::release();
			}
		}
	}
}
