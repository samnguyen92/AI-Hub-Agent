/**
 * Align Builder - Text & Textarea Input Controls
 * @package Align
 */

let TextInput = { ...Input, ...{

    events: [
        // event, listener, child element
        ["input", "onChange", "input"],
        ["keyup", "onChange", "input"],
        ["change", "onChange", "input"],
        ["focusout", "onChange", "input"],
	 ],
	
	init: function(data) {
		return this.render("textinput", data);
	},
  }
}

let TextareaInput = { ...Input, ...{

    events: [
        ["input", "onChange", "textarea"],
        ["keyup", "onChange", "textarea"],
        ["change", "onChange", "textarea"],
        ["focusout", "onChange", "textarea"],
	 ],

	autoGrow: function(textarea) {
		if (!textarea) return;
		textarea.style.height = "auto";
		textarea.style.height = `${Math.max(textarea.scrollHeight, 68)}px`;
	},

	isInlineOnlyNode: function(node) {
		if (!node || node.nodeType !== 1) return true;
		const allowedTags = new Set(["B", "STRONG", "I", "EM", "A", "BR", "SPAN", "SMALL", "U", "S", "MARK"]);
		for (let i = 0; i < node.children.length; i++) {
			const child = node.children[i];
			if (!allowedTags.has(child.tagName)) return false;
			if (!this.isInlineOnlyNode(child)) return false;
		}
		return true;
	},

	hasInlineFormattingTags: function(html) {
		if (!html || typeof html !== "string") return false;
		return /<\/?(b|strong|i|em|a|span|small|u|s|mark|br)\b[^>]*>/i.test(html);
	},

	showFormattingNotice: function(container, node) {
		if (!container) return;
		let notice = container.querySelector(".align-textarea-formatting-notice");
		if (!notice) {
			notice = document.createElement("div");
			notice.className = "align-textarea-formatting-notice";

			const warnP = document.createElement("p");
			warnP.className = "align-textarea-warning-text";
			warnP.textContent = "This text contains inline formatting. Editing plain text may overwrite tags.";

			const editBtn = document.createElement("button");
			editBtn.type = "button";
			editBtn.className = "align-textarea-edit-btn";
			editBtn.textContent = "Edit formatting on canvas";
			editBtn.addEventListener("click", () => {
				const target = this.targetNode || node || (Vvveb.Builder && Vvveb.Builder.selectedEl);
				if (target) {
					if (typeof target.scrollIntoView === "function") {
						try { target.scrollIntoView({ block: "center", behavior: "smooth" }); } catch (e) { target.scrollIntoView(false); }
					}
					if (Vvveb.Builder && typeof Vvveb.Builder.startTextEdit === "function") {
						Vvveb.Builder.startTextEdit(target);
					}
				}
			});

			notice.appendChild(warnP);
			notice.appendChild(editBtn);
			container.appendChild(notice);
		}
	},

	setValue: function(value) {
		let el = this.element && (this.element[0] || this.element);
		if (el && el.querySelector) {
			let input = el.querySelector('textarea');
			if (input) { 
				let targetNode = this.targetNode || (Vvveb.Builder && Vvveb.Builder.selectedEl);
				let isContentMode = !!(Vvveb.Builder && Vvveb.Builder.contentMode);

				if (targetNode && isContentMode && targetNode.children.length > 0 && this.isInlineOnlyNode(targetNode)) {
					this.initialHadFormatting = true;
					this.initialHtml = targetNode.innerHTML;
					input.value = targetNode.innerText ?? "";
					this.showFormattingNotice(el, targetNode);
				} else {
					input.value = (value !== undefined && value !== null) ? value : "";
					let notice = el.querySelector(".align-textarea-formatting-notice");
					if (notice) notice.remove();
				}

				this.autoGrow(input);
			}
		}
	},

	onChange: function(event, node, input) {
		let textarea = input && input.querySelector ? input.querySelector('textarea') : input;
		if (textarea) this.autoGrow(textarea);

		let targetNode = this.targetNode || (Vvveb.Builder && Vvveb.Builder.selectedEl);
		let isContentMode = !!(Vvveb.Builder && Vvveb.Builder.contentMode);

		if (isContentMode && this.initialHadFormatting) {
			let el = this.element && (this.element[0] || this.element);
			this.showFormattingNotice(el, targetNode);
		}

		if (event && event.target) {
			const e = new CustomEvent('propertyChange', {
				detail: {
					value: textarea ? textarea.value : (this.value ?? ""),
					input: this,
					origEvent: event
				}
			});
			event.currentTarget.dispatchEvent(e);
		}
	},
	
	init: function(data, node) {
		this.targetNode = node || (Vvveb.Builder && Vvveb.Builder.selectedEl) || null;
		this.initialHadFormatting = false;
		this.initialHtml = "";

		let el = this.render("textareainput", data);
		let textarea = el ? el.querySelector('textarea') : null;
		if (textarea) {
			textarea.addEventListener("input", () => this.autoGrow(textarea));
			setTimeout(() => this.autoGrow(textarea), 0);
		}
		return el;
	},
  }	
}

let ValueTextInput = { ...TextInput, ...{

    events: [
        ["input", "onChange", "input"],
        ["keyup", "onChange", "input"],
        ["change", "onChange", "input"],
        ["focusout", "onChange", "input"],
	 ],
	
	init: function(data) {
		return this.render("textinput", data);
	},
  }
}

let TextValueInput = { ...Input, ...{
	

    events: [
        ["input", "onChange", "input"],
        ["keyup", "onChange", "input"],
        ["change", "onChange", "input"],
        ["focusout", "onChange", "input"],
	    ["click", "onChange", "button" /*'select'*/],
	 ],
	
	setValue: function(value) {
		return false;
	},
		
	init: function(data) {
		return this.render("textvalue", data);
	},
  }
}
