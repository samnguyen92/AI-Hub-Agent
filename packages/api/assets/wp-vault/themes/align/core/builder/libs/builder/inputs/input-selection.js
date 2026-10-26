/**
 * Align Builder - Selection & Radio Input Controls
 * @package Align
 */

let SelectInput = { ...Input, ...{
	
    events: [
        ["change", "onChange", "select"],
	 ],
	

	setValue: function(value) {
		if (this.element[0] && value) {
			let input = this.element[0].querySelector('select');
		
			if (input) { 
				input.value = value;
			}
		}
	},
	
	init: function(data) {
		return this.render("select", data);
	},
  }
}

let IconSelectInput = { ...Input, ...{
	
    events: [
        ["change", "onChange", "select"],
	 ],
	

	setValue: function(value) {
		if (this.element[0] && value) {
			let input = this.element[0].querySelector('select');
		
			if (input) { 
				input.value = value;
			}
		}
	},
	
	init: function(data) {
		return this.render("icon-select", data);
	},
  }
}

let HtmlListSelectInput = { ...Input, ...{
	
	data:{},
	cache:{},
	
    events: [
        //["click", "onChange", "li"],
        ["change", "onListChange", "select"],
        ["keyup", "searchElement", "input.search"],
        ["click", "clearSearch", "button.clear-backspace"],
	 ],
	
	clearSearch : function(event, element, input) {
		let search = element.querySelector("input.search");
		if (search) {
			search.value = "";
			input.searchElement(event, element, input);
		}
		
		search.dispatchEvent(new KeyboardEvent("keyup", {
			bubbles: true,
			cancelable: true,
		}));
	},


	searchElement : function(event, element, input) {
		searchText = this.value;
		
		delay(() => {
			element.querySelectorAll("li").forEach((el, i) => {
				
				if (!searchText || el.title.indexOf(searchText) > -1) { 
					el.style.display = '';
				} else {
					el.style.display = 'none';
				}
			});
			
		}, 500);
	},	

	onElementClick: function(event, element, input) {
		let data = input.data;
		let svg = this.closest(data.insertElement);
		let value = svg.outerHTML ?? "<svg></svg>";
		input.value = value;
		let ret = input.onChange.call(this, event, element, input);
		
		return element;
	},
	
	onListChange: function(event, element, input) {
		if (!input.data || !input.data.url) return;
		let val = this ? this.value : '';
		let url = input.data.url.replace('{value}', val);
		let elements = element ? element.querySelector(".elements") : null;
		if (!elements) return;
		
		elements.innerHTML = `<div class="p-4"><div class="spinner-border spinner-border-sm" role="status">
		  <span class="visually-hidden">Loading...</span>
		</div> Loading...</div>`;
		
		//cache ajax requests
		if (input.cache[url] != undefined) {
			elements.innerHTML = input.cache[url];
		} else {
			fetch(url)
			.then((response) => {
				if (!response.ok) { 
					throw new Error('HTTP ' + response.status + ' (' + response.statusText + ')'); 
				}
				return response.text();
			})
			.then((text) => {
				input.cache[url] = text;
				elements.innerHTML = text;
			})
			.catch(error => {
				console.warn("[Align InputSelection] Failed to fetch list:", url, error?.message || error);
				elements.innerHTML = `<div class="p-3 text-muted small text-center">List not available</div>`;
			});
		}
	},
	
	setValue: function(value) {
		let select = this.element[0].querySelector("select");
		if (value && select) {
			select.value = value;
		}
	},
	
	init: function(data) {
		this.data = data;
		this.events.push(["click", "onElementClick", data.clickElement]);
		let template = this.render("html-list-select", data);
		let select = template.querySelector("select");
		this.onListChange.call(select, new Event('change'), template, this);
		return template;
	},
  }
}

let RadioInput = { ...Input, ...{

	events: [
        ["change", "onChange", "input"],
	 ],

	setValue: function(value) {
		if (this.element[0] && value) {
			let input = this.element[0].querySelector('input');
		
			if (input) { 
				if (value == input.value) {
					input.setAttribute("checked", "true");
					input.checked = true;
				} else {
					input.checked = false;
					input.removeAttribute("checked");
				}
				
			}
		}
	},
	
	init: function(data) {
		return this.render("radioinput", data);
	},
  }
}

let RadioButtonInput = { ...RadioInput, ...{

	setValue: function(value) {
		if (this.element[0] && value) {
			let inputs = this.element[0].querySelectorAll('input');
			inputs.forEach((el, i) => {
				if (value == el.value) {
					selected = el;
				} else {
					el.checked = false;
					el.removeAttribute("checked");
				}		
			});

			selected.checked = true;
			selected.setAttribute("checked", "checked");
		}
	},

	init: function(data) {
		return this.render("radiobuttoninput", data);
	},
  }
}
