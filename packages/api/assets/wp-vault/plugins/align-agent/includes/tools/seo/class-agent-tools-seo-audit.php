<?php
/**
 * Align Agent SEO Audit Tools
 *
 * Scans published and draft posts/pages for SEO meta coverage (title, description, focus keyword)
 * based on the active SEO adapter (Rank Math, Yoast, or Align theme default).
 *
 * @package Align_Agent
 * @subpackage Includes\Tools\Seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_Seo_Audit {

	/**
	 * Register SEO audit tools into Align_Agent_Tool_Registry.
	 *
	 * @return void
	 */
	public static function init(): void {
		Align_Agent_Tool_Registry::register( 'audit_seo_coverage', array(
			'type'             => 'read',
			'group'            => 'seo',
			'scope'            => 'local',
			'capability'       => 'edit_posts',
			'requires_builder' => false,
			'danger'           => false,
			'description'      => 'Audit SEO meta coverage (title, description, focus keyword, score) across published and draft pages/posts. Read-only. To fix, call the active save_seo_* tool per item. Process at most 10 items per turn.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'post_types' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'List of post types to audit. Default: ["page", "post"].',
					),
					'lang'       => array(
						'type'        => 'string',
						'description' => 'Language code filter or "all". Default: "all".',
					),
					'scope'      => array(
						'type'        => 'string',
						'enum'        => array( 'missing', 'needs_optimization', 'all' ),
						'description' => 'Audit scope filter. missing: missing title or description; needs_optimization: missing or low score; all: all scanned items. Default: missing.',
					),
					'limit'      => array(
						'type'        => 'integer',
						'description' => 'Maximum number of items to scan (1..200). Default: 50.',
					),
				),
			),
			'callback'         => array( __CLASS__, 'tool_audit_seo_coverage' ),
		) );
	}

	/**
	 * Callback for audit_seo_coverage.
	 */
	public static function tool_audit_seo_coverage( array $args = array() ): array {
		if ( ! class_exists( 'Align_Agent_Seo_Adapters' ) ) {
			return array(
				'success' => false,
				'error'   => 'seo_adapters_unavailable',
				'message' => 'SEO adapters class is unavailable.',
			);
		}

		$provider = Align_Agent_Seo_Adapters::get_provider();
		if ( 'others' === $provider ) {
			return array(
				'success'  => false,
				'provider' => 'others',
				'error'    => 'provider_unsupported',
				'message'  => 'No supported SEO plugin active (Rank Math, Yoast, or Align SEO).',
			);
		}

		$meta_keys = self::get_meta_keys_for_provider( $provider );
		$types     = ! empty( $args['post_types'] ) && is_array( $args['post_types'] ) ? $args['post_types'] : array( 'page', 'post' );
		$lang      = isset( $args['lang'] ) ? sanitize_key( (string) $args['lang'] ) : 'all';
		$scope     = isset( $args['scope'] ) ? sanitize_key( (string) $args['scope'] ) : 'missing';
		$limit     = isset( $args['limit'] ) ? min( 200, max( 1, (int) $args['limit'] ) ) : 50;

		$query_args = array(
			'post_type'      => $types,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => $limit,
			'orderby'        => 'ID',
			'order'          => 'DESC',
		);

		if ( 'all' !== $lang && ! empty( $lang ) ) {
			$query_args['meta_key']   = '_align_lang';
			$query_args['meta_value'] = $lang;
		}

		$posts = get_posts( $query_args );
		$items = array();

		$scanned             = count( $posts );
		$missing_title_cnt   = 0;
		$missing_desc_cnt    = 0;

		foreach ( $posts as $p ) {
			$title_val = trim( (string) get_post_meta( $p->ID, $meta_keys['title'], true ) );
			$desc_val  = trim( (string) get_post_meta( $p->ID, $meta_keys['description'], true ) );
			$kw_val    = ! empty( $meta_keys['keyword'] ) ? trim( (string) get_post_meta( $p->ID, $meta_keys['keyword'], true ) ) : '';
			$score_val = ! empty( $meta_keys['score'] ) ? (int) get_post_meta( $p->ID, $meta_keys['score'], true ) : null;

			$has_title = '' !== $title_val;
			$has_desc  = '' !== $desc_val;

			if ( ! $has_title ) {
				$missing_title_cnt++;
			}
			if ( ! $has_desc ) {
				$missing_desc_cnt++;
			}

			$is_missing = ( ! $has_title || ! $has_desc );
			$needs_opt  = $is_missing || ( null !== $score_val && $score_val < 80 );

			if ( 'missing' === $scope && ! $is_missing ) {
				continue;
			}
			if ( 'needs_optimization' === $scope && ! $needs_opt ) {
				continue;
			}

			$post_lang = '';
			if ( class_exists( 'Align_Language_Manager' ) ) {
				$post_lang = Align_Language_Manager::get_post_lang( $p->ID ) ?: Align_Language_Manager::get_default_lang();
			}

			$items[] = array(
				'post_id'         => $p->ID,
				'title'           => $p->post_title,
				'url'             => get_permalink( $p->ID ),
				'lang'            => $post_lang,
				'status'          => $p->post_status,
				'has_title'       => $has_title,
				'has_description' => $has_desc,
				'keyword'         => $kw_val,
				'score'           => $score_val,
			);
		}

		return array(
			'success'  => true,
			'provider' => $provider,
			'items'    => $items,
			'stats'    => array(
				'scanned'             => $scanned,
				'matched'             => count( $items ),
				'missing_title'       => $missing_title_cnt,
				'missing_description' => $missing_desc_cnt,
			),
			'message'  => sprintf( 'Scanned %d posts with SEO provider "%s". Matched %d items in scope "%s".', $scanned, $provider, count( $items ), $scope ),
		);
	}

	/**
	 * Get meta key mapping for the active SEO provider.
	 */
	private static function get_meta_keys_for_provider( string $provider ): array {
		switch ( $provider ) {
			case 'rank_math':
				return array(
					'title'       => 'rank_math_title',
					'description' => 'rank_math_description',
					'keyword'     => 'rank_math_focus_keyword',
					'score'       => 'rank_math_seo_score',
				);
			case 'yoast':
				return array(
					'title'       => '_yoast_wpseo_title',
					'description' => '_yoast_wpseo_metadesc',
					'keyword'     => '_yoast_wpseo_focuskw',
					'score'       => null,
				);
			case 'theme_default':
			default:
				return array(
					'title'       => '_align_seo_title',
					'description' => '_align_seo_description',
					'keyword'     => '_align_seo_keywords',
					'score'       => null,
				);
		}
	}
}
