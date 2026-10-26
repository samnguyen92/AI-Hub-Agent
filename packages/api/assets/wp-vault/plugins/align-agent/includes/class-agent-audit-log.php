<?php
/**
 * Align Agent - Audit Log Manager
 *
 * Records write operations executed via tools or user approvals,
 * captures pre-write state snapshots, and maintains the last 100 entries.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Audit_Log {

	/**
	 * WordPress option key storing the audit log array.
	 */
	const OPTION_KEY = 'align_agent_audit_logs';

	/**
	 * Maximum number of audit log entries to retain.
	 */
	const MAX_ENTRIES = 100;

	/**
	 * Initialize audit log hooks.
	 */
	public static function init(): void {
		// Ready for future listeners or cleanup hooks.
	}

	/**
	 * Log a write action or mutation performed by the agent.
	 *
	 * @param int        $user_id     WordPress user ID who performed or approved the action.
	 * @param string     $tool        Tool name (e.g. save_page, update_site_identity).
	 * @param int|string $object_id   Target object ID, slug, or option key.
	 * @param string     $action_type Action category (e.g. update, create, delete, restore).
	 * @param array      $details     Additional context or parameters.
	 * @return array{id: string, timestamp: int, iso_date: string, user_id: int, user_login: string, tool: string, object_id: string, action_type: string, details: array}
	 */
	public static function log_action( int $user_id, string $tool, $object_id, string $action_type, array $details = array() ): array {
		$user_login = 'system';
		if ( $user_id > 0 ) {
			$user_data = get_userdata( $user_id );
			if ( $user_data ) {
				$user_login = (string) $user_data->user_login;
			}
		}

		$obj_id_str = is_scalar( $object_id ) ? (string) $object_id : (string) wp_json_encode( $object_id );

		$entry = array(
			'id'          => 'audit_' . wp_generate_uuid4(),
			'timestamp'   => time(),
			'iso_date'    => gmdate( 'c' ),
			'user_id'     => $user_id,
			'user_login'  => $user_login,
			'tool'        => sanitize_key( $tool ),
			'object_id'   => $obj_id_str,
			'action_type' => sanitize_key( $action_type ),
			'details'     => $details,
		);

		$logs = self::get_logs();
		array_unshift( $logs, $entry );

		if ( count( $logs ) > self::MAX_ENTRIES ) {
			$logs = array_slice( $logs, 0, self::MAX_ENTRIES );
		}

		update_option( self::OPTION_KEY, $logs, false );
		error_log( sprintf( '[Align Agent] Audit log recorded | tool: %s | object: %s | action: %s | user: %d', $tool, $obj_id_str, $action_type, $user_id ) );

		return $entry;
	}

	/**
	 * Retrieve stored audit log entries.
	 *
	 * @param int $limit Max entries to return.
	 * @return array<int, array>
	 */
	public static function get_logs( int $limit = self::MAX_ENTRIES ): array {
		$raw = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$limit = max( 1, min( self::MAX_ENTRIES, $limit ) );
		return array_slice( $raw, 0, $limit );
	}

	/**
	 * Clear all stored audit logs.
	 *
	 * @return bool
	 */
	public static function clear_logs(): bool {
		error_log( '[Align Agent] Audit logs cleared.' );
		return delete_option( self::OPTION_KEY );
	}

	/**
	 * Snapshot WordPress options before modification.
	 *
	 * @param array<string> $option_keys List of option names to capture.
	 * @return array<string, mixed>
	 */
	public static function snapshot_options( array $option_keys ): array {
		$snapshot = array();
		foreach ( $option_keys as $key ) {
			$clean_key              = sanitize_key( $key );
			$snapshot[ $clean_key ] = get_option( $clean_key, null );
		}
		return $snapshot;
	}

	/**
	 * Snapshot post metadata before modification.
	 *
	 * @param int           $post_id   WordPress post ID.
	 * @param array<string> $meta_keys Optional list of specific meta keys to capture.
	 * @return array<string, mixed>
	 */
	public static function snapshot_post_meta( int $post_id, array $meta_keys = array() ): array {
		if ( $post_id <= 0 ) {
			return array();
		}

		$all_meta    = get_post_meta( $post_id );
		$snapshot    = array();
		$keys_to_get = ! empty( $meta_keys ) ? $meta_keys : array_keys( $all_meta );

		foreach ( $keys_to_get as $key ) {
			if ( isset( $all_meta[ $key ] ) ) {
				$snapshot[ $key ] = maybe_unserialize( $all_meta[ $key ][0] );
			} else {
				$snapshot[ $key ] = null;
			}
		}

		return $snapshot;
	}

	/**
	 * Snapshot taxonomy term data before modification.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return array|null Term array or null if not found.
	 */
	public static function snapshot_term( int $term_id, string $taxonomy ): ?array {
		if ( $term_id <= 0 || empty( $taxonomy ) ) {
			return null;
		}

		$term = get_term( $term_id, $taxonomy, ARRAY_A );
		if ( is_wp_error( $term ) || ! is_array( $term ) ) {
			return null;
		}

		return $term;
	}

	/**
	 * Snapshot WordPress user data (excluding sensitive fields) before modification.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array|null User snapshot or null if not found.
	 */
	public static function snapshot_user( int $user_id ): ?array {
		if ( $user_id <= 0 ) {
			return null;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return null;
		}

		return array(
			'ID'           => $user->ID,
			'user_login'   => $user->user_login,
			'user_email'   => $user->user_email,
			'display_name' => $user->display_name,
			'first_name'   => $user->first_name,
			'last_name'    => $user->last_name,
			'roles'        => $user->roles,
		);
	}
}
