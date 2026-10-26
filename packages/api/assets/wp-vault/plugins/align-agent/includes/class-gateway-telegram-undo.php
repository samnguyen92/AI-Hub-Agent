<?php
/**
 * Align Agent - Telegram Undo & History Controller
 *
 * Handles /undo, /history commands, and 'u|' / 'U|' inline keyboard callbacks.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Gateway_Telegram_Undo {

	/**
	 * Handle Telegram slash commands (/undo, /history).
	 *
	 * @param string $cmd     Normalized command slug.
	 * @param string $chat_id Telegram Chat ID.
	 * @param int    $user_id Bound WordPress user ID.
	 * @return bool True if handled.
	 */
	public static function handle_command( string $cmd, string $chat_id, int $user_id ): bool {
		if ( '/undo' === $cmd ) {
			return self::handle_undo_command( $chat_id, $user_id );
		}

		if ( '/history' === $cmd ) {
			return self::handle_history_command( $chat_id, $user_id );
		}

		return false;
	}

	/**
	 * Handle /undo command: reverts the most recent applied change set.
	 *
	 * @param string $chat_id Target Chat ID.
	 * @param int    $user_id Bound user ID.
	 * @return bool
	 */
	private static function handle_undo_command( string $chat_id, int $user_id ): bool {
		$history = Align_Agent_Change_Set::list_for( $user_id, 10 );
		$target  = null;

		foreach ( $history as $cs ) {
			if ( 'applied' === ( $cs['status'] ?? '' ) ) {
				$target = $cs;
				break;
			}
		}

		if ( ! $target ) {
			Align_Gateway_Telegram::send_message(
				"ℹ️ <b>Nothing to undo</b>\nNo recently applied change sets found for this site.",
				'HTML',
				'',
				$chat_id
			);
			return true;
		}

		$cs_id = $target['id'];
		$res   = Align_Agent_Undo_Service::undo( $user_id, $cs_id, false );

		if ( ! empty( $res['success'] ) ) {
			$reply = sprintf( "↩️ <b>Reverted %d changes</b> (Change Set: <code>%s</code>).", (int) ( $res['reverted_count'] ?? 0 ), esc_html( $cs_id ) );
			$markup = '';
			if ( ! empty( $res['redo_cs_id'] ) ) {
				$markup = wp_json_encode( array(
					'inline_keyboard' => array(
						array(
							array( 'text' => '↪️ Redo', 'callback_data' => 'u|' . $res['redo_cs_id'] ),
						),
					),
				) );
			}
			Align_Gateway_Telegram::send_message( $reply, 'HTML', $markup, $chat_id );
			return true;
		}

		if ( 'conflict' === ( $res['error'] ?? '' ) ) {
			$reply  = "⚠️ <b>Conflict Detected</b>\nLive content was modified after this change set.\nDo you want to force overwrite?";
			$markup = wp_json_encode( array(
				'inline_keyboard' => array(
					array(
						array( 'text' => '⚠️ Force Undo', 'callback_data' => 'U|' . $cs_id ),
					),
				),
			) );
			Align_Gateway_Telegram::send_message( $reply, 'HTML', $markup, $chat_id );
			return true;
		}

		$err_msg = $res['message'] ?? ( $res['error'] ?? 'Unknown error' );
		Align_Gateway_Telegram::send_message(
			"❌ <b>Undo failed</b>: " . esc_html( $err_msg ),
			'HTML',
			'',
			$chat_id
		);
		return true;
	}

	/**
	 * Handle /history command: lists 10 recent change sets with individual undo buttons.
	 *
	 * @param string $chat_id Target Chat ID.
	 * @param int    $user_id Bound user ID.
	 * @return bool
	 */
	private static function handle_history_command( string $chat_id, int $user_id ): bool {
		$history = Align_Agent_Change_Set::list_for( $user_id, 10 );
		if ( empty( $history ) ) {
			Align_Gateway_Telegram::send_message( "ℹ️ No recent change history found.", 'HTML', '', $chat_id );
			return true;
		}

		$lines    = array( "<b>Recent Changes History (Max 10)</b>\n" );
		$keyboard = array();

		foreach ( $history as $idx => $cs ) {
			$id      = $cs['id'];
			$time    = ! empty( $cs['created'] ) ? wp_date( 'H:i:s d/m', (int) $cs['created'] ) : 'recent';
			$status  = $cs['status'] ?? 'unknown';
			$entries = $cs['entries'] ?? array();
			$count   = count( $entries );
			$labels  = array();

			foreach ( array_slice( $entries, 0, 2 ) as $e ) {
				$labels[] = $e['label'] ?? ( $e['tool'] ?? 'edit' );
			}
			$label_str = ! empty( $labels ) ? implode( ', ', $labels ) : 'updates';

			$status_icon = ( 'applied' === $status ) ? '🟢' : ( ( 'undone' === $status ) ? '↩️' : '⚪' );
			$lines[]     = sprintf( '%d. %s <code>%s</code> (%s) — %s [%d ops]', $idx + 1, $status_icon, $id, $time, esc_html( $label_str ), $count );

			if ( 'applied' === $status ) {
				$keyboard[] = array(
					array( 'text' => sprintf( '↩️ Undo #%d (%s)', $idx + 1, $id ), 'callback_data' => 'u|' . $id ),
				);
			}
		}

		$markup = ! empty( $keyboard ) ? wp_json_encode( array( 'inline_keyboard' => $keyboard ) ) : '';
		Align_Gateway_Telegram::send_message( implode( "\n", $lines ), 'HTML', $markup, $chat_id );
		return true;
	}

	/**
	 * Handle Telegram inline keyboard callback query for undo actions ('u|', 'U|').
	 *
	 * @param string $cb_id      Callback Query ID.
	 * @param string $type       Action type ('u' for standard, 'U' for force).
	 * @param string $cs_id      Change Set ID.
	 * @param string $chat_id    Chat ID.
	 * @param int    $message_id Telegram Message ID.
	 * @param string $orig_text  Original card text.
	 * @return void
	 */
	public static function handle_callback( string $cb_id, string $type, string $cs_id, string $chat_id, int $message_id, string $orig_text ): void {
		$bound_user_id = Align_Gateway_Telegram::get_bound_user_id();
		$force         = ( 'U' === $type );

		$res = Align_Agent_Undo_Service::undo( $bound_user_id, $cs_id, $force );

		if ( ! empty( $res['success'] ) ) {
			self::answer_cb( $cb_id, __( 'Changes successfully reverted.', 'align-agent' ) );
			$reverted_count = (int) ( $res['reverted_count'] ?? 0 );
			$new_text       = sprintf( "↩️ <b>Reverted %d changes.</b>\nChange Set <code>%s</code> has been undone.", $reverted_count, esc_html( $cs_id ) );

			$new_markup = null;
			if ( ! empty( $res['redo_cs_id'] ) ) {
				$new_markup = array(
					'inline_keyboard' => array(
						array(
							array( 'text' => '↪️ Redo', 'callback_data' => 'u|' . $res['redo_cs_id'] ),
						),
					),
				);
			}
			self::edit_message( $chat_id, $message_id, $new_text, $new_markup );
			return;
		}

		if ( 'conflict' === ( $res['error'] ?? '' ) ) {
			self::answer_cb( $cb_id, __( 'Conflict detected: live content changed.', 'align-agent' ), true );
			$new_text   = sprintf( "%s\n\n⚠️ <b>Conflict Detected</b>\nLive content was modified after this action was recorded.\nTap 'Force Undo' to revert anyway.", $orig_text );
			$new_markup = array(
				'inline_keyboard' => array(
					array(
						array( 'text' => '⚠️ Force Undo', 'callback_data' => 'U|' . $cs_id ),
					),
				),
			);
			self::edit_message( $chat_id, $message_id, $new_text, $new_markup );
			return;
		}

		if ( 'already_undone' === ( $res['error'] ?? '' ) ) {
			self::answer_cb( $cb_id, __( 'This change set has already been undone.', 'align-agent' ), true );
			self::edit_message( $chat_id, $message_id, $orig_text . "\n\n<i>(Already undone)</i>", null );
			return;
		}

		if ( 'undo_in_progress' === ( $res['error'] ?? '' ) ) {
			self::answer_cb( $cb_id, __( 'Undo is already running. Please wait.', 'align-agent' ), true );
			return;
		}

		$msg = $res['message'] ?? ( $res['error'] ?? 'Undo failed' );
		self::answer_cb( $cb_id, $msg, true );
	}

	/**
	 * Answer Telegram callback query.
	 *
	 * @param string $cb_id      Callback ID.
	 * @param string $text       Toast message.
	 * @param bool   $show_alert Whether to show popup alert.
	 */
	private static function answer_cb( string $cb_id, string $text, bool $show_alert = false ): void {
		$token = Align_Gateway_Telegram::get_bot_token();
		if ( empty( $token ) || empty( $cb_id ) ) {
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
						'callback_query_id' => $cb_id,
						'text'              => $text,
						'show_alert'        => $show_alert,
					)
				),
			)
		);
	}

	/**
	 * Edit Telegram message text and keyboard.
	 *
	 * @param string     $chat_id    Chat ID.
	 * @param int        $message_id Message ID.
	 * @param string     $text       Updated text.
	 * @param array|null $reply_markup Inline keyboard structure or null to remove keyboard.
	 */
	private static function edit_message( string $chat_id, int $message_id, string $text, ?array $reply_markup ): void {
		$token = Align_Gateway_Telegram::get_bot_token();
		if ( empty( $token ) || empty( $chat_id ) || $message_id <= 0 ) {
			return;
		}

		$body = array(
			'chat_id'    => $chat_id,
			'message_id' => $message_id,
			'text'       => $text,
			'parse_mode' => 'HTML',
		);
		if ( null !== $reply_markup ) {
			$body['reply_markup'] = $reply_markup;
		}

		wp_remote_post(
			'https://api.telegram.org/bot' . $token . '/editMessageText',
			array(
				'timeout'  => 5,
				'blocking' => false,
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'body'     => wp_json_encode( $body ),
			)
		);
	}
}
