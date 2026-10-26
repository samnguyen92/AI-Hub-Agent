<?php
/**
 * Align Theme - Translation Matrix & SEO Automation
 *
 * Provides live translation status tracking across all pages and posts (EN ↔ ZH),
 * coverage metrics, orphan detection, and automatic 301 redirect on slug change.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Translation_Matrix {

	/**
	 * Initialize hooks and AJAX actions.
	 */
	public static function init() {
		add_action( 'wp_ajax_align_get_translation_matrix', array( __CLASS__, 'ajax_get_translation_matrix' ) );
		add_action( 'post_updated', array( __CLASS__, 'on_slug_change_create_redirect' ), 10, 3 );
	}

	/**
	 * AJAX endpoint to return the translation matrix of all pages/posts.
	 */
	public static function ajax_get_translation_matrix() {
		check_ajax_referer( 'align_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized.', 'align' ) ) );
		}

		if ( ! class_exists( 'Align_Language_Manager' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Language Manager is not initialized.', 'align' ) ) );
		}

		$languages    = Align_Language_Manager::get_languages();
		$default_lang = Align_Language_Manager::get_default_lang();
		$post_type    = isset( $_GET['post_type'] ) && in_array( $_GET['post_type'], array( 'page', 'post', 'all' ), true ) ? $_GET['post_type'] : 'all';

		$types = ( 'all' === $post_type ) ? array( 'page', 'post' ) : array( $post_type );

		// Query all published and draft items
		$posts = get_posts( array(
			'post_type'      => $types,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 200,
			'orderby'        => 'post_type title',
			'order'          => 'ASC',
		) );

		$matrix           = array();
		$processed_groups = array();
		$total_groups     = 0;
		$fully_translated = 0;
		$missing_count    = 0;

		foreach ( $posts as $p ) {
			$post_id    = $p->ID;
			$lang       = Align_Language_Manager::get_post_lang( $post_id ) ?: $default_lang;
			$group_id   = get_post_meta( $post_id, Align_Language_Manager::META_GROUP, true );
			$parent_id  = get_post_meta( $post_id, Align_Language_Manager::META_PARENT, true );

			// Determine primary key for grouping
			$cluster_key = $group_id ?: ( $parent_id ?: ( ( $lang === $default_lang ) ? $post_id : 'solo_' . $post_id ) );

			if ( isset( $processed_groups[ $cluster_key ] ) ) {
				continue;
			}
			$processed_groups[ $cluster_key ] = true;
			$total_groups++;

			// Get all translations linked to this post
			$translations = Align_Language_Manager::get_translations( $post_id );
			$trans_by_lang = array();
			foreach ( $translations as $tr ) {
				$trans_by_lang[ $tr['lang'] ] = $tr;
			}

			// Ensure current post is represented
			if ( ! isset( $trans_by_lang[ $lang ] ) ) {
				$trans_by_lang[ $lang ] = array(
					'post_id'   => $post_id,
					'lang'      => $lang,
					'title'     => $p->post_title,
					'url'       => get_permalink( $post_id ),
					'status'    => $p->post_status,
					'modified'  => $p->post_modified,
				);
			}

			// Find default (EN) post
			$default_item = $trans_by_lang[ $default_lang ] ?? null;
			if ( ! $default_item ) {
				// Fallback to current post if no EN post found
				$default_item = array(
					'post_id'   => $post_id,
					'lang'      => $lang,
					'title'     => $p->post_title,
					'url'       => get_permalink( $post_id ),
					'status'    => $p->post_status,
					'modified'  => $p->post_modified,
				);
			}

			$en_post = get_post( $default_item['post_id'] );
			$en_title = $en_post ? $en_post->post_title : $default_item['title'];
			$en_type  = $en_post ? $en_post->post_type : $p->post_type;

			$lang_statuses = array();
			$all_present   = true;

			foreach ( $languages as $l ) {
				$code = $l['code'];
				if ( isset( $trans_by_lang[ $code ] ) ) {
					$tr_post_id = intval( $trans_by_lang[ $code ]['post_id'] );
					$tr_post    = get_post( $tr_post_id );
					$is_draft   = $tr_post && 'draft' === $tr_post->post_status;
					$edit_url   = admin_url( 'post.php?post=' . $tr_post_id . '&action=edit' );
					$builder_url = admin_url( 'admin.php?page=align-builder&post_id=' . $tr_post_id );

					$lang_statuses[ $code ] = array(
						'status'      => $is_draft ? 'draft' : 'published',
						'post_id'     => $tr_post_id,
						'title'       => $tr_post ? $tr_post->post_title : '',
						'url'         => get_permalink( $tr_post_id ),
						'edit_url'    => $edit_url,
						'builder_url' => $builder_url,
						'modified'    => $tr_post ? $tr_post->post_modified : '',
					);
				} else {
					$all_present = false;
					$missing_count++;
					$create_url = admin_url( 'admin.php?page=align-builder&post_id=' . $default_item['post_id'] . '&create_lang=' . $code );
					$lang_statuses[ $code ] = array(
						'status'     => 'missing',
						'post_id'    => 0,
						'title'      => '',
						'create_url' => $create_url,
					);
				}
			}

			if ( $all_present ) {
				$fully_translated++;
			}

			$matrix[] = array(
				'id'             => $default_item['post_id'],
				'title'          => $en_title,
				'post_type'      => $en_type,
				'cluster_key'    => $cluster_key,
				'languages'      => $lang_statuses,
				'is_complete'    => $all_present,
			);
		}

		wp_send_json_success( array(
			'total'            => $total_groups,
			'fully_translated' => $fully_translated,
			'missing_count'    => $missing_count,
			'languages'        => $languages,
			'matrix'           => $matrix,
		) );
	}

	/**
	 * Automatically create 301 redirect in Rank Math when a published post slug is changed.
	 *
	 * @param int     $post_id     Post ID.
	 * @param WP_Post $post_after  Updated post object.
	 * @param WP_Post $post_before Original post object.
	 */
	public static function on_slug_change_create_redirect( $post_id, $post_after, $post_before ) {
		// Ignore auto-drafts and revisions
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Only trigger when slug actually changed on published items
		if ( empty( $post_before->post_name ) || empty( $post_after->post_name ) ) {
			return;
		}
		if ( $post_before->post_name === $post_after->post_name ) {
			return;
		}
		if ( 'publish' !== $post_before->post_status && 'publish' !== $post_after->post_status ) {
			return;
		}

		// Ensure importer class is loaded.
		if ( ! class_exists( 'Align_Redirection_Importer' ) ) {
			$importer_file = __DIR__ . '/class-align-redirection-importer.php';
			if ( file_exists( $importer_file ) ) {
				require_once $importer_file;
			}
		}

		// Check if Rank Math Redirection module is available
		if ( ! class_exists( 'Align_Redirection_Importer' ) || ! Align_Redirection_Importer::is_rank_math_active() ) {
			return;
		}

		// Check if feature option is enabled
		$auto_redirect_enabled = get_option( 'align_enable_auto_redirect', '1' );
		if ( '0' === $auto_redirect_enabled || false === $auto_redirect_enabled ) {
			return;
		}

		// Compute old and new URL paths
		$old_slug = $post_before->post_name;
		$new_slug = $post_after->post_name;

		$old_permalink = get_permalink( $post_before );
		$new_permalink = get_permalink( $post_after );

		$old_path = wp_parse_url( $old_permalink, PHP_URL_PATH );
		$new_path = wp_parse_url( $new_permalink, PHP_URL_PATH );

		if ( empty( $old_path ) || empty( $new_path ) || $old_path === $new_path ) {
			return;
		}

		try {
			$clean_pattern = \RankMath\Redirections\DB::get_clean_pattern( $old_path, 'exact' );

			// Check if already redirected
			global $wpdb;
			$table = $wpdb->prefix . 'rank_math_redirections';
			$like  = '%' . $wpdb->esc_like( $clean_pattern ) . '%';
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE sources LIKE %s LIMIT 1", $like ) );

			if ( ! $existing ) {
				$sources = array(
					array(
						'pattern'    => $clean_pattern,
						'comparison' => 'exact',
						'ignore'     => '',
					),
				);

				$insert_id = \RankMath\Redirections\DB::add( array(
					'sources'     => $sources,
					'url_to'      => $new_path,
					'header_code' => 301,
					'status'      => 'active',
				) );

				if ( $insert_id ) {
					// Purge cache
					if ( method_exists( 'Align_Redirection_Importer', 'purge_rankmath_cache' ) ) {
						Align_Redirection_Importer::purge_rankmath_cache();
					} else {
						$cache_table = $wpdb->prefix . 'rank_math_redirections_cache';
						$wpdb->query( "TRUNCATE TABLE {$cache_table}" );
					}
					error_log( sprintf( '[Align TranslationMatrix] Auto-created 301 redirect for post #%d: %s -> %s', $post_id, $old_path, $new_path ) );
				}
			}
		} catch ( \Throwable $e ) {
			error_log( '[Align TranslationMatrix] Failed to create auto redirect: ' . $e->getMessage() );
		}
	}
}
