/**
 * Align Builder - WordPress Gutenberg Block Component
 * @package Align
 */

if (typeof Vvveb !== "undefined") {
    const defaultPlaceholder = (window.AlignWpPlaceholders && window.AlignWpPlaceholders.block) || 'paragraph';

    // 1. Register Component
    Vvveb.Components.extend("_base", "wordpress/block", {
        name: "WP Gutenberg Block",
        attributes: ["data-component-wp-block"],
        image: "icons/container.svg",
        allowChildren: false,
        isLeaf: true,
        dragHtml: '<div class="w-100 p-3 border border-success rounded text-center bg-light"><strong>[Gutenberg Block]</strong></div>',
        html: '<div data-component-wp-block data-wp-block="<!-- wp:paragraph --><p>Gutenberg Block Content</p><!-- /wp:paragraph -->">' + defaultPlaceholder + '</div>',
        
        afterDrop: function(node) {
            let val = (node && node.getAttribute ? node.getAttribute('data-wp-block') : '') || '<!-- wp:paragraph --><p>Gutenberg Block Content</p><!-- /wp:paragraph -->';
            const nameMatch = val && val.match(/<!--\s*wp:([\w/\-]+)/);
            node.textContent = nameMatch ? nameMatch[1] : (val ? 'custom' : 'paragraph');
            if (window.AlignBuilderModules && window.AlignBuilderModules.WpComponentsPreview) {
                window.AlignBuilderModules.WpComponentsPreview.renderNodePreview(node, true, 0);
            }
            return node;
        },

        init: function(node) {
            if (node) {
                const blockVal = (node.getAttribute('data-wp-block') || '').trim();
                const nameMatch = blockVal && blockVal.match(/<!--\s*wp:([\w/\-]+)/);
                if (!node.textContent || !node.textContent.trim()) {
                    node.textContent = nameMatch ? nameMatch[1] : (blockVal ? 'custom' : 'paragraph');
                }
                if (window.AlignBuilderModules && window.AlignBuilderModules.WpComponentsPreview) {
                    window.AlignBuilderModules.WpComponentsPreview.renderNodePreview(node, false, 0);
                }
            }
            if (window.AlignBuilderModules && window.AlignBuilderModules.WpReuseBlockOverwrite) {
                window.AlignBuilderModules.WpReuseBlockOverwrite.handleNodeSelection(node);
            }
        },

        properties: [{
            name: "Block Markup",
            key: "wp-block",
            htmlAttr: "data-wp-block",
            inputtype: TextareaInput,
            data: {
                rows: 4
            },
            inline: false,
            col: 12,
            onChange: function(node, value, input) {
                if (node) {
                    // Normalize value if passed as an object or input element
                    if (typeof value === "object" && value !== null) {
                        value = value.value !== undefined ? value.value : (input && input.value !== undefined ? input.value : String(value));
                    }
                    value = value ? value.trim() : '';
                    node.setAttribute('data-wp-block', value);
                    const nameMatch = value && value.match(/<!--\s*wp:([\w/\-]+)/);
                    node.textContent = nameMatch ? nameMatch[1] : (value ? 'custom' : 'paragraph');

                    if (window.AlignBuilderModules && window.AlignBuilderModules.WpComponentsPreview) {
                        window.AlignBuilderModules.WpComponentsPreview.renderNodePreview(node, true, 300);
                    }

                    if (window.AlignBuilderModules && window.AlignBuilderModules.WpReuseBlockOverwrite) {
                        setTimeout(function() {
                            window.AlignBuilderModules.WpReuseBlockOverwrite.handleNodeSelection(node, true);
                        }, 100);
                    }
                }
                return node;
            }
        }]
    });

    // 2. Register Block Preset
    if (Vvveb.Blocks) {
        Vvveb.Blocks.add("wordpress/block", {
            name: "WP Gutenberg Block",
            image: "icons/container.svg",
            dragHtml: '<div class="w-100 p-3 border border-success rounded text-center bg-light"><strong>[Gutenberg Block]</strong></div>',
            html: '<div data-component-wp-block data-wp-block="<!-- wp:paragraph --><p>Gutenberg Block Content</p><!-- /wp:paragraph -->">' + defaultPlaceholder + '</div>'
        });
    }
}
