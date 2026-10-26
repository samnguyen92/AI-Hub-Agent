<?php
/**
 * Align Theme - Bulk AI Translator & SEO Assistant Controller
 *
 * Handles asynchronous bulk scanning and item-by-item translation pipeline:
 * 1. Resolves/creates target translation post, template part, or reusable block.
 * 2. Translates blocks, markup, text, and title into target language.
 * 3. Localizes all internal URLs (e.g. /contact/ -> /zh/contact/, CTAs, Nav links).
 * 4. Once translation completes, triggers AI SEO Generator on the translated text (for pages/posts).
 * 5. Syncs metadata with Rank Math SEO.
 *
 * Adheres strictly to Align Settings Style Guide & < 500 lines constraint.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Bulk_Translator {

	/**
	 * Initialize AJAX hooks.
	 */
	public static function init() {
		add_action( 'wp_ajax_align_bulk_scan_items',         array( __CLASS__, 'ajax_scan_items' ) );
		add_action( 'wp_ajax_align_bulk_process_item',      array( __CLASS__, 'ajax_process_item' ) );
		add_action( 'wp_ajax_align_bulk_generate_item_seo', array( __CLASS__, 'ajax_generate_item_seo' ) );
	}

	/**
	 * AJAX: Scan translatable items matching filter criteria.
	 */
	public static function ajax_scan_items() {
		check_ajax_referer( 'align_settings_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized access.' ), 403 );
		}

		$target_lang = isset( $_POST['target_lang'] ) ? sanitize_key( $_POST['target_lang'] ) : '';
		$mode        = isset( $_POST['mode'] ) ? sanitize_text_field( $_POST['mode'] ) : 'missing_only';
		$types_raw   = isset( $_POST['post_types'] ) ? (array) $_POST['post_types'] : array( 'page', 'post' );

		$allowed_types = array( 'page', 'post', 'wp_template_part', 'wp_template', 'wp_block' );
		$post_types    = array_values( array_intersect( $types_raw, $allowed_types ) );

		if ( empty( $post_types ) ) {
			$post_types = array( 'page', 'post' );
		}

		if ( empty( $target_lang ) ) {
			wp_send_json_error( array( 'message' => 'Target language is required.' ), 400 );
		}

		$default_lang = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en';

		error_log( "[Align BulkTranslator] Scanning items | target_lang={$target_lang} | mode={$mode} | types=" . implode( ',', $post_types ) );

		$items = array();

		// ---------------------------------------------------------------------
		// 1. Scan Template Parts (Header/Footer) & Block Templates (page, 404, etc.)
		// ---------------------------------------------------------------------
		$tpl_configs = array(
			'wp_template_part' => array( 'defs' => array( 'header', 'footer' ), 'label' => 'Template Part' ),
			'wp_template'      => array( 'defs' => array( 'page', 'single', '404', 'archive', 'index', 'search' ), 'label' => 'Block Template' ),
		);

		foreach ( $tpl_configs as $t_type => $t_cfg ) {
			if ( ! in_array( $t_type, $post_types, true ) || ! class_exists( 'Align_Template_Language' ) ) {
				continue;
			}

			$candidates = $t_cfg['defs'];
			$db_t = get_posts( array( 'post_type' => $t_type, 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => -1 ) );
			foreach ( $db_t as $dt ) {
				if ( ! preg_match( '/-[a-z]{2}$/i', $dt->post_name ) && ! in_array( $dt->post_name, $candidates, true ) ) {
					$candidates[] = $dt->post_name;
				}
			}

			foreach ( $candidates as $slug ) {
				$loc_slug  = $slug . '-' . $target_lang;
				$has_trans = ( 'wp_template' === $t_type ) ? Align_Template_Language::template_exists( $loc_slug ) : Align_Template_Language::template_part_exists( $loc_slug );
				$t_status  = self::check_translation_content_status( $has_trans ? $loc_slug : '', $target_lang, 0, $t_type );

				if ( ( 'missing_only' === $mode && $has_trans ) || ( 'missing_or_untranslated' === $mode && 'translated' === $t_status['status'] ) ) {
					continue;
				}

				$prefix = ( 'wp_template' === $t_type ) ? 'tpl_' : 'part_';
				$items[] = array(
					'id'                 => $prefix . $slug,
					'slug'               => $slug,
					'title'              => ucfirst( $slug ) . ' (' . $t_cfg['label'] . ')',
					'post_type'          => $t_type,
					'post_status'        => 'publish',
					'has_translation'    => $has_trans,
					'translation_id'     => $has_trans ? $loc_slug : '',
					'translation_status' => $t_status['status'],
					'char_count'         => $t_status['char_count'],
					'trans_label'        => $t_status['label'],
					'edit_url'           => admin_url( 'site-editor.php?postType=' . $t_type ),
					'target_edit_url'    => $has_trans ? admin_url( 'site-editor.php?postType=' . $t_type ) : '',
					'view_url'           => home_url( '/' . $target_lang . '/' ),
					'target_view_url'    => home_url( '/' . $target_lang . '/' ),
				);
			}
		}

		// ---------------------------------------------------------------------
		// 2. Scan Standard Posts, Pages, and Reusable Blocks (wp_block)
		// ---------------------------------------------------------------------
		$standard_types = array_diff( $post_types, array( 'wp_template_part', 'wp_template' ) );

		if ( ! empty( $standard_types ) ) {
			$args = array(
				'post_type'              => $standard_types,
				'post_status'            => array( 'publish', 'draft' ),
				'posts_per_page'         => -1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => false,
				'update_post_term_cache' => false,
			);

			$posts = get_posts( $args );

			foreach ( $posts as $p ) {
				// Skip language root container pages (e.g. page 'zh')
				$is_root = get_post_meta( $p->ID, '_align_lang_root', true );
				if ( ! empty( $is_root ) ) {
					continue;
				}

				// Check post's assigned language
				$item_lang = get_post_meta( $p->ID, '_align_lang', true );
				if ( empty( $item_lang ) ) {
					$item_lang = $default_lang;
				}

				// Only translate from default language to target language
				if ( $item_lang !== $default_lang ) {
					continue;
				}

				// Check if translation already exists for target_lang
				$has_translation = false;
				$translation_id  = 0;

				if ( class_exists( 'Align_Language_Manager' ) ) {
					$translations = Align_Language_Manager::get_translations( $p->ID );
					foreach ( $translations as $tr ) {
						if ( $tr['lang'] === $target_lang && (int) $tr['post_id'] !== (int) $p->ID ) {
							$has_translation = true;
							$translation_id  = (int) $tr['post_id'];
							break;
						}
					}
				}

				$trans_status = self::check_translation_content_status( $translation_id, $target_lang, $p->ID, $p->post_type );

				if ( 'missing_only' === $mode && $has_translation ) {
					continue;
				}
				if ( 'missing_or_untranslated' === $mode && 'translated' === $trans_status['status'] ) {
					continue;
				}

				$type_label = ( 'wp_block' === $p->post_type ) ? ' (Reusable CTA Block)' : '';

				$items[] = array(
					'id' => (int) $p->ID, 'slug' => $p->post_name, 'post_type' => $p->post_type, 'post_status' => $p->post_status,
					'title'              => ( ! empty( $p->post_title ) ? $p->post_title : '(No title #' . $p->ID . ')' ) . $type_label,
					'has_translation'    => $has_translation, 'translation_id' => $translation_id,
					'translation_status' => $trans_status['status'], 'char_count' => $trans_status['char_count'], 'trans_label' => $trans_status['label'],
					'edit_url'           => get_edit_post_link( $p->ID, 'raw' ),
					'target_edit_url'    => $translation_id ? get_edit_post_link( $translation_id, 'raw' ) : '',
					'view_url'           => get_permalink( $p->ID ),
					'target_view_url'    => $translation_id ? get_permalink( $translation_id ) : '',
				);
			}
		}

		error_log( '[Align BulkTranslator] Scan complete. Found ' . count( $items ) . ' candidate items.' );

		wp_send_json_success( array(
			'total'       => count( $items ),
			'target_lang' => $target_lang,
			'items'       => $items,
		) );
	}

	/**
	 * Inspect target item content to determine if it has actually been translated.
	 *
	 * @param int|string $target_id_or_slug Target post ID or localized template slug.
	 * @param string     $target_lang        Target language code (e.g. 'zh').
	 * @param int        $source_id          Source post ID (optional).
	 * @param string     $post_type          Item post type ('page', 'post', 'wp_template', etc.).
	 * @return array { status: string, char_count: int, label: string }
	 */
	public static function check_translation_content_status( $target_id_or_slug, $target_lang, $source_id = 0, $post_type = 'page' ) {
		if ( empty( $target_id_or_slug ) ) {
			return array( 'status' => 'missing', 'char_count' => 0, 'label' => 'Missing' );
		}

		$content = '';
		if ( in_array( $post_type, array( 'wp_template', 'wp_template_part' ), true ) ) {
			$t_posts = get_posts( array(
				'post_type'      => $post_type,
				'name'           => $target_id_or_slug,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 1,
			) );
			if ( ! empty( $t_posts ) && ! empty( $t_posts[0]->post_content ) ) {
				$content = $t_posts[0]->post_content;
			} else {
				$dir_name = ( 'wp_template' === $post_type ) ? 'templates' : 'parts';
				$f_child  = get_stylesheet_directory() . '/' . $dir_name . '/' . $target_id_or_slug . '.html';
				$f_parent = get_template_directory() . '/' . $dir_name . '/' . $target_id_or_slug . '.html';
				$target_f = file_exists( $f_child ) ? $f_child : ( file_exists( $f_parent ) ? $f_parent : '' );
				if ( ! empty( $target_f ) ) {
					$content = file_get_contents( $target_f );
				}
			}
		} else {
			$post = get_post( (int) $target_id_or_slug );
			if ( ! $post ) {
				return array( 'status' => 'missing', 'char_count' => 0, 'label' => 'Missing' );
			}
			$content = $post->post_content;
		}

		if ( empty( trim( $content ) ) ) {
			return array( 'status' => 'untranslated', 'char_count' => 0, 'label' => 'Empty Content' );
		}

		$clean_text = wp_strip_all_tags( html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

		if ( str_starts_with( $target_lang, 'zh' ) ) {
			preg_match_all( '/[\x{4e00}-\x{9fff}]/u', $clean_text, $matches );
			$zh_count = count( $matches[0] ?? array() );

			error_log( "[Align BulkTranslator] Status check target={$target_id_or_slug} | zh_count={$zh_count} | clean_len=" . mb_strlen( $clean_text ) );

			if ( $zh_count >= 20 ) {
				return array(
					'status'     => 'translated',
					'char_count' => $zh_count,
					'label'      => 'Translated (' . $zh_count . ' chars)',
				);
			}

			return array(
				'status'     => 'untranslated',
				'char_count' => $zh_count,
				'label'      => 'Untranslated Content (' . $zh_count . ' CJK)',
			);
		}

		if ( $source_id > 0 ) {
			$src_post = get_post( (int) $source_id );
			if ( $src_post ) {
				$src_text = wp_strip_all_tags( html_entity_decode( $src_post->post_content, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				if ( ! empty( $src_text ) && $src_text === $clean_text ) {
					return array( 'status' => 'untranslated', 'char_count' => 0, 'label' => 'Untranslated Content (EN)' );
				}
			}
		}

		return array(
			'status'     => 'translated',
			'char_count' => mb_strlen( $clean_text ),
			'label'      => 'Translated (' . mb_strlen( $clean_text ) . ' chars)',
		);
	}

	/**
	 * AJAX: Process single item pipeline (Translate Content -> Localize Links -> Generate AI SEO).
	 */
	public static function ajax_process_item() {
		if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 300 ); }
		check_ajax_referer( 'align_settings_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized access.' ), 403 );
		}

		$source_id_raw = isset( $_POST['source_id'] ) ? sanitize_text_field( $_POST['source_id'] ) : '';
		$target_lang   = isset( $_POST['target_lang'] ) ? sanitize_key( $_POST['target_lang'] ) : '';
		$generate_seo  = ! empty( $_POST['generate_seo'] );
		$defer_seo     = ! empty( $_POST['defer_seo'] );
		$overwrite     = ! empty( $_POST['overwrite'] );
		$custom_inst   = isset( $_POST['custom_instructions'] ) ? sanitize_textarea_field( $_POST['custom_instructions'] ) : '';

		if ( empty( $source_id_raw ) || empty( $target_lang ) ) {
			wp_send_json_error( array( 'message' => 'Missing source_id or target_lang.' ), 400 );
		}

		error_log( "[Align BulkTranslator] Starting pipeline for item '{$source_id_raw}' to '{$target_lang}'" );

		// ---------------------------------------------------------------------
		// Case A: Template Part or Block Template
		// ---------------------------------------------------------------------
		if ( str_starts_with( $source_id_raw, 'part_' ) || str_starts_with( $source_id_raw, 'tpl_' ) ) {
			$is_tpl    = str_starts_with( $source_id_raw, 'tpl_' );
			$base_slug = sanitize_key( substr( $source_id_raw, $is_tpl ? 4 : 5 ) );
			$t_type    = $is_tpl ? 'wp_template' : 'wp_template_part';

			if ( ! class_exists( 'Align_Template_Language' ) ) {
				wp_send_json_error( array( 'message' => 'Align_Template_Language is not loaded.' ), 500 );
			}

			$t_res = $is_tpl
				? Align_Template_Language::ensure_translated_template( $base_slug, $target_lang )
				: Align_Template_Language::ensure_translated_template_part( $base_slug, $target_lang );

			if ( is_wp_error( $t_res ) ) {
				wp_send_json_error( array( 'message' => $t_res->get_error_message() ), 500 );
			}

			$trans_post_id  = is_numeric( $t_res ) ? (int) $t_res : 0;
			$localized_slug = $base_slug . '-' . $target_lang;

			wp_send_json_success( array(
				'source_id'        => $source_id_raw,
				'target_id'        => $trans_post_id ?: $localized_slug,
				'target_lang'      => $target_lang,
				'translated_title' => ucfirst( $localized_slug ),
				'post_type'        => $t_type,
				'text_count'       => 0,
				'seo_pending'      => false,
				'seo_generated'    => false,
				'seo_keyword'      => '',
				'seo_title'        => '',
				'seo_score'        => 'N/A',
				'edit_url'         => admin_url( 'site-editor.php?postType=' . $t_type ),
				'view_url'         => home_url( '/' . $target_lang . '/' ),
			) );
		}

		// ---------------------------------------------------------------------
		// Case B: Standard Post, Page, or Reusable Block (wp_block)
		// ---------------------------------------------------------------------
		$source_id = intval( $source_id_raw );
		$source    = get_post( $source_id );
		if ( ! $source ) {
			wp_send_json_error( array( 'message' => "Source post #{$source_id} not found." ), 404 );
		}

		// Step 1: Resolve or Create Target Translation Post
		$target_id = 0;
		if ( class_exists( 'Align_Language_Manager' ) ) {
			$translations = Align_Language_Manager::get_translations( $source_id );
			foreach ( $translations as $tr ) {
				if ( $tr['lang'] === $target_lang && (int) $tr['post_id'] !== $source_id ) {
					$target_id = (int) $tr['post_id'];
					break;
				}
			}
		}

		if ( ! $target_id ) {
			if ( ! class_exists( 'Align_Language_Manager' ) ) {
				wp_send_json_error( array( 'message' => 'Align_Language_Manager is not loaded.' ), 500 );
			}
			$create_res = Align_Language_Manager::create_translation( $source_id, $target_lang );
			if ( is_wp_error( $create_res ) ) {
				wp_send_json_error( array( 'message' => 'Could not create translation: ' . $create_res->get_error_message() ), 500 );
			}
			$target_id = (int) $create_res;
		}

		// Step 2: Translate Content & Title (Also localizes internal links to target lang)
		if ( ! class_exists( 'Align_Builder_Translator' ) ) {
			wp_send_json_error( array( 'message' => 'Align_Builder_Translator is not loaded.' ), 500 );
		}

		$trans_req = new WP_REST_Request( 'POST', '/align/v1/translate-post' );
		$trans_req->set_param( 'post_id', $target_id );
		$trans_req->set_param( 'target_lang', $target_lang );
		if ( ! empty( $custom_inst ) ) {
			$trans_req->set_param( 'custom_instructions', $custom_inst );
		}

		$trans_res = Align_Builder_Translator::rest_translate_post( $trans_req );
		if ( is_wp_error( $trans_res ) ) {
			wp_send_json_error( array(
				'message'   => 'Content translation failed: ' . $trans_res->get_error_message(),
				'target_id' => $target_id,
			), 500 );
		}

		$trans_data = is_a( $trans_res, 'WP_REST_Response' ) ? $trans_res->get_data() : (array) $trans_res;
		clean_post_cache( $target_id );

		$can_generate_seo = ( $generate_seo && in_array( $source->post_type, array( 'page', 'post' ), true ) );
		$seo_data         = array( 'saved' => false, 'keyword' => '', 'title' => '', 'score' => '' );

		if ( $can_generate_seo && ! $defer_seo ) {
			$seo_data = self::generate_and_save_item_seo( $target_id, $target_lang );
		}

		wp_send_json_success( array(
			'source_id'        => $source_id,
			'target_id'        => $target_id,
			'target_lang'      => $target_lang,
			'translated_title' => get_the_title( $target_id ),
			'post_type'        => $source->post_type,
			'text_count'       => $trans_data['text_count'] ?? 0,
			'seo_pending'      => ( $can_generate_seo && $defer_seo ),
			'seo_generated'    => $seo_data['saved'],
			'seo_keyword'      => $seo_data['keyword'],
			'seo_title'        => $seo_data['title'],
			'seo_score'        => $seo_data['score'],
			'edit_url'         => get_edit_post_link( $target_id, 'raw' ),
			'view_url'         => get_permalink( $target_id ),
		) );
	}

	/**
	 * AJAX: Generate Rank Math SEO for translated target post in a separate light request.
	 */
	public static function ajax_generate_item_seo() {
		if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 300 ); }
		check_ajax_referer( 'align_settings_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized access.' ), 403 );
		}

		$target_id   = isset( $_POST['target_id'] ) ? (int) $_POST['target_id'] : 0;
		$target_lang = isset( $_POST['target_lang'] ) ? sanitize_key( $_POST['target_lang'] ) : 'zh';

		if ( ! $target_id ) {
			wp_send_json_error( array( 'message' => 'Missing target_id.' ), 400 );
		}

		$seo_data = self::generate_and_save_item_seo( $target_id, $target_lang );
		wp_send_json_success( $seo_data );
	}

	/**
	 * Helper: Generate and persist AI SEO metadata for a target post.
	 *
	 * @param int    $target_id   Target post ID.
	 * @param string $target_lang Target language code.
	 * @return array SEO execution result.
	 */
	public static function generate_and_save_item_seo( $target_id, $target_lang ) {
		$seo_saved = false;
		$keyword   = '';
		$title     = '';
		$score     = get_post_meta( $target_id, 'rank_math_seo_score', true ) ?: '';

		if ( class_exists( 'Align_Builder_Seo_Handler' ) ) {
			$seo_req = new WP_REST_Request( 'POST', '/align/v1/seo-generate' );
			$seo_req->set_param( 'post_id', $target_id );
			$seo_req->set_param( 'lang', $target_lang );
			$seo_gen_res = Align_Builder_Seo_Handler::generate_seo_meta( $seo_req );

			if ( ! is_wp_error( $seo_gen_res ) ) {
				$gen_data = is_a( $seo_gen_res, 'WP_REST_Response' ) ? $seo_gen_res->get_data() : (array) $seo_gen_res;
				if ( ! empty( $gen_data['generated'] ) ) {
					$gen     = $gen_data['generated'];
					$keyword = $gen['focus_keyword'] ?? '';
					$title   = $gen['title'] ?? '';

					$save_req = new WP_REST_Request( 'POST', '/align/v1/seo-meta' );
					$save_req->set_param( 'post_id', $target_id );
					foreach ( array( 'post_title', 'focus_keyword', 'title', 'description', 'slug', 'excerpt', 'og_title', 'og_description' ) as $f ) {
						if ( ! empty( $gen[ $f ] ) ) $save_req->set_param( $f, $gen[ $f ] );
					}
					if ( ! is_wp_error( Align_Builder_Seo_Handler::save_seo_meta( $save_req ) ) ) {
						$seo_saved = true;
						$score     = get_post_meta( $target_id, 'rank_math_seo_score', true ) ?: '';
					}
				}
			}
		}

		clean_post_cache( $target_id );
		return array(
			'saved'   => $seo_saved,
			'keyword' => $keyword,
			'title'   => $title,
			'score'   => $score,
		);
	}
}
