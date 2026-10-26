/**
 * Align Builder Engine - GUI Controller & Viewport
 * @package Align
 */

Vvveb.Gui = {
	
	init: function() {
		let theme = localStorage.getItem('theme') || 'dark';
		this.setTheme(theme);
		let contentMode = localStorage.getItem('align_builder_content_mode') === 'true';
		if (typeof this.setContentMode === "function") {
			this.setContentMode(contentMode);
		}

		document.querySelectorAll("[data-vvveb-action]").forEach(function (el,i) {
			const on = el.dataset.vvvebOn ?? "click";
			el.addEventListener(on, Vvveb.Gui[el.dataset.vvvebAction]);
		});
		
		this.shortcuts();
		window.addEventListener("resize", function() {
			let currentVp = Vvveb.StyleManager?.currentBreakpoint;
			if (!currentVp || currentVp === "none" || currentVp === "desktop") {
				let fitZoom = Vvveb.Gui?.calcFitZoom ? Vvveb.Gui.calcFitZoom(1920, 1080) : 100;
				let zoomInput = document.getElementById("zoom");
				let wrapper = document.getElementById("iframe-wrapper");
				if (zoomInput && wrapper) {
					zoomInput.value = fitZoom;
					let scaleStr = (fitZoom < 100) ? " scale(" + (fitZoom / 100) + ")" : "";
					wrapper.style.transform = "translate(-50%, -50%)" + scaleStr;
				}
			}
		}, { passive: true });
	},
	
	shortcuts: function() {
		let self = this;

		const isInputActive = function(target) {
			if (!target) target = document.activeElement;
			if (!target) return false;
			const tag = (target.tagName || "").toLowerCase();
			if (tag === "input" || tag === "textarea" || tag === "select") return true;
			if (target.isContentEditable || target.getAttribute("contenteditable") === "true") return true;
			return !!(target.closest && (target.closest(".cm-editor") || target.closest(".CodeMirror") || target.closest("[contenteditable='true']")));
		};

		handleShortcuts = function(e) {
			if (e.key === 'Delete' || e.key === 'Backspace') {
				if (Vvveb.Builder?.contentMode) return;
				if (!isInputActive(e.target)) {
					if (Vvveb.WysiwygEditor?.isActive || Vvveb.Builder?.texteditEl) return;
					if ((Vvveb.Builder?.selectedEls && Vvveb.Builder.selectedEls.size > 0) || Vvveb.Builder?.selectedEl) {
						e.preventDefault();
						e.stopPropagation();
						if (typeof Vvveb.Builder.deleteSelectedNodes === "function") {
							Vvveb.Builder.deleteSelectedNodes();
						}
						return;
					}
				}
			}

			if (e.ctrlKey || e.metaKey) {
				switch (e.key) {
					case 's':
						e.preventDefault();
						let btn = document.querySelector('#top-panel .save-btn, .save-btn');
						if (btn && !btn.disabled) {
							btn.click();
						}
						return;
					case 'z':
						e.preventDefault();
						if (e.shiftKey) {
							self.redo();
						} else {
							self.undo();
						}
						return;
					case 'Z':
					case 'y':
						e.preventDefault();
						self.redo();
						return;
					case 'e':
						e.preventDefault();
						if (Vvveb.Builder?.contentMode) {
							if (typeof displayToast === "function") {
								displayToast("warning", "Content Mode", "Code Editor is locked in Content Mode.");
							}
							return;
						}
						self.toggleEditor();
						return;
					case 'P':
						e.preventDefault();
						if (Vvveb.Builder?.contentMode) return;
						self.newPage();
						return;					
					case 'S':
						e.preventDefault();
						if (Vvveb.Builder?.contentMode) return;
						self.newSection();
						return;
				}
			}
		};
		
		// Handle shortcuts from main window and iframe also
		document.addEventListener('keydown', handleShortcuts);		
		window.addEventListener('vvveb.iframe.loaded', () => {
			if (Vvveb.Builder.frameBody) {
				Vvveb.Builder.frameBody.addEventListener('keydown', handleShortcuts);		
			}
			if (Vvveb.Builder.iframe?.contentWindow?.document) {
				Vvveb.Builder.iframe.contentWindow.document.addEventListener('keydown', handleShortcuts);
			}
		});

	},
	
	undo : function () {
		if (Vvveb.WysiwygEditor.isActive) {
			Vvveb.WysiwygEditor.undo();
		} else {
			Vvveb.Undo.undo();
		}
		Vvveb.Builder.selectNode();
	},
	
	redo : function () {
		if (Vvveb.WysiwygEditor.isActive) {
			Vvveb.WysiwygEditor.redo();
		} else {
			Vvveb.Undo.redo();
		}
		Vvveb.Builder.selectNode();
	},
	
	//show modal with html content
	saveModal: function () {
		document.getElementById('textarea-modal textarea').value = Vvveb.Builder.getHtml();
		document.getElementById('textarea-modal').modal();
	},
    
	//post html content through ajax to save to filesystem/db
	save: function (event, saveUrl = null, saveBtn = null) {
		let btn = saveBtn ?? this;
		saveUrl = saveUrl ?? this.dataset.vvvebUrl;
		let pageData = Vvveb.FileManager.getPageData();
		//if offcanvas check if user provided new template name
		if (btn.classList.contains("save-offcanvas")) {
			if (document.querySelector("#save-offcanvas [name=template]:checked").value == "new") {
				pageData['file'] = document.querySelector("#save-offcanvas [name=folder]").value + "/" + document.querySelector("#save-offcanvas [name=file]").value;
			}
		}

		btn.querySelector(".loading").classList.remove("d-none");
		btn.querySelector(".button-text").classList.add("d-none");
	
		let detail = {...pageData, saveUrl, saveBtn};
		const saveEvent = new CustomEvent("Vvveb.Gui.save", {detail});
		window.dispatchEvent(saveEvent);

			return Vvveb.Builder.save(pageData, saveUrl, (data) => {//success
				//use toast to show save status

				let bg = "success";
				if (!data.success && data != "success") {		
					bg = "danger";
				}
				
				displayToast(bg, "Save", data.message ?? data);

				const offcanvas = document.getElementById('save-offcanvas');
				if (offcanvas) {
					let instance = bootstrap.Offcanvas.getInstance(offcanvas);
					if (instance) instance.hide();			
				}
				
				btn.querySelector(".loading").classList.add("d-none");
				btn.querySelector(".button-text").classList.remove("d-none");
			}, (error) => {//error
				btn.querySelector(".loading").classList.add("d-none");
				btn.querySelector(".button-text").classList.remove("d-none");
				let message = error?.message || error?.statusText || "Error saving!";
				displayToast("danger", "Error", message);
			}, () => {//done
				btn.querySelector(".loading").classList.add("d-none");
				btn.querySelector(".button-text").classList.remove("d-none");
			});	
		
	},
	
	viewport : function () {
		let breakpoint = this.dataset?.view || "desktop";
		let canvas = document.getElementById("canvas");
		let wrapper = document.getElementById("iframe-wrapper");
		if (canvas) canvas.classList.remove("responsive");
		document.querySelectorAll(".responsive-btns .dropdown-menu .btn.active").forEach(e => e.classList.remove("active"));

		if (Vvveb.StyleManager.currentBreakpoint === breakpoint && breakpoint !== "desktop") {
			Vvveb.StyleManager.currentBreakpoint = "none";
			breakpoint = "desktop";
		}
		
		if (Vvveb.Builder.documentFrame) {
			Vvveb.Builder.documentFrame.style.width = "100%";
			Vvveb.Builder.documentFrame.style.height = "100%";
		}
		
		let widthVal = "1920px";
		let heightVal = "1080px";
		let targetW = 1920;
		let targetH = 1080;

		if (breakpoint === "mobile") {
			if (canvas) canvas.classList.add("responsive");
			widthVal = "375px"; heightVal = "812px"; targetW = 375; targetH = 812;
			Vvveb.StyleManager.currentBreakpoint = "mobile";
			let btn = document.getElementById("mobile-view") || this;
			if (btn?.classList) btn.classList.add("active");
		} else if (breakpoint === "tablet") {
			if (canvas) canvas.classList.add("responsive");
			widthVal = "768px"; heightVal = "1024px"; targetW = 768; targetH = 1024;
			Vvveb.StyleManager.currentBreakpoint = "tablet";
			let btn = document.getElementById("tablet-view") || this;
			if (btn?.classList) btn.classList.add("active");
		} else {
			// Desktop standard default size: 1920 x 1080
			widthVal = "1920px"; heightVal = "1080px"; targetW = 1920; targetH = 1080;
			Vvveb.StyleManager.currentBreakpoint = "none";
			let desktopBtn = document.getElementById("desktop-view");
			if (desktopBtn) desktopBtn.classList.add("active");
		}

		if (wrapper) {
			wrapper.style.width = widthVal;
			wrapper.style.height = heightVal;
		}

		console.log('[Align Viewport] Breakpoint changed:', breakpoint, '| Resolution:', widthVal, 'x', heightVal);

		let activeBtn = document.querySelector(".responsive-btns .dropdown-menu .btn.active") || document.getElementById("desktop-view");
		let toggleBtnIcon = document.querySelector(".responsive-btns [data-bs-toggle='dropdown'] i");
		let activeBtnIcon = activeBtn ? activeBtn.querySelector("i") : null;
		if (toggleBtnIcon && activeBtnIcon) {
			toggleBtnIcon.className = activeBtnIcon.className;
		}

		let fitZoom = Vvveb.Gui.calcFitZoom ? Vvveb.Gui.calcFitZoom(targetW, targetH) : 100;
		let zoomInput = document.getElementById("zoom");
		if (zoomInput) {
			zoomInput.value = fitZoom;
		}

		if (wrapper) {
			let scaleStr = (fitZoom < 100) ? " scale(" + (fitZoom / 100) + ")" : "";
			wrapper.style.transform = "translate(-50%, -50%)" + scaleStr;
		}

		// Re-position selection box if an element is currently selected
		if (Vvveb.Builder.selectedEl) {
			setTimeout(() => {
				if (Vvveb.Builder.selectedEl && typeof Vvveb.Builder.selectNode === "function") {
					Vvveb.Builder.selectNode(Vvveb.Builder.selectedEl);
				}
			}, 250);
		}
	},
	
	toggleEditor : function () {
		if (Vvveb.Builder?.contentMode) {
			if (typeof displayToast === "function") {
				displayToast("warning", "Content Mode", "Code Editor is locked in Content Mode.");
			}
			return;
		}
		let modalEl = document.getElementById("codeEditorModal");
		if (!modalEl) return;

		let bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
		bsModal.toggle();
	},
	
	toggleEditorJsExecute : function () {
		Vvveb.Builder.runJsOnSetHtml = this.checked;
	},
	
	search : function () {
		let searchText = (this.value || "").toLowerCase().trim();
		let panel = this.parentNode.parentNode.querySelector("div > ul");
		if (!panel) return;

		panel.querySelectorAll("li.header").forEach(function (headerEl) {
			let subItems = headerEl.querySelectorAll("ol li");
			let hasMatch = false;

			subItems.forEach(function (el) {
				let searchData = (el.dataset.search || "").toLowerCase();
				if (!searchText) {
					el.style.display = "";
				} else if (searchData.indexOf(searchText) > -1) {
					el.style.display = "";
					hasMatch = true;
				} else {
					el.style.display = "none";
				}
			});

			let check = headerEl.querySelector('input.header_check');
			if (check) {
				if (searchText) {
					check.checked = hasMatch;
					headerEl.style.display = hasMatch ? "" : "none";
				} else {
					check.checked = false;
					headerEl.style.display = "";
				}
			}
		});
	},
	
	clearSearch : function (e) {
		let input = this.parentNode.querySelector("input");
		input.value = "";
		input.dispatchEvent(new KeyboardEvent("keyup", {
			bubbles: true,
			cancelable: true,
		}));
	},
	
	toggleSections : function (e) {
		let icon = this.querySelector("i");
		let closed = (this.dataset.closed == "true");

		if (closed) {
			icon.setAttribute("class", "la la-minus");
			this.dataset.closed = "false";
		} else {
			icon.setAttribute("class", "la la-plus");
			this.dataset.closed = "true";
		}
		
		this.parentNode.parentNode.parentNode.querySelectorAll('input.header_check[type="checkbox"]').forEach(e => e.checked = closed);
	},

	//Pages, file/components tree 
	newPage: function () {
		if (Vvveb.Builder?.contentMode) return;
		
		let newPageModal = document.getElementById('new-page-modal');
		let form = newPageModal.querySelector("form");
		
		const bsModal = bootstrap.Modal.getOrCreateInstance(newPageModal);
		bsModal.show(); 

		let submitForm = function(e) {

			let data = {};
			this.querySelectorAll("input[type=text],input[type=number],input[type=checkbox]:checked,input[type=radio]:checked,input[name=image],textarea,select:not(:disabled)").forEach( (el, i) => {
				if (el.offsetParent || el.name == 'image') data[el.name] = el.value;
			});			
			
			if (!data['file']) {
				data['file'] = data['title'] + '.html';
			}
			
			if (!data['name']) {
				data['name'] = data['title'];
			}

			if (data['file']) {
				data['url']  = data['file'] = data['folder'] + "/" + data['file'];
				//data['url']  = Vvveb.themeBaseUrl + data['url'];
			}

			e.preventDefault();
			
			return Vvveb.Builder.save(data, this.getAttribute("action"), function (savedData) {
					data.title = data.name;

					if (typeof savedData === 'object' && savedData !== null) {
						data.name = savedData.name ?? data.name;
						data.url = savedData.url ?? data.url;
						data.file = savedData.file ?? data.file;
						data.title = savedData.title ?? data.title;
					}
					
					delete data['startTemplateUrl'];
					let page = Vvveb.FileManager.addPage(data.name, data);
					Vvveb.FileManager.loadPage(data.name);
					Vvveb.FileManager.scrollToPage(page);
					bsModal.hide();
			}, (error) => {//error
				let message = error?.message || error?.statusText || "Error saving!";
				displayToast("danger", "Error", message);
			}, () => {//done
				delete data['startTemplateUrl'];
				let page = Vvveb.FileManager.addPage(data.name, data);
				Vvveb.FileManager.loadPage(data.name);
				Vvveb.FileManager.scrollToPage(page);
				bsModal.hide();
			});
		};
		
		if (!form.dataset.init) {
			form.addEventListener("submit", submitForm);
			form.dataset.init = true;
		}
	},

	newSection : function (e, onlyBase = false) {
		if (Vvveb.Builder?.contentMode) return false;
	
		let addSectionBox = document.getElementById("add-section-box");
		let addSectionElement = {};
		
		//addSectionElement = self.highlightEl; 
		if (addSectionBox) {
			addSectionBox.style.display  = "flex"; 
			addSectionBox.style.top = "";
			addSectionBox.style.left = "";
			addSectionBox.classList.add("only-sections"); 
			if (onlyBase) {
				addSectionBox.classList.add("only-base"); 
			} else {
				addSectionBox.classList.remove("only-base");
			}
		}

		let bsTab = bootstrap.Tab.getOrCreateInstance(document.getElementById("box-sections-tab"));
		if (bsTab) bsTab.show(); 

		if (e && e.preventDefault) e.preventDefault();
		return false;
			
	},
	
	newComponent : function (e) {
		if (Vvveb.Builder?.contentMode) return false;

		let addSectionBox = document.getElementById("add-section-box");
		if (addSectionBox) {
			addSectionBox.style.display  = "flex"; 
			addSectionBox.style.top = "";
			addSectionBox.style.left = "";
			addSectionBox.classList.remove("only-sections"); 
			addSectionBox.classList.remove("only-base"); 
		}

		let bsTab = bootstrap.Tab.getOrCreateInstance(document.getElementById("box-components-tab"));
		if (bsTab) bsTab.show(); 

		if (e && e.preventDefault) e.preventDefault();
		return false;
	},
	
	//layout
	togglePanel: function (panel, cssVar) {
		panel = document.querySelector(panel);
		let body = document.querySelector("body");
		let prevValue = getComputedStyle(body).getPropertyValue(cssVar);
		let visible = false;
		
		if (prevValue !== "0px") {
			panel.dataset.layoutToggle = prevValue;
			body.style.setProperty(cssVar, "0px");
			panel.style.display = "none";
			visible = false;
		} else {
			prevValue = panel.dataset.layoutToggle;
			body.style.setProperty(cssVar, "");
			panel.style.display = "";
			visible = true;
		}
		
		return visible;
	}
};
