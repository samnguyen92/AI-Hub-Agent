<?php
/**
 * Align Agent Gateway - REST API Router
 *
 * Registers all 9 REST API endpoints under namespace 'align-agent/v1'
 * and attaches dedicated controller callbacks and authentication checks.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Gateway_Router {

	/**
	 * Route namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'align-agent/v1';

	/**
	 * Initialize router hooks.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register all endpoints in the agent gateway namespace.
	 */
	public static function register_routes(): void {
		$auth_callback = array( 'Align_Gateway_Auth', 'check_permission' );
		$namespace     = defined( 'ALIGN_AGENT_REST_NS' ) ? ALIGN_AGENT_REST_NS : self::NAMESPACE;

		// 1. GET & POST /ping - Handshake and health check.
		register_rest_route(
			$namespace,
			'/ping',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( 'Align_Gateway_System', 'handle_ping' ),
				'permission_callback' => $auth_callback,
			)
		);

		// 2. POST /cache/flush - Clear transients, object cache, and theme cache.
		register_rest_route(
			$namespace,
			'/cache/flush',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( 'Align_Gateway_System', 'handle_cache_flush' ),
				'permission_callback' => $auth_callback,
				'args'                => array(
					'flush_rewrites' => array(
						'type'        => 'boolean',
						'required'    => false,
						'default'     => false,
					),
				),
			)
		);

		// 3. GET /context - Design tokens, CSS variables, and active stylesheets.
		register_rest_route(
			$namespace,
			'/context',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( 'Align_Gateway_Context', 'handle_get_context' ),
				'permission_callback' => $auth_callback,
			)
		);

		// 4. GET /pages - Lightweight page listing and search.
		register_rest_route(
			$namespace,
			'/pages',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( 'Align_Gateway_Pages', 'handle_get_pages' ),
				'permission_callback' => $auth_callback,
				'args'                => array(
					'page'     => array(
						'type'     => 'integer',
						'default'  => 1,
						'required' => false,
					),
					'per_page' => array(
						'type'     => 'integer',
						'default'  => 20,
						'required' => false,
					),
					's'        => array(
						'type'     => 'string',
						'required' => false,
					),
					'slug'     => array(
						'type'     => 'string',
						'required' => false,
					),
					'status'   => array(
						'type'     => 'string',
						'default'  => 'publish,draft,private',
						'required' => false,
					),
				),
			)
		);

		// 5. GET /pages/(?P<id>\d+) - Single page details with decoupled HTML & CSS.
		register_rest_route(
			$namespace,
			'/pages/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( 'Align_Gateway_Pages', 'handle_get_page' ),
				'permission_callback' => $auth_callback,
				'args'                => array(
					'id' => array(
						'validate_callback' => function( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// 6. POST /pages/create - Create page, write CSS to disk, wrap in custom-html block.
		register_rest_route(
			$namespace,
			'/pages/create',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( 'Align_Gateway_Pages', 'handle_create_page' ),
				'permission_callback' => $auth_callback,
				'args'                => array(
					'title'  => array(
						'type'     => 'string',
						'required' => false,
					),
					'slug'   => array(
						'type'     => 'string',
						'required' => false,
					),
					'status' => array(
						'type'     => 'string',
						'default'  => 'publish',
						'required' => false,
					),
					'html'   => array(
						'type'     => 'string',
						'required' => false,
					),
					'css'    => array(
						'type'     => 'string',
						'required' => false,
					),
					'js'     => array(
						'type'     => 'string',
						'required' => false,
					),
				),
			)
		);

		// 7. PUT /pages/(?P<id>\d+) - Update page content and disk CSS.
		register_rest_route(
			$namespace,
			'/pages/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( 'Align_Gateway_Pages', 'handle_update_page' ),
				'permission_callback' => $auth_callback,
				'args'                => array(
					'id'     => array(
						'validate_callback' => function( $param ) {
							return is_numeric( $param );
						},
					),
					'title'  => array(
						'type'     => 'string',
						'required' => false,
					),
					'slug'   => array(
						'type'     => 'string',
						'required' => false,
					),
					'status' => array(
						'type'     => 'string',
						'required' => false,
					),
					'html'   => array(
						'type'     => 'string',
						'required' => false,
					),
					'css'    => array(
						'type'     => 'string',
						'required' => false,
					),
					'js'     => array(
						'type'     => 'string',
						'required' => false,
					),
				),
			)
		);

		// 8. POST /pages/(?P<id>\d+)/rollback - Revert page to previous revision.
		register_rest_route(
			$namespace,
			'/pages/(?P<id>\d+)/rollback',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( 'Align_Gateway_Pages', 'handle_rollback_page' ),
				'permission_callback' => $auth_callback,
				'args'                => array(
					'id'          => array(
						'validate_callback' => function( $param ) {
							return is_numeric( $param );
						},
					),
					'revision_id' => array(
						'type'     => 'integer',
						'required' => false,
					),
				),
			)
		);

		// 9. POST /validate - Sandbox dry-run linter & validator.
		register_rest_route(
			$namespace,
			'/validate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( 'Align_Gateway_Validator', 'handle_validate' ),
				'permission_callback' => $auth_callback,
				'args'                => array(
					'html' => array(
						'type'     => 'string',
						'required' => false,
					),
					'css'  => array(
						'type'     => 'string',
						'required' => false,
					),
				),
			)
		);

		// 10. POST /mcp - Model Context Protocol (MCP) JSON-RPC 2.0 endpoint.
		register_rest_route(
			$namespace,
			'/mcp',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( 'Align_Gateway_MCP', 'handle_mcp_request' ),
				'permission_callback' => array( 'Align_Gateway_MCP', 'check_permission' ),
			)
		);

		error_log( sprintf( '[Align Agent] Registered 10 endpoints under namespace %s.', $namespace ) );
	}
}

// Backwards compatibility alias.
if ( ! class_exists( 'Align_Agent_Router' ) ) {
	class_alias( 'Align_Gateway_Router', 'Align_Agent_Router' );
}
