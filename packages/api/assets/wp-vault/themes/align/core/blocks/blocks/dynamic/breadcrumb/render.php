<?php
if (!defined('ABSPATH')) {
    exit;
}

$separator   = isset($attributes['separator']) ? $attributes['separator'] : '/';
$home_text   = isset($attributes['homeText']) ? trim($attributes['homeText']) : 'Home';
$show_current = isset($attributes['showCurrent']) ? filter_var($attributes['showCurrent'], FILTER_VALIDATE_BOOLEAN) : true;
$custom_class = isset($attributes['customClass']) ? trim($attributes['customClass']) : '';
$element_id  = isset($attributes['elementId']) ? trim($attributes['elementId']) : '';
$aria_label  = isset($attributes['ariaLabel']) ? trim($attributes['ariaLabel']) : 'Breadcrumb';

if (empty($home_text)) {
    $home_text = 'Home';
}

$crumbs = array();

// 1. Home Link
$crumbs[] = array(
    'title' => $home_text,
    'url'   => home_url('/'),
);

if (is_front_page() || is_home()) {
    if (!$show_current) {
        $crumbs = array();
    }
} elseif (is_singular('post')) {
    $categories = get_the_category();
    if (!empty($categories)) {
        $main_cat = $categories[0];
        $parents = get_ancestors($main_cat->term_id, 'category', 'taxonomy');
        $parents = array_reverse($parents);
        foreach ($parents as $parent_id) {
            $parent_term = get_term($parent_id, 'category');
            if ($parent_term && !is_wp_error($parent_term)) {
                $crumbs[] = array(
                    'title' => $parent_term->name,
                    'url'   => get_term_link($parent_term),
                );
            }
        }
        $crumbs[] = array(
            'title' => $main_cat->name,
            'url'   => get_term_link($main_cat),
        );
    }
    if ($show_current) {
        $crumbs[] = array(
            'title' => get_the_title(),
            'url'   => '',
        );
    }
} elseif (is_singular('page')) {
    global $post;
    if ($post && $post->post_parent) {
        $ancestors = get_post_ancestors($post->ID);
        $ancestors = array_reverse($ancestors);
        foreach ($ancestors as $ancestor_id) {
            $crumbs[] = array(
                'title' => get_the_title($ancestor_id),
                'url'   => get_permalink($ancestor_id),
            );
        }
    }
    if ($show_current) {
        $crumbs[] = array(
            'title' => get_the_title(),
            'url'   => '',
        );
    }
} elseif (is_singular()) {
    $post_type = get_post_type();
    $post_type_obj = get_post_type_object($post_type);
    if ($post_type_obj && $post_type_obj->has_archive) {
        $crumbs[] = array(
            'title' => $post_type_obj->labels->name,
            'url'   => get_post_type_archive_link($post_type),
        );
    }
    if ($show_current) {
        $crumbs[] = array(
            'title' => get_the_title(),
            'url'   => '',
        );
    }
} elseif (is_category() || is_tag() || is_tax()) {
    $term = get_queried_object();
    if ($term && !is_wp_error($term)) {
        if ($term->parent) {
            $parents = get_ancestors($term->term_id, $term->taxonomy, 'taxonomy');
            $parents = array_reverse($parents);
            foreach ($parents as $parent_id) {
                $parent_term = get_term($parent_id, $term->taxonomy);
                if ($parent_term && !is_wp_error($parent_term)) {
                    $crumbs[] = array(
                        'title' => $parent_term->name,
                        'url'   => get_term_link($parent_term),
                    );
                }
            }
        }
        if ($show_current) {
            $crumbs[] = array(
                'title' => $term->name,
                'url'   => '',
            );
        }
    }
} elseif (is_search()) {
    if ($show_current) {
        $crumbs[] = array(
            'title' => sprintf(__('Search: %s', 'align-function'), get_search_query()),
            'url'   => '',
        );
    }
} elseif (is_404()) {
    if ($show_current) {
        $crumbs[] = array(
            'title' => __('404 Not Found', 'align-function'),
            'url'   => '',
        );
    }
}

if (empty($crumbs)) {
    return;
}

$id_attr = !empty($element_id) ? ' id="' . esc_attr($element_id) . '"' : '';
$class_attr = 'align-breadcrumb ' . esc_attr($custom_class);
?>
<nav<?php echo $id_attr; ?> class="<?php echo trim($class_attr); ?>" aria-label="<?php echo esc_attr($aria_label); ?>">
    <ol class="align-breadcrumb-list" itemscope itemtype="https://schema.org/BreadcrumbList">
        <?php foreach ($crumbs as $index => $crumb): ?>
            <?php
            $is_last = ($index === count($crumbs) - 1);
            $pos = $index + 1;
            ?>
            <li class="align-breadcrumb-item<?php echo $is_last ? ' is-current' : ''; ?>" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                <?php if (!empty($crumb['url']) && !$is_last): ?>
                    <a itemprop="item" href="<?php echo esc_url($crumb['url']); ?>">
                        <span itemprop="name"><?php echo esc_html($crumb['title']); ?></span>
                    </a>
                <?php else: ?>
                    <span itemprop="name" <?php echo $is_last ? 'aria-current="page"' : ''; ?>><?php echo esc_html($crumb['title']); ?></span>
                <?php endif; ?>
                <meta itemprop="position" content="<?php echo intval($pos); ?>" />
            </li>
            <?php if (!$is_last): ?>
                <li class="align-breadcrumb-separator" aria-hidden="true"><?php echo esc_html($separator); ?></li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ol>
</nav>
