<?php
/**
 * Align Lazyload Engine
 * Handles enqueuing lazy load assets for frontend media loading.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Align_Lazyload
 */
class Align_Lazyload {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 999 );
		add_filter( 'render_block', array( __CLASS__, 'filter_content' ), 999 );
		add_filter( 'wp_img_tag_add_auto_sizes', '__return_false' );
		add_filter( 'wp_calculate_image_srcset', '__return_false' );
	}

	/**
	 * Filter HTML content on frontend to enforce hard-deferred lazyload for offscreen media.
	 *
	 * @param string $content HTML content.
	 * @return string Processed HTML.
	 */
	public static function filter_content( $content ) {
		if ( is_admin() || is_feed() || empty( $content ) || ! is_string( $content ) ) {
			return $content;
		}

		if ( strpos( $content, 'data-srcset' ) === false && strpos( $content, 'data-src' ) === false && strpos( $content, 'lazy' ) === false ) {
			return $content;
		}

		$placeholder_svg = 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 1 1\'%3E%3C/svg%3E';

		// 1. WordPress 6.2+ HTML API Implementation (WP_HTML_Tag_Processor)
		if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$processor = new WP_HTML_Tag_Processor( $content );

			while ( $processor->next_tag() ) {
				$tag_name = $processor->get_tag();

				// Process <source> tag
				if ( 'SOURCE' === $tag_name ) {
					$data_srcset = $processor->get_attribute( 'data-srcset' );
					if ( ! empty( $data_srcset ) && $processor->get_attribute( 'srcset' ) ) {
						$processor->remove_attribute( 'srcset' );
					}
				}

				// Process <img> tag
				if ( 'IMG' === $tag_name ) {
					$loading       = strtolower( (string) $processor->get_attribute( 'loading' ) );
					$fetchpriority = strtolower( (string) $processor->get_attribute( 'fetchpriority' ) );
					$class_attr    = (string) $processor->get_attribute( 'class' );

					$is_eager = ( 'eager' === $loading || 'high' === $fetchpriority || false !== strpos( $class_attr, 'no-lazy' ) || false !== strpos( $class_attr, 'ignorelazyload' ) );
					if ( $is_eager ) {
						continue;
					}

					$data_src = $processor->get_attribute( 'data-src' );
					if ( ! empty( $data_src ) ) {
						$src = (string) $processor->get_attribute( 'src' );
						if ( 0 !== strpos( $src, 'data:' ) ) {
							$processor->set_attribute( 'src', $placeholder_svg );
						}
						if ( $processor->get_attribute( 'data-srcset' ) && $processor->get_attribute( 'srcset' ) ) {
							$processor->remove_attribute( 'srcset' );
						}
					}

					$sizes_attr = (string) $processor->get_attribute( 'sizes' );
					if ( ! empty( $sizes_attr ) && false !== strpos( $sizes_attr, 'auto' ) ) {
						$clean_sizes = trim( preg_replace( '/\bauto,\s*/i', '', $sizes_attr ) );
						if ( 'auto' === $clean_sizes || empty( $clean_sizes ) ) {
							$clean_sizes = '(max-width: 599px) 375px, (max-width: 992px) 768px, 1200px';
						}
						$processor->set_attribute( 'sizes', $clean_sizes );
					}
				}
			}

			return $processor->get_updated_html();
		}

		// 2. Fallback Regex for <picture> elements (WP < 6.2)
		$content = preg_replace_callback( '/<picture\b[^>]*>(.*?)<\/picture>/is', function( $matches ) use ( $placeholder_svg ) {
			$inner = $matches[1];
			// Check if eager or no-lazy
			if ( strpos( $inner, 'loading="eager"' ) !== false || strpos( $inner, 'fetchpriority="high"' ) !== false || strpos( $inner, 'no-lazy' ) !== false || strpos( $inner, 'ignorelazyload' ) !== false ) {
				return $matches[0];
			}

			// In sources with data-srcset, remove real srcset
			$inner = preg_replace_callback( '/<source\s+([^>]*?)>/i', function( $sm ) {
				$attrs = $sm[1];
				if ( strpos( $attrs, 'data-srcset' ) !== false && strpos( $attrs, 'srcset=' ) !== false ) {
					$attrs = preg_replace( '/\s+srcset=["\'][^"\']*["\']/i', '', $attrs );
					return '<source ' . $attrs . '>';
				}
				return $sm[0];
			}, $inner );

			// In img with data-src, replace src with SVG placeholder
			$inner = preg_replace_callback( '/<img\s+([^>]*?)>/i', function( $im ) use ( $placeholder_svg ) {
				$attrs = $im[1];
				if ( strpos( $attrs, 'data-src' ) !== false ) {
					if ( preg_match( '/(?<![a-zA-Z0-9_-])src=["\']([^"\']+)["\']/i', $attrs, $src_m ) ) {
						if ( strpos( $src_m[1], 'data:' ) !== 0 ) {
							$attrs = preg_replace( '/(?<![a-zA-Z0-9_-])src=["\'][^"\']+["\']/i', 'src="' . $placeholder_svg . '"', $attrs );
						}
					}
					if ( strpos( $attrs, 'data-srcset=' ) !== false ) {
						$attrs = preg_replace( '/(?<![a-zA-Z0-9_-])srcset=["\'][^"\']*["\']/i', '', $attrs );
					}
					return '<img ' . $attrs . '>';
				}
				return $im[0];
			}, $inner );

			return '<picture>' . $inner . '</picture>';
		}, $content );

		// Fallback Regex for standalone <img class="lazy"> or with data-src (WP < 6.2)
		$content = preg_replace_callback( '/<img\s+([^>]*?)>/i', function( $im ) use ( $placeholder_svg ) {
			$attrs = $im[1];
			if ( strpos( $attrs, 'loading="eager"' ) !== false || strpos( $attrs, 'no-lazy' ) !== false || strpos( $attrs, 'ignorelazyload' ) !== false ) {
				return $im[0];
			}
			if ( strpos( $attrs, 'data-src=' ) !== false ) {
				if ( preg_match( '/(?<![a-zA-Z0-9_-])src=["\']([^"\']+)["\']/i', $attrs, $src_m ) ) {
					if ( strpos( $src_m[1], 'data:' ) !== 0 ) {
						$attrs = preg_replace( '/(?<![a-zA-Z0-9_-])src=["\'][^"\']+["\']/i', 'src="' . $placeholder_svg . '"', $attrs );
					}
				}
				if ( strpos( $attrs, 'data-srcset=' ) !== false ) {
					$attrs = preg_replace( '/(?<![a-zA-Z0-9_-])srcset=["\'][^"\']*["\']/i', '', $attrs );
				}
				return '<img ' . $attrs . '>';
			}
			return $im[0];
		}, $content );

		return $content;
	}

	/**
	 * Pre-minified Shimmer Skeleton CSS constant for zero runtime CPU overhead.
	 */
	const SHIMMER_CSS = 'img.lazy:not(.loaded),video.lazy:not(.loaded),iframe.lazy:not(.loaded),.lazy-loading:not(.loaded),[data-bg-src].lazy:not(.loaded){background:linear-gradient(90deg,#e5e7eb 0%,#f3f4f6 50%,#e5e7eb 100%);background-size:200% 100%;animation:lazy-shimmer 1.5s infinite;transition:opacity .3s ease,filter .3s ease}@keyframes lazy-shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}img.lazy.loaded,video.lazy.loaded,iframe.lazy.loaded,.lazy-loading.loaded,[data-bg-src].loaded{background:transparent !important;animation:none !important}';

	/**
	 * Enqueue lazyload CSS & JS assets on frontend pages.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		if ( is_admin() || is_feed() ) {
			return;
		}

		$core_url  = defined( 'ALIGN_CORE_URL' ) ? ALIGN_CORE_URL : get_template_directory_uri() . '/core/';
		$core_path = defined( 'ALIGN_CORE_PATH' ) ? ALIGN_CORE_PATH : get_template_directory() . '/core/';

		// 1. Inject Pre-minified Shimmer Skeleton CSS into consolidated inline CSS queue (0 CPU runtime overhead)
		if ( class_exists( 'Align_Custom_CSS_JS' ) ) {
			Align_Custom_CSS_JS::add_inline_css( 'lazyload', self::SHIMMER_CSS );
		} else {
			wp_register_style( 'lazyload', false, array(), '1.0.0' );
			wp_enqueue_style( 'lazyload' );
			wp_add_inline_style( 'lazyload', self::SHIMMER_CSS );
		}

		// 2. Enqueue Lazyload JS Engine
		$js_file = $core_path . 'assets/js/align-lazyload.js';
		if ( file_exists( $js_file ) ) {
			wp_enqueue_script(
				'align-lazyload',
				$core_url . 'assets/js/align-lazyload.js',
				array(),
				filemtime( $js_file ),
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		}
	}
}
