<?php
/**
 * Align Theme - Custom Multi-Language Manager
 *
 * Manages language configurations, translation groups, and language-aware page queries.
 * Each translation is a separate WordPress page linked via _align_lang_group meta.
 * URL prefixes use WordPress native Page Hierarchy (zero custom rewrite rules).
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Language_Manager {

	const OPTION_KEY  = 'align_languages';
	const META_LANG   = '_align_lang';
	const META_GROUP  = '_align_lang_group';
	const META_PARENT = '_align_lang_parent';

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'wp_ajax_align_language_add',    array( __CLASS__, 'ajax_add_language' ) );
		add_action( 'wp_ajax_align_language_update', array( __CLASS__, 'ajax_update_language' ) );
		add_action( 'wp_ajax_align_language_remove', array( __CLASS__, 'ajax_remove_language' ) );
	}

	// -------------------------------------------------------------------------
	// Language Config CRUD
	// -------------------------------------------------------------------------

	/**
	 * Get all configured languages.
	 */
	public static function get_languages() {
		$langs = get_option( self::OPTION_KEY, array() );
		if ( empty( $langs ) || ! is_array( $langs ) ) {
			$langs = array(
				array( 'code' => 'en', 'name' => 'English', 'flag' => '🇺🇸', 'slug' => '', 'script' => 'default', 'is_default' => true, 'root_page_id' => 0 ),
			);
			update_option( self::OPTION_KEY, $langs );
		}
		return $langs;
	}

	/**
	 * Get default language code.
	 */
	public static function get_default_lang() {
		foreach ( self::get_languages() as $lang ) {
			if ( ! empty( $lang['is_default'] ) ) return $lang['code'];
		}
		return 'en';
	}

	/**
	 * Get a single language config by code.
	 */
	public static function get_language( $code ) {
		foreach ( self::get_languages() as $lang ) {
			if ( $lang['code'] === $code ) return $lang;
		}
		return null;
	}

	/**
	 * Add a new language.
	 */
	public static function add_language( $code, $name, $flag, $slug = '', $script = 'default', $prompt_hint = '' ) {
		$code = sanitize_key( $code );
		$slug = sanitize_title( $slug ?: $code );
		$name = sanitize_text_field( $name );
		$script = sanitize_key( $script ?: 'default' );
		$prompt_hint = sanitize_textarea_field( $prompt_hint ?: '' );

		if ( empty( $code ) || empty( $name ) ) {
			return new WP_Error( 'invalid_input', 'Language code and name are required.' );
		}

		$langs = self::get_languages();
		foreach ( $langs as $lang ) {
			if ( $lang['code'] === $code ) {
				return new WP_Error( 'already_exists', "Language '{$code}' already exists." );
			}
		}

		$root_page_id = 0;
		if ( empty( $flag ) ) {
			$flag_map = array( 'zh' => '🇨🇳', 'cn' => '🇨🇳', 'en' => '🇺🇸', 'vi' => '🇻🇳', 'ja' => '🇯🇵', 'ko' => '🇰🇷', 'fr' => '🇫🇷', 'de' => '🇩🇪', 'es' => '🇪🇸' );
			$flag     = $flag_map[ $code ] ?? '';
		}

		$langs[] = array(
			'code'         => $code,
			'name'         => $name,
			'flag'         => $flag ?: '',
			'slug'         => $slug,
			'script'       => $script,
			'prompt_hint'  => $prompt_hint,
			'is_default'   => false,
			'root_page_id' => 0,
		);

		update_option( self::OPTION_KEY, $langs );
		if ( class_exists( 'Align_Post_Language_Meta' ) ) {
			Align_Post_Language_Meta::register_post_lang_rewrites();
		}
		flush_rewrite_rules( false );
		error_log( "[Align LangManager] Added language: {$code} | name: {$name} | flag: {$flag} | script: {$script}" );
		return true;
	}

	/**
	 * Update an existing language config (including default language flag/name).
	 */
	public static function update_language( $code, $name, $flag, $slug = '', $script = 'default', $prompt_hint = '' ) {
		$code        = sanitize_key( $code );
		$name        = sanitize_text_field( $name );
		$flag        = sanitize_text_field( $flag );
		$script      = sanitize_key( $script ?: 'default' );
		$prompt_hint = sanitize_textarea_field( $prompt_hint ?: '' );

		if ( empty( $code ) || empty( $name ) ) {
			return new WP_Error( 'invalid_input', 'Language code and name are required.' );
		}

		$langs = self::get_languages();
		$found = false;

		foreach ( $langs as &$lang ) {
			if ( $lang['code'] === $code ) {
				$lang['name']        = $name;
				$lang['flag']        = $flag;
				$lang['script']      = $script;
				$lang['prompt_hint'] = $prompt_hint;
				if ( empty( $lang['is_default'] ) && ! empty( $slug ) ) {
					$lang['slug'] = sanitize_title( $slug );
				}
				$found = true;
				break;
			}
		}

		if ( ! $found ) {
			return new WP_Error( 'not_found', "Language '{$code}' not found." );
		}

		update_option( self::OPTION_KEY, $langs );
		if ( class_exists( 'Align_Post_Language_Meta' ) ) {
			Align_Post_Language_Meta::register_post_lang_rewrites();
		}
		flush_rewrite_rules( false );
		error_log( "[Align LangManager] Updated language: {$code} | name: {$name} | flag: {$flag} | script: {$script}" );
		return true;
	}

	/**
	 * Remove a language. Cannot remove the default language.
	 *
	 * @param string $code Language code to remove.
	 * @return true|WP_Error
	 */
	public static function remove_language( $code ) {
		$code  = sanitize_key( $code );
		$langs = self::get_languages();
		$new   = array();
		$found = false;

		foreach ( $langs as $lang ) {
			if ( $lang['code'] === $code ) {
				$found = true;
				if ( ! empty( $lang['is_default'] ) ) {
					return new WP_Error( 'cannot_remove_default', 'Cannot remove the default language.' );
				}
				// Note: We keep the Root Lang Page intact to avoid breaking existing URLs.
				continue;
			}
			$new[] = $lang;
		}

		if ( ! $found ) {
			return new WP_Error( 'not_found', "Language '{$code}' not found." );
		}

		update_option( self::OPTION_KEY, $new );
		if ( class_exists( 'Align_Post_Language_Meta' ) ) {
			Align_Post_Language_Meta::register_post_lang_rewrites();
		}
		flush_rewrite_rules( false );
		error_log( '[Align LangManager] Removed language: ' . $code );
		return true;
	}

	/**
	 * Ensure a hidden Root Lang Page exists for a URL slug prefix.
	 * Creates one if it doesn't exist. Returns the page ID.
	 *
	 * @param string $slug URL slug (e.g. 'zh').
	 * @param string $name Language name for page title.
	 * @return int Page ID.
	 */
	public static function ensure_root_lang_page( $slug, $name ) {
		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $existing ) {
			error_log( "[Align LangManager] Root lang page already exists: {$slug} (ID: {$existing->ID})" );
			return $existing->ID;
		}

		$page_id = wp_insert_post( array(
			'post_title'     => $name . ' Root',
			'post_name'      => $slug,
			'post_status'    => 'publish',
			'post_type'      => 'page',
			'post_content'   => '',
			'post_author'    => get_current_user_id(),
			'menu_order'     => 9999,
			'comment_status' => 'closed',
		) );

		if ( is_wp_error( $page_id ) ) {
			error_log( "[Align LangManager] Failed to create root lang page for: {$slug} | " . $page_id->get_error_message() );
			return 0;
		}

		update_post_meta( $page_id, '_align_lang_root', $slug );
		update_post_meta( $page_id, '_align_lang', $slug );
		update_post_meta( $page_id, '_align_hide_from_nav', '1' );
		error_log( "[Align LangManager] Created root lang page: {$slug} (ID: {$page_id})" );
		return $page_id;
	}

	// -------------------------------------------------------------------------
	// Post Language Meta Helpers
	// -------------------------------------------------------------------------

	public static function get_post_lang( $post_id ) {
		return (string) get_post_meta( (int) $post_id, self::META_LANG, true );
	}

	/**
	 * Check if a post is the front page for a language.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $lang    Optional language code.
	 * @return bool
	 */
	public static function is_language_front_page( int $post_id, string $lang = '' ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}
		$is_lang_front = (string) get_post_meta( $post_id, '_align_is_lang_front', true );
		if ( '' !== $is_lang_front && ( '' === $lang || $is_lang_front === $lang ) ) {
			return true;
		}
		$front_id    = (int) get_option( 'page_on_front' );
		$front_group = $front_id > 0 ? (string) get_post_meta( $front_id, self::META_GROUP, true ) : '';
		$post_group  = (string) get_post_meta( $post_id, self::META_GROUP, true );

		$is_lang_root = (string) get_post_meta( $post_id, '_align_lang_root', true );
		if ( '' !== $is_lang_root && ( '' === $lang || $is_lang_root === $lang ) ) {
			$post = get_post( $post_id );
			if ( ( '' !== $front_group && $front_group === $post_group ) || ( $post && '' !== trim( (string) $post->post_content ) ) ) {
				return true;
			}
		}

		if ( $front_id > 0 ) {
			if ( $post_id === $front_id ) {
				return true;
			}
			if ( (int) get_post_meta( $post_id, self::META_PARENT, true ) === $front_id ) {
				return true;
			}
			if ( '' !== $front_group && $front_group === $post_group ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the front page ID for a specific language.
	 *
	 * @param string $lang Language code.
	 * @return int
	 */
	public static function get_language_front_page_id( string $lang ): int {
		$lang = sanitize_key( $lang );
		if ( '' === $lang ) {
			return 0;
		}
		if ( $lang === self::get_default_lang() ) {
			return (int) get_option( 'page_on_front' );
		}
		$front_id = (int) get_option( 'page_on_front' );
		if ( $front_id > 0 ) {
			foreach ( self::get_translations( $front_id ) as $tr ) {
				if ( isset( $tr['lang'] ) && $tr['lang'] === $lang ) {
					return (int) $tr['post_id'];
				}
			}
		}
		$pages = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'AND',
				array( 'key' => self::META_LANG, 'value' => $lang ),
				array(
					'relation' => 'OR',
					array( 'key' => '_align_is_lang_front', 'value' => $lang ),
					array( 'key' => '_align_lang_root', 'value' => $lang ),
				),
			),
		) );
		return ! empty( $pages ) ? (int) $pages[0] : 0;
	}

	public static function get_translations( $post_id ) {
		$group = get_post_meta( (int) $post_id, self::META_GROUP, true );
		if ( ! $group ) return array();

		$post_type = get_post_type( (int) $post_id ) ?: 'any';
		$siblings  = get_posts( array(
			'post_type'      => $post_type,
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => -1,
			'meta_query'     => array( array( 'key' => self::META_GROUP, 'value' => $group ) ),
			'exclude'        => array( (int) $post_id ),
		) );

		$langs  = self::get_languages();
		$result = array();
		foreach ( $siblings as $p ) {
			$lang_code = get_post_meta( $p->ID, self::META_LANG, true );
			$lang_cfg  = null;
			foreach ( $langs as $l ) {
				if ( $l['code'] === $lang_code ) {
					$lang_cfg = $l;
					break;
				}
			}
			$result[] = array(
				'post_id'   => $p->ID,
				'lang'      => $lang_code,
				'lang_name' => $lang_cfg ? $lang_cfg['name'] : $lang_code,
				'flag'      => $lang_cfg ? $lang_cfg['flag'] : '',
				'title'     => $p->post_title,
				'url'       => get_permalink( $p->ID ),
			);
		}
		return $result;
	}

	// -------------------------------------------------------------------------
	// Translation Creation
	// -------------------------------------------------------------------------

	/**
	 * Create a translation of a post in the target language.
	 * Copies post content, assigns lang group, sets page parent for URL prefix,
	 * and copies RankMath meta as template.
	 *
	 * @param int    $source_id   Source post ID.
	 * @param string $target_lang Target language code (e.g. 'zh').
	 * @return int|WP_Error New post ID or WP_Error.
	 */
	public static function create_translation( $source_id, $target_lang ) {
		$source_id   = (int) $source_id;
		$target_lang = sanitize_key( $target_lang );
		$source      = get_post( $source_id );

		if ( ! $source ) {
			return new WP_Error( 'not_found', 'Source post not found.' );
		}

		$post_type = $source->post_type;

		// Validate target language exists
		$target_lang_cfg = self::get_language( $target_lang );
		if ( ! $target_lang_cfg ) {
			return new WP_Error( 'invalid_lang', "Language '{$target_lang}' is not configured." );
		}

		// Check if translation for this lang already exists in the same group
		$existing_group = get_post_meta( $source_id, self::META_GROUP, true );
		if ( $existing_group ) {
			$existing = get_posts( array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => 1,
				'meta_query'     => array(
					array( 'key' => self::META_GROUP, 'value' => $existing_group ),
					array( 'key' => self::META_LANG,  'value' => $target_lang ),
				),
			) );
			if ( ! empty( $existing ) ) {
				return new WP_Error( 'already_translated', "Translation in '{$target_lang}' already exists (ID: " . $existing[0]->ID . ').', array( 'existing_id' => $existing[0]->ID ) );
			}
		}

		// Ensure source has a lang group
		$group = $existing_group;
		if ( ! $group ) {
			$group = 'grp-' . substr( md5( uniqid( 'align_lang_', true ) ), 0, 12 );
			update_post_meta( $source_id, self::META_GROUP, $group );
			if ( ! get_post_meta( $source_id, self::META_LANG, true ) ) {
				update_post_meta( $source_id, self::META_LANG, self::get_default_lang() );
			}
		}

		// Determine parent for hierarchical post types (WPML/Polylang style)
		$post_parent = 0;
		if ( 'page' === $post_type && (int) $source->post_parent > 0 ) {
			$parent_translations = self::get_translations( (int) $source->post_parent );
			foreach ( $parent_translations as $parent_tr ) {
				if ( isset( $parent_tr['lang'] ) && $parent_tr['lang'] === $target_lang ) {
					$parent_tr_id = (int) $parent_tr['post_id'];
					if ( ! get_post_meta( $parent_tr_id, '_align_lang_root', true ) ) {
						$post_parent = $parent_tr_id;
						break;
					}
				}
			}
		}

		// Create translation post with KSES disabled to preserve script tags
		kses_remove_filters();
		$is_front_page = ( $source_id === (int) get_option( 'page_on_front' ) );
		$post_data     = array(
			'post_title'   => $source->post_title . ' [' . strtoupper( $target_lang ) . ']',
			'post_content' => wp_slash( $source->post_content ),
			'post_status'  => $is_front_page ? 'publish' : $source->post_status,
			'post_type'    => $post_type,
			'post_name'    => $source->post_name . '-' . $target_lang,
			'post_author'  => get_current_user_id(),
			'post_parent'  => $post_parent,
		);
		$new_id = wp_insert_post( $post_data );
		kses_init_filters();

		if ( is_wp_error( $new_id ) ) {
			error_log( '[Align LangManager] Failed to create translation | Error: ' . $new_id->get_error_message() );
			return $new_id;
		}

		update_post_meta( $new_id, self::META_LANG,   $target_lang );
		update_post_meta( $new_id, self::META_GROUP,  $group );
		update_post_meta( $new_id, self::META_PARENT, $source_id );
		if ( $is_front_page ) {
			update_post_meta( $new_id, '_align_is_lang_front', $target_lang );
		}
		self::copy_rankmath_meta( $source_id, $new_id );

		// Copy post-specific data (categories, thumbnail)
		if ( 'post' === $post_type ) {
			$cats = wp_get_post_categories( $source_id );
			if ( ! empty( $cats ) ) wp_set_post_categories( $new_id, $cats );
		}
		$thumb_id = get_post_thumbnail_id( $source_id );
		if ( $thumb_id ) set_post_thumbnail( $new_id, $thumb_id );

		error_log( '[Align LangManager] Created translation | source_id=' . $source_id . ' | target_lang=' . $target_lang . ' | new_id=' . $new_id . ' | type=' . $post_type );
		return $new_id;
	}

	public static function copy_rankmath_meta( $source_id, $target_id ) {
		$keys = array( 'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword', 'rank_math_og_title', 'rank_math_og_description', 'rank_math_twitter_title', 'rank_math_twitter_description', 'rank_math_robots', 'rank_math_canonical_url', 'rank_math_seo_score' );
		$copied = 0;
		foreach ( $keys as $key ) {
			$val = get_post_meta( $source_id, $key, true );
			if ( ! empty( $val ) ) {
				update_post_meta( $target_id, $key, $val );
				$copied++;
			}
		}
		error_log( '[Align LangManager] Copied ' . $copied . ' RankMath meta keys from post ' . $source_id . ' to ' . $target_id );
	}

	// -------------------------------------------------------------------------
	// REST API Callbacks
	// -------------------------------------------------------------------------

	/**
	 * REST: Get active languages list.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function rest_get_languages( $request ) {
		return rest_ensure_response( self::get_languages() );
	}

	/**
	 * REST: Create translation of a page in a target language.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_create_translation( $request ) {
		$source_id   = intval( $request->get_param( 'post_id' ) );
		$target_lang = sanitize_key( $request->get_param( 'target_lang' ) );

		if ( ! $source_id || ! $target_lang ) {
			return new WP_Error( 'missing_params', 'post_id and target_lang are required.', array( 'status' => 400 ) );
		}

		$result = self::create_translation( $source_id, $target_lang );

		if ( is_wp_error( $result ) ) {
			$err_data = is_array( $result->get_error_data() ) ? $result->get_error_data() : array();
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array_merge( array( 'status' => 400 ), $err_data ) );
		}

		$new_post    = get_post( $result );
		$lang_cfg    = self::get_language( $target_lang );
		$editor_url  = admin_url( 'admin.php?page=' . ALIGN_BUILDER_SLUG . '&post_id=' . $result . '&lang=' . $target_lang );

		return rest_ensure_response( array(
			'success'    => true,
			'new_post_id' => $result,
			'title'      => $new_post ? $new_post->post_title : '',
			'lang'       => $target_lang,
			'lang_name'  => $lang_cfg ? $lang_cfg['name'] : $target_lang,
			'editor_url' => $editor_url,
		) );
	}

	// -------------------------------------------------------------------------
	// AJAX Handlers (for Settings UI)
	// -------------------------------------------------------------------------

	public static function ajax_add_language() {
		check_ajax_referer( 'align_language_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

		$code        = sanitize_key( $_POST['code'] ?? '' );
		$name        = sanitize_text_field( $_POST['name'] ?? '' );
		$flag        = sanitize_text_field( $_POST['flag'] ?? '' );
		$slug        = sanitize_title( $_POST['slug'] ?? '' );
		$script      = sanitize_key( $_POST['script'] ?? 'default' );
		$prompt_hint = sanitize_textarea_field( $_POST['prompt_hint'] ?? '' );

		$result = self::add_language( $code, $name, $flag, $slug, $script, $prompt_hint );
		if ( is_wp_error( $result ) ) wp_send_json_error( $result->get_error_message() );
		wp_send_json_success( array( 'languages' => self::get_languages() ) );
	}

	public static function ajax_update_language() {
		check_ajax_referer( 'align_language_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

		$code        = sanitize_key( $_POST['code'] ?? '' );
		$name        = sanitize_text_field( $_POST['name'] ?? '' );
		$flag        = sanitize_text_field( $_POST['flag'] ?? '' );
		$slug        = sanitize_title( $_POST['slug'] ?? '' );
		$script      = sanitize_key( $_POST['script'] ?? 'default' );
		$prompt_hint = sanitize_textarea_field( $_POST['prompt_hint'] ?? '' );

		$result = self::update_language( $code, $name, $flag, $slug, $script, $prompt_hint );
		if ( is_wp_error( $result ) ) wp_send_json_error( $result->get_error_message() );
		wp_send_json_success( array( 'languages' => self::get_languages() ) );
	}

	public static function ajax_remove_language() {
		check_ajax_referer( 'align_language_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

		$code   = sanitize_key( $_POST['code'] ?? '' );
		$result = self::remove_language( $code );
		if ( is_wp_error( $result ) ) wp_send_json_error( $result->get_error_message() );
		wp_send_json_success( array( 'languages' => self::get_languages() ) );
	}
}
