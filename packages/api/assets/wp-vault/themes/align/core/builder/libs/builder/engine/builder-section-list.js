/**
 * Align Builder Engine - Section List Accordion
 * @package Align
 */

Vvveb.SectionList = {
	
	selector: '.sections-container',
	allowedComponents: {},
	sortableInstance: null,
	
	init: function(allowedComponents = {}) {

		this.allowedComponents = allowedComponents;

		document.querySelector(this.selector).addEventListener("click", function (e) {
			let element = e.target.closest(":scope > div .controls");
			if (element) {
				let node = element.parentNode._node;
				if (node) {
					if (Vvveb.scrollToNode) Vvveb.scrollToNode(node);
					Vvveb.Builder.selectNode(node);
					Vvveb.Builder.loadNodeComponent(node);
				}
			}
		});
		
		document.querySelector(this.selector).addEventListener("dblclick", function (e) {
			let element = e.target.closest(":scope > div");
			if (element) {
				const node = element._node;
				node.click();
			}
		});
		
		
		document.querySelector(this.selector).addEventListener("click", function (e) {
			let element = e.target.closest("li[data-component] label");
			if (element) {
				let node = element.parentNode._node;
				if (node) {
					if (Vvveb.scrollToNode) Vvveb.scrollToNode(node);
					node.click();
				}
			}
		});
		
		document.querySelector(this.selector).addEventListener("mouseenter", function (e) {
			let element = e.target.closest("li[data-component] label");
			if (element) {
				const node = document.querySelector(element.parentNode._node);
				node.css("outline","1px dashed blue");
			}
		});
		
		document.querySelector(this.selector).addEventListener("mouseleave", function (e){
			let element = e.target.closest("li[data-component] label");
			if (element) {
				const node = document.querySelector(element.parentNode._node);
				node.css("outline","");
				if (node.getAttribute("style") == "") node.removeAttribute("style");
			}
		});		
		
		
		document.querySelector(this.selector).addEventListener("click", function (e) {
			let element = e.target.closest(".delete-btn");
			if (element) {
				let section = element.closest(".section-item");
				let node = section._node;
				node.remove();
				section.remove();
				Vvveb.TreeList.loadComponents();
				
				e.stopPropagation();
				e.preventDefault();
			}
		});

		let sectionIn;
		let img = document.querySelector(".block-preview img");
		document.querySelector(".sections-list").addEventListener("mouseover", function (e) {
			let element = e.target.closest("li[data-type]");
			if (element) {
				if (sectionIn != element) {
					let src = element.querySelector("img").getAttribute("src");
					sectionIn = element;
					img.setAttribute("src", src);
					img.style.display = "";
				}
			} else {
				sectionIn = element;
				img.setAttribute("src", "");
				img.style.display = "none";
			}
		})
		
		/*
		document.querySelector(this.selector).addEventListener("click", ".up-btn", function (e) {
			let section = e.target.closest(".section-item");
			let node = section._node;
			Vvveb.Builder.moveNodeUp(node);
			Vvveb.Builder.moveNodeUp(section);
			
			e.preventDefault();
		});


		document.querySelector(this.selector).addEventListener("click", ".down-btn", function (e) {
			let section = e.target.closest(".section-item");
			let node = section._node;
			Vvveb.Builder.moveNodeDown(node);
			Vvveb.Builder.moveNodeDown(section);
			
			e.preventDefault();
		});
		*/

		
		let self = this;
		document.querySelector(".sections-list")?.addEventListener("click", function (e) {
			if (e.target.closest(".custom-tpl-actions") || e.target.closest(".delete-custom-tpl-btn") || e.target.closest(".edit-custom-tpl-btn")) return;
			let element = e.target.closest(".add-section-btn") || e.target.closest("li[data-drag-type='section']");
			if (element) {
				let item = element.closest("li") || element;
				let section = Vvveb.Sections.get(item.dataset.type);
				if (!section || !section.html) return;
				let node = generateElements(section.html)[0];
				let sectionType = node.tagName.toLowerCase();
				let afterSection = Vvveb.Builder.frameBody.querySelector(":scope > " + sectionType + ":last-of-type");
				
				if (afterSection) {
					afterSection.after(node);
				} else {
					if (sectionType == "nav") {
						afterSection = Vvveb.Builder.frameBody.querySelector(":scope > nav:first,> header:last-of-type");		
						
						if (afterSection) {
							afterSection.before(node);
						} else {
							Vvveb.Builder.frameBody.append(node);
						}
					} else if (sectionType != "footer") {
						afterSection = Vvveb.Builder.frameBody.querySelector("body > footer:last-of-type");		
						
						if (afterSection) {
							afterSection.before(node);
						} else {
							Vvveb.Builder.frameBody.append(node);
						}
					} else {
						let newsection = Vvveb.Builder.frameBody.querySelector('newsection')
						if (newsection) {
							newsection.before(node);
						} else {
							Vvveb.Builder.frameBody.append(node);
						}
					}
				}
				
				//node.click();

				Vvveb.Builder.selectNode(node);
				Vvveb.Builder.loadNodeComponent(node);
				/*
				Vvveb.Builder.frameHtml.animate({
					scrollTop: node.offset().top
				}, 1000);
				
				delay(() => node.click(), 1000);
				*/
				
				Vvveb.Undo.addMutation({type: 'childList', 
										target: node.parentNode, 
										addedNodes: [node], 
										nextSibling: node.nextSibling});								

				
				self.loadSections();
				Vvveb.TreeList.loadComponents();
				Vvveb.TreeList.selectComponent(node);
				if (Vvveb.scrollToNode) Vvveb.scrollToNode(node);

				e.preventDefault();
			}
		});
		
		document.querySelector(this.selector).addEventListener("click", function (e) {
			let element = e.target.closest(".properties-btn");
			if (element) {
				let section = element.closest(".section-item");
				let node = section._node;
				node.click();
				
				e.preventDefault();
			}
		});
		
	},
	
	getSections: function() {
		let sections = [];
		let sectionList = 
			window.FrameDocument.body.querySelectorAll(':scope > section, :scope > header, :scope > footer, :scope > main, :scope > nav');
		
		sectionList.forEach(function (node, i) {
			//skip internal editor helper elements
			if (node.id === 'vvvebjs-new-section' || 
				(node.tagName && node.tagName.toUpperCase() === 'NEWSECTION') ||
				(node.hasAttribute && node.hasAttribute('data-vvveb-helpers'))) {
				return;
			}

			let id = node.id ? node.id : (node.title ? node.title : node.ariaLabel ?? node.className);
			if (!id) {
				id = 'section-' +  Math.floor(Math.random() * 10000);
			}
			let section = {
				name: id.replace(/[^\w+]+/g,' '),
				id: node.id,
				type: node.tagName.toLowerCase(),
				node: node
			};
			sections.push(section);
		});
		
		return sections;
	},

	loadComponents: function(sectionListItem, section, allowedComponents = {}) {

		let tree = [];
		getNodeTree(section, tree, allowedComponents);
		
		let html = drawComponentsTree(tree);
		document.querySelector("ol", sectionListItem).replaceWith(html);
	},
	
	
	addSection: function(data) {
		let section = generateElements(tmpl("vvveb-section", data))[0];
		section._node = data.node;
		section.removeAttribute("draggable");
		document.querySelector(this.selector).append(section);

		//this.loadComponents(section, data.node, this.allowedComponents);
	},

	loadSections: function() {
		let sections = this.getSections();
		let container = document.querySelector(this.selector);

		if (this.sortableInstance) {
			this.sortableInstance.destroy();
			this.sortableInstance = null;
		}

		container.replaceChildren();
		for (i in sections) {
			this.addSection(sections[i]);
		}

		if (window.Sortable && container) {
			this.sortableInstance = new Sortable(container, {
				swap: true,
				swapClass: 'sortable-swap-highlight',
				animation: 150,
				forceFallback: true,
				fallbackClass: 'sortable-fallback',
				fallbackTolerance: 3,
				handle: '.section-item',
				onEnd: (evt) => {
					let itemEl = evt.item;
					let swapEl = evt.swapItem;

					if (itemEl && swapEl && itemEl._node && swapEl._node && itemEl !== swapEl) {
						let originalEvt = evt.originalEvent;
						let clientX = originalEvt ? (originalEvt.clientX || (originalEvt.changedTouches && originalEvt.changedTouches[0] ? originalEvt.changedTouches[0].clientX : 0)) : 0;
						let clientY = originalEvt ? (originalEvt.clientY || (originalEvt.changedTouches && originalEvt.changedTouches[0] ? originalEvt.changedTouches[0].clientY : 0)) : 0;

						if (clientX && clientY) {
							let rect = swapEl.getBoundingClientRect();
							let isOverSwapEl = (
								clientX >= rect.left &&
								clientX <= rect.right &&
								clientY >= rect.top &&
								clientY <= rect.bottom
							);
							if (!isOverSwapEl) {
								this.loadSections();
								return;
							}
						}

						let nodeA = itemEl._node;
						let nodeB = swapEl._node;

						if (nodeA.parentNode && nodeB.parentNode) {
							let parentA = nodeA.parentNode;
							let parentB = nodeB.parentNode;
							let siblingA = nodeA.nextSibling === nodeB ? nodeA : nodeA.nextSibling;

							parentB.insertBefore(nodeA, nodeB);
							parentA.insertBefore(nodeB, siblingA);

							if (Vvveb.Undo) {
								Vvveb.Undo.addMutation({
									type: 'swap',
									nodeA: nodeA,
									nodeB: nodeB,
									itemA: itemEl,
									itemB: swapEl
								});
							}

							if (Vvveb.TreeList) {
								Vvveb.TreeList.loadComponents();
							}
						}
					}
				}
			});
		}
	},
	
	//drag and drop 
	dragOver: function(e) {
		let element = e.target.closest("div");
		if (element) {
			if (e.target != dragover && 
				e.target.className == "section-item") {

				if (dragover) {
					dragover.classList.remove("drag-over");
				}
				
				dragover = e.target;  
				dragover.classList.add("drag-over");
			}
		}
	},

	dragEnd: function (e) {
		let element = e.target.closest("div");
		if (element) {

			if (dragover) {
				let parent = selected.parentNode;
				let selectedNode = selected._node;
				let replaceNode = dragover._node;

				if ((dragover.offsetTop > selected.offsetTop)) {
					//replace section item list
					parent.insertBefore(selected, dragover.nextElementSibling);
					//replace section
					replaceNode.parentNode.insertBefore(selectedNode, replaceNode.nextElementSibling);
				} else {
					//replace section item list
					parent.insertBefore(selected, dragover);
					//replace section
					replaceNode.parentNode.insertBefore(selectedNode, replaceNode);
				}
				
				dragover.classList.remove("drag-over");
				
				let node = selectedNode;
				
				Vvveb.Undo.addMutation({type: 'move', 
									target: node,
									oldParent: node.parentNode,
									oldNextSibling: node.nextSibling});
			}

			selected = null;
			dragover = null;
		}
	},

	dragStart: function (e) {
		let element = e.target.closest("div");
		if (element) {
			selected = e.target
		}
	},
}
