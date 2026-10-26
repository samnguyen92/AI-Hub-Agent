<?php

if (!defined('ABSPATH')) {
    exit;
}

// Load individual blocks - uncomment to enable
// Each block's init.php will automatically load its sidebar panel
// require_once ALIGN_CORE_PATH . 'blocks/blocks/dynamic/gallery-dynamic/init.php';
require_once ALIGN_CORE_PATH . 'blocks/blocks/dynamic/query-builder/init.php';
require_once ALIGN_CORE_PATH . 'blocks/blocks/dynamic/live-search/init.php';
require_once ALIGN_CORE_PATH . 'blocks/blocks/dynamic/element/init.php';
require_once ALIGN_CORE_PATH . 'blocks/blocks/dynamic/media/init.php';
require_once ALIGN_CORE_PATH . 'blocks/blocks/dynamic/marquee/init.php';
require_once ALIGN_CORE_PATH . 'blocks/blocks/dynamic/breadcrumb/init.php';
require_once ALIGN_CORE_PATH . 'blocks/blocks/dynamic/share/init.php';
require_once ALIGN_CORE_PATH . 'blocks/blocks/dynamic/icon/init.php';
require_once ALIGN_CORE_PATH . 'blocks/blocks/dynamic/custom-html/init.php';


/**
 * Register custom block category for Align blocks.
 * Available in posts, pages, and templates for API-converted content.
 *
 * @param array                   $categories           Array of block categories.
 * @param WP_Block_Editor_Context $block_editor_context The current block editor context.
 * @return array
 */
function align_block_category( $categories, $block_editor_context = null ) {
    return array_merge(
        array(
            array(
                'slug'  => 'align-category',
                'title' => __( 'Align Builder', 'align-function' ),
            ),
        ),
        $categories
    );
}
add_filter( 'block_categories_all', 'align_block_category', 10, 2 );
