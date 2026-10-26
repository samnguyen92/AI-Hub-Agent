<?php
/**
 * Align Theme Core Engine - Align Studio Controller
 *
 * Provides a dedicated standalone studio workspace to inspect, customize,
 * and export any element into a WordPress Reusable Block (wp_block).
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Builder_Dynamic_Studio {

	/**
	 * Constructor to hook into WordPress admin and REST API.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle_studio_page_request' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_subpage' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register hidden subpages for Align Studio.
	 */
	public function register_admin_subpage() {
		add_submenu_page(
			null, // Hidden from menu sidebar
			__( 'Align Studio', 'align' ),
			__( 'Align Studio', 'align' ),
			'edit_pages',
			'align-studio',
			array( $this, 'render_studio_page' )
		);

		// Backward compatibility alias
		add_submenu_page(
			null,
			__( 'Align Studio', 'align' ),
			__( 'Align Studio', 'align' ),
			'edit_pages',
			'align-dynamic-studio',
			array( $this, 'render_studio_page' )
		);
	}

	/**
	 * Intercept standalone studio admin page request before WordPress admin header.
	 */
	public function handle_studio_page_request() {
		if ( ! is_admin() || ! isset( $_GET['page'] ) ) {
			return;
		}

		$page = sanitize_key( $_GET['page'] );
		if ( 'align-studio' === $page || 'align-dynamic-studio' === $page ) {
			if ( ! current_user_can( 'edit_pages' ) && ! current_user_can( 'edit_posts' ) ) {
				wp_die( esc_html__( 'You do not have permission to access Align Studio.', 'align' ) );
			}

			// Prevent caching on Studio page
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			if ( ! defined( 'DONOTMINIFY' ) ) {
				define( 'DONOTMINIFY', true );
			}
			if ( function_exists( 'nocache_headers' ) ) {
				nocache_headers();
			}
			if ( ! headers_sent() ) {
				header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
				header( 'Pragma: no-cache' );
				header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' );
			}

			error_log( '[Align Studio] Serving independent Align Studio page' );
			$this->render_studio_page();
			exit;
		}
	}

	/**
	 * Register REST API route for exporting to Reusable Block.
	 */
	public function register_rest_routes() {
		$edit_perms = function() {
			return current_user_can( 'edit_pages' ) || current_user_can( 'edit_posts' );
		};

		// Studio draft storage endpoint
		register_rest_route(
			ALIGN_BUILDER_REST_NS,
			'/studio-draft',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_save_studio_draft' ),
				'permission_callback' => $edit_perms,
			)
		);

		// Studio canvas live preview document endpoint (loaded via iframe.src)
		register_rest_route(
			ALIGN_BUILDER_REST_NS,
			'/studio-canvas',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_studio_canvas' ),
				'permission_callback' => function( $request ) {
					if ( current_user_can( 'edit_pages' ) || current_user_can( 'edit_posts' ) ) {
						return true;
					}
					$nonce = $request->get_header( 'x_wp_nonce' ) ?: $request->get_param( '_wpnonce' );
					return ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'wp_rest' ) );
				},
			)
		);

		// Studio live preview rendering endpoint
		register_rest_route(
			ALIGN_BUILDER_REST_NS,
			'/studio-render-preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_render_preview' ),
				'permission_callback' => $edit_perms,
			)
		);

		// Reusable Block export endpoints
		register_rest_route(
			ALIGN_BUILDER_REST_NS,
			'/export-studio-block',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_export_reusable_block' ),
				'permission_callback' => $edit_perms,
			)
		);

		register_rest_route(
			ALIGN_BUILDER_REST_NS,
			'/export-dynamic-reusable',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_export_reusable_block' ),
				'permission_callback' => $edit_perms,
			)
		);
	}

	/**
	 * Save studio element draft to transient storage.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_save_studio_draft( $request ) {
		$params   = $request->get_json_params();
		$draft_id = ! empty( $params['draft_id'] ) ? sanitize_key( $params['draft_id'] ) : ( 'std_' . substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 10 ) );
		$payload  = array(
			'html'           => ! empty( $params['html'] ) ? wp_unslash( $params['html'] ) : '',
			'css'            => ! empty( $params['css'] ) ? wp_unslash( $params['css'] ) : '',
			'js'             => ! empty( $params['js'] ) ? wp_unslash( $params['js'] ) : '',
			'tag'            => ! empty( $params['tag'] ) ? sanitize_text_field( $params['tag'] ) : 'SECTION',
			'source_page_id' => ! empty( $params['source_page_id'] ) ? intval( $params['source_page_id'] ) : 0,
			'title'          => ! empty( $params['title'] ) ? sanitize_text_field( $params['title'] ) : '',
		);

		set_transient( 'align_studio_draft_' . $draft_id, $payload, 12 * HOUR_IN_SECONDS );
		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			set_transient( 'align_studio_draft_user_' . $user_id, $payload, 12 * HOUR_IN_SECONDS );
		}

		$canvas_url = add_query_arg(
			array(
				'draft_id' => $draft_id,
				'_wpnonce' => wp_create_nonce( 'wp_rest' ),
			),
			rest_url( ALIGN_BUILDER_REST_NS . '/studio-canvas' )
		);

		error_log( sprintf( '[Align Studio] Draft saved: %s | HTML len: %d', $draft_id, strlen( $payload['html'] ) ) );

		return rest_ensure_response( array(
			'status'     => 'success',
			'draft_id'   => $draft_id,
			'canvas_url' => esc_url_raw( $canvas_url ),
		) );
	}

	/**
	 * Serve live Canvas HTML document for the Studio iframe.
	 *
	 * @param WP_REST_Request $request REST request object.
	 */
	public function handle_get_studio_canvas( $request ) {
		$draft_id   = sanitize_key( $request->get_param( 'draft_id' ) );
		$post_id    = intval( $request->get_param( 'id' ) ?: $request->get_param( 'post_id' ) );
		$content    = '';
		$scoped_css = '';
		$scoped_js  = '';
		$title      = 'Align Studio Canvas';

		$draft = null;
		if ( ! empty( $draft_id ) ) {
			$draft = get_transient( 'align_studio_draft_' . $draft_id );
		}
		if ( empty( $draft ) ) {
			$user_id = get_current_user_id();
			if ( $user_id > 0 ) {
				$draft = get_transient( 'align_studio_draft_user_' . $user_id );
			}
		}
		if ( ! empty( $draft ) && is_array( $draft ) ) {
			$content    = ! empty( $draft['html'] ) ? $draft['html'] : '';
			$scoped_css = ! empty( $draft['css'] ) ? $draft['css'] : '';
			$scoped_js  = ! empty( $draft['js'] ) ? $draft['js'] : '';
			$title      = ! empty( $draft['title'] ) ? $draft['title'] : 'Studio Canvas Preview';
		}

		if ( empty( $content ) && $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$title       = $post->post_title;
				$raw_content = $post->post_content;
				if ( preg_match( '/<!-- wp:align\/custom-html[^>]*>(.*?)<!-- \/wp:align\/custom-html -->/s', $raw_content, $matches ) ) {
					$content = trim( $matches[1] );
				} elseif ( preg_match( '/<!-- wp:html -->(.*?)<!-- \/wp:html -->/s', $raw_content, $matches ) ) {
					$content = trim( $matches[1] );
				} else {
					$content = $raw_content;
				}
				if ( empty( $scoped_css ) ) {
					$scoped_css = get_post_meta( $post_id, '_align_custom_css', true ) ?: '';
					if ( empty( $scoped_css ) && class_exists( 'Align_Builder_Assets' ) && ! empty( $post->post_name ) ) {
						$scoped_css = Align_Builder_Assets::load_external_css( 'style-block-' . $post->post_name ) ?: '';
					}
				}
			}
		}

		if ( class_exists( 'Align_Builder_Dynamic_Renderer' ) ) {
			$source_page = ! empty( $draft['source_page_id'] ) ? intval( $draft['source_page_id'] ) : 0;
			$content     = Align_Builder_Dynamic_Renderer::render_for_preview( $content, $source_page, $post_id );
		}

		$font_preloads    = class_exists( 'Align_Builder_Assets' ) ? Align_Builder_Assets::get_font_preloads() : array();
		$theme_css_urls   = class_exists( 'Align_Builder_Assets' ) ? Align_Builder_Assets::get_builder_css_urls() : array();
		if ( function_exists( 'get_stylesheet_uri' ) ) {
			$theme_css_urls[] = get_stylesheet_uri();
		}
		array_push( $theme_css_urls, ALIGN_BUILDER_URL . 'css/vendor/bootstrap-custom.css', ALIGN_BUILDER_URL . 'css/vendor/line-awesome.css' );
		$theme_css_urls = array_values( array_unique( array_filter( array_map( 'esc_url_raw', $theme_css_urls ) ) ) );

		$header_js_assets = class_exists( 'Align_Builder_Assets' ) ? Align_Builder_Assets::get_builder_js_assets( 'header' ) : array();
		$footer_js_assets = class_exists( 'Align_Builder_Assets' ) ? Align_Builder_Assets::get_builder_js_assets( 'footer' ) : array();

		// Exclude Lenis smooth scroll from Studio preview canvas to prevent scroll hijacking
		$exclude_lenis = function( $asset ) {
			$url = is_array( $asset ) ? ( $asset['url'] ?? '' ) : ( is_object( $asset ) ? ( $asset->url ?? '' ) : (string) $asset );
			return false === strpos( $url, 'lenis' );
		};
		$header_js_assets = array_values( array_filter( $header_js_assets, $exclude_lenis ) );
		$footer_js_assets = array_values( array_filter( $footer_js_assets, $exclude_lenis ) );

		$custom_css = get_option( 'align_custom_css', '' );
		$custom_js  = get_option( 'align_custom_js', '' );

		if ( function_exists( 'show_admin_bar' ) ) { show_admin_bar( false ); }
		if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
		if ( function_exists( 'nocache_headers' ) ) { nocache_headers(); }
		if ( ! headers_sent() ) {
			header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
			header( 'Pragma: no-cache' );
			header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' );
			header( 'Content-Type: text/html; charset=UTF-8' );
		}

		echo '<!DOCTYPE html><html translate="no" class="notranslate" data-lenis-prevent="true" data-lenis-prevent-wheel="true" data-lenis-prevent-touch="true"><head><meta charset="utf-8">';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate"><meta http-equiv="Pragma" content="no-cache"><meta http-equiv="Expires" content="0">';
		echo '<title>' . esc_html( $title ) . '</title>';

		foreach ( $font_preloads as $preload_tag ) {
			echo $preload_tag . "\n";
		}
		foreach ( $theme_css_urls as $css_url ) {
			$busted_css = add_query_arg( 'v', time(), $css_url );
			echo '<link rel="stylesheet" href="' . esc_url( $busted_css ) . '" data-align-preview="1">' . "\n";
		}
		foreach ( $header_js_assets as $js ) {
			$url  = is_array( $js ) ? $js['url'] : $js->url;
			$attr = ( 'defer' === ( is_array( $js ) ? $js['loading'] : $js->loading ) ) ? ' defer' : ( ( 'async' === ( is_array( $js ) ? $js['loading'] : $js->loading ) ) ? ' async' : '' );
			echo '<script src="' . esc_url( add_query_arg( 'v', time(), $url ) ) . '"' . $attr . ' data-align-preview="1"></script>' . "\n";
		}
		if ( is_string( $custom_css ) && '' !== trim( $custom_css ) ) {
			echo '<style id="align-global-custom-css" data-align-preview="1">' . trim( $custom_css ) . '</style>' . "\n";
		}
		echo '<style id="align-studio-scoped-css">' . ( $scoped_css ?: '' ) . '</style>' . "\n";

		// Guaranteed layout and scrolling rules injected after all stylesheets to prevent override
		echo '<style id="align-studio-base-styles">#wpadminbar { display: none !important; } html { margin: 0 !important; padding: 0 !important; width: 100% !important; height: 100% !important; overflow-y: auto !important; overflow-x: hidden !important; scroll-behavior: auto !important; scrollbar-width: thin !important; scrollbar-color: rgba(100, 116, 139, 0.45) transparent !important; } html::-webkit-scrollbar { width: 8px !important; } html::-webkit-scrollbar-track { background: transparent !important; } html::-webkit-scrollbar-thumb { background-color: rgba(100, 116, 139, 0.45) !important; border-radius: 4px !important; } html::-webkit-scrollbar-thumb:hover { background-color: rgba(100, 116, 139, 0.75) !important; } body { margin: 0 !important; padding: 0 !important; width: 100% !important; min-height: 100% !important; height: auto !important; overflow-y: visible !important; overflow-x: hidden !important; background: #ffffff !important; color: #1e293b; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; box-sizing: border-box; } #align-studio-stage { width: 100%; min-height: 100%; box-sizing: border-box; } *, *::before, *::after { box-sizing: inherit; }</style>';

		echo '</head><body data-gramm="false" data-enable-grammarly="false" spellcheck="false" data-lenis-prevent="true" data-lenis-prevent-wheel="true" data-lenis-prevent-touch="true">';
		echo '<div id="align-studio-stage" data-lenis-prevent="true">' . trim( $content ) . '</div>';

		if ( function_exists( 'includes_url' ) ) {
			echo '<script src="' . esc_url( includes_url( '/js/jquery/jquery.min.js' ) ) . '"></script>' . "\n";
		}
		foreach ( $footer_js_assets as $js ) {
			$url  = is_array( $js ) ? $js['url'] : $js->url;
			$attr = ( 'defer' === ( is_array( $js ) ? $js['loading'] : $js->loading ) ) ? ' defer' : ( ( 'async' === ( is_array( $js ) ? $js['loading'] : $js->loading ) ) ? ' async' : '' );
			echo '<script src="' . esc_url( add_query_arg( 'v', time(), $url ) ) . '"' . $attr . ' data-align-preview="1"></script>' . "\n";
		}
		echo '<script id="align-studio-scoped-js">' . ( $scoped_js ?: '' ) . '</script>' . "\n";
		if ( is_string( $custom_js ) && '' !== trim( $custom_js ) ) {
			echo '<script id="align-global-custom-js" data-align-preview="1">' . trim( $custom_js ) . '</script>' . "\n";
		}
		echo '<script>if (typeof window.lenis !== "undefined" && window.lenis) { try { window.lenis.destroy(); } catch(e){} window.lenis = null; }</script>';
		echo '</body></html>';
		exit;
	}

	/**
	 * Handle export REST request to create a native wp_block.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_export_reusable_block( $request ) {
		$params = $request->get_json_params();

		$raw_title     = ! empty( $params['title'] ) ? sanitize_text_field( $params['title'] ) : '';
		$raw_slug      = ! empty( $params['slug'] ) ? sanitize_title( $params['slug'] ) : '';
		$html_payload  = ! empty( $params['html'] ) ? wp_unslash( $params['html'] ) : '';
		$css_payload   = ! empty( $params['css'] ) ? wp_unslash( $params['css'] ) : '';
		$js_payload    = ! empty( $params['js'] ) ? wp_unslash( $params['js'] ) : '';
		$source_id     = ! empty( $params['source_page_id'] ) ? intval( $params['source_page_id'] ) : 0;

		if ( empty( $html_payload ) ) {
			return new WP_Error( 'empty_content', __( 'Cannot export an empty block payload.', 'align' ), array( 'status' => 400 ) );
		}

		$existing_id   = ! empty( $params['post_id'] ) ? absint( $params['post_id'] ) : ( ! empty( $params['block_id'] ) ? absint( $params['block_id'] ) : 0 );
		$is_update     = ( $existing_id > 0 && 'wp_block' === get_post_type( $existing_id ) );

		if ( $is_update ) {
			$post_id  = $existing_id;
			$cur_post = get_post( $post_id );
			$title    = ! empty( $raw_title ) ? $raw_title : $cur_post->post_title;
			$slug     = ! empty( $raw_slug ) ? $raw_slug : $cur_post->post_name;
		} else {
			$title = ! empty( $raw_title ) ? $raw_title : __( 'Dynamic Reusable Block', 'align' );
			$slug  = ! empty( $raw_slug ) ? $raw_slug : sanitize_title( $title );

			// Disambiguate slug if already taken
			$existing = get_posts( array(
				'post_type'      => 'wp_block',
				'post_status'    => 'any',
				'name'           => $slug,
				'posts_per_page' => 1,
			) );
			if ( ! empty( $existing ) ) {
				$slug .= '-' . substr( md5( uniqid( '', true ) ), 0, 5 );
			}
		}

		// Prepare external scoped CSS and JS assets
		$style_id  = 'style-block-' . $slug;
		$script_id = 'script-block-' . $slug;
		if ( ( ! empty( $css_payload ) || ! empty( $js_payload ) ) && class_exists( 'Align_Builder_Assets' ) ) {
			Align_Builder_Assets::save_external_assets( $style_id, $script_id, $css_payload, $js_payload, array(), array() );
		}

		// Minify and wrap HTML payload
		if ( class_exists( 'Align_Builder_Minifier' ) ) {
			$clean_html = Align_Builder_Minifier::minify_payload( $html_payload, $css_payload, $js_payload, $style_id, $script_id );
		} else {
			$clean_html = $html_payload;
		}

		$block_id   = 'align-' . substr( md5( $is_update ? (string) $post_id : uniqid( '', true ) ), 0, 8 );
		$attrs_json = wp_json_encode( array( 'blockId' => $block_id, 'enableShortcode' => true ) );
		$post_content = "<!-- wp:align/custom-html " . $attrs_json . " -->\n" . $clean_html . "\n<!-- /wp:align/custom-html -->";

		kses_remove_filters();
		if ( $is_update ) {
			wp_update_post( array(
				'ID'           => $post_id,
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => wp_slash( $post_content ),
			) );
		} else {
			$post_id = wp_insert_post( array(
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => wp_slash( $post_content ),
				'post_status'  => 'publish',
				'post_type'    => 'wp_block',
			) );
		}
		kses_init_filters();

		if ( is_wp_error( $post_id ) ) {
			error_log( '[Align Studio] Failed to save wp_block: ' . $post_id->get_error_message() );
			return $post_id;
		}

		// Save meta flags
		update_post_meta( $post_id, '_align_is_dynamic_block', 1 );
		if ( $source_id > 0 ) {
			update_post_meta( $post_id, '_align_source_page_id', $source_id );
		}
		if ( ! empty( $css_payload ) ) {
			update_post_meta( $post_id, '_align_custom_css', $css_payload );
		}

		$shortcode = '[reuse-block id="' . $post_id . '"]';
		$edit_url  = admin_url( 'admin.php?page=align-studio&post_id=' . $post_id );

		error_log( sprintf(
			'[Align Studio] %s Reusable Block ID: %d ("%s") | Shortcode: %s | Source Page: %d',
			$is_update ? 'Updated' : 'Exported',
			$post_id, $title, $shortcode, $source_id
		) );

		return rest_ensure_response( array(
			'status'    => 'success',
			'mode'      => $is_update ? 'update' : 'create',
			'post_id'   => $post_id,
			'title'     => $title,
			'slug'      => $slug,
			'shortcode' => $shortcode,
			'edit_url'  => $edit_url,
			'message'   => $is_update ? __( 'Reusable Block updated successfully!', 'align' ) : __( 'Reusable Block created successfully!', 'align' ),
		) );
	}

	/**
	 * Handle REST request to render dynamic preview HTML.
	 *
	 * @param WP_REST_Request $request REST request instance.
	 * @return WP_REST_Response
	 */
	public function handle_render_preview( $request ) {
		$params         = $request->get_json_params() ?: $request->get_params();
		$raw_html       = isset( $params['html'] ) ? (string) $params['html'] : '';
		$source_page_id = ! empty( $params['source_page_id'] ) ? absint( $params['source_page_id'] ) : 0;
		$post_id        = ! empty( $params['post_id'] ) ? absint( $params['post_id'] ) : 0;

		$start_time = microtime( true );
		$rendered   = class_exists( 'Align_Builder_Dynamic_Renderer' )
			? Align_Builder_Dynamic_Renderer::render_for_preview( $raw_html, $source_page_id, $post_id )
			: $raw_html;

		$duration_ms = round( ( microtime( true ) - $start_time ) * 1000, 2 );
		error_log( sprintf( '[Align DynamicStudio:Preview] Rendered preview in %.2f ms | in=%d bytes -> out=%d bytes', $duration_ms, strlen( $raw_html ), strlen( $rendered ) ) );

		return rest_ensure_response( array(
			'status'        => 'success',
			'rendered_html' => $rendered,
		) );
	}

	/**
	 * Render the dedicated Align Studio workspace.
	 */
	public function render_studio_page() {
		$template_file = ALIGN_BUILDER_PATH . 'studio/index.php';
		if ( file_exists( $template_file ) ) {
			error_log( '[Align Studio] Loading Align Studio workspace' );
			require_once $template_file;
		} else {
			wp_die( esc_html__( 'Align Studio template not found.', 'align' ) );
		}
	}
}
