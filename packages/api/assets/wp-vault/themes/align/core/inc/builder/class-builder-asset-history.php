<?php
/**
 * Align Theme Core Engine - Builder Asset History Service
 *
 * Provides automatic snapshots, pruning, listing, and restoration for CSS and JS assets
 * prior to any destructive overwrite or unlink operation.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	// Allow standalone execution in test environments if ABSPATH is stubbed or omitted.
	if ( ! defined( 'ALIGN_TEST_RUNNER' ) ) {
		exit;
	}
}

class Align_Builder_Asset_History {

	/**
	 * Max snapshots to retain per asset file by default.
	 */
	const DEFAULT_KEEP_COUNT = 5;

	/**
	 * Custom assets directory override (used primarily in tests).
	 *
	 * @var string|null
	 */
	private static $custom_assets_dir = null;

	/**
	 * Cache of initialized history directories to avoid redundant filesystem checks.
	 *
	 * @var array<string, bool>
	 */
	private static array $history_dirs_initialized = array();

	/**
	 * Set custom assets directory for testing.
	 *
	 * @param string|null $dir Directory path or null to reset.
	 */
	public static function set_custom_assets_dir( ?string $dir ): void {
		self::$custom_assets_dir        = $dir ? rtrim( $dir, '/\\' ) . '/' : null;
		self::$history_dirs_initialized = array();
	}

	/**
	 * Get base directory path for builder static assets.
	 *
	 * @param string $fallback_file Optional file path to infer directory.
	 * @return string Assets directory path with trailing slash.
	 */
	public static function get_assets_dir( string $fallback_file = '' ): string {
		if ( ! empty( self::$custom_assets_dir ) ) {
			return self::$custom_assets_dir;
		}

		if ( ! empty( $fallback_file ) && ( false !== strpos( $fallback_file, '/' ) || false !== strpos( $fallback_file, '\\' ) ) ) {
			return dirname( $fallback_file ) . '/';
		}

		if ( class_exists( 'Align_Builder_Assets' ) && method_exists( 'Align_Builder_Assets', 'get_builder_assets_dir' ) ) {
			return Align_Builder_Assets::get_builder_assets_dir();
		}

		if ( function_exists( 'get_stylesheet_directory' ) ) {
			$dir = get_stylesheet_directory() . '/assets/builder/';
			if ( function_exists( 'trailingslashit' ) ) {
				$dir = trailingslashit( get_stylesheet_directory() ) . 'assets/builder/';
			}
			return $dir;
		}

		return sys_get_temp_dir() . '/align-assets/';
	}

	/**
	 * Get absolute directory path for asset history snapshots.
	 * Ensures the directory exists and has security files (.htaccess, index.php).
	 *
	 * @param string $fallback_file Optional file path to infer directory.
	 * @return string History directory with trailing slash.
	 */
	public static function get_history_dir( string $fallback_file = '' ): string {
		$assets_dir  = self::get_assets_dir( $fallback_file );
		$history_dir = rtrim( $assets_dir, '/\\' ) . '/.history/';

		if ( isset( self::$history_dirs_initialized[ $history_dir ] ) ) {
			return $history_dir;
		}

		if ( ! file_exists( $history_dir ) ) {
			if ( function_exists( 'wp_mkdir_p' ) ) {
				wp_mkdir_p( $history_dir );
			} else {
				@mkdir( $history_dir, 0755, true );
			}
		}

		// Security: write or update .htaccess with Apache 2.4 & 2.2 rules.
		$htaccess_file = $history_dir . '.htaccess';
		if ( ! file_exists( $htaccess_file ) || false === strpos( (string) @file_get_contents( $htaccess_file ), 'Require all denied' ) ) {
			$htaccess_rules  = "<IfModule mod_authz_core.c>\n";
			$htaccess_rules .= "    Require all denied\n";
			$htaccess_rules .= "</IfModule>\n";
			$htaccess_rules .= "<IfModule !mod_authz_core.c>\n";
			$htaccess_rules .= "    Deny from all\n";
			$htaccess_rules .= "</IfModule>\n";
			@file_put_contents( $htaccess_file, $htaccess_rules );
		}

		// Security: write index.php stub if missing.
		$index_file = $history_dir . 'index.php';
		if ( ! file_exists( $index_file ) ) {
			@file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
		}

		self::$history_dirs_initialized[ $history_dir ] = true;

		return $history_dir;
	}

	/**
	 * Parse file path into sanitized name and whitelisted extension.
	 * Returns null if extension is not css or js.
	 *
	 * @param string $path File path or basename.
	 * @return array{name: string, ext: string}|null
	 */
	private static function parse_file_parts( string $path ): ?array {
		$raw_basename = basename( $path );
		$clean        = function_exists( 'sanitize_file_name' )
			? basename( sanitize_file_name( $raw_basename ) )
			: basename( preg_replace( '/[^A-Za-z0-9_\-\.]/', '', $raw_basename ) );

		if ( '' === $clean ) {
			return null;
		}

		$ext = strtolower( (string) pathinfo( $clean, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'css', 'js' ), true ) ) {
			return null;
		}

		$name = pathinfo( $clean, PATHINFO_FILENAME );
		if ( '' === $name ) {
			return null;
		}

		return array(
			'name' => $name,
			'ext'  => $ext,
		);
	}

	/**
	 * Create a history snapshot of an asset file before overwrite or deletion.
	 *
	 * @param string      $file_path   Path to the asset file.
	 * @param string      $reason      Reason for snapshot ('overwrite' or 'delete').
	 * @param string|null $new_content Optional upcoming new content to check for identical bytes.
	 * @return bool True if snapshot created, false otherwise.
	 */
	public static function snapshot( string $file_path, string $reason = 'overwrite', ?string $new_content = null ): bool {
		$parts = self::parse_file_parts( $file_path );
		if ( null === $parts ) {
			return false;
		}

		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return false;
		}

		$current_size = filesize( $file_path );
		if ( false === $current_size || $current_size <= 0 ) {
			return false;
		}

		$current_content = file_get_contents( $file_path );
		if ( false === $current_content || '' === trim( $current_content ) ) {
			return false;
		}

		// Skip if new content is provided and byte-identical to current file.
		if ( null !== $new_content && $new_content === $current_content ) {
			error_log( sprintf( '[Align AssetHistory] Snapshot skipped for %s: byte-identical to existing content', basename( $file_path ) ) );
			return false;
		}

		$history_dir = self::get_history_dir( $file_path );
		$base_name   = $parts['name'];
		$ext         = '.' . $parts['ext'];

		// Check the most recent snapshot for duplicate content.
		$existing_snapshots = self::list_snapshots_for_parts( $history_dir, $base_name, $parts['ext'] );
		if ( ! empty( $existing_snapshots ) ) {
			$latest = $existing_snapshots[0];
			if ( $latest['bytes'] === $current_size && file_exists( $latest['path'] ) && md5_file( $latest['path'] ) === md5( $current_content ) ) {
				error_log( sprintf( '[Align AssetHistory] Snapshot skipped for %s: byte-identical to latest snapshot', basename( $file_path ) ) );
				return false;
			}
		}

		// Construct snapshot file path: <assets_dir>/.history/<basename>.<unix_ts>.<ext>
		$timestamp     = time();
		$snapshot_name = sprintf( '%s.%d%s', $base_name, $timestamp, $ext );
		$snapshot_path = $history_dir . $snapshot_name;

		// Handle possible same-second collision by appending microtime suffix if needed.
		if ( file_exists( $snapshot_path ) ) {
			$snapshot_name = sprintf( '%s.%d_%d%s', $base_name, $timestamp, (int) ( microtime( true ) * 1000 ) % 1000, $ext );
			$snapshot_path = $history_dir . $snapshot_name;
		}

		$copied = @copy( $file_path, $snapshot_path );
		if ( ! $copied ) {
			error_log( sprintf( '[Align AssetHistory] [ERROR] Failed to create snapshot for %s at %s', $file_path, $snapshot_path ) );
			return false;
		}

		$bytes = filesize( $snapshot_path );
		error_log( sprintf( '[Align AssetHistory] Snapshot created: %s (Bytes: %d, reason: %s)', $snapshot_path, $bytes, $reason ) );

		// Prune older snapshots beyond retention limit.
		$pruned = self::prune( $file_path, self::DEFAULT_KEEP_COUNT );
		if ( $pruned > 0 ) {
			error_log( sprintf( '[Align AssetHistory] Pruned %d old snapshot(s) for %s', $pruned, basename( $file_path ) ) );
		}

		return true;
	}

	/**
	 * Prune oldest snapshots beyond keep limit for an asset file.
	 *
	 * @param string $file_path_or_basename File path or basename.
	 * @param int    $keep                  Number of latest snapshots to retain.
	 * @return int Number of pruned snapshots.
	 */
	public static function prune( string $file_path_or_basename, int $keep = self::DEFAULT_KEEP_COUNT ): int {
		$parts = self::parse_file_parts( $file_path_or_basename );
		if ( null === $parts ) {
			return 0;
		}

		$history_dir = self::get_history_dir( $file_path_or_basename );
		$snapshots   = self::list_snapshots_for_parts( $history_dir, $parts['name'], $parts['ext'] );

		if ( count( $snapshots ) <= $keep ) {
			return 0;
		}

		$to_prune     = array_slice( $snapshots, $keep );
		$pruned_count = 0;

		foreach ( $to_prune as $snap ) {
			if ( file_exists( $snap['path'] ) ) {
				if ( @unlink( $snap['path'] ) ) {
					$pruned_count++;
				}
			}
		}

		return $pruned_count;
	}

	/**
	 * List snapshots for a given basename, ordered newest first.
	 * Returns sanitized file information without revealing server absolute filesystem path.
	 *
	 * @param string $basename Asset filename (e.g. 'style-page-goi-subscription.css').
	 * @return array<int, array{filename: string, timestamp: int, bytes: int}>
	 */
	public static function list_snapshots( string $basename ): array {
		$parts = self::parse_file_parts( $basename );
		if ( null === $parts ) {
			return array();
		}

		$history_dir = self::get_history_dir( $basename );
		$snapshots   = self::list_snapshots_for_parts( $history_dir, $parts['name'], $parts['ext'] );

		$public_list = array();
		foreach ( $snapshots as $snap ) {
			$public_list[] = array(
				'filename'  => basename( $snap['path'] ),
				'timestamp' => $snap['timestamp'],
				'bytes'     => $snap['bytes'],
			);
		}

		return $public_list;
	}

	/**
	 * Internal helper to find snapshots in history dir matching name and ext.
	 *
	 * @param string $history_dir History directory path.
	 * @param string $name        File name without timestamp or extension.
	 * @param string $ext         File extension without dot.
	 * @return array<int, array{path: string, timestamp: int, bytes: int}>
	 */
	private static function list_snapshots_for_parts( string $history_dir, string $name, string $ext ): array {
		if ( ! is_dir( $history_dir ) ) {
			return array();
		}

		$escaped_name = addcslashes( $name, '*?[]' );
		$pattern      = rtrim( $history_dir, '/\\' ) . '/' . $escaped_name . '.*.' . $ext;

		$files = glob( $pattern );
		if ( empty( $files ) ) {
			return array();
		}

		$snapshots = array();
		foreach ( $files as $file ) {
			$file_base = basename( $file );
			if ( '.htaccess' === $file_base || 'index.php' === $file_base ) {
				continue;
			}

			// Validate snapshot format: <name>.<ts>.<ext> or <name>.<ts>_<subts>.<ext>
			$regex = '/^' . preg_quote( $name, '/' ) . '\.(\d+)(?:_\d+)?\.' . preg_quote( $ext, '/' ) . '$/';
			if ( ! preg_match( $regex, $file_base, $m ) ) {
				continue;
			}

			$ts          = (int) $m[1];
			$size        = (int) @filesize( $file );
			$snapshots[] = array(
				'path'      => $file,
				'timestamp' => $ts,
				'bytes'     => $size,
			);
		}

		// Sort newest first (descending timestamp).
		usort( $snapshots, function ( $a, $b ) {
			if ( $a['timestamp'] === $b['timestamp'] ) {
				return strcmp( $b['path'], $a['path'] );
			}
			return ( $a['timestamp'] > $b['timestamp'] ) ? -1 : 1;
		} );

		return $snapshots;
	}

	/**
	 * Restore an asset file from history snapshot.
	 *
	 * @param string $basename  Target file basename (e.g. style-page-goi-subscription.css).
	 * @param int    $timestamp Optional specific snapshot timestamp (0 for newest).
	 * @return bool True on success, false on failure.
	 */
	public static function restore( string $basename, int $timestamp = 0 ): bool {
		$parts = self::parse_file_parts( $basename );
		if ( null === $parts ) {
			error_log( sprintf( '[Align AssetHistory] Restore failed: Invalid or non-whitelisted basename %s', $basename ) );
			return false;
		}

		$history_dir = self::get_history_dir( $basename );
		$snapshots   = self::list_snapshots_for_parts( $history_dir, $parts['name'], $parts['ext'] );

		if ( empty( $snapshots ) ) {
			error_log( sprintf( '[Align AssetHistory] Restore failed: No snapshots found for %s', $basename ) );
			return false;
		}

		$chosen = null;
		if ( $timestamp > 0 ) {
			foreach ( $snapshots as $snap ) {
				if ( $snap['timestamp'] === $timestamp ) {
					$chosen = $snap;
					break;
				}
			}
			if ( null === $chosen ) {
				error_log( sprintf( '[Align AssetHistory] Restore failed: Snapshot for %s at timestamp %d not found', $basename, $timestamp ) );
				return false;
			}
		} else {
			$chosen = $snapshots[0];
		}

		// Live target path in builder assets directory.
		$assets_dir = self::get_assets_dir( $basename );
		$live_file  = rtrim( $assets_dir, '/\\' ) . '/' . $parts['name'] . '.' . $parts['ext'];

		// Snapshot current live file before overwriting (if it exists and is non-empty).
		if ( file_exists( $live_file ) && filesize( $live_file ) > 0 ) {
			self::snapshot( $live_file, 'overwrite' );
		}

		// Copy chosen snapshot back to live file path.
		$copied = @copy( $chosen['path'], $live_file );
		if ( ! $copied ) {
			error_log( sprintf( '[Align AssetHistory] [ERROR] Failed to restore %s from %s', $live_file, $chosen['path'] ) );
			return false;
		}

		@clearstatcache( true, $live_file );
		$restored_bytes = filesize( $live_file );
		error_log( sprintf( '[Align AssetHistory] Restored %s from snapshot %s (Bytes: %d)', $live_file, $chosen['path'], $restored_bytes ) );

		return true;
	}
}
