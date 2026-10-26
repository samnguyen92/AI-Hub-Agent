(function (wp, helpers) {
    if (!wp || !wp.blocks || !wp.element || !helpers) {
        return;
    }
    const registerBlockType = wp.blocks.registerBlockType;
    const el = wp.element.createElement;
    const Fragment = wp.element.Fragment;
    const __ = wp.i18n.__;
    const useSelect = wp.data.useSelect;
    const blockEditor = wp.blockEditor || wp.editor;
    const InspectorControls = blockEditor ? blockEditor.InspectorControls : null;
    const InnerBlocks = blockEditor ? blockEditor.InnerBlocks : null;
    const useInnerBlocksProps = blockEditor ? blockEditor.useInnerBlocksProps : null;
    const MediaUpload = blockEditor ? blockEditor.MediaUpload : null;
    const MediaUploadCheck = blockEditor ? blockEditor.MediaUploadCheck : null;
    const ServerSideRender = wp.serverSideRender;
    const useBlockProps = blockEditor && blockEditor.useBlockProps ? blockEditor.useBlockProps : function(props) { return props || {}; };
    const components = wp.components || {};
    const PanelBody = components.PanelBody || function (props) { return el('div', props, props.children); };
    const TextControl = components.TextControl;
    const ToggleControl = components.ToggleControl;
    const RangeControl = components.RangeControl;
    const CheckboxControl = components.CheckboxControl;
    const BaseControl = components.BaseControl || function(props) { return el('div', props, props.children); };
    const Button = components.Button || function(props) { return el('button', props, props.children); };
    const TabPanel = components.TabPanel;
    // Helpers from window.alignQueryBuilderHelpers
    const getBlockElement = helpers.getBlockElement;
    const AlignSelectControl = helpers.AlignSelectControl;
    const AlignMultiSelectControl = helpers.AlignMultiSelectControl;
    const useHideAdvancedPanel = helpers.useHideAdvancedPanel;
    const AlignSSRErrorBoundary = helpers.AlignSSRErrorBoundary;
    const customLogoIcon = helpers.customLogoIcon;
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
                marginBottom: '16px' 
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
                        overflowY: 'auto'
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
                    help: __('e.g. 12, 45', 'align-function')
                }) : null
            ]) : null
        ]);
    }

    function Edit(props) {
        const attributes = props.attributes;
        const setAttributes = props.setAttributes;
        useHideAdvancedPanel();
        // Call useInnerBlocksProps unconditionally (React Rules of Hooks)
        const innerBlocksProps = useInnerBlocksProps ? useInnerBlocksProps(
            { className: 'splide__list align_custom_block' },
            {
                allowedBlocks: ['align/slide'],
                template: [['align/slide', {}]]
            }
        ) : null;
        // Reactively count slides for dot label fields
        const innerBlocksCount = useSelect(function(select) {
            var block = select('core/block-editor').getBlock(props.clientId);
            return block && block.innerBlocks ? block.innerBlocks.length : 0;
        }, [props.clientId]);
        // Get all post types registered in WordPress
        const postTypes = useSelect(function(select) {
            return select('core').getPostTypes({ per_page: -1 });
        }, []);
        // Filter for viewable post types, excluding attachment
        const availablePostTypes = wp.element.useMemo(function() {
            if (window.alignBlockPostTypes && window.alignBlockPostTypes.length > 0) {
                return window.alignBlockPostTypes;
            }
            return (postTypes || []).filter(function(pt) {
                return pt.viewable && pt.slug !== 'attachment';
            });
        }, [postTypes]);
        wp.element.useEffect(function() {
            const timer = setTimeout(function() {
                const blockEl = getBlockElement(props.clientId);
                if (blockEl) {
                    const layoutEl = blockEl.querySelector('.block-editor-block-list__layout');
                    if (layoutEl) {
                        if (attributes.displayMode === 'slider' && attributes.dataSource === 'custom') {
                            layoutEl.classList.add('splide__list', 'align_custom_block');
                        } else {
                            layoutEl.classList.remove('splide__list', 'align_custom_block');
                        }
                    }
                }
            }, 100);
            return function() { clearTimeout(timer); };
        }, [attributes.displayMode, attributes.dataSource, props.clientId]);
        const inspector = InspectorControls ? el(InspectorControls, {}, [
            TabPanel ? el(TabPanel, {
                className: 'align-tab-panel align-qb-tab-panel',
                activeClass: 'is-active',
                tabs: [
                    { name: 'query', title: __('Query', 'align-function'), className: 'align-qb-tab-item' },
                    { name: 'layout', title: __('Layout', 'align-function'), className: 'align-qb-tab-item' },
                    { name: 'display', title: __('Styles', 'align-function'), className: 'align-qb-tab-item' },
                    { name: 'technical', title: __('Technical', 'align-function'), className: 'align-qb-tab-item' }
                ]
            }, function(tab) {
                if (tab.name === 'query') {
                    return el('div', { className: 'align-qb-tab-content' }, [
                        el(PanelBody, { title: __('Data Source & Query', 'align-function'), initialOpen: true }, [
                            el(AlignSelectControl, {
                                label: __('Data Source', 'align-function'),
                                value: attributes.dataSource,
                                options: [
                                    { label: __('Query Posts', 'align-function'), value: 'post' },
                                    { label: __('Custom Items', 'align-function'), value: 'custom' }
                                ],
                                onChange: function (value) { 
                                    setAttributes({ dataSource: value }); 
                                    if (value === 'post') { wp.data.dispatch('core/block-editor').replaceInnerBlocks(props.clientId, []); }
                                }
                            }),
                            attributes.dataSource === 'post' ? el(Fragment, { key: 'post-query-fields' }, [
                                el(AlignMultiSelectControl, {
                                    label: __('Select Post Types', 'align-function'),
                                    value: attributes.selectedPostTypes || ['all'],
                                    options: [
                                        { label: __('All Post Types', 'align-function'), value: 'all' }
                                    ].concat(availablePostTypes.map(function(pt) {
                                        return { label: pt.name + ' (' + pt.slug + ')', value: pt.slug };
                                    })),
                                    onChange: function(value, isChecked) {
                                        let current = [...(attributes.selectedPostTypes || [])];
                                        if (value === 'all') {
                                            if (isChecked) {
                                                setAttributes({ selectedPostTypes: ['all'] });
                                            } else {
                                                setAttributes({ selectedPostTypes: [] });
                                            }
                                        } else {
                                            current = current.filter(function(s) { return s !== 'all'; });
                                            if (isChecked) {
                                                if (!current.includes(value)) current.push(value);
                                            } else {
                                                current = current.filter(function(s) { return s !== value; });
                                            }
                                            if (current.length === 0) {
                                                current = ['all'];
                                            }
                                            setAttributes({ selectedPostTypes: current });
                                        }
                                    }
                                }),

                                !attributes.queryRelatedPosts ? el(TaxonomyFilterControl, {
                                    attributes: attributes,
                                    setAttributes: setAttributes,
                                    AlignSelectControl: AlignSelectControl
                                }) : null,
                                el(AlignSelectControl, {
                                    label: __('Order By', 'align-function'),
                                    value: attributes.queryOrderBy,
                                    options: [
                                        { label: __('Date Published', 'align-function'), value: 'date' },
                                        { label: __('Last Modified (Edits)', 'align-function'), value: 'modified' },
                                        { label: __('Random', 'align-function'), value: 'rand' },
                                        { label: __('Title', 'align-function'), value: 'title' }
                                    ],
                                    onChange: function (value) { setAttributes({ queryOrderBy: value }); }
                                }),
                                attributes.queryOrderBy !== 'rand' ? el(AlignSelectControl, {
                                    label: __('Order', 'align-function'),
                                    value: attributes.queryOrder,
                                    options: [
                                        { label: __('Newest to Oldest (DESC)', 'align-function'), value: 'DESC' },
                                        { label: __('Oldest to Newest (ASC)', 'align-function'), value: 'ASC' }
                                    ],
                                    onChange: function (value) { setAttributes({ queryOrder: value }); }
                                }) : null,
                                el(ToggleControl, {
                                    label: __('Related Posts by Current Post', 'align-function'),
                                    checked: attributes.queryRelatedPosts || false,
                                    onChange: function (value) { setAttributes({ queryRelatedPosts: value }); },
                                    help: __('Fetch posts with similar terms and ignore the current post.', 'align-function')
                                }),
                                el(RangeControl, {
                                    label: __('Posts Per Page', 'align-function'),
                                    value: attributes.postsPerPage,
                                    min: 1,
                                    max: 20,
                                    onChange: function (value) { setAttributes({ postsPerPage: value }); }
                                })
                            ]) : null
                        ])
                    ]);
                } else if (tab.name === 'layout') {
                    return el('div', { className: 'align-qb-tab-content' }, [
                        el(PanelBody, { title: __('Layout & Display Mode', 'align-function'), initialOpen: true }, [
                            el(AlignSelectControl, {
                                label: __('Display Mode', 'align-function'),
                                value: attributes.displayMode,
                                options: [
                                    { label: __('Slider', 'align-function'), value: 'slider' },
                                    { label: __('Grid', 'align-function'), value: 'grid' }
                                ],
                                onChange: function (value) { setAttributes({ displayMode: value }); }
                            }),
                            attributes.dataSource === 'post' && attributes.displayMode === 'grid' ? el(AlignSelectControl, {
                                label: __('Pagination Type', 'align-function'),
                                value: attributes.paginationType,
                                options: [
                                    { label: __('None', 'align-function'), value: 'none' },
                                    { label: __('Page Numbers (Click page)', 'align-function'), value: 'numbers' },
                                    { label: __('Load More Button (Click to view more)', 'align-function'), value: 'load_more' },
                                    { label: __('Infinite Scroll', 'align-function'), value: 'infinite' }
                                ],
                                onChange: function (value) { setAttributes({ paginationType: value }); }
                            }) : null,
                            attributes.displayMode === 'grid' ? el(ToggleControl, {
                                label: __('Enable Search Filter', 'align-function'),
                                checked: !!attributes.enableSearch,
                                onChange: function (value) { setAttributes({ enableSearch: value }); }
                            }) : null,
                            attributes.displayMode === 'grid' ? el(RangeControl, {
                                label: __('Grid Columns', 'align-function'),
                                value: attributes.gridColumns,
                                min: 1,
                                max: 20,
                                onChange: function (value) { setAttributes({ gridColumns: value }); }
                            }) : null,
                            attributes.displayMode === 'grid' && attributes.enableSearch ? el(RangeControl, {
                                label: __('Search Debounce (ms)', 'align-function'),
                                value: attributes.searchDebounce || 400,
                                min: 100,
                                max: 1500,
                                step: 50,
                                onChange: function (value) { setAttributes({ searchDebounce: value }); }
                            }) : null,
                            attributes.displayMode === 'grid' && attributes.enableSearch ? el(TextControl, {
                                label: __('Search Placeholder', 'align-function'),
                                value: attributes.searchPlaceholder || '',
                                onChange: function (value) { setAttributes({ searchPlaceholder: value }); }
                            }) : null
                        ]),
                        attributes.displayMode === 'slider' ? el(PanelBody, { title: __('Slider Options', 'align-function'), initialOpen: true }, [
                            el(AlignSelectControl, {
                                label: __('Transition Effect', 'align-function'),
                                value: attributes.transitionEffect || 'fade',
                                options: [
                                    { label: __('Fade (Default)', 'align-function'), value: 'fade' },
                                    { label: __('Slide', 'align-function'), value: 'slide' }
                                ],
                                onChange: function (value) { setAttributes({ transitionEffect: value }); }
                            }),
                            el(ToggleControl, {
                                label: __('Enable Splide Initialization', 'align-function'),
                                checked: !!attributes.enableSliderInit,
                                onChange: function (value) { setAttributes({ enableSliderInit: value }); },
                                help: __('Turn on to automatically initialize Splide slider on frontend.', 'align-function')
                            }),
                            attributes.enableSliderInit ? el(Fragment, { key: 'slider-toggles' }, [
                                el(ToggleControl, {
                                    label: __('Enable Loop', 'align-function'),
                                    checked: attributes.loop,
                                    onChange: function (value) { setAttributes({ loop: value }); }
                                }),
                                el(ToggleControl, {
                                    label: __('Show Arrows', 'align-function'),
                                    checked: attributes.arrows,
                                    onChange: function (value) { setAttributes({ arrows: value }); }
                                }),
                                el(ToggleControl, {
                                    label: __('Show Dots (Pagination)', 'align-function'),
                                    checked: attributes.showDots !== false,
                                    onChange: function (value) { setAttributes({ showDots: value }); }
                                }),
                                el(ToggleControl, {
                                    label: __('Lazyload Video', 'align-function'),
                                    checked: attributes.videoLazyload !== false,
                                    onChange: function (value) { setAttributes({ videoLazyload: value }); },
                                    help: __('Remove inactive videos to save memory.', 'align-function')
                                }),
                                el(ToggleControl, {
                                    label: __('Hook Navigator', 'align-function'),
                                    checked: attributes.hookNavigator,
                                    onChange: function (value) { setAttributes({ hookNavigator: value }); }
                                })
                            ]) : null,
                            el(ToggleControl, {
                                label: __('Custom Dot Labels', 'align-function'),
                                checked: attributes.useCustomDotLabels,
                                onChange: function (value) { setAttributes({ useCustomDotLabels: value }); },
                                help: __('Render dot label text elements for custom JS/CSS integration', 'align-function')
                            }),
                            attributes.enableSliderInit ? el(Fragment, { key: 'slider-numeric-settings' }, [
                                el(TextControl, {
                                    label: __('Slides Per View (Desktop)', 'align-function'),
                                    value: attributes.slidesPerView,
                                    onChange: function (value) { setAttributes({ slidesPerView: value }); },
                                    help: __('E.g. 1, 2, 1.5, 2.5', 'align-function')
                                }),
                                el(TextControl, {
                                    label: __('Slides Per View (Tablet)', 'align-function'),
                                    value: attributes.slidesPerViewTablet || '1',
                                    onChange: function (value) { setAttributes({ slidesPerViewTablet: value }); },
                                    help: __('E.g. 1, 2, 1.5, 2.5', 'align-function')
                                }),
                                el(TextControl, {
                                    label: __('Slides Per View (Mobile)', 'align-function'),
                                    value: attributes.slidesPerViewMobile || '1',
                                    onChange: function (value) { setAttributes({ slidesPerViewMobile: value }); },
                                    help: __('E.g. 1, 2, 1.5, 2.5', 'align-function')
                                }),
                                (parseFloat(attributes.slidesPerView) > 1 || parseFloat(attributes.slidesPerViewTablet) > 1 || parseFloat(attributes.slidesPerViewMobile) > 1) ? el(TextControl, {
                                    label: __('Space Between (px)', 'align-function'),
                                    value: String(attributes.spaceBetween !== undefined ? attributes.spaceBetween : 20),
                                    onChange: function (value) {
                                        var num = parseInt(value, 10);
                                        setAttributes({ spaceBetween: isNaN(num) ? 0 : num });
                                    },
                                    help: __('Gap between slides in pixels. Default: 20', 'align-function')
                                }) : null,
                                el(TextControl, {
                                    label: __('Autoplay Delay (ms)', 'align-function'),
                                    value: attributes.autoplayDelay || '',
                                    onChange: function (value) { setAttributes({ autoplayDelay: parseInt(value, 10) || 0 }); },
                                    help: __('E.g. 3000 = 3s. Set 0 or empty to disable.', 'align-function'),
                                    type: 'number'
                                }),
                                attributes.hookNavigator ? el(TextControl, {
                                    label: __('Next Button Selector', 'align-function'),
                                    value: attributes.nextSelector,
                                    onChange: function (value) { setAttributes({ nextSelector: value }); },
                                    help: __('E.g. .my-custom-next', 'align-function')
                                }) : null,
                                attributes.hookNavigator ? el(TextControl, {
                                    label: __('Prev Button Selector', 'align-function'),
                                    value: attributes.prevSelector,
                                    onChange: function (value) { setAttributes({ prevSelector: value }); },
                                    help: __('E.g. .my-custom-prev', 'align-function')
                                }) : null
                            ]) : null,
                            attributes.useCustomDotLabels ? (function() {
                                var slideCount = attributes.dataSource === 'custom'
                                    ? Math.max(innerBlocksCount, 1)
                                    : (parseInt(attributes.postsPerPage, 10) || 4);
                                var labels = attributes.dotLabels ? attributes.dotLabels.split('||') : [];
                                var fields = [];
                                for (var _di = 0; _di < slideCount; _di++) {
                                    (function(idx) {
                                        fields.push(el('div', {
                                            key: 'dot-row-' + idx,
                                            style: { display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '6px' }
                                        },
                                            el('span', { style: { fontSize: '12px', minWidth: '16px', color: '#757575' } }, (idx + 1) + '.'),
                                            el('input', {
                                                type: 'text',
                                                value: labels[idx] || '',
                                                placeholder: 'dot',
                                                onChange: function (e) {
                                                    var val = e.target.value;
                                                    var updated = attributes.dotLabels ? attributes.dotLabels.split('||') : [];
                                                    while (updated.length < slideCount) { updated.push(''); }
                                                    updated[idx] = val;
                                                    while (updated.length > 0 && updated[updated.length - 1] === '') { updated.pop(); }
                                                    setAttributes({ dotLabels: updated.join('||') });
                                                },
                                                style: {
                                                    flex: 1, padding: '4px 8px', fontSize: '12px',
                                                    border: '1px solid #cbd5e1', borderRadius: '6px',
                                                    lineHeight: '1.4', outline: 'none'
                                                }
                                            })
                                        ));
                                    })(_di);
                                }
                                return el('div', {
                                    style: {
                                        maxHeight: '300px',
                                        overflowY: 'auto',
                                        border: '1px solid #e2e8f0',
                                        borderRadius: '6px',
                                        padding: '8px 12px 8px 8px',
                                        marginBottom: '10px'
                                    }
                                }, fields);
                            })() : null
                        ]) : null
                    ]);
                } else if (tab.name === 'display') {
                    return el('div', { className: 'align-qb-tab-content' }, [
                        attributes.dataSource === 'post' ? el(PanelBody, { title: __('Card Styles & Elements', 'align-function'), initialOpen: true }, [
                            el(AlignSelectControl, {
                                label: __('Card Style', 'align-function'),
                                value: attributes.postCardStyle,
                                options: [
                                    { label: __('Overlay (Text on Image)', 'align-function'), value: 'overlay' },
                                    { label: __('Stacked (Text below Image)', 'align-function'), value: 'stacked' },
                                    { label: __('No Image (Text only)', 'align-function'), value: 'no-image' }
                                ],
                                onChange: function (value) { setAttributes({ postCardStyle: value }); }
                            }),
                            el(ToggleControl, {
                                label: __('Link Whole Container', 'align-function'),
                                checked: !!attributes.linkWholeContainer,
                                onChange: function (value) { setAttributes({ linkWholeContainer: value }); },
                                help: __('Wrap entire card container in permalink anchor tag.', 'align-function')
                            }),
                            el(ToggleControl, {
                                label: __('Show Title', 'align-function'),
                                checked: attributes.showPostTitle,
                                onChange: function (value) { setAttributes({ showPostTitle: value }); }
                            }),
                            el(ToggleControl, {
                                label: __('Show Excerpt', 'align-function'),
                                checked: attributes.showPostExcerpt,
                                onChange: function (value) { setAttributes({ showPostExcerpt: value }); }
                            }),
                            el(ToggleControl, {
                                label: __('Show Date', 'align-function'),
                                checked: attributes.showPostDate,
                                onChange: function (value) { setAttributes({ showPostDate: value }); }
                            }),
                            el(ToggleControl, {
                                label: __('Show Category / Badge', 'align-function'),
                                checked: attributes.showPostCategory,
                                onChange: function (value) { setAttributes({ showPostCategory: value }); }
                            }),
                            attributes.postCardStyle !== 'overlay' ? el(ToggleControl, {
                                label: __('Show Read More', 'align-function'),
                                checked: attributes.showReadMore,
                                onChange: function (value) { setAttributes({ showReadMore: value }); }
                            }) : null,
                            attributes.postCardStyle !== 'no-image' ? el(BaseControl, {
                                label: __('Fallback Image', 'align-function'),
                                help: __('Default image used if post has no featured image.', 'align-function'),
                                key: 'fallback-image-control'
                            }, [
                                MediaUploadCheck && MediaUpload ? el(MediaUploadCheck, { key: 'media-check' },
                                    el(MediaUpload, {
                                        key: 'media-upload',
                                        onSelect: function (media) {
                                            var imgUrl = media.url || (media.sizes && media.sizes.full ? media.sizes.full.url : '');
                                            setAttributes({
                                                fallbackImageId: media.id || 0,
                                                fallbackImageUrl: imgUrl
                                            });
                                        },
                                        allowedTypes: ['image'],
                                        value: attributes.fallbackImageId,
                                        render: function (obj) {
                                            if (!attributes.fallbackImageUrl) {
                                                return el(Button, {
                                                    variant: 'secondary',
                                                    onClick: obj.open,
                                                    style: { width: '100%', justifyContent: 'center', marginTop: '6px' }
                                                }, __('Select Fallback Image', 'align-function'));
                                            }
                                            return el('div', { style: { marginTop: '6px' } }, [
                                                el('div', {
                                                    style: {
                                                        position: 'relative',
                                                        marginBottom: '6px',
                                                        borderRadius: '4px',
                                                        overflow: 'hidden',
                                                        border: '1px solid #ccc',
                                                        maxHeight: '120px'
                                                    }
                                                }, [
                                                    el('img', {
                                                        src: attributes.fallbackImageUrl,
                                                        alt: 'Fallback Image',
                                                        style: { width: '100%', height: '100px', objectFit: 'cover', display: 'block' }
                                                    })
                                                ]),
                                                el('div', { style: { display: 'flex', gap: '8px' } }, [
                                                    el(Button, {
                                                        variant: 'secondary',
                                                        isSmall: true,
                                                        onClick: obj.open
                                                    }, __('Replace', 'align-function')),
                                                    el(Button, {
                                                        variant: 'link',
                                                        isDestructive: true,
                                                        isSmall: true,
                                                        onClick: function () {
                                                            setAttributes({
                                                                fallbackImageId: 0,
                                                                fallbackImageUrl: ''
                                                            });
                                                        }
                                                    }, __('Remove', 'align-function'))
                                                ])
                                            ]);
                                        }
                                    })
                                ) : null
                            ]) : null,
                            attributes.showPostTitle ? el(AlignSelectControl, {
                                label: __('Title Data Source', 'align-function'),
                                value: attributes.titleSource || 'default',
                                options: [
                                    { label: __('Default (WordPress Title)', 'align-function'), value: 'default' },
                                    { label: (window.alignPluginSupport && window.alignPluginSupport.acf) ? __('ACF Custom Field (Slug)', 'align-function') : __('ACF Custom Field (Plugin Not Active)', 'align-function'), value: 'acf', disabled: !(window.alignPluginSupport && window.alignPluginSupport.acf) },
                                    { label: (window.alignPluginSupport && window.alignPluginSupport.rankMath) ? __('Rank Math SEO Title', 'align-function') : __('Rank Math SEO Title (Plugin Not Active)', 'align-function'), value: 'rank_math', disabled: !(window.alignPluginSupport && window.alignPluginSupport.rankMath) }
                                ],
                                onChange: function (value) { setAttributes({ titleSource: value }); }
                            }) : null,
                            attributes.showPostTitle && attributes.titleSource === 'acf' ? el(TextControl, {
                                label: __('Title ACF Field Slug', 'align-function'),
                                value: attributes.titleAcfSlug || '',
                                placeholder: 'e.g. custom_title',
                                help: __('Enter ACF field slug to retrieve title data.', 'align-function'),
                                onChange: function (value) { setAttributes({ titleAcfSlug: value }); }
                            }) : null,
                            attributes.showPostTitle ? el(AlignSelectControl, {
                                label: __('Title HTML Tag', 'align-function'),
                                value: attributes.titleTag,
                                options: [
                                    { label: 'H2', value: 'h2' }, { label: 'H3', value: 'h3' }, { label: 'H4', value: 'h4' },
                                    { label: 'H5', value: 'h5' }, { label: 'H6', value: 'h6' }, { label: 'P', value: 'p' }, { label: 'DIV', value: 'div' }
                                ],
                                onChange: function (value) { setAttributes({ titleTag: value }); }
                            }) : null,
                            attributes.showPostExcerpt ? el(AlignSelectControl, {
                                label: __('Excerpt Data Source', 'align-function'),
                                value: attributes.excerptSource || 'default',
                                options: [
                                    { label: __('Default (WordPress Excerpt)', 'align-function'), value: 'default' },
                                    { label: (window.alignPluginSupport && window.alignPluginSupport.acf) ? __('ACF Custom Field (Slug)', 'align-function') : __('ACF Custom Field (Plugin Not Active)', 'align-function'), value: 'acf', disabled: !(window.alignPluginSupport && window.alignPluginSupport.acf) },
                                    { label: (window.alignPluginSupport && window.alignPluginSupport.rankMath) ? __('Rank Math Meta Description', 'align-function') : __('Rank Math Meta Description (Plugin Not Active)', 'align-function'), value: 'rank_math', disabled: !(window.alignPluginSupport && window.alignPluginSupport.rankMath) }
                                ],
                                onChange: function (value) { setAttributes({ excerptSource: value }); }
                            }) : null,
                            attributes.showPostExcerpt && attributes.excerptSource === 'acf' ? el(TextControl, {
                                label: __('Excerpt ACF Field Slug', 'align-function'),
                                value: attributes.excerptAcfSlug || '',
                                placeholder: 'e.g. custom_excerpt',
                                help: __('Enter ACF field slug to retrieve excerpt data.', 'align-function'),
                                onChange: function (value) { setAttributes({ excerptAcfSlug: value }); }
                            }) : null,
                            attributes.showPostExcerpt ? el(AlignSelectControl, {
                                label: __('Excerpt HTML Tag', 'align-function'),
                                value: attributes.excerptTag,
                                options: [ { label: 'P', value: 'p' }, { label: 'DIV', value: 'div' } ],
                                onChange: function (value) { setAttributes({ excerptTag: value }); }
                            }) : null,
                            attributes.showPostDate ? el(AlignSelectControl, {
                                label: __('Date Data Source', 'align-function'),
                                value: attributes.dateSource || 'default',
                                options: [
                                    { label: __('Default (WordPress Date)', 'align-function'), value: 'default' },
                                    { label: (window.alignPluginSupport && window.alignPluginSupport.acf) ? __('ACF Custom Field (Slug)', 'align-function') : __('ACF Custom Field (Plugin Not Active)', 'align-function'), value: 'acf', disabled: !(window.alignPluginSupport && window.alignPluginSupport.acf) },
                                    { label: (window.alignPluginSupport && window.alignPluginSupport.rankMath) ? __('Rank Math Date', 'align-function') : __('Rank Math Date (Plugin Not Active)', 'align-function'), value: 'rank_math', disabled: !(window.alignPluginSupport && window.alignPluginSupport.rankMath) }
                                ],
                                onChange: function (value) { setAttributes({ dateSource: value }); }
                            }) : null,
                            attributes.showPostDate && attributes.dateSource === 'acf' ? el(TextControl, {
                                label: __('Date ACF Field Slug', 'align-function'),
                                value: attributes.dateAcfSlug || '',
                                placeholder: 'e.g. event_date',
                                help: __('Enter ACF field slug to retrieve date data.', 'align-function'),
                                onChange: function (value) { setAttributes({ dateAcfSlug: value }); }
                            }) : null,
                            attributes.showPostDate ? el(AlignSelectControl, {
                                label: __('Date Format', 'align-function'),
                                value: attributes.dateFormat,
                                options: [
                                    { label: __('Default (F j, Y)', 'align-function'), value: 'F j, Y' },
                                    { label: __('Y-m-d (e.g. 2026-06-08)', 'align-function'), value: 'Y-m-d' },
                                    { label: __('d/m/Y (e.g. 08/06/2026)', 'align-function'), value: 'd/m/Y' },
                                    { label: __('m/d/Y (e.g. 06/08/2026)', 'align-function'), value: 'm/d/Y' },
                                    { label: __('Custom', 'align-function'), value: 'custom' }
                                ],
                                onChange: function (value) { setAttributes({ dateFormat: value }); }
                            }) : null,
                            attributes.showPostDate && attributes.dateFormat === 'custom' ? el(TextControl, {
                                label: __('Custom Date Format', 'align-function'),
                                value: attributes.customDateFormat,
                                onChange: function (value) { setAttributes({ customDateFormat: value }); },
                                help: el('a', { href: 'https://wordpress.org/documentation/article/customize-date-and-time-format/', target: '_blank' }, __('Format Documentation', 'align-function'))
                            }) : null,
                            attributes.postCardStyle !== 'overlay' && attributes.showReadMore ? el(TextControl, {
                                label: __('Read More Text', 'align-function'),
                                value: attributes.readMoreText,
                                onChange: function (value) { setAttributes({ readMoreText: value }); }
                            }) : null,
                            attributes.postCardStyle !== 'overlay' && attributes.showReadMore ? el(AlignSelectControl, {
                                label: __('Read More Icon', 'align-function'),
                                value: attributes.readMoreIconType,
                                options: [
                                    { label: __('None', 'align-function'), value: 'none' },
                                    { label: __('Arrow Right (→)', 'align-function'), value: 'arrow' },
                                    { label: __('Chevron Right (>)', 'align-function'), value: 'chevron' },
                                    { label: __('Custom SVG', 'align-function'), value: 'custom' }
                                ],
                                onChange: function (value) { setAttributes({ readMoreIconType: value }); }
                            }) : null,
                            attributes.postCardStyle !== 'overlay' && attributes.showReadMore && attributes.readMoreIconType === 'custom' ? el(components.TextareaControl, {
                                label: __('Custom SVG Code', 'align-function'),
                                value: attributes.readMoreCustomSvg,
                                onChange: function (value) { setAttributes({ readMoreCustomSvg: value }); },
                                help: __('Paste raw <svg>...</svg> code here.', 'align-function')
                            }) : null
                        ]) : el(PanelBody, { title: __('Card Styles', 'align-function'), initialOpen: true }, [
                            el('p', { style: { color: '#64748b', fontSize: '13px', fontStyle: 'italic' } },
                                __('Custom data source uses InnerBlocks slides. Card styles apply to post queries only.', 'align-function')
                            )
                        ])
                    ]);
                } else if (tab.name === 'technical') {
                    return el('div', { className: 'align-qb-tab-content' }, [
                        el(PanelBody, { title: __('Technical Settings', 'align-function'), initialOpen: true }, [
                            el(TextControl, {
                                label: __('CSS Class', 'align-function'),
                                value: attributes.className || '',
                                onChange: function (value) { setAttributes({ className: value }); },
                                help: __('Separate multiple classes with spaces.', 'align-function')
                            })
                        ])
                    ]);
                }
                return null;
            }) : null
        ]) : null;
        let editorContent;
        if (attributes.dataSource === 'custom') {
            if (innerBlocksProps) {
                editorContent = el('div', innerBlocksProps);
            } else {
                editorContent = el(InnerBlocks, {
                    allowedBlocks: ['align/slide'],
                    template: [['align/slide', {}]]
                });
            }
        } else {
            // ServerSideRender for post query mode - wrapped in error boundary
            if (ServerSideRender) {
                editorContent = el(AlignSSRErrorBoundary, { key: 'ssr-' + attributes.displayMode },
                    el(ServerSideRender, {
                        block: 'align/query-builder',
                        attributes: attributes,
                        key: 'ssr-render'
                    })
                );
            } else {
                editorContent = el('div', { className: 'align-query-builder-placeholder' }, 
                    __('Query Builder: Active Query Mode', 'align-function')
                );
            }
        }
                const blockClass = attributes.displayMode === 'grid' ? 'align-qb-wrap align-qb-wrap--grid' : 'align-qb-wrap align-qb-wrap--slider splide';
        let wrapperClassName = '';
        if (attributes.dataSource === 'custom') { wrapperClassName = blockClass; }
        // Realtime editor preview: inject scoped CSS to simulate slidesPerView
        var slidePreviewStyle = null;
        if (attributes.displayMode === 'slider' && attributes.dataSource === 'post') {
            var _spv = attributes.slidesPerView === 'auto' ? 'auto' : (parseFloat(attributes.slidesPerView) || 1);
            var _rawGap = (attributes.spaceBetween !== undefined && attributes.spaceBetween !== '') ? parseInt(attributes.spaceBetween, 10) : 20;
            var _gap = isNaN(_rawGap) ? 0 : _rawGap;
            var _sel = '[data-block="' + props.clientId + '"]';
            var cssRules = '';
            cssRules += _sel + ' .splide__list { display: flex !important; gap: ' + _gap + 'px !important; }';
            if (_spv === 'auto') {
                cssRules += _sel + ' .splide__slide { width: auto !important; flex: 0 0 auto !important; }';
            } else {
                var _totalGap = _gap * (_spv - 1);
                var _w = 'calc((100% - ' + _totalGap + 'px) / ' + _spv + ')';
                cssRules += _sel + ' .splide__slide { width: ' + _w + ' !important; flex: 0 0 ' + _w + ' !important; }';
            }
            slidePreviewStyle = el('style', { key: 'qb-slider-preview-' + props.clientId }, cssRules);
        }
        return [
            inspector,
            slidePreviewStyle,
            el('div', useBlockProps({
                className: wrapperClassName,
                key: 'qb-wrap-' + attributes.dataSource + '-' + attributes.displayMode,
                'data-qb-grid-cols': attributes.displayMode === 'grid' ? attributes.gridColumns : undefined,
                onClickCapture: function(e) { if (e.target.closest && e.target.closest('a')) { e.preventDefault(); } }
            }), editorContent)
        ];
    }
    registerBlockType('align/query-builder', {
        icon: customLogoIcon,
        edit: Edit,
        save: function (props) {
            if (props.attributes.dataSource === 'custom') {
                return el(wp.blockEditor.InnerBlocks.Content, {});
            }
            return el(wp.blockEditor.InnerBlocks.Content, {});
        }
    });
})(window.wp, window.alignQueryBuilderHelpers);
