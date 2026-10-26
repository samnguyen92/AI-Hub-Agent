<?php
$tag = isset($attributes['tag']) ? $attributes['tag'] : 'div';
$elementId = isset($attributes['elementId']) ? trim($attributes['elementId']) : '';
$dataEditId = isset($attributes['dataEditId']) ? trim($attributes['dataEditId']) : '';

// Static tracker logic for $elementId to prevent duplicate HTML IDs on frontend render
if (!function_exists('align_track_element_id')) {
    function align_track_element_id($id) {
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
$elementId = align_track_element_id($elementId);
$dataEditId = align_track_element_id($dataEditId);

// Check if current user is logged in and is not a subscriber, and quick edit is enabled in settings
$show_edit_id = false;
if (is_user_logged_in() && get_option('align_enable_frontend_quick_edit', 1)) {
    $current_user = wp_get_current_user();
    $roles = (array) $current_user->roles;
    if (!empty($roles) && !in_array('subscriber', $roles)) {
        $show_edit_id = true;
    }
}


// Only allow data-edit-id on text/paragraph level tags (including links and inline styling tags)
$allowed_edit_tags = array('p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span', 'strong', 'em', 'b', 'i', 'u', 'li', 'blockquote', 'a', 'code', 'pre', 'sub', 'sup');
$is_allowed_tag = in_array(strtolower($tag), $allowed_edit_tags);

$customAttributes = isset($attributes['customAttributes']) ? trim($attributes['customAttributes']) : '';

$href = isset($attributes['href']) ? trim($attributes['href']) : '';
$hrefId = isset($attributes['hrefId']) ? intval($attributes['hrefId']) : 0;
if (class_exists('AlignSearchOptimizer')) {
    $href = AlignSearchOptimizer::resolveUrl($href, $hrefId);
}

$target = isset($attributes['target']) ? trim($attributes['target']) : '';
$rel = isset($attributes['rel']) ? trim($attributes['rel']) : '';
$ariaLabel = isset($attributes['ariaLabel']) ? trim($attributes['ariaLabel']) : '';

$svgInnerCode = isset($attributes['svgInnerCode']) ? $attributes['svgInnerCode'] : '';

$textContent = isset($attributes['content']) ? $attributes['content'] : '';

// Retrieve standard Gutenberg wrapper attributes (className, styles like color/typography/spacing)
$wrapper_attributes = get_block_wrapper_attributes();

// Remove WordPress's default 'wp-block-align-element' class to keep it clean for custom CSS
$wrapper_attributes = str_replace('wp-block-align-element', '', $wrapper_attributes);
// Clean up double spaces if any
$wrapper_attributes = str_replace('class=" ', 'class="', $wrapper_attributes);

// Manually inject className since customClassName support is disabled
$custom_class = isset($attributes['className']) ? trim($attributes['className']) : '';
if (!empty($dataEditId) && !$is_allowed_tag) {
    $unique_css_class = str_replace('edit-', 'css-', $dataEditId);
    $custom_class = !empty($custom_class) ? $unique_css_class . ' ' . $custom_class : $unique_css_class;
}

$parentEditId = ($block && isset($block->context['align/parentEditId'])) ? $block->context['align/parentEditId'] : '';
if (!empty($parentEditId)) {
    $parent_group_class = str_replace('edit-', 'css-group-', $parentEditId);
    $custom_class = !empty($custom_class) ? $parent_group_class . ' ' . $custom_class : $parent_group_class;
}

if (!empty($custom_class)) {
    if (strpos($wrapper_attributes, 'class="') !== false) {
        $wrapper_attributes = str_replace('class="', 'class="' . esc_attr($custom_class) . ' ', $wrapper_attributes);
    } else {
        $wrapper_attributes .= ' class="' . esc_attr($custom_class) . '"';
    }
}

// Clean up empty class attributes or double spaces
$wrapper_attributes = str_replace('class=""', '', $wrapper_attributes);
$wrapper_attributes = preg_replace('/class="\s*"/', '', $wrapper_attributes);
$wrapper_attributes = preg_replace('/\s+/', ' ', $wrapper_attributes);
$wrapper_attributes = trim($wrapper_attributes);

// Construct custom attributes string
$extra_attrs_array = [];
if (!empty($elementId)) {
    $extra_attrs_array[] = 'id="' . esc_attr($elementId) . '"';
}

if (!empty($dataEditId) && $is_allowed_tag && $show_edit_id) {
    // Add unique data-edit-id for quick edit and custom CSS targeting (restricted to allowed text/paragraph tags)
    $extra_attrs_array[] = 'data-edit-id="' . esc_attr($dataEditId) . '"';
}

$enableAnchor = isset($attributes['enableAnchor']) ? (bool) $attributes['enableAnchor'] : false;
$anchorId = isset($attributes['anchorId']) ? trim($attributes['anchorId']) : '';

if ($enableAnchor && !empty($anchorId)) {
    $extra_attrs_array[] = 'data-anchor-target="' . esc_attr($anchorId) . '"';
}

if ($tag === 'a') {
    $final_href = $href;
    if ($enableAnchor && !empty($anchorId) && empty($final_href)) {
        $final_href = '#' . $anchorId;
    }
    if (!empty($final_href)) $extra_attrs_array[] = 'href="' . esc_attr($final_href) . '"';
    if (!empty($target)) $extra_attrs_array[] = 'target="' . esc_attr($target) . '"';
    if (!empty($rel)) $extra_attrs_array[] = 'rel="' . esc_attr($rel) . '"';
    if (!empty($ariaLabel)) $extra_attrs_array[] = 'aria-label="' . esc_attr($ariaLabel) . '"';
}


if (!empty($customAttributes)) {
    // Append user's raw custom attributes (we assume user enters valid syntax like data-aos="fade-up")
    $extra_attrs_array[] = $customAttributes;
}

$extra_attrs = implode(' ', $extra_attrs_array);

if (!empty($extra_attrs)) {
    $wrapper_attributes .= ' ' . $extra_attrs;
}

$container_tags = ['div', 'section', 'article', 'header', 'footer', 'main', 'aside', 'nav', 'a', 'ul', 'ol', 'li', 'button'];
$self_closing_tags = ['hr', 'br', 'input'];
$type = isset($attributes['type']) ? $attributes['type'] : 'inner'; // Default is inner based on block.json
$is_container = in_array($tag, $container_tags) || $type === 'inner';
$is_self_closing = in_array($tag, $self_closing_tags);

if ($tag === 'svg') {
    // If svgInnerCode contains the full <svg> wrapper, extract only the inner paths
    $trimmedSvg = trim($svgInnerCode);
    if (strpos($trimmedSvg, '<svg') === 0 && preg_match('/<svg([^>]*)>(.*)<\/svg>/is', $trimmedSvg, $matches)) {
        $inner_html = $matches[2];
        $svg_attrs_string = $matches[1];
        
        // Force width and height to 100% for responsive scaling in CSS containers
        if (strpos($wrapper_attributes, 'width=') === false) {
            $wrapper_attributes .= ' width="100%"';
        }
        if (strpos($wrapper_attributes, 'height=') === false) {
            $wrapper_attributes .= ' height="100%"';
        }
        
        // Auto-extract viewBox, stroke, fill, stroke-width, etc. from original <svg> tag
        preg_match_all('/([a-zA-Z\-]+)\s*=\s*["\']([^"\']*)["\']/i', $svg_attrs_string, $attr_matches, PREG_SET_ORDER);
        foreach ($attr_matches as $attr) {
            $attr_name = $attr[1];
            $lower_name = strtolower($attr_name);
            $attr_value = $attr[2];
            
            // Skip class, id, style as they are handled by Gutenberg block wrapper attributes
            // Also skip width/height since we set them to 100%
            if (in_array($lower_name, ['class', 'id', 'style', 'width', 'height'])) {
                continue;
            }
            
            // Ensure viewBox is camelCased
            if ($lower_name === 'viewbox') {
                $attr_name = 'viewBox';
            }
            
            // Prioritize viewBox from the actual SVG markup since it defines the path coordinate system
            if ($lower_name === 'viewbox') {
                // Strip existing viewbox from wrapper_attributes (if any was added from customAttributes)
                $wrapper_attributes = preg_replace('/viewbox\s*=\s*["\'][^"\']*["\']/i', '', $wrapper_attributes);
                $wrapper_attributes .= ' ' . $attr_name . '="' . esc_attr($attr_value) . '"';
            } else {
                // Append other attributes if not already defined (customAttributes takes priority for stroke/fill/etc.)
                if (strpos($wrapper_attributes, strtolower($attr_name) . '=') === false) {
                    $wrapper_attributes .= ' ' . $attr_name . '="' . esc_attr($attr_value) . '"';
                }
            }
        }
    } else {
        $inner_html = $svgInnerCode;
        if (strpos($wrapper_attributes, 'width=') === false) {
            $wrapper_attributes .= ' width="100%"';
        }
        if (strpos($wrapper_attributes, 'height=') === false) {
            $wrapper_attributes .= ' height="100%"';
        }
    }
} else {
    $inner_html = $is_container ? $textContent . $content : $textContent;
}

// If the block actually contains child blocks (InnerBlocks) and the current tag is not a container tag,
// automatically change the tag to 'div' to avoid incorrect nested DOM tag HTML errors on the frontend.
if (!empty(trim($content)) && !in_array($tag, $container_tags)) {
    $tag = 'div';
}

// Ensure safety against totally invalid tags
$allowed_tags = array_merge($container_tags, $self_closing_tags, ['svg', 'p', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6']);
if (!in_array($tag, $allowed_tags)) {
    $tag = 'div';
}

if ($is_self_closing) {
    echo sprintf(
        '<%1$s %2$s />',
        esc_html($tag),
        $wrapper_attributes
    );
} else {
    echo sprintf(
        '<%1$s %2$s>%3$s</%1$s>',
        esc_html($tag),
        $wrapper_attributes,
        $inner_html
    );
}
?>
