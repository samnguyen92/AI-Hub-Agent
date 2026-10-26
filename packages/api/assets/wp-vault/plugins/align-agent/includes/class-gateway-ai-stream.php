<?php
/**
 * Align Agent AI Streaming Handler.
 *
 * Implements low-level cURL SSE streaming for AI chat completions.
 * Aggregates content and tool calls incrementally to prevent gateway timeouts.
 *
 * @package AlignAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Gateway_AI_Stream {

	/**
	 * Perform streaming chat completion request via cURL SSE parser.
	 *
	 * @param string $endpoint AI completion endpoint URL.
	 * @param string $api_key  Bearer authorization API key.
	 * @param array  $payload  JSON payload for chat completion.
	 * @param array  $limits   Request limits (timeout, idle_timeout).
	 * @return array Normalized response matching chat_completion format.
	 */
	public static function request( string $endpoint, string $api_key, array $payload, array $limits = array() ): array {
		if ( ! function_exists( 'curl_init' ) ) {
			return array(
				'success'       => false,
				'content'       => '',
				'tool_calls'    => array(),
				'finish_reason' => '',
				'message'       => __( 'cURL extension is required for AI streaming.', 'align-agent' ),
				'raw'           => array(),
			);
		}

		$payload['stream'] = true;

		$timeout      = isset( $limits['timeout'] ) && (int) $limits['timeout'] > 0 ? (int) $limits['timeout'] : 90;
		$idle_timeout = isset( $limits['idle_timeout'] ) && (int) $limits['idle_timeout'] > 0 ? (int) $limits['idle_timeout'] : 45;

		$t0               = microtime( true );
		$ttfb_ms          = 0;
		$chunk_count      = 0;
		$total_bytes      = 0;
		$tool_args_bytes  = 0;
		$content_acc      = '';
		$tool_calls_map   = array();
		$finish_reason    = '';
		$error_message    = '';
		$raw_non_sse_body = '';
		$is_sse           = null;
		$line_buffer      = '';

		$ch = curl_init();

		$headers = array(
			'Authorization: Bearer ' . $api_key,
			'Content-Type: application/json',
			'Accept: text/event-stream, application/json',
		);

		curl_setopt( $ch, CURLOPT_URL, $endpoint );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $payload ) );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 15 );
		curl_setopt( $ch, CURLOPT_LOW_SPEED_LIMIT, 1 );
		curl_setopt( $ch, CURLOPT_LOW_SPEED_TIME, $idle_timeout );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, false );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );

		curl_setopt(
			$ch,
			CURLOPT_HEADERFUNCTION,
			function ( $curl, $header_line ) use ( &$is_sse ) {
				$len = strlen( $header_line );
				$lc  = strtolower( $header_line );
				if ( 0 === strpos( $lc, 'content-type:' ) ) {
					$is_sse = ( false !== strpos( $lc, 'text/event-stream' ) );
				}
				return $len;
			}
		);

		curl_setopt(
			$ch,
			CURLOPT_WRITEFUNCTION,
			function ( $curl, $data ) use (
				&$t0,
				&$ttfb_ms,
				&$chunk_count,
				&$total_bytes,
				&$tool_args_bytes,
				&$content_acc,
				&$tool_calls_map,
				&$finish_reason,
				&$error_message,
				&$raw_non_sse_body,
				&$is_sse,
				&$line_buffer
			) {
				$len          = strlen( $data );
				$total_bytes += $len;
				$chunk_count++;

				if ( 0 === $ttfb_ms ) {
					$ttfb_ms = (int) round( ( microtime( true ) - $t0 ) * 1000 );
				}

				if ( false === $is_sse ) {
					$raw_non_sse_body .= $data;
					return $len;
				}

				$line_buffer .= $data;
				$lines        = explode( "\n", $line_buffer );
				$line_buffer  = array_pop( $lines );

				foreach ( $lines as $raw_line ) {
					$line = trim( $raw_line );
					if ( '' === $line ) {
						continue;
					}

					// SSE comment or keep-alive (e.g. ": OPENROUTER PROCESSING").
					if ( 0 === strpos( $line, ':' ) ) {
						continue;
					}

					if ( 0 === strpos( $line, 'data:' ) ) {
						$json_str = trim( substr( $line, 5 ) );

						if ( '[DONE]' === $json_str ) {
							continue;
						}

						$chunk = json_decode( $json_str, true );
						if ( ! is_array( $chunk ) ) {
							continue;
						}

						// Handle stream inline error objects.
						if ( isset( $chunk['error'] ) ) {
							$error_message = is_array( $chunk['error'] ) && isset( $chunk['error']['message'] )
								? (string) $chunk['error']['message']
								: (string) wp_json_encode( $chunk['error'] );
							continue;
						}

						$choice = isset( $chunk['choices'][0] ) && is_array( $chunk['choices'][0] ) ? $chunk['choices'][0] : null;
						if ( ! $choice ) {
							continue;
						}

						if ( ! empty( $choice['finish_reason'] ) ) {
							$finish_reason = (string) $choice['finish_reason'];
						}

						$delta = isset( $choice['delta'] ) && is_array( $choice['delta'] ) ? $choice['delta'] : array();

						if ( isset( $delta['content'] ) && null !== $delta['content'] ) {
							$content_acc .= (string) $delta['content'];
						}

						if ( ! empty( $delta['tool_calls'] ) && is_array( $delta['tool_calls'] ) ) {
							foreach ( $delta['tool_calls'] as $tc_delta ) {
								$idx = isset( $tc_delta['index'] ) ? (int) $tc_delta['index'] : count( $tool_calls_map );
								if ( ! isset( $tool_calls_map[ $idx ] ) ) {
									$tool_calls_map[ $idx ] = array(
										'id'       => '',
										'type'     => 'function',
										'function' => array(
											'name'      => '',
											'arguments' => '',
										),
									);
								}

								if ( ! empty( $tc_delta['id'] ) ) {
									$tool_calls_map[ $idx ]['id'] .= (string) $tc_delta['id'];
								}

								if ( ! empty( $tc_delta['function']['name'] ) ) {
									$tool_calls_map[ $idx ]['function']['name'] .= (string) $tc_delta['function']['name'];
								}

								if ( isset( $tc_delta['function']['arguments'] ) && null !== $tc_delta['function']['arguments'] ) {
									$arg_part         = (string) $tc_delta['function']['arguments'];
									$tool_args_bytes += strlen( $arg_part );
									$tool_calls_map[ $idx ]['function']['arguments'] .= $arg_part;
								}
							}
						}
					}
				}

				return $len;
			}
		);

		curl_exec( $ch );

		$curl_errno = curl_errno( $ch );
		$curl_error = curl_error( $ch );
		$http_code  = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		$total_ms = (int) round( ( microtime( true ) - $t0 ) * 1000 );

		error_log(
			sprintf(
				'[Align Agent] AI stream | ttfb_ms: %d | total_ms: %d | bytes: %d | chunks: %d | tool_args_bytes: %d | http_code: %d',
				$ttfb_ms,
				$total_ms,
				$total_bytes,
				$chunk_count,
				$tool_args_bytes,
				$http_code
			)
		);

		if ( 0 !== $curl_errno ) {
			error_log( sprintf( '[Align Agent] AI stream cURL error (%d): %s', $curl_errno, $curl_error ) );
			return array(
				'success'       => false,
				'content'       => '',
				'tool_calls'    => array(),
				'finish_reason' => '',
				'message'       => sprintf( __( 'AI stream connection failed: %s', 'align-agent' ), $curl_error ),
				'raw'           => array( 'curl_errno' => $curl_errno ),
			);
		}

		if ( $http_code < 200 || $http_code >= 300 ) {
			$err_detail = ! empty( $raw_non_sse_body ) ? $raw_non_sse_body : ( 'HTTP ' . $http_code );
			$parsed_err = json_decode( $raw_non_sse_body, true );
			if ( is_array( $parsed_err ) && ! empty( $parsed_err['error']['message'] ) ) {
				$err_detail = $parsed_err['error']['message'];
			}
			error_log( sprintf( '[Align Agent] AI stream HTTP error (%d): %s', $http_code, $err_detail ) );
			return array(
				'success'       => false,
				'content'       => '',
				'tool_calls'    => array(),
				'finish_reason' => '',
				'message'       => sprintf( __( 'AI Provider stream error (HTTP %1$d): %2$s', 'align-agent' ), $http_code, $err_detail ),
				'raw'           => is_array( $parsed_err ) ? $parsed_err : array( 'body' => $raw_non_sse_body ),
			);
		}

		// Fallback parse if the server returned normal application/json non-SSE response.
		if ( false === $is_sse && ! empty( $raw_non_sse_body ) ) {
			$data   = json_decode( $raw_non_sse_body, true );
			$choice = isset( $data['choices'][0] ) && is_array( $data['choices'][0] ) ? $data['choices'][0] : array();
			$msg    = isset( $choice['message'] ) && is_array( $choice['message'] ) ? $choice['message'] : array();

			return array(
				'success'       => true,
				'content'       => isset( $msg['content'] ) ? (string) $msg['content'] : '',
				'tool_calls'    => isset( $msg['tool_calls'] ) && is_array( $msg['tool_calls'] ) ? $msg['tool_calls'] : array(),
				'finish_reason' => isset( $choice['finish_reason'] ) ? (string) $choice['finish_reason'] : 'stop',
				'message'       => '',
				'raw'           => is_array( $data ) ? $data : array(),
			);
		}

		if ( ! empty( $error_message ) ) {
			error_log( sprintf( '[Align Agent] AI stream chunk error: %s', $error_message ) );
			return array(
				'success'       => false,
				'content'       => '',
				'tool_calls'    => array(),
				'finish_reason' => '',
				'message'       => sprintf( __( 'AI stream failed: %s', 'align-agent' ), $error_message ),
				'raw'           => array(),
			);
		}

		ksort( $tool_calls_map );
		$final_tool_calls = array();
		foreach ( $tool_calls_map as $tc ) {
			if ( empty( $tc['id'] ) ) {
				$tc['id'] = 'call_' . wp_generate_password( 12, false, false );
			}
			$final_tool_calls[] = $tc;
		}

		return array(
			'success'       => true,
			'content'       => $content_acc,
			'tool_calls'    => $final_tool_calls,
			'finish_reason' => ! empty( $finish_reason ) ? $finish_reason : 'stop',
			'message'       => '',
			'raw'           => array(
				'ttfb_ms'         => $ttfb_ms,
				'total_ms'        => $total_ms,
				'total_bytes'     => $total_bytes,
				'chunk_count'     => $chunk_count,
				'tool_args_bytes' => $tool_args_bytes,
			),
		);
	}
}
