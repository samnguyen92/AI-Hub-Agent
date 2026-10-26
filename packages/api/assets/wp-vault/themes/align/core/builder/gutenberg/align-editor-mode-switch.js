/**
 * Align Theme Editor Mode Switch & Lock Controller
 *
 * Manages editor mode resolution ('gutenberg' | 'builder'), lock state checks,
 * save-in-progress button locking, dirty-state warnings, and destructive mode
 * switching via REST API.
 *
 * Adheres strictly to Align Coding Standards (< 500 lines, English logs/comments).
 *
 * @package Align
 */

(function() {
    'use strict';

    let _isSwitchingRequestInFlight = false;

    const iconLayout = '<svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-8 16H5V5h6v14zm8 0h-6V12h6v7zm0-9h-6V5h6v5z"/></svg>';
    const iconGutenberg = '<svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>';

    function isTemplateEntity(postType) {
        return postType === 'wp_template' || postType === 'wp_template_part';
    }

    function getEligiblePostTypes() {
        const cfgTypes = window.AlignEditorModeConfig?.postTypes;
        const base = Array.isArray(cfgTypes) && cfgTypes.length > 0 ? cfgTypes.slice() : ['post', 'page'];
        if (!base.includes('page')) base.push('page');
        if (!base.includes('post')) base.push('post');
        return base;
    }

    function getSupportedPostTypes() {
        const types = getEligiblePostTypes();
        if (!types.includes('wp_template')) types.push('wp_template');
        if (!types.includes('wp_template_part')) types.push('wp_template_part');
        return types;
    }

    function isEligiblePostType(postType) {
        return Boolean(postType && !isTemplateEntity(postType) && getEligiblePostTypes().includes(postType));
    }

    function getPostId() {
        if (window.AlignSiteEditorIntegration?.getExactPostId) {
            return window.AlignSiteEditorIntegration.getExactPostId();
        }
        try {
            const id = window.wp?.data?.select?.('core/editor')?.getCurrentPostId?.();
            if (id) return id;
        } catch (err) {}
        const hiddenId = document.getElementById('post_ID');
        if (hiddenId?.value) return hiddenId.value;
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('post') || urlParams.get('postId') || urlParams.get('post_id') || null;
    }

    function getPostType() {
        if (window.AlignSiteEditorIntegration?.getExactPostType) {
            return window.AlignSiteEditorIntegration.getExactPostType();
        }
        try {
            const t = window.wp?.data?.select?.('core/editor')?.getCurrentPostType?.();
            if (t) return t;
        } catch (err) {}
        const hiddenType = document.getElementById('post_type');
        if (hiddenType?.value) return hiddenType.value;
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('post_type') || urlParams.get('postType') || 'page';
    }

    function getEntityKey(postType) {
        const target = window.AlignSiteEditorIntegration?.resolveBuilderTarget?.();
        return target?.param || getPostId() || postType || 'page';
    }

    /**
     * Read whether the current post is locked to its editor mode (§4.1, §4.5).
     */
    function isPostLocked(postType) {
        const type = postType || getPostType();
        if (isTemplateEntity(type)) return Boolean(window.AlignTemplateModeLock?.getLockedMode?.(type));

        try {
            const ed = window.wp?.data?.select?.('core/editor');
            const editedMeta = ed?.getEditedPostAttribute?.('meta');
            if (editedMeta && editedMeta._align_editor_locked !== undefined) {
                return editedMeta._align_editor_locked === '1' || editedMeta._align_editor_locked === true;
            }
            const currentMeta = ed?.getCurrentPost?.()?.meta;
            if (currentMeta && currentMeta._align_editor_locked !== undefined) {
                return currentMeta._align_editor_locked === '1' || currentMeta._align_editor_locked === true;
            }
        } catch (err) {
            console.warn('[Align UI] Error reading lock state from store:', err);
        }

        const cfgLocked = window.AlignEditorModeConfig?.locked;
        return cfgLocked === true || cfgLocked === '1';
    }

    /**
     * Read saved editor mode from WordPress data store, localized config, or classic DOM.
     */
    function getSavedEditorModeMeta() {
        try {
            const ed = window.wp?.data?.select?.('core/editor');
            const mode = ed?.getEditedPostAttribute?.('meta')?._align_editor_mode || ed?.getCurrentPost?.()?.meta?._align_editor_mode;
            if (mode === 'builder' || mode === 'gutenberg') return mode;
        } catch (err) {
            console.warn('[Align UI] Error reading editor mode meta from store:', err);
        }

        const cfgMode = window.AlignEditorModeConfig?.lockedMode;
        if (cfgMode === 'builder' || cfgMode === 'gutenberg') return cfgMode;

        const domSelect = document.querySelector?.('select[name="_align_editor_mode"]');
        return (domSelect?.value === 'builder' || domSelect?.value === 'gutenberg') ? domSelect.value : null;
    }

    /**
     * Resolve active editor mode ('gutenberg' | 'builder').
     * When locked, strictly returns the locked mode and ignores URL/sessionStorage (§4.5).
     */
    function getActiveEditorMode(postType) {
        const type = postType || getPostType();
        const defaultMode = (type === 'page') ? 'builder' : 'gutenberg';

        if (isPostLocked(type)) {
            return isTemplateEntity(type) ? window.AlignTemplateModeLock.getLockedMode(type) : (getSavedEditorModeMeta() || defaultMode);
        }

        const urlParams = new URLSearchParams(window.location.search);
        const urlMode = urlParams.get('align_mode');
        if (urlMode === 'builder' || urlMode === 'gutenberg') return urlMode;

        if (!isTemplateEntity(type) && isEligiblePostType(type)) {
            const savedMeta = getSavedEditorModeMeta();
            if (savedMeta) return savedMeta;
        }

        try {
            const stored = window.sessionStorage?.getItem('align_mode_' + getEntityKey(type));
            if (stored === 'builder' || stored === 'gutenberg') return stored;
        } catch (e) {}

        return defaultMode;
    }

    /**
     * Persist editor mode transition for unlocked posts or template entities.
     */
    function setActiveEditorMode(postType, mode) {
        const type = postType || getPostType();
        if (isPostLocked(type)) return false;

        if (!isTemplateEntity(type) && isEligiblePostType(type)) {
            try {
                const edSelect = window.wp?.data?.select?.('core/editor');
                const existingMeta = { ...(edSelect?.getEditedPostAttribute?.('meta') || {}) };
                delete existingMeta._align_editor_locked;
                window.wp?.data?.dispatch?.('core/editor')?.editPost?.({ meta: { ...existingMeta, _align_editor_mode: mode } });
            } catch (err) {
                console.warn('[Align UI] Could not update editor mode meta in store:', err);
            }
        }

        try { window.sessionStorage?.setItem('align_mode_' + getEntityKey(type), mode); } catch (e) {}
        return true;
    }

    /**
     * Check if any save or mode switch operation is currently in progress (§4.6).
     */
    function isSavingInProgress() {
        if (_isSwitchingRequestInFlight || window.AlignSaveCoordinator?.isBusy?.()) return true;
        try {
            const ed = window.wp?.data?.select?.('core/editor');
            if (ed && (ed.isSavingPost?.() || ed.isAutosavingPost?.() || ed.isSavingNonPostEntityChanges?.())) {
                return true;
            }
        } catch (err) {}
        return false;
    }

    /**
     * Check whether the current editor has unsaved changes (§4.7).
     */
    function isEditorDirty(currentMode) {
        try {
            if (currentMode === 'builder' && window.AlignSaveCoordinator?._lastBuilderPayload) return true;
            if (window.wp?.data?.select?.('core/editor')?.isEditedPostDirty?.()) return true;
        } catch (err) {}
        return false;
    }

    /**
     * Check whether the active template entity has unsaved edits in the core-data store.
     */
    function hasUnsavedTemplateEdits(postType) {
        try {
            const entity = window.AlignSaveCoordinator?.resolveActiveEntity?.();
            if (!entity?.id) return false;
            return Boolean(window.wp?.data?.select?.('core')?.hasEditsForEntityRecord?.('postType', postType, entity.id));
        } catch (err) {
            console.warn('[Align UI] Could not read template edit state:', err);
            return false;
        }
    }

    /**
     * Show confirmation modal (or fallback window.confirm) for locked post switch (§4.8).
     */
    function confirmLockedModeSwitch(currentMode, targetMode, postType) {
        const currentLabel = currentMode === 'builder' ? 'Align Builder' : 'Gutenberg';
        const targetLabel = targetMode === 'builder' ? 'Align Builder' : 'Gutenberg';
        const typeLabel = postType === 'wp_template' ? 'template' : (postType === 'wp_template_part' ? 'template part'
            : (String(postType || 'post').replace(/[^a-zA-Z0-9_-]/g, '') || 'post'));
        const keepNote = isTemplateEntity(postType) ? ' Header/footer template parts and the post content block are kept.' : '';
        const title = 'Switch to ' + targetLabel + ' editor?';
        const body = 'This content was built with ' + currentLabel + '. ' + currentLabel + ' and ' + targetLabel + ' store content in different formats, so they cannot be converted.\n\n' +
            'All current content of this ' + typeLabel + ' will be permanently cleared and you will start with an empty ' + targetLabel + ' canvas.' + keepNote + '\n\n' +
            'A revision backup will be created if this post type supports revisions.';

        if (!document.body || typeof document.createElement !== 'function' || window.__ALIGN_TEST_USE_NATIVE_CONFIRM) {
            return Promise.resolve(typeof window.confirm === 'function' ? Boolean(window.confirm(title + '\n\n' + body)) : false);
        }

        return new Promise(function(resolve) {
            const existing = document.getElementById('align-editor-mode-lock-modal');
            if (existing) existing.remove();

            const overlay = document.createElement('div');
            overlay.id = 'align-editor-mode-lock-modal';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.style.cssText = 'position:fixed;inset:0;z-index:999999;background:rgba(15,23,42,0.55);display:flex;align-items:center;justify-content:center;padding:16px;';

            const dialog = document.createElement('div');
            dialog.style.cssText = 'background:#fff;border-radius:8px;max-width:480px;width:100%;padding:24px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.15);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#0f172a;';
            dialog.innerHTML =
                '<h2 style="margin:0 0 12px;font-size:18px;font-weight:600;color:#0f172a;">' + title + '</h2>' +
                '<p style="margin:0 0 10px;font-size:13.5px;line-height:1.5;color:#334155;">This content was built with ' + currentLabel + '. ' + currentLabel + ' and ' + targetLabel + ' store content in different formats, so they cannot be converted.</p>' +
                '<p style="margin:0 0 10px;font-size:13.5px;line-height:1.5;color:#0f172a;"><strong>All current content of this ' + typeLabel + ' will be permanently cleared</strong> and you will start with an empty ' + targetLabel + ' canvas.' + keepNote + '</p>' +
                '<p style="margin:0 0 20px;font-size:12.5px;line-height:1.5;color:#64748b;">A revision backup will be created if this post type supports revisions.</p>' +
                '<div style="display:flex;justify-content:flex-end;gap:8px;">' +
                    '<button type="button" data-action="cancel" style="background:transparent;border:1px solid #cbd5e1;color:#334155;height:34px;padding:0 14px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">Cancel</button>' +
                    '<button type="button" data-action="confirm" style="background:#dc2626;border:1px solid #dc2626;color:#fff;height:34px;padding:0 14px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">Clear content &amp; switch</button>' +
                '</div>';

            overlay.appendChild(dialog);
            document.body.appendChild(overlay);

            const finish = function(result) { overlay.remove(); resolve(result); };
            dialog.querySelector('[data-action="cancel"]')?.addEventListener('click', function() { finish(false); });
            dialog.querySelector('[data-action="confirm"]')?.addEventListener('click', function() { finish(true); });
            overlay.addEventListener('click', function(ev) { if (ev.target === overlay) finish(false); });
        });
    }

    /**
     * Call POST /switch-editor-mode endpoint to clear content and switch mode (§4.4).
     */
    async function requestLockedModeSwitch(postId, targetMode, postType) {
        const switchUrl = window.AlignEditorModeConfig?.switchUrl || (window.location.origin + '/wp-json/align-builder/v1/switch-editor-mode');
        const nonce = window.AlignEditorModeConfig?.nonce || '';

        _isSwitchingRequestInFlight = true;
        updateSwitcherButtonDisabledState();

        try {
            let responseData = null;
            if (typeof window.fetch === 'function') {
                const res = await window.fetch(switchUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                    credentials: 'same-origin',
                    body: JSON.stringify({ post_id: Number(postId), target_mode: targetMode })
                });
                const json = await res.json().catch(function() { return {}; });
                if (!res.ok) throw new Error(json?.message || ('HTTP ' + res.status));
                responseData = json;
            } else if (window.wp?.apiFetch) {
                responseData = await window.wp.apiFetch({
                    url: switchUrl,
                    method: 'POST',
                    data: { post_id: Number(postId), target_mode: targetMode }
                });
            } else {
                throw new Error('No HTTP client available for mode switch.');
            }

            try { window.sessionStorage?.removeItem?.('align_mode_' + getEntityKey(postType)); } catch (e) {}
            reloadWithoutModeParam();
            return responseData;
        } catch (err) {
            failLockedModeSwitch(err);
            return null;
        }
    }

    /**
     * Templates: clear own content in the store (structure kept), save natively, then reopen in target mode.
     */
    async function requestTemplateModeSwitch(targetMode, postType) {
        _isSwitchingRequestInFlight = true;
        updateSwitcherButtonDisabledState();
        try {
            await window.AlignTemplateModeLock.clearForSwitch(postType);
            _isSwitchingRequestInFlight = false;
            setActiveEditorMode(postType, targetMode);
            reloadWithoutModeParam();
            return true;
        } catch (err) {
            failLockedModeSwitch(err);
            return false;
        }
    }

    function reloadWithoutModeParam() {
        try {
            const cleanUrl = new URL(window.location.href);
            if (cleanUrl.searchParams.has('align_mode')) {
                cleanUrl.searchParams.delete('align_mode');
                window.location.href = cleanUrl.toString();
                return;
            }
        } catch (e) {}
        if (typeof window.location?.reload === 'function') window.location.reload();
    }

    function failLockedModeSwitch(err) {
        console.error('[Align UI] Failed to switch locked editor mode:', err);
        _isSwitchingRequestInFlight = false;
        updateSwitcherButtonDisabledState();
        if (typeof window.alert === 'function') {
            window.alert('Failed to switch editor mode: ' + (err?.message || 'Unknown error'));
        }
    }

    /**
     * Handle click on the header mode switcher button (§4.7).
     */
    async function handleSwitchClick(e) {
        if (e) { e.preventDefault(); e.stopPropagation(); }
        if (isSavingInProgress()) return false;

        const btn = document.getElementById('align-mode-switcher-btn');
        const postType = getPostType();
        const modeNow = btn?.getAttribute('data-mode') || getActiveEditorMode(postType);
        const nextMode = (modeNow === 'builder') ? 'gutenberg' : 'builder';

        if (isPostLocked(postType)) {
            const confirmed = await confirmLockedModeSwitch(modeNow, nextMode, postType);
            console.log('[Align UI] Mode switch: ' + modeNow + ' -> ' + nextMode + ' | locked=true | confirmed=' + confirmed);
            if (!confirmed) return false;
            if (isTemplateEntity(postType)) return requestTemplateModeSwitch(nextMode, postType);
            const postId = getPostId();
            if (!postId) return false;
            await requestLockedModeSwitch(postId, nextMode, postType);
            return true;
        }

        // Templates: Builder snapshots already live in the store, so only Gutenberg -> Builder can drop edits
        // (the Builder iframe always loads the last saved template from the database).
        if (isTemplateEntity(postType) && nextMode === 'builder' && hasUnsavedTemplateEdits(postType)) {
            const confirmed = typeof window.confirm === 'function'
                ? Boolean(window.confirm('This template has unsaved changes. Align Builder loads the last saved version, so these changes will be overwritten on the next Builder save. Continue?'))
                : true;
            console.log('[Align UI] Template mode switch with unsaved edits: ' + modeNow + ' -> ' + nextMode + ' | confirmed=' + confirmed);
            if (!confirmed) return false;
        }

        if (!isTemplateEntity(postType) && isEditorDirty(modeNow)) {
            const confirmed = typeof window.confirm === 'function' ? Boolean(window.confirm('Unsaved changes will be lost. Continue?')) : true;
            if (!confirmed) {
                console.log('[Align UI] Mode switch: ' + modeNow + ' -> ' + nextMode + ' | locked=false | confirmed=false');
                return false;
            }
        }

        console.log('[Align UI] Mode switch: ' + modeNow + ' -> ' + nextMode + ' | locked=false | confirmed=true');
        setActiveEditorMode(postType, nextMode);

        if (nextMode === 'builder') {
            window.AlignSiteEditorIntegration?.embedAlignBuilderCanvas?.();
        } else {
            if (window.AlignSaveCoordinator) window.AlignSaveCoordinator._lastBuilderPayload = null;
            window.AlignSiteEditorIntegration?.unmountAlignBuilderCanvas?.();
        }

        mountHeaderSwitcherButton(nextMode);
        return true;
    }

    function renderButtonLabel(btn, currentMode) {
        const isFse = isTemplateEntity(getPostType());
        btn.setAttribute('data-mode', currentMode);
        if (currentMode === 'builder') {
            btn.className = 'align-mode-switcher-btn align-mode-to-gutenberg';
            btn.innerHTML = iconGutenberg + '<span>' + (isFse ? 'Switch to FSE' : 'Switch to Gutenberg') + '</span>';
            btn.title = isFse ? 'Switch back to WordPress FSE Site Editor' : 'Switch back to WordPress Gutenberg block editor';
        } else {
            btn.className = 'align-mode-switcher-btn align-mode-to-builder';
            btn.innerHTML = iconLayout + '<span>Edit with Align Builder</span>';
            btn.title = 'Open Align Visual Builder';
        }
    }

    function updateSwitcherButtonDisabledState() {
        const btn = document.getElementById('align-mode-switcher-btn');
        if (!btn) return;
        const saving = isSavingInProgress();
        btn.disabled = saving;
        if (saving) {
            if (btn.getAttribute('data-saving-state') !== '1') {
                btn.setAttribute('data-saving-state', '1');
                btn.innerHTML = '<span>Saving\u2026</span>';
            }
        } else if (btn.getAttribute('data-saving-state') === '1') {
            btn.removeAttribute('data-saving-state');
            renderButtonLabel(btn, btn.getAttribute('data-mode') || getActiveEditorMode(getPostType()));
        }
    }

    function mountHeaderSwitcherButton(currentMode) {
        const headerSettings = document.querySelector?.('.editor-header__settings, .edit-post-header__settings, .edit-site-header-edit-mode__actions, .edit-site-header__actions, .edit-site-header-edit-mode');
        if (!headerSettings) return;

        let container = document.getElementById('align-mode-switcher-wrap');
        if (!container) {
            container = document.createElement('div');
            container.id = 'align-mode-switcher-wrap';
            container.className = 'align-mode-switcher-container';
            headerSettings.prepend(container);
        } else if (!headerSettings.contains(container)) {
            headerSettings.prepend(container);
        }

        let btn = document.getElementById('align-mode-switcher-btn');
        if (!btn) {
            btn = document.createElement('button');
            btn.id = 'align-mode-switcher-btn';
            btn.type = 'button';
            container.appendChild(btn);
            btn.addEventListener('click', handleSwitchClick);
        }

        const saving = isSavingInProgress();
        btn.disabled = saving;
        if (saving) {
            btn.setAttribute('data-mode', currentMode);
            btn.setAttribute('data-saving-state', '1');
            btn.innerHTML = '<span>Saving\u2026</span>';
            return;
        }

        if (btn.getAttribute('data-saving-state') === '1' || btn.getAttribute('data-mode') !== currentMode || !btn.innerHTML) {
            btn.removeAttribute('data-saving-state');
            renderButtonLabel(btn, currentMode);
        }
    }

    if (window.wp?.data?.subscribe) {
        window.wp.data.subscribe(function() { updateSwitcherButtonDisabledState(); });
    }

    window.AlignEditorModeSwitch = {
        getSupportedPostTypes: getSupportedPostTypes,
        getEligiblePostTypes: getEligiblePostTypes,
        isTemplateEntity: isTemplateEntity,
        isPostLocked: isPostLocked,
        getSavedEditorModeMeta: getSavedEditorModeMeta,
        getActiveEditorMode: getActiveEditorMode,
        setActiveEditorMode: setActiveEditorMode,
        isSavingInProgress: isSavingInProgress,
        isEditorDirty: isEditorDirty,
        mountHeaderSwitcherButton: mountHeaderSwitcherButton,
        handleSwitchClick: handleSwitchClick,
        updateSwitcherButtonDisabledState: updateSwitcherButtonDisabledState
    };
})();
