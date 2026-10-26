/**
 * Align Theme Builder - WordPress Components Shadow DOM Live Preview Engine
 *
 * Provides isolated, non-persistent, and interactive live previews for WordPress
 * Shortcodes, Gutenberg Blocks, and Post Content placeholders using Open Shadow DOM.
 *
 * @package AlignBuilder
 */

window.AlignBuilderModules = window.AlignBuilderModules || {};

window.AlignBuilderModules.WpComponentsPreview = (function() {
    'use strict';

    /** In-memory cache for rendered previews: key -> rendered HTML string. */
    const _cache = new Map();
    /** In-flight HTTP request map to deduplicate concurrent requests. */
    const _inFlight = new Map();
    /** WeakMap storing pending debounce timer per DOM Node. */
    const _debounceTimers = new WeakMap();
    /** Maximum depth allowed for nested component preview rendering. */
    const MAX_PREVIEW_DEPTH = 2;

    /**
     * Scoped stylesheet injected inside each component Shadow Root.
     */
    const SHADOW_STYLE = `:host { display: block !important; width: 100% !important; box-sizing: border-box !important; position: relative !important; isolation: isolate !important; outline: none !important; }
.align-preview-container, .align-preview-content { box-sizing: border-box; width: 100%; pointer-events: none !important; }
.align-preview-container { min-height: 24px; }
.align-preview-content * { pointer-events: none !important; user-select: none !important; }
.align-preview-card { display: flex; align-items: center; gap: 8px; padding: 10px 14px; margin: 4px 0; border-radius: 6px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 12.5px; line-height: 1.4; box-sizing: border-box; pointer-events: none !important; user-select: none !important; }
.align-preview-loading { background: #f8fafc; border: 1.5px dashed #94a3b8; color: #475569; }
.align-preview-empty { background: #f0f4ff; border: 1.5px dashed #4f7ef8; color: #1e293b; }
.align-preview-no-output { background: #fffbeb; border: 1.5px dashed #f59e0b; color: #92400e; }
.align-preview-error { background: #fef2f2; border: 1.5px dashed #ef4444; color: #991b1b; }
.align-preview-badge { display: inline-flex; align-items: center; justify-content: center; padding: 2px 7px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; flex-shrink: 0; color: #ffffff; }
.badge-shortcode { background: #4f7ef8; } .badge-block { background: #10b981; } .badge-post-content { background: #6366f1; } .badge-error { background: #ef4444; }
.align-preview-spinner { width: 14px; height: 14px; border: 2px solid rgba(79, 126, 248, 0.2); border-top-color: #4f7ef8; border-radius: 50%; animation: align-preview-spin 0.8s linear infinite; flex-shrink: 0; }
@keyframes align-preview-spin { to { transform: rotate(360deg); } }
.align-preview-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 12px; }`;

    /** Compute a unique signature string for a component element to detect recursive loops. */
    function getComponentSignature(el) {
        if (!el || !el.getAttribute) return '';
        if (el.hasAttribute('data-component-shortcode')) {
            return 'sc:' + (el.getAttribute('data-shortcode') || el.getAttribute('data-shortcode-text') || el.textContent || '').trim();
        }
        if (el.hasAttribute('data-component-wp-block')) {
            return 'blk:' + (el.getAttribute('data-wp-block') || el.textContent || '').trim();
        }
        if (el.hasAttribute('data-component-post-content')) {
            return 'post-content';
        }
        return '';
    }

    /** Check if a component node exceeds maximum preview nesting depth or creates a recursion cycle. */
    function checkRecursion(node) {
        const sig = getComponentSignature(node);
        let depth = 0;
        let curr = node;
        while (curr) {
            let parent = curr.parentElement;
            if (!parent && curr.parentNode && curr.parentNode.host) {
                parent = curr.parentNode.host;
            }
            if (!parent) break;
            curr = parent;
            if (curr.nodeType === 1 && (curr.hasAttribute('data-component-shortcode') || curr.hasAttribute('data-component-wp-block') || curr.hasAttribute('data-component-post-content'))) {
                depth++;
                if (depth >= MAX_PREVIEW_DEPTH) {
                    return { isRecursion: true, reason: `Max preview depth (${MAX_PREVIEW_DEPTH}) reached` };
                }
                if (sig && getComponentSignature(curr) === sig) {
                    return { isRecursion: true, reason: 'Recursive cycle detected' };
                }
            }
        }
        return false;
    }

    /** Escape special HTML characters to prevent XSS. */
    function escapeHtml(str) {
        if (!str || typeof str !== 'string') return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    /** Strip script tags from rendered HTML to avoid unwanted code execution in editor canvas. */
    function sanitizeRenderedHtml(html) {
        if (!html || typeof html !== 'string') return '';
        return html.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
    }

    /** Retrieve or create an Open Shadow Root on the host node. */
    function getOrCreateShadowRoot(node) {
        if (!node || !node.attachShadow) return null;
        if (node.shadowRoot) return node.shadowRoot;
        try {
            return node.attachShadow({ mode: 'open' });
        } catch (err) {
            console.error('[Align WpComponentsPreview] Failed to attach shadow root:', err);
            return null;
        }
    }

    /** Retrieve REST configuration from window.VvvebWpConfig with safe fallbacks. */
    function getRestConfig() {
        const cfg = window.VvvebWpConfig || {};
        return {
            nonce: cfg.restNonce || '',
            shortcodeUrl: cfg.restRenderShortcodeUrl || '/wp-json/align-builder/v1/render-shortcode',
            blockUrl: cfg.restRenderBlockUrl || '/wp-json/align-builder/v1/render-block'
        };
    }

    /**
     * Render UI states inside component Shadow Root.
     *
     * @param {HTMLElement} node Target component node.
     * @param {'loading'|'empty'|'no-output'|'error'|'post-content'|'success'} state UI State.
     * @param {Object} payload State payload (type, rawVal, error, html).
     */
    function renderShadowDOM(node, state, payload) {
        const shadow = getOrCreateShadowRoot(node);
        if (!shadow) return;

        try {
            if (window.AlignBuilderModules && window.AlignBuilderModules.WpStyleInjector && typeof window.AlignBuilderModules.WpStyleInjector.applyShadowRootStyleSheets === 'function') {
                window.AlignBuilderModules.WpStyleInjector.applyShadowRootStyleSheets(shadow);
            }
        } catch (e) {
            // Non-blocking style injection fallback
        }

        payload = payload || {};
        node.setAttribute('data-preview-state', state);

        let bodyHtml = '';
        const type = payload.type || 'shortcode';
        const badgeClass = type === 'block' ? 'badge-block' : 'badge-shortcode';
        const badgeLabel = type === 'block' ? '{} Block' : '[/] Shortcode';

        switch (state) {
            case 'loading':
                bodyHtml = `<div class="align-preview-card align-preview-loading"><span class="align-preview-spinner"></span><span class="align-preview-badge ${badgeClass}">${badgeLabel}</span><span class="align-preview-text">Rendering preview...</span></div>`;
                break;
            case 'empty':
                bodyHtml = `<div class="align-preview-card align-preview-empty"><span class="align-preview-badge ${badgeClass}">${badgeLabel}</span><span class="align-preview-text">${escapeHtml(type === 'block' ? 'Empty Gutenberg block' : 'Empty WordPress shortcode')}</span></div>`;
                break;
            case 'no-output':
                bodyHtml = `<div class="align-preview-card align-preview-no-output"><span class="align-preview-badge ${badgeClass}">${badgeLabel}</span><span class="align-preview-text">${escapeHtml(payload.rawVal || '')} &mdash; <em>(No HTML output rendered)</em></span></div>`;
                break;
            case 'error':
                bodyHtml = `<div class="align-preview-card align-preview-error"><span class="align-preview-badge badge-error">Error</span><span class="align-preview-text">${escapeHtml(payload.error || 'Failed to render preview')}</span></div>`;
                break;
            case 'post-content':
                bodyHtml = `<div class="align-preview-card align-preview-empty" style="background:#f8fafc;border-color:#6366f1;color:#334155;"><span class="align-preview-badge badge-post-content" style="background:#6366f1;">WP Post Content</span><span class="align-preview-text" style="font-weight:500;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:12.5px;">Dynamic Page / Post Content Placeholder</span></div>`;
                break;
            case 'success':
                node.setAttribute('data-preview-state', 'rendered');
                bodyHtml = `<div class="align-preview-content">${sanitizeRenderedHtml(payload.html || '')}</div>`;
                break;
        }

        shadow.innerHTML = `<style>${SHADOW_STYLE}</style><div class="align-preview-container">${bodyHtml}</div>`;

        if (window.AlignBuilderModules && window.AlignBuilderModules.WpStyleInjector && typeof window.AlignBuilderModules.WpStyleInjector.injectAssetsIntoShadowRoot === 'function') {
            window.AlignBuilderModules.WpStyleInjector.injectAssetsIntoShadowRoot(shadow);
        }

        if (state === 'success') {
            setTimeout(() => {
                shadow.querySelectorAll('[data-component-shortcode], [data-component-wp-block]').forEach(child => renderNodePreview(child, false, 0));
            }, 16);
            if (window.AlignBuilderModules && window.AlignBuilderModules.WpStyleInjector && typeof window.AlignBuilderModules.WpStyleInjector.normalizeImagesInIframe === 'function') {
                window.AlignBuilderModules.WpStyleInjector.normalizeImagesInIframe(shadow);
            }

            // Bootstrap live dynamic components inside Shadow DOM (e.g. Live Search, Product Details)
            setTimeout(function() {
                try {
                    const frameWin = window.FrameWindow || window.Vvveb?.Builder?.iframe?.contentWindow || window;
                    const liveSearch = frameWin?.AlignLiveSearch || window.AlignLiveSearch;
                    if (typeof liveSearch?.bootstrap === 'function') liveSearch.bootstrap(shadow);
                    const prodDetails = frameWin?.AlignProductDetails || window.AlignProductDetails;
                    if (typeof prodDetails?.init === 'function') prodDetails.init(shadow);
                } catch (e) { /* Non-blocking */ }
            }, 60);

            // Notify listeners that preview rendered and sync live inspector if component is selected
            try {
                window.dispatchEvent(new CustomEvent('Align.WpComponentsPreview.rendered', { detail: { node } }));
                const isSelected = (window.Vvveb?.Builder?.selectedEl === node) || (window.Vvveb?.Builder?.selectedEls && window.Vvveb.Builder.selectedEls.has(node));
                if (isSelected && window.AlignBuilderModules?.WpReuseBlockOverwrite) {
                    window.AlignBuilderModules.WpReuseBlockOverwrite.handleNodeSelection(node, true);
                }
            } catch (e) {
                console.error('[Align WpComponentsPreview] Error notifying preview rendered:', e);
            }
        }
    }

    /** Fetch shortcode rendered preview via WordPress REST API with caching & deduplication. */
    async function fetchShortcodePreview(shortcodeVal, instanceId) {
        const cleanVal = (shortcodeVal || '').trim();
        if (!cleanVal) return '';

        const wpConfig = window.VvvebWpConfig || {};
        const curPage = window.Vvveb?.FileManager?.currentPage ? window.Vvveb.FileManager.pages[window.Vvveb.FileManager.currentPage] : null;
        const pageId = (curPage && curPage.postId) || wpConfig.activePostId || 0;
        const tpId = (curPage && curPage.tpId) || wpConfig.activeTpId || '';
        const templateType = (curPage && curPage.templateType) || '';
        const instKey = instanceId || 'default';

        const cacheKey = 'sc:' + cleanVal + ':' + instKey + ':' + pageId + ':' + tpId;
        if (_cache.has(cacheKey)) return _cache.get(cacheKey);
        if (_inFlight.has(cacheKey)) return _inFlight.get(cacheKey);

        const config = getRestConfig();
        const promise = (async () => {
            try {
                const headers = { 'Content-Type': 'application/json' };
                if (config.nonce) headers['X-WP-Nonce'] = config.nonce;
                const response = await fetch(config.shortcodeUrl, {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify({
                        shortcode: cleanVal,
                        instance_id: instKey,
                        page_id: pageId,
                        tp_id: tpId,
                        template_type: templateType
                    })
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                const data = await response.json();
                const rendered = (data && typeof data.rendered === 'string') ? data.rendered : '';
                _cache.set(cacheKey, rendered);
                return rendered;
            } finally {
                _inFlight.delete(cacheKey);
            }
        })();

        _inFlight.set(cacheKey, promise);
        return promise;
    }

    /** Fetch block rendered preview via WordPress REST API with caching & deduplication. */
    async function fetchBlockPreview(blockVal) {
        const cleanVal = (blockVal || '').trim();
        if (!cleanVal) return '';

        const cacheKey = 'blk:' + cleanVal;
        if (_cache.has(cacheKey)) return _cache.get(cacheKey);
        if (_inFlight.has(cacheKey)) return _inFlight.get(cacheKey);

        const config = getRestConfig();
        const promise = (async () => {
            try {
                const headers = { 'Content-Type': 'application/json' };
                if (config.nonce) headers['X-WP-Nonce'] = config.nonce;
                const response = await fetch(config.blockUrl, {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify({ block_content: cleanVal })
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                const data = await response.json();
                const rendered = (data && typeof data.rendered === 'string') ? data.rendered : '';
                _cache.set(cacheKey, rendered);
                return rendered;
            } finally {
                _inFlight.delete(cacheKey);
            }
        })();

        _inFlight.set(cacheKey, promise);
        return promise;
    }

    /** Render live preview for a single component DOM node with sequence guard and debounce. */
    function renderNodePreview(node, forceRefresh = false, debounceMs = 0) {
        if (!node || !node.hasAttribute) return;

        const isShortcode = node.hasAttribute('data-component-shortcode');
        const isBlock = node.hasAttribute('data-component-wp-block');
        const isPostContent = node.hasAttribute('data-component-post-content');

        if (!isShortcode && !isBlock && !isPostContent) return;

        const rec = checkRecursion(node);
        if (rec && rec.isRecursion) {
            console.warn(`[Align WpComponentsPreview] Recursion prevented: ${rec.reason}`);
            renderShadowDOM(node, 'error', { error: rec.reason });
            return;
        }

        const currentState = node.getAttribute('data-preview-state');
        if (!forceRefresh && (currentState === 'rendered' || currentState === 'success')) {
            const shadow = node.shadowRoot;
            if (shadow && shadow.querySelector('.align-preview-content')) {
                return; // Content is already rendered and intact, DO NOT re-assign innerHTML, DO NOT reload images
            }
        }

        if (isPostContent) {
            renderShadowDOM(node, 'post-content', {});
            return;
        }

        const seq = (node._alignPreviewSeq = (node._alignPreviewSeq || 0) + 1);

        if (_debounceTimers.has(node)) {
            clearTimeout(_debounceTimers.get(node));
            _debounceTimers.delete(node);
        }

        const executeRender = async () => {
            if (node._alignPreviewSeq !== seq) return;

            const type = isShortcode ? 'shortcode' : 'block';
            const attrName = isShortcode ? 'data-shortcode' : 'data-wp-block';
            let rawVal = (node.getAttribute(attrName) || '').trim();
            if (!rawVal && isShortcode) {
                rawVal = (node.getAttribute('data-shortcode-text') || node.textContent || '').trim();
            }

            if (!rawVal) {
                renderShadowDOM(node, 'empty', { type: type });
                return;
            }

            // Disambiguate reusable block instance to guarantee preview and cache isolation
            let instanceId = 'default';
            const isReuse = isShortcode && /\[(?:reuse-block|reuse_block|align_block|align_reusable_block|reusable_block)\b/i.test(rawVal);
            if (isReuse) {
                const matchInst = rawVal.match(/\b(?:instance|instance_id|key)=["']?([a-zA-Z0-9_-]+)["']?/i);
                instanceId = node.getAttribute('data-instance-id') || node.getAttribute('data-instance') || (matchInst && matchInst[1]) || '';

                if (!instanceId) {
                    const matchId = rawVal.match(/id=["']?(\d+)["']?/i);
                    const matchSlug = rawVal.match(/\bslug=["']?([^"'\s\]]+)["']?/i);
                    const blockRef = (matchId && matchId[1]) || (matchSlug && matchSlug[1]);
                    const doc = node.ownerDocument || window.FrameDocument || (window.Vvveb?.Builder?.frameDoc) || document;

                    if (blockRef && doc && doc.querySelectorAll) {
                        const allSame = Array.from(doc.querySelectorAll('[data-component-shortcode], [data-component-wp-block]')).filter(el => {
                            const sc = (el.getAttribute('data-shortcode') || el.getAttribute('data-wp-block') || el.textContent || '').trim();
                            return sc.includes(blockRef) || (el.getAttribute('data-reuse-block-id') === blockRef);
                        });
                        const idx = allSame.indexOf(node);
                        instanceId = (idx > 0) ? ('inst_' + (idx + 1)) : 'default';
                    } else {
                        instanceId = 'default';
                    }
                }

                node.setAttribute('data-instance-id', instanceId);
                if (instanceId !== 'default' && !rawVal.includes('instance=')) {
                    rawVal = rawVal.replace(/\]$/, ` instance="${instanceId}"]`);
                    node.setAttribute('data-shortcode', rawVal);
                    node.textContent = rawVal;
                }
            }

            const wpConfig = window.VvvebWpConfig || {};
            const curPage = window.Vvveb?.FileManager?.currentPage ? window.Vvveb.FileManager.pages[window.Vvveb.FileManager.currentPage] : null;
            const pageId = (curPage && curPage.postId) || wpConfig.activePostId || 0;
            const tpId = (curPage && curPage.tpId) || wpConfig.activeTpId || '';

            const cacheKey = isShortcode
                ? ('sc:' + rawVal + ':' + instanceId + ':' + pageId + ':' + tpId)
                : ('blk:' + rawVal);

            if (forceRefresh) {
                _cache.delete(cacheKey);
            }

            if (_cache.has(cacheKey)) {
                try {
                    const cachedHtml = _cache.get(cacheKey);
                    if (!cachedHtml || !cachedHtml.trim()) {
                        renderShadowDOM(node, 'no-output', { type: type, rawVal: rawVal });
                    } else {
                        renderShadowDOM(node, 'success', { html: cachedHtml });
                    }
                } catch (err) {
                    console.error(`[Align WpComponentsPreview] Error rendering cached ${type} preview:`, err);
                    renderShadowDOM(node, 'error', { error: err.message || 'Render failed' });
                }
                return;
            }

            renderShadowDOM(node, 'loading', { type: type });

            try {
                const rendered = isShortcode
                    ? await fetchShortcodePreview(rawVal, instanceId)
                    : await fetchBlockPreview(rawVal);

                if (node._alignPreviewSeq !== seq) return;

                if (!rendered || !rendered.trim()) {
                    renderShadowDOM(node, 'no-output', { type: type, rawVal: rawVal });
                } else {
                    renderShadowDOM(node, 'success', { html: rendered });
                }
            } catch (err) {
                if (node._alignPreviewSeq !== seq) return;
                console.error(`[Align WpComponentsPreview] Error rendering ${type} preview:`, err);
                renderShadowDOM(node, 'error', { error: err.message || 'Render failed' });
            }
        };

        if (debounceMs > 0) {
            const timer = setTimeout(executeRender, debounceMs);
            _debounceTimers.set(node, timer);
        } else {
            executeRender();
        }
    }

    /**
     * Query all WordPress component nodes in the active canvas iframe and trigger preview rendering.
     *
     * @param {boolean} forceRefresh Whether to force re-render/cache bust.
     */
    function triggerWpComponentsPreviewInIframe(forceRefresh = false) {
        const frameDoc = window.FrameDocument || (window.Vvveb && window.Vvveb.Builder && window.Vvveb.Builder.iframe ? window.Vvveb.Builder.iframe.contentDocument : null);
        if (!frameDoc || !frameDoc.body) return;
        const scan = (root) => {
            if (!root || !root.querySelectorAll) return;
            root.querySelectorAll('[data-component-shortcode], [data-component-wp-block], [data-component-post-content]').forEach(n => renderNodePreview(n, forceRefresh, 0));
            root.querySelectorAll('*').forEach(el => { if (el.shadowRoot) scan(el.shadowRoot); });
        };
        scan(frameDoc.body);
    }

    /** Clear preview caches. */
    function clearCache() {
        _cache.clear();
        _inFlight.clear();
    }

    /** Auto-bind lifecycle events to refresh previews whenever canvas iframe loads. */
    function init() {
        window.addEventListener('Vvveb.iframe.loaded', function() {
            setTimeout(function() {
                triggerWpComponentsPreviewInIframe(true);
            }, 50);
        });

        window.addEventListener('Vvveb.FileManager.loadPage', function() {
            setTimeout(function() {
                triggerWpComponentsPreviewInIframe(true);
            }, 200);
        });

        window.addEventListener('Vvveb.Builder.selectNode', function(e) {
            const target = e.detail ? e.detail.target : null;
            if (target && target.hasAttribute && (target.hasAttribute('data-component-shortcode') || target.hasAttribute('data-component-wp-block'))) {
                const shadow = target.shadowRoot;
                if (!shadow || !shadow.querySelector('.align-preview-content')) {
                    renderNodePreview(target, false, 0);
                }
            }
        });
    }

    return {
        escapeHtml, sanitizeRenderedHtml, getOrCreateShadowRoot, renderShadowDOM,
        fetchShortcodePreview, fetchBlockPreview, renderNodePreview,
        triggerWpComponentsPreviewInIframe, clearCache, init,
        MAX_PREVIEW_DEPTH, getComponentSignature, checkRecursion
    };
})();

// Auto-initialize lifecycle listener
if (window.AlignBuilderModules.WpComponentsPreview) {
    window.AlignBuilderModules.WpComponentsPreview.init();
}
