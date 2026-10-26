/**
 * Align Builder Engine - Tree Navigator Coalesced Renderer & State Preserver
 * Batches multiple loadComponents() calls per animation frame, preserves scroll position,
 * maintains expanded node states via stable AST IDs, and queues pending selections.
 * @package Align
 */

window.Vvveb = window.Vvveb || {};
Vvveb.TreeList = Vvveb.TreeList || {};

Object.assign(Vvveb.TreeList, {
	_renderScheduled: false,
	_renderRafId: null,
	_renderCallCount: 0,
	_pendingSelectNode: null,
	_pendingSyncSelect: false,

	/**
	 * Public API: Schedule or synchronously execute a Tree Navigator rebuild.
	 * Preserves the exact loadComponents() signature so all existing call sites work unchanged.
	 * @param {Object} [options] - Optional configuration ({ sync: true } to flush immediately)
	 */
	loadComponents: function(options = {}) {
		this._renderCallCount++;

		if (options && options.sync === true) {
			if (this._renderRafId !== null && typeof cancelAnimationFrame === "function") {
				cancelAnimationFrame(this._renderRafId);
				this._renderRafId = null;
			}
			this._renderScheduled = false;
			this._flushRender();
			return;
		}

		if (!this._renderScheduled) {
			this._renderScheduled = true;
			const scheduleFn = (typeof requestAnimationFrame === "function")
				? requestAnimationFrame
				: (cb) => setTimeout(cb, 16);
			this._renderRafId = scheduleFn(() => {
				this._renderRafId = null;
				this._flushRender();
			});
		}
	},

	/**
	 * Flush any pending scheduled render and execute queued selection callbacks.
	 */
	_flushRender: function() {
		const coalesced = this._renderCallCount || 1;
		this._renderScheduled = false;
		this._renderCallCount = 0;

		try {
			this._renderTree(coalesced);
		} catch (err) {
			console.error("[Align TreeNavigator] Error during tree render:", err);
		}

		if (this._pendingSelectNode) {
			const nodeToSelect = this._pendingSelectNode;
			this._pendingSelectNode = null;
			if (typeof this._selectComponentImmediate === "function") {
				this._selectComponentImmediate(nodeToSelect);
			}
		} else if (this._pendingSyncSelect) {
			this._pendingSyncSelect = false;
			if (typeof this.syncWithBuilderSelection === "function") {
				this.syncWithBuilderSelection();
			}
		}
	},

	/**
	 * Select a component in the Tree Navigator.
	 * If a tree render is currently scheduled for the next frame, queues the node
	 * so it is selected and expanded immediately after the tree DOM is rebuilt.
	 * @param {HTMLElement} node - Target canvas DOM element
	 */
	selectComponent: function(node) {
		if (!node) return false;
		if (this._renderScheduled) {
			this._pendingSelectNode = node;
		}
		if (typeof this._selectComponentImmediate === "function") {
			return this._selectComponentImmediate(node);
		}
		return false;
	},

	/**
	 * Core synchronous Tree Navigator DOM renderer.
	 * @param {number} [coalesced=1] - Number of loadComponents() calls coalesced into this render
	 */
	_renderTree: function(coalesced = 1) {
		const startTime = (typeof performance !== "undefined" && performance.now) ? performance.now() : Date.now();
		let self = this;

		if (!this.container) {
			this.container = document.querySelector(this.selector);
		}
		if (!this.container) return;

		let treeContainer = this.container.querySelector(".tree");
		if (!treeContainer) return;

		const prevScrollTop = treeContainer.scrollTop || 0;
		let list = treeContainer.querySelector("ol");

		// Snapshot currently open checkboxes in the tree before replacing <ol>
		if (list) {
			try {
				list.querySelectorAll("li[data-component] > input[type=checkbox]:checked").forEach(input => {
					let li = input.closest("li[data-component]");
					let astId = li?._astId || (li?._treeNode?.getAttribute ? li._treeNode.getAttribute("data-ast-id") : null);
					if (astId) self.expandedAstIds.add(astId);
				});
			} catch (err) {
				console.warn("[Align TreeNavigator] Failed to snapshot expanded tree states:", err);
			}
		}

		let doc = window.FrameDocument || (Vvveb.Builder?.iframe?.contentWindow ? Vvveb.Builder.iframe.contentWindow.document : null);
		if (!doc || !doc.body) return;

		if (Vvveb.AstParser && typeof Vvveb.AstParser.syncDom === "function") {
			Vvveb.AstParser.syncDom(doc.body);
		}

		this.tree = [];
		this.idToNode = {};
		getNodeTree(doc.body, this.tree, {}, this.idToNode);

		let ol = drawComponentsTree(this.tree);

		// Restore all preserved expanded nodes on the newly generated <ol>
		if (self.expandedAstIds && self.expandedAstIds.size > 0) {
			ol.querySelectorAll("li[data-component]").forEach(li => {
				let astId = li._astId || (li._treeNode?.getAttribute ? li._treeNode.getAttribute("data-ast-id") : null);
				if (astId && self.expandedAstIds.has(astId)) {
					let input = li.querySelector(":scope > input[type=checkbox]");
					if (input) input.checked = true;
				}
			});
		}

		if (list) {
			list.replaceWith(ol);
		} else {
			treeContainer.appendChild(ol);
		}

		// Restore scroll position to eliminate visual jumping
		treeContainer.scrollTop = prevScrollTop;

		if (typeof this.initSortable === "function") {
			this.initSortable();
		}

		if (typeof this.syncWithBuilderSelection === "function") {
			this.syncWithBuilderSelection();
		}

		const nodeCount = Object.keys(this.idToNode).length;
		const duration = Math.round(((typeof performance !== "undefined" && performance.now) ? performance.now() : Date.now()) - startTime);
		console.log(`[Align TreeNavigator] Render: ${nodeCount} nodes in ${duration}ms | coalesced=${coalesced} calls`);
	}
});
