/**
 * Align Theme Builder - Dynamic Style Injector & Dummy Image Replacement
 */

window.AlignBuilderModules = window.AlignBuilderModules || {};

window.AlignBuilderModules.WpStyleInjector = {
    _themeSheetsCache: new Map(),
    _customCssSheet: null,
    _cachedAdoptedSheets: [],
    _hookedWindows: (typeof WeakSet !== 'undefined' ? new WeakSet() : new Set()),
    _inFlightThemeCss: new Map(),

    injectThemeStylesIntoIframe: function() {
        const wpConfig = window.VvvebWpConfig || {};
        const frameDoc = window.FrameDocument || (window.Vvveb && window.Vvveb.Builder && window.Vvveb.Builder.iframe ? window.Vvveb.Builder.iframe.contentDocument : null);
        const frameWin = window.FrameWindow || (window.Vvveb && window.Vvveb.Builder && window.Vvveb.Builder.iframe ? window.Vvveb.Builder.iframe.contentWindow : null) || (frameDoc ? frameDoc.defaultView : null);
        if (!frameDoc || !frameDoc.head) return;

        // 1. Inject Theme CSS stylesheets
        const themeCssUrls = wpConfig.themeCssUrls || [];
        themeCssUrls.forEach(url => {
            if (!url || typeof url !== 'string') return;
            const existingLink = Array.from(frameDoc.querySelectorAll('link[rel="stylesheet"]')).some(l => l.getAttribute('href') === url);
            if (!existingLink) {
                const link = frameDoc.createElement('link');
                link.rel = 'stylesheet';
                link.href = url;
                link.setAttribute('data-vvveb-helpers', 'true');
                frameDoc.head.appendChild(link);
            }
        });

        // 2. Inject Custom CSS
        if (wpConfig.customCss && !frameDoc.querySelector('#align-custom-css')) {
            const style = frameDoc.createElement('style');
            style.id = 'align-custom-css';
            style.setAttribute('data-vvveb-helpers', 'true');
            style.textContent = wpConfig.customCss;
            frameDoc.head.appendChild(style);
        }

        // 3. Inject Theme JS assets
        const themeJsAssets = wpConfig.themeJsAssets || [];
        themeJsAssets.forEach(asset => {
            const url = typeof asset === 'string' ? asset : (asset && asset.url);
            if (!url) return;
            const existingScript = Array.from(frameDoc.querySelectorAll('script[src]')).some(s => s.getAttribute('src') === url);
            if (!existingScript) {
                const script = frameDoc.createElement('script');
                script.src = url;
                script.setAttribute('data-vvveb-helpers', 'true');
                frameDoc.head.appendChild(script);
            }
        });

        // 4. Inject Custom JS
        if (wpConfig.customJs && !frameDoc.querySelector('#align-custom-js')) {
            const customScript = frameDoc.createElement('script');
            customScript.id = 'align-custom-js';
            customScript.setAttribute('data-vvveb-helpers', 'true');
            customScript.textContent = wpConfig.customJs;
            frameDoc.head.appendChild(customScript);
        }

        this.initShadowRootStyleSheets(frameDoc, frameWin);
        this.normalizeImagesInIframe();
        this.replaceDummyImagesInIframe();
    },

    initShadowRootStyleSheets: function(frameDoc, frameWin) {
        frameDoc = frameDoc || window.FrameDocument || (window.Vvveb && window.Vvveb.Builder && window.Vvveb.Builder.iframe ? window.Vvveb.Builder.iframe.contentDocument : null);
        frameWin = frameWin || window.FrameWindow || (window.Vvveb && window.Vvveb.Builder && window.Vvveb.Builder.iframe ? window.Vvveb.Builder.iframe.contentWindow : null) || (frameDoc ? frameDoc.defaultView : null);

        if (frameWin && frameWin.Element && frameWin.Element.prototype && frameWin.Element.prototype.attachShadow) {
            if (!this._hookedWindows.has(frameWin) && !frameWin._alignAttachShadowHooked) {
                this._hookedWindows.add(frameWin);
                frameWin._alignAttachShadowHooked = true;
                const originalAttachShadow = frameWin.Element.prototype.attachShadow;
                const self = this;
                frameWin.Element.prototype.attachShadow = function(init) {
                    const shadowRoot = originalAttachShadow.call(this, init);
                    try {
                        self.injectAssetsIntoShadowRoot(shadowRoot);
                    } catch (err) {
                        console.warn('[AlignStyleInjector] Error injecting assets into shadow root:', err);
                    }
                    return shadowRoot;
                };
            }
        }

        if (this._activeFrameWin !== frameWin) {
            this._activeFrameWin = frameWin;
            this._themeSheetsCache.clear();
            this._customCssSheet = null;
            this._cachedAdoptedSheets = [];
        }

        const wpConfig = window.VvvebWpConfig || {};
        const StyleSheetClass = (frameWin && frameWin.CSSStyleSheet) || null;

        if (StyleSheetClass) {
            if (wpConfig.customCss) {
                this.updateCustomCss(wpConfig.customCss, StyleSheetClass);
            }
            this._rebuildCachedAdoptedSheets();
            if (wpConfig.themeCssUrls && wpConfig.themeCssUrls.length > 0) {
                this._loadThemeCssUrls(wpConfig.themeCssUrls, StyleSheetClass, frameDoc);
            }
        }

        if (frameDoc) {
            this.injectIntoShadowRoots(frameDoc.body || frameDoc);
        }
    },

    _rebuildCachedAdoptedSheets: function() {
        const wpConfig = window.VvvebWpConfig || {};
        const themeCssUrls = wpConfig.themeCssUrls || [];
        const sheets = [];

        themeCssUrls.forEach(url => {
            if (this._themeSheetsCache.has(url)) {
                sheets.push(this._themeSheetsCache.get(url));
            }
        });

        if (this._customCssSheet) {
            sheets.push(this._customCssSheet);
        }

        this._cachedAdoptedSheets = sheets;
    },

    _loadThemeCssUrls: function(themeCssUrls, StyleSheetClass, frameDoc) {
        if (!themeCssUrls || !Array.isArray(themeCssUrls) || !StyleSheetClass) return;

        let hasNewSheets = false;
        const fetchPromises = [];

        themeCssUrls.forEach(url => {
            if (!url || typeof url !== 'string' || this._themeSheetsCache.has(url)) {
                return;
            }

            let promise = this._inFlightThemeCss.get(url);
            if (!promise) {
                promise = fetch(url)
                    .then(res => {
                        if (!res.ok) throw new Error(`HTTP ${res.status}`);
                        return res.text();
                    })
                    .catch(err => {
                        console.warn(`[AlignStyleInjector] Failed to fetch theme CSS at ${url}:`, err);
                        return '';
                    })
                    .finally(() => {
                        this._inFlightThemeCss.delete(url);
                    });
                this._inFlightThemeCss.set(url, promise);
            }

            fetchPromises.push(
                promise.then(cssText => {
                    if (cssText) {
                        try {
                            const sheet = new StyleSheetClass();
                            sheet.replaceSync(cssText);
                            this._themeSheetsCache.set(url, sheet);
                            hasNewSheets = true;
                        } catch (err) {
                            console.warn(`[AlignStyleInjector] Failed to construct stylesheet for ${url}:`, err);
                        }
                    }
                })
            );
        });

        if (fetchPromises.length > 0) {
            Promise.all(fetchPromises).then(() => {
                if (hasNewSheets) {
                    this._rebuildCachedAdoptedSheets();
                    const doc = frameDoc || window.FrameDocument || (window.Vvveb && window.Vvveb.Builder && window.Vvveb.Builder.iframe ? window.Vvveb.Builder.iframe.contentDocument : null);
                    if (doc) {
                        this.injectIntoShadowRoots(doc.body || doc);
                    }
                }
            });
        }
    },

    injectAssetsIntoShadowRoot: function(shadowRoot) {
        if (!shadowRoot) return;

        const wpConfig = window.VvvebWpConfig || {};
        const doc = (shadowRoot && shadowRoot.ownerDocument) || (shadowRoot && shadowRoot.host && shadowRoot.host.ownerDocument) || window.FrameDocument || document;

        // 1. Inject Theme CSS stylesheets
        const themeCssUrls = wpConfig.themeCssUrls || [];
        themeCssUrls.forEach(url => {
            if (!url || typeof url !== 'string') return;
            const existingLink = Array.from(shadowRoot.querySelectorAll('link')).some(l => l.getAttribute('href') === url);
            if (!existingLink) {
                const link = doc.createElement('link');
                link.rel = 'stylesheet';
                link.href = url;
                link.setAttribute('data-vvveb-helpers', 'true');
                shadowRoot.appendChild(link);
            }
        });

        // 2. Inject Custom CSS
        if (wpConfig.customCss) {
            let customStyle = shadowRoot.querySelector('#align-custom-css');
            if (!customStyle) {
                customStyle = doc.createElement('style');
                customStyle.id = 'align-custom-css';
                customStyle.setAttribute('data-vvveb-helpers', 'true');
                shadowRoot.appendChild(customStyle);
            }
            if (customStyle.textContent !== wpConfig.customCss) {
                customStyle.textContent = wpConfig.customCss;
            }
        }

        // 3. Inject page/block scoped styles with ancestor selector transformation
        try {
            const hostNode = shadowRoot.host;
            const styleNodes = doc.querySelectorAll('style[id^="style-"], style[id^="align-style-"], style[id^="vvvebjs-styles"]');
            if (styleNodes && styleNodes.length > 0) {
                const ancestorIds = [];
                let curr = hostNode;
                while (curr && curr !== doc.body && curr !== doc.documentElement) {
                    if (curr.id) ancestorIds.push(curr.id);
                    curr = curr.parentElement;
                }
                let combinedCss = '';
                styleNodes.forEach(s => {
                    if (s.id === 'align-host-context-styles' || s.id === 'align-custom-css') return;
                    if (s.textContent) combinedCss += s.textContent + '\n';
                });
                if (combinedCss.trim()) {
                    let transformedCss = '';
                    if (ancestorIds.length > 0) {
                        ancestorIds.forEach(aId => {
                            const idRegex = new RegExp('#' + aId + '\\s+', 'g');
                            if (idRegex.test(combinedCss)) {
                                transformedCss += combinedCss.replace(idRegex, ':host-context(#' + aId + ') ') + '\n' + combinedCss.replace(idRegex, '') + '\n';
                            }
                        });
                    }
                    if (!transformedCss) {
                        transformedCss = combinedCss.replace(/#([\w\-]+)\s+/g, ':host-context(#$1) ') + '\n' + combinedCss.replace(/#([\w\-]+)\s+/g, '');
                    }
                    let hostStyle = shadowRoot.querySelector('#align-host-context-styles');
                    if (!hostStyle) {
                        hostStyle = doc.createElement('style');
                        hostStyle.id = 'align-host-context-styles';
                        hostStyle.setAttribute('data-vvveb-helpers', 'true');
                        shadowRoot.appendChild(hostStyle);
                    }
                    if (hostStyle.textContent !== transformedCss) {
                        hostStyle.textContent = transformedCss;
                        console.log('[AlignStyleInjector] Injected transformed scoped styles into shadow root (length: ' + transformedCss.length + ')');
                    }
                }
            }
        } catch (e) {
            console.warn('[AlignStyleInjector] Error injecting host context styles:', e);
        }

        // 4. Apply adoptedStyleSheets
        this.applyShadowRootStyleSheets(shadowRoot);
    },

    applyShadowRootStyleSheets: function(shadowRoot) {
        if (!shadowRoot || !('adoptedStyleSheets' in shadowRoot)) return;
        if (!this._cachedAdoptedSheets || this._cachedAdoptedSheets.length === 0) return;

        try {
            const currentAdopted = shadowRoot.adoptedStyleSheets ? Array.from(shadowRoot.adoptedStyleSheets) : [];
            let modified = false;
            const newAdopted = [...currentAdopted];

            for (let i = 0; i < this._cachedAdoptedSheets.length; i++) {
                const sheet = this._cachedAdoptedSheets[i];
                if (sheet && !newAdopted.includes(sheet)) {
                    newAdopted.push(sheet);
                    modified = true;
                }
            }

            if (modified) {
                shadowRoot.adoptedStyleSheets = newAdopted;
            }
        } catch (err) {
            // Sharing constructed stylesheets across document/window boundaries is restricted by browser security
            // Safe fallback: <link rel="stylesheet"> tags injected directly in shadowRoot handle styles
        }
    },

    injectIntoShadowRoots: function(rootNode) {
        if (!rootNode) return;

        if (rootNode.shadowRoot) {
            this.injectAssetsIntoShadowRoot(rootNode.shadowRoot);
            this.injectIntoShadowRoots(rootNode.shadowRoot);
        }

        if (rootNode.querySelectorAll) {
            const elements = rootNode.querySelectorAll('*');
            for (let i = 0; i < elements.length; i++) {
                const el = elements[i];
                if (el.shadowRoot) {
                    this.injectAssetsIntoShadowRoot(el.shadowRoot);
                    this.injectIntoShadowRoots(el.shadowRoot);
                }
            }
        }
    },

    updateCustomCss: function(customCss, targetStyleSheetClass) {
        const cssText = typeof customCss === 'string' ? customCss : '';
        const StyleSheetClass = targetStyleSheetClass || (window.FrameWindow && window.FrameWindow.CSSStyleSheet) || (typeof CSSStyleSheet !== 'undefined' ? CSSStyleSheet : null);

        if (!this._customCssSheet && StyleSheetClass) {
            try {
                this._customCssSheet = new StyleSheetClass();
                this._rebuildCachedAdoptedSheets();
            } catch (e) {
                console.warn('[AlignStyleInjector] Error creating customCss stylesheet:', e);
            }
        }

        if (this._customCssSheet) {
            try {
                this._customCssSheet.replaceSync(cssText);
            } catch (e) {
                console.warn('[AlignStyleInjector] Error updating customCss stylesheet:', e);
            }
        }
    },

    shouldIgnoreImage: function(url) {
        if (!url || typeof url !== 'string') return true;
        const trimmed = url.trim();
        if (!trimmed || trimmed === 'none' || trimmed.startsWith('#')) return true;

        const wpConfig = window.VvvebWpConfig || {};
        const dummyImgUrl = wpConfig.dummyImgUrl || 'https://picsum.photos/seed/align/600/400';

        // Case 2: Already a dummy or placeholder image
        if (trimmed.includes('picsum.photos') || trimmed.includes('placeholder') || trimmed === dummyImgUrl) {
            return true;
        }

        // Case 1: Valid WordPress, data/blob, or absolute URLs
        if (trimmed.includes('wp-content') || trimmed.includes('wp-includes') || trimmed.includes('uploads')) {
            return true;
        }
        if (trimmed.startsWith('data:') || trimmed.startsWith('blob:')) {
            return true;
        }
        if (/^(?:https?:)?\/\//i.test(trimmed)) {
            return true;
        }

        return false;
    },

    shouldSkipElement: function(el) {
        if (!el || !el.closest) return false;
        if (el.closest('[data-vvveb-helpers]') || el.hasAttribute('data-vvveb-helpers') || el.classList.contains('vvveb-helper')) {
            return true;
        }
        if (el.closest('[data-component-shortcode], [data-component-wp-block], [data-component-post-content]')) {
            return true;
        }
        if (typeof ShadowRoot !== 'undefined' && el.getRootNode && el.getRootNode() instanceof ShadowRoot) {
            return true;
        }
        return false;
    },

    normalizeImagesInIframe: function(rootNode) {
        const frameDoc = window.FrameDocument || (window.Vvveb && window.Vvveb.Builder && window.Vvveb.Builder.iframe ? window.Vvveb.Builder.iframe.contentDocument : null);
        if (!frameDoc || !frameDoc.body) return;

        const targetRoot = rootNode || frameDoc.body;
        if (!targetRoot || !targetRoot.querySelectorAll) return;

        const processImg = (img) => {
            if (this.shouldSkipElement(img)) return;
            if (!img.hasAttribute('decoding')) {
                img.setAttribute('decoding', 'async');
            }
        };

        if (targetRoot.tagName === 'IMG') {
            processImg(targetRoot);
        }
        targetRoot.querySelectorAll('img').forEach(processImg);
    },

    _isReplacingImages: false,

    replaceDummyImagesInIframe: function(rootNode) {
        if (this._isReplacingImages) return;
        this._isReplacingImages = true;

        try {
            const wpConfig = window.VvvebWpConfig || {};
            const frameDoc = window.FrameDocument || (window.Vvveb && window.Vvveb.Builder && window.Vvveb.Builder.iframe ? window.Vvveb.Builder.iframe.contentDocument : null);
            if (!frameDoc || !frameDoc.body) return;

            const targetRoot = rootNode || frameDoc.body;
            if (!targetRoot || !targetRoot.querySelectorAll) return;

            const dummyImgUrl = wpConfig.dummyImgUrl || 'https://picsum.photos/seed/align/600/400';
            const self = this;

            // 1. Process <img> elements
            const processImg = function(img) {
                if (self.shouldSkipElement(img)) return;
                const dataSrc = img.getAttribute('data-src'), src = img.getAttribute('src');
                if (dataSrc && !self.shouldIgnoreImage(dataSrc)) img.setAttribute('data-src', dummyImgUrl);
                if (src && !self.shouldIgnoreImage(src)) img.setAttribute('src', dummyImgUrl);
                else if (dataSrc && !src) img.setAttribute('src', dataSrc);
            };
            if (targetRoot.tagName === 'IMG') processImg(targetRoot);
            targetRoot.querySelectorAll('img').forEach(processImg);

            // 2. Process <picture> <source> elements
            const processSource = function(source) {
                if (self.shouldSkipElement(source)) return;
                const srcset = source.getAttribute('srcset'), dataSrcset = source.getAttribute('data-srcset');
                if (srcset && !self.shouldIgnoreImage(srcset)) source.setAttribute('srcset', dummyImgUrl);
                if (dataSrcset && !self.shouldIgnoreImage(dataSrcset)) source.setAttribute('data-srcset', dummyImgUrl);
                if (!source.getAttribute('srcset') && dataSrcset) source.setAttribute('srcset', dataSrcset);
            };
            if (targetRoot.tagName === 'SOURCE') processSource(targetRoot);
            targetRoot.querySelectorAll('picture source').forEach(processSource);

            // 3. Process video poster and video source
            targetRoot.querySelectorAll('video[data-poster]').forEach(v => {
                if (!self.shouldSkipElement(v) && !v.getAttribute('poster')) v.setAttribute('poster', v.getAttribute('data-poster'));
            });
            targetRoot.querySelectorAll('video source[data-src]').forEach(s => {
                if (!self.shouldSkipElement(s) && !s.getAttribute('src')) s.setAttribute('src', s.getAttribute('data-src'));
            });

            // 4. Process iframe[data-src]
            targetRoot.querySelectorAll('iframe[data-src]').forEach(f => {
                if (!self.shouldSkipElement(f) && !f.getAttribute('src')) f.setAttribute('src', f.getAttribute('data-src'));
            });

            // 5. Process [data-bg-src] elements
            targetRoot.querySelectorAll('[data-bg-src]').forEach(el => {
                if (self.shouldSkipElement(el)) return;
                let bgSrc = el.getAttribute('data-bg-src');
                if (bgSrc && !self.shouldIgnoreImage(bgSrc)) { bgSrc = dummyImgUrl; el.setAttribute('data-bg-src', dummyImgUrl); }
                if (bgSrc && !bgSrc.startsWith('data:image')) el.style.backgroundImage = 'url("' + bgSrc + '")';
            });

            // 6. Process inline style background-image
            targetRoot.querySelectorAll('[style*="url("], [style*="url ("]').forEach(el => {
                if (self.shouldSkipElement(el) || !el.style || !el.style.backgroundImage) return;
                const bg = el.style.backgroundImage, match = bg.match(/url\((?:['"]?)(.*?)(?:['"]?)\)/i);
                if (match && match[1] && !self.shouldIgnoreImage(match[1].trim())) el.style.backgroundImage = 'url("' + dummyImgUrl + '")';
            });
        } catch (e) {
            console.error('[AlignStyleInjector] Error replacing dummy images in iframe:', e);
        } finally {
            this._isReplacingImages = false;
        }
    }
};

window.replaceDummyImagesInIframe = function(rootNode) {
    window.AlignBuilderModules.WpStyleInjector.replaceDummyImagesInIframe(rootNode);
};

window.addEventListener("Vvveb.iframe.loaded", function() {
    if (window.AlignBuilderModules.WpStyleInjector) {
        window.AlignBuilderModules.WpStyleInjector.injectThemeStylesIntoIframe();
    }
});

window.addEventListener("Vvveb.FileManager.loadPage", function() {
    if (window.AlignBuilderModules.WpStyleInjector) {
        window.AlignBuilderModules.WpStyleInjector.injectThemeStylesIntoIframe();
    }
});
