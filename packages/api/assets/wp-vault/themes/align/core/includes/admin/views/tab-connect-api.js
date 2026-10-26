/**
 * Tab: Connect API JavaScript Module.
 * Handles Two-Tab Provider Switching, Active Provider Activation, Key Visibility, and Connection Diagnostics.
 *
 * @package Align
 */

document.addEventListener('DOMContentLoaded', function() {
	function escapeHtml(str) {
		if (!str) return '';
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	// 1. Two-Tab Navigation: OpenRouter vs OpenAI (Official & Compatible)
	const aiTabBtns = document.querySelectorAll('.align-ai-tab-btn');
	const aiTabPanes = document.querySelectorAll('.align-ai-tab-pane');

	function switchAiTab(tabKey) {
		aiTabBtns.forEach(btn => {
			if (btn.dataset.aiTab === tabKey) {
				btn.classList.add('is-active');
			} else {
				btn.classList.remove('is-active');
			}
		});

		aiTabPanes.forEach(pane => {
			if (pane.id === 'ai-pane-' + tabKey) {
				pane.style.display = 'block';
			} else {
				pane.style.display = 'none';
			}
		});

		try {
			localStorage.setItem('align_active_ai_tab', tabKey);
		} catch (e) {}

		console.log('[Align AI Connection] Switched AI view tab to:', tabKey);
	}

	// 2. Active Provider Switching (1-Click Switch on Tab Click)
	const activeProviderInput = document.getElementById('align_ai_provider');
	const headerPill = document.getElementById('align-active-pill-header');

	// Legacy Sync Fields
	const legacyBaseInput = document.getElementById('align_ai_base_url');
	const legacyKeyInput = document.getElementById('align_ai_api_key');
	const legacyModelInput = document.getElementById('align_ai_model');
	const legacyOrgInput = document.getElementById('align_ai_organization');

	function syncLegacyFields(provider) {
		if (provider === 'openai') {
			const openAiBase = document.getElementById('align_openai_base_url');
			const openAiKey = document.getElementById('align_openai_api_key');
			const openAiModel = document.getElementById('align_openai_model');
			const openAiOrg = document.getElementById('align_openai_organization');
			if (legacyBaseInput && openAiBase) legacyBaseInput.value = openAiBase.value;
			if (legacyKeyInput && openAiKey) legacyKeyInput.value = openAiKey.value;
			if (legacyModelInput && openAiModel) legacyModelInput.value = openAiModel.value;
			if (legacyOrgInput && openAiOrg) legacyOrgInput.value = openAiOrg.value;
		} else {
			const orBase = document.getElementById('align_openrouter_base_url');
			const orKey = document.getElementById('align_openrouter_api_key');
			const orModel = document.getElementById('align_openrouter_model');
			if (legacyBaseInput && orBase) legacyBaseInput.value = orBase.value;
			if (legacyKeyInput && orKey) legacyKeyInput.value = orKey.value;
			if (legacyModelInput && orModel) legacyModelInput.value = orModel.value;
			if (legacyOrgInput) legacyOrgInput.value = '';
		}
	}

	function setActiveProvider(newProvider) {
		if (!activeProviderInput) return;
		activeProviderInput.value = newProvider;
		syncLegacyFields(newProvider);

		if (newProvider === 'openai') {
			if (headerPill) headerPill.textContent = 'Active Engine: OpenAI / Compatible';
		} else {
			if (headerPill) headerPill.textContent = 'Active Engine: OpenRouter';
		}

		console.log('[Align AI Connection] Active provider switched to:', newProvider);
	}

	aiTabBtns.forEach(btn => {
		btn.addEventListener('click', function(e) {
			e.preventDefault();
			const targetTab = this.dataset.aiTab;
			switchAiTab(targetTab);
			setActiveProvider(targetTab);
		});
	});

	// Initialize active tab from active provider
	let initialTab = activeProviderInput && activeProviderInput.value ? activeProviderInput.value : 'openrouter';
	switchAiTab(initialTab);

	// 3. Toggle Show/Hide API Keys (Independent for each tab)
	function setupKeyToggle(toggleBtnId, keyInputId) {
		const toggleBtn = document.getElementById(toggleBtnId);
		const keyInput = document.getElementById(keyInputId);
		if (toggleBtn && keyInput) {
			toggleBtn.addEventListener('click', function(e) {
				e.preventDefault();
				if (keyInput.type === 'password') {
					keyInput.type = 'text';
					toggleBtn.textContent = 'Hide';
				} else {
					keyInput.type = 'password';
					toggleBtn.textContent = 'Show';
				}
			});
		}
	}
	setupKeyToggle('align-toggle-openrouter-key', 'align_openrouter_api_key');
	setupKeyToggle('align-toggle-openai-key', 'align_openai_api_key');

	// 4. Model Recommendation Chips
	const modelChips = document.querySelectorAll('.align-chip-btn[data-fill-target]');
	modelChips.forEach(chip => {
		chip.addEventListener('click', function(e) {
			e.preventDefault();
			const targetId = this.dataset.fillTarget;
			const modelVal = this.dataset.modelValue;
			const targetInput = document.getElementById(targetId);
			if (targetInput) {
				targetInput.value = modelVal;
				targetInput.focus();
			}
		});
	});

	// 5. OpenAI Quick Presets (Base URL & Model)
	const openAiPresets = document.querySelectorAll('.align-openai-preset');
	const openAiBaseInput = document.getElementById('align_openai_base_url');
	const openAiModelInput = document.getElementById('align_openai_model');
	openAiPresets.forEach(preset => {
		preset.addEventListener('click', function(e) {
			e.preventDefault();
			if (openAiBaseInput) openAiBaseInput.value = this.dataset.base;
			if (openAiModelInput) openAiModelInput.value = this.dataset.model;
			if (openAiBaseInput) openAiBaseInput.focus();
		});
	});

	// 6. Generic AI Test Connection Executor
	function runAiTest({ provider, baseUrl, apiKey, model, org, btn, spinner, statusEl, resultBox }) {
		if (!apiKey) {
			if (resultBox) {
				resultBox.style.display = 'block';
				resultBox.style.background = '#fef2f2';
				resultBox.style.border = '1px solid #fecaca';
				resultBox.style.color = '#991b1b';
				resultBox.innerHTML = '<strong>API Key Required:</strong> Please enter an API Key before testing connection.';
			}
			return;
		}

		if (btn) btn.disabled = true;
		if (spinner) spinner.classList.add('is-active');
		if (statusEl) statusEl.innerHTML = '<span style="color: #0284c7;">Connecting to endpoint...</span>';
		if (resultBox) resultBox.style.display = 'none';

		const nonceInput = document.querySelector('input[name="align_settings_nonce"]');
		const nonce = nonceInput ? nonceInput.value : '';

		const formData = new FormData();
		formData.append('action', 'align_test_ai_connection');
		formData.append('security', nonce);
		formData.append('align_settings_nonce', nonce);
		formData.append('provider', provider);
		formData.append('base_url', baseUrl);
		formData.append('api_key', apiKey);
		formData.append('model', model);
		formData.append('organization', org || '');

		console.log('[Align AI Connection] Testing ' + provider + ' at endpoint: ' + baseUrl);

		fetch(ajaxurl, {
			method: 'POST',
			body: formData
		})
		.then(res => res.json())
		.then(resData => {
			if (btn) btn.disabled = false;
			if (spinner) spinner.classList.remove('is-active');

			if (resData.success && resData.data) {
				const data = resData.data;
				if (statusEl) {
					statusEl.innerHTML = '<span style="color: #16a34a;">✓ Operational (' + data.latency_ms + 'ms)</span>';
				}
				if (resultBox) {
					resultBox.style.display = 'block';
					resultBox.style.background = '#f0fdf4';
					resultBox.style.border = '1px solid #bbf7d0';
					resultBox.style.color = '#166534';
					resultBox.innerHTML = '<div style="font-weight: 700; margin-bottom: 4px;">✓ ' + escapeHtml(data.message) + '</div>' +
						'<div style="font-size: 12px; color: #15803d;"><strong>Confirmed Model:</strong> <code>' + escapeHtml(data.detected_model) + '</code> | <strong>Latency:</strong> ' + data.latency_ms + 'ms | <strong>Status:</strong> HTTP ' + data.status_code + '</div>';
				}
			} else {
				const errMsg = (resData.data && resData.data.message) ? resData.data.message : 'Connection failed';
				if (statusEl) {
					statusEl.innerHTML = '<span style="color: #dc2626;">✗ Failed</span>';
				}
				if (resultBox) {
					resultBox.style.display = 'block';
					resultBox.style.background = '#fef2f2';
					resultBox.style.border = '1px solid #fecaca';
					resultBox.style.color = '#991b1b';
					resultBox.innerHTML = '<div style="font-weight: 700; margin-bottom: 4px;">✗ Connection Verification Failed</div>' +
						'<div style="font-size: 12px;">' + escapeHtml(errMsg) + '</div>';
				}
			}
		})
		.catch(err => {
			if (btn) btn.disabled = false;
			if (spinner) spinner.classList.remove('is-active');
			if (statusEl) statusEl.innerHTML = '<span style="color: #dc2626;">✗ Request Error</span>';
			if (resultBox) {
				resultBox.style.display = 'block';
				resultBox.style.background = '#fef2f2';
				resultBox.style.border = '1px solid #fecaca';
				resultBox.style.color = '#991b1b';
				resultBox.innerHTML = '<div style="font-size: 12px;">' + escapeHtml(err.message) + '</div>';
			}
		});
	}

	// 7. OpenRouter Test Handler
	const testOpenRouterBtn = document.getElementById('align-test-openrouter-btn');
	if (testOpenRouterBtn) {
		testOpenRouterBtn.addEventListener('click', function(e) {
			e.preventDefault();
			const baseUrl = (document.getElementById('align_openrouter_base_url')?.value || '').trim();
			const apiKey  = (document.getElementById('align_openrouter_api_key')?.value || '').trim();
			const model   = (document.getElementById('align_openrouter_model')?.value || '').trim();

			runAiTest({
				provider: 'openrouter',
				baseUrl: baseUrl,
				apiKey: apiKey,
				model: model,
				org: '',
				btn: testOpenRouterBtn,
				spinner: document.getElementById('align-openrouter-spinner'),
				statusEl: document.getElementById('align-openrouter-test-status'),
				resultBox: document.getElementById('align-openrouter-test-result-box')
			});
		});
	}

	// 8. OpenAI Test Handler
	const testOpenAiBtn = document.getElementById('align-test-openai-btn');
	if (testOpenAiBtn) {
		testOpenAiBtn.addEventListener('click', function(e) {
			e.preventDefault();
			const baseUrl = (document.getElementById('align_openai_base_url')?.value || '').trim();
			const apiKey  = (document.getElementById('align_openai_api_key')?.value || '').trim();
			const model   = (document.getElementById('align_openai_model')?.value || '').trim();
			const org     = (document.getElementById('align_openai_organization')?.value || '').trim();

			runAiTest({
				provider: 'openai',
				baseUrl: baseUrl,
				apiKey: apiKey,
				model: model,
				org: org,
				btn: testOpenAiBtn,
				spinner: document.getElementById('align-openai-spinner'),
				statusEl: document.getElementById('align-openai-test-status'),
				resultBox: document.getElementById('align-openai-test-result-box')
			});
		});
	}

	// 4. Cloud API 6-Endpoint Diagnostics Tester
	const testAllEndpointsBtn = document.getElementById('align-test-all-endpoints-btn');
	const epSpinner = document.getElementById('align-endpoint-spinner');
	const epStatus = document.getElementById('align-endpoint-status-summary');
	const epTableWrap = document.getElementById('align-endpoints-table-wrap');
	const epTableBody = document.getElementById('align-endpoints-table-body');
	const cloudApiInput = document.getElementById('align_minify_api_url');

	if (testAllEndpointsBtn) {
		testAllEndpointsBtn.addEventListener('click', function(e) {
			e.preventDefault();
			testAllEndpointsBtn.disabled = true;
			if (epSpinner) epSpinner.classList.add('is-active');
			if (epStatus) epStatus.innerHTML = '<span style="color: #0284c7;">Testing endpoints...</span>';

			const nonceInput = document.querySelector('input[name="align_settings_nonce"]');
			const nonce = nonceInput ? nonceInput.value : '';
			const apiUrl = cloudApiInput ? cloudApiInput.value.trim() : '';

			const formData = new FormData();
			formData.append('action', 'align_test_all_endpoints');
			formData.append('security', nonce);
			formData.append('api_url', apiUrl);

			fetch(ajaxurl, {
				method: 'POST',
				body: formData
			})
			.then(res => res.json())
			.then(data => {
				testAllEndpointsBtn.disabled = false;
				if (epSpinner) epSpinner.classList.remove('is-active');

				if (!data.success || !data.data) {
					var errMsg = (data.data && data.data.message) ? data.data.message : 'Diagnostics failed';
					if (epStatus) epStatus.innerHTML = '<span style="color: #dc2626;">✗ ' + escapeHtml(errMsg) + '</span>';
					return;
				}

				var payload = data.data;
				var summary = payload.summary || {};
				var endpoints = payload.endpoints || [];

				if (epTableBody) {
					epTableBody.innerHTML = '';
					endpoints.forEach(function(ep) {
						var row = document.createElement('tr');
						var methodBadge = ep.method === 'POST'
							? '<span style="background: #0284c7; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: 600;">POST</span>'
							: '<span style="background: #64748b; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: 600;">GET</span>';

						var statusBadge = '';
						if (ep.status === 'operational') {
							statusBadge = ep.latency_ms >= 1500
								? '<span style="background: #fff8e5; color: #b26200; border: 1px solid #dba617; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;">Slow</span>'
								: '<span style="background: #edfaef; color: #008a20; border: 1px solid #00a32a; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;">Operational</span>';
						} else {
							statusBadge = '<span style="background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;" title="' + escapeHtml(ep.message || '') + '">Offline (Fallback Active)</span>';
						}

						var latencyColor = ep.status === 'operational'
							? (ep.latency_ms < 500 ? '#16a34a' : (ep.latency_ms < 1500 ? '#d97706' : '#dc2626'))
							: '#94a3b8';

						var descHtml = '<div style="font-size: 11px; color: #64748b; margin-top: 2px;">' + escapeHtml(ep.description || '');
						if (ep.status !== 'operational' && ep.fallback) {
							descHtml += ' &bull; <span style="color: #0284c7; font-weight: 500;">Fallback: ' + escapeHtml(ep.fallback) + '</span>';
						}
						descHtml += '</div>';

						row.innerHTML =
							'<td style="padding: 10px 12px; font-weight: 600; vertical-align: top;">' +
								escapeHtml(ep.name) + descHtml +
							'</td>' +
							'<td style="padding: 10px 12px; vertical-align: middle;">' + methodBadge + '</td>' +
							'<td style="padding: 10px 12px; vertical-align: middle;"><code>' + escapeHtml(ep.path) + '</code></td>' +
							'<td style="padding: 10px 12px; vertical-align: middle; font-family: monospace; font-weight: 600; color: ' + latencyColor + ';">' + ep.latency_ms + 'ms</td>' +
							'<td style="padding: 10px 12px; vertical-align: middle;">' + statusBadge + '</td>';

						epTableBody.appendChild(row);
					});
				}

				if (epTableWrap) epTableWrap.style.display = 'block';

				if (epStatus) {
					if (summary.all_operational) {
						epStatus.innerHTML = '<span style="color: #16a34a; font-weight: 600;">All Endpoints Operational (' + summary.total_latency_ms + 'ms)</span>';
					} else {
						epStatus.innerHTML = '<span style="color: #b45309; font-weight: 600;">' + summary.operational + '/' + summary.total + ' Operational (' + summary.failed + ' Offline &bull; Fallbacks Active)</span>';
					}
				}
			})
			.catch(err => {
				testAllEndpointsBtn.disabled = false;
				if (epSpinner) epSpinner.classList.remove('is-active');
				if (epStatus) epStatus.innerHTML = '<span style="color: #dc2626; font-weight: 600;">Connection check failed: ' + escapeHtml(err.message) + '</span>';
			});
		});
	}

	// 9. Accordion Support: Card 1 (AI Hub: Open Default), Card 2 (Cloud Services: Closed Default)
	function setupAccordion(cardId, headerId, bodyId, labelId, openText, closedText) {
		const card   = document.getElementById(cardId);
		const header = document.getElementById(headerId);
		const body   = document.getElementById(bodyId);
		const label  = document.getElementById(labelId);

		if (!card || !header || !body) return;

		header.addEventListener('click', function(e) {
			if (e.target && e.target.closest('button, a, input, label')) {
				return;
			}
			const isOpen = card.classList.contains('is-open');
			if (isOpen) {
				body.style.display = 'none';
				card.classList.remove('is-open');
				if (label) label.textContent = closedText;
			} else {
				body.style.display = 'block';
				card.classList.add('is-open');
				if (label) label.textContent = openText;
			}
		});
	}

	setupAccordion('align-ai-hub-card', 'align-ai-hub-header', 'align-ai-hub-body', 'align-ai-toggle-label', 'Hide Details', 'Show Details');
	setupAccordion('align-cloud-microservices-card', 'align-cloud-header', 'align-cloud-body', 'align-cloud-toggle-label', 'Hide Details', 'Show Details');
});
