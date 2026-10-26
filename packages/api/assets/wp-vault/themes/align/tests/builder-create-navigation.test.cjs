const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../core/builder/js/modules/wp-items-crud.js'), 'utf8');

async function createItem({
    type = 'page',
    title = 'test',
    slug = 'test',
    embedded = true,
    current = 'post_id=582&embedded=1',
    newPostId = 583,
    tpId = null,
    editorUrl = null,
    unsaved = false,
    confirm = true,
    success = true,
    config = {}
} = {}) {
    const navigations = [];
    const postedMessages = [];
    let requests = 0;

    const location = (url, target) => ({
        search: new URL(url).search,
        get href() { return url; },
        set href(value) { navigations.push({ target, url: value }); }
    });

    const window = {
        location: location('http://palmetto-stg.local/subsite/wp-admin/admin.php?page=align-builder&' + current, 'iframe'),
        VvvebWpConfig: { restCreateItemUrl: '/create-item', builderSlug: 'align-builder', ...config },
        Vvveb: {
            Undo: { undoIndex: unsaved ? 3 : 0 },
            FileManager: { pages: {} }
        },
        confirm: () => confirm
    };

    const parentWindow = {
        location: location('http://palmetto-stg.local/subsite/wp-admin/post.php?post=582&action=edit', 'parent'),
        postMessage: (msg, origin) => { postedMessages.push(msg); }
    };
    window.parent = embedded ? parentWindow : window;

    const createMockElement = (props = {}) => ({
        addEventListener: () => {},
        removeEventListener: () => {},
        style: {},
        focus: () => {},
        appendChild: () => {},
        ...props
    });

    const domElements = {
        'align-item-title': createMockElement({ value: title }),
        'align-item-slug': createMockElement({ value: slug }),
        'align-create-item-modal': createMockElement(),
        'align-modal-title': createMockElement({ textContent: '' }),
        'align-modal-confirm': createMockElement({ textContent: 'Create', disabled: false }),
        'align-modal-cancel': createMockElement(),
        'new-item-toggle': null,
        'add-item-menu': null
    };

    let confirmHandler = null;
    domElements['align-modal-confirm'].addEventListener = (event, handler) => {
        if (event === 'click') confirmHandler = handler;
    };

    let addBtnHandler = null;
    const addPageBtn = {
        addEventListener: (event, handler) => {
            if (event === 'click') addBtnHandler = handler;
        }
    };

    vm.runInNewContext(source, {
        window, URL, URLSearchParams, setTimeout, clearTimeout,
        document: {
            getElementById: id => {
                if (id === 'add-wp-page-btn' && type === 'page') return addPageBtn;
                if (id === 'add-wp-template-part-btn' && type === 'template_part') return addPageBtn;
                return domElements[id] || null;
            },
            querySelector: () => null,
            querySelectorAll: () => [],
            body: { appendChild() {} }
        },
        console: { log() {}, warn() {}, error() {} },
        alert() {},
        confirm: () => confirm,
        fetch: async () => {
            requests++;
            return {
                json: async () => {
                    if (!success) return { status: 'error', message: 'Creation failed' };
                    return {
                        status: 'success',
                        post_id: newPostId,
                        tp_id: tpId,
                        type: type,
                        slug: slug,
                        title: title,
                        editor_url: editorUrl || ('http://palmetto-stg.local/subsite/wp-admin/admin.php?page=align-builder&post_id=' + newPostId)
                    };
                }
            };
        }
    });

    window.AlignBuilderModules.WpItemsCrud.init();

    // Trigger open create modal
    if (addBtnHandler) addBtnHandler();
    domElements['align-item-title'].value = title;
    domElements['align-item-slug'].value = slug;

    // Trigger confirm button
    if (confirmHandler) confirmHandler();

    await new Promise(resolve => setTimeout(resolve, 400));
    return { navigations, postedMessages, requests };
}

test('embedded page creation navigates whole parent editor to post.php?post={ID}&action=edit', async () => {
    const { navigations, postedMessages, requests } = await createItem({ type: 'page', newPostId: 583, embedded: true });
    assert.equal(requests, 1);
    assert.deepEqual(navigations, [{ target: 'parent', url: 'http://palmetto-stg.local/subsite/wp-admin/post.php?post=583&action=edit' }]);
    assert.equal(postedMessages[0]?.type, 'ALIGN_NAVIGATE');
    assert.equal(postedMessages[0]?.url, 'http://palmetto-stg.local/subsite/wp-admin/post.php?post=583&action=edit');
});

test('embedded template part creation navigates parent window to editor_url', async () => {
    const customEditorUrl = 'http://palmetto-stg.local/subsite/wp-admin/admin.php?page=align-builder&tp_id=tp_theme%2F%2Fheader';
    const { navigations } = await createItem({
        type: 'template_part',
        tpId: 'tp_theme//header',
        editorUrl: customEditorUrl,
        embedded: true
    });
    assert.deepEqual(navigations, [{ target: 'parent', url: customEditorUrl }]);
});

test('standalone page creation navigates to Gutenberg editor post.php', async () => {
    const { navigations } = await createItem({ type: 'page', newPostId: 583, embedded: false });
    assert.deepEqual(navigations, [{ target: 'iframe', url: 'http://palmetto-stg.local/subsite/wp-admin/post.php?post=583&action=edit' }]);
});

test('unsaved changes prompt prevents page creation and navigation when cancelled', async () => {
    const { navigations, requests } = await createItem({ unsaved: true, confirm: false });
    assert.equal(requests, 0);
    assert.deepEqual(navigations, []);
});

test('failed creation response does not trigger redirect', async () => {
    const { navigations, requests } = await createItem({ success: false });
    assert.equal(requests, 1);
    assert.deepEqual(navigations, []);
});
