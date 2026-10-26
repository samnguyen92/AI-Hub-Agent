<?php
/**
 * Align Agent - Template, Template Part, and Reusable Block Management Tools
 *
 * Registers read tools (list_templates, get_template, list_reuse_blocks, get_reuse_block)
 * and write tools (save_template, create_reuse_block, save_reuse_block) routed
 * exclusively through Align_Builder_* handlers.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_Templates {

	/** Register all read and write template/block tools. */
	public static function init(): void {
		self::register_read_tools();
		self::register_write_tools();
	}

	/** Register read-only template and block tools. */
	private static function register_read_tools(): void {
		Align_Agent_Tool_Registry::register( 'list_templates', array(
			'type' => 'read', 'group' => 'structure', 'capability' => 'edit_theme_options',
			'description' => 'List WordPress block templates and template parts (Header, Footer, 404, Single, Archive) with areas, types, and sources.',
			'parameters'  => array( 'type' => 'object', 'properties' => array( 'type' => array( 'type' => 'string', 'description' => 'Optional filter: "all", "wp_template", or "wp_template_part".' ), 'search' => array( 'type' => 'string', 'description' => 'Optional keyword search filter.' ) ) ),
			'callback'    => array( __CLASS__, 'tool_list_templates' ),
		) );

		Align_Agent_Tool_Registry::register( 'get_template', array(
			'type' => 'read', 'requires_builder' => true, 'group' => 'structure', 'capability' => 'edit_theme_options',
			'description' => 'Retrieve decoupled HTML body, CSS, and JS for a WordPress template or template part by tp_id.',
			'parameters'  => array( 'type' => 'object', 'properties' => array( 'tp_id' => array( 'type' => 'string', 'description' => 'Template part or template ID/slug (e.g. tpl_404, tp_header, header).' ), 'template_type' => array( 'type' => 'string', 'description' => 'Optional template type: "wp_template" or "wp_template_part".' ) ), 'required' => array( 'tp_id' ) ),
			'callback'    => array( __CLASS__, 'tool_get_template' ),
		) );

		Align_Agent_Tool_Registry::register( 'list_reuse_blocks', array(
			'type' => 'read', 'group' => 'structure', 'capability' => 'edit_posts',
			'description' => 'List WordPress reusable blocks (wp_block post type) with IDs, slugs, titles, and shortcodes.',
			'parameters'  => array( 'type' => 'object', 'properties' => array( 'search' => array( 'type' => 'string', 'description' => 'Optional keyword search filter.' ), 'page' => array( 'type' => 'integer', 'description' => 'Page number (default 1).' ), 'per_page' => array( 'type' => 'integer', 'description' => 'Items per page (default 20, max 50).' ) ) ),
			'callback'    => array( __CLASS__, 'tool_list_reuse_blocks' ),
		) );

		Align_Agent_Tool_Registry::register( 'get_reuse_block', array(
			'type' => 'read', 'requires_builder' => true, 'group' => 'structure', 'capability' => 'edit_posts',
			'description' => 'Retrieve decoupled HTML body, CSS, and JS for a reusable block by ID.',
			'parameters'  => array( 'type' => 'object', 'properties' => array( 'id' => array( 'type' => 'integer', 'description' => 'Numeric WordPress post ID of the reusable block.' ) ), 'required' => array( 'id' ) ),
			'callback'    => array( __CLASS__, 'tool_get_reuse_block' ),
		) );
	}

	/** Register write tools requiring user approval. */
	private static function register_write_tools(): void {
		Align_Agent_Tool_Registry::register( 'save_template', array(
			'type' => 'write', 'undo' => array( 'adapter' => 'template', 'id_arg' => 'tp_id' ), 'requires_builder' => true, 'group' => 'structure', 'capability' => 'edit_theme_options', 'danger' => true,
			'description' => 'Save updated HTML, CSS, and JS for a WordPress template or template part via Align Builder. Affects entire website layout. Can provide html, css, and js concurrently; at least one content parameter (html, css, js) is required. Requires user approval.',
			'parameters'  => array( 'type' => 'object', 'properties' => array( 'tp_id' => array( 'type' => 'string', 'description' => 'Template part or template ID/slug (e.g. tpl_404, tp_header).' ), 'html' => array( 'type' => 'string', 'description' => 'Clean body HTML without <script> tags. Can be provided concurrently with css and js; at least one of html, css, js is required.' ), 'css' => array( 'type' => 'string', 'description' => 'Optional scoped template CSS. Can be provided concurrently with html and js.' ), 'js' => array( 'type' => 'string', 'description' => 'Optional scoped template JavaScript. Can be provided concurrently with html and css.' ), 'template_type' => array( 'type' => 'string', 'description' => 'Optional template type: "wp_template" or "wp_template_part".' ) ), 'required' => array( 'tp_id' ) ),
			'callback'         => array( __CLASS__, 'tool_save_template' ),
			'summary_callback' => array( __CLASS__, 'summarize_save_template' ),
		) );

		Align_Agent_Tool_Registry::register( 'create_reuse_block', array(
			'type' => 'write', 'undo' => array( 'adapter' => 'create' ), 'requires_builder' => true, 'group' => 'structure', 'capability' => 'edit_posts',
			'description' => 'Create a new reusable block with HTML, CSS, and JS via Align Builder. Requires user approval.',
			'parameters'  => array( 'type' => 'object', 'properties' => array( 'title' => array( 'type' => 'string', 'description' => 'Reusable block title.' ), 'slug' => array( 'type' => 'string', 'description' => 'Optional slug for the block.' ), 'html' => array( 'type' => 'string', 'description' => 'Clean body HTML without <script> tags.' ), 'css' => array( 'type' => 'string', 'description' => 'Optional scoped block CSS.' ), 'js' => array( 'type' => 'string', 'description' => 'Optional scoped block JavaScript.' ) ), 'required' => array( 'title', 'html' ) ),
			'callback'         => array( __CLASS__, 'tool_create_reuse_block' ),
			'summary_callback' => array( __CLASS__, 'summarize_create_reuse_block' ),
		) );

		Align_Agent_Tool_Registry::register( 'save_reuse_block', array(
			'type' => 'write', 'undo' => array( 'adapter' => 'template', 'id_arg' => 'post_id' ), 'requires_builder' => true, 'group' => 'structure', 'capability' => 'edit_posts',
			'description' => 'Save updated HTML, CSS, or JS for an existing reusable block. Omitted html/css/js keep their current values. Requires user approval.',
			'parameters'  => array( 'type' => 'object', 'properties' => array( 'post_id' => array( 'type' => 'integer', 'description' => 'Numeric post ID of the reusable block.' ), 'id' => array( 'type' => 'integer', 'description' => 'Alias for post_id.' ), 'title' => array( 'type' => 'string', 'description' => 'Optional updated title.' ), 'html' => array( 'type' => 'string', 'description' => 'Clean body HTML without <script> tags.' ), 'css' => array( 'type' => 'string', 'description' => 'Optional scoped block CSS.' ), 'js' => array( 'type' => 'string', 'description' => 'Optional scoped block JavaScript.' ) ), 'required' => array( 'post_id' ) ),
			'callback'         => array( __CLASS__, 'tool_save_reuse_block' ),
			'summary_callback' => array( __CLASS__, 'summarize_save_reuse_block' ),
		) );
	}

	/** Count how many published/draft posts/pages embed a specific reusable block. */
	public static function count_block_embeds( int $block_id ): int {
		if ( $block_id <= 0 ) { return 0; }
		global $wpdb;
		$l1 = '%' . $wpdb->esc_like( '[reuse-block id="' . $block_id . '"' ) . '%';
		$l2 = '%' . $wpdb->esc_like( '[reuse_block id="' . $block_id . '"' ) . '%';
		$l3 = '%' . $wpdb->esc_like( '[reuse-block id=\'' . $block_id . '\'' ) . '%';
		$l4 = '%' . $wpdb->esc_like( '[reuse_block id=\'' . $block_id . '\'' ) . '%';
		$l5 = '%' . $wpdb->esc_like( 'wp:block {"ref":' . $block_id ) . '%';
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status IN ('publish', 'draft') AND (post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s)", $l1, $l2, $l3, $l4, $l5 ) );
	}

	/** Build approval summary for save_template. */
	public static function summarize_save_template( array $args ): array {
		$tp_id = sanitize_text_field( (string) ( $args['tp_id'] ?? '' ) );
		$type  = sanitize_key( (string) ( $args['template_type'] ?? '' ) );
		return array(
			'title' => sprintf( __( 'Save Template: %s', 'align-agent' ), $tp_id ), 'description' => __( '⚠️ Warning: This change modifies a site template and affects the entire website layout.', 'align-agent' ),
			'danger' => true,
			'preview_html' => isset( $args['html'] ) ? (string) $args['html'] : '',
			'preview_css'  => isset( $args['css'] ) ? (string) $args['css'] : '',
			'preview_js'   => isset( $args['js'] ) ? (string) $args['js'] : '',
			'meta' => array( 'tp_id' => $tp_id, 'template_type' => $type, 'danger' => true ),
		);
	}

	/** Build approval summary for create_reuse_block. */
	public static function summarize_create_reuse_block( array $args ): array {
		$title = sanitize_text_field( (string) ( $args['title'] ?? 'Untitled Reusable Block' ) );
		return array(
			'title' => sprintf( __( 'Create Reusable Block "%s"', 'align-agent' ), $title ), 'description' => __( 'Create a new reusable block. Embeddable via [reuse-block id="N"].', 'align-agent' ),
			'preview_html' => isset( $args['html'] ) ? (string) $args['html'] : '', 'preview_css' => isset( $args['css'] ) ? (string) $args['css'] : '', 'meta' => array( 'title' => $title ),
		);
	}

	/** Build approval summary for save_reuse_block. */
	public static function summarize_save_reuse_block( array $args ): array {
		$post_id = absint( $args['post_id'] ?? $args['id'] ?? 0 );
		$post    = $post_id > 0 ? get_post( $post_id ) : null;
		$title   = ! empty( $args['title'] ) ? sanitize_text_field( (string) $args['title'] ) : ( $post ? $post->post_title : '#' . $post_id );
		$count   = self::count_block_embeds( $post_id );
		return array(
			'title' => sprintf( __( 'Save Reusable Block #%1$d (%2$s)', 'align-agent' ), $post_id, $title ), 'description' => sprintf( __( 'Update reusable block content. Embedded in %d page(s)/post(s).', 'align-agent' ), $count ),
			'preview_html' => isset( $args['html'] ) ? (string) $args['html'] : '', 'preview_css' => isset( $args['css'] ) ? (string) $args['css'] : '', 'meta' => array( 'post_id' => $post_id, 'embedded_count' => $count ),
		);
	}

	/** Tool: list_templates. */
	public static function tool_list_templates( array $args = array() ): array {
		$type_filter = sanitize_key( (string) ( $args['type'] ?? 'all' ) );
		$search      = isset( $args['search'] ) ? trim( sanitize_text_field( (string) $args['search'] ) ) : '';

		$templates = function_exists( 'get_block_templates' ) ? get_block_templates( array(), 'wp_template' ) : array();
		$parts     = function_exists( 'get_block_templates' ) ? get_block_templates( array(), 'wp_template_part' ) : array();

		// Merge DB template parts in case active child theme term missed parent theme parts.
		$existing_slugs = array_map( function( $p ) { return $p->slug; }, $parts );
		$db_parts = get_posts( array( 'post_type' => 'wp_template_part', 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => 100 ) );
		foreach ( $db_parts as $p_post ) {
			if ( ! in_array( $p_post->post_name, $existing_slugs, true ) ) {
				$mock = function_exists( '_build_block_template_result_from_post' ) ? _build_block_template_result_from_post( $p_post ) : null;
				if ( $mock ) {
					$parts[] = $mock;
					$existing_slugs[] = $p_post->post_name;
				}
			}
		}

		$all_items = array();
		if ( 'wp_template_part' !== $type_filter ) {
			foreach ( $templates as $t ) {
				$tp_id = ( 0 === strpos( $t->id, 'tpl_' ) ) ? $t->id : ( 'tpl_' . $t->id );
				$all_items[] = array( 'tp_id' => $tp_id, 'slug' => (string) $t->slug, 'title' => (string) ( ! empty( $t->title ) ? $t->title : ucfirst( $t->slug ) ), 'area' => (string) ( $t->area ?? '' ), 'type' => 'wp_template', 'source' => (string) ( ! empty( $t->source ) ? $t->source : ( ! empty( $t->wp_id ) ? 'custom' : 'theme' ) ) );
			}
		}

		if ( 'wp_template' !== $type_filter ) {
			foreach ( $parts as $p ) {
				$tp_id = ( 0 === strpos( $p->id, 'tp_' ) ) ? $p->id : ( 'tp_' . $p->id );
				$all_items[] = array( 'tp_id' => $tp_id, 'slug' => (string) $p->slug, 'title' => (string) ( ! empty( $p->title ) ? $p->title : ucfirst( $p->slug ) ), 'area' => (string) ( ! empty( $p->area ) ? $p->area : 'general' ), 'type' => 'wp_template_part', 'source' => (string) ( ! empty( $p->source ) ? $p->source : ( ! empty( $p->wp_id ) ? 'custom' : 'theme' ) ) );
			}
		}

		if ( '' !== $search ) {
			$all_items = array_values( array_filter( $all_items, function( $i ) use ( $search ) {
				return ( false !== stripos( $i['slug'], $search ) || false !== stripos( $i['title'], $search ) || false !== stripos( $i['tp_id'], $search ) );
			} ) );
		}

		return array( 'success' => true, 'total' => count( $all_items ), 'templates' => $all_items );
	}

	/** Extract template/template-part HTML, CSS, JS, and metadata safely. */
	private static function extract_template_payload( string $tp_id, string $template_type = '' ): ?array {
		$raw_id          = $tp_id;
		$is_explicit_tpl = ( 0 === strpos( $raw_id, 'tpl_' ) );
		$is_explicit_tp  = ( 0 === strpos( $raw_id, 'tp_' ) );
		if ( $is_explicit_tpl || $is_explicit_tp ) { $raw_id = preg_replace( '/^t(p|pl)_/', '', $raw_id ); }

		$slug = ( false !== strpos( $raw_id, '//' ) ) ? explode( '//', $raw_id )[1] : $raw_id;
		$slug = sanitize_file_name( $slug );
		if ( empty( $slug ) ) { return null; }

		$search_slugs = array_values( array_unique( array_filter( array(
			$slug, get_stylesheet() . '//' . $slug, get_template() . '//' . $slug,
			sanitize_title( get_stylesheet() . '-' . $slug ), sanitize_title( get_template() . '-' . $slug ), $raw_id,
		) ) ) );

		$is_template = ( 'template' === $template_type || 'wp_template' === $template_type || $is_explicit_tpl );
		if ( ! $is_template && ! $is_explicit_tp && '' === $template_type ) {
			$is_template = ( file_exists( get_stylesheet_directory() . '/templates/' . $slug . '.html' ) || file_exists( get_template_directory() . '/templates/' . $slug . '.html' ) )
				|| ! empty( get_posts( array( 'post_type' => 'wp_template', 'post_name__in' => $search_slugs, 'posts_per_page' => 1 ) ) );
		}

		$target_type = $is_template ? 'wp_template' : 'wp_template_part';
		$found = get_posts( array( 'post_type' => $target_type, 'post_status' => 'any', 'posts_per_page' => 1, 'post_name__in' => $search_slugs, 'tax_query' => array( array( 'taxonomy' => 'wp_theme', 'field' => 'slug', 'terms' => array( get_stylesheet(), get_template() ) ) ) ) );
		if ( empty( $found ) ) { $found = get_posts( array( 'post_type' => $target_type, 'post_status' => 'any', 'posts_per_page' => 1, 'post_name__in' => $search_slugs ) ); }

		$raw_content = '';
		$title       = ucfirst( str_replace( '-', ' ', $slug ) );
		if ( ! empty( $found ) ) {
			$raw_content = (string) $found[0]->post_content;
			$title       = (string) $found[0]->post_title;
		} elseif ( function_exists( 'get_block_template' ) ) {
			$tpl = get_block_template( ( false !== strpos( $raw_id, '//' ) ) ? $raw_id : get_stylesheet() . '//' . $raw_id, $target_type ) ?: get_block_template( get_template() . '//' . $slug, $target_type ) ?: get_block_template( $slug, $target_type );
			if ( $tpl ) { $raw_content = (string) $tpl->content; $title = (string) ( ! empty( $tpl->title ) ? $tpl->title : $title ); }
		}

		if ( empty( $raw_content ) ) {
			$sub_dir = $is_template ? 'templates' : 'parts';
			$paths   = array( get_stylesheet_directory() . '/' . $sub_dir . '/' . $slug . '.html', get_template_directory() . '/' . $sub_dir . '/' . $slug . '.html' );
			if ( ! $is_template ) { $paths[] = get_stylesheet_directory() . '/templates/parts/' . $slug . '.html'; $paths[] = get_template_directory() . '/templates/parts/' . $slug . '.html'; }
			foreach ( $paths as $fp ) {
				if ( file_exists( $fp ) ) { $raw_content = (string) file_get_contents( $fp ); break; }
			}
		}

		if ( $is_template && ! empty( $raw_content ) ) {
			$raw_content = preg_replace( '/<!--\s*wp:template-part\b.*?(?:\/-->|-->[\s\S]*?<!--\s*\/wp:template-part\s*-->)/is', '', $raw_content );
		}

		$html = $raw_content;
		if ( preg_match( '/<!-- wp:align\/custom-html[^>]*>(.*?)<!-- \/wp:align\/custom-html -->/s', $raw_content, $m ) || preg_match( '/<!-- wp:html -->(.*?)<!-- \/wp:html -->/s', $raw_content, $m ) ) {
			$html = trim( $m[1] );
		}
		if ( preg_match( '/<body[^>]*>(.*?)<\/body>/is', $html, $bm ) ) { $html = trim( $bm[1] ); }
		$html = preg_replace( '/<style[^>]*id="wp-global-styles"[^>]*>[\s\S]*?<\/style>/i', '', $html );
		$html = preg_replace( '/<(script|style)[^>]*data-align-preview="1"[^>]*>[\s\S]*?<\/\1>/i', '', $html );

		$prefix    = $is_template ? 'template' : 'part';
		$style_id  = 'style-' . $prefix . '-' . $slug;
		$script_id = 'script-' . $prefix . '-' . $slug;
		$css = ''; $js = '';

		$css_pattern = '/<style[^>]*\bid\s*=\s*(["\']?)(?:' . preg_quote( $style_id, '/' ) . '|style-' . $prefix . '-[^"\'\s>]+|align-style-' . $prefix . '-[^"\'\s>]+)\1[^>]*>([\s\S]*?)<\/style>/is';
		if ( preg_match( $css_pattern, $html, $cm ) ) { $css = trim( $cm[2] ); $html = preg_replace( $css_pattern, '', $html, 1 ); }

		$js_pattern = '/<script[^>]*\bid\s*=\s*(["\']?)(?:' . preg_quote( $script_id, '/' ) . '|script-' . $prefix . '-[^"\'\s>]+|align-script-' . $prefix . '-[^"\'\s>]+)\1[^>]*>([\s\S]*?)<\/script>/is';
		if ( preg_match( $js_pattern, $html, $jm ) ) { $js = trim( $jm[2] ); $html = preg_replace( $js_pattern, '', $html, 1 ); }

		if ( class_exists( 'Align_Builder_Assets' ) ) {
			$css = Align_Builder_Assets::load_external_css( $style_id, $css, array( 'align-' . $style_id ) );
			$js  = Align_Builder_Assets::load_external_js( $script_id, $js, array( 'align-' . $script_id ) );
		}

		return array( 'tp_id' => $tp_id, 'slug' => $slug, 'title' => $title, 'template_type' => $target_type, 'html' => trim( (string) $html ), 'css' => trim( (string) $css ), 'js' => trim( (string) $js ) );
	}

	/** Tool: get_template. */
	public static function tool_get_template( array $args = array() ): array {
		$tp_id         = sanitize_text_field( (string) ( $args['tp_id'] ?? '' ) );
		$template_type = sanitize_key( (string) ( $args['template_type'] ?? '' ) );
		if ( '' === $tp_id ) {
			return array( 'success' => false, 'error' => 'missing_tp_id', 'message' => __( 'A valid tp_id is required.', 'align-agent' ) );
		}

		$req = new WP_REST_Request( 'GET', '/align-builder/v1/page-content' );
		$req->set_param( 'tp_id', $tp_id );
		if ( '' !== $template_type ) { $req->set_param( 'template_type', $template_type ); }

		$payload = self::extract_template_payload( $tp_id, $template_type );
		if ( ! $payload ) {
			return array( 'success' => false, 'error' => 'template_not_found', 'message' => sprintf( __( 'Template "%s" was not found.', 'align-agent' ), $tp_id ) );
		}

		return array_merge( array( 'success' => true ), $payload );
	}

	/** Tool: save_template via Align_Builder_Crud_Handler::save_page. */
	public static function tool_save_template( array $args = array() ): array {
		$tp_id = sanitize_text_field( (string) ( $args['tp_id'] ?? '' ) );
		if ( '' === $tp_id ) {
			return array( 'success' => false, 'error' => 'missing_tp_id', 'message' => __( 'A valid tp_id is required.', 'align-agent' ) );
		}

		$template_type = sanitize_key( (string) ( ! empty( $args['template_type'] ) ? $args['template_type'] : ( 0 === strpos( $tp_id, 'tpl_' ) ? 'template' : ( 0 === strpos( $tp_id, 'tp_' ) ? 'template_part' : 'template' ) ) ) );
		$existing      = null;
		if ( ! array_key_exists( 'html', $args ) || ! array_key_exists( 'css', $args ) || ! array_key_exists( 'js', $args ) ) {
			$tpl_info = self::tool_get_template( array( 'tp_id' => $tp_id, 'template_type' => $template_type ) );
			if ( ! empty( $tpl_info['success'] ) ) { $existing = $tpl_info; }
		}
		$html = array_key_exists( 'html', $args ) ? (string) $args['html'] : ( $existing['html'] ?? '' );
		$css  = array_key_exists( 'css', $args ) ? (string) $args['css'] : ( $existing['css'] ?? '' );
		$js   = array_key_exists( 'js', $args ) ? (string) $args['js'] : ( $existing['js'] ?? '' );
		if ( ( ! array_key_exists( 'css', $args ) || ! array_key_exists( 'js', $args ) ) && $existing ) {
			error_log( sprintf( '[Align Agent] save_template kept existing CSS/JS for %s.', $tp_id ) );
		}

		if ( ! array_key_exists( 'html', $args ) && ! array_key_exists( 'css', $args ) && ! array_key_exists( 'js', $args ) && empty( $args['title'] ) ) {
			return array( 'success' => false, 'error' => 'nothing_to_update', 'message' => __( 'No fields provided to update (must provide at least one of html, css, js, title).', 'align-agent' ) );
		}

		if ( class_exists( 'Align_Builder_Template_Structure' ) ) {
			$persisted = Align_Builder_Template_Structure::get_persisted_content( (string) $tp_id, (string) $template_type );
			if ( 'gutenberg' === Align_Builder_Template_Structure::detect_mode( $persisted ) ) {
				error_log( sprintf( '[Align Agent] save_template blocked for %s: locked to gutenberg.', $tp_id ) );
				return array( 'success' => false, 'error' => 'editor_mode_locked', 'message' => sprintf( __( 'Template "%s" is locked to Gutenberg editor. Switch editor mode before saving with Align Agent.', 'align-agent' ), $tp_id ) );
			}
		}

		$post_id = absint( $args['post_id'] ?? 0 );
		if ( $post_id > 0 && class_exists( 'Align_Editor_Mode_Lock' ) && Align_Editor_Mode_Lock::is_locked_to( $post_id, 'gutenberg' ) ) {
			error_log( sprintf( '[Align Agent] save_template blocked for Post #%d: locked to gutenberg.', $post_id ) );
			return array( 'success' => false, 'error' => 'editor_mode_locked', 'message' => sprintf( __( 'Template post #%d is locked to Gutenberg editor.', 'align-agent' ), $post_id ) );
		}

		$guard_err = Align_Agent_Tools_Pages::guard_html_and_css( $html, $css, $js );
		if ( null !== $guard_err ) { return $guard_err; }

		$save_req = new WP_REST_Request( 'POST', '/align-builder/v1/save-page' );
		foreach ( array( 'tp_id' => $tp_id, 'html' => $html, 'css' => $css, 'js' => $js, 'template_type' => $template_type ) as $k => $v ) {
			$save_req->set_param( $k, $v );
		}

		$res = Align_Builder_Crud_Handler::save_page( $save_req );
		if ( is_wp_error( $res ) ) {
			return array( 'success' => false, 'error' => $res->get_error_code(), 'message' => $res->get_error_message() );
		}

		error_log( sprintf( '[Align Agent] Template saved successfully: %s (type: %s)', $tp_id, $template_type ) );

		return array(
			'success'       => true,
			'tp_id'         => $tp_id,
			'template_type' => $template_type,
			'message'       => sprintf( __( 'Template "%s" saved successfully.', 'align-agent' ), $tp_id ),
			'ui_card'       => array(
				'title'   => sprintf( __( 'Saved Template: %s', 'align-agent' ), $tp_id ),
				'message' => __( 'Site template updated successfully.', 'align-agent' ),
				'danger'  => true,
			),
		);
	}

	/** Tool: list_reuse_blocks. */
	public static function tool_list_reuse_blocks( array $args = array() ): array {
		$page     = max( 1, absint( $args['page'] ?? 1 ) );
		$per_page = min( 50, max( 1, absint( $args['per_page'] ?? 20 ) ) );
		$search   = isset( $args['search'] ) ? trim( sanitize_text_field( (string) $args['search'] ) ) : '';

		$query_args = array(
			'post_type' => 'wp_block', 'post_status' => array( 'publish', 'draft' ),
			'paged' => $page, 'posts_per_page' => $per_page, 'orderby' => 'title', 'order' => 'ASC',
		);
		if ( '' !== $search ) { $query_args['s'] = $search; }

		$query  = new WP_Query( $query_args );
		$blocks = array();
		foreach ( $query->posts as $b ) {
			$blocks[] = array(
				'id' => (int) $b->ID, 'slug' => (string) $b->post_name,
				'title' => (string) ( $b->post_title ?: __( '(No Title)', 'align-agent' ) ),
				'shortcode' => sprintf( '[reuse-block id="%d"]', $b->ID ),
				'status' => (string) $b->post_status, 'modified' => (string) $b->post_modified_gmt,
			);
		}

		return array( 'success' => true, 'page' => $page, 'per_page' => $per_page, 'total' => (int) $query->found_posts, 'total_pages' => (int) $query->max_num_pages, 'blocks' => $blocks );
	}

	/** Tool: get_reuse_block. */
	public static function tool_get_reuse_block( array $args = array() ): array {
		$id = absint( $args['id'] ?? $args['post_id'] ?? 0 );
		if ( $id <= 0 ) {
			return array( 'success' => false, 'error' => 'invalid_id', 'message' => __( 'A valid reusable block ID is required.', 'align-agent' ) );
		}

		$post = get_post( $id );
		if ( ! $post || 'wp_block' !== $post->post_type ) {
			return array( 'success' => false, 'error' => 'block_not_found', 'message' => sprintf( __( 'Reusable block #%d was not found.', 'align-agent' ), $id ) );
		}

		$parts = class_exists( 'Align_Gateway_Pages' )
			? Align_Gateway_Pages::extract_decoupled_parts( $post )
			: array( 'html' => (string) $post->post_content, 'css' => '', 'js' => '' );

		if ( class_exists( 'Align_Builder_Assets' ) && ! empty( $post->post_name ) ) {
			$parts['css'] = Align_Builder_Assets::load_external_css( 'style-block-' . $post->post_name, $parts['css'] );
			$parts['js']  = Align_Builder_Assets::load_external_js( 'script-block-' . $post->post_name, $parts['js'] );
		}

		return array(
			'success' => true, 'id' => (int) $post->ID, 'slug' => (string) $post->post_name,
			'title' => (string) $post->post_title, 'shortcode' => sprintf( '[reuse-block id="%d"]', $post->ID ),
			'status' => (string) $post->post_status, 'html' => $parts['html'], 'css' => $parts['css'],
			'js' => $parts['js'], 'modified' => (string) $post->post_modified_gmt,
		);
	}

	/** Tool: create_reuse_block via Align_Builder_Crud_Handler::create_item + save_page. */
	public static function tool_create_reuse_block( array $args = array() ): array {
		$title = sanitize_text_field( (string) ( $args['title'] ?? '' ) );
		$slug  = ! empty( $args['slug'] ) ? sanitize_title( (string) $args['slug'] ) : sanitize_title( $title );
		if ( '' === $title ) {
			return array( 'success' => false, 'error' => 'missing_title', 'message' => __( 'Reusable block title is required.', 'align-agent' ) );
		}

		$html      = isset( $args['html'] ) ? (string) $args['html'] : '';
		$css       = isset( $args['css'] ) ? (string) $args['css'] : '';
		$js        = isset( $args['js'] ) ? (string) $args['js'] : '';
		$guard_err = Align_Agent_Tools_Pages::guard_html_and_css( $html, $css, $js );
		if ( null !== $guard_err ) { return $guard_err; }

		$create_req = new WP_REST_Request( 'POST', '/align-builder/v1/create-item' );
		$create_req->set_header( 'Content-Type', 'application/json' );
		$create_req->set_body( (string) wp_json_encode( array( 'type' => 'reusable_block', 'title' => $title, 'slug' => $slug ) ) );

		$create_res = Align_Builder_Crud_Handler::create_item( $create_req );
		if ( is_wp_error( $create_res ) ) {
			return array( 'success' => false, 'error' => $create_res->get_error_code(), 'message' => $create_res->get_error_message() );
		}

		$data    = $create_res instanceof WP_REST_Response ? $create_res->get_data() : (array) $create_res;
		$post_id = absint( $data['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return array( 'success' => false, 'error' => 'create_failed', 'message' => __( 'Failed to obtain new post ID for reusable block.', 'align-agent' ) );
		}

		$save_req = new WP_REST_Request( 'POST', '/align-builder/v1/save-page' );
		foreach ( array( 'post_id' => $post_id, 'html' => $html, 'css' => $css, 'js' => $js ) as $k => $v ) {
			$save_req->set_param( $k, $v );
		}

		$save_res = Align_Builder_Crud_Handler::save_page( $save_req );
		if ( is_wp_error( $save_res ) ) {
			return array( 'success' => false, 'error' => $save_res->get_error_code(), 'message' => $save_res->get_error_message() );
		}

		return array(
			'success' => true, 'id' => $post_id, 'title' => $title, 'slug' => $slug,
			'shortcode' => sprintf( '[reuse-block id="%d"]', $post_id ),
			'message' => sprintf( __( 'Created reusable block #%1$d ("%2$s").', 'align-agent' ), $post_id, $title ),
			'ui_card' => array( 'title' => sprintf( __( 'Created Reusable Block #%1$d: %2$s', 'align-agent' ), $post_id, $title ), 'message' => sprintf( __( 'Shortcode: [reuse-block id="%d"]', 'align-agent' ), $post_id ), 'post_id' => $post_id ),
		);
	}

	/** Tool: save_reuse_block via Align_Builder_Crud_Handler::save_page. */
	public static function tool_save_reuse_block( array $args = array() ): array {
		$post_id = absint( $args['post_id'] ?? $args['id'] ?? 0 );
		$post    = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post || 'wp_block' !== $post->post_type ) {
			return array( 'success' => false, 'error' => 'block_not_found', 'message' => sprintf( __( 'Reusable block #%d was not found.', 'align-agent' ), $post_id ) );
		}

		if ( ! array_key_exists( 'html', $args ) && ! array_key_exists( 'css', $args ) && ! array_key_exists( 'js', $args ) && empty( $args['title'] ) ) {
			return array( 'success' => false, 'error' => 'nothing_to_update', 'message' => __( 'No fields provided to update (must provide at least one of html, css, js, title).', 'align-agent' ) );
		}

		$existing = null;
		if ( ! array_key_exists( 'html', $args ) || ! array_key_exists( 'css', $args ) || ! array_key_exists( 'js', $args ) ) {
			$block_info = self::tool_get_reuse_block( array( 'post_id' => $post_id ) );
			if ( ! empty( $block_info['success'] ) ) { $existing = $block_info; }
		}
		$html = array_key_exists( 'html', $args ) ? (string) $args['html'] : ( $existing['html'] ?? (string) $post->post_content );
		$css  = array_key_exists( 'css', $args ) ? (string) $args['css'] : ( $existing['css'] ?? '' );
		$js   = array_key_exists( 'js', $args ) ? (string) $args['js'] : ( $existing['js'] ?? '' );
		if ( ( ! array_key_exists( 'css', $args ) || ! array_key_exists( 'js', $args ) ) && $existing ) {
			error_log( sprintf( '[Align Agent] save_reuse_block kept existing CSS/JS for %d.', $post_id ) );
		}
		$guard_err = Align_Agent_Tools_Pages::guard_html_and_css( $html, $css, $js );
		if ( null !== $guard_err ) { return $guard_err; }

		if ( ! empty( $args['title'] ) ) {
			$new_title = sanitize_text_field( (string) $args['title'] );
			if ( '' !== $new_title && $new_title !== $post->post_title ) {
				$rename_req = new WP_REST_Request( 'POST', '/align-builder/v1/rename-item' );
				$rename_req->set_header( 'Content-Type', 'application/json' );
				$rename_req->set_body( (string) wp_json_encode( array( 'post_id' => $post_id, 'title' => $new_title ) ) );
				Align_Builder_Crud_Handler::rename_item( $rename_req );
			}
		}

		$save_req = new WP_REST_Request( 'POST', '/align-builder/v1/save-page' );
		foreach ( array( 'post_id' => $post_id, 'html' => $html, 'css' => $css, 'js' => $js ) as $k => $v ) {
			$save_req->set_param( $k, $v );
		}

		$save_res = Align_Builder_Crud_Handler::save_page( $save_req );
		if ( is_wp_error( $save_res ) ) {
			return array( 'success' => false, 'error' => $save_res->get_error_code(), 'message' => $save_res->get_error_message() );
		}

		$fresh = get_post( $post_id );
		$count = self::count_block_embeds( $post_id );

		return array(
			'success' => true, 'id' => $post_id, 'title' => $fresh ? (string) $fresh->post_title : '',
			'shortcode' => sprintf( '[reuse-block id="%d"]', $post_id ), 'embedded_count' => $count,
			'message' => sprintf( __( 'Reusable block #%1$d saved successfully.', 'align-agent' ), $post_id ),
			'ui_card' => array( 'title' => sprintf( __( 'Saved Reusable Block #%1$d: %2$s', 'align-agent' ), $post_id, $fresh ? $fresh->post_title : '' ), 'message' => sprintf( __( 'Embedded in %d page(s)/post(s)', 'align-agent' ), $count ), 'post_id' => $post_id ),
		);
	}
}
