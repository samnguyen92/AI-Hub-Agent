<?php
/**
 * HTML to Gutenberg Block Converter Sub-view.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="align-card-header" id="align-block-converter-header" style="cursor: pointer; user-select: none;" title="<?php esc_attr_e( 'Click to toggle HTML to Gutenberg Block Converter', 'align' ); ?>">
	<div>
		<h3 class="align-card-title"><?php esc_html_e( 'HTML to Gutenberg Block Converter', 'align' ); ?></h3>
		<p class="align-card-desc"><?php esc_html_e( 'Convert raw HTML code into native Gutenberg block markup using the Align Converter microservice.', 'align' ); ?></p>
	</div>
	<div class="align-accordion-toggle">
		<span id="align-block-converter-toggle-label"><?php esc_html_e( 'Show Details', 'align' ); ?></span>
		<span class="dashicons dashicons-arrow-down-alt2" style="font-size: 16px; width: 16px; height: 16px;"></span>
	</div>
</div>
<div class="align-card-body" id="align-block-converter-body" style="display: none;">

	<!-- 2-Column Responsive Layout -->
	<div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 16px;">
		<!-- Column 1: Raw HTML Input -->
		<div style="flex: 1 1 450px; min-width: 320px;">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
				<label for="align-convert-input-html" style="font-weight: 600;"><?php esc_html_e( 'Raw HTML Input', 'align' ); ?></label>
				<span id="align-convert-char-count" style="font-size: 12px; color: #64748b;">0 <?php esc_html_e( 'characters', 'align' ); ?></span>
			</div>
			<textarea id="align-convert-input-html" rows="14" style="width: 100%; font-family: monospace, Consolas, Monaco, monospace; font-size: 13px; padding: 10px; border-radius: 4px; box-sizing: border-box; resize: vertical;" placeholder="<section class=&quot;hero&quot;>&#10;  <h1>Welcome to Align</h1>&#10;  <p>Convert raw HTML elements into clean Gutenberg blocks seamlessly.</p>&#10;</section>" spellcheck="false"></textarea>
		</div>

		<!-- Column 2: Block Markup Output -->
		<div style="flex: 1 1 450px; min-width: 320px;">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
				<label for="align-convert-output-markup" style="font-weight: 600;"><?php esc_html_e( 'Gutenberg Block Markup (Read-only)', 'align' ); ?></label>
				<span id="align-convert-output-stats" style="font-size: 12px; color: #64748b;"></span>
			</div>
			<textarea id="align-convert-output-markup" rows="14" readonly style="width: 100%; font-family: monospace, Consolas, Monaco, monospace; font-size: 13px; background: #f6f7f7; padding: 10px; border-radius: 4px; box-sizing: border-box; color: #2c3338; resize: vertical;" placeholder="<!-- wp:align/element {&quot;tag&quot;:&quot;section&quot;} -->&#10;...&#10;<!-- /wp:align/element -->" spellcheck="false"></textarea>
		</div>
	</div>

	<!-- Action Controls -->
	<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
		<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
			<button type="button" class="button button-primary" id="align-convert-btn">
				<?php esc_html_e( 'Convert to Blocks', 'align' ); ?>
			</button>
			<button type="button" class="button button-secondary" id="align-convert-clear-btn"><?php esc_html_e( 'Clear', 'align' ); ?></button>
			<span class="spinner" id="align-convert-spinner" style="float: none; margin: 0;"></span>
			<span id="align-convert-status" style="font-size: 13px; font-weight: 500;"></span>
		</div>
		<div>
			<button type="button" class="button button-secondary" id="align-convert-copy-btn">
				<span id="align-convert-copy-text"><?php esc_html_e( 'Copy Markup', 'align' ); ?></span>
			</button>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	// Accordion toggle for Block Converter Playground
	const card = document.getElementById('align-block-converter-card');
	const header = document.getElementById('align-block-converter-header');
	const body = document.getElementById('align-block-converter-body');
	const label = document.getElementById('align-block-converter-toggle-label');

	if (header && card && body) {
		header.addEventListener('click', function(e) {
			if (e.target && e.target.closest('button, a, input, label, textarea')) {
				return;
			}
			const isOpen = card.classList.contains('is-open');
			if (isOpen) {
				body.style.display = 'none';
				card.classList.remove('is-open');
				if (label) label.textContent = '<?php echo esc_js( __( 'Show Details', 'align' ) ); ?>';
			} else {
				body.style.display = 'block';
				card.classList.add('is-open');
				if (label) label.textContent = '<?php echo esc_js( __( 'Hide Details', 'align' ) ); ?>';
			}
		});
	}

	const inputHtml = document.getElementById('align-convert-input-html');
	const outputMarkup = document.getElementById('align-convert-output-markup');
	const convertBtn = document.getElementById('align-convert-btn');
	const clearBtn = document.getElementById('align-convert-clear-btn');
	const copyBtn = document.getElementById('align-convert-copy-btn');
	const copyText = document.getElementById('align-convert-copy-text');
	const spinner = document.getElementById('align-convert-spinner');
	const statusEl = document.getElementById('align-convert-status');
	const charCount = document.getElementById('align-convert-char-count');
	const outputStats = document.getElementById('align-convert-output-stats');
	const apiUrlInput = document.getElementById('align_minify_api_url');

	// Update character counter for input HTML
	if (inputHtml && charCount) {
		const updateCount = function() {
			charCount.textContent = inputHtml.value.length.toLocaleString() + ' <?php echo esc_js( __( 'characters', 'align' ) ); ?>';
		};
		inputHtml.addEventListener('input', updateCount);
		inputHtml.addEventListener('paste', function() { setTimeout(updateCount, 50); });
	}

	function setStatus(msg, isError) {
		if (!statusEl) return;
		statusEl.innerHTML = isError ? '<span style="color: #d63638;">' + msg + '</span>' : '<span style="color: #008a20;">' + msg + '</span>';
	}

	// Convert Action Handler
	if (convertBtn) {
		convertBtn.addEventListener('click', function(e) {
			e.preventDefault();
			const htmlContent = inputHtml ? inputHtml.value.trim() : '';
			if (!htmlContent) {
				setStatus('<?php echo esc_js( __( 'Please enter HTML markup to convert.', 'align' ) ); ?>', true);
				if (inputHtml) inputHtml.focus();
				return;
			}

			convertBtn.disabled = true;
			if (spinner) spinner.classList.add('is-active');
			setStatus('<?php echo esc_js( __( 'Converting HTML to blocks...', 'align' ) ); ?>', false);

			const nonceInput = document.querySelector('input[name="align_settings_nonce"]');
			const nonce = nonceInput ? nonceInput.value : '';
			const apiUrl = apiUrlInput ? apiUrlInput.value.trim() : '';

			const formData = new FormData();
			formData.append('action', 'align_convert_html_to_blocks');
			formData.append('align_settings_nonce', nonce);
			formData.append('nonce', nonce);
			formData.append('html', htmlContent);
			formData.append('word_mode', '0');
			if (apiUrl) formData.append('api_url', apiUrl);

			console.log('[Align BlockConverter] Sending HTML to conversion endpoint.');

			fetch(ajaxurl, { method: 'POST', body: formData })
			.then(function(res) {
				return res.text().then(function(text) {
					try {
						return JSON.parse(text);
					} catch (parseErr) {
						console.error('[Align BlockConverter] Non-JSON response received:', text);
						var cleanText = text.replace(/<[^>]*>?/gm, '').trim();
						throw new Error(cleanText.substring(0, 150) || ('HTTP ' + res.status));
					}
				});
			})
			.then(function(resData) {
				convertBtn.disabled = false;
				if (spinner) spinner.classList.remove('is-active');

				if (!resData.success || !resData.data) {
					var errMsg = (resData.data && resData.data.message) ? resData.data.message : '<?php echo esc_js( __( 'Conversion failed', 'align' ) ); ?>';
					setStatus('✗ ' + errMsg, true);
					console.error('[Align BlockConverter] Conversion failed:', errMsg);
					return;
				}

				var payload = resData.data;
				if (outputMarkup) outputMarkup.value = payload.markup || '';

				var blockCount = payload.blocks_count || 0;
				var latency = payload.latency_ms || 0;
				var statsText = blockCount > 0 ? (blockCount + ' <?php echo esc_js( __( 'blocks', 'align' ) ); ?> (' + latency + 'ms)') : (latency + 'ms');
				if (outputStats) outputStats.textContent = statsText;

				setStatus('✓ <?php echo esc_js( __( 'Converted successfully!', 'align' ) ); ?>', false);
				console.log('[Align BlockConverter] Block conversion succeeded:', payload);
			})
			.catch(function(err) {
				convertBtn.disabled = false;
				if (spinner) spinner.classList.remove('is-active');
				setStatus('✗ <?php echo esc_js( __( 'Request error: ', 'align' ) ); ?>' + err.message, true);
				console.error('[Align BlockConverter] Network error during conversion:', err);
			});
		});
	}

	// Clear Action Handler
	if (clearBtn) {
		clearBtn.addEventListener('click', function(e) {
			e.preventDefault();
			if (inputHtml) inputHtml.value = '';
			if (outputMarkup) outputMarkup.value = '';
			if (statusEl) statusEl.innerHTML = '';
			if (outputStats) outputStats.textContent = '';
			if (charCount) charCount.textContent = '0 <?php echo esc_js( __( 'characters', 'align' ) ); ?>';
			console.log('[Align BlockConverter] Cleared inputs and outputs.');
		});
	}

	// Copy Markup Action Handler with Clipboard API and fallback
	if (copyBtn && copyText) {
		let copyTimeout = null;
		copyBtn.addEventListener('click', function(e) {
			e.preventDefault();
			const markupText = outputMarkup ? outputMarkup.value : '';
			if (!markupText) {
				setStatus('<?php echo esc_js( __( 'Nothing to copy. Please convert HTML first.', 'align' ) ); ?>', true);
				return;
			}

			const handleSuccess = function() {
				if (copyTimeout) clearTimeout(copyTimeout);
				copyText.innerHTML = '<span style="color: #008a20; font-weight: 600;"><?php echo esc_js( __( 'Copied! ✓', 'align' ) ); ?></span>';
				copyTimeout = setTimeout(function() {
					copyText.textContent = '<?php echo esc_js( __( 'Copy Markup', 'align' ) ); ?>';
				}, 2500);
				console.log('[Align BlockConverter] Block markup copied to clipboard.');
			};

			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(markupText).then(handleSuccess).catch(function(err) {
					console.warn('[Align BlockConverter] Clipboard API failed, attempting execCommand fallback:', err);
					fallbackCopy(markupText, handleSuccess);
				});
			} else {
				fallbackCopy(markupText, handleSuccess);
			}
		});

		function fallbackCopy(text, onSuccess) {
			const tempArea = document.createElement('textarea');
			tempArea.value = text;
			tempArea.style.position = 'fixed';
			tempArea.style.top = '-9999px';
			tempArea.style.left = '-9999px';
			document.body.appendChild(tempArea);
			tempArea.focus();
			tempArea.select();
			try {
				const successful = document.execCommand('copy');
				if (successful && typeof onSuccess === 'function') onSuccess();
			} catch (err) {
				console.error('[Align BlockConverter] Fallback copy failed:', err);
			} finally {
				document.body.removeChild(tempArea);
			}
		}
	}
});
</script>
