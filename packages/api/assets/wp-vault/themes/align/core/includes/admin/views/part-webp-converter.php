<?php
/**
 * WebP Media Converter Partial View.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="align-settings-card">
	<div class="align-card-header">
		<div>
			<h3 class="align-card-title"><?php esc_html_e( 'WebP Media Converter', 'align' ); ?></h3>
			<p class="align-card-desc"><?php esc_html_e( 'Configure automatic WebP conversion on upload and bulk compress existing Media Library images.', 'align' ); ?></p>
		</div>
	</div>

	<div class="align-card-body">
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Auto Convert to WebP', 'align' ); ?></th>
				<td>
					<label style="font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
						<input type="checkbox" name="align_webp_enable_auto_convert" value="1" <?php checked( 1, get_option( 'align_webp_enable_auto_convert', 1 ) ); ?>>
						<span><?php esc_html_e( 'Automatically convert uploaded images (JPG/PNG) to WebP format.', 'align' ); ?></span>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="align_webp_quality"><?php esc_html_e( 'WebP Quality', 'align' ); ?></label></th>
				<td>
					<input type="number" name="align_webp_quality" id="align_webp_quality" min="1" max="100" class="small-text" value="<?php echo esc_attr( get_option( 'align_webp_quality', 80 ) ); ?>">
					<p class="description"><?php esc_html_e( 'Compression quality (1-100). Default is 80.', 'align' ); ?></p>
				</td>
			</tr>
		</table>

		<!-- Bulk Convert Subsection -->
		<div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #f1f5f9;">
			<h4 style="margin: 0 0 4px 0; font-size: 14px; font-weight: 700; color: #0f172a;"><?php esc_html_e( 'Bulk Convert Media Library Images', 'align' ); ?></h4>
			<p style="margin: 0 0 14px 0; font-size: 12.5px; color: #64748b;"><?php esc_html_e( 'Scan existing JPG/PNG images in your Media Library and convert them to WebP format using the Cloud API endpoint.', 'align' ); ?></p>
			
			<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
				<button type="button" class="button button-secondary" id="align-webp-check-status-btn">
					<?php esc_html_e( 'Check Image Status', 'align' ); ?>
				</button>
				<button type="button" class="button button-primary" id="align-webp-start-bulk-btn" disabled>
					<?php esc_html_e( 'Convert All to WebP', 'align' ); ?>
				</button>
				<span id="align-webp-bulk-status-text" style="font-size: 13px; font-weight: 500;"></span>
			</div>

			<div id="align-webp-progress-wrap" style="display: none; margin-top: 15px; max-width: 600px;">
				<div style="background: #f1f5f9; border-radius: 4px; height: 14px; overflow: hidden; position: relative;">
					<div id="align-webp-progress-bar" style="background: #0284c7; height: 100%; width: 0%; transition: width 0.3s;"></div>
				</div>
				<p id="align-webp-progress-label" style="font-size: 12px; margin-top: 6px; color: #64748b;"></p>
			</div>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	const checkBtn = document.getElementById('align-webp-check-status-btn');
	const startBulkBtn = document.getElementById('align-webp-start-bulk-btn');
	const statusText = document.getElementById('align-webp-bulk-status-text');
	const progressWrap = document.getElementById('align-webp-progress-wrap');
	const progressBar = document.getElementById('align-webp-progress-bar');
	const progressLabel = document.getElementById('align-webp-progress-label');

	let pendingIds = [];
	let totalToProcess = 0;
	let processedCount = 0;

	if (checkBtn) {
		checkBtn.addEventListener('click', function(e) {
			e.preventDefault();
			checkBtn.disabled = true;
			statusText.textContent = 'Scanning media library...';
			statusText.style.color = '#50575e';

			const nonceInput = document.querySelector('input[name="align_settings_nonce"]');
			const nonce = nonceInput ? nonceInput.value : '';

			const formData = new FormData();
			formData.append('action', 'align_webp_bulk_status');
			formData.append('security', nonce);

			fetch(ajaxurl, {
				method: 'POST',
				body: formData
			})
			.then(res => res.text().then(text => {
				try {
					return JSON.parse(text);
				} catch (e) {
					throw new Error(text.replace(/<[^>]*>?/gm, '').trim().substring(0, 120) || ('HTTP ' + res.status));
				}
			}))
			.then(data => {
				checkBtn.disabled = false;
				if (data.success) {
					const info = data.data;
					pendingIds = info.ids || [];
					totalToProcess = info.pending || 0;
					statusText.textContent = 'Total: ' + info.total + ' images | Converted: ' + info.converted + ' | Pending: ' + info.pending;
					statusText.style.color = '#1d2327';

					if (info.pending > 0) {
						startBulkBtn.disabled = false;
						startBulkBtn.textContent = 'Convert (' + info.pending + ') Images to WebP';
					} else {
						startBulkBtn.disabled = true;
						startBulkBtn.textContent = 'All Images Converted';
					}
				} else {
					statusText.textContent = 'Error: ' + (data.data && data.data.message ? data.data.message : 'Unknown error');
					statusText.style.color = '#d63638';
				}
			})
			.catch(err => {
				checkBtn.disabled = false;
				statusText.textContent = 'Scan failed: ' + err.message;
				statusText.style.color = '#d63638';
			});
		});
	}

	if (startBulkBtn) {
		startBulkBtn.addEventListener('click', function(e) {
			e.preventDefault();
			if (!pendingIds.length) return;

			startBulkBtn.disabled = true;
			checkBtn.disabled = true;
			progressWrap.style.display = 'block';

			// Configure async worker pool concurrency.
			const CONCURRENCY = 2;
			const totalInitial = pendingIds.length;
			processedCount = 0;
			let activeWorkers = 0;

			function updateProgress() {
				const percent = Math.min(100, Math.round((processedCount / totalInitial) * 100));
				progressBar.style.width = percent + '%';
				progressLabel.textContent = 'Processing: ' + processedCount + ' / ' + totalInitial + ' (' + percent + '%)';
			}

			function runWorker() {
				// When queue is empty, check if all workers have completed.
				if (pendingIds.length === 0) {
					if (activeWorkers === 0) {
						progressBar.style.width = '100%';
						progressLabel.textContent = 'Completed! Processed ' + processedCount + ' images.';
						checkBtn.disabled = false;
						startBulkBtn.disabled = true;
						startBulkBtn.textContent = 'All Images Converted';
					}
					return;
				}

				// Dequeue one attachment ID for this worker.
				const id = pendingIds.shift();
				activeWorkers++;

				const nonceInput = document.querySelector('input[name="align_settings_nonce"]');
				const nonce = nonceInput ? nonceInput.value : '';

				const formData = new FormData();
				formData.append('action', 'align_webp_bulk_batch');
				formData.append('security', nonce);
				formData.append('ids[]', id);

				fetch(ajaxurl, {
					method: 'POST',
					body: formData
				})
				.then(res => res.text().then(text => {
					try {
						return JSON.parse(text);
					} catch (e) {
						throw new Error(text.replace(/<[^>]*>?/gm, '').trim().substring(0, 120) || ('HTTP ' + res.status));
					}
				}))
				.then(data => {
					processedCount++;
					updateProgress();
				})
				.catch(err => {
					console.error('[Align WebP Bulk Worker Error]', err);
					processedCount++;
					updateProgress();
				})
				.finally(() => {
					activeWorkers--;
					runWorker();
				});
			}

			// Initialize progress display and spawn initial workers.
			updateProgress();
			const workersToStart = Math.min(CONCURRENCY, pendingIds.length);
			for (let i = 0; i < workersToStart; i++) {
				runWorker();
			}
		});
	}
});
</script>
