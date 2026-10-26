/**
 * Align Builder Engine - Style List Accordion
 * @package Align
 */

Vvveb.StyleList = {
	
	selector: '.sections-container',
	allowedComponents: {},
	
	applyStyle: function(style) {
		let doc =  window.FrameDocument;
		let themeStyle = doc.getElementById("vvvebjs-theme-style");
		
		if (!style) {
			if (themeStyle) themeStyle.remove();
			return;
		}
		
		//if style element does not exist create it			
		if (!themeStyle) {
			themeStyle    = doc.createElement("link");
			themeStyle.id = "vvvebjs-theme-style";
			themeStyle.rel = "stylesheet";
			themeStyle.media = "screen";
			doc.head.append(themeStyle);
		}
		
		themeStyle.href = style;
	},
	
	load: function(doc) {
		let themeStyle = window.FrameDocument.getElementById("vvvebjs-theme-style");
		if (themeStyle) {
			let style = Vvveb.Styles.getByStyle(themeStyle.getAttribute("href"));
			if (style) {
				document.querySelector('.styles-list [data-type="' + style + '"]')?.classList.add("active");
			}
		}
	},
	
	init: function() {

		let self = this;
		document.querySelector(".styles-list").addEventListener("click", function (e) {
			let element = e.target.closest("li");
			if (element) {
				let style = Vvveb.Styles.get(element.dataset.type);
				
				if (element.classList.contains("active")) {
					element.classList.remove("active");
					style = "";//if already active remove current style
				} else {
					document.querySelector(".styles-list .active")?.classList.remove("active");
					element.classList.add("active");
				}
				
				self.applyStyle(style.style);
				
				console.log(style);

				e.preventDefault();
			}
		});
				
	}
}
