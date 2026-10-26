(function() {
    const { __ } = wp.i18n;
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, ToggleControl, TabPanel } = wp.components;
    const { createElement: el, Fragment } = wp.element;

    const AlignSelectControl = function(props) {
        const [isOpen, setIsOpen] = wp.element.useState(false);
        const wrapperRef = wp.element.useRef(null);

        wp.element.useEffect(function() {
            const handleClickOutside = function(event) {
                if (wrapperRef.current && !wrapperRef.current.contains(event.target)) {
                    setIsOpen(false);
                }
            };
            document.addEventListener('mousedown', handleClickOutside);
            return function() {
                document.removeEventListener('mousedown', handleClickOutside);
            };
        }, []);

        var selectedOpt = props.options.find(function(o) { return String(o.value) === String(props.value); });
        var displayLabel = selectedOpt ? selectedOpt.label : (props.options.length > 0 ? props.options[0].label : '');

        return el('div', { 
            className: 'align-custom-select-wrapper ' + (isOpen ? 'is-open' : ''), 
            ref: wrapperRef,
            style: { marginBottom: '16px', position: 'relative', fontFamily: '-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif' } 
        }, [
            props.label ? el('label', { key: 'label' }, props.label) : null,
            el('button', {
                key: 'button',
                className: 'align-select-btn',
                onClick: function(e) { e.preventDefault(); setIsOpen(!isOpen); },
                type: 'button'
            }, [
                el('span', { key: 'text' }, displayLabel),
                el('span', { key: 'arrow', className: 'align-custom-select-arrow' })
            ]),
            isOpen ? el('div', {
                key: 'dropdown',
                className: 'align-select-dropdown'
            }, props.options.map(function(opt, index) {
                return el('div', {
                    key: index,
                    className: 'align-select-opt ' + (opt.disabled ? 'is-disabled' : ''),
                    onClick: function() {
                        if (opt.disabled) return;
                        setIsOpen(false);
                        if (props.onChange) props.onChange(opt.value);
                    }
                }, opt.label);
            })) : null
        ]);
    };

    const breadcrumbIcon = el('svg', { width: 24, height: 24, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round' }, [
        el('path', { key: 'p1', d: 'm6 17 5-5-5-5' }),
        el('path', { key: 'p2', d: 'm13 17 5-5-5-5' })
    ]);

    registerBlockType('align/breadcrumb', {
        icon: breadcrumbIcon,
        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { separator, homeText, showCurrent, customClass, elementId, ariaLabel } = attributes;

            const blockProps = useBlockProps({
                className: 'align-breadcrumb-block ' + (customClass || ''),
                id: elementId || undefined
            });

            const separatorOptions = [
                { label: '/ (Slash)', value: '/' },
                { label: '> (Angle Right)', value: '>' },
                { label: '» (Double Angle)', value: '»' },
                { label: '| (Pipe)', value: '|' },
                { label: '- (Dash)', value: '-' }
            ];

            return el(Fragment, {}, [
                el(InspectorControls, { key: 'inspector' },
                    el(TabPanel, {
                        className: 'align-tab-panel align-qb-tab-panel',
                        activeClass: 'is-active',
                        tabs: [
                            { name: 'editor', title: __('Editor', 'align-function'), className: 'align-qb-tab-item' },
                            { name: 'seo', title: __('SEO/ADA', 'align-function'), className: 'align-qb-tab-item' },
                            { name: 'technical', title: __('Technical', 'align-function'), className: 'align-qb-tab-item' }
                        ]
                    }, function(tab) {
                        if (tab.name === 'editor') {
                            return el('div', { className: 'align-qb-tab-content' }, [
                                el(PanelBody, { title: __('Breadcrumb Settings', 'align-function'), initialOpen: true }, [
                                    el(TextControl, {
                                        label: __('Home Label', 'align-function'),
                                        value: homeText || 'Home',
                                        onChange: function(val) { setAttributes({ homeText: val }); }
                                    }),
                                    el(AlignSelectControl, {
                                        label: __('Separator', 'align-function'),
                                        value: separator || '/',
                                        options: separatorOptions,
                                        onChange: function(val) { setAttributes({ separator: val }); }
                                    }),
                                    el(ToggleControl, {
                                        label: __('Show Current Page/Post', 'align-function'),
                                        checked: !!showCurrent,
                                        onChange: function(val) { setAttributes({ showCurrent: val }); }
                                    })
                                ])
                            ]);
                        }
                        if (tab.name === 'seo') {
                            return el('div', { className: 'align-qb-tab-content' }, [
                                el(PanelBody, { title: __('SEO & ADA Settings', 'align-function'), initialOpen: true }, [
                                    el(TextControl, {
                                        label: __('Aria Label (Accessibility)', 'align-function'),
                                        value: ariaLabel || 'Breadcrumb',
                                        onChange: function(val) { setAttributes({ ariaLabel: val }); },
                                        help: __('Screen reader label for nav container.', 'align-function')
                                    })
                                ])
                            ]);
                        }
                        if (tab.name === 'technical') {
                            return el('div', { className: 'align-qb-tab-content' }, [
                                el(PanelBody, { title: __('Technical Settings', 'align-function'), initialOpen: true }, [
                                    el(TextControl, {
                                        label: __('Custom CSS Class', 'align-function'),
                                        value: customClass || '',
                                        onChange: function(val) { setAttributes({ customClass: val }); }
                                    }),
                                    el(TextControl, {
                                        label: __('HTML ID Attribute', 'align-function'),
                                        value: elementId || '',
                                        onChange: function(val) { setAttributes({ elementId: val }); }
                                    })
                                ])
                            ]);
                        }
                    })
                ),
                el('nav', blockProps,
                    el('ol', { className: 'align-breadcrumb-list' }, [
                        el('li', { key: 'home', className: 'align-breadcrumb-item' },
                            el('a', { href: 'javascript:void(0);', onClick: function(e) { e.preventDefault(); } }, homeText || 'Home')
                        ),
                        el('li', { key: 'sep1', className: 'align-breadcrumb-separator' }, separator || '/'),
                        el('li', { key: 'cat', className: 'align-breadcrumb-item' },
                            el('a', { href: 'javascript:void(0);', onClick: function(e) { e.preventDefault(); } }, __('Category', 'align-function'))
                        ),
                        showCurrent ? el('li', { key: 'sep2', className: 'align-breadcrumb-separator' }, separator || '/') : null,
                        showCurrent ? el('li', { key: 'current', className: 'align-breadcrumb-item is-current' },
                            el('span', {}, __('Sample Page Title', 'align-function'))
                        ) : null
                    ])
                )
            ]);
        },
        save: function() {
            return null;
        }
    });
})();
