<?php
/**
 * Align Agent Gateway - Design & Style Context Controller
 *
 * Inspects loaded stylesheets, detects utility frameworks (Bootstrap Grid/Utilities),
 * filters vendor libraries, and extracts raw custom CSS and CSS variables for AI context.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Gateway_Context {

	/**
	 * List of vendor identifiers to exclude from raw custom CSS extraction.
	 *
	 * @var array
	 */
	private static $vendor_keywords = array(
		'splide',
		'lenis',
		'smooth-scroll',
		'swiper',
		'font-awesome',
		'fontawesome',
	);

	/**
	 * Handle context retrieval endpoint.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response Design context response.
	 */
	public static function handle_get_context( WP_REST_Request $request ): WP_REST_Response {
		$frameworks      = self::detect_frameworks();
		$assets_info     = self::get_active_css_assets();
		$custom_css_data = self::load_custom_css_sources();
		$extracted_vars  = self::extract_css_variables( $custom_css_data['raw_css'] );

		$response_data = array(
			'frameworks'             => $frameworks,
			'active_stylesheets'     => $assets_info,
			'custom_css'             => $custom_css_data['raw_css'],
			'css_variables'          => $extracted_vars,
			'custom_files_loaded'    => $custom_css_data['loaded_files'],
			'total_custom_css_bytes' => strlen( $custom_css_data['raw_css'] ),
		);

		error_log( sprintf(
			'[Align Agent] Context compiled: %d frameworks detected, %d bytes custom CSS from %d files.',
			count( $frameworks ),
			$response_data['total_custom_css_bytes'],
			count( $custom_css_data['loaded_files'] )
		) );

		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Retrieve comprehensive website intelligence snapshot for AI context.
	 *
	 * @return array Structured website intelligence.
	 */
	public static function get_site_intelligence(): array {
		global $wp_version;

		$theme        = wp_get_theme();
		$parent_theme = ( is_child_theme() && $theme->parent() ) ? $theme->parent()->get( 'Name' ) : 'None';

		$pages_query = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 20,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		) );

		$pages_inventory = array();
		foreach ( $pages_query as $page ) {
			$pages_inventory[] = array(
				'id'            => (int) $page->ID,
				'title'         => (string) ( $page->post_title ? $page->post_title : '(No title)' ),
				'slug'          => (string) $page->post_name,
				'status'        => (string) $page->post_status,
				'permalink'     => (string) get_permalink( $page->ID ),
				'modified_date' => (string) $page->post_modified,
			);
		}

		$frameworks      = self::detect_frameworks();
		$custom_css_data = self::load_custom_css_sources();
		$all_vars        = self::extract_css_variables( $custom_css_data['raw_css'] );
		$top_vars        = array_slice( $all_vars, 0, 15, true );

		$published_posts = wp_count_posts( 'post' );
		$published_pages = wp_count_posts( 'page' );

		return array(
			'site_title'           => (string) get_bloginfo( 'name' ),
			'tagline'              => (string) get_bloginfo( 'description' ),
			'site_url'             => (string) home_url(),
			'wp_version'           => (string) ( $wp_version ?? 'unknown' ),
			'active_theme'         => $theme->get( 'Name' ) . ' v' . $theme->get( 'Version' ),
			'parent_theme'         => $parent_theme,
			'active_plugins_count' => count( (array) get_option( 'active_plugins', array() ) ),
			'total_posts_count'    => isset( $published_posts->publish ) ? (int) $published_posts->publish : 0,
			'total_pages_count'    => isset( $published_pages->publish ) ? (int) $published_pages->publish : 0,
			'pages_inventory'      => $pages_inventory,
			'frameworks'           => $frameworks,
			'css_variables'        => $top_vars,
		);
	}

	/**
	 * Detect active CSS frameworks present in child theme or loaded assets.
	 *
	 * @return array List of detected framework names.
	 */
	private static function detect_frameworks(): array {
		$frameworks = array();
		$child_dir  = get_stylesheet_directory();

		$candidates = array(
			'bootstrap-grid'      => array( 'assets/bootstrap-grid.min.css', 'assets/grid.css' ),
			'bootstrap-utilities' => array( 'assets/bootstrap-utilities.min.css' ),
			'bootstrap-reboot'    => array( 'assets/bootstrap-reboot.min.css' ),
		);

		foreach ( $candidates as $fw_name => $relative_paths ) {
			foreach ( $relative_paths as $rel_path ) {
				if ( file_exists( $child_dir . '/' . $rel_path ) ) {
					$frameworks[] = $fw_name;
					break;
				}
			}
		}

		$loaded_assets = get_option( 'align_loaded_assets', array() );
		if ( is_array( $loaded_assets ) ) {
			foreach ( $loaded_assets as $asset ) {
				if ( ! is_array( $asset ) || empty( $asset['url'] ) ) {
					continue;
				}
				$url = strtolower( (string) $asset['url'] );
				if ( strpos( $url, 'bootstrap-grid' ) !== false && ! in_array( 'bootstrap-grid', $frameworks, true ) ) {
					$frameworks[] = 'bootstrap-grid';
				}
				if ( strpos( $url, 'bootstrap-utilities' ) !== false && ! in_array( 'bootstrap-utilities', $frameworks, true ) ) {
					$frameworks[] = 'bootstrap-utilities';
				}
			}
		}

		return array_values( array_unique( $frameworks ) );
	}

	/**
	 * Get list of active CSS stylesheets, excluding vendor libraries.
	 *
	 * @return array Array of active CSS asset metadata.
	 */
	private static function get_active_css_assets(): array {
		$effective_assets = array();

		if ( class_exists( 'Align_Admin_Assets' ) && method_exists( 'Align_Admin_Assets', 'get_effective_loaded_assets' ) ) {
			$effective_assets = Align_Admin_Assets::get_effective_loaded_assets();
		} else {
			$effective_assets = get_option( 'align_loaded_assets', array() );
		}

		if ( ! is_array( $effective_assets ) ) {
			return array();
		}

		$output = array();
		foreach ( $effective_assets as $asset ) {
			if ( ! is_array( $asset ) || empty( $asset['url'] ) ) {
				continue;
			}

			$type    = isset( $asset['type'] ) ? strtolower( (string) $asset['type'] ) : 'css';
			$enabled = ! isset( $asset['enabled'] ) || ! empty( $asset['enabled'] );

			if ( 'css' !== $type || ! $enabled ) {
				continue;
			}

			$url       = (string) $asset['url'];
			$is_vendor = self::is_vendor_asset( $url );

			$output[] = array(
				'url'          => $url,
				'location'     => $asset['location'] ?? 'header',
				'plugin_title' => $asset['plugin_title'] ?? '',
				'is_core'      => ! empty( $asset['is_core'] ),
				'is_vendor'    => $is_vendor,
			);
		}

		return $output;
	}

	/**
	 * Check whether an asset URL belongs to a vendor library.
	 *
	 * @param string $url Asset URL.
	 * @return bool True if vendor, false otherwise.
	 */
	private static function is_vendor_asset( string $url ): bool {
		$clean = strtolower( $url );
		foreach ( self::$vendor_keywords as $keyword ) {
			if ( false !== strpos( $clean, $keyword ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Load raw custom CSS content from child theme and registered user stylesheets.
	 *
	 * @return array Array containing raw_css string and list of loaded files.
	 */
	private static function load_custom_css_sources(): array {
		$child_dir    = get_stylesheet_directory();
		$loaded_files = array();
		$combined_css = '';

		// 1. Child theme primary root.css candidates.
		$primary_candidates = array(
			'assets/root.css',
			'assets/src/general/css/root.css',
			'assets/dist/general/css/root.min.css',
		);

		foreach ( $primary_candidates as $rel_path ) {
			$full_path = $child_dir . '/' . $rel_path;
			if ( self::is_safe_css_file( $full_path ) && is_readable( $full_path ) ) {
				$content = file_get_contents( $full_path );
				if ( false !== $content && '' !== trim( $content ) ) {
					$combined_css  .= "/* Source: " . esc_html( $rel_path ) . " */\n" . trim( $content ) . "\n\n";
					$loaded_files[] = $rel_path;
					break;
				}
			}
		}

		// 2. Active non-vendor user custom CSS from Align Asset Loader.
		$saved_assets = get_option( 'align_loaded_assets', array() );
		if ( is_array( $saved_assets ) ) {
			foreach ( $saved_assets as $asset ) {
				if ( ! is_array( $asset ) || empty( $asset['url'] ) ) {
					continue;
				}
				$type    = isset( $asset['type'] ) ? strtolower( (string) $asset['type'] ) : 'css';
				$enabled = ! isset( $asset['enabled'] ) || ! empty( $asset['enabled'] );
				$url     = (string) $asset['url'];

				if ( 'css' !== $type || ! $enabled || self::is_vendor_asset( $url ) ) {
					continue;
				}

				// Skip already loaded root.css.
				if ( false !== strpos( $url, 'root.css' ) || false !== strpos( $url, 'root.min.css' ) ) {
					continue;
				}

				// Skip framework bundles.
				if ( false !== strpos( $url, 'bootstrap-' ) ) {
					continue;
				}

				$file_path = false;
				if ( class_exists( 'Align_Admin_Assets' ) && method_exists( 'Align_Admin_Assets', 'resolve_asset_filesystem_path' ) ) {
					$file_path = Align_Admin_Assets::resolve_asset_filesystem_path( $url );
				}

				if ( ! $file_path || ! file_exists( $file_path ) ) {
					$clean_url  = ltrim( preg_replace( '/[?#].*$/', '', $url ), '/' );
					$maybe_file = $child_dir . '/' . $clean_url;
					if ( file_exists( $maybe_file ) ) {
						$file_path = $maybe_file;
					}
				}

				if ( $file_path && self::is_safe_css_file( $file_path ) && is_readable( $file_path ) ) {
					$content = file_get_contents( $file_path );
					if ( false !== $content && '' !== trim( $content ) ) {
						$basename = basename( $file_path );
						if ( ! in_array( $basename, $loaded_files, true ) ) {
							$combined_css  .= "/* Source: " . esc_html( $basename ) . " */\n" . trim( $content ) . "\n\n";
							$loaded_files[] = $basename;
						}
					}
				}
			}
		}

		return array(
			'raw_css'      => trim( $combined_css ),
			'loaded_files' => $loaded_files,
		);
	}

	/**
	 * Validate whether a physical file path is strictly safe to read.
	 *
	 * Ensures .css extension and validates that the path resides within allowed theme or uploads directories.
	 *
	 * @param string $file_path File path to check.
	 * @return bool True if safe, false otherwise.
	 */
	private static function is_safe_css_file( string $file_path ): bool {
		if ( empty( $file_path ) ) {
			return false;
		}

		$real_path = realpath( $file_path );
		if ( false === $real_path || ! is_file( $real_path ) ) {
			return false;
		}

		$ext = strtolower( pathinfo( $real_path, PATHINFO_EXTENSION ) );
		if ( 'css' !== $ext ) {
			return false;
		}

		if ( class_exists( 'Align_Admin_Assets' ) && method_exists( 'Align_Admin_Assets', 'is_safe_asset_path' ) ) {
			return Align_Admin_Assets::is_safe_asset_path( $real_path );
		}

		$normalized_real = wp_normalize_path( $real_path );
		$upload_info     = wp_upload_dir();
		$allowed_dirs    = array(
			wp_normalize_path( trailingslashit( get_stylesheet_directory() ) ),
			wp_normalize_path( trailingslashit( get_template_directory() ) ),
			wp_normalize_path( trailingslashit( $upload_info['basedir'] ) ),
		);

		foreach ( $allowed_dirs as $allowed_dir ) {
			if ( 0 === strpos( $normalized_real, $allowed_dir ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Extract CSS custom properties (--variable-name: value) from CSS content.
	 *
	 * @param string $css Raw CSS string.
	 * @return array Associative array of CSS variable name => value.
	 */
	private static function extract_css_variables( string $css ): array {
		if ( empty( $css ) ) {
			return array();
		}

		$variables = array();
		if ( preg_match_all( '/--([a-zA-Z0-9_-]+)\s*:\s*([^;]+);/', $css, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$key               = '--' . trim( $match[1] );
				$value             = trim( $match[2] );
				$variables[ $key ] = $value;
			}
		}

		return $variables;
	}
}
