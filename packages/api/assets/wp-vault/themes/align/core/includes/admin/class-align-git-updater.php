<?php
/**
 * Git Updater Engine for Align Theme.
 * Handles GitHub repository updates, persistent backup snapshots, clean replacement, and manual revert.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Git_Updater {

	const DEFAULT_REPO   = 'bibikeo1996/Align-Real-Builder';
	const DEFAULT_BRANCH = 'main';
	const DEFAULT_TOKEN  = '';
	const USER_AGENT     = 'Align-Theme-Git-Updater/1.0';

	public static function get_token(): string {
		$token = get_option( 'align_git_token', '' );
		if ( empty( $token ) || 0 === strpos( $token, 'ghp_' ) ) {
			return self::DEFAULT_TOKEN;
		}
		return $token;
	}

	public static function init() {
		add_action( 'wp_ajax_align_git_test_connection', array( __CLASS__, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_align_git_pull_code', array( __CLASS__, 'ajax_pull_code' ) );
		add_action( 'wp_ajax_align_git_revert_backup', array( __CLASS__, 'ajax_revert_backup' ) );
		add_action( 'init', array( __CLASS__, 'handle_webhook' ) );
		add_action( 'init', array( __CLASS__, 'handle_emergency_revert' ) );
	}

	public static function get_backup_dir() {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . 'align-backups/';
	}

	public static function get_backup_file() {
		return self::get_backup_dir() . 'align-theme-backup-latest.zip';
	}

	public static function get_temp_working_dir() {
		$upload_dir = wp_upload_dir();
		$temp_dir   = wp_normalize_path( trailingslashit( $upload_dir['basedir'] ) . 'align-temp/' );
		if ( ! is_dir( $temp_dir ) ) {
			wp_mkdir_p( $temp_dir );
			@file_put_contents( $temp_dir . '.htaccess', 'deny from all' );
		}
		if ( ! is_writable( $temp_dir ) ) {
			return wp_normalize_path( trailingslashit( get_temp_dir() ) );
		}
		return $temp_dir;
	}

	public static function set_maintenance_mode( $enable = false ) {
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		$file = ABSPATH . '.maintenance';
		if ( $enable ) {
			$string = '<?php $upgrading = ' . time() . '; ?>';
			$wp_filesystem->delete( $file );
			$wp_filesystem->put_contents( $file, $string, FS_CHMOD_FILE );
			error_log( '[Align GitUpdater] WordPress Maintenance Mode: ENABLED.' );
		} else {
			if ( file_exists( $file ) ) {
				$wp_filesystem->delete( $file );
			}
			error_log( '[Align GitUpdater] WordPress Maintenance Mode: DISABLED.' );
		}
	}

	/**
	 * Create a persistent zip snapshot of the current theme in wp-content/uploads/align-backups/.
	 *
	 * @return bool|WP_Error True on success or WP_Error.
	 */
	public static function create_persistent_backup() {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'zip_ext_missing', __( 'PHP ZipArchive extension is required for persistent backup.', 'align' ) );
		}
		$theme_dir   = wp_normalize_path( get_template_directory() );
		$backup_dir  = self::get_backup_dir();
		$backup_file = self::get_backup_file();

		if ( ! is_dir( $theme_dir ) ) {
			return new WP_Error( 'theme_not_found', __( 'Current theme directory not found.', 'align' ) );
		}
		if ( ! is_dir( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );
			@file_put_contents( $backup_dir . '.htaccess', 'deny from all' );
		}

		$zip = new ZipArchive();
		if ( $zip->open( $backup_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
			return new WP_Error( 'zip_create_failed', __( 'Could not create persistent backup zip file.', 'align' ) );
		}

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $theme_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ( $files as $name => $file ) {
			if ( ! $file->isDir() ) {
				$file_path     = wp_normalize_path( $file->getRealPath() );
				$relative_path = substr( $file_path, strlen( $theme_dir ) + 1 );
				$zip->addFile( $file_path, $relative_path );
			}
		}
		$zip->close();

		$size = file_exists( $backup_file ) ? filesize( $backup_file ) : 0;
		update_option( 'align_git_last_backup_date', current_time( 'mysql' ) );
		update_option( 'align_git_last_backup_size', $size );
		error_log( sprintf( '[Align GitUpdater] Persistent backup snapshot created at %s (%d bytes)', $backup_file, $size ) );
		return true;
	}

	/**
	 * Restore theme from persistent backup snapshot.
	 *
	 * @return bool|WP_Error True on success or WP_Error.
	 */
	public static function restore_persistent_backup() {
		$backup_file = self::get_backup_file();
		if ( ! file_exists( $backup_file ) ) {
			return new WP_Error( 'backup_not_found', __( 'No persistent backup snapshot found to revert.', 'align' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( ! WP_Filesystem() ) {
			return new WP_Error( 'filesystem_error', __( 'Failed to initialize WordPress Filesystem.', 'align' ) );
		}
		global $wp_filesystem;

		$dest_align_dir = wp_normalize_path( get_template_directory() );
		$extract_temp   = self::get_temp_working_dir() . 'align_revert_extract_' . wp_generate_password( 10, false ) . '/';
		$wp_filesystem->mkdir( $extract_temp );

		$unzip_status = unzip_file( $backup_file, $extract_temp );
		if ( is_wp_error( $unzip_status ) ) {
			$wp_filesystem->delete( $extract_temp, true );
			return $unzip_status;
		}

		$has_functions = $wp_filesystem->is_file( trailingslashit( $extract_temp ) . 'functions.php' );
		$has_style     = $wp_filesystem->is_file( trailingslashit( $extract_temp ) . 'style.css' );
		$has_core      = $wp_filesystem->is_file( trailingslashit( $extract_temp ) . 'core/init.php' );

		if ( ! $has_functions || ! $has_style || ! $has_core ) {
			$wp_filesystem->delete( $extract_temp, true );
			return new WP_Error( 'corrupted_backup', __( 'Backup snapshot is missing essential files (functions.php, style.css, core/init.php).', 'align' ) );
		}

		self::set_maintenance_mode( true );
		try {
			if ( is_dir( $dest_align_dir ) ) {
				$wp_filesystem->delete( $dest_align_dir, true );
			}
			$wp_filesystem->mkdir( $dest_align_dir );
			$copy_res = copy_dir( $extract_temp, $dest_align_dir );
			$wp_filesystem->delete( $extract_temp, true );

			if ( is_wp_error( $copy_res ) ) {
				throw new Exception( $copy_res->get_error_message() );
			}
			if ( function_exists( 'opcache_reset' ) ) {
				@opcache_reset();
			}
			update_option( 'align_git_last_updated', current_time( 'mysql' ) . ' (Reverted)' );
			error_log( '[Align GitUpdater] Theme successfully REVERTED to previous backup snapshot.' );
		} catch ( Exception $e ) {
			$wp_filesystem->delete( $extract_temp, true );
			self::set_maintenance_mode( false );
			return new WP_Error( 'revert_failed', sprintf( __( 'Revert failed: %s', 'align' ), $e->getMessage() ) );
		}

		self::set_maintenance_mode( false );
		return true;
	}

	/**
	 * AJAX endpoint to test connection.
	 */
	public static function ajax_test_connection() {
		$nonce = $_POST['align_settings_nonce'] ?? $_POST['nonce'] ?? '';
		if ( ! wp_verify_nonce( $nonce, 'align_settings_nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized request.', 'align' ) ) );
		}

		$repo   = ! empty( $_POST['repository'] ) ? sanitize_text_field( wp_unslash( $_POST['repository'] ) ) : ( get_option( 'align_git_repository' ) ?: self::DEFAULT_REPO );
		if ( false === strpos( $repo, '/' ) ) {
			$repo = 'bibikeo1996/' . $repo;
		}
		$branch = ! empty( $_POST['branch'] ) ? sanitize_text_field( wp_unslash( $_POST['branch'] ) ) : ( get_option( 'align_git_branch' ) ?: self::DEFAULT_BRANCH );
		$token  = ! empty( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : self::get_token();

		$repo_parts = explode( '/', trim( $repo, '/' ) );
		$owner      = rawurlencode( $repo_parts[0] );
		$repo_slug  = rawurlencode( $repo_parts[1] ?? $repo_parts[0] );
		$api_url    = sprintf( 'https://api.github.com/repos/%s/%s/branches/%s', $owner, $repo_slug, rawurlencode( $branch ) );

		$headers = array( 'User-Agent' => self::USER_AGENT, 'Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28' );
		if ( ! empty( $token ) ) {
			$headers['Authorization'] = 'Bearer ' . trim( $token );
		}

		$response = wp_remote_get( $api_url, array( 'timeout' => 15, 'headers' => $headers ) );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 || empty( $body ) ) {
			$msg = $body['message'] ?? ( 'GitHub API returned HTTP ' . $code );
			wp_send_json_error( array( 'message' => $msg, 'code' => $code ) );
		}

		$commit = $body['commit'] ?? array();
		wp_send_json_success( array(
			'message' => __( 'Connection verified successfully!', 'align' ),
			'sha'     => substr( $commit['sha'] ?? 'Unknown', 0, 7 ),
			'author'  => $commit['commit']['author']['name'] ?? 'Unknown',
			'branch'  => $body['name'] ?? $branch,
		) );
	}

	/**
	 * AJAX endpoint to pull code.
	 */
	public static function ajax_pull_code() {
		$nonce = $_POST['align_settings_nonce'] ?? $_POST['nonce'] ?? '';
		if ( ! wp_verify_nonce( $nonce, 'align_settings_nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized request.', 'align' ) ) );
		}
		$repo   = ! empty( $_POST['repository'] ) ? sanitize_text_field( wp_unslash( $_POST['repository'] ) ) : ( get_option( 'align_git_repository' ) ?: self::DEFAULT_REPO );
		$branch = ! empty( $_POST['branch'] ) ? sanitize_text_field( wp_unslash( $_POST['branch'] ) ) : ( get_option( 'align_git_branch' ) ?: self::DEFAULT_BRANCH );
		$token  = ! empty( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : self::get_token();

		$result = self::perform_pull( $repo, $branch, $token );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => __( 'Align Theme cleanly updated from GitHub!', 'align' ), 'details' => $result ) );
	}

	public static function ajax_revert_backup() {
		$nonce = $_POST['align_settings_nonce'] ?? $_POST['nonce'] ?? '';
		if ( ! wp_verify_nonce( $nonce, 'align_settings_nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized request.', 'align' ) ) );
		}
		$result = self::restore_persistent_backup();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => __( 'Align Theme successfully restored to previous backup snapshot!', 'align' ) ) );
	}

	/**
	 * Perform pull, snapshot backup, clean replace and validation.
	 *
	 * @param string $repo Repository.
	 * @param string $branch Branch.
	 * @param string $token Token.
	 * @return array|WP_Error Result or error.
	 */
	public static function perform_pull( $repo = '', $branch = '', $token = '' ) {
		$repo   = ! empty( $repo ) ? trim( $repo, '/' ) : ( get_option( 'align_git_repository' ) ?: self::DEFAULT_REPO );
		if ( false === strpos( $repo, '/' ) ) {
			$repo = 'bibikeo1996/' . $repo;
		}
		$branch = ! empty( $branch ) ? $branch : ( get_option( 'align_git_branch' ) ?: self::DEFAULT_BRANCH );
		$token  = ! empty( $token ) ? $token : self::get_token();

		$dest_align_dir = wp_normalize_path( get_template_directory() );
		$themes_root    = wp_normalize_path( dirname( $dest_align_dir ) . '/' );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( ! WP_Filesystem() ) {
			return new WP_Error( 'filesystem_error', __( 'Failed to initialize WordPress Filesystem.', 'align' ) );
		}
		global $wp_filesystem;

		// 1. Create Persistent Safety Snapshot Backup FIRST
		error_log( '[Align GitUpdater] Creating persistent snapshot backup before pull...' );
		$snapshot_res = self::create_persistent_backup();
		if ( is_wp_error( $snapshot_res ) ) {
			error_log( '[Align GitUpdater] Warning: Snapshot backup creation failed: ' . $snapshot_res->get_error_message() );
		}

		// 2. Stream Download Zip from GitHub API
		$zip_url = sprintf( 'https://api.github.com/repos/%s/zipball/%s', $repo, rawurlencode( $branch ) );
		$headers = array(
			'User-Agent'           => self::USER_AGENT,
			'Accept'               => 'application/vnd.github+json',
			'X-GitHub-Api-Version' => '2022-11-28',
		);
		if ( ! empty( $token ) ) {
			$headers['Authorization'] = 'Bearer ' . trim( $token );
		}

		$temp_working = self::get_temp_working_dir();
		$tmp_zip      = $temp_working . 'align_git_zip_' . wp_generate_password( 10, false ) . '.zip';
		$response     = wp_remote_get( $zip_url, array( 'timeout' => 300, 'stream' => true, 'filename' => $tmp_zip, 'headers' => $headers ) );

		if ( is_wp_error( $response ) ) {
			$wp_filesystem->delete( $tmp_zip );
			return new WP_Error( 'download_failed', sprintf( __( 'Download failed: %s', 'align' ), $response->get_error_message() ) );
		}

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			$wp_filesystem->delete( $tmp_zip );
			return new WP_Error( 'download_http_error', sprintf( 'GitHub returned HTTP %d. Aborted safely.', wp_remote_retrieve_response_code( $response ) ) );
		}

		$zip_size = filesize( $tmp_zip );

		// 3. Extract to Temporary Folder (inside open_basedir safe uploads temp)
		$extract_base = $temp_working . 'align_git_extract_' . wp_generate_password( 12, false ) . '/';
		$wp_filesystem->mkdir( $extract_base );
		$unzip_status = unzip_file( $tmp_zip, $extract_base );
		$wp_filesystem->delete( $tmp_zip );

		if ( is_wp_error( $unzip_status ) ) {
			$wp_filesystem->delete( $extract_base, true );
			return $unzip_status;
		}

		// 4. Robust Location of Source Theme and Repository Root
		$source_align_dir = self::locate_source_align_dir( $extract_base );
		$github_root      = self::locate_github_root( $extract_base );

		// 5. Strict Pre-flight Integrity Verification
		if ( empty( $source_align_dir ) ||
			 ! file_exists( trailingslashit( $source_align_dir ) . 'functions.php' ) ||
			 ! file_exists( trailingslashit( $source_align_dir ) . 'style.css' ) ||
			 ! file_exists( trailingslashit( $source_align_dir ) . 'core/init.php' ) ) {
			$wp_filesystem->delete( $extract_base, true );
			return new WP_Error( 'invalid_theme_package', __( 'Downloaded package is corrupted or missing essential theme files (functions.php, style.css, core/init.php). Aborted safely.', 'align' ) );
		}

		// 6. Enter Maintenance Mode & Perform Clean Replacement
		self::set_maintenance_mode( true );

		try {
			if ( is_dir( $dest_align_dir ) ) {
				$wp_filesystem->delete( $dest_align_dir, true );
			}

			$wp_filesystem->mkdir( $dest_align_dir );
			$copy_result = copy_dir( $source_align_dir, $dest_align_dir );

			if ( is_wp_error( $copy_result ) ) {
				throw new Exception( $copy_result->get_error_message() );
			}

			// Post-copy verification
			if ( ! $wp_filesystem->is_file( trailingslashit( $dest_align_dir ) . 'functions.php' ) ) {
				throw new Exception( 'functions.php missing after copy.' );
			}

			// Copy root files
			foreach ( array( 'AGENTS.md', '.gitignore', 'index.php' ) as $r_file ) {
				if ( $wp_filesystem->is_file( $github_root . $r_file ) ) {
					$wp_filesystem->copy( $github_root . $r_file, $themes_root . $r_file, true );
				}
			}

			$wp_filesystem->delete( $extract_base, true );
			if ( function_exists( 'opcache_reset' ) ) {
				@opcache_reset();
			}
			update_option( 'align_git_last_updated', current_time( 'mysql' ) );

			// Automatically synchronize updated templates & parts into child theme and wp_posts DB
			if ( class_exists( 'Align_Template_Tweaks' ) && method_exists( 'Align_Template_Tweaks', 'sync_all_templates_with_db' ) ) {
				Align_Template_Tweaks::sync_all_templates_with_db();
			}

		} catch ( Exception $e ) {
			error_log( '[Align GitUpdater] Error during copy: ' . $e->getMessage() . '. Reverting from snapshot...' );
			self::restore_persistent_backup();
			$wp_filesystem->delete( $extract_base, true );
			self::set_maintenance_mode( false );
			return new WP_Error( 'replace_failed', sprintf( __( 'Update failed: %s. Reverted to previous snapshot.', 'align' ), $e->getMessage() ) );
		}

		self::set_maintenance_mode( false );
		return array( 'repository' => $repo, 'branch' => $branch, 'size_bytes' => $zip_size );
	}

	public static function handle_webhook() {
		if ( ! isset( $_GET['align-git-webhook'] ) ) return;
		if ( ! get_option( 'align_git_webhook_enabled', 0 ) ) { status_header( 403 ); die( 'Webhook disabled.' ); }
		$token = get_option( 'align_git_webhook_token', '' );
		if ( empty( $token ) || ! hash_equals( $token, (string) ( $_GET['token'] ?? '' ) ) ) { status_header( 401 ); die( 'Invalid token.' ); }
		$result = self::perform_pull();
		if ( is_wp_error( $result ) ) { status_header( 500 ); die( $result->get_error_message() ); }
		status_header( 200 ); die( 'Deployment successful.' );
	}

	public static function handle_emergency_revert() {
		if ( ! isset( $_GET['align-emergency-revert'] ) ) return;
		if ( ! current_user_can( 'manage_options' ) ) {
			$token     = get_option( 'align_git_webhook_token', '' );
			$req_token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
			if ( empty( $token ) || empty( $req_token ) || ! hash_equals( $token, $req_token ) ) {
				status_header( 403 ); die( 'Access denied.' );
			}
		}
		$res = self::restore_persistent_backup();
		if ( is_wp_error( $res ) ) {
			wp_die( 'Emergency Revert Failed: ' . esc_html( $res->get_error_message() ) );
		}
		wp_die( '<div style="font-family:sans-serif; text-align:center; margin-top:60px; max-width:600px; margin-left:auto; margin-right:auto; padding:30px; border:1px solid #c3c4c7; border-radius:6px; background:#fff;"><h2 style="color:#007017;">&#10004; Emergency Revert Successful!</h2><p style="color:#50575e; font-size:14px;">Align Theme has been cleanly restored from your persistent backup snapshot.</p><a href="' . esc_url( admin_url( 'options-general.php?page=align-settings#git-updater' ) ) . '" style="display:inline-block; margin-top:15px; padding:8px 16px; background:#2271b1; color:#fff; text-decoration:none; border-radius:3px; font-weight:600;">Return to Align Settings</a></div>' );
	}
	/**
	 * Recursively locate the Align theme source directory within extracted zipball.
	 *
	 * @param string $extract_base Extracted temp base path.
	 * @return string|false Path to source align directory or false.
	 */
	public static function locate_source_align_dir( $extract_base ) {
		$extract_base = wp_normalize_path( untrailingslashit( $extract_base ) );
		if ( file_exists( $extract_base . '/align/functions.php' ) && file_exists( $extract_base . '/align/core/init.php' ) ) {
			return $extract_base . '/align';
		}
		if ( file_exists( $extract_base . '/functions.php' ) && file_exists( $extract_base . '/core/init.php' ) ) {
			return $extract_base;
		}
		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $extract_base, RecursiveDirectoryIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);
			foreach ( $iterator as $item ) {
				if ( $item->isDir() ) {
					$dir = wp_normalize_path( $item->getRealPath() );
					if ( file_exists( $dir . '/functions.php' ) && file_exists( $dir . '/style.css' ) && file_exists( $dir . '/core/init.php' ) ) {
						$style = @file_get_contents( $dir . '/style.css' );
						if ( false !== stripos( $style, 'Theme Name: Align' ) && false === stripos( $style, 'Template: align' ) ) {
							return $dir;
						}
					}
				}
			}
		} catch ( Exception $e ) {
			error_log( '[Align GitUpdater] Recursive directory search exception: ' . $e->getMessage() );
		}
		$subdirs = @scandir( $extract_base );
		if ( is_array( $subdirs ) ) {
			foreach ( $subdirs as $sub ) {
				if ( $sub === '.' || $sub === '..' ) continue;
				$cand = wp_normalize_path( $extract_base . '/' . $sub );
				if ( is_dir( $cand ) ) {
					if ( file_exists( $cand . '/align/functions.php' ) && file_exists( $cand . '/align/core/init.php' ) ) return $cand . '/align';
					if ( file_exists( $cand . '/functions.php' ) && file_exists( $cand . '/core/init.php' ) ) return $cand;
				}
			}
		}
		return false;
	}

	/**
	 * Locate root folder of the downloaded repository package.
	 *
	 * @param string $extract_base Extracted temp base path.
	 * @return string Root repository path.
	 */
	public static function locate_github_root( $extract_base ) {
		$extract_base = wp_normalize_path( untrailingslashit( $extract_base ) );
		if ( file_exists( $extract_base . '/AGENTS.md' ) || file_exists( $extract_base . '/.gitignore' ) ) {
			return trailingslashit( $extract_base );
		}
		$subdirs = @scandir( $extract_base );
		if ( is_array( $subdirs ) ) {
			foreach ( $subdirs as $sub ) {
				if ( $sub === '.' || $sub === '..' ) continue;
				$cand = wp_normalize_path( $extract_base . '/' . $sub );
				if ( is_dir( $cand ) && ( file_exists( $cand . '/AGENTS.md' ) || file_exists( $cand . '/align' ) ) ) {
					return trailingslashit( $cand );
				}
			}
		}
		return trailingslashit( $extract_base );
	}
}


