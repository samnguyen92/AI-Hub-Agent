/**
 * Align Builder HTML Components - HR, Labels, Text & Select Form Elements
 * @package Align
 */

Vvveb.Components.extend("_base", "html/hr", {
    image: "icons/hr.svg",
    nodes: ["hr"],
    name: "Horizontal Rule",
    html: '<hr class="border-primary border-4 opacity-25">',
	properties:[{
        name: "Type",
        key: "border-color",
		htmlAttr: "class",
        validValues: ["border-primary", "border-secondary", "border-success", "border-danger", "border-warning", "border-info", "border-light", "border-dark", "border-white"],
        inputtype: SelectInput,
        data: {
            options: [{
				value: "Default",
				text: ""
			}, {
				value: "border-primary",
				text: "Primary"
			}, {
				value: "border-secondary",
				text: "Secondary"
			}, {
				value: "border-success",
				text: "Success"
			}, {
				value: "border-danger",
				text: "Danger"
			}, {
				value: "border-warning",
				text: "Warning"
			}, {
				value: "border-info",
				text: "Info"
			}, {
				value: "border-light",
				text: "Light"
			}, {
				value: "border-dark",
				text: "Dark"
			}, {
				value: "border-white",
				text: "White"
			}]
        }
    },{
        name: "Border",
        key: "border-size",
		htmlAttr: "class",
        validValues: ["border-1", "border-2", "border-3", "border-4", "border-5"],
        inputtype: SelectInput,
        data: {
            options: [{
				value: "Default",
				text: ""
			}, {
				value: "border-1",
				text: "Size 1"
			}, {
				value: "border-2",
				text: "Size 2"
			}, {
				value: "border-3",
				text: "Size 3"
			}, {
				value: "border-4",
				text: "Size 4"
			}, {
				value: "border-5",
				text: "Size 5"
			}]
        }
    },{
        name: "Opacity",
        key: "opacity",
		htmlAttr: "class",
        validValues: ["opacity-25", "opacity-50", "opacity-75", "opacity-100"],
        inputtype: SelectInput,
        data: {
            options: [{
				value: "Default",
				text: ""
			}, {
				value: "opacity-25",
				text: "Opacity 25%"
			}, {
				value: "opacity-50",
				text: "Opacity 50%"
			}, {
				value: "opacity-75",
				text: "Opacity 75%"
			}, {
				value: "opacity-100",
				text: "Opacity 100%"
			}]
        }
    }]
});

Vvveb.Components.extend("_base", "html/label", {
    name: "Label",
    nodes: ["label"],
    html: '<label for="">Label</label>',
    properties: [{
        name: "For id",
        htmlAttr: "for",
        key: "for",
        inputtype: TextInput
    }]
});


Vvveb.Components.extend("_base", "html/textinput", {
    name: "Input",
	nodes: ["input"],
	//attributes: {"type":"text"},
    image: "icons/text_input.svg",
    html: '<input type="text" class="form-control">',
    properties: [{
        name: "Name",
        key: "name",
        htmlAttr: "name",
        inputtype: TextInput
    }, {
        name: "Value",
        key: "value",
        htmlAttr: "value",
        inputtype: TextInput
    }, {
        name: "Type",
        key: "type",
        htmlAttr: "type",
		inputtype: SelectInput,
        data: {
            options: [{
                value: "text",
                text: "text"
            }, {
                value: "button",
                text: "button"
            }, {
                value: "checkbox",
                text: "checkbox"
            }, {
                value: "color",
                text: "color"
            }, {
                value: "date",
                text: "date"
            }, {
                value: "datetime-local",
                text: "datetime-local"
            }, {
                value: "email",
                text: "email"
            }, {
                value: "file",
                text: "file"
            }, {
                value: "hidden",
                text: "hidden"
            }, {
                value: "image",
                text: "image"
            }, {
                value: "month",
                text: "month"
            }, {
                value: "number",
                text: "number"
            }, {
                value: "password",
                text: "password"
            }, {
                value: "radio",
                text: "radio"
            }, {
                value: "range",
                text: "range"
            }, {
                value: "reset",
                text: "reset"
            }, {
                value: "search",
                text: "search"
            }, {
                value: "submit",
                text: "submit"
            }, {
                value: "tel",
                text: "tel"
            }, {
                value: "text",
                text: "text"
            }, {
                value: "time",
                text: "time"
            }, {
                value: "url",
                text: "url"
            }, {
                value: "week",
                text: "week"
            }]
        }
    }, {
        name: "Placeholder",
        key: "placeholder",
        htmlAttr: "placeholder",
        inputtype: TextInput
    }, {
        name: "Disabled",
        key: "disabled",
        htmlAttr: "disabled",
		col:6,
		inline:true,
        inputtype: CheckboxInput,
	},{
        name: "Required",
        key: "required",
        htmlAttr: "required",
		col:6,
		inline:true,
        inputtype: CheckboxInput,
    }]
});

Vvveb.Components.extend("_base", "html/selectinput", {
	nodes: ["select"],
    name: "Select Input",
    image: "icons/select_input.svg",
    html: '<select class="form-control"><option value="value1">Text 1</option><option value="value2">Text 2</option><option value="value3">Text 3</option></select>',

	beforeInit: function (node) {
		properties = [];
		let i = 0;
		
		node.querySelectorAll('option').forEach(el => {
			data = {"value": el.value, "text": el.text};
			i++;
			properties.push({
				name: "Option " + i,
				key: "option" + i,
				//index: i - 1,
				optionNode: el,
				inline:true,
				inputtype: TextValueInput,
				data: data,
				onChange: function(node, value, input) {
					
					option = this.optionNode;
					
					//if remove button is clicked remove option and render row properties
					if (input.nodeName == 'BUTTON') {
						option.remove();
						Vvveb.Components.render("html/selectinput");
						return node;
					}

					if (input.name == "value") option.setAttribute("value", value); 
					else if (input.name == "text") option.textContent = value;
					return node;
				},	
			});
		});

		//remove all option properties
		this.properties = this.properties.filter(function(item) {
			return item.key.indexOf("option") === -1;
		});
		
		//add remaining properties to generated column properties
		this.properties =  properties.concat(this.properties);
		
		return node;
	},
    
    properties: [{
        name: "Name",
        key: "name",
        htmlAttr: "name",
        inputtype: TextInput
    }, {
        name: "Disabled",
        key: "disabled",
        htmlAttr: "disabled",
		col:6,
		inline:true,
        inputtype: CheckboxInput,
	},{
        name: "Required",
        key: "required",
        htmlAttr: "required",
		col:6,
		inline:true,
        inputtype: CheckboxInput,
    }, {
        name: "Option",
        key: "option1",
        inputtype: TextValueInput
	}, {
        name: "Option",
        key: "option2",
        inputtype: TextValueInput
	}, {
        name: "",
        key: "addChild",
        inputtype: ButtonInput,
        data: {text:"Add option", icon:"la-plus"},
        onChange: function(node)
        {
			 node.append(generateElements('<option value="value">Text</option>')[0]);
			 
			 //render component properties again to include the new column inputs
			 Vvveb.Components.render("html/selectinput");
			 
			 return node;
		}
	}]
});

Vvveb.Components.extend("_base", "html/textareainput", {
	nodes: ["textarea"],
    name: "Text Area",
    image: "icons/text_area.svg",
    html: '<textarea class="form-control"></textarea>',
	properties: [{
        name: "Name",
        key: "name",
        htmlAttr: "name",
        inputtype: TextInput
    }, {
        name: "Value",
        key: "value",
        htmlAttr: "value",
        inputtype: TextInput
    }, {
        name: "Placeholder",
        key: "placeholder",
        htmlAttr: "placeholder",
        inputtype: TextInput
    }, {
        name: "Columns",
        key: "cols",
        htmlAttr: "cols",
        inputtype: NumberInput
    }, {
        name: "Rows",
        key: "rows",
        htmlAttr: "rows",
        inputtype: NumberInput
    }, {
        name: "Disabled",
        key: "disabled",
        htmlAttr: "disabled",
		col:6,
		inline:true,
        inputtype: CheckboxInput,
	},{
        name: "Required",
        key: "required",
        htmlAttr: "required",
		col:6,
		inline:true,
        inputtype: CheckboxInput,
    }]	
});
