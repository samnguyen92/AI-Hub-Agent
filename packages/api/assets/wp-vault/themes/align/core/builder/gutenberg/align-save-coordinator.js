/**
 * Align Theme Gutenberg Save Coordinator
 * Coordinates one authoritative save path between WordPress Gutenberg and the
 * embedded Align Visual Builder iframe.
 *
 * @package Align
 */

(function() {
    'use strict';

    window.AlignSaveCoordinator = {
        _isInitialized: false,
        _lastCapturedVersion: 0,
        _isStoreSaving: false,
        _activeSave: null,
        _lastBuilderPayload: null,
        _saveSeq: 0,
        _snapshotTimeoutMs: 5000,

        isBusy: function() {
            return Boolean(this._activeSave || this._isStoreSaving);
        },

        resolveActiveEntity: function() {
            const hasData = !!(window.wp?.data?.select);
            const iframe = this.getIframe();
            const hasEmbeddedFrame = !!(iframe?.contentWindow);
            // Templates are embedded only while the Builder iframe is mounted; in Gutenberg mode
            // native Save must stay untouched (otherwise the click is swallowed with no iframe to snapshot).
            const isBuilderMounted = hasEmbeddedFrame || document.body.classList.contains('is-align-builder-embedded');

            if (hasData) {
                const es = wp.data.select('core/edit-site');
                if (es && es.getEditedPostType) {
                    const postType = es.getEditedPostType();
                    const postId = es.getEditedPostId ? es.getEditedPostId() : null;
                    if (postId && (postType === 'wp_template' || postType === 'wp_template_part')) {
                        const cleanId = String(postId).replace(/^t(p|pl)_/, '');
                        return { kind: postType === 'wp_template' ? 'template' : 'template_part', postType: postType, id: cleanId, isEmbedded: isBuilderMounted };
                    }
                }
            }

            if (hasData) {
                const ed = wp.data.select('core/editor');
                if (ed && ed.getCurrentPostId) {
                    const postId = ed.getCurrentPostId();
                    const postType = ed.getCurrentPostType ? ed.getCurrentPostType() : 'page';
                    if (postId) {
                        if (postType === 'wp_template' || postType === 'wp_template_part') {
                            const cleanId = String(postId).replace(/^t(p|pl)_/, '');
                            return { kind: postType === 'wp_template' ? 'template' : 'template_part', postType: postType, id: cleanId, isEmbedded: isBuilderMounted };
                        }
                        const numId = Number(postId);
                        if (!isNaN(numId) && numId > 0) {
                            const meta = ed.getEditedPostAttribute?.('meta') || {};
                            const defaultMode = (postType === 'page') ? 'builder' : 'gutenberg';
                            const switchMode = window.AlignEditorModeSwitch?.getActiveEditorMode?.(postType);
                            const mode = switchMode || meta._align_editor_mode || defaultMode;
                            const isEmbedded = hasEmbeddedFrame || document.body.classList.contains('is-align-builder-embedded') || mode === 'builder';
                            return { kind: 'post', postType: postType, id: numId, isEmbedded: isEmbedded, editorMode: mode };
                        }
                    }
                }
            }

            const urlParams = new URLSearchParams(window.location.search);
            const pParam = urlParams.get('p');
            const pId = urlParams.get('pId');
            const postTypeParam = urlParams.get('postType') || urlParams.get('post_type') || 'page';
            const postIdParam = urlParams.get('postId') || urlParams.get('post') || urlParams.get('post_id');
            const pathParam = urlParams.get('path');

            if (pParam) {
                const decoded = decodeURIComponent(pParam);
                const pMatch = decoded.match(/^\/?(wp_template|wp_template_part)\/(.+)$/);
                if (pMatch) {
                    const type = pMatch[1];
                    const cleanId = pMatch[2].replace(/^t(p|pl)_/, '');
                    return { kind: type === 'wp_template' ? 'template' : 'template_part', postType: type, id: cleanId, isEmbedded: isBuilderMounted };
                }
            }
            if (pId) {
                const isPart = postTypeParam === 'wp_template_part' || String(pId).startsWith('tp_');
                const cleanId = String(pId).replace(/^t(p|pl)_/, '');
                return { kind: isPart ? 'template_part' : 'template', postType: isPart ? 'wp_template_part' : 'wp_template', id: cleanId, isEmbedded: isBuilderMounted };
            }
            if (postTypeParam === 'wp_template' || postTypeParam === 'wp_template_part') {
                if (postIdParam) {
                    const cleanId = String(postIdParam).replace(/^t(p|pl)_/, '');
                    return { kind: postTypeParam === 'wp_template' ? 'template' : 'template_part', postType: postTypeParam, id: cleanId, isEmbedded: isBuilderMounted };
                }
            }
            if (pathParam) {
                if (pathParam.includes('/template-parts/')) {
                    const slug = pathParam.split('/template-parts/')[1].split('/')[0];
                    return { kind: 'template_part', postType: 'wp_template_part', id: 'align-child//' + slug, isEmbedded: isBuilderMounted };
                }
                if (pathParam.includes('/templates/')) {
                    const slug = pathParam.split('/templates/')[1].split('/')[0];
                    return { kind: 'template', postType: 'wp_template', id: 'align-child//' + slug, isEmbedded: isBuilderMounted };
                }
            }
            if (postIdParam) {
                if (String(postIdParam).includes('//')) {
                    const cleanId = String(postIdParam).replace(/^t(p|pl)_/, '');
                    return { kind: 'template', postType: 'wp_template', id: cleanId, isEmbedded: isBuilderMounted };
                }
                const numId = Number(postIdParam);
                if (!isNaN(numId) && numId > 0) {
                    return { kind: 'post', postType: postTypeParam, id: numId, isEmbedded: hasEmbeddedFrame || document.body.classList.contains('is-align-builder-embedded') };
                }
            }
            return { kind: 'unknown', postType: 'page', id: null, isEmbedded: false };
        },

        getIframe: function() {
            return document.getElementById('align-builder-embedded-frame');
        },

        getEntityKey: function(entity) {
            return entity ? [entity.kind, entity.postType, entity.id].join(':') : 'unknown';
        },

        buildBlockMarkup: function(entity, payload) {
            if (!window.AlignTemplateMarkup) throw new Error('AlignTemplateMarkup module is not loaded.');
            return window.AlignTemplateMarkup.build(entity, payload);
        },

        payloadMatchesEntity: function(entity, payload) {
            if (!payload) return false;
            if (entity.kind === 'post') return !payload.tp_id && String(payload.post_id || '') === String(entity.id);
            if (entity.kind === 'template' || entity.kind === 'template_part') {
                const payloadId = String(payload.tp_id || '').replace(/^t(p|pl)_/, '');
                const entityId = String(entity.id || '').replace(/^t(p|pl)_/, '');
                return !!payloadId && payloadId === entityId;
            }
            return false;
        },

        handleBuilderDirty: function(payload, entityOverride) {
            const entity = entityOverride || this.resolveActiveEntity();
            if (!entity.isEmbedded || !payload) return false;

            if (!this.payloadMatchesEntity(entity, payload)) {
                console.warn('[Align SaveCoordinator] Ignored snapshot for a different entity.', {
                    entity: this.getEntityKey(entity),
                    postId: payload.post_id || null,
                    tpId: payload.tp_id || null
                });
                return false;
            }

            if (payload._capturedVersion !== undefined) {
                this._lastCapturedVersion = payload._capturedVersion;
            }
            this._lastBuilderPayload = payload;

            try {
                const markup = this.buildBlockMarkup(entity, payload);
                const coreSelect = window.wp?.data?.select?.('core');
                const coreDispatch = window.wp?.data?.dispatch?.('core');
                const edited = coreSelect?.getEditedEntityRecord?.('postType', entity.postType, entity.id);
                const editedContent = edited ? (typeof edited.content === 'function' ? edited.content(edited) : edited.content) : null;

                if (entity.kind === 'post') {
                    const ed = window.wp?.data?.dispatch?.('core/editor');
                    const edSelect = window.wp?.data?.select?.('core/editor');
                    if (ed?.editPost) {
                        const existingMeta = edSelect?.getEditedPostAttribute?.('meta') || {};
                        const meta = { ...existingMeta, _align_editor_mode: 'builder' };
                        delete meta._align_editor_locked;
                        if (payload.reusable_overrides) {
                            meta._align_reusable_overrides = payload.reusable_overrides;
                        }
                        ed.editPost({ meta: meta }, { undoIgnore: true });
                    }
                }

                // One entity edit (content + parsed blocks) instead of resetBlocks + editPost per snapshot.
                // The hidden block editor re-syncs from the entity blocks; Builder snapshots stay out of Gutenberg undo.
                if (editedContent === markup) {
                    console.log('[Align SaveCoordinator] Snapshot markup unchanged; skipped store update.');
                } else if (coreDispatch?.editEntityRecord) {
                    const blocks = window.wp?.blocks?.parse ? window.wp.blocks.parse(markup) : undefined;
                    coreDispatch.editEntityRecord('postType', entity.postType, entity.id, { content: markup, blocks: blocks }, { undoIgnore: true });
                    console.log('[Align SaveCoordinator] Synced Builder snapshot to store | Entity: ' + this.getEntityKey(entity) + ' | Markup length: ' + markup.length);
                }
                return true;
            } catch (err) {
                console.warn('[Align SaveCoordinator] Error updating dirty state in store:', err);
                return false;
            }
        },

        getNativeSaveError: function(entity) {
            const edSelect = window.wp?.data?.select?.('core/editor');
            const coreSelect = window.wp?.data?.select?.('core');
            const postError = entity.kind === 'post' ? edSelect?.getLastPostSaveError?.() : null;
            const entityError = entity.id ? coreSelect?.getLastEntitySaveError?.('postType', entity.postType, entity.id) : null;
            return postError || entityError || null;
        },

        triggerNativeSave: function(saveContext) {
            const self = this;
            const context = saveContext || {};
            const entity = context.entity || self.resolveActiveEntity();
            if (!entity.isEmbedded) return Promise.resolve(false);

            console.log(`[Align SaveCoordinator] [NATIVE] Saving ${entity.kind} #${entity.id} | request=${context.requestId || 'manual'} | version=${context.capturedVersion ?? 'n/a'}`);
            const edDispatch = window.wp?.data?.dispatch?.('core/editor');
            const coreDispatch = window.wp?.data?.dispatch?.('core');
            const editSiteDispatch = window.wp?.data?.dispatch?.('core/edit-site');

            if (editSiteDispatch?.setIsSaveViewOpened) {
                editSiteDispatch.setIsSaveViewOpened(false);
            }

            const failIfNativeError = function() {
                const error = self.getNativeSaveError(entity);
                if (error) throw error;
                return true;
            };

            if ((entity.kind === 'template' || entity.kind === 'template_part') && coreDispatch?.saveEditedEntityRecord) {
                return Promise.resolve(coreDispatch.saveEditedEntityRecord('postType', entity.postType, entity.id, { throwOnError: true }))
                    .then(failIfNativeError);
            }

            if (edDispatch?.savePost) {
                return Promise.resolve(edDispatch.savePost()).then(failIfNativeError);
            }

            return Promise.reject(new Error('Native WordPress save API is not available.'));
        },

        notifyIframeSavingState: function(isSaving) {
            const iframe = this.getIframe();
            if (iframe?.contentWindow) {
                iframe.contentWindow.postMessage({
                    type: 'ALIGN_COORDINATOR_SAVING_STATE',
                    saving: !!isSaving
                }, window.location.origin);
            }
        },

        acknowledgeToIframe: function(requestId, success, message, extra) {
            const iframe = this.getIframe();
            if (iframe?.contentWindow) {
                iframe.contentWindow.postMessage({
                    type: 'ALIGN_COORDINATOR_SAVE_ACK',
                    requestId: requestId,
                    success: !!success,
                    message: message || (success ? 'Saved successfully' : 'Save failed'),
                    capturedVersion: extra?.capturedVersion ?? this._lastCapturedVersion,
                    ...(extra || {})
                }, window.location.origin);
            }
        },

        startCoordinatedSave: function(source, initialPayload) {
            const self = this;
            const entity = self.resolveActiveEntity();
            const iframe = self.getIframe();
            if (!entity.isEmbedded || !iframe?.contentWindow) return;

            if (self._activeSave) {
                console.warn('[Align SaveCoordinator] Save already in flight; waiting for the active snapshot/save cycle.');
                return;
            }

            const requestId = 'align-save-' + Date.now() + '-' + (++self._saveSeq);
            self._activeSave = {
                requestId: requestId,
                entity: entity,
                entityKey: self.getEntityKey(entity),
                source: source || 'unknown',
                capturedVersion: self._lastCapturedVersion
            };
            self.notifyIframeSavingState(true);
            console.log('[Align SaveCoordinator] Requesting embedded builder snapshot before native save.', {
                requestId: requestId,
                source: source || 'unknown',
                entity: self._activeSave.entityKey,
                hasInitialPayload: !!initialPayload
            });

            if (initialPayload) {
                self.handleSnapshotReady({ requestId: requestId, payload: initialPayload });
                return;
            }

            self._activeSave.timer = window.setTimeout(function() {
                const active = self._activeSave;
                if (!active || active.requestId !== requestId) return;
                if (self._lastBuilderPayload && self.payloadMatchesEntity(active.entity, self._lastBuilderPayload)) {
                    console.warn('[Align SaveCoordinator] Snapshot request timed out; using last dirty payload fallback.', { requestId: requestId });
                    self.handleSnapshotReady({ requestId: requestId, payload: self._lastBuilderPayload });
                    return;
                }
                self._activeSave = null;
                self.notifyIframeSavingState(false);
                console.error('[Align SaveCoordinator] Snapshot request timed out before native save.', { requestId: requestId });
                self.acknowledgeToIframe(requestId, false, 'Builder snapshot timed out before save.', {
                    capturedVersion: active.capturedVersion
                });
            }, self._snapshotTimeoutMs);

            iframe.contentWindow.postMessage({
                type: 'ALIGN_COORDINATOR_REQUEST_SNAPSHOT',
                requestId: requestId
            }, window.location.origin);
        },

        finishCoordinatedSave: function(requestId, success, message, extra) {
            const active = this._activeSave;
            if (active?.timer) window.clearTimeout(active.timer);
            this._activeSave = null;
            this.notifyIframeSavingState(false);
            this.acknowledgeToIframe(requestId, success, message, extra);
        },

        handleSnapshotReady: function(data) {
            const self = this;
            const active = self._activeSave;
            const requestId = data.requestId || null;
            if (!active || active.requestId !== requestId) {
                console.warn('[Align SaveCoordinator] Ignored stale snapshot response.', { requestId: requestId });
                return;
            }

            const payload = data.payload || null;
            const capturedVersion = payload?._capturedVersion ?? active.capturedVersion;
            active.capturedVersion = capturedVersion;

            if (!self.handleBuilderDirty(payload, active.entity)) {
                self.finishCoordinatedSave(requestId, false, 'Builder snapshot did not match the active editor.', {
                    capturedVersion: capturedVersion
                });
                return;
            }

            self.triggerNativeSave({
                requestId: requestId,
                entity: active.entity,
                capturedVersion: capturedVersion
            }).then(function() {
                self.finishCoordinatedSave(requestId, true, 'Saved successfully', {
                    capturedVersion: capturedVersion
                });
            }).catch(function(err) {
                console.error('[Align SaveCoordinator] Native WordPress save failed:', err);
                self.finishCoordinatedSave(requestId, false, err?.message || 'Save failed', {
                    capturedVersion: capturedVersion
                });
            });
        },

        setupStoreSubscription: function() {
            const self = this;
            if (!window.wp?.data?.subscribe) return;

            window.wp.data.subscribe(function() {
                const entity = self.resolveActiveEntity();
                if (!entity.isEmbedded) return;

                const edSelect = window.wp?.data?.select?.('core/editor');
                const coreSelect = window.wp?.data?.select?.('core');
                const isSaving = Boolean(
                    edSelect?.isSavingPost?.() ||
                    edSelect?.isSavingNonPostEntityChanges?.() ||
                    (entity.id && coreSelect?.isSavingEntityRecord?.('postType', entity.postType, entity.id))
                );

                if (isSaving && !self._isStoreSaving) {
                    self._isStoreSaving = true;
                    console.log('[Align SaveCoordinator] Native WordPress save in progress...');
                    self.notifyIframeSavingState(true);
                } else if (!isSaving && self._isStoreSaving) {
                    self._isStoreSaving = false;
                    self.notifyIframeSavingState(!!self._activeSave);
                    const error = self.getNativeSaveError(entity);
                    if (error) {
                        console.error('[Align SaveCoordinator] Native WordPress save finished with error:', error);
                    } else {
                        console.log('[Align SaveCoordinator] Native WordPress save completed.');
                    }
                }
            });
        },

        isWpSaveButton: function(btn) {
            const text = (btn.textContent || '').trim().toLowerCase();
            return btn.classList.contains('editor-post-publish-button') ||
                btn.classList.contains('editor-post-save-draft') ||
                btn.classList.contains('editor-post-publish-panel__toggle') ||
                btn.classList.contains('editor-post-switch-to-draft') ||
                btn.classList.contains('edit-site-save-button') ||
                btn.classList.contains('editor-entities-saved-states__save-button') ||
                btn.classList.contains('boot-save-button') ||
                (btn.closest('.editor-header__settings, .edit-post-header__settings, .edit-site-header, .editor-header, .edit-post-header') && (
                    btn.classList.contains('is-primary') || /save|update|publish|lưu|cập nhật|đăng/i.test(text)
                ));
        },

        init: function() {
            const self = this;
            if (self._isInitialized) return;
            self._isInitialized = true;

            console.log('[Align SaveCoordinator] Initialized with snapshot-first native WordPress save integration.');
            self.setupStoreSubscription();

            document.addEventListener('click', function(e) {
                const btn = e.target.closest('button, a');
                if (!btn || !self.isWpSaveButton(btn)) return;

                const entity = self.resolveActiveEntity();
                if (entity.isEmbedded) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();

                    const editSiteDispatch = window.wp?.data?.dispatch?.('core/edit-site');
                    if (editSiteDispatch?.setIsSaveViewOpened) editSiteDispatch.setIsSaveViewOpened(false);
                    self.startCoordinatedSave('wp-button');
                }
            }, true);

            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                    const entity = self.resolveActiveEntity();
                    if (entity.isEmbedded) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        self.startCoordinatedSave('keyboard');
                    }
                }
            }, true);

            window.addEventListener('message', function(e) {
                const iframe = self.getIframe();
                if (e.origin !== window.location.origin || !e.data || (iframe?.contentWindow && e.source !== iframe.contentWindow)) return;

                if (e.data.type === 'ALIGN_BUILDER_DIRTY') {
                    self.handleBuilderDirty(e.data.payload);
                }

                if (e.data.type === 'ALIGN_BUILDER_REQUEST_SAVE') {
                    self.startCoordinatedSave('builder-button', e.data.payload || null);
                }

                if (e.data.type === 'ALIGN_COORDINATOR_SNAPSHOT_READY') {
                    self.handleSnapshotReady(e.data);
                }
            });
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => window.AlignSaveCoordinator.init());
    } else {
        window.AlignSaveCoordinator.init();
    }
})();
