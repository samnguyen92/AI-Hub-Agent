/**
 * Align Theme Builder - Serializer Schema Definitions
 *
 * Provides attribute whitelists, blocklists, selector configurations,
 * and helper tag classifications for clean DOM serialization.
 *
 * @package AlignBuilder
 */

window.AlignBuilderModules = window.AlignBuilderModules || {};

window.AlignBuilderModules.AlignSerializerSchema = (function() {
    'use strict';

    /**
     * Standard HTML5 and SVG attributes allowed in clean serialized markup.
     */
    const ALLOWED_ATTRIBUTES = new Set([
        // Standard HTML attributes
        'id', 'class', 'style', 'title', 'dir', 'lang', 'tabindex', 'accesskey',
        'hidden', 'contenteditable', 'draggable', 'spellcheck', 'role',
        // Media & Resource attributes
        'src', 'srcset', 'sizes', 'alt', 'width', 'height', 'loading', 'decoding',
        'fetchpriority', 'crossorigin', 'referrerpolicy', 'ismap', 'usemap',
        'poster', 'preload', 'autoplay', 'loop', 'muted', 'controls', 'playsinline',
        'default', 'kind', 'srclang', 'label',
        // Links & Forms attributes
        'href', 'target', 'rel', 'download', 'hreflang', 'type', 'ping',
        'name', 'value', 'placeholder', 'action', 'method', 'enctype', 'novalidate',
        'for', 'form', 'formaction', 'formenctype', 'formmethod', 'formnovalidate', 'formtarget',
        'autocomplete', 'autofocus', 'disabled', 'readonly', 'required', 'checked',
        'selected', 'multiple', 'maxlength', 'minlength', 'pattern', 'min', 'max',
        'step', 'size', 'rows', 'cols', 'wrap', 'accept', 'list', 'inputmode', 'enterkeyhint',
        // Table & Structure attributes
        'colspan', 'rowspan', 'headers', 'scope', 'span', 'datetime', 'open',
        'reversed', 'start', 'cite', 'media', 'sandbox', 'allow', 'allowfullscreen',
        'as', 'nonce', 'integrity', 'capture',
        // SVG Specifications attributes
        'xmlns', 'xmlns:xlink', 'xmlns:svg', 'version', 'baseprofile', 'viewbox',
        'preserveaspectratio', 'x', 'y', 'cx', 'cy', 'r', 'rx', 'ry', 'x1', 'y1',
        'x2', 'y2', 'd', 'points', 'pathlength', 'dx', 'dy', 'fill', 'fill-opacity',
        'fill-rule', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin',
        'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity',
        'transform', 'transform-origin', 'clip-path', 'clip-rule', 'mask', 'maskunits',
        'maskcontentunits', 'patternunits', 'patterntransform', 'gradientunits',
        'gradienttransform', 'spreadmethod', 'offset', 'stop-color', 'stop-opacity',
        'filter', 'filterunits', 'primitiveunits', 'stddeviation', 'in', 'in2',
        'result', 'mode', 'values', 'operator', 'k1', 'k2', 'k3', 'k4', 'edge-mode',
        'edgemode', 'text-anchor', 'dominant-baseline', 'alignment-baseline',
        'baseline-shift', 'letter-spacing', 'word-spacing', 'font-family', 'font-size',
        'font-weight', 'font-style', 'dur', 'repeatcount', 'attributename', 'to',
        'from', 'by', 'begin', 'end', 'restart', 'calcmode', 'keysplines', 'keytimes',
        'opacity', 'visibility', 'color', 'vector-effect', 'marker-start', 'marker-mid',
        'marker-end', 'orient', 'refx', 'refy', 'markerunits', 'markerwidth', 'markerheight',
        'xlink:href', 'xlink:title', 'xlink:show', 'xlink:actuate', 'xml:space', 'xml:lang'
    ]);

    /**
     * Allowed namespace prefixes for data-, aria-, and svg namespace attributes.
     */
    const ALLOWED_PREFIXES = [
        'aria-', 'data-align-', 'data-component-', 'data-overwrite-', 'data-allow-',
        'data-aos', 'data-swiper', 'data-gsap', 'data-bs-', 'data-fancybox',
        'data-tippy', 'data-scroll', 'data-cursor', 'data-lazy-', 'data-focal-',
        'data-slide', 'data-instance-', 'xlink:', 'xml:'
    ];

    /**
     * Allowed exact data attributes (shortcodes, lazyloading, utilities, and 15 animation attributes).
     * Note: data-src is intentionally excluded here and guarded by MEDIA_TAGS_FOR_DATA_SRC.
     */
    const ALLOWED_EXACT_DATA_ATTRS = new Set([
        'data-shortcode', 'data-shortcode-tag', 'data-wp-block', 'data-instance-id',
        'data-instance', 'data-reuse-block-id', 'data-srcset', 'data-poster',
        'data-bg-src', 'data-original', 'data-lazy-src', 'data-speed',
        'data-opacity', 'data-toggle', 'data-target', 'data-dismiss',
        'data-ride', 'data-align-link-id', 'data-id', 'data-slot-width',
        'data-selected-sizes', 'data-align-sizes', 'data-align-image-id',
        'data-align-link-title', 'data-align-preload',
        // 15 Animation engine attributes
        'data-split', 'data-scene', 'data-flow', 'data-par', 'data-sp',
        'data-reveal', 'data-rise', 'data-open', 'data-reveal-now', 'data-scatter',
        'data-clock', 'data-top', 'data-nl', 'data-filter-grid', 'data-hscroll',
        // Project and interactive frontend data attributes
        'data-us-project', 'data-t', 'data-stack', 'data-pop', 'data-cat',
        'data-filter', 'data-cursor-wide', 'data-cursor-img', 'data-cursor-label',
        'data-theme', 'data-lang-link', 'data-480', 'data-720', 'data-1120', 'data-society'
    ]);

    /**
     * Tags permitted to retain data-src for native lazyloading and deferred playback.
     */
    const MEDIA_TAGS_FOR_DATA_SRC = new Set([
        'img', 'source', 'video', 'audio', 'iframe', 'picture'
    ]);

    /**
     * Blocklisted attribute prefix patterns (extensions, temporary flags, editor helpers).
     */
    const BLOCKLISTED_PREFIXES = [
        'bis_', 'data-gr-', 'data-gramm', 'data-lastpass', 'data-1p',
        'data-loom', 'data-browser-', 'data-extension-', 'data-vvveb-',
        'data-align-editable'
    ];

    /**
     * Blocklisted exact attribute names.
     */
    const BLOCKLISTED_EXACT_ATTRS = new Set([
        'data-preview-loading', 'data-preview-state', 'data-ll-status',
        'data-vvveb-helpers', 'data-vvveb-actions', 'data-ast-id',
        'data-align-editable',
        'bis_skin_checked', 'bis_register', 'bis_depth', 'bis_frame_id', 'bis_status'
    ]);

    /**
     * Dangerous element tags to strip completely (malicious SVG SMIL animation, untracked scripts).
     */
    const DANGEROUS_TAGS = new Set([
        'animate', 'set', 'animatetransform', 'animatemotion'
    ]);

    /**
     * URI and action attributes requiring strict URL sanitization.
     */
    const URI_ATTRIBUTES = new Set([
        'href', 'xlink:href', 'src', 'action', 'formaction', 'poster', 'data'
    ]);

    /**
     * Dangerous URL scheme prefixes.
     */
    const DANGEROUS_URL_PREFIXES = [
        'javascript:', 'vbscript:', 'data:text/html', 'data:text/javascript'
    ];

    /**
     * Exact tag names injected by browser extensions or temporary builder placeholders.
     */
    const EXTENSION_EXACT_TAGS = new Set([
        'newsection', 'loom-container', 'loom-shadow-root'
    ]);

    /**
     * Tag name prefixes injected by browser extensions.
     */
    const EXTENSION_TAG_PREFIXES = ['grammarly-', 'loom-'];

    /**
     * Selectors for internal editor helpers and temporary UI elements to be stripped.
     */
    const HELPER_SELECTORS = [
        'newsection', '#vvvebjs-new-section', '[data-vvveb-helpers]',
        '.vvveb-new-section-container', '.vvveb-new-section-row', '.vvveb-new-section-col',
        '.vvveb-new-section-btn', '#new-component', '#new-section', '#new-blank-section',
        'style[id^="style-"]', 'style[id^="align-style-"]', 'script[id^="script-"]',
        'script[id^="align-script-"]', 'style#vvvebjs-styles', 'script#vvvebjs-scripts',
        'style#align-custom-css', 'script#align-custom-js', '[data-align-preview="1"]',
        'style#align-global-custom-css', 'script#align-global-custom-js', 'style#wp-global-styles'
    ];

    /**
     * Regex pattern to validate attribute names, prefixes, and tags added via extend().
     */
    const SAFE_IDENTIFIER_REGEX = /^[a-z0-9_:-]+$/i;

    /**
     * Validate token added via extend() to prevent injection of malicious rules.
     *
     * @param {string} str Candidate token string.
     * @return {boolean} True if token is safe.
     */
    function isValidAttrToken(str) {
        if (!str || typeof str !== 'string') return false;
        const trimmed = str.trim().toLowerCase();
        if (!trimmed || trimmed.startsWith('on')) return false;
        return SAFE_IDENTIFIER_REGEX.test(trimmed);
    }

    const schema = {
        /**
         * Check if an attribute is explicitly blocklisted.
         *
         * @param {string} attrName Raw or lowercase attribute name.
         * @return {boolean} True if blocklisted.
         */
        isBlocklistedAttribute: function(attrName) {
            if (!attrName || typeof attrName !== 'string') return false;
            const attr = attrName.toLowerCase().trim();

            // Explicit hard block for all 'on*' event handlers (e.g. onclick, onload, onerror)
            if (attr.startsWith('on')) {
                return true;
            }

            // Block unknown colon namespaces (only allow explicit xml/xlink/xmlns)
            if (attr.includes(':') && !attr.startsWith('xlink:') && !attr.startsWith('xml:') && !attr.startsWith('xmlns:')) {
                return true;
            }

            if (BLOCKLISTED_EXACT_ATTRS.has(attr)) return true;
            for (let i = 0; i < BLOCKLISTED_PREFIXES.length; i++) {
                if (attr.startsWith(BLOCKLISTED_PREFIXES[i])) return true;
            }
            return false;
        },

        /**
         * Verify if a URL or URI attribute value is safe (anti-XSS).
         *
         * @param {string} val Attribute value.
         * @return {boolean} True if safe, false if contains dangerous scheme.
         */
        isUrlSafe: function(val) {
            if (!val || typeof val !== 'string') return true;
            const cleaned = val.replace(/[\u0000-\u001F\u007F-\u009F\s]+/g, '').toLowerCase();
            for (let i = 0; i < DANGEROUS_URL_PREFIXES.length; i++) {
                if (cleaned.startsWith(DANGEROUS_URL_PREFIXES[i])) {
                    return false;
                }
            }
            return true;
        },

        /**
         * Check if an attribute value is safe for the given attribute name.
         *
         * @param {string} attrName Attribute name.
         * @param {string} attrValue Attribute value.
         * @return {boolean} True if safe.
         */
        isAttributeValueSafe: function(attrName, attrValue) {
            if (!attrName || typeof attrName !== 'string') return false;
            const attr = attrName.toLowerCase().trim();
            if (URI_ATTRIBUTES.has(attr)) {
                return this.isUrlSafe(attrValue);
            }
            return true;
        },

        /**
         * Check if an attribute is allowed on a specific element.
         *
         * @param {string} attrName Raw attribute name.
         * @param {string} [tagName] Optional HTML tag name.
         * @param {Element} [el] Optional DOM element reference.
         * @param {string} [attrValue] Optional attribute value.
         * @return {boolean} True if allowed.
         */
        isAttributeAllowed: function(attrName, tagName, el, attrValue) {
            if (!attrName || typeof attrName !== 'string') return false;
            const attr = attrName.toLowerCase().trim();

            // 1. Explicit blocklist check (including on* event handlers and bad namespaces)
            if (this.isBlocklistedAttribute(attr)) {
                return false;
            }

            // 2. Anti-XSS check for URI / action attribute values
            let val = attrValue;
            if (val === undefined && el && el.getAttribute) {
                val = el.getAttribute(attrName);
            }
            if (val !== undefined && val !== null && !this.isAttributeValueSafe(attr, val)) {
                return false;
            }

            // 3. Special conditional handling for data-src (media tags only)
            if (attr === 'data-src') {
                const resolvedTag = (tagName || (el && el.tagName) || '').toLowerCase();
                return MEDIA_TAGS_FOR_DATA_SRC.has(resolvedTag);
            }

            // 4. Standard allowed HTML/SVG attributes
            if (ALLOWED_ATTRIBUTES.has(attr)) {
                return true;
            }

            // 5. Explicitly allowed exact data attributes
            if (ALLOWED_EXACT_DATA_ATTRS.has(attr)) {
                return true;
            }

            // 6. Allowed attribute prefixes
            for (let i = 0; i < ALLOWED_PREFIXES.length; i++) {
                if (attr.startsWith(ALLOWED_PREFIXES[i])) {
                    return true;
                }
            }

            return false;
        },

        /**
         * Check if a tag belongs to browser extensions (Grammarly, Loom, etc.).
         *
         * @param {string} tagName Tag name to check.
         * @return {boolean} True if extension tag.
         */
        isExtensionTag: function(tagName) {
            if (!tagName || typeof tagName !== 'string') return false;
            const tag = tagName.toLowerCase();
            if (EXTENSION_EXACT_TAGS.has(tag)) return true;
            for (let i = 0; i < EXTENSION_TAG_PREFIXES.length; i++) {
                if (tag.startsWith(EXTENSION_TAG_PREFIXES[i])) return true;
            }
            return false;
        },

        /**
         * Check if a tag is a dangerous SVG SMIL element or forbidden tag.
         *
         * @param {string} tagName Tag name.
         * @return {boolean} True if dangerous tag.
         */
        isDangerousTag: function(tagName) {
            if (!tagName || typeof tagName !== 'string') return false;
            const tag = tagName.toLowerCase();
            return DANGEROUS_TAGS.has(tag);
        },

        /**
         * Get array of helper selectors to strip during serialization.
         *
         * @return {string[]} Array of CSS selectors.
         */
        getHelperSelectors: function() {
            return HELPER_SELECTORS.slice();
        },

        /**
         * Extend schema with custom rules with strict input sanitization.
         *
         * @param {Object} rules Custom schema rules.
         */
        extend: function(rules) {
            if (!rules || typeof rules !== 'object') return;
            if (Array.isArray(rules.allowedAttributes)) {
                rules.allowedAttributes.forEach(attr => {
                    if (isValidAttrToken(attr)) ALLOWED_ATTRIBUTES.add(attr.toLowerCase().trim());
                });
            }
            if (Array.isArray(rules.allowedPrefixes)) {
                rules.allowedPrefixes.forEach(prefix => {
                    if (isValidAttrToken(prefix)) {
                        const p = prefix.toLowerCase().trim();
                        if (!ALLOWED_PREFIXES.includes(p)) ALLOWED_PREFIXES.push(p);
                    }
                });
            }
            if (Array.isArray(rules.allowedExactDataAttrs)) {
                rules.allowedExactDataAttrs.forEach(attr => {
                    if (isValidAttrToken(attr)) ALLOWED_EXACT_DATA_ATTRS.add(attr.toLowerCase().trim());
                });
            }
            if (Array.isArray(rules.mediaTagsForDataSrc)) {
                rules.mediaTagsForDataSrc.forEach(tag => {
                    if (isValidAttrToken(tag)) MEDIA_TAGS_FOR_DATA_SRC.add(tag.toLowerCase().trim());
                });
            }
            if (Array.isArray(rules.blocklistedPrefixes)) {
                rules.blocklistedPrefixes.forEach(prefix => {
                    if (isValidAttrToken(prefix)) {
                        const p = prefix.toLowerCase().trim();
                        if (!BLOCKLISTED_PREFIXES.includes(p)) BLOCKLISTED_PREFIXES.push(p);
                    }
                });
            }
            if (Array.isArray(rules.blocklistedExactAttrs)) {
                rules.blocklistedExactAttrs.forEach(attr => {
                    if (isValidAttrToken(attr)) BLOCKLISTED_EXACT_ATTRS.add(attr.toLowerCase().trim());
                });
            }
            if (Array.isArray(rules.extensionExactTags)) {
                rules.extensionExactTags.forEach(tag => {
                    if (isValidAttrToken(tag)) EXTENSION_EXACT_TAGS.add(tag.toLowerCase().trim());
                });
            }
            if (Array.isArray(rules.extensionTagPrefixes)) {
                rules.extensionTagPrefixes.forEach(prefix => {
                    if (isValidAttrToken(prefix)) {
                        const p = prefix.toLowerCase().trim();
                        if (!EXTENSION_TAG_PREFIXES.includes(p)) EXTENSION_TAG_PREFIXES.push(p);
                    }
                });
            }
            if (Array.isArray(rules.helperSelectors)) {
                rules.helperSelectors.forEach(sel => {
                    if (typeof sel === 'string' && sel.trim()) {
                        const s = sel.trim();
                        if (!HELPER_SELECTORS.includes(s)) HELPER_SELECTORS.push(s);
                    }
                });
            }
        }
    };

    // Auto-extend with global custom rules if present
    if (typeof window !== 'undefined' && window.AlignCustomSerializerRules) {
        schema.extend(window.AlignCustomSerializerRules);
    }

    return schema;
})();
