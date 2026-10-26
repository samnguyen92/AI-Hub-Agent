<?php
/**
 * Align Agent - Change Set Store
 *
 * Manages per-turn change sets, snapshots, mutex locking, and retention GC.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Change_Set {

	const OPTION_PREFIX = 'align_agent_cs_';
	const CLAIM_PREFIX  = 'align_agent_cs_claim_';
	const INDEX_PREFIX  = 'align_agent_cs_index_';
	const MAX_PER_USER  = 30;
	const TTL_SECONDS   = 604800; // 7 days (7 * 86400).
	const MAX_RAW_BYTES = 524288; // 512 KB.
	const MAX_GZ_BYTES  = 2097152; // 2 MB.

	/**
	 * Open a new change set for the current turn.
	 *
	 * @param int    $user_id User ID.
	 * @param string $channel Channel name (telegram, chatbox, etc.).
	 * @return string Generated Change Set ID.
	 */
	public static function open( int $user_id, string $channel = 'telegram' ): string {
		$cs_id = 'cs_' . wp_generate_password( 12, false, false );
		$cs_id = strtolower( $cs_id );

		$record = array(
			'id'            => $cs_id,
			'user_id'       => $user_id,
			'channel'       => sanitize_key( $channel ),
			'created'       => time(),
			'status'        => 'applied',
			'tg_message_id' => 0,
			'entries'       => array(),
		);

		add_option( self::OPTION_PREFIX . $cs_id, $record, '', 'no' );
		self::add_to_index( $user_id, $cs_id );

		error_log( sprintf( '[Align Agent] Change set opened | id: %s | user: %d | channel: %s', $cs_id, $user_id, $channel ) );
		return $cs_id;
	}

	/**
	 * Retrieve a change set by ID.
	 *
	 * @param string $cs_id Change Set ID.
	 * @return array|null
	 */
	public static function get( string $cs_id ): ?array {
		$cs_id = sanitize_key( $cs_id );
		if ( empty( $cs_id ) ) {
			return null;
		}

		$data = get_option( self::OPTION_PREFIX . $cs_id, null );
		if ( ! is_array( $data ) ) {
			return null;
		}

		if ( ! empty( $data['entries'] ) && is_array( $data['entries'] ) ) {
			foreach ( $data['entries'] as &$entry ) {
				if ( ! empty( $entry['before']['compressed'] ) ) {
					if ( ! empty( $entry['before']['post_content_gz'] ) ) {
						$uncompressed = @gzuncompress( base64_decode( (string) $entry['before']['post_content_gz'] ) );
						if ( false !== $uncompressed ) {
							$entry['before']['post_content'] = $uncompressed;
						}
						unset( $entry['before']['post_content_gz'] );
					}
					if ( ! empty( $entry['before']['html_gz'] ) ) {
						$uncompressed_html = @gzuncompress( base64_decode( (string) $entry['before']['html_gz'] ) );
						if ( false !== $uncompressed_html ) {
							$entry['before']['html'] = $uncompressed_html;
						}
						unset( $entry['before']['html_gz'] );
					}
				}
			}
			unset( $entry );
		}

		return $data;
	}

	/**
	 * Append an executed write action entry to a change set.
	 *
	 * @param string $cs_id Change Set ID.
	 * @param array  $entry Entry metadata and before/after snapshots.
	 * @return void
	 */
	public static function append( string $cs_id, array $entry ): void {
		$cs_id = sanitize_key( $cs_id );
		$data  = get_option( self::OPTION_PREFIX . $cs_id, null );
		if ( ! is_array( $data ) ) {
			return;
		}

		// Handle HTML/content compression if snapshot payload is large (REQ-05 / L8 fix).
		$raw_pc   = isset( $entry['before']['post_content'] ) && is_string( $entry['before']['post_content'] ) ? $entry['before']['post_content'] : '';
		$raw_html = isset( $entry['before']['html'] ) && is_string( $entry['before']['html'] ) ? $entry['before']['html'] : '';
		$max_len  = max( strlen( $raw_pc ), strlen( $raw_html ) );

		if ( $max_len > self::MAX_RAW_BYTES ) {
			if ( $max_len > self::MAX_GZ_BYTES ) {
				unset( $entry['before']['post_content'], $entry['before']['html'] );
				$entry['before']['html_dropped'] = true;
				if ( ! empty( $entry['before']['revision_id'] ) && (int) $entry['before']['revision_id'] > 0 ) {
					$entry['undoable'] = true;
				} else {
					$entry['undoable'] = false;
				}
			} else {
				if ( '' !== $raw_pc ) {
					$gz_pc = @gzcompress( $raw_pc, 6 );
					if ( false !== $gz_pc ) {
						$entry['before']['post_content_gz'] = base64_encode( $gz_pc );
						unset( $entry['before']['post_content'] );
						$entry['before']['compressed'] = true;
					}
				}
				if ( '' !== $raw_html ) {
					$gz_html = @gzcompress( $raw_html, 6 );
					if ( false !== $gz_html ) {
						$entry['before']['html_gz'] = base64_encode( $gz_html );
						unset( $entry['before']['html'] );
						$entry['before']['compressed'] = true;
					}
				}
			}
		}

		$data['entries'][] = $entry;
		update_option( self::OPTION_PREFIX . $cs_id, $data, 'no' );
	}

	/**
	 * Update change set status.
	 *
	 * @param string $cs_id  Change Set ID.
	 * @param string $status New status ('applied', 'undone', 'partial', 'expired').
	 * @return void
	 */
	public static function mark( string $cs_id, string $status ): void {
		$data = self::get( $cs_id );
		if ( ! $data ) {
			return;
		}
		$data['status'] = sanitize_key( $status );
		update_option( self::OPTION_PREFIX . $cs_id, $data, 'no' );
	}

	/**
	 * Set Telegram message ID for inline UI updates.
	 *
	 * @param string $cs_id      Change Set ID.
	 * @param int    $message_id Telegram message ID.
	 * @return void
	 */
	public static function set_tg_message_id( string $cs_id, int $message_id ): void {
		$data = self::get( $cs_id );
		if ( ! $data ) {
			return;
		}
		$data['tg_message_id'] = $message_id;
		update_option( self::OPTION_PREFIX . $cs_id, $data, 'no' );
	}

	/**
	 * List change sets for a user.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Max records to return.
	 * @return array<int, array>
	 */
	public static function list_for( int $user_id, int $limit = 10 ): array {
		$index = get_option( self::INDEX_PREFIX . $user_id, array() );
		if ( ! is_array( $index ) || empty( $index ) ) {
			return array();
		}

		$results = array();
		$count   = 0;
		// Iterate newest first.
		foreach ( array_reverse( $index ) as $cs_id ) {
			$cs = self::get( $cs_id );
			if ( $cs ) {
				$results[] = $cs;
				$count++;
				if ( $count >= $limit ) {
					break;
				}
			}
		}

		return $results;
	}

	/**
	 * Mutex claim a change set to prevent concurrent undo execution.
	 *
	 * @param string $cs_id Change Set ID.
	 * @return bool True if lock acquired.
	 */
	public static function claim( string $cs_id ): bool {
		$cs_id = sanitize_key( $cs_id );
		if ( empty( $cs_id ) ) {
			return false;
		}

		$lock_key = self::CLAIM_PREFIX . $cs_id;
		$acquired = add_option( $lock_key, time(), '', 'no' );
		if ( ! $acquired ) {
			// Check for stale lock (older than 60 seconds).
			$locked_at = (int) get_option( $lock_key, 0 );
			if ( $locked_at > 0 && ( time() - $locked_at ) > 60 ) {
				delete_option( $lock_key );
				return add_option( $lock_key, time(), '', 'no' );
			}
			return false;
		}

		return true;
	}

	/**
	 * Release mutex lock on a change set.
	 *
	 * @param string $cs_id Change Set ID.
	 * @return void
	 */
	public static function release( string $cs_id ): void {
		$cs_id = sanitize_key( $cs_id );
		if ( ! empty( $cs_id ) ) {
			delete_option( self::CLAIM_PREFIX . $cs_id );
		}
	}

	/**
	 * Add Change Set ID to per-user index and enforce MAX_PER_USER limit.
	 *
	 * @param int    $user_id User ID.
	 * @param string $cs_id   Change Set ID.
	 * @return void
	 */
	private static function add_to_index( int $user_id, string $cs_id ): void {
		$key   = self::INDEX_PREFIX . $user_id;
		$index = get_option( $key, array() );
		if ( ! is_array( $index ) ) {
			$index = array();
		}

		$index[] = $cs_id;

		// Enforce retention limit: delete oldest if index exceeds MAX_PER_USER.
		while ( count( $index ) > self::MAX_PER_USER ) {
			$oldest_id = array_shift( $index );
			if ( ! empty( $oldest_id ) ) {
				delete_option( self::OPTION_PREFIX . $oldest_id );
				delete_option( self::CLAIM_PREFIX . $oldest_id );
			}
		}

		update_option( $key, array_values( $index ), 'no' );
	}

	/**
	 * Garbage collection cron runner: cleans up change sets older than 7 days.
	 *
	 * @return int Number of change sets deleted.
	 */
	public static function gc(): int {
		global $wpdb;

		$cutoff  = time() - self::TTL_SECONDS;
		$deleted = 0;

		// Find all index options.
		$index_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( self::INDEX_PREFIX ) . '%'
			)
		);

		foreach ( $index_rows as $row ) {
			$index = maybe_unserialize( $row->option_value );
			if ( ! is_array( $index ) ) {
				continue;
			}

			$kept = array();
			foreach ( $index as $cs_id ) {
				$cs = self::get( $cs_id );
				if ( ! $cs || ( isset( $cs['created'] ) && (int) $cs['created'] < $cutoff ) ) {
					delete_option( self::OPTION_PREFIX . $cs_id );
					delete_option( self::CLAIM_PREFIX . $cs_id );
					$deleted++;
				} else {
					$kept[] = $cs_id;
				}
			}

			if ( count( $kept ) !== count( $index ) ) {
				update_option( $row->option_name, array_values( $kept ), 'no' );
			}
		}

		return $deleted;
	}
}
