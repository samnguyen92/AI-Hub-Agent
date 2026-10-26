<?php
/**
 * Align Agent Gateway - Dry-Run Markup & Style Validator
 *
 * Provides sandbox validation for HTML structure (detecting unclosed tags,
 * malformed attributes, hierarchy errors) and CSS sanity before saving.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Gateway_Validator {

	/**
	 * HTML void elements that do not require closing tags.
	 *
	 * @var array
	 */
	private static $void_tags = array(
		'area',
		'base',
		'br',
		'col',
		'embed',
		'hr',
		'img',
		'input',
		'link',
		'meta',
		'param',
		'source',
		'track',
		'wbr',
	);

	/**
	 * Direct markup and CSS validation helper.
	 *
	 * @param string $html HTML markup.
	 * @param string $css  Optional CSS code.
	 * @return array{valid: bool, errors: array, warnings: array, stats: array}
	 */
	public static function validate_markup( string $html, string $css = '' ): array {
		$errors   = array();
		$warnings = array();

		// 1. Validate HTML structure.
		if ( '' !== trim( $html ) ) {
			self::validate_html_tags( $html, $errors, $warnings );
			self::validate_html_dom( $html, $errors, $warnings );
		} else {
			$warnings[] = __( 'HTML payload is empty.', 'align-agent' );
		}

		// 2. Validate CSS syntax.
		if ( '' !== trim( $css ) ) {
			self::validate_css_syntax( $css, $errors, $warnings );
		}

		$is_valid = empty( $errors );

		error_log( sprintf(
			'[Align Agent] Validation executed: %s | Errors: %d, Warnings: %d.',
			$is_valid ? 'PASSED' : 'FAILED',
			count( $errors ),
			count( $warnings )
		) );

		return array(
			'valid'    => $is_valid,
			'errors'   => $errors,
			'warnings' => $warnings,
			'stats'    => array(
				'html_bytes'     => strlen( $html ),
				'css_bytes'      => strlen( $css ),
				'errors_count'   => count( $errors ),
				'warnings_count' => count( $warnings ),
			),
		);
	}

	/**
	 * Handle dry-run validation request.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response Validation results.
	 */
	public static function handle_validate( WP_REST_Request $request ): WP_REST_Response {
		$html = (string) ( $request->get_param( 'html' ) ?? '' );
		$css  = (string) ( $request->get_param( 'css' ) ?? '' );

		$data = self::validate_markup( $html, $css );

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Stack-based HTML tag balance checker.
	 *
	 * @param string $html     HTML input string.
	 * @param array  $errors   Reference to errors array.
	 * @param array  $warnings Reference to warnings array.
	 */
	private static function validate_html_tags( string $html, array &$errors, array &$warnings ) {
		// Strip comments and script/style contents for tag balance analysis.
		$cleaned = preg_replace( '/<!--[\s\S]*?-->/', '', $html );
		$cleaned = preg_replace( '/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $cleaned );
		$cleaned = preg_replace( '/<style\b[^>]*>[\s\S]*?<\/style>/i', '', $cleaned );

		$stack = array();

		if ( preg_match_all( '/<(\/)?([a-zA-Z0-9\-]+)([^>]*)>/i', $cleaned, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches as $match ) {
				$full_tag    = $match[0][0];
				$is_closing  = ! empty( $match[1][0] );
				$tag_name    = strtolower( $match[2][0] );
				$attrs       = $match[3][0];
				$byte_offset = $match[0][1];

				// Check if self-closing tag or void tag.
				$is_self_closing = ( substr( rtrim( $attrs ), -1 ) === '/' );
				if ( in_array( $tag_name, self::$void_tags, true ) || $is_self_closing ) {
					if ( $is_closing ) {
						$warnings[] = sprintf( __( 'Unexpected closing tag </%s> for void element at position %d.', 'align-agent' ), $tag_name, $byte_offset );
					}
					continue;
				}

				if ( ! $is_closing ) {
					// Opening tag.
					$stack[] = array( 'tag' => $tag_name, 'offset' => $byte_offset );
				} else {
					// Closing tag.
					if ( empty( $stack ) ) {
						$errors[] = sprintf( __( 'Unexpected closing tag </%s> with no matching open tag at position %d.', 'align-agent' ), $tag_name, $byte_offset );
						continue;
					}

					$top = end( $stack );
					if ( $top['tag'] === $tag_name ) {
						array_pop( $stack );
					} else {
						// Look up stack to see if open tag exists.
						$found_idx = -1;
						for ( $i = count( $stack ) - 1; $i >= 0; $i-- ) {
							if ( $stack[ $i ]['tag'] === $tag_name ) {
								$found_idx = $i;
								break;
							}
						}

						if ( $found_idx !== -1 ) {
							for ( $j = count( $stack ) - 1; $j > $found_idx; $j-- ) {
								$errors[] = sprintf( __( 'Unclosed tag <%s> opened at position %d, prematurely closed by </%s>.', 'align-agent' ), $stack[ $j ]['tag'], $stack[ $j ]['offset'], $tag_name );
							}
							$stack = array_slice( $stack, 0, $found_idx );
						} else {
							$errors[] = sprintf( __( 'Mismatched closing tag </%s> at position %d (expected </%s>).', 'align-agent' ), $tag_name, $byte_offset, $top['tag'] );
						}
					}
				}
			}
		}

		while ( ! empty( $stack ) ) {
			$unclosed = array_pop( $stack );
			$errors[] = sprintf( __( 'Unclosed tag <%s> detected (opened at character offset %d).', 'align-agent' ), $unclosed['tag'], $unclosed['offset'] );
		}
	}

	/**
	 * Validate HTML using DOMDocument parser for severe XML/HTML malformations.
	 *
	 * @param string $html     HTML input string.
	 * @param array  $errors   Reference to errors array.
	 * @param array  $warnings Reference to warnings array.
	 */
	private static function validate_html_dom( string $html, array &$errors, array &$warnings ) {
		if ( ! class_exists( 'DOMDocument' ) ) {
			return;
		}

		$prev_libxml = libxml_use_internal_errors( true );
		libxml_clear_errors();

		$doc     = new DOMDocument( '1.0', 'UTF-8' );
		$wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';
		$doc->loadHTML( $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

		$xml_errors = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors( $prev_libxml );

		foreach ( $xml_errors as $err ) {
			// Suppress common false positives for HTML5 semantic tags.
			if ( false !== strpos( $err->message, 'Tag nav invalid' ) ||
				false !== strpos( $err->message, 'Tag main invalid' ) ||
				false !== strpos( $err->message, 'Tag section invalid' ) ||
				false !== strpos( $err->message, 'Tag article invalid' ) ||
				false !== strpos( $err->message, 'Tag header invalid' ) ||
				false !== strpos( $err->message, 'Tag footer invalid' ) ||
				false !== strpos( $err->message, 'Tag aside invalid' ) ) {
				continue;
			}

			if ( LIBXML_ERR_FATAL === $err->level ) {
				$errors[] = sprintf( __( 'DOM Parser Fatal Error: %s (line %d)', 'align-agent' ), trim( $err->message ), $err->line );
			} elseif ( LIBXML_ERR_ERROR === $err->level ) {
				$warnings[] = sprintf( __( 'DOM Parser Error: %s (line %d)', 'align-agent' ), trim( $err->message ), $err->line );
			}
		}
	}

	/**
	 * Check CSS syntax for balanced braces and unterminated comments.
	 *
	 * @param string $css      CSS string.
	 * @param array  $errors   Reference to errors array.
	 * @param array  $warnings Reference to warnings array.
	 */
	private static function validate_css_syntax( string $css, array &$errors, array &$warnings ) {
		$open_comments  = substr_count( $css, '/*' );
		$close_comments = substr_count( $css, '*/' );
		if ( $open_comments !== $close_comments ) {
			$errors[] = sprintf( __( 'Unbalanced CSS comments: %d opened (/*) vs %d closed (*/).', 'align-agent' ), $open_comments, $close_comments );
		}

		$clean_css = preg_replace( '/\/\*[\s\S]*?\*\//', '', $css );
		$clean_css = preg_replace( '/(["\'])(?:\\\\\1|.)*?\1/', '', $clean_css );

		$open_braces  = substr_count( $clean_css, '{' );
		$close_braces = substr_count( $clean_css, '}' );

		if ( $open_braces !== $close_braces ) {
			$errors[] = sprintf( __( 'Unbalanced CSS braces: %d opening "{" vs %d closing "}".', 'align-agent' ), $open_braces, $close_braces );
		}
	}
}
