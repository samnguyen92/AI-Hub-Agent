<?php
/**
 * Align Agent Patch Engine
 *
 * Provides string matching and replacement engine for surgical content edits.
 * Enforces strict single-match semantics: rejects ambiguous matches or missing patterns.
 *
 * @package Align_Agent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Patch_Engine {

	/**
	 * Apply a single replacement on a string target.
	 *
	 * @param string $content Source content.
	 * @param string $find Target substring to locate (must occur exactly once).
	 * @param string $replace Replacement string.
	 * @return array{success: bool, content?: string, snippet?: array<string, string>, error?: string, message?: string, count?: int}
	 */
	public static function patch_string( string $content, string $find, string $replace ): array {
		if ( '' === $find ) {
			return array(
				'success' => false,
				'error'   => 'patch_invalid_edit',
				'message' => __( "The 'find' pattern cannot be empty. If target content is empty, use save_template, save_page, or save_post instead.", 'align-agent' ),
			);
		}

		$count = substr_count( $content, $find );
		if ( 0 === $count ) {
			return array(
				'success' => false,
				'error'   => 'patch_no_match',
				'message' => sprintf( __( 'Pattern was not found in content: "%s"', 'align-agent' ), mb_substr( $find, 0, 80 ) ),
			);
		}

		if ( $count > 1 ) {
			return array(
				'success' => false,
				'error'   => 'patch_ambiguous_match',
				'count'   => $count,
				'message' => sprintf( __( 'Pattern matches %d times. Provide more surrounding context to make it unique.', 'align-agent' ), $count ),
			);
		}

		$pos     = strpos( $content, $find );
		$start   = max( 0, (int) $pos - 80 );
		$len_ctx = ( (int) $pos - $start ) + strlen( $find ) + 80;
		$before  = substr( $content, $start, $len_ctx );

		$new_content = substr_replace( $content, $replace, (int) $pos, strlen( $find ) );

		$len_new_ctx = ( (int) $pos - $start ) + strlen( $replace ) + 80;
		$after       = substr( $new_content, $start, $len_new_ctx );

		return array(
			'success' => true,
			'content' => $new_content,
			'snippet' => array(
				'before' => $before,
				'after'  => $after,
			),
		);
	}

	/**
	 * Apply an ordered series of edits to associative fields array (html, css, js).
	 *
	 * @param array<string, string> $fields Current field values.
	 * @param array<int, array<string, mixed>> $edits Array of edits: [{ field, find, replace }, ...].
	 * @param array<string> $allowed_fields List of allowable field keys (default: html, css, js).
	 * @return array{success: bool, fields?: array<string, string>, snippets?: array<int, array<string, mixed>>, error?: string, message?: string, count?: int}
	 */
	public static function apply_edits( array $fields, array $edits, array $allowed_fields = array( 'html', 'css', 'js' ) ): array {
		if ( empty( $edits ) || ! is_array( $edits ) || count( $edits ) > 10 ) {
			return array(
				'success' => false,
				'error'   => 'invalid_edits_count',
				'message' => __( 'Edits must contain between 1 and 10 items.', 'align-agent' ),
			);
		}

		$patched_fields = $fields;
		$snippets       = array();

		foreach ( $edits as $idx => $edit ) {
			if ( ! is_array( $edit ) ) {
				return array(
					'success' => false,
					'error'   => 'patch_invalid_edit',
					'message' => sprintf( __( 'Edit #%d is malformed.', 'align-agent' ), $idx + 1 ),
				);
			}

			$field   = sanitize_key( (string) ( $edit['field'] ?? '' ) );
			$find    = (string) ( $edit['find'] ?? '' );
			$replace = (string) ( $edit['replace'] ?? '' );

			if ( ! in_array( $field, $allowed_fields, true ) ) {
				return array(
					'success' => false,
					'error'   => 'invalid_field',
					'message' => sprintf( __( 'Field "%1$s" in edit #%2$d is not supported.', 'align-agent' ), $field, $idx + 1 ),
				);
			}

			$current_val = (string) ( $patched_fields[ $field ] ?? '' );
			$result      = self::patch_string( $current_val, $find, $replace );

			if ( empty( $result['success'] ) ) {
				return $result;
			}

			$patched_fields[ $field ] = (string) $result['content'];
			$snippets[]               = array(
				'field'   => $field,
				'before'  => $result['snippet']['before'] ?? '',
				'after'   => $result['snippet']['after'] ?? '',
				'find'    => $find,
				'replace' => $replace,
			);
		}

		return array(
			'success'  => true,
			'fields'   => $patched_fields,
			'snippets' => $snippets,
		);
	}
}
