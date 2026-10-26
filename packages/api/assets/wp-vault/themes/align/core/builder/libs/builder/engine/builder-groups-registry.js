/**
 * Align Builder Engine - Groups Registry (Blocks, Sections, Styles)
 * @package Align
 */

Vvveb.Blocks = {
	
	_blocks: {},

	get: function(type) {
		return this._blocks[type];
	},

	add: function(type, data) {
		data.type = type;
		this._blocks[type] = data;
	},
};	

Vvveb.Sections = {
	
	_sections: {},

	get: function(type) {
		return this._sections[type];
	},

	add: function(type, data) {
		data.type = type;
		this._sections[type] = data;
	},
};	

Vvveb.Styles = {
	
	_styles: {},

	get: function(type) {
		return this._styles[type];
	},
	
	getByStyle: function(style) {
		for (const name in this._styles) {
			if (this._styles[name].style == style) {
				return name;
			}
		}
	},

	add: function(type, data) {
		data.type = type;
		this._styles[type] = data;
	},
};
