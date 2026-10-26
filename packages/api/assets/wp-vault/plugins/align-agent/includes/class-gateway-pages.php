<?php
/**
 * Align Agent Gateway - Dedicated Pages Controller
 *
 * Provides REST endpoints for external agents to list, retrieve, create,
 * update, and rollback WordPress pages with decoupled HTML, CSS, and JS.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Gateway_Pages {

	/**
	 * List pages with search and status filtering (metadata only).
	 */
	public static function handle_get_pages( WP_REST_Request $request ): WP_REST_Response {
		$page     = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
		$per_page = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?: 20 ) ) );
		$search   = sanitize_text_field( (string) ( $request->get_param( 's' ) ?: $request->get_param( 'search' ) ?: '' ) );
		$slug     = sanitize_title( (string) ( $request->get_param( 'slug' ) ?: $request->get_param( 'name' ) ?: '' ) );
		$status   = sanitize_text_field( (string) ( $request->get_param( 'status' ) ?: 'publish,draft,private' ) );
		$lang     = sanitize_key( (string) ( $request->get_param( 'lang' ) ?: '' ) );

		$allowed_statuses = array( 'publish', 'draft', 'pending', 'private', 'future' );
		$status_list      = array_values( array_intersect( array_filter( array_map( 'trim', explode( ',', $status ) ) ), $allowed_statuses ) );
		if ( empty( $status_list ) ) {
			$status_list = array( 'publish', 'draft', 'private' );
		}

		$query_args = array(
			'post_type'      => 'page',
			'post_status'    => $status_list,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);
		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}
		if ( '' !== $slug ) {
			$query_args['name'] = $slug;
		}
		if ( '' !== $lang && class_exists( 'Align_Agent_Tools_Finder' ) ) {
			$query_args['meta_query'] = Align_Agent_Tools_Finder::build_lang_meta_query( $lang );
		}

		$query    = new WP_Query( $query_args );
		$items    = array();
		$def_lang = class_exists( 'Align_Language_Manager' ) ? (string) Align_Language_Manager::get_default_lang() : 'en';

		foreach ( $query->posts as $post ) {
			$content   = (string) $post->post_content;
			$post_lang = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_post_lang( (int) $post->ID ) : (string) get_post_meta( (int) $post->ID, '_align_lang', true );
			$post_lang = '' !== $post_lang ? $post_lang : $def_lang;
			if ( '' !== $lang && $post_lang !== $lang ) {
				continue;
			}
			$raw_url = (string) get_permalink( $post->ID );
			$items[] = array(
				'id'              => (int) $post->ID,
				'title'           => $post->post_title ? $post->post_title : __( '(No Title)', 'align-agent' ),
				'slug'            => $post->post_name,
				'status'          => $post->post_status,
				'lang'            => $post_lang,
				'url'             => class_exists( 'Align_Agent_Tools_Media' ) ? Align_Agent_Tools_Media::to_root_relative_url( $raw_url ) : $raw_url,
				'modified'        => $post->post_modified_gmt,
				'date'            => $post->post_date_gmt,
				'has_custom_html' => ( false !== strpos( $content, 'wp:align/custom-html' ) || false !== strpos( $content, 'wp:html' ) ),
			);
		}

		$total_posts = (int) $query->found_posts;
		$total_pages = (int) $query->max_num_pages;

		error_log( sprintf( '[Align Agent] Listed %d pages (page %d of %d, total %d).', count( $items ), $page, $total_pages, $total_posts ) );

		$response = new WP_REST_Response( $items, 200 );
		$response->header( 'X-WP-Total', (string) $total_posts );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );
		return $response;
	}

	/**
	 * Retrieve a single page with clean body HTML and CSS from disk.
	 */
	public static function handle_get_page( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post || 'page' !== $post->post_type ) {
			error_log( sprintf( '[Align Agent] Page not found for ID: %d', $post_id ) );
			return new WP_Error( 'page_not_found', __( 'Page not found.', 'align-agent' ), array( 'status' => 404 ) );
		}

		$parts   = self::extract_decoupled_parts( $post );
		$raw_url = (string) get_permalink( $post->ID );
		$data    = array(
			'id'       => (int) $post->ID,
			'title'    => $post->post_title,
			'slug'     => $post->post_name,
			'status'   => $post->post_status,
			'url'      => class_exists( 'Align_Agent_Tools_Media' ) ? Align_Agent_Tools_Media::to_root_relative_url( $raw_url ) : $raw_url,
			'html'     => $parts['html'],
			'css'      => $parts['css'],
			'js'       => $parts['js'],
			'date'     => $post->post_date_gmt,
			'modified' => $post->post_modified_gmt,
		);

		error_log( sprintf( '[Align Agent] Fetched page ID %d ("%s") | HTML: %d bytes, CSS: %d bytes, JS: %d bytes.', $post->ID, $post->post_name, strlen( $parts['html'] ), strlen( $parts['css'] ), strlen( $parts['js'] ) ) );
		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Create a new page, write CSS to disk, and wrap in align/custom-html block.
	 */
	public static function handle_create_page( WP_REST_Request $request ) {
		$title = sanitize_text_field( (string) ( $request->get_param( 'title' ) ?? '' ) );
		$slug  = sanitize_title( (string) ( $request->get_param( 'slug' ) ?? '' ) );

		if ( empty( $title ) && empty( $slug ) ) {
			return new WP_Error( 'missing_title', __( 'Title or slug is required to create a page.', 'align-agent' ), array( 'status' => 400 ) );
		}
		if ( empty( $title ) ) {
			$title = ucwords( str_replace( '-', ' ', $slug ) );
		}

		$raw_status     = sanitize_key( (string) ( $request->get_param( 'status' ) ?: 'publish' ) );
		$valid_statuses = array( 'publish', 'draft', 'pending', 'private', 'future' );
		$status         = in_array( $raw_status, $valid_statuses, true ) ? $raw_status : 'publish';

		$html      = (string) ( $request->get_param( 'html' ) ?? '' );
		$css       = (string) ( $request->get_param( 'css' ) ?? '' );
		$js        = (string) ( $request->get_param( 'js' ) ?? '' );
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
			error_log( '[Align Agent] Create page failed: ' . $post_id->get_error_message() );
			return $post_id;
		}

		$created_post = get_post( $post_id );
		$final_slug   = $created_post->post_name;

		$has_explicit = ( null !== $request->get_param( 'css' ) || null !== $request->get_param( 'js' ) );
		self::write_asset_files( $final_slug, $css, $js, $has_explicit );
		$block_content = self::assemble_block_payload( $html, $css, $js, $final_slug );

		kses_remove_filters();
		wp_update_post( array( 'ID' => $post_id, 'post_content' => wp_slash( $block_content ) ) );
		kses_init_filters();

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
			'date'     => $fresh_post->post_date_gmt,
			'modified' => $fresh_post->post_modified_gmt,
		);

		error_log( sprintf( '[Align Agent] Created page ID %d ("%s") | Status: %s.', $post_id, $final_slug, $status ) );
		return new WP_REST_Response( $data, 201 );
	}

	/**
	 * Update an existing page content and disk CSS.
	 */
	public static function handle_update_page( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post || 'page' !== $post->post_type ) {
			error_log( sprintf( '[Align Agent] Update failed: Page #%d not found.', $post_id ) );
			return new WP_Error( 'page_not_found', __( 'Page not found.', 'align-agent' ), array( 'status' => 404 ) );
		}

		$parts    = self::extract_decoupled_parts( $post );
		$new_html = ( null !== $request->get_param( 'html' ) ) ? (string) $request->get_param( 'html' ) : $parts['html'];
		$new_css  = ( null !== $request->get_param( 'css' ) )  ? (string) $request->get_param( 'css' )  : $parts['css'];
		$new_js   = ( null !== $request->get_param( 'js' ) )   ? (string) $request->get_param( 'js' )   : $parts['js'];

		$post_update = array( 'ID' => $post_id );
		if ( null !== $request->get_param( 'title' ) ) {
			$post_update['post_title'] = sanitize_text_field( (string) $request->get_param( 'title' ) );
		}
		if ( null !== $request->get_param( 'slug' ) ) {
			$raw_slug = sanitize_title( (string) $request->get_param( 'slug' ) );
			if ( ! empty( $raw_slug ) ) {
				$post_update['post_name'] = $raw_slug;
			}
		}
		if ( null !== $request->get_param( 'status' ) ) {
			$raw_status     = sanitize_key( (string) $request->get_param( 'status' ) );
			$valid_statuses = array( 'publish', 'draft', 'pending', 'private', 'future' );
			if ( in_array( $raw_status, $valid_statuses, true ) ) {
				$post_update['post_status'] = $raw_status;
			}
		}

		$final_slug   = ! empty( $post_update['post_name'] ) ? $post_update['post_name'] : $post->post_name;
		$has_explicit = ( null !== $request->get_param( 'css' ) || null !== $request->get_param( 'js' ) );
		self::write_asset_files( $final_slug, $new_css, $new_js, $has_explicit );

		$block_content               = self::assemble_block_payload( $new_html, $new_css, $new_js, $final_slug );
		$post_update['post_content'] = wp_slash( $block_content );

		kses_remove_filters();
		$result = wp_update_post( $post_update, true );
		kses_init_filters();

		if ( is_wp_error( $result ) ) {
			error_log( sprintf( '[Align Agent] Update failed for page ID %d: %s', $post_id, $result->get_error_message() ) );
			return $result;
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
			'date'     => $fresh_post->post_date_gmt,
			'modified' => $fresh_post->post_modified_gmt,
		);

		error_log( sprintf( '[Align Agent] Updated page ID %d ("%s").', $post_id, $final_slug ) );
		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Rollback page to a previous revision.
	 */
	public static function handle_rollback_page( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post || 'page' !== $post->post_type ) {
			error_log( sprintf( '[Align Agent] Rollback failed: Page #%d not found.', $post_id ) );
			return new WP_Error( 'page_not_found', __( 'Page not found.', 'align-agent' ), array( 'status' => 404 ) );
		}

		$revisions = wp_get_post_revisions( $post_id );
		if ( empty( $revisions ) ) {
			error_log( sprintf( '[Align Agent] Rollback failed: No revisions found for page #%d.', $post_id ) );
			return new WP_Error( 'no_revisions', __( 'No revisions found for this page.', 'align-agent' ), array( 'status' => 404 ) );
		}

		$req_revision_id = (int) $request->get_param( 'revision_id' );
		$target_revision = ( $req_revision_id > 0 && isset( $revisions[ $req_revision_id ] ) ) ? $revisions[ $req_revision_id ] : reset( $revisions );

		if ( ! $target_revision ) {
			return new WP_Error( 'revision_not_found', __( 'Specified revision was not found.', 'align-agent' ), array( 'status' => 404 ) );
		}

		$rev_id = (int) $target_revision->ID;
		kses_remove_filters();
		$restored_id = wp_restore_post_revision( $rev_id );
		kses_init_filters();

		if ( ! $restored_id ) {
			$updated = wp_update_post( array( 'ID' => $post_id, 'post_content' => wp_slash( $target_revision->post_content ) ) );
			if ( is_wp_error( $updated ) || ! $updated ) {
				error_log( sprintf( '[Align Agent] Rollback failed during restore for page #%d.', $post_id ) );
				return new WP_Error( 'rollback_failed', __( 'Failed to restore revision.', 'align-agent' ), array( 'status' => 500 ) );
			}
		}

		$restored_post = get_post( $post_id );
		$parts         = self::extract_decoupled_parts( $restored_post );
		self::write_asset_files( $restored_post->post_name, $parts['css'], $parts['js'], false );

		error_log( sprintf( '[Align Agent] Rolled back page ID %d ("%s") to revision #%d.', $post_id, $restored_post->post_name, $rev_id ) );

		$response = array(
			'success'              => true,
			'message'              => sprintf( __( 'Page rolled back successfully to revision #%d.', 'align-agent' ), $rev_id ),
			'restored_revision_id' => $rev_id,
			'page'                 => array(
				'id'       => (int) $post_id,
				'title'    => $restored_post->post_title,
				'slug'     => $restored_post->post_name,
				'status'   => $restored_post->post_status,
				'url'      => get_permalink( $post_id ),
				'html'     => $parts['html'],
				'css'      => $parts['css'],
				'js'       => $parts['js'],
				'modified' => $restored_post->post_modified_gmt,
			),
		);
		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Extract decoupled body HTML, CSS, and JS from post content and disk.
	 */
	public static function extract_decoupled_parts( WP_Post $post ): array {
		$raw_content = (string) $post->post_content;
		$body_html   = '';

		if ( preg_match( '/<!-- wp:align\/custom-html[^>]*>(.*?)<!-- \/wp:align\/custom-html -->/s', $raw_content, $matches ) ) {
			$body_html = trim( $matches[1] );
		} elseif ( preg_match( '/<!-- wp:html -->(.*?)<!-- \/wp:html -->/s', $raw_content, $matches ) ) {
			$body_html = trim( $matches[1] );
		} else {
			$body_html = trim( $raw_content );
		}

		if ( preg_match( '/<body[^>]*>(.*?)<\/body>/is', $body_html, $body_matches ) ) {
			$body_html = trim( $body_matches[1] );
		}

		$body_html = preg_replace( '/<style[^>]*id="wp-global-styles"[^>]*>[\s\S]*?<\/style>/i', '', $body_html );
		$body_html = preg_replace( '/<(script|style)[^>]*data-align-preview="1"[^>]*>[\s\S]*?<\/\1>/i', '', $body_html );

		$slug        = $post->post_name;
		$style_id    = 'style-page-' . $slug;
		$script_id   = 'script-page-' . $slug;
		$css         = '';
		$js          = '';
		$css_pattern = '/<style[^>]*\bid\s*=\s*(["\']?)(?:' . preg_quote( $style_id, '/' ) . '|style-page-[^"\'\s>]+|align-style-page-[^"\'\s>]+|page-[^"\'\s>]+)\1[^>]*>([\s\S]*?)<\/style>/is';

		if ( preg_match( $css_pattern, $body_html, $css_matches ) ) {
			$css       = trim( $css_matches[2] );
			$body_html = preg_replace( $css_pattern, '', $body_html, 1 );
		}

		$js_pattern = '/<script[^>]*\bid\s*=\s*(["\']?)(?:' . preg_quote( $script_id, '/' ) . '|script-page-[^"\'\s>]+|align-script-page-[^"\'\s>]+)\1[^>]*>([\s\S]*?)<\/script>/is';
		if ( preg_match( $js_pattern, $body_html, $js_matches ) ) {
			$js        = trim( $js_matches[2] );
			$body_html = preg_replace( $js_pattern, '', $body_html, 1 );
		}

		return array(
			'html' => trim( (string) $body_html ),
			'css'  => trim( (string) self::read_asset_file( $slug, 'css', $css ) ),
			'js'   => trim( (string) self::read_asset_file( $slug, 'js', $js ) ),
		);
	}

	/**
	 * Assemble Gutenberg custom-html block payload.
	 */
	private static function assemble_block_payload( string $html, string $css, string $js, string $slug ): string {
		$style_id  = 'style-page-' . $slug;
		$script_id = 'script-page-' . $slug;

		if ( class_exists( 'Align_Builder_Minifier' ) && method_exists( 'Align_Builder_Minifier', 'minify_payload' ) ) {
			$clean_payload = Align_Builder_Minifier::minify_payload( $html, $css, $js, $style_id, $script_id );
		} else {
			$assembled = '';
			if ( '' !== trim( $css ) ) {
				$assembled .= '<style id="' . esc_attr( $style_id ) . '">' . $css . '</style>' . "\n";
			}
			$assembled .= $html;
			if ( '' !== trim( $js ) ) {
				$assembled .= "\n" . '<script id="' . esc_attr( $script_id ) . '">' . $js . '</script>';
			}
			$clean_payload = $assembled;
		}

		$block_id   = 'align-' . substr( md5( uniqid( (string) mt_rand(), true ) ), 0, 8 );
		$attrs_json = wp_json_encode( array( 'blockId' => $block_id, 'enableShortcode' => true ) );
		return "<!-- wp:align/custom-html " . $attrs_json . " -->\n" . $clean_payload . "\n<!-- /wp:align/custom-html -->";
	}

	/**
	 * Persist CSS and JS files to disk using WP_Filesystem and Align Builder asset services.
	 */
	private static function write_asset_files( string $slug, string $css, string $js, bool $is_explicit = false ): void {
		$safe_slug = sanitize_file_name( $slug );
		if ( empty( $safe_slug ) ) {
			error_log( '[Align Agent] write_asset_files aborted: empty slug provided.' );
			return;
		}

		$fs  = self::init_filesystem();
		$dir = trailingslashit( get_stylesheet_directory() ) . 'assets/builder/';

		if ( ! is_dir( $dir ) && function_exists( 'wp_mkdir_p' ) ) {
			wp_mkdir_p( $dir );
		}

		if ( '' !== trim( $css ) ) {
			$f1 = $dir . 'page-' . $safe_slug . '.css';
			$f2 = $dir . 'style-page-' . $safe_slug . '.css';
			if ( $fs ) {
				$fs->put_contents( $f1, $css, FS_CHMOD_FILE );
				$fs->put_contents( $f2, $css, FS_CHMOD_FILE );
			} else {
				file_put_contents( $f1, $css );
				file_put_contents( $f2, $css );
			}
		}

		if ( '' !== trim( $js ) ) {
			$jf = $dir . 'script-page-' . $safe_slug . '.js';
			if ( $fs ) {
				$fs->put_contents( $jf, $js, FS_CHMOD_FILE );
			} else {
				file_put_contents( $jf, $js );
			}
		}

		if ( class_exists( 'Align_Builder_Assets' ) ) {
			if ( $is_explicit ) {
				Align_Builder_Assets::save_external_assets( 'style-page-' . $safe_slug, 'script-page-' . $safe_slug, $css, $js );
			} elseif ( '' !== trim( $css ) || '' !== trim( $js ) ) {
				if ( method_exists( 'Align_Builder_Assets', 'sync_external_assets' ) ) {
					Align_Builder_Assets::sync_external_assets( 'style-page-' . $safe_slug, 'script-page-' . $safe_slug, $css, $js );
				} else {
					Align_Builder_Assets::save_external_assets( 'style-page-' . $safe_slug, 'script-page-' . $safe_slug, $css, $js );
				}
			}
		}
	}

	/**
	 * Read asset file from disk with fallbacks across naming conventions.
	 */
	public static function read_asset_file( string $slug, string $type, string $fallback = '' ): string {
		$safe_slug = sanitize_file_name( $slug );
		if ( empty( $safe_slug ) ) {
			return $fallback;
		}

		$dir        = trailingslashit( get_stylesheet_directory() ) . 'assets/builder/';
		$candidates = ( 'css' === $type )
			? array( $dir . 'page-' . $safe_slug . '.css', $dir . 'style-page-' . $safe_slug . '.css', $dir . 'align-style-page-' . $safe_slug . '.css' )
			: array( $dir . 'script-page-' . $safe_slug . '.js', $dir . 'page-' . $safe_slug . '.js', $dir . 'align-script-page-' . $safe_slug . '.js' );

		foreach ( $candidates as $file_path ) {
			if ( file_exists( $file_path ) && is_readable( $file_path ) ) {
				$content = file_get_contents( $file_path );
				if ( false !== $content && '' !== trim( $content ) ) {
					return trim( $content );
				}
			}
		}
		return $fallback;
	}

	/**
	 * Initialize and retrieve WP_Filesystem instance.
	 */
	private static function init_filesystem() {
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		return $wp_filesystem ?: false;
	}
}
