<?php
/**
 * Title: Hero Banner
 * Slug: align/banner
 * Categories: align-custom
 * Inserter: true
 *
 * @package Align Child
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!-- wp:group {"tagName":"section","align":"full","layout":{"type":"constrained"},"style":{"spacing":{"padding":{"top":"5rem","bottom":"5rem"}}}} -->
<section class="wp-block-group alignfull hero-banner-section">
    <!-- wp:heading {"level":1,"align":"center"} -->
    <h1 class="wp-block-heading has-text-align-center">Welcome to Our Site</h1>
    <!-- /wp:heading -->

    <!-- wp:paragraph {"align":"center"} -->
    <p class="has-text-align-center">Build your modern website using standard custom blocks and templates.</p>
    <!-- /wp:paragraph -->

    <!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
    <div class="wp-block-buttons">
        <!-- wp:button -->
        <div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">Get Started</a></div>
        <!-- /wp:button -->
    </div>
    <!-- /wp:buttons -->
</section>
<!-- /wp:group -->