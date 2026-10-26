<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders a single search result item.
 * Devs can modify this template to customize the result card HTML.
 *
 * @param int   $post_id    The post ID.
 * @param array $attributes Block attributes (showThumbnail, showExcerpt, showPostType).
 */
function align_live_search_render_result_item($post_id, $attributes) {
    $show_thumbnail = isset($attributes['showThumbnail']) ? (bool) $attributes['showThumbnail'] : true;
    $show_excerpt   = isset($attributes['showExcerpt'])   ? (bool) $attributes['showExcerpt']   : false;
    $show_post_type = isset($attributes['showPostType'])  ? (bool) $attributes['showPostType']  : false;

    $permalink   = get_permalink($post_id);
    $title       = get_the_title($post_id);
    $post_type   = get_post_type($post_id);
    $post_type_obj = get_post_type_object($post_type);
    $type_label  = $post_type_obj ? $post_type_obj->labels->singular_name : $post_type;
    $excerpt     = wp_trim_words(get_the_excerpt($post_id), 12, '...');

    $thumbnail_html = '';
    if ($show_thumbnail && has_post_thumbnail($post_id)) {
        $thumb_url      = get_the_post_thumbnail_url($post_id, 'thumbnail');
        $thumbnail_html = '<img class="align-ls-item-thumb" src="' . esc_url($thumb_url) . '" alt="' . esc_attr($title) . '" loading="lazy" width="48" height="48" />';
    }
    ?>
    <a class="align-ls-item" href="<?php echo esc_url($permalink); ?>" role="option">
        <?php if ($thumbnail_html): ?>
            <div class="align-ls-item-media"><?php echo $thumbnail_html; ?></div>
        <?php endif; ?>
        <div class="align-ls-item-body">
            <span class="align-ls-item-title"><?php echo esc_html($title); ?></span>
            <?php if ($show_post_type): ?>
                <span class="align-ls-item-type"><?php echo esc_html($type_label); ?></span>
            <?php endif; ?>
            <?php if ($show_excerpt && $excerpt): ?>
                <p class="align-ls-item-excerpt"><?php echo esc_html($excerpt); ?></p>
            <?php endif; ?>
        </div>
    </a>
    <?php
}
