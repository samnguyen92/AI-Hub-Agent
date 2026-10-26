<?php
/**
 * Align Theme Core Engine - ZIP Importer Structure Scanner
 * Detects project structure: global assets and folder-per-page hierarchy.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Zip_Scanner {

	/**
	 * Scan the extracted directory and build the project catalog.
	 *
	 * @param string $root_dir Absolute path to unzipped directory.
	 * @return array Project catalog containing assets and pages.
	 */
	public static function scan_directory( string $root_dir ): array {
		$root_dir = wp_normalize_path( trailingslashit( $root_dir ) );
		$root_dir = wp_normalize_path( self::normalize_directory_to_root( $root_dir ) );
		error_log( sprintf( '[Align ZipScanner] Scanning normalized directory: %s', $root_dir ) );

		$catalog = array(
			'root_dir'        => $root_dir,
			'global_assets'   => array(
				'css'    => array(),
				'js'     => array(),
				'fonts'  => array(),
				'images' => self::collect_all_media_files( $root_dir ),
			),
			'template_parts'  => array(),
			'reusable_blocks' => array(),
			'pages'           => array(),
			'languages'       => class_exists( 'Align_Zip_Lang_Resolver' ) ? Align_Zip_Lang_Resolver::detect_lang_dirs( $root_dir ) : array(),
		);

		if ( ! is_dir( $root_dir ) ) {
			return $catalog;
		}

		$entries          = array_diff( scandir( $root_dir ), array( '.', '..' ) );
		$known_asset_dirs = array( 'assets', 'asset', 'public', 'static', 'dist', 'css', 'js', 'fonts', 'img', 'images', 'styles', 'scripts', 'lib', 'libs', 'libraries', 'plugins', 'vendor', 'vendors', 'mail', 'includes', 'inc', 'theme', 'components', 'media', 'upload', 'uploads' );
		$scanned_dirs     = array();

		foreach ( $entries as $entry ) {
			$cand_path = wp_normalize_path( $root_dir . $entry );
			if ( is_dir( $cand_path ) ) {
				$folder_trail = wp_normalize_path( trailingslashit( $cand_path ) );
				$lower_name   = strtolower( $entry );
				if ( in_array( $lower_name, array( 'node_modules', '.git', 'standalone', 'backup', 'backups', 'archive', 'archives', 'old', 'test', 'tests', 'docs', 'temp', 'tmp' ), true ) ) {
					continue;
				}

				$is_known_asset = in_array( $lower_name, $known_asset_dirs, true );
				$has_html_file  = false;
				if ( ! $is_known_asset ) {
					$folder_files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $folder_trail, RecursiveDirectoryIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
					foreach ( $folder_files as $f_item ) {
						if ( $f_item->isFile() && in_array( strtolower( pathinfo( $f_item->getPathname(), PATHINFO_EXTENSION ) ), array( 'html', 'htm' ), true ) ) {
							$has_html_file = true;
							break;
						}
					}
				}

				if ( $is_known_asset || ! $has_html_file ) {
					$assets_found = self::scan_assets_folder( $folder_trail );
					$catalog['global_assets']['css']   = array_merge( $catalog['global_assets']['css'], $assets_found['css'] );
					$catalog['global_assets']['js']    = array_merge( $catalog['global_assets']['js'], $assets_found['js'] );
					$catalog['global_assets']['fonts'] = array_merge( $catalog['global_assets']['fonts'], $assets_found['fonts'] );
					$scanned_dirs[]                    = $folder_trail;
				}
			}
		}

		// Also collect loose root CSS/JS/Font files
		$font_exts = array( 'woff2', 'woff', 'ttf', 'otf', 'eot' );
		foreach ( $entries as $entry ) {
			$full_p = wp_normalize_path( $root_dir . $entry );
			if ( is_file( $full_p ) ) {
				$ext = strtolower( pathinfo( $full_p, PATHINFO_EXTENSION ) );
				if ( 'css' === $ext ) $catalog['global_assets']['css'][] = $full_p;
				elseif ( 'js' === $ext ) $catalog['global_assets']['js'][] = $full_p;
				elseif ( in_array( $ext, $font_exts, true ) ) $catalog['global_assets']['fonts'][] = $full_p;
			}
		}

		$catalog['global_assets']['css']   = array_values( array_unique( $catalog['global_assets']['css'] ) );
		$catalog['global_assets']['js']    = array_values( array_unique( $catalog['global_assets']['js'] ) );
		$catalog['global_assets']['fonts'] = array_values( array_unique( $catalog['global_assets']['fonts'] ) );

		// Scan for Global Template Parts, Reusable Blocks, and Page Folders
		$scan_result                = self::scan_pages_and_parts( $root_dir, $scanned_dirs, $catalog['languages'] );
		$catalog['template_parts']  = $scan_result['template_parts'];
		$catalog['reusable_blocks'] = $scan_result['reusable_blocks'];
		$catalog['pages']           = $scan_result['pages'];

		error_log( sprintf(
			'[Align ZipScanner] Found: %d CSS, %d JS, %d Media, %d parts, %d blocks, %d pages',
			count( $catalog['global_assets']['css'] ), count( $catalog['global_assets']['js'] ),
			count( $catalog['global_assets']['images'] ), count( $catalog['template_parts'] ),
			count( $catalog['reusable_blocks'] ), count( $catalog['pages'] )
		) );

		return $catalog;
	}

	/**
	 * Scan the assets directory for CSS, JS, fonts, and images.
	 */
	private static function scan_assets_folder( string $assets_dir ): array {
		$assets_dir = wp_normalize_path( trailingslashit( $assets_dir ) );
		$result     = array( 'css' => array(), 'js' => array(), 'fonts' => array(), 'images' => array() );
		if ( ! is_dir( $assets_dir ) ) return $result;

		$iterator   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $assets_dir, RecursiveDirectoryIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
		$img_exts   = array( 'png', 'jpg', 'jpeg', 'webp', 'svg', 'gif', 'ico' );
		$font_exts  = array( 'woff2', 'woff', 'ttf', 'otf', 'eot' );

		foreach ( $iterator as $item ) {
			if ( ! $item->isFile() ) continue;
			$path = wp_normalize_path( $item->getPathname() );
			$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
			if ( 'css' === $ext ) $result['css'][] = $path;
			elseif ( 'js' === $ext ) $result['js'][] = $path;
			elseif ( in_array( $ext, $font_exts, true ) ) $result['fonts'][] = $path;
			elseif ( in_array( $ext, $img_exts, true ) ) $result['images'][] = $path;
		}
		return $result;
	}

	/**
	 * Scan directory for Global Template Parts, Reusable Blocks, and Page Folders.
	 */
	private static function scan_pages_and_parts( string $root_dir, $assets_dirs = array(), array $detected_langs = array() ): array {
		$root_dir        = wp_normalize_path( trailingslashit( $root_dir ) );
		$template_parts  = array();
		$reusable_blocks = array();
		$pages           = array();
		$entries         = array_diff( scandir( $root_dir ), array( '.', '..' ) );
		$raw_assets      = is_array( $assets_dirs ) ? $assets_dirs : ( $assets_dirs ? array( $assets_dirs ) : array() );
		$assets_list     = array_map( function( $d ) { return wp_normalize_path( trailingslashit( $d ) ); }, $raw_assets );

		// 1. Scan Subdirectories (Folder-per-page or template-part or reuse-block pattern)
		foreach ( $entries as $entry ) {
			if ( '__MACOSX' === $entry || 0 === strpos( $entry, '._' ) || 0 === strpos( $entry, '.' ) || 0 === strpos( $entry, '__' ) ) {
				continue;
			}

			$full_path = wp_normalize_path( $root_dir . $entry );
			if ( is_dir( $full_path ) ) {
				$folder_trail = wp_normalize_path( trailingslashit( $full_path ) );
				$is_asset_dir = false;
				foreach ( $assets_list as $a_dir ) {
					if ( 0 === strpos( $folder_trail, $a_dir ) ) {
						$is_asset_dir = true;
						break;
					}
				}
				if ( $is_asset_dir || in_array( strtolower( $entry ), array( 'assets', 'vendor', 'node_modules', '.git', 'dist', 'standalone', 'backup', 'backups', 'archive', 'archives', 'old', 'test', 'tests', 'docs', 'temp', 'tmp' ), true ) ) {
					continue;
				}

				$lower_name = strtolower( trim( $entry ) );

				// Language directories: handled separately below, skip in root scan
				if ( ! empty( $detected_langs[ $lower_name ] ) ) {
					continue;
				}

				// Check Header Template Part
				if ( in_array( $lower_name, array( 'header', 'headers', 'template-parts/header', 'parts/header' ), true ) ) {
					$part_data = self::inspect_page_folder( $folder_trail, 'header' );
					if ( $part_data ) {
						$part_data['type']        = 'template_part';
						$part_data['slug']        = 'header';
						$part_data['title']       = 'Header';
						$template_parts['header'] = $part_data;
						error_log( sprintf( '[Align ZipScanner] Detected Global Header Template Part in "%s"', $entry ) );
					}
					continue;
				}

				// Check Footer Template Part
				if ( in_array( $lower_name, array( 'footer', 'footers', 'template-parts/footer', 'parts/footer' ), true ) ) {
					$part_data = self::inspect_page_folder( $folder_trail, 'footer' );
					if ( $part_data ) {
						$part_data['type']        = 'template_part';
						$part_data['slug']        = 'footer';
						$part_data['title']       = 'Footer';
						$template_parts['footer'] = $part_data;
						error_log( sprintf( '[Align ZipScanner] Detected Global Footer Template Part in "%s"', $entry ) );
					}
					continue;
				}

				// Check Reusable Blocks container folder
				if ( in_array( $lower_name, array( 'reuse-block', 'reuse-blocks', 'reusable-blocks', 'blocks', 'patterns', 'sections', 'components' ), true ) ) {
					$block_entries = array_diff( scandir( $folder_trail ), array( '.', '..' ) );
					foreach ( $block_entries as $b_entry ) {
						$b_path = wp_normalize_path( $folder_trail . $b_entry );
						if ( is_dir( $b_path ) ) {
							$b_data = self::inspect_page_folder( wp_normalize_path( trailingslashit( $b_path ) ), $b_entry );
							if ( $b_data ) {
								$b_data['type']                     = 'reusable_block';
								$b_data['is_block']                 = true;
								$reusable_blocks[ $b_data['slug'] ] = $b_data;
								error_log( sprintf( '[Align ZipScanner] Detected Reusable Block folder "%s" in "%s"', $b_entry, $entry ) );
							}
						} elseif ( is_file( $b_path ) && in_array( strtolower( pathinfo( $b_entry, PATHINFO_EXTENSION ) ), array( 'html', 'htm' ), true ) ) {
							$b_slug = sanitize_title( pathinfo( $b_entry, PATHINFO_FILENAME ) );
							$reusable_blocks[ $b_slug ] = array(
								'type'        => 'reusable_block',
								'is_block'    => true,
								'folder_name' => '',
								'folder_path' => $folder_trail,
								'slug'        => $b_slug,
								'title'       => self::format_page_title( $b_slug ),
								'html_file'   => $b_path,
								'css_files'   => array(),
								'js_files'    => array(),
								'media_files' => array(),
								'is_home'     => false,
							);
							error_log( sprintf( '[Align ZipScanner] Detected Reusable Block file "%s" in "%s"', $b_entry, $entry ) );
						}
					}
					continue;
				}
			}
		}

		// 2. Scan Root Special Parts (Header, Footer, Reusable Blocks)
		foreach ( $entries as $entry ) {
			if ( 0 === strpos( $entry, '._' ) || 0 === strpos( $entry, '.' ) || 0 === strpos( $entry, '__' ) ) continue;
			$full_path = wp_normalize_path( $root_dir . $entry );
			if ( is_file( $full_path ) && in_array( strtolower( pathinfo( $entry, PATHINFO_EXTENSION ) ), array( 'html', 'htm' ), true ) ) {
				$slug = sanitize_title( pathinfo( $entry, PATHINFO_FILENAME ) );
				if ( 0 === strpos( $slug, '_' ) ) $slug = ltrim( $slug, '_' );
				if ( empty( $slug ) ) continue;

				if ( 'header' === $slug && ! isset( $template_parts['header'] ) ) {
					$template_parts['header'] = array(
						'type' => 'template_part', 'folder_name' => '', 'folder_path' => $root_dir,
						'slug' => 'header', 'title' => 'Header', 'html_file' => $full_path,
						'css_files' => array(), 'js_files' => array(), 'media_files' => array(), 'is_home' => false,
					);
					continue;
				}

				if ( 'footer' === $slug && ! isset( $template_parts['footer'] ) ) {
					$template_parts['footer'] = array(
						'type' => 'template_part', 'folder_name' => '', 'folder_path' => $root_dir,
						'slug' => 'footer', 'title' => 'Footer', 'html_file' => $full_path,
						'css_files' => array(), 'js_files' => array(), 'media_files' => array(), 'is_home' => false,
					);
					continue;
				}

				if ( 0 === strpos( $slug, 'block-' ) || 0 === strpos( $slug, 'reuse-' ) || 0 === strpos( $slug, 'pattern-' ) ) {
					$clean_slug = preg_replace( '/^(block|reuse|pattern)-/', '', $slug );
					$reusable_blocks[ $clean_slug ] = array(
						'type' => 'reusable_block', 'is_block' => true, 'folder_name' => '', 'folder_path' => $root_dir,
						'slug' => $clean_slug, 'title' => self::format_page_title( $clean_slug ), 'html_file' => $full_path,
						'css_files' => array(), 'js_files' => array(), 'media_files' => array(), 'is_home' => false,
					);
					continue;
				}
			}
		}

		// 3. Scan Hierarchical Pages and Blog Posts via Align_Zip_Page_Tree
		$exclude_page_dirs = array(
			'assets', 'vendor', 'node_modules', '.git', 'dist', 'standalone', 'backup', 'backups', 'archive', 'archives', 'old', 'temp', 'tmp',
			'header', 'headers', 'template-parts', 'parts', 'footer', 'footers',
			'reuse-block', 'reuse-blocks', 'reusable-blocks', 'blocks', 'patterns', 'sections', 'components',
		);
		$exclude_page_dirs  = array_merge( $exclude_page_dirs, array_keys( $detected_langs ) );
		$root_only_excludes = class_exists( 'Align_Zip_Page_Tree' ) ? Align_Zip_Page_Tree::BLOG_DIRS : array();
		$pages = class_exists( 'Align_Zip_Page_Tree' ) ? Align_Zip_Page_Tree::collect_pages( $root_dir, $exclude_page_dirs, 5, $root_only_excludes ) : array();
		$posts = class_exists( 'Align_Zip_Page_Tree' ) ? Align_Zip_Page_Tree::collect_posts( $root_dir ) : array();
		$all_entries = array_merge( $pages, $posts );

		// 4. Scan secondary language directories
		if ( class_exists( 'Align_Zip_Lang_Resolver' ) && ! empty( $detected_langs ) ) {
			foreach ( $detected_langs as $lang_folder => $lang_info ) {
				if ( ! empty( $lang_info['is_default'] ) ) {
					// REQ-20: Default language folder -> unwrap (process pages at root level)
					$lang_scan = Align_Zip_Lang_Resolver::scan_language_folder( $lang_info['dir'], '', $lang_info['code'] );
				} else {
					$lang_scan = Align_Zip_Lang_Resolver::scan_language_folder( $lang_info['dir'], $lang_folder, $lang_info['code'] );
				}
				if ( ! empty( $lang_scan['template_parts'] ) ) {
					$template_parts = array_merge( $template_parts, $lang_scan['template_parts'] );
				}
				if ( ! empty( $lang_scan['pages'] ) ) {
					$all_entries = array_merge( $all_entries, $lang_scan['pages'] );
				}
			}
		}

		return array(
			'template_parts'  => $template_parts,
			'reusable_blocks' => $reusable_blocks,
			'pages'           => array_values( $all_entries ),
		);
	}

	/**
	 * Inspect a single page folder and collect its HTML, CSS, JS, and Media files.
	 */
	private static function inspect_page_folder( string $folder_path, string $folder_name ): ?array {
		$folder_path = wp_normalize_path( trailingslashit( $folder_path ) );
		$slug        = sanitize_title( $folder_name );
		$is_home     = in_array( $slug, array( 'home', 'trang-chu', 'front-page', 'index' ), true );
		if ( 'index' === $slug ) $slug = 'home';

		$title       = self::format_page_title( $folder_name );
		$html_file   = null;
		$css_files   = array();
		$js_files    = array();
		$media_files = array();
		$img_exts    = array( 'png', 'jpg', 'jpeg', 'webp', 'svg', 'gif', 'ico', 'mp4' );

		if ( ! is_dir( $folder_path ) ) return null;

		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $folder_path, RecursiveDirectoryIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
		foreach ( $iterator as $item ) {
			if ( ! $item->isFile() ) continue;
			$path = wp_normalize_path( $item->getPathname() );
			$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
			$name = strtolower( pathinfo( $path, PATHINFO_FILENAME ) );
			if ( 'html' === $ext || 'htm' === $ext ) {
				if ( null === $html_file || 'index' === $name || $name === strtolower( $folder_name ) ) $html_file = $path;
			} elseif ( 'css' === $ext ) $css_files[] = $path;
			elseif ( 'js' === $ext ) $js_files[] = $path;
			elseif ( in_array( $ext, $img_exts, true ) ) $media_files[] = $path;
		}
		if ( ! $html_file ) return null;

		return array(
			'type' => 'folder_page', 'folder_name' => $folder_name, 'folder_path' => $folder_path,
			'slug' => $slug, 'title' => $title, 'html_file' => $html_file,
			'css_files' => $css_files, 'js_files' => $js_files, 'media_files' => $media_files, 'is_home' => $is_home,
		);
	}

	/**
	 * Generate a clean human-readable title from folder name or slug.
	 */
	public static function format_page_title( string $name ): string {
		$lower = strtolower( trim( $name ) );
		if ( in_array( $lower, array( 'index', 'home', 'front-page', 'trang-chu' ), true ) ) return 'Home';
		$clean = str_replace( array( '-', '_', '+' ), ' ', $name );
		return trim( implode( ' ', array_map( 'ucfirst', explode( ' ', $clean ) ) ) );
	}

	/**
	 * Physically normalize extracted directory: unwrap nested folders and move files to root.
	 */
	public static function normalize_directory_to_root( string $session_dir ): string {
		$session_dir = wp_normalize_path( trailingslashit( $session_dir ) );
		if ( ! is_dir( $session_dir ) ) return $session_dir;

		// 1. Delete all macOS junk and hidden files
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $session_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			$base = $item->getBasename();
			$path = wp_normalize_path( $item->getPathname() );
			if ( '.__MACOSX' === $base || '__MACOSX' === $base || 0 === strpos( $base, '._' ) || '.DS_Store' === $base || 'Thumbs.db' === $base ) {
				if ( $item->isDir() ) @rmdir( $path ); else @unlink( $path );
			}
		}

		// 2. Loop unwrap single top-level folders
		$max_unwrap = 5;
		while ( $max_unwrap-- > 0 ) {
			$entries = array_values( array_filter( scandir( $session_dir ), function( $e ) {
				return ! in_array( $e, array( '.', '..', '.DS_Store', '__MACOSX' ), true ) && 0 !== strpos( $e, '._' );
			} ) );

			if ( 1 === count( $entries ) && is_dir( $session_dir . $entries[0] ) ) {
				$sub = wp_normalize_path( trailingslashit( $session_dir . $entries[0] ) );
				foreach ( array_diff( scandir( $sub ), array( '.', '..' ) ) as $sub_item ) {
					@rename( wp_normalize_path( $sub . $sub_item ), wp_normalize_path( $session_dir . $sub_item ) );
				}
				@rmdir( $sub );
			} else {
				break;
			}
		}

		// 3. If no direct HTML files exist in session root, but exist in a subfolder (dist, site, public, html, pages, etc.)
		$root_entries  = array_diff( scandir( $session_dir ), array( '.', '..' ) );
		$has_root_html = false;
		foreach ( $root_entries as $e ) {
			if ( is_file( $session_dir . $e ) && in_array( strtolower( pathinfo( $e, PATHINFO_EXTENSION ) ), array( 'html', 'htm' ), true ) ) {
				$has_root_html = true;
				break;
			}
		}

		if ( ! $has_root_html ) {
			$cands = array( 'site', 'dist', 'public', 'src', 'template', 'app', 'build', 'html', 'web', 'pages' );
			foreach ( $cands as $cand ) {
				$cand_dir = wp_normalize_path( trailingslashit( $session_dir . $cand ) );
				if ( ! is_dir( $cand_dir ) ) continue;
				$cand_files    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $cand_dir, RecursiveDirectoryIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
				$cand_has_html = false;
				foreach ( $cand_files as $cf ) {
					if ( $cf->isFile() && in_array( strtolower( pathinfo( $cf->getPathname(), PATHINFO_EXTENSION ) ), array( 'html', 'htm' ), true ) ) {
						$cand_has_html = true; break;
					}
				}
				if ( $cand_has_html ) {
					foreach ( array_diff( scandir( $cand_dir ), array( '.', '..' ) ) as $c_item ) {
						$target = wp_normalize_path( $session_dir . $c_item );
						if ( ! file_exists( $target ) ) @rename( wp_normalize_path( $cand_dir . $c_item ), $target );
					}
					break;
				}
			}
		}
		return $session_dir;
	}

	/**
	 * Backward compatibility alias for normalize_directory_to_root.
	 */
	public static function resolve_actual_root_dir( string $root_dir ): string {
		return self::normalize_directory_to_root( $root_dir );
	}

	/**
	 * Recursively collect ALL media files in project directory.
	 */
	public static function collect_all_media_files( string $root_dir ): array {
		$root_dir = wp_normalize_path( trailingslashit( $root_dir ) );
		if ( ! is_dir( $root_dir ) ) return array();
		$media_files = array();
		$img_exts    = array( 'png', 'jpg', 'jpeg', 'webp', 'svg', 'gif', 'ico', 'mp4', 'webm', 'avif', 'bmp' );
		$iterator    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root_dir, RecursiveDirectoryIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );

		foreach ( $iterator as $item ) {
			if ( ! $item->isFile() ) continue;
			$path = wp_normalize_path( $item->getPathname() );
			$base = $item->getBasename();
			if ( 0 === strpos( $base, '._' ) || 0 === strpos( $base, '.' ) || 0 === strpos( $base, '__' ) ) continue;
			if ( in_array( strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ), $img_exts, true ) ) $media_files[] = $path;
		}

		return array_values( array_unique( $media_files ) );
	}
}
