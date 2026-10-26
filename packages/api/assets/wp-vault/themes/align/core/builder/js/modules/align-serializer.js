/**
 * Align Theme Builder - DOM Serializer & Sanitizer Engine
 * 
 * Enforces Zero-Preview-Persistence, cleans extension attributes/tags,
 * unwraps runtime animation split spans, and produces normalized HTML for database storage.
 *
 * @package AlignBuilder
 */

window.AlignBuilderModules = window.AlignBuilderModules || {};

window.AlignBuilderModules.AlignSerializer = (function() {
    'use strict';

    /**
     * Minimal fallback allowed attribute whitelist.
     */
    const FALLBACK_ALLOWED_ATTRS = new Set([
        'id', 'class', 'style', 'title', 'dir', 'lang', 'role',
        'src', 'srcset', 'sizes', 'alt', 'width', 'height', 'loading',
        'href', 'target', 'rel', 'type', 'name', 'value', 'placeholder',
        'data-split', 'data-scene', 'data-flow', 'data-par', 'data-sp',
        'data-reveal', 'data-rise', 'data-open', 'data-reveal-now', 'data-scatter',
        'data-clock', 'data-top', 'data-nl', 'data-filter-grid', 'data-hscroll',
        'data-us-project', 'data-t', 'data-stack', 'data-pop', 'data-cat',
        'data-filter', 'data-cursor-wide', 'data-cursor-img', 'data-cursor-label',
        'data-theme', 'data-lang-link', 'data-480', 'data-720', 'data-1120', 'data-society'
    ]);

    /**
     * Fallback schema in case AlignSerializerSchema module is not loaded.
     */
    const FALLBACK_SCHEMA = {
        isBlocklistedAttribute: function(attr) {
            if (!attr) return true;
            const a = attr.toLowerCase().trim();
            if (a.startsWith('on')) return true;
            if (a.includes(':') && !a.startsWith('xlink:') && !a.startsWith('xml:')) return true;
            return a.startsWith('bis_') || 
                   a.startsWith('data-vvveb-') || 
                   a.startsWith('data-gr-') || 
                   a.startsWith('data-loom');
        },
        isAttributeAllowed: function(attrName, tagName, el, attrValue) {
            if (!attrName) return false;
            const attr = attrName.toLowerCase().trim();
            if (this.isBlocklistedAttribute(attr)) return false;

            // Anti-XSS check for URI attributes
            if (['href', 'src', 'action', 'formaction', 'poster', 'data'].includes(attr)) {
                let val = attrValue !== undefined ? attrValue : (el && el.getAttribute ? el.getAttribute(attrName) : '');
                if (val && typeof val === 'string') {
                    const cleaned = val.replace(/[\u0000-\u001F\u007F-\u009F\s]+/g, '').toLowerCase();
                    if (cleaned.startsWith('javascript:') || cleaned.startsWith('vbscript:') || cleaned.startsWith('data:text/html') || cleaned.startsWith('data:text/javascript')) {
                        return false;
                    }
                }
            }

            if (attr === 'data-src') {
                const tag = (tagName || (el && el.tagName) || '').toLowerCase();
                return ['img', 'video', 'source', 'iframe', 'audio', 'picture'].includes(tag);
            }

            if (FALLBACK_ALLOWED_ATTRS.has(attr)) return true;
            if (attr.startsWith('data-align-') || attr.startsWith('aria-')) return true;

            return false;
        },
        isExtensionTag: function(tagName) {
            if (!tagName) return false;
            const tag = tagName.toLowerCase();
            return tag.startsWith('grammarly-') || 
                   tag.startsWith('loom-') || 
                   tag === 'newsection' || 
                   tag === 'loom-container' || 
                   tag === 'loom-shadow-root';
        },
        isDangerousTag: function(tagName) {
            if (!tagName) return false;
            const tag = tagName.toLowerCase();
            return ['animate', 'set', 'animatetransform', 'animatemotion'].includes(tag);
        },
        getHelperSelectors: function() {
            return [
                'newsection',
                '#vvvebjs-new-section',
                '[data-vvveb-helpers]',
                'style[id^="style-"]',
                'script[id^="script-"]',
                '[data-align-preview="1"]'
            ];
        }
    };

    /**
     * Retrieve active serializer schema definition with fallback.
     *
     * @return {Object} Schema module or fallback schema.
     */
    function getSchema() {
        if (typeof window !== 'undefined' && 
            window.AlignBuilderModules && 
            window.AlignBuilderModules.AlignSerializerSchema) {
            return window.AlignBuilderModules.AlignSerializerSchema;
        }
        return FALLBACK_SCHEMA;
    }

    return {
        /**
         * Check if an attribute is explicitly blocklisted.
         *
         * @param {string} attr Lowercase attribute name.
         * @return {boolean} True if blocklisted.
         */
        isBlocklistedAttribute: function(attr) {
            return getSchema().isBlocklistedAttribute(attr);
        },

        /**
         * Check if an attribute is allowed in sanitized output.
         *
         * @param {string} attrName Raw attribute name.
         * @param {string} [tagName] Optional tag name.
         * @param {Element} [el] Optional element reference.
         * @param {string} [attrValue] Optional attribute value.
         * @return {boolean} True if allowed.
         */
        isAttributeAllowed: function(attrName, tagName, el, attrValue) {
            return getSchema().isAttributeAllowed(attrName, tagName, el, attrValue);
        },

        /**
         * Check if a tag belongs to browser extensions (Grammarly, Loom, etc.).
         *
         * @param {string} tagName Lowercase tag name.
         * @return {boolean} True if extension tag.
         */
        isExtensionTag: function(tagName) {
            return getSchema().isExtensionTag(tagName);
        },

        /**
         * Check if a tag is a dangerous SVG SMIL element or forbidden tag.
         *
         * @param {string} tagName Tag name.
         * @return {boolean} True if dangerous tag.
         */
        isDangerousTag: function(tagName) {
            const schema = getSchema();
            return schema.isDangerousTag ? schema.isDangerousTag(tagName) : false;
        },

        /**
         * Get helper element selectors to be removed.
         *
         * @return {string[]} Array of selector strings.
         */
        getHelperSelectors: function() {
            return getSchema().getHelperSelectors();
        },

        /**
         * Serialize and sanitize a DOM Node or HTML string.
         *
         * @param {Node|string} input DOM Node, Element, or HTML string.
         * @param {Object} options Serialization options.
         * @return {string} Sanitized clean HTML string.
         */
        serialize: function(input, options) {
            const startTime = performance.now();
            const opts = Object.assign({
                debug: false,
                fullDocument: false
            }, options || {});

            let rootNode = null;
            let isStringInput = false;
            let initialBrCount = 0;

            if (typeof input === 'string') {
                isStringInput = true;
                const brMatches = input.match(/<br\b[^>]*>/gi);
                initialBrCount = brMatches ? brMatches.length : 0;
                const parser = new DOMParser();
                const doc = parser.parseFromString(input, 'text/html');
                rootNode = opts.fullDocument ? doc.documentElement : doc.body;
            } else if (input && input.nodeType) {
                if (input.querySelectorAll) {
                    initialBrCount = input.querySelectorAll('br').length;
                }
                rootNode = input.cloneNode(true);

                // Transfer Pre-Animation Baseline Snapshot data onto cloned elements.
                // Iterative BFS avoids call-stack overflow on deeply nested DOMs.
                const win = (input.ownerDocument && input.ownerDocument.defaultView) || window;
                const snapMap = win.__ALIGN_DOM_SNAPSHOT__;
                if (snapMap && snapMap.get) {
                    try {
                        const queue = [[input, rootNode]];
                        while (queue.length) {
                            const [orig, clone] = queue.shift();
                            if (!orig || !clone || orig.nodeType !== 1) continue;
                            const snapData = snapMap.get(orig);
                            if (snapData) clone.__alignSnap = snapData;
                            let oChild = orig.firstElementChild;
                            let cChild = clone.firstElementChild;
                            while (oChild && cChild) {
                                queue.push([oChild, cChild]);
                                oChild = oChild.nextElementSibling;
                                cChild = cChild.nextElementSibling;
                            }
                        }
                    } catch (e) {
                        console.warn('[AlignSerializer] Snapshot transfer warning:', e);
                    }
                }
            } else {
                return '';
            }

            if (!rootNode) return '';

            let totalNodes = 0;
            let removedHelpersCount = 0;
            let removedExtensionTagsCount = 0;
            let textRestoreCount = 0;
            let strippedAttrsCount = 0;
            const strippedAttrsDetails = [];

            // 1. Remove editor helper elements matching selector list
            const helperSelectors = this.getHelperSelectors();
            helperSelectors.forEach(selector => {
                try {
                    rootNode.querySelectorAll(selector).forEach(el => {
                        el.remove();
                        removedHelpersCount++;
                    });
                } catch (e) {}
            });

            // 1b. Universal unwrap for runtime animation pin spacers (GSAP ScrollTrigger, etc.)
            let unpinnedSpacersCount = 0;
            let pinSpacers = rootNode.querySelectorAll('.pin-spacer');
            while (pinSpacers.length > 0) {
                pinSpacers.forEach(spacer => {
                    if (spacer.parentNode) {
                        while (spacer.firstChild) {
                            spacer.parentNode.insertBefore(spacer.firstChild, spacer);
                        }
                        spacer.remove();
                        unpinnedSpacersCount++;
                    }
                });
                pinSpacers = rootNode.querySelectorAll('.pin-spacer');
            }

            // 2. Universal Text Restore for animated split containers.
            // data-src presence is the MARKER that runSplit() has injected child spans into this element.
            // FIX: Use current textContent (not data-src value) as the restored text.
            //   - If user never edited: textContent === data-src → result is identical.
            //   - If user edited via contenteditable: textContent has the new value → edit is preserved.
            // Setting textContent simultaneously removes all injected child spans (ln, wd, ch, etc.)
            // without needing to know their class names (library-agnostic).
            rootNode.querySelectorAll('[data-split][data-src]').forEach(splitParent => {
                const current = (splitParent.textContent || '').trim();
                splitParent.textContent = current; // collapse runtime spans, keep current (possibly edited) text
                splitParent.removeAttribute('data-src');
                textRestoreCount++;
            });

            // 2b. Structural fallback for [data-split] parents where data-src was unavailable.
            // Detect runtime split spans by shape, not by class name:
            //   - Element is a SPAN (not a semantic tag)
            //   - Has no attributes that signal authored intent (href, src, id, data-*, etc.)
            //   - Contains only text (no nested element children)
            // Any authored inline element (<a>, <strong>, <em>, etc.) aborts the collapse.
            rootNode.querySelectorAll('[data-split]').forEach(splitParent => {
                const children = Array.from(splitParent.childNodes);
                const hasSpanChildren = children.some(n => n.nodeType === 1 && n.tagName === 'SPAN');
                if (!hasSpanChildren) return;

                /**
                 * Returns true if the node looks like a runtime-injected split wrapper.
                 * The check is structural — no class name coupling.
                 * @param {Node} n
                 * @returns {boolean}
                 */
                const isRuntimeSplitSpan = n => {
                    if (n.nodeType === 3) return true; // bare text node — ok
                    if (n.nodeType !== 1) return true;  // comment / PI — ignore
                    if (n.tagName !== 'SPAN') return false; // non-span element = authored content
                    if (n.firstElementChild) return false;  // nested elements = authored span
                    // Authored spans carry semantic attributes (href-via-wrapping, id, data-*, etc.)
                    // Runtime split spans typically have only class and/or style.
                    for (let i = 0; i < n.attributes.length; i++) {
                        const a = n.attributes[i].name;
                        if (a !== 'class' && a !== 'style' && a !== 'aria-hidden') return false;
                    }
                    return true;
                };

                if (children.every(isRuntimeSplitSpan)) {
                    splitParent.textContent = (splitParent.textContent || '').trim();
                    textRestoreCount++;
                } else {
                    console.warn('[AlignSerializer] [data-split] has mixed children, skipping collapse:', splitParent);
                }
            });

            // 3. Process remaining elements, clean previews and sanitize attributes
            const allElements = rootNode.querySelectorAll('*');
            totalNodes = allElements.length;

            allElements.forEach(el => {
                const tagName = el.tagName.toLowerCase();

                // 3a. Remove extension tags, untracked scripts, and dangerous SVG SMIL elements
                if (tagName === 'script' || this.isDangerousTag(tagName) || this.isExtensionTag(tagName)) {
                    el.remove();
                    removedExtensionTagsCount++;
                    return;
                }

                // 3b. Zero-Preview-Persistence: Clean and normalize shortcodes, blocks, and post-content
                if (el.hasAttribute('data-component-shortcode')) {
                    let scVal = (el.getAttribute('data-shortcode') || el.textContent || '').trim();
                    if (scVal) {
                        // Normalize double quotes to single quotes to prevent &quot; attribute corruption in HTML
                        scVal = scVal.replace(/"/g, "'");
                        el.setAttribute('data-shortcode', scVal);
                        el.textContent = scVal;
                    } else {
                        el.innerHTML = '';
                    }
                } else if (el.hasAttribute('data-component-post-content')) {
                    el.innerHTML = '';
                    el.textContent = 'post-content';
                } else if (el.hasAttribute('data-component-wp-block')) {
                    el.innerHTML = '';
                } else if (el.classList.contains('shortcode-preview-content') || el.classList.contains('block-preview-content')) {
                    el.innerHTML = '';
                }

                // 3c. Preserve dynamic featured image shortcode on serialization
                if (tagName === 'img' && (el.hasAttribute('data-align-featured') || el.getAttribute('data-featured-shortcode') === '[align_post_hero_image]')) {
                    el.setAttribute('src', '[align_post_hero_image]');
                    if (!el.getAttribute('alt') || el.getAttribute('alt') === '[align_post_title]') {
                        el.setAttribute('alt', '[align_post_title]');
                    }
                }

                // 3d. Clean runtime editing attributes and active animation classes
                if (el.hasAttribute('contenteditable')) {
                    el.removeAttribute('contenteditable');
                }
                if (el.hasAttribute('data-placeholder')) {
                    el.removeAttribute('data-placeholder');
                }
                if (el.getAttribute('spellcheck') === 'false') {
                    el.removeAttribute('spellcheck');
                }
                // Guard: SVG child elements may not have classList in all browsers
                const hasCL = el.classList != null;

                // Universal Baseline Snapshot Comparison:
                // If a pre-animation snapshot entry exists for this element, restore original classes and styles.
                const snap = el.__alignSnap;
                if (snap) {
                    // Universal class comparison: remove any class not present in baseline
                    if (hasCL && typeof snap.c === 'string') {
                        const baseClasses = new Set(snap.c.trim().split(/\s+/).filter(Boolean));
                        const currentClasses = Array.from(el.classList);
                        currentClasses.forEach(cls => {
                            if (!baseClasses.has(cls)) {
                                el.classList.remove(cls);
                            }
                        });
                    }
                    // Universal inline style comparison: remove any style property not present in baseline
                    if (el.style && snap.s) {
                        const currentProps = [];
                        for (let i = 0; i < el.style.length; i++) {
                            currentProps.push(el.style[i]);
                        }
                        currentProps.forEach(prop => {
                            if (!(prop in snap.s)) {
                                el.style.removeProperty(prop);
                            }
                        });
                    }
                }

                // Note: Hardcoded class fallbacks (is-in, is-buried, is-px) and CSS var fallbacks
                // (--p, --cover, --sp) have been removed. The Universal Baseline Snapshot Diff
                // above already strips every runtime-added class/style regardless of name.
                // Fallback is only relevant for elements not in the snapshot — which cannot happen
                // since the snapshot script walks the full DOM tree synchronously at page load.
                if (el.hasAttribute('class') && !el.getAttribute('class').trim()) {
                    el.removeAttribute('class');
                }

                // Clean remaining runtime computed animation styles:
                // clip-path:path() is a universal value-based check — no class coupling required.
                // The snapshot diff handles all inline CSS var removals (--p, --cover, --sp, etc.)
                if (el.style) {
                    const cpVal = el.style.getPropertyValue('clip-path');
                    if (cpVal && cpVal.trim().startsWith('path(')) {
                        el.style.removeProperty('clip-path');
                    }
                    if (el.hasAttribute('style') && !el.getAttribute('style').trim()) {
                        el.removeAttribute('style');
                    }
                }


                // 3e. Sanitize and filter all element attributes (pass tagName, el, and attr.value)
                const attrs = Array.from(el.attributes);
                attrs.forEach(attr => {
                    const attrName = attr.name;
                    const attrVal = attr.value;
                    if (!this.isAttributeAllowed(attrName, tagName, el, attrVal)) {
                        el.removeAttribute(attrName);
                        strippedAttrsCount++;
                        if (opts.debug && strippedAttrsDetails.length < 50) {
                            strippedAttrsDetails.push(`<${tagName}> @${attrName}`);
                        }
                    }
                });
            });

            // 4. Extract serialized HTML content
            let outputHtml = '';
            if (opts.fullDocument || (isStringInput && /<html\b|<body\b/i.test(input))) {
                outputHtml = rootNode.outerHTML || rootNode.innerHTML;
            } else {
                outputHtml = rootNode.innerHTML;
            }

            outputHtml = (outputHtml || '').trim();
            const elapsed = (performance.now() - startTime).toFixed(2);
            const outputBrMatches = outputHtml.match(/<br\b[^>]*>/gi);
            const outputBrCount = outputBrMatches ? outputBrMatches.length : 0;

            // 5. Detailed console group debugging if enabled
            if (opts.debug) {
                console.groupCollapsed(
                    `%c[AlignSerializer]%c Cleaned DOM in ${elapsed}ms | Nodes: ${totalNodes} | Stripped Attrs: ${strippedAttrsCount} | Text Restored: ${textRestoreCount} | <br> tags: ${initialBrCount} -> ${outputBrCount}`,
                    'background: #6366f1; color: #fff; font-weight: bold; padding: 2px 6px; border-radius: 4px;',
                    'color: #6366f1; font-weight: bold;'
                );
                console.log('Execution Time:', `${elapsed} ms`);
                console.log('Total Traversed Nodes:', totalNodes);
                console.log('Removed Helper Elements:', removedHelpersCount);
                console.log('Unwrapped Pin Spacers:', unpinnedSpacersCount);
                console.log('Removed Extension Tags:', removedExtensionTagsCount);
                console.log('Text Restore (data-src + structural fallback):', textRestoreCount);
                console.log('Total Stripped Attributes:', strippedAttrsCount);
                if (strippedAttrsDetails.length > 0) {
                    console.log('Sample Stripped Attributes (up to 50):', strippedAttrsDetails);
                }
                console.log('<br> Tags (Input vs Output):', `${initialBrCount} -> ${outputBrCount}`);
                if (outputBrCount > 0) {
                    console.warn('[AlignSerializer] ⚠️ <br> tags present in output:', outputBrMatches);
                }
                console.log('Clean Output HTML Length:', outputHtml.length, 'characters');
                console.groupEnd();
            }

            return outputHtml;
        }
    };
})();
