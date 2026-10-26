/**
 * Align Builder Engine - Components Registry
 * @package Align
 */

Vvveb.Components = {
	_components: {},
	_nodesLookup: {},
	_attributesLookup: {},
	_classesLookup: {},
	_classesRegexLookup: {},
	componentPropertiesElement: "#right-panel .component-properties",
	componentPropertiesDefaultSection: "content",

	get: function(type) {
		return this._components[type];
	},

	updateProperty: function(type, key, value) {
		let properties = this._components[type]["properties"];
		for (property in properties) {
			if (key == properties[property]["key"])  {
				return this._components[type]["properties"][property] = 
				Object.assign(properties[property], value);
			}
		}
	},

	getProperty: function(type, key) {
		let properties = this._components[type] ? this._components[type]["properties"] : [];
		for (property in properties) {
			if (key == properties[property]["key"])  {
				return properties[property];
			}
		}
	},

	add: function(type, data) {
		data.type = type;
		this._components[type] = data;
		if (data.nodes) {
			for (let i in data.nodes) {	
				this._nodesLookup[ data.nodes[i] ] = data;
			}
		}
		if (data.attributes) {
			if (data.attributes.constructor === Array) {
				for (let i in data.attributes) {	
					this._attributesLookup[ data.attributes[i] ] = data;
				}
			} else {
				for (let i in data.attributes) {	
					if (typeof this._attributesLookup[i] === 'undefined') {
						this._attributesLookup[i] = {};
					}
					if (typeof this._attributesLookup[i][ data.attributes[i] ] === 'undefined') {
						this._attributesLookup[i][ data.attributes[i] ] = {};
					}
					this._attributesLookup[i][ data.attributes[i] ] = data;
				}
			}
		}
		if (data.classes) {
			for (let i in data.classes) {	
				this._classesLookup[ data.classes[i] ] = data;
			}
		}
		if (data.classesRegex) {
			for (let i in data.classesRegex) {	
				this._classesRegexLookup[ data.classesRegex[i] ] = data;
			}
		}
	},

	extend: function(inheritType, type, data) {
		let newData = data;
		if (inheritData = this._components[inheritType]) {
			newData = {...inheritData, ...data};
			newData.properties = (data.properties ? data.properties : []).concat(inheritData.properties ? inheritData.properties : []);
		}

		// Sort by order
		newData.properties.sort(function (a, b) {
			if (typeof a.sort === "undefined") a.sort = 0;
			if (typeof b.sort === "undefined") b.sort = 0;
			if (a.sort < b.sort) return -1;
			if (a.sort > b.sort) return 1;
			return 0;
		});

		this.add(type, newData);
	},

	matchNode: function(node) {
		let component = {};
		if (!node || !node.tagName) return false;

		// 1. Specialized WordPress Block inspection before generic fallback
		if (node.getAttribute) {
			const wpBlock = node.getAttribute('data-wp-block');
			if (wpBlock) {
				if (/<!--\s*wp:align\/live-search\b/.test(wpBlock) && this._components['wordpress/live-search']) {
					return this._components['wordpress/live-search'];
				}
			}
		}
		
		if (node.attributes && node.attributes.length) {
			//search for attributes
			for (let i in node.attributes) {
				if (node.attributes[i]) {
					attr = node.attributes[i].name;
					value = node.attributes[i].value;

					if (attr in this._attributesLookup) {
						component = this._attributesLookup[ attr ];
						
						//currently we check that is not a component by looking at name attribute
						//if we have a collection of objects it means that attribute value must be checked
						if (typeof component["name"] === "undefined") {
							if (value in component) {
								return component[value];
							}
						} else {
							return component;
						}
					}
				}
			}
				
			for (let i in node.attributes) {
				attr = node.attributes[i].name;
				value = node.attributes[i].value;
				
				//check for node classes
				if (attr == "class") {
					classes = value.split(" ");
					
					for (j in classes) {
						if (classes[j] in this._classesLookup)
						return this._classesLookup[ classes[j] ];	
					}
					
					for (regex in this._classesRegexLookup) {
						regexObj = new RegExp(regex);
						if (regexObj.exec(value)) {
							return this._classesRegexLookup[ regex ];	
						}
					}
				}
			}
		}

		tagName = node.tagName.toLowerCase();
		if (tagName in this._nodesLookup) return this._nodesLookup[ tagName ];
	
		return false;
	},

	renderProperties: function(component, properties, componentsPanelSections, container) {
		let fn = function(component, property) {
			if (property.input) {
				property.input.addEventListener('propertyChange', (event) => {
						element = selectedElement = (nodeElement && nodeElement.isConnected) ? nodeElement : Vvveb.Builder.selectedEl;
						let value = event.detail.value, input = event.detail.input, origEvent = event.detail.origEvent;
						
						if (!element) {
							console.warn('[Align Components] propertyChange skipped: target element not found');
							return;
						}
						if (property.element) element = property.element;
						if (property.child) element = element.querySelector(property.child);
						if (property.parent) element = element.parent(property.parent);
						if (!element) return;

						if (property.onChange) {
							let ret = property.onChange(element, value, input, component, origEvent);
							if (typeof ret == "object")  {
								element = ret;
							} else {
								value = ret;
							}
						}/* else */
						if (property.htmlAttr) {
							let oldValue;
							
							if (property.htmlAttr == "class") {
								oldValue = element.getAttribute(property.htmlAttr) || "";
								
								if (property.validValues) {
									element.classList.remove(...property.validValues.filter(v => v));
									if (value) {
										element.classList.add(...value.split(" "));
									}
								} else {
									if (value) {
										element.setAttribute(property.htmlAttr, value);
									} else {
										element.removeAttribute(property.htmlAttr);
									}
								}
							}
							else if (property.htmlAttr == "style") {
								oldStyle = Vvveb.StyleManager.getCss();
								element = Vvveb.StyleManager.setStyle(element, property.key, value);
							} else if (property.htmlAttr == "innerHTML")  {
								oldValue = element.innerHTML;
								element = Vvveb.ContentManager.setHtml(element, value);
							} else if (property.htmlAttr == "outerHTML")  {
								oldValue = element.outerHTML;
								element = Vvveb.ContentManager.setHtml(element, value, true);
							} else if (property.htmlAttr == "innerText")  {
								oldValue = element.innerText;
								element = Vvveb.ContentManager.setText(element, value);
							} else {
								oldValue = element.getAttribute(property.htmlAttr);
								//if value is empty then remove attribute useful for attributes without values like disabled
								if (value) {
									element.setAttribute(property.htmlAttr, value);
								} else {
									element.removeAttribute(property.htmlAttr);
								}
							}
							
							if (property.htmlAttr == "style") {
								mutation = {
									type: 'style', 
									target: element, 
									attributeName: property.htmlAttr, 
									oldValue: oldStyle, 
									newValue: Vvveb.StyleManager.getCss()};
								
								Vvveb.Undo.addMutation(mutation);
							} else {
								Vvveb.Undo.addMutation({
									type: 'attributes', 
									target: element, 
									attributeName: property.htmlAttr, 
									oldValue: oldValue, 
									newValue: value
								});
							}

							const propEvent = new CustomEvent("Vvveb.Builder.propertyChange", {
								detail: {
									element: element,
									property: property.key || property.htmlAttr || "",
									htmlAttr: property.htmlAttr,
									oldValue: oldValue,
									newValue: value
								}
							});
							console.log('[Align Components] Property changed:', property.htmlAttr, '| Target:', element.tagName || 'unknown');
							window.dispatchEvent(propEvent);
							if (Vvveb.Builder && Vvveb.Builder.frameBody) {
								Vvveb.Builder.frameBody.dispatchEvent(new CustomEvent("Vvveb.Builder.propertyChange", { detail: propEvent.detail }));
							}
						} else {
							if (Vvveb.Undo) {
								Vvveb.Undo.addMutation({
									type: 'attributes',
									target: element,
									attributeName: property.key,
									oldValue: null,
									newValue: value
								});
							}
						}

						if (component && component.onChange) {
							element = component.onChange(element, property, value, input);
						}
						
						const isTypingInInspector = origEvent && (origEvent.type === "input" || origEvent.type === "keyup") && property.htmlAttr !== "outerHTML";
						if (!isTypingInInspector) {
							if (property.child || property.parent) {
								Vvveb.Builder.selectNode(selectedElement);
							} else {
								Vvveb.Builder.selectNode(element);
							}
						} else if (Vvveb.TreeList?.syncWithBuilderSelection) {
							Vvveb.TreeList.syncWithBuilderSelection();
							console.log('[Align Components] Preserved tree active state during inspector typing.');
						}
						
						return element;
				});		
			}
			
			return property.input;
		};
		
		let defaultSection = this.componentPropertiesDefaultSection;
		let section;
		if (componentsPanelSections) {
			section = componentsPanelSections[defaultSection].querySelector('.section[data-section="default"]');
		}
		let nodeElement = Vvveb.Builder.selectedEl;
		
		for (let i in properties) {
			let property = properties[i];
			let element = nodeElement;

			if (property.beforeInit) property.beforeInit(element);
			
			if (property.element) element = property.element;
			if (property.child) element = element.querySelector(property.child) ?? element;
			if (property.parent) element = element.closest(property.parent) ?? element;

			// 1. Declarative check & Global Safety Guard: Hide raw text properties if element has child elements
			const hasChildNodes = element?.children?.length > 0;
			const isDirectText = !property.child && (property.name === "Text" || property.key === "innerHTML" || property.htmlAttr === "innerHTML");
			const isCodeBlock = element && (element.tagName === "CODE" || element.tagName === "PRE");
			if (hasChildNodes && (property.childlessOnly || (isDirectText && property.childlessOnly !== false && !isCodeBlock))) {
				console.log('[Align Components] Property "' + (property.name || property.key) + '" skipped: element has child elements');
				property.input = null;
				continue;
			}

			// 2. Functional filter: Custom filter predicate
			if (typeof property.filter === "function") {
				try {
					if (!property.filter(element, component, nodeElement)) {
						console.log('[Align Components] Property "' + (property.name || property.key) + '" skipped by filter');
						property.input = null;
						continue;
					}
				} catch (filterErr) {
					console.error('[Align Components] Error executing property filter:', filterErr);
				}
			}

			if (property.data) {
				property.data["key"] = property.key;
			} else {
				property.data = {"key" : property.key};
			}

			if (typeof property.group  === 'undefined') property.group = null;

			property.input = property.inputtype.init(property.data, element);
			
			let value = "";
			const key = property.key;
			const htmlAttr = property.htmlAttr;

			// Strict Hooking Filter: Only hook Text, Id, Class, Title, and basic direct HTML attributes (src, href, etc.)
			// Never scan inline CSS, internal stylesheet rules, or computed styles into style configs.
			if (htmlAttr === "innerHTML" || key === "innerHTML") {
				value = Vvveb.ContentManager.getHtml(element);
				if (property.inputtype?.setValue) property.inputtype.setValue(value);
			} else if (htmlAttr === "innerText" || key === "innerText" || key === "text") {
				value = Vvveb.ContentManager.getText(element);
				if (property.inputtype?.setValue) property.inputtype.setValue(value);
			} else if (htmlAttr === "outerHTML" || key === "outerHTML") {
				value = Vvveb.ContentManager.getHtml(element, true);
				if (property.inputtype?.setValue) property.inputtype.setValue(value);
			} else if (key === "id" || htmlAttr === "id") {
				value = element.getAttribute ? (element.getAttribute("id") || "") : (element.id || "");
				if (property.inputtype?.setValue) property.inputtype.setValue(value);
			} else if (key === "class" || (htmlAttr === "class" && !property.validValues)) {
				value = element.getAttribute ? (element.getAttribute("class") || "") : (element.className || "");
				if (typeof value === "string" && Vvveb.Builder && Vvveb.Builder.ignoreClasses) {
					value = value.split(/\s+/).filter(c => c && !Vvveb.Builder.ignoreClasses.includes(c)).join(" ");
				}
				if (property.inputtype?.setValue) property.inputtype.setValue(value);
			} else if (htmlAttr === "class" && property.validValues) {
				const classStr = element.getAttribute ? (element.getAttribute("class") || "") : (element.className || "");
				const classes = classStr.split(/\s+/);
				value = property.validValues.find(v => v && classes.includes(v)) || (property.defaultValue || "");
				if (property.inputtype?.setValue) property.inputtype.setValue(value);
			} else if (key === "title" || htmlAttr === "title") {
				value = element.getAttribute ? (element.getAttribute("title") || "") : (element.title || "");
				if (property.inputtype?.setValue) property.inputtype.setValue(value);
			} else if (property.init && typeof property.init === "function") {
				try {
					value = property.init(element);
				} catch (e) {
					value = (htmlAttr && element.getAttribute) ? (element.getAttribute(htmlAttr) || "") : "";
				}
				if (property.inputtype?.setValue) property.inputtype.setValue(value);
			} else if (htmlAttr && htmlAttr !== "style" && !property.validValues) {
				if (element.getAttribute) {
					value = element.getAttribute(htmlAttr) || "";
				}
				if (value && property.inputtype?.setValue) property.inputtype.setValue(value);
			}
			
			fn(component, property);
			
			let propertySection = defaultSection;
			if (property.section) {
				propertySection = property.section;
			}			

			if (property.inputtype == SectionInput) {
				section = componentsPanelSections[propertySection].querySelector('.section[data-section="' + property.key + '"]');
				
				if (Vvveb.preservePropertySections && section) {
					section.replaceChildren();
				} else  {
					componentsPanelSections[propertySection].append(property.input);
					section = componentsPanelSections[propertySection].querySelector('.section[data-section="' + property.key + '"]');
				}
			}
			else {
				let row = generateElements(tmpl('vvveb-property', property))[0]; 
				row.querySelector('.input').append(property.input);
				if (container) {
					container.append(row);
				} else {
					section.append(row);
				}
			}
			
			if (property.inputtype.afterInit) {
				property.inputtype.afterInit(property.input);
			}

			if (property.afterInit) {
				property.afterInit(element, property.input);
			}
		}
	},
	
	render: function(type, panel = false) {
		let component = this._components[type];
		if (!component) return;

		if (!panel) {
			panel = this.componentPropertiesElement;
		}

		let defaultSection = this.componentPropertiesDefaultSection;
		let componentsPanelSections = {};

		const isWpCustomComponent = (type === 'wordpress/shortcode' || type === 'wordpress/block' || type === 'wordpress/post-content');

		document.querySelectorAll(panel + " .tab-pane:not(#seo-tab):not([data-section='seo'])").forEach((el, i) => {
			let sectionName = el.dataset.section;
			if (sectionName === 'seo' || el.id === 'seo-tab') return;
			componentsPanelSections[sectionName] = el;
			for (const item of el.querySelectorAll(
				'label:not([data-header="default"]) + input,' +
				'label:not([data-header="default"]),' +
				'.section:not([data-section="default"])'
			)) {
				if (isWpCustomComponent && item.closest('#align-content-overwrite-section, .align-content-overwrite-section')) {
					continue;
				}
				item.remove();
			}
		});

		if (!isWpCustomComponent) {
			const existingAcc = document.getElementById("align-content-overwrite-section");
			if (existingAcc && window.AlignBuilderModules?.WpReuseBlockOverwrite?.removeAccordion) {
				window.AlignBuilderModules.WpReuseBlockOverwrite.removeAccordion();
			}
		}
		
		let section = componentsPanelSections[defaultSection].querySelector('.section[data-section="default"]');
		
		if (!(Vvveb.preservePropertySections && section)) {
			let template = tmpl("vvveb-input-sectioninput", {key:"default", header:component.name, expanded:true});

			componentsPanelSections[defaultSection].replaceChildren();
			componentsPanelSections[defaultSection].append(generateElements(template)[0]);
			
			section = componentsPanelSections[defaultSection].querySelector(".section");
		}

		let defaultCheck = componentsPanelSections[defaultSection].querySelector('input.header_check');
		if (defaultCheck) {
			defaultCheck.checked = true;
		}

		let headerSpan = componentsPanelSections[defaultSection]?.querySelector('[data-header="default"] span');
		if (headerSpan) {
			headerSpan.innerHTML = component.name;
		}
		if (section) {
			section.replaceChildren();
		}
	
		if (component.beforeInit) component.beforeInit(Vvveb.Builder.selectedEl);

		this.renderProperties(component, component.properties, componentsPanelSections);

		if (isWpCustomComponent && componentsPanelSections[defaultSection]) {
			const existingAcc = componentsPanelSections[defaultSection].querySelector('#align-content-overwrite-section, .align-content-overwrite-section');
			if (existingAcc) {
				componentsPanelSections[defaultSection].append(existingAcc);
			}
		}
		
		if (component.init) component.init(Vvveb.Builder.selectedEl);
	}
};
