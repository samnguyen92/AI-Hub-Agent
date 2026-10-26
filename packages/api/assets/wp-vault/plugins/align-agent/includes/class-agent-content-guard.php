<?php
/**
 * Align Agent Content Guard
 *
 * Validates and guards HTML, CSS, and JS content before saving to the database.
 * Enforces security constraints: blocks script tags and inspects risky JS patterns.
 *
 * @package Align_Agent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Content_Guard {

	/**
	 * Inspect JavaScript payload for forbidden tags and suspicious execution patterns.
	 *
	 * @param string $js Raw JavaScript string.
	 * @return array{valid: bool, error?: string, message?: string, warnings?: array<string>}
	 */
	public static function check_js( string $js ): array {
		if ( '' === trim( $js ) ) {
			return array(
				'valid'    => true,
				'warnings' => array(),
			);
		}

		if ( preg_match( '/<\/?script\b/i', $js ) ) {
			return array(
				'valid'   => false,
				'error'   => 'forbidden_js_payload',
				'message' => __( 'Embedding <script> or </script> tags inside JavaScript is forbidden.', 'align-agent' ),
			);
		}

		$warnings  = array();
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST ) ?: '';

		if ( preg_match( '/\beval\s*\(/i', $js ) ) {
			$warnings[] = __( 'Uses eval() which may execute untrusted strings.', 'align-agent' );
		}
		if ( preg_match( '/\bnew\s+Function\s*\(/i', $js ) ) {
			$warnings[] = __( 'Uses new Function() dynamic code execution.', 'align-agent' );
		}
		if ( preg_match( '/\bdocument\.write\s*\(/i', $js ) ) {
			$warnings[] = __( 'Uses document.write() which can overwrite document structure.', 'align-agent' );
		}
		if ( preg_match( '/\bdocument\.cookie\b/i', $js ) ) {
			$warnings[] = __( 'Accesses document.cookie (sensitive session data).', 'align-agent' );
		}
		if ( preg_match( '/\blocalStorage\b/i', $js ) ) {
			$warnings[] = __( 'Accesses localStorage client-side storage.', 'align-agent' );
		}

		if ( preg_match_all( '/\b(?:fetch|import)\s*\(\s*[\'"`](https?:\/\/[^\'"`]+)[\'"`]/i', $js, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				$host = wp_parse_url( $url, PHP_URL_HOST );
				if ( $host && '' !== $site_host && strtolower( (string) $host ) !== strtolower( (string) $site_host ) ) {
					$warnings[] = sprintf( __( 'Makes external request to "%s".', 'align-agent' ), esc_html( (string) $host ) );
				}
			}
		}

		if ( preg_match_all( '/\.open\s*\(\s*[\'"`][A-Z]+[\'"`]\s*,\s*[\'"`](https?:\/\/[^\'"`]+)[\'"`]/i', $js, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				$host = wp_parse_url( $url, PHP_URL_HOST );
				if ( $host && '' !== $site_host && strtolower( (string) $host ) !== strtolower( (string) $site_host ) ) {
					$warnings[] = sprintf( __( 'Makes external XMLHttpRequest to "%s".', 'align-agent' ), esc_html( (string) $host ) );
				}
			}
		}

		return array(
			'valid'    => true,
			'warnings' => array_values( array_unique( $warnings ) ),
		);
	}

	/**
	 * Guard HTML, CSS, and JS against forbidden tags and run markup validator.
	 *
	 * @param string $html Body HTML markup.
	 * @param string $css Scoped CSS.
	 * @param string $js Scoped JavaScript.
	 * @return array<string, mixed>|null Returns error array if invalid, or null if passed.
	 */
	public static function guard( string $html, string $css, string $js = '' ): ?array {
		if ( preg_match( '/<script\b[^>]*>/i', $html ) ) {
			error_log( '[Align Agent] Write rejected: HTML payload contains forbidden <script> tag.' );
			return array(
				'success' => false,
				'error'   => 'forbidden_script_tag',
				'message' => __( 'Embedding <script> or <script src> inside page HTML is forbidden. Move page JavaScript to the dedicated "js" parameter, or register third-party embeds via Align Studio -> Reuse Block.', 'align-agent' ),
			);
		}

		if ( '' !== $js ) {
			$js_check = self::check_js( $js );
			if ( empty( $js_check['valid'] ) ) {
				error_log( '[Align Agent] Write rejected: JS payload contains forbidden <script> tags.' );
				return array(
					'success' => false,
					'error'   => $js_check['error'] ?? 'forbidden_js_payload',
					'message' => $js_check['message'] ?? __( 'Forbidden JavaScript payload.', 'align-agent' ),
				);
			}
			if ( ! empty( $js_check['warnings'] ) ) {
				error_log( sprintf( '[Align Agent] JS guard: %d warnings detected.', count( $js_check['warnings'] ) ) );
			}
		}

		if ( class_exists( 'Align_Gateway_Validator' ) ) {
			$validation = Align_Gateway_Validator::validate_markup( $html, $css );
			if ( empty( $validation['valid'] ) ) {
				return array(
					'success'    => false,
					'error'      => 'invalid_markup',
					'message'    => __( 'Markup validation failed. Fix the reported HTML/CSS errors and retry.', 'align-agent' ),
					'validation' => $validation,
				);
			}
		}

		return null;
	}
}
