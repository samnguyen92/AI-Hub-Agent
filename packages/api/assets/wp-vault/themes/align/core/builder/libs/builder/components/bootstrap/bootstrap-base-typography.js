/**
 * Align Builder Bootstrap - Base Typography & Color Properties
 * @package Align
 */

Vvveb.Components.add("_base", {
    name: "Element",
	properties: [{
        key: "element_header",
        inputtype: SectionInput,
        name:false,
        sort:base_sort++,
        data: {header:"General"},
    }, {
        name: "Id",
        key: "id",
        htmlAttr: "id",
        sort: base_sort++,
        inline:true,
        col:6,
        inputtype: TextInput
    }, {
        name: "Class",
        key: "class",
        htmlAttr: "class",
        sort: base_sort++,
        inline:true,
        col:6,
        inputtype: TextInput
    }
   ]
});    

//display
Vvveb.Components.extend("_base", "_base", {
	 properties: [
     {
        key: "display_header",
        inputtype: SectionInput,
        name:false,
        sort: base_sort++,
		section: style_section,
        data: {header:"Display"},
    }, {
        name: "Display",
        key: "display",
        htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:true,
        inputtype: SelectInput,
        validValues: ["block", "inline", "inline-block", "none"],
        data: {
            options: [{
                value: "block",
                text: "Block"
            }, {
                value: "inline",
                text: "Inline"
            }, {
                value: "inline-block",
                text: "Inline Block"
            }, {
                value: "none",
                text: "none"
            }]
        }
    }, {
        name: "Position",
        key: "position",
        htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:true,
        inputtype: SelectInput,
        validValues: ["static", "fixed", "relative", "absolute"],
        data: {
            options: [{
                value: "static",
                text: "Static"
            }, {
                value: "fixed",
                text: "Fixed"
            }, {
                value: "relative",
                text: "Relative"
            }, {
                value: "absolute",
                text: "Absolute"
            }]
        }
    }, {
        name: "Top",
        key: "top",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:true,
        parent:"",
        inputtype: CssUnitInput
	}, {
        name: "Left",
        key: "left",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:true,
        parent:"",
        inputtype: CssUnitInput
    }, {
        name: "Bottom",
        key: "bottom",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:true,
        parent:"",
        inputtype: CssUnitInput
	}, {
        name: "Right",
        key: "right",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:true,
        parent:"",
        inputtype: CssUnitInput
    },{
        name: "Float",
        key: "float",
        htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:12,
        inline:true,
        inputtype: RadioButtonInput,
        data: {
			extraclass:"btn-group-sm btn-group-fullwidth",
            options: [{
                value: "none",
                icon:"la la-times",
                //text: "None",
                title: "None",
                checked:true,
            }, {
                value: "left",
                //text: "Left",
                title: "Left",
                icon:"la la-align-left",
                checked:false,
            }, {
                value: "right",
                //text: "Right",
                title: "Right",
                icon:"la la-align-right",
                checked:false,
            }],
         }
	}, {
        name: "Opacity",
        key: "opacity",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:12,
		inline:true,
        parent:"",
        inputtype: RangeInput,
        data:{
			max: 1, //max zoom level
			min:0,
			step:0.1
       },
	}, {
        name: "Background Color",
        key: "background-color",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:true,
		htmlAttr: "style",
        inputtype: ColorInput,
	}, {
        name: "Text Color",
        key: "color",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:true,
		htmlAttr: "style",
        inputtype: ColorInput,
  	}]
});    

//Typography
Vvveb.Components.extend("_base", "_base", {
	 properties: [
     {
		key: "typography_header",
		inputtype: SectionInput,
		name:false,
		sort: base_sort++,
		section: style_section,
		data: {header:"Typography"},
 
	}, {
        name: "Font size",
        key: "font-size",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:true,
        inputtype: CssUnitInput
	}, {
        name: "Font weight",
        key: "font-weight",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:true,
        inputtype: SelectInput,
        data: {
			options: [{
				value: "",
				text: "Default"
			}, {	
				value: "100",
				text: "Thin"
			}, {
				value: "200",
				text: "Extra-Light"
			}, {
				value: "300",
				text: "Light"
			}, {
				value: "400",
				text: "Normal"
			}, {
				value: "500",
				text: "Medium"
			}, {
				value: "600",
				text: "Semi-Bold"
			}, {
				value: "700",
				text: "Bold"
			}, {
				value: "800",
				text: "Extra-Bold"
			}, {
				value: "900",
				text: "Ultra-Bold"
			}],
		}
   }, {
        name: "Font family",
        key: "font-family",
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
			}, {
				value: "Arial, Helvetica, sans-serif",
				text: "Arial"
			}, {
				value: '\'Lucida Sans Unicode\', \'Lucida Grande\', sans-serif',
				text: 'Lucida Grande'
			}, {
				value: '\'Palatino Linotype\', \'Book Antiqua\', Palatino, serif',
				text: 'Palatino Linotype'
			}, {
				value: '\'Times New Roman\', Times, serif',
				text: 'Times New Roman'
			}, {
				value: "Georgia, serif",
				text: "Georgia, serif"
			}, {
				value: "Tahoma, Geneva, sans-serif",
				text: "Tahoma"
			}, {
				value: '\'Comic Sans MS\', cursive, sans-serif',
				text: 'Comic Sans'
			}, {
				value: 'Verdana, Geneva, sans-serif',
				text: 'Verdana'
			}, {
				value: 'Impact, Charcoal, sans-serif',
				text: 'Impact'
			}, {
				value: '\'Arial Black\', Gadget, sans-serif',
				text: 'Arial Black'
			}, {
				value: '\'Trebuchet MS\', Helvetica, sans-serif',
				text: 'Trebuchet'
			}, {
				value: '\'Courier New\', Courier, monospace',
				text: 'Courier New'
			}, {
				value: '\'Brush Script MT\', sans-serif',
				text: 'Brush Script'
			}]
		}
	}, {
        name: "Text align",
        key: "text-align",
        htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:12,
        inline:true,
        inputtype: RadioButtonInput,
        data: {
			extraclass:"btn-group-sm btn-group-fullwidth",
            options: [{
                value: "",
                icon:"la la-times",
                //text: "None",
                title: "None",
                checked:true,
            }, {
                value: "left",
                //text: "Left",
                title: "Left",
                icon:"la la-align-left",
                checked:false,
            }, {
                value: "center",
                //text: "Center",
                title: "Center",
                icon:"la la-align-center",
                checked:false,
            }, {
                value: "right",
                //text: "Right",
                title: "Right",
                icon:"la la-align-right",
                checked:false,
            }, {
                value: "justify",
                //text: "justify",
                title: "Justify",
                icon:"la la-align-justify",
                checked:false,
            }],
        },
	}, {
        name: "Line height",
        key: "line-height",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:true,
        inputtype: CssUnitInput
	}, {
        name: "Letter spacing",
        key: "letter-spacing",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:true,
        inputtype: CssUnitInput
	}, {
        name: "Text decoration",
        key: "text-decoration-line",
        htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:12,
        inline:true,
        inputtype: RadioButtonInput,
        data: {
			extraclass:"btn-group-sm btn-group-fullwidth",
            options: [{
                value: "none",
                icon:"la la-times",
                //text: "None",
                title: "None",
                checked:true,
            }, {
                value: "underline",
                //text: "Left",
                title: "underline",
                icon:"la la-long-arrow-alt-down",
                checked:false,
            }, {
                value: "overline",
                //text: "Right",
                title: "overline",
                icon:"la la-long-arrow-alt-up",
                checked:false,
            }, {
                value: "line-through",
                //text: "Right",
                title: "Line Through",
                icon:"la la-strikethrough",
                checked:false,
            }, {
                value: "underline overline",
                //text: "justify",
                title: "Underline Overline",
                icon:"la la-arrows-alt-v",
                checked:false,
            }],
        },
	}, {
        name: "Decoration Color",
        key: "text-decoration-color",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:true,
		htmlAttr: "style",
        inputtype: ColorInput,
	}, {
        name: "Decoration style",
        key: "text-decoration-style",
		htmlAttr: "style",
        sort: base_sort++,
		section: style_section,
        col:6,
		inline:true,
        inputtype: SelectInput,
        data: {
			options: [{
				value: "",
				text: "Default"
			}, {	
				value: "solid",
				text: "Solid"
			}, {
				value: "wavy",
				text: "Wavy"
			}, {
				value: "dotted",
				text: "Dotted"
			}, {
				value: "dashed",
				text: "Dashed"
			}, {
				value: "double",
				text: "Double"
			}],
		}
  }]
})
    
//Size
