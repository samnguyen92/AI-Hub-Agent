<?php
/**
 * Align Agent - Undo Adapters
 *
 * Implements capture, capture_after, and revert logic for each undo adapter.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Undo_Adapters {

	/** Capture "before" snapshot prior to write tool execution. */
	public static function capture( string $tool, array $args ): array {
		$spec    = Align_Agent_Tool_Registry::get_undo_spec( $tool );
		$spec    = class_exists( 'Align_Agent_Undo_Resolver' ) ? Align_Agent_Undo_Resolver::resolve_spec( $tool, $spec, $args ) : $spec;
		$adapter = ! empty( $spec['adapter'] ) ? sanitize_key( $spec['adapter'] ) : 'none';

		if ( empty( $spec ) ) {
			error_log( sprintf( '[Align Agent] Undo adapter missing | tool: %s', $tool ) );
		}

		$captured = array(
			'tool'     => $tool,
			'adapter'  => $adapter,
			'object'   => array( 'type' => 'unknown', 'id' => 0 ),
			'label'    => $tool,
			'before'   => array(),
			'undoable' => true,
		);

		switch ( $adapter ) {
			case 'post':
				$resolved = class_exists( 'Align_Agent_Undo_Resolver' )
					? Align_Agent_Undo_Resolver::resolve_object_id( $spec, $args, $tool )
					: array( 'id' => absint( $args[ $spec['id_arg'] ?? 'post_id' ] ?? ( $args['page_id'] ?? ( $args['post_id'] ?? ( $args['id'] ?? 0 ) ) ) ) );
				$post_id  = absint( $resolved['id'] ?? 0 );
				if ( $post_id > 0 ) {
					$post = get_post( $post_id );
					if ( $post instanceof WP_Post ) {
						$captured['object'] = array( 'type' => $post->post_type, 'id' => $post_id );
						$captured['label']  = $post->post_title ?: ( 'Item #' . $post_id );
						$rev_id             = 0;
						if ( post_type_supports( $post->post_type, 'revisions' ) ) {
							$created_rev = wp_save_post_revision( $post_id );
							if ( is_int( $created_rev ) && $created_rev > 0 ) {
								$rev_id = $created_rev;
							} else {
								$recent_revs = wp_get_post_revisions( $post_id, array( 'numberposts' => 1 ) );
								$rev_id      = ! empty( $recent_revs ) ? (int) array_key_first( $recent_revs ) : 0;
							}
						}
						$decoupled          = class_exists( 'Align_Gateway_Pages' ) ? Align_Gateway_Pages::extract_decoupled_parts( $post ) : array();
						$captured['before'] = array(
							'post_title'        => $post->post_title,
							'post_content'      => (string) $post->post_content,
							'content_hash'      => md5( (string) $post->post_content ),
							'post_status'       => $post->post_status,
							'post_name'         => $post->post_name,
							'post_parent'       => $post->post_parent,
							'menu_order'        => $post->menu_order,
							'thumbnail_id'      => get_post_thumbnail_id( $post_id ),
							'revision_id'       => $rev_id,
							'html'              => $decoupled['html'] ?? ( $decoupled['body_html'] ?? $post->post_content ),
							'css'               => $decoupled['css'] ?? '',
							'js'                => $decoupled['js'] ?? '',
							'post_modified_gmt' => $post->post_modified_gmt,
						);
					}
				}
				break;

			case 'template':
				$resolved = class_exists( 'Align_Agent_Undo_Resolver' )
					? Align_Agent_Undo_Resolver::resolve_object_id( $spec, $args, $tool )
					: array( 'id' => absint( $args['template_id'] ?? ( $args['block_id'] ?? ( $args['id'] ?? 0 ) ) ), 'type' => 'unknown', 'tp_id' => '', 'slug' => '' );
				$tpl_id   = absint( $resolved['id'] ?? 0 );

				if ( $tpl_id > 0 ) {
					$post = get_post( $tpl_id );
					if ( $post instanceof WP_Post ) {
						$tp_id = ! empty( $resolved['tp_id'] ) ? $resolved['tp_id'] : ( 'wp_template' === $post->post_type ? 'tpl_' . $post->post_name : ( 'wp_template_part' === $post->post_type ? 'tp_' . $post->post_name : '' ) );
						$captured['object'] = array( 'type' => $post->post_type, 'id' => $tpl_id, 'tp_id' => $tp_id, 'slug' => $post->post_name, 'template_type' => $post->post_type );
						$captured['label']  = $post->post_title ?: ( $tp_id ?: ( 'Template #' . $tpl_id ) );

						if ( 'wp_block' === $post->post_type ) {
							$dec = class_exists( 'Align_Gateway_Pages' ) ? Align_Gateway_Pages::extract_decoupled_parts( $post ) : array();
							if ( class_exists( 'Align_Builder_Assets' ) && ! empty( $post->post_name ) ) {
								$dec['css'] = Align_Builder_Assets::load_external_css( 'style-block-' . $post->post_name, $dec['css'] ?? '' );
								$dec['js']  = Align_Builder_Assets::load_external_js( 'script-block-' . $post->post_name, $dec['js'] ?? '' );
							}
							$html = $dec['html'] ?? ( $dec['body_html'] ?? $post->post_content );
							$css  = $dec['css'] ?? '';
							$js   = $dec['js'] ?? '';
						} else {
							$info = class_exists( 'Align_Agent_Tools_Templates' )
								? Align_Agent_Tools_Templates::tool_get_template( array( 'tp_id' => $tp_id ?: $post->post_name, 'template_type' => $post->post_type ) )
								: array();
							$html = $info['html'] ?? $post->post_content;
							$css  = $info['css'] ?? '';
							$js   = $info['js'] ?? '';
						}

						$captured['before'] = array(
							'post_title'        => $post->post_title,
							'post_content'      => (string) $post->post_content,
							'content_hash'      => md5( (string) $post->post_content ),
							'post_status'       => $post->post_status,
							'tp_id'             => $tp_id,
							'html'              => (string) $html,
							'css'               => (string) $css,
							'js'                => (string) $js,
							'post_modified_gmt' => $post->post_modified_gmt,
						);
					}
				} elseif ( 'theme_file' === ( $resolved['type'] ?? '' ) ) {
					$tp_id    = (string) ( $resolved['tp_id'] ?? '' );
					$tpl_type = (string) ( $resolved['template_type'] ?? ( 0 === strpos( $tp_id, 'tpl_' ) ? 'wp_template' : 'wp_template_part' ) );
					$info     = class_exists( 'Align_Agent_Tools_Templates' )
						? Align_Agent_Tools_Templates::tool_get_template( array( 'tp_id' => $tp_id, 'template_type' => $tpl_type ) )
						: array();
					$raw_cnt  = ! empty( $info['html'] ) ? (string) $info['html'] : (string) ( $resolved['theme_content'] ?? '' );

					$captured['object'] = array( 'type' => 'theme_file', 'id' => 0, 'tp_id' => $tp_id, 'slug' => (string) ( $resolved['slug'] ?? '' ), 'template_type' => $tpl_type );
					$captured['label']  = (string) ( $resolved['title'] ?: $tp_id );
					$captured['before'] = array(
						'post_title'    => (string) ( $resolved['title'] ?? $tp_id ),
						'post_content'  => $raw_cnt,
						'content_hash'  => md5( $raw_cnt ),
						'post_status'   => 'publish',
						'tp_id'         => $tp_id,
						'template_type' => $tpl_type,
						'html'          => (string) ( $info['html'] ?? $raw_cnt ),
						'css'           => (string) ( $info['css'] ?? '' ),
						'js'            => (string) ( $info['js'] ?? '' ),
					);
				}
				break;

			case 'create':
				$captured['object'] = array( 'type' => 'created_item', 'id' => 0 );
				break;

			case 'trash':
				$item_id = absint( $args['post_id'] ?? ( $args['media_id'] ?? ( $args['id'] ?? 0 ) ) );
				if ( $item_id > 0 && ( $post = get_post( $item_id ) ) instanceof WP_Post ) {
					$captured['object'] = array( 'type' => $post->post_type, 'id' => $item_id );
					$captured['label']  = $post->post_title ?: ( 'Item #' . $item_id );
					$captured['before'] = array( 'post_status' => $post->post_status );
				} elseif ( 'delete_redirect' === $tool ) {
					$captured['object'] = array( 'type' => 'redirect', 'id' => (string) ( $args['source'] ?? '' ) );
					$captured['before'] = array( 'rule' => $args );
				}
				break;

			case 'option':
				$keys = ! empty( $spec['keys'] ) && is_array( $spec['keys'] ) ? $spec['keys'] : array();
				if ( empty( $keys ) && ! empty( $args['key'] ) ) {
					$keys = array( (string) $args['key'] );
				} elseif ( empty( $keys ) && ! empty( $args['slot'] ) ) {
					$keys = array( 'align_code_slot_' . sanitize_key( $args['slot'] ) );
				}
				$safe_keys = array();
				foreach ( $keys as $k ) {
					if ( preg_match( '/(?:password|secret|token)/i', $k ) ) {
						$captured['undoable'] = false;
					} else {
						$safe_keys[] = $k;
					}
				}
				$captured['object'] = array( 'type' => 'options', 'keys' => $safe_keys );
				$captured['before'] = class_exists( 'Align_Agent_Audit_Log' ) ? Align_Agent_Audit_Log::snapshot_options( $safe_keys ) : array();
				break;

			case 'meta':
				$post_id            = absint( $args['post_id'] ?? ( $args['id'] ?? 0 ) );
				$meta_keys          = ! empty( $spec['meta_keys'] ) && is_array( $spec['meta_keys'] ) ? $spec['meta_keys'] : array();
				$captured['object'] = array( 'type' => 'post_meta', 'id' => $post_id );
				$captured['before'] = class_exists( 'Align_Agent_Audit_Log' ) ? Align_Agent_Audit_Log::snapshot_post_meta( $post_id, $meta_keys ) : array();
				break;

			case 'term':
				$term_id  = absint( $args['term_id'] ?? ( $args['id'] ?? 0 ) );
				$taxonomy = sanitize_key( (string) ( $args['taxonomy'] ?? 'category' ) );
				$post_id  = absint( $args['post_id'] ?? 0 );
				if ( $post_id > 0 && 'assign_terms' === $tool ) {
					$captured['object'] = array( 'type' => 'object_terms', 'id' => $post_id );
					$captured['before'] = array( 'taxonomy' => $taxonomy, 'terms' => wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) ) );
				} elseif ( $term_id > 0 ) {
					$captured['object'] = array( 'type' => 'term', 'id' => $term_id );
					$captured['before'] = class_exists( 'Align_Agent_Audit_Log' ) ? Align_Agent_Audit_Log::snapshot_term( $term_id, $taxonomy ) : array();
				}
				break;

			case 'user_role':
				$user_id            = absint( $args['user_id'] ?? ( $args['id'] ?? 0 ) );
				$captured['object'] = array( 'type' => 'user', 'id' => $user_id );
				$captured['before'] = class_exists( 'Align_Agent_Audit_Log' ) ? Align_Agent_Audit_Log::snapshot_user( $user_id ) : array();
				break;

			case 'comment':
				$comment_id         = absint( $args['comment_id'] ?? ( $args['id'] ?? 0 ) );
				$comment            = get_comment( $comment_id );
				$captured['object'] = array( 'type' => 'comment', 'id' => $comment_id );
				$captured['before'] = $comment ? array( 'comment_approved' => $comment->comment_approved ) : array();
				break;

			case 'media':
				$captured['object']   = array( 'type' => 'attachment', 'id' => 0 );
				$captured['undoable'] = defined( 'MEDIA_TRASH' ) && MEDIA_TRASH;
				break;

			default:
				$captured['undoable'] = false;
				break;
		}

		return $captured;
	}

	/** Finalize entry with "after" fingerprint following tool execution. */
	public static function capture_after( string $tool, array $args, array $result, array $captured ): array {
		$adapter  = $captured['adapter'];
		$after_fp = array();

		switch ( $adapter ) {
			case 'post':
			case 'template':
				$id = absint( $captured['object']['id'] ?? 0 );
				if ( 0 === $id && ! empty( $captured['object']['tp_id'] ) && class_exists( 'Align_Agent_Undo_Resolver' ) ) {
					$spec     = Align_Agent_Undo_Resolver::resolve_spec( $tool, Align_Agent_Tool_Registry::get_undo_spec( $tool ), $args );
					$resolved = Align_Agent_Undo_Resolver::resolve_object_id( $spec, array_merge( $args, array( 'tp_id' => $captured['object']['tp_id'] ) ), $tool );
					if ( ! empty( $resolved['id'] ) ) {
						$id                       = (int) $resolved['id'];
						$captured['object']['id'] = $id;
						if ( 'theme_file' === ( $captured['object']['type'] ?? '' ) && ! empty( $resolved['type'] ) ) {
							$captured['object']['type'] = $resolved['type'];
						}
					}
				}
				if ( $id > 0 ) {
					clean_post_cache( $id );
					$post = get_post( $id );
					if ( $post instanceof WP_Post ) {
						$after_fp = array( 'post_modified_gmt' => $post->post_modified_gmt, 'hash' => md5( (string) $post->post_content ) );
					}
				}
				break;

			case 'create':
				$created_id               = absint( $result['post_id'] ?? ( $result['id'] ?? ( $result['term_id'] ?? ( $result['reply_comment_id'] ?? 0 ) ) ) );
				$captured['object']['id'] = $created_id;
				if ( 'create_user' === $tool ) {
					$captured['undoable'] = false;
				} elseif ( 'reply_comment' === $tool ) {
					$captured['object']['type'] = 'comment';
					$captured['label']          = 'Reply #' . $created_id;
				} elseif ( $created_id > 0 && ( $post = get_post( $created_id ) ) instanceof WP_Post ) {
					$captured['object']['type'] = $post->post_type;
					$captured['label']          = $post->post_title ?: ( 'New ' . $post->post_type );
					$after_fp                   = array( 'post_modified_gmt' => $post->post_modified_gmt );
				}
				break;

			case 'media':
				$captured['object']['id'] = absint( $result['attachment_id'] ?? ( $result['id'] ?? 0 ) );
				if ( ! ( defined( 'MEDIA_TRASH' ) && MEDIA_TRASH ) ) {
					$captured['undoable'] = false;
				}
				break;

			case 'option':
				$hashes = array();
				foreach ( ( $captured['object']['keys'] ?? array() ) as $k ) {
					$hashes[ $k ] = md5( (string) wp_json_encode( get_option( $k ) ) );
				}
				$after_fp = array( 'hashes' => $hashes );
				break;

			default:
				$after_fp = array( 'time' => time() );
				break;
		}

		$captured['after_fp'] = $after_fp;
		return $captured;
	}

	/** Revert an individual entry back to its captured "before" state. */
	public static function revert( array $entry ): array {
		if ( empty( $entry['undoable'] ) ) {
			return array( 'success' => false, 'error' => 'not_undoable', 'message' => 'This operation cannot be undone.' );
		}

		$adapter = $entry['adapter'] ?? 'none';
		$object  = $entry['object'] ?? array();
		$before  = $entry['before'] ?? array();

		switch ( $adapter ) {
			case 'post':
			case 'template':
				return self::revert_post_or_template( $adapter, $object, $before );

			case 'create':
				$id = absint( $object['id'] ?? 0 );
				if ( $id > 0 ) {
					if ( 'comment' === ( $object['type'] ?? '' ) ) {
						wp_trash_comment( $id );
						return array( 'success' => true, 'trashed_comment_id' => $id );
					}
					if ( get_post( $id ) ) {
						wp_trash_post( $id );
						return array( 'success' => true, 'trashed_id' => $id );
					}
				}
				return array( 'success' => true );

			case 'trash':
				$id = absint( $object['id'] ?? 0 );
				if ( $id > 0 ) {
					wp_untrash_post( $id );
					if ( ! empty( $before['post_status'] ) && 'trash' !== $before['post_status'] ) {
						wp_update_post( array( 'ID' => $id, 'post_status' => $before['post_status'] ) );
					}
					return array( 'success' => true, 'untrashed_id' => $id );
				}
				return array( 'success' => false, 'error' => 'not_found' );

			case 'option':
				foreach ( $before as $k => $v ) {
					null === $v ? delete_option( $k ) : update_option( $k, $v, 'no' );
				}
				return array( 'success' => true );

			case 'meta':
				$id = absint( $object['id'] ?? 0 );
				foreach ( $before as $k => $v ) {
					null === $v ? delete_post_meta( $id, $k ) : update_post_meta( $id, $k, $v );
				}
				return array( 'success' => true );

			case 'term':
				if ( 'object_terms' === ( $object['type'] ?? '' ) ) {
					$terms = is_array( $before['terms'] ?? null ) ? array_map( 'intval', $before['terms'] ) : array();
					wp_set_object_terms( absint( $object['id'] ?? 0 ), $terms, (string) ( $before['taxonomy'] ?? 'category' ) );
				}
				return array( 'success' => true );

			case 'user_role':
				$user_id = absint( $object['id'] ?? 0 );
				if ( $user_id > 0 && ! empty( $before['roles'] ) && is_array( $before['roles'] ) ) {
					$user = new WP_User( $user_id );
					if ( $user->exists() ) {
						$user->set_role( $before['roles'][0] );
						return array( 'success' => true );
					}
				}
				return array( 'success' => false, 'error' => 'user_not_found' );

			case 'comment':
				$comment_id = absint( $object['id'] ?? 0 );
				if ( $comment_id > 0 && isset( $before['comment_approved'] ) ) {
					wp_set_comment_status( $comment_id, $before['comment_approved'] );
					return array( 'success' => true );
				}
				return array( 'success' => false, 'error' => 'comment_not_found' );

			case 'media':
				$attach_id = absint( $object['id'] ?? 0 );
				if ( $attach_id > 0 && defined( 'MEDIA_TRASH' ) && MEDIA_TRASH ) {
					wp_trash_post( $attach_id );
					return array( 'success' => true );
				}
				return array( 'success' => false, 'error' => 'media_trash_disabled' );

			default:
				return array( 'success' => false, 'error' => 'not_undoable' );
		}
	}

	/** Revert a post or template entry with revision fallback and MD5 hash verification. */
	private static function revert_post_or_template( string $adapter, array $object, array $before ): array {
		$id    = absint( $object['id'] ?? 0 );
		$tp_id = (string) ( $object['tp_id'] ?? ( $before['tp_id'] ?? '' ) );

		if ( $id <= 0 && '' !== $tp_id && class_exists( 'Align_Agent_Undo_Resolver' ) ) {
			$resolved = Align_Agent_Undo_Resolver::resolve_object_id( array( 'id_arg' => 'tp_id' ), array( 'tp_id' => $tp_id ), 'save_template' );
			$id       = absint( $resolved['id'] ?? 0 );
		}
		if ( $id <= 0 ) {
			return array( 'success' => false, 'error' => 'invalid_post_id', 'message' => 'Target post ID is invalid.' );
		}

		if ( ! empty( $before['html_dropped'] ) ) {
			$rev_id = absint( $before['revision_id'] ?? 0 );
			if ( $rev_id <= 0 ) {
				return array( 'success' => false, 'error' => 'revert_failed', 'message' => 'No revision available to restore large post.' );
			}
			$restored = wp_restore_post_revision( $rev_id );
			if ( ! $restored || is_wp_error( $restored ) ) {
				return array( 'success' => false, 'error' => 'revert_failed', 'message' => 'Failed to restore post revision.' );
			}
			clean_post_cache( $id );
			return array( 'success' => true, 'post_id' => $id, 'restored_revision' => $rev_id );
		}

		$title   = (string) ( $before['post_title'] ?? '' );
		$html    = (string) ( $before['html'] ?? ( $before['post_content'] ?? '' ) );
		$css     = (string) ( $before['css'] ?? '' );
		$js      = (string) ( $before['js'] ?? '' );
		$status  = (string) ( $before['post_status'] ?? 'publish' );
		$content = array_key_exists( 'post_content', $before ) ? (string) $before['post_content'] : $html;

		if ( class_exists( 'Align_Builder_Crud_Handler' ) ) {
			$req    = new WP_REST_Request( 'POST', '/align-builder/v1/save-page' );
			$params = array( 'post_id' => $id, 'html' => $html, 'css' => $css, 'js' => $js, 'status' => $status );
			if ( 'template' === $adapter && '' !== $tp_id ) {
				$params['tp_id']         = $tp_id;
				$params['template_type'] = ! empty( $object['template_type'] ) ? (string) $object['template_type'] : ( 0 === strpos( $tp_id, 'tpl_' ) ? 'wp_template' : 'wp_template_part' );
			}
			if ( '' !== $title ) {
				$params['title'] = $title;
			}
			foreach ( $params as $k => $v ) {
				$req->set_param( $k, $v );
			}
			$res = Align_Builder_Crud_Handler::save_page( $req );
			if ( is_wp_error( $res ) ) {
				return array( 'success' => false, 'error' => $res->get_error_code(), 'message' => $res->get_error_message() );
			}
		}

		$update_arr = array( 'ID' => $id, 'post_content' => wp_slash( $content ), 'post_status' => $status );
		if ( '' !== $title ) {
			$update_arr['post_title'] = wp_slash( $title );
		}

		kses_remove_filters();
		$upd = wp_update_post( $update_arr, true );
		kses_init_filters();

		if ( is_wp_error( $upd ) ) {
			return array( 'success' => false, 'error' => $upd->get_error_code(), 'message' => $upd->get_error_message() );
		}

		if ( 'post' === $adapter && isset( $before['thumbnail_id'] ) ) {
			$before['thumbnail_id'] > 0 ? set_post_thumbnail( $id, (int) $before['thumbnail_id'] ) : delete_post_thumbnail( $id );
		}

		clean_post_cache( $id );
		$fresh = get_post( $id );
		if ( ! ( $fresh instanceof WP_Post ) ) {
			return array( 'success' => false, 'error' => 'revert_failed', 'message' => 'Target post missing after revert.' );
		}

		$expected_hash = ! empty( $before['content_hash'] ) ? (string) $before['content_hash'] : md5( $content );
		if ( md5( (string) $fresh->post_content ) !== $expected_hash ) {
			return array( 'success' => false, 'error' => 'revert_mismatch', 'message' => 'Reverted content hash did not match snapshot.' );
		}

		return 'template' === $adapter ? array( 'success' => true, 'id' => $id ) : array( 'success' => true, 'post_id' => $id );
	}
}
