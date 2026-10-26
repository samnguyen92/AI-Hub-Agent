/**
 * Align Builder Elements Components - Tabs, Tab Panes & Accordion Elements
 * @package Align
 */

Vvveb.Components.add("elements/tab", {
	//attributes: ["data-component-tabs"],
	classes: ["tab-pane"],
    name: "Tab",
    image: "icons/tabs.svg",
    html: ``,
    properties: [{
			name: "Id",
			key: "id",
			htmlAttr: "id",
			inline:false,
			col:6,
			inputtype: TextInput
		},{
			name: "Class",
			key: "class",
			htmlAttr: "class",
			inline:false,
			col:6,
			inputtype: TextInput
			
		} , {
        name: "Active",
        key: "active",
        htmlAttr: "class",
        validValues: ["", "active"],
        inputtype: ToggleInput,
        inline:true,
        col:6,
        data: {
            on: "active",
            off: ""
        }
    }]
}); 
	
Vvveb.Components.add("elements/tabs", {
    attributes: ["data-component-tabs"],
    name: "Tabs",
    image: "icons/tabs.svg",
    html: `
	<div data-component-tabs id="tabs-parentId">
			<nav>
			  <div class="nav nav-tabs" role="tablist">
				<button class="nav-link active" id="nav-tab-parentId-1" data-bs-toggle="tab" data-bs-target="#nav-parentId-1" type="button" role="tab" aria-controls="nav-1" aria-selected="true">Home</button>
				<button class="nav-link" id="nav-tab-parentId-2" data-bs-toggle="tab" data-bs-target="#nav-parentId-2" type="button" role="tab" aria-controls="nav-2" aria-selected="false">Profile</button>
				<button class="nav-link" id="nav-tab-parentId-3" data-bs-toggle="tab" data-bs-target="#nav-parentId-3" type="button" role="tab" aria-controls="nav-3" aria-selected="false">Contact</button>	  </div>
			</nav>
			<div class="tab-content">
			  <div class="tab-pane p-4 show active" id="nav-parentId-1" role="tabpanel" aria-labelledby="nav-tab-1" tabindex="0">
				<p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. Corporis perferendis rem accusantium ducimus animi nesciunt expedita omnis aut quas molestias!</p>
			  </div>
			  <div class="tab-pane p-4" id="nav-parentId-2" role="tabpanel" aria-labelledby="nav-tab-2" tabindex="0">
				<p>Mauris viverra cursus ante laoreet eleifend. Donec vel fringilla ante. Aenean finibus velit id urna vehicula, nec maximus est sollicitudin</p>
			  </div>
			  <div class="tab-pane p-4" id="nav-parentId-3" role="tabpanel" aria-labelledby="nav-tab-3" tabindex="0">
				<p>Quisque sagittis non ex eget vestibulum</p>
			  </div>
			</div>
	</div>`,
	afterDrop: function(node) {
		//set unique accordion parent id, this is a bootstrap accordion limitation
		let parentId = Math.ceil(Math.random() * 1000);
		node.id = 'tabs-' + parentId;
		node.innerHTML = node.innerHTML.replaceAll('parentId', parentId);

		return node;
	},	
    properties: [{
			//name: "List",
			key: "list",
			component: "elements/tab",
			children :[{
				component: "elements/tab",
				name: "html/gridcolumn",
				classesRegex: ["col-"],
			}],			
			inline:false,
			inputtype: ListInput,
			data: {
				selector:"> .nav-link",
				container:"nav > .nav-tabs",
				prefix:"Tab ",
				name: "text",
				removeElement: false,//handle manually to delete pane also
				//"newElement": ``
			},
			onChange: function(node, value, input, component, event) {
				let element = node;
				let tabsId = element.id.replace('tabs-','');
				
				let nav = node.querySelector("nav .nav-tabs");
				let content = node.querySelector(".tab-content");

				if (event.action) {
					if (event.action == "add") {
						let random = Math.floor(Math.random() * 100) + 1;
						let index = nav.childElementCount + 1;
						
						nav.append(generateElements(`<button class="nav-link" id="nav-tab-${tabsId}-${index}-${random}" data-bs-toggle="tab" data-bs-target="#tab-${tabsId}-${index}-${random}" type="button" role="tab" aria-controls="tab-${index}-${random}" aria-selected="false">Tab ${index}</button>`)[0]);
						
						content.append(generateElements(`<div class="tab-pane p-4" id="tab-${tabsId}-${index}-${random}" role="tabpanel" aria-labelledby="tab-${tabsId}-${index}-${random}" tabindex="0"><p>Never think of results, just do!</p></div>`)[0]);
						
						//temporary solution to better update list
						Vvveb.Components.render("elements/tabs");
					}
					
					let index = event.index + 1;
					if (event.action == "remove") {
						nav.querySelector("button:nth-child(" + index + ")").remove();
						content.querySelector(".tab-pane:nth-child(" + index + ")").remove();
					} else if (event.action == "select") {
						let tab = nav.querySelector("button:nth-child(" + index + ")");
						Vvveb.Builder.iframe.contentWindow.bootstrap.Tab.getOrCreateInstance(tab).show();
					}
				}
				
				return node;
			},
		}
	]
}); 	


//Accordion
Vvveb.Components.add("elements/accordion", {
    classes: ["accordion"],
    name: "Accordeon",
    image: "icons/accordion.svg",
    html: `<div class="accordion" id="accordion-parentId">
		  <div class="accordion-item">
			<h2 class="accordion-header" id="headingOne-parentId">
			  <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne-parentId" aria-expanded="true" aria-controls="collapseOne-parentId">
				Accordion Item #1
			  </button>
			</h2>
			<div id="collapseOne-parentId" class="accordion-collapse collapse show" aria-labelledby="headingOne-parentId" data-bs-parent="#accordion-parentId">
			  <div class="accordion-body">
				<p>Mauris viverra cursus ante laoreet eleifend. Donec vel fringilla ante. Aenean finibus velit id urna vehicula, nec maximus est sollicitudin</p>
			  </div>
			</div>
		  </div>
		  <div class="accordion-item">
			<h2 class="accordion-header" id="headingTwo-parentId">
			  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo-parentId" aria-expanded="false" aria-controls="collapseTwo">
				Accordion Item #2
			  </button>
			</h2>
			<div id="collapseTwo-parentId" class="accordion-collapse collapse" aria-labelledby="headingTwo-parentId" data-bs-parent="#accordion-parentId">
			  <div class="accordion-body">
				<p>Mauris viverra cursus ante laoreet eleifend. Donec vel fringilla ante. Aenean finibus velit id urna vehicula, nec maximus est sollicitudin</p>
			  </div>
			</div>
		  </div>
		  <div class="accordion-item">
			<h2 class="accordion-header" id="headingThree-parentId">
			  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree-parentId" aria-expanded="false" aria-controls="collapseThree">
				Accordion Item #3
			  </button>
			</h2>
			<div id="collapseThree-parentId" class="accordion-collapse collapse" aria-labelledby="headingThree-parentId" data-bs-parent="#accordion-parentId">
			  <div class="accordion-body">
				<p>Mauris viverra cursus ante laoreet eleifend. Donec vel fringilla ante. Aenean finibus velit id urna vehicula, nec maximus est sollicitudin</p>
			  </div>
			</div>
		  </div>
		</div>`,
	afterDrop: function(node) {
		//set unique accordion id
		let parentId = Math.ceil(Math.random() * 1000);
		//node.outerHTML = node.outerHTML.replaceAll('parentId', parentId);
		node.id = 'accordion-' + parentId;
		node.innerHTML = node.innerHTML.replaceAll('parentId', parentId);
		
		return node;
	},
    properties: [{
			//name: "List",
			key: "list",
			component: "elements/tab",
			inline:false,
			inputtype: ListInput,
			data: {
				selector:":scope > .accordion-item",
				container:"",
				prefix:"Item ",
				name: "text",
				nameElement: ".accordion-button",
				removeElement: false,//handle manually
				//"newElement": ``
			},
			onChange: function(node, value, input, component, event) {
				let element = node;
				let accordionId = element.id.replace('accordion-','');
				
				if (event.action) {
					if (event.action == "add") {
						let random = Math.floor(Math.random() * 100) + 1;
						let index = element.childElementCount + 1;
						
						node.append(generateElements(`<div class="accordion-item">
							<h2 class="accordion-header" id="heading-${index}-${random}">
							  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-${index}-${random}" aria-expanded="false" aria-controls="collapseTwo">Accordion Item #${index}</button>
							</h2>
							<div id="collapse-${index}-${random}" class="accordion-collapse collapse" aria-labelledby="heading-${index}-${random}" data-bs-parent="#accordion-${accordionId}">
							  <div class="accordion-body">
								<p>Begin with the possible; begin with one step.</p>
							  </div>
							</div>
						  </div>`)[0]);
						
						//temporary solution to better update list
						Vvveb.Components.render("elements/accordion");
					}
					
					let index = (event.index + 1);
					if (event.action == "remove") {
						node.querySelector(":scope > .accordion-item:nth-child(" + index + ")").remove();
					} else if (event.action == "select") {
						let el = node.querySelector(":scope > .accordion-item:nth-child(" + index + ")");
						let btn= el.querySelector(".accordion-button");
						let collapse = el.querySelector(" .collapse");

						node.querySelectorAll(":scope > .accordion-item .collapse").forEach(e => e.classList.remove("show"));
						node.querySelectorAll(":scope > .accordion-item .accordion-button").forEach(btn => btn.classList.add("collapsed"));
						collapse.classList.add("show");
						btn.classList.remove("collapsed");
						//el[0].click();
						//Vvveb.Builder.iframe.contentWindow.bootstrap.Collapse.getOrCreateInstance(el[0]).toggle();
					}
				}
				
				return node;
			},
		},{
			name: "Flush",
			key: "flush",
			htmlAttr: "class",
			validValues: ["accordion-flush"],
			inputtype: ToggleInput,
			data: {
				on: "accordion-flush",
				off: ""
			}
		},		
	]
});
