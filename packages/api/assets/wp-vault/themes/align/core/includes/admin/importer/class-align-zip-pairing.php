<?php
/**
 * Align Theme Core Engine - ZIP Importer Multilingual Pairing Engine
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Zip_Pairing {

	public static array $default_aliases = array(
		'about'    => 'about-us',
		'about-us' => 'about',
	);

	/**
	 * Pair entities across languages and assign translation groups.
	 *
	 * @param array  $path_to_id_map Map of path_key => post_id.
	 * @param array  $catalog        Full session catalog.
	 * @param array  $custom_aliases Optional aliases.
	 * @param array  $manifest       Optional translation manifest.
	 * @return array Pairing summary (linked_count, unpaired).
	 */
	public static function pair(
		array $path_to_id_map,
		array $catalog,
		array $custom_aliases = array(),
		array $manifest = array()
	): array {
		$detected_langs = $catalog['languages'] ?? array();
		$default_lang   = ( class_exists( 'Align_Language_Manager' ) && method_exists( 'Align_Language_Manager', 'get_default_lang' ) )
			? Align_Language_Manager::get_default_lang()
			: 'en';

		// Tag default lang if missing
		foreach ( $path_to_id_map as $path_key => $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id <= 0 ) continue;
			$first_seg = explode( '/', $path_key )[0];
			if ( ! isset( $detected_langs[ $first_seg ] ) ) {
				$cur = get_post_meta( $post_id, '_align_lang', true );
				if ( empty( $cur ) ) {
					update_post_meta( $post_id, '_align_lang', $default_lang );
				}
			}
		}

		// Separate entities into Default Lang vs Secondary Langs
		// Structure: $default_entries[type][key] = ['post_id' => int, 'path_key' => string, 'slug' => string, 'item' => array]
		$default_pages = array();
		$default_posts = array();
		$other_pages   = array();
		$other_posts   = array();

		$pages_catalog = $catalog['pages'] ?? array();
		$pages_by_pk   = array();
		foreach ( $pages_catalog as $p_item ) {
			$pk = $p_item['path_key'] ?? '';
			if ( '' !== $pk ) {
				$pages_by_pk[ $pk ] = $p_item;
			}
		}

		foreach ( $path_to_id_map as $path_key => $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id <= 0 ) continue;

			$post_obj = get_post( $post_id );
			if ( ! $post_obj ) continue;

			$post_type = $post_obj->post_type;
			$lang      = get_post_meta( $post_id, '_align_lang', true ) ?: $default_lang;
			$item      = $pages_by_pk[ $path_key ] ?? array();
			$slug      = $post_obj->post_name;

			$entry = array(
				'post_id'  => $post_id,
				'path_key' => $path_key,
				'slug'     => $slug,
				'lang'     => $lang,
				'item'     => $item,
			);

			if ( $lang === $default_lang ) {
				if ( 'post' === $post_type ) {
					$default_posts[ $path_key ] = $entry;
				} else {
					$default_pages[ $path_key ] = $entry;
				}
			} else {
				if ( 'post' === $post_type ) {
					$other_posts[] = $entry;
				} else {
					$other_pages[] = $entry;
				}
			}
		}

		$linked_groups = array(); // group_key => [lang => post_id]
		$aliases       = array_merge( self::$default_aliases, $custom_aliases );
		$unpaired      = array();

		// Helper to normalize homepage key
		$home_keys = array( 'home', 'front-page', 'trang-chu', 'index', '' );

		// 1 & 2 & 3: PAIR PAGES
		$paired_default_pages = array();
		foreach ( $other_pages as $sec ) {
			$sec_lang = $sec['lang'];
			$sec_pk   = $sec['path_key'];
			$sec_pid  = $sec['post_id'];

			// Strip lang prefix
			$segs = explode( '/', $sec_pk );
			if ( $segs[0] === $sec_lang || isset( $detected_langs[ $segs[0] ] ) ) {
				array_shift( $segs );
			}
			$clean_key = implode( '/', $segs );

			// Check home key normalization
			$is_root_page  = (bool) get_post_meta( $sec_pid, '_align_lang_root', true );
			$is_lang_front = (bool) get_post_meta( $sec_pid, '_align_is_lang_front', true )
				|| ( class_exists( 'Align_Language_Manager' ) && method_exists( 'Align_Language_Manager', 'is_language_front_page' ) && Align_Language_Manager::is_language_front_page( $sec_pid, $sec_lang ) );
			if ( $is_root_page || $is_lang_front || in_array( $clean_key, $home_keys, true ) ) {
				$clean_key = '';
			}

			$matched_def_pk = null;

			// Manifest check
			if ( ! empty( $manifest[ $sec_pk ] ) && isset( $default_pages[ $manifest[ $sec_pk ] ] ) ) {
				$matched_def_pk = $manifest[ $sec_pk ];
			}

			// Normalized path match
			if ( null === $matched_def_pk ) {
				foreach ( $default_pages as $def_pk => $def_info ) {
					if ( isset( $paired_default_pages[ $def_pk ] ) ) continue;
					$def_clean = $def_pk;
					$is_def_home = ! empty( $def_info['item']['is_home'] ) || in_array( $def_pk, $home_keys, true );
					if ( $is_def_home ) {
						$def_clean = '';
					}
					if ( $def_clean === $clean_key ) {
						$matched_def_pk = $def_pk;
						break;
					}
				}
			}

			// Alias match
			if ( null === $matched_def_pk ) {
				$sec_slug = basename( $clean_key );
				if ( isset( $aliases[ $sec_slug ] ) ) {
					$target_alias = $aliases[ $sec_slug ];
					foreach ( $default_pages as $def_pk => $def_info ) {
						if ( isset( $paired_default_pages[ $def_pk ] ) ) continue;
						$def_slug = basename( $def_pk );
						if ( $def_slug === $target_alias ) {
							$matched_def_pk = $def_pk;
							break;
						}
					}
				}
			}

			if ( null !== $matched_def_pk ) {
				$paired_default_pages[ $matched_def_pk ] = true;
				$def_pid = $default_pages[ $matched_def_pk ]['post_id'];
				$grp_key = 'page:' . $matched_def_pk;
				$linked_groups[ $grp_key ] = array(
					$default_lang => $def_pid,
					$sec_lang     => $sec_pid,
				);
			} else {
				$unpaired[] = $sec;
			}
		}

		// 4: PAIR POSTS (Hero image matching deterministic 30/30)
		$paired_default_posts = array();
		foreach ( $other_posts as $sec_post ) {
			$sec_lang = $sec_post['lang'];
			$sec_pid  = $sec_post['post_id'];
			$sec_html = ! empty( $sec_post['item']['html_file'] ) && file_exists( $sec_post['item']['html_file'] )
				? (string) file_get_contents( $sec_post['item']['html_file'] )
				: ( ( $p_obj = get_post( $sec_pid ) ) ? $p_obj->post_content : '' );

			$en_slug_from_hero = self::extract_en_slug_from_hero( $sec_html );
			$matched_def_pk    = null;

			if ( $en_slug_from_hero ) {
				foreach ( $default_posts as $def_pk => $def_info ) {
					if ( isset( $paired_default_posts[ $def_pk ] ) ) continue;
					$def_slug = $def_info['slug'];
					$def_leaf = basename( $def_pk );
					if ( $def_slug === $en_slug_from_hero || $def_leaf === $en_slug_from_hero ) {
						$matched_def_pk = $def_pk;
						break;
					}
				}
			}

			// Fallback: Path key match or manifest
			if ( null === $matched_def_pk && ! empty( $manifest[ $sec_post['path_key'] ] ) ) {
				$mf_pk = $manifest[ $sec_post['path_key'] ];
				if ( isset( $default_posts[ $mf_pk ] ) && ! isset( $paired_default_posts[ $mf_pk ] ) ) {
					$matched_def_pk = $mf_pk;
				}
			}

			if ( null !== $matched_def_pk ) {
				$paired_default_posts[ $matched_def_pk ] = true;
				$def_pid = $default_posts[ $matched_def_pk ]['post_id'];
				$grp_key = 'post:' . $matched_def_pk;
				$linked_groups[ $grp_key ] = array(
					$default_lang => $def_pid,
					$sec_lang     => $sec_pid,
				);
			} else {
				$unpaired[] = $sec_post;
			}
		}

		// Apply Groups & Metas
		$linked_count = 0;
		foreach ( $linked_groups as $grp_key => $lang_posts ) {
			$default_id = $lang_posts[ $default_lang ] ?? null;
			if ( $default_id ) {
				$group_id = get_post_meta( $default_id, '_align_lang_group', true );
				if ( empty( $group_id ) ) {
					$group_id = 'grp-' . substr( md5( uniqid( 'align_lang_', true ) ), 0, 12 );
					update_post_meta( $default_id, '_align_lang_group', $group_id );
				}
			} else {
				$group_id = 'grp-' . substr( md5( uniqid( 'align_lang_', true ) ), 0, 12 );
			}

			foreach ( $lang_posts as $l => $pid ) {
				update_post_meta( $pid, '_align_lang_group', $group_id );
				if ( $default_id && $pid !== $default_id ) {
					update_post_meta( $pid, '_align_lang_parent', (int) $default_id );
				}
			}
			if ( count( $lang_posts ) > 1 ) {
				$linked_count++;
			}
		}

		error_log( sprintf( '[Align ZipPairing] Successfully paired %d translation groups (%d unpaired)', $linked_count, count( $unpaired ) ) );

		return array(
			'linked_count' => $linked_count,
			'groups'       => $linked_groups,
			'unpaired'     => $unpaired,
		);
	}

	/**
	 * Extract EN slug from hero image src using DOMDocument.
	 *
	 * @param string $html Post HTML markup.
	 * @return string|null EN slug or null.
	 */
	public static function extract_en_slug_from_hero( string $html ): ?string {
		if ( empty( $html ) ) return null;

		$prev_errors = libxml_use_internal_errors( true );
		$dom         = new DOMDocument();
		$loaded      = $dom->loadHTML( mb_encode_numericentity( $html, array( 0x80, 0x10FFFF, 0, ~0 ), 'UTF-8' ), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev_errors );

		if ( ! $loaded ) return null;

		$imgs = $dom->getElementsByTagName( 'img' );
		foreach ( $imgs as $img ) {
			$src = $img->getAttribute( 'src' );
			if ( '' === $src ) continue;

			// Pattern 1: .../assets/img/blog/<slug>/hero... or .../blog/<slug>/hero...
			if ( preg_match( '#/(?:assets/img|img)/blog/([^/]+)/#i', $src, $m ) ) {
				return sanitize_title( $m[1] );
			}
			// Pattern 2: general blog subfolder in src
			if ( preg_match( '#/blog/([^/]+)/#i', $src, $m ) ) {
				return sanitize_title( $m[1] );
			}
		}

		return null;
	}
}
