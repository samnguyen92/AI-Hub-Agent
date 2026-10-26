<?php
/**
 * Align Builder Dynamic Renderer
 * Resolves whitelisted dynamic tokens and repeaters at SSR runtime.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Builder_Dynamic_Renderer {

	/**
	 * Initialize runtime hooks.
	 */
	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'filter_the_content' ), 15 );
	}

	/**
	 * Re-entrancy guard to prevent recursive loops on the_content filter.
	 *
	 * @var bool
	 */
	private static $is_rendering = false;

	/**
	 * Filter post content on frontend to resolve dynamic tokens.
	 *
	 * @param string $content Post content HTML.
	 * @return string
	 */
	public static function filter_the_content( $content ) {
		if ( self::$is_rendering || empty( $content ) || false === strpos( $content, '{{' ) ) {
			return $content;
		}

		self::$is_rendering = true;
		try {
			$content = self::render_tokens( $content, get_the_ID() );
		} finally {
			self::$is_rendering = false;
		}

		return $content;
	}

	/**
	 * Resolve target post context.
	 *
	 * @param int $target_post_id Explicit target post ID.
	 * @param int $block_post_id  Optional reusable block post ID.
	 * @return WP_Post|null
	 */
	public static function resolve_target_post( $target_post_id = 0, $block_post_id = 0 ) {
		$target_id = absint( $target_post_id );
		if ( $target_id > 0 ) {
			$post = get_post( $target_id );
			if ( $post && ! in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
				return $post;
			}
		}

		if ( isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof WP_Post && ! empty( $GLOBALS['post']->ID ) ) {
			return $GLOBALS['post'];
		}

		$current_id = get_the_ID() ?: get_queried_object_id();
		if ( $current_id > 0 ) {
			$post = get_post( $current_id );
			if ( $post && ! in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
				return $post;
			}
		}

		if ( $block_post_id > 0 ) {
			$source_id = absint( get_post_meta( $block_post_id, '_align_source_page_id', true ) );
			if ( $source_id > 0 ) {
				$post = get_post( $source_id );
				if ( $post && ! in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
					return $post;
				}
			}
		}

		// Fallback for canvas previews when no post context is active
		$recent = get_posts( array(
			'post_type'      => array( 'page', 'post' ),
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'ID',
			'order'          => 'DESC',
		) );

		return ! empty( $recent ) ? $recent[0] : null;
	}

	/**
	 * Render dynamic tokens in HTML content.
	 *
	 * @param string $html           Input HTML containing tokens.
	 * @param int    $target_post_id Explicit target post ID.
	 * @param int    $block_post_id  Optional block ID for metadata lookup.
	 * @return string
	 */
	public static function render_tokens( $html, $target_post_id = 0, $block_post_id = 0 ) {
		if ( empty( $html ) || false === strpos( $html, '{{' ) ) {
			return $html;
		}

		$post = self::resolve_target_post( $target_post_id, $block_post_id );
		if ( ! $post ) {
			return $html;
		}

		$start_len = strlen( $html );

		// 1. Resolve loop repeater blocks: {{ loop_items [attrs] }}...{{ /loop_items }}
		if ( preg_match( '/\{\{\s*loop_items\b([^}]*)\}\}([\s\S]*?)\{\{\s*\/loop_items\s*\}\}/i', $html, $loop_m ) ) {
			$loop_attrs    = $loop_m[1];
			$card_template = $loop_m[2];

			// Detect posts_per_page dynamically:
			// 1. Check attributes on loop_items: posts_per_page="N" or count="N" or limit="N"
			// 2. Check data attribute in HTML: data-posts-per-page="N"
			// 3. Fallback to 6 if completely unspecified
			$posts_per_page = 6;
			if ( preg_match( '/(?:posts_per_page|count|limit)\s*=\s*["\']?(\d+)["\']?/i', $loop_attrs, $attr_m ) ) {
				$posts_per_page = max( 1, min( 50, (int) $attr_m[1] ) );
			} elseif ( preg_match( '/data-posts-per-page\s*=\s*["\'](\d+)["\']/i', $html, $data_m ) ) {
				$posts_per_page = max( 1, min( 50, (int) $data_m[1] ) );
			}

			$query = new WP_Query( array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => $posts_per_page,
				'no_found_rows'  => false,
			) );

			$loop_output = '';
			if ( $query->have_posts() ) {
				$loop_index = 1;
				while ( $query->have_posts() ) {
					$query->the_post();
					$rendered_card = self::replace_single_post_tokens( $card_template, get_post(), $loop_index, $posts_per_page );
					$loop_output .= $rendered_card;
					$loop_index++;
				}
				wp_reset_postdata();
			}

			$html = str_replace( $loop_m[0], $loop_output, $html );
			$html = preg_replace( '/\{\{\s*max_pages\s*\}\}/i', (string) $query->max_num_pages, $html );
			$html = preg_replace( '/\{\{\s*posts_per_page\s*\}\}/i', (string) $posts_per_page, $html );
		}

		// 2. Resolve single post & site tokens
		$html = self::replace_single_post_tokens( $html, $post );

		error_log( sprintf(
			'[Align DynamicRenderer] Hydrated tokens for Post #%d ("%s") | Block #%d | Len: %d -> %d bytes',
			$post->ID,
			substr( $post->post_title, 0, 30 ),
			$block_post_id,
			$start_len,
			strlen( $html )
		) );

		return $html;
	}

	/**
	 * Replace tokens for a single post entity.
	 *
	 * @param string  $template       HTML template chunk.
	 * @param WP_Post $post           Target post object.
	 * @param int     $loop_index     1-based loop counter.
	 * @param int     $posts_per_page Current query posts limit.
	 * @return string
	 */
	public static function replace_single_post_tokens( $template, $post, $loop_index = 1, $posts_per_page = 6 ) {
		if ( empty( $template ) || false === strpos( $template, '{{' ) || ! ( $post instanceof WP_Post ) ) {
			return $template;
		}

		return preg_replace_callback( '/\{\{\s*([a-zA-Z0-9_\.\-]+)\s*\}\}/', function( $m ) use ( $post, $loop_index, $posts_per_page ) {
			$key = trim( $m[1] );
			return self::resolve_single_token( $key, $post, $loop_index, $posts_per_page, $m[0] );
		}, $template );
	}

	/**
	 * Resolve a single dynamic token on demand.
	 *
	 * @param string  $key            Token key without braces.
	 * @param WP_Post $post           Target post object.
	 * @param int     $loop_index     1-based loop counter.
	 * @param int     $posts_per_page Posts per page limit.
	 * @param string  $raw_match      The original matching token string.
	 * @return string Resolved token value or original match if unrecognized.
	 */
	public static function resolve_single_token( $key, $post, $loop_index = 1, $posts_per_page = 6, $raw_match = '' ) {
		switch ( $key ) {
			case 'post.id':
				return (string) $post->ID;

			case 'post.title':
				return get_the_title( $post ) ?: $post->post_title;

			case 'post.permalink':
				return esc_url( get_permalink( $post->ID ) );

			case 'post.thumbnail':
				$thumb_id  = get_post_thumbnail_id( $post->ID );
				$thumb_url = $thumb_id ? get_the_post_thumbnail_url( $post->ID, 'full' ) : '';
				if ( empty( $thumb_url ) && class_exists( 'Align_Builder_Assets' ) ) {
					$thumb_url = esc_url( get_template_directory_uri() . '/core/builder/img/dummy/1.webp' );
				}
				return esc_url( $thumb_url );

			case 'post.thumbnail_alt':
				$thumb_id  = get_post_thumbnail_id( $post->ID );
				$thumb_alt = $thumb_id ? get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) : '';
				return esc_attr( ! empty( $thumb_alt ) ? $thumb_alt : ( get_the_title( $post ) ?: $post->post_title ) );

			case 'post.excerpt':
				return wp_strip_all_tags( get_the_excerpt( $post->ID ) );

			case 'post.content':
				// Recursion safety: prevent nested the_content filter loops
				static $rendering_content = false;
				if ( $rendering_content ) {
					return $post->post_content;
				}
				$rendering_content = true;
				try {
					$rendered = apply_filters( 'the_content', $post->post_content );
				} finally {
					$rendering_content = false;
				}
				return $rendered;

			case 'post.date':
				return get_the_date( '', $post->ID );

			case 'post.modified_date':
				return get_the_modified_date( '', $post->ID );

			case 'post.reading_time':
				$word_count   = str_word_count( wp_strip_all_tags( $post->post_content ) );
				$reading_mins = max( 1, (int) ceil( $word_count / 200 ) );
				return $reading_mins . ' min read';

			case 'post.comment_count':
				return (string) get_comments_number( $post->ID );

			case 'post.slug':
				return esc_attr( $post->post_name );

			case 'post.category':
				$terms = get_the_category( $post->ID );
				return esc_html( ! empty( $terms ) ? $terms[0]->name : '' );

			case 'post.category_url':
				$terms = get_the_category( $post->ID );
				return esc_url( ! empty( $terms ) ? get_category_link( $terms[0]->term_id ) : '#' );

			case 'post.tag':
				$post_tags = get_the_tags( $post->ID );
				if ( ! empty( $post_tags ) && ! is_wp_error( $post_tags ) ) {
					return esc_html( $post_tags[0]->name );
				}
				$terms = get_the_category( $post->ID );
				return esc_html( ! empty( $terms ) ? $terms[0]->name : '' );

			case 'post.tags':
				$post_tags = get_the_tags( $post->ID );
				if ( ! empty( $post_tags ) && ! is_wp_error( $post_tags ) ) {
					return esc_html( implode( ', ', wp_list_pluck( $post_tags, 'name' ) ) );
				}
				$terms = get_the_category( $post->ID );
				return esc_html( ! empty( $terms ) ? $terms[0]->name : '' );

			case 'post.tag_url':
				$post_tags = get_the_tags( $post->ID );
				return esc_url( ( ! empty( $post_tags ) && ! is_wp_error( $post_tags ) ) ? get_tag_link( $post_tags[0]->term_id ) : '#' );

			case 'post.author_name':
				return esc_html( get_the_author_meta( 'display_name', $post->post_author ) );

			case 'post.author_avatar':
				return esc_url( get_avatar_url( $post->post_author ) );

			case 'post.author_url':
				return esc_url( get_author_posts_url( $post->post_author ) );

			case 'post.author_bio':
				return esc_html( get_the_author_meta( 'description', $post->post_author ) );

			case 'loop.index':
				return (string) $loop_index;

			case 'loop.index0':
				return (string) ( $loop_index - 1 );

			case 'posts_per_page':
				return (string) $posts_per_page;

			case 'max_pages':
				return '1';

			case 'site.name':
				return get_bloginfo( 'name' );

			case 'site.url':
				return esc_url( home_url( '/' ) );

			case 'site.description':
				return get_bloginfo( 'description' );

			case 'site.year':
			case 'current_year':
				return date( 'Y' );

			case 'site.logo':
				$custom_logo_id = get_theme_mod( 'custom_logo' );
				return esc_url( $custom_logo_id ? wp_get_attachment_image_url( $custom_logo_id, 'full' ) : '' );
		}

		// ACF custom fields: {{ acf.field_name }}
		if ( 0 === strpos( $key, 'acf.' ) ) {
			$field_name = substr( $key, 4 );
			$val        = function_exists( 'get_field' ) ? get_field( $field_name, $post->ID ) : get_post_meta( $post->ID, $field_name, true );
			if ( is_array( $val ) ) {
				return isset( $val['url'] ) ? esc_url( $val['url'] ) : ( isset( $val['title'] ) ? esc_html( $val['title'] ) : '' );
			}
			return is_scalar( $val ) ? esc_html( (string) $val ) : '';
		}

		// Standard post meta: {{ meta.meta_key }}
		if ( 0 === strpos( $key, 'meta.' ) ) {
			$meta_key = substr( $key, 5 );
			$val      = get_post_meta( $post->ID, $meta_key, true );
			return is_scalar( $val ) ? esc_html( (string) $val ) : '';
		}

		return $raw_match;
	}

	/**
	 * Centralized render pipeline for Studio live preview and AJAX previews.
	 *
	 * @param string $html           Raw template HTML.
	 * @param int    $source_page_id Source page ID context.
	 * @param int    $target_post_id Explicit target post ID / block ID.
	 * @return string Rendered preview HTML.
	 */
	public static function render_for_preview( $html, $source_page_id = 0, $target_post_id = 0 ): string {
		if ( empty( $html ) ) {
			return '';
		}

		if ( class_exists( 'Align_Builder_Assets' ) ) {
			$html = Align_Builder_Assets::replace_dummy_images( $html );
		}

		if ( false !== strpos( $html, '[align_post_hero_image' ) ) {
			$preview_pid = class_exists( 'Align_Single_Post_Init' ) ? Align_Single_Post_Init::resolve_post_id() : 0;
			$html = preg_replace_callback( '/\[align_post_hero_image(?:\s+size=[\'"]([^\'"]+)[\'"])?[^\]]*\]/i', function( $m ) use ( $preview_pid ) {
				$size = ! empty( $m[1] ) ? $m[1] : 'garage_blog_featured_desktop';
				$url  = class_exists( 'Align_Single_Post_Init' ) ? Align_Single_Post_Init::get_hero_image_url( $preview_pid, $size ) : '';
				return ! empty( $url ) ? esc_url( $url ) : '/wp-content/uploads/2026/08/home-3-1200x460.webp';
			}, $html );
		}

		if ( class_exists( 'Align_Builder_Content_Handler' ) ) {
			$html = Align_Builder_Content_Handler::resolve_dynamic_links( $html );
			$html = Align_Builder_Content_Handler::render_builder_components_for_preview( $html );
		}

		return self::render_tokens( $html, $source_page_id, $target_post_id );
	}
}
