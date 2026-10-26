<?php
/**
 * Align Agent - Global Vault
 *
 * Manages persistent, multi-chunk compressed backups for global scope objects
 * (templates, template parts, reusable blocks, sitewide CSS/JS, and code slots)
 * using hidden CPTs with zero wp_postmeta overhead.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Global_Vault {

	const CPT_BACKUP    = 'align_agent_backup';
	const CPT_CHUNK     = 'align_agent_bk_chunk';
	const MIME_TYPE     = 'align-backup/v1';
	const MAX_RAW_BYTES = 20971520; // 20 MB.
	const CHUNK_SIZE    = 524288;   // 512 KB.

	/** Register hidden backup CPTs and 'foreign' post status. */
	public static function init(): void {
		if ( did_action( 'init' ) ) {
			self::register_types();
		} else {
			add_action( 'init', array( __CLASS__, 'register_types' ), 5 );
		}
	}

	/** Register CPTs and custom post status. */
	public static function register_types(): void {
		$args = array(
			'public' => false, 'show_ui' => false, 'show_in_rest' => false,
			'can_export' => false, 'exclude_from_search' => true, 'rewrite' => false,
			'query_var' => false, 'supports' => array(),
		);
		register_post_type( self::CPT_BACKUP, $args );
		register_post_type( self::CPT_CHUNK, $args );
		register_post_status( 'foreign', array(
			'label' => _x( 'Foreign Backup', 'post status', 'align-agent' ),
			'public' => false, 'exclude_from_search' => true,
			'show_in_admin_all_list' => false, 'show_in_admin_status_list' => false,
		) );
	}

	/**
	 * Determine if a tool + args call targets the Global Vault and return target descriptors.
	 */
	public static function resolve_targets( string $tool, array $args ): array {
		$targets = array();

		if ( 'save_template' === $tool || 'update_nav_links' === $tool || ( 'patch_content' === $tool && 'template' === ( $args['target_type'] ?? '' ) ) ) {
			$resolved  = Align_Agent_Undo_Resolver::resolve_object_id( array( 'adapter' => 'template', 'id_arg' => 'tp_id' ), $args, $tool );
			$slug      = $resolved['slug'] ?: sanitize_file_name( (string) ( $args['tp_id'] ?? 'header' ) );
			$is_tpl    = 'wp_template' === ( $resolved['template_type'] ?? '' );
			$obj_key   = ( $is_tpl ? 'tpl:' : 'tp:' ) . $slug;
			$fields    = self::determine_modified_fields( $tool, $args, array( 'html', 'css', 'js' ) );
			$targets[] = array( 'object_key' => $obj_key, 'parent_id' => (int) $resolved['id'], 'fields' => $fields, 'state' => self::read_live_state( $obj_key, $resolved['id'], $fields ) );
		} elseif ( 'save_reuse_block' === $tool || ( 'patch_content' === $tool && 'reuse_block' === ( $args['target_type'] ?? '' ) ) ) {
			$block_id = absint( $args['post_id'] ?? ( $args['id'] ?? 0 ) );
			if ( $block_id > 0 ) {
				$obj_key   = 'block:' . $block_id;
				$fields    = self::determine_modified_fields( $tool, $args, array( 'html', 'css', 'js' ) );
				$targets[] = array( 'object_key' => $obj_key, 'parent_id' => $block_id, 'fields' => $fields, 'state' => self::read_live_state( $obj_key, $block_id, $fields ) );
			}
		} elseif ( 'save_global_code' === $tool ) {
			$fields = self::determine_modified_fields( $tool, $args, array( 'css', 'js' ) );
			if ( in_array( 'css', $fields, true ) ) {
				$key       = 'option:align_custom_css';
				$targets[] = array( 'object_key' => $key, 'parent_id' => 0, 'fields' => array( 'value' ), 'state' => self::read_live_state( $key, 0, array( 'value' ) ) );
			}
			if ( in_array( 'js', $fields, true ) ) {
				$key       = 'option:align_custom_js';
				$targets[] = array( 'object_key' => $key, 'parent_id' => 0, 'fields' => array( 'value' ), 'state' => self::read_live_state( $key, 0, array( 'value' ) ) );
			}
		} elseif ( 'save_code_slot' === $tool ) {
			$slot_map = array( 'tracking_code' => 'align_tracking_code', 'body_open_code' => 'align_body_open_code', 'footer_html_code' => 'align_footer_html_code' );
			$slot     = sanitize_key( (string) ( $args['slot'] ?? '' ) );
			if ( isset( $slot_map[ $slot ] ) && ( array_key_exists( 'code', $args ) || ! empty( $args['edits'] ) ) ) {
				$key       = 'option:' . $slot_map[ $slot ];
				$targets[] = array( 'object_key' => $key, 'parent_id' => 0, 'fields' => array( 'value' ), 'state' => self::read_live_state( $key, 0, array( 'value' ) ) );
			}
		}

		return $targets;
	}

	/** Determine which fields are about to be modified (REQ-09). */
	private static function determine_modified_fields( string $tool, array $args, array $default ): array {
		if ( 'update_nav_links' === $tool ) {
			return array( 'html' );
		}
		$fields = array();
		foreach ( array( 'html', 'css', 'js' ) as $f ) {
			if ( array_key_exists( $f, $args ) ) {
				$fields[] = $f;
			}
		}
		if ( ! empty( $args['edits'] ) && is_array( $args['edits'] ) ) {
			foreach ( $args['edits'] as $edit ) {
				$ef = sanitize_key( (string) ( $edit['field'] ?? '' ) );
				if ( in_array( $ef, array( 'html', 'css', 'js' ), true ) ) {
					$fields[] = $ef;
				}
			}
		}
		$fields = array_values( array_unique( $fields ) );
		return ! empty( $fields ) ? $fields : $default;
	}

	/** Read live state for a given object_key and requested fields. */
	public static function read_live_state( string $object_key, int $parent_id = 0, array $fields = array( 'html', 'css', 'js' ) ): array {
		if ( 0 === strpos( $object_key, 'option:' ) ) {
			return array( 'value' => (string) get_option( substr( $object_key, 7 ), '' ) );
		}

		if ( 0 === strpos( $object_key, 'block:' ) ) {
			$block_id = $parent_id > 0 ? $parent_id : absint( substr( $object_key, 6 ) );
			$info     = class_exists( 'Align_Agent_Tools_Templates' ) ? Align_Agent_Tools_Templates::tool_get_reuse_block( array( 'post_id' => $block_id ) ) : array();
			$post     = $block_id > 0 ? get_post( $block_id ) : null;
			$out      = array();
			foreach ( $fields as $f ) {
				$out[ $f ] = isset( $info[ $f ] ) ? (string) $info[ $f ] : '';
			}
			if ( $post instanceof WP_Post ) {
				$out['post_content'] = (string) $post->post_content;
			}
			return $out;
		}

		$is_tpl   = 0 === strpos( $object_key, 'tpl:' );
		$slug     = substr( $object_key, $is_tpl ? 4 : 3 );
		$tp_id    = ( $is_tpl ? 'tpl_' : 'tp_' ) . $slug;
		$tpl_type = $is_tpl ? 'wp_template' : 'wp_template_part';
		$info     = class_exists( 'Align_Agent_Tools_Templates' ) ? Align_Agent_Tools_Templates::tool_get_template( array( 'tp_id' => $tp_id, 'template_type' => $tpl_type ) ) : array();
		$out      = array();
		foreach ( $fields as $f ) {
			$out[ $f ] = isset( $info[ $f ] ) ? (string) $info[ $f ] : '';
		}
		if ( $parent_id <= 0 && class_exists( 'Align_Agent_Undo_Resolver' ) ) {
			$res       = Align_Agent_Undo_Resolver::resolve_object_id( array( 'id_arg' => 'tp_id' ), array( 'tp_id' => $tp_id, 'template_type' => $tpl_type ), 'save_template' );
			$parent_id = (int) ( $res['id'] ?? 0 );
			if ( 0 === $parent_id && 'theme_file' === ( $res['type'] ?? '' ) ) {
				$out['post_content'] = (string) ( $res['theme_content'] ?? '' );
			}
		}
		if ( $parent_id > 0 && ( clean_post_cache( $parent_id ) || true ) && ( $post = get_post( $parent_id ) ) instanceof WP_Post ) {
			$out['post_content'] = (string) $post->post_content;
		}
		return $out;
	}

	/** Store a snapshot in the Global Vault with chunking, dedup, and read-back verification. */
	public static function store( string $object_key, array $payload, array $meta = array() ): array {
		if ( class_exists( 'Align_Agent_Vault_Gc' ) ) {
			Align_Agent_Vault_Gc::mark_foreign_backups();
		}

		$raw_json = (string) wp_json_encode( $payload );
		$size_raw = strlen( $raw_json );

		if ( $size_raw > self::MAX_RAW_BYTES || ( isset( $meta['force_size_raw'] ) && (int) $meta['force_size_raw'] > self::MAX_RAW_BYTES ) ) {
			error_log( sprintf( '[Align Agent] Vault rejected oversized payload | key: %s | bytes: %d', $object_key, $size_raw ) );
			return array( 'success' => false, 'error' => 'backup_too_large', 'message' => 'Content exceeds 20 MB backup limit. The edit was blocked.' );
		}

		$content_hash = md5( $raw_json );
		$home_url     = (string) home_url();
		$latest       = get_posts( array( 'post_type' => self::CPT_BACKUP, 'post_status' => 'private', 'title' => $object_key, 'posts_per_page' => 1, 'orderby' => 'ID', 'order' => 'DESC' ) );
		if ( ! empty( $latest ) && $latest[0] instanceof WP_Post ) {
			$l_meta = json_decode( (string) $latest[0]->post_excerpt, true );
			if ( $latest[0]->post_name === $content_hash && ( is_array( $l_meta ) ? (string) ( $l_meta['home_url'] ?? '' ) : '' ) === $home_url ) {
				return array( 'success' => true, 'backup_id' => (int) $latest[0]->ID, 'deduplicated' => true );
			}
		}

		$compressed = @gzcompress( $raw_json, 6 );
		if ( false === $compressed ) {
			return array( 'success' => false, 'error' => 'backup_failed', 'message' => 'Failed to compress backup payload.' );
		}

		$encoded      = base64_encode( $compressed );
		$chunks       = str_split( $encoded, self::CHUNK_SIZE ) ?: array( '' );
		$js_funcs     = self::extract_js_functions( (string) ( $payload['js'] ?? ( $payload['value'] ?? '' ) ) );
		$excerpt_data = array(
			'tool' => (string) ( $meta['tool'] ?? 'manual' ), 'fields' => array_values( $meta['fields'] ?? array_keys( $payload ) ),
			'size_raw' => $size_raw, 'size_gz' => strlen( $compressed ), 'chunks' => count( $chunks ), 'home_url' => $home_url,
			'channel' => (string) ( $meta['channel'] ?? 'chatbox' ), 'user_id' => absint( $meta['user_id'] ?? get_current_user_id() ),
			'reason' => (string) ( $meta['reason'] ?? 'pre_edit' ), 'js_functions' => $js_funcs,
		);

		kses_remove_filters();
		$backup_id = wp_insert_post( array(
			'post_type' => self::CPT_BACKUP, 'post_status' => 'private', 'post_title' => $object_key,
			'post_name' => $content_hash, 'post_parent' => absint( $meta['parent_id'] ?? 0 ),
			'post_excerpt' => wp_slash( (string) wp_json_encode( $excerpt_data ) ),
			'post_content' => wp_slash( $chunks[0] ), 'post_mime_type' => self::MIME_TYPE,
		), true );

		if ( is_wp_error( $backup_id ) || $backup_id <= 0 ) {
			kses_init_filters();
			return array( 'success' => false, 'error' => 'backup_failed', 'message' => 'Failed to insert backup record.' );
		}

		$chunk_ids = array();
		for ( $i = 1, $total_c = count( $chunks ); $i < $total_c; $i++ ) {
			$cid = wp_insert_post( array(
				'post_type' => self::CPT_CHUNK, 'post_status' => 'private', 'post_title' => $object_key . '#chunk-' . $i,
				'post_parent' => (int) $backup_id, 'menu_order' => $i,
				'post_content' => wp_slash( $chunks[ $i ] ), 'post_mime_type' => self::MIME_TYPE,
			), true );
			if ( is_wp_error( $cid ) || $cid <= 0 ) {
				kses_init_filters();
				self::delete_backup_post( (int) $backup_id );
				return array( 'success' => false, 'error' => 'backup_failed', 'message' => 'Failed to write backup chunk.' );
			}
			$chunk_ids[] = (int) $cid;
		}
		kses_init_filters();

		self::purge_postmeta( array_merge( array( (int) $backup_id ), $chunk_ids ) );
		$read_back = self::read_payload( (int) $backup_id );
		if ( ! $read_back['success'] || ( $read_back['hash'] ?? '' ) !== $content_hash ) {
			self::delete_backup_post( (int) $backup_id );
			error_log( sprintf( '[Align Agent] Vault read-back verification failed | key: %s | id: %d', $object_key, $backup_id ) );
			return array( 'success' => false, 'error' => 'backup_failed', 'message' => 'Backup verification failed.' );
		}

		if ( class_exists( 'Align_Agent_Vault_Gc' ) ) {
			Align_Agent_Vault_Gc::enforce( $object_key, (array) ( $meta['protect_ids'] ?? array() ) );
		}
		return array( 'success' => true, 'backup_id' => (int) $backup_id, 'deduplicated' => false );
	}

	/** Read and reassemble a backup payload from the database. */
	public static function read_payload( int $backup_id ): array {
		clean_post_cache( $backup_id );
		$post = get_post( $backup_id );
		if ( ! ( $post instanceof WP_Post ) || self::CPT_BACKUP !== $post->post_type ) {
			return array( 'success' => false, 'error' => 'not_found' );
		}

		$meta       = json_decode( (string) $post->post_excerpt, true );
		$meta       = is_array( $meta ) ? $meta : array();
		$num_chunks = max( 1, absint( $meta['chunks'] ?? 1 ) );
		$encoded    = (string) $post->post_content;

		if ( $num_chunks > 1 ) {
			$children = get_posts( array(
				'post_type' => self::CPT_CHUNK, 'post_status' => array( 'private', 'foreign', 'publish', 'draft' ),
				'post_parent' => $backup_id, 'posts_per_page' => $num_chunks, 'orderby' => 'menu_order', 'order' => 'ASC',
			) );
			if ( count( $children ) !== ( $num_chunks - 1 ) ) {
				return array( 'success' => false, 'error' => 'missing_chunks' );
			}
			foreach ( $children as $child ) {
				clean_post_cache( $child->ID );
				$fresh_c  = get_post( $child->ID );
				$encoded .= $fresh_c ? (string) $fresh_c->post_content : '';
			}
		}

		$compressed = base64_decode( $encoded, true );
		$raw_json   = false !== $compressed ? @gzuncompress( $compressed ) : false;
		$payload    = false !== $raw_json ? json_decode( $raw_json, true ) : null;
		if ( ! is_array( $payload ) ) {
			return array( 'success' => false, 'error' => 'corrupt_payload' );
		}

		return array( 'success' => true, 'post' => $post, 'meta' => $meta, 'payload' => $payload, 'hash' => md5( $raw_json ) );
	}

	/** Read live fields with success status and resolved parent_id. */
	public static function read_live_fields( string $object_key, array $fields = array( 'html', 'css', 'js' ), int $parent_id = 0 ): array {
		if ( 0 === strpos( $object_key, 'option:' ) ) {
			return array( 'success' => true, 'parent_id' => 0, 'state' => array( 'value' => (string) get_option( substr( $object_key, 7 ), '' ) ) );
		}
		if ( 0 === strpos( $object_key, 'block:' ) ) {
			$bid  = $parent_id > 0 ? $parent_id : absint( substr( $object_key, 6 ) );
			$post = $bid > 0 ? ( clean_post_cache( $bid ) || true ? get_post( $bid ) : null ) : null;
			$st   = self::read_live_state( $object_key, $bid, $fields );
			return array( 'success' => $post instanceof WP_Post, 'parent_id' => $post instanceof WP_Post ? (int) $post->ID : 0, 'state' => $st );
		}
		$is_tpl   = 0 === strpos( $object_key, 'tpl:' );
		$slug     = substr( $object_key, $is_tpl ? 4 : 3 );
		$tp_id    = ( $is_tpl ? 'tpl_' : 'tp_' ) . $slug;
		$tpl_type = $is_tpl ? 'wp_template' : 'wp_template_part';
		if ( $parent_id <= 0 && class_exists( 'Align_Agent_Undo_Resolver' ) ) {
			$res       = Align_Agent_Undo_Resolver::resolve_object_id( array( 'id_arg' => 'tp_id' ), array( 'tp_id' => $tp_id, 'template_type' => $tpl_type ), 'save_template' );
			$parent_id = absint( $res['id'] ?? 0 );
		}
		$post = $parent_id > 0 ? ( clean_post_cache( $parent_id ) || true ? get_post( $parent_id ) : null ) : null;
		$st   = self::read_live_state( $object_key, $parent_id, $fields );
		return array( 'success' => $post instanceof WP_Post, 'parent_id' => $post instanceof WP_Post ? (int) $post->ID : 0, 'state' => $st );
	}

	/** Verify post-write state and automatically restore from vault on mismatch (REQ-14). */
	public static function verify_after( array $vault_records, array $args = array() ): array {
		foreach ( $vault_records as $rec ) {
			$obj_key   = (string) ( $rec['object_key'] ?? '' );
			$backup_id = absint( $rec['backup_id'] ?? 0 );
			$fields    = is_array( $rec['fields'] ?? null ) ? $rec['fields'] : array( 'html', 'css', 'js' );
			$read      = self::read_live_fields( $obj_key, $fields, absint( $rec['parent_id'] ?? 0 ) );
			$live      = $read['state'] ?? array();

			$mismatch = empty( $read['success'] );

			if ( ! $mismatch && 0 === strpos( $obj_key, 'option:' ) ) {
				$live_hash = md5( trim( (string) ( $live['value'] ?? '' ) ) );
				if ( isset( $args['css'] ) && 'option:align_custom_css' === $obj_key && $live_hash !== md5( trim( (string) $args['css'] ) ) ) {
					$mismatch = true;
				} elseif ( isset( $args['js'] ) && 'option:align_custom_js' === $obj_key && $live_hash !== md5( trim( (string) $args['js'] ) ) ) {
					$mismatch = true;
				} elseif ( isset( $args['code'] ) && $live_hash !== md5( trim( (string) $args['code'] ) ) ) {
					$mismatch = true;
				}
			} elseif ( ! $mismatch ) {
				if ( absint( $read['parent_id'] ?? 0 ) <= 0 ) {
					$mismatch = true;
				} else {
					foreach ( array( 'html', 'css', 'js' ) as $f ) {
						if ( isset( $args[ $f ] ) && '' !== trim( (string) $args[ $f ] ) && '' === trim( (string) ( $live[ $f ] ?? '' ) ) ) {
							$mismatch = true;
						}
					}
				}
			}

			if ( $mismatch && $backup_id > 0 ) {
				self::restore_by_id( $backup_id );
				error_log( sprintf( '[Align Agent] Write verification failed; auto-restored from vault | key: %s | backup: %d', $obj_key, $backup_id ) );
				return array( 'ok' => false, 'error' => 'write_verify_failed', 'message' => 'Post-write verification failed. Object was automatically restored from Global Vault.' );
			}
		}

		return array( 'ok' => true );
	}

	/** Alias for restore_internal(). */
	public static function restore_by_id( int $backup_id ): array {
		return self::restore_internal( $backup_id );
	}

	/** Internal restore of a backup record without creating a pre-restore backup. */
	public static function restore_internal( int $backup_id ): array {
		$read = self::read_payload( $backup_id );
		if ( ! $read['success'] ) {
			return array( 'success' => false, 'error' => $read['error'] ?? 'read_failed', 'message' => 'Failed to read backup payload.' );
		}

		$post       = $read['post'];
		$meta       = $read['meta'];
		$payload    = $read['payload'];
		$object_key = (string) $post->post_title;

		if ( 'foreign' === $post->post_status || ( ! empty( $meta['home_url'] ) && $meta['home_url'] !== home_url() ) ) {
			return array( 'success' => false, 'error' => 'foreign_backup_forbidden', 'message' => 'Cannot restore a backup created on a different domain.' );
		}

		if ( 0 === strpos( $object_key, 'option:' ) ) {
			Align_Agent_Option_Writer::write( 'restore_backup', array( substr( $object_key, 7 ) => (string) ( $payload['value'] ?? '' ) ) );
			return array( 'success' => true, 'object_key' => $object_key );
		}

		if ( 0 === strpos( $object_key, 'block:' ) ) {
			$block_id = absint( substr( $object_key, 6 ) );
			$cur      = self::read_live_state( $object_key, $block_id, array( 'html', 'css', 'js' ) );
			if ( class_exists( 'Align_Builder_Crud_Handler' ) ) {
				$req = new WP_REST_Request( 'POST', '/align-builder/v1/save-page' );
				$req->set_param( 'post_id', $block_id );
				$req->set_param( 'html', array_key_exists( 'html', $payload ) ? $payload['html'] : ( $cur['html'] ?? '' ) );
				$req->set_param( 'css', array_key_exists( 'css', $payload ) ? $payload['css'] : ( $cur['css'] ?? '' ) );
				$req->set_param( 'js', array_key_exists( 'js', $payload ) ? $payload['js'] : ( $cur['js'] ?? '' ) );
				Align_Builder_Crud_Handler::save_page( $req );
			}
			if ( isset( $payload['post_content'] ) ) {
				kses_remove_filters();
				wp_update_post( array( 'ID' => $block_id, 'post_content' => wp_slash( (string) $payload['post_content'] ) ) );
				kses_init_filters();
			}
			clean_post_cache( $block_id );
			return array( 'success' => true, 'object_key' => $object_key );
		}

		$is_tpl   = 0 === strpos( $object_key, 'tpl:' );
		$slug     = substr( $object_key, $is_tpl ? 4 : 3 );
		$tp_id    = ( $is_tpl ? 'tpl_' : 'tp_' ) . $slug;
		$tpl_type = $is_tpl ? 'wp_template' : 'wp_template_part';
		$cur = self::read_live_state( $object_key, (int) $post->post_parent, array( 'html', 'css', 'js' ) );
		if ( class_exists( 'Align_Builder_Crud_Handler' ) ) {
			$req = new WP_REST_Request( 'POST', '/align-builder/v1/save-page' );
			foreach ( array( 'tp_id' => $tp_id, 'template_type' => $tpl_type, 'html' => $payload['html'] ?? ( $cur['html'] ?? '' ), 'css' => $payload['css'] ?? ( $cur['css'] ?? '' ), 'js' => $payload['js'] ?? ( $cur['js'] ?? '' ) ) as $k => $v ) {
				$req->set_param( $k, $v );
			}
			Align_Builder_Crud_Handler::save_page( $req );
		}

		if ( isset( $payload['post_content'] ) ) {
			$res = Align_Agent_Undo_Resolver::resolve_object_id( array( 'id_arg' => 'tp_id' ), array( 'tp_id' => $tp_id, 'template_type' => $tpl_type ), 'save_template' );
			if ( ! empty( $res['id'] ) ) {
				kses_remove_filters();
				wp_update_post( array( 'ID' => (int) $res['id'], 'post_content' => wp_slash( (string) $payload['post_content'] ) ) );
				kses_init_filters();
				clean_post_cache( (int) $res['id'] );
			}
		}

		return array( 'success' => true, 'object_key' => $object_key );
	}

	/** Delete a vault backup post and all its chunks permanently. */
	public static function delete_backup_post( int $backup_id ): void {
		$post = get_post( $backup_id );
		if ( ! ( $post instanceof WP_Post ) || self::CPT_BACKUP !== $post->post_type ) {
			return;
		}
		$children = get_posts( array(
			'post_type' => self::CPT_CHUNK, 'post_status' => array( 'private', 'foreign', 'publish', 'draft', 'trash' ),
			'post_parent' => $backup_id, 'posts_per_page' => 200, 'fields' => 'ids',
		) );
		self::purge_postmeta( array_merge( array( $backup_id ), array_map( 'intval', $children ) ) );
		foreach ( $children as $cid ) {
			wp_delete_post( (int) $cid, true );
		}
		wp_delete_post( $backup_id, true );
	}

	/** Ensure zero wp_postmeta rows exist for vault backup posts. */
	private static function purge_postmeta( array $post_ids ): void {
		foreach ( $post_ids as $pid ) {
			foreach ( array_keys( (array) get_post_meta( $pid ) ) as $mkey ) {
				delete_post_meta( $pid, (string) $mkey );
			}
		}
	}

	/** Extract JavaScript function names from JS source code. */
	public static function extract_js_functions( string $js ): array {
		if ( '' === trim( $js ) ) {
			return array();
		}
		$names = array();
		if ( preg_match_all( '/\bfunction\s+([a-zA-Z_$][a-zA-Z0-9_$]*)\s*\(/', $js, $m1 ) ) {
			$names = array_merge( $names, $m1[1] );
		}
		if ( preg_match_all( '/\b(?:const|let|var)\s+([a-zA-Z_$][a-zA-Z0-9_$]*)\s*=\s*(?:async\s*)?(?:function\b|\([^)]*\)\s*=>|[a-zA-Z_$][a-zA-Z0-9_$]*\s*=>)/', $js, $m2 ) ) {
			$names = array_merge( $names, $m2[1] );
		}
		return array_slice( array_values( array_unique( $names ) ), 0, 50 );
	}
}
