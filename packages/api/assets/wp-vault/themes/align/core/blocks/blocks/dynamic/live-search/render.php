<?php
if (!defined('ABSPATH')) {
    exit;
}

function align_live_search_render_callback($attributes) {
    $placeholder    = isset($attributes['placeholder'])   ? esc_attr($attributes['placeholder'])   : 'Search...';
    $search_page    = isset($attributes['searchPageUrl']) && !empty($attributes['searchPageUrl'])
                      ? esc_url($attributes['searchPageUrl'])
                      : home_url('/');
    $class_name     = isset($attributes['className'])     ? esc_attr($attributes['className'])     : '';
    $enable_live    = !isset($attributes['enableLiveSearch']) || (bool) $attributes['enableLiveSearch'];
    $allow_toggle   = $enable_live && (!isset($attributes['allowModeToggle']) || (bool) $attributes['allowModeToggle']);
    $default_mode   = $enable_live && isset($attributes['defaultMode']) ? esc_attr($attributes['defaultMode']) : ($enable_live ? 'live' : 'normal');
    $attrs_json     = esc_attr(wp_json_encode($attributes));
    $ajax_url       = esc_url(admin_url('admin-ajax.php'));
    $rest_url       = esc_url(get_rest_url(null, '/align/v1/live-search'));
    $nonce          = wp_create_nonce('align_live_search_nonce');

    $enable_cat_filter = !empty($attributes['enableCategoryFilter']);
    $cat_tax           = !empty($attributes['categoryFilterTaxonomy']) ? sanitize_key($attributes['categoryFilterTaxonomy']) : 'category';
    $all_label         = !empty($attributes['categoryFilterAllLabel']) ? esc_html($attributes['categoryFilterAllLabel']) : __('All Categories', 'align-function');
    $filter_terms      = !empty($attributes['categoryFilterTerms']) ? trim($attributes['categoryFilterTerms']) : 'all';

    $categories = array();
    if ($enable_cat_filter && taxonomy_exists($cat_tax)) {
        $term_args = array(
            'taxonomy'   => $cat_tax,
            'hide_empty' => false,
            'number'     => 100,
        );
        if ($filter_terms !== 'all' && !empty($filter_terms)) {
            $term_ids = array_filter(array_map('intval', explode(',', $filter_terms)));
            if (!empty($term_ids)) {
                $term_args['include'] = $term_ids;
            }
        }
        $terms_result = get_terms($term_args);
        if (!is_wp_error($terms_result) && !empty($terms_result)) {
            $categories = $terms_result;
        }
    }

    // Add extra class if toggle is disabled or live search is turned off or has category filter
    $toggle_class = (!$allow_toggle ? 'has-no-toggle' : '')
                  . (!$enable_live ? ' is-normal-only' : '')
                  . ($enable_cat_filter && !empty($categories) ? ' has-category-filter' : '');

    ob_start();
    ?>
    <div class="align-live-search <?php echo $class_name; ?> <?php echo trim($toggle_class); ?>"
         data-attributes="<?php echo $attrs_json; ?>"
         data-ajax-url="<?php echo $ajax_url; ?>"
         data-rest-url="<?php echo $rest_url; ?>"
         data-enable-live="<?php echo $enable_live ? 'true' : 'false'; ?>"
         data-default-mode="<?php echo $default_mode; ?>"
         data-nonce="<?php echo esc_attr($nonce); ?>"
         data-search-page="<?php echo $search_page; ?>"
    >
        <form class="align-ls-form" method="get" action="<?php echo $search_page; ?>" role="search">
            <div class="align-ls-input-wrap">
                <input
                    type="search"
                    name="s"
                    class="align-ls-input"
                    placeholder="<?php echo $placeholder; ?>"
                    autocomplete="off"
                    aria-label="<?php echo $placeholder; ?>"
                />
                <?php if ($enable_cat_filter && !empty($categories)) : ?>
                <div class="align-ls-category-wrap" data-taxonomy="<?php echo esc_attr($cat_tax); ?>">
                    <input type="hidden" name="<?php echo esc_attr($cat_tax === 'category' ? 'cat' : $cat_tax); ?>" class="align-ls-category-input" value="" />
                    <button type="button" class="align-ls-category-toggle" aria-haspopup="listbox" aria-expanded="false" aria-label="<?php echo esc_attr($all_label); ?>">
                        <span class="align-ls-category-current"><?php echo esc_html($all_label); ?></span>
                        <svg class="align-ls-category-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                    <div class="align-ls-category-menu" data-lenis-prevent="true" data-lenis-prevent-wheel="true" data-lenis-prevent-touch="true" role="listbox" aria-label="<?php echo esc_attr($all_label); ?>">
                        <div class="align-ls-category-item is-selected" role="option" aria-selected="true" data-value="" data-slug="">
                            <span class="align-ls-category-name"><?php echo esc_html($all_label); ?></span>
                        </div>
                        <?php foreach ($categories as $cat) : ?>
                            <div class="align-ls-category-item" role="option" aria-selected="false" data-value="<?php echo esc_attr($cat->term_id); ?>" data-slug="<?php echo esc_attr($cat->slug); ?>">
                                <span class="align-ls-category-name"><?php echo esc_html($cat->name); ?></span>
                                <?php if (isset($cat->count) && $cat->count > 0) : ?>
                                    <span class="align-ls-category-count"><?php echo intval($cat->count); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($allow_toggle) : ?>
                <button type="button" class="align-ls-mode-toggle is-live" aria-label="Toggle Search Mode" title="Switch between Live and Normal search">
                    <span class="align-ls-mode-indicator"></span>
                    <span class="align-ls-mode-text">Live</span>
                </button>
                <?php endif; ?>
                <button type="submit" class="align-ls-submit" aria-label="Search">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                </button>
            </div>
            <div class="align-ls-dropdown" data-lenis-prevent="true" data-lenis-prevent-wheel="true" data-lenis-prevent-touch="true" role="listbox" aria-label="Search suggestions" aria-live="polite"></div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
