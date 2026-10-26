/**
 * Align Theme Builder - Reusable Block Content Overwrite Link Picker
 *
 * Implements debounced Ajax search and Post ID locking for Content Overwrite
 * link fields in both Live Canvas and REST API inspectors.
 *
 * @package Align
 */

window.AlignBuilderModules = window.AlignBuilderModules || {};
window.AlignBuilderModules.WpReuseBlockOverwrite = window.AlignBuilderModules.WpReuseBlockOverwrite || {};

(function() {
    'use strict';

    const OverwriteLinkPicker = {
        debounceTimer: null,

        toRelativeUrl: function(url) {
            if (window.LinkInput && typeof window.LinkInput.toRelativeUrl === 'function') {
                return window.LinkInput.toRelativeUrl(url);
            }
            if (!url || typeof url !== 'string') return '/';
            const trimmed = url.trim();
            if (!trimmed) return '/';
            if (trimmed.startsWith('#') || trimmed.startsWith('mailto:') || trimmed.startsWith('tel:') || trimmed.startsWith('javascript:')) {
                return trimmed;
            }
            if (trimmed.startsWith('/#')) {
                return trimmed.substring(1);
            }
            try {
                const currentOrigin = window.location.origin;
                const parsed = new URL(trimmed, currentOrigin);
                // External link: preserve full URL if origin differs from current site origin
                if (parsed.origin !== currentOrigin) {
                    return trimmed;
                }
                return (parsed.pathname || '/') + (parsed.search || '') + (parsed.hash || '');
            } catch (e) {
                return trimmed;
            }
        },

        escapeAttr: function(str) {
            if (str === null || str === undefined) return '';
            return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#039;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        },

        renderLinkPickerHtml: function(options) {
            options = options || {};
            const url = options.url || '';
            const linkId = options.linkId ? String(options.linkId) : '';
            const linkTitle = options.linkTitle || (linkId ? ('Post #' + linkId) : '');
            const isLocked = Boolean(linkId && linkId !== '0');

            let html = '<div class="vvveb-link-input-wrapper position-relative overwrite-link-picker-wrap" style="width: 100%;">';
            // Unlocked container (Search input + icon + results)
            html += '<div class="link-unlocked-container" style="' + (isLocked ? 'display: none;' : 'display: block;') + '">';
            html += '  <div class="input-group input-group-sm link-input-group">';
            html += '    <input type="text" class="form-control form-control-sm link-url-input overwrite-link-href" value="' + this.escapeAttr(url) + '" placeholder="Type URL or search post..." autocomplete="off" style="font-size: 11px;" />';
            html += '    <span class="input-group-text link-search-icon" title="Search posts"><i class="la la-search"></i></span>';
            html += '  </div>';
            html += '  <div class="link-search-results dropdown-menu shadow-sm p-0 position-absolute" style="display: none; left: 0; right: 0; width: 100%; z-index: 1050;"></div>';
            html += '</div>';

            // Locked container (Card view with Post ID badge and title)
            html += '<div class="link-locked-container" style="' + (isLocked ? 'display: block;' : 'display: none;') + '">';
            html += '  <div class="link-locked-card d-flex align-items-center justify-content-between">';
            html += '    <div class="d-flex align-items-center gap-1 min-w-0 flex-grow-1 text-truncate me-1">';
            html += '      <span class="badge link-locked-id-badge flex-shrink-0" title="Post ID #' + this.escapeAttr(linkId) + '">#<span class="locked-id-value">' + this.escapeAttr(linkId) + '</span></span>';
            html += '      <span class="fw-medium text-truncate locked-title item-title" title="' + this.escapeAttr(linkTitle) + '">' + this.escapeAttr(linkTitle) + '</span>';
            html += '    </div>';
            html += '    <button type="button" class="btn btn-link btn-disconnect p-0 flex-shrink-0" title="Disconnect link" aria-label="Disconnect"><i class="la la-unlink"></i></button>';
            html += '  </div>';
            html += '</div>';
            html += '</div>';

            return html;
        },

        bindLinkPicker: function(wrapper, options) {
            if (!wrapper) return;
            const self = this;
            options = options || {};

            const unlockedContainer = wrapper.querySelector('.link-unlocked-container');
            const lockedContainer = wrapper.querySelector('.link-locked-container');
            const inputEl = wrapper.querySelector('input.link-url-input');
            const resultsContainer = wrapper.querySelector('.link-search-results');
            const btnDisconnect = wrapper.querySelector('.btn-disconnect');
            const lockedTitleEl = wrapper.querySelector('.locked-title');
            const lockedIdEl = wrapper.querySelector('.locked-id-value');
            const lockedBadgeEl = wrapper.querySelector('.link-locked-id-badge');

            let currentLinkId = options.linkId ? String(options.linkId) : '';
            let currentLinkTitle = options.linkTitle || '';

            const performSearch = function(keyword) {
                if (!resultsContainer) return;
                const restNonce = (window.VvvebWpConfig && window.VvvebWpConfig.restNonce) ? window.VvvebWpConfig.restNonce : '';
                let searchUrl = '';

                if (window.VvvebWpConfig && window.VvvebWpConfig.restSearchLinksUrl) {
                    const base = window.VvvebWpConfig.restSearchLinksUrl;
                    searchUrl = base + (base.indexOf('?') > -1 ? '&' : '?') + 'search=' + encodeURIComponent(keyword);
                } else {
                    const restBase = (window.VvvebWpConfig && window.VvvebWpConfig.restUrl) ? window.VvvebWpConfig.restUrl : '/wp-json/align-builder/v1/';
                    searchUrl = restBase + (restBase.endsWith('/') ? '' : '/') + 'search-links?search=' + encodeURIComponent(keyword);
                    if (restNonce) searchUrl += '&_wpnonce=' + encodeURIComponent(restNonce);
                }

                resultsContainer.style.display = 'block';
                resultsContainer.innerHTML = '<div class="p-2 text-muted small text-center link-search-loading"><i class="la la-spinner la-spin me-1"></i>Searching posts...</div>';

                fetch(searchUrl, {
                    headers: { 'X-WP-Nonce': restNonce },
                    credentials: 'same-origin'
                })
                .then(function(res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(function(items) {
                    if (!Array.isArray(items) || items.length === 0) {
                        resultsContainer.innerHTML = '<div class="p-2 text-muted small text-center link-search-empty"><i class="la la-info-circle me-1 opacity-75"></i>No posts found</div>';
                        return;
                    }

                    resultsContainer.innerHTML = '';
                    items.forEach(function(item) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'dropdown-item link-search-item';

                        const itemTitle = item.title || 'Untitled';
                        const itemUrl = self.toRelativeUrl(item.url || '');
                        const postId = String(item.id || '');
                        const typeLabel = item.type_label || item.type || 'Post';

                        btn.innerHTML = '<div class="link-search-row-top" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">' +
                            '<div class="link-search-item-title text-truncate" title="' + self.escapeAttr(itemTitle) + '" style="font-size: 11.5px; font-weight: 600; max-width: 170px;">' + self.escapeAttr(itemTitle) + '</div>' +
                            '<div class="link-search-badges" style="display: flex; gap: 4px; flex-shrink: 0;">' +
                            '<span class="badge link-search-type-badge" style="font-size: 9px;">' + self.escapeAttr(typeLabel) + '</span>' +
                            '<span class="badge link-search-id-badge" style="font-size: 9px;">#' + self.escapeAttr(postId) + '</span></div></div>' +
                            '<div class="link-search-row-bottom" style="display: flex; align-items: center; gap: 4px; font-size: 10px; color: #a0aec0;">' +
                            '<i class="la la-link link-search-url-icon"></i><div class="link-search-item-url text-truncate" title="' + self.escapeAttr(itemUrl) + '">' + self.escapeAttr(itemUrl) + '</div></div>';

                        btn.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();

                            currentLinkId = postId;
                            currentLinkTitle = itemTitle;

                            self.setLinkState(wrapper, { href: itemUrl, link_id: postId, link_title: itemTitle });
                            if (resultsContainer) {
                                resultsContainer.style.display = 'none';
                                resultsContainer.innerHTML = '';
                            }

                            if (typeof options.onChange === 'function') {
                                options.onChange({ href: itemUrl, link_id: postId, link_title: itemTitle });
                            }
                        });

                        resultsContainer.appendChild(btn);
                    });
                })
                .catch(function(err) {
                    console.error('[Align ReusableBlock LinkPicker] Search error:', err);
                    resultsContainer.innerHTML = '<div class="p-2 text-danger small text-center link-search-error"><i class="la la-exclamation-circle me-1"></i>Error searching links</div>';
                });
            };

            if (inputEl) {
                inputEl.addEventListener('input', function() {
                    const searchVal = this.value ? this.value.trim() : '';
                    clearTimeout(self.debounceTimer);

                    if (!searchVal || searchVal.startsWith('http://') || searchVal.startsWith('https://') || searchVal.startsWith('#') || searchVal.startsWith('mailto:') || searchVal.startsWith('tel:')) {
                        if (resultsContainer) {
                            resultsContainer.style.display = 'none';
                            resultsContainer.innerHTML = '';
                        }
                    } else {
                        self.debounceTimer = setTimeout(function() {
                            performSearch(searchVal);
                        }, 300);
                    }

                    // Manual URL entry clears locked post ID
                    currentLinkId = '';
                    currentLinkTitle = '';
                    if (typeof options.onChange === 'function') {
                        options.onChange({ href: this.value, link_id: '', link_title: '' });
                    }
                });

                inputEl.addEventListener('change', function() {
                    if (typeof options.onChange === 'function') {
                        options.onChange({ href: this.value, link_id: currentLinkId, link_title: currentLinkTitle });
                    }
                });
            }

            if (btnDisconnect) {
                btnDisconnect.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const currentHref = (inputEl ? inputEl.value : '') || '';
                    currentLinkId = '';
                    currentLinkTitle = '';

                    self.setLinkState(wrapper, { href: currentHref, link_id: '', link_title: '' });
                    if (inputEl) inputEl.focus();

                    if (typeof options.onChange === 'function') {
                        options.onChange({ href: currentHref, link_id: '', link_title: '' });
                    }
                });
            }
        },

        setLinkState: function(wrapper, state) {
            if (!wrapper) return;
            state = state || {};
            const linkId = state.link_id ? String(state.link_id) : (state.linkId ? String(state.linkId) : '');
            const linkTitle = state.link_title || state.linkTitle || (linkId ? ('Post #' + linkId) : '');
            const href = state.href || state.value || '';
            const isLocked = Boolean(linkId && linkId !== '0');

            const unlockedContainer = wrapper.querySelector('.link-unlocked-container');
            const lockedContainer = wrapper.querySelector('.link-locked-container');
            const inputEl = wrapper.querySelector('input.link-url-input');
            const lockedTitleEl = wrapper.querySelector('.locked-title');
            const lockedIdEl = wrapper.querySelector('.locked-id-value');
            const lockedBadgeEl = wrapper.querySelector('.link-locked-id-badge');

            if (inputEl) inputEl.value = href;
            if (lockedTitleEl) {
                lockedTitleEl.textContent = linkTitle;
                lockedTitleEl.setAttribute('title', linkTitle);
            }
            if (lockedIdEl) lockedIdEl.textContent = linkId;
            if (lockedBadgeEl) lockedBadgeEl.setAttribute('title', 'Post ID #' + linkId);

            if (unlockedContainer) unlockedContainer.style.display = isLocked ? 'none' : 'block';
            if (lockedContainer) lockedContainer.style.display = isLocked ? 'block' : 'none';

            const resultsContainer = wrapper.querySelector('.link-search-results');
            if (resultsContainer) {
                resultsContainer.style.display = 'none';
                resultsContainer.innerHTML = '';
            }
        }
    };

    Object.assign(window.AlignBuilderModules.WpReuseBlockOverwrite, OverwriteLinkPicker);
})();
