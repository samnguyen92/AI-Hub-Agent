<?php
/**
 * Align Theme - Hreflang SEO Tags
 *
 * Injects <link rel="alternate" hreflang="..."> tags into the <head> for all
 * frontend pages that have multi-language counterparts.
 *
 * This is critical for Google to correctly index the EN and zh-HK versions of the
 * Trusted Union website as separate language targets, avoiding duplicate content
 * penalties and ensuring each language version ranks for the correct locale.
 *
 * Locale mapping uses IETF BCP 47 language tags (e.g. zh-HK, en, en-GB).
 * The x-default alternate always points to the English version.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Hreflang {

	/**
	 * Maps internal Align language slugs to IETF BCP 47 hreflang codes.
	 * Keys are the values stored in _align_lang post meta.
	 */
	const HREFLANG_MAP = array(
		'en'    => 'en',
		'vi'    => 'vi',
		'zh'    => 'zh-HK',
		'zh-hk' => 'zh-HK',
		'zh_hk' => 'zh-HK',
		'zh-tw' => 'zh-TW',
		'ja'    => 'ja',
		'ko'    => 'ko',
	);

	const OG_LOCALE_MAP = array(
		'en'    => 'en_US',
		'vi'    => 'vi_VN',
		'zh'    => 'zh_HK',
		'zh-hk' => 'zh_HK',
		'zh_hk' => 'zh_HK',
		'zh-tw' => 'zh_TW',
		'ja'    => 'ja_JP',
		'ko'    => 'ko_KR',
	);

	/**
	 * The language slug that is used as x-default (the international fallback).
	 */
	const DEFAULT_LANG = 'en';

	private static $request_lang_cache = null;

	public static function get_request_lang( bool $refresh = false ): string {
		if ( ! $refresh && null !== self::$request_lang_cache ) {
			return self::$request_lang_cache;
		}

		$default_lang = ( class_exists( 'Align_Language_Manager' ) && method_exists( 'Align_Language_Manager', 'get_default_lang' ) )
			? Align_Language_Manager::get_default_lang()
			: self::DEFAULT_LANG;

		$post_id = get_the_ID() ?: get_queried_object_id();
		if ( $post_id ) {
			$meta_lang = get_post_meta( $post_id, '_align_lang', true );
			if ( ! empty( $meta_lang ) ) {
				$val = strtolower( trim( $meta_lang ) );
				self::$request_lang_cache = $val;
				return $val;
			}
		}

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		if ( '' !== $uri && class_exists( 'Align_Language_Manager' ) ) {
			$langs = Align_Language_Manager::get_languages();
			foreach ( $langs as $l ) {
				$slug = strtolower( trim( $l['slug'] ?? ( $l['code'] ?? '' ) ) );
				if ( '' !== $slug && preg_match( '#^/' . preg_quote( $slug, '#' ) . '(?:/.*)?$#i', $uri ) ) {
					self::$request_lang_cache = $slug;
					return $slug;
				}
			}
		}

		// Do not permanently cache default fallback if queried object was not yet resolved
		if ( ! did_action( 'wp' ) && ! did_action( 'wp_head' ) && ! is_admin() ) {
			return $default_lang;
		}

		self::$request_lang_cache = $default_lang;
		return self::$request_lang_cache;
	}

	/**
	 * Register the wp_head hooks and language/locale filters.
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'output_hreflang_tags' ), 1 );
		add_action( 'wp_head', array( __CLASS__, 'output_og_locale_tags' ), 2 );

		// Rank Math OpenGraph Facebook locale filter
		add_filter( 'rank_math/opengraph/facebook/og_locale', array( __CLASS__, 'filter_og_locale' ) );

		// HTML lang attribute filter (e.g. lang="zh-HK")
		add_filter( 'language_attributes', array( __CLASS__, 'filter_language_attributes' ) );

		// WordPress Core locale filters
		add_filter( 'locale', array( __CLASS__, 'filter_locale' ) );
		add_filter( 'determine_locale', array( __CLASS__, 'filter_locale' ) );

		// Rank Math Schema JSON-LD inLanguage filter
		add_filter( 'rank_math/json_ld', array( __CLASS__, 'filter_json_ld' ), 99, 2 );
	}

	/**
	 * Determine if current frontend request is for Chinese (zh / zh-HK).
	 *
	 * @return bool
	 */
	public static function is_zh_request() {
		$l = self::get_request_lang();
		return ( 'zh' === $l || 'zh-hk' === $l || 'zh_hk' === $l );
	}

	/**
	 * Filter Rank Math Facebook OpenGraph locale.
	 *
	 * @param string $locale Current locale.
	 * @return string
	 */
	public static function filter_og_locale( $locale ) {
		$lang = self::get_request_lang();
		return self::OG_LOCALE_MAP[ $lang ] ?? ( $locale ?: 'en_US' );
	}

	/**
	 * Filter HTML root language attribute.
	 *
	 * @param string $output Current language attributes string.
	 * @return string
	 */
	public static function filter_language_attributes( $output ) {
		$default_lang = ( class_exists( 'Align_Language_Manager' ) && method_exists( 'Align_Language_Manager', 'get_default_lang' ) )
			? Align_Language_Manager::get_default_lang()
			: self::DEFAULT_LANG;

		$req_lang = self::get_request_lang();
		if ( $req_lang !== $default_lang ) {
			$mapped = self::map_hreflang( $req_lang );
			if ( $mapped ) {
				if ( preg_match( '/\blang=(["\']).*?\1/i', $output ) ) {
					return preg_replace( '/\blang=(["\']).*?\1/i', 'lang="' . esc_attr( $mapped ) . '"', $output );
				}
				return $output . ' lang="' . esc_attr( $mapped ) . '"';
			}
		}
		return $output;
	}

	/**
	 * Filter WordPress core locale for frontend requests.
	 *
	 * @param string $locale Current locale.
	 * @return string
	 */
	public static function filter_locale( $locale ) {
		if ( ! is_admin() ) {
			$req_lang = self::get_request_lang();
			if ( isset( self::OG_LOCALE_MAP[ $req_lang ] ) ) {
				return self::OG_LOCALE_MAP[ $req_lang ];
			}
		}
		return $locale;
	}

	/**
	 * Filter Rank Math JSON-LD Schema to ensure inLanguage is correct on localized pages.
	 *
	 * @param array $data    Schema graph data.
	 * @param mixed $context Schema context.
	 * @return array
	 */
	public static function filter_json_ld( $data, $context ) {
		$req_lang = self::get_request_lang();
		$mapped   = self::map_hreflang( $req_lang );
		if ( $mapped && is_array( $data ) ) {
			array_walk_recursive( $data, function( &$val, $key ) use ( $mapped ) {
				if ( 'inLanguage' === $key && ( 'en-US' === $val || 'en' === $val ) ) {
					$val = $mapped;
				}
			} );
		}
		return $data;
	}

	/**
	 * Output OpenGraph locale alternate tags and fallback og:locale if needed.
	 */
	public static function output_og_locale_tags() {
		$current_lang = self::get_request_lang();
		$current_og   = self::OG_LOCALE_MAP[ $current_lang ] ?? 'en_US';

		// Output alternates for configured languages
		$configured_langs = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_languages() : array();
		if ( empty( $configured_langs ) ) {
			$configured_langs = array( array( 'code' => 'en' ), array( 'code' => 'zh-hk' ) );
		}

		$emitted = array();
		foreach ( $configured_langs as $cfg ) {
			$c = strtolower( trim( $cfg['code'] ?? '' ) );
			if ( '' === $c || $c === $current_lang ) continue;
			$alt_og = self::OG_LOCALE_MAP[ $c ] ?? null;
			if ( $alt_og && ! isset( $emitted[ $alt_og ] ) && $alt_og !== $current_og ) {
				$emitted[ $alt_og ] = true;
				printf( '<meta property="og:locale:alternate" content="%s" />' . "\n", esc_attr( $alt_og ) );
			}
		}

		// Fallback og:locale if Rank Math OpenGraph is inactive
		if ( ! class_exists( '\RankMath\OpenGraph\Facebook' ) ) {
			printf( '<meta property="og:locale" content="%s" />' . "\n", esc_attr( $current_og ) );
		}
	}

	/**
	 * Output <link rel="alternate" hreflang="..."> tags for the current page.
	 *
	 * Only fires for singular posts/pages that have an _align_lang_group meta,
	 * indicating this page is part of a multi-language group.
	 */
	public static function output_hreflang_tags() {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		$group = get_post_meta( $post_id, '_align_lang_group', true );
		if ( empty( $group ) ) {
			return;
		}

		// Fetch all pages that belong to the same language group.
		$siblings = get_posts( array(
			'post_type'      => get_post_type( $post_id ),
			'posts_per_page' => 20,
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'   => '_align_lang_group',
					'value' => $group,
				),
			),
			'fields'         => 'ids',
		) );

		if ( empty( $siblings ) ) {
			return;
		}

		$alternates  = array();
		$default_url = '';

		foreach ( $siblings as $sibling_id ) {
			$sibling_lang = get_post_meta( $sibling_id, '_align_lang', true ) ?: self::DEFAULT_LANG;
			$sibling_url  = get_permalink( $sibling_id );

			if ( empty( $sibling_url ) ) {
				continue;
			}

			$hreflang = self::map_hreflang( $sibling_lang );
			if ( ! $hreflang ) {
				continue;
			}

			$alternates[ $hreflang ] = esc_url( $sibling_url );

			// Track the x-default (English) URL.
			if ( self::is_default_lang( $sibling_lang ) ) {
				$default_url = esc_url( $sibling_url );
			}
		}

		if ( empty( $alternates ) ) {
			return;
		}

		echo "\n<!-- [Align Hreflang] Multi-language alternate links -->\n";

		foreach ( $alternates as $hreflang => $url ) {
			printf(
				'<link rel="alternate" hreflang="%s" href="%s">' . "\n",
				esc_attr( $hreflang ),
				$url
			);
		}

		// Always output x-default pointing to the English version.
		if ( $default_url ) {
			printf(
				'<link rel="alternate" hreflang="x-default" href="%s">' . "\n",
				$default_url
			);
		}

		echo "<!-- [Align Hreflang] End -->\n\n";
	}

	/**
	 * Map an Align language slug to its IETF BCP 47 hreflang code.
	 *
	 * @param string $lang Align language slug (e.g. 'zh', 'en', 'zh-hk').
	 * @return string|false IETF hreflang code (e.g. 'zh-HK', 'en') or false if unmapped.
	 */
	public static function map_hreflang( $lang ) {
		$slug = strtolower( trim( (string) $lang ) );
		if ( isset( self::HREFLANG_MAP[ $slug ] ) ) {
			return self::HREFLANG_MAP[ $slug ];
		}
		// Fallback for valid BCP 47 code (e.g. fr, de, es-ES)
		if ( preg_match( '/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/i', $slug ) ) {
			$parts = explode( '-', $slug );
			$norm  = strtolower( array_shift( $parts ) );
			foreach ( $parts as $p ) {
				$norm .= '-' . ( 2 === strlen( $p ) ? strtoupper( $p ) : ucfirst( strtolower( $p ) ) );
			}
			return $norm;
		}
		return false;
	}

	/**
	 * Determine whether a language slug is the default (x-default) language.
	 *
	 * @param string $lang Language slug.
	 * @return bool
	 */
	private static function is_default_lang( $lang ) {
		$default_lang = ( class_exists( 'Align_Language_Manager' ) && method_exists( 'Align_Language_Manager', 'get_default_lang' ) )
			? Align_Language_Manager::get_default_lang()
			: self::DEFAULT_LANG;
		$slug = strtolower( trim( (string) $lang ) );
		return ( $slug === $default_lang || 0 === strpos( $slug, $default_lang . '-' ) );
	}
}
