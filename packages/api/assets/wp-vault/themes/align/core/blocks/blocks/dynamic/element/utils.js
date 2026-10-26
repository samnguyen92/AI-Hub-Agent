(function(wp) {
    const CONTAINER_TAGS = ['div', 'section', 'article', 'header', 'footer', 'main', 'aside', 'nav', 'a', 'ul', 'ol', 'li'];
    const TEXT_TAGS = ['p', 'span', 'strong', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'label', 'button'];
    const SELF_CLOSING_TAGS = ['hr', 'br', 'input'];

    window.__activeAlignTabs = window.__activeAlignTabs || {};

    function getActiveTab(tabListId, tabListEl) {
        if (window.__activeAlignTabs[tabListId]) {
            return window.__activeAlignTabs[tabListId];
        }
        if (tabListEl) {
            const firstTab = tabListEl.querySelector('[data-tab], [data-tab-target]');
            if (firstTab) {
                const defaultTab = firstTab.getAttribute('data-tab') || firstTab.getAttribute('data-tab-target');
                window.__activeAlignTabs[tabListId] = defaultTab;
                return defaultTab;
            }
        }
        return null;
    }

    function getBlockElement(clientId) {
        var foundEl = document.querySelector('[data-block="' + clientId + '"]');
        if (foundEl) return foundEl;
        var iframes = document.querySelectorAll('iframe');
        for (var i = 0; i < iframes.length; i++) {
            try {
                var doc = iframes[i].contentDocument || iframes[i].contentWindow.document;
                foundEl = doc.querySelector('[data-block="' + clientId + '"]');
                if (foundEl) return foundEl;
            } catch (e) {}
        }
        return null;
    }

    function useHideAdvancedPanel() {
        wp.element.useEffect(function() {
            var hideAdvanced = function() {
                var inspector = document.querySelector('.block-editor-block-inspector');
                if (!inspector) return;
                var panels = inspector.querySelectorAll('.components-panel__body');
                panels.forEach(function(panel) {
                    var btn = panel.querySelector(':scope > .components-panel__body-title button');
                    if (btn && btn.textContent.trim() === 'Advanced') { panel.style.display = 'none'; }
                });
            };
            var timer = setTimeout(hideAdvanced, 50);
            return function() { clearTimeout(timer); };
        });
    }

    window.AlignElementUtils = {
        CONTAINER_TAGS: CONTAINER_TAGS,
        TEXT_TAGS: TEXT_TAGS,
        SELF_CLOSING_TAGS: SELF_CLOSING_TAGS,
        getActiveTab: getActiveTab,
        getBlockElement: getBlockElement,
        useHideAdvancedPanel: useHideAdvancedPanel
    };
})(window.wp);
