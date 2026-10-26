<?php
/**
 * Block rendering filters for Breadcrumbs and CPT Taxonomies.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the breadcrumbs pattern dynamically on the frontend.
 *
 * @return string Rendered HTML.
 */
function align_render_breadcrumbs_html() {
	if ( ! class_exists( 'Align_SEO_Breadcrumbs' ) ) {
		return '';
	}

	$breadcrumbs = Align_SEO_Breadcrumbs::get_breadcrumbs();
	if ( empty( $breadcrumbs ) ) {
		return '';
	}

	$html  = '<!-- Breadcrumb Nav -->';
	$html .= '<nav class="align-breadcrumbs" aria-label="Breadcrumb">';

	$count = count( $breadcrumbs );
	foreach ( $breadcrumbs as $index => $item ) {
		$position = $index + 1;
		$name     = esc_html( $item['name'] );
		$url      = esc_url( $item['item'] );

		if ( 1 === $position ) {
			// Home item
			$html .= sprintf(
				'<a href="%s">%s</a>',
				$url,
				$name
			);
		} else {
			// Separator
			$html .= '<span aria-hidden="true">/</span>';

			if ( $position === $count ) {
				// Current item (no link, active text color)
				$html .= sprintf(
					'<span class="text-black">%s</span>',
					$name
				);
			} else {
				// Parent items (category, parent pages)
				$html .= sprintf(
					'<a href="%s">%s</a>',
					$url,
					$name
				);
			}
		}
	}

	$html .= '</nav>';

	return $html;
}

// Hook into block rendering to render breadcrumbs pattern dynamically
add_filter( 'render_block', function( $block_content, $block ) {
	if ( isset( $block['blockName'] ) ) {
		$is_breadcrumb = false;

		// Case 1: Standard FSE block pattern reference
		if ( 'core/pattern' === $block['blockName'] && isset( $block['attrs']['slug'] ) && 'align/breadcrumbs' === $block['attrs']['slug'] ) {
			$is_breadcrumb = true;
		}
		// Case 2: Block pattern converted to custom HTML in Site Editor/database templates
		elseif ( 'core/html' === $block['blockName'] ) {
			if ( ( isset( $block['attrs']['metadata']['patternName'] ) && 'align/breadcrumbs' === $block['attrs']['metadata']['patternName'] ) ||
				 ( strpos( $block_content, 'aria-label="Breadcrumb"' ) !== false && strpos( $block_content, 'text-[#00000080]' ) !== false ) ) {
				$is_breadcrumb = true;
			}
		}

		if ( $is_breadcrumb ) {
			return align_render_breadcrumbs_html();
		}
	}
	return $block_content;
}, 10, 2 );

/**
 * Dynamically resolve the correct tag taxonomy for Custom Post Types when rendering the Tags block.
 */
add_filter( 'render_block', function( $block_content, $block ) {
	if ( isset( $block['blockName'] ) && 'core/post-terms' === $block['blockName'] ) {
		// Target standard tag blocks (term === 'post_tag')
		if ( isset( $block['attrs']['term'] ) && 'post_tag' === $block['attrs']['term'] ) {
			$queried_post_id = get_queried_object_id();
			$post_type       = get_post_type( $queried_post_id );

			// Check if this is a Custom Post Type
			if ( $post_type && ! in_array( $post_type, array( 'post', 'page' ), true ) ) {
				$target_taxonomy = '';
				if ( 'podcast' === $post_type ) {
					$target_taxonomy = 'podcast-tags';
				} elseif ( 'press-release' === $post_type ) {
					$target_taxonomy = 'press-release-tag';
				} else {
					// Fallback to searching custom taxonomies containing 'tag'
					$taxonomies = get_object_taxonomies( $post_type );
					foreach ( $taxonomies as $tax ) {
						if ( strpos( $tax, 'tag' ) !== false ) {
							$target_taxonomy = $tax;
							break;
						}
					}
				}

				if ( ! empty( $target_taxonomy ) ) {
					$terms = get_the_terms( $queried_post_id, $target_taxonomy );
					if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
						$html = '';
						foreach ( $terms as $term ) {
							$term_link = get_term_link( $term );
							if ( ! is_wp_error( $term_link ) ) {
								$html .= sprintf(
									'<a href="%s" rel="tag" class="wp-block-post-terms__link">%s</a>',
									esc_url( $term_link ),
									esc_html( $term->name )
								);
							}
						}

						if ( ! empty( $html ) ) {
							return sprintf(
								'<div class="wp-block-post-terms taxonomy-post_tag">%s</div>',
								$html
							);
						}
					}
					return '';
				}
			}
		}
	}
	return $block_content;
}, 10, 2 );
