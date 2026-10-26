/**
 * Align Agent - Chatbox Content Enhancer
 *
 * Enhances rendered markdown bubbles: demotes headings (h1->h3, h2->h4),
 * wraps tables for horizontal scroll, adds language badges, copy buttons,
 * and sandboxed HTML preview to code blocks.
 */
(function (window, document) {
	'use strict';

	function demoteHeadings(rootEl) {
		var headings = rootEl.querySelectorAll('h1, h2');
		Array.prototype.forEach.call(headings, function (el) {
			var newTag = el.tagName.toLowerCase() === 'h1' ? 'h3' : 'h4';
			var newEl = document.createElement(newTag);
			for (var i = 0; i < el.attributes.length; i++) {
				var attr = el.attributes[i];
				newEl.setAttribute(attr.name, attr.value);
			}
			newEl.innerHTML = el.innerHTML;
			if (el.parentNode) {
				el.parentNode.replaceChild(newEl, el);
			}
		});
	}

	function wrapTables(rootEl) {
		var tables = rootEl.querySelectorAll('table');
		Array.prototype.forEach.call(tables, function (table) {
			if (table.parentNode && table.parentNode.classList.contains('align-chat-table-wrap')) {
				return;
			}
			var wrap = document.createElement('div');
			wrap.className = 'align-chat-table-wrap';
			table.parentNode.insertBefore(wrap, table);
			wrap.appendChild(table);
		});
		return tables.length;
	}

	function copyToClipboard(text, btn) {
		function showCopied() {
			var orig = btn.textContent;
			btn.textContent = 'Copied';
			setTimeout(function () { btn.textContent = orig; }, 1500);
		}

		if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
			navigator.clipboard.writeText(text).then(showCopied).catch(function () {
				fallbackCopy(text);
				showCopied();
			});
		} else {
			fallbackCopy(text);
			showCopied();
		}
	}

	function fallbackCopy(text) {
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.style.position = 'fixed';
		ta.style.opacity = '0';
		document.body.appendChild(ta);
		ta.select();
		try { document.execCommand('copy'); } catch (e) {}
		document.body.removeChild(ta);
	}

	function enhanceCodeBlocks(rootEl) {
		var codeBlocks = rootEl.querySelectorAll('pre > code');
		Array.prototype.forEach.call(codeBlocks, function (codeEl) {
			var preEl = codeEl.parentNode;
			if (!preEl || preEl.parentNode.classList.contains('align-chat-code')) {
				return;
			}

			var langMatch = (codeEl.className || '').match(/\blanguage-([a-zA-Z0-9_\-]+)\b/);
			var lang = langMatch ? langMatch[1].toLowerCase() : 'text';

			var container = document.createElement('div');
			container.className = 'align-chat-code';
			preEl.parentNode.insertBefore(container, preEl);

			var header = document.createElement('div');
			header.className = 'align-chat-code-header';

			var langBadge = document.createElement('span');
			langBadge.className = 'align-chat-code-lang';
			langBadge.textContent = lang;
			header.appendChild(langBadge);

			var actions = document.createElement('div');
			actions.className = 'align-chat-code-actions';

			if (lang === 'html') {
				var previewBtn = document.createElement('button');
				previewBtn.type = 'button';
				previewBtn.className = 'button align-chat-code-btn';
				previewBtn.setAttribute('data-action', 'preview-code');
				previewBtn.textContent = 'Preview';

				var previewWrap = null;
				previewBtn.addEventListener('click', function () {
					if (previewWrap) {
						var isHidden = previewWrap.style.display === 'none';
						previewWrap.style.display = isHidden ? 'block' : 'none';
						previewBtn.textContent = isHidden ? 'Hide Preview' : 'Preview';
						return;
					}
					previewWrap = document.createElement('div');
					previewWrap.className = 'align-chat-code-preview';
					var iframe = document.createElement('iframe');
					iframe.setAttribute('sandbox', '');
					iframe.srcdoc = codeEl.textContent;
					previewWrap.appendChild(iframe);
					container.appendChild(previewWrap);
					previewBtn.textContent = 'Hide Preview';
				});
				actions.appendChild(previewBtn);
			}

			var copyBtn = document.createElement('button');
			copyBtn.type = 'button';
			copyBtn.className = 'button align-chat-code-btn';
			copyBtn.setAttribute('data-action', 'copy-code');
			copyBtn.textContent = 'Copy';
			copyBtn.addEventListener('click', function () {
				copyToClipboard(codeEl.textContent, copyBtn);
			});
			actions.appendChild(copyBtn);

			header.appendChild(actions);
			container.appendChild(header);
			container.appendChild(preEl);
		});
		return codeBlocks.length;
	}

	function enhance(rootEl) {
		if (!rootEl) {
			return;
		}
		try {
			demoteHeadings(rootEl);
			var tablesCount = wrapTables(rootEl);
			var codeCount = enhanceCodeBlocks(rootEl);
			console.log('[Align UI] Enhanced message: ' + codeCount + ' code block(s), ' + tablesCount + ' table(s)');
		} catch (err) {
			console.error('[Align UI] Message enhance error: ' + (err && err.message ? err.message : String(err)));
		}
	}

	function attachUndo(targetEl, changeSet, requestApiFn, appendMsgFn) {
		if (!targetEl || !changeSet || !changeSet.undoable || !changeSet.id) {
			return null;
		}

		var row = document.createElement('div');
		row.className = 'align-chat-undo-row';
		row.style.marginTop = '8px';

		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'button align-chat-undo-btn';
		btn.setAttribute('data-action', 'undo-change-set');
		btn.setAttribute('aria-label', 'Undo last changes');
		btn.setAttribute('data-cs-id', changeSet.id);
		btn.style.height = '28px';
		btn.style.lineHeight = '26px';
		btn.style.fontSize = '12px';
		btn.style.padding = '0 10px';
		btn.textContent = (changeSet.count > 1) ? ('↩️ Undo all (' + changeSet.count + ')') : '↩️ Undo';

		async function runUndo(csId, buttonEl, force) {
			buttonEl.disabled = true;
			buttonEl.textContent = 'Undoing...';
			try {
				var res = await requestApiFn('/chat/undo', 'POST', { cs_id: csId, force: !!force });
				if (res && res.success) {
					buttonEl.textContent = '↩️ Undone (' + (res.reverted_count || 1) + ')';
					buttonEl.classList.add('is-undone');
					if (res.redo_cs_id) {
						var redoBtn = document.createElement('button');
						redoBtn.type = 'button';
						redoBtn.className = 'button button-secondary';
						redoBtn.style.height = '28px';
						redoBtn.style.lineHeight = '26px';
						redoBtn.style.fontSize = '12px';
						redoBtn.style.marginLeft = '8px';
						redoBtn.textContent = '↪️ Redo';
						redoBtn.addEventListener('click', function () { runUndo(res.redo_cs_id, redoBtn, false); });
						buttonEl.parentNode.appendChild(redoBtn);
					}
				} else if (res && res.error === 'conflict') {
					buttonEl.textContent = '⚠️ Force Undo';
					buttonEl.disabled = false;
					buttonEl.onclick = function () { runUndo(csId, buttonEl, true); };
					if (typeof appendMsgFn === 'function') {
						appendMsgFn('error', res.message || 'Live content modified. Tap Force Undo to overwrite.');
					}
				} else {
					buttonEl.disabled = false;
					buttonEl.textContent = 'Undo Failed';
					if (typeof appendMsgFn === 'function') {
						appendMsgFn('error', (res && res.message) ? res.message : 'Undo operation failed.');
					}
				}
			} catch (err) {
				buttonEl.disabled = false;
				buttonEl.textContent = 'Undo Failed';
				if (typeof appendMsgFn === 'function') {
					appendMsgFn('error', err && err.message ? err.message : 'Undo request failed.');
				}
			}
		}

		btn.addEventListener('click', function () {
			runUndo(changeSet.id, btn, false);
		});

		row.appendChild(btn);
		targetEl.appendChild(row);
		return row;
	}

	window.AlignChatboxEnhance = {
		enhance: enhance,
		attachUndo: attachUndo
	};
})(window, document);
