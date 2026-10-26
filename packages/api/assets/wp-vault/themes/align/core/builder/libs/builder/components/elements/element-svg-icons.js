/**
 * Align Builder Elements Components - SVG Images, Elements & Font Icons
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

Vvveb.ComponentsGroup['Elements'] = [
/*sections */
"elements/font-icon",
"elements/carousel", 
"elements/gallery",
"elements/slider",
"elements/tabs",
"elements/accordion",
"elements/flip-box",
//"elements/counter",
"elements/svg-image",
"elements/figure",
//"elements/testimonial",
"elements/social-icons",
//"elements/icon-list",
//"elements/divider",
//"elements/separator",
//"elements/image-box",
//"elements/icon-box",
//"elements/animated-headline",
//"elements/price-table",
//"elements/price-list",
//"elements/reviews",
"elements/code",
"elements/image-compare",
//"elements/back-to-top",
//"elements/blob",
//"elements/image-shape",
//"elements/image-shape",
//"elements/rating",
];

Vvveb.Components.extend("_base","elements/figure", {
    nodes: ["figure"],
	name: "Figure",
    image: "icons/image.svg",
    resizable:true,
    html: `<figure>
		  <img src="https://picsum.photos/200/300" alt="Trulli">
		  <figcaption>Fig.1 - Trulli, Puglia, Italy.</figcaption>
		  <div class="border"></div>
		</figure>`,
		
	stylesheets:[{
			//the css is added in head when the element is added to page
			'src': Vvveb.baseUrl + 'css/figure.css',
			//the css is removed on save if none of the figure elements are present in the page
			'mustHaveElement':"figure",
		},
	],
	/*
	javascripts:[
		{
			'src': Vvveb.baseUrl + 'css/figure.js',
			//the js is removed on save if none of the figure elements are present in the page
			'mustHaveElement':"figure",
		}
	],*/	
    resizable:true,//show select box resize handlers
    
    properties: [{
        name: "Image",
        key: "src",
        child:"img",
        htmlAttr: "src",
        inputtype: ImageInput
    },{
        name: "Width",
        key: "width",
        child:"img",
        htmlAttr: "width",
        inputtype: CssUnitInput
    },{
        name: "Height",
        key: "height",
        child:"img",
        htmlAttr: "height",
        inputtype: CssUnitInput
    },{
        name: "Alt",
        key: "alt",
        child:"img",
        htmlAttr: "alt",
        inputtype: TextInput
    },{
        name: "Featured image",
        key: "featured_image",
        defaultValue: false,
        child:"img",
        inputtype: CheckboxInput,
        data: {
            className: "form-switch"
        },
        init: function(node) {
            let img = node.querySelector("img") || node;
            let loading = (img.getAttribute("loading") || "").toLowerCase();
            let isNoLazy = img.classList.contains("no-lazy") || img.classList.contains("ignorelazyload");
            return loading === "eager" || img.getAttribute("fetchpriority") === "high" || isNoLazy;
        },
        onChange: function(node, value, input) {
            let img = node.querySelector("img") || node;
            if (value) {
                img.setAttribute("loading", "eager");
                img.setAttribute("fetchpriority", "high");
                img.setAttribute("data-align-preload", "1");
                img.classList.add("no-lazy");
                img.classList.remove("lazy");
                let dataSrc = img.getAttribute("data-src");
                if (dataSrc && !dataSrc.startsWith("data:image")) {
                    img.setAttribute("src", dataSrc);
                    img.removeAttribute("data-src");
                }
            } else {
                img.removeAttribute("loading");
                img.removeAttribute("fetchpriority");
                img.removeAttribute("data-align-preload");
                img.classList.remove("no-lazy");
            }
            return node;
        }
    },{
        name: "Caption",
        key: "caption",
        child:"figcaption",
        htmlAttr: "innerHTML",
        inputtype: TextareaInput
    }]    
});



//Icon
Vvveb.Components.extend("_base","elements/font-icon", {
    classes: ["la", "lab"],
    name: "Font Icon",
    image: "icons/star.svg",
    html: `<i class="la la-star la-2x"></i>`,
    properties: [{
        name: "Icon class",
        key: "icon",
        inline: true,
        htmlAttr: "class",
        inputtype: TextInput
    },{
        name: "Size",
        key: "type",
        htmlAttr: "class",
        inputtype: SelectInput,
        validValues: ["","la-lg", "la-2x"],
        data: {
            options: [{
                value: "",
                text: "Normal"
            },{
                value: "la-lg",
                text: "Large"
            },{
                value: "la-2x",
                text: "2x"
            }]
        }
    }]
});   

Vvveb.Components.extend("_base","elements/svg-image", {
    nodes: ["svg"],
    name: "Svg Image",
    image: "icons/star.svg",
    html: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="64" height="64">
		<path d="M 30.335938 12.546875 L 20.164063 11.472656 L 16 2.132813 L 11.835938 11.472656 L 1.664063 12.546875 L 9.261719 19.394531 L 7.140625 29.398438 L 16 24.289063 L 24.859375 29.398438 L 22.738281 19.394531 Z"/>
    </svg>`,
    properties: [{
        name: "Svg",
        key: "svg",
        htmlAttr: "innerHTML",
        inputtype: SvgInput
    },{
        name: "Width",
        key: "width",
        htmlAttr: "width",
        inputtype: RangeInput,
        data:{
		max: 640,
		min:6,
		step:1
        }
   },{
        name: "Height",
        key: "height",
        htmlAttr: "height",
        inputtype: RangeInput,
        data:{
		max: 640,
		min:6,
		step:1
        }			
   },{
        name: "Stroke width",
        key: "stroke-width",
        htmlAttr: "stroke-width",
        inputtype: RangeInput,
        data:{
			max: 512,
			min:1,
			step:1
        }			
   }, {
        name: "Code",
        key: "code",
		inline:true,
        htmlAttr: "outerHTML",
        inputtype: TextareaInput
   }, {
        key: "svg_style_header",
        inputtype: SectionInput,
        name:false,
        section: style_section,
        data: {header:"Svg colors"},
   },{
        name: "Fill",
        key: "fill",
        col:4,
        inline:true,
        section: style_section,
        htmlAttr: "fill",
        inputtype: ColorInput,
   },{
        name: "Color",
        key: "color",
        col:4,
        inline:true,
        section: style_section,
        htmlAttr: "color",
        inputtype: ColorInput,
   },{
        name: "Stroke",
        key: "Stroke",
        col:4,
        inline:true,
        section: style_section,
        htmlAttr: "stroke",
        inputtype: ColorInput,
   }]
});   



Vvveb.Components.add("elements/svg-element", {
    nodes: ["path", "line", "polyline", "polygon", "rect", "circle", "ellipse", "g"],
    name: "Svg element",
    image: "icons/star.svg",
    html: ``,
    properties: [{
        name: "Fill Color",
        key: "fill",
        //sort: base_sort++,
        col:4,
        inline:true,
		section: style_section,
		htmlAttr: "fill",
        inputtype: ColorInput,
   },{
        name: "Color",
        key: "color",
        //sort: base_sort++,
        col:4,
        inline:true,
		section: style_section,
		htmlAttr: "color",
        inputtype: ColorInput,
   },{
        name: "Stroke",
        key: "Stroke",
        //sort: base_sort++,
        col:4,
        inline:true,
		section: style_section,
		htmlAttr: "color",
        inputtype: ColorInput,
  	},{
  		name: "Stroke width",
		key: "stroke-width",
		htmlAttr: "stroke-width",
		inputtype: RangeInput,
		data:{
			max: 512,
			min:1,
			step:1
		}			
	}]
});  

//Gallery
