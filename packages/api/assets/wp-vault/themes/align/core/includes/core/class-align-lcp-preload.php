<?php
/**
 * Align Theme LCP & Featured Image Preload Engine
 *
 * Scans current page content and post metadata for LCP / Featured images
 * marked with data-align-preload, fetchpriority="high", or WordPress featured image,
 * and outputs optimized <link rel="preload" as="image"> tags at Priority 1 in <head>.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_LCP_Preload {

	/**
	 * Track emitted preload URLs to prevent duplicate preload tags.
	 *
	 * @var array<string, bool>
	 */
	private static array $emitted_urls = [];

	/**
	 * Initialize frontend hooks.
	 */
	public static function init(): void {
		add_action( 'wp_head', [ __CLASS__, 'output_lcp_preload' ], 1 );
	}

	/**
	 * Output LCP Image preload tag in <head> at Priority 1.
	 */
	public static function output_lcp_preload(): void {
		if ( is_admin() || is_feed() ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return;
		}

		$post = self::get_current_target_post();
		$post_id = $post ? $post->ID : 0;
		$content = $post ? (string) $post->post_content : '';

		$candidate = null;

		// 1. First priority: Scan post_content for manually marked LCP image in Builder
		if ( ! empty( $content ) ) {
			$candidate = self::extract_lcp_image_from_content( $content );
		}

		// 2. Fallback: Check WordPress Native Featured Image (post_thumbnail)
		if ( ! $candidate && $post_id > 0 && has_post_thumbnail( $post_id ) ) {
			$candidate = self::extract_native_featured_image( $post_id );
		}

		if ( ! $candidate || empty( $candidate['src'] ) ) {
			return;
		}

		$url = self::normalize_url( $candidate['src'] );
		if ( empty( $url ) || isset( self::$emitted_urls[ $url ] ) ) {
			return;
		}

		self::$emitted_urls[ $url ] = true;

		$srcset_attr = '';
		if ( ! empty( $candidate['srcset'] ) ) {
			$srcset_attr = ' imagesrcset="' . esc_attr( self::normalize_srcset( $candidate['srcset'] ) ) . '"';
		}

		$sizes_attr = '';
		if ( ! empty( $candidate['sizes'] ) ) {
			$sizes_attr = ' imagesizes="' . esc_attr( $candidate['sizes'] ) . '"';
		}

		error_log( sprintf( '[Align LCP Preload] Preloading LCP image: %s | Post ID: %d', $url, $post_id ) );

		echo '<link rel="preload" as="image" href="' . esc_url( $url ) . '"' . $srcset_attr . $sizes_attr . ' fetchpriority="high">' . "\n";
	}

	/**
	 * Get the current context post object for singular or front page.
	 *
	 * @return WP_Post|null
	 */
	private static function get_current_target_post(): ?WP_Post {
		if ( is_singular() ) {
			$post = get_queried_object();
			return ( $post instanceof WP_Post ) ? $post : null;
		}

		if ( is_front_page() ) {
			$front_page_id = (int) get_option( 'page_on_front' );
			if ( $front_page_id > 0 ) {
				$post = get_post( $front_page_id );
				return ( $post instanceof WP_Post ) ? $post : null;
			}
		}

		return null;
	}

	/**
	 * Extract candidate LCP image attributes from HTML content using WP_HTML_Tag_Processor.
	 *
	 * @param string $content HTML content.
	 * @return array{src: string, srcset: string, sizes: string}|null
	 */
	private static function extract_lcp_image_from_content( string $content ): ?array {
		if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$processor = new WP_HTML_Tag_Processor( $content );
			while ( $processor->next_tag( 'IMG' ) ) {
				$has_preload   = '1' === $processor->get_attribute( 'data-align-preload' ) || 'true' === $processor->get_attribute( 'data-align-preload' );
				$fetchpriority = strtolower( (string) $processor->get_attribute( 'fetchpriority' ) );
				$loading       = strtolower( (string) $processor->get_attribute( 'loading' ) );
				$classes       = strtolower( (string) $processor->get_attribute( 'class' ) );

				$is_featured = $has_preload
					|| 'high' === $fetchpriority
					|| ( 'eager' === $loading && str_contains( $classes, 'no-lazy' ) )
					|| str_contains( $classes, 'blogfeaturedimage' );

				if ( $is_featured ) {
					$src    = (string) ( $processor->get_attribute( 'src' ) ?: $processor->get_attribute( 'data-src' ) );
					$srcset = (string) ( $processor->get_attribute( 'srcset' ) ?: $processor->get_attribute( 'data-srcset' ) );
					$sizes  = (string) $processor->get_attribute( 'sizes' );

					if ( ! empty( $src ) && ! str_starts_with( $src, 'data:image' ) && 'none' !== $src ) {
						return [
							'src'    => $src,
							'srcset' => $srcset,
							'sizes'  => $sizes,
						];
					}
				}
			}
		} else {
			// Regex fallback if WP_HTML_Tag_Processor is not available
			if ( preg_match( '/<img\b[^>]*(?:data-align-preload=["\']?1["\']?|fetchpriority=["\']?high["\']?)[^>]*>/is', $content, $img_m ) ) {
				$img_tag = $img_m[0];
				$src    = preg_match( '/\bsrc=["\']([^"\']+)["\']/i', $img_tag, $src_m ) ? $src_m[1] : '';
				$srcset = preg_match( '/\bsrcset=["\']([^"\']+)["\']/i', $img_tag, $set_m ) ? $set_m[1] : '';
				$sizes  = preg_match( '/\bsizes=["\']([^"\']+)["\']/i', $img_tag, $sz_m ) ? $sz_m[1] : '';

				if ( ! empty( $src ) && ! str_starts_with( $src, 'data:image' ) ) {
					return [
						'src'    => $src,
						'srcset' => $srcset,
						'sizes'  => $sizes,
					];
				}
			}
		}

		return null;
	}

	/**
	 * Extract native WordPress featured image attributes.
	 *
	 * @param int $post_id Post ID.
	 * @return array{src: string, srcset: string, sizes: string}|null
	 */
	private static function extract_native_featured_image( int $post_id ): ?array {
		$thumb_id = get_post_thumbnail_id( $post_id );
		if ( ! $thumb_id ) {
			return null;
		}

		$src = wp_get_attachment_image_url( $thumb_id, 'full' );
		if ( ! $src ) {
			return null;
		}

		$srcset = (string) wp_get_attachment_image_srcset( $thumb_id, 'full' );
		$sizes  = (string) wp_get_attachment_image_sizes( $thumb_id, 'full' );

		return [
			'src'    => $src,
			'srcset' => $srcset,
			'sizes'  => $sizes,
		];
	}

	/**
	 * Normalize relative URL to full absolute URL.
	 *
	 * @param string $url Asset URL.
	 * @return string
	 */
	public static function normalize_url( string $url ): string {
		$url = trim( $url );
		if ( empty( $url ) ) {
			return '';
		}

		if ( str_starts_with( $url, 'http://' ) || str_starts_with( $url, 'https://' ) || str_starts_with( $url, '//' ) || str_starts_with( $url, 'data:' ) ) {
			return $url;
		}

		return home_url( '/' . ltrim( $url, '/' ) );
	}

	/**
	 * Normalize URLs inside srcset candidates.
	 *
	 * @param string $srcset Comma-separated srcset string.
	 * @return string
	 */
	public static function normalize_srcset( string $srcset ): string {
		$parts = explode( ',', $srcset );
		$normalized = [];

		foreach ( $parts as $part ) {
			$candidate = trim( $part );
			if ( empty( $candidate ) ) {
				continue;
			}
			$tokens = preg_split( '/\s+/', $candidate, 2 );
			if ( ! empty( $tokens[0] ) ) {
				$candidate_url = self::normalize_url( $tokens[0] );
				$descriptor    = $tokens[1] ?? '';
				$normalized[]  = trim( $candidate_url . ' ' . $descriptor );
			}
		}

		return implode( ', ', $normalized );
	}
}
