/**
 * Align Builder - WordPress Live Search Component
 * Provides complete visual inspector controls cloned 1:1 from Align Live Search Gutenberg block.
 * Uses clean stacked layout without redundant section headers.
 *
 * @package Align
 */

if (typeof Vvveb !== "undefined") {

    /**
     * Helper to safely extract JSON attributes from data-wp-block attribute.
     *
     * @param {HTMLElement} node DOM Node.
     * @return {Object} Parsed attributes object.
     */
    function getLiveSearchAttributes(node) {
        if (!node) return {};
        const rawMarkup = (node.getAttribute('data-wp-block') || '').trim();
        const match = rawMarkup.match(/<!--\s*wp:align\/live-search\s*(\{[\s\S]*?\})\s*\/-->/);
        if (match && match[1]) {
            try {
                return JSON.parse(match[1]);
            } catch (e) {
                console.error('[Align Builder LiveSearch] Error parsing JSON attributes:', e);
            }
        }
        return {};
    }

    /**
     * Helper to serialize attributes back to Gutenberg comment format and trigger live preview.
     *
     * @param {HTMLElement} node DOM Node.
     * @param {string} key Attribute key.
     * @param {*} val Attribute value.
     * @return {HTMLElement} Updated DOM Node.
     */
    function updateLiveSearchAttribute(node, key, val) {
        if (!node) return node;
        const attrs = getLiveSearchAttributes(node);
        if (val === undefined || val === null) {
            delete attrs[key];
        } else {
            attrs[key] = val;
        }

        const newMarkup = '<!-- wp:align/live-search ' + JSON.stringify(attrs) + ' /-->';
        node.setAttribute('data-wp-block', newMarkup);

        // Update raw markup input in sidebar if present
        const rawInput = document.querySelector('textarea[data-key="rawBlockMarkup"]');
        if (rawInput) {
            rawInput.value = newMarkup;
        }

        if (window.AlignBuilderModules && window.AlignBuilderModules.WpComponentsPreview) {
            window.AlignBuilderModules.WpComponentsPreview.renderNodePreview(node, true, 300);
        }
        return node;
    }

    /**
     * Helper to show/hide category filter child fields based on enableCategoryFilter toggle.
     *
     * @param {boolean} isEnabled Whether category query is enabled.
     */
    function toggleCategoryFieldsVisibility(isEnabled) {
        const keys = ['categoryFilterTaxonomy', 'categoryFilterAllLabel', 'categoryFilterTerms'];
        keys.forEach(function(k) {
            const row = document.querySelector('.mb-2[data-key="' + k + '"]');
            if (row) {
                row.style.display = isEnabled ? '' : 'none';
            }
        });
    }

    /**
     * Get dynamic list of taxonomies across WordPress & e-commerce plugins (WooCommerce, FluentCart, EDD, etc.)
     *
     * @param {boolean} includeEmptyOption Whether to prepend empty option.
     * @return {Array} List of options for SelectInput.
     */
    function getDynamicTaxonomyOptions(includeEmptyOption) {
        const options = [];
        if (includeEmptyOption) {
            options.push({ value: "", text: "-- No Taxonomy Filter --" });
        }
        const taxList = window.alignBlockTaxonomies || [];
        if (taxList.length > 0) {
            taxList.forEach(function(tax) {
                const ptStr = (tax.post_types && tax.post_types.length) ? ' (' + tax.post_types.join(', ') + ')' : '';
                options.push({
                    value: tax.slug,
                    text: tax.name + ' [' + tax.slug + ']' + ptStr
                });
            });
        } else {
            options.push(
                { value: "category", text: "Categories (category) [post]" },
                { value: "product-categories", text: "FluentCart Categories (product-categories) [fluent-products]" },
                { value: "product_cat", text: "WooCommerce Categories (product_cat) [product]" },
                { value: "download_category", text: "EDD Categories (download_category) [download]" },
                { value: "product-brands", text: "FluentCart Brands (product-brands) [fluent-products]" },
                { value: "post_tag", text: "Tags (post_tag) [post]" }
            );
        }
        options.push({ value: "custom", text: "Custom Taxonomy Slug..." });
        return options;
    }

    /**
     * Get dynamic list of post types across WordPress & e-commerce plugins.
     *
     * @return {Array} List of options for SelectInput.
     */
    function getDynamicPostTypeOptions() {
        const options = [
            { value: "all", text: "All Public Post Types (Default)" }
        ];
        const ptList = window.alignBlockPostTypes || [];
        if (ptList.length > 0) {
            ptList.forEach(function(pt) {
                options.push({
                    value: pt.slug,
                    text: pt.name + ' (' + pt.slug + ')'
                });
            });
        } else {
            options.push(
                { value: "post", text: "Posts (post)" },
                { value: "page", text: "Pages (page)" },
                { value: "fluent-products", text: "FluentCart Products (fluent-products)" },
                { value: "product", text: "WooCommerce Products (product)" },
                { value: "download", text: "EDD Downloads (download)" },
                { value: "guide", text: "Guides (guide)" },
                { value: "post,page", text: "Posts & Pages" }
            );
        }
        options.push({ value: "custom", text: "Custom Post Types..." });
        return options;
    }

    // 1. Register Component with streamlined Inspector Panels
    Vvveb.Components.extend("_base", "wordpress/live-search", {
        name: "Live Search Settings",
        attributes: ["data-component-wp-live-search"],
        image: "icons/search.svg",
        allowChildren: false,
        isLeaf: true,
        dragHtml: '<div class="w-100 p-3 border border-primary rounded text-center bg-light"><strong>[WP Live Search]</strong></div>',
        html: '<div data-component-wp-block data-component-wp-live-search data-wp-block=\'<!-- wp:align/live-search {"placeholder":"Search...","postsPerPage":5,"enableLiveSearch":true,"allowModeToggle":true,"defaultMode":"live","showThumbnail":true,"showExcerpt":false,"showPostType":false} /-->\'>Live Search Block</div>',

        afterDrop: function(node) {
            if (node) {
                if (window.AlignBuilderModules && window.AlignBuilderModules.WpComponentsPreview) {
                    window.AlignBuilderModules.WpComponentsPreview.renderNodePreview(node, true, 0);
                }
            }
            return node;
        },

        init: function(node) {
            if (node) {
                if (window.AlignBuilderModules && window.AlignBuilderModules.WpComponentsPreview) {
                    window.AlignBuilderModules.WpComponentsPreview.renderNodePreview(node, false, 0);
                }
                const attrs = getLiveSearchAttributes(node);
                const isCatEnabled = attrs.enableCategoryFilter !== undefined ? Boolean(attrs.enableCategoryFilter) : false;
                setTimeout(function() {
                    toggleCategoryFieldsVisibility(isCatEnabled);
                }, 30);
            }
            if (window.AlignBuilderModules && window.AlignBuilderModules.WpReuseBlockOverwrite) {
                window.AlignBuilderModules.WpReuseBlockOverwrite.handleNodeSelection(node);
            }
        },

        properties: [
            // ==========================================
            // MAIN: SEARCH & QUERY SETTINGS
            // ==========================================
            {
                name: "Enable Live Search (AJAX)",
                key: "enableLiveSearch",
                inputtype: typeof ToggleInput !== "undefined" ? ToggleInput : (typeof CheckboxInput !== "undefined" ? CheckboxInput : "CheckboxInput"),
                inline: false,
                col: 12,
                data: {
                    on: "true",
                    off: "false",
                    defaultValue: true
                },
                init: function(node) {
                    const attrs = getLiveSearchAttributes(node);
                    return attrs.enableLiveSearch !== undefined ? Boolean(attrs.enableLiveSearch) : true;
                },
                onChange: function(node, value) {
                    const isChecked = (value === true || value === "true" || value === 1 || value === "1");
                    return updateLiveSearchAttribute(node, "enableLiveSearch", isChecked);
                }
            },
            {
                name: "Placeholder Text",
                key: "placeholder",
                inputtype: typeof TextInput !== "undefined" ? TextInput : "TextInput",
                inline: false,
                col: 12,
                data: {
                    defaultValue: "Search..."
                },
                init: function(node) {
                    const attrs = getLiveSearchAttributes(node);
                    return attrs.placeholder !== undefined ? attrs.placeholder : "Search...";
                },
                onChange: function(node, value) {
                    const val = typeof value === "object" && value !== null && value.value !== undefined ? value.value : value;
                    return updateLiveSearchAttribute(node, "placeholder", String(val || ''));
                }
            },
            {
                name: "Post Types",
                key: "selectedPostTypes",
                inputtype: typeof SelectInput !== "undefined" ? SelectInput : "SelectInput",
                inline: false,
                col: 12,
                data: {
                    get options() {
                        return getDynamicPostTypeOptions();
                    }
                },
                init: function(node) {
                    const attrs = getLiveSearchAttributes(node);
                    if (attrs.customPostType) return "custom";
                    const pts = attrs.selectedPostTypes;
                    if (Array.isArray(pts)) {
                        if (pts.includes('all')) return 'all';
                        return pts.join(',');
                    }
                    return attrs.postType || "all";
                },
                onChange: function(node, value) {
                    const val = typeof value === "object" && value !== null && value.value !== undefined ? value.value : String(value || 'all');
                    if (val === 'custom') {
                        return node;
                    }
                    const ptArray = val.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
                    return updateLiveSearchAttribute(node, "selectedPostTypes", ptArray);
                }
            },
            {
                name: "Custom Post Types (Slug)",
                key: "customPostType",
                inputtype: typeof TextInput !== "undefined" ? TextInput : "TextInput",
                inline: false,
                col: 12,
                data: {
                    placeholder: "e.g. fluent-products, product, event"
                },
                init: function(node) {
                    const attrs = getLiveSearchAttributes(node);
                    return attrs.customPostType || "";
                },
                onChange: function(node, value) {
                    const val = typeof value === "object" && value !== null && value.value !== undefined ? value.value : String(value || '');
                    if (val.trim()) {
                        const ptArray = val.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
                        updateLiveSearchAttribute(node, "customPostType", val.trim());
                        return updateLiveSearchAttribute(node, "selectedPostTypes", ptArray);
                    } else {
                        updateLiveSearchAttribute(node, "customPostType", undefined);
                        return updateLiveSearchAttribute(node, "selectedPostTypes", ["all"]);
                    }
                }
            },
            {
                name: "Filter by Taxonomy",
                key: "filterTaxonomy",
                inputtype: typeof SelectInput !== "undefined" ? SelectInput : "SelectInput",
                inline: false,
                col: 12,
                data: {
                    get options() {
                        return getDynamicTaxonomyOptions(true);
                    }
                },
                init: function(node) {
                    const attrs = getLiveSearchAttributes(node);
                    const currentTax = attrs.filterTaxonomy || "";
                    const dynamicOpts = getDynamicTaxonomyOptions(true);
                    const exists = dynamicOpts.some(function(o) { return o.value === currentTax; });
                    if (exists || currentTax === "") {
                        return currentTax;
                    }
                    return "custom";
                },
                onChange: function(node, value) {
                    const val = typeof value === "object" && value !== null && value.value !== undefined ? value.value : String(value || '');
                    if (val === 'custom') return node;
                    return updateLiveSearchAttribute(node, "filterTaxonomy", val);
                }
            },
            {
                name: "Custom Taxonomy Slug",
                key: "customTaxonomySlug",
                inputtype: typeof TextInput !== "undefined" ? TextInput : "TextInput",
                inline: false,
                col: 12,
                data: {
                    placeholder: "e.g. topic, brand, car_model"
                },
                init: function(node) {
                    const attrs = getLiveSearchAttributes(node);
                    const currentTax = attrs.filterTaxonomy || "";
                    if (!['', 'category', 'post_tag', 'product_cat'].includes(currentTax)) {
                        return currentTax;
                    }
                    return "";
                },
                onChange: function(node, value) {
                    const val = typeof value === "object" && value !== null && value.value !== undefined ? value.value : String(value || '');
                    if (val.trim()) {
                        return updateLiveSearchAttribute(node, "filterTaxonomy", val.trim());
                    }
                    return node;
                }
            },
            {
                name: "Filter Term IDs",
                key: "filterTermIds",
                inputtype: typeof TextInput !== "undefined" ? TextInput : "TextInput",
                inline: false,
                col: 12,
                data: {
                    placeholder: "e.g. 12, 45, 89 (Comma-separated)"
                },
                init: function(node) {
                    const attrs = getLiveSearchAttributes(node);
                    return attrs.filterTermIds || "";
                },
                onChange: function(node, value) {
                    const val = typeof value === "object" && value !== null && value.value !== undefined ? value.value : String(value || '');
                    return updateLiveSearchAttribute(node, "filterTermIds", val.trim());
                }
            },
            {
                name: "Max Results",
                key: "postsPerPage",
                inputtype: typeof RangeInput !== "undefined" ? RangeInput : (typeof NumberInput !== "undefined" ? NumberInput : "NumberInput"),
                inline: false,
                col: 12,
                data: {
                    min: 1,
                    max: 20,
                    step: 1,
                    defaultValue: 5
                },
                init: function(node) {
                    const attrs = getLiveSearchAttributes(node);
                    return attrs.postsPerPage !== undefined ? attrs.postsPerPage : 5;
                },
                onChange: function(node, value) {
                    const val = typeof value === "object" && value !== null && value.value !== undefined ? value.value : value;
                    const num = parseInt(val, 10) || 5;
                    return updateLiveSearchAttribute(node, "postsPerPage", num);
                }
            },
            {
                name: "Allow Mode Toggle",
                key: "allowModeToggle",
                inputtype: typeof ToggleInput !== "undefined" ? ToggleInput : (typeof CheckboxInput !== "undefined" ? CheckboxInput : "CheckboxInput"),
                inline: false,
                col: 12,
                data: {
                    on: "true",
                    off: "false",
                    defaultValue: true
                },
                init: function(node) {
                    const attrs = getLiveSearchAttributes(node);
                    return attrs.allowModeToggle !== undefined ? Boolean(attrs.allowModeToggle) : true;
                },
                onChange: function(node, value) {
                    const isChecked = (value === true || value === "true" || value === 1 || value === "1");
                    return updateLiveSearchAttribute(node, "allowModeToggle", isChecked);
                }
            },
            {
                name: "Default Search Mode",
                key: "defaultMode",
                inputtype: typeof SelectInput !== "undefined" ? SelectInput : "SelectInput",
                inline: false,
                col: 12,
                data: {
                    options: [
                        { value: "live", text: "Live Search (AJAX Dropdown)" },
                        { value: "normal", text: "Normal Search (Page Redirect)" }
                    ]
                },
                init: function(node) {
                    const attrs = getLiveSearchAttributes(node);
                    return attrs.defaultMode || "live";
                },
                onChange: function(node, value) {
                    const val = typeof value === "object" && value !== null && value.value !== undefined ? value.value : String(value || 'live');
                    return updateLiveSearchAttribute(node, "defaultMode", val);
                }
            },
            {
                name: "Enable Query by Categories",
                key: "enableCategoryFilter",
                inputtype: typeof ToggleInput !== "undefined" ? ToggleInput : (typeof CheckboxInput !== "undefined" ? CheckboxInput : "CheckboxInput"),
                inline: false,
                col: 12,
                data: {
                    on: "true",
                    off: "false",
                    defaultValue: false
                },
                init: function(node) {
                    const attrs = getLiveSearchAttributes(node);
                    return attrs.enableCategoryFilter !== undefined ? Boolean(attrs.enableCategoryFilter) : false;
                },
                afterInit: function(element, input) {
                    const attrs = getLiveSearchAttributes(element);
                    const isEnabled = attrs.enableCategoryFilter !== undefined ? Boolean(attrs.enableCategoryFilter) : false;
                    setTimeout(function() {
                        toggleCategoryFieldsVisibility(isEnabled);
                    }, 10);
                },
                onChange: function(node, value) {
                    const isChecked = (value === true || value === "true" || value === 1 || value === "1");
                    toggleCategoryFieldsVisibility(isChecked);
                    return updateLiveSearchAttribute(node, "enableCategoryFilter", isChecked);
                }
            },
            {
                name: "Category Taxonomy",
                key: "categoryFilterTaxonomy",
                inputtype: typeof SelectInput !== "undefined" ? SelectInput : "SelectInput",
                inline: false,
                col: 12,
                data: {
                    get options() {
                        return getDynamicTaxonomyOptions(false);
                    }
                },
                init: function(node) {
                    const attrs = getLiveSearchAttributes(node);
                    return attrs.categoryFilterTaxonomy || "category";
                },
                afterInit: function(element, input) {
                    const attrs = getLiveSearchAttributes(element);
                    const isEnabled = attrs.enableCategoryFilter !== undefined ? Boolean(attrs.enableCategoryFilter) : false;
                    const row = input ? input.closest('.mb-2[data-key]') : document.querySelector('.mb-2[data-key="categoryFilterTaxonomy"]');
                    if (row) {
                        row.style.display = isEnabled ? '' : 'none';
                    }
                },
                onChange: function(node, value) {
                    const val = typeof value === "object" && value !== null && value.value !== undefined ? value.value : String(value || 'category');
                    return updateLiveSearchAttribute(node, "categoryFilterTaxonomy", val);
                }
            },
            {
                name: "All Categories Label",
                key: "categoryFilterAllLabel",
                inputtype: typeof TextInput !== "undefined" ? TextInput : "TextInput",
                inline: false,
                col: 12,
                data: {
                    defaultValue: "All Categories"
                },
                init: function(node) {
                    const attrs = getLiveSearchAttributes(node);
                    return attrs.categoryFilterAllLabel !== undefined ? attrs.categoryFilterAllLabel : "All Categories";
                },
                afterInit: function(element, input) {
                    const attrs = getLiveSearchAttributes(element);
                    const isEnabled = attrs.enableCategoryFilter !== undefined ? Boolean(attrs.enableCategoryFilter) : false;
                    const row = input ? input.closest('.mb-2[data-key]') : document.querySelector('.mb-2[data-key="categoryFilterAllLabel"]');
                    if (row) {
                        row.style.display = isEnabled ? '' : 'none';
                    }
                },
                onChange: function(node, value) {
                    const val = typeof value === "object" && value !== null && value.value !== undefined ? value.value : value;
                    return updateLiveSearchAttribute(node, "categoryFilterAllLabel", String(val || 'All Categories'));
                }
            },
            {
                name: "Filter Categories (IDs or 'all')",
                key: "categoryFilterTerms",
                inputtype: typeof TextInput !== "undefined" ? TextInput : "TextInput",
                inline: false,
                col: 12,
                data: {
                    placeholder: "all (or comma-separated IDs: 12, 15, 23)",
                    defaultValue: "all"
                },
                init: function(node) {
                    const attrs = getLiveSearchAttributes(node);
                    return attrs.categoryFilterTerms !== undefined ? attrs.categoryFilterTerms : "all";
                },
                afterInit: function(element, input) {
                    const attrs = getLiveSearchAttributes(element);
                    const isEnabled = attrs.enableCategoryFilter !== undefined ? Boolean(attrs.enableCategoryFilter) : false;
                    const row = input ? input.closest('.mb-2[data-key]') : document.querySelector('.mb-2[data-key="categoryFilterTerms"]');
                    if (row) {
                        row.style.display = isEnabled ? '' : 'none';
                    }
                },
                onChange: function(node, value) {
                    const val = typeof value === "object" && value !== null && value.value !== undefined ? value.value : value;
                    return updateLiveSearchAttribute(node, "categoryFilterTerms", String(val || 'all'));
                }
            },

            // ==========================================
            // SECTION: DISPLAY OPTIONS
            // ==========================================
            {
                key: "section_display_header",
                inputtype: typeof SectionInput !== "undefined" ? SectionInput : null,
                name: false,
                data: { header: "Display Options" }
            },
            {
                name: "Show Thumbnail",
                key: "showThumbnail",
                inputtype: typeof ToggleInput !== "undefined" ? ToggleInput : (typeof CheckboxInput !== "undefined" ? CheckboxInput : "CheckboxInput"),
                inline: false,
                col: 12,
                data: {
                    on: "true",
                    off: "false",
                    defaultValue: true
                },
                init: function(node) {
                    const attrs = getLiveSearchAttributes(node);
                    return attrs.showThumbnail !== undefined ? Boolean(attrs.showThumbnail) : true;
                },
                onChange: function(node, value) {
                    const isChecked = (value === true || value === "true" || value === 1 || value === "1");
                    return updateLiveSearchAttribute(node, "showThumbnail", isChecked);
                }
            },
            {
                name: "Show Excerpt",
                key: "showExcerpt",
                inputtype: typeof ToggleInput !== "undefined" ? ToggleInput : (typeof CheckboxInput !== "undefined" ? CheckboxInput : "CheckboxInput"),
                inline: false,
                col: 12,
                data: {
                    on: "true",
                    off: "false",
                    defaultValue: false
                },
                init: function(node) {
                    const attrs = getLiveSearchAttributes(node);
                    return attrs.showExcerpt !== undefined ? Boolean(attrs.showExcerpt) : false;
                },
                onChange: function(node, value) {
                    const isChecked = (value === true || value === "true" || value === 1 || value === "1");
                    return updateLiveSearchAttribute(node, "showExcerpt", isChecked);
                }
            },
            {
                name: "Show Post Type Label",
                key: "showPostType",
                inputtype: typeof ToggleInput !== "undefined" ? ToggleInput : (typeof CheckboxInput !== "undefined" ? CheckboxInput : "CheckboxInput"),
                inline: false,
                col: 12,
                data: {
                    on: "true",
                    off: "false",
                    defaultValue: false
                },
                init: function(node) {
                    const attrs = getLiveSearchAttributes(node);
                    return attrs.showPostType !== undefined ? Boolean(attrs.showPostType) : false;
                },
                onChange: function(node, value) {
                    const isChecked = (value === true || value === "true" || value === 1 || value === "1");
                    return updateLiveSearchAttribute(node, "showPostType", isChecked);
                }
            },

            // ==========================================
            // SECTION: TECHNICAL & REDIRECTION
            // ==========================================
            {
                key: "section_tech_header",
                inputtype: typeof SectionInput !== "undefined" ? SectionInput : null,
                name: false,
                data: { header: "Technical & Redirection" }
            },
            {
                name: "Search Page URL",
                key: "searchPageUrl",
                inputtype: typeof TextInput !== "undefined" ? TextInput : "TextInput",
                inline: false,
                col: 12,
                data: {
                    placeholder: "/?s= (Leave empty for home search)"
                },
                init: function(node) {
                    const attrs = getLiveSearchAttributes(node);
                    return attrs.searchPageUrl || "";
                },
                onChange: function(node, value) {
                    const val = typeof value === "object" && value !== null && value.value !== undefined ? value.value : String(value || '');
                    return updateLiveSearchAttribute(node, "searchPageUrl", val.trim());
                }
            },
            {
                name: "CSS Class",
                key: "className",
                inputtype: typeof TextInput !== "undefined" ? TextInput : "TextInput",
                inline: false,
                col: 12,
                data: {
                    placeholder: "e.g. my-custom-search"
                },
                init: function(node) {
                    const attrs = getLiveSearchAttributes(node);
                    return attrs.className || "";
                },
                onChange: function(node, value) {
                    const val = typeof value === "object" && value !== null && value.value !== undefined ? value.value : String(value || '');
                    return updateLiveSearchAttribute(node, "className", val.trim());
                }
            },

            // ==========================================
            // SECTION: RAW BLOCK MARKUP (SYNC)
            // ==========================================
            {
                key: "section_markup_header",
                inputtype: typeof SectionInput !== "undefined" ? SectionInput : null,
                name: false,
                data: { header: "Gutenberg Block Markup" }
            },
            {
                name: "Block Markup",
                key: "rawBlockMarkup",
                inputtype: typeof TextareaInput !== "undefined" ? TextareaInput : "TextareaInput",
                inline: false,
                col: 12,
                data: {
                    rows: 4
                },
                init: function(node) {
                    return node ? (node.getAttribute('data-wp-block') || '') : '';
                },
                onChange: function(node, value) {
                    let val = typeof value === "object" && value !== null && value.value !== undefined ? value.value : String(value || '');
                    val = val.trim();
                    node.setAttribute('data-wp-block', val);
                    if (window.AlignBuilderModules && window.AlignBuilderModules.WpComponentsPreview) {
                        window.AlignBuilderModules.WpComponentsPreview.renderNodePreview(node, true, 300);
                    }
                    return node;
                }
            }
        ]
    });

    // 2. Register Block Preset in Palette
    if (Vvveb.Blocks) {
        Vvveb.Blocks.add("wordpress/live-search", {
            name: "WP Live Search",
            image: "icons/search.svg",
            dragHtml: '<div class="w-100 p-3 border border-primary rounded text-center bg-light"><strong>[WP Live Search]</strong></div>',
            html: '<div data-component-wp-block data-component-wp-live-search data-wp-block=\'<!-- wp:align/live-search {"placeholder":"Search...","postsPerPage":5,"enableLiveSearch":true,"allowModeToggle":true,"defaultMode":"live","showThumbnail":true,"showExcerpt":false,"showPostType":false} /-->\'>Live Search Block</div>'
        });
    }
}
