<?php
if (!defined('ABSPATH')) {
    exit;
}

$icon_mode     = isset($attributes['iconMode']) ? trim($attributes['iconMode']) : 'lucide';
$lucide_icon   = isset($attributes['lucideIcon']) ? trim($attributes['lucideIcon']) : 'star';
$custom_svg    = isset($attributes['customSvg']) ? trim($attributes['customSvg']) : '';
$custom_img    = isset($attributes['customImgUrl']) ? trim($attributes['customImgUrl']) : '';
$icon_size     = isset($attributes['iconSize']) ? intval($attributes['iconSize']) : 24;
$icon_color    = isset($attributes['iconColor']) ? trim($attributes['iconColor']) : '';
$custom_class  = isset($attributes['customClass']) ? trim($attributes['customClass']) : '';
$element_id    = isset($attributes['elementId']) ? trim($attributes['elementId']) : '';

if (!function_exists('align_get_lucide_icon_paths')) {
    function align_get_lucide_icon_paths($name) {
        static $lucide_map = null;
        if ($lucide_map === null) {
            $map_file = __DIR__ . '/lucide-icons-map.php';
            if (file_exists($map_file)) {
                $lucide_map = require $map_file;
            } else {
                $lucide_map = array();
            }
        }
        return isset($lucide_map[$name]) ? $lucide_map[$name] : (isset($lucide_map['star']) ? $lucide_map['star'] : '');
    }
}

$id_attr = !empty($element_id) ? ' id="' . esc_attr($element_id) . '"' : '';
$wrapper_classes = array('align-icon-block');
if (!empty($custom_class)) {
    $wrapper_classes[] = esc_attr($custom_class);
}

$styles = array(
    'width: ' . $icon_size . 'px',
    'height: ' . $icon_size . 'px'
);
if (!empty($icon_color)) {
    $styles[] = 'color: ' . esc_attr($icon_color);
}
$style_attr = ' style="' . implode('; ', $styles) . '"';
?>
<div<?php echo $id_attr; ?> class="<?php echo implode(' ', $wrapper_classes); ?>"<?php echo $style_attr; ?>>
    <?php if ($icon_mode === 'svg' && !empty($custom_svg)): ?>
        <?php echo $custom_svg; ?>
    <?php elseif ($icon_mode === 'image' && !empty($custom_img)): ?>
        <img src="<?php echo esc_url($custom_img); ?>" alt="" class="align-icon-img" style="width:<?php echo $icon_size; ?>px;height:<?php echo $icon_size; ?>px;object-fit:contain;" />
    <?php else: ?>
        <svg class="align-icon-svg" width="<?php echo $icon_size; ?>" height="<?php echo $icon_size; ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <?php echo align_get_lucide_icon_paths($lucide_icon); ?>
        </svg>
    <?php endif; ?>
</div>
