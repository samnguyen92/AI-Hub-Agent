<?php
/**
 * Shared HTML conversion service for Settings and editor paste.
 *
 * @package Align
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Block_Converter {
	/**
	 * Convert raw HTML into Gutenberg block markup.
	 *
	 * @param string $html      Raw pasted HTML.
	 * @param bool   $word_mode Whether to clean Microsoft Word markup.
	 * @param string $url       Optional converter base URL.
	 * @return array|WP_Error Conversion result or error.
	 */
	public static function convert( $html, $word_mode = false, $url = '' ) {
		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			return new WP_Error( 'align_empty_html', 'Please provide HTML content to convert.', array( 'status' => 400 ) );
		}
		if ( strlen( $html ) > 1048576 ) {
			return new WP_Error( 'align_html_too_large', 'HTML must be smaller than 1 MB.', array( 'status' => 413 ) );
		}
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$html = wp_kses_post( $html );
		}
		$url      = $url ?: align_get_api_converter_url();
		$endpoint = Align_Endpoint_Tester::normalize_base_url( $url ) . '/api/convert';
		$start    = microtime( true );
		error_log( '[Align API] HTML conversion started | bytes=' . strlen( $html ) . ' | user_id=' . get_current_user_id() );
		$response = wp_safe_remote_post( $endpoint, array(
			'timeout' => 20,
			'limit_response_size' => 5242880,
			'headers' => array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
			'body' => wp_json_encode( array( 'html' => $html, 'wordMode' => (bool) $word_mode ) ),
		) );
		if ( is_wp_error( $response ) ) {
			$err_msg = $response->get_error_message();
			error_log( sprintf( '[Align API] HTML converter API unreachable (%s) | Generating local core/html fallback block.', $err_msg ) );
			$latency = (int) round( ( microtime( true ) - $start ) * 1000 );
			return array(
				'markup'       => "<!-- wp:html -->\n" . trim( $html ) . "\n<!-- /wp:html -->",
				'blocks_count' => 1,
				'latency_ms'   => $latency,
				'fallback'     => true,
				'message'      => 'Cloud API offline. Fallback core/html block generated.',
			);
		}
		$status  = wp_remote_retrieve_response_code( $response );
		$body    = wp_remote_retrieve_body( $response );
		$data    = json_decode( $body, true );
		$payload = is_array( $data ) ? ( $data['data'] ?? $data ) : array();
		$markup  = is_array( $payload ) ? ( $payload['markup'] ?? '' ) : '';
		if ( $status < 200 || $status >= 300 || JSON_ERROR_NONE !== json_last_error() ||
			( isset( $data['success'] ) && ! $data['success'] ) || ! is_string( $markup ) || ! has_blocks( $markup ) ) {
			$err_reason = 'status=' . $status . ' | json_error=' . json_last_error_msg();
			error_log( sprintf( '[Align API] Invalid HTML converter response (%s) | Generating local core/html fallback block.', $err_reason ) );
			$latency = (int) round( ( microtime( true ) - $start ) * 1000 );
			return array(
				'markup'       => "<!-- wp:html -->\n" . trim( $html ) . "\n<!-- /wp:html -->",
				'blocks_count' => 1,
				'latency_ms'   => $latency,
				'fallback'     => true,
				'message'      => 'Converter response invalid. Fallback core/html block generated.',
			);
		}
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$markup = wp_kses_post( $markup );
		}
		$latency = (int) round( ( microtime( true ) - $start ) * 1000 );
		error_log( '[Align API] HTML conversion completed | status=' . $status . ' | markup_bytes=' . strlen( $markup ) . ' | latency_ms=' . $latency );
		return array( 'markup' => $markup, 'blocks_count' => count( parse_blocks( $markup ) ), 'latency_ms' => $latency );
	}
}
