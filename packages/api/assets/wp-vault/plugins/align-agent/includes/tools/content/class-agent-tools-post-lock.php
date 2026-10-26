<?php
/**
 * Align Agent Post Password Lock Tools
 *
 * Provides inspection and modification of individual post password protection.
 * Password values are never included in tool outputs, logs, or approval details.
 *
 * @package Align_Agent
 * @subpackage Includes\Tools\Content
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_Post_Lock {

	/**
	 * Register post lock tools into Align_Agent_Tool_Registry.
	 *
	 * @return void
	 */
	public static function init(): void {
		Align_Agent_Tool_Registry::register( 'get_post_lock', array(
			'type'             => 'read',
			'group'            => 'content',
			'scope'            => 'local',
			'capability'       => 'edit_posts',
			'requires_builder' => false,
			'danger'           => false,
			'description'      => 'Check if a specific post or page has password protection enabled.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Target post or page ID.',
					),
				),
				'required'   => array( 'post_id' ),
			),
			'callback'         => array( __CLASS__, 'tool_get_post_lock' ),
		) );

		Align_Agent_Tool_Registry::register( 'set_post_lock', array(
			'type'             => 'write',
			'undo'             => array( 'adapter' => 'post', 'id_arg' => 'post_id' ),
			'group'            => 'content',
			'scope'            => 'local',
			'capability'       => 'edit_posts',
			'requires_builder' => false,
			'danger'           => false,
			'description'      => 'Enable or disable password protection on a post or page. When locking a live published post, user approval is required.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'  => array(
						'type'        => 'integer',
						'description' => 'Target post or page ID.',
					),
					'enabled'  => array(
						'type'        => 'boolean',
						'description' => 'True to lock post with password, false to remove password protection.',
					),
					'password' => array(
						'type'        => 'string',
						'description' => 'Password (4..64 characters). Required when enabled is true.',
					),
				),
				'required'   => array( 'post_id', 'enabled' ),
			),
			'callback'         => array( __CLASS__, 'tool_set_post_lock' ),
			'summary_callback' => array( __CLASS__, 'summarize_set_post_lock' ),
		) );
	}

	/**
	 * Callback for get_post_lock.
	 */
	public static function tool_get_post_lock( array $args ): array {
		$post_id = absint( $args['post_id'] ?? 0 );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return array(
				'success' => false,
				'error'   => 'post_not_found',
				'message' => 'Post does not exist.',
			);
		}

		$is_locked = class_exists( 'Align_Post_Password_Lock' ) && method_exists( 'Align_Post_Password_Lock', 'is_post_locked' )
			? Align_Post_Password_Lock::is_post_locked( $post_id )
			: ! empty( $post->post_password );

		return array(
			'success' => true,
			'post_id' => $post_id,
			'title'   => $post->post_title,
			'status'  => $post->post_status,
			'locked'  => $is_locked,
			'message' => sprintf( 'Post %d password protection is currently %s.', $post_id, $is_locked ? 'active' : 'disabled' ),
		);
	}

	/**
	 * Callback for set_post_lock.
	 */
	public static function tool_set_post_lock( array $args ): array {
		$post_id = absint( $args['post_id'] ?? 0 );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return array(
				'success' => false,
				'error'   => 'post_not_found',
				'message' => 'Post does not exist.',
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array(
				'success' => false,
				'error'   => 'forbidden',
				'message' => 'You do not have permission to edit this post.',
			);
		}

		$enabled = ! empty( $args['enabled'] );

		if ( ! class_exists( 'Align_Post_Password_Lock' ) ) {
			return array(
				'success' => false,
				'error'   => 'theme_feature_unavailable',
				'message' => 'Align_Post_Password_Lock class not available in theme.',
			);
		}

		$was_locked = Align_Post_Password_Lock::is_post_locked( $post_id );

		if ( $enabled ) {
			$password = (string) ( $args['password'] ?? '' );
			if ( strlen( $password ) < 4 || strlen( $password ) > 64 ) {
				return array(
					'success' => false,
					'error'   => 'invalid_password',
					'message' => 'Password length must be between 4 and 64 characters.',
				);
			}
			Align_Post_Password_Lock::set_password( $post_id, $password );
		} else {
			Align_Post_Password_Lock::disable_lock( $post_id );
		}

		$current_user_id = get_current_user_id();
		Align_Agent_Audit_Log::log_action(
			$current_user_id,
			'set_post_lock',
			$post_id,
			$enabled ? 'lock' : 'unlock',
			array(
				'enabled_before' => $was_locked,
				'enabled_after'  => $enabled,
			)
		);

		$status_msg = $enabled
			? sprintf( 'Page "%s" is now password protected.', $post->post_title )
			: sprintf( 'Password protection removed from "%s".', $post->post_title );

		return array(
			'success' => true,
			'post_id' => $post_id,
			'locked'  => $enabled,
			'message' => $status_msg,
			'ui_card' => array(
				'title'   => $enabled ? 'Post Locked' : 'Post Unlocked',
				'message' => $status_msg,
			),
		);
	}

	/**
	 * Provide approval summary for set_post_lock.
	 */
	public static function summarize_set_post_lock( array $args ): array {
		$post_id = absint( $args['post_id'] ?? 0 );
		$post    = get_post( $post_id );
		$title   = $post ? $post->post_title : 'Post #' . $post_id;
		$enabled = ! empty( $args['enabled'] );

		$desc = $enabled
			? sprintf( 'Enable password protection for post "%s". Visitors will be prompted for password.', $title )
			: sprintf( 'Remove password protection from post "%s".', $title );

		return array(
			'title'       => sprintf( '%s Post "%s"', $enabled ? 'Lock' : 'Unlock', $title ),
			'description' => $desc,
			'meta'        => array(
				'post_id'  => $post_id,
				'enabled'  => $enabled,
				'password' => $enabled ? '••••' : null,
			),
		);
	}
}
