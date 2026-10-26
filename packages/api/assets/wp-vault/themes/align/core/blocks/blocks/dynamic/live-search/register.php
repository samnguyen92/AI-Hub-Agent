<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get all searchable post types across WordPress and e-commerce plugins (WooCommerce, FluentCart, EDD, etc.)
 *
 * @return array List of post types with slug and name.
 */
function align_get_all_searchable_post_types() {
    $all_pts = get_post_types(array(), 'objects');
    $editor_post_types = array();
    $exclude_pts = array(
        'attachment', 'revision', 'nav_menu_item', 'custom_css', 
        'customize_changeset', 'oembed_cache', 'user_request', 
        'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_global_styles'
    );
    
    foreach ($all_pts as $pt) {
        if (in_array($pt->name, $exclude_pts, true)) {
            continue;
        }
        if (!empty($pt->public) || !empty($pt->show_ui) || !empty($pt->show_in_rest) || !empty($pt->publicly_queryable)) {
            $editor_post_types[] = array(
                'slug' => $pt->name,
                'name' => $pt->label ? $pt->label : $pt->name,
            );
        }
    }
    return $editor_post_types;
}

/**
 * Get all searchable taxonomies and terms across WordPress and e-commerce plugins (WooCommerce, FluentCart, EDD, SureCart, etc.)
 *
 * @return array List of taxonomies with slug, name, post_types and terms.
 */
function align_get_all_searchable_taxonomies() {
    $all_tax_objects = get_taxonomies(array(), 'objects');
    $editor_taxonomies = array();
    $exclude_tax = array(
        'post_format', 'nav_menu', 'link_category', 'wp_theme', 
        'wp_template_part_area', 'action-group', 'wp_pattern_category'
    );

    foreach ($all_tax_objects as $tax) {
        if (in_array($tax->name, $exclude_tax, true)) {
            continue;
        }
        // Include any taxonomy that is public, or has show_ui, or show_in_rest, or show_tagcloud (e.g. FluentCart product-categories, WooCommerce product_cat, EDD download_category, etc.)
        if (!empty($tax->public) || !empty($tax->show_ui) || !empty($tax->show_in_rest) || !empty($tax->show_tagcloud) || !empty($tax->publicly_queryable)) {
            $terms = get_terms(array(
                'taxonomy'   => $tax->name,
                'hide_empty' => false,
                'number'     => 300,
            ));
            $term_list = array();
            if (!is_wp_error($terms) && !empty($terms)) {
                foreach ($terms as $t) {
                    $term_list[] = array(
                        'id'    => $t->term_id,
                        'name'  => $t->name,
                        'slug'  => $t->slug,
                        'count' => $t->count,
                    );
                }
            }
            $editor_taxonomies[] = array(
                'slug'       => $tax->name,
                'name'       => $tax->label ? $tax->label : $tax->name,
                'post_types' => is_array($tax->object_type) ? array_values($tax->object_type) : array(),
                'terms'      => $term_list,
            );
        }
    }
    return $editor_taxonomies;
}

function align_register_live_search_block() {
    $metadata = json_decode(file_get_contents(__DIR__ . '/block.json'), true);

    $script_handle = 'align-live-search-editor';
    wp_register_script(
        $script_handle,
        ALIGN_DIR_URL . 'core/blocks/blocks/dynamic/live-search/editor.js',
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-data', 'wp-components', 'wp-i18n'),
        filemtime(ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/live-search/editor.js'),
        true
    );

    $editor_post_types = align_get_all_searchable_post_types();
    wp_localize_script($script_handle, 'alignBlockPostTypes', $editor_post_types);

    $editor_taxonomies = align_get_all_searchable_taxonomies();
    wp_localize_script($script_handle, 'alignBlockTaxonomies', $editor_taxonomies);

    $frontend_script_handle = 'align-live-search-frontend';
    wp_register_script(
        $frontend_script_handle,
        ALIGN_DIR_URL . 'core/blocks/blocks/dynamic/live-search/script.js',
        array(),
        filemtime(ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/live-search/script.js'),
        true
    );

    // Pass AJAX url, REST url and nonce to frontend script
    wp_localize_script($frontend_script_handle, 'alignLiveSearchObj', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'rest_url' => get_rest_url(null, '/align/v1/live-search'),
        'nonce'    => wp_create_nonce('align_live_search_nonce'),
    ));

    wp_register_style(
        'align-live-search-style',
        ALIGN_DIR_URL . 'core/blocks/blocks/dynamic/live-search/style.css',
        array(),
        filemtime(ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/live-search/style.css')
    );

    require_once ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/live-search/render.php';

    register_block_type('align/live-search', array(
        'api_version'     => $metadata['apiVersion'],
        'title'           => $metadata['title'],
        'category'        => $metadata['category'],
        'icon'            => isset($metadata['icon']) ? $metadata['icon'] : 'search',
        'attributes'      => $metadata['attributes'],
        'supports'        => $metadata['supports'],
        'editor_script'   => $script_handle,
        'script'          => $frontend_script_handle,
        'style'           => 'align-live-search-style',
        'render_callback' => 'align_live_search_render_callback',
    ));
}
add_action('init', 'align_register_live_search_block', 99);

/**
 * AJAX handler — returns rendered HTML for search results.
 * Frontend JS fetches this HTML and injects it directly into the dropdown.
 */
function align_live_search_ajax_handler() {
    $keyword    = isset($_POST['keyword'])    ? sanitize_text_field(wp_unslash($_POST['keyword'])) : '';
    $attributes = isset($_POST['attributes']) ? json_decode(stripslashes($_POST['attributes']), true) : array();

    if (!is_array($attributes)) {
        $attributes = array();
    }

    if (empty($keyword)) {
        wp_send_json_error('Empty keyword');
        wp_die();
    }

    $has_selected_pt = array_key_exists('selectedPostTypes', $attributes);
    $selected_post_types = isset($attributes['selectedPostTypes']) ? $attributes['selectedPostTypes'] : array();

    if (!$has_selected_pt) {
        $legacy_pt = isset($attributes['postType']) ? $attributes['postType'] : 'post';
        if ($legacy_pt === 'custom') {
            $custom_pt = isset($attributes['customPostType']) ? $attributes['customPostType'] : 'post';
            $selected_post_types = array_filter(array_map('trim', explode(',', $custom_pt)));
        } elseif ($legacy_pt === 'any') {
            $selected_post_types = array('all');
        } else {
            $selected_post_types = array($legacy_pt);
        }
    }

    $searchable_pts = align_get_all_searchable_post_types();
    $all_pt_slugs   = array_map(function($p) { return $p['slug']; }, $searchable_pts);

    if (in_array('all', $selected_post_types)) {
        $selected_post_types = !empty($all_pt_slugs) ? $all_pt_slugs : array('post', 'page');
    }

    $post_type = $selected_post_types;

    if (empty($post_type)) {
        wp_send_json_success('<div class="align-ls-no-results">No results found.</div>');
        wp_die();
    }

    $posts_per_page  = isset($attributes['postsPerPage'])  ? intval($attributes['postsPerPage'])  : 5;
    $filter_taxonomy = isset($attributes['filterTaxonomy']) ? sanitize_key($attributes['filterTaxonomy']) : '';
    $filter_term_ids = isset($attributes['filterTermIds'])  ? sanitize_text_field($attributes['filterTermIds']) : '';

    $args = array(
        'post_type'              => $post_type,
        'post_status'            => 'publish',
        'posts_per_page'         => $posts_per_page,
        's'                      => $keyword,
        'orderby'                => 'relevance',
        // Performance: skip COUNT(*) — live search never needs total pages
        'no_found_rows'          => true,
        // Performance: skip preloading postmeta & term caches into PHP RAM
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    );

    $selected_cat_id = isset($_POST['selectedCategoryId']) ? intval($_POST['selectedCategoryId']) : 0;

    // Apply taxonomy filter if provided
    $tax_query = array();
    if (!empty($filter_taxonomy) && !empty($filter_term_ids)) {
        $term_ids = array_filter(array_map('intval', explode(',', $filter_term_ids)));
        if (!empty($term_ids)) {
            $tax_query[] = array(
                'taxonomy' => $filter_taxonomy,
                'field'    => 'term_id',
                'terms'    => $term_ids,
            );
        }
    }

    if (!empty($selected_cat_id)) {
        $cat_tax = !empty($attributes['categoryFilterTaxonomy']) ? sanitize_key($attributes['categoryFilterTaxonomy']) : 'category';
        $tax_query[] = array(
            'taxonomy' => $cat_tax,
            'field'    => 'term_id',
            'terms'    => array($selected_cat_id),
        );
    }

    if (!empty($tax_query)) {
        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'AND';
        }
        $args['tax_query'] = $tax_query;
    }

    // Generate cache key based on query arguments
    $cache_key = 'align_ls_' . md5(wp_json_encode($args));
    $html = get_transient($cache_key);

    if (false === $html) {
        $query = new WP_Query($args);

        // Load result item template
        require_once ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/live-search/render-results.php';

        ob_start();
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                align_live_search_render_result_item(get_the_ID(), $attributes);
            }
            wp_reset_postdata();
        } else {
            echo '<div class="align-ls-no-results">No results found.</div>';
        }
        $html = ob_get_clean();

        // Cache search results for 15 minutes
        set_transient($cache_key, $html, 15 * MINUTE_IN_SECONDS);
    }

    wp_send_json_success($html);
    wp_die();
}
add_action('wp_ajax_align_live_search',        'align_live_search_ajax_handler');
add_action('wp_ajax_nopriv_align_live_search', 'align_live_search_ajax_handler');

/**
 * REST API handler — returns rendered HTML for search results.
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function align_live_search_rest_handler(WP_REST_Request $request) {
    $params = $request->get_json_params();
    if (empty($params)) {
        $params = $request->get_body_params();
    }

    $keyword    = isset($params['keyword'])    ? sanitize_text_field($params['keyword']) : '';
    $attributes = isset($params['attributes']) ? $params['attributes'] : array();

    if (!is_array($attributes)) {
        $attributes = array();
    }

    if (empty($keyword)) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Empty keyword'), 400);
    }

    $has_selected_pt = array_key_exists('selectedPostTypes', $attributes);
    $selected_post_types = isset($attributes['selectedPostTypes']) ? $attributes['selectedPostTypes'] : array();

    if (!$has_selected_pt) {
        $legacy_pt = isset($attributes['postType']) ? $attributes['postType'] : 'post';
        if ($legacy_pt === 'custom') {
            $custom_pt = isset($attributes['customPostType']) ? $attributes['customPostType'] : 'post';
            $selected_post_types = array_filter(array_map('trim', explode(',', $custom_pt)));
        } elseif ($legacy_pt === 'any') {
            $selected_post_types = array('all');
        } else {
            $selected_post_types = array($legacy_pt);
        }
    }

    $searchable_pts = align_get_all_searchable_post_types();
    $all_pt_slugs   = array_map(function($p) { return $p['slug']; }, $searchable_pts);

    if (in_array('all', $selected_post_types)) {
        $selected_post_types = !empty($all_pt_slugs) ? $all_pt_slugs : array('post', 'page');
    }

    $post_type = $selected_post_types;

    if (empty($post_type)) {
        return new WP_REST_Response(array('success' => true, 'data' => '<div class="align-ls-no-results">No results found.</div>'), 200);
    }

    $posts_per_page  = isset($attributes['postsPerPage'])  ? intval($attributes['postsPerPage'])  : 5;
    $filter_taxonomy = isset($attributes['filterTaxonomy']) ? sanitize_key($attributes['filterTaxonomy']) : '';
    $filter_term_ids = isset($attributes['filterTermIds'])  ? sanitize_text_field($attributes['filterTermIds']) : '';

    $args = array(
        'post_type'              => $post_type,
        'post_status'            => 'publish',
        'posts_per_page'         => $posts_per_page,
        's'                      => $keyword,
        'orderby'                => 'relevance',
        // Performance: skip COUNT(*) — live search never needs total pages
        'no_found_rows'          => true,
        // Performance: skip preloading postmeta & term caches into PHP RAM
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    );

    $selected_cat_id = isset($params['selectedCategoryId']) ? intval($params['selectedCategoryId']) : 0;

    // Apply taxonomy filter if provided
    $tax_query = array();
    if (!empty($filter_taxonomy) && !empty($filter_term_ids)) {
        $term_ids = array_filter(array_map('intval', explode(',', $filter_term_ids)));
        if (!empty($term_ids)) {
            $tax_query[] = array(
                'taxonomy' => $filter_taxonomy,
                'field'    => 'term_id',
                'terms'    => $term_ids,
            );
        }
    }

    if (!empty($selected_cat_id)) {
        $cat_tax = !empty($attributes['categoryFilterTaxonomy']) ? sanitize_key($attributes['categoryFilterTaxonomy']) : 'category';
        $tax_query[] = array(
            'taxonomy' => $cat_tax,
            'field'    => 'term_id',
            'terms'    => array($selected_cat_id),
        );
    }

    if (!empty($tax_query)) {
        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'AND';
        }
        $args['tax_query'] = $tax_query;
    }

    // Generate cache key based on query arguments
    $cache_key = 'align_ls_' . md5(wp_json_encode($args));
    $html = get_transient($cache_key);

    if (false === $html) {
        $query = new WP_Query($args);

        // Load result item template
        require_once ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/live-search/render-results.php';

        ob_start();
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                align_live_search_render_result_item(get_the_ID(), $attributes);
            }
            wp_reset_postdata();
        } else {
            echo '<div class="align-ls-no-results">No results found.</div>';
        }
        $html = ob_get_clean();

        // Cache search results for 15 minutes
        set_transient($cache_key, $html, 15 * MINUTE_IN_SECONDS);
    }

    return new WP_REST_Response(array('success' => true, 'data' => $html), 200);
}

/**
 * Rate-limit callback for live search REST endpoint.
 * Allows max 30 requests per IP per minute (uses transient cache).
 * Returns true for normal traffic, WP_Error to throttle abusers.
 *
 * @return true|WP_Error
 */
function align_live_search_rest_permission(WP_REST_Request $request) {
    // Rate limiting: max 60 requests per IP per minute
    $ip   = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
    $key  = 'align_ls_rl_' . md5($ip);
    $hits = (int) get_transient($key);

    if ($hits >= 60) {
        return new WP_Error(
            'rate_limited',
            'Too many requests. Please wait a moment.',
            array('status' => 429)
        );
    }

    set_transient($key, $hits + 1, MINUTE_IN_SECONDS);

    return true;
}

/**
 * Register REST API route for live search.
 */
function align_live_search_register_rest_route() {
    register_rest_route('align/v1', '/live-search', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'align_live_search_rest_handler',
        'permission_callback' => 'align_live_search_rest_permission',
    ));
}
add_action('rest_api_init', 'align_live_search_register_rest_route');

/**
 * Clear live search transients when posts are saved, updated or deleted.
 */
function align_live_search_clear_transients() {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_align_ls_%' OR option_name LIKE '_transient_timeout_align_ls_%'");
}
add_action('save_post', 'align_live_search_clear_transients');
add_action('deleted_post', 'align_live_search_clear_transients');


