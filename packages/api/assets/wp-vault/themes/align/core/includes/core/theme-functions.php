<?php
/**
 * Theme Helper Functions and Utilities for Align theme.
 *
 * @package Align
 */

function my_excerpt_length($length)
{
    return 20;
}
add_filter('excerpt_length', 'my_excerpt_length');

add_filter('big_image_size_threshold', '__return_false');

/**
 * Get active External API Converter URL with fallback to default.
 *
 * @return string API Base URL.
 */
function align_get_api_converter_url() {
	$url = get_option( 'align_minify_api_url' );
	if ( empty( $url ) || ! is_string( $url ) || trim( $url ) === '' ) {
		return 'https://convertb.alignvn.cloud';
	}
	return esc_url_raw( trim( $url ) );
}


// ============================
// 🔒 Disable all comments site-wide
// ============================
function disable_all_comments_everywhere()
{
    // 1. Remove comment & trackback support for all post types
    foreach (get_post_types() as $type) {
        remove_post_type_support($type, 'comments');
        remove_post_type_support($type, 'trackbacks');
    }

    // 2. Turn off comments/pings everywhere
    add_filter('comments_open', '__return_false', 20, 2);
    add_filter('pings_open', '__return_false', 20, 2);
    add_filter('comments_array', '__return_empty_array', 10, 2);

    // 3. Remove "Comments" menu from admin sidebar
    add_action('admin_menu', function () {
        remove_menu_page('edit-comments.php');
    });

    // 4. Remove "Comments" from admin top bar
    add_action('admin_bar_menu', function ($wp_admin_bar) {
        $wp_admin_bar->remove_menu('comments');
    }, 999);

    // 5. Block access to comment management page (edit-comments.php)
    add_action('admin_init', function () {
        global $pagenow;
        if ($pagenow === 'edit-comments.php') {
            wp_redirect(admin_url());
            exit;
        }
    });

    // 6. Hide "Recent Comments" widget from dashboard
    add_action('wp_dashboard_setup', function () {
        remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
    });
}

add_action('init', 'disable_all_comments_everywhere');


// ============================
// 🔍 Optimize Search Order (Exact Match First & Slug Support)
// ============================
class AlignSearchOptimizer 
{
    private static $resolved_urls = [];

    /**
     * Initialize filters for search optimization.
     */
    public static function init() 
    {
        // 1. Optimize search ordering (exact slug/title match first)
        add_filter('posts_orderby', [self::class, 'optimizeSearchOrder'], 10, 2);

        // 2. Optimize search SQL query (match title or slug only, skip content/excerpt)
        add_filter('posts_search', [self::class, 'optimizeSearchSql'], 10, 2);

        // 3. Clean search keywords (strip slashes, URL path...) before WP executes query
        add_filter('rest_search_query', [self::class, 'cleanRestSearchQuery'], 10, 2);
    }

    /**
     * Clean search keywords in REST API request.
     */
    public static function cleanRestSearchQuery($args, $request) 
    {
        if (!empty($args['s']) && is_string($args['s'])) {
            $args['s'] = self::cleanSearchTerm($args['s']);
        }
        return $args;
    }

    /**
     * Modify default SQL WHERE search clause to match title or slug only.
     */
    public static function optimizeSearchSql($search, $query) 
    {
        global $wpdb;

        if (self::isRestSearchRequest($query)) {
            $searchTerm = $query->get('s');
            if (!empty($searchTerm)) {
                $slugTerm = sanitize_title(ltrim($searchTerm, '/'));
                if (!empty($slugTerm)) {
                    $likeTerm = '%' . $wpdb->esc_like($searchTerm) . '%';
                    
                    // Match only post_title or post_name (slug), excluding post_content and post_excerpt
                    $search = $wpdb->prepare(
                        " AND (({$wpdb->posts}.post_title LIKE %s) OR ({$wpdb->posts}.post_name = %s) OR ({$wpdb->posts}.post_name LIKE %s))",
                        $likeTerm,
                        $slugTerm,
                        $slugTerm . '%'
                    );
                }
            }
        }

        return $search;
    }

    /**
     * Boost results with 100% exact slug or title match to top.
     */
    public static function optimizeSearchOrder($orderby, $query) 
    {
        if (self::isRestSearchRequest($query)) {
            return self::buildExactMatchOrderSql($orderby, $query);
        }
        return $orderby;
    }

    /**
     * Resolve relative URL based on post ID (with static memory cache).
     */
    public static function resolveUrl($staticHref, $hrefId) 
    {
        $hrefId = intval($hrefId);
        if ($hrefId <= 0) {
            return $staticHref;
        }

        if (isset(self::$resolved_urls[$hrefId])) {
            return self::$resolved_urls[$hrefId];
        }

        $dynamicUrl = get_permalink($hrefId);
        if ($dynamicUrl && !is_wp_error($dynamicUrl)) {
            $relative = self::convertToRelative($dynamicUrl);
            self::$resolved_urls[$hrefId] = $relative;
            return $relative;
        }

        return $staticHref;
    }

    /**
     * Convert absolute URL to relative URL.
     */
    public static function convertToRelative($url) 
    {
        if (empty($url)) {
            return '';
        }

        $home_url = home_url();
        
        // Strip home URL prefix if matched
        if (strpos($url, $home_url) === 0) {
            $url = substr($url, strlen($home_url));
        }

        // Handle fallback if scheme (http/https) or port differs
        $parsed_home = wp_parse_url($home_url);
        $parsed_url = wp_parse_url($url);
        
        if (isset($parsed_url['host']) && isset($parsed_home['host']) && $parsed_url['host'] === $parsed_home['host']) {
            $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
            $query = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
            $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
            $url = $path . $query . $fragment;
        }

        // Ensure path starts with '/'
        if ($url && $url[0] !== '/' && $url[0] !== '?' && $url[0] !== '#') {
            $url = '/' . $url;
        }

        return $url;
    }

    /**
     * Check if current query is a REST API search request.
     */
    private static function isRestSearchRequest($query) 
    {
        return defined('REST_REQUEST') && REST_REQUEST && $query->is_search() && $query->get('s');
    }

    /**
     * Build exact match ordering SQL expression.
     */
    private static function buildExactMatchOrderSql($orderby, $query) 
    {
        global $wpdb;
        $searchTerm = $query->get('s');
        
        $slugTerm = sanitize_title(ltrim($searchTerm, '/'));
        $exactTerm = $searchTerm;
        $startTerm = $wpdb->esc_like($searchTerm) . '%';
        $likeTerm = '%' . $wpdb->esc_like($searchTerm) . '%';

        $customOrder = $wpdb->prepare(
            "CASE 
                WHEN {$wpdb->posts}.post_name = %s THEN 1
                WHEN {$wpdb->posts}.post_title = %s THEN 2
                WHEN {$wpdb->posts}.post_title LIKE %s THEN 3
                WHEN {$wpdb->posts}.post_title LIKE %s THEN 4
                ELSE 5
            END ASC, ",
            $slugTerm,
            $exactTerm,
            $startTerm,
            $likeTerm
        );

        return $customOrder . ($orderby ? $orderby : "{$wpdb->posts}.post_date DESC");
    }

    /**
     * Clean search keywords (remove domain, slashes, query params, hash).
     */
    private static function cleanSearchTerm($searchTerm) 
    {
        if (strpos($searchTerm, 'http://') === 0 || strpos($searchTerm, 'https://') === 0 || strpos($searchTerm, '//') === 0) {
            $path = wp_parse_url($searchTerm, PHP_URL_PATH);
            if ($path) {
                $searchTerm = $path;
            }
        }

        $searchTerm = trim($searchTerm, '/');

        if (($pos = strpos($searchTerm, '?')) !== false) {
            $searchTerm = substr($searchTerm, 0, $pos);
        }
        if (($pos = strpos($searchTerm, '#')) !== false) {
            $searchTerm = substr($searchTerm, 0, $pos);
        }

        return trim($searchTerm);
    }
}

AlignSearchOptimizer::init();
