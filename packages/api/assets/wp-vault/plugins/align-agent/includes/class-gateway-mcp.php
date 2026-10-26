<?php
/**
 * Align Agent Gateway - MCP Server Controller
 *
 * Exposes JSON-RPC 2.0 over StreamableHTTP at /wp-json/align-agent/v1/mcp
 * authenticated via WordPress Application Passwords (BasicAuth).
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Gateway_MCP {

	/**
	 * Initialize MCP hooks.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register MCP route if called directly via init().
	 */
	public static function register_routes(): void {
		$namespace = defined( 'ALIGN_AGENT_REST_NS' ) ? ALIGN_AGENT_REST_NS : 'align-agent/v1';
		register_rest_route(
			$namespace,
			'/mcp',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_mcp_request' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
	}

	/**
	 * Check BasicAuth permission using WordPress Application Passwords.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return bool|WP_Error True if authenticated, WP_Error on failure.
	 */
	public static function check_permission( WP_REST_Request $request ) {
		// Force enable application passwords availability on local HTTP environments.
		add_filter( 'wp_is_application_passwords_available', '__return_true' );

		$username = '';
		$password = '';

		$auth = (string) $request->get_header( 'authorization' );
		if ( '' === $auth && ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$auth = (string) $_SERVER['HTTP_AUTHORIZATION'];
		}
		if ( '' === $auth && ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			$auth = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
		}

		if ( '' !== $auth && 0 === stripos( trim( $auth ), 'Basic ' ) ) {
			$encoded = trim( substr( trim( $auth ), 6 ) );
			$decoded = base64_decode( $encoded, true );
			if ( false !== $decoded && false !== strpos( $decoded, ':' ) ) {
				list( $username, $password ) = explode( ':', $decoded, 2 );
			}
		} elseif ( isset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] ) ) {
			$username = (string) $_SERVER['PHP_AUTH_USER'];
			$password = (string) $_SERVER['PHP_AUTH_PW'];
		}

		$username = trim( $username );
		$password = trim( $password );

		if ( '' === $username || '' === $password ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Missing Basic Authorization credentials.', 'align-agent' ),
				array( 'status' => 401 )
			);
		}

		$cache_key      = 'align_mcp_auth_' . substr( hash( 'sha256', $username . ':' . $password ), 0, 32 );
		$cached_user_id = (int) get_transient( $cache_key );
		if ( $cached_user_id > 0 ) {
			$cached_user = get_user_by( 'id', $cached_user_id );
			if ( $cached_user instanceof WP_User && ( user_can( $cached_user, 'manage_options' ) || user_can( $cached_user, 'edit_pages' ) ) ) {
				wp_set_current_user( $cached_user_id );
				return true;
			}
		}

		$user = wp_authenticate_application_password( null, $username, $password );

		if ( ! ( $user instanceof WP_User ) && class_exists( 'WP_Application_Passwords' ) ) {
			$target_user = get_user_by( 'login', $username );
			if ( ! $target_user && is_email( $username ) ) {
				$target_user = get_user_by( 'email', $username );
			}
			if ( $target_user instanceof WP_User ) {
				$clean_pass = preg_replace( '/[^a-z\d]/i', '', $password );
				$passwords  = WP_Application_Passwords::get_user_application_passwords( $target_user->ID );
				foreach ( $passwords as $item ) {
					if ( ! empty( $item['password'] ) && wp_check_password( $clean_pass, $item['password'], $target_user->ID ) ) {
						$user = $target_user;
						break;
					}
				}
			}
		}

		if ( is_wp_error( $user ) || ! ( $user instanceof WP_User ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid Application Password.', 'align-agent' ),
				array( 'status' => 401 )
			);
		}

		if ( ! user_can( $user, 'manage_options' ) && ! user_can( $user, 'edit_pages' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Insufficient user capabilities for MCP access.', 'align-agent' ),
				array( 'status' => 403 )
			);
		}

		set_transient( $cache_key, (int) $user->ID, 300 );
		wp_set_current_user( $user->ID );
		return true;
	}

	/**
	 * Handle incoming JSON-RPC 2.0 MCP request.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response JSON-RPC 2.0 response.
	 */
	public static function handle_mcp_request( WP_REST_Request $request ): WP_REST_Response {
		$raw_body = $request->get_body();
		$body     = json_decode( $raw_body, true );

		if ( ! is_array( $body ) ) {
			return new WP_REST_Response(
				array(
					'jsonrpc' => '2.0',
					'id'      => null,
					'error'   => array(
						'code'    => -32700,
						'message' => 'Parse error',
					),
				),
				200
			);
		}

		$method = isset( $body['method'] ) ? (string) $body['method'] : '';
		$params = isset( $body['params'] ) && is_array( $body['params'] ) ? $body['params'] : array();

		// JSON-RPC 2.0 notification (no id field present, e.g. notifications/initialized).
		if ( ! array_key_exists( 'id', $body ) ) {
			return new WP_REST_Response( (object) array(), 200 );
		}

		$id = $body['id'];

		if ( 'initialize' === $method ) {
			return new WP_REST_Response(
				array(
					'jsonrpc' => '2.0',
					'id'      => $id,
					'result'  => array(
						'protocolVersion' => '2024-11-05',
						'capabilities'    => array(
							'tools' => (object) array(),
						),
						'serverInfo'      => array(
							'name'    => 'align-agent-mcp',
							'version' => defined( 'ALIGN_AGENT_VERSION' ) ? ALIGN_AGENT_VERSION : '1.0.0',
						),
						'instructions'    => class_exists( 'Align_Agent_Loop' ) ? Align_Agent_Loop::build_system_prompt() : '',
					),
				),
				200
			);
		}

		if ( 'ping' === $method ) {
			return new WP_REST_Response(
				array(
					'jsonrpc' => '2.0',
					'id'      => $id,
					'result'  => (object) array(),
				),
				200
			);
		}

		if ( 'tools/list' === $method ) {
			return new WP_REST_Response(
				array(
					'jsonrpc' => '2.0',
					'id'      => $id,
					'result'  => array(
						'tools' => self::get_tools_schema(),
					),
				),
				200
			);
		}

		if ( 'tools/call' === $method ) {
			$tool_name = isset( $params['name'] ) ? (string) $params['name'] : '';
			$args      = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();

			// Priority 1: Dispatch via Align_Agent_Tool_Registry if tool is registered.
			$target_tool_name = $tool_name;
			if ( 'update_page' === $target_tool_name && class_exists( 'Align_Agent_Tool_Registry' ) && Align_Agent_Tool_Registry::get_tool( 'save_page' ) ) {
				$target_tool_name = 'save_page';
			}

			if ( class_exists( 'Align_Agent_Tool_Registry' ) ) {
				$tool_def = Align_Agent_Tool_Registry::get_tool( $target_tool_name );
				if ( $tool_def && empty( $tool_def['hidden'] ) ) {
					if ( ! empty( $tool_def['requires_builder'] ) && ! Align_Agent_Tool_Registry::is_builder_available() ) {
						$err_res = array(
							'success' => false,
							'error'   => 'builder_unavailable',
							'message' => __( 'Align Theme Builder is not active. This operation is disabled.', 'align-agent' ),
						);
						return new WP_REST_Response(
							array(
								'jsonrpc' => '2.0',
								'id'      => $id,
								'result'  => array(
									'isError' => true,
									'content' => array(
										array(
											'type' => 'text',
											'text' => (string) wp_json_encode( $err_res ),
										),
									),
								),
							),
							200
						);
					}

					if ( ! empty( $tool_def['capability'] ) && ! current_user_can( $tool_def['capability'] ) ) {
						$err_res = array(
							'success' => false,
							'error'   => 'forbidden',
							'message' => __( 'You do not have permission to execute this tool.', 'align-agent' ),
						);
						return new WP_REST_Response(
							array(
								'jsonrpc' => '2.0',
								'id'      => $id,
								'result'  => array(
									'isError' => true,
									'content' => array(
										array(
											'type' => 'text',
											'text' => (string) wp_json_encode( $err_res ),
										),
									),
								),
							),
							200
						);
					}

					// Auto-alias id <-> post_id for convenience across MCP clients.
					if ( isset( $args['id'] ) && ! isset( $args['post_id'] ) ) {
						$args['post_id'] = $args['id'];
					} elseif ( isset( $args['post_id'] ) && ! isset( $args['id'] ) ) {
						$args['id'] = $args['post_id'];
					}
					if ( 'list_pages' === $target_tool_name && isset( $args['s'] ) && ! isset( $args['search'] ) ) {
						$args['search'] = $args['s'];
					}

					if ( Align_Agent_Tool_Registry::is_write_tool( $target_tool_name ) && class_exists( 'Align_Agent_Write_Executor' ) ) {
						$exec_result = Align_Agent_Write_Executor::run( $target_tool_name, $args, get_current_user_id(), 'mcp' );
					} else {
						$exec_result = Align_Agent_Tool_Registry::execute( $target_tool_name, $args );
					}

					$is_error = isset( $exec_result['success'] ) && false === $exec_result['success'];

					return new WP_REST_Response(
						array(
							'jsonrpc' => '2.0',
							'id'      => $id,
							'result'  => array(
								'isError' => $is_error,
								'content' => array(
									array(
										'type' => 'text',
										'text' => (string) wp_json_encode( $exec_result ),
									),
								),
							),
						),
						200
					);
				}
			}

			// Priority 2: Gateway Fallback (ping, get_context, update_page, etc.).
			$sub_req = new WP_REST_Request( 'POST', '/align-agent/v1/mcp' );
			foreach ( $args as $k => $v ) {
				$sub_req->set_param( $k, $v );
			}

			switch ( $tool_name ) {
				case 'ping':
					$res = Align_Gateway_System::handle_ping( $sub_req );
					break;
				case 'list_pages':
					$res = Align_Gateway_Pages::handle_get_pages( $sub_req );
					break;
				case 'get_page':
					$res = Align_Gateway_Pages::handle_get_page( $sub_req );
					break;
				case 'create_page':
					$res = Align_Gateway_Pages::handle_create_page( $sub_req );
					break;
				case 'update_page':
					$res = Align_Gateway_Pages::handle_update_page( $sub_req );
					break;
				case 'get_context':
					$res = Align_Gateway_Context::handle_get_context( $sub_req );
					break;
				default:
					return new WP_REST_Response(
						array(
							'jsonrpc' => '2.0',
							'id'      => $id,
							'error'   => array(
								'code'    => -32601,
								'message' => sprintf( 'Method/Tool not found: %s', $tool_name ),
							),
						),
						200
					);
			}

			if ( is_wp_error( $res ) ) {
				$error_payload = array(
					'error'   => $res->get_error_code(),
					'message' => $res->get_error_message(),
				);
				return new WP_REST_Response(
					array(
						'jsonrpc' => '2.0',
						'id'      => $id,
						'result'  => array(
							'isError' => true,
							'content' => array(
								array(
									'type' => 'text',
									'text' => (string) wp_json_encode( $error_payload ),
								),
							),
						),
					),
					200
				);
			}

			$data = ( $res instanceof WP_REST_Response ) ? $res->get_data() : $res;

			return new WP_REST_Response(
				array(
					'jsonrpc' => '2.0',
					'id'      => $id,
					'result'  => array(
						'isError' => false,
						'content' => array(
							array(
								'type' => 'text',
								'text' => (string) wp_json_encode( $data ),
							),
						),
					),
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'error'   => array(
					'code'    => -32601,
					'message' => sprintf( 'Method not found: %s', $method ),
				),
			),
			200
		);
	}

	/**
	 * Return the schema definitions for all registered Align Agent MCP tools.
	 *
	 * @return array List of MCP tool schemas.
	 */
	public static function get_tools_schema(): array {
		$tools      = array();
		$registered = array();

		if ( class_exists( 'Align_Agent_Tool_Registry' ) ) {
			$builder_active = Align_Agent_Tool_Registry::is_builder_available();
			foreach ( Align_Agent_Tool_Registry::get_all() as $tool ) {
				if ( ! empty( $tool['hidden'] ) ) {
					continue;
				}
				if ( ! empty( $tool['requires_builder'] ) && ! $builder_active ) {
					continue;
				}
				if ( ! empty( $tool['capability'] ) && ! current_user_can( $tool['capability'] ) ) {
					continue;
				}

				$input_schema = isset( $tool['parameters'] ) && is_array( $tool['parameters'] )
					? $tool['parameters']
					: array(
						'type'       => 'object',
						'properties' => new stdClass(),
					);

				if ( isset( $input_schema['properties'] ) && is_array( $input_schema['properties'] ) && empty( $input_schema['properties'] ) ) {
					$input_schema['properties'] = new stdClass();
				}

				$desc = preg_replace( '/\s*Requires user approval\.?/i', '', (string) ( $tool['description'] ?? '' ) );

				$tools[]                         = array(
					'name'        => (string) $tool['name'],
					'description' => trim( (string) $desc ),
					'inputSchema' => $input_schema,
				);
				$registered[ $tool['name'] ] = true;
			}
		}

		$gateway_extras = array(
			array(
				'name'        => 'ping',
				'description' => 'Health check and site system info.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => (object) array(),
				),
			),
			array(
				'name'        => 'get_context',
				'description' => 'Get site CSS context, active frameworks, CSS variables, and design tokens.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => (object) array(),
				),
			),
			array(
				'name'        => 'update_page',
				'description' => 'Update an existing WordPress page by ID (alias for save_page).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array( 'type' => 'integer', 'description' => 'Numeric WordPress page ID.' ),
						'post_id' => array( 'type' => 'integer', 'description' => 'Numeric WordPress page ID.' ),
						'title'   => array( 'type' => 'string', 'description' => 'Optional new page title.' ),
						'html'    => array( 'type' => 'string', 'description' => 'Clean body HTML without <script> tags.' ),
						'css'     => array( 'type' => 'string', 'description' => 'Optional scoped page CSS.' ),
						'js'      => array( 'type' => 'string', 'description' => 'Optional scoped page JavaScript.' ),
						'status'  => array( 'type' => 'string', 'description' => 'Optional post status: draft, publish, pending, or private.' ),
					),
				),
			),
		);

		foreach ( $gateway_extras as $extra ) {
			if ( empty( $registered[ $extra['name'] ] ) ) {
				$tools[] = $extra;
			}
		}

		return $tools;
	}
}
