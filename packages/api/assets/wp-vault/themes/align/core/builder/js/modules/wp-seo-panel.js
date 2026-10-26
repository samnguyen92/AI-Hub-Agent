/**
 * Align Builder - SEO Panel (RankMath Integration + Gemini AI)
 *
 * Adds a "SEO" tab in the Right Sidebar (page-level, shown when no element selected).
 * Reads/writes RankMath post meta via REST API.
 * Generates SEO content via Gemini AI based on page content + language.
 */
window.AlignBuilderModules = window.AlignBuilderModules || {};

window.AlignBuilderModules.WpSeoPanel = {
    postId: null,
    lang: 'en',
    seoData: {},
    _initialized: false,
    _panelEl: null,
    _tabEl: null,

    init: function() {
        if (this._initialized) return;
        this._initialized = true;

        const config = window.VvvebWpConfig || {};
        this.postId = config.activePostId || new URLSearchParams(window.location.search).get('post_id') || null;
        this.lang   = config.alignActiveLang || new URLSearchParams(window.location.search).get('lang') || config.alignDefaultLang || 'en';

        console.log('[Align SEO] Init | post_id:', this.postId, '| lang:', this.lang, '| RankMath:', config.rankMathActive);
        this._buildSeoTab();
        this._hookSelectionEvents();
    },

    // SEO Tab Injection into Right Panel
    _buildSeoTab: function() {
        const self = this;
        const panel = document.getElementById('seo-tab');
        const tabLink = document.getElementById('align-seo-tab-link');

        if (!panel) {
            setTimeout(function() { self._buildSeoTab(); }, 500);
            return;
        }

        if (!panel.querySelector('.align-seo-panel-wrap') || !panel.querySelector('#seo-post-title')) {
            panel.innerHTML = this._renderPanelHtml();
            this._panelEl = panel;
            this._bindPanelEvents();
        }

        if (tabLink && !tabLink.dataset.seoBound) {
            tabLink.dataset.seoBound = '1';
            tabLink.addEventListener('click', function(e) {
                if (!panel.querySelector('#seo-post-title')) {
                    panel.innerHTML = self._renderPanelHtml();
                    self._panelEl = panel;
                    self._bindPanelEvents();
                }
                const config = window.VvvebWpConfig || {};
                const currentPostId = self.postId || config.activePostId || new URLSearchParams(window.location.search).get('post_id');
                if (currentPostId) {
                    self.postId = currentPostId;
                    self.loadSeoMeta(currentPostId);
                }
            });
        }

        if (this.postId) {
            this.loadSeoMeta(this.postId);
        }

        console.log('[Align SEO] SEO tab connected to right panel inspector.');
    },

    _renderPanelHtml: function() {
        return `
        <div class="align-seo-panel-wrap">
            <div class="align-seo-header">
                <div class="align-seo-title">
                    <span class="align-seo-icon"><i class="la la-search-plus"></i></span>
                    <span>SEO Settings</span>
                </div>
                <div id="align-seo-plugin-badge" class="align-seo-badge"></div>
            </div>

            <!-- SEO Grade Score Card -->
            <div class="align-seo-score-card" id="align-seo-score-card">
                <div class="align-seo-score-header" id="align-seo-score-header" title="Click to view SEO checklist">
                    <div class="align-seo-score-badge poor" id="align-seo-score-badge">
                        <span class="score-num" id="align-seo-score-val">0</span>
                        <span class="score-total">/100</span>
                    </div>
                    <div class="align-seo-score-info">
                        <div class="score-status" id="align-seo-score-status"><span>Needs Work</span><span>0/100</span></div>
                        <div class="score-progress-bar"><div class="score-progress-fill poor" id="align-seo-score-fill" style="width:0%"></div></div>
                    </div>
                    <button type="button" class="align-seo-checklist-toggle" id="align-seo-checklist-toggle" aria-label="Toggle checklist">
                        <i class="la la-angle-down"></i>
                    </button>
                </div>
                <div class="align-seo-checklist" id="align-seo-checklist" style="display:none"></div>
            </div>

            <div class="align-seo-loading" id="align-seo-loading" style="display:none">
                <i class="la la-spinner la-spin"></i> Loading SEO data...
            </div>

            <div class="align-seo-fields" id="align-seo-fields">
                <div class="align-seo-field">
                    <label class="align-seo-label" for="seo-post-title"><i class="la la-font"></i> Page Title <span class="align-seo-counter" id="seo-post-title-count">0 chars</span></label>
                    <input type="text" id="seo-post-title" class="align-seo-input" placeholder="Page title in WordPress..." maxlength="120"/>
                </div>
                <div class="align-seo-field">
                    <label class="align-seo-label" for="seo-focus-keyword"><i class="la la-key"></i> Focus Keywords <span style="font-size:9.5px;color:#64748b;font-weight:400;margin-left:auto;">comma-separated</span></label>
                    <input type="text" id="seo-focus-keyword" class="align-seo-input" placeholder="Primary keyword, secondary keyword 1, secondary 2..." maxlength="200"/>
                </div>
                <div class="align-seo-field">
                    <label class="align-seo-label" for="seo-slug"><i class="la la-link"></i> URL Slug <span class="align-seo-counter" id="seo-slug-count">0 chars</span></label>
                    <input type="text" id="seo-slug" class="align-seo-input" placeholder="page-url-slug..." maxlength="120"/>
                </div>
                <div class="align-seo-field">
                    <label class="align-seo-label" for="seo-title"><i class="la la-heading"></i> SEO Title <span class="align-seo-counter" id="seo-title-count">0 / 60</span></label>
                    <input type="text" id="seo-title" class="align-seo-input" placeholder="SEO page title (50-60 chars)..." maxlength="120"/>
                    <div class="align-seo-serp-title" id="seo-serp-title-preview"></div>
                </div>
                <div class="align-seo-field">
                    <label class="align-seo-label" for="seo-description"><i class="la la-align-left"></i> Meta Description <span class="align-seo-counter" id="seo-desc-count">0 / 160</span></label>
                    <textarea id="seo-description" class="align-seo-input align-seo-textarea" rows="3" placeholder="Compelling meta description (140-160 chars)..." maxlength="320"></textarea>
                    <div class="align-seo-serp-desc" id="seo-serp-desc-preview"></div>
                </div>
                <div class="align-seo-field">
                    <label class="align-seo-label" for="seo-excerpt"><i class="la la-file-text-o"></i> Post Excerpt <span class="align-seo-counter" id="seo-excerpt-count">0 / 200</span></label>
                    <textarea id="seo-excerpt" class="align-seo-input align-seo-textarea" rows="2" placeholder="Brief summary / excerpt for this page..." maxlength="300"></textarea>
                </div>
                <div class="align-seo-divider"><span>Open Graph</span></div>
                <div class="align-seo-field">
                    <label class="align-seo-label" for="seo-og-title"><i class="la la-share-alt"></i> OG Title</label>
                    <input type="text" id="seo-og-title" class="align-seo-input" placeholder="Open Graph title..." maxlength="120"/>
                </div>
                <div class="align-seo-field">
                    <label class="align-seo-label" for="seo-og-description"><i class="la la-comment"></i> OG Description</label>
                    <textarea id="seo-og-description" class="align-seo-input align-seo-textarea" rows="2" placeholder="Open Graph description..." maxlength="300"></textarea>
                </div>
            </div>
            <!-- AI Generate & One-Click AI Fix -->
            <div class="align-seo-actions">
                <button id="align-seo-ai-btn" class="align-seo-btn align-seo-btn-ai" title="Generate SEO content with Gemini AI"><i class="la la-magic"></i><span>AI Generate</span></button>
                <button id="align-seo-fix-btn" class="align-seo-btn align-seo-btn-fix" title="AI Fix & Optimize: Generate best keywords, title & description and auto-save for 90+ score"><i class="la la-bolt"></i><span>AI Fix SEO</span></button>
                <button id="align-seo-save-btn" class="align-seo-btn align-seo-btn-save"><i class="la la-save"></i><span>Save SEO</span></button>
            </div>

            <!-- SERP Preview -->
            <div class="align-seo-serp-preview" id="align-seo-serp">
                <div class="serp-label">Google Preview</div>
                <div class="serp-card">
                    <div class="serp-url" id="serp-url-preview"></div>
                    <div class="serp-title" id="serp-title-preview">Page Title</div>
                    <div class="serp-desc" id="serp-desc-preview">Meta description will appear here...</div>
                </div>
            </div>
        </div>`;
    },

    loadSeoMeta: function(postId) {
        if (!postId) return;
        this.postId = postId;
        const config = window.VvvebWpConfig || {};
        const url = config.restSeoMetaUrl;
        if (!url) { console.error('[Align SEO] restSeoMetaUrl not found in VvvebWpConfig'); return; }

        this._setLoading(true);
        fetch(url + '&id=' + postId, {
            headers: { 'X-WP-Nonce': config.restNonce },
        })
        .then(r => r.json())
        .then(data => {
            this.seoData = data;
            this._fillFields(data);
            this._updateSerpPreview();
            this._updatePluginBadge(data);
            this._setLoading(false);
            console.log('[Align SEO] Loaded meta for post_id:', postId, '| title:', data.post_title, '| lang:', data.post_lang);
        })
        .catch(err => {
            this._setLoading(false);
            console.error('[Align SEO] Failed to load SEO meta:', err);
        });
    },

    _fillFields: function(data) {
        const f = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.value = val || '';
        };
        f('seo-post-title',    data.post_title || '');
        f('seo-focus-keyword', data.focus_keyword);
        let slug = data.slug || data.post_slug || '';
        try { slug = decodeURIComponent(slug); } catch(e) {}
        f('seo-slug',          slug);
        const si = document.getElementById('seo-slug');
        if (si) { si.readOnly = !!data.is_root_page; si.title = data.is_root_page ? 'Root Language / Homepage URL is locked' : ''; }
        f('seo-title',         data.title);
        f('seo-description',   data.description);
        f('seo-excerpt',       data.excerpt || data.post_excerpt || '');
        f('seo-og-title',      data.og_title);
        f('seo-og-description', data.og_description);
        this._updateCounters(data.seo_score || data.rank_math_seo_score);
    },

    _collectFields: function() {
        const g = (id) => (document.getElementById(id) || {}).value || '';
        return {
            post_title: g('seo-post-title'), focus_keyword: g('seo-focus-keyword'),
            slug: g('seo-slug'), title: g('seo-title'), description: g('seo-description'),
            excerpt: g('seo-excerpt'), og_title: g('seo-og-title'), og_description: g('seo-og-description'),
        };
    },

    save: function(silent, onComplete) {
        const self = this;
        if (!this.postId) {
            const config = window.VvvebWpConfig || {};
            this.postId = config.activePostId || new URLSearchParams(window.location.search).get('post_id') || null;
        }
        if (!this.postId) return;
        const config = window.VvvebWpConfig || {};
        const url = config.restSeoMetaUrl;
        if (!url) return;

        const btn = document.getElementById('align-seo-save-btn');
        if (btn && !silent) { btn.disabled = true; btn.innerHTML = '<i class="la la-spinner la-spin"></i> Saving...'; }

        const fields = this._collectFields();
        const scoreVal = parseInt(document.getElementById('align-seo-score-val')?.textContent || '0', 10);
        if (!isNaN(scoreVal) && scoreVal > 0) fields.seo_score = scoreVal;

        const payload = Object.assign({ post_id: this.postId }, fields);
        console.log('[Align SEO] Saving meta for post_id:', this.postId, '| title:', fields.post_title);

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.restNonce },
            body: JSON.stringify(payload),
        }).then(r => r.json()).then(data => {
            if (data && data.post_url) { self.seoData.post_url = data.post_url; self._updateSerpPreview(); }
            if (data && data.post_title) { self.seoData.post_title = data.post_title; }
            if (fields.post_title) {
                const newTitle = fields.post_title;
                const pages = window.Vvveb?.FileManager?.pages;
                if (pages) {
                    for (let k in pages) { if (parseInt(pages[k].postId || pages[k].post_id, 10) === parseInt(self.postId, 10)) { pages[k].title = newTitle; break; } }
                }
                document.querySelectorAll('#filemanager .tree li[data-page]').forEach(function(li) {
                    const p = window.Vvveb?.FileManager?.pages?.[li.dataset.page];
                    if (p && parseInt(p.postId || p.post_id, 10) === parseInt(self.postId, 10)) {
                        const lbl = li.querySelector('label');
                        if (lbl && lbl.childNodes[0]) lbl.childNodes[0].nodeValue = newTitle + ' ';
                    }
                });
            }
            // Push the JS-calculated score to Rank Math's own REST endpoint so Gutenberg always shows the same score.
            self._syncScoreToRankMath(scoreVal);

            if (!silent && typeof displayToast === 'function') displayToast('success', 'SEO Saved', 'Page title, slug & SEO meta updated.');
            console.log('[Align SEO] Saved fields:', data.saved, '| score synced to RankMath:', scoreVal);
            if (btn && !silent) {
                btn.disabled = false;
                btn.innerHTML = '<i class="la la-check"></i> Saved!';
                setTimeout(() => { if (btn) btn.innerHTML = '<i class="la la-save"></i> <span>Save SEO</span>'; }, 2000);
            }
            if (typeof onComplete === 'function') onComplete(data);
        }).catch(err => {
            console.error('[Align SEO] Save failed:', err);
            if (!silent && typeof displayToast === 'function') displayToast('danger', 'Save Failed', 'Could not save SEO meta.');
            if (btn && !silent) { btn.disabled = false; btn.innerHTML = '<i class="la la-save"></i> <span>Save SEO</span>'; }
            if (typeof onComplete === 'function') onComplete(null, err);
        });
    },

    _syncScoreToRankMath: function(score) {
        const config = window.VvvebWpConfig || {};
        const rmUrl = config.rankMathScoreUrl, rmNonce = config.rankMathNonce || config.restNonce;
        if (!rmUrl || !rmNonce || !this.postId || isNaN(score) || score <= 0) return;
        const body = { postScores: {} };
        body.postScores[this.postId] = score;
        fetch(rmUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rmNonce },
            body: JSON.stringify(body),
        }).then(r => r.json()).then(() => {
            console.log('[Align SEO] RankMath score synced | post_id:', this.postId, '| score:', score);
        }).catch(err => console.warn('[Align SEO] RankMath score sync failed:', err.message || err));
    },

    // -------------------------------------------------------------------------
    // AI Generate via Gemini
    // -------------------------------------------------------------------------

    aiGenerate: function(autoSave, onComplete) {
        if (!this.postId) return;
        const config = window.VvvebWpConfig || {};
        const url = config.restSeoGenerateUrl;
        if (!url) { console.error('[Align SEO] restSeoGenerateUrl not configured.'); return; }

        // Get page text from canvas iframe
        let pageText = '';
        try {
            const iframe = document.getElementById('iframe-layer') || document.getElementById('canvas-frame');
            if (iframe && iframe.contentDocument && iframe.contentDocument.body) {
                pageText = iframe.contentDocument.body.innerText || '';
            }
        } catch(e) {}

        const btn = document.getElementById('align-seo-ai-btn');
        if (btn && !autoSave) { btn.disabled = true; btn.innerHTML = '<i class="la la-spinner la-spin"></i> Generating...'; }

        const payload = {
            post_id: this.postId,
            lang:    this.lang,
            content: (pageText || '').trim(),
        };

        console.log('[Align SEO] AI Generate | post_id:', this.postId, '| lang:', this.lang, '| content_len:', payload.content.length);

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.restNonce },
            body: JSON.stringify(payload),
        })
        .then(r => r.json())
        .then(data => {
            if (btn && !autoSave) { btn.disabled = false; btn.innerHTML = '<i class="la la-magic"></i> <span>AI Generate</span>'; }

            if (data.success && data.generated) {
                const g = data.generated;
                if (g.page_title) this._setField('seo-post-title', g.page_title);
                this._setField('seo-focus-keyword', g.focus_keyword);
                this._setField('seo-title',         g.title);
                this._setField('seo-description',   g.description);
                if (g.excerpt) this._setField('seo-excerpt', g.excerpt);
                this._setField('seo-og-title',      g.og_title);
                this._setField('seo-og-description', g.og_description);
                this._updateCounters();
                this._updateSerpPreview();

                if (autoSave) {
                    this.save(true, () => {
                        const newScore = document.getElementById('align-seo-score-val')?.textContent || '';
                        if (typeof displayToast === 'function') {
                            displayToast('success', 'AI SEO Fixed & Saved', 'SEO score optimized to ' + newScore + '/100 and saved.');
                        }
                        if (typeof onComplete === 'function') onComplete(data);
                    });
                } else {
                    if (typeof displayToast === 'function') displayToast('success', 'AI Generated', 'Review and save when ready.');
                    if (typeof onComplete === 'function') onComplete(data);
                }
                console.log('[Align SEO] AI generated | keyword:', g.focus_keyword);
            } else {
                const msg = (data && data.message) || 'AI generation failed.';
                if (typeof displayToast === 'function') displayToast('danger', 'AI Error', msg);
                console.error('[Align SEO] AI generate error:', msg);
                if (typeof onComplete === 'function') onComplete(null, msg);
            }
        })
        .catch(err => {
            if (btn && !autoSave) { btn.disabled = false; btn.innerHTML = '<i class="la la-magic"></i> <span>AI Generate</span>'; }
            console.error('[Align SEO] AI Generate network error:', err);
            if (typeof displayToast === 'function') displayToast('danger', 'Network Error', 'Failed to connect to AI service.');
            if (typeof onComplete === 'function') onComplete(null, err);
        });
    },

    _setField: function(id, val) {
        const el = document.getElementById(id);
        if (el) el.value = val || '';
    },

    _setLoading: function(loading) {
        const el = document.getElementById('align-seo-loading');
        const fields = document.getElementById('align-seo-fields');
        if (el) el.style.display = loading ? 'flex' : 'none';
        if (fields) fields.style.opacity = loading ? '0.4' : '1';
    },

    _updatePluginBadge: function(data) {
        const badge = document.getElementById('align-seo-plugin-badge');
        if (!badge) return;
        if (data.rankmath_active) {
            badge.textContent = data.seo_score ? `RankMath: ${data.seo_score}/100` : 'RankMath';
            badge.className = 'align-seo-badge align-seo-badge-rankmath';
        } else if (data.seopress_active) {
            badge.textContent = 'SEOPress';
            badge.className = 'align-seo-badge align-seo-badge-seopress';
        } else {
            badge.textContent = 'No SEO Plugin';
            badge.className = 'align-seo-badge align-seo-badge-none';
        }
    },

    _updateCounters: function(initialScore) {
        const updateLen = (id, countId, max, goodMin) => {
            const el = document.getElementById(id);
            const cnt = document.getElementById(countId);
            if (!el || !cnt) return;
            const len = (el.value || '').length;
            cnt.textContent = max ? `${len} / ${max}` : `${len} chars`;
            if (max) cnt.className = 'align-seo-counter' + (len > max ? ' over' : (len >= (goodMin || max) ? ' good' : ''));
        };
        updateLen('seo-post-title', 'seo-post-title-count', 0);
        updateLen('seo-title', 'seo-title-count', 60, 50);
        updateLen('seo-description', 'seo-desc-count', 160, 140);
        updateLen('seo-slug', 'seo-slug-count', 0);
        updateLen('seo-excerpt', 'seo-excerpt-count', 200, 0);
        this._updateScore(initialScore);
    },

    _updateScore: function(initialScore) {
        if (!window.AlignBuilderModules?.WpSeoScorer) return;
        const fields = this._collectFields();
        fields.post_url = this.seoData.post_url || window.location.href;
        window.AlignBuilderModules.WpSeoScorer.updateDom(fields, initialScore);
    },

    _updateSerpPreview: function() {
        const title   = (document.getElementById('seo-title')?.value || '').trim() || (this.seoData.post_title || 'Page Title');
        const desc    = (document.getElementById('seo-description')?.value || '').trim() || 'Meta description will appear here...';
        const slugVal = (document.getElementById('seo-slug')?.value || '').trim();
        let url       = this.seoData.post_url || window.location.origin;
        if (slugVal && url) {
            try {
                const u = new URL(url);
                const parts = u.pathname.split('/').filter(Boolean);
                if (parts.length > 0) parts[parts.length - 1] = slugVal;
                else parts.push(slugVal);
                u.pathname = '/' + parts.join('/') + '/';
                url = u.toString();
            } catch(e) {}
        }

        const tEl = document.getElementById('serp-title-preview'), dEl = document.getElementById('serp-desc-preview'), uEl = document.getElementById('serp-url-preview');
        if (tEl) tEl.textContent = title.substring(0, 70);
        if (dEl) dEl.textContent = desc.substring(0, 180);
        if (uEl) uEl.textContent = url;
    },

    _bindPanelEvents: function() {
        const self = this;
        document.addEventListener('click', function(e) {
            if (e.target.closest('#align-seo-score-header')) {
                const c = document.getElementById('align-seo-score-card'), l = document.getElementById('align-seo-checklist');
                if (c && l) l.style.display = c.classList.toggle('open') ? 'flex' : 'none';
            }
            const grpHdr = e.target.closest('.align-seo-group-header');
            if (grpHdr) grpHdr.closest('.align-seo-group')?.classList.toggle('open');
            if (e.target.closest('#align-seo-save-btn')) { e.preventDefault(); self.save(); }
            if (e.target.closest('#align-seo-ai-btn')) { e.preventDefault(); self.aiGenerate(); }
            if (e.target.closest('#align-seo-fix-btn')) {
                e.preventDefault();
                const fixBtn = document.getElementById('align-seo-fix-btn');
                if (fixBtn) { fixBtn.disabled = true; fixBtn.innerHTML = '<i class="la la-spinner la-spin"></i> Fixing...'; }
                self.aiGenerate(true, () => { if (fixBtn) { fixBtn.disabled = false; fixBtn.innerHTML = '<i class="la la-bolt"></i> <span>AI Fix SEO</span>'; } });
            }
        });

        ['seo-post-title', 'seo-title', 'seo-description', 'seo-slug', 'seo-excerpt', 'seo-og-title', 'seo-og-description', 'seo-focus-keyword'].forEach(function(id) {
            document.addEventListener('input', function(e) {
                if (e.target && e.target.id === id) {
                    self._updateCounters();
                    self._updateSerpPreview();
                }
            });
        });
    },

    _hookSelectionEvents: function() {
        const self = this;
        // When no element is selected (page-level), show SEO tab; otherwise, let it be
        document.addEventListener('Vvveb.PageUnselect', function() {
            if (self.postId) self._showSeoTab();
        });
        document.addEventListener('Vvveb.NodeSelected', function() {
            // SEO tab still accessible, just not auto-triggered on node select
        });
    },

    _showSeoTab: function() {
        const tab = document.getElementById('align-seo-tab-link');
        if (tab) tab.click();
    },
};

// Auto-init
document.addEventListener('DOMContentLoaded', function() {
    if (window.AlignBuilderModules && window.AlignBuilderModules.WpSeoPanel) {
        window.AlignBuilderModules.WpSeoPanel.init();
    }
});
