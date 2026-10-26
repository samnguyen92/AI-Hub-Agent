/**
 * Align Builder Engine - Fonts Manager (Google Fonts)
 * @package Align
 */

Vvveb.FontsManager = {
	
	activeFonts:[],
	providers: {},//{"google":GoogleFontsManager};
	
	addFontList: function(provider, groupName, fontList) {
		let fonts = {};
		let fontNames = [];
		
		let fontSelect = generateElements("<optgroup label='" + groupName + "'></optgroup>")[0];
		for (const font in fontList) {
			fontNames.push({"text":font, "value":font, "data-provider": provider});
			let option = new Option(font, font);
			option.dataset.provider = provider;
			//option.style.setProperty("font-family", font);//font preview if the fonts are loaded in editor
			fontSelect.append(option);
		}
		document.getElementById("font-family").append(fontSelect);	

		let list = Vvveb.Components.getProperty("_base", "font-family");
		if (list) {
			list.onChange = function (node,value, input, component) {
				let option = input.options[input.selectedIndex];
				Vvveb.FontsManager.addFont(option.dataset.provider, value, node);
				return node;
			};
			
			list.data.options.push({optgroup:groupName});
			list.data.options = list.data.options.concat(fontNames);

			Vvveb.Components.updateProperty("_base", "font-family", {data:list.data});
			
			//update default font list
			fontList = list.data.options;
		}
	},
	
	addProvider: function(provider, Obj) {
		this.providers[provider] = Obj;
	},
	
	//add also element so we can keep track of the used fonts to remove unused ones
	addFont: function(provider, fontFamily, element = false) {
		if (!provider) return;
		
		let providerObj = this.providers[provider];
		if (providerObj) {
			providerObj.addFont(fontFamily);
			this.activeFonts.push({provider, fontFamily, element});
		}
	},
	
	removeFont: function(provider, fontFamily) {
		if (!provider) return;

		let providerObj = this.providers[provider];
		if (provider!= "default" && providerObj) {
			providerObj.removeFont(fontFamily);
		}
	},
	
	//check if the added fonts are still used for the elements they were set and remove unused ones
	cleanUnusedFonts: function (){
		for (i in this.activeFonts) {
			let elementFont = this.activeFonts[i];
			if (elementFont.element) {
				if (Vvveb.StyleManager.getStyle(elementFont.element,'font-family').replaceAll('"','') != elementFont.fontFamily) {
					this.removeFont(elementFont.provider, elementFont.fontFamily);
				}
			}
		}
	}
};
