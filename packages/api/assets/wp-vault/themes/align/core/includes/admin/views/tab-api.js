/**
 * Tab: Dedicated AI REST API Admin JavaScript
 *
 * Handles reactive endpoint pill switching, cURL generation, documentation inspection,
 * clipboard copying, and interactive live REST console execution.
 *
 * @package Align
 */

document.addEventListener('DOMContentLoaded', function() {
	// 1. API Endpoints Registry
	const ALIGN_API_REGISTRY = {
		create_page: {
			key: 'create_page',
			method: 'POST',
			route: '/pages',
			name: 'Create Page',
			description: 'Single-shot page creation. Saves post, externalizes CSS/JS, and wraps Gutenberg Custom HTML block.',
			headers: ['Content-Type: application/json', 'Authorization: Basic <base64(username:password)>'],
			needsId: false,
			payload: {
				title: 'Sample AI Landing Page',
				slug: 'sample-ai-landing',
				status: 'publish',
				lang: 'en',
				html: '<section class="hero-section">\n  <h1>Autonomous Creation</h1>\n  <p>Engineered with Align Dedicated AI REST API.</p>\n</section>',
				css: '.hero-section { padding: 80px 20px; text-align: center; }\n.hero-section h1 { font-size: 40px; color: #0f172a; font-weight: 800; }',
				js: 'console.log("[Align AI] Page mounted dynamically.");'
			}
		},
		list_pages: {
			key: 'list_pages',
			method: 'GET',
			route: '/pages?per_page=10&page=1',
			name: 'List Pages',
			description: 'List paginated pages with decoupled asset status. Query parameters: page, per_page, search, status, lang. Returns X-WP-Total headers.',
			headers: ['Accept: application/json', 'Authorization: Basic <base64(username:password)>'],
			needsId: false,
			payload: null
		},
		get_page: {
			key: 'get_page',
			method: 'GET',
			route: '/pages/{id}',
			name: 'Get Page',
			description: 'Retrieve full decoupled page with clean separated body HTML, scoped CSS, and scoped JS.',
			headers: ['Accept: application/json', 'Authorization: Basic <base64(username:password)>'],
			needsId: true,
			payload: null
		},
		update_page: {
			key: 'update_page',
			method: 'PUT',
			route: '/pages/{id}',
			name: 'Update Page',
			description: 'Partial or full update of page attributes, HTML body, CSS styling, and JS scripts.',
			headers: ['Content-Type: application/json', 'Authorization: Basic <base64(username:password)>'],
			needsId: true,
			payload: {
				title: 'Sample AI Landing Page (Updated)',
				html: '<section class="hero-section">\n  <h1>Autonomous Creation (Updated)</h1>\n  <p>Updated via PUT endpoint.</p>\n</section>',
				css: '.hero-section { padding: 90px 20px; text-align: center; }\n.hero-section h1 { font-size: 42px; color: #0284c7; }',
				js: 'console.log("[Align AI] Page update verified.");'
			}
		},
		delete_page: {
			key: 'delete_page',
			method: 'DELETE',
			route: '/pages/{id}',
			name: 'Delete Page',
			description: 'Delete page post and purge associated static CSS/JS files from disk.',
			headers: ['Authorization: Basic <base64(username:password)>'],
			needsId: true,
			payload: null
		},
		ping: {
			key: 'ping',
			method: 'GET',
			route: '/ping',
			name: 'Ping / Health Check',
			description: 'Health check, authentication verification, and current user role inspection.',
			headers: ['Authorization: Basic <base64(username:password)>'],
			needsId: false,
			payload: null
		}
	};

	let currentEndpointKey = 'create_page';

	// 2. Accordion Toggling Setup
	function setupCardAccordion(cardId, headerId, bodyId, openText, closedText) {
		const card   = document.getElementById(cardId);
		const header = document.getElementById(headerId);
		const body   = document.getElementById(bodyId);
		const label  = card ? card.querySelector('.align-accordion-toggle-label') : null;
		if (!card || !header || !body) return;
		header.addEventListener('click', function(e) {
			if (e.target && e.target.closest('button, a, input, select, label')) return;
			const isOpen = card.classList.contains('is-open');
			body.style.display = isOpen ? 'none' : 'block';
			card.classList.toggle('is-open', !isOpen);
			if (label) label.textContent = isOpen ? closedText : openText;
		});
	}

	setupCardAccordion('align-api-overview-card', 'align-api-overview-header', 'align-api-overview-body', 'Hide Overview', 'Show Overview');
	setupCardAccordion('align-api-specs-card', 'align-api-specs-header', 'align-api-specs-body', 'Hide Specifications', 'Show Specifications');
	setupCardAccordion('align-api-console-card', 'align-api-console-header', 'align-api-console-body', 'Close Console', 'Open Console');

	function ensureConsoleOpen() {
		const card  = document.getElementById('align-api-console-card');
		const body  = document.getElementById('align-api-console-body');
		const label = card ? card.querySelector('.align-accordion-toggle-label') : null;
		if (card && body) {
			body.style.display = 'block';
			card.classList.add('is-open');
			if (label) label.textContent = 'Close Console';
		}
	}

	// 3. Universal Copy to Clipboard Handler
	document.addEventListener('click', function(e) {
		const btn = e.target.closest('.align-copy-btn');
		if (!btn) return;
		e.preventDefault();

		let textToCopy = '';
		if (btn.dataset.clipboard) {
			textToCopy = btn.dataset.clipboard;
		} else if (btn.dataset.clipboardTarget) {
			const targetEl = document.querySelector(btn.dataset.clipboardTarget);
			if (targetEl) {
				textToCopy = targetEl.textContent.trim();
			}
		}

		if (!textToCopy) return;

		navigator.clipboard.writeText(textToCopy).then(function() {
			const originalHtml = btn.innerHTML;
			btn.innerHTML = '<span class="dashicons dashicons-yes" style="color: #16a34a;"></span><span>Copied!</span>';
			btn.style.pointerEvents = 'none';

			setTimeout(function() {
				btn.innerHTML = originalHtml;
				btn.style.pointerEvents = 'auto';
			}, 2000);
		}).catch(function(err) {
			console.error('[Align API Console] Failed to copy to clipboard:', err);
		});
	});

	function generateCurlCommand(endpoint, baseUrl, username) {
		const cleanBase = (baseUrl || window.alignAiBaseApiUrl || '/wp-json/align-ai/v1').replace(/\/+$/, '');
		const user = username || window.alignAiUsername || 'admin';
		let cmd = 'curl -X ' + endpoint.method + ' "' + cleanBase + endpoint.route + '" \\\n  -u "' + user + ':YOUR_APP_PASSWORD"';
		if (endpoint.payload) {
			cmd += ' \\\n  -H "Content-Type: application/json" \\\n  -d \'' + JSON.stringify(endpoint.payload, null, 2) + '\'';
		}
		return cmd;
	}

	function renderEndpointDetails(endpointKey) {
		const endpoint = ALIGN_API_REGISTRY[endpointKey];
		if (!endpoint) return;

		currentEndpointKey = endpointKey;

		// 1. Update pills active state
		document.querySelectorAll('.align-api-pill').forEach(function(pill) {
			if (pill.dataset.endpoint === endpointKey) {
				pill.classList.add('is-active');
			} else {
				pill.classList.remove('is-active');
			}
		});

		// 2. Update badge, name, route, description
		const badgeEl = document.getElementById('align-endpoint-badge');
		const nameEl  = document.getElementById('align-endpoint-name');
		const routeEl = document.getElementById('align-endpoint-route');
		const descEl  = document.getElementById('align-endpoint-desc');

		if (badgeEl) {
			badgeEl.textContent = endpoint.method;
			badgeEl.className = 'align-method-badge align-method-' + endpoint.method.toLowerCase();
		}
		if (nameEl) nameEl.textContent = endpoint.name;
		if (routeEl) routeEl.textContent = endpoint.route;
		if (descEl) descEl.textContent = endpoint.description;

		// 3. Update full request URL
		const cleanBase = (window.alignAiBaseApiUrl || '/wp-json/align-ai/v1').replace(/\/+$/, '');
		const fullUrl = cleanBase + endpoint.route;
		const urlEl = document.getElementById('align-endpoint-url');
		if (urlEl) {
			urlEl.textContent = fullUrl;
		}

		// 4. Update required headers
		const headersWrap = document.getElementById('align-endpoint-headers');
		if (headersWrap) {
			headersWrap.innerHTML = endpoint.headers.map(function(h) {
				return '<code style="font-size: 11.5px; font-family: monospace; background: #f1f5f9; padding: 3px 8px; border-radius: 4px; color: #1e293b; border: 1px solid #e2e8f0;">' + h + '</code>';
			}).join(' ');
		}

		// 5. Update JSON payload panel
		const payloadCodeEl  = document.getElementById('align-endpoint-payload-code');
		const copyPayloadBtn = document.getElementById('align-btn-copy-json');
		if (payloadCodeEl) {
			if (endpoint.payload) {
				payloadCodeEl.textContent = JSON.stringify(endpoint.payload, null, 2);
				if (copyPayloadBtn) copyPayloadBtn.style.display = 'inline-flex';
			} else {
				payloadCodeEl.textContent = '// No request body required for this endpoint.';
				if (copyPayloadBtn) copyPayloadBtn.style.display = 'none';
			}
		}

		// 6. Update cURL panel
		const curlCodeEl = document.getElementById('align-endpoint-curl-code');
		if (curlCodeEl) {
			curlCodeEl.textContent = generateCurlCommand(endpoint, cleanBase, window.alignAiUsername);
		}
	}

	// 5. Console Form Inputs & Visibility Helper
	const methodSelect = document.getElementById('align-console-method');
	const pathInput    = document.getElementById('align-console-path');
	const bodyTextarea = document.getElementById('align-console-body');
	const bodyWrap     = document.getElementById('align-console-body-wrap');

	function updateBodyVisibility() {
		if (!methodSelect || !bodyWrap) return;
		const method = methodSelect.value.toUpperCase();
		bodyWrap.style.opacity = (method === 'GET' || method === 'DELETE') ? '0.5' : '1';
	}

	if (methodSelect) {
		methodSelect.addEventListener('change', updateBodyVisibility);
		updateBodyVisibility();
	}

	function syncQuickActionButtons(target) {
		document.querySelectorAll('.align-console-quick-btn').forEach(function(btn) {
			const isMatch = (target === btn) ||
				(target === 'create_page' && btn.dataset.bodyType === 'create') ||
				(target === 'ping' && btn.dataset.path && btn.dataset.path.endsWith('/ping')) ||
				(target === 'list_pages' && btn.dataset.path && btn.dataset.path.includes('per_page'));
			if (isMatch) {
				btn.classList.remove('button-secondary');
				btn.classList.add('button-primary', 'is-active');
			} else {
				btn.classList.remove('button-primary', 'is-active');
				btn.classList.add('button-secondary');
			}
		});
	}

	// 6. Event Wiring: Pill Clicks & Load in Console
	document.addEventListener('click', function(e) {
		const pill = e.target.closest('.align-api-pill');
		if (!pill) return;
		e.preventDefault();

		const key = pill.dataset.endpoint;
		if (key && ALIGN_API_REGISTRY[key]) {
			renderEndpointDetails(key);
		}
	});

	const loadConsoleBtn = document.getElementById('align-btn-load-console');
	if (loadConsoleBtn) {
		loadConsoleBtn.addEventListener('click', function(e) {
			e.preventDefault();
			const endpoint = ALIGN_API_REGISTRY[currentEndpointKey];
			if (!endpoint) return;

			let route = endpoint.route;
			if (endpoint.needsId || route.includes('{id}')) {
				const enteredId = prompt('Enter the target Page ID:');
				if (!enteredId || isNaN(parseInt(enteredId, 10))) {
					return;
				}
				route = route.replace('{id}', parseInt(enteredId, 10));
			}

			ensureConsoleOpen();

			if (methodSelect) {
				methodSelect.value = endpoint.method;
			}

			if (pathInput) {
				const cleanBase = (window.alignAiBaseApiUrl || '/wp-json/align-ai/v1').replace(/\/+$/, '');
				pathInput.value = cleanBase + route;
			}

			if (bodyTextarea) {
				if (endpoint.payload) {
					bodyTextarea.value = JSON.stringify(endpoint.payload, null, 2);
				} else {
					bodyTextarea.value = '';
				}
			}

			updateBodyVisibility();
			syncQuickActionButtons(currentEndpointKey);

			const consoleCard = document.getElementById('align-api-console-card');
			if (consoleCard) {
				consoleCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		});
	}

	// Quick action buttons inside console
	document.addEventListener('click', function(e) {
		const quickBtn = e.target.closest('.align-console-quick-btn');
		if (!quickBtn) return;
		e.preventDefault();

		syncQuickActionButtons(quickBtn);

		const method   = quickBtn.dataset.method || 'GET';
		const path     = quickBtn.dataset.path || '';
		const bodyType = quickBtn.dataset.bodyType || '';

		if (methodSelect) methodSelect.value = method;
		if (pathInput && path) {
			pathInput.value = path;
		}

		if (bodyTextarea) {
			if (bodyType === 'create' && ALIGN_API_REGISTRY.create_page && ALIGN_API_REGISTRY.create_page.payload) {
				bodyTextarea.value = JSON.stringify(ALIGN_API_REGISTRY.create_page.payload, null, 2);
			} else {
				bodyTextarea.value = '';
			}
		}

		updateBodyVisibility();
	});

	// 5. Execute Live Request in Console
	const sendBtn      = document.getElementById('align-console-send-btn');
	const statusBadge  = document.getElementById('align-console-status-badge');
	const latencySpan  = document.getElementById('align-console-latency');
	const responseText = document.getElementById('align-console-response-text');
	const copyRespBtn  = document.getElementById('align-console-copy-response');

	if (sendBtn) {
		sendBtn.addEventListener('click', function() {
			const method = methodSelect ? methodSelect.value.toUpperCase() : 'GET';
			const url    = pathInput ? pathInput.value.trim() : '';
			const body   = bodyTextarea ? bodyTextarea.value.trim() : '';

			if (!url) {
				alert('Please enter a target request URL.');
				return;
			}

			// Validate JSON payload for POST/PUT if body is non-empty
			let parsedPayload = null;
			if ((method === 'POST' || method === 'PUT') && body) {
				try {
					parsedPayload = JSON.parse(body);
				} catch (jsonErr) {
					alert('Invalid JSON in request payload:\n' + jsonErr.message);
					return;
				}
			}

			sendBtn.disabled = true;
			const originalBtnHtml = sendBtn.innerHTML;
			sendBtn.innerHTML = '<span class="spinner is-active"></span><span>Sending...</span>';

			if (statusBadge) statusBadge.style.display = 'none';
			if (latencySpan) latencySpan.textContent = '';
			if (copyRespBtn) copyRespBtn.style.display = 'none';
			if (responseText) responseText.textContent = 'Sending request to: ' + url + ' ...';

			const nonce = window.alignRestNonce || window.alignSettingsNonce || '';
			const headers = {
				'Accept': 'application/json'
			};

			if (nonce) {
				headers['X-WP-Nonce'] = nonce;
			}

			const options = {
				method: method,
				headers: headers
			};

			if (parsedPayload && (method === 'POST' || method === 'PUT')) {
				headers['Content-Type'] = 'application/json';
				options.body = JSON.stringify(parsedPayload);
			}

			const startTime = performance.now();

			fetch(url, options)
				.then(async function(response) {
					const endTime = performance.now();
					const latencyMs = Math.round(endTime - startTime);

					const status = response.status;
					const statusText = response.statusText || '';

					if (statusBadge) {
						statusBadge.textContent = status + ' ' + statusText;
						statusBadge.style.display = 'inline-block';
						const is2xx = (status >= 200 && status < 300);
						const is4xx = (status >= 400 && status < 500);
						statusBadge.style.background = is2xx ? '#dcfce7' : (is4xx ? '#fef9c3' : '#fee2e2');
						statusBadge.style.color = is2xx ? '#15803d' : (is4xx ? '#a16207' : '#dc2626');
						statusBadge.style.border = '1px solid ' + (is2xx ? '#bbf7d0' : (is4xx ? '#fef08a' : '#fecaca'));
					}

					if (latencySpan) latencySpan.textContent = latencyMs + 'ms';

					let responseBody = '';
					const contentType = response.headers.get('content-type') || '';

					if (contentType.includes('application/json')) {
						try {
							const jsonData = await response.json();
							responseBody = JSON.stringify(jsonData, null, 2);
						} catch (e) {
							responseBody = await response.text();
						}
					} else {
						responseBody = await response.text();
					}

					if (responseText) responseText.textContent = responseBody;
					if (copyRespBtn) copyRespBtn.style.display = 'inline-block';

					console.log('[Align API Console] Request complete:', { method, url, status, latencyMs });
				})
				.catch(function(fetchErr) {
					const endTime = performance.now();
					const latencyMs = Math.round(endTime - startTime);

					if (statusBadge) {
						statusBadge.textContent = 'Network Error';
						statusBadge.style.display = 'inline-block';
						statusBadge.style.background = '#fee2e2';
						statusBadge.style.color = '#dc2626';
						statusBadge.style.border = '1px solid #fecaca';
					}
					if (latencySpan) latencySpan.textContent = latencyMs + 'ms';
					if (responseText) responseText.textContent = 'Network request failed: ' + fetchErr.message;

					console.error('[Align API Console] Request error:', fetchErr);
				})
				.finally(function() {
					sendBtn.disabled = false;
					sendBtn.innerHTML = originalBtnHtml;
				});
		});
	}

	// 7. Console Defaults Initialization
	function initConsoleDefaults() {
		if (methodSelect) methodSelect.value = 'POST';
		if (pathInput) {
			const cleanBase = (window.alignAiBaseApiUrl || '/wp-json/align-ai/v1').replace(/\/+$/, '');
			pathInput.value = cleanBase + '/pages';
		}
		if (bodyTextarea && ALIGN_API_REGISTRY.create_page && ALIGN_API_REGISTRY.create_page.payload) {
			bodyTextarea.value = JSON.stringify(ALIGN_API_REGISTRY.create_page.payload, null, 2);
		}
		updateBodyVisibility();
	}

	// 8. Initial Render
	renderEndpointDetails('create_page');
	initConsoleDefaults();
});
