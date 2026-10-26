<?php
/**
 * Align Smooth Scroll Engine (Lenis Integration)
 *
 * Enqueues Lenis smooth-scroll library, injects standard container CSS,
 * and initializes the global window.lenis instance on frontend pages.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Smooth_Scroll {

	/**
	 * Initialize smooth scroll hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 20 );
	}

	/**
	 * Check if smooth scroll is enabled in theme settings.
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public static function is_enabled(): bool {
		return (int) get_option( 'align_enable_lenis_scroll', 0 ) === 1;
	}

	/**
	 * Pre-minified Lenis Container Reset CSS constant for zero runtime CPU overhead.
	 */
	const LENIS_CSS = 'html.lenis,html.lenis body{height:auto}.lenis.lenis-smooth{scroll-behavior:auto !important}.lenis.lenis-smooth [data-lenis-prevent]{overscroll-behavior:contain}.lenis.lenis-stopped{overflow:hidden}.lenis.lenis-scrolling iframe{pointer-events:none}';

	/**
	 * Pre-minified Lenis Initialization JS constant for zero runtime CPU overhead.
	 * Supports hybrid initialization for both immediate/defer and delay JS strategies.
	 */
	const LENIS_INIT_JS = 'function _initLenis(){if("undefined"!=typeof Lenis&&!window.lenis){window.lenis=new Lenis({autoRaf:!0,smoothWheel:!0});return!0}return!1}if(!_initLenis()){document.addEventListener("DOMContentLoaded",_initLenis);window.addEventListener("load",_initLenis);var _lInt=setInterval(function(){_initLenis()&&clearInterval(_lInt)},50);setTimeout(function(){clearInterval(_lInt)},10000);}';

	/**
	 * Enqueue Lenis JS library, standard CSS styles, and initialize window.lenis.
	 *
	 * @return void
	 */
	public static function enqueue_assets(): void {
		if ( is_admin() || is_feed() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( ! self::is_enabled() ) {
			return;
		}

		$lenis_path = get_template_directory() . '/core/assets/plugins/smooth-scroll/lenis.min.js';
		$lenis_uri  = get_template_directory_uri() . '/core/assets/plugins/smooth-scroll/lenis.min.js';

		if ( ! file_exists( $lenis_path ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Align SmoothScroll] Warning: Lenis script file not found at: ' . $lenis_path );
			}
			return;
		}

		$version = filemtime( $lenis_path );

		// 1. Enqueue Lenis Script in Footer (only fallback if Align_Custom_CSS_JS is not present)
		if ( ! class_exists( 'Align_Custom_CSS_JS' ) ) {
			wp_enqueue_script(
				'align-lenis',
				$lenis_uri,
				array(),
				$version ? (string) $version : '1.1.18',
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		}

		// 2. Inject Pre-minified Lenis Init Script into consolidated inline JS queue (0 CPU overhead)
		if ( class_exists( 'Align_Custom_CSS_JS' ) ) {
			Align_Custom_CSS_JS::add_inline_js( 'lenis-init', self::LENIS_INIT_JS, 'footer' );
		} else {
			wp_add_inline_script( 'align-lenis', self::LENIS_INIT_JS, 'after' );
		}

		// 3. Inject Pre-minified Lenis Reset CSS into consolidated inline CSS queue (0 CPU overhead)
		if ( class_exists( 'Align_Custom_CSS_JS' ) ) {
			Align_Custom_CSS_JS::add_inline_css( 'lenis', self::LENIS_CSS );
		} else {
			wp_register_style( 'align-lenis-style', false, array(), $version ? (string) $version : '1.1.18' );
			wp_enqueue_style( 'align-lenis-style' );
			wp_add_inline_style( 'align-lenis-style', self::LENIS_CSS );
		}
	}
}
