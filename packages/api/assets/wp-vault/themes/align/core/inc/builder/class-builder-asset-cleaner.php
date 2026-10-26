<?php
/**
 * Align Theme Core Engine - Builder Asset Cleaner & Garbage Collector
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Builder_Asset_Cleaner {

	/**
	 * WordPress Hook Handler: Triggered before a post is deleted.
	 *
	 * @param int          $post_id Post ID being deleted.
	 * @param WP_Post|null $post    Post object if available.
	 */
	public static function handle_before_delete_post( $post_id, $post = null ) {
		$post_id = intval( $post_id );
		if ( ! $post_id || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$post_obj = ( $post instanceof WP_Post ) ? $post : get_post( $post_id );
		if ( ! $post_obj ) {
			return;
		}

		self::delete_post_assets( $post_obj );
	}

	/**
	 * Delete all associated CSS/JS external assets and template files for a given post or template entity.
	 *
	 * @param int|WP_Post $post Post ID or WP_Post object.
	 * @return bool True if assets processed, false otherwise.
	 */
	public static function delete_post_assets( $post ): bool {
		$post_obj = ( $post instanceof WP_Post ) ? $post : get_post( intval( $post ) );
		if ( ! ( $post_obj instanceof WP_Post ) ) {
			return false;
		}

		$post_id   = $post_obj->ID;
		$post_type = $post_obj->post_type;
		$slug      = sanitize_title( $post_obj->post_name );

		if ( 'wp_template' === $post_type || 'wp_template_part' === $post_type ) {
			$type = ( 'wp_template' === $post_type ) ? 'template' : 'part';
			if ( ! empty( $slug ) ) {
				self::delete_template_part_assets( $slug, $type );
			}
		}

		$prefix = 'page';
		if ( 'wp_block' === $post_type ) {
			$prefix = 'block';
		} elseif ( 'wp_template' === $post_type ) {
			$prefix = 'template';
		} elseif ( 'wp_template_part' === $post_type ) {
			$prefix = 'part';
		}

		$candidate_ids = array();
		if ( ! empty( $slug ) ) {
			$candidate_ids[] = 'style-' . $prefix . '-' . $slug;
			$candidate_ids[] = 'script-' . $prefix . '-' . $slug;
			$candidate_ids[] = 'align-style-' . $prefix . '-' . $slug;
			$candidate_ids[] = 'align-script-' . $prefix . '-' . $slug;
		}

		$candidate_ids[] = 'style-' . $prefix . '-' . $post_id;
		$candidate_ids[] = 'script-' . $prefix . '-' . $post_id;
		$candidate_ids[] = 'align-style-' . $prefix . '-' . $post_id;
		$candidate_ids[] = 'align-script-' . $prefix . '-' . $post_id;

		if ( 'page' !== $prefix ) {
			$candidate_ids[] = 'style-page-' . $post_id;
			$candidate_ids[] = 'script-page-' . $post_id;
			$candidate_ids[] = 'align-style-page-' . $post_id;
			$candidate_ids[] = 'align-script-page-' . $post_id;
		}

		$dir = class_exists( 'Align_Builder_Assets' )
			? Align_Builder_Assets::get_builder_assets_dir()
			: trailingslashit( get_stylesheet_directory() ) . 'assets/builder/';

		$deleted_count = 0;
		foreach ( array_unique( array_filter( $candidate_ids ) ) as $file_id ) {
			$ext       = ( 0 === strpos( $file_id, 'style-' ) || 0 === strpos( $file_id, 'align-style-' ) ) ? '.css' : '.js';
			$file_path = $dir . sanitize_file_name( $file_id ) . $ext;

			if ( file_exists( $file_path ) ) {
				if ( class_exists( 'Align_Builder_Asset_History' ) ) {
					Align_Builder_Asset_History::snapshot( $file_path, 'delete' );
				}
				unlink( $file_path );
				clearstatcache( true, $file_path );
				$deleted_count++;
				error_log( sprintf( '[Align AssetCleaner] Deleted asset for Post #%d (%s): %s', $post_id, $post_type, $file_path ) );
				error_log( sprintf( '[Align CrudHandler] Cleaned asset file: %s', $file_path ) );
			}
		}

		return true;
	}

	/**
	 * Delete template or template part external assets and disk template file.
	 *
	 * @param string $slug Template or part slug.
	 * @param string $type 'template' or 'part'.
	 * @return bool True on execution.
	 */
	public static function delete_template_part_assets( string $slug, string $type = 'template' ): bool {
		$safe_slug = sanitize_file_name( $slug );
		if ( empty( $safe_slug ) ) {
			return false;
		}

		$prefix = ( 'template' === $type ) ? 'template' : 'part';
		$dir    = class_exists( 'Align_Builder_Assets' )
			? Align_Builder_Assets::get_builder_assets_dir()
			: trailingslashit( get_stylesheet_directory() ) . 'assets/builder/';

		$candidate_files = array(
			$dir . 'style-' . $prefix . '-' . $safe_slug . '.css',
			$dir . 'script-' . $prefix . '-' . $safe_slug . '.js',
			$dir . 'align-style-' . $prefix . '-' . $safe_slug . '.css',
			$dir . 'align-script-' . $prefix . '-' . $safe_slug . '.js',
		);

		foreach ( $candidate_files as $file_path ) {
			if ( file_exists( $file_path ) ) {
				if ( class_exists( 'Align_Builder_Asset_History' ) ) {
					Align_Builder_Asset_History::snapshot( $file_path, 'delete' );
				}
				unlink( $file_path );
				clearstatcache( true, $file_path );
				error_log( sprintf( '[Align AssetCleaner] Deleted %s external asset: %s', $type, $file_path ) );
				error_log( sprintf( '[Align CrudHandler] Cleaned template asset: %s', $file_path ) );
			}
		}

		$sub_dir     = ( 'template' === $type ) ? 'templates' : 'parts';
		$target_file = trailingslashit( get_stylesheet_directory() ) . $sub_dir . '/' . $safe_slug . '.html';
		if ( file_exists( $target_file ) ) {
			unlink( $target_file );
			clearstatcache( true, $target_file );
			error_log( sprintf( '[Align CrudHandler] Deleted %s disk file: %s', $type, $target_file ) );
		}

		return true;
	}

	/**
	 * Clean up legacy asset files after writing a new asset file.
	 *
	 * @param string       $type         'css' or 'js'.
	 * @param string       $current_file Absolute path to active asset file.
	 * @param array|string $legacy_ids   Array of legacy identifier names.
	 * @return int Number of files cleaned.
	 */
	public static function clean_legacy_files( string $type, string $current_file, $legacy_ids = array() ): int {
		$legacies = is_array( $legacy_ids ) ? $legacy_ids : array_filter( array( (string) $legacy_ids ) );
		if ( empty( $legacies ) ) {
			return 0;
		}

		$dir           = class_exists( 'Align_Builder_Assets' )
			? Align_Builder_Assets::get_builder_assets_dir()
			: trailingslashit( get_stylesheet_directory() ) . 'assets/builder/';
		$ext           = '.' . $type;
		$cleaned_count = 0;

		foreach ( array_unique( array_filter( $legacies ) ) as $legacy_id ) {
			$legacy_file = $dir . sanitize_file_name( (string) $legacy_id ) . $ext;
			if ( $legacy_file !== $current_file && file_exists( $legacy_file ) ) {
				unlink( $legacy_file );
				clearstatcache( true, $legacy_file );
				$cleaned_count++;
				error_log( sprintf( '[Align AssetCleaner] Legacy %s removed: %s', strtoupper( $type ), $legacy_file ) );
				error_log( sprintf( '[Align CrudHandler] Legacy %s removed: %s', strtoupper( $type ), $legacy_file ) );
			}
		}

		return $cleaned_count;
	}

	/**
	 * Purge or migrate legacy builder asset files with align- prefix in assets/builder/.
	 *
	 * @return array Array of processed file stats ('renamed', 'deleted').
	 */
	public static function purge_orphaned_assets(): array {
		$dir = class_exists( 'Align_Builder_Assets' )
			? Align_Builder_Assets::get_builder_assets_dir()
			: trailingslashit( get_stylesheet_directory() ) . 'assets/builder/';

		if ( ! is_dir( $dir ) ) {
			return array( 'renamed' => 0, 'deleted' => 0 );
		}

		$renamed_count = 0;
		$deleted_count = 0;
		$files         = scandir( $dir );

		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				if ( '.' === $file || '..' === $file ) {
					continue;
				}
				if ( preg_match( '/^align-(style-[^.]+\.css|script-[^.]+\.js)$/', $file, $m ) ) {
					$old_file = $dir . $file;
					$new_file = $dir . $m[1];
					if ( file_exists( $new_file ) ) {
						unlink( $old_file );
						clearstatcache( true, $old_file );
						$deleted_count++;
						error_log( sprintf( '[Align AssetCleaner] Deleted legacy file: %s (Modern file %s exists)', $file, $m[1] ) );
					} else {
						rename( $old_file, $new_file );
						clearstatcache( true, $old_file );
						clearstatcache( true, $new_file );
						$renamed_count++;
						error_log( sprintf( '[Align AssetCleaner] Renamed legacy file: %s -> %s', $file, $m[1] ) );
					}
				}
			}
		}

		return array( 'renamed' => $renamed_count, 'deleted' => $deleted_count );
	}
}
