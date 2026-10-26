<?php
/**
 * Align Agent Gateway - Telegram Runner Process Mutex Lock
 *
 * Implements an atomic option-based lock to prevent concurrent Telegram runner
 * instances while supporting heartbeat lease refreshes and automatic stale lock recovery.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Gateway_Telegram_Lock {

	/**
	 * Option key for runner lock.
	 */
	const OPTION_KEY = 'align_agent_tg_lock';

	/**
	 * Default lock lease duration in seconds (15 minutes).
	 */
	const DEFAULT_TTL = 900;

	/**
	 * Tracks whether the current PHP execution context successfully acquired this lock.
	 *
	 * @var bool
	 */
	private static bool $has_lock = false;

	/**
	 * Check if current execution context holds the active lock.
	 *
	 * @return bool
	 */
	public static function is_held_by_current_process(): bool {
		return self::$has_lock;
	}

	/**
	 * Attempt to acquire the runner lock.
	 *
	 * Uses atomic add_option. If the lock option already exists but its expiration timestamp
	 * is in the past, clears the stale lock and re-attempts acquisition.
	 *
	 * @param int $ttl Lock lifetime in seconds. Defaults to 900.
	 * @return bool True if lock was acquired, false if busy.
	 */
	public static function acquire( int $ttl = self::DEFAULT_TTL ): bool {
		if ( self::$has_lock ) {
			return self::refresh( $ttl );
		}

		if ( $ttl <= 0 ) {
			$ttl = self::DEFAULT_TTL;
		}

		$now     = time();
		$expires = $now + $ttl;

		// 1. Try atomic add_option.
		$acquired = add_option( self::OPTION_KEY, $expires, '', 'no' );
		if ( $acquired ) {
			self::$has_lock = true;
			error_log( sprintf( '[Align Agent] TG lock acquired | expires_in: %ds | expires_at: %d', $ttl, $expires ) );
			return true;
		}

		// 2. Lock option already exists. Check if stale.
		$current_expires = (int) get_option( self::OPTION_KEY, 0 );
		if ( $current_expires > 0 && $current_expires < $now ) {
			delete_option( self::OPTION_KEY );
			$retry_acquired = add_option( self::OPTION_KEY, $expires, '', 'no' );
			if ( $retry_acquired ) {
				self::$has_lock = true;
				error_log( sprintf( '[Align Agent] TG lock stale (was %d, now %d) - cleared and acquired | expires_in: %ds', $current_expires, $now, $ttl ) );
				return true;
			}
		}

		self::$has_lock = false;
		error_log( sprintf( '[Align Agent] TG lock busy | current_expires: %d | now: %d | remaining: %ds', $current_expires, $now, max( 0, $current_expires - $now ) ) );
		return false;
	}

	/**
	 * Refresh lock lease duration for current runner.
	 *
	 * Only updates the lock expiration if the current execution context holds it.
	 *
	 * @param int $ttl Extended lease duration in seconds. Defaults to 900.
	 * @return bool True if refreshed, false if lock is not held or update failed.
	 */
	public static function refresh( int $ttl = self::DEFAULT_TTL ): bool {
		if ( ! self::$has_lock ) {
			error_log( '[Align Agent] TG lock refresh skipped: not held by current process.' );
			return false;
		}

		if ( $ttl <= 0 ) {
			$ttl = self::DEFAULT_TTL;
		}

		$expires = time() + $ttl;
		update_option( self::OPTION_KEY, $expires, 'no' );
		error_log( sprintf( '[Align Agent] TG lock refreshed | extended_by: %ds | new_expires_at: %d', $ttl, $expires ) );
		return true;
	}

	/**
	 * Release the runner lock.
	 */
	public static function release(): void {
		if ( ! self::$has_lock ) {
			return;
		}
		delete_option( self::OPTION_KEY );
		self::$has_lock = false;
		error_log( '[Align Agent] TG lock released.' );
	}
}
