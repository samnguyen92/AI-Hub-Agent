<?php
/**
 * Align Agent - Option Writer Helper
 *
 * Provides atomic snapshotting, updating, audit logging, and restoration
 * of WordPress options modified by agent tools.
 *
 * @package Align_Agent
 * @subpackage Includes\Tools\Site
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Align_Agent_Option_Writer {

	/**
	 * Empty initializer for compatibility with tools loader loop.
	 *
	 * @return void
	 */
	public static function init(): void {
		// Helper class; no tool definitions registered directly here.
	}

	/**
	 * Snapshot, write, and audit a set of options atomically (best effort).
	 *
	 * @param string               $tool    Tool name for the audit entry.
	 * @param array<string, mixed> $changes Associative array of option keys to new values. Keys must already be validated.
	 * @param array<string>        $mask    Option keys whose values must be replaced by '***' in audit logs.
	 * @return array{success: bool, before: array<string, mixed>, after: array<string, mixed>, changed: array<string>, audit_id: string}
	 */
	public static function write( string $tool, array $changes, array $mask = array() ): array {
		if ( empty( $changes ) ) {
			return array(
				'success'  => true,
				'before'   => array(),
				'after'    => array(),
				'changed'  => array(),
				'audit_id' => '',
			);
		}

		$keys    = array_keys( $changes );
		$before  = Align_Agent_Audit_Log::snapshot_options( $keys );
		$changed = array();

		// Determine which options actually changed value.
		foreach ( $changes as $key => $new_val ) {
			$old_val = $before[ $key ] ?? null;
			// Compare values (loose comparison or serialized comparison for arrays/objects).
			if ( maybe_serialize( $old_val ) !== maybe_serialize( $new_val ) ) {
				$changed[] = $key;
			}
		}

		if ( empty( $changed ) ) {
			return array(
				'success'  => true,
				'before'   => $before,
				'after'    => $before,
				'changed'  => array(),
				'audit_id' => '',
			);
		}

		// Perform option updates for changed keys.
		foreach ( $changed as $key ) {
			update_option( $key, $changes[ $key ] );
		}

		// Read back real persisted values to ensure sanitizer effects are captured.
		$after = Align_Agent_Audit_Log::snapshot_options( $keys );

		// Prepare audit details with masking support.
		$audit_before   = $before;
		$audit_after    = $after;
		$has_mask       = false;

		if ( ! empty( $mask ) ) {
			foreach ( $mask as $mask_key ) {
				if ( array_key_exists( $mask_key, $audit_before ) ) {
					$audit_before[ $mask_key ] = '***';
					$has_mask                 = true;
				}
				if ( array_key_exists( $mask_key, $audit_after ) ) {
					$audit_after[ $mask_key ] = '***';
					$has_mask                = true;
				}
			}
		}

		$audit_details = array(
			'before'         => $audit_before,
			'after'          => $audit_after,
			'changed'        => $changed,
			'masked'         => $mask,
			'undo_supported' => ! $has_mask,
		);

		$current_user_id = get_current_user_id();
		$log_entry       = Align_Agent_Audit_Log::log_action(
			$current_user_id,
			$tool,
			'options',
			'update',
			$audit_details
		);

		error_log( sprintf(
			'[Align Agent] %s: Updated %d options (%s). Audit ID: %s',
			$tool,
			count( $changed ),
			implode( ', ', $changed ),
			$log_entry['id'] ?? 'unknown'
		) );

		return array(
			'success'  => true,
			'before'   => $before,
			'after'    => $after,
			'changed'  => $changed,
			'audit_id' => $log_entry['id'] ?? '',
		);
	}

	/**
	 * Restore an option snapshot produced by write() or snapshot_options().
	 * Null value => delete_option().
	 * Handles theme_mod:custom_logo specifically via set_theme_mod().
	 *
	 * @param array<string, mixed> $snapshot Associative array of option keys to values.
	 * @return array<string, bool> Map of option key to restoration success boolean.
	 */
	public static function restore( array $snapshot ): array {
		$results = array();

		foreach ( $snapshot as $key => $value ) {
			if ( 'theme_mod:custom_logo' === $key ) {
				if ( null === $value || false === $value ) {
					remove_theme_mod( 'custom_logo' );
					$results[ $key ] = true;
				} else {
					set_theme_mod( 'custom_logo', absint( $value ) );
					$results[ $key ] = true;
				}
				continue;
			}

			if ( null === $value ) {
				$results[ $key ] = delete_option( $key );
			} else {
				$results[ $key ] = update_option( $key, $value );
			}
		}

		error_log( sprintf(
			'[Align Agent] Option_Writer: Restored %d options (%s)',
			count( $results ),
			implode( ', ', array_keys( $results ) )
		) );

		return $results;
	}
}
