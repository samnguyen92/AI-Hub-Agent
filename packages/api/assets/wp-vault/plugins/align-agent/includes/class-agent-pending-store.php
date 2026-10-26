<?php
/**
 * Align Agent - Isolated Pending Writes Storage
 *
 * Persists pending write operations into dedicated options outside user_meta
 * conversation blobs to prevent concurrent overwrite and lost updates.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Pending_Store {

	/**
	 * Default time-to-live for pending write actions in seconds (15 minutes).
	 */
	const TTL = 900;

	/**
	 * Option key prefix for individual pending items.
	 */
	const OPTION_PREFIX = 'align_agent_pw_';

	/**
	 * Option key prefix for mutex claim on an item.
	 */
	const CLAIM_PREFIX = 'align_agent_pw_claim_';

	/**
	 * Option key prefix for per-user and per-channel index of pending IDs.
	 */
	const INDEX_PREFIX = 'align_agent_pw_index_';

	/**
	 * Option key prefix for batch approval groupings.
	 */
	const BATCH_PREFIX = 'align_agent_pw_batch_';

	/**
	 * Tracks Telegram message IDs superseded during the most recent supersede_all() invocation.
	 *
	 * @var int[]
	 */
	private static array $last_superseded_message_ids = array();

	/**
	 * Retrieve the index option key for a channel and user.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $channel Conversation channel ('chatbox', 'telegram', etc.).
	 * @return string
	 */
	public static function get_index_key( int $user_id, string $channel ): string {
		$channel = sanitize_key( $channel );
		if ( empty( $channel ) ) {
			$channel = 'chatbox';
		}
		return self::INDEX_PREFIX . $channel . '_' . $user_id;
	}

	/**
	 * Add a new pending write item to isolated storage.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $channel Channel name.
	 * @param array  $item    Pending action payload.
	 * @return string Generated or existing pending ID.
	 */
	public static function add( int $user_id, string $channel, array $item ): string {
		$pending_id = ! empty( $item['pending_id'] ) ? sanitize_text_field( (string) $item['pending_id'] ) : 'pw_' . wp_generate_password( 16, false, false );
		$now        = time();

		$record = array(
			'pending_id'   => $pending_id,
			'user_id'      => $user_id,
			'channel'      => $channel,
			'tool_call_id' => isset( $item['tool_call_id'] ) ? (string) $item['tool_call_id'] : '',
			'tool_name'    => isset( $item['tool_name'] ) ? (string) $item['tool_name'] : '',
			'args'         => isset( $item['args'] ) && is_array( $item['args'] ) ? $item['args'] : array(),
			'summary'      => isset( $item['summary'] ) && is_array( $item['summary'] ) ? $item['summary'] : array(),
			'status'       => 'pending',
			'created_at'   => isset( $item['created_at'] ) ? (int) $item['created_at'] : $now,
			'expires_at'   => isset( $item['expires_at'] ) ? (int) $item['expires_at'] : ( $now + self::TTL ),
		);

		if ( isset( $item['tg_message_id'] ) ) {
			$record['tg_message_id'] = (int) $item['tg_message_id'];
		}

		$option_key = self::OPTION_PREFIX . $pending_id;
		update_option( $option_key, $record, 'no' );

		// Update index.
		$index_key = self::get_index_key( $user_id, $channel );
		$ids       = get_option( $index_key, array() );
		if ( ! is_array( $ids ) ) {
			$ids = array();
		}
		if ( ! in_array( $pending_id, $ids, true ) ) {
			$ids[] = $pending_id;
			update_option( $index_key, array_values( $ids ), 'no' );
		}

		return $pending_id;
	}

	/**
	 * Retrieve a pending write item by its ID.
	 *
	 * @param string $id Pending ID.
	 * @return array|null Item array or null if not found.
	 */
	public static function get( string $id ): ?array {
		$id = sanitize_text_field( $id );
		if ( empty( $id ) ) {
			return null;
		}
		$item = get_option( self::OPTION_PREFIX . $id, null );
		return is_array( $item ) ? $item : null;
	}

	/**
	 * Claim and take a pending write item atomically.
	 *
	 * Uses an atomic add_option mutex to ensure only one concurrent decider succeeds.
	 *
	 * @param string $id Pending ID.
	 * @return array{item: ?array, error: ?string} Result item and error code if any.
	 */
	public static function take( string $id ): array {
		$id = sanitize_text_field( $id );
		if ( empty( $id ) ) {
			return array( 'item' => null, 'error' => 'not_found' );
		}

		// Mutex claim via add_option ('no' autoload).
		$claim_key = self::CLAIM_PREFIX . $id;
		$claimed   = add_option( $claim_key, time(), '', 'no' );
		if ( ! $claimed ) {
			return array( 'item' => null, 'error' => 'already_decided' );
		}

		$item = self::get( $id );
		if ( null === $item ) {
			return array( 'item' => null, 'error' => 'not_found' );
		}

		$status = isset( $item['status'] ) ? (string) $item['status'] : 'pending';
		if ( 'superseded' === $status ) {
			return array( 'item' => null, 'error' => 'superseded' );
		}
		if ( 'decided' === $status ) {
			return array( 'item' => null, 'error' => 'already_decided' );
		}

		$expires_at = isset( $item['expires_at'] ) ? (int) $item['expires_at'] : 0;
		if ( $expires_at > 0 && time() > $expires_at ) {
			return array( 'item' => null, 'error' => 'expired' );
		}

		// Mark item as decided.
		$item['status']     = 'decided';
		$item['decided_at'] = time();
		update_option( self::OPTION_PREFIX . $id, $item, 'no' );

		// Remove from user index.
		$user_id = isset( $item['user_id'] ) ? (int) $item['user_id'] : 0;
		$channel = isset( $item['channel'] ) ? (string) $item['channel'] : 'chatbox';
		if ( $user_id > 0 ) {
			self::remove_from_index( $user_id, $channel, $id );
		}

		return array( 'item' => $item, 'error' => null );
	}

	/**
	 * List active pending write items for a given user and channel.
	 * Automatically purges expired items from index.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $channel Channel name.
	 * @return array<string, array> Associative array of active items keyed by pending_id.
	 */
	public static function list_for( int $user_id, string $channel ): array {
		if ( $user_id <= 0 ) {
			return array();
		}

		$index_key = self::get_index_key( $user_id, $channel );
		$ids       = get_option( $index_key, array() );
		if ( ! is_array( $ids ) || empty( $ids ) ) {
			return array();
		}

		$now          = time();
		$active_items = array();
		$retained_ids = array();
		$dirty        = false;

		foreach ( $ids as $id ) {
			$item = self::get( $id );
			if ( null === $item ) {
				$dirty = true;
				continue;
			}

			$status     = isset( $item['status'] ) ? (string) $item['status'] : 'pending';
			$expires_at = isset( $item['expires_at'] ) ? (int) $item['expires_at'] : 0;

			if ( 'pending' !== $status ) {
				$dirty = true;
				continue;
			}

			if ( $expires_at > 0 && $now > $expires_at ) {
				$dirty = true;
				continue;
			}

			$active_items[ $id ] = $item;
			$retained_ids[]      = $id;
		}

		if ( $dirty ) {
			update_option( $index_key, array_values( $retained_ids ), 'no' );
		}

		return $active_items;
	}

	/**
	 * Mark all pending items for a user and channel as superseded.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $channel Channel name.
	 * @return int[] List of Telegram message IDs associated with superseded actions.
	 */
	public static function supersede_all( int $user_id, string $channel ): array {
		if ( $user_id <= 0 ) {
			return array();
		}

		$index_key = self::get_index_key( $user_id, $channel );
		$ids       = get_option( $index_key, array() );
		if ( ! is_array( $ids ) || empty( $ids ) ) {
			return array();
		}

		$now            = time();
		$tg_message_ids = array();

		foreach ( $ids as $id ) {
			$item = self::get( $id );
			if ( null === $item ) {
				continue;
			}

			$status = isset( $item['status'] ) ? (string) $item['status'] : 'pending';
			if ( 'pending' === $status ) {
				$item['status']        = 'superseded';
				$item['superseded_at'] = $now;
				update_option( self::OPTION_PREFIX . $id, $item, 'no' );

				if ( ! empty( $item['tg_message_id'] ) ) {
					$tg_message_ids[] = (int) $item['tg_message_id'];
				}
			}
		}

		// Clear the index.
		update_option( $index_key, array(), 'no' );

		self::$last_superseded_message_ids = array_values( array_unique( $tg_message_ids ) );
		return self::$last_superseded_message_ids;
	}

	/**
	 * Retrieve message IDs superseded in the most recent supersede_all() call.
	 *
	 * @return int[]
	 */
	public static function get_last_superseded_message_ids(): array {
		return self::$last_superseded_message_ids;
	}

	/**
	 * Save a batch of pending IDs for combined approval.
	 *
	 * @param string   $batch_id    Batch identifier.
	 * @param string[] $pending_ids List of pending item IDs.
	 */
	public static function set_batch( string $batch_id, array $pending_ids ): void {
		$batch_id = sanitize_key( $batch_id );
		if ( empty( $batch_id ) || empty( $pending_ids ) ) {
			return;
		}
		update_option( self::BATCH_PREFIX . $batch_id, array_values( $pending_ids ), 'no' );
	}

	/**
	 * Retrieve pending IDs associated with a batch.
	 *
	 * @param string $batch_id Batch identifier.
	 * @return string[]
	 */
	public static function get_batch( string $batch_id ): array {
		$batch_id = sanitize_key( $batch_id );
		if ( empty( $batch_id ) ) {
			return array();
		}
		$ids = get_option( self::BATCH_PREFIX . $batch_id, array() );
		return is_array( $ids ) ? $ids : array();
	}

	/**
	 * Delete a stored batch mapping.
	 *
	 * @param string $batch_id Batch identifier.
	 */
	public static function delete_batch( string $batch_id ): void {
		$batch_id = sanitize_key( $batch_id );
		if ( ! empty( $batch_id ) ) {
			delete_option( self::BATCH_PREFIX . $batch_id );
		}
	}

	/**
	 * Associate a Telegram message ID with an existing pending write item.
	 *
	 * @param string $id            Pending ID.
	 * @param int    $tg_message_id Telegram message ID.
	 * @return bool
	 */
	public static function set_tg_message_id( string $id, int $tg_message_id ): bool {
		$item = self::get( $id );
		if ( null === $item ) {
			return false;
		}
		$item['tg_message_id'] = $tg_message_id;
		return update_option( self::OPTION_PREFIX . $id, $item, 'no' );
	}

	/**
	 * Remove a specific ID from the user index.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $channel Channel name.
	 * @param string $id      Pending ID to remove.
	 */
	public static function remove_from_index( int $user_id, string $channel, string $id ): void {
		$index_key = self::get_index_key( $user_id, $channel );
		$ids       = get_option( $index_key, array() );
		if ( ! is_array( $ids ) || empty( $ids ) ) {
			return;
		}

		$filtered = array_filter(
			$ids,
			function ( $existing_id ) use ( $id ) {
				return $existing_id !== $id;
			}
		);

		update_option( $index_key, array_values( $filtered ), 'no' );
	}
}
