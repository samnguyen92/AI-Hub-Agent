/**
 * Align Theme Builder - WordPress Pages & Templates FileManager Router
 */

window.AlignBuilderModules = window.AlignBuilderModules || {};

window.AlignBuilderModules.WpFilemanager = {
    copyTextToClipboard: function(text, onSuccess) {
        if (!text) return;
        function fallbackCopy(str, cb) {
            try {
                const tempInput = document.createElement('textarea');
                tempInput.value = str;
                tempInput.style.position = 'fixed';
                tempInput.style.left = '-999999px';
                tempInput.style.top = '-999999px';
                tempInput.setAttribute('readonly', '');
                document.body.appendChild(tempInput);
                tempInput.focus();
                tempInput.select();
                document.execCommand('copy');
                document.body.removeChild(tempInput);
                if (typeof cb === 'function') cb();
            } catch (err) {
                console.warn('[Align WpFilemanager] Fallback copy error:', err);
            }
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                if (typeof onSuccess === 'function') onSuccess();
            }).catch(function() {
                fallbackCopy(text, onSuccess);
            });
        } else {
            fallbackCopy(text, onSuccess);
        }
    },

    /**
     * Centralized navigation helper to synchronize parent Gutenberg editor and builder.
     */
    navigateToItem: function(pageData, pageName) {
        if (!pageData) return false;
        const wpConfig = window.VvvebWpConfig || {};
        const builderSlug = wpConfig.builderSlug || 'align-builder';
        const currentParams = new URLSearchParams(window.location.search);
        const isEmbedded = window.parent !== window;
        let parentParams = currentParams;
        if (isEmbedded) {
            try { parentParams = new URLSearchParams(window.parent.location.search); } catch (e) { parentParams = currentParams; }
        }
        const parentPostId = isEmbedded ? parentParams.get('post') : null;
        const currentPostId = parentPostId || currentParams.get('post_id') || wpConfig.activePostId;
        const currentTpId = currentParams.get('tp_id') || wpConfig.activeTpId;
        const targetPostId = pageData.postId ? String(pageData.postId) : null;
        const targetTpId = pageData.tpId ? String(pageData.tpId) : null;
        const currentLang = parentParams.get('lang') || currentParams.get('lang') || '';
        const targetLang = pageData.lang || '';

        const isDifferentPost = Boolean(targetPostId && String(targetPostId) !== String(currentPostId));
        const isDifferentTp   = Boolean(targetTpId && String(targetTpId) !== String(currentTpId));
        const isDifferentLang = Boolean(targetLang && currentLang && targetLang !== currentLang);

        if (!isDifferentPost && !isDifferentTp && !isDifferentLang) return false;

        const hasUnsavedChanges = (window.Vvveb?.Undo?.undoIndex > 0) || false;
        if (hasUnsavedChanges) {
            const targetTitle = pageData.title || pageName || 'this item';
            const confirmed = window.confirm(
                'You have unsaved changes on this page.\n\n' +
                'If you navigate to "' + targetTitle + '" now, your unsaved changes will be lost.\n\n' +
                'Click OK to leave, or Cancel to stay and save first.'
            );
            if (!confirmed) {
                console.log('[Align FileManager] Navigation cancelled by user — unsaved changes preserved.');
                return true;
            }
        }

        const isPage = !!(targetPostId && (pageData.folder === 'Pages' || (!pageData.tpId && !pageData.isReusable)));
        let redirectUrl;
        if (isPage) {
            redirectUrl = new URL('post.php', window.location.href);
            redirectUrl.search = new URLSearchParams({ post: targetPostId, action: 'edit' }).toString();
            if (targetLang || currentLang) redirectUrl.searchParams.set('lang', targetLang || currentLang);
        } else if (pageData.isReusable) {
            redirectUrl = new URL('admin.php', window.location.href);
            redirectUrl.search = new URLSearchParams({ page: 'align-studio', post_id: targetPostId }).toString();
        } else {
            redirectUrl = new URL(window.location.pathname + '?page=' + builderSlug, window.location.href);
            if (targetTpId) redirectUrl.searchParams.set('tp_id', targetTpId);
            else if (targetPostId) redirectUrl.searchParams.set('post_id', targetPostId);
            if (targetLang || currentLang) redirectUrl.searchParams.set('lang', targetLang || currentLang);
        }

        const navigationWindow = (isPage || isEmbedded) ? (window.top || window.parent || window) : window;
        // Loop prevention: do not navigate if target URL is identical to destination window location
        try {
            if (redirectUrl.href === navigationWindow.location.href) {
                console.log('[Align FileManager] Already at target destination URL, skipping navigate:', redirectUrl.href);
                return false;
            }
        } catch (e) {}

        console.log('[Align FileManager] Navigating to:', pageData.title || pageName, '| URL:', redirectUrl.href, '| Target:', (isPage || isEmbedded) ? 'parent' : 'iframe');
        if (isEmbedded || isPage) {
            try { (window.top || window.parent).postMessage({ type: 'ALIGN_NAVIGATE', url: redirectUrl.href }, window.location.origin); } catch (err) {}
        }
        navigationWindow.location.href = redirectUrl.href;
        return true;
    },

    init: function() {
        const self = this;
        const wpConfig = window.VvvebWpConfig || {};

        // 1. Intercept click events on #filemanager tree items to do full page navigation (like an <a> tag)
        document.addEventListener("click", function(e) {
            const fileLi = e.target.closest("#filemanager .tree li[data-page]");
            if (fileLi && !e.target.closest('.delete, .rename, .duplicate, .copy-id, .align-tree-tag-id, .align-tree-tag-reusable')) {
                const pageName = fileLi.dataset.page;
                if (pageName && window.Vvveb?.FileManager?.pages?.[pageName]) {
                    if (self.navigateToItem(window.Vvveb.FileManager.pages[pageName], pageName)) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                    }
                }
            }
        }, true);


        // 1b. Fast 1-click copy handler for Reusable Block ID / Shortcode in left sidebar tree
        document.addEventListener("click", function(e) {
            const copyBtn = e.target.closest("#filemanager .tree li .copy-id");
            const tagEl   = e.target.closest("#filemanager .tree li .align-tree-tag-id, #filemanager .tree li .align-tree-tag-reusable");
            if (copyBtn || tagEl) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();

                const targetEl = copyBtn || tagEl;
                const fileLi = targetEl.closest("li[data-page]");
                if (!fileLi) return;

                const pageName = fileLi.dataset.page;
                const pageData = (window.Vvveb && window.Vvveb.FileManager && window.Vvveb.FileManager.pages && pageName)
                    ? window.Vvveb.FileManager.pages[pageName]
                    : null;

                const rawBlockId = (pageData && (pageData.postId || pageData.id))
                    || fileLi.dataset.post_id
                    || fileLi.getAttribute('data-post_id')
                    || String(pageName || '').replace(/^block_/, '');

                if (!rawBlockId) return;

                const isAltOrShift = e.altKey || e.shiftKey;
                const shortcodeText = (pageData && pageData.shortcode) || ('[reuse-block id="' + rawBlockId + '"]');
                const textToCopy = isAltOrShift ? String(rawBlockId) : shortcodeText;

                self.copyTextToClipboard(textToCopy, function() {
                    console.log('[Align WpFilemanager] 1-Click Copied Reusable Block:', textToCopy);
                    const btn = fileLi.querySelector('.file-actions .copy-id');
                    if (btn) {
                        const icon = btn.querySelector('i');
                        if (icon) {
                            const origClass = icon.className;
                            icon.className = 'la la-check';
                            btn.style.color = '#22c55e';
                            setTimeout(function() {
                                icon.className = origClass;
                                btn.style.color = '';
                            }, 1500);
                        }
                    }

                    const tag = fileLi.querySelector('.align-tree-tag-id, .align-tree-tag-reusable');
                    if (tag) {
                        tag.classList.add('copied');
                        setTimeout(function() {
                            tag.classList.remove('copied');
                        }, 1500);
                    }

                    if (typeof displayToast === 'function') {
                        const msg = isAltOrShift
                            ? ('Reusable Block ID ' + textToCopy + ' copied to clipboard!')
                            : ('Shortcode ' + textToCopy + ' copied to clipboard!');
                        displayToast('success', 'Copied', msg);
                    }
                });
            }
        }, true);


        // Top Panel Shortcode display for Reusable Blocks + Page Indicator
        window.addEventListener("Vvveb.FileManager.loadPage", function(e) {
            setTimeout(function() {
                if (window.AlignBuilderModules.WpComponentsPreview) {
                    window.AlignBuilderModules.WpComponentsPreview.triggerWpComponentsPreviewInIframe();
                }
            }, 300);

            const pageData = e.detail;
            if (!pageData) return;

            // Update the current page indicator pill in the top panel
            self.updatePageIndicator(pageData);

            const scContainer = document.getElementById('top-panel-shortcode-container');
            const scTextElem  = document.getElementById('top-panel-shortcode-text');
            const isReusable   = (pageData.folder === 'Reusable Blocks' || pageData.type === 'Reusable Blocks' || (pageData.name && String(pageData.name).startsWith('block_')) || pageData.shortcode);
            if (isReusable && scContainer && scTextElem) {
                const rawBlockId = pageData.postId || pageData.id || String(pageData.name).replace('block_', '');
                const scText = pageData.shortcode || ('[reuse-block id="' + rawBlockId + '"]');
                scTextElem.textContent = scText;
                scContainer.style.display = 'inline-flex';
            } else if (scContainer) {
                scContainer.style.display = 'none';
            }

            if (scContainer && !scContainer.getAttribute('data-copy-hooked')) {
                scContainer.setAttribute('data-copy-hooked', '1');
                scContainer.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const txt = scTextElem ? scTextElem.textContent.trim() : '';
                    if (!txt) return;
                    self.copyTextToClipboard(txt, function() {
                        scContainer.classList.add('copied');
                        const copyIcon = scContainer.querySelector('.icon-copy') || scContainer.querySelector('.la-copy');
                        if (copyIcon) copyIcon.className = 'la la-check icon-copy';
                        if (typeof displayToast === 'function') displayToast('success', 'Copied', 'Shortcode ' + txt + ' copied to clipboard!');
                        setTimeout(function() {
                            scContainer.classList.remove('copied');
                            if (copyIcon) copyIcon.className = 'la la-copy icon-copy';
                        }, 1500);
                    });
                });
            }
        });

        // Initial fetch of WordPress pages and templates
        setTimeout(() => self.loadWpPages(), 300);
    },

    /**
     * Update the #align-page-indicator pill in the top panel to show the currently editing page/template.
     *
     * @param {Object} pageData - Page data object from Vvveb.FileManager.pages
     */
    updatePageIndicator: function(pageData) {
        const pill    = document.getElementById('align-page-indicator');
        const textEl  = document.getElementById('align-page-indicator-text');
        const badgeEl = document.getElementById('align-page-indicator-badge');
        const iconEl  = pill ? pill.querySelector('.align-page-indicator-icon') : null;

        if (!pill || !textEl || !badgeEl) return;

        const title = (pageData && (pageData.title || pageData.name)) || '–';
        const tplType = (pageData && (pageData.templateType || pageData.type || pageData.folder || '')) || '';
        const isTemplate     = tplType === 'template'      || tplType === 'Templates';
        const isTemplatePart = tplType === 'template_part' || tplType === 'Template Parts';
        const isReusable     = tplType === 'Reusable Blocks' || (pageData && (pageData.isReusable || (pageData.name && String(pageData.name).startsWith('block_'))));

        pill.classList.remove('is-template', 'is-template-part', 'is-reusable');
        if (isReusable) {
            pill.classList.add('is-reusable');
            if (iconEl) iconEl.className = 'la la-cubes align-page-indicator-icon';
            badgeEl.textContent = 'Reusable Block';
        } else if (isTemplate) {
            pill.classList.add('is-template');
            if (iconEl) iconEl.className = 'la la-file-code align-page-indicator-icon';
            badgeEl.textContent = 'Template';
        } else if (isTemplatePart) {
            pill.classList.add('is-template-part');
            if (iconEl) iconEl.className = 'la la-puzzle-piece align-page-indicator-icon';
            badgeEl.textContent = 'Template Part';
        } else {
            if (iconEl) iconEl.className = 'la la-file-alt align-page-indicator-icon';
            badgeEl.textContent = 'Page';
        }

        textEl.textContent = title;
        textEl.title       = title;
        pill.style.display = 'inline-flex';
        console.log('[Align FileManager] Page indicator updated:', title, '| Type:', tplType || 'page');
    },

    loadWpPages: function(langFilter) {
        const wpConfig = window.VvvebWpConfig || {};
        if (!wpConfig.restPagesUrl) return;

        const activeLang = (typeof langFilter === 'string' ? langFilter : (wpConfig.alignActiveLang || new URLSearchParams(window.location.search).get('lang') || wpConfig.alignDefaultLang || ''));
        let fetchUrl = wpConfig.restPagesUrl + (wpConfig.restPagesUrl.indexOf('?') > -1 ? '&' : '?') + '_t=' + Date.now();
        if (activeLang) fetchUrl += '&lang=' + encodeURIComponent(activeLang);

        fetch(fetchUrl, {
            cache: 'no-store',
            headers: {
                'X-WP-Nonce': wpConfig.restNonce,
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache'
            }
        })
        .then(res => res.json())
        .then(pages => {
            if (!Array.isArray(pages)) return;

            const formattedPages = {};
            pages.forEach(p => {
                const isTemplate = (p.template_type === 'template' || p.type === 'Templates' || p.folder === 'Templates');
                const isTemplatePart = (p.template_type === 'template_part' || p.type === 'Template Parts' || p.folder === 'Template Parts');
                const isPageOrBlock = !isTemplate && !isTemplatePart;
                const tpIdVal = (isTemplate || isTemplatePart) ? (p.id ? String(p.id) : (isTemplate ? 'tpl_' + p.name : 'tp_' + p.name)) : null;

                formattedPages[p.name] = {
                    name: p.name, title: p.title, slug: p.slug || '', url: p.url || p.file, file: p.file,
                    folder: p.folder || p.type || "Pages",
                    templateType: p.template_type || (isTemplate ? 'template' : (isTemplatePart ? 'template_part' : 'page')),
                    postId: isPageOrBlock ? p.id : null, post_id: isPageOrBlock ? p.id : null, tpId: tpIdVal,
                    lang: p.lang || '', langGroup: p.lang_group || '', isSystem: !!p.is_system, isCustom: !!p.is_custom,
                    isReusable: !isTemplate && !isTemplatePart && (p.folder === 'Reusable Blocks' || p.type === 'Reusable Blocks' || !!p.is_reusable),
                    shortcode: p.shortcode || (!isTemplate && !isTemplatePart && (p.folder === 'Reusable Blocks' || !!p.is_reusable) ? ('[reuse-block id="' + p.id + '"]') : ''),
                    tagLabel: p.tag_label || '', tagType: p.tag_type || ''
                };
            });

            if (window.Vvveb && window.Vvveb.FileManager) {
                window.Vvveb.FileManager.addPages(formattedPages);

                // Ensure Reusable Blocks folder exists in tree even if 0 blocks exist
                const treeOl = document.querySelector("#filemanager .tree > ol");
                if (treeOl && !treeOl.querySelector('li[data-folder="Reusable Blocks"]')) {
                    const folderData = { folder: "Reusable Blocks", folderTitle: "Reusable Blocks" };
                    const folderElements = generateElements(tmpl("vvveb-filemanager-folder", folderData));
                    const folder = Array.from(folderElements).find(el => el.nodeType === 1) || folderElements[0];
                    if (folder) treeOl.append(folder);
                }
            }

            // Auto load active template part or post specified in URL
            let pageToLoad = pages[0];
            const currentSearch = new URLSearchParams(window.location.search);
            const rawActivePostId = currentSearch.get('post_id') || currentSearch.get('postId') || (wpConfig ? wpConfig.activePostId : null);
            let rawActiveTpId = currentSearch.get('tp_id') || currentSearch.get('pId') || (wpConfig ? wpConfig.activeTpId : null);

            // If activePostId is not numeric and contains // or template prefixes, it is actually a template/template-part id
            if (rawActivePostId && isNaN(Number(rawActivePostId)) && (String(rawActivePostId).includes('//') || String(rawActivePostId).includes('tpl_') || String(rawActivePostId).includes('tp_'))) {
                if (!rawActiveTpId) {
                    rawActiveTpId = rawActivePostId;
                }
            }

            if (rawActiveTpId) {
                const activeTpId = String(rawActiveTpId);
                const rawSearch = activeTpId.replace(/^t(p|pl)_/, '');
                const slugOnly = rawSearch.includes('//') ? rawSearch.split('//')[1] : rawSearch;

                // 1. Exact ID match
                let found = pages.find(p => p.id === activeTpId || String(p.id).replace(/^t(p|pl)_/, '') === rawSearch);

                // 2. Exact Name or Slug match
                if (!found) {
                    found = pages.find(p => 
                        p.name === activeTpId || 
                        p.name === 'tp_' + slugOnly || 
                        p.name === 'tpl_' + slugOnly || 
                        p.slug === slugOnly || 
                        (p.id && String(p.id).split('//')[1] === slugOnly)
                    );
                }

                // 3. Exact URL parameter match in p.file
                if (!found) {
                    found = pages.find(p => {
                        if (!p.file) return false;
                        try {
                            const fileUrl = new URL(p.file, window.location.origin);
                            const fileTpId = fileUrl.searchParams.get('tp_id');
                            if (fileTpId) {
                                const cleanFileTpId = fileTpId.replace(/^t(p|pl)_/, '');
                                return cleanFileTpId === rawSearch || (cleanFileTpId.includes('//') ? cleanFileTpId.split('//')[1] === slugOnly : cleanFileTpId === slugOnly);
                            }
                        } catch (err) {}
                        return false;
                    });
                }

                if (found) {
                    pageToLoad = found;
                } else {
                    let ns = (wpConfig && wpConfig.restNamespace) ? wpConfig.restNamespace : 'align-builder/v1';
                    let baseFileUrl = (wpConfig && wpConfig.restPageContentUrl) ? wpConfig.restPageContentUrl : '';
                    if (!baseFileUrl && wpConfig && wpConfig.restPagesUrl) {
                        baseFileUrl = wpConfig.restPagesUrl.replace('/pages', '/page-content');
                    }
                    if (!baseFileUrl) {
                        baseFileUrl = `/wp-json/${ns}/page-content`;
                    }
                    const activeLang = (new URLSearchParams(window.location.search)).get('lang') || '';
                    let fileUrl = baseFileUrl + (baseFileUrl.indexOf('?') !== -1 ? '&' : '?') + 'tp_id=' + encodeURIComponent(activeTpId);
                    if (activeLang) {
                        fileUrl += '&lang=' + encodeURIComponent(activeLang);
                    }
                    if (wpConfig && wpConfig.restNonce && fileUrl.indexOf('_wpnonce') === -1) {
                        fileUrl += '&_wpnonce=' + encodeURIComponent(wpConfig.restNonce);
                    }
                    pageToLoad = {
                        id: activeTpId,
                        name: activeTpId,
                        title: 'Template: ' + activeTpId,
                        file: fileUrl
                    };
                }
            } else if (rawActivePostId && !isNaN(Number(rawActivePostId))) {
                const numId = String(rawActivePostId);
                const found = pages.find(p => 
                    String(p.id) === numId || 
                    p.name === 'page_' + numId || 
                    p.name === 'post_' + numId ||
                    p.name === 'block_' + numId ||
                    (p.postId && String(p.postId) === numId)
                );
                if (found) {
                    pageToLoad = found;
                } else {
                    // Dynamic Post Content Fallback: Load target post/CPT directly into canvas
                    const postType = (wpConfig && wpConfig.activePostType) ? wpConfig.activePostType : 'post';
                    let fileUrl = (wpConfig && wpConfig.restPageContentUrl) ? wpConfig.restPageContentUrl : '';
                    if (!fileUrl && wpConfig && wpConfig.restPagesUrl) {
                        fileUrl = wpConfig.restPagesUrl.replace('/pages', '/page-content');
                    }
                    if (!fileUrl) {
                        fileUrl = '/wp-json/align-builder/v1/page-content';
                    }
                    const sep = fileUrl.includes('?') ? '&' : '?';
                    if (!fileUrl.includes('id=')) {
                        fileUrl += sep + 'id=' + encodeURIComponent(numId);
                    }
                    if (wpConfig && wpConfig.restNonce && !fileUrl.includes('_wpnonce=')) {
                        fileUrl += '&_wpnonce=' + encodeURIComponent(wpConfig.restNonce);
                    }

                    pageToLoad = {
                        id: numId,
                        name: postType + '_' + numId,
                        title: (postType.charAt(0).toUpperCase() + postType.slice(1)) + ' #' + numId,
                        file: fileUrl,
                        postId: numId,
                        post_id: numId
                    };
                    console.log('[Align FileManager] Dynamic Post Content Fallback activated for post ID:', numId, '| Type:', postType);
                }
            }

            // Restore folder accordion states and auto-open folder for active page
            if (window.Vvveb && window.Vvveb.FileManager && typeof window.Vvveb.FileManager.restoreFolderStates === 'function') {
                const activeFolder = pageToLoad ? (pageToLoad.folder || pageToLoad.type || "Pages") : null;
                window.Vvveb.FileManager.restoreFolderStates(activeFolder);
            }

            if (pageToLoad && window.Vvveb && window.Vvveb.Builder) {
                window.Vvveb.Builder.init(pageToLoad.file, function() {
                    if (window.AlignBuilderModules.WpStyleInjector) {
                        window.AlignBuilderModules.WpStyleInjector.injectThemeStylesIntoIframe();
                        window.AlignBuilderModules.WpStyleInjector.replaceDummyImagesInIframe();
                    }
                    if (window.AlignBuilderModules.WpComponentsPreview) {
                        window.AlignBuilderModules.WpComponentsPreview.triggerWpComponentsPreviewInIframe();
                    }
                });
                if (window.Vvveb.FileManager && pageToLoad.name && window.Vvveb.FileManager.pages && window.Vvveb.FileManager.pages[pageToLoad.name]) {
                    window.Vvveb.FileManager.loadPage(pageToLoad.name);
                } else {
                    // Dynamic fallback: loadPage event won't fire — update indicator directly
                    if (window.AlignBuilderModules && window.AlignBuilderModules.WpFilemanager) {
                        window.AlignBuilderModules.WpFilemanager.updatePageIndicator(pageToLoad);
                    }
                }
            }
        })
        .catch(err => {
            console.error("Error loading WP pages & template parts:", err);
        });
    }
};
