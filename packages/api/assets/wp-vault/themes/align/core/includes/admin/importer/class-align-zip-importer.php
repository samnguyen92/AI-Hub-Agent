<?php
/**
 * Align Theme Core Engine - ZIP Importer Master Controller & Orchestrator
 *
 * @package Align
 */

if ( ! defined( "ABSPATH" ) ) {
	exit;
}

require_once __DIR__ . "/class-align-zip-scanner.php";
require_once __DIR__ . "/class-align-zip-page-tree.php";
require_once __DIR__ . "/class-align-zip-lang-resolver.php";
require_once __DIR__ . "/class-align-zip-link-resolver.php";
require_once __DIR__ . "/class-align-zip-pairing.php";
require_once __DIR__ . "/class-align-zip-media-index.php";
require_once __DIR__ . "/class-align-zip-media-rewriter.php";
require_once __DIR__ . "/class-align-zip-page-assembler.php";
require_once __DIR__ . "/class-align-zip-post-assembler.php";
require_once __DIR__ . "/class-align-zip-batch-runner.php";

class Align_Zip_Importer {

	/**
	 * Initialize ZIP Importer hooks.
	 */
	public static function init() {
		add_action( "wp_ajax_align_import_zip_file", array( __CLASS__, "ajax_import_zip_file" ) );
		add_action( "wp_ajax_align_import_init", array( __CLASS__, "ajax_import_init" ) );
		add_action( "wp_ajax_align_import_ai_refactor", array( __CLASS__, "ajax_import_ai_refactor" ) );
		add_action( "wp_ajax_align_import_reset", array( __CLASS__, "ajax_import_reset" ) );
		add_action( "wp_ajax_align_import_media_batch", array( __CLASS__, "ajax_import_media_batch" ) );
		add_action( "wp_ajax_align_import_parts", array( __CLASS__, "ajax_import_parts" ) );
		add_action( "wp_ajax_align_import_pages_batch", array( __CLASS__, "ajax_import_pages_batch" ) );
		add_action( "wp_ajax_align_import_finalize", array( __CLASS__, "ajax_import_finalize" ) );
		add_action( "wp_ajax_align_reset_site_data", array( __CLASS__, "ajax_reset_site_data" ) );
		add_action( "wp_ajax_align_download_sample_template_zip", array( __CLASS__, "ajax_download_sample_template_zip" ) );
		add_action( "wp_ajax_align_check_importer_permissions", array( __CLASS__, "ajax_check_importer_permissions" ) );
	}

	/**
	 * Check write permissions for uploads and themes directory.
	 */
	public static function check_system_permissions(): array {
		$upload_info  = wp_upload_dir();
		$upload_dir   = wp_normalize_path( $upload_info['basedir'] );
		$theme_dir    = wp_normalize_path( get_stylesheet_directory() );
		$norm_abspath = wp_normalize_path( ABSPATH );

		if ( ! is_dir( $upload_dir ) ) {
			wp_mkdir_p( $upload_dir );
		}
		$uploads_writable = is_dir( $upload_dir ) && is_writable( $upload_dir );
		$theme_writable   = is_dir( $theme_dir ) && is_writable( $theme_dir );

		$issues = array();
		if ( ! $uploads_writable ) {
			$issues[] = sprintf( __( 'Uploads directory (%s) is not writable.', 'align' ), str_replace( $norm_abspath, '', $upload_dir ) );
		}
		if ( ! $theme_writable ) {
			$issues[] = sprintf( __( 'Theme directory (%s) is read-only (fallback to uploads/align-assets).', 'align' ), str_replace( $norm_abspath, '', $theme_dir ) );
		}

		return array(
			'all_writable'     => $uploads_writable && $theme_writable,
			'uploads_writable' => $uploads_writable,
			'theme_writable'   => $theme_writable,
			'upload_path'      => str_replace( $norm_abspath, '', $upload_dir ),
			'theme_path'       => str_replace( $norm_abspath, '', $theme_dir ),
			'issues'           => $issues,
			'commands'         => array(
				'chmod -R 775 ' . escapeshellarg( $upload_dir ),
				'chmod -R 775 ' . escapeshellarg( $theme_dir ),
				'chown -R www-data:www-data ' . escapeshellarg( wp_normalize_path( WP_CONTENT_DIR ) ),
			),
		);
	}

	public static function ajax_check_importer_permissions() {
		check_ajax_referer( "align_settings_nonce", "nonce" );
		if ( ! current_user_can( "manage_options" ) ) wp_send_json_error( array( "message" => "Unauthorized." ), 403 );
		wp_send_json_success( self::check_system_permissions() );
	}

	public static function ajax_import_init() {
		check_ajax_referer( "align_settings_nonce", "nonce" );
		if ( ! current_user_can( "manage_options" ) ) wp_send_json_error( array( "message" => "Unauthorized" ), 403 );
		if ( empty( $_FILES["zip_file"] ) || ! is_uploaded_file( $_FILES["zip_file"]["tmp_name"] ) ) {
			wp_send_json_error( array( "message" => __( "No ZIP file uploaded.", "align" ) ), 400 );
		}
		$perms = self::check_system_permissions();
		if ( ! $perms["uploads_writable"] ) {
			wp_send_json_error( array( "permission_error" => true, "message" => __( "Uploads directory is not writable.", "align" ) ), 403 );
		}
		$res = Align_Zip_Batch_Runner::init_session( $_FILES["zip_file"]["tmp_name"], $_FILES["zip_file"]["name"] );
		if ( ! empty( $res["success"] ) ) wp_send_json_success( $res );
		else wp_send_json_error( $res, 400 );
	}

	public static function ajax_import_ai_refactor() {
		check_ajax_referer( "align_settings_nonce", "nonce" );
		if ( ! current_user_can( "manage_options" ) ) wp_send_json_error( array( "message" => "Unauthorized" ), 403 );
		$sid = sanitize_text_field( $_POST["session_id"] ?? "" );

		$ai_config = array(
			"ai_endpoint" => esc_url_raw( $_POST["ai_endpoint"] ?? "https://openrouter.ai/api/v1/chat/completions" ),
			"ai_api_key"  => sanitize_text_field( $_POST["ai_api_key"] ?? "" ),
			"ai_model"    => sanitize_text_field( $_POST["ai_model"] ?? "google/gemini-2.5-flash" ),
		);

		if ( ! empty( $ai_config["ai_api_key"] ) ) {
			update_option( "align_openrouter_api_key", $ai_config["ai_api_key"] );
		}
		if ( ! empty( $ai_config["ai_model"] ) ) {
			update_option( "align_openrouter_model", $ai_config["ai_model"] );
		}

		$res = Align_Zip_Batch_Runner::process_ai_refactor( $sid, $ai_config );
		if ( ! empty( $res["success"] ) ) wp_send_json_success( $res );
		else wp_send_json_error( $res, 400 );
	}

	public static function ajax_import_reset() {
		check_ajax_referer( "align_settings_nonce", "nonce" );
		$sid = sanitize_text_field( $_POST["session_id"] ?? "" );
		$opt = array( "overwrite" => ! empty( $_POST["overwrite"] ) );
		$res = Align_Zip_Batch_Runner::reset_and_register_assets( $sid, $opt );
		if ( ! empty( $res["success"] ) ) wp_send_json_success( $res );
		else wp_send_json_error( $res, 400 );
	}

	public static function ajax_import_media_batch() {
		check_ajax_referer( "align_settings_nonce", "nonce" );
		$sid = sanitize_text_field( $_POST["session_id"] ?? "" );
		$off = intval( $_POST["offset"] ?? 0 );
		$lim = intval( $_POST["limit"] ?? 10 );
		$cwb = ! isset( $_POST["convert_webp"] ) || ! empty( $_POST["convert_webp"] );
		$res = Align_Zip_Batch_Runner::process_media_batch( $sid, $off, $lim, $cwb );
		if ( ! empty( $res["success"] ) ) wp_send_json_success( $res );
		else wp_send_json_error( $res, 400 );
	}

	public static function ajax_import_parts() {
		check_ajax_referer( "align_settings_nonce", "nonce" );
		$sid = sanitize_text_field( $_POST["session_id"] ?? "" );
		$opt = array( "minify" => ! empty( $_POST["minify"] ), "overwrite" => ! empty( $_POST["overwrite"] ) );
		$res = Align_Zip_Batch_Runner::process_parts_and_blocks( $sid, $opt );
		if ( ! empty( $res["success"] ) ) wp_send_json_success( $res );
		else wp_send_json_error( $res, 400 );
	}

	public static function ajax_import_pages_batch() {
		check_ajax_referer( "align_settings_nonce", "nonce" );
		$sid = sanitize_text_field( $_POST["session_id"] ?? "" );
		$off = intval( $_POST["offset"] ?? 0 );
		$lim = intval( $_POST["limit"] ?? 5 );
		$opt = array( "minify" => ! empty( $_POST["minify"] ), "overwrite" => ! empty( $_POST["overwrite"] ) );
		$res = Align_Zip_Batch_Runner::process_pages_batch( $sid, $off, $lim, $opt );
		if ( ! empty( $res["success"] ) ) wp_send_json_success( $res );
		else wp_send_json_error( $res, 400 );
	}

	public static function ajax_import_finalize() {
		check_ajax_referer( "align_settings_nonce", "nonce" );
		$sid = sanitize_text_field( $_POST["session_id"] ?? "" );
		$res = Align_Zip_Batch_Runner::finalize_session( $sid );
		if ( ! empty( $res["success"] ) ) wp_send_json_success( $res );
		else wp_send_json_error( $res, 400 );
	}

	public static function ajax_download_sample_template_zip() {
		check_ajax_referer( "align_settings_nonce", "nonce" );
		if ( ! current_user_can( "manage_options" ) ) wp_die( esc_html__( "Unauthorized.", "align" ), 403 );
		require_once __DIR__ . "/class-align-zip-sample-generator.php";
		Align_Zip_Sample_Generator::generate_and_stream_zip();
	}

	public static function ajax_import_zip_file() {
		check_ajax_referer( "align_settings_nonce", "nonce" );
		if ( ! current_user_can( "manage_options" ) ) wp_send_json_error( array( "message" => "Unauthorized." ), 403 );
		if ( empty( $_FILES["zip_file"] ) || ! is_uploaded_file( $_FILES["zip_file"]["tmp_name"] ) ) {
			wp_send_json_error( array( "message" => __( "No ZIP file was uploaded.", "align" ) ), 400 );
		}
		$perms = self::check_system_permissions();
		if ( ! $perms["uploads_writable"] ) {
			wp_send_json_error( array( "permission_error" => true, "message" => __( "Uploads directory is not writable.", "align" ), "details" => $perms ), 403 );
		}
		$options = array(
			"overwrite"    => ! empty( $_POST["overwrite"] ),
			"minify"       => ! empty( $_POST["minify"] ),
			"convert_webp" => ! isset( $_POST["convert_webp"] ) || ! empty( $_POST["convert_webp"] ),
			"import_media" => ! isset( $_POST["import_media"] ) || ! empty( $_POST["import_media"] ),
			"ai_refactor"  => ! empty( $_POST["ai_refactor"] ),
		);
		$result = self::process_zip_file( $_FILES["zip_file"]["tmp_name"], $_FILES["zip_file"]["name"], $options );
		if ( ! empty( $result["success"] ) ) wp_send_json_success( $result );
		else wp_send_json_error( $result, 400 );
	}

	public static function rest_import_zip( $request ) {
		$files = $request->get_file_params();
		if ( empty( $files["zip_file"] ) ) return new WP_REST_Response( array( "success" => false, "message" => "No zip_file parameter." ), 400 );
		$file    = $files["zip_file"];
		$options = array(
			"overwrite"    => (bool) $request->get_param( "overwrite" ),
			"minify"       => (bool) $request->get_param( "minify" ),
			"convert_webp" => (bool) $request->get_param( "convert_webp" ),
			"import_media" => true,
			"ai_refactor"  => (bool) $request->get_param( "ai_refactor" ),
		);
		$result  = self::process_zip_file( $file["tmp_name"], $file["name"], $options );
		return new WP_REST_Response( $result, ! empty( $result["success"] ) ? 200 : 400 );
	}

	/**
	 * Core Orchestration: Validate, Unzip, Scan, Rewrite Media, and Assemble Pages.
	 */
	public static function process_zip_file( string $zip_path, string $orig_name, array $options = array() ): array {
		$start_time = microtime( true );
		$init = Align_Zip_Batch_Runner::init_session( $zip_path, $orig_name );
		if ( empty( $init["success"] ) ) return $init;

		$sid = $init["session_id"];

		// Optional: AI Semantic Refactoring before site reset and media sideload
		if ( ! empty( $options["ai_refactor"] ) ) {
			$ref_res = Align_Zip_Batch_Runner::process_ai_refactor( $sid );
			if ( ! empty( $ref_res["success"] ) ) {
				$init["total_pages"] = $ref_res["total_pages"] ?? $init["total_pages"];
				$init["total_media"] = $ref_res["total_media"] ?? $init["total_media"];
			}
		}

		Align_Zip_Batch_Runner::reset_and_register_assets( $sid, $options );

		if ( ! empty( $init["total_media"] ) && ( ! isset( $options["import_media"] ) || ! empty( $options["import_media"] ) ) ) {
			$cwb = ! isset( $options["convert_webp"] ) || ! empty( $options["convert_webp"] );
			Align_Zip_Batch_Runner::process_media_batch( $sid, 0, $init["total_media"], $cwb );
		}

		Align_Zip_Batch_Runner::process_parts_and_blocks( $sid, $options );

		if ( ! empty( $init["total_pages"] ) ) {
			Align_Zip_Batch_Runner::process_pages_batch( $sid, 0, $init["total_pages"], $options );
		}

		$res = Align_Zip_Batch_Runner::finalize_session( $sid );
		$res["execution_time"] = round( microtime( true ) - $start_time, 2 ) . "s";
		return $res;
	}

	public static function execute_full_site_reset( bool $deactivate_plugins = false ): array {
		$deleted_pages = 0; $deleted_media = 0; $deactivated_plugins = 0;
		$post_types = array( "page", "post", "wp_block", "wp_template_part", "wp_template", "wp_navigation", "nav_menu_item" );
		$all_posts  = get_posts( array( "post_type" => $post_types, "post_status" => array( "publish", "draft", "private", "trash", "future", "inherit" ), "posts_per_page" => -1, "fields" => "ids" ) );
		foreach ( $all_posts as $p_id ) {
			if ( class_exists( "Align_Builder_Asset_Cleaner" ) && method_exists( "Align_Builder_Asset_Cleaner", "delete_post_assets" ) ) {
				Align_Builder_Asset_Cleaner::delete_post_assets( $p_id );
			}
			if ( wp_delete_post( $p_id, true ) ) $deleted_pages++;
		}
		foreach ( wp_get_nav_menus() ?: array() as $m ) wp_delete_nav_menu( $m->term_id );

		update_option( "show_on_front", "posts" );
		delete_option( "page_on_front" );
		delete_option( "page_for_posts" );

		foreach ( get_posts( array( "post_type" => "attachment", "post_status" => "inherit", "posts_per_page" => -1, "fields" => "ids" ) ) as $att_id ) {
			if ( wp_delete_attachment( $att_id, true ) ) $deleted_media++;
		}

		$parts_dir = wp_normalize_path( get_stylesheet_directory() . '/parts' );
		if ( is_dir( $parts_dir ) ) {
			$blank = "<!-- wp:align/custom-html {\"blockId\":\"align-%s\",\"enableShortcode\":true} -->\n<!-- /wp:align/custom-html -->\n";
			@file_put_contents( wp_normalize_path( trailingslashit( $parts_dir ) . 'header.html' ), sprintf( $blank, 'header' ) );
			@file_put_contents( wp_normalize_path( trailingslashit( $parts_dir ) . 'footer.html' ), sprintf( $blank, 'footer' ) );
		}
		$tpl_dir = wp_normalize_path( get_stylesheet_directory() . '/templates' );
		if ( is_dir( $tpl_dir ) ) {
			$clean_tpl = "<!-- wp:template-part {\"slug\":\"header\",\"tagName\":\"header\"} /-->\n\n<!-- wp:post-content /-->\n\n<!-- wp:template-part {\"slug\":\"footer\",\"tagName\":\"footer\"} /-->\n";
			foreach ( array( 'index.html', 'index-template.html', 'page.html', 'single.html' ) as $tf ) {
				@file_put_contents( wp_normalize_path( trailingslashit( $tpl_dir ) . $tf ), $clean_tpl );
			}
		}

		if ( class_exists( 'Align_Builder_Assets' ) && method_exists( 'Align_Builder_Assets', 'get_builder_assets_dir' ) ) {
			$b_dir = wp_normalize_path( Align_Builder_Assets::get_builder_assets_dir() );
			if ( is_dir( $b_dir ) ) {
				self::cleanup_directory_files_only( $b_dir );
			}
		}
		$child_assets = wp_normalize_path( get_stylesheet_directory() . '/assets' );
		if ( is_dir( $child_assets ) ) {
			self::cleanup_directory( $child_assets );
			wp_mkdir_p( $child_assets );
		}
		$align_upload = wp_normalize_path( trailingslashit( wp_upload_dir()['basedir'] ) . 'align-assets' );
		if ( is_dir( $align_upload ) ) {
			self::cleanup_directory( $align_upload );
			wp_mkdir_p( $align_upload );
		}
		update_option( 'align_loaded_assets', array() );

		if ( $deactivate_plugins ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			$active = get_option( 'active_plugins', array() );
			if ( ! empty( $active ) && is_array( $active ) ) {
				$deactivated_plugins = count( $active );
				deactivate_plugins( $active, true );
			}
		}

		if ( function_exists( 'wp_clean_theme_json_cache' ) ) {
			wp_clean_theme_json_cache();
		}
		return array( 'deleted_pages' => $deleted_pages, 'deleted_media' => $deleted_media, 'deactivated_plugins' => $deactivated_plugins );
	}

	public static function ajax_reset_site_data() {
		check_ajax_referer( 'align_settings_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}
		$stats = self::execute_full_site_reset( true );
		wp_send_json_success( array(
			'message'             => sprintf( __( 'Factory Reset complete: Cleared %d items, %d media files, deactivated %d plugins.', 'align' ), $stats['deleted_pages'], $stats['deleted_media'], $stats['deactivated_plugins'] ),
			'deleted_pages'       => $stats['deleted_pages'],
			'deleted_media'       => $stats['deleted_media'],
			'deactivated_plugins' => $stats['deactivated_plugins'],
		) );
	}

	public static function cleanup_directory_files_only( string $dir ): void {
		$dir = wp_normalize_path( $dir );
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( array_diff( scandir( $dir ), array( '.', '..' ) ) as $item ) {
			$p = wp_normalize_path( $dir . '/' . $item );
			if ( is_file( $p ) ) {
				@unlink( $p );
			}
		}
	}

	public static function cleanup_directory( string $dir ): void {
		$dir = wp_normalize_path( $dir );
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $items as $item ) {
			$item_path = wp_normalize_path( $item->getPathname() );
			if ( $item->isDir() ) {
				@rmdir( $item_path );
			} else {
				@unlink( $item_path );
			}
		}
		@rmdir( $dir );
	}

	public static function copy_and_register_global_assets( array $global_assets, string $root_dir = '' ): void {
		$root_dir        = wp_normalize_path( trailingslashit( $root_dir ) );
		$target_dir      = wp_normalize_path( trailingslashit( get_stylesheet_directory() ) . 'assets/' );
		$target_url_base = '/wp-content/themes/' . get_stylesheet() . '/assets/';
		if ( ! is_dir( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}

		if ( ! is_writable( $target_dir ) ) {
			$upload_dir      = wp_upload_dir();
			$target_dir      = wp_normalize_path( trailingslashit( $upload_dir['basedir'] ) . 'align-assets/' );
			$target_url_base = '/wp-content/uploads/align-assets/';
			if ( ! is_dir( $target_dir ) ) {
				wp_mkdir_p( $target_dir );
			}
		}

		$configs = array(
			'css'   => array( 'type' => 'css', 'loc' => 'header', 'load' => 'normal' ),
			'js'    => array( 'type' => 'js', 'loc' => 'footer', 'load' => 'defer' ),
			'fonts' => array( 'type' => 'font', 'loc' => 'header', 'load' => 'normal' ),
		);

		foreach ( $configs as $key => $cfg ) {
			if ( empty( $global_assets[ $key ] ) ) {
				continue;
			}
			$asset_list = $global_assets[ $key ];
			if ( 'js' === $key ) {
				usort( $asset_list, function( $a, $b ) {
					$a_vendor = preg_match( '#(vendor|plugins|libs|gsap|lenis|scrolltrigger)#i', $a );
					$b_vendor = preg_match( '#(vendor|plugins|libs|gsap|lenis|scrolltrigger)#i', $b );
					return ( $a_vendor && ! $b_vendor ) ? -1 : ( ( ! $a_vendor && $b_vendor ) ? 1 : 0 );
				} );
			}
			foreach ( $asset_list as $file_path ) {
				$file_path = wp_normalize_path( $file_path );
				if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
					continue;
				}
				$rel_sub = ( '' !== $root_dir && 0 === strpos( $file_path, $root_dir ) )
					? preg_replace( '#^(assets|asset|public|static)/#i', '', substr( $file_path, strlen( $root_dir ) ) )
					: basename( $file_path );

				$rel_sub   = ltrim( str_replace( '\\', '/', $rel_sub ), '/' );
				$dest_file = wp_normalize_path( $target_dir . $rel_sub );
				$dest_dir  = wp_normalize_path( dirname( $dest_file ) );
				if ( ! is_dir( $dest_dir ) ) {
					wp_mkdir_p( $dest_dir );
				}

				$written = @copy( $file_path, $dest_file ) || ( function_exists( 'file_get_contents' ) && false !== @file_put_contents( $dest_file, file_get_contents( $file_path ) ) );
				if ( $written && file_exists( $dest_file ) ) {
					$rel_url = $target_url_base . $rel_sub;
					$fname   = basename( $file_path );
					$loc     = $cfg['loc'];
					$load    = $cfg['load'];
					if ( 'js' === $cfg['type'] && ( false !== stripos( $fname, 'jquery' ) || false !== stripos( $fname, 'bootstrap' ) ) ) {
						$loc  = 'header';
						$load = 'normal';
					}
					Align_Zip_Page_Assembler::register_loaded_asset( $rel_url, $cfg['type'], $loc, $load );
					error_log( sprintf( '[Align ZipImporter] Copied asset %s -> %s (Registered: %s)', $fname, $dest_file, $rel_url ) );
				}
			}
		}
	}

	/**
	 * Apply Reading settings safely at finalization.
	 *
	 * @param array $catalog Session catalog.
	 * @return array
	 */
	public static function apply_front_page( array $catalog ): array {
		if ( class_exists( 'Align_Zip_Media_Index' ) ) {
			return Align_Zip_Media_Index::apply_front_page( $catalog );
		}
		return array( 'status' => 'none', 'id' => 0 );
	}
}
