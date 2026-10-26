/**
 * Align Theme Builder - REST API Page & Template Save Handler
 *
 * @package AlignBuilder
 */

window.AlignBuilderModules = window.AlignBuilderModules || {};

window.AlignBuilderModules.WpSaveHandler = {
    isSaving: false,
    _lastCapturedSnapshot: null,
    _savedTimer: null,
    _dirtyTimer: null,
    _currentEditVersion: 0,
    _boundFrameDoc: null,
    _canvasListeners: null,

    cleanPreviewContent: function(html) {
        if (!html || typeof html !== 'string') return html;
        if (window.AlignBuilderModules?.AlignSerializer) {
            return window.AlignBuilderModules.AlignSerializer.serialize(html, { debug: false });
        }
        try {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            if (doc?.body) {
                doc.querySelectorAll('newsection, #vvvebjs-new-section, [data-vvveb-helpers], .vvveb-new-section-container, .vvveb-new-section-row, .vvveb-new-section-col, .vvveb-new-section-btn, #new-component, #new-section, #new-blank-section').forEach(el => el.remove());
                doc.querySelectorAll('[data-component-shortcode], [data-component-wp-block], .shortcode-preview-content, .block-preview-content').forEach(el => { el.innerHTML = ''; });
                doc.querySelectorAll('style[id^="style-"], style[id^="align-style-"], style#vvvebjs-styles, script[id^="script-"], script[id^="align-script-"], script#vvvebjs-scripts').forEach(el => {
                    if (!el.textContent?.trim()) el.remove();
                });
                doc.querySelectorAll('*').forEach(el => {
                    el.removeAttribute('data-preview-loading');
                    el.removeAttribute('bis_skin_checked');
                    el.removeAttribute('bis_register');
                });
                return /<html\b|<body\b/i.test(html) ? doc.documentElement.outerHTML : doc.body.innerHTML;
            }
        } catch (e) {
            console.error('[Align SaveHandler] cleanPreviewContent error:', e);
        }
        return html;
    },

    applyAutoLazyload: function(html) {
        if (!html || typeof html !== 'string') return html;
        try {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            if (doc?.body) {
                doc.querySelectorAll('img').forEach(function(img) {
                    img.removeAttribute('data-ll-status');
                    const loading = (img.getAttribute('loading') || '').toLowerCase();
                    const isEager = loading === 'eager' || img.getAttribute('fetchpriority') === 'high' || img.classList.contains('no-lazy') || img.classList.contains('ignorelazyload');
                    if (!img.hasAttribute('decoding')) img.setAttribute('decoding', 'async');
                    if (isEager) { img.setAttribute('loading', 'eager'); img.setAttribute('fetchpriority', 'high'); }
                    else if (!img.hasAttribute('loading')) img.setAttribute('loading', 'lazy');
                });
                doc.querySelectorAll('video').forEach(v => {
                    if (!v.classList.contains('no-lazy') && !v.classList.contains('ignorelazyload') && !v.hasAttribute('preload')) v.setAttribute('preload', 'none');
                });
                doc.querySelectorAll('iframe').forEach(f => {
                    if (!f.classList.contains('no-lazy') && !f.classList.contains('ignorelazyload') && !f.hasAttribute('loading')) f.setAttribute('loading', 'lazy');
                });
                return /<html\b|<body\b/i.test(html) ? doc.documentElement.outerHTML : doc.body.innerHTML;
            }
        } catch (e) {
            console.warn('[Align SaveHandler] applyAutoLazyload error:', e);
        }
        return html;
    },

    setButtonState: function(state, text) {
        if (this._savedTimer) {
            clearTimeout(this._savedTimer);
            this._savedTimer = null;
        }

        if (state === "dirty") {
            this._currentEditVersion = (this._currentEditVersion || 0) + 1;
            this.notifyDirtyDebounced();
        }

        document.querySelectorAll("#top-panel .save-btn, .save-btn").forEach(btn => {
            const loadingEl = btn.querySelector(".loading");
            const textEl = btn.querySelector(".button-text");

            if (state === "saving") {
                btn.setAttribute("disabled", "true");
                if (loadingEl) loadingEl.classList.remove("d-none");
                if (textEl) textEl.classList.add("d-none");
            } else if (state === "saved") {
                btn.removeAttribute("disabled");
                if (loadingEl) loadingEl.classList.add("d-none");
                if (textEl) {
                    textEl.classList.remove("d-none");
                    textEl.innerHTML = `<i class="la la-check me-1"></i><span>${text || 'Saved'}</span>`;
                }
                this._savedTimer = setTimeout(() => {
                    document.querySelectorAll("#top-panel .save-btn, .save-btn").forEach(b => {
                        b.removeAttribute("disabled");
                        b.querySelector(".loading")?.classList.add("d-none");
                        const bText = b.querySelector(".button-text");
                        if (bText) { bText.classList.remove("d-none"); bText.innerHTML = "<span>Save</span>"; }
                    });
                }, 1000);
            } else if (state === "dirty" || state === "error" || state === "clean") {
                btn.removeAttribute("disabled");
                if (loadingEl) loadingEl.classList.add("d-none");
                if (textEl) { textEl.classList.remove("d-none"); textEl.innerHTML = "<span>Save</span>"; }
            }
        });
    },

    notifyDirtyDebounced: function(delay = 300) {
        const self = this;
        const isEmbedded = (window.parent && window.parent !== window);
        if (!isEmbedded) return;

        if (self._dirtyTimer) clearTimeout(self._dirtyTimer);
        self._dirtyTimer = setTimeout(() => {
            self._dirtyTimer = null;
            try {
                const snapshot = self.captureSnapshotPayload();
                window.parent.postMessage({
                    type: 'ALIGN_BUILDER_DIRTY',
                    payload: snapshot
                }, window.location.origin);
            } catch (e) {
                console.error('[Align SaveHandler] Failed to capture dirty snapshot:', e);
            }
        }, delay);
    },

    bindCanvasEvents: function(frameDoc) {
        const self = this;
        if (!frameDoc?.body) return;
        self.unbindCanvasEvents();

        const frameBody = frameDoc.body;
        self._boundFrameDoc = frameDoc;
        const handler = () => self.setButtonState("dirty");
        self._canvasListeners = { target: frameBody, handler: handler };
        ['vvveb.undo.add', 'vvveb.undo.restore', 'input', 'change', 'paste', 'drop'].forEach(ev => frameBody.addEventListener(ev, handler));
    },

    unbindCanvasEvents: function() {
        const self = this;
        if (self._canvasListeners?.target) {
            const { target, handler } = self._canvasListeners;
            ['vvveb.undo.add', 'vvveb.undo.restore', 'input', 'change', 'paste', 'drop'].forEach(ev => target.removeEventListener(ev, handler));
            self._canvasListeners = null;
            self._boundFrameDoc = null;
        }
    },

    collectReusableOverrides: function(frameDoc) {
        let reusableOverrides = JSON.parse(JSON.stringify(window._alignActiveReuseOverrides || {}));
        if (!frameDoc) return reusableOverrides;
        const blockCounts = {};
        frameDoc.querySelectorAll('[data-component-shortcode], [data-component-wp-block]').forEach(blockWrapper => {
            let text = blockWrapper.getAttribute('data-shortcode') || blockWrapper.getAttribute('data-wp-block') || blockWrapper.textContent || '';
            const matchId = text.match(/id=["']?(\d+)["']?/i);
            const matchSlug = text.match(/\bslug=["']?([^"'\s\]]+)["']?/i);
            let blockId = matchId?.[1] || null;
            if (!blockId && matchSlug?.[1]) {
                const targetSlug = matchSlug[1].toLowerCase();
                const blocks = window.Vvveb?.WpReusableBlocks || window.VvvebWpConfig?.reusableBlocks;
                const found = Array.isArray(blocks) ? blocks.find(b => (b.slug?.toLowerCase() === targetSlug) || (b.name?.toLowerCase() === targetSlug)) : null;
                blockId = found ? String(found.id || found.post_id) : targetSlug;
            }
            if (!blockId) return;

            blockCounts[blockId] = (blockCounts[blockId] || 0) + 1;
            const matchInst = text.match(/\b(?:instance|instance_id|key)=["']?([a-zA-Z0-9_-]+)["']?/i);
            let instanceKey = blockWrapper.getAttribute('data-instance-id') || blockWrapper.getAttribute('data-instance') || matchInst?.[1] || (blockCounts[blockId] > 1 ? ('inst_' + blockCounts[blockId]) : 'default');
            blockWrapper.setAttribute('data-instance-id', instanceKey);
            if (instanceKey !== 'default' && (text.includes('[reuse-block') || text.includes('[align_block')) && !text.includes('instance=')) {
                text = text.replace(/\]$/, ` instance="${instanceKey}"]`);
                blockWrapper.setAttribute('data-shortcode', text);
                blockWrapper.textContent = text;
            }

            const fieldsObj = {};
            const targetScope = blockWrapper.shadowRoot || blockWrapper;
            targetScope.querySelectorAll('[data-overwrite-id], [data-allow-overwrite="true"]').forEach((el, index) => {
                const tagName = (el.tagName || '').toUpperCase();
                const ovId = el.getAttribute('data-overwrite-id') || el.id || ('ov-' + tagName.toLowerCase() + '-' + (index + 1));
                if (!ovId) return;
                if (['IMG', 'VIDEO', 'PICTURE', 'FIGURE'].includes(tagName)) {
                    fieldsObj[ovId] = { type: 'media', src: el.getAttribute('src') || el.src || '', srcset: el.getAttribute('srcset') || '', alt: el.getAttribute('alt') || '', width: el.getAttribute('width') || el.style.width || '', height: el.getAttribute('height') || el.style.height || '', align: el.getAttribute('align') || '' };
                } else if (tagName === 'BUTTON') {
                    fieldsObj[ovId] = { type: 'button', content: el.innerHTML || el.textContent || '' };
                } else if (tagName === 'A') {
                    fieldsObj[ovId] = { type: 'link', content: el.innerText || el.textContent || '', href: el.getAttribute('href') || '', target: el.getAttribute('target') || '', link_id: el.getAttribute('data-align-link-id') || '', link_title: el.getAttribute('data-align-link-title') || '' };
                } else {
                    fieldsObj[ovId] = { type: 'typography', content: el.innerHTML || el.textContent || '' };
                }
            });
            if (Object.keys(fieldsObj).length > 0) {
                reusableOverrides[blockId] = reusableOverrides[blockId] || {};
                reusableOverrides[blockId][instanceKey] = Object.assign({}, fieldsObj, reusableOverrides[blockId][instanceKey] || {});
            }
        });
        return reusableOverrides;
    },

    captureSnapshotPayload: function() {
        const wpConfig = window.VvvebWpConfig || {};

        if (window.Vvveb?.WysiwygEditor?.isActive) window.Vvveb.WysiwygEditor.destroy();

        if (window.Vvveb) {
            if (window.Vvveb.CodeEditor?.flush) window.Vvveb.CodeEditor.flush();
            if (window.Vvveb.CssEditor?.flush) window.Vvveb.CssEditor.flush();
            if (window.Vvveb.JsEditor?.flush) window.Vvveb.JsEditor.flush();
        }

        let activePostId = wpConfig.activePostId || null;
        let activeTpId = wpConfig.activeTpId || null;
        let activeTemplateType = null;

        if (window.Vvveb?.FileManager?.currentPage) {
            const curPageObj = window.Vvveb.FileManager.pages[window.Vvveb.FileManager.currentPage];
            if (curPageObj) {
                if (curPageObj.postId) { activePostId = curPageObj.postId; activeTpId = null; activeTemplateType = curPageObj.templateType || 'page'; }
                else if (curPageObj.tpId) { activeTpId = curPageObj.tpId; activePostId = null; activeTemplateType = curPageObj.templateType || (curPageObj.tpId.startsWith('tpl_') ? 'template' : 'template_part'); }
            }
        }

        const params = new URLSearchParams(window.location.search);
        if (!activePostId && !activeTpId) {
            const urlPostId = params.get('post_id') || params.get('postId') || wpConfig.activePostId || null;
            const urlTpId = params.get('tp_id') || params.get('pId') || wpConfig.activeTpId || null;
            if (urlTpId) {
                activeTpId = urlTpId;
                activeTemplateType = String(urlTpId).startsWith('tpl_') ? 'template' : 'template_part';
            } else if (urlPostId) {
                activePostId = urlPostId;
                activeTemplateType = wpConfig.activePostType || 'page';
            }
            console.log('[Align SaveHandler] Snapshot target resolved from URL/config fallback:', {
                postId: activePostId || null,
                tpId: activeTpId || null
            });
        }

        const frameBody = window.Vvveb?.Builder?.frameBody;
        const frameDoc = window.Vvveb?.Builder?.frameDoc || (frameBody ? frameBody.ownerDocument : null);
        const reusableOverrides = this.collectReusableOverrides(frameDoc);
        const serializer = window.AlignBuilderModules?.AlignSerializer;

        let htmlContent = '';
        if (frameBody && serializer) {
            htmlContent = serializer.serialize(frameBody, { debug: false });
        } else if (window.Vvveb?.Builder) {
            const rawHtml = window.Vvveb.Builder.getHtml() || '';
            htmlContent = serializer ? serializer.serialize(rawHtml, { debug: false }) : this.cleanPreviewContent(rawHtml);
        }

        if (htmlContent) {
            htmlContent = this.applyAutoLazyload(htmlContent);
            if (window.location.origin) {
                const originRegex = new RegExp(window.location.origin.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '/wp-content/', 'gi');
                htmlContent = htmlContent.replace(originRegex, '/wp-content/');
            }
        }

        let cssContent = '';
        if (window.Vvveb?.StyleManager?.getCss) cssContent = window.Vvveb.StyleManager.getCss();
        else if (window.Vvveb?.CssEditor?.getValue) cssContent = window.Vvveb.CssEditor.getValue();
        cssContent = (cssContent || '').trim();

        let jsContent = '';
        if (window.Vvveb?.JsManager?.getJs) jsContent = window.Vvveb.JsManager.getJs();
        else if (window.Vvveb?.JsEditor?.getValue) jsContent = window.Vvveb.JsEditor.getValue();
        jsContent = (jsContent || '').trim();

        const snapshot = { post_id: activePostId, tp_id: activeTpId, template_type: activeTemplateType, html: htmlContent, css: cssContent, js: jsContent, reusable_overrides: reusableOverrides, _capturedVersion: this._currentEditVersion || 0 };

        this._lastCapturedSnapshot = snapshot;
        return snapshot;
    },

    applyPostSaveSuccess: function(data, overrides, capturedVersion) {
        const self = this;
        let label = "Saved";
        if (data && data.action === 'publish') label = "Published";
        else if (data && (data.action === 'draft' || data.status === 'draft')) label = "Draft Saved";
        else if (data && data.status === 'publish') label = "Updated";

        const isStillDirty = (self._currentEditVersion > (capturedVersion ?? self._lastCapturedSnapshot?._capturedVersion ?? 0));
        if (!isStillDirty) {
            self.setButtonState("saved", label);
            if (window.Vvveb?.Undo?.reset) window.Vvveb.Undo.reset();
            if (window.Vvveb?.Builder) window.Vvveb.Builder.hasChanges = false;
        } else {
            self.setButtonState("dirty");
            self.notifyDirtyDebounced(100);
        }

        if (overrides) window._alignActiveReuseOverrides = overrides;
        if (window.AlignBuilderModules?.WpComponentsPreview) {
            window.AlignBuilderModules.WpComponentsPreview.clearCache();
            window.AlignBuilderModules.WpComponentsPreview.triggerWpComponentsPreviewInIframe(true);
        }
        if (window.AlignBuilderModules?.WpReuseBlockOverwrite) {
            window.AlignBuilderModules.WpReuseBlockOverwrite.fieldsCache = {};
            window.AlignBuilderModules.WpReuseBlockOverwrite._pendingFetches = {};
        }
        if (window.Vvveb) {
            if (window.Vvveb.CodeEditor?.codemirror && window.Vvveb.Builder) window.Vvveb.CodeEditor.setValue(window.Vvveb.Builder.getHtml(true, false));
            if (window.Vvveb.CssEditor?.codemirror && window.Vvveb.StyleManager) window.Vvveb.CssEditor.setValue(window.Vvveb.StyleManager.getCss(), false);
            if (window.Vvveb.JsEditor?.codemirror && window.Vvveb.JsManager) window.Vvveb.JsEditor.setValue(window.Vvveb.JsManager.getJs(), false);
        }
    },

    executeSave: function() {
        const self = this;
        const wpConfig = window.VvvebWpConfig || {};

        if (self.isSaving) {
            console.warn('[Align SaveHandler] Save already in flight, skipping executeSave.');
            return;
        }

        let payload;
        try {
            payload = self.captureSnapshotPayload();
        } catch (captureErr) {
            self.setButtonState("error");
            console.error('[Align SaveHandler] Failed to capture save payload:', captureErr);
            if (typeof displayToast === "function") displayToast("danger", "Error", "Failed to capture Builder content before save.");
            return;
        }

        self.isSaving = true;
        self.setButtonState("saving");
        console.log(`[Align SaveHandler] Saving to ${wpConfig.restSaveUrl} | Target: ${payload.tp_id || ('Post #' + payload.post_id)}`);

        if (typeof fetch !== 'function') {
            console.warn('[Align SaveHandler] fetch is not defined in current environment');
            self.isSaving = false;
            self.setButtonState("error");
            return;
        }

        fetch(wpConfig.restSaveUrl, {
            method: "POST",
            headers: { "Content-Type": "application/json", "X-WP-Nonce": wpConfig.restNonce },
            body: JSON.stringify(payload)
        })
        .then(res => res.text().then(text => {
            let data = null;
            try {
                data = text ? JSON.parse(text) : null;
            } catch (parseErr) {
                console.error('[Align SaveHandler] Non-JSON response | HTTP ' + res.status + ' | Body length=' + text.length, text.slice(0, 500));
            }
            if (!res.ok || !data) throw new Error(data?.message || `HTTP ${res.status} (empty or invalid JSON response, check PHP error log)`);
            return data;
        }))
        .then(data => {
            self.isSaving = false;
            if (data.status === "success" || data.post_id || data.tp_id) {
                self.applyPostSaveSuccess(data, payload.reusable_overrides, payload._capturedVersion);
                const successMsg = data.message || "Saved successfully";
                if (typeof displayToast === "function") displayToast("success", "Saved", successMsg);
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({ type: 'ALIGN_BUILDER_SAVE_SUCCESS', status: data.status, payload: payload }, window.location.origin);
                }
            } else {
                self.setButtonState("error");
                const errMsg = "Error saving: " + (data.message || "Unknown error");
                if (typeof displayToast === "function") displayToast("danger", "Error", errMsg);
                else console.error(errMsg);
            }
        })
        .catch(err => {
            self.isSaving = false;
            self.setButtonState("error");
            console.error("[Align SaveHandler] Save request error:", err);
            if (typeof displayToast === "function") displayToast("danger", "Error", "Failed to save to WordPress REST API.");
        });
    },

    init: function() {
        const self = this;
        const isEmbedded = (window.parent && window.parent !== window);

        const onIframeLoaded = (e) => {
            const doc = e?.detail || window.Vvveb?.Builder?.frameDoc || (window.Vvveb?.Builder?.frameBody ? window.Vvveb.Builder.frameBody.ownerDocument : null);
            self.bindCanvasEvents(doc);
        };
        window.addEventListener("Vvveb.iframe.loaded", onIframeLoaded);
        window.addEventListener("vvveb.iframe.loaded", onIframeLoaded);

        if (window.Vvveb?.Builder?.frameDoc || window.Vvveb?.Builder?.frameBody) {
            const doc = window.Vvveb?.Builder?.frameDoc || window.Vvveb.Builder.frameBody.ownerDocument;
            self.bindCanvasEvents(doc);
        }

        window.addEventListener("Vvveb.StyleManager.setStyle", () => self.setButtonState("dirty"));
        window.addEventListener("Vvveb.Builder.propertyChange", (event) => {
            console.log('[Align SaveHandler] Builder property changed; marking embedded snapshot dirty.', event.detail || {});
            self.setButtonState("dirty");
            if (window.Vvveb?.Builder?.refreshDomSnapshot) window.Vvveb.Builder.refreshDomSnapshot();
        });
        window.addEventListener("vvveb.undo.add", () => {
            self.setButtonState("dirty");
            if (window.Vvveb?.Builder?.refreshDomSnapshot) window.Vvveb.Builder.refreshDomSnapshot();
        });
        window.addEventListener("vvveb.undo.restore", () => {
            self.setButtonState("dirty");
            if (window.Vvveb?.Builder?.refreshDomSnapshot) window.Vvveb.Builder.refreshDomSnapshot();
        });

        document.querySelectorAll(".save-btn").forEach(btn => {
            btn.addEventListener("click", function(e) {
                e.preventDefault();
                e.stopPropagation();

                if (isEmbedded) {
                    try {
                        const snapshot = self.captureSnapshotPayload();
                        console.log('[Align SaveHandler] Embedded save requested through Gutenberg coordinator.', { version: snapshot._capturedVersion, htmlLength: (snapshot.html || '').length, cssLength: (snapshot.css || '').length, jsLength: (snapshot.js || '').length });
                        self.setButtonState("saving");
                        window.parent.postMessage({ type: 'ALIGN_BUILDER_REQUEST_SAVE', payload: snapshot }, window.location.origin);
                    } catch (captureErr) {
                        self.setButtonState("error");
                        console.error('[Align SaveHandler] Failed to capture embedded save payload:', captureErr);
                        if (typeof displayToast === "function") displayToast("danger", "Error", "Failed to capture Builder content before save.");
                    }
                    return;
                }

                self.executeSave();
            });
        });

        window.addEventListener("message", function(event) {
            if (event.origin !== window.location.origin || !event.data) return;

            if (event.data.type === "ALIGN_COORDINATOR_TRIGGER_DIRECT_SAVE" || event.data.type === "ALIGN_BUILDER_TRIGGER_SAVE") {
                console.log('[Align SaveHandler] Embedded save trigger received; requesting coordinated native save.');
                try {
                    const snapshot = self.captureSnapshotPayload();
                    self.setButtonState("saving");
                    window.parent.postMessage({ type: 'ALIGN_BUILDER_REQUEST_SAVE', payload: snapshot }, window.location.origin);
                } catch (captureErr) {
                    self.setButtonState("error");
                    console.error('[Align SaveHandler] Failed to capture coordinated save payload:', captureErr);
                }
            }

            if (event.data.type === "ALIGN_COORDINATOR_FLUSH_DIRTY") {
                if (self._dirtyTimer) {
                    clearTimeout(self._dirtyTimer);
                    self._dirtyTimer = null;
                    const snapshot = self.captureSnapshotPayload();
                    window.parent.postMessage({ type: 'ALIGN_BUILDER_DIRTY', payload: snapshot }, window.location.origin);
                }
            }

            if (event.data.type === "ALIGN_COORDINATOR_SAVING_STATE") {
                if (event.data.saving) self.setButtonState("saving");
            }

            if (event.data.type === "ALIGN_COORDINATOR_REQUEST_SNAPSHOT") {
                try {
                    const snapshot = self.captureSnapshotPayload();
                    console.log('[Align SaveHandler] Snapshot ready for coordinator save.', { requestId: event.data.requestId, version: snapshot._capturedVersion, htmlLength: (snapshot.html || '').length, cssLength: (snapshot.css || '').length, jsLength: (snapshot.js || '').length });
                    window.parent.postMessage({ type: 'ALIGN_COORDINATOR_SNAPSHOT_READY', requestId: event.data.requestId, payload: snapshot }, window.location.origin);
                } catch (captureErr) {
                    self.setButtonState("error");
                    console.error('[Align SaveHandler] Failed to capture requested snapshot:', captureErr);
                    window.parent.postMessage({ type: 'ALIGN_COORDINATOR_SNAPSHOT_READY', requestId: event.data.requestId, payload: null }, window.location.origin);
                }
            }

            if (event.data.type === "ALIGN_COORDINATOR_SAVE_ACK") {
                console.log('[Align SaveHandler] Received ALIGN_COORDINATOR_SAVE_ACK:', event.data);
                if (event.data.success) {
                    const overrides = self._lastCapturedSnapshot?.reusable_overrides;
                    const capturedVer = event.data.capturedVersion ?? self._lastCapturedSnapshot?._capturedVersion;
                    self.applyPostSaveSuccess(event.data, overrides, capturedVer);
                    if (typeof displayToast === "function") displayToast("success", "Saved", event.data.message || "Saved successfully");
                } else {
                    self.setButtonState("error");
                    const errMsg = event.data.message || 'Save failed';
                    if (typeof displayToast === "function") displayToast("danger", "Error", errMsg);
                }
            }
        });
    }
};
