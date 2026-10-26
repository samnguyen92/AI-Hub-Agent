/**
 * Align Builder Engine - Fast Incremental DOM Morphing (morphdom-inspired)
 * Provides non-destructive DOM patching between Virtual AST/HTML Code and Canvas Iframe
 * @package Align
 */

window.Vvveb = window.Vvveb || {};

Vvveb.DomMorph = {
	/**
	 * Morph an existing DOM node into the target state represented by a new DOM node or HTML string
	 * @param {HTMLElement} fromNode - Target live DOM node in canvas iframe (e.g. FrameDocument.body)
	 * @param {HTMLElement|string} toNodeOrHtml - New DOM node or HTML string from Code Editor / AST
	 * @param {Object} options - Custom morph configuration options
	 * @returns {HTMLElement} - The morphed node
	 */
	morph: function(fromNode, toNodeOrHtml, options = {}) {
		if (!fromNode) return fromNode;

		const startTime = (typeof performance !== "undefined" && performance.now) ? performance.now() : Date.now();
		let mutatedCount = 0;

		// Convert HTML string to a temporary DOM structure if needed
		let toNode = toNodeOrHtml;
		if (typeof toNodeOrHtml === "string") {
			const parserDoc = document.implementation.createHTMLDocument("");
			parserDoc.body.innerHTML = toNodeOrHtml;
			toNode = parserDoc.body;
		}

		if (!toNode) return fromNode;

		// Default morph options
		const config = Object.assign({
			childrenOnly: true,
			ignoreHelpers: true,
			onBeforeNodeAdded: null,
			onBeforeNodeDiscarded: null,
			onBeforeNodeModified: null
		}, options);

		try {
			if (config.childrenOnly) {
				mutatedCount = this.morphChildren(fromNode, toNode, config);
			} else {
				mutatedCount = this.morphNode(fromNode, toNode, config);
			}
			const duration = Math.round(((typeof performance !== "undefined" && performance.now) ? performance.now() : Date.now()) - startTime);
			if (mutatedCount > 0) {
				console.log(`[Align DomMorph] Morphed Canvas DOM in ${duration}ms (Mutations: ${mutatedCount})`);
			}
		} catch (error) {
			console.error('[Align DomMorph] Error during DOM morphing:', error);
			// Fallback: safe innerHTML assignment if morphing encounters unexpected structural exception
			if (config.childrenOnly && typeof toNodeOrHtml === "string") {
				fromNode.innerHTML = toNodeOrHtml;
			}
		}

		return fromNode;
	},

	/**
	 * Morph attributes and content between two DOM elements
	 */
	morphNode: function(fromNode, toNode, config) {
		let mutations = 0;

		// Skip morphing canvas internal helper elements
		if (config.ignoreHelpers && this.isHelperNode(fromNode)) {
			return 0;
		}

		// If node types differ or tag names differ, replace directly
		if (fromNode.nodeType !== toNode.nodeType || fromNode.nodeName !== toNode.nodeName) {
			const clone = toNode.cloneNode(true);
			if (fromNode.parentNode) {
				fromNode.parentNode.replaceChild(clone, fromNode);
				return 1;
			}
			return 0;
		}

		// Text or Comment node morphing
		if (fromNode.nodeType === 3 /* TEXT_NODE */ || fromNode.nodeType === 8 /* COMMENT_NODE */) {
			if (fromNode.nodeValue !== toNode.nodeValue) {
				fromNode.nodeValue = toNode.nodeValue;
				return 1;
			}
			return 0;
		}

		// Element node morphing (Node Type 1)
		if (fromNode.nodeType === 1) {
			mutations += this.morphAttributes(fromNode, toNode);
			mutations += this.morphChildren(fromNode, toNode, config);
		}

		return mutations;
	},

	/**
	 * Synchronize attributes between two elements
	 */
	morphAttributes: function(fromEl, toEl) {
		let count = 0;
		const fromAttrs = fromEl.attributes;
		const toAttrs = toEl.attributes;

		// Set/Update all attributes from toEl
		for (let i = 0; i < toAttrs.length; i++) {
			const attr = toAttrs[i];
			const name = attr.name;
			const val = attr.value;

			// Skip internal transient editor classes if not present in toEl
			if (name === "class") {
				const filteredFromClass = this.cleanTransientClasses(fromEl.getAttribute("class") || "");
				const cleanToClass = this.cleanTransientClasses(val || "");
				if (filteredFromClass !== cleanToClass) {
					fromEl.setAttribute("class", val);
					count++;
				}
				continue;
			}

			if (fromEl.getAttribute(name) !== val) {
				fromEl.setAttribute(name, val);
				count++;
			}
		}

		// Remove attributes from fromEl that are not in toEl (except helper attributes)
		for (let j = fromAttrs.length - 1; j >= 0; j--) {
			const attr = fromAttrs[j];
			const name = attr.name;

			// Preserve builder helper attributes on canvas
			if (name.startsWith("data-vvveb-") || name === "data-ast-id" || name === "contenteditable") {
				continue;
			}

			if (!toEl.hasAttribute(name)) {
				fromEl.removeAttribute(name);
				count++;
			}
		}

		return count;
	},

	/**
	 * Reconcile child nodes list between fromNode and toNode using keyed matching & LCS diffing
	 */
	morphChildren: function(fromParent, toParent, config) {
		let mutations = 0;
		let fromChild = fromParent.firstChild;
		let toChild = toParent.firstChild;

		while (toChild) {
			// Skip canvas helper nodes when iterating fromParent
			if (config.ignoreHelpers && fromChild && this.isHelperNode(fromChild)) {
				fromChild = fromChild.nextSibling;
				continue;
			}

			// If fromChild is null, append remaining toChild nodes
			if (!fromChild) {
				const clone = toChild.cloneNode(true);
				fromParent.appendChild(clone);
				mutations++;
				toChild = toChild.nextSibling;
				continue;
			}

			// Same tag/type match -> Morph in-place
			if (this.isMatchingNode(fromChild, toChild)) {
				mutations += this.morphNode(fromChild, toChild, config);
				fromChild = fromChild.nextSibling;
				toChild = toChild.nextSibling;
				continue;
			}

			// Lookahead: find if toChild exists later in fromParent
			let foundInFrom = null;
			let lookahead = fromChild.nextSibling;
			while (lookahead) {
				if (this.isMatchingNode(lookahead, toChild)) {
					foundInFrom = lookahead;
					break;
				}
				lookahead = lookahead.nextSibling;
			}

			if (foundInFrom) {
				// Move existing fromNode into the current position and morph it
				fromParent.insertBefore(foundInFrom, fromChild);
				mutations += this.morphNode(foundInFrom, toChild, config);
				mutations++;
				toChild = toChild.nextSibling;
			} else {
				// Insert newly created clone before current fromChild
				const clone = toChild.cloneNode(true);
				fromParent.insertBefore(clone, fromChild);
				mutations++;
				toChild = toChild.nextSibling;
			}
		}

		// Remove any remaining trailing nodes in fromParent (preserving helpers)
		while (fromChild) {
			const next = fromChild.nextSibling;
			if (!config.ignoreHelpers || !this.isHelperNode(fromChild)) {
				fromParent.removeChild(fromChild);
				mutations++;
			}
			fromChild = next;
		}

		return mutations;
	},

	/**
	 * Check if two nodes have matching identity
	 */
	isMatchingNode: function(a, b) {
		if (!a || !b) return false;
		if (a.nodeType !== b.nodeType) return false;
		if (a.nodeType === 3 || a.nodeType === 8) {
			return true; // Text or comment can morph
		}
		if (a.nodeType === 1) {
			if (a.nodeName !== b.nodeName) return false;
			// If both have ID, check if IDs match
			if (a.id && b.id) {
				return a.id === b.id;
			}
			// If both have AST ID, check match
			const astA = a.getAttribute("data-ast-id");
			const astB = b.getAttribute("data-ast-id");
			if (astA && astB) {
				return astA === astB;
			}
			return true;
		}
		return false;
	},

	/**
	 * Check if a node is an internal canvas editor helper
	 */
	isHelperNode: function(node) {
		if (!node || node.nodeType !== 1) return false;
		if (node.id === "vvvebjs-new-section" || node.id === "highlight-box" || node.id === "select-box") return true;
		if (node.hasAttribute("data-vvveb-helpers") || node.classList.contains("vvveb-helper")) return true;
		return false;
	},

	/**
	 * Clean runtime temporary classes
	 */
	cleanTransientClasses: function(classStr) {
		return classStr
			.replace(/\b(sortable-ghost|sortable-chosen|sortable-fallback|tree-drop-[a-z]+)\b/g, '')
			.replace(/\s+/g, ' ')
			.trim();
	}
};
