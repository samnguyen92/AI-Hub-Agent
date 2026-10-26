/**
 * Align Builder Engine - Style Manager (CSS Properties)
 * @package Align
 */

Vvveb.StyleManager = {
	
	styles:{},
	cssContainer:false,
	breakpoints: {
		'mobile':'375px',
		'tablet':'768px',
		'desktop':'1920px',
		'sm':'375px',
		'md':'768px',
		'lg':'991.98px',
		'xl':'1199.98px',
		'xxl':'1399.98px'
	},
	doc:false,
	inlineCSS:false,
	currentElement:null,
	currentSelector:null,
	currentBreakpoint:"none",
	state:"",//hover, active etc
	
	getScopedId: function() {
		let pageData = (window.Vvveb?.FileManager && window.Vvveb.FileManager.getPageData) ? window.Vvveb.FileManager.getPageData() : null;
		let postId = pageData ? pageData.postId : null;
		let tpId = pageData ? pageData.tpId : null;
		let templateType = pageData ? pageData.templateType : null;
		let slug = pageData?.slug || '';

		// Fallback to URL params ONLY if pageData is null / empty
		if (!pageData || (!postId && !tpId)) {
			let urlParams = (typeof window !== "undefined" && window.location) ? new URLSearchParams(window.location.search) : null;
			if (urlParams) {
				postId = postId || urlParams.get('post_id');
				tpId = tpId || urlParams.get('tp_id');
				templateType = templateType || urlParams.get('template_type');
			}
		}

		if (tpId) {
			let rawId = String(tpId).replace(/^t(p|pl)_/, '');
			let tpSlug = rawId.includes('//') ? rawId.split('//')[1] : rawId;
			let isTemplate = (templateType === 'template' || String(tpId).startsWith('tpl_'));
			let prefix = isTemplate ? 'template' : 'part';
			return 'style-' + prefix + '-' + tpSlug;
		}
		if (postId) {
			let isBlock = (pageData?.folder === 'Reusable Blocks' || pageData?.type === 'Reusable Blocks' || (pageData?.name && String(pageData.name).startsWith('block_')));
			let entitySlug = slug || (window.VvvebWpConfig?.activePostSlug) || postId;
			let prefix = isBlock ? 'block' : 'page';
			return 'style-' + prefix + '-' + entitySlug;
		}
		return 'vvvebjs-styles';
	},

	getStyleElement: function(doc) {
		doc = doc || this.doc || window.FrameDocument || (Vvveb.Builder && Vvveb.Builder.iframe && Vvveb.Builder.iframe.contentWindow ? Vvveb.Builder.iframe.contentWindow.document : null);
		if (!doc) return null;
		let scopedId = this.getScopedId();
		let el = doc.getElementById(scopedId);
		if (!el) {
			el = doc.getElementById('align-' + scopedId);
		}
		if (!el) {
			let pageData = (window.Vvveb?.FileManager && window.Vvveb.FileManager.getPageData) ? window.Vvveb.FileManager.getPageData() : null;
			let postId = pageData ? pageData.postId : (typeof window !== "undefined" && window.location ? new URLSearchParams(window.location.search).get('post_id') : null);
			if (postId) {
				let isBlock = (pageData?.folder === 'Reusable Blocks' || pageData?.type === 'Reusable Blocks' || (pageData?.name && String(pageData.name).startsWith('block_')));
				let legacyId = (isBlock ? 'style-block-' : 'style-page-') + postId;
				let oldLegacyId = (isBlock ? 'align-style-block-' : 'align-style-page-') + postId;
				el = doc.getElementById(legacyId) || doc.getElementById(oldLegacyId);
			}
		}
		if (!el) {
			el = doc.getElementById('vvvebjs-styles') || doc.querySelector('style[id^="style-"], style[id^="align-style-"]');
		}
		return el || null;
	},

	init: function(doc) {
		doc = doc || this.doc || window.FrameDocument || (Vvveb.Builder && Vvveb.Builder.iframe && Vvveb.Builder.iframe.contentWindow ? Vvveb.Builder.iframe.contentWindow.document : null);
		if (doc) {
			this.doc = doc;
			let style = this.getStyleElement(doc);
			let scopedId = this.getScopedId();
			console.log('%c[Align StyleManager] init() called | style tag exists:', 'color: #38bdf8;', !!style, style ? `(id: #${style.id}, CSS length: ${style.textContent.trim().length})` : `❌ NOT FOUND (target: #${scopedId})`);
			if (style && style.textContent && style.textContent.trim()) {
				style.id = scopedId;
				if (doc.head && style.parentNode !== doc.head) {
					doc.head.append(style);
				}
				this.cssContainer = style;
				try {
					this.loadCss();
				} catch (e) {}
			} else {
				if (style && style.parentNode && (style.id === scopedId || style.id === 'align-' + scopedId || style.id === 'vvvebjs-styles')) style.remove();
				this.cssContainer = false;
				this.styles = {};
			}
			return this.cssContainer; 
		}
	},	
	
	loadCss: function() {
		let style = this.cssContainer ? this.cssContainer.sheet : null;
		if (!style || !style.cssRules) return;

		for (let j = 0; j < style.cssRules.length; j++) {
			let media = "none";
			let rule = style.cssRules[j];
			
			if (rule.media && rule.media.mediaText) {
				let mediaText = rule.media.mediaText;
				for (let breakpoint in this.breakpoints) {
					if (mediaText.indexOf(this.breakpoints[breakpoint]) > -1) {
						media = breakpoint;
						break;
					}
				} 
			}
			
			const selector = (media === "none") ? rule.selectorText : (rule.cssRules && rule.cssRules[0] ? rule.cssRules[0].selectorText : '');
			const styles = (media === "none") ? rule.style : (rule.cssRules && rule.cssRules[0] ? rule.cssRules[0].style : null);

			if (media && selector && styles) {
				this.styles[media] = this.styles[media] ?? {};
				this.styles[media][selector] = {};
			
				for (let k = 0; k < styles.length; k++) {
					const property = styles[k];
					const value = styles[property];
					this.styles[media][selector][property] = value;
				}
			}
		}		
	},
	
	getSelectorForElement: function(element) {
		if (!element) return '';
		
		let currentElement = element;
		let selector = [];
		
		while (currentElement.parentElement) {
			let elementSelector = "";
			let classSelector = Array.from(currentElement.classList).map(function (className) {
					if (Vvveb.Builder.ignoreClasses.indexOf(className) == -1) {
						return "." + className;
					}
				}).join("");

			//element (tag) selector
			let tag = currentElement.tagName.toLowerCase();
			//exclude top most element body unless the parent element is body
			if (tag == "body" && selector.length > 1) {
				break;
			}
			
			//stop at a unique element (with id)
			if (currentElement.id) {
				elementSelector = "#" + currentElement.id;
				selector.push(elementSelector);
				break;
			} else if (classSelector) {
				//class selector
				elementSelector = classSelector;
			} else {
				//element selector
				elementSelector = tag
			}
			
			if (elementSelector) {
				selector.push(elementSelector);
			}
			
			currentElement = currentElement.parentElement;
		}
		
		return selector.reverse().join(" > ");
	},	
	
	setState: function(state) {
		this.state = state;
	},

	addSelectorState: function(selector) {
		return selector + (this.state ? ":" + this.state : "");
	},
	
	setStyle: function(element, styleProp, value) {
		let selector;
		
		if (typeof(element) == "string") {
			selector = element;
		} else {
			let node = element;

			//if propert is set with inline style attribute then override it and don't save to css
			//inline text editor sets properties like font-size inline that can't be later overriten from css
			if (node.style && node.style[styleProp]) {
				node.style[styleProp] = value;
				return element;
			}

			selector = this.getSelectorForElement(node);	
		}
		
		if (this.inlineCSS) {
			element.style[styleProp] = value;
			return element;	
		}
		
		selector = this.addSelectorState(selector);
		
		const media = this.currentBreakpoint;
		
		//styles[media][selector][styleProp] = value
		if (!this.styles[media]) {
			this.styles[media] = {};
		}
		if (!this.styles[media][selector]) {
			this.styles[media][selector] = {};
		}
		if (!this.styles[media][selector][styleProp]) {
			this.styles[media][selector][styleProp] = {};
		}
		this.styles[media][selector][styleProp] = value;
		
		this.generateCss(media);

		window.dispatchEvent(new CustomEvent("Vvveb.StyleManager.setStyle", {detail: {element, styleProp, value}}));
		
		return element;		
        	//uncomment bellow code to set css in element's style attribute 
		//return element.css(styleProp, value);
	},
	
	setCss: function (css) {
		let doc = this.doc || window.FrameDocument || (Vvveb.Builder && Vvveb.Builder.iframe && Vvveb.Builder.iframe.contentWindow ? Vvveb.Builder.iframe.contentWindow.document : null);
		const cleanCss = (css || '').trim();
		console.log('%c[Align StyleManager] setCss() called | incoming CSS len:', 'color: #eab308;', cleanCss.length, cleanCss.length === 0 ? '⚠️ (WIPING CSS TO EMPTY!)' : '');
		if (doc) {
			this.doc = doc;
			let style = this.getStyleElement(doc);
			let scopedId = this.getScopedId();
			if (!cleanCss) {
				if (style && style.parentNode && (style.id === scopedId || style.id === 'align-' + scopedId || style.id === 'vvvebjs-styles')) {
					style.remove();
				}
				this.cssContainer = false;
				this.styles = {};
				return;
			}
			if (!style) {
				style = doc.createElement('style');
				style.id = scopedId;
				style.type = 'text/css';
				if (doc.head) doc.head.append(style);
			} else {
				style.id = scopedId;
				if (doc.head && style.parentNode !== doc.head) {
					doc.head.append(style);
				}
			}
			style.textContent = cleanCss;
			this.cssContainer = style;
			try {
				this.loadCss();
			} catch (e) {}
		}
	},

	getCss: function () {
		let doc = window.FrameDocument || this.doc || (Vvveb.Builder && Vvveb.Builder.iframe && Vvveb.Builder.iframe.contentWindow ? Vvveb.Builder.iframe.contentWindow.document : null);
		if (doc) {
			let style = this.getStyleElement(doc);
			if (style && typeof style.textContent === "string") {
				this.cssContainer = style;
				const css = style.textContent.trim();
				return css;
			}
		}
		if (this.cssContainer && typeof this.cssContainer.textContent === "string") {
			return this.cssContainer.textContent.trim();
		}
		return '';
	},
	
	generateCss: function (mediaOverride) {
		this.cssContainer = this.getStyleElement(this.doc);

		let hasRules = false;
		let css = "";
		for (let media in this.styles) {
			let isMedia = (media !== "none" && media !== "desktop" && this.breakpoints[media] && this.breakpoints[media] !== "100%");
			if (isMedia) {
				let width = this.breakpoints[media];
				css += `@media (max-width: ${width}){\n\n`;
			}
			for (let selector in this.styles[media]) {
				let ruleContent = "";
				for (let property in this.styles[media][selector]) {
					const value = this.styles[media][selector][property];
					if (value !== undefined && value !== "") {
						ruleContent += `\t${property}: ${value};\n`;
					}
				}
				if (ruleContent) {
					hasRules = true;
					css += `${selector} {\n` + ruleContent + '}\n\n';
				}
			}
			if (isMedia) {
				css += `}\n\n`;
			}
		}

		if (hasRules && css.trim()) {
			let scopedId = this.getScopedId();
			if (!this.cssContainer) {
				this.cssContainer = this.doc.createElement('style');
				this.cssContainer.id = scopedId;
				this.cssContainer.type = "text/css";
				if (this.doc.head) this.doc.head.append(this.cssContainer);
			} else {
				this.cssContainer.id = scopedId;
			}
			this.cssContainer.innerHTML = css;
			return css;
		} else {
			if (this.cssContainer && this.cssContainer.parentNode) {
				this.cssContainer.remove();
			}
			this.cssContainer = false;
			return '';
		}
	},
	
	
	_getCssStyle: function(element, styleProp){
		// Disabled scanning of inline/internal/computed CSS to eliminate incorrect style hooking into configs.
		return "";
	},
	
	getStyle: function(element, styleProp){
		return this._getCssStyle(element, styleProp);
	}
}
