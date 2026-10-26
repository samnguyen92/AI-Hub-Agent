<?php
/**
 * Align Agent - Scope-Based Approval Policy
 *
 * Classifies tool execution into 'block' (hard-gated tools or manual approval mode)
 * or 'auto' (immediate execution with change set undo snapshot).
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Approval_Policy {

	/**
	 * Hard-gated tools requiring explicit confirmation.
	 */
	const HARD_GATE = array(
		'delete_user',
		'send_password_reset',
		'set_site_protection',
		'update_user_role',
		'create_user',
		'restore_backup',
	);

	/**
	 * Classify a tool call into approval decisions: 'block' or 'auto'.
	 *
	 * Unified policy across all channels (Telegram + Chatbox).
	 *
	 * @param string $tool Tool identifier.
	 * @param array  $args Tool arguments.
	 * @return string 'block' | 'auto'
	 */
	public static function classify( string $tool, array $args = array() ): string {
		$tool     = sanitize_key( $tool );
		$mode     = get_option( 'align_agent_approval_mode', 'auto' );
		$decision = ( 'manual' === $mode || self::is_hard_gated( $tool, $args ) ) ? 'block' : 'auto';

		$filtered = apply_filters( 'align_agent_approval_policy', $decision, $tool, $args );
		// Backwards-compatibility: site filters returning 'approve' treated as 'block'.
		if ( 'approve' === $filtered ) {
			return 'block';
		}
		return in_array( $filtered, array( 'block', 'auto' ), true ) ? $filtered : 'auto';
	}

	/**
	 * Check if a tool matches hard gate conditions.
	 *
	 * @param string $tool Tool identifier.
	 * @param array  $args Tool arguments.
	 * @return bool
	 */
	private static function is_hard_gated( string $tool, array $args ): bool {
		switch ( $tool ) {
			case 'delete_user':
			case 'send_password_reset':
			case 'restore_backup':
				return true;
			case 'set_site_protection':
				return ! empty( $args['enabled'] );
			case 'update_user_role':
			case 'create_user':
				return 'administrator' === sanitize_key( (string) ( $args['role'] ?? '' ) );
		}
		return false;
	}

	/**
	 * Get current approval mode.
	 *
	 * @return string 'auto' | 'manual'
	 */
	public static function get_mode(): string {
		return (string) get_option( 'align_agent_approval_mode', 'auto' );
	}
}
