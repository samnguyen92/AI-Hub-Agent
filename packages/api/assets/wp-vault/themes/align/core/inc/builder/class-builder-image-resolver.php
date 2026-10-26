<?php
/**
 * Align Theme Core Engine - Hybrid Responsive Image & Picture Srcset Resolver
 *
 * Automatically resolves and injects responsive srcset, sizes, and 3-tier art-direction
 * picture sources into HTML content for the editor builder canvas preview and dynamic rendering.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Builder_Image_Resolver {

	/** In-memory cache for attachment metadata during a single request lifecycle. */
	private static $attachment_cache = array();

	/** Normalize any WordPress URL to a relative URL (/wp-content/uploads/...). */
	public static function to_relative_url( $url ) {
		if ( empty( $url ) || ! is_string( $url ) ) { return ''; }
		if ( strpos( $url, 'data:' ) === 0 || strpos( $url, 'blob:' ) === 0 ) { return $url; }

		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! empty( $path ) ) {
			$pos = strpos( $path, '/wp-content/' );
			if ( false !== $pos ) {
				$query = wp_parse_url( $url, PHP_URL_QUERY );
				return substr( $path, $pos ) . ( $query ? '?' . $query : '' );
			}
			if ( 0 === strpos( $path, 'wp-content/' ) ) {
				return '/' . $path;
			}
		}

		return ( 0 === strpos( $url, 'wp-content/' ) ) ? '/' . $url : $url;
	}

	/**
	 * Retrieve full attachment sizes metadata, utilizing Align_Image_Sizes or WP core fallbacks.
	 *
	 * @param int $attachment_id WordPress attachment ID.
	 * @return array|null Structured attachment data array or null on failure.
	 */
	public static function get_attachment_data( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return null;
		}

		if ( array_key_exists( $attachment_id, self::$attachment_cache ) ) {
			return self::$attachment_cache[ $attachment_id ];
		}

		$post = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			self::$attachment_cache[ $attachment_id ] = null;
			return null;
		}

		$mime_type = get_post_mime_type( $attachment_id );
		$is_svg    = ( 'image/svg+xml' === $mime_type );

		if ( class_exists( 'Align_Image_Sizes' ) && method_exists( 'Align_Image_Sizes', 'get_attachment_sizes_data' ) ) {
			$data = Align_Image_Sizes::get_attachment_sizes_data( $attachment_id );
			if ( $data ) {
				$data['mime_type']                        = $mime_type;
				$data['is_svg']                           = $is_svg;
				self::$attachment_cache[ $attachment_id ] = $data;
				return $data;
			}
		}

		$full_src = wp_get_attachment_image_src( $attachment_id, 'full' );
		$full_url = $full_src ? $full_src[0] : wp_get_attachment_url( $attachment_id );
		if ( ! $full_url ) {
			self::$attachment_cache[ $attachment_id ] = null;
			return null;
		}

		$meta        = wp_get_attachment_metadata( $attachment_id );
		$full_width  = $full_src ? (int) $full_src[1] : ( isset( $meta['width'] ) ? (int) $meta['width'] : 0 );
		$full_height = $full_src ? (int) $full_src[2] : ( isset( $meta['height'] ) ? (int) $meta['height'] : 0 );
		$alt         = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( empty( $alt ) && $post ) {
			$alt = $post->post_title;
		}

		$sizes = array();
		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $s_name => $s_info ) {
				$src = wp_get_attachment_image_src( $attachment_id, $s_name );
				if ( $src && ! empty( $src[0] ) ) {
					$w = ! empty( $s_info['width'] ) ? (int) $s_info['width'] : (int) $src[1];
					$h = ! empty( $s_info['height'] ) ? (int) $s_info['height'] : (int) $src[2];
					$sizes[] = array( 'name' => $s_name, 'url' => $src[0], 'width' => $w, 'height' => $h );
				}
			}
		}

		$data = array(
			'id' => $attachment_id, 'full_url' => $full_url, 'width' => $full_width, 'height' => $full_height,
			'alt' => $alt, 'sizes' => $sizes, 'total_sizes' => count( $sizes ), 'mime_type' => $mime_type, 'is_svg' => $is_svg,
		);

		self::$attachment_cache[ $attachment_id ] = $data;
		return $data;
	}

	/**
	 * Main entry point: scan and resolve dynamic image srcset and picture breakpoints in HTML content.
	 *
	 * @param string $content HTML content to process.
	 * @return string Processed HTML content.
	 */
	public static function resolve_dynamic_image_srcset( $content ) {
		if ( empty( $content ) || ! is_string( $content ) ) {
			return $content;
		}

		// Early-exit guard
		if ( false === strpos( $content, 'data-id' ) && false === strpos( $content, 'wp-image-' ) && false === strpos( $content, 'data-align-image-id' ) && false === strpos( $content, 'data-align-featured' ) && false === strpos( $content, 'blogFeaturedImage' ) && false === strpos( $content, 'wp-block-post-featured-image' ) && false === strpos( $content, 'wp-content/uploads' ) ) {
			return $content;
		}

		$picture_count = 0;
		$img_count     = 0;

		$resolved_content = preg_replace_callback(
			'/<!--[\s\S]*?-->|(<picture\b[^>]*>[\s\S]*?<\/picture>)|(<img\b[^>]*>)/is',
			function( $matches ) use ( &$picture_count, &$img_count ) {
				if ( empty( $matches[1] ) && empty( $matches[2] ) ) {
					return $matches[0]; // HTML comment
				}
				if ( ! empty( $matches[1] ) ) {
					$picture_count++;
					return self::resolve_picture_tag( $matches[1] );
				}
				if ( ! empty( $matches[2] ) ) {
					$img_count++;
					return self::resolve_img_tag( $matches[2] );
				}
				return $matches[0];
			},
			$content
		);

		if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'ALIGN_DEBUG' ) && ALIGN_DEBUG ) ) {
			error_log( sprintf( '[Align ImageResolver] Resolved Dynamic Image Srcset | Pictures: %d | Standalone Imgs: %d | Payload Length: %d', $picture_count, $img_count, strlen( $resolved_content ) ) );
		}

		return $resolved_content;
	}

	/**
	 * Resolve art-direction <source> breakpoints and fallback <img> inside a <picture> tag.
	 *
	 * @param string $picture_tag Full HTML picture tag.
	 * @return string Processed picture tag or unwrapped img tag.
	 */
	public static function resolve_picture_tag( $picture_tag ) {
		if ( empty( $picture_tag ) || ! is_string( $picture_tag ) ) {
			return $picture_tag;
		}

		$attachment_id = self::extract_attachment_id( $picture_tag );
		if ( $attachment_id <= 0 ) {
			return $picture_tag;
		}

		$data = self::get_attachment_data( $attachment_id );
		if ( ! preg_match( '/<img\b[^>]*>/i', $picture_tag, $img_match ) ) {
			return $picture_tag;
		}
		$inner_img = $img_match[0];

		// Self-healing: if inner <img> points to a different valid attachment, prioritize it
		if ( function_exists( 'attachment_url_to_postid' ) && preg_match( '/\bsrc\s*=\s*([\'"])(.*?)\1/i', $inner_img, $src_m ) ) {
			$src_url = trim( $src_m[2] );
			if ( ! empty( $src_url ) && false !== strpos( $src_url, 'wp-content/uploads' ) && ! self::is_dummy_or_placeholder( $src_url ) ) {
				$full_src = ( strpos( $src_url, 'http' ) === 0 ) ? $src_url : home_url( $src_url );
				$url_aid  = (int) attachment_url_to_postid( explode( '?', $full_src )[0] );
				if ( ! $url_aid ) {
					$clean_sub = preg_replace( '/(-\d+x\d+)(\.[a-zA-Z0-9]+)$/', '$2', $full_src );
					if ( $clean_sub !== $full_src ) { $url_aid = (int) attachment_url_to_postid( $clean_sub ); }
				}
				if ( $url_aid > 0 && $url_aid !== $attachment_id && ( $url_data = self::get_attachment_data( $url_aid ) ) ) {
					$attachment_id = $url_aid;
					$data          = $url_data;
				}
			}
		}

		if ( ! $data ) {
			return $picture_tag;
		}
		$is_svg    = ! empty( $data['is_svg'] );
		$is_icon   = $is_svg || ( $data['width'] > 0 && $data['width'] <= 150 && $data['height'] > 0 && $data['height'] <= 150 );

		// SVG / Small Icon: Unwrap to clean standalone <img> without <picture> wrapper
		if ( preg_match( '/\bdata-skip-picture\s*=\s*[\'"]?true[\'"]?/i', $picture_tag ) ) {
			return $picture_tag;
		}

		if ( $is_icon ) {
			$clean_url = self::to_relative_url( $data['full_url'] );
			$clean_img = self::remove_tag_attributes( $inner_img, array( 'srcset', 'data-srcset', 'sizes' ) );
			$clean_img = self::set_tag_attribute( $clean_img, 'src', $clean_url );
			if ( preg_match( '/\bdata-src\b/i', $clean_img ) ) { $clean_img = self::set_tag_attribute( $clean_img, 'data-src', $clean_url ); }
			if ( $data['width'] > 0 ) { $clean_img = self::set_tag_attribute( $clean_img, 'width', $data['width'] ); }
			if ( $data['height'] > 0 ) { $clean_img = self::set_tag_attribute( $clean_img, 'height', $data['height'] ); }
			if ( ! empty( $data['alt'] ) && ! preg_match( '/\balt\s*=\s*[\'"][^\'"]+[\'"]/i', $clean_img ) ) { $clean_img = self::set_tag_attribute( $clean_img, 'alt', $data['alt'] ); }
			if ( ! preg_match( '/\bdecoding\s*=\s*/i', $clean_img ) ) { $clean_img = self::set_tag_attribute( $clean_img, 'decoding', 'async' ); }
			$clean_img = self::clean_image_inline_style( $clean_img );
			$clean_img = self::set_tag_attribute( $clean_img, 'data-id', $attachment_id );
			return self::update_tag_class( $clean_img, 'wp-image-' . $attachment_id );
		}

		// Slot-width extraction from picture attribute or inner img width
		$slot_width = 0;
		if ( preg_match( '/\bdata-slot-width\s*=\s*[\'"]?(\d+)[\'"]?/i', $picture_tag, $m ) ) {
			$slot_width = (int) $m[1];
		} elseif ( preg_match( '/\bwidth\s*=\s*[\'"]?(\d+)[\'"]?/i', $inner_img, $m ) && (int) $m[1] < 1200 ) {
			$slot_width = (int) $m[1];
		}

		$custom_sizes_attr = '';
		if ( preg_match( '/\b(?:data-align-sizes|data-selected-sizes)\s*=\s*[\'"]([^\'"]+)[\'"]/i', $picture_tag, $m ) ) {
			$custom_sizes_attr = trim( $m[1] );
		}

		// Photo / Media Mode: 3-Tier Art-Direction picture
		$bp = self::resolve_picture_breakpoints( $data, $slot_width, $custom_sizes_attr );
		if ( ! preg_match( '/^<picture\b([^>]*)>/i', trim( $picture_tag ), $pic_match ) ) {
			return $picture_tag;
		}

		$picture_open = self::set_tag_attribute( $pic_match[0], 'data-id', $attachment_id );
		if ( $slot_width > 0 ) $picture_open = self::set_tag_attribute( $picture_open, 'data-slot-width', $slot_width );
		if ( ! empty( $custom_sizes_attr ) ) $picture_open = self::set_tag_attribute( $picture_open, 'data-align-sizes', $custom_sizes_attr );
		$picture_open = self::update_tag_class( $picture_open, 'wp-image-' . $attachment_id );

		// Clean and update fallback <img>
		$updated_img = self::remove_tag_attributes( $inner_img, array( 'srcset', 'data-srcset', 'sizes' ) );
		$updated_img = self::set_tag_attribute( $updated_img, 'src', $bp['desktop']['url'] );
		if ( preg_match( '/\bdata-src\b/i', $updated_img ) ) { $updated_img = self::set_tag_attribute( $updated_img, 'data-src', $bp['desktop']['url'] ); }
		$desk_w = ! empty( $bp['desktop']['width'] ) ? $bp['desktop']['width'] : ( $data['width'] > 0 ? $data['width'] : 0 );
		$desk_h = ! empty( $bp['desktop']['height'] ) ? $bp['desktop']['height'] : ( $data['height'] > 0 ? $data['height'] : 0 );
		if ( $desk_w > 0 ) { $updated_img = self::set_tag_attribute( $updated_img, 'width', $desk_w ); }
		if ( $desk_h > 0 ) { $updated_img = self::set_tag_attribute( $updated_img, 'height', $desk_h ); }
		if ( ! empty( $data['alt'] ) && ! preg_match( '/\balt\s*=\s*[\'"][^\'"]+[\'"]/i', $updated_img ) ) { $updated_img = self::set_tag_attribute( $updated_img, 'alt', $data['alt'] ); }
		if ( ! preg_match( '/\bloading\s*=\s*/i', $updated_img ) ) { $updated_img = self::set_tag_attribute( $updated_img, 'loading', 'lazy' ); }
		if ( ! preg_match( '/\bdecoding\s*=\s*/i', $updated_img ) ) { $updated_img = self::set_tag_attribute( $updated_img, 'decoding', 'async' ); }
		$updated_img = self::clean_image_inline_style( $updated_img );
		$updated_img = self::set_tag_attribute( $updated_img, 'data-id', $attachment_id );
		$updated_img = preg_replace( '/\bwp-image-\d+\b/', '', $updated_img );
		$updated_img = trim( preg_replace( '/\s+class=([\'"])\s*\1/', '', $updated_img ) );

		$source_mobile = sprintf( '<source media="(max-width: 599px)" srcset="%s" width="%d" height="%d">', esc_attr( $bp['mobile']['url'] ), $bp['mobile']['width'], $bp['mobile']['height'] );
		$source_tablet = sprintf( '<source media="(max-width: 992px)" srcset="%s" width="%d" height="%d">', esc_attr( $bp['tablet']['url'] ), $bp['tablet']['width'], $bp['tablet']['height'] );

		return $picture_open . "\n  " . $source_mobile . "\n  " . $source_tablet . "\n  " . $updated_img . "\n</picture>";
	}

	/**
	 * Resolve dynamic srcset, sizes, and metadata on standalone <img> tags.
	 *
	 * @param string $img_tag Standalone HTML img tag.
	 * @return string Processed img tag or auto-wrapped <picture> container.
	 */
	public static function resolve_img_tag( $img_tag ) {
		if ( empty( $img_tag ) || ! is_string( $img_tag ) ) {
			return $img_tag;
		}

		$attachment_id = self::extract_attachment_id( $img_tag );
		if ( $attachment_id <= 0 ) {
			return $img_tag;
		}

		$src = preg_match( '/\bsrc\s*=\s*([\'"])(.*?)\1/i', $img_tag, $m ) ? $m[2] : '';
		if ( self::is_dummy_or_placeholder( $src ) ) {
			return $img_tag;
		}

		$data = self::get_attachment_data( $attachment_id );
		if ( ! $data ) {
			return $img_tag;
		}

		$is_svg   = ! empty( $data['is_svg'] );
		$is_icon  = $is_svg || ( $data['width'] > 0 && $data['width'] <= 150 && $data['height'] > 0 && $data['height'] <= 150 );
		$full_url = self::to_relative_url( $data['full_url'] );

		$skip_picture = preg_match( '/\bdata-skip-picture\s*=\s*[\'"]?true[\'"]?/i', $img_tag ) || preg_match( '/\bthumb-preview\b/i', $img_tag );

		if ( $is_icon || $skip_picture ) {
			$clean_url = $skip_picture ? $src : $full_url;
			$img = self::remove_tag_attributes( $img_tag, array( 'srcset', 'data-srcset', 'sizes' ) );
			$img = self::set_tag_attribute( $img, 'src', $clean_url );
			if ( preg_match( '/\bdata-src\b/i', $img ) ) { $img = self::set_tag_attribute( $img, 'data-src', $clean_url ); }
			if ( $data['width'] > 0 && ! $skip_picture ) { $img = self::set_tag_attribute( $img, 'width', $data['width'] ); }
			if ( $data['height'] > 0 && ! $skip_picture ) { $img = self::set_tag_attribute( $img, 'height', $data['height'] ); }
			if ( ! empty( $data['alt'] ) && ! preg_match( '/\balt\s*=\s*[\'"][^\'"]+[\'"]/i', $img ) ) { $img = self::set_tag_attribute( $img, 'alt', $data['alt'] ); }
			if ( ! preg_match( '/\bdecoding\s*=\s*/i', $img ) ) { $img = self::set_tag_attribute( $img, 'decoding', 'async' ); }
			$img = self::clean_image_inline_style( $img );
			$img = self::set_tag_attribute( $img, 'data-id', $attachment_id );
			return self::update_tag_class( $img, 'wp-image-' . $attachment_id );
		}

		// Photo / Media Mode: Auto-wrap into 3-tier Art-Direction <picture>
		return self::resolve_picture_tag( '<picture data-id="' . $attachment_id . '">' . $img_tag . '</picture>' );
	}

	/**
	 * Extract attachment ID from data-id, data-align-image-id, wp-image-{id}, featured image, or upload URL.
	 */
	protected static function extract_attachment_id( $html ) {
		if ( preg_match( '/\b(?:data-id|data-align-image-id)\s*=\s*[\'"]?(\d+)[\'"]?/i', $html, $m ) ) return absint( $m[1] );
		if ( preg_match( '/\bclass\s*=\s*[\'"][^\'"]*?\bwp-image-(\d+)\b[^\'"]*?[\'"]/i', $html, $m ) ) return absint( $m[1] );
		if ( preg_match( '/\b(?:data-align-featured|blogFeaturedImage|wp-block-post-featured-image)\b/i', $html ) ) {
			$pid = class_exists( 'Align_Single_Post_Init' ) ? Align_Single_Post_Init::resolve_post_id() : ( (int) ( get_the_ID() ?: get_queried_object_id() ) );
			if ( $pid > 0 && function_exists( 'get_post_thumbnail_id' ) ) {
				$tid = (int) get_post_thumbnail_id( $pid );
				if ( $tid > 0 ) return $tid;
			}
		}
		if ( preg_match( '/\bsrc\s*=\s*([\'"])(.*?)\1/i', $html, $m ) && function_exists( 'attachment_url_to_postid' ) ) {
			$url = trim( $m[2] );
			if ( false !== strpos( $url, 'wp-content/uploads' ) && ! self::is_dummy_or_placeholder( $url ) ) {
				$clean_url = explode( '?', $url )[0];
				$full_url  = ( strpos( $clean_url, 'http' ) === 0 ) ? $clean_url : home_url( $clean_url );
				$aid       = (int) attachment_url_to_postid( $full_url );
				if ( $aid > 0 ) return $aid;
				$cleaned_sub = preg_replace( '/(-\d+x\d+)(\.[a-zA-Z0-9]+)$/', '$2', $full_url );
				if ( $cleaned_sub !== $full_url ) {
					$aid = (int) attachment_url_to_postid( $cleaned_sub );
					if ( $aid > 0 ) return $aid;
				}
			}
		}
		return 0;
	}

	/**
	 * Determine if an image URL is a dummy placeholder or non-resolvable asset.
	 */
	protected static function is_dummy_or_placeholder( $url ) {
		if ( empty( $url ) || ! is_string( $url ) ) { return true; }
		foreach ( array( 'picsum.photos', 'placeholder', 'data:image', 'none', 'example.com' ) as $p ) {
			if ( false !== strpos( $url, $p ) ) { return true; }
		}
		return false;
	}

	/**
	 * Find the sub-size in pool whose width is closest to target width.
	 *
	 * @param array $pool Filtered list of valid sub-sizes.
	 * @param int   $target_width Target width in px.
	 * @param int   $min_width Optional minimum width constraint.
	 * @return array|null Best matching sub-size item.
	 */
	protected static function find_closest_size( array $pool, int $target_width, int $min_width = 0, float $orig_ratio = 0.0 ) {
		if ( empty( $pool ) ) return null;
		$candidates = $pool;
		if ( $min_width > 0 ) {
			$filtered = array_values( array_filter( $pool, function( $s ) use ( $min_width ) { return (int) $s['width'] >= $min_width; } ) );
			if ( ! empty( $filtered ) ) $candidates = $filtered;
		}
		$best = $candidates[0]; $best_score = PHP_INT_MAX;
		foreach ( $candidates as $s ) {
			$w       = (int) $s['width'];
			$penalty = ( $w < $target_width ) ? 1.25 : 1.0;
			$ideal_h = ( $orig_ratio > 0.0 ) ? ( $w * $orig_ratio ) : (float) ( $s['height'] ?? $w );
			$score   = ( abs( $w - $target_width ) * $penalty ) + ( abs( (int) ( $s['height'] ?? $w ) - $ideal_h ) * 0.01 );
			if ( $score < $best_score ) { $best_score = $score; $best = $s; }
		}
		return $best;
	}

	/**
	 * Compute 3-tier responsive picture breakpoints from attachment data.
	 * Supports explicit slots (mobile:x,tablet:y,desktop:z), legacy CSV, and auto proximity scoring.
	 */
	protected static function resolve_picture_breakpoints( $data, $slot_width = 0, $selected_sizes_str = '' ) {
		$sizes_list  = ! empty( $data['sizes'] ) ? $data['sizes'] : array();
		$full_url    = self::to_relative_url( $data['full_url'] ?? '' );
		$full_w      = (int) ( $data['width'] ?? 0 );
		$full_h      = (int) ( $data['height'] ?? 0 );
		$orig_ratio  = ( $full_w > 0 && $full_h > 0 ) ? ( $full_h / $full_w ) : 0.0;
		$valid_sizes = array();

		foreach ( $sizes_list as $s ) {
			if ( ! empty( $s['url'] ) && ! empty( $s['width'] ) && (int) $s['width'] > 0 ) {
				$valid_sizes[] = array( 'name' => ( $s['name'] ?? '' ), 'url' => self::to_relative_url( $s['url'] ), 'width' => (int) $s['width'], 'height' => (int) ( $s['height'] ?? 0 ) );
			}
		}
		usort( $valid_sizes, function( $a, $b ) {
			if ( $a['width'] === $b['width'] ) { return abs( $a['width'] - $a['height'] ) - abs( $b['width'] - $b['height'] ); }
			return $a['width'] - $b['width'];
		} );

		// Parse explicit slot configurations (mobile:x,tablet:y,desktop:z or legacy comma-separated)
		$slot_configs = array();
		if ( ! empty( $selected_sizes_str ) ) {
			$raw = strtolower( trim( $selected_sizes_str ) );
			if ( false !== strpos( $raw, ':' ) ) {
				foreach ( explode( ',', $raw ) as $pair ) {
					$kv = array_map( 'trim', explode( ':', $pair, 2 ) );
					if ( 2 === count( $kv ) ) { $slot_configs[ $kv[0] ] = $kv[1]; }
				}
			} else {
				$parts = array_map( 'trim', explode( ',', $raw ) );
				$cnt   = count( $parts );
				$slot_configs['mobile']  = $parts[0] ?? 'auto';
				$slot_configs['tablet']  = ( $cnt >= 2 ) ? ( 2 === $cnt ? $parts[0] : $parts[1] ) : ( $parts[0] ?? 'auto' );
				$slot_configs['desktop'] = $parts[ $cnt - 1 ] ?? 'auto';
			}
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( '[Align ImageResolver] Slot configs parsed: %s', $selected_sizes_str ) );
			}
		}

		// Compute proximity fallbacks when any slot is 'auto' or unspecified
		$auto = array( 'mobile' => null, 'tablet' => null, 'desktop' => null );
		if ( ! empty( $valid_sizes ) ) {
			$pool           = ( $slot_width > 0 && $slot_width < 150 ) ? $valid_sizes : ( array_values( array_filter( $valid_sizes, function( $s ) { return $s['width'] >= 150; } ) ) ?: $valid_sizes );
			$is_constrained = ( $slot_width > 0 && $slot_width < 1200 );
			$auto['mobile']  = self::find_closest_size( $pool, $is_constrained ? min( $slot_width, 599 ) : 599, 0, $orig_ratio );
			$mob_w           = $auto['mobile'] ? (int) $auto['mobile']['width'] : 0;
			$auto['tablet']  = self::find_closest_size( $pool, $is_constrained ? min( $slot_width, 992 ) : 992, $mob_w, $orig_ratio );
			$tab_w           = $auto['tablet'] ? (int) $auto['tablet']['width'] : $mob_w;
			$auto['desktop'] = self::find_closest_size( $pool, $is_constrained ? min( $full_w ?: 1920, $slot_width * 2 ) : 1440, $tab_w, $orig_ratio );
		}

		$find_slot = function( $val, $slot_key ) use ( $valid_sizes, $auto, $full_url, $full_w, $full_h ) {
			if ( ! empty( $val ) && 'auto' !== $val ) {
				if ( 'full' === $val ) { return array( 'url' => $full_url, 'width' => ( $full_w ?: 1200 ), 'height' => ( $full_h ?: 800 ) ); }
				foreach ( $valid_sizes as $vs ) {
					if ( strtolower( $vs['name'] ) === $val || ( $vs['width'] . 'x' . $vs['height'] ) === $val ) {
						return array( 'url' => $vs['url'], 'width' => $vs['width'], 'height' => $vs['height'] );
					}
				}
			}
			$match = $auto[ $slot_key ] ?? null;
			$fb_w  = ( 'mobile' === $slot_key ) ? 375 : ( 'tablet' === $slot_key ? 768 : 1200 );
			$fb_h  = ( 'mobile' === $slot_key ) ? 200 : ( 'tablet' === $slot_key ? 500 : 800 );
			return array( 'url' => $match ? $match['url'] : $full_url, 'width' => $match ? $match['width'] : ( $full_w ?: $fb_w ), 'height' => $match ? $match['height'] : ( $full_h ?: $fb_h ) );
		};

		return array(
			'mobile'  => $find_slot( $slot_configs['mobile'] ?? 'auto', 'mobile' ),
			'tablet'  => $find_slot( $slot_configs['tablet'] ?? 'auto', 'tablet' ),
			'desktop' => $find_slot( $slot_configs['desktop'] ?? 'auto', 'desktop' ),
		);
	}

	/** Clean redundant inline style attributes (width: 100%; height: auto;) from an HTML img tag. */
	public static function clean_image_inline_style( $tag ) {
		if ( ! preg_match( '/\bstyle\s*=\s*([\'"])(.*?)\1/i', $tag, $m ) ) return $tag;
		$cleaned = trim( preg_replace( '/;\s*;+/', ';', preg_replace( '/\bheight\s*:\s*auto\s*;?/i', '', preg_replace( '/\bwidth\s*:\s*100%\s*;?/i', '', $m[2] ) ) ), " \t\n\r\0\x0B;" );
		if ( '' === $cleaned ) return preg_replace( '/\s*\bstyle\s*=\s*([\'"])(.*?)\1/i', '', $tag, 1 );
		return preg_replace( '/\bstyle\s*=\s*([\'"])(.*?)\1/i', 'style=' . $m[1] . esc_attr( $cleaned ) . $m[1], $tag, 1 );
	}

	/** Set or replace an attribute on an HTML tag string. */
	protected static function set_tag_attribute( $tag, $name, $value ) {
		$val = esc_attr( (string) $value );
		$pat = '/\b' . preg_quote( $name, '/' ) . '\s*=\s*(?:([\'"])(.*?)\1|([^\s>]+))/i';
		return preg_match( $pat, $tag ) ? preg_replace( $pat, $name . '="' . $val . '"', $tag, 1 ) : preg_replace( '/(\s*\/?>)$/', ' ' . $name . '="' . $val . '"$1', $tag, 1 );
	}

	/** Remove attributes from an HTML tag string. */
	protected static function remove_tag_attributes( $tag, $names ) {
		foreach ( (array) $names as $name ) {
			$tag = preg_replace( '/\s+\b' . preg_quote( $name, '/' ) . '(?:\s*=\s*(?:([\'"])(.*?)\1|[^\s>]+))?/i', '', $tag );
		}
		return $tag;
	}

	/** Update or add class to an HTML tag, optionally stripping legacy classes matching pattern. */
	protected static function update_tag_class( $tag, $add_class, $remove_pattern = '/\bwp-image-\d+\b/' ) {
		if ( preg_match( '/\bclass\s*=\s*([\'"])(.*?)\1/i', $tag, $m ) ) {
			$classes = $remove_pattern ? preg_replace( $remove_pattern, '', $m[2] ) : $m[2];
			$arr     = array_filter( explode( ' ', trim( preg_replace( '/\s+/', ' ', $classes ) ) ) );
			if ( ! in_array( $add_class, $arr, true ) ) $arr[] = $add_class;
			return preg_replace( '/\bclass\s*=\s*([\'"])(.*?)\1/i', 'class=' . $m[1] . esc_attr( implode( ' ', array_unique( $arr ) ) ) . $m[1], $tag, 1 );
		}
		return preg_replace( '/(\s*\/?>)$/', ' class="' . esc_attr( $add_class ) . '"$1', $tag, 1 );
	}
}
