<?php
/**
 * Align Theme - Template & Template Part Multi-Language Engine
 *
 * Resolves translated template parts (header-zh, footer-zh) and templates (single-zh, archive-zh)
 * based on the active frontend language, falling back to default English when untranslated.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Template_Language {

	/**
	 * Active language code on current request.
	 *
	 * @var string
	 */
	private static $current_lang = null;

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_filter( 'render_block_data', array( __CLASS__, 'filter_template_part_block_data' ), 10, 3 );
		add_filter( 'pre_get_block_template', array( __CLASS__, 'filter_block_template' ), 10, 3 );

		$hierarchy_types = array( 'single', 'page', 'archive', 'search', 'home', 'index', '404', 'singular', 'category', 'tag', 'taxonomy', 'author', 'date', 'frontpage' );
		foreach ( $hierarchy_types as $ht ) {
			add_filter( "{$ht}_template_hierarchy", array( __CLASS__, 'filter_template_hierarchy' ), 10, 1 );
		}
	}

	/**
	 * Prepend localized template slugs into WordPress template hierarchy.
	 *
	 * @param array $templates Array of template filenames/slugs.
	 * @return array
	 */
	public static function filter_template_hierarchy( array $templates ): array {
		$current_lang = self::get_current_lang();
		$default_lang = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en';
		if ( $current_lang === $default_lang ) {
			return $templates;
		}

		$localized = array();
		foreach ( $templates as $t ) {
			$base = preg_replace( '/\.(php|html)$/i', '', $t );
			if ( empty( $base ) || str_ends_with( $base, '-' . $current_lang ) ) {
				continue;
			}
			$localized[] = $base . '-' . $current_lang . '.html';
			$localized[] = $base . '-' . $current_lang . '.php';
			$localized[] = $base . '-' . $current_lang;
		}
		if ( ! empty( $localized ) ) {
			error_log( '[Align Core] Template hierarchy filtered for lang ' . $current_lang . ' | Added localized candidates: ' . count( $localized ) );
		}
		return array_merge( $localized, $templates );
	}

	/**
	 * Get the active frontend language for the current request.
	 *
	 * @return string Language code (e.g. 'zh' or 'en').
	 */
	public static function get_current_lang(): string {
		if ( null !== self::$current_lang ) {
			return self::$current_lang;
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
			self::$current_lang = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en';
			return self::$current_lang;
		}

		// 1. Check query param ?lang=
		if ( ! empty( $_GET['lang'] ) ) {
			self::$current_lang = sanitize_key( $_GET['lang'] );
			return self::$current_lang;
		}

		// 2. Check current post meta _align_lang
		$queried_id = get_queried_object_id();
		if ( $queried_id && class_exists( 'Align_Language_Manager' ) ) {
			$post_lang = Align_Language_Manager::get_post_lang( $queried_id );
			if ( ! empty( $post_lang ) ) {
				self::$current_lang = $post_lang;
				return self::$current_lang;
			}
		}

		// 3. Check request URI prefix (e.g. /zh/...)
		$uri = trim( $_SERVER['REQUEST_URI'] ?? '', '/' );
		$first_segment = explode( '/', $uri )[0] ?? '';
		if ( ! empty( $first_segment ) && class_exists( 'Align_Language_Manager' ) ) {
			$lang_cfg = Align_Language_Manager::get_language( $first_segment );
			if ( $lang_cfg && ! empty( $lang_cfg['slug'] ) && $lang_cfg['slug'] === $first_segment ) {
				self::$current_lang = $lang_cfg['code'];
				return self::$current_lang;
			}
		}

		// Default fallback
		self::$current_lang = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en';
		return self::$current_lang;
	}

	/**
	 * Intercept template part block rendering to swap slug to localized version.
	 * e.g. "header" -> "header-zh" if viewing Chinese page and "header-zh" exists.
	 *
	 * @param array $parsed_block Parsed block data.
	 * @param array $source_block Source block data.
	 * @param WP_Block|null $parent_block Parent block instance.
	 * @return array
	 */
	public static function filter_template_part_block_data( $parsed_block, $source_block, $parent_block ) {
		if ( 'core/template-part' !== ( $parsed_block['blockName'] ?? '' ) ) {
			return $parsed_block;
		}

		$current_lang = self::get_current_lang();
		$default_lang = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en';

		if ( $current_lang === $default_lang ) {
			return $parsed_block;
		}

		$slug = $parsed_block['attrs']['slug'] ?? '';
		if ( empty( $slug ) ) {
			return $parsed_block;
		}

		$localized_slug = $slug . '-' . $current_lang;

		// Check if translated template part exists in database or theme
		if ( self::template_part_exists( $localized_slug ) ) {
			$parsed_block['attrs']['slug'] = $localized_slug;
		} else if ( in_array( $slug, array( 'header', 'footer' ), true ) ) {
			$new_id = self::ensure_translated_template_part( $slug, $current_lang );
			if ( ! is_wp_error( $new_id ) ) {
				$parsed_block['attrs']['slug'] = $localized_slug;
			}
		}

		return $parsed_block;
	}

	/**
	 * Check if a template part with the given slug exists in the database or theme files.
	 *
	 * @param string $slug Template part slug.
	 * @return bool
	 */
	public static function template_part_exists( string $slug ): bool {
		// 1. Check in wp_template_part posts
		$found = get_posts( array(
			'post_type'      => 'wp_template_part',
			'name'           => $slug,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
		) );

		if ( ! empty( $found ) ) {
			return true;
		}

		// 2. Check in child/parent theme parts/ directory
		$theme_path = get_stylesheet_directory() . '/parts/' . $slug . '.html';
		if ( file_exists( $theme_path ) ) {
			return true;
		}

		$parent_path = get_template_directory() . '/parts/' . $slug . '.html';
		return file_exists( $parent_path );
	}

	private static bool $is_filtering_template = false;

	/**
	 * Intercept block templates (e.g. single -> single-zh) if localized version exists.
	 */
	public static function filter_block_template( $template, $id, $template_type ) {
		if ( null !== $template || self::$is_filtering_template ) return $template;

		$current_lang = self::get_current_lang();
		$default_lang = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en';
		if ( $current_lang === $default_lang ) return null;

		// Extract slug from template id (e.g. "align//single")
		$parts = explode( '//', $id );
		$slug  = end( $parts );
		if ( empty( $slug ) || str_ends_with( $slug, '-' . $current_lang ) ) return null;

		$localized_slug = $slug . '-' . $current_lang;
		$localized_id   = ( count( $parts ) > 1 ? $parts[0] . '//' : '' ) . $localized_slug;

		self::$is_filtering_template = true;
		$custom_template = get_block_template( $localized_id, $template_type );
		if ( ! $custom_template && in_array( $slug, array( 'page', 'single', '404', 'archive', 'index', 'search' ), true ) ) {
			$new_id = self::ensure_translated_template( $slug, $current_lang );
			if ( ! is_wp_error( $new_id ) ) {
				$custom_template = get_block_template( $localized_id, $template_type );
			}
		}
		self::$is_filtering_template = false;

		return $custom_template ?: null;
	}

	/**
	 * Check if a block template with the given slug exists in DB or theme files.
	 */
	public static function template_exists( string $slug ): bool {
		$found = get_posts( array(
			'post_type'      => 'wp_template',
			'name'           => $slug,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
		) );
		if ( ! empty( $found ) ) return true;

		$child_path = get_stylesheet_directory() . '/templates/' . $slug . '.html';
		return file_exists( $child_path ) || file_exists( get_template_directory() . '/templates/' . $slug . '.html' );
	}

	/**
	 * Ensure that a translated block template (page, single, 404, archive) exists.
	 */
	public static function ensure_translated_template( string $base_slug, string $target_lang ) {
		$localized_slug = $base_slug . '-' . $target_lang;

		$existing = get_posts( array(
			'post_type'      => 'wp_template',
			'name'           => $localized_slug,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
		) );
		if ( ! empty( $existing ) ) return (int) $existing[0];

		// Fetch base content
		$base_content = '';
		$base_posts   = get_posts( array(
			'post_type'      => 'wp_template',
			'name'           => $base_slug,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 1,
		) );
		if ( ! empty( $base_posts ) && ! empty( $base_posts[0]->post_content ) ) {
			$base_content = $base_posts[0]->post_content;
		} else {
			$child_file  = get_stylesheet_directory() . '/templates/' . $base_slug . '.html';
			$parent_file = get_template_directory() . '/templates/' . $base_slug . '.html';
			$target_file = file_exists( $child_file ) ? $child_file : ( file_exists( $parent_file ) ? $parent_file : '' );
			if ( ! empty( $target_file ) ) $base_content = file_get_contents( $target_file );
		}

		if ( empty( trim( $base_content ) ) ) {
			$base_content = self::get_default_template_markup( $base_slug );
		}
		if ( empty( trim( $base_content ) ) ) {
			return new WP_Error( 'base_not_found', "Base template '{$base_slug}' content not found." );
		}

		$translated_content = self::translate_html_text_nodes( $base_content, $target_lang );

		if ( class_exists( 'Align_Language_Url_Localizer' ) ) {
			$translated_content = Align_Language_Url_Localizer::localize_html( $translated_content, $target_lang );
		}

		// Localize embedded template parts (header -> header-zh, footer -> footer-zh)
		$translated_content = preg_replace( '/("slug"\s*:\s*")(header|footer)(")/', '$1$2-' . $target_lang . '$3', $translated_content );
		self::ensure_translated_template_part( 'header', $target_lang );
		self::ensure_translated_template_part( 'footer', $target_lang );

		// Write to theme templates/ directory if writable
		$tpl_dir = get_stylesheet_directory() . '/templates';
		if ( is_dir( $tpl_dir ) && is_writable( $tpl_dir ) ) {
			@file_put_contents( $tpl_dir . '/' . $localized_slug . '.html', $translated_content );
		}

		if ( function_exists( 'kses_remove_filters' ) ) kses_remove_filters();
		$post_id = wp_insert_post( array(
			'post_name'    => $localized_slug,
			'post_title'   => ucfirst( $base_slug ) . ' (' . strtoupper( $target_lang ) . ')',
			'post_content' => wp_slash( $translated_content ),
			'post_status'  => 'publish',
			'post_type'    => 'wp_template',
		) );
		if ( function_exists( 'kses_init_filters' ) ) kses_init_filters();

		if ( ! is_wp_error( $post_id ) && $post_id > 0 ) {
			wp_set_object_terms( $post_id, get_stylesheet(), 'wp_theme' );
			update_post_meta( $post_id, '_align_lang', $target_lang );
			if ( function_exists( 'wp_clean_theme_json_cache' ) ) wp_clean_theme_json_cache();
			error_log( sprintf( '[Align Template Lang] Auto-generated block template "%s" (ID: %d, lang: %s)', $localized_slug, $post_id, $target_lang ) );
		}

		return $post_id;
	}

	/**
	 * Translates text nodes in raw HTML using Align_Builder_Translator.
	 */
	public static function translate_html_text_nodes( string $html, string $target_lang ): string {
		if ( ! class_exists( 'Align_Builder_Translator' ) || ! class_exists( 'Align_AI_Connection' ) ) {
			return $html;
		}

		$dom = new DOMDocument( '1.0', 'UTF-8' );
		libxml_use_internal_errors( true );
		$wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';
		if ( ! $dom->loadHTML( $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD ) ) {
			return $html;
		}
		libxml_clear_errors();

		$xpath = new DOMXPath( $dom );
		$nodes = $xpath->query( '//text()[not(ancestor::script) and not(ancestor::style) and not(ancestor::code) and not(ancestor::pre)]' );
		$texts = array();
		$map   = array();

		foreach ( $nodes as $node ) {
			$val = trim( $node->nodeValue );
			if ( mb_strlen( $val ) >= 2 && ! preg_match( '/^[\s\d\W]+$/u', $val ) ) {
				$texts[] = $val;
				$map[]   = $node;
			}
		}

		if ( ! empty( $texts ) ) {
			$translated = Align_Builder_Translator::translate_batch( $texts, $target_lang, 'en' );
			if ( is_array( $translated ) && count( $translated ) === count( $texts ) ) {
				foreach ( $map as $idx => $dom_node ) {
					if ( ! empty( $translated[ $idx ] ) ) {
						$dom_node->nodeValue = $translated[ $idx ];
					}
				}
				$body = $dom->getElementsByTagName( 'body' )->item( 0 );
				if ( $body ) {
					$new_html = '';
					foreach ( $body->childNodes as $child ) {
						$new_html .= $dom->saveHTML( $child );
					}
					if ( ! empty( $new_html ) ) return $new_html;
				}
			}
		}

		return $html;
	}

	/**
	 * Ensure that a translated template part exists.
	 * If missing, duplicates base template part, translates text using AI connection if available,
	 * writes to theme parts/ directory and registers in wp_template_part post type.
	 *
	 * @param string $base_slug   Base slug (e.g. 'header', 'footer').
	 * @param string $target_lang Target language code (e.g. 'zh').
	 * @return int|WP_Error Post ID of created/existing template part or WP_Error.
	 */
	public static function ensure_translated_template_part( string $base_slug, string $target_lang ) {
		$localized_slug = $base_slug . '-' . $target_lang;

		// 1. Check if already exists in DB
		$existing = get_posts( array(
			'post_type'      => 'wp_template_part',
			'name'           => $localized_slug,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
		) );
		if ( ! empty( $existing ) ) {
			return (int) $existing[0];
		}

		// 2. Fetch base content from DB or theme parts/
		$base_content = '';
		$base_posts   = get_posts( array(
			'post_type'      => 'wp_template_part',
			'name'           => $base_slug,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 1,
		) );
		if ( ! empty( $base_posts ) && ! empty( $base_posts[0]->post_content ) ) {
			$base_content = $base_posts[0]->post_content;
		} else {
			$child_file  = get_stylesheet_directory() . '/parts/' . $base_slug . '.html';
			$parent_file = get_template_directory() . '/parts/' . $base_slug . '.html';
			$target_file = file_exists( $child_file ) ? $child_file : ( file_exists( $parent_file ) ? $parent_file : '' );
			if ( ! empty( $target_file ) ) {
				$base_content = file_get_contents( $target_file );
			}
		}

		if ( empty( trim( $base_content ) ) ) {
			return new WP_Error( 'base_not_found', "Base template part '{$base_slug}' content not found." );
		}

		// 3. Attempt AI translation of text nodes if available
		$translated_content = self::translate_html_text_nodes( $base_content, $target_lang );

		// Localize internal URLs (e.g. /contact/ -> /zh/contact/, CTAs, Nav links)
		if ( class_exists( 'Align_Language_Url_Localizer' ) ) {
			$translated_content = Align_Language_Url_Localizer::localize_html( $translated_content, $target_lang );
		}

		// 4. Save to theme parts/ directory
		$parts_dir = get_stylesheet_directory() . '/parts';
		if ( is_dir( $parts_dir ) && is_writable( $parts_dir ) ) {
			@file_put_contents( $parts_dir . '/' . $localized_slug . '.html', $translated_content );
		}

		// 5. Register in DB
		$area = str_starts_with( $base_slug, 'header' ) ? 'header' : ( str_starts_with( $base_slug, 'footer' ) ? 'footer' : 'uncategorized' );
		$title = ucfirst( $base_slug ) . ' (' . strtoupper( $target_lang ) . ')';

		if ( function_exists( 'kses_remove_filters' ) ) {
			kses_remove_filters();
		}

		$post_id = wp_insert_post( array(
			'post_name'    => $localized_slug,
			'post_title'   => $title,
			'post_content' => wp_slash( $translated_content ),
			'post_status'  => 'publish',
			'post_type'    => 'wp_template_part',
		) );

		if ( function_exists( 'kses_init_filters' ) ) {
			kses_init_filters();
		}

		if ( ! is_wp_error( $post_id ) && $post_id > 0 ) {
			wp_set_object_terms( $post_id, get_stylesheet(), 'wp_theme' );
			wp_set_object_terms( $post_id, $area, 'wp_template_part_area' );
			update_post_meta( $post_id, '_align_lang', $target_lang );

			if ( function_exists( 'wp_clean_theme_json_cache' ) ) {
				wp_clean_theme_json_cache();
			}
			error_log( sprintf( '[Align Template Lang] Auto-generated template part "%s" (ID: %d, lang: %s, area: %s)', $localized_slug, $post_id, $target_lang, $area ) );
			return $post_id;
		}

		return $post_id;
	}

	/**
	 * Default fallback block markup for standard FSE templates (404, page, single).
	 */
	private static function get_default_template_markup( string $slug ): string {
		if ( '404' === $slug ) {
			return '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->' . "\n" .
				'<main class="wp-block-group align-404" style="padding:80px 20px;text-align:center;">' . "\n" .
				'<!-- wp:heading {"level":1,"textAlign":"center"} -->' . "\n" .
				'<h1 class="wp-block-heading has-text-align-center">Page Not Found</h1><!-- /wp:heading -->' . "\n" .
				'<!-- wp:paragraph {"align":"center"} -->' . "\n" .
				'<p class="has-text-align-center">The page you are looking for might have been removed or is temporarily unavailable.</p><!-- /wp:paragraph -->' . "\n" .
				'<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->' . "\n" .
				'<div class="wp-block-buttons"><!-- wp:button -->' . "\n" .
				'<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/">Back to Home</a></div>' . "\n" .
				'<!-- /wp:button --></div><!-- /wp:buttons -->' . "\n" .
				'</main>' . "\n" .
				'<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';
		}
		if ( 'page' === $slug ) {
			return '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->' . "\n" .
				'<main class="wp-block-group"><!-- wp:post-title {"level":1} /--><!-- wp:post-content {"layout":{"type":"constrained"}} /--></main>' . "\n" .
				'<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';
		}
		if ( 'single' === $slug ) {
			return '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->' . "\n" .
				'<main class="wp-block-group"><!-- wp:post-title {"level":1} /--><!-- wp:post-featured-image /--><!-- wp:post-content {"layout":{"type":"constrained"}} /--></main>' . "\n" .
				'<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';
		}
		return '';
	}
}


