/**
 * Align Theme - Align Studio Main Controller JS
 * Orchestrates canvas preview, live two-way sync, inspector, and block export
 *
 * @package Align
 */

window.AlignStudio = (function() {
    'use strict';

    let currentPayload = {
        html: '',
        css: '',
        js: '',
        tag: 'SECTION',
        sourcePageId: 0,
        classes: ''
    };

    let exportedBlockId = null;

    function init() {
        console.log('[Align Studio] Initializing Align Studio...');
        loadPayload();
        setupBlockMetadata();
        renderCanvas();
        setupViewportSwitcher();
        setupPanelToggles();
        setupExportAction();
        setupCopyAction();

        if (window.StudioCodeEditor && typeof window.StudioCodeEditor.init === 'function') {
            window.StudioCodeEditor.init();
            window.StudioCodeEditor.setContent(currentPayload.html, currentPayload.css, currentPayload.js);
        }
    }

    function loadPayload() {
        const config = window.AlignStudioConfig || {};
        if (config.isEditMode && config.postId) {
            currentPayload.postId = currentPayload.sourcePageId = config.postId;
            if (config.blockTitle) currentPayload.title = config.blockTitle;
            if (config.blockSlug) currentPayload.slug = config.blockSlug;
            if (config.existingHtml) currentPayload.html = config.existingHtml;
            if (config.existingCss) currentPayload.css = config.existingCss;
            if (config.existingJs) currentPayload.js = config.existingJs;
        }

        try {
            const raw = sessionStorage.getItem('align_studio_payload') || sessionStorage.getItem('align_dynamic_payload') || localStorage.getItem('align_studio_payload');
            if (raw) {
                const parsed = JSON.parse(raw);
                const isMatch = !config.isEditMode || (String(parsed.postId) === String(config.postId) || String(parsed.sourcePageId) === String(config.postId));
                if (isMatch) {
                    currentPayload = Object.assign(currentPayload, parsed);
                    if (parsed.tagName && !currentPayload.tag) currentPayload.tag = parsed.tagName.toUpperCase();
                    if (parsed.className && !currentPayload.classes) currentPayload.classes = parsed.className;
                    if (parsed.js && !currentPayload.js) currentPayload.js = parsed.js;

                    if (Array.isArray(parsed.themeCssUrls) && parsed.themeCssUrls.length > 0) {
                        window.AlignStudioConfig = window.AlignStudioConfig || {};
                        const existing = window.AlignStudioConfig.themeCssUrls || [];
                        window.AlignStudioConfig.themeCssUrls = Array.from(new Set([...existing, ...parsed.themeCssUrls]));
                    }
                }

                console.log('[Align Studio] Payload loaded from storage:', {
                    isEditMode: !!config.isEditMode,
                    tag: currentPayload.tag,
                    htmlLen: currentPayload.html ? currentPayload.html.length : 0,
                    cssLen: currentPayload.css ? currentPayload.css.length : 0
                });
            }
        } catch (err) {
            console.error('[Align Studio] Failed to parse payload:', err);
        }
    }

    function setupBlockMetadata() {
        const topTitle = document.getElementById('studio-block-title-top');
        const sideTitle = document.getElementById('studio-block-title');
        const slugInput = document.getElementById('studio-block-slug');
        const metaSource = document.getElementById('meta-source-page');
        const config = window.AlignStudioConfig || {};

        const tag = (currentPayload.tag || 'SECTION').toUpperCase();
        const initialTitle = (config.isEditMode && (config.blockTitle || currentPayload.title))
            ? (config.blockTitle || currentPayload.title)
            : ('Dynamic ' + (tag === 'SECTION' ? 'Section' : (tag === 'ARTICLE' ? 'Card' : tag)));
        const initialSlug = (config.isEditMode && (config.blockSlug || currentPayload.slug))
            ? (config.blockSlug || currentPayload.slug)
            : ('dynamic-' + tag.toLowerCase() + '-' + Math.random().toString(36).substring(2, 6));

        if (topTitle) topTitle.value = initialTitle;
        if (sideTitle) sideTitle.value = initialTitle;
        if (slugInput) slugInput.value = initialSlug;
        if (metaSource) {
            metaSource.textContent = config.isEditMode
                ? `#${config.postId} (Reusable Block)`
                : (currentPayload.sourcePageId ? `#${currentPayload.sourcePageId}` : 'Builder Canvas');
        }

        const syncTitle = (val) => {
            if (topTitle && topTitle.value !== val) topTitle.value = val;
            if (sideTitle && sideTitle.value !== val) sideTitle.value = val;
            if (!config.isEditMode && slugInput) {
                slugInput.value = val.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'dynamic-block';
            }
        };

        if (topTitle) topTitle.addEventListener('input', e => syncTitle(e.target.value));
        if (sideTitle) sideTitle.addEventListener('input', e => syncTitle(e.target.value));
    }

    function getStage(doc) {
        if (!doc || !doc.body) return null;
        let stage = doc.getElementById('align-studio-stage');
        if (!stage) {
            stage = doc.createElement('div');
            stage.id = 'align-studio-stage';
            const firstScript = doc.body.querySelector('script');
            if (firstScript) {
                doc.body.insertBefore(stage, firstScript);
            } else {
                doc.body.appendChild(stage);
            }
        }
        return stage;
    }

    function renderCanvas() {
        const iframe = document.getElementById('studio-canvas-iframe');
        if (!iframe) return;

        const config = window.AlignStudioConfig || {};

        const onIframeReady = function() {
            try {
                const doc = iframe.contentDocument || iframe.contentWindow.document;
                if (!doc || !doc.body) return;

                console.log('[Align Studio] Iframe canvas ready:', iframe.src);
                const stage = getStage(doc);

                // 1. Synchronize HTML into canvas stage
                if (currentPayload.html) {
                    if (stage && (!stage.innerHTML.trim() || stage.innerHTML.trim() !== currentPayload.html.trim())) {
                        stage.innerHTML = currentPayload.html;
                        console.log('[Align Studio] Injected HTML into canvas stage, length:', currentPayload.html.length);
                    }
                } else if (stage && stage.innerHTML.trim()) {
                    currentPayload.html = stage.innerHTML.trim();
                    console.log('[Align Studio] Synced HTML from server canvas into currentPayload');
                }

                // 2. Synchronize Scoped CSS into <style id="align-studio-scoped-css">
                let scopedCssEl = doc.getElementById('align-studio-scoped-css');
                if (!scopedCssEl) {
                    scopedCssEl = doc.createElement('style');
                    scopedCssEl.id = 'align-studio-scoped-css';
                    (doc.head || doc.body).appendChild(scopedCssEl);
                }
                if (currentPayload.css) {
                    scopedCssEl.textContent = currentPayload.css;
                    console.log('[Align Studio] Injected CSS into canvas, length:', currentPayload.css.length);
                } else if (scopedCssEl.textContent.trim()) {
                    currentPayload.css = scopedCssEl.textContent.trim();
                }

                // 3. Synchronize Scoped JS into <script id="align-studio-scoped-js">
                let scopedJsEl = doc.getElementById('align-studio-scoped-js');
                if (currentPayload.js) {
                    if (!scopedJsEl) {
                        scopedJsEl = doc.createElement('script');
                        scopedJsEl.id = 'align-studio-scoped-js';
                        doc.body.appendChild(scopedJsEl);
                    }
                    scopedJsEl.textContent = currentPayload.js;
                    console.log('[Align Studio] Injected JS into canvas, length:', currentPayload.js.length);
                } else if (scopedJsEl && scopedJsEl.textContent.trim()) {
                    currentPayload.js = scopedJsEl.textContent.trim();
                }

                // 4. Update CodeMirror 6 Editor with latest values
                if (window.StudioCodeEditor && typeof window.StudioCodeEditor.setContent === 'function') {
                    window.StudioCodeEditor.setContent(currentPayload.html, currentPayload.css, currentPayload.js);
                }

                // 5. Enforce natural document scrolling inside iframe
                if (doc.documentElement) {
                    doc.documentElement.style.cssText += ';overflow-y:auto!important;overflow-x:hidden!important;height:100%!important;scroll-behavior:auto!important;';
                }
                if (doc.body) {
                    doc.body.style.cssText += ';overflow-y:visible!important;min-height:100%!important;height:auto!important;';
                }

                // Dummy / broken image fallback
                doc.body.querySelectorAll('img').forEach(img => {
                    img.addEventListener('error', function() {
                        this.onerror = null;
                        this.src = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="300" height="200" viewBox="0 0 300 200"><rect fill="%23f1f5f9" width="300" height="200"/><text fill="%2394a3b8" font-family="sans-serif" font-size="13" x="50%" y="50%" text-anchor="middle" dominant-baseline="middle">Image Preview</text></svg>';
                    });
                });

                attachCanvasBehavior(doc);

                if (window.StudioPreview && typeof window.StudioPreview.onCanvasReady === 'function') {
                    window.StudioPreview.onCanvasReady(iframe, currentPayload.html);
                }
            } catch (err) {
                console.warn('[Align Studio] Cannot access iframe DOM:', err);
            }
        };

        iframe.onload = onIframeReady;

        // Forward outer viewport mouse wheel scrolling into iframe
        const viewport = document.getElementById('studio-canvas-viewport');
        if (viewport && !viewport.dataset.wheelBound) {
            viewport.dataset.wheelBound = '1';
            viewport.addEventListener('wheel', e => {
                if (iframe && iframe.contentWindow && typeof iframe.contentWindow.scrollBy === 'function') {
                    iframe.contentWindow.scrollBy({ top: e.deltaY, behavior: 'auto' });
                }
            }, { passive: true });
        }

        // If iframe is already loaded (e.g. from cache or fast load)
        if (iframe.contentDocument && iframe.contentDocument.readyState === 'complete' && iframe.contentDocument.body) {
            onIframeReady();
        }

        // Ensure iframe.src points to server canvas endpoint
        const currentSrc = iframe.getAttribute('src') || iframe.src;
        if (!currentSrc || currentSrc === 'about:blank' || currentSrc.endsWith('/admin.php')) {
            const targetUrl = config.initialCanvasUrl || (config.restCanvasUrl ? config.restCanvasUrl + '?_wpnonce=' + (config.restNonce || '') : '');
            if (targetUrl) {
                console.log('[Align Studio] Setting iframe.src to:', targetUrl);
                iframe.src = targetUrl;
            }
        }
    }

    function attachCanvasBehavior(doc) {
        if (!doc || !doc.body) return;
        doc.body.querySelectorAll('a').forEach(a => { a.onclick = (e) => e.preventDefault(); });
        doc.body.querySelectorAll('form').forEach(f => { f.onsubmit = (e) => e.preventDefault(); });
    }

    function updateHtmlFromEditor(newHtml) {
        currentPayload.html = newHtml;
        if (window.StudioPreview && typeof window.StudioPreview.onEditorChange === 'function') {
            window.StudioPreview.onEditorChange(newHtml);
            return;
        }
        const iframe = document.getElementById('studio-canvas-iframe');
        if (!iframe) return;
        try {
            const doc = iframe.contentDocument || iframe.contentWindow.document;
            const stage = doc ? getStage(doc) : null;
            if (stage) {
                stage.innerHTML = newHtml;
                attachCanvasBehavior(doc);
            }
        } catch (err) {
            console.error('[Align Studio] Error updating HTML in iframe:', err);
        }
    }

    function updateCssFromEditor(newCss) {
        currentPayload.css = newCss;
        const iframe = document.getElementById('studio-canvas-iframe');
        if (!iframe) return;

        try {
            const doc = iframe.contentDocument || iframe.contentWindow.document;
            if (doc) {
                let styleEl = doc.getElementById('align-studio-scoped-css');
                if (!styleEl) {
                    styleEl = doc.createElement('style');
                    styleEl.id = 'align-studio-scoped-css';
                    (doc.head || doc.body).appendChild(styleEl);
                }
                styleEl.textContent = newCss;
                console.log('[Align Studio] Realtime CSS updated in canvas, len:', newCss.length);
            }
        } catch (err) {
            console.error('[Align Studio] Error updating CSS in iframe:', err);
        }
    }

    function updateJsFromEditor(newJs) {
        currentPayload.js = newJs;
        const iframe = document.getElementById('studio-canvas-iframe');
        if (!iframe) return;

        try {
            const doc = iframe.contentDocument || iframe.contentWindow.document;
            if (doc && doc.body) {
                let oldScript = doc.getElementById('align-studio-scoped-js');
                const newScript = doc.createElement('script');
                newScript.id = 'align-studio-scoped-js';
                newScript.textContent = newJs;
                if (oldScript && oldScript.parentNode) {
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                } else {
                    doc.body.appendChild(newScript);
                }
                console.log('[Align Studio] Realtime JS updated in canvas, len:', newJs.length);
            }
        } catch (err) {
            console.error('[Align Studio] Error updating JS in iframe:', err);
        }
    }

    function setupViewportSwitcher() {
        const wrapper = document.getElementById('canvas-wrapper');
        const buttons = document.querySelectorAll('.studio-viewport-btn');
        if (!wrapper || !buttons.length) return;

        buttons.forEach(btn => {
            btn.addEventListener('click', () => {
                buttons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const vp = btn.getAttribute('data-viewport') || '100%';
                wrapper.style.maxWidth = vp;
                if (vp === '100%') {
                    wrapper.classList.remove('is-responsive');
                } else {
                    wrapper.classList.add('is-responsive');
                }
            });
        });
    }

    function setupPanelToggles() {
        const toggleBtn = document.getElementById('toggle-code-btn');
        const panel = document.getElementById('studio-code-panel');
        if (toggleBtn && panel) {
            toggleBtn.addEventListener('click', () => {
                panel.classList.toggle('d-none');
                const isVis = !panel.classList.contains('d-none');
                toggleBtn.classList.toggle('active', isVis);
                if (isVis && window.StudioCodeEditor) window.StudioCodeEditor.switchTab('html');
            });
        }
    }

    function setupExportAction() {
        const exportBtn = document.getElementById('export-reusable-btn');
        if (!exportBtn) return;

        exportBtn.addEventListener('click', function(e) {
            e.preventDefault();

            if (window.StudioCodeEditor) {
                const ed = window.StudioCodeEditor.getContent();
                if (ed.html) currentPayload.html = ed.html;
                if (ed.css) currentPayload.css = ed.css;
                if (ed.js) currentPayload.js = ed.js;
            }

            if (!currentPayload.html) return showToast('No element content available to export.', 'danger');

            const title = (document.getElementById('studio-block-title-top')?.value || document.getElementById('studio-block-title')?.value || 'Dynamic Reusable Block').trim();
            const slug = (document.getElementById('studio-block-slug')?.value || title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'dynamic-block').trim();
            const config = window.AlignStudioConfig || {};
            if (!config.restExportUrl) return showToast('Server configuration error: missing export URL', 'danger');

            const isEdit = !!(config.isEditMode && config.postId);
            const targetPostId = isEdit ? config.postId : (currentPayload.postId || 0);

            exportBtn.disabled = true;
            const btnText = exportBtn.querySelector('.btn-text');
            const spinner = exportBtn.querySelector('.spinner-border');
            if (btnText) btnText.textContent = isEdit ? 'Updating...' : 'Exporting...';
            if (spinner) spinner.classList.remove('d-none');

            console.log('[Align Studio] Sending export/update for:', title, 'ID:', targetPostId);

            fetch(config.restExportUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.restNonce || '' },
                body: JSON.stringify({
                    post_id: targetPostId,
                    title,
                    slug,
                    html: currentPayload.html,
                    css: currentPayload.css || '',
                    js: currentPayload.js || '',
                    source_page_id: currentPayload.sourcePageId || 0
                })
            })
            .then(res => res.ok ? res.json() : Promise.reject(new Error('HTTP ' + res.status)))
            .then(data => {
                if (data.status === 'success' && data.post_id) {
                    exportedBlockId = data.post_id;
                    displayExportSuccess(data);
                    showToast(data.message || (isEdit ? 'Reusable Block updated!' : 'Reusable Block created!'), 'success');
                } else {
                    throw new Error(data.message || 'Export failed');
                }
            })
            .catch(err => {
                console.error('[Align Studio] Export failed:', err);
                showToast('Export failed: ' + (err.message || 'Error'), 'danger');
            })
            .finally(() => {
                exportBtn.disabled = false;
                if (spinner) spinner.classList.add('d-none');
            });
        });
    }

    function displayExportSuccess(data) {
        const banner = document.getElementById('export-success-banner');
        const scSidebar = document.getElementById('shortcode-sidebar-code');
        const scTop = document.getElementById('shortcode-top-preview');
        const topBox = document.getElementById('top-panel-shortcode-container');
        const exportBtn = document.getElementById('export-reusable-btn');
        const isUpdate = (data.mode === 'update');

        if (banner) {
            banner.classList.add('visible');
            banner.innerHTML = `<strong>Success!</strong> Reusable Block <strong>#${data.post_id}</strong> ${isUpdate ? 'updated' : 'created'}.`;
        }
        if (scSidebar && data.shortcode) scSidebar.textContent = data.shortcode;
        if (scTop && topBox && data.shortcode) { scTop.textContent = data.shortcode; topBox.style.display = 'inline-flex'; }
        if (exportBtn && data.post_id) {
            const btnText = exportBtn.querySelector('.btn-text');
            if (btnText) btnText.textContent = isUpdate ? `Updated (#${data.post_id})` : `Exported (#${data.post_id})`;
            exportBtn.classList.add('is-success');
            setTimeout(() => {
                if (btnText) btnText.textContent = isUpdate ? 'Update Reusable Block' : `Exported (#${data.post_id})`;
                exportBtn.classList.remove('is-success');
            }, 3000);
        }
    }

    function setupCopyAction() {
        const copyBtn = document.getElementById('btn-copy-shortcode');
        const scSidebar = document.getElementById('shortcode-sidebar-code');
        const topBox = document.getElementById('top-panel-shortcode-container');
        const scTop = document.getElementById('shortcode-top-preview');

        const doCopy = (text, cb) => {
            if (!text || text.includes('...')) return showToast('Please export the block first to generate shortcode.', 'info');
            if (navigator.clipboard?.writeText) {
                navigator.clipboard.writeText(text).then(() => { if (cb) cb(); showToast('Copied: ' + text, 'success'); });
            }
        };

        if (copyBtn && scSidebar) {
            copyBtn.addEventListener('click', () => doCopy(scSidebar.textContent.trim(), () => {
                if (copyBtn._copyTimer) clearTimeout(copyBtn._copyTimer);
                const orig = copyBtn.getAttribute('data-orig') || copyBtn.textContent;
                copyBtn.setAttribute('data-orig', orig);
                copyBtn.textContent = 'Copied!';
                copyBtn._copyTimer = setTimeout(() => {
                    copyBtn.textContent = orig;
                    copyBtn._copyTimer = null;
                }, 2000);
            }));
        }
        if (topBox && scTop) topBox.addEventListener('click', () => doCopy(scTop.textContent.trim()));
    }

    function showToast(message, type = 'info') {
        const toast = document.getElementById('studio-toast');
        if (!toast) return;
        toast.textContent = message;
        toast.className = `studio-toast visible toast-${type}`;
        toast.style.borderColor = (type === 'success') ? '#10b981' : ((type === 'danger') ? '#ef4444' : 'rgba(255,255,255,0.15)');
        setTimeout(() => toast.classList.remove('visible'), 3200);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return {
        updateHtmlFromEditor: updateHtmlFromEditor,
        updateCssFromEditor: updateCssFromEditor,
        updateJsFromEditor: updateJsFromEditor
    };
})();
