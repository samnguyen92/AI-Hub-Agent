<?php
/**
 * Align Agent Header & Template Part Navigation Tools
 *
 * Provides inspection and modification of <nav> menus located within template parts
 * (such as header.html or tp_header). Synchronizes desktop and mobile nav fragments.
 *
 * @package Align_Agent
 * @subpackage Includes\Tools\Structure
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-agent-nav-dom-engine.php';

class Align_Agent_Tools_Navigation {

	/**
	 * Register navigation tools.
	 *
	 * @return void
	 */
	public static function init(): void {
		Align_Agent_Tool_Registry::register( 'list_nav_links', array(
			'type'             => 'read',
			'group'            => 'structure',
			'scope'            => 'local',
			'capability'       => 'edit_theme_options',
			'requires_builder' => false,
			'danger'           => false,
			'description'      => 'List navigation links from <nav> tags inside a template part (default: tp_header), including aria-labels, URLs, labels, and synchronization status across desktop and mobile navs.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'tp_id' => array(
						'type'        => 'string',
						'description' => 'Template part identifier (e.g. tp_header, tp_footer). Defaults to tp_header.',
					),
				),
			),
			'callback'         => array( __CLASS__, 'tool_list_nav_links' ),
		) );

		Align_Agent_Tool_Registry::register( 'update_nav_links', array(
			'type'             => 'write',
			'undo'             => array( 'adapter' => 'template', 'id_arg' => 'tp_id' ),
			'group'            => 'structure',
			'scope'            => 'global',
			'capability'       => 'edit_theme_options',
			'requires_builder' => true,
			'danger'           => true,
			'description'      => 'Add, remove, rename, change href, or reorder navigation links across template part nav fragments (desktop and mobile synced). Requires edit_theme_options capability and user approval.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'tp_id'      => array(
						'type'        => 'string',
						'description' => 'Template part identifier (e.g. tp_header). Defaults to tp_header.',
					),
					'nav_index'  => array(
						'type'        => 'integer',
						'description' => 'Optional 0-based nav index to apply changes to. Omit to apply identically across all navs (desktop + mobile drawer).',
					),
					'operations' => array(
						'type'        => 'array',
						'description' => 'List of 1 to 20 menu operations to apply sequentially.',
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'op'         => array(
									'type' => 'string',
									'enum' => array( 'add', 'remove', 'rename', 'change_href', 'move' ),
								),
								'href'       => array(
									'type'        => 'string',
									'description' => 'Target link href (exact match after trim). Required except for add.',
								),
								'label'      => array(
									'type'        => 'string',
									'description' => 'Text label for link.',
								),
								'new_href'   => array(
									'type'        => 'string',
									'description' => 'New URL for add or change_href.',
								),
								'after_href' => array(
									'type'        => 'string',
									'description' => 'Insert or move after this link href. Omit to append at the end.',
								),
							),
							'required'   => array( 'op' ),
						),
					),
				),
				'required'   => array( 'operations' ),
			),
			'callback'         => array( __CLASS__, 'tool_update_nav_links' ),
			'summary_callback' => array( __CLASS__, 'summarize_update_nav_links' ),
		) );
	}

	/**
	 * Callback for list_nav_links tool.
	 *
	 * @param array<string, mixed> $args Tool arguments.
	 * @return array<string, mixed>
	 */
	public static function tool_list_nav_links( array $args = array() ): array {
		$tp_id = ! empty( $args['tp_id'] ) ? sanitize_key( (string) $args['tp_id'] ) : 'tp_header';

		$template = Align_Agent_Tools_Templates::tool_get_template( array( 'tp_id' => $tp_id ) );
		if ( empty( $template['success'] ) ) {
			return $template;
		}

		$html      = (string) ( $template['html'] ?? '' );
		$fragments = Align_Agent_Nav_Dom_Engine::find_nav_fragments( $html );

		if ( empty( $fragments ) ) {
			return array(
				'success' => true,
				'tp_id'   => $tp_id,
				'navs'    => array(),
				'in_sync' => true,
				'message' => sprintf( 'No <nav> tags found in template part "%s". Use get_template to inspect the full structure.', $tp_id ),
			);
		}

		$navs_data    = array();
		$level0_hrefs = array();

		foreach ( $fragments as $idx => $frag ) {
			$doc = Align_Agent_Nav_Dom_Engine::load_fragment( $frag['html'] );
			if ( ! $doc ) {
				continue;
			}

			$root = $doc->getElementById( '__align_nav_root' );
			if ( ! $root ) {
				continue;
			}

			$nav_elem = null;
			foreach ( $root->childNodes as $child ) {
				if ( $child instanceof DOMElement && 'nav' === strtolower( $child->tagName ) ) {
					$nav_elem = $child;
					break;
				}
			}

			if ( ! $nav_elem ) {
				continue;
			}

			$aria_label = $nav_elem->getAttribute( 'aria-label' );
			$class_attr = $nav_elem->getAttribute( 'class' );
			$links      = Align_Agent_Nav_Dom_Engine::extract_links( $nav_elem );

			$lvl0 = array();
			foreach ( $links as $lnk ) {
				if ( 0 === $lnk['depth'] ) {
					$lvl0[] = Align_Agent_Nav_Dom_Engine::normalize_href( $lnk['href'] );
				}
			}
			$level0_hrefs[ $idx ] = $lvl0;

			$navs_data[] = array(
				'index'      => $idx,
				'aria_label' => $aria_label,
				'class'      => $class_attr,
				'links'      => $links,
			);
		}

		$in_sync = true;
		if ( count( $level0_hrefs ) > 1 ) {
			$first = reset( $level0_hrefs );
			foreach ( $level0_hrefs as $h_list ) {
				if ( $h_list !== $first ) {
					$in_sync = false;
					break;
				}
			}
		}

		return array(
			'success' => true,
			'tp_id'   => $tp_id,
			'navs'    => $navs_data,
			'in_sync' => $in_sync,
			'message' => sprintf( 'Found %d navigation menu(s) in "%s". Menus in sync: %s.', count( $navs_data ), $tp_id, $in_sync ? 'yes' : 'no' ),
		);
	}

	/**
	 * Callback for update_nav_links tool.
	 *
	 * @param array<string, mixed> $args Tool arguments.
	 * @return array<string, mixed>
	 */
	public static function tool_update_nav_links( array $args ): array {
		$tp_id = ! empty( $args['tp_id'] ) ? sanitize_key( (string) $args['tp_id'] ) : 'tp_header';
		$ops   = isset( $args['operations'] ) && is_array( $args['operations'] ) ? $args['operations'] : array();

		if ( empty( $ops ) ) {
			return array(
				'success' => false,
				'error'   => 'empty_operations',
				'message' => 'operations array cannot be empty.',
			);
		}

		if ( count( $ops ) > 20 ) {
			return array(
				'success' => false,
				'error'   => 'too_many_operations',
				'message' => 'Maximum 20 operations allowed per call.',
			);
		}

		$template = Align_Agent_Tools_Templates::tool_get_template( array( 'tp_id' => $tp_id ) );
		if ( empty( $template['success'] ) ) {
			return $template;
		}

		$nav_index = isset( $args['nav_index'] ) ? (int) $args['nav_index'] : null;

		$applied = Align_Agent_Nav_Dom_Engine::apply_operations( (string) ( $template['html'] ?? '' ), $ops, $nav_index );
		if ( ! $applied['ok'] ) {
			return array(
				'success' => false,
				'error'   => $applied['error'] ?? 'apply_failed',
				'message' => $applied['message'] ?? 'Failed to apply navigation changes.',
			);
		}

		$save_res = Align_Agent_Tools_Templates::tool_save_template( array(
			'tp_id' => $tp_id,
			'html'  => $applied['html'],
			'css'   => $template['css'] ?? '',
			'js'    => $template['js'] ?? '',
		) );

		if ( empty( $save_res['success'] ) ) {
			return $save_res;
		}

		$current_user_id = get_current_user_id();
		Align_Agent_Audit_Log::log_action(
			$current_user_id,
			'update_nav_links',
			$tp_id,
			'update',
			array(
				'operations'   => $ops,
				'nav_index'    => $nav_index,
				'before_links' => $applied['before'],
				'after_links'  => $applied['after'],
			)
		);

		$msg = sprintf( 'Navigation links updated successfully in "%s".', $tp_id );
		if ( ! empty( $applied['warnings'] ) ) {
			$msg .= ' Warnings: ' . implode( '; ', $applied['warnings'] );
		}

		return array(
			'success'  => true,
			'tp_id'    => $tp_id,
			'message'  => $msg,
			'warnings' => $applied['warnings'],
			'ui_card'  => array(
				'title'   => 'Navigation Updated',
				'message' => sprintf( 'Applied %d operations across navigation menus in "%s".', count( $ops ), $tp_id ),
			),
		);
	}

	/**
	 * Provide approval summary for update_nav_links.
	 *
	 * @param array<string, mixed> $args Tool arguments.
	 * @return array<string, mixed>
	 */
	public static function summarize_update_nav_links( array $args ): array {
		$tp_id    = ! empty( $args['tp_id'] ) ? sanitize_key( (string) $args['tp_id'] ) : 'tp_header';
		$ops      = isset( $args['operations'] ) && is_array( $args['operations'] ) ? $args['operations'] : array();
		$template = Align_Agent_Tools_Templates::tool_get_template( array( 'tp_id' => $tp_id ) );

		if ( empty( $template['success'] ) ) {
			return array(
				'title'       => 'Menu update (invalid template)',
				'description' => sprintf( 'Failed to load template part "%s".', $tp_id ),
				'meta'        => array(),
			);
		}

		$nav_index = isset( $args['nav_index'] ) ? (int) $args['nav_index'] : null;
		$applied   = Align_Agent_Nav_Dom_Engine::apply_operations( (string) ( $template['html'] ?? '' ), $ops, $nav_index );

		if ( ! $applied['ok'] ) {
			return array(
				'title'       => 'Menu update (invalid)',
				'description' => sprintf( 'Error applying operations: %s', $applied['message'] ?? 'Unknown error' ),
				'meta'        => array(),
			);
		}

		$desc_lines = array();
		foreach ( $ops as $op ) {
			$action = $op['op'] ?? '';
			switch ( $action ) {
				case 'add':
					$after        = ! empty( $op['after_href'] ) ? ' (after ' . $op['after_href'] . ')' : '';
					$desc_lines[] = sprintf( '➕ %s → %s%s', $op['label'] ?? 'Link', $op['new_href'] ?? $op['href'] ?? '', $after );
					break;
				case 'remove':
					$desc_lines[] = sprintf( '➖ %s', $op['href'] ?? '' );
					break;
				case 'rename':
					$desc_lines[] = sprintf( '✏️ Rename %s → "%s"', $op['href'] ?? '', $op['label'] ?? '' );
					break;
				case 'change_href':
					$desc_lines[] = sprintf( '🔗 Change href %s → %s', $op['href'] ?? '', $op['new_href'] ?? '' );
					break;
				case 'move':
					$desc_lines[] = sprintf( '↕️ Move %s (after %s)', $op['href'] ?? '', $op['after_href'] ?? 'end' );
					break;
			}
		}

		if ( ! empty( $applied['warnings'] ) ) {
			$desc_lines[] = "\n" . implode( "\n", $applied['warnings'] );
		}

		$preview_html = '';
		$after_first  = reset( $applied['after'] );
		if ( is_array( $after_first ) ) {
			$links_preview = array();
			foreach ( $after_first as $l ) {
				$links_preview[] = sprintf( '<a href="%s">%s</a>', esc_attr( $l['href'] ), esc_html( $l['label'] ) );
			}
			$preview_html = '<nav>' . implode( ' ', $links_preview ) . '</nav>';
		}

		return array(
			'title'        => sprintf( 'Update Navigation in %s', $tp_id ),
			'description'  => implode( "\n", $desc_lines ),
			'preview_html' => $preview_html,
			'meta'         => array(
				'navs_affected' => count( $applied['after'] ),
				'tp_id'         => $tp_id,
			),
		);
	}
}
