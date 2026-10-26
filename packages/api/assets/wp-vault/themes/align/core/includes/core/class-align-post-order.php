<?php
/**
 * Align Post Order - Core Class
 *
 * Enables inline drag & drop post reordering directly on post list tables
 * (`edit.php`, `upload.php`) for all post types in WordPress admin, and enables
 * the native Order input field in Quick Edit.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Post_Order {

	/**
	 * Boot the feature.
	 */
	public static function init(): void {
		$instance = new self();

		// Front-end hooks (always registered).
		add_filter( 'pre_get_posts', [ $instance, 'pre_get_posts' ] );
		add_filter( 'posts_orderby', [ $instance, 'posts_orderby' ], 99, 2 );
		add_action( 'init', [ $instance, 'on_init' ] );

		// Admin-only hooks.
		if ( ! is_admin() ) {
			return;
		}

		$cap = self::get_required_capability();
		if ( ! current_user_can( $cap ) ) {
			return;
		}

		// Enable page-attributes (Order field) for all UI post types so Quick Edit shows "Order".
		add_action( 'admin_init', [ $instance, 'enable_quick_edit_order_field' ] );

		// Enqueue scripts & styles for list tables.
		add_action( 'admin_enqueue_scripts', [ $instance, 'enqueue_assets' ], 10 );

		// AJAX action.
		add_action( 'wp_ajax_align_update_post_order_archive', [ $instance, 'ajax_update_post_order_archive' ] );
	}

	/**
	 * Ensure all UI post types support 'page-attributes' so WordPress core
	 * automatically renders the native Order (Thứ tự) input field in Quick Edit.
	 */
	public function enable_quick_edit_order_field(): void {
		$post_types = get_post_types( [ 'show_ui' => true ] );

		foreach ( $post_types as $pt ) {
			if ( ! post_type_supports( $pt, 'page-attributes' ) ) {
				add_post_type_support( $pt, 'page-attributes' );
			}
		}
	}

	/**
	 * Options helper.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_options(): array {
		$saved = get_option( 'align_post_order_options', [] );

		$defaults = [
			'autosort'              => 1,
			'adminsort'             => 1,
			'navigation_sort_apply' => 1,
			'capability'            => 'edit_posts',
		];

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Required capability helper.
	 *
	 * @param string $post_type Post type slug.
	 * @return string Capability.
	 */
	public static function get_required_capability( string $post_type = '' ): string {
		$options = self::get_options();
		$cap     = ! empty( $options['capability'] ) ? $options['capability'] : 'edit_posts';

		return (string) apply_filters( 'align_post_order/edit_capability', $cap, $post_type );
	}

	// -------------------------------------------------------------------------
	// Front-End Navigation & Queries
	// -------------------------------------------------------------------------

	public function on_init(): void {
		if ( is_admin() ) {
			return;
		}

		$options = self::get_options();
		$enabled = ( strval( $options['navigation_sort_apply'] ) === '1' );
		$enabled = (bool) apply_filters( 'align_post_order/navigation_sort_apply', $enabled );

		if ( ! $enabled ) {
			return;
		}

		add_filter( 'get_previous_post_where', [ $this, 'nav_previous_where' ], 99, 3 );
		add_filter( 'get_previous_post_sort', [ $this, 'nav_previous_sort' ] );
		add_filter( 'get_next_post_where', [ $this, 'nav_next_where' ], 99, 3 );
		add_filter( 'get_next_post_sort', [ $this, 'nav_next_sort' ] );
	}

	public function nav_previous_where( string $where, bool $same_term, $excluded ): string {
		global $post, $wpdb;
		if ( empty( $post ) ) {
			return $where;
		}

		return $wpdb->prepare(
			"AND p.menu_order >= %d AND p.ID <> %d AND p.post_status = 'publish' AND p.post_type = %s",
			$post->menu_order,
			$post->ID,
			$post->post_type
		);
	}

	public function nav_previous_sort( string $sort ): string {
		global $wpdb;
		return "ORDER BY {$wpdb->posts}.menu_order ASC, {$wpdb->posts}.post_date ASC LIMIT 1";
	}

	public function nav_next_where( string $where, bool $same_term, $excluded ): string {
		global $post, $wpdb;
		if ( empty( $post ) ) {
			return $where;
		}

		return $wpdb->prepare(
			"AND p.menu_order <= %d AND p.ID <> %d AND p.post_status = 'publish' AND p.post_type = %s",
			$post->menu_order,
			$post->ID,
			$post->post_type
		);
	}

	public function nav_next_sort( string $sort ): string {
		global $wpdb;
		return "ORDER BY {$wpdb->posts}.menu_order DESC, {$wpdb->posts}.post_date DESC LIMIT 1";
	}

	public function pre_get_posts( \WP_Query $query ): \WP_Query {
		if ( is_admin() ) {
			return $query;
		}

		if ( ! empty( $query->query_vars['ignore_custom_sort'] ) ) {
			return $query;
		}

		if ( isset( $query->query_vars['post_type'] ) && 'nav_menu_item' === $query->query_vars['post_type'] ) {
			return $query;
		}

		if ( $query->is_search() && ! empty( $query->query['s'] ) ) {
			return $query;
		}

		$options = self::get_options();

		if ( strval( $options['autosort'] ) === '1' ) {
			if ( isset( $query->query['suppress_filters'] ) ) {
				$query->query['suppress_filters'] = false;
			}
			if ( isset( $query->query_vars['suppress_filters'] ) ) {
				$query->query_vars['suppress_filters'] = false;
			}
		}

		return $query;
	}

	public function posts_orderby( string $order_by, \WP_Query $query ): string {
		global $wpdb;

		$options = self::get_options();

		if ( ! empty( $query->query_vars['ignore_custom_sort'] ) ) {
			return $order_by;
		}

		if ( $query->is_search() && ! empty( $query->query['s'] ) ) {
			return $order_by;
		}

		if ( preg_match( '/FIELD\s*\(/i', $order_by ) ) {
			return $order_by;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['orderby'] ) && 'menu_order' !== $_GET['orderby'] ) {
			return $order_by;
		}

		$ignore = (bool) apply_filters( 'align_post_order/posts_orderby/ignore', false, $order_by, $query );
		if ( $ignore ) {
			return $order_by;
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
			if ( strval( $options['adminsort'] ) === '1' ) {
				$order    = (string) apply_filters( 'align_post_order/posts_order', '', $query );
				$order_by = "{$wpdb->posts}.menu_order {$order}, {$wpdb->posts}.post_date DESC";
			}
			return $order_by;
		}

		if ( strval( $options['autosort'] ) === '1' ) {
			$order = (string) apply_filters( 'align_post_order/posts_order', '', $query );

			if ( '' === trim( $order_by ) ) {
				$order_by = "{$wpdb->posts}.menu_order {$order}";
			} else {
				$order_by = "{$wpdb->posts}.menu_order {$order}, {$order_by}";
			}
		}

		return $order_by;
	}

	// -------------------------------------------------------------------------
	// Assets Loading
	// -------------------------------------------------------------------------

	/**
	 * Enqueue drag & drop assets on list tables.
	 *
	 * @param string $hook Admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		$screen = get_current_screen();

		if ( ! $screen || empty( $screen->post_type ) ) {
			return;
		}

		// Only load on list table screens ('edit' or 'upload').
		if ( ! in_array( $screen->base, [ 'edit', 'upload' ], true ) ) {
			return;
		}

		if ( isset( $screen->taxonomy ) && ! empty( $screen->taxonomy ) ) {
			return;
		}

		// If user explicitly sorted by another column (e.g. title, date), skip drag-drop.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['orderby'] ) && 'menu_order' !== $_GET['orderby'] ) {
			return;
		}

		$base_uri = get_template_directory_uri();
		$version  = wp_get_theme( get_template() )->get( 'Version' );

		wp_enqueue_style(
			'align-post-order',
			$base_uri . '/core/assets/admin/css/post-order.css',
			[],
			$version
		);

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-ui-sortable' );

		wp_register_script(
			'align-post-order',
			$base_uri . '/core/assets/admin/js/post-order.js',
			[ 'jquery', 'jquery-ui-sortable' ],
			$version,
			[ 'in_footer' => true ]
		);

		wp_localize_script(
			'align-post-order',
			'AlignPostOrder',
			[
				'post_type'          => $screen->post_type,
				'archive_sort_nonce' => wp_create_nonce( 'align_post_order_archive_nonce' ),
				'ajax_url'           => admin_url( 'admin-ajax.php' ),
			]
		);

		wp_enqueue_script( 'align-post-order' );
	}

	public function ajax_update_post_order_archive(): void {
		$ajax_file = get_template_directory() . '/core/includes/core/class-align-post-order-ajax.php';
		if ( file_exists( $ajax_file ) ) {
			require_once $ajax_file;
		}

		if ( class_exists( 'Align_Post_Order_Ajax' ) ) {
			Align_Post_Order_Ajax::save_archive_order();
		}

		wp_die();
	}
}
