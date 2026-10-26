/**
 * Align Builder Engine - Color Palette Manager
 * @package Align
 */

Vvveb.ColorPalette = {
	colors: {},
	
	getAll: function() {
		return this.colors;
	},
	
	add: function(name, color) {
		this.colors[name] = color;
	},
	
	remove: function(color) {
		delete this.colors[name];
	},
}

function friendlyName(name) {
	name = name.replaceAll("--bs-","").replace(/[-_]/g, " ").trim();  
	return name = name[0].toUpperCase() + name.slice(1);
}

Vvveb.ColorPaletteManager = {
	
	cssVars: {"font": {}, "color" : {}, "dimensions": {}},
	
	getType: function (type) { 
		return this.cssVars[type];
	},
		
	getAllCSSVariableNames:  function (styleSheets = document.styleSheets, selector){

	   for(let i = 0; i < styleSheets.length; i++){
		  try{ 
			 let cssRules =  styleSheets[i].cssRules;
			 for( let j = 0; j < cssRules.length; j++){
				try{
				   let style = cssRules[j].style;	
				   if (selector && cssRules[j].selectorText && cssRules[j].selectorText != selector) continue;
				   for(let k = 0; k < style.length; k++){
					  let name = style[k];
					  let value = style.getPropertyValue(name).trim();
					  let type = "";
					  
					  if(name.startsWith("--")){
						//ignore bootstrap rgb variables
						if (name.endsWith("-rgb")) continue;
						//ignore variables depending on other variables
						if (value.startsWith("var(")) continue;

						let friendlyName = name.replace("--bs-","").replaceAll("-", " ");  
						 
						if (value.startsWith("#")) {
							type = "color";
						} else if (value.indexOf('"') >= 0 || value.indexOf("'") >= 0) {
							type = "font";
						} else if (value.endsWith('em') > 0 || value.endsWith('px') > 0) {
							type = "dimensions";
						} else if (!isNaN(parseFloat(value))) {
							type = "dimensions";
						}

						if (type) {
							 if (!this.cssVars[type]) this.cssVars[type] = {};
							 this.cssVars[type][name] = {value, type, friendlyName};
						 }
					  }
				   }
				} catch (error) {}
			 }
		  } catch (error) {}
	   }
	   
	   return this.cssVars;
	},
	
	getCssWithVars:  function (styleSheets = document.styleSheets, vars){
	   let cssVars = {};
	   let css = "";
	   let cssStyles = "";
	   for(let i = 0; i < styleSheets.length; i++){
		  try{ 
			 let cssRules =  styleSheets[i].cssRules;
			 for( let j = 0; j < cssRules.length; j++){
				try{
				   let style = cssRules[j].style;	
				   //if (selector && cssRules[j].selectorText && cssRules[j].selectorText != selector) continue;
				   cssStyles = "";
				   for(let k = 0; k < style.length; k++){
					  let name = style[k];
					  let value = style.getPropertyValue(name);
					  if(name.startsWith('--bs-btn-')) {
						  for (v in vars) {
							  if (value == vars[v]) {
								  cssVars[name] = v;
								  cssStyles += name + ":var(" + v + ");\n";
							}
						  }
					  }
					  
				   }
				   if (cssStyles) {
				   css += cssRules[j].selectorText + "{\n"
				   css += cssStyles;
				   css += "}\n";
					}
				} catch (error) {}
			 }
		  } catch (error) {}
	   }
	   return cssVars;
	},

	init: function(document) {
		Vvveb.Components.render("config/bootstrap", "#configuration .component-properties");
		
		//apply current theme color palette
		//let colors = Vvveb.ColorPaletteManager.getType("color");
		let colors = this.cssVars.color;
		for (const name in colors) {
			let color = colors[name].value;

			if (color[0] == "#" && color.length == 7) {//add only valid hex color values 7 char long
				//add color as name to keep values unique
				Vvveb.ColorPalette.add(color, color);
			}
		}
	},
	
};
