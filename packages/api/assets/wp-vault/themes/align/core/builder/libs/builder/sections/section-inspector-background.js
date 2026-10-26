/**
 * Align Builder Section - Background & Video Overlay Properties
 * @package Align
 */

let SectionBackground = [{
        key: "section_background_header",
        inputtype: SectionInput,
        name:false,
        sort: section_sort++,
        //section: style_section,
        data: {header:"Background"},
   },{
        name: false,
        key: "section-bg",
        sort: section_sort++,
        inputtype: RadioButtonInput,
        data: {
            inline: true,
            extraclass:"btn-group-sm btn-group-fullwidth",
            options: [{
                value: "none",
                text: "None",
                title: "None",
                checked:true,
            },{
                value: "bg-image",
                icon:"la la-image",
                text: "Image",
                title: "Image",    
            },{/*  
                value: "gradient",
                icon:"la la-palette",
                text: "Gradient",
                title: "Gradient",
            },{*/
                value: "bg-video",
                icon:"la la-video",
                text: "Video",
                title: "Video",
            },{
                value: "bg-yt",
                icon:"lab la-youtube",
                text: "Youtube",
                title: "Youtube",/*
            },{
                value: "slideshow",
                icon:"la la-arrows-alt-h",
                text: "Slider",
                title: "Slider",*/
            }],
        },
		hideGroups : function() {
			document.querySelectorAll('.mb-2[data-group="bg-image"],.mb-2[data-group="bg-video"],.mb-2[data-group="bg-yt"]').forEach(e => e.classList.add("d-none"));
		},
		
		onChange : function(node, value, input) {
			this.hideGroups();
			document.querySelectorAll('.mb-2[data-group="'+ input.value + '"].d-none').forEach((el, i) => {
				el.classList.remove("d-none");
			});

			let container = node.querySelector(":scope > .background-container");
			if (!container) {
				container = generateElements('<div class="background-container"></div>')[0];
				node.appendChild(container);
			}
			
			let img = node.querySelector(":scope > .background-container > img");
			let video = node.querySelector(":scope > .background-container > video");
			let yt = node.querySelector(":scope > .background-container > .iframe-container ");
			
			container.querySelectorAll(":scope > *").forEach((el, i) => {
				el.classList.add("d-none");
			});
			
			switch (value) {
				case "bg-image":
					if (img) {
						img.classList.remove("d-none");
					} else {
						container.append(generateElements(bgImageTemplate)[0]);
						//reselect element to load image
						node.click();
					}
				break;
				case "bg-video":
					if (video) {
						video.classList.remove("d-none");
					} else {
						container.append(generateElements(bgVideoTemplate)[0]);
						//reselect element to load video
						node.click();
					}
				break;
				case "bg-yt":
					if (yt) {
						yt.classList.remove("d-none");
					} else {
						container.append(generateElements(bgYoutubeTemplate)[0]);
						//reselect element to load video
						node.click();
					}
				break;
			}


			return node;
		}, 
		init: function(node) {
			let selected = "none";
			let img   = node.querySelector(":scope > .background-container > img");
			let video = node.querySelector(":scope > .background-container > video");
			let yt = node.querySelector(":scope > .background-container > .iframe-container ");
			
			if (img && (img.offsetParent || img.offsetHeight || !img.classList.contains("d-none"))) {
				selected = "bg-image";
			}
			
			if (video && (video.offsetParent || video.offsetHeight || !video.classList.contains("d-none"))) {
				selected = "bg-video";
			}
			
			if (yt && (yt.offsetParent || yt.offsetHeight || !yt.classList.contains("d-none"))) {
				selected = "bg-yt";
			}
			
			this.hideGroups();
			return selected;
		},            
    },{
        name: "Image",
        key: "src",
        sort: section_sort++,
        htmlAttr: "src",
		child:":scope > .background-container > img",
		group:"bg-image",
		inline:true,
        inputtype: ImageInput
    },{
        name: "Video",
        child: "source",
        key: "src",
        sort: section_sort++,
        htmlAttr: "src",
		child:":scope > .background-container > video",
		group:"bg-video",
		inline:true,
        inputtype: VideoInput
   },{
        name: "Poster",
        key: "poster",
        sort: section_sort++,
        htmlAttr: "poster",
		child:":scope > .background-container > video",
		group:"bg-video",
		inline:true,
        inputtype: ImageInput
    },{
        name: "Video",
        key: "src",
        sort: section_sort++,
        htmlAttr: "src",
		child:":scope > .background-container > .iframe-container > iframe",
		group:"bg-yt",
		inline:true,
        inputtype: TextInput,
        onChange: function(node, value) {
			return value.replace("watch?v=", "embed/") + "?autoplay=1&loop=1&playsinline=1&controls=0&mute=1";

			return node;
		},
    },{
        name: "Parallax",
        key: "parallax",
        sort: section_sort++,
        child:":scope > .background-container",
        htmlAttr: "class",
        validValues: ["", "parallax"],
        inputtype: ToggleInput,
        data: {
            className: "",
            on: 'parallax',
            off: ''
        },	 
     },	 
];


let SectionOverlay = [{
        key: "section_overlay",
        inputtype: SectionInput,
        name:false,
        sort: section_sort++,
        //section: style_section,
        data: {header:"Overlay"},
	},{
        //name: "Enable",
		name: false,
        key: "overlay",
        sort: section_sort++,
        inline: true,
        //validValues: ["", "active"],
        inputtype: ToggleInput,
        data: {
            className: "",
            on: 'true',
            off: 'false'
        },
		onChange : function(node, value, input) {
			let group = document.querySelectorAll('.mb-2[data-group="overlay"]');
			let overlay = node.querySelector(":scope > .overlay");
			
			if (value == 'true') {
				group.forEach(e => e.classList.remove("d-none"));
				
				if (!overlay) {
					overlay = generateElements('<div class="overlay"></div>')[0];
					node.appendChild(overlay);
				} else {
					overlay.classList.remove("d-none");
				}
			} else {
				group.forEach(e => e.classList.add("d-none"));
				if (overlay) overlay.classList.add("d-none");
			}

			return element;
		}, 
		init: function(node) {
			let overlay = node.querySelector(":scope > .overlay");
			let group = document.querySelectorAll('.mb-2[data-group="overlay"]');

			if (overlay && overlay.offsetParent) {
				group.forEach(e => e.classList.remove("d-none"));
				return 'true';
			} else {
				group.forEach(e => e.classList.add("d-none"));
				return 'false';
			}
		}		
    },{
        name: "Color",
        key: "background-color",
        sort: section_sort++,
        htmlAttr: "style",
		child:":scope > .overlay",
		group:"overlay",
        inputtype: ColorInput
   },{
        name: "Opacity",
        key: "opacity",
        sort: section_sort++,
		htmlAttr: "style",
		inline:false,
		group:"overlay",
		child:":scope > .overlay",
        inputtype: RangeInput,
        data:{
			max: 1, //max zoom level
			min:0,
			step:0.1
       }
}];

function sectionSeparatorProperties(name, title) {
	return [{
        key: `section_${name}_separator`,
        inputtype: SectionInput,
        name:false,
        sort: section_sort++,
        //section: style_section,
        data: {header:`${title} Separator`},
	},{
        //name: "Enable",
		name: false,
        key: `${name}_separator`,
        sort: section_sort++,
        inline: true,
        inputtype: ToggleInput,
        data: {
            className: "",
            on: 'true',
            off: 'false'
        },
		onChange : function(node, value, input) {
			let group = document.querySelectorAll(`[data-group="${name}_separator"]`);
			let separator = node.querySelector(`:scope > .${name}.separator`);

			if (value == 'true') {
				group.forEach(e => e.classList.remove("d-none"));
				
				if (!separator) {
					separator = generateElements(`<div class="separator ${name}">${defaultSeparatorSvg}</div>`)[0];
					node.appendChild(separator);
				} else {
					separator.classList.remove("d-none");
				}
			} else {
				group.forEach(e => e.classList.add("d-none"));
				separator.classList.add("d-none");
			}

			return element;
		}, 
		init: function(node) {
			let group = node.querySelectorAll(`[data-group="${name}_separator"]`);
			let separator = node.querySelector(`:scope > .${name}.separator`);
			
			if (separator && separator.offsetParent) {
				group.forEach(e => e.classList.remove("d-none"));
				return 'true';
			} else {
				group.forEach(e => e.classList.add("d-none"));
				return 'false';
			}
		}		
    },{
		name: "Icon",
		key: "icon",
		sort: section_sort++,
		inline:true,
		group:`${name}_separator`,
		child:`.separator.${name} > svg`,
		inputtype: HtmlListSelectInput,
		onChange:function(element, value, input, component) {
			let newElement = generateElements(value)[0];
			let attributes = element.attributes;
			
			//keep old svg size and colors
			for (let i = 0; i < attributes.length; i++) {
				let attr = attributes[i];
				if (attr.name && attr.name != "viewBox") {
					newElement.setAttribute(attr.name, attr.value);
				}
			}
			
			element.replaceWith(newElement);
			return newElement;
		},
		data: {
			// Clear broken relative template URL to prevent 404 fetch errors
			url: "",
			clickElement:"li",
			insertElement:"svg",
			elements: 'Loading ...',
			options: [{
                value: "digital-red-panther",
                text: "Red panther"
            }]
		},
	},{
		name: "Width",
		key: "width",
		sort: section_sort++,
		htmlAttr: "width",
		group:`${name}_separator`,
		child:`.separator.${name} > svg`,
		inputtype: RangeInput,
		data:{
			max: 640,
			min:6,
			step:1
		}
   },{
		name: "Height",
		key: "height",
		sort: section_sort++,
		htmlAttr: "height",
		group:`${name}_separator`,
		child:`.separator.${name} > svg`,
		inputtype: RangeInput,
		data:{
			max: 640,
			min:6,
			step:1
		}			
   },{
		name: "Stroke width",
		key: "stroke-width",
		sort: section_sort++,
		htmlAttr: "stroke-width",
		group:`${name}_separator`,
		child:`.separator.${name} > svg`,
		inputtype: RangeInput,
		data:{
			max: 512,
			min:1,
			step:1
		}			
   },/*{
		key: "separator_svg_style_header",
		inputtype: SectionInput,
		name:false,
		group:`${name}_separator`,
		sort: section_sort++,
        //section: style_section,
		data: {header:"Svg colors"},
	},*/ {
        name: "Fill Color",
        key: "fill",
        sort: section_sort++,
        col:12,
        inline:true,
        //section: style_section,
		group:`${name}_separator`,
		child:`.separator.${name} > svg`,
		htmlAttr: "fill",
        inputtype: ColorInput,
   },{
        name: "Color",
        key: "color",
        sort: section_sort++,
        col:12,
        inline:true,
        //section: style_section,
	group:`${name}_separator`,
	child:`.separator.${name} > svg`,
	htmlAttr: "color",
        inputtype: ColorInput,
   },{
        name: "Stroke",
        key: "stroke",
        sort: section_sort++,
        col:12,
        inline:true,
        //section: style_section,
		group:`${name}_separator`,
		child:`.separator.${name} > svg`,
		htmlAttr: "color",
        inputtype: ColorInput,
  	}
];
}
