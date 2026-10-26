/**
 * Align Builder - Advanced Controls (Color, Tags, Autocomplete, List, Notice)
 * @package Align
 */

let ColorInput = { ...Input, ...{

	 //html5 color input only supports setting values as hex colors even if the picker returns only rgb
	 rgb2hex: function(value) {
		 if (value) {
			 value = value.trim();
			 
			 if (rgb = value.match(/^rgba?[\s+]?\([\s+]?(\d+)[\s+]?,[\s+]?(\d+)[\s+]?,[\s+]?(\d+)[\s+]?/i)) {
				 
				 return (rgb && rgb.length === 4) ? "#" +
				  ("0" + parseInt(rgb[1],10).toString(16)).slice(-2) +
				  ("0" + parseInt(rgb[2],10).toString(16)).slice(-2) +
				  ("0" + parseInt(rgb[3],10).toString(16)).slice(-2) : rgb;
			 }
		}
		 
		 return value;
	},

    events: [
        ["change", "onChange", "input"],
	 ],

	setValue: function(value) {
		if (this.element[0] && value) {
			let input = this.element[0].querySelector('input');
		
			if (input) { 
				input.value = this.rgb2hex(value);
			}
		}
	},
	
	init: function(data) {
		//if no palette provided use default
		if (!data.palette) {
			data.palette = Vvveb.ColorPalette.getAll();
		}
		
		return this.render("colorinput", data);
	},
  }
}

let ButtonInput = { ...Input, ...{

    events: [
        ["click", "onChange", "button" /*'select'*/],
	 ],
	

	setValue: function(value) {
		if (this.element[0] && value) {
			let input = this.element[0].querySelector('button');
		
			if (input) { 
				input.value = value;
			}
		}		
	},
	
	init: function(data) {
		return this.render("button", data);
	},
  }
}

let SectionInput = { ...Input, ...{

    events: [
        //["click", "onChange", "button" /*'select'*/],
	 ],
	

	setValue: function(value) {
		return false;
	},
	
	init: function(data) {
		return this.render("sectioninput", data);
	},
  }
}

let ListInput = { ...Input, ...{
	
    events: [
        ["change", "onChange", "select"],
        ["click", "remove", ".delete-btn"],
        ["click", "add", ".btn-new"],
        ["click", "select", ".section-item"],
	 ],
	

	remove: function(event, node, input) {
		let sectionItem = this.closest(".section-item");
		let index = [...sectionItem.parentNode.children].indexOf(sectionItem);//sectionItem.index();
		let data = input.data;
		
		if (data.removeElement) {
			let container = input.node;
			if (data.container) {
				container.querySelector(data.container);
			}
			container.querySelector(data.selector + ":nth-child(" + (index + 1) + ")").remove();
		}
		sectionItem.remove();
		
		event.action = "remove";
		event.index = index;
		input.onChange(event, node, input, this);
		event.preventDefault();
		return false;
	},

	add: function(event, node, input) {
		let newElement = input.data.newElement ?? false;
		if (newElement) {
			let container = input.node;
			if (data.container) {
				container.querySelector(data.container);
			}			
			container.append(generateElements(newElement)[0]);
		}
		
		event.action = "add";
		input.onChange(event, node, input, this);
		return false;
	},	
	
	select: function(event, node, input) {
		let sectionItem = this.closest(".section-item");
		if (sectionItem.parentNode) {
			let index = [...sectionItem.parentNode.children].indexOf(sectionItem);//sectionItem.index();
			
			event.action = "select";
			event.index = index;
			input.onChange(event, node, input, this);
		}
		return false;
	},

	setValue: function(value) {
	},

	init: function(data, node) {
		this.component = data.component;
		this.selector = data.selector;
		this.node = node;

		let elements = this.node.querySelectorAll(data.container + " " + this.selector);
		let options = [];
		
		elements.forEach(function (e, i) {
			let element = e;
			if (data.nameElement) {
				element = element.querySelector(data.nameElement);
			}
			let name = (data.name = "text" ? element.textContent.substr(0, 15) : element.id);
			options.push({
				name: name,
				type: (data.prefix ?? "") + (i + 1) + (data.suffix ?? ""),
			});
		});

		data.options = options;
		data.elements = elements;
		this.data = data;

		let list = this.render("listinput", data);
		let items = list.querySelectorAll(".section-item");
		let i = 0;
		for (const item of items) {
			const container = item.querySelector(".tree") ?? item;
			let properties = {};
			if (data.elementProperties) {
				for (let prop in data.elementProperties) {
					properties[prop] = Object.assign({}, data.elementProperties[prop]);
					properties[prop].element = elements[i];
				}
			}
			
			Vvveb.Components.renderProperties(null, properties, null, container);
			i++;
		}

		return list;
	},
  }
}

let AutocompleteInput = { ...Input, ...{

    events: [
        ["autocomplete.change", "onAutocompleteChange", "input"],
        ["input", "onAutocompleteChange", "input"],
	 ],

	onAutocompleteChange: function(event, node, input) {
		input.value = event.detail.value;
		return input.onChange.call(this, event, node, input);
	},

	setValue: function(value) {
		if (this.element && value) {
			let input = this.element.querySelectorAll('input').forEach(e => e.value = value);
		}
	},	

	init: function(data) {
		
		this.element = this.render("textinput", data);
		
		let autocomplete = new _AutocompleteInput(this.element.querySelector("input"), data);
		
		return this.element;
	}
  }
}

let AutocompleteList = { ...Input, ...{

    events: [
        ["autocompletelist.change", "onAutocompleteChange", "input"],
	 ],

	onAutocompleteChange: function(event, node, input) {
		input.value = event.detail;
		return input.onChange.call(this, event, node, input);
	},

	setValue: function(value) {
		if (this.element && value) {
			let input = this.element.querySelectorAll('input').forEach(e => e.value = value);
		}	
	},

	init: function(data) {
		
		this.element = this.render("textinput", data);
		
		let autocomplete = new _AutocompleteList(this.element.querySelector("input"), data);
		
		return this.element;
	}
  }
}

let TagsInput = { ...Input, ...{

    events: [
        ["tagsinput.change", "onTagsInputChange", "input"],
	 ],

	onTagsInputChange: function(event, node, input) {
		let tags = "";
		let detail = event.detail && event.detail[0];
		if (Array.isArray(detail)) {
			tags = detail.filter(Boolean).join(" ");
		} else if (typeof detail === "object" && detail !== null) {
			tags = Object.keys(detail).filter(Boolean).join(" ");
		} else if (typeof detail === "string") {
			tags = detail;
		}
		input.value = tags;
		return input.onChange.call(this, event, node, input);
	},

	setValue: function(value) {
		const val = (value !== undefined && value !== null) ? value : "";
		if (this.autocomplete) {
			if (typeof this.autocomplete.setValue === "function") {
				this.autocomplete.setValue(val);
			} else if (this.autocomplete.tagsInput && typeof this.autocomplete.tagsInput.setValue === "function") {
				this.autocomplete.tagsInput.setValue(val);
			}
		}
	},

	init: function(data) {
		this.element = this.render("tagsinput", data);
		this.autocomplete = new _TagsInput(this.element.querySelector("input"), data);
		
		return this.element;
	}
  }
}


let NoticeInput = { ...Input, ...{

    events: [
	 ],
	
	init: function(data) {
		return this.render("noticeinput", data);
	},
  }
}
