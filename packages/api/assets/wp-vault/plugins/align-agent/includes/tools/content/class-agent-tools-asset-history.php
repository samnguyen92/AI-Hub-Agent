<?php
/**
 * Align Agent - Asset History Tools
 *
 * Provides inspection and restoration tools for CSS and JS assets backed up
 * by Align_Builder_Asset_History.
 *
 * @package Align_Agent
 * @subpackage Includes\Tools\Content
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_Asset_History {

	/**
	 * Register asset history tools with Align_Agent_Tool_Registry.
	 */
	public static function init(): void {
		self::register_read_tools();
		self::register_write_tools();
	}

	/**
	 * Register read tool for listing snapshots.
	 */
	private static function register_read_tools(): void {
		Align_Agent_Tool_Registry::register(
			'list_asset_history',
			array(
				'type'        => 'read',
				'group'       => 'content',
				'capability'  => 'edit_theme_options',
				'description' => 'List available snapshot history backups for a specific CSS or JS asset file.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'basename' => array(
							'type'        => 'string',
							'description' => 'Target asset file basename (e.g. style-page-goi-subscription.css).',
						),
					),
					'required'   => array( 'basename' ),
				),
				'callback'    => array( __CLASS__, 'tool_list_asset_history' ),
			)
		);
	}

	/**
	 * Register write tool for restoring a snapshot.
	 */
	private static function register_write_tools(): void {
		Align_Agent_Tool_Registry::register(
			'restore_asset_history',
			array(
				'type'             => 'write',
				'undo'             => array( 'adapter' => 'post', 'id_arg' => 'post_id' ),
				'requires_builder' => true,
				'group'            => 'content',
				'capability'       => 'edit_theme_options',
				'description'      => 'Restore a CSS or JS asset file from a history snapshot. Requires user approval.',
				'parameters'       => array(
					'type'       => 'object',
					'properties' => array(
						'basename'  => array(
							'type'        => 'string',
							'description' => 'Target asset file basename (e.g. style-page-goi-subscription.css).',
						),
						'timestamp' => array(
							'type'        => 'integer',
							'description' => 'Optional snapshot timestamp to restore. Omit or pass 0 for newest snapshot.',
						),
					),
					'required'   => array( 'basename' ),
				),
				'callback'         => array( __CLASS__, 'tool_restore_asset_history' ),
				'summary_callback' => array( __CLASS__, 'summarize_restore_asset_history' ),
			)
		);
	}

	/**
	 * Build summary for restore_asset_history approval card.
	 *
	 * @param array $args Tool arguments.
	 * @return array Approval card summary.
	 */
	public static function summarize_restore_asset_history( array $args ): array {
		$basename  = basename( sanitize_file_name( (string) ( $args['basename'] ?? '' ) ) );
		$timestamp = absint( $args['timestamp'] ?? 0 );
		$label     = $timestamp > 0 ? sprintf( '%s (timestamp: %d)', $basename, $timestamp ) : sprintf( '%s (newest snapshot)', $basename );

		return array(
			'title'        => sprintf( __( 'Restore Asset Snapshot: %s', 'align-agent' ), $basename ),
			'description'  => sprintf( __( 'Revert asset file %s to snapshot backup.', 'align-agent' ), $label ),
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => array(
				'basename'  => $basename,
				'timestamp' => $timestamp,
			),
		);
	}

	/**
	 * Execute list_asset_history tool.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error Tool response.
	 */
	public static function tool_list_asset_history( array $args = array() ): array|WP_Error {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to view asset history.', 'align-agent' ),
				array( 'status' => 403 )
			);
		}

		if ( ! class_exists( 'Align_Builder_Asset_History' ) ) {
			return array(
				'success' => false,
				'error'   => 'history_unavailable',
				'message' => __( 'Align Builder Asset History service is unavailable.', 'align-agent' ),
			);
		}

		$basename = basename( sanitize_file_name( (string) ( $args['basename'] ?? '' ) ) );
		if ( empty( $basename ) ) {
			return array(
				'success' => false,
				'error'   => 'missing_basename',
				'message' => __( 'Asset basename is required.', 'align-agent' ),
			);
		}

		$ext = strtolower( (string) pathinfo( $basename, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'css', 'js' ), true ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_extension',
				'message' => __( 'Only .css and .js asset files are supported.', 'align-agent' ),
			);
		}

		$snapshots = Align_Builder_Asset_History::list_snapshots( $basename );

		return array(
			'success'   => true,
			'basename'  => $basename,
			'total'     => count( $snapshots ),
			'snapshots' => $snapshots,
		);
	}

	/**
	 * Execute restore_asset_history tool.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error Tool response.
	 */
	public static function tool_restore_asset_history( array $args = array() ): array|WP_Error {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to restore asset history.', 'align-agent' ),
				array( 'status' => 403 )
			);
		}

		if ( ! class_exists( 'Align_Builder_Asset_History' ) ) {
			return array(
				'success' => false,
				'error'   => 'history_unavailable',
				'message' => __( 'Align Builder Asset History service is unavailable.', 'align-agent' ),
			);
		}

		$basename  = basename( sanitize_file_name( (string) ( $args['basename'] ?? '' ) ) );
		$timestamp = absint( $args['timestamp'] ?? 0 );

		if ( empty( $basename ) ) {
			return array(
				'success' => false,
				'error'   => 'missing_basename',
				'message' => __( 'Asset basename is required.', 'align-agent' ),
			);
		}

		$ext = strtolower( (string) pathinfo( $basename, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'css', 'js' ), true ) ) {
			return array(
				'success' => false,
				'error'   => 'invalid_extension',
				'message' => __( 'Only .css and .js asset files are supported.', 'align-agent' ),
			);
		}

		$ok = Align_Builder_Asset_History::restore( $basename, $timestamp );
		if ( ! $ok ) {
			return array(
				'success' => false,
				'error'   => 'restore_failed',
				'message' => sprintf( __( 'Failed to restore snapshot for %s.', 'align-agent' ), $basename ),
			);
		}

		return array(
			'success'   => true,
			'basename'  => $basename,
			'timestamp' => $timestamp,
			'message'   => sprintf( __( 'Successfully restored %s from snapshot backup.', 'align-agent' ), $basename ),
		);
	}
}
