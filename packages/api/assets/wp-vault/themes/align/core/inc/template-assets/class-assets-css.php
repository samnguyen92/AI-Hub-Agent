<?php
/**
 * Assets Loader CSS Manager
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AssetsCSS {

	/**
	 * List of CSS handles registered for footer optimization.
	 *
	 * @var array
	 */
	private static array $optimizedHandles = [];

	/**
	 * List of CSS handles that have had their inline styles injected.
	 *
	 * @var array
	 */
	private static array $injectedHandles = [];

	/**
	 * Register style handles to be optimized.
	 *
	 * @param string $handle
	 * @return void
	 */
	public static function markAsOptimized( string $handle ): void {
		self::$optimizedHandles[] = $handle;
	}

	/**
	 * Enqueue non-important CSS files in the footer.
	 *
	 * @param array $enqueueStyles
	 * @return void
	 */
	public static function enqueueFooter( array $enqueueStyles ): void {
		if ( empty( $enqueueStyles ) ) {
			return;
		}

		foreach ( $enqueueStyles as $css ) {
			wp_enqueue_style( $css['handle'], $css['src'], [], $css['ver'] );
			self::markAsOptimized( $css['handle'] );
		}
	}

	/**
	 * Filter style tags to set data-delay="true" and data-href attributes.
	 *
	 * @param string $html
	 * @param string $handle
	 * @param string $href
	 * @return string
	 */
	public static function filterTag( string $html, string $handle, string $href = '' ): string {
		if ( AssetsConfig::shouldSkipOptimize() ) {
			return $html;
		}

		$should_delay = in_array( $handle, self::$optimizedHandles, true );

		if ( ! $should_delay && ! empty( $href ) && AssetsConfig::matchesDelayUrl( $href ) ) {
			$should_delay = true;
		}

		if ( ! $should_delay && ! empty( $html ) ) {
			if ( preg_match( '/href=[\'"]([^\'"]+)[\'"]/i', $html, $matches ) ) {
				if ( AssetsConfig::matchesDelayUrl( $matches[1] ) ) {
					$should_delay = true;
				}
			}
		}

		if ( $should_delay && false !== strpos( $html, ' href=' ) ) {
			$html = str_replace( ' href=', ' data-delay="true" data-href=', $html );
		}

		return $html;
	}
}
