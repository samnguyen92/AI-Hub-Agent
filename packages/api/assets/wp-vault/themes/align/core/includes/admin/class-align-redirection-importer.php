<?php
/**
 * Align Theme - Rank Math Redirection Bulk Importer
 *
 * Provides batch importing of URL redirections directly into Rank Math SEO.
 * Supports raw pasted text (tab/comma/space separated), CSV file upload,
 * dry-run validation, loop detection, duplicate checking, and CSV export.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Redirection_Importer {

	/**
	 * Initialize hooks and AJAX actions.
	 */
	public static function init() {
		add_action( 'wp_ajax_align_preview_redirections', array( __CLASS__, 'ajax_preview_redirections' ) );
		add_action( 'wp_ajax_align_import_redirections',  array( __CLASS__, 'ajax_import_redirections' ) );
		add_action( 'wp_ajax_align_export_redirections',  array( __CLASS__, 'ajax_export_redirections' ) );
		add_action( 'wp_ajax_align_delete_redirection',   array( __CLASS__, 'ajax_delete_redirection' ) );
		add_action( 'wp_ajax_align_get_redirections',     array( __CLASS__, 'ajax_get_redirections' ) );
	}

	/**
	 * Check if Rank Math Redirections module is active and available.
	 *
	 * Verifies that Rank Math core helper exists, the Redirections module
	 * is enabled in Rank Math settings, and the database handler class is loaded.
	 *
	 * @return bool True if active and usable.
	 */
	public static function is_rank_math_active() {
		try {
			// 1. Check if Rank Math Helper class exists.
			if ( ! class_exists( '\RankMath\Helper' ) ) {
				return false;
			}

			// 2. Check if is_module_active method exists and redirections module is active.
			if ( ! method_exists( '\RankMath\Helper', 'is_module_active' ) || ! \RankMath\Helper::is_module_active( 'redirections' ) ) {
				return false;
			}

			// 3. Check if Redirections DB class is available.
			if ( ! class_exists( '\RankMath\Redirections\DB' ) ) {
				return false;
			}

			return true;
		} catch ( \Throwable $e ) {
			error_log( '[Align RedirectionImporter] is_rank_math_active check failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Preview & Validate raw pasted redirection rules (Dry Run).
	 */
	public static function ajax_preview_redirections() {
		check_ajax_referer( 'align_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized user.', 'align' ) ) );
		}

		if ( ! self::is_rank_math_active() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Rank Math SEO Redirection module is not active or installed.', 'align' ) ) );
		}

		$raw_text = isset( $_POST['raw_text'] ) ? wp_unslash( $_POST['raw_text'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( empty( trim( $raw_text ) ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please enter or paste at least one redirection line.', 'align' ) ) );
		}

		$lines   = preg_split( '/\r\n|\r|\n/', trim( $raw_text ) );
		$items   = array();
		$valid   = 0;
		$dups    = 0;
		$errors  = 0;

		$home_url  = untrailingslashit( home_url() );
		$home_host = wp_parse_url( $home_url, PHP_URL_HOST );

		foreach ( $lines as $index => $line ) {
			$line = trim( $line );
			// Skip empty lines or comments
			if ( empty( $line ) || str_starts_with( $line, '#' ) ) {
				continue;
			}

			// Parse delimiters: Tab, Comma, Semicolon, or Multiple spaces
			$parts = array();
			if ( strpos( $line, "\t" ) !== false ) {
				$parts = explode( "\t", $line );
			} elseif ( strpos( $line, ',' ) !== false ) {
				$parts = str_getcsv( $line );
			} elseif ( strpos( $line, ';' ) !== false ) {
				$parts = explode( ';', $line );
			} else {
				$parts = preg_split( '/\s+/', $line );
			}

			$parts = array_values( array_filter( array_map( 'trim', $parts ) ) );
			if ( count( $parts ) < 2 ) {
				$items[] = array(
					'line'        => $index + 1,
					'source'      => $parts[0] ?? $line,
					'destination' => '',
					'code'        => 301,
					'status'      => 'error',
					'message'     => esc_html__( 'Missing destination URL.', 'align' ),
				);
				$errors++;
				continue;
			}

			$source_raw = $parts[0];
			$dest_raw   = $parts[1];
			$code_raw   = isset( $parts[2] ) ? intval( $parts[2] ) : 301;
			if ( ! in_array( $code_raw, array( 301, 302, 307, 410, 451 ), true ) ) {
				$code_raw = 301;
			}

			// Clean source URL: strip home_url domain if pasted full URL
			$source_path = $source_raw;
			$src_parsed  = wp_parse_url( $source_raw );
			if ( ! empty( $src_parsed['host'] ) ) {
				if ( $src_parsed['host'] === $home_host ) {
					$source_path = ( $src_parsed['path'] ?? '/' ) . ( ! empty( $src_parsed['query'] ) ? '?' . $src_parsed['query'] : '' );
				}
			}

			// Strip leading slash for Rank Math pattern matching
			$clean_pattern = \RankMath\Redirections\DB::get_clean_pattern( $source_path, 'exact' );

			// Clean destination
			$dest_clean = $dest_raw;
			if ( in_array( $code_raw, array( 410, 451 ), true ) ) {
				$dest_clean = '';
			} else {
				$dest_parsed = wp_parse_url( $dest_raw );
				if ( empty( $dest_parsed['scheme'] ) && ! str_starts_with( $dest_clean, '/' ) ) {
					$dest_clean = '/' . $dest_clean;
				}
			}

			// Validation: Loop detection
			$clean_dest_pattern = \RankMath\Redirections\DB::get_clean_pattern( $dest_clean, 'exact' );
			if ( $clean_pattern === $clean_dest_pattern && ! in_array( $code_raw, array( 410, 451 ), true ) ) {
				$items[] = array(
					'line'        => $index + 1,
					'source'      => $source_path,
					'destination' => $dest_clean,
					'code'        => $code_raw,
					'status'      => 'error',
					'message'     => esc_html__( 'Self-redirection loop detected (Source equals Destination).', 'align' ),
				);
				$errors++;
				continue;
			}

			// Check existing redirection in Rank Math DB
			$existing = self::find_existing_redirection( $clean_pattern );
			$is_dup   = (bool) $existing;

			if ( $is_dup ) {
				$dups++;
				$items[] = array(
					'line'            => $index + 1,
					'source'          => $source_path,
					'pattern'         => $clean_pattern,
					'destination'     => $dest_clean,
					'code'            => $code_raw,
					'status'          => 'duplicate',
					'existing_id'     => $existing->id,
					'existing_target' => $existing->url_to,
					'message'         => sprintf( esc_html__( 'Already exists in Rank Math (ID #%d -> %s).', 'align' ), $existing->id, $existing->url_to ),
				);
			} else {
				$valid++;
				$items[] = array(
					'line'        => $index + 1,
					'source'      => $source_path,
					'pattern'     => $clean_pattern,
					'destination' => $dest_clean,
					'code'        => $code_raw,
					'status'      => 'valid',
					'message'     => esc_html__( 'Ready to import.', 'align' ),
				);
			}
		}

		wp_send_json_success( array(
			'total'      => count( $items ),
			'valid'      => $valid,
			'duplicates' => $dups,
			'errors'     => $errors,
			'items'      => $items,
		) );
	}

	/**
	 * Execute Import into Rank Math Database.
	 */
	public static function ajax_import_redirections() {
		check_ajax_referer( 'align_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized user.', 'align' ) ) );
		}

		if ( ! self::is_rank_math_active() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Rank Math SEO is not available.', 'align' ) ) );
		}

		$items_raw = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$overwrite = ! empty( $_POST['overwrite'] ) && '1' === $_POST['overwrite'];

		$items = json_decode( $items_raw, true );
		if ( ! is_array( $items ) || empty( $items ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No valid items received for import.', 'align' ) ) );
		}

		$imported = 0;
		$skipped  = 0;
		$updated  = 0;
		$failed   = 0;

		foreach ( $items as $it ) {
			if ( empty( $it['pattern'] ) ) {
				continue;
			}

			$pattern = sanitize_text_field( $it['pattern'] );
			$dest    = esc_url_raw( $it['destination'] );
			$code    = intval( $it['code'] ?? 301 );
			if ( ! in_array( $code, array( 301, 302, 307, 410, 451 ), true ) ) {
				$code = 301;
			}

			// Check existing
			$existing = self::find_existing_redirection( $pattern );
			if ( $existing ) {
				if ( ! $overwrite ) {
					$skipped++;
					continue;
				}

				// Update existing
				$sources = array(
					array(
						'pattern'    => $pattern,
						'comparison' => 'exact',
						'ignore'     => '',
					),
				);

				try {
					$updated_res = \RankMath\Redirections\DB::update( array(
						'id'          => $existing->id,
						'sources'     => $sources,
						'url_to'      => $dest,
						'header_code' => $code,
						'status'      => 'active',
						'updated'     => current_time( 'mysql' ),
					) );

					if ( $updated_res ) {
						$updated++;
					} else {
						$failed++;
					}
				} catch ( \Throwable $e ) {
					error_log( '[Align RedirectionImporter] DB::update failed: ' . $e->getMessage() );
					$failed++;
				}
				continue;
			}

			// Insert new record
			$sources = array(
				array(
					'pattern'    => $pattern,
					'comparison' => 'exact',
					'ignore'     => '',
				),
			);

			try {
				$insert_id = \RankMath\Redirections\DB::add( array(
					'sources'     => $sources,
					'url_to'      => $dest,
					'header_code' => $code,
					'status'      => 'active',
				) );

				if ( $insert_id ) {
					$imported++;
				} else {
					$failed++;
				}
			} catch ( \Throwable $e ) {
				error_log( '[Align RedirectionImporter] DB::add failed: ' . $e->getMessage() );
				$failed++;
			}
		}

		// Purge Rank Math cache
		self::purge_rankmath_cache();

		error_log( sprintf( '[Align RedirectionImporter] Import finished: %d new, %d updated, %d skipped, %d failed', $imported, $updated, $skipped, $failed ) );

		wp_send_json_success( array(
			'imported' => $imported,
			'updated'  => $updated,
			'skipped'  => $skipped,
			'failed'   => $failed,
			'message'  => sprintf(
				esc_html__( 'Import completed successfully: %d created, %d updated, %d skipped.', 'align' ),
				$imported,
				$updated,
				$skipped
			),
		) );
	}

	/**
	 * Get list of active redirections for admin table.
	 */
	public static function ajax_get_redirections() {
		check_ajax_referer( 'align_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) || ! self::is_rank_math_active() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized or Rank Math is inactive.', 'align' ) ) );
		}

		$limit  = isset( $_GET['limit'] ) ? intval( $_GET['limit'] ) : 50;
		$offset = isset( $_GET['offset'] ) ? intval( $_GET['offset'] ) : 0;
		$search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';

		$args = array(
			'limit'  => $limit,
			'offset' => $offset,
			'status' => 'active',
		);
		if ( ! empty( $search ) ) {
			$args['search'] = $search;
		}

		$data = array();
		try {
			$data = \RankMath\Redirections\DB::get_redirections( $args );
		} catch ( \Throwable $e ) {
			error_log( '[Align RedirectionImporter] ajax_get_redirections error: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to retrieve redirections from Rank Math.', 'align' ) ) );
		}

		$list = array();

		if ( ! empty( $data['redirections'] ) && is_iterable( $data['redirections'] ) ) {
			foreach ( $data['redirections'] as $r ) {
				$sources = maybe_unserialize( $r['sources'] );
				$pattern = '';
				if ( is_array( $sources ) && ! empty( $sources[0]['pattern'] ) ) {
					$pattern = $sources[0]['pattern'];
				}
				$list[] = array(
					'id'            => $r['id'],
					'source'        => '/' . ltrim( $pattern, '/' ),
					'url_to'        => $r['url_to'],
					'header_code'   => $r['header_code'],
					'hits'          => $r['hits'],
					'created'       => $r['created'],
					'last_accessed' => $r['last_accessed'],
				);
			}
		}

		wp_send_json_success( array(
			'total'        => intval( $data['count'] ?? count( $list ) ),
			'redirections' => $list,
		) );
	}

	/**
	 * Delete a single redirection rule.
	 */
	public static function ajax_delete_redirection() {
		check_ajax_referer( 'align_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) || ! self::is_rank_math_active() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized.', 'align' ) ) );
		}

		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid ID.', 'align' ) ) );
		}

		try {
			$res = \RankMath\Redirections\DB::delete( $id );
			if ( $res ) {
				self::purge_rankmath_cache();
				wp_send_json_success();
			}
		} catch ( \Throwable $e ) {
			error_log( '[Align RedirectionImporter] ajax_delete_redirection error: ' . $e->getMessage() );
		}

		wp_send_json_error( array( 'message' => esc_html__( 'Failed to delete redirection.', 'align' ) ) );
	}

	/**
	 * Export all redirections to CSV for backup.
	 */
	public static function ajax_export_redirections() {
		if ( ! current_user_can( 'manage_options' ) || ! self::is_rank_math_active() ) {
			wp_die( 'Unauthorized.' );
		}

		check_admin_referer( 'align_export_redirections_nonce' );

		$data = array();
		try {
			$data = \RankMath\Redirections\DB::get_redirections( array( 'limit' => 5000, 'status' => 'any' ) );
		} catch ( \Throwable $e ) {
			error_log( '[Align RedirectionImporter] ajax_export_redirections error: ' . $e->getMessage() );
		}
		$rows = $data['redirections'] ?? array();

		$filename = 'rankmath-redirections-' . gmdate( 'Y-m-d-His' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'Source URL', 'Destination URL', 'HTTP Code', 'Status', 'Hits', 'Created' ) );

		if ( is_iterable( $rows ) ) {
			foreach ( $rows as $r ) {
				$sources = maybe_unserialize( $r['sources'] );
				$pattern = '';
				if ( is_array( $sources ) && ! empty( $sources[0]['pattern'] ) ) {
					$pattern = '/' . ltrim( $sources[0]['pattern'], '/' );
				}
				fputcsv( $output, array(
					$pattern,
					$r['url_to'],
					$r['header_code'],
					$r['status'],
					$r['hits'],
					$r['created'],
				) );
			}
		}

		fclose( $output );
		exit;
	}

	/**
	 * Find an existing redirection record by pattern.
	 *
	 * @param string $pattern Clean source pattern.
	 * @return object|null Database record or null.
	 */
	private static function find_existing_redirection( $pattern ) {
		try {
			global $wpdb;
			$table = $wpdb->prefix . 'rank_math_redirections';
			// Search in serialized sources string
			$like = '%' . $wpdb->esc_like( $pattern ) . '%';
			$sql  = $wpdb->prepare( "SELECT * FROM {$table} WHERE sources LIKE %s AND status != 'trashed' LIMIT 1", $like );
			return $wpdb->get_row( $sql );
		} catch ( \Throwable $e ) {
			error_log( '[Align RedirectionImporter] find_existing_redirection error: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Purge Rank Math redirection cache table.
	 */
	public static function purge_rankmath_cache() {
		try {
			global $wpdb;
			$cache_table = $wpdb->prefix . 'rank_math_redirections_cache';
			$wpdb->query( "TRUNCATE TABLE {$cache_table}" );
		} catch ( \Throwable $e ) {
			error_log( '[Align RedirectionImporter] purge_rankmath_cache error: ' . $e->getMessage() );
		}
	}
}
