<?php
/**
 * Align Theme Core Engine - ZIP Importer Media Index & Resolvers
 *
 * Provides O(1) media path normalization, lookup, attribute rewrite (srcset, data-*),
 * and robust url() CSS parsing.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Zip_Media_Index {

	/** @var array<string, int> Count of occurrences per filename to detect duplicates */
	private static array $name_counts = array();

	/** @var array<string, string> Basename => unique relative path if unique */
	private static array $unique_basenames = array();

	/** @var array<int, string> List of unresolved media URLs for reporting */
	private static array $missing_media = array();

	public static function reset(): void {
		self::$name_counts      = array();
		self::$unique_basenames = array();
		self::$missing_media    = array();
	}

	public static function get_missing_media(): array {
		return array_values( array_unique( self::$missing_media ) );
	}

	/**
	 * Compute normalized relative path of a file against root directory.
	 * Preserves case and subdirectory hierarchy.
	 *
	 * @param string $abs_path Absolute file path.
	 * @param string $root_dir Root extraction directory.
	 * @return string Relative path (e.g. 'assets/img/cases/alkf/02.webp').
	 */
	public static function rel_path( string $abs_path, string $root_dir ): string {
		$abs  = wp_normalize_path( $abs_path );
		$root = wp_normalize_path( trailingslashit( $root_dir ) );
		if ( '' !== $root && 0 === strpos( $abs, $root ) ) {
			return ltrim( substr( $abs, strlen( $root ) ), '/' );
		}
		return ltrim( str_replace( '\\', '/', $abs_path ), '/' );
	}

	/**
	 * Build index of media files to detect unique basenames.
	 *
	 * @param array  $media_files List of media file paths.
	 * @param string $root_dir Root directory.
	 */
	public static function build_index( array $media_files, string $root_dir ): void {
		self::$name_counts      = array();
		self::$unique_basenames = array();
		foreach ( $media_files as $f ) {
			$rel  = self::rel_path( $f, $root_dir );
			$base = basename( $rel );
			self::$name_counts[ $base ] = ( self::$name_counts[ $base ] ?? 0 ) + 1;
			if ( 1 === self::$name_counts[ $base ] ) {
				self::$unique_basenames[ $base ] = $rel;
			} else {
				unset( self::$unique_basenames[ $base ] );
			}
		}
	}

	/**
	 * Resolve raw relative/absolute media reference to WordPress Uploads URL.
	 *
	 * @param string $raw_url     Raw URL attribute value.
	 * @param string $page_folder Page folder directory.
	 * @param string $root_dir    Root directory.
	 * @param array  $url_mapping Mapping of rel_path => uploads_url.
	 * @return string|null Resolved URL or null.
	 */
	public static function resolve( string $raw_url, string $page_folder, string $root_dir, array $url_mapping ): ?string {
		$trimmed = trim( $raw_url, " \t\n\r\0\x0B'\"" );
		if ( '' === $trimmed || 0 === strpos( $trimmed, 'data:' ) || 0 === strpos( $trimmed, 'http://' ) || 0 === strpos( $trimmed, 'https://' ) || 0 === strpos( $trimmed, '//' ) || 0 === strpos( $trimmed, '#' ) ) {
			return null;
		}

		$clean = rawurldecode( str_replace( '\\', '/', preg_replace( '/[?#].*$/', '', $trimmed ) ) );
		$page_folder = wp_normalize_path( trailingslashit( $page_folder ) );
		$root_dir    = wp_normalize_path( trailingslashit( $root_dir ) );

		// Compute rel path candidate
		if ( 0 === strpos( $clean, '/' ) ) {
			$rel = ltrim( $clean, '/' );
		} else {
			$combined = $page_folder . $clean;
			$rel      = self::collapse_path( $combined, $root_dir );
		}

		$rel = ltrim( wp_normalize_path( $rel ), '/' );

		// 1. Direct O(1) match on relative path
		if ( isset( $url_mapping[ $rel ] ) ) {
			return $url_mapping[ $rel ];
		}

		// 2. Try WebP extension swap (jpg/jpeg/png <-> webp)
		$ext = strtolower( pathinfo( $rel, PATHINFO_EXTENSION ) );
		$dir = dirname( $rel );
		$dir_prefix = ( '.' === $dir || '' === $dir ) ? '' : trailingslashit( $dir );
		$stem = pathinfo( $rel, PATHINFO_FILENAME );

		if ( in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
			$webp_cand = $dir_prefix . $stem . '.webp';
			if ( isset( $url_mapping[ $webp_cand ] ) ) {
				return $url_mapping[ $webp_cand ];
			}
		} elseif ( 'webp' === $ext ) {
			foreach ( array( 'jpg', 'png', 'jpeg' ) as $orig_ext ) {
				$cand = $dir_prefix . $stem . '.' . $orig_ext;
				if ( isset( $url_mapping[ $cand ] ) ) {
					return $url_mapping[ $cand ];
				}
			}
		}

		// 3. Fallback: check if basename is uniquely identified in ZIP
		$base = basename( $rel );
		if ( isset( self::$unique_basenames[ $base ] ) ) {
			$unique_rel = self::$unique_basenames[ $base ];
			if ( isset( $url_mapping[ $unique_rel ] ) ) {
				return $url_mapping[ $unique_rel ];
			}
		}

		// Check unique basename with webp swap
		if ( in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
			$base_webp = $stem . '.webp';
			if ( isset( self::$unique_basenames[ $base_webp ] ) ) {
				$unique_rel = self::$unique_basenames[ $base_webp ];
				if ( isset( $url_mapping[ $unique_rel ] ) ) {
					return $url_mapping[ $unique_rel ];
				}
			}
		}

		// Legacy fallback if full file path or simple basename exists in mapping
		if ( isset( $url_mapping[ $base ] ) && ( ( self::$name_counts[ $base ] ?? 0 ) <= 1 ) ) {
			return $url_mapping[ $base ];
		}

		self::$missing_media[] = $raw_url;
		$reason = ( ( self::$name_counts[ $base ] ?? 0 ) > 1 ) ? 'ambiguous' : 'missing';
		error_log( sprintf( '[Align ZipMedia] Unresolved media url=%s rel=%s reason=%s', $raw_url, $rel, $reason ) );

		return null;
	}

	/**
	 * Collapse relative dots (../ and ./) against root directory.
	 */
	private static function collapse_path( string $path, string $root_dir ): string {
		$norm_path = wp_normalize_path( $path );
		$norm_root = wp_normalize_path( $root_dir );

		// Standardize parts
		$segments = explode( '/', $norm_path );
		$stack    = array();
		foreach ( $segments as $seg ) {
			if ( '' === $seg || '.' === $seg ) {
				continue;
			}
			if ( '..' === $seg ) {
				if ( ! empty( $stack ) ) {
					array_pop( $stack );
				}
			} else {
				$stack[] = $seg;
			}
		}
		$rebuilt = ( 0 === strpos( $norm_path, '/' ) ? '/' : '' ) . implode( '/', $stack );

		if ( '' !== $norm_root && 0 === strpos( $rebuilt, $norm_root ) ) {
			return ltrim( substr( $rebuilt, strlen( $norm_root ) ), '/' );
		}
		return $rebuilt;
	}

	/**
	 * Rewrite extra HTML attributes (srcset, data-srcset, imagesrcset, and data-* media).
	 *
	 * @param string   $html     HTML input.
	 * @param callable $resolver Callback (string $raw_url): ?string.
	 * @return string Processed HTML.
	 */
	public static function rewrite_extra_attributes( string $html, callable $resolver ): string {
		if ( empty( $html ) ) {
			return $html;
		}

		$media_exts = array( 'png', 'jpg', 'jpeg', 'webp', 'svg', 'gif', 'ico', 'avif', 'bmp', 'mp4', 'webm', 'ogg', 'mov', 'mp3', 'wav' );

		// 1. Rewrite srcset, data-srcset, imagesrcset
		$srcset_pattern = '/\b(srcset|data-srcset|imagesrcset)\s*=\s*(["\'])(.*?)\2/is';
		$html = preg_replace_callback( $srcset_pattern, function( $matches ) use ( $resolver ) {
			$attr_name = $matches[1];
			$quote     = $matches[2];
			$val       = $matches[3];

			$candidates = preg_split( '/,\s+/', trim( $val ) );
			$rewritten  = array();
			$changed    = false;

			foreach ( $candidates as $cand ) {
				$cand = trim( $cand );
				if ( '' === $cand ) continue;
				$parts = preg_split( '/\s+/', $cand, 2 );
				$raw_u = $parts[0] ?? '';
				$desc  = isset( $parts[1] ) ? ' ' . $parts[1] : '';

				if ( '' === $raw_u || 0 === strpos( $raw_u, 'http://' ) || 0 === strpos( $raw_u, 'https://' ) || 0 === strpos( $raw_u, '//' ) || 0 === strpos( $raw_u, 'data:' ) ) {
					$rewritten[] = $cand;
					continue;
				}

				$new_u = $resolver( $raw_u );
				if ( $new_u ) {
					$clean_new_u = preg_replace( '/[?#].*$/', '', $new_u );
					$rewritten[] = esc_url( $clean_new_u ) . $desc;
					$changed     = true;
				} else {
					$rewritten[] = $cand;
				}
			}

			if ( $changed ) {
				return $attr_name . '=' . $quote . implode( ', ', $rewritten ) . $quote;
			}
			return $matches[0];
		}, $html );

		// 2. Rewrite data-* attributes containing media URLs (e.g. data-cursor-img)
		$data_pattern = '/\b(data-[a-zA-Z0-9_\-]+)\s*=\s*(["\'])(.*?)\2/is';
		$html = preg_replace_callback( $data_pattern, function( $matches ) use ( $resolver, $media_exts ) {
			$attr_name = strtolower( $matches[1] );
			$quote     = $matches[2];
			$val       = trim( $matches[3] );

			// Skip standard known non-media data attributes and srcset
			if ( in_array( $attr_name, array( 'data-src', 'data-bg-src', 'data-srcset' ), true ) ) {
				return $matches[0];
			}

			if ( '' === $val || 0 === strpos( $val, 'http://' ) || 0 === strpos( $val, 'https://' ) || 0 === strpos( $val, '//' ) || 0 === strpos( $val, 'data:' ) || 0 === strpos( $val, '#' ) || 0 === strpos( $val, '{' ) ) {
				return $matches[0];
			}

			$clean_path = rawurldecode( str_replace( '\\', '/', preg_replace( '/[?#].*$/', '', $val ) ) );
			$ext        = strtolower( pathinfo( $clean_path, PATHINFO_EXTENSION ) );

			if ( in_array( $ext, $media_exts, true ) ) {
				$new_u = $resolver( $val );
				if ( $new_u ) {
					$clean_new_u = preg_replace( '/[?#].*$/', '', $new_u );
					return $attr_name . '=' . $quote . esc_url( $clean_new_u ) . $quote;
				}
			}

			return $matches[0];
		}, $html );

		return $html;
	}

	/**
	 * Rewrite url() references in CSS supporting unquoted, single-quoted, and double-quoted URLs.
	 *
	 * @param string   $css      CSS input.
	 * @param callable $resolver Callback (string $raw_url): ?string.
	 * @return string Processed CSS.
	 */
	public static function rewrite_css_urls_with_resolver( string $css, callable $resolver ): string {
		if ( empty( $css ) || false === stripos( $css, 'url(' ) ) {
			return $css;
		}

		$pattern = '/url\(\s*(["\']?|&#x27;|&apos;|&#39;|&quot;)(.*?)\1\s*\)/i';
		return preg_replace_callback( $pattern, function( $matches ) use ( $resolver ) {
			$delimiter = $matches[1] ?? '';
			$raw_url   = trim( html_entity_decode( $matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			$raw_url   = trim( $raw_url, "'\"" );

			if ( '' === $raw_url || 0 === strpos( $raw_url, 'data:' ) || 0 === strpos( $raw_url, 'http://' ) || 0 === strpos( $raw_url, 'https://' ) || 0 === strpos( $raw_url, '//' ) ) {
				return $matches[0];
			}

			$clean_path = rawurldecode( str_replace( '\\', '/', preg_replace( '/[?#].*$/', '', $raw_url ) ) );
			$ext        = strtolower( pathinfo( $clean_path, PATHINFO_EXTENSION ) );

			$resolved = $resolver( $raw_url );
			if ( $resolved ) {
				$clean_res = preg_replace( '/[?#].*$/', '', $resolved );
				return 'url(' . $delimiter . esc_url( $clean_res ) . $delimiter . ')';
			}

			if ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp', 'svg', 'gif' ), true ) ) {
				$dummy_bg = Align_Zip_Media_Rewriter::get_dummy_media_url( $raw_url );
				return 'url(' . $delimiter . esc_url( $dummy_bg ) . $delimiter . ')';
			}

			return $matches[0];
		}, $css );
	}

	/**
	 * Apply Reading settings safely at finalization step.
	 *
	 * @param array $catalog Session catalog.
	 * @return array Status payload array('status' => 'set'|'kept'|'none', 'id' => int).
	 */
	public static function apply_front_page( array $catalog ): array {
		$created_pages  = $catalog['created_pages'] ?? array();
		$path_to_id_map = $catalog['path_to_id_map'] ?? array();
		$slug_to_id_map = $catalog['slug_to_id_map'] ?? array();
		$import_options = $catalog['import_options'] ?? array();
		$overwrite      = ! isset( $import_options['overwrite'] ) || ! empty( $import_options['overwrite'] );

		// 1. Identify candidate home_id created in this session
		$home_id = 0;
		$pages   = $catalog['pages'] ?? array();

		// Priority A: page with is_home = true from pages list or created_pages
		foreach ( $pages as $p_info ) {
			if ( ! empty( $p_info['is_home'] ) && ! empty( $p_info['is_placeholder'] ) ) {
				continue;
			}
			$pk = $p_info['path_key'] ?? '';
			if ( ! empty( $p_info['is_home'] ) ) {
				if ( isset( $path_to_id_map[ $pk ] ) && is_numeric( $path_to_id_map[ $pk ] ) ) {
					$home_id = (int) $path_to_id_map[ $pk ];
					break;
				}
				if ( isset( $slug_to_id_map[ $p_info['slug'] ?? '' ] ) ) {
					$home_id = (int) $slug_to_id_map[ $p_info['slug'] ];
					break;
				}
			}
		}

		if ( ! $home_id ) {
			foreach ( $created_pages as $cp ) {
				if ( is_array( $cp ) && ! empty( $cp['is_home'] ) && ! empty( $cp['post_id'] ) && is_numeric( $cp['post_id'] ) ) {
					$home_id = (int) $cp['post_id'];
					break;
				}
			}
		}

		// Priority B: root level 'home', 'front-page', 'trang-chu', or empty path_key
		if ( ! $home_id ) {
			foreach ( array( '', 'home', 'front-page', 'trang-chu' ) as $cand ) {
				$cand_pid = 0;
				if ( isset( $path_to_id_map[ $cand ] ) && is_numeric( $path_to_id_map[ $cand ] ) ) {
					$cand_pid = (int) $path_to_id_map[ $cand ];
				} elseif ( isset( $slug_to_id_map[ $cand ] ) && is_numeric( $slug_to_id_map[ $cand ] ) ) {
					$cand_pid = (int) $slug_to_id_map[ $cand ];
				}
				if ( $cand_pid > 0 ) {
					$cand_post = get_post( $cand_pid );
					if ( $cand_post && 'publish' === $cand_post->post_status ) {
						$home_id = $cand_pid;
						break;
					}
				}
			}
		}

		// Rule 2: overwrite = true
		if ( $overwrite ) {
			if ( $home_id > 0 ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $home_id );
				error_log( sprintf( '[Align Importer] Front page set: ID %d (overwrite=true)', $home_id ) );
				return array( 'status' => 'set', 'id' => $home_id );
			}
			update_option( 'show_on_front', 'posts' );
			delete_option( 'page_on_front' );
			error_log( '[Align Importer] No home page in ZIP; Reading left as posts' );
			return array( 'status' => 'none', 'id' => 0 );
		}

		// Rule 3: overwrite = false
		$curr_front = (int) get_option( 'page_on_front' );
		$curr_show  = get_option( 'show_on_front' );
		if ( 'page' === $curr_show && $curr_front > 0 ) {
			$existing_front = get_post( $curr_front );
			if ( $existing_front && 'publish' === $existing_front->post_status ) {
				error_log( sprintf( '[Align Importer] Front page kept: ID %d', $curr_front ) );
				return array( 'status' => 'kept', 'id' => $curr_front );
			}
		}

		if ( $home_id > 0 ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $home_id );
			error_log( sprintf( '[Align Importer] Front page set: ID %d (overwrite=false, fallback)', $home_id ) );
			return array( 'status' => 'set', 'id' => $home_id );
		}

		return array( 'status' => 'none', 'id' => 0 );
	}
}
