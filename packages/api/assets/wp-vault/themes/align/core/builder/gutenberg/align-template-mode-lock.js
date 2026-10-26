/**
 * Align Theme Template Editor Mode Lock
 * Locks wp_template / wp_template_part to the editor that produced their saved content,
 * like the page/post lock (_align_editor_locked) but derived from content, because theme-file
 * templates have no database row (and no meta) until their first save.
 * Detection rules mirror Align_Builder_Template_Structure::detect_mode() (PHP).
 *
 * @package Align
 */

(function() {
    'use strict';

    const STRUCTURAL_BLOCKS = ['core/template-part', 'core/post-content'];
    const BUILDER_BLOCKS = ['align/custom-html', 'core/html'];

    window.AlignTemplateModeLock = {
        _memo: { content: null, mode: null },

        /** Active template entity matching the given post type, or null. */
        getEntity: function(postType) {
            const entity = window.AlignSaveCoordinator?.resolveActiveEntity?.();
            return (entity && entity.postType === postType && entity.id) ? entity : null;
        },

        /** Last saved content from core-data; null while the record is not resolved yet. */
        getPersistedContent: function(entity) {
            const record = window.wp?.data?.select?.('core')?.getEntityRecord?.('postType', entity.postType, entity.id);
            if (!record) return null;
            return record.content?.raw ?? (typeof record.content === 'string' ? record.content : '');
        },

        /** 'builder' | 'gutenberg' | null (unlocked: only header/footer/post-content structure). */
        detectMode: function(content) {
            if (this._memo.content === content) return this._memo.mode;

            const blocks = window.AlignTemplateMarkup.parseTopLevel(content || '').filter(function(block) {
                return STRUCTURAL_BLOCKS.indexOf(block.blockName) === -1;
            });
            let mode = 'gutenberg';
            if (!blocks.length) {
                mode = null;
            } else if (blocks.length === 1 && BUILDER_BLOCKS.indexOf(blocks[0].blockName) !== -1) {
                const isEmpty = (blocks[0].innerHTML || '').trim() === '' && !(blocks[0].innerBlocks || []).length;
                mode = isEmpty ? null : 'builder';
            }

            this._memo = { content: content, mode: mode };
            return mode;
        },

        getLockedMode: function(postType) {
            try {
                const entity = this.getEntity(postType);
                if (!entity) return null;
                const content = this.getPersistedContent(entity);
                return content === null ? null : this.detectMode(content);
            } catch (err) {
                console.warn('[Align UI] Could not resolve template lock state:', err);
                return null;
            }
        },

        /** Structure kept on a mode switch: leading parts + post-content block + trailing parts. */
        buildClearedContent: function(existing) {
            const markup = window.AlignTemplateMarkup;
            const wrappers = markup.getWrappers(existing);
            const postContent = markup.findBlock(existing, 'core/post-content');
            const middle = postContent ? [markup.serializeRaw(postContent)] : [];
            return wrappers.leading.concat(middle, wrappers.trailing).join('\n\n');
        },

        /** Clear the template's own content (keeping its structure) and save it natively. */
        clearForSwitch: async function(postType) {
            const entity = this.getEntity(postType);
            if (!entity) throw new Error('No active template entity to switch.');

            const existing = this.getPersistedContent(entity) || '';
            const content = this.buildClearedContent(existing);
            const blocks = window.wp?.blocks?.parse ? window.wp.blocks.parse(content) : undefined;
            const core = window.wp.data.dispatch('core');

            console.log('[Align UI] Clearing template for mode switch: ' + entity.postType + ' ' + entity.id + ' | ' + existing.length + ' -> ' + content.length + ' bytes');
            core.editEntityRecord('postType', entity.postType, entity.id, { content: content, blocks: blocks });
            await core.saveEditedEntityRecord('postType', entity.postType, entity.id, { throwOnError: true });
            return content;
        }
    };
})();
