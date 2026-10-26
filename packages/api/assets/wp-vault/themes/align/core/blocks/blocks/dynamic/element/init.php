<?php
if (!defined('ABSPATH')) {
    exit;
}

function align_register_element_block() {
    $block_json_path = ALIGN_CORE_PATH . 'blocks/blocks/dynamic/element/block.json';
    if (!file_exists($block_json_path)) {
        return;
    }

    $metadata = json_decode(file_get_contents($block_json_path), true);

    $components_handle = 'align-element-components';
    wp_register_script(
        $components_handle,
        ALIGN_CORE_URL . 'blocks/blocks/dynamic/element/components.js',
        array('wp-element', 'wp-components', 'wp-i18n'),
        filemtime(ALIGN_CORE_PATH . 'blocks/blocks/dynamic/element/components.js'),
        true
    );

    $utils_handle = 'align-element-utils';
    wp_register_script(
        $utils_handle,
        ALIGN_CORE_URL . 'blocks/blocks/dynamic/element/utils.js',
        array('wp-element', 'wp-i18n'),
        filemtime(ALIGN_CORE_PATH . 'blocks/blocks/dynamic/element/utils.js'),
        true
    );

    $tab_effects_handle = 'align-element-editor-tab-effects';
    wp_register_script(
        $tab_effects_handle,
        ALIGN_CORE_URL . 'blocks/blocks/dynamic/element/tab-effects.js',
        array('wp-element', $utils_handle),
        filemtime(ALIGN_CORE_PATH . 'blocks/blocks/dynamic/element/tab-effects.js'),
        true
    );

    $inspector_handle = 'align-element-editor-inspector';
    wp_register_script(
        $inspector_handle,
        ALIGN_CORE_URL . 'blocks/blocks/dynamic/element/inspector.js',
        array('wp-element', 'wp-components', 'wp-i18n', 'wp-block-editor', 'wp-data', $utils_handle, $components_handle),
        filemtime(ALIGN_CORE_PATH . 'blocks/blocks/dynamic/element/inspector.js'),
        true
    );

    $internal_handle = 'align-element-editor-internal';
    wp_register_script(
        $internal_handle,
        ALIGN_CORE_URL . 'blocks/blocks/dynamic/element/editor-internal.js',
        array('wp-element', 'wp-components', 'wp-i18n', 'wp-block-editor', 'wp-data', $utils_handle, $components_handle, $inspector_handle, $tab_effects_handle),
        filemtime(ALIGN_CORE_PATH . 'blocks/blocks/dynamic/element/editor-internal.js'),
        true
    );

    $script_handle = 'align-element-editor-script';
    wp_register_script(
        $script_handle,
        ALIGN_CORE_URL . 'blocks/blocks/dynamic/element/editor.js',
        array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', $components_handle, $utils_handle, $internal_handle),
        filemtime(ALIGN_CORE_PATH . 'blocks/blocks/dynamic/element/editor.js'),
        true
    );

    $style_handle = 'align-element-editor-style';
    wp_register_style(
        $style_handle,
        ALIGN_CORE_URL . 'blocks/blocks/dynamic/element/editor.css',
        array(),
        filemtime(ALIGN_CORE_PATH . 'blocks/blocks/dynamic/element/editor.css')
    );

    register_block_type('align/element', array(
        'api_version'      => $metadata['apiVersion'],
        'title'            => $metadata['title'],
        'category'         => $metadata['category'],
        'icon'             => isset($metadata['icon']) ? $metadata['icon'] : 'layout',
        'attributes'       => $metadata['attributes'],
        'supports'         => $metadata['supports'],
        'provides_context' => isset($metadata['providesContext']) ? $metadata['providesContext'] : null,
        'uses_context'     => isset($metadata['usesContext']) ? $metadata['usesContext'] : null,
        'editor_script'    => $script_handle,
        'editor_style'     => $style_handle,
        'render_callback'  => 'align_element_render_callback',
    ));
}
add_action('init', 'align_register_element_block');

function align_element_enqueue_editor_assets() {
    wp_enqueue_style(
        'align-element-editor-override',
        ALIGN_CORE_URL . 'blocks/blocks/dynamic/element/editor.css',
        array(),
        filemtime(ALIGN_CORE_PATH . 'blocks/blocks/dynamic/element/editor.css')
    );
}
add_action('enqueue_block_editor_assets', 'align_element_enqueue_editor_assets');
function align_element_render_callback($attributes, $content = '', $block = null) {
    ob_start();
    require ALIGN_CORE_PATH . 'blocks/blocks/dynamic/element/render.php';
    return ob_get_clean();
}
