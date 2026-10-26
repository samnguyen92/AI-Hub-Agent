/**
 * Align Builder Engine - Canvas Content Loader & HTML Sync
 * @package Align
 */

Object.assign(Vvveb.Builder, {
_frameLoaded : function() {
		
		let self = Vvveb.Builder;
		
		self.frameDoc  = window.FrameDocument;
		self.frameHtml = window.FrameDocument.querySelector("html");
		self.frameBody = window.FrameDocument.querySelector("body");
		self.frameHead = window.FrameDocument.querySelector("head");
		
		// Ensure editor helpers stylesheet is loaded in canvas
		if (!self.frameHead.querySelector('link[data-vvveb-helpers]')) {
			let helperLink = document.createElement("link");
			helperLink.setAttribute("rel", "stylesheet");
			helperLink.setAttribute("data-vvveb-helpers", "1");
			let baseBuilderUrl = window.VvvebWpConfig?.pluginUrl || Vvveb.baseUrl || 'libs/builder/';
			helperLink.setAttribute("href", baseBuilderUrl + 'css/vvvebjs-editor-helpers.css?v=' + Date.now());
			self.frameHead.appendChild(helperLink);
		}
		self.frameHtml.setAttribute("data-vvvebjs-editor","");

		self._initHighlight();
		
		window.dispatchEvent(new CustomEvent("Vvveb.iframe.loaded", {detail: self.frameDoc}));

		if (window.AlignBuilderModules?.WpContextMenu?.attachCanvas) {
			window.AlignBuilderModules.WpContextMenu.attachCanvas(self.frameDoc);
		}

		document.querySelector(".loading-message").classList.remove("active");
		Vvveb.NewSection.init();
		if (Vvveb.Builder.contentMode) {
			self.frameBody.classList.add("content-mode-active");
			self.frameHtml.classList.add("content-mode-active");
			let newSec = self.frameDoc.getElementById("vvvebjs-new-section");
			if (newSec) {
				newSec.style.setProperty("display", "none", "important");
			}
		}
		Vvveb.StyleList.load(self.frameDoc);
		if (Vvveb.StyleManager) {
			Vvveb.StyleManager.init(self.frameDoc);
		}
		if (typeof Vvveb.JsManager !== "undefined") {
			Vvveb.JsManager.init(self.frameDoc);
		}
		
		if (typeof Vvveb.WordEditor !== "undefined") {
			Vvveb.WordEditor.init(self.frameDoc);
			Vvveb.WordEditor.checkAndInitEmptyCanvas();
		}

		if (Vvveb.TreeList) {
			Vvveb.TreeList.loadComponents();
		}
		if (Vvveb.SectionList) {
			Vvveb.SectionList.loadSections();
		}

		// Reload components and sections on mutation
		let setSaveButtonState = function (e) { 
			if (Vvveb.TreeList) Vvveb.TreeList.loadComponents();
			if (Vvveb.SectionList) Vvveb.SectionList.loadSections();
		};		
		
		Vvveb.Builder.frameBody.addEventListener("vvveb.undo.add", setSaveButtonState);		
		Vvveb.Builder.frameBody.addEventListener("vvveb.undo.restore", setSaveButtonState);		
    },	
    
    _getElementType: function(el) {
		
		//search for component attribute
		let componentName = '';  
		let componentAttribute = '';  
		   
		if (el.attributes) {
			for (let j = 0; j < el.attributes.length; j++){
			  let nodeName = el.attributes[j].nodeName;	
			  
			  if (nodeName.indexOf('data-component') > -1)	 {
				componentName = nodeName.replace('data-component-', '');	
				return [componentName, "component"];
			  }		  
			  
			  if (nodeName.indexOf('data-v-component-') > -1)	 {
				componentName = nodeName.replace('data-v-component-', '');	
				return [componentName,"component"];
			  }

			  if (nodeName.indexOf('data-v-') > -1)	 {
				componentAttribute = (componentAttribute ? componentAttribute + " - " : "") + 
										nodeName.replace('data-v-', '') + " ";	
			  }
			}
		}

		if (componentAttribute != '') return [componentAttribute, "attribute"];
		
		if (el.id) {
			componentName = "#" + el.id;
		} else {
			componentName = (el.className && (typeof el.className == "string")) ? "." + el.className.split(" ")[0] : "";
		}
		
		return [componentName, el.tagName];
	},
	
	loadNodeComponent:  function(node) {
		const data = Vvveb.Components.matchNode(node);
		let component;
		
		if (data) 
			component = data.type;
		else 
			component = Vvveb.defaultComponent;
			
		Vvveb.component = Vvveb.Components.get(component);	
		Vvveb.Components.render(component);
		this.selectedComponent = component;

		//if component properties is loaded in left panel tab instead of right panel show tab
		let propertiesTab = document.querySelector(".component-properties-tab a");
		if (propertiesTab.offsetParent) {//if properites tab is enabled/visible 
				propertiesTab.style.display = "";
				const bsTab = bootstrap.Tab.getOrCreateInstance(propertiesTab);
				bsTab.show(); 
		}		
	},
	
	reloadComponent:  function() {
		Vvveb.Components.render(this.selectedComponent);
	},
	
	moveNodeUp:  function(node) {
		if (!node) {
			node = Vvveb.Builder.selectedEl;
		}

		const oldParent = node.parentNode;
		const oldNextSibling = node.nextSibling;
		const next = node.previousElementSibling;
		
		if (next) {
			next.before(node);
		} else {
			node.parentNode.before(node);
		}
		
		Vvveb.Builder.selectNode(node);

		const newParent = node.parentNode;
		const newNextSibling = node.nextSibling;
		
		Vvveb.Undo.addMutation({type: 'move', 
								target: node,
								oldParent: oldParent,
								newParent: newParent,
								oldNextSibling: oldNextSibling,
								newNextSibling: newNextSibling});

	},

	moveNodeDown:  function(node) {
			if (!node) {
				node = Vvveb.Builder.selectedEl;
			}

			const oldParent = node.parentNode;
			const oldNextSibling = node.nextSibling;
			const next = node.nextElementSibling;
			
			if (next) {
				next.after(node);
			} else {
				node.parentNode.after(node);
			}
			
			Vvveb.Builder.selectNode(node);
			
			const newParent = node.parentNode;
			const newNextSibling = node.nextSibling;
			
			Vvveb.Undo.addMutation({type: 'move', 
									target: node,
									oldParent: oldParent,
									newParent: newParent,
									oldNextSibling: oldNextSibling,
									newNextSibling: newNextSibling});
	},

	cloneNode:  function(node) {
		if (!node) {
			node = Vvveb.Builder.selectedEl;
		}

		const clone = node.cloneNode(true);
		node.after(clone);
		node.click();
		
		Vvveb.Undo.addMutation({type: 'childList', 
								target: node.parentNode, 
								addedNodes: [clone],
								nextSibling: node.nextSibling});
		
	},
	
	
	selectNode:  function(node) {
		let SelectBox = document.getElementById("select-box");
		
		if (!node) {
			if (SelectBox) SelectBox.style.display = "none";
			let SelectActions = document.getElementById("select-actions");
			if (SelectActions) SelectActions.style.display = "none";
			this.selectedEl = null;
			this.selectedEls = new Set();
			return;
		}

		let self = this;
		let SelectActions = document.getElementById("select-actions");
		let AddSectionBtn = document.getElementById("add-section-btn");
		let elementType = this._getElementType(node);
		
		if (self.texteditEl && (self.selectedEl != node)) {
			Vvveb.WysiwygEditor.destroy(self.texteditEl);
			self.selectPadding = 0;
			SelectBox.classList.remove("text-edit");
			SelectActions.style.display = "";
			self.texteditEl = null;
		}

		if (elementType[1] == "BODY") {
			if (SelectActions) SelectActions.style.display = "none";
			if (AddSectionBtn) AddSectionBtn.style.display = "none";
		} else {
			if (SelectActions) SelectActions.style.display = "flex";
			if (AddSectionBtn) AddSectionBtn.style.display = "";
		}

		let target = node;
		self.selectedEl = target;
		self.selectedEls = new Set([target]);

		try {
			let box = target.getBoundingClientRect ? target.getBoundingClientRect() : { top: 0, left: 0, width: 0, height: 0 };
			let top = (box.top - self.selectPadding);
			let left = (box.left - self.selectPadding);
			let width = ((box.width || target.offsetWidth || target.clientWidth) + self.selectPadding * 2);
			let height = ((box.height || target.offsetHeight || target.clientHeight) + self.selectPadding * 2);
				
			SelectBox.style.top  = top + "px"; 
			SelectBox.style.left = left + "px"; 			
			SelectBox.style.width = width + "px"; 			
			SelectBox.style.height = height + "px";
			SelectBox.style.display = "block";
				 
			Vvveb.Breadcrumb.loadBreadcrumb(target);
		
		} catch(err) {
			console.error("[Align Selection] Error calculating select box position:", err);
			return false;
		}
			 
		let highlightType = document.querySelector("#highlight-name .type");
		let highlightName = document.querySelector("#highlight-name .name");
		if (highlightType) highlightType.innerHTML = elementType[0];
		if (highlightName) highlightName.innerHTML = elementType[1];

		// Update topbar select element badge and dynamic action buttons
		let badgeType = document.querySelector("#top-select-element-badge .type");
		let badgeName = document.querySelector("#top-select-element-badge .name");
		if (badgeType) badgeType.textContent = elementType[0] || "";
		if (badgeName) badgeName.textContent = elementType[1] || "";

		// Update directional & parent navigation buttons state
		let upBtn = document.getElementById("up-btn");
		let downBtn = document.getElementById("down-btn");
		let parentBtn = document.getElementById("parent-btn");

		if (upBtn) {
			if (target.previousElementSibling) upBtn.classList.remove("disabled");
			else upBtn.classList.add("disabled");
		}
		if (downBtn) {
			if (target.nextElementSibling) downBtn.classList.remove("disabled");
			else downBtn.classList.add("disabled");
		}
		if (parentBtn) {
			if (target.parentNode && target.parentNode.tagName && target.parentNode.tagName !== "BODY" && target.parentNode.tagName !== "HTML") {
				parentBtn.classList.remove("disabled");
			} else {
				parentBtn.classList.add("disabled");
			}
		}

		// Context-specific actions
		let changeImgBtn = document.getElementById("change-img-btn");
		let translateBtn = document.getElementById("translate-code-btn");
		let toggleOverwriteBtn = document.getElementById("toggle-overwrite-btn");
		let tagName = target.tagName ? target.tagName.toUpperCase() : "";

		if (changeImgBtn) changeImgBtn.style.display = (tagName === "IMG") ? "" : "none";
		if (translateBtn) {
			let isTextNode = (tagName === "P" || tagName === "SPAN" || tagName.startsWith("H") || tagName === "BUTTON" || tagName === "A" || target.children.length === 0);
			translateBtn.style.opacity = isTextNode ? "1" : "0.5";
		}

		if (toggleOverwriteBtn) {
			const isReusable = typeof this.isEditingReusableBlock === "function" 
				? this.isEditingReusableBlock() 
				: ((window.VvvebWpConfig && window.VvvebWpConfig.activePostType === 'wp_block'));

			let isTypography = (
				tagName === "P" || tagName === "SPAN" || tagName.startsWith("H") || 
				tagName === "A" || tagName === "BUTTON" || tagName === "BLOCKQUOTE" || 
				tagName === "B" || tagName === "STRONG" || tagName === "EM" || tagName === "I" ||
				tagName === "LABEL" || tagName === "SMALL" || tagName === "LI" ||
				(target.children.length === 0 && target.textContent && target.textContent.trim().length > 0)
			);
			let isMedia = (
				tagName === "IMG" || tagName === "PICTURE" || tagName === "VIDEO" || 
				tagName === "SOURCE" || tagName === "SVG" || tagName === "FIGURE"
			);

			// Overwrite toggle button is strictly visible only in Reusable Block builder mode
			if (isReusable && (isTypography || isMedia)) {
				toggleOverwriteBtn.style.display = "";
				let isAllow = target.getAttribute("data-allow-overwrite") === "true";
				if (isAllow) {
					toggleOverwriteBtn.classList.add("active");
					toggleOverwriteBtn.setAttribute("title", "Overwrite Allowed on Live Page (Click to disable)");
				} else {
					toggleOverwriteBtn.classList.remove("active");
					toggleOverwriteBtn.setAttribute("title", "Allow Overwrite on Live Page");
				}
			} else {
				toggleOverwriteBtn.style.display = "none";
			}
		}

		// Update toggle hide/unhide button state
		let toggleHideBtn = document.getElementById("toggle-hide-btn");
		if (toggleHideBtn) {
			if (tagName === "BODY" || tagName === "HTML") {
				toggleHideBtn.style.display = "none";
			} else {
				toggleHideBtn.style.display = "";
				let isHidden = target.getAttribute && target.getAttribute("data-align-hide") === "true";
				let icon = toggleHideBtn.querySelector("i");
				if (isHidden) {
					toggleHideBtn.classList.add("is-hidden");
					if (icon) icon.className = "la la-eye-slash";
					toggleHideBtn.setAttribute("title", "Unhide element (restore frontend render)");
				} else {
					toggleHideBtn.classList.remove("is-hidden");
					if (icon) icon.className = "la la-eye";
					toggleHideBtn.setAttribute("title", "Hide element from frontend");
				}
			}
		}
		
		window.dispatchEvent(new CustomEvent("Vvveb.Builder.selectNode", {detail: {target}}));
	},

/* iframe highlight */
});
