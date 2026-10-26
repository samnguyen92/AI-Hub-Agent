<?php
/**
 * Align Theme Core Engine - Builder CRUD & Rendering Actions Handler
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Builder_Crud_Handler {

	/**
	 * Strip UTF-8 Byte Order Mark (BOM) from string payload.
	 *
	 * @param string|null $string Input string.
	 * @return string Cleaned string without BOM.
	 */
	public static function strip_bom( ?string $string ): string {
		if ( ! empty( $string ) && str_starts_with( $string, "\xEF\xBB\xBF" ) ) {
			error_log( '[Align CrudHandler] UTF-8 BOM detected and stripped from input payload.' );
			return substr( $string, 3 );
		}
		return (string) $string;
	}

	/** Save page content or template part to WordPress database and template file. */
	public static function save_page( $request ) {
		$post_id            = intval( $request->get_param( 'post_id' ) ?: $request->get_param( 'file' ) );
		$tp_id              = $request->get_param( 'tp_id' );
		$template_type      = $request->get_param( 'template_type' ) ?: 'template';
		$html               = self::strip_bom( $request->get_param( 'html' ) ?? '' );
		$css_param          = trim( (string) ( $request->get_param( 'css' ) ?? '' ) );
		$js_param           = trim( (string) ( $request->get_param( 'js' ) ?? '' ) );
		$reusable_overrides = $request->get_param( 'reusable_overrides' );

		if ( ! empty( $tp_id ) ) {
			if ( class_exists( 'Align_Builder_Template_Structure' ) ) {
				$persisted = Align_Builder_Template_Structure::get_persisted_content( (string) $tp_id, (string) $template_type );
				if ( 'gutenberg' === Align_Builder_Template_Structure::detect_mode( $persisted ) ) {
					error_log( sprintf( '[Align CrudHandler] Blocked /save-page for %s: template locked to gutenberg (%d bytes).', $tp_id, strlen( $persisted ) ) );
					return new WP_Error(
						'editor_mode_locked',
						__( 'This template is locked to Gutenberg editor. Switch editor mode in the Site Editor before saving with Align Builder.', 'align' ),
						array( 'status' => 409 )
					);
				}
			}
			$result = Align_Builder_Persistence_Service::persist_template( $tp_id, $html, $css_param, $js_param, $template_type, $reusable_overrides );
			if ( 'error' === ( $result['status'] ?? '' ) ) {
				return new WP_Error( 'template_save_failed', $result['message'] ?? __( 'Template save failed.', 'align' ), array( 'status' => 500 ) );
			}
			return rest_ensure_response( $result );
		}

		if ( ! $post_id && preg_match( '/id=(\d+)/', (string) $request->get_param( 'file' ), $m ) ) {
			$post_id = intval( $m[1] );
		}

		if ( ! $post_id ) {
			return new WP_Error( 'missing_id', __( 'Post ID or Template Part ID is required', 'align' ), array( 'status' => 400 ) );
		}

		if ( class_exists( 'Align_Editor_Mode_Lock' ) ) {
			if ( Align_Editor_Mode_Lock::is_locked_to( $post_id, 'gutenberg' ) ) {
				error_log( sprintf( '[Align CrudHandler] Blocked /save-page for Post #%d: locked to gutenberg.', $post_id ) );
				return new WP_Error(
					'editor_mode_locked',
					__( 'This post is locked to Gutenberg editor. Switch editor mode before saving with Align Builder.', 'align' ),
					array( 'status' => 409 )
				);
			}
			if ( ! Align_Editor_Mode_Lock::is_locked( $post_id ) ) {
				update_post_meta( $post_id, Align_Page_Settings::META_EDITOR_MODE, 'builder' );
			}
		}

		$post_status        = sanitize_key( $request->get_param( 'status' ) ?: $request->get_param( 'post_status' ) ?: '' );

		$result = Align_Builder_Persistence_Service::persist_page( $post_id, $html, $css_param, $js_param, $reusable_overrides, $post_status );
		if ( 'error' === ( $result['status'] ?? '' ) ) {
			return new WP_Error( 'page_save_failed', $result['message'] ?? __( 'Page save failed.', 'align' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( $result );
	}

	/** Initialize script environment ensuring style handlers and actions are active. */
	private static function init_script_environment(): void {
		if ( ! did_action( 'wp_enqueue_scripts' ) ) do_action( 'wp_enqueue_scripts' );
		if ( empty( $GLOBALS['wp_styles'] ) && function_exists( 'wp_styles' ) ) wp_styles();
	}

	/** Capture newly enqueued styles and prepend them to rendered markup. */
	private static function capture_styles( array $before_styles, string $rendered ): string {
		global $wp_styles;
		if ( isset( $wp_styles ) && is_array( $wp_styles->queue ) ) {
			$new_styles = array_diff( $wp_styles->queue, $before_styles );
			if ( ! empty( $new_styles ) ) {
				ob_start();
				$wp_styles->do_items( $new_styles );
				return ob_get_clean() . "\n" . $rendered;
			}
		}
		return $rendered;
	}

	/** Render WordPress Shortcode preview. */
	public static function render_shortcode( $request ) {
		$shortcode = $request->get_param( 'shortcode' );
		if ( empty( $shortcode ) ) return rest_ensure_response( array( 'shortcode' => '', 'rendered' => '' ) );
		$raw_shortcode = wp_unslash( $shortcode );
		if ( function_exists( 'align_normalize_shortcode' ) ) $raw_shortcode = align_normalize_shortcode( $raw_shortcode );
		$instance_id = sanitize_key( $request->get_param( 'instance_id' ) ?: '' );
		if ( $instance_id && 'default' !== $instance_id && ! preg_match( '/\b(?:instance|instance_id|key)=/i', $raw_shortcode ) ) {
			$raw_shortcode = preg_replace( '/\]$/', ' instance="' . $instance_id . '"]', $raw_shortcode );
		}

		$page_id       = $request->get_param( 'page_id' );
		$tp_id         = $request->get_param( 'tp_id' );
		$template_type = $request->get_param( 'template_type' );
		$is_reusable   = (bool) preg_match( '/^\[\s*(?:reuse[-_]block|align[-_](?:reusable[-_])?block|reusable[-_]block)\b/i', $raw_shortcode );

		if ( $is_reusable && ! empty( $tp_id ) && class_exists( 'Align_Reusable_Block' ) ) {
			$page_id = Align_Reusable_Block::resolve_template_post_id( $tp_id, $template_type );
		} elseif ( ! $is_reusable && ! empty( $tp_id ) && str_contains( $tp_id, 'single' ) ) {
			$raw_slug   = preg_replace( '/^t(p|pl)_/', '', $tp_id );
			$base_tp    = preg_replace( '/-zh$/', '', strpos( $raw_slug, '//' ) !== false ? explode( '//', $raw_slug )[1] : $raw_slug );
			$target_cpt = in_array( $base_tp, array( 'single', 'single-post' ), true ) ? 'post' : ( str_starts_with( $base_tp, 'single-' ) ? substr( $base_tp, 7 ) : '' );
			if ( ! empty( $target_cpt ) ) {
				$zh = str_contains( $tp_id, '-zh' );
				$mq = $zh ? array( array( 'key' => '_align_lang', 'value' => 'zh' ) ) : array( 'relation' => 'OR', array( 'key' => '_align_lang', 'compare' => 'NOT EXISTS' ), array( 'key' => '_align_lang', 'value' => 'en' ), array( 'key' => '_align_lang', 'value' => '' ) );
				$sp = get_posts( array( 'post_type' => $target_cpt, 'post_status' => 'publish', 'posts_per_page' => 1, 'meta_query' => $mq ) );
				if ( ! empty( $sp ) ) { $page_id = $sp[0]->ID; }
			}
		} elseif ( empty( $page_id ) && ! empty( $tp_id ) && class_exists( 'Align_Reusable_Block' ) ) {
			$page_id = Align_Reusable_Block::resolve_template_post_id( $tp_id, $template_type );
		}

		if ( $page_id ) {
			if ( $is_reusable && preg_match( '/\bpage_id=["\']?\d+["\']?/i', $raw_shortcode ) ) {
				$raw_shortcode = preg_replace( '/\bpage_id=["\']?\d+["\']?/i', 'page_id="' . intval( $page_id ) . '"', $raw_shortcode );
			} elseif ( ! preg_match( '/\b(?:page_id|target_id)=/i', $raw_shortcode ) ) {
				$raw_shortcode = preg_replace( '/\]$/', ' page_id="' . intval( $page_id ) . '"]', $raw_shortcode );
			}
		}

		$original_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		if ( $page_id && ( $target_post = get_post( (int) $page_id ) ) ) {
			$GLOBALS['post'] = $target_post;
			setup_postdata( $target_post );
		}

		if ( empty( $GLOBALS['wp_styles'] ) && function_exists( 'wp_styles' ) ) { wp_styles(); }
		global $wp_styles;
		$before_styles = ( isset( $wp_styles ) && is_array( $wp_styles->queue ) ) ? $wp_styles->queue : array();
		self::init_script_environment();

		ob_start();
		$return_val = do_shortcode( $raw_shortcode );
		$echoed_val = ob_get_clean();
		$rendered   = ( ! empty( $echoed_val ) ) ? $echoed_val . $return_val : (string) $return_val;
		$rendered   = self::capture_styles( $before_styles, $rendered );

		if ( $original_post ) { $GLOBALS['post'] = $original_post; setup_postdata( $original_post ); } else { wp_reset_postdata(); }

		error_log( sprintf( '[Align CrudHandler] Render Shortcode preview: "%s" (Context Page ID: %d) | Length: %d bytes', substr( $raw_shortcode, 0, 80 ), (int) $page_id, strlen( $rendered ) ) );

		return rest_ensure_response( array( 'shortcode' => $shortcode, 'rendered' => $rendered ) );
	}

	/** Render WordPress Gutenberg Block preview. */
	public static function render_block( $request ) {
		$block_content = $request->get_param( 'block_content' );
		if ( empty( $block_content ) ) {
			return rest_ensure_response( array( 'block_content' => '', 'rendered' => '' ) );
		}
		$raw_block  = wp_unslash( $block_content );
		if ( empty( $GLOBALS['wp_styles'] ) && function_exists( 'wp_styles' ) ) { wp_styles(); }
		global $wp_styles;
		$before_styles = ( isset( $wp_styles ) && is_array( $wp_styles->queue ) ) ? $wp_styles->queue : array();
		self::init_script_environment();

		ob_start();
		$return_val = function_exists( 'do_blocks' ) ? do_blocks( $raw_block ) : $raw_block;
		$echoed_val = ob_get_clean();
		$rendered   = ( ! empty( $echoed_val ) ) ? $echoed_val . $return_val : (string) $return_val;
		$rendered   = self::capture_styles( $before_styles, $rendered );

		error_log( sprintf( '[Align CrudHandler] Render Block preview: "%s" | Length: %d bytes', substr( $raw_block, 0, 80 ), strlen( $rendered ) ) );

		return rest_ensure_response( array( 'block_content' => $block_content, 'rendered' => $rendered ) );
	}

	/** Generate default custom HTML block structure for new pages/templates. */
	public static function generate_default_custom_html_block( $title = '' ) {
		$block_id   = 'align-' . substr( md5( uniqid( '', true ) ), 0, 8 );
		$attrs_json = wp_json_encode( array( 'blockId' => $block_id, 'enableShortcode' => true ) );
		return "<!-- wp:align/custom-html " . $attrs_json . " -->\n\n<!-- /wp:align/custom-html -->";
	}

	/** Create a new WordPress Page or Template (wp_template). */
	public static function create_item( $request ) {
		$params = $request->get_json_params();
		$type   = isset( $params['type'] ) ? sanitize_key( $params['type'] ) : 'page';
		$title  = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : '';
		$slug   = isset( $params['slug'] ) ? sanitize_title( $params['slug'] ) : sanitize_title( $title );

		if ( empty( $title ) || empty( $slug ) ) {
			return new WP_Error( 'missing_fields', __( 'Title and slug are required.', 'align' ), array( 'status' => 400 ) );
		}

		$nonce         = wp_create_nonce( 'wp_rest' );
		$default_block = self::generate_default_custom_html_block( $title );

		if ( 'template_part' === $type || 'part' === $type || 'template' === $type ) {
			$is_template      = ( 'template' === $type );
			$cpt              = $is_template ? 'wp_template' : 'wp_template_part';
			$full_name_child  = get_stylesheet() . '//' . $slug;
			$full_name_parent = get_template() . '//' . $slug;
			$search_slugs     = array_values( array_unique( array_filter( array( $slug, $full_name_child, $full_name_parent, sanitize_title( get_stylesheet() . '-' . $slug ), sanitize_title( get_template() . '-' . $slug ) ) ) ) );

			$existing = get_posts( array( 'post_type' => $cpt, 'post_status' => 'any', 'posts_per_page' => 1, 'post_name__in' => $search_slugs, 'tax_query' => array( array( 'taxonomy' => 'wp_theme', 'field' => 'slug', 'terms' => array( get_stylesheet(), get_template() ) ) ) ) );
			if ( empty( $existing ) ) {
				$existing = get_posts( array( 'post_type' => $cpt, 'post_status' => 'any', 'posts_per_page' => 1, 'post_name__in' => $search_slugs ) );
			}
			if ( ! empty( $existing ) ) {
				return new WP_Error( 'already_exists', sprintf( __( '%s with this slug already exists.', 'align' ), $is_template ? 'Template' : 'Template Part' ), array( 'status' => 409 ) );
			}

			// Auto-preserve FSE Header, Post Content placeholder, and Footer for Templates
			if ( $is_template ) {
				$tpl_inner       = '<div data-component-post-content>post-content</div>';
				$tpl_block_id    = 'align-' . substr( md5( uniqid( '', true ) ), 0, 8 );
				$tpl_attrs       = wp_json_encode( array( 'blockId' => $tpl_block_id, 'enableShortcode' => true ) );
				$tpl_block       = "<!-- wp:align/custom-html " . $tpl_attrs . " -->\n" . $tpl_inner . "\n<!-- /wp:align/custom-html -->";
				$content_to_save = "<!-- wp:template-part {\"slug\":\"header\",\"tagName\":\"header\"} /-->\n\n" . $tpl_block . "\n\n<!-- wp:template-part {\"slug\":\"footer\",\"tagName\":\"footer\"} /-->";
			} else {
				$content_to_save = $default_block;
			}

			$post_id = wp_insert_post( array( 'post_name' => $slug, 'post_title' => $title, 'post_content' => self::strip_bom( $content_to_save ), 'post_status' => 'publish', 'post_type' => $cpt ) );
			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}

			wp_set_object_terms( $post_id, get_stylesheet(), 'wp_theme' );
			if ( ! $is_template ) {
				$area = str_starts_with( $slug, 'header' ) ? 'header' : ( str_starts_with( $slug, 'footer' ) ? 'footer' : ( in_array( $slug, array( 'sidebar', 'comments' ), true ) ? $slug : 'uncategorized' ) );
				wp_set_object_terms( $post_id, $area, 'wp_template_part_area' );
			}
			if ( function_exists( 'wp_clean_theme_json_cache' ) ) {
				wp_clean_theme_json_cache();
			}

			$prefix     = $is_template ? 'tpl_' : 'tp_';
			$tp_id      = $prefix . get_stylesheet() . '//' . $slug;
			$editor_url = admin_url( 'admin.php?page=' . ALIGN_BUILDER_SLUG . '&tp_id=' . urlencode( $tp_id ) );
			error_log( sprintf( '[Align CrudHandler] Created %s "%s" (ID: %d, tp_id: %s) -> Editor URL: %s', $is_template ? 'Template' : 'Template Part', $title, $post_id, $tp_id, $editor_url ) );

			return rest_ensure_response( array(
				'status' => 'success', 'post_id' => $post_id, 'tp_id' => $tp_id, 'type' => $type, 'slug' => $slug, 'title' => $title, 'editor_url' => $editor_url, 'file' => add_query_arg( '_wpnonce', $nonce, rest_url( ALIGN_BUILDER_REST_NS . '/page-content?tp_id=' . urlencode( $tp_id ) ) ),
			) );
		}

		if ( 'reusable_block' === $type || 'block' === $type ) {
			$existing_blocks = get_posts( array( 'post_type' => 'wp_block', 'post_status' => 'any', 'name' => $slug, 'posts_per_page' => 1 ) );
			if ( ! empty( $existing_blocks ) ) {
				return new WP_Error( 'already_exists', __( 'Reusable Block with this slug already exists.', 'align' ), array( 'status' => 409 ) );
			}
			$post_id = wp_insert_post( array( 'post_title' => $title, 'post_name' => $slug, 'post_content' => self::strip_bom( $default_block ), 'post_status' => 'publish', 'post_type' => 'wp_block' ) );
			if ( is_wp_error( $post_id ) ) { return $post_id; }
			$editor_url = admin_url( 'admin.php?page=' . ALIGN_BUILDER_SLUG . '&post_id=' . $post_id );
			error_log( sprintf( '[Align CrudHandler] Created Reusable Block "%s" (ID: %d) -> Editor URL: %s', $title, $post_id, $editor_url ) );

			return rest_ensure_response( array(
				'status' => 'success', 'post_id' => $post_id, 'type' => 'reusable_block', 'slug' => $slug, 'title' => $title, 'shortcode' => '[reuse-block id="' . $post_id . '"]', 'url' => get_permalink( $post_id ), 'editor_url' => $editor_url, 'file' => add_query_arg( '_wpnonce', $nonce, rest_url( ALIGN_BUILDER_REST_NS . '/page-content?id=' . $post_id ) ),
			) );
		}

		$existing_pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'name' => $slug, 'posts_per_page' => 1 ) );
		if ( ! empty( $existing_pages ) ) {
			return new WP_Error( 'already_exists', __( 'Page with this slug already exists.', 'align' ), array( 'status' => 409 ) );
		}

		$post_id = wp_insert_post( array( 'post_title' => $title, 'post_name' => $slug, 'post_content' => self::strip_bom( $default_block ), 'post_status' => 'publish', 'post_type' => 'page' ) );
		if ( is_wp_error( $post_id ) ) { return $post_id; }
		$admin_edit_url = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
		$editor_url     = $admin_edit_url;
		error_log( sprintf( '[Align CrudHandler] Created Page "%s" (ID: %d) -> Editor URL: %s', $title, $post_id, $editor_url ) );

		return rest_ensure_response( array(
			'status'         => 'success',
			'post_id'        => $post_id,
			'type'           => 'page',
			'slug'           => $slug,
			'title'          => $title,
			'url'            => get_permalink( $post_id ),
			'editor_url'     => $editor_url,
			'admin_edit_url' => $admin_edit_url,
			'file'           => add_query_arg( '_wpnonce', $nonce, rest_url( ALIGN_BUILDER_REST_NS . '/page-content?id=' . $post_id ) ),
		) );
	}

	/** Rename a WordPress Page, Reusable Block, or Template. */
	public static function rename_item( $request ) {
		$params  = $request->get_json_params();
		$post_id = isset( $params['post_id'] ) ? intval( $params['post_id'] ) : 0;
		$tp_id   = isset( $params['tp_id'] ) ? sanitize_text_field( $params['tp_id'] ) : '';
		$title   = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : '';

		if ( empty( $title ) ) {
			return new WP_Error( 'missing_title', __( 'Title is required.', 'align' ), array( 'status' => 400 ) );
		}

		if ( $tp_id && ! $post_id ) {
			$raw_id           = preg_replace( '/^t(p|pl)_/', '', $tp_id );
			$is_template      = ( 0 === strpos( $tp_id, 'tpl_' ) );
			$post_type_target = $is_template ? 'wp_template' : 'wp_template_part';
			$slug             = sanitize_file_name( ( strpos( $raw_id, '//' ) !== false ) ? explode( '//', $raw_id )[1] : $raw_id );
			$search_slugs     = array_values( array_unique( array_filter( array( $slug, get_stylesheet() . '//' . $slug, get_template() . '//' . $slug, $raw_id ) ) ) );
			$found            = get_posts( array( 'post_type' => $post_type_target, 'post_status' => 'any', 'posts_per_page' => 1, 'post_name__in' => $search_slugs ) );
			if ( ! empty( $found ) ) { $post_id = $found[0]->ID; }
		}

		if ( ! $post_id ) {
			return new WP_Error( 'missing_id', __( 'Item ID is required.', 'align' ), array( 'status' => 400 ) );
		}

		$updated = wp_update_post( array( 'ID' => $post_id, 'post_title' => $title ) );
		if ( is_wp_error( $updated ) || empty( $updated ) ) {
			return new WP_Error( 'rename_failed', __( 'Failed to rename item.', 'align' ), array( 'status' => 500 ) );
		}

		error_log( sprintf( '[Align CrudHandler] Renamed item ID: %d to "%s"', $post_id, $title ) );

		return rest_ensure_response( array( 'status' => 'success', 'message' => __( 'Item renamed successfully.', 'align' ), 'post_id' => $post_id, 'title' => $title ) );
	}

	/** Delete a WordPress Page or Template. */
	public static function delete_item( $request ) {
		$params  = $request->get_json_params();
		$post_id = isset( $params['post_id'] ) ? intval( $params['post_id'] ) : 0;
		$tp_id   = isset( $params['tp_id'] ) ? sanitize_text_field( $params['tp_id'] ) : '';

		if ( $post_id ) {
			if ( class_exists( 'Align_Builder_Asset_Cleaner' ) ) { Align_Builder_Asset_Cleaner::delete_post_assets( $post_id ); }
			if ( ! wp_delete_post( $post_id, true ) ) {
				return new WP_Error( 'delete_failed', __( 'Failed to delete page.', 'align' ), array( 'status' => 500 ) );
			}
			return rest_ensure_response( array( 'status' => 'success', 'message' => __( 'Page deleted successfully.', 'align' ), 'post_id' => $post_id ) );
		}

		if ( $tp_id ) {
			$raw_id           = preg_replace( '/^t(p|pl)_/', '', $tp_id );
			$is_template      = ( 0 === strpos( $tp_id, 'tpl_' ) );
			$post_type_target = $is_template ? 'wp_template' : 'wp_template_part';
			$slug             = sanitize_file_name( ( strpos( $raw_id, '//' ) !== false ) ? explode( '//', $raw_id )[1] : $raw_id );
			$search_slugs     = array_values( array_unique( array_filter( array( $slug, get_stylesheet() . '//' . $slug, get_template() . '//' . $slug, sanitize_title( get_stylesheet() . '-' . $slug ), sanitize_title( get_template() . '-' . $slug ), $raw_id, sanitize_title( $raw_id ) ) ) ) );

			if ( class_exists( 'Align_Builder_Asset_Cleaner' ) ) {
				Align_Builder_Asset_Cleaner::delete_template_part_assets( $slug, $is_template ? 'template' : 'part' );
			}

			$found = get_posts( array( 'post_type' => $post_type_target, 'post_status' => 'any', 'posts_per_page' => -1, 'post_name__in' => $search_slugs ) );
			if ( ! empty( $found ) ) {
				foreach ( $found as $item ) { wp_delete_post( $item->ID, true ); }
			}

			if ( function_exists( 'wp_clean_theme_json_cache' ) ) { wp_clean_theme_json_cache(); }

			$label = $is_template ? __( 'Template', 'align' ) : __( 'Template Part', 'align' );
			return rest_ensure_response( array( 'status' => 'success', 'message' => sprintf( __( '%s deleted successfully.', 'align' ), $label ), 'tp_id' => $tp_id ) );
		}

		return new WP_Error( 'missing_id', __( 'post_id or tp_id is required.', 'align' ), array( 'status' => 400 ) );
	}
}
