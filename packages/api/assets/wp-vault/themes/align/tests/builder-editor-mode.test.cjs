const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const switchSource = fs.readFileSync(path.join(__dirname, '../core/builder/gutenberg/align-editor-mode-switch.js'), 'utf8');
const integrationSource = fs.readFileSync(path.join(__dirname, '../core/builder/gutenberg/align-site-editor-integration.js'), 'utf8');
const coordinatorSource = fs.readFileSync(path.join(__dirname, '../core/builder/gutenberg/align-save-coordinator.js'), 'utf8');

function setupIntegrationEnv({
    url = 'http://example.local/wp-admin/post.php?post=101&action=edit',
    postType = 'page',
    postId = 101,
    meta = {},
    sessionStore = {},
    domSelectValue = null,
    config = {},
    isDirty = false,
    isSavingPost = false,
    isAutosavingPost = false,
    coordinatorBusy = false,
    confirmReturn = true,
    fetchMock = null,
    entityHasEdits = false,
    templateLock = null
} = {}) {
    const session = { ...sessionStore };
    let confirmCalls = 0;
    const sessionStorage = {
        getItem: (k) => (k in session ? session[k] : null),
        setItem: (k, v) => { session[k] = String(v); },
        removeItem: (k) => { delete session[k]; }
    };

    let currentMeta = { ...meta };
    let reloaded = false;
    const logs = [];
    const subscribeCallbacks = [];

    const wpMock = {
        data: {
            select: (store) => {
                if (store === 'core/editor') {
                    return {
                        getCurrentPostId: () => postId,
                        getCurrentPostType: () => postType,
                        getEditedPostAttribute: (attr) => (attr === 'meta' ? currentMeta : null),
                        getCurrentPost: () => ({ id: postId, meta: currentMeta }),
                        isEditedPostDirty: () => isDirty,
                        isSavingPost: () => isSavingPost,
                        isAutosavingPost: () => isAutosavingPost,
                        isSavingNonPostEntityChanges: () => false
                    };
                }
                if (store === 'core/edit-site') {
                    return {
                        getEditedPostId: () => null,
                        getEditedPostType: () => null
                    };
                }
                if (store === 'core/block-editor') {
                    return {
                        getSelectedBlock: () => null
                    };
                }
                if (store === 'core') {
                    return {
                        hasEditsForEntityRecord: () => entityHasEdits
                    };
                }
                return null;
            },
        dispatch: (store) => {
                if (store === 'core/editor') {
                    return {
                        editPost: (edits) => {
                            if (edits && edits.meta) {
                                lastEditMeta = { ...edits.meta };
                                currentMeta = { ...currentMeta, ...edits.meta };
                            }
                        }
                    };
                }
                return {};
            },
            subscribe: (cb) => {
                subscribeCallbacks.push(cb);
            }
        }
    };

    let lastEditMeta = null;
    const windowObj = {
        location: {
            href: url,
            search: new URL(url).search,
            pathname: new URL(url).pathname,
            origin: new URL(url).origin,
            reload: () => { reloaded = true; }
        },
        sessionStorage,
        addEventListener: () => {},
        setInterval: () => {},
        setTimeout: () => {},
        clearTimeout: () => {},
        confirm: () => { confirmCalls++; return confirmReturn; },
        alert: () => {},
        fetch: fetchMock || (async () => ({
            ok: true,
            status: 200,
            json: async () => ({ status: 'success', post_id: postId, mode: 'gutenberg', locked: false, revision_id: 55, assets_cleared: true })
        })),
        __ALIGN_TEST_USE_NATIVE_CONFIRM: true,
        AlignEditorModeConfig: {
            postTypes: ['post', 'page', 'portfolio'],
            locked: false,
            lockedMode: null,
            switchUrl: 'http://example.local/wp-json/align-builder/v1/switch-editor-mode',
            nonce: 'test-nonce',
            ...config
        },
        AlignSaveCoordinator: {
            isBusy: () => coordinatorBusy,
            resolveActiveEntity: () => ({ id: postId }),
            _lastBuilderPayload: null
        },
        AlignTemplateModeLock: templateLock,
        wp: wpMock
    };

    const elementsById = {};
    const docObj = {
        body: {
            className: `post-${postId} post-type-${postType}`,
            classList: {
                add: () => {},
                remove: () => {},
                contains: () => false
            }
        },
        getElementById: (id) => {
            if (id in elementsById) return elementsById[id];
            if (id === 'post_ID' && postId !== null) return { value: String(postId) };
            if (id === 'post_type') return { value: postType };
            return null;
        },
        querySelector: (sel) => {
            if (sel === 'select[name="_align_editor_mode"]' && domSelectValue !== null) {
                return { value: domSelectValue };
            }
            return null;
        },
        querySelectorAll: () => []
    };

    const context = {
        window: windowObj,
        sessionStorage,
        wp: wpMock,
        document: docObj,
        URL,
        URLSearchParams,
        console: {
            log: (...args) => { logs.push(args.join(' ')); },
            warn: () => {},
            error: () => {}
        },
        setInterval: () => {},
        setTimeout: () => {},
        clearTimeout: () => {}
    };

    vm.runInNewContext(switchSource, context);
    vm.runInNewContext(integrationSource, context);

    return {
        getActiveEditorMode: context.window.AlignSiteEditorIntegration.getActiveEditorMode,
        getSavedEditorModeMeta: context.window.AlignSiteEditorIntegration.getSavedEditorModeMeta,
        setActiveEditorMode: context.window.AlignSiteEditorIntegration.setActiveEditorMode,
        modeSwitch: context.window.AlignEditorModeSwitch,
        session,
        getMeta: () => currentMeta,
        getLastEditMeta: () => lastEditMeta,
        wasReloaded: () => reloaded,
        getConfirmCalls: () => confirmCalls,
        logs,
        elementsById,
        triggerSubscribe: () => subscribeCallbacks.forEach((cb) => cb())
    };
}

function setupCoordinatorEnv({
    url = 'http://example.local/wp-admin/post.php?post=202&action=edit',
    postType = 'page',
    postId = 202,
    meta = {}
} = {}) {
    let lastEditMeta = null;
    const wpMock = {
        data: {
            select: (store) => {
                if (store === 'core/editor') {
                    return {
                        getCurrentPostId: () => postId,
                        getCurrentPostType: () => postType,
                        getEditedPostAttribute: (attr) => (attr === 'meta' ? meta : null)
                    };
                }
                return null;
            },
            dispatch: (store) => {
                if (store === 'core/editor') {
                    return {
                        editPost: (edits) => {
                            if (edits && edits.meta) lastEditMeta = { ...edits.meta };
                        }
                    };
                }
                return {};
            }
        }
    };

    const windowObj = {
        location: {
            href: url,
            search: new URL(url).search,
            pathname: new URL(url).pathname,
            origin: new URL(url).origin
        },
        addEventListener: () => {},
        setInterval: () => {},
        setTimeout: () => {},
        clearTimeout: () => {},
        AlignTemplateMarkup: { build: (_ent, p) => p?.html || '' },
        wp: wpMock
    };

    const docObj = {
        body: {
            className: `post-${postId} post-type-${postType}`,
            classList: {
                contains: () => false
            }
        },
        getElementById: () => null,
        querySelector: () => null,
        querySelectorAll: () => [],
        addEventListener: () => {}
    };

    const context = {
        window: windowObj,
        wp: wpMock,
        document: docObj,
        URL,
        URLSearchParams,
        console: { log: () => {}, warn: () => {}, error: () => {} }
    };

    vm.runInNewContext(coordinatorSource, context);
    context.window.AlignSaveCoordinator._getLastEditMeta = () => lastEditMeta;
    return context.window.AlignSaveCoordinator;
}

test('1. New page with no mode set defaults to "builder"', () => {
    const env = setupIntegrationEnv({ postType: 'page', meta: {} });
    assert.equal(env.getActiveEditorMode('page'), 'builder', 'New page must default to builder mode');
});

test('2. Page with saved meta "gutenberg" resolves to "gutenberg"', () => {
    const env = setupIntegrationEnv({ postType: 'page', meta: { _align_editor_mode: 'gutenberg' } });
    assert.equal(env.getActiveEditorMode('page'), 'gutenberg', 'Page with meta gutenberg must resolve to gutenberg');
});

test('3. Page with saved meta "builder" resolves to "builder"', () => {
    const env = setupIntegrationEnv({ postType: 'page', meta: { _align_editor_mode: 'builder' } });
    assert.equal(env.getActiveEditorMode('page'), 'builder', 'Page with meta builder must resolve to builder');
});

test('4. Blog post with no mode set defaults to "gutenberg"', () => {
    const env = setupIntegrationEnv({ postType: 'post', meta: {} });
    assert.equal(env.getActiveEditorMode('post'), 'gutenberg', 'New post must default to gutenberg mode');
});

test('5. Blog post with saved meta "builder" resolves to "builder"', () => {
    const env = setupIntegrationEnv({ postType: 'post', meta: { _align_editor_mode: 'builder' } });
    assert.equal(env.getActiveEditorMode('post'), 'builder', 'Blog post with meta builder must resolve to builder');
});

test('6. Blog post with saved meta "gutenberg" resolves to "gutenberg"', () => {
    const env = setupIntegrationEnv({ postType: 'post', meta: { _align_editor_mode: 'gutenberg' } });
    assert.equal(env.getActiveEditorMode('post'), 'gutenberg', 'Blog post with meta gutenberg must resolve to gutenberg');
});

test('7. URL param ?align_mode=builder overrides post meta and default when UNLOCKED', () => {
    const env = setupIntegrationEnv({
        url: 'http://example.local/wp-admin/post.php?post=101&action=edit&align_mode=builder',
        postType: 'post',
        meta: { _align_editor_mode: 'gutenberg', _align_editor_locked: '0' }
    });
    assert.equal(env.getActiveEditorMode('post'), 'builder', 'URL param ?align_mode=builder must take precedence over meta when unlocked');
});

test('8. URL param ?align_mode=gutenberg overrides page default "builder" when UNLOCKED', () => {
    const env = setupIntegrationEnv({
        url: 'http://example.local/wp-admin/post.php?post=101&action=edit&align_mode=gutenberg',
        postType: 'page',
        meta: {}
    });
    assert.equal(env.getActiveEditorMode('page'), 'gutenberg', 'URL param ?align_mode=gutenberg must take precedence over page default');
});

test('9. Session storage overrides mode for entities without post meta', () => {
    const env = setupIntegrationEnv({
        url: 'http://example.local/wp-admin/site-editor.php?pId=header&postType=wp_template_part',
        postType: 'wp_template_part',
        postId: null,
        meta: {},
        sessionStore: {
            'align_mode_&tp_id=tp_header': 'builder'
        }
    });
    const mode = env.getActiveEditorMode('wp_template_part');
    assert.equal(mode, 'builder', 'Session storage should override mode for template parts');
});

test('10. Classic DOM select fallback reads editor mode when store is empty', () => {
    const env = setupIntegrationEnv({
        postType: 'page',
        meta: {},
        domSelectValue: 'gutenberg'
    });
    const mode = env.getActiveEditorMode('page');
    assert.equal(mode, 'gutenberg', 'DOM select value should be used as fallback when store meta is empty');
});

test('11. Coordinator resolves default mode: Page -> builder, Post -> gutenberg', () => {
    const pageCoord = setupCoordinatorEnv({ postType: 'page', postId: 301, meta: {} });
    const pageEntity = pageCoord.resolveActiveEntity();
    assert.equal(pageEntity.editorMode, 'builder', 'Coordinator page entity without meta must resolve to builder');
    assert.equal(pageEntity.isEmbedded, true, 'Coordinator page entity defaulting to builder must be embedded');

    const postCoord = setupCoordinatorEnv({ postType: 'post', postId: 302, meta: {} });
    const postEntity = postCoord.resolveActiveEntity();
    assert.equal(postEntity.editorMode, 'gutenberg', 'Coordinator post entity without meta must resolve to gutenberg');
    assert.equal(postEntity.isEmbedded, false, 'Coordinator post entity defaulting to gutenberg must not be embedded');
});

test('12. Coordinator honors explicit post meta overrides, strips _align_editor_locked, and exposes isBusy()', () => {
    const postWithBuilder = setupCoordinatorEnv({
        postType: 'post',
        postId: 303,
        meta: { _align_editor_mode: 'builder', _align_editor_locked: '0', custom_key: 'keep' }
    });
    const entity1 = postWithBuilder.resolveActiveEntity();
    assert.equal(entity1.editorMode, 'builder');
    assert.equal(entity1.isEmbedded, true);
    assert.equal(postWithBuilder.isBusy(), false);
    postWithBuilder._isStoreSaving = true;
    assert.equal(postWithBuilder.isBusy(), true);
    postWithBuilder.handleBuilderDirty({ post_id: 303, html: '<div>Hi</div>' }, entity1);
    assert.equal('_align_editor_locked' in postWithBuilder._getLastEditMeta(), false, 'handleBuilderDirty must strip _align_editor_locked from editPost meta');
    assert.equal(postWithBuilder._getLastEditMeta()._align_editor_mode, 'builder');
    assert.equal(postWithBuilder._getLastEditMeta().custom_key, 'keep');

    const pageWithGutenberg = setupCoordinatorEnv({
        postType: 'page',
        postId: 304,
        meta: { _align_editor_mode: 'gutenberg' }
    });
    const entity2 = pageWithGutenberg.resolveActiveEntity();
    assert.equal(entity2.editorMode, 'gutenberg');
    assert.equal(entity2.isEmbedded, false);
});

test('13. T10: Locked post ignores ?align_mode= URL param and sessionStorage', () => {
    const env = setupIntegrationEnv({
        url: 'http://example.local/wp-admin/post.php?post=101&action=edit&align_mode=builder',
        postType: 'page',
        postId: 101,
        meta: { _align_editor_mode: 'gutenberg', _align_editor_locked: '1' },
        sessionStore: {
            'align_mode_&post_id=101': 'builder'
        }
    });
    assert.equal(env.modeSwitch.isPostLocked('page'), true);
    assert.equal(env.getActiveEditorMode('page'), 'gutenberg');
    assert.equal(env.setActiveEditorMode('page', 'builder'), false, 'Direct setActiveEditorMode must be rejected when locked');
});

test('14. T12: Custom Post Type (CPT) reads and writes _align_editor_mode meta without dirtying _align_editor_locked', () => {
    const env = setupIntegrationEnv({
        postType: 'portfolio',
        postId: 404,
        meta: { _align_editor_mode: 'builder', _align_editor_locked: '0', other_meta: 'val' }
    });
    assert.equal(env.modeSwitch.getSupportedPostTypes().includes('portfolio'), true);
    assert.equal(env.getActiveEditorMode('portfolio'), 'builder');
    env.setActiveEditorMode('portfolio', 'gutenberg');
    assert.equal(env.getMeta()._align_editor_mode, 'gutenberg');
    assert.equal('_align_editor_locked' in env.getLastEditMeta(), false, 'setActiveEditorMode must strip _align_editor_locked from editPost meta');
    assert.equal(env.getLastEditMeta().other_meta, 'val');
});

test('15. T2: Unlocked dirty editor asks confirmation before switching; Cancel aborts switch', async () => {
    const env = setupIntegrationEnv({
        postType: 'page',
        postId: 101,
        meta: { _align_editor_mode: 'gutenberg', _align_editor_locked: '0' },
        isDirty: true,
        confirmReturn: false
    });
    const switched = await env.modeSwitch.handleSwitchClick();
    assert.equal(switched, false);
    assert.equal(env.getMeta()._align_editor_mode, 'gutenberg');
    assert.ok(env.logs.some((l) => l.includes('[Align UI] Mode switch: gutenberg -> builder | locked=false | confirmed=false')));
});

test('15b. Template with unsaved edits asks confirmation before Gutenberg -> Builder; Cancel aborts switch', async () => {
    const templateUrl = 'http://example.local/wp-admin/site-editor.php?p=%2Fwp_template%2Falign-child%2F%2F404&canvas=edit';
    const dirty = setupIntegrationEnv({ url: templateUrl, postType: 'wp_template', postId: 'align-child//404', entityHasEdits: true, confirmReturn: false });
    assert.equal(await dirty.modeSwitch.handleSwitchClick(), false);
    assert.equal(dirty.getConfirmCalls(), 1);
    assert.equal(dirty.modeSwitch.getActiveEditorMode('wp_template'), 'gutenberg');

    const clean = setupIntegrationEnv({ url: templateUrl, postType: 'wp_template', postId: 'align-child//404', entityHasEdits: false });
    assert.equal(await clean.modeSwitch.handleSwitchClick(), true);
    assert.equal(clean.getConfirmCalls(), 0);
    assert.equal(clean.modeSwitch.getActiveEditorMode('wp_template'), 'builder');
});

test('15c. Locked template: mode follows saved content; switch asks, clears via native save, then reloads', async () => {
    const templateUrl = 'http://example.local/wp-admin/site-editor.php?p=%2Fwp_template%2Falign-child%2F%2Fhome&canvas=edit&align_mode=gutenberg';
    let locked = 'builder';
    let cleared = 0;
    const templateLock = {
        getLockedMode: () => locked,
        clearForSwitch: async () => { cleared++; locked = null; return ''; }
    };

    const cancel = setupIntegrationEnv({ url: templateUrl, postType: 'wp_template', postId: 'align-child//home', templateLock, confirmReturn: false });
    assert.equal(cancel.modeSwitch.getActiveEditorMode('wp_template'), 'builder', 'Lock ignores ?align_mode=');
    assert.equal(cancel.setActiveEditorMode('wp_template', 'gutenberg'), false, 'Locked template mode cannot be set directly');
    assert.equal(await cancel.modeSwitch.handleSwitchClick(), false);
    assert.equal(cleared, 0);

    const confirm = setupIntegrationEnv({ url: templateUrl, postType: 'wp_template', postId: 'align-child//home', templateLock, confirmReturn: true });
    assert.equal(await confirm.modeSwitch.handleSwitchClick(), true);
    assert.equal(cleared, 1);
    assert.equal(Object.values(confirm.session)[0], 'gutenberg');
});

test('16. T4 & T5: Locked post switch triggers confirmation and calls REST endpoint on confirm', async () => {
    let calledPayload = null;
    const envCancel = setupIntegrationEnv({
        postType: 'page',
        postId: 101,
        meta: { _align_editor_mode: 'builder', _align_editor_locked: '1' },
        confirmReturn: false
    });
    const resCancel = await envCancel.modeSwitch.handleSwitchClick();
    assert.equal(resCancel, false);
    assert.equal(envCancel.wasReloaded(), false);

    const envConfirm = setupIntegrationEnv({
        postType: 'page',
        postId: 101,
        meta: { _align_editor_mode: 'builder', _align_editor_locked: '1' },
        confirmReturn: true,
        fetchMock: async (url, opts) => {
            calledPayload = JSON.parse(opts.body);
            return {
                ok: true,
                status: 200,
                json: async () => ({ status: 'success', post_id: 101, mode: 'gutenberg', locked: false })
            };
        }
    });
    const resConfirm = await envConfirm.modeSwitch.handleSwitchClick();
    assert.equal(resConfirm, true);
    assert.deepEqual(calledPayload, { post_id: 101, target_mode: 'gutenberg' });
    assert.equal(envConfirm.wasReloaded(), true);
});

test('17. T8 & T9: Switch button is disabled during save or autosave', async () => {
    const env = setupIntegrationEnv({
        postType: 'page',
        postId: 101,
        meta: { _align_editor_mode: 'builder', _align_editor_locked: '0' },
        isSavingPost: true
    });
    assert.equal(env.modeSwitch.isSavingInProgress(), true);
    const switched = await env.modeSwitch.handleSwitchClick();
    assert.equal(switched, false);
});
