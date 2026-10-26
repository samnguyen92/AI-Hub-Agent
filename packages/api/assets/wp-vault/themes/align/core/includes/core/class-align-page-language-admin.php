<?php
/**
 * Align Theme - Page Multi-Language Admin Management
 *
 * Adds Language column, translation flags, and language filter views
 * to the hierarchical Pages list (/wp-admin/edit.php?post_type=page).
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Page_Language_Admin {

	/**
	 * Initialize hooks for post_type = page.
	 */
	public static function init() {
		add_filter( 'manage_page_posts_columns',       array( 'Align_Post_Language_Meta', 'add_lang_column' ) );
		add_action( 'manage_page_posts_custom_column', array( __CLASS__, 'render_page_lang_column' ), 10, 2 );
		add_filter( 'views_edit-page',                 array( __CLASS__, 'add_page_lang_views' ) );
		add_action( 'pre_get_posts',                   array( __CLASS__, 'filter_pages_by_lang' ) );
	}

	/**
	 * Render language column for pages (delegate to Align_Post_Language_Meta).
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public static function render_page_lang_column( $column, $post_id ) {
		if ( 'align_langs' !== $column ) return;
		if ( class_exists( 'Align_Post_Language_Meta' ) ) {
			Align_Post_Language_Meta::render_lang_column( $column, $post_id );
		}
	}

	/**
	 * Add Language filter tabs to /wp-admin/edit.php?post_type=page.
	 * Excludes Root Lang Pages from counts.
	 *
	 * @param array $views Existing view links.
	 * @return array Modified view links.
	 */
	public static function add_page_lang_views( $views ) {
		if ( ! class_exists( 'Align_Language_Manager' ) ) return $views;

		$langs = Align_Language_Manager::get_languages();
		if ( count( $langs ) < 2 ) return $views;

		$current_filter = isset( $_GET['lang'] ) ? sanitize_key( $_GET['lang'] ) : '';
		$default_lang   = Align_Language_Manager::get_default_lang();

		foreach ( $langs as $l ) {
			$code = $l['code'];
			$name = $l['name'];
			$flag = $l['flag'] ?? '';

			// Count pages for this language excluding root lang pages
			$count_args = array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => '_align_lang_root',
						'compare' => 'NOT EXISTS',
					),
				),
			);

			if ( $code === $default_lang ) {
				$count_args['meta_query'][] = array(
					'relation' => 'OR',
					array( 'key' => '_align_lang', 'value' => $code ),
					array( 'key' => '_align_lang', 'compare' => 'NOT EXISTS' ),
				);
			} else {
				$count_args['meta_query'][] = array(
					'key'   => '_align_lang',
					'value' => $code,
				);
			}

			$count = count( get_posts( $count_args ) );
			$url   = add_query_arg( array( 'post_type' => 'page', 'lang' => $code ), admin_url( 'edit.php' ) );
			$class = ( $current_filter === $code ) ? 'current' : '';

			$views[ 'lang_' . $code ] = sprintf(
				'<a href="%s" class="%s">%s %s <span class="count">(%d)</span></a>',
				esc_url( $url ),
				esc_attr( $class ),
				$flag,
				esc_html( $name ),
				$count
			);
		}

		return $views;
	}

	/**
	 * Filter page queries by language in /wp-admin/edit.php?post_type=page.
	 *
	 * @param WP_Query $query Main query.
	 */
	public static function filter_pages_by_lang( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) return;
		if ( 'page' !== $query->get( 'post_type' ) ) return;

		$lang = isset( $_GET['lang'] ) ? sanitize_key( $_GET['lang'] ) : '';
		if ( empty( $lang ) ) return;

		$default_lang = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en';

		$meta_query = $query->get( 'meta_query' ) ?: array();
		if ( ! is_array( $meta_query ) ) $meta_query = array();

		// Exclude root lang page
		$meta_query[] = array(
			'key'     => '_align_lang_root',
			'compare' => 'NOT EXISTS',
		);

		if ( $lang === $default_lang ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array( 'key' => '_align_lang', 'value' => $lang ),
				array( 'key' => '_align_lang', 'compare' => 'NOT EXISTS' ),
			);
		} else {
			$meta_query[] = array(
				'key'   => '_align_lang',
				'value' => $lang,
			);
		}

		$query->set( 'meta_query', $meta_query );
	}
}
