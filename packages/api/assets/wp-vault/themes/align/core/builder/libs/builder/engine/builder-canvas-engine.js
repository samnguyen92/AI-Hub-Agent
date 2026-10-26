/**
 * Align Builder Engine - Canvas & Iframe Core
 * @package Align
 */

Vvveb.Builder = {

	component : {},
	iframe: null,
	dragMoveMutation: false,
	isPreview: false,
	contentMode: false,
	runJsOnSetHtml: true,
	designerMode: false,
	highlightEnabled: false,
	selectPadding: 0,
	leftPanelWidth: 275,
	ignoreClasses: ["clearfix", "masonry", "has-shadow"],
	
	init: function(url, callback) {

		let self = this;
		
		self.loadControlGroups();
		self.loadBlockGroups();
		self.loadSectionGroups();
		self.loadStylesGroups();
		
		self.selectedEl = null;
		self.highlightEl = null;
		self.initCallback = callback;
		
		self.documentFrame = document.querySelector("#iframe-wrapper > iframe");
		self.canvas = document.getElementById("canvas");
		
		if (!url) {
			url = 'about:blank';
		}

		self._loadIframe(url + (url.indexOf('?') > -1 ? '&r=':'?r=') + Math.random());
		
		self._initDragdrop();
		
		self._initBox();

		self.dragElement = null;
		
		self.highlightEnabled = true;
		
		self.leftPanelWidth = document.getElementById("left-panel").clientWidth;

		// Apply default Desktop viewport (1920x1080) on init
		if (Vvveb.Gui && typeof Vvveb.Gui.setViewport === "function") {
			Vvveb.Gui.setViewport("desktop");
		} else {
			let wrapper = document.getElementById("iframe-wrapper");
			if (wrapper) {
				wrapper.style.width = "1920px";
				wrapper.style.height = "1080px";
				let fitZoom = Vvveb.Gui?.calcFitZoom ? Vvveb.Gui.calcFitZoom(1920, 1080) : 50;
				let zoomInput = document.getElementById("zoom");
				let scaleStr = (fitZoom < 100) ? " scale(" + (fitZoom / 100) + ")" : "";
				wrapper.style.transform = "translate(-50%, -50%)" + scaleStr;
			}
		}

		let canvasEl = document.getElementById("canvas");
		if (canvasEl) {
			canvasEl.scrollTop = 0;
			canvasEl.scrollLeft = 0;
			canvasEl.addEventListener("scroll", function() {
				if (this.scrollTop !== 0) this.scrollTop = 0;
				if (this.scrollLeft !== 0) this.scrollLeft = 0;
			}, { passive: true });
		}
	},
	
	/* controls */    	
	loadControlGroups : function() {	

		let componentsList = document.querySelectorAll(".components-list");
		let count = 0;
		
		componentsList.forEach(function (list) {
			let type = list.dataset.type || "leftpanel";
			list.replaceChildren();
			count++;
			
			for (let group in Vvveb.ComponentsGroup) {
				let cleanGroup = String(group).replace(/[^a-zA-Z0-9_-]/g, '_');
				let headerId = `${type}_comphead_${cleanGroup}_${count}`;
				
				list.append(generateElements(
				`<li class="header" data-section="${group}" data-search="">
					<input class="header_check" type="checkbox" id="${headerId}">
					<label class="header" for="${headerId}">
						${group}<div class="header-arrow"></div>
					</label>
					<ol></ol>
				</li>`)[0]);

				let componentsSubList = list.querySelector(`li[data-section="${group}"] ol`);
				let components = Vvveb.ComponentsGroup[ group ];
				
				for (let j in components) {
					const componentType = components[j];
					let component = Vvveb.Components.get(componentType);
					
					if (component) {
						let iconUrl = '';
						if (component.image) {
							let imgPath = String(component.image).trim();
							if (imgPath.startsWith('http://') || imgPath.startsWith('https://') || imgPath.startsWith('data:')) {
								iconUrl = imgPath;
							} else if (imgPath.startsWith('icons/')) {
								iconUrl = (Vvveb.baseUrl || 'libs/builder/') + imgPath;
							} else {
								iconUrl = (Vvveb.imgBaseUrl || 'libs/builder/icons/') + imgPath;
							}
						}

						let iconInner = iconUrl ? '' : (component.icon ? `<i class="${component.icon}"></i>` : '<i class="la la-cube"></i>');
						let iconStyle = iconUrl ? `style="background-image: url('${iconUrl}');"` : '';

						let item = generateElements(`<li data-section="${group}" data-drag-type="component" data-type="${componentType}" data-search="${(component.name || '').toLowerCase()}">
							<div class="component-icon" ${iconStyle}>${iconInner}</div>
							<span>${component.name}</span>
						</li>`)[0];
						
						if (componentsSubList) {
							componentsSubList.append(item);
						}
					}
				}
			}
		});
	 },
	 
	loadSectionGroups : function() {	

		let sectionsList = document.querySelectorAll(".sections-list");
		let count = 0;

		sectionsList.forEach(function (list) {
			let type = list.dataset.type || "leftpanel";
			list.replaceChildren();
			count++;

			for (let group in Vvveb.SectionsGroup) {
				let cleanGroup = String(group).replace(/[^a-zA-Z0-9_-]/g, '_');
				let headerId = `${type}_sectionhead_${cleanGroup}_${count}`;

				list.append(generateElements(
				`<li class="header" data-section="${group}" data-search="">
					<input class="header_check" type="checkbox" id="${headerId}">
					<label class="header" for="${headerId}">
						${group}<div class="header-arrow"></div>
					</label>
					<ol></ol>
				</li>`)[0]);

				let sectionsSubList = list.querySelector(`li[data-section="${group}"] ol`);
				let sections = Vvveb.SectionsGroup[ group ];

				for (let j in sections) {
					const sectionType = sections[j];
					const section = Vvveb.Sections.get(sectionType);
					
					if (section) {
						let item = generateElements(`<li data-section="${group}" data-drag-type="section" data-type="${sectionType}" data-search="${(section.name || '').toLowerCase()}">
									<span class="name">${section.name}</span>
									<div class="add-section-btn" title="Add section"><i class="la la-plus"></i></div>
								</li>`)[0];

						if (section.image && !section.isCustom && group !== 'Layout') {
							let image = ((section.image.indexOf('/') == -1) ? Vvveb.imgBaseUrl : '') + section.image;
							let imgEl = generateElements(`<img class="preview" src="${image}" loading="lazy">`)[0];
							item.prepend(imgEl);
						} else {
							let iconClass = section.icon || 'la la-code';
							let iconEl = generateElements(`<div class="template-icon-preview"><i class="${iconClass}"></i></div>`)[0];
							item.prepend(iconEl);
						}
						
						if (sectionsSubList) {
							sectionsSubList.append(item);
						}
					}
				}
			}
		});
	 },
	 
	loadBlockGroups : function() {	

		let blocksList = document.querySelectorAll(".blocks-list");
		let count = 0;

		blocksList.forEach(function (list) {
			let type = list.dataset.type || "leftpanel";
			list.replaceChildren();
			count++;

			for (let group in Vvveb.BlocksGroup) {
				let cleanGroup = String(group).replace(/[^a-zA-Z0-9_-]/g, '_');
				let headerId = `${type}_blockhead_${cleanGroup}_${count}`;

				list.append(generateElements(
				`<li class="header" data-section="${group}" data-search="">
					<input class="header_check" type="checkbox" id="${headerId}">
					<label class="header" for="${headerId}">
						${group}<div class="header-arrow"></div>
					</label>
					<ol></ol>
				</li>`)[0]);

				let blocksSubList = list.querySelector(`li[data-section="${group}"] ol`);
				let blocks = Vvveb.BlocksGroup[ group ];

				for (let j in blocks) {
					const blockType = blocks[j];
					const block = Vvveb.Blocks.get(blockType);
					
					if (block) {
						let item = generateElements(`<li data-section="${group}" data-drag-type="block" data-type="${blockType}" data-search="${(block.name || '').toLowerCase()}">
									<span class="name">${block.name}</span>
									<img class="preview" src="" loading="lazy">
								</li>`)[0];

						if (block.image) {
							let image = ((block.image.indexOf('/') == -1) ? Vvveb.imgBaseUrl : '') + block.image;
							let imgEl = item.querySelector("img");
							if (imgEl) {
								imgEl.setAttribute("src", image);
							}
						}
						
						if (blocksSubList) {
							blocksSubList.append(item);
						}
					}
				}
			}
		});
	 },	
	 
	 loadStylesGroups : function() {	

		let stylesList = document.querySelectorAll(".styles-list");
		let count = 0;

		stylesList.forEach(function (list) {
			let type = list.dataset.type || "leftpanel";
			list.replaceChildren();
			count++;

			for (let group in Vvveb.StylesGroup) {
				let cleanGroup = String(group).replace(/[^a-zA-Z0-9_-]/g, '_');
				let headerId = `${type}_stylehead_${cleanGroup}_${count}`;

				list.append(generateElements(
				`<li class="header" data-section="${group}" data-search="">
					<input class="header_check" type="checkbox" id="${headerId}">
					<label class="header" for="${headerId}">
						${group}<div class="header-arrow"></div>
					</label>
					<ol></ol>
				</li>`)[0]);

				let stylesSubList = list.querySelector(`li[data-section="${group}"] ol`);
				let styles = Vvveb.StylesGroup[ group ];

				for (let j in styles) {
					const styleType = styles[j];
					const style = Vvveb.Styles.get(styleType);
					
					if (style) {
						let item = generateElements(`<li data-section="${group}" data-type="${styleType}" data-search="${(style.name || '').toLowerCase()}">
									<span class="name">${style.name}</span>
									<img class="preview" src="" loading="lazy">
								</li>`)[0];

						if (style.image) {
							let image = ((style.image.indexOf('/') == -1) ? Vvveb.imgBaseUrl : '') + style.image;
							let imgEl = item.querySelector("img");
							if (imgEl) {
								imgEl.setAttribute("src", image);
							}
						}
						
						if (stylesSubList) {
							stylesSubList.append(item);
						}
					}
				}
			}
		});
	 },
	
	loadUrl : function(url, callback) {	
		let self = this;
		document.getElementById("select-box").style.display = "none";
		
		self.initCallback = callback;
		let targetUrl = url;
		if (targetUrl && targetUrl !== 'about:blank') {
			targetUrl += (targetUrl.indexOf('?') > -1 ? '&_t=' : '?_t=') + Date.now();
		}
		if (Vvveb.Builder.iframe.src != targetUrl) Vvveb.Builder.iframe.src = targetUrl;
	},
	
/* iframe */
	_loadIframe : function(url) {	

		let self = this;
		self.iframe = this.documentFrame;
		self.iframe.src = url;

	    return this.documentFrame.addEventListener("load", function() {
				window.FrameWindow = self.iframe.contentWindow;
				window.FrameDocument = self.iframe.contentWindow.document;
				let addSectionBox = document.getElementById("add-section-box"); 
				let highlightBox = document.getElementById("highlight-box");
				let SelectBox = document.getElementById("select-box");
				
				highlightBox.style.display = "none"; 
				

				window.FrameWindow.addEventListener("beforeunload", function(event) {
					if (Vvveb.Undo.undoIndex >= 0) {
						let dialogText = "You have unsaved changes";
						event.returnValue = dialogText;
						return dialogText;
					}
				});
				
				window.FrameWindow.addEventListener("pagehide", function(event) {
					document.querySelector(".loading-message")?.classList.add("active");
					Vvveb.Undo.reset();
				});
				
				//prevent accidental clicks on links when editing text
				window.FrameDocument.addEventListener("click", function(event) {
					if (Vvveb.WysiwygEditor.isActive && event.target.closest("a"))  {
						event.preventDefault();
						return false;
					}
				});
				
				selectBoxPosition = function(event) {
						let target = self.selectedEl;
						
						highlightBox.style.display = "none"; 

						if (target && target.getBoundingClientRect) {
							let box = target.getBoundingClientRect();
						
							SelectBox.style.top  = (box.top - self.selectPadding) + "px"; 
							SelectBox.style.left = (box.left - self.selectPadding) + "px";

							SelectBox.style.width = ((box.width || target.offsetWidth || target.clientWidth) + self.selectPadding * 2) + "px"; 			
							SelectBox.style.height = ((box.height || target.offsetHeight || target.clientHeight) + self.selectPadding * 2) + "px";
						}
				};
				
				window.FrameWindow.addEventListener("scroll", selectBoxPosition, { passive: true });
				window.FrameWindow.addEventListener("resize", selectBoxPosition, { passive: true });
				window.addEventListener("resize", selectBoxPosition, { passive: true });
			
				Vvveb.WysiwygEditor.init(window.FrameDocument);
				Vvveb.StyleManager.init(window.FrameDocument);
				if (typeof Vvveb.JsManager !== "undefined") Vvveb.JsManager.init(window.FrameDocument);
				Vvveb.ColorPaletteManager.init(window.FrameDocument);

				if (self.initCallback) self.initCallback();

                return self._frameLoaded();
        });		
        
	},

	/**
	 * Check if the builder is currently editing a Reusable Block (wp_block).
	 *
	 * @return {boolean} True if current editing context is a Reusable Block.
	 */
	isEditingReusableBlock: function() {
		const wpConfig = window.VvvebWpConfig || {};
		if (wpConfig.activePostType === 'wp_block') return true;
		if (window.Vvveb && window.Vvveb.FileManager && window.Vvveb.FileManager.currentPage) {
			const pageData = window.Vvveb.FileManager.pages[window.Vvveb.FileManager.currentPage];
			if (pageData) {
				return (pageData.folder === 'Reusable Blocks' || pageData.type === 'Reusable Blocks' || pageData.templateType === 'reusable_block' || (pageData.name && String(pageData.name).startsWith('block_')));
			}
		}
		return false;
	}
};
