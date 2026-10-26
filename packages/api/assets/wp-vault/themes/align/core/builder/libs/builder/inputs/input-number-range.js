/**
 * Align Builder - Number, Range & CSS Unit Input Controls
 * @package Align
 */

let DateInput = { ...TextInput, ...{

    events: [
        ["change", "onChange", "input"],
	 ],
	
	init: function(data) {
		return this.render("dateinput", data);
	},
  }
}

let RangeInput = { ...Input, ...{

    events: [
        ["change", "onRangeChange", "input"],
	 ],
	 
	onRangeChange: function(event, node, input) {
		this.parentNode.querySelector('input[type=number]').value = this.value;
		this.parentNode.querySelector('input[type=range]').value = this.value;
		return input.onChange.call(this, event, node, input);
	},	 

	setValue: function(value) {
		this.element[0].querySelector('input[type=number]').value = value;
		this.element[0].querySelector('input[type=range]').value = value;
	},
	
	init: function(data) {
		return this.render("rangeinput", data);
	},
  }
}

let NumberInput = { ...Input, ...{

    events: [
        ["change", "onChange", "input"],
	 ],
	
	init: function(data) {
		return this.render("numberinput", data);
	},
  }
}

let CssUnitInput = { ...Input, ...{

	number:0,
	unit:"px",

    events: [
        ["change", "onInputChange", "select"],
        ["change", "onInputChange", "input"],
        ["keyup", "onInputChange", "input"],
	 ],
		
	onInputChange: function(event, node, input) {
		if (node) {
			let number = node.querySelector("input").value;
			let unit = node.querySelector("select").value;

			if (this.value != "") input[this.name] = this.value;// this.name = unit or number	
			if (unit == "") unit = "px";//if unit is not set use default px
		
			let value = "";	
			if (unit == "auto")  {
				node.classList.add("auto");
				value = unit;
			}
			else  {
				node.classList.remove("auto");
				value = number + (unit ? unit : "");
			}
			
			input.value = value;

			return input.onChange.call(this, event, node, input);
		}
	},
	
	setValue: function(value) {
		if (value && this.element) {
			let element = this.element[0];
			this.number = parseFloat(value);
			this.unit = value.replace(this.number, '').trim();
			
			if (this.unit == "auto") element.classList.add("auto");

			element.querySelector("input[type=number]").value = this.number;
			element.querySelector("select").value = this.unit;
		}
	},
	
	init: function(data) {
		return this.render("cssunitinput", data);
	},
  }
}
