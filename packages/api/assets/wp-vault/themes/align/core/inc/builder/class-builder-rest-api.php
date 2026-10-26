<?php
/**
 * Align Theme Core Engine - Builder REST API Controllers & Router
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-builder-content-handler.php';
require_once __DIR__ . '/class-builder-crud-handler.php';
require_once __DIR__ . '/class-builder-revision-handler.php';
require_once __DIR__ . '/class-builder-seo-handler.php';
require_once __DIR__ . '/class-builder-translator.php';
require_once __DIR__ . '/class-builder-custom-templates-handler.php';
require_once __DIR__ . '/class-builder-export-import-handler.php';

class Align_Builder_Rest_Api {

	/**
	 * Constructor to register REST API routes.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_filter( 'rest_pre_serve_request', array( $this, 'send_builder_nocache_headers' ), 10, 4 );
	}

	/**
	 * Ensure no-cache headers on all Builder REST API responses.
	 *
	 * @param bool             $served  Whether the request has already been served.
	 * @param WP_HTTP_Response $result  Result to send to the client.
	 * @param WP_REST_Request  $request Request used to generate the response.
	 * @param WP_REST_Server   $server  Server instance.
	 * @return bool
	 */
	public function send_builder_nocache_headers( $served, $result, $request, $server ) {
		$route = $request ? $request->get_route() : '';
		if ( ! empty( $route ) && 0 === strpos( ltrim( $route, '/' ), ALIGN_BUILDER_REST_NS ) ) {
			if ( function_exists( 'nocache_headers' ) ) {
				nocache_headers();
			}
			if ( ! headers_sent() ) {
				header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
				header( 'Pragma: no-cache' );
				header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' );
			}
		}
		return $served;
	}

	/**
	 * Register all REST API endpoints for Align Builder.
	 */
	public function register_rest_routes() {
		$ns = ALIGN_BUILDER_REST_NS;
		register_rest_route( $ns, '/pages', array( 'methods' => 'GET', 'callback' => array( 'Align_Builder_Content_Handler', 'get_pages' ), 'permission_callback' => array( $this, 'check_permission' ) ) );
		register_rest_route( $ns, '/page-content', array( 'methods' => 'GET', 'callback' => array( 'Align_Builder_Content_Handler', 'get_page_content' ), 'permission_callback' => array( $this, 'check_permission' ) ) );
		register_rest_route( $ns, '/search-links', array( 'methods' => 'GET', 'callback' => array( 'Align_Builder_Content_Handler', 'search_links' ), 'permission_callback' => array( $this, 'check_permission' ) ) );
		register_rest_route( $ns, '/save-page', array( 'methods' => 'POST', 'callback' => array( 'Align_Builder_Crud_Handler', 'save_page' ), 'permission_callback' => array( $this, 'check_permission' ) ) );
		register_rest_route( $ns, '/switch-editor-mode', array( 'methods' => 'POST', 'callback' => array( 'Align_Editor_Mode_Lock', 'rest_switch_editor_mode' ), 'permission_callback' => array( 'Align_Editor_Mode_Lock', 'check_switch_permission' ) ) );
		register_rest_route( $ns, '/render-shortcode', array( 'methods' => 'POST', 'callback' => array( 'Align_Builder_Crud_Handler', 'render_shortcode' ), 'permission_callback' => array( $this, 'check_permission' ) ) );
		register_rest_route( $ns, '/render-block', array( 'methods' => 'POST', 'callback' => array( 'Align_Builder_Crud_Handler', 'render_block' ), 'permission_callback' => array( $this, 'check_permission' ) ) );
		register_rest_route( $ns, '/create-item', array( 'methods' => 'POST', 'callback' => array( 'Align_Builder_Crud_Handler', 'create_item' ), 'permission_callback' => array( $this, 'check_permission' ) ) );
		register_rest_route( $ns, '/rename-item', array( 'methods' => 'POST', 'callback' => array( 'Align_Builder_Crud_Handler', 'rename_item' ), 'permission_callback' => array( $this, 'check_permission' ) ) );
		register_rest_route( $ns, '/delete-item', array( 'methods' => 'POST', 'callback' => array( 'Align_Builder_Crud_Handler', 'delete_item' ), 'permission_callback' => array( $this, 'check_permission' ) ) );
		register_rest_route( $ns, '/attachment-sizes', array( 'methods' => 'GET', 'callback' => array( 'Align_Image_Sizes', 'rest_get_attachment_sizes' ), 'permission_callback' => array( $this, 'check_permission' ) ) );
		register_rest_route( $ns, '/revisions', array( 'methods' => 'GET', 'callback' => array( 'Align_Builder_Revision_Handler', 'get_revisions' ), 'permission_callback' => array( $this, 'check_permission' ) ) );
		register_rest_route( $ns, '/restore-revision', array( 'methods' => 'POST', 'callback' => array( 'Align_Builder_Revision_Handler', 'restore_revision' ), 'permission_callback' => array( $this, 'check_permission' ) ) );
		register_rest_route( $ns, '/import-zip', array( 'methods' => 'POST', 'callback' => array( 'Align_Zip_Importer', 'rest_import_zip' ), 'permission_callback' => array( $this, 'check_permission' ) ) );
		register_rest_route( $ns, '/custom-templates', array(
			array( 'methods' => 'GET',    'callback' => array( 'Align_Builder_Custom_Templates_Handler', 'get_templates' ),   'permission_callback' => array( $this, 'check_permission' ) ),
			array( 'methods' => 'POST',   'callback' => array( 'Align_Builder_Custom_Templates_Handler', 'save_template' ),  'permission_callback' => array( $this, 'check_permission' ) ),
			array( 'methods' => 'DELETE', 'callback' => array( 'Align_Builder_Custom_Templates_Handler', 'delete_template' ),'permission_callback' => array( $this, 'check_permission' ) ),
		) );
		register_rest_route( $ns, '/export-page', array( 'methods' => array( 'GET', 'POST' ), 'callback' => array( 'Align_Builder_Export_Import_Handler', 'export_page' ), 'permission_callback' => array( $this, 'check_permission' ) ) );
		register_rest_route( $ns, '/import-page', array( 'methods' => 'POST', 'callback' => array( 'Align_Builder_Export_Import_Handler', 'import_page' ), 'permission_callback' => array( $this, 'check_permission' ) ) );

		// Language Manager endpoints
		register_rest_route( $ns, '/languages', array( 'methods' => 'GET', 'callback' => array( 'Align_Language_Manager', 'rest_get_languages' ), 'permission_callback' => array( $this, 'check_permission' ) ) );
		register_rest_route( $ns, '/create-translation', array( 'methods' => 'POST', 'callback' => array( 'Align_Language_Manager', 'rest_create_translation' ), 'permission_callback' => array( $this, 'check_permission' ) ) );

		// SEO Meta endpoints (RankMath integration)
		register_rest_route( $ns, '/seo-meta', array(
			array( 'methods' => 'GET',  'callback' => array( 'Align_Builder_Seo_Handler', 'get_seo_meta' ),      'permission_callback' => array( $this, 'check_permission' ) ),
			array( 'methods' => 'POST', 'callback' => array( 'Align_Builder_Seo_Handler', 'save_seo_meta' ),     'permission_callback' => array( $this, 'check_permission' ) ),
		) );
		register_rest_route( $ns, '/seo-generate', array( 'methods' => 'POST', 'callback' => array( 'Align_Builder_Seo_Handler', 'generate_seo_meta' ), 'permission_callback' => array( $this, 'check_permission' ) ) );

		// AI Content Translator endpoints
		register_rest_route( $ns, '/translate-content', array( 'methods' => 'POST', 'callback' => array( 'Align_Builder_Translator', 'rest_translate_content' ), 'permission_callback' => array( $this, 'check_permission' ) ) );
		register_rest_route( $ns, '/localize-urls', array( 'methods' => 'POST', 'callback' => array( $this, 'api_localize_urls' ), 'permission_callback' => array( $this, 'check_permission' ) ) );
		register_rest_route( $ns, '/translate-post', array( 'methods' => 'POST', 'callback' => array( 'Align_Builder_Translator', 'rest_translate_post' ), 'permission_callback' => array( $this, 'check_permission' ) ) );
		register_rest_route( $ns, '/copy-original-post', array( 'methods' => 'POST', 'callback' => array( 'Align_Builder_Translator', 'rest_copy_original_post' ), 'permission_callback' => array( $this, 'check_permission' ) ) );
		register_rest_route( $ns, '/copy-original-section', array( 'methods' => 'POST', 'callback' => array( 'Align_Builder_Translator', 'rest_copy_original_section' ), 'permission_callback' => array( $this, 'check_permission' ) ) );
		register_rest_route( $ns, '/generate-content', array( 'methods' => 'POST', 'callback' => array( 'Align_Builder_Translator', 'rest_generate_content' ), 'permission_callback' => array( $this, 'check_permission' ) ) );
	}

	/**
	 * Localize an array of internal URLs into the target language.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function api_localize_urls( $request ) {
		$urls        = $request->get_param( 'urls' );
		$target_lang = sanitize_key( $request->get_param( 'target_lang' ) ?: '' );

		if ( ! is_array( $urls ) || empty( $target_lang ) ) {
			return rest_ensure_response( array( 'success' => true, 'urls' => (object) array() ) );
		}

		$result = array();
		if ( class_exists( 'Align_Language_Url_Localizer' ) ) {
			foreach ( $urls as $u ) {
				if ( is_string( $u ) && '' !== trim( $u ) ) {
					$result[ $u ] = Align_Language_Url_Localizer::localize_url( $u, $target_lang );
				}
			}
		}

		return rest_ensure_response( array(
			'success' => true,
			'urls'    => $result,
		) );
	}

	/**
	 * Permission callback for REST API requests.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return bool True if authorized, false otherwise.
	 */
	public function check_permission( $request ) {
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) ) {
			return false;
		}
		$nonce = $request->get_header( 'x_wp_nonce' ) ?: $request->get_param( '_wpnonce' );
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return false;
		}
		return true;
	}

	// -------------------------------------------------------------
	// Backward-Compatibility Method Proxies
	// -------------------------------------------------------------

	public function api_get_pages( $request ) {
		return Align_Builder_Content_Handler::get_pages( $request );
	}

	public function api_get_page_content( $request ) {
		Align_Builder_Content_Handler::get_page_content( $request );
	}

	public function api_search_links( $request ) {
		return Align_Builder_Content_Handler::search_links( $request );
	}

	public function api_save_page( $request ) {
		return Align_Builder_Crud_Handler::save_page( $request );
	}

	public function api_render_shortcode( $request ) {
		return Align_Builder_Crud_Handler::render_shortcode( $request );
	}

	public function api_render_block( $request ) {
		return Align_Builder_Crud_Handler::render_block( $request );
	}

	public function api_create_item( $request ) {
		return Align_Builder_Crud_Handler::create_item( $request );
	}

	public function api_rename_item( $request ) {
		return Align_Builder_Crud_Handler::rename_item( $request );
	}

	public function api_delete_item( $request ) {
		return Align_Builder_Crud_Handler::delete_item( $request );
	}
}
