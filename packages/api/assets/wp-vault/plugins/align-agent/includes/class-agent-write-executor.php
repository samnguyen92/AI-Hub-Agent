<?php
/**
 * Align Agent - Write Executor
 *
 * Wraps tool execution with fail-closed Backup Gate, Global Vault backups,
 * post-write verification, change set tracking, and audit logging.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Write_Executor {

	/**
	 * Run a write tool under Backup Gate, Global Vault, and Change Set tracking.
	 *
	 * @param string $tool    Tool function name.
	 * @param array  $args    Tool arguments.
	 * @param int    $user_id User ID performing the action.
	 * @param string $channel Execution channel ('telegram', 'chatbox', 'mcp').
	 * @return array Tool execution result.
	 */
	public static function run( string $tool, array $args = array(), int $user_id = 0, string $channel = 'telegram' ): array {
		if ( $user_id <= 0 ) {
			$user_id = get_current_user_id();
		} else {
			$current_id = get_current_user_id();
			if ( $current_id !== $user_id ) {
				wp_set_current_user( $user_id );
			}
		}

		// 1. Ensure active Change Set for this turn.
		$report = class_exists( 'Align_Agent_Run_Report' ) ? Align_Agent_Run_Report::get_instance() : null;
		$cs_id  = $report && method_exists( $report, 'get_change_set_id' ) ? $report->get_change_set_id() : '';

		if ( empty( $cs_id ) ) {
			$cs_id = Align_Agent_Change_Set::open( $user_id, $channel );
			if ( $report && method_exists( $report, 'set_change_set_id' ) ) {
				$report->set_change_set_id( $cs_id );
			}
		}

		// 2. Capture pre-execution state.
		$captured = Align_Agent_Undo_Adapters::capture( $tool, $args );
		$adapter  = (string) ( $captured['adapter'] ?? 'none' );
		$obj_type = (string) ( $captured['object']['type'] ?? 'unknown' );
		$obj_id   = absint( $captured['object']['id'] ?? 0 );

		// 3. Fail-Closed Backup Gate (REQ-03).
		if ( in_array( $adapter, array( 'post', 'template', 'option' ), true ) ) {
			$invalid_target = ( 'option' !== $adapter && 'theme_file' !== $obj_type && $obj_id <= 0 );
			if ( empty( $captured['before'] ) || $invalid_target ) {
				$target_label = (string) ( $args['tp_id'] ?? ( $args['post_id'] ?? ( $args['id'] ?? $obj_id ) ) );
				error_log( sprintf( '[Align Agent] Backup gate blocked | tool: %s | target: %s', $tool, $target_label ) );
				return array(
					'success' => false,
					'error'   => 'backup_failed',
					'message' => 'Backup could not be created. The edit was blocked.',
				);
			}
		}

		// 4. Pre-write Global Vault backup for global-scope objects (REQ-06..REQ-11).
		$vault_records = array();
		if ( class_exists( 'Align_Agent_Global_Vault' ) ) {
			$targets = Align_Agent_Global_Vault::resolve_targets( $tool, $args );
			foreach ( $targets as $t ) {
				$incoming_size = strlen( (string) wp_json_encode( $args ) );
				$store_res     = Align_Agent_Global_Vault::store(
					$t['object_key'],
					$t['state'],
					array(
						'tool'           => $tool,
						'fields'         => $t['fields'],
						'parent_id'      => $t['parent_id'],
						'user_id'        => $user_id,
						'channel'        => $channel,
						'reason'         => 'pre_edit',
						'force_size_raw' => $incoming_size,
					)
				);

				if ( empty( $store_res['success'] ) ) {
					$err_code = (string) ( $store_res['error'] ?? 'backup_failed' );
					error_log( sprintf( '[Align Agent] Backup gate blocked (vault) | tool: %s | target: %s | error: %s', $tool, $t['object_key'], $err_code ) );
					return array(
						'success' => false,
						'error'   => $err_code,
						'message' => $store_res['message'] ?? 'Backup could not be created. The edit was blocked.',
					);
				}

				$vault_records[] = array(
					'object_key' => $t['object_key'],
					'backup_id'  => (int) $store_res['backup_id'],
					'fields'     => $t['fields'],
					'parent_id'  => $t['parent_id'],
				);
			}
		}

		// 5. Execute the tool callback.
		$result = Align_Agent_Tool_Registry::execute( $tool, $args );

		// 6. If execution failed, do not record change set entry.
		if ( empty( $result['success'] ) ) {
			if ( class_exists( 'Align_Agent_Run_Report' ) ) {
				Align_Agent_Run_Report::record_event( $user_id, $tool, $args, $result );
			}
			return $result;
		}

		// 7. Post-write Global Vault verification (REQ-14).
		if ( ! empty( $vault_records ) && class_exists( 'Align_Agent_Global_Vault' ) ) {
			$verify = Align_Agent_Global_Vault::verify_after( $vault_records, $args );
			if ( empty( $verify['ok'] ) ) {
				return array(
					'success' => false,
					'error'   => $verify['error'] ?? 'write_verify_failed',
					'message' => $verify['message'] ?? 'Post-write verification failed.',
				);
			}
			$backup_ids           = array_values( array_column( $vault_records, 'backup_id' ) );
			$result['backup_id']  = $backup_ids[0];
			$result['backup_ids'] = $backup_ids;
		}

		// 8. Finalize snapshot with after fingerprint.
		$entry = Align_Agent_Undo_Adapters::capture_after( $tool, $args, $result, $captured );

		// 9. Append to current Change Set.
		Align_Agent_Change_Set::append( $cs_id, $entry );

		// 10. Audit log with change set linkage.
		$final_obj_id = absint( $entry['object']['id'] ?? 0 );
		if ( class_exists( 'Align_Agent_Audit_Log' ) ) {
			Align_Agent_Audit_Log::log_action(
				$user_id,
				$tool,
				$final_obj_id,
				'write',
				array(
					'args'           => Align_Agent_Tool_Registry::redact_args( $args ),
					'change_set'     => $cs_id,
					'undo_supported' => ! empty( $entry['undoable'] ),
					'backup_id'      => $result['backup_id'] ?? 0,
				)
			);
		}

		// 11. Record in Run Report.
		if ( class_exists( 'Align_Agent_Run_Report' ) ) {
			Align_Agent_Run_Report::record_event( $user_id, $tool, $args, $result );
		}

		// 12. Structured diagnostic log.
		error_log(
			sprintf(
				'[Align Agent] Change set | cs: %s | tool: %s | adapter: %s | undoable: %d',
				$cs_id,
				$tool,
				$entry['adapter'],
				! empty( $entry['undoable'] ) ? 1 : 0
			)
		);

		return $result;
	}
}
