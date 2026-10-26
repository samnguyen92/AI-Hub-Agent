<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper functions for blocks to auto-detect block name from folder structure
 * 
 * Usage in block files:
 *   $block_info = align_block_get_info(__DIR__);
 *   $block_name = $block_info['name']; // e.g., "gallery-dynamic"
 *   $block_namespace = $block_info['namespace']; // e.g., "align/gallery-dynamic"
 *   $block_function_prefix = $block_info['function_prefix']; // e.g., "align_gallery_dynamic"
 */

/**
 * Get block information from directory path
 * 
 * @param string $dir_path Directory path of the block (usually __DIR__)
 * @return array Block information array
 */
function align_block_get_info($dir_path) {
    // Normalize path
    $dir_path = rtrim($dir_path, '/\\');
    
    // Extract block name from path
    // Expected: .../blocks/dynamic/{block-name}/
    $parts = explode(DIRECTORY_SEPARATOR, $dir_path);
    $block_name = end($parts);
    
    // Fallback: if not in expected structure, use folder name
    if (empty($block_name) || $block_name === '.' || $block_name === '..') {
        $block_name = basename($dir_path);
    }
    
    // Generate namespace and function prefix
    $namespace = 'align/' . $block_name;
    $function_prefix = 'align_' . str_replace('-', '_', $block_name);
    $shortcode_name = 'align_' . str_replace('-', '_', $block_name);
    
    return array(
        'name' => $block_name,
        'namespace' => $namespace,
        'function_prefix' => $function_prefix,
        'shortcode_name' => $shortcode_name,
        'dir_path' => $dir_path,
        'dir_url' => ALIGN_DIR_URL . 'core/blocks/blocks/dynamic/' . $block_name . '/',
    );
}


