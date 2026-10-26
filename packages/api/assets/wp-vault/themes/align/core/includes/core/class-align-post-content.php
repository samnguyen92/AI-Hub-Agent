<?php
/**
 * Post Content Handler & Shortcode Engine for Align Theme.
 *
 * Handles dynamic post content resolution, formatting, unwrapping,
 * recursion prevention, and shortcode registration.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Post_Content {

	/**
	 * Maximum recursion rendering depth.
	 */
	const MAX_DEPTH = 3;

	/**
	 * Active rendering post IDs stack.
	 *
	 * @var array<int>
	 */
	private static $active_rendering_posts = array();

	/**
	 * Current rendering depth level.
	 *
	 * @var int
	 */
	private static $current_depth = 0;

	/**
	 * Re-entrancy guard flag for the_content filter.
	 *
	 * @var bool
	 */
	private static $is_filtering_content = false;

	/**
	 * Initialize post content engine and register shortcodes.
	 */
	public static function init() {
		$shortcodes = array( 'wp_content', 'post_content', 'align_content', 'align_post_content' );
		foreach ( $shortcodes as $shortcode ) {
			add_shortcode( $shortcode, array( __CLASS__, 'render_shortcode' ) );
		}
	}

	/**
	 * Resolve target post ID using a 7-tier waterfall hierarchy.
	 *
	 * @param int   $explicit_id   Explicitly passed post ID.
	 * @param array $block_context Gutenberg block context array.
	 * @return int Resolved post ID or 0 if none found.
	 */
	public static function resolve_post_id( $explicit_id = 0, $block_context = array() ) {
		// Tier 1: Explicit ID passed
		$explicit_id = intval( $explicit_id );
		if ( $explicit_id > 0 ) {
			return $explicit_id;
		}

		// Tier 2: Gutenberg block context postId
		if ( ! empty( $block_context['postId'] ) && intval( $block_context['postId'] ) > 0 ) {
			return intval( $block_context['postId'] );
		}

		// Tier 3: in_the_loop() ID
		if ( in_the_loop() && get_the_ID() ) {
			return (int) get_the_ID();
		}

		// Tier 4: get_queried_object_id() for singular queries
		if ( function_exists( 'is_singular' ) && is_singular() && function_exists( 'get_queried_object_id' ) ) {
			$queried_id = (int) get_queried_object_id();
			if ( $queried_id > 0 ) {
				return $queried_id;
			}
		}

		// Tier 5: Global $post->ID
		global $post;
		if ( isset( $post->ID ) && (int) $post->ID > 0 ) {
			return (int) $post->ID;
		}

		// Tier 6: Global $wp_query->post->ID
		global $wp_query;
		if ( isset( $wp_query->post->ID ) && (int) $wp_query->post->ID > 0 ) {
			return (int) $wp_query->post->ID;
		}

		// Tier 7: Fallback to get_queried_object_id() if valid post
		if ( function_exists( 'get_queried_object_id' ) ) {
			$queried_id = (int) get_queried_object_id();
			if ( $queried_id > 0 && get_post_type( $queried_id ) ) {
				return $queried_id;
			}
		}

		return 0;
	}

	/**
	 * Render post content with recursion guards, unwrapping, and formatting.
	 *
	 * @param int   $post_id Target post ID (optional, defaults to current).
	 * @param array $args    Rendering arguments.
	 * @return string Rendered post content HTML.
	 */
	public static function render( $post_id = 0, $args = array() ) {
		$defaults = array(
			'raw'           => false,
			'words'         => 0,
			'strip_tags'    => false,
			'class'         => '',
			'tag'           => '',
			'block_context' => array(),
		);
		$args = wp_parse_args( $args, $defaults );

		$resolved_id = self::resolve_post_id( $post_id, $args['block_context'] );
		if ( $resolved_id <= 0 ) {
			return '';
		}

		// Check recursion limits and current stack
		if ( self::$current_depth >= self::MAX_DEPTH || in_array( $resolved_id, self::$active_rendering_posts, true ) ) {
			error_log( sprintf(
				'[Align PostContent] Recursion prevented for post ID %d (Depth: %d, Stack: %s)',
				$resolved_id,
				self::$current_depth,
				implode( ',', self::$active_rendering_posts )
			) );
			return '';
		}

		$post = get_post( $resolved_id );
		if ( ! $post || ! ( $post instanceof WP_Post ) ) {
			return '';
		}

		// Security guard: check if post is locked for current visitor
		if ( class_exists( 'Align_Post_Password_Lock' ) && Align_Post_Password_Lock::is_locked_for_visitor( $post ) ) {
			return Align_Post_Password_Lock::render_embedded_placeholder( $post );
		}

		// Security guard: verify post status for unauthorized viewers
		if ( ! current_user_can( 'read_post', $resolved_id ) && ! in_array( $post->post_status, array( 'publish', 'inherit' ), true ) ) {
			return '';
		}

		// Push to recursion tracking stack
		self::$active_rendering_posts[] = $resolved_id;
		self::$current_depth++;

		try {
			$content = $post->post_content;

			// 1. Unwrap custom HTML block wrappers if present
			if ( preg_match( '/<!--\s*wp:align\/custom-html\b[^>]*>(.*?)<!--\s*\/wp:align\/custom-html\s*-->/s', $content, $custom_matches ) ) {
				$content = trim( $custom_matches[1] );
			} elseif ( preg_match( '/<!--\s*wp:html\s*-->(.*?)<!--\s*\/wp:html\s*-->/s', $content, $html_matches ) ) {
				$content = trim( $html_matches[1] );
			}

			// Unwrap legacy align-custom-html-block wrapper div
			if ( preg_match( '/^<div\b[^>]*\bclass="[^"]*\balign-custom-html-block\b[^"]*"[^>]*>\s*([\s\S]*?)\s*<\/div>$/i', trim( $content ), $wrapper_m ) ) {
				$content = trim( $wrapper_m[1] );
			}

			// 2. Render inner Gutenberg blocks if present
			if ( function_exists( 'has_blocks' ) && has_blocks( $content ) && function_exists( 'do_blocks' ) ) {
				$content = do_blocks( $content );
			}

			// 3. Format with the_content filters if not raw mode (temporarily bypass wpautop and wptexturize to protect custom HTML/JS/CSS markup)
			if ( ! $args['raw'] ) {
				if ( ! self::$is_filtering_content ) {
					self::$is_filtering_content  = true;
					$wpautop_priority            = has_filter( 'the_content', 'wpautop' );
					$wptexturize_priority        = has_filter( 'the_content', 'wptexturize' );
					if ( false !== $wpautop_priority ) {
						remove_filter( 'the_content', 'wpautop', $wpautop_priority );
					}
					if ( false !== $wptexturize_priority ) {
						remove_filter( 'the_content', 'wptexturize', $wptexturize_priority );
					}
					try {
						$content = apply_filters( 'the_content', $content );
					} finally {
						if ( false !== $wpautop_priority ) {
							add_filter( 'the_content', 'wpautop', $wpautop_priority );
						}
						if ( false !== $wptexturize_priority ) {
							add_filter( 'the_content', 'wptexturize', $wptexturize_priority );
						}
						self::$is_filtering_content = false;
					}
				}
			}

			// 4. Render shortcodes and builder components
			if ( function_exists( 'align_parse_and_render_builder_components' ) ) {
				$content = align_parse_and_render_builder_components( $content );
			}
			if ( function_exists( 'do_shortcode' ) ) {
				$content = do_shortcode( $content );
			}

			// 5. Strip tags and limit words if requested
			if ( ! empty( $args['strip_tags'] ) ) {
				$content = wp_strip_all_tags( $content );
			}
			if ( ! empty( $args['words'] ) && (int) $args['words'] > 0 ) {
				$content = wp_trim_words( $content, (int) $args['words'], '&hellip;' );
			}

			// 6. Optional wrapper tag and class with container tag whitelist
			$content = trim( $content );
			if ( ! empty( $content ) ) {
				$raw_tag      = ! empty( $args['tag'] ) ? sanitize_key( $args['tag'] ) : '';
				$allowed_tags = array( 'div', 'article', 'section', 'aside', 'main', 'span', 'p', 'header', 'footer' );
				$tag          = in_array( $raw_tag, $allowed_tags, true ) ? $raw_tag : ( ! empty( $raw_tag ) ? 'div' : '' );
				$class        = ! empty( $args['class'] ) ? esc_attr( trim( $args['class'] ) ) : '';

				if ( ! empty( $tag ) ) {
					$class_attr = ! empty( $class ) ? ' class="' . $class . '"' : '';
					$content    = '<' . $tag . $class_attr . '>' . $content . '</' . $tag . '>';
				} elseif ( ! empty( $class ) ) {
					$content = '<div class="' . $class . '">' . $content . '</div>';
				}
			}

			return $content;
		} finally {
			array_pop( self::$active_rendering_posts );
			self::$current_depth--;
		}
	}

	/**
	 * Shortcode callback handler for [wp_content], [post_content], etc.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string Rendered content HTML.
	 */
	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'         => 0,
				'slug'       => '',
				'words'      => 0,
				'strip_tags' => false,
				'raw'        => false,
				'class'      => '',
				'tag'        => '',
			),
			$atts,
			'post_content'
		);

		$post_id = intval( $atts['id'] );

		// Resolve slug to post ID if provided and ID not given
		if ( empty( $post_id ) && ! empty( $atts['slug'] ) ) {
			$page_by_path = get_page_by_path( sanitize_title( $atts['slug'] ), OBJECT, array( 'post', 'page' ) );
			if ( $page_by_path && isset( $page_by_path->ID ) ) {
				$post_id = (int) $page_by_path->ID;
			}
		}

		$args = array(
			'words'      => intval( $atts['words'] ),
			'strip_tags' => filter_var( $atts['strip_tags'], FILTER_VALIDATE_BOOLEAN ),
			'raw'        => filter_var( $atts['raw'], FILTER_VALIDATE_BOOLEAN ),
			'class'      => sanitize_text_field( $atts['class'] ),
			'tag'        => sanitize_key( $atts['tag'] ),
		);

		return self::render( $post_id, $args );
	}
}
