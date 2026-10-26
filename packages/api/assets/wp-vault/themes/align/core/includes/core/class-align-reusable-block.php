<?php
/**
 * Class Align_Reusable_Block
 * Handles WordPress Reusable Blocks (`wp_block`) shortcodes, overrides, instance keys, and REST API.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Reusable_Block {

	private static $active_template_part_stack = array();

	public static function init() {
		foreach ( array( 'reuse-block', 'reuse_block', 'align_block', 'align_reusable_block', 'reusable_block' ) as $tag ) add_shortcode( $tag, array( __CLASS__, 'render_shortcode' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_filter( 'pre_render_block', array( __CLASS__, 'on_pre_render_block' ), 10, 2 ); add_filter( 'render_block', array( __CLASS__, 'on_render_block' ), 10, 2 );
	}

	public static function on_pre_render_block( $pre_render, $parsed_block ) {
		if ( ! empty( $parsed_block['blockName'] ) && 'core/template-part' === $parsed_block['blockName'] ) {
			$slug = $parsed_block['attrs']['slug'] ?? '';
			self::$active_template_part_stack[] = array( 'slug' => $slug, 'id' => ( $slug ? self::resolve_template_post_id( $slug, 'template_part' ) : 0 ) );
		}
		return $pre_render;
	}
	public static function on_render_block( $content, $parsed_block ) {
		if ( ! empty( $parsed_block['blockName'] ) && 'core/template-part' === $parsed_block['blockName'] ) array_pop( self::$active_template_part_stack );
		return $content;
	}

	public static function register_rest_routes() {
		$can_edit = function() { return current_user_can( 'edit_posts' ); };
		register_rest_route( 'align-reuse/v1', '/fields', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'get_template_fields' ), 'permission_callback' => $can_edit, 'args' => array( 'id' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ) ) ) );
		register_rest_route( 'align-reuse/v1', '/templates', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'get_templates_list' ), 'permission_callback' => $can_edit ) );
	}

	public static function is_instance_keyed( $block_data ) {
		if ( ! is_array( $block_data ) || empty( $block_data ) ) return false;
		$first = reset( $block_data );
		if ( ! is_array( $first ) ) return false;
		$sub = reset( $first );
		return is_array( $sub ) || ! ( isset( $first['type'] ) || isset( $first['content'] ) || isset( $first['src'] ) || isset( $first['href'] ) );
	}

	public static function resolve_slug_or_id( $key ) {
		static $slug_id_map = array();
		if ( empty( $key ) ) return array( 0, '' );
		if ( is_numeric( $key ) ) {
			$id = absint( $key );
			if ( ! isset( $slug_id_map[ 'id_' . $id ] ) ) { $p = get_post( $id ); $slug_id_map[ 'id_' . $id ] = ( $p && ! empty( $p->post_name ) ) ? $p->post_name : ''; }
			return array( $id, $slug_id_map[ 'id_' . $id ] );
		}
		$slug = sanitize_title( (string) $key );
		if ( ! isset( $slug_id_map[ 'slug_' . $slug ] ) ) {
			$found = get_posts( array( 'post_type' => 'wp_block', 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1, 'no_found_rows' => true, 'update_post_meta_cache' => false, 'update_post_term_cache' => false ) );
			$slug_id_map[ 'slug_' . $slug ] = ! empty( $found ) ? (int) $found[0]->ID : 0;
		}
		return array( $slug_id_map[ 'slug_' . $slug ], $slug );
	}

	public static function resolve_block_overrides( $all_overrides, $block_id, $instance_key = '' ) {
		if ( empty( $all_overrides ) || ! is_array( $all_overrides ) ) return array();
		$target_key = $block_id;
		if ( ! isset( $all_overrides[ $target_key ] ) ) {
			list( $res_id, $res_slug ) = self::resolve_slug_or_id( $block_id );
			$alt_key = is_numeric( $block_id ) ? $res_slug : $res_id;
			if ( $alt_key && isset( $all_overrides[ $alt_key ] ) ) $target_key = $alt_key;
		}
		if ( ! isset( $all_overrides[ $target_key ] ) || ! is_array( $all_overrides[ $target_key ] ) ) return array();
		$data = $all_overrides[ $target_key ];
		if ( empty( $data ) ) return array();
		if ( self::is_instance_keyed( $data ) ) {
			if ( ! empty( $instance_key ) ) return ( isset( $data[ $instance_key ] ) && is_array( $data[ $instance_key ] ) ) ? $data[ $instance_key ] : array();
			return ( isset( $data['default'] ) && is_array( $data['default'] ) ) ? $data['default'] : array();
		}
		return $data;
	}

	private static function get_node_inner_html( $dom, $node ) {
		$html = '';
		if ( $node && $node->hasChildNodes() ) { foreach ( $node->childNodes as $child ) $html .= $dom->saveHTML( $child ); }
		return '' !== $html ? $html : ( $node ? ( $node->nodeValue ?: '' ) : '' );
	}

	private static function get_node_clean_text( $xpath, $node ) {
		if ( ! $node ) return '';
		foreach ( array( 'span', 'b', 'strong', 'em', 'label' ) as $t ) {
			$f = $xpath->query( './/' . $t, $node );
			if ( $f && $f->length > 0 && '' !== trim( $f->item( 0 )->textContent ) ) return trim( $f->item( 0 )->textContent );
		}
		$texts = $xpath->query( './text()', $node );
		if ( $texts && $texts->length > 0 ) {
			foreach ( $texts as $tn ) { if ( '' !== trim( $tn->nodeValue ) ) return trim( $tn->nodeValue ); }
		}
		return trim( $node->textContent );
	}

	private static function update_composite_node_text( $dom, $xpath, $node, $clean_val ) {
		foreach ( array( 'span', 'b', 'strong', 'em', 'label' ) as $t ) {
			$f = $xpath->query( './/' . $t, $node );
			if ( $f && $f->length > 0 ) {
				while ( $f->item( 0 )->hasChildNodes() ) $f->item( 0 )->removeChild( $f->item( 0 )->firstChild );
				$f->item( 0 )->appendChild( $dom->createTextNode( wp_strip_all_tags( $clean_val ) ) ); return;
			}
		}
		$texts = $xpath->query( './text()', $node );
		if ( $texts && $texts->length > 0 ) {
			foreach ( $texts as $tn ) { if ( '' !== trim( $tn->nodeValue ) ) { $tn->nodeValue = wp_strip_all_tags( $clean_val ); return; } }
		}
		$node->appendChild( $dom->createTextNode( wp_strip_all_tags( $clean_val ) ) );
	}

	private static function sanitize_html_content( $val ) {
		return wp_kses_post( preg_replace( '#<(script|style)\b[^>]*>(.*?)</\1>#is', '', (string) $val ) );
	}

	public static function sanitize_fields_map( array $fields ) {
		$clean = array();
		$allowed_attrs = array( 'type', 'content', 'src', 'srcset', 'href', 'target', 'alt', 'title', 'width', 'height', 'align', 'link_id', 'link_title', 'hidden' );
		foreach ( $fields as $ov_id => $ov_data ) {
			$clean_ov_id = sanitize_text_field( $ov_id );
			if ( empty( $clean_ov_id ) || ! is_array( $ov_data ) ) continue;
			$clean_item = array();
			foreach ( $ov_data as $key => $val ) {
				$key = sanitize_key( $key );
				if ( ! in_array( $key, $allowed_attrs, true ) ) continue;
				if ( 'content' === $key ) $clean_item['content'] = self::sanitize_html_content( $val );
				elseif ( 'link_id' === $key ) $clean_item['link_id'] = absint( $val );
				elseif ( 'hidden' === $key ) $clean_item['hidden'] = ( ! empty( $val ) && 'false' !== $val && false !== $val );
				elseif ( 'src' === $key || 'href' === $key ) $clean_item[ $key ] = esc_url_raw( (string) $val );
				else $clean_item[ $key ] = in_array( $key, array( 'align', 'type' ), true ) ? sanitize_key( (string) $val ) : sanitize_text_field( (string) $val );
			}
			if ( ! empty( $clean_item ) ) $clean[ $clean_ov_id ] = $clean_item;
		}
		return $clean;
	}

	public static function sanitize_overrides( $raw_overrides ) {
		if ( is_string( $raw_overrides ) ) $raw_overrides = json_decode( $raw_overrides, true );
		if ( ! is_array( $raw_overrides ) || empty( $raw_overrides ) ) return array();
		uksort( $raw_overrides, function( $a, $b ) { return ( is_numeric( $a ) === is_numeric( $b ) ) ? 0 : ( is_numeric( $a ) ? 1 : -1 ); } );
		$grouped = array(); $slug_map = array();
		foreach ( $raw_overrides as $b_id => $b_data ) {
			list( $clean_id, $slug ) = self::resolve_slug_or_id( $b_id );
			if ( ! $clean_id || ! is_array( $b_data ) ) continue;
			if ( ! empty( $slug ) ) $slug_map[ $clean_id ] = $slug;
			$entry = array();
			if ( self::is_instance_keyed( $b_data ) ) {
				foreach ( $b_data as $inst_k => $fields ) {
					$clean_k = sanitize_key( $inst_k ) ?: 'default';
					if ( is_array( $fields ) && ( ! empty( $san = self::sanitize_fields_map( $fields ) ) ) ) $entry[ $clean_k ] = $san;
				}
			} elseif ( ! empty( $san = self::sanitize_fields_map( $b_data ) ) ) {
				$entry = $san;
			}
			if ( empty( $entry ) ) continue;
			if ( ! isset( $grouped[ $clean_id ] ) ) { $grouped[ $clean_id ] = $entry; }
			else {
				if ( self::is_instance_keyed( $grouped[ $clean_id ] ) && ! self::is_instance_keyed( $entry ) ) $entry = array( 'default' => $entry );
				elseif ( ! self::is_instance_keyed( $grouped[ $clean_id ] ) && self::is_instance_keyed( $entry ) ) $grouped[ $clean_id ] = array( 'default' => $grouped[ $clean_id ] );
				$grouped[ $clean_id ] = array_replace_recursive( $grouped[ $clean_id ], $entry );
			}
		}
		$clean = array();
		foreach ( $grouped as $cid => $final_data ) {
			$clean[ $cid ] = $final_data; $slug = $slug_map[ $cid ] ?? '';
			if ( empty( $slug ) ) list( , $slug ) = self::resolve_slug_or_id( $cid );
			if ( ! empty( $slug ) ) $clean[ $slug ] = $final_data;
		}
		return $clean;
	}

	public static function render_shortcode( $atts ) {
		static $rendering_posts = array(), $render_cache = array(), $post_occurrences = array();
		$atts = shortcode_atts( array( 'id' => 0, 'slug' => '', 'name' => '', 'instance' => '', 'key' => '', 'instance_id' => '', 'page_id' => 0, 'target_id' => 0 ), $atts, 'reuse-block' );
		$post_id      = absint( $atts['id'] );
		$instance_key = ! empty( $atts['instance'] ) ? sanitize_key( $atts['instance'] ) : ( ! empty( $atts['key'] ) ? sanitize_key( $atts['key'] ) : ( ! empty( $atts['instance_id'] ) ? sanitize_key( $atts['instance_id'] ) : '' ) );
		$slug         = ! empty( $atts['slug'] ) ? sanitize_title( $atts['slug'] ) : ( ! empty( $atts['name'] ) ? sanitize_title( $atts['name'] ) : '' );
		if ( ! $post_id && ! empty( $slug ) ) list( $post_id ) = self::resolve_slug_or_id( $slug );
		if ( ! $post_id ) return '';

		$context_prefix = '';
		if ( ! empty( self::$active_template_part_stack ) && empty( $atts['page_id'] ) && empty( $atts['target_id'] ) ) {
			$active_tp = end( self::$active_template_part_stack );
			if ( ! empty( $active_tp['id'] ) ) { $current_page_id = (int) $active_tp['id']; $context_prefix = 'tp_' . ( ! empty( $active_tp['slug'] ) ? $active_tp['slug'] : $active_tp['id'] ) . '_'; }
		} else {
			$current_page_id = ! empty( $atts['page_id'] ) ? absint( $atts['page_id'] ) : ( ! empty( $atts['target_id'] ) ? absint( $atts['target_id'] ) : 0 );
			if ( ! $current_page_id && ! empty( $_REQUEST['tp_id'] ) ) $current_page_id = self::resolve_template_post_id( sanitize_text_field( $_REQUEST['tp_id'] ), isset( $_REQUEST['template_type'] ) ? sanitize_text_field( $_REQUEST['template_type'] ) : '' );
			if ( ! $current_page_id ) $current_page_id = get_the_ID() ?: ( isset( $GLOBALS['post']->ID ) ? absint( $GLOBALS['post']->ID ) : 0 );
		}

		$occ_key = $post_id . '_' . $context_prefix . $current_page_id;
		$occ     = isset( $post_occurrences[ $occ_key ] ) ? ++$post_occurrences[ $occ_key ] : ( $post_occurrences[ $occ_key ] = 1 );
		if ( empty( $instance_key ) && $occ > 1 && $current_page_id && ( $all_ovs = get_post_meta( $current_page_id, '_align_reusable_overrides', true ) ) && ! empty( $all_ovs[ $post_id ] ) && is_array( $all_ovs[ $post_id ] ) ) {
			foreach ( array( 'inst_' . $occ, 'instance_' . $occ, (string) $occ ) as $candidate_key ) {
				if ( isset( $all_ovs[ $post_id ][ $candidate_key ] ) ) { $instance_key = $candidate_key; break; }
			}
		}

		$cache_key = $post_id . '_' . $context_prefix . $current_page_id . '_' . ( $instance_key ?: ( $occ > 1 ? 'occ_' . $occ : 'default' ) );
		if ( isset( $render_cache[ $cache_key ] ) ) return $render_cache[ $cache_key ];
		if ( isset( $rendering_posts[ $post_id ] ) ) return '';
		$post = get_post( $post_id );
		$is_builder_mode = ( ! empty( $_GET['page'] ) && 'align-builder' === $_GET['page'] );
		if ( ! $post || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) || empty( trim( $post->post_content ) ) ) {
			$err_msg = ! $post ? "Post ID {$post_id} not found" : ( in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ? "Post ID {$post_id} is {$post->post_status}" : "Post ID {$post_id} is empty" );
			error_log( sprintf( '[Align ReusableBlock] %s', $err_msg ) );
			return $is_builder_mode ? '<div class="align-reusable-block-error" style="padding:12px;background:#fee2e2;color:#991b1b;border:1px solid #f87171;border-radius:4px;margin:10px 0;font-size:13px;">[Align ReusableBlock] ' . esc_html( $err_msg ) . '</div>' : '';
		}
		$rendering_posts[ $post_id ] = true;
		try {
			$raw = $post->post_content;
			$rendered = ( preg_match( '/<!-- wp:align\/custom-html[^>]*>(.*?)<!-- \/wp:align\/custom-html -->/s', $raw, $m ) || preg_match( '/<!-- wp:html -->(.*?)<!-- \/wp:html -->/s', $raw, $m ) ) ? trim( $m[1] ) : ( function_exists( 'do_blocks' ) ? do_blocks( $raw ) : $raw );
			$rendered = self::apply_reusable_overrides( $rendered, $post_id, $current_page_id, $instance_key );
			if ( class_exists( 'Align_Builder_Image_Resolver' ) ) $rendered = Align_Builder_Image_Resolver::resolve_dynamic_image_srcset( $rendered );
			if ( function_exists( 'align_parse_and_render_builder_components' ) ) $rendered = align_parse_and_render_builder_components( $rendered );
			if ( class_exists( 'Align_Builder_Dynamic_Renderer' ) ) $rendered = Align_Builder_Dynamic_Renderer::render_tokens( $rendered, $current_page_id, $post_id );
			// Ensure nested shortcodes within reusable block are expanded during REST API builder preview
			if ( ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || $is_builder_mode ) && strpos( $rendered, 'data-component-shortcode' ) !== false ) {
				$rendered = preg_replace_callback( '/<div\b[^>]*\bdata-component-shortcode\b[^>]*\bdata-shortcode=(["\'])(.*?)\1[^>]*>[\s\S]*?<\/div>/is', function( $m ) {
					$sc_out = do_shortcode( html_entity_decode( $m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
					return ! empty( $sc_out ) ? $sc_out : $m[0];
				}, $rendered );
			}
			// Ensure scoped CSS & JS are loaded (from disk if available, with DB fallback)
			if ( class_exists( 'Align_Builder_Assets' ) && ! empty( $post->post_name ) ) {
				$b_slug = $post->post_name; $b_style_id = 'style-block-' . $b_slug; $b_script_id = 'script-block-' . $b_slug;
				if ( false === strpos( $rendered, $b_style_id ) && ( $disk_css = Align_Builder_Assets::load_external_css( $b_style_id ) ) ) $rendered = '<style id="' . esc_attr( $b_style_id ) . '" type="text/css">' . $disk_css . '</style>' . "\n" . $rendered;
				if ( false === strpos( $rendered, $b_script_id ) && ( $disk_js = Align_Builder_Assets::load_external_js( $b_script_id ) ) ) $rendered .= "\n" . '<script id="' . esc_attr( $b_script_id ) . '" type="text/javascript">' . $disk_js . '</script>';
			}

			$render_cache[ $cache_key ] = $rendered;
		} finally {
			unset( $rendering_posts[ $post_id ] );
		}

		return $render_cache[ $cache_key ];
	}

	public static function resolve_template_post_id( $tp_id, $template_type = '' ) {
		if ( empty( $tp_id ) ) return 0;
		$raw_id = $tp_id;
		$cpt = ( 'template' === $template_type || 0 === strpos( $raw_id, 'tpl_' ) ) ? 'wp_template' : ( ( in_array( $template_type, array( 'part', 'template_part' ), true ) || 0 === strpos( $raw_id, 'tp_' ) ) ? 'wp_template_part' : array( 'wp_template', 'wp_template_part' ) );
		if ( 0 === strpos( $raw_id, 'tp_' ) || 0 === strpos( $raw_id, 'tpl_' ) ) $raw_id = preg_replace( '/^t(p|pl)_/', '', $raw_id );
		$slug = sanitize_file_name( ( strpos( $raw_id, '//' ) !== false ) ? explode( '//', $raw_id )[1] : $raw_id );
		$search_slugs = array_values( array_unique( array_filter( array( $slug, get_stylesheet() . '//' . $slug, get_template() . '//' . $slug, sanitize_title( get_stylesheet() . '-' . $slug ), sanitize_title( get_template() . '-' . $slug ), $raw_id, sanitize_title( $raw_id ) ) ) ) );
		$found = get_posts( array( 'post_type' => $cpt, 'post_status' => 'any', 'posts_per_page' => 1, 'post_name__in' => $search_slugs, 'tax_query' => array( array( 'taxonomy' => 'wp_theme', 'field' => 'slug', 'terms' => array( get_stylesheet(), get_template() ) ) ) ) );
		if ( empty( $found ) ) $found = get_posts( array( 'post_type' => $cpt, 'post_status' => 'any', 'posts_per_page' => 1, 'post_name__in' => $search_slugs ) );
		return ! empty( $found ) ? (int) $found[0]->ID : 0;
	}

	public static function get_cascading_template_overrides( $reusable_post_id ) {
		static $cascading_cache = array(), $slugs_meta_cache = array();
		$slugs = array();
		if ( ! empty( $GLOBALS['_wp_current_template_id'] ) ) $slugs[] = preg_replace( '/^.*?\/\//', '', $GLOBALS['_wp_current_template_id'] );
		if ( is_404() ) $slugs[] = '404'; elseif ( is_search() ) $slugs[] = 'search';
		elseif ( is_front_page() || is_home() ) $slugs = array_merge( $slugs, array( 'front-page', 'home' ) );
		elseif ( is_singular() ) {
			$pt = get_post_type(); $obj = get_queried_object();
			if ( $obj && ! empty( $obj->post_name ) ) $slugs[] = $pt . '-' . $obj->post_name;
			if ( $pt ) $slugs = array_merge( $slugs, array( 'single-' . $pt, $pt, 'singular', 'single' ) );
		} elseif ( is_archive() ) $slugs[] = 'archive';
		$slugs = array_values( array_unique( array_filter( array_merge( $slugs, array( 'index' ) ) ) ) );

		$cache_key = md5( implode( '|', $slugs ) ) . ':' . $reusable_post_id;
		if ( isset( $cascading_cache[ $cache_key ] ) ) return $cascading_cache[ $cache_key ];

		$slugs_key = md5( implode( '|', $slugs ) );
		if ( ! isset( $slugs_meta_cache[ $slugs_key ] ) ) {
			$slugs_meta_cache[ $slugs_key ] = array();
			$search_slugs = array();
			foreach ( $slugs as $s ) { $search_slugs = array_merge( $search_slugs, array( $s, get_stylesheet() . '//' . $s, get_template() . '//' . $s, sanitize_title( get_stylesheet() . '-' . $s ), sanitize_title( get_template() . '-' . $s ) ) ); }
			$posts = get_posts( array( 'post_type' => 'wp_template', 'post_status' => 'any', 'posts_per_page' => 20, 'post_name__in' => array_values( array_unique( $search_slugs ) ) ) );
			$posts_map = array();
			foreach ( $posts as $p ) {
				$raw_name = preg_replace( '/^.*?\/\//', '', $p->post_name );
				$clean_s  = sanitize_title( str_replace( array( get_stylesheet() . '-', get_template() . '-' ), '', $raw_name ) );
				$posts_map[ $clean_s ] = $p; $posts_map[ $raw_name ] = $p; $posts_map[ $p->post_name ] = $p;
			}
			foreach ( $slugs as $s ) {
				if ( isset( $posts_map[ $s ] ) && ( $meta = get_post_meta( $posts_map[ $s ]->ID, '_align_reusable_overrides', true ) ) && is_array( $meta ) ) {
					foreach ( $meta as $b_id => $ovs ) {
						if ( ! isset( $slugs_meta_cache[ $slugs_key ][ $b_id ] ) && is_array( $ovs ) && ! empty( $ovs ) ) $slugs_meta_cache[ $slugs_key ][ $b_id ] = $ovs;
					}
				}
			}
		}

		$found_ovs = $slugs_meta_cache[ $slugs_key ][ $reusable_post_id ] ?? array();
		if ( empty( $found_ovs ) ) {
			list( $res_id, $res_slug ) = self::resolve_slug_or_id( $reusable_post_id );
			$alt_key = is_numeric( $reusable_post_id ) ? $res_slug : $res_id;
			if ( $alt_key && isset( $slugs_meta_cache[ $slugs_key ][ $alt_key ] ) ) $found_ovs = $slugs_meta_cache[ $slugs_key ][ $alt_key ];
		}
		return $cascading_cache[ $cache_key ] = $found_ovs;
	}

	public static function apply_reusable_overrides( $html, $reusable_post_id, $current_page_id = 0, $instance_key = '' ) {
		if ( empty( $html ) || ! $reusable_post_id || ( $current_page_id && $current_page_id === $reusable_post_id ) ) return $html;
		$block_overrides = array();
		if ( ! $current_page_id ) $current_page_id = get_the_ID() ?: ( isset( $GLOBALS['post']->ID ) ? (int) $GLOBALS['post']->ID : 0 );
		if ( $current_page_id && ( $all_overrides = get_post_meta( $current_page_id, '_align_reusable_overrides', true ) ) && is_array( $all_overrides ) ) {
			$block_overrides = self::resolve_block_overrides( $all_overrides, $reusable_post_id, $instance_key );
		}
		if ( empty( $block_overrides ) ) {
			$cascading = self::get_cascading_template_overrides( $reusable_post_id );
			if ( ! empty( $cascading ) && is_array( $cascading ) ) $block_overrides = self::is_instance_keyed( $cascading ) ? self::resolve_block_overrides( array( $reusable_post_id => $cascading ), $reusable_post_id, $instance_key ) : $cascading;
		}
		if ( empty( $block_overrides ) || ! is_array( $block_overrides ) ) return $html;
		$has_complex_override = false;
		foreach ( $block_overrides as $ov_item ) {
			if ( isset( $ov_item['content'] ) || ! empty( $ov_item['hidden'] ) || ! empty( $ov_item['src'] ) ) { $has_complex_override = true; break; }
		}

		// Fast-path: WP_HTML_Tag_Processor for link/attribute-only overrides (excluding images)
		if ( ! $has_complex_override && class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$processor = new WP_HTML_Tag_Processor( $html ); $modified = false;
			while ( $processor->next_tag() ) {
				if ( 'true' === $processor->get_attribute( 'data-allow-overwrite' ) && ( $ov_id = $processor->get_attribute( 'data-overwrite-id' ) ) && isset( $block_overrides[ $ov_id ] ) ) {
					$ov_data = $block_overrides[ $ov_id ];
					if ( 'A' === strtoupper( $processor->get_tag() ) ) {
						$href = $ov_data['href'] ?? ''; $link_id = absint( $ov_data['link_id'] ?? 0 );
						if ( $link_id > 0 && ( $resolved = wp_make_link_relative( get_permalink( $link_id ) ) ) ) {
							$href = $resolved; $processor->set_attribute( 'data-align-link-id', (string) $link_id );
							if ( ! empty( $ov_data['link_title'] ) ) $processor->set_attribute( 'data-align-link-title', esc_attr( $ov_data['link_title'] ) );
							$modified = true;
						}
						if ( '' !== $href ) { $processor->set_attribute( 'href', esc_url( $href ) ); $modified = true; }
						if ( isset( $ov_data['target'] ) ) { ( '' !== $ov_data['target'] ) ? $processor->set_attribute( 'target', esc_attr( $ov_data['target'] ) ) : $processor->remove_attribute( 'target' ); $modified = true; }
					}
				}
			}
			if ( $modified ) return $processor->get_updated_html();
		}

		// DOMDocument Parser for subtree content manipulation
		if ( class_exists( 'DOMDocument' ) && class_exists( 'DOMXPath' ) ) {
			try {
				$styles = array();
				$html_for_dom = preg_replace_callback( '#<style\b[^>]*>.*?</style>#is', function( $match ) use ( &$styles ) {
					$styles[] = $match[0];
					return '<!-- ALIGN_STYLE_PLACEHOLDER_' . ( count( $styles ) - 1 ) . ' -->';
				}, $html );

				$dom = new DOMDocument(); $prev_err = libxml_use_internal_errors( true );
				$loaded = $dom->loadHTML( '<?xml encoding="UTF-8"?><div>' . $html_for_dom . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
				libxml_clear_errors(); libxml_use_internal_errors( $prev_err );

				if ( $loaded ) {
					$xpath = new DOMXPath( $dom );
					$nodes = $xpath->query( '//*[@data-allow-overwrite="true"]' );
					if ( $nodes && $nodes->length > 0 ) {
						$index = 1;
						foreach ( $nodes as $node ) {
							$ov_id = $node->getAttribute( 'data-overwrite-id' ) ?: ( 'ov-' . strtolower( $node->tagName ) . '-' . $index );
							if ( isset( $block_overrides[ $ov_id ] ) ) {
								$ov_data  = $block_overrides[ $ov_id ];
								if ( ! empty( $ov_data['hidden'] ) ) {
									$pic_p = null; $c = $node->parentNode;
									while ( $c && 'div' !== strtolower( $c->nodeName ) ) { if ( 'picture' === strtolower( $c->nodeName ) ) { $pic_p = $c; break; } $c = $c->parentNode; }
									if ( $pic_p && $pic_p->parentNode ) $pic_p->parentNode->removeChild( $pic_p );
									elseif ( $node->parentNode ) $node->parentNode->removeChild( $node );
									continue;
								}
								$tag_name = strtoupper( $node->tagName );

								if ( 'IMG' === $tag_name ) {
									$new_src = $ov_data['src'] ?? '';
									$new_id  = ( ! empty( $new_src ) ) ? attachment_url_to_postid( home_url( $new_src ) ) : 0;
									$pic     = null; $c = $node->parentNode;
									while ( $c && 'div' !== strtolower( $c->nodeName ) ) { if ( 'picture' === strtolower( $c->nodeName ) ) { $pic = $c; break; } $c = $c->parentNode; }
									if ( $pic ) {
										foreach ( $xpath->query( './/source', $pic ) as $s ) $s->parentNode->removeChild( $s );
										$pic->appendChild( $node );
										if ( $new_id > 0 ) {
											$pic->setAttribute( 'data-id', (string) $new_id );
											$pic->setAttribute( 'class', trim( preg_replace( '/\bwp-image-\d+\b/', '', $pic->getAttribute( 'class' ) ) . ' wp-image-' . $new_id ) );
										} else {
											$pic->removeAttribute( 'data-id' );
											$pic->setAttribute( 'class', trim( preg_replace( '/\bwp-image-\d+\b/', '', $pic->getAttribute( 'class' ) ) ) );
										}
									}
									foreach ( array( 'src', 'srcset', 'alt', 'width', 'height', 'align' ) as $attr ) {
										if ( isset( $ov_data[ $attr ] ) && '' !== $ov_data[ $attr ] ) $node->setAttribute( $attr, ( 'src' === $attr ) ? esc_url( $ov_data[ $attr ] ) : esc_attr( $ov_data[ $attr ] ) );
									}
									if ( $new_id > 0 ) {
										$node->setAttribute( 'data-id', (string) $new_id );
										$node->setAttribute( 'class', trim( preg_replace( '/\bwp-image-\d+\b/', '', $node->getAttribute( 'class' ) ) . ' wp-image-' . $new_id ) );
									}
								} elseif ( 'A' === $tag_name ) {
									$href = $ov_data['href'] ?? ''; $link_id = absint( $ov_data['link_id'] ?? 0 );
									if ( $link_id > 0 && ( $resolved = wp_make_link_relative( get_permalink( $link_id ) ) ) ) {
										$href = $resolved; $node->setAttribute( 'data-align-link-id', (string) $link_id );
										if ( ! empty( $ov_data['link_title'] ) ) $node->setAttribute( 'data-align-link-title', esc_attr( $ov_data['link_title'] ) );
									}
									if ( '' !== $href ) { $node->setAttribute( 'href', esc_url( $href ) ); } elseif ( isset( $ov_data['href'] ) ) { $node->removeAttribute( 'href' ); }
									if ( isset( $ov_data['target'] ) ) ( '' !== $ov_data['target'] ) ? $node->setAttribute( 'target', esc_attr( $ov_data['target'] ) ) : $node->removeAttribute( 'target' );
								}
								if ( isset( $ov_data['content'] ) && 'IMG' !== $tag_name ) {
									$clean_val = self::sanitize_html_content( $ov_data['content'] );
									if ( in_array( $tag_name, array( 'A', 'BUTTON' ), true ) ) {
										$is_empty_text = ( '' === trim( wp_strip_all_tags( $clean_val ) ) );
										$has_icons     = ( $xpath->query( './/svg|.//img', $node ) && $xpath->query( './/svg|.//img', $node )->length > 0 );
										if ( $is_empty_text && ! $has_icons && empty( $ov_data['href'] ) ) {
											if ( $node->parentNode ) $node->parentNode->removeChild( $node );
											continue;
										}
										if ( $node->hasChildNodes() ) self::update_composite_node_text( $dom, $xpath, $node, $clean_val );
									} else {
										while ( $node->hasChildNodes() ) $node->removeChild( $node->firstChild );
										$frag = $dom->createDocumentFragment();
										if ( @$frag->appendXML( $clean_val ) ) $node->appendChild( $frag ); else $node->nodeValue = esc_html( wp_strip_all_tags( $clean_val ) );
									}
								}
							}
							$index++;
						}
						$saved_content = '';
						$wrapper       = $dom->getElementsByTagName( 'div' )->item( 0 );
						if ( $wrapper && $wrapper->hasChildNodes() ) { foreach ( $wrapper->childNodes as $child ) $saved_content .= $dom->saveHTML( $child ); } else { $saved_content = $dom->saveHTML(); }
						foreach ( $styles as $s_idx => $s_html ) $saved_content = str_replace( '<!-- ALIGN_STYLE_PLACEHOLDER_' . $s_idx . ' -->', $s_html, $saved_content );
						$html = $saved_content;
					}
				}
			} catch ( Exception $e ) { error_log( '[Align ReusableBlock] DOM parsing exception: ' . $e->getMessage() ); }
		}

		return $html;
	}

	public static function get_template_fields( WP_REST_Request $request ) {
		list( $post_id, $slug ) = self::resolve_slug_or_id( $request->get_param( 'id' ) );
		$page_id       = $request->get_param( 'page_id' ) ?: ( ! empty( $request->get_param( 'tp_id' ) ) ? self::resolve_template_post_id( $request->get_param( 'tp_id' ), $request->get_param( 'template_type' ) ) : 0 );
		$instance_key  = sanitize_key( $request->get_param( 'instance_id' ) ?: $request->get_param( 'instance' ) ?: '' );
		$post          = get_post( $post_id );
		if ( ! $post || 'wp_block' !== $post->post_type ) return new WP_Error( 'not_found', __( 'Template not found.', 'align' ), array( 'status' => 404 ) );
		$saved_overrides = ( $page_id ? get_post_meta( $page_id, '_align_reusable_overrides', true ) : array() );
		$block_overrides = self::resolve_block_overrides( $saved_overrides, $post_id, $instance_key );
		$fields_list     = array(); $seen_keys = array();

		if ( class_exists( 'DOMDocument' ) && class_exists( 'DOMXPath' ) && ! empty( $post->post_content ) ) {
			try {
				$dom = new DOMDocument(); $prev_err = libxml_use_internal_errors( true );
				$loaded = $dom->loadHTML( '<?xml encoding="UTF-8"?>' . $post->post_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
				libxml_clear_errors(); libxml_use_internal_errors( $prev_err );
				if ( $loaded ) {
					$xpath = new DOMXPath( $dom );
					$nodes = $xpath->query( '//*[@data-allow-overwrite="true"]' );
					if ( $nodes && $nodes->length > 0 ) {
						$index = 1;
						foreach ( $nodes as $node ) {
							$ov_id = $node->getAttribute( 'data-overwrite-id' ) ?: ( 'ov-' . strtolower( $node->tagName ) . '-' . $index );
							$tag   = strtolower( $node->tagName );
							$saved = isset( $block_overrides[ $ov_id ] ) ? $block_overrides[ $ov_id ] : array();
							$is_h  = ! empty( $saved['hidden'] );
							if ( 'img' === $tag || 'picture' === $tag || 'video' === $tag ) {
								if ( ! in_array( $ov_id . '_src', $seen_keys, true ) ) {
									$seen_keys[] = $ov_id . '_src'; $m_val = $node->getAttribute( 'src' ) ?: '';
									$fields_list[] = array( 'id' => $ov_id, 'blockName' => 'media', 'type' => 'image', 'fieldType' => 'image', 'attribute' => 'src', 'value' => ( isset( $saved['src'] ) && '' !== $saved['src'] ) ? $saved['src'] : $m_val, 'master_value' => $m_val, 'hidden' => $is_h, 'label' => sprintf( __( 'Media [%s]', 'align' ), strtoupper( $ov_id ) ) );
								}
							} elseif ( 'a' === $tag ) {
								if ( ! in_array( $ov_id . '_link', $seen_keys, true ) ) {
									$seen_keys[] = $ov_id . '_link'; $m_val = $node->getAttribute( 'href' ) ?: ''; $m_text = self::get_node_clean_text( $xpath, $node );
									$m_link_id = $node->getAttribute( 'data-align-link-id' ) ?: ''; $m_link_title = $node->getAttribute( 'data-align-link-title' ) ?: '';
									$cur_link_id = $saved['link_id'] ?? $m_link_id; $cur_link_title = $saved['link_title'] ?? $m_link_title;
									$target = $saved['target'] ?? ( $node->getAttribute( 'target' ) ?: '' ); $cur_href = ( isset( $saved['href'] ) && '' !== $saved['href'] ) ? $saved['href'] : $m_val;
									if ( ! empty( $cur_link_id ) && absint( $cur_link_id ) > 0 && ( $res = wp_make_link_relative( get_permalink( absint( $cur_link_id ) ) ) ) ) $cur_href = $res;
									if ( ! empty( $m_link_id ) && absint( $m_link_id ) > 0 && ( $m_res = wp_make_link_relative( get_permalink( absint( $m_link_id ) ) ) ) ) $m_val = $m_res;
									$fields_list[] = array( 'id' => $ov_id, 'blockName' => 'element', 'type' => 'link', 'fieldType' => 'url', 'attribute' => 'href', 'value' => $cur_href, 'master_value' => $m_val, 'content' => ( isset( $saved['content'] ) && '' !== $saved['content'] ) ? $saved['content'] : $m_text, 'master_text' => $m_text, 'target' => $target, 'master_target' => $target, 'link_id' => $cur_link_id, 'master_link_id' => $m_link_id, 'link_title' => $cur_link_title, 'master_link_title' => $m_link_title, 'hidden' => $is_h, 'label' => sprintf( __( 'Link [%s]', 'align' ), strtoupper( $ov_id ) ) );
								}
							} elseif ( 'button' === $tag ) {
								if ( ! in_array( $ov_id . '_button', $seen_keys, true ) ) {
									$seen_keys[] = $ov_id . '_button'; $m_val = self::get_node_clean_text( $xpath, $node );
									$fields_list[] = array( 'id' => $ov_id, 'blockName' => 'element', 'type' => 'button', 'fieldType' => 'text', 'attribute' => 'content', 'value' => ( isset( $saved['content'] ) && '' !== $saved['content'] ) ? $saved['content'] : $m_val, 'master_value' => $m_val, 'hidden' => $is_h, 'label' => sprintf( __( 'Button [%s]', 'align' ), strtoupper( $ov_id ) ) );
								}
							} elseif ( ! in_array( $ov_id . '_content', $seen_keys, true ) ) {
								$seen_keys[] = $ov_id . '_content'; $m_val = self::get_node_inner_html( $dom, $node );
								$fields_list[] = array( 'id' => $ov_id, 'blockName' => 'element', 'type' => 'text', 'fieldType' => in_array( $tag, array( 'p', 'div', 'blockquote' ), true ) ? 'textarea' : 'text', 'attribute' => 'content', 'value' => ( isset( $saved['content'] ) && '' !== $saved['content'] ) ? $saved['content'] : $m_val, 'master_value' => $m_val, 'hidden' => $is_h, 'label' => sprintf( __( 'Text [%s]', 'align' ), strtoupper( $ov_id ) ) );
							}
							$index++;
						}
					}
				}
			} catch ( Exception $e ) { error_log( '[Align ReusableBlock] REST fields DOM parsing exception: ' . $e->getMessage() ); }
		}

		return rest_ensure_response( array( 'fields' => $fields_list ) );
	}

	public static function get_templates_list() {
		$posts = get_posts( array( 'post_type' => 'wp_block', 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true, 'update_post_meta_cache' => false, 'update_post_term_cache' => false ) );
		return rest_ensure_response( array_map( function( $p ) { return array( 'id' => $p->ID, 'title' => $p->post_title ?: __( '(Untitled)', 'align' ) ); }, $posts ) );
	}
}
