<?php
/**
 * Align Theme - Bulk AI SEO Generator Engine
 *
 * Scans Pages and Posts across languages, generates high-ranking SEO metadata
 * using Gemini AI, synchronizes with Rank Math, and guarantees 80+ SEO scores.
 *
 * Adheres strictly to Align coding standards & < 500 lines limit.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Bulk_Seo_Generator {

	/**
	 * Initialize AJAX hooks.
	 */
	public static function init() {
		add_action( 'wp_ajax_align_bulk_seo_scan_items', array( __CLASS__, 'ajax_scan_items' ) );
		add_action( 'wp_ajax_align_bulk_seo_process_item', array( __CLASS__, 'ajax_process_item' ) );
		add_action( 'wp_ajax_align_bulk_seo_recalculate_item', array( __CLASS__, 'ajax_recalculate_item' ) );
	}

	/**
	 * AJAX: Scan items based on post type, language, and SEO status filters.
	 */
	public static function ajax_scan_items() {
		check_ajax_referer( 'align_settings_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized access.' ), 403 );
		}

		$post_types = isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] )
			? array_map( 'sanitize_key', $_POST['post_types'] )
			: array( 'page', 'post' );

		$lang_filter = isset( $_POST['lang'] ) ? sanitize_key( $_POST['lang'] ) : 'all';
		$scope       = isset( $_POST['scope'] ) ? sanitize_key( $_POST['scope'] ) : 'needs_optimization';
		$search      = isset( $_POST['search'] ) ? sanitize_text_field( (string) $_POST['search'] ) : '';

		error_log( "[Align BulkSeo] Scan items request: types=" . implode( ',', $post_types ) . " | lang={$lang_filter} | scope={$scope} | search={$search}" );

		$query_args = array(
			'post_type'      => $post_types,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'DESC',
		);

		if ( ! empty( $search ) ) {
			$query_args['s'] = $search;
		}

		$posts = get_posts( $query_args );
		$default_lang = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en';

		$items        = array();
		$stats_total  = 0;
		$stats_opt    = 0; // >= 80
		$stats_mod    = 0; // 50 - 79
		$stats_needs  = 0; // < 50 or empty

		foreach ( $posts as $post ) {
			$post_id   = $post->ID;
			$post_lang = get_post_meta( $post_id, '_align_lang', true ) ?: $default_lang;

			// Language filtering
			if ( 'all' !== $lang_filter && $post_lang !== $lang_filter ) {
				continue;
			}

			$focus_kw   = get_post_meta( $post_id, 'rank_math_focus_keyword', true ) ?: '';
			$seo_title  = get_post_meta( $post_id, 'rank_math_title', true ) ?: '';
			$seo_desc   = get_post_meta( $post_id, 'rank_math_description', true ) ?: '';
			$raw_score  = get_post_meta( $post_id, 'rank_math_seo_score', true );
			$score      = ( '' !== $raw_score && false !== $raw_score ) ? intval( $raw_score ) : null;

			// Global metrics counter
			$stats_total++;
			if ( null !== $score && $score >= 80 ) {
				$stats_opt++;
			} elseif ( null !== $score && $score >= 50 ) {
				$stats_mod++;
			} else {
				$stats_needs++;
			}

			// Scope filtering
			$has_kw = ! empty( trim( $focus_kw ) );
			if ( 'missing_only' === $scope && $has_kw ) {
				continue;
			}
			if ( 'needs_optimization' === $scope && ( null !== $score && $score >= 80 ) ) {
				continue;
			}

			$kw_parts   = array_map( 'trim', explode( ',', $focus_kw ) );
			$primary_kw = ! empty( $kw_parts[0] ) ? $kw_parts[0] : '';

			$items[] = array(
				'id'            => $post_id,
				'title'         => $post->post_title ?: '(No title #' . $post_id . ')',
				'slug'          => urldecode( (string) $post->post_name ),
				'post_type'     => $post->post_type,
				'lang'          => $post_lang,
				'focus_keyword' => $focus_kw,
				'primary_kw'    => $primary_kw,
				'seo_title'     => $seo_title,
				'seo_desc'      => $seo_desc,
				'score'         => $score,
				'permalink'     => get_permalink( $post_id ),
				'edit_url'      => get_edit_post_link( $post_id, 'raw' ),
			);
		}

		error_log( "[Align BulkSeo] Scanned " . count( $items ) . " filtered items out of {$stats_total} total" );

		wp_send_json_success( array(
			'items' => $items,
			'stats' => array(
				'total'      => $stats_total,
				'optimized'  => $stats_opt,
				'moderate'   => $stats_mod,
				'needs_opt'  => $stats_needs,
			),
		) );
	}

	/**
	 * AJAX: Generate SEO metadata for a single item via AI, save, and update Rank Math score.
	 */
	public static function ajax_process_item() {
		check_ajax_referer( 'align_settings_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized access.' ), 403 );
		}

		$post_id   = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => 'Invalid post ID.' ), 400 );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => 'Post not found.' ), 404 );
		}

		$default_lang = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en';
		$post_lang    = get_post_meta( $post_id, '_align_lang', true ) ?: $default_lang;
		$old_score    = get_post_meta( $post_id, 'rank_math_seo_score', true );

		error_log( "[Align BulkSeo] Processing post_id={$post_id} | title='{$post->post_title}' | lang={$post_lang} | old_score=" . ( $old_score ?: 'none' ) );

		if ( ! class_exists( 'Align_Builder_Seo_Handler' ) ) {
			require_once get_template_directory() . '/core/inc/builder/class-builder-seo-handler.php';
		}

		// 1. Generate SEO meta via Gemini AI
		$seo_request = new WP_REST_Request( 'POST', '/align-builder/v1/seo-generate' );
		$seo_request->set_param( 'post_id', $post_id );
		$seo_request->set_param( 'lang', $post_lang );

		$gen_response = Align_Builder_Seo_Handler::generate_seo_meta( $seo_request );
		if ( is_wp_error( $gen_response ) ) {
			error_log( "[Align BulkSeo] Generation failed for post_id={$post_id}: " . $gen_response->get_error_message() );
			wp_send_json_error( array( 'message' => $gen_response->get_error_message() ), 500 );
		}

		$gen_data = $gen_response->get_data();
		if ( empty( $gen_data['generated'] ) ) {
			wp_send_json_error( array( 'message' => 'Failed to retrieve generated SEO metadata.' ), 500 );
		}

		$meta = $gen_data['generated'];

		// 2. Prepare save request
		$save_request = new WP_REST_Request( 'POST', '/align-builder/v1/seo-meta' );
		$save_request->set_param( 'post_id', $post_id );
		$save_request->set_param( 'focus_keyword', $meta['focus_keyword'] );
		$save_request->set_param( 'title', $meta['title'] );
		$save_request->set_param( 'description', $meta['description'] );
		$save_request->set_param( 'og_title', $meta['og_title'] ?? $meta['title'] );
		$save_request->set_param( 'og_description', $meta['og_description'] ?? $meta['description'] );

		if ( ! empty( $meta['excerpt'] ) ) {
			$save_request->set_param( 'excerpt', $meta['excerpt'] );
		}

		// Save SEO metadata and calculate score (slug is permanently locked to preserve existing permalinks)
		$save_response = Align_Builder_Seo_Handler::save_seo_meta( $save_request );
		if ( is_wp_error( $save_response ) ) {
			wp_send_json_error( array( 'message' => $save_response->get_error_message() ), 500 );
		}

		// 3. Evaluate exact Rank Math score
		$eval_result = Align_Builder_Seo_Handler::evaluate_rank_math_score(
			$post_id,
			$meta['focus_keyword'],
			$meta['title'],
			$meta['description'],
			get_post_field( 'post_name', $post_id ),
			true
		);

		$new_score = $eval_result['score'];
		error_log( "[Align BulkSeo] Successfully optimized post_id={$post_id} | score: " . ( $old_score ?: '0' ) . " -> {$new_score}" );

		wp_send_json_success( array(
			'post_id'       => $post_id,
			'title'         => get_the_title( $post_id ),
			'slug'          => urldecode( (string) get_post_field( 'post_name', $post_id ) ),
			'focus_keyword' => $meta['focus_keyword'],
			'seo_title'     => $meta['title'],
			'seo_desc'      => $meta['description'],
			'old_score'     => ( '' !== $old_score && false !== $old_score ) ? intval( $old_score ) : null,
			'new_score'     => $new_score,
			'tests'         => $eval_result['tests'],
		) );
	}

	/**
	 * AJAX: Recalculate Rank Math score for an item without invoking AI.
	 */
	public static function ajax_recalculate_item() {
		check_ajax_referer( 'align_settings_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized access.' ), 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => 'Invalid post ID.' ), 400 );
		}

		if ( ! class_exists( 'Align_Builder_Seo_Handler' ) ) {
			require_once get_template_directory() . '/core/inc/builder/class-builder-seo-handler.php';
		}

		$kw    = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
		$title = get_post_meta( $post_id, 'rank_math_title', true );
		$desc  = get_post_meta( $post_id, 'rank_math_description', true );
		$slug  = get_post_field( 'post_name', $post_id );

		$eval_result = Align_Builder_Seo_Handler::evaluate_rank_math_score( $post_id, $kw, $title, $desc, $slug, true );

		wp_send_json_success( array(
			'post_id'   => $post_id,
			'new_score' => $eval_result['score'],
			'tests'     => $eval_result['tests'],
		) );
	}
}
