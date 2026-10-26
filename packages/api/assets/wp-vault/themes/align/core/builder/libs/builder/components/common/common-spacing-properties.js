/**
 * Align Builder Common Properties - Spacing, Margin, Padding & Layout Controls
 * @package Align
 */

let ComponentBaseMargin = {
	 properties: [{
		key: "margins_header",
		inputtype: SectionInput,
		name:false,
		sort: base_sort++,
		section: style_section,
		data: {header:"Margin", expanded:false},
	},{
        name: "Top",
        key: "margin-top",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        inputtype: CssUnitInput
	},{
        name: "Right",
        key: "margin-right",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        inputtype: CssUnitInput
    },{
        name: "Bottom",
        key: "margin-bottom",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        inputtype: CssUnitInput
    },{
        name: "Left",
        key: "margin-left",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        inputtype: CssUnitInput
    }]
}

Vvveb.Components.extend("_base", "_base", ComponentBaseMargin);

//Padding
let ComponentBasePadding = {

	 properties: [{
		key: "paddings_header",
		inputtype: SectionInput,
		name:false,
		sort: base_sort++,
		section: style_section,
		data: {header:"Padding", expanded:false},
	},{
        name: "Top",
        key: "padding-top",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        inputtype: CssUnitInput
	},{
        name: "Right",
        key: "padding-right",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        inputtype: CssUnitInput
    },{
        name: "Bottom",
        key: "padding-bottom",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        inputtype: CssUnitInput
    },{
        name: "Left",
        key: "padding-left",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        inputtype: CssUnitInput
    }]
}

Vvveb.Components.extend("_base", "_base", ComponentBasePadding);


//Border
Vvveb.Components.extend("_base", "_base", {
	 properties: [{
		key: "border_header",
		inputtype: SectionInput,
		name:false,
		sort: base_sort++,
		section: style_section,
		data: {header:"Border", expanded:false},
	 },{        
        name: "Style",
        key: "border-style",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:12,
		inline:false,
        inputtype: SelectInput,
        data: {
			options: [{
				value: "",
				text: "Default"
			},{	
				value: "solid",
				text: "Solid"
			},{
				value: "dotted",
				text: "Dotted"
			},{
				value: "dashed",
				text: "Dashed"
			}],
		}
	},{
        name: "Width",
        key: "border-width",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        inputtype: CssUnitInput
   	},{
        name: "Color",
        key: "border-color",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
		htmlAttr: "style",
        inputtype: ColorInput,
	}]
});    



//Border radius
Vvveb.Components.extend("_base", "_base", {
	 properties: [{
		key: "border_radius_header",
		inputtype: SectionInput,
		name:false,
		sort: base_sort++,
		section: style_section,
		data: {header:"Border radius", expanded:false},
	},{
        name: "Top Left",
        key: "border-top-left-radius",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        inputtype: CssUnitInput
	},{
        name: "Top Right",
        key: "border-top-right-radius",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        inputtype: CssUnitInput
    },{
        name: "Bottom Left",
        key: "border-bottom-left-radius",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        inputtype: CssUnitInput
    },{
        name: "Bottom Right",
        key: "border-bottom-right-radius",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        inputtype: CssUnitInput
    }]
});    

//Background image
Vvveb.Components.extend("_base", "_base", {
	 properties: [{
		key: "background_image_header",
		inputtype: SectionInput,
		name:false,
		sort: base_sort++,
		section: style_section,
		data: {header:"Background Image", expanded:false},
	 },{
        name: "Image",
        key: "Image",
        sort: base_sort++,
		section: style_section,
		//htmlAttr: "style",
        inputtype: ImageInput,
        
        init: function(node) {
			function extractBgUrl(bg) {
				if (!bg || typeof bg !== 'string') return '';
				let url = '';
				let quotedMatch = bg.match(/url\(\s*(['"])(.*?)\1\s*\)/i);
				if (quotedMatch && quotedMatch[2]) {
					url = quotedMatch[2];
				} else {
					let unquotedMatch = bg.match(/url\(\s*([^\s'")]+)\s*\)/i);
					if (unquotedMatch && unquotedMatch[1]) {
						url = unquotedMatch[1];
					} else {
						url = bg;
					}
				}
				url = url.replace(/^['"]+|['"]+$/g, '').trim();
				if (!url || url === 'none' || url.startsWith('data:image') || url.includes('icons/image.svg')) {
					return '';
				}
				return url;
			}

			let bg = (node && node.style) ? node.style.backgroundImage : '';
			let image = extractBgUrl(bg);
			if (!image && node && typeof node.getAttribute === 'function') {
				image = extractBgUrl(node.getAttribute('data-bg-src'));
			}
			if (!image && node) {
				try {
					let win = (node.ownerDocument && node.ownerDocument.defaultView) || window;
					if (win && win.getComputedStyle) {
						let computedBg = win.getComputedStyle(node).backgroundImage;
						image = extractBgUrl(computedBg);
					}
				} catch (e) {
					console.warn('[Align CommonSpacing] Error reading computed backgroundImage:', e);
				}
			}
			return image || '';
        },

		onChange: function(node, value) {
			function extractBgUrl(bg) {
				if (!bg || typeof bg !== 'string') return '';
				let url = '';
				let quotedMatch = bg.match(/url\(\s*(['"])(.*?)\1\s*\)/i);
				if (quotedMatch && quotedMatch[2]) {
					url = quotedMatch[2];
				} else {
					let unquotedMatch = bg.match(/url\(\s*([^\s'")]+)\s*\)/i);
					if (unquotedMatch && unquotedMatch[1]) {
						url = unquotedMatch[1];
					} else {
						url = bg;
					}
				}
				url = url.replace(/^['"]+|['"]+$/g, '').trim();
				if (!url || url === 'none' || url.startsWith('data:image') || url.includes('icons/image.svg')) {
					return '';
				}
				return url;
			}

			let cleanVal = extractBgUrl(value);
			let styleVal = cleanVal ? 'url("' + cleanVal + '")' : '';
			if (node && node.style) {
				if (cleanVal) {
					node.style.backgroundImage = styleVal;
					if (typeof node.setAttribute === 'function') {
						node.setAttribute('data-bg-src', cleanVal);
					}
				} else {
					node.style.backgroundImage = '';
					if (typeof node.removeAttribute === 'function') {
						node.removeAttribute('data-bg-src');
					}
				}
			}

			if (window.Vvveb && window.Vvveb.StyleManager) {
				window.Vvveb.StyleManager.setStyle(node, "background-image", styleVal);
			}

			if (window.AlignBuilderModules?.WpSaveHandler?.setButtonState) {
				window.AlignBuilderModules.WpSaveHandler.setButtonState('dirty');
			}
			document.querySelectorAll("#top-panel .save-btn, .save-btn").forEach(btn => btn.removeAttribute("disabled"));

			return node;
		}        

   	},{
        name: "Repeat",
        key: "background-repeat",
        sort: base_sort++,
		section: style_section,
		htmlAttr: "style",
        inputtype: SelectInput,
        data: {
			options: [{
				value: "",
				text: "Default"
			},{	
				value: "repeat-x",
				text: "repeat-x"
			},{
				value: "repeat-y",
				text: "repeat-y"
			},{
				value: "no-repeat",
				text: "no-repeat"
			}],
		}
   	},{
        name: "Size",
        key: "background-size",
        sort: base_sort++,
		section: style_section,
		htmlAttr: "style",
        inputtype: SelectInput,
        data: {
			options: [{
				value: "",
				text: "Default"
			},{	
				value: "contain",
				text: "contain"
			},{
				value: "cover",
				text: "cover"
			}],
		}
   	},{
        name: "Position x",
        key: "background-position-x",
        sort: base_sort++,
		section: style_section,
		htmlAttr: "style",
        col:6,
		inline:false,
		inputtype: SelectInput,
        data: {
			options: [{
				value: "",
				text: "Default"
			},{	
				value: "center",
				text: "center"
			},{	
				value: "right",
				text: "right"
			},{
				value: "left",
				text: "left"
			}],
		}
   	},{
        name: "Position y",
        key: "background-position-y",
        sort: base_sort++,
		section: style_section,
		htmlAttr: "style",
        col:6,
		inline:false,
		inputtype: SelectInput,
        data: {
			options: [{
				value: "",
				text: "Default"
			},{	
				value: "center",
				text: "center"
			},{	
				value: "top",
				text: "top"
			},{
				value: "bottom",
				text: "bottom"
			}],
		}
    }]
});    


//Device visibility
