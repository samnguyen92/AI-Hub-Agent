/**
 * Align Theme Builder - Unified Topbar & Toolbar Tooltips Module
 *
 * Manages Bootstrap tooltips across top-panel and selection actions toolbar.
 * Eliminates browser native tooltips and prevents dangling tooltip artifacts
 * across selection changes, clicks, canvas scrolling, and DOM mutations.
 *
 * @package Align
 */

window.AlignBuilderModules = window.AlignBuilderModules || {};

window.AlignBuilderModules.WpToolbarTooltips = (function() {
    'use strict';

    /**
     * Primary selector for toolbar buttons, badge, and pills with titles
     */
    const TARGET_SELECTOR = '#select-actions a, #select-actions button, #select-actions #top-select-element-badge, #top-panel button[title], #top-panel button[data-bs-original-title], #top-panel a[title], #top-panel a[data-bs-original-title], #top-panel .top-panel-shortcode-pill, #top-panel .top-panel-dynamic-pill';

    /**
     * Standard Bootstrap Tooltip configuration for Align Builder toolbar
     */
    const TOOLTIP_CONFIG = {
        trigger: 'hover',
        placement: 'bottom',
        container: 'body',
        animation: true,
        delay: { show: 100, hide: 50 },
        customClass: 'align-toolbar-tooltip'
    };

    let observer = null;
    let isInitialized = false;

    // Track listener references for lifecycle management
    let topPanelEl = null;
    let boundTopPanelClick = null;
    let boundSelectNode = null;
    let boundIframeLoaded = null;
    let boundWindowScroll = null;
    let boundKeyDown = null;

    /**
     * Initialize or update a single element tooltip
     *
     * Copies native title to data-bs-original-title, strips title attribute
     * to eliminate browser native tooltip, and binds Bootstrap tooltip.
     *
     * @param {Element} el Target DOM element
     */
    function initElement(el) {
        if (!el || !(el instanceof Element)) return;

        let title = el.getAttribute('title');
        if (title) {
            el.setAttribute('data-bs-original-title', title);
            el.removeAttribute('title');
        } else {
            title = el.getAttribute('data-bs-original-title');
        }

        if (!title || !title.trim()) {
            return;
        }

        if (!window.bootstrap || !window.bootstrap.Tooltip) {
            return;
        }

        try {
            let tip = window.bootstrap.Tooltip.getInstance(el);
            const hasCustomClass = tip && tip._config && typeof tip._config.customClass === 'string' && tip._config.customClass.includes('align-toolbar-tooltip');
            if (tip && !hasCustomClass) {
                try {
                    tip.dispose();
                } catch (disposeErr) {}
                tip = null;
            }
            if (!tip) {
                new window.bootstrap.Tooltip(el, TOOLTIP_CONFIG);
            } else if (typeof tip.setContent === 'function') {
                tip.setContent({ '.tooltip-inner': title });
            }
        } catch (err) {
            console.warn('[Align Tooltips] Failed to initialize tooltip:', el, err);
        }
    }

    /**
     * Update tooltip title text on a target element
     *
     * @param {Element} el Target DOM element
     * @param {string} title New tooltip title text
     */
    function updateTitle(el, title) {
        if (!el || !(el instanceof Element)) return;

        if (typeof title !== 'string' || !title.trim()) {
            hide(el);
            el.removeAttribute('title');
            el.removeAttribute('data-bs-original-title');
            return;
        }

        el.setAttribute('data-bs-original-title', title);
        if (el.hasAttribute('title')) {
            el.removeAttribute('title');
        }

        if (!window.bootstrap || !window.bootstrap.Tooltip) {
            return;
        }

        try {
            let tip = window.bootstrap.Tooltip.getInstance(el);
            const hasCustomClass = tip && tip._config && typeof tip._config.customClass === 'string' && tip._config.customClass.includes('align-toolbar-tooltip');
            if (tip && !hasCustomClass) {
                try {
                    tip.dispose();
                } catch (disposeErr) {}
                tip = null;
            }
            if (!tip) {
                new window.bootstrap.Tooltip(el, TOOLTIP_CONFIG);
            } else if (typeof tip.setContent === 'function') {
                tip.setContent({ '.tooltip-inner': title });
            }
        } catch (err) {
            console.warn('[Align Tooltips] Failed to update tooltip title:', el, err);
        }
    }

    /**
     * Hide tooltip on a specific element
     *
     * @param {Element} el Target DOM element
     */
    function hide(el) {
        if (!el || !(el instanceof Element)) return;

        try {
            const tip = window.bootstrap?.Tooltip?.getInstance?.(el);
            if (tip && typeof tip.hide === 'function') {
                tip.hide();
            }
        } catch (err) {
            console.warn('[Align Tooltips] Failed to hide tooltip:', el, err);
        }
    }

    /**
     * Hide all active tooltips across the builder toolbar
     */
    function hideAll() {
        try {
            const elements = document.querySelectorAll(TARGET_SELECTOR);
            elements.forEach(function(el) {
                const tip = window.bootstrap?.Tooltip?.getInstance?.(el);
                if (tip && typeof tip.hide === 'function') {
                    tip.hide();
                }
            });

            // Defensive cleanup: hide and detach any lingering shown tooltip bubbles in body
            const stuckTooltips = document.querySelectorAll('.tooltip.align-toolbar-tooltip.show');
            stuckTooltips.forEach(function(node) {
                node.classList.remove('show');
                if (node.parentNode) {
                    node.parentNode.removeChild(node);
                }
            });
        } catch (err) {
            console.warn('[Align Tooltips] Error in hideAll:', err);
        }
    }

    /**
     * Bind click, scroll, and keydown events to canvas iframe to dismiss open tooltips
     */
    function bindIframeListeners() {
        try {
            const frameDoc = window.FrameDocument || (window.Vvveb?.Builder?.iframe ? window.Vvveb.Builder.iframe.contentDocument : null);
            const frameWin = window.FrameWindow || (window.Vvveb?.Builder?.iframe ? window.Vvveb.Builder.iframe.contentWindow : null);

            if (frameDoc && !frameDoc._alignTooltipsBound) {
                frameDoc._alignTooltipsBound = true;
                frameDoc.addEventListener('click', hideAll, { passive: true });
                frameDoc.addEventListener('scroll', hideAll, { passive: true });
                frameDoc.addEventListener('keydown', handleKeyDown, { passive: true });
            }

            if (frameWin && !frameWin._alignTooltipsBound) {
                frameWin._alignTooltipsBound = true;
                frameWin.addEventListener('scroll', hideAll, { passive: true });
            }
        } catch (err) {
            console.warn('[Align Tooltips] Unable to bind iframe listeners:', err);
        }
    }

    /**
     * Capture click events on #top-panel to dismiss tooltip and blur active button
     *
     * @param {MouseEvent} event Click event
     */
    function handleTopPanelClick(event) {
        const btn = event.target.closest('button, a, .element-badge, .top-panel-shortcode-pill, .top-panel-dynamic-pill');
        if (btn) {
            hide(btn);
            if (typeof btn.blur === 'function') {
                btn.blur();
            }
        }
    }

    /**
     * Handle Escape key press to dismiss open tooltips
     *
     * @param {KeyboardEvent} event Keyboard event
     */
    function handleKeyDown(event) {
        if (event.key === 'Escape' || event.key === 'Esc' || event.keyCode === 27) {
            hideAll();
        }
    }

    /**
     * Setup MutationObserver on #top-panel to intercept dynamic attribute changes
     */
    function setupObserver() {
        const topPanel = document.getElementById('top-panel');
        if (!topPanel || typeof MutationObserver === 'undefined') return;

        if (observer) {
            observer.disconnect();
        }

        observer = new MutationObserver(function(mutations) {
            for (let i = 0; i < mutations.length; i++) {
                const mutation = mutations[i];

                if (mutation.type === 'attributes') {
                    const target = mutation.target;

                    // 1. Intercept native title attribute changes
                    if (mutation.attributeName === 'title' && target && target.hasAttribute('title')) {
                        const newTitle = target.getAttribute('title');
                        if (newTitle) {
                            updateTitle(target, newTitle);
                        }
                    }

                    // 2. Detect when #select-actions toolbar is hidden
                    if (target.id === 'select-actions' || (target.closest && target.closest('#select-actions'))) {
                        if (mutation.attributeName === 'style' || mutation.attributeName === 'class') {
                            const selectActions = document.getElementById('select-actions');
                            if (selectActions) {
                                const isHidden = selectActions.style.display === 'none' ||
                                                 selectActions.offsetParent === null ||
                                                 window.getComputedStyle(selectActions).display === 'none';
                                if (isHidden) {
                                    hideAll();
                                }
                            }
                        }
                    }
                } else if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            if (node.matches && node.matches(TARGET_SELECTOR)) {
                                initElement(node);
                            }
                            if (node.querySelectorAll) {
                                const matchedChildren = node.querySelectorAll(TARGET_SELECTOR);
                                matchedChildren.forEach(initElement);
                            }
                        }
                    });
                }
            }
        });

        observer.observe(topPanel, {
            attributes: true,
            attributeFilter: ['title', 'style', 'class'],
            subtree: true,
            childList: true
        });
    }

    /**
     * Initialize all top-panel and toolbar tooltips
     */
    function init() {
        const elements = document.querySelectorAll(TARGET_SELECTOR);
        elements.forEach(initElement);

        if (isInitialized) {
            bindIframeListeners();
            return;
        }

        topPanelEl = document.getElementById('top-panel');
        if (topPanelEl) {
            boundTopPanelClick = handleTopPanelClick;
            topPanelEl.addEventListener('click', boundTopPanelClick, true);
        }

        boundSelectNode = function() {
            hideAll();
        };
        window.addEventListener('Vvveb.Builder.selectNode', boundSelectNode);

        boundIframeLoaded = function() {
            bindIframeListeners();
        };
        window.addEventListener('Vvveb.iframe.loaded', boundIframeLoaded);
        window.addEventListener('vvveb.iframe.loaded', boundIframeLoaded);

        boundWindowScroll = function() {
            hideAll();
        };
        window.addEventListener('scroll', boundWindowScroll, { passive: true });

        boundKeyDown = handleKeyDown;
        window.addEventListener('keydown', boundKeyDown, { passive: true });

        if (window.Vvveb?.Builder?.iframe) {
            window.Vvveb.Builder.iframe.addEventListener('load', bindIframeListeners);
        }

        setupObserver();
        bindIframeListeners();

        isInitialized = true;
        console.log('[Align Tooltips] WpToolbarTooltips initialized successfully');
    }

    /**
     * Destroy observer and event listeners, and dispose all tooltips
     */
    function destroy() {
        if (observer) {
            observer.disconnect();
            observer = null;
        }

        if (topPanelEl && boundTopPanelClick) {
            topPanelEl.removeEventListener('click', boundTopPanelClick, true);
            boundTopPanelClick = null;
            topPanelEl = null;
        }

        if (boundSelectNode) {
            window.removeEventListener('Vvveb.Builder.selectNode', boundSelectNode);
            boundSelectNode = null;
        }

        if (boundIframeLoaded) {
            window.removeEventListener('Vvveb.iframe.loaded', boundIframeLoaded);
            window.removeEventListener('vvveb.iframe.loaded', boundIframeLoaded);
            boundIframeLoaded = null;
        }

        if (boundWindowScroll) {
            window.removeEventListener('scroll', boundWindowScroll);
            boundWindowScroll = null;
        }

        if (boundKeyDown) {
            window.removeEventListener('keydown', boundKeyDown);
            boundKeyDown = null;
        }

        try {
            const frameDoc = window.FrameDocument || (window.Vvveb?.Builder?.iframe ? window.Vvveb.Builder.iframe.contentDocument : null);
            if (frameDoc && frameDoc._alignTooltipsBound) {
                frameDoc.removeEventListener('click', hideAll);
                frameDoc.removeEventListener('scroll', hideAll);
                frameDoc.removeEventListener('keydown', handleKeyDown);
                delete frameDoc._alignTooltipsBound;
            }
        } catch (err) {}

        const elements = document.querySelectorAll(TARGET_SELECTOR);
        elements.forEach(function(el) {
            try {
                const tip = window.bootstrap?.Tooltip?.getInstance?.(el);
                if (tip && typeof tip.dispose === 'function') {
                    tip.dispose();
                }
            } catch (err) {}
        });

        isInitialized = false;
        console.log('[Align Tooltips] WpToolbarTooltips destroyed');
    }

    return {
        init: init,
        initElement: initElement,
        updateTitle: updateTitle,
        hide: hide,
        hideAll: hideAll,
        destroy: destroy
    };
})();
