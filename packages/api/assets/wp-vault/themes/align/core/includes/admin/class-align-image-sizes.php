<?php
/**
 * Media Image Sizes Manager for Align theme.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Image_Sizes {

	public static function get_standard_sizes_definitions() {
		return array(
			'thumbnail'    => array(
				'label'        => __( 'Thumbnail', 'align' ),
				'default_w'    => 150,
				'default_h'    => 150,
				'default_crop' => 0,
			),
			'medium'       => array(
				'label'        => __( 'Medium', 'align' ),
				'default_w'    => 300,
				'default_h'    => 300,
				'default_crop' => 0,
			),
			'medium_large' => array(
				'label'        => __( 'Medium Large', 'align' ),
				'default_w'    => 768,
				'default_h'    => 0,
				'default_crop' => 0,
			),
			'large'        => array(
				'label'        => __( 'Large', 'align' ),
				'default_w'    => 1024,
				'default_h'    => 1024,
				'default_crop' => 0,
			),
			'1536x1536'    => array(
				'label'        => __( '1536x1536 (2x Medium Large)', 'align' ),
				'default_w'    => 1536,
				'default_h'    => 1536,
				'default_crop' => 0,
			),
			'2048x2048'    => array(
				'label'        => __( '2048x2048 (2x Large)', 'align' ),
				'default_w'    => 2048,
				'default_h'    => 2048,
				'default_crop' => 0,
			),
		);
	}

	public static function init() {
		add_action( 'after_setup_theme', array( __CLASS__, 'register_custom_image_sizes' ) );
		add_filter( 'image_size_names_choose', array( __CLASS__, 'add_custom_sizes_to_choose' ) );
		add_action( 'init', array( __CLASS__, 'sync_standard_sizes_to_wp_core' ) );
		add_filter( 'intermediate_image_sizes_advanced', array( __CLASS__, 'filter_intermediate_image_sizes' ) );
		add_action( 'wp_ajax_align_delete_image_size', array( __CLASS__, 'ajax_delete_image_size' ) );
		add_action( 'wp_ajax_align_add_custom_image_size', array( __CLASS__, 'ajax_add_custom_image_size' ) );
		add_action( 'wp_ajax_align_get_attachment_sizes', array( __CLASS__, 'ajax_get_attachment_sizes' ) );

		$standard_sizes = array( 'thumbnail', 'medium', 'medium_large', 'large', '1536x1536', '2048x2048' );
		foreach ( $standard_sizes as $size_name ) {
			add_action( "update_option_align_size_{$size_name}_w", function( $old_val, $new_val ) use ( $size_name ) {
				update_option( "{$size_name}_size_w", absint( $new_val ) );
			}, 10, 2 );
			add_action( "update_option_align_size_{$size_name}_h", function( $old_val, $new_val ) use ( $size_name ) {
				update_option( "{$size_name}_size_h", absint( $new_val ) );
			}, 10, 2 );
			add_action( "update_option_align_size_{$size_name}_crop", function( $old_val, $new_val ) use ( $size_name ) {
				update_option( "{$size_name}_crop", ! empty( $new_val ) ? 1 : 0 );
			}, 10, 2 );
		}
	}

	/**
	 * AJAX endpoint to add a new custom image size.
	 */
	public static function ajax_add_custom_image_size() {
		check_ajax_referer( 'align_settings_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized permission.', 'align' ) ) );
		}

		$label  = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$width  = isset( $_POST['width'] ) ? absint( $_POST['width'] ) : 0;
		$height = isset( $_POST['height'] ) ? absint( $_POST['height'] ) : 0;
		$crop   = ! empty( $_POST['crop'] ) ? 1 : 0;

		if ( empty( $label ) ) {
			wp_send_json_error( array( 'message' => __( 'Please provide a display label for the image size.', 'align' ) ) );
		}

		if ( 0 === $width && 0 === $height ) {
			wp_send_json_error( array( 'message' => __( 'Width or Height must be greater than 0.', 'align' ) ) );
		}

		$name = sanitize_key( str_replace( ' ', '_', strtolower( $label ) ) );
		if ( empty( $name ) ) {
			$name = 'size_' . time();
		}

		// Check reserved standard sizes
		$reserved = array( 'thumbnail', 'medium', 'medium_large', 'large', '1536x1536', '2048x2048', 'full' );
		if ( in_array( $name, $reserved, true ) ) {
			wp_send_json_error( array( 'message' => sprintf( __( 'Size name "%s" is reserved by WordPress. Please choose another name.', 'align' ), $name ) ) );
		}

		$custom_sizes = get_option( 'align_custom_image_sizes', array() );
		if ( ! is_array( $custom_sizes ) ) {
			$custom_sizes = array();
		}

		// Check duplicate size slug
		foreach ( $custom_sizes as $existing ) {
			if ( isset( $existing['name'] ) && $existing['name'] === $name ) {
				wp_send_json_error( array( 'message' => sprintf( __( 'A size with name "%s" already exists.', 'align' ), $name ) ) );
			}
		}

		$new_size = array(
			'name'   => $name,
			'label'  => $label,
			'width'  => $width,
			'height' => $height,
			'crop'   => $crop,
		);

		$custom_sizes[] = $new_size;
		update_option( 'align_custom_image_sizes', $custom_sizes );
		add_image_size( $name, $width, $height, (bool) $crop );

		error_log( sprintf( '[Align MediaSizes] Custom size added via AJAX: %s (%dx%d, crop=%d)', $name, $width, $height, $crop ) );

		wp_send_json_success( array(
			'message' => sprintf( __( 'Custom size "%s" added successfully.', 'align' ), $label ),
			'size'    => $new_size,
			'index'   => count( $custom_sizes ) - 1,
		) );
	}

	public static function ajax_delete_image_size() {
		check_ajax_referer( 'align_settings_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized permission.', 'align' ) ) );
		}

		$size_type = isset( $_POST['size_type'] ) ? sanitize_key( $_POST['size_type'] ) : 'standard';
		$size_name = isset( $_POST['size_name'] ) ? sanitize_key( $_POST['size_name'] ) : '';

		if ( empty( $size_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid size identifier.', 'align' ) ) );
		}

		if ( 'standard' === $size_type ) {
			$opts = self::get_size_option_names( $size_name );
			update_option( $opts['w'], 0 );
			update_option( $opts['h'], 0 );
			update_option( $opts['crop'], 0 );
			update_option( "{$size_name}_size_w", 0 );
			update_option( "{$size_name}_size_h", 0 );
			update_option( "{$size_name}_crop", 0 );

			if ( in_array( $size_name, array( '1536x1536', '2048x2048' ), true ) ) {
				remove_image_size( $size_name );
			}

			error_log( '[Align MediaSizes] Standard size deleted via AJAX: ' . $size_name );
			wp_send_json_success( array(
				'message'   => sprintf( __( 'Standard size %s deleted successfully.', 'align' ), $size_name ),
				'size_name' => $size_name,
			) );
		} else {
			$custom_sizes = get_option( 'align_custom_image_sizes', array() );
			$updated = array();

			if ( is_array( $custom_sizes ) ) {
				foreach ( $custom_sizes as $size ) {
					$cur_name = isset( $size['name'] ) ? sanitize_key( $size['name'] ) : '';
					if ( $cur_name === $size_name ) {
						continue;
					}
					$updated[] = $size;
				}
			}

			update_option( 'align_custom_image_sizes', $updated );
			remove_image_size( $size_name );

			error_log( '[Align MediaSizes] Custom size deleted via AJAX: ' . $size_name );
			wp_send_json_success( array(
				'message'   => sprintf( __( 'Custom size %s deleted successfully.', 'align' ), $size_name ),
				'size_name' => $size_name,
			) );
		}
	}

	public static function get_size_option_names( $name ) {
		return array(
			'w'    => 'align_size_' . $name . '_w',
			'h'    => 'align_size_' . $name . '_h',
			'crop' => 'align_size_' . $name . '_crop',
		);
	}

	public static function sync_standard_sizes_to_wp_core() {
		$definitions = self::get_standard_sizes_definitions();
		foreach ( $definitions as $size_name => $def ) {
			$opts       = self::get_size_option_names( $size_name );
			$align_w    = get_option( $opts['w'], null );
			$align_h    = get_option( $opts['h'], null );
			$align_crop = get_option( $opts['crop'], null );

			if ( null !== $align_w ) {
				$core_w = get_option( "{$size_name}_size_w", null );
				if ( (int) $core_w !== (int) $align_w ) {
					update_option( "{$size_name}_size_w", absint( $align_w ) );
				}
			}
			if ( null !== $align_h ) {
				$core_h = get_option( "{$size_name}_size_h", null );
				if ( (int) $core_h !== (int) $align_h ) {
					update_option( "{$size_name}_size_h", absint( $align_h ) );
				}
			}
			$core_crop   = get_option( "{$size_name}_crop", null );
			$target_crop = ( null !== $align_crop ) ? ( ! empty( $align_crop ) ? 1 : 0 ) : ( ! empty( $def['default_crop'] ) ? 1 : 0 );
			if ( null !== $core_crop && (int) $core_crop !== $target_crop ) {
				update_option( "{$size_name}_crop", $target_crop );
				error_log( sprintf( '[Align MediaSizes] Synced crop setting for %s: core_crop was %d, set to %d', $size_name, (int) $core_crop, $target_crop ) );
			}

			// Handle removing 1536x1536 and 2048x2048 if width/height are 0
			if ( in_array( $size_name, array( '1536x1536', '2048x2048' ), true ) ) {
				$w = (int) ( null !== $align_w ? $align_w : get_option( "{$size_name}_size_w", $def['default_w'] ) );
				$h = (int) ( null !== $align_h ? $align_h : get_option( "{$size_name}_size_h", $def['default_h'] ) );
				if ( 0 === $w && 0 === $h ) {
					remove_image_size( $size_name );
				}
			}
		}
	}

	public static function filter_intermediate_image_sizes( $sizes ) {
		$definitions = self::get_standard_sizes_definitions();
		foreach ( $definitions as $size_name => $def ) {
			$opts = self::get_size_option_names( $size_name );
			$w    = get_option( $opts['w'], get_option( "{$size_name}_size_w", null ) );
			$h    = get_option( $opts['h'], get_option( "{$size_name}_size_h", null ) );

			// If both width and height are 0 (user deleted/disabled this size)
			if ( null !== $w && null !== $h && 0 === (int) $w && 0 === (int) $h ) {
				unset( $sizes[ $size_name ] );
			}
		}
		return $sizes;
	}

	public static function register_custom_image_sizes() {
		$custom_sizes = get_option( 'align_custom_image_sizes', array() );
		if ( is_array( $custom_sizes ) ) {
			foreach ( $custom_sizes as $size ) {
				$width  = isset( $size['width'] ) ? absint( $size['width'] ) : 0;
				$height = isset( $size['height'] ) ? absint( $size['height'] ) : 0;
				$name   = isset( $size['name'] ) ? sanitize_key( $size['name'] ) : '';

				if ( ! empty( $name ) && ( $width > 0 || $height > 0 ) ) {
					add_image_size(
						$name,
						$width,
						$height,
						! empty( $size['crop'] )
					);
				}
			}
		}
	}

	public static function add_custom_sizes_to_choose( $sizes ) {
		$custom_sizes = get_option( 'align_custom_image_sizes', array() );
		if ( is_array( $custom_sizes ) ) {
			foreach ( $custom_sizes as $size ) {
				if ( ! empty( $size['name'] ) ) {
					$sizes[ sanitize_key( $size['name'] ) ] = esc_html( $size['label'] ?? $size['name'] );
				}
			}
		}
		return $sizes;
	}

	/**
	 * Retrieve all registered and generated image sizes with width, height, and URL for a given attachment ID.
	 *
	 * @param int $attachment_id WordPress attachment ID.
	 * @return array|null Metadata array or null on failure.
	 */
	public static function get_attachment_sizes_data( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
			return null;
		}

		$meta        = wp_get_attachment_metadata( $attachment_id );
		$full_src    = wp_get_attachment_image_src( $attachment_id, 'full' );
		$full_url    = $full_src ? $full_src[0] : wp_get_attachment_url( $attachment_id );
		$full_width  = $full_src ? (int) $full_src[1] : ( isset( $meta['width'] ) ? (int) $meta['width'] : 0 );
		$full_height = $full_src ? (int) $full_src[2] : ( isset( $meta['height'] ) ? (int) $meta['height'] : 0 );

		$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( empty( $alt ) ) {
			$attachment_post = get_post( $attachment_id );
			$alt             = $attachment_post ? $attachment_post->post_title : '';
		}

		$sizes     = array();
		$seen_urls = array();

		// 1. Loop through all sub-sizes in metadata
		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size_name => $size_info ) {
				$src = wp_get_attachment_image_src( $attachment_id, $size_name );
				if ( $src && ! empty( $src[0] ) ) {
					$url = $src[0];
					$w   = ! empty( $size_info['width'] ) ? (int) $size_info['width'] : (int) $src[1];
					$h   = ! empty( $size_info['height'] ) ? (int) $size_info['height'] : (int) $src[2];
					if ( $w > 0 && ! isset( $seen_urls[ $url ] ) ) {
						$seen_urls[ $url ] = true;
						$sizes[]           = array(
							'name'   => $size_name,
							'url'    => $url,
							'width'  => $w,
							'height' => $h,
						);
					}
				}
			}
		}

		// 2. Add full size if not already included
		if ( $full_url && $full_width > 0 && ! isset( $seen_urls[ $full_url ] ) ) {
			$seen_urls[ $full_url ] = true;
			$sizes[]                = array(
				'name'   => 'full',
				'url'    => $full_url,
				'width'  => $full_width,
				'height' => $full_height,
			);
		}

		// 3. Sort ascending by width for clean srcset output
		usort( $sizes, function( $a, $b ) {
			return $a['width'] - $b['width'];
		} );

		return array(
			'id'          => $attachment_id,
			'full_url'    => $full_url,
			'width'       => $full_width,
			'height'      => $full_height,
			'alt'         => $alt,
			'sizes'       => $sizes,
			'total_sizes' => count( $sizes ),
		);
	}

	/**
	 * REST API endpoint callback for attachment sizes.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public static function rest_get_attachment_sizes( $request ) {
		$id   = absint( $request->get_param( 'id' ) );
		$data = self::get_attachment_sizes_data( $id );
		if ( ! $data ) {
			return new WP_Error( 'not_found', __( 'Attachment not found or not an image.', 'align' ), array( 'status' => 404 ) );
		}
		error_log( sprintf( '[Align MediaSizes] REST attachment sizes fetched: ID=%d, Total Sizes=%d', $id, $data['total_sizes'] ) );
		return rest_ensure_response( $data );
	}

	/**
	 * AJAX endpoint callback for attachment sizes.
	 */
	public static function ajax_get_attachment_sizes() {
		$id   = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
		$data = self::get_attachment_sizes_data( $id );
		if ( ! $data ) {
			wp_send_json_error( array( 'message' => __( 'Attachment not found or not an image.', 'align' ) ), 404 );
		}
		error_log( sprintf( '[Align MediaSizes] AJAX attachment sizes fetched: ID=%d, Total Sizes=%d', $id, $data['total_sizes'] ) );
		wp_send_json_success( $data );
	}
}

if ( ! function_exists( 'align_get_image_size_option_names' ) ) {
	function align_get_image_size_option_names( $name ) {
		return Align_Image_Sizes::get_size_option_names( $name );
	}
}
