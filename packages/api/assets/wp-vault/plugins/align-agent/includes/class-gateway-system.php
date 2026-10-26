<?php
/**
 * Align Agent Gateway - System Diagnostics & Operations Controller
 *
 * Dedicated endpoints for agent handshake (ping) and atomic cache invalidation.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Gateway_System {

	/**
	 * Handle handshake ping endpoint.
	 * Returns system diagnostics, active theme, and builder status.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response System health and metadata response.
	 */
	public static function handle_ping( WP_REST_Request $request ): WP_REST_Response {
		$theme        = wp_get_theme();
		$child_theme  = is_child_theme() ? $theme->get( 'Name' ) : false;
		$parent_theme = is_child_theme() && $theme->parent() ? $theme->parent()->get( 'Name' ) : $theme->get( 'Name' );

		error_log( '[Align Agent] Ping received from remote client.' );

		$data = array(
			'status'             => 'ok',
			'gateway'            => 'Align Agent Gateway',
			'version'            => defined( 'ALIGN_AGENT_VERSION' ) ? ALIGN_AGENT_VERSION : '1.0.0',
			'server_time'        => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'site_name'          => get_bloginfo( 'name' ),
			'site_url'           => site_url(),
			'home_url'           => home_url(),
			'wp_version'         => get_bloginfo( 'version' ),
			'php_version'        => PHP_VERSION,
			'theme'              => $parent_theme,
			'child_theme'        => $child_theme,
			'memory_limit'       => ini_get( 'memory_limit' ),
			'max_execution_time' => (int) ini_get( 'max_execution_time' ),
			'builder_active'     => class_exists( 'Align_Builder_Assets' ),
		);

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Handle cache flush endpoint.
	 * Clears WordPress object cache, transients, and theme cache.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response Result of cache flush operation.
	 */
	public static function handle_cache_flush( WP_REST_Request $request ): WP_REST_Response {
		error_log( '[Align Agent] Executing system cache flush...' );

		$flushed_actions = array();

		// 1. Flush WordPress object cache.
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
			$flushed_actions[] = 'wp_cache_flush';
		}

		// 2. Clean theme.json cache if supported.
		if ( function_exists( 'wp_clean_theme_json_cache' ) ) {
			wp_clean_theme_json_cache();
			$flushed_actions[] = 'wp_clean_theme_json_cache';
		}

		// 3. Reset OPcache if available.
		if ( function_exists( 'opcache_reset' ) && filter_var( ini_get( 'opcache.enable' ), FILTER_VALIDATE_BOOLEAN ) ) {
			@opcache_reset();
			$flushed_actions[] = 'opcache_reset';
		}

		// 4. Optionally flush rewrite rules if requested.
		$flush_rewrites = (bool) $request->get_param( 'flush_rewrites' );
		if ( $flush_rewrites && function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
			$flushed_actions[] = 'flush_rewrite_rules';
		}

		error_log( sprintf( '[Align Agent] Cache flush completed. Actions: %s', implode( ', ', $flushed_actions ) ) );

		$response = array(
			'success'   => true,
			'message'   => __( 'System cache flushed successfully.', 'align-agent' ),
			'actions'   => $flushed_actions,
			'timestamp' => gmdate( 'Y-m-d\TH:i:s\Z' ),
		);

		return new WP_REST_Response( $response, 200 );
	}
}
