/**
 * Align Theme Builder - Page Content Panel (Content Mode Form-Style Editing)
 * Module: Vvveb.ContentPanel / AlignContentPanel
 *
 * Scans editable elements on canvas, groups them by top-level sections,
 * presents textareas with auto-grow and live debounce binding to canvas,
 * maintains WeakMap references, pushes Undo mutations on blur,
 * provides bidirectional sync with inline editor, and supports filter search.
 *
 * @package Align
 */

(function (window) {
	'use strict';

	window.Vvveb = window.Vvveb || {};

	const AlignContentPanel = {
		container: null,
		listEl: null,
		filterInput: null,
		counterEl: null,
		isAttached: false,
		nodeToRowMap: new WeakMap(),
		textareaToNodeMap: new WeakMap(),
		nodeToOriginalValue: new WeakMap(),
		debounceTimers: new WeakMap(),

		init: function () {
			this.container = document.getElementById("content-panel-tabs");
			this.listEl = document.getElementById("content-panel-list");
			this.filterInput = document.getElementById("content-panel-filter-input");
			this.counterEl = this.container ? this.container.querySelector(".badge-content-items-count") : null;

			// Filter search input
			if (this.filterInput) {
				this.filterInput.addEventListener("input", (e) => {
					this.filterRows(e.target.value);
				});
			}

			// Clear filter button
			const clearBtn = this.container ? this.container.querySelector(".clear-filter-btn") : null;
			if (clearBtn && this.filterInput) {
				clearBtn.addEventListener("click", () => {
					this.filterInput.value = "";
					this.filterRows("");
					this.filterInput.focus();
				});
			}

			// Listen to mode change
			window.addEventListener("Vvveb.Builder.contentModeChange", (e) => {
				const enabled = !!(e?.detail?.contentMode ?? Vvveb.Builder?.contentMode);
				if (enabled) {
					this.attach();
				} else {
					this.detach();
				}
			});

			// Listen to canvas load
			window.addEventListener("Vvveb.iframe.loaded", () => {
				if (Vvveb.Builder?.contentMode) {
					this.buildPanel();
				}
			});

			// Canvas -> panel sync: listen to Undo additions & custom align:text-edited event
			window.addEventListener("align:text-edited", (e) => {
				if (e.detail?.target) {
					this.syncFromCanvas(e.detail.target);
				}
			});

			window.addEventListener("vvveb.undo.add", (e) => {
				const mutation = e.detail;
				if (mutation && mutation.type === "characterData" && mutation.target) {
					this.syncFromCanvas(mutation.target);
				}
			});

			window.addEventListener("vvveb.undo.restore", (e) => {
				const mutation = e.detail;
				if (mutation && mutation.type === "characterData" && mutation.target) {
					this.syncFromCanvas(mutation.target);
				}
			});

			if (Vvveb.Builder?.contentMode) {
				this.attach();
			}
		},

		attach: function () {
			if (this.isAttached) return;
			this.isAttached = true;
			this.buildPanel();
			console.log('[Align UI] Page Content panel attached.');
		},

		detach: function () {
			this.isAttached = false;
			if (this.listEl) {
				this.listEl.innerHTML = '<div class="text-muted small text-center p-3 no-content-placeholder">No editable content found.</div>';
			}
			if (this.counterEl) {
				this.counterEl.textContent = '0 items';
			}
			console.log('[Align UI] Page Content panel detached.');
		},

		isInlineOnlyNode: function (node) {
			if (!node || node.nodeType !== 1) return true;
			const allowedTags = new Set(['B', 'STRONG', 'I', 'EM', 'A', 'BR', 'SPAN', 'SMALL', 'U', 'S', 'MARK']);
			for (let i = 0; i < node.children.length; i++) {
				const child = node.children[i];
				if (!allowedTags.has(child.tagName)) {
					return false;
				}
				if (!this.isInlineOnlyNode(child)) {
					return false;
				}
			}
			return true;
		},

		isPlainNode: function (node) {
			if (!node || node.nodeType !== 1) return true;
			return node.children.length === 0;
		},

		getNodeLabel: function (node) {
			const tag = (node.tagName || 'DIV').toUpperCase();
			if (['H1', 'H2', 'H3', 'H4', 'H5', 'H6'].includes(tag)) return 'Heading';
			if (tag === 'P') return 'Paragraph';
			if (tag === 'BUTTON' || (tag === 'A' && node.classList.contains('btn'))) return 'Button';
			if (tag === 'A') return 'Link';
			if (tag === 'LI') return 'List Item';
			if (tag === 'BLOCKQUOTE') return 'Quote';
			return tag;
		},

		buildPanel: function () {
			if (!this.listEl) return;
			const frameDoc = Vvveb.Builder?.frameDoc;
			if (!frameDoc || !frameDoc.body) {
				this.listEl.innerHTML = '<div class="text-muted small text-center p-3 no-content-placeholder">Canvas not loaded.</div>';
				return;
			}

			const resolveFn = Vvveb.Builder?.resolveTextEditableElement;
			if (typeof resolveFn !== "function") return;

			const walker = frameDoc.createTreeWalker(
				frameDoc.body,
				NodeFilter.SHOW_ELEMENT,
				null,
				false
			);

			const items = [];
			const seen = new Set();
			let walkNode = walker.currentNode;

			while (walkNode) {
				const editable = resolveFn(walkNode);
				if (editable && !seen.has(editable)) {
					seen.add(editable);
					items.push(editable);
				}
				walkNode = walker.nextNode();
			}

			this.listEl.innerHTML = '';
			if (items.length === 0) {
				this.listEl.innerHTML = '<div class="text-muted small text-center p-3 no-content-placeholder">No editable content found.</div>';
				if (this.counterEl) this.counterEl.textContent = '0 items';
				return;
			}

			if (this.counterEl) {
				this.counterEl.textContent = `${items.length} ${items.length === 1 ? 'item' : 'items'}`;
			}

			// Group by section or parent container
			const sectionGroups = new Map();
			items.forEach((node, index) => {
				const sectionParent = node.closest('section, header, footer, main, [data-component-section]') || frameDoc.body;
				let groupTitle = 'Main Content';
				if (sectionParent !== frameDoc.body) {
					groupTitle = sectionParent.getAttribute('aria-label') ||
						sectionParent.getAttribute('data-name') ||
						sectionParent.querySelector('h1, h2, h3, h4')?.textContent?.trim()?.slice(0, 30) ||
						(sectionParent.tagName.toLowerCase() + (sectionParent.id ? '#' + sectionParent.id : ''));
				}

				if (!sectionGroups.has(sectionParent)) {
					sectionGroups.set(sectionParent, { title: groupTitle, nodes: [] });
				}
				sectionGroups.get(sectionParent).nodes.push({ node, index });
			});

			let groupCounter = 1;
			sectionGroups.forEach((groupData) => {
				const groupEl = document.createElement('div');
				groupEl.className = 'content-panel-section-group';

				const headingEl = document.createElement('h3');
				headingEl.className = 'content-panel-section-title';
				headingEl.textContent = groupData.title || `Section ${groupCounter}`;
				groupEl.appendChild(headingEl);

				groupData.nodes.forEach(({ node, index }) => {
					const row = this.createRow(node, index);
					groupEl.appendChild(row);
				});

				this.listEl.appendChild(groupEl);
				groupCounter++;
			});

			if (this.filterInput && this.filterInput.value) {
				this.filterRows(this.filterInput.value);
			}

			console.log(`[Align UI] Page Content panel populated with ${items.length} items.`);
		},

		createRow: function (node, index) {
			const row = document.createElement('div');
			row.className = 'content-panel-row';
			row.setAttribute('data-node-index', index);
			row.setAttribute('data-action', 'edit-text');

			const labelBar = document.createElement('div');
			labelBar.className = 'content-panel-label-bar';

			const labelEl = document.createElement('label');
			const inputId = `content-field-${index}`;
			labelEl.className = 'content-panel-label';
			labelEl.setAttribute('for', inputId);
			labelEl.textContent = this.getNodeLabel(node);

			const badgeEl = document.createElement('span');
			badgeEl.className = 'badge bg-light text-dark content-panel-tag-badge border';
			badgeEl.textContent = (node.tagName || '').toLowerCase();

			labelBar.appendChild(labelEl);
			labelBar.appendChild(badgeEl);
			row.appendChild(labelBar);

			const isInline = this.isInlineOnlyNode(node);
			const isPlain = this.isPlainNode(node);

			if (isInline || isPlain) {
				const textarea = document.createElement('textarea');
				textarea.id = inputId;
				textarea.className = 'content-panel-textarea';
				textarea.rows = 2;
				textarea.value = (node.innerText || node.textContent || '').trim();

				this.textareaToNodeMap.set(textarea, node);
				this.nodeToRowMap.set(node, row);
				this.nodeToOriginalValue.set(node, node.innerHTML);

				textarea.addEventListener('focus', () => this.handleFocus(node, row));
				textarea.addEventListener('input', () => this.handleInput(textarea, node, isPlain));
				textarea.addEventListener('blur', () => this.handleBlur(textarea, node));

				row.appendChild(textarea);

				// Initial auto-grow
				setTimeout(() => this.autoGrow(textarea), 0);
			} else {
				// Complex element -> read-only row with "Edit on canvas" button
				const box = document.createElement('div');
				box.className = 'content-panel-readonly-box';

				const preview = document.createElement('div');
				preview.className = 'content-panel-readonly-preview';
				preview.textContent = (node.innerText || node.textContent || '').trim() || '[Complex component]';

				const editBtn = document.createElement('button');
				editBtn.type = 'button';
				editBtn.className = 'content-panel-btn-edit';
				editBtn.textContent = 'Edit on canvas';
				editBtn.addEventListener('click', () => {
					this.focusOnCanvas(node);
				});

				box.appendChild(preview);
				box.appendChild(editBtn);
				row.appendChild(box);

				this.nodeToRowMap.set(node, row);
			}

			return row;
		},

		autoGrow: function (textarea) {
			if (!textarea) return;
			textarea.style.height = 'auto';
			textarea.style.height = `${Math.max(textarea.scrollHeight, 44)}px`;
		},

		handleFocus: function (node, row) {
			// Update active row highlight
			if (this.listEl) {
				this.listEl.querySelectorAll('.content-panel-row.is-active').forEach(el => {
					el.classList.remove('is-active');
				});
			}
			row.classList.add('is-active');

			// Snapshot original value on focus for Undo comparison
			this.nodeToOriginalValue.set(node, node.innerHTML);

			// Select node on canvas and TreeList
			if (Vvveb.Builder && typeof Vvveb.Builder.selectNode === "function") {
				Vvveb.Builder.selectNode(node);
			}
			if (Vvveb.TreeList && typeof Vvveb.TreeList.selectComponent === "function") {
				Vvveb.TreeList.selectComponent(node);
			}
			if (typeof node.scrollIntoView === "function") {
				try {
					node.scrollIntoView({ block: 'center', behavior: 'smooth' });
				} catch (e) {
					node.scrollIntoView(false);
				}
			}
		},

		handleInput: function (textarea, node, isPlain) {
			this.autoGrow(textarea);

			if (this.debounceTimers.has(textarea)) {
				clearTimeout(this.debounceTimers.get(textarea));
			}

			const timer = setTimeout(() => {
				const val = textarea.value;
				if (isPlain) {
					node.textContent = val;
				} else {
					// Inline formatted node: if tags exist, route to inline canvas editor
					// to avoid destroying formatting tags (b/strong/i/em/a)
					console.log('[Align UI] Inline-formatted element edited from panel, routing to canvas inline editor.');
					this.focusOnCanvas(node);
				}
			}, 150);

			this.debounceTimers.set(textarea, timer);
		},

		handleBlur: function (textarea, node) {
			if (this.debounceTimers.has(textarea)) {
				clearTimeout(this.debounceTimers.get(textarea));
				this.debounceTimers.delete(textarea);
			}

			// Ensure plain text content is synced immediately
			const isPlain = this.isPlainNode(node);
			if (isPlain) {
				node.textContent = textarea.value;
			}

			const oldValue = this.nodeToOriginalValue.get(node);
			const newValue = node.innerHTML;

			if (oldValue !== undefined && oldValue !== newValue) {
				if (Vvveb.Undo) {
					Vvveb.Undo.addMutation({
						type: 'characterData',
						target: node,
						oldValue: oldValue,
						newValue: newValue
					});
				}
				this.nodeToOriginalValue.set(node, newValue);
				console.log('[Align UI] Undo mutation recorded from panel blur.');
			}
		},

		focusOnCanvas: function (node) {
			if (!node) return;
			if (Vvveb.Builder && typeof Vvveb.Builder.selectNode === "function") {
				Vvveb.Builder.selectNode(node);
			}
			if (typeof node.scrollIntoView === "function") {
				try {
					node.scrollIntoView({ block: 'center', behavior: 'smooth' });
				} catch (e) {
					node.scrollIntoView(false);
				}
			}
			if (typeof Vvveb.Builder?.startTextEdit === "function") {
				Vvveb.Builder.startTextEdit(node);
			}
		},

		syncFromCanvas: function (targetNode) {
			if (!targetNode || !this.isAttached) return;
			const row = this.nodeToRowMap.get(targetNode);
			if (!row) return;

			const textarea = row.querySelector('textarea');
			if (textarea) {
				const newText = (targetNode.innerText || targetNode.textContent || '').trim();
				if (textarea.value !== newText) {
					textarea.value = newText;
					this.autoGrow(textarea);
				}
				this.nodeToOriginalValue.set(targetNode, targetNode.innerHTML);
			} else {
				const preview = row.querySelector('.content-panel-readonly-preview');
				if (preview) {
					preview.textContent = (targetNode.innerText || targetNode.textContent || '').trim() || '[Complex component]';
				}
			}
			console.log('[Align UI] Panel synced from canvas text change.');
		},

		filterRows: function (query) {
			if (!this.listEl) return;
			const cleanQuery = (query || '').toLowerCase().trim();
			const groups = this.listEl.querySelectorAll('.content-panel-section-group');

			groups.forEach(group => {
				let visibleInGroup = 0;
				const rows = group.querySelectorAll('.content-panel-row');
				rows.forEach(row => {
					const text = (row.textContent || '').toLowerCase();
					const textarea = row.querySelector('textarea');
					const val = (textarea ? textarea.value : '').toLowerCase();
					const match = !cleanQuery || text.includes(cleanQuery) || val.includes(cleanQuery);
					row.classList.toggle('d-none', !match);
					if (match) visibleInGroup++;
				});
				group.classList.toggle('d-none', visibleInGroup === 0 && cleanQuery.length > 0);
			});
		}
	};

	window.AlignContentPanel = AlignContentPanel;
	Vvveb.ContentPanel = AlignContentPanel;

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", () => AlignContentPanel.init());
	} else {
		AlignContentPanel.init();
	}

})(window);
