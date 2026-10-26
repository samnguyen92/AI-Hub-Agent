/**
 * Align Builder Section - Separators & Component Definition
 * @package Align
 */

let SectionBottomSeparator = [{
        key: "section_bottom_separator",
        inputtype: SectionInput,
        name:false,
        sort: section_sort++,
        //section: style_section,
        data: {header:"Bottom Separator"},
	},{
        //name: "Enable",
		name: false,
        key: "top_bottom",
        sort: section_sort++,
        inline: true,
        validValues: ["", "active"],
        inputtype: ToggleInput,
        data: {
            className: "",
            on: "active",
            off: ""
        }
	}, 
];
	
/* Section */
let ComponentSectionContent = [
	...SectionContent,
	...SectionBackground,
	...SectionOverlay,
	...sectionSeparatorProperties("top", "Top"),
	...sectionSeparatorProperties("bottom", "Bottom"),
];
   

let ComponentSectionStyle = [];/*[{
        key: "Section Style",
        inputtype: SectionInput,
        name:false,
        //section: style_section,
        data: {header:"Style"},
    },{
        name: "Text1 Style",
        key: "text1",
        htmlAttr: "innerHTML",
        inputtype: TextInput,
        //section: style_section,
    },{
        name: "Name1 Style",
        key: "name1",
        htmlAttr: "name",
        inputtype: TextInput,
        //section: style_section,
    },{
        name: "Type1 Style",
        key: "type1",
		htmlAttr: "type",
        inputtype: SelectInput,
        //section: style_section,
        data: {
		options: [{
			value: "button",
			text: "button"
		},{	
			value: "reset",
			text: "reset"
		},{
			value: "submit",
			text: "submit"
		}],
		}
   	},{
        name: "Autofocus1 Style",
        key: "autofocus1",
        htmlAttr: "autofocus",
        inputtype: CheckboxInput,
		inline:true,
        col:6,
        //section: style_section
   	},{
        name: "Disabled1 Style",
        key: "disabled1",
        htmlAttr: "disabled",
        inputtype: CheckboxInput,		
		inline:true,
        col:6,
        //section: style_section,
}];*/

let ComponentSectionAdvanced = [];/* [{
	key: "Section Advanced",
	inputtype: SectionInput,
	name:false,
	section: advanced_section,
	data: {header:"Advanced"},
    },{
        name: "Text1 Advanced",
        key: "text1",
        htmlAttr: "innerHTML",
        inputtype: TextInput,
        section: advanced_section,
    },{
        name: "Name1 Advanced",
        key: "name1",
        htmlAttr: "name",
        inputtype: TextInput,
        section: advanced_section,
    },{
        name: "Type1 Advanced",
        key: "type1",
		htmlAttr: "type",
        inputtype: SelectInput,
        section: advanced_section,
        data: {
		options: [{
			value: "button",
			text: "button"
		},{	
			value: "reset",
			text: "reset"
		},{
			value: "submit",
			text: "submit"
		}],
	}
    },{
        name: "Autofocus1 Advanced",
        key: "autofocus1",
        htmlAttr: "autofocus",
        inputtype: CheckboxInput,
		inline:true,
        col:6,
        section: advanced_section
    },{
        name: "Disabled1 Advanced",
        key: "disabled1",
        htmlAttr: "disabled",
        inputtype: CheckboxInput,		
		inline:true,
        col:6,
        section: advanced_section,
}];*/

function componentsInit(node) {
		
		document.querySelectorAll('.mb-2[data-group]').forEach(e => e.classList.add("d-none"));

		let img = node.querySelector(":scope > .background-container img");
		let video = node.querySelector(":scope > .background-container video");
		let yt = node.querySelector(":scope > .background-container .iframe-container");
		let overlay = node.querySelector(":scope > .overlay");
		let separatorTop = node.querySelector(":scope > .separator.top");
		let separatorBottom = node.querySelector(":scope > .separator.bottom");
		let bg = "";
	
		if (img && (img.offsetParent || img.offsetHeight || !img.classList.contains("d-none"))) {
			bg = "bg-image";
		}
		
		if (video && (video.offsetParent || video.offsetHeight || !video.classList.contains("d-none"))) {
			bg = "bg-video";
		}	
		
		if (yt && (yt.offsetParent || yt.offsetHeight || !yt.classList.contains("d-none"))) {
			bg = "bg-yt";
		}	
	
		let showSection = function (section) {
			document.querySelectorAll('.mb-2[data-group="' + section + '"]').forEach(e => e.classList.remove("d-none"));
		}
		
		if (bg) {
			showSection(bg);
		}
		
		if (overlay && overlay.offsetParent) {
			showSection("overlay");
		}		
		
		if (separatorTop && separatorTop.offsetParent) {
			showSection("top_separator");
		}
		
		if (separatorBottom && separatorBottom.offsetParent) {
			showSection("bottom_separator");
		}
}

//Vvveb.Components.add("elements/section", {
Vvveb.Components.extend("_base", "elements/section", {
    nodes: ["section"],
    name: "Section",
    image: "icons/section.svg",
    html: `<section>
				<div class="container">
					<h1>Section</h1>
				</div>
			</section>`,
    properties: [
		...ComponentSectionContent, 
		...ComponentSectionStyle,
		...ComponentSectionAdvanced
	],
	init: componentsInit	
});  

//Vvveb.Components.add("elements/header", {
Vvveb.Components.extend("_base", "elements/header", {
    nodes: ["header"],
    name: "Header",
    image: "icons/section.svg",
    html: `<header>
				<div class="container">
					<h1>Section</h1>
				</div>
			</header>`,
    properties: [
		...ComponentSectionContent, 
		...ComponentSectionStyle,
		...ComponentSectionAdvanced
	],
    init: componentsInit	
});  

//Vvveb.Components.add("elements/footer", {
Vvveb.Components.extend("_base", "elements/footer", {
    nodes: ["footer"],
    name: "Footer",
    image: "icons/section.svg",
    html: `<footer>
				<div class="container">
					<h1>Section</h1>
				</div>
			</footer>`,
    properties: [
		...ComponentSectionContent, 
		...ComponentSectionStyle,
		...ComponentSectionAdvanced
	],
    init: componentsInit
});
