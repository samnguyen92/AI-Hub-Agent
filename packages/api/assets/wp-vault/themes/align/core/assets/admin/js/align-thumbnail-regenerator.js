/**
 * Force Thumbnail Regenerator Frontend Engine for Align Theme.
 *
 * @package Align
 */

(function() {
	'use strict';

	var STORAGE_FAILED_KEY = 'align_regen_failed_ids';

	var state = {
		queue: [],
		total: 0,
		currentIndex: 0,
		successCount: 0,
		errorCount: 0,
		deletedSizesCount: 0,
		failedIds: [],
		isProcessing: false,
		currentMode: 'all',
		startTime: null
	};

	var dom = {};

	function escapeHtml(str) {
		if (typeof str !== 'string') return '';
		return str
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function initDOM() {
		dom.wrapper         = document.getElementById('align-regen-dashboard');
		if (!dom.wrapper) return;

		dom.header          = document.getElementById('align-regen-header');
		dom.body            = document.getElementById('align-regen-body');

		dom.startBtn        = document.getElementById('align-regen-start-btn');
		dom.resumeBtn       = document.getElementById('align-regen-resume-btn');
		dom.cancelBtn       = document.getElementById('align-regen-cancel-btn');
		dom.retryBtn        = document.getElementById('align-regen-retry-btn');
		dom.resetCheckBtn   = document.getElementById('align-regen-reset-checkpoint-btn');
		dom.clearLogBtn     = document.getElementById('align-regen-clear-log-btn');

		dom.progressBar     = document.getElementById('align-regen-progress-fill');
		dom.progressPercent = document.getElementById('align-regen-progress-percent');
		dom.progressCount   = document.getElementById('align-regen-progress-count');

		dom.statTotal       = document.getElementById('align-regen-stat-total');
		dom.statSuccess     = document.getElementById('align-regen-stat-success');
		dom.statError       = document.getElementById('align-regen-stat-error');
		dom.statDeleted     = document.getElementById('align-regen-stat-deleted');

		dom.consoleBody     = document.getElementById('align-regen-console-body');

		// Load stored failed IDs if available from previous sessions
		try {
			var savedFailed = localStorage.getItem(STORAGE_FAILED_KEY);
			if (savedFailed) {
				var parsed = JSON.parse(savedFailed);
				if (Array.isArray(parsed) && parsed.length > 0) {
					state.failedIds = parsed;
					if (dom.retryBtn) {
						dom.retryBtn.style.display = 'inline-flex';
						setButtonText(dom.retryBtn, 'Retry Failed (' + state.failedIds.length + ')');
					}
				}
			}
		} catch (e) {
			console.warn('[Align ThumbnailRegenerator] Failed to parse stored failed IDs:', e);
		}
	}

	function toggleAccordion(forceOpen) {
		if (!dom.wrapper || !dom.body) return;
		var shouldOpen = (typeof forceOpen === 'boolean') ? forceOpen : (dom.body.style.display === 'none');
		if (shouldOpen) {
			dom.body.style.display = 'block';
			dom.wrapper.classList.add('is-open');
		} else {
			dom.body.style.display = 'none';
			dom.wrapper.classList.remove('is-open');
		}
	}

	function setButtonText(btn, text) {
		if (!btn) return;
		var textEl = btn.querySelector('.align-btn-text');
		if (textEl) {
			textEl.textContent = text;
		} else {
			btn.textContent = text;
		}
	}

	function appendLog(badgeText, badgeType, messageHtml) {
		if (!dom.consoleBody) return;

		var entry = document.createElement('div');
		entry.className = 'align-regen-log-entry';

		var badge = document.createElement('span');
		badge.className = 'align-regen-log-badge badge-' + badgeType;
		badge.textContent = badgeText;

		var text = document.createElement('span');
		text.innerHTML = messageHtml;

		entry.appendChild(badge);
		entry.appendChild(text);
		dom.consoleBody.appendChild(entry);

		// Auto scroll to bottom
		dom.consoleBody.scrollTop = dom.consoleBody.scrollHeight;
	}

	function updateStatsUI() {
		if (dom.statTotal)   dom.statTotal.textContent   = state.total;
		if (dom.statSuccess) dom.statSuccess.textContent = state.successCount;
		if (dom.statError)   dom.statError.textContent   = state.errorCount;
		if (dom.statDeleted) dom.statDeleted.textContent = state.deletedSizesCount;

		var percent = state.total > 0 ? Math.round((state.currentIndex / state.total) * 100) : 0;
		if (percent > 100) percent = 100;

		if (dom.progressBar) {
			dom.progressBar.style.width = percent + '%';
			if (state.isProcessing) {
				dom.progressBar.classList.add('is-active');
			} else {
				dom.progressBar.classList.remove('is-active');
			}
		}

		if (dom.progressPercent) dom.progressPercent.textContent = percent + '%';
		if (dom.progressCount)   dom.progressCount.textContent   = state.currentIndex + ' / ' + state.total;
	}

	function fetchImageIds(mode, customIds) {
		var config = window.alignRegenData || {};
		if (!config.ajaxUrl || !config.nonce) {
			console.error('[Align ThumbnailRegenerator] Missing localization data.');
			return;
		}

		state.isProcessing = true;
		state.currentMode  = mode;
		state.startTime    = new Date();

		if (dom.startBtn)  dom.startBtn.style.display  = 'none';
		if (dom.resumeBtn) dom.resumeBtn.style.display = 'none';
		if (dom.retryBtn)  dom.retryBtn.style.display  = 'none';
		if (dom.cancelBtn) {
			dom.cancelBtn.style.display = 'inline-flex';
		}

		appendLog('INFO', 'info', 'Querying image attachment IDs from database (Mode: ' + escapeHtml(mode) + ')...');

		var formData = new FormData();
		formData.append('action', 'align_get_regen_ids');
		formData.append('mode', mode);
		formData.append('ids', customIds || '');
		formData.append('security', config.nonce);

		fetch(config.ajaxUrl, {
			method: 'POST',
			body: formData
		})
		.then(function(res) { return res.json(); })
		.then(function(res) {
			if (res && res.success && res.data && res.data.ids) {
				state.queue = res.data.ids;
				state.total = state.queue.length;
				state.currentIndex = 0;
				state.successCount = 0;
				state.errorCount   = 0;
				state.deletedSizesCount = 0;

				updateStatsUI();

				if (state.total === 0) {
					appendLog('INFO', 'info', 'No images found to process.');
					finishUp();
					return;
				}

				appendLog('START', 'info', 'Found ' + state.total + ' images. Starting sequential thumbnail regeneration...');
				processNext();
			} else {
				var msg = (res && res.data && res.data.message) ? res.data.message : 'Error querying images.';
				appendLog('ERROR', 'error', escapeHtml(msg));
				finishUp();
			}
		})
		.catch(function(err) {
			console.error('[Align ThumbnailRegenerator] Network error:', err);
			appendLog('ERROR', 'error', 'Network failure connecting to server.');
			finishUp();
		});
	}

	function processNext() {
		if (!state.isProcessing) {
			return;
		}

		if (state.queue.length === 0) {
			finishUp();
			return;
		}

		var id = state.queue.shift();
		var config = window.alignRegenData || {};

		var formData = new FormData();
		formData.append('action', 'align_regenerate_single_thumbnail');
		formData.append('id', id);
		formData.append('security', config.nonce);

		// Per-image client-side timeout protection (60s max before auto-skip)
		var controller = null;
		var timeoutId  = null;
		var fetchOptions = {
			method: 'POST',
			body: formData
		};

		if (typeof AbortController !== 'undefined') {
			controller = new AbortController();
			fetchOptions.signal = controller.signal;
			timeoutId = setTimeout(function() {
				controller.abort();
			}, 60000); // 60 seconds timeout
		}

		fetch(config.ajaxUrl, fetchOptions)
		.then(function(res) {
			if (timeoutId) clearTimeout(timeoutId);
			return res.json();
		})
		.then(function(res) {
			if (!state.isProcessing) return;

			state.currentIndex++;

			if (res && res.data && res.data.current_nonce) {
				config.nonce = res.data.current_nonce;
			}

			if (res && res.success) {
				state.successCount++;

				var itemData = res.data || {};
				var safeTitle = escapeHtml(itemData.title || itemData.filename || 'Image');

				if (itemData.skipped) {
					appendLog('SKIPPED', 'skip', 'ID ' + id + ' (' + safeTitle + '): ' + escapeHtml(itemData.message || ''));
				} else {
					var delList = itemData.deleted || [];
					var genList = itemData.generated || [];
					state.deletedSizesCount += delList.length;

					var safeDel = delList.map(escapeHtml).join(', ');
					var safeGen = genList.map(escapeHtml).join(', ');

					var logHtml = '<strong>' + safeTitle + '</strong> (ID ' + id + ')<br>' +
						'&nbsp;&nbsp;↳ <span style="color: #60a5fa;">Deleted: ' + (safeDel ? safeDel : 'none') + '</span><br>' +
						'&nbsp;&nbsp;↳ <span style="color: #34d399;">Regenerated: ' + (safeGen ? safeGen : 'none') + '</span>';

					appendLog('SUCCESS', 'success', logHtml);
				}
			} else {
				state.errorCount++;
				if (state.failedIds.indexOf(id) === -1) {
					state.failedIds.push(id);
				}
				try {
					localStorage.setItem(STORAGE_FAILED_KEY, JSON.stringify(state.failedIds));
				} catch (e) {}

				var errMsg = (res && res.data && res.data.message) ? res.data.message : 'Unknown generation failure.';
				appendLog('ERROR', 'error', 'ID ' + id + ': ' + escapeHtml(errMsg));
			}

			updateStatsUI();
			processNext();
		})
		.catch(function(err) {
			if (timeoutId) clearTimeout(timeoutId);
			if (!state.isProcessing) return;

			console.error('[Align ThumbnailRegenerator] ID ' + id + ' exception:', err);
			state.currentIndex++;
			state.errorCount++;
			if (state.failedIds.indexOf(id) === -1) {
				state.failedIds.push(id);
			}
			try {
				localStorage.setItem(STORAGE_FAILED_KEY, JSON.stringify(state.failedIds));
			} catch (e) {}

			var errorText = (err && err.name === 'AbortError')
				? 'ID ' + id + ': Request timed out after 60s (Auto-skipped).'
				: 'ID ' + id + ': Network or server error (Skipped).';

			appendLog('ERROR', 'error', errorText);
			updateStatsUI();
			processNext();
		});
	}

	function finishUp() {
		state.isProcessing = false;

		var elapsedSeconds = state.startTime ? Math.round((new Date() - state.startTime) / 1000) : 0;

		if (dom.progressBar) {
			dom.progressBar.classList.remove('is-active');
			if (state.total > 0 && state.errorCount === 0) {
				dom.progressBar.classList.add('is-done');
			}
		}

		if (dom.cancelBtn) dom.cancelBtn.style.display = 'none';
		if (dom.startBtn)  dom.startBtn.style.display  = 'inline-flex';

		if (state.failedIds.length > 0 && dom.retryBtn) {
			dom.retryBtn.style.display = 'inline-flex';
			setButtonText(dom.retryBtn, 'Retry Failed (' + state.failedIds.length + ')');
		} else {
			try {
				localStorage.removeItem(STORAGE_FAILED_KEY);
			} catch (e) {}
		}

		appendLog('FINISH', 'info', 'Finished in ' + elapsedSeconds + 's. Success: ' + state.successCount + ', Errors: ' + state.errorCount);
		console.log('[Align ThumbnailRegenerator] Completed batch:', state);
	}

	function cancelProcess() {
		state.isProcessing = false;
		state.queue        = [];

		if (dom.progressBar) {
			dom.progressBar.classList.remove('is-active');
		}

		if (dom.cancelBtn) dom.cancelBtn.style.display = 'none';
		if (dom.startBtn)  dom.startBtn.style.display  = 'inline-flex';

		// Only show resume if we were in 'all' or 'resume' mode
		if (dom.resumeBtn && (state.currentMode === 'all' || state.currentMode === 'resume')) {
			dom.resumeBtn.style.display = 'inline-flex';
		}

		appendLog('CANCELLED', 'error', 'Process was cancelled by user.');
		console.log('[Align ThumbnailRegenerator] Process cancelled.');
	}

	function bindEvents() {
		if (dom.header) {
			dom.header.addEventListener('click', function(e) {
				// Don't toggle if clicking on interactive children (button, a, input, etc.)
				if (e.target && e.target.closest('button, a, input, label')) {
					return;
				}
				toggleAccordion();
			});
		}

		if (dom.startBtn) {
			dom.startBtn.addEventListener('click', function() {
				var config = window.alignRegenData || {};
				var confirmMsg = (config.strings && config.strings.confirmStart) ? config.strings.confirmStart : 'Start regenerating all thumbnails?';
				if (confirm(confirmMsg)) {
					fetchImageIds('all');
				}
			});
		}

		if (dom.resumeBtn) {
			dom.resumeBtn.addEventListener('click', function() {
				fetchImageIds('resume');
			});
		}

		if (dom.cancelBtn) {
			dom.cancelBtn.addEventListener('click', function() {
				cancelProcess();
			});
		}

		if (dom.retryBtn) {
			dom.retryBtn.addEventListener('click', function() {
				if (state.failedIds.length > 0) {
					fetchImageIds('custom', state.failedIds.join(','));
				}
			});
		}

		if (dom.resetCheckBtn) {
			dom.resetCheckBtn.addEventListener('click', function() {
				var config = window.alignRegenData || {};
				var formData = new FormData();
				formData.append('action', 'align_reset_regen_checkpoint');
				formData.append('security', config.nonce);

				fetch(config.ajaxUrl, { method: 'POST', body: formData })
				.then(function() {
					if (dom.resumeBtn) dom.resumeBtn.style.display = 'none';
					dom.resetCheckBtn.style.display = 'none';
					if (dom.retryBtn)  dom.retryBtn.style.display  = 'none';
					try {
						localStorage.removeItem(STORAGE_FAILED_KEY);
					} catch (e) {}
					state.failedIds = [];
					appendLog('RESET', 'info', 'Checkpoint and failed items cleared. Next run will start from beginning.');
				});
			});
		}

		if (dom.clearLogBtn) {
			dom.clearLogBtn.addEventListener('click', function() {
				if (dom.consoleBody) dom.consoleBody.innerHTML = '';
			});
		}

		// Handle prefilled IDs (from Media Library row action or Bulk actions)
		var config = window.alignRegenData || {};
		if (config.prefilledIds && config.prefilledIds.length > 0) {
			toggleAccordion(true);
			appendLog('INFO', 'info', 'Auto-launching regeneration for ' + config.prefilledIds.length + ' selected image(s)...');
			fetchImageIds('custom', config.prefilledIds.join(','));
		}
	}

	document.addEventListener('DOMContentLoaded', function() {
		initDOM();
		bindEvents();
	});

})();
