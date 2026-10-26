<?php
/**
 * Align Agent Gateway - OpenAI-Compatible AI Provider Controller
 *
 * Provides chat completion client, API configuration storage, and connection verification
 * for OpenAI-compatible endpoints (e.g. https://api.lsia.site/v1/chat/completions).
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Gateway_AI {

	/**
	 * Option keys.
	 */
	const ENDPOINT_OPTION = 'align_agent_ai_endpoint';
	const KEY_OPTION      = 'align_agent_ai_api_key';
	const MODEL_OPTION    = 'align_agent_ai_model';

	/**
	 * Default fallback values.
	 */
	const DEFAULT_ENDPOINT = 'https://api.lsia.site/v1/chat/completions';
	const DEFAULT_MODEL    = 'gpt-4o';

	/**
	 * Initialize AI gateway component.
	 */
	public static function init(): void {
		// Ready for future filters or scheduled operations.
	}

	/**
	 * Retrieve AI endpoint URL.
	 * Checks wp-config constant override first.
	 *
	 * @return string
	 */
	public static function get_endpoint(): string {
		if ( defined( 'ALIGN_AGENT_AI_ENDPOINT' ) && '' !== trim( (string) ALIGN_AGENT_AI_ENDPOINT ) ) {
			return trim( (string) ALIGN_AGENT_AI_ENDPOINT );
		}

		$endpoint = get_option( self::ENDPOINT_OPTION, self::DEFAULT_ENDPOINT );
		return ! empty( $endpoint ) ? trim( (string) $endpoint ) : self::DEFAULT_ENDPOINT;
	}

	/**
	 * Retrieve AI API key.
	 * Checks wp-config constant override first.
	 *
	 * @return string
	 */
	public static function get_api_key(): string {
		if ( defined( 'ALIGN_AGENT_AI_API_KEY' ) && '' !== trim( (string) ALIGN_AGENT_AI_API_KEY ) ) {
			return trim( (string) ALIGN_AGENT_AI_API_KEY );
		}

		return trim( (string) get_option( self::KEY_OPTION, '' ) );
	}

	/**
	 * Retrieve default AI model.
	 * Checks wp-config constant override first.
	 *
	 * @return string
	 */
	public static function get_model(): string {
		if ( defined( 'ALIGN_AGENT_AI_MODEL' ) && '' !== trim( (string) ALIGN_AGENT_AI_MODEL ) ) {
			return trim( (string) ALIGN_AGENT_AI_MODEL );
		}

		$model = get_option( self::MODEL_OPTION, self::DEFAULT_MODEL );
		return ! empty( $model ) ? trim( (string) $model ) : self::DEFAULT_MODEL;
	}

	/**
	 * Check whether AI API key is configured.
	 *
	 * @return bool
	 */
	public static function is_configured(): bool {
		$key = self::get_api_key();
		return ! empty( $key );
	}

	/**
	 * Normalize messages array to support standard roles, assistant tool_calls, and tool outputs.
	 *
	 * @param array<int, mixed> $messages Raw messages array.
	 * @return array<int, array<string, mixed>>
	 */
	private static function normalize_messages( array $messages ): array {
		$normalized = array();
		foreach ( $messages as $msg ) {
			if ( ! is_array( $msg ) || empty( $msg['role'] ) ) {
				continue;
			}
			$role = (string) $msg['role'];
			if ( 'assistant' === $role && ! empty( $msg['tool_calls'] ) && is_array( $msg['tool_calls'] ) ) {
				$entry = array(
					'role'       => 'assistant',
					'content'    => isset( $msg['content'] ) && '' !== (string) $msg['content'] ? (string) $msg['content'] : null,
					'tool_calls' => array_values( $msg['tool_calls'] ),
				);
				$normalized[] = $entry;
			} elseif ( 'tool' === $role ) {
				$entry = array(
					'role'         => 'tool',
					'tool_call_id' => isset( $msg['tool_call_id'] ) ? (string) $msg['tool_call_id'] : '',
					'content'      => isset( $msg['content'] ) ? (string) $msg['content'] : '',
				);
				if ( ! empty( $msg['name'] ) ) {
					$entry['name'] = (string) $msg['name'];
				}
				$normalized[] = $entry;
			} else {
				$normalized[] = array(
					'role'    => $role,
					'content' => isset( $msg['content'] ) ? (string) $msg['content'] : '',
				);
			}
		}
		return $normalized;
	}

	/**
	 * Send OpenAI-compatible chat completion request.
	 *
	 * @param array<int, array<string, mixed>> $messages Array of message objects.
	 * @param array<string, mixed>             $options  Optional overrides (endpoint, api_key, model, temperature, max_tokens, tools, tool_choice, timeout).
	 * @return array{success: bool, content: string, tool_calls: array, finish_reason: string, message: string, raw: array}
	 */
	public static function chat_completion( array $messages, array $options = array() ): array {
		$endpoint = ! empty( $options['endpoint'] ) ? trim( (string) $options['endpoint'] ) : self::get_endpoint();
		$api_key  = ! empty( $options['api_key'] ) ? trim( (string) $options['api_key'] ) : self::get_api_key();
		$model    = ! empty( $options['model'] ) ? trim( (string) $options['model'] ) : self::get_model();

		if ( empty( $api_key ) ) {
			error_log( '[Align Agent] AI Chat Completion failed: API key is not configured.' );
			return array(
				'success'       => false,
				'content'       => '',
				'tool_calls'    => array(),
				'finish_reason' => '',
				'message'       => __( 'AI API key is not configured.', 'align-agent' ),
				'raw'           => array(),
			);
		}

		if ( empty( $endpoint ) ) {
			error_log( '[Align Agent] AI Chat Completion failed: Endpoint is empty.' );
			return array(
				'success'       => false,
				'content'       => '',
				'tool_calls'    => array(),
				'finish_reason' => '',
				'message'       => __( 'AI endpoint is empty.', 'align-agent' ),
				'raw'           => array(),
			);
		}

		$payload = array(
			'model'    => $model,
			'messages' => self::normalize_messages( $messages ),
		);

		if ( isset( $options['temperature'] ) ) {
			$payload['temperature'] = (float) $options['temperature'];
		}

		if ( isset( $options['max_tokens'] ) && (int) $options['max_tokens'] > 0 ) {
			$payload['max_tokens'] = (int) $options['max_tokens'];
		}

		if ( ! empty( $options['tools'] ) && is_array( $options['tools'] ) ) {
			$payload['tools']       = array_values( $options['tools'] );
			$payload['tool_choice'] = ! empty( $options['tool_choice'] ) ? $options['tool_choice'] : 'auto';
		}

		$timeout      = isset( $options['timeout'] ) && (int) $options['timeout'] > 0 ? (int) $options['timeout'] : 60;
		$use_stream   = ! empty( $options['stream'] ) && function_exists( 'curl_init' ) && class_exists( 'Align_Gateway_AI_Stream' );
		$idle_timeout = isset( $options['idle_timeout'] ) && (int) $options['idle_timeout'] > 0 ? (int) $options['idle_timeout'] : 45;

		if ( $use_stream ) {
			return Align_Gateway_AI_Stream::request(
				$endpoint,
				$api_key,
				$payload,
				array(
					'timeout'      => $timeout,
					'idle_timeout' => $idle_timeout,
				)
			);
		}

		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
			'body'    => wp_json_encode( $payload ),
			'timeout' => $timeout,
		);

		$response = wp_remote_post( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			$err_msg = $response->get_error_message();
			error_log( sprintf( '[Align Agent] AI Chat Completion request failed: %s', $err_msg ) );
			return array(
				'success'       => false,
				'content'       => '',
				'tool_calls'    => array(),
				'finish_reason' => '',
				'message'       => sprintf( __( 'AI request failed: %s', 'align-agent' ), $err_msg ),
				'raw'           => array(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			$err_detail = isset( $data['error']['message'] ) ? $data['error']['message'] : ( 'HTTP ' . $code );
			error_log( sprintf( '[Align Agent] AI Chat Completion error (HTTP %d): %s', $code, $err_detail ) );
			return array(
				'success'       => false,
				'content'       => '',
				'tool_calls'    => array(),
				'finish_reason' => '',
				'message'       => sprintf( __( 'AI Provider error (HTTP %1$d): %2$s', 'align-agent' ), $code, $err_detail ),
				'raw'           => is_array( $data ) ? $data : array( 'body' => $body ),
			);
		}

		$choice        = isset( $data['choices'][0] ) && is_array( $data['choices'][0] ) ? $data['choices'][0] : array();
		$msg_obj       = isset( $choice['message'] ) && is_array( $choice['message'] ) ? $choice['message'] : array();
		$content       = isset( $msg_obj['content'] ) && null !== $msg_obj['content'] ? (string) $msg_obj['content'] : '';
		$tool_calls    = isset( $msg_obj['tool_calls'] ) && is_array( $msg_obj['tool_calls'] ) ? array_values( $msg_obj['tool_calls'] ) : array();
		$finish_reason = isset( $choice['finish_reason'] ) ? (string) $choice['finish_reason'] : '';

		error_log( sprintf(
			'[Align Agent] AI Chat Completion succeeded | Model: %s | Response size: %d bytes | Tool calls: %d.',
			$model,
			strlen( $content ),
			count( $tool_calls )
		) );

		return array(
			'success'       => true,
			'content'       => $content,
			'tool_calls'    => $tool_calls,
			'finish_reason' => $finish_reason,
			'message'       => __( 'AI Chat Completion succeeded.', 'align-agent' ),
			'raw'           => is_array( $data ) ? $data : array(),
		);
	}

	/**
	 * Test connection to OpenAI-compatible provider with a test ping prompt.
	 *
	 * @param string $custom_endpoint Optional custom endpoint to test before saving.
	 * @param string $custom_key      Optional custom API key to test before saving.
	 * @param string $custom_model    Optional custom model to test before saving.
	 * @return array{success: bool, content?: string, message: string}
	 */
	public static function test_connection( string $custom_endpoint = '', string $custom_key = '', string $custom_model = '' ): array {
		$endpoint = ! empty( $custom_endpoint ) ? trim( $custom_endpoint ) : self::get_endpoint();
		$key      = ! empty( $custom_key ) ? trim( $custom_key ) : self::get_api_key();
		$model    = ! empty( $custom_model ) ? trim( $custom_model ) : self::get_model();

		if ( empty( $key ) ) {
			return array(
				'success' => false,
				'message' => __( 'API Key is empty. Please enter an API key to test.', 'align-agent' ),
			);
		}

		$messages = array(
			array(
				'role'    => 'user',
				'content' => 'Respond with: Align Agent AI Connected!',
			),
		);

		$result = self::chat_completion(
			$messages,
			array(
				'endpoint'    => $endpoint,
				'api_key'     => $key,
				'model'       => $model,
				'temperature' => 0.2,
				'max_tokens'  => 60,
			)
		);

		if ( $result['success'] ) {
			return array(
				'success' => true,
				'content' => $result['content'],
				'message' => sprintf(
					__( 'AI Provider connected successfully! Response: "%s"', 'align-agent' ),
					esc_html( trim( $result['content'] ) )
				),
			);
		}

		return array(
			'success' => false,
			'message' => $result['message'],
		);
	}

	/**
	 * Verify whether the configured AI model supports OpenAI function/tool calling.
	 *
	 * @param string $custom_endpoint Optional custom endpoint.
	 * @param string $custom_key      Optional custom API key.
	 * @param string $custom_model    Optional custom model.
	 * @return array{success: bool, tool_calls?: array, message: string}
	 */
	public static function test_tool_calling( string $custom_endpoint = '', string $custom_key = '', string $custom_model = '' ): array {
		$endpoint = ! empty( $custom_endpoint ) ? trim( $custom_endpoint ) : self::get_endpoint();
		$key      = ! empty( $custom_key ) ? trim( $custom_key ) : self::get_api_key();
		$model    = ! empty( $custom_model ) ? trim( $custom_model ) : self::get_model();

		if ( empty( $key ) ) {
			return array(
				'success' => false,
				'message' => __( 'API Key is empty. Please enter an API key to test tool calling.', 'align-agent' ),
			);
		}

		$tools = array(
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'ping_cms_status',
					'description' => 'Check the current CMS health status for a given target module.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'module' => array(
								'type'        => 'string',
								'description' => 'Module name to check, e.g. pages.',
							),
						),
						'required'   => array( 'module' ),
					),
				),
			),
		);

		$messages = array(
			array(
				'role'    => 'user',
				'content' => 'Call the ping_cms_status tool for the module "pages".',
			),
		);

		$result = self::chat_completion(
			$messages,
			array(
				'endpoint'    => $endpoint,
				'api_key'     => $key,
				'model'       => $model,
				'temperature' => 0.0,
				'max_tokens'  => 120,
				'tools'       => $tools,
				'tool_choice' => 'auto',
			)
		);

		if ( ! $result['success'] ) {
			return array(
				'success' => false,
				'message' => $result['message'],
			);
		}

		if ( ! empty( $result['tool_calls'] ) ) {
			$fn_name = $result['tool_calls'][0]['function']['name'] ?? 'unknown';
			return array(
				'success'    => true,
				'tool_calls' => $result['tool_calls'],
				'message'    => sprintf(
					__( 'Tool calling verified! Model invoked function "%s".', 'align-agent' ),
					esc_html( (string) $fn_name )
				),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Connected to AI Provider, but the model did not return any tool_calls. Choose a model that supports OpenAI Function Calling.', 'align-agent' ),
		);
	}

	/**
	 * REST API endpoint handler for POST /align-agent/v1/ai/completion.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response
	 */
	public static function handle_rest_ai_completion( WP_REST_Request $request ): WP_REST_Response {
		$messages = $request->get_param( 'messages' );
		$prompt   = $request->get_param( 'prompt' );

		if ( empty( $messages ) && empty( $prompt ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Either "messages" array or "prompt" string is required.', 'align-agent' ),
				),
				400
			);
		}

		if ( empty( $messages ) && ! empty( $prompt ) ) {
			$messages = array(
				array(
					'role'    => 'user',
					'content' => sanitize_text_field( (string) $prompt ),
				),
			);
		} elseif ( ! is_array( $messages ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( '"messages" parameter must be an array.', 'align-agent' ),
				),
				400
			);
		}

		$options = array();
		if ( $request->get_param( 'model' ) ) {
			$options['model'] = sanitize_text_field( (string) $request->get_param( 'model' ) );
		}
		if ( null !== $request->get_param( 'temperature' ) ) {
			$options['temperature'] = (float) $request->get_param( 'temperature' );
		}
		if ( null !== $request->get_param( 'max_tokens' ) ) {
			$options['max_tokens'] = (int) $request->get_param( 'max_tokens' );
		}
		if ( is_array( $request->get_param( 'tools' ) ) ) {
			$options['tools'] = $request->get_param( 'tools' );
		}
		if ( null !== $request->get_param( 'tool_choice' ) ) {
			$options['tool_choice'] = $request->get_param( 'tool_choice' );
		}

		$result = self::chat_completion( $messages, $options );
		$status = $result['success'] ? 200 : 400;

		return new WP_REST_Response( $result, $status );
	}
}
