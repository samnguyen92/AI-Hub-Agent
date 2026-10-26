/**
 * Align Builder Engine - Breadcrumb Hierarchy Bar
 * @package Align
 */

Vvveb.Breadcrumb = {
	tree:false,	
	
	init: function() {
		this.tree = document.querySelector(".breadcrumb-navigator > .breadcrumb");
		if (!this.tree) return;
		this.tree.replaceChildren();

		this.tree.addEventListener("click", function (e) {
			let element = event.target.closest(".breadcrumb-item");
			if (element) {
				let node = element._node;
				if (node) {
					Vvveb.Builder.selectNode(node);
					Vvveb.Builder.loadNodeComponent(node);
					if (Vvveb.scrollToNode) Vvveb.scrollToNode(node);
				}
				
				e.preventDefault();
			}
		});
		
		let currentHoverNode;
		this.tree.addEventListener("mousemove", function (e) {
			if (event.target == currentHoverNode) return;
			currentHoverNode = event.target;
			
			let element = event.target.closest(".breadcrumb-item");
			if (element) {
				let node = element._node;

				node.dispatchEvent(new MouseEvent("mousemove", {
					bubbles: true,
					cancelable: true,
				}));
			}
		})
	},
	
	addElement: function(data, element) {
		if (!this.tree) {
			this.tree = document.querySelector(".breadcrumb-navigator > .breadcrumb");
		}
		if (!this.tree) return;
		let li = generateElements(tmpl("vvveb-breadcrumb-navigaton-item", data))[0];
		li._node = element;			
		this.tree.prepend(li);
	},
		
	loadBreadcrumb: function(element) {
		if (!this.tree) {
			this.tree = document.querySelector(".breadcrumb-navigator > .breadcrumb");
		}
		if (!this.tree) return;
		this.tree.replaceChildren();
		let currentElement = element;
		
		while (currentElement.parentElement) {
			let elementType = Vvveb.Builder._getElementType(currentElement);
			let el = elementType[1].toLowerCase();
		
			this.addElement({
				"name":  el + " " + elementType[0],
				"className": "el-" + el
			}, currentElement);
			
			currentElement = currentElement.parentElement;
		}
	}
}
