/**
 * Align Builder Engine - New Section & Component Insertion
 * @package Align
 */

Vvveb.NewSection = {
	container: null,
	template: `<newsection id="vvvebjs-new-section" data-vvveb-helpers>
			  <div class="vvveb-new-section-container container">
				<div class="vvveb-new-section-row row">
				  <div class="vvveb-new-section-col col">
					<button type="button" id="new-component" class="vvveb-new-section-btn">Add component</button>
				  </div>
				</div>
			  </div>
			</newsection>`,
	
	bindEvents: function() {
		if (!this.container) return;
		this.container.onmouseout = function(event) {
			Vvveb.Builder.highlightEnabled = true;
		};
		
		this.container.onmouseover = function(event) {
			Vvveb.Builder.highlightEnabled = false;
		};
		
		this.container.onclick = function(event) {
			if (event.target.id == 'new-component' || event.target.id == 'new-blank-section') {
				Vvveb.Gui.newComponent(event);
			} else {
				if (event.target.id == 'new-section') {
					Vvveb.Gui.newSection(event, false);
				}
			}
		};
	},

	init: function() {
		this.insert();
	},	
	
	insert: function() {
		if (!Vvveb.Builder.frameDoc || !Vvveb.Builder.frameBody) return null;
		let existing = Vvveb.Builder.frameDoc.getElementById("vvvebjs-new-section");
		if (existing) {
			this.container = existing;
			this.bindEvents();
			if (Vvveb.Builder.contentMode) {
				existing.style.setProperty("display", "none", "important");
			}
			return existing;
		}

		let position = 'before';
		
		let lastSection = Vvveb.Builder.frameBody.querySelector(':scope > footer:last-of-type');
		if (!lastSection) {
			position = 'after';
			lastSection = Vvveb.Builder.frameBody.querySelector(':scope > main:last-of-type') || 
			Vvveb.Builder.frameBody.querySelector(':scope > section:last-of-type');
		}
		
		this.container = generateElements(this.template)[0];
		if (lastSection) {
			if (position == 'after') {
				lastSection.after(this.container);
			} else {
				lastSection.before(this.container);
			}
		} else {
			Vvveb.Builder.frameBody.append(this.container);
		}
		
		this.bindEvents();
		if (Vvveb.Builder.contentMode && this.container) {
			this.container.style.setProperty("display", "none", "important");
		}
		return this.container;
	}
};

Object.assign(Vvveb.Builder, {
	_initAddSectionModal: function() {
		let self = this;
		let addSectionBox = document.getElementById("add-section-box");
		let addSectionElement;

		function showAddSectionModal() {
			if (addSectionBox) {
				addSectionBox.style.display = "flex";
				addSectionBox.style.top = "";
				addSectionBox.style.left = "";
			}
		}

		function hideAddSectionModal() {
			if (addSectionBox) {
				addSectionBox.style.display = "none";
			}
		}
		
		document.getElementById("add-section-btn")?.addEventListener("click", function(event) {
			addSectionElement = self.highlightEl; 
			showAddSectionModal();
			addSectionBox.classList.remove("only-base"); 
			let tagName = (self.highlightEl && self.highlightEl.tagName) ? self.highlightEl.tagName.toLowerCase() : "";
			let bsTab;
			if (["section", "footer", "header"].includes(tagName)) {
				addSectionBox.classList.add("only-sections"); 
				bsTab = bootstrap.Tab.getOrCreateInstance(document.getElementById("box-sections-tab"));
			} else {
				addSectionBox.classList.remove("only-sections"); 
				bsTab = bootstrap.Tab.getOrCreateInstance(document.getElementById("box-components-tab"));
			}
			if (bsTab) bsTab.show(); 

			event.preventDefault();
			return false;
		});
		
		document.getElementById("close-section-btn")?.addEventListener("click", function(event) {
			hideAddSectionModal();
		});

		document.addEventListener("keydown", function(event) {
			if (event.key === "Escape" || event.key === "Esc") {
				hideAddSectionModal();
			}
		});
		
		function addSectionComponent(component, after = true) {
			let html = component.html.replace('RANDOM_ID', Math.floor(Math.random() * 1000));
			let node = generateElements(html)[0];
			let tagName = node.tagName.toLowerCase();
			let element = addSectionElement;
			
			if (["section", "footer", "header", "nav"].includes(tagName)) {
				if (!element) {
					element = Vvveb.Builder.frameBody.querySelector(':scope > ' + tagName + ':last-of-type') || Vvveb.Builder.frameBody.querySelector(':scope > section:last-of-type') || Vvveb.Builder.frameBody.querySelector(':scope > header:last-of-type') || Vvveb.Builder.frameBody.querySelector(':scope > main') || Vvveb.Builder.frameBody.querySelector(':scope > footer:last-of-type');
				} else {
					while (element = element.parentElement) {
						tagName = element.tagName.toLowerCase();
						if (["section", "footer", "header", "nav"].includes(tagName)) {
							after = true;
							break;
						}					
					} 
				} 
				
				if (!element) {
					element = addSectionElement;
				}
			}
			
			if (element) {
				if (after) {
					element.after(node);
				} else {
					element.append(node);
				}
			} else {
				let newsection = Vvveb.Builder.frameBody.querySelector('newsection');
				if (newsection) {
					newsection.before(node);
				} else {
					Vvveb.Builder.frameBody.append(node);
				}
			}
			
			if (component.afterDrop) {
				node = component.afterDrop(node) || node;
			}

			self.selectNode(node);
			self.loadNodeComponent(node);
			Vvveb.TreeList.loadComponents();
			Vvveb.TreeList.selectComponent(node);
			if (Vvveb.scrollToNode) Vvveb.scrollToNode(node);

			Vvveb.Undo.addMutation({
				type: 'childList', 
				target: node.parentNode, 
				addedNodes: [node], 
				nextSibling: node.nextSibling
			});
		}
		
		addSectionBox?.addEventListener("click", function(event) {
			let element = event.target.closest("ol li");
			if (element) {
				let itemType = element.dataset.dragType;
				let html;
				switch (itemType) {
					case 'component':
						html = Vvveb.Components.get(element.dataset.type);
						break;					
					case 'block':
						html = Vvveb.Blocks.get(element.dataset.type);
						break;					
					case 'section':
						html = Vvveb.Sections.get(element.dataset.type);
						break;
				}

				addSectionComponent(html, (document.querySelector("[name='add-section-insert-mode']:checked").value == "after"));

				if (typeof hideAddSectionModal === "function") {
					hideAddSectionModal();
				} else if (addSectionBox) {
					addSectionBox.style.display = "none";
				}
			}
		});
	},

	appendComponent: function(type, dragType = "component") {
		let component;
		if (dragType == "component") {
			component = Vvveb.Components.get(type);
		} else if (dragType == "section") {
			component = Vvveb.Sections.get(type);
		} else if (dragType == "block") {
			component = Vvveb.Blocks.get(type);
		}

		if (!component || !component.html) return;

		let html = component.html.replace(/RANDOM_ID/g, Math.floor(Math.random() * 1000));
		let node = generateElements(html)[0];
		if (!node) return;

		if (!this.frameBody) {
			let frameDoc = window.FrameDocument || (this.iframe && this.iframe.contentWindow ? this.iframe.contentWindow.document : null);
			if (frameDoc) this.frameBody = frameDoc.body;
		}
		if (!this.frameBody) return;

		let targetEl = this.selectedEl;
		if (targetEl && targetEl.isConnected && targetEl !== this.frameBody && !targetEl.closest("#vvvebjs-new-section")) {
			let targetTag = targetEl.tagName.toLowerCase();
			if (["section", "header", "footer", "nav"].includes(targetTag) && dragType === "section") {
				targetEl.after(node);
			} else if (["div", "section", "main", "article", "aside", "form"].includes(targetTag) && 
				(targetEl.classList.contains("container") || targetEl.classList.contains("container-fluid") || targetEl.classList.contains("row") || targetEl.classList.contains("col") || targetEl.classList.contains("col-12"))) {
				targetEl.append(node);
			} else {
				targetEl.after(node);
			}
		} else {
			let newsection = this.frameBody.querySelector("newsection, #vvvebjs-new-section");
			if (newsection) {
				newsection.before(node);
			} else {
				this.frameBody.append(node);
			}
		}

		if (component.afterDrop) {
			node = component.afterDrop(node) || node;
		}

		if (window.AlignBuilderModules?.WpStyleInjector?.replaceDummyImagesInIframe) {
			window.AlignBuilderModules.WpStyleInjector.replaceDummyImagesInIframe(node);
		} else if (window.replaceDummyImagesInIframe) {
			window.replaceDummyImagesInIframe(node);
		}

		if (window.AlignBuilderModules && window.AlignBuilderModules.WpComponentsPreview) {
			window.AlignBuilderModules.WpComponentsPreview.triggerWpComponentsPreviewInIframe(true);
		}

		this.selectNode(node);
		this.loadNodeComponent(node);
		if (Vvveb.TreeList) {
			Vvveb.TreeList.loadComponents();
			Vvveb.TreeList.selectComponent(node);
		}
		if (Vvveb.scrollToNode) Vvveb.scrollToNode(node);

		if (Vvveb.Undo) {
			Vvveb.Undo.addMutation({
				type: "childList",
				target: node.parentNode,
				addedNodes: [node],
				nextSibling: node.nextSibling
			});
		}

		return node;
	}
});
