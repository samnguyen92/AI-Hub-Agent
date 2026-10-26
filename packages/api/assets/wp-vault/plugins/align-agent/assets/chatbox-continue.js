/**
 * Align Agent - Chatbox Turn Limit Continuation UI
 *
 * Renders the accessible status bubble and Continue button when the automatic
 * tool-calling turn limit is reached, and resumes the agent step loop on click.
 */
(function (window, document) {
	'use strict';

	/**
	 * Append a turn-limit status bubble with a Continue button to the message container.
	 *
	 * @param {HTMLElement} messagesEl Container element for chat messages.
	 * @param {string}      text       Progress summary or turn limit notice.
	 * @param {Object}      i18n       Localized UI strings.
	 * @param {Function}    onContinue Optional custom callback; defaults to window.AlignChatbox.continueRun.
	 * @returns {HTMLElement|null}
	 */
	function appendContinueBubble(messagesEl, text, i18n, onContinue) {
		if (!messagesEl) {
			return null;
		}

		var renderer = window.AlignChatboxRender;
		var bubble = null;

		if (renderer && typeof renderer.createMessageElement === 'function') {
			bubble = renderer.createMessageElement('assistant', String(text || ''));
			bubble.classList.add('align-chat-msg-continue');
		} else {
			bubble = document.createElement('div');
			bubble.className = 'align-chat-msg align-chat-msg-assistant align-chat-msg-continue';
			var p = document.createElement('p');
			p.textContent = String(text || '');
			bubble.appendChild(p);
		}

		bubble.setAttribute('role', 'status');
		bubble.setAttribute('aria-live', 'polite');

		var actionsWrap = document.createElement('div');
		actionsWrap.className = 'align-chat-continue-actions';

		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'button button-primary align-chat-continue-btn';
		btn.setAttribute('data-action', 'continue-run');
		btn.textContent = (i18n && i18n.continueLabel) ? String(i18n.continueLabel) : 'Continue';

		btn.addEventListener('click', function () {
			if (btn.disabled) {
				return;
			}
			btn.disabled = true;
			console.info('[Align UI] Continuing run from turn limit');

			if (typeof onContinue === 'function') {
				onContinue();
			} else if (window.AlignChatbox && typeof window.AlignChatbox.continueRun === 'function') {
				window.AlignChatbox.continueRun();
			}
		});

		actionsWrap.appendChild(btn);
		bubble.appendChild(actionsWrap);
		messagesEl.appendChild(bubble);

		try {
			messagesEl.scrollTop = messagesEl.scrollHeight;
		} catch (e) {
			// Ignore scroll error.
		}

		setTimeout(function () {
			try {
				btn.focus();
			} catch (e) {
				// Ignore focus error.
			}
		}, 30);

		return bubble;
	}

	window.AlignChatboxContinue = {
		appendContinueBubble: appendContinueBubble
	};
})(window, document);
