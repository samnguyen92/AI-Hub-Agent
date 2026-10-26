<?php
/**
 * Align Agent - Global Vault Garbage Collector & AJAX Purge
 *
 * Enforces retention limits (10 per object, 500 site-wide, 50 MB compressed,
 * 7-day TTL, minimum 3 latest kept per object), foreign domain detection,
 * daily WP-Cron cleanup, and manual admin purge AJAX handler.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Vault_Gc {

	const MAX_PER_OBJECT    = 10;
	const MIN_KEEP_OBJECT   = 3;
	const MAX_SITE_BACKUPS  = 500;
	const MAX_SITE_GZ_BYTES = 52428800; // 50 MB.
	const TTL_SECONDS       = 604800;   // 7 days.
	const CRON_HOOK         = 'align_agent_vault_gc';

	/** Register cron and AJAX hooks. */
	public static function init(): void {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_cron' ) );
		add_action( 'wp_ajax_align_agent_vault_purge', array( __CLASS__, 'ajax_purge' ) );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	/** Detect backups created under a different home_url and mark them 'foreign' (REQ-12). */
	public static function mark_foreign_backups(): int {
		$current_home = (string) home_url();
		$posts        = get_posts( array(
			'post_type'      => Align_Agent_Global_Vault::CPT_BACKUP,
			'post_status'    => 'private',
			'posts_per_page' => 550,
			'orderby'        => 'ID',
			'order'          => 'DESC',
		) );

		$marked = 0;
		foreach ( $posts as $p ) {
			$meta     = json_decode( (string) $p->post_excerpt, true );
			$rec_home = is_array( $meta ) ? (string) ( $meta['home_url'] ?? '' ) : '';
			if ( '' !== $rec_home && $rec_home !== $current_home ) {
				wp_update_post( array( 'ID' => $p->ID, 'post_status' => 'foreign' ) );
				clean_post_cache( $p->ID );
				$marked++;
			}
		}
		if ( $marked > 0 ) {
			error_log( sprintf( '[Align Agent] Vault GC marked %d backup(s) as foreign.', $marked ) );
		}
		return $marked;
	}

	/** Enforce per-object and site-wide retention rules (REQ-07). */
	public static function enforce( string $object_key = '', array $protect_ids = array() ): int {
		self::mark_foreign_backups();
		$deleted     = 0;
		$protect_ids = array_map( 'absint', $protect_ids );

		if ( '' !== $object_key ) {
			$deleted += self::trim_object_excess( $object_key, $protect_ids );
		}

		$all_posts = get_posts( array(
			'post_type'      => Align_Agent_Global_Vault::CPT_BACKUP,
			'post_status'    => array( 'private', 'foreign' ),
			'posts_per_page' => 1000,
			'orderby'        => 'ID',
			'order'          => 'DESC',
		) );

		if ( empty( $all_posts ) ) {
			return $deleted;
		}

		$cutoff    = time() - self::TTL_SECONDS;
		$by_object = array();
		foreach ( $all_posts as $p ) {
			$by_object[ (string) $p->post_title ][] = $p;
		}

		$surviving = array();
		foreach ( $by_object as $key => $list ) {
			$rank = 0;
			foreach ( $list as $p ) {
				$created = strtotime( $p->post_date_gmt ?: $p->post_date );
				if ( 'foreign' === $p->post_status ) {
					if ( $created > 0 && $created < $cutoff ) {
						Align_Agent_Global_Vault::delete_backup_post( (int) $p->ID );
						$deleted++;
					}
					continue;
				}

				$rank++;
				if ( in_array( (int) $p->ID, $protect_ids, true ) ) {
					continue;
				}
				if ( $rank > self::MAX_PER_OBJECT || ( $rank > self::MIN_KEEP_OBJECT && $created > 0 && $created < $cutoff ) ) {
					Align_Agent_Global_Vault::delete_backup_post( (int) $p->ID );
					$deleted++;
					continue;
				}

				$meta        = json_decode( (string) $p->post_excerpt, true );
				$size_gz     = is_array( $meta ) ? absint( $meta['size_gz'] ?? 0 ) : strlen( (string) $p->post_content );
				$surviving[] = array( 'id' => (int) $p->ID, 'key' => $key, 'rank' => $rank, 'size_gz' => $size_gz );
			}
		}

		$total_count = count( $surviving );
		$total_bytes = array_sum( array_column( $surviving, 'size_gz' ) );

		if ( $total_count > self::MAX_SITE_BACKUPS || $total_bytes > self::MAX_SITE_GZ_BYTES ) {
			usort( $surviving, function ( array $a, array $b ): int {
				return $a['id'] <=> $b['id'];
			} );

			foreach ( $surviving as $item ) {
				if ( $total_count <= self::MAX_SITE_BACKUPS && $total_bytes <= self::MAX_SITE_GZ_BYTES ) {
					break;
				}
				if ( $item['rank'] <= self::MIN_KEEP_OBJECT ) {
					continue;
				}
				Align_Agent_Global_Vault::delete_backup_post( $item['id'] );
				$total_count--;
				$total_bytes -= $item['size_gz'];
				$deleted++;
			}
		}

		return $deleted;
	}

	/** Trim a single object_key to MAX_PER_OBJECT immediately, skipping protected IDs. */
	private static function trim_object_excess( string $object_key, array $protect_ids = array() ): int {
		$posts = get_posts( array(
			'post_type'      => Align_Agent_Global_Vault::CPT_BACKUP,
			'post_status'    => 'private',
			'title'          => $object_key,
			'posts_per_page' => 100,
			'orderby'        => 'ID',
			'order'          => 'DESC',
		) );

		$deleted = 0;
		if ( count( $posts ) > self::MAX_PER_OBJECT ) {
			foreach ( array_slice( $posts, self::MAX_PER_OBJECT ) as $p ) {
				if ( in_array( (int) $p->ID, $protect_ids, true ) ) {
					continue;
				}
				Align_Agent_Global_Vault::delete_backup_post( (int) $p->ID );
				$deleted++;
			}
		}
		return $deleted;
	}

	/** Daily WP-Cron callback. */
	public static function run_cron(): void {
		$deleted = self::enforce();
		error_log( sprintf( '[Align Agent] Vault daily GC completed | deleted: %d', $deleted ) );
	}

	/** Purge all backups in the Global Vault. */
	public static function purge_all(): int {
		$posts = get_posts( array(
			'post_type'      => Align_Agent_Global_Vault::CPT_BACKUP,
			'post_status'    => array( 'private', 'foreign', 'publish', 'draft', 'trash' ),
			'posts_per_page' => 1000,
			'fields'         => 'ids',
		) );

		$deleted = 0;
		foreach ( $posts as $bid ) {
			Align_Agent_Global_Vault::delete_backup_post( (int) $bid );
			$deleted++;
		}

		$orphan_chunks = get_posts( array(
			'post_type'      => Align_Agent_Global_Vault::CPT_CHUNK,
			'post_status'    => array( 'private', 'foreign', 'publish', 'draft', 'trash' ),
			'posts_per_page' => 1000,
			'fields'         => 'ids',
		) );
		foreach ( $orphan_chunks as $cid ) {
			wp_delete_post( (int) $cid, true );
		}

		error_log( sprintf( '[Align Agent] Vault purged all backups | deleted: %d | user: %d', $deleted, get_current_user_id() ) );
		return $deleted;
	}

	/** AJAX handler for Purge Backups button in Updates tab (REQ-13). */
	public static function ajax_purge(): void {
		check_ajax_referer( 'align_agent_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'align-agent' ) ), 403 );
		}

		$deleted = self::purge_all();
		wp_send_json_success( array(
			'deleted' => $deleted,
			'message' => sprintf( __( 'Purged %d backup(s) from Global Vault.', 'align-agent' ), $deleted ),
		) );
	}
}
