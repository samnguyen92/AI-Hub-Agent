/**
 * Align Theme - Bulk AI SEO Generator Client-Side Queue & UI Controller
 *
 * Coordinates scanning, batch execution, live score badge animation,
 * filter searching, and real-time logging for Pages & Posts.
 *
 * Adheres strictly to Align coding standards & < 500 lines limit.
 *
 * @package Align
 */

(function ($) {
	'use strict';

	var state = {
		items: [],
		isRunning: false,
		isPaused: false,
		queue: [],
		totalInQueue: 0,
		processedCount: 0,
		batchSize: 2
	};

	function log(msg) {
		var $log = $('#align-seo-log-area');
		if (!$log.length) return;
		var time = new Date().toLocaleTimeString();
		var line = '[' + time + '] ' + msg + '\n';
		$log.val($log.val() + line);
		$log.scrollTop($log[0].scrollHeight);
		console.log('[Align BulkSeo] ' + msg);
	}

	function getScoreBadge(score) {
		if (score === null || score === undefined || score === '') {
			return '<span style="display:inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1;">N/A</span>';
		}
		var s = parseInt(score, 10);
		if (s >= 80) {
			return '<span style="display:inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; background: #dcfce7; color: #15803d; border: 1px solid #86efac;">' + s + ' / 100</span>';
		}
		if (s >= 50) {
			return '<span style="display:inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; background: #fef3c7; color: #b45309; border: 1px solid #fcd34d;">' + s + ' / 100</span>';
		}
		return '<span style="display:inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5;">' + s + ' / 100</span>';
	}

	function updateSelectedCount() {
		var checked = $('.align-seo-row-cb:checked').length;
		var total = $('.align-seo-row-cb').length;
		$('#align-seo-selected-count').text(checked + ' of ' + total + ' selected');
		if (checked > 0 && !state.isRunning) {
			$('#align-seo-btn-run').show();
		} else if (!state.isRunning) {
			$('#align-seo-btn-run').hide();
		}
	}

	function renderTable(items) {
		var $tbody = $('#align-seo-table-body');
		$tbody.empty();

		if (!items || !items.length) {
			$tbody.html('<tr><td colspan="8" style="text-align: center; padding: 24px; color: #64748b;">No content matching the selected filters.</td></tr>');
			$('#align-seo-btn-run').hide();
			$('#align-seo-selected-count').text('');
			return;
		}

		items.forEach(function (it) {
			var langBadge = '<span style="text-transform: uppercase; font-size: 11px; font-weight: 600; padding: 2px 6px; background: #f1f5f9; border-radius: 4px; color: #475569;">' + it.lang + '</span>';
			var typeBadge = '<span style="font-size: 11px; color: #64748b;">' + it.post_type + '</span>';
			var kwDisplay = it.focus_keyword
				? '<span style="font-size: 12px; font-weight: 600; color: #0284c7;">' + $('<div>').text(it.primary_kw || it.focus_keyword).html() + '</span>'
				: '<span style="font-size: 12px; color: #94a3b8; font-style: italic;">Missing focus keyword</span>';

			var tr = '<tr id="align-seo-row-' + it.id + '" data-id="' + it.id + '">' +
				'<td style="text-align: center; vertical-align: middle;"><input type="checkbox" class="align-seo-row-cb" value="' + it.id + '" checked="checked" /></td>' +
				'<td style="color: #64748b; font-size: 12px;">' + it.id + '</td>' +
				'<td>' +
					'<div style="font-weight: 600; font-size: 13px; color: #0f172a;"><a href="' + it.permalink + '" target="_blank" style="text-decoration: none; color: inherit;">' + $('<div>').text(it.title).html() + '</a></div>' +
					'<div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">/' + it.slug + ' &bull; <a href="' + it.edit_url + '" target="_blank" style="color: #0284c7; text-decoration: none;">Edit</a></div>' +
				'</td>' +
				'<td>' + typeBadge + '</td>' +
				'<td>' + langBadge + '</td>' +
				'<td class="align-seo-cell-kw">' + kwDisplay + '</td>' +
				'<td class="align-seo-cell-score" style="text-align: center;">' + getScoreBadge(it.score) + '</td>' +
				'<td style="text-align: right;">' +
					'<button type="button" class="button button-secondary align-seo-btn-single" data-id="' + it.id + '" style="font-size: 11px; height: 26px; line-height: 24px; padding: 0 8px;">Optimize</button>' +
				'</td>' +
			'</tr>';
			$tbody.append(tr);
		});

		$('#align-seo-select-all').prop('checked', true);
		updateSelectedCount();
	}

	function scanContent() {
		var $btn = $('#align-seo-btn-scan');
		$btn.prop('disabled', true).text('Scanning...');

		var postTypes = [];
		if ($('#align_seo_type_page').is(':checked')) postTypes.push('page');
		if ($('#align_seo_type_post').is(':checked')) postTypes.push('post');

		var lang = $('#align_seo_filter_lang').val() || 'all';
		var scope = $('input[name="align_seo_scope"]:checked').val() || 'needs_optimization';
		var search = $('#align-seo-search-input').val() || '';

		log('Starting content audit... (types=' + postTypes.join(',') + ', lang=' + lang + ', scope=' + scope + ')');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'align_bulk_seo_scan_items',
				security: window.alignSettingsNonce || '',
				post_types: postTypes,
				lang: lang,
				scope: scope,
				search: search
			}
		}).done(function (res) {
			$btn.prop('disabled', false).text('Scan & Audit Content');
			if (res.success && res.data) {
				state.items = res.data.items || [];
				$('#align-seo-stat-total').text(res.data.stats.total);
				$('#align-seo-stat-opt').text(res.data.stats.optimized);
				$('#align-seo-stat-mod').text(res.data.stats.moderate);
				$('#align-seo-stat-needs').text(res.data.stats.needs_opt);

				log('Scan complete: ' + state.items.length + ' actionable items loaded (' + res.data.stats.optimized + ' already optimized).');
				renderTable(state.items);
			} else {
				var msg = res.data && res.data.message ? res.data.message : 'Scan failed.';
				log('Error: ' + msg);
				alert(msg);
			}
		}).fail(function (xhr, status, err) {
			$btn.prop('disabled', false).text('Scan & Audit Content');
			log('AJAX Error: ' + err);
			alert('Failed to connect to server.');
		});
	}

	function updateProgress(done, total) {
		var pct = total > 0 ? Math.round((done / total) * 100) : 0;
		$('#align-seo-progress-bar').css('width', pct + '%');
		$('#align-seo-progress-percent').text(pct + '% (' + done + '/' + total + ')');
	}

	function processSingleItem(postId) {
		return new Promise(function (resolve, reject) {
			var $row = $('#align-seo-row-' + postId);
			var $btn = $row.find('.align-seo-btn-single');
			$btn.prop('disabled', true).text('Working...');
			$row.css('background-color', '#f0f9ff');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'align_bulk_seo_process_item',
					security: window.alignSettingsNonce || '',
					post_id: postId
				}
			}).done(function (res) {
				$btn.prop('disabled', false).text('Optimize');
				$row.css('background-color', '');

				if (res.success && res.data) {
					var d = res.data;
					$row.find('.align-seo-cell-score').html(getScoreBadge(d.new_score));
					$row.find('.align-seo-cell-kw').html('<span style="font-size: 12px; font-weight: 600; color: #15803d;">' + $('<div>').text(d.focus_keyword.split(',')[0].trim()).html() + '</span>');
					log('Post #' + postId + ' ("' + d.title + '"): Score updated to ' + d.new_score + '/100 (KW: ' + d.focus_keyword + ')');
					resolve(d);
				} else {
					var err = res.data && res.data.message ? res.data.message : 'Unknown error';
					log('Post #' + postId + ' failed: ' + err);
					$row.find('.align-seo-cell-score').html('<span style="color: #dc2626; font-size: 11px;">Error</span>');
					resolve({ error: err });
				}
			}).fail(function (xhr, status, err) {
				$btn.prop('disabled', false).text('Optimize');
				$row.css('background-color', '');
				log('Post #' + postId + ' network error: ' + err);
				resolve({ error: err });
			});
		});
	}

	async function runQueue() {
		state.batchSize = parseInt($('#align_seo_batch_size').val(), 10) || 2;

		while (state.queue.length > 0) {
			if (!state.isRunning) break;
			if (state.isPaused) {
				await new Promise(function (r) { setTimeout(r, 600); });
				continue;
			}

			var batch = state.queue.splice(0, state.batchSize);
			var promises = batch.map(function (id) {
				return processSingleItem(id);
			});

			await Promise.all(promises);
			state.processedCount += batch.length;
			updateProgress(state.processedCount, state.totalInQueue);
		}

		if (state.queue.length === 0 && state.isRunning) {
			state.isRunning = false;
			$('#align-seo-progress-text').text('All items processed successfully!');
			log('Bulk SEO optimization completed. Total items: ' + state.processedCount);
			finishExecution();
		}
	}

	function finishExecution() {
		state.isRunning = false;
		state.isPaused = false;
		$('#align-seo-btn-run').show().text('Optimize Selected');
		$('#align-seo-btn-pause').hide();
		$('#align-seo-btn-stop').hide();
		$('#align-seo-btn-scan').prop('disabled', false);
	}

	// Initialization & Event Bindings
	$(document).ready(function () {
		// Scan Content Button
		$('#align-seo-btn-scan').on('click', function (e) {
			e.preventDefault();
			scanContent();
		});

		// Select All Checkbox
		$('#align-seo-select-all').on('change', function () {
			var isChecked = $(this).is(':checked');
			$('.align-seo-row-cb').prop('checked', isChecked);
			updateSelectedCount();
		});

		// Individual Row Checkboxes
		$(document).on('change', '.align-seo-row-cb', function () {
			updateSelectedCount();
		});

		// Real-time Title / Slug Filter Search
		$('#align-seo-search-input').on('keyup input', function () {
			var query = $(this).val().toLowerCase().trim();
			$('#align-seo-table-body tr').each(function () {
				var text = $(this).text().toLowerCase();
				if (!query || text.indexOf(query) !== -1) {
					$(this).show();
				} else {
					$(this).hide();
				}
			});
			updateSelectedCount();
		});

		// Single Row Optimize Click
		$(document).on('click', '.align-seo-btn-single', function (e) {
			e.preventDefault();
			var postId = parseInt($(this).data('id'), 10);
			if (postId) {
				processSingleItem(postId);
			}
		});

		// Run Bulk Button
		$('#align-seo-btn-run').on('click', function (e) {
			e.preventDefault();
			var selected = [];
			$('.align-seo-row-cb:checked').each(function () {
				var id = parseInt($(this).val(), 10);
				if (id) selected.push(id);
			});

			if (!selected.length) {
				alert('Please select at least one item to optimize.');
				return;
			}

			if (!confirm('Start bulk SEO generation for ' + selected.length + ' item(s)?')) {
				return;
			}

			state.queue = selected.slice();
			state.totalInQueue = selected.length;
			state.processedCount = 0;
			state.isRunning = true;
			state.isPaused = false;

			$('#align-seo-btn-run').hide();
			$('#align-seo-btn-pause').show().text('Pause');
			$('#align-seo-btn-stop').show();
			$('#align-seo-btn-scan').prop('disabled', true);
			$('#align-seo-progress-wrap').show();
			$('#align-seo-progress-text').text('Optimizing SEO metadata with AI...');
			updateProgress(0, state.totalInQueue);

			log('Starting bulk queue for ' + selected.length + ' items...');
			runQueue();
		});

		// Pause Button
		$('#align-seo-btn-pause').on('click', function (e) {
			e.preventDefault();
			state.isPaused = !state.isPaused;
			$(this).text(state.isPaused ? 'Resume' : 'Pause');
			log(state.isPaused ? 'Execution paused by user.' : 'Execution resumed.');
		});

		// Stop Button
		$('#align-seo-btn-stop').on('click', function (e) {
			e.preventDefault();
			state.isRunning = false;
			log('Bulk execution stopped by user.');
			finishExecution();
		});

		// Clear Log Button
		$('#align-seo-clear-log').on('click', function (e) {
			e.preventDefault();
			$('#align-seo-log-area').val('');
		});
	});

})(jQuery);
