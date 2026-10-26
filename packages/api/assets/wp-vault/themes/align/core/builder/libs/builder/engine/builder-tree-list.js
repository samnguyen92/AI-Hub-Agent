/**
 * Align Builder Engine - DOM Tree Navigator
 * Multi-selection and tree hierarchy controller
 * @package Align
 */

Vvveb.TreeList = {
	selector: '#tree-list',
	container: null,
	tree: [],
	idToNode: {},
	selectedLis: new Set(),
	_lastClickedLi: null,
	expandedAstIds: new Set(),

	init: function() {
		this.container = document.querySelector(this.selector);
		let header = this.container ? this.container.querySelector(".header") : null;
		let isDown = false;
		let offset = [0, 0];
		let self = this;

		if (header) {
			header.addEventListener('mousedown', function(e) {
				if (e.which == 1) { // Left click
					isDown = true;
					offset = [
						self.container.offsetLeft - e.clientX,
						self.container.offsetTop - e.clientY
					];
				}
			}, true);
		}

		document.addEventListener('mouseup', function() {
			isDown = false;
		}, true);

		document.addEventListener('mousemove', function(event) {
			if (isDown && self.container) {
				event.preventDefault();
				let left = Math.max(event.clientX + offset[0], 0);
				let top = Math.max(event.clientY + offset[1], 0);

				if (left >= 0 && (left < (window.innerWidth - self.container.clientWidth))) self.container.style.left = left + "px";
				if (top >= 0 && (top < (window.innerHeight - self.container.clientHeight))) self.container.style.top  = top + "px";
			}
		});

		// Listen for explicit chevron expand/collapse toggles to maintain expandedAstIds SSOT
		document.querySelector(this.selector)?.addEventListener("change", function (e) {
			let input = e.target.closest("input[type=checkbox]");
			if (input) {
				let li = input.closest("li[data-component]");
				let astId = li?._astId || (li?._treeNode?.getAttribute ? li._treeNode.getAttribute("data-ast-id") : null);
				if (astId) {
					if (input.checked) {
						self.expandedAstIds.add(astId);
						console.log("[Align TreeNavigator] Node expanded:", astId);
					} else {
						self.expandedAstIds.delete(astId);
						console.log("[Align TreeNavigator] Node collapsed:", astId);
					}
				}
			}
		});

		document.querySelector(this.selector)?.addEventListener("click", function (e) {
			let cloneBtn = e.target.closest(".tree-node-clone");
			if (cloneBtn) {
				if (Vvveb.Builder?.contentMode) return false;
				e.preventDefault();
				e.stopPropagation();
				let li = cloneBtn.closest("li[data-component]");
				if (li && li._treeNode) {
					if (!Vvveb.Builder?.selectedEls || !Vvveb.Builder.selectedEls.has(li._treeNode)) {
						self.singleSelectComponent(li, li._treeNode);
					}
					if (Vvveb.Builder && typeof Vvveb.Builder.cloneSelectedNodes === "function") {
						Vvveb.Builder.cloneSelectedNodes();
					}
				}
				return false;
			}

			let deleteBtn = e.target.closest(".tree-node-delete");
			if (deleteBtn) {
				if (Vvveb.Builder?.contentMode) return false;
				e.preventDefault();
				e.stopPropagation();
				let li = deleteBtn.closest("li[data-component]");
				if (li && li._treeNode) {
					if (!Vvveb.Builder?.selectedEls || !Vvveb.Builder.selectedEls.has(li._treeNode)) {
						self.singleSelectComponent(li, li._treeNode);
					}
					if (Vvveb.Builder && typeof Vvveb.Builder.deleteSelectedNodes === "function") {
						Vvveb.Builder.deleteSelectedNodes();
					}
				}
				return false;
			}

			let element = e.target.closest("li[data-component] label");
			if (!element) {
				let liEl = e.target.closest("li[data-component]");
				if (liEl && !e.target.closest(".tree-node-actions") && !e.target.closest("input[type=checkbox]")) {
					element = liEl.querySelector(":scope > label");
				}
			}
			if (element) {
				e.preventDefault();
				let li = element.closest("li[data-component]") || element.parentNode;
				let node = li._treeNode;
				if ((!node || !node.isConnected) && li._astId) {
					let doc = window.FrameDocument || (Vvveb.Builder?.iframe?.contentWindow?.document);
					if (doc) {
						node = doc.querySelector(`[data-ast-id="${li._astId}"]`);
						if (node) li._treeNode = node;
					}
				}
				if (!node) return;

				if (e.ctrlKey || e.metaKey) {
					self.toggleSelectComponent(li, node);
				} else if (e.shiftKey && self._lastClickedLi) {
					self.rangeSelectComponent(li, node);
				} else {
					self.singleSelectComponent(li, node);
				}
			}
		});

		document.querySelector(this.selector)?.addEventListener("mousemove", function (e) {
			let element = e.target.closest("li[data-component]");
			if (element) {
				let node = element._treeNode;
				if ((!node || !node.isConnected) && element._astId) {
					let doc = window.FrameDocument || (Vvveb.Builder?.iframe?.contentWindow?.document);
					if (doc) {
						node = doc.querySelector(`[data-ast-id="${element._astId}"]`);
						if (node) element._treeNode = node;
					}
				}
				if (node?.dispatchEvent) {
					node.dispatchEvent(new MouseEvent("mousemove", { bubbles: true, cancelable: true }));
				}
			}
		});

		document.addEventListener("shown.bs.tab", function(event) {
			if (event.target && (event.target.id === "pages-tab" || event.target.getAttribute("href") === "#pages-tabs")) {
				self.loadComponents();
				if (Vvveb.Builder.selectedEl) self.selectComponent(Vvveb.Builder.selectedEl);
			}
		});

		document.addEventListener("focusin", function(e) {
			if (!e.target?.closest || !e.target.closest("#right-panel .component-properties")) return;
			if (!Vvveb.Builder?.selectedEl) return;
			self.syncWithBuilderSelection();
			console.log("[Align TreeNavigator] Preserved active selection while sidebar input is focused.");
		});

		window.addEventListener("Vvveb.FileManager.loadPage", function() {
			self.expandedAstIds.clear();
			console.log("[Align TreeNavigator] Reset expanded nodes cache for new page");
		});

		self.loadComponents();
	},

	/**
	 * Single item selection handler
	 */
	singleSelectComponent: function(li, node) {
		this.selectedLis = new Set([li]);
		this._lastClickedLi = li;
		this._updateTreeVisuals();

		if (node) {
			if (Vvveb.scrollToNode) Vvveb.scrollToNode(node);
			if (Vvveb.Builder) {
				Vvveb.Builder.selectNode(node);
				Vvveb.Builder.loadNodeComponent(node);
			}
		}
		console.log("[Align TreeNavigator] Single select:", node?.tagName || node);
	},

	/**
	 * Toggle item selection on Ctrl/Cmd + click
	 */
	toggleSelectComponent: function(li, node) {
		if (this.selectedLis.has(li)) {
			this.selectedLis.delete(li);
			if (this.selectedLis.size === 0) {
				this._lastClickedLi = null;
				if (Vvveb.Builder) Vvveb.Builder.clearSelection();
			} else if (this.selectedLis.size === 1) {
				const remainingLi = Array.from(this.selectedLis)[0];
				this._lastClickedLi = remainingLi;
				if (remainingLi._treeNode && Vvveb.Builder) {
					Vvveb.Builder.selectNode(remainingLi._treeNode);
					Vvveb.Builder.loadNodeComponent(remainingLi._treeNode);
				}
			} else {
				const nodes = Array.from(this.selectedLis).map(item => item._treeNode).filter(Boolean);
				const primaryNode = nodes[nodes.length - 1];
				if (Vvveb.Builder) Vvveb.Builder.selectMultipleNodes(nodes, primaryNode);
			}
		} else {
			this.selectedLis.add(li);
			this._lastClickedLi = li;
			const nodes = Array.from(this.selectedLis).map(item => item._treeNode).filter(Boolean);
			if (nodes.length === 1 && Vvveb.Builder) {
				Vvveb.Builder.selectNode(node);
				Vvveb.Builder.loadNodeComponent(node);
			} else if (Vvveb.Builder) {
				Vvveb.Builder.selectMultipleNodes(nodes, node);
			}
		}
		this._updateTreeVisuals();
		console.log(`[Align TreeNavigator] Multi-select count: ${this.selectedLis.size}`);
	},

	/**
	 * Range selection on Shift + click
	 */
	rangeSelectComponent: function(targetLi, targetNode) {
		if (!this._lastClickedLi || !this.container) {
			this.singleSelectComponent(targetLi, targetNode);
			return;
		}

		const allLis = Array.from(this.container.querySelectorAll(".tree li[data-component]"));
		const startIdx = allLis.indexOf(this._lastClickedLi);
		const endIdx = allLis.indexOf(targetLi);

		if (startIdx !== -1 && endIdx !== -1) {
			const min = Math.min(startIdx, endIdx);
			const max = Math.max(startIdx, endIdx);
			const rangeLis = allLis.slice(min, max + 1);

			this.selectedLis = new Set(rangeLis);
			this._updateTreeVisuals();

			const nodes = rangeLis.map(item => item._treeNode).filter(Boolean);
			if (Vvveb.Builder) {
				Vvveb.Builder.selectMultipleNodes(nodes, targetNode);
			}
			console.log(`[Align TreeNavigator] Range selected: ${rangeLis.length} elements`);
		}
	},

	/**
	 * Update CSS classes (.active, .multi-selected) on Tree Navigator nodes
	 */
	_updateTreeVisuals: function() {
		if (!this.container) this.container = document.querySelector(this.selector);
		if (!this.container) return;

		this.container.querySelectorAll(".tree li > label").forEach(label => {
			label.classList.remove("active", "multi-selected");
		});

		const count = this.selectedLis.size;
		if (count === 1) {
			const singleLi = Array.from(this.selectedLis)[0];
			let label = singleLi ? singleLi.querySelector(":scope > label") : null;
			if (label) label.classList.add("active");
		} else if (count > 1) {
			this.selectedLis.forEach(li => {
				let label = li.querySelector(":scope > label");
				if (label) {
					label.classList.add("multi-selected");
					if (li._treeNode && Vvveb.Builder && li._treeNode === Vvveb.Builder.selectedEl) {
						label.classList.add("active");
					}
				}
			});
		}
	},

	/**
	 * Synchronize tree selection with Vvveb.Builder.selectedEls
	 */
	syncWithBuilderSelection: function() {
		if (!this.container) this.container = document.querySelector(this.selector);
		if (!this.container) return;

		this.selectedLis = new Set();
		const allLis = this.container.querySelectorAll(".tree li[data-component]");
		const selectedNodes = Vvveb.Builder?.selectedEls && Vvveb.Builder.selectedEls.size > 0 ? Array.from(Vvveb.Builder.selectedEls) : (Vvveb.Builder?.selectedEl ? [Vvveb.Builder.selectedEl] : []);
		const selectedAstIds = new Set(selectedNodes.map(node => node?.getAttribute ? node.getAttribute("data-ast-id") : null).filter(Boolean));

		if (selectedNodes.length > 1) {
			allLis.forEach(li => {
				if ((li._treeNode && Vvveb.Builder.selectedEls.has(li._treeNode)) || (li._astId && selectedAstIds.has(li._astId))) {
					this.selectedLis.add(li);
				}
			});
		} else if (selectedNodes.length === 1) {
			allLis.forEach(li => {
				if (li._treeNode === selectedNodes[0] || (li._astId && selectedAstIds.has(li._astId))) {
					this.selectedLis.add(li);
				}
			});
		}

		if (selectedNodes.length && !this.selectedLis.size) {
			console.warn("[Align TreeNavigator] Active tree item not found during selection sync.");
		}
		this._updateTreeVisuals();
	},

	/**
	 * Immediate component selection implementation (wrapped by builder-tree-render.js scheduler)
	 */
	_selectComponentImmediate: function(node) {
		if (!node) return false;
		if (!this.container) this.container = document.querySelector(this.selector);
		if (!this.container) return false;

		let targetLi = null;

		for (const i in this.idToNode) {
			if (node == this.idToNode[i]) {
				let element = document.getElementById("id" + i);
				if (element) targetLi = element.closest("li[data-component]");
				break;
			}
		}

		if (!targetLi) {
			const astId = node.getAttribute ? node.getAttribute("data-ast-id") : null;
			const lis = this.container.querySelectorAll(".tree li[data-component]");
			for (let li of lis) {
				if (li._treeNode === node || (astId && li._astId === astId)) {
					targetLi = li;
					break;
				}
			}
		}

		// If still not found, the node may be a hidden-from-tree child (e.g. <source> or <img> inside <picture>).
		// Walk up DOM ancestors until we find the nearest one that has a corresponding tree li.
		if (!targetLi && node.parentElement) {
			let ancestor = node.parentElement;
			while (ancestor && ancestor !== document.body) {
				const lis = this.container.querySelectorAll(".tree li[data-component]");
				const ancestorAstId = ancestor.getAttribute ? ancestor.getAttribute("data-ast-id") : null;
				for (let li of lis) {
					if (li._treeNode === ancestor || (ancestorAstId && li._astId === ancestorAstId)) {
						targetLi = li;
						break;
					}
				}
				if (targetLi) {
					console.log("[Align TreeNavigator] Hidden child clicked — bubbled up to nearest tree ancestor:", ancestor.tagName);
					break;
				}
				ancestor = ancestor.parentElement;
			}
		}

		if (targetLi) {
			this.selectedLis = new Set([targetLi]);
			this._lastClickedLi = targetLi;

			// Expand parents
			let parent = targetLi;
			let current = targetLi;
			while (parent = current.closest("li")) {
				current = parent.parentNode;
				let input = parent.querySelector(":scope > input[type=checkbox]");
				if (input) {
					input.checked = true;
					let astId = parent._astId || (parent._treeNode?.getAttribute ? parent._treeNode.getAttribute("data-ast-id") : null);
					if (astId) this.expandedAstIds.add(astId);
				}
			}

			this._updateTreeVisuals();

			// Scroll into view in tree only if target label is outside visible container bounds
			let treeContainer = this.container.querySelector(".tree") || this.container;
			if (treeContainer) {
				let labelEl = targetLi.querySelector(":scope > label") || targetLi;
				if (treeContainer.getBoundingClientRect && labelEl.getBoundingClientRect) {
					let cRect = treeContainer.getBoundingClientRect();
					let lRect = labelEl.getBoundingClientRect();
					if (cRect.height > 0 && lRect.height > 0) {
						if (lRect.top < cRect.top) {
							treeContainer.scrollTop -= (cRect.top - lRect.top);
						} else if (lRect.bottom > cRect.bottom) {
							treeContainer.scrollTop += (lRect.bottom - cRect.bottom);
						}
					}
				}
			}
		}
		return false;
	},

	selectComponent: function(node) {
		return this._selectComponentImmediate(node);
	},

	loadComponents: function(options = {}) {
		if (typeof this._renderTree === "function") {
			this._renderTree();
		}
	}
};
