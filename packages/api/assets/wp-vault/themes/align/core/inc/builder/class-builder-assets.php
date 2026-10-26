<?php
/**
 * Align Theme Core Engine - Builder Asset Management & Resolvers
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Builder_Assets {

	/** Resolve asset URL dynamically, converting relative paths to absolute site URLs. */
	public static function resolve_asset_url( $url, $with_version = true ) {
		if ( empty( $url ) || ! is_string( $url ) ) return '';
		$trimmed = trim( $url );
		if ( '' === $trimmed ) return '';

		if ( class_exists( 'Align_Custom_CSS_JS' ) && method_exists( 'Align_Custom_CSS_JS', 'resolve_asset_url' ) ) {
			$resolved = Align_Custom_CSS_JS::resolve_asset_url( $trimmed, $with_version );
			if ( ! empty( $resolved ) ) {
				return ( 0 !== strpos( $resolved, '//' ) && ! preg_match( '#^https?://#i', $resolved ) ) ? site_url( '/' . ltrim( $resolved, '/' ) ) : $resolved;
			}
		}
		if ( 0 === strpos( $trimmed, '//' ) || preg_match( '#^https?://#i', $trimmed ) ) return $trimmed;
		return site_url( '/' . ltrim( $trimmed, '/' ) );
	}

	/** Check if an asset entry is enabled. */
	public static function is_asset_enabled( array $asset ) {
		if ( ! isset( $asset['enabled'] ) ) return false;
		$raw = $asset['enabled'];
		if ( is_bool( $raw ) ) return $raw;
		if ( is_numeric( $raw ) ) return 1 === (int) $raw;
		return is_string( $raw ) ? in_array( strtolower( trim( $raw ) ), array( '1', 'true', 'on' ), true ) : false;
	}

	/** Get builder CSS URLs including defaults, child theme global styles, and custom assets. */
	public static function get_builder_css_urls() {
		$theme_css_urls = array();
		if ( file_exists( get_template_directory() . '/style.css' ) ) {
			$theme_css_urls[] = self::resolve_asset_url( get_template_directory_uri() . '/style.css' );
		}
		if ( file_exists( get_stylesheet_directory() . '/style.css' ) && get_stylesheet_directory() !== get_template_directory() ) {
			$theme_css_urls[] = self::resolve_asset_url( get_stylesheet_directory_uri() . '/style.css' );
		}

		// Auto-discover child theme global CSS files
		$child_dir = get_stylesheet_directory();
		$child_uri = get_stylesheet_directory_uri();
		$global_css_candidates = array(
			'assets/root.css',
			'assets/src/general/css/root.css',
			'assets/dist/general/css/root.min.css',
			'assets/bootstrap-reboot.min.css',
			'assets/bootstrap-utilities.min.css',
			'assets/bootstrap-grid.min.css',
		);
		foreach ( $global_css_candidates as $rel_css ) {
			if ( file_exists( $child_dir . '/' . $rel_css ) ) {
				$theme_css_urls[] = self::resolve_asset_url( $child_uri . '/' . $rel_css );
			}
		}

		$assets = get_option( 'align_loaded_assets', array() );
		if ( is_array( $assets ) && ! empty( $assets ) ) {
			foreach ( $assets as $asset ) {
				if ( ! is_array( $asset ) ) continue;
				$type    = isset( $asset['type'] ) && is_string( $asset['type'] ) ? strtolower( trim( $asset['type'] ) ) : 'css';
				$raw_url = ( isset( $asset['url'] ) && is_string( $asset['url'] ) ) ? trim( $asset['url'] ) : '';
				if ( 'css' === $type && self::is_asset_enabled( $asset ) && '' !== $raw_url ) {
					$url = self::resolve_asset_url( $raw_url );
					if ( '' !== $url ) $theme_css_urls[] = $url;
				}
			}
		}

		// Automatically include registered dynamic block stylesheets (e.g. live-search/style.css, breadcrumb, etc.)
		$dynamic_blocks_dir = defined( 'ALIGN_DIR_PATH' ) ? ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/' : ( get_template_directory() . '/core/blocks/blocks/dynamic/' );
		if ( is_dir( $dynamic_blocks_dir ) ) {
			$block_styles = glob( $dynamic_blocks_dir . '*/style.css' );
			if ( ! empty( $block_styles ) ) {
				foreach ( $block_styles as $style_file ) {
					$rel_path  = defined( 'ALIGN_DIR_PATH' ) ? str_replace( ALIGN_DIR_PATH, '', $style_file ) : str_replace( get_template_directory(), '', $style_file );
					$base_url  = ( defined( 'ALIGN_DIR_PATH' ) && defined( 'ALIGN_DIR_URL' ) ) ? ALIGN_DIR_URL : get_template_directory_uri();
					$theme_css_urls[] = self::resolve_asset_url( $base_url . str_replace( '\\', '/', $rel_path ) );
				}
			}
		}

		// Auto-discover child theme module CSS files (e.g. kit-builder.css, shop-page.css, etc.)
		$child_modules_css = glob( $child_dir . '/inc/*/assets/css/*.css' );
		if ( ! empty( $child_modules_css ) ) {
			foreach ( $child_modules_css as $mod_css ) {
				if ( str_contains( basename( $mod_css ), '-admin' ) ) continue;
				$rel_css = str_replace( $child_dir . '/', '', $mod_css );
				$theme_css_urls[] = self::resolve_asset_url( $child_uri . '/' . $rel_css );
			}
		}

		return array_values( array_unique( array_filter( array_map( 'esc_url_raw', $theme_css_urls ) ) ) );
	}

	/** Get builder JS assets from Asset Loader tab and child theme main JS. */
	public static function get_builder_js_assets( $target_location = 'all' ) {
		$js_assets  = array();
		$target_loc = is_string( $target_location ) ? strtolower( trim( $target_location ) ) : 'all';

		$assets = get_option( 'align_loaded_assets', array() );
		if ( is_array( $assets ) && ! empty( $assets ) ) {
			foreach ( $assets as $asset ) {
				if ( ! is_array( $asset ) || ! self::is_asset_enabled( $asset ) ) continue;
				$type     = isset( $asset['type'] ) && is_string( $asset['type'] ) ? strtolower( trim( $asset['type'] ) ) : 'js';
				$raw_url  = isset( $asset['url'] ) && is_string( $asset['url'] ) ? trim( $asset['url'] ) : '';
				$location = isset( $asset['location'] ) && in_array( strtolower( trim( $asset['location'] ) ), array( 'header', 'footer' ), true ) ? strtolower( trim( $asset['location'] ) ) : 'header';
				$loading  = isset( $asset['loading'] ) && in_array( strtolower( trim( $asset['loading'] ) ), array( 'defer', 'async' ), true ) ? strtolower( trim( $asset['loading'] ) ) : 'normal';

				if ( 'js' === $type && '' !== $raw_url && ( 'all' === $target_loc || $location === $target_loc ) ) {
					$clean_url = esc_url_raw( self::resolve_asset_url( $raw_url ) );
					if ( '' !== $clean_url ) $js_assets[] = array( 'url' => $clean_url, 'location' => $location, 'loading' => $loading );
				}
			}
		}


		// Include child theme main JS if present
		$child_dir = get_stylesheet_directory();
		$child_uri = get_stylesheet_directory_uri();
		if ( 'all' === $target_loc || 'footer' === $target_loc ) {
			$main_js_candidates = array( 'assets/dist/general/js/main.min.js', 'assets/src/general/js/main.js', 'assets/main.js' );
			foreach ( $main_js_candidates as $rel_js ) {
				if ( file_exists( $child_dir . '/' . $rel_js ) ) {
					$js_assets[] = array( 'url' => esc_url_raw( self::resolve_asset_url( $child_uri . '/' . $rel_js ) ), 'location' => 'footer', 'loading' => 'normal' );
					break;
				}
			}
			$prod_details_rel = 'inc/details-product-page/assets/js/product-details.js';
			if ( file_exists( $child_dir . '/' . $prod_details_rel ) ) {
				$js_assets[] = array( 'url' => esc_url_raw( self::resolve_asset_url( $child_uri . '/' . $prod_details_rel ) ), 'location' => 'footer', 'loading' => 'normal' );
			}
		}


		// Automatically include registered dynamic block frontend scripts (e.g. live-search/script.js)
		$dynamic_blocks_dir = defined( 'ALIGN_DIR_PATH' ) ? ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/' : ( get_template_directory() . '/core/blocks/blocks/dynamic/' );
		if ( is_dir( $dynamic_blocks_dir ) ) {
			$block_scripts = glob( $dynamic_blocks_dir . '*/script.js' );
			if ( ! empty( $block_scripts ) ) {
				foreach ( $block_scripts as $script_file ) {

					$rel_path   = defined( 'ALIGN_DIR_PATH' ) ? str_replace( ALIGN_DIR_PATH, '', $script_file ) : str_replace( get_template_directory(), '', $script_file );
					$base_url   = ( defined( 'ALIGN_DIR_PATH' ) && defined( 'ALIGN_DIR_URL' ) ) ? ALIGN_DIR_URL : get_template_directory_uri();
					$clean_url  = esc_url_raw( self::resolve_asset_url( $base_url . str_replace( '\\', '/', $rel_path ) ) );
					if ( '' !== $clean_url ) $js_assets[] = array( 'url' => $clean_url, 'location' => 'footer', 'loading' => 'normal' );
				}
			}
		}
		// Deduplicate assets by URL
		$seen_urls     = array();
		$unique_assets = array();
		foreach ( $js_assets as $asset ) {
			if ( ! empty( $asset['url'] ) && ! isset( $seen_urls[ $asset['url'] ] ) ) {
				$seen_urls[ $asset['url'] ] = true;
				$unique_assets[]            = $asset;
			}
		}
		return $unique_assets;

	}

	/** Generate Font Preload HTML tags for Live Canvas preview. */
	public static function get_font_preloads() {
		$assets        = get_option( 'align_loaded_assets', array() );
		$font_preloads = array();
		if ( is_array( $assets ) && ! empty( $assets ) ) {
			$mime_types = array( 'woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf', 'otf' => 'font/otf', 'eot' => 'application/vnd.ms-fontobject' );
			foreach ( $assets as $asset ) {
				if ( ! is_array( $asset ) || ! self::is_asset_enabled( $asset ) ) continue;
				$type     = isset( $asset['type'] ) ? strtolower( trim( $asset['type'] ) ) : '';
				$location = isset( $asset['location'] ) ? strtolower( trim( $asset['location'] ) ) : 'header';
				$raw_url  = isset( $asset['url'] ) ? trim( $asset['url'] ) : '';
				if ( 'font' === $type && '' !== $raw_url && 'footer' !== $location ) {
					$url = self::resolve_asset_url( $raw_url, false );
					if ( '' !== $url ) {
						$ext       = strtolower( pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
						$mime_attr = isset( $mime_types[ $ext ] ) ? ' type="' . $mime_types[ $ext ] . '"' : '';
						$font_preloads[] = '<link rel="preload" href="' . esc_url( $url ) . '" as="font"' . $mime_attr . ' crossorigin data-align-preview="1">';
					}
				}
			}
		}
		return $font_preloads;
	}

	/** Replace non-wp-content dummy image URLs with Picsum placeholder photos. */
	public static function replace_dummy_images( $html ) {
		if ( empty( $html ) || ! is_string( $html ) ) return $html;
		$target_url   = 'https://picsum.photos/seed/align/600/400';
		$is_preserved = function( $url ) use ( $target_url ) {
			if ( empty( $url ) || ! is_string( $url ) ) return true;
			$trimmed = trim( $url );
			if ( '' === $trimmed || 'none' === $trimmed || 0 === strpos( $trimmed, '#' ) ) return true;
			if ( 0 === strpos( $trimmed, '[' ) || false !== strpos( $trimmed, '[' ) || 0 === strpos( $trimmed, '{' ) ) return true;
			if ( false !== strpos( $trimmed, 'picsum.photos' ) || false !== strpos( $trimmed, 'placeholder' ) || $trimmed === $target_url ) return true;
			if ( false !== strpos( $trimmed, 'wp-content' ) || false !== strpos( $trimmed, 'wp-includes' ) || false !== strpos( $trimmed, 'uploads' ) ) return true;
			if ( 0 === strpos( $trimmed, 'data:' ) || 0 === strpos( $trimmed, 'blob:' ) ) return true;
			if ( 0 === strpos( $trimmed, '//' ) || preg_match( '#^https?://#i', $trimmed ) ) return true;
			return false;
		};

		$replace_css_urls = function( $css ) use ( $target_url, $is_preserved ) {
			if ( empty( $css ) || false === stripos( $css, 'url(' ) ) return $css;
			return preg_replace_callback( '/url\(\s*([\'"]?)(.*?)\1\s*\)/i', function( $matches ) use ( $target_url, $is_preserved ) {
				$url = trim( $matches[2] );
				return $is_preserved( $url ) ? $matches[0] : ( 'url(' . $matches[1] . $target_url . $matches[1] . ')' );
			}, $css );
		};

		// Protect custom dynamic component containers (shortcodes, wp blocks, post content)
		$placeholders    = array();
		$placeholder_idx = 0;
		$html = preg_replace_callback(
			'/<(?P<tag>[a-zA-Z0-9\-]+)\b[^>]*\b(?:data-component-shortcode|data-component-wp-block|data-component-post-content)\b[^>]*>(?:.*?<\/\k<tag>>)?/is',
			function( $matches ) use ( &$placeholders, &$placeholder_idx ) {
				$key = '<!--ALIGN_DYNAMIC_COMPONENT_BLOCK_' . ( $placeholder_idx++ ) . '-->';
				$placeholders[ $key ] = $matches[0];
				return $key;
			},
			$html
		);

		if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$processor = new WP_HTML_Tag_Processor( $html );
			while ( $processor->next_tag() ) {
				if ( null !== $processor->get_attribute( 'data-component-shortcode' ) ||
				     null !== $processor->get_attribute( 'data-component-wp-block' ) ||
				     null !== $processor->get_attribute( 'data-component-post-content' ) ||
				     null !== $processor->get_attribute( 'data-vvveb-helpers' ) ) {
					continue;
				}

				$tag_name = $processor->get_tag();
				if ( in_array( $tag_name, array( 'IMG', 'SOURCE' ), true ) ) {
					if ( 'IMG' === $tag_name ) {
						$src = $processor->get_attribute( 'src' );
						if ( null !== $src && ! $is_preserved( $src ) ) {
							$processor->set_attribute( 'src', $target_url );
							$processor->remove_attribute( 'srcset' );
							$processor->remove_attribute( 'data-srcset' );
							$processor->remove_attribute( 'sizes' );
						}
						$data_src = $processor->get_attribute( 'data-src' );
						if ( null !== $data_src && ! $is_preserved( $data_src ) ) $processor->set_attribute( 'data-src', $target_url );
					} else {
						foreach ( array( 'srcset', 'data-srcset' ) as $attr ) {
							$val = $processor->get_attribute( $attr );
							if ( null !== $val && ! $is_preserved( $val ) ) $processor->set_attribute( $attr, $target_url );
						}
					}
				}
				$data_bg_src = $processor->get_attribute( 'data-bg-src' );
				if ( null !== $data_bg_src && ! $is_preserved( $data_bg_src ) ) $processor->set_attribute( 'data-bg-src', $target_url );
				$style = $processor->get_attribute( 'style' );
				if ( null !== $style && false !== stripos( $style, 'url(' ) ) {
					$new_style = $replace_css_urls( $style );
					if ( $new_style !== $style ) $processor->set_attribute( 'style', $new_style );
				}
			}
			$html = $processor->get_updated_html();
		} else {
			$pattern = '#(?:(?P<attr_name>src|data-src|srcset|data-srcset|data-bg-src)=(?P<quote>[\'"])(?P<url>[^\'"]+)\k<quote>)|(?:url\((?P<css_quote>[\'"]?)(?P<css_url>[^\'"\)]+)\k<css_quote>\))#i';
			$html    = preg_replace_callback( $pattern, function ( $matches ) use ( $target_url, $is_preserved ) {
				$is_attr = ! empty( $matches['attr_name'] );
				$url     = $is_attr ? $matches['url'] : ( $matches['css_url'] ?? '' );
				if ( $is_preserved( $url ) ) return $matches[0];
				return $is_attr ? $matches['attr_name'] . '=' . $matches['quote'] . $target_url . $matches['quote'] : 'url(' . ( $matches['css_quote'] ?? '' ) . $target_url . ( $matches['css_quote'] ?? '' ) . ')';
			}, $html );
		}

		if ( false !== stripos( $html, '<style' ) ) {
			$html = preg_replace_callback( '#(<style\b[^>]*>)(.*?)(</style>)#is', function ( $matches ) use ( $replace_css_urls ) {
				return $matches[1] . $replace_css_urls( $matches[2] ) . $matches[3];
			}, $html );
		}

		return ! empty( $placeholders ) ? strtr( $html, $placeholders ) : $html;
	}

	/** Get absolute directory path for builder static assets. */
	public static function get_builder_assets_dir() {
		$dir = trailingslashit( get_stylesheet_directory() ) . 'assets/builder/';
		if ( ! file_exists( $dir ) && function_exists( 'wp_mkdir_p' ) ) wp_mkdir_p( $dir );
		return $dir;
	}

	/** Get URI for builder static assets. */
	public static function get_builder_assets_uri() {
		return trailingslashit( get_stylesheet_directory_uri() ) . 'assets/builder/';
	}

	/** Save a single external asset file with minification and legacy file cleanup. */
	public static function save_single_asset( string $type, string $id, string $code, $legacy_ids = array(), bool $allow_delete = true ) {
		if ( class_exists( 'Align_Builder_Asset_Writer' ) ) {
			Align_Builder_Asset_Writer::save_single_asset( $type, $id, $code, $legacy_ids, $allow_delete );
		}
	}

	/** Save CSS and JS assets to external files on disk with minification and legacy file cleanup (destructive on empty). */
	public static function save_external_assets( $style_id, $script_id, $css_code, $js_code, $legacy_style_ids = array(), $legacy_script_ids = array() ) {
		if ( class_exists( 'Align_Builder_Asset_Writer' ) ) {
			Align_Builder_Asset_Writer::save_external_assets( $style_id, $script_id, $css_code, $js_code, $legacy_style_ids, $legacy_script_ids );
		}
	}

	/** Synchronize CSS and JS assets to external files on disk (non-destructive on empty). */
	public static function sync_external_assets( $style_id, $script_id, $css_code, $js_code, $legacy_style_ids = array(), $legacy_script_ids = array() ) {
		if ( class_exists( 'Align_Builder_Asset_Writer' ) ) {
			Align_Builder_Asset_Writer::sync_external_assets( $style_id, $script_id, $css_code, $js_code, $legacy_style_ids, $legacy_script_ids );
		}
	}

	/** Load external asset content from disk with legacy ID fallbacks. */
	private static function load_single_asset( string $type, string $id, string $fallback = '', $legacy_ids = array() ): string {
		if ( empty( $id ) ) return $fallback;
		$dir     = self::get_builder_assets_dir();
		$safe_id = sanitize_file_name( $id );
		$ext     = '.' . $type;
		$file    = $dir . $safe_id . $ext;

		$canonical_fallback = trim( $fallback );
		if ( '' !== $canonical_fallback ) {
			if ( file_exists( $file ) && is_readable( $file ) ) {
				$content = file_get_contents( $file );
				if ( false !== $content && '' !== trim( $content ) ) {
					error_log( sprintf( '[Align ContentHandler] Loaded external %s for ID %s (Bytes: %d)', strtoupper( $type ), $id, strlen( $content ) ) );
					return $content;
				}
			}
			return $canonical_fallback;
		}

		if ( file_exists( $file ) && is_readable( $file ) ) {
			$content = file_get_contents( $file );
			if ( false !== $content && '' !== trim( $content ) ) {
				error_log( sprintf( '[Align ContentHandler] Loaded external %s for ID %s (Bytes: %d)', strtoupper( $type ), $id, strlen( $content ) ) );
				return $content;
			}
		}

		$legacies = is_array( $legacy_ids ) ? $legacy_ids : array_filter( array( (string) $legacy_ids ) );
		if ( 0 !== strpos( $id, 'align-' ) ) $legacies[] = 'align-' . $id;

		foreach ( array_unique( array_filter( $legacies ) ) as $legacy_id ) {
			if ( $legacy_id === $id ) continue;
			$legacy_file = $dir . sanitize_file_name( $legacy_id ) . $ext;
			if ( file_exists( $legacy_file ) && is_readable( $legacy_file ) ) {
				$content = file_get_contents( $legacy_file );
				if ( false !== $content && '' !== trim( $content ) ) {
					error_log( sprintf( '[Align ContentHandler] Loaded legacy %s for ID %s (Bytes: %d)', strtoupper( $type ), $legacy_id, strlen( $content ) ) );
					return $content;
				}
			}
		}

		return $fallback;
	}

	/** Load external CSS content from disk if available, with fallback and legacy ID support. */
	public static function load_external_css( $style_id, $fallback_css = '', $legacy_style_ids = array() ) {
		return self::load_single_asset( 'css', (string) $style_id, (string) $fallback_css, $legacy_style_ids );
	}

	/** Load external JS content from disk if available, with fallback and legacy ID support. */
	public static function load_external_js( $script_id, $fallback_js = '', $legacy_script_ids = array() ) {
		return self::load_single_asset( 'js', (string) $script_id, (string) $fallback_js, $legacy_script_ids );
	}

	/** Enqueue an asset file from disk, resolving prefix differences and numeric ID fallbacks. */
	public static function enqueue_disk_asset( string $type, string $id, string $assets_dir = '', string $assets_uri = '' ): bool {
		if ( empty( $assets_dir ) ) { $assets_dir = self::get_builder_assets_dir(); }
		if ( empty( $assets_uri ) ) { $assets_uri = self::get_builder_assets_uri(); }
		$ext       = '.' . $type;
		$file_path = $assets_dir . sanitize_file_name( $id ) . $ext;
		$prefix    = ( 'css' === $type ) ? 'style-' : 'script-';

		if ( ! file_exists( $file_path ) && 0 === strpos( $id, 'align-' . $prefix ) ) {
			$clean_id   = substr( $id, 6 );
			$clean_file = $assets_dir . sanitize_file_name( $clean_id ) . $ext;
			if ( file_exists( $clean_file ) && is_readable( $clean_file ) ) {
				$file_path = $clean_file;
				$id        = $clean_id;
			}
		} elseif ( ! file_exists( $file_path ) && 0 === strpos( $id, $prefix ) ) {
			$legacy_id   = 'align-' . $id;
			$legacy_file = $assets_dir . sanitize_file_name( $legacy_id ) . $ext;
			if ( file_exists( $legacy_file ) && is_readable( $legacy_file ) ) {
				$file_path = $legacy_file;
				$id        = $legacy_id;
			}
		}

		if ( ! file_exists( $file_path ) && preg_match( '/^(?:align-)?' . $prefix . '(page|block)-(\d+)$/', $id, $id_m ) ) {
			$post = get_post( intval( $id_m[2] ) );
			if ( $post && ! empty( $post->post_name ) ) {
				$slug_clean  = $assets_dir . sanitize_file_name( $prefix . $id_m[1] . '-' . $post->post_name ) . $ext;
				$slug_legacy = $assets_dir . sanitize_file_name( 'align-' . $prefix . $id_m[1] . '-' . $post->post_name ) . $ext;
				if ( file_exists( $slug_clean ) && is_readable( $slug_clean ) ) {
					$file_path = $slug_clean;
					$id        = $prefix . $id_m[1] . '-' . $post->post_name;
				} elseif ( file_exists( $slug_legacy ) && is_readable( $slug_legacy ) ) {
					$file_path = $slug_legacy;
					$id        = 'align-' . $prefix . $id_m[1] . '-' . $post->post_name;
				}
			}
		}

		if ( file_exists( $file_path ) && is_readable( $file_path ) ) {
			clearstatcache( true, $file_path );
			$file_url = $assets_uri . basename( $file_path );
			$version  = (string) filemtime( $file_path );
			if ( 'css' === $type && function_exists( 'wp_enqueue_style' ) ) {
				wp_enqueue_style( $id, $file_url, array(), $version );
			} elseif ( 'js' === $type && function_exists( 'wp_enqueue_script' ) ) {
				wp_enqueue_script( $id, $file_url, array(), $version, true );
			}
			error_log( sprintf( '[Align CustomHTML Block] Enqueued external %s [%s] -> %s (v=%s)', strtoupper( $type ), $id, $file_url, $version ) );
			return true;
		}

		return false;
	}

	/** Process HTML markup, enqueue external CSS/JS files if present on disk, and strip inline tags. */
	public static function process_and_enqueue_external_assets( $html ) {
		if ( empty( $html ) || ! is_string( $html ) || ! (bool) get_option( 'align_builder_external_assets', 0 ) ) return $html;

		$assets_dir  = self::get_builder_assets_dir();
		$assets_uri  = self::get_builder_assets_uri();
		$css_pattern = '/<style[^>]*\bid\s*=\s*(["\'])((?:align-)?style-[^"\'\s>]+)\1[^>]*>[\s\S]*?<\/style>/is';
		$js_pattern  = '/<script[^>]*\bid\s*=\s*(["\'])((?:align-)?script-[^"\'\s>]+)\1[^>]*>[\s\S]*?<\/script>/is';

		$html = preg_replace_callback( $css_pattern, function ( $matches ) use ( $assets_dir, $assets_uri ) {
			return self::enqueue_disk_asset( 'css', $matches[2], $assets_dir, $assets_uri ) ? '' : $matches[0];
		}, $html );

		return preg_replace_callback( $js_pattern, function ( $matches ) use ( $assets_dir, $assets_uri ) {
			return self::enqueue_disk_asset( 'js', $matches[2], $assets_dir, $assets_uri ) ? '' : $matches[0];
		}, $html );
	}

	/** Purge or migrate legacy builder asset files with align- prefix in assets/builder/. */
	public static function purge_legacy_builder_assets() {
		return class_exists( 'Align_Builder_Asset_Cleaner' ) ? Align_Builder_Asset_Cleaner::purge_orphaned_assets() : array( 'renamed' => 0, 'deleted' => 0 );
	}
}
