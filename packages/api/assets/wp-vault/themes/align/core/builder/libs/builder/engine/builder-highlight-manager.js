/**
 * Align Builder Engine - Highlight Box Manager
 * @package Align
 */

Object.assign(Vvveb.Builder, {
_initHighlight: function() {
		
		let self = Vvveb.Builder;
		
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

		let resolveCustomComponent = function(el, evt) {
			if (evt && typeof evt.composedPath === 'function') {
				const path = evt.composedPath();
				for (const item of path) {
					if (item && item.hasAttribute && (
						item.hasAttribute('data-component-shortcode') ||
						item.hasAttribute('data-component-wp-block') ||
						item.hasAttribute('data-component-post-content')
					)) {
						return item;
					}
					if (item && item.closest) {
						const found = item.closest('[data-component-shortcode], [data-component-wp-block], [data-component-post-content]');
						if (found) return found;
					}
				}
			}
			return el?.closest ? el.closest('[data-component-shortcode], [data-component-wp-block], [data-component-post-content]') : null;
		};

		let _rafTicking = false;
		let _lastMoveEvent = null;

		let highlightMove = function(event) {
			if ((Vvveb.WysiwygEditor && Vvveb.WysiwygEditor.isActive) || (Vvveb.WordEditor && Vvveb.WordEditor.isActive) || (event.target?.closest && event.target.closest(".align-document-container"))) {
				if (self.highlightBox) self.highlightBox.hide();
				return;
			}
			if (self.highlightEnabled == true && event.target/* && isElement(event.target)*/) {
				_lastMoveEvent = event;
				if (!_rafTicking) {
					window.requestAnimationFrame(function() {
						if ((Vvveb.WysiwygEditor && Vvveb.WysiwygEditor.isActive) || (Vvveb.WordEditor && Vvveb.WordEditor.isActive)) {
							_rafTicking = false;
							if (self.highlightBox) self.highlightBox.hide();
							return;
						}
						let e = _lastMoveEvent;
						let target = e.target;
						let customComp = resolveCustomComponent(target, e);
						if (customComp) {
							target = customComp;
						}
						self.highlightEl = target;
						let scaleFactor = getScaleFactor();
						let box = target.getBoundingClientRect ? target.getBoundingClientRect() : { top: 0, left: 0, width: target.offsetWidth || 0, height: target.offsetHeight || 0 };
						let height = box.height || target.offsetHeight || 0;
						let halfHeight = Math.max(height / 2, 5);
						let width = box.width || target.offsetWidth || 0;
						let halfWidth = Math.max(width / 2, 5);
						let prepend = true;
						
						let x = e.x / scaleFactor;
						let y = e.y / scaleFactor;

						if (self.isResize) {
							if (!self.initialPosition) {
								self.initialPosition = {x,y};
							}
							
							let deltaX = x - self.initialPosition.x; 
							let deltaY = y - self.initialPosition.y; 
							
							let selBox = self.selectedEl.getBoundingClientRect ? self.selectedEl.getBoundingClientRect() : { top: 0, left: 0 };
							
							width = self.initialSize.width;
							height = self.initialSize.height;
							
							switch (self.resizeHandler) {
								case "top-left":
									height -= deltaY; 
									width -= deltaX; 
								break;
								case "top-center":
									height -= deltaY; 
								break;
								case "top-right":
									height -= deltaY; 
									width += deltaX; 
								break;
								case "center-left":
									width -= deltaX; 
								break;
								case "center-right":
									width += deltaX; 
								break;
								case "bottom-left":
									width -= deltaX; 
									height += deltaY; 
								break;
								case "bottom-center":
									height += deltaY; 
								break;
								case "bottom-right":
									width += deltaX; 
									height += deltaY; 
								break;
							}
						
							if (self.resizeMode == "css") {
								self.selectedEl.style.width = width + "px";
								self.selectedEl.style.height = height + "px";
							} else {
								self.selectedEl.setAttribute("width", width);
								self.selectedEl.setAttribute("height", height);
							}

							let SelectBox = document.getElementById("select-box");
							SelectBox.style.top  = (selBox.top - self.selectPadding) + "px"; 
							SelectBox.style.left = (selBox.left - self.selectPadding) + "px"; 			
							SelectBox.style.width = width + "px"; 			
							SelectBox.style.height = self.selectedEl.offsetHeight + "px";
							SelectBox.style.display = "block";
						
						} else if (self.isDragging) {
							let noChildren = {
								input: true, textarea: true, img: true, svg: true, iframe: true,
								embed: true, col: true, area: true, hr: true, br: true, wbr: true
							};

							let parent = self.highlightEl;
							let isBlock = (window.getComputedStyle(parent).display == "block");

							if (self.dragType == "section") {
								let closest = parent.closest("section, header, footer, body");
								if (closest) { parent = closest; }
								noChildren.section = true;
							}

							let parentTagName = parent.tagName.toLowerCase();
							let isVattribute = false;
							if (parent.childElementCount == 0) {
								for (let attr of parent.attributes) {
									if (attr.name.startsWith("data-v-") && !attr.name.startsWith("data-v-component-")) {
										isVattribute = true;
										break;
									}
								}
							}
							
							try {
								if ((box.top < (y - halfHeight)) || (box.left < (x - halfWidth))) {
									if (noChildren[parentTagName] || isBlock || isVattribute) { 
										parent.after(self.dragElement);
									} else {
										if (parent == self.dragElement.parentNode) {
											parent.appendChild(self.dragElement);
										} else {
											parent.append(self.dragElement);
										}
									}
									prepend = true;
								} else {
									if (noChildren[parentTagName] || isBlock || isVattribute) { 
										parent.parentNode.insertBefore(self.dragElement, parent);
									} else {
										parent.prepend(self.dragElement);
									}
									prepend = false;
								};
								
								if (self.designerMode) {
									let win = self.frameDoc?.defaultView || window.FrameWindow || window;
									let frameScrollLeft = win.pageXOffset ?? win.scrollX ?? self.frameDoc?.documentElement?.scrollLeft ?? 0;
									let frameScrollTop = win.pageYOffset ?? win.scrollY ?? self.frameDoc?.documentElement?.scrollTop ?? 0;
									let parentOffset = offset(self.dragElement.offsetParent);
									self.dragElement.style.position = "absolute"; 
									self.dragElement.style.left = (x - (parentOffset.left - frameScrollLeft)) + "px"; 
									self.dragElement.style.top = (y - (parentOffset.top - frameScrollTop)) + "px"; 			
								}
							} catch(err) {
								console.error("[Align Highlight] Drag move error:", err);
								_rafTicking = false;
								return false;
							}
							
							if (!self.designerMode && self.iconDrag) {
								self.iconDrag.style.top  = (y + 60) + "px"; 
								self.iconDrag.style.left = (x + self.leftPanelWidth + 10) + "px"; 			
							}
						} else {
							if (Vvveb.WysiwygEditor.isActive) {
								if (self.texteditEl && self.texteditEl.contains(target)) {
									_rafTicking = false;
									return true;
								}
							}
								 
							document.getElementById("highlight-box").setAttribute("style",
								`top:${box.top}px; 
								 left:${box.left}px;
								 width:${width}px; 
								 height:${height}px;
								 display:${target.hasAttribute('contenteditable') ? "none":"block"};
								 border:${self.isDragging ? "1px dashed #0d6efd":""};
							`);

							if (height < 50) {
								document.getElementById("section-actions").classList.add("outside");	 
							} else {
								document.getElementById("section-actions").classList.remove("outside");	
							}

							let elementType = self._getElementType(target);
							document.querySelector("#highlight-name .type").innerHTML = elementType[0];
							document.querySelector("#highlight-name .name").innerHTML = elementType[1];
						}
						
						_rafTicking = false;
					});
					_rafTicking = true;
				}
			}
		};
		
		self.frameBody.addEventListener("mousemove", highlightMove, { passive: true });
		self.highlightMove = highlightMove;
		
		let highlightUp = function(event) {
			self.isResize = false;
			document.querySelectorAll("#section-actions, #highlight-name").forEach(el => el.style.display = "");
			if (self.isDragging) {
				self.isDragging = false;
				if (self.frameBody) self.frameBody.classList.remove('is-dragging-active');
				Vvveb.Builder.highlightEnabled = true;
				if (self.iconDrag) self.iconDrag.remove();
				document.getElementById("component-clone")?.remove();

				if (self.dragMoveMutation === false) {				
					if (self.component.dragHtml || Vvveb.dragHtml) { //if dragHtml is set for dragging then set real component html
						if (self.component) {
							let html = self.component.html.replace('RANDOM_ID', Math.floor(Math.random() * 1000));
							let newElement = generateElements(html)[0];
							self.dragElement.replaceWith(newElement);
							self.dragElement = newElement;
						}
					} 
					
					if (self.component.afterDrop) self.dragElement = self.component.afterDrop(self.dragElement);

					if (window.AlignBuilderModules?.WpStyleInjector?.replaceDummyImagesInIframe) {
						window.AlignBuilderModules.WpStyleInjector.replaceDummyImagesInIframe(self.dragElement);
					}
				} else {
					self.selectedEl.classList.remove("is-dragged");
					self.dragElement.replaceWith(self.selectedEl);
					self.dragElement = self.selectedEl;
				}

				const node = self.dragElement;
				self.selectNode(node);
				if (window.AlignBuilderModules && window.AlignBuilderModules.WpComponentsPreview) {
					window.AlignBuilderModules.WpComponentsPreview.triggerWpComponentsPreviewInIframe(true);
				}
				Vvveb.TreeList.loadComponents();
				Vvveb.TreeList.selectComponent(node);
				self.loadNodeComponent(node);
				
				if (self.dragType == "section" && Vvveb.scrollToNode) {
					Vvveb.scrollToNode(node);
				}

				if (self.dragMoveMutation === false) {
					Vvveb.Undo.addMutation({type: 'childList', 
											target: node.parentNode, 
											addedNodes: [node], 
											nextSibling: node.nextSibling});
				} else {
					self.dragMoveMutation.newParent = node.parentNode;
					self.dragMoveMutation.newNextSibling = node.nextSibling;
					
					Vvveb.Undo.addMutation(self.dragMoveMutation);
					self.dragMoveMutation = false;
				}
			}
		};
		
		self.frameBody.addEventListener("mouseup", highlightUp);
		self.highlightUp = highlightUp;

		let resolveTextEditableElement = function(el) {
			if (!el || el.nodeType !== 1) return null;
			if (el.closest('[data-component-shortcode], [data-component-wp-block], [data-component-post-content]')) return null;

			const nonTextTags = ['IMG', 'VIDEO', 'AUDIO', 'SVG', 'CANVAS', 'INPUT', 'TEXTAREA', 'SELECT', 'IFRAME', 'BODY', 'HTML', 'HR'];
			if (nonTextTags.includes(el.tagName)) return null;

			const textBlock = el.closest('p, h1, h2, h3, h4, h5, h6, li, blockquote, figcaption, dt, dd');
			if (textBlock && !textBlock.closest('[data-component-shortcode], [data-component-wp-block], [data-component-post-content]')) {
				return textBlock;
			}

			const textTags = ['SPAN', 'A', 'BUTTON', 'LABEL', 'B', 'STRONG', 'I', 'EM', 'SMALL', 'U', 'S', 'MARK', 'CITE', 'Q', 'TD', 'TH'];
			if (textTags.includes(el.tagName)) return el;

			if (el.children.length === 0 && el.textContent.trim().length > 0) return el;

			return null;
		};

		let startTextEdit = function(target) {
			if (!target || !Vvveb.WysiwygEditor) return;
			if (Vvveb.WysiwygEditor.isActive && self.texteditEl === target) return;

			if (Vvveb.WysiwygEditor.isActive && self.texteditEl !== target) {
				if (typeof self._cleanupTextEdit === "function") {
					self._cleanupTextEdit();
				}
				Vvveb.WysiwygEditor.destroy(self.texteditEl);
			}

			self.selectPadding = 10;
			self.texteditEl = target;

			Vvveb.WysiwygEditor.edit(self.texteditEl);
			
			let _updateSelectBox = function(event) {
				if (!self.texteditEl) return;
				let box = self.texteditEl.getBoundingClientRect ? self.texteditEl.getBoundingClientRect() : { top: 0, left: 0, width: self.texteditEl.offsetWidth, height: self.texteditEl.offsetHeight };

				let SelectBox = document.getElementById("select-box");
				if (SelectBox) {
					SelectBox.style.top  = (box.top - self.selectPadding) + "px";
					SelectBox.style.left = (box.left - self.selectPadding) + "px";
					SelectBox.style.width = ((box.width || self.texteditEl.offsetWidth) + (self.selectPadding * 2)) + "px";
					SelectBox.style.height = ((box.height || self.texteditEl.offsetHeight) + (self.selectPadding * 2)) + "px";
					SelectBox.style.display = "block";
				}
			};

			let _cleanupTextEditListeners = function() {
				if (target) {
					target.removeEventListener("blur", _onBlurCleanup);
					target.removeEventListener("keyup", _updateSelectBox);
					target.removeEventListener("paste", _updateSelectBox);
					target.removeEventListener("input", _updateSelectBox);
				}
				if (typeof Vvveb.WordEditor !== "undefined" && Vvveb.WordEditor.hideBubbleToolbar) {
					Vvveb.WordEditor.hideBubbleToolbar();
				}
				self._cleanupTextEdit = null;
			};

			let _onBlurCleanup = function() {
				_updateSelectBox();
				_cleanupTextEditListeners();
			};

			self._cleanupTextEdit = _cleanupTextEditListeners;
			
			target.addEventListener("blur", _onBlurCleanup);	
			target.addEventListener("keyup", _updateSelectBox);	
			target.addEventListener("paste", _updateSelectBox);	
			target.addEventListener("input", _updateSelectBox);	
			_updateSelectBox();	
			
			let selectBox = document.getElementById("select-box");
			if (selectBox) selectBox.classList.add("text-edit");
			let selectActions = document.getElementById("select-actions");
			if (selectActions) selectActions.style.display = "none";
			let highlightBox = document.getElementById("highlight-box");
			if (highlightBox) highlightBox.style.display = "none";
		};

		self.resolveTextEditableElement = resolveTextEditableElement;
		self.startTextEdit = startTextEdit;

		let highlightDbClick = function(event) {
			if (Vvveb.Builder.contentMode) return false;
			if (Vvveb.Builder.isPreview == false && Vvveb.Builder.highlightEnabled) {
				startTextEdit(event.target);
		 	}
		};
		
		self.frameBody.addEventListener("dblclick", highlightDbClick);
		
		let highlightClick = function(event) {
			
			if (Vvveb.Builder.isPreview == false && Vvveb.Builder.highlightEnabled){
				if (event.target) {
					if (Vvveb.WysiwygEditor.isActive) {
						if (self.texteditEl && self.texteditEl.contains(event.target)) {
							return true;
						}
						if (typeof self._cleanupTextEdit === "function") {
							self._cleanupTextEdit();
						}
						Vvveb.WysiwygEditor.destroy(self.texteditEl);
					}
					
					let target = event.target;
					let customComp = resolveCustomComponent(target, event);
					if (customComp) {
						target = customComp;
					}
					
					self.selectNode(target);
					Vvveb.TreeList.selectComponent(target);
					self.loadNodeComponent(target);

					if (Vvveb.component.resizable) {
						document.getElementById("select-box").classList.add("resizable");
                      	self.resizeMode = Vvveb.component.resizeMode;
					} else {
						document.getElementById("select-box").classList.remove("resizable");
					}
					
					let addSectionBox = document.getElementById("add-section-box");
					if (addSectionBox) {
						addSectionBox.style.display = "none";
					}

					if (Vvveb.Builder.contentMode && window.AlignContentModeEditor) {
						AlignContentModeEditor.handleClick(target, event);
					}

					event.preventDefault();
					return false;
				}	
			}	
			
		};
		
		self.frameBody.addEventListener("click", highlightClick);
		
	}
});
