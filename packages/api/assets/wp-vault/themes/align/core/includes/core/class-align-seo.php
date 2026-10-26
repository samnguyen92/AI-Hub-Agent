<?php
/**
 * Align Theme - Native Default SEO Engine
 *
 * Stores and prints native _align_seo_* post meta tags in <head> when neither
 * Rank Math (RANK_MATH_VERSION) nor Yoast SEO (WPSEO_VERSION) is active.
 * Provides 'align_seo_fields' filter and 'align_seo_saved' action hooks.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Seo {

	/**
	 * Supported native SEO post meta keys.
	 */
	const META_KEYS = array(
		'title'          => '_align_seo_title',
		'description'    => '_align_seo_description',
		'focus_keyword'  => '_align_seo_focus_keyword',
		'og_title'       => '_align_seo_og_title',
		'og_description' => '_align_seo_og_description',
		'og_image_id'    => '_align_seo_og_image_id',
		'noindex'        => '_align_seo_noindex',
	);

	/**
	 * Initialize hooks for frontend <head> output and document title filtering.
	 */
	public static function init(): void {
		add_action( 'after_setup_theme', array( __CLASS__, 'ensure_theme_support' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'output_head_tags' ), 5 );
		add_filter( 'pre_get_document_title', array( __CLASS__, 'filter_pre_get_document_title' ), 20 );
		add_filter( 'document_title_parts', array( __CLASS__, 'filter_document_title_parts' ), 20 );
		add_filter( 'wp_title', array( __CLASS__, 'filter_wp_title' ), 20, 2 );
	}

	/**
	 * Ensure post-thumbnails and title-tag support are active for SEO resolution.
	 */
	public static function ensure_theme_support(): void {
		if ( ! current_theme_supports( 'post-thumbnails' ) ) {
			add_theme_support( 'post-thumbnails' );
		}
		if ( ! current_theme_supports( 'title-tag' ) ) {
			add_theme_support( 'title-tag' );
		}
	}

	/**
	 * Check whether Rank Math or Yoast SEO is currently active.
	 *
	 * @return bool
	 */
	public static function has_external_seo_plugin(): bool {
		return defined( 'RANK_MATH_VERSION' ) || defined( 'WPSEO_VERSION' );
	}

	/**
	 * Check whether a post has at least one _align_seo_* meta value saved.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return bool
	 */
	public static function has_align_seo_meta( int $post_id ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}
		foreach ( self::META_KEYS as $meta_key ) {
			$val = get_post_meta( $post_id, $meta_key, true );
			if ( '' !== (string) $val && '0' !== (string) $val ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Determine if Align_Seo should output tags for the given post ID.
	 * Condition: singular page/post with _align_seo_* meta AND no Rank Math / Yoast active.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return bool
	 */
	public static function should_output_for_post( int $post_id ): bool {
		if ( self::has_external_seo_plugin() ) {
			return false;
		}
		return self::has_align_seo_meta( $post_id );
	}

	/**
	 * Save native _align_seo_* post meta and fire 'align_seo_saved' action.
	 *
	 * @param int   $post_id WordPress post ID.
	 * @param array $args    SEO field values (title, description, focus_keyword, og_title, og_description, og_image_id, noindex).
	 * @return array|WP_Error Array of saved keys and values, or WP_Error on failure.
	 */
	public static function save_meta( int $post_id, array $args ) {
		$post = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', sprintf( 'Post #%d was not found.', $post_id ) );
		}

		$saved = array();

		$text_fields = array( 'title', 'description', 'focus_keyword', 'og_title', 'og_description' );
		foreach ( $text_fields as $field ) {
			if ( array_key_exists( $field, $args ) && null !== $args[ $field ] ) {
				$clean = sanitize_text_field( (string) $args[ $field ] );
				update_post_meta( $post_id, self::META_KEYS[ $field ], $clean );
				$saved[ $field ] = $clean;
			}
		}

		if ( array_key_exists( 'og_image_id', $args ) && null !== $args['og_image_id'] ) {
			$img_id = absint( $args['og_image_id'] );
			update_post_meta( $post_id, self::META_KEYS['og_image_id'], $img_id );
			$saved['og_image_id'] = $img_id;
		}

		if ( array_key_exists( 'noindex', $args ) && null !== $args['noindex'] ) {
			$noindex = ! empty( $args['noindex'] ) ? '1' : '0';
			update_post_meta( $post_id, self::META_KEYS['noindex'], $noindex );
			$saved['noindex'] = ( '1' === $noindex );
		}

		clean_post_cache( $post_id );
		error_log( sprintf( '[Align Core] Saved Align_Seo meta for post #%d (%s).', $post_id, implode( ', ', array_keys( $saved ) ) ) );

		/**
		 * Fires after Align_Seo saves SEO meta for a post.
		 *
		 * @param int   $post_id Post ID.
		 * @param array $saved   Saved field pairs.
		 */
		do_action( 'align_seo_saved', $post_id, $saved );

		return array(
			'post_id' => $post_id,
			'saved'   => $saved,
		);
	}

	/**
	 * Resolve all SEO field values for a post with fallback hierarchy.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return array<string, mixed>
	 */
	public static function resolve_fields( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$site_name = trim( (string) get_bloginfo( 'name' ) );
		$raw_title = trim( (string) get_post_meta( $post_id, self::META_KEYS['title'], true ) );
		if ( '' !== $raw_title ) {
			$title = $raw_title;
		} else {
			$post_title = trim( (string) $post->post_title );
			$title      = ( '' !== $site_name && '' !== $post_title )
				? $post_title . ' - ' . $site_name
				: ( '' !== $post_title ? $post_title : $site_name );
		}

		$raw_desc = trim( (string) get_post_meta( $post_id, self::META_KEYS['description'], true ) );
		if ( '' !== $raw_desc ) {
			$description = $raw_desc;
		} elseif ( '' !== trim( (string) $post->post_excerpt ) ) {
			$description = self::clean_excerpt_text( (string) $post->post_excerpt, 160 );
		} else {
			$rendered    = function_exists( 'do_blocks' ) ? do_blocks( (string) $post->post_content ) : (string) $post->post_content;
			$description = self::clean_excerpt_text( $rendered, 155 );
		}

		$raw_og_title = trim( (string) get_post_meta( $post_id, self::META_KEYS['og_title'], true ) );
		$og_title     = '' !== $raw_og_title ? $raw_og_title : $title;

		$raw_og_desc    = trim( (string) get_post_meta( $post_id, self::META_KEYS['og_description'], true ) );
		$og_description = '' !== $raw_og_desc ? $raw_og_desc : $description;

		$og_image_url = '';
		$og_image_id  = absint( get_post_meta( $post_id, self::META_KEYS['og_image_id'], true ) );
		if ( $og_image_id > 0 ) {
			$img_url = wp_get_attachment_image_url( $og_image_id, 'full' );
			if ( is_string( $img_url ) && '' !== $img_url ) {
				$og_image_url = self::ensure_absolute_url( $img_url );
			}
		}

		if ( '' === $og_image_url ) {
			$thumb_url = get_the_post_thumbnail_url( $post_id, 'full' );
			if ( is_string( $thumb_url ) && '' !== $thumb_url ) {
				$og_image_url = self::ensure_absolute_url( $thumb_url );
			}
		}

		$noindex = '1' === (string) get_post_meta( $post_id, self::META_KEYS['noindex'], true );
		$url     = (string) get_permalink( $post_id );

		$fields = array(
			'title'               => $title,
			'description'         => $description,
			'focus_keyword'       => trim( (string) get_post_meta( $post_id, self::META_KEYS['focus_keyword'], true ) ),
			'og_title'            => $og_title,
			'og_description'      => $og_description,
			'og_type'             => 'page' === $post->post_type ? 'website' : 'article',
			'og_url'              => $url,
			'og_image'            => $og_image_url,
			'twitter_card'        => '' !== $og_image_url ? 'summary_large_image' : 'summary',
			'twitter_title'       => $og_title,
			'twitter_description' => $og_description,
			'twitter_image'       => $og_image_url,
			'noindex'             => $noindex,
		);

		/**
		 * Filter resolved SEO fields before printing meta tags in <head>.
		 *
		 * @param array $fields  Resolved SEO key-value pairs.
		 * @param int   $post_id Current post ID.
		 */
		$filtered = apply_filters( 'align_seo_fields', $fields, $post_id );
		return is_array( $filtered ) ? $filtered : $fields;
	}

	/**
	 * Strip HTML/shortcodes and safely truncate UTF-8 text to $max_chars characters.
	 *
	 * @param string $raw       Raw HTML or text content.
	 * @param int    $max_chars Maximum UTF-8 character length (default 155).
	 * @return string
	 */
	public static function clean_excerpt_text( string $raw, int $max_chars = 155 ): string {
		$stripped = strip_shortcodes( $raw );
		$stripped = preg_replace( '/<style\b[^>]*>.*?<\/style>/is', ' ', $stripped );
		$stripped = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', ' ', $stripped );
		$stripped = wp_strip_all_tags( $stripped, true );
		$stripped = html_entity_decode( $stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$stripped = trim( (string) preg_replace( '/\s+/u', ' ', $stripped ) );

		if ( '' === $stripped ) {
			return '';
		}

		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			if ( mb_strlen( $stripped, 'UTF-8' ) > $max_chars ) {
				return trim( mb_substr( $stripped, 0, $max_chars, 'UTF-8' ) );
			}
			return $stripped;
		}

		return substr( $stripped, 0, $max_chars );
	}

	/**
	 * Convert a relative URL into an absolute site URL if needed.
	 *
	 * @param string $url Raw image or page URL.
	 * @return string
	 */
	private static function ensure_absolute_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		if ( 0 === strpos( $url, 'http://' ) || 0 === strpos( $url, 'https://' ) ) {
			return $url;
		}
		if ( 0 === strpos( $url, '//' ) ) {
			return ( is_ssl() ? 'https:' : 'http:' ) . $url;
		}
		return home_url( '/' . ltrim( $url, '/' ) );
	}

	/**
	 * Filter pre_get_document_title when Align_Seo is active for the current singular post.
	 *
	 * @param string $title Current document title.
	 * @return string
	 */
	public static function filter_pre_get_document_title( $title ) {
		if ( ! is_singular() ) {
			return $title;
		}
		$post_id = (int) get_queried_object_id();
		if ( ! self::should_output_for_post( $post_id ) ) {
			return $title;
		}
		$fields = self::resolve_fields( $post_id );
		return ! empty( $fields['title'] ) ? (string) $fields['title'] : $title;
	}

	/**
	 * Filter document_title_parts as fallback for core wp_get_document_title().
	 *
	 * @param array $parts Title parts array.
	 * @return array
	 */
	public static function filter_document_title_parts( $parts ) {
		if ( ! is_singular() || ! is_array( $parts ) ) {
			return $parts;
		}
		$post_id = (int) get_queried_object_id();
		if ( ! self::should_output_for_post( $post_id ) ) {
			return $parts;
		}
		$fields = self::resolve_fields( $post_id );
		if ( ! empty( $fields['title'] ) ) {
			$parts['title']   = (string) $fields['title'];
			$parts['site']    = '';
			$parts['tagline'] = '';
		}
		return $parts;
	}

	/**
	 * Filter legacy wp_title() calls for classic templates.
	 *
	 * @param string $title Current title.
	 * @param string $sep   Title separator.
	 * @return string
	 */
	public static function filter_wp_title( $title, $sep = '' ) {
		if ( ! is_singular() ) {
			return $title;
		}
		$post_id = (int) get_queried_object_id();
		if ( ! self::should_output_for_post( $post_id ) ) {
			return $title;
		}
		$fields = self::resolve_fields( $post_id );
		return ! empty( $fields['title'] ) ? (string) $fields['title'] : $title;
	}

	/**
	 * Output SEO, OpenGraph, and Twitter Card meta tags in <head>.
	 * Does NOT print og:locale, hreflang, meta keywords, or canonical (handled by WP core & Align_Hreflang).
	 */
	public static function output_head_tags(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = (int) get_queried_object_id();
		if ( ! self::should_output_for_post( $post_id ) ) {
			return;
		}

		$fields = self::resolve_fields( $post_id );
		if ( empty( $fields ) ) {
			return;
		}

		echo "\n<!-- [Align SEO] Theme Default Meta Tags -->\n";

		if ( ! empty( $fields['noindex'] ) ) {
			echo '<meta name="robots" content="noindex, follow" />' . "\n";
		}

		if ( ! empty( $fields['description'] ) ) {
			printf( '<meta name="description" content="%s" />' . "\n", esc_attr( (string) $fields['description'] ) );
		}

		if ( ! empty( $fields['og_title'] ) ) {
			printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( (string) $fields['og_title'] ) );
		}

		if ( ! empty( $fields['og_description'] ) ) {
			printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( (string) $fields['og_description'] ) );
		}

		if ( ! empty( $fields['og_type'] ) ) {
			printf( '<meta property="og:type" content="%s" />' . "\n", esc_attr( (string) $fields['og_type'] ) );
		}

		if ( ! empty( $fields['og_url'] ) ) {
			printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( (string) $fields['og_url'] ) );
		}

		if ( ! empty( $fields['og_image'] ) ) {
			printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( (string) $fields['og_image'] ) );
		}

		if ( ! empty( $fields['twitter_card'] ) ) {
			printf( '<meta name="twitter:card" content="%s" />' . "\n", esc_attr( (string) $fields['twitter_card'] ) );
		}

		if ( ! empty( $fields['twitter_title'] ) ) {
			printf( '<meta name="twitter:title" content="%s" />' . "\n", esc_attr( (string) $fields['twitter_title'] ) );
		}

		if ( ! empty( $fields['twitter_description'] ) ) {
			printf( '<meta name="twitter:description" content="%s" />' . "\n", esc_attr( (string) $fields['twitter_description'] ) );
		}

		if ( ! empty( $fields['twitter_image'] ) ) {
			printf( '<meta name="twitter:image" content="%s" />' . "\n", esc_url( (string) $fields['twitter_image'] ) );
		}

		echo "<!-- [Align SEO] End -->\n";
	}
}
