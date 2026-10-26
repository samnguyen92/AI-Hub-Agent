<?php
if (!defined('ABSPATH')) {
    exit;
}

$show_fb       = isset($attributes['showFB']) ? filter_var($attributes['showFB'], FILTER_VALIDATE_BOOLEAN) : true;
$show_x        = isset($attributes['showX']) ? filter_var($attributes['showX'], FILTER_VALIDATE_BOOLEAN) : true;
$show_linkedin = isset($attributes['showLinkedIn']) ? filter_var($attributes['showLinkedIn'], FILTER_VALIDATE_BOOLEAN) : true;
$show_email    = isset($attributes['showEmail']) ? filter_var($attributes['showEmail'], FILTER_VALIDATE_BOOLEAN) : true;
$show_copy     = isset($attributes['showCopy']) ? filter_var($attributes['showCopy'], FILTER_VALIDATE_BOOLEAN) : true;

$button_style = isset($attributes['buttonStyle']) ? trim($attributes['buttonStyle']) : 'icon_text';
$layout       = isset($attributes['layout']) ? trim($attributes['layout']) : 'row';
$custom_class = isset($attributes['customClass']) ? trim($attributes['customClass']) : '';
$element_id   = isset($attributes['elementId']) ? trim($attributes['elementId']) : '';

$current_url   = rawurlencode(get_permalink());
$current_title = rawurlencode(get_the_title());

$fb_url       = 'https://www.facebook.com/sharer/sharer.php?u=' . $current_url;
$x_url        = 'https://twitter.com/intent/tweet?url=' . $current_url . '&text=' . $current_title;
$linkedin_url = 'https://www.linkedin.com/sharing/share-offsite/?url=' . $current_url;
$email_url    = 'mailto:?subject=' . $current_title . '&body=' . $current_url;

$link_mode = isset($attributes['linkMode']) ? trim($attributes['linkMode']) : 'share';
$custom_fb       = isset($attributes['customFbUrl']) ? trim($attributes['customFbUrl']) : '';
$custom_x        = isset($attributes['customXUrl']) ? trim($attributes['customXUrl']) : '';
$custom_linkedin = isset($attributes['customLinkedInUrl']) ? trim($attributes['customLinkedInUrl']) : '';
$custom_email    = isset($attributes['customEmailUrl']) ? trim($attributes['customEmailUrl']) : '';

if ($link_mode === 'custom') {
    if (!empty($custom_fb)) $fb_url = $custom_fb;
    if (!empty($custom_x)) $x_url = $custom_x;
    if (!empty($custom_linkedin)) $linkedin_url = $custom_linkedin;
    if (!empty($custom_email)) $email_url = $custom_email;
}

if (!function_exists('align_get_share_icon_markup')) {
    function align_get_share_icon_markup($icon_type, $custom_svg, $custom_img_url, $default_svg_html) {
        if ($icon_type === 'image' && !empty($custom_img_url)) {
            return '<img src="' . esc_url($custom_img_url) . '" alt="" class="align-share-icon" style="width:18px;height:18px;object-fit:contain;" />';
        } elseif ($icon_type === 'svg' && !empty($custom_svg)) {
            return $custom_svg;
        }
        return $default_svg_html;
    }
}

$fb_icon_type     = isset($attributes['fbIconType']) ? trim($attributes['fbIconType']) : 'svg';
$fb_custom_svg    = isset($attributes['fbCustomSvg']) ? trim($attributes['fbCustomSvg']) : '';
$fb_custom_img    = isset($attributes['fbCustomImgUrl']) ? trim($attributes['fbCustomImgUrl']) : '';

$x_icon_type     = isset($attributes['xIconType']) ? trim($attributes['xIconType']) : 'svg';
$x_custom_svg    = isset($attributes['xCustomSvg']) ? trim($attributes['xCustomSvg']) : '';
$x_custom_img    = isset($attributes['xCustomImgUrl']) ? trim($attributes['xCustomImgUrl']) : '';

$li_icon_type     = isset($attributes['linkedinIconType']) ? trim($attributes['linkedinIconType']) : 'svg';
$li_custom_svg    = isset($attributes['linkedinCustomSvg']) ? trim($attributes['linkedinCustomSvg']) : '';
$li_custom_img    = isset($attributes['linkedinCustomImgUrl']) ? trim($attributes['linkedinCustomImgUrl']) : '';

$em_icon_type     = isset($attributes['emailIconType']) ? trim($attributes['emailIconType']) : 'svg';
$em_custom_svg    = isset($attributes['emailCustomSvg']) ? trim($attributes['emailCustomSvg']) : '';
$em_custom_img    = isset($attributes['emailCustomImgUrl']) ? trim($attributes['emailCustomImgUrl']) : '';

$cp_icon_type     = isset($attributes['copyIconType']) ? trim($attributes['copyIconType']) : 'svg';
$cp_custom_svg    = isset($attributes['copyCustomSvg']) ? trim($attributes['copyCustomSvg']) : '';
$cp_custom_img    = isset($attributes['copyCustomImgUrl']) ? trim($attributes['copyCustomImgUrl']) : '';

$fb_default_svg = '<svg class="align-share-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>';
$x_default_svg  = '<svg class="align-share-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2C3 15.5.5 9.6 3 5c2.2 2.6 5.6 4.1 9 4-.9-4.2 4-6.6 7-3.8 1.1 0 3-1.2 3-1.2z"/></svg>';
$li_default_svg = '<svg class="align-share-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect width="4" height="12" x="2" y="9"/><circle cx="4" cy="4" r="2"/></svg>';
$em_default_svg = '<svg class="align-share-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>';
$cp_default_svg = '<svg class="align-share-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';

$id_attr = !empty($element_id) ? ' id="' . esc_attr($element_id) . '"' : '';
$wrapper_classes = array(
    'align-share-block',
    'align-share-layout-' . esc_attr($layout),
    'align-share-style-' . esc_attr($button_style)
);
if (!empty($custom_class)) {
    $wrapper_classes[] = esc_attr($custom_class);
}
?>
<div<?php echo $id_attr; ?> class="<?php echo implode(' ', $wrapper_classes); ?>">
    <div class="align-share-buttons">
        <?php if ($show_fb): ?>
            <a class="align-share-btn align-share-fb" href="<?php echo esc_url($fb_url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e('Share on Facebook', 'align-function'); ?>">
                <?php echo align_get_share_icon_markup($fb_icon_type, $fb_custom_svg, $fb_custom_img, $fb_default_svg); ?>
                <?php if ($button_style !== 'icon_only'): ?>
                    <span class="align-share-label"><?php _e('Facebook', 'align-function'); ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if ($show_x): ?>
            <a class="align-share-btn align-share-x" href="<?php echo esc_url($x_url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e('Share on X', 'align-function'); ?>">
                <?php echo align_get_share_icon_markup($x_icon_type, $x_custom_svg, $x_custom_img, $x_default_svg); ?>
                <?php if ($button_style !== 'icon_only'): ?>
                    <span class="align-share-label"><?php _e('X', 'align-function'); ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if ($show_linkedin): ?>
            <a class="align-share-btn align-share-linkedin" href="<?php echo esc_url($linkedin_url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e('Share on LinkedIn', 'align-function'); ?>">
                <?php echo align_get_share_icon_markup($li_icon_type, $li_custom_svg, $li_custom_img, $li_default_svg); ?>
                <?php if ($button_style !== 'icon_only'): ?>
                    <span class="align-share-label"><?php _e('LinkedIn', 'align-function'); ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if ($show_email): ?>
            <a class="align-share-btn align-share-email" href="<?php echo esc_url($email_url); ?>" aria-label="<?php esc_attr_e('Share via Email', 'align-function'); ?>">
                <?php echo align_get_share_icon_markup($em_icon_type, $em_custom_svg, $em_custom_img, $em_default_svg); ?>
                <?php if ($button_style !== 'icon_only'): ?>
                    <span class="align-share-label"><?php _e('Email', 'align-function'); ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if ($show_copy): ?>
            <button type="button" class="align-share-btn align-share-copy" data-share-url="<?php echo esc_url(get_permalink()); ?>" aria-label="<?php esc_attr_e('Copy Link', 'align-function'); ?>">
                <?php echo align_get_share_icon_markup($cp_icon_type, $cp_custom_svg, $cp_custom_img, $cp_default_svg); ?>
                <?php if ($button_style !== 'icon_only'): ?>
                    <span class="align-share-label"><?php _e('Copy Link', 'align-function'); ?></span>
                <?php endif; ?>
                <span class="align-share-toast"><?php _e('Copied!', 'align-function'); ?></span>
            </button>
        <?php endif; ?>
    </div>
</div>
