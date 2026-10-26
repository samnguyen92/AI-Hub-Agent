<?php
/**
 * Align Theme - Bulk URL Problem Scanner & Batch Localizer Engine
 *
 * Audits translated pages, posts, template parts, and reusable blocks to detect
 * internal links that still point to original source language URLs, and provides
 * a 1-click batch fix mechanism requiring ZERO AI tokens.
 *
 * Adheres strictly to Align coding standards & < 500 lines constraint.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Bulk_Url_Scanner {

	/**
	 * Initialize AJAX hooks.
	 */
	public static function init() {
		add_action( 'wp_ajax_align_bulk_scan_url_problems', array( __CLASS__, 'ajax_scan_url_problems' ) );
		add_action( 'wp_ajax_align_bulk_fix_url_problems',  array( __CLASS__, 'ajax_fix_url_problems' ) );
	}

	/**
	 * AJAX: Scan all translated items for unlocalized internal URLs.
	 */
	public static function ajax_scan_url_problems() {
		check_ajax_referer( 'align_settings_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized access.' ), 403 );
		}

		$target_lang = isset( $_POST['target_lang'] ) ? sanitize_key( $_POST['target_lang'] ) : '';
		if ( empty( $target_lang ) ) {
			wp_send_json_error( array( 'message' => 'Target language is required.' ), 400 );
		}

		$default_lang = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en';
		if ( $target_lang === $default_lang ) {
			wp_send_json_error( array( 'message' => 'Target language cannot be default language.' ), 400 );
		}

		error_log( "[Align BulkUrlScanner] Starting URL problems audit for language: '{$target_lang}'" );

		$scanned_count     = 0;
		$total_issues      = 0;
		$items_with_issues = array();

		// 1. Scan Posts, Pages, and Reusable Blocks with _align_lang = $target_lang
		$posts = get_posts( array(
			'post_type'      => array( 'page', 'post', 'wp_block' ),
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => -1,
			'meta_key'       => '_align_lang',
			'meta_value'     => $target_lang,
		) );

		foreach ( $posts as $p ) {
			$scanned_count++;
			$raw_html = $p->post_content;
			$issues   = self::detect_unlocalized_urls( $raw_html, $target_lang );

			if ( ! empty( $issues ) ) {
				$count = count( $issues );
				$total_issues += $count;
				$items_with_issues[] = array(
					'id'            => $p->ID,
					'item_type'     => $p->post_type,
					'title'         => $p->post_title ?: __( '(Untitled)', 'align' ),
					'issues_count'  => $count,
					'issues_sample' => array_slice( $issues, 0, 5 ),
					'edit_url'      => get_edit_post_link( $p->ID, 'raw' ),
				);
			}
		}

		// 2. Scan Template Parts & Templates with suffix -{$target_lang}
		$tpl_posts = get_posts( array(
			'post_type'      => array( 'wp_template_part', 'wp_template' ),
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => -1,
		) );

		foreach ( $tpl_posts as $tp ) {
			if ( ! str_ends_with( $tp->post_name, '-' . $target_lang ) ) {
				continue;
			}

			$scanned_count++;
			$raw_html = $tp->post_content;
			$issues   = self::detect_unlocalized_urls( $raw_html, $target_lang );

			if ( ! empty( $issues ) ) {
				$count = count( $issues );
				$total_issues += $count;
				$items_with_issues[] = array(
					'id'            => $tp->ID,
					'item_type'     => $tp->post_type,
					'title'         => ucfirst( str_replace( '-' . $target_lang, '', $tp->post_name ) ) . ' (' . ( 'wp_template' === $tp->post_type ? 'Template' : 'Part' ) . ')',
					'issues_count'  => $count,
					'issues_sample' => array_slice( $issues, 0, 5 ),
					'edit_url'      => admin_url( 'site-editor.php?postType=' . $tp->post_type ),
				);
			}
		}

		error_log( sprintf( '[Align BulkUrlScanner] Scan complete | Checked %d items | Found %d issues across %d items', $scanned_count, $total_issues, count( $items_with_issues ) ) );

		wp_send_json_success( array(
			'target_lang'        => $target_lang,
			'scanned_count'      => $scanned_count,
			'total_issues_count' => $total_issues,
			'items_count'        => count( $items_with_issues ),
			'items'              => $items_with_issues,
		) );
	}

	/**
	 * AJAX: Batch-fix unlocalized URLs in selected items.
	 */
	public static function ajax_fix_url_problems() {
		check_ajax_referer( 'align_settings_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized access.' ), 403 );
		}

		$target_lang = isset( $_POST['target_lang'] ) ? sanitize_key( $_POST['target_lang'] ) : '';
		$item_ids    = isset( $_POST['item_ids'] ) ? array_map( 'intval', (array) $_POST['item_ids'] ) : array();

		if ( empty( $target_lang ) ) {
			wp_send_json_error( array( 'message' => 'Target language is required.' ), 400 );
		}

		if ( empty( $item_ids ) ) {
			wp_send_json_error( array( 'message' => 'No items specified to fix.' ), 400 );
		}

		if ( ! class_exists( 'Align_Language_Url_Localizer' ) ) {
			wp_send_json_error( array( 'message' => 'Align_Language_Url_Localizer is unavailable.' ), 500 );
		}

		$fixed_count      = 0;
		$total_urls_fixed = 0;

		foreach ( $item_ids as $pid ) {
			$post = get_post( $pid );
			if ( ! $post ) {
				continue;
			}

			$old_content = $post->post_content;
			$new_content = Align_Language_Url_Localizer::localize_html( $old_content, $target_lang );

			if ( $new_content !== $old_content ) {
				if ( function_exists( 'kses_remove_filters' ) ) {
					kses_remove_filters();
				}

				wp_update_post( array(
					'ID'           => $pid,
					'post_content' => wp_slash( $new_content ),
				) );

				if ( function_exists( 'kses_init_filters' ) ) {
					kses_init_filters();
				}

				// If template part or template, synchronize file on disk if exists
				if ( in_array( $post->post_type, array( 'wp_template_part', 'wp_template' ), true ) ) {
					$sub_dir     = ( 'wp_template' === $post->post_type ) ? 'templates' : 'parts';
					$target_file = get_stylesheet_directory() . '/' . $sub_dir . '/' . $post->post_name . '.html';
					if ( file_exists( $target_file ) && is_writable( $target_file ) ) {
						file_put_contents( $target_file, $new_content );
					}
				}

				clean_post_cache( $pid );
				$fixed_count++;
			}
		}

		error_log( sprintf( '[Align BulkUrlScanner] Batch fix completed | Items updated: %d of %d requested', $fixed_count, count( $item_ids ) ) );

		wp_send_json_success( array(
			'message'     => sprintf( __( 'Successfully localized URLs for %d item(s).', 'align' ), $fixed_count ),
			'fixed_count' => $fixed_count,
		) );
	}

	/**
	 * Detects unlocalized internal URLs within an HTML string.
	 *
	 * @param string $html        HTML content.
	 * @param string $target_lang Target language code.
	 * @return array List of unlocalized URLs found.
	 */
	public static function detect_unlocalized_urls( $html, $target_lang ) {
		if ( empty( $html ) || empty( $target_lang ) || ! class_exists( 'Align_Language_Url_Localizer' ) ) {
			return array();
		}

		// Protect language switchers & no-localize elements from inspection
		$cleaned_html = preg_replace( '#<(?:div|nav|ul)[^>]*\balign-lang-switcher\b[^>]*>.*?</(?:div|nav|ul)>#is', '', $html );
		$cleaned_html = preg_replace( '#<[^>]*\bdata-no-localize\b[^>]*>.*?</[^>]+>#is', '', $cleaned_html );

		if ( ! preg_match_all( '/\bhref=(["\'])([^"\']+)\1/i', $cleaned_html, $matches ) ) {
			return array();
		}

		$unlocalized = array();
		$checked     = array();

		foreach ( $matches[2] as $raw_url ) {
			$u = trim( $raw_url );
			if ( empty( $u ) || isset( $checked[ $u ] ) ) {
				continue;
			}
			$checked[ $u ] = true;

			// Skip anchors, mailto, tel, javascript, media files
			if ( str_starts_with( $u, '#' ) ||
			     str_starts_with( $u, 'mailto:' ) ||
			     str_starts_with( $u, 'tel:' ) ||
			     str_starts_with( $u, 'javascript:' ) ||
			     preg_match( '/\.(?:jpg|jpeg|png|gif|svg|webp|pdf|zip|css|js|woff|woff2)(?:\?.*)?$/i', $u ) ) {
				continue;
			}

			// Check if URL is local
			$home_url    = home_url();
			$is_absolute = ( str_starts_with( $u, 'http://' ) || str_starts_with( $u, 'https://' ) );

			if ( $is_absolute && ! str_starts_with( $u, $home_url ) ) {
				continue; // External domain
			}

			// Localize URL test
			$localized = Align_Language_Url_Localizer::localize_url( $u, $target_lang );
			if ( $localized !== $u ) {
				$unlocalized[] = array(
					'original'  => $u,
					'suggested' => $localized,
				);
			}
		}

		return $unlocalized;
	}
}
