/**
 * Align Builder - Dynamic Block Converter Client Module
 *
 * Captures selected element's clean DOM and scoped CSS, stores payload into
 * sessionStorage, and redirects/opens the Dynamic Block Studio workspace.
 *
 * @package AlignBuilder
 */

window.AlignBuilderModules = window.AlignBuilderModules || {};

window.AlignBuilderModules.DynamicBlockConverter = (function() {
    'use strict';

    /**
     * Storage key used to transfer DOM payload to Align Studio
     */
    const STORAGE_KEY = 'align_studio_payload';

    /**
     * Initialize the button click and tooltip behaviors
     */
    function init() {
        const btn = document.getElementById('convert-dynamic-btn');
        if (!btn) {
            return;
        }

        // Initialize tooltip with centralized module or fallback
        if (window.AlignBuilderModules?.WpToolbarTooltips?.initElement) {
            window.AlignBuilderModules.WpToolbarTooltips.initElement(btn);
        } else if (window.bootstrap && window.bootstrap.Tooltip) {
            try {
                const initTitle = btn.getAttribute('title') || btn.getAttribute('data-bs-original-title');
                if (initTitle) {
                    btn.setAttribute('data-bs-original-title', initTitle);
                }
                btn.removeAttribute('title');
                new window.bootstrap.Tooltip(btn, {
                    trigger: 'hover',
                    placement: 'bottom',
                    container: 'body',
                    customClass: 'align-toolbar-tooltip',
                    animation: true,
                    delay: { show: 100, hide: 50 }
                });
            } catch (err) {
                console.warn('[Align DynamicStudio] Failed to initialize bootstrap tooltip:', err);
            }
        }

        updateButtonState();
        window.addEventListener('Vvveb.Builder.selectNode', updateButtonState);

        btn.addEventListener('click', handleConvertClick);
        console.log('[Align DynamicStudio] DynamicBlockConverter initialized successfully');
    }

    function updateButtonTooltip(title) {
        const btn = document.getElementById('convert-dynamic-btn');
        if (!btn) return;
        if (window.AlignBuilderModules?.WpToolbarTooltips?.updateTitle) {
            window.AlignBuilderModules.WpToolbarTooltips.updateTitle(btn, title);
            return;
        }
        btn.setAttribute('data-bs-original-title', title);
        btn.removeAttribute('title');
        const tip = window.bootstrap?.Tooltip?.getInstance?.(btn);
        if (tip && typeof tip.setContent === 'function') {
            tip.setContent({ '.tooltip-inner': title });
        } else if (window.bootstrap && window.bootstrap.Tooltip) {
            try {
                new window.bootstrap.Tooltip(btn, {
                    trigger: 'hover',
                    placement: 'bottom',
                    container: 'body',
                    customClass: 'align-toolbar-tooltip',
                    animation: true,
                    delay: { show: 100, hide: 50 }
                });
            } catch (err) {
                console.warn('[Align DynamicStudio] Failed to initialize fallback tooltip:', err);
            }
        }
    }

    function updateButtonState() {
        const btn = document.getElementById('convert-dynamic-btn');
        if (!btn) return;

        const isCanvasDynamic = !!(window.VvvebWpConfig && window.VvvebWpConfig.isDynamicBlock);
        if (isCanvasDynamic) {
            updateButtonTooltip('Edit dynamic block in Align Studio');
            btn.classList.add('is-dynamic-edit');
            return;
        }

        const node = (window.Vvveb && window.Vvveb.Builder) ? window.Vvveb.Builder.selectedEl : null;
        let reuseId = null;
        if (node && window.AlignBuilderModules?.WpReuseBlockOverwrite?.getReuseBlockId) {
            reuseId = window.AlignBuilderModules.WpReuseBlockOverwrite.getReuseBlockId(node);
        }

        if (reuseId) {
            btn.dataset.targetBlockId = reuseId;
            updateButtonTooltip('Edit Reusable Block #' + reuseId + ' in Align Studio');
            btn.classList.add('is-dynamic-edit');
        } else {
            delete btn.dataset.targetBlockId;
            updateButtonTooltip('Convert to dynamic block');
            btn.classList.remove('is-dynamic-edit');
        }
    }

    /**
     * Handle click on the Convert to Dynamic Block button
     *
     * @param {Event} event Click event
     */
    function handleConvertClick(event) {
        event.preventDefault();

        const btn = event.currentTarget || document.getElementById('convert-dynamic-btn');
        const isCanvasDynamic = !!(window.VvvebWpConfig && window.VvvebWpConfig.isDynamicBlock);
        const currentPostId = (window.VvvebWpConfig && window.VvvebWpConfig.activePostId) ? window.VvvebWpConfig.activePostId : 0;
        const targetBlockId = isCanvasDynamic ? currentPostId : (btn.dataset.targetBlockId ? parseInt(btn.dataset.targetBlockId, 10) : 0);
        const isEditMode = targetBlockId > 0;

        const selectedNode = (window.Vvveb && window.Vvveb.Builder) ? window.Vvveb.Builder.selectedEl : null;
        if (!selectedNode && !isEditMode) {
            console.warn('[Align DynamicStudio] Cannot convert: No element selected');
            if (typeof window.displayToast === 'function') {
                window.displayToast('warning', 'Notice', 'Please select an element first');
            }
            return false;
        }

        console.log('[Align DynamicStudio] Converting element/block:', isEditMode ? ('Block #' + targetBlockId) : (selectedNode ? selectedNode.tagName : 'Canvas'));

        // 1. Extract clean DOM preserving root element and stripping builder noise
        let cleanHtml = '';
        if (selectedNode) {
            try {
                const clone = selectedNode.cloneNode(true);
                const cleanElement = function(el) {
                    Array.from(el.attributes).forEach(function(attr) {
                        if (attr.name.startsWith('data-vvveb-') || attr.name.startsWith('bis_') || attr.name === 'data-ast-id' || attr.name === 'contenteditable') {
                            el.removeAttribute(attr.name);
                        }
                    });
                    el.classList.remove('selected', 'active', 'vvveb-selected', 'vvveb-highlight');
                    if (el.getAttribute('class') === '') el.removeAttribute('class');
                };
                cleanElement(clone);
                clone.querySelectorAll('*').forEach(cleanElement);
                // Resolve placeholder and lazy-loaded image sources
                clone.querySelectorAll('img').forEach(function(img) {
                    const dataSrc = img.getAttribute('data-src') || img.getAttribute('data-lazy-src') || img.getAttribute('data-original');
                    let src = img.getAttribute('src') || '';
                    if (dataSrc && (!src || src.startsWith('data:') || src === '[align_post_hero_image]')) {
                        img.setAttribute('src', dataSrc);
                    } else if (src === '[align_post_hero_image]' || !src) {
                        img.setAttribute('src', '/wp-content/uploads/2026/08/home-3.webp');
                    }
                });

                cleanHtml = clone.outerHTML || '';
            } catch (err) {
                console.error('[Align DynamicStudio] Error cleaning DOM:', err);
                cleanHtml = selectedNode.outerHTML || '';
            }
        }

        // 2. Extract stylesheets, scoped CSS & JS from Canvas
        const frameDoc = window.FrameDocument || (window.Vvveb && window.Vvveb.Builder && window.Vvveb.Builder.iframe ? window.Vvveb.Builder.iframe.contentDocument : null);
        let activeStylesheets = [];
        if (frameDoc) {
            frameDoc.querySelectorAll('link[rel="stylesheet"]').forEach(function(l) {
                if (l.href && !l.href.includes('editor.css') && !l.href.includes('vvvebjs')) {
                    activeStylesheets.push(l.href);
                }
            });
        }

        let collectedRules = [];
        const seenRuleTexts = new Set();
        function addRule(cssText) {
            if (!cssText) return;
            const trimmed = cssText.trim();
            if (trimmed && !seenRuleTexts.has(trimmed)) {
                seenRuleTexts.add(trimmed);
                collectedRules.push(trimmed);
            }
        }

        function doesSelectorMatch(selectorText, rootEl) {
            if (!selectorText || typeof selectorText !== 'string' || !rootEl) return false;
            const parts = selectorText.split(',');
            for (let i = 0; i < parts.length; i++) {
                let s = parts[i].trim();
                if (!s || s.startsWith('.vvveb-') || s.startsWith('.align-builder-')) continue;
                const testSelector = s.replace(/::?[a-zA-Z0-9_-]+(\([^)]*\))?/g, '').trim();
                if (!testSelector || testSelector === '*' || testSelector === 'html' || testSelector === 'body') continue;
                try {
                    if (rootEl.matches(testSelector) || rootEl.querySelector(testSelector)) {
                        return true;
                    }
                } catch (e) {}
            }
            return false;
        }

        if (frameDoc && frameDoc.styleSheets) {
            Array.from(frameDoc.styleSheets).forEach(function(sheet) {
                try {
                    const rules = sheet.cssRules || sheet.rules;
                    if (!rules) return;
                    Array.from(rules).forEach(function(rule) {
                        try {
                            if (rule.selectorText) {
                                if (!selectedNode || doesSelectorMatch(rule.selectorText, selectedNode)) {
                                    addRule(rule.cssText);
                                }
                            } else if (rule.type === 4 || rule.conditionText) {
                                const innerRules = Array.from(rule.cssRules || []);
                                const matchedInner = innerRules.filter(r => !selectedNode || (r.selectorText && doesSelectorMatch(r.selectorText, selectedNode)));
                                if (matchedInner.length > 0) {
                                    addRule(`@media ${rule.conditionText || rule.media.mediaText} {\n  ${matchedInner.map(r => r.cssText).join('\n  ')}\n}`);
                                }
                            }
                        } catch (ruleErr) {}
                    });
                } catch (sheetErr) {}
            });
        }

        try {
            if (window.Vvveb && window.Vvveb.StyleManager && typeof window.Vvveb.StyleManager.getCss === 'function') {
                const smCss = window.Vvveb.StyleManager.getCss();
                if (smCss && smCss.trim()) addRule(smCss.trim());
            }
        } catch (smErr) {}

        const scopedCss = collectedRules.join('\n\n');

        let scopedJs = '';
        try {
            if (window.Vvveb && window.Vvveb.JsManager && typeof window.Vvveb.JsManager.getJs === 'function') {
                scopedJs = window.Vvveb.JsManager.getJs() || '';
            }
        } catch (jsErr) {}

        // 3. Prepare transfer payload
        const tagName = selectedNode ? (selectedNode.tagName ? selectedNode.tagName.toUpperCase() : 'DIV') : 'SECTION';
        const tagSlug = selectedNode ? (selectedNode.tagName ? selectedNode.tagName.toLowerCase() : 'block') : 'section';

        if (isEditMode) {
            const editPayload = {
                postId: targetBlockId,
                isEditMode: true,
                html: cleanHtml,
                css: scopedCss,
                js: scopedJs,
                themeCssUrls: activeStylesheets,
                tagName: tagSlug,
                className: selectedNode ? (selectedNode.className || '') : '',
                elementId: selectedNode ? (selectedNode.id || '') : '',
                sourcePageId: targetBlockId,
                sourceTitle: (window.VvvebWpConfig && window.VvvebWpConfig.activePostSlug) || '',
                createdAt: Date.now()
            };

            try {
                sessionStorage.setItem(STORAGE_KEY, JSON.stringify(editPayload));
                sessionStorage.setItem('align_dynamic_payload', JSON.stringify(editPayload));
                localStorage.setItem(STORAGE_KEY, JSON.stringify(editPayload));
            } catch (storageErr) {
                console.error('[Align Studio] Error writing to storage:', storageErr);
            }

            let studioUrl = (window.VvvebWpConfig && window.VvvebWpConfig.dynamicStudioUrl)
                ? window.VvvebWpConfig.dynamicStudioUrl
                : (window.location.origin + window.location.pathname + '?page=align-studio');
            studioUrl = studioUrl.replace('align-dynamic-studio', 'align-studio');
            studioUrl += (studioUrl.includes('?') ? '&' : '?') + 'post_id=' + targetBlockId;

            if (typeof window.displayToast === 'function') {
                window.displayToast('info', 'Align Studio', 'Opening Align Studio for block #' + targetBlockId + '...');
            }

            console.log('[Align Studio] Navigating to Align Studio in edit mode:', studioUrl);
            window.open(studioUrl, '_blank');
            return false;
        }

        const title = 'Dynamic ' + tagName + ' Block';
        const slug = 'dynamic-' + tagSlug + '-' + Math.random().toString(36).substring(2, 6);
        const sourcePageId = (window.VvvebWpConfig && window.VvvebWpConfig.activePostId) ? window.VvvebWpConfig.activePostId : 0;

        const payload = {
            html: cleanHtml,
            css: scopedCss,
            js: scopedJs,
            themeCssUrls: activeStylesheets,
            tagName: tagSlug,
            className: selectedNode.className || '',
            elementId: selectedNode.id || '',
            sourcePageId: sourcePageId,
            sourceTitle: (window.VvvebWpConfig && window.VvvebWpConfig.activePostSlug) ? window.VvvebWpConfig.activePostSlug : '',
            createdAt: Date.now()
        };

        const draftId = 'std_' + Math.random().toString(36).substring(2, 10);
        payload.draftId = draftId;

        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
            sessionStorage.setItem('align_dynamic_payload', JSON.stringify(payload));
            sessionStorage.setItem('align_studio_draft_id', draftId);
            localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
            localStorage.setItem('align_studio_draft_id', draftId);
            console.log('[Align Studio] Stored conversion payload in session & local storage:', {
                draftId: draftId,
                htmlLength: cleanHtml.length,
                cssLength: scopedCss.length,
                tag: payload.tagName
            });
        } catch (storageErr) {
            console.error('[Align Studio] Error writing to storage:', storageErr);
            if (typeof window.displayToast === 'function') {
                window.displayToast('danger', 'Error', 'Failed to save element payload to storage');
            }
            return false;
        }

        // 4. Open Align Studio in a new tab (must be absolute URL to bypass builder <base href>)
        let studioUrl = '';
        if (window.VvvebWpConfig && window.VvvebWpConfig.adminUrl) {
            studioUrl = window.VvvebWpConfig.adminUrl + '?page=align-studio';
        } else if (window.VvvebWpConfig && window.VvvebWpConfig.dynamicStudioUrl) {
            studioUrl = window.VvvebWpConfig.dynamicStudioUrl.replace('align-dynamic-studio', 'align-studio');
        } else {
            studioUrl = window.location.origin + window.location.pathname + '?page=align-studio';
        }
        studioUrl += '&draft_id=' + encodeURIComponent(draftId);
        if (sourcePageId) {
            studioUrl += '&post_id=' + sourcePageId;
        }

        if (typeof window.displayToast === 'function') {
            window.displayToast('info', 'Align Studio', 'Opening Align Studio...');
        }

        console.log('[Align Studio] Navigating to Align Studio:', studioUrl);
        window.open(studioUrl, '_blank');

        // Asynchronously save draft to REST endpoint so Studio iframe loads directly from server
        const ns = (window.VvvebWpConfig && window.VvvebWpConfig.restNamespace) || 'align-builder/v1';
        const draftEndpoint = (window.VvvebWpConfig && window.VvvebWpConfig.pluginUrl)
            ? `${window.VvvebWpConfig.pluginUrl}../../wp-json/${ns}/studio-draft`
            : `/wp-json/${ns}/studio-draft`;
        const nonce = window.VvvebWpConfig ? window.VvvebWpConfig.restNonce : '';

        fetch(draftEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
            body: JSON.stringify({
                draft_id: draftId,
                html: payload.html,
                css: payload.css,
                js: payload.js,
                tag: payload.tagName,
                source_page_id: sourcePageId,
                title: title
            })
        })
        .then(res => res.ok ? res.json() : null)
        .then(data => {
            console.log('[Align Studio] Pre-saved server draft successfully:', draftId);
        })
        .catch(err => {
            console.warn('[Align Studio] Could not pre-save draft, Studio will load from storage:', err);
        });

        return false;
    }

    return {
        init: init,
        handleConvertClick: handleConvertClick
    };
})();

// Auto-init on DOMContentLoaded or immediate if document already loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', window.AlignBuilderModules.DynamicBlockConverter.init);
} else {
    window.AlignBuilderModules.DynamicBlockConverter.init();
}
