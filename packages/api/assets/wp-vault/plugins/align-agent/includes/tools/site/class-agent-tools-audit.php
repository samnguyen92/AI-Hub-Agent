<?php
/**
 * Align Agent Audit & Undo Tools
 *
 * Provides inspection of recent agent write actions and atomic restoration (undo)
 * of modified configuration options.
 *
 * @package Align_Agent
 * @subpackage Includes\Tools\Site
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_Audit {

	/**
	 * Whitelist of tool names that can be safely undone via option restoration.
	 *
	 * @var array<string>
	 */
	private const UNDOABLE = array(
		'update_site_settings',
		'save_code_slot',
		'set_site_protection',
		'update_site_identity',
		'set_homepage',
		'manage_language',
	);

	/**
	 * Register audit tools into Align_Agent_Tool_Registry.
	 *
	 * @return void
	 */
	public static function init(): void {
		Align_Agent_Tool_Registry::register( 'list_audit_logs', array(
			'type'             => 'read',
			'group'            => 'ops',
			'scope'            => 'local',
			'capability'       => 'manage_options',
			'requires_builder' => false,
			'danger'           => false,
			'description'      => 'List recent agent audit log entries, indicating which actions support automated undo.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'limit' => array(
						'type'        => 'integer',
						'description' => 'Maximum entries to retrieve (1..50). Default: 20.',
					),
					'tool'  => array(
						'type'        => 'string',
						'description' => 'Optional tool filter (e.g. update_site_settings).',
					),
				),
			),
			'callback'         => array( __CLASS__, 'tool_list_audit_logs' ),
		) );

		Align_Agent_Tool_Registry::register( 'undo_audit_action', array(
			'type'             => 'write',
			'undo'             => array( 'adapter' => 'none' ),
			'group'            => 'ops',
			'scope'            => 'global',
			'capability'       => 'manage_options',
			'requires_builder' => false,
			'danger'           => true,
			'description'      => 'Undo an earlier configuration action by restoring the recorded options snapshot. Requires user approval.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'audit_id' => array(
						'type'        => 'string',
						'description' => 'Audit log ID to revert (e.g. audit_...).',
					),
				),
				'required'   => array( 'audit_id' ),
			),
			'callback'         => array( __CLASS__, 'tool_undo_audit_action' ),
			'summary_callback' => array( __CLASS__, 'summarize_undo_audit_action' ),
		) );
	}

	/**
	 * Callback for list_audit_logs.
	 */
	public static function tool_list_audit_logs( array $args = array() ): array {
		if ( ! class_exists( 'Align_Agent_Audit_Log' ) ) {
			return array(
				'success' => false,
				'error'   => 'audit_unavailable',
				'message' => 'Align_Agent_Audit_Log is unavailable.',
			);
		}

		$limit      = isset( $args['limit'] ) ? min( 50, max( 1, (int) $args['limit'] ) ) : 20;
		$tool_filt  = isset( $args['tool'] ) ? sanitize_key( (string) $args['tool'] ) : '';
		$all_logs   = Align_Agent_Audit_Log::get_logs();
		$undone_ids = array();

		// Find which audit IDs have already been undone.
		foreach ( $all_logs as $entry ) {
			if ( 'undo_audit_action' === ( $entry['tool'] ?? '' ) ) {
				$undone_ids[] = (string) ( $entry['object_id'] ?? '' );
			}
		}

		$entries = array();
		foreach ( $all_logs as $entry ) {
			if ( '' !== $tool_filt && ( $entry['tool'] ?? '' ) !== $tool_filt ) {
				continue;
			}

			$id          = (string) ( $entry['id'] ?? '' );
			$tool_name   = (string) ( $entry['tool'] ?? '' );
			$details     = is_array( $entry['details'] ?? null ) ? $entry['details'] : array();
			$before_snap = $details['before'] ?? null;
			$undo_supp   = ( $details['undo_supported'] ?? true ) !== false;
			$is_undone   = in_array( $id, $undone_ids, true );

			$can_undo = in_array( $tool_name, self::UNDOABLE, true )
				&& is_array( $before_snap )
				&& ! empty( $before_snap )
				&& $undo_supp
				&& ! $is_undone;

			$clean_details = self::sanitize_details_for_display( $details );

			$entries[] = array(
				'id'          => $id,
				'iso_date'    => (string) ( $entry['iso_date'] ?? '' ),
				'user_login'  => (string) ( $entry['user_login'] ?? '' ),
				'tool'        => $tool_name,
				'object_id'   => (string) ( $entry['object_id'] ?? '' ),
				'action_type' => (string) ( $entry['action_type'] ?? '' ),
				'undoable'    => $can_undo,
				'details'     => $clean_details,
			);

			if ( count( $entries ) >= $limit ) {
				break;
			}
		}

		return array(
			'success' => true,
			'count'   => count( $entries ),
			'entries' => $entries,
			'message' => sprintf( 'Retrieved %d audit log entries.', count( $entries ) ),
		);
	}

	/**
	 * Callback for undo_audit_action.
	 */
	public static function tool_undo_audit_action( array $args ): array {
		$audit_id = sanitize_text_field( (string) ( $args['audit_id'] ?? '' ) );
		if ( empty( $audit_id ) ) {
			return array(
				'success' => false,
				'error'   => 'missing_audit_id',
				'message' => 'audit_id is required.',
			);
		}

		if ( ! class_exists( 'Align_Agent_Audit_Log' ) ) {
			return array(
				'success' => false,
				'error'   => 'audit_unavailable',
				'message' => 'Audit log service is unavailable.',
			);
		}

		$all_logs   = Align_Agent_Audit_Log::get_logs();
		$target_log = null;

		foreach ( $all_logs as $entry ) {
			if ( ( $entry['id'] ?? '' ) === $audit_id ) {
				$target_log = $entry;
				break;
			}
		}

		if ( ! $target_log ) {
			return array(
				'success' => false,
				'error'   => 'audit_not_found',
				'message' => sprintf( 'Audit entry "%s" not found.', $audit_id ),
			);
		}

		// v5: Delegate to Undo_Service if audit entry is associated with a change_set.
		$cs_id = $target_log['details']['change_set'] ?? '';
		if ( ! empty( $cs_id ) && class_exists( 'Align_Agent_Undo_Service' ) ) {
			return Align_Agent_Undo_Service::undo( get_current_user_id(), (string) $cs_id );
		}

		$tool = (string) ( $target_log['tool'] ?? '' );
		if ( ! in_array( $tool, self::UNDOABLE, true ) ) {
			return array(
				'success' => false,
				'error'   => 'undo_not_supported',
				'message' => sprintf( 'Tool "%s" cannot be undone automatically. For content/template modifications, use restore_revision or restore_asset_history.', $tool ),
			);
		}

		// Check if already undone.
		foreach ( $all_logs as $entry ) {
			if ( 'undo_audit_action' === ( $entry['tool'] ?? '' ) && (string) ( $entry['object_id'] ?? '' ) === $audit_id ) {
				return array(
					'success' => false,
					'error'   => 'already_undone',
					'message' => sprintf( 'Action "%s" was already undone.', $audit_id ),
				);
			}
		}

		$details = is_array( $target_log['details'] ?? null ) ? $target_log['details'] : array();
		$before  = $details['before'] ?? null;

		if ( empty( $before ) || ! is_array( $before ) ) {
			return array(
				'success' => false,
				'error'   => 'no_snapshot',
				'message' => 'Audit entry has no previous snapshot to restore.',
			);
		}

		if ( isset( $details['undo_supported'] ) && false === $details['undo_supported'] ) {
			return array(
				'success' => false,
				'error'   => 'masked_snapshot',
				'message' => 'This action contained masked credentials and cannot be restored safely.',
			);
		}

		$warnings = array();
		$after    = is_array( $details['after'] ?? null ) ? $details['after'] : array();

		// Check if any options have changed since the action was executed.
		foreach ( $after as $k => $expected_val ) {
			$current_val = get_option( $k, null );
			if ( maybe_serialize( $current_val ) !== maybe_serialize( $expected_val ) ) {
				$warnings[] = sprintf( 'Option "%s" was modified since this action took place.', $k );
			}
		}

		// Perform restoration via Align_Agent_Option_Writer.
		$restored_map = Align_Agent_Option_Writer::restore( $before );

		// If permalink_structure was restored, flush rewrites.
		if ( array_key_exists( 'permalink_structure', $restored_map ) ) {
			flush_rewrite_rules( false );
		}

		$current_user_id = get_current_user_id();
		Align_Agent_Audit_Log::log_action(
			$current_user_id,
			'undo_audit_action',
			$audit_id,
			'restore',
			array(
				'restored_keys'   => array_keys( $restored_map ),
				'original_tool'   => $tool,
				'warnings'        => $warnings,
			)
		);

		return array(
			'success'       => true,
			'audit_id'      => $audit_id,
			'restored_keys' => array_keys( $restored_map ),
			'warnings'      => $warnings,
			'message'       => sprintf( 'Successfully reverted action %s (%s). Restored %d option(s).', $audit_id, $tool, count( $restored_map ) ),
			'ui_card'       => array(
				'title'   => 'Action Undone',
				'message' => sprintf( 'Reverted %s (%d options restored).', $tool, count( $restored_map ) ),
			),
		);
	}

	/**
	 * Provide approval summary for undo_audit_action.
	 */
	public static function summarize_undo_audit_action( array $args ): array {
		$audit_id = (string) ( $args['audit_id'] ?? '' );
		$all_logs = class_exists( 'Align_Agent_Audit_Log' ) ? Align_Agent_Audit_Log::get_logs() : array();
		$entry    = null;

		foreach ( $all_logs as $item ) {
			if ( ( $item['id'] ?? '' ) === $audit_id ) {
				$entry = $item;
				break;
			}
		}

		if ( ! $entry ) {
			return array(
				'title'       => 'Undo Action (Not Found)',
				'description' => sprintf( 'Audit entry "%s" not found.', $audit_id ),
				'meta'        => array(),
			);
		}

		$tool   = (string) ( $entry['tool'] ?? 'action' );
		$before = $entry['details']['before'] ?? array();
		$keys   = is_array( $before ) ? array_keys( $before ) : array();

		return array(
			'title'       => sprintf( 'Undo "%s" (%s)', $tool, $audit_id ),
			'description' => sprintf( 'Restores %d configuration options to their prior state (%s).', count( $keys ), implode( ', ', $keys ) ),
			'meta'        => array(
				'audit_id'      => $audit_id,
				'original_tool' => $tool,
				'keys'          => $keys,
			),
		);
	}

	/**
	 * Sanitize details array for display, truncating long strings and masking secrets.
	 */
	private static function sanitize_details_for_display( array $details ): array {
		$clean = array();
		foreach ( $details as $k => $v ) {
			if ( is_array( $v ) ) {
				$clean[ $k ] = self::sanitize_details_for_display( $v );
			} elseif ( is_string( $k ) && preg_match( '/^(?:password|pass|secret|token|code)$/i', $k ) ) {
				$clean[ $k ] = '***';
			} elseif ( is_string( $v ) ) {
				$clean[ $k ] = ( strlen( $v ) > 300 ) ? substr( $v, 0, 300 ) . '...' : $v;
			} else {
				$clean[ $k ] = $v;
			}
		}
		return $clean;
	}
}
