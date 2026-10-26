<?php
/**
 * Align Agent - Tool Registry for Chatbox Function Calling
 *
 * Manages registration, OpenAI tool schema generation, read/write classification,
 * and callback execution for Align Agent chat tools.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tool_Registry {

	/**
	 * Registered tool definitions keyed by tool name.
	 *
	 * @var array<string, array{name: string, type: string, requires_builder: bool, capability: string, group: string, danger: bool, description: string, parameters: array, callback: callable, summary_callback?: callable}>
	 */
	private static array $tools = array();

	/**
	 * Initialize tool registry hooks.
	 */
	public static function init(): void {
		// Ready for core and third-party tool registrations.
	}

	/**
	 * Register a tool with its OpenAI JSON Schema and execution callback.
	 *
	 * @param string   $name       Unique tool function name (e.g. list_pages).
	 * @param array    $definition Tool definition containing type ('read'|'write'), description, parameters, callback, and optional summary_callback.
	 * @return bool
	 */
	public static function register( string $name, array $definition ): bool {
		$clean_name = sanitize_key( $name );
		if ( empty( $clean_name ) || empty( $definition['callback'] ) || ! is_callable( $definition['callback'] ) ) {
			error_log( sprintf( '[Align Agent] Tool registration failed: invalid name or callback for "%s".', $name ) );
			return false;
		}

		$type             = isset( $definition['type'] ) && 'write' === $definition['type'] ? 'write' : 'read';
		$requires_builder = ! empty( $definition['requires_builder'] );
		$capability       = ! empty( $definition['capability'] ) ? (string) $definition['capability'] : 'manage_options';
		$group            = ! empty( $definition['group'] ) ? (string) $definition['group'] : 'content';
		$danger           = ! empty( $definition['danger'] );

		if ( ! empty( $definition['scope'] ) && in_array( $definition['scope'], array( 'global', 'local' ), true ) ) {
			$scope = $definition['scope'];
		} else {
			$global_names = array( 'save_template', 'create_reuse_block', 'save_reuse_block', 'save_global_code', 'set_homepage', 'update_site_identity', 'apply_page_clone' );
			$scope        = ( $danger || 'users' === $group || in_array( $clean_name, $global_names, true ) ) ? 'global' : 'local';
		}

		$params = isset( $definition['parameters'] ) && is_array( $definition['parameters'] )
			? $definition['parameters']
			: array(
				'type'       => 'object',
				'properties' => new stdClass(),
			);

		if ( isset( $params['properties'] ) && is_array( $params['properties'] ) && empty( $params['properties'] ) ) {
			$params['properties'] = new stdClass();
		}

		$undo = isset( $definition['undo'] ) && is_array( $definition['undo'] ) ? $definition['undo'] : array();

		self::$tools[ $clean_name ] = array(
			'name'             => $clean_name,
			'type'             => $type,
			'requires_builder' => $requires_builder,
			'capability'       => $capability,
			'group'            => $group,
			'scope'            => $scope,
			'danger'           => $danger,
			'hidden'           => ! empty( $definition['hidden'] ),
			'description'      => isset( $definition['description'] ) ? (string) $definition['description'] : '',
			'parameters'       => $params,
			'undo'             => $undo,
			'callback'         => $definition['callback'],
			'summary_callback' => isset( $definition['summary_callback'] ) && is_callable( $definition['summary_callback'] ) ? $definition['summary_callback'] : null,
		);

		return true;
	}

	/**
	 * Check if Align Builder CRUD Handler is active on the current site.
	 *
	 * @return bool
	 */
	public static function is_builder_available(): bool {
		return class_exists( 'Align_Builder_Crud_Handler' );
	}

	/**
	 * Retrieve a registered tool definition by name.
	 *
	 * @param string $name Tool name.
	 * @return array|null
	 */
	public static function get_tool( string $name ): ?array {
		return self::$tools[ $name ] ?? null;
	}

	/**
	 * Retrieve all registered tool definitions.
	 *
	 * @return array<string, array>
	 */
	public static function get_all(): array {
		return self::$tools;
	}

	/**
	 * Retrieve undo specification for a registered tool.
	 *
	 * @param string $tool Tool name.
	 * @return array
	 */
	public static function get_undo_spec( string $tool ): array {
		$def = self::get_tool( $tool );
		return ( $def && ! empty( $def['undo'] ) && is_array( $def['undo'] ) ) ? $def['undo'] : array();
	}

	/**
	 * Retrieve the scope ('global' or 'local') of a registered tool.
	 *
	 * @param string $name Tool name.
	 * @return string
	 */
	public static function get_tool_scope( string $name ): string {
		$tool = self::get_tool( $name );
		return $tool['scope'] ?? 'local';
	}

	/**
	 * Redact sensitive credential keys from arguments for logging.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 */
	public static function redact_args( array $args ): array {
		$redacted = $args;
		foreach ( $redacted as $k => $v ) {
			if ( is_array( $v ) ) {
				$redacted[ $k ] = self::redact_args( $v );
			} elseif ( is_string( $k ) && preg_match( '/^(?:password|pass|secret|token|key)$/i', $k ) ) {
				$redacted[ $k ] = '***';
			}
		}
		return $redacted;
	}

	/**
	 * Check whether a tool is classified as a write operation requiring user approval.
	 *
	 * @param string $name Tool name.
	 * @return bool
	 */
	public static function is_write_tool( string $name ): bool {
		$tool = self::get_tool( $name );
		return $tool ? ( 'write' === $tool['type'] ) : false;
	}

	/**
	 * Build the OpenAI-compatible tools array for chat_completion payloads.
	 * Automatically hides tools that require Align Builder when it is not available.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_openai_tools_schema(): array {
		$builder_active = self::is_builder_available();
		$schemas        = array();

		foreach ( self::$tools as $tool ) {
			if ( ! empty( $tool['requires_builder'] ) && ! $builder_active ) {
				continue;
			}

			if ( ! empty( $tool['hidden'] ) ) {
				continue;
			}

			$schemas[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => $tool['name'],
					'description' => $tool['description'],
					'parameters'  => $tool['parameters'],
				),
			);
		}

		return $schemas;
	}

	/**
	 * Build a human-readable approval summary for a pending write tool call.
	 *
	 * @param string $name Tool name.
	 * @param array  $args Parsed tool arguments.
	 * @return array{title: string, description: string, preview_html: string, preview_css: string, meta: array, danger: bool}
	 */
	public static function summarize_write_action( string $name, array $args ): array {
		$tool        = self::get_tool( $name );
		$tool_danger = ! empty( $tool['danger'] );

		if ( $tool && ! empty( $tool['summary_callback'] ) ) {
			try {
				$custom = call_user_func( $tool['summary_callback'], $args );
				if ( is_array( $custom ) ) {
					if ( ! isset( $custom['danger'] ) ) {
						$custom['danger'] = $tool_danger;
					}
					$summary = wp_parse_args(
						$custom,
						array(
							'title'        => $name,
							'description'  => '',
							'preview_html' => '',
							'preview_css'  => '',
							'preview_js'   => isset( $args['js'] ) ? (string) $args['js'] : '',
							'js_warnings'  => array(),
							'meta'         => array(),
							'danger'       => $tool_danger,
						)
					);
					if ( empty( $summary['preview_js'] ) && isset( $args['js'] ) && '' !== (string) $args['js'] ) {
						$summary['preview_js'] = (string) $args['js'];
					}
					if ( ! empty( $summary['preview_js'] ) && empty( $summary['js_warnings'] ) && class_exists( 'Align_Agent_Content_Guard' ) ) {
						$check = Align_Agent_Content_Guard::check_js( (string) $summary['preview_js'] );
						$summary['js_warnings'] = ! empty( $check['warnings'] ) ? $check['warnings'] : array();
						if ( ! empty( $summary['js_warnings'] ) ) {
							error_log( sprintf( '[Align Agent] JS guard: %d warnings for %s.', count( $summary['js_warnings'] ), $name ) );
						}
					}
					return $summary;
				}
			} catch ( Throwable $e ) {
				error_log( sprintf( '[Align Agent] Tool summary callback error for "%s": %s', $name, $e->getMessage() ) );
			}
		}

		$preview_js  = isset( $args['js'] ) ? (string) $args['js'] : '';
		$js_warnings = array();
		if ( '' !== $preview_js && class_exists( 'Align_Agent_Content_Guard' ) ) {
			$check       = Align_Agent_Content_Guard::check_js( $preview_js );
			$js_warnings = ! empty( $check['warnings'] ) ? $check['warnings'] : array();
			if ( ! empty( $js_warnings ) ) {
				error_log( sprintf( '[Align Agent] JS guard: %d warnings for %s.', count( $js_warnings ), $name ) );
			}
		}

		return array(
			'title'        => sprintf( __( 'Confirm %s', 'align-agent' ), $name ),
			'description'  => sprintf( __( 'Execute write tool "%s".', 'align-agent' ), $name ),
			'preview_html' => isset( $args['html'] ) ? (string) $args['html'] : '',
			'preview_css'  => isset( $args['css'] ) ? (string) $args['css'] : '',
			'preview_js'   => $preview_js,
			'js_warnings'  => $js_warnings,
			'meta'         => array(),
			'danger'       => $tool_danger,
		);
	}

	/**
	 * Execute a registered tool callback safely.
	 *
	 * @param string $name Tool name.
	 * @param array  $args Associative array of arguments from LLM.
	 * @return array<string, mixed>
	 */
	public static function execute( string $name, array $args = array() ): array {
		$tool = self::get_tool( $name );
		if ( ! $tool ) {
			error_log( sprintf( '[Align Agent] Tool execution failed: unknown tool "%s".', $name ) );
			return array(
				'success' => false,
				'error'   => 'unknown_tool',
				'message' => sprintf( __( 'Tool "%s" is not registered.', 'align-agent' ), $name ),
			);
		}

		if ( ! empty( $tool['capability'] ) && ! current_user_can( $tool['capability'] ) ) {
			return array(
				'success' => false,
				'error'   => 'forbidden',
				'message' => __( 'You do not have permission to execute this tool.', 'align-agent' ),
			);
		}

		if ( ! empty( $tool['requires_builder'] ) && ! self::is_builder_available() ) {
			error_log( sprintf( '[Align Agent] Blocked tool "%s": Align Builder is not active.', $name ) );
			return array(
				'success' => false,
				'error'   => 'builder_unavailable',
				'message' => __( 'Align Theme Builder is not active. This operation is disabled.', 'align-agent' ),
			);
		}

		try {
			$result = call_user_func( $tool['callback'], $args );
			if ( is_wp_error( $result ) ) {
				return array(
					'success' => false,
					'error'   => $result->get_error_code(),
					'message' => $result->get_error_message(),
				);
			}
			return is_array( $result ) ? $result : array( 'success' => true, 'data' => $result );
		} catch ( Throwable $e ) {
			error_log( sprintf( '[Align Agent] Exception in tool "%s": %s', $name, $e->getMessage() ) );
			return array(
				'success' => false,
				'error'   => 'tool_exception',
				'message' => $e->getMessage(),
			);
		}
	}
}
