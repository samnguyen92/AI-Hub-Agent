/**
 * Align Builder - Checkbox & Toggle Switch Controls
 * @package Align
 */

let CheckboxInput = { ...Input, ...{

    events: [
        ["change", "onCheck", "input"],
	 ],
	
	onCheck: function(event, node, input) {
		input.value = this.checked;
		return input.onChange.call(this, event, node, input);
	},

	setValue: function(value) {
		if (this.element[0]) {
			let input = this.element[0].querySelector('input');

			if (input) { 
				if (value) {
					input.checked = true;
				} else {
					input.checked = false;
				}
			}
		}
	},
	
	init: function(data) {
		return this.render("checkboxinput", data);
	},
  }
}

let ToggleInput = { ...Input, ...{
	events: [
        ["change", "onToggleChange", "input"],
	 ],

	onToggleChange: function(event, node, input) {
		const valOn = this.getAttribute("data-value-on") || "true";
		const valOff = this.getAttribute("data-value-off") || "false";
		input.value = this.checked ? valOn : valOff;
		return input.onChange.call(this, event, node, input);
	},

	setValue: function(value) {
		if (this.element[0]) {
			let input = this.element[0].querySelector('input');

			if (input) { 
				const valOn = input.getAttribute("data-value-on") || "true";
				if (value === true || value === "true" || value === 1 || value === "1" || value == valOn) {
					input.checked = true;
					input.setAttribute("checked", "true");
				} else {
					input.checked = false;
					input.removeAttribute("checked");
				}
			}
		}
	},
	
	init: function(data) {
		data = data || {};
		if (typeof data.on === 'undefined') data.on = "true";
		if (typeof data.off === 'undefined') data.off = "false";
		return this.render("toggle", data);
	},
  }
}
