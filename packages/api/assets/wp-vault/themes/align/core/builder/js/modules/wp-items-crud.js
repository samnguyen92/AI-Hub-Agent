/**
 * Align Theme Builder - WordPress Items Create and Delete UI Manager
 */

window.AlignBuilderModules = window.AlignBuilderModules || {};

window.AlignBuilderModules.WpItemsCrud = {
    init: function() {
        const wpConfig      = window.VvvebWpConfig || {};
        const createItemUrl = wpConfig.restCreateItemUrl;
        const deleteItemUrl = wpConfig.restDeleteItemUrl;
        const restNonce     = wpConfig.restNonce;

        // Toggle dropdown menu for Add button
        const addToggle = document.getElementById('new-item-toggle');
        const addMenu   = document.getElementById('add-item-menu');

        if (addToggle && addMenu) {
            addToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                const isOpen = addMenu.style.display === 'flex';
                addMenu.style.display = isOpen ? 'none' : 'flex';
                addMenu.style.flexDirection = 'column';
            });
            document.addEventListener('click', function() {
                addMenu.style.display = 'none';
            });
        }

        // Modal logic
        const modal       = document.getElementById('align-create-item-modal');
        const modalTitle  = document.getElementById('align-modal-title');
        const inputTitle  = document.getElementById('align-item-title');
        const inputSlug   = document.getElementById('align-item-slug');
        const btnCancel   = document.getElementById('align-modal-cancel');
        const btnConfirm  = document.getElementById('align-modal-confirm');
        let currentItemType = 'page';

        function openCreateModal(type) {
            currentItemType = type;
            const titles = { template: 'Add New Template', template_part: 'Add New Template Part (Header, Footer, Part)', reusable_block: 'Add New Reusable Block' };
            modalTitle.textContent = titles[type] || 'Add New Page';
            inputTitle.value = '';
            inputSlug.value  = '';
            document.body.appendChild(modal);
            modal.style.cssText = 'display:flex !important;position:fixed !important;inset:0 !important;z-index:2147483647 !important;background:rgba(0,0,0,0.55) !important;align-items:center !important;justify-content:center !important;pointer-events:all !important;';
            setTimeout(() => inputTitle.focus(), 50);
            if (addMenu) addMenu.style.display = 'none';
        }

        function closeCreateModal() {
            modal.style.display = 'none';
            inputTitle.value = '';
            inputSlug.value  = '';
        }

        if (inputTitle) {
            inputTitle.addEventListener('input', function() {
                inputSlug.value = this.value.toLowerCase().trim().replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
            });
        }

        const addPageBtn         = document.getElementById('add-wp-page-btn');
        const addTemplateBtn     = document.getElementById('add-wp-template-btn');
        const addTemplatePartBtn = document.getElementById('add-wp-template-part-btn');
        const addBlockBtn        = document.getElementById('add-wp-block-btn');
        if (addPageBtn)         addPageBtn.addEventListener('click',         () => openCreateModal('page'));
        if (addTemplateBtn)     addTemplateBtn.addEventListener('click',     () => openCreateModal('template'));
        if (addTemplatePartBtn) addTemplatePartBtn.addEventListener('click', () => openCreateModal('template_part'));
        if (addBlockBtn)        addBlockBtn.addEventListener('click',        () => openCreateModal('reusable_block'));
        if (btnCancel)      btnCancel.addEventListener('click', closeCreateModal);
        if (modal) modal.addEventListener('click', e => { if (e.target === modal) closeCreateModal(); });

        if (btnConfirm) {
            btnConfirm.addEventListener('click', function() {
                const title = inputTitle.value.trim();
                const slug  = inputSlug.value.trim() || title.toLowerCase().replace(/\s+/g, '-');
                if (!title) {
                    inputTitle.style.borderColor = '#ef4444';
                    inputTitle.focus();
                    return;
                }
                if (!createItemUrl) {
                    alert('REST API URL not configured.');
                    return;
                }

                const hasUnsaved = (window.Vvveb?.Undo?.undoIndex > 0) || false;
                if (hasUnsaved) {
                    const confirmed = window.confirm(
                        'You have unsaved changes on this page.\n\n' +
                        'Creating and navigating to "' + title + '" will leave the current page without saving.\n\n' +
                        'Click OK to proceed, or Cancel to stay and save first.'
                    );
                    if (!confirmed) return;
                }

                btnConfirm.textContent = 'Creating...';
                btnConfirm.disabled = true;

                fetch(createItemUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': restNonce
                    },
                    body: JSON.stringify({ type: currentItemType, title: title, slug: slug })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success' || data.post_id) {
                        const typeLabel = currentItemType === 'template' ? 'Template' : (currentItemType === 'template_part' ? 'Template Part' : (currentItemType === 'reusable_block' ? 'Reusable block' : 'Page'));
                        const scInfo = data.shortcode ? (' (Shortcode: ' + data.shortcode + ')') : '';
                        console.log('[Align Crud] ' + typeLabel + ' created successfully:', data);

                        const isEmbedded = window.parent !== window;
                        const isPage = currentItemType === 'page' || data.type === 'page' || (data.post_id && !data.tp_id);
                        const builderSlug = wpConfig.builderSlug || 'align-builder';
                        let redirectUrl = null;

                        if (isPage && data.post_id) {
                            redirectUrl = new URL('post.php', window.location.href);
                            redirectUrl.search = new URLSearchParams({ post: data.post_id, action: 'edit' }).toString();
                        } else if (data.admin_edit_url) {
                            redirectUrl = new URL(data.admin_edit_url, window.location.href);
                        } else if (data.editor_url) {
                            redirectUrl = new URL(data.editor_url, window.location.href);
                        } else {
                            redirectUrl = new URL(window.location.href);
                            if (data.tp_id) {
                                redirectUrl.search = new URLSearchParams({ page: builderSlug, tp_id: data.tp_id }).toString();
                            } else if (data.post_id) {
                                redirectUrl.search = new URLSearchParams({ page: builderSlug, post_id: data.post_id }).toString();
                            }
                        }

                        const targetUrl = redirectUrl ? redirectUrl.href : '';
                        const navigationWindow = (isPage || isEmbedded) ? (window.top || window.parent || window) : window;

                        if (typeof displayToast === 'function') {
                            displayToast('success', 'Created', typeLabel + ' "' + title + '" created! Redirecting to editor...' + scInfo);
                        }

                        closeCreateModal();

                        setTimeout(function() {
                            if (targetUrl) {
                                console.log('[Align Crud] Redirecting to created item editor:', targetUrl, '| Target:', (isPage || isEmbedded) ? 'parent' : 'iframe');
                                if (isEmbedded || isPage) {
                                    try { (window.top || window.parent).postMessage({ type: 'ALIGN_NAVIGATE', url: targetUrl }, window.location.origin); } catch (e) {}
                                }
                                navigationWindow.location.href = targetUrl;
                            } else {
                                window.location.reload();
                            }
                        }, 350);
                    } else {
                        const errMsg = data.message || data.code || 'Unknown error';
                        console.warn('[Align Crud] Failed to create item:', errMsg);
                        if (typeof displayToast === 'function') {
                            displayToast('danger', 'Error', 'Failed to create: ' + errMsg);
                        } else {
                            alert('Error: ' + errMsg);
                        }
                        btnConfirm.textContent = 'Create';
                        btnConfirm.disabled = false;
                    }
                })
                .catch(err => {
                    console.error('[Align Crud] Create item error:', err);
                    if (typeof displayToast === 'function') {
                        displayToast('danger', 'Error', 'Network error while creating item.');
                    } else {
                        alert('Network error.');
                    }
                    btnConfirm.textContent = 'Create';
                    btnConfirm.disabled = false;
                });
            });
        }

        function hookDeleteButtons() {
            if (!deleteItemUrl) return;

            const fileItems = document.querySelectorAll('#filemanager .tree li.file:not([data-delete-hooked])');
            fileItems.forEach(function(li) {
                li.setAttribute('data-delete-hooked', '1');

                const deleteBtn = li.querySelector('.delete');
                if (!deleteBtn) return;

                deleteBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    e.preventDefault();

                    const pageName = li.getAttribute('data-page') || '';
                    let postId = null;
                    let tpId   = null;

                    if (pageName && window.Vvveb && window.Vvveb.FileManager && window.Vvveb.FileManager.pages[pageName]) {
                        const pageObj = window.Vvveb.FileManager.pages[pageName];
                        postId = pageObj.postId || null;
                        tpId   = pageObj.tpId   || null;
                    }

                    if (!postId && !tpId) {
                        postId = li.getAttribute('data-post_id') || li.dataset.post_id || null;
                        const isTp = pageName && (pageName.startsWith('tpl_') || pageName.startsWith('tp_'));
                        tpId = isTp ? pageName : null;
                    }

                    const titleEl = li.querySelector('label > span');
                    const itemName = titleEl ? titleEl.textContent.trim() : 'this item';

                    if (!postId && !tpId) {
                        alert('Cannot determine item ID to delete.');
                        return;
                    }

                    if (!confirm('Delete "' + itemName + '" from WordPress? This cannot be undone.')) return;

                    const body = postId ? { post_id: parseInt(postId, 10) } : { tp_id: tpId };

                    fetch(deleteItemUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': restNonce
                        },
                        body: JSON.stringify(body)
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'success') {
                            console.log('[Align Crud] Item deleted successfully:', data);
                            if (typeof displayToast === 'function') {
                                displayToast('success', 'Deleted', '"' + itemName + '" deleted successfully.');
                            }

                            const currentParams = new URLSearchParams(window.location.search);
                            const currentPostId = currentParams.get('post_id') || currentParams.get('postId') || wpConfig.activePostId;
                            const currentTpId   = currentParams.get('tp_id') || currentParams.get('pId') || wpConfig.activeTpId;
                            const isEmbedded = window.parent !== window;
                            const parentPostId = isEmbedded ? new URLSearchParams(window.parent.location.search).get('post') : null;
                            const isCurrentActive = (postId && (String(postId) === String(currentPostId) || String(postId) === parentPostId)) || (tpId && String(tpId) === String(currentTpId));
                            const builderSlug = wpConfig.builderSlug || 'align-builder';
                            let redirectUrl = new URL(window.location.href);
                            if (isCurrentActive) {
                                const remainingPage = Object.values(window.Vvveb?.FileManager?.pages || {}).find(page =>
                                    page.folder === 'Pages' && !page.tpId && !page.isReusable &&
                                    /^[1-9]\d*$/.test(String(page.postId)) && String(page.postId) !== String(postId)
                                );
                                // Reload the WordPress editor so its title, metadata and save target match the canvas.
                                if (!remainingPage) {
                                    redirectUrl = new URL('edit.php?post_type=page', window.location.href);
                                } else if (isEmbedded) {
                                    redirectUrl = new URL('post.php', window.location.href);
                                    redirectUrl.search = new URLSearchParams({ post: remainingPage.postId, action: 'edit' }).toString();
                                } else {
                                    redirectUrl.search = new URLSearchParams({ page: builderSlug, post_id: remainingPage.postId }).toString();
                                    const lang = remainingPage.lang || currentParams.get('lang');
                                    if (lang) redirectUrl.searchParams.set('lang', lang);
                                }
                                console.log('[Align Crud] Redirecting after active item deletion:', {
                                    deletedId: postId || tpId, nextPostId: remainingPage?.postId || null,
                                    embedded: isEmbedded, url: redirectUrl.href
                                });
                            }
                            const navigationWindow = isCurrentActive && isEmbedded ? window.parent : window;
                            navigationWindow.location.href = redirectUrl.href;
                        } else {
                            const errMsg = data.message || data.code || 'Unknown error';
                            console.warn('[Align Crud] Delete failed:', errMsg);
                            if (typeof displayToast === 'function') {
                                displayToast('danger', 'Error', 'Delete failed: ' + errMsg);
                            } else {
                                alert('Delete error: ' + errMsg);
                            }
                        }
                    })
                    .catch(err => {
                        console.error('[Align Crud] Delete error:', err);
                        alert('Network error while deleting.');
                    });
                });
            });
        }

        // Rename Item Modal & Button Logic
        const renameModal      = document.getElementById('align-rename-item-modal');
        const renameModalTitle = document.getElementById('align-rename-modal-title');
        const renameInputTitle = document.getElementById('align-rename-item-title');
        const renameBtnCancel  = document.getElementById('align-rename-modal-cancel');
        const renameBtnConfirm = document.getElementById('align-rename-modal-confirm');
        const renameItemUrl    = wpConfig.restRenameItemUrl;
        let activeRenameItem   = { postId: null, tpId: null, pageName: '', iconPrefix: '', li: null };

        function openRenameModal(postId, tpId, pageName, currentTitle, li) {
            if (!renameModal) return;
            const match = currentTitle.match(/^(📄|🧱|🎨|📌\s*Header:\s*|🦶\s*Footer:\s*|🧩\s*Part:\s*)\s*/u);
            const iconPrefix = match ? match[0] : '';
            const rawTitle   = match ? currentTitle.slice(match[0].length).trim() : currentTitle.trim();

            activeRenameItem = { postId, tpId, pageName, iconPrefix, li };
            if (renameInputTitle) renameInputTitle.value = rawTitle;
            if (renameModalTitle) renameModalTitle.textContent = 'Rename Item';

            document.body.appendChild(renameModal);
            renameModal.style.cssText = 'display:flex !important;position:fixed !important;inset:0 !important;z-index:2147483647 !important;background:rgba(0,0,0,0.55) !important;align-items:center !important;justify-content:center !important;pointer-events:all !important;';
            setTimeout(function() {
                if (renameInputTitle) {
                    renameInputTitle.focus();
                    renameInputTitle.select();
                }
            }, 50);
        }

        function closeRenameModal() {
            if (!renameModal) return;
            renameModal.style.display = 'none';
            if (renameInputTitle) {
                renameInputTitle.value = '';
                renameInputTitle.style.borderColor = '#e0e0e0';
            }
            activeRenameItem = { postId: null, tpId: null, pageName: '', iconPrefix: '', li: null };
            if (renameBtnConfirm) {
                renameBtnConfirm.textContent = 'Save';
                renameBtnConfirm.disabled = false;
            }
        }

        if (renameBtnCancel) renameBtnCancel.addEventListener('click', closeRenameModal);
        if (renameModal) {
            renameModal.addEventListener('click', function(e) {
                if (e.target === renameModal) closeRenameModal();
            });
        }
        if (renameInputTitle) {
            renameInputTitle.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (renameBtnConfirm) renameBtnConfirm.click();
                } else if (e.key === 'Escape') {
                    closeRenameModal();
                }
            });
        }

        if (renameBtnConfirm) {
            renameBtnConfirm.addEventListener('click', function() {
                const newTitle = renameInputTitle ? renameInputTitle.value.trim() : '';
                if (!newTitle) {
                    if (renameInputTitle) {
                        renameInputTitle.style.borderColor = '#ef4444';
                        renameInputTitle.focus();
                    }
                    return;
                }

                if (!renameItemUrl) {
                    alert('Rename REST API URL is not configured.');
                    return;
                }

                renameBtnConfirm.textContent = 'Saving...';
                renameBtnConfirm.disabled = true;

                const body = activeRenameItem.postId ? { post_id: activeRenameItem.postId, title: newTitle } : { tp_id: activeRenameItem.tpId, title: newTitle };

                fetch(renameItemUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': restNonce
                    },
                    body: JSON.stringify(body)
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        const fullTitle = (activeRenameItem.iconPrefix || '') + newTitle;
                        console.log('[Align Crud] Item renamed successfully:', data);

                        // 1. Live update DOM tree node label
                        if (activeRenameItem.li) {
                            const titleSpan = activeRenameItem.li.querySelector('label > span');
                            if (titleSpan) {
                                titleSpan.textContent = fullTitle;
                            }
                        }

                        // 2. Update Vvveb.FileManager page registry
                        if (window.Vvveb && window.Vvveb.FileManager && window.Vvveb.FileManager.pages && activeRenameItem.pageName) {
                            if (window.Vvveb.FileManager.pages[activeRenameItem.pageName]) {
                                window.Vvveb.FileManager.pages[activeRenameItem.pageName].title = fullTitle;
                            }
                        }

                        if (typeof displayToast === 'function') {
                            displayToast('success', 'Renamed', 'Item renamed to "' + newTitle + '" successfully.');
                        }

                        closeRenameModal();
                    } else {
                        const errMsg = data.message || data.code || 'Unknown error';
                        console.warn('[Align Crud] Rename failed:', errMsg);
                        if (typeof displayToast === 'function') {
                            displayToast('danger', 'Error', 'Rename failed: ' + errMsg);
                        } else {
                            alert('Rename error: ' + errMsg);
                        }
                        renameBtnConfirm.textContent = 'Save';
                        renameBtnConfirm.disabled = false;
                    }
                })
                .catch(err => {
                    console.error('[Align Crud] Rename network error:', err);
                    if (typeof displayToast === 'function') {
                        displayToast('danger', 'Error', 'Network error while renaming item.');
                    } else {
                        alert('Network error while renaming item.');
                    }
                    renameBtnConfirm.textContent = 'Save';
                    renameBtnConfirm.disabled = false;
                });
            });
        }

        function hookRenameButtons() {
            if (!renameItemUrl) return;

            const fileItems = document.querySelectorAll('#filemanager .tree li.file:not([data-rename-hooked])');
            fileItems.forEach(function(li) {
                li.setAttribute('data-rename-hooked', '1');

                const renameBtn = li.querySelector('.rename');
                if (!renameBtn) return;

                renameBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    e.preventDefault();

                    const pageName = li.getAttribute('data-page') || '';
                    let postId = null;
                    let tpId   = null;
                    let currentTitle = '';

                    if (pageName && window.Vvveb && window.Vvveb.FileManager && window.Vvveb.FileManager.pages[pageName]) {
                        const pageObj = window.Vvveb.FileManager.pages[pageName];
                        postId = pageObj.postId || null;
                        tpId   = pageObj.tpId   || null;
                        currentTitle = pageObj.title || '';
                    }

                    if (!postId && !tpId) {
                        postId = li.getAttribute('data-post_id') || li.dataset.post_id || null;
                        const isTp = pageName && (pageName.startsWith('tpl_') || pageName.startsWith('tp_'));
                        tpId = isTp ? pageName : null;
                    }

                    if (!currentTitle) {
                        const titleEl = li.querySelector('label > span');
                        currentTitle = titleEl ? titleEl.textContent.trim() : '';
                    }

                    if (!postId && !tpId) {
                        alert('Cannot determine item ID to rename.');
                        return;
                    }

                    openRenameModal(postId ? parseInt(postId, 10) : null, tpId, pageName, currentTitle, li);
                });
            });
        }

        const filemanagerTree = document.querySelector('#filemanager .tree ol');
        if (filemanagerTree) {
            const treeObserver = new MutationObserver(function() {
                hookDeleteButtons();
                hookRenameButtons();
            });
            treeObserver.observe(filemanagerTree, { childList: true, subtree: true });
        }
        hookDeleteButtons();
        hookRenameButtons();
    }
};
