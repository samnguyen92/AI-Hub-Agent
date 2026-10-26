/**
 * Align Theme Settings - CodeMirror Editor Initialization
 *
 * Initializes WordPress codeEditor (CodeMirror) instances for custom code textareas
 * with proper modes (css, javascript, htmlmixed). Handles dynamic tab/subtab switching
 * refresh and form submission sync.
 *
 * @package Align
 */

/* global wp, jQuery */
(function($) {
	'use strict';

	$(function() {
		if (!wp || !wp.codeEditor || typeof wp.codeEditor.initialize !== 'function') {
			return;
		}

		var editorConfigs = [
			{ id: 'align_custom_css', mode: 'text/css' },
			{ id: 'align_custom_js', mode: 'text/javascript' },
			{ id: 'align_tracking_code', mode: 'text/html' },
			{ id: 'align_body_open_code', mode: 'text/html' },
			{ id: 'align_footer_html_code', mode: 'text/html' }
		];

		var initializedEditors = [];

		editorConfigs.forEach(function(item) {
			var textarea = document.getElementById(item.id);
			if (!textarea) {
				return;
			}

			var initialContent = textarea.value || '';
			var targetMode = item.mode;

			if (item.id === 'align_custom_css') {
				targetMode = initialContent.toLowerCase().indexOf('<style') !== -1 ? 'text/html' : 'text/css';
			} else if (item.id === 'align_custom_js') {
				targetMode = initialContent.toLowerCase().indexOf('<script') !== -1 ? 'text/html' : 'text/javascript';
			}

			// wp.codeEditor.defaultSettings provides base settings if wp_enqueue_code_editor was called
			var baseSettings = wp.codeEditor.defaultSettings ? $.extend(true, {}, wp.codeEditor.defaultSettings) : { codemirror: {} };
			if (!baseSettings.codemirror) {
				baseSettings.codemirror = {};
			}

			baseSettings.codemirror.mode = targetMode;
			baseSettings.codemirror.lineNumbers = true;
			baseSettings.codemirror.lineWrapping = true;
			baseSettings.codemirror.indentUnit = 2;
			baseSettings.codemirror.tabSize = 2;
			baseSettings.codemirror.autoCloseBrackets = true;
			baseSettings.codemirror.matchBrackets = true;

			try {
				var editorInstance = wp.codeEditor.initialize(textarea, baseSettings);
				if (editorInstance && editorInstance.codemirror) {
					var cm = editorInstance.codemirror;
					initializedEditors.push(cm);

					// Sync on change to keep underlying textarea updated
					cm.on('change', function() {
						cm.save();
					});
				}
			} catch (e) {
				if (window.console && console.warn) {
					console.warn('[Align UI] Failed to initialize CodeMirror for #' + item.id, e);
				}
			}
		});

		if (initializedEditors.length > 0) {
			console.log('[Align UI] CodeMirror initialized for custom code editors');
		}

		// Ensure all editors are saved before settings form submits
		var form = document.getElementById('align-settings-form') || $('form#align-settings-form')[0];
		if (form) {
			form.addEventListener('submit', function() {
				initializedEditors.forEach(function(cm) {
					cm.save();
				});
			});
		}

		// Refresh CodeMirror layout when tabs or subtabs are clicked
		function refreshEditors() {
			setTimeout(function() {
				initializedEditors.forEach(function(cm) {
					cm.refresh();
				});
			}, 50);
			setTimeout(function() {
				initializedEditors.forEach(function(cm) {
					cm.refresh();
				});
			}, 200);
		}

		document.addEventListener('click', function(e) {
			if (e.target.closest('.align-nav-tab') || e.target.closest('.align-subnav-pill') || e.target.closest('.align-switch-tab-link')) {
				refreshEditors();
			}
		});

		window.addEventListener('hashchange', refreshEditors);
	});
})(jQuery);
