<?php
/**
 * Align Theme Core Engine - ZIP Importer Page Assembler
 * Parses HTML/CSS/JS, creates WordPress Pages, saves external scoped assets, and wraps Gutenberg Custom Blocks.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Zip_Page_Assembler {

	/**
	 * Assemble a WordPress Page from scanned item.
	 *
	 * @param array  $page_info Scanned page metadata.
	 * @param array  $options   Import options.
	 * @param string $root_dir  Root project directory.
	 * @return array Result containing status, post_id, slug, and URLs.
	 */
	public static function assemble_page( array $page_info, array $options, string $root_dir, int $parent_id = 0 ): array {
		$overwrite      = ! empty( $options['overwrite'] );
		$is_placeholder = ! empty( $page_info['is_placeholder'] );
		$path_key       = $page_info['path_key'] ?? '';
		$slug           = sanitize_title( $page_info['slug'] ?? 'page' );
		$title          = ! empty( $page_info['title'] ) ? $page_info['title'] : self::format_page_title( $slug );
		if ( in_array( $slug, array( 'index', 'home', 'front-page', 'trang-chu' ), true ) && '' === $path_key ) {
			$slug = 'home';
			$title = 'Home';
		}

		$payload = null;
		if ( ! $is_placeholder ) {
			$payload = self::prepare_component_payload( $page_info, $root_dir );
			if ( ! $payload ) {
				return array(
					'status'   => 'error',
					'message'  => sprintf( __( 'Failed to prepare HTML payload for page: %s', 'align' ), $slug ),
					'slug'     => $slug,
					'path_key' => $path_key,
				);
			}
		}

		// Deduplicate slug against existing pages (Language-aware to prevent overwriting default language page or WP -2 suffix)
		$default_lang = ( class_exists( 'Align_Language_Manager' ) && method_exists( 'Align_Language_Manager', 'get_default_lang' ) )
			? Align_Language_Manager::get_default_lang()
			: 'en';
		$page_lang    = ! empty( $page_info['lang'] ) ? sanitize_key( $page_info['lang'] ) : $default_lang;

		$same_lang_query = array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'trash', 'future', 'auto-draft' ),
			'post_name__in'  => array_values( array_unique( array( $slug, $slug . '-' . $page_lang ) ) ),
			'post_parent'    => $parent_id,
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		if ( $page_lang === $default_lang ) {
			$same_lang_query['meta_query'] = array(
				'relation' => 'OR',
				array( 'key' => '_align_lang', 'value' => $default_lang ),
				array( 'key' => '_align_lang', 'compare' => 'NOT EXISTS' ),
			);
		} else {
			$same_lang_query['meta_query'] = array(
				array( 'key' => '_align_lang', 'value' => $page_lang ),
			);
		}
		$same_lang_ids = get_posts( $same_lang_query );

		if ( ! empty( $same_lang_ids ) && $overwrite ) {
			foreach ( $same_lang_ids as $old_id ) {
				$old_id = is_object( $old_id ) ? $old_id->ID : (int) $old_id;
				if ( class_exists( 'Align_Builder_Asset_Cleaner' ) && method_exists( 'Align_Builder_Asset_Cleaner', 'delete_post_assets' ) ) {
					Align_Builder_Asset_Cleaner::delete_post_assets( $old_id );
				}
				wp_delete_post( $old_id, true );
			}
		}

		// Anti-collision against any remaining pages at the same parent level
		$any_existing = get_posts( array(
			'name'           => $slug,
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'trash', 'future', 'auto-draft' ),
			'post_parent'    => $parent_id,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		) );

		if ( ! empty( $any_existing ) ) {
			$base_slug = $slug;
			if ( $page_lang !== $default_lang ) {
				$cand_slug = $base_slug . '-' . $page_lang;
				$cand_collision = get_posts( array(
					'name'           => $cand_slug,
					'post_type'      => 'page',
					'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'trash', 'future', 'auto-draft' ),
					'post_parent'    => $parent_id,
					'posts_per_page' => 1,
					'fields'         => 'ids',
				) );
				if ( empty( $cand_collision ) ) {
					$slug = $cand_slug;
				} else {
					$suffix = 2;
					while ( ! empty( get_posts( array( 'name' => $cand_slug . '-' . $suffix, 'post_type' => 'page', 'post_status' => 'any', 'post_parent' => $parent_id, 'fields' => 'ids' ) ) ) ) {
						$suffix++;
					}
					$slug = $cand_slug . '-' . $suffix;
				}
			} else {
				$suffix = 2;
				while ( ! empty( class_exists( 'Align_Zip_Page_Tree' ) ? Align_Zip_Page_Tree::find_existing_page( $base_slug . '-' . $suffix, $parent_id ) : get_page_by_path( $base_slug . '-' . $suffix, OBJECT, 'page' ) ) ) {
					$suffix++;
				}
				$slug  = $base_slug . '-' . $suffix;
				$title = $title . ' ' . $suffix;
			}
		}

		$post_status = $is_placeholder ? 'draft' : 'publish';
		$post_id     = wp_insert_post( array(
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_status'  => $post_status,
			'post_type'    => 'page',
			'post_parent'  => $parent_id,
			'post_content' => '',
		) );

		if ( ! $post_id || is_wp_error( $post_id ) ) {
			return array(
				'status'   => 'error',
				'message'  => is_wp_error( $post_id ) ? $post_id->get_error_message() : __( 'Failed to create post.', 'align' ),
				'slug'     => $slug,
				'path_key' => $path_key,
			);
		}

		update_post_meta( $post_id, '_align_lang', $page_lang );

		if ( $is_placeholder ) {
			update_post_meta( $post_id, '_align_zip_placeholder', 1 );
			$block_code = self::build_custom_html_block( '', '', '', 'style-page-' . $slug, 'script-page-' . $slug );
		} else {
			$style_id   = 'style-page-' . $slug;
			$script_id  = 'script-page-' . $slug;
			$block_code = self::build_custom_html_block( $payload['body'], $payload['css'], $payload['js'], $style_id, $script_id );
		}

		kses_remove_filters();
		wp_update_post( array(
			'ID'           => $post_id,
			'post_content' => wp_slash( $block_code ),
		) );
		kses_init_filters();

		$permalink  = get_permalink( $post_id );
		$editor_url = admin_url( 'admin.php?page=align-builder&post_id=' . $post_id );

		error_log( sprintf( '[Align ZipAssembler] Page ID %d parent=%d path="%s" status="%s" -> %s', $post_id, $parent_id, $path_key, $post_status, $permalink ) );

		return array(
			'status'         => 'success',
			'post_id'        => $post_id,
			'title'          => $title,
			'slug'           => $slug,
			'path_key'       => $path_key,
			'url'            => $permalink,
			'editor_url'     => $editor_url,
			'is_home'        => ! empty( $page_info['is_home'] ),
			'is_placeholder' => $is_placeholder,
		);
	}

	/**
	 * Register an asset (CDN or local file) into the 'align_loaded_assets' option.
	 *
	 * @param string $url      Asset URL.
	 * @param string $type     'css', 'js', or 'font'.
	 * @param string $location 'header' or 'footer'.
	 * @param string $loading  'normal', 'defer', or 'async'.
	 * @return bool True if registered.
	 */
	public static function register_loaded_asset( string $url, string $type = 'css', string $location = 'header', string $loading = 'normal' ): bool {
		$url = trim( $url );
		if ( '' === $url ) return false;
		if ( 0 !== strpos( $url, '//' ) && 0 !== strpos( $url, 'http' ) ) {
			$url = wp_normalize_path( $url );
		}

		// Exact extension and intent enforcement
		$clean_url = strtolower( strtok( $url, '?#' ) );
		$ext       = pathinfo( $clean_url, PATHINFO_EXTENSION );
		$font_exts = array( 'woff2', 'woff', 'ttf', 'otf', 'eot' );

		if ( in_array( $ext, $font_exts, true ) ) {
			$type     = 'font';
			$location = 'header';
			$loading  = 'normal';
		} elseif ( 'js' === $ext || false !== stripos( $url, '/api/js' ) || preg_match( '/\.(js)(\?|$)/i', $url ) ) {
			$type     = 'js';
			$is_head  = false !== stripos( $url, 'jquery' ) || false !== stripos( $url, 'bootstrap' ) || false !== stripos( $url, 'maps.googleapis.com' );
			$location = $is_head ? 'header' : ( in_array( $location, array( 'header', 'footer' ), true ) ? $location : 'footer' );
			$loading  = $is_head ? 'normal' : ( in_array( $loading, array( 'normal', 'defer', 'async' ), true ) ? $loading : 'defer' );
		} elseif ( 'css' === $ext || false !== stripos( $url, 'fonts.googleapis.com' ) || preg_match( '/\.(css)(\?|$)/i', $url ) ) {
			$type     = 'css';
			$location = 'header';
			$loading  = 'normal';
		} else {
			$type     = in_array( $type, array( 'css', 'js', 'font' ), true ) ? $type : 'css';
			$location = in_array( $location, array( 'header', 'footer' ), true ) ? $location : ( 'js' === $type ? 'footer' : 'header' );
			$loading  = in_array( $loading, array( 'normal', 'defer', 'async' ), true ) ? $loading : ( 'js' === $type ? 'defer' : 'normal' );
		}

		$current_assets = get_option( 'align_loaded_assets', array() );
		if ( ! is_array( $current_assets ) ) $current_assets = array();

		$url_norm = preg_replace( '/^https?:/i', '', $url );
		foreach ( $current_assets as $asset ) {
			if ( is_array( $asset ) && ! empty( $asset['url'] ) && preg_replace( '/^https?:/i', '', trim( $asset['url'] ) ) === $url_norm ) {
				return true;
			}
		}

		$current_assets[] = array(
			'url'      => ( 0 === strpos( $url, '//' ) || 0 === strpos( $url, 'http' ) ) ? esc_url_raw( $url ) : $url,
			'type'     => $type,
			'location' => $location,
			'loading'  => $loading,
			'enabled'  => 1,
			'is_core'  => 0,
		);
		update_option( 'align_loaded_assets', $current_assets );
		error_log( sprintf( '[Align ZipAssembler] Registered asset: %s (%s, %s, %s)', $url, $type, $location, $loading ) );
		return true;
	}

	public static function extract_and_register_cdn_assets( string $html ): string {
		if ( empty( $html ) ) return $html;

		// 1. Scan <link rel="stylesheet"> & Google Fonts
		$link_pattern = '/<link\b([^>]*\bhref\s*=\s*(["\'])(.*?)\2[^>]*)>/is';
		$html = preg_replace_callback( $link_pattern, function( $matches ) {
			$tag     = $matches[0]; $attrs = $matches[1]; $raw_url = trim( $matches[3] );
			$is_css  = ( false !== stripos( $attrs, 'stylesheet' ) || preg_match( '/\.(css)($|\?)/i', $raw_url ) || false !== stripos( $raw_url, 'fonts.googleapis.com' ) );
			$is_cdn  = ( 0 === strpos( $raw_url, 'http://' ) || 0 === strpos( $raw_url, 'https://' ) || 0 === strpos( $raw_url, '//' ) );
			if ( $is_cdn && $is_css ) {
				self::register_loaded_asset( $raw_url, 'css', 'header', 'normal' );
				return '';
			}
			return ( $is_cdn && ( false !== stripos( $attrs, 'preconnect' ) || false !== stripos( $attrs, 'dns-prefetch' ) ) ) || $is_css ? '' : $tag;
		}, $html );

		// 2. Scan <script src="..."></script>
		$script_pattern = '/<script\b([^>]*\bsrc\s*=\s*(["\'])(.*?)\2[^>]*)>[\s\S]*?<\/script>/is';
		return preg_replace_callback( $script_pattern, function( $matches ) {
			$raw_url = trim( $matches[3] );
			$is_cdn  = ( 0 === strpos( $raw_url, 'http://' ) || 0 === strpos( $raw_url, 'https://' ) || 0 === strpos( $raw_url, '//' ) );
			if ( $is_cdn ) {
				$is_jquery = ( false !== stripos( $raw_url, 'jquery' ) );
				self::register_loaded_asset( $raw_url, 'js', $is_jquery ? 'header' : 'footer', $is_jquery ? 'normal' : 'defer' );
			}
			return '';
		}, $html );
	}

	/**
	 * Extract body content, inline style tags, and inline script tags from full HTML.
	 *
	 * @param string $html Full HTML document.
	 * @return array Extracted parts: body, css, js.
	 */
	private static function extract_markup_and_scripts( string $html ): array {
		$clean_html = self::extract_and_register_cdn_assets( trim( $html ) );
		$css        = '';
		$js         = '';

		if ( preg_match_all( '/<style\b[^>]*>([\s\S]*?)<\/style>/is', $clean_html, $style_matches ) ) {
			$css .= "\n" . implode( "\n", array_map( 'trim', $style_matches[1] ) );
			$clean_html = preg_replace( '/<style\b[^>]*>[\s\S]*?<\/style>/is', '', $clean_html );
		}

		if ( preg_match_all( '/<script\b(?![^>]*\bsrc\s*=)[^>]*>([\s\S]*?)<\/script>/is', $clean_html, $script_matches ) ) {
			$js .= "\n" . implode( "\n", array_map( 'trim', $script_matches[1] ) );
			$clean_html = preg_replace( '/<script\b(?![^>]*\bsrc\s*=)[^>]*>[\s\S]*?<\/script>/is', '', $clean_html );
		}

		if ( preg_match( '/<body[^>]*>(.*?)<\/body>/is', $clean_html, $body_m ) ) {
			$clean_html = trim( $body_m[1] );
		} else {
			$clean_html = trim( preg_replace( '/<\/?(html|head|body)\b[^>]*>|<!DOCTYPE[^>]*>/is', '', $clean_html ) );
		}

		$clean_html = preg_replace( '/<style[^>]*id="wp-global-styles"[^>]*>[\s\S]*?<\/style>/i', '', $clean_html );
		$clean_html = preg_replace( '/<(script|style)[^>]*data-align-preview="1"[^>]*>[\s\S]*?<\/\1>/i', '', $clean_html );

		if ( str_contains( $clean_html, 'pin-spacer' ) && class_exists( 'Align_Builder_Persistence_Service' ) ) {
			$clean_html = Align_Builder_Persistence_Service::unwrap_runtime_spacers( $clean_html );
		}

		return array( 'body' => trim( $clean_html ), 'css' => trim( $css ), 'js' => trim( $js ) );
	}

	/**
	 * Assemble and save a WordPress Global Template Part (Header or Footer).
	 *
	 * @param array  $part_info Discovered template part info.
	 * @param array  $options   Import options.
	 * @param string $root_dir  Root project directory.
	 * @return array Result containing status, slug, and file path.
	 */
	public static function assemble_template_part( array $part_info, array $options, string $root_dir ): array {
		$slug = $part_info['slug'];
		error_log( sprintf( '[Align ZipAssembler] Assembling Global Template Part "%s"', $slug ) );

		$payload = self::prepare_component_payload( $part_info, $root_dir );
		if ( ! $payload ) {
			return array(
				'status'  => 'error',
				'message' => sprintf( __( 'Failed to prepare Template Part payload: %s', 'align' ), $slug ),
				'slug'    => $slug,
			);
		}

		$style_id   = 'style-part-' . $slug;
		$script_id  = 'script-part-' . $slug;
		$block_code = self::build_custom_html_block( $payload['body'], $payload['css'], $payload['js'], $style_id, $script_id );

		$parts_dir = wp_normalize_path( trailingslashit( get_stylesheet_directory() ) . 'parts' );
		if ( ! is_dir( $parts_dir ) ) {
			wp_mkdir_p( $parts_dir );
		}

		$dest_file = wp_normalize_path( trailingslashit( $parts_dir ) . $slug . '.html' );
		$written   = @file_put_contents( $dest_file, $block_code );
		if ( ! $written ) {
			wp_insert_post( array(
				'post_title'   => ucfirst( $slug ),
				'post_name'    => $slug,
				'post_content' => $block_code,
				'post_status'  => 'publish',
				'post_type'    => 'wp_template_part',
				'tax_input'    => array(
					'wp_theme'              => array( get_stylesheet() ),
					'wp_template_part_area' => array( ( 0 === strpos( $slug, 'header' ) ) ? 'header' : ( ( 0 === strpos( $slug, 'footer' ) ) ? 'footer' : 'uncategorized' ) ),
				),
			) );
		} else {
			$existing_parts = get_posts( array(
				'post_type'      => 'wp_template_part',
				'post_status'    => array( 'publish', 'draft', 'private', 'trash', 'future', 'inherit' ),
				'name'           => $slug,
				'posts_per_page' => 1,
			) );
			if ( ! empty( $existing_parts ) ) {
				wp_delete_post( $existing_parts[0]->ID, true );
			}
		}

		if ( function_exists( 'wp_clean_theme_json_cache' ) ) {
			wp_clean_theme_json_cache();
		}

		error_log( sprintf( '[Align ZipAssembler] Successfully assembled Template Part "%s" to %s', $slug, $dest_file ) );

		return array(
			'status'     => 'success',
			'is_part'    => true,
			'title'      => ucfirst( $slug ) . ' (Global ' . ucfirst( $slug ) . ')',
			'slug'       => $slug,
			'file_path'  => $dest_file,
			'post_id'    => 'Template Part',
			'url'        => home_url( '/' ),
			'editor_url' => admin_url( 'site-editor.php?postType=wp_template_part&postId=' . urlencode( get_stylesheet() . '//' . $slug ) ),
		);
	}

	/**
	 * Assemble a WordPress Reusable Block (wp_block Synced Pattern).
	 *
	 * @param array  $block_info Scanned block metadata.
	 * @param array  $options    Import options.
	 * @param string $root_dir   Root project directory.
	 * @return array Result metadata.
	 */
	public static function assemble_reusable_block( array $block_info, array $options, string $root_dir ): array {
		$slug  = $block_info['slug'];
		$title = $block_info['title'];

		error_log( sprintf( '[Align ZipAssembler] Assembling Reusable Block "%s" (slug: %s)', $title, $slug ) );

		$payload = self::prepare_component_payload( $block_info, $root_dir );
		if ( ! $payload ) {
			return array(
				'status'  => 'error',
				'message' => sprintf( __( 'Failed to prepare Reusable Block payload: %s', 'align' ), $slug ),
				'slug'    => $slug,
			);
		}

		// Find existing wp_block strictly by post_type to prevent media attachment slug collisions
		$found    = get_posts( array( 'post_type' => 'wp_block', 'name' => $slug, 'post_status' => 'any', 'posts_per_page' => 1 ) );
		$existing = ! empty( $found ) ? $found[0] : null;

		if ( $existing ) {
			$post_id = $existing->ID;
		} else {
			$post_id = wp_insert_post( array(
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'wp_block',
				'post_content' => '',
			) );
		}

		if ( ! $post_id || is_wp_error( $post_id ) ) {
			return array(
				'status'  => 'error',
				'message' => is_wp_error( $post_id ) ? $post_id->get_error_message() : __( 'Failed to create Reusable Block post.', 'align' ),
				'slug'    => $slug,
			);
		}

		$style_id   = 'style-block-' . $slug;
		$script_id  = 'script-block-' . $slug;
		$block_code = self::build_custom_html_block( $payload['body'], $payload['css'], $payload['js'], $style_id, $script_id );

		kses_remove_filters();
		wp_update_post( array(
			'ID'           => $post_id,
			'post_content' => wp_slash( $block_code ),
		) );
		kses_init_filters();

		$shortcode  = sprintf( '[reuse-block id="%d"]', $post_id );
		$editor_url = admin_url( 'admin.php?page=align-builder&post_id=' . $post_id );

		error_log( sprintf( '[Align ZipAssembler] Successfully assembled Reusable Block ID %d ("%s") -> %s', $post_id, $title, $shortcode ) );

		return array(
			'status'     => 'success',
			'is_block'   => true,
			'post_id'    => $post_id,
			'title'      => $title,
			'slug'       => $slug,
			'shortcode'  => $shortcode,
			'url'        => $editor_url,
			'editor_url' => $editor_url,
		);
	}

	/**
	 * Prepare extracted body, scoped CSS, and scoped JS for a page, template part, or reusable block.
	 *
	 * @param array  $info     Component metadata.
	 * @param string $root_dir Root project directory.
	 * @return array|null Extracted payload array or null on error.
	 */
	public static function prepare_component_payload( array $info, string $root_dir ): ?array {
		$html_file = ! empty( $info['html_file'] ) ? wp_normalize_path( $info['html_file'] ) : '';
		if ( ! file_exists( $html_file ) || ! is_readable( $html_file ) ) {
			return null;
		}

		$raw_html = file_get_contents( $html_file );
		if ( false === $raw_html ) {
			return null;
		}

		$extracted = self::extract_markup_and_scripts( $raw_html );
		$body_html = $extracted['body'];
		$css       = $extracted['css'];
		$js        = $extracted['js'];
		$root_dir  = wp_normalize_path( trailingslashit( $root_dir ) );
		$folder_p  = ! empty( $info['folder_path'] ) ? wp_normalize_path( trailingslashit( $info['folder_path'] ) ) : $root_dir;

		if ( ! empty( $info['css_files'] ) ) {
			foreach ( $info['css_files'] as $css_f ) {
				$css_f = wp_normalize_path( $css_f );
				if ( file_exists( $css_f ) && is_readable( $css_f ) ) {
					$c = file_get_contents( $css_f );
					if ( false !== $c && '' !== trim( $c ) ) {
						$css .= "\n" . trim( $c );
					}
				}
			}
		}

		if ( ! empty( $info['js_files'] ) ) {
			foreach ( $info['js_files'] as $js_f ) {
				$js_f = wp_normalize_path( $js_f );
				if ( file_exists( $js_f ) && is_readable( $js_f ) ) {
					$j = file_get_contents( $js_f );
					if ( false !== $j && '' !== trim( $j ) ) {
						$js .= "\n" . trim( $j );
					}
				}
			}
		}

		$body_html = Align_Zip_Media_Rewriter::rewrite_html_assets( $body_html, $folder_p, $root_dir );
		if ( '' !== trim( $css ) ) {
			$css = Align_Zip_Media_Rewriter::rewrite_css_urls( $css, $folder_p, $root_dir );
		}

		return array(
			'body' => $body_html,
			'css'  => trim( $css ),
			'js'   => trim( $js ),
		);
	}

	/**
	 * Build Gutenberg custom-html block payload with external asset sync.
	 *
	 * @param string $body_html Body HTML.
	 * @param string $css       Scoped CSS.
	 * @param string $js        Scoped JS.
	 * @param string $style_id  Style ID.
	 * @param string $script_id Script ID.
	 * @return string Gutenberg block markup.
	 */
	public static function build_custom_html_block( string $body_html, string $css, string $js, string $style_id, string $script_id ): string {
		if ( class_exists( 'Align_Builder_Assets' ) && method_exists( 'Align_Builder_Assets', 'save_external_assets' ) ) {
			Align_Builder_Assets::save_external_assets( $style_id, $script_id, $css, $js );
		}
		if ( class_exists( 'Align_Builder_Minifier' ) ) {
			$clean_payload = Align_Builder_Minifier::minify_payload( $body_html, $css, $js, $style_id, $script_id );
		} else {
			$assembled = '';
			if ( '' !== trim( $css ) ) {
				$assembled .= '<style id="' . esc_attr( $style_id ) . '">' . $css . '</style>';
			}
			$assembled .= $body_html;
			if ( '' !== trim( $js ) ) {
				$assembled .= '<script id="' . esc_attr( $script_id ) . '">' . $js . '</script>';
			}
			$clean_payload = $assembled;
		}

		$block_id   = 'align-' . substr( md5( uniqid( '', true ) ), 0, 8 );
		$attrs_json = wp_json_encode( array( 'blockId' => $block_id, 'enableShortcode' => true ) );
		return "<!-- wp:align/custom-html " . $attrs_json . " -->\n" . $clean_payload . "\n<!-- /wp:align/custom-html -->";
	}
}
