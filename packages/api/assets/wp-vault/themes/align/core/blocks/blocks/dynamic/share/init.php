<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Align Share block
 */
function align_register_share_block() {
    $block_json_path = ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/share/block.json';
    if (!file_exists($block_json_path)) {
        return;
    }

    $metadata = json_decode(file_get_contents($block_json_path), true);

    $editor_script_handle = 'align-share-editor';
    wp_register_script(
        $editor_script_handle,
        ALIGN_DIR_URL . 'core/blocks/blocks/dynamic/share/editor.js',
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'),
        filemtime(ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/share/editor.js'),
        true
    );

    $frontend_script_handle = 'align-share-frontend';
    wp_register_script(
        $frontend_script_handle,
        ALIGN_DIR_URL . 'core/blocks/blocks/dynamic/share/script.js',
        array(),
        filemtime(ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/share/script.js'),
        true
    );

    $style_handle = 'align-share-style';
    wp_register_style(
        $style_handle,
        ALIGN_DIR_URL . 'core/blocks/blocks/dynamic/share/style.css',
        array(),
        filemtime(ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/share/style.css')
    );

    register_block_type('align/share', array(
        'api_version'     => $metadata['apiVersion'],
        'title'           => $metadata['title'],
        'category'        => $metadata['category'],
        'icon'            => isset($metadata['icon']) ? $metadata['icon'] : 'share',
        'attributes'      => $metadata['attributes'],
        'supports'        => $metadata['supports'],
        'editor_script'   => $editor_script_handle,
        'script'          => $frontend_script_handle,
        'style'           => $style_handle,
        'render_callback' => 'align_share_render_callback',
    ));
}
add_action('init', 'align_register_share_block');

function align_share_render_callback($attributes, $content = '') {
    ob_start();
    require ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/share/render.php';
    return ob_get_clean();
}
