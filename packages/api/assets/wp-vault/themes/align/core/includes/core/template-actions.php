<?php
/**
 * Quick edit template action links for WordPress admin post list table.
 *
 * @package Align
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add "Edit Template" action link to the hover action links for posts/pages/CPTs.
 *
 * @param array   $actions An array of row action links.
 * @param WP_Post $post    The post object.
 * @return array Modified array of row action links.
 */
function align_add_edit_template_row_action($actions, $post) {
    // Only show to users who are allowed to edit templates
    if (!current_user_can('edit_theme_options')) {
        return $actions;
    }

    $post_type = $post->post_type;

    // Ignore structural and non-viewable post types
    $ignored_post_types = array(
        'attachment',
        'revision',
        'nav_menu_item',
        'custom_css',
        'customize_changeset',
        'oembed_cache',
        'user_request',
        'wp_block',
        'wp_template',
        'wp_template_part',
        'wp_navigation'
    );

    if (in_array($post_type, $ignored_post_types, true)) {
        return $actions;
    }

    $template_slugs = array();

    // 1. Check if a custom template is directly assigned to the post
    $custom_template = get_post_meta($post->ID, '_wp_page_template', true);
    if (!empty($custom_template) && $custom_template !== 'default') {
        if (strpos($custom_template, '//') !== false) {
            $parts = explode('//', $custom_template);
            $template_slugs[] = end($parts);
        } elseif (strpos($custom_template, '.php') === false) {
            $template_slugs[] = $custom_template;
        }
    }

    // 2. Add hierarchical fallback template names
    if ($post_type === 'page') {
        $template_slugs[] = 'page-' . $post->post_name;
        $template_slugs[] = 'page-' . $post->ID;
        $template_slugs[] = 'page';
        $template_slugs[] = 'singular';
        $template_slugs[] = 'index';
    } elseif ($post_type === 'post') {
        $template_slugs[] = 'single-post';
        $template_slugs[] = 'single';
        $template_slugs[] = 'singular';
        $template_slugs[] = 'index';
    } else {
        // For custom post types (ACF, SCF, etc.)
        $template_slugs[] = 'single-' . $post_type . '-' . $post->post_name;
        $template_slugs[] = 'single-' . $post_type;
        $template_slugs[] = 'single';
        $template_slugs[] = 'singular';
        $template_slugs[] = 'index';
    }

    // Clean up empty values and duplicates
    $template_slugs = array_unique(array_filter($template_slugs));

    // Resolve the first existing block template using WordPress Core API
    $resolved_slug = 'index';
    if (function_exists('get_block_templates')) {
        $found_templates = get_block_templates(array('slug__in' => $template_slugs));
        if (!empty($found_templates)) {
            foreach ($template_slugs as $slug) {
                foreach ($found_templates as $tpl) {
                    if ($tpl->slug === $slug) {
                        $resolved_slug = $slug;
                        break 2;
                    }
                }
            }
        } else {
            $resolved_slug = reset($template_slugs);
        }
    } else {
        $resolved_slug = reset($template_slugs);
    }

    // Construct the direct Site Editor edit URL
    $theme_slug = get_stylesheet();
    $template_id = $theme_slug . '//' . $resolved_slug;
    $edit_url = admin_url('site-editor.php?postType=wp_template&postId=' . urlencode($template_id) . '&canvas=edit');

    // Inject the custom action link with native WordPress admin colors
    $actions['edit_template'] = sprintf(
        '<a href="%s" title="%s">%s</a>',
        esc_url($edit_url),
        esc_attr__('Edit the template assigned to this post', 'align'),
        esc_html__('Edit Template', 'align')
    );

    // Reorder actions to place 'edit_template' right after 'edit'
    // This is safe and does not affect WordPress core functionality
    $reordered_actions = array();
    if (isset($actions['edit'])) {
        $reordered_actions['edit'] = $actions['edit'];
    }
    
    $reordered_actions['edit_template'] = $actions['edit_template'];

    foreach ($actions as $key => $val) {
        if ($key !== 'edit' && $key !== 'edit_template') {
            $reordered_actions[$key] = $val;
        }
    }

    return $reordered_actions;
}
add_filter('post_row_actions', 'align_add_edit_template_row_action', 10, 2);
add_filter('page_row_actions', 'align_add_edit_template_row_action', 10, 2);
