/**
 * Align Builder - WordPress Post Content Placeholder Component
 * @package Align
 */

if (typeof Vvveb !== "undefined") {
    const defaultPlaceholder = (window.AlignWpPlaceholders && window.AlignWpPlaceholders.postContent) || 'post-content';

    // 1. Register Component
    Vvveb.Components.extend("_base", "wordpress/post-content", {
        name: "WP Post Content",
        attributes: ["data-component-post-content"],
        image: "icons/paragraph.svg",
        allowChildren: false,
        isLeaf: true,
        dragHtml: '<div class="w-100 p-2 border border-primary rounded text-center bg-light text-primary" style="font-size:12px;font-weight:600;">[📄 WP Post Content]</div>',
        html: '<div data-component-post-content>' + defaultPlaceholder + '</div>',

        afterDrop: function(node) {
            if (node) {
                node.textContent = defaultPlaceholder;
                if (window.AlignBuilderModules && window.AlignBuilderModules.WpComponentsPreview) {
                    window.AlignBuilderModules.WpComponentsPreview.renderNodePreview(node, true, 0);
                }
            }
            return node;
        },

        init: function(node) {
            if (node) {
                if (!node.textContent || !node.textContent.trim()) {
                    node.textContent = defaultPlaceholder;
                }
                if (window.AlignBuilderModules && window.AlignBuilderModules.WpComponentsPreview) {
                    window.AlignBuilderModules.WpComponentsPreview.renderNodePreview(node, false, 0);
                }
            }
            if (window.AlignBuilderModules && window.AlignBuilderModules.WpReuseBlockOverwrite) {
                window.AlignBuilderModules.WpReuseBlockOverwrite.handleNodeSelection(node);
            }
        }
    });

    // 2. Register Block Preset
    if (Vvveb.Blocks) {
        Vvveb.Blocks.add("wordpress/post-content", {
            name: "WP Post Content",
            image: "icons/paragraph.svg",
            dragHtml: '<div class="w-100 p-2 border border-primary rounded text-center bg-light text-primary" style="font-size:12px;font-weight:600;">[📄 WP Post Content]</div>',
            html: '<div data-component-post-content>' + defaultPlaceholder + '</div>'
        });
    }
}
