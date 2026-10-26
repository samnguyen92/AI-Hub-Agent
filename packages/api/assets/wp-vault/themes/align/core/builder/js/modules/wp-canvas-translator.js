/**
 * Align Theme Builder - AI Canvas Content Translator & Section Controls
 *
 * Traverses live canvas DOM text nodes via TreeWalker and translates them
 * to the target language using Align_Builder_Translator REST endpoints.
 * Supports full-page translation, individual section translation, and
 * restoring individual sections from original English content.
 *
 * @package Align
 */
window.AlignBuilderModules = window.AlignBuilderModules || {};

window.AlignBuilderModules.WpCanvasTranslator = {

    /**
     * Initialize toolbar button listeners and tooltips
     */
    init: function() {
        const translateSecBtn = document.getElementById('translate-section-btn');
        const copySecBtn      = document.getElementById('copy-original-section-btn');

        if (translateSecBtn) {
            translateSecBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.translateSelectedSection();
            });
            this.initTooltip(translateSecBtn, 'AI Translate Section');
        }

        if (copySecBtn) {
            copySecBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.copySelectedOriginalSection();
            });
            this.initTooltip(copySecBtn, 'Restore Section from Original (English)');
        }

        window.addEventListener('Vvveb.Builder.selectNode', () => {
            this.updateToolbarState();
        });

        console.log('[Align CanvasTranslator] Initialized with section-level translation and copy controls');
    },

    /**
     * Initialize Bootstrap tooltip on a toolbar button
     */
    initTooltip: function(btn, title) {
        if (!btn) return;
        if (window.AlignBuilderModules?.WpToolbarTooltips?.updateTitle) {
            window.AlignBuilderModules.WpToolbarTooltips.updateTitle(btn, title);
            return;
        }
        btn.setAttribute('data-bs-original-title', title);
        btn.removeAttribute('title');
        if (window.bootstrap && window.bootstrap.Tooltip) {
            try {
                new window.bootstrap.Tooltip(btn, { trigger: 'hover', placement: 'bottom', container: 'body', customClass: 'align-toolbar-tooltip', animation: true, delay: { show: 100, hide: 50 } });
            } catch (err) {}
        }
    },

    /**
     * Update toolbar buttons state based on active language and selection
     */
    updateToolbarState: function() {
        const config = window.VvvebWpConfig || {};
        const isTranslation = !!(config.alignActiveLang && config.alignDefaultLang && config.alignActiveLang !== config.alignDefaultLang);
        const copySecBtn = document.getElementById('copy-original-section-btn');
        const translateSecBtn = document.getElementById('translate-section-btn');

        if (copySecBtn) {
            // Highlight copy button if on translated page
            copySecBtn.style.display = isTranslation ? 'inline-flex' : '';
        }

        if (translateSecBtn) {
            const el = window.Vvveb && window.Vvveb.Builder ? window.Vvveb.Builder.selectedEl : null;
            const tag = el && el.tagName ? el.tagName.toUpperCase() : '';
            const leafTags = ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'SPAN', 'A', 'BUTTON', 'LI', 'LABEL', 'BLOCKQUOTE'];
            let title = 'AI Translate Section';
            if (leafTags.includes(tag)) {
                const friendlyName = (tag === 'P') ? 'Paragraph' : (tag.startsWith('H') ? 'Heading' : tag.charAt(0) + tag.slice(1).toLowerCase());
                title = `AI Translate ${friendlyName}`;
            }
            this.initTooltip(translateSecBtn, title);
        }
    },

    /**
     * Resolve the active live canvas Document object across different Vvveb setups.
     */
    getCanvasDocument: function() {
        if (window.FrameDocument && window.FrameDocument.body) return window.FrameDocument;
        if (window.Vvveb?.Builder?.iframe?.contentDocument?.body) return window.Vvveb.Builder.iframe.contentDocument;
        const domIframe = document.querySelector('#iframe-wrapper iframe, #canvas-frame, iframe.active-canvas');
        return domIframe?.contentDocument?.body ? domIframe.contentDocument : null;
    },

    /**
     * Resolve target element: prioritizes the selected text node (P, H1-H6, A, SPAN, etc.)
     * or falls back to nearest section container if a structural container is selected.
     */
    resolveTargetElement: function() {
        const el = window.Vvveb && window.Vvveb.Builder ? window.Vvveb.Builder.selectedEl : null;
        if (!el) {
            return null;
        }

        const tag = el.tagName ? el.tagName.toUpperCase() : '';
        const leafTags = ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'SPAN', 'A', 'BUTTON', 'LI', 'LABEL', 'BLOCKQUOTE', 'SMALL', 'STRONG', 'EM', 'B', 'I'];
        
        // If a specific text element is selected, translate that element only!
        if (leafTags.includes(tag) || (el.children && el.children.length === 0)) {
            return el;
        }

        // Otherwise, search for nearest section or container
        const section = el.closest('section, main > *, header, footer, [class*="section"], [class*="block"]') || el;
        return section;
    },

    /**
     * Legacy section resolution alias for restoring original section.
     */
    resolveTargetSection: function() {
        const el = window.Vvveb && window.Vvveb.Builder ? window.Vvveb.Builder.selectedEl : null;
        if (!el) return null;
        return el.closest('section, main > *, header, footer, [class*="section"], [class*="block"]') || el;
    },

    /**
     * Translate the currently selected element or section
     */
    translateSelectedSection: function() {
        const target = this.resolveTargetElement();
        if (!target) {
            if (typeof displayToast === 'function') {
                displayToast('warning', 'No Element Selected', 'Please click to select an element or section on the canvas first.');
            }
            return;
        }

        const config = window.VvvebWpConfig || {};
        const targetLang = config.alignActiveLang || 'zh';
        const btn = document.getElementById('translate-section-btn');

        if (btn) {
            btn.classList.add('disabled');
            btn.innerHTML = '<i class="la la-spinner la-spin"></i>';
        }

        const tag = target.tagName ? target.tagName.toUpperCase() : 'ELEMENT';
        const isLeaf = ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'SPAN', 'A', 'BUTTON', 'LI', 'LABEL', 'BLOCKQUOTE'].includes(tag);
        const targetLabel = isLeaf ? (tag === 'P' ? 'paragraph' : 'element') : 'section';

        if (typeof displayToast === 'function') {
            displayToast('info', 'AI Translation', `Translating selected ${targetLabel} to ${targetLang.toUpperCase()}...`);
        }

        this.translateElementNodes(target, targetLang, () => {
            if (btn) {
                btn.classList.remove('disabled');
                btn.innerHTML = '<i class="la la-language"></i>';
            }
        });
    },

    /**
     * Restore the currently selected section from linked original English content
     */
    copySelectedOriginalSection: function() {
        const section = this.resolveTargetSection();
        if (!section) {
            if (typeof displayToast === 'function') {
                displayToast('warning', 'No Element Selected', 'Please click to select a section on the canvas first.');
            }
            return;
        }

        const config = window.VvvebWpConfig || {};
        const restUrl = config.restCopyOriginalSectionUrl;
        if (!restUrl) {
            console.error('[Align CanvasTranslator] restCopyOriginalSectionUrl not configured');
            return;
        }

        const doc = section.ownerDocument || this.getCanvasDocument();
        const allSections = Array.from(doc.querySelectorAll('main > section, body > section, section'));
        const sectionIndex = allSections.indexOf(section);
        const sectionId = section.getAttribute('id') || '';
        const sectionClasses = section.getAttribute('class') || '';

        const btn = document.getElementById('copy-original-section-btn');
        if (btn) {
            btn.classList.add('disabled');
            btn.innerHTML = '<i class="la la-spinner la-spin"></i>';
        }

        if (typeof displayToast === 'function') {
            displayToast('info', 'Restoring Original', 'Fetching original section from English content...');
        }

        fetch(restUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.restNonce
            },
            body: JSON.stringify({
                post_id: config.activePostId,
                section_index: sectionIndex >= 0 ? sectionIndex : null,
                section_id: sectionId,
                section_classes: sectionClasses
            })
        })
        .then(r => r.json())
        .then(res => {
            if (btn) {
                btn.classList.remove('disabled');
                btn.innerHTML = '<i class="la la-history"></i>';
            }

            if (res.success && res.html) {
                const temp = doc.createElement('div');
                temp.innerHTML = res.html;
                const newSection = temp.firstElementChild;

                if (newSection && section.parentNode) {
                    section.parentNode.replaceChild(newSection, section);

                    if (window.Vvveb && window.Vvveb.Builder && typeof window.Vvveb.Builder.selectNode === 'function') {
                        window.Vvveb.Builder.selectNode(newSection);
                    }

                    // Enable Save button
                    document.querySelectorAll('#top-panel .save-btn, .save-btn').forEach(b => {
                        b.removeAttribute('disabled');
                        b.disabled = false;
                    });

                    if (typeof displayToast === 'function') {
                        displayToast('success', 'Original Restored', 'Section restored from English! Click AI Translate Section if you wish to retranslate it.');
                    }
                    console.log(`[Align CanvasTranslator] Section #${sectionIndex} successfully replaced with original English HTML`);
                }
            } else {
                const msg = res.message || 'Could not find matching original section.';
                if (typeof displayToast === 'function') displayToast('danger', 'Restore Failed', msg);
                console.error('[Align CanvasTranslator] Restore section failed:', msg);
            }
        })
        .catch(err => {
            if (btn) {
                btn.classList.remove('disabled');
                btn.innerHTML = '<i class="la la-history"></i>';
            }
            console.error('[Align CanvasTranslator] Network error restoring section:', err);
            if (typeof displayToast === 'function') displayToast('danger', 'Network Error', 'Failed to connect to server.');
        });
    },

    /**
     * Traverses and translates text nodes within a given DOM root container
     */
    translateElementNodes: function(rootEl, targetLang, onComplete) {
        const config = window.VvvebWpConfig || {};
        const url = config.restTranslateContentUrl;

        if (!url || !rootEl) {
            if (typeof onComplete === 'function') onComplete();
            return;
        }

        const items = [];
        const walker = rootEl.ownerDocument.createTreeWalker(rootEl, NodeFilter.SHOW_TEXT, {
            acceptNode: function(node) {
                const parentTag = node.parentElement ? node.parentElement.tagName.toUpperCase() : '';
                if (['SCRIPT', 'STYLE', 'NOSCRIPT', 'CODE', 'PRE'].includes(parentTag)) {
                    return NodeFilter.FILTER_REJECT;
                }
                const val = (node.nodeValue || '').trim();
                if (val.length >= 2 && /\p{L}/u.test(val)) {
                    return NodeFilter.FILTER_ACCEPT;
                }
                return NodeFilter.FILTER_SKIP;
            }
        });

        let cur;
        while ((cur = walker.nextNode())) {
            items.push({ type: 'text', node: cur, text: cur.nodeValue.trim() });
        }

        // Form placeholders (input, textarea)
        rootEl.querySelectorAll('input[placeholder], textarea[placeholder]').forEach(inp => {
            const ph = (inp.getAttribute('placeholder') || '').trim();
            if (ph.length >= 2 && /\p{L}/u.test(ph)) {
                items.push({ type: 'placeholder', el: inp, text: ph });
            }
        });

        // Submit and button values
        rootEl.querySelectorAll('input[type="submit"][value], input[type="button"][value]').forEach(btnEl => {
            const val = (btnEl.getAttribute('value') || '').trim();
            if (val.length >= 2 && /\p{L}/u.test(val)) {
                items.push({ type: 'value', el: btnEl, text: val });
            }
        });

        if (items.length === 0) {
            if (typeof displayToast === 'function') {
                displayToast('info', 'No Text Found', 'No translatable text elements found in selected section.');
            }
            if (typeof onComplete === 'function') onComplete();
            return;
        }

        const texts = items.map(it => it.text);
        console.log(`[Align CanvasTranslator] Translating section with ${texts.length} items (texts + form fields) to '${targetLang}'`);

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.restNonce },
            body: JSON.stringify({ texts: texts, target_lang: targetLang }),
        })
        .then(r => r.json())
        .then(res => {
            if (res.success && Array.isArray(res.translated)) {
                let replacedCount = 0;
                res.translated.forEach((trans, idx) => {
                    const it = items[idx];
                    if (!trans || !it) return;
                    if (it.type === 'text' && it.node) {
                        it.node.nodeValue = trans;
                        replacedCount++;
                    } else if (it.type === 'placeholder' && it.el) {
                        it.el.setAttribute('placeholder', trans);
                        it.el.placeholder = trans;
                        replacedCount++;
                    } else if (it.type === 'value' && it.el) {
                        it.el.setAttribute('value', trans);
                        it.el.value = trans;
                        replacedCount++;
                    }
                });

                // Also sync with Vvveb right-panel inputs if currently focused
                try {
                    const selEl = window.Vvveb && window.Vvveb.Builder ? window.Vvveb.Builder.selectedEl : null;
                    if (selEl && (selEl === rootEl || rootEl.contains(selEl))) {
                        const panelInputs = document.querySelectorAll('#right-panel textarea, #right-panel input[type="text"]');
                        panelInputs.forEach(inp => {
                            const val = (inp.value || '').trim();
                            if (val && texts.includes(val)) {
                                const matchIdx = texts.indexOf(val);
                                if (res.translated[matchIdx]) {
                                    inp.value = res.translated[matchIdx];
                                }
                            }
                        });
                    }
                } catch (syncErr) {
                    console.warn('[Align CanvasTranslator] Right panel input sync warning:', syncErr);
                }

                // Also localize internal href links in this container
                this.localizeElementLinks(rootEl, targetLang, (localizedLinksCount) => {
                    if (typeof onComplete === 'function') onComplete();

                    // Trigger Vvveb save state
                    document.querySelectorAll('#top-panel .save-btn, .save-btn').forEach(b => {
                        b.removeAttribute('disabled');
                        b.disabled = false;
                    });

                    const linkMsg = localizedLinksCount > 0 ? ` and localized ${localizedLinksCount} link${localizedLinksCount > 1 ? 's' : ''}` : '';
                    if (typeof displayToast === 'function') {
                        displayToast('success', 'Translation Complete', `Successfully translated ${replacedCount} text elements${linkMsg}. Click Save to keep changes.`);
                    }
                    console.log(`[Align CanvasTranslator] Translation applied: ${replacedCount} text nodes, ${localizedLinksCount} links localized`);
                });
            } else {
                if (typeof onComplete === 'function') onComplete();
                const msg = res.message || 'Translation failed.';
                if (typeof displayToast === 'function') displayToast('danger', 'Translation Error', msg);
                console.error('[Align CanvasTranslator] Section translation failed:', msg);
            }
        })
        .catch(err => {
            if (typeof onComplete === 'function') onComplete();
            console.error('[Align CanvasTranslator] Section translation error:', err);
            if (typeof displayToast === 'function') displayToast('danger', 'Network Error', 'Failed to connect to AI translation service.');
        });
    },

    /**
     * Localizes internal links within a given DOM root container
     */
    localizeElementLinks: function(rootEl, targetLang, callback) {
        const config = window.VvvebWpConfig || {};
        const url = config.restLocalizeUrlsUrl;
        if (!url || !rootEl) {
            if (typeof callback === 'function') callback(0);
            return;
        }

        const links = Array.from(rootEl.querySelectorAll('a[href]')).filter(a => {
            if (a.hasAttribute('data-no-localize') || a.closest('[data-no-localize]')) return false;
            const h = (a.getAttribute('href') || '').trim();
            if (!h || h.startsWith('#') || h.startsWith('mailto:') || h.startsWith('tel:') || h.startsWith('javascript:')) return false;
            return true;
        });

        if (links.length === 0) {
            if (typeof callback === 'function') callback(0);
            return;
        }

        const rawUrls = Array.from(new Set(links.map(a => a.getAttribute('href').trim())));
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.restNonce },
            body: JSON.stringify({ urls: rawUrls, target_lang: targetLang })
        })
        .then(r => r.json())
        .then(res => {
            let count = 0;
            if (res.success && res.urls) {
                links.forEach(a => {
                    const orig = a.getAttribute('href').trim();
                    if (res.urls[orig] && res.urls[orig] !== orig) {
                        a.setAttribute('href', res.urls[orig]);
                        count++;
                    }
                });
            }
            if (typeof callback === 'function') callback(count);
        })
        .catch(err => {
            console.warn('[Align CanvasTranslator] URL localization skipped on error:', err);
            if (typeof callback === 'function') callback(0);
        });
    },

    /**
     * Translate the current full canvas content to target language.
     *
     * @param {string} targetLang Language code (e.g. 'zh').
     */
    translateCanvas: function(targetLang) {
        const config = window.VvvebWpConfig || {};
        targetLang = targetLang || config.alignActiveLang || 'zh';

        const doc = this.getCanvasDocument();
        if (!doc || !doc.body) {
            console.warn('[Align CanvasTranslator] Canvas document not ready or empty');
            if (typeof displayToast === 'function') {
                displayToast('warning', 'Canvas Empty', 'Canvas document is still loading or not ready.');
            }
            return;
        }

        const btn = document.getElementById('align-ai-translate-page-btn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="la la-spinner la-spin"></i> <span>Translating...</span>';
        }

        if (typeof displayToast === 'function') {
            displayToast('info', 'AI Translation', `Translating canvas to ${targetLang.toUpperCase()}...`);
        }

        this.translateElementNodes(doc.body, targetLang, () => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="la la-magic"></i> <span>AI Translate</span>';
            }
        });
    }
};

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.AlignBuilderModules.WpCanvasTranslator.init();
});
