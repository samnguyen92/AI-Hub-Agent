(function (wp) {
    if (!wp || !wp.blocks || !wp.element) {
        return;
    }

    const registerBlockType = wp.blocks.registerBlockType;
    const el = wp.element.createElement;
    const Fragment = wp.element.Fragment;
    const __ = wp.i18n.__;
    const useSelect = wp.data.useSelect;

    const blockEditor = wp.blockEditor || wp.editor;
    const InspectorControls = blockEditor ? blockEditor.InspectorControls : null;
    const useBlockProps = blockEditor && blockEditor.useBlockProps
        ? blockEditor.useBlockProps
        : function (props) { return props || {}; };

    const components = wp.components || {};
    const PanelBody    = components.PanelBody    || function (p) { return el('div', p, p.children); };
    const TextControl  = components.TextControl;
    const ToggleControl = components.ToggleControl;
    const RangeControl = components.RangeControl;
    const CheckboxControl = components.CheckboxControl;

    // ---- Reusable custom dropdown (same as query-builder) ----
    const AlignSelectControl = function (props) {
        const [isOpen, setIsOpen] = wp.element.useState(false);
        const wrapperRef = wp.element.useRef(null);

        wp.element.useEffect(function () {
            const handleClickOutside = function (event) {
                if (wrapperRef.current && !wrapperRef.current.contains(event.target)) {
                    setIsOpen(false);
                }
            };
            document.addEventListener('mousedown', handleClickOutside);
            return function () { document.removeEventListener('mousedown', handleClickOutside); };
        }, []);

        const selectedOpt = props.options.find(function (o) { return String(o.value) === String(props.value); });
        const displayLabel = selectedOpt ? selectedOpt.label : (props.options.length > 0 ? props.options[0].label : '');

        return el('div', {
            className: 'align-custom-select-wrapper ' + (isOpen ? 'is-open' : ''),
            ref: wrapperRef,
            style: { marginBottom: '16px', position: 'relative' }
        }, [
            props.label ? el('label', { key: 'label' }, props.label) : null,
            el('button', {
                key: 'button',
                className: 'align-select-btn',
                onClick: function (e) { e.preventDefault(); setIsOpen(!isOpen); },
                type: 'button'
            }, [
                el('span', { key: 'text' }, displayLabel),
                el('span', { key: 'arrow', className: 'align-custom-select-arrow' })
            ]),
            isOpen ? el('div', { key: 'dropdown', className: 'align-select-dropdown' },
                props.options.map(function (opt, idx) {
                    return el('div', {
                        key: idx,
                        className: 'align-select-opt ' + (opt.disabled ? 'is-disabled' : ''),
                        onClick: function () {
                            if (opt.disabled) return;
                            setIsOpen(false);
                            if (props.onChange) props.onChange(opt.value);
                        }
                    }, opt.label);
                })
            ) : null
        ]);
    };

    // ---- Hide the WP Advanced panel (same pattern as query-builder) ----
    function useHideAdvancedPanel() {
        wp.element.useEffect(function () {
            const hideAdvanced = function () {
                const inspector = document.querySelector('.block-editor-block-inspector');
                if (!inspector) return;
                inspector.querySelectorAll('.components-panel__body').forEach(function (panel) {
                    const btn = panel.querySelector(':scope > .components-panel__body-title button');
                    if (btn && btn.textContent.trim() === 'Advanced') {
                        panel.style.display = 'none';
                    }
                });
            };
            const timer = setTimeout(hideAdvanced, 50);
            return function () { clearTimeout(timer); };
        });
    }

    function TaxonomyFilterControl(props) {
        const attributes = props.attributes;
        const setAttributes = props.setAttributes;
        const AlignSelectControl = props.AlignSelectControl;
        const useState = wp.element.useState;
        const [searchTerm, setSearchTerm] = useState('');
        const [showManualInput, setShowManualInput] = useState(false);

        const taxonomies = (window.alignBlockTaxonomies || []).map(function(tax) {
            return {
                slug: tax.slug,
                name: tax.name,
                terms: tax.terms || [],
                postTypes: tax.post_types || []
            };
        });

        const currentTaxSlug = attributes.filterTaxonomy || '';
        const rawTermIds = attributes.filterTermIds || '';

        const selectedTermIds = rawTermIds
            .split(',')
            .map(function(s) { return s.trim(); })
            .filter(Boolean);

        const taxOptions = [
            { label: __('-- Select Taxonomy --', 'align-function'), value: '' }
        ];
        taxonomies.forEach(function(tax) {
            taxOptions.push({
                label: tax.name + ' (' + tax.slug + ')',
                value: tax.slug
            });
        });
        taxOptions.push({ label: __('Custom Taxonomy Slug...', 'align-function'), value: '__custom__' });

        const isKnownTax = taxonomies.some(function(t) { return t.slug === currentTaxSlug; });
        const isCustomTax = currentTaxSlug !== '' && !isKnownTax;

        const activeTax = taxonomies.find(function(t) { return t.slug === currentTaxSlug; });
        const availableTerms = activeTax ? activeTax.terms : [];

        const filteredTerms = availableTerms.filter(function(term) {
            if (!searchTerm) return true;
            const lower = searchTerm.toLowerCase();
            return (term.name && term.name.toLowerCase().includes(lower)) ||
                   (term.slug && term.slug.toLowerCase().includes(lower)) ||
                   String(term.id).includes(lower);
        });

        function handleTermToggle(termId, checked) {
            const strId = String(termId);
            let updated = [...selectedTermIds];
            if (checked) {
                if (!updated.includes(strId)) updated.push(strId);
            } else {
                updated = updated.filter(function(id) { return id !== strId; });
            }
            setAttributes({ filterTermIds: updated.join(',') });
        }

        return el('div', { 
            className: 'align-taxonomy-filter-box', 
            style: { 
                marginBottom: '16px', 
                padding: '12px', 
                border: '1px solid #e0e0e0', 
                borderRadius: '6px', 
                backgroundColor: '#fff' 
            } 
        }, [
            el(AlignSelectControl || components.SelectControl, {
                label: __('Filter by Taxonomy', 'align-function'),
                value: isCustomTax ? '__custom__' : currentTaxSlug,
                options: taxOptions,
                onChange: function(val) {
                    if (val === '__custom__') {
                        setAttributes({ filterTaxonomy: '' });
                    } else {
                        setAttributes({ filterTaxonomy: val, filterTermIds: '' });
                    }
                }
            }),

            isCustomTax ? el(TextControl, {
                label: __('Custom Taxonomy Slug', 'align-function'),
                value: attributes.filterTaxonomy,
                onChange: function(val) { setAttributes({ filterTaxonomy: val }); },
                help: __('e.g. category, post_tag, product_cat', 'align-function')
            }) : null,

            currentTaxSlug && isKnownTax ? el('div', { className: 'align-terms-section', style: { marginTop: '12px' } }, [
                el('div', { 
                    style: { 
                        display: 'flex', 
                        justifyContent: 'space-between', 
                        alignItems: 'center', 
                        marginBottom: '8px' 
                    } 
                }, [
                    el('label', { 
                        style: { 
                            fontWeight: '600', 
                            fontSize: '11px', 
                            textTransform: 'uppercase', 
                            color: '#555' 
                        } 
                    }, (activeTax ? activeTax.name : '') + ' (' + __('Filter Terms/Tags', 'align-function') + ')'),
                    selectedTermIds.length > 0 ? el('button', {
                        type: 'button',
                        className: 'components-button is-link is-destructive',
                        style: { fontSize: '11px', textDecoration: 'none' },
                        onClick: function() { setAttributes({ filterTermIds: '' }); }
                    }, __('Clear Selected (' + selectedTermIds.length + ')', 'align-function')) : null
                ]),

                availableTerms.length > 5 ? el(TextControl, {
                    placeholder: __('Search ' + (activeTax ? activeTax.name : 'terms') + '...', 'align-function'),
                    value: searchTerm,
                    onChange: function(val) { setSearchTerm(val); }
                }) : null,

                el('div', {
                    style: {
                        maxHeight: '180px',
                        overflowY: 'auto',
                        border: '1px solid #dcdcde',
                        borderRadius: '4px',
                        padding: '8px 12px',
                        backgroundColor: '#fafafa'
                    }
                }, [
                    availableTerms.length === 0 ? el('div', { 
                        style: { color: '#757575', fontStyle: 'italic', fontSize: '12px', padding: '4px 0' } 
                    }, __('No terms found for this taxonomy.', 'align-function')) : null,
                    filteredTerms.map(function(term) {
                        const isChecked = selectedTermIds.includes(String(term.id));
                        return el(CheckboxControl, {
                            key: term.id,
                            label: term.name + (term.count !== undefined ? ' (' + term.count + ')' : ''),
                            checked: isChecked,
                            onChange: function(checked) { handleTermToggle(term.id, checked); }
                        });
                    })
                ]),

                el('div', { style: { marginTop: '8px', textAlign: 'right' } }, [
                    el('button', {
                        type: 'button',
                        className: 'components-button is-link',
                        style: { fontSize: '11px', color: '#757575', textDecoration: 'underline' },
                        onClick: function() { setShowManualInput(!showManualInput); }
                    }, showManualInput ? __('Hide Manual IDs', 'align-function') : __('Manual Term IDs', 'align-function'))
                ]),

                showManualInput ? el(TextControl, {
                    label: __('Term IDs (Comma-separated)', 'align-function'),
                    value: attributes.filterTermIds,
                    onChange: function(val) { setAttributes({ filterTermIds: val }); },
                    help: __('Manually specify IDs for terms not listed above.', 'align-function')
                }) : null
            ]) : null
        ]);
    }

    function CategoryFilterDropdownControl(props) {
        const attributes = props.attributes;
        const setAttributes = props.setAttributes;
        const AlignSelectControl = props.AlignSelectControl;
        const useState = wp.element.useState;
        const [searchTerm, setSearchTerm] = useState('');

        if (!attributes.enableCategoryFilter) return null;

        const taxonomies = (window.alignBlockTaxonomies || []).map(function(tax) {
            return {
                slug: tax.slug,
                name: tax.name,
                terms: tax.terms || [],
                postTypes: tax.post_types || []
            };
        });

        const currentTaxSlug = attributes.categoryFilterTaxonomy || 'category';
        const rawTerms = (attributes.categoryFilterTerms || 'all').trim();
        const isAllSelected = rawTerms === 'all' || rawTerms === '';
        const selectedTermIds = isAllSelected ? [] : rawTerms.split(',').map(function(s) { return s.trim(); }).filter(Boolean);

        const taxOptions = taxonomies.map(function(tax) {
            return {
                label: tax.name + ' (' + tax.slug + ')',
                value: tax.slug
            };
        });
        if (!taxOptions.some(function(t) { return t.value === 'category'; })) {
            taxOptions.unshift({ label: 'Categories (category)', value: 'category' });
        }

        const activeTax = taxonomies.find(function(t) { return t.slug === currentTaxSlug; });
        const availableTerms = activeTax ? activeTax.terms : [];

        const filteredTerms = availableTerms.filter(function(term) {
            if (!searchTerm) return true;
            const lower = searchTerm.toLowerCase();
            return (term.name && term.name.toLowerCase().includes(lower)) ||
                   (term.slug && term.slug.toLowerCase().includes(lower)) ||
                   String(term.id).includes(lower);
        });

        function handleToggleAll(checked) {
            if (checked) {
                setAttributes({ categoryFilterTerms: 'all' });
            } else {
                setAttributes({ categoryFilterTerms: '' });
            }
        }

        function handleTermToggle(termId, checked) {
            const strId = String(termId);
            let updated = isAllSelected ? [] : [...selectedTermIds];
            if (checked) {
                if (!updated.includes(strId)) updated.push(strId);
            } else {
                updated = updated.filter(function(id) { return id !== strId; });
            }
            if (updated.length === 0) {
                setAttributes({ categoryFilterTerms: 'all' });
            } else {
                setAttributes({ categoryFilterTerms: updated.join(',') });
            }
        }

        return el('div', {
            className: 'align-category-filter-settings',
            style: {
                marginBottom: '16px',
                padding: '12px',
                border: '1px solid #e0e0e0',
                borderRadius: '6px',
                backgroundColor: '#fafafa'
            }
        }, [
            el(AlignSelectControl || components.SelectControl, {
                label: __('Category Taxonomy', 'align-function'),
                value: currentTaxSlug,
                options: taxOptions,
                onChange: function(val) {
                    setAttributes({ categoryFilterTaxonomy: val, categoryFilterTerms: 'all' });
                }
            }),

            el(TextControl, {
                label: __('All Categories Label', 'align-function'),
                value: attributes.categoryFilterAllLabel || 'All Categories',
                onChange: function(val) { setAttributes({ categoryFilterAllLabel: val }); }
            }),

            el('div', { style: { marginTop: '12px' } }, [
                el('div', {
                    style: {
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        marginBottom: '8px'
                    }
                }, [
                    el('label', {
                        style: {
                            fontWeight: '600',
                            fontSize: '11px',
                            textTransform: 'uppercase',
                            color: '#555'
                        }
                    }, (activeTax ? activeTax.name : 'Categories') + ' (' + __('List of WP Categories', 'align-function') + ')'),
                    !isAllSelected && selectedTermIds.length > 0 ? el('button', {
                        type: 'button',
                        className: 'components-button is-link is-destructive',
                        style: { fontSize: '11px', textDecoration: 'none' },
                        onClick: function() { setAttributes({ categoryFilterTerms: 'all' }); }
                    }, __('Reset to All', 'align-function')) : null
                ]),

                availableTerms.length > 5 ? el(TextControl, {
                    placeholder: __('Search ' + (activeTax ? activeTax.name : 'categories') + '...', 'align-function'),
                    value: searchTerm,
                    onChange: function(val) { setSearchTerm(val); }
                }) : null,

                el('div', {
                    style: {
                        maxHeight: '180px',
                        overflowY: 'auto',
                        border: '1px solid #dcdcde',
                        borderRadius: '4px',
                        padding: '8px 12px',
                        backgroundColor: '#ffffff'
                    }
                }, [
                    el(CheckboxControl, {
                        key: 'all-categories',
                        label: __('All ' + (activeTax ? activeTax.name : 'Categories') + ' (Include All)', 'align-function'),
                        checked: isAllSelected,
                        onChange: handleToggleAll
                    }),
                    availableTerms.length === 0 ? el('div', {
                        style: { color: '#757575', fontStyle: 'italic', fontSize: '12px', padding: '4px 0' }
                    }, __('No categories found for this taxonomy.', 'align-function')) : null,
                    filteredTerms.map(function(term) {
                        const isChecked = !isAllSelected && selectedTermIds.includes(String(term.id));
                        return el(CheckboxControl, {
                            key: term.id,
                            label: term.name + (term.count !== undefined ? ' (' + term.count + ')' : ''),
                            checked: isChecked,
                            onChange: function(checked) { handleTermToggle(term.id, checked); }
                        });
                    })
                ])
            ])
        ]);
    }

    // ---- Edit component ----
    function Edit(props) {
        const attributes  = props.attributes;
        const setAttributes = props.setAttributes;

        useHideAdvancedPanel();

        const postTypes = useSelect(function(select) {
            return select('core').getPostTypes({ per_page: -1 });
        }, []);

        const availablePostTypes = wp.element.useMemo(function() {
            return (postTypes || []).filter(function(pt) {
                return pt.viewable && pt.slug !== 'attachment';
            });
        }, [postTypes]);

        const allowToggle = attributes.allowModeToggle !== false;
        const defaultMode = attributes.defaultMode || 'live';
        const blockProps = useBlockProps({
            className: 'align-live-search-editor-preview' + (!allowToggle ? ' has-no-toggle' : '')
        });

        const inspector = InspectorControls ? el(InspectorControls, {}, [
            // Panel 1: Editor (open by default)
            el(PanelBody, { title: __('Editor', 'align-function'), initialOpen: true }, [
                TextControl ? el(TextControl, {
                    label: __('Placeholder Text', 'align-function'),
                    value: attributes.placeholder,
                    onChange: function (val) { setAttributes({ placeholder: val }); }
                }) : null,

                el('div', { 
                    style: { 
                        marginBottom: '16px', 
                        padding: '12px', 
                        border: '1px solid #e0e0e0', 
                        borderRadius: '4px', 
                        maxHeight: '180px', 
                        overflowY: 'auto',
                        backgroundColor: '#fafafa'
                    } 
                }, [
                    el('label', { 
                        style: { 
                            display: 'block', 
                            fontWeight: '600', 
                            marginBottom: '8px', 
                            fontSize: '11px', 
                            textTransform: 'uppercase', 
                            color: '#555' 
                        } 
                    }, __('Select Post Types', 'align-function')),
                    el(CheckboxControl, {
                        key: 'all',
                        label: __('All Post Types', 'align-function'),
                        checked: (attributes.selectedPostTypes || []).includes('all'),
                        onChange: function(checked) {
                            if (checked) {
                                setAttributes({ selectedPostTypes: ['all'] });
                            } else {
                                setAttributes({ selectedPostTypes: [] });
                            }
                        }
                    }),
                    availablePostTypes.map(function(pt) {
                        const isChecked = (attributes.selectedPostTypes || []).includes(pt.slug);
                        return el(CheckboxControl, {
                            key: pt.slug,
                            label: pt.name + ' (' + pt.slug + ')',
                            checked: isChecked,
                            onChange: function(checked) {
                                let updated = [...(attributes.selectedPostTypes || [])];
                                updated = updated.filter(function(s) { return s !== 'all'; });
                                if (checked) {
                                    if (!updated.includes(pt.slug)) updated.push(pt.slug);
                                } else {
                                    updated = updated.filter(function(s) { return s !== pt.slug; });
                                }
                                setAttributes({ selectedPostTypes: updated });
                            }
                        });
                    })
                ]),

                el(TaxonomyFilterControl, {
                    attributes: attributes,
                    setAttributes: setAttributes,
                    AlignSelectControl: AlignSelectControl
                }),

                ToggleControl ? el(ToggleControl, {
                    label: __('Enable Live Search (AJAX)', 'align-function'),
                    help: __('When enabled, search queries fetch results in real-time via AJAX.', 'align-function'),
                    checked: attributes.enableLiveSearch !== false,
                    onChange: function (val) { setAttributes({ enableLiveSearch: val }); }
                }) : null,

                attributes.enableLiveSearch !== false && RangeControl ? el(RangeControl, {
                    label: __('Max Results', 'align-function'),
                    value: attributes.postsPerPage,
                    min: 1,
                    max: 20,
                    onChange: function (val) { setAttributes({ postsPerPage: val }); }
                }) : null,

                attributes.enableLiveSearch !== false && ToggleControl ? el(ToggleControl, {
                    label: __('Allow Mode Toggle', 'align-function'),
                    checked: attributes.allowModeToggle !== false,
                    onChange: function (val) { setAttributes({ allowModeToggle: val }); }
                }) : null,

                attributes.enableLiveSearch !== false ? el(AlignSelectControl, {
                    label: __('Default Search Mode', 'align-function'),
                    value: attributes.defaultMode || 'live',
                    options: [
                        { label: __('Live Search', 'align-function'), value: 'live' },
                        { label: __('Normal Search', 'align-function'), value: 'normal' }
                    ],
                    onChange: function (val) { setAttributes({ defaultMode: val }); }
                }) : null,

                ToggleControl ? el(ToggleControl, {
                    label: __('Enable Query by Categories', 'align-function'),
                    help: __('Display a category dropdown inside the search bar to filter searches by category.', 'align-function'),
                    checked: attributes.enableCategoryFilter === true,
                    onChange: function (val) { setAttributes({ enableCategoryFilter: val }); }
                }) : null,

                el(CategoryFilterDropdownControl, {
                    attributes: attributes,
                    setAttributes: setAttributes,
                    AlignSelectControl: AlignSelectControl
                })
            ]),

            // Panel 2: Display (collapsed)
            el(PanelBody, { title: __('Display', 'align-function'), initialOpen: false }, [
                ToggleControl ? el(ToggleControl, {
                    label: __('Show Thumbnail', 'align-function'),
                    checked: attributes.showThumbnail,
                    onChange: function (val) { setAttributes({ showThumbnail: val }); }
                }) : null,
                ToggleControl ? el(ToggleControl, {
                    label: __('Show Excerpt', 'align-function'),
                    checked: attributes.showExcerpt,
                    onChange: function (val) { setAttributes({ showExcerpt: val }); }
                }) : null,
                ToggleControl ? el(ToggleControl, {
                    label: __('Show Post Type Label', 'align-function'),
                    checked: attributes.showPostType,
                    onChange: function (val) { setAttributes({ showPostType: val }); }
                }) : null
            ]),

            // Panel 3: Technical (collapsed)
            el(PanelBody, { title: __('Technical', 'align-function'), initialOpen: false }, [
                TextControl ? el(TextControl, {
                    label: __('Search Page URL', 'align-function'),
                    value: attributes.searchPageUrl,
                    placeholder: '/?s=',
                    help: __('URL to redirect when user presses Enter. Defaults to home URL with ?s= query.', 'align-function'),
                    onChange: function (val) { setAttributes({ searchPageUrl: val }); }
                }) : null,
                TextControl ? el(TextControl, {
                    label: __('CSS Class', 'align-function'),
                    value: attributes.className,
                    onChange: function (val) { setAttributes({ className: val }); }
                }) : null
            ])
        ]) : null;

        // Static preview in editor — no live AJAX
        const preview = el('div', blockProps,
            el('div', { className: 'align-ls-editor-preview' },
                el('div', { className: 'align-ls-input-wrap' },
                    el('input', {
                        type: 'text',
                        className: 'align-ls-input',
                        placeholder: attributes.placeholder || 'Search...',
                        readOnly: true,
                        style: { paddingRight: allowToggle ? '105px' : '44px' }
                    }),
                    allowToggle ? el('button', { type: 'button', className: 'align-ls-mode-toggle ' + (defaultMode === 'live' ? 'is-live' : ''), disabled: true },
                        el('span', { className: 'align-ls-mode-indicator' }),
                        el('span', { className: 'align-ls-mode-text' }, defaultMode === 'live' ? __('Live', 'align-function') : __('Normal', 'align-function'))
                    ) : null,
                    el('button', { type: 'button', className: 'align-ls-submit', disabled: true },
                        el('svg', { xmlns: 'http://www.w3.org/2000/svg', width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round' },
                            el('circle', { cx: 11, cy: 11, r: 8 }),
                            el('line', { x1: 21, y1: 21, x2: 16.65, y2: 16.65 })
                        )
                    )
                ),
                el('p', { className: 'align-ls-editor-hint' }, __('Live Search — results appear below on frontend.', 'align-function'))
            )
        );

        return el(Fragment, {}, [inspector, preview]);
    }

    registerBlockType('align/live-search', {
        edit: Edit,
        save: function () { return null; } // Dynamic block — rendered via PHP
    });

}(window.wp));
