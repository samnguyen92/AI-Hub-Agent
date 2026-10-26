/**
 * Align Theme Settings - Bulk AI Translator & SEO Client Engine
 *
 * Implements client-side asynchronous queue processing:
 * 1. Scans translatable items via AJAX.
 * 2. Iteratively processes items sequentially to prevent HTTP 504 timeouts.
 * 3. Translates blocks, then generates AI SEO metadata in target language.
 * 4. Updates real-time progress bar, item status badges, and execution log.
 *
 * @package Align
 */

document.addEventListener('DOMContentLoaded', function() {
	const card = document.getElementById('align-bulk-translator-card');
	if (!card) return;

	// UI Elements
	const $ = id => document.getElementById(id);
	const targetLangSelect = $('align_bulk_target_lang'), typePageCb = $('align_bulk_type_page'), typePostCb = $('align_bulk_type_post');
	const typeTemplatesCb  = $('align_bulk_type_templates'), typePartsCb = $('align_bulk_type_parts'), typeBlocksCb = $('align_bulk_type_blocks');
	const enableSeoCb      = $('align_bulk_enable_seo'), scanBtn = $('align-bulk-scan-btn'), startBtn = $('align-bulk-start-btn');
	const pauseBtn         = $('align-bulk-pause-btn'), resumeBtn = $('align-bulk-resume-btn'), stopBtn = $('align-bulk-stop-btn');
	const selectAllCb      = $('align-bulk-select-all'), searchInput = $('align-bulk-search'), filterPillsWrap = $('align-bulk-filter-pills');
	const statsWrap        = $('align-bulk-stats-summary'), statCompleted = $('align-bulk-stat-completed'), statTotal = $('align-bulk-stat-total'), statFailed = $('align-bulk-stat-failed');
	const progressWrap     = $('align-bulk-progress-wrap'), progressBar = $('align-bulk-progress-bar'), progressLabel = $('align-bulk-progress-label'), progressPercent = $('align-bulk-progress-percent');
	const tableWrap        = $('align-bulk-table-wrap'), tableBody = $('align-bulk-table-body'), logWrap = $('align-bulk-log-wrap'), logConsole = $('align-bulk-log-console'), clearLogBtn = $('align-bulk-clear-log-btn');
	const customInstInput  = $('align_bulk_custom_instructions'), loadHkPresetBtn = $('align-bulk-load-hk-preset-btn');

	// State
	let scannedItems = [], selectedIds = new Set(), queue = [], currentIndex = 0;
	let completedCount = 0, failedCount = 0, isRunning = false, isPaused = false, isStopped = false, activeFilter = 'all';

	function toggleItemCheckboxes(enable) {
		if (selectAllCb) selectAllCb.disabled = !enable;
		if (searchInput) searchInput.disabled = !enable;
		document.querySelectorAll('.align-bulk-item-select').forEach(cb => { cb.disabled = !enable; });
	}

	function syncTargetLanguagePrompt() {
		if (!targetLangSelect || !customInstInput) return;
		const opt = targetLangSelect.selectedOptions[0];
		if (!opt) return;
		const prompt = opt.dataset.prompt || '';
		const script = opt.dataset.script || '';
		if (prompt) {
			customInstInput.value = prompt;
		} else if (opt.value === 'zh' || opt.value.startsWith('zh') || script === 'traditional-hk') {
			customInstInput.value = 'Target: Hong Kong Traditional Chinese (繁體中文 - 香港). Use authentic HK corporate & insurance terms (e.g. 僱員補償保險, 索償, 質素, 強積金 MPF, 客戶). Formal written Chinese (書面語). Keep "Trusted Union" in English.';
		}
	}
	if (targetLangSelect) targetLangSelect.addEventListener('change', syncTargetLanguagePrompt);
	syncTargetLanguagePrompt();

	if (loadHkPresetBtn && customInstInput) {
		loadHkPresetBtn.addEventListener('click', function() {
			customInstInput.value = 'Target: Hong Kong Traditional Chinese (繁體中文 - 香港). Use authentic HK corporate & insurance terms (e.g. 僱員補償保險, 索償, 質素, 強積金 MPF, 客戶). Formal written Chinese (書面語). Zero Mainland buzzwords. Keep "Trusted Union" in English.';
		});
	}

	function updateSelectionUi() {
		const sel = selectedIds.size;
		if (startBtn) {
			startBtn.disabled = sel === 0 || isRunning;
			startBtn.textContent = sel > 0 ? `Start Bulk Translation (${sel})` : 'Select Items to Translate';
		}
		const visibleCbs = tableBody ? Array.from(tableBody.querySelectorAll('tr[id^="align-bulk-row-"]:not([style*="display: none"]) .align-bulk-item-select')) : [];
		const visibleChecked = visibleCbs.filter(cb => cb.checked).length;
		if (selectAllCb) {
			selectAllCb.checked = (visibleCbs.length > 0 && visibleChecked === visibleCbs.length);
			selectAllCb.indeterminate = (visibleChecked > 0 && visibleChecked < visibleCbs.length);
		}
		if (statTotal && !isRunning) statTotal.textContent = sel;
	}

	function updateFilterCounts() {
		const all = scannedItems.length;
		const needs = scannedItems.filter(i => i.translation_status !== 'translated').length;
		const done = scannedItems.filter(i => i.translation_status === 'translated').length;
		const cAll = document.getElementById('align-bulk-count-all');
		const cNeeds = document.getElementById('align-bulk-count-needs');
		const cDone = document.getElementById('align-bulk-count-done');
		if (cAll) cAll.textContent = all;
		if (cNeeds) cNeeds.textContent = needs;
		if (cDone) cDone.textContent = done;
	}

	function filterTableRows() {
		const q = (searchInput ? searchInput.value.trim().toLowerCase() : '');
		const rows = tableBody ? tableBody.querySelectorAll('tr[id^="align-bulk-row-"]') : [];
		let visible = 0;
		rows.forEach(tr => {
			const text = tr.textContent.toLowerCase();
			const status = tr.dataset.status || '';
			const matchesQuery = !q || text.includes(q);
			let matchesFilter = true;
			if (activeFilter === 'needs_trans') matchesFilter = (status !== 'translated');
			else if (activeFilter === 'translated') matchesFilter = (status === 'translated');

			const show = matchesQuery && matchesFilter;
			tr.style.display = show ? '' : 'none';
			if (show) visible++;
		});
		let noMatch = document.getElementById('align-bulk-no-match-row');
		if (rows.length > 0 && visible === 0) {
			if (!noMatch) {
				noMatch = document.createElement('tr');
				noMatch.id = 'align-bulk-no-match-row';
				noMatch.innerHTML = '<td colspan="7" style="text-align:center; padding: 20px; color: #64748b;">No matching items found.</td>';
				tableBody.appendChild(noMatch);
			}
			noMatch.style.display = '';
		} else if (noMatch) {
			noMatch.style.display = 'none';
		}
		updateSelectionUi();
	}

	if (searchInput) {
		searchInput.addEventListener('input', filterTableRows);
	}

	if (filterPillsWrap) {
		filterPillsWrap.addEventListener('click', function(e) {
			const btn = e.target.closest('.align-bulk-filter-pill');
			if (!btn) return;
			filterPillsWrap.querySelectorAll('.align-bulk-filter-pill').forEach(b => b.classList.remove('is-active'));
			btn.classList.add('is-active');
			activeFilter = btn.dataset.filter || 'all';
			filterTableRows();
		});
	}

	if (selectAllCb) {
		selectAllCb.addEventListener('change', function() {
			const checked = this.checked;
			const visibleCbs = tableBody ? tableBody.querySelectorAll('tr[id^="align-bulk-row-"]:not([style*="display: none"]) .align-bulk-item-select') : [];
			visibleCbs.forEach(cb => {
				cb.checked = checked;
				const id = String(cb.dataset.id);
				if (checked) selectedIds.add(id);
				else selectedIds.delete(id);
			});
			updateSelectionUi();
		});
	}

	function getNonce() {
		const nonceInput = document.querySelector('input[name="align_settings_nonce"]');
		return nonceInput ? nonceInput.value : '';
	}

	async function safeAjaxCall(fd) {
		const res = await fetch(ajaxurl, { method: 'POST', body: fd });
		const txt = await res.text();
		try {
			return JSON.parse(txt);
		} catch (e) {
			if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText || 'Gateway Timeout / Server Error'}`);
			throw new Error(`Invalid response: ${txt.substring(0, 60)}...`);
		}
	}

	function appendLog(msg, type = 'info') {
		if (!logConsole) return;
		if (logWrap && logWrap.style.display === 'none') logWrap.style.display = 'block';
		const ts = new Date().toLocaleTimeString();
		const colors = { success: '#34d399', error: '#f87171', warn: '#fbbf24', info: '#94a3b8' };
		const line = document.createElement('div');
		line.style.cssText = 'margin-bottom: 3px; word-break: break-all;';
		line.innerHTML = `<span style="color:#64748b;">[${ts}]</span> <span style="color:${colors[type] || colors.info};">${msg}</span>`;
		logConsole.appendChild(line);
		logConsole.scrollTop = logConsole.scrollHeight;
	}

	if (clearLogBtn) {
		clearLogBtn.addEventListener('click', function() {
			if (logConsole) logConsole.innerHTML = '';
		});
	}

	function updateProgress() {
		const total = queue.length, processed = completedCount + failedCount;
		const pct = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;
		if (progressBar) progressBar.style.width = pct + '%';
		if (progressPercent) progressPercent.textContent = pct + '%';
		if (progressLabel) {
			if (isStopped) progressLabel.textContent = `Stopped at ${processed} of ${total} items.`;
			else if (isPaused) progressLabel.textContent = `Paused (${processed} / ${total} items processed).`;
			else if (processed >= total && total > 0) progressLabel.textContent = `Completed! ${completedCount} succeeded, ${failedCount} failed.`;
			else progressLabel.textContent = `Processing item ${currentIndex + 1} of ${total}...`;
		}
		if (statCompleted) statCompleted.textContent = completedCount;
		if (statFailed) statFailed.textContent = failedCount;
		if (statTotal) statTotal.textContent = total;
	}

	// -------------------------------------------------------------------------
	// 1. Scan Translatable Content
	// -------------------------------------------------------------------------
	if (scanBtn) {
		scanBtn.addEventListener('click', function() {
			const targetLang = targetLangSelect ? targetLangSelect.value : '';
			if (!targetLang) {
				alert('Please configure and select a target language first.');
				return;
			}

			const postTypes = [];
			if (typePageCb && typePageCb.checked) postTypes.push('page');
			if (typePostCb && typePostCb.checked) postTypes.push('post');
			if (typeTemplatesCb && typeTemplatesCb.checked) postTypes.push('wp_template');
			if (typePartsCb && typePartsCb.checked) postTypes.push('wp_template_part');
			if (typeBlocksCb && typeBlocksCb.checked) postTypes.push('wp_block');

			if (postTypes.length === 0) {
				alert('Please select at least one content type.');
				return;
			}

			const scopeRadio = document.querySelector('input[name="align_bulk_scope"]:checked');
			const mode = scopeRadio ? scopeRadio.value : 'missing_only';

			scanBtn.disabled = true;
			scanBtn.textContent = 'Scanning...';
			appendLog(`Scanning candidate content for target language '${targetLang}' (Mode: ${mode})...`);

			const fd = new FormData();
			fd.append('action', 'align_bulk_scan_items');
			fd.append('security', getNonce());
			fd.append('target_lang', targetLang);
			fd.append('mode', mode);
			postTypes.forEach(t => fd.append('post_types[]', t));

			fetch(ajaxurl, { method: 'POST', body: fd })
				.then(res => res.json())
				.then(data => {
					scanBtn.disabled = false;
					scanBtn.textContent = 'Scan Translatable Content';

					if (!data || !data.success) {
						const err = (data && data.data && data.data.message) ? data.data.message : 'Scan failed.';
						appendLog(`Scan error: ${err}`, 'error');
						alert(err);
						return;
					}

					scannedItems = data.data.items || [];
					// Auto-select items that need translation (missing or untranslated content)
					selectedIds = new Set(scannedItems.filter(i => i.translation_status !== 'translated').map(i => String(i.id)));
					if (selectedIds.size === 0 && scannedItems.length > 0) {
						selectedIds = new Set(scannedItems.map(i => String(i.id)));
					}

					queue = [];
					currentIndex = 0;
					completedCount = 0;
					failedCount = 0;
					isRunning = false;
					isPaused = false;
					isStopped = false;

					appendLog(`Scan completed: Found ${scannedItems.length} candidate items.`, 'success');

					if (searchInput) searchInput.value = '';
					activeFilter = 'all';
					if (filterPillsWrap) filterPillsWrap.querySelectorAll('.align-bulk-filter-pill').forEach(b => b.classList.toggle('is-active', b.dataset.filter === 'all'));
					updateFilterCounts();
					renderTable();
					if (tableWrap) tableWrap.style.display = 'block';
					if (progressWrap) progressWrap.style.display = 'block';
					if (statsWrap) statsWrap.style.display = 'inline-block';
					updateSelectionUi();
					updateProgress();
				})
				.catch(err => {
					scanBtn.disabled = false;
					scanBtn.textContent = 'Scan Translatable Content';
					appendLog(`Network error during scan: ${err.message}`, 'error');
					alert('Scan network error: ' + err.message);
				});
		});
	}

	// -------------------------------------------------------------------------
	// 2. Render Status Table
	// -------------------------------------------------------------------------
	function renderTable() {
		if (!tableBody) return;
		tableBody.innerHTML = '';

		if (scannedItems.length === 0) {
			tableBody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 24px; color: #64748b;">No matching items found for the selected criteria.</td></tr>';
			return;
		}

		scannedItems.forEach((item) => {
			const tr = document.createElement('tr');
			tr.id = `align-bulk-row-${item.id}`;
			tr.dataset.status = item.translation_status || 'missing';

			const typeMap = { page: 'Page', wp_template: 'Layout Template', wp_template_part: 'Template Part', wp_block: 'CTA / Pattern' };
			const typeLabel = typeMap[item.post_type] || 'Post';

			let initialTransBadge = '';
			if (item.translation_status === 'translated') {
				initialTransBadge = `<span class="align-bulk-badge is-success" id="trans-badge-${item.id}">Translated (${item.char_count} chars)</span>`;
			} else if (item.translation_status === 'untranslated') {
				initialTransBadge = `<span class="align-bulk-badge is-untranslated" id="trans-badge-${item.id}">Untranslated (#${item.translation_id})</span>`;
			} else {
				initialTransBadge = `<span class="align-bulk-badge is-pending" id="trans-badge-${item.id}">Missing</span>`;
			}

			const isChecked = selectedIds.has(String(item.id));
			const targetLinks = item.translation_id ? `<a href="${item.target_view_url || item.view_url}" target="_blank" class="align-bulk-table-link">View Target</a> <a href="${item.target_edit_url || item.edit_url}" target="_blank" class="align-bulk-table-link">Edit Target</a>` : '';

			tr.innerHTML = `
				<td style="text-align: center; vertical-align: middle;"><input type="checkbox" class="align-bulk-item-select" data-id="${item.id}" ${isChecked ? 'checked="checked"' : ''} style="margin: 0;" /></td>
				<td style="font-weight: 600; color: #64748b;">${item.id}</td>
				<td><strong>${escapeHtml(item.title)}</strong></td>
				<td><span style="font-size: 11.5px; color: #475569;">${typeLabel}</span></td>
				<td id="cell-trans-${item.id}">${initialTransBadge}</td>
				<td id="cell-seo-${item.id}"><span class="align-bulk-badge is-pending" id="seo-badge-${item.id}">Pending</span></td>
				<td id="cell-action-${item.id}" style="text-align: right; white-space: nowrap;"><a href="${item.edit_url}" target="_blank" class="align-bulk-table-link">Edit EN</a> ${targetLinks}</td>
			`;
			tableBody.appendChild(tr);
		});

		tableBody.querySelectorAll('.align-bulk-item-select').forEach(cb => {
			cb.addEventListener('change', function() {
				if (this.checked) selectedIds.add(String(this.dataset.id));
				else selectedIds.delete(String(this.dataset.id));
				updateSelectionUi();
			});
		});
	}

	function escapeHtml(str) {
		const div = document.createElement('div');
		div.textContent = str || '';
		return div.innerHTML;
	}

	// -------------------------------------------------------------------------
	// 3. Sequential Queue Processing
	// -------------------------------------------------------------------------
	if (startBtn) {
		startBtn.addEventListener('click', function() {
			queue = scannedItems.filter(item => selectedIds.has(String(item.id)));
			if (queue.length === 0) {
				alert('Please select at least one item to translate.');
				return;
			}

			currentIndex = 0; completedCount = 0; failedCount = 0;
			isRunning = true; isPaused = false; isStopped = false;

			toggleItemCheckboxes(false);
			startBtn.style.display = 'none';
			if (pauseBtn) pauseBtn.style.display = 'inline-flex';
			if (stopBtn) stopBtn.style.display = 'inline-flex';
			if (resumeBtn) resumeBtn.style.display = 'none';
			if (scanBtn) scanBtn.disabled = true;
			updateProgress();
			appendLog(`Starting bulk translation queue for ${queue.length} selected items...`);
			processQueue();
		});
	}

	if (pauseBtn) pauseBtn.addEventListener('click', () => { isPaused = true; pauseBtn.style.display = 'none'; if (resumeBtn) resumeBtn.style.display = 'inline-flex'; appendLog('Queue paused by user.', 'warn'); updateProgress(); });
	if (resumeBtn) resumeBtn.addEventListener('click', () => { isPaused = false; resumeBtn.style.display = 'none'; if (pauseBtn) pauseBtn.style.display = 'inline-flex'; appendLog('Queue resumed by user.'); updateProgress(); processQueue(); });
	if (stopBtn) stopBtn.addEventListener('click', () => {
		if (!confirm('Are you sure you want to stop the bulk translation process?')) return;
		isStopped = true; isRunning = false; isPaused = false;
		stopBtn.style.display = 'none';
		if (pauseBtn) pauseBtn.style.display = 'none';
		if (resumeBtn) resumeBtn.style.display = 'none';
		if (startBtn) { startBtn.style.display = 'inline-flex'; startBtn.disabled = false; }
		if (scanBtn) scanBtn.disabled = false;
		toggleItemCheckboxes(true); updateSelectionUi(); appendLog('Queue stopped by user.', 'warn'); updateProgress();
	});

	function processQueue() {
		if (!isRunning || isStopped || isPaused) return;

		if (currentIndex >= queue.length) {
			isRunning = false;
			toggleItemCheckboxes(true);
			updateSelectionUi();
			appendLog(`All items finished! Completed: ${completedCount}, Failed: ${failedCount}.`, 'success');
			updateProgress();
			if (stopBtn) stopBtn.style.display = 'none';
			if (pauseBtn) pauseBtn.style.display = 'none';
			if (startBtn) { startBtn.style.display = 'inline-flex'; startBtn.disabled = false; startBtn.textContent = 'Re-Run Selected Items'; }
			if (scanBtn) scanBtn.disabled = false;
			return;
		}

		const item = queue[currentIndex];
		const targetLang = targetLangSelect ? targetLangSelect.value : 'zh';
		const generateSeo = enableSeoCb ? enableSeoCb.checked : true;
		const scopeRadio = document.querySelector('input[name="align_bulk_scope"]:checked');
		const overwrite = scopeRadio && scopeRadio.value === 'all';

		// UI update: item in progress
		const transCell = document.getElementById(`cell-trans-${item.id}`);
		const seoCell   = document.getElementById(`cell-seo-${item.id}`);
		const actionCell = document.getElementById(`cell-action-${item.id}`);

		if (transCell) {
			transCell.innerHTML = '<span class="align-bulk-badge is-working">Translating...</span>';
		}
		if (seoCell && generateSeo) {
			seoCell.innerHTML = '<span class="align-bulk-badge is-working">Waiting for text...</span>';
		}

		updateProgress();
		appendLog(`[${currentIndex + 1}/${queue.length}] Translating #${item.id} "${item.title}" to '${targetLang}'...`);

		const fd = new FormData();
		fd.append('action', 'align_bulk_process_item');
		fd.append('security', getNonce());
		fd.append('source_id', item.id);
		fd.append('target_lang', targetLang);
		fd.append('generate_seo', generateSeo ? 1 : 0);
		fd.append('defer_seo', generateSeo ? 1 : 0);
		fd.append('overwrite', overwrite ? 1 : 0);
		if (customInstInput && customInstInput.value.trim()) {
			fd.append('custom_instructions', customInstInput.value.trim());
		}

		safeAjaxCall(fd)
			.then(async resData => {
				if (!resData || !resData.success) {
					const msg = (resData && resData.data && resData.data.message) ? resData.data.message : 'Unknown error.';
					throw new Error(msg);
				}

				const result = resData.data;
				item.translation_status = 'translated';
				const row = document.getElementById(`align-bulk-row-${item.id}`);
				if (row) row.dataset.status = 'translated';

				if (transCell) {
					const cnt = result.text_count ? `${result.text_count} nodes` : 'Done';
					transCell.innerHTML = `<span class="align-bulk-badge is-success">Translated (${cnt})</span>`;
				}

				// Stage 2: Separate light SEO call to guarantee request never hits 120s gateway timeout
				if (result.seo_pending && result.target_id) {
					if (seoCell) seoCell.innerHTML = '<span class="align-bulk-badge is-working">Generating SEO...</span>';
					appendLog(`[SEO] Generating AI SEO metadata for #${result.target_id}...`);
					try {
						const seoFd = new FormData();
						seoFd.append('action', 'align_bulk_generate_item_seo'); seoFd.append('security', getNonce());
						seoFd.append('target_id', result.target_id); seoFd.append('target_lang', targetLang);
						const seoRes = await safeAjaxCall(seoFd);
						if (seoRes && seoRes.success && seoRes.data) {
							result.seo_generated = seoRes.data.saved; result.seo_score = seoRes.data.score;
							result.seo_keyword   = seoRes.data.keyword; result.seo_title = seoRes.data.title;
						}
					} catch (seoErr) {
						appendLog(`SEO warning on #${result.target_id}: ${seoErr.message}`, 'warn');
					}
				}

				if (seoCell) {
					if (result.seo_generated) {
						const scoreText = result.seo_score ? `Score: ${result.seo_score}/100` : 'Saved';
						const kwText = result.seo_keyword ? ` [${result.seo_keyword}]` : '';
						seoCell.innerHTML = `<span class="align-bulk-badge is-success">${scoreText}</span> <small style="color:#475569; font-size:11px;" title="${escapeHtml(result.seo_title)}">${escapeHtml(kwText)}</small>`;
					} else {
						seoCell.innerHTML = '<span class="align-bulk-badge is-skipped">Skipped</span>';
					}
				}

				if (actionCell && result.target_id) {
					actionCell.innerHTML = `<a href="${item.edit_url}" target="_blank" class="align-bulk-table-link">Edit EN</a> <a href="${result.view_url}" target="_blank" class="align-bulk-table-link">View Target</a> <a href="${result.edit_url}" target="_blank" class="align-bulk-table-link">Edit Target</a>`;
				}
				completedCount++;
				appendLog(`Success #${item.id} -> #${result.target_id}: "${result.translated_title}" (SEO: ${result.seo_generated ? 'Yes' : 'No'})`, 'success');
				updateFilterCounts();
				currentIndex++;
				updateProgress();
				setTimeout(processQueue, 400);
			})
			.catch(err => {
				failedCount++;
				if (transCell) transCell.innerHTML = `<span class="align-bulk-badge is-error" title="${escapeHtml(err.message)}">Failed</span>`;
				if (seoCell) seoCell.innerHTML = '<span class="align-bulk-badge is-skipped">Skipped</span>';
				appendLog(`Failed #${item.id}: ${err.message}`, 'error');
				currentIndex++;
				updateProgress();
				setTimeout(processQueue, 500);
			});
	}
});
