/**
 * Align Agent Gateway - Admin Scripts
 */
document.addEventListener('DOMContentLoaded', function () {
	function getI18n(key, fallback) {
		return (window.alignAgentData && window.alignAgentData[key]) || fallback;
	}

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.textContent = str;
		return div.innerHTML;
	}

	function updateStatus(el, message, isSuccess, isRawHtml) {
		if (!el) return;
		el.style.display = 'block';
		el.innerHTML = isRawHtml ? message : '<span style="color: ' + (isSuccess ? '#16a34a' : '#dc2626') + ';">' + (isSuccess ? '✓ ' : '✗ ') + escapeHtml(message) + '</span>';
	}

	function postAjax(action, params, onSuccess, onError) {
		var formData = new FormData();
		formData.append('action', action);
		formData.append('security', getI18n('nonce', ''));
		if (params) {
			Object.keys(params).forEach(function (k) {
				if (params[k] !== undefined && params[k] !== null) formData.append(k, params[k]);
			});
		}
		var url = getI18n('ajaxUrl', '/wp-admin/admin-ajax.php');
		return fetch(url, { method: 'POST', body: formData })
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (data && data.success) {
					if (onSuccess) onSuccess(data.data || {});
				} else {
					var err = (data && data.data && data.data.message) ? data.data.message : 'Action failed.';
					if (onError) onError(err, data ? data.data : null);
				}
			})
			.catch(function (err) {
				if (onError) onError('Request failed: ' + err.message, null);
			});
	}

	function setupPasswordToggle(btnId, inputId, txtId) {
		var btn = document.getElementById(btnId), input = document.getElementById(inputId), txt = document.getElementById(txtId);
		if (!btn || !input) return;
		btn.addEventListener('click', function () {
			var isPass = input.type === 'password';
			input.type = isPass ? 'text' : 'password';
			if (txt) txt.textContent = isPass ? 'Hide' : 'Show';
			var icon = btn.querySelector('.dashicons');
			if (icon) {
				icon.classList.toggle('dashicons-visibility', !isPass);
				icon.classList.toggle('dashicons-hidden', isPass);
			}
		});
	}

	// 1. Password Visibility Toggles.
	setupPasswordToggle('align-license-toggle-key-btn', 'align_license_key', 'align-license-toggle-key-text');
	setupPasswordToggle('align-bridge-toggle-bot-token-btn', 'align_bridge_bot_token', 'align-bridge-toggle-bot-token-text');
	setupPasswordToggle('align-bridge-toggle-llm-key-btn', 'align_bridge_llm_api_key', 'align-bridge-toggle-llm-key-text');
	setupPasswordToggle('align-bridge-toggle-app-pass-btn', 'align_bridge_wp_app_password', 'align-bridge-toggle-app-pass-text');

	// 2. License Activation & Deactivation Gate.
	var activateLicenseBtn   = document.getElementById('align-agent-activate-license-btn');
	var deactivateLicenseBtn = document.getElementById('align-agent-deactivate-license-btn');
	var licenseStatusEl      = document.getElementById('align-agent-license-status');
	var licenseMidUrlIn      = document.getElementById('align_license_middleware_url');
	var licenseKeyIn         = document.getElementById('align_license_key');

	if (activateLicenseBtn) {
		activateLicenseBtn.addEventListener('click', function () {
			var midUrl = licenseMidUrlIn ? licenseMidUrlIn.value.trim() : '';
			var licKey = licenseKeyIn ? licenseKeyIn.value.trim() : '';

			if (!licKey || licKey.indexOf('ak_') !== 0) {
				updateStatus(licenseStatusEl, 'Please enter a valid License Key starting with ak_.', false);
				return;
			}

			activateLicenseBtn.disabled = true;
			updateStatus(licenseStatusEl, 'Verifying license key with Middleware Hub...', true);

			postAjax('align_agent_activate_license', {
				middleware_url: midUrl,
				license_key: licKey
			}, function (data) {
				updateStatus(licenseStatusEl, data.message || 'License activated! Reloading...', true);
				setTimeout(function () { window.location.reload(); }, 600);
			}, function (err) {
				activateLicenseBtn.disabled = false;
				updateStatus(licenseStatusEl, err || 'Failed to activate license.', false);
			});
		});
	}

	if (deactivateLicenseBtn) {
		deactivateLicenseBtn.addEventListener('click', function () {
			deactivateLicenseBtn.disabled = true;
			postAjax('align_agent_deactivate_license', {}, function () {
				window.location.reload();
			}, function () {
				deactivateLicenseBtn.disabled = false;
			});
		});
	}

	// 3. Bridge Tab: Telegram 6-Digit Pairing, LLM Test & Site Sync.
	var MAX_POLL_COUNT        = 48;
	var registerBridgeBtn     = document.getElementById('align-agent-register-bridge-btn');
	var bridgeStatusEl        = document.getElementById('align-agent-bridge-status');
	var bridgeTokenInput      = document.getElementById('align_bridge_bot_token');
	var bridgeAllowedIdsInput = document.getElementById('align_bridge_allowed_user_ids');
	var bridgePairBtn         = document.getElementById('align-bridge-pair-btn');
	var bridgePairBox         = document.getElementById('align-bridge-pair-box');
	var bridgePairCodeEl      = document.getElementById('align-bridge-pair-code');
	var bridgePairHintEl      = document.getElementById('align-bridge-pair-hint');
	var bridgePairStatus      = document.getElementById('align-bridge-pair-status');
	var bridgeTestAiBtn       = document.getElementById('align-bridge-test-ai-btn');
	var bridgeAiStatus        = document.getElementById('align-bridge-ai-status');
	var bridgeLlmBaseIn       = document.getElementById('align_bridge_llm_base_url');
	var bridgeLlmKeyIn        = document.getElementById('align_bridge_llm_api_key');
	var bridgeLlmModelIn      = document.getElementById('align_bridge_llm_model');
	var bridgeDebounceTimer   = null;
	var bridgePollingInterval = null;
	var bridgePollIteration   = 0;
	var bridgeActiveCode      = '';
	var bridgeLastPairedToken = '';

	function isValidBotToken(token) {
		return typeof token === 'string' && token.length >= 35 && token.indexOf(':') !== -1;
	}

	function normalizeLlmBaseUrl(rawUrl) {
		var clean = (rawUrl || '').trim().replace(/\/+$/, '');
		return clean.replace(/\/chat\/completions$/i, '');
	}

	if (bridgeLlmBaseIn) {
		bridgeLlmBaseIn.addEventListener('blur', function () {
			var normalized = normalizeLlmBaseUrl(bridgeLlmBaseIn.value);
			if (normalized && normalized !== bridgeLlmBaseIn.value) {
				bridgeLlmBaseIn.value = normalized;
			}
		});
	}

	if (bridgeTestAiBtn) {
		bridgeTestAiBtn.addEventListener('click', function () {
			var baseUrl = normalizeLlmBaseUrl(bridgeLlmBaseIn ? bridgeLlmBaseIn.value : '');
			if (bridgeLlmBaseIn && baseUrl) {
				bridgeLlmBaseIn.value = baseUrl;
			}
			var endpoint = baseUrl ? (baseUrl + '/chat/completions') : '';
			var apiKey   = bridgeLlmKeyIn ? bridgeLlmKeyIn.value.trim() : '';
			var model    = bridgeLlmModelIn ? bridgeLlmModelIn.value.trim() : '';

			if (!apiKey) {
				updateStatus(bridgeAiStatus, 'Please enter an LLM API Key first.', false);
				return;
			}

			bridgeTestAiBtn.disabled = true;
			updateStatus(bridgeAiStatus, getI18n('testingAiMsg', 'Testing connection to AI Provider...'), true);

			postAjax('align_agent_test_ai', {
				endpoint: endpoint,
				api_key: apiKey,
				model: model
			}, function (data) {
				bridgeTestAiBtn.disabled = false;
				updateStatus(bridgeAiStatus, data.message || 'AI Provider connected successfully!', true);
			}, function (err) {
				bridgeTestAiBtn.disabled = false;
				updateStatus(bridgeAiStatus, err, false);
			});
		});
	}

	function stopBridgePolling() {
		if (bridgePollingInterval) {
			clearInterval(bridgePollingInterval);
			bridgePollingInterval = null;
		}
		bridgePollIteration = 0;
	}

	function startBridgePolling(tokenVal) {
		stopBridgePolling();
		bridgePollingInterval = setInterval(function () {
			if (++bridgePollIteration > MAX_POLL_COUNT) {
				stopBridgePolling();
				updateStatus(bridgePairStatus, 'Pairing timed out. Click "Generate Pairing Code" to try again.', false);
				return;
			}
			var currentToken = bridgeTokenInput ? bridgeTokenInput.value.trim() : '';
			if (!currentToken || currentToken !== tokenVal) { stopBridgePolling(); return; }

			postAjax('align_agent_pair_poll', { bot_token: tokenVal, code: bridgeActiveCode }, function (data) {
				if (data.chat_id) {
					stopBridgePolling();
					if (bridgeAllowedIdsInput) bridgeAllowedIdsInput.value = data.chat_id;
					if (bridgePairBox) bridgePairBox.style.display = 'none';
					bridgeLastPairedToken = tokenVal;
					updateStatus(bridgePairStatus, data.message || ('Connected! Chat ID ' + data.chat_id + ' paired and saved.'), true);
					if (data.auto_synced) {
						updateBridgeStatus('Settings automatically synced to Bridge (status: connected).', true);
					} else {
						var llmBase = normalizeLlmBaseUrl(bridgeLlmBaseIn ? bridgeLlmBaseIn.value : '');
						var llmKey  = bridgeLlmKeyIn ? bridgeLlmKeyIn.value.trim() : '';
						var llmMod  = bridgeLlmModelIn ? bridgeLlmModelIn.value.trim() : '';
						var wpPass  = (document.getElementById('align_bridge_wp_app_password') || {}).value || '';
						if (tokenVal && llmBase && llmKey && llmMod && wpPass.trim() && registerBridgeBtn) {
							registerBridgeBtn.click();
						}
					}
				}
			}, function () {
				// Still waiting for the code to arrive; keep polling silently.
			});
		}, 2500);
	}

	function startBridgePairing(force) {
		var tokenVal = bridgeTokenInput ? bridgeTokenInput.value.trim() : '';
		if (!isValidBotToken(tokenVal)) {
			updateStatus(bridgePairStatus, 'Please enter a valid Telegram Bot Token first.', false);
			return;
		}
		stopBridgePolling();
		if (bridgePairBtn) bridgePairBtn.disabled = true;
		updateStatus(bridgePairStatus, 'Generating pairing code...', true);

		postAjax('align_agent_pair_start', { bot_token: tokenVal, force: force ? '1' : '0' }, function (data) {
			if (bridgePairBtn) bridgePairBtn.disabled = false;
			bridgeActiveCode = data.code || '';
			if (bridgePairCodeEl) bridgePairCodeEl.textContent = bridgeActiveCode;
			if (bridgePairBox) bridgePairBox.style.display = 'block';

			var bName = data.bot_username || '';
			var bLink = data.bot_link || (bName ? 'https://t.me/' + bName : '');
			var target = bLink
				? '<a href="' + encodeURI(bLink) + '" target="_blank" rel="noopener noreferrer">@' + escapeHtml(bName) + '</a>'
				: 'your bot';
			if (bridgePairHintEl) bridgePairHintEl.innerHTML = 'Open ' + target + ' in Telegram and send this code.';

			updateStatus(bridgePairStatus, '<span style="color: #0284c7; font-weight: 600;">Waiting for the code in Telegram...</span> The chat connects automatically once received.', false, true);
			startBridgePolling(tokenVal);
		}, function (err) {
			if (bridgePairBtn) bridgePairBtn.disabled = false;
			updateStatus(bridgePairStatus, err || 'Failed to generate pairing code.', false);
		});
	}

	function triggerBridgeTokenAutoPairing() {
		var tokenVal = bridgeTokenInput ? bridgeTokenInput.value.trim() : '';
		if (!isValidBotToken(tokenVal)) { stopBridgePolling(); return; }
		if (tokenVal === bridgeLastPairedToken && bridgeAllowedIdsInput && bridgeAllowedIdsInput.value.trim() !== '') return;
		startBridgePairing(false);
	}

	if (bridgeTokenInput) {
		['input', 'paste', 'change'].forEach(function (evt) {
			bridgeTokenInput.addEventListener(evt, function () {
				if (bridgeDebounceTimer) clearTimeout(bridgeDebounceTimer);
				bridgeDebounceTimer = setTimeout(triggerBridgeTokenAutoPairing, evt === 'change' ? 0 : (evt === 'paste' ? 150 : 700));
			});
		});
	}

	if (bridgePairBtn) {
		bridgePairBtn.addEventListener('click', function () {
			startBridgePairing(true);
		});
	}

	function updateBridgeStatus(message, isSuccess) {
		if (!bridgeStatusEl) return;
		bridgeStatusEl.style.display = 'block';
		bridgeStatusEl.style.backgroundColor = isSuccess ? '#d1e7dd' : '#f8d7da';
		bridgeStatusEl.style.color = isSuccess ? '#0f5132' : '#842029';
		bridgeStatusEl.style.border = '1px solid ' + (isSuccess ? '#badbcc' : '#f5c2c7');
		bridgeStatusEl.textContent = (isSuccess ? '✓ ' : '✗ ') + message;
	}

	if (registerBridgeBtn) {
		registerBridgeBtn.addEventListener('click', function () {
			var middlewareUrl  = (document.getElementById('align_bridge_middleware_url') || {}).value || '';
			var approvalRadio  = document.querySelector('input[name="align_bridge_approval_mode"]:checked');
			var approvalMode   = approvalRadio ? approvalRadio.value : 'auto';
			var botToken       = (document.getElementById('align_bridge_bot_token') || {}).value || '';
			var allowedUserIds = (document.getElementById('align_bridge_allowed_user_ids') || {}).value || '';
			var llmBaseUrl     = normalizeLlmBaseUrl((document.getElementById('align_bridge_llm_base_url') || {}).value || '');
			if (bridgeLlmBaseIn && llmBaseUrl) {
				bridgeLlmBaseIn.value = llmBaseUrl;
			}
			var llmApiKey     = (document.getElementById('align_bridge_llm_api_key') || {}).value || '';
			var llmModel      = (document.getElementById('align_bridge_llm_model') || {}).value || '';
			var wpUsername    = (document.getElementById('align_bridge_wp_username') || {}).value || '';
			var wpAppPassword = (document.getElementById('align_bridge_wp_app_password') || {}).value || '';

			registerBridgeBtn.disabled = true;
			updateBridgeStatus('Saving and syncing configuration to Middleware Bridge...', true);

			postAjax('align_agent_register_bridge', {
				middleware_url: middlewareUrl.trim(),
				approval_mode: approvalMode,
				bot_token: botToken.trim(),
				allowed_user_ids: allowedUserIds.trim(),
				llm_base_url: llmBaseUrl,
				llm_api_key: llmApiKey.trim(),
				llm_model: llmModel.trim(),
				wp_username: wpUsername.trim(),
				wp_app_password: wpAppPassword.trim()
			}, function (data) {
				registerBridgeBtn.disabled = false;
				updateBridgeStatus(data.message || 'Settings saved and synced to Bridge successfully!', true);
			}, function (err) {
				registerBridgeBtn.disabled = false;
				updateBridgeStatus(err || 'Failed to sync configuration to Bridge.', false);
			});
		});
	}

	// 4. Window unload cleanup.
	window.addEventListener('beforeunload', function () {
		stopBridgePolling();
		if (bridgeDebounceTimer) clearTimeout(bridgeDebounceTimer);
	});
});
