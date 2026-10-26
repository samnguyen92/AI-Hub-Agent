/**
 * Align Theme Builder - Right-Click Context Menu Module
 * Provides a native floating context menu for element actions in Canvas & Navigator.
 * @package Align
 */

window.AlignBuilderModules = window.AlignBuilderModules || {};

window.AlignBuilderModules.WpContextMenu = (function() {
    'use strict';

    let menuEl = null;
    let isInitialized = false;

    const VOID_TAGS = new Set([
        'IMG', 'INPUT', 'HR', 'BR', 'TEXTAREA', 'SELECT',
        'IFRAME', 'VIDEO', 'AUDIO', 'SOURCE', 'BUTTON', 'EMBED'
    ]);

    function resolveComponent(el, evt) {
        if (evt && typeof evt.composedPath === 'function') {
            for (const item of evt.composedPath()) {
                if (item?.hasAttribute && (
                    item.hasAttribute('data-component-shortcode') ||
                    item.hasAttribute('data-component-wp-block') ||
                    item.hasAttribute('data-component-post-content')
                )) return item;
                if (item?.closest) {
                    const f = item.closest('[data-component-shortcode], [data-component-wp-block], [data-component-post-content]');
                    if (f) return f;
                }
            }
        }
        return el?.closest ? el.closest('[data-component-shortcode], [data-component-wp-block], [data-component-post-content]') : null;
    }

    function isInputActive(target) {
        if (!target) target = document.activeElement;
        if (!target) return false;
        const tag = (target.tagName || '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select') return true;
        if (target.isContentEditable || target.getAttribute('contenteditable') === 'true') return true;
        if (target.closest && (target.closest('.cm-editor') || target.closest('.CodeMirror') || target.closest('[contenteditable="true"]'))) return true;
        return false;
    }

    function createMenuDOM() {
        if (menuEl) return menuEl;
        menuEl = document.createElement('div');
        menuEl.id = 'align-builder-context-menu';
        menuEl.innerHTML = `
            <div class="context-menu-header"><span class="tag-name">DIV</span><span class="class-name"></span></div>
            <ul class="context-menu-list">
                <li class="context-menu-item" data-action="copy">
                    <div class="context-menu-item-content"><i class="la la-copy"></i><span>Copy</span></div>
                    <span class="context-menu-shortcut">Ctrl+C</span>
                </li>
                <li class="context-menu-item" data-action="paste-after">
                    <div class="context-menu-item-content"><i class="la la-paste"></i><span>Paste After</span></div>
                    <span class="context-menu-shortcut">Ctrl+V</span>
                </li>
                <li class="context-menu-item" data-action="paste-inside">
                    <div class="context-menu-item-content"><i class="la la-level-down-alt"></i><span>Paste Inside</span></div>
                </li>
                <li class="context-menu-item" data-action="duplicate">
                    <div class="context-menu-item-content"><i class="la la-clone"></i><span>Duplicate</span></div>
                    <span class="context-menu-shortcut">Ctrl+D</span>
                </li>
                <li class="context-menu-divider"></li>
                <li class="context-menu-item" data-action="move-up">
                    <div class="context-menu-item-content"><i class="la la-arrow-up"></i><span>Move Up</span></div>
                    <span class="context-menu-shortcut">Alt+↑</span>
                </li>
                <li class="context-menu-item" data-action="move-down">
                    <div class="context-menu-item-content"><i class="la la-arrow-down"></i><span>Move Down</span></div>
                    <span class="context-menu-shortcut">Alt+↓</span>
                </li>
                <li class="context-menu-divider"></li>
                <li class="context-menu-item" data-action="toggle-hide">
                    <div class="context-menu-item-content"><i class="la la-eye"></i><span class="hide-label">Hide Element</span></div>
                </li>
                <li class="context-menu-item" data-action="translate" style="display:none;">
                    <div class="context-menu-item-content"><i class="la la-language"></i><span>AI Translate Section</span></div>
                </li>
                <li class="context-menu-item" data-action="restore" style="display:none;">
                    <div class="context-menu-item-content"><i class="la la-history"></i><span>Restore from Original</span></div>
                </li>
                <li class="context-menu-item" data-action="convert-dynamic" style="display:none;">
                    <div class="context-menu-item-content"><i class="la la-magic"></i><span>Convert to Dynamic</span></div>
                </li>
                <li class="context-menu-divider"></li>
                <li class="context-menu-item is-danger" data-action="delete">
                    <div class="context-menu-item-content"><i class="la la-trash"></i><span>Delete</span></div>
                    <span class="context-menu-shortcut">Del</span>
                </li>
            </ul>
        `;
        document.body.appendChild(menuEl);
        menuEl.addEventListener('click', function(e) {
            const item = e.target.closest('.context-menu-item');
            if (!item || item.classList.contains('is-disabled')) return;
            executeAction(item.getAttribute('data-action'));
            hideMenu();
            e.preventDefault();
            e.stopPropagation();
        });
        menuEl.addEventListener('contextmenu', function(e) { e.preventDefault(); e.stopPropagation(); });
        return menuEl;
    }

    function executeAction(action) {
        console.log('[Align ContextMenu] Executing action:', action);
        try {
            switch (action) {
                case 'copy': copySelectedElement(); break;
                case 'paste-after': pasteCopiedElement(false); break;
                case 'paste-inside': pasteCopiedElement(true); break;
                case 'duplicate': window.Vvveb?.Builder?.cloneSelectedNodes?.(); break;
                case 'move-up':
                    document.getElementById('select-box')?.style.setProperty('display', 'none');
                    window.Vvveb?.Builder?.moveNodeUp?.();
                    break;
                case 'move-down':
                    document.getElementById('select-box')?.style.setProperty('display', 'none');
                    window.Vvveb?.Builder?.moveNodeDown?.();
                    break;
                case 'toggle-hide': document.getElementById('toggle-hide-btn')?.click(); break;
                case 'translate': document.getElementById('translate-section-btn')?.click(); break;
                case 'restore': document.getElementById('copy-original-section-btn')?.click(); break;
                case 'convert-dynamic': document.getElementById('convert-dynamic-btn')?.click(); break;
                case 'delete': window.Vvveb?.Builder?.deleteSelectedNodes?.(); break;
            }
        } catch (err) {
            console.error('[Align ContextMenu] Error executing ' + action + ':', err);
        }
    }

    function copySelectedElement() {
        const node = window.Vvveb?.Builder?.selectedEl;
        if (!node || node.tagName === 'BODY' || node.tagName === 'HTML') return false;
        window.Vvveb = window.Vvveb || {};
        window.Vvveb.Builder = window.Vvveb.Builder || {};
        window.Vvveb.Builder.clipboard = {
            outerHTML: node.outerHTML,
            tagName: node.tagName,
            nodeClone: node.cloneNode(true),
            timestamp: Date.now()
        };
        if (navigator.clipboard?.writeText) {
            navigator.clipboard.writeText(node.outerHTML).catch(() => {});
        }
        const tagLabel = node.tagName.toLowerCase();
        if (typeof window.displayToast === 'function') {
            window.displayToast('success', 'Copied', `<${tagLabel}> copied to clipboard`);
        }
        console.log('[Align ContextMenu] Copied element:', tagLabel);
        return true;
    }

    function pasteCopiedElement(inside) {
        const clipboard = window.Vvveb?.Builder?.clipboard;
        if (!clipboard || !clipboard.nodeClone) {
            if (typeof window.displayToast === 'function') {
                window.displayToast('warning', 'Clipboard Empty', 'No element in clipboard to paste.');
            }
            return false;
        }
        let targetNode = window.Vvveb?.Builder?.selectedEl;
        if (!targetNode || targetNode.tagName === 'HTML') {
            targetNode = window.FrameDocument?.body || document.body;
        }

        let newNode = null;
        try {
            newNode = clipboard.nodeClone.cloneNode(true);
            if (typeof window.Vvveb.Builder._disambiguateReuseBlockDom === 'function') {
                window.Vvveb.Builder._disambiguateReuseBlockDom(newNode);
            }

            if (inside || targetNode.tagName === 'BODY') {
                if (VOID_TAGS.has(targetNode.tagName)) return false;
                targetNode.appendChild(newNode);
                const targetAstId = targetNode.getAttribute ? targetNode.getAttribute('data-ast-id') : null;
                if (targetAstId && window.Vvveb?.TreeList?.expandedAstIds) {
                    window.Vvveb.TreeList.expandedAstIds.add(targetAstId);
                }
                if (window.Vvveb?.Undo) {
                    window.Vvveb.Undo.addMutation({
                        type: 'childList', target: targetNode, addedNodes: [newNode], nextSibling: null
                    });
                }
            } else {
                if (!targetNode.parentNode) return false;
                targetNode.after(newNode);
                if (window.Vvveb?.Undo) {
                    window.Vvveb.Undo.addMutation({
                        type: 'childList', target: targetNode.parentNode, addedNodes: [newNode], nextSibling: newNode.nextSibling
                    });
                }
            }
        } catch (err) {
            console.error('[Align ContextMenu] Error pasting element into DOM:', err);
            return false;
        }

        if (window.Vvveb?.TreeList?.loadComponents) window.Vvveb.TreeList.loadComponents();
        if (window.Vvveb?.SectionList?.loadSections) window.Vvveb.SectionList.loadSections();
        window.Vvveb.Builder.selectNode(newNode);
        if (window.Vvveb?.TreeList?.selectComponent) window.Vvveb.TreeList.selectComponent(newNode);
        if (window.Vvveb?.Builder?.loadNodeComponent) window.Vvveb.Builder.loadNodeComponent(newNode);

        if (typeof window.displayToast === 'function') {
            window.displayToast('success', 'Pasted', `Element pasted ${inside ? 'inside' : 'after'}`);
        }
        console.log('[Align ContextMenu] Pasted element successfully');
        return true;
    }

    function showMenu(x, y, targetNode) {
        if (!menuEl) createMenuDOM();
        if (!targetNode) targetNode = window.Vvveb?.Builder?.selectedEl;
        if (!targetNode || targetNode.tagName === 'HTML') return;

        const isBody = targetNode.tagName === 'BODY';

        // 1. Header info
        const tagSpan = menuEl.querySelector('.tag-name');
        const classSpan = menuEl.querySelector('.class-name');
        if (tagSpan) tagSpan.textContent = targetNode.tagName;
        if (classSpan) {
            let desc = targetNode.id ? '#' + targetNode.id : '';
            if (targetNode.className && typeof targetNode.className === 'string') {
                const cls = targetNode.className.trim().split(/\s+/).filter(c => c && !c.startsWith('is-') && !c.startsWith('vvveb-'))[0];
                if (cls) desc += (desc ? ' ' : '') + '.' + cls;
            }
            classSpan.textContent = desc;
        }

        // 2. Clipboard & Paste states
        const hasClipboard = !!(window.Vvveb?.Builder?.clipboard?.nodeClone);
        const copyItem = menuEl.querySelector('[data-action="copy"]');
        const pasteAfterItem = menuEl.querySelector('[data-action="paste-after"]');
        const pasteInsideItem = menuEl.querySelector('[data-action="paste-inside"]');
        const dupItem = menuEl.querySelector('[data-action="duplicate"]');
        const upItem = menuEl.querySelector('[data-action="move-up"]');
        const downItem = menuEl.querySelector('[data-action="move-down"]');
        const hideItem = menuEl.querySelector('[data-action="toggle-hide"]');
        const delItem = menuEl.querySelector('[data-action="delete"]');

        if (copyItem) copyItem.classList.toggle('is-disabled', isBody);
        if (dupItem) dupItem.classList.toggle('is-disabled', isBody);
        if (upItem) upItem.classList.toggle('is-disabled', isBody);
        if (downItem) downItem.classList.toggle('is-disabled', isBody);
        if (hideItem) hideItem.classList.toggle('is-disabled', isBody);
        if (delItem) delItem.classList.toggle('is-disabled', isBody);

        if (pasteAfterItem) {
            pasteAfterItem.style.display = isBody ? 'none' : '';
            pasteAfterItem.classList.toggle('is-disabled', !hasClipboard);
        }
        if (pasteInsideItem) {
            const canContain = !VOID_TAGS.has(targetNode.tagName);
            pasteInsideItem.style.display = canContain ? '' : 'none';
            pasteInsideItem.classList.toggle('is-disabled', !hasClipboard || !canContain);
        }

        // 3. Hide / Unhide button label & icon
        const isHidden = targetNode.getAttribute?.('data-align-hide') === 'true';
        if (hideItem) {
            const icon = hideItem.querySelector('i');
            const label = hideItem.querySelector('.hide-label');
            if (icon) icon.className = isHidden ? 'la la-eye-slash' : 'la la-eye';
            if (label) label.textContent = isHidden ? 'Unhide Element' : 'Hide Element';
        }

        // 4. Extended context tools
        ['translate-section-btn', 'copy-original-section-btn', 'convert-dynamic-btn'].forEach(btnId => {
            const b = document.getElementById(btnId);
            const act = btnId === 'translate-section-btn' ? 'translate' : (btnId === 'copy-original-section-btn' ? 'restore' : 'convert-dynamic');
            const item = menuEl.querySelector(`[data-action="${act}"]`);
            if (item) item.style.display = (!isBody && b && window.getComputedStyle(b).display !== 'none') ? '' : 'none';
        });

        // 5. Position calculation
        menuEl.style.display = 'block';
        menuEl.style.visibility = 'hidden';
        const w = menuEl.offsetWidth || 230;
        const h = menuEl.offsetHeight || 280;
        menuEl.style.left = Math.max(8, Math.min(x, window.innerWidth - w - 8)) + 'px';
        menuEl.style.top = Math.max(8, Math.min(y, window.innerHeight - h - 8)) + 'px';
        menuEl.style.visibility = 'visible';
    }

    function hideMenu() {
        if (menuEl && menuEl.style.display !== 'none') menuEl.style.display = 'none';
    }

    function handleCanvasContextMenu(e) {
        if (!e.target || window.Vvveb?.WysiwygEditor?.isActive) return;

        let target = resolveComponent(e.target, e) || e.target;
        if (!target || target.tagName === 'HTML') return;

        // Prevent browser native context menu aggressively
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        // Select node in Builder
        if (target.tagName !== 'BODY' && window.Vvveb?.Builder?.selectNode) {
            window.Vvveb.Builder.selectNode(target);
            window.Vvveb.TreeList?.selectComponent(target);
            window.Vvveb.Builder.loadNodeComponent(target);
        }

        const iframe = window.Vvveb?.Builder?.iframe || document.querySelector('#iframe-wrapper > iframe, #iframe1');
        const r = iframe ? iframe.getBoundingClientRect() : { left: 0, top: 0 };
        showMenu(r.left + e.clientX, r.top + e.clientY, target);
        return false;
    }

    function handleTreeContextMenu(e) {
        const li = e.target.closest('li[data-component]');
        if (!li || !li._treeNode) return;
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        window.Vvveb?.TreeList?.singleSelectComponent?.(li, li._treeNode);
        showMenu(e.clientX, e.clientY, li._treeNode);
        return false;
    }

    function handleKeyboardShortcuts(e) {
        if (isInputActive(e.target)) return;
        if (window.Vvveb?.WysiwygEditor?.isActive || window.Vvveb?.Builder?.texteditEl) return;
        if (e.key === 'Escape') { hideMenu(); return; }

        if (e.ctrlKey || e.metaKey) {
            const k = (e.key || '').toLowerCase();
            if (k === 'c' && window.Vvveb?.Builder?.selectedEl) {
                e.preventDefault();
                copySelectedElement();
            } else if (k === 'v' && window.Vvveb?.Builder?.clipboard?.nodeClone) {
                e.preventDefault();
                pasteCopiedElement(false);
            } else if (k === 'd' && window.Vvveb?.Builder?.selectedEl) {
                e.preventDefault();
                window.Vvveb.Builder.cloneSelectedNodes?.();
            }
        }
    }

    function attachCanvasListeners(frameDoc) {
        if (!frameDoc) return;
        try {
            const frameWin = frameDoc.defaultView || frameDoc.parentWindow;

            // Remove existing to prevent duplicates
            frameDoc.removeEventListener('contextmenu', handleCanvasContextMenu, true);
            frameDoc.removeEventListener('click', hideMenu, true);
            frameDoc.removeEventListener('scroll', hideMenu, true);
            frameDoc.removeEventListener('keydown', handleKeyboardShortcuts, true);

            // Attach with capture: true so it runs before browser default
            frameDoc.addEventListener('contextmenu', handleCanvasContextMenu, true);
            frameDoc.addEventListener('click', hideMenu, true);
            frameDoc.addEventListener('scroll', hideMenu, { passive: true, capture: true });
            frameDoc.addEventListener('keydown', handleKeyboardShortcuts, true);

            if (frameWin) {
                frameWin.removeEventListener('contextmenu', handleCanvasContextMenu, true);
                frameWin.removeEventListener('scroll', hideMenu, true);
                frameWin.addEventListener('contextmenu', handleCanvasContextMenu, true);
                frameWin.addEventListener('scroll', hideMenu, { passive: true, capture: true });
            }

            console.log('[Align ContextMenu] Canvas iframe listeners successfully attached (capture mode)');
        } catch (err) {
            console.error('[Align ContextMenu] Failed to attach canvas listeners:', err);
        }
    }

    function bindIframeEvents() {
        const iframe = window.Vvveb?.Builder?.iframe || document.querySelector('#iframe-wrapper > iframe, #iframe1');
        if (iframe) {
            iframe.addEventListener('load', function() {
                try {
                    const doc = iframe.contentDocument || iframe.contentWindow?.document;
                    if (doc) attachCanvasListeners(doc);
                } catch (e) {}
            });
            try {
                const currentDoc = iframe.contentDocument || iframe.contentWindow?.document;
                if (currentDoc && currentDoc.readyState !== 'loading') {
                    attachCanvasListeners(currentDoc);
                }
            } catch (e) {}
        }
    }

    function init() {
        if (isInitialized) return;
        createMenuDOM();

        // 1. Outside click dismissal
        document.addEventListener('click', function(e) {
            if (menuEl && !menuEl.contains(e.target)) hideMenu();
        }, true);
        window.addEventListener('scroll', hideMenu, { passive: true });
        window.addEventListener('resize', hideMenu, { passive: true });
        window.addEventListener('blur', hideMenu);

        // 2. Selection box contextmenu
        document.getElementById('select-box')?.addEventListener('contextmenu', function(e) {
            if (window.Vvveb?.Builder?.selectedEl) {
                e.preventDefault();
                e.stopPropagation();
                showMenu(e.clientX, e.clientY, window.Vvveb.Builder.selectedEl);
            }
        }, true);

        // 3. Tree navigator contextmenu
        const treeList = document.querySelector('#tree-list');
        if (treeList) {
            treeList.addEventListener('contextmenu', handleTreeContextMenu, true);
        }

        // 4. Global shortcuts
        document.addEventListener('keydown', handleKeyboardShortcuts, true);

        // 5. Iframe canvas listeners
        window.addEventListener('Vvveb.iframe.loaded', function(e) {
            const doc = e.detail || window.FrameDocument || window.Vvveb?.Builder?.iframe?.contentWindow?.document;
            attachCanvasListeners(doc);
        });
        window.addEventListener('vvveb.iframe.loaded', function(e) {
            const doc = e.detail || window.FrameDocument || window.Vvveb?.Builder?.iframe?.contentWindow?.document;
            attachCanvasListeners(doc);
        });

        bindIframeEvents();

        // Polling retry for asynchronous canvas load
        [300, 1000, 2500].forEach(delay => {
            setTimeout(function() {
                const doc = window.FrameDocument || window.Vvveb?.Builder?.iframe?.contentDocument || document.querySelector('#iframe-wrapper > iframe')?.contentDocument;
                if (doc && doc.body) {
                    attachCanvasListeners(doc);
                }
            }, delay);
        });

        isInitialized = true;
        console.log('[Align ContextMenu] ContextMenu module initialized with bulletproof capture');
    }

    return {
        init: init,
        attachCanvas: attachCanvasListeners,
        show: showMenu,
        hide: hideMenu,
        copy: copySelectedElement,
        paste: pasteCopiedElement
    };
})();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', window.AlignBuilderModules.WpContextMenu.init);
} else {
    window.AlignBuilderModules.WpContextMenu.init();
}
