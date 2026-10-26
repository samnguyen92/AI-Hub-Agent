<?php
/**
 * Align Theme Functions & Main Entrypoint
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 1. Define Theme Constants
if ( ! defined( 'ALIGN_DIR_PATH' ) ) {
	define( 'ALIGN_DIR_PATH', get_template_directory() . '/' );
}
if ( ! defined( 'ALIGN_DIR_URL' ) ) {
	define( 'ALIGN_DIR_URL', get_template_directory_uri() . '/' );
}
if ( ! defined( 'ALIGN_CORE_PATH' ) ) {
	define( 'ALIGN_CORE_PATH', ALIGN_DIR_PATH . 'core/' );
}
if ( ! defined( 'ALIGN_CORE_URL' ) ) {
	define( 'ALIGN_CORE_URL', ALIGN_DIR_URL . 'core/' );
}

// 2. Load Align Theme Core Engine Bootstrap
$core_init = ALIGN_CORE_PATH . 'init.php';
if ( file_exists( $core_init ) ) {
	require_once $core_init;
}

// 3. Theme Setup & Feature Registrations
if ( ! function_exists( 'align_setup' ) ) {
	function align_setup() {
		load_theme_textdomain( 'align', get_template_directory() . '/languages' );
		remove_theme_support( 'core-block-patterns' );
	}
}
add_action( 'after_setup_theme', 'align_setup' );

// 4. Block Styles Registration
function align_register_block_styles() {
	$block_styles = array(
		'core/columns'      => array( 'columns-reverse' => __( 'Reverse', 'align' ) ),
		'core/group'        => array( 'shadow-light' => __( 'Shadow', 'align' ), 'shadow-solid' => __( 'Solid', 'align' ) ),
		'core/list'         => array( 'no-disc' => __( 'No Disc', 'align' ) ),
		'core/quote'        => array( 'shadow-light' => __( 'Shadow', 'align' ), 'shadow-solid' => __( 'Solid', 'align' ) ),
		'core/social-links' => array( 'outline' => __( 'Outline', 'align' ) ),
	);

	foreach ( $block_styles as $block => $styles ) {
		foreach ( $styles as $style_name => $style_label ) {
			register_block_style(
				$block,
				array(
					'name'  => $style_name,
					'label' => $style_label,
				)
			);
		}
	}
}
add_action( 'init', 'align_register_block_styles' );

// 5. Block Pattern Categories Registration
function align_register_block_pattern_categories() {
	if ( function_exists( 'register_block_pattern_category' ) ) {
		register_block_pattern_category( 'align-page', array( 'label' => __( 'Page', 'align' ) ) );
		register_block_pattern_category( 'align-pricing', array( 'label' => __( 'Pricing', 'align' ) ) );
		register_block_pattern_category( 'align-custom', array( 'label' => __( 'My Custom Patterns', 'align' ) ) );
	}
}
add_action( 'init', 'align_register_block_pattern_categories' );

// 6. WP Head Cleanup
function align_cleanup_head() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head', 10, 0 );
	remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 10 );
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
	remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_classic_theme_styles' );

	// Disable WP 6.8+ Speculative Loading / Speculation Rules
	remove_action( 'wp_footer', 'wp_print_speculation_rules' );
	add_filter( 'wp_speculation_rules_configuration', '__return_null' );
}
add_action( 'init', 'align_cleanup_head' );

/**
 * Allow numeric slug '404' for pages without WordPress auto-renaming to '404-2'.
 */
add_filter( 'pre_wp_unique_post_slug', function( $override_slug, $slug, $post_id, $post_status, $post_type, $post_parent ) {
	if ( '404' === $slug && 'page' === $post_type ) {
		global $wpdb;
		$check_sql       = "SELECT post_name FROM $wpdb->posts WHERE post_name = %s AND post_type = %s AND ID != %d AND post_parent = %d LIMIT 1";
		$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $slug, $post_type, $post_id, $post_parent ) );
		if ( ! $post_name_check ) {
			return $slug;
		}
	}
	return $override_slug;
}, 10, 6 );
