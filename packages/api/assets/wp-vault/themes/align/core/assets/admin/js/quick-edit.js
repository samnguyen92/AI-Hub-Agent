/**
 * JS for Front-End Quick Edit
 *
 * Handles floating edit button positioning, REST API fetching/saving,
 * and direct DOM manipulation with high-end transition effects.
 * Supports nested tags and redirects edit targeting to the deepest editable child.
 * Validates text presence to ensure only blocks with actual text can be edited.
 * Implements AJAX autocomplete search for links (href & hrefId) identical to Gutenberg CMS.
 */
document.addEventListener('DOMContentLoaded', function () {
    // 1. Check for editable blocks
    const editableBlocks = document.querySelectorAll('[data-edit-id]');
    if (editableBlocks.length === 0) {
        return;
    }

    // Ensure we have localized API configuration
    if (typeof alignQuickEditData === 'undefined') {
        console.error('Align Quick Edit data is missing.');
        return;
    }

    // 2. Inject floating edit button
    const floatBtn = document.createElement('button');
    floatBtn.className = 'align-quick-edit-float-btn';
    floatBtn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M12 20h9" stroke-linecap="round" stroke-linejoin="round"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
    floatBtn.title = 'Quick Edit';
    document.body.appendChild(floatBtn);

    // 3. Inject modal template with input wrapper and search result dropdown
    const modal = document.createElement('div');
    modal.className = 'align-quick-edit-modal';
    modal.innerHTML = `
        <div class="align-quick-edit-modal-backdrop"></div>
        <div class="align-quick-edit-modal-content">
            <div class="align-quick-edit-modal-header">
                <h3>Quick Edit Content</h3>
                <button class="align-quick-edit-modal-close" aria-label="Close">&times;</button>
            </div>
            <div class="align-quick-edit-modal-body">
                <div class="align-quick-edit-loading-overlay">
                    <div class="align-quick-edit-spinner"></div>
                </div>
                <div class="align-quick-edit-error-msg" style="display: none; color: #ef4444; background: #fee2e2; border: 1px solid #fca5a5; padding: 10px; border-radius: 6px; margin-bottom: 16px; font-size: 13px; line-height: 1.4;"></div>
                <div class="align-quick-edit-field-group">
                    <label for="align-quick-edit-textarea">Text Content</label>
                    <textarea id="align-quick-edit-textarea" placeholder="Enter block content..."></textarea>
                </div>
                <div class="align-quick-edit-field-group align-quick-edit-href-group" style="display: none; position: relative;">
                    <label for="align-quick-edit-href">Link URL (href)</label>
                    <div class="align-quick-edit-badge-wrapper" style="display: none;">
                        <div class="badge-left">
                            <span class="dashicons dashicons-admin-page"></span>
                            <span class="badge-page-title">Linked Page</span>
                            <span class="badge-page-id">ID: 0</span>
                        </div>
                        <button class="align-quick-edit-badge-disconnect" type="button">Disconnect</button>
                    </div>
                    <div class="align-quick-edit-input-wrapper">
                        <input type="text" id="align-quick-edit-href" placeholder="e.g. /about-us or search post/page..." autocomplete="off" />
                        <div class="align-quick-edit-search-spinner" style="display: none;"></div>
                    </div>
                    <div class="align-quick-edit-search-results"></div>
                </div>
            </div>
            <div class="align-quick-edit-modal-footer">
                <button class="align-quick-edit-btn-cancel">Cancel</button>
                <button class="align-quick-edit-btn-save">Save Changes</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    const backdrop = modal.querySelector('.align-quick-edit-modal-backdrop');
    const closeBtn = modal.querySelector('.align-quick-edit-modal-close');
    const cancelBtn = modal.querySelector('.align-quick-edit-btn-cancel');
    const saveBtn = modal.querySelector('.align-quick-edit-btn-save');
    const textarea = modal.querySelector('#align-quick-edit-textarea');
    const hrefInput = modal.querySelector('#align-quick-edit-href');
    const hrefGroup = modal.querySelector('.align-quick-edit-href-group');
    const badgeWrapper = modal.querySelector('.align-quick-edit-badge-wrapper');
    const badgeTitle = modal.querySelector('.badge-page-title');
    const badgeId = modal.querySelector('.badge-page-id');
    const badgeDisconnect = modal.querySelector('.align-quick-edit-badge-disconnect');
    const searchSpinner = modal.querySelector('.align-quick-edit-search-spinner');
    const searchResults = modal.querySelector('.align-quick-edit-search-results');
    const loadingOverlay = modal.querySelector('.align-quick-edit-loading-overlay');

    let activeElement = null;
    let activeEditId = null;
    let activeLinkEditId = null; // edit_id of associated anchor block if exists
    let activeHrefId = 0;        // Associated link ID on database (Gutenberg hrefId)
    let activeHref = '';         // URL value to save/update dynamic permalinks realtime
    let hideTimeout = null;
    let isHoveringElement = false;
    let isHoveringBtn = false;
    let searchDebounce = null;

    function showDynamicBadge(id, title) {
        if (!id || id <= 0) return;
        
        activeHrefId = parseInt(id);
        badgeId.textContent = `ID: ${id}`;
        
        // Hide standard input wrapper
        if (hrefInput) {
            hrefInput.parentElement.style.display = 'none';
            hrefInput.value = '';
        }
        searchResults.classList.remove('is-visible');
        searchResults.innerHTML = '';
        
        // Show badge
        badgeWrapper.style.display = 'flex';
        
        if (title) {
            badgeTitle.textContent = title;
            // Fetch relative url dynamically to make sure activeHref is correct
            fetch(`/wp-json/custom/v1/permalink?id=${id}`)
                .then(res => res.json())
                .then(res => {
                    if (res && res.url) {
                        activeHref = res.url;
                    }
                });
        } else {
            badgeTitle.textContent = 'Resolving...';
            // Fetch title & url dynamically from REST endpoint
            fetch(`/wp-json/custom/v1/permalink?id=${id}`)
                .then(res => {
                    if (!res.ok) throw new Error();
                    return res.json();
                })
                .then(res => {
                    if (res) {
                        if (res.title) {
                            badgeTitle.textContent = res.title;
                        } else {
                            badgeTitle.textContent = 'WordPress Page';
                        }
                        if (res.url) {
                            activeHref = res.url;
                        }
                    }
                })
                .catch(() => {
                    badgeTitle.textContent = 'WordPress Page';
                });
        }
    }

    function showSearchInput(url) {
        activeHrefId = 0;
        activeHref = url || '';
        
        // Hide badge
        badgeWrapper.style.display = 'none';
        badgeTitle.textContent = 'Linked Page';
        badgeId.textContent = 'ID: 0';
        
        // Show input
        if (hrefInput) {
            hrefInput.parentElement.style.display = 'block';
            hrefInput.value = url || '';
        }
    }

    if (badgeDisconnect) {
        badgeDisconnect.addEventListener('click', function (e) {
            e.preventDefault();
            showSearchInput('');
        });
    }

    // Helper to find associated A tag recursively up or down in DOM
    function findAssociatedAnchorDOM(el) {
        if (!el) return null;
        if (el.tagName.toLowerCase() === 'a') return el;
        
        const parentAnchor = el.closest('a');
        if (parentAnchor) return parentAnchor;
        
        const childAnchor = el.querySelector('a');
        if (childAnchor) return childAnchor;
        
        return null;
    }

    function showError(message) {
        const errorMsg = modal.querySelector('.align-quick-edit-error-msg');
        if (errorMsg) {
            errorMsg.textContent = message;
            errorMsg.style.display = 'block';
        }
    }

    function clearError() {
        const errorMsg = modal.querySelector('.align-quick-edit-error-msg');
        if (errorMsg) {
            errorMsg.textContent = '';
            errorMsg.style.display = 'none';
        }
    }

    // Whitelisted tags allowed to trigger the Quick Edit UI
    const ALLOWED_TAGS = ['a', 'span', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'em', 'b', 'i', 'figure', 'img', 'video', 'picture'];

    /**
     * Resolve the actual element that should be edited.
     * If the element has nested children with data-edit-id, redirect to the deepest one containing text.
     * If the resolved element contains no actual text (empty or only whitespace), returns null.
     */
    function getTargetElement(el) {
        if (!el) return null;

        // If the element itself is locked, block editing
        if (el.hasAttribute('data-quick-edit-locked')) {
            return null;
        }

        const tagName = el.tagName.toLowerCase();

        // If the element is not in the allowed tags list, bubble up to parent editables
        if (!ALLOWED_TAGS.includes(tagName)) {
            const parentEditable = el.parentElement ? el.parentElement.closest('[data-edit-id]') : null;
            if (parentEditable) {
                return getTargetElement(parentEditable);
            }
            return null;
        }

        // If the element has no text content (and is not a media element), try to find a parent editable
        const isMediaTag = ['figure', 'img', 'video', 'picture'].includes(tagName);
        if (!isMediaTag && el.textContent.trim() === '') {
            const parentEditable = el.parentElement ? el.parentElement.closest('[data-edit-id]') : null;
            if (parentEditable) {
                return getTargetElement(parentEditable);
            }
            return null;
        }

        const childEditables = el.querySelectorAll('[data-edit-id]');
        if (childEditables.length > 0) {
            // Search backwards to find the deepest nested child that has text content
            for (let i = childEditables.length - 1; i >= 0; i--) {
                const child = childEditables[i];
                if (child.hasAttribute('data-quick-edit-locked')) {
                    continue;
                }
                const childTagName = child.tagName.toLowerCase();
                if (ALLOWED_TAGS.includes(childTagName)) {
                    const isChildMedia = ['figure', 'img', 'video', 'picture'].includes(childTagName);
                    if (isChildMedia || child.textContent.trim() !== '') {
                        return child;
                    }
                }
            }
        }
        return el;
    }

    // 4. Position and show/hide floating button on block hover
    function showFloatingBtn(el) {
        // Redirect to the deepest editable child if nested, and validate text presence
        const targetEl = getTargetElement(el);
        
        if (!targetEl) {
            floatBtn.classList.remove('is-active');
            return;
        }
        
        activeElement = targetEl;
        activeEditId = targetEl.getAttribute('data-edit-id');

        const rect = targetEl.getBoundingClientRect();
        
        // Define button dimensions (26px x 26px)
        const btnWidth = 26;
        const btnHeight = 26;

        // Determine horizontal offset:
        // For narrow elements (less than 80px), place the button outside to the right (+6px offset)
        // For wider elements, we can overlap slightly (-8px offset) to keep it compact
        const isNarrow = rect.width < 80;
        const leftOffset = isNarrow ? 6 : -8;

        // Calculate position relative to document
        let top = rect.top + window.scrollY - 10;
        let left = rect.right + window.scrollX + leftOffset;

        // Prevent positioning above the viewport/document top
        if (top < window.scrollY + 4) {
            top = rect.top + window.scrollY + 4;
        }

        // Prevent positioning off-screen to the right
        const maxLeft = window.innerWidth + window.scrollX - btnWidth - 8;
        if (left > maxLeft) {
            left = maxLeft;
        }

        // Prevent positioning off-screen to the left
        if (left < window.scrollX + 8) {
            left = window.scrollX + 8;
        }

        floatBtn.style.top = `${top}px`;
        floatBtn.style.left = `${left}px`;
        floatBtn.classList.add('is-active');
    }

    function requestHideFloatingBtn() {
        if (modal && modal.classList.contains('is-open')) {
            return;
        }
        clearTimeout(hideTimeout);
        hideTimeout = setTimeout(() => {
            floatBtn.classList.remove('is-active');
            activeElement = null;
            activeEditId = null;
            currentHoveredEditable = null;
        }, 200);
    }

    let currentHoveredEditable = null;

    // Use global delegation to track hover states reliably across nested elements
    document.addEventListener('mouseover', function (e) {
        const editable = e.target.closest('[data-edit-id]');
        const isOverFloatBtn = e.target.closest('.align-quick-edit-float-btn');

        if (editable) {
            clearTimeout(hideTimeout);
            if (currentHoveredEditable !== editable) {
                currentHoveredEditable = editable;
                showFloatingBtn(editable);
            }
        } else if (isOverFloatBtn) {
            clearTimeout(hideTimeout);
        } else {
            if (currentHoveredEditable !== null) {
                requestHideFloatingBtn();
            }
        }
    });

    // Handle mouse leaving the window
    document.addEventListener('mouseleave', function () {
        if (currentHoveredEditable !== null) {
            requestHideFloatingBtn();
        }
    });

    // 5. Open modal & fetch content
    floatBtn.addEventListener('click', function () {
        if (!activeEditId) return;

        // Show modal and loading state
        modal.classList.add('is-open');
        loadingOverlay.classList.add('is-visible');
        clearError();
        textarea.value = '';
        if (hrefInput) hrefInput.value = '';
        if (hrefGroup) hrefGroup.style.display = 'none';
        searchResults.innerHTML = '';
        searchResults.classList.remove('is-visible');
        activeLinkEditId = null;
        activeHrefId = 0;

        // Fetch original content from WP REST API
        const currentPostId = alignQuickEditData.currentPostId || 0;
        fetch(`${alignQuickEditData.restUrl}?edit_id=${activeEditId}&current_post_id=${currentPostId}`, {
            headers: {
                'X-WP-Nonce': alignQuickEditData.nonce
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                textarea.value = data.content || '';
                
                // Show/hide href field based on associated link type
                if (data.hasLink && hrefGroup && hrefInput) {
                    hrefGroup.style.display = 'block';
                    activeLinkEditId = data.linkEditId || '';
                    const loadedHrefId = parseInt(data.hrefId || 0);
                    if (loadedHrefId > 0) {
                        showDynamicBadge(loadedHrefId);
                    } else {
                        showSearchInput(data.href || '');
                    }
                } else if (hrefGroup) {
                    hrefGroup.style.display = 'none';
                }
            } else {
                showError('Failed to load block content.');
            }
        })
        .catch(err => {
            console.error('Fetch error:', err);
            showError('Connection error while loading block content.');
        })
        .finally(() => {
            loadingOverlay.classList.remove('is-visible');
            textarea.focus();
        });
    });

    // Link autocomplete search on user typing (debounce search)
    if (hrefInput) {
        hrefInput.addEventListener('input', function () {
            const query = hrefInput.value.trim();
            
            // Whenever user types custom text, reset selected link ID to 0
            activeHrefId = 0;
            activeHref = hrefInput.value;
            
            if (query.length < 2) {
                searchResults.classList.remove('is-visible');
                searchResults.innerHTML = '';
                return;
            }
            
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(function () {
                searchSpinner.style.display = 'block';
                
                // Fetch dynamic page/post suggestion from standard WP Search API
                fetch(`/wp-json/wp/v2/search?search=${encodeURIComponent(query)}`, {
                    headers: {
                        'X-WP-Nonce': alignQuickEditData.nonce
                    }
                })
                .then(res => {
                    if (!res.ok) throw new Error('Search failed');
                    return res.json();
                })
                .then(data => {
                    searchResults.innerHTML = '';
                    if (Array.isArray(data) && data.length > 0) {
                        data.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'align-quick-edit-search-item';
                            div.setAttribute('data-id', item.id);
                            div.setAttribute('data-url', item.url);
                            
                            // Strip HTML tags from title
                            const cleanTitle = item.title ? item.title.replace(/<\/?[^>]+(>|$)/g, "") : '';
                            
                            div.innerHTML = `
                                <span class="item-title">${cleanTitle}</span>
                                <span class="item-type">${item.subtype || item.type}</span>
                            `;
                            searchResults.appendChild(div);
                        });
                        searchResults.classList.add('is-visible');
                    } else {
                        searchResults.classList.remove('is-visible');
                    }
                })
                .catch(err => {
                    console.error('WP REST Search error:', err);
                })
                .finally(() => {
                    searchSpinner.style.display = 'none';
                });
            }, 300);
        });

        // Hide results dropdown on click outside
        document.addEventListener('click', function (e) {
            if (!hrefGroup.contains(e.target)) {
                searchResults.classList.remove('is-visible');
            }
        });

        // Show search results again on input focus if there is content
        hrefInput.addEventListener('focus', function () {
            if (searchResults.children.length > 0) {
                searchResults.classList.add('is-visible');
            }
        });

        // Select search item from results
        searchResults.addEventListener('click', function (e) {
            const item = e.target.closest('.align-quick-edit-search-item');
            if (!item) return;
            
            const id = parseInt(item.getAttribute('data-id') || 0);
            const url = item.getAttribute('data-url');
            const title = item.querySelector('.item-title').textContent;
            
            if (id > 0) {
                showDynamicBadge(id, title);
            } else {
                let relativeUrl = url;
                try {
                    const urlObj = new URL(url);
                    if (urlObj.origin === window.location.origin) {
                        relativeUrl = urlObj.pathname + urlObj.search + urlObj.hash;
                    }
                } catch (err) {
                    relativeUrl = url.replace(window.location.origin, '');
                }
                showSearchInput(relativeUrl);
            }
            
            searchResults.classList.remove('is-visible');
            searchResults.innerHTML = '';
        });
    }

    // 6. Save content
    saveBtn.addEventListener('click', function () {
        if (!activeEditId || !activeElement) return;

        const newContent = textarea.value;
        const newHref = activeHref;
        loadingOverlay.classList.add('is-visible');
        clearError();

        fetch(alignQuickEditData.restUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': alignQuickEditData.nonce
            },
            body: JSON.stringify({
                edit_id: activeEditId,
                link_edit_id: activeLinkEditId,
                content: newContent,
                href: newHref,
                href_id: activeHrefId,
                current_post_id: alignQuickEditData.currentPostId || 0
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to update content');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Apply immediately to Frontend DOM
                updateElementDOM(activeElement, newContent, newHref);
                
                // Cache element reference for CSS flash highlight effect
                const targetElement = activeElement;
                if (targetElement) {
                    targetElement.classList.add('align-quick-edit-highlighted');
                    setTimeout(() => {
                        targetElement.classList.remove('align-quick-edit-highlighted');
                    }, 2000);
                }

                // Close modal
                closeModal();
            } else {
                showError('Update failed.');
            }
        })
        .catch(err => {
            console.error('Save error:', err);
            showError('Connection error while saving content.');
        })
        .finally(() => {
            loadingOverlay.classList.remove('is-visible');
        });
    });

    // Helper to update the text content of the DOM element safely
    function updateElementDOM(el, newText, newHref) {
        // Update associated anchor DOM element if present
        const associatedAnchor = findAssociatedAnchorDOM(el);
        if (associatedAnchor && typeof newHref === 'string') {
            associatedAnchor.setAttribute('href', newHref);
        }

        // If element has no child tags, update innerHTML directly
        if (el.children.length === 0) {
            el.innerHTML = newText;
            return;
        }

        // If there are child elements, recursively find the deepest child containing text
        // to preserve wrapper/container HTML elements (like <a> links, <i> icons, etc.)
        let deepestWithText = el;
        while (deepestWithText.children.length > 0) {
            let foundChild = null;
            for (let i = 0; i < deepestWithText.children.length; i++) {
                if (deepestWithText.children[i].textContent.trim() !== '') {
                    foundChild = deepestWithText.children[i];
                    break;
                }
            }
            if (foundChild) {
                deepestWithText = foundChild;
            } else {
                break;
            }
        }

        // Safely update the content of the deepest child element
        deepestWithText.innerHTML = newText;
    }

    // 7. Modal utility helpers
    function closeModal() {
        modal.classList.remove('is-open');
        loadingOverlay.classList.remove('is-visible');
        textarea.value = '';
        if (hrefInput) {
            hrefInput.value = '';
            hrefInput.parentElement.style.display = 'block';
        }
        badgeWrapper.style.display = 'none';
        badgeTitle.textContent = 'Linked Page';
        badgeId.textContent = 'ID: 0';
        searchResults.innerHTML = '';
        searchResults.classList.remove('is-visible');
        
        // Reset active elements and hide float button
        floatBtn.classList.remove('is-active');
        activeElement = null;
        activeEditId = null;
        currentHoveredEditable = null;
    }

    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);

    // Escape key closes modal
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });
});
