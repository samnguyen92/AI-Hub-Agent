/**
 * Align Agent - Chatbox Safe Markdown & UI Card Renderer
 *
 * Converts AI Markdown responses into sanitized HTML via marked + DOMPurify,
 * enforces strict tag/attribute allowlists, blocks javascript:/data: URLs,
 * adds rel="noopener noreferrer" target="_blank" to links, and builds
 * approval cards (with sandboxed iframe preview) and result/rollback cards.
 */
(function (window, document) {
	'use strict';

	var ALLOWED_TAGS = [
		'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'del', 'ins', 'mark', 'sub', 'sup', 'small', 'kbd', 'abbr',
		'code', 'pre', 'blockquote', 'ul', 'ol', 'li', 'dl', 'dt', 'dd',
		'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'hr', 'span', 'div',
		'table', 'caption', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
		'img', 'figure', 'figcaption', 'details', 'summary', 'input'
	];

	var ALLOWED_ATTR = [
		'href', 'title', 'target', 'rel', 'class', 'src', 'alt', 'width', 'height', 'loading',
		'colspan', 'rowspan', 'start', 'open', 'type', 'checked', 'disabled'
	];

	var hookInstalled = false;

	/**
	 * Ensure DOMPurify hook is registered once to enforce safe links and media.
	 */
	function ensurePurifyHook() {
		if (hookInstalled || !window.DOMPurify || typeof window.DOMPurify.addHook !== 'function') {
			return;
		}
		hookInstalled = true;

		window.DOMPurify.addHook('afterSanitizeAttributes', function (node) {
			var tag = node.tagName ? node.tagName.toLowerCase() : '';
			if (tag === 'a') {
				var href = (node.getAttribute('href') || '').trim();
				if (/^(javascript|data|vbscript):/i.test(href)) {
					node.removeAttribute('href');
				} else {
					node.setAttribute('target', '_blank');
					node.setAttribute('rel', 'noopener noreferrer');
				}
			} else if (tag === 'img') {
				var src = (node.getAttribute('src') || '').trim();
				if (/^(javascript|data|vbscript):/i.test(src) || !/^(https?:\/\/|\/)/i.test(src)) {
					if (node.parentNode) {
						node.parentNode.removeChild(node);
					} else {
						node.removeAttribute('src');
					}
				} else {
					node.setAttribute('loading', 'lazy');
					node.setAttribute('decoding', 'async');
					if (!node.getAttribute('alt')) {
						node.setAttribute('alt', '');
					}
				}
			} else if (tag === 'input') {
				var type = (node.getAttribute('type') || '').toLowerCase();
				if (type === 'checkbox') {
					node.setAttribute('disabled', 'disabled');
				} else if (node.parentNode) {
					node.parentNode.removeChild(node);
				}
			}
		});
	}

	/**
	 * Fallback HTML entity escaper if DOMPurify or marked is unavailable.
	 *
	 * @param {string} text Raw string.
	 * @returns {string} Escaped HTML string.
	 */
	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = String(text == null ? '' : text);
		return div.innerHTML;
	}

	/**
	 * Render Markdown text into strictly sanitized HTML.
	 *
	 * @param {string} markdownText Raw Markdown from user or assistant.
	 * @returns {string} Sanitized HTML safe for innerHTML assignment.
	 */
	function renderMarkdownSafe(markdownText) {
		var raw = String(markdownText == null ? '' : markdownText);
		if (!raw.trim()) {
			return '';
		}

		try {
			ensurePurifyHook();
			var parsedHtml = raw;
			if (window.marked && typeof window.marked.parse === 'function') {
				parsedHtml = window.marked.parse(raw, { breaks: true, gfm: true });
			} else {
				parsedHtml = '<p>' + escapeHtml(raw).replace(/\n/g, '<br>') + '</p>';
			}

			if (window.DOMPurify && typeof window.DOMPurify.sanitize === 'function') {
				return window.DOMPurify.sanitize(parsedHtml, {
					ALLOWED_TAGS: ALLOWED_TAGS,
					ALLOWED_ATTR: ALLOWED_ATTR,
					ALLOW_DATA_ATTR: false
				});
			}

			return '<p>' + escapeHtml(raw) + '</p>';
		} catch (err) {
			console.error('[Align UI] Markdown render error: ' + (err && err.message ? err.message : String(err)));
			return '<p>' + escapeHtml(raw) + '</p>';
		}
	}

	/**
	 * Build a message bubble DOM element.
	 *
	 * @param {string} role    'user' | 'assistant' | 'error'
	 * @param {string} content Message text (Markdown for assistant, plain/Markdown for user).
	 * @returns {HTMLElement}
	 */
	function createMessageElement(role, content) {
		var wrapper = document.createElement('div');
		var roleClass = 'align-chat-msg-assistant';
		if (role === 'user') {
			roleClass = 'align-chat-msg-user';
		} else if (role === 'error') {
			roleClass = 'align-chat-msg-error';
		}

		wrapper.className = 'align-chat-msg ' + roleClass;
		wrapper.setAttribute('data-role', role);

		if (role === 'user') {
			var text = String(content == null ? '' : content);
			var mediaRegex = /\[Attached Media:\s*attachment_id=(\d+),\s*url=([^,\s]+),\s*file=([^\]]+)\]/g;
			var match;
			var attachedMedia = [];
			while ((match = mediaRegex.exec(text)) !== null) {
				attachedMedia.push({
					id: match[1],
					url: match[2],
					filename: match[3]
				});
			}
			var cleanText = text.replace(mediaRegex, '').trim();
			if (cleanText) {
				var pUser = document.createElement('p');
				pUser.textContent = cleanText;
				wrapper.appendChild(pUser);
			}
			if (attachedMedia.length) {
				var attachWrap = document.createElement('div');
				attachWrap.className = 'align-chat-msg-attachments';
				attachedMedia.forEach(function (att) {
					if (/^(https?:\/\/|\/)/i.test(att.url)) {
						var thumbLink = document.createElement('a');
						thumbLink.href = att.url;
						thumbLink.target = '_blank';
						thumbLink.rel = 'noopener noreferrer';
						thumbLink.className = 'align-chat-msg-thumb';
						var img = document.createElement('img');
						img.src = att.url;
						img.alt = att.filename || '';
						img.width = 48;
						img.height = 48;
						img.loading = 'lazy';
						thumbLink.appendChild(img);
						attachWrap.appendChild(thumbLink);
					}
				});
				wrapper.appendChild(attachWrap);
			}
		} else if (role === 'error') {
			var pErr = document.createElement('p');
			pErr.textContent = String(content == null ? '' : content);
			wrapper.appendChild(pErr);
		} else {
			wrapper.innerHTML = renderMarkdownSafe(content);
			if (window.AlignChatboxEnhance && typeof window.AlignChatboxEnhance.enhance === 'function') {
				try {
					window.AlignChatboxEnhance.enhance(wrapper);
				} catch (enhErr) {
					console.error('[Align UI] Enhance error: ' + (enhErr && enhErr.message ? enhErr.message : String(enhErr)));
				}
			}
		}

		return wrapper;
	}

	/**
	 * Build a write-operation Approval Card DOM element with optional sandboxed iframe preview.
	 *
	 * @param {Object}   approval   Pending approval object ({pending_id, tool_name, summary}).
	 * @param {Object}   i18n       Localized UI strings.
	 * @param {Function} onDecision Callback invoked as onDecision(pendingId, 'approve'|'cancel', cardEl).
	 * @returns {HTMLElement}
	 */
	function createApprovalCard(approval, i18n, onDecision) {
		var summary = (approval && approval.summary) || {};
		var pendingId = (approval && approval.pending_id) || '';
		var card = document.createElement('div');
		card.className = 'align-chat-card align-chat-card-approval';
		card.setAttribute('data-pending-id', pendingId);

		if ((approval && approval.danger) || summary.danger) {
			card.classList.add('align-chat-card-danger');
			card.setAttribute('role', 'alert');
		}

		var titleEl = document.createElement('h3');
		titleEl.className = 'align-chat-card-title';
		titleEl.textContent = summary.title || (i18n && i18n.confirmAction) || 'Confirm Write Operation';
		card.appendChild(titleEl);

		var descEl = document.createElement('p');
		descEl.className = 'align-chat-card-desc';
		descEl.id = 'align-card-desc-' + pendingId;
		descEl.textContent = summary.description || '';
		card.appendChild(descEl);

		var actions = document.createElement('div');
		actions.className = 'align-chat-card-actions';

		var approveBtn = document.createElement('button');
		approveBtn.type = 'button';
		approveBtn.className = 'button button-primary';
		approveBtn.setAttribute('data-action', 'approve-write');
		approveBtn.setAttribute('data-step', pendingId);
		approveBtn.setAttribute('aria-describedby', descEl.id);
		var approveSpan = document.createElement('span');
		approveSpan.textContent = (i18n && i18n.approveBtn) || 'Approve';
		approveBtn.appendChild(approveSpan);

		var cancelBtn = document.createElement('button');
		cancelBtn.type = 'button';
		cancelBtn.className = 'button button-secondary';
		cancelBtn.setAttribute('data-action', 'cancel-write');
		cancelBtn.setAttribute('data-step', pendingId);
		cancelBtn.setAttribute('aria-describedby', descEl.id);
		var cancelSpan = document.createElement('span');
		cancelSpan.textContent = (i18n && i18n.cancelBtn) || 'Cancel';
		cancelBtn.appendChild(cancelSpan);

		actions.appendChild(approveBtn);
		actions.appendChild(cancelBtn);

		var previewWrap = null;
		var previewHtml = String(summary.preview_html || '').trim();
		var previewCss = String(summary.preview_css || '').trim();

		if (previewHtml !== '') {
			var toggleBtn = document.createElement('button');
			toggleBtn.type = 'button';
			toggleBtn.className = 'button';
			toggleBtn.setAttribute('data-action', 'toggle-preview');
			toggleBtn.setAttribute('aria-expanded', 'false');
			var toggleSpan = document.createElement('span');
			toggleSpan.textContent = (i18n && i18n.previewBtn) || 'Preview HTML';
			toggleBtn.appendChild(toggleSpan);
			actions.appendChild(toggleBtn);

			previewWrap = document.createElement('div');
			previewWrap.className = 'align-chat-preview-wrap';
			previewWrap.id = 'align-card-preview-' + pendingId;
			previewWrap.style.display = 'none';
			toggleBtn.setAttribute('aria-controls', previewWrap.id);

			toggleBtn.addEventListener('click', function () {
				var isHidden = previewWrap.style.display === 'none';
				if (isHidden && !previewWrap.querySelector('iframe')) {
					var iframe = document.createElement('iframe');
					iframe.className = 'align-chat-preview-iframe';
					iframe.setAttribute('sandbox', '');
					iframe.setAttribute('title', (i18n && i18n.previewIframeTitle) || 'Sandboxed HTML Preview');
					iframe.srcdoc = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>body{font-family:system-ui,-apple-system,sans-serif;margin:12px;}' + previewCss + '</style></head><body>' + previewHtml + '</body></html>';
					previewWrap.appendChild(iframe);
				}
				previewWrap.style.display = isHidden ? 'block' : 'none';
				toggleBtn.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
			});
		}

		var cssWrap = null;
		if (previewCss !== '') {
			var toggleCssBtn = document.createElement('button');
			toggleCssBtn.type = 'button';
			toggleCssBtn.className = 'button';
			toggleCssBtn.setAttribute('data-action', 'toggle-css');
			toggleCssBtn.setAttribute('aria-expanded', 'false');
			var toggleCssSpan = document.createElement('span');
			toggleCssSpan.textContent = (i18n && i18n.viewCssBtn) || 'View CSS';
			toggleCssBtn.appendChild(toggleCssSpan);
			actions.appendChild(toggleCssBtn);

			cssWrap = document.createElement('div');
			cssWrap.className = 'align-chat-css-wrap';
			cssWrap.id = 'align-card-css-' + pendingId;
			cssWrap.style.display = 'none';
			toggleCssBtn.setAttribute('aria-controls', cssWrap.id);
			var cssPre = document.createElement('pre');
			var cssCode = document.createElement('code');
			cssCode.className = 'language-css';
			cssCode.textContent = previewCss;
			cssPre.appendChild(cssCode);
			cssWrap.appendChild(cssPre);

			toggleCssBtn.addEventListener('click', function () {
				var isHidden = cssWrap.style.display === 'none';
				cssWrap.style.display = isHidden ? 'block' : 'none';
				toggleCssBtn.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
			});
		}

		var jsWrap = null;
		var previewJs = String(summary.preview_js || '').trim();
		var jsWarnings = Array.isArray(summary.js_warnings) ? summary.js_warnings : [];

		if (jsWarnings.length > 0) {
			var warnWrap = document.createElement('div');
			warnWrap.className = 'align-chat-js-warnings';
			warnWrap.setAttribute('role', 'alert');
			var warnTitle = document.createElement('strong');
			warnTitle.textContent = (i18n && i18n.jsWarningsTitle) || 'Security Warnings:';
			warnWrap.appendChild(warnTitle);
			var warnList = document.createElement('ul');
			jsWarnings.forEach(function (w) {
				var li = document.createElement('li');
				li.textContent = String(w);
				warnList.appendChild(li);
			});
			warnWrap.appendChild(warnList);
			card.appendChild(warnWrap);
		}

		if (previewJs !== '') {
			var toggleJsBtn = document.createElement('button');
			toggleJsBtn.type = 'button';
			toggleJsBtn.className = 'button';
			toggleJsBtn.setAttribute('data-action', 'toggle-js');
			toggleJsBtn.setAttribute('aria-expanded', 'false');
			var toggleJsSpan = document.createElement('span');
			toggleJsSpan.textContent = (i18n && i18n.viewJsBtn) || 'View JS';
			toggleJsBtn.appendChild(toggleJsSpan);
			actions.appendChild(toggleJsBtn);

			jsWrap = document.createElement('div');
			jsWrap.className = 'align-chat-js-wrap';
			jsWrap.id = 'align-card-js-' + pendingId;
			jsWrap.style.display = 'none';
			toggleJsBtn.setAttribute('aria-controls', jsWrap.id);
			var jsPre = document.createElement('pre');
			var jsCode = document.createElement('code');
			jsCode.className = 'language-javascript';
			jsCode.textContent = previewJs;
			jsPre.appendChild(jsCode);
			jsWrap.appendChild(jsPre);

			toggleJsBtn.addEventListener('click', function () {
				var isHidden = jsWrap.style.display === 'none';
				jsWrap.style.display = isHidden ? 'block' : 'none';
				toggleJsBtn.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
			});
		}

		card.appendChild(actions);
		if (previewWrap) {
			card.appendChild(previewWrap);
		}
		if (cssWrap) {
			card.appendChild(cssWrap);
		}
		if (jsWrap) {
			card.appendChild(jsWrap);
		}

		function handleDecision(decision) {
			approveBtn.disabled = true;
			cancelBtn.disabled = true;
			if (typeof onDecision === 'function') {
				onDecision(pendingId, decision, card);
			}
		}

		approveBtn.addEventListener('click', function () {
			handleDecision('approve');
		});

		cancelBtn.addEventListener('click', function () {
			handleDecision('cancel');
		});

		return card;
	}

	/**
	 * Build a Result Card DOM element with Page Link and Rollback button.
	 *
	 * @param {Object}   cardData   Result card data ({title, message, url, post_id, rollback_revision_id}).
	 * @param {Object}   i18n       Localized UI strings.
	 * @param {Function} onRollback Callback invoked as onRollback(postId, revisionId, btnEl).
	 * @returns {HTMLElement}
	 */
	function createResultCard(cardData, i18n, onRollback) {
		var data = cardData || {};
		var card = document.createElement('div');
		card.className = 'align-chat-card align-chat-card-result';

		var titleEl = document.createElement('h3');
		titleEl.className = 'align-chat-card-title';
		titleEl.textContent = data.title || 'Operation Completed';
		card.appendChild(titleEl);

		if (data.message) {
			var descEl = document.createElement('p');
			descEl.className = 'align-chat-card-desc';
			descEl.textContent = data.message;
			card.appendChild(descEl);
		}

		var actions = document.createElement('div');
		actions.className = 'align-chat-card-actions';

		if (data.url && /^https?:\/\//i.test(String(data.url))) {
			var viewLink = document.createElement('a');
			viewLink.className = 'button button-secondary';
			viewLink.href = String(data.url);
			viewLink.target = '_blank';
			viewLink.rel = 'noopener noreferrer';
			viewLink.setAttribute('data-action', 'view-page');
			var linkSpan = document.createElement('span');
			linkSpan.textContent = (i18n && i18n.viewPageBtn) || 'View Page';
			viewLink.appendChild(linkSpan);
			actions.appendChild(viewLink);
		}

		var postId = parseInt(data.post_id, 10) || 0;
		var revId = parseInt(data.rollback_revision_id, 10) || 0;
		if (postId > 0 && revId > 0) {
			var rollbackBtn = document.createElement('button');
			rollbackBtn.type = 'button';
			rollbackBtn.className = 'button button-danger';
			rollbackBtn.setAttribute('data-action', 'rollback-page');
			rollbackBtn.setAttribute('data-step', String(revId));
			var rbSpan = document.createElement('span');
			rbSpan.textContent = (i18n && i18n.rollbackBtn) || 'Rollback';
			rollbackBtn.appendChild(rbSpan);

			rollbackBtn.addEventListener('click', function () {
				if (typeof onRollback === 'function') {
					onRollback(postId, revId, rollbackBtn);
				}
			});
			actions.appendChild(rollbackBtn);
		}

		if (actions.children.length > 0) {
			card.appendChild(actions);
		}

		return card;
	}

	window.AlignChatboxRender = {
		renderMarkdownSafe: renderMarkdownSafe,
		createMessageElement: createMessageElement,
		createApprovalCard: createApprovalCard,
		createResultCard: createResultCard
	};
})(window, document);
