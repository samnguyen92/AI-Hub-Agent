/**
 * Align Theme - Translation Matrix Frontend JS
 *
 * Fetches and displays coverage matrix for EN ↔ ZH pages and posts,
 * provides live filtering, search, and 1-click action links.
 *
 * @package Align
 */
document.addEventListener('DOMContentLoaded', function() {
	const refreshBtn   = document.getElementById('align-matrix-refresh-btn');
	const typeFilter   = document.getElementById('align-matrix-type-filter');
	const statusFilter = document.getElementById('align-matrix-status-filter');
	const searchInput  = document.getElementById('align-matrix-search-input');
	const tbody        = document.getElementById('align-matrix-tbody');
	const metricTotal  = document.getElementById('align-matrix-metric-total');
	const metricComp   = document.getElementById('align-matrix-metric-complete');
	const metricMiss   = document.getElementById('align-matrix-metric-missing');

	let matrixData = [];
	let languagesList = [];
	let hasLoadedOnce = false;

	// Lazy load when matrix subtab is first viewed or on explicit load
	function checkAndLoadMatrix() {
		const matrixWrap = document.getElementById('subtab-seo-languages-matrix');
		if (matrixWrap && matrixWrap.style.display !== 'none' && !hasLoadedOnce) {
			hasLoadedOnce = true;
			loadMatrix();
		}
	}

	// Trigger on tab clicks or hash change
	document.addEventListener('click', function(e) {
		const pill = e.target.closest('.align-subnav-pill[data-subtab="seo-languages-matrix"]');
		if (pill) {
			setTimeout(checkAndLoadMatrix, 50);
		}
	});

	if (refreshBtn) {
		refreshBtn.addEventListener('click', () => loadMatrix());
	}
	if (typeFilter) {
		typeFilter.addEventListener('change', () => filterAndRender());
	}
	if (statusFilter) {
		statusFilter.addEventListener('change', () => filterAndRender());
	}
	if (searchInput) {
		searchInput.addEventListener('input', () => filterAndRender());
	}

	function loadMatrix() {
		if (!tbody) return;
		tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:24px; color:#64748b;">Loading translation matrix data…</td></tr>';
		if (refreshBtn) refreshBtn.disabled = true;

		const url = new URL(ajaxurl, window.location.origin);
		url.searchParams.set('action', 'align_get_translation_matrix');
		url.searchParams.set('nonce', window.alignSettingsNonce || document.querySelector('input[name="align_settings_nonce"]')?.value || '');

		fetch(url.toString())
			.then(res => res.json())
			.then(data => {
				if (refreshBtn) refreshBtn.disabled = false;
				if (!data.success) {
					tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:24px; color:#dc2626;">Failed to load data: ' + (data.data?.message || 'Error') + '</td></tr>';
					return;
				}

				const res = data.data;
				matrixData = res.matrix || [];
				languagesList = res.languages || [];

				if (metricTotal) metricTotal.textContent = res.total ?? matrixData.length;
				if (metricComp)  metricComp.textContent = res.fully_translated ?? 0;
				if (metricMiss)  metricMiss.textContent = res.missing_count ?? 0;

				filterAndRender();
			})
			.catch(err => {
				if (refreshBtn) refreshBtn.disabled = false;
				tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:24px; color:#dc2626;">Error: ' + err.message + '</td></tr>';
			});
	}

	function filterAndRender() {
		if (!tbody) return;
		const typeVal   = typeFilter ? typeFilter.value : 'all';
		const statusVal = statusFilter ? statusFilter.value : 'all';
		const query     = (searchInput ? searchInput.value : '').toLowerCase().trim();

		const filtered = matrixData.filter(item => {
			if (typeVal !== 'all' && item.post_type !== typeVal) return false;
			if (statusVal === 'complete' && !item.is_complete) return false;
			if (statusVal === 'missing' && item.is_complete) return false;
			if (query && !item.title.toLowerCase().includes(query)) return false;
			return true;
		});

		if (!filtered.length) {
			tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:24px; color:#94a3b8;">No matching content found.</td></tr>';
			return;
		}

		tbody.innerHTML = '';
		filtered.forEach(item => {
			const tr = document.createElement('tr');

			const typeBadge = item.post_type === 'page'
				? '<span style="font-size:10.5px; font-weight:600; padding:2px 6px; border-radius:3px; background:#f1f5f9; color:#475569;">Page</span>'
				: '<span style="font-size:10.5px; font-weight:600; padding:2px 6px; border-radius:3px; background:#e0f2fe; color:#0369a1;">Post</span>';

			// Secondary Language status (ZH)
			let zhHtml = '';
			const zhData = item.languages['zh'];
			if (zhData) {
				if (zhData.status === 'published') {
					zhHtml = `<div style="display:flex; align-items:center; gap:6px;">
						<span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:#10b981;"></span>
						<a href="${escapeHtml(zhData.url)}" target="_blank" style="font-weight:600; font-size:12.5px; color:#0284c7; text-decoration:none;">
							${escapeHtml(zhData.title || '中文版')}
						</a>
						<span style="font-size:10px; color:#64748b;">(ID #${zhData.post_id})</span>
					</div>`;
				} else if (zhData.status === 'draft') {
					zhHtml = `<div style="display:flex; align-items:center; gap:6px;">
						<span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:#f59e0b;"></span>
						<span style="font-size:12px; font-weight:600; color:#b45309;">Draft</span>
						<span style="font-size:11px; color:#64748b;">(${escapeHtml(zhData.title)})</span>
					</div>`;
				} else {
					zhHtml = `<div style="display:flex; align-items:center; gap:8px;">
						<span style="font-size:11.5px; color:#dc2626; font-weight:500;">Missing</span>
						<a href="${escapeHtml(zhData.create_url)}" class="button button-secondary" style="height:24px; line-height:22px; padding:0 8px; font-size:11px;">
							+ Create ZH
						</a>
					</div>`;
				}
			} else {
				zhHtml = '<span style="color:#94a3b8; font-size:11px;">—</span>';
			}

			// Actions
			const enId = item.id;
			const builderUrl = 'admin.php?page=align-builder&post_id=' + enId;
			const editUrl = 'post.php?post=' + enId + '&action=edit';

			tr.innerHTML = `
				<td>
					<div style="font-weight:600; font-size:13px; color:#0f172a; margin-bottom:2px;">
						${escapeHtml(item.title)}
					</div>
					<div style="font-size:11px; color:#64748b;">ID #${item.id}</div>
				</td>
				<td>${typeBadge}</td>
				<td>${zhHtml}</td>
				<td>
					<div style="display:flex; gap:6px; align-items:center;">
						<a href="${builderUrl}" class="button button-secondary" style="height:26px; line-height:24px; padding:0 8px; font-size:11px;" target="_blank">
							Builder
						</a>
						<a href="${editUrl}" class="button button-secondary" style="height:26px; line-height:24px; padding:0 8px; font-size:11px;" target="_blank">
							Edit
						</a>
					</div>
				</td>
			`;

			tbody.appendChild(tr);
		});
	}

	function escapeHtml(str) {
		return String(str || '').replace(/[&<>"']/g, function(m) {
			return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];
		});
	}

	// Initial check
	setTimeout(checkAndLoadMatrix, 100);
});
