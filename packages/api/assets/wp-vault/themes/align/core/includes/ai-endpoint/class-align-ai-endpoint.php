<?php
/**
 * Align Theme - Dedicated AI REST API Endpoint Engine
 *
 * Registers secure, decoupled REST API endpoints under `/align-ai/v1` for automated AI/agent
 * page lifecycle operations with Dual-Mode Authentication (Application Passwords & Cookie Session).
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ALIGN_AI_REST_NS' ) ) {
	define( 'ALIGN_AI_REST_NS', 'align-ai/v1' );
}

class Align_AI_Endpoint {

	/**
	 * Initialize the AI REST API engine.
	 */
	public static function init(): void {
		require_once __DIR__ . '/class-align-ai-page-controller.php';

		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'send_nocache_headers' ), 10, 4 );

		error_log( '[Align AI Endpoint] AI REST API module initialized.' );
	}

	/**
	 * Register all dedicated AI REST routes.
	 */
	public static function register_rest_routes(): void {
		// 1. Health check & auth verification ping
		register_rest_route(
			ALIGN_AI_REST_NS,
			'/ping',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_ping' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		// 2. Pages collection: List & Create
		register_rest_route(
			ALIGN_AI_REST_NS,
			'/pages',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( 'Align_AI_Page_Controller', 'get_items' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
					'args'                => array(
						'page'     => array(
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page' => array(
							'default'           => 10,
							'sanitize_callback' => 'absint',
						),
						'search'   => array(
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'status'   => array(
							'default'           => 'publish,draft,private',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'lang'     => array(
							'default'           => '',
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( 'Align_AI_Page_Controller', 'create_item' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
				),
			)
		);

		// 3. Single page item: Read, Update, Delete
		register_rest_route(
			ALIGN_AI_REST_NS,
			'/pages/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( 'Align_AI_Page_Controller', 'get_item' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'validate_callback' => function( $param ) {
								return is_numeric( $param );
							},
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( 'Align_AI_Page_Controller', 'update_item' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'validate_callback' => function( $param ) {
								return is_numeric( $param );
							},
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( 'Align_AI_Page_Controller', 'delete_item' ),
					'permission_callback' => array( __CLASS__, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'validate_callback' => function( $param ) {
								return is_numeric( $param );
							},
						),
					),
				),
			)
		);
	}

	/**
	 * Dual-mode permission check.
	 * Allows Application Password / Basic Auth headers directly without nonce check.
	 * Requires valid nonce for cookie-authenticated browser sessions.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return true|WP_Error True if authorized, WP_Error otherwise.
	 */
	public static function check_permission( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			error_log( '[Align AI Endpoint] Unauthorized request: User is not logged in.' );
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You are not currently logged in.', 'align' ),
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'edit_pages' ) && ! current_user_can( 'manage_options' ) ) {
			error_log( sprintf( '[Align AI Endpoint] Forbidden: User #%d lacks edit_pages or manage_options permission.', get_current_user_id() ) );
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to manage pages.', 'align' ),
				array( 'status' => 403 )
			);
		}

		// Dual-Mode Auth Check: Application Passwords vs Cookie Session
		global $wp_rest_auth_cookie;
		$is_cookie_session = ( true === $wp_rest_auth_cookie );

		if ( $is_cookie_session ) {
			// Cookie Session: Require valid REST nonce or Align Settings nonce
			$nonce = $request->get_header( 'x_wp_nonce' );
			if ( empty( $nonce ) ) {
				$nonce = $request->get_param( '_wpnonce' );
			}

			$valid_rest     = ! empty( $nonce ) && wp_verify_nonce( $nonce, 'wp_rest' );
			$valid_settings = ! empty( $nonce ) && wp_verify_nonce( $nonce, 'align_settings_nonce' );

			if ( ! $valid_rest && ! $valid_settings ) {
				error_log( '[Align AI Endpoint] Cookie authentication failed: Invalid or missing nonce.' );
				return new WP_Error(
					'rest_cookie_invalid_nonce',
					__( 'Cookie check failed - invalid nonce.', 'align' ),
					array( 'status' => 403 )
				);
			}
		}

		// Item-level capability checks for requests with an ID parameter
		$post_id = (int) $request->get_param( 'id' );
		if ( $post_id > 0 ) {
			$method = strtoupper( $request->get_method() );
			if ( 'DELETE' === $method && ! current_user_can( 'delete_post', $post_id ) ) {
				error_log( sprintf( '[Align AI Endpoint] Forbidden: User #%d lacks delete_post permission for page #%d.', get_current_user_id(), $post_id ) );
				return new WP_Error(
					'rest_cannot_delete',
					__( 'Sorry, you are not allowed to delete this page.', 'align' ),
					array( 'status' => 403 )
				);
			}
			if ( in_array( $method, array( 'PUT', 'PATCH' ), true ) && ! current_user_can( 'edit_post', $post_id ) ) {
				error_log( sprintf( '[Align AI Endpoint] Forbidden: User #%d lacks edit_post permission for page #%d.', get_current_user_id(), $post_id ) );
				return new WP_Error(
					'rest_cannot_edit',
					__( 'Sorry, you are not allowed to edit this page.', 'align' ),
					array( 'status' => 403 )
				);
			}
		}

		return true;
	}

	/**
	 * Handle API ping request.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function handle_ping( WP_REST_Request $request ): WP_REST_Response {
		global $wp_rest_auth_cookie;
		$auth_type = ( true === $wp_rest_auth_cookie ) ? 'cookie' : 'application_password';

		$current_user = wp_get_current_user();

		error_log( sprintf( '[Align AI Endpoint] Ping request verified for user #%d (%s) via %s.', $current_user->ID, $current_user->user_login, $auth_type ) );

		return rest_ensure_response(
			array(
				'success'   => true,
				'message'   => 'Align AI REST API is operational.',
				'user'      => array(
					'id'    => $current_user->ID,
					'login' => $current_user->user_login,
					'roles' => $current_user->roles,
				),
				'version'   => '1.0.0',
				'auth_type' => $auth_type,
			)
		);
	}

	/**
	 * Enforce nocache headers for all AI REST responses.
	 *
	 * @param mixed           $served  Whether the request has already been served.
	 * @param WP_HTTP_Response $result  Result to send to the client.
	 * @param WP_REST_Request  $request Request used to generate the response.
	 * @param WP_REST_Server   $server  Server instance.
	 * @return mixed
	 */
	public static function send_nocache_headers( $served, $result, $request, $server ) {
		if ( is_a( $request, 'WP_REST_Request' ) && 0 === strpos( $request->get_route(), '/' . ALIGN_AI_REST_NS ) ) {
			nocache_headers();
		}
		return $served;
	}
}
