<?php
/**
 * Align Agent Multi-language Tools
 *
 * Provides inspection, matrix overview, creation, and configuration
 * for multi-language translations and supported languages.
 *
 * @package Align_Agent
 * @subpackage Includes\Tools\I18n
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_I18n {

	/**
	 * Register i18n tools into Align_Agent_Tool_Registry.
	 *
	 * @return void
	 */
	public static function init(): void {
		Align_Agent_Tool_Registry::register( 'list_languages', array(
			'type'             => 'read',
			'group'            => 'i18n',
			'scope'            => 'local',
			'capability'       => 'edit_posts',
			'requires_builder' => false,
			'danger'           => false,
			'description'      => 'List all configured site languages and default language.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(),
			),
			'callback'         => array( __CLASS__, 'tool_list_languages' ),
		) );

		Align_Agent_Tool_Registry::register( 'get_translations', array(
			'type'             => 'read',
			'group'            => 'i18n',
			'scope'            => 'local',
			'capability'       => 'edit_posts',
			'requires_builder' => false,
			'danger'           => false,
			'description'      => 'Retrieve all existing translated posts/pages linked to a given post ID, along with missing language codes.',
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
			'callback'         => array( __CLASS__, 'tool_get_translations' ),
		) );

		Align_Agent_Tool_Registry::register( 'translation_overview', array(
			'type'             => 'read',
			'group'            => 'i18n',
			'scope'            => 'local',
			'capability'       => 'edit_posts',
			'requires_builder' => false,
			'danger'           => false,
			'description'      => 'Get sitewide translation coverage overview and matrix grouped by post cluster, including completion statistics.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'post_type' => array(
						'type'        => 'string',
						'enum'        => array( 'page', 'post', 'all' ),
						'description' => 'Post type filter. Default: all.',
					),
					'limit'     => array(
						'type'        => 'integer',
						'description' => 'Maximum number of items to scan (1..200). Default: 100.',
					),
				),
			),
			'callback'         => array( __CLASS__, 'tool_translation_overview' ),
		) );

		Align_Agent_Tool_Registry::register( 'create_translation', array(
			'type'             => 'write',
			'group'            => 'i18n',
			'scope'            => 'local',
			'capability'       => 'edit_posts',
			'requires_builder' => false,
			'danger'           => false,
			'undo'             => array( 'adapter' => 'create' ),
			'description'      => 'Create a linked translation of an existing page/post in target_lang and save its translated title and HTML content. Recommended workflow: first call get_page(source_id) to read the source HTML and title, then call create_translation with source_id, target_lang, translated title (without [LANG] suffix), and translated html (preserving all HTML tags, classes, attributes, and SEO structure while translating visible human-readable text into target_lang). URL slug stays SEO-friendly ASCII based on the original slug.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'source_id'   => array(
						'type'        => 'integer',
						'description' => 'Source post/page ID to translate from.',
					),
					'target_lang' => array(
						'type'        => 'string',
						'description' => 'Target language code (must be configured in site languages).',
					),
					'title'       => array(
						'type'        => 'string',
						'description' => 'Translated page/post title in target_lang for human readers (MUST NOT include [JA]/[VI] suffix). Strongly recommended to provide immediately.',
					),
					'html'        => array(
						'type'        => 'string',
						'description' => 'Full HTML content with all visible text translated into target_lang while strictly preserving original HTML structure, tags, CSS classes, IDs, and SEO hierarchy (h1, h2, etc.).',
					),
					'status'      => array(
						'type'        => 'string',
						'description' => 'Optional post status: draft (default) or publish.',
					),
				),
				'required'   => array( 'source_id', 'target_lang' ),
			),
			'callback'         => array( __CLASS__, 'tool_create_translation' ),
			'summary_callback' => array( __CLASS__, 'summarize_create_translation' ),
		) );

		Align_Agent_Tool_Registry::register( 'manage_language', array(
			'type'             => 'write',
			'group'            => 'i18n',
			'scope'            => 'global',
			'capability'       => 'manage_options',
			'requires_builder' => false,
			'danger'           => true,
			'undo'             => array( 'adapter' => 'option', 'keys' => array( 'align_languages' ) ),
			'description'      => 'Add, update, or remove a site language configuration in Align theme. Requires manage_options capability and user approval.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'action'      => array(
						'type' => 'string',
						'enum' => array( 'add', 'update', 'remove' ),
					),
					'code'        => array(
						'type'        => 'string',
						'description' => 'Language code (e.g. "en", "vi", "zh", "ja").',
					),
					'name'        => array(
						'type'        => 'string',
						'description' => 'Human-readable language name (required for add/update).',
					),
					'flag'        => array(
						'type'        => 'string',
						'description' => 'Emoji flag or icon identifier.',
					),
					'slug'        => array(
						'type'        => 'string',
						'description' => 'URL slug prefix for language (e.g. "zh").',
					),
					'script'      => array(
						'type'        => 'string',
						'description' => 'Writing script/direction or locale code.',
					),
					'prompt_hint' => array(
						'type'        => 'string',
						'description' => 'Translation prompt hint for AI assistants.',
					),
				),
				'required'   => array( 'action', 'code' ),
			),
			'callback'         => array( __CLASS__, 'tool_manage_language' ),
			'summary_callback' => array( __CLASS__, 'summarize_manage_language' ),
		) );
	}

	public static function tool_list_languages( array $args = array() ): array {
		if ( ! class_exists( 'Align_Language_Manager' ) ) {
			return array( 'success' => false, 'error' => 'theme_feature_unavailable', 'message' => 'Align_Language_Manager unavailable.' );
		}

		$raw_langs    = Align_Language_Manager::get_languages();
		$default_lang = Align_Language_Manager::get_default_lang();
		$langs_output = array();

		foreach ( $raw_langs as $key => $data ) {
			$code            = (string) ( $data['code'] ?? $key );
			$hint            = (string) ( $data['prompt_hint'] ?? '' );
			$langs_output[ $code ] = array(
				'code'            => $code,
				'name'            => (string) ( $data['name'] ?? '' ),
				'flag'            => (string) ( $data['flag'] ?? '' ),
				'slug'            => (string) ( $data['slug'] ?? $code ),
				'script'          => (string) ( $data['script'] ?? 'Latn' ),
				'is_default'      => ( $code === $default_lang ),
				'root_page_id'    => (int) ( $data['root_page_id'] ?? 0 ),
				'has_prompt_hint' => '' !== trim( $hint ),
				'prompt_hint'     => ( strlen( $hint ) > 300 ) ? substr( $hint, 0, 300 ) . '...' : $hint,
			);
		}

		return array(
			'success'      => true,
			'languages'    => $langs_output,
			'default_lang' => $default_lang,
			'message'      => sprintf( 'Configured %d language(s). Default: %s.', count( $langs_output ), $default_lang ),
		);
	}

	public static function tool_get_translations( array $args ): array {
		if ( ! class_exists( 'Align_Language_Manager' ) ) {
			return array( 'success' => false, 'error' => 'theme_feature_unavailable', 'message' => 'Align_Language_Manager unavailable.' );
		}

		$post_id = absint( $args['post_id'] ?? 0 );
		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return array( 'success' => false, 'error' => 'invalid_post_id', 'message' => 'Post does not exist.' );
		}

		$current_lang = Align_Language_Manager::get_post_lang( $post_id ) ?: Align_Language_Manager::get_default_lang();
		$translations = Align_Language_Manager::get_translations( $post_id );
		$raw_langs    = Align_Language_Manager::get_languages();
		$all_langs    = array();
		foreach ( $raw_langs as $k => $v ) {
			$all_langs[] = is_array( $v ) && isset( $v['code'] ) ? (string) $v['code'] : (string) $k;
		}

		$translated_langs = array();
		foreach ( $translations as $tr_key => $tr_val ) {
			if ( is_array( $tr_val ) && isset( $tr_val['lang'] ) ) {
				$translated_langs[] = (string) $tr_val['lang'];
			} elseif ( is_string( $tr_key ) ) {
				$translated_langs[] = $tr_key;
			}
		}

		$missing = array();
		foreach ( $all_langs as $code ) {
			if ( $code !== $current_lang && ! in_array( $code, $translated_langs, true ) ) {
				$missing[] = $code;
			}
		}

		return array(
			'success'      => true,
			'post_id'      => $post_id,
			'lang'         => $current_lang,
			'translations' => $translations,
			'missing'      => $missing,
			'message'      => sprintf( 'Post %d has %d translations. Missing: %s.', $post_id, count( $translations ), ! empty( $missing ) ? implode( ', ', $missing ) : 'none' ),
		);
	}

	public static function tool_translation_overview( array $args = array() ): array {
		if ( ! class_exists( 'Align_Language_Manager' ) ) {
			return array( 'success' => false, 'error' => 'theme_feature_unavailable', 'message' => 'Align_Language_Manager unavailable.' );
		}

		$post_type = $args['post_type'] ?? 'all';
		$limit     = isset( $args['limit'] ) ? min( 200, max( 1, (int) $args['limit'] ) ) : 100;
		$types     = ( 'all' === $post_type ) ? array( 'page', 'post' ) : array( $post_type );

		$posts     = get_posts( array( 'post_type' => $types, 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => $limit, 'orderby' => 'ID', 'order' => 'ASC' ) );
		$raw_langs = Align_Language_Manager::get_languages();
		$all_langs = array();
		foreach ( $raw_langs as $k => $v ) {
			$all_langs[] = is_array( $v ) && isset( $v['code'] ) ? (string) $v['code'] : (string) $k;
		}
		$groups    = array();

		foreach ( $posts as $p ) {
			$gk = get_post_meta( $p->ID, '_align_lang_group', true ) ?: ( get_post_meta( $p->ID, '_align_lang_parent', true ) ?: (string) $p->ID );
			if ( ! isset( $groups[ $gk ] ) ) {
				$groups[ $gk ] = array( 'title' => $p->post_title, 'items' => array(), 'missing' => array() );
			}
			$groups[ $gk ]['items'][ Align_Language_Manager::get_post_lang( $p->ID ) ?: Align_Language_Manager::get_default_lang() ] = (int) $p->ID;
		}

		$fully_translated = 0;
		$total_missing    = 0;
		foreach ( $groups as &$grp ) {
			$grp_missing = array();
			foreach ( $all_langs as $l ) {
				if ( ! isset( $grp['items'][ $l ] ) ) {
					$grp_missing[] = $l;
				}
			}
			$grp['missing'] = $grp_missing;
			empty( $grp_missing ) ? $fully_translated++ : $total_missing += count( $grp_missing );
		}
		unset( $grp );

		return array(
			'success' => true,
			'groups'  => $groups,
			'stats'   => array( 'total_groups' => count( $groups ), 'fully_translated' => $fully_translated, 'missing_count' => $total_missing ),
			'message' => sprintf( 'Scanned %d groups. %d complete, %d missing.', count( $groups ), $fully_translated, $total_missing ),
		);
	}

	public static function tool_create_translation( array $args ): array {
		if ( ! class_exists( 'Align_Language_Manager' ) ) {
			return array( 'success' => false, 'error' => 'theme_feature_unavailable', 'message' => 'Align_Language_Manager unavailable.' );
		}

		$source_id       = absint( $args['source_id'] ?? 0 );
		$target_lang     = sanitize_key( (string) ( $args['target_lang'] ?? '' ) );
		$raw_title       = isset( $args['title'] ) ? sanitize_text_field( (string) $args['title'] ) : '';
		$custom_title    = trim( (string) preg_replace( '/\s*\[[a-zA-Z_-]+\]\s*$/', '', $raw_title ) );
		$translated_html = isset( $args['html'] ) ? trim( (string) $args['html'] ) : '';
		$req_status      = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '';

		$source_post = $source_id > 0 ? get_post( $source_id ) : null;
		if ( ! $source_post ) {
			return array( 'success' => false, 'error' => 'invalid_source_id', 'message' => 'Source post does not exist.' );
		}
		if ( ! current_user_can( 'edit_post', $source_id ) ) {
			return array( 'success' => false, 'error' => 'forbidden', 'message' => 'Permission denied.' );
		}

		$warnings = array();
		$source_parts = array( 'html' => (string) $source_post->post_content, 'css' => '', 'js' => '' );
		if ( class_exists( 'Align_Gateway_Pages' ) ) {
			$source_parts = Align_Gateway_Pages::extract_decoupled_parts( $source_post );
		}
		if ( class_exists( 'Align_Agent_Tools_Media' ) ) {
			if ( '' !== $translated_html ) {
				$translated_html = Align_Agent_Tools_Media::sanitize_internal_urls_to_dynamic( $translated_html );
			}
			$source_parts['html'] = Align_Agent_Tools_Media::sanitize_internal_urls_to_dynamic( (string) ( $source_parts['html'] ?? '' ) );
			$source_parts['css']  = Align_Agent_Tools_Media::sanitize_internal_urls_to_dynamic( (string) ( $source_parts['css'] ?? '' ) );
		}

		if ( '' !== $translated_html && class_exists( 'Align_Agent_Tools_Pages' ) ) {
			$guard_err = Align_Agent_Tools_Pages::guard_html_and_css( $translated_html, (string) ( $source_parts['css'] ?? '' ), (string) ( $source_parts['js'] ?? '' ) );
			if ( null !== $guard_err ) {
				return $guard_err;
			}
		}

		$new_id = Align_Language_Manager::create_translation( $source_id, $target_lang );
		if ( is_wp_error( $new_id ) ) {
			$err_code = $new_id->get_error_code();
			if ( 'already_translated' === $err_code ) {
				$error_data  = $new_id->get_error_data();
				$existing_id = is_array( $error_data ) && isset( $error_data['existing_id'] ) ? (int) $error_data['existing_id'] : 0;
				return array( 'success' => false, 'error' => 'already_translated', 'message' => sprintf( 'Already translated to "%s".', $target_lang ), 'existing_id' => $existing_id );
			}
			return array( 'success' => false, 'error' => $err_code, 'message' => $new_id->get_error_message() );
		}

		$base_slug     = '' !== $source_post->post_name ? $source_post->post_name : sanitize_title( $source_post->post_title );
		$seo_slug      = $base_slug . '-' . $target_lang;
		$is_front_page = ( $source_id === (int) get_option( 'page_on_front' ) );
		$target_status = in_array( $req_status, array( 'draft', 'publish' ), true )
			? $req_status
			: ( $is_front_page ? get_post_status( $new_id ) : 'draft' );

		$title_is_translated = ( '' !== $custom_title && strtolower( $custom_title ) !== strtolower( trim( $source_post->post_title ) ) );
		$html_is_translated  = ( '' !== $translated_html && trim( $translated_html ) !== trim( (string) ( $source_parts['html'] ?? '' ) ) );

		$update_post_args = array(
			'ID'          => $new_id,
			'post_status' => $target_status,
			'post_name'   => $seo_slug,
		);
		if ( '' !== $custom_title ) {
			$update_post_args['post_title'] = $custom_title;
		}
		wp_update_post( $update_post_args );

		$html_to_save = '' !== $translated_html ? $translated_html : (string) ( $source_parts['html'] ?? '' );

		if ( Align_Agent_Tool_Registry::is_builder_available() && class_exists( 'Align_Builder_Crud_Handler' ) && 'page' === get_post_type( $source_id ) ) {
			$save_req = new WP_REST_Request( 'POST', '/align-builder/v1/page/save' );
			$save_req->set_param( 'post_id', $new_id );
			$save_req->set_param( 'html', $html_to_save );
			$save_req->set_param( 'css', (string) ( $source_parts['css'] ?? '' ) );
			$save_req->set_param( 'js', (string) ( $source_parts['js'] ?? '' ) );
			$save_req->set_param( 'status', $target_status );
			$save_res = Align_Builder_Crud_Handler::save_page( $save_req );
			if ( is_wp_error( $save_res ) ) {
				$warnings[] = 'assets_not_synced: Failed to synchronize builder asset files.';
			}
		} elseif ( '' !== $translated_html ) {
			kses_remove_filters();
			wp_update_post( array(
				'ID'           => $new_id,
				'post_content' => wp_slash( $translated_html ),
				'post_name'    => $seo_slug,
			) );
			kses_init_filters();
		}

		if ( '' !== $translated_html && class_exists( 'Align_Agent_Tools_I18n_Urls' ) ) {
			Align_Agent_Tools_I18n_Urls::tool_localize_post_urls( array( 'post_id' => $new_id ) );
		}

		// Ensure SEO slug stays intact after any builder or URL localization saves.
		$fresh_check = get_post( $new_id );
		if ( $fresh_check && $fresh_check->post_name !== $seo_slug ) {
			wp_update_post( array( 'ID' => $new_id, 'post_name' => $seo_slug ) );
			$fresh_check = get_post( $new_id );
		}

		Align_Agent_Audit_Log::log_action( get_current_user_id(), 'create_translation', $new_id, 'create', array( 'source_id' => $source_id, 'target_lang' => $target_lang, 'post_status' => $target_status ) );

		$lang_config           = Align_Language_Manager::get_language( $target_lang );
		$final_title           = $fresh_check ? (string) $fresh_check->post_title : $custom_title;
		$final_slug            = $fresh_check ? (string) $fresh_check->post_name : $seo_slug;
		$translation_completed = ( $title_is_translated && $html_is_translated );

		$raw_preview_url = (string) get_preview_post_link( $new_id );
		$preview_url     = class_exists( 'Align_Agent_Tools_Media' ) ? Align_Agent_Tools_Media::to_root_relative_url( $raw_preview_url ) : $raw_preview_url;
		$raw_edit_url    = (string) get_edit_post_link( $new_id, 'raw' );
		$edit_url        = class_exists( 'Align_Agent_Tools_Media' ) ? Align_Agent_Tools_Media::to_root_relative_url( $raw_edit_url ) : $raw_edit_url;

		if ( $translation_completed ) {
			return array(
				'success'               => true,
				'post_id'               => $new_id,
				'title'                 => $final_title,
				'slug'                  => $final_slug,
				'lang'                  => $target_lang,
				'status'                => $target_status,
				'translation_completed' => true,
				'preview_url'           => $preview_url,
				'edit_url'              => $edit_url,
				'prompt_hint'           => $lang_config['prompt_hint'] ?? '',
				'warnings'              => $warnings,
				'next_step'             => 'Translation and URL localization completed.',
				'message'               => sprintf( 'Created and translated post (ID %d, title: "%s", slug: "%s") in "%s".', $new_id, $final_title, $final_slug, $target_lang ),
				'ui_card'               => array( 'title' => 'Translation Created', 'message' => sprintf( 'Post ID: %d (%s, %s) - %s', $new_id, $target_lang, $target_status, $final_title ), 'url' => $preview_url ),
			);
		}

		return array(
			'success'               => true,
			'post_id'               => $new_id,
			'title'                 => $final_title,
			'slug'                  => $final_slug,
			'lang'                  => $target_lang,
			'status'                => $target_status,
			'translation_completed' => false,
			'warning'               => 'CONTENT NOT TRANSLATED YET! The draft currently contains untranslated source text and a temporary [' . strtoupper( $target_lang ) . '] title.',
			'source_title'          => (string) $source_post->post_title,
			'source_html'           => (string) ( $source_parts['html'] ?? '' ),
			'preview_url'           => $preview_url,
			'edit_url'              => $edit_url,
			'prompt_hint'           => $lang_config['prompt_hint'] ?? '',
			'warnings'              => $warnings,
			'next_step'             => sprintf(
				'CRITICAL MANDATORY NEXT STEP: Do NOT stop or reply to the user yet! Immediately call save_page with post_id=%d, translated title in "%s" (remove [%s]), and translated html in "%s" (keep all HTML tags/classes/SEO structure, translate only visible text), then call localize_post_urls(post_id=%d).',
				$new_id,
				$target_lang,
				strtoupper( $target_lang ),
				$target_lang,
				$new_id
			),
			'message'               => sprintf( 'Created translation draft (ID %d) in "%s", awaiting translated title and HTML via save_page.', $new_id, $target_lang ),
			'ui_card'               => array( 'title' => 'Translation Draft Created', 'message' => sprintf( 'Post ID: %d (%s, %s).', $new_id, $target_lang, $target_status ), 'url' => $preview_url ),
		);
	}

	public static function tool_manage_language( array $args ): array {
		if ( ! class_exists( 'Align_Language_Manager' ) ) {
			return array( 'success' => false, 'error' => 'theme_feature_unavailable', 'message' => 'Align_Language_Manager unavailable.' );
		}

		$action = $args['action'] ?? '';
		$code   = sanitize_key( (string) ( $args['code'] ?? '' ) );
		if ( empty( $code ) ) {
			return array( 'success' => false, 'error' => 'missing_code', 'message' => 'Language code is required.' );
		}

		$before = Align_Agent_Audit_Log::snapshot_options( array( 'align_languages' ) );

		if ( 'add' === $action || 'update' === $action ) {
			$name = isset( $args['name'] ) ? sanitize_text_field( (string) $args['name'] ) : '';
			if ( empty( $name ) ) {
				return array( 'success' => false, 'error' => 'missing_name', 'message' => 'Language name is required.' );
			}
			$flag   = isset( $args['flag'] ) ? sanitize_text_field( (string) $args['flag'] ) : '';
			$slug   = isset( $args['slug'] ) ? sanitize_title( (string) $args['slug'] ) : $code;
			$script = isset( $args['script'] ) ? sanitize_text_field( (string) $args['script'] ) : 'Latn';
			$hint   = isset( $args['prompt_hint'] ) ? sanitize_textarea_field( (string) $args['prompt_hint'] ) : '';

			$res = ( 'add' === $action )
				? Align_Language_Manager::add_language( $code, $name, $flag, $slug, $script, $hint )
				: Align_Language_Manager::update_language( $code, $name, $flag, $slug, $script, $hint );

			if ( is_wp_error( $res ) ) {
				return array( 'success' => false, 'error' => $res->get_error_code(), 'message' => $res->get_error_message() );
			}
		} elseif ( 'remove' === $action ) {
			$res = Align_Language_Manager::remove_language( $code );
			if ( is_wp_error( $res ) ) {
				return array( 'success' => false, 'error' => $res->get_error_code(), 'message' => $res->get_error_message() );
			}
		} else {
			return array( 'success' => false, 'error' => 'invalid_action', 'message' => 'Action must be add, update, or remove.' );
		}

		$after = Align_Agent_Audit_Log::snapshot_options( array( 'align_languages' ) );
		Align_Agent_Audit_Log::log_action( get_current_user_id(), 'manage_language', $code, $action, array( 'before' => $before, 'after' => $after, 'changed' => array( 'align_languages' ), 'masked' => array(), 'undo_supported' => true ) );

		return array(
			'success' => true,
			'action'  => $action,
			'code'    => $code,
			'message' => sprintf( 'Language "%s" successfully %sd.', $code, $action ),
			'ui_card' => array( 'title' => 'Language Configuration Updated', 'message' => sprintf( 'Action: %s | Code: %s.', $action, $code ) ),
		);
	}

	public static function summarize_create_translation( array $args ): array {
		$source_id   = absint( $args['source_id'] ?? 0 );
		$target_lang = (string) ( $args['target_lang'] ?? '' );
		$source_post = get_post( $source_id );
		return array(
			'title'       => sprintf( 'Create %s Translation for "%s"', strtoupper( $target_lang ), $source_post ? $source_post->post_title : 'Unknown' ),
			'description' => sprintf( 'Creates a new draft post linked to post %d. Clones template and builder assets.', $source_id ),
			'meta'        => array( 'source_id' => $source_id, 'target_lang' => $target_lang ),
		);
	}

	public static function summarize_manage_language( array $args ): array {
		$action = $args['action'] ?? 'modify';
		$code   = (string) ( $args['code'] ?? '' );
		$name   = (string) ( $args['name'] ?? $code );
		$slug   = (string) ( $args['slug'] ?? $code );
		$desc   = sprintf( 'Action: %s language %s (%s).', strtoupper( $action ), $name, $code );
		if ( 'add' === $action ) {
			$desc .= sprintf( "\nVirtual URL prefix \"/%s/\" will be routed automatically.", $slug );
		} elseif ( 'remove' === $action ) {
			$desc .= "\n⚠️ Existing translated pages stay online but lose their language label.";
		}
		return array(
			'title'       => sprintf( '%s Language "%s"', ucfirst( $action ), $code ),
			'description' => $desc,
			'meta'        => array( 'action' => $action, 'code' => $code ),
		);
	}
}
