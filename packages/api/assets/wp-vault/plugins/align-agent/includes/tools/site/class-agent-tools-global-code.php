<?php
/**
 * Align Agent Sitewide Code Tools
 *
 * Provides inspection and modification of sitewide custom CSS and JavaScript options.
 * Enforces unfiltered_html capability and automatic minification via theme hooks.
 *
 * @package Align_Agent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_Global_Code {

	/** @var bool */
	private static bool $registered = false;

	/** Initialize and register global code tools if Align theme asset engine is active. */
	public static function init(): void {
		if ( class_exists( 'Align_Custom_CSS_JS' ) ) {
			self::register_tools();
			return;
		}

		if ( did_action( 'init' ) ) {
			error_log( '[Align Agent] Skipped global code tools: Align_Custom_CSS_JS not available.' );
			return;
		}

		add_action( 'init', array( __CLASS__, 'register_if_available' ), 20 );
	}

	/** Callback on init hook to register tools once theme has loaded. */
	public static function register_if_available(): void {
		if ( self::$registered ) {
			return;
		}

		if ( class_exists( 'Align_Custom_CSS_JS' ) ) {
			self::register_tools();
		} else {
			error_log( '[Align Agent] Skipped global code tools: Align_Custom_CSS_JS not available.' );
		}
	}

	/** Register get_global_code and save_global_code tools. */
	private static function register_tools(): void {
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;
		Align_Agent_Tool_Registry::register( 'get_global_code', array(
			'type'             => 'read',
			'group'            => 'site',
			'capability'       => 'manage_options',
			'requires_builder' => false,
			'danger'           => false,
			'description'      => 'Retrieve sitewide custom CSS, custom JavaScript, and read-only tracking code with byte sizes.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(),
			),
			'callback'         => array( __CLASS__, 'tool_get_global_code' ),
		) );

		Align_Agent_Tool_Registry::register( 'save_global_code', array(
			'type'             => 'write',
			'undo'             => array( 'adapter' => 'option', 'keys' => array( 'align_custom_css', 'align_custom_js' ) ),
			'group'            => 'site',
			'capability'       => 'manage_options',
			'requires_builder' => false,
			'danger'           => true,
			'description'      => 'Update sitewide custom CSS or JavaScript via full replacement or surgical edits. Affects the entire website layout and functionality. Requires unfiltered_html capability and user approval.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'css'   => array(
						'type'        => 'string',
						'description' => 'Complete replacement for sitewide custom CSS.',
					),
					'js'    => array(
						'type'        => 'string',
						'description' => 'Complete replacement for sitewide custom JavaScript.',
					),
					'edits' => array(
						'type'        => 'array',
						'description' => 'Optional surgical search-and-replace edits on sitewide CSS or JS.',
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'field'   => array(
									'type'        => 'string',
									'enum'        => array( 'css', 'js' ),
									'description' => 'Field to edit: "css" or "js".',
								),
								'find'    => array(
									'type'        => 'string',
									'description' => 'Exact string to locate. Must appear exactly once.',
								),
								'replace' => array(
									'type'        => 'string',
									'description' => 'Replacement string.',
								),
							),
							'required'   => array( 'field', 'find', 'replace' ),
						),
					),
				),
			),
			'callback'         => array( __CLASS__, 'tool_save_global_code' ),
			'summary_callback' => array( __CLASS__, 'summarize_save_global_code' ),
		) );
	}

	/** Tool: get_global_code. */
	public static function tool_get_global_code( array $args = array() ): array {
		$css      = (string) get_option( 'align_custom_css', '' );
		$js       = (string) get_option( 'align_custom_js', '' );
		$tracking = (string) get_option( 'align_tracking_code', '' );

		return array(
			'success'        => true,
			'css'            => $css,
			'css_bytes'      => strlen( $css ),
			'js'             => $js,
			'js_bytes'       => strlen( $js ),
			'tracking_code'  => $tracking,
			'tracking_bytes' => strlen( $tracking ),
		);
	}

	/** Build approval summary for save_global_code. */
	public static function summarize_save_global_code( array $args ): array {
		$current_css = (string) get_option( 'align_custom_css', '' );
		$current_js  = (string) get_option( 'align_custom_js', '' );

		$preview_css = $current_css;
		$preview_js  = $current_js;
		$desc_lines  = array();

		if ( ! empty( $args['edits'] ) && is_array( $args['edits'] ) && class_exists( 'Align_Agent_Patch_Engine' ) ) {
			$applied = Align_Agent_Patch_Engine::apply_edits(
				array( 'css' => $current_css, 'js' => $current_js ),
				$args['edits'],
				array( 'css', 'js' )
			);
			if ( ! empty( $applied['success'] ) && ! empty( $applied['fields'] ) ) {
				$preview_css = $applied['fields']['css'];
				$preview_js  = $applied['fields']['js'];
				if ( ! empty( $applied['snippets'] ) ) {
					foreach ( $applied['snippets'] as $s ) {
						$desc_lines[] = sprintf( '[%s] "%s" -> "%s"', strtoupper( (string) $s['field'] ), mb_substr( (string) $s['find'], 0, 30 ), mb_substr( (string) $s['replace'], 0, 30 ) );
					}
				}
			}
		}

		if ( array_key_exists( 'css', $args ) ) {
			$preview_css  = (string) $args['css'];
			$desc_lines[] = __( 'Replace sitewide CSS', 'align-agent' );
		}
		if ( array_key_exists( 'js', $args ) ) {
			$preview_js   = (string) $args['js'];
			$desc_lines[] = __( 'Replace sitewide JS', 'align-agent' );
		}

		$desc = __( 'Update sitewide custom code (align_custom_css / align_custom_js).', 'align-agent' );
		if ( ! empty( $desc_lines ) ) {
			$desc .= "\n" . implode( "\n", $desc_lines );
		}

		return array(
			'title'        => __( 'Update Sitewide Custom Code', 'align-agent' ),
			'description'  => $desc,
			'preview_html' => '',
			'preview_css'  => $preview_css,
			'preview_js'   => $preview_js,
			'danger'       => true,
			'meta'         => array(
				'has_css'   => array_key_exists( 'css', $args ),
				'has_js'    => array_key_exists( 'js', $args ),
				'has_edits' => ! empty( $args['edits'] ),
			),
		);
	}

	/** Tool: save_global_code. */
	public static function tool_save_global_code( array $args = array() ): array {
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			return array(
				'success' => false,
				'error'   => 'unfiltered_html_required',
				'message' => __( 'Updating sitewide code requires unfiltered_html capability to prevent sanitization breakage.', 'align-agent' ),
			);
		}

		$has_css   = array_key_exists( 'css', $args );
		$has_js    = array_key_exists( 'js', $args );
		$has_edits = ! empty( $args['edits'] ) && is_array( $args['edits'] );

		if ( ! $has_css && ! $has_js && ! $has_edits ) {
			return array(
				'success' => false,
				'error'   => 'missing_code_payload',
				'message' => __( 'Provide full "css", "js", or an "edits" array to update sitewide code.', 'align-agent' ),
			);
		}

		$current_css = (string) get_option( 'align_custom_css', '' );
		$current_js  = (string) get_option( 'align_custom_js', '' );
		$target_css  = $current_css;
		$target_js   = $current_js;
		$updated     = array();

		if ( $has_edits ) {
			if ( ! class_exists( 'Align_Agent_Patch_Engine' ) ) {
				return array(
					'success' => false,
					'error'   => 'engine_unavailable',
					'message' => __( 'Patch engine is not available.', 'align-agent' ),
				);
			}

			$applied = Align_Agent_Patch_Engine::apply_edits(
				array( 'css' => $current_css, 'js' => $current_js ),
				$args['edits'],
				array( 'css', 'js' )
			);

			if ( empty( $applied['success'] ) ) {
				return $applied;
			}

			$target_css = (string) $applied['fields']['css'];
			$target_js  = (string) $applied['fields']['js'];

			if ( $target_css !== $current_css ) {
				$updated['align_custom_css'] = true;
			}
			if ( $target_js !== $current_js ) {
				$updated['align_custom_js'] = true;
			}
		}

		if ( $has_css ) {
			$target_css                  = (string) $args['css'];
			$updated['align_custom_css'] = true;
		}

		if ( $has_js ) {
			$target_js                  = (string) $args['js'];
			$updated['align_custom_js'] = true;
		}

		$updated_keys = array_keys( $updated );
		if ( empty( $updated_keys ) ) {
			return array(
				'success' => false,
				'error'   => 'nothing_to_update',
				'message' => __( 'No changes detected in sitewide code.', 'align-agent' ),
			);
		}

		if ( in_array( 'align_custom_css', $updated_keys, true ) && '' !== $target_css && class_exists( 'Align_Gateway_Validator' ) ) {
			$val = Align_Gateway_Validator::validate_markup( '', $target_css );
			if ( empty( $val['valid'] ) ) {
				return array(
					'success'    => false,
					'error'      => 'invalid_css',
					'message'    => __( 'Sitewide CSS validation failed.', 'align-agent' ),
					'validation' => $val,
				);
			}
		}

		if ( in_array( 'align_custom_js', $updated_keys, true ) && '' !== $target_js && class_exists( 'Align_Agent_Content_Guard' ) ) {
			$js_val = Align_Agent_Content_Guard::check_js( $target_js );
			if ( empty( $js_val['valid'] ) ) {
				return array(
					'success' => false,
					'error'   => $js_val['error'] ?? 'forbidden_js_payload',
					'message' => $js_val['message'] ?? __( 'Forbidden JavaScript payload.', 'align-agent' ),
				);
			}
		}

		$snapshot = array();
		if ( class_exists( 'Align_Agent_Audit_Log' ) ) {
			$snapshot = Align_Agent_Audit_Log::snapshot_options( array( 'align_custom_css', 'align_custom_js' ) );
		}

		foreach ( $updated_keys as $key ) {
			if ( 'align_custom_css' === $key ) {
				update_option( 'align_custom_css', $target_css );
			} elseif ( 'align_custom_js' === $key ) {
				update_option( 'align_custom_js', $target_js );
			}
		}

		$keys_str = implode( ', ', $updated_keys );
		error_log( sprintf( '[Align Agent] save_global_code updated %s.', $keys_str ) );

		if ( class_exists( 'Align_Agent_Audit_Log' ) ) {
			Align_Agent_Audit_Log::log_action(
				get_current_user_id(),
				'save_global_code',
				'global_code',
				'update',
				array(
					'keys'     => $updated_keys,
					'snapshot' => $snapshot,
				)
			);
		}

		return array(
			'success'      => true,
			'updated_keys' => $updated_keys,
			'snapshot'     => $snapshot,
			'message'      => sprintf( __( 'Sitewide code updated successfully (%s).', 'align-agent' ), $keys_str ),
			'ui_card'      => array(
				'title'   => __( 'Sitewide Code Updated', 'align-agent' ),
				'message' => sprintf( __( 'Keys updated: %1$s', 'align-agent' ), $keys_str ),
			),
		);
	}
}
