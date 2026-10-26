<?php
if (!defined('ABSPATH')) {
    exit;
}

function align_register_media_block() {
    $block_json_path = ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/media/block.json';
    if (!file_exists($block_json_path)) {
        return;
    }

    $metadata = json_decode(file_get_contents($block_json_path), true);

    $script_handle = 'align-media-editor-script';
    wp_register_script(
        $script_handle,
        ALIGN_DIR_URL . 'core/blocks/blocks/dynamic/media/editor.js',
        array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render'),
        filemtime(ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/media/editor.js'),
        true
    );

    // If editor.css exists, register it
    $style_handle = 'align-media-editor-style';
    $css_path = ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/media/editor.css';
    if (file_exists($css_path)) {
        wp_register_style(
            $style_handle,
            ALIGN_DIR_URL . 'core/blocks/blocks/dynamic/media/editor.css',
            array(),
            filemtime($css_path)
        );
    } else {
        $style_handle = false;
    }

    register_block_type('align/media', array(
        'api_version'      => $metadata['apiVersion'],
        'title'            => $metadata['title'],
        'category'         => $metadata['category'],
        'icon'             => isset($metadata['icon']) ? $metadata['icon'] : 'format-image',
        'attributes'       => $metadata['attributes'],
        'supports'         => $metadata['supports'],
        'editor_script'    => $script_handle,
        'editor_style'     => $style_handle,
        'render_callback'  => 'align_media_render_callback',
    ));
}
add_action('init', 'align_register_media_block');

function align_media_enqueue_editor_assets() {
    $css_path = ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/media/editor.css';
    if (file_exists($css_path)) {
        wp_enqueue_style(
            'align-media-editor-override',
            ALIGN_DIR_URL . 'core/blocks/blocks/dynamic/media/editor.css',
            array(),
            filemtime($css_path)
        );
    }
}
add_action('enqueue_block_editor_assets', 'align_media_enqueue_editor_assets');

function align_media_render_callback($attributes, $content = '') {
    ob_start();
    require ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/media/render.php';
    return ob_get_clean();
}
