<?php
/**
 * Align Agent - Draft Page Clone Manager
 *
 * Implements draft cloning for live published pages so AI modifications
 * are performed safely on draft copies until explicitly promoted via user approval.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Page_Clone {

	/**
	 * Meta key linking clone to original post ID.
	 */
	const META_CLONE_OF = '_align_agent_clone_of';

	/**
	 * Meta key storing original post_modified_gmt at the time clone was branched.
	 */
	const META_BASE_MODIFIED = '_align_agent_clone_base_modified';

	/**
	 * Option key prefix for per-user session clones.
	 */
	const SESSION_KEY_PREFIX = 'align_agent_session_clones_';

	/**
	 * Get an existing active draft clone of the original post, or create a new one.
	 *
	 * Reuses the draft clone if multiple edits occur within the same session.
	 *
	 * @param int $original_id WordPress post ID of the live entity.
	 * @return int Clone post ID, or 0 on failure.
	 */
	public static function get_or_create( int $original_id ): int {
		$original_id = absint( $original_id );
		if ( $original_id <= 0 ) {
			return 0;
		}

		$original = get_post( $original_id );
		if ( ! $original ) {
			return 0;
		}

		// 1. Look for an existing draft clone of this post.
		$existing_clones = get_posts(
			array(
				'post_type'      => $original->post_type,
				'post_status'    => 'draft',
				'posts_per_page' => 1,
				'meta_key'       => self::META_CLONE_OF,
				'meta_value'     => $original_id,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $existing_clones ) ) {
			return (int) $existing_clones[0];
		}

		// 2. Create a new draft clone.
		$new_title = sprintf( '[Draft] %s', $original->post_title );
		$new_slug  = sanitize_title( $original->post_name . '-draft-' . wp_generate_password( 6, false, false ) );
		$parts     = class_exists( 'Align_Gateway_Pages' )
			? Align_Gateway_Pages::extract_decoupled_parts( $original )
			: array( 'html' => $original->post_content, 'css' => '', 'js' => '' );

		$clone_id = 0;
		if ( 'page' === $original->post_type && class_exists( 'Align_Builder_Crud_Handler' ) ) {
			$req = new WP_REST_Request( 'POST', '/align-builder/v1/create-item' );
			$req->set_header( 'Content-Type', 'application/json' );
			$req->set_body( (string) wp_json_encode( array( 'type' => 'page', 'title' => $new_title, 'slug' => $new_slug ) ) );
			$res = Align_Builder_Crud_Handler::create_item( $req );
			if ( ! is_wp_error( $res ) ) {
				$data     = $res instanceof WP_REST_Response ? $res->get_data() : (array) $res;
				$clone_id = absint( $data['post_id'] ?? 0 );
			}
		}

		if ( $clone_id <= 0 ) {
			$clone_id = wp_insert_post(
				array(
					'post_title'   => $new_title,
					'post_name'    => $new_slug,
					'post_type'    => $original->post_type,
					'post_status'  => 'draft',
					'post_content' => $parts['html'],
				)
			);
		}

		if ( is_wp_error( $clone_id ) || ! $clone_id ) {
			error_log( sprintf( '[Align Agent] Failed to create draft clone for post #%d', $original_id ) );
			return 0;
		}

		// Populate content via Builder handler if available.
		if ( class_exists( 'Align_Builder_Crud_Handler' ) ) {
			$save_req = new WP_REST_Request( 'POST', '/align-builder/v1/save-page' );
			foreach ( array( 'post_id' => $clone_id, 'html' => $parts['html'], 'css' => $parts['css'], 'js' => $parts['js'], 'status' => 'draft' ) as $k => $v ) {
				$save_req->set_param( $k, $v );
			}
			Align_Builder_Crud_Handler::save_page( $save_req );
		}

		// Track clone origin and original modified timestamp for concurrency checking.
		update_post_meta( $clone_id, self::META_CLONE_OF, $original_id );
		update_post_meta( $clone_id, self::META_BASE_MODIFIED, (string) $original->post_modified_gmt );

		error_log( sprintf( '[Align Agent] Created draft clone #%d for live post #%d.', $clone_id, $original_id ) );
		return $clone_id;
	}

	/**
	 * Rewrite tool argument targets from original post_id to the clone_id.
	 *
	 * @param string $tool     Tool name.
	 * @param array  $args     Original arguments.
	 * @param int    $clone_id Target clone ID.
	 * @return array Rewritten arguments.
	 */
	public static function rewrite_args( string $tool, array $args, int $clone_id ): array {
		if ( isset( $args['post_id'] ) ) {
			$args['post_id'] = $clone_id;
		}
		if ( isset( $args['id'] ) ) {
			$args['id'] = $clone_id;
		}
		if ( isset( $args['status'] ) ) {
			$args['status'] = 'draft';
		}
		return $args;
	}

	/**
	 * Handle autonomous write execution against a draft clone.
	 *
	 * @param string $tool    Tool name.
	 * @param array  $args    Tool arguments.
	 * @param int    $user_id Current user ID.
	 * @return array Execution result.
	 */
	public static function handle_write( string $tool, array $args, int $user_id ): array {
		$orig_id = absint( $args['post_id'] ?? ( $args['id'] ?? 0 ) );
		if ( $orig_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'missing_post_id',
				'message' => __( 'Missing post_id for clone modification.', 'align-agent' ),
			);
		}

		$clone_id = self::get_or_create( $orig_id );
		if ( $clone_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'clone_failed',
				'message' => sprintf( __( 'Could not initialize draft clone for page #%d.', 'align-agent' ), $orig_id ),
			);
		}

		$new_args = self::rewrite_args( $tool, $args, $clone_id );
		$result   = Align_Agent_Tool_Registry::execute( $tool, $new_args, $user_id );

		// Record clone in session so runner can queue apply action at turn end.
		self::record_session_clone( $user_id, $clone_id );

		if ( class_exists( 'Align_Agent_Audit_Log' ) ) {
			Align_Agent_Audit_Log::log_action(
				$user_id,
				$tool,
				$clone_id,
				'clone_write',
				array( 'original_id' => $orig_id, 'args' => $new_args )
			);
		}

		$note = sprintf( ' (Edited on draft clone #%d of live page #%d. Changes will be promoted upon your approval).', $clone_id, $orig_id );
		$result['message'] = ( $result['message'] ?? '' ) . $note;

		return $result;
	}

	/**
	 * Apply content from draft clone back into the original live page and clean up.
	 *
	 * @param int $clone_id Draft clone post ID.
	 * @return array
	 */
	public static function apply( int $clone_id ): array {
		$clone_id = absint( $clone_id );
		$orig_id  = absint( get_post_meta( $clone_id, self::META_CLONE_OF, true ) );
		if ( $orig_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'not_a_clone',
				'message' => sprintf( __( 'Post #%d is not an active draft clone.', 'align-agent' ), $clone_id ),
			);
		}

		$original = get_post( $orig_id );
		$clone    = get_post( $clone_id );
		if ( ! $original || ! $clone ) {
			return array(
				'success' => false,
				'error'   => 'post_not_found',
				'message' => __( 'Original or clone post record not found.', 'align-agent' ),
			);
		}

		// Gutenberg editor lock check on original.
		if ( class_exists( 'Align_Editor_Mode_Lock' ) && Align_Editor_Mode_Lock::is_locked_to( $orig_id, 'gutenberg' ) ) {
			return array(
				'success' => false,
				'error'   => 'editor_mode_locked',
				'message' => sprintf( __( 'Page #%d is locked to Gutenberg editor.', 'align-agent' ), $orig_id ),
			);
		}

		// Detect concurrent manual edit on live page.
		$base_mod     = (string) get_post_meta( $clone_id, self::META_BASE_MODIFIED, true );
		$base_changed = ( '' !== $base_mod && (string) $original->post_modified_gmt !== $base_mod );

		// Extract markup from clone.
		$parts = class_exists( 'Align_Gateway_Pages' )
			? Align_Gateway_Pages::extract_decoupled_parts( $clone )
			: array( 'html' => $clone->post_content, 'css' => '', 'js' => '' );

		if ( preg_match( '/<script\b[^>]*>/i', $parts['html'] ) ) {
			return array(
				'success' => false,
				'error'   => 'script_tag_forbidden',
				'message' => __( 'Raw <script> tags are strictly forbidden.', 'align-agent' ),
			);
		}

		if ( class_exists( 'Align_Gateway_Validator' ) ) {
			$val = Align_Gateway_Validator::validate_markup( $parts['html'], $parts['css'] );
			if ( empty( $val['valid'] ) ) {
				return array(
					'success' => false,
					'error'   => 'validation_failed',
					'message' => $val['message'] ?? __( 'Markup validation failed on clone.', 'align-agent' ),
				);
			}
		}

		// Audit Log snapshot before mutation.
		if ( class_exists( 'Align_Agent_Audit_Log' ) ) {
			$orig_parts = Align_Gateway_Pages::extract_decoupled_parts( $original );
			Align_Agent_Audit_Log::log_action(
				get_current_user_id(),
				'apply_page_clone',
				$orig_id,
				'update',
				array( 'clone_id' => $clone_id, 'preview_snippet' => mb_substr( $orig_parts['html'], 0, 300, 'UTF-8' ) )
			);
		}

		// Apply clone content into original live post (retaining original status and slug).
		if ( class_exists( 'Align_Builder_Crud_Handler' ) ) {
			$save_req = new WP_REST_Request( 'POST', '/align-builder/v1/save-page' );
			foreach ( array( 'post_id' => $orig_id, 'html' => $parts['html'], 'css' => $parts['css'], 'js' => $parts['js'], 'status' => $original->post_status ) as $k => $v ) {
				$save_req->set_param( $k, $v );
			}
			$save_res = Align_Builder_Crud_Handler::save_page( $save_req );
			if ( is_wp_error( $save_res ) ) {
				return array(
					'success' => false,
					'error'   => $save_res->get_error_code(),
					'message' => $save_res->get_error_message(),
				);
			}
		} else {
			wp_update_post( array( 'ID' => $orig_id, 'post_content' => $parts['html'] ) );
		}

		// Trash clone post and clean physical assets.
		wp_trash_post( $clone_id );
		self::cleanup_clone_assets( $clone );

		$msg = sprintf( __( 'Applied changes from draft clone #%1$d to live page #%2$d successfully.', 'align-agent' ), $clone_id, $orig_id );
		if ( $base_changed ) {
			$msg .= ' ' . __( 'Warning: Original page was modified while the draft was pending.', 'align-agent' );
		}

		$url = (string) get_permalink( $orig_id );
		return array(
			'success'      => true,
			'post_id'      => $orig_id,
			'clone_id'     => $clone_id,
			'base_changed' => $base_changed,
			'url'          => $url,
			'message'      => $msg,
			'ui_card'      => array(
				'title'   => sprintf( __( 'Applied Clone #%1$d to Page #%2$d', 'align-agent' ), $clone_id, $orig_id ),
				'message' => $msg,
				'url'     => $url,
			),
		);
	}

	/**
	 * Discard a draft clone and clean up its temporary assets.
	 *
	 * @param int $clone_id Draft clone post ID.
	 * @return array
	 */
	public static function discard( int $clone_id ): array {
		$clone = get_post( $clone_id );
		if ( ! $clone ) {
			return array(
				'success' => false,
				'error'   => 'not_found',
				'message' => __( 'Clone post not found.', 'align-agent' ),
			);
		}

		$orig_id = absint( get_post_meta( $clone_id, self::META_CLONE_OF, true ) );
		wp_trash_post( $clone_id );
		self::cleanup_clone_assets( $clone );

		return array(
			'success'  => true,
			'clone_id' => $clone_id,
			'message'  => sprintf( __( 'Discarded draft clone #%1$d for page #%2$d.', 'align-agent' ), $clone_id, $orig_id ),
		);
	}

	/**
	 * Record a clone ID in the user's current session for post-turn approval queueing.
	 *
	 * @param int $user_id  WordPress user ID.
	 * @param int $clone_id Clone post ID.
	 */
	public static function record_session_clone( int $user_id, int $clone_id ): void {
		if ( $user_id <= 0 || $clone_id <= 0 ) {
			return;
		}

		$key    = self::SESSION_KEY_PREFIX . $user_id;
		$clones = get_option( $key, array() );
		if ( ! is_array( $clones ) ) {
			$clones = array();
		}

		if ( ! in_array( $clone_id, $clones, true ) ) {
			$clones[] = $clone_id;
			update_option( $key, array_values( $clones ), 'no' );
		}
	}

	/**
	 * Retrieve all clone IDs recorded in the user's active session.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return int[]
	 */
	public static function get_session_clones( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}

		$clones = get_option( self::SESSION_KEY_PREFIX . $user_id, array() );
		return is_array( $clones ) ? array_map( 'intval', $clones ) : array();
	}

	/**
	 * Clear recorded session clones for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 */
	public static function clear_session_clones( int $user_id ): void {
		if ( $user_id > 0 ) {
			delete_option( self::SESSION_KEY_PREFIX . $user_id );
		}
	}

	/**
	 * Clean physical CSS/JS asset files written for a temporary clone.
	 *
	 * @param WP_Post $clone Clone post object.
	 */
	private static function cleanup_clone_assets( WP_Post $clone ): void {
		$slug       = sanitize_title( $clone->post_name );
		$upload_dir = wp_upload_dir();
		$paths      = array(
			get_template_directory() . "/assets/builder/style-page-{$slug}.css",
			get_template_directory() . "/assets/builder/script-page-{$slug}.js",
			trailingslashit( $upload_dir['basedir'] ) . "align-builder/style-page-{$slug}.css",
			trailingslashit( $upload_dir['basedir'] ) . "align-builder/script-page-{$slug}.js",
		);

		foreach ( $paths as $path ) {
			if ( file_exists( $path ) ) {
				@unlink( $path );
			}
		}
	}
}
