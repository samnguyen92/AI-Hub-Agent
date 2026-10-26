<?php
/**
 * Assets Loader Cache Manager
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AssetsCache {

	/**
	 * Fetch assets data from transient cache.
	 *
	 * @param string $key
	 * @return array|bool
	 */
	public static function get( string $key ) {
		if ( ! AssetsConfig::isCacheEnabled() || AssetsConfig::isDebugging() ) {
			return false;
		}
		return get_transient( AssetsConfig::CACHE_PREFIX . $key );
	}

	/**
	 * Save assets data to transient cache.
	 *
	 * @param string $key
	 * @param array  $data
	 * @return bool
	 */
	public static function set( string $key, array $data ): bool {
		if ( ! AssetsConfig::isCacheEnabled() || AssetsConfig::isDebugging() ) {
			return false;
		}
		return set_transient( AssetsConfig::CACHE_PREFIX . $key, $data, AssetsConfig::CACHE_EXPIRATION );
	}

	/**
	 * Clear all template and general transients matching the cache prefix.
	 *
	 * @return void
	 */
	public static function clearAll(): void {
		global $wpdb;
		$prefix = AssetsConfig::CACHE_PREFIX;

		$transients = $wpdb->get_col( $wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
			'_transient_' . $prefix . '%'
		) );

		if ( ! empty( $transients ) ) {
			foreach ( $transients as $transient ) {
				$transientName = str_replace( '_transient_', '', $transient );
				delete_transient( $transientName );
			}
		}
	}
}
