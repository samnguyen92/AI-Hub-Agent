<?php
/**
 * Align Theme Core Engine - Builder Asset Synchronizer
 *
 * Provides non-destructive migration and synchronization of scoped CSS and JS assets
 * from post or template content to disk files.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	if ( ! defined( 'ALIGN_TEST_RUNNER' ) ) {
		exit;
	}
}

class Align_Builder_Asset_Sync {

	/**
	 * Extract scoped CSS and JS from template or template part content and write to external files.
	 * Returns early if no inline assets exist (File-First mode).
	 *
	 * @param string $content HTML or block content.
	 * @param string $slug    Template or part slug.
	 * @param string $type    'template' or 'part'.
	 * @return void
	 */
	public static function sync_template_assets_from_content( string $content, string $slug, string $type = 'template' ): void {
		$prefix    = ( 'template' === $type ) ? 'template' : 'part';
		$style_id  = 'style-' . $prefix . '-' . $slug;
		$script_id = 'script-' . $prefix . '-' . $slug;
		$css       = '';
		$js        = '';

		if ( preg_match( '/<style[^>]*\bid\s*=\s*(["\']?)' . preg_quote( $style_id, '/' ) . '\1[^>]*>([\s\S]*?)<\/style>/is', $content, $m ) ) {
			$css = trim( $m[2] );
		}
		if ( preg_match( '/<script[^>]*\bid\s*=\s*(["\']?)' . preg_quote( $script_id, '/' ) . '\1[^>]*>([\s\S]*?)<\/script>/is', $content, $m ) ) {
			$js = trim( $m[2] );
		}

		if ( '' === $css && '' === $js ) {
			error_log( sprintf( '[Align Persistence] Skip asset sync for %s "%s" (File-First, no inline assets)', $type, $slug ) );
			return;
		}

		if ( class_exists( 'Align_Builder_Assets' ) && method_exists( 'Align_Builder_Assets', 'sync_external_assets' ) ) {
			Align_Builder_Assets::sync_external_assets( $style_id, $script_id, $css, $js, array( 'align-style-' . $prefix . '-' . $slug ), array( 'align-script-' . $prefix . '-' . $slug ) );
			error_log( sprintf( '[Align Persistence] Synced %s assets for "%s" (CSS: %d bytes, JS: %d bytes)', $type, $slug, strlen( $css ), strlen( $js ) ) );
		}
	}

	/**
	 * Extract scoped CSS and JS from post_content and write to external files.
	 * Returns early if no inline assets exist (File-First mode).
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function sync_post_assets_from_content( int $post_id, $post ): void {
		$content = is_object( $post ) ? ( $post->post_content ?? '' ) : '';
		$slug    = ( is_object( $post ) && ! empty( $post->post_name ) ) ? $post->post_name : (string) $post_id;
		$prefix  = ( is_object( $post ) && 'wp_block' === ( $post->post_type ?? '' ) ) ? 'block' : 'page';

		$style_id  = 'style-' . $prefix . '-' . $slug;
		$script_id = 'script-' . $prefix . '-' . $slug;

		$css = '';
		$js  = '';

		if ( preg_match( '/<style[^>]*\bid\s*=\s*(["\']?)' . preg_quote( $style_id, '/' ) . '\1[^>]*>([\s\S]*?)<\/style>/is', $content, $m ) ) {
			$css = trim( $m[2] );
		} elseif ( preg_match( '/<style[^>]*\bid\s*=\s*(["\']?)vvvebjs-styles\1[^>]*>([\s\S]*?)<\/style>/is', $content, $m ) ) {
			$css = trim( $m[2] );
		}

		if ( preg_match( '/<script[^>]*\bid\s*=\s*(["\']?)' . preg_quote( $script_id, '/' ) . '\1[^>]*>([\s\S]*?)<\/script>/is', $content, $m ) ) {
			$js = trim( $m[2] );
		} elseif ( preg_match( '/<script[^>]*\bid\s*=\s*(["\']?)vvvebjs-scripts\1[^>]*>([\s\S]*?)<\/script>/is', $content, $m ) ) {
			$js = trim( $m[2] );
		}

		if ( '' === $css && '' === $js ) {
			error_log( sprintf( '[Align Persistence] Skip asset sync for Post #%d (File-First, no inline assets)', $post_id ) );
			return;
		}

		if ( class_exists( 'Align_Builder_Assets' ) && method_exists( 'Align_Builder_Assets', 'sync_external_assets' ) ) {
			Align_Builder_Assets::sync_external_assets( $style_id, $script_id, $css, $js );
			error_log( sprintf( '[Align Persistence] Auto-synced assets for Post #%d (CSS: %d bytes, JS: %d bytes)', $post_id, strlen( $css ), strlen( $js ) ) );
		}
	}
}
