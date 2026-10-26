<?php

/**
 * Title: Breadcrumbs
 * Slug: align/breadcrumbs
 * Categories: align-custom
 * Inserter: true
 * 
 * @package Align
 */

if (! defined('ABSPATH')) {
  exit;
}

// Resolve the correct queried object ID in FSE template context
$queried_post_id = get_queried_object_id();
$post_type       = get_post_type($queried_post_id);
$categories      = [];
$primary_cat     = null;

// Determine post type and retrieve categories or taxonomy terms
if ($post_type && ! in_array($post_type, ['post', 'page'], true)) {
  // For Custom Post Types, retrieve associated taxonomies dynamically
  $taxonomies = get_object_taxonomies($post_type);
  if (! empty($taxonomies)) {
    // Filter out format taxonomies to get the main classification taxonomy
    $custom_tax = array_filter($taxonomies, function ($tax) {
      return ! in_array($tax, ['post_format'], true);
    });

    if (! empty($custom_tax)) {
      $primary_tax = reset($custom_tax);
      if (class_exists('AlignPostHelper')) {
        $primary_cat = AlignPostHelper::getPrimaryTermObject($queried_post_id, $primary_tax);
      } else {
        $terms       = get_the_terms($queried_post_id, $primary_tax);
        $primary_cat = (! empty($terms) && ! is_wp_error($terms)) ? $terms[0] : null;
      }
    }
  }
} else {
  // For standard posts, fallback to the primary category or default categories
  if (class_exists('AlignPostHelper')) {
    $primary_cat = AlignPostHelper::getPrimaryTermObject($queried_post_id, 'category');
  } else {
    $categories  = get_the_category();
    $primary_cat = ! empty($categories) ? $categories[0] : null;
  }
}

// Retrieve custom post type details if applicable
$cpt_object       = null;
$cpt_archive_link = '';
if ($post_type && ! in_array($post_type, ['post', 'page', 'attachment'], true)) {
  $cpt_object = get_post_type_object($post_type);
  if ($cpt_object) {
    $cpt_archive_link = get_post_type_archive_link($post_type);
  }
}



?>
<!-- Breadcrumb Nav -->
<nav class="align-breadcrumbs" aria-label="Breadcrumb">
  <a href="<?php echo esc_url(home_url('/')); ?>">Home</a>

  <?php if ($cpt_object) : ?>
    <span aria-hidden="true">/</span>
    <?php if ($cpt_archive_link) : ?>
      <a href="<?php echo esc_url($cpt_archive_link); ?>">
        <?php echo esc_html($cpt_object->labels->name); ?>
      </a>
    <?php else : ?>
      <span>
        <?php echo esc_html($cpt_object->labels->name); ?>
      </span>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($primary_cat) : ?>
    <span aria-hidden="true">/</span>
    <?php
    $term_link = is_a($primary_cat, 'WP_Term') ? get_term_link($primary_cat) : get_category_link($primary_cat->term_id);
    if (! is_wp_error($term_link)) :
    ?>
      <a href="<?php echo esc_url($term_link); ?>">
        <?php echo esc_html($primary_cat->name); ?>
      </a>
    <?php else : ?>
      <span>
        <?php echo esc_html($primary_cat->name); ?>
      </span>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (is_singular() || is_single() || ($queried_post_id && get_post_status($queried_post_id) === 'publish')) : ?>
    <span aria-hidden="true">/</span>
    <span class="text-black">
      <?php echo esc_html(get_the_title($queried_post_id)); ?>
    </span>
  <?php endif; ?>
</nav>