<?php

function register_acf_blocks()
{
    // register_block_type(__DIR__ . '/our-blocks/splide');
    // register_block_type(__DIR__ . '/our-blocks/custom-table');
}
// add_action('init', 'register_acf_blocks');

// -------------------------
// Page Excerpt Support
// -------------------------
add_post_type_support('page', 'excerpt');

// Remove inline style CSS and decode script entities on frontend only
add_action('template_redirect', function () {
    if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return;
    }

    ob_start(function ($html) {
        $ids = [
            'wp-img-auto-sizes-contain-inline-css',
            'wp-block-post-content-inline-css',
            'wp-block-group-inline-css',
            'wp-block-group-theme-inline-css',
            'wp-block-term-name-inline-css',
            'wp-block-term-template-inline-css',
            'wp-block-template-skip-link-inline-css',
            'wp-block-template-part-theme-inline-css',
            'global-styles-inline-css',
            'wp-block-library-inline-css',
            'greenshift-post-css-inline-css',
            'wp-emoji-styles-inline-css',
            'wp-block-paragraph-inline-css',
            'greenshift-global-css-inline-css'
        ];
        foreach ($ids as $id) {
            $pattern = '#<style[^>]*id=(["\'])'
                . preg_quote($id, '#')
                . '\1[^>]*>.*?</style>#si';
            $html = preg_replace($pattern, '', $html);
        }

        // Remove WP Interactivity API & Navigation Block script modules
        $script_module_ids = [
            '@wordpress/block-library/navigation/view-js-module',
            '@wordpress/interactivity-js-modulepreload',
            '@wordpress/interactivity',
        ];
        foreach ($script_module_ids as $mod_id) {
            $pattern_script = '#<script[^>]*id=(["\'])' . preg_quote($mod_id, '#') . '\1[^>]*>.*?</script>#si';
            $pattern_link   = '#<link[^>]*id=(["\'])'   . preg_quote($mod_id, '#') . '\1[^>]*>#si';
            $html = preg_replace($pattern_script, '', $html);
            $html = preg_replace($pattern_link, '', $html);
        }

        // Fix any &#038; or &amp; inside all inline <script> tags across the entire page
        $html = preg_replace_callback( '/<script\b([^>]*)>([\s\S]*?)<\/script>/is', function( $matches ) {
            $attrs = $matches[1];
            $code  = $matches[2];
            if ( false !== stripos( $attrs, 'application/ld+json' ) || false !== stripos( $attrs, 'speculationrules' ) ) {
                return '<script' . $attrs . '>' . $code . '</script>';
            }
            $clean = str_replace( array( '&#038;', '&amp;' ), '&', $code );
            return '<script' . $attrs . '>' . $clean . '</script>';
        }, $html );

        return $html;
    });
});

// -------------------------------------------------------------
// 🚫 Disable Core WordPress & Gutenberg Default CSS Styles (Frontend Only)
// -------------------------------------------------------------
add_action( 'init', function() {
    if ( is_admin() ) {
        return;
    }
    // Disable block skip link styles
    remove_action( 'wp_enqueue_scripts', 'wp_enqueue_block_template_skip_link' );
} );

function align_disable_wp_core_styles() {
    if ( is_admin() ) {
        return;
    }

    $handles = [
        'wp-block-library',
        'wp-block-library-theme',
        'wp-block-directory-provider',
        'classic-theme-styles',
        'global-styles',
        'wp-block-template-skip-link',
    ];

    foreach ( $handles as $handle ) {
        wp_dequeue_style( $handle );
        wp_deregister_style( $handle );
    }

    // Dynamic clean-up of queued block styles
    global $wp_styles;
    if ( ! empty( $wp_styles->queue ) ) {
        foreach ( $wp_styles->queue as $handle ) {
            if ( strpos( $handle, 'wp-block-' ) === 0 ) {
                wp_dequeue_style( $handle );
                wp_deregister_style( $handle );
            }
        }
    }
}
add_action( 'wp_enqueue_scripts', 'align_disable_wp_core_styles', 1000 );
add_action( 'wp_print_styles', 'align_disable_wp_core_styles', 1000 );

// -------------------------------------------------------------
// 🚫 Disable WP Core Interactivity API & Navigation Script Modules
// -------------------------------------------------------------
function align_disable_wp_navigation_script_modules() {
    if ( is_admin() ) {
        return;
    }

    $modules = [
        '@wordpress/block-library/navigation/view-js-module',
        '@wordpress/interactivity',
    ];

    foreach ( $modules as $module ) {
        if ( function_exists( 'wp_dequeue_script_module' ) ) {
            wp_dequeue_script_module( $module );
        }
        if ( function_exists( 'wp_deregister_script_module' ) ) {
            wp_deregister_script_module( $module );
        }
    }
}
add_action( 'wp_enqueue_scripts', 'align_disable_wp_navigation_script_modules', 1000 );
add_action( 'wp_head', 'align_disable_wp_navigation_script_modules', 1000 );
add_action( 'wp_footer', 'align_disable_wp_navigation_script_modules', 1000 );


