/**
 * Align Builder Engine - Code Editors & Two-Way Live Sync (CodeMirror 6, Lezer AST, Multi-Tab HTML/CSS/JS)
 * @package Align
 */

function updateSyncBadge(isSyncing) {
	const badge = document.getElementById("code-editor-sync-badge");
	if (!badge) return;
	badge.className = isSyncing
		? "badge bg-warning-subtle text-warning border border-warning-subtle d-flex align-items-center gap-1 py-1 px-2 small"
		: "badge bg-success-subtle text-success border border-success-subtle d-flex align-items-center gap-1 py-1 px-2 small";
	badge.innerHTML = isSyncing
		? '<span class="spinner-border spinner-border-sm" role="status" style="width:0.75rem;height:0.75rem;"></span> <span>Syncing...</span>'
		: '<i class="la la-check-circle"></i> <span>Live Sync</span>';
}

function markSaveDirty() {
	updateSyncBadge(false);
	if (window.AlignBuilderModules?.WpSaveHandler?.setButtonState) {
		window.AlignBuilderModules.WpSaveHandler.setButtonState("dirty");
	} else {
		document.querySelectorAll("#top-panel .save-btn").forEach(el => el.removeAttribute("disabled"));
	}
}

Vvveb.CodeEditor = {
	isActive: false, oldValue: '', doc: false, codemirror: false,
	_timer: null, _syncTimer: null, _isInternalUpdating: false, isDirty: false, lastCleanValue: '',
	scheduleSync: function(delayMs = 300) {
		if (!this.isActive || this._isInternalUpdating) return;
		clearTimeout(this._syncTimer);
		this._syncTimer = setTimeout(() => {
			this._syncTimer = null;
			if (this.isActive && !this._isInternalUpdating) this.setValue();
		}, delayMs);
	},
	init: function(doc) {
		if (this.codemirror == false) {
			const textarea = document.querySelector("#vvveb-code-editor textarea");
			if (!textarea) return;
			this.codemirror = CodeMirror.fromTextArea(textarea, {
				mode: 'text/html',
				lineNumbers: true,
				autofocus: true,
				lineWrapping: true
			});
			
			this.isActive = true;
			const self = this;
			this.codemirror.on("change", function (cm, v) { 
				if (v.origin != "setValue" && !self._isInternalUpdating) {
					self.isDirty = true;
					const delayTime = (v.origin === "paste") ? 50 : 500;
					clearTimeout(self._timer);
					updateSyncBadge(true);
					self._timer = setTimeout(() => { self.flush(); }, delayTime);
				}
			});

			const syncIfActive = () => self.scheduleSync(300);
			if (Vvveb.Builder?.frameBody) {
				Vvveb.Builder.frameBody.addEventListener("vvveb.undo.add", syncIfActive);
				Vvveb.Builder.frameBody.addEventListener("vvveb.undo.restore", syncIfActive);
			}
			window.addEventListener("vvveb.undo.add", syncIfActive);
			window.addEventListener("vvveb.undo.restore", syncIfActive);
			if (Vvveb.Builder?.documentFrame) {
				Vvveb.Builder.documentFrame.addEventListener("load", syncIfActive);
			}
			window.addEventListener("vvveb.Builder.selectNode", (e) => self.setSelection(e));
		}
		
		this.isActive = true;
		this.setValue();
		return this.codemirror;
	},

	flush: function(force = false) {
		const hadPendingTimer = !!this._timer;
		if (this._timer) {
			clearTimeout(this._timer);
			this._timer = null;
		}
		if (!this.codemirror) return;
		let currentCode = this.codemirror.getValue();

		if (!force && !hadPendingTimer && !this.isDirty && currentCode === this.lastCleanValue) {
			updateSyncBadge(false);
			return;
		}

		this.isDirty = false;
		this.lastCleanValue = currentCode;

		this._isInternalUpdating = true;
		if (Vvveb.Builder && typeof Vvveb.Builder.setHtml === "function") {
			Vvveb.Builder.setHtml(currentCode);
		}
		this._isInternalUpdating = false;

		if (window.AlignBuilderModules?.WpStyleInjector) {
			window.AlignBuilderModules.WpStyleInjector.replaceDummyImagesInIframe();
		}
		if (Vvveb.TreeList) Vvveb.TreeList.loadComponents();
		if (Vvveb.SectionList) Vvveb.SectionList.loadSections();

		markSaveDirty();
	},

	setSelection: function(e) {
		if (e?.detail?.target && this.codemirror) {
			const value = e.detail.target.outerHTML;
			const cursor = this.codemirror.getSearchCursor(value);
			if (cursor.find(false)) {
				this.codemirror.setSelection(cursor.from(), cursor.to());
				this.codemirror.scrollIntoView({from: cursor.from(), to: cursor.to()}, 5);
			}
		}
	},
	
	getValue: function() {
		return this.codemirror ? this.codemirror.getValue() : '';
	},

	setValue: function(value) {
		if (this._timer) {
			clearTimeout(this._timer);
			this._timer = null;
			updateSyncBadge(false);
		}
		if (this.codemirror) {
			let currentHtml = (typeof value === "string") ? value : (Vvveb.Builder ? Vvveb.Builder.getHtml(true, false) : '');
			if (this.codemirror.getValue() !== currentHtml) {
				this._isInternalUpdating = true;
				const scrollInfo = this.codemirror.getScrollInfo();
				const cursor = this.codemirror.getCursor();
				this.codemirror.setValue(currentHtml);
				this.codemirror.scrollTo(scrollInfo.left, scrollInfo.top);
				if (cursor) this.codemirror.setCursor(cursor);
				this._isInternalUpdating = false;
			}
			this.isDirty = false;
			this.lastCleanValue = currentHtml;
			const self = this;
			setTimeout(function() {
				if (self.codemirror) self.codemirror.refresh();
			}, 150);
		}
	},

	format: function() {
		if (this.codemirror && typeof this.codemirror.formatDocument === "function") {
			this.codemirror.formatDocument();
			this.flush(true);
		}
		console.log("[Align CodeEditor] HTML formatted via js-beautify");
	},

	destroy: function() {
		this.isActive = false;
		window.removeEventListener("vvveb.StyleManager.setStyle", Vvveb.CodeEditor.setStyle);
		window.removeEventListener("vvveb.Builder.selectNode", Vvveb.CodeEditor.setSelection);
	},

	toggle: function() {
		if (!this.isActive) { this.isActive = true; return this.init(); }
		this.isActive = false;
		this.destroy();
	}
};

Vvveb.ModalCodeEditor = {
	modal: false,
	init: function() { this.modal = document.getElementById("codeEditorModal"); },
	show: function() {
		if (!this.modal) this.init();
		if (this.modal && typeof bootstrap !== "undefined") bootstrap.Modal.getOrCreateInstance(this.modal).show();
	},
	hide: function() {
		if (this.modal && typeof bootstrap !== "undefined") bootstrap.Modal.getOrCreateInstance(this.modal).hide();
	},
	setValue: function(value) { if (Vvveb.CodeEditor) Vvveb.CodeEditor.setValue(value); },
	getValue: function() { return Vvveb.CodeEditor ? Vvveb.CodeEditor.getValue() : ""; }
};

Vvveb.CssEditor = {
	_timer: null, oldValue: '', doc: false, textarea: false, codemirror: false, isActive: false,
	init: function(doc) {
		if (this.codemirror == false) {
			this.textarea = document.getElementById("css-editor");
			if (!this.textarea) return;
			this.codemirror = CodeMirror.fromTextArea(this.textarea, {
				mode: 'text/css',
				lineNumbers: true,
				autofocus: true,
				lineWrapping: true
			});		
			
			const self = this;
			this.codemirror.getDoc().on("change", function (e, v) { 
				if (v.origin != "setValue") {
					clearTimeout(self._timer);
					updateSyncBadge(true);
					self._timer = setTimeout(() => { self.flush(); }, 100);
				}
			});
			
			window.addEventListener("vvveb.Builder.selectNode", (e) => Vvveb.CssEditor.setSelection(e));
			window.addEventListener("vvveb.StyleManager.setStyle", Vvveb.CssEditor.setStyle);
		}
				
		this.isActive = true;
		if (Vvveb.StyleManager) {
			const currentCss = Vvveb.StyleManager.getCss() || '';
			if (this.getValue() !== currentCss) {
				this.setValue(currentCss, false);
			}
		}
	},

	flush: function() {
		if (this._timer) {
			clearTimeout(this._timer);
			this._timer = null;
			const cssVal = this.getValue();
			if (Vvveb.StyleManager && typeof cssVal === 'string' && (cssVal.trim() || this.isActive)) {
				Vvveb.StyleManager.setCss(cssVal);
			}
			markSaveDirty();
		}
	},

	getValue: function() {
		return this.codemirror ? this.codemirror.getValue() : (this.textarea ? this.textarea.value : '');
	},
	
	setValue: function(value, updateStyles = true) {
		const val = value || '';
		if (this.codemirror) {
			if (this.codemirror.getValue() !== val) {
				const scrollInfo = this.codemirror.getScrollInfo();
				this.codemirror.setValue(val);
				this.codemirror.scrollTo(scrollInfo.left, scrollInfo.top);
			}
			const self = this;
			setTimeout(function() {
				if (self.codemirror) self.codemirror.refresh();
			}, 150);
		} else if (this.textarea) {
			this.textarea.value = val;
		}

		if (updateStyles && Vvveb.StyleManager) {
			Vvveb.StyleManager.setCss(val);
		}
	},

	setStyle: function(e) {
		if (Vvveb.StyleManager && Vvveb.CssEditor.isActive) {
			Vvveb.CssEditor.setValue(Vvveb.StyleManager.getCss(), false);
		}
	},

	setSelection: function(e) {
		if (e.detail && e.detail.target && this.codemirror && Vvveb.StyleManager) {
			const value = Vvveb.StyleManager.getSelectorForElement(e.detail.target);
			if (value) {
				const cursor = this.codemirror.getSearchCursor(value);
				if (cursor.find(false)) {
					this.codemirror.setSelection(cursor.from(), cursor.to());
					this.codemirror.scrollIntoView({from: cursor.from(), to: cursor.to()}, 5);
				}
			}
		}
	},
	
	format: function() {
		if (this.codemirror && typeof this.codemirror.formatDocument === "function") {
			this.codemirror.formatDocument();
			this.flush();
		}
		console.log("[Align CssEditor] CSS formatted via js-beautify");
	},

	destroy: function() {
		window.removeEventListener("vvveb.StyleManager.setStyle", Vvveb.CssEditor.setStyle);
		window.removeEventListener("vvveb.Builder.selectNode", Vvveb.CssEditor.setSelection);
		this.isActive = false;
	},

	toggle: function() {
		if (!this.isActive) { this.isActive = true; return this.init(); }
		this.isActive = false;
		this.destroy();
	}
};

Vvveb.JsEditor = {
	_timer: null, oldValue: '', doc: false, textarea: false, codemirror: false, isActive: false,
	init: function(doc) {
		if (this.codemirror == false) {
			this.textarea = document.getElementById("js-editor");
			if (!this.textarea) return;
			this.codemirror = CodeMirror.fromTextArea(this.textarea, {
				mode: 'javascript',
				lineNumbers: true,
				autofocus: true,
				lineWrapping: true
			});		
			
			const self = this;
			this.codemirror.getDoc().on("change", function (e, v) { 
				if (v.origin != "setValue") {
					clearTimeout(self._timer);
					updateSyncBadge(true);
					self._timer = setTimeout(() => { self.flush(); }, 200);
				}
			});
		}
				
		this.isActive = true;
		if (typeof Vvveb.JsManager !== "undefined") {
			const js = Vvveb.JsManager.getJs() || '';
			if (this.getValue() !== js) {
				this.setValue(js, false);
			}
		}
	},

	flush: function() {
		if (this._timer) {
			clearTimeout(this._timer);
			this._timer = null;
			const jsVal = this.getValue();
			if (typeof Vvveb.JsManager !== "undefined" && typeof jsVal === 'string' && (jsVal.trim() || this.isActive)) {
				Vvveb.JsManager.setJs(jsVal);
			}
			markSaveDirty();
		}
	},

	getValue: function() {
		return this.codemirror ? this.codemirror.getValue() : (this.textarea ? this.textarea.value : '');
	},
	
	setValue: function(value, updateScripts = true) {
		const val = value || '';
		if (this.codemirror) {
			if (this.codemirror.getValue() !== val) {
				const scrollInfo = this.codemirror.getScrollInfo();
				this.codemirror.setValue(val);
				this.codemirror.scrollTo(scrollInfo.left, scrollInfo.top);
			}
			const self = this;
			setTimeout(function() {
				if (self.codemirror) self.codemirror.refresh();
			}, 150);
		} else if (this.textarea) {
			this.textarea.value = val;
		}

		if (updateScripts && typeof Vvveb.JsManager !== "undefined") {
			Vvveb.JsManager.setJs(val);
		}
	},

	format: function() {
		if (this.codemirror && typeof this.codemirror.formatDocument === "function") {
			this.codemirror.formatDocument();
			this.flush();
		}
		console.log("[Align JsEditor] JS formatted via js-beautify");
	},

	destroy: function() {
		this.isActive = false;
	},

	toggle: function() {
		if (!this.isActive) { this.isActive = true; return this.init(); }
		this.isActive = false;
		this.destroy();
	}
};

// Initialize Bootstrap modal event handlers for Code Editor Modal
document.addEventListener("DOMContentLoaded", function () {
	const modalEl = document.getElementById("codeEditorModal");
	if (!modalEl) return;

	modalEl.addEventListener("shown.bs.modal", function () {
		const doc = window.FrameDocument || (Vvveb.Builder.iframe?.contentWindow ? Vvveb.Builder.iframe.contentWindow.document : null);
		if (doc) {
			if (Vvveb.StyleManager) Vvveb.StyleManager.init(doc);
			if (typeof Vvveb.JsManager !== "undefined") Vvveb.JsManager.init(doc);
		}

		Vvveb.CodeEditor.init();
		Vvveb.CssEditor.init();
		if (typeof Vvveb.JsEditor !== "undefined") Vvveb.JsEditor.init();

		if (Vvveb.Builder && typeof Vvveb.Builder.getHtml === "function") {
			Vvveb.CodeEditor.setValue(Vvveb.Builder.getHtml(true, false));
		}
		if (Vvveb.StyleManager) {
			Vvveb.CssEditor.setValue(Vvveb.StyleManager.getCss() || '', false);
		}
		if (typeof Vvveb.JsEditor !== "undefined" && typeof Vvveb.JsManager !== "undefined") {
			Vvveb.JsEditor.setValue(Vvveb.JsManager.getJs() || '', false);
		}

		setTimeout(function () {
			const activeTab = modalEl.querySelector('.nav-link.active');
			const target = activeTab ? (activeTab.getAttribute("href") || activeTab.getAttribute("data-bs-target")) : '#code-tab';

			if (target === "#code-tab" && Vvveb.CodeEditor.codemirror) {
				Vvveb.CodeEditor.codemirror.refresh();
				if (Vvveb.Builder?.selectedEl) {
					Vvveb.CodeEditor.setSelection({ detail: { target: Vvveb.Builder.selectedEl } });
				}
			} else if (target === "#csscode-tab" && Vvveb.CssEditor.codemirror) {
				Vvveb.CssEditor.codemirror.refresh();
			} else if (target === "#jscode-tab" && typeof Vvveb.JsEditor !== "undefined" && Vvveb.JsEditor.codemirror) {
				Vvveb.JsEditor.codemirror.refresh();
			}
		}, 150);
	});

	modalEl.addEventListener("hidden.bs.modal", function () {
		if (Vvveb.CodeEditor && typeof Vvveb.CodeEditor.flush === "function") Vvveb.CodeEditor.flush();
		if (Vvveb.CssEditor && typeof Vvveb.CssEditor.flush === "function") Vvveb.CssEditor.flush();
		if (typeof Vvveb.JsEditor !== "undefined" && typeof Vvveb.JsEditor.flush === "function") Vvveb.JsEditor.flush();

		Vvveb.CodeEditor.isActive = false;
		Vvveb.CssEditor.isActive = false;
		if (typeof Vvveb.JsEditor !== "undefined") {
			Vvveb.JsEditor.isActive = false;
		}
	});

	// Refresh CodeMirror editor and flush pending changes when switching tabs inside modal
	modalEl.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (tabEl) {
		tabEl.addEventListener("hide.bs.tab", function (event) {
			const currentTarget = event.target.getAttribute("href") || event.target.getAttribute("data-bs-target");
			if (currentTarget === "#csscode-tab" && Vvveb.CssEditor) Vvveb.CssEditor.flush();
			else if (currentTarget === "#jscode-tab" && typeof Vvveb.JsEditor !== "undefined") Vvveb.JsEditor.flush();
			else if (currentTarget === "#code-tab" && Vvveb.CodeEditor) Vvveb.CodeEditor.flush();
		});

		tabEl.addEventListener("shown.bs.tab", function (event) {
			const target = event.target.getAttribute("href") || event.target.getAttribute("data-bs-target");
			if (target === "#code-tab" && Vvveb.CodeEditor.codemirror) {
				const html = Vvveb.Builder ? Vvveb.Builder.getHtml(true, false) : '';
				if (Vvveb.CodeEditor.getValue() !== html) Vvveb.CodeEditor.setValue(html);
				Vvveb.CodeEditor.codemirror.refresh();
			} else if (target === "#csscode-tab" && Vvveb.CssEditor.codemirror) {
				if (Vvveb.StyleManager) {
					const css = Vvveb.StyleManager.getCss() || '';
					if (Vvveb.CssEditor.getValue() !== css) Vvveb.CssEditor.setValue(css, false);
				}
				Vvveb.CssEditor.codemirror.refresh();
			} else if (target === "#jscode-tab" && typeof Vvveb.JsEditor !== "undefined" && Vvveb.JsEditor.codemirror) {
				if (typeof Vvveb.JsManager !== "undefined") {
					const js = Vvveb.JsManager.getJs() || '';
					if (Vvveb.JsEditor.getValue() !== js) Vvveb.JsEditor.setValue(js, false);
				}
				Vvveb.JsEditor.codemirror.refresh();
			}
		});
	});

	// Format code button handler for active tab (HTML/CSS/JS)
	const formatBtn = document.getElementById("code-editor-format-btn");
	if (formatBtn) {
		formatBtn.addEventListener("click", function () {
			const activeTab = modalEl.querySelector('.nav-link.active');
			const target = activeTab ? (activeTab.getAttribute("href") || activeTab.getAttribute("data-bs-target")) : '#code-tab';

			if (target === "#code-tab" && Vvveb.CodeEditor) {
				Vvveb.CodeEditor.format();
			} else if (target === "#csscode-tab" && Vvveb.CssEditor) {
				Vvveb.CssEditor.format();
			} else if (target === "#jscode-tab" && typeof Vvveb.JsEditor !== "undefined") {
				Vvveb.JsEditor.format();
			}

			const originalHtml = formatBtn.innerHTML;
			formatBtn.innerHTML = '<i class="la la-check text-success"></i> <span class="text-success">Formatted!</span>';
			setTimeout(() => { formatBtn.innerHTML = originalHtml; }, 1200);
		});
	}
});
