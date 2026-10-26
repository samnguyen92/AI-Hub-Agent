<?php
/**
 * Align Agent Navigation DOM Engine
 *
 * Handles DOM parsing, link extraction, and fragment manipulation for navigation menus.
 *
 * @package Align_Agent
 * @subpackage Includes\Tools\Structure
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Nav_Dom_Engine {

	/**
	 * Apply navigation operations onto template HTML.
	 *
	 * @param string   $html      Original template HTML.
	 * @param array    $ops       List of operation specs.
	 * @param int|null $nav_index Optional targeted nav index.
	 * @return array{ok: bool, html?: string, before?: array, after?: array, warnings: array, error?: string, message?: string}
	 */
	public static function apply_operations( string $html, array $ops, ?int $nav_index = null ): array {
		$fragments = self::find_nav_fragments( $html );
		if ( empty( $fragments ) ) {
			return array(
				'ok'       => false,
				'error'    => 'no_nav_fragments',
				'message'  => 'No <nav> tags found in template HTML.',
				'warnings' => array(),
			);
		}

		if ( null !== $nav_index && ! isset( $fragments[ $nav_index ] ) ) {
			return array(
				'ok'       => false,
				'error'    => 'invalid_nav_index',
				'message'  => sprintf( 'Nav index %d out of bounds (found %d navs).', $nav_index, count( $fragments ) ),
				'warnings' => array(),
			);
		}

		$warnings = array();
		foreach ( $ops as $op_idx => $op ) {
			$action      = $op['op'] ?? '';
			$target_href = ( 'add' === $action ) ? ( $op['new_href'] ?? ( $op['href'] ?? '' ) ) : ( $op['new_href'] ?? '' );
			if ( '' !== $target_href ) {
				$v_url = self::validate_href( (string) $target_href );
				if ( ! $v_url['valid'] ) {
					return array(
						'ok'       => false,
						'error'    => 'invalid_href',
						'message'  => sprintf( 'Operation %d has invalid href: %s', $op_idx + 1, $v_url['message'] ),
						'warnings' => array(),
					);
				}
				if ( ! empty( $v_url['warning'] ) ) {
					$warnings[] = $v_url['warning'];
				}
			}
		}

		$before_links = array();
		$after_links  = array();
		$replacements = array();

		foreach ( $fragments as $idx => $frag ) {
			if ( null !== $nav_index && $idx !== $nav_index ) {
				continue;
			}

			$doc = self::load_fragment( $frag['html'] );
			if ( ! $doc ) {
				return array( 'ok' => false, 'error' => 'dom_parse_error', 'message' => 'Failed to parse nav fragment.', 'warnings' => array() );
			}

			$root = $doc->getElementById( '__align_nav_root' );
			$nav_elem = null;
			foreach ( $root->childNodes as $child ) {
				if ( $child instanceof DOMElement && 'nav' === strtolower( $child->tagName ) ) {
					$nav_elem = $child;
					break;
				}
			}
			if ( ! $nav_elem ) {
				continue;
			}

			$before_links[ $idx ] = self::extract_links( $nav_elem );

			foreach ( $ops as $op_idx => $op ) {
				$res = self::apply_single_op( $doc, $nav_elem, $op );
				if ( ! $res['ok'] ) {
					return array(
						'ok'       => false,
						'error'    => $res['error'] ?? 'operation_failed',
						'message'  => sprintf( 'Nav %d operation %d ("%s") failed: %s', $idx, $op_idx + 1, $op['op'] ?? '', $res['message'] ?? 'error' ),
						'warnings' => array(),
					);
				}
			}

			$after_links[ $idx ] = self::extract_links( $nav_elem );
			$new_fragment_html   = self::save_fragment( $doc );

			$replacements[] = array(
				'offset'   => $frag['offset'],
				'length'   => $frag['length'],
				'new_html' => $new_fragment_html,
			);
		}

		usort( $replacements, function( $a, $b ) {
			return $b['offset'] <=> $a['offset'];
		} );

		$new_html = $html;
		foreach ( $replacements as $rep ) {
			$new_html = substr_replace( $new_html, $rep['new_html'], $rep['offset'], $rep['length'] );
		}

		return array(
			'ok'       => true,
			'html'     => $new_html,
			'before'   => $before_links,
			'after'    => $after_links,
			'warnings' => array_unique( $warnings ),
		);
	}

	private static function apply_single_op( DOMDocument $doc, DOMElement $nav, array $op ): array {
		$action = $op['op'] ?? '';
		$href   = (string) ( $op['href'] ?? '' );

		if ( 'add' === $action ) {
			return self::op_add( $doc, $nav, $op );
		}

		$target_a = self::find_anchor_by_href( $nav, $href );
		if ( ! $target_a ) {
			return array(
				'ok'      => false,
				'error'   => 'link_not_found',
				'message' => sprintf( 'Could not find link matching href "%s".', $href ),
			);
		}

		switch ( $action ) {
			case 'remove':
				$item = self::get_link_container_item( $target_a, $nav );
				$item->parentNode->removeChild( $item );
				return array( 'ok' => true );

			case 'rename':
				$new_label = (string) ( $op['label'] ?? '' );
				if ( '' === trim( $new_label ) ) {
					return array( 'ok' => false, 'error' => 'empty_label', 'message' => 'rename requires non-empty label.' );
				}
				self::update_anchor_label( $doc, $target_a, $new_label );
				return array( 'ok' => true );

			case 'change_href':
				$new_href = (string) ( $op['new_href'] ?? '' );
				if ( '' === trim( $new_href ) ) {
					return array( 'ok' => false, 'error' => 'empty_href', 'message' => 'change_href requires new_href.' );
				}
				$target_a->setAttribute( 'href', $new_href );
				if ( ! empty( $op['label'] ) ) {
					self::update_anchor_label( $doc, $target_a, (string) $op['label'] );
				}
				return array( 'ok' => true );

			case 'move':
				$after_href = (string) ( $op['after_href'] ?? '' );
				$item       = self::get_link_container_item( $target_a, $nav );
				$parent     = $item->parentNode;

				$item->parentNode->removeChild( $item );

				if ( '' === $after_href ) {
					$parent->appendChild( $item );
				} else {
					$ref_a = self::find_anchor_by_href( $nav, $after_href );
					if ( ! $ref_a ) {
						return array( 'ok' => false, 'error' => 'after_link_not_found', 'message' => sprintf( 'Reference link "%s" not found.', $after_href ) );
					}
					$ref_item = self::get_link_container_item( $ref_a, $nav );
					if ( $ref_item->nextSibling ) {
						$ref_item->parentNode->insertBefore( $item, $ref_item->nextSibling );
					} else {
						$ref_item->parentNode->appendChild( $item );
					}
				}
				return array( 'ok' => true );

			default:
				return array( 'ok' => false, 'error' => 'unknown_op', 'message' => sprintf( 'Unsupported op: %s', $action ) );
		}
	}

	private static function op_add( DOMDocument $doc, DOMElement $nav, array $op ): array {
		$new_href   = (string) ( $op['new_href'] ?? ( $op['href'] ?? '' ) );
		$label      = (string) ( $op['label'] ?? '' );
		$after_href = (string) ( $op['after_href'] ?? '' );

		if ( '' === trim( $new_href ) ) {
			return array( 'ok' => false, 'error' => 'missing_href', 'message' => 'add requires new_href or href.' );
		}
		if ( '' === trim( $label ) ) {
			return array( 'ok' => false, 'error' => 'missing_label', 'message' => 'add requires label.' );
		}

		$template_a = null;
		$all_as     = $nav->getElementsByTagName( 'a' );
		if ( $all_as->length > 0 ) {
			$template_a = $all_as->item( $all_as->length - 1 );
		}

		if ( ! $template_a ) {
			$new_a = $doc->createElement( 'a' );
			$new_a->setAttribute( 'href', $new_href );
			$new_a->appendChild( $doc->createTextNode( $label ) );
			$nav->appendChild( $new_a );
			return array( 'ok' => true );
		}

		$template_item = self::get_link_container_item( $template_a, $nav );
		$clone_item    = $template_item->cloneNode( true );

		$clone_a = ( $clone_item instanceof DOMElement && 'a' === strtolower( $clone_item->tagName ) )
			? $clone_item
			: $clone_item->getElementsByTagName( 'a' )->item( 0 );

		if ( ! $clone_a ) {
			return array( 'ok' => false, 'error' => 'clone_failed', 'message' => 'Cloned item missing <a> tag.' );
		}

		$clone_a->setAttribute( 'href', $new_href );
		self::update_anchor_label( $doc, $clone_a, $label );

		$clone_a->removeAttribute( 'aria-current' );
		$class_str = $clone_a->getAttribute( 'class' );
		if ( '' !== $class_str ) {
			$clean_classes = preg_replace( '/\b(?:is-active|current|active|current-menu-item)\b/i', '', $class_str );
			$clone_a->setAttribute( 'class', trim( preg_replace( '/\s+/', ' ', $clean_classes ) ) );
		}

		if ( $clone_item !== $clone_a ) {
			$sub_lists = $clone_item->getElementsByTagName( 'ul' );
			while ( $sub_lists->length > 0 ) {
				$sub = $sub_lists->item( 0 );
				$sub->parentNode->removeChild( $sub );
			}
		}

		$parent = $template_item->parentNode;

		if ( '' !== $after_href ) {
			$ref_a = self::find_anchor_by_href( $nav, $after_href );
			if ( $ref_a ) {
				$ref_item = self::get_link_container_item( $ref_a, $nav );
				if ( $ref_item->nextSibling ) {
					$ref_item->parentNode->insertBefore( $clone_item, $ref_item->nextSibling );
				} else {
					$ref_item->parentNode->appendChild( $clone_item );
				}
				return array( 'ok' => true );
			}
		}

		$parent->appendChild( $clone_item );
		return array( 'ok' => true );
	}

	private static function update_anchor_label( DOMDocument $doc, DOMElement $a, string $label ): void {
		$has_elements = false;
		foreach ( $a->childNodes as $child ) {
			if ( $child instanceof DOMElement ) {
				$has_elements = true;
				break;
			}
		}

		if ( ! $has_elements ) {
			$a->textContent = $label;
			return;
		}

		$last_text_node = null;
		foreach ( $a->childNodes as $child ) {
			if ( XML_TEXT_NODE === $child->nodeType && '' !== trim( $child->nodeValue ) ) {
				$last_text_node = $child;
			}
		}

		if ( $last_text_node ) {
			$last_text_node->nodeValue = ' ' . trim( $label );
		} else {
			$a->appendChild( $doc->createTextNode( ' ' . trim( $label ) ) );
		}
	}

	private static function get_link_container_item( DOMElement $a, DOMElement $nav ): DOMElement {
		$cur = $a;
		while ( $cur->parentNode && $cur->parentNode !== $nav ) {
			if ( $cur->parentNode instanceof DOMElement && 'li' === strtolower( $cur->parentNode->tagName ) ) {
				return $cur->parentNode;
			}
			$cur = $cur->parentNode;
		}
		return $a;
	}

	private static function find_anchor_by_href( DOMElement $nav, string $href ): ?DOMElement {
		$target_norm = self::normalize_href( $href );
		$all_as      = $nav->getElementsByTagName( 'a' );

		for ( $i = 0; $i < $all_as->length; $i++ ) {
			$a = $all_as->item( $i );
			if ( ! ( $a instanceof DOMElement ) ) {
				continue;
			}
			$a_href = self::normalize_href( $a->getAttribute( 'href' ) );
			if ( $a_href === $target_norm ) {
				return $a;
			}
		}

		return null;
	}

	public static function normalize_href( string $href ): string {
		$trimmed = trim( $href );
		if ( '/' === $trimmed || '' === $trimmed || 0 === strpos( $trimmed, '#' ) ) {
			return $trimmed;
		}
		return untrailingslashit( $trimmed );
	}

	private static function validate_href( string $href ): array {
		$href = trim( $href );
		if ( '' === $href ) {
			return array( 'valid' => false, 'message' => 'href cannot be empty.' );
		}

		if ( preg_match( '#^(?:javascript|data):#i', $href ) ) {
			return array( 'valid' => false, 'message' => 'javascript: and data: pseudo-protocols are forbidden.' );
		}

		if ( preg_match( '#^http://#i', $href ) ) {
			return array( 'valid' => false, 'message' => 'Insecure http:// URLs are forbidden. Use https:// or relative paths.' );
		}

		$warning = null;
		if ( 0 === strpos( $href, '/' ) && 0 !== strpos( $href, '//' ) && '/' !== $href ) {
			$post_id = url_to_postid( home_url( $href ) );
			if ( 0 === $post_id ) {
				$warning = sprintf( 'unresolved_href: "%s" does not resolve to an existing post or page.', $href );
			}
		}

		return array(
			'valid'   => true,
			'warning' => $warning,
		);
	}

	public static function find_nav_fragments( string $html ): array {
		$fragments = array();
		if ( preg_match_all( '#<nav\b[^>]*>.*?</nav>#is', $html, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[0] as $match ) {
				$fragments[] = array(
					'html'   => $match[0],
					'offset' => $match[1],
					'length' => strlen( $match[0] ),
				);
			}
		}
		return $fragments;
	}

	public static function load_fragment( string $fragment ): ?DOMDocument {
		$doc = new DOMDocument();
		$wrapped = '<?xml encoding="UTF-8"><div id="__align_nav_root">' . $fragment . '</div>';
		$prev    = libxml_use_internal_errors( true );
		$loaded  = $doc->loadHTML( $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		return $loaded ? $doc : null;
	}

	public static function save_fragment( DOMDocument $doc ): string {
		$root = $doc->getElementById( '__align_nav_root' );
		if ( ! $root ) {
			return '';
		}

		$out = '';
		foreach ( $root->childNodes as $child ) {
			$out .= $doc->saveHTML( $child );
		}
		return $out;
	}

	public static function extract_links( DOMElement $nav ): array {
		$links  = array();
		$all_as = $nav->getElementsByTagName( 'a' );

		for ( $i = 0; $i < $all_as->length; $i++ ) {
			$a = $all_as->item( $i );
			if ( ! ( $a instanceof DOMElement ) ) {
				continue;
			}

			$label = trim( $a->textContent );
			$href  = trim( $a->getAttribute( 'href' ) );

			if ( '' === $label && '' === $href ) {
				continue;
			}

			$depth = 0;
			$cur   = $a->parentNode;
			while ( $cur && $cur !== $nav ) {
				if ( $cur instanceof DOMElement && in_array( strtolower( $cur->tagName ), array( 'ul', 'ol' ), true ) ) {
					$depth++;
				}
				$cur = $cur->parentNode;
			}
			$depth = max( 0, $depth - 1 );

			$links[] = array(
				'label' => $label,
				'href'  => $href,
				'depth' => $depth,
			);
		}

		return $links;
	}
}
