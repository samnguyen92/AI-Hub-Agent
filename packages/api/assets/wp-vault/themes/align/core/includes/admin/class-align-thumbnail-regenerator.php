<?php
/**
 * Force Thumbnail Regenerator Engine for Align Theme.
 *
 * Deletes old and orphaned thumbnail files and recreates all registered
 * image sub-sizes using WordPress core APIs.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Thumbnail_Regenerator {

	/**
	 * Checkpoint option key for resuming jobs.
	 *
	 * @var string
	 */
	const OPTION_CHECKPOINT = 'align_last_regenerated_id';

	/**
	 * Capability required to run regeneration.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Initialize hooks and filters.
	 */
	public static function init() {
		// AJAX Handlers
		add_action( 'wp_ajax_align_get_regen_ids', array( __CLASS__, 'ajax_get_image_ids' ) );
		add_action( 'wp_ajax_align_regenerate_single_thumbnail', array( __CLASS__, 'ajax_process_image' ) );
		add_action( 'wp_ajax_align_reset_regen_checkpoint', array( __CLASS__, 'ajax_reset_checkpoint' ) );

		// Media Library Actions
		add_filter( 'media_row_actions', array( __CLASS__, 'add_media_row_action' ), 10, 2 );
		add_filter( 'bulk_actions-upload', array( __CLASS__, 'add_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-upload', array( __CLASS__, 'handle_bulk_action' ), 10, 3 );

		// Assets Enqueue
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue admin scripts and styles when on Align Settings or Media Library.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_admin_assets( $hook ) {
		$is_settings_page = ( false !== strpos( $hook, 'align-settings' ) );
		$is_media_page    = ( 'upload.php' === $hook );

		if ( ! $is_settings_page && ! $is_media_page ) {
			return;
		}

		$theme_uri = get_template_directory_uri();
		$version   = defined( 'ALIGN_VERSION' ) ? ALIGN_VERSION : '1.0.0';

		wp_enqueue_style(
			'align-thumbnail-regenerator',
			$theme_uri . '/core/assets/admin/css/align-thumbnail-regenerator.css',
			array(),
			$version
		);

		wp_enqueue_script(
			'align-thumbnail-regenerator',
			$theme_uri . '/core/assets/admin/js/align-thumbnail-regenerator.js',
			array( 'jquery' ),
			$version,
			true
		);

		$checkpoint = (int) get_option( self::OPTION_CHECKPOINT, 0 );

		// Query pending IDs if requested via URL params
		$prefilled_ids = array();
		if ( ! empty( $_GET['align_regen_ids'] ) ) {
			$raw_ids = sanitize_text_field( wp_unslash( $_GET['align_regen_ids'] ) );
			$prefilled_ids = array_filter( array_map( 'intval', explode( ',', $raw_ids ) ) );
		}

		wp_localize_script(
			'align-thumbnail-regenerator',
			'alignRegenData',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'align_thumbnail_regen_nonce' ),
				'checkpoint'    => $checkpoint,
				'prefilledIds'  => array_values( $prefilled_ids ),
				'strings'       => array(
					'confirmStart'    => __( 'Are you sure you want to regenerate all thumbnails? This will recreate all active sizes.', 'align' ),
					'stopping'        => __( 'Stopping current process...', 'align' ),
					'processAborted'  => __( 'Process paused or aborted.', 'align' ),
					'allDone'         => __( 'All thumbnails successfully regenerated!', 'align' ),
					'serverError'     => __( 'Server connection error occurred.', 'align' ),
					'retryFailed'     => __( 'Retry Failed Items', 'align' ),
					'resumingFrom'    => __( 'Resuming from last checkpoint...', 'align' ),
				),
			)
		);
	}

	/**
	 * AJAX endpoint to fetch attachment IDs to be processed.
	 */
	public static function ajax_get_image_ids() {
		check_ajax_referer( 'align_thumbnail_regen_nonce', 'security' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized user capability.', 'align' ) ) );
		}

		global $wpdb;

		$mode        = isset( $_POST['mode'] ) ? sanitize_key( $_POST['mode'] ) : 'all';
		$custom_ids  = isset( $_POST['ids'] ) ? sanitize_text_field( wp_unslash( $_POST['ids'] ) ) : '';
		$image_ids   = array();

		if ( ! empty( $custom_ids ) ) {
			$parsed = array_filter( array_map( 'intval', explode( ',', $custom_ids ) ) );
			$image_ids = array_values( $parsed );
			delete_option( self::OPTION_CHECKPOINT );
		} else {
			$resume_id = ( 'resume' === $mode ) ? (int) get_option( self::OPTION_CHECKPOINT, PHP_INT_MAX ) : PHP_INT_MAX;
			if ( $resume_id <= 0 ) {
				$resume_id = PHP_INT_MAX;
			}

			// Efficient query fetching IDs only
			$sql = $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE ID < %d AND post_type = 'attachment' AND post_mime_type LIKE %s ORDER BY ID DESC",
				$resume_id,
				'image/%'
			);

			$image_ids = array_map( 'intval', $wpdb->get_col( $sql ) );
		}

		error_log( '[Align ThumbnailRegenerator] Prepared ' . count( $image_ids ) . ' image IDs for processing (Mode: ' . $mode . ')' );

		wp_send_json_success( array(
			'ids'   => $image_ids,
			'total' => count( $image_ids ),
		) );
	}

	/**
	 * AJAX endpoint to process a single attachment ID.
	 */
	public static function ajax_process_image() {
		$nonce = isset( $_POST['security'] ) ? sanitize_key( $_POST['security'] ) : '';
		$tick  = wp_verify_nonce( $nonce, 'align_thumbnail_regen_nonce' );

		if ( ! $tick || ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token or unauthorized permissions.', 'align' ) ) );
		}

		$current_nonce = ( 2 === $tick ) ? wp_create_nonce( 'align_thumbnail_regen_nonce' ) : $nonce;

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( empty( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing attachment ID.', 'align' ) ) );
		}

		// Raise memory limit and execution time for heavy image operations
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'image' );
		}
		if ( function_exists( 'set_time_limit' ) && false === strpos( ini_get( 'disable_functions' ), 'set_time_limit' ) ) {
			@set_time_limit( 300 );
		}

		// Concurrency guard per attachment ID
		$lock_key = 'align_regen_lock_' . $id;
		if ( get_transient( $lock_key ) ) {
			wp_send_json_error( array(
				'id'            => $id,
				'message'       => __( 'Image is currently being processed by another task.', 'align' ),
				'current_nonce' => $current_nonce,
			) );
		}
		set_transient( $lock_key, time(), 60 );

		try {
			$post = get_post( $id );
			if ( ! $post || 'attachment' !== $post->post_type || 0 !== strpos( $post->post_mime_type, 'image/' ) ) {
				throw new Exception( sprintf( __( 'ID %d is not a valid image attachment.', 'align' ), $id ) );
			}

			if ( 'image/svg+xml' === $post->post_mime_type ) {
				delete_transient( $lock_key );
				update_option( self::OPTION_CHECKPOINT, $id );
				wp_send_json_success( array(
					'id'            => $id,
					'title'         => get_the_title( $id ),
					'skipped'       => true,
					'message'       => __( 'Skipped (SVG format does not require thumbnails).', 'align' ),
					'current_nonce' => $current_nonce,
				) );
			}

			$meta = wp_get_attachment_metadata( $id );
			$image_fullpath = self::get_attachment_path( $id, $meta );

			if ( empty( $image_fullpath ) || ! file_exists( $image_fullpath ) ) {
				throw new Exception( sprintf( __( 'Original image file not found on disk for ID %d.', 'align' ), $id ) );
			}

			// 1. Delete old metadata sizes and orphaned files
			$deleted_sizes = self::delete_attachment_thumbnails( $id, $image_fullpath, $meta );

			// 2. Regenerate active thumbnail sizes
			$regenerated_sizes = self::regenerate_attachment_thumbnails( $id, $image_fullpath, $meta );

			// 3. Save checkpoint ONLY on success
			update_option( self::OPTION_CHECKPOINT, $id );
			delete_transient( $lock_key );

			error_log( sprintf( '[Align ThumbnailRegenerator] ID %d processed: Deleted [%s], Generated [%s]', $id, implode( ', ', $deleted_sizes ), implode( ', ', $regenerated_sizes ) ) );

			wp_send_json_success( array(
				'id'            => $id,
				'title'         => get_the_title( $id ) ? get_the_title( $id ) : wp_basename( $image_fullpath ),
				'filename'      => wp_basename( $image_fullpath ),
				'deleted'       => $deleted_sizes,
				'generated'     => $regenerated_sizes,
				'current_nonce' => $current_nonce,
			) );

		} catch ( Exception $e ) {
			delete_transient( $lock_key );
			error_log( '[Align ThumbnailRegenerator] Error processing ID ' . $id . ': ' . $e->getMessage() );
			// Note: Do NOT advance checkpoint on error so user can retry/resume accurately
			wp_send_json_error( array(
				'id'            => $id,
				'message'       => $e->getMessage(),
				'current_nonce' => $current_nonce,
			) );
		}
	}

	/**
	 * Delete existing thumbnail files and disk orphans for an attachment.
	 *
	 * @param int    $image_id Attachment ID.
	 * @param string $image_fullpath Absolute path to original image.
	 * @param array  $meta Current attachment metadata.
	 * @return array List of deleted thumbnail size labels.
	 */
	public static function delete_attachment_thumbnails( $image_id, $image_fullpath, $meta ) {
		$deleted   = array();
		$file_info = pathinfo( $image_fullpath );
		$dir       = trailingslashit( $file_info['dirname'] );

		// 1. Delete sizes defined in current metadata
		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size_key => $size_data ) {
				if ( empty( $size_data['file'] ) ) {
					continue;
				}
				$thumb_path = $dir . wp_basename( $size_data['file'] );
				if ( $thumb_path !== $image_fullpath && is_file( $thumb_path ) ) {
					@unlink( $thumb_path );
					if ( is_file( $thumb_path . '.webp' ) ) {
						@unlink( $thumb_path . '.webp' );
					}
					$deleted[] = sprintf( '%dx%d (%s)', $size_data['width'], $size_data['height'], $size_key );
				}
			}
		}

		// 2. Scan directory to delete orphaned thumbnails not in metadata
		$clean_stem = preg_replace( '/-scaled$/', '', $file_info['filename'] );
		$extension  = ! empty( $file_info['extension'] ) ? $file_info['extension'] : '';
		$dh         = @opendir( $file_info['dirname'] );

		if ( false !== $dh && ! empty( $clean_stem ) && ! empty( $extension ) ) {
			// Strict regex: stem followed strictly by -(scaled-)?WIDTHxHEIGHT.ext
			$orphan_regex = '/^' . preg_quote( $clean_stem, '/' ) . '-(?:scaled-)?(\d+)x(\d+)\.' . preg_quote( $extension, '/' ) . '$/i';

			while ( false !== ( $entry = readdir( $dh ) ) ) {
				$thumb_fullpath = $dir . $entry;
				if ( $thumb_fullpath === $image_fullpath ) {
					continue;
				}

				if ( preg_match( $orphan_regex, $entry, $matches ) ) {
					@unlink( $thumb_fullpath );
					if ( is_file( $thumb_fullpath . '.webp' ) ) {
						@unlink( $thumb_fullpath . '.webp' );
					}
					$dim_label = sprintf( '%dx%d (orphan)', $matches[1], $matches[2] );
					if ( ! in_array( $dim_label, $deleted, true ) ) {
						$deleted[] = $dim_label;
					}
				}
			}
			closedir( $dh );
		}

		clearstatcache();
		return $deleted;
	}

	/**
	 * Regenerate new thumbnails for an attachment using WordPress Core APIs.
	 *
	 * @param int    $image_id Attachment ID.
	 * @param string $image_fullpath Absolute path to image.
	 * @param array  $meta Existing attachment metadata.
	 * @return array List of generated size labels.
	 * @throws Exception If generation fails.
	 */
	public static function regenerate_attachment_thumbnails( $image_id, $image_fullpath, $meta ) {
		// Prefer true high-res original image if available (WP 5.3+ Big Image threshold)
		$original_path = '';
		if ( function_exists( 'wp_get_original_image_path' ) ) {
			$original_path = wp_get_original_image_path( $image_id, true );
		}

		$source_path = ( ! empty( $original_path ) && is_file( $original_path ) ) ? $original_path : $image_fullpath;

		// WordPress Core generation helper
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$new_metadata = wp_generate_attachment_metadata( $image_id, $source_path );

		if ( is_wp_error( $new_metadata ) ) {
			throw new Exception( $new_metadata->get_error_message() );
		}
		if ( empty( $new_metadata ) ) {
			throw new Exception( __( 'Failed to generate attachment metadata.', 'align' ) );
		}

		// Preserve original_image reference if applicable
		if ( ! empty( $meta['original_image'] ) && empty( $new_metadata['original_image'] ) ) {
			$new_metadata['original_image'] = $meta['original_image'];
		}

		wp_update_attachment_metadata( $image_id, $new_metadata );

		$generated = array();
		if ( ! empty( $new_metadata['sizes'] ) && is_array( $new_metadata['sizes'] ) ) {
			foreach ( $new_metadata['sizes'] as $size_name => $sdata ) {
				$generated[] = sprintf( '%s (%dx%d)', $size_name, $sdata['width'], $sdata['height'] );
			}
		}

		return $generated;
	}

	/**
	 * Retrieve true absolute path for an attachment.
	 *
	 * @param int   $id Attachment ID.
	 * @param array $meta Attachment metadata.
	 * @return string File absolute path.
	 */
	public static function get_attachment_path( $id, $meta ) {
		$upload_dir  = wp_upload_dir( null, false, true );
		$upload_path = trailingslashit( $upload_dir['basedir'] );

		$file = get_post_meta( $id, '_wp_attached_file', true );
		if ( ! empty( $file ) ) {
			$path = ( 0 !== strpos( $file, '/' ) && ! preg_match( '|^.:\\\|', $file ) ) ? $upload_path . $file : $file;
			$path = apply_filters( 'get_attached_file', $path, $id );
			if ( is_file( $path ) ) {
				return $path;
			}
		}

		if ( is_array( $meta ) && ! empty( $meta['file'] ) ) {
			$path = is_file( $meta['file'] ) ? $meta['file'] : $upload_path . $meta['file'];
			if ( is_file( $path ) ) {
				return $path;
			}
		}

		return '';
	}

	/**
	 * AJAX endpoint to clear resume checkpoint.
	 */
	public static function ajax_reset_checkpoint() {
		check_ajax_referer( 'align_thumbnail_regen_nonce', 'security' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error();
		}
		delete_option( self::OPTION_CHECKPOINT );
		wp_send_json_success();
	}

	/**
	 * Add "Force Regenerate Thumbnails" action in Media Library list row.
	 *
	 * @param array   $actions Current row actions.
	 * @param WP_Post $post Attachment post object.
	 * @return array Modified row actions.
	 */
	public static function add_media_row_action( $actions, $post ) {
		if ( 0 === strpos( $post->post_mime_type, 'image/' ) && current_user_can( self::CAPABILITY ) ) {
			$target_url = add_query_arg(
				array(
					'page'            => 'align-settings',
					'tab'             => 'media',
					'align_regen_ids' => (int) $post->ID,
				),
				admin_url( 'options-general.php' )
			);

			$actions['align_force_regen'] = sprintf(
				'<a href="%s" style="color: #2271b1; font-weight: 600;" title="%s">%s</a>',
				esc_url( $target_url ),
				esc_attr__( 'Force regenerate all thumbnails for this image', 'align' ),
				esc_html__( 'Force Regenerate Thumbnails', 'align' )
			);
		}
		return $actions;
	}

	/**
	 * Add "Force Regenerate Thumbnails" to Media Library Bulk Actions.
	 *
	 * @param array $actions Bulk action options.
	 * @return array Modified options.
	 */
	public static function add_bulk_actions( $actions ) {
		$actions['align_bulk_force_regen'] = __( 'Force Regenerate Thumbnails', 'align' );
		return $actions;
	}

	/**
	 * Handle Media Library Bulk Action submission.
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $action Action key.
	 * @param array  $post_ids Array of attachment IDs.
	 * @return string Modified redirect URL.
	 */
	public static function handle_bulk_action( $redirect_to, $action, $post_ids ) {
		if ( 'align_bulk_force_regen' !== $action || empty( $post_ids ) || ! is_array( $post_ids ) ) {
			return $redirect_to;
		}

		check_admin_referer( 'bulk-media' );
		$ids = implode( ',', array_filter( array_map( 'intval', $post_ids ) ) );

		return add_query_arg(
			array(
				'page'            => 'align-settings',
				'tab'             => 'media',
				'align_regen_ids' => $ids,
			),
			admin_url( 'options-general.php' )
		);
	}
}
