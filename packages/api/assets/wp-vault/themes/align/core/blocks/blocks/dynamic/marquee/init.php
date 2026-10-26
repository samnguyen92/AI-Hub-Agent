<?php
if (!defined('ABSPATH')) {
    exit;
}

function align_register_marquee_block() {
    $block_json_path = ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/marquee/block.json';
    if (!file_exists($block_json_path)) {
        return;
    }

    $metadata = json_decode(file_get_contents($block_json_path), true);

    $editor_script_handle = 'align-marquee-editor';
    wp_register_script(
        $editor_script_handle,
        ALIGN_DIR_URL . 'core/blocks/blocks/dynamic/marquee/editor.js',
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'align-query-builder-editor-helpers'),
        filemtime(ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/marquee/editor.js'),
        true
    );

    $frontend_script_handle = 'align-marquee-frontend';
    wp_register_script(
        $frontend_script_handle,
        ALIGN_DIR_URL . 'core/blocks/blocks/dynamic/marquee/script.js',
        array('splide-js', 'splide-autoscroll-js'),
        filemtime(ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/marquee/script.js'),
        true
    );

    $style_handle = 'align-marquee-style';
    wp_register_style(
        $style_handle,
        ALIGN_DIR_URL . 'core/blocks/blocks/dynamic/marquee/style.css',
        array('splide-css'),
        filemtime(ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/marquee/style.css')
    );

    register_block_type('align/marquee', array(
        'api_version'      => $metadata['apiVersion'],
        'title'            => $metadata['title'],
        'category'         => $metadata['category'],
        'icon'             => isset($metadata['icon']) ? $metadata['icon'] : 'slides',
        'attributes'       => $metadata['attributes'],
        'supports'         => $metadata['supports'],
        'editor_script'    => $editor_script_handle,
        'script'           => $frontend_script_handle,
        'style'            => $style_handle,
        'render_callback'  => 'align_marquee_render_callback',
    ));
}
add_action('init', 'align_register_marquee_block');

function align_marquee_render_callback($attributes, $content = '') {
    ob_start();
    require ALIGN_DIR_PATH . 'core/blocks/blocks/dynamic/marquee/render.php';
    return ob_get_clean();
}
