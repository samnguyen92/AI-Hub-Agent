<?php
/**
 * Align Agent - SEO Tools & <head> Verification Engine
 *
 * Registers 'check_seo_output' (read) and dynamically registers ONLY the single
 * SEO write tool matching the 'align_agent_seo_provider' setting:
 * - rank_math     -> save_seo_rank_math
 * - yoast         -> save_seo_yoast
 * - theme_default -> save_seo_theme_default
 * - others        -> no SEO write tool registered
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_Seo {

	/**
	 * Register SEO read/write tools according to the configured SEO provider.
	 */
	public static function init(): void {
		self::register_read_tools();
		self::register_provider_write_tool();
	}

	/**
	 * Register the read-only check_seo_output tool.
	 */
	private static function register_read_tools(): void {
		Align_Agent_Tool_Registry::register(
			'check_seo_output',
			array(
				'type'        => 'read',
				'group'       => 'seo',
				'capability'  => 'edit_posts',
				'description' => 'Fetch a published post or page URL via wp_remote_get and parse <head> with DOMDocument to inspect present, missing, and duplicate SEO/OpenGraph/Twitter/canonical/hreflang tags. Returns status="unreachable" if the post is draft or password-protected.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'Numeric WordPress post/page ID to inspect.',
						),
					),
					'required'   => array( 'post_id' ),
				),
				'callback'    => array( __CLASS__, 'tool_check_seo_output' ),
			)
		);
	}

	/**
	 * Register ONLY the write tool matching Align_Agent_Seo_Adapters::get_provider().
	 */
	private static function register_provider_write_tool(): void {
		$provider = Align_Agent_Seo_Adapters::get_provider();

		if ( 'others' === $provider ) {
			return;
		}

		$common_props = array(
			'post_id'        => array( 'type' => 'integer', 'description' => 'Numeric WordPress page/post ID.' ),
			'title'          => array( 'type' => 'string', 'description' => 'SEO title (max 70 characters recommended).' ),
			'description'    => array( 'type' => 'string', 'description' => 'Meta description (max 160 characters recommended).' ),
			'focus_keyword'  => array( 'type' => 'string', 'description' => 'Primary focus keyword or comma-separated keywords.' ),
			'og_title'       => array( 'type' => 'string', 'description' => 'OpenGraph / Twitter social title.' ),
			'og_description' => array( 'type' => 'string', 'description' => 'OpenGraph / Twitter social description.' ),
			'og_image_id'    => array( 'type' => 'integer', 'description' => 'WordPress Media Library attachment ID for og:image / twitter:image.' ),
			'noindex'        => array( 'type' => 'boolean', 'description' => 'Set true to mark page as noindex.' ),
		);

		if ( 'rank_math' === $provider ) {
			$props              = $common_props;
			$props['canonical'] = array( 'type' => 'string', 'description' => 'Optional custom canonical URL.' );
			Align_Agent_Tool_Registry::register(
				'save_seo_rank_math',
				array(
					'type'             => 'write',
					'undo'             => array( 'adapter' => 'meta', 'id_arg' => 'post_id', 'meta_keys' => array( 'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword', 'rank_math_canonical_url', 'rank_math_robots' ) ),
					'group'            => 'seo',
					'capability'       => 'edit_posts',
					'description'      => 'Save SEO and OpenGraph metadata to Rank Math SEO post meta for a page/post/CPT, then run check_seo_output automatically. Requires user approval.',
					'parameters'       => array(
						'type'       => 'object',
						'properties' => $props,
						'required'   => array( 'post_id' ),
					),
					'callback'         => array( __CLASS__, 'tool_save_seo_rank_math' ),
					'summary_callback' => array( __CLASS__, 'summarize_seo_write' ),
				)
			);
		} elseif ( 'yoast' === $provider ) {
			$props              = $common_props;
			$props['canonical'] = array( 'type' => 'string', 'description' => 'Optional custom canonical URL.' );
			Align_Agent_Tool_Registry::register(
				'save_seo_yoast',
				array(
					'type'             => 'write',
					'undo'             => array( 'adapter' => 'meta', 'id_arg' => 'post_id', 'meta_keys' => array( '_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_focuskw', '_yoast_wpseo_canonical', '_yoast_wpseo_meta-robots-noindex' ) ),
					'group'            => 'seo',
					'capability'       => 'edit_posts',
					'description'      => 'Save SEO and OpenGraph metadata to Yoast SEO post meta for a page/post/CPT, then run check_seo_output automatically. Requires user approval.',
					'parameters'       => array(
						'type'       => 'object',
						'properties' => $props,
						'required'   => array( 'post_id' ),
					),
					'callback'         => array( __CLASS__, 'tool_save_seo_yoast' ),
					'summary_callback' => array( __CLASS__, 'summarize_seo_write' ),
				)
			);
		} elseif ( 'theme_default' === $provider ) {
			Align_Agent_Tool_Registry::register(
				'save_seo_theme_default',
				array(
					'type'             => 'write',
					'undo'             => array( 'adapter' => 'meta', 'id_arg' => 'post_id', 'meta_keys' => array( '_align_seo_title', '_align_seo_description', '_align_seo_focus_keyword', '_align_seo_og_title', '_align_seo_og_description', '_align_seo_og_image_id', '_align_seo_noindex' ) ),
					'group'            => 'seo',
					'capability'       => 'edit_posts',
					'description'      => 'Save SEO and OpenGraph metadata to Align Theme Default SEO (_align_seo_*) for a page/post/CPT, then run check_seo_output automatically. Requires user approval.',
					'parameters'       => array(
						'type'       => 'object',
						'properties' => $common_props,
						'required'   => array( 'post_id' ),
					),
					'callback'         => array( __CLASS__, 'tool_save_seo_theme_default' ),
					'summary_callback' => array( __CLASS__, 'summarize_seo_write' ),
				)
			);
		}
	}

	/**
	 * Build human-readable approval card summary for any SEO write tool.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 */
	public static function summarize_seo_write( array $args ): array {
		$post_id  = absint( $args['post_id'] ?? 0 );
		$post     = $post_id > 0 ? get_post( $post_id ) : null;
		$p_title  = $post ? $post->post_title : '#' . $post_id;
		$provider = Align_Agent_Seo_Adapters::get_provider();
		$label    = Align_Agent_Seo_Adapters::get_provider_label( $provider );

		$parts = array();
		if ( ! empty( $args['title'] ) ) {
			$parts[] = 'Title: "' . sanitize_text_field( (string) $args['title'] ) . '"';
		}
		if ( ! empty( $args['description'] ) ) {
			$parts[] = 'Description: "' . sanitize_text_field( (string) $args['description'] ) . '"';
		}
		if ( ! empty( $args['focus_keyword'] ) ) {
			$parts[] = 'Keyword: "' . sanitize_text_field( (string) $args['focus_keyword'] ) . '"';
		}
		if ( ! empty( $args['og_image_id'] ) ) {
			$parts[] = 'OG Image ID: #' . absint( $args['og_image_id'] );
		}

		$desc = sprintf(
			/* translators: 1: SEO provider label, 2: Summary of fields */
			__( 'Save SEO metadata via %1$s (%2$s).', 'align-agent' ),
			$label,
			! empty( $parts ) ? implode( ' | ', $parts ) : __( 'update fields', 'align-agent' )
		);

		return array(
			'title'        => sprintf( __( 'Save SEO for Post #%1$d (%2$s)', 'align-agent' ), $post_id, $p_title ),
			'description'  => $desc,
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => array(
				'post_id'  => $post_id,
				'provider' => $provider,
			),
		);
	}

	/**
	 * Execute save_seo_rank_math and append automatic check_seo_output report.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error
	 */
	public static function tool_save_seo_rank_math( array $args = array() ) {
		$post_id = absint( $args['post_id'] ?? 0 );
		$res     = Align_Agent_Seo_Adapters::save_rank_math( $post_id, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return self::format_seo_write_response( $post_id, 'Rank Math SEO', $res );
	}

	/**
	 * Execute save_seo_yoast and append automatic check_seo_output report.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error
	 */
	public static function tool_save_seo_yoast( array $args = array() ) {
		$post_id = absint( $args['post_id'] ?? 0 );
		$res     = Align_Agent_Seo_Adapters::save_yoast( $post_id, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return self::format_seo_write_response( $post_id, 'Yoast SEO', $res );
	}

	/**
	 * Execute save_seo_theme_default and append automatic check_seo_output report.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error
	 */
	public static function tool_save_seo_theme_default( array $args = array() ) {
		$post_id = absint( $args['post_id'] ?? 0 );
		$res     = Align_Agent_Seo_Adapters::save_theme_default( $post_id, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return self::format_seo_write_response( $post_id, 'Align Theme Default SEO', $res );
	}

	/**
	 * Format the final write tool output, running check_seo_output automatically (non-blocking).
	 *
	 * @param int    $post_id        Post ID.
	 * @param string $provider_label Human-readable provider label.
	 * @param array  $adapter_res    Adapter result array.
	 * @return array
	 */
	private static function format_seo_write_response( int $post_id, string $provider_label, array $adapter_res ): array {
		$check_report = self::tool_check_seo_output( array( 'post_id' => $post_id ) );
		$url          = (string) get_permalink( $post_id );
		$warnings     = ! empty( $adapter_res['warnings'] ) && is_array( $adapter_res['warnings'] ) ? $adapter_res['warnings'] : array();
		$saved_keys   = ! empty( $adapter_res['saved'] ) && is_array( $adapter_res['saved'] ) ? array_keys( $adapter_res['saved'] ) : array();

		$msg = sprintf(
			/* translators: 1: Post ID, 2: Provider name */
			__( 'Saved SEO metadata for page #%1$d via %2$s. If your site uses a page cache or CDN, clear the cache to see updated tags immediately.', 'align-agent' ),
			$post_id,
			$provider_label
		);

		if ( ! empty( $warnings ) ) {
			$msg .= ' Warning: ' . implode( ' ', $warnings );
		}

		return array(
			'success'          => true,
			'post_id'          => $post_id,
			'provider'         => $adapter_res['provider'] ?? '',
			'saved'            => $adapter_res['saved'] ?? array(),
			'warnings'         => $warnings,
			'seo_check_output' => $check_report,
			'url'              => $url,
			'message'          => $msg,
			'ui_card'          => array(
				'title'                => sprintf( __( 'Saved SEO Metadata (#%d)', 'align-agent' ), $post_id ),
				'message'              => sprintf(
					'%s | Fields: %s',
					$provider_label,
					! empty( $saved_keys ) ? implode( ', ', $saved_keys ) : 'none'
				),
				'url'                  => $url,
				'post_id'              => $post_id,
				'rollback_revision_id' => 0,
			),
		);
	}

	/**
	 * Execute check_seo_output: inspect <head> of a published, non-password-protected page.
	 *
	 * @param array $args Tool arguments containing post_id.
	 * @return array
	 */
	public static function tool_check_seo_output( array $args = array() ): array {
		$post_id = absint( $args['post_id'] ?? $args['id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'invalid_id',
				'message' => __( 'A valid numeric post_id is required.', 'align-agent' ),
			);
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return array(
				'success' => false,
				'error'   => 'post_not_found',
				'message' => sprintf( __( 'Post #%d was not found.', 'align-agent' ), $post_id ),
			);
		}

		if ( 'publish' !== $post->post_status || ! empty( $post->post_password ) ) {
			$reason = 'publish' !== $post->post_status
				? sprintf( 'Post status is "%s" (not published).', $post->post_status )
				: 'Post is password-protected.';

			return array(
				'success' => true,
				'post_id' => $post_id,
				'status'  => 'unreachable',
				'reason'  => $reason,
				'message' => sprintf( 'Cannot inspect <head> via HTTP because the page is unreachable to anonymous crawlers (%s).', $reason ),
			);
		}

		$url = (string) get_permalink( $post_id );
		if ( '' === $url ) {
			return array(
				'success' => true,
				'post_id' => $post_id,
				'status'  => 'unreachable',
				'reason'  => 'Empty permalink.',
			);
		}

		$fetch_url = add_query_arg( 'align_seo_check', time(), $url );
		$response  = wp_remote_get(
			$fetch_url,
			array(
				'timeout'   => 15,
				'sslverify' => false,
				'headers'   => array( 'Cache-Control' => 'no-cache' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => true,
				'post_id' => $post_id,
				'url'     => $url,
				'status'  => 'unreachable',
				'reason'  => $response->get_error_message(),
			);
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		$html      = (string) wp_remote_retrieve_body( $response );
		if ( $http_code < 200 || $http_code >= 400 || '' === trim( $html ) ) {
			return array(
				'success'   => true,
				'post_id'   => $post_id,
				'url'       => $url,
				'status'    => 'unreachable',
				'http_code' => $http_code,
				'reason'    => sprintf( 'HTTP %d returned when fetching page.', $http_code ),
			);
		}

		return self::parse_head_html( $post_id, $url, $html );
	}

	/**
	 * Parse <head> HTML using DOMDocument and report present, missing, and duplicate SEO tags.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $url     Page permalink.
	 * @param string $html    Full HTML document body.
	 * @return array
	 */
	public static function parse_head_html( int $post_id, string $url, string $html ): array {
		$head_html = $html;
		if ( preg_match( '/<head\b[^>]*>(.*?)<\/head>/is', $html, $matches ) ) {
			$head_html = '<head>' . $matches[1] . '</head>';
		}

		$dom      = new DOMDocument();
		$prev_err = libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $head_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev_err );

		$counts = array();
		$values = array();

		$record_tag = static function ( string $key, string $val ) use ( &$counts, &$values ): void {
			$counts[ $key ]   = ( $counts[ $key ] ?? 0 ) + 1;
			$values[ $key ][] = $val;
		};

		$titles = $dom->getElementsByTagName( 'title' );
		foreach ( $titles as $title_node ) {
			$record_tag( 'title', trim( (string) $title_node->textContent ) );
		}

		$metas = $dom->getElementsByTagName( 'meta' );
		foreach ( $metas as $meta ) {
			if ( ! ( $meta instanceof DOMElement ) ) {
				continue;
			}
			$name     = strtolower( trim( (string) $meta->getAttribute( 'name' ) ) );
			$property = strtolower( trim( (string) $meta->getAttribute( 'property' ) ) );
			$content  = trim( (string) $meta->getAttribute( 'content' ) );

			if ( 'description' === $name || 'robots' === $name || 0 === strpos( $name, 'twitter:' ) ) {
				$record_tag( $name, $content );
			}
			if ( 0 === strpos( $property, 'og:' ) ) {
				$record_tag( $property, $content );
			}
		}

		$links = $dom->getElementsByTagName( 'link' );
		foreach ( $links as $link ) {
			if ( ! ( $link instanceof DOMElement ) ) {
				continue;
			}
			$rel      = strtolower( trim( (string) $link->getAttribute( 'rel' ) ) );
			$href     = trim( (string) $link->getAttribute( 'href' ) );
			$hreflang = trim( (string) $link->getAttribute( 'hreflang' ) );

			if ( 'canonical' === $rel ) {
				$record_tag( 'canonical', $href );
			} elseif ( 'alternate' === $rel && '' !== $hreflang ) {
				$record_tag( 'hreflang:' . strtolower( $hreflang ), $href );
			}
		}

		$expected_keys = array(
			'title',
			'description',
			'canonical',
			'og:title',
			'og:description',
			'og:type',
			'og:url',
			'og:image',
			'og:locale',
			'twitter:card',
			'twitter:title',
			'twitter:description',
			'twitter:image',
		);

		$present    = array();
		$missing    = array();
		$duplicates = array();

		foreach ( $expected_keys as $k ) {
			if ( ! empty( $counts[ $k ] ) ) {
				$present[ $k ] = 1 === count( $values[ $k ] ) ? $values[ $k ][0] : $values[ $k ];
			} else {
				$missing[] = $k;
			}
		}

		foreach ( $counts as $k => $cnt ) {
			if ( 'og:locale:alternate' === $k ) {
				continue;
			}
			if ( 0 === strpos( $k, 'hreflang:' ) ) {
				$present[ $k ] = 1 === count( $values[ $k ] ) ? $values[ $k ][0] : $values[ $k ];
			}
			if ( $cnt > 1 ) {
				$duplicates[ $k ] = array(
					'count'  => $cnt,
					'values' => $values[ $k ],
				);
			}
		}

		return array(
			'success'    => true,
			'post_id'    => $post_id,
			'url'        => $url,
			'status'     => 'ok',
			'present'    => $present,
			'missing'    => $missing,
			'duplicates' => $duplicates,
		);
	}
}
