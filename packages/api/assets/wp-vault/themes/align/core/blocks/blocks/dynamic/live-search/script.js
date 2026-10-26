(function () {
    'use strict';

    /**
     * Debounce utility — delays function execution.
     * @param {Function} fn - Function to debounce.
     * @param {number} delay - Delay in milliseconds.
     * @returns {Function}
     */
    function debounce(fn, delay) {
        var timer;
        return function () {
            var args = arguments;
            var ctx  = this;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(ctx, args);
            }, delay);
        };
    }

    /**
     * Initialize a single Live Search widget.
     * @param {HTMLElement} container - The .align-live-search wrapper element.
     */
    function initLiveSearch(container) {
        var input      = container.querySelector('.align-ls-input');
        var dropdown   = container.querySelector('.align-ls-dropdown');
        var form       = container.querySelector('.align-ls-form');
        var ajaxUrl    = container.dataset.ajaxUrl || '';
        var restUrl    = container.dataset.restUrl || '';
        var nonce      = container.dataset.nonce   || '';
        var searchPage = container.dataset.searchPage || '';
        var modeToggle = container.querySelector('.align-ls-mode-toggle');
        var modeText   = modeToggle ? modeToggle.querySelector('.align-ls-mode-text') : null;
        var attributes = {};

        try {
            attributes = JSON.parse(container.dataset.attributes || '{}');
        } catch (e) {
            attributes = {};
        }

        if (!input || !dropdown || (!ajaxUrl && !restUrl)) return;

        var currentRequest = null;
        var lastKeyword    = '';
        var localCache     = {};
        
        var enableLive  = container.dataset.enableLive !== 'false' && attributes.enableLiveSearch !== false;
        var defaultMode = enableLive ? (container.dataset.defaultMode || 'live') : 'normal';
        var currentMode = enableLive ? (localStorage.getItem('align_search_mode') || defaultMode) : 'normal';

        /**
         * Update mode toggle button UI classes and text.
         */
        function updateToggleUI() {
            if (!modeToggle || !enableLive) return;
            if (currentMode === 'live') {
                modeToggle.classList.add('is-live');
                if (modeText) modeText.textContent = 'Live';
            } else {
                modeToggle.classList.remove('is-live');
                if (modeText) modeText.textContent = 'Normal';
            }
        }

        updateToggleUI();

        /**
         * Open the dropdown and make it visible.
         */
        function openDropdown() {
            dropdown.classList.add('is-open');
        }

        /**
         * Close the dropdown and hide it.
         */
        function closeDropdown() {
            dropdown.classList.remove('is-open', 'is-loading');
        }

        /**
         * Set loading state on the dropdown.
         */
        function setLoading() {
            dropdown.classList.add('is-open', 'is-loading');
            dropdown.innerHTML = '<div class="align-ls-loading"><span class="align-ls-spinner"></span></div>';
        }

        var catWrap    = container.querySelector('.align-ls-category-wrap');
        var catInput   = container.querySelector('.align-ls-category-input');
        var catToggle  = container.querySelector('.align-ls-category-toggle');
        var catCurrent = container.querySelector('.align-ls-category-current');
        var catMenu    = container.querySelector('.align-ls-category-menu');
        var catItems   = container.querySelectorAll('.align-ls-category-item');
        var catSelect  = container.querySelector('.align-ls-category-select');

        function getSelectedCategoryId() {
            if (catInput) return catInput.value.trim();
            if (catSelect) return catSelect.value.trim();
            return '';
        }

        /**
         * Fetch search results from the REST API or AJAX endpoint and inject HTML.
         * @param {string} keyword - Search keyword.
         */
        function fetchResults(keyword) {
            if (keyword.length < 2) {
                closeDropdown();
                return;
            }

            var selectedCatId = getSelectedCategoryId();
            var cacheKey = keyword + '__cat_' + selectedCatId;

            // Serve from client-side memory cache if available
            if (localCache[cacheKey]) {
                dropdown.innerHTML = localCache[cacheKey];
                dropdown.classList.remove('is-loading');
                openDropdown();
                return;
            }

            // Skip fetching if current mode is normal search
            if (currentMode === 'normal') {
                return;
            }

            // Abort previous request if still running
            if (currentRequest && typeof currentRequest.abort === 'function') {
                currentRequest.abort();
            }

            setLoading();

            function sendRequest(isRest) {
                var targetUrl = isRest ? restUrl : ajaxUrl;
                xhr = new XMLHttpRequest();
                currentRequest = xhr;
                xhr.open('POST', targetUrl, true);

                xhr.onreadystatechange = function () {
                    if (xhr.readyState !== 4) return;
                    currentRequest = null;

                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success && response.data) {
                                localCache[cacheKey] = response.data;
                                dropdown.innerHTML = response.data;
                                dropdown.classList.remove('is-loading');
                                openDropdown();
                            } else {
                                var noResultsHtml = '<div class="align-ls-no-results">No results found.</div>';
                                localCache[cacheKey] = noResultsHtml;
                                dropdown.innerHTML = noResultsHtml;
                                dropdown.classList.remove('is-loading');
                                openDropdown();
                            }
                        } catch (e) {
                            closeDropdown();
                        }
                    } else if (isRest && ajaxUrl) {
                        // Fall back to admin-ajax.php if REST API fails
                        sendRequest(false);
                    } else {
                        closeDropdown();
                    }
                };

                if (isRest) {
                    xhr.setRequestHeader('Content-Type', 'application/json; charset=UTF-8');
                    if (nonce) xhr.setRequestHeader('X-Align-Nonce', nonce);
                    var payload = {
                        keyword: keyword,
                        selectedCategoryId: selectedCatId,
                        attributes: attributes
                    };
                    xhr.send(JSON.stringify(payload));
                } else {
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
                    var body = 'action=align_live_search'
                        + '&nonce='      + encodeURIComponent(nonce)
                        + '&keyword='   + encodeURIComponent(keyword)
                        + '&selectedCategoryId=' + encodeURIComponent(selectedCatId)
                        + '&attributes=' + encodeURIComponent(JSON.stringify(attributes));
                    xhr.send(body);
                }
            }

            var xhr;
            sendRequest(!!restUrl);
        }

        var debouncedFetch = debounce(fetchResults, 300);

        // Prevent Lenis smooth scroll from blocking nested scroll in category menu & dropdown
        [catMenu, dropdown].forEach(function (scrollEl) {
            if (!scrollEl) return;
            scrollEl.setAttribute('data-lenis-prevent', 'true');
            scrollEl.setAttribute('data-lenis-prevent-wheel', 'true');
            scrollEl.setAttribute('data-lenis-prevent-touch', 'true');
            scrollEl.addEventListener('wheel', function (e) {
                e.stopPropagation();
                if (scrollEl.scrollHeight > scrollEl.clientHeight) {
                    scrollEl.scrollTop += e.deltaY;
                }
            }, { passive: false });
            scrollEl.addEventListener('touchmove', function (e) {
                e.stopPropagation();
            }, { passive: true });
        });

        // Bind Custom HTML Category Dropdown events
        if (catWrap && catToggle && catMenu) {
            catToggle.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var isOpen = catWrap.classList.contains('is-open');
                if (isOpen) {
                    catWrap.classList.remove('is-open');
                    catToggle.setAttribute('aria-expanded', 'false');
                } else {
                    catWrap.classList.add('is-open');
                    catToggle.setAttribute('aria-expanded', 'true');
                }
            });

            catItems.forEach(function (item) {
                item.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var val = item.getAttribute('data-value') || '';
                    var nameEl = item.querySelector('.align-ls-category-name');
                    var name = nameEl ? nameEl.textContent.trim() : item.textContent.trim();

                    if (catInput) {
                        catInput.value = val;
                    }
                    if (catCurrent) {
                        catCurrent.textContent = name;
                    }

                    catItems.forEach(function (i) {
                        i.classList.remove('is-selected');
                        i.setAttribute('aria-selected', 'false');
                    });
                    item.classList.add('is-selected');
                    item.setAttribute('aria-selected', 'true');

                    catWrap.classList.remove('is-open');
                    catToggle.setAttribute('aria-expanded', 'false');

                    // Trigger live search with new category
                    lastKeyword = '';
                    var inputVal = input.value.trim();
                    if (inputVal.length >= 2 && currentMode === 'live') {
                        debouncedFetch(inputVal);
                    }
                });
            });

            // Close category menu on click outside
            document.addEventListener('click', function (e) {
                if (!catWrap.contains(e.target)) {
                    catWrap.classList.remove('is-open');
                    catToggle.setAttribute('aria-expanded', 'false');
                }
            });
        }

        // Listen for legacy category selection change fallback
        if (catSelect) {
            catSelect.addEventListener('change', function () {
                lastKeyword = '';
                var val = input.value.trim();
                if (val.length >= 2 && currentMode === 'live') {
                    debouncedFetch(val);
                }
            });
        }

        // Bind click event to mode toggle button
        if (modeToggle) {
            modeToggle.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                currentMode = (currentMode === 'live') ? 'normal' : 'live';
                localStorage.setItem('align_search_mode', currentMode);
                updateToggleUI();
                if (currentMode === 'normal') {
                    closeDropdown();
                } else {
                    // Trigger search immediately if keyword is valid
                    var val = input.value.trim();
                    if (val.length >= 2) {
                        debouncedFetch(val);
                    }
                }
            });
        }

        // Listen for input changes
        input.addEventListener('input', function () {
            if (currentMode === 'normal') {
                closeDropdown();
                return;
            }
            debouncedFetch(input.value.trim());
        });

        // Show cached results on focus if input has value
        input.addEventListener('focus', function () {
            if (currentMode === 'normal') return;
            if (input.value.trim().length >= 2 && dropdown.innerHTML.trim() !== '') {
                openDropdown();
            }
        });

        // Handle Enter key — redirect to search page
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeDropdown();
                input.blur();
                return;
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                var keyword = input.value.trim();
                if (keyword) {
                    var queryParams = 's=' + encodeURIComponent(keyword);
                    var catId = getSelectedCategoryId();
                    if (catId) {
                        var catParam = catInput ? (catInput.name || 'cat') : (catSelect ? (catSelect.name || 'cat') : 'cat');
                        queryParams += '&' + encodeURIComponent(catParam) + '=' + encodeURIComponent(catId);
                    }
                    var url = searchPage
                        ? searchPage + (searchPage.indexOf('?') > -1 ? '&' : '?') + queryParams
                        : '/?' + queryParams;
                    window.location.href = url;
                }
            }
        });

        // Close dropdown on outside click
        document.addEventListener('mousedown', function (e) {
            if (!container.contains(e.target)) {
                closeDropdown();
            }
        });

        // Mark container as initialized to prevent double-binding
        container.setAttribute('data-initialized', 'true');
    }

    // Expose AlignLiveSearch global API for builder and dynamic iframe previews
    window.AlignLiveSearch = {
        init: initLiveSearch,
        bootstrap: function (root) {
            var context = root || document;
            var containers = context.querySelectorAll ? context.querySelectorAll('.align-live-search:not([data-initialized="true"])') : [];
            containers.forEach(function (container) {
                initLiveSearch(container);
            });
        }
    };

    /**
     * Bootstrap: find all Live Search blocks on the page and initialize them.
     */
    function bootstrap() {
        if (window.AlignLiveSearch) {
            window.AlignLiveSearch.bootstrap(document);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
    } else {
        bootstrap();
    }

}());


