const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../core/builder/js/modules/wp-items-crud.js'), 'utf8');

async function deleteItem({ embedded = true, current = 'post_id=1574', deleted = { postId: 1574 }, remaining = true, success = true, confirm = true, config = {} } = {}) {
    let click;
    let requests = 0;
    const navigations = [];
    const location = (url, target) => ({
        search: new URL(url).search,
        get href() { return url; },
        set href(value) { navigations.push({ target, url: value }); }
    });
    const window = {
        location: location('http://palmetto-stg.local/subsite/wp-admin/admin.php?page=align-builder&' + current, 'iframe'),
        VvvebWpConfig: { restDeleteItemUrl: '/delete-item', ...config },
        Vvveb: { FileManager: { pages: {
            deleted: { folder: 'Pages', ...deleted },
            block: { folder: 'Reusable Blocks', postId: 80, isReusable: true },
            part: { folder: 'Template Parts', tpId: 'tp_theme//header' },
            ...(remaining ? { next: { folder: 'Pages', postId: 42, title: '404', lang: 'en' } } : {})
        } } }
    };
    window.parent = embedded ? { location: location('http://palmetto-stg.local/subsite/wp-admin/post.php?post=1574&action=edit', 'parent') } : window;
    const li = {
        setAttribute() {}, getAttribute: () => 'deleted',
        querySelector: selector => selector === '.delete' ? { addEventListener: (_, handler) => { click = handler; } } : { textContent: 'Hello word' }
    };
    vm.runInNewContext(source, {
        window, URL, URLSearchParams,
        document: { getElementById: () => null, querySelector: () => null, querySelectorAll: () => [li] },
        console: { log() {}, warn() {}, error() {} }, alert() {}, confirm: () => confirm,
        fetch: async () => { requests++; return { json: async () => ({ status: success ? 'success' : 'error' }) }; }
    });
    window.AlignBuilderModules.WpItemsCrud.init();
    click({ stopPropagation() {}, preventDefault() {} });
    await new Promise(resolve => setImmediate(resolve));
    return { navigations, requests };
}

test('active embedded deletion navigates the whole editor to the remaining page ID', async () => {
    const { navigations } = await deleteItem();
    assert.deepEqual(navigations, [{ target: 'parent', url: 'http://palmetto-stg.local/subsite/wp-admin/post.php?post=42&action=edit' }]);
});

test('last page deletion returns to Pages, skipping reusable blocks and template parts', async () => {
    const { navigations } = await deleteItem({ remaining: false });
    assert.deepEqual(navigations, [{ target: 'parent', url: 'http://palmetto-stg.local/subsite/wp-admin/edit.php?post_type=page' }]);
});

test('inactive deletion refreshes only the current iframe and preserves embedded parameters', async () => {
    const { navigations } = await deleteItem({ deleted: { postId: 999 }, current: 'post_id=1574&embedded=1&lang=en' });
    assert.equal(navigations[0].target, 'iframe');
    assert.match(navigations[0].url, /post_id=1574&embedded=1&lang=en$/);
});

test('standalone deletion keeps the Builder and selects an explicit surviving page', async () => {
    const { navigations } = await deleteItem({ embedded: false });
    assert.deepEqual(navigations, [{ target: 'iframe', url: 'http://palmetto-stg.local/subsite/wp-admin/admin.php?page=align-builder&post_id=42&lang=en' }]);
});

test('parent ID is recognized when the iframe has navigated away from its original page', async () => {
    const { navigations } = await deleteItem({ current: 'post_id=42&embedded=1' });
    assert.equal(navigations[0].target, 'parent');
});

test('config-only active ID and active template deletion also exit the stale editor', async () => {
    for (const options of [
        { current: 'embedded=1', config: { activePostId: 1574 } },
        { current: 'tp_id=tp_theme%2F%2Fheader', deleted: { tpId: 'tp_theme//header' } }
    ]) {
        assert.equal((await deleteItem(options)).navigations[0].target, 'parent');
    }
});

test('failed or cancelled deletion never redirects', async () => {
    assert.deepEqual((await deleteItem({ success: false })).navigations, []);
    const cancelled = await deleteItem({ confirm: false });
    assert.deepEqual(cancelled.navigations, []);
    assert.equal(cancelled.requests, 0);
});
