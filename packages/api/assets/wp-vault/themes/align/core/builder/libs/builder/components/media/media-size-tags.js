/**
 * Align Builder Media Components - Responsive Media Size Tags Subsystem
 * Manages 3 dedicated screensize slots (Mobile, Tablet, Desktop) with smart breakpoint mapping.
 *
 * @package AlignBuilder
 */

window.AlignBuilderModules = window.AlignBuilderModules || {};

window.AlignBuilderModules.MediaSizeTags = (function() {
    'use strict';

    const _cache = new Map();

    /**
     * Normalize URL to clean relative path.
     */
    function getCleanUrl(url) {
        if (!url) return '';
        try {
            const parsed = new URL(url, window.location.origin);
            return parsed.pathname + (parsed.search || '') + (parsed.hash || '');
        } catch (e) {
            return url.startsWith('/') ? url : '/' + url;
        }
    }

    /**
     * Fetch attachment sizes from REST API or admin-ajax.
     */
    async function fetchAttachmentSizes(attachmentId) {
        if (!attachmentId) return null;
        if (_cache.has(attachmentId)) {
            return _cache.get(attachmentId);
        }

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
                    if (data && Array.isArray(data.sizes)) {
                        _cache.set(attachmentId, data);
                        return data;
                    }
                }
            } catch (e) {
                console.warn('[Align MediaSizeTags] REST sizes fetch failed:', e);
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
                    _cache.set(attachmentId, json.data);
                    return json.data;
                }
            }
        } catch (e) {
            console.error('[Align MediaSizeTags] AJAX sizes fetch failed:', e);
        }

        return null;
    }

    /**
     * Resolve target image and picture elements from selected node.
     */
    function resolveTargetElements(node) {
        if (!node) return { img: null, picture: null };
        let img = (node.tagName === 'IMG') ? node : (node.querySelector ? node.querySelector('img') : null);
        let picture = (node.tagName === 'PICTURE') ? node : (img?.parentElement?.tagName === 'PICTURE' ? img.parentElement : null);
        return { img, picture };
    }

    /**
     * Parse slot sizes from attribute string (key-value or legacy comma-separated).
     *
     * @param {string} attrStr Attribute value from data-align-sizes.
     * @return {{ mobile: string, tablet: string, desktop: string }}
     */
    function parseSlotSizes(attrStr) {
        const defaultSlots = { mobile: 'auto', tablet: 'auto', desktop: 'auto' };
        if (!attrStr || typeof attrStr !== 'string') return defaultSlots;

        const trimmed = attrStr.trim().toLowerCase();
        if (!trimmed) return defaultSlots;

        if (trimmed.includes(':')) {
            const parts = trimmed.split(',');
            for (const part of parts) {
                const [k, v] = part.split(':').map(s => s.trim());
                if (k && v && defaultSlots.hasOwnProperty(k)) {
                    defaultSlots[k] = v;
                }
            }
            return defaultSlots;
        }

        // Legacy comma-separated format
        const items = trimmed.split(',').map(s => s.trim()).filter(Boolean);
        if (items.length === 1) {
            defaultSlots.mobile = items[0];
            defaultSlots.tablet = items[0];
            defaultSlots.desktop = items[0];
        } else if (items.length === 2) {
            defaultSlots.mobile = items[0];
            defaultSlots.tablet = items[0];
            defaultSlots.desktop = items[1];
        } else if (items.length >= 3) {
            defaultSlots.mobile = items[0];
            defaultSlots.tablet = items[1];
            defaultSlots.desktop = items[2];
        }
        return defaultSlots;
    }

    /**
     * Extract active slot sizes configuration from DOM element.
     *
     * @param {HTMLElement} node DOM element.
     * @return {{ mobile: string, tablet: string, desktop: string }}
     */
    function getActiveSlots(node) {
        const { img, picture } = resolveTargetElements(node);
        const target = picture || img;
        if (!target) return { mobile: 'auto', tablet: 'auto', desktop: 'auto' };

        const explicit = target.getAttribute('data-align-sizes') || target.getAttribute('data-selected-sizes');
        return parseSlotSizes(explicit);
    }

    /**
     * Extract active selected size names (for backward compatibility).
     *
     * @param {HTMLElement} node DOM element.
     * @return {string[]} Non-auto size identifiers.
     */
    function getSelectedSizeNames(node) {
        const slots = getActiveSlots(node);
        return [slots.mobile, slots.tablet, slots.desktop].filter(v => v && v !== 'auto');
    }

    /**
     * Proximity scoring helper to find closest size when a slot is set to 'auto'.
     *
     * @param {Array} pool Filtered valid sizes list.
     * @param {number} targetWidth Target width in px.
     * @return {Object|null}
     */
    function findClosestSize(pool, targetWidth) {
        if (!pool || pool.length === 0) return null;
        let best = pool[0];
        let minScore = Infinity;
        for (const s of pool) {
            const w = parseInt(s.width, 10) || 0;
            const penalty = (w < targetWidth) ? 1.25 : 1.0;
            const score = Math.abs(w - targetWidth) * penalty;
            if (score < minScore) {
                minScore = score;
                best = s;
            }
        }
        return best;
    }

    /**
     * Resolve single slot item from pool, matching by name or dimensions, falling back to auto item.
     *
     * @param {Array} pool Valid sub-sizes array.
     * @param {string} val Slot value ('auto' or size identifier).
     * @param {Object} autoItem Proximity fallback item.
     * @return {Object|null}
     */
    function resolveSlotItem(pool, val, autoItem) {
        if (!val || val === 'auto') return autoItem;
        const lower = val.toLowerCase();
        const match = pool.find(s =>
            ((s.name || '').toLowerCase() === lower) ||
            (`${s.width}x${s.height}` === lower)
        );
        return match || autoItem;
    }

    /**
     * Apply 3 screensize slots to canvas DOM elements.
     *
     * @param {HTMLElement} node Selected node.
     * @param {{ mobile: string, tablet: string, desktop: string }} slots Slot configuration.
     * @param {Object} allSizesData Attachment data from server.
     */
    function applySlotSizesToNode(node, slots, allSizesData) {
        let { img, picture } = resolveTargetElements(node);
        if (!img) return;

        const doc = img.ownerDocument || document;
        const attachmentId = img.getAttribute('data-id') || picture?.getAttribute('data-id');

        // Ensure <picture> wrapper exists
        if (!picture) {
            picture = doc.createElement('picture');
            img.parentNode.insertBefore(picture, img);
            picture.appendChild(img);
        }

        if (attachmentId) {
            picture.setAttribute('data-id', attachmentId);
            picture.classList.add('wp-image-' + attachmentId);
        }

        const validSizes = (allSizesData?.sizes || [])
            .filter(s => s && s.url && s.width && parseInt(s.width, 10) >= 150)
            .sort((a, b) => (parseInt(a.width, 10) || 0) - (parseInt(b.width, 10) || 0));

        // Proximity calculation for auto slots
        const slotWidth = parseInt(picture.getAttribute('data-slot-width') || img.getAttribute('width') || 0, 10);
        const fullW = parseInt(allSizesData?.width, 10) || 0;
        const isConstrained = (slotWidth > 0 && slotWidth < 1200);

        const autoMobile = findClosestSize(validSizes, isConstrained ? Math.min(slotWidth, 599) : 599);
        const autoTablet = findClosestSize(validSizes, isConstrained ? Math.min(slotWidth, 992) : 992);
        const autoDesktop = findClosestSize(validSizes, isConstrained ? Math.min(fullW || 1920, slotWidth * 2) : 1440) || validSizes[validSizes.length - 1];

        const resolvedMobile = resolveSlotItem(validSizes, slots.mobile, autoMobile);
        const resolvedTablet = resolveSlotItem(validSizes, slots.tablet, autoTablet);
        const resolvedDesktop = resolveSlotItem(validSizes, slots.desktop, autoDesktop);

        const allAuto = (slots.mobile === 'auto' && slots.tablet === 'auto' && slots.desktop === 'auto');
        const newAttrValue = allAuto ? null : `mobile:${slots.mobile},tablet:${slots.tablet},desktop:${slots.desktop}`;

        if (allAuto) {
            picture.removeAttribute('data-align-sizes');
            picture.removeAttribute('data-selected-sizes');
        } else {
            picture.setAttribute('data-align-sizes', newAttrValue);
        }

        // Source 1: Mobile (< 600px)
        let srcMobile = picture.querySelector('source[media*="599"]');
        if (!srcMobile) {
            srcMobile = doc.createElement('source');
            srcMobile.setAttribute('media', '(max-width: 599px)');
            picture.insertBefore(srcMobile, img);
        }
        if (resolvedMobile) {
            srcMobile.setAttribute('srcset', getCleanUrl(resolvedMobile.url));
            if (resolvedMobile.width) srcMobile.setAttribute('width', resolvedMobile.width);
            if (resolvedMobile.height) srcMobile.setAttribute('height', resolvedMobile.height);
        }

        // Source 2: Tablet (< 992px)
        let srcTablet = picture.querySelector('source[media*="992"]');
        if (!srcTablet) {
            srcTablet = doc.createElement('source');
            srcTablet.setAttribute('media', '(max-width: 992px)');
            picture.insertBefore(srcTablet, img);
        }
        if (resolvedTablet) {
            srcTablet.setAttribute('srcset', getCleanUrl(resolvedTablet.url));
            if (resolvedTablet.width) srcTablet.setAttribute('width', resolvedTablet.width);
            if (resolvedTablet.height) srcTablet.setAttribute('height', resolvedTablet.height);
        }

        // Clean up legacy non-standard source tags
        picture.querySelectorAll('source').forEach(s => {
            const media = s.getAttribute('media') || '';
            if (!media.includes('599') && !media.includes('992')) {
                s.remove();
            }
        });

        // Desktop Fallback <img>
        if (resolvedDesktop) {
            const cleanDeskUrl = getCleanUrl(resolvedDesktop.url);
            img.src = cleanDeskUrl;
            img.setAttribute('src', cleanDeskUrl);
            if (resolvedDesktop.width) img.setAttribute('width', resolvedDesktop.width);
            if (resolvedDesktop.height) img.setAttribute('height', resolvedDesktop.height);
        }
        img.removeAttribute('srcset');
        img.removeAttribute('sizes');
        img.removeAttribute('data-srcset');
        if (!img.hasAttribute('loading')) img.setAttribute('loading', 'lazy');
        if (!img.hasAttribute('decoding')) img.setAttribute('decoding', 'async');
        if (attachmentId) {
            img.setAttribute('data-id', attachmentId);
            img.classList.add('wp-image-' + attachmentId);
        }

        // Push mutation to Vvveb undo stack
        if (window.Vvveb && window.Vvveb.Undo) {
            window.Vvveb.Undo.addMutation({
                type: 'attributes',
                target: picture,
                attributeName: 'data-align-sizes',
                newValue: newAttrValue
            });
        }

        // Mark canvas dirty
        if (window.AlignBuilderModules?.WpSaveHandler?.setButtonState) {
            window.AlignBuilderModules.WpSaveHandler.setButtonState('dirty');
        }
        document.querySelectorAll('#top-panel .save-btn, .save-btn').forEach(btn => btn.removeAttribute('disabled'));

        console.log(`[Align MediaSizeTags] Applied 3-slot sizes to ID ${attachmentId}:`, slots);
    }

    /**
     * Render the 3 Screensize Slots UI inside inspector container.
     *
     * @param {HTMLElement} container Inspector panel container.
     * @param {number|string} attachmentId Attachment ID.
     * @param {HTMLElement} selectedNode Selected canvas element.
     */
    async function render(container, attachmentId, selectedNode) {
        if (!container) return;

        let wrapper = container.querySelector('.align-media-sizes-slots-wrapper') || container.querySelector('.align-media-sizes-tags-wrapper');
        if (!wrapper) {
            wrapper = document.createElement('div');
            wrapper.className = 'align-media-sizes-slots-wrapper mt-2';
            container.appendChild(wrapper);
        } else {
            wrapper.className = 'align-media-sizes-slots-wrapper mt-2';
        }

        if (!attachmentId) {
            wrapper.innerHTML = '';
            wrapper.style.display = 'none';
            return;
        }

        wrapper.style.display = 'block';
        wrapper.innerHTML = '<div class="align-size-slots-loading text-muted small py-1"><i class="la la-spinner la-spin me-1"></i>Loading responsive slots...</div>';

        const data = await fetchAttachmentSizes(attachmentId);
        if (!data || !Array.isArray(data.sizes) || data.sizes.length === 0) {
            wrapper.innerHTML = '';
            wrapper.style.display = 'none';
            return;
        }

        const validSizes = data.sizes.filter(s => s && s.url && s.width && parseInt(s.width, 10) >= 150);
        if (validSizes.length === 0) {
            wrapper.innerHTML = '';
            wrapper.style.display = 'none';
            return;
        }

        validSizes.sort((a, b) => (parseInt(a.width, 10) || 0) - (parseInt(b.width, 10) || 0));

        const currentSlots = getActiveSlots(selectedNode);

        let optionsHtml = '<option value="auto">Auto (Smart Resolution)</option>';
        validSizes.forEach(s => {
            const val = (s.name || `${s.width}x${s.height}`).toLowerCase();
            const label = (s.name && s.name !== `${s.width}x${s.height}`)
                ? `${s.name} (${s.width}×${s.height})`
                : `${s.width}×${s.height}`;
            optionsHtml += `<option value="${val}">${label}</option>`;
        });

        wrapper.innerHTML = `
            <div class="align-size-slots-header d-flex align-items-center justify-content-between mb-2">
                <span class="small fw-semibold align-size-slots-title">Screensize Breakpoints</span>
                <button type="button" class="btn btn-sm align-size-reset-btn" title="Reset all slots to Auto">
                    <i class="la la-undo me-1"></i>Reset to Auto
                </button>
            </div>
            <div class="align-size-slots-list d-flex flex-column gap-2">
                <div class="align-size-slot-row">
                    <div class="align-size-slot-label-group d-flex align-items-center justify-content-between mb-1">
                        <span class="align-size-slot-label"><i class="la la-mobile me-1"></i>Mobile</span>
                        <span class="align-size-slot-badge">&lt; 600px</span>
                    </div>
                    <select class="form-select form-select-sm align-size-slot-select" data-slot="mobile">
                        ${optionsHtml}
                    </select>
                </div>
                <div class="align-size-slot-row">
                    <div class="align-size-slot-label-group d-flex align-items-center justify-content-between mb-1">
                        <span class="align-size-slot-label"><i class="la la-tablet me-1"></i>Tablet</span>
                        <span class="align-size-slot-badge">&lt; 992px</span>
                    </div>
                    <select class="form-select form-select-sm align-size-slot-select" data-slot="tablet">
                        ${optionsHtml}
                    </select>
                </div>
                <div class="align-size-slot-row">
                    <div class="align-size-slot-label-group d-flex align-items-center justify-content-between mb-1">
                        <span class="align-size-slot-label"><i class="la la-desktop me-1"></i>Desktop</span>
                        <span class="align-size-slot-badge">&gt; 992px</span>
                    </div>
                    <select class="form-select form-select-sm align-size-slot-select" data-slot="desktop">
                        ${optionsHtml}
                    </select>
                </div>
            </div>
        `;

        const mobileSelect = wrapper.querySelector('.align-size-slot-select[data-slot="mobile"]');
        const tabletSelect = wrapper.querySelector('.align-size-slot-select[data-slot="tablet"]');
        const desktopSelect = wrapper.querySelector('.align-size-slot-select[data-slot="desktop"]');
        const resetBtn = wrapper.querySelector('.align-size-reset-btn');

        function setSelectVal(sel, targetVal) {
            if (!targetVal || targetVal === 'auto') {
                sel.value = 'auto';
                return;
            }
            const found = Array.from(sel.options).some(o => o.value === targetVal);
            sel.value = found ? targetVal : 'auto';
        }

        setSelectVal(mobileSelect, currentSlots.mobile);
        setSelectVal(tabletSelect, currentSlots.tablet);
        setSelectVal(desktopSelect, currentSlots.desktop);

        function triggerApply() {
            const slots = {
                mobile: mobileSelect.value,
                tablet: tabletSelect.value,
                desktop: desktopSelect.value
            };
            const currentNode = window.Vvveb?.Builder?.selectedEl || selectedNode;
            applySlotSizesToNode(currentNode, slots, data);
        }

        mobileSelect.addEventListener('change', triggerApply);
        tabletSelect.addEventListener('change', triggerApply);
        desktopSelect.addEventListener('change', triggerApply);

        resetBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            mobileSelect.value = 'auto';
            tabletSelect.value = 'auto';
            desktopSelect.value = 'auto';
            triggerApply();
        });
    }

    return {
        fetchAttachmentSizes,
        render,
        applySizesToNode: applySlotSizesToNode,
        applySlotSizesToNode,
        getActiveSlots,
        getSelectedSizeNames,
        parseSlotSizes
    };
})();
