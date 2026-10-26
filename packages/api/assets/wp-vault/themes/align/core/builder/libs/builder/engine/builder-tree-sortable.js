/**
 * Align Builder Engine - DOM Tree Navigator Sortable & Drag-Drop Logic
 * Handles nested drag-and-drop reordering with 3-zone hit testing (before/inside/after)
 * @package Align
 */

window.Vvveb = window.Vvveb || {};
Vvveb.TreeList = Vvveb.TreeList || {};

Object.assign(Vvveb.TreeList, {
	sortableInstances: [],
	_activeDrag: null,
	_activeDrop: null,

	_leafTags: new Set([
		"area", "base", "br", "col", "embed", "hr", "img", "input", "link", "meta", "param", "source", "track", "wbr",
		"p", "h1", "h2", "h3", "h4", "h5", "h6", "span", "a", "button", "label", "textarea", "select", "option",
		"iframe", "video", "audio", "picture", "svg", "canvas", "code", "pre", "strong", "em", "b", "i", "small", "sub", "sup"
	]),

	/**
	 * Determine whether a target DOM element can accept nested child elements in the Tree Navigator
	 */
	canAcceptChildren: function(targetNode) {
		if (!targetNode || !targetNode.tagName) return false;
		const tag = targetNode.tagName.toLowerCase();
		if (this._leafTags.has(tag)) return false;
		if (targetNode.hasAttribute && (
			targetNode.hasAttribute("data-component-shortcode") ||
			targetNode.hasAttribute("data-component-wp-block") ||
			targetNode.hasAttribute("data-component-post-content")
		)) {
			return false;
		}
		return true;
	},

	/**
	 * Initialize SortableJS instances on all nested lists in Tree Navigator
	 */
	initSortable: function() {
		if (this.sortableInstances) {
			this.sortableInstances.forEach(instance => {
				if (instance && instance.destroy) instance.destroy();
			});
		}
		this.sortableInstances = [];

		if (!window.Sortable) return;

		let container = document.querySelector(this.selector + " .tree");
		if (!container) return;

		let self = this;
		let clearDropIndicators = function() {
			let treeEl = document.querySelector(self.selector + " .tree");
			if (treeEl) {
				treeEl.querySelectorAll(".tree-drop-inside, .tree-drop-before, .tree-drop-after").forEach(el => {
					el.classList.remove("tree-drop-inside", "tree-drop-before", "tree-drop-after");
				});
			}
		};

		let updateDropTarget = function(clientX, clientY) {
			if (!self._activeDrag || !self._activeDrag.draggedLi) return;
			let draggedLi = self._activeDrag.draggedLi;

			let elUnder = (clientX && clientY) ? document.elementFromPoint(clientX, clientY) : null;
			let targetLi = elUnder ? elUnder.closest(self.selector + " .tree li[data-component]") : null;

			clearDropIndicators();

			if (targetLi && targetLi !== draggedLi && !draggedLi.contains(targetLi) && targetLi._treeNode) {
				let targetLabel = targetLi.querySelector(":scope > label");
				if (targetLabel) {
					let rect = targetLabel.getBoundingClientRect();
					let offsetY = clientY - rect.top;
					let allowInside = self.canAcceptChildren(targetLi._treeNode);

					let dropMode = "inside";
					if (!allowInside) {
						dropMode = (offsetY < rect.height * 0.5) ? "before" : "after";
					} else {
						// 3-Zone Trigger calculated against the 26px row label:
						// Top 25%: Drop BEFORE (sibling)
						// Middle 50%: Drop INSIDE (nest as child)
						// Bottom 25%: Drop AFTER (sibling)
						if (offsetY < rect.height * 0.25) {
							dropMode = "before";
						} else if (offsetY > rect.height * 0.75) {
							dropMode = "after";
						} else {
							dropMode = "inside";
						}
					}

					targetLi.classList.add("tree-drop-" + dropMode);
					self._activeDrop = {
						draggedLi: draggedLi,
						targetLi: targetLi,
						dropMode: dropMode
					};
				}
			} else {
				self._activeDrop = null;
			}
		};

		let onGlobalDragMove = function(e) {
			let clientX = e.clientX || (e.touches && e.touches[0] ? e.touches[0].clientX : 0);
			let clientY = e.clientY || (e.touches && e.touches[0] ? e.touches[0].clientY : 0);
			updateDropTarget(clientX, clientY);
		};

		let lists = container.querySelectorAll("ol");
		lists.forEach(ol => {
			let instance = new Sortable(ol, {
				group: {
					name: 'tree-navigator',
					pull: false,
					put: false
				},
				sort: false,
				filter: '.tree-node-actions, .tree-node-action, input[type=checkbox]',
				preventOnFilter: false,
				animation: 150,
				forceFallback: true,
				fallbackClass: 'sortable-fallback',
				fallbackTolerance: 3,
				ghostClass: 'sortable-ghost',
				chosenClass: 'sortable-chosen',
				onStart: (evt) => {
					if (Vvveb.Builder?.contentMode) return false;
					self._activeDrag = { draggedLi: evt.item };
					self._activeDrop = null;
					document.addEventListener("mousemove", onGlobalDragMove, { passive: true });
					document.addEventListener("touchmove", onGlobalDragMove, { passive: true });
				},
				onMove: function(evt, originalEvent) {
					if (Vvveb.Builder?.contentMode) return false;
					if (originalEvent) {
						let clientX = originalEvent.clientX || (originalEvent.touches && originalEvent.touches[0] ? originalEvent.touches[0].clientX : 0);
						let clientY = originalEvent.clientY || (originalEvent.touches && originalEvent.touches[0] ? originalEvent.touches[0].clientY : 0);
						updateDropTarget(clientX, clientY);
					}
					return false;
				},
				onEnd: (evt) => {
					document.removeEventListener("mousemove", onGlobalDragMove);
					document.removeEventListener("touchmove", onGlobalDragMove);
					clearDropIndicators();

					if (Vvveb.Builder?.contentMode) return;

					let activeDrop = self._activeDrop;
					let itemEl = evt.item;
					self._activeDrag = null;
					self._activeDrop = null;

					if (!itemEl || !itemEl._treeNode || !activeDrop || !activeDrop.targetLi || !activeDrop.dropMode) {
						self.loadComponents();
						return;
					}

					let nodeA = itemEl._treeNode;
					let targetLi = activeDrop.targetLi;
					let dropMode = activeDrop.dropMode;

					if (targetLi === itemEl || !targetLi._treeNode) {
						self.loadComponents();
						return;
					}

					let targetNode = targetLi._treeNode;
					if (!nodeA || !targetNode || nodeA === targetNode) {
						self.loadComponents();
						return;
					}

					// Circular move guard: prevent moving an ancestor into its own descendant
					if (nodeA.contains && nodeA.contains(targetNode)) {
						console.warn("[Align DragDrop] Circular move rejected: cannot move parent into its own descendant");
						self.loadComponents();
						return;
					}

					// Leaf / non-container element guard
					const targetTag = (targetNode.tagName || "").toLowerCase();
					if (dropMode === "inside" && !self.canAcceptChildren(targetNode)) {
						dropMode = "after";
					}

					let oldParentNode = nodeA.parentNode;
					let oldNextSiblingNode = nodeA.nextSibling;
					let newParentNode = null;
					let newNextSiblingNode = null;

					if (dropMode === "inside") {
						newParentNode = targetNode;
						newNextSiblingNode = null;
					} else if (dropMode === "before") {
						newParentNode = targetNode.parentNode;
						newNextSiblingNode = targetNode;
					} else if (dropMode === "after") {
						newParentNode = targetNode.parentNode;
						newNextSiblingNode = targetNode.nextSibling;
					}

					if (!newParentNode) {
						self.loadComponents();
						return;
					}

					try {
						if (newNextSiblingNode && newNextSiblingNode.parentNode === newParentNode) {
							newParentNode.insertBefore(nodeA, newNextSiblingNode);
						} else {
							let helperBox = newParentNode.querySelector ? newParentNode.querySelector('#vvvebjs-new-section, [data-vvveb-helpers]') : null;
							if (helperBox && helperBox.parentNode === newParentNode) {
								newParentNode.insertBefore(nodeA, helperBox);
							} else {
								newParentNode.appendChild(nodeA);
							}
						}

						if (dropMode === "inside" && self.expandedAstIds) {
							let targetAstId = targetLi._astId || (targetNode.getAttribute ? targetNode.getAttribute("data-ast-id") : null);
							if (targetAstId) {
								self.expandedAstIds.add(targetAstId);
							}
						}

						let undoRecorded = false;
						if (Vvveb.Undo && (oldParentNode !== newParentNode || oldNextSiblingNode !== nodeA.nextSibling)) {
							Vvveb.Undo.addMutation({
								type: 'move',
								target: nodeA,
								oldParent: oldParentNode,
								newParent: newParentNode,
								oldNextSibling: oldNextSiblingNode,
								newNextSibling: nodeA.nextSibling
							});
							undoRecorded = true;
						}

						console.log(`[Align DragDrop] Tree move: <${(nodeA.tagName || '').toLowerCase()}> -> ${dropMode} -> <${targetTag}> | undo=${undoRecorded ? 'recorded' : 'skipped'}`);
					} catch (err) {
						console.error("[Align DragDrop] Error executing DOM tree move:", err);
					}

					if (Vvveb.SectionList) {
						Vvveb.SectionList.loadSections();
					}

					self.loadComponents();

					if (Vvveb.Builder) {
						Vvveb.Builder.selectNode(nodeA);
						self.selectComponent(nodeA);
					}
				}
			});
			this.sortableInstances.push(instance);
		});
	}
});
