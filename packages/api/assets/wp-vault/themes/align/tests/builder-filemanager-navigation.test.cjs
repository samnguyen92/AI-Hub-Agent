const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../core/builder/js/modules/wp-filemanager.js'), 'utf8');

async function navigateTreeItem({
    pageName = 'page_583',
    pageData = { postId: 583, title: 'test', folder: 'Pages' },
    embedded = true,
    current = 'post_id=582&embedded=1',
    parentPostId = '582',
    unsaved = false,
    confirm = true,
    config = {},
    parentQuery = ''
} = {}) {
    const navigations = [];
    const postedMessages = [];

    const location = (url, target) => ({
        search: new URL(url).search,
        pathname: new URL(url).pathname,
        get href() { return url; },
        set href(value) { navigations.push({ target, url: value }); }
    });

    const window = {
        location: location('http://palmetto-stg.local/subsite/wp-admin/admin.php?page=align-builder&' + current, 'iframe'),
        VvvebWpConfig: { restPagesUrl: '/pages', builderSlug: 'align-builder', ...config },
        Vvveb: {
            Undo: { undoIndex: unsaved ? 3 : 0 },
            FileManager: {
                pages: {
                    [pageName]: pageData,
                    page_582: { postId: 582, title: '404', folder: 'Pages' }
                }
            }
        },
        addEventListener: () => {},
        removeEventListener: () => {},
        confirm: () => confirm
    };

    const parentUrl = 'http://palmetto-stg.local/subsite/wp-admin/post.php?post=' + parentPostId + '&action=edit' + (parentQuery ? ('&' + parentQuery) : '');
    const parentWindow = {
        location: location(parentUrl, 'parent'),
        postMessage: (msg, origin) => { postedMessages.push(msg); }
    };
    window.parent = embedded ? parentWindow : window;

    const clickHandlers = [];
    const mockLi = {
        dataset: { page: pageName },
        closest: selector => {
            if (selector === '#filemanager .tree li[data-page]') return mockLi;
            return null; // not delete, rename, etc.
        }
    };

    vm.runInNewContext(source, {
        window, URL, URLSearchParams, setTimeout, clearTimeout,
        document: {
            addEventListener: (event, handler) => {
                if (event === 'click') clickHandlers.push(handler);
            },
            getElementById: () => null,
            querySelector: () => null,
            querySelectorAll: () => []
        },
        console: { log() {}, warn() {}, error() {} },
        alert() {},
        confirm: () => confirm,
        fetch: async () => ({ json: async () => [] })
    });

    window.AlignBuilderModules.WpFilemanager.init();

    // Trigger click on tree item
    let prevented = false;
    const clickEvent = {
        target: mockLi,
        preventDefault: () => { prevented = true; },
        stopImmediatePropagation: () => {}
    };
    clickHandlers.forEach(h => h(clickEvent));

    return { navigations, postedMessages, prevented };
}

test('embedded tree click on a different page navigates parent window to post.php?post={ID}&action=edit', async () => {
    const { navigations, postedMessages, prevented } = await navigateTreeItem({
        pageName: 'page_583',
        pageData: { postId: 583, title: 'test', folder: 'Pages' },
        embedded: true,
        parentPostId: '582'
    });

    assert.equal(prevented, true);
    assert.deepEqual(navigations, [{ target: 'parent', url: 'http://palmetto-stg.local/subsite/wp-admin/post.php?post=583&action=edit' }]);
    assert.equal(postedMessages[0]?.type, 'ALIGN_NAVIGATE');
    assert.equal(postedMessages[0]?.url, 'http://palmetto-stg.local/subsite/wp-admin/post.php?post=583&action=edit');
});

test('embedded tree click on the currently active page does not navigate', async () => {
    const { navigations, prevented } = await navigateTreeItem({
        pageName: 'page_582',
        pageData: { postId: 582, title: '404', folder: 'Pages' },
        embedded: true,
        parentPostId: '582'
    });

    assert.equal(prevented, false);
    assert.deepEqual(navigations, []);
});

test('standalone tree click on a page navigates to Gutenberg editor post.php', async () => {
    const { navigations, prevented } = await navigateTreeItem({
        pageName: 'page_583',
        pageData: { postId: 583, title: 'test', folder: 'Pages' },
        embedded: false
    });

    assert.equal(prevented, true);
    assert.deepEqual(navigations, [{ target: 'iframe', url: 'http://palmetto-stg.local/subsite/wp-admin/post.php?post=583&action=edit' }]);
});

test('tree click on a template part navigates to builder with tp_id', async () => {
    const { navigations, prevented } = await navigateTreeItem({
        pageName: 'tp_header',
        pageData: { tpId: 'tp_theme//header', title: 'Header', folder: 'Template Parts' },
        embedded: false
    });

    assert.equal(prevented, true);
    assert.deepEqual(navigations, [{ target: 'iframe', url: 'http://palmetto-stg.local/subsite/wp-admin/admin.php?page=align-builder&tp_id=tp_theme%2F%2Fheader' }]);
});

test('embedded tree click on the currently active page with lang param does not navigate (loop prevention)', async () => {
    const { navigations, prevented } = await navigateTreeItem({
        pageName: 'page_582',
        pageData: { postId: 582, title: '404', folder: 'Pages', lang: 'en' },
        embedded: true,
        parentPostId: '582',
        parentQuery: 'lang=en'
    });

    assert.equal(prevented, false);
    assert.deepEqual(navigations, []);
});

test('unsaved changes prompt on tree click stops navigation if cancelled', async () => {
    const { navigations, prevented } = await navigateTreeItem({
        pageName: 'page_583',
        pageData: { postId: 583, title: 'test', folder: 'Pages' },
        unsaved: true,
        confirm: false
    });

    assert.equal(prevented, true);
    assert.deepEqual(navigations, []);
});
