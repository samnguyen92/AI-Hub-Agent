<?php
/**
 * Align Agent - Comment Moderation & Interaction Tools
 *
 * Registers comment management tools:
 * - list_comments (read)
 * - moderate_comment (write, requires approval)
 * - reply_comment (write, requires approval)
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_Comments {

	/**
	 * Initialize comment tools.
	 */
	public static function init(): void {
		self::register_read_tools();
		self::register_write_tools();
	}

	/** Register read comment tools. */
	private static function register_read_tools(): void {
		Align_Agent_Tool_Registry::register( 'list_comments', array(
			'type'        => 'read',
			'group'       => 'comments',
			'capability'  => 'moderate_comments',
			'description' => 'List WordPress comments with optional status filter (hold, approve, spam, trash, all), post ID, and limit.',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'status'  => array( 'type' => 'string', 'description' => 'Comment status: hold, approve, spam, trash, or all (default all).' ),
					'post_id' => array( 'type' => 'integer', 'description' => 'Optional post ID to filter comments for a specific post.' ),
					'number'  => array( 'type' => 'integer', 'description' => 'Number of comments to return (default 20, max 50).' ),
					'search'  => array( 'type' => 'string', 'description' => 'Optional keyword search in comment text or author.' ),
				),
			),
			'callback'    => array( __CLASS__, 'tool_list_comments' ),
		) );
	}

	/** Register write comment tools. */
	private static function register_write_tools(): void {
		Align_Agent_Tool_Registry::register( 'moderate_comment', array(
			'type'             => 'write',
			'group'            => 'comments',
			'capability'       => 'moderate_comments',
			'undo'             => array( 'adapter' => 'comment' ),
			'description'      => 'Change the status of a comment (approve, hold, spam, or trash). Requires user approval.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'comment_id' => array( 'type' => 'integer', 'description' => 'Numeric comment ID.' ),
					'status'     => array( 'type' => 'string', 'description' => 'Target status: approve, hold, spam, or trash.' ),
				),
				'required'   => array( 'comment_id', 'status' ),
			),
			'callback'         => array( __CLASS__, 'tool_moderate_comment' ),
			'summary_callback' => array( __CLASS__, 'summarize_moderate_comment' ),
		) );

		Align_Agent_Tool_Registry::register( 'reply_comment', array(
			'type'             => 'write',
			'group'            => 'comments',
			'capability'       => 'moderate_comments',
			'undo'             => array( 'adapter' => 'create' ),
			'description'      => 'Post an official reply to an existing comment as the logged-in administrator. Requires user approval.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'comment_id' => array( 'type' => 'integer', 'description' => 'Parent comment ID to reply to.' ),
					'content'    => array( 'type' => 'string', 'description' => 'Reply comment text.' ),
				),
				'required'   => array( 'comment_id', 'content' ),
			),
			'callback'         => array( __CLASS__, 'tool_reply_comment' ),
			'summary_callback' => array( __CLASS__, 'summarize_reply_comment' ),
		) );
	}

	/** Execute list_comments tool. */
	public static function tool_list_comments( array $args = array() ): array {
		$number  = min( 50, max( 1, absint( $args['number'] ?? 20 ) ) );
		$status  = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : 'all';
		$post_id = absint( $args['post_id'] ?? 0 );
		$search  = isset( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : '';

		$valid_statuses = array( 'hold', 'approve', 'spam', 'trash', 'all' );
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			$status = 'all';
		}

		$query_args = array(
			'number'  => $number,
			'status'  => $status,
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
		);

		if ( $post_id > 0 ) {
			$query_args['post_id'] = $post_id;
		}
		if ( '' !== $search ) {
			$query_args['search'] = $search;
		}

		$comments = get_comments( $query_args );
		$items    = array();

		foreach ( $comments as $c ) {
			$status_label = '1' === (string) $c->comment_approved ? 'approved' : ( '0' === (string) $c->comment_approved ? 'pending' : (string) $c->comment_approved );
			$items[] = array(
				'comment_id'     => (int) $c->comment_ID,
				'post_id'        => (int) $c->comment_post_ID,
				'post_title'     => (string) get_the_title( $c->comment_post_ID ),
				'author'         => (string) $c->comment_author,
				'author_email'   => (string) $c->comment_author_email,
				'content'        => wp_strip_all_tags( (string) $c->comment_content ),
				'status'         => $status_label,
				'date'           => (string) $c->comment_date,
				'parent_id'      => (int) $c->comment_parent,
			);
		}

		return array(
			'success'  => true,
			'total'    => count( $items ),
			'comments' => $items,
		);
	}

	/** Summarize moderate_comment for approval card. */
	public static function summarize_moderate_comment( array $args ): array {
		$comment_id = absint( $args['comment_id'] ?? 0 );
		$status     = sanitize_key( (string) ( $args['status'] ?? 'approve' ) );
		$comment    = $comment_id > 0 ? get_comment( $comment_id ) : null;
		$author     = $comment ? $comment->comment_author : '#' . $comment_id;
		return array(
			'title'        => sprintf( __( 'Moderate Comment #%1$d (%2$s)', 'align-agent' ), $comment_id, $status ),
			'description'  => sprintf( __( 'Set status of comment #%1$d from "%2$s" to "%3$s".', 'align-agent' ), $comment_id, $author, $status ),
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => array( 'comment_id' => $comment_id, 'status' => $status ),
		);
	}

	/** Execute moderate_comment tool. */
	public static function tool_moderate_comment( array $args = array() ) {
		$comment_id = absint( $args['comment_id'] ?? 0 );
		$status     = sanitize_key( (string) ( $args['status'] ?? '' ) );

		$valid = array( 'approve', 'hold', 'spam', 'trash' );
		if ( ! in_array( $status, $valid, true ) ) {
			return new WP_Error( 'invalid_status', 'Status must be one of: approve, hold, spam, trash.' );
		}

		$comment = $comment_id > 0 ? get_comment( $comment_id ) : null;
		if ( ! $comment ) {
			return new WP_Error( 'comment_not_found', sprintf( 'Comment #%d was not found.', $comment_id ) );
		}

		$status_map = array(
			'approve' => 'approve',
			'hold'    => 'hold',
			'spam'    => 'spam',
			'trash'   => 'trash',
		);

		$res = wp_set_comment_status( $comment_id, $status_map[ $status ] );
		if ( ! $res ) {
			return new WP_Error( 'update_failed', sprintf( 'Failed to update status for comment #%d.', $comment_id ) );
		}

		error_log( sprintf( '[Align Agent] Moderated comment #%d to status "%s".', $comment_id, $status ) );

		return array(
			'success'    => true,
			'comment_id' => $comment_id,
			'status'     => $status,
			'message'    => sprintf( 'Comment #%1$d status changed to "%2$s".', $comment_id, $status ),
			'ui_card'    => array(
				'title'   => sprintf( 'Comment #%d Moderated', $comment_id ),
				'message' => sprintf( 'Status set to "%s" for comment by %s.', $status, $comment->comment_author ),
				'url'     => admin_url( 'comment.php?action=editcomment&c=' . $comment_id ),
			),
		);
	}

	/** Summarize reply_comment for approval card. */
	public static function summarize_reply_comment( array $args ): array {
		$comment_id = absint( $args['comment_id'] ?? 0 );
		$comment    = $comment_id > 0 ? get_comment( $comment_id ) : null;
		$author     = $comment ? $comment->comment_author : '#' . $comment_id;
		$content    = sanitize_text_field( (string) ( $args['content'] ?? '' ) );
		return array(
			'title'        => sprintf( __( 'Reply to Comment #%d', 'align-agent' ), $comment_id ),
			'description'  => sprintf( __( 'Post official reply to comment by %1$s: "%2$s"', 'align-agent' ), $author, wp_trim_words( $content, 15 ) ),
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => array( 'comment_id' => $comment_id ),
		);
	}

	/** Execute reply_comment tool. */
	public static function tool_reply_comment( array $args = array() ) {
		$comment_id = absint( $args['comment_id'] ?? 0 );
		$content    = trim( (string) ( $args['content'] ?? '' ) );

		if ( empty( $content ) ) {
			return new WP_Error( 'missing_content', 'Reply comment content cannot be empty.' );
		}

		$parent = $comment_id > 0 ? get_comment( $comment_id ) : null;
		if ( ! $parent ) {
			return new WP_Error( 'comment_not_found', sprintf( 'Parent comment #%d was not found.', $comment_id ) );
		}

		$current_user = wp_get_current_user();
		$author_name  = $current_user && $current_user->exists() ? $current_user->display_name : 'Site Administrator';
		$author_email = $current_user && $current_user->exists() ? $current_user->user_email : get_option( 'admin_email' );
		$author_url   = $current_user && $current_user->exists() ? $current_user->user_url : home_url();

		$data = array(
			'comment_post_ID'      => (int) $parent->comment_post_ID,
			'comment_parent'       => $comment_id,
			'comment_author'       => $author_name,
			'comment_author_email' => $author_email,
			'comment_author_url'   => $author_url,
			'comment_content'      => wp_kses_post( $content ),
			'comment_type'         => 'comment',
			'user_id'              => $current_user ? (int) $current_user->ID : 0,
			'comment_approved'     => 1,
		);

		$new_id = wp_insert_comment( $data );
		if ( ! $new_id ) {
			return new WP_Error( 'insert_failed', 'Failed to insert reply comment.' );
		}

		error_log( sprintf( '[Align Agent] Inserted reply comment #%d to parent comment #%d.', $new_id, $comment_id ) );

		return array(
			'success'          => true,
			'reply_comment_id' => (int) $new_id,
			'parent_id'        => $comment_id,
			'post_id'          => (int) $parent->comment_post_ID,
			'message'          => sprintf( 'Reply posted successfully (Comment #%d).', $new_id ),
			'ui_card'          => array(
				'title'   => sprintf( 'Reply Posted (#%d)', $new_id ),
				'message' => sprintf( 'Replied to comment by %s.', $parent->comment_author ),
				'url'     => admin_url( 'comment.php?action=editcomment&c=' . $new_id ),
			),
		);
	}
}
