<?php
/**
 * Developer Tweaks & Wrapper Cleanup Manager for WordPress FSE Templates.
 *
 * Provides configurable switches to enable/disable template-part wrapper unwrapping,
 * clean markup filtering, and block wrapper customization.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Template_Tweaks {

	/**
	 * Default feature configuration flags.
	 *
	 * @var array<string, bool>
	 */
	private static $config = array(
		'unwrap_template_parts' => true,
		'clean_block_comments'  => false,
	);

	/**
	 * Initialize template tweaks and register hooks.
	 */
	public static function init() {
		// Allow child themes or plugins to override feature flags via filter.
		self::$config = apply_filters( 'align_template_tweaks_config', self::$config );

		// 1. Template Parts Wrapper Unwrapper (header, footer, div)
		if ( self::is_enabled( 'unwrap_template_parts' ) ) {
			add_filter( 'render_block_core/template-part', array( __CLASS__, 'unwrap_template_part_wrapper' ), 10, 2 );
		}

		// 2. Clean Frontend Gutenberg Block Comments (<!-- wp:... -->)
		if ( self::is_enabled( 'clean_block_comments' ) ) {
			add_filter( 'render_block', array( __CLASS__, 'strip_block_comments' ), 999, 2 );
		}

		// 3. Guarantee FSE Templates always reflect the active theme's files on disk
		add_action( 'wp_loaded', array( __CLASS__, 'sync_all_templates_with_db' ) );
		add_filter( 'get_block_template', array( __CLASS__, 'sync_file_template_content' ), 10, 3 );
		add_filter( 'pre_get_block_file_template', array( __CLASS__, 'sync_file_template_content' ), 10, 3 );
	}

	/**
	 * Check whether a tweak feature is enabled.
	 *
	 * Checks constant override (e.g., ALIGN_UNWRAP_TEMPLATE_PARTS),
	 * then filter override (align_enable_unwrap_template_parts),
	 * then internal configuration.
	 *
	 * @param string $feature Feature identifier.
	 * @return bool
	 */
	public static function is_enabled( $feature ) {
		$const_name = 'ALIGN_' . strtoupper( $feature );
		if ( defined( $const_name ) ) {
			return (bool) constant( $const_name );
		}

		$enabled = isset( self::$config[ $feature ] ) ? (bool) self::$config[ $feature ] : false;
		return (bool) apply_filters( "align_enable_{$feature}", $enabled );
	}

	/**
	 * Programmatically toggle a feature ON or OFF at runtime.
	 *
	 * @param string $feature Feature identifier.
	 * @param bool   $enable  True to enable, false to disable.
	 */
	public static function set_feature( $feature, $enable = true ) {
		self::$config[ $feature ] = (bool) $enable;
	}

	/**
	 * Remove default <header|footer|div class="wp-block-template-part"> wrapper tags.
	 *
	 * Extracts and returns the pure inner HTML without WordPress wrapper element.
	 *
	 * @param string $block_content Rendered block HTML content.
	 * @param array  $block         Block parsed data array.
	 * @return string Unwrapped clean HTML content.
	 */
	public static function unwrap_template_part_wrapper( $block_content, $block ) {
		if ( empty( $block_content ) || ! is_string( $block_content ) ) {
			return $block_content;
		}

		// Regex: Strip outer wrapper tag having class="...wp-block-template-part..."
		$trimmed = trim( $block_content );
		$pattern = '/^<([a-z0-9]+)\s+[^>]*class="[^"]*wp-block-template-part[^"]*"[^>]*>\s*(.*?)\s*<\/\1>$/is';

		if ( preg_match( $pattern, $trimmed, $matches ) ) {
			return $matches[2];
		}

		return $block_content;
	}

	/**
	 * Strip Gutenberg block comments from frontend rendered block output.
	 *
	 * @param string $block_content Rendered block content.
	 * @param array  $block         Block parsed data array.
	 * @return string Content with block comments removed.
	 */
	public static function strip_block_comments( $block_content, $block ) {
		if ( is_admin() || wp_is_json_request() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $block_content;
		}

		if ( empty( $block_content ) || ! is_string( $block_content ) ) {
			return $block_content;
		}

		return preg_replace( '/<!--\s*\/?wp:[^>]*-->/s', '', $block_content );
	}

	/**
	 * Sync block template content with active child/parent theme HTML template files on disk.
	 *
	 * @param WP_Block_Template|null $block_template Block template object.
	 * @param string                 $id             Template identifier.
	 * @param string                 $template_type  'wp_template' or 'wp_template_part'.
	 * @return WP_Block_Template|null
	 */
	public static function sync_file_template_content( $block_template, $id, $template_type ) {
		if ( ! $block_template || ! is_object( $block_template ) || empty( $block_template->slug ) ) {
			return $block_template;
		}

		$sub_dir = ( 'wp_template_part' === $template_type ) ? 'parts' : 'templates';
		$slug    = $block_template->slug;

		$child_file  = get_stylesheet_directory() . '/' . $sub_dir . '/' . $slug . '.html';
		$parent_file = get_template_directory() . '/' . $sub_dir . '/' . $slug . '.html';

		$target_file = file_exists( $child_file ) ? $child_file : ( file_exists( $parent_file ) ? $parent_file : '' );

		if ( ! empty( $target_file ) ) {
			$file_content = file_get_contents( $target_file );
			if ( ! empty( trim( $file_content ) ) ) {
				$file_mtime = filemtime( $target_file );
				$wp_id      = ! empty( $block_template->wp_id ) ? (int) $block_template->wp_id : 0;
				$db_mtime   = $wp_id ? (int) get_post_modified_time( 'U', true, $wp_id ) : 0;

				// A newer DB customization (native Site Editor save) wins; the REST insert hook mirrors it to disk.
				// Only a file edited after the last DB save (e.g. in an IDE) overrides the DB copy.
				if ( $wp_id > 0 && $file_mtime <= $db_mtime ) {
					return $block_template;
				}

				if ( $block_template->content !== $file_content ) {
					$block_template->content = $file_content;
					if ( $wp_id > 0 ) {
						wp_update_post( array(
							'ID'           => $wp_id,
							'post_content' => wp_slash( $file_content ),
						) );
						error_log( sprintf(
							'[Align TemplateTweaks] Auto-synced file to DB: slug="%s", wp_id=%d (File mtime: %d > DB mtime: %d)',
							$slug,
							$wp_id,
							$file_mtime,
							$db_mtime
						) );
					}
				} else {
					$block_template->content = $file_content;
				}
			}
		}

		return $block_template;
	}

	/**
	 * Synchronize all template and template part HTML files on disk from active stylesheet into wp_posts database.
	 */
	public static function sync_all_templates_with_db() {
		global $wpdb;

		$child_dir = get_stylesheet_directory();

		$dirs = array(
			'wp_template'      => trailingslashit( $child_dir ) . 'templates/',
			'wp_template_part' => trailingslashit( $child_dir ) . 'parts/',
		);

		foreach ( $dirs as $post_type => $dir ) {
			if ( ! is_dir( $dir ) ) {
				continue;
			}
			$files = glob( $dir . '*.html' );
			if ( ! $files ) {
				continue;
			}
			foreach ( $files as $file ) {
				$slug    = basename( $file, '.html' );
				$content = file_get_contents( $file );
				if ( empty( trim( $content ) ) ) {
					continue;
				}

				$sql = $wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name IN (%s, %s, %s)",
					$post_type,
					get_stylesheet() . '//' . $slug,
					get_template() . '//' . $slug,
					$slug
				);
				$ids = $wpdb->get_col( $sql );
				if ( ! empty( $ids ) ) {
					foreach ( $ids as $id ) {
						$cur = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", (int) $id ) );
						if ( $cur !== $content ) {
							$wpdb->update(
								$wpdb->posts,
								array( 'post_content' => $content ),
								array( 'ID' => (int) $id )
							);
							clean_post_cache( (int) $id );
							error_log( sprintf( '[Align SyncTemplates] Updated %s ID #%d for slug "%s" (%d bytes)', $post_type, $id, $slug, strlen( $content ) ) );
						}
					}
				}
			}
		}
	}
}
