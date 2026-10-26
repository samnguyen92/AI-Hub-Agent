(function() {
    const { __ } = wp.i18n;
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, ToggleControl, TextControl, Button } = wp.components;
    const { createElement: el, Fragment, RawHTML } = wp.element;
    const { select } = wp.data;

    // Align Logo SVG component
    const AlignLogo = function(props) {
        return el('svg', {
            viewBox: '0 0 59 41',
            width: props.width || '38',
            height: props.height || '26',
            fill: 'currentColor',
            className: props.className || 'align-logo-svg'
        }, [
            el('g', { key: 'g' }, [
                el('path', { key: 'p1', d: "M22.6614 0.387405C22.6614 0.58818 22.6614 0.772223 22.6614 0.96045C22.6614 8.74048 22.6654 16.5205 22.6533 24.3005C22.6533 24.6812 22.7668 24.8736 23.0951 25.0283C23.7032 25.3128 24.2991 25.6349 24.8787 25.9737C25.0166 26.0531 25.1422 26.2623 25.1625 26.4296C25.2071 26.7475 25.1747 27.0737 25.1747 27.4502C24.9882 27.4502 24.8382 27.4502 24.6842 27.4502C23.2573 27.4502 21.8263 27.4 20.3994 27.4627C19.0455 27.5255 17.6105 26.576 17.6024 24.6017C17.5983 23.4221 17.6024 22.2426 17.6024 21.0672C17.6024 15.1569 17.6024 9.25078 17.6024 3.34047C17.6024 2.27385 17.8983 1.35782 18.8063 0.763858C19.086 0.579814 19.4306 0.416684 19.7508 0.399953C20.7075 0.358125 21.6601 0.387405 22.6614 0.387405Z" }),
                el('path', { key: 'p2', d: "M8.72852 12.8442C10.3905 12.8442 12.0363 12.8442 13.7145 12.8442C13.7145 17.7298 13.7145 22.6069 13.7145 27.505C12.0485 27.505 10.4027 27.505 8.72852 27.505C8.72852 22.6111 8.72852 17.7423 8.72852 12.8442Z" }),
                el('path', { key: 'p3', d: "M26.5117 27.5464C26.5117 22.6483 26.5117 17.7962 26.5117 12.9023C28.1616 12.9023 29.8073 12.9023 31.4896 12.9023C31.4896 17.7669 31.4896 22.6483 31.4896 27.5464C29.8357 27.5464 28.198 27.5464 26.5117 27.5464Z" }),
                el('path', { key: 'p4', d: "M4.86967 27.4414C3.81166 27.4414 2.76581 27.5376 1.74429 27.4163C0.698439 27.2909 -0.136619 26.1531 -0.140673 25.0447C-0.152834 21.7737 -0.156887 18.5028 -0.140673 15.2318C-0.132565 14.0397 0.90923 12.8769 2.05642 12.789C2.83878 12.7305 3.62519 12.7681 4.41161 12.764C4.55349 12.764 4.69536 12.764 4.86562 12.764C4.86967 17.6913 4.86967 22.5852 4.86967 27.4414Z" }),
                el('path', { key: 'p5', d: "M40.3639 21.2889C40.372 21.4144 40.3841 21.5399 40.3841 21.6653C40.3841 26.8688 40.376 32.068 40.3882 37.2714C40.3922 38.8525 39.1802 40.1408 37.6925 40.0362C36.9263 39.9819 36.1561 40.0279 35.3535 40.0279C35.3535 33.7453 35.3535 27.5296 35.3535 21.2931L35.3373 19.6701C35.3373 19.3146 35.3373 18.9674 35.3373 18.6244C35.3373 17.7126 35.3251 16.7965 35.3454 15.8847C35.3495 15.6212 35.2603 15.4915 35.0373 15.3869C34.405 15.0858 33.7847 14.7553 33.1524 14.4542C32.901 14.3371 32.7754 14.1865 32.8038 13.8853C32.8321 13.5967 32.8119 13.3039 32.8119 12.9735C32.9902 12.9651 33.1281 12.9525 33.2659 12.9525C34.7374 12.9525 36.2088 12.9484 37.6763 12.9525C39.3099 12.9567 40.3801 14.0652 40.3801 15.7467C40.3801 17.0391 40.3801 18.3358 40.3801 19.6701L40.3639 21.2889Z" }),
                el('path', { key: 'p6', d: "M49.1762 21.3413C49.1762 23.4536 49.1762 25.5408 49.1762 27.6573C47.5142 27.6573 45.8643 27.6573 44.178 27.6573C44.178 25.5659 44.178 23.4704 44.178 21.3413L44.1699 19.7058C44.1699 17.4806 44.1699 15.2637 44.1699 13.0259C45.836 13.0259 47.4858 13.0259 49.1681 13.0259C49.1681 15.2762 49.1681 17.4806 49.1681 19.7058L49.1762 21.3413Z" }),
                el('path', { key: 'p7', d: "M26.4997 35.2553C26.4997 34.7116 26.4916 34.1678 26.5038 33.624C26.5159 33.005 26.897 32.6076 27.501 32.5993C28.4982 32.5867 29.4954 32.5867 30.4966 32.5993C31.1047 32.6076 31.4979 32.9841 31.5141 33.6115C31.5384 34.699 31.5384 35.7907 31.5141 36.8783C31.4979 37.4973 31.0966 37.8696 30.4845 37.878C29.4994 37.8905 28.5184 37.8905 27.5334 37.878C26.8564 37.8696 26.5119 37.4973 26.5078 36.803C26.4957 36.2885 26.4997 35.774 26.4997 35.2553Z" }),
                el('path', { key: 'p8', d: "M26.4998 4.98421C26.4998 4.453 26.4958 3.92596 26.4998 3.39475C26.508 2.76732 26.8728 2.35323 27.4687 2.34486C28.4821 2.33231 29.4915 2.33231 30.5049 2.34486C31.0927 2.35323 31.4899 2.7255 31.5061 3.32782C31.5345 4.4279 31.5345 5.53216 31.5061 6.63643C31.4899 7.25548 31.1129 7.59011 30.5049 7.59847C29.4915 7.60684 28.4821 7.61102 27.4687 7.59847C26.8363 7.59011 26.508 7.23457 26.4998 6.57368C26.4958 6.04247 26.4998 5.51125 26.4998 4.98421Z" }),
                el('path', { key: 'p9', d: "M52.7676 13.1133H55.5395C57.0703 13.1133 58.3113 14.3543 58.3113 15.8852V24.8937C58.3113 26.4246 57.0703 27.6656 55.5395 27.6656H52.7676V13.1133Z" })
            ])
        ]);
    };

    /**
     * Helper to resolve the exact target (Page, Template, or Template Part)
     * from all Gutenberg context sources, stores, and URL parameters.
     */
    function resolveBuilderTarget(props) {
        // 1. Check parent hierarchy for core/template-part block
        if (props && props.clientId && select('core/block-editor')) {
            const blockEditor = select('core/block-editor');
            if (blockEditor && blockEditor.getBlockParents && blockEditor.getBlock) {
                const parentIds = blockEditor.getBlockParents(props.clientId);
                if (Array.isArray(parentIds) && parentIds.length > 0) {
                    for (let i = parentIds.length - 1; i >= 0; i--) {
                        const parentBlock = blockEditor.getBlock(parentIds[i]);
                        if (parentBlock && parentBlock.name === 'core/template-part') {
                            const slug = (parentBlock.attributes && parentBlock.attributes.slug) || '';
                            const theme = (parentBlock.attributes && parentBlock.attributes.theme) || 'align-child';
                            if (slug) {
                                const tpId = 'tp_' + theme + '//' + slug;
                                return {
                                    type: 'template_part',
                                    param: '&tp_id=' + encodeURIComponent(tpId),
                                    label: 'Template Part (' + slug + ')'
                                };
                            }
                        }
                    }
                }
            }
        }

        // 2. Check props.context (populated via usesContext in block.json)
        if (props && props.context) {
            const ctx = props.context;
            if (ctx['templatePart/slug']) {
                const slug = ctx['templatePart/slug'];
                const theme = ctx['templatePart/theme'] || 'align-child';
                const tpId = 'tp_' + theme + '//' + slug;
                return {
                    type: 'template_part',
                    param: '&tp_id=' + encodeURIComponent(tpId),
                    label: 'Template Part (' + slug + ')'
                };
            }
            if (ctx.postType === 'wp_template' && ctx.postId) {
                const tplId = String(ctx.postId).startsWith('tpl_') ? ctx.postId : 'tpl_' + ctx.postId;
                return {
                    type: 'template',
                    param: '&tp_id=' + encodeURIComponent(tplId),
                    label: 'Template (' + ctx.postId + ')'
                };
            }
            if (ctx.postType === 'wp_template_part' && ctx.postId) {
                const tpId = String(ctx.postId).startsWith('tp_') ? ctx.postId : 'tp_' + ctx.postId;
                return {
                    type: 'template_part',
                    param: '&tp_id=' + encodeURIComponent(tpId),
                    label: 'Template Part (' + ctx.postId + ')'
                };
            }
            if (ctx.postId && !isNaN(Number(ctx.postId))) {
                return {
                    type: 'page',
                    param: '&post_id=' + encodeURIComponent(ctx.postId),
                    label: 'Page #' + ctx.postId
                };
            }
        }

        // 3. Check core/edit-site store (Full Site Editor)
        if (select('core/edit-site')) {
            const editSite = select('core/edit-site');
            const editedType = editSite.getEditedPostType ? editSite.getEditedPostType() : null;
            const editedId = editSite.getEditedPostId ? editSite.getEditedPostId() : null;

            if (editedType === 'wp_template' && editedId) {
                const tplId = String(editedId).startsWith('tpl_') ? editedId : 'tpl_' + editedId;
                return {
                    type: 'template',
                    param: '&tp_id=' + encodeURIComponent(tplId),
                    label: 'Template (' + editedId + ')'
                };
            }
            if (editedType === 'wp_template_part' && editedId) {
                const tpId = String(editedId).startsWith('tp_') ? editedId : 'tp_' + editedId;
                return {
                    type: 'template_part',
                    param: '&tp_id=' + encodeURIComponent(tpId),
                    label: 'Template Part (' + editedId + ')'
                };
            }
            if ((editedType === 'page' || editedType === 'post' || editedType === 'wp_block') && editedId && !isNaN(Number(editedId))) {
                return {
                    type: 'page',
                    param: '&post_id=' + encodeURIComponent(editedId),
                    label: 'Page #' + editedId
                };
            }
        }

        // 4. Check core/editor store (Standard Post Editor / Unified Gutenberg)
        if (select('core/editor')) {
            const editor = select('core/editor');
            const currentType = editor.getCurrentPostType ? editor.getCurrentPostType() : null;
            const currentId = editor.getCurrentPostId ? editor.getCurrentPostId() : null;

            if (currentType === 'wp_template' && currentId) {
                const tplId = String(currentId).startsWith('tpl_') ? currentId : 'tpl_' + currentId;
                return {
                    type: 'template',
                    param: '&tp_id=' + encodeURIComponent(tplId),
                    label: 'Template (' + currentId + ')'
                };
            }
            if (currentType === 'wp_template_part' && currentId) {
                const tpId = String(currentId).startsWith('tp_') ? currentId : 'tp_' + currentId;
                return {
                    type: 'template_part',
                    param: '&tp_id=' + encodeURIComponent(tpId),
                    label: 'Template Part (' + currentId + ')'
                };
            }
            if (currentId) {
                if (!isNaN(Number(currentId))) {
                    return {
                        type: 'page',
                        param: '&post_id=' + encodeURIComponent(currentId),
                        label: 'Page #' + currentId
                    };
                } else if (String(currentId).includes('//')) {
                    const tplId = String(currentId).startsWith('tpl_') || String(currentId).startsWith('tp_')
                        ? currentId
                        : 'tpl_' + currentId;
                    return {
                        type: 'template',
                        param: '&tp_id=' + encodeURIComponent(tplId),
                        label: 'Template (' + currentId + ')'
                    };
                }
            }
        }

        // 5. Fallback: Parse URL search and hash parameters
        const urlParams = new URLSearchParams(window.location.search);
        const pId = urlParams.get('pId');
        const postTypeParam = urlParams.get('postType');
        const postIdParam = urlParams.get('postId') || urlParams.get('post');
        const pathParam = urlParams.get('path');

        if (pId) {
            let tpId = pId;
            if (!tpId.startsWith('tp_') && !tpId.startsWith('tpl_')) {
                tpId = (postTypeParam === 'wp_template_part') ? 'tp_' + pId : 'tpl_' + pId;
            }
            return {
                type: (postTypeParam === 'wp_template_part' || tpId.startsWith('tp_')) ? 'template_part' : 'template',
                param: '&tp_id=' + encodeURIComponent(tpId),
                label: '' + pId
            };
        }

        if (postTypeParam === 'wp_template' && postIdParam) {
            const tplId = String(postIdParam).startsWith('tpl_') ? postIdParam : 'tpl_' + postIdParam;
            return {
                type: 'template',
                param: '&tp_id=' + encodeURIComponent(tplId),
                label: 'Template (' + postIdParam + ')'
            };
        }

        if (postTypeParam === 'wp_template_part' && postIdParam) {
            const tpId = String(postIdParam).startsWith('tp_') ? postIdParam : 'tp_' + postIdParam;
            return {
                type: 'template_part',
                param: '&tp_id=' + encodeURIComponent(tpId),
                label: 'Template Part (' + postIdParam + ')'
            };
        }

        if (postIdParam) {
            if (!isNaN(Number(postIdParam))) {
                return {
                    type: 'page',
                    param: '&post_id=' + encodeURIComponent(postIdParam),
                    label: 'Page #' + postIdParam
                };
            } else if (String(postIdParam).includes('//')) {
                return {
                    type: 'template',
                    param: '&tp_id=' + encodeURIComponent('tpl_' + postIdParam),
                    label: 'Template (' + postIdParam + ')'
                };
            }
        }

        if (pathParam) {
            if (pathParam.includes('/template-parts/')) {
                const slug = pathParam.split('/template-parts/')[1].split('/')[0];
                return {
                    type: 'template_part',
                    param: '&tp_id=' + encodeURIComponent('tp_align-child//' + slug),
                    label: 'Template Part (' + slug + ')'
                };
            }
            if (pathParam.includes('/templates/')) {
                const slug = pathParam.split('/templates/')[1].split('/')[0];
                return {
                    type: 'template',
                    param: '&tp_id=' + encodeURIComponent('tpl_align-child//' + slug),
                    label: 'Template (' + slug + ')'
                };
            }
        }

        return {
            type: 'generic',
            param: '',
            label: 'Align Builder'
        };
    }

    function getBuilderUrl(props) {
        const baseUrl = (window.AlignVvvebConfig && window.AlignVvvebConfig.builderUrl)
            ? window.AlignVvvebConfig.builderUrl
            : (window.location.origin ? (window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '/admin.php?page=align-builder')) : 'admin.php?page=align-builder');

        const target = resolveBuilderTarget(props);
        return baseUrl + target.param;
    }

    registerBlockType('align/custom-html', {
        title: __('Align Builder Content', 'align'),
        icon: AlignLogo,
        category: 'align-category',
        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { blockId, enableShortcode } = attributes;

            const blockProps = useBlockProps({
                className: 'align-builder-block-placeholder-wrapper'
            });

            const targetInfo = resolveBuilderTarget(props);
            const builderUrl = getBuilderUrl(props);

            return el(Fragment, {}, [
                el(InspectorControls, { key: 'inspector' },
                    el(PanelBody, { title: __('Align Builder Settings', 'align'), initialOpen: true }, [
                        el(ToggleControl, {
                            key: 'enableShortcode',
                            label: __('Execute Shortcodes', 'align'),
                            help: __('Process WordPress shortcodes inside this content block.', 'align'),
                            checked: enableShortcode !== false,
                            onChange: function(val) {
                                setAttributes({ enableShortcode: val });
                            }
                        }),
                        el(TextControl, {
                            key: 'blockId',
                            label: __('Block HTML ID', 'align'),
                            help: __('Optional unique DOM ID for styling or anchoring.', 'align'),
                            value: blockId || '',
                            onChange: function(val) {
                                setAttributes({ blockId: val });
                            }
                        }),
                        el('div', { key: 'builder-btn', style: { marginTop: '16px' } }, [
                            el(Button, {
                                variant: 'primary',
                                href: builderUrl,
                                target: '_blank',
                                rel: 'noopener noreferrer',
                                style: { width: '100%', justifyContent: 'center' }
                            }, __('Open Align Builder', 'align'))
                        ])
                    ])
                ),
                el('div', blockProps,
                    el('a', {
                        href: builderUrl,
                        target: '_blank',
                        rel: 'noopener noreferrer',
                        className: 'align-builder-block-card-link',
                        title: __('Open in Align Builder: ', 'align') + targetInfo.label
                    }, [
                        el('div', { className: 'align-builder-block-card-icon' },
                            el(AlignLogo, { width: '42', height: '29' })
                        ),
                        el('span', { className: 'align-builder-block-card-title' }, 
                            __('Edit with Align Builder', 'align')
                        )
                    ])
                )
            ]);
        },
        save: function(props) {
            const { attributes } = props || {};
            return el(RawHTML, null, (attributes && attributes.content) ? attributes.content : '');
        },
        deprecated: [
            {
                attributes: {
                    blockId: {
                        type: 'string',
                        default: ''
                    },
                    enableShortcode: {
                        type: 'boolean',
                        default: true
                    }
                },
                save: function() {
                    return null;
                }
            }
        ]
    });
})();
