/**
 * Align Builder Engine - Node Selection & Element Actions
 * Multi-selection management, batch deletion, batch cloning, and pruning algorithms
 * @package Align
 */

Object.assign(Vvveb.Builder, {
	selectedEls: new Set(),

	/**
	 * Prune descendant DOM elements so parent operations do not double-process nested children
	 * @param {Array|Set} elements - Array or Set of DOM elements
	 * @returns {Array} - Top-level DOM elements only
	 */
	pruneDescendantElements: function(elements) {
		if (!elements || elements.length === 0) return [];
		const elArray = Array.from(elements).filter(Boolean);
		return elArray.filter(el => {
			return !elArray.some(other => other !== el && other.contains && other.contains(el));
		});
	},

	/**
	 * Prune descendant AST nodes
	 * @param {Array} astNodes - Array of Virtual AST nodes
	 * @returns {Array} - Top-level AST nodes only
	 */
	pruneAstDescendants: function(astNodes) {
		if (!astNodes || astNodes.length === 0) return [];
		const astArray = Array.from(astNodes).filter(Boolean);
		return astArray.filter(ast => {
			let p = ast.parent;
			while (p) {
				if (astArray.includes(p)) return false;
				p = p.parent;
			}
			return true;
		});
	},

	/**
	 * Select multiple nodes simultaneously
	 * @param {Array} nodes - Array of DOM elements to select
	 * @param {HTMLElement} [primaryNode] - Primary focused element
	 */
	selectMultipleNodes: function(nodes, primaryNode) {
		if (!nodes || nodes.length === 0) {
			this.clearSelection();
			return;
		}

		const filteredNodes = nodes.filter(Boolean);
		if (filteredNodes.length === 1) {
			this.selectNode(filteredNodes[0]);
			return;
		}

		this.selectedEls = new Set(filteredNodes);
		const target = primaryNode || filteredNodes[filteredNodes.length - 1];
		this.selectedEl = target;
		let SelectBox = document.getElementById("select-box");
		let SelectActions = document.getElementById("select-actions");
		let AddSectionBtn = document.getElementById("add-section-btn");

		if (this.texteditEl) {
			Vvveb.WysiwygEditor.destroy(this.texteditEl);
			this.selectPadding = 0;
			SelectBox?.classList.remove("text-edit");
			this.texteditEl = null;
		}

		if (SelectActions) SelectActions.style.display = "flex";
		if (AddSectionBtn) AddSectionBtn.style.display = "none";

		// In Word Mode / Document container, do not show builder select box overlay over text elements
		if (target?.closest && target.closest(".align-document-container")) {
			if (SelectBox) SelectBox.style.display = "none";
			if (SelectActions) SelectActions.style.display = "none";
			if (Vvveb.TreeList?.syncWithBuilderSelection) Vvveb.TreeList.syncWithBuilderSelection();
			return;
		}

		// Compute combined bounding box for select box
		try {
			let minTop = Infinity, minLeft = Infinity, maxRight = -Infinity, maxBottom = -Infinity;
			filteredNodes.forEach(el => {
				if (el.getBoundingClientRect) {
					let box = el.getBoundingClientRect();
					if (box.top < minTop) minTop = box.top;
					if (box.left < minLeft) minLeft = box.left;
					if (box.right > maxRight) maxRight = box.right;
					if (box.bottom > maxBottom) maxBottom = box.bottom;
				}
			});

			if (SelectBox && minTop !== Infinity) {
				let top = minTop - (this.selectPadding || 0);
				let left = minLeft - (this.selectPadding || 0);
				let width = (maxRight - minLeft) + (this.selectPadding || 0) * 2;
				let height = (maxBottom - minTop) + (this.selectPadding || 0) * 2;

				SelectBox.style.top = top + "px";
				SelectBox.style.left = left + "px";
				SelectBox.style.width = width + "px";
				SelectBox.style.height = height + "px";
				SelectBox.style.display = "block";
			}
		} catch (err) {
			console.error("[Align Selection] Error calculating multi-selection bounding box:", err);
		}

		this._updateMultiSelectionUI();

		if (Vvveb.TreeList && typeof Vvveb.TreeList.syncWithBuilderSelection === "function") {
			Vvveb.TreeList.syncWithBuilderSelection();
		}

		window.dispatchEvent(new CustomEvent("Vvveb.Builder.selectNode", {
			detail: { target, multiple: true, count: this.selectedEls.size }
		}));
	},

	/**
	 * Clear all active element selections
	 */
	clearSelection: function() {
		this.selectedEls = new Set();
		this.selectedEl = null;
		let SelectBox = document.getElementById("select-box");
		if (SelectBox) SelectBox.style.display = "none";
		let SelectActions = document.getElementById("select-actions");
		if (SelectActions) SelectActions.style.display = "none";
		let badgeType = document.querySelector("#top-select-element-badge .type");
		let badgeName = document.querySelector("#top-select-element-badge .name");
		if (badgeType) badgeType.textContent = "";
		if (badgeName) badgeName.textContent = "";
		if (Vvveb.TreeList) {
			Vvveb.TreeList.selectedLis = new Set();
			Vvveb.TreeList._lastClickedLi = null;
			if (typeof Vvveb.TreeList._updateTreeVisuals === "function") {
				Vvveb.TreeList._updateTreeVisuals();
			}
		}
		console.log("[Align Selection] Cleared selection");
	},

	/**
	 * Update UI states specifically for multi-selection mode
	 */
	_updateMultiSelectionUI: function() {
		const count = this.selectedEls ? this.selectedEls.size : 0;
		if (count <= 1) return;

		let badgeType = document.querySelector("#top-select-element-badge .type"), badgeName = document.querySelector("#top-select-element-badge .name");
		if (badgeType) badgeType.textContent = "Selected";
		if (badgeName) badgeName.textContent = `${count} elements`;
		let highlightType = document.querySelector("#highlight-name .type"), highlightName = document.querySelector("#highlight-name .name");
		if (highlightType) highlightType.textContent = "Selected";
		if (highlightName) highlightName.textContent = `${count} elements`;

		// Disable single-element specific buttons when multiple items are selected
		['up-btn', 'down-btn', 'parent-btn'].forEach(id => document.getElementById(id)?.classList.add('disabled'));
		['change-img-btn', 'toggle-overwrite-btn'].forEach(id => { const el = document.getElementById(id); if (el) el.style.display = 'none'; });
		const translateBtn = document.getElementById('translate-code-btn');
		if (translateBtn) translateBtn.style.opacity = '0.5';
		this._updateToggleHideBtnUI(Array.from(this.selectedEls));
	},

	/**
	 * Update toggle hide/unhide button state for single or multi selection
	 */
	_updateToggleHideBtnUI: function(nodes) {
		let toggleHideBtn = document.getElementById("toggle-hide-btn");
		if (!toggleHideBtn) return;
		let validNodes = (nodes || []).filter(n => n && n.tagName && n.tagName !== "BODY" && n.tagName !== "HTML");
		if (validNodes.length === 0) { toggleHideBtn.style.display = "none"; return; }
		toggleHideBtn.style.display = "";
		let allHidden = validNodes.every(n => n.getAttribute("data-align-hide") === "true");
		let icon = toggleHideBtn.querySelector("i");
		if (allHidden) {
			toggleHideBtn.classList.add("is-hidden");
			if (icon) icon.className = "la la-eye-slash";
			toggleHideBtn.setAttribute("title", "Unhide element (restore frontend render)");
		} else {
			toggleHideBtn.classList.remove("is-hidden");
			if (icon) icon.className = "la la-eye";
			toggleHideBtn.setAttribute("title", "Hide element from frontend");
		}
	},

	/**
	 * Batch delete all currently selected nodes (with descendant pruning)
	 */
	deleteSelectedNodes: function() {
		if (this.contentMode) return false;
		let nodesToDelete = (this.selectedEls && this.selectedEls.size > 0) ? Array.from(this.selectedEls) : (this.selectedEl ? [this.selectedEl] : []);
		if (nodesToDelete.length === 0) return false;
		const topNodes = this.pruneDescendantElements(nodesToDelete);
		if (topNodes.length === 0) return false;
		console.log(`[Align Selection] Batch deleting ${topNodes.length} top-level nodes (from ${nodesToDelete.length} selected)`);

		try {
			topNodes.forEach(node => {
				if (node && node.parentNode && node.tagName !== "BODY" && node.tagName !== "HTML") {
					if (Vvveb.Undo) {
						Vvveb.Undo.addMutation({
							type: 'childList', target: node.parentNode, removedNodes: [node], nextSibling: node.nextSibling
						});
					}
					node.remove();
				}
			});
		} catch (err) {
			console.error("[Align Selection] Error deleting nodes:", err);
		}

		this.clearSelection();
		if (Vvveb.TreeList?.loadComponents) Vvveb.TreeList.loadComponents();
		if (Vvveb.SectionList?.loadSections) Vvveb.SectionList.loadSections();
		return true;
	},

	/**
	 * Batch clone all currently selected nodes (with descendant pruning)
	 */
	cloneSelectedNodes: function() {
		if (this.contentMode) return false;
		let nodesToClone = (this.selectedEls && this.selectedEls.size > 0) ? Array.from(this.selectedEls) : (this.selectedEl ? [this.selectedEl] : []);
		if (nodesToClone.length === 0) return false;
		const topNodes = this.pruneDescendantElements(nodesToClone);
		if (topNodes.length === 0) return false;
		console.log(`[Align Selection] Batch cloning ${topNodes.length} top-level nodes (from ${nodesToClone.length} selected)`);

		const clonedElements = [];
		try {
			topNodes.forEach(node => {
				if (node && node.parentNode && node.tagName !== "BODY" && node.tagName !== "HTML") {
					let clone = node.cloneNode(true);
					this._disambiguateReuseBlockDom(clone);
					node.after(clone);
					clonedElements.push(clone);
					if (Vvveb.Undo) {
						Vvveb.Undo.addMutation({
							type: 'childList', target: node.parentNode, addedNodes: [clone], nextSibling: clone.nextSibling
						});
					}
				}
			});
		} catch (err) {
			console.error("[Align Selection] Error cloning nodes:", err);
		}

		if (Vvveb.TreeList?.loadComponents) Vvveb.TreeList.loadComponents();
		if (Vvveb.SectionList?.loadSections) Vvveb.SectionList.loadSections();

		if (clonedElements.length === 1) {
			this.selectNode(clonedElements[0]);
			if (Vvveb.TreeList?.selectComponent) Vvveb.TreeList.selectComponent(clonedElements[0]);
		} else if (clonedElements.length > 1) {
			this.selectMultipleNodes(clonedElements, clonedElements[0]);
		}
		return true;
	},

	_disambiguateReuseBlockDom: function(clone) {
		if (!clone?.querySelectorAll) return;
		const nodes = clone.matches?.('[data-component-shortcode], [data-component-wp-block], [data-reuse-block-id], [data-shortcode], [data-wp-block]') ? [clone] : [];
		clone.querySelectorAll('[data-component-shortcode], [data-component-wp-block], [data-reuse-block-id], [data-shortcode], [data-wp-block]').forEach(el => nodes.push(el));
		nodes.forEach(el => {
			let sc = el.getAttribute('data-shortcode') || el.getAttribute('data-wp-block') || (el.textContent?.includes('[') ? el.textContent.trim() : '');
			if (/\[(?:reuse-block|align_block|align_reusable_block|reusable_block|reuse_block)\b/i.test(sc) || el.hasAttribute('data-reuse-block-id')) {
				const uId = 'inst_' + Math.floor(Date.now() % 100000) + '_' + Math.floor(Math.random() * 1000);
				el.setAttribute('data-instance-id', uId);
				if (sc) {
					sc = /\b(?:instance|instance_id|key)=["']?[^"'\s\]]+["']?/i.test(sc) ? sc.replace(/\b(?:instance|instance_id|key)=["']?[^"'\s\]]+["']?/i, `instance="${uId}"`) : sc.replace(/\]$/, ` instance="${uId}"]`);
					el.setAttribute('data-shortcode', sc);
					if (el.hasAttribute('data-wp-block')) el.setAttribute('data-wp-block', sc);
					if (el.textContent?.includes('[')) el.textContent = sc;
				}
			}
		});
	},

	cloneNode: function(node) {
		if (node) this.selectNode(node);
		return this.cloneSelectedNodes();
	},
	deleteNode: function(node) {
		if (node) this.selectNode(node);
		return this.deleteSelectedNodes();
	},

	_initBox: function() {
		let self = this;
		document.getElementById("drag-btn")?.addEventListener("mousedown", function(event) {
			if (self.contentMode || event.which !== 1) return false;
			self.isDragging = true;
			if (self.frameBody) self.frameBody.classList.add('is-dragging-active');
			document.querySelectorAll("#section-actions, #highlight-name, #select-box").forEach(el => el.style.display = "");
			if (self.designerMode) {
				self.dragElement = self.selectedEl;
			} else {
				self.selectedEl.style.position = ""; self.selectedEl.style.top = ""; self.selectedEl.style.left = "";
				self.selectedEl.classList.add("is-dragged");
				self.dragElement = generateElements(Vvveb.dragHtml)[0];
			}
			const node = self.selectedEl;			
			self.dragMoveMutation = { type: 'move', target: node, oldParent: node.parentNode, oldNextSibling: node.nextSibling };
			event.preventDefault();
			return false;
		});

		document.getElementById("toggle-overwrite-btn")?.addEventListener("click", function(event) {
			event.preventDefault();
			const isReusable = typeof Vvveb.Builder.isEditingReusableBlock === "function" 
				? Vvveb.Builder.isEditingReusableBlock() 
				: ((window.VvvebWpConfig && window.VvvebWpConfig.activePostType === 'wp_block'));
			if (!isReusable) return false;
			let node = Vvveb.Builder.selectedEl;
			if (!node) return false;
			let isAllow = node.getAttribute("data-allow-overwrite") === "true";
			let toggleOverwriteBtn = document.getElementById("toggle-overwrite-btn");
			if (isAllow) {
				node.removeAttribute("data-allow-overwrite");
				node.removeAttribute("data-overwrite-id");
				if (toggleOverwriteBtn) { toggleOverwriteBtn.classList.remove("active"); toggleOverwriteBtn.setAttribute("title", "Allow Overwrite on Live Page"); }
			} else {
				node.setAttribute("data-allow-overwrite", "true");
				if (!node.getAttribute("data-overwrite-id")) { node.setAttribute("data-overwrite-id", "ov-" + Math.random().toString(36).substring(2, 10)); }
				if (toggleOverwriteBtn) { toggleOverwriteBtn.classList.add("active"); toggleOverwriteBtn.setAttribute("title", "Overwrite Allowed on Live Page (Click to disable)"); }
			}
			if (Vvveb.Undo) Vvveb.Undo.addMutation({ type: 'attributes', target: node, attributeName: 'data-allow-overwrite' });
			return false;
		});

		document.getElementById("toggle-hide-btn")?.addEventListener("click", function(event) {
			event.preventDefault();
			let nodes = (self.selectedEls && self.selectedEls.size > 0) ? Array.from(self.selectedEls) : (self.selectedEl ? [self.selectedEl] : []);
			nodes = nodes.filter(n => n && n.tagName && n.tagName !== "BODY" && n.tagName !== "HTML");
			if (nodes.length === 0) return false;

			let allHidden = nodes.every(n => n.getAttribute("data-align-hide") === "true");
			let newHideVal = !allHidden;

			nodes.forEach(n => {
				let oldVal = n.getAttribute("data-align-hide");
				if (newHideVal) { n.setAttribute("data-align-hide", "true"); } else { n.removeAttribute("data-align-hide"); }
				if (Vvveb.Undo) {
					Vvveb.Undo.addMutation({ type: 'attributes', target: n, attributeName: 'data-align-hide', oldValue: oldVal, newValue: newHideVal ? "true" : null });
				}
			});

			self._updateToggleHideBtnUI(nodes);
			if (Vvveb.TreeList && typeof Vvveb.TreeList.loadComponents === "function") {
				Vvveb.TreeList.loadComponents();
				if (self.selectedEl && typeof Vvveb.TreeList.selectComponent === "function") {
					Vvveb.TreeList.selectComponent(self.selectedEl);
				}
			}
			console.log(`[Align SelectionManager] Toggled hide state to ${newHideVal} for ${nodes.length} element(s)`);
			return false;
		});

		document.getElementById("down-btn")?.addEventListener("click", function(event) {
			event.preventDefault();
			if (Vvveb.Builder?.contentMode) return false;
			document.getElementById("select-box").style.display = "none";
			Vvveb.Builder.moveNodeDown();
			return false;
		});

		document.getElementById("up-btn")?.addEventListener("click", function(event) {
			event.preventDefault();
			if (Vvveb.Builder?.contentMode) return false;
			document.getElementById("select-box").style.display = "none";
			Vvveb.Builder.moveNodeUp();
			return false;
		});

		document.getElementById("clone-btn")?.addEventListener("click", function(event) {
			event.preventDefault();
			Vvveb.Builder.cloneSelectedNodes();
			return false;
		});

		document.getElementById("parent-btn")?.addEventListener("click", function(event) {
			event.preventDefault();
			const node = self.selectedEl ? self.selectedEl.parentNode : null;
			if (node && node.tagName && node.tagName !== "BODY" && node.tagName !== "HTML") {
				self.selectNode(node);
				self.loadNodeComponent(node);
				Vvveb.TreeList.selectComponent(node);
			}
			return false;
		});

		document.getElementById("save-reusable-btn")?.addEventListener("click", function(event) {
			event.preventDefault();
			if (Vvveb.Builder?.contentMode) return false;
			const node = self.selectedEl;
			if (!node) return false;
			let type = (node.tagName.toLowerCase() === 'section') ? 'section' : 'block';
			const name = prompt("Enter name for new reusable " + type, '');
			if (name) Vvveb.Builder.saveElement(node, type, name);
			return false;
		});
		document.getElementById("delete-btn")?.addEventListener("click", function(event) {
			event.preventDefault();
			Vvveb.Builder.deleteSelectedNodes();
			return false;
		});
		document.getElementById("change-img-btn")?.addEventListener("click", function(event) {
			event.preventDefault();
			let selectedEl = self.selectedEl;
			if (!selectedEl || selectedEl.tagName !== "IMG" || !window.wp?.media) return false;
			let frame = wp.media({ title: 'Select Image from WordPress', button: { text: 'Use image' }, multiple: false });
			frame.on('select', function() {
				let attachment = frame.state().get('selection').first().toJSON();
				let url = attachment.url;
				try { let parsed = new URL(url); url = parsed.pathname + (parsed.search || '') + (parsed.hash || ''); } catch (e) {}
				let oldUrl = selectedEl.getAttribute("src") || "";
				selectedEl.setAttribute("src", url);
				Vvveb.Undo.addMutation({ type: 'attributes', target: selectedEl, attributeName: 'src', oldValue: oldUrl, newValue: url });
				self.selectNode(selectedEl);
			});
			frame.open();
			return false;
		});

		// Initialize Add Section Modal if available
		if (typeof this._initAddSectionModal === "function") {
			this._initAddSectionModal();
		}
	}
});
