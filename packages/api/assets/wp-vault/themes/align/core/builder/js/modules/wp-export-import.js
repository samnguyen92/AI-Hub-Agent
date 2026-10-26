/**
 * Align Theme Builder - Export & Import Page Module
 *
 * Provides toolbar actions to export the saved page data as a clean ZIP package
 * (index.html, page.css, page.js) and import such packages into the active canvas
 * for preview prior to saving.
 *
 * @package Align
 */

window.AlignBuilderModules = window.AlignBuilderModules || {};

window.AlignBuilderModules.WpExportImport = (function() {
    'use strict';

    /**
     * Resolves currently active page or template identifiers.
     *
     * @returns {Object} { postId, tpId, templateType }
     */
    function getActiveContext() {
        const wpConfig = window.VvvebWpConfig || {};
        let activePostId = wpConfig.activePostId || null;
        let activeTpId = wpConfig.activeTpId || null;
        let activeTemplateType = wpConfig.activePostType || null;

        if (window.Vvveb?.FileManager?.currentPage) {
            const curPageObj = window.Vvveb.FileManager.pages[window.Vvveb.FileManager.currentPage];
            if (curPageObj) {
                if (curPageObj.postId) {
                    activePostId = curPageObj.postId;
                    activeTpId = null;
                    activeTemplateType = curPageObj.templateType || 'page';
                } else if (curPageObj.tpId) {
                    activeTpId = curPageObj.tpId;
                    activePostId = null;
                    activeTemplateType = curPageObj.templateType || (curPageObj.tpId.startsWith('tpl_') ? 'template' : 'template_part');
                }
            }
        }

        return {
            postId: activePostId,
            tpId: activeTpId,
            templateType: activeTemplateType
        };
    }

    /**
     * Checks if there are unsaved modifications on canvas.
     *
     * @returns {boolean} True if unsaved changes exist.
     */
    function hasUnsavedChanges() {
        const hasUndo = Boolean(window.Vvveb?.Undo?.hasUndo && window.Vvveb.Undo.hasUndo());
        const saveBtn = document.querySelector('.save-btn');
        const isDirty = saveBtn ? (saveBtn.classList.contains('is-dirty') || saveBtn.getAttribute('data-dirty') === 'true') : false;
        return hasUndo || isDirty;
    }

    /**
     * Handles clicking the Export Page button.
     */
    function handleExport() {
        const wpConfig = window.VvvebWpConfig || {};
        const ctx = getActiveContext();

        if (!ctx.postId && !ctx.tpId) {
            console.warn('[Align ExportImport] Cannot export: No active post_id or tp_id found.');
            if (typeof displayToast === 'function') {
                displayToast('error', 'Export Error', 'No active page found to export.');
            }
            return;
        }

        if (hasUnsavedChanges()) {
            console.log('[Align ExportImport] Unsaved changes detected on canvas during export.');
            if (typeof displayToast === 'function') {
                displayToast('info', 'Export Notice', 'Exporting the last saved version. Unsaved canvas edits will not be included.');
            }
        }

        try {
            const exportUrl = new URL(wpConfig.restExportPageUrl, window.location.origin);
            if (ctx.postId) exportUrl.searchParams.set('post_id', ctx.postId);
            if (ctx.tpId) exportUrl.searchParams.set('tp_id', ctx.tpId);
            if (ctx.templateType) exportUrl.searchParams.set('template_type', ctx.templateType);
            exportUrl.searchParams.set('_wpnonce', wpConfig.restNonce);

            console.log(`[Align ExportImport] 🚀 Initiating export download: post_id=${ctx.postId}, tp_id=${ctx.tpId}`);

            // Direct download trigger via hidden anchor
            const downloadLink = document.createElement('a');
            downloadLink.href = exportUrl.toString();
            downloadLink.style.display = 'none';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            setTimeout(() => {
                document.body.removeChild(downloadLink);
            }, 1000);

            if (typeof displayToast === 'function') {
                displayToast('success', 'Exporting', 'Packaging saved page into ZIP...');
            }
        } catch (e) {
            console.error('[Align ExportImport] ❌ Failed to construct export URL:', e);
            if (typeof displayToast === 'function') {
                displayToast('error', 'Export Error', 'Failed to generate export package.');
            }
        }
    }

    /**
     * Handles clicking the Import Page button.
     */
    function handleImportClick() {
        if (hasUnsavedChanges()) {
            const confirmed = window.confirm('You have unsaved changes on the canvas. Importing a package will replace your current edits with the imported content. Do you want to continue?');
            if (!confirmed) {
                console.log('[Align ExportImport] Import cancelled by user due to unsaved changes.');
                return;
            }
        }

        const fileInput = document.getElementById('align-import-page-file');
        if (fileInput) {
            fileInput.value = '';
            fileInput.click();
        }
    }

    /**
     * Handles file input selection for import.
     *
     * @param {Event} e Change event.
     */
    function handleFileSelected(e) {
        const fileInput = e.target;
        const file = fileInput.files && fileInput.files[0];
        if (!file) return;

        if (!file.name.toLowerCase().endsWith('.zip')) {
            console.warn('[Align ExportImport] Rejected non-ZIP file:', file.name);
            if (typeof displayToast === 'function') {
                displayToast('error', 'Invalid File', 'Please select a valid .zip package.');
            } else {
                alert('Please select a valid .zip package.');
            }
            fileInput.value = '';
            return;
        }

        const wpConfig = window.VvvebWpConfig || {};
        const importBtn = document.getElementById('btn-import-page');
        const originalBtnHtml = importBtn ? importBtn.innerHTML : '<i class="la la-upload"></i>';

        if (importBtn) {
            importBtn.disabled = true;
            importBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
        }

        if (typeof displayToast === 'function') {
            displayToast('info', 'Importing', 'Extracting and reading ZIP package...');
        }

        const formData = new FormData();
        formData.append('zip_file', file);

        console.log('[Align ExportImport] 📤 Uploading ZIP file for extraction:', file.name, `(${file.size} bytes)`);

        fetch(wpConfig.restImportPageUrl, {
            method: 'POST',
            headers: {
                'X-WP-Nonce': wpConfig.restNonce
            },
            body: formData
        })
        .then(res => {
            if (!res.ok) {
                return res.json().then(err => {
                    throw new Error(err.message || ('Import failed with HTTP ' + res.status));
                });
            }
            return res.json();
        })
        .then(data => {
            if (data.status === 'success') {
                console.log('[Align ExportImport] ✅ Package unpacked successfully. Hydrating canvas preview...');
                hydrateImportedState(data);
                if (typeof displayToast === 'function') {
                    displayToast('success', 'Import Preview', 'Page package imported successfully! Previewing changes. Click "Save" to persist.');
                }
            } else {
                throw new Error(data.message || 'Server returned non-success status.');
            }
        })
        .catch(err => {
            console.error('[Align ExportImport] ❌ Import Error:', err);
            if (typeof displayToast === 'function') {
                displayToast('error', 'Import Failed', err.message || 'Failed to import page package.');
            } else {
                alert('Import Error: ' + err.message);
            }
        })
        .finally(() => {
            if (importBtn) {
                importBtn.disabled = false;
                importBtn.innerHTML = originalBtnHtml;
            }
            fileInput.value = '';
        });
    }

    /**
     * Hydrates imported HTML, CSS, JS, and overrides into the active Builder state.
     *
     * @param {Object} data Server response containing { html, css, js, reusable_overrides }
     */
    function hydrateImportedState(data) {
        const html = data.html || '';
        const css  = typeof data.css === 'string' ? data.css : '';
        const js   = typeof data.js === 'string' ? data.js : '';

        // 1. Hydrate HTML Body into canvas
        if (window.Vvveb?.Builder?.setHtml) {
            window.Vvveb.Builder.setHtml(html);
        } else if (window.Vvveb?.Builder?.frameBody) {
            window.Vvveb.Builder.frameBody.innerHTML = html;
        }

        // 2. Hydrate CSS State
        if (window.Vvveb?.StyleManager?.setCss) {
            window.Vvveb.StyleManager.setCss(css);
        }
        if (window.Vvveb?.CssEditor?.setValue) {
            window.Vvveb.CssEditor.setValue(css, true);
        }

        // 3. Hydrate JS State
        if (window.Vvveb?.JsManager?.setJs) {
            window.Vvveb.JsManager.setJs(js);
        }
        if (window.Vvveb?.JsEditor?.setValue) {
            window.Vvveb.JsEditor.setValue(js, true);
        }

        // 4. Hydrate Reusable Overrides
        if (data.reusable_overrides && typeof data.reusable_overrides === 'object') {
            window._alignActiveReuseOverrides = data.reusable_overrides;
        } else {
            window._alignActiveReuseOverrides = {};
        }

        // 5. Synchronize CodeMirror HTML editor
        if (window.Vvveb?.CodeEditor?.setValue && window.Vvveb?.Builder?.getHtml) {
            window.Vvveb.CodeEditor.setValue(window.Vvveb.Builder.getHtml(true, false));
        }

        // 6. Re-hydrate shortcode / component previews and replace dummy images
        if (window.AlignBuilderModules?.WpComponentsPreview?.triggerWpComponentsPreviewInIframe) {
            window.AlignBuilderModules.WpComponentsPreview.triggerWpComponentsPreviewInIframe(true);
        }
        if (window.AlignBuilderModules?.WpStyleInjector?.replaceDummyImagesInIframe) {
            window.AlignBuilderModules.WpStyleInjector.replaceDummyImagesInIframe();
        }

        // 7. Mark as dirty so user is prompted to Save
        if (window.AlignBuilderModules?.WpSaveHandler?.setButtonState) {
            window.AlignBuilderModules.WpSaveHandler.setButtonState('dirty');
        }

        // 8. Register undo mutation
        if (window.Vvveb?.Undo?.addMutation && window.Vvveb?.Builder?.frameBody) {
            window.Vvveb.Undo.addMutation({
                type: 'characterData',
                target: window.Vvveb.Builder.frameBody
            });
        }

        console.log(`[Align ExportImport] Canvas hydrated | HTML: ${html.length} chars, CSS: ${css.length} chars, JS: ${js.length} chars`);
    }

    /**
     * Initialize listeners.
     */
    function init() {
        const exportBtn = document.getElementById('btn-export-page');
        const importBtn = document.getElementById('btn-import-page');
        const fileInput = document.getElementById('align-import-page-file');

        if (exportBtn) {
            exportBtn.addEventListener('click', handleExport);
        }
        if (importBtn) {
            importBtn.addEventListener('click', handleImportClick);
        }
        if (fileInput) {
            fileInput.addEventListener('change', handleFileSelected);
        }

        console.log('[Align ExportImport] Module initialized successfully.');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return {
        init: init,
        handleExport: handleExport,
        handleImportClick: handleImportClick,
        hydrateImportedState: hydrateImportedState
    };
})();
