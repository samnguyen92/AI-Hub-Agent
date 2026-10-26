/**
 * Align Builder Common Properties - Device Visibility & Bootstrap Config Inspector
 * @package Align
 */

let ComponentDeviceVisibility = {
	 properties: [{
		key: "visibility_header",
		inputtype: SectionInput,
		name:false,
		sort: base_sort++,
		section: advanced_section,
		data: {header:"Hide based on device screen width"},
	},{
        name: "Extra small devices",
        key: "hidexs",
        col:6,
		inline:true,
		sort: base_sort++,
		section: advanced_section,
        htmlAttr: "class",
        validValues: ["d-xs-none"],
        inputtype: ToggleInput,
        data: {
            on: "d-xs-none",
            off: ""
        }
	},{
        name: "Small devices",
        key: "hidesm",
        col:6,
		inline:true,
		sort: base_sort++,
		section: advanced_section,
        htmlAttr: "class",
        validValues: ["d-sm-none"],
        inputtype: ToggleInput,
        data: {
            on: "d-sm-none",
            off: ""
        }
	},{
        name: "Medium devices",
        key: "hidemd",
        col:6,
		inline:true,
		sort: base_sort++,
		section: advanced_section,
        htmlAttr: "class",
        validValues: ["d-md-none"],
        inputtype: ToggleInput,
        data: {
            on: "d-md-none",
            off: ""
        }
	},{
        name: "Large devices",
        key: "hidelg",
        col:6,
		inline:true,
		sort: base_sort++,
		section: advanced_section,
        htmlAttr: "class",
        validValues: ["d-lg-none"],
        inputtype: ToggleInput,
        data: {
            on: "d-lg-none",
            off: ""
        }
	},{
        name: "Xl devices",
        key: "hidexl",
        col:6,
		inline:true,
		sort: base_sort++,
		section: advanced_section,
        htmlAttr: "class",
        validValues: ["d-xl-none"],
        inputtype: ToggleInput,
        data: {
            on: "d-xl-none",
            off: ""
        }
	},{
        name: "Xxl devices",
        key: "hidexxl",
        col:6,
		inline:true,
		sort: base_sort++,
		section: advanced_section,
        htmlAttr: "class",
        validValues: ["d-xxl-none"],
        inputtype: ToggleInput,
        data: {
            on: "d-xxl-none",
            off: ""
        }
    }]
};

Vvveb.Components.extend("_base", "_base", ComponentDeviceVisibility);


Vvveb.Components.add("config/bootstrap", {
    name: "Bootstrap Variables",
	beforeInit: function (node) {
		properties = [];
		let i = 0;
		let j = 0;

		let cssVars = Vvveb.ColorPaletteManager.getAllCSSVariableNames(window.FrameDocument.styleSheets/*, ":root"*/);
		
		for (type in cssVars) {
			properties.push({
				key: "cssVars" + type,
				inputtype: SectionInput,
				name:type,
				sort: base_sort++,
				data: {header:type[0].toUpperCase() + type.slice(1)},
			});
			
			for (selector in cssVars[type]) {
				
				let friendlyName = selector.replaceAll(/--bs-/g,"").replaceAll("-", " ").trim();  
				friendlyName = friendlyName[0].toUpperCase() + friendlyName.slice(1);

				let value = cssVars[type][selector];
				let input;
				
				data = {selector, type:value.type, step:"any"};
				
				if (value.type == "color") {
					input = ColorInput;
				} else if (value.type == "font") {
					input = SelectInput;
					data.options = fontList;
				} else if (value.type == "dimensions") {
					input = CssUnitInput;
				}

				i++;
				properties.push({
					name: friendlyName,
					key: "cssvar" + i,
					defaultValue: value.value,
					//index: i - 1,
					columnNode: this,
					col:(value.type == "font" || value.type == "dimensions") ? 12 : 6,
					inline:(value.type == "font" || value.type == "dimensions") ? false : false,
					section: advanced_section,
					inputtype: input,
					data: data,
					onChange: function(node, value, input) {
						
						if (this.data.type == "font") {
							let option = input.options[input.selectedIndex];
							Vvveb.FontsManager.addFont(option.dataset.provider, value, node[0]);
						}

						Vvveb.StyleManager.setStyle(":root", this.data.selector, value);
						
						return node;
					},	
				});
			}
		}
		
		this.properties = properties;
		return node;
	},
	properties: [],
});
