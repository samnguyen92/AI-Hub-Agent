/**
 * Align Theme Builder - WordPress Media Library Modal Bridge
 * Auto-detects responsive image sizes and dynamic 3-tier breakpoints.
 */

window.AlignBuilderModules = window.AlignBuilderModules || {};

window.AlignBuilderModules.WpMediaModal = {
    getCleanUrl: function(rawUrl) {
        if (!rawUrl) return '';
        try {
            let parsed = new URL(rawUrl);
            return parsed.pathname + (parsed.search || '') + (parsed.hash || '');
        } catch(e) {
            return rawUrl.startsWith('/') ? rawUrl : '/' + rawUrl;
        }
    },

    getAbsoluteUrl: function(url) {
        if (!url) return '';
        if (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('//') || url.startsWith('data:')) {
            return url;
        }
        if (url.startsWith('/')) {
            return window.location.origin + url;
        }
        if (url.startsWith('wp-content/') || url.startsWith('wp-includes/')) {
            return window.location.origin + '/' + url;
        }
        return url;
    },

    resolveAttachmentSizes: function(attachment) {
        if (!attachment) return { mobileUrl: '', tabletUrl: '', desktopUrl: '', fullUrl: '', alt: '' };
        const fullUrl = this.getCleanUrl(attachment.url);
        const sizes = attachment.sizes || {};

        let mobileUrl = sizes.mobile_375?.url || sizes.medium?.url || sizes.thumbnail?.url || fullUrl;
        mobileUrl = this.getCleanUrl(mobileUrl);

        let tabletUrl = sizes.tablet_768?.url || sizes.medium_large?.url || sizes.large?.url || fullUrl;
        tabletUrl = this.getCleanUrl(tabletUrl);

        let desktopUrl = sizes.desktop_1125?.url || sizes.large?.url || sizes.full?.url || fullUrl;
        desktopUrl = this.getCleanUrl(desktopUrl);

        return {
            mobileUrl,
            tabletUrl,
            desktopUrl,
            fullUrl,
            alt: attachment.alt || attachment.title || ''
        };
    },

    /**
     * Fetch all actual registered image sizes generated in WordPress via REST API or AJAX.
     *
     * @param {number|string} attachmentId Attachment ID.
     * @return {Promise<Object|null>}
     */
    fetchAttachmentSizes: async function(attachmentId) {
        if (!attachmentId) return null;
        const wpConfig = window.VvvebWpConfig || {};
        const restUrl = wpConfig.restAttachmentSizesUrl;
        const ajaxUrl = wpConfig.ajaxUrl || '/wp-admin/admin-ajax.php';
        const nonce = wpConfig.restNonce || '';

        // 1. Try REST API endpoint
        if (restUrl) {
            try {
                const sep = restUrl.includes('?') ? '&' : '?';
                const res = await fetch(`${restUrl}${sep}id=${encodeURIComponent(attachmentId)}`, {
                    headers: { 'X-WP-Nonce': nonce }
                });
                if (res.ok) {
                    const data = await res.json();
                    if (data && data.sizes) {
                        return data;
                    }
                }
            } catch (e) {
                console.warn('[Align MediaModal] REST sizes fetch failed, trying admin-ajax:', e);
            }
        }

        // 2. Fallback to admin-ajax.php
        try {
            const formData = new URLSearchParams();
            formData.append('action', 'align_get_attachment_sizes');
            formData.append('id', attachmentId);
            const res = await fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success && json.data) {
                    return json.data;
                }
            }
        } catch (e) {
            console.error('[Align MediaModal] AJAX sizes fetch failed:', e);
        }

        return null;
    },


    /**
     * Resolve 3-tier art-direction picture breakpoints from attachment metadata.
     * Mobile (< 600px): largest sub-size <= 600px (e.g. 375x200)
     * Tablet (600px - 992px): largest sub-size between 600px and 1200px (e.g. 1200x1200 / 768px)
     * Desktop (> 992px): full primary source
     *
     * @param {Object} attachment Backbone attachment JSON.
     * @param {Array<Object>} serverSizes Array of server-generated sizes.
     * @param {string} fullUrl Primary full URL.
     * @param {number} fullWidth Full image width.
     * @param {number} fullHeight Full image height.
     * @return {Object} Breakpoints definition for picture sources.
     */
    resolvePictureBreakpoints: function(attachment, serverSizes, fullUrl, fullWidth, fullHeight, targetSlotWidth) {
        const sizesList = Array.isArray(serverSizes) && serverSizes.length > 0 ? serverSizes : Object.values(attachment.sizes || {});
        let mobile = null, tablet = null, desktop = null;
        const cleanFull = this.getCleanUrl(fullUrl);

        if (sizesList.length > 0) {
            const sorted = [...sizesList].filter(s => s && s.url && s.width).sort((a, b) => {
                const wa = parseInt(a.width, 10), wb = parseInt(b.width, 10);
                if (wa === wb) {
                    return Math.abs(wa - parseInt(a.height || a.width, 10)) - Math.abs(wb - parseInt(b.height || b.width, 10));
                }
                return wa - wb;
            });
            const contentSizes = sorted.filter(s => parseInt(s.width, 10) >= 150);
            const pool = contentSizes.length > 0 ? contentSizes : sorted;

            const findClosest = (cands, targetW, minW = 0) => {
                if (!cands || cands.length === 0) return null;
                let list = cands;
                if (minW > 0) {
                    const filtered = cands.filter(s => parseInt(s.width, 10) >= minW);
                    if (filtered.length > 0) list = filtered;
                }
                let best = list[0];
                let bestScore = Infinity;
                for (const s of list) {
                    const w = parseInt(s.width, 10);
                    const diff = Math.abs(w - targetW);
                    const penalty = w < targetW ? 1.25 : 1.0;
                    const origRatio = (fullHeight > 0 && fullWidth > 0) ? (fullHeight / fullWidth) : 0;
                    const idealH = origRatio > 0 ? (w * origRatio) : w;
                    const aspectDiff = Math.abs(parseInt(s.height || w, 10) - idealH) * 0.01;
                    const score = (diff * penalty) + aspectDiff;
                    if (score < bestScore) { bestScore = score; best = s; }
                }
                return best;
            };

            if (targetSlotWidth && targetSlotWidth > 0 && targetSlotWidth < 1200) {
                const targetMob = Math.min(targetSlotWidth, 599);
                const targetTab = Math.min(targetSlotWidth, 992);
                const targetDesk = Math.min(fullWidth || 1920, targetSlotWidth * 2);

                mobile = findClosest(pool, targetMob);
                const mobW = mobile ? parseInt(mobile.width, 10) : 0;
                tablet = findClosest(pool, targetTab, mobW);
                const tabW = tablet ? parseInt(tablet.width, 10) : mobW;
                desktop = findClosest(pool, targetDesk, tabW);
            } else {
                mobile = findClosest(pool, 599);
                const mobW = mobile ? parseInt(mobile.width, 10) : 0;
                tablet = findClosest(pool, 992, mobW);
                const tabW = tablet ? parseInt(tablet.width, 10) : mobW;
                desktop = findClosest(pool, 1440, tabW);
            }
        }

        const mobileUrl = mobile ? this.getCleanUrl(mobile.url) : cleanFull;
        const tabletUrl = tablet ? this.getCleanUrl(tablet.url) : cleanFull;
        const desktopUrl = desktop ? this.getCleanUrl(desktop.url) : cleanFull;

        return {
            mobile: {
                url: mobileUrl,
                width: mobile ? parseInt(mobile.width, 10) : (fullWidth || 375),
                height: mobile ? parseInt(mobile.height, 10) : (fullHeight || 200)
            },
            tablet: {
                url: tabletUrl,
                width: tablet ? parseInt(tablet.width, 10) : (fullWidth || 768),
                height: tablet ? parseInt(tablet.height, 10) : (fullHeight || 500)
            },
            desktop: {
                url: desktopUrl,
                width: desktop ? parseInt(desktop.width, 10) : (fullWidth || 1200),
                height: desktop ? parseInt(desktop.height, 10) : (fullHeight || 800)
            }
        };
    },

    /**
     * Clean redundant width: 100% and height: auto inline styles from an image element.
     * Removes the style attribute if it becomes empty.
     *
     * @param {HTMLImageElement} img Target image element.
     */
    cleanImageInlineStyle: function(img) {
        if (!img || !img.style) return;
        if (img.style.width === '100%') img.style.removeProperty('width');
        if (img.style.height === 'auto') img.style.removeProperty('height');
        const styleVal = img.getAttribute('style');
        if (styleVal !== null && styleVal.trim() === '') {
            img.removeAttribute('style');
        }
    },

    init: function() {
        if (typeof MediaModal !== "undefined") {
            const origMediaModalOpen = MediaModal.prototype.open;
            const self = this;

            MediaModal.prototype.open = function(element, callback) {
                if (window.wp && window.wp.media) {
                    const targetElAtOpen = window.Vvveb?.Builder?.selectedEl || null;
                    let targetInputSel = null;
                    let targetThumbSel = null;

                    if (element instanceof Element) {
                        targetInputSel = element.dataset.targetInput;
                        targetThumbSel = element.dataset.targetThumb;
                    } else if (element) {
                        targetInputSel = element.targetInput;
                        targetThumbSel = element.targetThumb;
                    }

                    let frame = wp.media({
                        title: 'Select Media from WordPress',
                        button: { text: 'Use this media' },
                        multiple: false
                    });

                    frame.on('select', async function() {
                        let attachment = frame.state().get('selection').first().toJSON();
                        let attachmentId = attachment.id;
                        let rawUrl = attachment.url;
                        let fullUrl = self.getCleanUrl(rawUrl);
                        let fullWidth = parseInt(attachment.width, 10) || 0;
                        let fullHeight = parseInt(attachment.height, 10) || 0;
                        let altText = attachment.alt || attachment.title || '';
                        let serverSizes = null;

                        // Fetch real WordPress generated sub-sizes via AJAX/REST
                        if (attachmentId) {
                            try {
                                const serverData = await self.fetchAttachmentSizes(attachmentId);
                                if (serverData && Array.isArray(serverData.sizes) && serverData.sizes.length > 0) {
                                    fullUrl = self.getCleanUrl(serverData.full_url || fullUrl);
                                    fullWidth = serverData.width || fullWidth;
                                    fullHeight = serverData.height || fullHeight;
                                    altText = serverData.alt || altText;
                                    serverSizes = serverData.sizes;
                                    console.log(`[Align MediaModal] AJAX fetched ${serverData.total_sizes} sizes for ID ${attachmentId}`);
                                }
                            } catch (e) {
                                console.warn('[Align MediaModal] Failed fetching server attachment sizes, using local fallback:', e);
                            }
                        }

                        if (callback) {
                            callback(fullUrl, attachment);
                        }

                        let inputEl = targetInputSel ? document.querySelector(targetInputSel) : null;
                        if (!inputEl && element instanceof Element) {
                            inputEl = element.closest("div") ? element.closest("div").querySelector('input[type="text"]') : null;
                        }

                        let thumbEl = targetThumbSel ? document.querySelector(targetThumbSel) : null;
                        if (!thumbEl && element instanceof Element) {
                            thumbEl = element.closest("div") ? element.closest("div").querySelector('img') : null;
                        }

                        if (thumbEl) {
                            const thumbUrl = self.getAbsoluteUrl(fullUrl);
                            thumbEl.src = thumbUrl;
                            thumbEl.setAttribute("src", thumbUrl);
                            thumbEl.onerror = function() {
                                this.onerror = null;
                                this.src = (window.Vvveb ? Vvveb.baseUrl : 'libs/builder/') + 'icons/image.svg';
                            };
                        }

                        if (inputEl) {
                            inputEl.value = fullUrl;
                            try {
                                inputEl.dispatchEvent(new Event('change', { bubbles: true }));
                                inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                            } catch (e) {
                                console.warn('[Align MediaModal] Error dispatching change/input event:', e);
                            }
                        }

                        let selectedEl = window.Vvveb?.Builder?.selectedEl || targetElAtOpen;
                        if (window.Vvveb && window.Vvveb.Builder && selectedEl) {
                            const isBgContext = (
                                targetInputSel === '#input-Image' ||
                                (inputEl && (inputEl.name === 'Image' || inputEl.getAttribute('name') === 'Image' || inputEl.id === 'input-Image')) ||
                                (element instanceof Element && (
                                    element.dataset?.targetInput === '#input-Image' ||
                                    element.closest?.('.section-background_image_header') ||
                                    element.closest?.('[data-property="Image"]') ||
                                    element.closest?.('[data-group="background"]')
                                ))
                            );

                            if (isBgContext) {
                                if (selectedEl && selectedEl.style) {
                                    selectedEl.style.backgroundImage = 'url("' + fullUrl + '")';
                                    if (typeof selectedEl.setAttribute === 'function') {
                                        selectedEl.setAttribute('data-bg-src', fullUrl);
                                    }
                                }
                                if (window.Vvveb?.StyleManager) {
                                    window.Vvveb.StyleManager.setStyle(selectedEl, "background-image", 'url("' + fullUrl + '")');
                                }
                                if (window.AlignBuilderModules?.WpSaveHandler?.setButtonState) {
                                    window.AlignBuilderModules.WpSaveHandler.setButtonState('dirty');
                                }
                                document.querySelectorAll("#top-panel .save-btn, .save-btn").forEach(btn => btn.removeAttribute("disabled"));

                                window.Vvveb.Builder.selectNode(selectedEl);
                                if (window.Vvveb.Builder.reloadComponent) {
                                    window.Vvveb.Builder.reloadComponent();
                                }
                                console.log(`[Align MediaModal] Background image applied: ${fullUrl}`);
                            } else {
                                let targetImg = selectedEl.tagName && selectedEl.tagName.toLowerCase() === "img" ? selectedEl : (selectedEl.querySelector ? selectedEl.querySelector("img") : null);

                                if (targetImg) {
                                    const doc = targetImg.ownerDocument || document;
                                    const isSvg = attachment.mime === 'image/svg+xml' || (rawUrl && rawUrl.toLowerCase().endsWith('.svg'));
                                    const isSmallIcon = fullWidth > 0 && fullWidth <= 150 && fullHeight > 0 && fullHeight <= 150;
                                    const isIcon = isSvg || isSmallIcon;

                                    if (isIcon) {
                                        // 1. Icon Mode: Clean standalone <img> without <picture> or <source>
                                        if (targetImg.parentElement && targetImg.parentElement.tagName === 'PICTURE') {
                                            const pic = targetImg.parentElement;
                                            pic.querySelectorAll('source').forEach(s => s.remove());
                                            pic.replaceWith(targetImg);
                                        }

                                        targetImg.removeAttribute('srcset');
                                        targetImg.removeAttribute('sizes');
                                        targetImg.removeAttribute('data-srcset');
                                        targetImg.src = fullUrl;
                                        targetImg.setAttribute('src', fullUrl);

                                        if (fullWidth > 0) targetImg.setAttribute('width', fullWidth);
                                        if (fullHeight > 0) targetImg.setAttribute('height', fullHeight);
                                        if (altText) targetImg.setAttribute('alt', altText);
                                        if (!targetImg.hasAttribute('decoding')) targetImg.setAttribute('decoding', 'async');

                                        if (attachmentId) {
                                            targetImg.className = (targetImg.className || '').replace(/\bwp-image-\d+\b/g, '').replace(/\s+/g, ' ').trim();
                                            targetImg.classList.add('wp-image-' + attachmentId);
                                            targetImg.setAttribute('data-id', attachmentId);
                                            if (attachment.title || attachment.name || altText) {
                                                targetImg.setAttribute('data-media-title', attachment.title || attachment.name || altText);
                                            }
                                        }

                                        self.cleanImageInlineStyle(targetImg);

                                        console.log(`[Align MediaModal] Icon mode applied (width=${fullWidth}px, SVG=${isSvg}) for ID ${attachmentId}`);
                                    } else {
                                        // 2. Photo / Media Mode: Smart Slot-Aware 3-Tier Art-Direction with <picture>
                                        let targetSlotWidth = 0;
                                        if (targetImg.getBoundingClientRect) {
                                            const rect = targetImg.getBoundingClientRect();
                                            if (rect && rect.width > 0) targetSlotWidth = Math.round(rect.width);
                                        }
                                        if (!targetSlotWidth && targetImg.offsetWidth > 0) {
                                            targetSlotWidth = targetImg.offsetWidth;
                                        }
                                        if (!targetSlotWidth && targetImg.closest) {
                                            if (targetImg.closest('.is-small, [data-slot-width="312"]')) targetSlotWidth = 312;
                                            else if (targetImg.closest('.is-large, [data-slot-width="644"]')) targetSlotWidth = 644;
                                        }

                                        const bp = self.resolvePictureBreakpoints(attachment, serverSizes, fullUrl, fullWidth, fullHeight, targetSlotWidth);
                                        let pictureEl = null;

                                        if (targetImg.parentElement && targetImg.parentElement.tagName === 'PICTURE') {
                                            pictureEl = targetImg.parentElement;
                                        } else {
                                            pictureEl = doc.createElement('picture');
                                            targetImg.parentNode.insertBefore(pictureEl, targetImg);
                                            pictureEl.appendChild(targetImg);
                                        }

                                        if (targetSlotWidth > 0) {
                                            pictureEl.setAttribute('data-slot-width', targetSlotWidth);
                                        }

                                        if (attachmentId) {
                                            pictureEl.className = (pictureEl.className || '').replace(/\bwp-image-\d+\b/g, '').replace(/\s+/g, ' ').trim();
                                            pictureEl.classList.add('wp-image-' + attachmentId);
                                            pictureEl.setAttribute('data-id', attachmentId);
                                            if (attachment.title || attachment.name || altText) {
                                                pictureEl.setAttribute('data-media-title', attachment.title || attachment.name || altText);
                                            }
                                            pictureEl.removeAttribute('data-align-sizes');
                                            pictureEl.removeAttribute('data-selected-sizes');

                                            targetImg.setAttribute('data-id', attachmentId);
                                            targetImg.className = (targetImg.className || '').replace(/\bwp-image-\d+\b/g, '').replace(/\s+/g, ' ').trim();
                                        }

                                        // Clear existing <source> elements in picture
                                        pictureEl.querySelectorAll('source').forEach(s => s.remove());

                                        // Source 1: Mobile (< 600px)
                                        const srcMobile = doc.createElement('source');
                                        srcMobile.setAttribute('media', '(max-width: 599px)');
                                        srcMobile.setAttribute('srcset', bp.mobile.url);
                                        if (bp.mobile.width) srcMobile.setAttribute('width', bp.mobile.width);
                                        if (bp.mobile.height) srcMobile.setAttribute('height', bp.mobile.height);
                                        pictureEl.insertBefore(srcMobile, targetImg);

                                        // Source 2: Tablet (600px - 992px)
                                        const srcTablet = doc.createElement('source');
                                        srcTablet.setAttribute('media', '(max-width: 992px)');
                                        srcTablet.setAttribute('srcset', bp.tablet.url);
                                        if (bp.tablet.width) srcTablet.setAttribute('width', bp.tablet.width);
                                        if (bp.tablet.height) srcTablet.setAttribute('height', bp.tablet.height);
                                        pictureEl.insertBefore(srcTablet, targetImg);

                                        // Fallback Primary Desktop <img> (Slot-aware optimized size)
                                        targetImg.src = bp.desktop.url;
                                        targetImg.setAttribute('src', bp.desktop.url);
                                        targetImg.removeAttribute('srcset');
                                        targetImg.removeAttribute('sizes');
                                        targetImg.removeAttribute('data-srcset');

                                        const imgWidth = bp.desktop.width || fullWidth;
                                        const imgHeight = bp.desktop.height || fullHeight;
                                        if (imgWidth > 0) targetImg.setAttribute('width', imgWidth);
                                        if (imgHeight > 0) targetImg.setAttribute('height', imgHeight);
                                        if (altText) targetImg.setAttribute('alt', altText);
                                        if (!targetImg.hasAttribute('loading')) targetImg.setAttribute('loading', 'lazy');
                                        if (!targetImg.hasAttribute('decoding')) targetImg.setAttribute('decoding', 'async');

                                        self.cleanImageInlineStyle(targetImg);

                                        console.log(`[Align MediaModal] Picture Art-Direction applied (Slot=${targetSlotWidth || 'default'}px): Mobile=${bp.mobile.url} (w=${bp.mobile.width}), Tablet=${bp.tablet.url} (w=${bp.tablet.width}), Desktop=${bp.desktop.url} (w=${bp.desktop.width})`);
                                    }
                                }

                                if (window.AlignBuilderModules?.WpSaveHandler?.setButtonState) {
                                    window.AlignBuilderModules.WpSaveHandler.setButtonState('dirty');
                                }
                                document.querySelectorAll("#top-panel .save-btn, .save-btn").forEach(btn => btn.removeAttribute("disabled"));
                                if (window.Vvveb?.Builder) {
                                    window.Vvveb.Builder.hasChanges = true;
                                }

                                if (attachmentId && typeof ImageInput !== "undefined" && ImageInput.setLockedView) {
                                    const container = ImageInput.getContainer(null, null, element);
                                    if (container) {
                                        ImageInput.setLockedView(attachmentId, attachment.title || attachment.name || altText, container);
                                    }
                                }

                                window.Vvveb.Builder.selectNode(targetImg || selectedEl);
                                if (window.Vvveb.Builder.reloadComponent) {
                                    window.Vvveb.Builder.reloadComponent();
                                }
                            }
                        }
                    });

                    frame.open();
                    return;
                }

                return origMediaModalOpen.call(this, element, callback);
            };
        }
    }
};
