/**
 * Align Builder Engine - Drag & Drop Sortable Engine
 * @package Align
 */

Object.assign(Vvveb.Builder, {
_initDragdrop : function() {

		let self = this;
		self.isDragging = false;	
		self.hasDragged = false;
		self.dragOriginElement = null;
		
		document.addEventListener("mousedown", function(event) {
			if (self.contentMode) return;
			let element = event.target.closest(".drag-elements-sidepane ul > li > ol > li[data-drag-type]");
			let html;
			
			if (element && event.which == 1) {//left click
				document.getElementById("component-clone")?.remove();
				document.querySelectorAll("#section-actions, #highlight-name, #select-box").forEach(e => e.style.display = "none");
				
				self.dragOriginElement = element;
				self.dragStartX = event.clientX;
				self.dragStartY = event.clientY;
				self.hasDragged = false;

				self.dragType  = element.dataset.dragType;
				if (self.dragType == "component") {
					self.component = Vvveb.Components.get(element.dataset.type);
				}
				else if (self.dragType == "section") {
					self.component = Vvveb.Sections.get(element.dataset.type);
				}
				else if (self.dragType == "block") {
					self.component = Vvveb.Blocks.get(element.dataset.type);
				}
				
				if (!self.component) return;

				if (self.component.dragHtml) {
					html = self.component.dragHtml;
				} else if (Vvveb.dragHtml) { 
					html = Vvveb.dragHtml;
				} else {
					html = (self.component.html || '').replace('RANDOM_ID', Math.floor(Math.random() * 1000));
				}
				
				self.dragElement = generateElements(html)[0];
				//self.dragElement.css("border", "1px dashed #4285f4");
				
				if (self.component.dragStart) self.dragElement = self.component.dragStart(self.dragElement);

				self.isDragging = true;
				if (self.frameBody) self.frameBody.classList.add('is-dragging-active');
				if (Vvveb.dragIcon == 'html') {
					self.iconDrag = generateElements(html)[0];
					self.iconDrag.setAttribute("id", "dragElement-clone");
					self.iconDrag.style.position = "absolute";
				}
				else if (self.designerMode == false) {
					self.iconDrag = document.createElement("img");
					self.iconDrag.setAttribute("id", "dragElement-clone");
					self.iconDrag.setAttribute("src", element.style.backgroundImage.replace(/^url\(['"](.+)['"]\)/, '$1'));
					
					self.iconDrag.style.zIndex = "100";
					self.iconDrag.style.position = "absolute";
					self.iconDrag.style.width = "64px";
					self.iconDrag.style.height = "64px";
					self.iconDrag.style.top = event.y + "px";
					self.iconDrag.style.left = event.x + "px";
				}
					
				if (self.iconDrag) {
					self.iconDrag.style.pointerEvents = "none";
					self.iconDrag.style.transform = "translate(-50%, -50%)";
				}
				self.dragMoveMutation = false;
				document.body.append(self.iconDrag);
				
				event.preventDefault();
				return false;
			}
		});
		
		document.addEventListener('mouseup', function(event) {
			if (self.isDragging == true) {
				let x = event.clientX;
				let y = event.clientY;
				let droppedInside = false;

				if (self.iframe && self.dragElement && self.dragElement.parentNode) {
					let rect = self.iframe.getBoundingClientRect();
					let getScaleFactor = function() {
						let iframeWrapper = document.getElementById("iframe-wrapper");
						if (!iframeWrapper) return 1;
						let transform = window.getComputedStyle(iframeWrapper).transform;
						if (transform && transform !== "none") {
							let matrix = transform.match(/^matrix\((.+)\)$/);
							if (matrix) {
								return parseFloat(matrix[1].split(',')[0]) || 1;
							}
						}
						return 1;
					};
					let scaleFactor = getScaleFactor();
					let iframeX = (x - rect.left) / scaleFactor;
					let iframeY = (y - rect.top) / scaleFactor;

					if (iframeX >= 0 && iframeX <= (rect.width / scaleFactor) && iframeY >= 0 && iframeY <= (rect.height / scaleFactor)) {
						droppedInside = true;
						if (self.highlightUp) {
							self.highlightUp(event);
						}
					}
				}

				if (!droppedInside) {
					if (!self.hasDragged && self.dragOriginElement) {
						let compType = self.dragOriginElement.dataset.type;
						let compDragType = self.dragOriginElement.dataset.dragType || "component";
						self.appendComponent(compType, compDragType);
					}

					self.isDragging = false;
					if (self.frameBody) self.frameBody.classList.remove('is-dragging-active');
					document.getElementById("component-clone")?.remove();
					document.querySelectorAll("#section-actions, #highlight-name, #select-box").forEach(el => el.style.display = "");
					if (self.iconDrag) self.iconDrag.remove();
					if (self.dragElement) self.dragElement.remove();
				}

				self.dragOriginElement = null;
				self.hasDragged = false;
			}
		});
		
		document.addEventListener('mousemove', function(event) {
			if (self.isDragging == true) {
				let dist = Math.hypot(event.clientX - (self.dragStartX || 0), event.clientY - (self.dragStartY || 0));
				if (dist > 5) {
					self.hasDragged = true;
				}

				if (self.iconDrag) {
					let x = event.clientX;
					let y = event.clientY;

					self.iconDrag.style.left = x + "px";
					self.iconDrag.style.top = y + "px";

					if (self.iframe) {
						let rect = self.iframe.getBoundingClientRect();
						let getScaleFactor = function() {
							let iframeWrapper = document.getElementById("iframe-wrapper");
							if (!iframeWrapper) return 1;
							let transform = window.getComputedStyle(iframeWrapper).transform;
							if (transform && transform !== "none") {
								let matrix = transform.match(/^matrix\((.+)\)$/);
								if (matrix) {
									return parseFloat(matrix[1].split(',')[0]) || 1;
								}
							}
							return 1;
						};
						let scaleFactor = getScaleFactor();
						let iframeX = (x - rect.left) / scaleFactor;
						let iframeY = (y - rect.top) / scaleFactor;

						// Check if cursor is within iframe canvas bounds
						if (iframeX >= 0 && iframeX <= (rect.width / scaleFactor) && iframeY >= 0 && iframeY <= (rect.height / scaleFactor)) {
							if (self.frameDoc) {
								let target = self.frameDoc.elementFromPoint(iframeX, iframeY);
								if (target && self.highlightMove) {
									self.highlightMove({
										target: target,
										x: iframeX,
										y: iframeY,
										clientX: iframeX,
										clientY: iframeY
									});
								}
							}
						}
					}
				}
			}
		});
			
	},
	
	removeHelpers: function (html, keepHelperAttributes = false) {
		if (!html || typeof html !== "string") return html;

		// 1. Remove entire <newsection ...>...</newsection> blocks
		html = html.replace(/<newsection\b[^>]*>[\s\S]*?<\/newsection>/gi, "");
		// 2. Remove any elements with id="vvvebjs-new-section" or data-vvveb-helpers
		html = html.replace(/<[a-z0-9_-]+\b[^>]*\bid=["']vvvebjs-new-section["'][^>]*>[\s\S]*?<\/[a-z0-9_-]+>/gi, "");
		html = html.replace(/<[a-z0-9_-]+\b[^>]*\bdata-vvveb-helpers\b[^>]*>[\s\S]*?<\/[a-z0-9_-]+>/gi, "");
		// 3. Remove any orphaned helper child containers / buttons
		html = html.replace(/<div\b[^>]*class=["'][^"']*\bvvveb-new-section-(?:container|row|col)\b[^"']*["'][^>]*>[\s\S]*?<\/div>/gi, "");
		html = html.replace(/<button\b[^>]*class=["'][^"']*\bvvveb-new-section-btn\b[^"']*["'][^>]*>[\s\S]*?<\/button>/gi, "");
		html = html.replace(/<button\b[^>]*id=["'](?:new-component|new-section|new-blank-section)["'][^>]*>[\s\S]*?<\/button>/gi, "");
		// 4. Remove opening/self-closing helper tags like stylesheets or scripts
		html = html.replace(/<[^>]+?\bdata-vvveb-helpers\b[^>]*>/gi, "");

		//attributes
		if (!keepHelperAttributes) {
			html = html.replace(/\s*data-vvveb-\w+(=["'].*?["'])?\s*/gi, "");
		}
		
		html = html.replaceAll("vvveb-hidden", "").replaceAll("data-vvvebjs-editor", "");
		return html;
	},

	getHtml: function(keepHelperAttributes = true, filter = true) {
		let doc = window.FrameDocument;
		if (!doc || !doc.body) return "";
		
		doc.querySelectorAll("[contenteditable]").forEach(e => e.removeAttribute("contenteditable"));
		doc.querySelectorAll("[spellcheckker]").forEach(e => e.removeAttribute("spellcheckker"));
		doc.querySelectorAll('script[src^="chrome-extension://"]').forEach(e => e.remove());
		doc.querySelectorAll('script[src^="moz-extension://"]').forEach(e => e.remove());
		
		if (filter) {
			window.dispatchEvent(new CustomEvent("Vvveb.getHtml.before", {detail: doc}));
		}

		let html = doc.body.innerHTML;
		html = this.removeHelpers(html, keepHelperAttributes);
		
		// Clean out only helper/preview style and script tags inserted by builder or WordPress preview
		html = html.replace(/<style[^>]*\bid\s*=\s*(["']?)(?:(?:align-)?style-[^"'\s>]+|vvvebjs-styles|align-custom-css|wp-global-styles)\1[^>]*>[\s\S]*?<\/style>/gi, "");
		html = html.replace(/<script[^>]*\bid\s*=\s*(["']?)(?:(?:align-)?script-[^"'\s>]+|vvvebjs-scripts|align-custom-js)\1[^>]*>[\s\S]*?<\/script>/gi, "");
		html = html.replace(/<(script|style)[^>]*data-(align-preview|vvveb-helpers)[^>]*>[\s\S]*?<\/\1>/gi, "");
		// Ensure local wp-content image URLs are strictly relative paths without domain/origin
		if (typeof window !== "undefined" && window.location && window.location.origin) {
			const originRegex = new RegExp(window.location.origin.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '/wp-content/', 'gi');
			html = html.replace(originRegex, '/wp-content/');
		}
		
		if (filter) {
			 window.dispatchEvent(new CustomEvent("Vvveb.getHtml.after", {detail: doc}));
			 window.dispatchEvent(new CustomEvent("Vvveb.getHtml.filter", {detail: html}));
		}
         
        return html.trim();
	},
	
	loadHtml: function(html) {
		this.iframe.srcdoc = html;
		/*
		this.iframe.src = "about:blank";
		window.FrameDocument.open();
		window.FrameDocument.write(html);
		window.FrameDocument.close();
		*/
	},
	
	setHtml: function(html) {
		if (typeof html !== "string") return;

		// 1. Strip any editor helper tags from incoming HTML
		html = this.removeHelpers(html, true);

		// 2. Extract Page CSS (<style id="style-..." or "vvvebjs-styles" or untagged) and inject safely into head, never body
		let cssContent = '';
		const cssRegex = /<style[^>]*\bid\s*=\s*(["']?)(?:(?:align-)?style-[^"'\s>]+|vvvebjs-styles)\1[^>]*>([\s\S]*?)<\/style>/i;
		let cssMatch = html.match(cssRegex);
		if (cssMatch) {
			cssContent = (cssMatch[2] || '').trim();
			html = html.replace(cssRegex, "");
		} else {
			const untaggedCss = /<style\b(?![^>]*\b(?:id="wp-global-styles"|data-align-preview))[^>]*>([\s\S]*?)<\/style>/i;
			cssMatch = html.match(untaggedCss);
			if (cssMatch) {
				cssContent = (cssMatch[1] || '').trim();
				html = html.replace(untaggedCss, "");
			}
		}
		if (cssContent.length > 0 && Vvveb.StyleManager && typeof Vvveb.StyleManager.setCss === "function") {
			console.log('%c[Align DragDrop] Extracted CSS in setHtml len:', 'color: #38bdf8;', cssContent.length);
			Vvveb.StyleManager.setCss(cssContent);
		}

		// 3. Extract Page JS (<script id="script-..." or "vvvebjs-scripts" or untagged) and inject safely, never body
		let jsContent = '';
		const jsRegex = /<script[^>]*\bid\s*=\s*(["']?)(?:(?:align-)?script-[^"'\s>]+|vvvebjs-scripts)\1[^>]*>([\s\S]*?)<\/script>/i;
		let jsMatch = html.match(jsRegex);
		if (jsMatch) {
			jsContent = (jsMatch[2] || '').trim();
			html = html.replace(jsRegex, "");
		} else {
			const untaggedJs = /<script\b(?![^>]*\bdata-align-preview)[^>]*>([\s\S]*?)<\/script>/i;
			jsMatch = html.match(untaggedJs);
			if (jsMatch) {
				jsContent = (jsMatch[1] || '').trim();
				html = html.replace(untaggedJs, "");
			}
		}
		if (jsContent.length > 0 && Vvveb.JsManager && typeof Vvveb.JsManager.setJs === "function") {
			console.log('%c[Align DragDrop] Extracted JS in setHtml len:', 'color: #a855f7;', jsContent.length);
			Vvveb.JsManager.setJs(jsContent);
		}

		function getTag(html, tag, outerHtml = false) {
			const regex = new RegExp("<" + tag + "\\b[^>]*>([\\s\\S]*?)<\\/" + tag + ">", "i");
			const match = html.match(regex);
			if (match) {
				return outerHtml ? match[0] : match[1];
			}
			return html;
		}

		let bodyMatch = html.match(/<body\b[^>]*>([\s\S]*?)<\/body>/i);
		let bodyContent = bodyMatch ? bodyMatch[1] : html;
		let targetBody = this.frameBody || (window.FrameDocument ? window.FrameDocument.body : null);
		if (targetBody) {
			if (Vvveb.DomMorph && typeof Vvveb.DomMorph.morph === "function") {
				Vvveb.DomMorph.morph(targetBody, bodyContent, { childrenOnly: true, ignoreHelpers: true });
			} else {
				targetBody.innerHTML = bodyContent;
			}
			this.refreshDomSnapshot(targetBody);
		}

		// Keep Virtual AST in sync with current HTML
		if (Vvveb.AstParser && typeof Vvveb.AstParser.parse === "function") {
			Vvveb.AstParser.parse(bodyContent);
		}

		// Always re-insert the official NewSection helper box at the canvas bottom
		if (Vvveb.NewSection && typeof Vvveb.NewSection.insert === "function") {
			Vvveb.NewSection.insert();
		}
			
		// Set head html only if explicit <head> tag exists and changed to avoid page flicker
		let headMatch = html.match(/<head\b[^>]*>([\s\S]*?)<\/head>/i);
		let headHtml = headMatch ? headMatch[1] : '';
		if (headHtml && window.FrameDocument && window.FrameDocument.head && window.FrameDocument.head.innerHTML != headHtml) {
			window.FrameDocument.head.innerHTML = headHtml;
		}

		// Realtime sync Tree Navigator & Section list
		if (Vvveb.TreeList) {
			Vvveb.TreeList.loadComponents();
		}
		if (Vvveb.SectionList) {
			Vvveb.SectionList.loadSections();
		}
		if (window.AlignBuilderModules && window.AlignBuilderModules.WpComponentsPreview) {
			window.AlignBuilderModules.WpComponentsPreview.triggerWpComponentsPreviewInIframe(true);
		}
	},

	refreshDomSnapshot: function(target) {
		const targetBody = target || this.frameBody || (window.FrameDocument ? window.FrameDocument.body : null);
		if (!targetBody) return;
		const win = targetBody.ownerDocument?.defaultView || window.FrameDocument?.defaultView || (Vvveb.Builder.iframe?.contentWindow);
		if (!win) return;
		if (typeof win.__alignRefreshDomSnapshot === "function") {
			win.__alignRefreshDomSnapshot(targetBody);
		} else {
			try {
				win.__ALIGN_DOM_SNAPSHOT__ = new WeakMap();
				const snap = (n) => {
					if (!n || n.nodeType !== 1) return;
					if (n.id === "vvvebjs-new-section" || n.id === "highlight-box" || n.id === "select-box" || n.hasAttribute("data-vvveb-helpers") || (n.classList && n.classList.contains("vvveb-helper"))) return;
					const s = {};
					if (n.style && n.style.length) {
						for (let i = 0; i < n.style.length; i++) {
							const p = n.style[i];
							s[p] = n.style.getPropertyValue(p);
						}
					}
					win.__ALIGN_DOM_SNAPSHOT__.set(n, { c: n.className || "", s: s });
					for (let c = n.firstElementChild; c; c = c.nextElementSibling) snap(c);
				};
				snap(targetBody);
			} catch (e) {
				console.warn('[Align DragDrop] Failed to refresh DOM snapshot fallback:', e);
			}
		}
		console.log('[Align DragDrop] Refreshed pre-animation DOM baseline snapshot');
	},

	saveElement: function(element, type, name, callback) {
		if (type == 'section') {
			Vvveb.Sections.add('reusable/'+ name, {
				name,
				image: "img/logo-small.png",
				html: element.outerHTML});
			
			if (Vvveb.SectionsGroup["Reusable"] === undefined) {
				Vvveb.SectionsGroup["Reusable"] = [];
			}
			
			Vvveb.SectionsGroup["Reusable"].push('reusable/'+ name);
			Vvveb.Builder.loadSectionGroups();
		} else {
			Vvveb.Blocks.add('reusable/'+ name, {
				name,
				image: "img/logo-small.png",
				html: element.outerHTML});
			
			if (Vvveb.BlocksGroup["Reusable"] === undefined) {
				Vvveb.BlocksGroup["Reusable"] = [];
			}
			
			Vvveb.BlocksGroup["Reusable"].push('reusable/'+ name);
			Vvveb.Builder.loadBlockGroups();
		}
		
		let data = {type, name, html:element.outerHTML};
		
		if (typeof saveUrl != "undefined") {
			fetch(saveUrl, {method: "POST", body: new URLSearchParams(data), credentials: 'include'})
			.then((response) => {
				if (!response.ok) { throw new Error(response.statusText) }
				return response.text();
			})
			.then((data) => {
				if (callback) callback(data);
				displayToast("success", "Save", "Element saved successfully");					
			})
			.catch(error => {
				console.error("[Align Builder] Error saving reusable element:", error?.message || error);
				displayToast("danger", "Error", "Error saving reusable element!");
			});
		}
	},
	
	save: function(data, saveUrl, success, error, done) {
		if (!data["file"]) {
			data["file"] = Vvveb.FileManager.getCurrentFileName();
		}

		if (!data["startTemplateUrl"]) {
			data["html"] = this.getHtml();
		}

		let detail = {...Vvveb.FileManager.getPageData(), ...data};
		window.dispatchEvent(new CustomEvent("Vvveb.Builder.save", {
			detail
		}));

		if (saveUrl && data.name) {
			return fetch(saveUrl, {
				method: "POST",  
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
				body: nestedFormData(data)
			})
			.then((response) => {
				if (!response.ok) {
					return Promise.resolve(response.text()).then((responseInText) => {
						return Promise.reject([response, responseInText]);
					});
				}
				return response.text();
			})
			.then((data) => {
				if (success) success(data);
				Vvveb.Undo.reset();
			})
			.catch((err) => {
				if (error) error(err);
				
				let [response, responseInText] = Array.isArray(err) ? err : [err, err?.message];
				let message = responseInText || response?.statusText || "Error saving!";

				if (response && typeof response.text === "function") {
					response.text().then(errorMessage => {
						displayToast("danger", "Error", (errorMessage || message).substr(0, 200));
					});
				} else {
					displayToast("danger", "Error", String(message).substr(0, 200));
				}
			});
		} else {
			if (done) done();
			Vvveb.Undo.reset();
		}
	}
});
