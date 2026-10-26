/**
 * Align Builder - Multi-Language Switcher
 *
 * Renders language filter tabs (EN | ZH | All) above File Manager,
 * adds language badge on each page item, and provides "Translate" button
 * to create translations of any page in any configured language.
 */
window.AlignBuilderModules = window.AlignBuilderModules || {};

window.AlignBuilderModules.WpLangSwitcher = {
    currentLang: '',
    defaultLang: 'en',
    languages: [],
    _initialized: false,

    init: function() {
        if (this._initialized) return;
        this._initialized = true;

        const config = window.VvvebWpConfig || {};
        if (!config.alignLangEnabled || !Array.isArray(config.alignLanguages) || config.alignLanguages.length < 2) {
            console.log('[Align LangSwitcher] Only 1 language configured or disabled — skipping.');
            return;
        }

        this.languages   = config.alignLanguages;
        this.defaultLang = config.alignDefaultLang || 'en';
        this.currentLang = config.alignActiveLang || new URLSearchParams(window.location.search).get('lang') || this.defaultLang;

        console.log('[Align LangSwitcher] Init | langs:', this.languages.map(l => l.code), '| active:', this.currentLang);

        this._injectStyles();
        this._renderLangTabs();
        this._hookFileManagerItems();
    },

    // -------------------------------------------------------------------------
    // Language Tabs UI
    // -------------------------------------------------------------------------

    _renderLangTabs: function() {
        const filemanager = document.getElementById('filemanager');
        if (!filemanager) return;

        const self = this;
        const tabs = this.languages.map(function(lang) {
            const isActive = lang.code === self.currentLang ? 'active' : '';
            return `<button class="align-lang-tab ${isActive}" data-lang="${lang.code}" title="${lang.name}">
                        <span class="align-lang-flag">${lang.flag || ''}</span>
                        <span class="align-lang-code">${lang.code.toUpperCase()}</span>
                    </button>`;
        }).join('');

        const switcherEl = document.createElement('div');
        switcherEl.id = 'align-lang-switcher';
        switcherEl.className = 'align-lang-switcher';
        switcherEl.innerHTML = `
            <div class="align-lang-tabs">
                ${tabs}
                <button class="align-ai-translate-page-btn" id="align-ai-translate-page-btn" title="Translate canvas content with AI">
                    <i class="la la-magic"></i> <span>AI Translate</span>
                </button>
            </div>`;

        filemanager.insertBefore(switcherEl, filemanager.firstChild);

        // Bind clicks
        switcherEl.addEventListener('click', function(e) {
            const aiBtn = e.target.closest('#align-ai-translate-page-btn');
            if (aiBtn) {
                e.preventDefault();
                e.stopPropagation();
                self.translateCanvas();
                return;
            }

            const tab = e.target.closest('.align-lang-tab');
            if (!tab) return;
            e.preventDefault();
            e.stopPropagation();

            const lang = tab.dataset.lang;
            if (lang === self.currentLang) return;

            console.log('[Align LangSwitcher] Switch to lang:', lang);
            self._switchLang(lang);
        });
    },

    _switchLang: function(targetLang) {
        if (!targetLang || targetLang === this.currentLang) return;

        const config = window.VvvebWpConfig || {};
        const isEmbedded = window.parent !== window;
        const navigationWindow = isEmbedded ? window.parent : window;
        const builderSlug = config.builderSlug || 'align-builder';

        function navigateToLangPost(targetId, lang) {
            let redirectUrl;
            if (isEmbedded) {
                redirectUrl = new URL('post.php', window.location.href);
                redirectUrl.search = new URLSearchParams({ post: targetId, action: 'edit', lang: lang }).toString();
            } else {
                redirectUrl = new URL(window.location.pathname + '?page=' + builderSlug, window.location.href);
                redirectUrl.search = new URLSearchParams({ page: builderSlug, post_id: targetId, lang: lang }).toString();
            }
            console.log('[Align LangSwitcher] Navigating to post #' + targetId + ' (' + lang + ') | Target:', isEmbedded ? 'parent' : 'iframe');
            if (isEmbedded) {
                try { window.parent.postMessage({ type: 'ALIGN_NAVIGATE', url: redirectUrl.href }, window.location.origin); } catch (e) {}
            }
            navigationWindow.location.href = redirectUrl.href;
        }

        const activePostId = config.activePostId ? parseInt(config.activePostId, 10) : (parseInt(new URLSearchParams(window.location.search).get('post_id'), 10) || 0);

        // 1. Direct check from active post translations injected by backend
        const directTranslations = Array.isArray(config.alignTranslations) ? config.alignTranslations : [];
        const foundDirect = directTranslations.find(t => t.lang === targetLang);
        if (foundDirect && foundDirect.post_id) {
            console.log('[Align LangSwitcher] Direct translation found: post #' + foundDirect.post_id + ' (' + targetLang + ')');
            navigateToLangPost(foundDirect.post_id, targetLang);
            return;
        }

        const pages = (window.Vvveb && window.Vvveb.FileManager && window.Vvveb.FileManager.pages) || {};

        // Find current page in FileManager
        let currentPage = null;
        if (activePostId) {
            for (let k in pages) {
                if (parseInt(pages[k].postId || pages[k].post_id, 10) === activePostId) {
                    currentPage = pages[k];
                    break;
                }
            }
        }

        // 2. Check if there is already a translation page in targetLang in FileManager
        let translationPage = null;
        if (currentPage && currentPage.langGroup) {
            for (let k in pages) {
                const p = pages[k];
                if (p.lang === targetLang && p.langGroup === currentPage.langGroup) {
                    translationPage = p;
                    break;
                }
            }
        }

        if (translationPage && (translationPage.postId || translationPage.id)) {
            const targetPostId = translationPage.postId || translationPage.id;
            console.log('[Align LangSwitcher] Tree translation found: post #' + targetPostId + ' (' + targetLang + ')');
            navigateToLangPost(targetPostId, targetLang);
            return;
        }

        // 3. If currently on a template or template part (tp_id)
        let activeTpId = config.activeTpId || (new URLSearchParams(window.location.search)).get('tp_id') || '';
        if (!activeTpId && window.Vvveb && window.Vvveb.FileManager && window.Vvveb.FileManager.currentPage) {
            const fmPage = window.Vvveb.FileManager.pages ? window.Vvveb.FileManager.pages[window.Vvveb.FileManager.currentPage] : null;
            if (fmPage && (fmPage.tpId || fmPage.templateType === 'template' || fmPage.templateType === 'template_part')) {
                activeTpId = fmPage.tpId || fmPage.id;
            }
        }

        if (activeTpId) {
            const rawSearch = String(activeTpId).replace(/^t(p|pl)_/, '');
            const slugOnly = rawSearch.includes('//') ? rawSearch.split('//')[1] : rawSearch;
            const baseSlug = slugOnly.replace(/-[a-z]{2}(?:-[a-z]{2})?$/, '');
            const targetSlug = baseSlug + '-' + targetLang;

            let targetTpPage = null;
            for (let k in pages) {
                const p = pages[k];
                if (p.slug === targetSlug || p.name === 'tp_' + targetSlug || p.name === 'tpl_' + targetSlug) {
                    targetTpPage = p;
                    break;
                }
            }

            const url = new URL(window.location.href);
            url.searchParams.delete('post_id');
            url.searchParams.delete('postId');
            url.searchParams.set('tp_id', targetTpPage ? targetTpPage.id : activeTpId);
            url.searchParams.set('lang', targetLang);
            window.location.href = url.toString();
            return;
        }

        // 4. If currently on a page that does NOT have a translation in targetLang
        if (activePostId && currentPage) {
            const langObj = this.languages.find(l => l.code === targetLang) || { name: targetLang.toUpperCase() };
            const pageTitle = (currentPage.title || 'this page').replace(/^[^\w]+/, '').trim();
            const confirmMsg = `No ${langObj.name} translation exists for "${pageTitle}" yet.\n\nClick OK to create a ${langObj.name} translation now and start editing it.\nClick Cancel to switch the view without translating this page.`;

            if (confirm(confirmMsg)) {
                this._openTranslateModal(activePostId, currentPage);
                return;
            }
        }

        // Switch language view without keeping the previous post_id
        const url = new URL(window.location.href);
        url.searchParams.delete('post_id');
        url.searchParams.delete('postId');
        url.searchParams.set('lang', targetLang);
        window.location.href = url.toString();
    },

    // -------------------------------------------------------------------------
    // File Manager Item Hooks: lang badge + Translate button
    // -------------------------------------------------------------------------

    _hookFileManagerItems: function() {
        const self = this;

        // Observe DOM for File Manager tree items being rendered
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(m) {
                if (m.addedNodes.length) {
                    self._decoratePageItems();
                }
            });
        });

        const tree = document.querySelector('#filemanager .tree');
        if (tree) {
            observer.observe(tree, { childList: true, subtree: true });
        }

        // Also run on initial load
        setTimeout(function() { self._decoratePageItems(); }, 800);

        // Hook translate button clicks (event delegation)
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.align-translate-btn');
            if (!btn) return;
            e.preventDefault();
            e.stopImmediatePropagation();

            const li = btn.closest('li[data-page]');
            if (!li) return;

            const pageName = li.dataset.page;
            const pageData = (window.Vvveb && window.Vvveb.FileManager && window.Vvveb.FileManager.pages)
                ? window.Vvveb.FileManager.pages[pageName] : null;
            const postId = pageData ? (pageData.postId || pageData.id || pageData.name) : null;

            if (!postId) {
                console.warn('[Align LangSwitcher] Could not resolve post_id for:', pageName);
                return;
            }

            self._openTranslateModal(postId, pageData);
        }, true);
    },

    _decoratePageItems: function() {
        const self = this;
        const config = window.VvvebWpConfig || {};
        const pages = window.Vvveb && window.Vvveb.FileManager ? (window.Vvveb.FileManager.pages || {}) : {};

        document.querySelectorAll('#filemanager .tree li[data-page]').forEach(function(li) {
            if (li.dataset.langDecorated) return;
            li.dataset.langDecorated = '1';

            const pageName = li.dataset.page;
            if (!pageName) return;
            const isSupported = pageName.startsWith('page_') || pageName.startsWith('post_') || pageName.startsWith('tp_') || pageName.startsWith('tpl_');
            if (!isSupported) return;

            const pageData = pages[pageName] || {};
            const lang = pageData.lang || '';
            const langCfg = self.languages.find(l => l.code === lang);

            // Add language badge next to title
            if (lang && langCfg) {
                const titleEl = li.querySelector('.file-title, label .name') || li.querySelector('label');
                if (titleEl && !titleEl.querySelector('.align-lang-badge')) {
                    const badge = document.createElement('span');
                    badge.className = 'align-lang-badge';
                    badge.textContent = (langCfg.flag || '') + ' ' + lang.toUpperCase();
                    badge.title = langCfg.name;
                    titleEl.appendChild(badge);
                }
            }

            // Add Translate button to file-actions
            const actions = li.querySelector('.file-actions');
            if (actions && !actions.querySelector('.align-translate-btn')) {
                const translateBtn = document.createElement('button');
                translateBtn.className = 'align-translate-btn';
                translateBtn.title = 'Create translation';
                translateBtn.innerHTML = '<i class="la la-language"></i>';
                actions.insertBefore(translateBtn, actions.firstChild);
            }
        });
    },

    // -------------------------------------------------------------------------
    // Translate Modal
    // -------------------------------------------------------------------------

    _openTranslateModal: function(postId, pageData) {
        const self = this;
        const currentLang = (pageData && pageData.lang) || this.defaultLang;
        const pageTitle = (pageData && pageData.title) ? pageData.title.replace(/^[^\w]+/, '').trim() : 'this page';

        // Available target languages (exclude current)
        const targets = this.languages.filter(l => l.code !== currentLang);
        if (targets.length === 0) {
            if (typeof displayToast === 'function') {
                displayToast('warning', 'No Languages', 'No other languages configured. Add more in Align Settings.');
            }
            return;
        }

        const langOptions = targets.map(l =>
            `<div class="align-translate-option" data-lang="${l.code}">
                <span class="flag">${l.flag || ''}</span>
                <span class="name">${l.name}</span>
                <span class="code">${l.code.toUpperCase()}</span>
            </div>`
        ).join('');

        // Remove existing modal
        const existingModal = document.getElementById('align-translate-modal');
        if (existingModal) existingModal.remove();

        const modal = document.createElement('div');
        modal.id = 'align-translate-modal';
        modal.className = 'align-translate-modal';
        modal.innerHTML = `
            <div class="align-translate-modal-inner">
                <div class="modal-header">
                    <span>Create Translation</span>
                    <button class="modal-close" id="align-translate-close"><i class="la la-times"></i></button>
                </div>
                <div class="modal-body">
                    <p class="modal-desc">Choose a language to translate <strong>${pageTitle}</strong>:</p>
                    <div class="align-translate-options">${langOptions}</div>
                    <label class="align-translate-ai-label">
                        <input type="checkbox" id="align-modal-ai-translate" checked>
                        <span>Auto-translate page content with AI</span>
                    </label>
                </div>
                <div class="modal-footer" id="align-translate-status"></div>
            </div>`;

        document.body.appendChild(modal);

        // Close button
        modal.querySelector('#align-translate-close').addEventListener('click', function() {
            modal.remove();
        });

        // Click outside to close
        modal.addEventListener('click', function(e) {
            if (e.target === modal) modal.remove();
        });

        // Language option click
        modal.querySelectorAll('.align-translate-option').forEach(function(opt) {
            opt.addEventListener('click', function() {
                const targetLang = opt.dataset.lang;
                self._createTranslation(postId, targetLang, modal);
            });
        });

        console.log('[Align LangSwitcher] Opened translate modal for post_id:', postId, '| current lang:', currentLang);
    },

    _createTranslation: function(postId, targetLang, modal) {
        const config = window.VvvebWpConfig || {};
        const url = config.restCreateTranslationUrl;
        if (!url) {
            console.error('[Align LangSwitcher] restCreateTranslationUrl not found in VvvebWpConfig');
            return;
        }

        const autoAi = modal.querySelector('#align-modal-ai-translate')?.checked ?? true;
        const status = modal.querySelector('#align-translate-status');
        if (status) status.innerHTML = '<span class="align-spinner">Creating translation...</span>';

        modal.querySelectorAll('.align-translate-option').forEach(el => el.style.pointerEvents = 'none');

        console.log('[Align LangSwitcher] Creating translation | post_id:', postId, '| target_lang:', targetLang, '| auto_ai:', autoAi);

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.restNonce },
            body: JSON.stringify({ post_id: postId, target_lang: targetLang }),
        })
        .then(r => r.json())
        .then(function(data) {
            if (data.success) {
                console.log('[Align LangSwitcher] Translation created | new_post_id:', data.new_post_id, '| lang:', data.lang);

                const navigateToNew = function() {
                    modal.remove();
                    const newUrl = new URL(window.location.href);
                    newUrl.searchParams.set('post_id', data.new_post_id);
                    newUrl.searchParams.set('lang', targetLang);
                    newUrl.searchParams.delete('tp_id');
                    window.location.href = newUrl.toString();
                };

                if (autoAi && config.restTranslatePostUrl) {
                    if (status) status.innerHTML = '<span class="align-spinner"><i class="la la-magic"></i> Translating page content with AI...</span>';
                    fetch(config.restTranslatePostUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.restNonce },
                        body: JSON.stringify({ post_id: data.new_post_id, target_lang: targetLang })
                    })
                    .then(r => r.json())
                    .then(function(tData) {
                        console.log('[Align LangSwitcher] Auto-translated new post | count:', tData.text_count);
                        if (typeof displayToast === 'function') {
                            displayToast('success', 'Translation Created', `"${data.title}" translated to ${data.lang_name}. Opening editor...`);
                        }
                        setTimeout(navigateToNew, 800);
                    })
                    .catch(function(err) {
                        console.warn('[Align LangSwitcher] Auto-translate failed, opening anyway:', err);
                        navigateToNew();
                    });
                } else {
                    if (typeof displayToast === 'function') {
                        displayToast('success', 'Translation Created', `"${data.title}" (${data.lang_name}) created as draft. Opening editor...`);
                    }
                    setTimeout(navigateToNew, 1000);
                }
            } else {
                const existingId = data.existing_id || (data.data && data.data.existing_id) || (data.message && data.message.match(/ID:\s*(\d+)/) ? data.message.match(/ID:\s*(\d+)/)[1] : null);
                if (existingId) {
                    console.log('[Align LangSwitcher] Translation already exists | navigating to post #' + existingId);
                    if (typeof displayToast === 'function') {
                        displayToast('info', 'Opening Translation', 'Loading existing ' + targetLang.toUpperCase() + ' translation...');
                    }
                    setTimeout(function() {
                        modal.remove();
                        const newUrl = new URL(window.location.href);
                        newUrl.searchParams.set('post_id', existingId);
                        newUrl.searchParams.set('lang', targetLang);
                        newUrl.searchParams.delete('tp_id');
                        window.location.href = newUrl.toString();
                    }, 400);
                    return;
                }
                const msg = data.message || 'Failed to create translation.';
                if (status) status.innerHTML = `<span class="align-error">${msg}</span>`;
                if (typeof displayToast === 'function') displayToast('danger', 'Error', msg);
                console.error('[Align LangSwitcher] Create translation failed:', msg);
                modal.querySelectorAll('.align-translate-option').forEach(el => el.style.pointerEvents = '');
            }
        })
        .catch(function(err) {
            console.error('[Align LangSwitcher] Network error:', err);
            if (status) status.innerHTML = '<span class="align-error">Network error. Please try again.</span>';
            modal.querySelectorAll('.align-translate-option').forEach(el => el.style.pointerEvents = '');
        });
    },

    // -------------------------------------------------------------------------
    // AI Canvas Translation
    // -------------------------------------------------------------------------

    translateCanvas: function(targetLang) {
        if (window.AlignBuilderModules && window.AlignBuilderModules.WpCanvasTranslator) {
            window.AlignBuilderModules.WpCanvasTranslator.translateCanvas(targetLang);
        } else {
            console.error('[Align LangSwitcher] WpCanvasTranslator module not loaded');
        }
    },

    // -------------------------------------------------------------------------
    // Styles
    // -------------------------------------------------------------------------

    _injectStyles: function() {
        // Styles loaded via css/components/editor-lang-seo.css
    },
};

// Auto-init when DOM ready
document.addEventListener('DOMContentLoaded', function() {
    if (window.AlignBuilderModules.WpLangSwitcher) {
        window.AlignBuilderModules.WpLangSwitcher.init();
    }
});
