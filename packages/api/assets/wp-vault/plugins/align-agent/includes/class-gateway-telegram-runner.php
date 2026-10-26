<?php
/**
 * Align Agent Gateway - Telegram Autonomous Runner
 *
 * Runs the autonomous multi-turn tool calling loop for incoming Telegram messages
 * under a designated Administrator WordPress user context and a dedicated session channel.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Gateway_Telegram_Runner {

	/**
	 * Lock duration in seconds (15 minutes).
	 */
	const LOCK_TTL = 900;

	/**
	 * Loopback runner REST endpoint route.
	 */
	const RUNNER_ROUTE = '/telegram/run';

	/**
	 * Initialize runner hooks and REST endpoint.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
	}

	/**
	 * Register loopback REST route.
	 */
	public static function register_rest_routes(): void {
		register_rest_route(
			defined( 'ALIGN_AGENT_REST_NS' ) ? ALIGN_AGENT_REST_NS : 'align-agent/v1',
			self::RUNNER_ROUTE,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_loopback_run' ),
				'permission_callback' => array( __CLASS__, 'verify_loopback_permission' ),
				'args'                => array(
					'text'        => array( 'required' => false, 'type' => 'string', 'default' => '' ),
					'chat_id'     => array( 'required' => true, 'type' => 'string' ),
					'sender_name' => array( 'required' => false, 'type' => 'string', 'default' => 'User' ),
					'message'     => array( 'required' => false, 'type' => 'object' ),
				),
			)
		);
	}

	/**
	 * Verify loopback request authenticity using HMAC-SHA256 of the payload.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return bool|WP_Error True if signature is valid.
	 */
	public static function verify_loopback_permission( WP_REST_Request $request ) {
		$secret_key = Align_Gateway_Auth::get_secret_key();
		if ( empty( $secret_key ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Secret key is not configured.', 'align-agent' ), array( 'status' => 403 ) );
		}

		$signature = (string) $request->get_header( 'x-align-agent-signature' );
		if ( empty( $signature ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Missing loopback signature.', 'align-agent' ), array( 'status' => 403 ) );
		}

		$body      = (string) $request->get_body();
		$expected  = hash_hmac( 'sha256', $body, $secret_key );

		if ( ! hash_equals( $expected, $signature ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Invalid loopback signature.', 'align-agent' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * REST handler for loopback runner execution.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response
	 */
	public static function handle_loopback_run( WP_REST_Request $request ): WP_REST_Response {
		$text        = sanitize_textarea_field( (string) $request->get_param( 'text' ) );
		$chat_id     = sanitize_text_field( (string) $request->get_param( 'chat_id' ) );
		$sender_name = sanitize_text_field( (string) $request->get_param( 'sender_name' ) );
		$raw_message = $request->get_param( 'message' );
		$message     = is_array( $raw_message ) ? $raw_message : null;

		$result = self::run( $text, $chat_id, $sender_name, $message );
		return new WP_REST_Response( $result, ! empty( $result['success'] ) ? 200 : 500 );
	}

	/**
	 * Dispatch background execution of runner (via FastCGI shutdown or loopback POST).
	 *
	 * @param string     $text        User message text.
	 * @param string     $chat_id     Telegram chat ID.
	 * @param string     $sender_name Sender display name.
	 * @param array|null $message     Raw Telegram message array.
	 */
	public static function schedule_run( string $text, string $chat_id, string $sender_name = 'User', ?array $message = null ): void {
		// Register a shutdown function. If fastcgi_finish_request exists, client gets 200 immediately.
		add_action(
			'shutdown',
			function () use ( $text, $chat_id, $sender_name, $message ) {
				if ( function_exists( 'ignore_user_abort' ) ) {
					ignore_user_abort( true );
				}
				if ( function_exists( 'set_time_limit' ) ) {
					@set_time_limit( 0 );
				}

				if ( function_exists( 'fastcgi_finish_request' ) ) {
					fastcgi_finish_request();
					self::run( $text, $chat_id, $sender_name, $message );
					return;
				}
				if ( function_exists( 'litespeed_finish_request' ) ) {
					litespeed_finish_request();
					self::run( $text, $chat_id, $sender_name, $message );
					return;
				}

				// Fallback: asynchronous non-blocking loopback HTTP request.
				self::dispatch_loopback( $text, $chat_id, $sender_name, $message );
			}
		);
	}

	/**
	 * Dispatch an asynchronous loopback HTTP request to the REST runner route.
	 *
	 * @param string     $text        User message text.
	 * @param string     $chat_id     Telegram chat ID.
	 * @param string     $sender_name Sender display name.
	 * @param array|null $message     Raw Telegram message array.
	 */
	public static function dispatch_loopback( string $text, string $chat_id, string $sender_name, ?array $message = null ): void {
		$secret_key = Align_Gateway_Auth::get_secret_key();
		if ( empty( $secret_key ) ) {
			error_log( '[Align Agent] Loopback dispatch aborted: secret key is not configured.' );
			return;
		}

		$url     = rest_url( ( defined( 'ALIGN_AGENT_REST_NS' ) ? ALIGN_AGENT_REST_NS : 'align-agent/v1' ) . self::RUNNER_ROUTE );
		$payload = wp_json_encode(
			array(
				'text'        => $text,
				'chat_id'     => $chat_id,
				'sender_name' => $sender_name,
				'message'     => $message,
			)
		);
		$sig     = hash_hmac( 'sha256', (string) $payload, $secret_key );

		wp_remote_post(
			$url,
			array(
				'timeout'   => 0.5,
				'blocking'  => false,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				'headers'   => array( 'Content-Type' => 'application/json', 'X-Align-Agent-Signature' => $sig ),
				'body'      => $payload,
			)
		);
	}

	/**
	 * Send a Telegram chat action (e.g. typing).
	 *
	 * @param string $action  Action type ('typing', etc.).
	 * @param string $chat_id Target chat ID.
	 */
	public static function send_chat_action( string $action = 'typing', string $chat_id = '' ): void {
		$token   = Align_Gateway_Telegram::get_bot_token();
		$chat_id = ! empty( $chat_id ) ? $chat_id : Align_Gateway_Telegram::get_chat_id();
		if ( empty( $token ) || empty( $chat_id ) ) {
			return;
		}

		$endpoint = 'https://api.telegram.org/bot' . $token . '/sendChatAction';
		wp_remote_post(
			$endpoint,
			array(
				'timeout'  => 5,
				'blocking' => false,
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'body'     => wp_json_encode( array( 'chat_id' => $chat_id, 'action' => $action ) ),
			)
		);
	}

	/**
	 * Handle Telegram built-in slash commands (/new, /status, /help).
	 *
	 * @param string $text    Command text.
	 * @param string $chat_id Telegram chat ID.
	 * @param int    $user_id Bound WordPress user ID.
	 * @return bool True if handled as a command, false to continue to LLM runner.
	 */
	public static function handle_command( string $text, string $chat_id, int $user_id ): bool {
		$cmd = strtolower( trim( explode( ' ', $text )[0] ) );
		// Strip bot username suffix if present (e.g., /new@AlignAgentBot).
		$cmd = explode( '@', $cmd )[0];

		if ( '/undo' === $cmd || '/history' === $cmd ) {
			if ( class_exists( 'Align_Gateway_Telegram_Undo' ) ) {
				return Align_Gateway_Telegram_Undo::handle_command( $cmd, $chat_id, $user_id );
			}
			return false;
		}

		if ( '/new' === $cmd || '/reset' === $cmd ) {
			Align_Agent_Conversation::set_channel( 'telegram' );
			Align_Agent_Conversation::delete_session( $user_id );
			if ( class_exists( 'Align_Gateway_Telegram_Media' ) ) {
				Align_Gateway_Telegram_Media::clear_queue( $user_id );
			}
			$reply = "<b>Session Reset</b>\nTelegram conversation session has been cleared. You are starting a fresh conversation.";
			Align_Gateway_Telegram::send_message( $reply, 'HTML', '', $chat_id );
			return true;
		}

		if ( '/status' === $cmd ) {
			Align_Agent_Conversation::set_channel( 'telegram' );
			$state   = Align_Agent_Conversation::get_state( $user_id );
			$pending = class_exists( 'Align_Agent_Pending_Store' )
				? Align_Agent_Pending_Store::list_for( $user_id, 'telegram' )
				: ( (array) ( $state['pending_writes'] ?? array() ) );
			$msgs    = ! empty( $state['messages'] ) ? count( $state['messages'] ) : 0;
			$queued_media_count = class_exists( 'Align_Gateway_Telegram_Media' )
				? count( Align_Gateway_Telegram_Media::get_queue( $user_id ) )
				: 0;

			$user = get_userdata( $user_id );
			$user_name = $user ? $user->display_name : '#' . $user_id;

			$policy_mode = class_exists( 'Align_Agent_Approval_Policy' ) ? Align_Agent_Approval_Policy::get_mode() : 'auto';

			$reply  = "<b>Align Agent Status</b>\n\n";
			$reply .= "• Bound Admin: <b>" . esc_html( $user_name ) . "</b>\n";
			$reply .= "• Policy Mode: <b>" . esc_html( $policy_mode ) . "</b>\n";
			$reply .= "• Messages in session: <b>" . $msgs . "</b>\n";
			$reply .= "• Pending approvals: <b>" . count( $pending ) . "</b>\n";
			$reply .= "• Queued images: <b>" . $queued_media_count . "</b>\n";

			$legacy_clones = get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'posts_per_page' => 10, 'meta_key' => '_align_agent_clone_of' ) );
			if ( ! empty( $legacy_clones ) ) {
				$reply .= "• Legacy v4 clones: <b>" . count( $legacy_clones ) . "</b>\n";
			}

			if ( ! empty( $pending ) ) {
				$reply .= "\n<i>Active pending actions:</i>\n";
				foreach ( $pending as $pid => $p ) {
					$tool = isset( $p['tool_name'] ) ? esc_html( $p['tool_name'] ) : 'unknown';
					if ( 'apply_page_clone' === $tool && ! empty( $p['args']['clone_id'] ) ) {
						$cid     = (int) $p['args']['clone_id'];
						$orig_id = (int) get_post_meta( $cid, '_align_agent_clone_of', true );
						$reply  .= "  - <code>" . esc_html( (string) $pid ) . "</code>: apply_page_clone (Clone #{$cid} -> Live #{$orig_id})\n";
					} else {
						$reply .= "  - <code>" . esc_html( (string) $pid ) . "</code>: " . $tool . "\n";
					}
				}
			}

			Align_Gateway_Telegram::send_message( $reply, 'HTML', '', $chat_id );
			return true;
		}

		if ( '/help' === $cmd || '/start' === $cmd ) {
			$reply  = "<b>Align Agent Gateway — Commands</b>\n\n";
			$reply .= "• <code>/new</code> — Reset Telegram conversation session.\n";
			$reply .= "• <code>/status</code> — View current session and pending approvals.\n";
			$reply .= "• <code>/undo</code> — Revert latest changes.\n";
			$reply .= "• <code>/history</code> — View recent change sets with Undo buttons.\n";
			$reply .= "• <code>/help</code> — Show this commands guide.\n\n";
			$reply .= "<b>Examples:</b>\n";
			$reply .= "• Add /pricing link to menu after FAQ\n";
			$reply .= "• Add Google Tag Manager code to body open\n";
			$reply .= "• Create French translation for About page\n\n";
			$reply .= "You can send any natural language command to inspect or update pages, templates, and content.";
			Align_Gateway_Telegram::send_message( $reply, 'HTML', '', $chat_id );
			return true;
		}

		return false;
	}

	/**
	 * Run the multi-turn agent loop synchronously for the given user input.
	 *
	 * @param string $text        User message text.
	 * @param string $chat_id     Telegram chat ID.
	 * @param string $sender_name Sender display name.
	 * @return array{success: bool, message: string}
	 */
	public static function run( string $text, string $chat_id, string $sender_name = 'User', ?array $raw_message = null ): array {
		if ( ( empty( $text ) && empty( $raw_message ) ) || empty( $chat_id ) ) {
			return array( 'success' => false, 'message' => 'Empty prompt and media, or missing chat ID.' );
		}

		// 1. Verify bound administrator.
		$bound_user_id = Align_Gateway_Telegram::get_bound_user_id();
		if ( $bound_user_id <= 0 || ! user_can( $bound_user_id, 'manage_options' ) ) {
			$err = __( 'No WordPress Administrator is bound to Telegram. Please configure "Bound Administrator" in WP Admin > Settings > Align Agent > Telegram.', 'align-agent' );
			Align_Gateway_Telegram::send_message( "<b>Configuration Error:</b>\n" . esc_html( $err ), 'HTML', '', $chat_id );
			return array( 'success' => false, 'message' => $err );
		}

		// 2. Process incoming photo/document media if present.
		$extracted = ( ! empty( $raw_message ) && class_exists( 'Align_Gateway_Telegram_Media' ) )
			? Align_Gateway_Telegram_Media::extract( $raw_message )
			: null;

		if ( $extracted ) {
			self::send_chat_action( 'upload_photo', $chat_id );
			$ingested = Align_Gateway_Telegram_Media::ingest( $extracted['file_id'], $extracted['filename'], $bound_user_id );

			if ( is_wp_error( $ingested ) ) {
				$err_note = sprintf( __( 'Could not process attached image: %s', 'align-agent' ), $ingested->get_error_message() );
				Align_Gateway_Telegram::send_message( '<b>Upload Error:</b> ' . esc_html( $err_note ), 'HTML', '', $chat_id );
			} else {
				$queued_count = Align_Gateway_Telegram_Media::queue( $bound_user_id, $ingested );

				// If there is NO caption/text instruction accompanying this image, just stage it and return.
				if ( empty( $text ) ) {
					$saved_msg = sprintf(
						"Saved image #%d (%d queued). Send your instruction to use it.",
						$ingested['id'],
						$queued_count
					);
					Align_Gateway_Telegram::send_message( esc_html( $saved_msg ), '', '', $chat_id );
					return array( 'success' => true, 'message' => 'Media queued.' );
				}
			}
		}

		if ( empty( $text ) ) {
			return array( 'success' => true, 'message' => 'No prompt to run.' );
		}

		// 3. Acquire autonomous runner lock.
		if ( ! Align_Gateway_Telegram_Lock::acquire( self::LOCK_TTL ) ) {
			$busy_msg = __( 'Align Agent is currently processing a previous request. Please wait a moment before sending another message.', 'align-agent' );
			Align_Gateway_Telegram::send_message( esc_html( $busy_msg ), '', '', $chat_id );
			return array( 'success' => false, 'message' => 'Runner lock active.' );
		}

		// 4. Switch WP user and set conversation channel.
		wp_set_current_user( $bound_user_id );
		Align_Agent_Conversation::set_channel( 'telegram' );

		try {
			// Check if handled by command.
			if ( self::handle_command( $text, $chat_id, $bound_user_id ) ) {
				return array( 'success' => true, 'message' => 'Command executed.' );
			}

			// Acknowledge received message to user so they know processing has started.
			$ack_text = __( '⏳ I received your request and am processing it...', 'align-agent' );
			Align_Gateway_Telegram::send_message( $ack_text, '', '', $chat_id );

			// Consume any queued attachments and append to user text prompt.
			if ( class_exists( 'Align_Gateway_Telegram_Media' ) ) {
				$media_tags = Align_Gateway_Telegram_Media::consume( $bound_user_id );
				if ( ! empty( $media_tags ) ) {
					$text = trim( $text ) . "\n\n" . $media_tags;
				}
			}

			// 5. Initial turn with user message.
			self::send_chat_action( 'typing', $chat_id );
			$step_result = Align_Agent_Loop::run_step( $bound_user_id, $text, 0 );
			Align_Gateway_Telegram_Lock::refresh( self::LOCK_TTL );

			// Mark superseded approval cards on Telegram if prior pending actions were superseded.
			if ( class_exists( 'Align_Agent_Pending_Store' ) && class_exists( 'Align_Gateway_Telegram_Approval' ) ) {
				$superseded_mids = Align_Agent_Pending_Store::get_last_superseded_message_ids();
				if ( ! empty( $superseded_mids ) ) {
					Align_Gateway_Telegram_Approval::mark_superseded( $superseded_mids );
				}
			}

			$turn_count  = 0;

			while ( 'continue' === $step_result['status'] && $turn_count < Align_Agent_Loop::MAX_CONTINUE_TURNS ) {
				$turn_count++;
				self::send_chat_action( 'typing', $chat_id );
				$step_result = Align_Agent_Loop::run_step( $bound_user_id, '', $turn_count );
				Align_Gateway_Telegram_Lock::refresh( self::LOCK_TTL );
			}

			// 6. Handle pending gated approvals.
			$all_approvals = $step_result['pending_approvals'] ?? array();

			if ( ! empty( $all_approvals ) ) {
				if ( class_exists( 'Align_Gateway_Telegram_Approval' ) ) {
					Align_Gateway_Telegram_Approval::send_pending_cards( $chat_id, $bound_user_id, $all_approvals );
				} else {
					$msg = "<b>Action Requires Approval</b>\nA write operation is queued. Please review and approve in WP Admin.";
					Align_Gateway_Telegram::send_message( $msg, 'HTML', '', $chat_id );
				}
			}

			// 7. Send run report with 1-click Undo button if changes were made.
			if ( class_exists( 'Align_Gateway_Telegram_Continue' ) ) {
				Align_Gateway_Telegram_Continue::send_run_report( $chat_id );
			}

			if ( ! empty( $step_result['reply'] ) ) {
				self::send_formatted_reply( $step_result['reply'], $chat_id );
			} elseif ( 'error' === $step_result['status'] ) {
				$err = ! empty( $step_result['message'] ) ? $step_result['message'] : 'An unexpected error occurred during execution.';
				Align_Gateway_Telegram::send_message( "<b>Error:</b> " . esc_html( $err ), 'HTML', '', $chat_id );
			}

			if ( ! empty( $step_result['limit_reached'] ) && class_exists( 'Align_Gateway_Telegram_Continue' ) ) {
				Align_Gateway_Telegram_Continue::send_button( $chat_id, (string) ( $step_result['conversation_id'] ?? '' ) );
			}

			return array( 'success' => true, 'message' => 'Execution completed.' );
		} catch ( Throwable $e ) {
			if ( class_exists( 'Align_Agent_Run_Report' ) ) {
				Align_Agent_Run_Report::reset();
			}
			error_log( '[Align Agent] Telegram runner unhandled exception: ' . $e->getMessage() );
			Align_Gateway_Telegram::send_message( '<b>Error:</b> ' . esc_html( $e->getMessage() ), 'HTML', '', $chat_id );
			return array( 'success' => false, 'message' => $e->getMessage() );
		} finally {
			Align_Gateway_Telegram_Lock::release();
		}
	}

	/**
	 * Send formatted assistant reply to Telegram chat, using chunker/formatter if available.
	 *
	 * @param string $reply   Raw assistant reply text.
	 * @param string $chat_id Target Telegram chat ID.
	 */
	public static function send_formatted_reply( string $reply, string $chat_id ): void {
		if ( class_exists( 'Align_Gateway_Telegram_Format' ) ) {
			Align_Gateway_Telegram_Format::send_reply( $reply, $chat_id );
			return;
		}

		// Fallback simple truncation.
		if ( mb_strlen( $reply, 'UTF-8' ) > 4000 ) {
			$chunks = str_split( $reply, 4000 );
			foreach ( $chunks as $chunk ) {
				Align_Gateway_Telegram::send_message( esc_html( $chunk ), '', '', $chat_id );
			}
		} else {
			Align_Gateway_Telegram::send_message( esc_html( $reply ), '', '', $chat_id );
		}
	}
}
