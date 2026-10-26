/**
 * Align Builder - WordPress Components Entrypoint (Backward Compatibility Bridge)
 * Decoupled into modular components under libs/builder/components/wordpress/
 * 
 * @package Align
 * @see libs/builder/components/wordpress/wp-base.js
 * @see libs/builder/components/wordpress/wp-shortcode.js
 * @see libs/builder/components/wordpress/wp-gutenberg-block.js
 * @see libs/builder/components/wordpress/wp-post-content.js
 * @see libs/builder/components/wordpress/wp-live-search.js
 */

(function() {
    // If components already registered via modular files, skip redundant execution
    if (typeof Vvveb !== "undefined" && Vvveb.Components && Vvveb.Components.get("wordpress/shortcode")) {
        return;
    }

    // Fallback load script elements if modular scripts have not executed
    const scriptBase = (typeof Vvveb !== "undefined" && Vvveb.baseUrl) ? Vvveb.baseUrl : 'libs/builder/';
    const modules = [
        'components/wordpress/wp-base.js',
        'components/wordpress/wp-shortcode.js',
        'components/wordpress/wp-gutenberg-block.js',
        'components/wordpress/wp-post-content.js',
        'components/wordpress/wp-live-search.js'
    ];

    modules.forEach(function(mod) {
        if (!document.querySelector(`script[src*="${mod}"]`)) {
            const script = document.createElement('script');
            script.src = scriptBase + mod;
            script.async = false;
            document.head.appendChild(script);
        }
    });
})();
