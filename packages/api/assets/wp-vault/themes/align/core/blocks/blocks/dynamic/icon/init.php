<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Align Icon block
 */
function align_register_icon_block() {
    $block_json_path = ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/icon/block.json';
    if (!file_exists($block_json_path)) {
        return;
    }

    $metadata = json_decode(file_get_contents($block_json_path), true);

    $lucide_script_handle = 'align-icon-lucide';
    wp_register_script(
        $lucide_script_handle,
        ALIGN_DIR_URL . 'core/blocks/blocks/dynamic/icon/lucide-icons.js',
        array(),
        filemtime(ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/icon/lucide-icons.js'),
        true
    );

    $editor_script_handle = 'align-icon-editor';
    wp_register_script(
        $editor_script_handle,
        ALIGN_DIR_URL . 'core/blocks/blocks/dynamic/icon/editor.js',
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', $lucide_script_handle),
        filemtime(ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/icon/editor.js'),
        true
    );

    $style_handle = 'align-icon-style';
    wp_register_style(
        $style_handle,
        ALIGN_DIR_URL . 'core/blocks/blocks/dynamic/icon/style.css',
        array(),
        filemtime(ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/icon/style.css')
    );

    register_block_type('align/icon', array(
        'api_version'     => isset($metadata['apiVersion']) ? $metadata['apiVersion'] : 3,
        'title'           => isset($metadata['title']) ? $metadata['title'] : 'Icon',
        'category'        => isset($metadata['category']) ? $metadata['category'] : 'align-category',
        'icon'            => isset($metadata['icon']) ? $metadata['icon'] : 'star-filled',
        'attributes'      => isset($metadata['attributes']) ? $metadata['attributes'] : array(),
        'supports'        => isset($metadata['supports']) ? $metadata['supports'] : array(),
        'editor_script'   => $editor_script_handle,
        'style'           => $style_handle,
        'render_callback' => 'align_icon_render_callback',
    ));
}
add_action('init', 'align_register_icon_block');

function align_icon_render_callback($attributes, $content = '') {
    ob_start();
    require ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/icon/render.php';
    return ob_get_clean();
}
