<?php
/**
 * Align Theme - Block Translator Tree Traversal & Slot Injection
 *
 * Recursively traverses parsed Gutenberg / Align block trees to extract translatable
 * text slots while preserving 100% of HTML tags, tag names, CSS classes, inner SVGs,
 * and element attributes.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Builder_Block_Translator {

	/**
	 * Recursively traverses parsed block tree to extract human-readable text strings
	 * while retaining pointers to the exact slot in the block attributes or innerHTML.
	 *
	 * @param array $blocks Array of parsed blocks (by reference).
	 * @param array $texts  Array of extracted string values.
	 * @param array $slots  Array of slot references and types.
	 */
	public static function collect_translatable_slots( &$blocks, &$texts, &$slots ) {
		foreach ( $blocks as &$b ) {
			$name = $b['blockName'] ?? '';
			if ( empty( $name ) && empty( trim( $b['innerHTML'] ?? '' ) ) ) {
				continue;
			}

			// 0. Align Custom HTML block or raw HTML content
			if ( 'align/custom-html' === $name || ( empty( $name ) && ! empty( trim( $b['innerHTML'] ?? '' ) ) ) ) {
				$raw_html = $b['innerHTML'] ?? '';
				if ( ! empty( $raw_html ) ) {
					$doc = new DOMDocument();
					libxml_use_internal_errors( true );
					$doc->loadHTML( '<div>' . mb_convert_encoding( $raw_html, 'HTML-ENTITIES', 'UTF-8' ) . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
					libxml_clear_errors();

					$xpath = new DOMXPath( $doc );
					$text_nodes = $xpath->query( '//text()[not(ancestor::script) and not(ancestor::style) and not(ancestor::code) and not(ancestor::pre) and not(ancestor::svg)]' );

					foreach ( $text_nodes as $tn ) {
						$val = trim( $tn->nodeValue );
						if ( mb_strlen( $val ) >= 2 && preg_match( '/[a-zA-Z\x{4e00}-\x{9fff}]{2,}/u', $val ) ) {
							$texts[] = $val;
							$slots[] = array(
								'type'     => 'html_text_node',
								'node'     => $tn,
								'doc'      => $doc,
								'block'    => &$b,
								'orig_txt' => $val,
							);
						}
					}

					// Collect input/textarea placeholders
					$form_placeholders = $xpath->query( '//input[@placeholder] | //textarea[@placeholder]' );
					foreach ( $form_placeholders as $fnode ) {
						$val = trim( $fnode->getAttribute( 'placeholder' ) );
						if ( mb_strlen( $val ) >= 2 && preg_match( '/[a-zA-Z\x{4e00}-\x{9fff}]{2,}/u', $val ) ) {
							$texts[] = $val;
							$slots[] = array(
								'type'     => 'html_attr_placeholder',
								'node'     => $fnode,
								'doc'      => $doc,
								'block'    => &$b,
								'orig_txt' => $val,
							);
						}
					}

					// Collect submit/button values
					$form_buttons = $xpath->query( '//input[(@type="submit" or @type="button") and @value]' );
					foreach ( $form_buttons as $bnode ) {
						$val = trim( $bnode->getAttribute( 'value' ) );
						if ( mb_strlen( $val ) >= 2 && preg_match( '/[a-zA-Z\x{4e00}-\x{9fff}]{2,}/u', $val ) ) {
							$texts[] = $val;
							$slots[] = array(
								'type'     => 'html_attr_value',
								'node'     => $bnode,
								'doc'      => $doc,
								'block'    => &$b,
								'orig_txt' => $val,
							);
						}
					}
				}
				continue;
			}

			// 1. Align Element dynamic block (Preserves tag, className, dataEditId, SVG, etc.)
			if ( 'align/element' === $name ) {
				$tag     = strtolower( $b['attrs']['tag'] ?? 'div' );
				$content = trim( $b['attrs']['content'] ?? '' );

				// Never translate raw SVG icons/elements
				if ( 'svg' !== $tag && ! empty( $content ) ) {
					$texts[] = $content;
					$slots[] = array(
						'type'  => 'align_element_content',
						'block' => &$b,
					);
				}

				// Process innerBlocks (e.g. <a> containing <span> and <svg>, <ul> containing <li>)
				if ( ! empty( $b['innerBlocks'] ) ) {
					self::collect_translatable_slots( $b['innerBlocks'], $texts, $slots );
				}
				continue;
			}

			// 2. Gutenberg Native Heading
			if ( 'core/heading' === $name ) {
				$clean = trim( wp_strip_all_tags( $b['innerHTML'] ?? '' ) );
				if ( ! empty( $clean ) ) {
					$texts[] = $clean;
					$slots[] = array(
						'type'  => 'core_heading',
						'block' => &$b,
					);
				}
				continue;
			}

			// 3. Gutenberg Native Quote
			if ( 'core/quote' === $name ) {
				$clean = trim( wp_strip_all_tags( $b['innerHTML'] ?? '' ) );
				if ( ! empty( $clean ) ) {
					$texts[] = $clean;
					$slots[] = array(
						'type'  => 'core_quote',
						'block' => &$b,
					);
				}
				if ( ! empty( $b['innerBlocks'] ) ) {
					self::collect_translatable_slots( $b['innerBlocks'], $texts, $slots );
				}
				continue;
			}

			// 4. Gutenberg Native List item
			if ( 'core/list-item' === $name ) {
				$clean = trim( wp_strip_all_tags( $b['innerHTML'] ?? '' ) );
				if ( ! empty( $clean ) ) {
					$texts[] = $clean;
					$slots[] = array(
						'type'  => 'core_list_item',
						'block' => &$b,
					);
				}
				continue;
			}

			// 5. Gutenberg Native List container
			if ( 'core/list' === $name ) {
				if ( ! empty( $b['innerBlocks'] ) ) {
					self::collect_translatable_slots( $b['innerBlocks'], $texts, $slots );
				} else {
					$inner = $b['innerHTML'] ?? '';
					if ( preg_match_all( '#<li[^>]*>(.*?)</li>#is', $inner, $matches ) ) {
						foreach ( $matches[1] as $li_html ) {
							$clean = trim( wp_strip_all_tags( $li_html ) );
							if ( ! empty( $clean ) ) {
								$texts[] = $clean;
								$slots[] = array(
									'type'     => 'core_list_raw_li',
									'block'    => &$b,
									'orig_txt' => $clean,
								);
							}
						}
					}
				}
				continue;
			}

			// 6. Gutenberg Native Paragraph
			if ( 'core/paragraph' === $name ) {
				$inner          = $b['innerHTML'] ?? '';
				$has_inner_tags = (bool) preg_match( '#<a|span|svg|strong|em|small|code#i', $inner );
				$clean          = trim( wp_strip_all_tags( $inner ) );
				if ( ! empty( $clean ) ) {
					$texts[] = $clean;
					$slots[] = array(
						'type'     => $has_inner_tags ? 'core_paragraph_html' : 'core_paragraph',
						'block'    => &$b,
						'orig_txt' => $clean,
					);
				}
				continue;
			}

			// Recurse into any other container blocks
			if ( ! empty( $b['innerBlocks'] ) ) {
				self::collect_translatable_slots( $b['innerBlocks'], $texts, $slots );
			}
		}
	}

	/**
	 * Injects translated text strings back into the block slots.
	 * Preserves all tag names, attributes, CSS classes, inner elements (svg, span).
	 *
	 * @param array $slots      Slot definitions from collect_translatable_slots.
	 * @param array $translated Array of translated strings (same order).
	 * @param array $original   Array of original strings (fallback).
	 */
	public static function apply_translated_slots( &$slots, $translated, $original ) {
		$docs_to_finalize = array();

		foreach ( $slots as $idx => &$slot ) {
			$trans = trim( $translated[ $idx ] ?? $original[ $idx ] ?? '' );
			if ( empty( $trans ) ) {
				continue;
			}

			$type = $slot['type'];

			if ( 'html_text_node' === $type ) {
				if ( isset( $slot['node'] ) && $slot['node'] instanceof DOMText ) {
					$slot['node']->nodeValue = $trans;
					$doc_id = spl_object_hash( $slot['doc'] );
					if ( ! isset( $docs_to_finalize[ $doc_id ] ) ) {
						$docs_to_finalize[ $doc_id ] = array(
							'doc'   => $slot['doc'],
							'block' => &$slot['block'],
						);
					}
				}
				continue;
			}

			if ( 'html_attr_placeholder' === $type || 'html_attr_value' === $type ) {
				if ( isset( $slot['node'] ) && $slot['node'] instanceof DOMElement ) {
					$attr_name = ( 'html_attr_placeholder' === $type ) ? 'placeholder' : 'value';
					$slot['node']->setAttribute( $attr_name, $trans );
					$doc_id = spl_object_hash( $slot['doc'] );
					if ( ! isset( $docs_to_finalize[ $doc_id ] ) ) {
						$docs_to_finalize[ $doc_id ] = array(
							'doc'   => $slot['doc'],
							'block' => &$slot['block'],
						);
					}
				}
				continue;
			}

			if ( 'align_element_content' === $type ) {
				$slot['block']['attrs']['content'] = $trans;
			} elseif ( 'core_heading' === $type ) {
				$slot['block']['innerHTML'] = preg_replace( '#(<h[1-6][^>]*>).*?(</h[1-6]>)#is', '${1}' . esc_html( $trans ) . '${2}', $slot['block']['innerHTML'] );
				$slot['block']['innerContent'][0] = $slot['block']['innerHTML'];
			} elseif ( 'core_paragraph' === $type ) {
				$slot['block']['innerHTML'] = preg_replace( '#(<p[^>]*>).*?(</p>)#is', '${1}' . esc_html( $trans ) . '${2}', $slot['block']['innerHTML'] );
				$slot['block']['innerContent'][0] = $slot['block']['innerHTML'];
			} elseif ( 'core_paragraph_html' === $type ) {
				$orig_clean = $slot['orig_txt'] ?? '';
				if ( ! empty( $orig_clean ) && false !== strpos( $slot['block']['innerHTML'], $orig_clean ) ) {
					$slot['block']['innerHTML'] = str_replace( $orig_clean, esc_html( $trans ), $slot['block']['innerHTML'] );
				} else {
					$slot['block']['innerHTML'] = preg_replace( '#(<p[^>]*>).*?(</p>)#is', '${1}' . esc_html( $trans ) . '${2}', $slot['block']['innerHTML'] );
				}
				$slot['block']['innerContent'][0] = $slot['block']['innerHTML'];
			} elseif ( 'core_quote' === $type ) {
				$slot['block']['innerHTML'] = preg_replace( '#(<blockquote[^>]*>\s*<p[^>]*>).*?(</p>\s*</blockquote>)#is', '${1}' . esc_html( $trans ) . '${2}', $slot['block']['innerHTML'] );
				$slot['block']['innerContent'][0] = $slot['block']['innerHTML'];
			} elseif ( 'core_list_item' === $type ) {
				$slot['block']['innerHTML'] = preg_replace( '#(<li[^>]*>).*?(</li>)#is', '${1}' . esc_html( $trans ) . '${2}', $slot['block']['innerHTML'] );
				$slot['block']['innerContent'][0] = $slot['block']['innerHTML'];
			} elseif ( 'core_list_raw_li' === $type ) {
				$orig_clean = $slot['orig_txt'] ?? '';
				if ( ! empty( $orig_clean ) ) {
					$slot['block']['innerHTML'] = str_replace( $orig_clean, esc_html( $trans ), $slot['block']['innerHTML'] );
					$slot['block']['innerContent'][0] = $slot['block']['innerHTML'];
				}
			}
		}

		if ( ! empty( $docs_to_finalize ) ) {
			foreach ( $docs_to_finalize as &$item ) {
				$wrapper  = $item['doc']->getElementsByTagName( 'div' )->item( 0 );
				$new_html = '';
				if ( $wrapper ) {
					foreach ( $wrapper->childNodes as $child ) {
						$new_html .= $item['doc']->saveHTML( $child );
					}
				} else {
					$new_html = $item['doc']->saveHTML();
				}
				$item['block']['innerHTML'] = $new_html;
				if ( isset( $item['block']['innerContent'][0] ) ) {
					$item['block']['innerContent'][0] = $new_html;
				}
			}
		}
	}
}
