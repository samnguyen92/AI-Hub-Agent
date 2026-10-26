<?php
/**
 * Align Theme Core Engine - Builder Template Duplicate Cleaner
 *
 * Scans and cleans duplicate template and template part records in WP database.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Builder_Template_Cleaner {

	/**
	 * Clean duplicate wp_template and wp_template_part posts in the WordPress database.
	 *
	 * @param string|null $target_type Specific post type ('wp_template' or 'wp_template_part') or null for both.
	 * @return array Summary of cleanup results.
	 */
	public static function clean_duplicate_templates( $target_type = null ) {
		$types = $target_type ? array( $target_type ) : array( 'wp_template', 'wp_template_part' );
		$stats = array(
			'cleaned' => 0,
			'deleted' => array(),
			'updated' => array(),
		);

		$theme_stylesheet = get_stylesheet();
		$theme_template   = get_template();

		foreach ( $types as $post_type ) {
			$posts = get_posts( array(
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'DESC', // Most recent first
			) );

			if ( empty( $posts ) ) {
				continue;
			}

			// Group posts by normalized base slug
			$grouped = array();
			foreach ( $posts as $p ) {
				$raw_name = $p->post_name;
				// Remove theme prefix if present (slash or dash separated)
				$normalized = preg_replace( '#^(?:' . preg_quote( $theme_stylesheet, '#' ) . '|' . preg_quote( $theme_template, '#' ) . ')[//\-_]#', '', $raw_name );
				$normalized = sanitize_title( $normalized );

				if ( ! isset( $grouped[ $normalized ] ) ) {
					$grouped[ $normalized ] = array();
				}
				$grouped[ $normalized ][] = $p;
			}

			foreach ( $grouped as $base_slug => $group_posts ) {
				// Keep the first (most recently updated/created) post as primary
				$primary_post = array_shift( $group_posts );

				// Standardize primary post slug and wp_theme taxonomy
				if ( $primary_post->post_name !== $base_slug ) {
					wp_update_post( array(
						'ID'        => $primary_post->ID,
						'post_name' => $base_slug,
					) );
					$stats['updated'][] = sprintf( '[%s] ID %d renamed to "%s"', $post_type, $primary_post->ID, $base_slug );
				}
				wp_set_object_terms( $primary_post->ID, $theme_stylesheet, 'wp_theme' );
				if ( 'wp_template_part' === $post_type ) {
					$area = in_array( $base_slug, array( 'header', 'footer', 'sidebar', 'comments' ), true ) ? $base_slug : 'uncategorized';
					wp_set_object_terms( $primary_post->ID, $area, 'wp_template_part_area' );
				}

				// Delete any duplicate remaining posts
				foreach ( $group_posts as $duplicate ) {
					wp_delete_post( $duplicate->ID, true );
					$stats['cleaned']++;
					$stats['deleted'][] = sprintf( '[%s] Deleted duplicate ID %d (slug: "%s")', $post_type, $duplicate->ID, $duplicate->post_name );
					error_log( sprintf( '[Align TemplateCleaner] Deleted duplicate %s ID %d with slug "%s" (kept ID %d for slug "%s")', $post_type, $duplicate->ID, $duplicate->post_name, $primary_post->ID, $base_slug ) );
				}
			}
		}

		if ( function_exists( 'wp_clean_theme_json_cache' ) ) {
			wp_clean_theme_json_cache();
		}

		error_log( sprintf( '[Align TemplateCleaner] Cleanup completed. Removed %d duplicate records.', $stats['cleaned'] ) );

		return $stats;
	}
}
