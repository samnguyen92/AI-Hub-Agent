<?php
/**
 * Align Agent - REST API Controller for WP Admin Chatbox
 *
 * Registers REST endpoints under 'align-agent/v1' (/chat/step, /chat/approve,
 * /chat/history, /chat/reset) secured exclusively by WordPress session cookie,
 * wp_rest nonce, and manage_options capability.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Chat_Controller {

	/**
	 * Rate limit cap: maximum chat messages per minute per user.
	 */
	const RATE_LIMIT_PER_MIN = 20;

	/**
	 * Initialize chat REST routes.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register the 4 chat endpoints under align-agent/v1.
	 */
	public static function register_routes(): void {
		$namespace = defined( 'ALIGN_AGENT_REST_NS' ) ? ALIGN_AGENT_REST_NS : 'align-agent/v1';
		$perm_cb   = array( __CLASS__, 'check_permission' );

		// 1. POST /chat/step - Run 1 turn of the client-driven loop.
		register_rest_route(
			$namespace,
			'/chat/step',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_step' ),
				'permission_callback' => $perm_cb,
				'args'                => array(
					'message'         => array( 'type' => 'string', 'required' => false, 'default' => '' ),
					'conversation_id' => array( 'type' => 'string', 'required' => false, 'default' => '' ),
					'turn'            => array( 'type' => 'integer', 'required' => false, 'default' => 0 ),
				),
			)
		);

		// 2. POST /chat/approve - Approve or reject a pending write tool call, or execute direct rollback.
		register_rest_route(
			$namespace,
			'/chat/approve',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_approve' ),
				'permission_callback' => $perm_cb,
				'args'                => array(
					'pending_id'  => array( 'type' => 'string', 'required' => false, 'default' => '' ),
					'decision'    => array( 'type' => 'string', 'required' => false, 'default' => 'approve' ),
					'action_type' => array( 'type' => 'string', 'required' => false, 'default' => '' ),
					'post_id'     => array( 'type' => 'integer', 'required' => false, 'default' => 0 ),
					'revision_id' => array( 'type' => 'integer', 'required' => false, 'default' => 0 ),
				),
			)
		);

		// 3. GET /chat/history - Retrieve current user's conversation history.
		register_rest_route(
			$namespace,
			'/chat/history',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_history' ),
				'permission_callback' => $perm_cb,
			)
		);

		// 4. POST /chat/reset - Clear current user's conversation history.
		register_rest_route(
			$namespace,
			'/chat/reset',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_reset' ),
				'permission_callback' => $perm_cb,
			)
		);

		// 5. GET|POST|DELETE /chat/session - Inspect or delete/recreate chat session (supports Dual Auth: WP Nonce or Gateway Secret Key).
		register_rest_route(
			$namespace,
			'/chat/session',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'handle_get_session' ),
					'permission_callback' => array( __CLASS__, 'check_session_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE . ', ' . WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'handle_delete_session' ),
					'permission_callback' => array( __CLASS__, 'check_session_permission' ),
				),
			)
		);

		// 6. POST /chat/undo - Undo recent change set (Dual Auth).
		register_rest_route(
			$namespace,
			'/chat/undo',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_undo' ),
				'permission_callback' => array( __CLASS__, 'check_session_permission' ),
				'args'                => array(
					'cs_id' => array( 'type' => 'string', 'required' => false, 'default' => '' ),
					'force' => array( 'type' => 'boolean', 'required' => false, 'default' => false ),
				),
			)
		);
	}

	/**
	 * Verify logged-in administrator session and wp_rest nonce.
	 * Rejects shared secret keys so chatbox endpoints can only be used by authenticated WP admins.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return bool|WP_Error
	 */
	public static function check_permission( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			error_log( '[Align Agent] Unauthorized chat access attempt: user lacks manage_options.' );
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access Align Agent Chat.', 'align-agent' ),
				array( 'status' => 403 )
			);
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) ) {
			$nonce = (string) $request->get_param( '_wpnonce' );
		}

		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			error_log( sprintf( '[Align Agent] Chat nonce verification failed for user #%d.', get_current_user_id() ) );
			return new WP_Error(
				'rest_cookie_invalid_nonce',
				__( 'Security nonce is invalid or expired. Please refresh the page.', 'align-agent' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Dual-auth permission check for /chat/session:
	 * Mode 1: Logged-in administrator with valid X-WP-Nonce (wp_rest).
	 * Mode 2: External integration with valid Gateway Secret Key (Authorization: Bearer or X-Align-Agent-Key).
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return bool|WP_Error
	 */
	public static function check_session_permission( WP_REST_Request $request ) {
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			$nonce = $request->get_header( 'X-WP-Nonce' );
			if ( empty( $nonce ) ) {
				$nonce = (string) $request->get_param( '_wpnonce' );
			}
			if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return true;
			}
		}

		if ( class_exists( 'Align_Gateway_Auth' ) ) {
			$has_key_header = '' !== (string) $request->get_header( 'authorization' ) || '' !== (string) $request->get_header( 'x-align-agent-key' );
			if ( $has_key_header ) {
				return Align_Gateway_Auth::check_permission( $request );
			}
		}

		return self::check_permission( $request );
	}

	/**
	 * Resolve the target administrator user ID for /chat/session requests.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return int WordPress user ID.
	 */
	private static function resolve_session_user_id( WP_REST_Request $request ): int {
		$param_uid = absint( $request->get_param( 'user_id' ) );
		if ( $param_uid > 0 && user_can( $param_uid, 'manage_options' ) ) {
			return $param_uid;
		}

		$current_uid = get_current_user_id();
		if ( $current_uid > 0 ) {
			return $current_uid;
		}

		$admins = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'order'   => 'ASC',
				'fields'  => 'ID',
			)
		);
		return ! empty( $admins ) ? (int) $admins[0] : 1;
	}

	/**
	 * Check per-user rate limit (max 20 user messages per 60-second window).
	 *
	 * @param int $user_id WordPress user ID.
	 * @return bool True if allowed, false if rate limit exceeded.
	 */
	private static function check_rate_limit( int $user_id ): bool {
		$key   = 'align_agent_chat_rl_' . $user_id;
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_LIMIT_PER_MIN ) {
			error_log( sprintf( '[Align Agent] Rate limit exceeded for user #%d (%d msgs/min).', $user_id, $count ) );
			return false;
		}
		set_transient( $key, $count + 1, 60 );
		return true;
	}

	/**
	 * Handle POST /chat/step.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response
	 */
	public static function handle_step( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();
		$message = trim( (string) $request->get_param( 'message' ) );
		$turn    = max( 0, (int) $request->get_param( 'turn' ) );

		if ( '' === $message && 0 === $turn ) {
			return new WP_REST_Response(
				array(
					'status'  => 'error',
					'message' => __( 'Message cannot be empty.', 'align-agent' ),
				),
				400
			);
		}

		if ( '' !== $message && ! self::check_rate_limit( $user_id ) ) {
			return new WP_REST_Response(
				array(
					'status'  => 'error',
					'message' => __( 'Rate limit exceeded (max 20 messages per minute). Please wait a moment.', 'align-agent' ),
				),
				429
			);
		}

		$result = Align_Agent_Loop::run_step( $user_id, $message, $turn );

		if ( class_exists( 'Align_Agent_Run_Report' ) && class_exists( 'Align_Agent_Change_Set' ) ) {
			$cs_id = Align_Agent_Run_Report::get_instance()->get_change_set_id();
			if ( ! empty( $cs_id ) ) {
				$cs = Align_Agent_Change_Set::get( $cs_id );
				if ( $cs && ! empty( $cs['entries'] ) ) {
					$undo_cnt = count( array_filter( $cs['entries'], fn( $e ) => ! empty( $e['undoable'] ) ) );
					$result['change_set'] = array( 'id' => $cs_id, 'count' => count( $cs['entries'] ), 'undoable' => $undo_cnt > 0 );
				}
			}
		}

		$code = ( 'error' === ( $result['status'] ?? '' ) ) ? 400 : 200;
		return new WP_REST_Response( $result, $code );
	}

	/**
	 * Handle POST /chat/approve (Approve/Cancel pending write or direct rollback).
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response
	 */
	public static function handle_approve( WP_REST_Request $request ): WP_REST_Response {
		$user_id     = get_current_user_id();
		$action_type = sanitize_key( (string) $request->get_param( 'action_type' ) );

		// Support direct Rollback button click from a result card.
		if ( 'rollback' === $action_type ) {
			$post_id     = absint( $request->get_param( 'post_id' ) );
			$revision_id = absint( $request->get_param( 'revision_id' ) );
			$result      = Align_Agent_Tool_Registry::execute(
				'restore_revision',
				array(
					'post_id'     => $post_id,
					'revision_id' => $revision_id,
				)
			);
			if ( ! empty( $result['success'] ) && class_exists( 'Align_Agent_Audit_Log' ) ) {
				Align_Agent_Audit_Log::log_action( $user_id, 'restore_revision', $post_id, 'restore', array( 'revision_id' => $revision_id ) );
			}
			$code        = ! empty( $result['success'] ) ? 200 : 400;
			return new WP_REST_Response( $result, $code );
		}

		$pending_id = sanitize_text_field( (string) $request->get_param( 'pending_id' ) );
		$decision   = sanitize_key( (string) $request->get_param( 'decision' ) );

		if ( empty( $pending_id ) ) {
			return new WP_REST_Response(
				array(
					'status'  => 'error',
					'message' => __( 'Missing pending_id parameter.', 'align-agent' ),
				),
				400
			);
		}

		$result = Align_Agent_Approval_Service::decide( $user_id, $pending_id, $decision );
		if ( empty( $result['success'] ) ) {
			$status_code = ( 'not_found' === ( $result['status'] ?? '' ) ) ? 404 : 400;
			return new WP_REST_Response(
				array(
					'status'  => 'error',
					'message' => $result['message'] ?? __( 'An error occurred during approval processing.', 'align-agent' ),
				),
				$status_code
			);
		}

		return new WP_REST_Response(
			array(
				'status'      => $result['status'],
				'decision'    => $result['decision'],
				'tool_name'   => $result['tool_name'] ?? '',
				'tool_result' => $result['tool_result'] ?? array(),
				'ui_card'     => $result['ui_card'] ?? array(),
			),
			200
		);
	}

	/**
	 * Handle GET /chat/history.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_history(): WP_REST_Response {
		$user_id = get_current_user_id();
		$state   = Align_Agent_Conversation::get_state( $user_id );

		$visible_messages = array();
		foreach ( $state['messages'] as $msg ) {
			$role = $msg['role'] ?? '';
			if ( 'user' === $role ) {
				$visible_messages[] = array(
					'role'      => 'user',
					'content'   => (string) ( $msg['content'] ?? '' ),
					'timestamp' => (int) ( $msg['timestamp'] ?? 0 ),
				);
			} elseif ( 'assistant' === $role && '' !== trim( (string) ( $msg['content'] ?? '' ) ) ) {
				$visible_messages[] = array(
					'role'      => 'assistant',
					'content'   => (string) $msg['content'],
					'timestamp' => (int) ( $msg['timestamp'] ?? 0 ),
				);
			} elseif ( 'tool' === $role && ! empty( $msg['ui_card'] ) ) {
				$visible_messages[] = array(
					'role'      => 'result_card',
					'ui_card'   => $msg['ui_card'],
					'timestamp' => (int) ( $msg['timestamp'] ?? 0 ),
				);
			}
		}

		return new WP_REST_Response(
			array(
				'success'           => true,
				'conversation_id'   => $state['conversation_id'],
				'messages'          => $visible_messages,
				'pending_approvals' => array_values( $state['pending_writes'] ),
			),
			200
		);
	}

	/**
	 * Handle POST /chat/reset (backward-compatible session reset).
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_reset(): WP_REST_Response {
		$user_id = get_current_user_id();
		$fresh   = Align_Agent_Conversation::delete_session( $user_id );

		return new WP_REST_Response(
			array(
				'success'                  => true,
				'status'                   => 'cleared',
				'session_id'               => $fresh['conversation_id'],
				'conversation_id'          => $fresh['conversation_id'],
				'previous_conversation_id' => $fresh['previous_conversation_id'] ?? '',
				'messages'                 => array(),
				'message'                  => __( 'Conversation reset.', 'align-agent' ),
			),
			200
		);
	}

	/**
	 * Handle GET /chat/session (inspect active session metadata & messages).
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response
	 */
	public static function handle_get_session( WP_REST_Request $request ): WP_REST_Response {
		$user_id = self::resolve_session_user_id( $request );
		$state   = Align_Agent_Conversation::get_state( $user_id );
		$pending = array_values( $state['pending_writes'] );

		return new WP_REST_Response(
			array(
				'success'           => true,
				'session_id'        => $state['conversation_id'],
				'conversation_id'   => $state['conversation_id'],
				'user_id'           => $user_id,
				'message_count'     => count( $state['messages'] ),
				'pending_count'     => count( $pending ),
				'updated_at'        => $state['updated_at'],
				'messages'          => $state['messages'],
				'pending_approvals' => $pending,
			),
			200
		);
	}

	/**
	 * Handle POST|DELETE /chat/session (delete stored session & start new conversation).
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response
	 */
	public static function handle_delete_session( WP_REST_Request $request ): WP_REST_Response {
		$user_id = self::resolve_session_user_id( $request );
		$fresh   = Align_Agent_Conversation::delete_session( $user_id );

		return new WP_REST_Response(
			array(
				'success'                  => true,
				'status'                   => 'cleared',
				'session_id'               => $fresh['conversation_id'],
				'conversation_id'          => $fresh['conversation_id'],
				'previous_conversation_id' => $fresh['previous_conversation_id'] ?? '',
				'user_id'                  => $user_id,
				'messages'                 => array(),
				'pending_approvals'        => array(),
				'message'                  => __( 'Session deleted and new conversation started.', 'align-agent' ),
			),
			200
		);
	}

	/**
	 * Handle POST /chat/undo (Undo recent change set).
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response
	 */
	public static function handle_undo( WP_REST_Request $request ): WP_REST_Response {
		$user_id = self::resolve_session_user_id( $request );
		$cs_id   = sanitize_key( (string) $request->get_param( 'cs_id' ) );
		$force   = (bool) $request->get_param( 'force' );

		if ( empty( $cs_id ) && class_exists( 'Align_Agent_Change_Set' ) ) {
			foreach ( Align_Agent_Change_Set::list_for( $user_id, 10 ) as $cs ) {
				if ( 'applied' === ( $cs['status'] ?? '' ) ) {
					$cs_id = $cs['id'];
					break;
				}
			}
		}

		if ( empty( $cs_id ) ) {
			return new WP_REST_Response( array( 'success' => false, 'error' => 'not_found', 'message' => __( 'No change set found to undo.', 'align-agent' ) ), 404 );
		}

		$result = Align_Agent_Undo_Service::undo( $user_id, $cs_id, $force );
		$code   = ! empty( $result['success'] ) ? 200 : ( 'conflict' === ( $result['error'] ?? '' ) ? 409 : 400 );
		return new WP_REST_Response( $result, $code );
	}
}
