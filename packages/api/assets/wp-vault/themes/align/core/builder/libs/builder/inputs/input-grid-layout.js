/**
 * Align Builder - Grid Layout & Products Controls
 * @package Align
 */

let GridLayoutInput = { ...TextInput, ...{

    events: [
        ["focusout", "onChange", "input"],
	 ],
	
	init: function(data) {
		return this.render("textinput", data);
	},
  }
}

let ProductsInput = { ...TextInput, ...{

    events: [
        ["focusout", "onChange", "input"],
	 ],
	
	init: function(data) {
		return this.render("textinput", data);
	},
  }
}

let GridInput = { ...Input, ...{
	

    events: [
        ["change", "onChange", "select" /*'select'*/],
        ["click", "onChange", "button" /*'select'*/],
	 ],
	

	setValue: function(value) {
		if (this.element[0] && value) {
			let input = this.element[0].querySelector('select');
		
			if (input) { 
				input.value = value;
				input.querySelector("option[selected]").selected = true;
			}
		}
	},
	
	init: function(data) {
		return this.render("grid", data);
	},
  }
}
