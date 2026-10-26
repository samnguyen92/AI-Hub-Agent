const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../core/builder/gutenberg/align-save-coordinator.js'), 'utf8');
const markupSource = fs.readFileSync(path.join(__dirname, '../core/builder/gutenberg/align-template-markup.js'), 'utf8');

const { fakeParse, fakeSerializeRawBlock, fakeCreateElement } = require('./helpers/fake-blocks.cjs');

function createCoordinatorContext({
    url = 'http://localhost/wp-admin/site-editor.php',
    coreEditor = null,
    coreEditSite = null,
    coreData = null,
    coreBlockEditor = null,
    builderMounted = true
} = {}) {
    const postedToIframe = [];
    const subscribers = [];
    const windowObj = {
        location: {
            origin: 'http://localhost',
            search: new URL(url).search,
            href: url
        },
        addEventListener: () => {},
        removeEventListener: () => {},
        URLSearchParams: URLSearchParams,
        document: {
            readyState: 'complete',
            location: {
                search: new URL(url).search,
                href: url
            },
            body: {
                classList: {
                    contains: (cls) => builderMounted && cls === 'is-align-builder-embedded'
                }
            },
            getElementById: (id) => {
                if (id === 'align-builder-embedded-frame' && builderMounted) {
                    return {
                        contentWindow: {
                            postMessage: (msg, origin) => {
                                postedToIframe.push({ msg, origin });
                            }
                        }
                    };
                }
                return null;
            },
            addEventListener: () => {},
            removeEventListener: () => {},
            createElement: fakeCreateElement,
            createComment: (data) => ({ data })
        },
        wp: {
            blocks: {
                parse: (markup) => [{ name: 'align/custom-html', attributes: { content: markup } }],
                serializeRawBlock: fakeSerializeRawBlock
            },
            blockSerializationDefaultParser: { parse: fakeParse },
            data: {
                subscribe: (fn) => {
                    subscribers.push(fn);
                    return () => {};
                },
                select: (store) => {
                    if (store === 'core/edit-site') return coreEditSite;
                    if (store === 'core/editor') return coreEditor?.select;
                    if (store === 'core') return coreData?.select;
                    if (store === 'core/block-editor') return coreBlockEditor?.select;
                    return null;
                },
                dispatch: (store) => {
                    if (store === 'core/editor') return coreEditor?.dispatch;
                    if (store === 'core') return coreData?.dispatch;
                    if (store === 'core/block-editor') return coreBlockEditor?.dispatch;
                    return null;
                }
            }
        },
        console: console
    };

    windowObj.window = windowObj;
    windowObj.globalThis = windowObj;

    const context = vm.createContext(windowObj);
    vm.runInContext(markupSource, context);
    vm.runInContext(source, context);

    return {
        context,
        coordinator: context.window.AlignSaveCoordinator,
        postedToIframe,
        subscribers,
        windowObj
    };
}

test('resolveActiveEntity: resolves template from core/editor without NaN', () => {
    const coreEditor = {
        select: {
            getCurrentPostId: () => 'align-child//404',
            getCurrentPostType: () => 'wp_template'
        }
    };

    const { coordinator } = createCoordinatorContext({
        url: 'http://localhost/wp-admin/site-editor.php?canvas=edit',
        coreEditor
    });

    const entity = coordinator.resolveActiveEntity();
    assert.equal(entity.kind, 'template');
    assert.equal(entity.postType, 'wp_template');
    assert.equal(entity.id, 'align-child//404');
    assert.equal(typeof entity.id, 'string');
    assert.equal(isNaN(Number(entity.id)), true);
    assert.equal(entity.isEmbedded, true);
});

test('resolveActiveEntity: resolves template part from core/edit-site', () => {
    const coreEditSite = {
        getEditedPostType: () => 'wp_template_part',
        getEditedPostId: () => 'tp_align-child//header'
    };

    const { coordinator } = createCoordinatorContext({
        url: 'http://localhost/wp-admin/site-editor.php',
        coreEditSite
    });

    const entity = coordinator.resolveActiveEntity();
    assert.equal(entity.kind, 'template_part');
    assert.equal(entity.postType, 'wp_template_part');
    assert.equal(entity.id, 'align-child//header');
    assert.equal(entity.isEmbedded, true);
});

test('resolveActiveEntity: resolves standard page from core/editor with numeric ID', () => {
    const coreEditor = {
        select: {
            getCurrentPostId: () => '42',
            getCurrentPostType: () => 'page',
            getEditedPostAttribute: (attr) => attr === 'meta' ? { _align_editor_mode: 'builder' } : null
        }
    };

    const { coordinator } = createCoordinatorContext({
        url: 'http://localhost/wp-admin/post.php?post=42&action=edit',
        coreEditor
    });

    const entity = coordinator.resolveActiveEntity();
    assert.equal(entity.kind, 'post');
    assert.equal(entity.postType, 'page');
    assert.equal(entity.id, 42);
    assert.equal(typeof entity.id, 'number');
    assert.equal(entity.isEmbedded, true);
});

test('resolveActiveEntity: parses Site Editor route p parameter for 404 template', () => {
    const { coordinator } = createCoordinatorContext({
        url: 'http://localhost/wp-admin/site-editor.php?canvas=edit&p=%2Fwp_template%2Falign-child%2F%2F404'
    });

    const entity = coordinator.resolveActiveEntity();
    assert.equal(entity.kind, 'template');
    assert.equal(entity.postType, 'wp_template');
    assert.equal(entity.id, 'align-child//404');
    assert.equal(entity.isEmbedded, true);
});

test('resolveActiveEntity: parses Site Editor route p parameter for template part', () => {
    const { coordinator } = createCoordinatorContext({
        url: 'http://localhost/wp-admin/site-editor.php?canvas=edit&p=/wp_template_part/align-child//footer'
    });

    const entity = coordinator.resolveActiveEntity();
    assert.equal(entity.kind, 'template_part');
    assert.equal(entity.postType, 'wp_template_part');
    assert.equal(entity.id, 'align-child//footer');
    assert.equal(entity.isEmbedded, true);
});

test('resolveActiveEntity: template in Gutenberg mode (no Builder iframe) is not embedded', () => {
    const coreEditSite = {
        getEditedPostType: () => 'wp_template',
        getEditedPostId: () => 'align-child//404'
    };
    const { coordinator } = createCoordinatorContext({ coreEditSite, builderMounted: false });

    const entity = coordinator.resolveActiveEntity();
    assert.equal(entity.kind, 'template');
    assert.equal(entity.id, 'align-child//404');
    assert.equal(entity.isEmbedded, false);
});

const PERSISTED_TEMPLATE = [
    '<!-- wp:template-part {"slug":"header-home","tagName":"header","theme":"align-child"} /-->',
    '<!-- wp:align/custom-html {"blockId":"align-keepme","enableShortcode":true} -->\n<p>Old</p>\n<!-- /wp:align/custom-html -->',
    '<!-- wp:post-content {"className":"article-body-content"} /-->',
    '<!-- wp:template-part {"slug":"footer-home","tagName":"footer","theme":"align-child"} /-->'
].join('\n\n');

function persistedRecordStore(content) {
    return { getEntityRecord: () => ({ content: { raw: content } }), getEditedEntityRecord: () => null };
}

test('buildBlockMarkup: keeps persisted header/footer, post-content attrs and blockId', () => {
    const { coordinator } = createCoordinatorContext({ coreData: { select: persistedRecordStore(PERSISTED_TEMPLATE), dispatch: {} } });
    const entity = { kind: 'template', postType: 'wp_template', id: 'align-child//404' };
    const payload = {
        html: '<div data-component-post-content="1">Original Content</div><p>404 Not Found</p>',
        css: 'h1 { color: red; }',
        js: 'console.log("404");'
    };

    const markup = coordinator.buildBlockMarkup(entity, payload);

    assert.match(markup, /^<!-- wp:template-part \{"slug":"header-home","tagName":"header","theme":"align-child"\} \/-->/);
    assert.match(markup, /<!-- wp:template-part \{"slug":"footer-home","tagName":"footer","theme":"align-child"\} \/-->$/);
    assert.doesNotMatch(markup, /"slug":"header"[,}]/);
    assert.match(markup, /<!-- wp:align\/custom-html \{"blockId":"align-keepme","enableShortcode":true,"styleId":"style-template-404","scriptId":"script-template-404"\} -->/);
    assert.match(markup, /<style id="style-template-404" type="text\/css">h1 { color: red; }<\/style>/);
    assert.match(markup, /<script id="script-template-404" type="text\/javascript">console\.log\("404"\);<\/script>/);
    assert.match(markup, /<!-- wp:post-content \{"className":"article-body-content"\} \/--><p>404 Not Found<\/p>/);
    assert.doesNotMatch(markup, /data-component-post-content/);
});

test('buildBlockMarkup: adds default header/footer only for new known templates', () => {
    const { coordinator } = createCoordinatorContext({ coreData: { select: persistedRecordStore(''), dispatch: {} } });
    const payload = { html: '<div data-component-post-content>post-content</div>', css: '', js: '' };

    const known = coordinator.buildBlockMarkup({ kind: 'template', postType: 'wp_template', id: 'align-child//404' }, payload);
    assert.match(known, /^<!-- wp:template-part \{"slug":"header","tagName":"header"\} \/-->/);
    assert.match(known, /<!-- wp:post-content \/-->/);
    assert.match(known, /<!-- wp:template-part \{"slug":"footer","tagName":"footer"\} \/-->$/);

    const custom = coordinator.buildBlockMarkup({ kind: 'template', postType: 'wp_template', id: 'align-child//landing' }, payload);
    assert.doesNotMatch(custom, /wp:template-part/);
});

test('buildBlockMarkup: headerless persisted template stays headerless', () => {
    const headerless = '<!-- wp:align/custom-html {"blockId":"align-x"} -->\n<p>Landing</p>\n<!-- /wp:align/custom-html -->';
    const { coordinator } = createCoordinatorContext({ coreData: { select: persistedRecordStore(headerless), dispatch: {} } });
    const markup = coordinator.buildBlockMarkup({ kind: 'template', postType: 'wp_template', id: 'align-child//page' }, { html: '<p>New</p>', css: '', js: '' });
    assert.doesNotMatch(markup, /wp:template-part/);
});

test('handleBuilderDirty: one entity edit with blocks, outside undo, no resetBlocks', () => {
    const recordedEdits = [];
    let resetCalls = 0;

    const coreData = {
        select: persistedRecordStore(PERSISTED_TEMPLATE),
        dispatch: {
            editEntityRecord: (kind, name, id, edits, options) => {
                recordedEdits.push({ kind, name, id, edits, options });
            }
        }
    };
    const coreBlockEditor = { dispatch: { resetBlocks: () => { resetCalls++; } } };

    const { coordinator } = createCoordinatorContext({
        url: 'http://localhost/wp-admin/site-editor.php?canvas=edit&p=%2Fwp_template%2Falign-child%2F%2F404',
        coreData,
        coreBlockEditor
    });

    const payload = { tp_id: 'align-child//404', html: '<p>Dirty Content</p>', css: '', js: '', _capturedVersion: 5 };
    coordinator.handleBuilderDirty(payload);

    assert.equal(recordedEdits.length, 1);
    assert.equal(recordedEdits[0].name, 'wp_template');
    assert.equal(recordedEdits[0].id, 'align-child//404');
    assert.match(recordedEdits[0].edits.content, /Dirty Content/);
    assert.match(recordedEdits[0].edits.content, /"slug":"header-home"/);
    assert.equal(recordedEdits[0].edits.blocks.length, 1);
    assert.equal(recordedEdits[0].options.undoIgnore, true);
    assert.equal(resetCalls, 0);
    assert.equal(coordinator._lastCapturedVersion, 5);
});

test('handleBuilderDirty: skips store update when markup is unchanged', () => {
    let editCalls = 0;
    let editedContent = null;
    const coreData = {
        select: {
            getEntityRecord: () => ({ content: { raw: PERSISTED_TEMPLATE } }),
            getEditedEntityRecord: () => ({ content: editedContent })
        },
        dispatch: { editEntityRecord: (kind, name, id, edits) => { editCalls++; editedContent = edits.content; } }
    };
    const { coordinator } = createCoordinatorContext({
        url: 'http://localhost/wp-admin/site-editor.php?canvas=edit&p=%2Fwp_template%2Falign-child%2F%2F404',
        coreData
    });

    const payload = { tp_id: 'align-child//404', html: '<p>Same</p>', css: '', js: '' };
    coordinator.handleBuilderDirty(payload);
    coordinator.handleBuilderDirty(payload);
    assert.equal(editCalls, 1);
});

test('triggerNativeSave: triggers native save action without click hijacking', async () => {
    let savedKind = null;
    let savedName = null;
    let savedId = null;
    let savedOptions = null;

    const coreData = {
        select: {},
        dispatch: {
            saveEditedEntityRecord: async (kind, name, id, options) => {
                savedKind = kind;
                savedName = name;
                savedId = id;
                savedOptions = options;
                return { id, status: 'publish' };
            }
        }
    };

    const { coordinator } = createCoordinatorContext({
        url: 'http://localhost/wp-admin/site-editor.php?canvas=edit&p=%2Fwp_template%2Falign-child%2F%2F404',
        coreData
    });

    coordinator.triggerNativeSave();

    assert.equal(savedKind, 'postType');
    assert.equal(savedName, 'wp_template');
    assert.equal(savedId, 'align-child//404');
    assert.equal(savedOptions?.throwOnError, true);
});

test('setupStoreSubscription: tracks native WordPress save transition and notifies iframe', () => {
    let isSaving = false;
    const coreEditor = {
        select: {
            getCurrentPostId: () => 'align-child//404',
            getCurrentPostType: () => 'wp_template',
            isSavingPost: () => isSaving,
            isSavingNonPostEntityChanges: () => isSaving,
            getLastPostSaveError: () => null
        }
    };
    const coreData = {
        select: {
            isSavingEntityRecord: () => isSaving,
            getLastEntitySaveError: () => null
        }
    };

    const { coordinator, postedToIframe, subscribers } = createCoordinatorContext({
        url: 'http://localhost/wp-admin/site-editor.php?canvas=edit&p=%2Fwp_template%2Falign-child%2F%2F404',
        coreEditor,
        coreData
    });

    coordinator._lastCapturedVersion = 7;
    assert.equal(subscribers.length, 1, 'Store subscription registered');

    // 1. Trigger transition: Saving starts
    isSaving = true;
    subscribers[0]();

    assert.equal(postedToIframe.length, 1);
    assert.equal(postedToIframe[0].msg.type, 'ALIGN_COORDINATOR_SAVING_STATE');
    assert.equal(postedToIframe[0].msg.saving, true);

    // 2. Trigger transition: Saving completes
    isSaving = false;
    subscribers[0]();

    assert.equal(postedToIframe.length, 2);
    assert.equal(postedToIframe[1].msg.type, 'ALIGN_COORDINATOR_SAVING_STATE');
    assert.equal(postedToIframe[1].msg.saving, false);
});
