/**
 * Align Theme Template Markup Builder
 * Builds Gutenberg block markup from an embedded Align Builder snapshot.
 * Template structure rules mirror Align_Builder_Template_Structure (PHP) so that
 * Site Editor saves and standalone Builder saves persist identical markup.
 *
 * @package Align
 */

(function() {
    'use strict';

    const DEFAULT_POST_CONTENT = '<!-- wp:post-content /-->';
    const DEFAULT_WRAPPED_SLUGS = ['home', 'page', 'single', 'index', 'archive', 'search', '404'];
    const DEFAULT_HEADER = '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->';
    const DEFAULT_FOOTER = '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';

    window.AlignTemplateMarkup = {
        _blockIdCache: {},

        getSlug: function(entity) {
            return String(entity.id || '').replace(/^t(p|pl)_/, '').split('//').pop();
        },

        /** Read the last persisted content, falling back to the current edited content. */
        getExistingContent: function(entity) {
            try {
                const core = window.wp?.data?.select?.('core');
                const record = core?.getEntityRecord?.('postType', entity.postType, entity.id);
                const raw = record?.content?.raw ?? (typeof record?.content === 'string' ? record.content : null);
                if (typeof raw === 'string') return raw;

                const edited = core?.getEditedEntityRecord?.('postType', entity.postType, entity.id);
                if (edited) return typeof edited.content === 'function' ? edited.content(edited) : (edited.content || '');
            } catch (err) {
                console.warn('[Align TemplateMarkup] Could not read persisted entity content:', err);
            }
            return '';
        },

        parseTopLevel: function(content) {
            const parser = window.wp?.blockSerializationDefaultParser?.parse;
            if (!content || !content.trim() || !parser) return [];
            return parser(content).filter(function(block) {
                return block.blockName || (block.innerHTML || '').trim() !== '';
            });
        },

        serializeRaw: function(block) {
            return window.wp.blocks.serializeRawBlock(block);
        },

        isFooterPart: function(block) {
            const attrs = block.attrs || {};
            return attrs.tagName === 'footer' || String(attrs.slug || '').indexOf('footer') === 0;
        },

        /** Extract leading and trailing template-part blocks from persisted template content. */
        getWrappers: function(existing) {
            const self = this;
            const blocks = self.parseTopLevel(existing);
            const isPart = function(block) { return block.blockName === 'core/template-part'; };
            const leading = [];
            const trailing = [];

            let start = 0;
            while (start < blocks.length && isPart(blocks[start])) start++;
            let end = blocks.length - 1;
            while (end >= start && isPart(blocks[end])) end--;

            blocks.forEach(function(block, index) {
                if (!isPart(block)) return;
                if (start === blocks.length) {
                    // Template holds only template parts: split them by footer semantics.
                    (self.isFooterPart(block) ? trailing : leading).push(self.serializeRaw(block));
                } else if (index < start) {
                    leading.push(self.serializeRaw(block));
                } else if (index > end) {
                    trailing.push(self.serializeRaw(block));
                }
            });
            return { leading: leading, trailing: trailing };
        },

        /** Find the first block of a given name at any depth. */
        findBlock: function(existing, blockName) {
            const stack = this.parseTopLevel(existing);
            while (stack.length) {
                const block = stack.shift();
                if (block.blockName === blockName) return block;
                if (block.innerBlocks?.length) stack.unshift.apply(stack, block.innerBlocks);
            }
            return null;
        },

        /** Keep one blockId per entity so repeated snapshots do not churn the markup. */
        resolveBlockId: function(entity, existing) {
            const key = entity.postType + ':' + entity.id;
            if (!this._blockIdCache[key]) {
                const mainBlock = this.findBlock(existing, 'align/custom-html');
                this._blockIdCache[key] = mainBlock?.attrs?.blockId || ('align-' + Math.random().toString(36).substring(2, 10));
            }
            return this._blockIdCache[key];
        },

        /** Replace Builder post-content placeholders with the persisted post-content block (DOM-based). */
        restorePostContent: function(html, postContentBlock) {
            if (!html || html.indexOf('data-component-post-content') === -1) return html;
            const commentData = postContentBlock.replace(/^<!--/, '').replace(/-->$/, '');
            const tpl = document.createElement('template');
            tpl.innerHTML = html;
            const placeholders = tpl.content.querySelectorAll('[data-component-post-content]');
            placeholders.forEach(function(node) {
                node.replaceWith(document.createComment(commentData));
            });
            console.log('[Align TemplateMarkup] Restored ' + placeholders.length + ' post-content placeholder(s) as: ' + postContentBlock);
            return tpl.innerHTML;
        },

        /** Build full block markup for a post, template, or template part snapshot. */
        build: function(entity, payload) {
            const kind = entity.kind;
            const isTemplate = kind === 'template';
            const isPart = kind === 'template_part';
            const prefix = isTemplate ? 'template' : (isPart ? 'part' : 'page');
            const slug = (prefix === 'page') ? entity.id : this.getSlug(entity);
            const styleId = 'style-' + prefix + '-' + slug;
            const scriptId = 'script-' + prefix + '-' + slug;
            const existing = (isTemplate || isPart) ? this.getExistingContent(entity) : '';

            const styleTag = payload.css ? `<style id="${styleId}" type="text/css">${payload.css}</style>\n` : '';
            const scriptTag = payload.js ? `<script id="${scriptId}" type="text/javascript">${payload.js}</script>\n` : '';
            let content = payload.html || '';
            if (isTemplate) {
                const postContentBlock = this.findBlock(existing, 'core/post-content');
                content = this.restorePostContent(content, postContentBlock ? this.serializeRaw(postContentBlock) : DEFAULT_POST_CONTENT);
            }

            const attrs = { blockId: this.resolveBlockId(entity, existing), enableShortcode: true };
            if (isTemplate || isPart) {
                attrs.styleId = styleId;
                attrs.scriptId = scriptId;
            }
            const mainBlock = `<!-- wp:align/custom-html ${JSON.stringify(attrs)} -->\n${styleTag}${content}\n${scriptTag}<!-- /wp:align/custom-html -->`;
            if (!isTemplate) return mainBlock;

            let wrappers = this.getWrappers(existing);
            if (!existing.trim() && DEFAULT_WRAPPED_SLUGS.indexOf(slug) !== -1) {
                wrappers = { leading: [DEFAULT_HEADER], trailing: [DEFAULT_FOOTER] };
            }
            console.log(`[Align TemplateMarkup] Template "${slug}" structure: ${wrappers.leading.length} leading part(s), ${wrappers.trailing.length} trailing part(s) | Existing length: ${existing.length}`);
            return wrappers.leading.concat([mainBlock], wrappers.trailing).join('\n\n');
        }
    };
})();
