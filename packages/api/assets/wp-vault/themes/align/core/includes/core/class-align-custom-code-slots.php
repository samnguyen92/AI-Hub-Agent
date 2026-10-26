<?php
/**
 * Align Custom Code Output Slots Engine
 *
 * Handles rendering user custom code in appropriate hooks:
 * - Tracking code in <head> (priority 1) with admin skip support.
 * - Body Open code in wp_body_open (priority 1).
 * - Footer HTML code in wp_footer (priority 100).
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Custom_Code_Slots {

	/**
	 * Initialize hooks for custom code slots.
	 */
	public static function init(): void {
		// Remove old tracking code from Align_Custom_CSS_JS to unify under should_output logic
		remove_action( 'wp_head', array( 'Align_Custom_CSS_JS', 'output_tracking_code' ), 1 );

		// Register unified slot hooks
		add_action( 'wp_head', array( __CLASS__, 'output_tracking_code' ), 1 );
		add_action( 'wp_body_open', array( __CLASS__, 'output_body_open_code' ), 1 );
		add_action( 'wp_footer', array( __CLASS__, 'output_footer_html_code' ), 100 );

		// Disable minification for custom CSS/JS: keep raw content intact
		add_action( 'update_option_align_custom_css', function( $old, $new ) {
			update_option( 'align_custom_css_minified', (string) $new, true );
		}, 20, 2 );
		add_action( 'add_option_align_custom_css', function( $opt, $val ) {
			update_option( 'align_custom_css_minified', (string) $val, true );
		}, 20, 2 );
		add_action( 'update_option_align_custom_js', function( $old, $new ) {
			update_option( 'align_custom_js_minified', (string) $new, true );
		}, 20, 2 );
		add_action( 'add_option_align_custom_js', function( $opt, $val ) {
			update_option( 'align_custom_js_minified', (string) $val, true );
		}, 20, 2 );

		// One-time self-healing sync if minified options exist but differ from raw options
		self::sync_raw_options();

		// Handle raw CSS and JS filters to prevent nested tags and support raw script/style input
		add_filter( 'align_inline_css', array( __CLASS__, 'filter_inline_css' ), 20 );
		add_filter( 'align_inline_footer_js', array( __CLASS__, 'filter_inline_footer_js' ), 20 );
	}

	/**
	 * Filter consolidated inline CSS queue.
	 *
	 * If user input contains <style tags, strips wrapper tags or unsets from queue and outputs directly
	 * to prevent nested <style><style> tags.
	 *
	 * @param array $queue Registered CSS snippets queue.
	 * @return array Filtered CSS queue.
	 */
	public static function filter_inline_css( array $queue ): array {
		if ( ! isset( $queue['custom-css'] ) || ! is_string( $queue['custom-css'] ) ) {
			return $queue;
		}

		$raw_css = $queue['custom-css'];

		// Check if CSS contains <style
		if ( stripos( $raw_css, '<style' ) !== false ) {
			// Strip wrapper/inner <style> and </style> tags so pure CSS is passed to <style id="align-core-inline-css">
			$clean_css = preg_replace( '/<\/?style\b[^>]*>/i', '', $raw_css );

			// If the user pasted other HTML tags (e.g. <link>, <noscript>), output directly right now
			if ( preg_match( '/<[a-z!]/i', $clean_css ) ) {
				unset( $queue['custom-css'] );
				echo "\n" . trim( $raw_css ) . "\n";
			} else {
				$clean_css = trim( $clean_css );
				if ( '' === $clean_css ) {
					unset( $queue['custom-css'] );
				} else {
					$queue['custom-css'] = $clean_css;
				}
			}
		}

		return $queue;
	}

	/**
	 * Filter consolidated inline JS footer queue.
	 *
	 * If user input contains <script tags (e.g. <script>...</script> or <script src="...">),
	 * remove 'custom-js' from the queue and print directly at wp_footer priority 99 to avoid
	 * invalid nested <script><script> tags.
	 *
	 * @param array $queue Registered JS snippets queue.
	 * @return array Filtered JS queue.
	 */
	public static function filter_inline_footer_js( array $queue ): array {
		if ( ! isset( $queue['custom-js'] ) || ! is_string( $queue['custom-js'] ) ) {
			return $queue;
		}

		$raw_js = $queue['custom-js'];

		// If user pasted code containing <script, unqueue from wrapper and output directly right now
		if ( stripos( $raw_js, '<script' ) !== false ) {
			unset( $queue['custom-js'] );
			echo "\n" . trim( $raw_js ) . "\n";
		}

		return $queue;
	}

	/**
	 * Ensure align_custom_css_minified and align_custom_js_minified match raw values.
	 */
	public static function sync_raw_options(): void {
		$raw_css = get_option( 'align_custom_css', null );
		if ( null !== $raw_css ) {
			$min_css = get_option( 'align_custom_css_minified', null );
			if ( $min_css !== $raw_css ) {
				update_option( 'align_custom_css_minified', (string) $raw_css, true );
			}
		}

		$raw_js = get_option( 'align_custom_js', null );
		if ( null !== $raw_js ) {
			$min_js = get_option( 'align_custom_js_minified', null );
			if ( $min_js !== $raw_js ) {
				update_option( 'align_custom_js_minified', (string) $raw_js, true );
			}
		}
	}

	/**
	 * Determine whether custom code slots should be output on frontend.
	 *
	 * @param string $slot_name Identifier for the slot (e.g. 'tracking', 'body_open', 'footer_html').
	 * @return bool True if allowed to output, false otherwise.
	 */
	public static function should_output( string $slot_name = '' ): bool {
		if ( is_admin() || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}

		$skip_admins = (int) get_option( 'align_custom_code_skip_admins', 0 );
		if ( 1 === $skip_admins && current_user_can( 'manage_options' ) ) {
			return false;
		}

		$should = true;

		/**
		 * Filter whether custom code slot should be output.
		 *
		 * @param bool   $should    Whether to output the code.
		 * @param string $slot_name Identifier for the slot.
		 */
		return (bool) apply_filters( 'align_custom_code_should_output', $should, $slot_name );
	}

	/**
	 * Output custom tracking code inside <head> (priority 1).
	 */
	public static function output_tracking_code(): void {
		if ( ! self::should_output( 'tracking' ) ) {
			return;
		}

		$tracking = get_option( 'align_tracking_code', '' );
		if ( is_string( $tracking ) && '' !== trim( $tracking ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Align Core] Tracking code output | Length: ' . strlen( $tracking ) );
			}
			echo "\n" . '<!-- Align Tracking Code -->' . "\n";
			echo trim( $tracking ) . "\n";
			echo '<!-- End Align Tracking Code -->' . "\n";
		}
	}

	/**
	 * Output custom HTML/JS right after <body> open tag (priority 1).
	 */
	public static function output_body_open_code(): void {
		if ( ! self::should_output( 'body_open' ) ) {
			return;
		}

		$body_code = get_option( 'align_body_open_code', '' );
		if ( is_string( $body_code ) && '' !== trim( $body_code ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Align Core] Body open code output | Length: ' . strlen( $body_code ) );
			}
			echo "\n" . '<!-- Align Body Open Code -->' . "\n";
			echo trim( $body_code ) . "\n";
			echo '<!-- End Align Body Open Code -->' . "\n";
		}
	}

	/**
	 * Output custom HTML/JS snippet before </body> closing tag (priority 100).
	 */
	public static function output_footer_html_code(): void {
		if ( ! self::should_output( 'footer_html' ) ) {
			return;
		}

		$footer_code = get_option( 'align_footer_html_code', '' );
		if ( is_string( $footer_code ) && '' !== trim( $footer_code ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Align Core] Footer HTML code output | Length: ' . strlen( $footer_code ) );
			}
			echo "\n" . '<!-- Align Footer HTML Code -->' . "\n";
			echo trim( $footer_code ) . "\n";
			echo '<!-- End Align Footer HTML Code -->' . "\n";
		}
	}
}
