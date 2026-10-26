/**
 * Align Theme Site Editor (FSE) & Block Editor Align Builder Integration
 *
 * Adheres strictly to Align Coding Standards (< 500 lines, English logs/comments).
 *
 * @package Align
 */

(function() {
    'use strict';

    /**
     * Four-tier exact Post ID extraction.
     */
    function getExactPostId() {
        if (window.wp?.data?.select) {
            const ed = wp.data.select('core/editor');
            if (ed?.getCurrentPostId) {
                const id = ed.getCurrentPostId();
                if (id) return id;
            }
            const es = wp.data.select('core/edit-site');
            if (es?.getEditedPostId) {
                const id = es.getEditedPostId();
                if (id && !isNaN(Number(id))) return id;
            }
        }
        const hiddenId = document.getElementById('post_ID');
        if (hiddenId?.value) return hiddenId.value;

        const urlParams = new URLSearchParams(window.location.search);
        const fromUrl = urlParams.get('post') || urlParams.get('postId') || urlParams.get('p') || urlParams.get('post_id');
        if (fromUrl && !isNaN(Number(fromUrl))) return fromUrl;

        const bodyMatch = document.body?.className?.match(/\bpost-(\d+)\b/);
        return (bodyMatch && bodyMatch[1]) ? bodyMatch[1] : null;
    }

    /**
     * Four-tier exact Post Type extraction.
     */
    function getExactPostType() {
        if (window.wp?.data?.select) {
            const ed = wp.data.select('core/editor');
            if (ed?.getCurrentPostType) {
                const type = ed.getCurrentPostType();
                if (type) return type;
            }
            const es = wp.data.select('core/edit-site');
            if (es?.getEditedPostType) {
                const type = es.getEditedPostType();
                if (type) return type;
            }
        }
        const hiddenType = document.getElementById('post_type');
        if (hiddenType?.value) return hiddenType.value;

        const urlParams = new URLSearchParams(window.location.search);
        const pParam = urlParams.get('p');
        if (pParam) {
            const pMatch = decodeURIComponent(pParam).match(/^\/?(wp_template|wp_template_part)\b/);
            if (pMatch) return pMatch[1];
        }
        const fromUrl = urlParams.get('post_type') || urlParams.get('postType');
        if (fromUrl) return fromUrl;

        const bodyMatch = document.body?.className?.match(/\bpost-type-([a-zA-Z0-9_-]+)\b/);
        return (bodyMatch && bodyMatch[1]) ? bodyMatch[1] : 'page';
    }

    /**
     * Helper to resolve the exact target (Page, Post, CPT, Template, or Template Part).
     * Always the entity being edited: a selected template-part block must not retarget the
     * embedded Builder (its snapshots would never match the Save Coordinator entity).
     * Template parts open in their own tab via the block toolbar "Edit ... in Align Builder" button.
     */
    function resolveBuilderTarget() {
        if (window.wp?.data?.select) {
            const editSite = wp.data.select('core/edit-site');
            if (editSite) {
                const editedType = editSite.getEditedPostType ? editSite.getEditedPostType() : null;
                const editedId = editSite.getEditedPostId ? editSite.getEditedPostId() : null;
                if (editedType === 'wp_template' && editedId) {
                    const tplId = String(editedId).startsWith('tpl_') ? editedId : 'tpl_' + editedId;
                    return { type: 'template', param: '&tp_id=' + encodeURIComponent(tplId), label: 'Template (' + editedId + ')' };
                }
                if (editedType === 'wp_template_part' && editedId) {
                    const tpId = String(editedId).startsWith('tp_') ? editedId : 'tp_' + editedId;
                    return { type: 'template_part', param: '&tp_id=' + encodeURIComponent(tpId), label: 'Template Part (' + editedId + ')' };
                }
                if (editedId && !isNaN(Number(editedId))) {
                    return { type: editedType || 'page', param: '&post_id=' + encodeURIComponent(editedId), label: 'Post #' + editedId };
                }
            }
        }

        const exactId = getExactPostId();
        const exactType = getExactPostType();
        if (exactId && !isNaN(Number(exactId))) {
            return { type: exactType, param: '&post_id=' + encodeURIComponent(exactId), label: exactType + ' #' + exactId };
        }

        const urlParams = new URLSearchParams(window.location.search);
        const pParam = urlParams.get('p');
        if (pParam) {
            const pMatch = decodeURIComponent(pParam).match(/^\/?(wp_template|wp_template_part)\/(.+)$/);
            if (pMatch) {
                const type = pMatch[1];
                const rawId = pMatch[2].replace(/^t(p|pl)_/, '');
                const prefix = (type === 'wp_template_part') ? 'tp_' : 'tpl_';
                const isPart = (type === 'wp_template_part');
                return { type: isPart ? 'template_part' : 'template', param: '&tp_id=' + encodeURIComponent(prefix + rawId), label: (isPart ? 'Template Part (' : 'Template (') + rawId + ')' };
            }
        }

        const pId = urlParams.get('pId');
        const postTypeParam = urlParams.get('postType') || urlParams.get('post_type');
        const postIdParam = urlParams.get('postId') || urlParams.get('post');
        const pathParam = urlParams.get('path');

        if (pId) {
            let tpId = pId;
            if (!tpId.startsWith('tp_') && !tpId.startsWith('tpl_')) {
                tpId = (postTypeParam === 'wp_template_part') ? 'tp_' + pId : 'tpl_' + pId;
            }
            return { type: (postTypeParam === 'wp_template_part' || tpId.startsWith('tp_')) ? 'template_part' : 'template', param: '&tp_id=' + encodeURIComponent(tpId), label: '' + pId };
        }

        if (postTypeParam === 'wp_template' && postIdParam) {
            return { type: 'template', param: '&tp_id=' + encodeURIComponent(String(postIdParam).startsWith('tpl_') ? postIdParam : 'tpl_' + postIdParam), label: 'Template (' + postIdParam + ')' };
        }
        if (postTypeParam === 'wp_template_part' && postIdParam) {
            return { type: 'template_part', param: '&tp_id=' + encodeURIComponent(String(postIdParam).startsWith('tp_') ? postIdParam : 'tp_' + postIdParam), label: 'Template Part (' + postIdParam + ')' };
        }
        if (postIdParam) {
            if (!isNaN(Number(postIdParam))) return { type: 'page', param: '&post_id=' + encodeURIComponent(postIdParam), label: 'Page #' + postIdParam };
            if (String(postIdParam).includes('//')) return { type: 'template', param: '&tp_id=' + encodeURIComponent('tpl_' + postIdParam), label: 'Template (' + postIdParam + ')' };
        }
        if (pathParam?.includes('/template-parts/')) {
            const slug = pathParam.split('/template-parts/')[1].split('/')[0];
            return { type: 'template_part', param: '&tp_id=' + encodeURIComponent('tp_align-child//' + slug), label: 'Template Part (' + slug + ')' };
        }
        if (pathParam?.includes('/templates/')) {
            const slug = pathParam.split('/templates/')[1].split('/')[0];
            return { type: 'template', param: '&tp_id=' + encodeURIComponent('tpl_align-child//' + slug), label: 'Template (' + slug + ')' };
        }

        return { type: 'generic', param: '', label: 'Align Builder' };
    }

    // 1. Gutenberg BlockEdit Filter for Template Parts (Header, Footer, Sidebar...)
    if (window.wp?.hooks && wp.element && wp.blockEditor) {
        const { createHigherOrderComponent } = wp.compose;
        const { Fragment, createElement } = wp.element;
        const { BlockControls, InspectorControls } = wp.blockEditor;
        const { ToolbarButton, PanelBody, Button } = wp.components;

        const withVvvebTemplatePartEdit = createHigherOrderComponent((BlockEdit) => {
            return (props) => {
                if (props.name === 'core/template-part') {
                    const slug = props.attributes?.slug || 'header';
                    const theme = props.attributes?.theme || 'align-child';
                    const tpId = 'tp_' + theme + '//' + slug;

                    const handleOpenVvveb = () => {
                        const baseUrl = window.AlignVvvebConfig?.builderUrl || (window.location.origin + '/wp-admin/admin.php?page=align-builder');
                        const builderUrl = baseUrl + '&tp_id=' + encodeURIComponent(tpId) + '&embedded=1';
                        window.open(builderUrl, '_blank') || (window.location.href = builderUrl);
                    };

                    return createElement(Fragment, {},
                        createElement(BlockEdit, props),
                        createElement(BlockControls, {}, createElement(ToolbarButton, { icon: 'layout', label: 'Edit ' + slug + ' in Align Builder', onClick: handleOpenVvveb })),
                        createElement(InspectorControls, {}, createElement(PanelBody, { title: 'Align Builder', initialOpen: true }, createElement(Button, { variant: 'primary', style: { width: '100%', justifyContent: 'center', backgroundColor: '#6366f1', borderColor: '#4f46e5' }, onClick: handleOpenVvveb }, 'Edit ' + slug.toUpperCase() + ' in Align Builder')))
                    );
                }
                return createElement(BlockEdit, props);
            };
        }, 'withVvvebTemplatePartEdit');

        wp.hooks.addFilter('editor.BlockEdit', 'align/vvveb-template-part-edit', withVvvebTemplatePartEdit);
    }

    /**
     * Delegate mode resolution to AlignEditorModeSwitch.
     */
    function getSavedEditorModeMeta() {
        return window.AlignEditorModeSwitch?.getSavedEditorModeMeta?.() ?? null;
    }

    function getActiveEditorMode(postType) {
        const type = postType || getExactPostType();
        if (window.AlignEditorModeSwitch?.getActiveEditorMode) {
            return window.AlignEditorModeSwitch.getActiveEditorMode(type);
        }
        return (type === 'page') ? 'builder' : 'gutenberg';
    }

    function setActiveEditorMode(postType, mode) {
        if (window.AlignEditorModeSwitch?.setActiveEditorMode) {
            return window.AlignEditorModeSwitch.setActiveEditorMode(postType, mode);
        }
        return false;
    }

    /**
     * Embed Align Visual Builder directly into Gutenberg Canvas (100% Height, Zero Scroll).
     */
    function embedAlignBuilderCanvas() {
        if (document.getElementById('align-builder-embedded-container')) return;

        const skeletonContent = document.querySelector(
            '.interface-interface-skeleton__content, .edit-post-layout__content, .block-editor-writing-flow, .edit-post-visual-editor'
        );
        if (!skeletonContent) return;

        const target = resolveBuilderTarget();
        if (!target?.param) return;

        const baseUrl = window.AlignVvvebConfig?.builderUrl || (window.location.origin + '/wp-admin/admin.php?page=align-builder');
        const sep = (baseUrl.includes('?') || target.param.includes('?')) ? '&' : '?';
        let embeddedUrl = baseUrl + target.param + sep + 'embedded=1';
        const urlParams = new URLSearchParams(window.location.search);
        const langParam = urlParams.get('lang');
        if (langParam && !embeddedUrl.includes('lang=')) {
            embeddedUrl += '&lang=' + encodeURIComponent(langParam);
        }

        console.log('[Align Gutenberg Bridge] [INFO] Initializing Embedded Align Builder Canvas:', embeddedUrl);

        // Hide Gutenberg visual writing flow and native footer breadcrumbs
        const nativeEditors = document.querySelectorAll(
            '.edit-post-visual-editor, .block-editor-writing-flow, .editor-visual-editor, .edit-post-meta-boxes-main, .interface-interface-skeleton__footer, .edit-post-layout__footer, .block-editor-block-breadcrumb, .edit-site-visual-editor, .edit-site-canvas-container'
        );
        nativeEditors.forEach(function(el) {
            if (el && el.id !== 'align-builder-embedded-container' && !el.contains(document.getElementById('align-builder-embedded-container'))) {
                el.style.setProperty('display', 'none', 'important');
            }
        });

        const container = document.createElement('div');
        container.id = 'align-builder-embedded-container';

        const iframe = document.createElement('iframe');
        iframe.id = 'align-builder-embedded-frame';
        iframe.src = embeddedUrl;
        iframe.setAttribute('allow', 'clipboard-read; clipboard-write');

        container.appendChild(iframe);
        skeletonContent.prepend(container);
        skeletonContent.scrollTop = 0;
        document.body.classList.add('is-align-builder-embedded');
    }

    /**
     * Unmount Align Builder Canvas and restore Gutenberg visual editor.
     */
    function unmountAlignBuilderCanvas() {
        const container = document.getElementById('align-builder-embedded-container');
        if (container) {
            container.remove();
            console.log('[Align Gutenberg Bridge] [INFO] Unmounted Align Builder Canvas');
        }
        document.body.classList.remove('is-align-builder-embedded');

        const nativeEditors = document.querySelectorAll(
            '.edit-post-visual-editor, .block-editor-writing-flow, .editor-visual-editor, .edit-post-meta-boxes-main, .interface-interface-skeleton__footer, .edit-post-layout__footer, .block-editor-block-breadcrumb, .edit-site-visual-editor, .edit-site-canvas-container'
        );
        nativeEditors.forEach(function(el) {
            if (el) el.style.removeProperty('display');
        });
    }

    /**
     * Synchronize editor mode based on post type and meta settings.
     */
    function syncEditorMode() {
        if (window.AlignEditorModeSwitch?.isSavingInProgress?.()) return;

        const postType = getExactPostType();
        const supportedTypes = window.AlignEditorModeSwitch?.getSupportedPostTypes?.() || ['post', 'page', 'wp_template', 'wp_template_part'];

        if (supportedTypes.includes(postType)) {
            const mode = getActiveEditorMode(postType);
            window.AlignEditorModeSwitch?.mountHeaderSwitcherButton?.(mode);

            if (mode === 'builder') {
                embedAlignBuilderCanvas();
            } else if (document.getElementById('align-builder-embedded-container')) {
                unmountAlignBuilderCanvas();
            }
        } else {
            const switcher = document.getElementById('align-mode-switcher-wrap');
            if (switcher) switcher.remove();
            if (document.getElementById('align-builder-embedded-container')) {
                unmountAlignBuilderCanvas();
            }
        }
    }

    // Listen for custom iframe control events and navigation
    window.addEventListener('message', function(event) {
        if (!event.data || event.origin !== window.location.origin) return;

        // Switch to Gutenberg message sent from inside iframe
        if (event.data.type === 'ALIGN_SWITCH_TO_GUTENBERG') {
            console.log('[Align Gutenberg Bridge] [INFO] Received ALIGN_SWITCH_TO_GUTENBERG from iframe');
            window.AlignEditorModeSwitch?.handleSwitchClick?.();
        }

        // Handle redirect request sent from inside iframe to keep parent Gutenberg shell in sync
        if (event.data.type === 'ALIGN_NAVIGATE' && event.data.url) {
            try {
                const targetUrl = new URL(event.data.url, window.location.href);
                if (targetUrl.href === window.location.href ||
                    (targetUrl.pathname === window.location.pathname && targetUrl.search === window.location.search)) {
                    console.log('[Align Gutenberg Bridge] [NAVIGATE] Already at target destination URL, ignoring redirect:', event.data.url);
                    return;
                }
            } catch (e) {}
            console.log('[Align Gutenberg Bridge] [NAVIGATE] Redirecting parent shell to:', event.data.url);
            window.location.href = event.data.url;
        }
    });

    window.AlignSiteEditorIntegration = {
        getExactPostId: getExactPostId,
        getExactPostType: getExactPostType,
        resolveBuilderTarget: resolveBuilderTarget,
        getActiveEditorMode: getActiveEditorMode,
        getSavedEditorModeMeta: getSavedEditorModeMeta,
        setActiveEditorMode: setActiveEditorMode,
        embedAlignBuilderCanvas: embedAlignBuilderCanvas,
        unmountAlignBuilderCanvas: unmountAlignBuilderCanvas
    };

    // Run watcher for dynamic DOM elements and editor mode syncing
    setInterval(function() {
        syncEditorMode();
    }, 500);
})();
