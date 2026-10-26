<?php
/**
 * Align Theme Core Engine - ZIP Importer Page Tree Resolver & Hierarchy Manager
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Zip_Page_Tree {

	/**
	 * Recognized root-level blog directory names.
	 *
	 * @var array
	 */
	public const BLOG_DIRS = array(
		'blog',
		'blogs',
		'posts',
		'post',
		'news',
		'articles',
		'tin-tuc',
		'bai-viet',
	);

	/**
	 * Recursively scan directory to collect page hierarchy metadata.
	 *
	 * @param string $root_dir Base extraction directory.
	 * @param array  $exclude_dirs Subdirectories to ignore.
	 * @param int    $max_depth Maximum traversal depth.
	 * @param array  $root_only_excludes Subdirectories to ignore ONLY at root level.
	 * @return array List of discovered raw pages.
	 */
	public static function collect_pages( string $root_dir, array $exclude_dirs = array(), int $max_depth = 5, array $root_only_excludes = array() ): array {
		$root_dir = trailingslashit( wp_normalize_path( $root_dir ) );
		$pages    = array();

		self::traverse_directory( $root_dir, '', $exclude_dirs, $max_depth, 0, $pages, $root_only_excludes );

		return $pages;
	}

	/**
	 * Helper for recursive directory traversal.
	 *
	 * @param string $root_dir Absolute root folder.
	 * @param string $rel_dir Relative directory from root (e.g. "services" or "services/web-design").
	 * @param array  $exclude_dirs Exclusion list.
	 * @param int    $max_depth Depth limit.
	 * @param int    $current_depth Current depth level.
	 * @param array  &$pages Reference to accumulated pages array.
	 * @param array  $root_only_excludes Root-only exclusions.
	 */
	private static function traverse_directory( string $root_dir, string $rel_dir, array $exclude_dirs, int $max_depth, int $current_depth, array &$pages, array $root_only_excludes = array() ): void {
		if ( $current_depth > $max_depth ) return;
		$current_path = $root_dir . ( '' !== $rel_dir ? trailingslashit( $rel_dir ) : '' );
		if ( ! is_dir( $current_path ) ) return;
		$entries = scandir( $current_path );
		if ( false === $entries ) return;

		$html_files = array();
		$sub_dirs   = array();

		foreach ( $entries as $entry ) {
			if ( in_array( $entry, array( '.', '..' ), true ) || 0 === strpos( $entry, '._' ) || 0 === strpos( $entry, '.' ) || '__MACOSX' === $entry ) continue;
			$full_entry = wp_normalize_path( $current_path . $entry );
			if ( is_dir( $full_entry ) ) {
				$entry_lower = strtolower( $entry );
				if ( in_array( $entry_lower, $exclude_dirs, true ) ) continue;
				if ( '' === $rel_dir && in_array( $entry_lower, $root_only_excludes, true ) ) continue;
				$sub_dirs[] = $entry;
			} elseif ( is_file( $full_entry ) ) {
				$ext = strtolower( pathinfo( $entry, PATHINFO_EXTENSION ) );
				if ( in_array( $ext, array( 'html', 'htm' ), true ) ) {
					$html_files[] = array( 'filename' => $entry, 'full_path' => $full_entry );
				}
			}
		}

		// Process HTML files in current folder
		$has_index = false;
		$index_file = null;
		foreach ( $html_files as $hf ) {
			$base = strtolower( pathinfo( $hf['filename'], PATHINFO_FILENAME ) );
			if ( 'index' === $base ) {
				$has_index  = true;
				$index_file = $hf;
				break;
			}
		}

		foreach ( $html_files as $hf ) {
			$fname = pathinfo( $hf['filename'], PATHINFO_FILENAME );
			$base  = strtolower( $fname );

			// Skip root-level special parts (header, footer, reusable blocks)
			if ( '' === $rel_dir ) {
				if ( in_array( $base, array( 'header', 'footer' ), true ) || 0 === strpos( $base, 'block-' ) || 0 === strpos( $base, 'reuse-' ) || 0 === strpos( $base, 'pattern-' ) ) {
					continue;
				}
			}

			// Case 1: Root directory
			if ( '' === $rel_dir ) {
				if ( 'index' === $base ) {
					$pages[] = self::build_page_entry( '', '', 0, 'home', $hf['full_path'], true, '' );
				} else {
					$slug = ltrim( sanitize_title( $fname ), '_' );
					if ( ! empty( $slug ) ) $pages[] = self::build_page_entry( $slug, '', 0, $slug, $hf['full_path'], false, '' );
				}
				continue;
			}

			// Case 2: Subdirectory
			$folder_slug = sanitize_title( basename( $rel_dir ) );
			$parent_rel  = dirname( $rel_dir );
			$parent_key  = ( '.' === $parent_rel || '' === $parent_rel ) ? '' : trim( wp_normalize_path( $parent_rel ), '/' );

			if ( 'index' === $base ) {
				$path_key = trim( wp_normalize_path( $rel_dir ), '/' );
				$depth    = substr_count( $path_key, '/' ) + 1;
				$pages[]  = self::build_page_entry( $path_key, $parent_key, $depth, $folder_slug, $hf['full_path'], false, $rel_dir );
			} else {
				$slug = ltrim( sanitize_title( $fname ), '_' );
				if ( empty( $slug ) ) continue;

				$dir_key = trim( wp_normalize_path( $rel_dir ), '/' );
				if ( $has_index && $slug === $folder_slug ) continue;

				$path_key = $dir_key . '/' . $slug;
				$depth    = substr_count( $path_key, '/' ) + 1;
				$pages[]  = self::build_page_entry( $path_key, $dir_key, $depth, $slug, $hf['full_path'], false, $rel_dir );
			}
		}

		// Recurse into subdirectories
		foreach ( $sub_dirs as $sdir ) {
			$next_rel = '' === $rel_dir ? $sdir : $rel_dir . '/' . $sdir;
			self::traverse_directory( $root_dir, $next_rel, $exclude_dirs, $max_depth, $current_depth + 1, $pages, $root_only_excludes );
		}
	}

	/**
	 * Build standardized page entry array.
	 */
	private static function build_page_entry( string $path_key, string $parent_key, int $depth, string $slug, string $html_file, bool $is_home, string $folder_name, string $entity = 'page' ): array {
		$title = class_exists( 'Align_Zip_Scanner' ) && method_exists( 'Align_Zip_Scanner', 'format_page_title' )
			? Align_Zip_Scanner::format_page_title( $slug )
			: ucwords( str_replace( array( '-', '_' ), ' ', $slug ) );

		return array(
			'entity'         => $entity,
			'type'           => 'flat_page',
			'path_key'       => $path_key,
			'parent_key'     => $parent_key,
			'depth'          => $depth,
			'folder_name'    => $folder_name,
			'folder_path'    => dirname( $html_file ),
			'slug'           => $slug,
			'title'          => $title,
			'html_file'      => wp_normalize_path( $html_file ),
			'css_files'      => array(),
			'js_files'       => array(),
			'media_files'    => array(),
			'is_home'        => $is_home,
			'is_placeholder' => false,
		);
	}

	/**
	 * Collect blog post entries from root-level blog directories.
	 *
	 * @param string $root_dir Base extraction directory.
	 * @param int    $max_depth Maximum traversal depth.
	 * @return array Discovered blog entries (posts and optional blog index page).
	 */
	public static function collect_posts( string $root_dir, int $max_depth = 5 ): array {
		$root_dir = trailingslashit( wp_normalize_path( $root_dir ) );
		if ( ! is_dir( $root_dir ) ) {
			return array();
		}

		$entries = scandir( $root_dir );
		if ( false === $entries ) {
			return array();
		}

		$blog_entries = array();

		foreach ( $entries as $entry ) {
			if ( in_array( $entry, array( '.', '..' ), true ) || 0 === strpos( $entry, '._' ) || 0 === strpos( $entry, '.' ) || '__MACOSX' === $entry ) {
				continue;
			}

			$full_path = wp_normalize_path( $root_dir . $entry );
			if ( ! is_dir( $full_path ) ) {
				continue;
			}

			$entry_lower = strtolower( $entry );
			if ( in_array( $entry_lower, self::BLOG_DIRS, true ) ) {
				self::traverse_blog_directory( $root_dir, $entry, $max_depth, 1, $blog_entries );
			}
		}

		return $blog_entries;
	}

	/**
	 * Recursively scan blog directory.
	 *
	 * @param string $root_dir Absolute root folder.
	 * @param string $rel_dir Relative directory from root (e.g. "blog" or "blog/2024").
	 * @param int    $max_depth Depth limit.
	 * @param int    $current_depth Current depth level.
	 * @param array  &$blog_entries Reference to blog entries.
	 */
	private static function traverse_blog_directory( string $root_dir, string $rel_dir, int $max_depth, int $current_depth, array &$blog_entries ): void {
		if ( $current_depth > $max_depth ) {
			error_log( sprintf( '[Align ZipTree] Skipping deep blog path exceeding max_depth=%d: %s', $max_depth, $rel_dir ) );
			return;
		}

		$current_path = $root_dir . trailingslashit( $rel_dir );
		if ( ! is_dir( $current_path ) ) {
			return;
		}

		$entries = scandir( $current_path );
		if ( false === $entries ) {
			return;
		}

		$html_files = array();
		$sub_dirs   = array();

		foreach ( $entries as $entry ) {
			if ( in_array( $entry, array( '.', '..' ), true ) || 0 === strpos( $entry, '._' ) || 0 === strpos( $entry, '.' ) || '__MACOSX' === $entry ) {
				continue;
			}

			$full_entry = wp_normalize_path( $current_path . $entry );
			if ( is_dir( $full_entry ) ) {
				$sub_dirs[] = $entry;
			} elseif ( is_file( $full_entry ) ) {
				$ext = strtolower( pathinfo( $entry, PATHINFO_EXTENSION ) );
				if ( in_array( $ext, array( 'html', 'htm' ), true ) ) {
					$html_files[] = array(
						'filename'  => $entry,
						'full_path' => $full_entry,
					);
				}
			}
		}

		foreach ( $html_files as $hf ) {
			$fname = pathinfo( $hf['filename'], PATHINFO_FILENAME );
			$base  = strtolower( $fname );

			// Ignore template parts or draft-like files
			if ( in_array( $base, array( 'header', 'footer' ), true ) || 0 === strpos( $base, '_' ) ) {
				error_log( sprintf( '[Align ZipTree] Skipping ignored blog file: %s', $hf['full_path'] ) );
				continue;
			}

			// Case 1: blog/index.html -> page 'blog' (D-4)
			$rel_dir_normalized = trim( wp_normalize_path( $rel_dir ), '/' );
			$is_root_blog_dir   = ! str_contains( $rel_dir_normalized, '/' );

			if ( $is_root_blog_dir && 'index' === $base ) {
				$page_entry = self::build_page_entry( $rel_dir_normalized, '', 1, $rel_dir_normalized, $hf['full_path'], false, $rel_dir_normalized, 'page' );
				$blog_entries[] = $page_entry;
				error_log( sprintf( '[Align ZipTree] Blog index detected as page: path_key=%s slug=%s', $page_entry['path_key'], $page_entry['slug'] ) );
				continue;
			}

			// Case 2: Post entry
			if ( 'index' === $base ) {
				// blog/second-post/index.html -> slug = 'second-post', path_key = 'blog/second-post'
				$slug     = sanitize_title( basename( $rel_dir_normalized ) );
				$path_key = $rel_dir_normalized;
			} else {
				$slug     = sanitize_title( $fname );
				$path_key = $rel_dir_normalized . '/' . $slug;
			}

			$depth = substr_count( $path_key, '/' ) + 1;
			$post_entry = self::build_page_entry( $path_key, '', $depth, $slug, $hf['full_path'], false, $rel_dir_normalized, 'post' );
			$blog_entries[] = $post_entry;
			error_log( sprintf( '[Align ZipTree] Post detected: path_key=%s slug=%s', $path_key, $slug ) );
		}

		foreach ( $sub_dirs as $sdir ) {
			self::traverse_blog_directory( $root_dir, $rel_dir . '/' . $sdir, $max_depth, $current_depth + 1, $blog_entries );
		}
	}

	/**
	 * Normalize, deduplicate, insert missing placeholders (D-1), and sort pages by depth ascending.
	 *
	 * @param array $pages Raw pages list.
	 * @return array Sorted normalized pages list.
	 */
	public static function normalize_pages( array $pages ): array {
		$keyed_pages = array();

		// Index existing pages by path_key
		foreach ( $pages as $p ) {
			$pk = isset( $p['path_key'] ) ? $p['path_key'] : '';
			if ( ! isset( $p['path_key'] ) ) {
				// Fallback compatibility for legacy scanners
				$slug = sanitize_title( $p['slug'] ?? 'home' );
				$pk   = in_array( $slug, array( 'index', 'home', 'front-page', 'trang-chu' ), true ) ? '' : $slug;
				$p['path_key']   = $pk;
				$p['parent_key'] = '';
				$p['depth']      = '' === $pk ? 0 : substr_count( $pk, '/' ) + 1;
			}

			if ( '' === $pk ) {
				$p['slug']    = 'home';
				$p['title']   = 'Home';
				$p['is_home'] = true;
			}

			if ( ! isset( $keyed_pages[ $pk ] ) ) {
				$keyed_pages[ $pk ] = $p;
			}
		}

		// D-1: Detect missing parent ancestors and generate placeholder entries
		$placeholders = array();
		foreach ( $keyed_pages as $pk => $p ) {
			if ( empty( $pk ) || 'post' === ( $p['entity'] ?? 'page' ) || 'lang_root' === ( $p['entity'] ?? 'page' ) ) {
				continue;
			}

			$page_lang = $p['lang'] ?? '';
			$segments  = explode( '/', $pk );
			array_pop( $segments ); // Drop own slug to check ancestors

			// Skip the language prefix folder itself when checking ancestor pages for secondary languages
			$accum        = '';
			$parent_accum = '';
			if ( '' !== $page_lang && ! empty( $segments ) && $segments[0] === $page_lang ) {
				$accum = array_shift( $segments );
			}

			foreach ( $segments as $seg ) {
				$accum = '' === $accum ? $seg : $accum . '/' . $seg;
				if ( ! isset( $keyed_pages[ $accum ] ) && ! isset( $placeholders[ $accum ] ) ) {
					$seg_slug = basename( $accum );
					if ( class_exists( 'Align_Zip_Scanner' ) && method_exists( 'Align_Zip_Scanner', 'format_page_title' ) ) {
						$title = Align_Zip_Scanner::format_page_title( $seg_slug );
					} else {
						$title = ucwords( str_replace( array( '-', '_' ), ' ', $seg_slug ) );
					}

					$depth = substr_count( $accum, '/' ) + 1;
					if ( '' !== $page_lang && 0 === strpos( $accum, $page_lang . '/' ) ) {
						$depth = substr_count( substr( $accum, strlen( $page_lang ) + 1 ), '/' ) + 1;
					}
					$placeholders[ $accum ] = array(
						'entity'         => 'page',
						'type'           => 'flat_page',
						'path_key'       => $accum,
						'parent_key'     => $parent_accum,
						'depth'          => $depth,
						'folder_name'    => $accum,
						'folder_path'    => '',
						'slug'           => $seg_slug,
						'title'          => $title,
						'html_file'      => '',
						'css_files'      => array(),
						'js_files'       => array(),
						'media_files'    => array(),
						'is_home'        => false,
						'is_placeholder' => true,
						'lang'           => $page_lang,
					);
				}
				$parent_accum = $accum;
			}
		}

		$all_pages = array_merge( array_values( $keyed_pages ), array_values( $placeholders ) );

		// Sort by entity (lang_root and page first, post after), then depth ascending, then path_key
		usort( $all_pages, function( $a, $b ) {
			$entity_a = $a['entity'] ?? 'page';
			$entity_b = $b['entity'] ?? 'page';
			$is_post_a = ( 'post' === $entity_a );
			$is_post_b = ( 'post' === $entity_b );
			if ( $is_post_a !== $is_post_b ) {
				return $is_post_a ? 1 : -1;
			}
			return ( $a['depth'] === $b['depth'] ) ? strcmp( $a['path_key'], $b['path_key'] ) : ( $a['depth'] <=> $b['depth'] );
		} );

		return $all_pages;
	}

	/**
	 * Find existing page by slug and parent ID.
	 *
	 * @param string $slug Page slug.
	 * @param int    $parent_id Parent post ID.
	 * @param string $path_key Relative hierarchical path key.
	 * @return array Matching post IDs.
	 */
	public static function find_existing_page( string $slug, int $parent_id = 0, string $path_key = '' ): array {
		$args = array(
			'name'           => $slug,
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'trash', 'future', 'auto-draft' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		if ( $parent_id > 0 ) {
			$args['post_parent'] = $parent_id;
		}

		$existing_ids = get_posts( $args );

		if ( ! empty( $path_key ) ) {
			$path_page = get_page_by_path( $path_key, OBJECT, 'page' );
			if ( $path_page && ! in_array( $path_page->ID, $existing_ids, true ) ) {
				$existing_ids[] = $path_page->ID;
			}
		}

		return $existing_ids;
	}

	/**
	 * Resolve parent post ID from catalog path_to_id_map.
	 *
	 * @param array $page Page metadata.
	 * @param array $path_to_id_map Map of path_key => post_id.
	 * @return int Parent post ID or 0.
	 */
	public static function resolve_parent_id( array $page, array $path_to_id_map ): int {
		$parent_key = $page['parent_key'] ?? '';
		if ( '' === $parent_key ) return 0;
		if ( isset( $path_to_id_map[ $parent_key ] ) ) return (int) $path_to_id_map[ $parent_key ];

		error_log( sprintf( '[Align ZipTree] Missing parent for page "%s", requested parent_key="%s". Falling back to root level.', $page['path_key'] ?? $page['slug'], $parent_key ) );
		return 0;
	}

	/**
	 * Resolve relative or absolute href into a normalized path_key.
	 *
	 * @param string $href Link href value.
	 * @param string $source_path_key Current page path_key.
	 * @return string Normalized path_key for catalog lookup.
	 */
	public static function resolve_href_to_key( string $href, string $source_path_key = '' ): string {
		$clean_path = preg_replace( '/[?#].*$/', '', trim( $href ) );
		if ( '' === $clean_path ) return '';
		$clean_path = str_replace( chr( 0 ), '', $clean_path );

		$is_root_relative = ( 0 === strpos( $clean_path, '/' ) );
		$clean_path       = ltrim( $clean_path, '/' );

		$base_dir = '';
		if ( ! $is_root_relative && '' !== $source_path_key ) {
			$source_dir = dirname( $source_path_key );
			$base_dir   = ( '.' === $source_dir || '' === $source_dir ) ? '' : $source_dir;
		}

		$combined = ( '' !== $base_dir && ! $is_root_relative ) ? $base_dir . '/' . $clean_path : $clean_path;
		$parts    = explode( '/', str_replace( '\\', '/', $combined ) );
		$stack    = array();

		foreach ( $parts as $part ) {
			if ( '' === $part || '.' === $part ) continue;
			if ( '..' === $part ) {
				if ( ! empty( $stack ) ) array_pop( $stack );
			} else {
				$stack[] = $part;
			}
		}

		$resolved = implode( '/', $stack );
		$resolved = preg_replace( '/\.(html|htm)$/i', '', $resolved );
		if ( 'index' === $resolved || preg_match( '/(^|\/)index$/', $resolved ) ) {
			$resolved = preg_replace( '/(^|\/)index$/', '', $resolved );
		}

		return trim( $resolved, '/' );
	}
}
