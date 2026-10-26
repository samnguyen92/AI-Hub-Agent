/**
 * Align Builder Engine - Word Editor Mode Controller
 * Provides distraction-free document writing, floating bubble toolbar,
 * gutter inserter (+), and image quick actions.
 *
 * @package Align
 */

Vvveb.WordEditor = {
	isActive: false,
	doc: null,
	activeDocContainer: null,
	currentNode: null,
	selectedImage: null,
	selectedFigure: null,

	init: function(doc) {
		if (!doc) return;
		this.doc = doc;
		this._initToolbarEvents();
		this._initGutterEvents();
		this._initImageToolbarEvents();
		this._bindDocEvents(doc);
		console.log("[Align WordEditor] Initialized Word Editor module.");
	},

	checkAndInitEmptyCanvas: function() {
		let body = Vvveb.Builder.frameBody;
		if (!body || !this.doc) return false;

		let visualChildren = Array.from(body.children).filter(el => {
			let tag = el.tagName ? el.tagName.toUpperCase() : "";
			return !["SCRIPT", "STYLE", "LINK", "META", "NOSCRIPT", "TEMPLATE"].includes(tag) 
				&& !el.classList.contains("loading-message") 
				&& !el.hasAttribute("data-vvveb-helpers");
		});

		let existingDoc = body.querySelector(".align-document-container");
		if (existingDoc) {
			this.activeDocContainer = existingDoc;
			this.setupDocumentEditable(existingDoc);
			return true;
		}

		let bodyText = (body.innerText || "").trim();
		if (visualChildren.length === 0 || (visualChildren.length === 1 && bodyText === "")) {
			console.log("[Align WordEditor] Blank canvas detected. Injecting Document Canvas...");
			let docArticle = this.doc.createElement("article");
			docArticle.className = "align-document-container container py-5";
			docArticle.setAttribute("contenteditable", "true");
			docArticle.setAttribute("spellcheck", "false");
			docArticle.innerHTML = '<h1 class="align-doc-title" data-placeholder="Document Title..."></h1><p class="align-doc-p" data-placeholder="Start typing your text here (press Enter for new paragraph)..."></p>';
			body.appendChild(docArticle);

			this.activeDocContainer = docArticle;
			this.setupDocumentEditable(docArticle);
			if (Vvveb.Undo) Vvveb.Undo.addMutation({ type: "childList", target: body, addedNodes: [docArticle], removedNodes: [] });
			if (Vvveb.TreeList) Vvveb.TreeList.loadComponents();
			return true;
		}
		return false;
	},

	setupDocumentEditable: function(container) {
		if (!container) return;
		container.setAttribute("contenteditable", "true");
		container.setAttribute("spellcheck", "false");
		this.isActive = true;
		document.body.classList.add("is-word-mode");
		document.getElementById("iframe-layer")?.classList.add("word-active");
	},

	_bindDocEvents: function(doc) {
		let self = this;
		doc.addEventListener("selectionchange", () => self._onSelectionChange());
		doc.addEventListener("keyup", () => {
			self._onSelectionChange();
			self._updateGutterPosition();
		});
		doc.addEventListener("mouseup", () => setTimeout(() => {
			self._onSelectionChange();
			self._updateGutterPosition();
		}, 10));
		doc.defaultView?.addEventListener("scroll", () => self._onSelectionChange());

		doc.addEventListener("click", function(e) {
			if (e.target && e.target.tagName === "IMG") {
				let figure = e.target.closest("figure") || e.target;
				self.selectImage(e.target, figure);
			} else {
				self.hideImageToolbar();
			}
		});

		doc.addEventListener("keydown", e => self._handleKeydown(e));
	},

	_handleKeydown: function(e) {
		let sel = this.doc.getSelection();
		if (!sel || !sel.rangeCount) return;
		let node = sel.anchorNode;
		if (node && node.nodeType === 3) node = node.parentNode;
		if (!node || !node.closest(".align-document-container")) return;

		// 1. Enter key in Title: creates a new Paragraph
		if (e.key === "Enter" && !e.shiftKey) {
			let block = node.closest("p, h1, h2, h3, blockquote, li");
			if (block && (block.tagName === "H1" || block.classList.contains("align-doc-title"))) {
				e.preventDefault();
				let newP = this.doc.createElement("p");
				newP.className = "align-doc-p";
				newP.setAttribute("data-placeholder", "Start typing your text here...");
				newP.innerHTML = "<br>";
				block.parentNode.insertBefore(newP, block.nextSibling);
				this._focusNode(newP);
				return;
			}
		}

		// 2. Space key: Markdown shortcuts (# H1, ## H2, ### H3, - List, > Quote)
		if (e.key === " " && node.tagName === "P") {
			let text = node.textContent;
			const shortcutMap = {
				"#": () => this.convertBlock(node, "h1"),
				"##": () => this.convertBlock(node, "h2"),
				"###": () => this.convertBlock(node, "h3"),
				"-": () => this.convertBlockToList(node, "ul"),
				"*": () => this.convertBlockToList(node, "ul"),
				"1.": () => this.convertBlockToList(node, "ol"),
				">": () => this.convertBlock(node, "blockquote")
			};
			if (shortcutMap[text]) {
				e.preventDefault();
				shortcutMap[text]();
			}
		}
	},

	convertBlock: function(element, newTag) {
		let newEl = this.doc.createElement(newTag);
		newEl.innerHTML = "<br>";
		element.parentNode.replaceChild(newEl, element);
		this._focusNode(newEl);
	},

	convertBlockToList: function(element, listTag) {
		let list = this.doc.createElement(listTag);
		let li = this.doc.createElement("li");
		li.innerHTML = "<br>";
		list.appendChild(li);
		element.parentNode.replaceChild(list, element);
		this._focusNode(li);
	},

	_onSelectionChange: function() {
		let sel = this.doc ? this.doc.getSelection() : null;
		if (!sel || !sel.rangeCount) {
			this.hideBubbleToolbar();
			return;
		}

		if (!sel.isCollapsed && sel.toString().trim().length > 0) {
			let range = sel.getRangeAt(0);
			let rect = range.getBoundingClientRect();
			if (rect && (rect.width > 0 || rect.height > 0)) {
				this.showBubbleToolbar(rect);
				return;
			}
		}


		this.hideBubbleToolbar();
	},

	showBubbleToolbar: function(rect) {
		let toolbar = document.getElementById("word-bubble-toolbar");
		if (!toolbar || !rect) return;
		if (rect.width === 0 && rect.height === 0) return;

		// Suppress builder selection and highlight overlays while editing text
		let highlightBox = document.getElementById("highlight-box");
		let selectBox = document.getElementById("select-box");
		if (highlightBox) highlightBox.style.setProperty("display", "none", "important");
		if (selectBox) selectBox.style.setProperty("display", "none", "important");

		// Calculate available width and clamp toolbar within canvas boundaries
		let toolbarWidth = toolbar.offsetWidth || 340;
		let toolbarHalf = toolbarWidth / 2;
		let iframeWrapper = document.getElementById("iframe-wrapper") || document.body;
		let wrapperRect = iframeWrapper.getBoundingClientRect();
		let maxAvailableWidth = wrapperRect.width || window.innerWidth;

		let selCenterX = Math.round(rect.left + (rect.width / 2));
		let clampedX = Math.max(toolbarHalf + 16, Math.min(maxAvailableWidth - toolbarHalf - 16, selCenterX));
		let arrowOffset = selCenterX - clampedX;
		let maxArrowOffset = toolbarHalf - 24;
		arrowOffset = Math.max(-maxArrowOffset, Math.min(maxArrowOffset, arrowOffset));

		toolbar.style.left = clampedX + "px";
		toolbar.style.setProperty("--arrow-x", `calc(50% + ${arrowOffset}px)`);

		// Smart vertical placement: if too close to top (< 65px), show below selection
		let isNearTop = rect.top < 65;
		if (isNearTop) {
			toolbar.classList.add("is-below");
			toolbar.style.top = Math.round(rect.bottom) + "px";
		} else {
			toolbar.classList.remove("is-below");
			toolbar.style.top = Math.round(rect.top) + "px";
		}

		toolbar.style.display = "flex";
		this._syncToolbarStates();
	},

	hideBubbleToolbar: function() {
		let toolbar = document.getElementById("word-bubble-toolbar");
		if (toolbar) toolbar.style.display = "none";
	},

	_syncToolbarStates: function() {
		let formatSelect = document.getElementById("word-block-format");
		if (formatSelect && this.doc) {
			let sel = this.doc.getSelection();
			let node = sel && sel.anchorNode ? (sel.anchorNode.nodeType === 3 ? sel.anchorNode.parentNode : sel.anchorNode) : null;
			let block = node ? node.closest("p, h1, h2, h3, h4, h5, h6, blockquote") : null;
			if (!block && Vvveb.WysiwygEditor?.element) {
				block = Vvveb.WysiwygEditor.element.closest("p, h1, h2, h3, h4, h5, h6, blockquote");
			}
			if (block && formatSelect.querySelector(`option[value="${block.tagName.toLowerCase()}"]`)) {
				formatSelect.value = block.tagName.toLowerCase();
			}
		}

		let toolbar = document.getElementById("word-bubble-toolbar");
		if (toolbar && this.doc) {
			try {
				toolbar.querySelector('[data-word-action="bold"]')?.classList.toggle("active", !!this.doc.queryCommandState("bold"));
				toolbar.querySelector('[data-word-action="italic"]')?.classList.toggle("active", !!this.doc.queryCommandState("italic"));
				toolbar.querySelector('[data-word-action="underline"]')?.classList.toggle("active", !!this.doc.queryCommandState("underline"));
				toolbar.querySelector('[data-word-action="strike"]')?.classList.toggle("active", !!this.doc.queryCommandState("strikeThrough"));
			} catch (e) {}
		}
	},

	_updateGutterPosition: function() {
		let gutter = document.getElementById("word-gutter-inserter");
		if (!gutter || !this.doc) return;
		let sel = this.doc.getSelection();
		if (!sel || !sel.rangeCount) {
			this.hideGutterInserter();
			return;
		}
		let node = sel.anchorNode;
		if (node && node.nodeType === 3) node = node.parentNode;
		let block = node ? node.closest("p, h1, h2, h3, blockquote") : null;

		if (block && block.closest(".align-document-container") && block.textContent.trim() === "" && !block.classList.contains("align-doc-title")) {
			let rect = block.getBoundingClientRect();
			gutter.style.left = Math.round(rect.left - 10) + "px";
			gutter.style.top = Math.round(rect.top + (rect.height / 2)) + "px";
			gutter.style.display = "flex";
			this.currentNode = block;
		} else {
			this.hideGutterInserter();
		}
	},

	hideGutterInserter: function() {
		let gutter = document.getElementById("word-gutter-inserter");
		let menu = document.getElementById("word-gutter-menu");
		if (gutter) gutter.style.display = "none";
		if (menu) menu.style.display = "none";
		let toggleBtn = document.getElementById("word-gutter-toggle-btn");
		if (toggleBtn) toggleBtn.classList.remove("active");
	},

	_initToolbarEvents: function() {
		let self = this;
		let toolbar = document.getElementById("word-bubble-toolbar");
		if (!toolbar) return;

		toolbar.addEventListener("mousedown", e => {
			if (e.target && e.target.tagName !== "SELECT") e.preventDefault();
		});

		toolbar.querySelectorAll("[data-word-action]").forEach(btn => {
			btn.addEventListener("click", function(e) {
				e.preventDefault();
				self.execFormat(this.getAttribute("data-word-action"));
			});
		});

		let formatSelect = document.getElementById("word-block-format");
		if (formatSelect) {
			formatSelect.addEventListener("change", function() {
				self.setBlockFormat(this.value);
				if (self.doc && Vvveb.WysiwygEditor?.element) {
					Vvveb.WysiwygEditor.element.focus();
				}
			});
		}
	},

	_initGutterEvents: function() {
		let self = this;
		let toggleBtn = document.getElementById("word-gutter-toggle-btn");
		let menu = document.getElementById("word-gutter-menu");

		if (toggleBtn && menu) {
			toggleBtn.addEventListener("click", function(e) {
				e.preventDefault();
				e.stopPropagation();
				let isOpen = menu.style.display === "flex";
				menu.style.display = isOpen ? "none" : "flex";
				toggleBtn.classList.toggle("active", !isOpen);
			});
		}

		if (menu) {
			menu.querySelectorAll("[data-insert]").forEach(btn => {
				btn.addEventListener("click", function(e) {
					e.preventDefault();
					self.insertItem(this.getAttribute("data-insert"));
					self.hideGutterInserter();
				});
			});
		}
	},

	_initImageToolbarEvents: function() {
		let self = this;
		let toolbar = document.getElementById("word-image-toolbar");
		if (!toolbar) return;

		toolbar.querySelectorAll("[data-img-size]").forEach(btn => {
			btn.addEventListener("click", e => { e.preventDefault(); self.setImageSize(btn.getAttribute("data-img-size")); });
		});

		toolbar.querySelectorAll("[data-img-align]").forEach(btn => {
			btn.addEventListener("click", e => { e.preventDefault(); self.setImageAlign(btn.getAttribute("data-img-align")); });
		});

		let replaceBtn = document.getElementById("word-img-replace-btn");
		if (replaceBtn) replaceBtn.addEventListener("click", e => { e.preventDefault(); self.replaceImage(); });

		let deleteBtn = document.getElementById("word-img-delete-btn");
		if (deleteBtn) deleteBtn.addEventListener("click", e => { e.preventDefault(); self.deleteImage(); });
	},

	execFormat: function(action) {
		if (!this.doc) return;
		const actions = {
			"bold": () => this.doc.execCommand("bold", false, null),
			"italic": () => this.doc.execCommand("italic", false, null),
			"underline": () => this.doc.execCommand("underline", false, null),
			"strike": () => this.doc.execCommand("strikeThrough", false, null),
			"bullet-list": () => this.doc.execCommand("insertUnorderedList", false, null),
			"numbered-list": () => this.doc.execCommand("insertOrderedList", false, null),
			"unlink": () => this.doc.execCommand("unlink", false, null),
			"link": () => { let u = prompt("Enter URL:", "https://"); if (u) this.doc.execCommand("createLink", false, u); }
		};
		if (actions[action]) actions[action]();
		this._syncToolbarStates();
	},

	setBlockFormat: function(tag) {
		if (!this.doc) return;
		this.doc.execCommand("formatBlock", false, "<" + tag + ">");
		let sel = this.doc.getSelection();
		if (sel && sel.anchorNode) {
			let node = sel.anchorNode.nodeType === 3 ? sel.anchorNode.parentNode : sel.anchorNode;
			let newBlock = node ? node.closest("p, h1, h2, h3, h4, h5, h6, blockquote") : null;
			if (newBlock && Vvveb.WysiwygEditor) {
				Vvveb.WysiwygEditor.element = newBlock;
				if (Vvveb.Builder) Vvveb.Builder.texteditEl = newBlock;
			}
		}
		this._onSelectionChange();
	},

	insertItem: function(type) {
		if (!this.currentNode) return;
		let targetNode = this.currentNode;

		if (type === "image") {
			this.openMediaModal(url => {
				let figure = this.doc.createElement("figure");
				figure.className = "align-doc-figure";
				figure.style.width = "100%";
				let img = this.doc.createElement("img");
				img.src = url;
				img.alt = "";
				figure.appendChild(img);
				targetNode.parentNode.insertBefore(figure, targetNode.nextSibling);
				this.selectImage(img, figure);
			});
		} else if (type === "h2" || type === "blockquote") {
			this.convertBlock(targetNode, type);
		} else if (type === "bullet-list" || type === "numbered-list") {
			this.convertBlockToList(targetNode, type === "bullet-list" ? "ul" : "ol");
		} else if (type === "hr") {
			let hr = this.doc.createElement("hr"), p = this.doc.createElement("p");
			p.innerHTML = "<br>";
			targetNode.parentNode.insertBefore(hr, targetNode);
			targetNode.parentNode.insertBefore(p, hr.nextSibling);
			this._focusNode(p);
		}
	},

	_focusNode: function(node) {
		let range = this.doc.createRange();
		range.setStart(node, 0);
		range.collapse(true);
		let sel = this.doc.getSelection();
		sel.removeAllRanges();
		sel.addRange(range);
	},

	openMediaModal: function(callback) {
		if (window.wp && window.wp.media) {
			let frame = wp.media({ title: "Select or Upload Image", multiple: false, library: { type: "image" } });
			frame.on("select", function() {
				let attachment = frame.state().get("selection").first().toJSON();
				if (attachment && attachment.url) callback(attachment.url);
			});
			frame.open();
		} else {
			let manualUrl = prompt("Enter image URL:", "https://");
			if (manualUrl) callback(manualUrl);
		}
	},

	selectImage: function(img, figure) {
		this.selectedImage = img;
		this.selectedFigure = figure;
		let figRect = figure.getBoundingClientRect();
		let toolbar = document.getElementById("word-image-toolbar");
		if (toolbar) {
			toolbar.style.left = Math.round(figRect.left + (figRect.width / 2)) + "px";
			toolbar.style.top = Math.round(figRect.top) + "px";
			toolbar.style.display = "flex";
		}
	},

	hideImageToolbar: function() {
		let toolbar = document.getElementById("word-image-toolbar");
		if (toolbar) toolbar.style.display = "none";
		this.selectedImage = null;
		this.selectedFigure = null;
	},

	setImageSize: function(percent) {
		if (this.selectedFigure) {
			this.selectedFigure.style.width = percent + "%";
			this.selectImage(this.selectedImage, this.selectedFigure);
		}
	},

	setImageAlign: function(align) {
		if (this.selectedFigure) {
			const alignMap = { "center": ["auto", "auto"], "left": ["0", "auto"], "right": ["auto", "0"] };
			if (alignMap[align]) {
				this.selectedFigure.style.marginLeft = alignMap[align][0];
				this.selectedFigure.style.marginRight = alignMap[align][1];
			}
			this.selectImage(this.selectedImage, this.selectedFigure);
		}
	},

	replaceImage: function() {
		if (this.selectedImage) this.openMediaModal(url => { this.selectedImage.src = url; });
	},

	deleteImage: function() {
		if (this.selectedFigure) {
			this.selectedFigure.remove();
			this.hideImageToolbar();
		}
	}
};
