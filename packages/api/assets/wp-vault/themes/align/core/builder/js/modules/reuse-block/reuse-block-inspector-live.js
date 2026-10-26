/**
 * Align Builder Reusable Block - Live DOM Content Overwrite Inspector
 * @package Align
 */

window.AlignBuilderModules = window.AlignBuilderModules || {};
window.AlignBuilderModules.WpReuseBlockOverwrite = window.AlignBuilderModules.WpReuseBlockOverwrite || {};

Object.assign(window.AlignBuilderModules.WpReuseBlockOverwrite, {
    _masterValuesCache: {},

    getAltBlockKey: function(blockId) {
        if (!blockId) return null;
        const bIdStr = String(blockId);
        const blocks = window.Vvveb?.WpReusableBlocks || window.VvvebWpConfig?.reusableBlocks;
        if (Array.isArray(blocks)) {
            const found = blocks.find(b => String(b.id || b.post_id) === bIdStr || (b.slug && b.slug.toLowerCase() === bIdStr.toLowerCase()) || (b.name && b.name.toLowerCase() === bIdStr.toLowerCase()));
            if (found) {
                const companion = (bIdStr === String(found.id || found.post_id)) ? (found.slug || found.name || null) : (found.id || found.post_id ? String(found.id || found.post_id) : null);
                if (companion && String(companion) !== bIdStr) return String(companion);
            }
        }
        return null;
    },

    escapeAttr: function(str) {
        return (str === null || str === undefined) ? '' : String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#039;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    },

    updateNodeTextPreservingIcons: function(el, newText) {
        if (!el) return;
        const target = el.querySelector('span, b, strong, em, label');
        if (target) { target.textContent = newText; return; }
        for (let i = 0; i < el.childNodes.length; i++) {
            if (el.childNodes[i].nodeType === 3 && el.childNodes[i].nodeValue.trim().length > 0) { el.childNodes[i].nodeValue = newText; return; }
        }
        el.textContent = newText;
    },

    getNodeCleanText: function(el) {
        if (!el) return '';
        const target = el.querySelector('span, b, strong, em, label');
        if (target && target.textContent.trim()) return target.textContent.trim();
        for (let i = 0; i < el.childNodes.length; i++) {
            if (el.childNodes[i].nodeType === 3 && el.childNodes[i].nodeValue.trim().length > 0) return el.childNodes[i].nodeValue.trim();
        }
        return (el.innerText || el.textContent || '').trim();
    },

    toggleCanvasElementHidden: function(liveEl, ovId, isHidden, blockNode) {
        if (isHidden) {
            if (liveEl?.parentNode) {
                const wrap = (liveEl.parentNode?.tagName?.toUpperCase() === 'PICTURE') ? liveEl.parentNode : liveEl;
                if (!liveEl._alignCommentPlaceholder) {
                    const c = (wrap.ownerDocument || document).createComment('align-hidden:' + ovId);
                    wrap.parentNode.replaceChild(c, wrap);
                    liveEl._alignCommentPlaceholder = c; c._alignOriginalElement = liveEl; c._alignWrapElement = wrap;
                }
            }
        } else {
            let c = liveEl ? liveEl._alignCommentPlaceholder : null;
            if (!c) {
                const scope = blockNode?.shadowRoot || blockNode || window.Vvveb?.Builder?.frameDoc || window.FrameDocument || document;
                if (scope) {
                    const it = (scope.ownerDocument || document).createNodeIterator(scope, NodeFilter.SHOW_COMMENT);
                    let node;
                    while ((node = it.nextNode())) { if (node.nodeValue === 'align-hidden:' + ovId) { c = node; break; } }
                }
            }
            if (c?.parentNode) {
                const orig = c._alignWrapElement || c._alignOriginalElement || liveEl;
                if (orig) {
                    c.parentNode.replaceChild(orig, c);
                    if (liveEl) delete liveEl._alignCommentPlaceholder;
                    delete c._alignOriginalElement; delete c._alignWrapElement;
                }
            } else if (window.AlignBuilderModules?.WpComponentsPreview) {
                window.AlignBuilderModules.WpComponentsPreview.triggerWpComponentsPreviewInIframe(true);
            }
        }
    },

    renderContentOverwriteAccordion: function(blockNode, domNodes, blockId, instanceId) {
        const self = this;
        const rightPanelProps = document.querySelector("#right-panel .component-properties") || document.querySelector("#properties");
        if (!rightPanelProps) return;

        instanceId = instanceId || self.getReuseBlockInstanceId(blockNode) || 'default';
        const activeId = String(blockNode.id || blockNode.getAttribute?.('data-ast-id') || blockNode._alignUid || blockId || 'selected');
        const masterKey = (blockId || 'noblock') + '_' + instanceId;
        const altKey = self.getAltBlockKey ? self.getAltBlockKey(blockId) : null;
        const userBlockData = window._alignActiveReuseOverrides?.[blockId] || (altKey && window._alignActiveReuseOverrides?.[altKey]) || {};
        const userOverrides = userBlockData[instanceId] || {};
        const globalMaster = (window.AlignBuilderModules?.WpReuseBlockOverwrite?.masterDefaults && (blockId || altKey)) ? (window.AlignBuilderModules.WpReuseBlockOverwrite.masterDefaults[blockId] || (altKey && window.AlignBuilderModules.WpReuseBlockOverwrite.masterDefaults[altKey]) || {}) : {};

        self._masterValuesCache = self._masterValuesCache || {};
        if (!self._masterValuesCache[masterKey]) self._masterValuesCache[masterKey] = {};

        const existingOvIds = new Set(domNodes.map(n => n.getAttribute && n.getAttribute('data-overwrite-id')).filter(Boolean));
        Object.keys(globalMaster).forEach(function(mOvId) {
            if (!existingOvIds.has(mOvId) && userOverrides[mOvId]?.hidden) {
                const gm = globalMaster[mOvId] || {};
                const placeholder = document.createElement(gm.type === 'media' ? 'img' : (gm.type === 'button' ? 'button' : (gm.type === 'link' ? 'a' : 'div')));
                placeholder.setAttribute('data-overwrite-id', mOvId);
                placeholder.setAttribute('data-align-hidden', 'true');
                domNodes.push(placeholder);
                existingOvIds.add(mOvId);
            }
        });

        domNodes.forEach(function(el, index) {
            const tagNameLower = el.tagName ? el.tagName.toLowerCase() : 'el';
            const ovId = el.getAttribute('data-overwrite-id') || ('ov-' + tagNameLower + '-' + (index + 1));
            const tagName = el.tagName ? el.tagName.toUpperCase() : '';
            const mItem = globalMaster[ovId] || (self._masterValuesCache[masterKey] ? self._masterValuesCache[masterKey][ovId] : null) || {};

            if (tagName === 'IMG' || tagName === 'PICTURE' || tagName === 'VIDEO' || tagName === 'FIGURE') {
                self._masterValuesCache[masterKey][ovId] = { type: 'media', src: (mItem.src !== undefined && mItem.src !== '') ? mItem.src : (el.getAttribute('src') || el.src || ''), width: (mItem.width !== undefined && mItem.width !== '') ? mItem.width : (el.getAttribute('width') || (el.style ? el.style.width : '') || ''), height: (mItem.height !== undefined && mItem.height !== '') ? mItem.height : (el.getAttribute('height') || (el.style ? el.style.height : '') || ''), alt: (mItem.alt !== undefined && mItem.alt !== '') ? mItem.alt : (el.getAttribute('alt') || ''), align: (mItem.align !== undefined && mItem.align !== '') ? mItem.align : (el.getAttribute('align') || ''), hidden: false };
            } else if (tagName === 'BUTTON') {
                self._masterValuesCache[masterKey][ovId] = { type: 'button', content: (mItem.content !== undefined && mItem.content !== '') ? mItem.content : self.getNodeCleanText(el), hidden: false };
            } else if (tagName === 'A') {
                self._masterValuesCache[masterKey][ovId] = { type: 'link', content: (mItem.content !== undefined && mItem.content !== '') ? mItem.content : self.getNodeCleanText(el), href: (mItem.href !== undefined && mItem.href !== '') ? mItem.href : (el.getAttribute('href') || ''), target: (mItem.target !== undefined && mItem.target !== '') ? mItem.target : (el.getAttribute('target') || ''), link_id: (mItem.link_id !== undefined && mItem.link_id !== '') ? mItem.link_id : (el.getAttribute('data-align-link-id') || ''), link_title: (mItem.link_title !== undefined && mItem.link_title !== '') ? mItem.link_title : (el.getAttribute('data-align-link-title') || ''), hidden: false };
            } else {
                self._masterValuesCache[masterKey][ovId] = { type: 'typography', content: (mItem.content !== undefined && mItem.content !== '') ? mItem.content : (el.innerHTML || el.textContent || ''), hidden: false };
            }
        });

        self.removeAccordion();

        const accordionWrapper = document.createElement("div");
        accordionWrapper.id = "align-content-overwrite-section";
        accordionWrapper.className = "align-content-overwrite-section";
        accordionWrapper.dataset.activeNodeId = activeId;
        accordionWrapper.dataset.instanceId = instanceId;
        accordionWrapper.dataset.sourceType = 'live';
        accordionWrapper._alignBlockNode = blockNode;
        accordionWrapper.innerHTML = '<label class="header" data-header="content_overwrite" for="header_content_overwrite"><span>Content Overwrite' + (instanceId && instanceId !== 'default' ? ' (' + self.escapeAttr(instanceId) + ')' : '') + '</span><div class="header-arrow"></div></label><input class="header_check" type="checkbox" id="header_content_overwrite" checked="checked"><div class="section row" data-section="content_overwrite"></div>';

        const targetContainer = rightPanelProps.querySelector('.tab-pane[data-section="content"]') || rightPanelProps.querySelector('.tab-pane') || rightPanelProps;
        targetContainer.appendChild(accordionWrapper);

        const sectionBody = accordionWrapper.querySelector('.section[data-section="content_overwrite"]');
        const masterData = self._masterValuesCache[masterKey] || {};

        function getLiveTarget(fallbackEl, ovId) {
            if (fallbackEl?.isConnected || fallbackEl?._alignCommentPlaceholder?.isConnected) return fallbackEl;
            const blockEl = blockNode?.isConnected ? blockNode : (window.Vvveb?.Builder?.selectedEl || accordionWrapper._alignBlockNode);
            const q = (r) => r?.querySelector?.('[data-overwrite-id="' + ovId + '"]') || r?.querySelector?.('[data-overwrite-id="' + ovId.toLowerCase() + '"]') || r?.querySelector?.('[data-overwrite-id="' + ovId.toUpperCase() + '"]');
            return (blockEl?.shadowRoot && q(blockEl.shadowRoot)) || (blockEl && q(blockEl)) || (window.FrameDocument && q(window.FrameDocument)) || fallbackEl;
        }

        function renderFieldHeader(labelHtml, ovId, isModified, isHidden) {
            const cleanLabel = (labelHtml || '').replace(/<[^>]*>/g, '').replace(/\s*\[[^\]]*\]/g, '').replace(/\s*Overwrite/i, '').trim();
            const idBadge = ovId ? ' <span style="color:#64748b;font-weight:normal;">[' + self.escapeAttr(ovId.toUpperCase()) + ']</span>' : '';
            return '<div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:4px 8px;margin-bottom:6px;"><div style="display:inline-flex;align-items:center;gap:5px;min-width:0;max-width:100%;white-space:nowrap;"><span style="font-size:11px;font-weight:600;text-transform:uppercase;color:#a0aec0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + self.escapeAttr(cleanLabel) + idBadge + '</span><span class="badge bg-warning text-dark overwrite-modified-badge" style="font-size:9px;padding:1px 4px;line-height:1.2;white-space:nowrap;display:' + (isModified ? 'inline-block' : 'none') + ';">Modified</span><span class="badge bg-secondary text-light overwrite-hidden-badge" style="font-size:9px;padding:1px 4px;line-height:1.2;white-space:nowrap;display:' + (isHidden ? 'inline-block' : 'none') + ';">Hidden</span></div><div style="display:inline-flex;align-items:center;gap:8px;margin-left:auto;white-space:nowrap;"><button type="button" class="btn btn-sm btn-link text-muted p-0 overwrite-hide-btn" style="font-size:11px;text-decoration:none;white-space:nowrap;color:#94a3b8;">' + (isHidden ? 'Unhide' : 'Hide') + '</button><button type="button" class="btn btn-sm btn-link text-muted p-0 overwrite-reset-btn" style="font-size:11px;text-decoration:none;white-space:nowrap;color:#94a3b8;display:' + ((isModified || isHidden) ? 'inline-block' : 'none') + ';">Reset</button></div></div>';
        }

        function bindHideToggle(itemRow, ovId, fieldType, getPayload, liveEl) {
            const hideBtn = itemRow.querySelector('.overwrite-hide-btn');
            const hiddenBadge = itemRow.querySelector('.overwrite-hidden-badge');
            const resetBtn = itemRow.querySelector('.overwrite-reset-btn');
            const setUiHiddenState = function(hidden) {
                if (hiddenBadge) hiddenBadge.style.display = hidden ? 'inline-block' : 'none';
                if (hideBtn) hideBtn.textContent = hidden ? 'Unhide' : 'Hide';
                if (resetBtn && hidden) resetBtn.style.display = 'inline-block';
                itemRow.querySelectorAll('input, select, textarea, button:not(.overwrite-hide-btn)').forEach(el => { el.disabled = hidden; });
                itemRow.style.opacity = hidden ? '0.6' : '';
            };
            hideBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const curHidden = hiddenBadge && hiddenBadge.style.display !== 'none';
                const nextHidden = !curHidden;
                const target = getLiveTarget(liveEl, ovId);
                self.toggleCanvasElementHidden(target, ovId, nextHidden, blockNode);
                setUiHiddenState(nextHidden);
                const payload = getPayload();
                payload.hidden = nextHidden;
                self.triggerCanvasChange(blockId, ovId, fieldType, payload, target, instanceId);
            });
            return setUiHiddenState;
        }

        domNodes.forEach(function(el, index) {
            const tagNameLower = el.tagName ? el.tagName.toLowerCase() : 'el';
            const ovId = el.getAttribute('data-overwrite-id') || ('ov-' + tagNameLower + '-' + (index + 1));
            const tagName = el.tagName ? el.tagName.toUpperCase() : '';
            const isMedia = (tagName === 'IMG' || tagName === 'PICTURE' || tagName === 'VIDEO' || tagName === 'FIGURE');
            const isButton = (tagName === 'BUTTON');
            const isLink = !isButton && (tagName === 'A');
            const masterItem = masterData[ovId] || {};
            const isHiddenInitial = Boolean(userOverrides[ovId]?.hidden || masterItem.hidden || el.getAttribute('data-align-hidden') === 'true' || el._alignCommentPlaceholder);

            const itemRow = document.createElement("div");
            itemRow.className = "mb-3 col-12 overwrite-item-row";
            itemRow.dataset.overwriteId = ovId;

            const ovData = userOverrides[ovId] || {};
            if (isMedia) {
                const currentSrc = (ovData.src !== undefined && ovData.src !== '') ? ovData.src : (el.getAttribute('src') || el.src || masterItem.src || '');
                const thumbSrc = self.getAbsoluteImgUrl(currentSrc, el);
                const currentWidth = (ovData.width !== undefined && ovData.width !== '') ? ovData.width : (el.getAttribute('width') || (el.style ? el.style.width : '') || masterItem.width || '');
                const currentHeight = (ovData.height !== undefined && ovData.height !== '') ? ovData.height : (el.getAttribute('height') || (el.style ? el.style.height : '') || masterItem.height || '');
                const currentAlt = (ovData.alt !== undefined) ? ovData.alt : (el.getAttribute('alt') || masterItem.alt || '');
                const currentAlign = (ovData.align !== undefined) ? ovData.align : (el.getAttribute('align') || masterItem.align || '');
                const isModified = Boolean((ovData.src && ovData.src !== masterItem.src) || (masterItem.src && (currentSrc !== masterItem.src || currentAlt !== (masterItem.alt || '') || currentWidth !== (masterItem.width || '') || currentHeight !== (masterItem.height || '') || currentAlign !== (masterItem.align || ''))));

                let mediaHtml = renderFieldHeader('Media', ovId, isModified, isHiddenInitial) +
                    '<div style="display:flex;flex-direction:column;gap:8px;margin-bottom:10px;"><div class="img-preview-box" style="width:100%;height:120px;border-radius:6px;border:1px dashed #4a5568;overflow:hidden;background:#1a202c;display:flex;align-items:center;justify-content:center;"><img class="overwrite-thumb" src="' + self.escapeAttr(thumbSrc) + '" style="max-width:100%;max-height:100%;object-fit:contain;" /></div>' +
                    '<div style="display:flex;gap:6px;"><input type="text" class="form-control form-control-sm overwrite-media-url" value="' + self.escapeAttr(currentSrc) + '" placeholder="/wp-content/..." style="font-size:11px;" /><button type="button" class="btn btn-sm btn-primary open-media-btn" style="font-size:11px;padding:4px 10px;flex-shrink:0;display:inline-flex;align-items:center;gap:4px;"><i class="la la-image"></i> Set image</button></div></div>' +
                    '<div class="row g-2" style="margin-bottom:8px;"><div class="col-6"><label style="font-size:11px;color:#a0aec0;margin-bottom:2px;display:block;">Width</label><input type="text" class="form-control form-control-sm overwrite-media-width" value="' + self.escapeAttr(currentWidth) + '" placeholder="e.g. 200" style="font-size:11px;" /></div>' +
                    '<div class="col-6"><label style="font-size:11px;color:#a0aec0;margin-bottom:2px;display:block;">Height</label><input type="text" class="form-control form-control-sm overwrite-media-height" value="' + self.escapeAttr(currentHeight) + '" placeholder="auto" style="font-size:11px;" /></div></div>' +
                    '<div style="margin-bottom:8px;"><label style="font-size:11px;color:#a0aec0;margin-bottom:2px;display:block;">Alt</label><input type="text" class="form-control form-control-sm overwrite-media-alt" value="' + self.escapeAttr(currentAlt) + '" placeholder="Alt description..." style="font-size:11px;" /></div>' +
                    '<div><label style="font-size:11px;color:#a0aec0;margin-bottom:4px;display:block;">Align</label><div class="btn-group btn-group-sm w-100 overwrite-media-align-group" role="group">' +
                    '<button type="button" class="btn btn-outline-secondary align-btn ' + (currentAlign === '' ? 'active' : '') + '" data-align="" title="None">✕</button><button type="button" class="btn btn-outline-secondary align-btn ' + (currentAlign === 'left' ? 'active' : '') + '" data-align="left" title="Left"><i class="la la-align-left"></i></button><button type="button" class="btn btn-outline-secondary align-btn ' + (currentAlign === 'center' ? 'active' : '') + '" data-align="center" title="Center"><i class="la la-align-center"></i></button><button type="button" class="btn btn-outline-secondary align-btn ' + (currentAlign === 'right' ? 'active' : '') + '" data-align="right" title="Right"><i class="la la-align-right"></i></button></div></div>';
                itemRow.innerHTML = mediaHtml;
                sectionBody.appendChild(itemRow);

                const urlInput = itemRow.querySelector('.overwrite-media-url'), thumbImg = itemRow.querySelector('.overwrite-thumb'), openMediaBtn = itemRow.querySelector('.open-media-btn');
                const widthInput = itemRow.querySelector('.overwrite-media-width'), heightInput = itemRow.querySelector('.overwrite-media-height'), altInput = itemRow.querySelector('.overwrite-media-alt');
                const alignGroup = itemRow.querySelector('.overwrite-media-align-group'), badgeEl = itemRow.querySelector('.overwrite-modified-badge'), resetBtn = itemRow.querySelector('.overwrite-reset-btn');

                const getMediaPayload = () => ({
                    type: 'media', src: urlInput.value, width: widthInput.value, height: heightInput.value, alt: altInput.value,
                    align: (alignGroup.querySelector('.align-btn.active')?.dataset.align || '')
                });
                const setUiHidden = bindHideToggle(itemRow, ovId, 'media', getMediaPayload, el);
                if (isHiddenInitial) setUiHidden(true);

                const updateMediaData = function() {
                    const srcVal = urlInput.value, wVal = widthInput.value, hVal = heightInput.value, altVal = altInput.value;
                    const activeAlignBtn = alignGroup.querySelector('.align-btn.active');
                    const alignVal = activeAlignBtn ? (activeAlignBtn.dataset.align || '') : '';
                    const liveEl = getLiveTarget(el, ovId);
                    if (liveEl && !liveEl._alignCommentPlaceholder) {
                        liveEl.setAttribute('src', srcVal); liveEl.setAttribute('data-src', srcVal);
                        if (liveEl.hasAttribute('srcset')) liveEl.setAttribute('srcset', srcVal);
                        if (liveEl.hasAttribute('data-srcset')) liveEl.setAttribute('data-srcset', srcVal);
                        if (liveEl.tagName.toUpperCase() === 'IMG') {
                            liveEl.src = srcVal;
                            if (liveEl.parentNode?.tagName?.toUpperCase() === 'PICTURE') {
                                liveEl.parentNode.querySelectorAll('source').forEach(s => { s.setAttribute('srcset', srcVal); if (s.hasAttribute('data-srcset')) s.setAttribute('data-srcset', srcVal); });
                            }
                        }
                        if (wVal) { liveEl.setAttribute('width', wVal); liveEl.style.width = isNaN(wVal) ? wVal : (wVal + 'px'); } else { liveEl.removeAttribute('width'); liveEl.style.width = ''; }
                        if (hVal) { liveEl.setAttribute('height', hVal); liveEl.style.height = isNaN(hVal) ? hVal : (hVal + 'px'); } else { liveEl.removeAttribute('height'); liveEl.style.height = ''; }
                        if (altVal) liveEl.setAttribute('alt', altVal); else liveEl.removeAttribute('alt');
                        if (alignVal) liveEl.setAttribute('align', alignVal); else liveEl.removeAttribute('align');
                    }
                    const modified = (masterItem.src && (srcVal !== masterItem.src || altVal !== (masterItem.alt || '') || wVal !== (masterItem.width || '') || hVal !== (masterItem.height || '') || alignVal !== (masterItem.align || '')));
                    const isHid = itemRow.querySelector('.overwrite-hidden-badge')?.style.display !== 'none';
                    if (badgeEl) badgeEl.style.display = modified ? 'inline-block' : 'none';
                    if (resetBtn) resetBtn.style.display = (modified || isHid) ? 'inline-flex' : 'none';
                    self.triggerCanvasChange(blockId, ovId, 'media', Object.assign(getMediaPayload(), { hidden: isHid }), liveEl || el, instanceId);
                };

                resetBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const defSrc = masterItem.src || el.getAttribute('src') || el.src || '';
                    urlInput.value = defSrc;
                    const newThumb = self.getAbsoluteImgUrl(defSrc, el);
                    if (thumbImg) { thumbImg.src = newThumb; thumbImg.setAttribute('src', newThumb); }
                    widthInput.value = masterItem.width || ''; heightInput.value = masterItem.height || ''; altInput.value = masterItem.alt || '';
                    alignGroup.querySelectorAll('.align-btn').forEach(b => b.classList.toggle('active', (b.dataset.align || '') === (masterItem.align || '')));
                    const liveEl = getLiveTarget(el, ovId);
                    self.toggleCanvasElementHidden(liveEl, ovId, false, blockNode);
                    setUiHidden(false);
                    updateMediaData();
                    self.removeOverrideKey(blockId, instanceId, ovId);
                });

                openMediaBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (!window.wp?.media) return;
                    const frame = wp.media({ title: 'Select Media for [' + ovId.toUpperCase() + ']', button: { text: 'Set image' }, multiple: false });
                    frame.on('select', function() {
                        let url = frame.state().get('selection').first().toJSON().url;
                        try { const p = new URL(url); url = p.pathname + (p.search || '') + (p.hash || ''); } catch(err) {}
                        urlInput.value = url;
                        const newThumb = self.getAbsoluteImgUrl(url, el);
                        if (thumbImg) { thumbImg.src = newThumb; thumbImg.setAttribute('src', newThumb); }
                        updateMediaData();
                    });
                    frame.open();
                });

                urlInput.addEventListener('input', function() {
                    const newThumb = self.getAbsoluteImgUrl(this.value, el);
                    if (thumbImg) { thumbImg.src = newThumb; thumbImg.setAttribute('src', newThumb); }
                    updateMediaData();
                });
                [widthInput, heightInput, altInput].forEach(inp => inp.addEventListener('input', updateMediaData));
                alignGroup.querySelectorAll('.align-btn').forEach(b => b.addEventListener('click', function() {
                    alignGroup.querySelectorAll('.align-btn').forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    updateMediaData();
                }));

            } else if (isButton) {
                const currentContent = (ovData.content !== undefined && ovData.content !== '') ? ovData.content : (self.getNodeCleanText(el) || masterItem.content || '');
                const isModified = (masterItem.content !== undefined && currentContent !== masterItem.content) || Boolean(ovData.content && ovData.content !== masterItem.content);
                itemRow.innerHTML = renderFieldHeader('Button', ovId, isModified, isHiddenInitial) +
                    '<input type="text" class="form-control form-control-sm overwrite-button-val" value="' + self.escapeAttr(currentContent) + '" placeholder="Button Text..." style="font-size: 11px;" />';
                sectionBody.appendChild(itemRow);

                const valInput = itemRow.querySelector('.overwrite-button-val'), badgeEl = itemRow.querySelector('.overwrite-modified-badge'), resetBtn = itemRow.querySelector('.overwrite-reset-btn');
                const getBtnPayload = () => ({ type: 'button', content: valInput.value });
                const setUiHidden = bindHideToggle(itemRow, ovId, 'button', getBtnPayload, el);
                if (isHiddenInitial) setUiHidden(true);

                valInput.addEventListener('input', function() {
                    const liveEl = getLiveTarget(el, ovId);
                    if (liveEl && !liveEl._alignCommentPlaceholder) self.updateNodeTextPreservingIcons(liveEl, this.value);
                    const modified = (masterItem.content !== undefined && this.value !== masterItem.content);
                    const isHid = itemRow.querySelector('.overwrite-hidden-badge')?.style.display !== 'none';
                    if (badgeEl) badgeEl.style.display = modified ? 'inline-block' : 'none';
                    if (resetBtn) resetBtn.style.display = (modified || isHid) ? 'inline-flex' : 'none';
                    self.triggerCanvasChange(blockId, ovId, 'button', Object.assign(getBtnPayload(), { hidden: isHid }), liveEl || el, instanceId);
                });

                resetBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const defContent = masterItem.content || self.getNodeCleanText(el);
                    valInput.value = defContent;
                    const liveEl = getLiveTarget(el, ovId);
                    self.toggleCanvasElementHidden(liveEl, ovId, false, blockNode);
                    setUiHidden(false);
                    if (liveEl) self.updateNodeTextPreservingIcons(liveEl, defContent);
                    if (badgeEl) badgeEl.style.display = 'none';
                    if (resetBtn) resetBtn.style.display = 'none';
                    self.triggerCanvasChange(blockId, ovId, 'button', { type: 'button', content: defContent, hidden: false }, liveEl || el, instanceId);
                    self.removeOverrideKey(blockId, instanceId, ovId);
                });

            } else if (isLink) {
                const currentHref = (ovData.href !== undefined && ovData.href !== '') ? ovData.href : (el.getAttribute('href') || masterItem.href || '');
                const currentText = (ovData.content !== undefined && ovData.content !== '') ? ovData.content : (self.getNodeCleanText(el) || masterItem.content || '');
                const currentTarget = (ovData.target !== undefined) ? ovData.target : (el.getAttribute('target') || masterItem.target || '');
                let currentLinkId = (ovData.link_id !== undefined && ovData.link_id !== '') ? ovData.link_id : (el.getAttribute('data-align-link-id') || (masterItem.link_id || ''));
                let currentLinkTitle = (ovData.link_title !== undefined && ovData.link_title !== '') ? ovData.link_title : (el.getAttribute('data-align-link-title') || (masterItem.link_title || ''));
                const isModified = (masterItem.content !== undefined && (currentText !== masterItem.content || currentHref !== (masterItem.href || '') || currentTarget !== (masterItem.target || '') || currentLinkId !== (masterItem.link_id || ''))) || Boolean(ovData.content || ovData.href);

                const linkPickerHtml = self.renderLinkPickerHtml ? self.renderLinkPickerHtml({ url: currentHref, linkId: currentLinkId, linkTitle: currentLinkTitle }) : ('<input type="text" class="form-control form-control-sm overwrite-link-href" value="' + self.escapeAttr(currentHref) + '" placeholder="URL (https://...)..." style="font-size: 11px;" />');
                itemRow.innerHTML = renderFieldHeader('Link', ovId, isModified, isHiddenInitial) +
                    '<div style="display:flex;flex-direction:column;gap:6px;"><div><label style="font-size:11px;color:#a0aec0;">Text</label><input type="text" class="form-control form-control-sm overwrite-link-text" value="' + self.escapeAttr(currentText) + '" placeholder="Button / Link Text..." style="font-size:11px;" /></div>' +
                    '<div><label style="font-size:11px;color:#a0aec0;">Url</label>' + linkPickerHtml + '</div>' +
                    '<div><label style="font-size:11px;color:#a0aec0;">Target</label><select class="form-select form-select-sm overwrite-link-target" style="font-size:11px;"><option value="" ' + (currentTarget === '' ? 'selected' : '') + '>Default (_self)</option><option value="_blank" ' + (currentTarget === '_blank' ? 'selected' : '') + '>New window (_blank)</option></select></div></div>';
                sectionBody.appendChild(itemRow);

                const textInput = itemRow.querySelector('.overwrite-link-text'), linkPickerWrap = itemRow.querySelector('.overwrite-link-picker-wrap'), targetSelect = itemRow.querySelector('.overwrite-link-target');
                const badgeEl = itemRow.querySelector('.overwrite-modified-badge'), resetBtn = itemRow.querySelector('.overwrite-reset-btn');

                const getLinkPayload = () => {
                    const hrefInput = itemRow.querySelector('.overwrite-link-href');
                    return { type: 'link', content: textInput.value, href: hrefInput ? hrefInput.value : '', target: targetSelect.value, link_id: currentLinkId, link_title: currentLinkTitle };
                };
                const setUiHidden = bindHideToggle(itemRow, ovId, 'link', getLinkPayload, el);
                if (isHiddenInitial) setUiHidden(true);

                const updateLinkData = function(linkState) {
                    const tVal = textInput.value, tgVal = targetSelect.value;
                    const hrefInput = itemRow.querySelector('.overwrite-link-href');
                    const hVal = (linkState && linkState.href !== undefined) ? linkState.href : (hrefInput ? hrefInput.value : '');
                    if (linkState && linkState.link_id !== undefined) { currentLinkId = linkState.link_id; currentLinkTitle = linkState.link_title || ''; }
                    const liveEl = getLiveTarget(el, ovId);
                    if (liveEl && !liveEl._alignCommentPlaceholder) {
                        self.updateNodeTextPreservingIcons(liveEl, tVal);
                        liveEl.setAttribute('href', hVal);
                        if (tgVal) liveEl.setAttribute('target', tgVal); else liveEl.removeAttribute('target');
                        if (currentLinkId) { liveEl.setAttribute('data-align-link-id', currentLinkId); if (currentLinkTitle) liveEl.setAttribute('data-align-link-title', currentLinkTitle); }
                        else { liveEl.removeAttribute('data-align-link-id'); liveEl.removeAttribute('data-align-link-title'); }
                    }
                    const modified = (masterItem.content !== undefined && (tVal !== masterItem.content || hVal !== (masterItem.href || '') || tgVal !== (masterItem.target || '') || currentLinkId !== (masterItem.link_id || '')));
                    const isHid = itemRow.querySelector('.overwrite-hidden-badge')?.style.display !== 'none';
                    if (badgeEl) badgeEl.style.display = modified ? 'inline-block' : 'none';
                    if (resetBtn) resetBtn.style.display = (modified || isHid) ? 'inline-flex' : 'none';
                    self.triggerCanvasChange(blockId, ovId, 'link', Object.assign(getLinkPayload(), { hidden: isHid }), liveEl || el, instanceId);
                };

                if (linkPickerWrap && self.bindLinkPicker) {
                    self.bindLinkPicker(linkPickerWrap, { url: currentHref, linkId: currentLinkId, linkTitle: currentLinkTitle, onChange: updateLinkData });
                }

                resetBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const defContent = masterItem.content || self.getNodeCleanText(el);
                    const defHref = masterItem.href || el.getAttribute('href') || '';
                    const defTarget = masterItem.target || el.getAttribute('target') || '';
                    const defLinkId = masterItem.link_id || '';
                    const defLinkTitle = masterItem.link_title || '';
                    textInput.value = defContent; targetSelect.value = defTarget; currentLinkId = defLinkId; currentLinkTitle = defLinkTitle;
                    if (linkPickerWrap && self.setLinkState) self.setLinkState(linkPickerWrap, { href: defHref, link_id: defLinkId, link_title: defLinkTitle });
                    else { const hi = itemRow.querySelector('.overwrite-link-href'); if (hi) hi.value = defHref; }
                    const liveEl = getLiveTarget(el, ovId);
                    self.toggleCanvasElementHidden(liveEl, ovId, false, blockNode);
                    setUiHidden(false);
                    if (liveEl) {
                        self.updateNodeTextPreservingIcons(liveEl, defContent);
                        liveEl.setAttribute('href', defHref);
                        if (defTarget) liveEl.setAttribute('target', defTarget); else liveEl.removeAttribute('target');
                        if (defLinkId) { liveEl.setAttribute('data-align-link-id', defLinkId); if (defLinkTitle) liveEl.setAttribute('data-align-link-title', defLinkTitle); }
                        else { liveEl.removeAttribute('data-align-link-id'); liveEl.removeAttribute('data-align-link-title'); }
                    }
                    if (badgeEl) badgeEl.style.display = 'none';
                    if (resetBtn) resetBtn.style.display = 'none';
                    self.triggerCanvasChange(blockId, ovId, 'link', { type: 'link', content: defContent, href: defHref, target: defTarget, link_id: defLinkId, link_title: defLinkTitle, hidden: false }, liveEl || el, instanceId);
                    self.removeOverrideKey(blockId, instanceId, ovId);
                });

                textInput.addEventListener('input', () => updateLinkData());
                targetSelect.addEventListener('change', () => updateLinkData());

            } else {
                const currentContent = (ovData.content !== undefined && ovData.content !== '') ? ovData.content : (el.innerHTML || el.textContent || masterItem.content || '');
                const isLongText = currentContent.length > 50 || tagName === 'P' || tagName === 'DIV' || tagName === 'BLOCKQUOTE';
                const isModified = (masterItem.content !== undefined && currentContent !== masterItem.content) || Boolean(ovData.content && ovData.content !== masterItem.content);
                itemRow.innerHTML = renderFieldHeader('Text', ovId, isModified, isHiddenInitial) +
                    (isLongText ? '<textarea class="form-control form-control-sm overwrite-text-val" rows="3" style="font-size:11px;resize:vertical;">' + self.escapeAttr(currentContent) + '</textarea>' : '<input type="text" class="form-control form-control-sm overwrite-text-val" value="' + self.escapeAttr(currentContent) + '" style="font-size:11px;" />');
                sectionBody.appendChild(itemRow);

                const valInput = itemRow.querySelector('.overwrite-text-val'), badgeEl = itemRow.querySelector('.overwrite-modified-badge'), resetBtn = itemRow.querySelector('.overwrite-reset-btn');
                const getTextPayload = () => ({ type: 'typography', content: valInput.value });
                const setUiHidden = bindHideToggle(itemRow, ovId, 'typography', getTextPayload, el);
                if (isHiddenInitial) setUiHidden(true);

                valInput.addEventListener('input', function() {
                    const liveEl = getLiveTarget(el, ovId);
                    if (liveEl && !liveEl._alignCommentPlaceholder) liveEl.innerHTML = this.value;
                    const modified = (masterItem.content !== undefined && this.value !== masterItem.content);
                    const isHid = itemRow.querySelector('.overwrite-hidden-badge')?.style.display !== 'none';
                    if (badgeEl) badgeEl.style.display = modified ? 'inline-block' : 'none';
                    if (resetBtn) resetBtn.style.display = (modified || isHid) ? 'inline-flex' : 'none';
                    self.triggerCanvasChange(blockId, ovId, 'typography', Object.assign(getTextPayload(), { hidden: isHid }), liveEl || el, instanceId);
                });

                resetBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const defContent = masterItem.content || el.innerHTML || el.textContent || '';
                    valInput.value = defContent;
                    const liveEl = getLiveTarget(el, ovId);
                    self.toggleCanvasElementHidden(liveEl, ovId, false, blockNode);
                    setUiHidden(false);
                    if (liveEl) liveEl.innerHTML = defContent;
                    if (badgeEl) badgeEl.style.display = 'none';
                    if (resetBtn) resetBtn.style.display = 'none';
                    self.triggerCanvasChange(blockId, ovId, 'typography', { type: 'typography', content: defContent, hidden: false }, liveEl || el, instanceId);
                    self.removeOverrideKey(blockId, instanceId, ovId);
                });
            }
        });

        // Add Reset All to Default button
        const resetAllRow = document.createElement('div');
        resetAllRow.className = 'col-12 mt-2 pt-2 border-top text-end';
        resetAllRow.innerHTML = '<button type="button" class="btn btn-sm btn-outline-danger overwrite-reset-all-btn" style="font-size:11px;padding:4px 10px;">Reset All to Default</button>';
        sectionBody.appendChild(resetAllRow);
        resetAllRow.querySelector('.overwrite-reset-all-btn').addEventListener('click', function(e) {
            e.preventDefault();
            sectionBody.querySelectorAll('.overwrite-reset-btn').forEach(btn => btn.click());
            const altKey = self.getAltBlockKey ? self.getAltBlockKey(blockId) : null;
            [blockId, altKey].filter(Boolean).forEach(k => {
                if (k && window._alignActiveReuseOverrides?.[k]) {
                    delete window._alignActiveReuseOverrides[k][instanceId || 'default'];
                    if (Object.keys(window._alignActiveReuseOverrides[k]).length === 0) delete window._alignActiveReuseOverrides[k];
                }
            });
            document.querySelectorAll("#top-panel .save-btn, .save-btn").forEach(b => { b.removeAttribute("disabled"); b.disabled = false; });
        });
    },

    triggerCanvasChange: function(blockId, ovId, type, overrideObj, targetEl, instanceId) {
        window._alignActiveReuseOverrides = window._alignActiveReuseOverrides || {};
        const instKey = instanceId || 'default';
        const altKey = this.getAltBlockKey ? this.getAltBlockKey(blockId) : null;
        [blockId, altKey].filter(Boolean).forEach(k => {
            window._alignActiveReuseOverrides[k] = window._alignActiveReuseOverrides[k] || {};
            window._alignActiveReuseOverrides[k][instKey] = window._alignActiveReuseOverrides[k][instKey] || {};
            window._alignActiveReuseOverrides[k][instKey][ovId] = overrideObj;
        });
        document.querySelectorAll("#top-panel .save-btn, .save-btn").forEach(btn => { btn.removeAttribute("disabled"); btn.disabled = false; });
        if (window.Vvveb?.Undo) {
            Vvveb.Undo.addMutation({ type: 'attributes', target: targetEl || Vvveb.Builder?.selectedEl || document.body, attributeName: 'data-overwrite-updated', newValue: String(Date.now()) });
        }
    },

    removeOverrideKey: function(blockId, instanceId, ovId) {
        if (!window._alignActiveReuseOverrides || !blockId) return;
        const instKey = instanceId || 'default';
        const altKey = this.getAltBlockKey ? this.getAltBlockKey(blockId) : null;
        [blockId, altKey].filter(Boolean).forEach(k => {
            if (window._alignActiveReuseOverrides[k]?.[instKey]) {
                delete window._alignActiveReuseOverrides[k][instKey][ovId];
                if (Object.keys(window._alignActiveReuseOverrides[k][instKey]).length === 0) delete window._alignActiveReuseOverrides[k][instKey];
                if (Object.keys(window._alignActiveReuseOverrides[k]).length === 0) delete window._alignActiveReuseOverrides[k];
            }
        });
    }
});
