<?php
/**
 * Align Agent Site Health Diagnostics Tools
 *
 * Compiles a read-only diagnostics snapshot of PHP, WordPress, MySQL, cron jobs,
 * disk space, active plugins, and redacted error log tails.
 *
 * @package Align_Agent
 * @subpackage Includes\Tools\Site
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_Health {

	/**
	 * Register site health tool into Align_Agent_Tool_Registry.
	 *
	 * @return void
	 */
	public static function init(): void {
		Align_Agent_Tool_Registry::register( 'site_health', array(
			'type'             => 'read',
			'group'            => 'ops',
			'scope'            => 'local',
			'capability'       => 'manage_options',
			'requires_builder' => false,
			'danger'           => false,
			'description'      => 'Diagnose site health, environment, PHP/WP versions, cron queue, disk storage, and redacted error log tail.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'include_log' => array(
						'type'        => 'boolean',
						'description' => 'Whether to include recent matching error log entries (redacted). Default: false.',
					),
					'log_lines'   => array(
						'type'        => 'integer',
						'description' => 'Number of log lines to retrieve (10..100). Default: 40.',
					),
				),
			),
			'callback'         => array( __CLASS__, 'tool_site_health' ),
		) );
	}

	/**
	 * Callback for site_health tool.
	 */
	public static function tool_site_health( array $args = array() ): array {
		global $wpdb, $wp_version;

		$include_log = ! empty( $args['include_log'] );
		$log_lines   = isset( $args['log_lines'] ) ? min( 100, max( 10, (int) $args['log_lines'] ) ) : 40;

		$theme = wp_get_theme();

		$versions = array(
			'wp'          => $wp_version,
			'php'         => PHP_VERSION,
			'db'          => $wpdb ? $wpdb->db_version() : 'unknown',
			'theme'       => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ),
			'align_agent' => defined( 'ALIGN_AGENT_VERSION' ) ? ALIGN_AGENT_VERSION : 'unknown',
		);

		$runtime = array(
			'memory_limit'       => ini_get( 'memory_limit' ),
			'max_execution_time' => ini_get( 'max_execution_time' ),
			'WP_DEBUG'           => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'WP_DEBUG_LOG'       => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
			'object_cache'       => wp_using_ext_object_cache(),
			'https'              => ( 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME ) ),
		);

		$updates_data = function_exists( 'wp_get_update_data' ) ? wp_get_update_data() : array();
		$updates      = $updates_data['counts'] ?? array();

		// Cron diagnostics.
		$cron_events   = _get_cron_array();
		$total_cron    = 0;
		$overdue_cron  = 0;
		$one_hour_ago  = time() - 3600;

		if ( is_array( $cron_events ) ) {
			foreach ( $cron_events as $ts => $hooks ) {
				foreach ( $hooks as $h => $evs ) {
					$cnt = count( $evs );
					$total_cron += $cnt;
					if ( $ts < $one_hour_ago ) {
						$overdue_cron += $cnt;
					}
				}
			}
		}

		$cron = array(
			'total_events' => $total_cron,
			'overdue'      => $overdue_cron,
			'disabled'     => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
		);

		// Disk free space.
		$disk_free = function_exists( 'disk_free_space' ) ? @disk_free_space( ABSPATH ) : false;
		$disk = array(
			'free_gb'          => false !== $disk_free ? round( $disk_free / ( 1024 * 1024 * 1024 ), 2 ) : null,
			'uploads_writable' => wp_is_writable( wp_upload_dir()['basedir'] ),
		);

		// Active plugins.
		$active_plugins = array();
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active_plugin_keys = (array) get_option( 'active_plugins', array() );
		foreach ( $active_plugin_keys as $plugin_file ) {
			$full_path = WP_PLUGIN_DIR . '/' . $plugin_file;
			if ( file_exists( $full_path ) ) {
				$data = get_plugin_data( $full_path, false, false );
				$active_plugins[] = array(
					'name'    => $data['Name'] ?: $plugin_file,
					'version' => $data['Version'] ?: 'unknown',
				);
			}
		}

		$log_entries = array();
		if ( $include_log ) {
			$log_entries = self::get_redacted_log_tail( $log_lines );
		}

		return array(
			'success'  => true,
			'versions' => $versions,
			'runtime'  => $runtime,
			'updates'  => $updates,
			'cron'     => $cron,
			'disk'     => $disk,
			'plugins'  => $active_plugins,
			'log'      => $log_entries,
			'message'  => 'Site health diagnostics collected.',
		);
	}

	/**
	 * Retrieve and redact recent lines from debug/error log.
	 */
	private static function get_redacted_log_tail( int $max_lines ): array {
		$log_file = '';
		if ( defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) && file_exists( WP_DEBUG_LOG ) ) {
			$log_file = WP_DEBUG_LOG;
		} elseif ( defined( 'WP_DEBUG_LOG' ) && true === WP_DEBUG_LOG && file_exists( WP_CONTENT_DIR . '/debug.log' ) ) {
			$log_file = WP_CONTENT_DIR . '/debug.log';
		} else {
			$ini_log = ini_get( 'error_log' );
			if ( $ini_log && file_exists( $ini_log ) ) {
				$log_file = $ini_log;
			}
		}

		if ( empty( $log_file ) || ! is_readable( $log_file ) ) {
			return array();
		}

		$fsize = filesize( $log_file );
		if ( 0 === $fsize ) {
			return array();
		}

		// Read up to last 256 KB.
		$read_bytes = min( 262144, $fsize );
		$fp         = fopen( $log_file, 'rb' );
		if ( ! $fp ) {
			return array();
		}

		fseek( $fp, -$read_bytes, SEEK_END );
		$chunk = fread( $fp, $read_bytes );
		fclose( $fp );

		$lines   = explode( "\n", $chunk );
		$matched = array();

		foreach ( array_reverse( $lines ) as $line ) {
			$trimmed = trim( $line );
			if ( '' === $trimmed ) {
				continue;
			}
			if ( preg_match( '#\[Align|PHP (?:Fatal|Warning|Parse)#i', $trimmed ) ) {
				$matched[] = self::redact_line( $trimmed );
				if ( count( $matched ) >= $max_lines ) {
					break;
				}
			}
		}

		return array_reverse( $matched );
	}

	/**
	 * Redact credentials, tokens, and emails from log strings.
	 */
	public static function redact_line( string $line ): string {
		$patterns = array(
			'#bot\d{6,}:[A-Za-z0-9_-]{20,}#'           => 'bot***',
			'#\bsk-[A-Za-z0-9_-]{16,}#'                => 'sk-***',
			'#Bearer\s+[A-Za-z0-9._\-]+#i'             => 'Bearer ***',
			'#(password|pass|secret|token|key)=\S+#i'  => '$1=***',
			'#[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}#i' => '***@***',
		);

		return preg_replace( array_keys( $patterns ), array_values( $patterns ), $line );
	}
}
