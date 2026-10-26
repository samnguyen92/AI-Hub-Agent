/**
 * Align Theme - Dynamic Studio Live Preview Controller
 * Manages live WordPress data hydration, raw template view, debounced sync, and canvas interactions.
 *
 * @package Align
 */

window.StudioPreview = (function() {
    'use strict';

    let mode = 'live'; // 'live' | 'template'
    let rawTemplateHtml = '';
    let renderedLiveHtml = '';
    let debounceTimer = null;
    let isFetchingPreview = false;

    function init() {
        console.log('[Align DynamicPreview] Initializing StudioPreview controller...');
        setupModeSwitchers();
    }

    function setupModeSwitchers() {
        const group = document.getElementById('studio-mode-group');
        if (!group) return;
        group.querySelectorAll('.studio-mode-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.getAttribute('data-mode');
                if (target && target !== mode) setMode(target);
            });
        });
    }

    function setMode(newMode) {
        if (newMode !== 'live' && newMode !== 'template') return;
        mode = newMode;
        const group = document.getElementById('studio-mode-group');
        if (group) {
            group.querySelectorAll('.studio-mode-btn').forEach(btn => {
                btn.classList.toggle('active', btn.getAttribute('data-mode') === mode);
            });
        }
        console.log('[Align DynamicPreview] Switched preview mode to: ' + mode);
        if (mode === 'template') {
            hydrateCanvas(rawTemplateHtml || '');
        } else {
            renderedLiveHtml ? hydrateCanvas(renderedLiveHtml) : requestLivePreview(rawTemplateHtml);
        }
    }

    function getMode() {
        return mode;
    }

    function onEditorChange(newHtml) {
        rawTemplateHtml = newHtml;
        renderedLiveHtml = ''; // Invalidate stale live preview cache
        if (mode === 'template') {
            hydrateCanvas(newHtml);
            return;
        }
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => requestLivePreview(newHtml), 300);
    }

    function onAiConverted(rawHtml, renderedHtml) {
        rawTemplateHtml = rawHtml;
        renderedLiveHtml = renderedHtml || rawHtml;
        console.log('[Align DynamicPreview] 0-roundtrip AI convert received (mode: ' + mode + ')');
        hydrateCanvas(mode === 'live' ? renderedLiveHtml : rawTemplateHtml);
    }

    function onCanvasReady(iframe, initialHtml) {
        if (initialHtml) { rawTemplateHtml = initialHtml; renderedLiveHtml = initialHtml; }
        try {
            const doc = iframe.contentDocument || iframe.contentWindow.document;
            if (doc) attachCanvasInteractions(doc);
        } catch (err) {
            console.warn('[Align DynamicPreview] Canvas ready hook error:', err);
        }
    }

    function requestLivePreview(htmlToRender) {
        if (!htmlToRender || !htmlToRender.trim()) {
            renderedLiveHtml = '';
            hydrateCanvas('');
            return;
        }
        const config = window.AlignStudioConfig || {};
        if (!config.restPreviewUrl) {
            console.warn('[Align DynamicPreview] Missing restPreviewUrl. Falling back to raw HTML.');
            hydrateCanvas(htmlToRender);
            return;
        }

        isFetchingPreview = true;
        fetch(config.restPreviewUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.restNonce || '' },
            body: JSON.stringify({
                html: htmlToRender,
                source_page_id: config.postId || 0,
                post_id: config.postId || 0
            })
        })
        .then(res => res.ok ? res.json() : Promise.reject(new Error('HTTP ' + res.status)))
        .then(data => {
            if (data.status === 'success' && typeof data.rendered_html === 'string') {
                renderedLiveHtml = data.rendered_html;
                if (mode === 'live') hydrateCanvas(renderedLiveHtml);
            } else {
                throw new Error(data.message || 'Render preview failed');
            }
        })
        .catch(err => {
            console.error('[Align DynamicPreview] Preview request failed:', err);
            if (mode === 'live') hydrateCanvas(htmlToRender);
        })
        .finally(() => { isFetchingPreview = false; });
    }

    function hydrateCanvas(html) {
        const iframe = document.getElementById('studio-canvas-iframe');
        if (!iframe) return;

        const startTime = performance.now();
        try {
            const doc = iframe.contentDocument || iframe.contentWindow.document;
            if (!doc || !doc.body) return;

            let stage = doc.getElementById('align-studio-stage');
            if (!stage) {
                stage = doc.createElement('div');
                stage.id = 'align-studio-stage';
                const firstScript = doc.body.querySelector('script');
                firstScript ? doc.body.insertBefore(stage, firstScript) : doc.body.appendChild(stage);
            }

            const scrollTop = doc.documentElement ? doc.documentElement.scrollTop : (doc.body ? doc.body.scrollTop : 0);
            stage.innerHTML = html;
            if (doc.documentElement) doc.documentElement.scrollTop = scrollTop;
            if (doc.body) doc.body.scrollTop = scrollTop;

            attachCanvasInteractions(doc);
            const duration = Math.round(performance.now() - startTime);
            console.log('[Align DynamicPreview] Hydrated canvas stage (length: ' + (html ? html.length : 0) + ' bytes) in ' + duration + ' ms');
        } catch (err) {
            console.error('[Align DynamicPreview] Failed to hydrate canvas:', err);
        }
    }

    function showTestToast(msg) {
        const toast = document.getElementById('studio-toast');
        if (!toast) return;
        toast.textContent = msg;
        toast.className = 'studio-toast visible toast-info';
        setTimeout(() => toast.classList.remove('visible'), 2500);
    }

    function attachCanvasInteractions(doc) {
        if (!doc || !doc.body) return;

        // 1. Prevent link and form navigation inside iframe canvas
        doc.body.querySelectorAll('a').forEach(a => { a.onclick = (e) => e.preventDefault(); });
        doc.body.querySelectorAll('form').forEach(f => { f.onsubmit = (e) => e.preventDefault(); });

        // 2. Fallback broken/missing preview images
        doc.body.querySelectorAll('img').forEach(img => {
            img.onerror = function() {
                this.onerror = null;
                this.src = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="300" height="200" viewBox="0 0 300 200"><rect fill="%23f1f5f9" width="300" height="200"/><text fill="%2394a3b8" font-family="sans-serif" font-size="13" x="50%" y="50%" text-anchor="middle" dominant-baseline="middle">Image Preview</text></svg>';
            };
        });

        // 3. Interactive testing for Load More button in preview
        const loadMoreBtns = doc.body.querySelectorAll('.align-load-more-btn, [data-page], .load-more-btn');
        loadMoreBtns.forEach(btn => {
            if (btn._previewBound) return;
            btn._previewBound = true;
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const currentPage = parseInt(btn.getAttribute('data-page') || '1', 10);
                const maxPage = parseInt(btn.getAttribute('data-max-page') || '3', 10);
                const postsPerPage = parseInt(btn.getAttribute('data-posts-per-page') || '3', 10);

                if (currentPage >= maxPage) {
                    btn.disabled = true;
                    btn.setAttribute('disabled', 'disabled');
                    btn.textContent = 'All posts loaded';
                    btn.style.cssText += ';opacity:0.6!important;cursor:not-allowed!important;';
                    showTestToast('[Preview Test] All posts loaded (Page ' + maxPage + ' of ' + maxPage + ')');
                    return;
                }

                const nextPage = currentPage + 1;
                console.log('[Align DynamicPreview] Load More tested: Page ' + currentPage + ' of ' + maxPage + ' (limit: ' + postsPerPage + ')');

                const origText = btn.textContent;
                btn.textContent = 'Loading test page ' + nextPage + '...';
                btn.style.opacity = '0.7';

                setTimeout(() => {
                    btn.setAttribute('data-page', nextPage);
                    btn.textContent = (nextPage >= maxPage) ? 'All posts loaded' : origText;
                    btn.style.opacity = (nextPage >= maxPage) ? '0.6' : '1';
                    if (nextPage >= maxPage) btn.style.cursor = 'not-allowed';
                    showTestToast('[Preview Test] Load more clicked: Next page would be #' + nextPage + ' (Max: ' + maxPage + ')');
                }, 600);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return {
        init: init,
        setMode: setMode,
        getMode: getMode,
        onEditorChange: onEditorChange,
        onAiConverted: onAiConverted,
        onCanvasReady: onCanvasReady,
        hydrateCanvas: hydrateCanvas,
        requestLivePreview: requestLivePreview
    };
})();
