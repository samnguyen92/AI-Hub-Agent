/**
 * Align Agent - Chatbox Media Library Picker, Drag & Drop, and Clipboard Paste
 *
 * Integrates WordPress wp.media modal, drawer-level drag & drop upload,
 * and clipboard image paste on the chat textarea.
 */
(function (window, document) {
	'use strict';

	var mediaFrame = null;
	var chatboxEl  = null;
	var uploadBtn  = null;
	var fileInput  = null;
	var textareaEl = null;
	var dragCounter = 0;

	var ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

	function isImageFile(file) {
		return !!(file && file.type && ALLOWED_MIME_TYPES.indexOf(file.type.toLowerCase()) !== -1);
	}

	function openMediaPicker() {
		if (window.AlignChatboxUpload && window.AlignChatboxUpload.isUploading()) {
			return;
		}
		if (!window.wp || !window.wp.media) {
			console.warn('[Align UI] wp.media unavailable, falling back to native file input');
			if (fileInput) fileInput.click();
			return;
		}
		if (mediaFrame) {
			mediaFrame.open();
			console.log('[Align UI] Media frame opened');
			return;
		}

		mediaFrame = window.wp.media({
			title: 'Select images for Align Agent',
			library: { type: 'image' },
			multiple: 'add',
			button: { text: 'Attach to chat' }
		});

		mediaFrame.on('select', function () {
			var selection = mediaFrame.state().get('selection');
			if (!selection) {
				return;
			}
			var count = 0;
			selection.each(function (attachment) {
				var a = attachment.toJSON();
				var thumbUrl = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
				var added = window.AlignChatboxUpload && window.AlignChatboxUpload.addAttachment({
					id: a.id,
					url: a.url,
					thumb: thumbUrl,
					filename: a.filename || a.title || ('image-' + a.id)
				});
				if (added) {
					count++;
				}
			});
			console.log('[Align UI] Selected ' + count + ' attachment(s) from library');
		});

		mediaFrame.open();
		console.log('[Align UI] Media frame opened');
	}

	function handleDragEnter(e) {
		if (!e.dataTransfer || !e.dataTransfer.types) {
			return;
		}
		var hasFiles = Array.prototype.indexOf.call(e.dataTransfer.types, 'Files') !== -1;
		if (!hasFiles) {
			return;
		}
		e.preventDefault();
		dragCounter++;
		if (chatboxEl) chatboxEl.classList.add('is-dragover');
	}

	function handleDragOver(e) {
		if (!e.dataTransfer) return;
		e.preventDefault();
		e.stopPropagation();
		try { e.dataTransfer.dropEffect = 'copy'; } catch (ignore) {}
	}

	function handleDragLeave(e) {
		e.preventDefault();
		dragCounter--;
		if (dragCounter <= 0) {
			dragCounter = 0;
			if (chatboxEl) chatboxEl.classList.remove('is-dragover');
		}
	}

	function handleDrop(e) {
		e.preventDefault();
		e.stopPropagation();
		dragCounter = 0;
		if (chatboxEl) chatboxEl.classList.remove('is-dragover');
		if (window.AlignChatbox && typeof window.AlignChatbox.isOpen === 'function' && !window.AlignChatbox.isOpen()) {
			return;
		}

		var dtFiles = e.dataTransfer && e.dataTransfer.files ? Array.prototype.slice.call(e.dataTransfer.files) : [];
		if (!dtFiles.length) return;

		var validFiles = [];
		var invalidCount = 0;
		dtFiles.forEach(function (f) {
			if (isImageFile(f)) { validFiles.push(f); } else { invalidCount++; }
		});
		console.log('[Align DragDrop] Dropped ' + dtFiles.length + ' file(s), accepted ' + validFiles.length);

		if (invalidCount > 0 && window.AlignChatboxUpload && typeof window.AlignChatboxUpload.showInlineUploadError === 'function') {
			window.AlignChatboxUpload.showInlineUploadError('Only JPG, PNG, WEBP, GIF images are supported.');
		}

		if (validFiles.length && window.AlignChatboxUpload && typeof window.AlignChatboxUpload.uploadFiles === 'function') {
			window.AlignChatboxUpload.uploadFiles(validFiles);
		}
	}

	function handlePaste(e) {
		var clipData = e.clipboardData || window.clipboardData;
		if (!clipData || !clipData.files || !clipData.files.length) {
			return;
		}

		var files = Array.prototype.slice.call(clipData.files);
		var imageFiles = files.filter(isImageFile);
		if (!imageFiles.length) {
			return;
		}

		e.preventDefault();
		console.log('[Align UI] Pasted ' + imageFiles.length + ' image(s) from clipboard');
		if (window.AlignChatboxUpload && typeof window.AlignChatboxUpload.uploadFiles === 'function') {
			window.AlignChatboxUpload.uploadFiles(imageFiles);
		}
	}

	function init() {
		try {
			chatboxEl  = document.getElementById('align-agent-chatbox');
			uploadBtn  = document.getElementById('align-chatbox-upload-btn');
			fileInput  = document.getElementById('align-chatbox-file-input');
			textareaEl = document.getElementById('align-chatbox-input');
		} catch (e) {
			console.error('[Align UI] Failed querying media elements: ' + (e && e.message ? e.message : String(e)));
			return;
		}

		if (uploadBtn) {
			uploadBtn.addEventListener('click', function (e) {
				e.preventDefault();
				openMediaPicker();
			});
		}

		if (chatboxEl) {
			chatboxEl.addEventListener('dragenter', handleDragEnter);
			chatboxEl.addEventListener('dragover', handleDragOver);
			chatboxEl.addEventListener('dragleave', handleDragLeave);
			chatboxEl.addEventListener('drop', handleDrop);
		}

		if (textareaEl) {
			textareaEl.addEventListener('paste', handlePaste);
		}
	}

	document.addEventListener('DOMContentLoaded', init);

	window.AlignChatboxMedia = {
		openPicker: openMediaPicker
	};
})(window, document);
