<?php
/**
 * Align Script Sanitizer.
 *
 * Cleans up orphaned or leaked inline JavaScript strings (e.g. IntersectionObserver js-reveal)
 * from database posts and filters frontend rendered output.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Script_Sanitizer {

	/**
	 * Regex matching any variation of leaked IntersectionObserver js-reveal script snippet.
	 * Matches raw quotes, smart quotes, HTML entities (&#8220;, &ldquo;, &quot;), and both escaped and unescaped ampersands.
	 */
	const PATTERN_JS = '~(?:<script[^>]*>\s*)?(?:["\'\x{201C}\x{201D}]|&quot;|&#8220;|&#8221;|&ldquo;|&rdquo;|“|”)?IntersectionObserver(?:["\'\x{201C}\x{201D}]|&quot;|&#8220;|&#8221;|&ldquo;|&rdquo;|“|”)?\s*in\s*window\s*(?:&&|&amp;&amp;)\s*!?window\.matchMedia\(.+?\)\.matches\s*(?:&&|&amp;&amp;)\s*document\.documentElement\.classList\.add\(.+?js-reveal.+?\);?(?:\s*<\/script>)?~iu';

	/**
	 * Regex matching orphaned CSS rules leaked outside style tags due to KSES stripping.
	 */
	const PATTERN_CSS = '~(?:<style[^>]*>\s*)?#main\s*(?:>|&gt;)\s*(?:\.block\s*(?:>|&gt;)\s*)?\.scrim__bg\s*\{\s*display\s*:\s*block\s*;?\s*\}(?:\s*<\/style>)?~iu';

	/**
	 * Legacy constant alias for backward compatibility.
	 */
	const PATTERN = self::PATTERN_JS;

	/**
	 * Bootstraps hooks.
	 */
	public static function init() {
		// Run at early priority (before wptexturize) and late priority (after wptexturize/renderers)
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 1 );
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 9999 );
		add_filter( 'render_block', array( __CLASS__, 'filter_block' ), 9999, 2 );

		// Auto-sanitize on post save
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'filter_post_data_before_save' ), 20, 2 );

		// Auto-run one-time DB sweep in admin if not yet performed
		add_action( 'admin_init', array( __CLASS__, 'maybe_auto_cleanup_database' ) );
	}

	/**
	 * Filter post content on frontend.
	 *
	 * @param string $content HTML content.
	 * @return string Sanitized content.
	 */
	public static function filter_content( $content ) {
		if ( empty( $content ) || ! is_string( $content ) ) {
			return $content;
		}

		if ( false !== stripos( $content, 'scrim__bg' ) ) {
			$content = preg_replace( self::PATTERN_CSS, '', $content );
		}

		if ( false !== stripos( $content, 'js-reveal' ) || false !== stripos( $content, 'IntersectionObserver' ) ) {
			$content = preg_replace( self::PATTERN_JS, '', $content );
		}

		return $content;
	}

	/**
	 * Filter rendered block content.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block structure.
	 * @return string Sanitized HTML.
	 */
	public static function filter_block( $block_content, $block ) {
		if ( empty( $block_content ) || ! is_string( $block_content ) ) {
			return $block_content;
		}

		if ( false !== stripos( $block_content, 'scrim__bg' ) ) {
			$block_content = preg_replace( self::PATTERN_CSS, '', $block_content );
		}

		if ( false !== stripos( $block_content, 'js-reveal' ) || false !== stripos( $block_content, 'IntersectionObserver' ) ) {
			$block_content = preg_replace( self::PATTERN_JS, '', $block_content );
		}

		return $block_content;
	}

	/**
	 * Clean data before saving to database.
	 *
	 * @param array $data    Sanitized post data.
	 * @param array $postarr Raw post data.
	 * @return array Modified post data.
	 */
	public static function filter_post_data_before_save( $data, $postarr ) {
		if ( ! empty( $data['post_content'] ) ) {
			if ( false !== stripos( $data['post_content'], 'scrim__bg' ) ) {
				$data['post_content'] = preg_replace( self::PATTERN_CSS, '', $data['post_content'] );
			}
			if ( false !== stripos( $data['post_content'], 'js-reveal' ) || false !== stripos( $data['post_content'], 'IntersectionObserver' ) ) {
				$data['post_content'] = preg_replace( self::PATTERN_JS, '', $data['post_content'] );
			}
		}
		return $data;
	}

	/**
	 * One-time automated database sweep on admin load.
	 */
	public static function maybe_auto_cleanup_database() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$done = get_option( 'align_sanitizer_db_cleaned_v3', false );
		if ( ! $done ) {
			self::run_database_cleanup();
			update_option( 'align_sanitizer_db_cleaned_v3', time() );
		}
	}

	/**
	 * Sweep database to permanently sanitize legacy posts containing leaked script or CSS.
	 *
	 * @return int Number of updated posts.
	 */
	public static function run_database_cleanup() {
		global $wpdb;

		$posts = $wpdb->get_results(
			"SELECT ID, post_content FROM {$wpdb->posts} 
			 WHERE post_content LIKE '%js-reveal%' 
			    OR post_content LIKE '%IntersectionObserver%'
			    OR post_content LIKE '%scrim__bg%'"
		);

		if ( empty( $posts ) ) {
			return 0;
		}

		$updated_count = 0;
		foreach ( $posts as $p ) {
			$clean = $p->post_content;
			if ( false !== stripos( $clean, 'scrim__bg' ) ) {
				$clean = preg_replace( self::PATTERN_CSS, '', $clean );
			}
			if ( false !== stripos( $clean, 'js-reveal' ) || false !== stripos( $clean, 'IntersectionObserver' ) ) {
				$clean = preg_replace( self::PATTERN_JS, '', $clean );
			}

			if ( $clean !== $p->post_content ) {
				$wpdb->update(
					$wpdb->posts,
					array( 'post_content' => $clean ),
					array( 'ID' => $p->ID ),
					array( '%s' ),
					array( '%d' )
				);
				clean_post_cache( $p->ID );
				$updated_count++;
			}
		}

		error_log( "[Align ScriptSanitizer] Database cleanup finished. Sanitized {$updated_count} posts." );
		return $updated_count;
	}
}
