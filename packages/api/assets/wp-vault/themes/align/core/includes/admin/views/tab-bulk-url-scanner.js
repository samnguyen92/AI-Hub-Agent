/**
 * Align Theme Settings - Bulk URL Problem Scanner & Batch Localizer Client Engine
 *
 * Provides standalone audit and 1-click batch fix for internal links across
 * translated pages, posts, templates, and reusable blocks without AI tokens.
 *
 * @package Align
 */

document.addEventListener('DOMContentLoaded', function() {
	const scanUrlsBtn   = document.getElementById('align-bulk-scan-urls-btn');
	const fixUrlsBtn    = document.getElementById('align-bulk-fix-urls-btn');
	const dismissBtn    = document.getElementById('align-bulk-dismiss-urls-btn');
	const issuesWrap    = document.getElementById('align-bulk-url-issues-wrap');
	const issuesText    = document.getElementById('align-bulk-url-issues-text');
	const targetLangSel = document.getElementById('align_bulk_target_lang');
	const logConsole    = document.getElementById('align-bulk-log-console');
	const logWrap       = document.getElementById('align-bulk-log-wrap');

	if (!scanUrlsBtn) return;

	let lastDetectedIssues = [];

	function log(msg, type = 'info') {
		if (!logConsole) return;
		if (logWrap && logWrap.style.display === 'none') {
			logWrap.style.display = 'block';
		}
		const color = type === 'error' ? '#ef4444' : (type === 'success' ? '#10b981' : (type === 'warn' ? '#f59e0b' : '#94a3b8'));
		const time = new Date().toLocaleTimeString('en-US', { hour12: false });
		const line = document.createElement('div');
		line.style.color = color;
		line.textContent = `[${time}] ${msg}`;
		logConsole.appendChild(line);
		logConsole.scrollTop = logConsole.scrollHeight;
	}

	// 1. Handle Scan URL Problems
	scanUrlsBtn.addEventListener('click', function(e) {
		e.preventDefault();
		const targetLang = targetLangSel ? targetLangSel.value : 'zh';
		const nonce = (window.alignSettings && window.alignSettings.nonce) 
			|| (window.alignAdminSettings && window.alignAdminSettings.nonce)
			|| document.getElementById('align_settings_nonce')?.value || '';

		scanUrlsBtn.disabled = true;
		const origText = scanUrlsBtn.textContent;
		scanUrlsBtn.textContent = 'Scanning URLs...';

		if (issuesWrap) issuesWrap.style.display = 'none';
		log(`[Align URL Scanner] Auditing internal links for language '${targetLang}'...`, 'info');

		const formData = new FormData();
		formData.append('action', 'align_bulk_scan_url_problems');
		formData.append('security', nonce);
		formData.append('target_lang', targetLang);

		fetch(ajaxurl, {
			method: 'POST',
			body: formData
		})
		.then(r => r.json())
		.then(res => {
			scanUrlsBtn.disabled = false;
			scanUrlsBtn.textContent = origText;

			if (!res.success) {
				log(`[Align URL Scanner] Scan failed: ${res.data?.message || 'Unknown error'}`, 'error');
				alert(res.data?.message || 'URL Scan failed.');
				return;
			}

			const data = res.data;
			lastDetectedIssues = data.items || [];

			if (data.total_issues_count > 0) {
				log(`[Align URL Scanner] Found ${data.total_issues_count} unlocalized links across ${data.items_count} item(s).`, 'warn');
				if (issuesWrap && issuesText) {
					issuesWrap.style.display = 'flex';
					issuesWrap.style.background = '#fffbeb';
					issuesWrap.style.borderColor = '#fde68a';
					issuesText.style.color = '#92400e';
					issuesText.textContent = `Found ${data.total_issues_count} unlocalized internal link(s) across ${data.items_count} item(s) (e.g. still pointing to source English URLs).`;
					if (fixUrlsBtn) fixUrlsBtn.style.display = 'inline-flex';
				}

				// Highlight matching rows in table if present
				lastDetectedIssues.forEach(item => {
					log(` -> Item #${item.id} "${item.title}" (${item.item_type}): ${item.issues_count} unlocalized links`, 'warn');
					const row = document.getElementById(`align-bulk-row-${item.id}`);
					if (row) {
						const transCell = row.querySelector('.align-bulk-trans-status');
						if (transCell) {
							transCell.innerHTML += `<div style="margin-top: 3px;"><span class="align-bulk-badge is-untranslated" style="font-size: 10px;">${item.issues_count} URL issues</span></div>`;
						}
					}
				});
			} else {
				log(`[Align URL Scanner] Clean! All ${data.scanned_count} translated items have 100% localized internal URLs.`, 'success');
				if (issuesWrap && issuesText) {
					issuesWrap.style.display = 'flex';
					issuesWrap.style.background = '#ecfdf5';
					issuesWrap.style.borderColor = '#a7f3d0';
					issuesText.style.color = '#065f46';
					issuesText.textContent = `All ${data.scanned_count} translated item(s) have 100% localized internal URLs. No issues found!`;
					if (fixUrlsBtn) fixUrlsBtn.style.display = 'none';
				}
			}
		})
		.catch(err => {
			scanUrlsBtn.disabled = false;
			scanUrlsBtn.textContent = origText;
			log(`[Align URL Scanner] Network error: ${err.message}`, 'error');
		});
	});

	// 2. Handle Fix All URL Problems Now
	if (fixUrlsBtn) {
		fixUrlsBtn.addEventListener('click', function(e) {
			e.preventDefault();
			if (!lastDetectedIssues.length) return;

			const targetLang = targetLangSel ? targetLangSel.value : 'zh';
			const nonce = (window.alignSettings && window.alignSettings.nonce) 
				|| (window.alignAdminSettings && window.alignAdminSettings.nonce)
				|| document.getElementById('align_settings_nonce')?.value || '';

			fixUrlsBtn.disabled = true;
			const origText = fixUrlsBtn.textContent;
			fixUrlsBtn.textContent = 'Fixing URLs...';

			const itemIds = lastDetectedIssues.map(it => it.id);
			log(`[Align URL Scanner] Batch fixing URLs for ${itemIds.length} items (0 AI tokens)...`, 'info');

			const formData = new FormData();
			formData.append('action', 'align_bulk_fix_url_problems');
			formData.append('security', nonce);
			formData.append('target_lang', targetLang);
			itemIds.forEach(id => formData.append('item_ids[]', id));

			fetch(ajaxurl, {
				method: 'POST',
				body: formData
			})
			.then(r => r.json())
			.then(res => {
				fixUrlsBtn.disabled = false;
				fixUrlsBtn.textContent = origText;

				if (!res.success) {
					log(`[Align URL Scanner] Fix failed: ${res.data?.message || 'Unknown error'}`, 'error');
					alert(res.data?.message || 'Failed to fix URLs.');
					return;
				}

				log(`[Align URL Scanner] Fixed! ${res.data.message}`, 'success');
				if (issuesWrap && issuesText) {
					issuesWrap.style.background = '#ecfdf5';
					issuesWrap.style.borderColor = '#a7f3d0';
					issuesText.style.color = '#065f46';
					issuesText.textContent = `Success! All internal URLs have been rewritten and localized for ${res.data.fixed_count} item(s).`;
					fixUrlsBtn.style.display = 'none';
				}

				lastDetectedIssues = [];
			})
			.catch(err => {
				fixUrlsBtn.disabled = false;
				fixUrlsBtn.textContent = origText;
				log(`[Align URL Scanner] Fix network error: ${err.message}`, 'error');
			});
		});
	}

	// 3. Dismiss button
	if (dismissBtn) {
		dismissBtn.addEventListener('click', function(e) {
			e.preventDefault();
			if (issuesWrap) issuesWrap.style.display = 'none';
		});
	}
});
