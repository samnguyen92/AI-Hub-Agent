/**
 * Align Theme Builder - Pure Side-by-Side Revision Comparison Studio
 * Unified Single Master Scroll Controller (100% Lockstep Synchronization)
 *
 * @package AlignBuilder
 */

window.AlignBuilderModules = window.AlignBuilderModules || {};

window.AlignBuilderModules.WpRevisionHandler = {
    revisions: [],
    leftRev: null,
    rightRev: null,
    singleRev: null,
    viewMode: 'split',
    syncScrollEnabled: true,
    activePostId: null,
    activeTpId: null,
    masterScrollY: 0,
    isDraggingThumb: false,
    dragStartY: 0,
    dragStartScrollY: 0,

    init: function() {
        const self = this;
        const modalEl = document.getElementById('revisionsModal');
        if (!modalEl) return;

        modalEl.addEventListener('show.bs.modal', () => {
            self.masterScrollY = 0;
            self.setMode('split');
            self.fetchRevisions();
        });

        modalEl.addEventListener('hidden.bs.modal', () => {
            ['rev-split-left-iframe', 'rev-split-right-iframe', 'rev-single-iframe'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.src = 'about:blank';
            });
        });

        document.getElementById('rev-mode-single-btn')?.addEventListener('click', (e) => {
            e.preventDefault();
            self.setMode('single');
        });

        document.getElementById('rev-mode-split-btn')?.addEventListener('click', (e) => {
            e.preventDefault();
            self.setMode('split');
        });

        const syncScrollBtn = document.getElementById('rev-sync-scroll-btn');
        syncScrollBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            self.syncScrollEnabled = !self.syncScrollEnabled;
            syncScrollBtn.classList.toggle('active', self.syncScrollEnabled);
            syncScrollBtn.classList.toggle('btn-outline-info', self.syncScrollEnabled);
            syncScrollBtn.classList.toggle('btn-outline-secondary', !self.syncScrollEnabled);
            const track = document.getElementById('rev-master-scrollbar');
            if (track) track.style.display = self.syncScrollEnabled ? 'block' : 'none';
        });

        document.getElementById('rev-select-left')?.addEventListener('change', function() {
            const found = self.revisions.find(r => r.id === parseInt(this.value, 10));
            if (found) self.setLeftRevision(found);
        });

        document.getElementById('rev-select-right')?.addEventListener('change', function() {
            const found = self.revisions.find(r => r.id === parseInt(this.value, 10));
            if (found) self.setRightRevision(found);
        });

        document.getElementById('rev-select-single')?.addEventListener('change', function() {
            const found = self.revisions.find(r => r.id === parseInt(this.value, 10));
            if (found) self.setSingleRevision(found);
        });

        document.getElementById('rev-apply-restore-btn')?.addEventListener('click', (e) => {
            e.preventDefault();
            const targetRev = (self.viewMode === 'split') ? self.rightRev : self.singleRev;
            if (!targetRev || targetRev.is_current) return;
            self.restoreRevision(targetRev.id);
        });

        self.bindMasterScrollbar();
        console.log('[Align RevisionHandler] Unified Single-Scroll Studio initialized');
    },

    setMode: function(mode) {
        const self = this;
        self.viewMode = mode;
        const singleBtn = document.getElementById('rev-mode-single-btn');
        const splitBtn = document.getElementById('rev-mode-split-btn');
        const splitContainer = document.getElementById('rev-split-preview-container');
        const singleContainer = document.getElementById('rev-single-preview-container');
        const syncScrollBtn = document.getElementById('rev-sync-scroll-btn');
        const scrollbar = document.getElementById('rev-master-scrollbar');

        if (mode === 'single') {
            splitBtn?.classList.remove('active', 'text-light');
            splitBtn?.classList.add('text-secondary');
            singleBtn?.classList.remove('text-secondary');
            singleBtn?.classList.add('active', 'text-light');

            if (splitContainer) {
                splitContainer.classList.remove('d-flex');
                splitContainer.classList.add('d-none');
            }
            if (singleContainer) {
                singleContainer.style.removeProperty('display');
                singleContainer.classList.remove('d-none');
                singleContainer.classList.add('d-flex');
            }

            syncScrollBtn?.classList.add('d-none');
            if (scrollbar) scrollbar.style.display = 'none';

            if (self.rightRev) self.setSingleRevision(self.rightRev);
            else if (self.leftRev) self.setSingleRevision(self.leftRev);
        } else {
            singleBtn?.classList.remove('active', 'text-light');
            singleBtn?.classList.add('text-secondary');
            splitBtn?.classList.remove('text-secondary');
            splitBtn?.classList.add('active', 'text-light');

            if (singleContainer) {
                singleContainer.classList.remove('d-flex');
                singleContainer.classList.add('d-none');
            }
            if (splitContainer) {
                splitContainer.style.removeProperty('display');
                splitContainer.classList.remove('d-none');
                splitContainer.classList.add('d-flex');
            }

            syncScrollBtn?.classList.remove('d-none');
            if (scrollbar) scrollbar.style.display = self.syncScrollEnabled ? 'block' : 'none';

            if (self.leftRev) self.loadLeftFrame();
            if (self.rightRev) self.loadRightFrame();
        }
        self.updateRestoreButton();
    },

    resolveActiveEntity: function() {
        const wpConfig = window.VvvebWpConfig || {};
        let activePostId = wpConfig.activePostId || null;
        let activeTpId = wpConfig.activeTpId || null;
        let activeTitle = 'Current Page';

        if (window.Vvveb?.FileManager?.currentPage) {
            const curPageObj = window.Vvveb.FileManager.pages[window.Vvveb.FileManager.currentPage];
            if (curPageObj) {
                if (curPageObj.postId) {
                    activePostId = curPageObj.postId;
                    activeTpId = null;
                } else if (curPageObj.tpId) {
                    activeTpId = curPageObj.tpId;
                    activePostId = null;
                }
                activeTitle = curPageObj.title || curPageObj.name || activeTitle;
            }
        }

        this.activePostId = activePostId;
        this.activeTpId = activeTpId;

        const titleBadge = document.getElementById('rev-item-title-badge');
        if (titleBadge) {
            titleBadge.textContent = activeTitle + (activeTpId ? ` (${activeTpId})` : ` (#${activePostId})`);
        }

        return { activePostId, activeTpId, activeTitle };
    },

    fetchRevisions: function() {
        const self = this;
        const wpConfig = window.VvvebWpConfig || {};
        if (!wpConfig.restRevisionsUrl) return;

        const { activePostId, activeTpId } = this.resolveActiveEntity();

        let fetchUrl = wpConfig.restRevisionsUrl + (wpConfig.restRevisionsUrl.includes('?') ? '&' : '?');
        if (activeTpId) fetchUrl += 'tp_id=' + encodeURIComponent(activeTpId);
        else if (activePostId) fetchUrl += 'post_id=' + encodeURIComponent(activePostId);
        fetchUrl += '&_t=' + Date.now();

        fetch(fetchUrl, {
            cache: 'no-store',
            headers: { 'X-WP-Nonce': wpConfig.restNonce, 'Cache-Control': 'no-cache, no-store' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success' && Array.isArray(data.revisions) && data.revisions.length > 0) {
                self.revisions = data.revisions;
                self.populateDropdowns(data.revisions);

                const leftItem = data.revisions[0];
                const rightItem = data.revisions.length > 1 ? data.revisions[1] : data.revisions[0];

                self.setLeftRevision(leftItem, false);
                self.setRightRevision(rightItem, false);
                self.loadLeftFrame();
                self.loadRightFrame();
            } else {
                self.revisions = [];
            }
        })
        .catch(err => {
            console.error('[Align RevisionHandler] Error fetching revisions:', err);
        });
    },

    populateDropdowns: function(revisions) {
        ['rev-select-left', 'rev-select-right', 'rev-select-single'].forEach(id => {
            const sel = document.getElementById(id);
            if (!sel) return;
            sel.innerHTML = '';
            revisions.forEach(rev => {
                const opt = document.createElement('option');
                opt.value = rev.id;
                let label = rev.is_current ? 'Current Live' : (rev.is_autosave ? `Autosave (#${rev.id})` : `Revision #${rev.id}`);
                label += ` — ${rev.human_time} (${rev.date_formatted})`;
                opt.textContent = label;
                sel.appendChild(opt);
            });
        });
    },

    setLeftRevision: function(rev, shouldLoad = true) {
        this.leftRev = rev;
        const sel = document.getElementById('rev-select-left');
        if (sel) sel.value = rev.id;
        const meta = document.getElementById('rev-left-meta-info');
        if (meta) meta.textContent = `${rev.human_time} by ${rev.author_name}`;
        if (shouldLoad) this.loadLeftFrame();
    },

    setRightRevision: function(rev, shouldLoad = true) {
        this.rightRev = rev;
        const sel = document.getElementById('rev-select-right');
        if (sel) sel.value = rev.id;
        const meta = document.getElementById('rev-right-meta-info');
        if (meta) meta.textContent = `${rev.human_time} by ${rev.author_name}`;
        this.updateRestoreButton();
        if (shouldLoad) this.loadRightFrame();
    },

    setSingleRevision: function(rev, shouldLoad = true) {
        this.singleRev = rev;
        const sel = document.getElementById('rev-select-single');
        if (sel) sel.value = rev.id;
        const meta = document.getElementById('rev-single-meta-info');
        if (meta) meta.textContent = `${rev.human_time} by ${rev.author_name}`;
        this.updateRestoreButton();
        if (shouldLoad) this.loadSingleFrame();
    },

    updateRestoreButton: function() {
        const restoreBtn = document.getElementById('rev-apply-restore-btn');
        const btnText = document.getElementById('rev-restore-btn-text');
        const targetRev = (this.viewMode === 'split') ? this.rightRev : this.singleRev;
        if (!restoreBtn || !btnText) return;

        if (!targetRev || targetRev.is_current) {
            restoreBtn.setAttribute('disabled', 'true');
            btnText.textContent = 'Already Current Version';
        } else {
            restoreBtn.removeAttribute('disabled');
            btnText.textContent = `Restore Revision #${targetRev.id}`;
        }
    },

    buildPreviewUrl: function(rev) {
        if (!rev) return 'about:blank';
        const wpConfig = window.VvvebWpConfig || {};
        let baseUrl = wpConfig.restPageContentUrl || `${window.location.origin}/wp-json/${wpConfig.restNamespace || 'align-builder/v1'}/page-content`;

        const urlObj = new URL(baseUrl, window.location.href);
        if (rev.is_current && this.activeTpId) {
            urlObj.searchParams.set('tp_id', this.activeTpId);
            urlObj.searchParams.delete('id');
        } else {
            urlObj.searchParams.set('id', rev.id);
            urlObj.searchParams.delete('tp_id');
        }
        if (wpConfig.restNonce) urlObj.searchParams.set('_wpnonce', wpConfig.restNonce);
        urlObj.searchParams.set('_t', Date.now());
        return urlObj.toString();
    },

    loadLeftFrame: function() {
        if (!this.leftRev) return;
        const iframe = document.getElementById('rev-split-left-iframe');
        const loader = document.getElementById('rev-left-loader');
        if (!iframe) return;
        loader?.classList.remove('d-none');
        iframe.onload = () => {
            loader?.classList.add('d-none');
            this.setupUnifiedIframe(iframe);
        };
        iframe.src = this.buildPreviewUrl(this.leftRev);
    },

    loadRightFrame: function() {
        if (!this.rightRev) return;
        const iframe = document.getElementById('rev-split-right-iframe');
        const loader = document.getElementById('rev-right-loader');
        if (!iframe) return;
        loader?.classList.remove('d-none');
        iframe.onload = () => {
            loader?.classList.add('d-none');
            this.setupUnifiedIframe(iframe);
        };
        iframe.src = this.buildPreviewUrl(this.rightRev);
    },

    loadSingleFrame: function() {
        if (!this.singleRev) return;
        const iframe = document.getElementById('rev-single-iframe');
        const loader = document.getElementById('rev-single-loader');
        if (!iframe) return;
        loader?.classList.remove('d-none');
        iframe.onload = () => loader?.classList.add('d-none');
        iframe.src = this.buildPreviewUrl(this.singleRev);
    },

    setupUnifiedIframe: function(iframe) {
        const self = this;
        try {
            const win = iframe.contentWindow;
            const doc = win?.document;
            if (!win || !doc) return;

            const style = doc.createElement('style');
            style.textContent = 'html, body { scroll-behavior: auto !important; scrollbar-width: none !important; -ms-overflow-style: none !important; overflow-y: scroll !important; } html::-webkit-scrollbar, body::-webkit-scrollbar { display: none !important; width: 0 !important; height: 0 !important; }';
            doc.head?.appendChild(style);

            doc.addEventListener('wheel', (e) => {
                if (!self.syncScrollEnabled || self.viewMode !== 'split') return;
                e.preventDefault();
                self.applyMasterScrollDelta(e.deltaY);
            }, { passive: false });
        } catch (e) {
            console.warn('[Align RevisionHandler] Unified iframe setup error:', e);
        }
    },

    bindMasterScrollbar: function() {
        const self = this;
        const modalEl = document.getElementById('revisionsModal');
        const track = document.getElementById('rev-master-scrollbar');
        const thumb = document.getElementById('rev-master-thumb');
        if (!track || !thumb) return;

        const endDrag = (e) => {
            if (!self.isDraggingThumb) return;
            self.isDraggingThumb = false;
            modalEl?.classList.remove('rev-is-dragging');
            try {
                if (e?.pointerId && thumb.hasPointerCapture(e.pointerId)) {
                    thumb.releasePointerCapture(e.pointerId);
                }
            } catch (err) {}
        };

        thumb.addEventListener('pointerdown', (e) => {
            e.preventDefault();
            e.stopPropagation();
            self.isDraggingThumb = true;
            self.dragStartY = e.clientY;
            self.dragStartScrollY = self.masterScrollY;
            modalEl?.classList.add('rev-is-dragging');
            try { thumb.setPointerCapture(e.pointerId); } catch (err) {}
        });

        thumb.addEventListener('pointermove', (e) => {
            if (!self.isDraggingThumb) return;
            const trackRect = track.getBoundingClientRect();
            const maxTrackY = trackRect.height - (thumb.offsetHeight || 60);
            if (maxTrackY <= 0) return;
            const newScrollY = self.dragStartScrollY + ((e.clientY - self.dragStartY) / maxTrackY) * self.getMaxScroll();
            self.setMasterScroll(newScrollY);
        });

        thumb.addEventListener('pointerup', endDrag);
        thumb.addEventListener('pointercancel', endDrag);

        track.addEventListener('click', (e) => {
            if (e.target === thumb) return;
            const trackRect = track.getBoundingClientRect();
            self.setMasterScroll(((e.clientY - trackRect.top) / trackRect.height) * self.getMaxScroll());
        });
    },

    getMaxScroll: function() {
        const leftWin = document.getElementById('rev-split-left-iframe')?.contentWindow;
        const rightWin = document.getElementById('rev-split-right-iframe')?.contentWindow;
        const leftMax = leftWin?.document ? (leftWin.document.documentElement.scrollHeight - leftWin.innerHeight) : 0;
        const rightMax = rightWin?.document ? (rightWin.document.documentElement.scrollHeight - rightWin.innerHeight) : 0;
        return Math.max(1, Math.max(leftMax, rightMax));
    },

    applyMasterScrollDelta: function(deltaY) {
        this.setMasterScroll(this.masterScrollY + deltaY);
    },

    setMasterScroll: function(scrollY) {
        const maxScroll = this.getMaxScroll();
        this.masterScrollY = Math.max(0, Math.min(maxScroll, scrollY));
        const scrollPct = maxScroll > 0 ? (this.masterScrollY / maxScroll) : 0;

        const leftWin = document.getElementById('rev-split-left-iframe')?.contentWindow;
        const rightWin = document.getElementById('rev-split-right-iframe')?.contentWindow;

        requestAnimationFrame(() => {
            if (leftWin?.document) {
                const leftMax = leftWin.document.documentElement.scrollHeight - leftWin.innerHeight;
                if (leftMax > 0) leftWin.scrollTo(0, scrollPct * leftMax);
            }
            if (rightWin?.document) {
                const rightMax = rightWin.document.documentElement.scrollHeight - rightWin.innerHeight;
                if (rightMax > 0) rightWin.scrollTo(0, scrollPct * rightMax);
            }
            const track = document.getElementById('rev-master-scrollbar');
            const thumb = document.getElementById('rev-master-thumb');
            if (track && thumb) {
                const maxThumbY = track.offsetHeight - (thumb.offsetHeight || 60);
                if (maxThumbY > 0) thumb.style.top = (scrollPct * maxThumbY) + 'px';
            }
        });
    },

    restoreRevision: function(revisionId) {
        const self = this;
        const wpConfig = window.VvvebWpConfig || {};
        const restoreBtn = document.getElementById('rev-apply-restore-btn');
        const spinner = document.getElementById('rev-restore-spinner');
        const icon = document.getElementById('rev-restore-icon');

        if (!confirm(`Are you sure you want to restore Revision #${revisionId}? This will replace current active content.`)) return;

        restoreBtn?.setAttribute('disabled', 'true');
        spinner?.classList.remove('d-none');
        icon?.classList.add('d-none');

        const payload = { revision_id: revisionId, post_id: self.activePostId, tp_id: self.activeTpId };

        fetch(wpConfig.restRestoreRevisionUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpConfig.restNonce },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            spinner?.classList.add('d-none');
            icon?.classList.remove('d-none');

            if (data.status === 'success') {
                const msg = data.message || `Revision #${revisionId} restored successfully!`;
                if (typeof displayToast === 'function') displayToast('success', 'Restored', msg);
                else alert(msg);

                const modalEl = document.getElementById('revisionsModal');
                if (modalEl && window.bootstrap?.Modal) {
                    window.bootstrap.Modal.getInstance(modalEl)?.hide();
                }

                setTimeout(() => {
                    const curPageObj = window.Vvveb?.FileManager?.currentPage ? window.Vvveb.FileManager.pages[window.Vvveb.FileManager.currentPage] : null;
                    if (curPageObj?.file && window.Vvveb?.Builder) window.Vvveb.Builder.loadUrl(curPageObj.file);
                    else window.location.reload();
                }, 400);
            } else {
                restoreBtn?.removeAttribute('disabled');
                const errMsg = 'Failed to restore revision: ' + (data.message || 'Unknown error');
                if (typeof displayToast === 'function') displayToast('danger', 'Error', errMsg);
                else alert(errMsg);
            }
        })
        .catch(err => {
            spinner?.classList.add('d-none');
            icon?.classList.remove('d-none');
            restoreBtn?.removeAttribute('disabled');
            console.error('[Align RevisionHandler] Restore error:', err);
        });
    }
};
