<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// 1. Automatically flush rewrite rules on plugin activation
function align_flush_rewrite_rules() {
    align_add_services_and_blogs_rewrite_rule();
    flush_rewrite_rules();
}

// 2. Add rewrite rules for URLs like /services/<slug> or /insights/<slug>
function align_add_services_and_blogs_rewrite_rule() {
    add_rewrite_rule(
        '^services/([^/]+)/?$',
        'index.php?name=$matches[1]',
        'top'
    );

    add_rewrite_rule(
        '^insights/([^/]+)/?$',
        'index.php?name=$matches[1]',
        'top'
    );

    // 401 Unauthorized Error Page
    add_rewrite_rule(
        '^401/?$',
        'index.php?pagename=401',
        'top'
    );

    add_rewrite_rule(
        '^zh/401/?$',
        'index.php?pagename=zh/401',
        'top'
    );
}
add_action('init', 'align_add_services_and_blogs_rewrite_rule');

/**
 * Send HTTP 401 Unauthorized status header when serving 401 error page.
 */
function align_handle_401_status_header() {
    if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return;
    }
    if ( is_page( '401' ) || ( ! empty( $_SERVER['REQUEST_URI'] ) && preg_match( '#^/(zh/)?401/?$#i', $_SERVER['REQUEST_URI'] ) ) ) {
        status_header( 401 );
        nocache_headers();
    }
}
add_action( 'template_redirect', 'align_handle_401_status_header' );

// 3. Change post permalink if it belongs to specific categories
function align_category_add_custom_url($post_link, $post) {
    $url_prefixes = [
        'services' => ['aethetic-treatment', 'medical-treatment'],
    ];

    if ($post->post_type === 'post') {
        $categories = get_the_category($post->ID);

        if (!empty($categories)) {
            foreach ($categories as $category) {
                foreach ($url_prefixes as $prefix => $target_slugs) {
                    if (
                        in_array($category->slug, $target_slugs) || 
                        (isset($category->parent) && $category->parent != 0 && in_array(get_category($category->parent)->slug, $target_slugs))
                    ) {
                        return home_url('/' . $prefix . '/' . $post->post_name . '/');
                    }
                }
            }
        }
    }

    return $post_link;
}
add_filter('post_link', 'align_category_add_custom_url', 10, 2);

function align_change_permalink_for_specific_categories($post_link, $post) {
    $url_prefixes = [
        'insights' => ['blog']
    ];

    if ($post->post_type === 'post') {
        $categories = get_the_category($post->ID);

        if (!empty($categories)) {
            foreach ($categories as $category) {
                foreach ($url_prefixes as $prefix => $target_slugs) {
                    if (in_array($category->slug, $target_slugs)) {
                        return home_url('/' . $prefix . '/' . $post->post_name . '/');
                    }
                }
            }
        }
    }

    return $post_link;
}
add_filter('post_link', 'align_change_permalink_for_specific_categories', 10, 2);

// Register categories to pages
function align_add_categories_tags_to_pages() {
    register_taxonomy_for_object_type('category', 'page');
    register_taxonomy_for_object_type('post_tag', 'page');
}
add_action('init', 'align_add_categories_tags_to_pages');
