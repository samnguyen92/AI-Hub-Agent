/**
 * Align Agent - Client-Driven Chatbox Controller
 *
 * Manages the multi-turn tool calling loop (/chat/step, /chat/approve,
 * /chat/history, /chat/reset), enforces the MAX_CONTINUE_TURNS continuation limit,
 * wraps all fetch and JSON.parse calls in try...catch, and logs with [Align UI].
 */
document.addEventListener('DOMContentLoaded', function () {
	'use strict';

	var MAX_CONTINUE_TURNS = 20;
	var chatboxEl, messagesEl, emptyEl, thinkingBubbleEl, formEl, inputEl, sendBtnEl, clearBtnEl, fabEl, backdropEl, closeBtnEl;
	var isOpen = false, hasLoadedHistory = false, isLoadingHistory = false;

	try {
		chatboxEl  = document.getElementById('align-agent-chatbox');
		messagesEl = document.getElementById('align-chatbox-messages');
		emptyEl    = document.getElementById('align-chatbox-empty');
		formEl     = document.getElementById('align-chatbox-form');
		inputEl    = document.getElementById('align-chatbox-input');
		sendBtnEl  = document.getElementById('align-chatbox-send-btn');
		clearBtnEl = document.getElementById('align-chatbox-clear-btn');
		fabEl      = document.getElementById('align-chatbox-fab');
		backdropEl = document.getElementById('align-chatbox-backdrop');
		closeBtnEl = document.getElementById('align-chatbox-close-btn');
	} catch (domErr) {
		console.error('[Align UI] Failed to query chatbox DOM elements: ' + (domErr && domErr.message ? domErr.message : String(domErr)));
		return;
	}

	if (!chatboxEl || !messagesEl || !formEl || !inputEl) return;

	var config         = window.alignChatboxData || {};
	var restUrl        = String(config.restUrl || '/wp-json/align-agent/v1').replace(/\/+$/, '');
	var restNonce      = String(config.restNonce || '');
	var i18n           = config.i18n || {};
	var conversationId = '';
	var isBusy         = false;
	var renderer       = window.AlignChatboxRender;

	function setBusyState(busy, statusLabel) {
		isBusy = !!busy;
		var disabled = isBusy || isLoadingHistory;
		if (sendBtnEl) sendBtnEl.disabled = disabled;
		if (clearBtnEl) clearBtnEl.disabled = disabled;
		if (inputEl) inputEl.disabled = disabled;

		if (busy && statusLabel) {
			hideEmptyPlaceholder();
			if (!thinkingBubbleEl) {
				thinkingBubbleEl = document.createElement('div');
				thinkingBubbleEl.className = 'align-chat-msg align-chat-msg-assistant align-chat-msg-thinking';
				thinkingBubbleEl.setAttribute('role', 'status');
				thinkingBubbleEl.setAttribute('aria-live', 'polite');
				thinkingBubbleEl.innerHTML = '<span class="align-chatbox-spinner" aria-hidden="true"></span><span class="align-chatbox-thinking-text"></span>';
			}
			var thinkingTextEl = thinkingBubbleEl.querySelector('.align-chatbox-thinking-text');
			if (thinkingTextEl) thinkingTextEl.textContent = statusLabel;
			if (messagesEl && thinkingBubbleEl.parentNode !== messagesEl) messagesEl.appendChild(thinkingBubbleEl);
			scrollToBottom();
		} else if (!busy) {
			if (thinkingBubbleEl && thinkingBubbleEl.parentNode) thinkingBubbleEl.parentNode.removeChild(thinkingBubbleEl);
			thinkingBubbleEl = null;
		}
	}

	function hideEmptyPlaceholder() {
		if (emptyEl && emptyEl.parentNode) emptyEl.style.display = 'none';
	}

	function scrollToBottom() {
		try { messagesEl.scrollTop = messagesEl.scrollHeight; } catch (e) {}
	}

	function appendMessage(role, text) {
		if (!renderer || !text) return;
		hideEmptyPlaceholder();
		messagesEl.appendChild(renderer.createMessageElement(role, text));
		scrollToBottom();
	}

	function appendContinueNotice(text) {
		hideEmptyPlaceholder();
		if (window.AlignChatboxContinue && typeof window.AlignChatboxContinue.appendContinueBubble === 'function') {
			window.AlignChatboxContinue.appendContinueBubble(messagesEl, text, i18n);
		} else {
			appendMessage('error', text);
		}
	}

	function appendApprovalCard(approval) {
		if (!renderer || !approval) return;
		hideEmptyPlaceholder();
		messagesEl.appendChild(renderer.createApprovalCard(approval, i18n, handleApprovalDecision));
		scrollToBottom();
	}

	function appendResultCard(cardData) {
		if (!renderer || !cardData) return;
		hideEmptyPlaceholder();
		messagesEl.appendChild(renderer.createResultCard(cardData, i18n, handleDirectRollback));
		scrollToBottom();
	}

	/**
	 * Execute a REST request to align-agent/v1 with try...catch around fetch & JSON.parse.
	 *
	 * @param {string} path   Endpoint path (e.g. '/chat/step').
	 * @param {string} method HTTP method ('GET' or 'POST').
	 * @param {Object} body   Optional JSON body payload.
	 * @returns {Promise<Object>}
	 */
	async function requestChatApi(path, method, body) {
		var options = {
			method: method || 'GET',
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': restNonce,
				'Accept': 'application/json'
			}
		};

		if (body && method !== 'GET') {
			options.headers['Content-Type'] = 'application/json';
			options.body = JSON.stringify(body);
		}

		var response;
		try {
			response = await fetch(restUrl + path, options);
		} catch (netErr) {
			var netMsg = netErr && netErr.message ? netErr.message : String(netErr);
			console.error('[Align UI] Network error calling ' + path + ': ' + netMsg);
			throw new Error(netMsg);
		}

		var rawText = '';
		try {
			rawText = await response.text();
		} catch (readErr) {
			var readMsg = readErr && readErr.message ? readErr.message : String(readErr);
			console.error('[Align UI] Failed reading response body from ' + path + ': ' + readMsg);
			throw new Error(readMsg);
		}

		var parsed = null;
		try {
			parsed = rawText ? JSON.parse(rawText) : {};
		} catch (parseErr) {
			var parseMsg = parseErr && parseErr.message ? parseErr.message : String(parseErr);
			console.error('[Align UI] Invalid JSON from ' + path + ': ' + parseMsg);
			throw new Error('Invalid JSON response from server.');
		}

		if (!response.ok) {
			var errText = (parsed && parsed.message) ? parsed.message : ('HTTP ' + response.status);
			console.error('[Align UI] API error on ' + path + ' (' + response.status + '): ' + errText);
			throw new Error(errText);
		}

		return parsed;
	}

	/**
	 * Run 1 turn of /chat/step and automatically continue up to MAX_CONTINUE_TURNS.
	 *
	 * @param {string} userMessage Initial user message (empty on continuation turns).
	 * @param {number} turn        Current turn counter.
	 */
	async function runStepLoop(userMessage, turn) {
		var currentTurn = typeof turn === 'number' ? turn : 0;
		var maxErr = i18n.maxTurnsError || 'Maximum automatic tool turns (' + MAX_CONTINUE_TURNS + ') reached.';
		if (currentTurn > MAX_CONTINUE_TURNS) {
			setBusyState(false, '');
			appendContinueNotice(maxErr);
			return;
		}

		setBusyState(
			true,
			currentTurn > 0
				? (i18n.runningToolStatus || 'Running tool...')
				: (i18n.thinkingStatus || 'Thinking...')
		);

		try {
			var res = await requestChatApi('/chat/step', 'POST', {
				conversation_id: conversationId,
				message: userMessage || '',
				turn: currentTurn
			});

			console.log('[Align UI] Chat step status=' + res.status + ', turn=' + currentTurn);

			if (res.conversation_id) {
				conversationId = res.conversation_id;
			}

			if (res.limit_reached) {
				setBusyState(false, '');
				appendContinueNotice((res.reply && String(res.reply).trim() !== '') ? res.reply : maxErr);
				if (res.change_set && res.change_set.undoable && window.AlignChatboxEnhance) {
					window.AlignChatboxEnhance.attachUndo(messagesEl.lastElementChild, res.change_set, requestChatApi, appendMessage);
				}
				return;
			}

			if (res.reply && String(res.reply).trim() !== '') {
				appendMessage('assistant', res.reply);
			}

			if (res.change_set && res.change_set.undoable && window.AlignChatboxEnhance) {
				window.AlignChatboxEnhance.attachUndo(messagesEl.lastElementChild, res.change_set, requestChatApi, appendMessage);
			}

			if (res.status === 'continue') {
				var nextTurn = typeof res.turn === 'number' ? res.turn : (currentTurn + 1);
				await runStepLoop('', nextTurn);
				return;
			}

			if (res.status === 'needs_approval' && Array.isArray(res.pending_approvals)) {
				setBusyState(false, '');
				res.pending_approvals.forEach(function (item) {
					appendApprovalCard(item);
				});
				return;
			}

			setBusyState(false, '');
		} catch (err) {
			setBusyState(false, '');
			appendMessage('error', err && err.message ? err.message : 'Chat step request failed.');
		}
	}

	/**
	 * Handle user clicking Approve or Cancel on a write approval card.
	 */
	async function handleApprovalDecision(pendingId, decision, cardEl) {
		setBusyState(true, decision === 'approve' ? (i18n.executingWriteStatus || 'Applying changes...') : (i18n.cancellingStatus || 'Cancelling...'));

		try {
			var res = await requestChatApi('/chat/approve', 'POST', {
				pending_id: pendingId,
				decision: decision
			});

			console.log('[Align UI] Approval decision=' + decision + ', status=' + (res.status || 'done'));

			if (cardEl && cardEl.parentNode) {
				cardEl.parentNode.removeChild(cardEl);
			}

			if (res.ui_card && res.ui_card.title) {
				appendResultCard(res.ui_card);
			} else if (res.tool_result && res.tool_result.success === false && res.tool_result.message) {
				appendMessage('error', res.tool_result.message);
			}

			if (res.status === 'continue') {
				await runStepLoop('', 1);
				return;
			}

			setBusyState(false, '');
		} catch (err) {
			setBusyState(false, '');
			appendMessage('error', err && err.message ? err.message : 'Failed to process approval.');
		}
	}

	/**
	 * Handle direct Rollback button click from a result card.
	 */
	async function handleDirectRollback(postId, revisionId, btnEl) {
		if (btnEl) {
			btnEl.disabled = true;
		}
		setBusyState(true, i18n.rollingBackStatus || 'Restoring previous revision...');

		try {
			var res = await requestChatApi('/chat/approve', 'POST', {
				action_type: 'rollback',
				post_id: postId,
				revision_id: revisionId
			});

			setBusyState(false, '');
			console.log('[Align UI] Direct rollback completed for post=' + postId + ', revision=' + revisionId);

			if (res && res.success) {
				appendMessage('assistant', res.message || ('Page #' + postId + ' restored to revision #' + revisionId + '.'));
			} else {
				if (btnEl) {
					btnEl.disabled = false;
				}
				appendMessage('error', (res && res.message) ? res.message : 'Rollback failed.');
			}
		} catch (err) {
			setBusyState(false, '');
			if (btnEl) {
				btnEl.disabled = false;
			}
			appendMessage('error', err && err.message ? err.message : 'Rollback request failed.');
		}
	}

	/**
	 * Load existing conversation history.
	 */
	async function loadHistory() {
		isLoadingHistory = true;
		setBusyState(true, i18n.thinkingStatus || 'Loading history...');
		try {
			var res = await requestChatApi('/chat/history', 'GET');
			if (res.conversation_id) conversationId = res.conversation_id;
			if (Array.isArray(res.messages) && res.messages.length > 0) {
				res.messages.forEach(function (msg) {
					if (msg.role === 'result_card' && msg.ui_card) {
						appendResultCard(msg.ui_card);
					} else if (msg.role === 'user' || msg.role === 'assistant') {
						appendMessage(msg.role, msg.content);
					}
				});
			}
			if (Array.isArray(res.pending_approvals) && res.pending_approvals.length > 0) {
				res.pending_approvals.forEach(function (item) { appendApprovalCard(item); });
			}
			hasLoadedHistory = true;
		} catch (err) {
			hasLoadedHistory = false;
			console.error('[Align UI] Could not load chat history: ' + (err && err.message ? err.message : String(err)));
		} finally {
			isLoadingHistory = false;
			setBusyState(false, '');
		}
	}

	formEl.addEventListener('submit', function (e) {
		e.preventDefault();
		if (isBusy || isLoadingHistory) return;
		var text = (inputEl.value || '').trim();
		var uploader = window.AlignChatboxUpload;
		var attSuffix = (uploader && typeof uploader.consumeAttachmentsText === 'function')
			? uploader.consumeAttachmentsText()
			: '';
		if (!text && !attSuffix) {
			return;
		}
		var fullMessage = (text ? text : 'Attached media for processing.') + attSuffix;
		inputEl.value = '';
		appendMessage('user', fullMessage);
		runStepLoop(fullMessage, 0);
	});

	inputEl.addEventListener('keydown', function (e) {
		if (e.key === 'Enter' && !e.shiftKey) {
			e.preventDefault();
			formEl.dispatchEvent(new Event('submit', { cancelable: true }));
		}
	});

	function clearSessionDom(newConvId) {
		conversationId = newConvId || '';
		if (thinkingBubbleEl && thinkingBubbleEl.parentNode) {
			thinkingBubbleEl.parentNode.removeChild(thinkingBubbleEl);
		}
		thinkingBubbleEl = null;
		messagesEl.innerHTML = '';
		if (emptyEl) {
			emptyEl.style.display = 'block';
			messagesEl.appendChild(emptyEl);
		}
		if (window.AlignChatboxUpload && typeof window.AlignChatboxUpload.clearAttachments === 'function') {
			window.AlignChatboxUpload.clearAttachments();
		}
	}

	async function executeSessionClear(method, statusMsg) {
		if (isBusy) {
			return null;
		}
		setBusyState(true, statusMsg || (i18n.clearingStatus || 'Clearing session...'));
		try {
			var res = await requestChatApi('/chat/session', method || 'DELETE', {});
			clearSessionDom(res.conversation_id || res.session_id || '');
			console.log('[Align UI] Session cleared via header button: ' + conversationId);
			if (inputEl) {
				inputEl.focus();
			}
			document.dispatchEvent(new CustomEvent('align-chatbox:session-cleared', { detail: res }));
			return res;
		} catch (err) {
			appendMessage('error', err && err.message ? err.message : 'Failed to clear session.');
			return null;
		} finally {
			setBusyState(false, '');
		}
	}

	function openDrawer() {
		if (isOpen) return;
		isOpen = true;
		chatboxEl.classList.add('is-open');
		chatboxEl.setAttribute('aria-hidden', 'false');
		if (fabEl) fabEl.setAttribute('aria-expanded', 'true');
		if (backdropEl) {
			backdropEl.removeAttribute('hidden');
			backdropEl.classList.add('is-active');
			backdropEl.setAttribute('aria-hidden', 'false');
		}
		document.body.classList.add('align-chatbox-open');

		// Lazy load history on first open only.
		if (!hasLoadedHistory && !isLoadingHistory) {
			loadHistory();
		}

		setTimeout(function () {
			if (inputEl) inputEl.focus();
		}, 150);

		try {
			document.dispatchEvent(new CustomEvent('align-chatbox:opened'));
		} catch (evErr) {}
	}

	function closeDrawer() {
		if (!isOpen) return;
		isOpen = false;
		chatboxEl.classList.remove('is-open');
		chatboxEl.setAttribute('aria-hidden', 'true');
		if (fabEl) fabEl.setAttribute('aria-expanded', 'false');
		if (backdropEl) {
			backdropEl.classList.remove('is-active');
			backdropEl.setAttribute('hidden', '');
			backdropEl.setAttribute('aria-hidden', 'true');
		}
		document.body.classList.remove('align-chatbox-open');
		if (fabEl) fabEl.focus();

		try {
			document.dispatchEvent(new CustomEvent('align-chatbox:closed'));
		} catch (evErr) {}
	}

	function toggleDrawer() {
		if (isOpen) { closeDrawer(); } else { openDrawer(); }
	}

	if (fabEl) fabEl.addEventListener('click', function (e) { e.preventDefault(); toggleDrawer(); });
	if (closeBtnEl) closeBtnEl.addEventListener('click', function (e) { e.preventDefault(); closeDrawer(); });
	if (backdropEl) backdropEl.addEventListener('click', function (e) { e.preventDefault(); closeDrawer(); });

	document.addEventListener('keydown', function (e) {
		if (isOpen && (e.key === 'Escape' || e.key === 'Esc' || e.keyCode === 27)) {
			e.preventDefault();
			closeDrawer();
		}
	});

	if (clearBtnEl) {
		clearBtnEl.addEventListener('click', function () {
			executeSessionClear('DELETE', i18n.clearingStatus || 'Clearing session...');
		});
	}

	window.AlignChatbox = {
		open: openDrawer,
		close: closeDrawer,
		toggle: toggleDrawer,
		isOpen: function () { return isOpen; },
		getSessionId: function () { return conversationId; },
		getSession: function () { return requestChatApi('/chat/session', 'GET'); },
		newSession: function () { return executeSessionClear('POST', 'Starting new session...'); },
		deleteSession: function () { return executeSessionClear('DELETE', 'Deleting session...'); },
		sendMessage: function (text) {
			var clean = String(text || '').trim();
			if (!clean || isBusy) {
				return Promise.resolve(false);
			}
			appendMessage('user', clean);
			return runStepLoop(clean, 0);
		},
		continueRun: function () {
			if (isBusy) return Promise.resolve(false);
			return runStepLoop('', 1);
		},
		reloadHistory: function () {
			clearSessionDom(conversationId);
			return loadHistory();
		}
	};
});
