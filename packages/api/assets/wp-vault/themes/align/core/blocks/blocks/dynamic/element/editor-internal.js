(function(wp) {
    if (!wp || !wp.element) return;

    const { InspectorControls, RichText, InnerBlocks, useBlockProps } = wp.blockEditor;
    const { createElement: el, Fragment } = wp.element;
    const { useSelect } = wp.data;

    const {
        CONTAINER_TAGS,
        TEXT_TAGS,
        getBlockElement,
        useHideAdvancedPanel
    } = window.AlignElementUtils;

    function EditInternal(props) {
        const { attributes, setAttributes, clientId } = props;
        const hasChildBlocks = useSelect(function(select) {
            return select('core/block-editor').getBlockOrder(clientId).length > 0;
        }, [clientId]);
        const { 
            tag, type, elementId, dataEditId, customAttributes, href, content,
            target, rel, ariaLabel,
            svgInnerCode, className
        } = attributes;

        const isContainer = CONTAINER_TAGS.includes(tag) || type === 'inner';
        const isText = TEXT_TAGS.includes(tag) && !isContainer;

        useHideAdvancedPanel();

        // Custom hooks from extracted tab-effects.js
        if (window.AlignElementTabEffects) {
            window.AlignElementTabEffects.useSplideEffects(clientId);
            window.AlignElementTabEffects.useTabListeners(clientId);
        }

        // Auto-generate unique dataEditId if it doesn't exist for all tags (used for unique CSS class names)
        const allowedEditTags = ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span', 'strong', 'em', 'b', 'i', 'u', 'li', 'blockquote', 'a', 'code', 'pre', 'sub', 'sup'];
        const isAllowedTag = allowedEditTags.includes(String(tag).toLowerCase());

        wp.element.useEffect(function() {
            if (!dataEditId) {
                const randomId = 'edit-' + Math.random().toString(36).substring(2, 10);
                setAttributes({ dataEditId: randomId });
            }
        }, [dataEditId]);

        wp.element.useEffect(function() {
            const timer = setTimeout(function() {
                const blockEl = getBlockElement(clientId);
                if (blockEl) {
                    const layoutEl = blockEl.querySelector('.block-editor-block-list__layout');
                    if (layoutEl) {
                        if (isContainer && className && className.includes('splide__list')) {
                            layoutEl.classList.add('align_custom_block');
                            layoutEl.classList.add('splide__list');
                        } else {
                            layoutEl.classList.remove('align_custom_block', 'splide__list');
                        }
                    }
                }
            }, 100);
            return function() { clearTimeout(timer); };
        }, [isContainer, className, clientId]);

        const inspector = window.AlignElementInspectorControls 
            ? el(window.AlignElementInspectorControls, props) 
            : null;

        const blockProps = useBlockProps();

        const editorContent = isContainer 
            ? el(Fragment, {}, [
                tag === 'li' 
                    ? el(RichText, {
                        tagName: 'span',
                        value: content,
                        onChange: function(value) { setAttributes({ content: value }); },
                        placeholder: '',
                        className: 'align-element-text'
                    })
                    : (content ? el(RichText, {
                        tagName: 'div',
                        value: content,
                        onChange: function(value) { setAttributes({ content: value }); },
                        placeholder: '',
                        className: 'align-element-preview-text align-element-text'
                    }) : null),
                el(InnerBlocks, {
                    renderAppender: (hasChildBlocks || (content && String(content).trim() !== '')) ? false : () => el('div', {
                        className: 'align-appender-wrapper',
                        style: {
                            display: 'flex',
                            justifyContent: 'center',
                            alignItems: 'center',
                            width: '100%',
                            padding: '10px 0'
                        }
                    }, el(wp.blockEditor.InnerBlocks.ButtonBlockAppender))
                })
            ])
            : (isText 
                ? el(RichText, {
                    tagName: tag,
                    value: content,
                    onChange: function(value) { setAttributes({ content: value }); },
                    placeholder: '',
                    className: 'align-element-text'
                })
                : null);

        let displayTag = tag;
        if (tag === 'a' || (hasChildBlocks && !['ul', 'ol', 'li'].includes(tag))) {
            displayTag = 'div';
        }

        const extraProps = {};
        if (tag === 'svg' && svgInnerCode) {
            const trimmedSvg = svgInnerCode.trim();
            if (trimmedSvg.startsWith('<svg')) {
                try {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(trimmedSvg, "image/svg+xml");
                    const svgEl = doc.querySelector('svg');
                    if (svgEl) {
                        extraProps.dangerouslySetInnerHTML = { __html: svgEl.innerHTML };
                        extraProps.width = "100%";
                        extraProps.height = "100%";
                        Array.from(svgEl.attributes).forEach(attr => {
                            const name = attr.name.toLowerCase();
                            if (!['class', 'id', 'style', 'width', 'height'].includes(name)) {
                                if (name === 'viewbox') {
                                    extraProps.viewBox = attr.value;
                                } else {
                                    extraProps[attr.name] = attr.value;
                                }
                            }
                        });
                    } else {
                        extraProps.dangerouslySetInnerHTML = { __html: svgInnerCode };
                    }
                } catch (e) {
                    extraProps.dangerouslySetInnerHTML = { __html: svgInnerCode };
                }
            } else {
                extraProps.dangerouslySetInnerHTML = { __html: svgInnerCode };
            }
        }

        if (tag === 'a') {
            if (href) extraProps.href = href;
            if (target) extraProps.target = target;
            if (rel) extraProps.rel = rel;
            if (ariaLabel) extraProps['aria-label'] = ariaLabel;
        }

        if (elementId) {
            extraProps.id = elementId;
        }

        if (dataEditId && isAllowedTag) {
            extraProps['data-edit-id'] = dataEditId;
        }

        if (customAttributes && typeof customAttributes === 'string') {
            try {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = `<div ${customAttributes}></div>`;
                const tempEl = tempDiv.firstChild;
                if (tempEl && tempEl.attributes) {
                    Array.from(tempEl.attributes).forEach(attr => {
                        const name = attr.name.toLowerCase();
                        // For SVG tags, prioritize viewBox from the SVG markup to avoid layout breaks
                        if (tag === 'svg' && name === 'viewbox') {
                            return;
                        }
                        extraProps[attr.name] = attr.value;
                    });
                }
            } catch (e) {
                console.error("Align Element: Invalid custom attributes string", e);
            }
        }

        const finalProps = { ...blockProps, ...extraProps };
        if (dataEditId && !isAllowedTag) {
            const uniqueCssClass = dataEditId.replace('edit-', 'css-');
            finalProps.className = finalProps.className ? `${uniqueCssClass} ${finalProps.className}` : uniqueCssClass;
        }

        const parentEditId = props.context ? props.context['align/parentEditId'] : '';
        if (parentEditId) {
            const parentGroupClass = parentEditId.replace('edit-', 'css-group-');
            finalProps.className = finalProps.className ? `${parentGroupClass} ${finalProps.className}` : parentGroupClass;
        }
        if (className && (!finalProps.className || !finalProps.className.includes(className))) {
            finalProps.className = finalProps.className ? `${finalProps.className} ${className}` : className;
        }

        // Tab/filter classes resolver from tab-effects.js
        if (window.AlignElementTabEffects) {
            window.AlignElementTabEffects.resolveTabAndFilterClasses(clientId, attributes, finalProps);
        }

        if (isContainer) {
            let containerStyle = {
                ...finalProps.style
            };
            
            if (tag === 'li') {
                containerStyle.listStyleType = 'initial';
            }

            if (attributes.className && typeof attributes.className === 'string' && attributes.className.includes('splide__list')) {
                containerStyle.flexWrap = 'nowrap';
            }
            
            finalProps.style = containerStyle;
            
            const existingOnClick = finalProps.onClick;
            finalProps.onClick = function(e) {
                if (existingOnClick) {
                    existingOnClick(e);
                }
                const isClickOnEmptyArea = 
                    e.target === e.currentTarget || 
                    (e.target.classList && (
                        e.target.classList.contains('align-appender-wrapper') || 
                        e.target.classList.contains('block-editor-inner-blocks') ||
                        e.target.classList.contains('block-editor-block-list__layout')
                    ));
                
                if (isClickOnEmptyArea) {
                    e.stopPropagation();
                    wp.data.dispatch('core/block-editor').selectBlock(clientId);
                }
            };
        }

        if (tag === 'svg') {
            return el(Fragment, {}, [
                inspector,
                el('svg', finalProps)
            ]);
        }

        return el(Fragment, {}, [
            inspector,
            el(displayTag, finalProps, editorContent)
        ]);
    }

    window.AlignElementEditInternal = EditInternal;
})(window.wp);
