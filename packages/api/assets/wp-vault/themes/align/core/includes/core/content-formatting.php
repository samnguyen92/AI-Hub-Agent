<?php
/**
 * Content formatting policy for Builder HTML and the wpautop setting.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Disable wpautop filter on content generated or managed by Align Builder.
 * Prevents WordPress from corrupting custom HTML markup with automated paragraph tags.
 *
 * @param string $content HTML content to inspect.
 * @return string Original content unchanged.
 */
function align_disable_wpautop_for_builder( $content ) {
	$disable_wpautop = (bool) get_option( 'align_disable_wpautop', false );
	if ( $disable_wpautop ) {
		$priority = has_filter( 'the_content', 'wpautop' );
		if ( false !== $priority ) {
			remove_filter( 'the_content', 'wpautop', $priority );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Align Core] wpautop disabled | filter=the_content | priority=' . $priority );
			}
		}
	}

	if ( empty( $content ) || ! is_string( $content ) ) {
		return $content;
	}

	$is_builder = (
		strpos( $content, 'wp:align/custom-html' ) !== false ||
		strpos( $content, 'data-component-' ) !== false ||
		strpos( $content, 'align-custom-html-block' ) !== false
	);

	if ( ! $is_builder && function_exists( 'has_block' ) && has_block( 'align/custom-html' ) ) {
		$is_builder = true;
	}

	if ( ! $is_builder && function_exists( 'is_singular' ) && is_singular() ) {
		$queried_post = get_post();
		if ( $queried_post && ! empty( $queried_post->post_content ) && strpos( $queried_post->post_content, 'wp:align/custom-html' ) !== false ) {
			$is_builder = true;
		}
	}

	if ( $is_builder ) {
		remove_filter( 'the_content', 'wpautop' );
		remove_filter( 'the_content', 'wptexturize' );
	} else {
		if ( ! $disable_wpautop && ! has_filter( 'the_content', 'wpautop' ) ) {
			add_filter( 'the_content', 'wpautop', 10 );
		}
		if ( ! has_filter( 'the_content', 'wptexturize' ) ) {
			add_filter( 'the_content', 'wptexturize', 10 );
		}
	}

	return $content;
}
add_filter( 'the_content', 'align_disable_wpautop_for_builder', 0 );

