<?php
/**
 * Align Theme - AI REST API Dedicated Page Controller
 *
 * Handles decoupled CRUD operations for WordPress Pages, providing clean separation
 * of HTML, CSS, and JS payloads with automated Gutenberg block wrapping and external asset synchronization.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_AI_Page_Controller {

	/**
	 * List pages with pagination, search, and language filtering.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response Paginated list of pages.
	 */
	public static function get_items( WP_REST_Request $request ): WP_REST_Response {
		$page     = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
		$per_page = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?: 10 ) ) );
		$search   = sanitize_text_field( (string) ( $request->get_param( 'search' ) ?: '' ) );
		$status   = sanitize_text_field( (string) ( $request->get_param( 'status' ) ?: 'publish,draft,private' ) );
		$lang     = sanitize_key( (string) ( $request->get_param( 'lang' ) ?: '' ) );

		$allowed_statuses = array( 'publish', 'draft', 'pending', 'private', 'future' );
		$status_list      = array_values( array_intersect( array_filter( array_map( 'trim', explode( ',', $status ) ) ), $allowed_statuses ) );
		if ( empty( $status_list ) ) $status_list = array( 'publish', 'draft', 'private' );

		$query_args = array(
			'post_type'      => 'page',
			'post_status'    => $status_list,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);

		if ( '' !== $search ) $query_args['s'] = $search;

		if ( '' !== $lang ) {
			$default_lang = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en';
			if ( $lang === $default_lang ) {
				$query_args['meta_query'] = array(
					'relation' => 'OR',
					array( 'key' => '_align_lang', 'value' => $lang ),
					array( 'key' => '_align_lang', 'compare' => 'NOT EXISTS' ),
				);
			} else {
				$query_args['meta_query'] = array(
					array( 'key' => '_align_lang', 'value' => $lang ),
				);
			}
		}

		$query = new WP_Query( $query_args );
		$items = array();

		foreach ( $query->posts as $post ) {
			$content         = (string) $post->post_content;
			$has_custom_html = ( false !== strpos( $content, 'wp:align/custom-html' ) || false !== strpos( $content, 'wp:html' ) );
			$post_lang       = get_post_meta( $post->ID, '_align_lang', true ) ?: ( class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en' );

			$items[] = array(
				'id'              => (int) $post->ID,
				'title'           => $post->post_title ?: __( '(No Title)', 'align' ),
				'slug'            => $post->post_name,
				'status'          => $post->post_status,
				'url'             => get_permalink( $post->ID ),
				'author'          => (int) $post->post_author,
				'date'            => $post->post_date_gmt,
				'modified'        => $post->post_modified_gmt,
				'lang'            => $post_lang,
				'has_custom_html' => $has_custom_html,
			);
		}

		$total_posts = (int) $query->found_posts;
		$total_pages = (int) $query->max_num_pages;

		error_log( sprintf( '[Align AI Controller] Listed %d pages (page %d of %d, total %d)', count( $items ), $page, $total_pages, $total_posts ) );

		$response = new WP_REST_Response( $items, 200 );
		$response->header( 'X-WP-Total', (string) $total_posts );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );

		return $response;
	}

	/**
	 * Retrieve a single page with fully decoupled HTML body, scoped CSS, and scoped JS.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error Page response object or WP_Error on failure.
	 */
	public static function get_item( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post || 'page' !== $post->post_type ) {
			error_log( sprintf( '[Align AI Controller] Page not found for ID: %d', $post_id ) );
			return new WP_Error( 'rest_post_invalid_id', __( 'Page not found.', 'align' ), array( 'status' => 404 ) );
		}

		$parts     = self::extract_decoupled_parts( $post );
		$post_lang = get_post_meta( $post->ID, '_align_lang', true ) ?: ( class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en' );

		$data = array(
			'id'       => (int) $post->ID,
			'title'    => $post->post_title,
			'slug'     => $post->post_name,
			'status'   => $post->post_status,
			'url'      => get_permalink( $post->ID ),
			'html'     => $parts['html'],
			'css'      => $parts['css'],
			'js'       => $parts['js'],
			'author'   => (int) $post->post_author,
			'date'     => $post->post_date_gmt,
			'modified' => $post->post_modified_gmt,
			'lang'     => $post_lang,
		);

		error_log( sprintf( '[Align AI Controller] Fetched page ID %d ("%s") | HTML: %d bytes, CSS: %d bytes, JS: %d bytes', $post->ID, $post->post_name, strlen( $parts['html'] ), strlen( $parts['css'] ), strlen( $parts['js'] ) ) );

		return rest_ensure_response( $data );
	}

	/**
	 * Single-shot page creation with decoupled markup, styling, and scripts.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error Created page response or error.
	 */
	public static function create_item( WP_REST_Request $request ) {
		$title = sanitize_text_field( (string) ( $request->get_param( 'title' ) ?? '' ) );
		$slug  = sanitize_title( (string) ( $request->get_param( 'slug' ) ?? '' ) );

		if ( empty( $title ) && empty( $slug ) ) {
			return new WP_Error( 'missing_title', __( 'Title or slug is required to create a page.', 'align' ), array( 'status' => 400 ) );
		}

		if ( empty( $title ) ) {
			$title = ucfirst( str_replace( '-', ' ', $slug ) );
		}

		$raw_status     = sanitize_key( (string) ( $request->get_param( 'status' ) ?: 'publish' ) );
		$valid_statuses = array( 'publish', 'draft', 'pending', 'private', 'future' );
		$status         = in_array( $raw_status, $valid_statuses, true ) ? $raw_status : 'publish';

		if ( 'publish' === $status && ! current_user_can( 'publish_pages' ) ) {
			$status = 'draft';
			error_log( '[Align AI Controller] User lacks publish_pages permission on create. Downgraded status to draft.' );
		}

		$html = (string) ( $request->get_param( 'html' ) ?? '' );
		$css  = (string) ( $request->get_param( 'css' ) ?? '' );
		$js   = (string) ( $request->get_param( 'js' ) ?? '' );

		$post_args = array(
			'post_title'   => $title,
			'post_status'  => $status,
			'post_type'    => 'page',
			'post_content' => '',
		);

		if ( ! empty( $slug ) ) {
			$post_args['post_name'] = $slug;
		}

		$post_id = wp_insert_post( $post_args, true );
		if ( is_wp_error( $post_id ) ) {
			error_log( '[Align AI Controller] Insert failed: ' . $post_id->get_error_message() );
			return $post_id;
		}

		$created_post = get_post( $post_id );
		$final_slug   = $created_post->post_name;
		$style_id     = 'style-page-' . $final_slug;
		$script_id    = 'script-page-' . $final_slug;

		// Save CSS & JS to external disk files
		if ( class_exists( 'Align_Builder_Assets' ) && method_exists( 'Align_Builder_Assets', 'save_external_assets' ) ) {
			if ( '' !== trim( $css ) || '' !== trim( $js ) ) {
				Align_Builder_Assets::save_external_assets( $style_id, $script_id, $css, $js );
			}
		}

		// Assemble block markup
		$block_code = self::assemble_block_payload( $html, $css, $js, $style_id, $script_id );

		$can_unfiltered = current_user_can( 'unfiltered_html' );
		if ( $can_unfiltered ) {
			kses_remove_filters();
		}
		wp_update_post( array(
			'ID'           => $post_id,
			'post_content' => wp_slash( $block_code ),
		) );
		if ( $can_unfiltered ) {
			kses_init_filters();
		}

		// Handle language
		$lang = sanitize_key( (string) ( $request->get_param( 'lang' ) ?: '' ) );
		if ( class_exists( 'Align_Language_Manager' ) ) {
			$save_lang = $lang ?: Align_Language_Manager::get_default_lang();
			update_post_meta( $post_id, '_align_lang', $save_lang );
			if ( ! get_post_meta( $post_id, '_align_lang_group', true ) ) {
				update_post_meta( $post_id, '_align_lang_group', 'grp-' . wp_generate_uuid4() );
			}
		} elseif ( ! empty( $lang ) ) {
			update_post_meta( $post_id, '_align_lang', $lang );
		}

		$fresh_post = get_post( $post_id );
		$data       = array(
			'id'       => (int) $post_id,
			'title'    => $fresh_post->post_title,
			'slug'     => $fresh_post->post_name,
			'status'   => $fresh_post->post_status,
			'url'      => get_permalink( $post_id ),
			'html'     => $html,
			'css'      => $css,
			'js'       => $js,
			'author'   => (int) $fresh_post->post_author,
			'date'     => $fresh_post->post_date_gmt,
			'modified' => $fresh_post->post_modified_gmt,
			'lang'     => get_post_meta( $post_id, '_align_lang', true ) ?: 'en',
		);

		error_log( sprintf( '[Align AI Controller] Created page ID %d ("%s") | Status: %s', $post_id, $fresh_post->post_name, $fresh_post->post_status ) );

		return new WP_REST_Response( $data, 201 );
	}

	/**
	 * Update an existing page with partial or full payload.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error Updated page response or error.
	 */
	public static function update_item( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post || 'page' !== $post->post_type ) {
			error_log( sprintf( '[Align AI Controller] Update target page not found for ID: %d', $post_id ) );
			return new WP_Error( 'rest_post_invalid_id', __( 'Page not found.', 'align' ), array( 'status' => 404 ) );
		}

		$existing_parts = self::extract_decoupled_parts( $post );
		$has_css_param  = ( null !== $request->get_param( 'css' ) );
		$has_js_param   = ( null !== $request->get_param( 'js' ) );
		$new_html       = ( null !== $request->get_param( 'html' ) ) ? (string) $request->get_param( 'html' ) : $existing_parts['html'];
		$new_css        = $has_css_param ? (string) $request->get_param( 'css' ) : $existing_parts['css'];
		$new_js         = $has_js_param  ? (string) $request->get_param( 'js' )  : $existing_parts['js'];

		$post_update = array( 'ID' => $post_id );

		if ( null !== $request->get_param( 'title' ) ) {
			$post_update['post_title'] = sanitize_text_field( (string) $request->get_param( 'title' ) );
		}

		if ( null !== $request->get_param( 'slug' ) ) {
			$post_update['post_name'] = sanitize_title( (string) $request->get_param( 'slug' ) );
		}

		if ( null !== $request->get_param( 'status' ) ) {
			$raw_status     = sanitize_key( (string) $request->get_param( 'status' ) );
			$valid_statuses = array( 'publish', 'draft', 'pending', 'private', 'future' );
			if ( in_array( $raw_status, $valid_statuses, true ) ) {
				if ( 'publish' === $raw_status && ! current_user_can( 'publish_pages' ) ) {
					$raw_status = 'draft';
					error_log( '[Align AI Controller] User lacks publish_pages permission on update. Downgraded status to draft.' );
				}
				$post_update['post_status'] = $raw_status;
			}
		}

		$final_slug = ! empty( $post_update['post_name'] ) ? $post_update['post_name'] : $post->post_name;
		$style_id   = 'style-page-' . $final_slug;
		$script_id  = 'script-page-' . $final_slug;

		$legacy_styles  = array( 'style-page-' . $post->post_name, 'style-page-' . $post_id, 'align-style-page-' . $post->post_name, 'align-style-page-' . $post_id );
		$legacy_scripts = array( 'script-page-' . $post->post_name, 'script-page-' . $post_id, 'align-script-page-' . $post->post_name, 'align-script-page-' . $post_id );

		// Save CSS & JS to external disk files
		if ( class_exists( 'Align_Builder_Assets' ) ) {
			if ( $has_css_param || $has_js_param ) {
				Align_Builder_Assets::save_external_assets( $style_id, $script_id, $new_css, $new_js, $legacy_styles, $legacy_scripts );
			} elseif ( method_exists( 'Align_Builder_Assets', 'sync_external_assets' ) ) {
				Align_Builder_Assets::sync_external_assets( $style_id, $script_id, $new_css, $new_js, $legacy_styles, $legacy_scripts );
			}
		}

		// Reassemble block markup
		$block_code                  = self::assemble_block_payload( $new_html, $new_css, $new_js, $style_id, $script_id );
		$post_update['post_content'] = wp_slash( $block_code );

		$can_unfiltered = current_user_can( 'unfiltered_html' );
		if ( $can_unfiltered ) {
			kses_remove_filters();
		}
		$result = wp_update_post( $post_update, true );
		if ( $can_unfiltered ) {
			kses_init_filters();
		}

		if ( is_wp_error( $result ) ) {
			error_log( sprintf( '[Align AI Controller] Failed updating post ID %d: %s', $post_id, $result->get_error_message() ) );
			return $result;
		}

		// Update language if provided
		if ( null !== $request->get_param( 'lang' ) ) {
			$lang = sanitize_key( (string) $request->get_param( 'lang' ) );
			if ( ! empty( $lang ) ) {
				update_post_meta( $post_id, '_align_lang', $lang );
			}
		}

		$fresh_post = get_post( $post_id );
		$data       = array(
			'id'       => (int) $post_id,
			'title'    => $fresh_post->post_title,
			'slug'     => $fresh_post->post_name,
			'status'   => $fresh_post->post_status,
			'url'      => get_permalink( $post_id ),
			'html'     => $new_html,
			'css'      => $new_css,
			'js'       => $new_js,
			'author'   => (int) $fresh_post->post_author,
			'date'     => $fresh_post->post_date_gmt,
			'modified' => $fresh_post->post_modified_gmt,
			'lang'     => get_post_meta( $post_id, '_align_lang', true ) ?: 'en',
		);

		error_log( sprintf( '[Align AI Controller] Updated page ID %d ("%s")', $post_id, $fresh_post->post_name ) );

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Delete an existing page and purge associated external assets.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error Response object or WP_Error.
	 */
	public static function delete_item( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post || 'page' !== $post->post_type ) {
			error_log( sprintf( '[Align AI Controller] Delete target page not found for ID: %d', $post_id ) );
			return new WP_Error( 'rest_post_invalid_id', __( 'Page not found.', 'align' ), array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			error_log( sprintf( '[Align AI Controller] Forbidden: User #%d lacks delete_post permission for page #%d.', get_current_user_id(), $post_id ) );
			return new WP_Error( 'rest_cannot_delete', __( 'Sorry, you are not allowed to delete this page.', 'align' ), array( 'status' => 403 ) );
		}

		// Clean external static assets from disk
		if ( class_exists( 'Align_Builder_Asset_Cleaner' ) && method_exists( 'Align_Builder_Asset_Cleaner', 'delete_post_assets' ) ) {
			Align_Builder_Asset_Cleaner::delete_post_assets( $post_id );
		}

		$deleted = wp_delete_post( $post_id, true );
		if ( ! $deleted ) {
			error_log( sprintf( '[Align AI Controller] Failed deleting post ID: %d', $post_id ) );
			return new WP_Error( 'rest_cannot_delete', __( 'Failed to delete page.', 'align' ), array( 'status' => 500 ) );
		}

		error_log( sprintf( '[Align AI Controller] Deleted page ID %d ("%s")', $post_id, $post->post_name ) );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Page deleted successfully.', 'align' ),
				'id'      => $post_id,
			),
			200
		);
	}

	/**
	 * Extract decoupled body HTML, scoped CSS, and scoped JS from a post.
	 *
	 * @param WP_Post $post WordPress post object.
	 * @return array Associative array with keys: html, css, js.
	 */
	public static function extract_decoupled_parts( WP_Post $post ): array {
		$raw_content = (string) $post->post_content;
		$body_html   = '';

		// 1. Extract content from Gutenberg Custom HTML block if present
		if ( preg_match( '/<!-- wp:align\/custom-html[^>]*>(.*?)<!-- \/wp:align\/custom-html -->/s', $raw_content, $matches ) ) {
			$body_html = trim( $matches[1] );
		} elseif ( preg_match( '/<!-- wp:html -->(.*?)<!-- \/wp:html -->/s', $raw_content, $matches ) ) {
			$body_html = trim( $matches[1] );
		} else {
			$body_html = trim( $raw_content );
		}

		// 2. Strip body wrapper if full HTML was stored
		if ( preg_match( '/<body[^>]*>(.*?)<\/body>/is', $body_html, $body_matches ) ) {
			$body_html = trim( $body_matches[1] );
		}

		// 3. Remove preview tags and global style tags
		$body_html = preg_replace( '/<style[^>]*id="wp-global-styles"[^>]*>[\s\S]*?<\/style>/i', '', $body_html );
		$body_html = preg_replace( '/<(script|style)[^>]*data-align-preview="1"[^>]*>[\s\S]*?<\/\1>/i', '', $body_html );

		$style_id  = 'style-page-' . $post->post_name;
		$script_id = 'script-page-' . $post->post_name;

		$css = '';
		$js  = '';

		// 4. Extract scoped inline CSS
		$exact_css_regex    = '/<style[^>]*\bid\s*=\s*(["\']?)' . preg_quote( $style_id, '/' ) . '\1[^>]*>([\s\S]*?)<\/style>/is';
		$fallback_css_regex = '/<style[^>]*\bid\s*=\s*(["\']?)(?:(?:align-)?style-[^"\'\s>]+|vvvebjs-styles)\1[^>]*>([\s\S]*?)<\/style>/is';

		if ( preg_match( $exact_css_regex, $body_html, $css_matches ) ) {
			$css       = trim( $css_matches[2] );
			$body_html = preg_replace( $exact_css_regex, '', $body_html, 1 );
		} elseif ( preg_match( $fallback_css_regex, $body_html, $css_matches ) ) {
			$css       = trim( $css_matches[2] );
			$body_html = preg_replace( $fallback_css_regex, '', $body_html, 1 );
		}

		// 5. Extract scoped inline JS
		$exact_js_regex    = '/<script[^>]*\bid\s*=\s*(["\']?)' . preg_quote( $script_id, '/' ) . '\1[^>]*>([\s\S]*?)<\/script>/is';
		$fallback_js_regex = '/<script[^>]*\bid\s*=\s*(["\']?)(?:(?:align-)?script-[^"\'\s>]+|vvvebjs-scripts)\1[^>]*>([\s\S]*?)<\/script>/is';

		if ( preg_match( $exact_js_regex, $body_html, $js_matches ) ) {
			$js        = trim( $js_matches[2] );
			$body_html = preg_replace( $exact_js_regex, '', $body_html, 1 );
		} elseif ( preg_match( $fallback_js_regex, $body_html, $js_matches ) ) {
			$js        = trim( $js_matches[2] );
			$body_html = preg_replace( $fallback_js_regex, '', $body_html, 1 );
		}

		// 6. Load external asset files from disk if available
		if ( class_exists( 'Align_Builder_Assets' ) ) {
			$legacy_styles  = array( 'style-page-' . $post->ID, 'align-style-page-' . $post->post_name, 'align-style-page-' . $post->ID );
			$legacy_scripts = array( 'script-page-' . $post->ID, 'align-script-page-' . $post->post_name, 'align-script-page-' . $post->ID );

			$css = Align_Builder_Assets::load_external_css( $style_id, $css, $legacy_styles );
			$js  = Align_Builder_Assets::load_external_js( $script_id, $js, $legacy_scripts );
		}

		return array(
			'html' => trim( (string) $body_html ),
			'css'  => trim( (string) $css ),
			'js'   => trim( (string) $js ),
		);
	}

	/**
	 * Assemble and minify Gutenberg Custom HTML block markup.
	 *
	 * @param string $html      Body HTML.
	 * @param string $css       Scoped CSS.
	 * @param string $js        Scoped JS.
	 * @param string $style_id  Target style ID.
	 * @param string $script_id Target script ID.
	 * @return string Gutenberg block string.
	 */
	public static function assemble_block_payload( string $html, string $css, string $js, string $style_id, string $script_id ): string {
		if ( class_exists( 'Align_Builder_Minifier' ) && method_exists( 'Align_Builder_Minifier', 'minify_payload' ) ) {
			$clean_payload = Align_Builder_Minifier::minify_payload( $html, $css, $js, $style_id, $script_id );
		} else {
			$assembled = '';
			if ( '' !== trim( $css ) ) {
				$assembled .= '<style id="' . esc_attr( $style_id ) . '">' . $css . '</style>';
			}
			$assembled .= $html;
			if ( '' !== trim( $js ) ) {
				$assembled .= '<script id="' . esc_attr( $script_id ) . '">' . $js . '</script>';
			}
			$clean_payload = $assembled;
		}

		$block_id   = 'align-' . substr( md5( uniqid( '', true ) ), 0, 8 );
		$attrs_json = wp_json_encode( array( 'blockId' => $block_id, 'enableShortcode' => true ) );

		return "<!-- wp:align/custom-html " . $attrs_json . " -->\n" . $clean_payload . "\n<!-- /wp:align/custom-html -->";
	}
}
