/**
 * Align Builder - Base Inspector Input Controller
 * @package Align
 */

let Input = {
	
	init: function(name) {
	},


	onChange: function(event, node, input) {
		if (event && event.target) {
			const e = new CustomEvent('propertyChange', { detail: {value : input.value ?? this.value, input: this, origEvent:event} });
			event.currentTarget.dispatchEvent(e);
		}
	},

	renderTemplate: function(name, data) {
		return tmpl("vvveb-input-" + name, data);
	},

	setValue: function(value) {
		let el = this.element && (this.element[0] || this.element);
		if (el && el.querySelector) {
			let input = el.querySelector('input');
			if (input) { 
				input.value = (value !== undefined && value !== null) ? value : "";
			}
		}
	},
	
	render: function(name, data) {
		let html = this.renderTemplate(name, data);
		this.element = generateElements(html);
		
		//bind events
		if (this.events)
		for (let i in this.events) {
			ev = this.events[i][0];
			fun = this[ this.events[i][1] ];
			el = this.events[i][2];
		
			this.element[0].addEventListener(ev, function (ev, el, fun, target, event) {
			  if (event.target.closest(el)) {
				  //target, event, element, input
				return fun.call(event.target, event, target, this);
			  }
			}.bind(this, ev, el, fun, this.element[0]));		
		}

		return this.element[0];
	}
};
