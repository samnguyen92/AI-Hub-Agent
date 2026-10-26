<?php
/**
 * Align API Sync Endpoint
 *
 * Provides a custom REST API endpoint to dynamically create/update pages,
 * generate HTML templates, and write CSS/JS template assets.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_API_Sync {

	/**
	 * Default secret token if not defined in wp-config.php.
	 */
	const DEFAULT_TOKEN = 'align_sync_token_5e8d9a2f1c834b92b67f';

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	/**
	 * Register custom REST API routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route( 'align-sync/v1', '/create-page', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'handle_create_page' ],
			'permission_callback' => [ self::class, 'check_permission' ],
		] );
	}

	/**
	 * Validate Bearer Token authentication.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return bool|WP_Error True if authorized, WP_Error otherwise.
	 */
	public static function check_permission( WP_REST_Request $request ) {
		$auth_header = $request->get_header( 'Authorization' );

		if ( ! $auth_header ) {
			return new WP_Error(
				'rest_unauthorized',
				__( 'Authorization header missing.', 'align' ),
				[ 'status' => 401 ]
			);
		}

		$expected_token = defined( 'ALIGN_SYNC_TOKEN' ) ? ALIGN_SYNC_TOKEN : self::DEFAULT_TOKEN;

		if ( preg_match( '/Bearer\s+(.*)$/i', $auth_header, $matches ) ) {
			$passed_token = trim( $matches[1] );
			if ( hash_equals( $expected_token, $passed_token ) ) {
				return true;
			}
		}

		return new WP_Error(
			'rest_forbidden',
			__( 'Invalid authorization token.', 'align' ),
			[ 'status' => 401 ]
		);
	}

	/**
	 * Create/update page, generate template and assets.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response
	 */
	public static function handle_create_page( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params();

		if ( empty( $params ) ) {
			return new WP_REST_Response( [ 'error' => __( 'Empty payload.', 'align' ) ], 400 );
		}

		$title        = ! empty( $params['title'] ) ? sanitize_text_field( $params['title'] ) : '';
		$slug         = ! empty( $params['slug'] ) ? sanitize_title( $params['slug'] ) : '';
		$template     = ! empty( $params['template'] ) ? sanitize_key( $params['template'] ) : '';
		$html_content = ! empty( $params['html_content'] ) ? $params['html_content'] : '';
		$assets       = ! empty( $params['assets'] ) ? $params['assets'] : [];

		if ( empty( $title ) || empty( $slug ) ) {
			return new WP_REST_Response( [ 'error' => __( 'Title and slug are required.', 'align' ) ], 400 );
		}

		$theme_dir = get_stylesheet_directory();

		// 1. Handle template specific assets creation (CSS/JS)
		if ( ! empty( $template ) ) {
			$assets_dir = $theme_dir . '/assets/template/' . $template;
			if ( ! is_dir( $assets_dir . '/css' ) ) {
				wp_mkdir_p( $assets_dir . '/css' );
			}
			if ( ! is_dir( $assets_dir . '/js' ) ) {
				wp_mkdir_p( $assets_dir . '/js' );
			}

			if ( ! empty( $assets ) ) {
				// CSS
				if ( isset( $assets['css'] ) && is_array( $assets['css'] ) ) {
					$css = $assets['css'];
					if ( isset( $css['important'] ) ) {
						file_put_contents( $assets_dir . '/css/important.css', $css['important'] );
					}
					if ( isset( $css['non_important'] ) ) {
						file_put_contents( $assets_dir . '/css/non-important.css', $css['non_important'] );
					}
				}
				// JS
				if ( isset( $assets['js'] ) && is_array( $assets['js'] ) ) {
					$js = $assets['js'];
					if ( isset( $js['important'] ) ) {
						file_put_contents( $assets_dir . '/js/important.js', $js['important'] );
					}
					if ( isset( $js['non_important'] ) ) {
						file_put_contents( $assets_dir . '/js/non-important.js', $js['non_important'] );
					}
				}

				// Flush Template cache to reflect asset modifications instantly
				if ( class_exists( 'AssetsCache' ) ) {
					AssetsCache::clearAll();
				}
			}
		}

		// 3. Create or update Page
		$existing_page = get_page_by_path( $slug, OBJECT, 'page' );

		$post_data = [
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $html_content,
			'post_status'  => 'publish',
			'post_type'    => 'page',
		];

		if ( $existing_page ) {
			$post_data['ID'] = $existing_page->ID;
			$post_id = wp_update_post( $post_data );
		} else {
			$post_id = wp_insert_post( $post_data );
		}

		if ( is_wp_error( $post_id ) ) {
			return new WP_REST_Response( [ 'error' => $post_id->get_error_message() ], 500 );
		}

		// 4. Assign custom FSE page template
		if ( ! empty( $template ) ) {
			$template_slug = 'templates/' . $template . '.html';
			update_post_meta( $post_id, '_wp_page_template', $template_slug );
		}

		return new WP_REST_Response( [
			'success'     => true,
			'post_id'     => $post_id,
			'url'         => get_permalink( $post_id ),
			'template'    => ! empty( $template ) ? 'templates/' . $template . '.html' : 'default',
			'assets_path' => ! empty( $template ) ? '/assets/template/' . $template : 'none',
		], 200 );
	}
}
