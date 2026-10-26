<?php
/**
 * Align Agent Multi-language URL Localizer Tools
 *
 * Scans and localizes internal link URLs within translated posts and pages.
 *
 * @package Align_Agent
 * @subpackage Includes\Tools\I18n
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_I18n_Urls {

	/**
	 * Register i18n URL tools into Align_Agent_Tool_Registry.
	 *
	 * @return void
	 */
	public static function init(): void {
		Align_Agent_Tool_Registry::register( 'scan_unlocalized_urls', array(
			'type'             => 'read',
			'group'            => 'i18n',
			'scope'            => 'local',
			'capability'       => 'edit_posts',
			'requires_builder' => false,
			'danger'           => false,
			'description'      => 'Scan translated posts/pages for internal URLs that still point to default language pages instead of translated counterparts.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Optional specific post ID to scan.',
					),
					'lang'    => array(
						'type'        => 'string',
						'description' => 'Language code to filter when post_id is omitted (must not be default language).',
					),
					'limit'   => array(
						'type'        => 'integer',
						'description' => 'Maximum posts to scan (1..50). Default: 20.',
					),
				),
			),
			'callback'         => array( __CLASS__, 'tool_scan_unlocalized_urls' ),
		) );

		Align_Agent_Tool_Registry::register( 'localize_post_urls', array(
			'type'             => 'write',
			'group'            => 'i18n',
			'scope'            => 'local',
			'capability'       => 'edit_posts',
			'requires_builder' => true,
			'danger'           => false,
			'undo'             => array( 'adapter' => 'post' ),
			'description'      => 'Automatically rewrite internal page links within a translated post to point to corresponding translated target pages.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'Target post ID (must be an active translation in non-default language).',
					),
				),
				'required'   => array( 'post_id' ),
			),
			'callback'         => array( __CLASS__, 'tool_localize_post_urls' ),
			'summary_callback' => array( __CLASS__, 'summarize_localize_post_urls' ),
		) );
	}

	/**
	 * Ensure Align_Bulk_Url_Scanner class is loaded.
	 *
	 * @return bool
	 */
	private static function ensure_url_scanner(): bool {
		if ( class_exists( 'Align_Bulk_Url_Scanner' ) ) {
			return true;
		}
		$file = get_template_directory() . '/core/includes/admin/class-align-bulk-url-scanner.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
		return class_exists( 'Align_Bulk_Url_Scanner' );
	}

	/**
	 * Callback for scan_unlocalized_urls.
	 */
	public static function tool_scan_unlocalized_urls( array $args = array() ): array {
		if ( ! class_exists( 'Align_Language_Manager' ) ) {
			return array(
				'success' => false,
				'error'   => 'theme_feature_unavailable',
				'message' => 'Align_Language_Manager is not available in current theme.',
			);
		}

		if ( ! self::ensure_url_scanner() ) {
			return array(
				'success' => false,
				'error'   => 'theme_feature_unavailable',
				'message' => 'Align_Bulk_Url_Scanner is not available in current theme.',
			);
		}

		$post_id      = absint( $args['post_id'] ?? 0 );
		$default_lang = Align_Language_Manager::get_default_lang();
		$posts_to_scan = array();

		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				return array(
					'success' => false,
					'error'   => 'invalid_post_id',
					'message' => 'Post does not exist.',
				);
			}
			$lang = Align_Language_Manager::get_post_lang( $post_id ) ?: $default_lang;
			$posts_to_scan[] = array( 'post' => $post, 'lang' => $lang );
		} else {
			$lang = sanitize_key( (string) ( $args['lang'] ?? '' ) );
			if ( empty( $lang ) || $lang === $default_lang ) {
				return array(
					'success' => false,
					'error'   => 'invalid_language',
					'message' => 'A non-default language code is required when post_id is not specified.',
				);
			}

			$limit = isset( $args['limit'] ) ? min( 50, max( 1, (int) $args['limit'] ) ) : 20;
			$found = get_posts( array(
				'post_type'      => array( 'page', 'post' ),
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => $limit,
				'meta_key'       => '_align_lang',
				'meta_value'     => $lang,
			) );

			foreach ( $found as $p ) {
				$posts_to_scan[] = array( 'post' => $p, 'lang' => $lang );
			}
		}

		$results = array();

		foreach ( $posts_to_scan as $item ) {
			$p    = $item['post'];
			$p_lg = $item['lang'];

			$parts = class_exists( 'Align_Gateway_Pages' )
				? Align_Gateway_Pages::extract_decoupled_parts( $p )
				: array( 'html' => $p->post_content );

			$html = (string) ( $parts['html'] ?? '' );
			if ( '' === trim( $html ) ) {
				continue;
			}

			$detected = Align_Bulk_Url_Scanner::detect_unlocalized_urls( $html, $p_lg );
			if ( ! empty( $detected ) ) {
				$results[] = array(
					'post_id' => $p->ID,
					'title'   => $p->post_title,
					'lang'    => $p_lg,
					'urls'    => $detected,
				);
			}
		}

		return array(
			'success' => true,
			'items'   => $results,
			'count'   => count( $results ),
			'message' => sprintf( 'Found %d post(s) with unlocalized internal URLs.', count( $results ) ),
		);
	}

	/**
	 * Callback for localize_post_urls.
	 */
	public static function tool_localize_post_urls( array $args ): array {
		if ( ! class_exists( 'Align_Language_Manager' ) || ! class_exists( 'Align_Language_Url_Localizer' ) ) {
			return array(
				'success' => false,
				'error'   => 'theme_feature_unavailable',
				'message' => 'Language manager or URL localizer not available in current theme.',
			);
		}

		$post_id = absint( $args['post_id'] ?? 0 );
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return array(
				'success' => false,
				'error'   => 'invalid_post_id',
				'message' => 'Post does not exist.',
			);
		}

		$lang         = Align_Language_Manager::get_post_lang( $post_id );
		$default_lang = Align_Language_Manager::get_default_lang();

		if ( empty( $lang ) || $lang === $default_lang ) {
			return array(
				'success' => false,
				'error'   => 'not_a_translation',
				'message' => sprintf( 'Post %d is in default language (%s) or not marked as translation.', $post_id, $default_lang ),
			);
		}

		if ( ! class_exists( 'Align_Gateway_Pages' ) || ! class_exists( 'Align_Builder_Crud_Handler' ) ) {
			return array(
				'success' => false,
				'error'   => 'builder_unavailable',
				'message' => 'Align Builder services are unavailable.',
			);
		}

		$parts    = Align_Gateway_Pages::extract_decoupled_parts( $post );
		$new_html = Align_Language_Url_Localizer::localize_html( $parts['html'], $lang );

		if ( $new_html === $parts['html'] ) {
			return array(
				'success' => true,
				'post_id' => $post_id,
				'changed' => false,
				'message' => 'All internal URLs are already localized.',
			);
		}

		if ( class_exists( 'Align_Agent_Tools_Pages' ) ) {
			$guarded = Align_Agent_Tools_Pages::guard_html_and_css( $new_html, $parts['css'] );
			if ( empty( $guarded['success'] ) ) {
				return $guarded;
			}
		}

		$save_req = new WP_REST_Request( 'POST', '/align-builder/v1/page/save' );
		$save_req->set_param( 'post_id', $post_id );
		$save_req->set_param( 'html', $new_html );
		$save_req->set_param( 'css', $parts['css'] );
		$save_req->set_param( 'js', $parts['js'] );

		$save_res = Align_Builder_Crud_Handler::save_page( $save_req );
		if ( is_wp_error( $save_res ) ) {
			return array(
				'success' => false,
				'error'   => $save_res->get_error_code(),
				'message' => $save_res->get_error_message(),
			);
		}

		$current_user_id = get_current_user_id();
		Align_Agent_Audit_Log::log_action(
			$current_user_id,
			'localize_post_urls',
			$post_id,
			'update',
			array(
				'lang'        => $lang,
				'post_title'  => $post->post_title,
				'post_status' => $post->post_status,
			)
		);

		return array(
			'success' => true,
			'post_id' => $post_id,
			'changed' => true,
			'message' => sprintf( 'Internal URLs in post %d localized for language "%s".', $post_id, $lang ),
			'ui_card' => array(
				'title'   => 'URLs Localized',
				'message' => sprintf( 'Post %d links updated for %s.', $post_id, $lang ),
			),
		);
	}

	/**
	 * Provide approval summary for localize_post_urls.
	 */
	public static function summarize_localize_post_urls( array $args ): array {
		$post_id = absint( $args['post_id'] ?? 0 );
		$post    = get_post( $post_id );
		$title   = $post ? $post->post_title : 'Post #' . $post_id;

		return array(
			'title'       => sprintf( 'Localize URLs in "%s"', $title ),
			'description' => sprintf( 'Rewrites internal links in post %d to match active translation slugs.', $post_id ),
			'meta'        => array(
				'post_id' => $post_id,
			),
		);
	}
}
