<?php
/**
 * Align Theme Core Engine - Builder Content & Pages Query Handler
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Builder_Content_Handler {

	/** Strip UTF-8 Byte Order Mark (BOM) from string. */
	public static function strip_bom( ?string $string ): string {
		if ( ! empty( $string ) && str_starts_with( $string, "\xEF\xBB\xBF" ) ) {
			error_log( '[Align ContentHandler] UTF-8 BOM detected and stripped from content.' );
			return substr( $string, 3 );
		}
		return (string) $string;
	}

	/**
	 * Get list of WordPress Pages, FSE Templates, and Template Parts.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function get_pages( $request ) {
		$nonce     = wp_create_nonce( 'wp_rest' );
		$formatted = array();
		$lang_filter   = sanitize_key( $request->get_param( 'lang' ) ?: '' );
		$default_lang  = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en';
		$query_args    = array( 'post_type' => 'page', 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' );
		$meta_q        = array();
		if ( $lang_filter ) {
			$meta_q = ( $lang_filter === $default_lang )
				? array( array( 'key' => '_align_lang_root', 'compare' => 'NOT EXISTS' ), array( 'relation' => 'OR', array( 'key' => '_align_lang', 'value' => $lang_filter ), array( 'key' => '_align_lang', 'compare' => 'NOT EXISTS' ) ) )
				: array( array( 'key' => '_align_lang', 'value' => $lang_filter ) );
			error_log( '[Align ContentHandler] Pages filtered by lang: ' . $lang_filter );
		}
		$query_args['meta_query'] = $meta_q;

		$wp_pages = get_posts( $query_args );

		foreach ( $wp_pages as $p ) {
			$p_lang       = get_post_meta( $p->ID, '_align_lang', true ) ?: '';
			$p_lang_group = get_post_meta( $p->ID, '_align_lang_group', true ) ?: '';
			$formatted[]  = array(
				'id' => $p->ID, 'name' => 'page_' . $p->ID, 'title' => '📄 ' . ( $p->post_title ?: __( '(No Title)', 'align' ) ),
				'slug' => $p->post_name, 'type' => 'Pages', 'template_type' => 'page', 'folder' => 'Pages', 'status' => $p->post_status,
				'url' => get_permalink( $p->ID ), 'editor_url' => admin_url( 'admin.php?page=' . ALIGN_BUILDER_SLUG . '&post_id=' . $p->ID ),
				'file' => add_query_arg( '_wpnonce', $nonce, rest_url( ALIGN_BUILDER_REST_NS . '/page-content?id=' . $p->ID ) ),
				'lang' => $p_lang, 'lang_group' => $p_lang_group,
			);
		}

		if ( function_exists( 'get_block_templates' ) ) {
			$system_slugs  = array( 'index', 'page', 'single', 'archive', 'search', '404', 'home', 'front-page', 'category', 'tag', 'author', 'date', 'taxonomy', 'attachment', 'singular', 'privacy-policy' );
			$custom_titles = array( 'home' => __( 'Blog Archive', 'align' ), 'single' => __( 'Single Posts', 'align' ), 'page' => __( 'Pages', 'align' ), 'single-fluent-products' => __( 'Single Product', 'align' ), 'archive-fluent-products' => __( 'Shop Archive', 'align' ), 'archive' => __( 'All Archives', 'align' ), 'taxonomy-product-categories' => __( 'Products by Category', 'align' ), 'search' => __( 'Search Results', 'align' ), '404' => __( 'Page: 404', 'align' ), 'index' => __( 'Index (Fallback)', 'align' ) );
			$raw_tpls = get_block_templates( array(), 'wp_template' );
			$tpl_map  = array();
			foreach ( $raw_tpls as $t ) {
				$t_lang = ! empty( $t->wp_id ) ? ( get_post_meta( $t->wp_id, '_align_lang', true ) ?: '' ) : '';
				if ( ! $t_lang && preg_match( '/-([a-z]{2}(?:-[a-z]{2})?)$/', $t->slug, $lm ) ) { $t_lang = $lm[1]; }
				$t_lang = $t_lang ?: $default_lang;
				$base   = preg_replace( '/-[a-z]{2}(?:-[a-z]{2})?$/', '', $t->slug );
				if ( ! isset( $tpl_map[ $base ] ) ) { $tpl_map[ $base ] = array( 'base' => null, 'localized' => null ); }
				if ( $lang_filter && $t_lang === $lang_filter ) { $tpl_map[ $base ]['localized'] = array( 't' => $t, 'lang' => $t_lang ); }
				if ( $t_lang === $default_lang || $t->slug === $base ) { $tpl_map[ $base ]['base'] = array( 't' => $t, 'lang' => $t_lang ); }
			}
			foreach ( $tpl_map as $base => $info ) {
				$chosen = ( $lang_filter && ! empty( $info['localized'] ) ) ? $info['localized'] : ( ! empty( $info['base'] ) ? $info['base'] : null );
				if ( ! $chosen ) { continue; }
				$t = $chosen['t'];
				$t_lang = $chosen['lang'];
				$title  = isset( $custom_titles[ $t->slug ] ) ? $custom_titles[ $t->slug ] : ( ! empty( $t->title ) ? $t->title : ucfirst( $t->slug ) );
				if ( $lang_filter && ! empty( $info['localized'] ) && ! str_contains( $title, '[' ) ) { $title .= ' [' . strtoupper( $lang_filter ) . ']'; }
				$tpl_id = 'tpl_' . $t->id;
				$is_custom = ! empty( $t->is_custom ) || ( isset( $t->source ) && 'custom' === $t->source && ! in_array( $t->slug, $system_slugs, true ) );
				$formatted[] = array(
					'id' => $tpl_id, 'name' => 'tpl_' . $t->slug, 'title' => '🎨 ' . $title, 'slug' => $t->slug, 'type' => 'Templates', 'template_type' => 'template', 'folder' => 'Templates', 'status' => 'publish',
					'is_system' => ! $is_custom, 'is_custom' => $is_custom, 'tag_label' => ( $is_custom ? 'User' : 'System' ), 'tag_type' => ( $is_custom ? 'user' : 'system' ),
					'url' => admin_url( 'site-editor.php?pId=' . $t->id ), 'editor_url' => admin_url( 'admin.php?page=' . ALIGN_BUILDER_SLUG . '&tp_id=' . urlencode( $tpl_id ) . ( $lang_filter ? '&lang=' . $lang_filter : '' ) ),
					'file' => add_query_arg( array( '_wpnonce' => $nonce, 'lang' => ( $lang_filter ?: $t_lang ) ), rest_url( ALIGN_BUILDER_REST_NS . '/page-content?tp_id=' . urlencode( $tpl_id ) ) ),
					'lang' => $lang_filter ?: $t_lang,
				);
			}

			foreach ( get_block_templates( array(), 'wp_template_part' ) as $tp ) {
				$tp_lang = ! empty( $tp->wp_id ) ? ( get_post_meta( $tp->wp_id, '_align_lang', true ) ?: '' ) : '';
				if ( ! $tp_lang && preg_match( '/-([a-z]{2}(?:-[a-z]{2})?)$/', $tp->slug, $lm ) ) { $tp_lang = $lm[1]; }
				$tp_lang = $tp_lang ?: $default_lang;
				if ( $lang_filter && $tp_lang !== $lang_filter ) { continue; }
				$title       = ! empty( $tp->title ) ? $tp->title : ucfirst( $tp->slug );
				$area_icon   = ( $tp->area === 'header' ) ? '📌 Header: ' : ( ( $tp->area === 'footer' ) ? '🦶 Footer: ' : '🧩 Part: ' );
				$tp_id       = 'tp_' . $tp->id;
				$is_custom   = ! in_array( $tp->slug, array( 'header', 'footer', 'sidebar', 'comments' ), true ) && ( empty( $tp->source ) || 'theme' !== $tp->source );
				$formatted[] = array(
					'id' => $tp_id, 'name' => 'tp_' . $tp->slug, 'title' => $area_icon . $title, 'slug' => $tp->slug, 'type' => 'Template Parts', 'template_type' => 'template_part', 'folder' => 'Template Parts', 'status' => 'publish',
					'is_system' => ! $is_custom, 'is_custom' => $is_custom, 'tag_label' => ( $is_custom ? 'User' : 'System' ), 'tag_type' => ( $is_custom ? 'user' : 'system' ),
					'url' => admin_url( 'site-editor.php?pId=' . $tp->id ), 'editor_url' => admin_url( 'admin.php?page=' . ALIGN_BUILDER_SLUG . '&tp_id=' . urlencode( $tp_id ) ),
					'file' => add_query_arg( '_wpnonce', $nonce, rest_url( ALIGN_BUILDER_REST_NS . '/page-content?tp_id=' . urlencode( $tp_id ) ) ),
					'lang' => $tp_lang,
				);
			}
		}
		$wp_blocks = get_posts( array( 'post_type' => 'wp_block', 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		foreach ( $wp_blocks as $b ) {
			$formatted[] = array(
				'id' => $b->ID, 'name' => 'block_' . $b->ID, 'title' => '🧱 ' . ( $b->post_title ?: __( '(No Title)', 'align' ) ),
				'slug' => $b->post_name, 'shortcode' => '[reuse-block id="' . $b->ID . '"]', 'type' => 'Reusable Blocks', 'template_type' => 'reusable_block', 'folder' => 'Reusable Blocks', 'status' => $b->post_status,
				'is_reusable' => true, 'is_dynamic' => (bool) get_post_meta( $b->ID, '_align_is_dynamic_block', true ), 'tag_label' => 'id: ' . $b->ID, 'tag_type' => 'id',
				'url' => get_permalink( $b->ID ), 'editor_url' => admin_url( 'admin.php?page=' . ALIGN_BUILDER_SLUG . '&post_id=' . $b->ID ), 'studio_url' => admin_url( 'admin.php?page=align-studio&post_id=' . $b->ID ),
				'file' => add_query_arg( '_wpnonce', $nonce, rest_url( ALIGN_BUILDER_REST_NS . '/page-content?id=' . $b->ID ) ),
			);
		}

		return rest_ensure_response( $formatted );
	}

	/**
	 * Get page content for live canvas editing with loaded CSS, JS, and font preloads.
	 *
	 * @param WP_REST_Request $request REST request object.
	 */
	public static function get_page_content( $request ) {
		$post_id           = intval( $request->get_param( 'id' ) );
		$tp_id             = $request->get_param( 'tp_id' );
		$template_type     = $request->get_param( 'template_type' );
		$content_to_render = '';
		$page_title        = 'Page Content';
		$is_template       = false;

		if ( $tp_id ) {
			$raw_id          = $tp_id;
			$is_explicit_tpl = ( 0 === strpos( $raw_id, 'tpl_' ) );
			$is_explicit_tp  = ( 0 === strpos( $raw_id, 'tp_' ) );

			if ( $is_explicit_tpl || $is_explicit_tp ) {
				$raw_id = preg_replace( '/^t(p|pl)_/', '', $raw_id );
			}

			$slug             = ( strpos( $raw_id, '//' ) !== false ) ? explode( '//', $raw_id )[1] : $raw_id;
			$slug             = sanitize_file_name( $slug );
			$child_slug_dash  = sanitize_title( get_stylesheet() . '-' . $slug );
			$parent_slug_dash = sanitize_title( get_template() . '-' . $slug );
			$full_name_child  = get_stylesheet() . '//' . $slug;
			$full_name_parent = get_template() . '//' . $slug;
			$search_slugs     = array_values( array_unique( array_filter( array(
				$slug, $full_name_child, $full_name_parent, $child_slug_dash, $parent_slug_dash, $raw_id, sanitize_title( $raw_id ),
			) ) ) );

			if ( 'template' === $template_type || $is_explicit_tpl ) {
				$is_template = true;
			} elseif ( 'template_part' === $template_type || $is_explicit_tp ) {
				$is_template = false;
			} else {
				$is_template = ( file_exists( get_stylesheet_directory() . '/templates/' . $slug . '.html' ) || file_exists( get_template_directory() . '/templates/' . $slug . '.html' ) );
				if ( ! $is_template && ! ( file_exists( get_stylesheet_directory() . '/parts/' . $slug . '.html' ) || file_exists( get_template_directory() . '/parts/' . $slug . '.html' ) || file_exists( get_stylesheet_directory() . '/templates/parts/' . $slug . '.html' ) || file_exists( get_template_directory() . '/templates/parts/' . $slug . '.html' ) ) ) {
					$is_template = ! empty( get_posts( array( 'post_type' => 'wp_template', 'post_name__in' => $search_slugs, 'posts_per_page' => 1 ) ) );
				}
			}

			$type           = $is_template ? 'wp_template' : 'wp_template_part';
			$sub_dir        = $is_template ? 'templates' : 'parts';
			$page_title     = ucfirst( $slug ) . ( $is_template ? ' Template' : ' Part' );
			$raw_tp_content = '';

			// 1. Search database post strictly matching target post type
			$found_posts = get_posts( array( 'post_type' => $type, 'post_status' => 'any', 'posts_per_page' => 1, 'post_name__in' => $search_slugs, 'tax_query' => array( array( 'taxonomy' => 'wp_theme', 'field' => 'slug', 'terms' => array( get_stylesheet(), get_template() ) ) ) ) );
			if ( empty( $found_posts ) ) { $found_posts = get_posts( array( 'post_type' => $type, 'post_status' => 'any', 'posts_per_page' => 1, 'post_name__in' => $search_slugs ) ); }
			if ( ! empty( $found_posts ) && ! empty( $found_posts[0]->post_content ) ) { $raw_tp_content = self::strip_bom( $found_posts[0]->post_content ); }

			// 2. Search block template object
			if ( empty( $raw_tp_content ) && function_exists( 'get_block_template_object' ) ) {
				$tp_obj = get_block_template_object( $raw_id, $type ) ?: get_block_template_object( $full_name_child, $type ) ?: get_block_template_object( $full_name_parent, $type ) ?: get_block_template_object( $slug, $type );
				if ( $tp_obj && ! empty( $tp_obj->content ) ) {
					$raw_tp_content = self::strip_bom( $tp_obj->content );
					if ( ! empty( $tp_obj->title ) ) { $page_title = $tp_obj->title; }
				}
			}

			// 3. Fallback strictly to matching directory on physical disk
			if ( empty( $raw_tp_content ) ) {
				$file_paths = array( get_stylesheet_directory() . '/' . $sub_dir . '/' . $slug . '.html', get_template_directory() . '/' . $sub_dir . '/' . $slug . '.html' );
				if ( ! $is_template ) {
					$file_paths[] = get_stylesheet_directory() . '/templates/parts/' . $slug . '.html';
					$file_paths[] = get_template_directory() . '/templates/parts/' . $slug . '.html';
				}
				foreach ( $file_paths as $fp ) {
					if ( file_exists( $fp ) ) {
						$raw_tp_content = self::strip_bom( (string) file_get_contents( $fp ) );
						break;
					}
				}
			}

			if ( ! empty( $raw_tp_content ) ) {
				$raw_tp_br_count = preg_match_all( '/<br\b[^>]*>/i', $raw_tp_content, $raw_tp_br_m );
				if ( $is_template ) {
					$raw_tp_content = preg_replace( '/<!--\s*wp:template-part\b.*?(?:\/-->|-->[\s\S]*?<!--\s*\/wp:template-part\s*-->)/is', '', $raw_tp_content );
					$raw_tp_content = preg_replace( '/<!--\s*wp:post-content\b.*?(?:\/-->|-->[\s\S]*?<!--\s*\/wp:post-content\s*-->)/is', '<div data-component-post-content>post-content</div>', $raw_tp_content );
				}
				if ( preg_match( '/<!-- wp:align\/custom-html[^>]*>(.*?)<!-- \/wp:align\/custom-html -->/s', $raw_tp_content, $matches ) || preg_match( '/<!-- wp:html -->(.*?)<!-- \/wp:html -->/s', $raw_tp_content, $matches ) ) {
					$content_to_render = trim( $matches[1] );
					if ( $is_template && strpos( $raw_tp_content, 'data-component-post-content' ) !== false && strpos( $content_to_render, 'data-component-post-content' ) === false ) {
						$content_to_render .= "\n<div data-component-post-content>post-content</div>";
					}
				} elseif ( function_exists( 'do_blocks' ) ) {
					$content_to_render = do_blocks( $raw_tp_content );
				} else {
					$content_to_render = $raw_tp_content;
				}
			}

			// For FSE templates, ensure post content placeholder is present if template body is empty
			if ( $is_template && empty( trim( $content_to_render ) ) ) {
				$content_to_render = '<div data-component-post-content>post-content</div>';
			}
		} else if ( $post_id ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$page_title        = $post->post_title;
				$raw_content       = self::strip_bom( $post->post_content );
				$raw_post_br_count = preg_match_all( '/<br\b[^>]*>/i', $raw_content, $raw_post_br_m );
				if ( preg_match( '/<!-- wp:align\/custom-html[^>]*>(.*?)<!-- \/wp:align\/custom-html -->/s', $raw_content, $matches ) || preg_match( '/<!-- wp:html -->(.*?)<!-- \/wp:html -->/s', $raw_content, $matches ) ) {
					$content_to_render = trim( $matches[1] );
				} else {
					$content_to_render = $raw_content;
				}
			}
		}

		$content_to_render = empty( trim( $content_to_render ) ) ? '' : self::strip_bom( $content_to_render );

		$raw_entity_br_count = ! empty( $tp_id ) ? ( isset( $raw_tp_br_count ) ? $raw_tp_br_count : 0 ) : ( isset( $raw_post_br_count ) ? $raw_post_br_count : 0 );

		// Determine scoped style and script IDs for target entity
		$style_id          = 'style-page-' . $post_id;
		$script_id         = 'script-page-' . $post_id;
		$legacy_style_ids  = array();
		$legacy_script_ids = array();

		if ( ! empty( $tp_id ) ) {
			$raw_id            = preg_replace( '/^t(p|pl)_/', '', $tp_id );
			$slug              = sanitize_file_name( strpos( $raw_id, '//' ) !== false ? explode( '//', $raw_id )[1] : $raw_id );
			$prefix            = $is_template ? 'template' : 'part';
			$style_id          = 'style-' . $prefix . '-' . $slug;
			$script_id         = 'script-' . $prefix . '-' . $slug;
			$legacy_style_ids  = array( 'align-style-' . $prefix . '-' . $slug );
			$legacy_script_ids = array( 'align-script-' . $prefix . '-' . $slug );
		} elseif ( $post_id > 0 ) {
			$target_post       = get_post( $post_id );
			$entity_slug       = ( $target_post && ! empty( $target_post->post_name ) ) ? $target_post->post_name : (string) $post_id;
			$is_block          = ( get_post_type( $post_id ) === 'wp_block' );
			$prefix            = $is_block ? 'block' : 'page';
			$style_id          = 'style-' . $prefix . '-' . $entity_slug;
			$script_id         = 'script-' . $prefix . '-' . $entity_slug;
			$legacy_style_ids  = array( 'align-style-' . $prefix . '-' . $entity_slug, 'align-style-' . $prefix . '-' . $post_id, 'style-' . $prefix . '-' . $post_id );
			$legacy_script_ids = array( 'align-script-' . $prefix . '-' . $entity_slug, 'align-script-' . $prefix . '-' . $post_id, 'script-' . $prefix . '-' . $post_id );
		}

		// 1. Extract target-scoped CSS (<style id="...">) BEFORE any DOM parsing/manipulation
		$extracted_page_css = '';
		$exact_css_regex    = '/<style[^>]*\bid\s*=\s*(["\']?)' . preg_quote( $style_id, '/' ) . '\1[^>]*>([\s\S]*?)<\/style>/is';
		$fallback_css_regex = '/<style[^>]*\bid\s*=\s*(["\']?)(?:(?:align-)?style-[^"\'\s>]+|vvvebjs-styles)\1[^>]*>([\s\S]*?)<\/style>/is';

		if ( preg_match( $exact_css_regex, $content_to_render, $css_matches ) ) {
			$extracted_page_css = $css_matches[2];
			$content_to_render  = preg_replace( $exact_css_regex, '', $content_to_render, 1 );
		} elseif ( preg_match( $fallback_css_regex, $content_to_render, $css_matches ) ) {
			$extracted_page_css = $css_matches[2];
			$content_to_render  = preg_replace( $fallback_css_regex, '', $content_to_render, 1 );
		} elseif ( preg_match_all( '/<style\b(?![^>]*\b(?:id="wp-global-styles"|data-align-preview))[^>]*>([\s\S]*?)<\/style>/is', $content_to_render, $css_matches ) ) {
			$extracted_page_css = trim( implode( "\n\n", array_filter( array_map( 'trim', $css_matches[1] ) ) ) );
			$content_to_render  = preg_replace( '/<style\b(?![^>]*\b(?:id="wp-global-styles"|data-align-preview))[^>]*>[\s\S]*?<\/style>/is', '', $content_to_render );
		}

		// 2. Extract target-scoped JS (<script id="...">) BEFORE any DOM parsing/manipulation
		$extracted_page_js = '';
		$exact_js_regex    = '/<script[^>]*\bid\s*=\s*(["\']?)' . preg_quote( $script_id, '/' ) . '\1[^>]*>([\s\S]*?)<\/script>/is';
		$fallback_js_regex = '/<script[^>]*\bid\s*=\s*(["\']?)(?:(?:align-)?script-[^"\'\s>]+|vvvebjs-scripts)\1[^>]*>([\s\S]*?)<\/script>/is';

		if ( preg_match( $exact_js_regex, $content_to_render, $js_matches ) ) {
			$extracted_page_js = $js_matches[2];
			$content_to_render = preg_replace( $exact_js_regex, '', $content_to_render, 1 );
		} elseif ( preg_match( $fallback_js_regex, $content_to_render, $js_matches ) ) {
			$extracted_page_js = $js_matches[2];
			$content_to_render = preg_replace( $fallback_js_regex, '', $content_to_render, 1 );
		} elseif ( preg_match_all( '/<script\b(?![^>]*\bdata-align-preview)[^>]*>([\s\S]*?)<\/script>/is', $content_to_render, $js_matches ) ) {
			$extracted_page_js = trim( implode( "\n\n", array_filter( array_map( 'trim', $js_matches[1] ) ) ) );
			$content_to_render = preg_replace( '/<script\b(?![^>]*\bdata-align-preview)[^>]*>[\s\S]*?<\/script>/is', '', $content_to_render );
		}
		$content_to_render = preg_replace( '/"?IntersectionObserver"?\s*in\s*window(?:&&|&amp;&amp;)!window\.matchMedia\([^)]+\)\.matches(?:&&|&amp;&amp;)document\.documentElement\.classList\.add\([^)]+\);?/i', '', $content_to_render );

		if ( class_exists( 'Align_Builder_Assets' ) ) {
			$extracted_page_css = Align_Builder_Assets::load_external_css( $style_id, $extracted_page_css, $legacy_style_ids );
			$extracted_page_js  = Align_Builder_Assets::load_external_js( $script_id, $extracted_page_js, $legacy_script_ids );

			// Scan for embedded reusable blocks and load their scoped CSS/JS into canvas
			if ( preg_match_all( '/\[(?:reuse[-_]block|align[-_]block)\b[^\]]*\b(?:id|slug)=(["\'])(.*?)\1/is', $content_to_render, $rb_matches ) ) {
				$embedded_rb_ids = array_unique( $rb_matches[2] );
				foreach ( $embedded_rb_ids as $rb_item ) {
					$rb_post = is_numeric( $rb_item ) ? get_post( (int) $rb_item ) : get_page_by_path( $rb_item, OBJECT, 'wp_block' );
					if ( $rb_post && ! empty( $rb_post->post_name ) ) {
						$rb_style_id = 'style-block-' . $rb_post->post_name;
						$rb_css      = Align_Builder_Assets::load_external_css( $rb_style_id );
						if ( ! empty( $rb_css ) && false === strpos( $extracted_page_css, $rb_style_id ) ) {
							$extracted_page_css .= "\n/* Block: " . esc_attr( $rb_post->post_name ) . " */\n" . $rb_css;
							error_log( sprintf( '[Align ContentHandler] Enqueued embedded reusable block CSS: %s (%d bytes)', $rb_post->post_name, strlen( $rb_css ) ) );
						}
						$rb_script_id = 'script-block-' . $rb_post->post_name;
						$rb_js        = Align_Builder_Assets::load_external_js( $rb_script_id );
						if ( ! empty( $rb_js ) && false === strpos( $extracted_page_js, $rb_script_id ) ) {
							$extracted_page_js .= "\n/* Block: " . esc_attr( $rb_post->post_name ) . " */\n" . $rb_js;
						}
					}
				}
			}
		}

		// Resolve dynamic shortcode image URLs for builder canvas preview
		if ( false !== strpos( $content_to_render, '[align_post_hero_image' ) ) {
			$preview_pid = class_exists( 'Align_Single_Post_Init' ) ? Align_Single_Post_Init::resolve_post_id() : 0;
			$content_to_render = preg_replace_callback( '/\[align_post_hero_image(?:\s+size=[\'"]([^\'"]+)[\'"])?[^\]]*\]/i', function( $m ) use ( $preview_pid ) {
				$size = ! empty( $m[1] ) ? $m[1] : 'garage_blog_featured_desktop';
				$url  = class_exists( 'Align_Single_Post_Init' ) ? Align_Single_Post_Init::get_hero_image_url( $preview_pid, $size ) : '';
				return ! empty( $url ) ? esc_url( $url ) : '/wp-content/uploads/2026/08/home-3-1200x460.webp';
			}, $content_to_render );
		}

		// Replace dummy image URLs with local WebP builder images
		$content_to_render = Align_Builder_Assets::replace_dummy_images( $content_to_render );

		// Resolve dynamic locked links for builder canvas preview
		$content_to_render = self::resolve_dynamic_links( $content_to_render );

		// Resolve hybrid dynamic image srcset & picture art-direction for builder canvas preview
		if ( class_exists( 'Align_Builder_Image_Resolver' ) ) {
			$content_to_render = Align_Builder_Image_Resolver::resolve_dynamic_image_srcset( $content_to_render );
		}

		// Render live component previews for builder canvas (only processes body HTML now)
		$content_to_render = self::render_builder_components_for_preview( $content_to_render );

		$theme_css_urls       = Align_Builder_Assets::get_builder_css_urls();
		$header_js_assets     = Align_Builder_Assets::get_builder_js_assets( 'header' );
		$footer_js_assets     = Align_Builder_Assets::get_builder_js_assets( 'footer' );
		$font_preloads        = Align_Builder_Assets::get_font_preloads();
		$custom_css           = get_option( 'align_custom_css', '' );
		$custom_js            = get_option( 'align_custom_js', '' );
		$tracking_code        = get_option( 'align_tracking_code', '' );
		$canvas_body_br_count = preg_match_all( '/<br\b[^>]*>/i', $content_to_render, $canvas_br_m );

		error_log( sprintf( '[Align ContentHandler] Canvas Output | post_id: %d, tp_id: %s (style_id: %s, script_id: %s) | Title: "%s" | Body Len: %d | Raw BR: %d -> Canvas BR: %d | CSS Len: %d | JS Len: %d', $post_id, $tp_id ?: 'none', $style_id, $script_id, $page_title, strlen( $content_to_render ), $raw_entity_br_count, $canvas_body_br_count, strlen( $extracted_page_css ), strlen( $extracted_page_js ) ) );

		if ( function_exists( 'show_admin_bar' ) ) { show_admin_bar( false ); }
		if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
		if ( function_exists( 'nocache_headers' ) ) nocache_headers();
		if ( ! headers_sent() ) {
			header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' ); header( 'Pragma: no-cache' );
			header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' ); header( 'Content-Type: text/html; charset=UTF-8' );
		}

		echo '<!DOCTYPE html><html translate="no" class="notranslate"><head><meta charset="utf-8"><meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate"><meta http-equiv="Pragma" content="no-cache"><meta http-equiv="Expires" content="0"><title>' . esc_html( $page_title ) . '</title><style>#wpadminbar { display: none !important; } html, body { margin-top: 0 !important; padding-top: 0 !important; scroll-behavior: auto !important; }</style>';
		foreach ( $font_preloads as $preload_tag ) { echo $preload_tag . "\n"; }
		foreach ( $theme_css_urls as $css_url ) { echo '<link rel="stylesheet" href="' . esc_url( add_query_arg( 'v', time(), $css_url ) ) . '" data-align-preview="1">' . "\n"; }
		$helpers_css_url = add_query_arg( 'v', time(), ( defined( 'ALIGN_BUILDER_URL' ) ? ALIGN_BUILDER_URL : get_template_directory_uri() . '/core/builder/' ) . 'css/vvvebjs-editor-helpers.css' );
		echo '<link rel="stylesheet" href="' . esc_url( $helpers_css_url ) . '" data-vvveb-helpers="1" data-align-preview="1">' . "\n";
		echo '<style id="align-editor-hide-helpers" data-align-preview="1">[data-align-hide="true"]{opacity:0.45!important;filter:blur(1.2px) grayscale(50%)!important;outline:1.5px dashed #f59e0b!important;outline-offset:-1px;position:relative;transition:opacity .2s ease,filter .2s ease}[data-align-hide="true"]:hover{opacity:0.75!important;filter:blur(0.5px) grayscale(20%)!important}[data-align-hide="true"]::before{content:"Hidden (No FE)";position:absolute;top:4px;left:4px;background:rgba(245,158,11,0.9);color:#fff;font-size:10px;font-weight:600;padding:1px 5px;border-radius:3px;pointer-events:none;z-index:9999;line-height:1.3}</style>' . "\n";
		foreach ( $header_js_assets as $js ) {
			$url  = is_array( $js ) ? $js['url'] : $js->url;
			$attr = ( 'defer' === ( is_array( $js ) ? $js['loading'] : $js->loading ) ) ? ' defer' : ( ( 'async' === ( is_array( $js ) ? $js['loading'] : $js->loading ) ) ? ' async' : '' );
			echo '<script src="' . esc_url( add_query_arg( 'v', time(), $url ) ) . '"' . $attr . ' data-align-preview="1"></script>' . "\n";
		}
		if ( is_string( $custom_css ) && '' !== trim( $custom_css ) ) { echo '<style id="align-global-custom-css" data-align-preview="1">' . trim( $custom_css ) . '</style>' . "\n"; }
		if ( '' !== trim( $extracted_page_css ) ) { echo '<style id="' . esc_attr( $style_id ) . '" type="text/css">' . $extracted_page_css . '</style>' . "\n"; }
		if ( '' !== trim( $extracted_page_js ) ) { echo '<script id="' . esc_attr( $script_id ) . '" type="text/javascript">' . $extracted_page_js . '</script>' . "\n"; }
		if ( is_string( $tracking_code ) && '' !== trim( $tracking_code ) ) { echo preg_replace( '/<(script|style)(?![^>]*data-align-preview)/i', '<$1 data-align-preview="1"', trim( $tracking_code ) ) . "\n"; }

		echo '</head><body data-gramm="false" data-enable-grammarly="false" spellcheck="false">' . trim( $content_to_render );
		echo '<script id="align-pre-animation-snapshot" data-align-preview="1">(function(){try{window.__ALIGN_DOM_SNAPSHOT__=new WeakMap();window.__alignRefreshDomSnapshot=function(root){try{window.__ALIGN_DOM_SNAPSHOT__=new WeakMap();function snap(n){if(!n||n.nodeType!==1)return;if(n.id==="vvvebjs-new-section"||n.id==="highlight-box"||n.id==="select-box"||n.hasAttribute("data-vvveb-helpers")||(n.classList&&n.classList.contains("vvveb-helper")))return;var s={};if(n.style&&n.style.length){for(var i=0;i<n.style.length;i++){var p=n.style[i];s[p]=n.style.getPropertyValue(p);}}window.__ALIGN_DOM_SNAPSHOT__.set(n,{c:n.className||"",s:s});for(var c=n.firstElementChild;c;c=c.nextElementSibling){snap(c);}}snap(root||document.body);}catch(e){console.warn("[Align] Snapshot err:",e);}};window.__alignRefreshDomSnapshot(document.body);}catch(e){console.warn("[Align] Snapshot init err:",e);}})();</script>' . "\n";

		foreach ( $footer_js_assets as $js ) {
			$url  = is_array( $js ) ? $js['url'] : $js->url;
			$attr = ( 'defer' === ( is_array( $js ) ? $js['loading'] : $js->loading ) ) ? ' defer' : ( ( 'async' === ( is_array( $js ) ? $js['loading'] : $js->loading ) ) ? ' async' : '' );
			echo '<script src="' . esc_url( add_query_arg( 'v', time(), $url ) ) . '"' . $attr . ' data-align-preview="1"></script>' . "\n";
		}
		if ( is_string( $custom_js ) && '' !== trim( $custom_js ) ) { echo '<script id="align-global-custom-js" data-align-preview="1">' . trim( $custom_js ) . '</script>' . "\n"; }
		echo '</body></html>';
		exit;
	}

	/**
	 * Render live preview content for shortcodes, Gutenberg blocks, and post content on builder canvas.
	 *
	 * @param string $content Raw HTML content.
	 * @return string Processed HTML content with rendered previews.
	 */
	public static function render_builder_components_for_preview( $content ) {
		if ( empty( $content ) || ! is_string( $content ) || ( strpos( $content, 'data-component-shortcode' ) === false && strpos( $content, 'data-component-wp-block' ) === false && strpos( $content, 'data-component-post-content' ) === false ) ) {
			return $content;
		}

		$input_br_count = preg_match_all( '/<br\b[^>]*>/i', $content, $in_br_m );
		if ( class_exists( 'DOMDocument' ) && class_exists( 'DOMXPath' ) ) {
			try {
				$dom      = new DOMDocument();
				$prev_err = libxml_use_internal_errors( true );
				$loaded   = $dom->loadHTML( '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>' . $content . '</body></html>' );
				libxml_clear_errors();
				libxml_use_internal_errors( $prev_err );

				if ( $loaded ) {
					$xpath = new DOMXPath( $dom );
					$nodes = $xpath->query( '//*[@data-component-shortcode or @data-component-wp-block or @data-component-post-content]' );
					if ( $nodes ) {
						foreach ( iterator_to_array( $nodes ) as $node ) {
							while ( $node->hasChildNodes() ) { $node->removeChild( $node->firstChild ); }
							if ( $node->hasAttribute( 'data-component-shortcode' ) ) {
								$shortcode_raw   = trim( $node->getAttribute( 'data-shortcode' ) );
								$node->nodeValue = ! empty( $shortcode_raw ) ? $shortcode_raw : '[gallery]';
							} elseif ( $node->hasAttribute( 'data-component-post-content' ) ) {
								$node->nodeValue = 'post-content';
							} else {
								$block_raw       = trim( $node->getAttribute( 'data-wp-block' ) );
								$block_name      = preg_match( '/<!--\s*wp:([\w\/\-]+)/', $block_raw, $bm ) ? $bm[1] : ( ! empty( $block_raw ) ? 'custom' : '' );
								$node->nodeValue = ! empty( $block_name ) ? $block_name : 'paragraph';
							}
						}
					}

					$body = $dom->getElementsByTagName( 'body' )->item( 0 );
					if ( $body ) {
						$result = '';
						foreach ( $body->childNodes as $child ) { $result .= $dom->saveHTML( $child ); }
						$clean_result    = str_replace( '</source>', '', trim( $result ) );
						$output_br_count = preg_match_all( '/<br\b[^>]*>/i', $clean_result, $out_br_m );
						if ( $input_br_count !== $output_br_count || $output_br_count > 0 ) {
							error_log( sprintf( '[Align ContentHandler] render_builder_components_for_preview | BR tags: %d (in) -> %d (out)', $input_br_count, $output_br_count ) );
						}
						return $clean_result;
					}
				}
			} catch ( Exception $e ) {
				error_log( '[Align ContentHandler] render_builder_components_for_preview exception: ' . $e->getMessage() );
			}
		}
		return $content;
	}

	/**
	 * Search published posts, pages, and public custom post types for Link Picker.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function search_links( $request ) {
		$search = $request->get_param( 'search' );
		$search = is_string( $search ) ? trim( sanitize_text_field( wp_unslash( $search ) ) ) : '';
		if ( mb_strlen( $search ) > 100 ) { $search = mb_substr( $search, 0, 100 ); }
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $post_types['attachment'] );
		$query_args = array( 'post_type' => array_values( $post_types ), 'post_status' => array( 'publish' ), 'posts_per_page' => 20, 'no_found_rows' => true, 'update_post_meta_cache' => false, 'update_post_term_cache' => false, 'orderby' => ! empty( $search ) ? 'relevance date' : 'date', 'order' => 'DESC' );
		if ( ! empty( $search ) ) { $query_args['s'] = $search; }
		$query = new WP_Query( $query_args );
		$results = array();
		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$pto = get_post_type_object( $post->post_type );
				$results[] = array( 'id' => $post->ID, 'title' => ! empty( $post->post_title ) ? $post->post_title : __( '(No Title)', 'align' ), 'url' => esc_url( wp_make_link_relative( get_permalink( $post->ID ) ) ?: '/' ), 'post_type' => $post->post_type, 'type' => $post->post_type, 'type_label' => $pto ? $pto->labels->singular_name : ucfirst( $post->post_type ) );
			}
		}
		return rest_ensure_response( $results );
	}

	public static function resolve_dynamic_links( $content ) {
		if ( empty( $content ) || ! is_string( $content ) || strpos( $content, 'data-align-link-id' ) === false ) return $content;
		return preg_replace_callback( '/<a\b([^>]*\bdata-align-link-id\s*=\s*[\'"]?(\d+)[\'"]?[^>]*)>/is', function( $matches ) {
			$post_id = intval( $matches[2] );
			if ( $post_id <= 0 ) return $matches[0];
			$post = get_post( $post_id );
			if ( ! $post || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) return $matches[0];
			$permalink = get_permalink( $post_id );
			if ( ! $permalink || is_wp_error( $permalink ) ) return $matches[0];
			$escaped_url = esc_url( wp_make_link_relative( $permalink ) ?: '/' );
			$attrs = preg_match( '/\bhref\s*=\s*(?:([\'"])(.*?)\1|([^\s>]+))/is', $matches[1] ) ? preg_replace( '/\bhref\s*=\s*(?:([\'"])(.*?)\1|([^\s>]+))/is', 'href="' . $escaped_url . '"', $matches[1] ) : ( $matches[1] . ' href="' . $escaped_url . '"' );
			return '<a' . $attrs . '>';
		}, $content );
	}
}
