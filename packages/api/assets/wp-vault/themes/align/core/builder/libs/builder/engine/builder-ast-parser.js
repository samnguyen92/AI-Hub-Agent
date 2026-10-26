/**
 * Align Builder Engine - Virtual AST Parser & Tree Controller
 * Single Source of Truth for Code-First DOM Structure & Tree Navigator Sync
 * @package Align
 */

window.Vvveb = window.Vvveb || {};

Vvveb.AstParser = {
	_idCounter: 0,
	root: null,
	idMap: {},

	/**
	 * Parse an HTML string or DOM node into a Virtual AST tree
	 * @param {string|HTMLElement} input - HTML string or DOM body element
	 * @returns {Object} - Virtual AST root
	 */
	parse: function(input) {
		const startTime = (typeof performance !== "undefined" && performance.now) ? performance.now() : Date.now();
		this.idMap = {};

		let rootEl;
		let stampDom = false;

		try {
			if (typeof input === "string") {
				this._idCounter = 0;
				const parserDoc = document.implementation.createHTMLDocument("");
				parserDoc.body.innerHTML = input || "";
				rootEl = parserDoc.body;
			} else if (input && input.nodeType) {
				rootEl = input;
				stampDom = true;
				let maxExistingId = 0;
				if (rootEl.querySelectorAll) {
					const stampedEls = rootEl.querySelectorAll("[data-ast-id]");
					for (let i = 0; i < stampedEls.length; i++) {
						const rawId = stampedEls[i].getAttribute("data-ast-id") || "";
						const num = parseInt(rawId.replace(/^ast_/, ""), 10);
						if (!isNaN(num) && num > maxExistingId) {
							maxExistingId = num;
						}
					}
				}
				this._idCounter = Math.max(this._idCounter || 0, maxExistingId);
			} else {
				this._idCounter = 0;
				const parserDoc = document.implementation.createHTMLDocument("");
				rootEl = parserDoc.body;
			}

			const stats = { seenIds: new Set(), reused: 0, created: 0, duplicatesFixed: 0 };
			this.root = this._buildAstNode(rootEl, null, stampDom, stats);

			if (stampDom) {
				console.log(`[Align AstParser] Stable IDs: reused=${stats.reused}, new=${stats.created}, duplicatesFixed=${stats.duplicatesFixed}`);
			}
		} catch (err) {
			console.error("[Align AstParser] Error parsing AST:", err);
		}

		const duration = Math.round(((typeof performance !== "undefined" && performance.now) ? performance.now() : Date.now()) - startTime);
		console.log(`[Align AstParser] Parsed AST: ${Object.keys(this.idMap).length} nodes in ${duration}ms`);

		return this.root;
	},

	/**
	 * Synchronize live canvas DOM tree with AST IDs
	 * Stamping data-ast-id ensures Tree Navigator and Code Editor remain 100% paired
	 */
	syncDom: function(domRoot) {
		if (!domRoot) return null;
		return this.parse(domRoot);
	},

	/**
	 * Recursively build AST nodes from a DOM element or text/comment node
	 */
	_buildAstNode: function(domNode, parentAst, stampDom = false, stats = null) {
		if (!domNode) return null;

		// Text Node
		if (domNode.nodeType === 3 /* TEXT_NODE */) {
			return {
				type: "#text",
				textContent: domNode.nodeValue,
				parent: parentAst
			};
		}

		// Comment Node
		if (domNode.nodeType === 8 /* COMMENT_NODE */) {
			return {
				type: "#comment",
				textContent: domNode.nodeValue,
				parent: parentAst
			};
		}

		// Element Node
		let astId;
		const isBody = !domNode.tagName || domNode.tagName.toUpperCase() === "BODY";

		if (stampDom && stats) {
			if (isBody) {
				astId = "ast_body";
			} else {
				const existingId = domNode.getAttribute ? domNode.getAttribute("data-ast-id") : null;
				if (existingId && !stats.seenIds.has(existingId)) {
					astId = existingId;
					stats.seenIds.add(astId);
					stats.reused++;
				} else {
					this._idCounter++;
					astId = "ast_" + this._idCounter;
					while (stats.seenIds.has(astId)) {
						this._idCounter++;
						astId = "ast_" + this._idCounter;
					}
					stats.seenIds.add(astId);
					if (existingId) {
						stats.duplicatesFixed++;
					} else {
						stats.created++;
					}
					if (domNode.setAttribute) {
						domNode.setAttribute("data-ast-id", astId);
					}
				}
			}
		} else {
			this._idCounter++;
			astId = "ast_" + this._idCounter;
		}

		// Extract attributes map
		const attributes = {};
		if (domNode.attributes) {
			for (let i = 0; i < domNode.attributes.length; i++) {
				const attr = domNode.attributes[i];
				if (attr.name !== "data-ast-id" && !attr.name.startsWith("data-vvveb-")) {
					attributes[attr.name] = attr.value;
				}
			}
		}

		// Identify component name / type
		let name = domNode.tagName ? domNode.tagName.toLowerCase() : "node";
		let label = name;
		if (attributes["id"]) {
			label = "#" + attributes["id"];
		} else if (attributes["class"]) {
			label = "." + attributes["class"].split(" ")[0];
		}

		// Check component match from Vvveb.Components if available
		let componentType = "html/base";
		if (Vvveb.Components && typeof Vvveb.Components.matchNode === "function" && domNode.tagName) {
			const matched = Vvveb.Components.matchNode(domNode);
			if (matched && matched.type) {
				componentType = matched.type;
				const compObj = Vvveb.Components.get(componentType);
				if (compObj && compObj.name) {
					label = compObj.name;
				}
			}
		}

		const astNode = {
			id: astId,
			tag: domNode.tagName ? domNode.tagName.toUpperCase() : "BODY",
			name: label,
			type: componentType,
			attributes: attributes,
			parent: parentAst,
			children: []
		};

		this.idMap[astId] = astNode;

		// Process all child nodes (elements, text nodes, and comments)
		if (domNode.childNodes && domNode.childNodes.length > 0) {
			for (let j = 0; j < domNode.childNodes.length; j++) {
				const childNode = domNode.childNodes[j];
				// Skip helper boxes
				if (childNode.nodeType === 1) {
					if (childNode.id === "vvvebjs-new-section" || (childNode.hasAttribute && childNode.hasAttribute("data-vvveb-helpers"))) {
						continue;
					}
					// Skip non-visual script/style inside body
					if (childNode.tagName === "SCRIPT" || childNode.tagName === "STYLE" || childNode.tagName === "LINK") {
						continue;
					}
				}
				const childAst = this._buildAstNode(childNode, astNode, stampDom, stats);
				if (childAst) {
					astNode.children.push(childAst);
				}
			}
		}

		return astNode;
	},

	/**
	 * Find an AST node by its ID
	 */
	getNode: function(astId) {
		return this.idMap[astId] || null;
	},

	/**
	 * Move an AST node to a new location
	 * @param {string} sourceId - ID of node being moved
	 * @param {string} targetId - ID of reference target node
	 * @param {string} position - 'before' | 'after' | 'inside'
	 */
	moveNode: function(sourceId, targetId, position = "after") {
		const sourceNode = this.getNode(sourceId);
		const targetNode = this.getNode(targetId);

		if (!sourceNode || !targetNode || sourceNode === targetNode) return false;

		// Prevent moving an ancestor node into its own descendant
		let p = targetNode;
		while (p) {
			if (p === sourceNode) {
				console.warn(`[Align AstParser] Circular move rejected: ${sourceId} is an ancestor of ${targetId}`);
				return false;
			}
			p = p.parent;
		}

		// Prevent inserting inside void/leaf elements
		const voidElements = ["area", "base", "br", "col", "embed", "hr", "img", "input", "link", "meta", "param", "source", "track", "wbr"];
		const targetTag = (targetNode.tag || "").toLowerCase();
		if (position === "inside" && voidElements.includes(targetTag)) {
			position = "after";
		}

		// Detach sourceNode from old parent
		if (sourceNode.parent && sourceNode.parent.children) {
			const idx = sourceNode.parent.children.indexOf(sourceNode);
			if (idx > -1) {
				sourceNode.parent.children.splice(idx, 1);
			}
		}

		if (position === "inside") {
			sourceNode.parent = targetNode;
			targetNode.children = targetNode.children || [];
			targetNode.children.push(sourceNode);
		} else if (position === "before") {
			sourceNode.parent = targetNode.parent;
			if (targetNode.parent && targetNode.parent.children) {
				const tIdx = targetNode.parent.children.indexOf(targetNode);
				targetNode.parent.children.splice(tIdx, 0, sourceNode);
			}
		} else { // after
			sourceNode.parent = targetNode.parent;
			if (targetNode.parent && targetNode.parent.children) {
				const tIdx = targetNode.parent.children.indexOf(targetNode);
				targetNode.parent.children.splice(tIdx + 1, 0, sourceNode);
			}
		}

		console.log(`[Align AstParser] Moved node ${sourceId} ${position} ${targetId}`);
		return true;
	},

	/**
	 * Clone an AST node and insert after original
	 */
	cloneNode: function(astId) {
		const original = this.getNode(astId);
		if (!original || !original.parent) return null;

		const cloned = this._deepCloneAst(original, original.parent);
		const parentChildren = original.parent.children;
		const idx = parentChildren.indexOf(original);
		if (idx > -1) {
			parentChildren.splice(idx + 1, 0, cloned);
		} else {
			parentChildren.push(cloned);
		}

		console.log(`[Align AstParser] Cloned node ${astId} -> ${cloned.id}`);
		return cloned;
	},

	/**
	 * Helper to recursively deep clone an AST node with new IDs
	 */
	_deepCloneAst: function(sourceAst, newParent) {
		if (sourceAst.type === "#text") {
			return {
				type: "#text",
				textContent: sourceAst.textContent,
				parent: newParent
			};
		}
		if (sourceAst.type === "#comment") {
			return {
				type: "#comment",
				textContent: sourceAst.textContent,
				parent: newParent
			};
		}

		this._idCounter++;
		const newId = "ast_" + this._idCounter;

		const clone = {
			id: newId,
			tag: sourceAst.tag,
			name: sourceAst.name,
			type: sourceAst.type,
			attributes: Object.assign({}, sourceAst.attributes),
			parent: newParent,
			children: []
		};

		this.idMap[newId] = clone;

		if (sourceAst.children && sourceAst.children.length > 0) {
			for (let i = 0; i < sourceAst.children.length; i++) {
				clone.children.push(this._deepCloneAst(sourceAst.children[i], clone));
			}
		}

		return clone;
	},

	/**
	 * Remove an AST node
	 */
	removeNode: function(astId) {
		const node = this.getNode(astId);
		if (!node || !node.parent) return false;

		const parentChildren = node.parent.children;
		const idx = parentChildren.indexOf(node);
		if (idx > -1) {
			parentChildren.splice(idx, 1);
		}
		delete this.idMap[astId];

		console.log(`[Align AstParser] Removed node ${astId}`);
		return true;
	},

	/**
	 * Serialize the AST back into clean HTML string
	 * @param {boolean} injectAstId - Set true when output is destined for Canvas preview
	 * @param {boolean} autoFormat - Automatically beautify output via js-beautify
	 * @returns {string} - Clean HTML string
	 */
	toHtml: function(injectAstId = false, autoFormat = true) {
		if (!this.root) return "";
		let html = "";
		for (let i = 0; i < this.root.children.length; i++) {
			html += this._renderAstNode(this.root.children[i], injectAstId);
		}
		if (autoFormat && typeof window.html_beautify === "function") {
			return window.html_beautify(html);
		}
		return html;
	},

	/**
	 * Render single AST node to HTML string
	 */
	_renderAstNode: function(node, injectAstId) {
		if (!node) return "";

		// Text Node rendering
		if (node.type === "#text") {
			return node.textContent || "";
		}

		// Comment Node rendering
		if (node.type === "#comment") {
			return `<!--${node.textContent || ""}-->`;
		}

		// Element Node rendering
		const tag = (node.tag || "div").toLowerCase();
		let attrStr = "";

		for (const key in node.attributes) {
			if (key === "data-ast-id") continue;
			const val = node.attributes[key];
			if (val === true || val === "") {
				attrStr += ` ${key}`;
			} else if (val !== false && val !== null && val !== undefined) {
				attrStr += ` ${key}="${String(val).replace(/"/g, '&quot;')}"`;
			}
		}

		if (injectAstId && node.id) {
			attrStr += ` data-ast-id="${node.id}"`;
		}

		const voidElements = ["area", "base", "br", "col", "embed", "hr", "img", "input", "link", "meta", "param", "source", "track", "wbr"];
		if (voidElements.includes(tag)) {
			return `<${tag}${attrStr}>`;
		}

		let innerContent = "";
		if (node.children && node.children.length > 0) {
			for (let i = 0; i < node.children.length; i++) {
				innerContent += this._renderAstNode(node.children[i], injectAstId);
			}
		}

		return `<${tag}${attrStr}>${innerContent}</${tag}>`;
	},

	/**
	 * Convert AST into Tree Navigator component representation
	 * Compatible with TreeList drawComponentsTree()
	 */
	getTreeRepresentation: function() {
		if (!this.root) return [];
		return this._buildComponentTree(this.root.children);
	},

	_buildComponentTree: function(astNodes) {
		const list = [];
		if (!astNodes) return list;

		for (let i = 0; i < astNodes.length; i++) {
			const ast = astNodes[i];
			// Skip text and comment nodes in visual Tree Navigator
			if (ast.type === "#text" || ast.type === "#comment") {
				continue;
			}
			const item = {
				name: ast.name,
				type: ast.type,
				astId: ast.id,
				tag: ast.tag,
				children: (ast.children && ast.children.length > 0) ? this._buildComponentTree(ast.children) : []
			};
			list.push(item);
		}
		return list;
	}
};
