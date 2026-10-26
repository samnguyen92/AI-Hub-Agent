/**
 * Align Builder - WordPress Language Switcher Component
 *
 * Provides visual insertion, inspector properties, and live Shadow DOM preview
 * for the [align-language-switcher] shortcode.
 *
 * @package Align
 */

if (typeof Vvveb !== "undefined") {
    const defaultPlaceholder = (window.AlignWpPlaceholders && window.AlignWpPlaceholders.languageSwitcher) || '[align-language-switcher]';

    /**
     * Rebuild and synchronize shortcode text from individual component attributes.
     */
    function rebuildLangShortcode(node) {
        if (!node) return;
        const type     = node.getAttribute('data-lang-type') || 'dropdown';
        const flags    = node.getAttribute('data-lang-flags') ?? '1';
        const names    = node.getAttribute('data-lang-names') ?? '1';
        const code     = node.getAttribute('data-lang-code')  ?? '0';
        const btnLabel = node.getAttribute('data-lang-btn-label') || 'auto';
        const cls      = (node.getAttribute('data-lang-class') || '').trim();

        let sc = `[align-language-switcher type="${type}"`;
        if (flags !== '1')       sc += ` show_flags="${flags}"`;
        if (names !== '1')       sc += ` show_names="${names}"`;
        if (code !== '0')        sc += ` show_code="${code}"`;
        if (btnLabel !== 'auto') sc += ` button_label="${btnLabel}"`;
        if (cls)                 sc += ` class="${cls}"`;
        sc += `]`;

        node.setAttribute('data-shortcode', sc);
        node.textContent = sc;

        if (window.AlignBuilderModules && window.AlignBuilderModules.WpComponentsPreview) {
            window.AlignBuilderModules.WpComponentsPreview.renderNodePreview(node, true, 0);
        }
    }

    // 1. Register Component
    Vvveb.Components.extend("_base", "wordpress/language-switcher", {
        name: "Language Switcher",
        attributes: ["data-component-lang-switch"],
        image: "icons/flag.svg",
        allowChildren: false,
        isLeaf: true,
        dragHtml: '<div class="w-100 p-2 border border-info rounded text-center bg-light text-info" style="font-size:12px;font-weight:600;">[🌐 Language Switcher]</div>',
        html: '<div data-component-lang-switch data-component-shortcode data-shortcode="[align-language-switcher type=\'dropdown\']" data-lang-type="dropdown" data-lang-flags="1" data-lang-names="1" data-lang-code="0" data-lang-btn-label="auto">' + defaultPlaceholder + '</div>',

        afterDrop: function(node) {
            if (node) {
                const sc = node.getAttribute('data-shortcode') || defaultPlaceholder;
                node.textContent = sc;
                if (window.AlignBuilderModules && window.AlignBuilderModules.WpComponentsPreview) {
                    window.AlignBuilderModules.WpComponentsPreview.renderNodePreview(node, true, 0);
                }
            }
            return node;
        },

        init: function(node) {
            if (node) {
                const sc = node.getAttribute('data-shortcode') || defaultPlaceholder;
                if (!node.textContent || !node.textContent.trim()) {
                    node.textContent = sc;
                }
                if (window.AlignBuilderModules && window.AlignBuilderModules.WpComponentsPreview) {
                    window.AlignBuilderModules.WpComponentsPreview.renderNodePreview(node, false, 0);
                }
            }
            if (window.AlignBuilderModules && window.AlignBuilderModules.WpReuseBlockOverwrite) {
                window.AlignBuilderModules.WpReuseBlockOverwrite.handleNodeSelection(node);
            }
        },

        properties: [
            {
                name: "Display Style",
                key: "data-lang-type",
                htmlAttr: "data-lang-type",
                inputtype: SelectInput,
                data: {
                    options: [
                        { value: "dropdown", text: "Dropdown Menu" },
                        { value: "pills",    text: "Pills (Inline Badges)" },
                        { value: "flags",    text: "Flags Only" },
                        { value: "list",     text: "Vertical List" }
                    ]
                },
                onChange: function(node, value) {
                    if (node) {
                        const val = (typeof value === "object" && value !== null) ? (value.value || 'dropdown') : String(value || 'dropdown');
                        node.setAttribute('data-lang-type', val);
                        rebuildLangShortcode(node);
                    }
                    return node;
                }
            },
            {
                name: "Show Flags",
                key: "data-lang-flags",
                htmlAttr: "data-lang-flags",
                inputtype: SelectInput,
                data: {
                    options: [
                        { value: "1", text: "Yes — Show Flags" },
                        { value: "0", text: "No — Hide Flags" }
                    ]
                },
                onChange: function(node, value) {
                    if (node) {
                        const val = (typeof value === "object" && value !== null) ? (value.value || '1') : String(value || '1');
                        node.setAttribute('data-lang-flags', val);
                        rebuildLangShortcode(node);
                    }
                    return node;
                }
            },
            {
                name: "Show Language Names",
                key: "data-lang-names",
                htmlAttr: "data-lang-names",
                inputtype: SelectInput,
                data: {
                    options: [
                        { value: "1", text: "Yes — Show Names" },
                        { value: "0", text: "No — Delete / Hide Names" }
                    ]
                },
                onChange: function(node, value) {
                    if (node) {
                        const val = (typeof value === "object" && value !== null) ? (value.value || '1') : String(value || '1');
                        node.setAttribute('data-lang-names', val);
                        rebuildLangShortcode(node);
                    }
                    return node;
                }
            },
            {
                name: "Button Label (Dropdown)",
                key: "data-lang-btn-label",
                htmlAttr: "data-lang-btn-label",
                inputtype: SelectInput,
                data: {
                    options: [
                        { value: "auto", text: "Auto (Code in Button, Name in Menu)" },
                        { value: "code", text: "Code Only (EN, ZH)" },
                        { value: "flag", text: "Flag Only (Hide Name & Code)" },
                        { value: "name", text: "Full Language Name" }
                    ]
                },
                onChange: function(node, value) {
                    if (node) {
                        const val = (typeof value === "object" && value !== null) ? (value.value || 'auto') : String(value || 'auto');
                        node.setAttribute('data-lang-btn-label', val);
                        rebuildLangShortcode(node);
                    }
                    return node;
                }
            },
            {
                name: "Show Code (EN, ZH)",
                key: "data-lang-code",
                htmlAttr: "data-lang-code",
                inputtype: SelectInput,
                data: {
                    options: [
                        { value: "0", text: "No — Hide Code" },
                        { value: "1", text: "Yes — Show Code" }
                    ]
                },
                onChange: function(node, value) {
                    if (node) {
                        const val = (typeof value === "object" && value !== null) ? (value.value || '0') : String(value || '0');
                        node.setAttribute('data-lang-code', val);
                        rebuildLangShortcode(node);
                    }
                    return node;
                }
            },
            {
                name: "Custom CSS Class",
                key: "data-lang-class",
                htmlAttr: "data-lang-class",
                inputtype: TextInput,
                data: {
                    placeholder: "e.g. my-lang-nav"
                },
                onChange: function(node, value) {
                    if (node) {
                        const val = (typeof value === "object" && value !== null) ? (value.value || '') : String(value || '');
                        node.setAttribute('data-lang-class', val);
                        rebuildLangShortcode(node);
                    }
                    return node;
                }
            }
        ]
    });

    // 2. Register Block Preset in Add Component Modal
    if (Vvveb.Blocks) {
        Vvveb.Blocks.add("wordpress/language-switcher", {
            name: "Language Switcher",
            image: "icons/flag.svg",
            dragHtml: '<div class="w-100 p-2 border border-info rounded text-center bg-light text-info" style="font-size:12px;font-weight:600;">[🌐 Language Switcher]</div>',
            html: '<div data-component-lang-switch data-component-shortcode data-shortcode="[align-language-switcher type=\'dropdown\']" data-lang-type="dropdown" data-lang-flags="1" data-lang-names="1" data-lang-code="0" data-lang-btn-label="auto">' + defaultPlaceholder + '</div>'
        });
    }
}
