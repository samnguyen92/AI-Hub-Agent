(function(wp) {
    if (!wp || !wp.element) return;

    const { __ } = wp.i18n;
    const { InspectorControls } = wp.blockEditor;
    const { PanelBody, TextControl, Button, TextareaControl, ToggleControl, TabPanel } = wp.components;
    const { createElement: el, Fragment, useState } = wp.element;
    const { useSelect } = wp.data;
    const { AlignSelectControl, AlignLinkSearchControl } = window;

    function ElementInspectorControls(props) {
        const { attributes, setAttributes, clientId } = props;
        const { 
            tag, href, hrefId, content, target, rel, ariaLabel,
            svgInnerCode, className, elementId, dataEditId, customAttributes,
            enableAnchor, anchorId
        } = attributes;

        const [newItemTag, setNewItemTag] = useState('li');

        // Check if the current user has the subscriber role
        const isSubscriber = useSelect(function(select) {
            const user = select('core').getCurrentUser();
            return user && user.roles ? user.roles.includes('subscriber') : false;
        }, []);

        const standardTargets = ['', '_self', '_blank', '_parent', '_top'];
        const standardRels = ['', 'noopener noreferrer', 'nofollow', 'noopener noreferrer nofollow', 'sponsored', 'ugc'];

        const [isCustomTarget, setIsCustomTarget] = useState(function() {
            return !standardTargets.includes(target || '');
        });
        const [isCustomRel, setIsCustomRel] = useState(function() {
            return !standardRels.includes(rel || '');
        });

        wp.element.useEffect(function() {
            setIsCustomTarget(!standardTargets.includes(attributes.target || ''));
        }, [attributes.target]);

        wp.element.useEffect(function() {
            setIsCustomRel(!standardRels.includes(attributes.rel || ''));
        }, [attributes.rel]);

        const targetSelectValue = isCustomTarget ? 'custom' : (target || '');
        const relSelectValue = isCustomRel ? 'custom' : (rel || '');

        const tabs = [
            { name: 'editor', title: __('Editor', 'align-function'), className: 'align-qb-tab-item' }
        ];
        if (tag === 'a') {
            tabs.push({ name: 'seo', title: __('SEO/ADA', 'align-function'), className: 'align-qb-tab-item' });
        }
        tabs.push({ name: 'technical', title: __('Technical', 'align-function'), className: 'align-qb-tab-item' });

        return el(InspectorControls, {}, [
            TabPanel ? el(TabPanel, {
                className: 'align-tab-panel align-qb-tab-panel',
                activeClass: 'is-active',
                tabs: tabs
            }, function(tab) {
                if (tab.name === 'editor') {
                    return el('div', { className: 'align-qb-tab-content' }, [
                        el(PanelBody, { title: __('Editor', 'align-function'), initialOpen: true }, [
                            tag !== 'svg' ? el(TextareaControl, {
                                label: __('Text Content', 'align-function'),
                                value: content,
                                onChange: function(value) { setAttributes({ content: value }); },
                                help: __('Enter text or HTML content for this element.', 'align-function'),
                                placeholder: __('Enter text or HTML content...', 'align-function')
                            }) : null,
                            tag === 'a' ? el(AlignLinkSearchControl, {
                                label: __('Link URL (href)', 'align-function'),
                                value: href,
                                hrefId: hrefId,
                                onChange: function(value, id) { setAttributes({ href: value, hrefId: id }); }
                            }) : null,
                            (tag === 'a' || tag === 'button') ? el(Fragment, {}, [
                                el(ToggleControl, {
                                    label: __('Anchor Scroll', 'align-function'),
                                    checked: !!enableAnchor,
                                    onChange: function(value) { setAttributes({ enableAnchor: value }); }
                                }),
                                enableAnchor ? el(TextControl, {
                                    label: __('Anchor ID', 'align-function'),
                                    value: anchorId || '',
                                    onChange: function(value) { setAttributes({ anchorId: value }); },
                                    help: __('Enter the ID of the element to scroll to (without #).', 'align-function'),
                                    placeholder: __('e.g. contact-section', 'align-function')
                                }) : null
                            ]) : null,
                            tag === 'svg' ? el(TextareaControl, {
                                label: __('Raw SVG Inner Code', 'align-function'),
                                value: svgInnerCode,
                                onChange: function(value) { setAttributes({ svgInnerCode: value }); },
                                help: __('Paste raw SVG inner tags here (e.g. <path.../>). Add viewBox etc. to Custom Attributes above.', 'align-function')
                            }) : null
                        ]),
                        (tag === 'ul' || tag === 'ol') ? el(PanelBody, { title: __('List Items Manager', 'align-function'), initialOpen: true }, [
                            el(AlignSelectControl, {
                                label: __('New Item Tag', 'align-function'),
                                value: newItemTag,
                                options: [
                                    { label: 'li (List Item)', value: 'li' },
                                    { label: 'ol (Ordered List)', value: 'ol' },
                                    { label: 'ul (Unordered List)', value: 'ul' }
                                ],
                                onChange: function(val) { setNewItemTag(val); }
                            }),
                            el(Button, {
                                isPrimary: true,
                                style: { width: '100%', justifyContent: 'center' },
                                onClick: function() {
                                    const currentChildren = wp.data.select('core/block-editor').getBlockOrder(clientId);
                                    const newBlock = wp.blocks.createBlock('align/element', { tag: newItemTag });
                                    wp.data.dispatch('core/block-editor').insertBlocks(newBlock, currentChildren.length, clientId);
                                    
                                    setTimeout(function() {
                                        wp.data.dispatch('core/block-editor').selectBlock(newBlock.clientId);
                                    }, 50);
                                }
                            }, __('Add New Item', 'align-function'))
                        ]) : null
                    ]);
                }
                if (tab.name === 'seo') {
                    return el('div', { className: 'align-qb-tab-content' }, [
                        el(PanelBody, { title: __('SEO/ADA', 'align-function'), initialOpen: true }, [
                            el(TextControl, {
                                label: __('Aria Label', 'align-function'),
                                value: ariaLabel,
                                onChange: function(value) { setAttributes({ ariaLabel: value }); },
                                help: __('e.g. View more details, Read more about us', 'align-function'),
                                placeholder: __('View more, Read details...', 'align-function')
                            }),
                            el(AlignSelectControl, {
                                label: __('Target (_blank, etc)', 'align-function'),
                                value: targetSelectValue,
                                onChange: function(value) {
                                    if (value === 'custom') {
                                        setIsCustomTarget(true);
                                        setAttributes({ target: '' });
                                    } else {
                                        setIsCustomTarget(false);
                                        setAttributes({ target: value });
                                    }
                                },
                                options: [
                                    { label: __('Not Set (Default)', 'align-function'), value: '' },
                                    { label: __('Same Window/Tab (_self)', 'align-function'), value: '_self' },
                                    { label: __('New Window/Tab (_blank)', 'align-function'), value: '_blank' },
                                    { label: __('Parent Frame (_parent)', 'align-function'), value: '_parent' },
                                    { label: __('Top Frame (_top)', 'align-function'), value: '_top' },
                                    { label: __('Custom...', 'align-function'), value: 'custom' }
                                ]
                            }),
                            isCustomTarget ? el(TextControl, {
                                label: __('Custom Target Value', 'align-function'),
                                value: target,
                                onChange: function(value) { setAttributes({ target: value }); },
                                placeholder: __('Enter custom target (e.g. custom_frame)...', 'align-function')
                            }) : null,
                            el(AlignSelectControl, {
                                label: __('Rel (noopener noreferrer)', 'align-function'),
                                value: relSelectValue,
                                onChange: function(value) {
                                    if (value === 'custom') {
                                        setIsCustomRel(true);
                                        setAttributes({ rel: '' });
                                    } else {
                                        setIsCustomRel(false);
                                        setAttributes({ rel: value });
                                    }
                                },
                                options: [
                                    { label: __('None (Default)', 'align-function'), value: '' },
                                    { label: __('Noopener Noreferrer (Recommended for _blank)', 'align-function'), value: 'noopener noreferrer' },
                                    { label: __('Nofollow (SEO Private Link)', 'align-function'), value: 'nofollow' },
                                    { label: __('Noopener Noreferrer Nofollow', 'align-function'), value: 'noopener noreferrer nofollow' },
                                    { label: __('Sponsored (Ad Link)', 'align-function'), value: 'sponsored' },
                                    { label: __('UGC (User Generated Content)', 'align-function'), value: 'ugc' },
                                    { label: __('Custom...', 'align-function'), value: 'custom' }
                                ]
                            }),
                            isCustomRel ? el(TextControl, {
                                label: __('Custom Rel Value', 'align-function'),
                                value: rel,
                                onChange: function(value) { setAttributes({ rel: value }); },
                                placeholder: __('Enter custom rel (e.g. next, prev)...', 'align-function')
                            }) : null
                        ])
                    ]);
                }
                if (tab.name === 'technical') {
                    return el('div', { className: 'align-qb-tab-content' }, [
                        el(PanelBody, { title: __('Technical', 'align-function'), initialOpen: true }, [
                            el(AlignSelectControl, {
                                label: __('HTML Tag', 'align-function'),
                                value: tag,
                                options: [
                                    { label: '--- Container Elements ---', disabled: true },
                                    { label: 'div', value: 'div' },
                                    { label: 'button', value: 'button' },
                                    { label: 'section', value: 'section' },
                                    { label: 'article', value: 'article' },
                                    { label: 'header', value: 'header' },
                                    { label: 'footer', value: 'footer' },
                                    { label: 'main', value: 'main' },
                                    { label: 'aside', value: 'aside' },
                                    { label: 'nav', value: 'nav' },
                                    { label: 'a (Link)', value: 'a' },
                                    { label: '--- List Elements ---', disabled: true },
                                    { label: 'ul (Unordered List)', value: 'ul' },
                                    { label: 'ol (Ordered List)', value: 'ol' },
                                    { label: 'li (List Item)', value: 'li' },
                                    { label: '--- Media & Graphic ---', disabled: true },
                                    { label: 'svg', value: 'svg' },
                                    { label: '--- Text Elements ---', disabled: true },
                                    { label: 'p', value: 'p' },
                                    { label: 'span', value: 'span' },
                                    { label: 'strong (Bold)', value: 'strong' },
                                    { label: 'em (Italic)', value: 'em' },
                                    { label: 'h1', value: 'h1' },
                                    { label: 'h2', value: 'h2' },
                                    { label: 'h3', value: 'h3' },
                                    { label: 'h4', value: 'h4' },
                                    { label: 'h5', value: 'h5' },
                                    { label: 'h6', value: 'h6' }
                                ],
                                onChange: function(value) {
                                    setAttributes({ tag: value });
                                    if (value === 'ul' || value === 'ol') {
                                        const currentChildren = wp.data.select('core/block-editor').getBlockOrder(clientId);
                                        if (currentChildren.length === 0) {
                                            const innerBlock = wp.blocks.createBlock('align/element', { tag: 'li' });
                                            wp.data.dispatch('core/block-editor').insertBlocks(innerBlock, 0, clientId);
                                        }
                                    }
                                }
                            }),
                            el(TextControl, {
                                label: __('CSS Class', 'align-function'),
                                value: className || '',
                                onChange: function(value) { setAttributes({ className: value }); },
                                help: __('Separate multiple classes with spaces.', 'align-function'),
                                placeholder: __('e.g. flex items-center gap-4...', 'align-function')
                            }),
                            el(TextControl, {
                                label: __('Element ID', 'align-function'),
                                value: elementId,
                                onChange: function(value) { setAttributes({ elementId: value }); },
                                help: __('Unique ID for this element.', 'align-function'),
                                placeholder: __('e.g. hero-section...', 'align-function')
                            }),
                            el(TextControl, {
                                label: __('Custom Attributes', 'align-function'),
                                value: customAttributes,
                                onChange: function(value) { setAttributes({ customAttributes: value }); },
                                help: __('e.g. data-aos="fade-up" aria-hidden="true"', 'align-function'),
                                placeholder: __('e.g. data-aos="fade-up"...', 'align-function')
                            })
                        ])
                    ]);
                }
            }) : null
        ]);
    }

    window.AlignElementInspectorControls = ElementInspectorControls;
})(window.wp);
