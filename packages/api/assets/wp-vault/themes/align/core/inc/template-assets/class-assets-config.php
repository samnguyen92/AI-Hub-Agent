<?php
/**
 * Assets Loader Configuration
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AssetsConfig {

	/**
	 * Cache expiration time in seconds (24 hours).
	 */
	public const CACHE_EXPIRATION = DAY_IN_SECONDS;

	/**
	 * Cache key prefix.
	 */
	public const CACHE_PREFIX = 'align_template_assets_';

	/**
	 * Check if caching is enabled.
	 *
	 * @return bool
	 */
	public static function isCacheEnabled(): bool {
		return (int) get_option( 'align_template_assets_cache', 1 ) === 1;
	}

	/**
	 * Check if debugging is active.
	 *
	 * @return bool
	 */
	public static function isDebugging(): bool {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return true;
		}
		$host = $_SERVER['HTTP_HOST'] ?? '';
		return false !== strpos( $host, 'localhost' ) || 
		       false !== strpos( $host, '127.0.0.1' ) || 
		       false !== strpos( $host, '.local' );
	}

	/**
	 * Check if Delay JS & CSS optimization is enabled in theme settings.
	 *
	 * @return bool
	 */
	public static function isDelayAssetsEnabled(): bool {
		return (int) get_option( 'align_enable_delay_assets', 0 ) === 1;
	}

	/**
	 * Check if HTML optimize tags should be skipped.
	 *
	 * When Delay JS & CSS is enabled:
	 * - Guests and Subscribers will have delay optimization applied (return false).
	 * - Logged-in non-subscriber roles (Admin, Editor, Author, Contributor, Shop Manager, etc.) bypass delay (return true).
	 *
	 * @return bool True to skip/bypass delay optimization, false to apply delay.
	 */
	public static function shouldSkipOptimize(): bool {
		if ( ! self::isDelayAssetsEnabled() ) {
			return true;
		}

		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user  = wp_get_current_user();
		$roles = (array) ( $user->roles ?? [] );

		// If user only has subscriber role, they still receive delayed optimization
		if ( in_array( 'subscriber', $roles, true ) && count( $roles ) === 1 ) {
			return false;
		}

		// Non-subscriber roles (admins, editors, authors, contributors, etc.) bypass delay
		return true;
	}

	/**
	 * Get list of custom delay URLs / path keywords from theme settings.
	 *
	 * @return array
	 */
	public static function getCustomDelayUrls(): array {
		$raw = (string) get_option( 'align_delay_custom_urls', '' );
		if ( empty( trim( $raw ) ) ) {
			return [];
		}
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		$clean = [];
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( ! empty( $line ) ) {
				$clean[] = $line;
			}
		}
		return $clean;
	}

	/**
	 * Check if a given URL / src / href matches any configured custom delay patterns or core assets.
	 *
	 * @param string $url Asset URL, path or handle.
	 * @return bool True if matched, false otherwise.
	 */
	public static function matchesDelayUrl( string $url ): bool {
		if ( empty( $url ) ) {
			return false;
		}

		if ( self::isDelayAssetsEnabled() ) {
			// Auto-apply delay to external core theme CSS and JS assets
			if ( ( false !== stripos( $url, '/themes/align/' ) || false !== stripos( $url, '/align/core/' ) ) &&
			     ! preg_match( '/\.(woff2?|ttf|otf|eot)(\?|$)/i', $url ) ) {
				return true;
			}
		}

		$patterns = self::getCustomDelayUrls();
		if ( empty( $patterns ) ) {
			return false;
		}

		foreach ( $patterns as $pattern ) {
			if ( false !== stripos( $url, $pattern ) ) {
				return true;
			}
		}

		return false;
	}
}
