<?php
/**
 * Align Theme Core Engine - ZIP Importer Blog Post Assembler
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Zip_Post_Assembler {

	/**
	 * Assemble a single blog post into WordPress post_type = 'post' using Align Builder mode.
	 *
	 * @param array  $info Component metadata.
	 * @param array  $options Import options.
	 * @param string $root_dir Project root directory.
	 * @return array Result array (status, post_id, title, slug, path_key, url, editor_url, entity).
	 */
	public static function assemble_post( array $info, array $options, string $root_dir, string $lang = '' ): array {
		$path_key = $info['path_key'] ?? '';
		$slug     = sanitize_title( $info['slug'] ?? 'post' );
		$lang     = ! empty( $lang ) ? $lang : ( $info['lang'] ?? '' );

		// Step 1: Prepare component payload via Page Assembler
		if ( ! class_exists( 'Align_Zip_Page_Assembler' ) || ! method_exists( 'Align_Zip_Page_Assembler', 'prepare_component_payload' ) ) {
			error_log( sprintf( '[Align ZipPost] Align_Zip_Page_Assembler::prepare_component_payload missing for post path=%s', $path_key ) );
			return array( 'status' => 'error', 'message' => 'Assembler unavailable' );
		}

		$payload = Align_Zip_Page_Assembler::prepare_component_payload( $info, $root_dir );
		if ( null === $payload ) {
			error_log( sprintf( '[Align ZipPost] Failed to extract payload for post path=%s file=%s', $path_key, $info['html_file'] ?? '' ) );
			return array( 'status' => 'error', 'message' => 'Empty payload' );
		}

		$raw_html  = ! empty( $info['html_file'] ) && file_exists( $info['html_file'] ) ? (string) file_get_contents( $info['html_file'] ) : '';
		$meta_data = self::extract_post_metadata( $raw_html, $slug );
		$title     = $meta_data['title'];
		$post_date = $meta_data['date'];

		// Step 3: Check existing post with same slug (Language-aware)
		$default_lang = ( class_exists( 'Align_Language_Manager' ) && method_exists( 'Align_Language_Manager', 'get_default_lang' ) )
			? Align_Language_Manager::get_default_lang()
			: 'en';
		$target_lang = ! empty( $lang ) ? $lang : $default_lang;

		// 3a. Find existing posts strictly matching the same language
		$same_lang_query = array(
			'name'           => $slug,
			'post_type'      => 'post',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		if ( $target_lang === $default_lang ) {
			$same_lang_query['meta_query'] = array(
				'relation' => 'OR',
				array( 'key' => '_align_lang', 'value' => $default_lang ),
				array( 'key' => '_align_lang', 'compare' => 'NOT EXISTS' ),
			);
		} else {
			$same_lang_query['meta_query'] = array(
				array( 'key' => '_align_lang', 'value' => $target_lang ),
			);
		}
		$same_lang_posts = get_posts( $same_lang_query );

		if ( ! empty( $same_lang_posts ) && ! empty( $options['overwrite'] ) ) {
			foreach ( $same_lang_posts as $old_id ) {
				if ( class_exists( 'Align_Builder_Asset_Cleaner' ) ) {
					Align_Builder_Asset_Cleaner::delete_post_assets( $old_id );
				}
				wp_delete_post( $old_id, true );
				error_log( sprintf( '[Align ZipPost] Overwrite same-lang post deleted ID=%d slug=%s lang=%s', $old_id, $slug, $target_lang ) );
			}
		}

		// 3b. Anti-collision across all languages / remaining posts
		$any_existing = get_posts( array(
			'name'           => $slug,
			'post_type'      => 'post',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		) );

		if ( ! empty( $any_existing ) ) {
			$orig_slug = $slug;
			if ( ! empty( $lang ) && $lang !== $default_lang ) {
				$candidate_slug = $slug . '-' . $lang;
				if ( empty( get_posts( array( 'name' => $candidate_slug, 'post_type' => 'post', 'post_status' => 'any', 'fields' => 'ids' ) ) ) ) {
					$slug = $candidate_slug;
				} else {
					$counter = 2;
					while ( ! empty( get_posts( array( 'name' => $candidate_slug . '-' . $counter, 'post_type' => 'post', 'post_status' => 'any', 'fields' => 'ids' ) ) ) ) {
						$counter++;
					}
					$slug = $candidate_slug . '-' . $counter;
				}
				error_log( sprintf( '[Align ZipPost] Slug collision across languages: %s (%s) -> %s', $orig_slug, $lang, $slug ) );
			} else {
				$counter = 2;
				while ( ! empty( get_posts( array( 'name' => $orig_slug . '-' . $counter, 'post_type' => 'post', 'post_status' => 'any', 'fields' => 'ids' ) ) ) ) {
					$counter++;
				}
				$slug = $orig_slug . '-' . $counter;
				error_log( sprintf( '[Align ZipPost] Slug collision resolved: %s -> %s', $orig_slug, $slug ) );
			}
		}

		// Step 4: Anti-collision against pages under /%postname%/ (REQ-14)
		$conflicting_page = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $conflicting_page ) {
			$orig_slug = $slug;
			$slug = $orig_slug . '-post';
			error_log( sprintf( '[Align ZipPost] Slug collision with page "%s" -> "%s"', $orig_slug, $slug ) );
		}

		// Step 5: Insert base post
		$post_arr = array(
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_content' => '',
		);
		if ( ! empty( $post_date ) ) {
			$post_arr['post_date'] = $post_date;
		}

		$post_id = wp_insert_post( $post_arr, true );
		if ( is_wp_error( $post_id ) ) {
			error_log( sprintf( '[Align ZipPost] wp_insert_post error for %s: %s', $slug, $post_id->get_error_message() ) );
			return array( 'status' => 'error', 'message' => $post_id->get_error_message() );
		}

		// Step 6: Build Align custom-html block and update post_content
		$style_id   = 'style-post-' . $slug;
		$script_id  = 'script-post-' . $slug;
		$block_html = Align_Zip_Page_Assembler::build_custom_html_block(
			$payload['body'],
			$payload['css'],
			$payload['js'],
			$style_id,
			$script_id
		);

		kses_remove_filters();
		wp_update_post( array(
			'ID'           => $post_id,
			'post_content' => wp_slash( $block_html ),
		) );
		kses_init_filters();

		// Step 7: Lock editor mode to builder (D-3)
		if ( class_exists( 'Align_Editor_Mode_Lock' ) && method_exists( 'Align_Editor_Mode_Lock', 'set_mode_and_lock' ) ) {
			Align_Editor_Mode_Lock::set_mode_and_lock( $post_id, 'builder', true );
		}

		$editor_url = admin_url( 'admin.php?page=align-builder&post_id=' . $post_id );
		error_log( sprintf( '[Align ZipPost] Post ID %d slug=%s path=%s mode=builder | payload=%d bytes', $post_id, $slug, $path_key, strlen( $block_html ) ) );

		return array(
			'status'     => 'success',
			'post_id'    => $post_id,
			'title'      => $title,
			'slug'       => $slug,
			'path_key'   => $path_key,
			'url'        => get_permalink( $post_id ),
			'editor_url' => $editor_url,
			'entity'     => 'post',
		);
	}

	/**
	 * Extract title and published date from HTML string using DOMDocument.
	 *
	 * @param string $html Raw HTML string.
	 * @param string $fallback_slug Fallback title slug.
	 * @return array Title and optional date string.
	 */
	private static function extract_post_metadata( string $html, string $fallback_slug ): array {
		$title = '';
		$date  = null;

		if ( '' !== trim( $html ) ) {
			$dom = new DOMDocument();
			libxml_use_internal_errors( true );
			$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
			libxml_clear_errors();

			// 1. Title: first <h1> -> <title> -> fallback
			$h1_nodes = $dom->getElementsByTagName( 'h1' );
			if ( $h1_nodes->length > 0 ) {
				$candidate = trim( $h1_nodes->item( 0 )->textContent );
				if ( '' !== $candidate ) {
					$title = $candidate;
				}
			}

			if ( '' === $title ) {
				$title_nodes = $dom->getElementsByTagName( 'title' );
				if ( $title_nodes->length > 0 ) {
					$candidate = trim( $title_nodes->item( 0 )->textContent );
					if ( '' !== $candidate ) {
						$title = $candidate;
					}
				}
			}

			// 2. Published Date: <meta property="article:published_time"> -> <time datetime>
			$meta_nodes = $dom->getElementsByTagName( 'meta' );
			foreach ( $meta_nodes as $mn ) {
				if ( 'article:published_time' === strtolower( $mn->getAttribute( 'property' ) ) ) {
					$candidate_time = trim( $mn->getAttribute( 'content' ) );
					if ( '' !== $candidate_time ) {
						$ts = strtotime( $candidate_time );
						if ( false !== $ts ) {
							$date = gmdate( 'Y-m-d H:i:s', $ts );
							break;
						} else {
							error_log( sprintf( '[Align ZipPost] Invalid article:published_time "%s"', $candidate_time ) );
						}
					}
				}
			}

			if ( null === $date ) {
				$time_nodes = $dom->getElementsByTagName( 'time' );
				foreach ( $time_nodes as $tn ) {
					$candidate_time = trim( $tn->getAttribute( 'datetime' ) );
					if ( '' === $candidate_time ) {
						$candidate_time = trim( $tn->textContent );
					}
					if ( '' !== $candidate_time ) {
						$ts = strtotime( $candidate_time );
						if ( false !== $ts ) {
							$date = gmdate( 'Y-m-d H:i:s', $ts );
							break;
						} else {
							error_log( sprintf( '[Align ZipPost] Invalid <time> datetime "%s"', $candidate_time ) );
						}
					}
				}
			}
		}

		if ( '' === $title ) {
			if ( class_exists( 'Align_Zip_Scanner' ) && method_exists( 'Align_Zip_Scanner', 'format_page_title' ) ) {
				$title = Align_Zip_Scanner::format_page_title( $fallback_slug );
			} else {
				$title = ucwords( str_replace( array( '-', '_' ), ' ', $fallback_slug ) );
			}
		}

		return array(
			'title' => $title,
			'date'  => $date,
		);
	}
}
