/**
 * Align Builder Reusable Block - API Fields Content Overwrite Inspector
 * @package Align
 */

window.AlignBuilderModules = window.AlignBuilderModules || {};
window.AlignBuilderModules.WpReuseBlockOverwrite = window.AlignBuilderModules.WpReuseBlockOverwrite || {};

Object.assign(window.AlignBuilderModules.WpReuseBlockOverwrite, {
    renderContentOverwriteAccordionFromApi: function(blockNode, fields, blockId, instanceId) {
        const self = this;
        const rightPanelProps = document.querySelector("#right-panel .component-properties") || document.querySelector("#properties");
        if (!rightPanelProps) return;

        instanceId = instanceId || self.getReuseBlockInstanceId(blockNode) || 'default';
        const activeId = String(blockNode.id || blockNode.getAttribute?.('data-ast-id') || blockNode._alignUid || blockId || 'selected');
        const masterKey = (blockId || 'noblock') + '_' + instanceId;
        const altKey = self.getAltBlockKey ? self.getAltBlockKey(blockId) : null;
        const userBlockData = window._alignActiveReuseOverrides?.[blockId] || (altKey && window._alignActiveReuseOverrides?.[altKey]) || {};
        const userOverrides = userBlockData[instanceId] || {};

        // Cache master values from API fields response
        self._masterValuesCache = self._masterValuesCache || {};
        self._masterValuesCache[masterKey] = {};
        fields.forEach(function(f) {
            const mVal = (f.master_value !== undefined) ? f.master_value : (f.value || '');
            if (f.fieldType === 'image' || f.type === 'image' || f.attribute === 'src') {
                self._masterValuesCache[masterKey][f.id] = { type: 'media', src: mVal, alt: f.alt || '', width: f.width || '', height: f.height || '', align: f.align || '', hidden: false };
            } else if (f.type === 'button') {
                self._masterValuesCache[masterKey][f.id] = { type: 'button', content: mVal, hidden: false };
            } else if (f.fieldType === 'url' || f.type === 'link' || f.attribute === 'href') {
                self._masterValuesCache[masterKey][f.id] = {
                    type: 'link', href: mVal, content: (f.master_text !== undefined ? f.master_text : (f.content || '')),
                    target: (f.master_target !== undefined ? f.master_target : (f.target || '')),
                    link_id: (f.master_link_id !== undefined ? f.master_link_id : (f.link_id || '')),
                    link_title: (f.master_link_title !== undefined ? f.master_link_title : (f.link_title || '')),
                    hidden: false
                };
            } else {
                self._masterValuesCache[masterKey][f.id] = { type: 'typography', content: mVal, hidden: false };
            }
        });

        self.removeAccordion();

        const accordionWrapper = document.createElement("div");
        accordionWrapper.id = "align-content-overwrite-section";
        accordionWrapper.className = "align-content-overwrite-section";
        accordionWrapper.dataset.activeNodeId = activeId;
        accordionWrapper.dataset.instanceId = instanceId;
        accordionWrapper.dataset.sourceType = 'api';
        accordionWrapper._alignBlockNode = blockNode;
        accordionWrapper.innerHTML = '<label class="header" data-header="content_overwrite" for="header_content_overwrite"><span>Content Overwrite' + (instanceId && instanceId !== 'default' ? ' (' + self.escapeAttr(instanceId) + ')' : '') + '</span><div class="header-arrow"></div></label><input class="header_check" type="checkbox" id="header_content_overwrite" checked="checked"><div class="section row" data-section="content_overwrite"></div>';

        const targetContainer = rightPanelProps.querySelector('.tab-pane[data-section="content"]') || rightPanelProps.querySelector('.tab-pane') || rightPanelProps;
        targetContainer.appendChild(accordionWrapper);

        const sectionBody = accordionWrapper.querySelector('.section[data-section="content_overwrite"]');
        const masterData = self._masterValuesCache[masterKey] || {};

        function renderFieldHeader(labelHtml, ovId, isModified, isHidden) {
            const cleanLabel = (labelHtml || '').replace(/<[^>]*>/g, '').replace(/\s*\[[^\]]*\]/g, '').replace(/\s*Overwrite/i, '').trim();
            const idBadge = ovId ? ' <span style="color:#64748b;font-weight:normal;">[' + self.escapeAttr(ovId.toUpperCase()) + ']</span>' : '';
            return '<div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:4px 8px;margin-bottom:6px;">' +
                '<div style="display:inline-flex;align-items:center;gap:5px;min-width:0;max-width:100%;white-space:nowrap;">' +
                '<span style="font-size:11px;font-weight:600;text-transform:uppercase;color:#a0aec0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + self.escapeAttr(cleanLabel) + idBadge + '</span>' +
                '<span class="badge bg-warning text-dark overwrite-modified-badge" style="font-size:9px;padding:1px 4px;line-height:1.2;white-space:nowrap;display:' + (isModified ? 'inline-block' : 'none') + ';">Modified</span>' +
                '<span class="badge bg-secondary text-light overwrite-hidden-badge" style="font-size:9px;padding:1px 4px;line-height:1.2;white-space:nowrap;display:' + (isHidden ? 'inline-block' : 'none') + ';">Hidden</span>' +
                '</div><div style="display:inline-flex;align-items:center;gap:8px;margin-left:auto;white-space:nowrap;">' +
                '<button type="button" class="btn btn-sm btn-link text-muted p-0 overwrite-hide-btn" style="font-size:11px;text-decoration:none;white-space:nowrap;color:#94a3b8;">' + (isHidden ? 'Unhide' : 'Hide') + '</button>' +
                '<button type="button" class="btn btn-sm btn-link text-muted p-0 overwrite-reset-btn" style="font-size:11px;text-decoration:none;white-space:nowrap;color:#94a3b8;display:' + ((isModified || isHidden) ? 'inline-block' : 'none') + ';">Reset</button>' +
                '</div></div>';
        }

        function bindHideToggle(itemRow, ovId, fieldType, getPayload, field) {
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
                field.hidden = nextHidden;
                self.applyFieldToCanvas(blockNode, ovId, fieldType, getPayload(), nextHidden);
                setUiHiddenState(nextHidden);
                const payload = getPayload();
                payload.hidden = nextHidden;
                self.triggerCanvasChange(blockId, ovId, fieldType, payload, null, instanceId);
            });
            return setUiHiddenState;
        }

        function syncCache(f, ovId, data) {
            Object.assign(f, data);
            if (self.fieldsCache?.[masterKey]) {
                const cf = self.fieldsCache[masterKey].find(item => item.id === ovId);
                if (cf) Object.assign(cf, data);
            }
        }

        fields.forEach(function(field) {
            const ovId = field.id;
            const label = field.label || ('Field ' + ovId);
            const isMedia = (field.fieldType === 'image' || field.fieldType === 'video' || field.type === 'image' || field.type === 'video' || field.attribute === 'src');
            const isButton = (field.type === 'button');
            const isLink = !isButton && (field.fieldType === 'url' || field.type === 'link' || field.attribute === 'href');
            const initialVal = (field.value !== undefined) ? field.value : '';
            const masterItem = masterData[ovId] || {};
            const isHiddenInitial = Boolean(field.hidden || userOverrides[ovId]?.hidden);

            const itemRow = document.createElement("div");
            itemRow.className = "mb-3 col-12 overwrite-item-row";
            itemRow.dataset.overwriteId = ovId;

            if (isMedia) {
                const isModified = (masterItem.src !== undefined && initialVal !== masterItem.src);
                const thumbSrc = self.getAbsoluteImgUrl(initialVal);
                itemRow.innerHTML = renderFieldHeader(label, ovId, isModified, isHiddenInitial) +
                    '<div style="display:flex;flex-direction:column;gap:8px;margin-bottom:10px;"><div class="img-preview-box" style="width:100%;height:120px;border-radius:6px;border:1px dashed #4a5568;overflow:hidden;background:#1a202c;display:flex;align-items:center;justify-content:center;"><img class="overwrite-thumb" src="' + self.escapeAttr(thumbSrc) + '" style="max-width:100%;max-height:100%;object-fit:contain;" /></div>' +
                    '<div style="display:flex;gap:6px;"><input type="text" class="form-control form-control-sm overwrite-media-url" value="' + self.escapeAttr(initialVal) + '" placeholder="Image URL..." style="font-size:11px;" /><button type="button" class="btn btn-sm btn-primary open-media-btn" style="font-size:11px;padding:4px 10px;flex-shrink:0;display:inline-flex;align-items:center;gap:4px;"><i class="la la-image"></i> Set image</button></div></div>' +
                    '<div class="row g-2" style="margin-bottom:8px;"><div class="col-6"><label style="font-size:11px;color:#a0aec0;">Width</label><input type="text" class="form-control form-control-sm overwrite-media-width" value="' + self.escapeAttr(field.width || '') + '" placeholder="200" style="font-size:11px;" /></div><div class="col-6"><label style="font-size:11px;color:#a0aec0;">Height</label><input type="text" class="form-control form-control-sm overwrite-media-height" value="' + self.escapeAttr(field.height || '') + '" placeholder="auto" style="font-size:11px;" /></div></div>' +
                    '<div style="margin-bottom:8px;"><label style="font-size:11px;color:#a0aec0;">Alt</label><input type="text" class="form-control form-control-sm overwrite-media-alt" value="' + self.escapeAttr(field.alt || '') + '" placeholder="Alt description..." style="font-size:11px;" /></div>';
                sectionBody.appendChild(itemRow);

                const urlInput = itemRow.querySelector('.overwrite-media-url');
                const thumbImg = itemRow.querySelector('.overwrite-thumb');
                const openMediaBtn = itemRow.querySelector('.open-media-btn');
                const widthInput = itemRow.querySelector('.overwrite-media-width');
                const heightInput = itemRow.querySelector('.overwrite-media-height');
                const altInput = itemRow.querySelector('.overwrite-media-alt');
                const badgeEl = itemRow.querySelector('.overwrite-modified-badge');
                const resetBtn = itemRow.querySelector('.overwrite-reset-btn');

                const getMediaPayload = () => ({ type: 'media', src: urlInput.value, width: widthInput.value, height: heightInput.value, alt: altInput.value });
                const setUiHidden = bindHideToggle(itemRow, ovId, 'media', getMediaPayload, field);
                if (isHiddenInitial) setUiHidden(true);

                const updateMediaApi = function() {
                    const srcVal = urlInput.value, wVal = widthInput.value, hVal = heightInput.value, altVal = altInput.value;
                    const defSrc = (masterItem.src !== undefined) ? masterItem.src : initialVal;
                    const modified = (srcVal !== defSrc);
                    const isHid = itemRow.querySelector('.overwrite-hidden-badge')?.style.display !== 'none';
                    if (badgeEl) badgeEl.style.display = modified ? 'inline-block' : 'none';
                    if (resetBtn) resetBtn.style.display = (modified || isHid) ? 'inline-flex' : 'none';

                    syncCache(field, ovId, { value: srcVal, width: wVal, height: hVal, alt: altVal, hidden: isHid });
                    self.applyFieldToCanvas(blockNode, ovId, 'media', srcVal, isHid);
                    self.triggerCanvasChange(blockId, ovId, 'media', Object.assign(getMediaPayload(), { hidden: isHid }), null, instanceId);
                };

                resetBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const defSrc = (masterItem.src !== undefined) ? masterItem.src : initialVal;
                    urlInput.value = defSrc;
                    const newThumb = self.getAbsoluteImgUrl(defSrc);
                    if (thumbImg) { thumbImg.src = newThumb; thumbImg.setAttribute('src', newThumb); }
                    widthInput.value = masterItem.width || ''; heightInput.value = masterItem.height || ''; altInput.value = masterItem.alt || '';
                    if (badgeEl) badgeEl.style.display = 'none';
                    if (resetBtn) resetBtn.style.display = 'none';

                    syncCache(field, ovId, { value: defSrc, width: masterItem.width || '', height: masterItem.height || '', alt: masterItem.alt || '', hidden: false });
                    setUiHidden(false);
                    self.applyFieldToCanvas(blockNode, ovId, 'media', defSrc, false);
                    self.triggerCanvasChange(blockId, ovId, 'media', { type: 'media', src: defSrc, width: masterItem.width || '', height: masterItem.height || '', alt: masterItem.alt || '', hidden: false }, null, instanceId);
                    self.removeOverrideKey(blockId, instanceId, ovId);
                });

                openMediaBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (!window.wp?.media) return;
                    const frame = wp.media({ title: 'Select Media for ' + label, button: { text: 'Set image' }, multiple: false });
                    frame.on('select', function() {
                        let url = frame.state().get('selection').first().toJSON().url;
                        try { const p = new URL(url); url = p.pathname + (p.search || '') + (p.hash || ''); } catch(err) {}
                        urlInput.value = url;
                        const newThumb = self.getAbsoluteImgUrl(url);
                        if (thumbImg) { thumbImg.src = newThumb; thumbImg.setAttribute('src', newThumb); }
                        updateMediaApi();
                    });
                    frame.open();
                });

                urlInput.addEventListener('input', function() {
                    const newThumb = self.getAbsoluteImgUrl(this.value);
                    if (thumbImg) { thumbImg.src = newThumb; thumbImg.setAttribute('src', newThumb); }
                    updateMediaApi();
                });
                [widthInput, heightInput, altInput].forEach(inp => inp.addEventListener('input', updateMediaApi));

            } else if (isLink) {
                const initHref = initialVal || '';
                const initContent = (field.content !== undefined) ? field.content : (masterItem.content || '');
                const initTarget = (field.target !== undefined) ? field.target : (masterItem.target || '');
                let currentLinkId = (field.link_id !== undefined) ? field.link_id : (masterItem.link_id || '');
                let currentLinkTitle = (field.link_title !== undefined) ? field.link_title : (masterItem.link_title || '');
                const isModified = (masterItem.content !== undefined && (initContent !== masterItem.content || initHref !== (masterItem.href || '') || initTarget !== (masterItem.target || '') || currentLinkId !== (masterItem.link_id || '')));

                const linkPickerHtml = self.renderLinkPickerHtml ? self.renderLinkPickerHtml({ url: initHref, linkId: currentLinkId, linkTitle: currentLinkTitle }) : ('<input type="text" class="form-control form-control-sm overwrite-link-href" value="' + self.escapeAttr(initHref) + '" placeholder="URL (https://...)..." style="font-size:11px;" />');
                itemRow.innerHTML = renderFieldHeader(label, ovId, isModified, isHiddenInitial) +
                    '<div style="display:flex;flex-direction:column;gap:6px;"><div><label style="font-size:11px;color:#a0aec0;">Text</label><input type="text" class="form-control form-control-sm overwrite-link-text" value="' + self.escapeAttr(initContent) + '" placeholder="Button / Link Text..." style="font-size:11px;" /></div>' +
                    '<div><label style="font-size:11px;color:#a0aec0;">Url</label>' + linkPickerHtml + '</div>' +
                    '<div><label style="font-size:11px;color:#a0aec0;">Target</label><select class="form-select form-select-sm overwrite-link-target" style="font-size:11px;"><option value="" ' + (initTarget === '' ? 'selected' : '') + '>Default (_self)</option><option value="_blank" ' + (initTarget === '_blank' ? 'selected' : '') + '>New window (_blank)</option></select></div></div>';
                sectionBody.appendChild(itemRow);

                const textInput = itemRow.querySelector('.overwrite-link-text');
                const linkPickerWrap = itemRow.querySelector('.overwrite-link-picker-wrap');
                const targetSelect = itemRow.querySelector('.overwrite-link-target');
                const badgeEl = itemRow.querySelector('.overwrite-modified-badge');
                const resetBtn = itemRow.querySelector('.overwrite-reset-btn');

                const getLinkPayload = () => {
                    const hrefInput = itemRow.querySelector('.overwrite-link-href');
                    return { type: 'link', content: textInput.value, href: hrefInput ? hrefInput.value : '', target: targetSelect.value, link_id: currentLinkId, link_title: currentLinkTitle };
                };
                const setUiHidden = bindHideToggle(itemRow, ovId, 'link', getLinkPayload, field);
                if (isHiddenInitial) setUiHidden(true);

                const updateLinkApi = function(linkState) {
                    const tVal = textInput.value, tgVal = targetSelect.value;
                    const hrefInput = itemRow.querySelector('.overwrite-link-href');
                    const hVal = (linkState && linkState.href !== undefined) ? linkState.href : (hrefInput ? hrefInput.value : '');
                    if (linkState && linkState.link_id !== undefined) { currentLinkId = linkState.link_id; currentLinkTitle = linkState.link_title || ''; }

                    const modified = (tVal !== (masterItem.content || '') || hVal !== (masterItem.href || '') || tgVal !== (masterItem.target || '') || currentLinkId !== (masterItem.link_id || ''));
                    const isHid = itemRow.querySelector('.overwrite-hidden-badge')?.style.display !== 'none';
                    if (badgeEl) badgeEl.style.display = modified ? 'inline-block' : 'none';
                    if (resetBtn) resetBtn.style.display = (modified || isHid) ? 'inline-flex' : 'none';

                    syncCache(field, ovId, { value: hVal, content: tVal, target: tgVal, link_id: currentLinkId, link_title: currentLinkTitle, hidden: isHid });
                    self.applyFieldToCanvas(blockNode, ovId, 'link', { href: hVal, content: tVal, target: tgVal, link_id: currentLinkId, link_title: currentLinkTitle }, isHid);
                    self.triggerCanvasChange(blockId, ovId, 'link', Object.assign(getLinkPayload(), { hidden: isHid }), null, instanceId);
                };

                if (linkPickerWrap && self.bindLinkPicker) {
                    self.bindLinkPicker(linkPickerWrap, { url: initHref, linkId: currentLinkId, linkTitle: currentLinkTitle, onChange: updateLinkApi });
                }

                resetBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const defText = masterItem.content || '';
                    const defHref = masterItem.href || '';
                    const defTarget = masterItem.target || '';
                    const defLinkId = masterItem.link_id || '';
                    const defLinkTitle = masterItem.link_title || '';

                    textInput.value = defText; targetSelect.value = defTarget; currentLinkId = defLinkId; currentLinkTitle = defLinkTitle;
                    if (linkPickerWrap && self.setLinkState) self.setLinkState(linkPickerWrap, { href: defHref, link_id: defLinkId, link_title: defLinkTitle });
                    else { const hi = itemRow.querySelector('.overwrite-link-href'); if (hi) hi.value = defHref; }
                    if (badgeEl) badgeEl.style.display = 'none';
                    if (resetBtn) resetBtn.style.display = 'none';

                    syncCache(field, ovId, { value: defHref, content: defText, target: defTarget, link_id: defLinkId, link_title: defLinkTitle, hidden: false });
                    setUiHidden(false);
                    self.applyFieldToCanvas(blockNode, ovId, 'link', { href: defHref, content: defText, target: defTarget, link_id: defLinkId, link_title: defLinkTitle }, false);
                    self.triggerCanvasChange(blockId, ovId, 'link', { type: 'link', href: defHref, content: defText, target: defTarget, link_id: defLinkId, link_title: defLinkTitle, hidden: false }, null, instanceId);
                    self.removeOverrideKey(blockId, instanceId, ovId);
                });

                textInput.addEventListener('input', () => updateLinkApi());
                targetSelect.addEventListener('change', () => updateLinkApi());

            } else if (isButton) {
                const initVal = initialVal || (field.content !== undefined ? field.content : '');
                const isModified = (masterItem.content !== undefined && initVal !== masterItem.content);
                itemRow.innerHTML = renderFieldHeader(label, ovId, isModified, isHiddenInitial) +
                    '<input type="text" class="form-control form-control-sm overwrite-button-val" value="' + self.escapeAttr(initVal) + '" placeholder="Button Text..." style="font-size:11px;" />';
                sectionBody.appendChild(itemRow);

                const valInput = itemRow.querySelector('.overwrite-button-val');
                const badgeEl = itemRow.querySelector('.overwrite-modified-badge');
                const resetBtn = itemRow.querySelector('.overwrite-reset-btn');
                const getBtnPayload = () => ({ type: 'button', content: valInput.value });
                const setUiHidden = bindHideToggle(itemRow, ovId, 'button', getBtnPayload, field);
                if (isHiddenInitial) setUiHidden(true);

                const updateButtonApi = function() {
                    const currentVal = valInput.value;
                    const defVal = (masterItem.content !== undefined) ? masterItem.content : initialVal;
                    const modified = (currentVal !== defVal);
                    const isHid = itemRow.querySelector('.overwrite-hidden-badge')?.style.display !== 'none';
                    if (badgeEl) badgeEl.style.display = modified ? 'inline-block' : 'none';
                    if (resetBtn) resetBtn.style.display = (modified || isHid) ? 'inline-flex' : 'none';

                    syncCache(field, ovId, { value: currentVal, content: currentVal, hidden: isHid });
                    self.applyFieldToCanvas(blockNode, ovId, 'button', currentVal, isHid);
                    self.triggerCanvasChange(blockId, ovId, 'button', Object.assign(getBtnPayload(), { hidden: isHid }), null, instanceId);
                };

                valInput.addEventListener('input', updateButtonApi);

                resetBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const defVal = (masterItem.content !== undefined) ? masterItem.content : initialVal;
                    valInput.value = defVal;
                    if (badgeEl) badgeEl.style.display = 'none';
                    if (resetBtn) resetBtn.style.display = 'none';

                    syncCache(field, ovId, { value: defVal, content: defVal, hidden: false });
                    setUiHidden(false);
                    self.applyFieldToCanvas(blockNode, ovId, 'button', defVal, false);
                    self.triggerCanvasChange(blockId, ovId, 'button', { type: 'button', content: defVal, hidden: false }, null, instanceId);
                    self.removeOverrideKey(blockId, instanceId, ovId);
                });

            } else {
                const isModified = (masterItem.content !== undefined && initialVal !== masterItem.content);
                itemRow.innerHTML = renderFieldHeader(label, ovId, isModified, isHiddenInitial) +
                    '<input type="text" class="form-control form-control-sm overwrite-text-val" value="' + self.escapeAttr(initialVal) + '" placeholder="Enter content..." style="font-size:11px;" />';
                sectionBody.appendChild(itemRow);

                const valInput = itemRow.querySelector('.overwrite-text-val');
                const badgeEl = itemRow.querySelector('.overwrite-modified-badge');
                const resetBtn = itemRow.querySelector('.overwrite-reset-btn');
                const getTextPayload = () => ({ type: 'typography', content: valInput.value });
                const setUiHidden = bindHideToggle(itemRow, ovId, 'typography', getTextPayload, field);
                if (isHiddenInitial) setUiHidden(true);

                const updateTextApi = function() {
                    const currentVal = valInput.value;
                    const defVal = (masterItem.content !== undefined) ? masterItem.content : initialVal;
                    const modified = (currentVal !== defVal);
                    const isHid = itemRow.querySelector('.overwrite-hidden-badge')?.style.display !== 'none';
                    if (badgeEl) badgeEl.style.display = modified ? 'inline-block' : 'none';
                    if (resetBtn) resetBtn.style.display = (modified || isHid) ? 'inline-flex' : 'none';

                    syncCache(field, ovId, { value: currentVal, content: currentVal, hidden: isHid });
                    self.applyFieldToCanvas(blockNode, ovId, 'text', currentVal, isHid);
                    self.triggerCanvasChange(blockId, ovId, 'typography', Object.assign(getTextPayload(), { hidden: isHid }), null, instanceId);
                };

                valInput.addEventListener('input', updateTextApi);

                resetBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const defVal = (masterItem.content !== undefined) ? masterItem.content : initialVal;
                    valInput.value = defVal;
                    if (badgeEl) badgeEl.style.display = 'none';
                    if (resetBtn) resetBtn.style.display = 'none';

                    syncCache(field, ovId, { value: defVal, content: defVal, hidden: false });
                    setUiHidden(false);
                    self.applyFieldToCanvas(blockNode, ovId, 'text', defVal, false);
                    self.triggerCanvasChange(blockId, ovId, 'typography', { type: 'typography', content: defVal, hidden: false }, null, instanceId);
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
            if (blockId && window._alignActiveReuseOverrides?.[blockId]) {
                delete window._alignActiveReuseOverrides[blockId][instanceId || 'default'];
                if (Object.keys(window._alignActiveReuseOverrides[blockId]).length === 0) delete window._alignActiveReuseOverrides[blockId];
            }
            document.querySelectorAll("#top-panel .save-btn, .save-btn").forEach(b => { b.removeAttribute("disabled"); b.disabled = false; });
        });
    },

    applyFieldToCanvas: function(blockNode, ovId, fieldType, value, isHidden) {
        let targetEl = null;
        const q = (r) => r?.querySelector?.('[data-overwrite-id="' + ovId + '"]') || r?.querySelector?.('[data-overwrite-id="' + ovId.toLowerCase() + '"]') || r?.querySelector?.('[data-overwrite-id="' + ovId.toUpperCase() + '"]');
        if (blockNode?.shadowRoot) targetEl = q(blockNode.shadowRoot);
        if (!targetEl && blockNode) targetEl = q(blockNode);
        if (!targetEl && Vvveb.Builder?.frameDoc) targetEl = q(Vvveb.Builder.frameDoc);

        if (isHidden !== undefined && this.toggleCanvasElementHidden) {
            this.toggleCanvasElementHidden(targetEl, ovId, isHidden, blockNode);
            if (isHidden) return;
        }

        if (!targetEl) return;

        if (fieldType === 'media') {
            const src = typeof value === 'object' ? (value.src || '') : value;
            targetEl.setAttribute('src', src);
            targetEl.setAttribute('data-src', src);
            if (targetEl.hasAttribute('srcset')) targetEl.setAttribute('srcset', src);
            if (targetEl.hasAttribute('data-srcset')) targetEl.setAttribute('data-srcset', src);
            if (targetEl.tagName.toUpperCase() === 'IMG') {
                targetEl.src = src;
                if (targetEl.parentNode?.tagName?.toUpperCase() === 'PICTURE') {
                    targetEl.parentNode.querySelectorAll('source').forEach(s => { s.setAttribute('srcset', src); if (s.hasAttribute('data-srcset')) s.setAttribute('data-srcset', src); });
                }
            }
        } else if (fieldType === 'link') {
            if (typeof value === 'object' && value !== null) {
                if (value.href !== undefined) targetEl.setAttribute('href', value.href);
                if (value.target !== undefined) { if (value.target) targetEl.setAttribute('target', value.target); else targetEl.removeAttribute('target'); }
                if (value.link_id !== undefined) { if (value.link_id) targetEl.setAttribute('data-align-link-id', value.link_id); else targetEl.removeAttribute('data-align-link-id'); }
                if (value.link_title !== undefined) { if (value.link_title) targetEl.setAttribute('data-align-link-title', value.link_title); else targetEl.removeAttribute('data-align-link-title'); }
                if (value.content !== undefined) {
                    if (this.updateNodeTextPreservingIcons) this.updateNodeTextPreservingIcons(targetEl, value.content);
                    else targetEl.textContent = value.content;
                }
            } else {
                targetEl.setAttribute('href', value);
            }
        } else if (fieldType === 'button') {
            const bVal = (typeof value === 'object' && value !== null && value.content !== undefined) ? value.content : value;
            if (this.updateNodeTextPreservingIcons) this.updateNodeTextPreservingIcons(targetEl, bVal);
            else targetEl.textContent = bVal;
        } else {
            targetEl.innerHTML = (typeof value === 'object' && value !== null && value.content !== undefined) ? value.content : value;
        }
    },

    refreshFieldValues: function(accordionWrapper, domNodes) {
        const self = this;
        domNodes.forEach(function(el, index) {
            const tagNameLower = el.tagName ? el.tagName.toLowerCase() : 'el';
            const ovId = el.getAttribute('data-overwrite-id') || ('ov-' + tagNameLower + '-' + (index + 1));
            if (!ovId) return;
            const itemRow = accordionWrapper.querySelector('[data-overwrite-id="' + ovId + '"]');
            if (!itemRow) return;

            const tagName = el.tagName ? el.tagName.toUpperCase() : '';
            if (tagName === 'IMG') {
                const urlInput = itemRow.querySelector('.overwrite-media-url');
                const thumbImg = itemRow.querySelector('.overwrite-thumb');
                const currentSrc = el.getAttribute('src') || el.src || '';
                if (urlInput && urlInput.value !== currentSrc) {
                    urlInput.value = currentSrc;
                    const newThumb = self.getAbsoluteImgUrl(currentSrc, el);
                    if (thumbImg && thumbImg.getAttribute('src') !== newThumb) { thumbImg.src = newThumb; thumbImg.setAttribute('src', newThumb); }
                }
            } else if (tagName === 'A') {
                const textInput = itemRow.querySelector('.overwrite-link-text');
                const targetSelect = itemRow.querySelector('.overwrite-link-target');
                const linkPickerWrap = itemRow.querySelector('.overwrite-link-picker-wrap');
                const currentHref = el.getAttribute('href') || '';
                const currentLinkId = el.getAttribute('data-align-link-id') || '';
                const currentLinkTitle = el.getAttribute('data-align-link-title') || '';
                const currentText = self.getNodeCleanText ? self.getNodeCleanText(el) : (el.innerText || el.textContent || '');
                const currentTarget = el.getAttribute('target') || '';
                if (linkPickerWrap && self.setLinkState) {
                    self.setLinkState(linkPickerWrap, { href: currentHref, link_id: currentLinkId, link_title: currentLinkTitle });
                } else {
                    const hrefInput = itemRow.querySelector('.overwrite-link-href');
                    if (hrefInput && hrefInput.value !== currentHref) hrefInput.value = currentHref;
                }
                if (textInput && textInput.value !== currentText) textInput.value = currentText;
                if (targetSelect && targetSelect.value !== currentTarget) targetSelect.value = currentTarget;
            } else {
                const textInput = itemRow.querySelector('.overwrite-text-val') || itemRow.querySelector('.overwrite-button-val');
                const currentText = (tagName === 'BUTTON') && self.getNodeCleanText ? self.getNodeCleanText(el) : (el.innerHTML || el.textContent || '');
                if (textInput && textInput.value !== currentText) textInput.value = currentText;
            }
        });
    },

    triggerCanvasChange: function(blockId, ovId, type, overrideObj, targetEl, instanceId) {
        window._alignActiveReuseOverrides = window._alignActiveReuseOverrides || {};
        const instKey = instanceId || 'default', altKey = this.getAltBlockKey ? this.getAltBlockKey(blockId) : null;
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
        const instKey = instanceId || 'default', altKey = this.getAltBlockKey ? this.getAltBlockKey(blockId) : null;
        [blockId, altKey].filter(Boolean).forEach(k => {
            if (window._alignActiveReuseOverrides[k]?.[instKey]) {
                delete window._alignActiveReuseOverrides[k][instKey][ovId];
                if (Object.keys(window._alignActiveReuseOverrides[k][instKey]).length === 0) delete window._alignActiveReuseOverrides[k][instKey];
                if (Object.keys(window._alignActiveReuseOverrides[k]).length === 0) delete window._alignActiveReuseOverrides[k];
            }
        });
    }
});
