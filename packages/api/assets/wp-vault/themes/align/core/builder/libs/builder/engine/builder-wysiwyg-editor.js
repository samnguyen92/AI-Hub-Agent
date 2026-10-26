/**
 * Align Builder Engine - Inline WYSIWYG Editor
 * @package Align
 */

Vvveb.WysiwygEditor = {
	
	isActive: false,
	oldValue: '',
	doc:false,
	_blurHandler: null,
	

	editorSetStyle: function (tag, style = {}, toggle = false) {
		let iframeWindow = Vvveb.Builder.iframe.contentWindow;
		let selection = iframeWindow.getSelection();
		let element = this.element;
		let range;

		if (!tag) {
			tag = "span";
		}

		if (selection.rangeCount > 0) {
			//check if the whole text is inside an existing node to use the node directly
			if ((selection.baseNode && selection.baseNode.nextSibling == null && selection.baseNode.previousSibling == null 
				&& selection.anchorOffset == 0 && selection.focusOffset == selection.baseNode.length) 
				|| (selection.anchorOffset == selection.focusOffset)) {
					
				element = selection.baseNode.parentNode;
				
			} else {
				element = document.createElement(tag);
				range = selection.getRangeAt(0);
				
				try {
					range.surroundContents(element);
					range.selectNodeContents(element.childNodes[0], 0); 

				} catch (e) {
					let content = range.extractContents();

					element.appendChild(content);
					range.insertNode(element);

					range.selectNodeContents(element);
				}				
			}
		}
		
		if (element && style) {
			for (let name in style) {

				if ( !style[name] || 
					(toggle && element.style.getPropertyValue(name))) {

					element.style.removeProperty(name);
					
				} else {
					element.style.setProperty(name, style[name]);
				}
			}
		}
		
		//if edited text is an empty span remove the span
		if (element.tagName == "SPAN" && element.style.length == 0 && element.attributes.length <= 1) {
			let textNode = iframeWindow.document.createTextNode(element.innerText);
			element.replaceWith(textNode);
			element = textNode;

			range = iframeWindow.document.createRange();
			range.selectNodeContents(element);
			selection.removeAllRanges();
			selection.addRange(range);		
        }
		
		//select link element to edit link etc
		if (tag == "a") {
			Vvveb.Builder.selectNode(element);
			Vvveb.Builder.loadNodeComponent(element);
		}
		return element;
	},
	
	init: function(doc) {
		this.doc = doc;
		let self = this;
		
		let boldBtn = document.getElementById("bold-btn");
		if (boldBtn) {
			boldBtn.addEventListener("click", function (e) {
				self.editorSetStyle(false, {"font-weight" : "bold"}, true);
				e.preventDefault();
				return false;
			});
		}

		let italicBtn = document.getElementById("italic-btn");
		if (italicBtn) {
			italicBtn.addEventListener("click", function (e) {
				self.editorSetStyle(false, {"font-style" : "italic"}, true);
				e.preventDefault();
				return false;
			});
		}

		let underlineBtn = document.getElementById("underline-btn");
		if (underlineBtn) {
			underlineBtn.addEventListener("click", function (e) {
				self.editorSetStyle(false, {"text-decoration" : "underline"}, true);
				e.preventDefault();
				return false;
			});
		}
		
		let strikeBtn = document.getElementById("strike-btn");
		if (strikeBtn) {
			strikeBtn.addEventListener("click", function (e) {
				self.editorSetStyle(false,  {"text-decoration" : "line-through"}, true);
				e.preventDefault();
				return false;
			});
		}

		let linkBtn = document.getElementById("link-btn");
		if (linkBtn) {
			linkBtn.addEventListener("click", function (e) {
				self.editorSetStyle("a");
				e.preventDefault();
				return false;
			});
		}

		let foreColorEl = document.getElementById("fore-color");
		if (foreColorEl) {
			foreColorEl.addEventListener("change", function (e) {
				self.editorSetStyle(false, {"color" : this.value});
				e.preventDefault();
				return false;
			});
		}
		
		let backColorEl = document.getElementById("back-color");
		if (backColorEl) {
			backColorEl.addEventListener("change", function (e) {
				self.editorSetStyle(false, {"background-color" : this.value});
				e.preventDefault();
				return false;
			});
		}
		
		let fontSizeEl = document.getElementById("font-size");
		if (fontSizeEl) {
			fontSizeEl.addEventListener("change", function (e) {
				self.editorSetStyle(false, {"font-size" : this.value});
				e.preventDefault();
				return false;
			});

			let sizes = "<option value=''> - Font size - </option>";
			for (let i = 1; i <= 128; i++) {
				sizes += "<option value='"+ i +"px'>"+ i +"</option>";
			}
			fontSizeEl.innerHTML = sizes;		
		}

		let fontFamilyEl = document.getElementById("font-family");
		if (fontFamilyEl) {
			fontFamilyEl.addEventListener("change", function (e) {
				let option = this.options[this.selectedIndex];
				let element = self.editorSetStyle(false, {"font-family" : this.value});
				if (Vvveb.FontsManager && option) {
					Vvveb.FontsManager.addFont(option.dataset.provider, this.value, element);
				}
				e.preventDefault();
				return false;
			});
		}

		let justifyBtn = document.getElementById("justify-btn");
		if (justifyBtn) {
			justifyBtn.addEventListener("click", function (e) {
				self.editorSetStyle(false, {"text-align" : e.srcElement.dataset.value});
				e.preventDefault();
				return false;
			});
		}

		doc.addEventListener('keydown', event => {
			if (event.key === 'Enter') {
				let target = event.target.closest("[contenteditable]");
				if (target) {
					doc.execCommand('insertLineBreak');
					event.preventDefault();
				}
			} else if (event.key === 'Escape') {
				let target = event.target.closest("[contenteditable]") || self.element;
				if (target) {
					if (Vvveb.Builder?.contentMode && self.oldValue !== undefined) {
						target.innerHTML = self.oldValue;
					}
					target.blur();
					event.preventDefault();
				}
			} else if (event.key === 'Tab' && Vvveb.Builder?.contentMode && window.AlignContentModeEditor?.handleTab) {
				event.preventDefault();
				window.AlignContentModeEditor.handleTab(event.shiftKey);
			}
		});		
	},
	
	undo: function(element) {
		this.doc.execCommand('undo',false,null);
	},

	redo: function(element) {
		this.doc.execCommand('redo',false,null);
	},
	
	edit: function(element) {
		element.setAttribute("contenteditable", true);
		element.setAttribute("spellcheckker", false);
		element.setAttribute("spellcheck", false);

		let wysiwygEditor = document.getElementById("wysiwyg-editor");
		if (wysiwygEditor) {
			wysiwygEditor.style.display = "none";
		}

		this.element = element;
		this.isActive = true;
		this.oldValue = element.innerHTML;

		let fontFamilyEl = document.getElementById("font-family");
		if (fontFamilyEl && Vvveb.StyleManager) {
			fontFamilyEl.value = Vvveb.StyleManager.getStyle(element, 'font-family') || '';
		}

		let foreColorEl = document.getElementById("fore-color");
		if (foreColorEl && Vvveb.StyleManager) {
			foreColorEl.value = Vvveb.StyleManager.getStyle(element, 'color') || '';
		}

		let backColorEl = document.getElementById("back-color");
		if (backColorEl && Vvveb.StyleManager) {
			backColorEl.value = Vvveb.StyleManager.getStyle(element, 'background-color') || '';
		}

		if (this._blurHandler && this.element) {
			this.element.removeEventListener("blur", this._blurHandler);
			this._blurHandler = null;
		}

		this._blurHandler = () => {
			setTimeout(() => {
				let activeEl = document.activeElement;
				let toolbar = document.getElementById("word-bubble-toolbar");
				if (toolbar && toolbar.contains(activeEl)) {
					return;
				}
				this.destroy(element);
			}, 100);
		};
		element.addEventListener("blur", this._blurHandler, { once: true });

		element.focus();
	},

	destroy: function(targetElement) {
		let node = targetElement || this.element;

		if (this._blurHandler && node) {
			node.removeEventListener("blur", this._blurHandler);
			this._blurHandler = null;
		}

		if (node) {
			node.removeAttribute("contenteditable");
			node.removeAttribute("spellcheckker");
			node.removeAttribute("spellcheck");
		}

		let wysiwygEditor = document.getElementById("wysiwyg-editor");
		if (wysiwygEditor) {
			wysiwygEditor.style.display = "none";
		}
		if (typeof Vvveb.WordEditor !== "undefined" && Vvveb.WordEditor.hideBubbleToolbar) {
			Vvveb.WordEditor.hideBubbleToolbar();
		}
		this.isActive = false;

		if (node && this.oldValue !== undefined && node.innerHTML !== this.oldValue) {
			if (Vvveb.Undo) {
				Vvveb.Undo.addMutation({
					type: 'characterData',
					target: node,
					oldValue: this.oldValue,
					newValue: node.innerHTML
				});
			}
			try {
				window.dispatchEvent(new CustomEvent("align:text-edited", {
					detail: { target: node, oldValue: this.oldValue, newValue: node.innerHTML }
				}));
			} catch (e) {
				// Ignore event dispatch errors
			}
		}

		this.element = null;
		this.oldValue = '';

		if (Vvveb.Builder) {
			if (typeof Vvveb.Builder._cleanupTextEdit === "function") {
				Vvveb.Builder._cleanupTextEdit();
			}
			Vvveb.Builder.selectPadding = 0;
			Vvveb.Builder.texteditEl = null;
		}

		let selectBox = document.getElementById("select-box");
		if (selectBox) {
			selectBox.classList.remove("text-edit");
			selectBox.style.removeProperty("display");
		}

		let selectActions = document.getElementById("select-actions");
		if (selectActions) {
			selectActions.style.display = "";
		}
	}
}
