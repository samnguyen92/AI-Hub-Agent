<?php
/**
 * Unified Multi-Endpoint Health Check & Diagnostics Engine.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Endpoint_Tester {

	/**
	 * Timeout duration for individual endpoint requests in seconds.
	 */
	const REQUEST_TIMEOUT = 4;

	/**
	 * Initialize AJAX hooks for endpoint tester.
	 */
	public static function init() {
		add_action( 'wp_ajax_align_test_all_endpoints', array( __CLASS__, 'ajax_test_all_endpoints' ) );
	}

	/**
	 * Normalize raw API URL to root base URL without subpaths or trailing slashes.
	 *
	 * @param string $raw_url Raw URL string input.
	 * @return string Clean base URL.
	 */
	public static function normalize_base_url( $raw_url = '' ) {
		if ( empty( $raw_url ) || ! is_string( $raw_url ) || trim( $raw_url ) === '' ) {
			$raw_url = function_exists( 'align_get_api_converter_url' ) ? align_get_api_converter_url() : 'https://convertb.alignvn.cloud';
		}

		$clean = trim( $raw_url );
		$clean = preg_replace( '#/(api/(convert-image-to-webp|convert-image|convert|minify|unminify|scss2css|scss-to-css|sass-to-css)|health)/?$#i', '', $clean );

		return rtrim( $clean, '/' );
	}

	/**
	 * Get specification list for all 6 active Align Cloud microservice endpoints.
	 *
	 * @param string $base_url Normalized base URL.
	 * @return array List of endpoint specifications.
	 */
	public static function get_endpoint_specs( $base_url ) {
		return array(
			array(
				'id'          => 'health',
				'name'        => 'Health Check',
				'path'        => '/health',
				'url'         => $base_url . '/health',
				'method'      => 'GET',
				'body'        => null,
				'description' => 'Server availability and uptime verification',
				'fallback'    => 'Normal operation unaffected',
			),
			array(
				'id'          => 'webp',
				'name'        => 'WebP Image Converter',
				'path'        => '/api/convert-image-to-webp',
				'url'         => $base_url . '/api/convert-image-to-webp',
				'method'      => 'POST',
				'body'        => array(
					'image'   => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
					'quality' => 80,
				),
				'description' => 'Converts PNG/JPG Base64 data to WebP format',
				'fallback'    => 'Original image preserved untouched (Bypass convert)',
			),
			array(
				'id'          => 'scss',
				'name'        => 'SCSS Compiler',
				'path'        => '/api/scss-to-css',
				'url'         => $base_url . '/api/scss-to-css',
				'method'      => 'POST',
				'body'        => array(
					'code'   => '$color: #0073aa; .align-test { color: $color; }',
					'minify' => true,
				),
				'description' => 'Compiles SCSS stylesheet to minified CSS',
				'fallback'    => 'Standard CSS unaffected',
			),
			array(
				'id'          => 'minify',
				'name'        => 'Code Minifier',
				'path'        => '/api/minify',
				'url'         => $base_url . '/api/minify',
				'method'      => 'POST',
				'body'        => array(
					'code' => '.align-test { margin: 0px 10px; padding: 0; }',
					'type' => 'css',
				),
				'description' => 'Minifies CSS and JS code payloads',
				'fallback'    => 'Raw code preserved untouched (Bypass minifier)',
			),
			array(
				'id'          => 'unminify',
				'name'        => 'Code Formatter (Unminify)',
				'path'        => '/api/unminify',
				'url'         => $base_url . '/api/unminify',
				'method'      => 'POST',
				'body'        => array(
					'code' => '.align-test{margin:0 10px;padding:0}',
					'type' => 'css',
				),
				'description' => 'Beautifies and formats minified CSS and JS',
				'fallback'    => 'Original source preserved',
			),
			array(
				'id'          => 'convert',
				'name'        => 'HTML to Blocks Converter',
				'path'        => '/api/convert',
				'url'         => $base_url . '/api/convert',
				'method'      => 'POST',
				'body'        => array(
					'html'     => '<section class="align-test"><h1>Test Title</h1><p>Sample description</p></section>',
					'wordMode' => false,
				),
				'description' => 'Parses raw HTML into Gutenberg block structures',
				'fallback'    => 'Original HTML preserved in Custom HTML block',
			),
		);
	}

	/**
	 * Execute a test request against a single endpoint specification.
	 *
	 * @param array $spec Endpoint specification array.
	 * @return array Diagnostic test result.
	 */
	public static function test_single_endpoint( array $spec ) {
		$start_time = microtime( true );

		$args = array(
			'timeout' => self::REQUEST_TIMEOUT,
			'headers' => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
		);

		if ( 'POST' === $spec['method'] && ! empty( $spec['body'] ) ) {
			$args['body'] = wp_json_encode( $spec['body'] );
		}

		if ( 'POST' === $spec['method'] ) {
			$response = wp_remote_post( $spec['url'], $args );
		} else {
			$response = wp_remote_get( $spec['url'], $args );
		}

		$latency_ms = (int) round( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			error_log( sprintf( '[Align API] Endpoint test failed: %s (%s) | Error: %s | Latency: %dms', $spec['name'], $spec['path'], $error_message, $latency_ms ) );

			return array(
				'id'              => $spec['id'],
				'name'            => $spec['name'],
				'path'            => $spec['path'],
				'method'          => $spec['method'],
				'description'     => $spec['description'] ?? '',
				'fallback'        => $spec['fallback'] ?? '',
				'fallback_active' => true,
				'status'          => 'failed',
				'code'            => 0,
				'latency_ms'      => $latency_ms,
				'message'         => $error_message,
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$json        = json_decode( $body, true );

		if ( $status_code >= 200 && $status_code < 300 ) {
			error_log( sprintf( '[Align API] Endpoint %s (%s) operational | HTTP %d | Latency: %dms', $spec['name'], $spec['path'], $status_code, $latency_ms ) );

			return array(
				'id'              => $spec['id'],
				'name'            => $spec['name'],
				'path'            => $spec['path'],
				'method'          => $spec['method'],
				'description'     => $spec['description'] ?? '',
				'fallback'        => $spec['fallback'] ?? '',
				'fallback_active' => false,
				'status'          => 'operational',
				'code'            => $status_code,
				'latency_ms'      => $latency_ms,
				'message'         => 'Operational',
			);
		}

		$error_msg = 'HTTP ' . $status_code;
		if ( is_array( $json ) ) {
			if ( ! empty( $json['error'] ) ) {
				$error_msg = is_string( $json['error'] ) ? $json['error'] : wp_json_encode( $json['error'] );
			} elseif ( ! empty( $json['message'] ) ) {
				$error_msg = is_string( $json['message'] ) ? $json['message'] : wp_json_encode( $json['message'] );
			}
		}

		error_log( sprintf( '[Align API] Endpoint %s (%s) returned error HTTP %d: %s | Latency: %dms', $spec['name'], $spec['path'], $status_code, $error_msg, $latency_ms ) );

		return array(
			'id'              => $spec['id'],
			'name'            => $spec['name'],
			'path'            => $spec['path'],
			'method'          => $spec['method'],
			'description'     => $spec['description'] ?? '',
			'fallback'        => $spec['fallback'] ?? '',
			'fallback_active' => true,
			'status'          => 'failed',
			'code'            => $status_code,
			'latency_ms'      => $latency_ms,
			'message'         => $error_msg,
		);
	}

	/**
	 * AJAX Handler: Run sequential diagnostics for all 6 endpoints.
	 */
	public static function ajax_test_all_endpoints() {
		$nonce = $_POST['security'] ?? $_POST['align_settings_nonce'] ?? $_POST['nonce'] ?? '';
		if ( ! wp_verify_nonce( $nonce, 'align_settings_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid or expired security token. Please refresh the page.', 'align' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized user permission.', 'align' ) ) );
		}

		$raw_url  = isset( $_POST['api_url'] ) ? sanitize_text_field( wp_unslash( $_POST['api_url'] ) ) : '';
		$base_url = self::normalize_base_url( $raw_url );
		$specs    = self::get_endpoint_specs( $base_url );

		$results           = array();
		$operational_count = 0;
		$failed_count      = 0;
		$total_latency     = 0;

		foreach ( $specs as $spec ) {
			$result = self::test_single_endpoint( $spec );
			$results[] = $result;
			$total_latency += (int) $result['latency_ms'];

			if ( 'operational' === $result['status'] ) {
				$operational_count++;
			} else {
				$failed_count++;
			}
		}

		error_log( sprintf(
			'[Align API] Tested all %d endpoints on %s | Operational: %d/%d | Total Latency: %dms',
			count( $specs ),
			$base_url,
			$operational_count,
			count( $specs ),
			$total_latency
		) );

		wp_send_json_success( array(
			'base_url'  => $base_url,
			'summary'   => array(
				'total'            => count( $specs ),
				'operational'      => $operational_count,
				'failed'           => $failed_count,
				'total_latency_ms' => $total_latency,
				'all_operational'  => ( $operational_count === count( $specs ) ),
			),
			'endpoints' => $results,
		) );
	}
}
