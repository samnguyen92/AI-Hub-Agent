(function() {
    const { __ } = wp.i18n;
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps, MediaUpload, MediaUploadCheck } = wp.blockEditor;
    const { PanelBody, TextControl, TextareaControl, ToggleControl, TabPanel, Button, BaseControl, RadioControl } = wp.components;
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

    registerBlockType('align/share', {
        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { showFB, showX, showLinkedIn, showEmail, showCopy, buttonStyle, layout, customClass, elementId } = attributes;

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

            function renderEditorIcon(iconType, customSvg, customImgUrl, defaultSvgNode) {
                if (iconType === 'image' && customImgUrl && customImgUrl.trim()) {
                    return el('img', { src: customImgUrl, className: 'align-share-icon', style: { width: 18, height: 18, objectFit: 'contain' } });
                }
                if ((!iconType || iconType === 'svg') && customSvg && customSvg.trim()) {
                    return el('span', { className: 'align-share-icon', dangerouslySetInnerHTML: { __html: customSvg } });
                }
                return defaultSvgNode;
            }

            function renderMediaControl(label, imgId, imgUrl, onSelectMedia, onRemoveMedia) {
                return el(BaseControl, { label: label, key: label },
                    el(MediaUploadCheck, {},
                        el(MediaUpload, {
                            onSelect: function(media) {
                                onSelectMedia(media.id, getRelativeUrl(media.url));
                            },
                            allowedTypes: ['image'],
                            value: imgId,
                            render: function(obj) {
                                if (!imgUrl) {
                                    return el(Button, {
                                        className: 'align-media-sidebar-dropzone',
                                        onClick: obj.open,
                                        style: { width: '100%', justifyContent: 'center', margin: '4px 0 12px 0' }
                                    }, [
                                        el('span', { className: 'dashicons dashicons-format-image', style: { marginRight: '6px' } }),
                                        el('span', {}, __('Select Image from Media', 'align-function'))
                                    ]);
                                }
                                return el('div', { className: 'align-media-sidebar-preview-wrapper', style: { display: 'flex', alignItems: 'center', gap: '8px', margin: '4px 0 12px 0' } }, [
                                    el('div', { 
                                        className: 'align-media-sidebar-image-preview-clickable',
                                        onClick: obj.open,
                                        title: __('Click to change image', 'align-function'),
                                        style: { cursor: 'pointer', border: '1px solid #cbd5e1', padding: '4px', borderRadius: '4px', background: '#f8fafc' }
                                    }, el('img', { src: imgUrl, alt: '', style: { width: '24px', height: '24px', objectFit: 'contain', display: 'block' } })),
                                    el(Button, {
                                        className: 'align-media-sidebar-remove-btn',
                                        onClick: function(e) {
                                            e.stopPropagation();
                                            onRemoveMedia();
                                        },
                                        isDestructive: true,
                                        isSmall: true
                                    }, __('Remove Image', 'align-function'))
                                ]);
                            }
                        })
                    )
                );
            }

            const blockProps = useBlockProps({
                className: 'align-share-block align-share-layout-' + (layout || 'row') + ' align-share-style-' + (buttonStyle || 'icon_text') + ' ' + (customClass || ''),
                id: elementId || undefined
            });

            const styleOptions = [
                { label: __('Icon & Text', 'align-function'), value: 'icon_text' },
                { label: __('Icon Only', 'align-function'), value: 'icon_only' }
            ];

            const layoutOptions = [
                { label: __('Horizontal Row', 'align-function'), value: 'row' },
                { label: __('Vertical Column', 'align-function'), value: 'column' }
            ];

            const formatOptions = [
                { label: __('SVG Code', 'align-function'), value: 'svg' },
                { label: __('Image File', 'align-function'), value: 'image' }
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
                                el(PanelBody, { title: __('Share Platforms', 'align-function'), initialOpen: true }, [
                                    el(ToggleControl, {
                                        label: __('Facebook', 'align-function'),
                                        checked: !!showFB,
                                        onChange: function(val) { setAttributes({ showFB: val }); }
                                    }),
                                    el(ToggleControl, {
                                        label: __('X (Twitter)', 'align-function'),
                                        checked: !!showX,
                                        onChange: function(val) { setAttributes({ showX: val }); }
                                    }),
                                    el(ToggleControl, {
                                        label: __('LinkedIn', 'align-function'),
                                        checked: !!showLinkedIn,
                                        onChange: function(val) { setAttributes({ showLinkedIn: val }); }
                                    }),
                                    el(ToggleControl, {
                                        label: __('Email', 'align-function'),
                                        checked: !!showEmail,
                                        onChange: function(val) { setAttributes({ showEmail: val }); }
                                    }),
                                    el(ToggleControl, {
                                        label: __('Copy Link', 'align-function'),
                                        checked: !!showCopy,
                                        onChange: function(val) { setAttributes({ showCopy: val }); }
                                    })
                                ]),
                                el(PanelBody, { title: __('Link Action Mode', 'align-function'), initialOpen: false }, [
                                    el(AlignSelectControl, {
                                        label: __('Link Mode', 'align-function'),
                                        value: attributes.linkMode || 'share',
                                        options: [
                                            { label: __('Auto Share Current Page', 'align-function'), value: 'share' },
                                            { label: __('Custom Profile Links', 'align-function'), value: 'custom' }
                                        ],
                                        onChange: function(val) { setAttributes({ linkMode: val }); }
                                    }),
                                    (attributes.linkMode === 'custom' && showFB) ? el(TextControl, {
                                        label: __('Facebook Custom URL', 'align-function'),
                                        value: attributes.customFbUrl || '',
                                        onChange: function(val) { setAttributes({ customFbUrl: val }); }
                                    }) : null,
                                    (attributes.linkMode === 'custom' && showX) ? el(TextControl, {
                                        label: __('X (Twitter) Custom URL', 'align-function'),
                                        value: attributes.customXUrl || '',
                                        onChange: function(val) { setAttributes({ customXUrl: val }); }
                                    }) : null,
                                    (attributes.linkMode === 'custom' && showLinkedIn) ? el(TextControl, {
                                        label: __('LinkedIn Custom URL', 'align-function'),
                                        value: attributes.customLinkedInUrl || '',
                                        onChange: function(val) { setAttributes({ customLinkedInUrl: val }); }
                                    }) : null,
                                    (attributes.linkMode === 'custom' && showEmail) ? el(TextControl, {
                                        label: __('Email / Mailto Custom URL', 'align-function'),
                                        value: attributes.customEmailUrl || '',
                                        onChange: function(val) { setAttributes({ customEmailUrl: val }); }
                                    }) : null
                                ]),
                                el(PanelBody, { title: __('Custom Icons & Graphics', 'align-function'), initialOpen: false }, [
                                    showFB ? el(Fragment, { key: 'fb-icon-cfg' }, [
                                        el(AlignSelectControl, {
                                            label: __('Facebook Icon Format', 'align-function'),
                                            value: attributes.fbIconType || 'svg',
                                            options: formatOptions,
                                            onChange: function(val) { setAttributes({ fbIconType: val }); }
                                        }),
                                        (attributes.fbIconType || 'svg') === 'svg' ? el(TextareaControl, {
                                            label: __('Custom SVG Code', 'align-function'),
                                            value: attributes.fbCustomSvg || '',
                                            onChange: function(val) { setAttributes({ fbCustomSvg: val }); },
                                            help: __('Paste full <svg>...</svg> markup (leave blank for Lucide SVG).', 'align-function')
                                        }) : renderMediaControl(
                                            __('Select Custom Image Icon', 'align-function'),
                                            attributes.fbCustomImgId,
                                            attributes.fbCustomImgUrl,
                                            function(id, url) { setAttributes({ fbCustomImgId: id, fbCustomImgUrl: url }); },
                                            function() { setAttributes({ fbCustomImgId: 0, fbCustomImgUrl: '' }); }
                                        )
                                    ]) : null,
                                    showX ? el(Fragment, { key: 'x-icon-cfg' }, [
                                        el(AlignSelectControl, {
                                            label: __('X (Twitter) Icon Format', 'align-function'),
                                            value: attributes.xIconType || 'svg',
                                            options: formatOptions,
                                            onChange: function(val) { setAttributes({ xIconType: val }); }
                                        }),
                                        (attributes.xIconType || 'svg') === 'svg' ? el(TextareaControl, {
                                            label: __('Custom SVG Code', 'align-function'),
                                            value: attributes.xCustomSvg || '',
                                            onChange: function(val) { setAttributes({ xCustomSvg: val }); },
                                            help: __('Paste full <svg>...</svg> markup (leave blank for Lucide SVG).', 'align-function')
                                        }) : renderMediaControl(
                                            __('Select Custom Image Icon', 'align-function'),
                                            attributes.xCustomImgId,
                                            attributes.xCustomImgUrl,
                                            function(id, url) { setAttributes({ xCustomImgId: id, xCustomImgUrl: url }); },
                                            function() { setAttributes({ xCustomImgId: 0, xCustomImgUrl: '' }); }
                                        )
                                    ]) : null,
                                    showLinkedIn ? el(Fragment, { key: 'li-icon-cfg' }, [
                                        el(AlignSelectControl, {
                                            label: __('LinkedIn Icon Format', 'align-function'),
                                            value: attributes.linkedinIconType || 'svg',
                                            options: formatOptions,
                                            onChange: function(val) { setAttributes({ linkedinIconType: val }); }
                                        }),
                                        (attributes.linkedinIconType || 'svg') === 'svg' ? el(TextareaControl, {
                                            label: __('Custom SVG Code', 'align-function'),
                                            value: attributes.linkedinCustomSvg || '',
                                            onChange: function(val) { setAttributes({ linkedinCustomSvg: val }); },
                                            help: __('Paste full <svg>...</svg> markup (leave blank for Lucide SVG).', 'align-function')
                                        }) : renderMediaControl(
                                            __('Select Custom Image Icon', 'align-function'),
                                            attributes.linkedinCustomImgId,
                                            attributes.linkedinCustomImgUrl,
                                            function(id, url) { setAttributes({ linkedinCustomImgId: id, linkedinCustomImgUrl: url }); },
                                            function() { setAttributes({ linkedinCustomImgId: 0, linkedinCustomImgUrl: '' }); }
                                        )
                                    ]) : null,
                                    showEmail ? el(Fragment, { key: 'em-icon-cfg' }, [
                                        el(AlignSelectControl, {
                                            label: __('Email Icon Format', 'align-function'),
                                            value: attributes.emailIconType || 'svg',
                                            options: formatOptions,
                                            onChange: function(val) { setAttributes({ emailIconType: val }); }
                                        }),
                                        (attributes.emailIconType || 'svg') === 'svg' ? el(TextareaControl, {
                                            label: __('Custom SVG Code', 'align-function'),
                                            value: attributes.emailCustomSvg || '',
                                            onChange: function(val) { setAttributes({ emailCustomSvg: val }); },
                                            help: __('Paste full <svg>...</svg> markup (leave blank for Lucide SVG).', 'align-function')
                                        }) : renderMediaControl(
                                            __('Select Custom Image Icon', 'align-function'),
                                            attributes.emailCustomImgId,
                                            attributes.emailCustomImgUrl,
                                            function(id, url) { setAttributes({ emailCustomImgId: id, emailCustomImgUrl: url }); },
                                            function() { setAttributes({ emailCustomImgId: 0, emailCustomImgUrl: '' }); }
                                        )
                                    ]) : null,
                                    showCopy ? el(Fragment, { key: 'cp-icon-cfg' }, [
                                        el(AlignSelectControl, {
                                            label: __('Copy Link Icon Format', 'align-function'),
                                            value: attributes.copyIconType || 'svg',
                                            options: formatOptions,
                                            onChange: function(val) { setAttributes({ copyIconType: val }); }
                                        }),
                                        (attributes.copyIconType || 'svg') === 'svg' ? el(TextareaControl, {
                                            label: __('Custom SVG Code', 'align-function'),
                                            value: attributes.copyCustomSvg || '',
                                            onChange: function(val) { setAttributes({ copyCustomSvg: val }); },
                                            help: __('Paste full <svg>...</svg> markup (leave blank for Lucide SVG).', 'align-function')
                                        }) : renderMediaControl(
                                            __('Select Custom Image Icon', 'align-function'),
                                            attributes.copyCustomImgId,
                                            attributes.copyCustomImgUrl,
                                            function(id, url) { setAttributes({ copyCustomImgId: id, copyCustomImgUrl: url }); },
                                            function() { setAttributes({ copyCustomImgId: 0, copyCustomImgUrl: '' }); }
                                        )
                                    ]) : null
                                ]),
                                el(PanelBody, { title: __('Layout & Appearance', 'align-function'), initialOpen: false }, [
                                    el(AlignSelectControl, {
                                        label: __('Button Style', 'align-function'),
                                        value: buttonStyle || 'icon_text',
                                        options: styleOptions,
                                        onChange: function(val) { setAttributes({ buttonStyle: val }); }
                                    })
                                ])
                            ]);
                        }
                        if (tab.name === 'seo') {
                            return el('div', { className: 'align-qb-tab-content' }, [
                                el(PanelBody, { title: __('SEO & ADA Settings', 'align-function'), initialOpen: true }, [
                                    el('p', { className: 'components-base-control__help' },
                                        __('Share links automatically use accessible aria-labels and security attributes (target="_blank" rel="noopener noreferrer").', 'align-function')
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
                el('div', blockProps, [
                    el('div', { key: 'list', className: 'align-share-buttons' }, [
                        showFB ? el('a', { key: 'fb', className: 'align-share-btn align-share-fb', href: 'javascript:void(0);', onClick: function(e) { e.preventDefault(); } }, [
                            renderEditorIcon(attributes.fbIconType, attributes.fbCustomSvg, attributes.fbCustomImgUrl,
                                el('svg', { key: 'icon', className: 'align-share-icon', width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round' },
                                    el('path', { d: 'M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z' })
                                )
                            ),
                            buttonStyle !== 'icon_only' ? el('span', { className: 'align-share-label' }, 'Facebook') : null
                        ]) : null,
                        showX ? el('a', { key: 'x', className: 'align-share-btn align-share-x', href: 'javascript:void(0);', onClick: function(e) { e.preventDefault(); } }, [
                            renderEditorIcon(attributes.xIconType, attributes.xCustomSvg, attributes.xCustomImgUrl,
                                el('svg', { key: 'icon', className: 'align-share-icon', width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round' },
                                    el('path', { d: 'M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2C3 15.5.5 9.6 3 5c2.2 2.6 5.6 4.1 9 4-.9-4.2 4-6.6 7-3.8 1.1 0 3-1.2 3-1.2z' })
                                )
                            ),
                            buttonStyle !== 'icon_only' ? el('span', { className: 'align-share-label' }, 'X') : null
                        ]) : null,
                        showLinkedIn ? el('a', { key: 'linkedin', className: 'align-share-btn align-share-linkedin', href: 'javascript:void(0);', onClick: function(e) { e.preventDefault(); } }, [
                            renderEditorIcon(attributes.linkedinIconType, attributes.linkedinCustomSvg, attributes.linkedinCustomImgUrl,
                                el('svg', { key: 'icon', className: 'align-share-icon', width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round' }, [
                                    el('path', { key: 'p', d: 'M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z' }),
                                    el('rect', { key: 'r', width: 4, height: 12, x: 2, y: 9 }),
                                    el('circle', { key: 'c', cx: 4, cy: 4, r: 2 })
                                ])
                            ),
                            buttonStyle !== 'icon_only' ? el('span', { className: 'align-share-label' }, 'LinkedIn') : null
                        ]) : null,
                        showEmail ? el('a', { key: 'email', className: 'align-share-btn align-share-email', href: 'javascript:void(0);', onClick: function(e) { e.preventDefault(); } }, [
                            renderEditorIcon(attributes.emailIconType, attributes.emailCustomSvg, attributes.emailCustomImgUrl,
                                el('svg', { key: 'icon', className: 'align-share-icon', width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round' }, [
                                    el('rect', { key: 'r', width: 20, height: 16, x: 2, y: 4, rx: 2 }),
                                    el('path', { key: 'p', d: 'm22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7' })
                                ])
                            ),
                            buttonStyle !== 'icon_only' ? el('span', { className: 'align-share-label' }, 'Email') : null
                        ]) : null,
                        showCopy ? el('button', { key: 'copy', className: 'align-share-btn align-share-copy', type: 'button', onClick: function(e) { e.preventDefault(); } }, [
                            renderEditorIcon(attributes.copyIconType, attributes.copyCustomSvg, attributes.copyCustomImgUrl,
                                el('svg', { key: 'icon', className: 'align-share-icon', width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round' }, [
                                    el('path', { key: 'p1', d: 'M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71' }),
                                    el('path', { key: 'p2', d: 'M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71' })
                                ])
                            ),
                            buttonStyle !== 'icon_only' ? el('span', { className: 'align-share-label' }, 'Copy Link') : null
                        ]) : null
                    ])
                ])
            ]);
        },
        save: function() {
            return null;
        }
    });
})();
