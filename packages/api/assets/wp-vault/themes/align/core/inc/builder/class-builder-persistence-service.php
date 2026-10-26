<?php
/**
 * Align Theme Core Engine - Unified Builder Persistence Service
 *
 * Handles canonical WordPress database persistence, atomic file synchronization,
 * template structure preservation, and asset extraction across all contexts.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Builder_Persistence_Service {

	/** @var bool Flag to prevent infinite recursion during save_post hooks. */
	private static $is_syncing_assets = false;

	/** Initialize WordPress lifecycle hooks for automatic asset synchronization. */
	public static function init() {
		add_action( 'save_post', array( __CLASS__, 'handle_save_post' ), 20, 3 );
		add_action( 'rest_after_insert_wp_template', array( __CLASS__, 'handle_rest_after_insert_template' ), 20, 3 );
		add_action( 'rest_after_insert_wp_template_part', array( __CLASS__, 'handle_rest_after_insert_template_part' ), 20, 3 );
	}

	/** Safely write file content atomically using a temporary file and rename. */
	public static function atomic_file_put_contents( string $filepath, string $content ): bool {
		$dir = dirname( $filepath );
		if ( ! is_dir( $dir ) && function_exists( 'wp_mkdir_p' ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			error_log( sprintf( '[Align Persistence] [ERROR] Target directory not writable: %s', $dir ) );
			return false;
		}

		// wp_tempnam() lives in wp-admin/includes/file.php, which is NOT loaded in REST context.
		if ( ! function_exists( 'wp_tempnam' ) && file_exists( ABSPATH . 'wp-admin/includes/file.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		// Trailing slash is required: core concatenates $dir . $filename without a separator.
		$temp_file = function_exists( 'wp_tempnam' ) ? wp_tempnam( basename( $filepath ), trailingslashit( $dir ) ) : '';
		if ( ! $temp_file ) {
			$temp_file = $filepath . '.tmp.' . wp_generate_uuid4();
			error_log( sprintf( '[Align Persistence] wp_tempnam unavailable, using fallback temp file: %s', $temp_file ) );
		}

		$bytes = file_put_contents( $temp_file, $content, LOCK_EX );
		if ( false === $bytes ) {
			error_log( sprintf( '[Align Persistence] [ERROR] Failed to write temporary file: %s', $temp_file ) );
			if ( file_exists( $temp_file ) ) @unlink( $temp_file );
			return false;
		}

		@chmod( $temp_file, 0664 );
		if ( ! @rename( $temp_file, $filepath ) ) {
			if ( ! @copy( $temp_file, $filepath ) ) {
				error_log( sprintf( '[Align Persistence] [ERROR] Failed to replace target file: %s from %s', $filepath, $temp_file ) );
				@unlink( $temp_file );
				return false;
			}
			@unlink( $temp_file );
		}

		clearstatcache( true, $filepath );
		return true;
	}

	/** Convert post-content placeholder back into native Gutenberg block. */
	public static function restore_post_content_block( string $html, string $post_content_block = '<!-- wp:post-content /-->' ): string {
		return preg_replace_callback(
			'/<div\b[^>]*\bdata-component-post-content\b[^>]*>[\s\S]*?<\/div>/i',
			function () use ( $post_content_block ) {
				return $post_content_block;
			},
			$html
		);
	}

	/**
	 * Unwrap runtime animation spacers (e.g. GSAP ScrollTrigger .pin-spacer).
	 *
	 * @param string $html Input HTML.
	 * @return string HTML with pin-spacers safely unwrapped.
	 */
	public static function unwrap_runtime_spacers( string $html ): string {
		if ( ! str_contains( $html, 'pin-spacer' ) ) {
			return $html;
		}

		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$xpath = new DOMXPath( $doc );
		while ( ( $spacers = $xpath->query( '//div[contains(concat(" ", normalize-space(@class), " "), " pin-spacer ")]' ) ) && $spacers->length > 0 ) {
			foreach ( $spacers as $spacer ) {
				$parent = $spacer->parentNode;
				if ( ! $parent ) continue;
				while ( $spacer->firstChild ) {
					$parent->insertBefore( $spacer->firstChild, $spacer );
				}
				$parent->removeChild( $spacer );
			}
		}

		$root = $doc->getElementsByTagName( 'div' )->item( 0 );
		if ( $root ) {
			$inner = '';
			foreach ( $root->childNodes as $child ) {
				$inner .= $doc->saveHTML( $child );
			}
			if ( ! empty( $inner ) ) {
				error_log( '[Align Persistence] Unwrapped runtime .pin-spacer elements from payload.' );
				return trim( $inner );
			}
		}

		return $html;
	}

	/** Clean raw HTML before packaging: strip body/html wrappers and preview assets. */
	public static function clean_html_body( string $html ): string {
		$clean = trim( $html );
		if ( str_starts_with( $clean, "\xEF\xBB\xBF" ) ) {
			$clean = trim( substr( $clean, 3 ) );
		}
		if ( preg_match( '/<body[^>]*>(.*?)<\/body>/is', $clean, $m ) ) {
			$clean = trim( $m[1] );
		} else {
			$clean = trim( preg_replace( '/<\/?(html|head|body)\b[^>]*>|<!DOCTYPE[^>]*>/i', '', $clean ) );
		}

		$clean = preg_replace( '/<style[^>]*id="wp-global-styles"[^>]*>[\s\S]*?<\/style>|<(script|style)[^>]*data-align-preview="1"[^>]*>[\s\S]*?<\/\1>/i', '', $clean );

		if ( str_contains( $clean, 'pin-spacer' ) ) {
			$clean = self::unwrap_runtime_spacers( $clean );
		}

		$site_url = site_url();
		$home_url = home_url();
		if ( ! empty( $site_url ) ) { $clean = str_ireplace( $site_url . '/wp-content/', '/wp-content/', $clean ); }
		if ( ! empty( $home_url ) && $home_url !== $site_url ) { $clean = str_ireplace( $home_url . '/wp-content/', '/wp-content/', $clean ); }

		return trim( $clean );
	}

	/** Package HTML, CSS, and JS into canonical align/custom-html block markup. */
	public static function package_custom_html_block( string $html, string $css, string $js, string $style_id, string $script_id ): string {
		$minified = class_exists( 'Align_Builder_Minifier' )
			? Align_Builder_Minifier::minify_payload( $html, $css, $js, $style_id, $script_id )
			: $html;

		$attrs = array( 'blockId' => 'align-' . substr( md5( uniqid( '', true ) ), 0, 8 ), 'enableShortcode' => true );
		if ( ! empty( $style_id ) )  $attrs['styleId']  = $style_id;
		if ( ! empty( $script_id ) ) $attrs['scriptId'] = $script_id;

		return "<!-- wp:align/custom-html " . wp_json_encode( $attrs ) . " -->\n" . $minified . "\n<!-- /wp:align/custom-html -->";
	}

	/** Persist a Page, Post, or Reusable Block canonical data and derived assets. */
	public static function persist_page( int $post_id, string $html, string $css_param = '', string $js_param = '', $reusable_overrides = null, string $post_status = '' ): array {
		if ( $post_id <= 0 ) return array( 'status' => 'error', 'message' => 'Invalid post ID provided.' );
		$post = get_post( $post_id );
		if ( ! $post ) return array( 'status' => 'error', 'message' => sprintf( 'Post #%d does not exist.', $post_id ) );

		$post_type  = $post->post_type;
		$clean_html = self::clean_html_body( $html );

		if ( '' === trim( $css_param ) && preg_match_all( '/<style\b[^>]*>([\s\S]*?)<\/style>/is', $clean_html, $css_m ) ) {
			$css_param = trim( implode( "\n\n", array_filter( array_map( 'trim', $css_m[1] ) ) ) );
		}
		if ( '' === trim( $js_param ) && preg_match_all( '/<script\b[^>]*>([\s\S]*?)<\/script>/is', $clean_html, $js_m ) ) {
			$js_param = trim( implode( "\n\n", array_map( 'trim', $js_m[1] ) ) );
		}
		$clean_html = preg_replace( array( '/<style\b[^>]*>[\s\S]*?<\/style>/is', '/<script\b[^>]*>[\s\S]*?<\/script>/is' ), '', $clean_html );

		if ( preg_match( '/^<div\b[^>]*\bclass="[^"]*\balign-custom-html-block\b[^"]*"[^>]*>\s*([\s\S]*?)\s*<\/div>$/i', trim( $clean_html ), $wrap_m ) ) {
			$clean_html = trim( $wrap_m[1] );
		}

		$entity_slug       = ! empty( $post->post_name ) ? $post->post_name : (string) $post_id;
		$prefix            = ( 'wp_block' === $post_type ) ? 'block' : 'page';
		$style_id          = 'style-' . $prefix . '-' . $entity_slug;
		$script_id         = 'script-' . $prefix . '-' . $entity_slug;
		$legacy_style_ids  = array( 'align-style-' . $prefix . '-' . $entity_slug, 'align-style-' . $prefix . '-' . $post_id, 'style-' . $prefix . '-' . $post_id );
		$legacy_script_ids = array( 'align-script-' . $prefix . '-' . $entity_slug, 'align-script-' . $prefix . '-' . $post_id, 'script-' . $prefix . '-' . $post_id );

		if ( class_exists( 'Align_Builder_Assets' ) ) {
			Align_Builder_Assets::save_external_assets( $style_id, $script_id, $css_param, $js_param, $legacy_style_ids, $legacy_script_ids );
		}

		$block_markup = self::package_custom_html_block( $clean_html, '', '', $style_id, $script_id );

		self::$is_syncing_assets = true;
		kses_remove_filters();
		$update_args = array( 'ID' => $post_id, 'post_content' => wp_slash( $block_markup ) );
		if ( ! empty( $post_status ) && in_array( $post_status, array( 'publish', 'draft', 'pending', 'private', 'future' ), true ) ) {
			$update_args['post_status'] = $post_status;
		}
		$updated = wp_update_post( $update_args, true );
		kses_init_filters();
		self::$is_syncing_assets = false;

		if ( is_wp_error( $updated ) ) {
			error_log( sprintf( '[Align Persistence] [ERROR] DB update failed for Post #%d: %s', $post_id, $updated->get_error_message() ) );
			return array( 'status' => 'error', 'message' => $updated->get_error_message() );
		}

		if ( class_exists( 'Align_Language_Manager' ) && ! get_post_meta( $post_id, '_align_lang', true ) ) {
			$parent_id   = wp_get_post_parent_id( $post_id );
			$parent_lang = $parent_id ? get_post_meta( $parent_id, '_align_lang', true ) : '';
			$save_lang   = $parent_lang ?: Align_Language_Manager::get_default_lang();
			update_post_meta( $post_id, '_align_lang', $save_lang );
			if ( ! get_post_meta( $post_id, '_align_lang_group', true ) ) {
				update_post_meta( $post_id, '_align_lang_group', 'grp-' . wp_generate_uuid4() );
			}
		}

		if ( isset( $reusable_overrides ) ) {
			if ( class_exists( 'Align_Reusable_Block' ) ) {
				$reusable_overrides = Align_Reusable_Block::sanitize_overrides( $reusable_overrides );
			} elseif ( is_string( $reusable_overrides ) ) {
				$reusable_overrides = json_decode( $reusable_overrides, true );
			}
			if ( is_array( $reusable_overrides ) ) {
				update_post_meta( $post_id, '_align_reusable_overrides', $reusable_overrides );
			}
		}

		clean_post_cache( $post_id );
		$version = time();
		error_log( sprintf( '[Align Persistence] [SUCCESS] Persisted Post #%d ("%s") | Length: %d bytes | Version: %s', $post_id, $post->post_title, strlen( $block_markup ), $version ) );

		return array(
			'status'             => 'success',
			'post_id'            => $post_id,
			'post_title'         => $post->post_title,
			'post_status'        => get_post_status( $post_id ),
			'version'            => $version,
			'persisted_length'   => strlen( $block_markup ),
			'reusable_overrides' => is_array( $reusable_overrides ) ? $reusable_overrides : array(),
			'message'            => sprintf( __( 'Page content saved to Post #%d', 'align' ), $post_id ),
		);
	}

	/** Persist a Template or Template Part canonical data, structure, and derived files. */
	public static function persist_template( string $tp_id, string $html, string $css_param = '', string $js_param = '', string $template_type = 'template', $reusable_overrides = null ): array {
		$raw_id           = $tp_id;
		$is_template      = ( 'template' === $template_type || 0 === strpos( $raw_id, 'tpl_' ) );
		$post_type_target = $is_template ? 'wp_template' : 'wp_template_part';

		if ( 0 === strpos( $raw_id, 'tp_' ) || 0 === strpos( $raw_id, 'tpl_' ) ) {
			$raw_id = preg_replace( '/^t(p|pl)_/', '', $raw_id );
		}
		$slug         = sanitize_file_name( ( strpos( $raw_id, '//' ) !== false ) ? explode( '//', $raw_id )[1] : $raw_id );
		$search_slugs = array_values( array_unique( array_filter( array(
			$slug, get_stylesheet() . '//' . $slug, get_template() . '//' . $slug,
			sanitize_title( get_stylesheet() . '-' . $slug ), sanitize_title( get_template() . '-' . $slug ), $raw_id,
		) ) ) );

		$clean_html = self::clean_html_body( $html );

		if ( '' === trim( $css_param ) && preg_match_all( '/<style\b[^>]*>([\s\S]*?)<\/style>/is', $clean_html, $css_m ) ) {
			$css_param = trim( implode( "\n\n", array_filter( array_map( 'trim', $css_m[1] ) ) ) );
		}
		if ( '' === trim( $js_param ) && preg_match_all( '/<script\b[^>]*>([\s\S]*?)<\/script>/is', $clean_html, $js_m ) ) {
			$js_param = trim( implode( "\n\n", array_filter( array_map( 'trim', $js_m[1] ) ) ) );
		}
		$clean_html = preg_replace( array( '/<style\b[^>]*>[\s\S]*?<\/style>/is', '/<script\b[^>]*>[\s\S]*?<\/script>/is' ), '', $clean_html );

		$existing_content = '';
		if ( $is_template ) {
			$found_posts = get_posts( array( 'post_type' => 'wp_template', 'post_status' => 'any', 'posts_per_page' => 1, 'post_name__in' => $search_slugs ) );
			if ( ! empty( $found_posts ) ) {
				$existing_content = $found_posts[0]->post_content;
			} else {
				$disk_file = get_stylesheet_directory() . '/templates/' . $slug . '.html';
				if ( file_exists( $disk_file ) ) $existing_content = (string) file_get_contents( $disk_file );
			}
			$clean_html = self::restore_post_content_block( $clean_html, Align_Builder_Template_Structure::find_post_content_block( $existing_content ) );
		}

		$prefix          = $is_template ? 'template' : 'part';
		$style_id          = 'style-' . $prefix . '-' . $slug;
		$script_id         = 'script-' . $prefix . '-' . $slug;
		$legacy_style_ids  = array( 'align-style-' . $prefix . '-' . $slug );
		$legacy_script_ids = array( 'align-script-' . $prefix . '-' . $slug );

		if ( class_exists( 'Align_Builder_Assets' ) ) {
			Align_Builder_Assets::save_external_assets( $style_id, $script_id, $css_param, $js_param, $legacy_style_ids, $legacy_script_ids );
		}

		$block_markup    = self::package_custom_html_block( $clean_html, '', '', $style_id, $script_id );
		$content_to_save = $block_markup;

		if ( $is_template ) {
			$content_to_save = Align_Builder_Template_Structure::wrap( $block_markup, $existing_content, $slug );
		}

		$found_posts = get_posts( array(
			'post_type' => $post_type_target, 'post_status' => 'any', 'posts_per_page' => 1, 'post_name__in' => $search_slugs,
			'tax_query' => array( array( 'taxonomy' => 'wp_theme', 'field' => 'slug', 'terms' => array( get_stylesheet(), get_template() ) ) ),
		) );
		if ( empty( $found_posts ) ) {
			$found_posts = get_posts( array( 'post_type' => $post_type_target, 'post_status' => 'any', 'posts_per_page' => 1, 'post_name__in' => $search_slugs ) );
		}

		self::$is_syncing_assets = true;
		kses_remove_filters();
		$saved_tp_post_id = ! empty( $found_posts ) ? $found_posts[0]->ID : null;
		if ( $saved_tp_post_id ) {
			$result = wp_update_post( array( 'ID' => $saved_tp_post_id, 'post_name' => $slug, 'post_content' => wp_slash( $content_to_save ) ), true );
		} else {
			$saved_tp_post_id = wp_insert_post( array(
				'post_name' => $slug, 'post_title' => ucfirst( str_replace( '-', ' ', $slug ) ), 'post_content' => wp_slash( $content_to_save ),
				'post_type' => $post_type_target, 'post_status' => 'publish',
			), true );
			$result = $saved_tp_post_id;
		}
		if ( ! is_wp_error( $result ) && $saved_tp_post_id ) {
			wp_set_object_terms( $saved_tp_post_id, get_stylesheet(), 'wp_theme' );
			if ( 'wp_template_part' === $post_type_target ) {
				$area = str_starts_with( $slug, 'header' ) ? 'header' : ( str_starts_with( $slug, 'footer' ) ? 'footer' : ( in_array( $slug, array( 'sidebar', 'comments' ), true ) ? $slug : 'uncategorized' ) );
				wp_set_object_terms( $saved_tp_post_id, $area, 'wp_template_part_area' );
			}
		}
		kses_init_filters();
		self::$is_syncing_assets = false;

		if ( is_wp_error( $result ) ) {
			error_log( sprintf( '[Align Persistence] [ERROR] Template DB update failed for %s "%s": %s', $post_type_target, $slug, $result->get_error_message() ) );
			return array( 'status' => 'error', 'message' => $result->get_error_message() );
		}

		$sub_dir     = $is_template ? 'templates' : 'parts';
		$target_dir  = get_stylesheet_directory() . '/' . $sub_dir;
		$target_file = $target_dir . '/' . $slug . '.html';
		$disk_saved  = self::atomic_file_put_contents( $target_file, $content_to_save );

		if ( isset( $reusable_overrides ) && $saved_tp_post_id ) {
			if ( class_exists( 'Align_Reusable_Block' ) ) {
				$reusable_overrides = Align_Reusable_Block::sanitize_overrides( $reusable_overrides );
			} elseif ( is_string( $reusable_overrides ) ) {
				$reusable_overrides = json_decode( $reusable_overrides, true );
			}
			if ( is_array( $reusable_overrides ) ) {
				update_post_meta( $saved_tp_post_id, '_align_reusable_overrides', $reusable_overrides );
			}
		}

		if ( function_exists( 'wp_clean_theme_json_cache' ) ) wp_clean_theme_json_cache();
		if ( $saved_tp_post_id ) clean_post_cache( $saved_tp_post_id );

		$label = $is_template ? __( 'Template', 'align' ) : __( 'Template Part', 'align' );
		return array(
			'status'             => 'success',
			'tp_id'              => $tp_id,
			'slug'               => $slug,
			'target_file'        => $target_file,
			'disk_saved'         => $disk_saved,
			'version'            => time(),
			'reusable_overrides' => is_array( $reusable_overrides ) ? $reusable_overrides : array(),
			'message'            => sprintf( __( '%s "%s" saved successfully to WP Database!', 'align' ), $label, $slug ),
		);
	}

	/** Hook into save_post to synchronize assets when native Gutenberg saves a builder page. */
	public static function handle_save_post( $post_id, $post, $update ) {
		if ( self::$is_syncing_assets ) return;
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) return;
		if ( ! $post || empty( $post->post_content ) ) return;
		if ( false === strpos( $post->post_content, '<!-- wp:align/custom-html' ) ) return;

		self::sync_post_assets_from_content( $post_id, $post );
	}

	/** Mirror saved template content to its disk file; Builder blocks also get scoped CSS/JS extracted. */
	private static function mirror_template_to_disk( string $content, string $slug, string $type ) {
		$has_builder = false !== strpos( $content, '<!-- wp:align/custom-html' );
		$sub_dir     = ( 'template' === $type ) ? 'templates' : 'parts';
		$target_file = get_stylesheet_directory() . '/' . $sub_dir . '/' . $slug . '.html';
		$disk        = $has_builder
			? preg_replace( array( '/<style\b[^>]*>[\s\S]*?<\/style>/is', '/<script\b[^>]*>[\s\S]*?<\/script>/is' ), '', $content )
			: $content;
		self::atomic_file_put_contents( $target_file, trim( (string) $disk ) );
		if ( $has_builder ) {
			self::sync_template_assets_from_content( $content, $slug, $type );
		}
		error_log( sprintf( '[Align Persistence] Synced %s "%s" from REST to disk file: %s (%d bytes, builder=%s)', $type, $slug, $target_file, strlen( $content ), $has_builder ? 'yes' : 'no' ) );
	}

	/** Hook into rest_after_insert_wp_template to sync external assets and disk template file. */
	public static function handle_rest_after_insert_template( $post_or_template, $request, $creating ) {
		if ( self::$is_syncing_assets ) return;
		$content = is_object( $post_or_template ) ? (string) ( $post_or_template->post_content ?? $post_or_template->content ?? '' ) : '';

		$slug = is_object( $post_or_template ) ? ( $post_or_template->post_name ?? $post_or_template->slug ?? '' ) : '';
		if ( empty( $slug ) && is_object( $request ) ) {
			$req_id = $request->get_param( 'id' ) ?: $request->get_param( 'slug' ) ?: '';
			$slug = ( strpos( $req_id, '//' ) !== false ) ? explode( '//', $req_id )[1] : $req_id;
		}
		$slug = sanitize_file_name( $slug );
		if ( empty( $slug ) ) return;

		self::mirror_template_to_disk( $content, $slug, 'template' );
	}

	/** Hook into rest_after_insert_wp_template_part to sync external assets and disk part file. */
	public static function handle_rest_after_insert_template_part( $post_or_part, $request, $creating ) {
		if ( self::$is_syncing_assets ) return;
		$content = is_object( $post_or_part ) ? (string) ( $post_or_part->post_content ?? $post_or_part->content ?? '' ) : '';

		$slug = is_object( $post_or_part ) ? ( $post_or_part->post_name ?? $post_or_part->slug ?? '' ) : '';
		if ( empty( $slug ) && is_object( $request ) ) {
			$req_id = $request->get_param( 'id' ) ?: $request->get_param( 'slug' ) ?: '';
			$slug = ( strpos( $req_id, '//' ) !== false ) ? explode( '//', $req_id )[1] : $req_id;
		}
		$slug = sanitize_file_name( $slug );
		if ( empty( $slug ) ) return;

		self::mirror_template_to_disk( $content, $slug, 'part' );
	}

	/** Extract scoped CSS and JS from template or template part content and write to external files. */
	public static function sync_template_assets_from_content( string $content, string $slug, string $type = 'template' ) {
		if ( class_exists( 'Align_Builder_Asset_Sync' ) ) {
			Align_Builder_Asset_Sync::sync_template_assets_from_content( $content, $slug, $type );
		}
	}

	/** Extract scoped CSS and JS from post_content and write to external files. */
	public static function sync_post_assets_from_content( int $post_id, $post ) {
		if ( class_exists( 'Align_Builder_Asset_Sync' ) ) {
			Align_Builder_Asset_Sync::sync_post_assets_from_content( $post_id, $post );
		}
	}
}
