<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('align_query_builder_render_pagination_html')) {
    function align_query_builder_render_pagination_html($query, $attributes, $paged = 1) {
        if (!$query || $query->max_num_pages <= 1) {
            return '';
        }
        $dataSource = $attributes['dataSource'] ?? 'post';
        $displayMode = $attributes['displayMode'] ?? 'slider';
        $paginationType = $attributes['paginationType'] ?? 'none';

        if ($dataSource !== 'post' || $displayMode !== 'grid' || $paginationType === 'none') {
            return '';
        }

        ob_start();
        echo '<div class="align-qb-pagination" data-max-pages="' . esc_attr($query->max_num_pages) . '" data-current-page="' . esc_attr($paged) . '">';
        if ($paginationType === 'numbers') {
            echo paginate_links(array(
                'base' => '%_%',
                'format' => '?paged=%#%',
                'total' => $query->max_num_pages,
                'current' => $paged,
                'prev_text' => '&laquo; Prev',
                'next_text' => 'Next &raquo;',
            ));
        } elseif ($paginationType === 'load_more') {
            echo '<button class="align-qb-load-more-btn qb-load-more-btn">Load More</button>';
        } elseif ($paginationType === 'infinite') {
            echo '<div class="align-qb-infinite-trigger qb-infinite-trigger">
                <svg class="align-spinner" viewBox="0 0 50 50" aria-label="Loading">
                    <circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
                </svg>
            </div>';
        }
        echo '</div>';
        return ob_get_clean();
    }
}

function align_query_builder_render_callback($attributes, $content, $block = null) {
    $dataSource = $attributes['dataSource'] ?? 'post';
    $displayMode = $attributes['displayMode'] ?? 'slider';
    $gridColumns = $attributes['gridColumns'] ?? 3;
    $slidesPerView = $attributes['slidesPerView'] ?? '1';
    $slidesPerViewTablet = $attributes['slidesPerViewTablet'] ?? '1';
    $slidesPerViewMobile = $attributes['slidesPerViewMobile'] ?? '1';
    $loop = isset($attributes['loop']) ? filter_var($attributes['loop'], FILTER_VALIDATE_BOOLEAN) : true;
    $arrows = isset($attributes['arrows']) ? filter_var($attributes['arrows'], FILTER_VALIDATE_BOOLEAN) : true;
    $showDots = isset($attributes['showDots']) ? filter_var($attributes['showDots'], FILTER_VALIDATE_BOOLEAN) : true;
    $hookNavigator = isset($attributes['hookNavigator']) ? filter_var($attributes['hookNavigator'], FILTER_VALIDATE_BOOLEAN) : false;
    $nextSelector = $attributes['nextSelector'] ?? '';
    $prevSelector = $attributes['prevSelector'] ?? '';
    $className = $attributes['className'] ?? '';
    $isAjax = $attributes['isAjax'] ?? false;
    $paginationType = $attributes['paginationType'] ?? 'none';
    $paged = isset($attributes['paged']) ? intval($attributes['paged']) : max(1, intval(get_query_var('paged')), intval(get_query_var('page')));

    $enableSliderInit = isset($attributes['enableSliderInit']) ? filter_var($attributes['enableSliderInit'], FILTER_VALIDATE_BOOLEAN) : false;
    $useCustomDotLabels = isset($attributes['useCustomDotLabels']) ? filter_var($attributes['useCustomDotLabels'], FILTER_VALIDATE_BOOLEAN) : false;
    $dotLabels = $attributes['dotLabels'] ?? '';

    $wrapperAttrs = '';
    if ($displayMode === 'slider') {
        if ($enableSliderInit) {
            $wrapperClasses = trim("splide align-splide-slider align-qb-wrap align-qb-wrap--slider {$className}");
        } else {
            $wrapperClasses = trim("align-qb-wrap align-qb-wrap--slider {$className}");
        }
        // Slider doesn't need specific inline style here
    } else {
        $wrapperClasses = trim("align-qb-wrap align-qb-wrap--grid {$className}");
        $wrapperAttrs = 'data-qb-grid-cols="' . intval($gridColumns) . '"';
    }

    $splideAttrs = '';
    if ($displayMode === 'slider' && $enableSliderInit) {
        $sliderId = 'qb-slider-' . wp_generate_password(8, false, false);
        $splideConfig = array(
            'slidesPerView' => is_numeric($slidesPerView) ? (float)$slidesPerView : ($slidesPerView === 'auto' ? 'auto' : 1),
            'slidesPerViewTablet' => is_numeric($slidesPerViewTablet) ? (float)$slidesPerViewTablet : ($slidesPerViewTablet === 'auto' ? 'auto' : 1),
            'slidesPerViewMobile' => is_numeric($slidesPerViewMobile) ? (float)$slidesPerViewMobile : ($slidesPerViewMobile === 'auto' ? 'auto' : 1),
            'loop' => (bool)$loop,
            'navigation' => (bool)$arrows,
            'showDots' => (bool)$showDots,
            'spaceBetween' => isset($attributes['spaceBetween']) ? intval($attributes['spaceBetween']) : 20,
            'transitionEffect' => $attributes['transitionEffect'] ?? 'fade',
            'hookNavigator' => (bool)$hookNavigator,
            'autoplayDelay' => isset($attributes['autoplayDelay']) ? intval($attributes['autoplayDelay']) : 0,
            'nextSelector' => $nextSelector,
            'prevSelector' => $prevSelector,
            'useCustomDotLabels' => !empty($attributes['useCustomDotLabels']),
            'dotLabels' => isset($attributes['dotLabels']) ? $attributes['dotLabels'] : '',
            'videoLazyload' => !isset($attributes['videoLazyload']) || filter_var($attributes['videoLazyload'], FILTER_VALIDATE_BOOLEAN),
        );
        $script = 'window.alignSliderConfigs = window.alignSliderConfigs || {}; window.alignSliderConfigs["' . esc_js($sliderId) . '"] = ' . json_encode($splideConfig) . ';';
        wp_add_inline_script('align-query-builder-frontend', $script);
        $splideAttrs = 'data-slider-id="' . esc_attr($sliderId) . '"';
    }

    ob_start();
    
    // Only output wrapper if not AJAX
    if (!$isAjax) {
        if (isset($attributes['queryRelatedPosts']) && filter_var($attributes['queryRelatedPosts'], FILTER_VALIDATE_BOOLEAN)) {
            $attributes['currentPostId'] = ($block && isset($block->context['postId'])) ? intval($block->context['postId']) : get_the_ID();
        }
        $containerAttrs = '';
        if ($displayMode === 'grid') {
            $dataAttrs = "data-attributes='" . esc_attr(json_encode($attributes)) . "'";
            $ajaxUrl = esc_url(admin_url('admin-ajax.php'));
            $containerAttrs = ' data-ajax-url="' . $ajaxUrl . '" ' . $dataAttrs;
        }
        ?>
        <div class="align-qb-container align-content"<?php echo $containerAttrs; ?>>
            <?php if ($displayMode === 'grid' && !empty($attributes['enableSearch'])): 
                $searchPlaceholder = !empty($attributes['searchPlaceholder']) ? $attributes['searchPlaceholder'] : 'Search...';
                $searchDebounce = isset($attributes['searchDebounce']) ? intval($attributes['searchDebounce']) : 400;
            ?>
                <div class="align-qb-search-wrap" data-debounce="<?php echo esc_attr($searchDebounce); ?>">
                    <div class="align-qb-search-input-container">
                        <input type="text" class="align-qb-search-input" placeholder="<?php echo esc_attr($searchPlaceholder); ?>" aria-label="<?php echo esc_attr($searchPlaceholder); ?>" autocomplete="off" />
                        <svg class="align-qb-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                </div>
            <?php endif; ?>
            <div class="<?php echo esc_attr($wrapperClasses); ?>" <?php echo $splideAttrs; ?> <?php echo $wrapperAttrs; ?>>
                <?php if ($displayMode === 'slider'): ?>
                    <div class="splide__track">
                        <ul class="splide__list">
                <?php endif; ?>
        <?php 
    } 
    ?>
            <?php
            if ($dataSource === 'post') {
                $hasSelectedPostTypes = array_key_exists('selectedPostTypes', $attributes);
                $selectedPostTypes = $attributes['selectedPostTypes'] ?? [];

                if (!$hasSelectedPostTypes) {
                    $legacyPostType = $attributes['postType'] ?? 'post';
                    if ($legacyPostType === 'custom') {
                        $customType = $attributes['customPostType'] ?? 'post';
                        $selectedPostTypes = array_filter(array_map('trim', explode(',', $customType)));
                    } elseif ($legacyPostType === 'any') {
                        $selectedPostTypes = array('all');
                    } else {
                        $selectedPostTypes = array($legacyPostType);
                    }
                }

                $public_post_types = get_post_types(array('public' => true), 'names');
                unset($public_post_types['attachment']);

                if (in_array('all', $selectedPostTypes)) {
                    $selectedPostTypes = array_values($public_post_types);
                }

                $postType = $selectedPostTypes;
                
                $postsPerPage = $attributes['postsPerPage'] ?? 4;
                
                $queryOrderBy = $attributes['queryOrderBy'] ?? 'date';
                $queryOrder = $attributes['queryOrder'] ?? 'DESC';
                
                $postCardStyle = $attributes['postCardStyle'] ?? 'overlay';
                $fallbackImageId = $attributes['fallbackImageId'] ?? 0;
                $fallbackImageUrl = $attributes['fallbackImageUrl'] ?? '';
                $showTitle = isset($attributes['showPostTitle']) ? $attributes['showPostTitle'] : true;
                $showExcerpt = $attributes['showPostExcerpt'] ?? false;
                $showDate = $attributes['showPostDate'] ?? false;
                $showCategory = $attributes['showPostCategory'] ?? false;
                $allowed_tags = array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span');
                $titleTag = in_array($attributes['titleTag'] ?? 'h3', $allowed_tags) ? $attributes['titleTag'] : 'h3';
                $excerptTag = in_array($attributes['excerptTag'] ?? 'p', $allowed_tags) ? $attributes['excerptTag'] : 'p';
                $dateFormat = $attributes['dateFormat'] ?? 'F j, Y';
                $customDateFormat = $attributes['customDateFormat'] ?? '';
                $showReadMore = isset($attributes['showReadMore']) ? $attributes['showReadMore'] : true;
                $readMoreText = $attributes['readMoreText'] ?? 'Read More';
                $readMoreIconType = $attributes['readMoreIconType'] ?? 'arrow';
                $readMoreCustomSvg = $attributes['readMoreCustomSvg'] ?? '';
                $filterTaxonomy = $attributes['filterTaxonomy'] ?? '';
                $filterTermIds = $attributes['filterTermIds'] ?? '';
                $searchKeyword = $attributes['searchKeyword'] ?? '';
                $linkWholeContainer = isset($attributes['linkWholeContainer']) ? filter_var($attributes['linkWholeContainer'], FILTER_VALIDATE_BOOLEAN) : false;
                $titleSource = $attributes['titleSource'] ?? 'default';
                $titleAcfSlug = $attributes['titleAcfSlug'] ?? '';
                $excerptSource = $attributes['excerptSource'] ?? 'default';
                $excerptAcfSlug = $attributes['excerptAcfSlug'] ?? '';
                $dateSource = $attributes['dateSource'] ?? 'default';
                $dateAcfSlug = $attributes['dateAcfSlug'] ?? '';

                $query = null;
                if (!empty($postType)) {
                    $args = array(
                        'post_type' => $postType,
                        'posts_per_page' => $postsPerPage,
                        'post_status' => 'publish',
                        'orderby' => $queryOrderBy,
                        'order' => $queryOrder,
                        'paged' => $paged,
                        'ignore_sticky_posts' => true,
                    );

                    if (!empty($searchKeyword)) {
                        $args['s'] = sanitize_text_field($searchKeyword);
                    }

                    // Fetch related posts based on current post ID if option is enabled
                    if (isset($attributes['queryRelatedPosts']) && filter_var($attributes['queryRelatedPosts'], FILTER_VALIDATE_BOOLEAN)) {
                        $current_post_id = null;
                        if ($isAjax && isset($attributes['currentPostId'])) {
                            $current_post_id = intval($attributes['currentPostId']);
                        } elseif ($block && isset($block->context['postId'])) {
                            $current_post_id = intval($block->context['postId']);
                        } else {
                            $current_post_id = get_the_ID();
                        }

                        if ($current_post_id) {
                            $args['post__not_in'] = array($current_post_id);
                            
                            // Override post type to match the current post's post type
                            $current_post_type = null;
                            if ($block && isset($block->context['postType'])) {
                                $current_post_type = $block->context['postType'];
                            } else {
                                $current_post_type = get_post_type($current_post_id);
                            }
                            
                            if ($current_post_type) {
                                $args['post_type'] = $current_post_type;
                            }
                            
                            $taxonomies = get_post_taxonomies($current_post_id);
                            $tax_query = array('relation' => 'OR');
                            
                            foreach ($taxonomies as $taxonomy) {
                                if ($taxonomy === 'post_format') {
                                    continue;
                                }
                                $terms = wp_get_post_terms($current_post_id, $taxonomy, array('fields' => 'ids'));
                                if (!is_wp_error($terms) && !empty($terms)) {
                                    $tax_query[] = array(
                                        'taxonomy' => $taxonomy,
                                        'field'    => 'term_id',
                                        'terms'    => $terms,
                                    );
                                }
                            }
                            
                            if (count($tax_query) > 1) {
                                $args['tax_query'] = $tax_query;
                            }
                        }
                    } elseif (!empty($filterTaxonomy) && !empty($filterTermIds)) {
                        $term_ids = array_filter(array_map('intval', explode(',', $filterTermIds)));
                        if (!empty($term_ids)) {
                            $args['tax_query'] = array(
                                array(
                                    'taxonomy' => trim($filterTaxonomy),
                                    'field'    => 'term_id',
                                    'terms'    => $term_ids,
                                ),
                            );
                        }
                    }

                    $query = new WP_Query($args);

                    // Fallback: If no related posts found with matching terms, query recent posts of the same post type instead
                    if (!$query->have_posts() && isset($args['tax_query']) && isset($attributes['queryRelatedPosts']) && filter_var($attributes['queryRelatedPosts'], FILTER_VALIDATE_BOOLEAN)) {
                        unset($args['tax_query']);
                        $query = new WP_Query($args);
                    }

                    $GLOBALS['align_qb_last_max_pages'] = $query->max_num_pages;
                    $GLOBALS['align_qb_last_pagination_html'] = align_query_builder_render_pagination_html($query, $attributes, $paged);
                }

                if ($query && $query->have_posts()) {
                    while ($query->have_posts()) {
                        $query->the_post();
                        $post_id = get_the_ID();
                        $permalink = get_permalink();
                        $title = AlignPostHelper::getPostTitle($post_id, $titleSource, $titleAcfSlug);
                        $excerpt = AlignPostHelper::getPostExcerpt($post_id, $excerptSource, $excerptAcfSlug, 15);
                        
                        $format = ($dateFormat === 'custom') ? $customDateFormat : $dateFormat;
                        $format = $format ? $format : get_option('date_format');
                        $date = AlignPostHelper::getPostDate($post_id, $dateSource, $dateAcfSlug, $format);
                        
                        $badge_name = '';
                        $badge_tax  = '';
                        $badge_link = '';
                        $post_tags  = []; // All terms for pill display
                        if ($showCategory) {
                            $taxonomies = get_post_taxonomies($post_id);
                            if (!empty($taxonomies)) {
                                foreach ($taxonomies as $tax) {
                                    if ($tax === 'post_format') continue;
                                    $terms = get_the_terms($post_id, $tax);
                                    if ($terms && !is_wp_error($terms)) {
                                        // First term of first taxonomy = main badge
                                        if (empty($badge_name)) {
                                            $first_term = reset($terms);
                                            $badge_name = $first_term->name;
                                            $badge_tax  = $tax;
                                            $badge_link = get_term_link($first_term);
                                            if (is_wp_error($badge_link)) $badge_link = '';
                                        }
                                        // Collect all terms as pills
                                        foreach ($terms as $term) {
                                            $tlink = get_term_link($term);
                                            $post_tags[] = [
                                                'name' => $term->name,
                                                'link' => is_wp_error($tlink) ? '' : $tlink,
                                            ];
                                        }
                                    }
                                }
                            }
                        }

                        
                        // Picture tags logic for post thumbnail
                        $image_html = '';
                        if ($postCardStyle !== 'no-image') {
                            $thumbnail_id = get_post_thumbnail_id($post_id);
                            if (!$thumbnail_id && !empty($fallbackImageId)) {
                                $thumbnail_id = intval($fallbackImageId);
                            }

                            $img_desktop = '';
                            $img_tablet = '';
                            $img_mobile = '';
                            $alt = '';

                            if ($thumbnail_id) {
                                $img_desktop = wp_get_attachment_image_url($thumbnail_id, 'full');
                                $img_tablet = wp_get_attachment_image_url($thumbnail_id, 'large');
                                $img_mobile = wp_get_attachment_image_url($thumbnail_id, 'medium');
                                $alt = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
                            }

                            // If attachment lookup failed or produced no URLs, fall back to fallbackImageUrl
                            if (empty($img_desktop) && !empty($fallbackImageUrl)) {
                                $img_desktop = $fallbackImageUrl;
                            }
                            if (empty($img_tablet)) {
                                $img_tablet = $img_desktop;
                            }
                            if (empty($img_mobile)) {
                                $img_mobile = $img_desktop;
                            }
                            if (empty($alt)) {
                                $alt = $title;
                            }

                            if (!empty($img_desktop) || !empty($img_mobile)) {
                                $display_src = !empty($img_mobile) ? $img_mobile : $img_desktop;
                                
                                $image_html .= '<picture>';
                                if (!empty($img_desktop)) {
                                    $image_html .= '<source media="(min-width: 1024px)" srcset="'.esc_url($img_desktop).'" data-srcset="'.esc_url($img_desktop).'">';
                                }
                                if (!empty($img_tablet)) {
                                    $image_html .= '<source media="(min-width: 768px)" srcset="'.esc_url($img_tablet).'" data-srcset="'.esc_url($img_tablet).'">';
                                }
                                $image_html .= '<img src="'.esc_url($display_src).'" data-src="'.esc_url($display_src).'" alt="'.esc_attr($alt).'" loading="lazy" class="lazy w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">';
                                $image_html .= '</picture>';
                            } else {
                                $image_html = '<div class="w-full h-full bg-gray-200 flex items-center justify-center text-gray-500">No Image</div>';
                            }
                        }
                        ?>
                        <?php if ($displayMode === 'slider'): ?>
                            <li class="splide__slide align-qb-item">
                        <?php else: ?>
                            <div class="align-qb-item">
                        <?php endif; ?>
                            <?php 
                            $cardTag = $linkWholeContainer ? 'a' : 'div';
                            $cardHref = $linkWholeContainer ? ' href="' . esc_url($permalink) . '" aria-label="' . esc_attr($title) . '" rel="bookmark"' : '';
                            $cardWholeClass = $linkWholeContainer ? ' align-qb-card--whole-link' : '';
                            ?>
                            <?php if ($postCardStyle === 'overlay'): ?>
                                <<?php echo $cardTag . $cardHref; ?> class="align-qb-card align-qb-card--overlay<?php echo $cardWholeClass; ?>">
                                    <?php if (!$linkWholeContainer): ?>
                                        <a href="<?php echo esc_url($permalink); ?>" class="align-qb-overlay-link" aria-label="<?php echo esc_attr($title); ?>"></a>
                                    <?php endif; ?>
                                    <div class="align-qb-image">
                                        <?php echo $image_html; ?>
                                    </div>
                                    <?php if ($badge_name): ?>
                                        <div class="align-qb-badge-wrap">
                                            <?php if ($badge_link && !$linkWholeContainer): ?>
                                                <a href="<?php echo esc_url($badge_link); ?>" class="align-qb-badge"><?php echo esc_html($badge_name); ?></a>
                                            <?php else: ?>
                                                <span class="align-qb-badge"><?php echo esc_html($badge_name); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="align-qb-content">
                                        <?php if ($showDate): ?>
                                            <span class="align-qb-meta align-qb-meta--date"><?php echo esc_html($date); ?></span>
                                        <?php endif; ?>
                                        <?php if ($showTitle): ?>
                                            <<?php echo $titleTag; ?> class="align-qb-title"><?php echo esc_html($title); ?></<?php echo $titleTag; ?>>
                                        <?php endif; ?>
                                        <?php if ($showExcerpt && $excerpt): ?>
                                            <<?php echo $excerptTag; ?> class="align-qb-excerpt"><?php echo esc_html($excerpt); ?></<?php echo $excerptTag; ?>>
                                        <?php endif; ?>
                                    </div>
                                </<?php echo $cardTag; ?>>
                            <?php elseif ($postCardStyle === 'no-image'): ?>
                                <<?php echo $cardTag . $cardHref; ?> class="align-qb-card align-qb-card--no-image<?php echo $cardWholeClass; ?>">
                                    <div class="align-qb-content">
                                        <?php if ($badge_name): ?>
                                            <div class="align-qb-badge-wrap">
                                                <?php if ($badge_link && !$linkWholeContainer): ?>
                                                    <a href="<?php echo esc_url($badge_link); ?>" class="align-qb-badge"><?php echo esc_html($badge_name); ?></a>
                                                <?php else: ?>
                                                    <span class="align-qb-badge"><?php echo esc_html($badge_name); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($post_tags)): ?>
                                            <div class="align-qb-tags">
                                                <?php foreach ($post_tags as $tag): ?>
                                                    <?php if ($tag['link'] && !$linkWholeContainer): ?>
                                                        <a href="<?php echo esc_url($tag['link']); ?>" class="align-qb-tag"><?php echo esc_html($tag['name']); ?></a>
                                                    <?php else: ?>
                                                        <span class="align-qb-tag"><?php echo esc_html($tag['name']); ?></span>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($showDate): ?>
                                            <span class="align-qb-meta align-qb-meta--date"><?php echo esc_html($date); ?></span>
                                        <?php endif; ?>
                                        <?php if ($showTitle): ?>
                                            <?php if (!$linkWholeContainer): ?>
                                                <a href="<?php echo esc_url($permalink); ?>" class="align-qb-title-link">
                                                    <<?php echo $titleTag; ?> class="align-qb-title"><?php echo esc_html($title); ?></<?php echo $titleTag; ?>>
                                                </a>
                                            <?php else: ?>
                                                <<?php echo $titleTag; ?> class="align-qb-title"><?php echo esc_html($title); ?></<?php echo $titleTag; ?>>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if ($showExcerpt && $excerpt): ?>
                                            <<?php echo $excerptTag; ?> class="align-qb-excerpt"><?php echo esc_html($excerpt); ?></<?php echo $excerptTag; ?>>
                                        <?php endif; ?>
                                        <?php if ($showReadMore): ?>
                                        <div class="align-qb-readmore-wrap">
                                            <?php if (!$linkWholeContainer): ?>
                                                <a href="<?php echo esc_url($permalink); ?>" class="align-qb-readmore">
                                            <?php else: ?>
                                                <div class="align-qb-readmore">
                                            <?php endif; ?>
                                                <span class="align-qb-readmore-text"><?php echo esc_html($readMoreText); ?></span>
                                                <?php
                                                if ($readMoreIconType === 'arrow') {
                                                    echo '<svg class="align-qb-icon align-qb-icon--arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>';
                                                } elseif ($readMoreIconType === 'chevron') {
                                                    echo '<svg class="align-qb-icon align-qb-icon--chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>';
                                                } elseif ($readMoreIconType === 'custom' && !empty($readMoreCustomSvg)) {
                                                    echo '<span class="align-qb-icon align-qb-icon--custom">' . $readMoreCustomSvg . '</span>';
                                                }
                                                ?>
                                            <?php if (!$linkWholeContainer): ?>
                                                </a>
                                            <?php else: ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </<?php echo $cardTag; ?>>
                            <?php else: // stacked style ?>
                                <<?php echo $cardTag . $cardHref; ?> class="align-qb-card align-qb-card--stacked<?php echo $cardWholeClass; ?>">
                                    <div class="align-qb-image-wrap">
                                        <?php if (!$linkWholeContainer): ?>
                                            <a href="<?php echo esc_url($permalink); ?>" class="align-qb-image-link">
                                        <?php else: ?>
                                            <div class="align-qb-image-link">
                                        <?php endif; ?>
                                            <div class="align-qb-image">
                                                <?php echo $image_html; ?>
                                            </div>
                                        <?php if (!$linkWholeContainer): ?>
                                            </a>
                                        <?php else: ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($badge_name): ?>
                                            <div class="align-qb-badge-wrap">
                                                <?php if ($badge_link && !$linkWholeContainer): ?>
                                                    <a href="<?php echo esc_url($badge_link); ?>" class="align-qb-badge"><?php echo esc_html($badge_name); ?></a>
                                                <?php else: ?>
                                                    <span class="align-qb-badge"><?php echo esc_html($badge_name); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($post_tags)): ?>
                                        <div class="align-qb-tags">
                                            <?php foreach ($post_tags as $tag): ?>
                                                <?php if ($tag['link'] && !$linkWholeContainer): ?>
                                                    <a href="<?php echo esc_url($tag['link']); ?>" class="align-qb-tag"><?php echo esc_html($tag['name']); ?></a>
                                                <?php else: ?>
                                                    <span class="align-qb-tag"><?php echo esc_html($tag['name']); ?></span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="align-qb-content">
                                        <?php if ($showDate): ?>
                                            <span class="align-qb-meta align-qb-meta--date"><?php echo esc_html($date); ?></span>
                                        <?php endif; ?>
                                        <?php if ($showTitle): ?>
                                            <?php if (!$linkWholeContainer): ?>
                                                <a href="<?php echo esc_url($permalink); ?>" class="align-qb-title-link">
                                                    <<?php echo $titleTag; ?> class="align-qb-title"><?php echo esc_html($title); ?></<?php echo $titleTag; ?>>
                                                </a>
                                            <?php else: ?>
                                                <<?php echo $titleTag; ?> class="align-qb-title"><?php echo esc_html($title); ?></<?php echo $titleTag; ?>>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if ($showExcerpt && $excerpt): ?>
                                            <<?php echo $excerptTag; ?> class="align-qb-excerpt"><?php echo esc_html($excerpt); ?></<?php echo $excerptTag; ?>>
                                        <?php endif; ?>
                                        <?php if ($showReadMore): ?>
                                        <div class="align-qb-readmore-wrap">
                                            <?php if (!$linkWholeContainer): ?>
                                                <a href="<?php echo esc_url($permalink); ?>" class="align-qb-readmore">
                                            <?php else: ?>
                                                <div class="align-qb-readmore">
                                            <?php endif; ?>
                                                <span class="align-qb-readmore-text"><?php echo esc_html($readMoreText); ?></span>
                                                <?php
                                                if ($readMoreIconType === 'arrow') {
                                                    echo '<svg class="align-qb-icon align-qb-icon--arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>';
                                                } elseif ($readMoreIconType === 'chevron') {
                                                    echo '<svg class="align-qb-icon align-qb-icon--chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>';
                                                } elseif ($readMoreIconType === 'custom' && !empty($readMoreCustomSvg)) {
                                                    echo '<span class="align-qb-icon align-qb-icon--custom">' . $readMoreCustomSvg . '</span>';
                                                }
                                                ?>
                                            <?php if (!$linkWholeContainer): ?>
                                                </a>
                                            <?php else: ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </<?php echo $cardTag; ?>>
                            <?php endif; ?>
                        <?php if ($displayMode === 'slider'): ?>
                            </li>
                        <?php else: ?>
                            </div>
                        <?php endif; ?>
                        <?php
                    }
                    wp_reset_postdata();
                } else {
                    echo '<p class="align-qb-no-posts">No posts found.</p>';
                }
            } else {
                // custom InnerBlocks
                echo $content;
            }
            ?>
        <?php 
    if (!$isAjax) {
        if ($displayMode === 'slider') { ?>
                </ul>
            </div>
            <?php } ?>
            </div>
            <?php
            if (!empty($GLOBALS['align_qb_last_pagination_html'])) {
                echo $GLOBALS['align_qb_last_pagination_html'];
            }
            ?>
            <?php
            // Render dot label elements for custom JS/CSS integration
            if ($displayMode === 'slider' && $useCustomDotLabels && !empty($dotLabels)) {
                $labelsArr = array_map('trim', explode('||', $dotLabels));
                $sliderRef = isset($sliderId) ? $sliderId : '';
                echo '<ul class="align-qb-dot-labels" data-slider-ref="' . esc_attr($sliderRef) . '">';
                foreach ($labelsArr as $idx => $label) {
                    if ($label !== '') {
                        echo '<li class="align-qb-dot-label" data-dot-index="' . esc_attr($idx) . '">';
                        echo '<span class="align-qb-dot-label-text">' . esc_html($label) . '</span>';
                        echo '<span class="align-qb-dot-label-bullet"></span>';
                        echo '</li>';
                    }
                }
                echo '</ul>';
            }
            ?>
        </div>
        <?php
    }
    return ob_get_clean();
}

function align_slide_render_callback($attributes, $content) {
    $className = $attributes['className'] ?? '';

    $wrapperClasses = trim("splide__slide align-splide-slide align-qb-item {$className}");

    ob_start();
    ?>
    <li class="<?php echo esc_attr($wrapperClasses); ?>">
        <div class="align-qb-slide-content">
            <?php echo $content; ?>
        </div>
    </li>
    <?php
    return ob_get_clean();
}