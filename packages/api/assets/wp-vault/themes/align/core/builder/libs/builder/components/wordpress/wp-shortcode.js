/**
 * Align Builder - WordPress Shortcode Component
 * @package Align
 */

if (typeof Vvveb !== "undefined") {
    const defaultPlaceholder = (window.AlignWpPlaceholders && window.AlignWpPlaceholders.shortcode) || '[gallery]';

    // 1. Register Component
    Vvveb.Components.extend("_base", "wordpress/shortcode", {
        name: "WP Shortcode",
        attributes: ["data-component-shortcode"],
        image: "icons/code.svg",
        allowChildren: false,
        isLeaf: true,
        dragHtml: '<div class="w-100 p-3 border border-primary rounded text-center bg-light"><strong>[WP Shortcode]</strong></div>',
        html: '<div data-component-shortcode data-shortcode="[gallery]">' + defaultPlaceholder + '</div>',
        
        afterDrop: function(node) {
            let val = (node && node.getAttribute ? node.getAttribute('data-shortcode') : '') || '[gallery]';
            node.textContent = val;
            if (window.AlignBuilderModules && window.AlignBuilderModules.WpComponentsPreview) {
                window.AlignBuilderModules.WpComponentsPreview.renderNodePreview(node, true, 0);
            }
            return node;
        },

        init: function(node) {
            if (node) {
                const shortcodeVal = (node.getAttribute('data-shortcode') || '').trim();
                if (!node.textContent || !node.textContent.trim()) {
                    node.textContent = shortcodeVal || '[gallery]';
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
            name: "Shortcode Text",
            key: "shortcode",
            htmlAttr: "data-shortcode",
            inputtype: TextareaInput,
            data: {
                rows: 3
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

                    // Auto-assign instance if duplicate reusable block shortcode is entered on canvas
                    const isReuse = /\[(?:reuse-block|reuse_block|align_block|align_reusable_block|reusable_block)\b/i.test(value);
                    if (isReuse) {
                        const matchId = value.match(/id=["']?(\d+)["']?/i);
                        const matchSlug = value.match(/\bslug=["']?([^"'\s\]]+)["']?/i);
                        const matchInst = value.match(/\b(?:instance|instance_id|key)=["']?([a-zA-Z0-9_-]+)["']?/i);
                        const blockRef = (matchId && matchId[1]) || (matchSlug && matchSlug[1]);

                        if (blockRef && !matchInst && window.Vvveb?.Builder?.frameDoc) {
                            const siblings = Array.from(window.Vvveb.Builder.frameDoc.querySelectorAll('[data-component-shortcode], [data-component-wp-block]')).filter(el => {
                                if (el === node) return false;
                                const sc = el.getAttribute('data-shortcode') || el.getAttribute('data-wp-block') || '';
                                return sc.includes(blockRef);
                            });
                            if (siblings.length > 0) {
                                const newInst = 'inst_' + (siblings.length + 1);
                                value = value.replace(/\]$/, ` instance="${newInst}"]`);
                                node.setAttribute('data-instance-id', newInst);
                                if (input && input.value !== undefined) input.value = value;
                            }
                        }
                    }

                    node.setAttribute('data-shortcode', value);
                    node.textContent = value || '[gallery]';

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
        }, {
            name: "",
            key: "instance_hint",
            inline: false,
            col: 12,
            inputtype: { ...Input, ...{
                init: function() {
                    let div = document.createElement("div");
                    div.className = "mb-2 mt-1";
                    div.innerHTML = '<small class="text-muted" style="font-size: 10.5px; display: block; line-height: 1.35; background: rgba(13, 110, 253, 0.08); border-left: 3px solid #0d6efd; padding: 4px 8px; border-radius: 2px;"><i class="la la-info-circle text-primary me-1"></i><strong>Tip:</strong> Add <code>instance="name"</code> in shortcode to customize multiple instances independently.</small>';
                    this.element = div;
                    return div;
                }
            }}
        }]
    });

    // 2. Register Block Preset
    if (Vvveb.Blocks) {
        Vvveb.Blocks.add("wordpress/shortcode", {
            name: "WP Shortcode",
            image: "icons/code.svg",
            dragHtml: '<div class="w-100 p-3 border border-primary rounded text-center bg-light"><strong>[WP Shortcode]</strong></div>',
            html: '<div data-component-shortcode data-shortcode="[gallery]">' + defaultPlaceholder + '</div>'
        });
    }
}
