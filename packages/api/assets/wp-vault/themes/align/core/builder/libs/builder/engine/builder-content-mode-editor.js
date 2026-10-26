/**
 * Align Theme Builder - Content Mode Inline Text Editor
 * Handles single-click inline editing, hover affordance, keyboard navigation (Tab/Esc),
 * and mode switch cleanup in Content Mode.
 *
 * @package Align
 */

(function (window) {
	'use strict';

	const AlignContentModeEditor = {
		isAttached: false,
		_boundMouseOver: null,
		_boundMouseOut: null,
		_boundCanvasLoad: null,
		_boundContentModeChange: null,
		_currentHoverEl: null,

		init: function () {
			this._boundContentModeChange = (e) => {
				const enabled = !!(e?.detail?.contentMode ?? Vvveb.Builder?.contentMode);
				if (enabled) {
					this.attach();
				} else {
					this.detach();
				}
			};
			window.addEventListener("Vvveb.Builder.contentModeChange", this._boundContentModeChange);

			this._boundCanvasLoad = () => {
				if (Vvveb.Builder?.contentMode) {
					this.attach();
				}
			};
			window.addEventListener("Vvveb.iframe.loaded", this._boundCanvasLoad);

			if (Vvveb.Builder?.contentMode) {
				this.attach();
			}
		},

		attach: function () {
			const frameDoc = Vvveb.Builder?.frameDoc;
			if (!frameDoc || !frameDoc.body) return;

			this.injectStyle(frameDoc);

			if (this.isAttached) return;

			this._boundMouseOver = this.onMouseOver.bind(this);
			this._boundMouseOut = this.onMouseOut.bind(this);

			frameDoc.body.addEventListener("mouseover", this._boundMouseOver, true);
			frameDoc.body.addEventListener("mouseout", this._boundMouseOut, true);
			this.isAttached = true;
			console.log('[Align Builder] Content Mode inline editor attached.');
		},

		detach: function () {
			const frameDoc = Vvveb.Builder?.frameDoc;
			if (frameDoc && frameDoc.body) {
				if (this._boundMouseOver) {
					frameDoc.body.removeEventListener("mouseover", this._boundMouseOver, true);
				}
				if (this._boundMouseOut) {
					frameDoc.body.removeEventListener("mouseout", this._boundMouseOut, true);
				}
				this.removeStyle(frameDoc);
				this.cleanAttributes(frameDoc);
			}

			if (Vvveb.WysiwygEditor?.isActive) {
				Vvveb.WysiwygEditor.destroy();
			}
			if (typeof Vvveb.WordEditor !== "undefined" && Vvveb.WordEditor.hideBubbleToolbar) {
				Vvveb.WordEditor.hideBubbleToolbar();
			}

			const highlightBox = document.getElementById("highlight-box");
			if (highlightBox) {
				highlightBox.classList.remove("align-editable-chip-text", "align-editable-chip-locked");
			}

			this._currentHoverEl = null;
			this.isAttached = false;
			console.log('[Align Builder] Content Mode inline editor detached.');
		},

		injectStyle: function (doc) {
			if (!doc || !doc.head) return;
			if (doc.getElementById("align-content-mode-style")) return;

			const style = doc.createElement("style");
			style.id = "align-content-mode-style";
			style.textContent = `
				[data-align-editable="text"] {
					outline: 1px dashed #0284c7 !important;
					outline-offset: 4px !important;
					cursor: text !important;
				}
				[data-align-editable="locked"] {
					cursor: not-allowed !important;
				}
			`;
			doc.head.appendChild(style);
		},

		removeStyle: function (doc) {
			if (!doc) return;
			const style = doc.getElementById("align-content-mode-style");
			if (style && style.parentNode) {
				style.parentNode.removeChild(style);
			}
		},

		cleanAttributes: function (doc) {
			if (!doc || !doc.body) return;
			try {
				const els = doc.body.querySelectorAll('[data-align-editable]');
				els.forEach((el) => el.removeAttribute('data-align-editable'));
			} catch (e) {
				console.warn('[Align Builder] Error cleaning data-align-editable:', e);
			}
		},

		onMouseOver: function (event) {
			if (!Vvveb.Builder?.contentMode) return;
			const target = event.target;
			if (!target || target.nodeType !== 1) return;

			if (this._currentHoverEl && this._currentHoverEl !== target) {
				this._currentHoverEl.removeAttribute('data-align-editable');
			}

			const highlightBox = document.getElementById("highlight-box");

			if (target.closest('[data-component-shortcode], [data-component-wp-block], [data-component-post-content]')) {
				target.setAttribute('data-align-editable', 'locked');
				this._currentHoverEl = target;
				if (highlightBox) {
					highlightBox.classList.remove("align-editable-chip-text");
					highlightBox.classList.add("align-editable-chip-locked");
				}
				return;
			}

			const resolveFn = Vvveb.Builder?.resolveTextEditableElement;
			const editable = typeof resolveFn === "function" ? resolveFn(target) : null;

			if (editable) {
				editable.setAttribute('data-align-editable', 'text');
				this._currentHoverEl = editable;
				if (highlightBox) {
					highlightBox.classList.remove("align-editable-chip-locked");
					highlightBox.classList.add("align-editable-chip-text");
				}
			} else {
				if (highlightBox) {
					highlightBox.classList.remove("align-editable-chip-text", "align-editable-chip-locked");
				}
			}
		},

		onMouseOut: function (event) {
			if (!Vvveb.Builder?.contentMode) return;
			const target = event.target;
			if (target && target.nodeType === 1) {
				target.removeAttribute('data-align-editable');
			}
			if (this._currentHoverEl) {
				this._currentHoverEl.removeAttribute('data-align-editable');
				this._currentHoverEl = null;
			}
			const highlightBox = document.getElementById("highlight-box");
			if (highlightBox) {
				highlightBox.classList.remove("align-editable-chip-text", "align-editable-chip-locked");
			}
		},

		handleClick: function (target, event) {
			const resolveFn = Vvveb.Builder?.resolveTextEditableElement;
			const clickTarget = event?.target || target;
			const editable = typeof resolveFn === "function" ? resolveFn(clickTarget) : null;

			if (!editable) {
				if (Vvveb.WysiwygEditor?.isActive) {
					Vvveb.WysiwygEditor.destroy();
				}
				if (typeof Vvveb.WordEditor !== "undefined" && Vvveb.WordEditor.hideBubbleToolbar) {
					Vvveb.WordEditor.hideBubbleToolbar();
				}
				console.log('[Align Builder] Content Mode click target is not editable text.');
				return;
			}

			if (editable.hasAttribute('data-align-editable')) {
				editable.removeAttribute('data-align-editable');
			}

			if (typeof Vvveb.Builder?.startTextEdit === "function") {
				Vvveb.Builder.startTextEdit(editable);
				this.setCaretFromPoint(event, editable);
				const tag = editable.tagName || 'UNKNOWN';
				const length = editable.textContent ? editable.textContent.length : 0;
				console.log(`[Align Builder] Content Mode inline edit start: ${tag} | text length=${length}`);
			}
		},

		setCaretFromPoint: function (event, editable) {
			const doc = Vvveb.Builder?.frameDoc;
			if (!doc) return;

			try {
				let range = null;
				if (event && typeof doc.caretRangeFromPoint === "function") {
					range = doc.caretRangeFromPoint(event.clientX, event.clientY);
				} else if (event && typeof doc.caretPositionFromPoint === "function") {
					const pos = doc.caretPositionFromPoint(event.clientX, event.clientY);
					if (pos) {
						range = doc.createRange();
						range.setStart(pos.offsetNode, pos.offset);
						range.collapse(true);
					}
				}

				const win = doc.defaultView || window;
				const sel = win.getSelection();

				if (range && sel && editable.contains(range.startContainer)) {
					sel.removeAllRanges();
					sel.addRange(range);
					return;
				}

				if (sel && editable) {
					range = doc.createRange();
					range.selectNodeContents(editable);
					range.collapse(false);
					sel.removeAllRanges();
					sel.addRange(range);
				}
			} catch (e) {
				console.warn('[Align Builder] Caret positioning fallback:', e);
			}
		},

		getEditableElementsList: function () {
			const frameDoc = Vvveb.Builder?.frameDoc;
			if (!frameDoc || !frameDoc.body) return [];

			const resolveFn = Vvveb.Builder?.resolveTextEditableElement;
			if (typeof resolveFn !== "function") return [];

			const walker = frameDoc.createTreeWalker(
				frameDoc.body,
				NodeFilter.SHOW_ELEMENT,
				null,
				false
			);

			const list = [];
			const seen = new Set();
			let node = walker.currentNode;

			while (node) {
				const editable = resolveFn(node);
				if (editable && !seen.has(editable)) {
					seen.add(editable);
					list.push(editable);
				}
				node = walker.nextNode();
			}

			return list;
		},

		handleTab: function (shiftKey) {
			const current = Vvveb.WysiwygEditor?.element;
			const list = this.getEditableElementsList();
			if (list.length === 0) return;

			let index = list.indexOf(current);
			if (index === -1) {
				index = shiftKey ? list.length - 1 : 0;
			} else {
				index = shiftKey ? index - 1 : index + 1;
				if (index < 0) index = list.length - 1;
				if (index >= list.length) index = 0;
			}

			const nextTarget = list[index];
			if (!nextTarget) return;

			if (Vvveb.WysiwygEditor?.isActive) {
				Vvveb.WysiwygEditor.destroy();
			}

			if (typeof Vvveb.Builder?.startTextEdit === "function") {
				Vvveb.Builder.startTextEdit(nextTarget);
				if (typeof nextTarget.scrollIntoView === "function") {
					try {
						nextTarget.scrollIntoView({ block: 'center', behavior: 'smooth' });
					} catch (e) {
						nextTarget.scrollIntoView(false);
					}
				}
				this.setCaretFromPoint(null, nextTarget);
			}
		}
	};

	window.AlignContentModeEditor = AlignContentModeEditor;

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", () => AlignContentModeEditor.init());
	} else {
		AlignContentModeEditor.init();
	}

})(window);
