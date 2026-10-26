<?php
/**
 * Align Theme Core Engine - Template Structure Preserver
 *
 * The Builder canvas never contains template-part blocks (they are stripped on load),
 * so every template save must re-attach the header/footer wrappers and the post-content
 * block attributes from the previously persisted template content.
 * Mirrors window.AlignTemplateMarkup (core/builder/gutenberg/align-template-markup.js).
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Builder_Template_Structure {

	const DEFAULT_POST_CONTENT = '<!-- wp:post-content /-->';

	/** Template slugs that receive default header/footer when no previous content exists. */
	const DEFAULT_WRAPPED_SLUGS = array( 'home', 'page', 'single', 'index', 'archive', 'search', '404' );

	/** Parse block markup into top-level blocks, dropping whitespace-only freeform chunks. */
	private static function parse_top_level_blocks( string $content ): array {
		if ( '' === trim( $content ) || ! function_exists( 'parse_blocks' ) ) {
			return array();
		}
		return array_values( array_filter( parse_blocks( $content ), function ( $block ) {
			return ! empty( $block['blockName'] ) || '' !== trim( (string) ( $block['innerHTML'] ?? '' ) );
		} ) );
	}

	/** Check whether a template-part block belongs after the main content. */
	private static function is_footer_part( array $block ): bool {
		$tag  = (string) ( $block['attrs']['tagName'] ?? '' );
		$slug = (string) ( $block['attrs']['slug'] ?? '' );
		return 'footer' === $tag || 0 === strpos( $slug, 'footer' );
	}

	/**
	 * Extract leading and trailing template-part blocks from persisted template content.
	 *
	 * @param string $existing Previously persisted template block markup.
	 * @return array{leading: string[], trailing: string[]} Serialized template-part blocks.
	 */
	public static function get_wrappers( string $existing ): array {
		$blocks   = self::parse_top_level_blocks( $existing );
		$count    = count( $blocks );
		$leading  = array();
		$trailing = array();

		$start = 0;
		while ( $start < $count && 'core/template-part' === $blocks[ $start ]['blockName'] ) {
			$start++;
		}
		$end = $count - 1;
		while ( $end >= $start && 'core/template-part' === $blocks[ $end ]['blockName'] ) {
			$end--;
		}

		foreach ( $blocks as $index => $block ) {
			if ( 'core/template-part' !== $block['blockName'] ) {
				continue;
			}
			if ( $start === $count ) {
				// Template holds only template parts: split them by footer semantics.
				if ( self::is_footer_part( $block ) ) {
					$trailing[] = serialize_block( $block );
				} else {
					$leading[] = serialize_block( $block );
				}
			} elseif ( $index < $start ) {
				$leading[] = serialize_block( $block );
			} elseif ( $index > $end ) {
				$trailing[] = serialize_block( $block );
			}
		}

		return array( 'leading' => $leading, 'trailing' => $trailing );
	}

	/** Find the first core/post-content block (any depth) and return its serialized comment. */
	public static function find_post_content_block( string $existing ): string {
		$stack = self::parse_top_level_blocks( $existing );
		while ( ! empty( $stack ) ) {
			$block = array_shift( $stack );
			if ( 'core/post-content' === ( $block['blockName'] ?? '' ) ) {
				return serialize_block( $block );
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$stack = array_merge( $block['innerBlocks'], $stack );
			}
		}
		return self::DEFAULT_POST_CONTENT;
	}

	/**
	 * Detect the editor mode a template is locked to (mirrors AlignTemplateMarkup.detectMode).
	 * Structural blocks (template-part, post-content) are ignored.
	 *
	 * @param string $content Persisted template or template part content.
	 * @return string|null 'builder', 'gutenberg', or null when unlocked (no own content).
	 */
	public static function detect_mode( string $content ): ?string {
		$blocks = array_values( array_filter( self::parse_top_level_blocks( $content ), function ( $block ) {
			return ! in_array( $block['blockName'], array( 'core/template-part', 'core/post-content' ), true );
		} ) );

		if ( empty( $blocks ) ) {
			return null;
		}
		if ( 1 === count( $blocks ) && in_array( $blocks[0]['blockName'], array( 'align/custom-html', 'core/html' ), true ) ) {
			return '' === trim( (string) ( $blocks[0]['innerHTML'] ?? '' ) ) && empty( $blocks[0]['innerBlocks'] ) ? null : 'builder';
		}
		return 'gutenberg';
	}

	/** Read persisted template content (DB customization first, then theme file) for a Builder tp_id. */
	public static function get_persisted_content( string $tp_id, string $template_type = '' ): string {
		if ( ! function_exists( 'get_block_template' ) ) {
			return '';
		}
		$is_part = 0 === strpos( $tp_id, 'tp_' ) || ( 0 !== strpos( $tp_id, 'tpl_' ) && 'template_part' === $template_type );
		$raw_id  = preg_replace( '/^t(p|pl)_/', '', $tp_id );
		$id      = ( false !== strpos( $raw_id, '//' ) ) ? $raw_id : get_stylesheet() . '//' . $raw_id;
		$tpl     = get_block_template( $id, $is_part ? 'wp_template_part' : 'wp_template' );
		return $tpl ? (string) $tpl->content : '';
	}

	/**
	 * Wrap the Builder main block with the header/footer parts of the persisted template.
	 *
	 * @param string $main_block Serialized align/custom-html block.
	 * @param string $existing   Previously persisted template content ('' when none).
	 * @param string $slug       Template slug.
	 * @return string Full template block markup.
	 */
	public static function wrap( string $main_block, string $existing, string $slug ): string {
		$wrappers = self::get_wrappers( $existing );

		if ( '' === trim( $existing ) && in_array( $slug, self::DEFAULT_WRAPPED_SLUGS, true ) ) {
			$wrappers = array(
				'leading'  => array( '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->' ),
				'trailing' => array( '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->' ),
			);
		}

		error_log( sprintf(
			'[Align Persistence] Template "%s" structure: %d leading part(s), %d trailing part(s) | Existing length: %d bytes',
			$slug,
			count( $wrappers['leading'] ),
			count( $wrappers['trailing'] ),
			strlen( $existing )
		) );

		return implode( "\n\n", array_merge( $wrappers['leading'], array( $main_block ), $wrappers['trailing'] ) );
	}
}
