<?php
/**
 * Content Filters & Frontend Markup Transformer for Align Theme.
 *
 * Handles root-relative URL rewriting, gallery normalization,
 * and builder dynamic component replacements.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Convert internal absolute URLs to root-relative URLs in HTML content.
 * Only runs on frontend to keep REST API, Admin Editor, and SEO tags intact.
 *
 * @param string $content The HTML content to filter.
 * @return string Filtered HTML content with root-relative internal links.
 */
function align_convert_internal_to_relative( $content ) {
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || empty( $content ) || ! is_string( $content ) ) {
		return $content;
	}
	$home_url    = home_url();
	$parsed_home = wp_parse_url( $home_url );
	$host        = isset( $parsed_home['host'] ) ? $parsed_home['host'] : '';
	if ( isset( $parsed_home['port'] ) ) {
		$host .= ':' . $parsed_home['port'];
	}
	if ( empty( $host ) || strpos( $content, $host) === false ) {
		return $content;
	}
	$escaped_host = preg_quote( $host, '/' );

	// 1. WordPress 6.2+ HTML API Implementation (WP_HTML_Tag_Processor)
	if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
		$processor    = new WP_HTML_Tag_Processor( $content );
		$url_attrs    = array( 'href', 'src', 'data-src', 'data-poster', 'poster', 'action' );
		$srcset_attrs = array( 'srcset', 'data-srcset' );

		while ( $processor->next_tag() ) {
			foreach ( $url_attrs as $attr ) {
				$val = $processor->get_attribute( $attr );
				if ( ! empty( $val ) && is_string( $val ) && strpos( $val, $host ) !== false ) {
					if ( preg_match( '/^(?:https?:)?\/\/' . $escaped_host . '([^#?]*)(.*)$/i', $val, $matches ) ) {
						$path = empty( $matches[1] ) ? '/' : $matches[1];
						if ( $path[0] !== '/' && $path[0] !== '?' && $path[0] !== '#' ) {
							$path = '/' . $path;
						}
						$processor->set_attribute( $attr, $path . $matches[2] );
					}
				}
			}
			foreach ( $srcset_attrs as $attr ) {
				$val = $processor->get_attribute( $attr );
				if ( ! empty( $val ) && is_string( $val ) && strpos( $val, $host ) !== false ) {
					$parts           = explode( ',', $val );
					$converted_parts = array();
					foreach ( $parts as $part ) {
						$part = trim( $part );
						if ( empty( $part ) ) {
							continue;
						}
						if ( preg_match( '/^((?:https?:)?\/\/' . $escaped_host . ')([^\s]*)(.*)$/i', $part, $pm ) ) {
							$path = empty( $pm[2] ) ? '/' : $pm[2];
							if ( $path[0] !== '/' && $path[0] !== '?' && $path[0] !== '#' ) {
								$path = '/' . $path;
							}
							$converted_parts[] = $path . $pm[3];
						} else {
							$converted_parts[] = $part;
						}
					}
					$processor->set_attribute( $attr, implode( ', ', $converted_parts ) );
				}
			}
		}
		return $processor->get_updated_html();
	}

	// 2. Fallback Regex pattern for URL attributes
	$pattern = '/(href|src|data-src|data-poster|poster|action)=["\']((?:https?:)?\/\/' . $escaped_host . ')([^"\']*)["\']/i';
	$content = preg_replace_callback( $pattern, function( $matches ) {
		$path = empty( $matches[3] ) ? '/' : $matches[3];
		if ( ! empty( $path ) && $path[0] !== '/' && $path[0] !== '?' && $path[0] !== '#' ) {
			$path = '/' . $path;
		}
		return $matches[1] . '="' . $path . '"';
	}, $content );

	// Fallback Regex for srcset and data-srcset attributes
	$srcset_pattern = '/(srcset|data-srcset)=["\']([^"\']+)["\']/i';
	$content = preg_replace_callback( $srcset_pattern, function( $matches ) use ( $escaped_host ) {
		$parts           = explode( ',', $matches[2] );
		$converted_parts = array();
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( empty( $part ) ) {
				continue;
			}
			if ( preg_match( '/^((?:https?:)?\/\/' . $escaped_host . ')([^\s]*)(.*)$/i', $part, $pm ) ) {
				$path = empty( $pm[2] ) ? '/' : $pm[2];
				if ( ! empty( $path ) && $path[0] !== '/' && $path[0] !== '?' && $path[0] !== '#' ) {
					$path = '/' . $path;
				}
				$converted_parts[] = $path . $pm[3];
			} else {
				$converted_parts[] = $part;
			}
		}
		return $matches[1] . '="' . implode( ', ', $converted_parts ) . '"';
	}, $content );

	return $content;
}

// Hook into frontend output mechanisms
add_filter( 'the_content', 'align_convert_internal_to_relative', 999 );
add_filter( 'wp_nav_menu', 'align_convert_internal_to_relative', 999 );
add_filter( 'widget_text', 'align_convert_internal_to_relative', 999 );
add_filter( 'widget_block_content', 'align_convert_internal_to_relative', 999 );
add_filter( 'render_block', function( $block_content, $block ) {
	return align_convert_internal_to_relative( $block_content );
}, 999, 2 );

require_once __DIR__ . '/content-formatting.php';

/**
 * Protect inline <script> and <style> tags from WordPress wptexturize entity encoding (&#038; -> &).
 * Guarantees zero SyntaxErrors in frontend JavaScript execution for anonymous and logged-in visitors.
 *
 * @param string $content HTML content.
 * @return string Content with decoded script/style entities.
 */
function align_fix_inline_scripts_entities( $content ) {
	if ( empty( $content ) || ! is_string( $content ) || ( strpos( $content, '<script' ) === false && strpos( $content, '<style' ) === false ) ) {
		return $content;
	}

	$content = preg_replace_callback( '/<script\b([^>]*)>([\s\S]*?)<\/script>/is', function( $matches ) {
		$attrs = $matches[1];
		$code  = $matches[2];
		$clean = str_replace( array( '&#038;', '&amp;' ), '&', $code );
		return '<script' . $attrs . '>' . $clean . '</script>';
	}, $content );

	$content = preg_replace_callback( '/<style\b([^>]*)>([\s\S]*?)<\/style>/is', function( $matches ) {
		$attrs = $matches[1];
		$css   = $matches[2];
		$clean = str_replace( array( '&#038;', '&amp;' ), '&', $css );
		return '<style' . $attrs . '>' . $clean . '</style>';
	}, $content );

	return $content;
}
add_filter( 'the_content', 'align_fix_inline_scripts_entities', 998 );


/**
 * Generate fallback HTML for empty gallery shortcode.
 *
 * @return string Fallback gallery HTML markup.
 */
function align_generate_fallback_gallery_html() {
	$svg   = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.5"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>';
	$items = '';
	for ( $i = 1; $i <= 3; $i++ ) {
		$items .= '<div style="flex:1 1 200px;min-height:180px;background:#f3f4f6;border:1px dashed #d1d5db;border-radius:8px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;color:#6b7280;padding:16px;text-align:center;">' . $svg . '<span style="font-size:13px;font-weight:500;">Gallery Image ' . $i . '</span></div>';
	}
	return '<div class="align-fallback-gallery" style="display:flex;flex-wrap:wrap;gap:16px;margin:16px 0;width:100%;">' . $items . '</div>';
}

/**
 * Normalize shortcode syntax and provide smart fallback for empty parameters (e.g. gallery without IDs).
 *
 * @param string $shortcode Raw shortcode string.
 * @return string Normalized shortcode string.
 */
function align_normalize_shortcode( $shortcode ) {
	if ( empty( $shortcode ) || ! is_string( $shortcode ) ) {
		return $shortcode;
	}

	// Smart fallback for gallery shortcode without explicit IDs
	if ( preg_match( '/^\[gallery(\s*\]|\s+([^\]]*\]))$/i', trim( $shortcode ) ) ) {
		if ( stripos( $shortcode, 'ids=' ) === false && stripos( $shortcode, 'include=' ) === false ) {
			$latest = get_posts( array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'posts_per_page' => 8,
				'post_status'    => 'inherit',
				'fields'         => 'ids',
			) );
			if ( ! empty( $latest ) ) {
				$ids_str = implode( ',', $latest );
				if ( trim( $shortcode ) === '[gallery]' ) {
					return '[gallery ids="' . $ids_str . '"]';
				} else {
					return str_replace( '[gallery', '[gallery ids="' . $ids_str . '"', $shortcode );
				}
			}
		}
	}

	return $shortcode;
}

/**
 * Helper function to replace builder component element node with rendered HTML content.
 *
 * @param DOMDocument $dom      The parent DOMDocument instance.
 * @param DOMNode     $node     The builder component element node.
 * @param string      $rendered Rendered HTML content.
 */
function align_replace_node_with_html( $dom, $node, $rendered ) {
	if ( ! empty( trim( $rendered ) ) && is_string( $rendered ) ) {
		// If rendered output contains a full HTML document (e.g. from standalone views), extract body content
		if ( preg_match( '/<body\b[^>]*>(.*?)<\/body>/is', $rendered, $body_m ) ) {
			$clean_rendered = trim( $body_m[1] );
		} else {
			$clean_rendered = preg_replace( '/^<!DOCTYPE\b[^>]*>/i', '', trim( $rendered ) );
		}

		$frag_dom = new DOMDocument();
		$prev_err = libxml_use_internal_errors( true );
		$frag_dom->loadHTML( '<?xml encoding="UTF-8"?><align-frag>' . $clean_rendered . '</align-frag>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev_err );

		$frag_root = $frag_dom->getElementsByTagName( 'align-frag' )->item( 0 );
		$target_nodes = ( $frag_root && $frag_root->childNodes ) ? $frag_root->childNodes : $frag_dom->childNodes;

		if ( $target_nodes ) {
			$children = array();
			foreach ( $target_nodes as $child ) {
				$children[] = $child;
			}
			foreach ( $children as $child ) {
				if ( defined( 'XML_PI_NODE' ) && $child->nodeType === XML_PI_NODE ) {
					continue;
				}
				if ( defined( 'XML_DOCUMENT_TYPE_NODE' ) && $child->nodeType === XML_DOCUMENT_TYPE_NODE ) {
					continue;
				}
				try {
					$imported = $dom->importNode( $child, true );
					if ( $imported && $imported instanceof DOMNode && $node->parentNode ) {
						$node->parentNode->insertBefore( $imported, $node );
					}
				} catch ( \Throwable $e ) {
					// Fallback for unimportable node types
				}
			}
		}
	}

	if ( $node->parentNode ) {
		$node->parentNode->removeChild( $node );
	}
}

/**
 * Render dynamic post content with multi-level fallback for FSE templates.
 *
 * @param int   $post_id Optional explicit post ID.
 * @param array $args    Optional rendering arguments.
 * @return string Rendered post content HTML.
 */
function align_get_rendered_post_content( $post_id = 0, $args = array() ) {
	if ( class_exists( 'Align_Post_Content' ) ) {
		return Align_Post_Content::render( $post_id, $args );
	}
	$current_id = $post_id ?: ( get_the_ID() ?: ( function_exists( 'get_queried_object_id' ) ? get_queried_object_id() : 0 ) );
	$post       = $current_id ? get_post( $current_id ) : null;
	return ( $post && ! empty( $post->post_content ) ) ? apply_filters( 'the_content', $post->post_content ) : '';
}

/**
 * Parse and render Align Builder components (shortcodes, Gutenberg blocks, post content) on frontend.
 *
 * @param string $content HTML content to parse.
 * @return string Processed HTML content.
 */
function align_parse_and_render_builder_components( $content ) {
	static $processing_depth = 0;

	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return $content;
	}

	if ( $processing_depth >= 5 || empty( $content ) || ! is_string( $content ) ) {
		return $content;
	}

	$has_builder_components = ( strpos( $content, 'data-component-shortcode' ) !== false || strpos( $content, 'data-component-wp-block' ) !== false || strpos( $content, 'data-component-post-content' ) !== false );
	$has_raw_shortcodes     = ( strpos( $content, '[' ) !== false && strpos( $content, ']' ) !== false );

	if ( ! $has_builder_components && ! $has_raw_shortcodes ) {
		return $content;
	}

	$processing_depth++;

	try {
		// 1. Process Builder Components (Shortcodes, Gutenberg blocks, Post Content)
		if ( $has_builder_components ) {
			$dom_success = false;

			if ( class_exists( 'DOMDocument' ) && class_exists( 'DOMXPath' ) ) {
				$dom      = new DOMDocument();
				$prev_err = libxml_use_internal_errors( true );
				$loaded   = $dom->loadHTML( '<?xml encoding="UTF-8"?><align-root>' . $content . '</align-root>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
				libxml_clear_errors();
				libxml_use_internal_errors( $prev_err );

				if ( $loaded ) {
					$xpath = new DOMXPath( $dom );
					$nodes = $xpath->query( '//*[@data-component-shortcode or @data-component-wp-block or @data-component-post-content]' );

					if ( $nodes && $nodes->length > 0 ) {
						$dom_success = true;
						$nodes_array = array();
						foreach ( $nodes as $node ) {
							$nodes_array[] = $node;
						}

						foreach ( $nodes_array as $node ) {
							if ( $node->hasAttribute( 'data-component-shortcode' ) ) {
								$shortcode = $node->getAttribute( 'data-shortcode' );
								if ( ! empty( $shortcode ) ) {
									$shortcode = html_entity_decode( $shortcode, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
									$shortcode = align_normalize_shortcode( $shortcode );
									$rendered  = do_shortcode( $shortcode );
									if ( '' === trim( $rendered ) ) {
										$rendered = preg_match( '/^\[gallery\b/i', $shortcode ) ? align_generate_fallback_gallery_html() : '<!-- shortcode:' . esc_html( $shortcode ) . ' returned empty -->';
									}
									align_replace_node_with_html( $dom, $node, $rendered );
								} elseif ( $node->parentNode ) {
									$node->parentNode->removeChild( $node );
								}
							} elseif ( $node->hasAttribute( 'data-component-wp-block' ) ) {
								$raw_block = trim( $node->getAttribute( 'data-wp-block' ) );
								if ( ! empty( $raw_block ) ) {
									$block_content = html_entity_decode( $raw_block, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
									$rendered      = do_shortcode( do_blocks( $block_content ) );
									align_replace_node_with_html( $dom, $node, $rendered );
								} elseif ( $node->parentNode ) {
									$node->parentNode->removeChild( $node );
								}
							} elseif ( $node->hasAttribute( 'data-component-post-content' ) ) {
								$custom_id    = $node->getAttribute( 'data-post-id' );
								$target_id    = ! empty( $custom_id ) ? intval( $custom_id ) : 0;
								$post_content = align_get_rendered_post_content( $target_id );
								align_replace_node_with_html( $dom, $node, $post_content );
							}
						}
					}

					// Remove Editor Header Labels (.shortcode-header, .block-header)
					$header_nodes = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " shortcode-header ") or contains(concat(" ", normalize-space(@class), " "), " block-header ")]' );
					if ( $header_nodes && $header_nodes->length > 0 ) {
						foreach ( iterator_to_array( $header_nodes ) as $hn ) {
							if ( $hn->parentNode ) {
								$hn->parentNode->removeChild( $hn );
							}
						}
					}

					// Remove Editor Helper Classes from class attributes
					$helper_classes = array( 'border-dashed', 'border-primary', 'border-info', 'border-success', 'bg-light', 'p-3', 'my-2', 'my-3' );
					$class_nodes    = $xpath->query( '//*[@class]' );
					if ( $class_nodes && $class_nodes->length > 0 ) {
						foreach ( $class_nodes as $cn ) {
							$current_classes  = preg_split( '/\s+/', trim( $cn->getAttribute( 'class' ) ) );
							$filtered_classes = array_diff( $current_classes, $helper_classes );
							if ( ! empty( $filtered_classes ) ) {
								$cn->setAttribute( 'class', implode( ' ', $filtered_classes ) );
							} else {
								$cn->removeAttribute( 'class' );
							}
						}
					}

					$root_node = $dom->getElementsByTagName( 'align-root' )->item( 0 );
					$output    = '';
					if ( $root_node && $root_node->childNodes ) {
						foreach ( $root_node->childNodes as $child ) {
							$output .= $dom->saveHTML( $child );
						}
					} else {
						$output = $dom->saveHTML();
					}

					if ( ! empty( $output ) && is_string( $output ) ) {
						$output      = preg_replace( '/<\?xml[^>]*\?>/i', '', $output );
						$output      = preg_replace_callback( '/<(style|script)\b([^>]*)>(.*?)<\/\1>/is', function( $m ) {
							$code = str_replace( array( '&gt;', '&lt;', '&amp;', '&quot;', '&#039;' ), array( '>', '<', '&', '"', "'" ), $m[3] );
							return '<' . $m[1] . $m[2] . '>' . $code . '</' . $m[1] . '>';
						}, $output );
						$content     = trim( $output );
						$dom_success = true;
					}
				}
			}

			// 2. Fallback Regex Parsing — only runs if DOMDocument was unavailable or failed
			if ( ! $dom_success ) {
				if ( strpos( $content, 'data-component-shortcode' ) !== false ) {
					$pattern_shortcode = '/<([a-zA-Z0-9]+)\b([^>]*\bdata-component-shortcode\b[^>]*)>(.*?)<\/\1>/s';
					$content = preg_replace_callback( $pattern_shortcode, function( $matches ) {
						if ( preg_match( '/data-shortcode=(["\'])(.*?)\1/s', $matches[2], $attr_match ) ) {
							$shortcode_raw = trim( $attr_match[2] );
							if ( empty( $shortcode_raw ) ) {
								return '';
							}
							$shortcode = align_normalize_shortcode( html_entity_decode( $shortcode_raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
							$rendered  = do_shortcode( $shortcode );
							if ( '' === trim( $rendered ) ) {
								return preg_match( '/^\[gallery\b/i', $shortcode ) ? align_generate_fallback_gallery_html() : '<!-- shortcode:' . esc_html( $shortcode ) . ' returned empty -->';
							}
							return $rendered;
						}
						return '';
					}, $content );
				}

				if ( strpos( $content, 'data-component-wp-block' ) !== false ) {
					$pattern_block = '/<([a-zA-Z0-9]+)\b([^>]*\bdata-component-wp-block\b[^>]*)>(.*?)<\/\1>/s';
					$content = preg_replace_callback( $pattern_block, function( $matches ) {
						if ( preg_match( '/data-wp-block=(["\'])(.*?)\1/s', $matches[2], $attr_match ) ) {
							$block_raw = trim( $attr_match[2] );
							if ( empty( $block_raw ) ) {
								return '';
							}
							$block_content = html_entity_decode( $block_raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
							return do_shortcode( do_blocks( $block_content ) );
						}
						return '';
					}, $content );
				}

				if ( strpos( $content, 'data-component-post-content' ) !== false ) {
					$pattern_post = '/<([a-zA-Z0-9]+)\b([^>]*\bdata-component-post-content\b[^>]*)>(.*?)<\/\1>/s';
					$content      = preg_replace_callback( $pattern_post, function( $matches ) {
						$target_id = 0;
						if ( preg_match( '/data-post-id=(["\'])(.*?)\1/s', $matches[2], $id_match ) ) {
							$target_id = intval( $id_match[2] );
						}
						return align_get_rendered_post_content( $target_id );
					}, $content );
				}

				$content = preg_replace( '/<([a-zA-Z0-9]+)\b[^>]*class=["\'][^"\']*\b(shortcode-header|block-header)\b[^"\']*["\'][^>]*>[\s\S]*?<\/\1>/i', '', $content );

				$helper_classes = array( 'border-dashed', 'border-primary', 'border-info', 'border-success', 'bg-light', 'p-3', 'my-2', 'my-3' );
				$content = preg_replace_callback( '/class=(["\'])(.*?)\1/i', function( $matches ) use ( $helper_classes ) {
					$current_classes  = preg_split( '/\s+/', trim( $matches[2] ) );
					$filtered_classes = array_diff( $current_classes, $helper_classes );
					return ! empty( $filtered_classes ) ? 'class=' . $matches[1] . implode( ' ', $filtered_classes ) . $matches[1] : '';
				}, $content );
			}
		}

		// 3. Fallback do_shortcode for raw shortcodes [...]
		if ( strpos( $content, '[' ) !== false && strpos( $content, ']' ) !== false ) {
			$content = do_shortcode( $content );
			if ( strpos( $content, 'data-component-shortcode' ) !== false || strpos( $content, 'data-component-wp-block' ) !== false || strpos( $content, 'data-component-post-content' ) !== false ) {
				$content = align_parse_and_render_builder_components( $content );
			}
		}
	} finally {
		$processing_depth--;
	}

	return $content;
}

// Hook component renderer into frontend filters at priority 12 (after wpautop at 10 and do_shortcode at 11)
add_filter( 'the_content', 'align_parse_and_render_builder_components', 12 );
add_filter( 'widget_text', 'align_parse_and_render_builder_components', 12 );
add_filter( 'widget_block_content', 'align_parse_and_render_builder_components', 12 );
add_filter( 'render_block', function( $block_content, $block ) {
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return $block_content;
	}
	return align_parse_and_render_builder_components( $block_content );
}, 12, 2 );
