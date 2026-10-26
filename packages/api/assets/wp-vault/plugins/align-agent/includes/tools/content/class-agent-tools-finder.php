<?php
/**
 * Align Agent - Content Finder & Aggregator Tools
 *
 * Provides count_content and find_content tools to discover and link
 * site content across post types before falling back to listing.
 *
 * @package Align_Agent
 * @subpackage Includes\Tools\Content
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_Finder {

	/**
	 * Validate a language code strictly against configured site languages in Align_Language_Manager.
	 * No hardcoded alias mapping is used; LLM must call list_languages first to obtain exact codes.
	 *
	 * @param string $lang_code Raw language code passed by LLM.
	 * @return array|null Null if valid (or empty), or an error response array if invalid/unconfigured.
	 */
	public static function validate_site_lang_code( string $lang_code ): ?array {
		$code = sanitize_key( trim( $lang_code ) );
		if ( '' === $code ) {
			return null;
		}
		if ( ! class_exists( 'Align_Language_Manager' ) ) {
			return null;
		}
		if ( null !== Align_Language_Manager::get_language( $code ) ) {
			return null;
		}
		$def_lang = (string) Align_Language_Manager::get_default_lang();
		if ( $code === $def_lang ) {
			return null;
		}
		return array(
			'success'             => false,
			'error'               => sprintf(
				'Invalid or unconfigured language code "%s". You MUST call list_languages first to inspect available site languages and use the exact "code" field.',
				$lang_code
			),
			'available_languages' => Align_Language_Manager::get_languages(),
		);
	}

	/**
	 * Build WP_Query meta_query clause for a target language code.
	 *
	 * @param string $target_lang Target language code.
	 * @return array
	 */
	public static function build_lang_meta_query( string $target_lang ): array {
		$def_lang = class_exists( 'Align_Language_Manager' ) ? (string) Align_Language_Manager::get_default_lang() : 'en';
		if ( $target_lang === $def_lang ) {
			return array(
				'relation' => 'OR',
				array(
					'key'   => '_align_lang',
					'value' => $def_lang,
				),
				array(
					'key'     => '_align_lang',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'   => '_align_lang',
					'value' => '',
				),
			);
		}
		return array(
			array(
				'key'   => '_align_lang',
				'value' => $target_lang,
			),
		);
	}

	/**
	 * Register content discovery tools.
	 */
	public static function init(): void {
		Align_Agent_Tool_Registry::register( 'count_content', array(
			'type'        => 'read',
			'group'       => 'content',
			'capability'  => 'edit_posts',
			'description' => 'Get sitewide content counts broken down by post type, status (publish, draft, private), and language (by_language). Use before listing all items.',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'lang' => array(
						'type'        => 'string',
						'description' => 'Exact language code from list_languages (e.g., call list_languages first to resolve the user\'s requested language into its exact code, then pass that code here).',
					),
				),
			),
			'callback'    => array( __CLASS__, 'tool_count_content' ),
		) );

		Align_Agent_Tool_Registry::register( 'find_content', array(
			'type'        => 'read',
			'group'       => 'content',
			'capability'  => 'edit_posts',
			'description' => 'Find posts, pages, or custom post types by ID, slug, title keyword, content keyword, or exact language code. Returns links, language, and metadata.',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'query'     => array(
						'type'        => 'string',
						'description' => 'Optional search keyword, title, slug, or numeric post ID. Can be omitted when filtering by lang.',
					),
					'lang'      => array(
						'type'        => 'string',
						'description' => 'Exact language code from list_languages (e.g., call list_languages first to resolve the user\'s requested language into its exact code, then pass that code here).',
					),
					'post_type' => array(
						'type'        => 'string',
						'description' => 'Post type slug or "any" (default: any).',
					),
					'status'    => array(
						'type'        => 'string',
						'description' => 'Comma-separated statuses (default: publish,draft,private).',
					),
					'limit'     => array(
						'type'        => 'integer',
						'description' => 'Maximum results (1..20, default: 10).',
					),
				),
			),
			'callback'    => array( __CLASS__, 'tool_find_content' ),
		) );
	}

	/**
	 * Callback for count_content tool.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 */
	public static function tool_count_content( array $args = array() ): array {
		$raw_lang = isset( $args['lang'] ) ? trim( (string) $args['lang'] ) : '';
		if ( '' !== $raw_lang ) {
			$lang_err = self::validate_site_lang_code( $raw_lang );
			if ( null !== $lang_err ) {
				return $lang_err;
			}
		}
		$lang_filter = sanitize_key( $raw_lang );

		$types_objects = get_post_types( array( 'show_ui' => true ), 'objects' );
		$by_type       = array();
		$grand_total   = 0;
		$valid_slugs   = array();
		$def_lang      = class_exists( 'Align_Language_Manager' ) ? (string) Align_Language_Manager::get_default_lang() : 'en';

		foreach ( $types_objects as $slug => $obj ) {
			if ( 'attachment' === $slug || 'nav_menu_item' === $slug || 'revision' === $slug || str_starts_with( $slug, 'wp_' ) ) {
				continue;
			}
			$valid_slugs[] = $slug;

			$counts  = wp_count_posts( $slug );
			$publish = isset( $counts->publish ) ? (int) $counts->publish : 0;
			$draft   = isset( $counts->draft ) ? (int) $counts->draft : 0;
			$private = isset( $counts->private ) ? (int) $counts->private : 0;
			$subtot  = $publish + $draft + $private;

			$grand_total += $subtot;
			$by_type[]    = array(
				'slug'    => $slug,
				'label'   => $obj->label ?: ucfirst( $slug ),
				'publish' => $publish,
				'draft'   => $draft,
				'private' => $private,
				'total'   => $subtot,
			);
		}

		$by_language = array();
		if ( class_exists( 'Align_Language_Manager' ) ) {
			foreach ( Align_Language_Manager::get_languages() as $k => $v ) {
				$code = is_array( $v ) && ! empty( $v['code'] ) ? (string) $v['code'] : (string) $k;
				$by_language[ $code ] = array( 'page' => 0, 'post' => 0, 'total' => 0 );
			}
		}
		if ( ! isset( $by_language[ $def_lang ] ) ) {
			$by_language[ $def_lang ] = array( 'page' => 0, 'post' => 0, 'total' => 0 );
		}

		if ( ! empty( $valid_slugs ) ) {
			$all_posts = get_posts( array(
				'post_type'      => $valid_slugs,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 500,
				'fields'         => 'ids',
			) );
			foreach ( $all_posts as $pid ) {
				$ptype = (string) get_post_type( $pid );
				$plang = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_post_lang( (int) $pid ) : (string) get_post_meta( (int) $pid, '_align_lang', true );
				$plang = '' !== $plang ? $plang : $def_lang;
				if ( '' !== $lang_filter && $plang !== $lang_filter ) {
					continue;
				}
				if ( ! isset( $by_language[ $plang ] ) ) {
					$by_language[ $plang ] = array( 'page' => 0, 'post' => 0, 'total' => 0 );
				}
				if ( ! isset( $by_language[ $plang ][ $ptype ] ) ) {
					$by_language[ $plang ][ $ptype ] = 0;
				}
				$by_language[ $plang ][ $ptype ]++;
				$by_language[ $plang ]['total']++;
			}
		}

		return array(
			'success'           => true,
			'site_default_lang' => $def_lang,
			'total'             => $grand_total,
			'types_count'       => count( $by_type ),
			'by_type'           => $by_type,
			'by_language'       => $by_language,
		);
	}

	/**
	 * Callback for find_content tool.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 */
	public static function tool_find_content( array $args = array() ): array {
		$raw_query = trim( (string) ( $args['query'] ?? '' ) );
		$raw_lang  = trim( (string) ( $args['lang'] ?? '' ) );

		if ( '' !== $raw_lang ) {
			$lang_err = self::validate_site_lang_code( $raw_lang );
			if ( null !== $lang_err ) {
				return $lang_err;
			}
		}
		$lang_filter = sanitize_key( $raw_lang );

		if ( '' === $raw_query && '' === $lang_filter && empty( $args['post_type'] ) && empty( $args['status'] ) ) {
			return array(
				'success'       => false,
				'error'         => 'missing_query',
				'message'       => 'Search query or lang filter cannot both be empty.',
				'items'         => array(),
				'total_matches' => 0,
			);
		}

		$limit     = min( 20, max( 1, absint( $args['limit'] ?? 10 ) ) );
		$post_type = ! empty( $args['post_type'] ) ? sanitize_key( (string) $args['post_type'] ) : 'any';

		if ( 'any' === $post_type ) {
			$ui_types  = get_post_types( array( 'show_ui' => true ) );
			$post_type = array_values( array_filter( $ui_types, function( $t ) {
				return 'attachment' !== $t && 'nav_menu_item' !== $t && 'revision' !== $t && ! str_starts_with( $t, 'wp_' );
			} ) );
		}

		$status_arg = ! empty( $args['status'] ) ? (string) $args['status'] : 'publish,draft,private';
		$statuses   = array_filter( array_map( 'trim', explode( ',', $status_arg ) ) );
		if ( empty( $statuses ) ) {
			$statuses = array( 'publish', 'draft', 'private' );
		}

		$def_lang      = class_exists( 'Align_Language_Manager' ) ? (string) Align_Language_Manager::get_default_lang() : 'en';
		$matched_posts = array();
		$total_found   = 0;

		$matches_lang = function( int $post_id ) use ( $lang_filter, $def_lang ): bool {
			if ( '' === $lang_filter ) {
				return true;
			}
			$p_lang = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_post_lang( $post_id ) : (string) get_post_meta( $post_id, '_align_lang', true );
			$p_lang = '' !== $p_lang ? $p_lang : $def_lang;
			return $p_lang === $lang_filter;
		};

		if ( '' === $raw_query ) {
			// Language/type/status browse mode without keyword query.
			$q_args = array(
				'post_type'      => $post_type,
				'post_status'    => $statuses,
				'posts_per_page' => $limit,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => false,
			);
			if ( '' !== $lang_filter ) {
				$q_args['meta_query'] = self::build_lang_meta_query( $lang_filter );
			}
			$browse_q    = new WP_Query( $q_args );
			$total_found = (int) $browse_q->found_posts;
			foreach ( $browse_q->posts as $p ) {
				if ( $matches_lang( (int) $p->ID ) ) {
					$matched_posts[ $p->ID ] = $p;
				}
			}
		} else {
			// Tier 1: Numeric ID exact match.
			if ( is_numeric( $raw_query ) ) {
				$id   = absint( $raw_query );
				$post = get_post( $id );
				if ( $post instanceof WP_Post && in_array( $post->post_status, $statuses, true ) && $matches_lang( (int) $post->ID ) ) {
					if ( is_array( $post_type ) ? in_array( $post->post_type, $post_type, true ) : $post->post_type === $post_type ) {
						$matched_posts[ $post->ID ] = $post;
					}
				}
			}

			// Tier 2: Exact post_name (slug) match.
			if ( count( $matched_posts ) < $limit ) {
				$slug_args = array(
					'name'           => sanitize_title( $raw_query ),
					'post_type'      => $post_type,
					'post_status'    => $statuses,
					'posts_per_page' => $limit,
					'no_found_rows'  => false,
				);
				if ( '' !== $lang_filter ) {
					$slug_args['meta_query'] = self::build_lang_meta_query( $lang_filter );
				}
				$slug_query = new WP_Query( $slug_args );
				foreach ( $slug_query->posts as $p ) {
					if ( ! isset( $matched_posts[ $p->ID ] ) && $matches_lang( (int) $p->ID ) ) {
						$matched_posts[ $p->ID ] = $p;
					}
				}
			}

			// Tier 3: Title or Slug LIKE search.
			if ( count( $matched_posts ) < $limit ) {
				global $wpdb;
				$like      = '%' . $wpdb->esc_like( $raw_query ) . '%';
				$slug_like = '%' . $wpdb->esc_like( sanitize_title( $raw_query ) ?: $raw_query ) . '%';
				$types_in  = is_array( $post_type ) ? "'" . implode( "','", array_map( 'esc_sql', $post_type ) ) . "'" : "'" . esc_sql( $post_type ) . "'";
				$status_in = "'" . implode( "','", array_map( 'esc_sql', $statuses ) ) . "'";
				$exclude   = ! empty( $matched_posts ) ? 'AND ID NOT IN (' . implode( ',', array_map( 'intval', array_keys( $matched_posts ) ) ) . ')' : '';

				$sql = "SELECT ID FROM {$wpdb->posts} WHERE (post_title LIKE %s OR post_name LIKE %s) AND post_type IN ({$types_in}) AND post_status IN ({$status_in}) {$exclude} ORDER BY post_modified_gmt DESC LIMIT %d";
				$ids = $wpdb->get_col( $wpdb->prepare( $sql, $like, $slug_like, 50 ) );

				foreach ( $ids as $id ) {
					if ( count( $matched_posts ) >= $limit ) {
						break;
					}
					$p = get_post( (int) $id );
					if ( $p instanceof WP_Post && ! isset( $matched_posts[ $p->ID ] ) && $matches_lang( (int) $p->ID ) ) {
						$matched_posts[ $p->ID ] = $p;
					}
				}
			}

			// Tier 4: Full-text content search (`s`).
			$total_found = count( $matched_posts );
			if ( count( $matched_posts ) < $limit ) {
				$s_args = array(
					's'              => $raw_query,
					'post_type'      => $post_type,
					'post_status'    => $statuses,
					'posts_per_page' => $limit - count( $matched_posts ),
					'post__not_in'   => array_keys( $matched_posts ),
					'no_found_rows'  => false,
				);
				if ( '' !== $lang_filter ) {
					$s_args['meta_query'] = self::build_lang_meta_query( $lang_filter );
				}
				$s_query      = new WP_Query( $s_args );
				$total_found += (int) $s_query->found_posts;
				foreach ( $s_query->posts as $p ) {
					if ( ! isset( $matched_posts[ $p->ID ] ) && $matches_lang( (int) $p->ID ) ) {
						$matched_posts[ $p->ID ] = $p;
					}
				}
			}
		}

		$items = array();
		foreach ( array_slice( $matched_posts, 0, $limit ) as $p ) {
			$pto     = get_post_type_object( $p->post_type );
			$raw_url = 'publish' === $p->post_status ? (string) get_permalink( $p ) : (string) get_preview_post_link( $p );
			$url     = class_exists( 'Align_Agent_Tools_Media' ) ? Align_Agent_Tools_Media::to_root_relative_url( $raw_url ) : $raw_url;
			$p_lang  = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_post_lang( (int) $p->ID ) : (string) get_post_meta( (int) $p->ID, '_align_lang', true );

			$items[] = array(
				'id'         => (int) $p->ID,
				'title'      => (string) $p->post_title,
				'slug'       => (string) $p->post_name,
				'post_type'  => (string) $p->post_type,
				'type_label' => $pto ? (string) $pto->labels->singular_name : (string) $p->post_type,
				'status'     => (string) $p->post_status,
				'lang'       => '' !== $p_lang ? $p_lang : $def_lang,
				'url'        => (string) $url,
				'modified'   => (string) $p->post_modified,
			);
		}

		return array(
			'success'           => true,
			'site_default_lang' => $def_lang,
			'query'             => $raw_query,
			'lang_filter'       => $lang_filter,
			'limit'             => $limit,
			'returned'          => count( $items ),
			'total_matches'     => max( count( $items ), $total_found ),
			'items'             => $items,
		);
	}
}
