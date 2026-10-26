<?php
/**
 * Align Agent - Undo Service
 *
 * Coordinates mutex-locked reversal of change sets with LIFO order, conflict detection, and redo support.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Undo_Service {

	/**
	 * Execute undo for a specific change set.
	 *
	 * @param int    $user_id User executing the undo.
	 * @param string $cs_id   Change Set ID.
	 * @param bool   $force   Whether to force undo despite detected conflicts.
	 * @return array Operation result.
	 */
	public static function undo( int $user_id, string $cs_id, bool $force = false ): array {
		$cs_id = sanitize_key( $cs_id );
		if ( empty( $cs_id ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_id',
				'message' => __( 'Invalid change set ID.', 'align-agent' ),
			);
		}

		// 1. Claim mutex lock.
		if ( ! Align_Agent_Change_Set::claim( $cs_id ) ) {
			return array(
				'success' => false,
				'error'   => 'undo_in_progress',
				'message' => __( 'An undo operation is already in progress for this change set.', 'align-agent' ),
			);
		}

		try {
			$cs = Align_Agent_Change_Set::get( $cs_id );
			if ( ! $cs ) {
				Align_Agent_Change_Set::release( $cs_id );
				return array(
					'success' => false,
					'error'   => 'not_found',
					'message' => __( 'Change set not found or expired.', 'align-agent' ),
				);
			}

			// 2. Check if already undone.
			if ( 'undone' === ( $cs['status'] ?? '' ) ) {
				Align_Agent_Change_Set::release( $cs_id );
				return array(
					'success' => false,
					'error'   => 'already_undone',
					'message' => __( 'This change set has already been undone.', 'align-agent' ),
				);
			}

			$entries = $cs['entries'] ?? array();
			if ( empty( $entries ) ) {
				Align_Agent_Change_Set::release( $cs_id );
				return array(
					'success' => false,
					'error'   => 'empty_change_set',
					'message' => __( 'Change set contains no entries.', 'align-agent' ),
				);
			}

			// 3. Check for newer change sets touching the same objects (LIFO dependency check).
			$newer_cs_id = self::find_newer_conflicting_change_set( $user_id, $cs );
			if ( ! empty( $newer_cs_id ) && ! $force ) {
				Align_Agent_Change_Set::release( $cs_id );
				return array(
					'success'     => false,
					'error'       => 'newer_change_exists',
					'newer_cs_id' => $newer_cs_id,
					'message'     => sprintf(
						__( 'A newer change (%s) modified the same items. Please undo newer changes first or use Force Undo.', 'align-agent' ),
						$newer_cs_id
					),
				);
			}

			// 4. Compare after fingerprint with current live state to detect manual edits.
			$conflicts = self::detect_conflicts( $entries );
			if ( ! empty( $conflicts ) && ! $force ) {
				Align_Agent_Change_Set::release( $cs_id );
				return array(
					'success'   => false,
					'error'     => 'conflict',
					'conflicts' => $conflicts,
					'message'   => __( 'Live content was edited after this change. Tap "Force undo" to overwrite.', 'align-agent' ),
				);
			}

			// 5. Pre-undo snapshot: Capture current state into a new "redo" change set.
			$redo_cs_id = self::create_redo_change_set( $user_id, $cs );

			// 6. Revert entries in reverse chronological (LIFO) order.
			$reversed_entries = array_reverse( $entries );
			$reverted_count   = 0;
			$has_failure      = false;
			$touch_cache      = false;

			foreach ( $reversed_entries as $entry ) {
				if ( empty( $entry['undoable'] ) ) {
					continue;
				}

				// Check editor mode lock if applicable.
				$obj_id = absint( $entry['object']['id'] ?? 0 );
				if ( $obj_id > 0 && class_exists( 'Align_Editor_Mode_Lock' ) ) {
					if ( Align_Editor_Mode_Lock::is_locked_to( $obj_id, 'builder' ) === false ) {
						// Not locked out.
					}
				}

				$revert_res = Align_Agent_Undo_Adapters::revert( $entry );
				if ( ! empty( $revert_res['success'] ) ) {
					$reverted_count++;
					if ( in_array( $entry['adapter'], array( 'post', 'template' ), true ) ) {
						$touch_cache = true;
					}
				} else {
					$has_failure = true;
					error_log(
						sprintf(
							'[Align Agent] Undo entry failed | cs: %s | tool: %s | error: %s',
							$cs_id,
							$entry['tool'] ?? 'unknown',
							$revert_res['error'] ?? 'unknown'
						)
					);
				}
			}

			// 7. Flush internal cache if post/template content changed.
			if ( $touch_cache ) {
				wp_cache_flush();
			}

			// Mark change set final status.
			$new_status = $has_failure ? ( $reverted_count > 0 ? 'partial' : 'failed' ) : 'undone';
			Align_Agent_Change_Set::mark( $cs_id, $new_status );

			// 8. Audit log.
			if ( class_exists( 'Align_Agent_Audit_Log' ) ) {
				Align_Agent_Audit_Log::log_action(
					$user_id,
					'undo_change_set',
					0,
					'undo',
					array(
						'cs_id'          => $cs_id,
						'redo_cs_id'     => $redo_cs_id,
						'reverted_count' => $reverted_count,
						'forced'         => $force,
						'status'         => $new_status,
					)
				);
			}

			Align_Agent_Change_Set::release( $cs_id );

			return array(
				'success'        => $reverted_count > 0 || ! $has_failure,
				'cs_id'          => $cs_id,
				'redo_cs_id'     => $redo_cs_id,
				'reverted_count' => $reverted_count,
				'status'         => $new_status,
				'message'        => sprintf( __( 'Reverted %d changes.', 'align-agent' ), $reverted_count ),
			);
		} catch ( Throwable $e ) {
			Align_Agent_Change_Set::release( $cs_id );
			error_log( sprintf( '[Align Agent] Exception during undo for %s: %s', $cs_id, $e->getMessage() ) );
			return array(
				'success' => false,
				'error'   => 'exception',
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Find any newer change set that touched the same object and has not been undone yet.
	 *
	 * @param int   $user_id Acting user ID.
	 * @param array $target_cs Target change set.
	 * @return string|null Newer change set ID or null if none.
	 */
	private static function find_newer_conflicting_change_set( int $user_id, array $target_cs ): ?string {
		$target_time = (int) ( $target_cs['created'] ?? 0 );
		$target_objs = array();

		foreach ( ( $target_cs['entries'] ?? array() ) as $e ) {
			$obj_id = $e['object']['id'] ?? null;
			$type   = $e['object']['type'] ?? '';
			if ( $obj_id ) {
				$target_objs[] = $type . ':' . $obj_id;
			}
		}

		if ( empty( $target_objs ) ) {
			return null;
		}

		$user_history = Align_Agent_Change_Set::list_for( $user_id, 30 );
		foreach ( $user_history as $other_cs ) {
			if ( $other_cs['id'] === $target_cs['id'] ) {
				continue;
			}
			$other_time = (int) ( $other_cs['created'] ?? 0 );
			if ( $other_time > $target_time && 'undone' !== ( $other_cs['status'] ?? '' ) ) {
				foreach ( ( $other_cs['entries'] ?? array() ) as $other_entry ) {
					$key = ( $other_entry['object']['type'] ?? '' ) . ':' . ( $other_entry['object']['id'] ?? '' );
					if ( in_array( $key, $target_objs, true ) ) {
						return (string) $other_cs['id'];
					}
				}
			}
		}

		return null;
	}

	/**
	 * Detect if live objects were modified after change set application.
	 *
	 * @param array $entries List of change set entries.
	 * @return array List of conflict descriptions.
	 */
	private static function detect_conflicts( array $entries ): array {
		$conflicts = array();

		foreach ( $entries as $entry ) {
			$adapter  = $entry['adapter'] ?? 'none';
			$after_fp = $entry['after_fp'] ?? array();
			$obj_id   = absint( $entry['object']['id'] ?? 0 );

			if ( in_array( $adapter, array( 'post', 'template' ), true ) && $obj_id > 0 ) {
				$post = get_post( $obj_id );
				if ( ! $post ) {
					continue;
				}
				if ( ! empty( $after_fp['post_modified_gmt'] ) && $post->post_modified_gmt !== $after_fp['post_modified_gmt'] ) {
					$conflicts[] = array(
						'object_id' => $obj_id,
						'label'     => $entry['label'] ?? ( 'Post #' . $obj_id ),
						'reason'    => 'post_modified_gmt_mismatch',
					);
				}
			}
		}

		return $conflicts;
	}

	/**
	 * Capture current live state as a redo change set before reverting.
	 *
	 * @param int   $user_id Acting user ID.
	 * @param array $cs      Change set being undone.
	 * @return string ID of newly created redo change set.
	 */
	private static function create_redo_change_set( int $user_id, array $cs ): string {
		$redo_cs_id = Align_Agent_Change_Set::open( $user_id, 'redo' );

		foreach ( ( $cs['entries'] ?? array() ) as $entry ) {
			if ( empty( $entry['undoable'] ) ) {
				continue;
			}
			$redo_entry = Align_Agent_Undo_Adapters::capture( $entry['tool'] ?? '', array( 'id' => $entry['object']['id'] ?? 0, 'post_id' => $entry['object']['id'] ?? 0 ) );
			$redo_entry = Align_Agent_Undo_Adapters::capture_after( $entry['tool'] ?? '', array(), array(), $redo_entry );
			Align_Agent_Change_Set::append( $redo_cs_id, $redo_entry );
		}

		return $redo_cs_id;
	}
}
