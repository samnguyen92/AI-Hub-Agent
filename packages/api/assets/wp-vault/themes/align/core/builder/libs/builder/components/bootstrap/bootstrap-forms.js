/**
 * Align Builder Bootstrap - Form Controls & Inputs
 * @package Align
 */

Vvveb.Components.extend("_base", "html/form", {
    nodes: ["form"],
    image: "icons/form.svg",
    name: "Form",
    html: '<form><div class="mb-3"><label>Text</label><input type="text" class="form-control"></div></div></form>',
    properties: [{
        name: "Style",
        key: "style",
        htmlAttr: "class",
        validValues: ["", "form-search", "form-inline", "form-horizontal"],
        inputtype: SelectInput,
        data: {
            options: [{
                value: "",
                text: "Default"
            }, {
                value: "form-search",
                text: "Search"
            }, {
                value: "form-inline",
                text: "Inline"
            }, {
                value: "form-horizontal",
                text: "Horizontal"
            }]
        }
    }, {
        name: "Action",
        key: "action",
        htmlAttr: "action",
        inputtype: TextInput
    }, {
        name: "Method",
        key: "method",
        htmlAttr: "method",
        inputtype: TextInput
    }]
});

Vvveb.Components.extend("_base", "html/textinput", {
    name: "Input",
	nodes: ["input"],
	//attributes: {"type":"text"},
    image: "icons/text_input.svg",
    html: '<div class="mb-3"><label>Text</label><input type="text" class="form-control"></div></div>',
    properties: [{
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
        inputtype: CheckboxInput,
	},{
        name: "Required",
        key: "required",
        htmlAttr: "required",
		col:6,
        inputtype: CheckboxInput,
	}]
});

Vvveb.Components.extend("_base", "html/selectinput", {
	nodes: ["select"],
    name: "Select Input",
    image: "icons/select_input.svg",
    html: '<div class="mb-3"><label>Choose an option </label><select class="form-control"><option value="value1">Text 1</option><option value="value2">Text 2</option><option value="value3">Text 3</option></select></div>',

	beforeInit: function (node)
	{
		properties = [];
		var i = 0;
		
		if (node && node.querySelectorAll) {
			node.querySelectorAll('option').forEach(function(opt) {
				let data = {"value": opt.value, "text": opt.text};
				
				i++;
				properties.push({
					name: "Option " + i,
					key: "option" + i,
					optionNode: opt,
					inputtype: TextValueInput,
					data: data,
					onChange: function(node, value, input) {
						let option = this.optionNode;
						if (!option) return node;
						
						//if remove button is clicked remove option and render row properties
						if (input.nodeName == 'BUTTON')
						{
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
		}
		
		//remove all option properties
		this.properties = this.properties.filter(function(item) {
			return item.key.indexOf("option") === -1;
		});
		
		//add remaining properties to generated column properties
		properties.push(this.properties[0]);
		
		this.properties = properties;
		return node;
	},
    
    properties: [{
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
			 if (node && node.append) {
			 	node.append(generateElements('<option value="value">Text</option>')[0]);
			 }
			 
			 //render component properties again to include the new column inputs
			 Vvveb.Components.render("html/selectinput");
			 
			 return node;
		}
	}]
});
Vvveb.Components.extend("_base", "html/textareainput", {
    name: "Text Area",
    image: "icons/text_area.svg",
    html: '<div class="mb-3"><label>Your response:</label><textarea class="form-control"></textarea></div>'
});
Vvveb.Components.extend("_base", "html/radiobutton", {
    name: "Radio Button",
	attributes: {"type":"radio"},
    image: "icons/radio.svg",
    html: '<label class="radio"><input type="radio"> Radio</label>',
    properties: [{
        name: "Name",
        key: "name",
        htmlAttr: "name",
        inputtype: TextInput
    }]
});
Vvveb.Components.extend("_base", "html/checkbox", {
    name: "Checkbox",
    attributes: {"type":"checkbox"},
    image: "icons/checkbox.svg",
    html: '<label class="checkbox"><input type="checkbox"> Checkbox</label>',
    properties: [{
        name: "Name",
        key: "name",
        htmlAttr: "name",
        inputtype: TextInput
    }]
});
Vvveb.Components.extend("_base", "html/fileinput", {
    name: "Input group",
	attributes: {"type":"file"},
    image: "icons/text_input.svg",
    html: '<div class="mb-3">\
			  <input type="file" class="form-control">\
			</div>'
});
