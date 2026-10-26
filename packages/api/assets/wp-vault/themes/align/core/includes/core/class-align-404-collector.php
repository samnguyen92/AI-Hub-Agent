<?php
/**
 * Align Theme - 404 URL Monitor & Quick Redirect Collector
 *
 * Automatically records frontend 404 requests (filtering out static assets and scans)
 * and provides 1-click 301 redirection integration with Rank Math SEO.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_404_Collector {

	const OPTION_KEY  = 'align_404_logs';
	const MAX_ENTRIES = 500;

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'capture_404' ) );

		// Admin AJAX Endpoints
		add_action( 'wp_ajax_align_get_404_logs',               array( __CLASS__, 'ajax_get_404_logs' ) );
		add_action( 'wp_ajax_align_delete_404_log',            array( __CLASS__, 'ajax_delete_404_log' ) );
		add_action( 'wp_ajax_align_clear_404_logs',             array( __CLASS__, 'ajax_clear_404_logs' ) );
		add_action( 'wp_ajax_align_create_redirect_from_404',   array( __CLASS__, 'ajax_create_redirect_from_404' ) );
	}

	/**
	 * Capture frontend 404 requests.
	 */
	public static function capture_404() {
		if ( ! is_404() || is_admin() || wp_doing_ajax() ) {
			return;
		}

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		if ( empty( $uri ) ) {
			return;
		}

		$parsed_path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( empty( $parsed_path ) ) {
			return;
		}

		$path = '/' . ltrim( (string) $parsed_path, '/' );

		// Ignore static file extensions
		if ( preg_match( '/\.(?:png|jpe?g|gif|webp|svg|ico|css|js|map|woff2?|ttf|eot|xml|txt|pdf|zip|gz|tar|rar|mp4|webm|ogg|mp3|wav|php)$/i', $path ) ) {
			return;
		}

		// Ignore common automated bot vulnerability scans
		if ( preg_match( '/(?:\.env|\.git|wp-admin|wp-login|xmlrpc|autodiscover|phpmyadmin|cgi-bin)/i', $path ) ) {
			return;
		}

		$referer = wp_get_raw_referer() ?: ( isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '' );
		self::record_404( $path, $referer );
	}

	/**
	 * Record a 404 path into option.
	 *
	 * @param string $path    Relative request path.
	 * @param string $referer Referer URL if present.
	 */
	public static function record_404( $path, $referer = '' ) {
		$logs = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		$now       = current_time( 'mysql' );
		$found_idx = -1;

		foreach ( $logs as $idx => $item ) {
			if ( isset( $item['path'] ) && $item['path'] === $path ) {
				$found_idx = $idx;
				break;
			}
		}

		if ( $found_idx >= 0 ) {
			$logs[ $found_idx ]['hits'] = intval( $logs[ $found_idx ]['hits'] ?? 1 ) + 1;
			$logs[ $found_idx ]['last_seen'] = $now;
			if ( ! empty( $referer ) ) {
				$logs[ $found_idx ]['referer'] = esc_url_raw( $referer );
			}
			$matched = $logs[ $found_idx ];
			unset( $logs[ $found_idx ] );
			array_unshift( $logs, $matched );
		} else {
			array_unshift( $logs, array(
				'path'       => $path,
				'hits'       => 1,
				'referer'    => ! empty( $referer ) ? esc_url_raw( $referer ) : '',
				'first_seen' => $now,
				'last_seen'  => $now,
			) );
		}

		if ( count( $logs ) > self::MAX_ENTRIES ) {
			$logs = array_slice( $logs, 0, self::MAX_ENTRIES );
		}

		update_option( self::OPTION_KEY, array_values( $logs ), false );
	}

	/**
	 * AJAX: Get list of 404 logs.
	 */
	public static function ajax_get_404_logs() {
		self::check_permission();

		$logs   = get_option( self::OPTION_KEY, array() );
		$search = isset( $_REQUEST['search'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['search'] ) ) : '';

		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		if ( ! empty( $search ) ) {
			$logs = array_filter( $logs, function( $item ) use ( $search ) {
				return false !== stripos( $item['path'], $search ) || false !== stripos( $item['referer'] ?? '', $search );
			} );
			$logs = array_values( $logs );
		}

		wp_send_json_success( array(
			'logs'  => $logs,
			'total' => count( $logs ),
		) );
	}

	/**
	 * AJAX: Delete a single 404 log entry.
	 */
	public static function ajax_delete_404_log() {
		self::check_permission();

		$path = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
		if ( empty( $path ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing path parameter.', 'align' ) ) );
		}

		$logs = get_option( self::OPTION_KEY, array() );
		if ( is_array( $logs ) ) {
			$logs = array_filter( $logs, function( $item ) use ( $path ) {
				return ( $item['path'] ?? '' ) !== $path;
			} );
			update_option( self::OPTION_KEY, array_values( $logs ), false );
		}

		wp_send_json_success( array( 'message' => __( 'Entry removed.', 'align' ) ) );
	}

	/**
	 * AJAX: Clear all 404 logs.
	 */
	public static function ajax_clear_404_logs() {
		self::check_permission();
		update_option( self::OPTION_KEY, array(), false );
		wp_send_json_success( array( 'message' => __( 'All 404 logs cleared.', 'align' ) ) );
	}

	/**
	 * AJAX: Create 301 Redirect directly from 404 entry and remove from logs.
	 */
	public static function ajax_create_redirect_from_404() {
		self::check_permission();

		// Ensure importer class is loaded.
		if ( ! class_exists( 'Align_Redirection_Importer' ) ) {
			$importer_file = dirname( __DIR__ ) . '/admin/class-align-redirection-importer.php';
			if ( file_exists( $importer_file ) ) {
				require_once $importer_file;
			}
		}

		if ( ! class_exists( 'Align_Redirection_Importer' ) || ! Align_Redirection_Importer::is_rank_math_active() ) {
			wp_send_json_error( array( 'message' => __( 'Rank Math Redirections module is inactive.', 'align' ) ) );
		}

		$source_path = isset( $_POST['source_path'] ) ? sanitize_text_field( wp_unslash( $_POST['source_path'] ) ) : '';
		$target_url  = isset( $_POST['target_url'] ) ? sanitize_text_field( wp_unslash( $_POST['target_url'] ) ) : '';
		$code        = isset( $_POST['code'] ) ? intval( $_POST['code'] ) : 301;

		if ( empty( $source_path ) || empty( $target_url ) ) {
			wp_send_json_error( array( 'message' => __( 'Source and destination URLs are required.', 'align' ) ) );
		}

		// Normalize source path
		$parsed_source = wp_parse_url( $source_path, PHP_URL_PATH );
		$clean_source  = $parsed_source ? '/' . ltrim( $parsed_source, '/' ) : $source_path;

		// Normalize target URL
		$clean_dest = trim( $target_url );
		$home       = home_url();
		if ( 0 === strpos( $clean_dest, $home ) ) {
			$clean_dest = substr( $clean_dest, strlen( $home ) );
			$clean_dest = '/' . ltrim( $clean_dest, '/' );
		}

		try {
			$clean_pattern = \RankMath\Redirections\DB::get_clean_pattern( $clean_source, 'exact' );

			$sources = array(
				array(
					'pattern'    => $clean_pattern,
					'comparison' => 'exact',
					'ignore'     => '',
				),
			);

			$insert_id = \RankMath\Redirections\DB::add( array(
				'sources'     => $sources,
				'url_to'      => $clean_dest,
				'header_code' => $code,
				'status'      => 'active',
			) );

			if ( ! $insert_id ) {
				wp_send_json_error( array( 'message' => __( 'Failed to create redirection in Rank Math.', 'align' ) ) );
			}

			// Purge Rank Math cache
			if ( method_exists( 'Align_Redirection_Importer', 'purge_rankmath_cache' ) ) {
				Align_Redirection_Importer::purge_rankmath_cache();
			} else {
				global $wpdb;
				$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}rank_math_redirections_cache" );
			}

			// Remove from 404 logs
			$logs = get_option( self::OPTION_KEY, array() );
			if ( is_array( $logs ) ) {
				$logs = array_filter( $logs, function( $item ) use ( $source_path, $clean_source ) {
					$p = $item['path'] ?? '';
					return ( $p !== $source_path && $p !== $clean_source );
				} );
				update_option( self::OPTION_KEY, array_values( $logs ), false );
			}

			error_log( sprintf( '[Align 404Collector] Created %d redirect from %s to %s (ID: %d)', $code, $clean_source, $clean_dest, $insert_id ) );

			wp_send_json_success( array(
				'message'     => sprintf( __( 'Created %d redirect from "%s" to "%s".', 'align' ), $code, $clean_source, $clean_dest ),
				'insert_id'   => $insert_id,
				'source_path' => $clean_source,
			) );
		} catch ( \Throwable $e ) {
			error_log( '[Align 404Collector] Failed to create redirect from 404: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => __( 'An error occurred while creating redirection in Rank Math.', 'align' ) ) );
		}
	}

	/**
	 * Verify admin AJAX permission and nonce.
	 */
	private static function check_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'align' ) ), 403 );
		}

		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'align_settings_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page.', 'align' ) ), 403 );
		}
	}
}
