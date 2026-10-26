/**
 * Load Custom Assets Tab Controller
 * Handles 3-type filtering (Font, CSS, JS, All), Drag & Drop row reordering, and AJAX uploads/deletions.
 *
 * @package Align
 */

document.addEventListener('DOMContentLoaded', function() {
	const tbody = document.getElementById('align-assets-tbody');
	if (!tbody) return;

	const addMediaBtn = document.getElementById('align-add-media-assets-btn');
	const addManualBtn = document.getElementById('align-add-manual-asset-btn');
	const nativeFileInput = document.getElementById('align-native-file-input');
	const singleFileInput = document.getElementById('align-single-file-input');
	const filterButtons = document.querySelectorAll('.align-asset-filter-btn');
	const alignNonce = (typeof alignSettingsConfig !== 'undefined' && alignSettingsConfig.nonce) ? alignSettingsConfig.nonce : (window.alignNonce || '');
	const ajaxUrl = (typeof alignSettingsConfig !== 'undefined' && alignSettingsConfig.ajaxUrl) ? alignSettingsConfig.ajaxUrl : (window.ajaxurl || '/wp-admin/admin-ajax.php');
	const isGlobalDelayEnabled = document.body.dataset.alignDelayAssets === '1' || window.alignIsGlobalDelayEnabled === true;

	const FONT_WEIGHTS = {
		'100': 'Thin (100)', '200': 'Extra Light (200)', '300': 'Light (300)',
		'400': 'Regular (400)', '500': 'Medium (500)', '600': 'Semi Bold (600)',
		'700': 'Bold (700)', '800': 'Extra Bold (800)', '900': 'Black (900)'
	};

	let currentFilter = 'all';
	let activeRowForSingleUpload = null;
	let draggedRow = null;

	function detectFileType(url) {
		if (!url) return 'css';
		const clean = url.split('?')[0].split('#')[0].toLowerCase();
		if (clean.endsWith('.js') || url.includes('/api/js') || url.includes('.js?')) return 'js';
		if (clean.endsWith('.woff') || clean.endsWith('.woff2') || clean.endsWith('.ttf') || clean.endsWith('.otf') || clean.endsWith('.eot')) return 'font';
		return 'css';
	}

	function detectFontWeight(url) {
		if (!url) return '400';
		const l = url.toLowerCase();
		if (l.includes('extralight') || l.includes('ultra-light') || l.includes('ultralight') || l.includes('200')) return '200';
		if (l.includes('semibold') || l.includes('semi-bold') || l.includes('demibold') || l.includes('demi-bold') || l.includes('600')) return '600';
		if (l.includes('extrabold') || l.includes('extra-bold') || l.includes('ultrabold') || l.includes('800')) return '800';
		if (l.includes('thin') || l.includes('100')) return '100';
		if (l.includes('light') || l.includes('300')) return '300';
		if (l.includes('bold') || l.includes('700')) return '700';
		if (l.includes('black') || l.includes('heavy') || l.includes('900')) return '900';
		return (l.includes('medium') || l.includes('500')) ? '500' : '400';
	}

	function getFilenameFromUrl(url) {
		if (!url || typeof url !== 'string') return '';
		const parts = url.split('?')[0].split('#')[0].replace(/\/+$/, '').split('/');
		const fn = parts.pop() || '';
		try { return decodeURIComponent(fn); } catch (e) { return fn; }
	}

	function isCustomEditableAsset(url, row = null) {
		if (!url || typeof url !== 'string') return false;
		if (row) {
			if (row.classList.contains('align-core-asset-row') || row.getAttribute('data-is-core') === '1') return false;
			const typeSelect = row.querySelector('.align-asset-type-select');
			if ((typeSelect ? typeSelect.value : row.getAttribute('data-type')) === 'font') return false;
		}
		const clean = url.split('?')[0].split('#')[0].toLowerCase();
		const ext = clean.split('.').pop();
		if (ext !== 'css' && ext !== 'js') return false;
		if (['woff', 'woff2', 'ttf', 'otf', 'eot'].includes(ext)) return false;
		if ((url.startsWith('http://') || url.startsWith('https://') || url.startsWith('//')) && !url.includes('/wp-content/themes/')) return false;
		return !clean.includes('/align/core/');
	}

	function getThemeEditorUrl(url, row = null) {
		if (!isCustomEditableAsset(url, row)) return '';
		const m = url.match(/\/wp-content\/themes\/([^\/]+)\/(.+)$/i);
		if (m) return `/wp-admin/theme-editor.php?file=${encodeURIComponent(m[2])}&theme=${encodeURIComponent(m[1])}`;
		const cleanPath = url.replace(/^\/+/, '');
		if (cleanPath) {
			const activeTheme = (typeof alignSettingsConfig !== 'undefined' && alignSettingsConfig.stylesheet) || window.alignThemeStylesheet || 'align-child';
			return `/wp-admin/theme-editor.php?file=${encodeURIComponent(cleanPath)}&theme=${encodeURIComponent(activeTheme)}`;
		}
		return '';
	}

	function updateRowEditButton(row) {
		const urlInput = row.querySelector('.align-asset-url-input');
		let editBtn = row.querySelector('.align-edit-asset-file-btn');
		if (!urlInput) return;
		const editorUrl = getThemeEditorUrl(urlInput.value ? urlInput.value.trim() : '', row);
		if (editorUrl) {
			if (!editBtn) {
				editBtn = document.createElement('a');
				editBtn.href = editorUrl;
				editBtn.target = '_blank';
				editBtn.rel = 'noopener noreferrer';
				editBtn.className = 'button align-edit-asset-file-btn';
				editBtn.title = 'Edit Custom File in Theme File Editor (New Tab)';
				editBtn.innerHTML = '<span class="dashicons dashicons-edit"></span>';
				const uploadBtn = row.querySelector('.align-select-single-file-btn');
				if (uploadBtn) uploadBtn.parentNode.insertBefore(editBtn, uploadBtn);
				else urlInput.parentNode.appendChild(editBtn);
			} else {
				editBtn.href = editorUrl;
				editBtn.removeAttribute('hidden');
				editBtn.classList.remove('is-hidden');
				editBtn.style.setProperty('display', 'inline-flex', 'important');
			}
		} else if (editBtn) {
			editBtn.href = '#';
			editBtn.setAttribute('hidden', 'hidden');
			editBtn.classList.add('is-hidden');
			editBtn.style.setProperty('display', 'none', 'important');
		}
	}

	function updateLoadingOptions(row, type, selectedVal = '', fontWeight = '') {
		const inlineCheckbox = row.querySelector('.align-asset-inline-checkbox');
		if (inlineCheckbox) {
			inlineCheckbox.disabled = (type === 'font');
			if (type === 'font') inlineCheckbox.checked = false;
		}

		const cells = row.querySelectorAll('td');
		const cell = cells.length >= 5 ? cells[4] : null;
		if (!cell) return;

		const match = row.querySelector('[name*="align_loaded_assets"]')?.name.match(/align_loaded_assets\[(\d+)\]/);
		const index = match ? match[1] : '0';

		if (type === 'font') {
			const activeWeight = fontWeight || detectFontWeight(row.querySelector('.align-asset-url-input')?.value || '');
			let weightOpts = '';
			for (const [wVal, wLabel] of Object.entries(FONT_WEIGHTS)) {
				weightOpts += `<option value="${wVal}" ${String(wVal) === String(activeWeight) ? 'selected' : ''}>${wLabel}</option>`;
			}
			cell.innerHTML = `<select name="align_loaded_assets[${index}][font_weight]" class="align-asset-font-weight-select widefat" style="height: 32px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 12px; font-weight: 500;">${weightOpts}</select><input type="hidden" name="align_loaded_assets[${index}][loading]" value="normal" />`;
		} else {
			let select = cell.querySelector('.align-asset-loading-select');
			if (!select) {
				cell.innerHTML = `<select name="align_loaded_assets[${index}][loading]" class="align-asset-loading-select widefat"></select>`;
				select = cell.querySelector('.align-asset-loading-select');
			}
			const defaultVal = isGlobalDelayEnabled ? 'delay' : (type === 'js' ? 'defer' : 'normal');
			let val = selectedVal || defaultVal;
			if (!isGlobalDelayEnabled && val === 'delay') val = (type === 'js') ? 'defer' : 'normal';

			if (type === 'css') {
				select.disabled = !isGlobalDelayEnabled;
				select.innerHTML = isGlobalDelayEnabled
					? `<option value="normal" ${val === 'normal' ? 'selected' : ''}>Normal (Sync)</option><option value="delay" ${val === 'delay' ? 'selected' : ''}>Delay</option>`
					: '<option value="normal" selected>Normal (Sync)</option>';
				select.value = (isGlobalDelayEnabled && val === 'delay') ? 'delay' : 'normal';
			} else {
				select.disabled = false;
				select.innerHTML = isGlobalDelayEnabled
					? `<option value="normal" ${val === 'normal' ? 'selected' : ''}>Normal (Sync)</option><option value="defer" ${val === 'defer' ? 'selected' : ''}>Defer</option><option value="async" ${val === 'async' ? 'selected' : ''}>Async</option><option value="delay" ${val === 'delay' ? 'selected' : ''}>Delay</option>`
					: `<option value="normal" ${val === 'normal' ? 'selected' : ''}>Normal (Sync)</option><option value="defer" ${val === 'defer' ? 'selected' : ''}>Defer</option><option value="async" ${val === 'async' ? 'selected' : ''}>Async</option>`;
				select.value = (isGlobalDelayEnabled && val === 'delay') ? 'delay' : (['normal', 'defer', 'async'].includes(val) ? val : 'defer');
			}
		}
	}

	function updateFilterCounts() {
		const rows = tbody.querySelectorAll('.align-asset-row');
		const counts = { all: rows.length, css: 0, js: 0, font: 0 };
		rows.forEach(row => {
			const typeSelect = row.querySelector('.align-asset-type-select');
			const type = typeSelect ? typeSelect.value : (row.getAttribute('data-type') || 'css');
			row.setAttribute('data-type', type);
			if (counts[type] !== undefined) counts[type]++;
		});
		document.querySelectorAll('.align-asset-filter-btn').forEach(btn => {
			const filterType = btn.getAttribute('data-filter') || 'all';
			const countBadge = btn.querySelector('.align-filter-count');
			if (countBadge) countBadge.textContent = counts[filterType] !== undefined ? counts[filterType] : 0;
		});
		applyCurrentFilter();
	}

	function applyCurrentFilter() {
		const rows = tbody.querySelectorAll('.align-asset-row');
		const noAssetsRow = tbody.querySelector('.align-no-assets-row');
		let visibleCount = 0;
		rows.forEach(row => {
			const type = row.getAttribute('data-type') || 'css';
			if (currentFilter === 'all' || type === currentFilter) {
				row.style.display = '';
				visibleCount++;
			} else {
				row.style.display = 'none';
			}
		});

		const existingFilterEmpty = tbody.querySelector('.align-filter-empty-row');
		if (existingFilterEmpty) existingFilterEmpty.remove();

		if (rows.length === 0) {
			if (!noAssetsRow) {
				const tr = document.createElement('tr');
				tr.className = 'align-no-assets-row';
				tr.innerHTML = '<td colspan="8" style="text-align: center; color: #64748b; padding: 24px;">No assets added yet. Click "Upload / Add Asset Files" or "Add Manual Row" to begin.</td>';
				tbody.appendChild(tr);
			}
		} else {
			if (noAssetsRow) noAssetsRow.remove();
			if (visibleCount === 0) {
				const filterLabels = { css: 'CSS', js: 'JS', font: 'Font' };
				const tr = document.createElement('tr');
				tr.className = 'align-filter-empty-row';
				tr.innerHTML = `<td colspan="8" style="text-align: center; color: #64748b; padding: 24px;">No <strong>${filterLabels[currentFilter] || currentFilter}</strong> assets found. Click "Add Manual Row" to add one.</td>`;
				tbody.appendChild(tr);
			}
		}
	}

	function reindexRows() {
		tbody.querySelectorAll('.align-asset-row').forEach((row, index) => {
			row.querySelectorAll('input, select').forEach(input => {
				const name = input.getAttribute('name');
				if (name) input.setAttribute('name', name.replace(/align_loaded_assets\[\d+\]/, 'align_loaded_assets[' + index + ']'));
			});
		});
		updateFilterCounts();
	}

	function attachDragListeners(row) {
		const handle = row.querySelector('.align-asset-drag-handle');
		if (!handle) return;
		row.setAttribute('draggable', 'false');
		handle.addEventListener('mousedown', () => row.setAttribute('draggable', 'true'));
		handle.addEventListener('mouseup', () => row.setAttribute('draggable', 'false'));
		row.addEventListener('dragstart', (e) => {
			draggedRow = row;
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData('text/plain', '');
			setTimeout(() => row.classList.add('align-asset-dragging'), 0);
		});
		row.addEventListener('dragend', () => {
			row.classList.remove('align-asset-dragging');
			row.setAttribute('draggable', 'false');
			tbody.querySelectorAll('.align-asset-row').forEach(r => r.classList.remove('align-drop-above', 'align-drop-below'));
			draggedRow = null;
		});
		row.addEventListener('dragover', (e) => {
			e.preventDefault();
			if (!draggedRow || draggedRow === row) return;
			e.dataTransfer.dropEffect = 'move';
			const isTop = (e.clientY - row.getBoundingClientRect().top) < (row.offsetHeight / 2);
			row.classList.toggle('align-drop-above', isTop);
			row.classList.toggle('align-drop-below', !isTop);
		});
		row.addEventListener('dragleave', () => row.classList.remove('align-drop-above', 'align-drop-below'));
		row.addEventListener('drop', (e) => {
			e.preventDefault();
			if (!draggedRow || draggedRow === row) return;
			const isTop = (e.clientY - row.getBoundingClientRect().top) < (row.offsetHeight / 2);
			tbody.insertBefore(draggedRow, isTop ? row : row.nextSibling);
			row.classList.remove('align-drop-above', 'align-drop-below');
			reindexRows();
		});
	}

	function createRow(url = '', type = 'css', location = 'header', loading = '', enabled = 1, is_inline = 0, is_core = 0, font_weight = '') {
		const noAssetsRow = tbody.querySelector('.align-no-assets-row');
		if (noAssetsRow) noAssetsRow.remove();
		const emptyFilterRow = tbody.querySelector('.align-filter-empty-row');
		if (emptyFilterRow) emptyFilterRow.remove();

		const index = tbody.querySelectorAll('.align-asset-row').length;
		const tr = document.createElement('tr');
		tr.className = 'align-asset-row' + (is_core ? ' align-core-asset-row' : '');
		tr.setAttribute('data-type', type);
		tr.setAttribute('data-is-core', is_core ? '1' : '0');

		const finalLocation = (location === 'footer') ? 'footer' : 'header';
		const filename = getFilenameFromUrl(url);
		const urlFieldHtml = url
			? `<input type="hidden" name="align_loaded_assets[${index}][url]" value="${url}" class="align-asset-url-input" /><span class="align-asset-filename" title="${url}" style="font-weight: 500; color: #1e293b; font-size: 13px; word-break: break-all; line-height: 1.4; flex: 1;">${filename}</span>`
			: `<span class="align-asset-filename" style="display: none; font-weight: 500; color: #1e293b; font-size: 13px; word-break: break-all; line-height: 1.4; flex: 1;"></span><input type="text" name="align_loaded_assets[${index}][url]" value="" class="widefat align-asset-url-input" placeholder="https://..." style="height: 30px; border-radius: 4px;" />`;

		const actionUploadHtml = is_core ? `<input type="hidden" name="align_loaded_assets[${index}][is_core]" value="1" />` : `<button type="button" class="button align-select-single-file-btn" title="Upload Native File"><span class="dashicons dashicons-upload"></span></button>`;
		const actionDeleteHtml = is_core ? `<span class="dashicons dashicons-lock" style="color: #94a3b8; font-size: 18px; width: 18px; height: 18px; line-height: 18px;" title="Core plugin asset cannot be deleted directly"></span>` : `<button type="button" class="button button-link-delete align-remove-asset-row-btn" title="Remove Row"><span class="dashicons dashicons-trash"></span></button>`;

		tr.innerHTML = `<td class="align-drag-cell" style="text-align: center; vertical-align: middle;"><span class="dashicons dashicons-menu align-asset-drag-handle" title="Drag to reorder asset position"></span></td><td><div style="display: flex; gap: 8px; align-items: center;">${urlFieldHtml}${actionUploadHtml}</div></td><td><input type="hidden" name="align_loaded_assets[${index}][type]" value="${type}" class="align-asset-type-select" /><span class="align-asset-type-text" style="font-weight: 600; color: #475569; font-size: 12.5px; text-transform: uppercase;">${(type || 'CSS').toUpperCase()}</span></td><td><select name="align_loaded_assets[${index}][location]" class="align-asset-location-select widefat"><option value="header" ${finalLocation === 'header' ? 'selected' : ''}>Header</option><option value="footer" ${finalLocation === 'footer' ? 'selected' : ''}>Footer</option></select></td><td><select name="align_loaded_assets[${index}][loading]" class="align-asset-loading-select widefat"></select></td><td style="text-align: center; vertical-align: middle;"><input type="checkbox" name="align_loaded_assets[${index}][is_inline]" value="1" ${is_inline ? 'checked' : ''} ${type === 'font' ? 'disabled' : ''} class="align-asset-inline-checkbox" title="Inline style/script directly into HTML markup" /></td><td style="text-align: center; vertical-align: middle;"><input type="checkbox" name="align_loaded_assets[${index}][enabled]" value="1" ${enabled ? 'checked' : ''} /></td><td style="text-align: center; vertical-align: middle;">${actionDeleteHtml}</td>`;

		updateLoadingOptions(tr, type, loading, font_weight);
		updateRowEditButton(tr);
		attachDragListeners(tr);
		tbody.appendChild(tr);
		reindexRows();
	}

	tbody.querySelectorAll('.align-asset-row').forEach(row => {
		attachDragListeners(row);
		updateRowEditButton(row);
	});

	filterButtons.forEach(btn => {
		btn.addEventListener('click', function(e) {
			e.preventDefault();
			filterButtons.forEach(b => b.classList.remove('is-active'));
			this.classList.add('is-active');
			currentFilter = this.getAttribute('data-filter') || 'all';
			applyCurrentFilter();
		});
	});

	updateFilterCounts();

	if (addManualBtn) addManualBtn.addEventListener('click', () => createRow('', (currentFilter === 'all') ? 'css' : currentFilter, 'header', 'normal', 1));
	if (addMediaBtn && nativeFileInput) addMediaBtn.addEventListener('click', () => nativeFileInput.click());

	if (nativeFileInput) {
		nativeFileInput.addEventListener('change', function() {
			if (!nativeFileInput.files || nativeFileInput.files.length === 0) return;
			const formData = new FormData();
			formData.append('action', 'align_upload_asset_file');
			formData.append('align_settings_nonce', alignNonce);
			for (let i = 0; i < nativeFileInput.files.length; i++) formData.append('files[]', nativeFileInput.files[i]);

			const origContent = addMediaBtn.innerHTML;
			addMediaBtn.disabled = true;
			addMediaBtn.innerHTML = '<span class="dashicons dashicons-update spin"></span> <span>Uploading...</span>';

			fetch(ajaxUrl, { method: 'POST', body: formData })
			.then(r => r.json())
			.then(data => {
				addMediaBtn.disabled = false;
				addMediaBtn.innerHTML = origContent;
				nativeFileInput.value = '';
				if (data.success && data.data && data.data.files) {
					data.data.files.forEach(file => {
						const fw = file.type === 'font' ? detectFontWeight(file.url) : '';
						createRow(file.url, file.type, 'header', 'normal', 1, 0, 0, fw);
					});
				} else {
					alert(data.data && data.data.message ? data.data.message : 'Upload failed.');
				}
			}).catch(err => {
				console.error('[Align CustomAssets] Upload error:', err);
				addMediaBtn.disabled = false;
				addMediaBtn.innerHTML = origContent;
				nativeFileInput.value = '';
				alert('An error occurred during file upload.');
			});
		});
	}

	if (singleFileInput) {
		singleFileInput.addEventListener('change', function() {
			if (!singleFileInput.files || singleFileInput.files.length === 0 || !activeRowForSingleUpload) return;
			const formData = new FormData();
			formData.append('action', 'align_upload_asset_file');
			formData.append('align_settings_nonce', alignNonce);
			formData.append('files[]', singleFileInput.files[0]);

			const row = activeRowForSingleUpload;
			const urlInput = row.querySelector('.align-asset-url-input');
			const typeSelect = row.querySelector('.align-asset-type-select');
			const locationSelect = row.querySelector('.align-asset-location-select');
			const prevPlaceholder = urlInput.placeholder;
			urlInput.placeholder = 'Uploading...';

			fetch(ajaxUrl, { method: 'POST', body: formData })
			.then(r => r.json())
			.then(data => {
				singleFileInput.value = '';
				urlInput.placeholder = prevPlaceholder;
				if (data.success && data.data && data.data.files && data.data.files.length > 0) {
					const uploaded = data.data.files[0];
					urlInput.value = uploaded.url;
					urlInput.type = 'hidden';
					const filenameSpan = row.querySelector('.align-asset-filename');
					if (filenameSpan) {
						filenameSpan.textContent = getFilenameFromUrl(uploaded.url);
						filenameSpan.title = uploaded.url;
						filenameSpan.style.display = '';
					}
					typeSelect.value = uploaded.type;
					const typeText = row.querySelector('.align-asset-type-text');
					if (typeText) typeText.textContent = uploaded.type.toUpperCase();
					row.setAttribute('data-type', uploaded.type);
					if (uploaded.type === 'font' && locationSelect && !locationSelect.value) locationSelect.value = 'header';
					updateLoadingOptions(row, uploaded.type, 'normal', uploaded.type === 'font' ? detectFontWeight(uploaded.url) : '');
					updateRowEditButton(row);
					updateFilterCounts();
				} else {
					alert(data.data && data.data.message ? data.data.message : 'Upload failed.');
				}
				activeRowForSingleUpload = null;
			}).catch(err => {
				console.error('[Align CustomAssets] Single upload error:', err);
				singleFileInput.value = '';
				urlInput.placeholder = prevPlaceholder;
				activeRowForSingleUpload = null;
				alert('An error occurred during file upload.');
			});
		});
	}

	tbody.addEventListener('change', function(e) {
		if (e.target.classList.contains('align-asset-url-input')) {
			const tr = e.target.closest('tr');
			const typeSelect = tr.querySelector('.align-asset-type-select');
			const typeText = tr.querySelector('.align-asset-type-text');
			const filenameSpan = tr.querySelector('.align-asset-filename');
			const locationSelect = tr.querySelector('.align-asset-location-select');
			const val = e.target.value.trim();
			if (val) {
				const detected = detectFileType(val);
				if (typeSelect) typeSelect.value = detected;
				if (typeText) typeText.textContent = detected.toUpperCase();
				tr.setAttribute('data-type', detected);
				if (detected === 'font' && locationSelect && !locationSelect.value) locationSelect.value = 'header';
				updateLoadingOptions(tr, detected, 'normal', detected === 'font' ? detectFontWeight(val) : '');
				if (filenameSpan) {
					filenameSpan.textContent = getFilenameFromUrl(val);
					filenameSpan.title = val;
					filenameSpan.style.display = '';
				}
				e.target.type = 'hidden';
			}
			updateRowEditButton(tr);
			updateFilterCounts();
		}
	});

	tbody.addEventListener('click', function(e) {
		const removeBtn = e.target.closest('.align-remove-asset-row-btn');
		if (removeBtn) {
			const tr = removeBtn.closest('tr');
			const urlInput = tr.querySelector('.align-asset-url-input');
			const url = urlInput ? urlInput.value.trim() : '';
			if (!url) {
				tr.remove();
				reindexRows();
				return;
			}
			if (!confirm('Are you sure you want to permanently delete this asset and its physical file from disk?')) return;
			const origHtml = removeBtn.innerHTML;
			removeBtn.disabled = true;
			removeBtn.innerHTML = '<span class="dashicons dashicons-update spin"></span>';

			const formData = new FormData();
			formData.append('action', 'align_delete_asset_file');
			formData.append('align_settings_nonce', alignNonce);
			formData.append('url', url);

			fetch(ajaxUrl, { method: 'POST', body: formData })
			.then(r => r.json())
			.then(d => {
				if (d.success) {
					tr.remove();
					reindexRows();
				} else {
					removeBtn.disabled = false;
					removeBtn.innerHTML = origHtml;
					alert(d.data && d.data.message ? d.data.message : 'Asset deletion failed.');
				}
			}).catch(err => {
				console.error('[Align CustomAssets] Delete error:', err);
				removeBtn.disabled = false;
				removeBtn.innerHTML = origHtml;
				alert('An error occurred during asset deletion.');
			});
			return;
		}

		const singleFileBtn = e.target.closest('.align-select-single-file-btn');
		if (singleFileBtn) {
			activeRowForSingleUpload = singleFileBtn.closest('tr');
			if (singleFileInput) singleFileInput.click();
			return;
		}
	});

	document.querySelectorAll('.align-copy-css-btn').forEach(btn => {
		btn.addEventListener('click', function(e) {
			e.preventDefault();
			const text = this.getAttribute('data-copy');
			if (!text) return;
			navigator.clipboard.writeText(text).then(() => {
				const orig = this.textContent;
				this.textContent = 'Copied!';
				setTimeout(() => { this.textContent = orig; }, 1800);
			});
		});
	});
});
