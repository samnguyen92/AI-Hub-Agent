/**
 * Align Builder Engine - Custom JavaScript Manager
 * @package Align
 */

Vvveb.JsManager = {
	scriptContainer: false,
	doc: false,

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
			return 'script-' + prefix + '-' + tpSlug;
		}
		if (postId) {
			let isBlock = (pageData?.folder === 'Reusable Blocks' || pageData?.type === 'Reusable Blocks' || (pageData?.name && String(pageData.name).startsWith('block_')));
			let entitySlug = slug || (window.VvvebWpConfig?.activePostSlug) || postId;
			let prefix = isBlock ? 'block' : 'page';
			return 'script-' + prefix + '-' + entitySlug;
		}
		return 'vvvebjs-scripts';
	},

	getScriptElement: function(doc) {
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
				let legacyId = (isBlock ? 'script-block-' : 'script-page-') + postId;
				let oldLegacyId = (isBlock ? 'align-script-block-' : 'align-script-page-') + postId;
				el = doc.getElementById(legacyId) || doc.getElementById(oldLegacyId);
			}
		}
		if (!el) {
			el = doc.getElementById('vvvebjs-scripts') || doc.querySelector('script[id^="script-"], script[id^="align-script-"]');
		}
		return el || null;
	},
	
	init: function(doc) {
		doc = doc || this.doc || window.FrameDocument || (Vvveb.Builder && Vvveb.Builder.iframe && Vvveb.Builder.iframe.contentWindow ? Vvveb.Builder.iframe.contentWindow.document : null);
		if (doc) {
			this.doc = doc;
			let script = this.getScriptElement(doc);
			let scopedId = this.getScopedId();
			console.log('%c[Align JsManager] init() called | script tag exists:', 'color: #a855f7;', !!script, script ? `(id: #${script.id}, JS length: ${script.textContent.trim().length})` : `❌ NOT FOUND (target: #${scopedId})`);
			if (script && script.textContent && script.textContent.trim()) {
				script.id = scopedId;
				if (doc.head && script.parentNode !== doc.head) {
					doc.head.append(script);
				}
				this.scriptContainer = script;
			} else {
				if (script && script.parentNode && (script.id === scopedId || script.id === 'align-' + scopedId || script.id === 'vvvebjs-scripts')) script.remove();
				this.scriptContainer = false;
			}
			return this.scriptContainer;
		}
	},
	
	setJs: function(js) {
		let doc = this.doc || window.FrameDocument || (Vvveb.Builder && Vvveb.Builder.iframe && Vvveb.Builder.iframe.contentWindow ? Vvveb.Builder.iframe.contentWindow.document : null);
		const cleanJs = (js || '').trim();
		console.log('%c[Align JsManager] setJs() called | incoming JS len:', 'color: #a855f7;', cleanJs.length, cleanJs.length === 0 ? '⚠️ (WIPING JS TO EMPTY!)' : '');
		if (doc) {
			this.doc = doc;
			let script = this.getScriptElement(doc);
			let scopedId = this.getScopedId();
			if (!cleanJs) {
				if (script && script.parentNode && (script.id === scopedId || script.id === 'align-' + scopedId || script.id === 'vvvebjs-scripts')) {
					script.remove();
				}
				this.scriptContainer = false;
				return;
			}
			let newScript = doc.createElement('script');
			newScript.id = scopedId;
			newScript.type = 'text/javascript';
			newScript.textContent = cleanJs;
			if (script && script.parentNode) {
				script.remove();
			}
			if (doc.head) {
				doc.head.append(newScript);
			} else if (doc.body) {
				doc.body.append(newScript);
			}
			this.scriptContainer = newScript;
		}
	},

	getJs: function() {
		let doc = window.FrameDocument || this.doc || (Vvveb.Builder && Vvveb.Builder.iframe && Vvveb.Builder.iframe.contentWindow ? Vvveb.Builder.iframe.contentWindow.document : null);
		if (doc) {
			let script = this.getScriptElement(doc);
			if (script && typeof script.textContent === "string") {
				this.scriptContainer = script;
				return script.textContent.trim();
			}
		}
		if (this.scriptContainer && typeof this.scriptContainer.textContent === "string") {
			return this.scriptContainer.textContent.trim();
		}
		return '';
	}
};


function displayToast(type, title, message, position = 'bottom', id = null) {
	if (!id) {
		id = position + "-toast";
	}
	
	let toast = document.getElementById(id);
	let header = toast.querySelector(".toast-header");
	toast.classList.remove("bottom-0", "top-0");
	toast.classList.add(position + "-0");
	toast.querySelector(".toast-body .message").innerHTML = message;
	header.classList.remove("danger", "success");
	header.classList.add("bg-" + type);	
	header.querySelector("strong").textContent = title;
	let toastDisplay = toast.cloneNode(true);
	toast.parentNode.appendChild(toastDisplay);
	
	let delay = 3000;
	if (type == "danger") {
		delay = 10000;
	}

	let bsToast = new bootstrap.Toast(toastDisplay, {animation:true, delay});
	toastDisplay.addEventListener('hidden.bs.toast', () => {
		toastDisplay.remove();
	});
    bsToast.show();
}
