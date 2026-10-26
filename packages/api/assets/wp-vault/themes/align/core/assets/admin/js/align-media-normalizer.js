/**
 * Media Filename Normalizer Controller for Align Theme.
 *
 * @package Align
 */

(function() {
	'use strict';

	var STORAGE_KEY = 'align_normalizer_failed_ids';
	var state = { queue: [], total: 0, currentIndex: 0, changedCount: 0, cleanCount: 0, errorCount: 0, failedIds: [], isProcessing: false, isCanceled: false, currentNonce: '' };
	var dom = {};

	function escapeHtml(str) {
		return (typeof str !== 'string') ? '' : str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	}

	function initDOM() {
		[
			'align-normalizer-modal', 'align-normalizer-modal-close', 'align-normalizer-dashboard',
			'align-normalizer-header', 'align-normalizer-body', 'align-normalizer-start-btn',
			'align-normalizer-resume-btn', 'align-normalizer-cancel-btn', 'align-normalizer-retry-btn',
			'align-normalizer-reset-checkpoint-btn', 'align-normalizer-clear-log-btn',
			'align-normalizer-progress-fill', 'align-normalizer-progress-percent', 'align-normalizer-progress-count',
			'align-normalizer-stat-total', 'align-normalizer-stat-changed', 'align-normalizer-stat-clean',
			'align-normalizer-stat-error', 'align-normalizer-console-body', 'align-norm-modal-start-btn',
			'align-norm-modal-resume-btn', 'align-norm-modal-cancel-btn', 'align-norm-modal-retry-btn',
			'align-norm-modal-clear-log-btn', 'align-norm-modal-progress-fill', 'align-norm-modal-progress-percent',
			'align-norm-modal-progress-count', 'align-norm-modal-total', 'align-norm-modal-changed',
			'align-norm-modal-clean', 'align-norm-modal-error', 'align-norm-modal-console-body'
		].forEach(function(id) { dom[id] = document.getElementById(id); });

		var config = window.alignNormalizerData || {};
		state.currentNonce = config.nonce || '';
		try {
			var saved = localStorage.getItem(STORAGE_KEY);
			if (saved) {
				state.failedIds = JSON.parse(saved) || [];
				if (state.failedIds.length > 0) updateRetryButtonUI();
			}
		} catch (e) {}
	}

	function appendLog(tag, type, message) {
		var time = new Date().toLocaleTimeString([], { hour12: false });
		var lineHtml = '<div class="align-normalizer-log-entry"><span class="align-log-time">[' + time + ']</span> ' +
			'<span class="align-log-tag is-' + escapeHtml(type) + '">[' + escapeHtml(tag) + ']</span> ' + escapeHtml(message) + '</div>';
		[dom['align-normalizer-console-body'], dom['align-norm-modal-console-body']].forEach(function(t) {
			if (t) {
				t.insertAdjacentHTML('beforeend', lineHtml);
				while (t.children.length > 200) { t.removeChild(t.firstChild); }
				t.scrollTop = t.scrollHeight;
			}
		});
	}

	function updateStats() {
		var setVal = function(id, val) { if (dom[id]) dom[id].textContent = val; };
		setVal('align-normalizer-stat-total', state.total); setVal('align-norm-modal-total', state.total);
		setVal('align-normalizer-stat-changed', state.changedCount); setVal('align-norm-modal-changed', state.changedCount);
		setVal('align-normalizer-stat-clean', state.cleanCount); setVal('align-norm-modal-clean', state.cleanCount);
		setVal('align-normalizer-stat-error', state.errorCount); setVal('align-norm-modal-error', state.errorCount);

		var pct = state.total > 0 ? Math.round((state.currentIndex / state.total) * 100) : 0, cnt = state.currentIndex + ' / ' + state.total;
		[ { f: 'align-normalizer-progress-fill', p: 'align-normalizer-progress-percent', c: 'align-normalizer-progress-count' },
		  { f: 'align-norm-modal-progress-fill', p: 'align-norm-modal-progress-percent', c: 'align-norm-modal-progress-count' } ].forEach(function(b) {
			if (dom[b.f]) dom[b.f].style.width = pct + '%';
			if (dom[b.p]) dom[b.p].textContent = pct + '%';
			if (dom[b.c]) dom[b.c].textContent = cnt;
		});
	}

	function updateRetryButtonUI() {
		var count = state.failedIds.length, text = 'Retry Failed (' + count + ')';
		[dom['align-normalizer-retry-btn'], dom['align-norm-modal-retry-btn']].forEach(function(btn) {
			if (btn) {
				btn.style.display = count > 0 ? 'inline-flex' : 'none';
				var span = btn.querySelector('.align-btn-text');
				if (span) span.textContent = text;
			}
		});
	}

	function setProcessingUI(processing) {
		state.isProcessing = processing;
		var sDisp = processing ? 'none' : 'inline-flex', cDisp = processing ? 'inline-flex' : 'none';
		[dom['align-normalizer-start-btn'], dom['align-norm-modal-start-btn']].forEach(function(btn) { if (btn) btn.style.display = sDisp; });
		[dom['align-normalizer-resume-btn'], dom['align-norm-modal-resume-btn']].forEach(function(btn) { if (btn && processing) btn.style.display = 'none'; });
		[dom['align-normalizer-cancel-btn'], dom['align-norm-modal-cancel-btn']].forEach(function(btn) { if (btn) btn.style.display = cDisp; });
	}

	function toggleAccordion(forceOpen) {
		var card = dom['align-normalizer-dashboard'], body = dom['align-normalizer-body'];
		if (!card || !body) return;
		var open = (typeof forceOpen === 'boolean') ? forceOpen : (body.style.display === 'none');
		body.style.display = open ? 'block' : 'none';
		card.classList.toggle('is-open', open);
	}

	function openModal() {
		if (dom['align-normalizer-modal']) { dom['align-normalizer-modal'].style.display = 'flex'; document.body.style.overflow = 'hidden'; }
	}
	function closeModal() {
		if (dom['align-normalizer-modal']) { dom['align-normalizer-modal'].style.display = 'none'; document.body.style.overflow = ''; }
	}

	function fetchMediaIds(mode, customIds) {
		var cfg = window.alignNormalizerData || {};
		setProcessingUI(true); state.isCanceled = false;
		appendLog('INIT', 'info', 'Fetching media attachments (mode: ' + mode + ')...');

		var fd = new FormData();
		fd.append('action', 'align_media_normalizer_get_ids');
		fd.append('security', state.currentNonce || cfg.nonce);
		fd.append('mode', mode || 'all');
		if (customIds) fd.append('ids', customIds);

		fetch(cfg.ajaxUrl, { method: 'POST', body: fd }).then(function(r) { return r.json(); }).then(function(res) {
			if (!res.success) {
				appendLog('ERROR', 'error', res.data && res.data.message ? res.data.message : 'Error fetching IDs');
				setProcessingUI(false); return;
			}
			state.queue = res.data.ids || []; state.total = state.queue.length;
			state.currentIndex = 0; state.changedCount = 0; state.cleanCount = 0; state.errorCount = 0;
			updateStats();
			if (state.total === 0) {
				appendLog('DONE', 'clean', 'No media items found to process.');
				setProcessingUI(false); return;
			}
			appendLog('START', 'info', 'Starting batch processing for ' + state.total + ' item(s)...');
			setTimeout(processNext, 50);
		}).catch(function(err) {
			appendLog('ERROR', 'error', 'Network error: ' + err.message);
			setProcessingUI(false);
		});
	}

	function processNext() {
		if (state.isCanceled) {
			appendLog('ABORT', 'error', 'Batch process canceled or paused.');
			setProcessingUI(false); return;
		}
		var cfg = window.alignNormalizerData || {};
		if (state.currentIndex >= state.total) {
			appendLog('COMPLETE', 'success', 'Finished! Formatted: ' + state.changedCount + ', Clean: ' + state.cleanCount + ', Errors: ' + state.errorCount);
			setProcessingUI(false);
			var resetFd = new FormData();
			resetFd.append('action', 'align_media_normalizer_reset');
			resetFd.append('security', state.currentNonce || cfg.nonce);
			fetch(cfg.ajaxUrl, { method: 'POST', body: resetFd });
			return;
		}

		var id = state.queue[state.currentIndex], fd = new FormData();
		fd.append('action', 'align_media_normalizer_process_single');
		fd.append('security', state.currentNonce || cfg.nonce);
		fd.append('id', id);

		fetch(cfg.ajaxUrl, { method: 'POST', body: fd }).then(function(r) { return r.json(); }).then(function(res) {
			if (res.data && res.data.current_nonce) state.currentNonce = res.data.current_nonce;
			state.currentIndex++;
			if (res.success && res.data) {
				if (res.data.changed) {
					state.changedCount++;
					appendLog('NORMALIZED', 'success', 'ID #' + id + ': ' + res.data.old_filename + ' -> ' + res.data.new_filename + ' (' + res.data.renamed_count + ' files)');
				} else {
					state.cleanCount++;
					appendLog('CLEAN', 'clean', 'ID #' + id + ': ' + res.data.new_filename + ' is already clean.');
				}
			} else {
				state.errorCount++;
				var msg = (res.data && res.data.message) ? res.data.message : 'Unknown error';
				appendLog('ERROR', 'error', 'ID #' + id + ': ' + msg);
				if (state.failedIds.indexOf(id) === -1) {
					state.failedIds.push(id);
					try { localStorage.setItem(STORAGE_KEY, JSON.stringify(state.failedIds)); } catch (e) {}
					updateRetryButtonUI();
				}
			}
			updateStats(); setTimeout(processNext, 50);
		}).catch(function(err) {
			state.currentIndex++; state.errorCount++;
			appendLog('ERROR', 'error', 'ID #' + id + ': Connection failed (' + err.message + ')');
			updateStats(); setTimeout(processNext, 50);
		});
	}

	function bindEvents() {
		var cfg = window.alignNormalizerData || {};
		if (dom['align-normalizer-header']) {
			dom['align-normalizer-header'].addEventListener('click', function(e) {
				if (!e.target.closest('button') && !e.target.closest('a')) toggleAccordion();
			});
		}

		var onStart = function() {
			var msg = cfg.strings && cfg.strings.confirmStart ? cfg.strings.confirmStart : 'Format all filenames?';
			if (confirm(msg)) fetchMediaIds('all');
		};
		[dom['align-normalizer-start-btn'], dom['align-norm-modal-start-btn']].forEach(function(b) { if (b) b.addEventListener('click', onStart); });
		[dom['align-normalizer-resume-btn'], dom['align-norm-modal-resume-btn']].forEach(function(b) { if (b) b.addEventListener('click', function() { fetchMediaIds('resume'); }); });
		[dom['align-normalizer-cancel-btn'], dom['align-norm-modal-cancel-btn']].forEach(function(b) { if (b) b.addEventListener('click', function() { state.isCanceled = true; }); });
		[dom['align-normalizer-retry-btn'], dom['align-norm-modal-retry-btn']].forEach(function(b) {
			if (b) b.addEventListener('click', function() { if (state.failedIds.length > 0) fetchMediaIds('custom', state.failedIds.join(',')); });
		});
		[dom['align-normalizer-clear-log-btn'], dom['align-norm-modal-clear-log-btn']].forEach(function(b) {
			if (b) b.addEventListener('click', function() {
				if (dom['align-normalizer-console-body']) dom['align-normalizer-console-body'].innerHTML = '';
				if (dom['align-norm-modal-console-body']) dom['align-norm-modal-console-body'].innerHTML = '';
			});
		});

		if (dom['align-normalizer-reset-checkpoint-btn']) {
			dom['align-normalizer-reset-checkpoint-btn'].addEventListener('click', function() {
				var fd = new FormData();
				fd.append('action', 'align_media_normalizer_reset');
				fd.append('security', state.currentNonce || cfg.nonce);
				fetch(cfg.ajaxUrl, { method: 'POST', body: fd }).then(function() {
					[dom['align-normalizer-resume-btn'], dom['align-norm-modal-resume-btn'], dom['align-normalizer-reset-checkpoint-btn']].forEach(function(el) { if (el) el.style.display = 'none'; });
					state.failedIds = [];
					try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
					updateRetryButtonUI();
					appendLog('RESET', 'info', 'Checkpoint cleared.');
				});
			});
		}

		if (dom['align-normalizer-modal-close']) dom['align-normalizer-modal-close'].addEventListener('click', closeModal);
		if (dom['align-normalizer-modal']) {
			dom['align-normalizer-modal'].addEventListener('click', function(e) { if (e.target === dom['align-normalizer-modal']) closeModal(); });
		}
		document.addEventListener('keydown', function(e) { if (e.key === 'Escape' || e.keyCode === 27) closeModal(); });

		if (cfg.isUploadPage) {
			var titleAction = document.querySelector('.wrap .page-title-action');
			if (titleAction) {
				var btn = document.createElement('a');
				btn.href = '#';
				btn.className = 'page-title-action align-normalizer-header-btn';
				btn.textContent = (cfg.strings && cfg.strings.btnFormat) ? cfg.strings.btnFormat : 'Format Filenames';
				btn.addEventListener('click', function(e) { e.preventDefault(); openModal(); });
				titleAction.parentNode.insertBefore(btn, titleAction.nextSibling);
			}

			document.addEventListener('click', function(e) {
				var link = e.target.closest('.align-format-name-link');
				if (link) {
					e.preventDefault();
					var id = link.getAttribute('data-id');
					if (id) { openModal(); fetchMediaIds('custom', id); }
				}
			});
		}

		if (cfg.prefilledIds && cfg.prefilledIds.length > 0) {
			if (cfg.isUploadPage) { openModal(); } else { toggleAccordion(true); }
			appendLog('QUEUE', 'info', 'Loaded ' + cfg.prefilledIds.length + ' item(s).');
			fetchMediaIds('custom', cfg.prefilledIds.join(','));
		}
	}

	document.addEventListener('DOMContentLoaded', function() {
		initDOM();
		bindEvents();
	});
})();
