<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Align Breadcrumb block
 */
function align_register_breadcrumb_block() {
    $block_json_path = ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/breadcrumb/block.json';
    if (!file_exists($block_json_path)) {
        return;
    }

    $metadata = json_decode(file_get_contents($block_json_path), true);

    $editor_script_handle = 'align-breadcrumb-editor';
    wp_register_script(
        $editor_script_handle,
        ALIGN_DIR_URL . 'core/blocks/blocks/dynamic/breadcrumb/editor.js',
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'),
        filemtime(ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/breadcrumb/editor.js'),
        true
    );

    $style_handle = 'align-breadcrumb-style';
    wp_register_style(
        $style_handle,
        ALIGN_DIR_URL . 'core/blocks/blocks/dynamic/breadcrumb/style.css',
        array(),
        filemtime(ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/breadcrumb/style.css')
    );

    register_block_type('align/breadcrumb', array(
        'api_version'     => $metadata['apiVersion'],
        'title'           => $metadata['title'],
        'category'        => $metadata['category'],
        'icon'            => isset($metadata['icon']) ? $metadata['icon'] : 'format-status',
        'attributes'      => $metadata['attributes'],
        'supports'        => $metadata['supports'],
        'editor_script'   => $editor_script_handle,
        'style'           => $style_handle,
        'render_callback' => 'align_breadcrumb_render_callback',
    ));
}
add_action('init', 'align_register_breadcrumb_block');

function align_breadcrumb_render_callback($attributes, $content = '') {
    ob_start();
    require ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/breadcrumb/render.php';
    return ob_get_clean();
}
