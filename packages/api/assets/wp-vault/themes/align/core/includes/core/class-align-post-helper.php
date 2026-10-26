<?php
/**
 * Helper class to resolve post categories or custom post types.
 *
 * @package Align
 */

if (!defined('ABSPATH')) {
    exit;
}

class AlignPostHelper {
    /**
     * Runtime cache to avoid duplicate queries within the same page load.
     * @var array
     */
    private static $cache = [];

    /**
     * Initialize class hooks and register shortcodes.
     */
    public static function init() {
        add_shortcode('align_post_category_or_type', [self::class, 'renderShortcode']);

        // Clear transient cache in admin panel to pick up new pattern files
        if (is_admin()) {
            add_action('admin_init', function() {
                delete_transient('wp_theme_files_patterns_' . get_stylesheet());
            });
        }

        // Inject the post category or post type slug as a CSS class name
        add_filter('render_block', [self::class, 'injectClassIntoCategoryBadge'], 10, 2);
    }

    /**
     * Renders the shortcode [align_post_category_or_type]
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public static function renderShortcode($atts = []): string {
        return esc_html(self::getPostCategoryOrType());
    }

    /**
     * Resolves the primary category, custom taxonomy term, or post type label.
     *
     * @param int|WP_Post|null $postId Post ID or object.
     * @return string Resolved label.
     */
    public static function getPostCategoryOrType($postId = null): string {
        $postId = ($postId instanceof WP_Post) ? $postId->ID : ($postId ? intval($postId) : get_the_ID());
        if (!$postId) {
            // Provide a visual placeholder for the block editor/admin context
            if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
                $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
                
                // Check if editing a specific custom post type template
                if (preg_match('/template-([a-zA-Z0-9_-]+)/', $referer, $matches)) {
                    $cpt = $matches[1];
                    if ($cpt === 'single') {
                        return __('Category', 'align');
                    }
                    return self::getPostTypeLabel($cpt);
                }
                
                // Check if post type is in query parameters
                if (isset($_GET['post_type'])) {
                    return self::getPostTypeLabel(sanitize_text_field($_GET['post_type']));
                }
                
                return __('Category', 'align');
            }
            return '';
        }

        // Return from memory cache if already resolved
        if (isset(self::$cache[$postId])) {
            return self::$cache[$postId];
        }

        $post_type = get_post_type($postId);
        if (!$post_type) {
            return '';
        }

        $label = '';

        if ($post_type === 'post') {
            // Standard category for posts
            $label = self::getPrimaryTerm($postId, 'category');
        } elseif ($post_type === 'page') {
            // Standard label for pages
            $label = __('Page', 'align');
        } else {
            // Custom Post Type - search for registered custom taxonomies
            $taxonomies = get_object_taxonomies($post_type);
            if (!empty($taxonomies)) {
                $custom_tax = array_filter($taxonomies, function ($tax) {
                    return !in_array($tax, ['post_format'], true);
                });

                if (!empty($custom_tax)) {
                    $primary_tax = reset($custom_tax);
                    $label = self::getPrimaryTerm($postId, $primary_tax);
                }
            }

            // Fallback to the CPT label if no taxonomy terms are found
            if (empty($label)) {
                $label = self::getPostTypeLabel($post_type);
            }
        }

        self::$cache[$postId] = $label;
        return $label;
    }

    /**
     * Resolves the primary term object using Yoast or Rank Math, with a fallback to the first term.
     *
     * @param int $postId
     * @param string $taxonomy
     * @return WP_Term|null
     */
    public static function getPrimaryTermObject($postId, string $taxonomy) {
        // 1. Try Yoast SEO primary term
        if (class_exists('WPSEO_Primary_Term')) {
            $wpseo_primary_term = new WPSEO_Primary_Term($taxonomy, $postId);
            $primary_term_id = $wpseo_primary_term->get_primary_term();
            if ($primary_term_id) {
                $term = get_term($primary_term_id);
                if ($term && !is_wp_error($term)) {
                    return $term;
                }
            }
        }

        // 2. Try Rank Math SEO primary term
        $rank_math_primary = get_post_meta($postId, "rank_math_primary_{$taxonomy}", true);
        if ($rank_math_primary) {
            $term = get_term($rank_math_primary);
            if ($term && !is_wp_error($term)) {
                return $term;
            }
        }

        // 3. Fallback to first available term
        $terms = get_the_terms($postId, $taxonomy);
        if (!empty($terms) && !is_wp_error($terms)) {
            return reset($terms);
        }

        return null;
    }

    /**
     * Resolves the primary term name using Yoast or Rank Math fallback.
     */
    private static function getPrimaryTerm($postId, string $taxonomy): string {
        $term = self::getPrimaryTermObject($postId, $taxonomy);
        return $term ? $term->name : '';
    }

    /**
     * Resolves label from custom post type configuration.
     */
    private static function getPostTypeLabel(string $postType): string {
        $cpt_object = get_post_type_object($postType);
        if ($cpt_object && isset($cpt_object->labels->singular_name)) {
            return $cpt_object->labels->singular_name;
        }
        return ucwords(str_replace(['-', '_'], ' ', $postType));
    }

    /**
     * Resolves the primary category, custom taxonomy term, or post type slug.
     *
     * @param int|WP_Post|null $postId Post ID or object.
     * @return string Resolved slug.
     */
    public static function getPostCategoryOrTypeSlug($postId = null): string {
        $postId = ($postId instanceof WP_Post) ? $postId->ID : ($postId ? intval($postId) : get_the_ID());
        if (!$postId) {
            // Provide a visual placeholder/slug for the block editor/admin context
            if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
                $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
                
                // Check if editing a specific custom post type template
                if (preg_match('/template-([a-zA-Z0-9_-]+)/', $referer, $matches)) {
                    $cpt = $matches[1];
                    if ($cpt === 'single') {
                        return 'category';
                    }
                    return sanitize_html_class($cpt);
                }
                
                // Check if post type is in query parameters
                if (isset($_GET['post_type'])) {
                    return sanitize_html_class(sanitize_text_field($_GET['post_type']));
                }
                
                return 'category';
            }
            return '';
        }

        $post_type = get_post_type($postId);
        if (!$post_type) {
            return '';
        }

        $slug = '';

        if ($post_type === 'post') {
            // Standard category for posts
            $term = self::getPrimaryTermObject($postId, 'category');
            $slug = $term ? $term->slug : '';
        } elseif ($post_type === 'page') {
            // Standard slug for pages
            $slug = 'page';
        } else {
            // Custom Post Type - search for registered custom taxonomies
            $taxonomies = get_object_taxonomies($post_type);
            if (!empty($taxonomies)) {
                $custom_tax = array_filter($taxonomies, function ($tax) {
                    return !in_array($tax, ['post_format'], true);
                });

                if (!empty($custom_tax)) {
                    $primary_tax = reset($custom_tax);
                    $term = self::getPrimaryTermObject($postId, $primary_tax);
                    $slug = $term ? $term->slug : '';
                }
            }

            // Fallback to the CPT name if no taxonomy terms are found
            if (empty($slug)) {
                $slug = $post_type;
            }
        }

        return sanitize_html_class($slug);
    }

    /**
     * Injects the category or post type slug as a CSS class name into the paragraph block content.
     *
     * @param string $blockContent The block HTML content.
     * @param array  $block        The parsed block representation.
     * @return string Modified block HTML content.
     */
    public static function injectClassIntoCategoryBadge(string $blockContent, array $block): string {
        if ($block['blockName'] === 'core/paragraph' || $block['blockName'] === 'align/element') {
            $isBadge = (
                strpos($blockContent, 'align-post-category-badge') !== false ||
                strpos($blockContent, 'post-cat') !== false ||
                strpos($blockContent, '[align_post_category_or_type]') !== false ||
                (
                    strpos($blockContent, 'inline-flex items-center justify-center') !== false &&
                    strpos($blockContent, 'tracking-[1.1px]') !== false &&
                    strpos($blockContent, 'border-[#00000033]') !== false
                )
            );

            if ($isBadge) {
                // Find the opening tag and its class attribute
                if (preg_match('/<([a-zA-Z0-9]+)\s+[^>]*class=["\']([^"\']*)["\']/', $blockContent, $matches)) {
                    $classes = $matches[2];
                    $classArray = explode(' ', $classes);
                    $newClassesArray = $classArray;

                    // 1. Add post type/category slug
                    $slug = self::getPostCategoryOrTypeSlug();
                    if ($slug && !in_array($slug, $newClassesArray, true)) {
                        $newClassesArray[] = $slug;
                    }

                    // 2. Ensure post-cat is present
                    if (!in_array('post-cat', $newClassesArray, true)) {
                        $newClassesArray[] = 'post-cat';
                    }

                    // 3. Ensure align-post-category-badge is present
                    if (!in_array('align-post-category-badge', $newClassesArray, true)) {
                        $newClassesArray[] = 'align-post-category-badge';
                    }

                    $newClasses = implode(' ', array_filter($newClassesArray));
                    if ($newClasses !== $classes) {
                        $blockContent = str_replace('class="' . $classes . '"', 'class="' . $newClasses . '"', $blockContent);
                        $blockContent = str_replace("class='" . $classes . "'", "class='" . $newClasses . "'", $blockContent);
                    }
                }
            }
        }
        return $blockContent;
    }

    /**
     * Check if Rank Math plugin is active.
     *
     * @return bool
     */
    public static function isRankMathActive(): bool {
        return class_exists('RankMath') || defined('RANK_MATH_VERSION') || class_exists('\RankMath\Helper');
    }

    /**
     * Check if Advanced Custom Fields (ACF) plugin is active.
     *
     * @return bool
     */
    public static function isAcfActive(): bool {
        return class_exists('ACF') || function_exists('get_field');
    }

    /**
     * Get post title based on source ('default', 'acf', 'rank_math').
     *
     * @param int|WP_Post|null $postId
     * @param string $source Data source: 'default', 'acf', or 'rank_math'
     * @param string $acfSlug ACF field slug
     * @return string
     */
    public static function getPostTitle($postId = null, string $source = 'default', string $acfSlug = ''): string {
        $postId = ($postId instanceof WP_Post) ? $postId->ID : ($postId ? intval($postId) : get_the_ID());
        if (!$postId) {
            return '';
        }

        if ($source === 'acf' && !empty($acfSlug)) {
            $value = function_exists('get_field') ? get_field($acfSlug, $postId) : get_post_meta($postId, $acfSlug, true);
            if (!empty($value)) {
                return is_array($value) ? implode(', ', $value) : wp_strip_all_tags(strval($value));
            }
        } elseif ($source === 'rank_math') {
            $rm_title = get_post_meta($postId, 'rank_math_title', true);
            if (!empty($rm_title)) {
                if (class_exists('\RankMath\Helper') && method_exists('\RankMath\Helper', 'replace_vars')) {
                    $post_obj = get_post($postId);
                    $parsed = \RankMath\Helper::replace_vars($rm_title, $post_obj);
                    if (!empty($parsed)) {
                        return wp_strip_all_tags($parsed);
                    }
                }
                if (class_exists('Align_SEO_Helper')) {
                    $parsed = Align_SEO_Helper::parse_variables($rm_title, $postId);
                    if (!empty($parsed)) {
                        return wp_strip_all_tags($parsed);
                    }
                }
                return wp_strip_all_tags($rm_title);
            }
        }

        return get_the_title($postId);
    }

    /**
     * Get post excerpt based on source ('default', 'acf', 'rank_math').
     *
     * @param int|WP_Post|null $postId
     * @param string $source Data source: 'default', 'acf', or 'rank_math'
     * @param string $acfSlug ACF field slug
     * @param int $length Trim length for default fallback
     * @return string
     */
    public static function getPostExcerpt($postId = null, string $source = 'default', string $acfSlug = '', int $length = 15): string {
        $postId = ($postId instanceof WP_Post) ? $postId->ID : ($postId ? intval($postId) : get_the_ID());
        if (!$postId) {
            return '';
        }

        if ($source === 'acf' && !empty($acfSlug)) {
            $value = function_exists('get_field') ? get_field($acfSlug, $postId) : get_post_meta($postId, $acfSlug, true);
            if (!empty($value)) {
                return is_array($value) ? implode(' ', $value) : wp_strip_all_tags(strval($value));
            }
        } elseif ($source === 'rank_math') {
            $rm_desc = get_post_meta($postId, 'rank_math_description', true);
            if (!empty($rm_desc)) {
                if (class_exists('\RankMath\Helper') && method_exists('\RankMath\Helper', 'replace_vars')) {
                    $post_obj = get_post($postId);
                    $parsed = \RankMath\Helper::replace_vars($rm_desc, $post_obj);
                    if (!empty($parsed)) {
                        return wp_strip_all_tags($parsed);
                    }
                }
                if (class_exists('Align_SEO_Helper')) {
                    $parsed = Align_SEO_Helper::parse_variables($rm_desc, $postId);
                    if (!empty($parsed)) {
                        return wp_strip_all_tags($parsed);
                    }
                }
                return wp_strip_all_tags($rm_desc);
            }
        }

        return wp_trim_words(get_the_excerpt($postId), $length, '...');
    }

    /**
     * Get post date based on source ('default', 'acf', 'rank_math').
     *
     * @param int|WP_Post|null $postId
     * @param string $source Data source: 'default', 'acf', or 'rank_math'
     * @param string $acfSlug ACF field slug
     * @param string $format Date format (e.g. 'F j, Y')
     * @return string
     */
    public static function getPostDate($postId = null, string $source = 'default', string $acfSlug = '', string $format = 'F j, Y'): string {
        $postId = ($postId instanceof WP_Post) ? $postId->ID : ($postId ? intval($postId) : get_the_ID());
        if (!$postId) {
            return '';
        }

        if ($source === 'acf' && !empty($acfSlug)) {
            $value = function_exists('get_field') ? get_field($acfSlug, $postId) : get_post_meta($postId, $acfSlug, true);
            if (!empty($value)) {
                if (is_numeric($value)) {
                    return wp_date($format, intval($value));
                }
                $timestamp = strtotime($value);
                if ($timestamp !== false && $timestamp > 0) {
                    return wp_date($format, $timestamp);
                }
                return wp_strip_all_tags(strval($value));
            }
        }

        return get_the_date($format, $postId);
    }
}
