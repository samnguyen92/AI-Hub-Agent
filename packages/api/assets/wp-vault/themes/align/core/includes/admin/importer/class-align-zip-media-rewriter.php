<?php
/**
 * Align Theme Core Engine - ZIP Importer Media Sideload & URL Rewriter
 * Handles media extraction, WordPress attachment sideloading, Cloud WebP conversion, and HTML/CSS link re-mapping.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Zip_Media_Rewriter {

	/** @var array<string, string> Relative/Absolute path => WordPress Uploads URL */
	private static array $url_mapping = array();

	public static function reset_mapping(): void {
		self::$url_mapping = array();
	}

	public static function get_url_mapping(): array {
		return self::$url_mapping;
	}

	public static function set_url_mapping( array $mapping ): void {
		self::$url_mapping = array_merge( self::$url_mapping, $mapping );
	}

	/**
	 * Sideload a list of media files into WordPress Uploads, perform WebP Cloud conversion, and build the URL map.
	 *
	 * @param array  $media_file_paths List of absolute file paths to media.
	 * @param bool   $auto_convert_webp Whether to attempt WebP conversion.
	 * @param string $root_dir         Root extraction directory.
	 * @return array Sideloaded media stats and mapping.
	 */
	public static function import_media_files( array $media_file_paths, bool $auto_convert_webp = true, string $root_dir = '' ): array {
		$uploaded_count      = 0;
		$reused_count        = 0;
		$converted_webp      = 0;
		$collisions_renamed  = 0;
		$error_count         = 0;

		@ini_set( 'max_execution_time', '300' );
		@set_time_limit( 300 );

		$upload_dir = wp_upload_dir();
		$base_dir   = $upload_dir['path'];

		if ( ! is_dir( $base_dir ) ) {
			wp_mkdir_p( $base_dir );
		}

		if ( class_exists( 'Align_WebP' ) ) {
			remove_filter( 'wp_generate_attachment_metadata', array( 'Align_WebP', 'handle_attachment_upload' ), 10 );
		}

		foreach ( $media_file_paths as $file_path ) {
			$file_path = wp_normalize_path( $file_path );
			if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
				continue;
			}

			$orig_filename = basename( $file_path );
			$ext           = strtolower( pathinfo( $orig_filename, PATHINFO_EXTENSION ) );
			$name_no_ext   = pathinfo( $orig_filename, PATHINFO_FILENAME );
			$rel           = class_exists( 'Align_Zip_Media_Index' ) ? Align_Zip_Media_Index::rel_path( $file_path, $root_dir ) : $orig_filename;

			// Check for existing attachment with meta _align_zip_rel
			$existing_id = 0;
			$existing_posts = get_posts( array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'meta_query'     => array(
					array(
						'key'   => '_align_zip_rel',
						'value' => $rel,
					),
				),
				'fields'         => 'ids',
			) );

			if ( ! empty( $existing_posts ) ) {
				$cand_id = (int) $existing_posts[0];
				$att_file = get_attached_file( $cand_id );
				if ( $att_file && file_exists( $att_file ) ) {
					$existing_id = $cand_id;
				}
			}

			if ( $existing_id > 0 ) {
				$att_url = wp_get_attachment_url( $existing_id );
				self::$url_mapping[ $rel ]       = $att_url;
				self::$url_mapping[ $file_path ] = $att_url;
				$reused_count++;
				error_log( sprintf( '[Align ZipMedia] Reused rel=%s -> ID %d (%s)', $rel, $existing_id, $att_url ) );
				continue;
			}

			$dest_filename = $orig_filename;
			$source_binary = $file_path;
			$is_webp_done  = false;

			if ( $auto_convert_webp && in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
				$webp_file = self::convert_to_webp_fast( $file_path );
				if ( $webp_file && file_exists( $webp_file ) && filesize( $webp_file ) > 0 ) {
					$source_binary = $webp_file;
					$dest_filename = $name_no_ext . '.webp';
					$is_webp_done  = true;
					$converted_webp++;
				}
			}

			$file_content = @file_get_contents( $source_binary );
			if ( false === $file_content ) {
				$error_count++;
				continue;
			}

			$upload_res = wp_upload_bits( $dest_filename, null, $file_content );
			if ( empty( $upload_res['error'] ) && ! empty( $upload_res['file'] ) ) {
				$dest_file = wp_normalize_path( $upload_res['file'] );
				$dest_url  = $upload_res['url'];

				if ( basename( $dest_file ) !== $dest_filename ) {
					$collisions_renamed++;
				}

				$filetype  = wp_check_filetype( $dest_filename, null );
				$mime_type = $is_webp_done ? 'image/webp' : ( $filetype['type'] ?: 'image/jpeg' );

				$attachment = array(
					'post_mime_type' => $mime_type,
					'post_title'     => sanitize_file_name( $name_no_ext ),
					'post_content'   => '',
					'post_status'    => 'inherit',
				);

				$attach_id = wp_insert_attachment( $attachment, $dest_file );
				if ( ! is_wp_error( $attach_id ) ) {
					update_post_meta( $attach_id, '_align_zip_rel', $rel );
					require_once ABSPATH . 'wp-admin/includes/image.php';
					$attach_data = wp_generate_attachment_metadata( $attach_id, $dest_file );
					wp_update_attachment_metadata( $attach_id, $attach_data );
				}

				self::$url_mapping[ $rel ]       = $dest_url;
				self::$url_mapping[ $file_path ] = $dest_url;
				if ( $is_webp_done ) {
					$rel_webp = preg_replace( '/\.(jpg|jpeg|png)$/i', '.webp', $rel );
					self::$url_mapping[ $rel_webp ] = $dest_url;
				}

				$uploaded_count++;
				error_log( sprintf( '[Align ZipMedia] Sideloaded rel=%s -> ID %d (%s)', $rel, $attach_id ?? 0, $dest_url ) );
			} else {
				$error_count++;
				error_log( sprintf( '[Align ZipMedia] Error writing file to uploads: %s | %s', $dest_filename, $upload_res['error'] ?? 'Write failed' ) );
			}
		}

		return array(
			'uploaded'           => $uploaded_count,
			'reused'             => $reused_count,
			'converted'          => $converted_webp,
			'collisions_renamed' => $collisions_renamed,
			'errors'             => $error_count,
			'mapping'            => self::$url_mapping,
		);
	}

	/**
	 * Rewrite relative asset URLs in HTML content.
	 *
	 * @param string $html        HTML content.
	 * @param string $page_folder Page folder directory.
	 * @param string $root_dir    Root project directory.
	 * @return string Processed HTML.
	 */
	public static function rewrite_html_assets( string $html, string $page_folder, string $root_dir ): string {
		if ( empty( $html ) ) {
			return $html;
		}

		$media_exts = array( 'png', 'jpg', 'jpeg', 'webp', 'svg', 'gif', 'ico', 'avif', 'bmp', 'mp4', 'webm', 'ogg', 'mov', 'mp3', 'wav' );

		$resolver = function( string $raw_url ) use ( $page_folder, $root_dir ): ?string {
			if ( class_exists( 'Align_Zip_Media_Index' ) ) {
				return Align_Zip_Media_Index::resolve( $raw_url, $page_folder, $root_dir, self::$url_mapping );
			}
			return null;
		};

		// 1. Rewrite primary HTML media attributes
		$attr_pattern = '/\b(src|data-src|data-bg-src|poster|href)\s*=\s*(["\'])(.*?)\2/is';
		$html = preg_replace_callback( $attr_pattern, function( $matches ) use ( $resolver, $media_exts ) {
			$attr_name  = strtolower( $matches[1] );
			$quote      = $matches[2];
			$raw_url    = $matches[3];
			$clean_path = str_replace( '\\', '/', preg_replace( '/[?#].*$/', '', trim( $raw_url ) ) );
			$ext        = strtolower( pathinfo( $clean_path, PATHINFO_EXTENSION ) );

			if ( 'href' === $attr_name && ! in_array( $ext, $media_exts, true ) ) {
				return $matches[0];
			}
			if ( in_array( $ext, array( 'js', 'mjs', 'css', 'html', 'htm', 'php', 'json', 'map', 'wasm', 'woff', 'woff2', 'ttf', 'otf', 'eot' ), true ) ) {
				return $matches[0];
			}
			if ( 0 === strpos( $raw_url, 'http://' ) || 0 === strpos( $raw_url, 'https://' ) || 0 === strpos( $raw_url, '//' ) || 0 === strpos( $raw_url, 'data:' ) || 0 === strpos( $raw_url, '#' ) || 0 === strpos( $raw_url, 'javascript:' ) || 0 === strpos( $raw_url, 'mailto:' ) || 0 === strpos( $raw_url, 'tel:' ) ) {
				return $matches[0];
			}

			$resolved = $resolver( $raw_url );
			if ( $resolved ) {
				$clean_res = preg_replace( '/[?#].*$/', '', $resolved );
				return $attr_name . '=' . $quote . esc_url( $clean_res ) . $quote;
			}
			if ( 'href' === $attr_name ) {
				return $matches[0];
			}
			if ( in_array( $ext, $media_exts, true ) || in_array( $attr_name, array( 'poster', 'data-bg-src' ), true ) || ( '' === $ext && '' !== trim( $raw_url ) && '#' !== trim( $raw_url ) ) ) {
				$dummy_url = self::get_dummy_media_url( $raw_url );
				return $attr_name . '=' . $quote . esc_url( $dummy_url ) . $quote;
			}
			return $matches[0];
		}, $html );

		// 2. Rewrite extra media attributes (srcset, data-srcset, imagesrcset, data-cursor-img...)
		if ( class_exists( 'Align_Zip_Media_Index' ) ) {
			$html = Align_Zip_Media_Index::rewrite_extra_attributes( $html, $resolver );
		}

		// 3. Rewrite inline styles with url(...)
		$html = self::rewrite_css_urls( $html, $page_folder, $root_dir );

		return $html;
	}

	/**
	 * Rewrite url(...) references in CSS stylesheets.
	 *
	 * @param string $css         CSS code.
	 * @param string $page_folder Page folder directory.
	 * @param string $root_dir    Root project directory.
	 * @return string Processed CSS.
	 */
	public static function rewrite_css_urls( string $css, string $page_folder, string $root_dir ): string {
		if ( empty( $css ) || false === stripos( $css, 'url(' ) ) {
			return $css;
		}

		$resolver = function( string $raw_url ) use ( $page_folder, $root_dir ): ?string {
			if ( class_exists( 'Align_Zip_Media_Index' ) ) {
				return Align_Zip_Media_Index::resolve( $raw_url, $page_folder, $root_dir, self::$url_mapping );
			}
			return null;
		};

		if ( class_exists( 'Align_Zip_Media_Index' ) ) {
			return Align_Zip_Media_Index::rewrite_css_urls_with_resolver( $css, $resolver );
		}

		return $css;
	}

	/**
	 * Generate a fallback placeholder URL when media asset is missing.
	 */
	public static function get_dummy_media_url( string $raw_url, string $type = 'image' ): string {
		$clean_path = preg_replace( '/[?#].*$/', '', $raw_url );
		$base_name  = basename( $clean_path );
		$ext        = strtolower( pathinfo( $base_name, PATHINFO_EXTENSION ) );

		if ( 'video' === $type || in_array( $ext, array( 'mp4', 'webm', 'ogg', 'mov', 'avi' ), true ) ) {
			return 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4';
		}
		if ( 'audio' === $type || in_array( $ext, array( 'mp3', 'wav', 'ogg', 'aac' ), true ) ) {
			return 'https://www.w3schools.com/html/horse.mp3';
		}

		$label = ucfirst( str_replace( array( '-', '_', '.' ), ' ', pathinfo( $base_name, PATHINFO_FILENAME ) ) );
		if ( empty( $label ) || in_array( strtolower( $label ), array( 'index', 'image', 'img', 'file' ), true ) ) {
			$label = 'Placeholder Image';
		}
		return 'https://placehold.co/800x600/e2e8f0/475569.png?text=' . rawurlencode( $label );
	}

	/**
	 * Rewrite internal HTML links to WordPress permalinks with data-align-link-id.
	 */
	public static function rewrite_internal_links( string $html, array $created_pages, string $source_path_key = '', string $source_lang = '', array $aliases = array() ): string {
		if ( empty( $html ) || empty( $created_pages ) ) {
			return $html;
		}

		$pattern = '/<a\b([^>]*\bhref\s*=\s*(["\'])(.*?)\2[^>]*)>/is';

		return preg_replace_callback( $pattern, function( $matches ) use ( $created_pages, $source_path_key, $source_lang, $aliases ) {
			$full_tag = $matches[0];
			$attrs    = $matches[1];
			$quote    = $matches[2];
			$raw_href = trim( $matches[3] );

			if ( '' === $raw_href || 0 === strpos( $raw_href, '#' ) || 0 === strpos( $raw_href, 'mailto:' ) || 0 === strpos( $raw_href, 'tel:' ) || 0 === strpos( $raw_href, 'http' ) || 0 === strpos( $raw_href, '//' ) ) {
				return $full_tag;
			}

			$clean_path = preg_replace( '/[?#].*$/', '', $raw_href );
			$suffix     = substr( $raw_href, strlen( $clean_path ) );
			$post_id    = 0;

			if ( '' !== $source_lang && class_exists( 'Align_Zip_Link_Resolver' ) ) {
				$post_id = Align_Zip_Link_Resolver::resolve( $raw_href, $source_path_key, $source_lang, $created_pages, $aliases );
			}

			if ( ! $post_id && class_exists( 'Align_Zip_Page_Tree' ) ) {
				$pk = Align_Zip_Page_Tree::resolve_href_to_key( $clean_path, $source_path_key );
				if ( isset( $created_pages[ $pk ] ) && is_numeric( $created_pages[ $pk ] ) ) {
					$post_id = (int) $created_pages[ $pk ];
				}
			}
			if ( ! $post_id ) {
				$clean = trim( str_replace( array( '../', './', 'index.html', '.html', '.htm' ), '', $clean_path ), '/' );
				$slug  = sanitize_title( $clean );
				if ( '' === $slug || 'index' === $slug ) $slug = 'home';
				if ( isset( $created_pages[ $slug ] ) && is_numeric( $created_pages[ $slug ] ) ) {
					$post_id = (int) $created_pages[ $slug ];
				}
			}

			if ( $post_id > 0 ) {
				$permalink = get_permalink( $post_id );
				if ( $permalink ) {
					$rel_url   = ( wp_make_link_relative( $permalink ) ?: '/' ) . $suffix;
					$new_attrs = preg_replace( '/\bhref\s*=\s*(["\']).*?\1/is', 'href="' . esc_url( $rel_url ) . '" data-align-link-id="' . $post_id . '"', $attrs );
					error_log( sprintf( '[Align ZipLinks] Rewrote href="%s" -> "%s" (post %d)', $raw_href, $rel_url, $post_id ) );
					return '<a' . $new_attrs . '>';
				}
			}

			return $full_tag;
		}, $html );
	}

	/**
	 * Convert image to WebP using fast local GD or Cloud API.
	 */
	private static function convert_to_webp_fast( string $file_path ): ?string {
		$file_path = wp_normalize_path( $file_path );
		$ext       = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		$out       = wp_normalize_path( dirname( $file_path ) . '/' . pathinfo( $file_path, PATHINFO_FILENAME ) . '.webp' );
		if ( file_exists( $out ) && filesize( $out ) > 0 ) return $out;

		if ( function_exists( 'imagewebp' ) ) {
			$img = ( 'png' === $ext && function_exists( 'imagecreatefrompng' ) )
				? @imagecreatefrompng( $file_path )
				: ( function_exists( 'imagecreatefromjpeg' ) ? @imagecreatefromjpeg( $file_path ) : null );
			if ( $img ) {
				imagepalettetotruecolor( $img );
				imagealphablending( $img, true );
				imagesavealpha( $img, true );
				$saved = @imagewebp( $img, $out, 82 );
				imagedestroy( $img );
				if ( $saved && file_exists( $out ) && filesize( $out ) > 0 ) return $out;
			}
		}

		if ( class_exists( 'Align_WebP' ) && method_exists( 'Align_WebP', 'convert_file_to_webp' ) ) {
			$res = Align_WebP::convert_file_to_webp( $file_path, Align_WebP::get_quality() );
			if ( $res && file_exists( $res ) && filesize( $res ) > 0 ) return wp_normalize_path( $res );
		}
		return null;
	}
}
