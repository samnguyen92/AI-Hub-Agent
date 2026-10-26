<?php
/**
 * Align Theme Core Engine - Builder Revisions Handler
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Builder_Revision_Handler {

	/**
	 * Retrieve revisions list for a given post ID or template part ID.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_revisions( $request ) {
		$post_id = intval( $request->get_param( 'post_id' ) ?: $request->get_param( 'id' ) );
		$tp_id   = $request->get_param( 'tp_id' );

		// Resolve post ID from tp_id if post_id not directly provided
		if ( ! $post_id && ! empty( $tp_id ) ) {
			$raw_id           = preg_replace( '/^t(p|pl)_/', '', $tp_id );
			$is_template      = ( 'template' === $request->get_param( 'template_type' ) || 0 === strpos( $tp_id, 'tpl_' ) );
			$post_type_target = $is_template ? 'wp_template' : 'wp_template_part';
			$slug             = sanitize_file_name( ( strpos( $raw_id, '//' ) !== false ) ? explode( '//', $raw_id )[1] : $raw_id );
			$search_slugs     = array_values( array_unique( array_filter( array(
				$slug, get_stylesheet() . '//' . $slug, get_template() . '//' . $slug, sanitize_title( get_stylesheet() . '-' . $slug ), sanitize_title( get_template() . '-' . $slug ), $raw_id, sanitize_title( $raw_id ),
			) ) ) );

			$found = get_posts( array(
				'post_type'      => $post_type_target,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'post_name__in'  => $search_slugs,
				'tax_query'      => array(
					array(
						'taxonomy' => 'wp_theme',
						'field'    => 'slug',
						'terms'    => array( get_stylesheet(), get_template() ),
					),
				),
			) );

			if ( empty( $found ) ) {
				$found = get_posts( array(
					'post_type'      => $post_type_target,
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'post_name__in'  => $search_slugs,
				) );
			}

			if ( ! empty( $found ) ) {
				$post_id = $found[0]->ID;
			}
		}

		if ( ! $post_id ) {
			return rest_ensure_response( array(
				'status'    => 'success',
				'post_id'   => 0,
				'revisions' => array(),
				'message'   => __( 'No post ID provided or entity not saved yet.', 'align' ),
			) );
		}

		$current_post = get_post( $post_id );
		if ( ! $current_post ) {
			return new WP_Error( 'post_not_found', __( 'Target post not found.', 'align' ), array( 'status' => 404 ) );
		}

		// Query native WordPress revisions
		$wp_revisions = wp_get_post_revisions( $post_id, array(
			'order'   => 'DESC',
			'orderby' => 'date',
		) );

		$revisions_data = array();

		// Add current active version as the first item
		$current_author = get_userdata( $current_post->post_author );
		$revisions_data[] = array(
			'id'              => $current_post->ID,
			'revision_id'     => $current_post->ID,
			'post_id'         => $post_id,
			'title'           => ! empty( $current_post->post_title ) ? $current_post->post_title : __( 'Current Version', 'align' ),
			'date'            => $current_post->post_modified,
			'date_formatted'  => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $current_post->post_modified ) ),
			'human_time'      => sprintf( __( '%s ago', 'align' ), human_time_diff( strtotime( $current_post->post_modified ), current_time( 'timestamp' ) ) ),
			'author_name'     => $current_author ? $current_author->display_name : __( 'System', 'align' ),
			'author_avatar'   => get_avatar_url( $current_post->post_author, array( 'size' => 48 ) ),
			'content_length'  => strlen( $current_post->post_content ),
			'is_current'      => true,
			'is_autosave'     => false,
		);

		if ( ! empty( $wp_revisions ) ) {
			foreach ( $wp_revisions as $rev ) {
				if ( (int) $rev->ID === (int) $current_post->ID ) {
					continue;
				}

				$is_autosave = wp_is_post_autosave( $rev->ID ) ? true : false;
				$rev_author  = get_userdata( $rev->post_author );

				$revisions_data[] = array(
					'id'              => $rev->ID,
					'revision_id'     => $rev->ID,
					'post_id'         => $post_id,
					'title'           => ! empty( $rev->post_title ) ? $rev->post_title : sprintf( __( 'Revision #%d', 'align' ), $rev->ID ),
					'date'            => $rev->post_modified,
					'date_formatted'  => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $rev->post_modified ) ),
					'human_time'      => sprintf( __( '%s ago', 'align' ), human_time_diff( strtotime( $rev->post_modified ), current_time( 'timestamp' ) ) ),
					'author_name'     => $rev_author ? $rev_author->display_name : __( 'Unknown', 'align' ),
					'author_avatar'   => get_avatar_url( $rev->post_author, array( 'size' => 48 ) ),
					'content_length'  => strlen( $rev->post_content ),
					'is_current'      => false,
					'is_autosave'     => $is_autosave,
				);
			}
		}

		error_log( sprintf( '[Align RevisionHandler] Retrieved %d revision(s) for Post #%d (tp_id: %s)', count( $revisions_data ), $post_id, $tp_id ?: 'none' ) );

		return rest_ensure_response( array(
			'status'    => 'success',
			'post_id'   => $post_id,
			'tp_id'     => $tp_id,
			'total'     => count( $revisions_data ),
			'revisions' => $revisions_data,
		) );
	}

	/**
	 * Restore a specific revision to become the active post content.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function restore_revision( $request ) {
		$params      = $request->get_json_params();
		$revision_id = isset( $params['revision_id'] ) ? intval( $params['revision_id'] ) : intval( $request->get_param( 'revision_id' ) );
		$post_id     = isset( $params['post_id'] ) ? intval( $params['post_id'] ) : intval( $request->get_param( 'post_id' ) );
		$tp_id       = isset( $params['tp_id'] ) ? sanitize_text_field( $params['tp_id'] ) : sanitize_text_field( $request->get_param( 'tp_id' ) );

		if ( ! $revision_id ) {
			return new WP_Error( 'missing_revision_id', __( 'Revision ID is required.', 'align' ), array( 'status' => 400 ) );
		}

		$revision = get_post( $revision_id );
		if ( ! $revision ) {
			return new WP_Error( 'revision_not_found', __( 'Revision not found.', 'align' ), array( 'status' => 404 ) );
		}

		if ( ! $post_id && $revision->post_parent ) {
			$post_id = $revision->post_parent;
		}

		if ( ! $post_id ) {
			return new WP_Error( 'missing_post_id', __( 'Post parent ID could not be determined.', 'align' ), array( 'status' => 400 ) );
		}

		// Perform core WordPress revision restoration
		kses_remove_filters();
		$restored_id = wp_restore_post_revision( $revision_id );
		kses_init_filters();

		if ( ! $restored_id ) {
			// Fallback: manually update post_content if wp_restore_post_revision returns null (e.g. for custom post types)
			$updated = wp_update_post( array(
				'ID'           => $post_id,
				'post_content' => wp_slash( $revision->post_content ),
			) );
			if ( is_wp_error( $updated ) || ! $updated ) {
				return new WP_Error( 'restore_failed', __( 'Failed to restore revision.', 'align' ), array( 'status' => 500 ) );
			}
		}

		error_log( '[Align Revision] Restore changes post_content only; CSS/JS files unchanged' );

		// If this is a Template or Template Part, synchronize to disk file
		if ( ! empty( $tp_id ) ) {
			$raw_id      = preg_replace( '/^t(p|pl)_/', '', $tp_id );
			$is_template = ( 0 === strpos( $tp_id, 'tpl_' ) );
			$slug        = sanitize_file_name( ( strpos( $raw_id, '//' ) !== false ) ? explode( '//', $raw_id )[1] : $raw_id );
			$sub_dir     = $is_template ? 'templates' : 'parts';
			$target_dir  = get_stylesheet_directory() . '/' . $sub_dir;
			$target_file = $target_dir . '/' . $slug . '.html';

			if ( is_dir( $target_dir ) ) {
				file_put_contents( $target_file, $revision->post_content );
				error_log( sprintf( '[Align RevisionHandler] Synced restored revision #%d to disk: %s', $revision_id, $target_file ) );
			}

			if ( function_exists( 'wp_clean_theme_json_cache' ) ) {
				wp_clean_theme_json_cache();
			}
		}

		error_log( sprintf( '[Align RevisionHandler] Restored revision #%d to Post #%d (tp_id: %s)', $revision_id, $post_id, $tp_id ?: 'none' ) );

		return rest_ensure_response( array(
			'status'      => 'success',
			'message'     => sprintf( __( 'Successfully restored revision #%d!', 'align' ), $revision_id ),
			'post_id'     => $post_id,
			'revision_id' => $revision_id,
			'tp_id'       => $tp_id,
		) );
	}
}
