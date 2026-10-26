/**
 * Test-only stand-ins for WordPress block parsing/serialization and the <template> element.
 */

// Minimal stand-in for @wordpress/block-serialization-default-parser (block comments + freeform text).
function fakeParse(content) {
    const root = { innerBlocks: [] };
    const stack = [root];
    const re = /<!--\s+(\/)?wp:([a-z0-9\/-]+)\s*(\{[\s\S]*?\})?\s*(\/)?-->/g;
    let last = 0;
    let match;
    const pushText = (text) => {
        if (!text) return;
        if (stack.length === 1) root.innerBlocks.push({ blockName: null, attrs: {}, innerBlocks: [], innerHTML: text });
        else stack[stack.length - 1].innerHTML += text;
    };
    while ((match = re.exec(content))) {
        pushText(content.slice(last, match.index));
        last = re.lastIndex;
        const name = match[2].includes('/') ? match[2] : 'core/' + match[2];
        if (match[1]) { stack.pop(); continue; }
        const block = { blockName: name, attrs: match[3] ? JSON.parse(match[3]) : {}, innerBlocks: [], innerHTML: '' };
        stack[stack.length - 1].innerBlocks.push(block);
        if (!match[4]) stack.push(block);
    }
    pushText(content.slice(last));
    return root.innerBlocks;
}

function fakeSerializeRawBlock(block) {
    const name = block.blockName.replace(/^core\//, '');
    const attrs = Object.keys(block.attrs || {}).length ? JSON.stringify(block.attrs) + ' ' : '';
    return `<!-- wp:${name} ${attrs}/-->`;
}

// Minimal <template> element: replaces [data-component-post-content] placeholder divs with comments.
function fakeCreateElement(tag) {
    if (tag !== 'template') throw new Error('Unsupported element in test: ' + tag);
    let html = '';
    const placeholderRe = /<div\b[^>]*\bdata-component-post-content\b[^>]*>[\s\S]*?<\/div>/gi;
    return {
        set innerHTML(value) { html = value; },
        get innerHTML() { return html; },
        content: {
            querySelectorAll: () => (html.match(placeholderRe) || []).map((outer) => ({
                replaceWith: (comment) => { html = html.replace(outer, '<!--' + comment.data + '-->'); }
            }))
        }
    };
}

module.exports = { fakeParse, fakeSerializeRawBlock, fakeCreateElement };
