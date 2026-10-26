<?php
/**
 * Align Theme - Language URL Localizer Engine
 *
 * Automatically localizes internal links within Pages, Posts, Template Parts (Header/Footer),
 * and Reusable Blocks (CTAs) to point to their translated counterparts.
 *
 * Example:
 * - On a Chinese ('zh') page or template part:
 *   - '/contact/'                 => '/zh/contact/' (or translated page URL)
 *   - 'https://site.com/contact/' => 'https://site.com/zh/contact/'
 *   - '/insights/some-post/'      => '/zh/insights/some-post-zh/'
 *   - '/' (home)                  => '/zh/'
 *
 * Adheres strictly to Align coding standards & < 500 lines limit.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Language_Url_Localizer {

	/**
	 * Cache of resolved URL mappings to avoid redundant queries in same request.
	 *
	 * @var array
	 */
	private static $url_cache = array();

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		// Filter rendered template parts on frontend (Header, Footer, CTAs)
		add_filter( 'render_block_core/template-part', array( __CLASS__, 'filter_template_part_render' ), 20, 2 );

		// Filter content on frontend
		add_filter( 'the_content', array( __CLASS__, 'filter_frontend_content' ), 25 );
	}

	/**
	 * Localize internal links inside rendered template part (e.g. Header, Footer).
	 *
	 * @param string $block_content Rendered HTML.
	 * @param array  $parsed_block  Block data.
	 * @return string Localized HTML.
	 */
	public static function filter_template_part_render( $block_content, $parsed_block ) {
		if ( empty( $block_content ) || ! class_exists( 'Align_Template_Language' ) ) {
			return $block_content;
		}

		$current_lang = Align_Template_Language::get_current_lang();
		$default_lang = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en';

		if ( $current_lang === $default_lang || empty( $current_lang ) ) {
			return $block_content;
		}

		return self::localize_html( $block_content, $current_lang );
	}

	/**
	 * Localize internal links in frontend content for non-default languages.
	 *
	 * @param string $content Post content.
	 * @return string Localized content.
	 */
	public static function filter_frontend_content( $content ) {
		if ( empty( $content ) || is_admin() || ! class_exists( 'Align_Template_Language' ) ) {
			return $content;
		}

		$current_lang = Align_Template_Language::get_current_lang();
		$default_lang = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en';

		if ( $current_lang === $default_lang || empty( $current_lang ) ) {
			return $content;
		}

		return self::localize_html( $content, $current_lang );
	}

	/**
	 * Localize all internal href attributes within an HTML markup string.
	 *
	 * @param string $html        HTML content.
	 * @param string $target_lang Target language code (e.g. 'zh').
	 * @return string Localized HTML.
	 */
	public static function localize_html( $html, $target_lang ) {
		if ( empty( $html ) || empty( $target_lang ) ) {
			return $html;
		}

		$default_lang = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en';
		if ( $target_lang === $default_lang ) {
			return $html;
		}

		// 1. Temporarily isolate & protect language switcher markup and elements marked data-no-localize
		$protected_blocks = array();

		// Match switcher containers (div, nav, ul)
		$html = preg_replace_callback(
			'#<(div|nav|ul)\b[^>]*\balign-lang-switcher\b[^>]*>.*?</\1>#is',
			function( $m ) use ( &$protected_blocks ) {
				$key = '%%ALIGN_PROTECTED_BLOCK_' . count( $protected_blocks ) . '%%';
				$protected_blocks[ $key ] = $m[0];
				return $key;
			},
			$html
		);

		// Match any element explicitly flagged with data-no-localize
		$html = preg_replace_callback(
			'#<([a-z0-9]+)\b[^>]*\bdata-no-localize\b[^>]*>.*?</\1>#is',
			function( $m ) use ( &$protected_blocks ) {
				$key = '%%ALIGN_PROTECTED_BLOCK_' . count( $protected_blocks ) . '%%';
				$protected_blocks[ $key ] = $m[0];
				return $key;
			},
			$html
		);

		// 2. Regex match and localize remaining href="..." and href='...'
		$pattern = '/\bhref=(["\'])([^"\']+)\1/i';

		$html = preg_replace_callback( $pattern, function( $matches ) use ( $target_lang ) {
			$quote = $matches[1];
			$url   = trim( $matches[2] );

			$localized_url = self::localize_url( $url, $target_lang );
			return 'href=' . $quote . esc_url( $localized_url ) . $quote;
		}, $html );

		// 3. Restore protected language switcher and no-localize elements
		if ( ! empty( $protected_blocks ) ) {
			$html = strtr( $html, $protected_blocks );
		}

		return $html;
	}

	/**
	 * Localize a single URL into the target language.
	 *
	 * @param string $url         Original URL or path.
	 * @param string $target_lang Target language code (e.g. 'zh').
	 * @return string Localized URL.
	 */
	public static function localize_url( $url, $target_lang ) {
		if ( empty( $url ) || empty( $target_lang ) ) {
			return $url;
		}

		// Skip anchors, mailto, tel, javascript, or external protocols
		if ( str_starts_with( $url, '#' ) ||
		     str_starts_with( $url, 'mailto:' ) ||
		     str_starts_with( $url, 'tel:' ) ||
		     str_starts_with( $url, 'javascript:' ) ) {
			return $url;
		}

		$home_url   = home_url();
		$is_absolute = ( 0 === strpos( $url, 'http://' ) || 0 === strpos( $url, 'https://' ) );

		// If absolute URL, ensure it matches home domain
		if ( $is_absolute && 0 !== strpos( $url, $home_url ) ) {
			return $url; // External domain link, do not touch
		}

		// Cache check
		$cache_key = $target_lang . '|' . $url;
		if ( isset( self::$url_cache[ $cache_key ] ) ) {
			return self::$url_cache[ $cache_key ];
		}

		// Resolve target lang prefix/slug (e.g. 'zh')
		$lang_cfg = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_language( $target_lang ) : null;
		$lang_slug = ( $lang_cfg && ! empty( $lang_cfg['slug'] ) ) ? $lang_cfg['slug'] : $target_lang;

		// Extract path, query, fragment
		$parsed = wp_parse_url( $url );
		$path   = $parsed['path'] ?? '/';
		$query  = isset( $parsed['query'] ) ? '?' . $parsed['query'] : '';
		$frag   = isset( $parsed['fragment'] ) ? '#' . $parsed['fragment'] : '';

		// If path already starts with /{$lang_slug}/, already localized
		if ( preg_match( '#^/' . preg_quote( $lang_slug, '#' ) . '(/|$)#', $path ) ) {
			self::$url_cache[ $cache_key ] = $url;
			return $url;
		}

		// Clean path for lookup (remove leading/trailing slashes)
		$clean_path = trim( $path, '/' );

		// 1. Home page link: '/' => '/zh/'
		if ( empty( $clean_path ) ) {
			$new_url = $is_absolute ? ( trailingslashit( $home_url ) . $lang_slug . '/' ) : ( '/' . $lang_slug . '/' );
			$res = $new_url . $query . $frag;
			self::$url_cache[ $cache_key ] = $res;
			return $res;
		}

		// 2. Try to find source post/page
		$source_post = null;

		// Try page by path (e.g. 'contact', 'about-us')
		$page = get_page_by_path( $clean_path, OBJECT, 'page' );
		if ( $page ) {
			$source_post = $page;
		} else {
			// Try post by slug (e.g. 'insights/critical-illness' or 'critical-illness')
			$path_parts = explode( '/', $clean_path );
			$slug = end( $path_parts );

			$posts = get_posts( array(
				'name'           => $slug,
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			) );

			if ( ! empty( $posts ) ) {
				$source_post = $posts[0];
			}
		}

		$localized_path = '';

		if ( $source_post && class_exists( 'Align_Language_Manager' ) ) {
			// Find existing translation in target language
			$translations = Align_Language_Manager::get_translations( $source_post->ID );
			$translated_id = 0;

			foreach ( $translations as $tr ) {
				if ( $tr['lang'] === $target_lang && (int) $tr['post_id'] !== (int) $source_post->ID ) {
					$translated_id = (int) $tr['post_id'];
					break;
				}
			}

			if ( $translated_id ) {
				$trans_permalink = get_permalink( $translated_id );
				if ( $trans_permalink ) {
					$trans_parsed = wp_parse_url( $trans_permalink );
					$localized_path = $trans_parsed['path'] ?? '';
				}
			}
		}

		// Fallback: If no translated post exists yet, prefix with lang slug
		if ( empty( $localized_path ) ) {
			$localized_path = '/' . $lang_slug . '/' . $clean_path . '/';
		}

		$final_url = $is_absolute
			? ( untrailingslashit( $home_url ) . $localized_path )
			: $localized_path;

		$final_url .= $query . $frag;

		self::$url_cache[ $cache_key ] = $final_url;
		return $final_url;
	}

	/**
	 * Recursively traverses parsed block tree to localize internal URLs in attributes & innerHTML.
	 *
	 * @param array  $blocks      Array of parsed blocks (by reference).
	 * @param string $target_lang Target language code.
	 */
	public static function localize_blocks( &$blocks, $target_lang ) {
		if ( empty( $blocks ) || ! is_array( $blocks ) || empty( $target_lang ) ) {
			return;
		}

		foreach ( $blocks as &$b ) {
			$name = $b['blockName'] ?? '';

			// 1. Localize URL in block attributes
			if ( ! empty( $b['attrs'] ) && is_array( $b['attrs'] ) ) {
				if ( ! empty( $b['attrs']['url'] ) && is_string( $b['attrs']['url'] ) ) {
					$b['attrs']['url'] = self::localize_url( $b['attrs']['url'], $target_lang );
				}
				if ( ! empty( $b['attrs']['href'] ) && is_string( $b['attrs']['href'] ) ) {
					$b['attrs']['href'] = self::localize_url( $b['attrs']['href'], $target_lang );
				}
			}

			// 2. Localize links in innerHTML
			if ( ! empty( $b['innerHTML'] ) ) {
				$b['innerHTML'] = self::localize_html( $b['innerHTML'], $target_lang );
			}

			// 3. Localize innerContent array
			if ( ! empty( $b['innerContent'] ) && is_array( $b['innerContent'] ) ) {
				foreach ( $b['innerContent'] as &$chunk ) {
					if ( is_string( $chunk ) && ! empty( $chunk ) ) {
						$chunk = self::localize_html( $chunk, $target_lang );
					}
				}
			}

			// 4. Recurse into innerBlocks
			if ( ! empty( $b['innerBlocks'] ) && is_array( $b['innerBlocks'] ) ) {
				self::localize_blocks( $b['innerBlocks'], $target_lang );
			}
		}
	}
}
