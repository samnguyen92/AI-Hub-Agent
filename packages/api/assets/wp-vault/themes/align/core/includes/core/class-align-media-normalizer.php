<?php
/**
 * Media Filename Normalizer & Auto-Sanitization Engine for Align Theme.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Media_Normalizer {

	/** @var string[] Known compound file extensions */
	private static $compound_extensions = array(
		'tar.gz', 'tar.bz2', 'tar.xz', 'min.css', 'min.js', 'user.ini',
	);

	/** Initialize hooks */
	public static function init() {
		add_filter( 'sanitize_file_name', array( __CLASS__, 'filter_sanitize_file_name' ), 10, 2 );
	}

	/** Filter hook for sanitize_file_name */
	public static function filter_sanitize_file_name( $filename, $filename_raw = '' ) {
		if ( ! apply_filters( 'align_media_normalizer_auto_enable', true ) ) {
			return $filename;
		}
		return self::normalize_filename( $filename );
	}

	/** Normalize a filename to clean, lowercase kebab-case format */
	public static function normalize_filename( $filename ) {
		if ( empty( $filename ) || ! is_string( $filename ) ) {
			return 'file';
		}

		$filename = trim( $filename );
		$ext      = '';
		$stem     = $filename;

		// 1. Identify and extract compound extensions (case-insensitive)
		foreach ( self::$compound_extensions as $compound_ext ) {
			$ext_len = strlen( $compound_ext );
			if ( strlen( $filename ) > $ext_len + 1 ) {
				$suffix = substr( $filename, -( $ext_len + 1 ) );
				if ( 0 === strcasecmp( $suffix, '.' . $compound_ext ) ) {
					$ext  = strtolower( $compound_ext );
					$stem = substr( $filename, 0, -( $ext_len + 1 ) );
					break;
				}
			}
		}

		// 2. Extract standard extension if compound extension was not found
		if ( '' === $ext ) {
			$last_dot = strrpos( $filename, '.' );
			if ( false !== $last_dot && $last_dot > 0 ) {
				$ext  = strtolower( substr( $filename, $last_dot + 1 ) );
				$stem = substr( $filename, 0, $last_dot );
			}
		}

		// 3. Remove accents / diacritics
		if ( function_exists( 'remove_accents' ) ) {
			$stem = remove_accents( $stem );
		} elseif ( function_exists( 'iconv' ) ) {
			$converted = @iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $stem );
			if ( false !== $converted && ! empty( $converted ) ) {
				$stem = $converted;
			}
		}

		// 4. Split CamelCase, replace non-alphanumerics, deduplicate hyphens, lowercase
		$stem = preg_replace( '/([A-Z]+)([A-Z][a-z])/', '$1-$2', $stem );
		$stem = preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $stem );
		$stem = preg_replace( '/[^a-zA-Z0-9\-]+/u', '-', $stem );
		$stem = preg_replace( '/-+/', '-', $stem );
		$stem = strtolower( trim( $stem, '-' ) );

		if ( '' === $stem ) {
			$stem = 'file';
		}

		return '' !== $ext ? ( $stem . '.' . $ext ) : $stem;
	}

	/** Safe atomic file rename supporting Windows NTFS case-only renaming and collision guard */
	public static function safe_rename( $old_path, $new_path ) {
		if ( empty( $old_path ) || empty( $new_path ) || ! file_exists( $old_path ) ) {
			return false;
		}

		$real_old = str_replace( '\\', '/', realpath( $old_path ) ? realpath( $old_path ) : $old_path );
		$real_new = str_replace( '\\', '/', $new_path );

		if ( $real_old === $real_new ) {
			return true;
		}

		// Case-only rename detection on case-insensitive filesystems (Windows NTFS / macOS)
		if ( 0 === strcasecmp( $real_old, $real_new ) ) {
			$dir      = dirname( $old_path );
			$base     = basename( $old_path );
			$tmp_path = $dir . '/.tmp_case_' . uniqid() . '_' . $base;

			if ( @rename( $old_path, $tmp_path ) ) {
				if ( @rename( $tmp_path, $new_path ) ) {
					clearstatcache( true, $new_path );
					return true;
				}
				@rename( $tmp_path, $old_path );
			}
			return false;
		}

		// Prevent collision if destination file already exists
		if ( file_exists( $new_path ) ) {
			return false;
		}

		$success = @rename( $old_path, $new_path );
		clearstatcache( true, $new_path );
		return $success;
	}

	/** Rename companion WebP files associated with a given image file */
	public static function rename_companion_webp( $old_path, $new_path ) {
		$dir_old  = dirname( $old_path );
		$dir_new  = dirname( $new_path );
		$stem_old = pathinfo( $old_path, PATHINFO_FILENAME );
		$stem_new = pathinfo( $new_path, PATHINFO_FILENAME );

		// Format A: file.ext.webp | Format B: file.webp
		$webp_a_old = $old_path . '.webp';
		$webp_a_new = $new_path . '.webp';
		if ( file_exists( $webp_a_old ) && $webp_a_old !== $webp_a_new ) {
			self::safe_rename( $webp_a_old, $webp_a_new );
		}

		$webp_b_old = $dir_old . '/' . $stem_old . '.webp';
		$webp_b_new = $dir_new . '/' . $stem_new . '.webp';
		if ( file_exists( $webp_b_old ) && $webp_b_old !== $webp_b_new ) {
			self::safe_rename( $webp_b_old, $webp_b_new );
		}
	}

	/**
	 * Check if a relative attachment path is claimed by another attachment in database.
	 *
	 * @param string $rel_path              Relative path (e.g. '2026/09/image.jpg').
	 * @param int    $current_attachment_id Current attachment ID to exclude.
	 * @return bool True if claimed by another attachment, false otherwise.
	 */
	public static function is_target_claimed( $rel_path, $current_attachment_id = 0 ) {
		global $wpdb;

		if ( empty( $rel_path ) ) {
			return false;
		}

		$sql    = "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s";
		$params = array( $rel_path );

		if ( ! empty( $current_attachment_id ) ) {
			$sql     .= ' AND post_id != %d';
			$params[] = (int) $current_attachment_id;
		}

		$sql .= ' LIMIT 1';

		$existing_id = $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
		return ! empty( $existing_id );
	}

	/** Thoroughly normalize an attachment's files, sub-sizes, companion WebP, and database records */
	public static function normalize_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( empty( $attachment_id ) ) {
			return new WP_Error( 'invalid_id', __( 'Invalid attachment ID.', 'align' ) );
		}

		$post = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return new WP_Error( 'not_attachment', __( 'Specified ID is not an attachment.', 'align' ) );
		}

		$attached_file = get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( empty( $attached_file ) ) {
			return new WP_Error( 'missing_attached_file', __( 'No attached file metadata found.', 'align' ) );
		}

		$upload_dir    = wp_upload_dir( null, false, true );
		$basedir       = trailingslashit( $upload_dir['basedir'] );
		$baseurl       = trailingslashit( $upload_dir['baseurl'] );
		$old_main_path = ( 0 !== strpos( $attached_file, '/' ) && ! preg_match( '|^.:\\\|', $attached_file ) ) ? $basedir . $attached_file : $attached_file;

		if ( ! file_exists( $old_main_path ) ) {
			return new WP_Error( 'file_not_found', sprintf( __( 'File not found on disk: %s', 'align' ), $attached_file ) );
		}

		$dir           = dirname( $old_main_path );
		$rel_dir       = dirname( $attached_file );
		$rel_prefix    = ( '.' === $rel_dir || empty( $rel_dir ) ) ? '' : trailingslashit( $rel_dir );
		$old_main_name = wp_basename( $old_main_path );
		$new_main_name = self::normalize_filename( $old_main_name );
		$meta          = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}

		$target_full_path    = $dir . '/' . $new_main_name;
		$target_rel_path     = $rel_prefix . $new_main_name;
		$file_exists_on_disk = file_exists( $target_full_path );
		$is_same_file        = false;

		if ( $file_exists_on_disk ) {
			$real_old = realpath( $old_main_path );
			$real_new = realpath( $target_full_path );
			if ( $real_old && $real_new && 0 === strcasecmp( str_replace( '\\', '/', $real_old ), str_replace( '\\', '/', $real_new ) ) ) {
				$is_same_file = true;
			}
		}

		$is_claimed = self::is_target_claimed( $target_rel_path, $attachment_id );

		// Check companion WebP collision if WebP auto-convert is active
		$webp_collision = false;
		if ( class_exists( 'Align_WebP' ) && Align_WebP::is_auto_convert_enabled() ) {
			$new_stem        = pathinfo( $new_main_name, PATHINFO_FILENAME );
			$target_webp_rel = $rel_prefix . $new_stem . '.webp';
			$target_webp_dir = $dir . '/' . $new_stem . '.webp';

			if ( file_exists( $target_webp_dir ) ) {
				$is_webp_claimed = self::is_target_claimed( $target_webp_rel, $attachment_id );
				$old_stem        = pathinfo( $old_main_name, PATHINFO_FILENAME );
				if ( $is_webp_claimed || 0 !== strcasecmp( $old_stem, $new_stem ) ) {
					$webp_collision = true;
				}
			}
		}

		// Collision detected if:
		// 1. Target file path is claimed by another attachment in DB.
		// 2. Target file exists on disk and is not the exact physical file.
		// 3. Target companion WebP collides with another attachment.
		$has_collision = $is_claimed || ( $file_exists_on_disk && ! $is_same_file ) || $webp_collision;

		if ( $has_collision && function_exists( 'wp_unique_filename' ) ) {
			$unique_name = wp_unique_filename( $dir, $new_main_name );
			error_log( sprintf(
				'[Align MediaNormalizer] Collision detected for ID %d: proposed "%s" conflicts (claimed=%s, disk_exists=%s, webp_collision=%s). Resolved to unique name: "%s"',
				$attachment_id,
				$new_main_name,
				$is_claimed ? 'yes' : 'no',
				$file_exists_on_disk ? 'yes' : 'no',
				$webp_collision ? 'yes' : 'no',
				$unique_name
			) );
			$new_main_name = $unique_name;
		}

		$renamed_files = array();
		$new_main_path = $dir . '/' . $new_main_name;

		// 1. Rename main attached file - abort immediately on rename failure
		if ( $old_main_path !== $new_main_path ) {
			if ( ! self::safe_rename( $old_main_path, $new_main_path ) ) {
				return new WP_Error( 'rename_failed', sprintf( __( 'Failed to safely rename main file: %s', 'align' ), $old_main_name ) );
			}
			$renamed_files[] = $new_main_name;
			self::rename_companion_webp( $old_main_path, $new_main_path );
		}

		// 2. Handle original_image (WP 5.3+ Big Image threshold)
		if ( ! empty( $meta['original_image'] ) ) {
			$old_orig_name = $meta['original_image'];
			$new_orig_name = self::normalize_filename( $old_orig_name );
			$old_orig_path = $dir . '/' . $old_orig_name;
			$new_orig_path = $dir . '/' . $new_orig_name;

			if ( $old_orig_name === $new_orig_name ) {
				// Already clean
			} elseif ( file_exists( $old_orig_path ) && self::safe_rename( $old_orig_path, $new_orig_path ) ) {
				$renamed_files[] = $new_orig_name;
				self::rename_companion_webp( $old_orig_path, $new_orig_path );
				$meta['original_image'] = $new_orig_name;
			}
		}

		// 3. Handle intermediate image sub-sizes
		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			$new_stem = preg_replace( '/-scaled$/', '', pathinfo( $new_main_name, PATHINFO_FILENAME ) );
			foreach ( $meta['sizes'] as $size_key => $size_data ) {
				if ( empty( $size_data['file'] ) ) {
					continue;
				}
				$old_size_name = $size_data['file'];
				$size_ext      = strtolower( pathinfo( $old_size_name, PATHINFO_EXTENSION ) );
				$new_size_name = ( ! empty( $size_data['width'] ) && ! empty( $size_data['height'] ) )
					? $new_stem . '-' . $size_data['width'] . 'x' . $size_data['height'] . '.' . $size_ext
					: self::normalize_filename( $old_size_name );

				$old_size_path = $dir . '/' . $old_size_name;
				$new_size_path = $dir . '/' . $new_size_name;

				if ( $old_size_name === $new_size_name ) {
					// Already clean
				} elseif ( file_exists( $old_size_path ) && self::safe_rename( $old_size_path, $new_size_path ) ) {
					$renamed_files[] = $new_size_name;
					self::rename_companion_webp( $old_size_path, $new_size_path );
					$meta['sizes'][ $size_key ]['file'] = $new_size_name;
				}
			}
		}

		// 4. Update database metadata & GUID
		$new_rel_attached = $rel_prefix . $new_main_name;
		update_post_meta( $attachment_id, '_wp_attached_file', $new_rel_attached );
		if ( isset( $meta['file'] ) ) {
			$meta['file'] = $new_rel_attached;
		}
		wp_update_attachment_metadata( $attachment_id, $meta );

		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'guid' => $baseurl . $new_rel_attached ), array( 'ID' => $attachment_id ) );
		clean_post_cache( $attachment_id );

		$changed = ( $old_main_name !== $new_main_name ) || ! empty( $renamed_files );
		if ( $changed ) {
			error_log( sprintf( '[Align MediaNormalizer] Normalized ID %d: "%s" -> "%s" (%d files updated)', $attachment_id, $old_main_name, $new_main_name, count( $renamed_files ) ) );
		}

		return array(
			'id'            => $attachment_id,
			'old_filename'  => $old_main_name,
			'new_filename'  => $new_main_name,
			'changed'       => $changed,
			'renamed_count' => count( $renamed_files ),
		);
	}
}
