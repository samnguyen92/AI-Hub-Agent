<?php
if (!defined('ABSPATH')) {
    exit;
}

// Helper to track and prevent duplicate HTML IDs on frontend render
if (!function_exists('align_track_media_id')) {
    function align_track_media_id($id) {
        static $seen_ids = array();
        if (empty($id)) {
            return $id;
        }
        if (!isset($seen_ids[$id])) {
            $seen_ids[$id] = 1;
            return $id;
        } else {
            $seen_ids[$id]++;
            return $id . '-' . $seen_ids[$id];
        }
    }
}

// Helper counter to generate unique IDs
if (!function_exists('align_get_media_counter')) {
    function align_get_media_counter() {
        static $counter = 0;
        $counter++;
        return $counter;
    }
}

// Helper to build video attributes string with lazyload and background placeholder
if (!function_exists('align_build_video_attrs')) {
    function align_build_video_attrs($id, $class, $poster, $focalStyle, $attrs, $custom, $ignoreLazy) {
        $video_attrs = [];
        if (in_array('loop', $attrs)) $video_attrs[] = 'loop';
        if (in_array('autoplay', $attrs)) $video_attrs[] = 'autoplay';
        if (in_array('muted', $attrs)) $video_attrs[] = 'muted';
        if (in_array('playsinline', $attrs)) $video_attrs[] = 'playsinline';
        if (!empty($attrs['preload'])) $video_attrs[] = 'preload="' . esc_attr($attrs['preload']) . '"';
        if (in_array('ariaHidden', $attrs)) $video_attrs[] = 'aria-hidden="true"';
        
        if (!empty($poster)) {
            $video_attrs[] = $ignoreLazy ? 'poster="' . esc_url($poster) . '"' : 'data-poster="' . esc_url($poster) . '"';
        }
        if (!empty($attrs['title'])) {
            $video_attrs[] = 'title="' . esc_attr($attrs['title']) . '"';
        }
        
        $video_classes = [];
        if (!$ignoreLazy) $video_classes[] = 'lazy';
        if (!empty($class)) $video_classes[] = $class;
        if (!empty($video_classes)) {
            $video_attrs[] = 'class="' . esc_attr(implode(' ', $video_classes)) . '"';
        }
        
        if (!empty($id)) $video_attrs[] = 'id="' . esc_attr($id) . '"';
        
        $finalStyle = '';
        if (!empty($focalStyle)) {
            $finalStyle = $focalStyle;
        }
        if (!empty($finalStyle)) {
            $video_attrs[] = 'style="' . esc_attr($finalStyle) . '"';
        }
        
        if (!empty($custom)) $video_attrs[] = $custom;

        return implode(' ', $video_attrs);
    }
}

$mediaType = isset($attributes['mediaType']) ? $attributes['mediaType'] : 'video';
$customAttributes = isset($attributes['customAttributes']) ? trim($attributes['customAttributes']) : '';
$ignoreLazyLoad = (isset($attributes['ignoreLazyLoad']) ? filter_var($attributes['ignoreLazyLoad'], FILTER_VALIDATE_BOOLEAN) : false) || is_admin();

if ($mediaType === 'video') {
    $mp4Url = isset($attributes['mp4Url']) ? trim($attributes['mp4Url']) : '';
    $webmUrl = isset($attributes['webmUrl']) ? trim($attributes['webmUrl']) : '';
    $posterUrl = isset($attributes['posterUrl']) ? trim($attributes['posterUrl']) : '';
    
    $tabletMp4Url = isset($attributes['tabletMp4Url']) ? trim($attributes['tabletMp4Url']) : '';
    $tabletWebmUrl = isset($attributes['tabletWebmUrl']) ? trim($attributes['tabletWebmUrl']) : '';
    $tabletPosterUrl = isset($attributes['tabletPosterUrl']) ? trim($attributes['tabletPosterUrl']) : '';

    $mobileMp4Url = isset($attributes['mobileMp4Url']) ? trim($attributes['mobileMp4Url']) : '';
    $mobileWebmUrl = isset($attributes['mobileWebmUrl']) ? trim($attributes['mobileWebmUrl']) : '';
    $mobilePosterUrl = isset($attributes['mobilePosterUrl']) ? trim($attributes['mobilePosterUrl']) : '';

    $videoTitle = isset($attributes['videoTitle']) ? trim($attributes['videoTitle']) : 'Align Video Intro';
    $loop = isset($attributes['loop']) ? filter_var($attributes['loop'], FILTER_VALIDATE_BOOLEAN) : true;
    $autoplay = isset($attributes['autoplay']) ? filter_var($attributes['autoplay'], FILTER_VALIDATE_BOOLEAN) : true;
    $muted = isset($attributes['muted']) ? filter_var($attributes['muted'], FILTER_VALIDATE_BOOLEAN) : true;
    $playsinline = isset($attributes['playsinline']) ? filter_var($attributes['playsinline'], FILTER_VALIDATE_BOOLEAN) : true;
    $preload = isset($attributes['preload']) ? trim($attributes['preload']) : 'auto';
    $ariaHidden = isset($attributes['ariaHidden']) ? filter_var($attributes['ariaHidden'], FILTER_VALIDATE_BOOLEAN) : true;
    $videoClass = isset($attributes['videoClass']) ? trim($attributes['videoClass']) : '';
    $videoId = isset($attributes['videoId']) ? trim($attributes['videoId']) : '';
    
    // Setup unique base ID
    $align_media_counter = align_get_media_counter();
    $baseVideoId = !empty($videoId) ? $videoId : 'align-media-video-' . $align_media_counter;
    $baseVideoId = align_track_media_id($baseVideoId);

    $hasTabletVideo = !empty($tabletMp4Url) || !empty($tabletWebmUrl) || !empty($tabletPosterUrl);
    $hasMobileVideo = !empty($mobileMp4Url) || !empty($mobileWebmUrl) || !empty($mobilePosterUrl);
    $isMultiVideo = $hasTabletVideo || $hasMobileVideo;

    $video_shared_attrs = [
        'preload' => $preload,
        'title' => $videoTitle
    ];
    if ($loop) $video_shared_attrs[] = 'loop';
    if ($autoplay) $video_shared_attrs[] = 'autoplay';
    if ($muted) $video_shared_attrs[] = 'muted';
    if ($playsinline) $video_shared_attrs[] = 'playsinline';
    if ($ariaHidden) $video_shared_attrs[] = 'aria-hidden="true"';

    if ($isMultiVideo) {
        // Multi-Video mode
        ?>
        <style>
            .<?php echo esc_html($baseVideoId . '-tablet'); ?>, 
            .<?php echo esc_html($baseVideoId . '-mobile'); ?> { 
                display: none !important; 
            }
            @media (max-width: 589px) {
                .<?php echo esc_html($baseVideoId . '-desktop'); ?>, 
                .<?php echo esc_html($baseVideoId . '-tablet'); ?> { 
                    display: none !important; 
                }
                .<?php echo esc_html($baseVideoId . '-mobile'); ?> { 
                    display: block !important; 
                }
            }
            @media (min-width: 590px) and (max-width: 991px) {
                .<?php echo esc_html($baseVideoId . '-desktop'); ?>, 
                .<?php echo esc_html($baseVideoId . '-mobile'); ?> { 
                    display: none !important; 
                }
                .<?php echo esc_html($baseVideoId . '-tablet'); ?> { 
                    display: block !important; 
                }
            }
            @media (min-width: 992px) {
                .<?php echo esc_html($baseVideoId . '-desktop'); ?> { 
                    display: block !important; 
                }
            }
        </style>
        
        <?php
        // Render 3 video elements
        // 1. Mobile Video
        $mobileVideoUrl = !empty($mobileMp4Url) ? $mobileMp4Url : (!empty($mobileWebmUrl) ? $mobileWebmUrl : $mp4Url);
        $mobileWebmSource = !empty($mobileWebmUrl) ? $mobileWebmUrl : $webmUrl;
        $mobilePoster = !empty($mobilePosterUrl) ? $mobilePosterUrl : $posterUrl;
        $mobileAttrs = align_build_video_attrs(
            $baseVideoId . '-mobile-el', 
            $videoClass . ' ' . $baseVideoId . '-mobile', 
            $mobilePoster, 
            "", 
            $video_shared_attrs, 
            $customAttributes, 
            $ignoreLazyLoad
        );
        ?>
        <video <?php echo $mobileAttrs; ?>>
            <?php if (!empty($mobileWebmSource)): ?>
                <source <?php echo $ignoreLazyLoad ? 'src' : 'data-src'; ?>="<?php echo esc_url($mobileWebmSource); ?>" type="video/webm">
            <?php endif; ?>
            <?php if (!empty($mobileVideoUrl)): ?>
                <source <?php echo $ignoreLazyLoad ? 'src' : 'data-src'; ?>="<?php echo esc_url($mobileVideoUrl); ?>" type="video/mp4">
            <?php endif; ?>
        </video>

        <?php
        // 2. Tablet Video
        $tabletVideoUrl = !empty($tabletMp4Url) ? $tabletMp4Url : (!empty($tabletWebmUrl) ? $tabletWebmUrl : $mp4Url);
        $tabletWebmSource = !empty($tabletWebmUrl) ? $tabletWebmUrl : $webmUrl;
        $tabletPoster = !empty($tabletPosterUrl) ? $tabletPosterUrl : $posterUrl;
        $tabletAttrs = align_build_video_attrs(
            $baseVideoId . '-tablet-el', 
            $videoClass . ' ' . $baseVideoId . '-tablet', 
            $tabletPoster, 
            "", 
            $video_shared_attrs, 
            $customAttributes, 
            $ignoreLazyLoad
        );
        ?>
        <video <?php echo $tabletAttrs; ?>>
            <?php if (!empty($tabletWebmSource)): ?>
                <source <?php echo $ignoreLazyLoad ? 'src' : 'data-src'; ?>="<?php echo esc_url($tabletWebmSource); ?>" type="video/webm">
            <?php endif; ?>
            <?php if (!empty($tabletVideoUrl)): ?>
                <source <?php echo $ignoreLazyLoad ? 'src' : 'data-src'; ?>="<?php echo esc_url($tabletVideoUrl); ?>" type="video/mp4">
            <?php endif; ?>
        </video>

        <?php
        // 3. Desktop Video
        $desktopAttrs = align_build_video_attrs(
            $baseVideoId . '-desktop-el', 
            $videoClass . ' ' . $baseVideoId . '-desktop', 
            $posterUrl, 
            "", 
            $video_shared_attrs, 
            $customAttributes, 
            $ignoreLazyLoad
        );
        ?>
        <video <?php echo $desktopAttrs; ?>>
            <?php if (!empty($webmUrl)): ?>
                <source <?php echo $ignoreLazyLoad ? 'src' : 'data-src'; ?>="<?php echo esc_url($webmUrl); ?>" type="video/webm">
            <?php endif; ?>
            <?php if (!empty($mp4Url)): ?>
                <source <?php echo $ignoreLazyLoad ? 'src' : 'data-src'; ?>="<?php echo esc_url($mp4Url); ?>" type="video/mp4">
            <?php endif; ?>
        </video>
        <?php
    } else {
        // Single-Video mode
        $desktopAttrs = align_build_video_attrs(
            $baseVideoId, 
            $videoClass, 
            $posterUrl, 
            '', 
            $video_shared_attrs, 
            $customAttributes, 
            $ignoreLazyLoad
        );
        ?>
        <video <?php echo $desktopAttrs; ?>>
            <?php if (!empty($webmUrl)): ?>
                <source <?php echo $ignoreLazyLoad ? 'src' : 'data-src'; ?>="<?php echo esc_url($webmUrl); ?>" type="video/webm">
            <?php endif; ?>
            <?php if (!empty($mp4Url)): ?>
                <source <?php echo $ignoreLazyLoad ? 'src' : 'data-src'; ?>="<?php echo esc_url($mp4Url); ?>" type="video/mp4">
            <?php endif; ?>
        </video>
        <?php
    }
} else {
    // Image Media Type
    $useFeaturedImage = isset($attributes['useFeaturedImage']) ? filter_var($attributes['useFeaturedImage'], FILTER_VALIDATE_BOOLEAN) : false;
    $img_id = isset($attributes['imageId']) ? intval($attributes['imageId']) : 0;
    
    $focalPoint = isset($attributes['focalPoint']) ? $attributes['focalPoint'] : null;
    $tabletFocalPoint = isset($attributes['tabletFocalPoint']) ? $attributes['tabletFocalPoint'] : null;
    $mobileFocalPoint = isset($attributes['mobileFocalPoint']) ? $attributes['mobileFocalPoint'] : null;

    $desktopOpacity = isset($attributes['imageOpacity']) ? floatval($attributes['imageOpacity']) : 1.0;
    $tabletOpacity = isset($attributes['tabletOpacity']) ? floatval($attributes['tabletOpacity']) : $desktopOpacity;
    $mobileOpacity = isset($attributes['mobileOpacity']) ? floatval($attributes['mobileOpacity']) : $desktopOpacity;

    $customTabletUrl = isset($attributes['tabletImgUrl']) ? trim($attributes['tabletImgUrl']) : '';
    $customMobileUrl = isset($attributes['mobileImgUrl']) ? trim($attributes['mobileImgUrl']) : '';

    if ($useFeaturedImage) {
        $current_post_id = get_the_ID();
        if ($current_post_id) {
            $featured_img_id = get_post_thumbnail_id($current_post_id);
            if ($featured_img_id) {
                $img_id = $featured_img_id;
            }
        }
    }

    $mainImgUrl = isset($attributes['desktopImgUrl']) ? trim($attributes['desktopImgUrl']) : '';
    $imageAlt = isset($attributes['imageAlt']) ? trim($attributes['imageAlt']) : '';
    $fetchPriority = isset($attributes['fetchPriority']) ? trim($attributes['fetchPriority']) : 'auto';
    $figureClass = isset($attributes['figureClass']) ? trim($attributes['figureClass']) : 'blogFeaturedImage wp-block-post-featured-image';
    $imgClass = isset($attributes['imgClass']) ? trim($attributes['imgClass']) : '';
    $imgId = isset($attributes['imgId']) ? trim($attributes['imgId']) : '';
    
    // Generate unique ID for CSS target
    $align_media_counter = align_get_media_counter();
    $unique_img_id = !empty($imgId) ? $imgId : 'align-media-img-' . $align_media_counter;
    $unique_img_id = align_track_media_id($unique_img_id);

    $desktopSize = isset($attributes['desktopSize']) ? trim($attributes['desktopSize']) : '';
    $tabletSize = isset($attributes['tabletSize']) ? trim($attributes['tabletSize']) : '';
    $mobileSize = isset($attributes['mobileSize']) ? trim($attributes['mobileSize']) : '';

    $mobileImgUrl = '';
    $tabletImgUrl = '';
    $desktopImgUrl = '';

    // 1. Desktop Image URL
    if ($img_id > 0) {
        $size_to_get = !empty($desktopSize) ? $desktopSize : 'large';
        $desktopImgUrl = wp_get_attachment_image_url($img_id, $size_to_get);
        if (!$desktopImgUrl) {
            $desktopImgUrl = wp_get_attachment_image_url($img_id, 'full');
        }
        if ($useFeaturedImage || empty($imageAlt)) {
            $imageAlt = get_post_meta($img_id, '_wp_attachment_image_alt', true);
        }
    }
    if (empty($desktopImgUrl)) {
        $desktopImgUrl = $mainImgUrl;
    }

    // 2. Tablet Image URL
    $tabletImgId = isset($attributes['tabletImgId']) ? intval($attributes['tabletImgId']) : 0;
    if ($tabletImgId > 0) {
        $size_to_get = !empty($tabletSize) ? $tabletSize : 'medium_large';
        $tabletImgUrl = wp_get_attachment_image_url($tabletImgId, $size_to_get);
        if (!$tabletImgUrl) {
            $tabletImgUrl = wp_get_attachment_image_url($tabletImgId, 'large');
        }
        if (!$tabletImgUrl) {
            $tabletImgUrl = wp_get_attachment_image_url($tabletImgId, 'full');
        }
    }
    if (empty($tabletImgUrl)) {
        // Fallback to desktop img_id with tablet size config
        if ($img_id > 0) {
            $size_to_get = !empty($tabletSize) ? $tabletSize : 'medium_large';
            $tabletImgUrl = wp_get_attachment_image_url($img_id, $size_to_get);
            if (!$tabletImgUrl) {
                $tabletImgUrl = wp_get_attachment_image_url($img_id, 'large');
            }
        }
    }
    if (empty($tabletImgUrl)) {
        $tabletImgUrl = $customTabletUrl;
    }

    // 3. Mobile Image URL
    $mobileImgId = isset($attributes['mobileImgId']) ? intval($attributes['mobileImgId']) : 0;
    if ($mobileImgId > 0) {
        $size_to_get = !empty($mobileSize) ? $mobileSize : 'medium';
        $mobileImgUrl = wp_get_attachment_image_url($mobileImgId, $size_to_get);
        if (!$mobileImgUrl) {
            $mobileImgUrl = wp_get_attachment_image_url($mobileImgId, 'large');
        }
        if (!$mobileImgUrl) {
            $mobileImgUrl = wp_get_attachment_image_url($mobileImgId, 'full');
        }
    }
    if (empty($mobileImgUrl)) {
        // Fallback to desktop img_id with mobile size config
        if ($img_id > 0) {
            $size_to_get = !empty($mobileSize) ? $mobileSize : 'medium';
            $mobileImgUrl = wp_get_attachment_image_url($img_id, $size_to_get);
        }
    }
    if (empty($mobileImgUrl)) {
        $mobileImgUrl = $customMobileUrl;
    }

    $resolvedTabletUrl = !empty($tabletImgUrl) ? $tabletImgUrl : $desktopImgUrl;
    $resolvedMobileUrl = !empty($mobileImgUrl) ? $mobileImgUrl : $desktopImgUrl;

    $default_src = $desktopImgUrl;

    // Generate Alt from filename if still empty
    if (empty($imageAlt) && !empty($default_src)) {
        $filename = basename($default_src);
        $filename = pathinfo($filename, PATHINFO_FILENAME);
        // Remove common crop sizes suffix like -900x507
        $filename = preg_replace('/-\d+x\d+$/i', '', $filename);
        $filename = str_replace(['-', '_'], ' ', $filename);
        $imageAlt = trim(preg_replace('/\s+/', ' ', $filename));
    }

    // Build picture figure classes and style placeholder
    $figure_attr = '';
    if (!empty($figureClass)) {
        $figure_attr = ' class="' . esc_attr($figureClass) . '"';
    } else {
        $figure_attr = '';
    }

    // Calculate focal points percentages
    $x = (isset($focalPoint['x']) && is_numeric($focalPoint['x'])) ? floatval($focalPoint['x']) * 100 : 50;
    $y = (isset($focalPoint['y']) && is_numeric($focalPoint['y'])) ? floatval($focalPoint['y']) * 100 : 50;

    $tablet_x = (isset($tabletFocalPoint['x']) && is_numeric($tabletFocalPoint['x'])) ? floatval($tabletFocalPoint['x']) * 100 : $x;
    $tablet_y = (isset($tabletFocalPoint['y']) && is_numeric($tabletFocalPoint['y'])) ? floatval($tabletFocalPoint['y']) * 100 : $y;

    $mobile_x = (isset($mobileFocalPoint['x']) && is_numeric($mobileFocalPoint['x'])) ? floatval($mobileFocalPoint['x']) * 100 : $x;
    $mobile_y = (isset($mobileFocalPoint['y']) && is_numeric($mobileFocalPoint['y'])) ? floatval($mobileFocalPoint['y']) * 100 : $y;

    // Build img element classes
    $img_classes = [];
    if ($ignoreLazyLoad) {
        $img_classes[] = 'ignorelazyload';
    } else {
        $img_classes[] = 'lazy';
    }
    if (!empty($imgClass)) {
        $img_classes[] = $imgClass;
    }
    $img_class_str = !empty($img_classes) ? ' class="' . esc_attr(implode(' ', $img_classes)) . '"' : '';

    // Build extra img attributes
    $img_attrs = [];
    $img_attrs[] = 'id="' . esc_attr($unique_img_id) . '"';
    if (!empty($fetchPriority) && $fetchPriority !== 'auto') {
        $img_attrs[] = 'fetchpriority="' . esc_attr($fetchPriority) . '"';
    }
    if (!empty($customAttributes)) {
        $img_attrs[] = $customAttributes;
    }

    $desktopObjectFit = isset($attributes['objectFit']) ? trim($attributes['objectFit']) : 'cover';
    $tabletObjectFit = isset($attributes['tabletObjectFit']) ? trim($attributes['tabletObjectFit']) : $desktopObjectFit;
    $mobileObjectFit = isset($attributes['mobileObjectFit']) ? trim($attributes['mobileObjectFit']) : $desktopObjectFit;

    $img_attrs_str = !empty($img_attrs) ? ' ' . implode(' ', $img_attrs) : '';
    $fig_id = $unique_img_id . '_fig';
    ?>
    <style>
        #<?php echo esc_html($fig_id); ?> {
            position: relative;
            display: block;
            overflow: hidden;
        }
        #<?php echo esc_html($fig_id); ?>::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #000000;
            pointer-events: none;
            z-index: 2;
            opacity: <?php echo esc_html(1 - $mobileOpacity); ?>;
            transition: opacity 0.3s ease;
        }
        #<?php echo esc_html($unique_img_id); ?> {
            object-position: <?php echo esc_html($mobile_x . '% ' . $mobile_y . '%'); ?>;
            object-fit: <?php echo esc_html($mobileObjectFit); ?>;
        }
        @media (min-width: 590px) {
            #<?php echo esc_html($fig_id); ?>::after {
                opacity: <?php echo esc_html(1 - $tabletOpacity); ?>;
            }
            #<?php echo esc_html($unique_img_id); ?> {
                object-position: <?php echo esc_html($tablet_x . '% ' . $tablet_y . '%'); ?>;
                object-fit: <?php echo esc_html($tabletObjectFit); ?>;
            }
        }
        @media (min-width: 992px) {
            #<?php echo esc_html($fig_id); ?>::after {
                opacity: <?php echo esc_html(1 - $desktopOpacity); ?>;
            }
            #<?php echo esc_html($unique_img_id); ?> {
                object-position: <?php echo esc_html($x . '% ' . $y . '%'); ?>;
                object-fit: <?php echo esc_html($desktopObjectFit); ?>;
            }
        }
    </style>
    <figure id="<?php echo esc_html($fig_id); ?>"<?php echo $figure_attr; ?>>
        <picture>
            <?php if (!empty($resolvedMobileUrl)): ?>
                <source media="(max-width: 589px)" <?php echo $ignoreLazyLoad ? 'srcset="' . esc_url($resolvedMobileUrl) . '"' : 'data-srcset="' . esc_url($resolvedMobileUrl) . '"'; ?>>
            <?php endif; ?>
            <?php if (!empty($resolvedTabletUrl)): ?>
                <source media="(min-width: 590px) and (max-width: 991px)" <?php echo $ignoreLazyLoad ? 'srcset="' . esc_url($resolvedTabletUrl) . '"' : 'data-srcset="' . esc_url($resolvedTabletUrl) . '"'; ?>>
            <?php endif; ?>
            <?php if (!empty($desktopImgUrl)): ?>
                <source media="(min-width: 992px)" <?php echo $ignoreLazyLoad ? 'srcset="' . esc_url($desktopImgUrl) . '"' : 'data-srcset="' . esc_url($desktopImgUrl) . '"'; ?>>
            <?php endif; ?>
            <?php if (!empty($default_src)): ?>
                <?php if ($ignoreLazyLoad): ?>
                    <img decoding="async" src="<?php echo esc_url($default_src); ?>" alt="<?php echo esc_attr($imageAlt); ?>"<?php echo $img_class_str; ?><?php echo $img_attrs_str; ?>>
                <?php else: ?>
                    <img decoding="async" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1 1'%3E%3C/svg%3E" data-src="<?php echo esc_url($default_src); ?>" alt="<?php echo esc_attr($imageAlt); ?>"<?php echo $img_class_str; ?><?php echo $img_attrs_str; ?>>
                <?php endif; ?>
            <?php endif; ?>
        </picture>
    </figure>
    <?php
}
