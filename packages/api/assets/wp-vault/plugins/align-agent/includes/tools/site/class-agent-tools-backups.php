<?php
/**
 * Align Agent - Global Vault Backup Tools
 *
 * Registers read tools (list_backups, search_backups, diff_backup) and write tool
 * (restore_backup) for inspecting, searching, diffing, and restoring Global Vault snapshots.
 *
 * @package Align_Agent
 * @subpackage Includes\Tools\Site
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_Backups {

	/** Initialize and register Global Vault tools. */
	public static function init(): void {
		self::register_read_tools();
		self::register_write_tools();
	}

	/** Register read-only backup inspection tools. */
	private static function register_read_tools(): void {
		Align_Agent_Tool_Registry::register( 'list_backups', array(
			'type' => 'read', 'group' => 'site', 'scope' => 'local', 'capability' => 'edit_theme_options', 'requires_builder' => false, 'danger' => false,
			'description' => 'List Global Vault backups for templates, template parts, reusable blocks, sitewide CSS/JS, and code slots.',
			'parameters'  => array(
				'type' => 'object',
				'properties' => array(
					'object_key' => array( 'type' => 'string', 'description' => 'Optional object key filter (e.g. tp:header, tpl:404, block:12, option:align_custom_css).' ),
					'limit'      => array( 'type' => 'integer', 'description' => 'Maximum number of backups to return (default 20, max 50).' ),
				),
			),
			'callback' => array( __CLASS__, 'tool_list_backups' ),
		) );

		Align_Agent_Tool_Registry::register( 'search_backups', array(
			'type' => 'read', 'group' => 'site', 'scope' => 'local', 'capability' => 'edit_theme_options', 'requires_builder' => false, 'danger' => false,
			'description' => 'Search inside up to 10 recent Global Vault backups of a specific object_key for a matching text or JS function.',
			'parameters'  => array(
				'type' => 'object',
				'properties' => array(
					'object_key' => array( 'type' => 'string', 'description' => 'Object key to search (e.g. tp:header, option:align_custom_js).' ),
					'text'       => array( 'type' => 'string', 'description' => 'Substring or function name to search for.' ),
				),
				'required' => array( 'object_key', 'text' ),
			),
			'callback' => array( __CLASS__, 'tool_search_backups' ),
		) );

		Align_Agent_Tool_Registry::register( 'diff_backup', array(
			'type' => 'read', 'group' => 'site', 'scope' => 'local', 'capability' => 'edit_theme_options', 'requires_builder' => false, 'danger' => false,
			'description' => 'Generate a line-by-line diff (up to 200 lines) between a Global Vault backup and the current live state.',
			'parameters'  => array(
				'type' => 'object',
				'properties' => array( 'backup_id' => array( 'type' => 'integer', 'description' => 'Numeric ID of the Global Vault backup.' ) ),
				'required' => array( 'backup_id' ),
			),
			'callback' => array( __CLASS__, 'tool_diff_backup' ),
		) );
	}

	/** Register restore_backup write tool (hard-gated, global scope, danger: true). */
	private static function register_write_tools(): void {
		Align_Agent_Tool_Registry::register( 'restore_backup', array(
			'type' => 'write', 'undo' => array( 'adapter' => 'none' ), 'group' => 'site', 'scope' => 'global',
			'capability' => 'edit_theme_options', 'requires_builder' => false, 'danger' => true,
			'description' => 'Restore a global object (template, template part, reusable block, or sitewide code option) from a Global Vault backup. Automatically saves a backup of the current live state before restoring. Always requires explicit user approval.',
			'parameters'  => array(
				'type' => 'object',
				'properties' => array( 'backup_id' => array( 'type' => 'integer', 'description' => 'Numeric ID of the Global Vault backup to restore.' ) ),
				'required' => array( 'backup_id' ),
			),
			'callback'         => array( __CLASS__, 'tool_restore_backup' ),
			'summary_callback' => array( __CLASS__, 'summarize_restore_backup' ),
		) );
	}

	/** Callback for list_backups. */
	public static function tool_list_backups( array $args = array() ): array {
		if ( class_exists( 'Align_Agent_Vault_Gc' ) ) {
			Align_Agent_Vault_Gc::mark_foreign_backups();
		}

		$object_key = isset( $args['object_key'] ) ? sanitize_text_field( (string) $args['object_key'] ) : '';
		$limit      = min( 50, max( 1, absint( $args['limit'] ?? 20 ) ) );
		$query_args = array(
			'post_type' => Align_Agent_Global_Vault::CPT_BACKUP, 'post_status' => 'private',
			'posts_per_page' => $limit, 'orderby' => 'ID', 'order' => 'DESC',
		);
		if ( '' !== $object_key ) {
			$query_args['title'] = $object_key;
		}

		$posts   = get_posts( $query_args );
		$backups = array();
		foreach ( $posts as $p ) {
			$meta = json_decode( (string) $p->post_excerpt, true );
			$meta = is_array( $meta ) ? $meta : array();
			if ( ! empty( $meta['home_url'] ) && $meta['home_url'] !== home_url() ) {
				continue;
			}
			$backups[] = array(
				'backup_id'    => (int) $p->ID,
				'object_key'   => (string) $p->post_title,
				'hash'         => (string) $p->post_name,
				'created_gmt'  => (string) $p->post_date_gmt,
				'tool'         => (string) ( $meta['tool'] ?? '' ),
				'fields'       => $meta['fields'] ?? array(),
				'size_raw'     => (int) ( $meta['size_raw'] ?? 0 ),
				'size_gz'      => (int) ( $meta['size_gz'] ?? 0 ),
				'chunks'       => (int) ( $meta['chunks'] ?? 1 ),
				'reason'       => (string) ( $meta['reason'] ?? '' ),
				'js_functions' => $meta['js_functions'] ?? array(),
			);
		}

		return array( 'success' => true, 'total' => count( $backups ), 'backups' => $backups );
	}

	/** Callback for search_backups. */
	public static function tool_search_backups( array $args = array() ): array {
		$object_key = sanitize_text_field( (string) ( $args['object_key'] ?? '' ) );
		$needle     = (string) ( $args['text'] ?? '' );
		if ( '' === $object_key || '' === $needle ) {
			return array( 'success' => false, 'error' => 'missing_params', 'message' => 'Both object_key and text are required.' );
		}

		if ( class_exists( 'Align_Agent_Vault_Gc' ) ) {
			Align_Agent_Vault_Gc::mark_foreign_backups();
		}

		$posts   = get_posts( array(
			'post_type' => Align_Agent_Global_Vault::CPT_BACKUP, 'post_status' => 'private',
			'title' => $object_key, 'posts_per_page' => 10, 'orderby' => 'ID', 'order' => 'DESC',
		) );
		$matches = array();

		foreach ( $posts as $p ) {
			$read = Align_Agent_Global_Vault::read_payload( (int) $p->ID );
			if ( ! $read['success'] || empty( $read['payload'] ) ) {
				continue;
			}
			foreach ( $read['payload'] as $field => $content ) {
				if ( 'post_content' === $field && isset( $read['payload']['html'] ) ) {
					continue;
				}
				$pos = stripos( (string) $content, $needle );
				if ( false !== $pos ) {
					$start     = max( 0, $pos - 80 );
					$matches[] = array(
						'backup_id'   => (int) $p->ID,
						'object_key'  => $object_key,
						'created_gmt' => (string) $p->post_date_gmt,
						'field'       => (string) $field,
						'snippet'     => substr( (string) $content, $start, strlen( $needle ) + 160 ),
					);
				}
			}
		}

		return array( 'success' => true, 'object_key' => $object_key, 'matches' => $matches );
	}

	/** Callback for diff_backup. */
	public static function tool_diff_backup( array $args = array() ): array {
		$backup_id = absint( $args['backup_id'] ?? 0 );
		if ( $backup_id <= 0 ) {
			return array( 'success' => false, 'error' => 'invalid_backup_id', 'message' => 'A valid backup_id is required.' );
		}

		$read = Align_Agent_Global_Vault::read_payload( $backup_id );
		if ( ! $read['success'] ) {
			return array( 'success' => false, 'error' => $read['error'] ?? 'not_found', 'message' => 'Backup not found or unreadable.' );
		}

		$post       = $read['post'];
		$payload    = $read['payload'];
		$object_key = (string) $post->post_title;
		$fields     = array_values( array_diff( array_keys( $payload ), array( 'post_content' ) ) ) ?: array_keys( $payload );
		$live       = Align_Agent_Global_Vault::read_live_state( $object_key, (int) $post->post_parent, $fields );
		$diff_lines = array();

		foreach ( $fields as $f ) {
			$old_lines = explode( "\n", (string) ( $payload[ $f ] ?? '' ) );
			$new_lines = explode( "\n", (string) ( $live[ $f ] ?? '' ) );
			if ( $old_lines === $new_lines ) {
				continue;
			}
			$diff_lines[] = sprintf( '--- backup#%d:%s', $backup_id, $f );
			$diff_lines[] = sprintf( '+++ live:%s', $f );
			$max          = max( count( $old_lines ), count( $new_lines ) );
			for ( $i = 0; $i < $max; $i++ ) {
				$o = $old_lines[ $i ] ?? null;
				$n = $new_lines[ $i ] ?? null;
				if ( $o === $n ) {
					continue;
				}
				if ( null !== $o ) {
					$diff_lines[] = sprintf( '- [L%d] %s', $i + 1, $o );
				}
				if ( null !== $n ) {
					$diff_lines[] = sprintf( '+ [L%d] %s', $i + 1, $n );
				}
				if ( count( $diff_lines ) >= 200 ) {
					$diff_lines   = array_slice( $diff_lines, 0, 200 );
					$diff_lines[] = '... (truncated at 200 lines)';
					break 2;
				}
			}
		}

		return array( 'success' => true, 'backup_id' => $backup_id, 'object_key' => $object_key, 'identical' => empty( $diff_lines ), 'diff' => implode( "\n", $diff_lines ) );
	}

	/** Build approval summary for restore_backup, including base_changed detection. */
	public static function summarize_restore_backup( array $args ): array {
		$backup_id = absint( $args['backup_id'] ?? 0 );
		$read      = $backup_id > 0 ? Align_Agent_Global_Vault::read_payload( $backup_id ) : array( 'success' => false );

		if ( empty( $read['success'] ) ) {
			return array(
				'title' => sprintf( __( 'Restore Backup #%d (Not Found)', 'align-agent' ), $backup_id ),
				'description' => __( 'The specified backup could not be loaded.', 'align-agent' ),
				'danger' => true, 'meta' => array( 'backup_id' => $backup_id, 'danger' => true ),
			);
		}

		$post         = $read['post'];
		$object_key   = (string) $post->post_title;
		$payload      = $read['payload'];
		$fields       = is_array( $read['meta']['fields'] ?? null ) ? $read['meta']['fields'] : array_keys( $payload );
		$live         = Align_Agent_Global_Vault::read_live_state( $object_key, (int) $post->post_parent, $fields );
		$base_changed = ( md5( (string) wp_json_encode( $live ) ) !== $post->post_name );

		$desc = sprintf( __( '⚠️ Restore "%1$s" from backup #%2$d (created %3$s UTC). Current live state will be backed up first.', 'align-agent' ), $object_key, $backup_id, $post->post_date_gmt );
		if ( $base_changed ) {
			$desc .= "\n" . __( 'Notice: Live content has changed since this backup was captured (base_changed).', 'align-agent' );
		}

		return array(
			'title'        => sprintf( __( 'Restore Global Backup #%1$d (%2$s)', 'align-agent' ), $backup_id, $object_key ),
			'description'  => $desc,
			'danger'       => true,
			'base_changed' => $base_changed,
			'preview_html' => (string) ( $payload['html'] ?? '' ),
			'preview_css'  => (string) ( $payload['css'] ?? ( 0 === strpos( $object_key, 'option:align_custom_css' ) ? ( $payload['value'] ?? '' ) : '' ) ),
			'preview_js'   => (string) ( $payload['js'] ?? ( 0 === strpos( $object_key, 'option:align_custom_js' ) ? ( $payload['value'] ?? '' ) : '' ) ),
			'meta'         => array( 'backup_id' => $backup_id, 'object_key' => $object_key, 'base_changed' => $base_changed, 'danger' => true ),
		);
	}

	/** Callback for restore_backup write tool. */
	public static function tool_restore_backup( array $args = array() ): array {
		if ( class_exists( 'Align_Agent_Vault_Gc' ) ) {
			Align_Agent_Vault_Gc::mark_foreign_backups();
		}

		$backup_id = absint( $args['backup_id'] ?? 0 );
		if ( $backup_id <= 0 ) {
			return array( 'success' => false, 'error' => 'invalid_backup_id', 'message' => 'A valid backup_id is required.' );
		}

		$read = Align_Agent_Global_Vault::read_payload( $backup_id );
		if ( ! $read['success'] ) {
			return array( 'success' => false, 'error' => $read['error'] ?? 'not_found', 'message' => 'Backup not found or unreadable.' );
		}

		$post       = $read['post'];
		$meta       = $read['meta'];
		$payload    = $read['payload'];
		$object_key = (string) $post->post_title;

		if ( 'foreign' === $post->post_status || ( ! empty( $meta['home_url'] ) && $meta['home_url'] !== home_url() ) ) {
			return array( 'success' => false, 'error' => 'foreign_backup_forbidden', 'message' => 'Restoring backups created on a different domain is forbidden.' );
		}

		$fields    = is_array( $meta['fields'] ?? null ) ? $meta['fields'] : array_keys( $payload );
		$live      = Align_Agent_Global_Vault::read_live_state( $object_key, (int) $post->post_parent, $fields );
		$pre_store = Align_Agent_Global_Vault::store( $object_key, $live, array(
			'tool' => 'restore_backup', 'fields' => $fields, 'parent_id' => (int) $post->post_parent,
			'user_id' => get_current_user_id(), 'channel' => class_exists( 'Align_Agent_Conversation' ) ? Align_Agent_Conversation::get_channel() : 'chatbox',
			'reason' => 'pre_restore', 'protect_ids' => array( $backup_id ),
		) );

		if ( empty( $pre_store['success'] ) ) {
			return array( 'success' => false, 'error' => $pre_store['error'] ?? 'backup_failed', 'message' => 'Could not snapshot current live state before restore. Operation aborted.' );
		}

		$restored = Align_Agent_Global_Vault::restore_internal( $backup_id );
		if ( empty( $restored['success'] ) ) {
			return $restored;
		}

		$pre_id = (int) ( $pre_store['backup_id'] ?? 0 );
		return array(
			'success'               => true,
			'restored_backup_id'    => $backup_id,
			'pre_restore_backup_id' => $pre_id,
			'backup_id'             => $pre_id,
			'object_key'            => $object_key,
			'message'               => sprintf( __( 'Restored "%1$s" from backup #%2$d (pre-restore snapshot saved as #%3$d).', 'align-agent' ), $object_key, $backup_id, $pre_id ),
			'ui_card'               => array(
				'title'   => sprintf( __( 'Restored Backup #%d', 'align-agent' ), $backup_id ),
				'message' => sprintf( __( 'Target: %1$s | Pre-restore backup: #%2$d', 'align-agent' ), $object_key, $pre_id ),
				'danger'  => true,
			),
		);
	}
}
