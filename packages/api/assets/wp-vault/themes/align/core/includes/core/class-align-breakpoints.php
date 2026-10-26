<?php
/**
 * Align Responsive Breakpoints Central Manager
 *
 * Provides a single source of truth for responsive breakpoints across PHP, CSS, and JS.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AlignBreakpoints {

	/**
	 * Get centralized breakpoints configuration.
	 *
	 * @return array Breakpoint width threshold values in pixels.
	 */
	public static function get_breakpoints() {
		$mobile = intval( get_option( 'align_breakpoint_mobile', 589 ) );
		$tablet = intval( get_option( 'align_breakpoint_tablet', 991 ) );

		if ( $mobile <= 0 ) {
			$mobile = 589;
		}
		if ( $tablet <= 0 ) {
			$tablet = 991;
		}

		$defaults = array(
			'mobile' => $mobile,
			'tablet' => $tablet,
		);

		/**
		 * Filter centralized responsive breakpoints.
		 *
		 * @param array $defaults Array with 'mobile' and 'tablet' pixel thresholds.
		 */
		return apply_filters( 'align_breakpoints', $defaults );
	}

	/**
	 * Initialize breakpoint hooks for frontend and admin editor.
	 *
	 * @return void
	 */
	public static function init() {
		// CSS variables and JS config output removed.
	}


}
