<?php
/**
 * Align Agent Content Patch Tool
 *
 * Provides surgical search-and-replace edits on page, post, template, and reusable block content.
 * Enforces strict single-match semantics without rewriting entire files.
 *
 * @package Align_Agent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_Patch {

	/** Initialize and register tool. */
	public static function init(): void {
		self::register();
	}

	/** Register tool in the registry. */
	public static function register(): void {
		Align_Agent_Tool_Registry::register( 'patch_content', array(
			'type'             => 'write',
			'undo'             => array( 'adapter' => 'post', 'id_arg' => 'post_id', 'dynamic_by' => 'target_type' ),
			'group'            => 'content',
			'capability'       => 'edit_posts',
			'requires_builder' => true,
			'description'      => 'Apply surgical search-and-replace edits to HTML, CSS, or JS of a page, post, template, or reusable block without resending the entire content. Requires user approval.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'target_type'   => array(
						'type'        => 'string',
						'enum'        => array( 'page', 'post', 'template', 'reuse_block' ),
						'description' => 'Target entity type to edit: "page", "post", "template", or "reuse_block".',
					),
					'post_id'       => array(
						'type'        => 'integer',
						'description' => 'Numeric WordPress ID (required for page, post, or reuse_block).',
					),
					'tp_id'         => array(
						'type'        => 'string',
						'description' => 'Template part or template ID/slug (required for template, e.g. tp_header, tpl_404).',
					),
					'template_type' => array(
						'type'        => 'string',
						'description' => 'Optional template type for template target: "wp_template" or "wp_template_part".',
					),
					'edits'         => array(
						'type'        => 'array',
						'description' => 'List of 1 to 10 search-and-replace edits.',
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'field'   => array(
									'type'        => 'string',
									'enum'        => array( 'html', 'css', 'js' ),
									'description' => 'Field to edit: "html", "css", or "js".',
								),
								'find'    => array(
									'type'        => 'string',
									'description' => 'Exact string to locate. Must appear exactly once in the target field.',
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
				'required'   => array( 'target_type', 'edits' ),
			),
			'callback'         => array( __CLASS__, 'tool_patch_content' ),
			'summary_callback' => array( __CLASS__, 'summarize_patch_content' ),
		) );

		if ( class_exists( 'Align_Agent_Working_Notes' ) && method_exists( 'Align_Agent_Working_Notes', 'register_tool' ) ) {
			Align_Agent_Working_Notes::register_tool();
		}
	}

	/**
	 * Retrieve current HTML, CSS, and JS for a specified target entity.
	 *
	 * @param string               $target_type Target type: page, post, template, reuse_block.
	 * @param array<string, mixed> $args Tool arguments.
	 * @return array{success: bool, fields?: array<string, string>, error?: string, message?: string, post?: WP_Post}
	 */
	public static function get_target_fields( string $target_type, array $args ): array {
		if ( 'template' === $target_type ) {
			$tp_id = sanitize_text_field( (string) ( $args['tp_id'] ?? '' ) );
			if ( '' === $tp_id ) {
				return array( 'success' => false, 'error' => 'missing_tp_id', 'message' => __( 'A valid tp_id is required for template patching.', 'align-agent' ) );
			}
			$tpl_type = sanitize_key( (string) ( $args['template_type'] ?? '' ) );
			$info     = Align_Agent_Tools_Templates::tool_get_template( array( 'tp_id' => $tp_id, 'template_type' => $tpl_type ) );
			if ( empty( $info['success'] ) ) {
				return array( 'success' => false, 'error' => $info['error'] ?? 'template_not_found', 'message' => $info['message'] ?? __( 'Template not found.', 'align-agent' ) );
			}
			return array(
				'success' => true,
				'fields'  => array(
					'html' => (string) ( $info['html'] ?? '' ),
					'css'  => (string) ( $info['css'] ?? '' ),
					'js'   => (string) ( $info['js'] ?? '' ),
				),
			);
		}

		$post_id = absint( $args['post_id'] ?? $args['id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return array( 'success' => false, 'error' => 'missing_post_id', 'message' => __( 'A valid numeric post_id is required.', 'align-agent' ) );
		}

		if ( 'reuse_block' === $target_type ) {
			$info = Align_Agent_Tools_Templates::tool_get_reuse_block( array( 'post_id' => $post_id ) );
			if ( empty( $info['success'] ) ) {
				return array( 'success' => false, 'error' => $info['error'] ?? 'block_not_found', 'message' => $info['message'] ?? __( 'Reusable block not found.', 'align-agent' ) );
			}
			return array(
				'success' => true,
				'fields'  => array(
					'html' => (string) ( $info['html'] ?? '' ),
					'css'  => (string) ( $info['css'] ?? '' ),
					'js'   => (string) ( $info['js'] ?? '' ),
				),
			);
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'success' => false, 'error' => 'post_not_found', 'message' => sprintf( __( 'Post #%d was not found.', 'align-agent' ), $post_id ) );
		}

		$parts = class_exists( 'Align_Gateway_Pages' )
			? Align_Gateway_Pages::extract_decoupled_parts( $post )
			: array( 'html' => (string) $post->post_content, 'css' => '', 'js' => '' );

		return array(
			'success' => true,
			'post'    => $post,
			'fields'  => array(
				'html' => (string) ( $parts['html'] ?? '' ),
				'css'  => (string) ( $parts['css'] ?? '' ),
				'js'   => (string) ( $parts['js'] ?? '' ),
			),
		);
	}

	/** Build approval card summary for patch_content. */
	public static function summarize_patch_content( array $args ): array {
		$target_type = sanitize_key( (string) ( $args['target_type'] ?? 'page' ) );
		$tp_id       = sanitize_text_field( (string) ( $args['tp_id'] ?? '' ) );
		$post_id     = absint( $args['post_id'] ?? $args['id'] ?? 0 );
		$target_id   = 'template' === $target_type ? $tp_id : '#' . $post_id;
		$edits       = is_array( $args['edits'] ?? null ) ? $args['edits'] : array();
		$danger      = 'template' === $target_type;

		$preview_html = '';
		$preview_css  = '';
		$preview_js   = '';
		$desc_lines   = array();

		$current = self::get_target_fields( $target_type, $args );
		if ( ! empty( $current['success'] ) && ! empty( $current['fields'] ) ) {
			$applied = Align_Agent_Patch_Engine::apply_edits( $current['fields'], $edits );
			if ( ! empty( $applied['success'] ) && ! empty( $applied['fields'] ) ) {
				$preview_html = $applied['fields']['html'];
				$preview_css  = $applied['fields']['css'];
				$preview_js   = $applied['fields']['js'];

				if ( ! empty( $applied['snippets'] ) ) {
					foreach ( $applied['snippets'] as $s ) {
						$f           = strtoupper( (string) $s['field'] );
						$find_str    = trim( (string) preg_replace( '/\s+/', ' ', (string) $s['find'] ) );
						$replace_str = trim( (string) preg_replace( '/\s+/', ' ', (string) $s['replace'] ) );
						$desc_lines[] = sprintf( '• [%s] "%s" → "%s"', $f, mb_substr( $find_str, 0, 35 ), mb_substr( $replace_str, 0, 35 ) );
					}
				}
			}
		}

		$desc = sprintf( __( 'Apply %1$d patch edit(s) to %2$s (%3$s):', 'align-agent' ), count( $edits ), $target_type, $target_id );
		if ( ! empty( $desc_lines ) ) {
			if ( count( $desc_lines ) > 5 ) {
				$more  = count( $desc_lines ) - 5;
				$desc .= "\n" . implode( "\n", array_slice( $desc_lines, 0, 5 ) ) . "\n" . sprintf( __( '... and %d more edit(s)', 'align-agent' ), $more );
			} else {
				$desc .= "\n" . implode( "\n", $desc_lines );
			}
		}

		return array(
			'title'        => sprintf( __( 'Patch %1$s: %2$s', 'align-agent' ), ucfirst( $target_type ), $target_id ),
			'description'  => $desc,
			'preview_html' => $preview_html,
			'preview_css'  => $preview_css,
			'preview_js'   => $preview_js,
			'danger'       => $danger,
			'meta'         => array(
				'target_type' => $target_type,
				'post_id'     => $post_id,
				'tp_id'       => $tp_id,
				'edits_count' => count( $edits ),
			),
		);
	}

	/** Tool: patch_content callback. */
	public static function tool_patch_content( array $args = array() ): array {
		$target_type = sanitize_key( (string) ( $args['target_type'] ?? '' ) );
		if ( ! in_array( $target_type, array( 'page', 'post', 'template', 'reuse_block' ), true ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_target_type',
				'message' => __( 'Invalid target_type. Must be one of page, post, template, reuse_block.', 'align-agent' ),
			);
		}

		if ( 'template' === $target_type && ! current_user_can( 'edit_theme_options' ) ) {
			return array(
				'success' => false,
				'error'   => 'forbidden',
				'message' => __( 'You do not have permission to edit website templates.', 'align-agent' ),
			);
		}

		$edits = $args['edits'] ?? null;
		if ( empty( $edits ) || ! is_array( $edits ) ) {
			return array(
				'success' => false,
				'error'   => 'missing_edits',
				'message' => __( 'A non-empty edits array is required.', 'align-agent' ),
			);
		}

		// Read fresh content from DB
		$current = self::get_target_fields( $target_type, $args );
		if ( empty( $current['success'] ) ) {
			return $current;
		}

		// Apply surgical edits in memory
		$applied = Align_Agent_Patch_Engine::apply_edits( $current['fields'], $edits );
		if ( empty( $applied['success'] ) ) {
			return $applied;
		}

		$fields = $applied['fields'];
		if ( class_exists( 'Align_Agent_Tools_Media' ) ) {
			if ( isset( $fields['html'] ) ) {
				$fields['html'] = Align_Agent_Tools_Media::sanitize_internal_urls_to_dynamic( (string) $fields['html'] );
			}
			if ( isset( $fields['css'] ) ) {
				$fields['css'] = Align_Agent_Tools_Media::sanitize_internal_urls_to_dynamic( (string) $fields['css'] );
			}
		}

		// Delegate save to standard write tool without modifying post status
		switch ( $target_type ) {
			case 'page':
				$save_args = array(
					'post_id' => absint( $args['post_id'] ?? 0 ),
					'html'    => $fields['html'],
					'css'     => $fields['css'],
					'js'      => $fields['js'],
				);
				return Align_Agent_Tools_Pages::tool_save_page( $save_args );

			case 'post':
				$save_args = array(
					'post_id' => absint( $args['post_id'] ?? 0 ),
					'html'    => $fields['html'],
					'css'     => $fields['css'],
					'js'      => $fields['js'],
				);
				return Align_Agent_Tools_Posts::tool_save_post( $save_args );

			case 'template':
				$tp_id     = sanitize_text_field( (string) ( $args['tp_id'] ?? '' ) );
				$tpl_type  = ! empty( $args['template_type'] )
					? sanitize_key( (string) $args['template_type'] )
					: ( 0 === strpos( $tp_id, 'tpl_' ) ? 'wp_template' : 'wp_template_part' );
				$save_args = array(
					'tp_id'         => $tp_id,
					'template_type' => $tpl_type,
					'html'          => $fields['html'],
					'css'           => $fields['css'],
					'js'            => $fields['js'],
				);
				return Align_Agent_Tools_Templates::tool_save_template( $save_args );

			case 'reuse_block':
				$save_args = array(
					'post_id' => absint( $args['post_id'] ?? $args['id'] ?? 0 ),
					'html'    => $fields['html'],
					'css'     => $fields['css'],
					'js'      => $fields['js'],
				);
				return Align_Agent_Tools_Templates::tool_save_reuse_block( $save_args );
		}

		return array(
			'success' => false,
			'error'   => 'unknown_target',
			'message' => __( 'Unsupported target type.', 'align-agent' ),
		);
	}
}
