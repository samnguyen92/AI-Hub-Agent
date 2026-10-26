<?php
/**
 * Align Theme Core Engine - ZIP Importer Batch Runner
 * Handles multi-step session lifecycle, media batching, and page chunking for shared hosting environments.
 *
 * @package Align
 */

if ( ! defined( "ABSPATH" ) ) {
	exit;
}

class Align_Zip_Batch_Runner {

	/**
	 * Get absolute path to temporary session directory.
	 *
	 * @param string $session_id Unique session identifier.
	 * @return string Directory path.
	 */
	public static function get_session_dir( string $session_id ): string {
		$clean_id   = preg_replace( '/[^a-zA-Z0-9_-]/', '', $session_id );
		$upload_dir = wp_upload_dir();
		return wp_normalize_path( trailingslashit( $upload_dir['basedir'] ) . 'align-temp-import-' . $clean_id . '/' );
	}

	/**
	 * Get session metadata file path.
	 *
	 * @param string $session_id Session ID.
	 * @return string File path.
	 */
	private static function get_catalog_file( string $session_id ): string {
		return wp_normalize_path( self::get_session_dir( $session_id ) . 'catalog.json' );
	}

	/**
	 * Save session catalog to disk.
	 *
	 * @param string $session_id Session ID.
	 * @param array  $catalog    Catalog data.
	 * @return bool True on success.
	 */
	public static function save_catalog( string $session_id, array $catalog ): bool {
		$file = self::get_catalog_file( $session_id );
		return false !== @file_put_contents( $file, wp_json_encode( $catalog ) );
	}

	/**
	 * Load session catalog from disk.
	 *
	 * @param string $session_id Session ID.
	 * @return array|null Catalog array or null if invalid.
	 */
	public static function load_catalog( string $session_id ): ?array {
		$file = self::get_catalog_file( $session_id );
		if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
			return null;
		}
		$json = file_get_contents( $file );
		return $json ? json_decode( $json, true ) : null;
	}

	/**
	 * Step 1: Initialize session, extract ZIP, and scan project.
	 *
	 * @param string $tmp_file  Uploaded temp file path.
	 * @param string $orig_name Original archive name.
	 * @return array Session init result.
	 */
	public static function init_session( string $tmp_file, string $orig_name ): array {
		$tmp_file = wp_normalize_path( $tmp_file );
		$ext      = strtolower( pathinfo( $orig_name, PATHINFO_EXTENSION ) );
		if ( 'zip' !== $ext ) {
			return array( 'success' => false, 'message' => __( 'Invalid file format. Only .zip is permitted.', 'align' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp_file ) ) {
			return array( 'success' => false, 'message' => __( 'Could not open ZIP archive.', 'align' ) );
		}

		$blocked_exts = array( 'php', 'phtml', 'phar', 'exe', 'sh', 'cgi', 'pl', 'py', 'htaccess' );
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = $zip->statIndex( $i )['name'];
			if ( false !== strpos( $name, '../' ) || false !== strpos( $name, '..\\' ) ) {
				$zip->close();
				return array( 'success' => false, 'message' => __( 'Security violation: invalid relative path.', 'align' ) );
			}
		}

		$session_id  = substr( md5( uniqid( '', true ) . $orig_name ), 0, 12 );
		$session_dir = wp_normalize_path( self::get_session_dir( $session_id ) );
		if ( ! is_dir( $session_dir ) ) {
			wp_mkdir_p( $session_dir );
		}

		$extracted = $zip->extractTo( $session_dir );
		$zip->close();

		if ( ! $extracted ) {
			Align_Zip_Importer::cleanup_directory( $session_dir );
			return array( 'success' => false, 'message' => __( 'Failed to extract ZIP archive.', 'align' ) );
		}

		// Security & Junk Cleanup: Recursively strip executable files, __MACOSX, and ._* junk files
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $session_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			$base = $item->getBasename();
			$path = wp_normalize_path( $item->getPathname() );

			// Delete macOS resource forks, hidden files, and __MACOSX directories
			if ( '.__MACOSX' === $base || '__MACOSX' === $base || 0 === strpos( $base, '._' ) || '.DS_Store' === $base || 'Thumbs.db' === $base ) {
				if ( $item->isDir() ) {
					@rmdir( $path );
				} else {
					@unlink( $path );
				}
				continue;
			}

			if ( $item->isFile() ) {
				$f_ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
				if ( in_array( $f_ext, $blocked_exts, true ) || 0 === strpos( $base, '.' ) ) {
					@unlink( $path );
					error_log( sprintf( '[Align BatchRunner] Safely stripped file: %s', $path ) );
				}
			}
		}

		$catalog = Align_Zip_Scanner::scan_directory( $session_dir );
		if ( empty( $catalog["pages"] ) && empty( $catalog["template_parts"] ) && empty( $catalog["reusable_blocks"] ) ) {
			Align_Zip_Importer::cleanup_directory( $session_dir );
			return array( "success" => false, "message" => __( "No HTML pages, template parts, or reusable blocks found.", "align" ) );
		}

		// Normalize, deduplicate, insert placeholders and sort by depth
		if ( class_exists( "Align_Zip_Page_Tree" ) ) {
			$catalog["pages"] = Align_Zip_Page_Tree::normalize_pages( $catalog["pages"] ?? array() );
		}

		// Flat media list
		$catalog["all_media_list"]  = Align_Zip_Scanner::collect_all_media_files( $catalog["root_dir"] );
		$catalog["created_pages"]   = array();
		$catalog["slug_to_id_map"]  = array();
		$catalog["path_to_id_map"]  = array();
		$catalog["total_media"]     = count( $catalog["all_media_list"] );
		$catalog["total_pages"]     = count( $catalog["pages"] );
		$catalog["session_id"]      = $session_id;

		if ( class_exists( "Align_Zip_Media_Index" ) ) {
			Align_Zip_Media_Index::build_index( $catalog["all_media_list"], $catalog["root_dir"] );
		}

		self::save_catalog( $session_id, $catalog );

		error_log( sprintf( "[Align BatchRunner] Session %s initialized: %d media, %d pages, %d parts", $session_id, $catalog["total_media"], $catalog["total_pages"], count( $catalog["template_parts"] ) ) );

		return array(
			"success"         => true,
			"session_id"      => $session_id,
			"total_media"     => $catalog["total_media"],
			"total_pages"     => $catalog["total_pages"],
			"template_parts"  => count( $catalog["template_parts"] ),
			"reusable_blocks" => count( $catalog["reusable_blocks"] ),
		);
	}

	/**
	 * Step 1.5: Perform intelligent semantic decomposition using AI.
	 *
	 * @param string $session_id Session ID.
	 * @return array Refactor result with refreshed counts.
	 */
	public static function process_ai_refactor( string $session_id, array $ai_config = array() ): array {
		$session_dir = self::get_session_dir( $session_id );
		if ( ! is_dir( $session_dir ) ) {
			return array( "success" => false, "message" => __( "Session expired or directory not found.", "align" ) );
		}

		require_once __DIR__ . "/class-align-zip-ai-refactor.php";
		$refactor_res = Align_Zip_AI_Refactor::refactor_session_directory( $session_dir, $ai_config );
		if ( empty( $refactor_res["success"] ) ) {
			return $refactor_res;
		}

		$catalog = Align_Zip_Scanner::scan_directory( $session_dir );
		$catalog["session_id"] = $session_id;

		if ( class_exists( "Align_Zip_Page_Tree" ) ) {
			$catalog["pages"] = Align_Zip_Page_Tree::normalize_pages( $catalog["pages"] ?? array() );
		}

		// Flat media list
		$catalog["all_media_list"]  = Align_Zip_Scanner::collect_all_media_files( $catalog["root_dir"] );
		$catalog["created_pages"]   = array();
		$catalog["slug_to_id_map"]  = array();
		$catalog["path_to_id_map"]  = array();
		$catalog["total_media"]     = count( $catalog["all_media_list"] );
		$catalog["total_pages"]     = count( $catalog["pages"] );

		self::save_catalog( $session_id, $catalog );

		return array(
			"success"         => true,
			"message"         => $refactor_res["message"],
			"total_pages"     => $catalog["total_pages"],
			"total_media"     => $catalog["total_media"],
			"template_parts"  => count( $catalog["template_parts"] ),
			"reusable_blocks" => count( $catalog["reusable_blocks"] ),
		);
	}

	/**
	 * Step 2: Reset site data and register global assets.
	 *
	 * @param string $session_id Session ID.
	 * @param array  $options    Import options.
	 * @return array Reset result.
	 */
	public static function reset_and_register_assets( string $session_id, array $options ): array {
		$catalog = self::load_catalog( $session_id );
		if ( ! $catalog ) {
			return array( "success" => false, "message" => __( "Session expired or not found.", "align" ) );
		}
		$catalog["import_options"] = $options;
		if ( ! isset( $options["overwrite"] ) || ! empty( $options["overwrite"] ) ) {
			Align_Zip_Importer::execute_full_site_reset( false );
		}

		Align_Zip_Media_Rewriter::reset_mapping();
		Align_Zip_Importer::copy_and_register_global_assets( $catalog["global_assets"], $catalog["root_dir"] );

		if ( class_exists( 'Align_Zip_Lang_Resolver' ) && ! empty( $catalog['languages'] ) ) {
			$languages_added = array();
			$lang_roots      = Align_Zip_Lang_Resolver::ensure_languages( $catalog['languages'], $languages_added );
			$catalog['lang_roots']       = $lang_roots;
			$catalog['languages_added']  = $languages_added;
			$path_to_id_map              = $catalog['path_to_id_map'] ?? array();
			foreach ( $lang_roots as $l_key => $root_pid ) {
				$path_to_id_map[ $l_key ] = $root_pid;
			}
			$catalog['path_to_id_map']   = $path_to_id_map;
			self::save_catalog( $session_id, $catalog );
		}

		return array( "success" => true, "message" => __( "Site reset and global assets registered.", "align" ) );
	}

	/**
	 * Step 3: Process a batch slice of media files.
	 *
	 * @param string $session_id Session ID.
	 * @param int    $offset     Slice start index.
	 * @param int    $limit      Slice length.
	 * @param bool   $convert_webp Auto WebP conversion flag.
	 * @return array Batch result.
	 */
	public static function process_media_batch( string $session_id, int $offset, int $limit, bool $convert_webp = true ): array {
		$catalog = self::load_catalog( $session_id );
		if ( ! $catalog ) {
			return array( "success" => false, "message" => __( "Session expired or not found.", "align" ) );
		}

		if ( ! empty( $catalog["url_mapping"] ) ) {
			Align_Zip_Media_Rewriter::set_url_mapping( $catalog["url_mapping"] );
		}

		if ( class_exists( "Align_Zip_Media_Index" ) && ! empty( $catalog["all_media_list"] ) && ! empty( $catalog["root_dir"] ) ) {
			Align_Zip_Media_Index::build_index( $catalog["all_media_list"], $catalog["root_dir"] );
		}

		$all_media = $catalog["all_media_list"] ?? array();
		$slice     = array_slice( $all_media, $offset, $limit );

		$stats = array( "uploaded" => 0, "converted" => 0 );
		if ( ! empty( $slice ) ) {
			$stats = Align_Zip_Media_Rewriter::import_media_files( $slice, $convert_webp, $catalog["root_dir"] ?? "" );
		}

		$catalog["url_mapping"] = Align_Zip_Media_Rewriter::get_url_mapping();
		self::save_catalog( $session_id, $catalog );

		$processed = min( $offset + count( $slice ), count( $all_media ) );
		return array(
			"success"     => true,
			"processed"   => $processed,
			"total"       => count( $all_media ),
			"batch_count" => count( $slice ),
			"uploaded"    => $stats["uploaded"] ?? 0,
		);
	}

	/**
	 * Step 4: Assemble Global Template Parts (Header/Footer) and Reusable Blocks.
	 *
	 * @param string $session_id Session ID.
	 * @param array  $options    Import options.
	 * @return array Result with assembled parts.
	 */
	public static function process_parts_and_blocks( string $session_id, array $options ): array {
		$catalog = self::load_catalog( $session_id );
		if ( ! $catalog ) {
			return array( "success" => false, "message" => __( "Session expired or not found.", "align" ) );
		}

		if ( ! empty( $catalog["url_mapping"] ) ) {
			Align_Zip_Media_Rewriter::set_url_mapping( $catalog["url_mapping"] );
		}

		$created_pages = $catalog["created_pages"] ?? array();
		$root_dir      = $catalog["root_dir"];

		// Header & Footer
		if ( ! empty( $catalog["template_parts"] ) ) {
			foreach ( $catalog["template_parts"] as $slug => $part ) {
				$res = Align_Zip_Page_Assembler::assemble_template_part( $part, $options, $root_dir );
				if ( "success" === $res["status"] ) {
					$created_pages[] = $res;
				}
			}
		}

		// Reusable Blocks
		if ( ! empty( $catalog["reusable_blocks"] ) ) {
			foreach ( $catalog["reusable_blocks"] as $slug => $block ) {
				$res = Align_Zip_Page_Assembler::assemble_reusable_block( $block, $options, $root_dir );
				if ( "success" === $res["status"] ) {
					$created_pages[] = $res;
				}
			}
		}

		$catalog["created_pages"] = $created_pages;
		self::save_catalog( $session_id, $catalog );

		return array(
			"success" => true,
			"created_parts" => count( $created_pages ),
		);
	}

	/**
	 * Step 5: Process a batch slice of WordPress pages.
	 *
	 * @param string $session_id Session ID.
	 * @param int    $offset     Slice offset.
	 * @param int    $limit      Slice limit.
	 * @param array  $options    Import options.
	 * @return array Batch result.
	 */
	public static function process_pages_batch( string $session_id, int $offset, int $limit, array $options ): array {
		$catalog = self::load_catalog( $session_id );
		if ( ! $catalog ) {
			return array( "success" => false, "message" => __( "Session expired or not found.", "align" ) );
		}

		if ( ! empty( $catalog["url_mapping"] ) ) {
			Align_Zip_Media_Rewriter::set_url_mapping( $catalog["url_mapping"] );
		}

		$pages          = $catalog["pages"] ?? array();
		$slice          = array_slice( $pages, $offset, $limit );
		$created_pages  = $catalog["created_pages"] ?? array();
		$slug_to_id_map = $catalog["slug_to_id_map"] ?? array();
		$path_to_id_map = $catalog["path_to_id_map"] ?? array();
		$root_dir       = $catalog["root_dir"];

		foreach ( $slice as $page_item ) {
			$entity = $page_item['entity'] ?? 'page';
			if ( 'post' === $entity ) {
				$res = class_exists( 'Align_Zip_Post_Assembler' ) ? Align_Zip_Post_Assembler::assemble_post( $page_item, $options, $root_dir ) : array( 'status' => 'error' );
			} elseif ( 'lang_root' === $entity ) {
				$root_pid = $path_to_id_map[ $page_item['path_key'] ] ?? 0;
				$res      = class_exists( 'Align_Zip_Lang_Resolver' ) ? Align_Zip_Lang_Resolver::assemble_lang_root( $page_item, (int) $root_pid, $root_dir ) : array( 'status' => 'error' );
			} else {
				$parent_id = class_exists( 'Align_Zip_Page_Tree' ) ? Align_Zip_Page_Tree::resolve_parent_id( $page_item, $path_to_id_map ) : 0;
				$res       = Align_Zip_Page_Assembler::assemble_page( $page_item, $options, $root_dir, $parent_id );
			}
			if ( 'success' === ( $res['status'] ?? '' ) ) {
				if ( ! empty( $page_item['lang'] ) && class_exists( 'Align_Zip_Lang_Resolver' ) ) {
					Align_Zip_Lang_Resolver::tag_entity( (int) $res['post_id'], $page_item['lang'] );
				}
				$created_pages[] = $res;
				$slug_to_id_map[ $res['slug'] ] = $res['post_id'];
				$pk = $res['path_key'] ?? ( $page_item['path_key'] ?? '' );
				$path_to_id_map[ $pk ] = $res['post_id'];
			}
		}

		$catalog["created_pages"]  = $created_pages;
		$catalog["slug_to_id_map"] = $slug_to_id_map;
		$catalog["path_to_id_map"] = $path_to_id_map;
		self::save_catalog( $session_id, $catalog );

		$processed = min( $offset + count( $slice ), count( $pages ) );
		return array(
			"success"     => true,
			"processed"   => $processed,
			"total"       => count( $pages ),
			"batch_count" => count( $slice ),
		);
	}

	/**
	 * Step 6: Finalize session, rewrite internal links, and cleanup.
	 *
	 * @param string $session_id Session ID.
	 * @return array Final summary payload.
	 */
	public static function finalize_session( string $session_id ): array {
		$catalog = self::load_catalog( $session_id );
		if ( ! $catalog ) {
			return array( "success" => false, "message" => __( "Session expired or not found.", "align" ) );
		}

		$created_pages  = $catalog["created_pages"] ?? array();
		$slug_to_id_map = $catalog["slug_to_id_map"] ?? array();
		$path_to_id_map = $catalog["path_to_id_map"] ?? array();

		// Apply reading settings (Front Page)
		$front_res = array( 'status' => 'none', 'id' => 0 );
		if ( class_exists( 'Align_Zip_Importer' ) && method_exists( 'Align_Zip_Importer', 'apply_front_page' ) ) {
			$front_res = Align_Zip_Importer::apply_front_page( $catalog );
		}

		// Multilingual: Link translation groups and flush rewrite rules before link rewriting
		if ( class_exists( 'Align_Zip_Lang_Resolver' ) && ! empty( $catalog['languages'] ) ) {
			Align_Zip_Lang_Resolver::finalize_multilingual( $path_to_id_map, $catalog );
		}

		// Second Pass: Rewrite internal page links across all created entities (Parts, Blocks, Pages)
		if ( class_exists( 'Align_Zip_Link_Resolver' ) ) {
			$link_map_2tier = Align_Zip_Link_Resolver::build_link_map( $created_pages );
			Align_Zip_Link_Resolver::rewrite_all_internal_links( $created_pages, $link_map_2tier );

			// Ensure localized parts for secondary languages (A-7 / D-8)
			if ( class_exists( 'Align_Zip_Lang_Resolver' ) && ! empty( $catalog['languages'] ) ) {
				foreach ( $catalog['languages'] as $folder_key => $lang_info ) {
					if ( empty( $lang_info['is_default'] ) ) {
						$l_code = $lang_info['code'] ?? $folder_key;
						Align_Zip_Lang_Resolver::ensure_localized_template_parts( $l_code, $catalog, $link_map_2tier );
					}
				}
			}
		}

		// Rewrite URLs in copied global CSS assets using full media mapping
		if ( ! empty( $catalog['global_assets']['css'] ) && ! empty( $catalog['url_mapping'] ) ) {
			Align_Zip_Media_Rewriter::set_url_mapping( $catalog['url_mapping'] );
			$target_dir = wp_normalize_path( trailingslashit( get_stylesheet_directory() ) . 'assets/' );
			if ( ! is_writable( $target_dir ) ) {
				$upload_info = wp_upload_dir();
				$target_dir  = wp_normalize_path( trailingslashit( $upload_info['basedir'] ) . 'align-assets/' );
			}
			foreach ( $catalog['global_assets']['css'] as $orig_css ) {
				$rel_sub  = ( '' !== $catalog['root_dir'] && 0 === strpos( $orig_css, $catalog['root_dir'] ) ) ? preg_replace( '#^(assets|asset|public|static)/#i', '', substr( $orig_css, strlen( $catalog['root_dir'] ) ) ) : basename( $orig_css );
				$dest_css = wp_normalize_path( $target_dir . ltrim( str_replace( '\\', '/', $rel_sub ), '/' ) );
				if ( file_exists( $dest_css ) ) {
					$raw = file_get_contents( $dest_css );
					if ( false !== $raw && false !== stripos( $raw, 'url(' ) ) {
						@file_put_contents( $dest_css, Align_Zip_Media_Rewriter::rewrite_css_urls( $raw, dirname( $orig_css ), $catalog['root_dir'] ) );
					}
				}
			}
		}

		// Cleanup temporary session directory
		Align_Zip_Importer::cleanup_directory( self::get_session_dir( $session_id ) );

		error_log( sprintf( '[Align BatchRunner] Session %s finalized successfully: %d pages/parts created', $session_id, count( $created_pages ) ) );

		return array(
			'success'        => true,
			'message'        => sprintf( __( 'Successfully imported %d pages/parts and %d media files!', 'align' ), count( $created_pages ), $catalog['total_media'] ?? 0 ),
			'pages'          => $created_pages,
			'media_imported' => $catalog['total_media'] ?? 0,
			'front_page'     => $front_res['status'] ?? 'none',
			'front_page_id'  => $front_res['id'] ?? 0,
			'media_missing'  => class_exists( 'Align_Zip_Media_Index' ) ? Align_Zip_Media_Index::get_missing_media() : array(),
		);
	}
}
