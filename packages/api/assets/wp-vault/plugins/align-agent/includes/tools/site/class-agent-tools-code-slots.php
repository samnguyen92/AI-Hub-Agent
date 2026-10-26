<?php
/**
 * Align Agent Code Slots Tools
 *
 * Provides inspection and modification of HTML code injection slots (head, body-open, footer)
 * and the skip_admins setting. Requires unfiltered_html for modification.
 *
 * @package Align_Agent
 * @subpackage Includes\Tools\Site
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_Code_Slots {

	/**
	 * Map of logical slot keys to WordPress option names.
	 *
	 * @var array<string, string>
	 */
	private const SLOTS = array(
		'tracking_code'    => 'align_tracking_code',     // printed in <head>
		'body_open_code'   => 'align_body_open_code',    // printed after <body>
		'footer_html_code' => 'align_footer_html_code',  // printed before </body>
	);

	/**
	 * Maximum allowed bytes per code slot payload.
	 *
	 * @var int
	 */
	private const MAX_BYTES = 65536;

	/**
	 * Option key for skipping custom code for administrators.
	 *
	 * @var string
	 */
	private const SKIP_ADMINS_OPTION = 'align_custom_code_skip_admins';

	/**
	 * Register code slots tools into Align_Agent_Tool_Registry.
	 *
	 * @return void
	 */
	public static function init(): void {
		Align_Agent_Tool_Registry::register( 'get_code_slots', array(
			'type'             => 'read',
			'group'            => 'site',
			'scope'            => 'local',
			'capability'       => 'manage_options',
			'requires_builder' => false,
			'danger'           => false,
			'description'      => 'Retrieve all sitewide HTML code slots (head tracking, body-open, footer) with byte sizes and skip_admins status.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(),
			),
			'callback'         => array( __CLASS__, 'tool_get_code_slots' ),
		) );

		Align_Agent_Tool_Registry::register( 'save_code_slot', array(
			'type'             => 'write',
			'undo'             => array( 'adapter' => 'option', 'keys' => array( 'align_tracking_code', 'align_body_open_code', 'align_footer_html_code', 'align_custom_code_skip_admins' ) ),
			'group'            => 'site',
			'scope'            => 'global',
			'capability'       => 'unfiltered_html',
			'requires_builder' => false,
			'danger'           => true,
			'description'      => 'Update a sitewide HTML code slot (tracking_code, body_open_code, footer_html_code) or configure skip_admins. Requires unfiltered_html capability and user approval.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'slot'        => array(
						'type'        => 'string',
						'enum'        => array( 'tracking_code', 'body_open_code', 'footer_html_code' ),
						'description' => 'Target code slot identifier.',
					),
					'code'        => array(
						'type'        => 'string',
						'description' => 'Full replacement content. Empty string clears the slot.',
					),
					'edits'       => array(
						'type'        => 'array',
						'description' => 'Surgical search-and-replace edits to apply to the current slot content.',
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'find'    => array( 'type' => 'string' ),
								'replace' => array( 'type' => 'string' ),
							),
							'required'   => array( 'find', 'replace' ),
						),
					),
					'skip_admins' => array(
						'type'        => 'boolean',
						'description' => 'Optional. Hide all custom code slots for logged-in administrators.',
					),
				),
			),
			'callback'         => array( __CLASS__, 'tool_save_code_slot' ),
			'summary_callback' => array( __CLASS__, 'summarize_save_code_slot' ),
		) );
	}

	/**
	 * Callback for get_code_slots tool.
	 *
	 * @param array<string, mixed> $args Tool arguments.
	 * @return array<string, mixed>
	 */
	public static function tool_get_code_slots( array $args = array() ): array {
		$slots_data = array();

		foreach ( self::SLOTS as $slot_key => $option_name ) {
			$raw_code   = (string) get_option( $option_name, '' );
			$slots_data[ $slot_key ] = array(
				'option' => $option_name,
				'code'   => $raw_code,
				'bytes'  => strlen( $raw_code ),
			);
		}

		$skip_admins = (bool) get_option( self::SKIP_ADMINS_OPTION, false );

		return array(
			'success'     => true,
			'slots'       => $slots_data,
			'skip_admins' => $skip_admins,
			'message'     => 'Retrieved sitewide code slots successfully.',
		);
	}

	/**
	 * Callback for save_code_slot tool.
	 *
	 * @param array<string, mixed> $args Tool arguments.
	 * @return array<string, mixed>
	 */
	public static function tool_save_code_slot( array $args ): array {
		$has_code        = array_key_exists( 'code', $args );
		$has_edits       = ! empty( $args['edits'] ) && is_array( $args['edits'] );
		$has_skip_admins = array_key_exists( 'skip_admins', $args );

		if ( ! $has_code && ! $has_edits && ! $has_skip_admins ) {
			return array(
				'success' => false,
				'error'   => 'empty_payload',
				'message' => 'At least one of code, edits, or skip_admins must be provided.',
			);
		}

		if ( $has_code && $has_edits ) {
			return array(
				'success' => false,
				'error'   => 'conflicting_payload',
				'message' => 'Cannot provide both code and edits simultaneously.',
			);
		}

		$slot = isset( $args['slot'] ) ? sanitize_key( (string) $args['slot'] ) : '';
		if ( ( $has_code || $has_edits ) && ( empty( $slot ) || ! isset( self::SLOTS[ $slot ] ) ) ) {
			return array(
				'success' => false,
				'error'   => 'missing_slot',
				'message' => sprintf( 'A valid slot is required when updating code. Valid slots: %s.', implode( ', ', array_keys( self::SLOTS ) ) ),
			);
		}

		$changes  = array();
		$warnings = array();

		if ( $has_code || $has_edits ) {
			$option_name  = self::SLOTS[ $slot ];
			$current_code = (string) get_option( $option_name, '' );
			$new_code     = '';

			if ( $has_code ) {
				$new_code = (string) $args['code'];
			} else {
				$new_code = $current_code;
				foreach ( $args['edits'] as $edit ) {
					$find    = (string) ( $edit['find'] ?? '' );
					$replace = (string) ( $edit['replace'] ?? '' );
					$patch   = Align_Agent_Patch_Engine::patch_string( $new_code, $find, $replace );
					if ( ! $patch['success'] ) {
						return array(
							'success' => false,
							'error'   => $patch['error'] ?? 'patch_failed',
							'message' => $patch['message'] ?? 'Failed to apply surgical edit to code slot.',
						);
					}
					$new_code = $patch['content'];
				}
			}

			// Run guard verification.
			$guard = self::guard_code( $new_code );
			if ( ! $guard['ok'] ) {
				return array(
					'success' => false,
					'error'   => $guard['error'] ?? 'guard_rejected',
					'message' => $guard['message'] ?? 'Code content failed security verification.',
				);
			}

			$warnings             = array_merge( $warnings, $guard['warnings'] );
			$changes[ $option_name ] = $new_code;
		}

		if ( $has_skip_admins ) {
			$changes[ self::SKIP_ADMINS_OPTION ] = ! empty( $args['skip_admins'] ) ? 1 : 0;
		}

		$write_result = Align_Agent_Option_Writer::write( 'save_code_slot', $changes );

		$msg = 'Sitewide code slot updated successfully. Clear page cache if a caching plugin is active.';
		if ( ! empty( $warnings ) ) {
			$msg .= ' Warnings: ' . implode( '; ', $warnings );
		}

		return array(
			'success'  => true,
			'message'  => $msg,
			'changed'  => $write_result['changed'],
			'warnings' => $warnings,
			'ui_card'  => array(
				'title'   => 'Sitewide Code Slot Updated',
				'message' => sprintf( 'Saved %d option(s) (%s).', count( $write_result['changed'] ), implode( ', ', $write_result['changed'] ) ),
			),
		);
	}

	/**
	 * Guard and validate code slot payload.
	 *
	 * @param string $code Raw code string.
	 * @return array{ok: bool, error?: string, message?: string, warnings: array<string>}
	 */
	public static function guard_code( string $code ): array {
		$warnings = array();

		if ( strlen( $code ) > self::MAX_BYTES ) {
			return array(
				'ok'      => false,
				'error'   => 'too_large',
				'message' => sprintf( 'Code size exceeds maximum limit of %d bytes.', self::MAX_BYTES ),
				'warnings' => array(),
			);
		}

		if ( preg_match( '/<\?(?:php|=)/i', $code ) ) {
			return array(
				'ok'      => false,
				'error'   => 'php_forbidden',
				'message' => 'PHP opening tags are strictly forbidden in custom code slots.',
				'warnings' => array(),
			);
		}

		// Disallow non-https external scripts: <script ... src="http://..." or src="//..." without https:
		if ( preg_match( '#<script\b[^>]*\bsrc\s*=\s*["\']?(?!https://)[^"\'>]+#i', $code ) ) {
			return array(
				'ok'      => false,
				'error'   => 'insecure_script_src',
				'message' => 'External script tags must use secure HTTPS URLs.',
				'warnings' => array(),
			);
		}

		if ( preg_match( '#\bon[a-z]+\s*=#i', $code ) || preg_match( '#javascript:#i', $code ) ) {
			$warnings[] = 'Code contains inline event handlers or javascript: pseudo-protocols.';
		}

		// Extract inline script contents and run Content Guard check.
		if ( preg_match_all( '#<script\b[^>]*>(.*?)</script>#is', $code, $matches ) ) {
			foreach ( $matches[1] as $inline_js ) {
				$trimmed_js = trim( $inline_js );
				if ( '' !== $trimmed_js ) {
					$js_check = Align_Agent_Content_Guard::check_js( $trimmed_js );
					if ( ! empty( $js_check['warnings'] ) ) {
						$warnings = array_merge( $warnings, $js_check['warnings'] );
					}
				}
			}
		}

		return array(
			'ok'       => true,
			'warnings' => array_unique( $warnings ),
		);
	}

	/**
	 * Provide approval summary for save_code_slot tool execution.
	 *
	 * @param array<string, mixed> $args Tool arguments.
	 * @return array<string, mixed>
	 */
	public static function summarize_save_code_slot( array $args ): array {
		$slot       = isset( $args['slot'] ) ? sanitize_key( (string) $args['slot'] ) : '';
		$slot_label = ucwords( str_replace( '_', ' ', $slot ?: 'code slot' ) );

		$title = sprintf( 'Update %s code', $slot_label );
		$desc  = '⚠️ This code runs on every page of the website.';

		$preview_js = '';
		$ext_hosts  = array();
		$bytes_before = 0;
		$bytes_after  = 0;

		if ( ! empty( $slot ) && isset( self::SLOTS[ $slot ] ) ) {
			$current      = (string) get_option( self::SLOTS[ $slot ], '' );
			$bytes_before = strlen( $current );

			$new_code = $current;
			if ( array_key_exists( 'code', $args ) ) {
				$new_code = (string) $args['code'];
			} elseif ( ! empty( $args['edits'] ) && is_array( $args['edits'] ) ) {
				foreach ( $args['edits'] as $edit ) {
					$patch = Align_Agent_Patch_Engine::patch_string( $new_code, (string) ( $edit['find'] ?? '' ), (string) ( $edit['replace'] ?? '' ) );
					if ( $patch['success'] ) {
						$new_code = $patch['content'];
					}
				}
			}
			$bytes_after = strlen( $new_code );

			// Collect inline scripts.
			if ( preg_match_all( '#<script\b[^>]*>(.*?)</script>#is', $new_code, $matches ) ) {
				$preview_js = implode( "\n;\n", array_filter( array_map( 'trim', $matches[1] ) ) );
			}

			// Collect external script hosts.
			if ( preg_match_all( '#<script\b[^>]*\bsrc\s*=\s*["\']?([^"\'>\s]+)#i', $new_code, $src_matches ) ) {
				foreach ( $src_matches[1] as $src_url ) {
					$host = wp_parse_url( $src_url, PHP_URL_HOST );
					if ( $host ) {
						$ext_hosts[] = $host;
					}
				}
			}
		}

		$meta = array(
			'slot'             => $slot,
			'bytes_before'     => $bytes_before,
			'bytes_after'      => $bytes_after,
			'external_scripts' => array_values( array_unique( $ext_hosts ) ),
		);

		if ( isset( $args['skip_admins'] ) ) {
			$meta['skip_admins'] = (bool) $args['skip_admins'];
		}

		return array(
			'title'       => $title,
			'description' => $desc,
			'preview_js'  => substr( $preview_js, 0, 1500 ),
			'meta'        => $meta,
		);
	}
}
