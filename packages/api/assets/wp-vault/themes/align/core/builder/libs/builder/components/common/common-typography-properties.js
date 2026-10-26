/**
 * Align Builder Common Properties - Typography Inspector Controls
 * @package Align
 */

let ComponentBaseTypography = {
	 properties: [{
		key: "typography_header",
		inputtype: SectionInput,
		name:false,
		sort: base_sort++,
		section: style_section,
		data: {header:"Typography"},
 
	},{
        name: "Font size",
        key: "font-size",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        inputtype: CssUnitInput
	},{
        name: "Font weight",
        key: "font-weight",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        inputtype: SelectInput,
        data: {
			options: [{
				value: "",
				text: "Default"
			},{	
				value: "100",
				text: "Thin"
			},{
				value: "200",
				text: "Extra-Light"
			},{
				value: "300",
				text: "Light"
			},{
				value: "400",
				text: "Normal"
			},{
				value: "500",
				text: "Medium"
			},{
				value: "600",
				text: "Semi-Bold"
			},{
				value: "700",
				text: "Bold"
			},{
				value: "800",
				text: "Extra-Bold"
			},{
				value: "900",
				text: "Ultra-Bold"
			}],
		}
    },{
        name: "Font family",
        key: "font-family",
	htmlAttr: "style",
        sort: base_sort++,
	section: style_section,
        col:12,
	inline:false,
        inputtype: SelectInput,
        data: {
			options: fontList
		}
	},{
        name: "Text align",
        key: "text-align",
        htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:12,
        inline:false,
        inputtype: RadioButtonInput,
        data: {
			extraclass:"btn-group-sm btn-group-fullwidth",
            options: [{
                value: "",
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
                value: "center",
                //text: "Center",
                title: "Center",
                icon:"la la-align-center",
                checked:false,
            },{
                value: "right",
                //text: "Right",
                title: "Right",
                icon:"la la-align-right",
                checked:false,
            },{
                value: "justify",
                //text: "justify",
                title: "Justify",
                icon:"la la-align-justify",
                checked:false,
            }],
        },
	},{
        name: "Line height",
        key: "line-height",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        inputtype: CssUnitInput
	},{
        name: "Letter spacing",
        key: "letter-spacing",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        inputtype: CssUnitInput
	},{
        name: "Text decoration",
        key: "text-decoration-line",
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
                value: "underline",
                //text: "Left",
                title: "underline",
                icon:"la la-long-arrow-alt-down",
                checked:false,
            },{
                value: "overline",
                //text: "Right",
                title: "overline",
                icon:"la la-long-arrow-alt-up",
                checked:false,
            },{
                value: "line-through",
                //text: "Right",
                title: "Line Through",
                icon:"la la-strikethrough",
                checked:false,
            },{
                value: "underline overline",
                //text: "justify",
                title: "Underline Overline",
                icon:"la la-arrows-alt-v",
                checked:false,
            }],
        },
	},{
        name: "Decoration Color",
        key: "text-decoration-color",
        sort: base_sort++,
		section: style_section,
        col:12,
		inline:true,
		htmlAttr: "style",
        inputtype: ColorInput,
	},{
        name: "Decoration style",
        key: "text-decoration-style",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:12,
		inline:true,
        inputtype: SelectInput,
        data: {
			options: [{
				value: "",
				text: "Default"
			},{	
				value: "solid",
				text: "Solid"
			},{
				value: "wavy",
				text: "Wavy"
			},{
				value: "dotted",
				text: "Dotted"
			},{
				value: "dashed",
				text: "Dashed"
			},{
				value: "double",
				text: "Double"
			}],
		}
  }]
}

Vvveb.Components.extend("_base", "_base", ComponentBaseTypography);
    
//Size
let ComponentBaseSize = {
	 properties: [{
		key: "size_header",
		inputtype: SectionInput,
		name:false,
		sort: base_sort++,
		section: style_section,
		data: {header:"Size", expanded:false},
	},{
        name: "Width",
        key: "width",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        inputtype: CssUnitInput
	},{
        name: "Height",
        key: "height",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        inputtype: CssUnitInput
	},{
        name: "Min Width",
        key: "min-width",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        inputtype: CssUnitInput
	},{
        name: "Min Height",
        key: "min-height",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        inputtype: CssUnitInput
	},{
        name: "Max Width",
        key: "max-width",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        inputtype: CssUnitInput
	},{
        name: "Max Height",
        key: "max-height",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:false,
        inputtype: CssUnitInput
    }]
}

Vvveb.Components.extend("_base", "_base", ComponentBaseSize);

//Margin
