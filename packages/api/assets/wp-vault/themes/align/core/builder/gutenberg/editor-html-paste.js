/** Convert pasted HTML through the configured Align service before inserting blocks. */
(function (wp) {
    'use strict';
    const editorStore = 'core/block-editor';
    const noticeId = 'align-html-paste';
    const documents = new WeakSet();
    const frames = new WeakSet();
    let busy = false;

    function notice(status, message) {
        wp.data.dispatch('core/notices').createNotice(status, message, { id: noticeId, isDismissible: true });
    }

    function context() {
        const editor = wp.data.select('core/editor');
        const site = wp.data.select('core/edit-site');
        const id = editor?.getCurrentPostId?.() || site?.getEditedPostId?.() || 0;
        const type = editor?.getCurrentPostType?.() || site?.getEditedPostType?.();
        return { id, type };
    }

    function clipboardHTML(event) {
        const clipboard = event.clipboardData;
        if (!clipboard || clipboard.files.length) return '';
        const plain = clipboard.getData('text/plain').trim();
        const rich = clipboard.getData('text/html');
        // Code copied from a text editor can also include syntax-highlighted clipboard HTML.
        const html = plain.startsWith('<') ? plain : rich;
        if (!html) return '';
        const parsed = new DOMParser().parseFromString(html, 'text/html');
        const comments = parsed.createTreeWalker(parsed, NodeFilter.SHOW_COMMENT);
        while (comments.nextNode()) {
            if (comments.currentNode.data.trim().startsWith('wp:')) return '';
        }
        return parsed.body.children.length ? html : '';
    }

    function destination(target) {
        const store = wp.data.select(editorStore);
        const node = target.closest('[data-block]');
        const id = node?.getAttribute('data-block');
        const block = id ? store.getBlock(id) : null;
        const selected = store.getMultiSelectedBlockClientIds();
        const ids = selected.length ? selected : (id ? [id] : []);
        const point = store.getBlockInsertionPoint();
        const root = block ? store.getBlockRootClientId(id) : point.rootClientId;
        if (store.getTemplateLock(root) || (block && store.getBlockEditingMode(id) !== 'default')) {
            throw new Error('This block is locked. Paste into an editable area.');
        }
        const plan = { ids, root, index: point.index, snapshot: JSON.stringify(ids.map(key => store.getBlock(key))), mode: 'after' };
        if (selected.length) {
            if (!store.canRemoveBlocks(ids)) throw new Error('The selected blocks cannot be replaced.');
            plan.mode = 'replace';
        } else if (block) {
            const editable = target.closest('[contenteditable="true"][data-wp-block-attribute-key="content"]');
            const textBlock = ['core/paragraph', 'core/heading'].includes(block.name) ||
                (block.name === 'align/element' && block.attributes.type === 'text' && ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'].includes(block.attributes.tag));
            if (textBlock && !block.innerBlocks.length && editable && store.canRemoveBlocks([id])) {
                const selection = editable.ownerDocument.getSelection();
                if (selection?.rangeCount && editable.contains(selection.anchorNode) && editable.contains(selection.focusNode)) {
                    const value = wp.richText.create({ element: editable, range: selection.getRangeAt(0) });
                    plan.before = wp.richText.toHTMLString({ value: wp.richText.slice(value, 0, value.start) });
                    plan.after = wp.richText.toHTMLString({ value: wp.richText.slice(value, value.end, value.text.length) });
                    plan.block = block;
                    plan.mode = 'split';
                }
            }
        }
        return plan;
    }

    function insert(markup, plan, originalContext) {
        const store = wp.data.select(editorStore);
        const current = context();
        if (current.id !== originalContext.id || current.type !== originalContext.type ||
            JSON.stringify(plan.ids.map(id => store.getBlock(id))) !== plan.snapshot) {
            throw new Error('The paste location changed during conversion. Please paste again.');
        }
        const blocks = wp.blocks.parse(markup);
        function validate(items) {
            return items.every(block => wp.blocks.getBlockType(block.name) &&
                !['core/missing', 'core/freeform'].includes(block.name) && block.isValid !== false && validate(block.innerBlocks));
        }
        if (!blocks.length || !validate(blocks)) throw new Error('The converter returned unsupported or invalid blocks.');
        if (store.getTemplateLock(plan.root) || !blocks.every(block => store.canInsertBlockType(block.name, plan.root))) {
            throw new Error('Converted blocks are not allowed at this paste location.');
        }
        const actions = wp.data.dispatch(editorStore);
        if (plan.mode === 'split' || plan.mode === 'replace') {
            if (!store.canRemoveBlocks(plan.ids)) throw new Error('The paste location is now locked.');
            const replacement = [...blocks];
            if (plan.mode === 'split') {
                if (plan.before) replacement.unshift(wp.blocks.cloneBlock(plan.block, { content: plan.before }));
                if (plan.after) {
                    const attrs = { ...plan.block.attributes, content: plan.after };
                    if (plan.before) {
                        delete attrs.anchor;
                        delete attrs.elementId;
                        delete attrs.dataEditId;
                    }
                    replacement.push(wp.blocks.createBlock(plan.block.name, attrs));
                }
            }
            actions.replaceBlocks(plan.ids, replacement);
        } else {
            const index = plan.ids.length ? store.getBlockIndex(plan.ids[0], plan.root) + 1 : plan.index;
            actions.insertBlocks(blocks, index, plan.root);
        }
        const count = blocks.length;
        console.log('[Align Paste] Converted blocks inserted', { blocks: count, mode: plan.mode, isFallback: Boolean(isFallback) });
        if (isFallback) {
            notice('warning', 'Converter API offline. HTML safely inserted as Custom HTML block.');
        } else {
            notice('success', 'HTML converted and inserted. Use Undo to revert.');
        }
    }

    async function paste(event) {
        const target = event.target?.nodeType === 1 ? event.target : event.target?.parentElement;
        // Only intercept visual canvas pastes; preserve title, inspector, code, and HTML editing.
        if (!target?.closest('.block-editor-block-list__layout') ||
            target.closest('textarea, input, pre, code, .block-editor-plain-text') ||
            !target.closest('[contenteditable="true"]')) return;
        let html;
        try {
            html = clipboardHTML(event);
        } catch (error) {
            console.error('[Align Paste] Clipboard parsing failed', error);
            return;
        }
        if (!html) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        if (busy) {
            notice('warning', 'HTML conversion is already running. Please wait before pasting again.');
            return;
        }
        busy = true;
        try {
            const originalContext = context();
            if (!originalContext.type) throw new Error('The editor is not ready. Please paste again.');
            const plan = destination(target);
            notice('info', 'Converting pasted HTML…');
            console.log('[Align Paste] Conversion requested', { htmlLength: html.length, postType: originalContext.type, postId: originalContext.id });
            const data = await wp.apiFetch({
                path: '/align/v1/convert-pasted-html', method: 'POST',
                data: { html, post_id: Number.isInteger(Number(originalContext.id)) ? Number(originalContext.id) : 0,
                    post_type: originalContext.type, word_mode: html.includes('urn:schemas-microsoft-com:office:word') }
            });
            if (!data || typeof data.markup !== 'string') throw new Error('The converter returned an invalid response.');
            insert(data.markup, plan, originalContext, Boolean(data.fallback));
        } catch (error) {
            console.error('[Align Paste] Conversion failed', error);
            notice('error', 'HTML was not inserted. ' + (error.message || 'Please try again.'));
        } finally {
            busy = false;
        }
    }

    function attach(doc) {
        if (!doc || documents.has(doc)) return;
        documents.add(doc);
        doc.addEventListener('paste', paste, true);
    }

    function scan() {
        try {
            attach(document);
            document.querySelectorAll('iframe[name="editor-canvas"], iframe.editor-canvas__iframe').forEach(frame => {
                if (!frames.has(frame)) {
                    frames.add(frame);
                    frame.addEventListener('load', () => scan());
                }
                attach(frame.contentDocument);
            });
        } catch (error) {
            console.error('[Align Paste] Editor canvas detection failed', error);
        }
    }
    wp.domReady(() => {
        scan();
        new MutationObserver(scan).observe(document.body, { childList: true, subtree: true });
    });
})(window.wp);
