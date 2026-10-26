const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../core/builder/js/modules/wp-save-handler.js'), 'utf8');

function createHandlerContext({ isEmbedded = true, origin = 'http://localhost' } = {}) {
    const parentMessages = [];
    const windowEventListeners = {};
    const documentEventListeners = {};
    const buttons = [];

    // Mock button element
    const createMockButton = () => {
        const loadingEl = {
            classList: {
                classes: new Set(['d-none']),
                add(c) { this.classes.add(c); },
                remove(c) { this.classes.delete(c); },
                contains(c) { return this.classes.has(c); }
            }
        };
        const textEl = {
            innerHTML: '<span>Save</span>',
            classList: {
                classes: new Set(),
                add(c) { this.classes.add(c); },
                remove(c) { this.classes.delete(c); },
                contains(c) { return this.classes.has(c); }
            }
        };
        const btn = {
            attributes: {},
            listeners: {},
            setAttribute(k, v) { this.attributes[k] = String(v); },
            removeAttribute(k) { delete this.attributes[k]; },
            getAttribute(k) { return this.attributes[k]; },
            addEventListener(ev, fn) {
                if (!this.listeners[ev]) this.listeners[ev] = [];
                this.listeners[ev].push(fn);
            },
            querySelector(sel) {
                if (sel === '.loading') return loadingEl;
                if (sel === '.button-text') return textEl;
                return null;
            },
            click() {
                const fns = this.listeners['click'] || [];
                fns.forEach(fn => fn({ preventDefault: () => {}, stopPropagation: () => {} }));
            }
        };
        return btn;
    };

    const topBtn = createMockButton();
    buttons.push(topBtn);

    const windowObj = {
        location: {
            origin: origin,
            href: origin + '/wp-admin/site-editor.php'
        },
        document: {
            querySelectorAll: (sel) => {
                if (sel.includes('.save-btn')) return buttons;
                return [];
            },
            addEventListener: (ev, fn) => {
                if (!documentEventListeners[ev]) documentEventListeners[ev] = [];
                documentEventListeners[ev].push(fn);
            }
        },
        addEventListener: (ev, fn) => {
            if (!windowEventListeners[ev]) windowEventListeners[ev] = [];
            windowEventListeners[ev].push(fn);
        },
        removeEventListener: (ev, fn) => {
            if (windowEventListeners[ev]) {
                windowEventListeners[ev] = windowEventListeners[ev].filter(f => f !== fn);
            }
        },
        dispatchEvent: (ev, detail) => {
            const list = windowEventListeners[ev] || [];
            list.forEach(fn => fn(detail));
        },
        parent: null,
        URLSearchParams: URLSearchParams,
        setTimeout: global.setTimeout,
        clearTimeout: global.clearTimeout,
        DOMParser: class {
            parseFromString(html) {
                return {
                    querySelectorAll: () => [],
                    body: {
                        querySelectorAll: () => [],
                        innerHTML: html
                    },
                    documentElement: { outerHTML: html }
                };
            }
        },
        AlignBuilderModules: {},
        VvvebWpConfig: {
            activePostId: 42,
            restSaveUrl: origin + '/wp-json/align/v1/save-page',
            restNonce: 'test-nonce'
        },
        Vvveb: {
            Builder: {
                hasChanges: false,
                getHtml: () => '<p>Initial Content</p>'
            },
            Undo: {
                reset: () => {}
            },
            StyleManager: {
                getCss: () => '.test { color: red; }'
            },
            JsManager: {
                getJs: () => 'console.log("hello");'
            }
        },
        console: console
    };

    if (isEmbedded) {
        windowObj.parent = {
            postMessage: (msg, targetOrigin) => {
                parentMessages.push({ msg, targetOrigin });
            }
        };
    } else {
        windowObj.parent = windowObj;
    }

    windowObj.window = windowObj;
    windowObj.globalThis = windowObj;

    const context = vm.createContext(windowObj);
    vm.runInContext(source, context);

    return {
        context,
        windowObj,
        parentMessages,
        topBtn,
        windowEventListeners
    };
}

function createMockFrameDoc() {
    const listeners = {};
    const frameBody = {
        addEventListener: (ev, fn) => {
            if (!listeners[ev]) listeners[ev] = [];
            listeners[ev].push(fn);
        },
        removeEventListener: (ev, fn) => {
            if (listeners[ev]) {
                listeners[ev] = listeners[ev].filter(f => f !== fn);
            }
        },
        dispatch: (ev) => {
            const fns = listeners[ev] || [];
            fns.forEach(fn => fn());
        }
    };
    return {
        body: frameBody,
        listeners,
        querySelectorAll: () => []
    };
}

test('WpSaveHandler binds canvas events and tracks mutations', () => {
    const { context, windowObj } = createHandlerContext();
    const handler = context.AlignBuilderModules.WpSaveHandler;
    handler.init();

    const mockDoc = createMockFrameDoc();
    handler.bindCanvasEvents(mockDoc);

    assert.ok(mockDoc.listeners['input'].length > 0, 'input listener attached');
    assert.ok(mockDoc.listeners['change'].length > 0, 'change listener attached');
    assert.ok(mockDoc.listeners['vvveb.undo.add'].length > 0, 'undo.add listener attached');

    // Trigger an input event on canvas
    const prevVer = handler._currentEditVersion || 0;
    mockDoc.body.dispatch('input');
    assert.equal(handler._currentEditVersion, prevVer + 1, 'Edit version incremented on input');

    // Clean unbind
    handler.unbindCanvasEvents();
    assert.equal(mockDoc.listeners['input'].length, 0, 'Listeners detached on unbind');
});

test('WpSaveHandler debounces dirty notification to parent coordinator', async () => {
    const { context, parentMessages } = createHandlerContext();
    const handler = context.AlignBuilderModules.WpSaveHandler;
    handler.init();

    const mockDoc = createMockFrameDoc();
    handler.bindCanvasEvents(mockDoc);

    // Dispatch multiple events rapidly
    mockDoc.body.dispatch('input');
    mockDoc.body.dispatch('change');
    mockDoc.body.dispatch('vvveb.undo.add');

    assert.equal(handler._currentEditVersion, 3);
    assert.equal(parentMessages.length, 0, 'Should not dispatch immediately before debounce');

    // Wait for 350ms debounce
    await new Promise(resolve => setTimeout(resolve, 350));

    assert.equal(parentMessages.length, 1, 'Debounced message dispatched once');
    assert.equal(parentMessages[0].msg.type, 'ALIGN_BUILDER_DIRTY');
    assert.equal(parentMessages[0].msg.payload._capturedVersion, 3);
    assert.equal(parentMessages[0].msg.payload.post_id, 42);
    assert.ok(parentMessages[0].msg.payload.html.includes('Initial Content'));
});

test('WpSaveHandler routes embedded Save button click to parent coordinator', () => {
    const { context, parentMessages, topBtn } = createHandlerContext({ isEmbedded: true });
    const handler = context.AlignBuilderModules.WpSaveHandler;
    handler.init();

    topBtn.click();

    assert.equal(parentMessages.length, 1);
    assert.equal(parentMessages[0].msg.type, 'ALIGN_BUILDER_REQUEST_SAVE');
    assert.equal(topBtn.getAttribute('disabled'), 'true');
});

test('WpSaveHandler in-flight edits race condition: preserves dirty state when edits occur during save', () => {
    const { context, parentMessages } = createHandlerContext({ isEmbedded: true });
    const handler = context.AlignBuilderModules.WpSaveHandler;
    handler.init();

    // 1. Initial edit to version 1
    handler.setButtonState('dirty');
    assert.equal(handler._currentEditVersion, 1);

    // 2. Snapshot captured for coordinator
    const snapshot = handler.captureSnapshotPayload();
    assert.equal(snapshot._capturedVersion, 1);

    // 3. User makes an edit WHILE save is in flight -> version increments to 2
    handler.setButtonState('dirty');
    assert.equal(handler._currentEditVersion, 2);
    context.Vvveb.Builder.hasChanges = true;

    // 4. Coordinator returns ACK success for the previous version 1 snapshot
    handler.applyPostSaveSuccess({ success: true, status: 'publish' }, null, snapshot._capturedVersion);

    // 5. Must NOT reset changes because version 2 is still uncommitted!
    assert.equal(context.Vvveb.Builder.hasChanges, true, 'hasChanges must remain true when edits occurred during save');
});

test('WpSaveHandler clean save: resets changes and marks button saved when no in-flight edits', () => {
    let undoResetCalled = false;
    const { context } = createHandlerContext({ isEmbedded: true });
    context.Vvveb.Undo.reset = () => { undoResetCalled = true; };

    const handler = context.AlignBuilderModules.WpSaveHandler;
    handler.init();

    // 1. Single edit
    handler.setButtonState('dirty');
    const snapshot = handler.captureSnapshotPayload();
    context.Vvveb.Builder.hasChanges = true;

    // 2. Save ACK for current version
    handler.applyPostSaveSuccess({ success: true, status: 'publish' }, null, snapshot._capturedVersion);

    assert.equal(context.Vvveb.Builder.hasChanges, false, 'hasChanges cleared');
    assert.equal(undoResetCalled, true, 'Undo stack reset');
});
