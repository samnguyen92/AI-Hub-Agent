<?php
if (!defined('ABSPATH')) {
    exit;
}

$className = $attributes['className'] ?? '';
$speed = floatval($attributes['speed'] ?? 1);
$direction = $attributes['direction'] ?? 'ltr';
$spaceBetween = intval($attributes['spaceBetween'] ?? 20);
$pauseOnHover = isset($attributes['pauseOnHover']) ? (bool)$attributes['pauseOnHover'] : true;
$slidesPerView = $attributes['slidesPerView'] ?? 'auto';
$slidesPerViewTablet = $attributes['slidesPerViewTablet'] ?? 'auto';
$slidesPerViewMobile = $attributes['slidesPerViewMobile'] ?? 'auto';

// Generate a random ID if unique id function fails
$unique_id = 'align-marquee-' . (function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid());

// Configuration array to be passed to frontend script.js
$config_attrs = array(
    'speed'               => $speed,
    'direction'           => $direction,
    'spaceBetween'        => $spaceBetween,
    'pauseOnHover'        => $pauseOnHover,
    'slidesPerView'       => $slidesPerView,
    'slidesPerViewTablet' => $slidesPerViewTablet,
    'slidesPerViewMobile' => $slidesPerViewMobile,
);

$wrapper_classes = trim("align-marquee-container splide {$className}");
?>
<div id="<?php echo esc_attr($unique_id); ?>" 
     class="<?php echo esc_attr($wrapper_classes); ?>" 
     data-config="<?php echo esc_attr(wp_json_encode($config_attrs)); ?>">
    <div class="splide__track">
        <ul class="splide__list">
            <?php
            // Calculate slide count and repeat content if necessary to ensure seamless looping without gaps
            $slide_count = substr_count($content, '<li');
            $final_content = $content;
            if ($slide_count > 0 && $slide_count < 15) {
                $repeat_times = ceil(15 / $slide_count);
                $final_content = str_repeat($content, $repeat_times);
            }
            echo $final_content;
            ?>
        </ul>
    </div>
</div>
