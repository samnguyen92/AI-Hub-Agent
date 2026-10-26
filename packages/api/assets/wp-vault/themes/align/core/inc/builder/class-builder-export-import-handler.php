<?php
/**
 * Align Theme Core Engine - Builder Export & Import Page Handler
 *
 * Handles exporting saved page data into a clean, standalone ZIP package
 * (index.html, page.css, page.js) and importing such packages into the active
 * canvas for preview prior to database persistence.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Builder_Export_Import_Handler {

	/**
	 * Export currently saved page as a ZIP package.
	 *
	 * @param WP_REST_Request $request REST request object.
	 */
	public static function export_page( $request ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'zip_unsupported', __( 'ZipArchive PHP extension is not available on server.', 'align' ), array( 'status' => 500 ) );
		}

		$post_id       = intval( $request->get_param( 'post_id' ) ?: $request->get_param( 'file' ) );
		$tp_id         = $request->get_param( 'tp_id' );
		$template_type = $request->get_param( 'template_type' );

		$page_title         = 'Page';
		$entity_slug        = 'page';
		$raw_content        = '';
		$extracted_page_css = '';
		$extracted_page_js  = '';
		$reusable_overrides = array();

		// 1. Resolve Target Entity and Retrieve Saved Content
		if ( ! empty( $tp_id ) ) {
			$raw_id          = preg_replace( '/^t(p|pl)_/', '', $tp_id );
			$is_template     = ( 'template' === $template_type || 0 === strpos( $tp_id, 'tpl_' ) );
			$entity_slug     = sanitize_file_name( strpos( $raw_id, '//' ) !== false ? explode( '//', $raw_id )[1] : $raw_id );
			$post_type_target = $is_template ? 'wp_template' : 'wp_template_part';
			$prefix          = $is_template ? 'template' : 'part';
			$page_title      = ucfirst( str_replace( '-', ' ', $entity_slug ) );

			$search_slugs = array_values( array_unique( array_filter( array(
				$entity_slug, get_stylesheet() . '//' . $entity_slug, get_template() . '//' . $entity_slug,
				sanitize_title( get_stylesheet() . '-' . $entity_slug ), sanitize_title( get_template() . '-' . $entity_slug ),
				$raw_id, sanitize_title( $raw_id ),
			) ) ) );

			$found_posts = get_posts( array( 'post_type' => $post_type_target, 'post_status' => 'any', 'posts_per_page' => 1, 'post_name__in' => $search_slugs, 'tax_query' => array( array( 'taxonomy' => 'wp_theme', 'field' => 'slug', 'terms' => array( get_stylesheet(), get_template() ) ) ) ) );
			if ( empty( $found_posts ) ) {
				$found_posts = get_posts( array( 'post_type' => $post_type_target, 'post_status' => 'any', 'posts_per_page' => 1, 'post_name__in' => $search_slugs ) );
			}

			if ( ! empty( $found_posts ) && ! empty( $found_posts[0]->post_content ) ) {
				$raw_content = $found_posts[0]->post_content;
				if ( ! empty( $found_posts[0]->post_title ) ) {
					$page_title = $found_posts[0]->post_title;
				}
				$reusable_overrides = get_post_meta( $found_posts[0]->ID, '_align_reusable_overrides', true ) ?: array();
			} else {
				// Disk fallback
				$sub_dir    = $is_template ? 'templates' : 'parts';
				$file_paths = array(
					get_stylesheet_directory() . '/' . $sub_dir . '/' . $entity_slug . '.html',
					get_template_directory() . '/' . $sub_dir . '/' . $entity_slug . '.html',
				);
				foreach ( $file_paths as $fp ) {
					if ( file_exists( $fp ) ) {
						$raw_content = file_get_contents( $fp );
						break;
					}
				}
			}

			$style_id          = 'style-' . $prefix . '-' . $entity_slug;
			$script_id         = 'script-' . $prefix . '-' . $entity_slug;
			$legacy_style_ids  = array( 'align-style-' . $prefix . '-' . $entity_slug );
			$legacy_script_ids = array( 'align-script-' . $prefix . '-' . $entity_slug );

		} elseif ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				return new WP_Error( 'not_found', __( 'Target post not found.', 'align' ), array( 'status' => 404 ) );
			}
			$page_title         = $post->post_title ?: ( 'Page #' . $post_id );
			$entity_slug        = ! empty( $post->post_name ) ? $post->post_name : ( 'page-' . $post_id );
			$raw_content        = $post->post_content;
			$is_block           = ( 'wp_block' === $post->post_type );
			$prefix             = $is_block ? 'block' : 'page';
			$style_id           = 'style-' . $prefix . '-' . $entity_slug;
			$script_id          = 'script-' . $prefix . '-' . $entity_slug;
			$legacy_style_ids   = array( 'align-style-' . $prefix . '-' . $entity_slug, 'align-style-' . $prefix . '-' . $post_id, 'style-' . $prefix . '-' . $post_id );
			$legacy_script_ids  = array( 'align-script-' . $prefix . '-' . $entity_slug, 'align-script-' . $prefix . '-' . $post_id, 'script-' . $prefix . '-' . $post_id );
			$reusable_overrides = get_post_meta( $post_id, '_align_reusable_overrides', true ) ?: array();
		} else {
			return new WP_Error( 'missing_id', __( 'Invalid or missing post_id/tp_id parameter.', 'align' ), array( 'status' => 400 ) );
		}

		// 2. Extract Clean Body Content
		$clean_body = $raw_content;
		if ( preg_match( '/<!-- wp:align\/custom-html[^>]*>(.*?)<!-- \/wp:align\/custom-html -->/s', $clean_body, $matches ) ||
			 preg_match( '/<!-- wp:html -->(.*?)<!-- \/wp:html -->/s', $clean_body, $matches ) ) {
			$clean_body = trim( $matches[1] );
		}
		if ( preg_match( '/<body[^>]*>(.*?)<\/body>/is', $clean_body, $body_m ) ) {
			$clean_body = trim( $body_m[1] );
		}

		// Strip legacy wrapper
		if ( preg_match( '/^<div\b[^>]*\bclass="[^"]*\balign-custom-html-block\b[^"]*"[^>]*>\s*([\s\S]*?)\s*<\/div>$/i', trim( $clean_body ), $legacy_wrap_m ) ) {
			$clean_body = trim( $legacy_wrap_m[1] );
		}

		// 3. Extract and Load Scoped CSS & JS
		$exact_css_regex = '/<style[^>]*\bid\s*=\s*(["\']?)' . preg_quote( $style_id, '/' ) . '\1[^>]*>([\s\S]*?)<\/style>/is';
		if ( preg_match( $exact_css_regex, $clean_body, $css_m ) ) {
			$extracted_page_css = $css_m[2];
			$clean_body         = preg_replace( $exact_css_regex, '', $clean_body, 1 );
		} elseif ( preg_match( '/<style[^>]*\bid\s*=\s*(["\']?)(?:(?:align-)?style-[^"\'\s>]+|vvvebjs-styles)\1[^>]*>([\s\S]*?)<\/style>/is', $clean_body, $css_m ) ) {
			$extracted_page_css = $css_m[2];
			$clean_body         = preg_replace( '/<style[^>]*\bid\s*=\s*(["\']?)(?:(?:align-)?style-[^"\'\s>]+|vvvebjs-styles)\1[^>]*>([\s\S]*?)<\/style>/is', '', $clean_body, 1 );
		}

		$exact_js_regex = '/<script[^>]*\bid\s*=\s*(["\']?)' . preg_quote( $script_id, '/' ) . '\1[^>]*>([\s\S]*?)<\/script>/is';
		if ( preg_match( $exact_js_regex, $clean_body, $js_m ) ) {
			$extracted_page_js = $js_m[2];
			$clean_body        = preg_replace( $exact_js_regex, '', $clean_body, 1 );
		} elseif ( preg_match( '/<script[^>]*\bid\s*=\s*(["\']?)(?:(?:align-)?script-[^"\'\s>]+|vvvebjs-scripts)\1[^>]*>([\s\S]*?)<\/script>/is', $clean_body, $js_m ) ) {
			$extracted_page_js = $js_m[2];
			$clean_body        = preg_replace( '/<script[^>]*\bid\s*=\s*(["\']?)(?:(?:align-)?script-[^"\'\s>]+|vvvebjs-scripts)\1[^>]*>([\s\S]*?)<\/script>/is', '', $clean_body, 1 );
		}

		// Fallback to load from external physical files
		if ( class_exists( 'Align_Builder_Assets' ) ) {
			$extracted_page_css = Align_Builder_Assets::load_external_css( $style_id, $extracted_page_css, $legacy_style_ids );
			$extracted_page_js  = Align_Builder_Assets::load_external_js( $script_id, $extracted_page_js, $legacy_script_ids );
		}

		// Remove any remaining editor helpers or preview markers
		$clean_body = preg_replace( '/<style[^>]*id="wp-global-styles"[^>]*>[\s\S]*?<\/style>|<(script|style)[^>]*data-align-preview="1"[^>]*>[\s\S]*?<\/\1>/i', '', $clean_body );
		$clean_body = preg_replace( '/<div\b[^>]*\bclass="[^"]*shortcode-preview-content[^"]*"[^>]*>[\s\S]*?<\/div>/i', '', $clean_body );

		// 4. Convert Relative URLs to Absolute Site URLs
		$clean_body         = self::make_html_urls_absolute( $clean_body );
		$extracted_page_css = self::make_css_urls_absolute( $extracted_page_css );

		// 5. Assemble Full index.html Document
		$theme_css_urls   = class_exists( 'Align_Builder_Assets' ) ? Align_Builder_Assets::get_builder_css_urls() : array();
		$font_preloads    = class_exists( 'Align_Builder_Assets' ) ? Align_Builder_Assets::get_font_preloads() : array();
		$header_js_assets = class_exists( 'Align_Builder_Assets' ) ? Align_Builder_Assets::get_builder_js_assets( 'header' ) : array();
		$footer_js_assets = class_exists( 'Align_Builder_Assets' ) ? Align_Builder_Assets::get_builder_js_assets( 'footer' ) : array();
		$custom_css       = get_option( 'align_custom_css', '' );
		$custom_js        = get_option( 'align_custom_js', '' );

		$index_html = "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n";
		$index_html .= "  <meta charset=\"utf-8\">\n";
		$index_html .= "  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
		$index_html .= "  <title>" . esc_html( $page_title ) . "</title>\n";

		foreach ( $font_preloads as $preload_tag ) {
			$index_html .= '  ' . $preload_tag . "\n";
		}
		foreach ( $theme_css_urls as $css_url ) {
			$index_html .= '  <link rel="stylesheet" href="' . esc_url( $css_url ) . "\">\n";
		}
		if ( ! empty( $custom_css ) ) {
			$index_html .= "  <style id=\"align-global-custom-css\">\n" . trim( $custom_css ) . "\n  </style>\n";
		}
		$index_html .= "  <link rel=" . '"stylesheet" href="page.css">' . "\n";

		foreach ( $header_js_assets as $js ) {
			$url  = is_array( $js ) ? $js['url'] : $js->url;
			$attr = ( 'defer' === ( is_array( $js ) ? $js['loading'] : $js->loading ) ) ? ' defer' : ( ( 'async' === ( is_array( $js ) ? $js['loading'] : $js->loading ) ) ? ' async' : '' );
			$index_html .= '  <script src="' . esc_url( $url ) . '"' . $attr . "></script>\n";
		}

		if ( ! empty( $reusable_overrides ) && is_array( $reusable_overrides ) ) {
			$index_html .= "  <script type=\"application/json\" id=\"align-reusable-overrides\">\n" . wp_json_encode( $reusable_overrides, JSON_PRETTY_PRINT ) . "\n  </script>\n";
		}

		$index_html .= "</head>\n<body>\n";
		$index_html .= trim( $clean_body ) . "\n";

		foreach ( $footer_js_assets as $js ) {
			$url  = is_array( $js ) ? $js['url'] : $js->url;
			$attr = ( 'defer' === ( is_array( $js ) ? $js['loading'] : $js->loading ) ) ? ' defer' : ( ( 'async' === ( is_array( $js ) ? $js['loading'] : $js->loading ) ) ? ' async' : '' );
			$index_html .= '  <script src="' . esc_url( $url ) . '"' . $attr . "></script>\n";
		}
		if ( ! empty( $custom_js ) ) {
			$index_html .= "  <script id=\"align-global-custom-js\">\n" . trim( $custom_js ) . "\n  </script>\n";
		}
		$index_html .= "  <script src=\"page.js\"></script>\n";
		$index_html .= "</body>\n</html>\n";

		// 6. Create ZIP Archive and Stream to Browser
		// wp_tempnam() lives in wp-admin/includes/file.php, which is NOT loaded in REST context.
		if ( ! function_exists( 'wp_tempnam' ) && file_exists( ABSPATH . 'wp-admin/includes/file.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$temp_zip = wp_tempnam( 'align-export-' ) . '.zip';
		error_log( '[Align Builder] Export temp ZIP path: ' . $temp_zip );
		$zip      = new ZipArchive();

		if ( true !== $zip->open( $temp_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'zip_failed', __( 'Failed to create temporary ZIP package.', 'align' ), array( 'status' => 500 ) );
		}

		$zip->addFromString( 'index.html', $index_html );
		$zip->addFromString( 'page.css', (string) $extracted_page_css );
		$zip->addFromString( 'page.js', (string) $extracted_page_js );
		$zip->close();

		$zip_size  = file_exists( $temp_zip ) ? filesize( $temp_zip ) : 0;
		$file_slug = sanitize_file_name( $entity_slug ?: 'page' );
		$filename  = 'align-export-' . $file_slug . '.zip';

		error_log( sprintf( '[Align ExportImport] Export generated | File: %s | Size: %d bytes | HTML: %d, CSS: %d, JS: %d', $filename, $zip_size, strlen( $index_html ), strlen( $extracted_page_css ), strlen( $extracted_page_js ) ) );

		if ( ob_get_level() ) {
			ob_end_clean();
		}

		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . $zip_size );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		readfile( $temp_zip );
		@unlink( $temp_zip );
		exit;
	}

	/**
	 * Import page package from uploaded ZIP file for canvas live preview.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error Response object or WP_Error.
	 */
	public static function import_page( $request ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'zip_unsupported', __( 'ZipArchive PHP extension is not available on server.', 'align' ), array( 'status' => 500 ) );
		}

		if ( empty( $_FILES['zip_file'] ) || empty( $_FILES['zip_file']['tmp_name'] ) ) {
			return new WP_Error( 'no_file', __( 'No ZIP file was uploaded.', 'align' ), array( 'status' => 400 ) );
		}

		$file_info = $_FILES['zip_file'];
		if ( $file_info['error'] !== UPLOAD_ERR_OK ) {
			return new WP_Error( 'upload_error', sprintf( __( 'Upload failed with error code %d.', 'align' ), $file_info['error'] ), array( 'status' => 400 ) );
		}

		// Security: Validate file size (max 25MB)
		if ( $file_info['size'] > 25 * 1024 * 1024 ) {
			return new WP_Error( 'file_too_large', __( 'Uploaded ZIP package exceeds maximum limit (25MB).', 'align' ), array( 'status' => 400 ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $file_info['tmp_name'] ) ) {
			return new WP_Error( 'invalid_zip', __( 'Uploaded file is not a valid ZIP package.', 'align' ), array( 'status' => 400 ) );
		}

		// Security: Zip Slip check
		$has_index = false;
		$index_filename = '';
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$entry_name = $zip->getNameIndex( $i );
			if ( str_contains( $entry_name, '..' ) || str_starts_with( $entry_name, '/' ) || str_starts_with( $entry_name, '\\' ) ) {
				$zip->close();
				return new WP_Error( 'zip_slip_detected', __( 'Potential Zip Slip security hazard detected in package.', 'align' ), array( 'status' => 400 ) );
			}
			$lower = strtolower( basename( $entry_name ) );
			if ( 'index.html' === $lower || 'index.htm' === $lower ) {
				$has_index      = true;
				$index_filename = $entry_name;
			}
		}

		if ( ! $has_index ) {
			$zip->close();
			return new WP_Error( 'missing_index', __( 'Package must contain index.html at root.', 'align' ), array( 'status' => 400 ) );
		}

		$raw_html = $zip->getFromName( $index_filename ) ?: '';
		$css      = (string) ( $zip->getFromName( 'page.css' ) ?: '' );
		$js       = (string) ( $zip->getFromName( 'page.js' ) ?: '' );
		$zip->close();

		// Extract reusable overrides if embedded
		$overrides = array();
		if ( preg_match( '/<script\b[^>]*\bid=["\']align-reusable-overrides["\'][^>]*>([\s\S]*?)<\/script>/i', $raw_html, $ov_m ) ) {
			$decoded = json_decode( trim( $ov_m[1] ), true );
			if ( is_array( $decoded ) ) {
				$overrides = $decoded;
			}
			$raw_html = preg_replace( '/<script\b[^>]*\bid=["\']align-reusable-overrides["\'][^>]*>[\s\S]*?<\/script>/i', '', $raw_html );
		}

		// Extract body content
		$html_body = $raw_html;
		if ( preg_match( '/<body\b[^>]*>([\s\S]*?)<\/body>/i', $raw_html, $body_match ) ) {
			$html_body = trim( $body_match[1] );
		} else {
			$html_body = trim( preg_replace( '/<\/?(html|head|body)\b[^>]*>|<!DOCTYPE[^>]*>/i', '', $raw_html ) );
		}

		// Strip local package asset tags if present in body
		$html_body = preg_replace( '/<link\b[^>]*\bhref=["\']page\.css["\'][^>]*>/i', '', $html_body );
		$html_body = preg_replace( '/<script\b[^>]*\bsrc=["\']page\.js["\'][^>]*>\s*<\/script>/i', '', $html_body );
		$html_body = preg_replace( '/<(script|style)[^>]*data-align-preview="1"[^>]*>[\s\S]*?<\/\1>/i', '', $html_body );

		error_log( sprintf( '[Align ExportImport] Import unpacked successfully | HTML: %d bytes, CSS: %d bytes, JS: %d bytes, Overrides: %d entries', strlen( $html_body ), strlen( $css ), strlen( $js ), count( $overrides ) ) );

		return rest_ensure_response( array(
			'status'             => 'success',
			'html'               => $html_body,
			'css'                => $css,
			'js'                 => $js,
			'reusable_overrides' => $overrides,
			'message'            => __( 'Page package extracted successfully for canvas preview.', 'align' ),
		) );
	}

	/**
	 * Convert relative URLs in HTML string to absolute URLs pointing to current site.
	 *
	 * @param string $html HTML content.
	 * @return string Processed HTML.
	 */
	public static function make_html_urls_absolute( $html ) {
		if ( empty( $html ) || ! is_string( $html ) ) {
			return $html;
		}

		// 1. Process standard URL attributes: src, href, data-src, data-lazy, data-original
		$html = preg_replace_callback(
			'/\b(src|href|data-src|data-lazy|data-original)\s*=\s*(["\'])([^"\']+?)\2/i',
			function( $m ) {
				$attr = $m[1];
				$quote = $m[2];
				$url   = trim( $m[3] );
				return $attr . '=' . $quote . esc_url( self::resolve_absolute_url( $url ) ) . $quote;
			},
			$html
		);

		// 2. Process srcset attributes
		$html = preg_replace_callback(
			'/\bsrcset\s*=\s*(["\'])([^"\']+?)\1/i',
			function( $m ) {
				$quote       = $m[1];
				$raw_srcset  = trim( $m[2] );
				$candidates  = explode( ',', $raw_srcset );
				$updated_arr = array();

				foreach ( $candidates as $cand ) {
					$cand = trim( $cand );
					if ( '' === $cand ) {
						continue;
					}
					$parts = preg_split( '/\s+/', $cand, 2 );
					$c_url = $parts[0];
					$desc  = isset( $parts[1] ) ? ( ' ' . $parts[1] ) : '';
					$updated_arr[] = esc_url( self::resolve_absolute_url( $c_url ) ) . $desc;
				}

				return 'srcset=' . $quote . implode( ', ', $updated_arr ) . $quote;
			},
			$html
		);

		return $html;
	}

	/**
	 * Convert relative url(...) paths in CSS string to absolute site URLs.
	 *
	 * @param string $css CSS content.
	 * @return string Processed CSS.
	 */
	public static function make_css_urls_absolute( $css ) {
		if ( empty( $css ) || ! is_string( $css ) ) {
			return $css;
		}

		return preg_replace_callback(
			'/url\(\s*(["\']?)(.*?)\1\s*\)/i',
			function( $m ) {
				$quote = $m[1];
				$url   = trim( $m[2] );
				return 'url(' . $quote . esc_url( self::resolve_absolute_url( $url ) ) . $quote . ')';
			},
			$css
		);
	}

	/**
	 * Resolve an individual URL to an absolute URL based on the current site origin.
	 *
	 * @param string $url Source URL.
	 * @return string Absolute URL or untouched original scheme.
	 */
	public static function resolve_absolute_url( $url ) {
		if ( empty( $url ) || ! is_string( $url ) ) {
			return '';
		}

		$trimmed = trim( $url );
		if ( '' === $trimmed ) {
			return '';
		}

		// Keep existing absolute or scheme-specific URLs intact
		if ( preg_match( '#^(https?:)?//#i', $trimmed ) ||
			 0 === strpos( $trimmed, 'data:' ) ||
			 0 === strpos( $trimmed, 'mailto:' ) ||
			 0 === strpos( $trimmed, 'tel:' ) ||
			 0 === strpos( $trimmed, 'javascript:' ) ||
			 0 === strpos( $trimmed, '#' ) ) {
			return $trimmed;
		}

		return site_url( '/' . ltrim( $trimmed, '/' ) );
	}
}
