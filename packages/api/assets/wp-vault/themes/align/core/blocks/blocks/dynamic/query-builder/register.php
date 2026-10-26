<?php
if (!defined('ABSPATH')) {
    exit;
}
function align_register_query_builder_block() {
    $metadata = json_decode(file_get_contents(__DIR__ . '/block.json'), true);
    $helpers_handle = 'align-query-builder-editor-helpers';
    wp_register_script(
        $helpers_handle,
        ALIGN_DIR_URL . 'core/blocks/blocks/dynamic/query-builder/editor-helpers.js',
        array('wp-element', 'wp-components', 'wp-i18n'),
        filemtime(ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/query-builder/editor-helpers.js'),
        true
    );
    $script_handle = 'align-query-builder-editor';
    wp_register_script(
        $script_handle,
        ALIGN_DIR_URL . 'core/blocks/blocks/dynamic/query-builder/editor.js',
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-data', 'wp-components', 'wp-i18n', 'wp-server-side-render', $helpers_handle),
        filemtime(ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/query-builder/editor.js'),
        true
    );
    $pt_objects = get_post_types(array('public' => true), 'objects');
    $editor_post_types = array();
    foreach ($pt_objects as $pt) {
        if ($pt->name === 'attachment') continue;
        $editor_post_types[] = array(
            'slug' => $pt->name,
            'name' => $pt->label,
        );
    }
    wp_localize_script($script_handle, 'alignBlockPostTypes', $editor_post_types);

    $tax_objects = get_taxonomies(array('public' => true), 'objects');
    $editor_taxonomies = array();
    foreach ($tax_objects as $tax) {
        if ($tax->name === 'post_format') continue;
        $terms = get_terms(array(
            'taxonomy'   => $tax->name,
            'hide_empty' => false,
            'number'     => 200,
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
            'name'       => $tax->label,
            'post_types' => array_values($tax->object_type),
            'terms'      => $term_list,
        );
    }
    wp_localize_script($script_handle, 'alignBlockTaxonomies', $editor_taxonomies);
    wp_localize_script($script_handle, 'alignPluginSupport', array(
        'rankMath' => AlignPostHelper::isRankMathActive(),
        'acf'      => AlignPostHelper::isAcfActive(),
    ));
    $slide_script_handle = 'align-query-builder-editor-slide';
    wp_register_script(
        $slide_script_handle,
        ALIGN_DIR_URL . 'core/blocks/blocks/dynamic/query-builder/editor-slide.js',
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-data', 'wp-components', 'wp-i18n', $helpers_handle),
        filemtime(ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/query-builder/editor-slide.js'),
        true
    );
    $frontend_script_handle = 'align-query-builder-frontend';
    // Splide JS/CSS are registered centrally by PluginAssets::register()
    // and conditionally enqueued by PluginAssets::enqueue()
    wp_register_script(
        $frontend_script_handle,
        ALIGN_DIR_URL . 'core/blocks/blocks/dynamic/query-builder/script.js',
        array('splide-js'),
        filemtime(ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/query-builder/script.js'),
        true
    );
    wp_register_style(
        'align-query-builder-style',
        ALIGN_DIR_URL . 'core/blocks/blocks/dynamic/query-builder/style.css',
        array('splide-css'),
        filemtime(ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/query-builder/style.css')
    );
    wp_localize_script($frontend_script_handle, 'alignQueryBuilderObj', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('align_query_builder_nonce'),
    ));
    require_once ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/query-builder/render.php';
    register_block_type('align/query-builder', array(
        'api_version'      => $metadata['apiVersion'],
        'title'            => $metadata['title'],
        'category'         => $metadata['category'],
        'icon'             => isset($metadata['icon']) ? $metadata['icon'] : 'layout',
        'attributes'       => $metadata['attributes'],
        'supports'         => $metadata['supports'],
        'uses_context'     => array('postId', 'postType'),
        'editor_script'    => $script_handle,
        'script'           => $frontend_script_handle,
        'style'            => 'align-query-builder-style',
        'render_callback'  => 'align_query_builder_render_callback',
    ));
    $slide_metadata = json_decode(file_get_contents(__DIR__ . '/slide-block.json'), true);
    register_block_type('align/slide', array(
        'api_version'      => $slide_metadata['apiVersion'],
        'title'            => $slide_metadata['title'],
        'category'         => $slide_metadata['category'],
        'parent'           => $slide_metadata['parent'],
        'icon'             => $slide_metadata['icon'],
        'attributes'       => $slide_metadata['attributes'],
        'supports'         => $slide_metadata['supports'],
        'editor_script'    => $slide_script_handle,
        'style'            => 'align-query-builder-style',
        'render_callback'  => 'align_slide_render_callback',
    ));
}
add_action('init', 'align_register_query_builder_block', 99);
function align_query_builder_ajax_load_more() {
    // Verify nonce — both logged-in and guest users send the same nonce from localized script.
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'align_query_builder_nonce')) {
        wp_send_json_error('Invalid nonce', 403);
        wp_die();
    }

    if (!isset($_POST['attributes']) || !isset($_POST['paged'])) {
        wp_send_json_error('Missing parameters');
        wp_die();
    }
    $attributes = json_decode(stripslashes($_POST['attributes']), true);
    if (!is_array($attributes)) {
        wp_send_json_error('Invalid attributes');
        wp_die();
    }
    $attributes['paged'] = intval($_POST['paged']);
    $attributes['isAjax'] = true;
    if (isset($_POST['s'])) {
        $attributes['searchKeyword'] = sanitize_text_field($_POST['s']);
    }
    // Load render function if not loaded
    require_once ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/query-builder/render.php';
    // Render the items
    $html = align_query_builder_render_callback($attributes, '');
    $maxPages = isset($GLOBALS['align_qb_last_max_pages']) ? intval($GLOBALS['align_qb_last_max_pages']) : 1;
    $paginationHtml = isset($GLOBALS['align_qb_last_pagination_html']) ? $GLOBALS['align_qb_last_pagination_html'] : '';
    if ($html) {
        wp_send_json_success(array(
            'html' => $html,
            'paginationHtml' => $paginationHtml,
            'maxPages' => $maxPages,
        ));
    } else {
        wp_send_json_error('No posts found');
    }
    wp_die();
}
add_action('wp_ajax_align_query_builder_load_more', 'align_query_builder_ajax_load_more');
add_action('wp_ajax_nopriv_align_query_builder_load_more', 'align_query_builder_ajax_load_more');

