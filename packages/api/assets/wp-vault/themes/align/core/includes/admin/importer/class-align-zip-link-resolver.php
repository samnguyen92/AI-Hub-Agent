<?php
/**
 * Align Theme Core Engine - ZIP Importer Language-Aware Link Resolver
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Zip_Link_Resolver {

	/**
	 * Predefined slug/path aliases across languages.
	 *
	 * @var array
	 */
	public static array $default_aliases = array(
		'about'    => 'about-us',
		'about-us' => 'about',
	);

	/**
	 * Resolve a raw href to a post ID within the source language context.
	 *
	 * @param string $raw_href        Raw link href.
	 * @param string $source_path_key Source entity path key.
	 * @param string $source_lang     Source entity language code.
	 * @param array  $link_map        Hierarchical 2-tier link map: link_map[lang][key] = post_id.
	 * @param array  $aliases         Optional custom aliases.
	 * @param string $default_lang    Default language code.
	 * @param array  &$unresolved     Array to record unresolved link details.
	 * @return int Resolved post ID or 0 if not resolved.
	 */
	public static function resolve(
		string $raw_href,
		string $source_path_key,
		string $source_lang,
		array $link_map,
		array $aliases = array(),
		string $default_lang = 'en',
		array &$unresolved = array()
	): int {
		$clean_path = preg_replace( '/[?#].*$/', '', trim( $raw_href ) );
		if ( '' === $clean_path ) {
			return 0;
		}

		$source_lang = ! empty( $source_lang ) ? $source_lang : $default_lang;
		$merged_aliases = array_merge( self::$default_aliases, $aliases );

		// 1. Resolve href to hierarchical path key
		$path_key = '';
		if ( class_exists( 'Align_Zip_Page_Tree' ) ) {
			$path_key = Align_Zip_Page_Tree::resolve_href_to_key( $clean_path, $source_path_key );
		}
		if ( '' === $path_key ) {
			$clean = trim( str_replace( array( '../', './', 'index.html', '.html', '.htm' ), '', $clean_path ), '/' );
			$path_key = sanitize_title( $clean );
		}

		// 2. If source_lang != default and path_key does not start with source_lang, prefix it
		$target_lang = $source_lang;
		$target_key  = $path_key;

		if ( $source_lang !== $default_lang ) {
			if ( 0 !== strpos( $path_key, $source_lang . '/' ) && $path_key !== $source_lang ) {
				$target_key = '' === $path_key ? $source_lang : $source_lang . '/' . $path_key;
			}
		}

		// Check if target_key explicitly begins with a known language prefix
		if ( preg_match( '#^([a-z]{2,3}(?:-[a-z0-9]+)?)(?:/(.*))?$#i', $target_key, $m ) ) {
			$potential_lang = strtolower( $m[1] );
			if ( isset( $link_map[ $potential_lang ] ) ) {
				$target_lang = $potential_lang;
			}
		}

		$lang_map = $link_map[ $target_lang ] ?? array();

		// 3. Exact match in language map
		if ( isset( $lang_map[ $target_key ] ) ) {
			return (int) $lang_map[ $target_key ];
		}
		if ( isset( $lang_map[ $path_key ] ) ) {
			return (int) $lang_map[ $path_key ];
		}

		// 4. Check aliases within same language
		$slug_only = basename( $target_key );
		if ( isset( $merged_aliases[ $slug_only ] ) ) {
			$aliased_slug = $merged_aliases[ $slug_only ];
			$aliased_key  = dirname( $target_key ) !== '.' && dirname( $target_key ) !== ''
				? dirname( $target_key ) . '/' . $aliased_slug
				: $aliased_slug;

			if ( isset( $lang_map[ $aliased_key ] ) ) {
				return (int) $lang_map[ $aliased_key ];
			}
			if ( isset( $lang_map[ $aliased_slug ] ) ) {
				return (int) $lang_map[ $aliased_slug ];
			}
		}

		// Fallback to slug-only match strictly within the SAME language
		if ( isset( $lang_map[ $slug_only ] ) ) {
			return (int) $lang_map[ $slug_only ];
		}

		// 5. Unresolved: record and never cross languages
		$unresolved[] = array(
			'source_path_key' => $source_path_key,
			'source_lang'     => $source_lang,
			'raw_href'        => $raw_href,
			'target_key'      => $target_key,
		);

		return 0;
	}

	/**
	 * Build 2-tier link map from created pages list.
	 *
	 * @param array  $created_pages List of created page records.
	 * @param string $default_lang  Default language code.
	 * @return array 2-tier link map: [lang][key] => post_id.
	 */
	public static function build_link_map( array $created_pages, string $default_lang = 'en' ): array {
		$link_map = array();
		foreach ( $created_pages as $cp ) {
			if ( empty( $cp['post_id'] ) ) continue;
			$pid = (int) $cp['post_id'];
			$l   = get_post_meta( $pid, '_align_lang', true ) ?: $default_lang;
			if ( ! isset( $link_map[ $l ] ) ) $link_map[ $l ] = array();
			if ( ! empty( $cp['slug'] ) ) {
				$link_map[ $l ][ $cp['slug'] ] = $pid;
				$suffix = '-' . $l;
				if ( $l !== $default_lang && substr( $cp['slug'], -strlen( $suffix ) ) === $suffix ) {
					$base_slug = substr( $cp['slug'], 0, -strlen( $suffix ) );
					if ( '' !== $base_slug && ! isset( $link_map[ $l ][ $base_slug ] ) ) {
						$link_map[ $l ][ $base_slug ] = $pid;
					}
				}
			}
			if ( ! empty( $cp['path_key'] ) ) {
				$link_map[ $l ][ $cp['path_key'] ] = $pid;
			}
		}
		return $link_map;
	}

	/**
	 * Rewrite internal links across all created entities.
	 *
	 * @param array $created_pages List of created page records.
	 * @param array $link_map      2-tier link map.
	 * @param string $default_lang Default language.
	 */
	public static function rewrite_all_internal_links( array $created_pages, array $link_map, string $default_lang = 'en' ): void {
		foreach ( $created_pages as $created ) {
			$source_lang = $default_lang;
			if ( ! empty( $created['slug'] ) && preg_match( '/-(?:([a-z]{2,3}(?:-[a-z0-9]+)?))$/i', $created['slug'], $lm ) ) {
				$source_lang = strtolower( $lm[1] );
			} elseif ( ! empty( $created['post_id'] ) ) {
				$source_lang = get_post_meta( (int) $created['post_id'], '_align_lang', true ) ?: $default_lang;
			}

			if ( ! empty( $created['is_part'] ) ) {
				$part_file = ! empty( $created['file_path'] ) ? wp_normalize_path( $created['file_path'] ) : '';
				if ( ! empty( $part_file ) && file_exists( $part_file ) ) {
					$part_content = file_get_contents( $part_file );
					if ( false !== $part_content ) {
						$updated_part = Align_Zip_Media_Rewriter::rewrite_internal_links( $part_content, $link_map, '', $source_lang );
						if ( $updated_part !== $part_content ) {
							@file_put_contents( $part_file, $updated_part );
						}
					}
				}
				if ( ! empty( $created['slug'] ) ) {
					$tp_posts = get_posts( array( 'post_type' => 'wp_template_part', 'name' => $created['slug'], 'post_status' => 'any', 'numberposts' => 1 ) );
					if ( ! empty( $tp_posts ) ) {
						$up = Align_Zip_Media_Rewriter::rewrite_internal_links( $tp_posts[0]->post_content, $link_map, '', $source_lang );
						if ( $up !== $tp_posts[0]->post_content ) {
							kses_remove_filters();
							wp_update_post( array( 'ID' => $tp_posts[0]->ID, 'post_content' => wp_slash( $up ) ) );
							kses_init_filters();
						}
					}
				}
				continue;
			}

			if ( empty( $created['post_id'] ) || ! is_numeric( $created['post_id'] ) ) continue;
			$p = get_post( $created['post_id'] );
			if ( $p && ! empty( $p->post_content ) ) {
				$source_pk = $created['path_key'] ?? '';
				$updated   = Align_Zip_Media_Rewriter::rewrite_internal_links( $p->post_content, $link_map, $source_pk, $source_lang );
				if ( $updated !== $p->post_content ) {
					kses_remove_filters();
					wp_update_post( array( 'ID' => $p->ID, 'post_content' => wp_slash( $updated ) ) );
					kses_init_filters();
				}
			}
		}
	}
}
