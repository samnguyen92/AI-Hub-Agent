<?php
/**
 * Align Agent - Page Clone Application Tools
 *
 * Registers the apply_page_clone tool to promote draft clone changes into live pages.
 *
 * @package Align_Agent
 * @subpackage Includes\Tools\Content
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_Clone {

	/**
	 * Register clone promotion tools.
	 */
	public static function init(): void {
		Align_Agent_Tool_Registry::register(
			'apply_page_clone',
			array(
				'type'             => 'write',
				'undo'             => array( 'adapter' => 'post', 'id_arg' => 'clone_id' ),
				'group'            => 'content',
				'scope'            => 'global',
				'requires_builder' => true,
				'danger'           => false,
				'hidden'           => true,
				'description'      => 'Promote changes from a reviewed draft clone back into the original live page.',
				'parameters'       => array(
					'type'       => 'object',
					'properties' => array(
						'clone_id' => array(
							'type'        => 'integer',
							'description' => 'Numeric ID of the draft clone to apply.',
						),
					),
					'required'   => array( 'clone_id' ),
				),
				'callback'         => array( __CLASS__, 'tool_apply_page_clone' ),
				'summary_callback' => array( __CLASS__, 'summarize_apply_page_clone' ),
			)
		);
	}

	/**
	 * Build summary for approval card.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 */
	public static function summarize_apply_page_clone( array $args ): array {
		$clone_id = absint( $args['clone_id'] ?? 0 );
		$orig_id  = $clone_id > 0 ? absint( get_post_meta( $clone_id, '_align_agent_clone_of', true ) ) : 0;
		$orig     = $orig_id > 0 ? get_post( $orig_id ) : null;
		$title    = $orig ? $orig->post_title : ( '#' . $orig_id );
		$preview  = $clone_id > 0 ? (string) get_preview_post_link( $clone_id ) : '';

		return array(
			'title'        => sprintf( __( 'Apply Draft Clone #%1$d to Live Page "%2$s"', 'align-agent' ), $clone_id, $title ),
			'description'  => sprintf( __( 'Review draft changes and promote to live published page #%d.', 'align-agent' ), $orig_id ),
			'preview_html' => '',
			'preview_css'  => '',
			'url'          => $preview,
			'meta'         => array(
				'clone_id'    => $clone_id,
				'original_id' => $orig_id,
				'preview_url' => $preview,
			),
		);
	}

	/**
	 * Execute apply_page_clone tool.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 */
	public static function tool_apply_page_clone( array $args = array() ): array {
		$clone_id = absint( $args['clone_id'] ?? 0 );
		if ( $clone_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'missing_clone_id',
				'message' => __( 'Missing or invalid clone_id.', 'align-agent' ),
			);
		}

		if ( ! class_exists( 'Align_Agent_Page_Clone' ) ) {
			return array(
				'success' => false,
				'error'   => 'clone_engine_missing',
				'message' => __( 'Page Clone engine is not loaded.', 'align-agent' ),
			);
		}

		return Align_Agent_Page_Clone::apply( $clone_id );
	}
}
