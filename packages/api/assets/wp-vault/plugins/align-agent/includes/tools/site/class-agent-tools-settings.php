<?php
/**
 * Align Agent Site Settings Tools
 *
 * Provides inspection and modification of standard WordPress configuration options
 * (reading, discussion, permalinks, timezone, formats) via whitelist validation.
 *
 * @package Align_Agent
 * @subpackage Includes\Tools\Site
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_Settings {

	/**
	 * Whitelisted WordPress settings fields and their validation rules.
	 *
	 * @var array<string, array{type: string, description: string, enum?: array<mixed>}>
	 */
	public const FIELDS = array(
		'blog_public'                  => array(
			'type'        => 'integer',
			'enum'        => array( 0, 1 ),
			'description' => 'Site visibility. 1 allows search engines, 0 asks search engines not to index this site.',
		),
		'permalink_structure'          => array(
			'type'        => 'string',
			'enum'        => array(
				'',
				'/%postname%/',
				'/%year%/%monthnum%/%postname%/',
				'/%year%/%monthnum%/%day%/%postname%/',
				'/%category%/%postname%/',
				'/archives/%post_id%',
			),
			'description' => 'Permalink URL structure preset.',
		),
		'timezone_string'              => array(
			'type'        => 'string',
			'description' => 'City timezone identifier, e.g. "Asia/Ho_Chi_Minh", "UTC", "America/New_York".',
		),
		'date_format'                  => array(
			'type'        => 'string',
			'description' => 'PHP date format string (max 30 characters), e.g. "F j, Y", "Y-m-d".',
		),
		'time_format'                  => array(
			'type'        => 'string',
			'description' => 'PHP time format string (max 30 characters), e.g. "g:i a", "H:i".',
		),
		'posts_per_page'               => array(
			'type'        => 'integer',
			'description' => 'Blog pages show at most this number of posts (1..100).',
		),
		'default_comment_status'       => array(
			'type'        => 'string',
			'enum'        => array( 'open', 'closed' ),
			'description' => 'Allow people to submit comments on new posts.',
		),
		'comment_moderation'           => array(
			'type'        => 'integer',
			'enum'        => array( 0, 1 ),
			'description' => 'Comment must be manually approved before it appears (1 = yes, 0 = no).',
		),
		'comment_registration'         => array(
			'type'        => 'integer',
			'enum'        => array( 0, 1 ),
			'description' => 'Users must be registered and logged in to comment (1 = yes, 0 = no).',
		),
		'close_comments_for_old_posts' => array(
			'type'        => 'integer',
			'enum'        => array( 0, 1 ),
			'description' => 'Automatically close comments on articles older than close_comments_days_old days.',
		),
		'close_comments_days_old'      => array(
			'type'        => 'integer',
			'description' => 'Number of days after which comments are closed (1..3650).',
		),
	);

	/**
	 * Map permalink structure to human-readable label.
	 *
	 * @param string $struct Permalink structure string.
	 * @return string
	 */
	private static function get_permalink_label( string $struct ): string {
		switch ( $struct ) {
			case '':
				return 'Plain (?p=123)';
			case '/%postname%/':
				return 'Post name (/sample-post/)';
			case '/%year%/%monthnum%/%postname%/':
				return 'Month and name (/2026/10/sample-post/)';
			case '/%year%/%monthnum%/%day%/%postname%/':
				return 'Day and name (/2026/10/21/sample-post/)';
			case '/%category%/%postname%/':
				return 'Category and name (/news/sample-post/)';
			case '/archives/%post_id%':
				return 'Numeric (/archives/123)';
			default:
				return 'Custom structure';
		}
	}

	/**
	 * Register site settings tools into Align_Agent_Tool_Registry.
	 *
	 * @return void
	 */
	public static function init(): void {
		Align_Agent_Tool_Registry::register( 'get_site_settings', array(
			'type'             => 'read',
			'group'            => 'site',
			'scope'            => 'local',
			'capability'       => 'manage_options',
			'requires_builder' => false,
			'danger'           => false,
			'description'      => 'Retrieve core WordPress settings (permalink structure, site visibility, timezone, formats, pagination, comment policies) and site URLs.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(),
			),
			'callback'         => array( __CLASS__, 'tool_get_site_settings' ),
		) );

		// Dynamically construct properties schema from FIELDS.
		$properties = array();
		foreach ( self::FIELDS as $field => $def ) {
			$prop = array(
				'type'        => $def['type'],
				'description' => $def['description'],
			);
			if ( isset( $def['enum'] ) ) {
				$prop['enum'] = $def['enum'];
			}
			$properties[ $field ] = $prop;
		}

		Align_Agent_Tool_Registry::register( 'update_site_settings', array(
			'type'             => 'write',
			'undo'             => array( 'adapter' => 'option', 'keys' => array_keys( self::FIELDS ) ),
			'group'            => 'site',
			'scope'            => 'global',
			'capability'       => 'manage_options',
			'requires_builder' => false,
			'danger'           => true,
			'description'      => 'Update core WordPress settings (blog_public, permalink_structure, timezone, date/time formats, posts_per_page, comment options). Requires user approval.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => $properties,
			),
			'callback'         => array( __CLASS__, 'tool_update_site_settings' ),
			'summary_callback' => array( __CLASS__, 'summarize_update_site_settings' ),
		) );
	}

	/**
	 * Callback for get_site_settings tool.
	 *
	 * @param array<string, mixed> $args Tool arguments.
	 * @return array<string, mixed>
	 */
	public static function tool_get_site_settings( array $args = array() ): array {
		$settings = array();

		foreach ( array_keys( self::FIELDS ) as $field ) {
			$val = get_option( $field );
			if ( 'blog_public' === $field || 'posts_per_page' === $field || 'comment_moderation' === $field || 'comment_registration' === $field || 'close_comments_for_old_posts' === $field || 'close_comments_days_old' === $field ) {
				$val = (int) $val;
			}
			$settings[ $field ] = $val;
		}

		$current_permalink           = (string) ( $settings['permalink_structure'] ?? '' );
		$settings['permalink_label'] = self::get_permalink_label( $current_permalink );
		$settings['site_url']        = site_url();
		$settings['home_url']        = home_url();

		return array(
			'success'  => true,
			'settings' => $settings,
			'message'  => 'Retrieved site settings successfully.',
		);
	}

	/**
	 * Callback for update_site_settings tool.
	 *
	 * @param array<string, mixed> $args Tool arguments.
	 * @return array<string, mixed>
	 */
	public static function tool_update_site_settings( array $args ): array {
		if ( empty( $args ) ) {
			return array(
				'success' => false,
				'error'   => 'empty_payload',
				'message' => 'No settings were provided to update.',
			);
		}

		// Check for unknown or disallowed fields.
		$disallowed = array();
		foreach ( array_keys( $args ) as $key ) {
			if ( ! array_key_exists( $key, self::FIELDS ) ) {
				$disallowed[] = $key;
			}
		}

		if ( ! empty( $disallowed ) ) {
			return array(
				'success' => false,
				'error'   => 'field_not_allowed',
				'message' => sprintf( 'The following fields are not allowed or recognized: %s.', implode( ', ', $disallowed ) ),
			);
		}

		$changes = array();

		// Validate each field.
		foreach ( $args as $key => $raw_value ) {
			$val_check = self::validate_field( $key, $raw_value );
			if ( ! $val_check['valid'] ) {
				return array(
					'success' => false,
					'error'   => $val_check['error'] ?? 'invalid_value',
					'message' => sprintf( 'Validation failed for field "%s": %s', $key, $val_check['message'] ?? 'Invalid value.' ),
				);
			}
			$changes[ $key ] = $val_check['sanitized_value'];
		}

		// When changing timezone_string, WordPress standard is setting gmt_offset to empty string.
		if ( isset( $changes['timezone_string'] ) ) {
			$changes['gmt_offset'] = '';
		}

		$write_result = Align_Agent_Option_Writer::write( 'update_site_settings', $changes );

		// Flush rewrite rules if permalink_structure actually changed.
		if ( in_array( 'permalink_structure', $write_result['changed'], true ) ) {
			flush_rewrite_rules( false );
			error_log( '[Align Agent] Settings: rewrite rules flushed' );
		}

		return array(
			'success' => true,
			'message' => sprintf( 'Successfully updated %d site setting(s).', count( $write_result['changed'] ) ),
			'changed' => $write_result['changed'],
			'ui_card' => array(
				'title'   => 'Site Settings Updated',
				'message' => sprintf( 'Updated options: %s.', ! empty( $write_result['changed'] ) ? implode( ', ', $write_result['changed'] ) : 'none (unchanged)' ),
			),
		);
	}

	/**
	 * Validate and sanitize a single setting field.
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Field value.
	 * @return array{valid: bool, error?: string, message?: string, sanitized_value?: mixed}
	 */
	private static function validate_field( string $key, $value ): array {
		$def = self::FIELDS[ $key ] ?? null;
		if ( ! $def ) {
			return array(
				'valid'   => false,
				'error'   => 'field_not_allowed',
				'message' => 'Unknown setting field.',
			);
		}

		switch ( $key ) {
			case 'blog_public':
				$val = (int) $value;
				if ( ! in_array( $val, array( 0, 1 ), true ) ) {
					return array( 'valid' => false, 'error' => 'invalid_enum', 'message' => 'blog_public must be 0 or 1.' );
				}
				return array( 'valid' => true, 'sanitized_value' => $val );

			case 'permalink_structure':
				$str = (string) $value;
				if ( ! in_array( $str, $def['enum'], true ) ) {
					return array( 'valid' => false, 'error' => 'invalid_enum', 'message' => sprintf( 'permalink_structure must be one of: %s.', implode( ', ', array_map( function( $v ) { return '""' === $v ? 'empty' : $v; }, $def['enum'] ) ) ) );
				}
				return array( 'valid' => true, 'sanitized_value' => $str );

			case 'timezone_string':
				$str = (string) $value;
				$all_tz = timezone_identifiers_list();
				if ( ! in_array( $str, $all_tz, true ) && 'UTC' !== $str ) {
					return array( 'valid' => false, 'error' => 'invalid_timezone', 'message' => 'Must be a valid PHP timezone identifier (e.g. "Asia/Ho_Chi_Minh", "UTC").' );
				}
				return array( 'valid' => true, 'sanitized_value' => $str );

			case 'date_format':
			case 'time_format':
				$str = sanitize_option( $key, (string) $value );
				if ( strlen( $str ) > 30 ) {
					return array( 'valid' => false, 'error' => 'format_too_long', 'message' => 'Format string must not exceed 30 characters.' );
				}
				return array( 'valid' => true, 'sanitized_value' => $str );

			case 'posts_per_page':
				$int_val = (int) $value;
				if ( $int_val < 1 || $int_val > 100 ) {
					return array( 'valid' => false, 'error' => 'out_of_range', 'message' => 'posts_per_page must be an integer between 1 and 100.' );
				}
				return array( 'valid' => true, 'sanitized_value' => $int_val );

			case 'default_comment_status':
				$str = strtolower( trim( (string) $value ) );
				if ( ! in_array( $str, array( 'open', 'closed' ), true ) ) {
					return array( 'valid' => false, 'error' => 'invalid_enum', 'message' => 'default_comment_status must be "open" or "closed".' );
				}
				return array( 'valid' => true, 'sanitized_value' => $str );

			case 'comment_moderation':
			case 'comment_registration':
			case 'close_comments_for_old_posts':
				$val = (int) $value;
				if ( ! in_array( $val, array( 0, 1 ), true ) ) {
					return array( 'valid' => false, 'error' => 'invalid_enum', 'message' => sprintf( '%s must be 0 or 1.', $key ) );
				}
				return array( 'valid' => true, 'sanitized_value' => $val );

			case 'close_comments_days_old':
				$int_val = (int) $value;
				if ( $int_val < 1 || $int_val > 3650 ) {
					return array( 'valid' => false, 'error' => 'out_of_range', 'message' => 'close_comments_days_old must be an integer between 1 and 3650.' );
				}
				return array( 'valid' => true, 'sanitized_value' => $int_val );

			default:
				return array( 'valid' => false, 'error' => 'unhandled_field', 'message' => 'Field validation rule missing.' );
		}
	}

	/**
	 * Provide approval summary for update_site_settings tool execution.
	 *
	 * @param array<string, mixed> $args Tool arguments.
	 * @return array<string, mixed>
	 */
	public static function summarize_update_site_settings( array $args ): array {
		$changes_desc = array();
		$warnings     = array();

		foreach ( $args as $key => $new_val ) {
			if ( ! array_key_exists( $key, self::FIELDS ) ) {
				continue;
			}
			$old_val = get_option( $key );
			$changes_desc[] = sprintf( '%s: "%s" → "%s"', $key, (string) $old_val, (string) $new_val );

			if ( 'blog_public' === $key && 0 === (int) $new_val ) {
				$warnings[] = '⚠️ Search engines will stop indexing the whole website.';
			}
			if ( 'permalink_structure' === $key && (string) $old_val !== (string) $new_val ) {
				$warnings[] = '⚠️ All page URLs will change. Old links may break.';
			}
		}

		$title = sprintf( 'Update %d site setting(s)', count( $changes_desc ) );
		$desc  = implode( "\n", $changes_desc );
		if ( ! empty( $warnings ) ) {
			$desc .= "\n\n" . implode( "\n", $warnings );
		}

		return array(
			'title'       => $title,
			'description' => substr( $desc, 0, 1500 ),
			'meta'        => array(
				'keys' => array_keys( $args ),
			),
		);
	}
}
