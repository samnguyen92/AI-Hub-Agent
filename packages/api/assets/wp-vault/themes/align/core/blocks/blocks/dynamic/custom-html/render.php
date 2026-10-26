<?php
/**
 * Render template for align/custom-html block
 *
 * @package Align
 *
 * @var array  $attributes Block attributes.
 * @var string $content    Block inner content.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'align_resolve_dynamic_post_links' ) ) {
	/**
	 * Resolve dynamic post links with data-align-link-id attribute.
	 *
	 * @param string $content HTML content.
	 * @return string Processed HTML content with resolved permalinks.
	 */
	function align_resolve_dynamic_post_links( $content ) {
		if ( empty( $content ) || ! is_string( $content ) || strpos( $content, 'data-align-link-id' ) === false ) {
			return $content;
		}

		$current_lang = class_exists( 'Align_Template_Language' ) ? Align_Template_Language::get_current_lang() : '';
		$default_lang = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en';

		return preg_replace_callback(
			'/<a\b([^>]*\bdata-align-link-id\s*=\s*[\'"]?(\d+)[\'"]?[^>]*)>/is',
			function( $matches ) use ( $current_lang, $default_lang ) {
				$full_tag = $matches[0];
				$attrs    = $matches[1];
				$post_id  = intval( $matches[2] );

				if ( $post_id <= 0 ) {
					return $full_tag;
				}

				// Resolve target language translation for this post ID if viewing non-default language
				if ( ! empty( $current_lang ) && $current_lang !== $default_lang && class_exists( 'Align_Language_Manager' ) ) {
					$translations = Align_Language_Manager::get_translations( $post_id );
					foreach ( $translations as $tr ) {
						if ( $tr['lang'] === $current_lang && (int) $tr['post_id'] !== $post_id ) {
							$post_id = (int) $tr['post_id'];
							break;
						}
					}
				}

				$post = get_post( $post_id );
				if ( ! $post || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
					return $full_tag;
				}

				$permalink = get_permalink( $post_id );
				if ( ! $permalink ) {
					return $full_tag;
				}

				$escaped_url = esc_url( wp_make_link_relative( $permalink ) ?: '/' );

				// Replace existing href or append new href attribute, and update data-align-link-id to resolved ID
				if ( preg_match( '/\bhref\s*=\s*(?:([\'"])(.*?)\1|([^\s>]+))/is', $attrs ) ) {
					$new_attrs = preg_replace( '/\bhref\s*=\s*(?:([\'"])(.*?)\1|([^\s>]+))/is', 'href="' . $escaped_url . '"', $attrs );
				} else {
					$new_attrs = $attrs . ' href="' . $escaped_url . '"';
				}

				$new_attrs = preg_replace( '/\bdata-align-link-id\s*=\s*[\'"]?\d+[\'"]?/i', 'data-align-link-id="' . $post_id . '"', $new_attrs );

				return '<a' . $new_attrs . '>';
			},
			$content
		);
	}
}

if ( empty( $content ) || '' === trim( $content ) ) {
	if ( ! empty( $attributes['content'] ) && '' !== trim( $attributes['content'] ) ) {
		$content = $attributes['content'];
	} else {
		return;
	}
}

$raw_br_count = preg_match_all( '/<br\b[^>]*>/i', $content, $raw_br_m );
$html         = $content;

// 1. Process shortcodes and builder components if enabled (default true)
$enable_shortcodes  = ! isset( $attributes['enableShortcode'] ) || (bool) $attributes['enableShortcode'];
$post_components_br = $raw_br_count;
$post_shortcode_br  = $raw_br_count;

if ( $enable_shortcodes ) {
	if ( function_exists( 'align_parse_and_render_builder_components' ) ) {
		$html               = align_parse_and_render_builder_components( $html );
		$post_components_br = preg_match_all( '/<br\b[^>]*>/i', $html, $comp_br_m );
	}
	if ( function_exists( 'do_shortcode' ) ) {
		$html              = do_shortcode( $html );
		$post_shortcode_br = preg_match_all( '/<br\b[^>]*>/i', $html, $sc_br_m );
	}
}

// 1.1 Resolve hybrid dynamic image srcset & picture art-direction
if ( class_exists( 'Align_Builder_Image_Resolver' ) ) {
	$html = Align_Builder_Image_Resolver::resolve_dynamic_image_srcset( $html );
}

// 2. Resolve dynamic post links
$html = align_resolve_dynamic_post_links( $html );

// 2.1 Localize internal links for non-default language views
if ( class_exists( 'Align_Language_Url_Localizer' ) && class_exists( 'Align_Template_Language' ) ) {
	$curr_lang = Align_Template_Language::get_current_lang();
	$def_lang  = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en';
	if ( ! empty( $curr_lang ) && $curr_lang !== $def_lang ) {
		$html = Align_Language_Url_Localizer::localize_html( $html, $curr_lang );
	}
}

// 3. Process and enqueue external builder assets if enabled
if ( class_exists( 'Align_Builder_Assets' ) ) {
	if ( ! empty( $attributes['styleId'] ) ) {
		Align_Builder_Assets::enqueue_disk_asset( 'css', (string) $attributes['styleId'] );
	}
	if ( ! empty( $attributes['scriptId'] ) ) {
		Align_Builder_Assets::enqueue_disk_asset( 'js', (string) $attributes['scriptId'] );
	}
	if ( method_exists( 'Align_Builder_Assets', 'process_and_enqueue_external_assets' ) ) {
		$html = Align_Builder_Assets::process_and_enqueue_external_assets( $html );
	}
}

// 4. Strip hidden elements from frontend output
if ( function_exists( 'align_strip_hidden_elements' ) ) {
	$html = align_strip_hidden_elements( $html );
}

// 5. Output clean HTML DOM (zero-wrapper mode for maximum semantic purity and flat DOM)
if ( preg_match( '/^<div\b[^>]*\bclass="[^"]*\balign-custom-html-block\b[^"]*"[^>]*>\s*([\s\S]*?)\s*<\/div>$/i', trim( $html ), $legacy_wrapper_m ) ) {
	$html = trim( $legacy_wrapper_m[1] );
}

$final_br_count = preg_match_all( '/<br\b[^>]*>/i', $html, $fin_br_m );

if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'ALIGN_DEBUG' ) && ALIGN_DEBUG ) ) {
	$block_identifier = ! empty( $attributes['blockId'] ) ? $attributes['blockId'] : 'custom-html';
	error_log( sprintf(
		'[Align CustomHTML Block] Render [%s] | Raw BR: %d | Post-Components BR: %d | Post-Shortcode BR: %d | Final BR: %d | Length: %d',
		$block_identifier,
		$raw_br_count,
		$post_components_br,
		$post_shortcode_br,
		$final_br_count,
		strlen( $html )
	) );
}

if ( ! empty( $attributes['className'] ) ) {
	$block_id    = esc_attr( ! empty( $attributes['blockId'] ) ? $attributes['blockId'] : 'align-html-' . substr( md5( uniqid( '', true ) ), 0, 8 ) );
	$extra_class = ' ' . esc_attr( $attributes['className'] );
	echo '<div id="' . $block_id . '" class="align-custom-html-block' . $extra_class . '">' . "\n" . $html . "\n" . '</div>';
} else {
	echo $html;
}
