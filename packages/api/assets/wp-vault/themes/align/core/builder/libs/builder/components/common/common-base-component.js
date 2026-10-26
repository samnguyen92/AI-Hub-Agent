/**
 * Align Builder Common Properties - Base Component Definition & Inspector Groups
 * @package Align
 */

/*
Copyright 2017 Ziadin Givan

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

   http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

https://github.com/givanz/Vvvebjs
*/

bgcolorClasses = ["bg-primary", "bg-secondary", "bg-success", "bg-danger", "bg-warning", "bg-info", "bg-body-secondary", "bg-dark", "bg-white"]

bgcolorSelectOptions = [{
	value: "Default",
	text: ""
},{
	value: "bg-primary",
	text: "Primary"
},{
	value: "bg-secondary",
	text: "Secondary"
},{
	value: "bg-success",
	text: "Success"
},{
	value: "bg-danger",
	text: "Danger"
},{
	value: "bg-warning",
	text: "Warning"
},{
	value: "bg-info",
	text: "Info"
},{
	value: "bg-body-secondary",
	text: "Light"
},{
	value: "bg-dark",
	text: "Dark"
},{
	value: "bg-white",
	text: "White"
}];

function changeNodeName(node, newNodeName) {
	let newNode;
	newNode = document.createElement(newNodeName);
	attributes = node.attributes;
	
	for (i = 0, len = attributes.length; i < len; i++) {
		newNode.setAttribute(attributes[i].nodeName, attributes[i].nodeValue);
	}

	while (node.hasChildNodes()) {
		newNode.appendChild(node.removeChild(node.firstChild))
	}

	node.replaceWith(newNode);
	
	return newNode;
}

let base_sort = 1000;//start sorting for base component from 100 to allow extended properties to be first
let style_section = 'style';
let advanced_section = 'advanced';

Vvveb.Components.add("_base", {
    name: "Element",
	properties: [{
        key: "element_header",
        inputtype: SectionInput,
        name:false,
        sort:base_sort++,
        data: {header:"General"},
    },{
        name: "Id",
        key: "id",
        htmlAttr: "id",
        sort: base_sort++,
        inline:false,
        col:6,
        inputtype: TextInput
    },{
        name: "Title",
        key: "title",
        htmlAttr: "title",
        sort: base_sort++,
        inline:false,
        col:6,
        inputtype: TextInput
    },{
        name: "Class",
        key: "class",
        htmlAttr: "class",
        sort: base_sort++,
        inline:false,
        col:12,
		inputtype: TagsInput,
		data: {
			allowFreeText:true,
			//get all classes used on the page
			data:[...[...document.querySelectorAll("[class]")].reduce((s, e) =>
				(e.classList.forEach(c => s.add(c)), s), new Set())].sort(),
		},
    }
   ]
});    

//display
Vvveb.Components.extend("_base", "_base", {
	 properties: [{
        key: "display_header",
        inputtype: SectionInput,
        name:false,
        sort: base_sort++,
		section: style_section,
        data: {header:"Display"},
     },{
		//linked styles notice message
		name:"",
		key: "linked_styles_check",
        sort: base_sort++,
        section: style_section,
        inline:false,
        col:12,
        inputtype: NoticeInput,
        data: {
			type:'warning',
			title:'Linked styles',
			text:'This element shares styles with other <a class="linked-elements-hover" href="javascript:void()"><b class="elements-count">4</b> elements</a>, to apply styles <b>only for this element</b> enter a <b>unique id</b> eg: <i>marketing-heading</i> in in <br/><a class="id-input" href="#content-tab" role="tab" aria-controls="components" aria-selected="false" href="#content-tab">Content > General > Id</a>.<br/><span class="text-muted small"></span>',
		},
		afterInit:function(node, inputElement) {
			if (!node || !window.FrameDocument) {
				if (inputElement.style) inputElement.style.display = "none";
				return;
			}

			let selector = Vvveb.StyleManager ? Vvveb.StyleManager.getSelectorForElement(node) : "";
			if (!selector || typeof selector !== "string" || !selector.trim()) {
				if (inputElement.style) inputElement.style.display = "none";
				return;
			}

			let elements = [];
			try {
				elements = window.FrameDocument.querySelectorAll(selector);
			} catch (e) {
				if (inputElement.style) inputElement.style.display = "none";
				return;
			}

			if (elements.length <= 1) {
				if (inputElement.style) {
					inputElement.style.display = "none";
				}
			} else {
				inputElement.style.display = "";
				let countEl = inputElement.querySelector(".elements-count");
				if (countEl) countEl.innerHTML = elements.length;
				let mutedEl = inputElement.querySelector(".text-muted");
				if (mutedEl) mutedEl.innerHTML = selector;
				
				let idInputBtn = inputElement.querySelector(".id-input");
				if (idInputBtn) {
					idInputBtn.addEventListener("click", (event) => {
						document.querySelectorAll(".content-tab a").forEach(e => e.click());
						
						setTimeout(function () { 
							let idInput = document.querySelectorAll("[name=id]");
							idInput.forEach(el => {if (el.offsetParent) el.focus()});
						}, 700);
						
						event.preventDefault();
						return false;
					});
				}
				
				let hoverEl = inputElement.querySelector(".linked-elements-hover");
				if (hoverEl) {
					hoverEl.addEventListener("mouseenter", function (){
						elements.forEach( e => e.style.outline = "2px dotted blue");
					});
					
					hoverEl.addEventListener("mouseleave", function (){
						elements.forEach( e => e.style.outline = "");
					});
				}
			}
		},	 
    },{
        name: "Display",
        key: "display",
        htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        inputtype: SelectInput,
        validValues: ["block", "inline", "inline-block", "none"],
        data: {
            options: [{
                value: "block",
                text: "Block"
            },{
                value: "inline",
                text: "Inline"
            },{
                value: "inline-block",
                text: "Inline Block"
            },{
                value: "inline-block",
                text: "Inline Block"
            },{
                value: "flex",
                text: "Flex"
            },{
                value: "inline-flex",
                text: "Inline Flex"
            },{
                value: "grid",
                text: "Grid"
            },{
                value: "inline-grid",
                text: "Inline grid"
            },{
                value: "table",
                text: "Table"
            },{
                value: "table-row",
                text: "Table Row"
            },{
                value: "list-item",
                text: "List Item"
            },{
                value: "inherit",
                text: "Inherit"
            },{
                value: "initial",
                text: "Initial"
            },{
                value: "none",
                text: "none"
            }]
        }
    },{
        name: "Position",
        key: "position",
        htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        inputtype: SelectInput,
        validValues: ["static", "fixed", "relative", "absolute"],
        data: {
            options: [{
                value: "static",
                text: "Static"
            },{
                value: "fixed",
                text: "Fixed"
            },{
                value: "relative",
                text: "Relative"
            },{
                value: "absolute",
                text: "Absolute"
            }]
        }
    },{
        name: "Top",
        key: "top",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        parent:"",
        inputtype: CssUnitInput
	},{
        name: "Left",
        key: "left",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        parent:"",
        inputtype: CssUnitInput
    },{
        name: "Bottom",
        key: "bottom",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        parent:"",
        inputtype: CssUnitInput
	},{
        name: "Right",
        key: "right",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        parent:"",
        inputtype: CssUnitInput
    },{
        name: "Float",
        key: "float",
        htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:12,
        inline:false,
        inputtype: RadioButtonInput,
        data: {
			extraclass:"btn-group-sm btn-group-fullwidth",
            options: [{
                value: "none",
                icon:"la la-times",
                //text: "None",
                title: "None",
                checked:true,
            },{
                value: "left",
                //text: "Left",
                title: "Left",
                icon:"la la-align-left",
                checked:false,
            },{
                value: "right",
                //text: "Right",
                title: "Right",
                icon:"la la-align-right",
                checked:false,
            }],
         }
	},{
        name: "Opacity",
        key: "opacity",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:12,
		inline:false,
        parent:"",
        inputtype: RangeInput,
        data:{
			max: 1, //max zoom level
			min:0,
			step:0.1
       },
	},{
        name: "Background",
        key: "background-color",
        sort: base_sort++,
		section: style_section,
        col:12,
		inline:true,
		htmlAttr: "style",
        inputtype: ColorInput,
	},{
        name: "Text Color",
        key: "color",
        sort: base_sort++,
		section: style_section,
        col:12,
		inline:true,
		htmlAttr: "style",
        inputtype: ColorInput,
  	}]
});    

//Typography
