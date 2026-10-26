<?php
/**
 * Custom Asset Management, Upload, and Deletion Engine for Align Theme.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Admin_Assets {

	/**
	 * Initialize hooks for admin custom asset management.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'migrate_legacy_assets' ) );
		add_filter( 'upload_mimes', array( __CLASS__, 'allow_custom_asset_mimes' ) );
		add_action( 'wp_ajax_align_upload_asset_file', array( __CLASS__, 'ajax_upload_asset_file' ) );
		add_action( 'wp_ajax_align_delete_asset_file', array( __CLASS__, 'ajax_delete_asset_file' ) );
		add_action( 'update_option_align_enable_delay_assets', array( __CLASS__, 'on_toggle_delay_assets' ), 10, 2 );
		add_action( 'add_option_align_enable_delay_assets', array( __CLASS__, 'on_add_delay_assets' ), 10, 2 );
	}

	/**
	 * Allow CSS, JS, and Font file uploads in media library for admin users.
	 *
	 * @param array $mimes Allowed mime types.
	 * @return array Modified mime types.
	 */
	public static function allow_custom_asset_mimes( $mimes ) {
		if ( current_user_can( 'manage_options' ) ) {
			$mimes['css']   = 'text/css';
			$mimes['js']    = 'application/javascript';
			$mimes['woff']  = 'font/woff';
			$mimes['woff2'] = 'font/woff2';
			$mimes['ttf']   = 'font/ttf';
			$mimes['otf']   = 'font/otf';
			$mimes['eot']   = 'application/vnd.ms-fontobject';
		}
		return $mimes;
	}

	/**
	 * Convert internal local asset URL to root-relative path.
	 */
	public static function get_relative_asset_url( $url ) {
		if ( empty( $url ) ) {
			return '';
		}
		$trimmed = trim( $url );
		if ( 0 === strpos( $trimmed, '//' ) ) {
			return esc_url_raw( $trimmed );
		}
		if ( 0 === strpos( $trimmed, '/wp-content/' ) ) {
			return $trimmed;
		}
		if ( 0 === strpos( $trimmed, 'wp-content/' ) ) {
			return '/' . $trimmed;
		}

		$child_theme_uri  = rtrim( get_stylesheet_directory_uri(), '/' );
		$parent_theme_uri = rtrim( get_template_directory_uri(), '/' );
		$upload_baseurl   = rtrim( wp_upload_dir()['baseurl'] ?? '', '/' );

		if ( ( ! empty( $child_theme_uri ) && 0 === strpos( $trimmed, $child_theme_uri ) ) || ( ! empty( $parent_theme_uri ) && 0 === strpos( $trimmed, $parent_theme_uri ) ) || ( ! empty( $upload_baseurl ) && 0 === strpos( $trimmed, $upload_baseurl ) ) ) {
			$parsed = wp_parse_url( $trimmed );
			$path   = $parsed['path'] ?? '';
			$query  = isset( $parsed['query'] ) ? '?' . $parsed['query'] : '';
			return '/' . ltrim( $path, '/' ) . $query;
		}

		$site_url = rtrim( site_url(), '/' );
		$home_url = rtrim( home_url(), '/' );
		if ( ! empty( $site_url ) && 0 === strpos( $trimmed, $site_url ) ) {
			return '/' . ltrim( substr( $trimmed, strlen( $site_url ) ), '/' );
		}
		if ( ! empty( $home_url ) && 0 === strpos( $trimmed, $home_url ) ) {
			return '/' . ltrim( substr( $trimmed, strlen( $home_url ) ), '/' );
		}

		$parsed_url  = wp_parse_url( $trimmed );
		$parsed_site = wp_parse_url( $site_url );
		if ( isset( $parsed_url['host'], $parsed_site['host'] ) && strtolower( $parsed_url['host'] ) === strtolower( $parsed_site['host'] ) ) {
			$path      = $parsed_url['path'] ?? '';
			$site_path = isset( $parsed_site['path'] ) ? rtrim( $parsed_site['path'], '/' ) : '';
			if ( ! empty( $site_path ) && 0 === strpos( $path, $site_path ) ) {
				$path = substr( $path, strlen( $site_path ) );
			}
			$query    = isset( $parsed_url['query'] ) ? '?' . $parsed_url['query'] : '';
			$fragment = isset( $parsed_url['fragment'] ) ? '#' . $parsed_url['fragment'] : '';
			return '/' . ltrim( $path, '/' ) . $query . $fragment;
		}
		return esc_url_raw( $trimmed );
	}

	/**
	 * Get definitions of active core plugin assets.
	 */
	public static function get_core_plugin_assets(): array {
		$core          = array();
		$delay_enabled = (int) get_option( 'align_enable_delay_assets', 0 ) === 1;
		$js_strategy   = $delay_enabled ? 'delay' : 'defer';
		$css_strategy  = $delay_enabled ? 'delay' : 'normal';

		if ( (int) get_option( 'align_enable_lenis_scroll', 0 ) === 1 ) {
			$core['/wp-content/themes/align/core/assets/plugins/smooth-scroll/lenis.min.js'] = array(
				'url' => '/wp-content/themes/align/core/assets/plugins/smooth-scroll/lenis.min.js', 'type' => 'js', 'location' => 'footer', 'loading' => $js_strategy, 'enabled' => 1, 'is_core' => 1, 'plugin_title' => 'Lenis Smooth Scroll',
			);
		}
		if ( (int) get_option( 'align_enable_splidejs', 0 ) === 1 ) {
			$core['/wp-content/themes/align/core/assets/plugins/splide/splide.min.css'] = array(
				'url' => '/wp-content/themes/align/core/assets/plugins/splide/splide.min.css', 'type' => 'css', 'location' => 'header', 'loading' => $css_strategy, 'enabled' => 1, 'is_core' => 1, 'plugin_title' => 'Splide.js CSS',
			);
			$core['/wp-content/themes/align/core/assets/plugins/splide/splide.min.js'] = array(
				'url' => '/wp-content/themes/align/core/assets/plugins/splide/splide.min.js', 'type' => 'js', 'location' => 'footer', 'loading' => $js_strategy, 'enabled' => 1, 'is_core' => 1, 'plugin_title' => 'Splide.js Core',
			);
			$core['/wp-content/themes/align/core/assets/plugins/splide/splide-extension-auto-scroll.min.js'] = array(
				'url' => '/wp-content/themes/align/core/assets/plugins/splide/splide-extension-auto-scroll.min.js', 'type' => 'js', 'location' => 'footer', 'loading' => $js_strategy, 'enabled' => 1, 'is_core' => 1, 'plugin_title' => 'Splide Auto-Scroll',
			);
		}
		return $core;
	}

	/**
	 * Get loaded assets merged with active core plugin assets.
	 */
	public static function get_effective_loaded_assets(): array {
		$saved_assets = get_option( 'align_loaded_assets', array() );
		if ( ! is_array( $saved_assets ) ) {
			$saved_assets = array();
		}

		$delay_enabled = (int) get_option( 'align_enable_delay_assets', 0 ) === 1;
		$core_assets   = self::get_core_plugin_assets();
		$merged        = array();
		$matched_urls  = array();

		foreach ( $saved_assets as $asset ) {
			if ( ! is_array( $asset ) || empty( $asset['url'] ) ) {
				continue;
			}
			$url         = $asset['url'];
			$is_core_url = strpos( $url, '/align/core/assets/plugins/' ) !== false;

			if ( isset( $core_assets[ $url ] ) ) {
				$asset['is_core']      = 1;
				$asset['plugin_title'] = $core_assets[ $url ]['plugin_title'];
				if ( empty( $asset['loading'] ) || ( ! $delay_enabled && 'delay' === $asset['loading'] ) ) {
					$asset['loading'] = $core_assets[ $url ]['loading'];
				}
				$merged[]       = $asset;
				$matched_urls[] = $url;
			} elseif ( ! $is_core_url ) {
				if ( ! $delay_enabled && isset( $asset['loading'] ) && 'delay' === $asset['loading'] ) {
					$asset['loading'] = ( isset( $asset['type'] ) && 'js' === $asset['type'] ) ? 'defer' : 'normal';
				}
				$merged[] = $asset;
			}
		}

		foreach ( $core_assets as $url => $core_item ) {
			if ( ! in_array( $url, $matched_urls, true ) ) {
				$merged[] = $core_item;
			}
		}

		return $merged;
	}

	/**
	 * Sanitize loaded assets array submitted from admin settings.
	 *
	 * @param mixed $input Input array from form submission.
	 * @return array Sanitized array of loaded assets.
	 */
	public static function sanitize_loaded_assets( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$delay_enabled   = (int) get_option( 'align_enable_delay_assets', 0 ) === 1;
		$allowed_loading = $delay_enabled ? array( 'normal', 'defer', 'async', 'delay' ) : array( 'normal', 'defer', 'async' );
		$sanitized       = array();

		foreach ( $input as $asset ) {
			if ( ! is_array( $asset ) ) {
				continue;
			}
			$raw_url = isset( $asset['url'] ) ? esc_url_raw( wp_unslash( $asset['url'] ) ) : '';
			if ( empty( $raw_url ) ) {
				continue;
			}

			$url      = esc_url_raw( self::get_relative_asset_url( $raw_url ) );
			$type     = isset( $asset['type'] ) && in_array( strtolower( $asset['type'] ), array( 'css', 'js', 'font' ), true ) ? strtolower( $asset['type'] ) : 'css';
			$location = isset( $asset['location'] ) && in_array( strtolower( $asset['location'] ), array( 'header', 'footer' ), true ) ? strtolower( $asset['location'] ) : 'header';
			$loading  = isset( $asset['loading'] ) && in_array( strtolower( $asset['loading'] ), $allowed_loading, true ) ? strtolower( $asset['loading'] ) : ( 'js' === $type ? 'defer' : 'normal' );
			$enabled   = ! empty( $asset['enabled'] ) ? 1 : 0;
			$is_core   = ! empty( $asset['is_core'] ) ? 1 : 0;
			$is_inline = ! empty( $asset['is_inline'] ) && 'font' !== $type ? 1 : 0;

			$clean_path = strtolower( strtok( $url, '?#' ) );
			$ext        = pathinfo( $clean_path, PATHINFO_EXTENSION );
			$font_weight = '';
			if ( in_array( $ext, array( 'woff2', 'woff', 'ttf', 'otf', 'eot' ), true ) ) {
				$type        = 'font';
				$loading     = 'normal';
				$is_inline   = 0;
				$font_weight = isset( $asset['font_weight'] ) && ! empty( $asset['font_weight'] ) ? sanitize_text_field( $asset['font_weight'] ) : ( class_exists( 'Align_Font_Manager' ) ? Align_Font_Manager::detect_weight_from_filename( $url ) : '400' );
			} elseif ( 'js' === $ext || false !== stripos( $url, '/api/js' ) || preg_match( '/\.(js)(\?|$)/i', $url ) ) {
				$type    = 'js';
				$loading = in_array( $loading, $allowed_loading, true ) ? $loading : ( 'header' === $location ? 'normal' : 'defer' );
			} elseif ( 'css' === $ext || false !== stripos( $url, 'fonts.googleapis.com' ) || preg_match( '/\.(css)(\?|$)/i', $url ) ) {
				$type    = 'css';
				$loading = ( $delay_enabled && 'delay' === $loading ) ? 'delay' : 'normal';
			}

			$sanitized[] = array(
				'url'         => $url,
				'type'        => $type,
				'location'    => $location,
				'loading'     => $loading,
				'font_weight' => $font_weight,
				'is_inline'   => $is_inline,
				'enabled'     => $enabled,
				'is_core'     => $is_core,
			);
		}

		if ( class_exists( 'Align_Font_Manager' ) ) {
			Align_Font_Manager::compile_from_loaded_assets( $sanitized );
		}

		return $sanitized;
	}

	/**
	 * Safely resolve local root-relative or absolute URLs to the physical file path.
	 *
	 * @param string $url_or_path URL or file path.
	 * @return string|false Normalized absolute file path, or false if external or unresolvable.
	 */
	public static function resolve_asset_filesystem_path( $url_or_path ) {
		if ( empty( $url_or_path ) ) {
			return false;
		}

		$clean = preg_replace( '/[?#].*$/', '', trim( $url_or_path ) );
		if ( file_exists( $clean ) && is_file( $clean ) ) {
			return wp_normalize_path( realpath( $clean ) );
		}

		$rel = self::get_relative_asset_url( $clean );
		if ( preg_match( '#^(https?:)?//#i', $rel ) ) {
			return false;
		}

		$rel_clean        = '/' . ltrim( $rel, '/' );
		$content_dir_name = basename( WP_CONTENT_DIR );
		$file_path        = ( 0 === strpos( $rel_clean, '/' . $content_dir_name . '/' ) )
			? trailingslashit( WP_CONTENT_DIR ) . substr( $rel_clean, strlen( '/' . $content_dir_name . '/' ) )
			: trailingslashit( ABSPATH ) . ltrim( $rel_clean, '/' );

		$real_path = realpath( $file_path );
		return ( false !== $real_path ) ? wp_normalize_path( $real_path ) : wp_normalize_path( $file_path );
	}

	/**
	 * Verify if a physical file path is strictly inside allowed asset directories.
	 */
	public static function is_safe_asset_path( $file_path ) {
		if ( empty( $file_path ) ) {
			return false;
		}

		$real_path = realpath( $file_path );
		if ( false === $real_path || ! is_file( $real_path ) ) {
			return false;
		}

		$normalized_real = wp_normalize_path( $real_path );
		$ext             = strtolower( pathinfo( $normalized_real, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'css', 'js', 'woff2', 'woff', 'ttf', 'otf', 'eot', 'map' ), true ) ) {
			return false;
		}

		$upload_info  = wp_upload_dir();
		$allowed_dirs = array(
			wp_normalize_path( trailingslashit( get_stylesheet_directory() ) . 'assets/' ),
			wp_normalize_path( trailingslashit( get_template_directory() ) . 'assets/' ),
			wp_normalize_path( trailingslashit( $upload_info['basedir'] ) . 'align-assets/' ),
			wp_normalize_path( trailingslashit( $upload_info['basedir'] ) ),
		);

		foreach ( $allowed_dirs as $allowed_dir ) {
			$allowed_dir_real = realpath( $allowed_dir );
			$check_dir        = ( false !== $allowed_dir_real ) ? wp_normalize_path( $allowed_dir_real ) : $allowed_dir;
			if ( 0 === strpos( $normalized_real, trailingslashit( $check_dir ) ) || $normalized_real === $check_dir ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Handle AJAX upload of custom asset files (.css, .js, and fonts).
	 */
	public static function ajax_upload_asset_file() {
		$nonce = $_POST['align_settings_nonce'] ?? $_POST['nonce'] ?? '';
		if ( ! wp_verify_nonce( $nonce, 'align_settings_nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized or invalid token.', 'align' ) ) );
		}
		if ( empty( $_FILES ) ) {
			wp_send_json_error( array( 'message' => __( 'No files were uploaded.', 'align' ) ) );
		}

		$target_dir = trailingslashit( get_stylesheet_directory() ) . 'assets/';
		$target_url = trailingslashit( get_stylesheet_directory_uri() ) . 'assets/';
		if ( ! file_exists( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}

		if ( ! is_writable( $target_dir ) ) {
			$upload_info = wp_upload_dir();
			$target_dir  = trailingslashit( $upload_info['basedir'] ) . 'align-assets/';
			$target_url  = trailingslashit( $upload_info['baseurl'] ) . 'align-assets/';
			if ( ! file_exists( $target_dir ) ) {
				wp_mkdir_p( $target_dir );
			}
		}

		$uploaded_files   = array();
		$files_to_process = array();

		foreach ( $_FILES as $file_group ) {
			if ( is_array( $file_group['name'] ) ) {
				foreach ( $file_group['name'] as $idx => $name ) {
					$files_to_process[] = array(
						'name'     => $name,
						'tmp_name' => $file_group['tmp_name'][ $idx ] ?? '',
						'error'    => $file_group['error'][ $idx ] ?? UPLOAD_ERR_OK,
					);
				}
			} else {
				$files_to_process[] = $file_group;
			}
		}

		$font_exts = array( 'woff2', 'woff', 'ttf', 'otf', 'eot' );

		foreach ( $files_to_process as $file ) {
			if ( empty( $file['tmp_name'] ) || $file['error'] !== UPLOAD_ERR_OK ) {
				continue;
			}
			$original_name = sanitize_file_name( $file['name'] );
			$ext           = strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, array_merge( array( 'css', 'js' ), $font_exts ), true ) ) {
				continue;
			}
			$asset_type      = in_array( $ext, $font_exts, true ) ? 'font' : $ext;
			$unique_filename = wp_unique_filename( $target_dir, $original_name );
			$destination     = $target_dir . $unique_filename;

			if ( move_uploaded_file( $file['tmp_name'], $destination ) ) {
				error_log( '[Align AdminAssets] Uploaded: ' . $destination );
				$uploaded_files[] = array(
					'url'  => self::get_relative_asset_url( $target_url . $unique_filename ),
					'type' => $asset_type,
					'name' => $unique_filename,
				);
			}
		}

		if ( empty( $uploaded_files ) ) {
			wp_send_json_error( array( 'message' => __( 'No valid .css, .js, or font files were uploaded.', 'align' ) ) );
		}
		wp_send_json_success( array( 'files' => $uploaded_files ) );
	}

	/**
	 * Handle AJAX permanent deletion of custom asset physical file.
	 */
	public static function ajax_delete_asset_file() {
		$nonce = $_POST['align_settings_nonce'] ?? $_POST['nonce'] ?? '';
		if ( ! wp_verify_nonce( $nonce, 'align_settings_nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized or invalid token.', 'align' ) ) );
		}
		$raw_url = isset( $_POST['url'] ) ? sanitize_text_field( wp_unslash( $_POST['url'] ) ) : '';
		if ( empty( $raw_url ) ) {
			wp_send_json_success( array( 'message' => __( 'No URL provided.', 'align' ) ) );
		}
		$resolved_path = self::resolve_asset_filesystem_path( $raw_url );
		if ( false === $resolved_path ) {
			self::remove_asset_from_option( $raw_url );
			wp_send_json_success( array( 'message' => __( 'External asset removed.', 'align' ), 'is_external' => true ) );
		}
		if ( ! self::is_safe_asset_path( $resolved_path ) ) {
			if ( ! file_exists( $resolved_path ) ) {
				self::remove_asset_from_option( $raw_url );
				wp_send_json_success( array( 'message' => __( 'Asset removed (not found on disk).', 'align' ) ) );
			}
			wp_send_json_error( array( 'message' => __( 'Unauthorized file deletion path.', 'align' ) ) );
		}
		if ( file_exists( $resolved_path ) ) {
			@unlink( $resolved_path );
			clearstatcache( true, $resolved_path );
		}
		self::remove_asset_from_option( $raw_url );
		wp_send_json_success( array( 'message' => __( 'Asset deleted permanently.', 'align' ), 'file' => $raw_url ) );
	}

	/**
	 * Helper to remove asset entry from align_loaded_assets database option.
	 */
	public static function remove_asset_from_option( $raw_url ) {
		if ( empty( $raw_url ) ) {
			return;
		}
		$assets = get_option( 'align_loaded_assets', array() );
		if ( ! is_array( $assets ) || empty( $assets ) ) {
			return;
		}
		$rel_url  = self::get_relative_asset_url( $raw_url );
		$filtered = array();
		$updated  = false;
		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) || empty( $asset['url'] ) ) {
				continue;
			}
			$asset_url = $asset['url'];
			$asset_rel = self::get_relative_asset_url( $asset_url );
			if ( $asset_url === $raw_url || $asset_rel === $rel_url || $asset_url === $rel_url ) {
				$updated = true;
				continue;
			}
			$filtered[] = $asset;
		}
		if ( $updated ) {
			update_option( 'align_loaded_assets', array_values( $filtered ) );
		}
	}

	public static function migrate_legacy_assets() {
		$legacy_dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'align-assets/';
		$target_dir = trailingslashit( get_stylesheet_directory() ) . 'assets/';
		$target_url = trailingslashit( get_stylesheet_directory_uri() ) . 'assets/';

		if ( is_dir( $legacy_dir ) && is_writable( $target_dir ) ) {
			foreach ( array_diff( scandir( $legacy_dir ) ?: array(), array( '.', '..' ) ) as $file ) {
				if ( is_file( $legacy_dir . $file ) && ! file_exists( $target_dir . $file ) ) @copy( $legacy_dir . $file, $target_dir . $file );
			}
		}

		$assets = get_option( 'align_loaded_assets', array() );
		if ( is_array( $assets ) && ! empty( $assets ) && is_dir( $target_dir ) ) {
			$updated = false;
			foreach ( $assets as $idx => $asset ) {
				$url = $asset['url'] ?? '';
				if ( false !== strpos( $url, '/align-assets/' ) || false !== strpos( $url, 'uploads/align-assets/' ) ) {
					$assets[ $idx ]['url'] = self::get_relative_asset_url( $target_url . basename( parse_url( $url, PHP_URL_PATH ) ) );
					$updated = true;
				}
			}
			if ( $updated ) update_option( 'align_loaded_assets', $assets );
		}
	}

	/**
	 * Sync loading strategies across all assets when Delay JS & CSS toggle changes.
	 */
	public static function on_toggle_delay_assets( $old_value, $value ) {
		if ( ( 1 === (int) $value ) === ( 1 === (int) $old_value ) ) return;
		$assets = get_option( 'align_loaded_assets', array() );
		if ( ! is_array( $assets ) || empty( $assets ) ) return;

		$new_enabled = 1 === (int) $value;
		$updated     = false;
		foreach ( $assets as &$asset ) {
			if ( ! is_array( $asset ) ) continue;
			$type = strtolower( trim( $asset['type'] ?? 'css' ) );
			if ( 'font' === $type ) continue;
			if ( $new_enabled ) {
				$asset['loading'] = 'delay'; $updated = true;
			} elseif ( isset( $asset['loading'] ) && 'delay' === $asset['loading'] ) {
				$asset['loading'] = ( 'js' === $type ) ? 'defer' : 'normal'; $updated = true;
			}
		}
		unset( $asset );
		if ( $updated ) update_option( 'align_loaded_assets', $assets );
	}

	public static function on_add_delay_assets( $option, $value ) {
		self::on_toggle_delay_assets( 0, $value );
	}
}
