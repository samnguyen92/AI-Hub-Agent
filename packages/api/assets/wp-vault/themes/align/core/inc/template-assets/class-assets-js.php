<?php
/**
 * Assets Loader JS Manager
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AssetsJS {

	/**
	 * List of JS handles registered for footer optimization.
	 *
	 * @var array
	 */
	private static array $optimizedHandles = [];

	/**
	 * Register script handles to be optimized.
	 *
	 * @param string $handle
	 * @return void
	 */
	public static function markAsOptimized( string $handle ): void {
		self::$optimizedHandles[] = $handle;
	}

	/**
	 * Enqueue non-important JS files in the footer.
	 *
	 * @param array $enqueueScripts
	 * @return void
	 */
	public static function enqueueFooter( array $enqueueScripts ): void {
		if ( empty( $enqueueScripts ) ) {
			return;
		}

		foreach ( $enqueueScripts as $js ) {
			$args = [
				'in_footer' => true,
			];

			if ( ! empty( $js['strategy'] ) ) {
				$args['strategy'] = $js['strategy'];
			}

			wp_enqueue_script( $js['handle'], $js['src'], [], $js['ver'], $args );
			self::markAsOptimized( $js['handle'] );
		}
	}

	/**
	 * Filter script tags to set data-delay="true" and data-src attributes.
	 *
	 * @param string $html
	 * @param string $handle
	 * @param string $src
	 * @return string
	 */
	public static function filterTag( string $html, string $handle, string $src = '' ): string {
		if ( AssetsConfig::shouldSkipOptimize() ) {
			return $html;
		}

		$should_delay = in_array( $handle, self::$optimizedHandles, true );

		if ( ! $should_delay && ! empty( $src ) && AssetsConfig::matchesDelayUrl( $src ) ) {
			$should_delay = true;
		}

		if ( ! $should_delay && ! empty( $html ) ) {
			if ( preg_match( '/src=[\'"]([^\'"]+)[\'"]/i', $html, $matches ) ) {
				if ( AssetsConfig::matchesDelayUrl( $matches[1] ) ) {
					$should_delay = true;
				}
			}
		}

		if ( $should_delay && false !== strpos( $html, ' src=' ) ) {
			$html = str_replace( ' src=', ' data-delay="true" data-src=', $html );
		}

		return $html;
	}
}
