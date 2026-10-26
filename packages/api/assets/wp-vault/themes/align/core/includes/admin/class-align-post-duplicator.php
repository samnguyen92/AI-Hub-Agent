<?php
/**
 * Align Post Duplicator - Admin Feature
 *
 * Allows administrators and editors to quickly clone posts, pages,
 * and custom post types as drafts with metadata and taxonomy terms preserved.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Post_Duplicator {

	/**
	 * Nonce action name.
	 */
	const NONCE_ACTION = 'align_duplicate_post_';

	/**
	 * Nonce query parameter name.
	 */
	const NONCE_NAME = 'align_duplicate_nonce';

	/**
	 * Initialize the post duplicator module.
	 */
	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}

		$instance = new self();

		// Add Duplicate link to row actions
		add_filter( 'post_row_actions', [ $instance, 'add_duplicate_link' ], 10, 2 );
		add_filter( 'page_row_actions', [ $instance, 'add_duplicate_link' ], 10, 2 );

		// Handle duplicate action request
		add_action( 'admin_action_align_duplicate_post', [ $instance, 'handle_duplicate_post' ] );

		// Backward compatibility with legacy snippet action
		add_action( 'admin_action_rd_duplicate_post_as_draft', [ $instance, 'handle_duplicate_post' ] );
	}

	/**
	 * Add Duplicate action link to post and page list row actions.
	 *
	 * @param array<string, string> $actions Existing row action links.
	 * @param WP_Post               $post    Current post object.
	 * @return array<string, string> Modified row actions.
	 */
	public function add_duplicate_link( array $actions, WP_Post $post ): array {
		if ( ! is_a( $post, 'WP_Post' ) ) {
			return $actions;
		}

		// Skip internal post types that should not be cloned
		$excluded_post_types = [ 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset' ];
		if ( in_array( $post->post_type, $excluded_post_types, true ) ) {
			return $actions;
		}

		// Ensure current user has permission to edit this post
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$duplicate_url = wp_nonce_url(
			admin_url( 'admin.php?action=align_duplicate_post&post=' . $post->ID ),
			self::NONCE_ACTION . $post->ID,
			self::NONCE_NAME
		);

		$actions['duplicate'] = sprintf(
			'<a href="%s" title="%s" aria-label="%s">%s</a>',
			esc_url( $duplicate_url ),
			esc_attr__( 'Duplicate this item as a draft', 'align' ),
			esc_attr( sprintf( __( 'Duplicate &#8220;%s&#8221; as a draft', 'align' ), $post->post_title ) ),
			esc_html__( 'Duplicate', 'align' )
		);

		return $actions;
	}

	/**
	 * Handle post duplication and redirect user to the new draft edit screen.
	 */
	public function handle_duplicate_post(): void {
		global $wpdb;

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : ( isset( $_POST['post'] ) ? absint( $_POST['post'] ) : 0 );

		if ( ! $post_id ) {
			wp_die( esc_html__( 'No post to duplicate has been supplied!', 'align' ) );
		}

		// Verify security nonce (supports standard and legacy nonce keys)
		$has_valid_nonce = false;
		if ( isset( $_REQUEST[ self::NONCE_NAME ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION . $post_id ) ) {
			$has_valid_nonce = true;
		} elseif ( isset( $_GET['duplicate_nonce'] ) && ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['duplicate_nonce'] ) ), 'duplicate.php' ) || wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['duplicate_nonce'] ) ), basename( __FILE__ ) ) ) ) {
			$has_valid_nonce = true;
		}

		if ( ! $has_valid_nonce ) {
			wp_die( esc_html__( 'Security check failed. Please refresh and try again.', 'align' ) );
		}

		// Capability authorization check
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to duplicate this post.', 'align' ) );
		}

		// Retrieve source post object
		$post = get_post( $post_id );
		if ( ! $post || ! is_a( $post, 'WP_Post' ) ) {
			wp_die( esc_html__( 'Post duplication failed, could not find original post: ', 'align' ) . esc_html( (string) $post_id ) );
		}

		$current_user = wp_get_current_user();
		$new_post_author = $current_user->ID ?: $post->post_author;

		// Build new post args
		$args = [
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'post_author'    => $new_post_author,
			'post_content'   => wp_slash( $post->post_content ),
			'post_excerpt'   => wp_slash( $post->post_excerpt ),
			'post_name'      => '', // Reset slug to allow WordPress to generate a clean permalink
			'post_parent'    => $post->post_parent,
			'post_password'  => $post->post_password,
			'post_status'    => 'draft',
			'post_title'     => wp_slash( $post->post_title ),
			'post_type'      => $post->post_type,
			'to_ping'        => $post->to_ping,
			'menu_order'     => $post->menu_order,
		];

		// Insert duplicate post into database
		$new_post_id = wp_insert_post( $args );

		if ( is_wp_error( $new_post_id ) || ! $new_post_id ) {
			$error_msg = is_wp_error( $new_post_id ) ? $new_post_id->get_error_message() : __( 'Failed to create duplicate post.', 'align' );
			wp_die( esc_html( $error_msg ) );
		}

		// Duplicate all assigned taxonomy terms
		$taxonomies = get_object_taxonomies( $post->post_type );
		if ( ! empty( $taxonomies ) ) {
			foreach ( $taxonomies as $taxonomy ) {
				$post_terms = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'slugs' ] );
				if ( ! is_wp_error( $post_terms ) && ! empty( $post_terms ) ) {
					wp_set_object_terms( $new_post_id, $post_terms, $taxonomy, false );
				}
			}
		}

		// Duplicate post meta
		$post_meta_infos = $wpdb->get_results(
			$wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d", $post_id )
		);

		$excluded_meta = [
			'_wp_old_slug',
			'_align_lang_parent',
		];

		if ( ! empty( $post_meta_infos ) ) {
			foreach ( $post_meta_infos as $meta_info ) {
				$meta_key   = $meta_info->meta_key;
				$meta_value = $meta_info->meta_value;

				if ( in_array( $meta_key, $excluded_meta, true ) ) {
					continue;
				}

				// Assign a fresh translation group UUID for the duplicate post to avoid collision
				if ( '_align_lang_group' === $meta_key ) {
					$meta_value = 'grp-' . wp_generate_uuid4();
				}

				$wpdb->insert(
					$wpdb->postmeta,
					[
						'post_id'    => $new_post_id,
						'meta_key'   => $meta_key,
						'meta_value' => $meta_value,
					],
					[
						'%d',
						'%s',
						'%s',
					]
				);
			}
		}

		/**
		 * Action triggered after a post has been duplicated.
		 *
		 * @param int     $new_post_id The ID of the newly created duplicate draft.
		 * @param WP_Post $post        The original post object.
		 */
		do_action( 'align_post_duplicated', $new_post_id, $post );

		error_log( sprintf( '[Align PostDuplicator] Duplicated Post #%d ("%s") to draft Post #%d by user #%d', $post_id, $post->post_title, $new_post_id, $new_post_author ) );

		// Redirect to edit screen of the newly created draft
		wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
		exit;
	}
}
