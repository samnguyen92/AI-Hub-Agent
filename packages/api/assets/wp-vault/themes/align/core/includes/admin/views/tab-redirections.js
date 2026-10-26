/**
 * Align Theme - Rank Math Redirection Importer Frontend JS
 *
 * Handles live parsing, dry-run preview rendering, batch execution,
 * search filtering, and deletion of redirection rules.
 *
 * @package Align
 */
document.addEventListener('DOMContentLoaded', function() {
	const previewBtn   = document.getElementById('align-redirection-preview-btn');
	const executeBtn   = document.getElementById('align-redirection-execute-btn');
	const clearBtn     = document.getElementById('align-redirection-clear-btn');
	const textarea     = document.getElementById('align-redirection-paste');
	const fileInput    = document.getElementById('align-redirection-file');
	const defaultCode  = document.getElementById('align-redirection-default-code');
	const overwriteChk = document.getElementById('align-redirection-overwrite');
	const statusSpan   = document.getElementById('align-redirection-status');
	const previewWrap  = document.getElementById('align-redirection-preview-wrap');
	const summaryP     = document.getElementById('align-redirection-summary');
	const previewTbody = document.getElementById('align-redirection-preview-tbody');

	// Active Redirections Elements
	const activeHeader = document.getElementById('align-active-redirections-header');
	const activeBody   = document.getElementById('align-active-redirections-body');
	const activeTbody  = document.getElementById('align-active-redirections-tbody');
	const searchInput  = document.getElementById('align-redirections-search-input');
	const searchBtn    = document.getElementById('align-redirections-search-btn');
	const refreshBtn   = document.getElementById('align-redirections-refresh-btn');

	let validatedItems = [];

	// File Upload Handler
	if (fileInput && textarea) {
		fileInput.addEventListener('change', function(e) {
			const file = e.target.files[0];
			if (!file) return;
			const reader = new FileReader();
			reader.onload = function(evt) {
				textarea.value = evt.target.result;
				if (statusSpan) {
					statusSpan.textContent = 'File loaded (' + file.name + '). Click "Analyze & Preview Rules".';
					statusSpan.style.color = '#0284c7';
				}
			};
			reader.readAsText(file);
		});
	}

	// Clear Button
	if (clearBtn && textarea) {
		clearBtn.addEventListener('click', function() {
			textarea.value = '';
			if (fileInput) fileInput.value = '';
			if (previewWrap) previewWrap.style.display = 'none';
			if (statusSpan) statusSpan.textContent = '';
			validatedItems = [];
		});
	}

	// Preview Dry Run Button
	if (previewBtn && textarea) {
		previewBtn.addEventListener('click', function() {
			const rawText = textarea.value.trim();
			if (!rawText) {
				alert('Please paste at least one redirection line or upload a CSV file.');
				return;
			}

			previewBtn.disabled = true;
			if (statusSpan) {
				statusSpan.textContent = 'Analyzing and validating rules against Rank Math database…';
				statusSpan.style.color = '#64748b';
			}

			const formData = new FormData();
			formData.append('action', 'align_preview_redirections');
			formData.append('nonce', window.alignSettingsNonce || document.querySelector('input[name="align_settings_nonce"]')?.value || '');
			formData.append('raw_text', rawText);

			fetch(ajaxurl, {
				method: 'POST',
				body: formData
			})
			.then(res => res.json())
			.then(data => {
				previewBtn.disabled = false;
				if (!data.success) {
					if (statusSpan) {
						statusSpan.textContent = data.data?.message || 'Validation failed.';
						statusSpan.style.color = '#dc2626';
					}
					return;
				}

				const res = data.data;
				validatedItems = res.items || [];

				if (statusSpan) {
					statusSpan.textContent = 'Analysis complete.';
					statusSpan.style.color = '#15803d';
				}

				if (summaryP) {
					summaryP.innerHTML = '<strong>' + res.total + '</strong> total rule(s) parsed: ' +
						'<span style="color:#15803d; font-weight:600;">' + res.valid + ' ready to import</span>, ' +
						'<span style="color:#b45309; font-weight:600;">' + res.duplicates + ' existing/duplicate</span>, ' +
						'<span style="color:#dc2626; font-weight:600;">' + res.errors + ' error(s)</span>.';
				}

				renderPreviewRows(validatedItems);
				if (previewWrap) {
					previewWrap.style.display = 'block';
					previewWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
				}
			})
			.catch(err => {
				previewBtn.disabled = false;
				if (statusSpan) {
					statusSpan.textContent = 'Request error: ' + err.message;
					statusSpan.style.color = '#dc2626';
				}
			});
		});
	}

	// Render Preview Table Rows
	function renderPreviewRows(items) {
		if (!previewTbody) return;
		previewTbody.innerHTML = '';

		if (!items.length) {
			previewTbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:14px; color:#94a3b8;">No valid lines found.</td></tr>';
			return;
		}

		items.forEach(it => {
			const tr = document.createElement('tr');

			let badgeHtml = '';
			if (it.status === 'valid') {
				badgeHtml = '<span style="display:inline-block; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600; background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0;">Ready</span>';
			} else if (it.status === 'duplicate') {
				badgeHtml = '<span style="display:inline-block; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600; background:#fffbeb; color:#b45309; border:1px solid #fef3c7;" title="' + (it.message || '') + '">Existing</span> <span style="font-size:11px; color:#64748b;">' + (it.message || '') + '</span>';
			} else {
				badgeHtml = '<span style="display:inline-block; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600; background:#fef2f2; color:#dc2626; border:1px solid #fecaca;">Error</span> <span style="font-size:11px; color:#dc2626;">' + (it.message || '') + '</span>';
			}

			tr.innerHTML = `
				<td style="font-size:12px; color:#64748b;">${it.line}</td>
				<td><code style="font-size:11.5px; color:#0f172a;">${escapeHtml(it.source)}</code></td>
				<td><code style="font-size:11.5px; color:#0284c7;">${escapeHtml(it.destination || '—')}</code></td>
				<td><span style="font-size:12px; font-weight:600;">${it.code}</span></td>
				<td>${badgeHtml}</td>
			`;
			previewTbody.appendChild(tr);
		});
	}

	// Execute Import Button
	if (executeBtn) {
		executeBtn.addEventListener('click', function() {
			if (!validatedItems.length) {
				alert('No items ready to import.');
				return;
			}

			const validItems = validatedItems.filter(it => it.status !== 'error');
			if (!validItems.length) {
				alert('No valid items found to import. Please resolve the errors first.');
				return;
			}

			const isOverwrite = overwriteChk && overwriteChk.checked;
			const msg = isOverwrite
				? 'Are you sure you want to import ' + validItems.length + ' redirection rule(s)? Existing matches will be OVERWRITTEN.'
				: 'Are you sure you want to import ' + validItems.length + ' redirection rule(s)? Existing matches will be SKIPPED.';

			if (!confirm(msg)) {
				return;
			}

			executeBtn.disabled = true;
			executeBtn.textContent = 'Importing to Rank Math…';

			const formData = new FormData();
			formData.append('action', 'align_import_redirections');
			formData.append('nonce', window.alignSettingsNonce || document.querySelector('input[name="align_settings_nonce"]')?.value || '');
			formData.append('overwrite', isOverwrite ? '1' : '0');
			formData.append('items', JSON.stringify(validItems));

			fetch(ajaxurl, {
				method: 'POST',
				body: formData
			})
			.then(res => res.json())
			.then(data => {
				executeBtn.disabled = false;
				executeBtn.textContent = 'Import to Rank Math Now';

				if (!data.success) {
					alert('Import failed: ' + (data.data?.message || 'Unknown error'));
					return;
				}

				const res = data.data;
				alert(res.message || 'Import successful!');

				// Clear input & preview
				if (textarea) textarea.value = '';
				if (previewWrap) previewWrap.style.display = 'none';
				validatedItems = [];

				// Refresh active table if open
				if (activeBody && activeBody.style.display !== 'none') {
					loadActiveRedirections();
				}
			})
			.catch(err => {
				executeBtn.disabled = false;
				executeBtn.textContent = 'Import to Rank Math Now';
				alert('Error during import: ' + err.message);
			});
		});
	}

	// Active Redirections Accordion Toggle
	if (activeHeader && activeBody) {
		activeHeader.addEventListener('click', function() {
			const isOpen = activeBody.style.display !== 'none';
			activeBody.style.display = isOpen ? 'none' : 'block';
			const arrow = activeHeader.querySelector('.align-accordion-arrow');
			if (arrow) {
				arrow.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
			}
			if (!isOpen) {
				loadActiveRedirections();
			}
		});
	}

	// Search & Refresh
	if (searchBtn && searchInput) {
		searchBtn.addEventListener('click', () => loadActiveRedirections(searchInput.value.trim()));
		searchInput.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); loadActiveRedirections(searchInput.value.trim()); } });
	}
	if (refreshBtn) {
		refreshBtn.addEventListener('click', () => loadActiveRedirections(searchInput ? searchInput.value.trim() : ''));
	}

	// Load Active Redirections via AJAX
	function loadActiveRedirections(search = '') {
		if (!activeTbody) return;
		activeTbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:16px; color:#64748b;">Loading redirections…</td></tr>';

		const url = new URL(ajaxurl, window.location.origin);
		url.searchParams.set('action', 'align_get_redirections');
		url.searchParams.set('nonce', window.alignSettingsNonce || document.querySelector('input[name="align_settings_nonce"]')?.value || '');
		if (search) url.searchParams.set('search', search);

		fetch(url.toString())
			.then(res => res.json())
			.then(data => {
				if (!data.success || !data.data?.redirections?.length) {
					activeTbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:16px; color:#94a3b8;">No active redirections found.</td></tr>';
					return;
				}

				activeTbody.innerHTML = '';
				data.data.redirections.forEach(r => {
					const tr = document.createElement('tr');
					tr.id = 'align-redir-row-' + r.id;
					tr.innerHTML = `
						<td style="font-size:11.5px; color:#64748b;">#${r.id}</td>
						<td><code style="font-size:11.5px; color:#0f172a;">${escapeHtml(r.source)}</code></td>
						<td><code style="font-size:11.5px; color:#0284c7;">${escapeHtml(r.url_to || '—')}</code></td>
						<td><span style="font-size:12px; font-weight:600;">${r.header_code}</span></td>
						<td style="font-size:12px; color:#64748b;">${r.hits}</td>
						<td>
							<button type="button" class="button button-danger align-delete-redir-btn" data-id="${r.id}" style="height:26px; line-height:24px; padding:0 8px; font-size:11px;">
								Delete
							</button>
						</td>
					`;
					activeTbody.appendChild(tr);
				});

				// Bind delete buttons
				activeTbody.querySelectorAll('.align-delete-redir-btn').forEach(btn => {
					btn.addEventListener('click', function(e) {
						e.stopPropagation();
						const id = this.getAttribute('data-id');
						if (!confirm('Are you sure you want to delete redirection #' + id + '?')) {
							return;
						}
						this.disabled = true;

						const fd = new FormData();
						fd.append('action', 'align_delete_redirection');
						fd.append('nonce', window.alignSettingsNonce || document.querySelector('input[name="align_settings_nonce"]')?.value || '');
						fd.append('id', id);

						fetch(ajaxurl, { method: 'POST', body: fd })
							.then(res => res.json())
							.then(delData => {
								if (delData.success) {
									const row = document.getElementById('align-redir-row-' + id);
									if (row) row.remove();
								} else {
									alert('Failed to delete: ' + (delData.data?.message || 'Error'));
									btn.disabled = false;
								}
							});
					});
				});
			})
			.catch(err => {
				activeTbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:16px; color:#dc2626;">Error loading data: ' + err.message + '</td></tr>';
			});
	}

	// 404 URL Monitor Elements
	const monitorHeader = document.getElementById('align-404-monitor-header');
	const monitorBody   = document.getElementById('align-404-monitor-body');
	const monitorTbody  = document.getElementById('align-404-tbody');
	const countBadge    = document.getElementById('align-404-count-badge');
	const search404Inp  = document.getElementById('align-404-search-input');
	const search404Btn  = document.getElementById('align-404-search-btn');
	const refresh404Btn = document.getElementById('align-404-refresh-btn');
	const clear404Btn   = document.getElementById('align-404-clear-btn');

	if (monitorHeader && monitorBody) {
		monitorHeader.addEventListener('click', function() {
			const isOpen = monitorBody.style.display !== 'none';
			monitorBody.style.display = isOpen ? 'none' : 'block';
			const arrow = monitorHeader.querySelector('.align-accordion-arrow');
			if (arrow) arrow.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
			if (!isOpen) load404Logs();
		});
	}

	if (search404Btn && search404Inp) {
		search404Btn.addEventListener('click', () => load404Logs(search404Inp.value.trim()));
		search404Inp.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); load404Logs(search404Inp.value.trim()); } });
	}
	if (refresh404Btn) {
		refresh404Btn.addEventListener('click', () => load404Logs(search404Inp ? search404Inp.value.trim() : ''));
	}

	if (clear404Btn) {
		clear404Btn.addEventListener('click', function() {
			if (!confirm('Are you sure you want to clear all 404 logs?')) return;
			const fd = new FormData();
			fd.append('action', 'align_clear_404_logs');
			fd.append('nonce', window.alignSettingsNonce || document.querySelector('input[name="align_settings_nonce"]')?.value || '');
			fetch(ajaxurl, { method: 'POST', body: fd })
				.then(r => r.json())
				.then(d => {
					if (d.success) load404Logs();
				});
		});
	}

	function load404Logs(search = '') {
		if (!monitorTbody) return;
		monitorTbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:16px; color:#64748b;">Loading 404 logs…</td></tr>';
		const url = new URL(ajaxurl, window.location.origin);
		url.searchParams.set('action', 'align_get_404_logs');
		url.searchParams.set('nonce', window.alignSettingsNonce || document.querySelector('input[name="align_settings_nonce"]')?.value || '');
		if (search) url.searchParams.set('search', search);

		fetch(url.toString())
			.then(r => r.json())
			.then(data => {
				if (!data.success || !data.data?.logs?.length) {
					monitorTbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:16px; color:#94a3b8;">No 404 errors recorded yet.</td></tr>';
					if (countBadge) countBadge.textContent = '(0)';
					return;
				}
				const logs = data.data.logs;
				if (countBadge) countBadge.textContent = '(' + logs.length + ')';
				monitorTbody.innerHTML = '';
				logs.forEach((item, idx) => {
					const tr = document.createElement('tr');
					tr.id = 'align-404-row-' + idx;
					tr.innerHTML = `
						<td><code style="font-size:11.5px; color:#dc2626; word-break:break-all;">${escapeHtml(item.path)}</code></td>
						<td style="text-align:center;"><span style="display:inline-block; padding:1px 6px; border-radius:10px; font-size:11px; font-weight:600; background:#f1f5f9; color:#475569;">${item.hits}</span></td>
						<td><span style="font-size:11.5px; color:#64748b; word-break:break-all;">${item.referer ? escapeHtml(item.referer) : '—'}</span></td>
						<td style="font-size:11px; color:#64748b;">${escapeHtml(item.last_seen || item.first_seen || '')}</td>
						<td style="text-align:right; white-space:nowrap;">
							<button type="button" class="button button-primary align-404-redirect-btn" data-path="${escapeHtml(item.path)}" style="height:26px; line-height:24px; padding:0 8px; font-size:11px;">
								Redirect
							</button>
							<button type="button" class="button button-secondary align-404-copy-btn" data-path="${escapeHtml(item.path)}" title="Paste into Importer" style="height:26px; line-height:24px; padding:0 6px; font-size:11px;">
								To Form
							</button>
							<button type="button" class="button button-danger align-404-del-btn" data-path="${escapeHtml(item.path)}" style="height:26px; line-height:24px; padding:0 6px; font-size:11px;">
								✕
							</button>
						</td>
					`;
					monitorTbody.appendChild(tr);
				});

				// Bind Redirect buttons
				monitorTbody.querySelectorAll('.align-404-redirect-btn').forEach(btn => {
					btn.addEventListener('click', function() {
						const path = this.getAttribute('data-path');
						const dest = prompt('Create 301 Redirect for:\n' + path + '\n\nEnter Destination URL (e.g. /new-page/ or full URL):', '/');
						if (!dest || !dest.trim()) return;

						btn.disabled = true;
						btn.textContent = 'Saving…';

						const fd = new FormData();
						fd.append('action', 'align_create_redirect_from_404');
						fd.append('nonce', window.alignSettingsNonce || document.querySelector('input[name="align_settings_nonce"]')?.value || '');
						fd.append('source_path', path);
						fd.append('target_url', dest.trim());
						fd.append('code', '301');

						fetch(ajaxurl, { method: 'POST', body: fd })
							.then(r => r.json())
							.then(res => {
								if (res.success) {
									alert(res.data?.message || 'Redirect created successfully!');
									load404Logs(search404Inp ? search404Inp.value.trim() : '');
									if (activeBody && activeBody.style.display !== 'none') loadActiveRedirections();
								} else {
									alert('Failed: ' + (res.data?.message || 'Unknown error'));
									btn.disabled = false;
									btn.textContent = 'Redirect';
								}
							})
							.catch(e => {
								alert('Network error: ' + e.message);
								btn.disabled = false;
								btn.textContent = 'Redirect';
							});
					});
				});

				// Bind To Form buttons
				monitorTbody.querySelectorAll('.align-404-copy-btn').forEach(btn => {
					btn.addEventListener('click', function() {
						const path = this.getAttribute('data-path');
						if (textarea) {
							const current = textarea.value.trim();
							textarea.value = (current ? current + '\n' : '') + path + '\t/\t301';
							textarea.scrollIntoView({ behavior: 'smooth', block: 'center' });
							textarea.focus();
						}
					});
				});

				// Bind Delete buttons
				monitorTbody.querySelectorAll('.align-404-del-btn').forEach(btn => {
					btn.addEventListener('click', function() {
						const path = this.getAttribute('data-path');
						const fd = new FormData();
						fd.append('action', 'align_delete_404_log');
						fd.append('nonce', window.alignSettingsNonce || document.querySelector('input[name="align_settings_nonce"]')?.value || '');
						fd.append('path', path);
						fetch(ajaxurl, { method: 'POST', body: fd })
							.then(r => r.json())
							.then(res => {
								if (res.success) {
									this.closest('tr')?.remove();
								}
							});
					});
				});
			})
			.catch(err => {
				monitorTbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:16px; color:#dc2626;">Error: ' + err.message + '</td></tr>';
			});
	}

	function escapeHtml(str) {
		return String(str || '').replace(/[&<>"']/g, function(m) {
			return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];
		});
	}
});
