/**
 * Align Agent - Chatbox Media Upload Controller (M1)
 *
 * Uploads user-selected images directly to WordPress core REST API
 * (POST /wp-json/wp/v2/media) using X-WP-Nonce, renders attachment chips
 * inside the chatbox footer, and exposes window.AlignChatboxUpload so
 * chatbox.js can append uploaded attachment IDs and URLs to user messages.
 */
(function (window, document) {
	'use strict';

	var attachments = [];
	var uploadBtnEl = null;
	var fileInputEl = null;
	var chipsWrapEl = null;
	var isUploading = false;

	function getMediaRestUrl() {
		var cfg = window.alignChatboxData || {};
		if (cfg.wpMediaRestUrl) {
			return String(cfg.wpMediaRestUrl);
		}
		var base = String(cfg.restUrl || '/wp-json/align-agent/v1').replace(/\/align-agent\/v1\/?$/, '');
		return base.replace(/\/+$/, '') + '/wp/v2/media';
	}

	function getRestNonce() {
		var cfg = window.alignChatboxData || {};
		return String(cfg.restNonce || '');
	}

	function renderChips() {
		if (!chipsWrapEl) {
			return;
		}
		chipsWrapEl.innerHTML = '';
		if (!attachments.length) {
			chipsWrapEl.style.display = 'none';
			return;
		}

		chipsWrapEl.style.display = 'flex';
		attachments.forEach(function (item) {
			var thumbDiv = document.createElement('div');
			thumbDiv.className = 'align-chatbox-thumb';
			thumbDiv.setAttribute('data-attachment-id', String(item.id));

			var img = document.createElement('img');
			img.src = item.thumb || item.url;
			img.alt = item.filename || '';
			img.width = 48;
			img.height = 48;
			img.loading = 'lazy';
			thumbDiv.appendChild(img);

			var removeBtn = document.createElement('button');
			removeBtn.type = 'button';
			removeBtn.className = 'align-chatbox-thumb-remove';
			removeBtn.setAttribute('data-action', 'remove-attachment');
			removeBtn.setAttribute('aria-label', 'Remove image ' + (item.filename || ''));
			removeBtn.textContent = '×';
			removeBtn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				attachments = attachments.filter(function (a) {
					return Number(a.id) !== Number(item.id);
				});
				renderChips();
			});
			thumbDiv.appendChild(removeBtn);

			chipsWrapEl.appendChild(thumbDiv);
		});
	}

	async function uploadSingleFile(file) {
		var mediaUrl = getMediaRestUrl();
		var nonce    = getRestNonce();
		var fd       = new FormData();
		fd.append('file', file, file.name);

		var response;
		try {
			response = await fetch(mediaUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'X-WP-Nonce': nonce,
					'Accept': 'application/json'
				},
				body: fd
			});
		} catch (netErr) {
			var netMsg = netErr && netErr.message ? netErr.message : String(netErr);
			console.error('[Align UI] Media upload network error: ' + netMsg);
			throw new Error(netMsg);
		}

		var rawText = '';
		try {
			rawText = await response.text();
		} catch (readErr) {
			var readMsg = readErr && readErr.message ? readErr.message : String(readErr);
			console.error('[Align UI] Failed reading media upload response: ' + readMsg);
			throw new Error(readMsg);
		}

		var parsed = null;
		try {
			parsed = rawText ? JSON.parse(rawText) : {};
		} catch (parseErr) {
			console.error('[Align UI] Invalid JSON from /wp/v2/media');
			throw new Error('Invalid JSON response from /wp/v2/media.');
		}

		if (!response.ok || !parsed || !parsed.id) {
			var errMsg = (parsed && parsed.message) ? parsed.message : ('Media upload failed (HTTP ' + response.status + ')');
			console.error('[Align UI] Media upload failed: ' + errMsg);
			throw new Error(errMsg);
		}

		var thumbUrl = (parsed.media_details && parsed.media_details.sizes && parsed.media_details.sizes.thumbnail && parsed.media_details.sizes.thumbnail.source_url)
			? parsed.media_details.sizes.thumbnail.source_url
			: (parsed.source_url || '');

		return {
			id: Number(parsed.id),
			url: String(parsed.source_url || ''),
			thumb: String(thumbUrl || parsed.source_url || ''),
			filename: String(file.name || ('attachment-' + parsed.id))
		};
	}

	function setUploadingState(busy) {
		isUploading = !!busy;
		if (uploadBtnEl) {
			uploadBtnEl.disabled = isUploading;
		}
	}

	function showInlineUploadError(errText) {
		var messagesEl = document.getElementById('align-chatbox-messages');
		if (!messagesEl) {
			return;
		}
		var emptyEl = document.getElementById('align-chatbox-empty');
		if (emptyEl) {
			emptyEl.style.display = 'none';
		}
		var errDiv = document.createElement('div');
		errDiv.className = 'align-chat-msg align-chat-msg-error';
		errDiv.setAttribute('role', 'alert');
		errDiv.textContent = 'Media Upload Error: ' + errText;
		messagesEl.appendChild(errDiv);
		messagesEl.scrollTop = messagesEl.scrollHeight;
	}

	function addAttachment(att) {
		if (!att || !att.id) {
			return false;
		}
		var exists = attachments.some(function (item) {
			return Number(item.id) === Number(att.id);
		});
		if (exists) {
			return false;
		}
		attachments.push({
			id: Number(att.id),
			url: String(att.url || ''),
			thumb: String(att.thumb || att.url || ''),
			filename: String(att.filename || ('attachment-' + att.id))
		});
		renderChips();
		return true;
	}

	async function uploadFiles(fileList) {
		var files = fileList ? Array.prototype.slice.call(fileList) : [];
		if (!files.length || isUploading) {
			return [];
		}

		setUploadingState(true);
		var statusWrap = document.getElementById('align-chatbox-tool-status');
		var statusText = document.getElementById('align-chatbox-tool-status-text');
		if (statusWrap && statusText) {
			statusWrap.style.display = 'flex';
			statusText.textContent = 'Uploading image to Media Library...';
		}

		var uploadedItems = [];
		try {
			for (var i = 0; i < files.length; i++) {
				var uploaded = await uploadSingleFile(files[i]);
				addAttachment(uploaded);
				uploadedItems.push(uploaded);
				console.log('[Align UI] Uploaded attachment #' + uploaded.id + ' (' + uploaded.url + ')');
			}
		} catch (err) {
			showInlineUploadError(err && err.message ? err.message : 'Upload failed.');
		} finally {
			setUploadingState(false);
			if (statusWrap) {
				statusWrap.style.display = 'none';
			}
		}
		return uploadedItems;
	}

	function initDom() {
		try {
			uploadBtnEl = document.getElementById('align-chatbox-upload-btn');
			fileInputEl = document.getElementById('align-chatbox-file-input');
			chipsWrapEl = document.getElementById('align-chatbox-attachments');
		} catch (e) {
			console.error('[Align UI] Failed querying upload elements: ' + (e && e.message ? e.message : String(e)));
			return;
		}

		if (!fileInputEl) {
			return;
		}

		fileInputEl.addEventListener('change', function () {
			var files = fileInputEl.files ? Array.prototype.slice.call(fileInputEl.files) : [];
			fileInputEl.value = '';
			if (files.length) {
				uploadFiles(files);
			}
		});
	}

	/**
	 * Format and consume queued attachments so chatbox.js can append them to the user prompt.
	 *
	 * @returns {string} Context suffix with attachment_id and URL pairs, or empty string.
	 */
	function consumeAttachmentsText() {
		if (!attachments.length) {
			return '';
		}
		var lines = attachments.map(function (att) {
			return '[Attached Media: attachment_id=' + att.id + ', url=' + att.url + ', file=' + att.filename + ']';
		});
		attachments = [];
		renderChips();
		return '\n' + lines.join('\n');
	}

	function hasAttachments() {
		return attachments.length > 0;
	}

	function clearAttachments() {
		attachments = [];
		renderChips();
	}

	document.addEventListener('DOMContentLoaded', initDom);

	window.AlignChatboxUpload = {
		consumeAttachmentsText: consumeAttachmentsText,
		hasAttachments: hasAttachments,
		clearAttachments: clearAttachments,
		addAttachment: addAttachment,
		uploadFiles: uploadFiles,
		showInlineUploadError: showInlineUploadError,
		isUploading: function () { return isUploading; },
		getAttachments: function () { return attachments.slice(); }
	};
})(window, document);
