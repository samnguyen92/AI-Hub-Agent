<?php
/**
 * Align Ecosystem Plugin Git Updater Engine
 *
 * Provides GitHub API integration with Fine-Grained Personal Access Tokens (PAT)
 * to test, snapshot-backup, and update Align ecosystem plugins directly from GitHub.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Plugin_Updater {

	const DEFAULT_TOKEN = '';
	const USER_AGENT    = 'Align-Plugin-Git-Updater/1.0';

	/**
	 * Boot the plugin updater engine.
	 */
	public static function init(): void {
		add_action( 'wp_ajax_align_git_pull_plugin', [ __CLASS__, 'ajax_pull_plugin' ] );
		add_action( 'wp_ajax_align_git_test_plugin', [ __CLASS__, 'ajax_test_plugin' ] );
		add_action( 'wp_ajax_align_git_revert_plugin', [ __CLASS__, 'ajax_revert_plugin' ] );
	}

	/**
	 * Retrieve the GitHub Fine-Grained Token.
	 */
	public static function get_token(): string {
		$token = get_option( 'align_git_token', '' );
		if ( empty( $token ) || 0 === strpos( $token, 'ghp_' ) ) {
			return self::DEFAULT_TOKEN;
		}
		return $token;
	}

	/**
	 * AJAX endpoint to test GitHub connection for a plugin.
	 */
	public static function ajax_test_plugin(): void {
		$nonce = $_POST['align_settings_nonce'] ?? $_POST['nonce'] ?? '';
		if ( ! wp_verify_nonce( $nonce, 'align_settings_nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized request.', 'align' ) ] );
		}

		$repo   = ! empty( $_POST['repository'] ) ? sanitize_text_field( wp_unslash( $_POST['repository'] ) ) : '';
		$branch = ! empty( $_POST['branch'] ) ? sanitize_text_field( wp_unslash( $_POST['branch'] ) ) : 'main';
		$token  = ! empty( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : self::get_token();

		if ( empty( $repo ) ) {
			wp_send_json_error( [ 'message' => __( 'Repository is required.', 'align' ) ] );
		}

		$res = self::verify_repo( $repo, $branch, $token );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( [ 'message' => $res->get_error_message() ] );
		}

		wp_send_json_success( $res );
	}

	/**
	 * Verify a repository branch via GitHub REST API with Fine-Grained Token.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function verify_repo( string $repo, string $branch = 'main', string $token = '' ) {
		$token = ! empty( $token ) ? $token : self::get_token();
		$parts = explode( '/', trim( $repo, '/' ) );
		if ( count( $parts ) < 2 ) {
			return new WP_Error( 'invalid_repo', __( 'Invalid repository format (expected owner/repo).', 'align' ) );
		}

		$api_url = sprintf(
			'https://api.github.com/repos/%s/%s/branches/%s',
			rawurlencode( $parts[0] ),
			rawurlencode( $parts[1] ),
			rawurlencode( $branch )
		);

		$headers = [
			'User-Agent'           => self::USER_AGENT,
			'Accept'               => 'application/vnd.github+json',
			'X-GitHub-Api-Version' => '2022-11-28',
		];
		if ( ! empty( $token ) ) {
			$headers['Authorization'] = 'Bearer ' . trim( $token );
		}

		$response = wp_remote_get( $api_url, [ 'timeout' => 15, 'headers' => $headers ] );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || empty( $body ) ) {
			$msg = $body['message'] ?? ( 'GitHub API returned HTTP ' . $code );
			return new WP_Error( 'github_error', $msg );
		}

		$commit = $body['commit'] ?? [];
		return [
			'message' => __( 'Connection verified successfully!', 'align' ),
			'sha'     => substr( $commit['sha'] ?? 'Unknown', 0, 7 ),
			'author'  => $commit['commit']['author']['name'] ?? 'Unknown',
			'branch'  => $body['name'] ?? $branch,
		];
	}

	/**
	 * AJAX endpoint to pull code for a specific Align plugin.
	 */
	public static function ajax_pull_plugin(): void {
		$nonce = $_POST['align_settings_nonce'] ?? $_POST['nonce'] ?? '';
		if ( ! wp_verify_nonce( $nonce, 'align_settings_nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized request.', 'align' ) ] );
		}

		$slug   = ! empty( $_POST['plugin_slug'] ) ? sanitize_key( $_POST['plugin_slug'] ) : '';
		$repo   = ! empty( $_POST['repository'] ) ? sanitize_text_field( wp_unslash( $_POST['repository'] ) ) : '';
		$branch = ! empty( $_POST['branch'] ) ? sanitize_text_field( wp_unslash( $_POST['branch'] ) ) : 'main';
		$token  = ! empty( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : self::get_token();

		if ( empty( $slug ) || empty( $repo ) ) {
			wp_send_json_error( [ 'message' => __( 'Plugin slug and repository are required.', 'align' ) ] );
		}

		$target_dir = wp_normalize_path( WP_PLUGIN_DIR . '/' . $slug );
		if ( ! is_dir( $target_dir ) ) {
			wp_send_json_error( [ 'message' => sprintf( __( 'Target plugin directory does not exist: %s', 'align' ), $slug ) ] );
		}

		$result = self::perform_pull_plugin( $slug, $repo, $branch, $token );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'message' => sprintf( __( 'Plugin "%s" cleanly updated from GitHub!', 'align' ), $slug ) ] );
	}

	/**
	 * Perform pull, backup, and clean replacement for a plugin.
	 *
	 * @return true|WP_Error
	 */
	public static function perform_pull_plugin( string $slug, string $repo, string $branch = 'main', string $token = '' ) {
		$token      = ! empty( $token ) ? $token : self::get_token();
		$target_dir = wp_normalize_path( WP_PLUGIN_DIR . '/' . $slug );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( ! WP_Filesystem() ) {
			return new WP_Error( 'fs_error', __( 'Failed to initialize WordPress Filesystem.', 'align' ) );
		}
		global $wp_filesystem;

		$upload_dir = wp_upload_dir();
		$backup_dir = trailingslashit( $upload_dir['basedir'] ) . 'align-backups/';
		$temp_dir   = wp_normalize_path( trailingslashit( $upload_dir['basedir'] ) . 'align-temp/' );

		if ( ! is_dir( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );
		}
		if ( ! is_dir( $temp_dir ) ) {
			wp_mkdir_p( $temp_dir );
		}

		// 1. Create safety snapshot backup
		if ( class_exists( 'ZipArchive' ) ) {
			$backup_file = $backup_dir . $slug . '-backup-latest.zip';
			$zip = new ZipArchive();
			if ( true === $zip->open( $backup_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
				$files = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator( $target_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
					RecursiveIteratorIterator::LEAVES_ONLY
				);
				foreach ( $files as $file ) {
					if ( ! $file->isDir() ) {
						$rel = substr( $file->getRealPath(), strlen( $target_dir ) + 1 );
						$zip->addFile( $file->getRealPath(), $rel );
					}
				}
				$zip->close();
				error_log( sprintf( '[Align PluginUpdater] Created backup snapshot for %s at %s', $slug, $backup_file ) );
			}
		}

		// 2. Download zipball from GitHub API
		$zip_url = sprintf( 'https://api.github.com/repos/%s/zipball/%s', trim( $repo, '/' ), rawurlencode( $branch ) );
		$headers = [
			'User-Agent'           => self::USER_AGENT,
			'Accept'               => 'application/vnd.github+json',
			'X-GitHub-Api-Version' => '2022-11-28',
		];
		if ( ! empty( $token ) ) {
			$headers['Authorization'] = 'Bearer ' . trim( $token );
		}

		$tmp_zip  = $temp_dir . $slug . '_zip_' . wp_generate_password( 8, false ) . '.zip';
		$response = wp_remote_get( $zip_url, [
			'timeout'  => 300,
			'stream'   => true,
			'filename' => $tmp_zip,
			'headers'  => $headers,
		] );

		if ( is_wp_error( $response ) ) {
			$wp_filesystem->delete( $tmp_zip );
			return new WP_Error( 'download_failed', sprintf( __( 'Download failed: %s', 'align' ), $response->get_error_message() ) );
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$wp_filesystem->delete( $tmp_zip );
			return new WP_Error( 'download_http_error', sprintf( 'GitHub returned HTTP %d for %s.', wp_remote_retrieve_response_code( $response ), $slug ) );
		}

		// 3. Extract to temp directory
		$extract_base = $temp_dir . $slug . '_extract_' . wp_generate_password( 8, false ) . '/';
		$wp_filesystem->mkdir( $extract_base );
		$unzip = unzip_file( $tmp_zip, $extract_base );
		$wp_filesystem->delete( $tmp_zip );

		if ( is_wp_error( $unzip ) ) {
			$wp_filesystem->delete( $extract_base, true );
			return $unzip;
		}

		// 4. Locate extracted files (inside owner-repo-sha wrapper)
		$subdirs = glob( $extract_base . '*', GLOB_ONLYDIR );
		$source_dir = ! empty( $subdirs ) ? $subdirs[0] : $extract_base;

		// 5. Replace plugin files
		$copy_res = copy_dir( $source_dir, $target_dir );
		$wp_filesystem->delete( $extract_base, true );

		if ( is_wp_error( $copy_res ) ) {
			return $copy_res;
		}

		// 6. Flush OPcache if enabled
		if ( function_exists( 'opcache_reset' ) ) {
			@opcache_reset();
		}

		error_log( sprintf( '[Align PluginUpdater] Cleanly updated plugin %s from %s (branch: %s)', $slug, $repo, $branch ) );
		return true;
	}

	/**
	 * Get the snapshot backup archive path for a plugin.
	 */
	public static function get_backup_file( string $slug ): string {
		$upload_dir = wp_upload_dir();
		$backup_dir = trailingslashit( $upload_dir['basedir'] ) . 'align-backups/';
		return $backup_dir . sanitize_file_name( $slug ) . '-backup-latest.zip';
	}

	/**
	 * Check if a snapshot backup archive exists for a plugin.
	 */
	public static function has_backup( string $slug ): bool {
		return file_exists( self::get_backup_file( $slug ) );
	}

	/**
	 * AJAX handler to revert an ecosystem plugin from its safety snapshot backup.
	 */
	public static function ajax_revert_plugin(): void {
		$nonce = $_POST['align_settings_nonce'] ?? $_POST['nonce'] ?? '';
		if ( ! wp_verify_nonce( $nonce, 'align_settings_nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized request.', 'align' ) ] );
		}

		$slug = ! empty( $_POST['plugin_slug'] ) ? sanitize_file_name( wp_unslash( $_POST['plugin_slug'] ) ) : '';
		if ( empty( $slug ) || ! in_array( $slug, [ 'align-files', 'align-seo-checker', 'align-deploy', 'align-agent' ], true ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid plugin identifier.', 'align' ) ] );
		}

		$backup_file = self::get_backup_file( $slug );
		if ( ! file_exists( $backup_file ) ) {
			wp_send_json_error( [ 'message' => __( 'No safety backup snapshot found for this plugin.', 'align' ) ] );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( ! WP_Filesystem() ) {
			wp_send_json_error( [ 'message' => __( 'WordPress filesystem initialization failed.', 'align' ) ] );
		}
		global $wp_filesystem;

		$target_dir   = wp_normalize_path( WP_PLUGIN_DIR . '/' . $slug );
		$upload_dir   = wp_upload_dir();
		$temp_dir     = trailingslashit( $upload_dir['basedir'] ) . 'align-temp/';
		wp_mkdir_p( $temp_dir );

		$extract_base = $temp_dir . $slug . '_revert_' . wp_generate_password( 8, false ) . '/';
		$wp_filesystem->mkdir( $extract_base );
		$unzip = unzip_file( $backup_file, $extract_base );

		if ( is_wp_error( $unzip ) ) {
			$wp_filesystem->delete( $extract_base, true );
			wp_send_json_error( [ 'message' => $unzip->get_error_message() ] );
		}

		$wp_filesystem->delete( $target_dir, true );
		$wp_filesystem->mkdir( $target_dir );
		copy_dir( $extract_base, $target_dir );
		$wp_filesystem->delete( $extract_base, true );

		if ( function_exists( 'opcache_reset' ) ) {
			@opcache_reset();
		}

		error_log( sprintf( '[Align PluginUpdater] Cleanly reverted plugin %s from snapshot backup.', $slug ) );

		wp_send_json_success( [
			'message' => sprintf( __( '%s was successfully restored from previous backup snapshot!', 'align' ), $slug ),
		] );
	}
}
