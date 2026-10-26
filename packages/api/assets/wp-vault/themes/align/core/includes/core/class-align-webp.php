<?php
/**
 * WebP Conversion and Media Optimization engine for Align theme.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_WebP {

	/** @var bool Fast-fail circuit breaker if cloud API is unreachable */
	private static $cloud_offline = false;

	public static function init() {
		add_filter( 'wp_generate_attachment_metadata', [ __CLASS__, 'handle_attachment_upload' ], 10, 2 );
		add_filter( 'image_editor_output_format', [ __CLASS__, 'filter_image_editor_output_format' ], 10, 3 );
		add_action( 'wp_ajax_align_webp_bulk_status', [ __CLASS__, 'ajax_bulk_status' ] );
		add_action( 'wp_ajax_align_webp_bulk_batch', [ __CLASS__, 'ajax_bulk_batch' ] );
		add_action( 'wp_ajax_align_webp_test_endpoint', [ __CLASS__, 'ajax_test_endpoint' ] );
	}

	public static function is_auto_convert_enabled() {
		return (bool) get_option( 'align_webp_enable_auto_convert', 1 );
	}

	public static function get_quality() {
		$quality = (int) get_option( 'align_webp_quality', 80 );
		return max( 1, min( 100, $quality ) );
	}

	public static function get_clean_webp_path( $file_path ) {
		$dir  = dirname( $file_path );
		$name = pathinfo( $file_path, PATHINFO_FILENAME );
		if ( empty( $dir ) || '.' === $dir ) {
			return $name . '.webp';
		}
		return $dir . '/' . $name . '.webp';
	}

	/**
	 * Map raster image mime types to WebP for core image editor and collision checking.
	 *
	 * Enables WordPress core (wp_unique_filename) to automatically detect collision with existing
	 * .webp files on disk when a new image is uploaded.
	 *
	 * @param array  $output_format Current mime type mappings.
	 * @param string $filename      Path to the file being processed.
	 * @param string $mime_type     Mime type of the source file.
	 * @return array Modified mime type mappings.
	 */
	public static function filter_image_editor_output_format( $output_format, $filename = '', $mime_type = '' ) {
		if ( ! self::is_auto_convert_enabled() ) {
			return $output_format;
		}

		if ( ! is_array( $output_format ) ) {
			$output_format = [];
		}

		$output_format['image/jpeg'] = 'image/webp';
		$output_format['image/png']  = 'image/webp';
		$output_format['image/gif']  = 'image/webp';

		return $output_format;
	}

	/**
	 * Check if a file path or its relative path is already claimed by another attachment in DB.
	 *
	 * @param string $file_path             Absolute or relative file path.
	 * @param int    $current_attachment_id Attachment ID to exclude.
	 * @return bool True if claimed by another attachment, false otherwise.
	 */
	public static function is_file_claimed_by_other( $file_path, $current_attachment_id = 0 ) {
		global $wpdb;

		if ( empty( $file_path ) ) {
			return false;
		}

		$upload_dir = wp_upload_dir( null, false, true );
		$basedir    = trailingslashit( wp_normalize_path( $upload_dir['basedir'] ) );
		$norm_path  = wp_normalize_path( $file_path );

		if ( str_starts_with( $norm_path, $basedir ) ) {
			$rel_path = substr( $norm_path, strlen( $basedir ) );
		} else {
			$rel_path = ltrim( str_replace( $basedir, '', $norm_path ), '/\\' );
		}

		$sql = "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND (meta_value = %s OR meta_value = %s)";
		$params = [ $rel_path, basename( $file_path ) ];

		if ( ! empty( $current_attachment_id ) ) {
			$sql .= ' AND post_id != %d';
			$params[] = (int) $current_attachment_id;
		}

		$sql .= ' LIMIT 1';

		$found_id = $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
		return ! empty( $found_id );
	}

	public static function convert_file_to_webp( $file_path, $quality = 80, $force = false, $attachment_id = 0 ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			error_log( '[Align WebP] WebP conversion skipped: file does not exist or unreadable (' . basename( $file_path ) . ')' );
			return false;
		}

		$mime = wp_check_filetype( $file_path )['type'];
		$supported_mimes = [ 'image/jpeg', 'image/jpg', 'image/png', 'image/gif' ];

		if ( ! in_array( $mime, $supported_mimes, true ) ) {
			return false;
		}

		$webp_path = self::get_clean_webp_path( $file_path );

		// Safe cache check: bypass conversion ONLY when not force and target file is not claimed by another attachment
		if ( ! $force && file_exists( $webp_path ) && filemtime( $webp_path ) >= filemtime( $file_path ) ) {
			if ( ! self::is_file_claimed_by_other( $webp_path, $attachment_id ) ) {
				return $webp_path;
			}
		}

		// Read binary image file
		$raw_data = file_get_contents( $file_path );
		if ( empty( $raw_data ) ) {
			error_log( '[Align WebP] Empty file contents for: ' . basename( $file_path ) );
			return false;
		}

		// 1. Try Cloud Microservices API if not marked offline
		if ( ! self::$cloud_offline ) {
			$base64_data = 'data:' . $mime . ';base64,' . base64_encode( $raw_data );

			$raw_domain   = function_exists( 'align_get_api_converter_url' ) ? align_get_api_converter_url() : 'https://convertb.alignvn.cloud';
			$clean_domain = preg_replace( '#/api/(convert-image-to-webp|convert-image|convert|minify|unminify|scss2css|scss-to-css|sass-to-css)$#i', '', rtrim( trim( $raw_domain ), '/' ) );
			$endpoint     = $clean_domain . '/api/convert-image-to-webp';

			$payload = [
				'image'   => $base64_data,
				'quality' => (int) $quality,
			];

			$response = wp_remote_post( $endpoint, [
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => wp_json_encode( $payload ),
				'timeout' => 8,
			] );

			if ( is_wp_error( $response ) ) {
				self::$cloud_offline = true;
				error_log( sprintf( '[Align WebP] WebP Cloud API unreachable (%s) | File: %s | Preserving original untouched image.', $response->get_error_message(), basename( $file_path ) ) );
			} else {
				$status_code = wp_remote_retrieve_response_code( $response );
				if ( 200 !== $status_code ) {
					self::$cloud_offline = true;
					error_log( sprintf( '[Align WebP] WebP Cloud API returned HTTP %d | File: %s | Preserving original untouched image.', $status_code, basename( $file_path ) ) );
				} else {
					$body = wp_remote_retrieve_body( $response );
					$json_data = json_decode( $body, true );
					if ( is_array( $json_data ) && ! empty( $json_data['success'] ) ) {
						$webp_base64 = ! empty( $json_data['base64'] ) ? $json_data['base64'] : ( ! empty( $json_data['data'] ) ? $json_data['data'] : '' );
						if ( ! empty( $webp_base64 ) ) {
							$raw_bytes = base64_decode( preg_replace( '#^data:image/\w+;base64,#i', '', $webp_base64 ) );
							if ( $raw_bytes ) {
								// Safe collision guard: if target WebP exists and is claimed by another attachment, generate unique name
								if ( file_exists( $webp_path ) ) {
									$dir       = dirname( $webp_path );
									$base_name = basename( $webp_path );
									$claimed   = self::is_file_claimed_by_other( $webp_path, $attachment_id );

									if ( $claimed || $force ) {
										$unique_name = wp_unique_filename( $dir, $base_name );
										if ( $unique_name !== $base_name ) {
											error_log( sprintf( '[Align WebP] Collision avoided: %s -> %s (Attachment ID: %d)', $base_name, $unique_name, $attachment_id ) );
											$webp_path = $dir . '/' . $unique_name;
										}
									}
								}

								if ( false !== file_put_contents( $webp_path, $raw_bytes ) ) {
									error_log( sprintf( '[Align WebP] WebP converted via Cloud API: %s (%d bytes)', basename( $webp_path ), strlen( $raw_bytes ) ) );
									return $webp_path;
								}
							}
						}
					}
					$err_msg = is_array( $json_data ) && ! empty( $json_data['error'] ) ? $json_data['error'] : 'Invalid response payload';
					error_log( sprintf( '[Align WebP] WebP conversion failed on Cloud API: %s | Preserving original untouched: %s', $err_msg, basename( $file_path ) ) );
				}
			}
		}

		// 2. Pure Passthrough (Option A): Zero PHP GD processing to prevent quality loss or CPU spike
		error_log( sprintf( '[Align WebP] WebP conversion bypassed (Cloud API offline). Preserving original untouched image: %s', basename( $file_path ) ) );
		return false;
	}

	public static function convert_attachment( $attachment_id, $metadata = null, $is_new_upload = false ) {
		if ( empty( $metadata ) ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );
		}

		$file = get_attached_file( $attachment_id );
		if ( ! $file ) {
			return $metadata;
		}

		$dir       = dirname( $file );
		$quality   = self::get_quality();
		$main_webp = null;

		if ( file_exists( $file ) ) {
			if ( preg_match( '/\.webp$/i', $file ) ) {
				$main_webp = $file;
			} else {
				$main_webp = self::convert_file_to_webp( $file, $quality, $is_new_upload, $attachment_id );
				if ( $main_webp && file_exists( $main_webp ) ) {
					if ( $main_webp !== $file && file_exists( $file ) ) {
						@unlink( $file );
					}
					update_attached_file( $attachment_id, $main_webp );
					wp_update_post( [
						'ID'             => $attachment_id,
						'post_mime_type' => 'image/webp',
					] );
				}
			}
		}

		// Only rewrite metadata if WebP actually succeeded, otherwise preserve original JPG/PNG
		if ( is_array( $metadata ) ) {
			if ( $main_webp && file_exists( $main_webp ) ) {
				if ( ! empty( $metadata['file'] ) ) {
					$meta_dir         = dirname( $metadata['file'] );
					$metadata['file'] = ( '.' === $meta_dir || empty( $meta_dir ) ) ? basename( $main_webp ) : $meta_dir . '/' . basename( $main_webp );
				}
				$metadata['filesize'] = filesize( $main_webp );
			}

			if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $size_key => $size_info ) {
					if ( ! empty( $size_info['file'] ) ) {
						$sub_file      = $size_info['file'];
						$sub_size_path = $dir . '/' . $sub_file;

						$candidate_webp = self::get_clean_webp_path( $sub_size_path );
						if ( file_exists( $candidate_webp ) ) {
							$metadata['sizes'][ $size_key ]['file']      = basename( $candidate_webp );
							$metadata['sizes'][ $size_key ]['mime-type'] = 'image/webp';
							$metadata['sizes'][ $size_key ]['filesize']  = filesize( $candidate_webp );
						} elseif ( file_exists( $sub_size_path ) ) {
							$sub_webp = self::convert_file_to_webp( $sub_size_path, $quality, $is_new_upload, $attachment_id );
							if ( $sub_webp && file_exists( $sub_webp ) ) {
								if ( $sub_webp !== $sub_size_path && file_exists( $sub_size_path ) ) {
									@unlink( $sub_size_path );
								}
								$metadata['sizes'][ $size_key ]['file']      = basename( $sub_webp );
								$metadata['sizes'][ $size_key ]['mime-type'] = 'image/webp';
								$metadata['sizes'][ $size_key ]['filesize']  = filesize( $sub_webp );
							}
						}
					}
				}
			}

			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		return $metadata;
	}

	public static function handle_attachment_upload( $metadata, $attachment_id ) {
		if ( ! self::is_auto_convert_enabled() ) {
			return $metadata;
		}

		return self::convert_attachment( $attachment_id, $metadata, true );
	}

	public static function ajax_bulk_status() {
		$nonce = $_POST['security'] ?? $_POST['align_settings_nonce'] ?? $_POST['nonce'] ?? '';
		if ( ! wp_verify_nonce( $nonce, 'align_settings_nonce' ) && ! wp_verify_nonce( $nonce, 'align_webp_bulk_nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid security token.', 'align' ) ] );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized', 'align' ) ] );
		}

		$query = new WP_Query( [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ],
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		$ids         = $query->posts;
		$total       = count( $ids );
		$converted   = 0;
		$pending_ids = [];

		foreach ( $ids as $id ) {
			$file = get_attached_file( $id );
			$meta = wp_get_attachment_metadata( $id );
			if ( $file ) {
				$is_webp_file   = (bool) preg_match( '/\.webp$/i', $file );
				$all_sizes_webp = true;

				if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
					foreach ( $meta['sizes'] as $sz ) {
						if ( ! empty( $sz['file'] ) && ! preg_match( '/\.webp$/i', $sz['file'] ) ) {
							$all_sizes_webp = false;
							break;
						}
					}
				}

				if ( $is_webp_file && $all_sizes_webp ) {
					$converted++;
				} else {
					$pending_ids[] = (int) $id;
				}
			}
		}

		wp_send_json_success( [
			'total'     => $total,
			'converted' => $converted,
			'pending'   => count( $pending_ids ),
			'ids'       => $pending_ids,
		] );
	}

	public static function ajax_bulk_batch() {
		$nonce = $_POST['security'] ?? $_POST['align_settings_nonce'] ?? $_POST['nonce'] ?? '';
		if ( ! wp_verify_nonce( $nonce, 'align_settings_nonce' ) && ! wp_verify_nonce( $nonce, 'align_webp_bulk_nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid security token.', 'align' ) ] );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized', 'align' ) ] );
		}

		$ids = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : [];
		if ( empty( $ids ) ) {
			wp_send_json_error( [ 'message' => __( 'No attachment IDs provided.', 'align' ) ] );
		}

		// Defense-in-depth: Cap batch size at 2 IDs per request to prevent microservice overload.
		$ids = array_slice( $ids, 0, 2 );

		$processed = 0;
		$errors    = 0;

		foreach ( $ids as $id ) {
			$res = self::convert_attachment( $id );
			if ( $res ) {
				$processed++;
			} else {
				$errors++;
			}
		}

		wp_send_json_success( [
			'processed' => $processed,
			'errors'    => $errors,
		] );
	}

	public static function ajax_test_endpoint() {
		$nonce = $_POST['security'] ?? $_POST['align_settings_nonce'] ?? $_POST['nonce'] ?? '';
		if ( ! wp_verify_nonce( $nonce, 'align_settings_nonce' ) && ! wp_verify_nonce( $nonce, 'align_webp_bulk_nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid security token.', 'align' ) ] );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized', 'align' ) ] );
		}

		$raw_domain   = function_exists( 'align_get_api_converter_url' ) ? align_get_api_converter_url() : 'https://convertb.alignvn.cloud';
		$clean_domain = preg_replace( '#/api/(convert-image-to-webp|convert-image|convert|minify|unminify|scss2css|scss-to-css|sass-to-css)$#i', '', rtrim( trim( $raw_domain ), '/' ) );
		$endpoint     = $clean_domain . '/api/convert-image-to-webp';

		$sample_base64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
		$quality       = self::get_quality();

		$start_time = microtime( true );
		$response   = wp_remote_post( $endpoint, [
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( [
				'image'   => $sample_base64,
				'quality' => $quality,
			] ),
			'timeout' => 15,
		] );
		$latency_ms = round( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( [
				'message'  => $response->get_error_message(),
				'endpoint' => $endpoint,
			] );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$json        = json_decode( $body, true );

		if ( 200 === $status_code && ! empty( $json['success'] ) ) {
			wp_send_json_success( [
				'endpoint'   => $endpoint,
				'latency_ms' => $latency_ms,
				'info'       => isset( $json['info'] ) ? $json['info'] : [],
			] );
		}

		wp_send_json_error( [
			'message'     => isset( $json['error'] ) ? $json['error'] : 'HTTP ' . $status_code,
			'status_code' => $status_code,
			'endpoint'    => $endpoint,
		] );
	}
}
