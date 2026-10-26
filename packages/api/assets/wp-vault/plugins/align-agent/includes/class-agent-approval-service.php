<?php
/**
 * Align Agent - Shared Pending Write Approval Service
 *
 * Encapsulates the execution, cancellation, audit logging, and conversation
 * history recording for pending write tool approvals across Chatbox and Telegram gateways.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Approval_Service {

	/**
	 * Approve and execute a pending write action.
	 *
	 * @param int    $user_id    WordPress user ID.
	 * @param string $pending_id Identifier of pending write request.
	 * @return array
	 */
	public static function approve( int $user_id, string $pending_id ): array {
		return self::decide( $user_id, $pending_id, 'approve' );
	}

	/**
	 * Cancel a pending write action.
	 *
	 * @param int    $user_id    WordPress user ID.
	 * @param string $pending_id Identifier of pending write request.
	 * @return array
	 */
	public static function cancel( int $user_id, string $pending_id ): array {
		return self::decide( $user_id, $pending_id, 'cancel' );
	}

	/**
	 * Process an approval or cancellation decision on a queued write action.
	 *
	 * @param int    $user_id    WordPress user ID executing or confirming the action.
	 * @param string $pending_id Identifier of pending write request in conversation state.
	 * @param string $decision   'approve' to execute tool, 'cancel' to reject.
	 * @return array{
	 *     success: bool,
	 *     status: string,
	 *     decision: string,
	 *     tool_name?: string,
	 *     tool_result?: array,
	 *     ui_card?: array,
	 *     has_more_queue?: bool,
	 *     message?: string
	 * }
	 */
	public static function decide( int $user_id, string $pending_id, string $decision = 'approve' ): array {
		if ( $user_id <= 0 ) {
			$user_id = get_current_user_id();
		}
		if ( $user_id <= 0 ) {
			return array(
				'success' => false,
				'status'  => 'error',
				'message' => __( 'Invalid user ID.', 'align-agent' ),
			);
		}

		$pending_id = sanitize_text_field( $pending_id );
		$decision   = 'approve' === strtolower( trim( $decision ) ) ? 'approve' : 'cancel';

		if ( empty( $pending_id ) ) {
			return array(
				'success' => false,
				'status'  => 'error',
				'message' => __( 'Missing pending action ID.', 'align-agent' ),
			);
		}

		// Retrieve and claim item from isolated pending store if available.
		if ( class_exists( 'Align_Agent_Pending_Store' ) ) {
			$taken = Align_Agent_Pending_Store::take( $pending_id );
			if ( empty( $taken['item'] ) ) {
				$error          = ! empty( $taken['error'] ) ? (string) $taken['error'] : 'not_found';
				$error_messages = array(
					'expired'         => __( 'This pending action has expired.', 'align-agent' ),
					'superseded'      => __( 'This pending action was superseded by a newer request.', 'align-agent' ),
					'already_decided' => __( 'This pending action has already been processed.', 'align-agent' ),
					'not_found'       => __( 'Pending write request was not found.', 'align-agent' ),
				);
				$msg = $error_messages[ $error ] ?? __( 'Pending write request was not found.', 'align-agent' );

				return array(
					'success' => false,
					'status'  => $error,
					'message' => $msg,
				);
			}
			$pending = $taken['item'];
		} else {
			$pending = Align_Agent_Conversation::pop_pending_write( $user_id, $pending_id );
			if ( ! $pending ) {
				return array(
					'success' => false,
					'status'  => 'not_found',
					'message' => __( 'Pending write request was not found.', 'align-agent' ),
				);
			}
		}

		$tc_id     = (string) $pending['tool_call_id'];
		$tool_name = (string) $pending['tool_name'];
		$args      = is_array( $pending['args'] ) ? $pending['args'] : array();

		// Handle user cancellation.
		if ( 'approve' !== $decision ) {
			$cancel_payload = array(
				'success' => false,
				'status'  => 'rejected_by_user',
				'message' => sprintf( __( 'User cancelled the "%s" operation.', 'align-agent' ), $tool_name ),
			);
			Align_Agent_Conversation::add_tool_message( $user_id, $tc_id, $tool_name, $cancel_payload );

			$pending_list   = class_exists( 'Align_Agent_Pending_Store' )
				? Align_Agent_Pending_Store::list_for( $user_id, Align_Agent_Conversation::get_channel() )
				: ( Align_Agent_Conversation::get_state( $user_id )['pending_writes'] ?? array() );
			$has_more_queue = ! empty( $pending_list );

			return array(
				'success'        => true,
				'status'         => 'cancelled',
				'decision'       => 'cancel',
				'tool_name'      => $tool_name,
				'tool_result'    => $cancel_payload,
				'has_more_queue' => $has_more_queue,
			);
		}

		// Execute tool via Write Executor so approved writes pass through Backup Gate & Global Vault.
		$channel     = Align_Agent_Conversation::get_channel();
		$exec_result = class_exists( 'Align_Agent_Write_Executor' )
			? Align_Agent_Write_Executor::run( $tool_name, $args, $user_id, $channel )
			: Align_Agent_Tool_Registry::execute( $tool_name, $args );
		if ( empty( $exec_result['success'] ) ) {
			error_log( sprintf( '[Align Agent] Approved write failed | tool: %s | error: %s | message: %s', $tool_name, $exec_result['error'] ?? 'unknown', $exec_result['message'] ?? '' ) );
		} elseif ( ! class_exists( 'Align_Agent_Write_Executor' ) && class_exists( 'Align_Agent_Audit_Log' ) ) {
			$object_id   = $args['post_id'] ?? $exec_result['post_id'] ?? $args['id'] ?? $exec_result['id'] ?? $args['tp_id'] ?? $exec_result['tp_id'] ?? 0;
			$action_type = explode( '_', $tool_name, 2 )[0] ?? 'write';
			Align_Agent_Audit_Log::log_action( $user_id, $tool_name, $object_id, $action_type, array( 'args' => $args ) );
		}

		$ui_card = ! empty( $exec_result['ui_card'] ) && is_array( $exec_result['ui_card'] ) ? $exec_result['ui_card'] : array();
		Align_Agent_Conversation::add_tool_message( $user_id, $tc_id, $tool_name, $exec_result, $ui_card );

		$pending_list   = class_exists( 'Align_Agent_Pending_Store' )
			? Align_Agent_Pending_Store::list_for( $user_id, Align_Agent_Conversation::get_channel() )
			: ( Align_Agent_Conversation::get_state( $user_id )['pending_writes'] ?? array() );
		$has_more_queue = ! empty( $pending_list );

		return array(
			'success'        => true,
			'status'         => $has_more_queue ? 'needs_approval' : 'continue',
			'decision'       => 'approve',
			'tool_name'      => $tool_name,
			'tool_result'    => $exec_result,
			'ui_card'        => $ui_card,
			'has_more_queue' => $has_more_queue,
		);
	}
}
