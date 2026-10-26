<?php
/**
 * Git Updater for Align Agent Plugin
 *
 * @package Align_Agent
 * @subpackage Includes
 */

defined( 'ABSPATH' ) || exit;

class Align_Agent_Git_Updater {

	const REPO          = 'bibikeo1996/Align-Agent';
	const DEFAULT_TOKEN = '';
	const OPTION_TOKEN  = 'align_agent_git_token';

	public static function init(): void {
		add_action( 'wp_ajax_align_agent_git_test', array( __CLASS__, 'ajax_test' ) );
		add_action( 'wp_ajax_align_agent_git_pull', array( __CLASS__, 'ajax_pull' ) );
		add_action( 'wp_ajax_align_agent_git_revert', array( __CLASS__, 'ajax_revert' ) );
	}

	public static function get_backup_file(): string {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . 'align-backups/align-agent-backup-latest.zip';
	}

	public static function has_backup(): bool {
		return file_exists( self::get_backup_file() );
	}

	public static function get_token(): string {
		$saved = get_option( self::OPTION_TOKEN, '' );
		if ( empty( $saved ) ) {
			$saved = get_option( 'align_git_token', '' );
		}
		return ! empty( $saved ) ? $saved : self::DEFAULT_TOKEN;
	}

	public static function ajax_test(): void {
		$nonce_key = isset( $_REQUEST['security'] ) ? 'security' : 'nonce';
		check_ajax_referer( 'align_agent_admin_nonce', $nonce_key );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized.', 'align-agent' ) ) );
		}

		$token   = sanitize_text_field( wp_unslash( $_POST['token'] ?? self::get_token() ) );
		$api_url = 'https://api.github.com/repos/' . self::REPO . '/branches/main';

		$headers = array(
			'User-Agent'           => 'Align-Agent-Updater/1.0',
			'Accept'               => 'application/vnd.github+json',
			'X-GitHub-Api-Version' => '2022-11-28',
			'Authorization'        => 'Bearer ' . trim( $token ),
		);

		$response = wp_remote_get( $api_url, array( 'timeout' => 15, 'headers' => $headers ) );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || empty( $body ) ) {
			wp_send_json_error( array( 'message' => $body['message'] ?? ( 'HTTP ' . $code ) ) );
		}

		$sha = substr( $body['commit']['sha'] ?? 'Unknown', 0, 7 );
		wp_send_json_success( array(
			'message' => sprintf( esc_html__( 'Connected to %s! Latest commit: %s', 'align-agent' ), self::REPO, $sha ),
			'sha'     => $sha,
		) );
	}

	public static function ajax_pull(): void {
		$nonce_key = isset( $_REQUEST['security'] ) ? 'security' : 'nonce';
		check_ajax_referer( 'align_agent_admin_nonce', $nonce_key );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized.', 'align-agent' ) ) );
		}

		$token = sanitize_text_field( wp_unslash( $_POST['token'] ?? self::get_token() ) );
		if ( ! empty( $_POST['token'] ) ) {
			update_option( self::OPTION_TOKEN, $token );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( ! WP_Filesystem() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Filesystem init error.', 'align-agent' ) ) );
		}
		global $wp_filesystem;

		$upload_dir = wp_upload_dir();
		$backup_dir = trailingslashit( $upload_dir['basedir'] ) . 'align-backups/';
		$temp_dir   = trailingslashit( $upload_dir['basedir'] ) . 'align-temp/';
		wp_mkdir_p( $backup_dir );
		wp_mkdir_p( $temp_dir );

		$target_dir = wp_normalize_path( WP_PLUGIN_DIR . '/align-agent' );

		// 1. Snapshot backup
		if ( class_exists( '\\ZipArchive' ) ) {
			$zip = new \ZipArchive();
			if ( true === $zip->open( $backup_dir . 'align-agent-backup-latest.zip', \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
				$files = new \RecursiveIteratorIterator(
					new \RecursiveDirectoryIterator( $target_dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
					\RecursiveIteratorIterator::LEAVES_ONLY
				);
				foreach ( $files as $file ) {
					if ( ! $file->isDir() ) {
						$zip->addFile( $file->getRealPath(), substr( $file->getRealPath(), strlen( $target_dir ) + 1 ) );
					}
				}
				$zip->close();
				update_option( 'align_agent_git_last_backup_date', current_time( 'mysql' ) );
				update_option( 'align_agent_git_last_backup_size', (int) @filesize( $backup_dir . 'align-agent-backup-latest.zip' ) );
			}
		}

		// 2. Download zipball
		$zip_url = 'https://api.github.com/repos/' . self::REPO . '/zipball/main';
		$tmp_zip = $temp_dir . 'align_agent_zip_' . wp_generate_password( 6, false ) . '.zip';
		$headers = array(
			'User-Agent'           => 'Align-Agent-Updater/1.0',
			'Accept'               => 'application/vnd.github+json',
			'X-GitHub-Api-Version' => '2022-11-28',
			'Authorization'        => 'Bearer ' . trim( $token ),
		);

		$res = wp_remote_get( $zip_url, array(
			'timeout'  => 300,
			'stream'   => true,
			'filename' => $tmp_zip,
			'headers'  => $headers,
		) );

		if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
			$wp_filesystem->delete( $tmp_zip );
			$err = is_wp_error( $res ) ? $res->get_error_message() : ( 'HTTP ' . wp_remote_retrieve_response_code( $res ) );
			wp_send_json_error( array( 'message' => sprintf( esc_html__( 'Download failed: %s', 'align-agent' ), $err ) ) );
		}

		// 3. Extract & Copy
		$extract_base = $temp_dir . 'align_agent_extract_' . wp_generate_password( 6, false ) . '/';
		$wp_filesystem->mkdir( $extract_base );
		$unzip = unzip_file( $tmp_zip, $extract_base );
		$wp_filesystem->delete( $tmp_zip );

		if ( is_wp_error( $unzip ) ) {
			$wp_filesystem->delete( $extract_base, true );
			wp_send_json_error( array( 'message' => $unzip->get_error_message() ) );
		}

		$subdirs    = glob( $extract_base . '*', GLOB_ONLYDIR );
		$source_dir = ! empty( $subdirs ) ? $subdirs[0] : $extract_base;

		copy_dir( $source_dir, $target_dir );
		$wp_filesystem->delete( $extract_base, true );

		if ( function_exists( 'opcache_reset' ) ) {
			@opcache_reset();
		}

		update_option( 'align_agent_git_last_updated', current_time( 'mysql' ) );
		error_log( '[Align GitUpdater] Align Agent successfully updated from GitHub repository.' );

		wp_send_json_success( array( 'message' => esc_html__( 'Align Agent successfully updated from GitHub!', 'align-agent' ) ) );
	}

	public static function ajax_revert(): void {
		$nonce_key = isset( $_REQUEST['security'] ) ? 'security' : 'nonce';
		check_ajax_referer( 'align_agent_admin_nonce', $nonce_key );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized.', 'align-agent' ) ) );
		}

		$backup_file = self::get_backup_file();
		if ( ! file_exists( $backup_file ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No backup snapshot found for Align Agent.', 'align-agent' ) ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( ! WP_Filesystem() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Filesystem init error.', 'align-agent' ) ) );
		}
		global $wp_filesystem;

		$target_dir = wp_normalize_path( WP_PLUGIN_DIR . '/align-agent' );

		// Clear target dir before restoring
		$files = glob( $target_dir . '/*' );
		if ( ! empty( $files ) ) {
			foreach ( $files as $file ) {
				if ( is_dir( $file ) ) {
					$wp_filesystem->delete( $file, true );
				} else {
					$wp_filesystem->delete( $file );
				}
			}
		}

		$unzip = unzip_file( $backup_file, $target_dir );
		if ( is_wp_error( $unzip ) ) {
			error_log( '[Align GitUpdater] Revert failed for align-agent: ' . $unzip->get_error_message() );
			wp_send_json_error( array( 'message' => sprintf( esc_html__( 'Revert failed: %s', 'align-agent' ), $unzip->get_error_message() ) ) );
		}

		if ( function_exists( 'opcache_reset' ) ) {
			@opcache_reset();
		}

		update_option( 'align_agent_git_last_updated', current_time( 'mysql' ) );
		error_log( '[Align GitUpdater] Align Agent successfully reverted from snapshot backup.' );

		wp_send_json_success( array( 'message' => esc_html__( 'Align Agent successfully reverted from backup snapshot!', 'align-agent' ) ) );
	}
}
