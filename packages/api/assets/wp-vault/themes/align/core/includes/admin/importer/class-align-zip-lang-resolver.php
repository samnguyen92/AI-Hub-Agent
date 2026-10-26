<?php
/**
 * Align Theme Core Engine - ZIP Importer Language Resolver & Multilingual Manager
 *
 * Handles language directory detection, root page resolution/repair,
 * language tagging, translation group linking, and rewrite rule flushing.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Zip_Lang_Resolver {

	public const KNOWN_LANGS = array(
		'vi'    => 'Vietnamese',
		'en'    => 'English',
		'zh'    => 'Chinese (Simplified)',
		'zh-hk' => 'Chinese (Hong Kong)',
		'zh-tw' => 'Chinese (Traditional)',
		'ja'    => 'Japanese',
		'ko'    => 'Korean',
		'fr'    => 'French',
		'de'    => 'German',
		'es'    => 'Spanish',
		'it'    => 'Italian',
		'pt'    => 'Portuguese',
		'ru'    => 'Russian',
		'th'    => 'Thai',
		'id'    => 'Indonesian',
		'ms'    => 'Malay',
		'ar'    => 'Arabic',
		'hi'    => 'Hindi',
		'nl'    => 'Dutch',
	);

	public static function detect_lang_dirs( string $root_dir ): array {
		$root_dir = wp_normalize_path( trailingslashit( $root_dir ) );
		if ( ! is_dir( $root_dir ) ) return array();

		$configured_langs = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_languages() : array();
		$default_lang     = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en';

		$configured_map = array();
		foreach ( $configured_langs as $cfg ) {
			$c = strtolower( trim( $cfg['code'] ?? '' ) );
			$s = strtolower( trim( $cfg['slug'] ?? '' ) );
			if ( '' !== $c ) $configured_map[ $c ] = $cfg;
			if ( '' !== $s ) $configured_map[ $s ] = $cfg;
		}

		$entries = scandir( $root_dir );
		if ( false === $entries ) return array();
		$detected = array();

		foreach ( $entries as $entry ) {
			if ( in_array( $entry, array( '.', '..' ), true ) || 0 === strpos( $entry, '._' ) || 0 === strpos( $entry, '.' ) || '__MACOSX' === $entry ) continue;
			$full_path = wp_normalize_path( $root_dir . $entry );
			if ( ! is_dir( $full_path ) ) continue;

			$lower_name    = strtolower( trim( $entry ) );
			$is_configured = isset( $configured_map[ $lower_name ] );
			$is_known      = isset( self::KNOWN_LANGS[ $lower_name ] );
			if ( ! $is_configured && ! $is_known ) continue;

			$code = $lower_name;
			$name = self::KNOWN_LANGS[ $lower_name ] ?? ( $configured_map[ $lower_name ]['name'] ?? ucfirst( $lower_name ) );
			if ( $is_configured ) {
				$cfg_item = $configured_map[ $lower_name ];
				$code     = $cfg_item['code'] ?? $lower_name;
				$name     = $cfg_item['name'] ?? $name;
			}

			$is_default = ( $code === $default_lang );
			$detected[ $lower_name ] = array(
				'code'        => $code,
				'name'        => $name,
				'dir'         => $full_path,
				'folder_name' => $entry,
				'is_default'  => $is_default,
				'configured'  => $is_configured,
			);
			error_log( sprintf( '[Align ZipLang] Language folder detected: %s (configured=%s, default=%s)', $entry, $is_configured ? 'true' : 'false', $is_default ? 'true' : 'false' ) );
		}
		return $detected;
	}

	public static function scan_language_folder( string $lang_dir, string $path_prefix, string $lang ): array {
		$lang_dir = wp_normalize_path( trailingslashit( $lang_dir ) );
		$result   = array( 'pages' => array(), 'template_parts' => array() );
		if ( ! is_dir( $lang_dir ) ) return $result;
		$entries = scandir( $lang_dir );
		if ( false === $entries ) return $result;

		$exclude_page_dirs = array(
			'assets', 'vendor', 'node_modules', '.git', 'dist', 'standalone', 'backup', 'backups', 'archive', 'archives', 'old', 'temp', 'tmp',
			'header', 'headers', 'template-parts', 'parts', 'footer', 'footers',
			'reuse-block', 'reuse-blocks', 'reusable-blocks', 'blocks', 'patterns', 'sections', 'components',
		);

		foreach ( $entries as $entry ) {
			if ( in_array( $entry, array( '.', '..' ), true ) || 0 === strpos( $entry, '._' ) || 0 === strpos( $entry, '.' ) || '__MACOSX' === $entry ) continue;
			$full_path  = wp_normalize_path( $lang_dir . $entry );
			$lower_name = strtolower( trim( $entry ) );

			if ( is_dir( $full_path ) ) {
				$folder_trail = trailingslashit( $full_path );
				if ( in_array( $lower_name, array( 'header', 'headers' ), true ) ) {
					$part = self::inspect_lang_part_folder( $folder_trail, 'header', $lang );
					if ( $part ) $result['template_parts'][ 'header-' . $lang ] = $part;
					continue;
				}
				if ( in_array( $lower_name, array( 'footer', 'footers' ), true ) ) {
					$part = self::inspect_lang_part_folder( $folder_trail, 'footer', $lang );
					if ( $part ) $result['template_parts'][ 'footer-' . $lang ] = $part;
					continue;
				}
				if ( in_array( $lower_name, array( 'reuse-block', 'reuse-blocks', 'reusable-blocks', 'blocks', 'patterns', 'sections', 'components' ), true ) ) {
					error_log( sprintf( '[Align ZipLang] Localized reusable blocks not supported: %s', $full_path ) );
					continue;
				}
			} elseif ( is_file( $full_path ) && in_array( strtolower( pathinfo( $entry, PATHINFO_EXTENSION ) ), array( 'html', 'htm' ), true ) ) {
				$base = strtolower( pathinfo( $entry, PATHINFO_FILENAME ) );
				if ( 'header' === $base || 'footer' === $base ) {
					$part_slug = $base . '-' . $lang;
					$result['template_parts'][ $part_slug ] = array(
						'type' => 'template_part', 'folder_name' => '', 'folder_path' => $lang_dir,
						'slug' => $part_slug, 'title' => ucfirst( $base ) . ' (' . strtoupper( $lang ) . ')', 'html_file' => $full_path,
						'css_files' => array(), 'js_files' => array(), 'media_files' => array(), 'is_home' => false,
					);
				}
			}
		}

		if ( class_exists( 'Align_Zip_Page_Tree' ) ) {
			$raw_pages = Align_Zip_Page_Tree::collect_pages( $lang_dir, $exclude_page_dirs, 5, Align_Zip_Page_Tree::BLOG_DIRS );
			$raw_posts = Align_Zip_Page_Tree::collect_posts( $lang_dir, 5 );

			foreach ( $raw_pages as $p ) {
				$p['lang'] = $lang;
				$orig_pk   = $p['path_key'] ?? '';
				if ( '' === $orig_pk || true === ( $p['is_home'] ?? false ) ) {
					$p['entity']     = 'lang_root';
					$p['path_key']   = $path_prefix;
					$p['parent_key'] = '';
					$p['depth']      = 1;
					$p['slug']       = '' !== $path_prefix ? 'home-' . $lang : 'home';
					$p['is_home']    = false;
				} else {
					$p['path_key'] = trim( $path_prefix . '/' . $orig_pk, '/' );
					$segments      = array_values( array_filter( explode( '/', trim( $orig_pk, '/' ) ), 'strlen' ) );
					$p['depth']    = count( $segments );
					if ( count( $segments ) <= 1 ) {
						$p['parent_key'] = '';
					} else {
						$parent_rel      = implode( '/', array_slice( $segments, 0, -1 ) );
						$p['parent_key'] = '' !== $path_prefix ? trim( $path_prefix . '/' . $parent_rel, '/' ) : $parent_rel;
					}
				}
				$result['pages'][] = $p;
			}

			foreach ( $raw_posts as $post ) {
				$post['lang']     = $lang;
				$orig_pk          = $post['path_key'] ?? '';
				$post['path_key'] = trim( $path_prefix . '/' . $orig_pk, '/' );
				$post['depth']    = substr_count( $post['path_key'], '/' ) + 1;
				if ( 'page' === ( $post['entity'] ?? 'post' ) ) {
					$post['parent_key'] = '';
				}
				$result['pages'][] = $post;
			}
		}
		return $result;
	}

	private static function inspect_lang_part_folder( string $folder_path, string $part_type, string $lang ): ?array {
		if ( ! is_dir( $folder_path ) ) return null;
		$iterator    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $folder_path, RecursiveDirectoryIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
		$html_file   = null;
		$css_files   = array();
		$js_files    = array();
		$media_files = array();
		$img_exts    = array( 'png', 'jpg', 'jpeg', 'webp', 'svg', 'gif', 'ico', 'mp4' );

		foreach ( $iterator as $item ) {
			if ( ! $item->isFile() ) continue;
			$path = wp_normalize_path( $item->getPathname() );
			$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
			$name = strtolower( pathinfo( $path, PATHINFO_FILENAME ) );
			if ( 'html' === $ext || 'htm' === $ext ) {
				if ( null === $html_file || 'index' === $name || $name === $part_type ) $html_file = $path;
			} elseif ( 'css' === $ext ) $css_files[] = $path;
			elseif ( 'js' === $ext ) $js_files[] = $path;
			elseif ( in_array( $ext, $img_exts, true ) ) $media_files[] = $path;
		}
		if ( ! $html_file ) return null;

		$slug = $part_type . '-' . $lang;
		return array(
			'type' => 'template_part', 'folder_name' => basename( $folder_path ), 'folder_path' => $folder_path,
			'slug' => $slug, 'title' => ucfirst( $part_type ) . ' (' . strtoupper( $lang ) . ')', 'html_file' => $html_file,
			'css_files' => $css_files, 'js_files' => $js_files, 'media_files' => $media_files, 'is_home' => false,
		);
	}

	public static function ensure_languages( array $languages, array &$languages_added = array() ): array {
		if ( ! class_exists( 'Align_Language_Manager' ) ) return array();

		foreach ( $languages as $folder_key => $lang_info ) {
			if ( ! empty( $lang_info['is_default'] ) ) continue;

			$code   = $lang_info['code'] ?? $folder_key;
			$name   = $lang_info['name'] ?? ( self::KNOWN_LANGS[ $code ] ?? ucfirst( $code ) );
			$slug   = $lang_info['slug'] ?? $code;
			$flag   = $lang_info['flag'] ?? '';
			$script = $lang_info['script'] ?? 'default';

			$existing_lang = Align_Language_Manager::get_language( $code );
			if ( ! $existing_lang ) {
				$add_res = Align_Language_Manager::add_language( $code, $name, $flag, $slug, $script );
				if ( ! is_wp_error( $add_res ) ) {
					$languages_added[] = $code;
				}
				error_log( sprintf( '[Align ZipLang] Auto-added language %s (slug=%s)', $code, $slug ) );
			}
		}
		return array();
	}

	public static function tag_entity( int $post_id, string $lang ): void {
		if ( $post_id <= 0 || empty( $lang ) ) return;
		update_post_meta( $post_id, '_align_lang', sanitize_key( $lang ) );
		error_log( sprintf( '[Align ZipLang] Tagged post %d lang=%s', $post_id, $lang ) );
	}

	public static function assemble_lang_root( array $info, int $root_id, string $root_dir ): array {
		if ( ! class_exists( 'Align_Zip_Page_Assembler' ) ) return array( 'status' => 'error' );
		$payload = Align_Zip_Page_Assembler::prepare_component_payload( $info, $root_dir );
		if ( null === $payload ) return array( 'status' => 'error', 'message' => 'Empty payload' );

		$lang = sanitize_key( $info['lang'] ?? ( $info['path_key'] ?? 'lang' ) );
		$slug = sanitize_title( $info['slug'] ?? ( 'home-' . $lang ) );
		if ( $slug === $lang || 'lang' === $slug || '' === $slug ) {
			$slug = 'home-' . $lang;
		}

		// Extract title: 1. <title> of index.html, 2. Default home page title, fallback to Language Name + Home
		$resolved_title = '';
		$raw_html = ! empty( $info['html_file'] ) && file_exists( $info['html_file'] ) ? (string) file_get_contents( $info['html_file'] ) : '';
		if ( ! empty( $raw_html ) && preg_match( '/<title\b[^>]*>(.*?)<\/title>/is', $raw_html, $tm ) ) {
			$t_cand = trim( strip_tags( $tm[1] ) );
			if ( '' !== $t_cand ) $resolved_title = $t_cand;
		}
		if ( '' === $resolved_title ) {
			$front_id = (int) get_option( 'page_on_front' );
			if ( $front_id > 0 ) {
				$ft = get_the_title( $front_id );
				if ( ! empty( $ft ) ) $resolved_title = $ft;
			}
		}
		if ( '' === $resolved_title ) {
			$lang_name      = self::KNOWN_LANGS[ $lang ] ?? strtoupper( $lang );
			$resolved_title = $lang_name . ' Home';
		}

		// Find existing language front page
		$post_id = $root_id > 0 ? $root_id : 0;
		if ( $post_id <= 0 && class_exists( 'Align_Language_Manager' ) && method_exists( 'Align_Language_Manager', 'get_language_front_page_id' ) ) {
			$post_id = Align_Language_Manager::get_language_front_page_id( $lang );
		}
		if ( $post_id <= 0 ) {
			$cand_posts = get_posts( array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'post_name__in'  => array_values( array_unique( array( $slug, 'home-' . $lang, 'index-' . $lang, $lang ) ) ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_align_lang',
						'value' => $lang,
					),
				),
			) );
			if ( ! empty( $cand_posts ) ) {
				$post_id = (int) $cand_posts[0];
			}
		}

		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			$post_id = wp_insert_post( array(
				'post_title'   => $resolved_title,
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_parent'  => 0,
				'post_content' => '',
			) );
			if ( ! $post_id || is_wp_error( $post_id ) ) {
				return array( 'status' => 'error', 'message' => 'Failed to create language front page' );
			}
		}

		$post_obj   = get_post( $post_id );
		$final_slug = $post_obj ? $post_obj->post_name : $slug;
		$style_id   = 'style-root-' . $final_slug;
		$script_id  = 'script-root-' . $final_slug;
		$block_html = Align_Zip_Page_Assembler::build_custom_html_block( $payload['body'], $payload['css'], $payload['js'], $style_id, $script_id );

		$update_args = array(
			'ID'           => $post_id,
			'post_content' => wp_slash( $block_html ),
			'post_title'   => $resolved_title,
			'post_status'  => 'publish',
			'post_parent'  => 0,
		);

		kses_remove_filters();
		wp_update_post( $update_args );
		kses_init_filters();

		update_post_meta( $post_id, '_align_lang', $lang );
		update_post_meta( $post_id, '_align_is_lang_front', $lang );
		delete_post_meta( $post_id, '_align_hide_from_nav' );

		if ( class_exists( 'Align_Editor_Mode_Lock' ) && method_exists( 'Align_Editor_Mode_Lock', 'set_mode_and_lock' ) ) {
			Align_Editor_Mode_Lock::set_mode_and_lock( $post_id, 'builder', true );
		}

		$editor_url = admin_url( 'admin.php?page=align-builder&post_id=' . $post_id );
		error_log( sprintf( '[Align ZipLang] Lang front page %s -> ID %d slug="%s" title="%s"', $lang, $post_id, $final_slug, $resolved_title ) );

		return array(
			'status'     => 'success',
			'post_id'    => $post_id,
			'title'      => $resolved_title,
			'slug'       => $final_slug,
			'path_key'   => $info['path_key'] ?? $lang,
			'url'        => get_permalink( $post_id ),
			'editor_url' => $editor_url,
			'entity'     => 'lang_root',
		);
	}

	public static function link_translation_groups( array $path_to_id_map, array $catalog ): int {
		if ( class_exists( 'Align_Zip_Pairing' ) ) {
			$res = Align_Zip_Pairing::pair( $path_to_id_map, $catalog );
			return (int) ( $res['linked_count'] ?? 0 );
		}
		return 0;
	}

	public static function finalize_multilingual( array $path_to_id_map, array $catalog ): void {
		self::link_translation_groups( $path_to_id_map, $catalog );
		if ( ! empty( $catalog['languages_added'] ) || ! empty( $catalog['lang_roots'] ) ) {
			flush_rewrite_rules( false );
			error_log( sprintf( '[Align ZipLang] Rewrite rules flushed (languages: %s)', implode( ', ', array_keys( $catalog['languages'] ?? array() ) ) ) );
		}
	}

	/**
	 * Ensure localized header and footer template parts exist for a language.
	 * Clones original unrewritten EN parts if language-specific parts are missing in ZIP.
	 *
	 * @param string $lang Language code (e.g. 'vi').
	 * @param array  $catalog Session catalog.
	 * @param array  $link_map_2tier 2-tier link map.
	 */
	public static function ensure_localized_template_parts( string $lang, array $catalog, array $link_map_2tier ): void {
		$theme_dir = wp_normalize_path( get_stylesheet_directory() );
		$parts_dir = wp_normalize_path( trailingslashit( $theme_dir ) . 'parts/' );
		if ( ! is_dir( $parts_dir ) ) wp_mkdir_p( $parts_dir );

		$template_parts = $catalog['template_parts'] ?? array();

		foreach ( array( 'header', 'footer' ) as $type ) {
			$localized_slug = $type . '-' . $lang;
			$dest_file      = wp_normalize_path( $parts_dir . $localized_slug . '.html' );

			// If ZIP already provided this localized part, do not clone
			if ( isset( $template_parts[ $localized_slug ] ) ) {
				continue;
			}

			// Find default part HTML file
			$default_part = $template_parts[ $type ] ?? ( $template_parts[ $type . 's' ] ?? null );
			$default_html_file = $default_part['html_file'] ?? '';
			if ( empty( $default_html_file ) || ! file_exists( $default_html_file ) ) {
				$default_child_file = wp_normalize_path( $parts_dir . $type . '.html' );
				if ( file_exists( $default_child_file ) ) {
					$default_html_file = $default_child_file;
				}
			}

			if ( empty( $default_html_file ) || ! file_exists( $default_html_file ) ) {
				continue;
			}

			$raw_content = file_get_contents( $default_html_file );
			if ( false === $raw_content ) continue;

			// 1. Rewrite media assets for the target language part
			$root_dir = $catalog['root_dir'] ?? '';
			if ( class_exists( 'Align_Zip_Media_Rewriter' ) && '' !== $root_dir ) {
				$part_folder = dirname( $default_html_file );
				$raw_content = Align_Zip_Media_Rewriter::rewrite_html_assets( $raw_content, $part_folder, $root_dir );
			}

			// 2. Rewrite links specifically for the target language L
			$rewritten = Align_Zip_Media_Rewriter::rewrite_internal_links( $raw_content, $link_map_2tier, '', $lang );
			@file_put_contents( $dest_file, $rewritten );
			error_log( sprintf( '[Align ZipLang] Cloned localized template part %s for lang=%s (%d bytes)', $localized_slug, $lang, strlen( $rewritten ) ) );
		}
	}
}
