/**
 * Align Theme Builder - AI Translator & Content Sync Module
 *
 * Provides "Copy from Original" and AI Translation controls in Align Builder:
 * 1. Top language bar Copy Original button
 * 2. Right panel AI Translation & Sync card
 * 3. 1-Click Copy & Translate pipeline
 *
 * @package Align
 */

window.AlignBuilderModules = window.AlignBuilderModules || {};

window.AlignBuilderModules.WpBuilderTranslator = {
    postId: null,
    tpId: null,
    activeLang: 'en',
    defaultLang: 'en',
    sourceId: 0,
    sourceTitle: '',
    _initialized: false,

    init: function() {
        if (this._initialized) return;
        this._initialized = true;

        const config = window.VvvebWpConfig || {};
        this.postId      = config.activePostId || new URLSearchParams(window.location.search).get('post_id') || null;
        this.tpId        = config.activeTpId || new URLSearchParams(window.location.search).get('tp_id') || new URLSearchParams(window.location.search).get('pId') || null;
        this.activeLang  = config.alignActiveLang || new URLSearchParams(window.location.search).get('lang') || config.alignDefaultLang || 'en';
        this.defaultLang = config.alignDefaultLang || 'en';
        this.sourceId    = config.alignSourceId || 0;
        this.sourceTitle = config.alignSourceTitle || '';

        // If on template/part and activeLang not explicit, infer from slug suffix (-zh, -vi, etc.)
        if (this.tpId && (!this.activeLang || this.activeLang === this.defaultLang)) {
            const m = String(this.tpId).match(/-([a-z]{2,5})$/i);
            if (m && m[1] && m[1].toLowerCase() !== this.defaultLang) {
                this.activeLang = m[1].toLowerCase();
            }
        }

        // If sourceId is not in config, find it from translations list
        if (!this.sourceId && config.alignTranslations && Array.isArray(config.alignTranslations)) {
            for (let i = 0; i < config.alignTranslations.length; i++) {
                const tr = config.alignTranslations[i];
                if (tr.lang === this.defaultLang && parseInt(tr.post_id, 10) !== parseInt(this.postId, 10)) {
                    this.sourceId = parseInt(tr.post_id, 10);
                    break;
                }
            }
        }

        console.log('[Align WpBuilderTranslator] Init | post_id:', this.postId, '| tp_id:', this.tpId, '| lang:', this.activeLang, '| source_id:', this.sourceId);

        this._injectTopBarButton();
        this._injectRightPanelCard();
        this._bindEvents();
    },

    // -------------------------------------------------------------------------
    // Top Bar "Copy Original" button injection
    // -------------------------------------------------------------------------

    _injectTopBarButton: function() {
        const self = this;
        const checkSwitcher = () => {
            const tabs = document.querySelector('#align-lang-switcher .align-lang-tabs');
            if (!tabs) {
                setTimeout(checkSwitcher, 400);
                return;
            }

            if (document.getElementById('align-ai-copy-orig-btn')) return;

            const isTranslated = self.activeLang && self.activeLang !== self.defaultLang;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.id = 'align-ai-copy-orig-btn';
            btn.className = 'align-ai-copy-orig-btn';
            btn.title = self.sourceTitle
                ? `Copy layout & content from English original: "${self.sourceTitle}" (#${self.sourceId})`
                : 'Copy layout & content from English original page';
            btn.innerHTML = '<i class="la la-copy"></i> <span>Copy Original</span>';

            if (!isTranslated) {
                btn.style.display = 'none';
            }

            // Insert before AI Translate button
            const aiBtn = tabs.querySelector('#align-ai-translate-page-btn');
            if (aiBtn) {
                tabs.insertBefore(btn, aiBtn);
            } else {
                tabs.appendChild(btn);
            }
        };

        checkSwitcher();
    },

    // -------------------------------------------------------------------------
    // Right Panel "Language & Translation" Card injection
    // -------------------------------------------------------------------------

    _injectRightPanelCard: function() {
        const self = this;
        const checkPanel = () => {
            const seoWrap = document.querySelector('#seo-tab .align-seo-panel-wrap');
            if (!seoWrap) {
                setTimeout(checkPanel, 500);
                return;
            }

            if (document.getElementById('align-seo-translation-card')) return;

            const isTranslated = self.activeLang && self.activeLang !== self.defaultLang;
            const card = document.createElement('div');
            card.id = 'align-seo-translation-card';
            card.className = 'align-seo-translation-card' + (isTranslated ? ' is-translated' : ' is-default-lang');

            const origLabel = self.sourceTitle
                ? `<strong>${self.sourceTitle}</strong> <span class="align-trans-id">(#${self.sourceId})</span>`
                : (isTranslated ? '<span class="text-muted">Original English Page</span>' : '<span class="text-muted">This is the default English page</span>');

            card.innerHTML = `
                <div class="align-trans-card-header">
                    <div class="align-trans-card-title">
                        <i class="la la-language"></i>
                        <span>AI Translation & Sync</span>
                    </div>
                    <span class="align-trans-lang-tag ${isTranslated ? 'zh' : 'en'}">${self.activeLang.toUpperCase()}</span>
                </div>
                <div class="align-trans-card-body">
                    <div class="align-trans-meta">
                        <span class="meta-label">Linked Original:</span>
                        <span class="meta-val">${origLabel}</span>
                    </div>
                    ${isTranslated ? `
                    <div class="align-trans-btn-grid">
                        <button type="button" class="align-trans-btn align-trans-btn-copy" id="align-panel-copy-orig-btn" title="Copy original layout and content from English version">
                            <i class="la la-copy"></i> <span>Copy from Original (EN)</span>
                        </button>
                        <button type="button" class="align-trans-btn align-trans-btn-trans" id="align-panel-translate-canvas-btn" title="Translate canvas text to Chinese with AI">
                            <i class="la la-magic"></i> <span>AI Translate Canvas</span>
                        </button>
                        <button type="button" class="align-trans-btn align-trans-btn-sync" id="align-panel-sync-translate-btn" title="1-Click: Copy original English then auto-translate to Chinese">
                            <i class="la la-bolt"></i> <span>1-Click Copy & Translate</span>
                        </button>
                    </div>` : `
                    <div class="align-trans-default-notice">
                        <i class="la la-info-circle"></i>
                        <span>You are editing the default English page. Switch to Chinese (ZH) in the top bar to translate or sync content.</span>
                    </div>`}
                </div>`;

            // Insert at the top of the SEO panel, before score card
            const scoreCard = seoWrap.querySelector('#align-seo-score-card');
            if (scoreCard) {
                seoWrap.insertBefore(card, scoreCard);
            } else {
                seoWrap.insertBefore(card, seoWrap.firstChild);
            }
        };

        checkPanel();
    },

    // -------------------------------------------------------------------------
    // Event Binding
    // -------------------------------------------------------------------------

    _bindEvents: function() {
        const self = this;

        document.addEventListener('click', function(e) {
            if (e.target.closest('#align-ai-copy-orig-btn') || e.target.closest('#align-panel-copy-orig-btn')) {
                e.preventDefault();
                self.copyFromOriginal();
            }

            if (e.target.closest('#align-panel-translate-canvas-btn')) {
                e.preventDefault();
                self.translateCanvas();
            }

            if (e.target.closest('#align-panel-sync-translate-btn')) {
                e.preventDefault();
                self.copyAndTranslate();
            }
        });
    },

    // -------------------------------------------------------------------------
    // Helper: Resolve Active Target (Post vs Template Part)
    // -------------------------------------------------------------------------

    resolveActiveTarget: function() {
        const config = window.VvvebWpConfig || {};
        let activePostId = this.postId || config.activePostId || (new URLSearchParams(window.location.search)).get('post_id') || null;
        let activeTpId   = this.tpId || config.activeTpId || (new URLSearchParams(window.location.search)).get('tp_id') || (new URLSearchParams(window.location.search)).get('pId') || null;

        if (window.Vvveb?.FileManager?.currentPage) {
            const curPageObj = window.Vvveb.FileManager.pages[window.Vvveb.FileManager.currentPage];
            if (curPageObj) {
                if (curPageObj.postId) {
                    activePostId = curPageObj.postId;
                    activeTpId   = null;
                } else if (curPageObj.tpId) {
                    activeTpId   = curPageObj.tpId;
                    activePostId = null;
                }
            }
        }

        return {
            postId: (activePostId && parseInt(activePostId, 10) > 0) ? parseInt(activePostId, 10) : null,
            tpId: activeTpId ? String(activeTpId) : null
        };
    },

    // -------------------------------------------------------------------------
    // Action 1: Copy from Original
    // -------------------------------------------------------------------------

    copyFromOriginal: function(onSuccess) {
        const self = this;
        const config = window.VvvebWpConfig || {};
        const target = this.resolveActiveTarget();

        if (!target.postId && !target.tpId) {
            const warnMsg = 'Cannot detect active post or template part.';
            if (typeof displayToast === 'function') {
                displayToast('danger', 'Copy Failed', warnMsg);
            } else {
                alert('Copy Failed: ' + warnMsg);
            }
            return;
        }

        let sourceLabel = 'the original English page';
        if (this.sourceTitle) {
            sourceLabel = `"${this.sourceTitle}" (#${this.sourceId})`;
        } else if (target.tpId) {
            const cleanTp = target.tpId.replace(/^t(p|pl)_/, '');
            const tpSlug  = cleanTp.includes('//') ? cleanTp.split('//')[1] : cleanTp;
            const srcSlug = tpSlug.replace(/-[a-z]{2,5}$/i, '');
            sourceLabel = `English original template "${srcSlug}"`;
        }

        const msg = `Copy layout and content from ${sourceLabel}?\n\n`
                  + `• All blocks, styles, classes, and layouts will be synced from the English version.\n`
                  + `• Your current canvas content will be replaced.\n\n`
                  + `Click OK to proceed.`;

        if (!window.confirm(msg)) return;

        const copyUrl = config.restCopyOriginalPostUrl;
        if (!copyUrl) {
            alert('Copy Original API endpoint not configured.');
            return;
        }

        const setButtonsLoading = (loading) => {
            ['align-ai-copy-orig-btn', 'align-panel-copy-orig-btn', 'align-panel-sync-translate-btn'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.disabled = loading;
                    if (loading) el.dataset.origHtml = el.innerHTML;
                    el.innerHTML = loading ? '<i class="la la-spinner la-spin"></i> Copying...' : (el.dataset.origHtml || el.innerHTML);
                }
            });
        };

        setButtonsLoading(true);
        if (typeof displayToast === 'function') {
            displayToast('info', 'Copying Content', 'Copying original layout and content from English version...');
        }

        const payload = {};
        if (target.postId) payload.post_id = target.postId;
        if (target.tpId)   payload.tp_id   = target.tpId;

        fetch(copyUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.restNonce },
            body: JSON.stringify(payload),
        })
        .then(r => r.json())
        .then(data => {
            setButtonsLoading(false);
            if (data && data.success) {
                console.log('[Align WpBuilderTranslator] Content copied successfully:', data);
                if (typeof onSuccess === 'function') {
                    onSuccess(data);
                    return;
                }
                if (typeof displayToast === 'function') {
                    displayToast('success', 'Content Copied', 'Original layout and content copied. Reloading builder...');
                }
                setTimeout(() => window.location.reload(), 1200);
            } else {
                const errMsg = (data && data.message) || 'Failed to copy original content.';
                console.error('[Align WpBuilderTranslator] Copy error:', errMsg);
                if (typeof displayToast === 'function') {
                    displayToast('danger', 'Copy Failed', errMsg);
                } else {
                    alert('Copy Failed: ' + errMsg);
                }
            }
        })
        .catch(err => {
            setButtonsLoading(false);
            console.error('[Align WpBuilderTranslator] Network error:', err);
            if (typeof displayToast === 'function') {
                displayToast('danger', 'Network Error', 'Failed to reach copy endpoint.');
            }
        });
    },

    // -------------------------------------------------------------------------
    // Action 2: Translate Canvas
    // -------------------------------------------------------------------------

    translateCanvas: function() {
        if (window.AlignBuilderModules?.WpCanvasTranslator) {
            window.AlignBuilderModules.WpCanvasTranslator.translateCanvas();
        } else {
            console.warn('[Align WpBuilderTranslator] WpCanvasTranslator not loaded.');
        }
    },

    // -------------------------------------------------------------------------
    // Action 3: 1-Click Copy & Translate
    // -------------------------------------------------------------------------

    copyAndTranslate: function() {
        const self = this;
        const config = window.VvvebWpConfig || {};

        const sourceLabel = this.sourceTitle ? `"${this.sourceTitle}"` : 'English original';
        const msg = `1-Click Sync & Translate:\n\n`
                  + `1. Copy full layout & blocks from ${sourceLabel}.\n`
                  + `2. Automatically translate all text content to ${this.activeLang.toUpperCase()} using AI.\n\n`
                  + `Click OK to start.`;

        if (!window.confirm(msg)) return;

        const syncBtn = document.getElementById('align-panel-sync-translate-btn');
        if (syncBtn) {
            syncBtn.disabled = true;
            syncBtn.innerHTML = '<i class="la la-spinner la-spin"></i> Syncing...';
        }

        // Step 1: Copy original
        this.copyFromOriginal(function(copyResult) {
            if (syncBtn) {
                syncBtn.innerHTML = '<i class="la la-spinner la-spin"></i> Translating with AI...';
            }
            if (typeof displayToast === 'function') {
                displayToast('info', 'Translating', 'Content copied. Translating text to ' + self.activeLang.toUpperCase() + ' with AI...');
            }

            // Step 2: Translate post or template on server
            const transUrl = config.restTranslatePostUrl;
            if (!transUrl) {
                window.location.reload();
                return;
            }

            const target = self.resolveActiveTarget();
            const transPayload = {
                post_id: target.postId || 0,
                tp_id: target.tpId || '',
                target_lang: self.activeLang,
            };

            fetch(transUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.restNonce },
                body: JSON.stringify(transPayload),
            })
            .then(r => r.json())
            .then(transResult => {
                if (syncBtn) syncBtn.disabled = false;
                if (transResult && transResult.success) {
                    if (typeof displayToast === 'function') {
                        displayToast('success', 'Translation Complete', 'Page copied and translated. Reloading builder...');
                    }
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    const err = (transResult && transResult.message) || 'Translation step failed.';
                    console.warn('[Align WpBuilderTranslator] Translate step error:', err);
                    if (typeof displayToast === 'function') {
                        displayToast('warning', 'Content Copied (Translation Warning)', err + ' Reloading canvas.');
                    }
                    setTimeout(() => window.location.reload(), 1500);
                }
            })
            .catch(err => {
                if (syncBtn) syncBtn.disabled = false;
                console.error('[Align WpBuilderTranslator] Server translate error:', err);
                setTimeout(() => window.location.reload(), 1000);
            });
        });
    },
};

// Auto-init on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    if (window.AlignBuilderModules?.WpBuilderTranslator) {
        window.AlignBuilderModules.WpBuilderTranslator.init();
    }
});
