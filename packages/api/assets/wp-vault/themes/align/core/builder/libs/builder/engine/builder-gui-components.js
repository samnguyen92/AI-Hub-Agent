/**
 * Align Builder Engine - GUI Panels & Dark Mode
 * @package Align
 */

Object.assign(Vvveb.Gui, {
toggleFileManager: function () {
		Vvveb.Gui.togglePanel("#filemanager", "--builder-filemanager-height");
	},
	
	toggleLeftColumn: function () {
		Vvveb.Gui.togglePanel("#left-panel", "--builder-left-panel-width");
	},	
	
	toggleRightColumn: function (e, rightColumnEnabled = null) {
		let btn = document.getElementById("toggle-right-column-btn");
		let panel = document.getElementById("right-panel");
		let body = document.querySelector("body");

		if (rightColumnEnabled === null) {
			rightColumnEnabled = Vvveb.Gui.togglePanel("#right-panel", "--builder-right-panel-width");
		} else {
			if (!rightColumnEnabled) {
				if (panel) panel.style.display = "none";
				body.style.setProperty("--builder-right-panel-width", "0px");
			} else {
				if (panel) panel.style.display = "";
				body.style.setProperty("--builder-right-panel-width", "");
			}
		}
		
		if (rightColumnEnabled) {
			document.getElementById("vvveb-builder")?.classList.remove("no-right-panel");
			document.querySelector(".component-properties-tab")?.classList.add("d-none");
			if (btn) {
				btn.classList.add("active");
				btn.setAttribute("aria-pressed", "true");
			}
		} else {
			document.getElementById("vvveb-builder")?.classList.add("no-right-panel");
			document.querySelector(".component-properties-tab")?.classList.remove("d-none");
			if (btn) {
				btn.classList.remove("active");
				btn.setAttribute("aria-pressed", "false");
			}
		}
		
		Vvveb.Components.componentPropertiesElement = (rightColumnEnabled ? "#right-panel" :"#left-panel #properties") + " .component-properties";
		let componentTab = document.querySelector("#components-tab");

		if (document.getElementById("properties") && document.getElementById("properties").offsetParent) {
			const bsTab = bootstrap.Tab.getOrCreateInstance(componentTab);
			if (componentTab) componentTab.style.display = "";
			if (bsTab) bsTab.show(); 
		}
	},

	toggleTreeList: function () {
		let treeList = document.getElementById("tree-list");
		treeList.classList.toggle("d-none");
		if (!treeList.classList.contains("d-none")) {
			Vvveb.TreeList.loadComponents();
			if (Vvveb.Builder.selectedEl) {
				Vvveb.TreeList.selectComponent(Vvveb.Builder.selectedEl);
			}
		}
		if (!treeList.offsetParent) {
			document.getElementById("toggle-tree-list")?.classList.remove("active");
		}
	},

	toggleSidebar: function () {
		let container = document.getElementById("container");
		container.classList.toggle("small-nav");
	},
	
	treeListRight: function () {
		let treeList = document.getElementById("tree-list");
		let btnIcon = document.querySelector("[data-vvveb-action='treeListRight'] i");
		if (treeList.style.height) {
			treeList.style.height = "";
			treeList.style.right = "";
			treeList.style.top = "";
			treeList.style.left = "";
			treeList.style.width = "";
			btnIcon.className = "icon-stop-outline";
		} else {
			treeList.style.height = "auto";
			treeList.style.right = "0";
			treeList.style.top = "0";
			treeList.style.left = "auto";
			treeList.style.width = "300px";
			btnIcon.className = "icon-remove-outline";
		}
	},

	treeListPanel: function () {
		let treeList = document.getElementById("tree-list");
		let canvas = document.getElementById("canvas");
		let filemanager = document.getElementById("filemanager");
		let btnIcon = document.querySelector("[data-vvveb-action='treeListPanel'] i");
		
		if (treeList.parentNode == canvas) {
			filemanager.after(treeList);
			btnIcon.className = "icon-chevron-forward-outline";
		} else {
			canvas.append(treeList);
			btnIcon.className = "icon-chevron-back-outline";
		}
	},

	setTheme: function (theme) {
		document.documentElement.setAttribute("data-bs-theme", theme);
		let icon = document.querySelector(".btn-dark-mode i");
		if (icon) {
			if (theme == "dark") {
				icon.classList.remove("la-sun");
				icon.classList.add("la-moon");
			} else {
				icon.classList.remove("la-moon");
				icon.classList.add("la-sun");
			}
		}
	},

	darkMode: function () {
		let theme = document.documentElement.getAttribute("data-bs-theme");
		theme = (theme == "dark") ? "light" : "dark";
		Vvveb.Gui.setTheme(theme);
		localStorage.setItem('theme', theme);
	},
	
	zoomChange: function () {
		let wrapper = document.getElementById("iframe-wrapper");
		if (!wrapper) return;
		let val = parseFloat(this.value);
		let scaleStr = (val && val !== 100) ? " scale(" + (val / 100) + ")" : "";
		wrapper.style.transform = "translate(-50%, -50%)" + scaleStr;
		console.log('[Align Viewport] Zoom scale adjusted to:', (val || 100) + '%');
	},

	calcFitZoom: function (targetW = 1920, targetH = 1080) {
		let canvas = document.getElementById("canvas");
		if (!canvas) return 100;
		let availW = canvas.clientWidth - 60;
		let availH = canvas.clientHeight - 60;
		if (availW <= 0 || availH <= 0) return 100;
		let scale = Math.min(availW / targetW, availH / targetH);
		return Math.min(100, Math.max(10, Math.floor(scale * 100)));
	},

	setViewport: function (breakpoint = "desktop") {
		let btn = document.getElementById(breakpoint + "-view");
		if (btn) {
			Vvveb.Gui.viewport.call(btn);
		} else {
			Vvveb.Gui.viewport.call({ dataset: { view: breakpoint } });
		}
	},

	setState: function () {
		Vvveb.StyleManager.setState(this.value);
		Vvveb.Builder.reloadComponent();
	},

	setContentMode: function(enabled) {
		Vvveb.Builder.contentMode = !!enabled;
		localStorage.setItem('align_builder_content_mode', Vvveb.Builder.contentMode);

		let builderEl = document.getElementById("vvveb-builder");
		if (builderEl) {
			builderEl.classList.toggle("content-mode-active", Vvveb.Builder.contentMode);
		}
		document.body.classList.toggle("content-mode-active", Vvveb.Builder.contentMode);

		// Synchronize content-mode state to iframe canvas and toggle Add component dock
		if (Vvveb.Builder.frameBody) {
			Vvveb.Builder.frameBody.classList.toggle("content-mode-active", Vvveb.Builder.contentMode);
		}
		if (Vvveb.Builder.frameDoc) {
			if (Vvveb.Builder.frameDoc.documentElement) {
				Vvveb.Builder.frameDoc.documentElement.classList.toggle("content-mode-active", Vvveb.Builder.contentMode);
			}
			let newSection = Vvveb.Builder.frameDoc.getElementById("vvvebjs-new-section");
			if (newSection) {
				newSection.style.setProperty("display", Vvveb.Builder.contentMode ? "none" : "", "important");
			}
		}

		let btn = document.getElementById("toggle-content-mode-btn");
		if (btn) {
			let badge = btn.querySelector(".badge-content-mode");
			let title = "";
			if (Vvveb.Builder.contentMode) {
				btn.classList.add("active");
				btn.classList.remove("btn-primary", "btn-light");
				if (badge) {
					badge.textContent = "ON";
					badge.className = "badge badge-content-mode is-on";
				}
				title = "Switch to Design Mode";
				btn.setAttribute("title", title);
			} else {
				btn.classList.remove("active", "btn-primary");
				btn.classList.add("btn-light");
				if (badge) {
					badge.textContent = "OFF";
					badge.className = "badge badge-content-mode is-off";
				}
				title = "Enable Content Mode";
				btn.setAttribute("title", title);
			}
			if (window.AlignBuilderModules?.WpToolbarTooltips?.updateTitle) {
				window.AlignBuilderModules.WpToolbarTooltips.updateTitle(btn, title);
			}
		}

		// Toggle content-panel-tab visibility in sidebar nav
		let contentPanelTabLi = document.querySelector("#elements-tabs .content-panel-tab");
		if (contentPanelTabLi) {
			contentPanelTabLi.classList.toggle("d-none", !Vvveb.Builder.contentMode);
		}

		if (Vvveb.Builder.contentMode) {
			if (typeof Vvveb.WordEditor !== "undefined") {
				Vvveb.WordEditor.checkAndInitEmptyCanvas();
			}

			// Switch to Content Panel or Pages tab in left sidebar if currently open on restricted tab
			let activeNavTab = document.querySelector("#elements-tabs .nav-link.active");
			if (activeNavTab && (activeNavTab.id === "components-tab" || activeNavTab.id === "sections-tab" || activeNavTab.id === "configuration-tab")) {
				let targetTab = document.getElementById("content-panel-tab") || document.getElementById("pages-tab");
				if (targetTab && window.bootstrap?.Tab) {
					bootstrap.Tab.getOrCreateInstance(targetTab).show();
				}
			}

			// Ensure properties inspector is on Content tab
			let contentPropsTab = document.querySelector('#right-panel .content-tab a, #left-panel .content-tab a, a[href="#content-tab"]');
			if (contentPropsTab && window.bootstrap?.Tab) {
				bootstrap.Tab.getOrCreateInstance(contentPropsTab).show();
			}

			// Close code editor if open
			let modalEl = document.getElementById("codeEditorModal");
			if (modalEl && window.bootstrap?.Modal) {
				let bsModal = bootstrap.Modal.getInstance(modalEl);
				if (bsModal) bsModal.hide();
			}
		}

		window.dispatchEvent(new CustomEvent("Vvveb.Builder.contentModeChange", {
			detail: { contentMode: Vvveb.Builder.contentMode }
		}));
	},

	toggleContentMode: function() {
		let nextState = !Vvveb.Builder.contentMode;
		Vvveb.Gui.setContentMode(nextState);
		if (typeof displayToast === "function") {
			if (nextState) {
				displayToast("info", "Content Mode", "Content Mode enabled. Only text, links, and media can be edited.");
			} else {
				displayToast("success", "Design Mode", "Design Mode enabled. Full layout and code editing unlocked.");
			}
		}
	}
});
