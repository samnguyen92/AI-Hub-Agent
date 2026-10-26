/**
 * Align Builder - Link Picker & Ajax Search Control
 * @package Align
 */

let LinkInput = { ...Input, ...{
	debounceTimer: null,
	currentPost: null,

	events: [
		["input", "onSearchInput", "input.link-url-input"],
		["change", "onManualChange", "input.link-url-input"],
		["click", "onItemSelect", ".link-search-item"],
		["click", "onDisconnect", ".btn-disconnect"],
	],

	toRelativeUrl: function(url) {
		if (!url || typeof url !== "string") return "/";
		let trimmed = url.trim();
		if (!trimmed) return "/";
		if (trimmed.startsWith("#") || trimmed.startsWith("mailto:") || trimmed.startsWith("tel:") || trimmed.startsWith("javascript:")) {
			return trimmed;
		}
		if (trimmed.startsWith("/#")) {
			return trimmed.substring(1);
		}
		try {
			let currentOrigin = window.location.origin;
			let parsed = new URL(trimmed, currentOrigin);
			// External link: preserve full URL if origin differs from current site origin
			if (parsed.origin !== currentOrigin) {
				return trimmed;
			}
			return (parsed.pathname || "/") + (parsed.search || "") + (parsed.hash || "");
		} catch (e) {
			return trimmed;
		}
	},

	getContainer: function(node, input, targetEl) {
		if (node && node.querySelector) return node;
		if (targetEl && targetEl.closest) {
			let found = targetEl.closest(".vvveb-link-input-wrapper");
			if (found) return found;
		}
		if (input && input.element) {
			return input.element[0] || input.element;
		}
		if (this && this.element) {
			return this.element[0] || this.element;
		}
		return null;
	},

	getTargetElement: function(node) {
		let el = null;
		if (node && node.nodeType === 1 && !node.classList?.contains("vvveb-link-input-wrapper") && !node.closest?.(".vvveb-link-input-wrapper")) {
			el = node;
		} else if (window.Vvveb && window.Vvveb.Builder && window.Vvveb.Builder.selectedEl) {
			el = window.Vvveb.Builder.selectedEl;
		}

		if (!el) return null;

		// 1. Unwrap jQuery or Array-like collections
		if (el.jquery || Array.isArray(el)) {
			el = el[0];
		} else if (el[0] && el[0].nodeType) {
			el = el[0];
		}

		// 2. If TextNode or CommentNode, get parent HTMLElement
		if (el && el.nodeType === 3 && el.parentElement) {
			el = el.parentElement;
		}

		// 3. If selector string, query from iframe or document
		if (typeof el === "string") {
			const frameDoc = window.Vvveb?.Builder?.frameDoc || window.FrameDocument || document;
			el = frameDoc ? frameDoc.querySelector(el) : null;
		}

		// 4. Validate HTMLElement and check for anchor context
		if (el && typeof el.setAttribute === "function") {
			if (el.tagName !== "A" && el.closest) {
				const anchor = el.closest("a");
				if (anchor && typeof anchor.setAttribute === "function") {
					return anchor;
				}
			}
			return el;
		}

		return null;
	},

	onSearchInput: function(event, node, input) {
		let searchVal = this.value ? this.value.trim() : "";
		let container = LinkInput.getContainer(node, input, this);
		if (!container) return;

		let resultsContainer = container.querySelector(".link-search-results");

		clearTimeout(LinkInput.debounceTimer);

		if (!searchVal || searchVal.startsWith("http://") || searchVal.startsWith("https://") || searchVal.startsWith("#") || searchVal.startsWith("/#") || searchVal.startsWith("mailto:") || searchVal.startsWith("tel:")) {
			if (resultsContainer) {
				resultsContainer.style.display = "none";
				resultsContainer.innerHTML = "";
			}
			return;
		}

		LinkInput.debounceTimer = setTimeout(() => {
			LinkInput.performSearch(searchVal, resultsContainer, container);
		}, 300);
	},

	performSearch: function(keyword, resultsContainer, container) {
		if (!resultsContainer) return;

		let restNonce = (window.VvvebWpConfig && window.VvvebWpConfig.restNonce) ? window.VvvebWpConfig.restNonce : "";
		let url = "";

		if (window.VvvebWpConfig && window.VvvebWpConfig.restSearchLinksUrl) {
			let base = window.VvvebWpConfig.restSearchLinksUrl;
			url = base + (base.indexOf("?") > -1 ? "&" : "?") + "search=" + encodeURIComponent(keyword);
		} else {
			let restBase = (window.VvvebWpConfig && window.VvvebWpConfig.restUrl) ? window.VvvebWpConfig.restUrl : "/wp-json/align-builder/v1/";
			url = restBase + (restBase.endsWith("/") ? "" : "/") + "search-links?search=" + encodeURIComponent(keyword);
			if (restNonce) {
				url += "&_wpnonce=" + encodeURIComponent(restNonce);
			}
		}

		resultsContainer.style.display = "block";
		resultsContainer.innerHTML = '<div class="p-2 text-muted small text-center link-search-loading"><i class="la la-spinner la-spin me-1"></i>Searching posts...</div>';

		fetch(url, {
			headers: {
				"X-WP-Nonce": restNonce
			},
			credentials: "same-origin"
		})
		.then(res => {
			if (!res.ok) {
				throw new Error("HTTP error " + res.status);
			}
			return res.json();
		})
		.then(data => {
			if (!Array.isArray(data) || data.length === 0) {
				resultsContainer.innerHTML = '<div class="p-2 text-muted small text-center link-search-empty"><i class="la la-info-circle me-1 opacity-75"></i>No posts found</div>';
				return;
			}

			resultsContainer.innerHTML = "";
			data.forEach(item => {
				let btn = document.createElement("button");
				btn.type = "button";
				btn.className = "dropdown-item link-search-item";

				// Top Row: Title & Badges
				let topRow = document.createElement("div");
				topRow.className = "link-search-row-top";

				let titleSpan = document.createElement("div");
				titleSpan.className = "link-search-item-title";
				let itemTitle = item.title || "Untitled";
				titleSpan.textContent = itemTitle;
				titleSpan.title = itemTitle;

				let badgesDiv = document.createElement("div");
				badgesDiv.className = "link-search-badges";

				let badge = document.createElement("span");
				badge.className = "badge link-search-type-badge";
				badge.textContent = item.type_label || item.type;

				let idBadge = document.createElement("span");
				idBadge.className = "badge link-search-id-badge";
				idBadge.textContent = "#" + item.id;

				badgesDiv.appendChild(badge);
				badgesDiv.appendChild(idBadge);

				topRow.appendChild(titleSpan);
				topRow.appendChild(badgesDiv);

				// Bottom Row: Icon & URL
				let bottomRow = document.createElement("div");
				bottomRow.className = "link-search-row-bottom";

				let urlIcon = document.createElement("i");
				urlIcon.className = "la la-link link-search-url-icon";

				let urlSpan = document.createElement("div");
				urlSpan.className = "link-search-item-url";
				let itemUrl = LinkInput.toRelativeUrl(item.url || "");
				urlSpan.textContent = itemUrl;
				urlSpan.title = itemUrl;

				bottomRow.appendChild(urlIcon);
				bottomRow.appendChild(urlSpan);

				btn.appendChild(topRow);
				btn.appendChild(bottomRow);

				btn.dataset.postId = item.id;
				btn.dataset.postTitle = itemTitle;
				btn.dataset.postUrl = itemUrl;

				resultsContainer.appendChild(btn);
			});
		})
		.catch(err => {
			console.error("[Align Builder] Link search error:", err);
			resultsContainer.innerHTML = '<div class="p-2 text-danger small text-center link-search-error"><i class="la la-exclamation-circle me-1"></i>Error searching links</div>';
		});
	},

	onItemSelect: function(event, node, input) {
		let btn = this.closest(".link-search-item");
		if (!btn) return;

		let postId = btn.dataset.postId;
		let postTitle = btn.dataset.postTitle;
		let postUrl = LinkInput.toRelativeUrl(btn.dataset.postUrl || "");

		console.log("[Align Builder] LinkInput: Selected post #" + postId + " (" + postTitle + ") -> " + postUrl);

		let container = LinkInput.getContainer(node, input, this);

		let targetEl = LinkInput.getTargetElement(node);
		if (targetEl && typeof targetEl.setAttribute === "function") {
			targetEl.setAttribute("data-align-link-id", postId);
			targetEl.setAttribute("data-align-link-title", postTitle);
			targetEl.setAttribute("href", postUrl);

			if (window.Vvveb?.Undo?.addMutation) {
				window.Vvveb.Undo.addMutation({
					type: 'attributes',
					target: targetEl,
					attributeName: 'data-align-link-id',
					oldValue: null,
					newValue: postId
				});
			}
		} else {
			console.warn("[Align Builder] LinkInput: target element is not an HTMLElement or setAttribute is unavailable, direct attribute update skipped");
		}

		if (input) input.value = postUrl;
		LinkInput.setLockedView(postId, postTitle, container);

		if (container) {
			let resultsContainer = container.querySelector(".link-search-results");
			if (resultsContainer) {
				resultsContainer.style.display = "none";
				resultsContainer.innerHTML = "";
			}

			const customEvent = new CustomEvent("propertyChange", {
				detail: { value: postUrl, input: input || LinkInput, origEvent: event },
				bubbles: true
			});
			container.dispatchEvent(customEvent);
		}

		if (window.AlignBuilderModules?.WpSaveHandler?.setButtonState) {
			window.AlignBuilderModules.WpSaveHandler.setButtonState("dirty");
		}
	},

	onManualChange: function(event, node, input) {
		let rawVal = this.value ? this.value.trim() : "";
		let val = rawVal ? LinkInput.toRelativeUrl(rawVal) : "";
		if (this.value !== val) {
			this.value = val;
		}

		console.log("[Align Builder] LinkInput: Manual change normalized URL:", rawVal, "->", val);

		let targetEl = LinkInput.getTargetElement(node);

		if (targetEl) {
			if (typeof targetEl.removeAttribute === "function") {
				targetEl.removeAttribute("data-align-link-id");
				targetEl.removeAttribute("data-align-link-title");
			}
			if (typeof targetEl.setAttribute === "function") {
				targetEl.setAttribute("href", val);
			}
		}

		if (input) input.value = val;

		if (window.AlignBuilderModules?.WpSaveHandler?.setButtonState) {
			window.AlignBuilderModules.WpSaveHandler.setButtonState("dirty");
		}

		if (input && input.onChange) {
			return input.onChange.call(this, event, node, input);
		}
	},

	onDisconnect: function(event, node, input) {
		let targetEl = LinkInput.getTargetElement(node);
		let currentHref = "";

		if (targetEl) {
			if (typeof targetEl.getAttribute === "function") {
				currentHref = targetEl.getAttribute("href") || "";
			}
			if (typeof targetEl.removeAttribute === "function") {
				targetEl.removeAttribute("data-align-link-id");
				targetEl.removeAttribute("data-align-link-title");
			}

			if (window.Vvveb?.Undo?.addMutation) {
				window.Vvveb.Undo.addMutation({
					type: 'attributes',
					target: targetEl,
					attributeName: 'data-align-link-id',
					oldValue: targetEl.getAttribute("data-align-link-id") || null,
					newValue: null
				});
			}
		}

		let container = LinkInput.getContainer(node, input, this);
		LinkInput.setUnlockedView(currentHref, container);

		if (window.AlignBuilderModules?.WpSaveHandler?.setButtonState) {
			window.AlignBuilderModules.WpSaveHandler.setButtonState("dirty");
		}

		if (container) {
			const customEvent = new CustomEvent("propertyChange", {
				detail: { value: currentHref, input: input || LinkInput, origEvent: event }
			});
			container.dispatchEvent(customEvent);

			let inputEl = container.querySelector("input.link-url-input");
			if (inputEl) {
				inputEl.focus();
			}
		}
	},

	setLockedView: function(postId, title, container) {
		container = container || LinkInput.getContainer(null, this);
		if (!container) return;

		let unlockedDiv = container.querySelector(".link-unlocked-container");
		let lockedDiv = container.querySelector(".link-locked-container");
		let titleEl = container.querySelector(".locked-title") || container.querySelector(".item-title");
		let idValueEl = container.querySelector(".locked-id-value");
		let idBadgeEl = container.querySelector(".link-locked-id-badge") || container.querySelector(".locked-id-badge");

		let displayTitle = (title && title.trim()) ? title.trim() : ("Post #" + postId);
		if (titleEl) {
			titleEl.textContent = displayTitle;
			if (typeof titleEl.setAttribute === "function") {
				titleEl.setAttribute("title", displayTitle);
			}
		}
		if (idValueEl) {
			idValueEl.textContent = postId ? postId : "";
		} else if (idBadgeEl) {
			idBadgeEl.textContent = "#" + (postId || "");
		}
		if (idBadgeEl && typeof idBadgeEl.setAttribute === "function") {
			idBadgeEl.setAttribute("title", "Post ID: " + postId);
		}

		if (unlockedDiv) unlockedDiv.style.display = "none";
		if (lockedDiv) lockedDiv.style.display = "block";
	},

	setUnlockedView: function(value, container) {
		container = container || LinkInput.getContainer(null, this);
		if (!container) return;

		let unlockedDiv = container.querySelector(".link-unlocked-container");
		let lockedDiv = container.querySelector(".link-locked-container");
		let inputEl = container.querySelector("input.link-url-input");

		if (inputEl) inputEl.value = value || "";
		if (unlockedDiv) unlockedDiv.style.display = "block";
		if (lockedDiv) lockedDiv.style.display = "none";
	},

	setValue: function(value) {
		let container = this.element ? (this.element[0] || this.element) : null;
		let targetEl = LinkInput.getTargetElement();
		if (targetEl && typeof targetEl.hasAttribute === "function" && targetEl.hasAttribute("data-align-link-id")) {
			let postId = targetEl.getAttribute("data-align-link-id");
			let title = (typeof targetEl.getAttribute === "function" ? targetEl.getAttribute("data-align-link-title") : "") || targetEl.textContent?.trim() || ("Post #" + postId);
			this.setLockedView(postId, title, container);
		} else {
			this.setUnlockedView(value, container);
		}
	},

	init: function(data, node) {
		data = Object.assign({}, data || {});
		let targetEl = LinkInput.getTargetElement(node);
		if (targetEl && typeof targetEl.hasAttribute === "function" && targetEl.hasAttribute("data-align-link-id")) {
			data.is_locked = true;
			data.linkId = targetEl.getAttribute("data-align-link-id");
			data.title = (typeof targetEl.getAttribute === "function" ? targetEl.getAttribute("data-align-link-title") : "") || targetEl.textContent?.trim() || ("Post #" + data.linkId);
		} else {
			data.is_locked = false;
			delete data.linkId;
			delete data.title;
		}
		return this.render("linkinput", data);
	}
}};

if (typeof window !== "undefined") {
	window.LinkInput = LinkInput;
}

// Global outside click handler for link search results
if (typeof document !== "undefined") {
	document.addEventListener("click", function(e) {
		if (!e.target.closest || !e.target.closest(".vvveb-link-input-wrapper")) {
			document.querySelectorAll(".link-search-results").forEach(function(el) {
				el.style.display = "none";
			});
		}
	});
}
