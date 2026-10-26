<?php
/**
 * Handles AJAX endpoints for inline post order management.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Post_Order_Ajax {

	/**
	 * Save paginated archive order via AJAX.
	 */
	public static function save_archive_order() {
		if ( ! check_ajax_referer( 'align_post_order_archive_nonce', 'ALIGN_archive_sort_nonce', false ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		if ( ! current_user_can( Align_Post_Order::get_required_capability() ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		global $wpdb;

		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : 'post';
		$paged     = isset( $_POST['paged'] ) ? intval( $_POST['paged'] ) : 1;
		$per_page  = isset( $_POST['per_page'] ) ? intval( $_POST['per_page'] ) : 20;

		$raw_order = isset( $_POST['order'] ) ? sanitize_text_field( wp_unslash( $_POST['order'] ) ) : '';
		parse_str( $raw_order, $parsed_data );

		$new_order = isset( $parsed_data['post'] ) ? array_map( 'intval', (array) $parsed_data['post'] ) : [];

		if ( empty( $new_order ) ) {
			wp_send_json_error( 'Empty order data' );
		}

		$statuses = [ 'publish', 'pending', 'draft', 'private', 'future', 'inherit' ];

		// Fetch all posts of this type.
		$all_posts = get_posts( [
			'post_type'      => $post_type,
			'post_status'    => $statuses,
			'posts_per_page' => -1,
			'orderby'        => [
				'menu_order' => 'ASC',
				'post_date'  => 'DESC',
			],
			'fields'         => 'ids',
		] );

		// Calculate slice offset and replace that page slice with new drag order without duplicates.
		$offset    = max( 0, $paged * $per_page - $per_page );
		$remaining = array_values( array_diff( $all_posts, $new_order ) );
		array_splice( $remaining, $offset, 0, $new_order );
		$all_posts = $remaining;

		// Update menu_order in DB for every post (1-indexed so menu_order > 0 is always true).
		foreach ( $all_posts as $position => $id ) {
			$wpdb->update(
				$wpdb->posts,
				[ 'menu_order' => $position + 1 ],
				[ 'ID'         => intval( $id ) ]
			);
			clean_post_cache( $id );
		}

		do_action( 'align_post_order/order_update_complete' );
		self::clear_cache();

		wp_send_json_success( 'Order updated successfully' );
	}

	/**
	 * Flush caching layers.
	 */
	public static function clear_cache() {
		wp_cache_flush();

		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}

		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		if ( class_exists( 'LiteSpeed\Purge' ) ) {
			\LiteSpeed\Purge::purge_all();
		}

		if ( function_exists( 'opcache_reset' ) ) {
			@opcache_reset();
		}

		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
	}
}
