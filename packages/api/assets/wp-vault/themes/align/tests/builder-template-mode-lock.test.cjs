const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { fakeParse, fakeSerializeRawBlock } = require('./helpers/fake-blocks.cjs');

const markupSource = fs.readFileSync(path.join(__dirname, '../core/builder/gutenberg/align-template-markup.js'), 'utf8');
const lockSource = fs.readFileSync(path.join(__dirname, '../core/builder/gutenberg/align-template-mode-lock.js'), 'utf8');

const HEADER = '<!-- wp:template-part {"slug":"header-home","tagName":"header","theme":"align-child"} /-->';
const FOOTER = '<!-- wp:template-part {"slug":"footer-home","tagName":"footer","theme":"align-child"} /-->';
const BUILDER_BLOCK = '<!-- wp:align/custom-html {"blockId":"align-x"} -->\n<p>Hero</p><!-- wp:post-content {"className":"article-body-content"} /-->\n<!-- /wp:align/custom-html -->';

function createLockContext({ record = { content: { raw: '' } }, entity = { kind: 'template', postType: 'wp_template', id: 'align-child//home' } } = {}) {
    const calls = { edits: [], saves: [] };
    const windowObj = {
        console: { log: () => {}, warn: () => {}, error: () => {} },
        AlignSaveCoordinator: { resolveActiveEntity: () => entity },
        wp: {
            blockSerializationDefaultParser: { parse: fakeParse },
            blocks: { serializeRawBlock: fakeSerializeRawBlock, parse: (markup) => [{ markup }] },
            data: {
                select: (store) => (store === 'core' ? { getEntityRecord: () => record } : null),
                dispatch: (store) => (store === 'core' ? {
                    editEntityRecord: (kind, name, id, edits) => { calls.edits.push({ kind, name, id, edits }); },
                    saveEditedEntityRecord: async (kind, name, id, options) => { calls.saves.push({ kind, name, id, options }); }
                } : null)
            }
        }
    };
    windowObj.window = windowObj;
    const context = vm.createContext(windowObj);
    vm.runInContext(markupSource, context);
    vm.runInContext(lockSource, context);
    return { lock: context.window.AlignTemplateModeLock, calls };
}

test('detectMode: structure-only templates are unlocked', () => {
    const { lock } = createLockContext();
    assert.equal(lock.detectMode(''), null);
    assert.equal(lock.detectMode(HEADER + '\n\n<!-- wp:post-content /-->\n\n' + FOOTER), null);
    assert.equal(lock.detectMode(HEADER + '\n<!-- wp:align/custom-html {"blockId":"a"} -->\n\n<!-- /wp:align/custom-html -->\n' + FOOTER), null);
});

test('detectMode: one Builder block locks to builder (align/custom-html or legacy core/html)', () => {
    const { lock } = createLockContext();
    assert.equal(lock.detectMode(HEADER + '\n\n' + BUILDER_BLOCK + '\n\n' + FOOTER), 'builder');
    assert.equal(lock.detectMode('<!-- wp:html -->\n<p>Legacy</p>\n<!-- /wp:html -->'), 'builder');
});

test('detectMode: native or mixed blocks lock to gutenberg', () => {
    const { lock } = createLockContext();
    assert.equal(lock.detectMode(HEADER + '\n<!-- wp:shortcode -->\n[x]\n<!-- /wp:shortcode -->\n' + FOOTER), 'gutenberg');
    const mixed = HEADER + '\n' + BUILDER_BLOCK + '\n<!-- wp:group -->\n<div></div>\n<!-- /wp:group -->\n' + BUILDER_BLOCK + '\n' + FOOTER;
    assert.equal(lock.detectMode(mixed), 'gutenberg');
});

test('getLockedMode: reads saved content and stays unlocked while the record is unresolved', () => {
    assert.equal(createLockContext({ record: { content: { raw: HEADER + '\n' + BUILDER_BLOCK + '\n' + FOOTER } } }).lock.getLockedMode('wp_template'), 'builder');
    assert.equal(createLockContext({ record: undefined }).lock.getLockedMode('wp_template'), null);
    assert.equal(createLockContext({ record: { content: { raw: BUILDER_BLOCK } } }).lock.getLockedMode('page'), null);
});

test('clearForSwitch: keeps header/footer and post-content, saves natively', async () => {
    const { lock, calls } = createLockContext({ record: { content: { raw: HEADER + '\n\n' + BUILDER_BLOCK + '\n\n' + FOOTER } } });
    const content = await lock.clearForSwitch('wp_template');

    assert.equal(content, HEADER + '\n\n<!-- wp:post-content {"className":"article-body-content"} /-->\n\n' + FOOTER);
    assert.equal(calls.edits.length, 1);
    assert.equal(calls.edits[0].id, 'align-child//home');
    assert.equal(calls.edits[0].edits.content, content);
    assert.equal(calls.saves.length, 1);
    assert.equal(calls.saves[0].options.throwOnError, true);
});

test('clearForSwitch: template part without structure becomes empty', async () => {
    const entity = { kind: 'template_part', postType: 'wp_template_part', id: 'align-child//header' };
    const { lock } = createLockContext({ entity, record: { content: { raw: '<!-- wp:paragraph -->\n<p>Logo</p>\n<!-- /wp:paragraph -->' } } });
    assert.equal(await lock.clearForSwitch('wp_template_part'), '');
});
