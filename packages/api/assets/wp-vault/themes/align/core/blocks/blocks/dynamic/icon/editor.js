(function() {
    const { __ } = wp.i18n;
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps, MediaUpload, MediaUploadCheck } = wp.blockEditor;
    const { PanelBody, TextControl, TextareaControl, TabPanel, Button, BaseControl, RangeControl } = wp.components;
    const { createElement: el, Fragment, useState, useRef, useEffect } = wp.element;

    const AlignSelectControl = function(props) {
        const [isOpen, setIsOpen] = useState(false);
        const wrapperRef = useRef(null);

        useEffect(function() {
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

    const iconBlockSvg = el('svg', { width: 24, height: 24, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round' },
        el('polygon', { points: '12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2' })
    );

    registerBlockType('align/icon', {
        icon: iconBlockSvg,
        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { iconMode, lucideIcon, customSvg, customImgId, customImgUrl, iconSize, iconColor, customClass, elementId } = attributes;
            const [searchTerm, setSearchTerm] = useState('');

            const lucideIconsDict = window.ALIGN_LUCIDE_ICONS || {};
            const iconNames = Object.keys(lucideIconsDict);

            const filteredIconNames = iconNames.filter(function(name) {
                return name.toLowerCase().includes(searchTerm.toLowerCase().trim());
            });

            function getRelativeUrl(url) {
                if (!url) return '';
                try {
                    var parsed = new URL(url);
                    return parsed.pathname + parsed.search + parsed.hash;
                } catch (e) {
                    var currentOrigin = window.location.origin;
                    if (url.indexOf(currentOrigin) === 0) {
                        return url.substring(currentOrigin.length);
                    }
                    return url;
                }
            }

            const blockProps = useBlockProps({
                className: 'align-icon-block ' + (customClass || ''),
                id: elementId || undefined,
                style: {
                    display: 'inline-flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    width: (iconSize || 24) + 'px',
                    height: (iconSize || 24) + 'px',
                    color: iconColor || undefined
                }
            });

            const modeOptions = [
                { label: __('Lucide Icon Library', 'align-function'), value: 'lucide' },
                { label: __('Custom SVG Code', 'align-function'), value: 'svg' },
                { label: __('Custom Image (Media)', 'align-function'), value: 'image' }
            ];

            function renderLucideSvg(pathsHtml, size) {
                return el('svg', {
                    className: 'align-icon-svg',
                    width: size || 24,
                    height: size || 24,
                    viewBox: '0 0 24 24',
                    fill: 'none',
                    stroke: 'currentColor',
                    strokeWidth: 2,
                    strokeLinecap: 'round',
                    strokeLinejoin: 'round',
                    dangerouslySetInnerHTML: { __html: pathsHtml }
                });
            }

            function renderCanvasIcon() {
                if (iconMode === 'svg' && customSvg && customSvg.trim()) {
                    return el('span', { className: 'align-icon-custom-svg', dangerouslySetInnerHTML: { __html: customSvg } });
                }
                if (iconMode === 'image' && customImgUrl && customImgUrl.trim()) {
                    return el('img', { src: customImgUrl, alt: '', style: { width: '100%', height: '100%', objectFit: 'contain' } });
                }
                var selectedPaths = lucideIconsDict[lucideIcon || 'star'] || lucideIconsDict['star'];
                return renderLucideSvg(selectedPaths, iconSize);
            }

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
                                el(PanelBody, { title: __('Icon Selection', 'align-function'), initialOpen: true }, [
                                    el(AlignSelectControl, {
                                        label: __('Icon Source Mode', 'align-function'),
                                        value: iconMode || 'lucide',
                                        options: modeOptions,
                                        onChange: function(val) { setAttributes({ iconMode: val }); }
                                    }),

                                    (iconMode || 'lucide') === 'lucide' ? el('div', { className: 'align-lucide-picker-wrapper', style: { marginTop: '12px' } }, [
                                        el(TextControl, {
                                            label: __('Search Lucide Icons', 'align-function'),
                                            value: searchTerm,
                                            onChange: function(val) { setSearchTerm(val); },
                                            placeholder: __('Type e.g. search, user, arrow...', 'align-function')
                                        }),
                                        el('div', {
                                            className: 'align-lucide-grid'
                                        }, filteredIconNames.length > 0 ? filteredIconNames.map(function(name) {
                                            const isSelected = lucideIcon === name;
                                            return el('button', {
                                                key: name,
                                                type: 'button',
                                                title: name,
                                                onClick: function(e) {
                                                    e.preventDefault();
                                                    setAttributes({ lucideIcon: name });
                                                },
                                                style: {
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center',
                                                    aspectRatio: '1 / 1',
                                                    padding: '6px',
                                                    border: isSelected ? '2px solid #2563eb' : '1px solid #e2e8f0',
                                                    borderRadius: '6px',
                                                    background: isSelected ? '#eff6ff' : '#ffffff',
                                                    color: isSelected ? '#2563eb' : '#334155',
                                                    cursor: 'pointer',
                                                    transition: 'all 0.15s ease'
                                                }
                                            }, renderLucideSvg(lucideIconsDict[name], 20));
                                        }) : el('p', { style: { gridColumn: 'span 5', textAlign: 'center', color: '#94a3b8', padding: '12px 0', fontSize: '12px' } }, __('No icons found', 'align-function')))
                                    ]) : null,

                                    iconMode === 'svg' ? el(TextareaControl, {
                                        label: __('Custom SVG Code', 'align-function'),
                                        value: customSvg || '',
                                        onChange: function(val) { setAttributes({ customSvg: val }); },
                                        help: __('Paste raw <svg>...</svg> code here.', 'align-function')
                                    }) : null,

                                    iconMode === 'image' ? el(BaseControl, { label: __('Custom Image Icon', 'align-function') },
                                        el(MediaUploadCheck, {},
                                            el(MediaUpload, {
                                                onSelect: function(media) {
                                                    setAttributes({ customImgId: media.id, customImgUrl: getRelativeUrl(media.url) });
                                                },
                                                allowedTypes: ['image'],
                                                value: customImgId,
                                                render: function(obj) {
                                                    if (!customImgUrl) {
                                                        return el(Button, {
                                                            className: 'align-media-sidebar-dropzone',
                                                            onClick: obj.open,
                                                            style: { width: '100%', justifyContent: 'center' }
                                                        }, [
                                                            el('span', { className: 'dashicons dashicons-format-image', style: { marginRight: '6px' } }),
                                                            el('span', {}, __('Select Image from Media', 'align-function'))
                                                        ]);
                                                    }
                                                    return el('div', { className: 'align-media-sidebar-preview-wrapper', style: { display: 'flex', alignItems: 'center', gap: '8px' } }, [
                                                        el('div', { 
                                                            onClick: obj.open,
                                                            title: __('Click to change image', 'align-function'),
                                                            style: { cursor: 'pointer', border: '1px solid #cbd5e1', padding: '4px', borderRadius: '4px', background: '#f8fafc' }
                                                        }, el('img', { src: customImgUrl, alt: '', style: { width: '32px', height: '32px', objectFit: 'contain', display: 'block' } })),
                                                        el(Button, {
                                                            onClick: function(e) {
                                                                e.stopPropagation();
                                                                setAttributes({ customImgId: 0, customImgUrl: '' });
                                                            },
                                                            isDestructive: true,
                                                            isSmall: true
                                                        }, __('Remove', 'align-function'))
                                                    ]);
                                                }
                                            })
                                        )
                                    ) : null
                                ]),

                                el(PanelBody, { title: __('Icon Styling', 'align-function'), initialOpen: false }, [
                                    el(RangeControl, {
                                        label: __('Icon Size (px)', 'align-function'),
                                        value: iconSize || 24,
                                        onChange: function(val) { setAttributes({ iconSize: val }); },
                                        min: 12,
                                        max: 120
                                    }),
                                    el(TextControl, {
                                        label: __('Icon Color (CSS)', 'align-function'),
                                        value: iconColor || '',
                                        onChange: function(val) { setAttributes({ iconColor: val }); },
                                        placeholder: __('e.g. #2563eb or currentColor', 'align-function')
                                    })
                                ])
                            ]);
                        }
                        if (tab.name === 'seo') {
                            return el('div', { className: 'align-qb-tab-content' }, [
                                el(PanelBody, { title: __('SEO & Accessibility', 'align-function'), initialOpen: true }, [
                                    el('p', { className: 'components-base-control__help' },
                                        __('SVG Icons render as inline decorative elements. Set aria-hidden="true" on parent containers if purely decorative.', 'align-function')
                                    )
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
                el('div', blockProps, renderCanvasIcon())
            ]);
        },
        save: function() {
            return null;
        }
    });
})();
