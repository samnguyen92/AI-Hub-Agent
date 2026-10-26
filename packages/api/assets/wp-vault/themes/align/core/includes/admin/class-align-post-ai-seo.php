<?php
/**
 * Align Theme - Post AI SEO & Content Generator Assistant
 *
 * Provides a dedicated Gutenberg sidebar meta box for Posts (and Pages) with:
 * 1. AI SEO Generator (Focus Keyword, SEO Title, Description, Slug, Excerpt, Rank Math Sync).
 * 2. AI Content Generator & Translator (Full Post Translation, Section Writing & Live Block Insertion).
 *
 * Adheres strictly to Align Settings Style Guide & < 500 lines constraint.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Post_Ai_Seo {

	/**
	 * Initialize hooks for Gutenberg post editor.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_box' ) );
	}

	/**
	 * Register the AI & SEO Assistant meta box in the post sidebar.
	 */
	public static function register_meta_box() {
		$screens = array( 'post', 'page' );
		foreach ( $screens as $screen ) {
			add_meta_box(
				'align_post_ai_seo',
				__( 'Align AI & SEO Assistant', 'align' ),
				array( __CLASS__, 'render_meta_box' ),
				$screen,
				'side',
				'high'
			);
		}
	}

	/**
	 * Render the Meta Box inside Gutenberg editor.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public static function render_meta_box( $post ) {
		$post_id      = $post->ID;
		$post_lang    = get_post_meta( $post_id, '_align_lang', true ) ?: ( class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en' );
		$all_langs    = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_languages() : array();
		$lang_name    = strtoupper( $post_lang );
		foreach ( $all_langs as $l ) {
			if ( $l['code'] === $post_lang ) {
				$lang_name = $l['name'];
				break;
			}
		}

		$seo_title     = get_post_meta( $post_id, 'rank_math_title', true ) ?: '';
		$seo_desc      = get_post_meta( $post_id, 'rank_math_description', true ) ?: '';
		$focus_kw      = get_post_meta( $post_id, 'rank_math_focus_keyword', true ) ?: '';
		$seo_score     = get_post_meta( $post_id, 'rank_math_seo_score', true ) ?: '';
		$post_slug     = $post->post_name;
		$post_excerpt  = $post->post_excerpt;

		$rest_nonce     = wp_create_nonce( 'wp_rest' );
		$rest_generate  = esc_url_raw( rest_url( ALIGN_BUILDER_REST_NS . '/seo-generate' ) );
		$rest_save_seo  = esc_url_raw( rest_url( ALIGN_BUILDER_REST_NS . '/seo-meta' ) );
		$rest_trans     = esc_url_raw( rest_url( ALIGN_BUILDER_REST_NS . '/translate-post' ) );
		$rest_copy_orig = esc_url_raw( rest_url( ALIGN_BUILDER_REST_NS . '/copy-original-post' ) );
		$rest_content   = esc_url_raw( rest_url( ALIGN_BUILDER_REST_NS . '/generate-content' ) );

		$source_post_id = get_post_meta( $post_id, class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::META_PARENT : '_align_lang_parent', true );
		if ( empty( $source_post_id ) && class_exists( 'Align_Language_Manager' ) ) {
			$translations = Align_Language_Manager::get_translations( $post_id );
			foreach ( $translations as $tr ) {
				if ( 'en' === $tr['lang'] && intval( $tr['post_id'] ) !== $post_id ) {
					$source_post_id = intval( $tr['post_id'] );
					break;
				}
			}
		}
		if ( empty( $source_post_id ) ) {
			$source_post_id = get_post_meta( $post_id, '_align_translation_source_id', true );
		}
		$source_post_title = $source_post_id ? get_the_title( $source_post_id ) : '';
		require __DIR__ . '/views/part-post-ai-seo.php';
	}
}

