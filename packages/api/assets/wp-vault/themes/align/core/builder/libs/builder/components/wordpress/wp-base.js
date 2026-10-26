/**
 * Align Builder - WordPress Components Group Registration & Shared Placeholders
 * @package Align
 */

if (typeof Vvveb !== "undefined") {
    // 1. Register under ComponentsGroup
    if (Vvveb.ComponentsGroup) {
        Vvveb.ComponentsGroup['WordPress'] = [
            "wordpress/shortcode",
            "wordpress/block",
            "wordpress/post-content",
            "wordpress/live-search",
            "wordpress/language-switcher"
        ];
    }

    // 2. Register under BlocksGroup
    if (Vvveb.BlocksGroup) {
        Vvveb.BlocksGroup['WordPress'] = [
            "wordpress/shortcode",
            "wordpress/block",
            "wordpress/post-content",
            "wordpress/live-search",
            "wordpress/language-switcher"
        ];
    }

    // Shared Placeholders
    window.AlignWpPlaceholders = window.AlignWpPlaceholders || {
        shortcode: '[gallery]',
        block: 'paragraph',
        postContent: 'post-content',
        liveSearch: 'Search...',
        languageSwitcher: '[align-language-switcher]'
    };
}
