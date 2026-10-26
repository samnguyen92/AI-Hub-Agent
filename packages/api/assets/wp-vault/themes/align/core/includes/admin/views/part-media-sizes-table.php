<?php
/**
 * Media Sizes Table Partial View.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$custom_sizes = get_option( 'align_custom_image_sizes', array() );
if ( ! is_array( $custom_sizes ) ) {
	$custom_sizes = array();
}
?>
<div class="align-settings-card is-accordion" id="align-media-sizes-card">
	<div class="align-card-header" id="align-media-sizes-header" style="cursor: pointer; user-select: none;" title="<?php esc_attr_e( 'Click to toggle Registered Media Sizes', 'align' ); ?>">
		<div>
			<h3 class="align-card-title"><?php esc_html_e( 'Registered Media Sizes', 'align' ); ?></h3>
			<p class="align-card-desc"><?php esc_html_e( 'Manage WordPress standard media sizes and custom sub-sizes in one place. Add, customize dimensions, or delete unused sizes.', 'align' ); ?></p>
		</div>
		<div style="display: flex; align-items: center; gap: 12px;">
			<button type="button" class="button button-secondary" id="align-toggle-add-size-btn">
				<?php esc_html_e( '+ Add Custom Size', 'align' ); ?>
			</button>
			<div class="align-accordion-toggle">
				<span id="align-media-sizes-toggle-label"><?php esc_html_e( 'Show Sizes', 'align' ); ?></span>
				<span class="dashicons dashicons-arrow-down-alt2 align-media-sizes-toggle-icon" style="font-size: 16px; width: 16px; height: 16px;"></span>
			</div>
		</div>
	</div>

	<div class="align-card-body align-media-sizes-body" id="align-media-sizes-body" style="display: none;">
		<!-- Quick Add Custom Size Panel -->
		<div id="align-new-size-panel" style="display: none; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 16px; margin-bottom: 18px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
			<h4 style="margin: 0 0 12px 0; color: #1e293b; font-size: 14px; font-weight: 600;">
				<?php esc_html_e( 'Add New Custom Image Size', 'align' ); ?>
			</h4>
	<div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
		<div style="flex: 1; min-width: 200px;">
			<label for="align-new-size-label" style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: #475569;"><?php esc_html_e( 'Display Label *', 'align' ); ?></label>
			<input type="text" id="align-new-size-label" class="regular-text" placeholder="e.g. Hero Banner" style="width: 100%; height: 34px;">
		</div>
		<div style="width: 120px;">
			<label for="align-new-size-width" style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: #475569;"><?php esc_html_e( 'Width (px)', 'align' ); ?></label>
			<input type="number" id="align-new-size-width" class="small-text" value="0" min="0" style="width: 100%; height: 34px;">
		</div>
		<div style="width: 120px;">
			<label for="align-new-size-height" style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: #475569;"><?php esc_html_e( 'Height (px)', 'align' ); ?></label>
			<input type="number" id="align-new-size-height" class="small-text" value="0" min="0" style="width: 100%; height: 34px;">
		</div>
		<div style="padding-bottom: 6px;">
			<label title="<?php esc_attr_e( 'When enabled, images will be cropped to exact dimensions rather than scaled proportionally.', 'align' ); ?>" style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; font-size: 13px; user-select: none;">
				<input type="checkbox" id="align-new-size-crop" value="1">
				<?php esc_html_e( 'Hard Crop', 'align' ); ?>
			</label>
		</div>
		<div style="display: flex; gap: 8px; margin-left: auto;">
			<button type="button" class="button" id="align-cancel-new-size-btn"><?php esc_html_e( 'Cancel', 'align' ); ?></button>
			<button type="button" class="button button-primary" id="align-save-new-size-btn" style="display: inline-flex; align-items: center; gap: 5px;">
				<span class="align-save-btn-text"><?php esc_html_e( 'Save Size', 'align' ); ?></span>
			</button>
		</div>
	</div>
	<div id="align-new-size-feedback" style="display: none; margin-top: 10px; font-size: 13px; padding: 6px 10px; border-radius: 4px;"></div>
</div>

<table class="widefat fixed striped" id="align-media-sizes-table">
	<thead>
		<tr>
			<th style="width: 250px;"><?php esc_html_e( 'Size Name', 'align' ); ?></th>
			<th style="width: 140px;"><?php esc_html_e( 'Width (px)', 'align' ); ?></th>
			<th style="width: 140px;"><?php esc_html_e( 'Height (px)', 'align' ); ?></th>
			<th style="width: 140px;"><?php esc_html_e( 'Crop Mode', 'align' ); ?></th>
			<th style="width: 100px;"><?php esc_html_e( 'Actions', 'align' ); ?></th>
		</tr>
	</thead>
	<tbody id="align-media-sizes-tbody">
		<?php
		// 1. Standard Sizes
		$standard_definitions = class_exists( 'Align_Image_Sizes' ) ? Align_Image_Sizes::get_standard_sizes_definitions() : array();
		foreach ( $standard_definitions as $size_name => $def ) :
			$opts       = align_get_image_size_option_names( $size_name );
			$w          = get_option( $opts['w'], get_option( "{$size_name}_size_w", $def['default_w'] ) );
			$h          = get_option( $opts['h'], get_option( "{$size_name}_size_h", $def['default_h'] ) );
			$align_crop = get_option( $opts['crop'], null );
			$crop       = ( null !== $align_crop ) ? ( ! empty( $align_crop ) ? 1 : 0 ) : ( ! empty( $def['default_crop'] ) ? 1 : 0 );

			// Skip if size was deleted (0x0)
			if ( 0 === (int) $w && 0 === (int) $h ) {
				continue;
			}
		?>
		<tr class="align-size-row" data-size-type="standard" data-size-name="<?php echo esc_attr( $size_name ); ?>">
			<td>
				<strong><?php echo esc_html( $def['label'] ); ?></strong>
				<br><code style="font-size: 11px; color: #666;"><?php echo esc_html( $size_name ); ?></code>
			</td>
			<td>
				<input type="number" name="<?php echo esc_attr( $opts['w'] ); ?>" value="<?php echo esc_attr( $w ); ?>" class="small-text align-size-w" min="0">
			</td>
			<td>
				<input type="number" name="<?php echo esc_attr( $opts['h'] ); ?>" value="<?php echo esc_attr( $h ); ?>" class="small-text align-size-h" min="0">
			</td>
			<td>
				<input type="hidden" name="<?php echo esc_attr( $opts['crop'] ); ?>" value="0">
				<label title="<?php esc_attr_e( 'When enabled, images will be cropped to exact dimensions rather than scaled proportionally.', 'align' ); ?>" style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; user-select: none;">
					<input type="checkbox" name="<?php echo esc_attr( $opts['crop'] ); ?>" value="1" <?php checked( 1, $crop ); ?> class="align-size-crop">
					<?php esc_html_e( 'Hard Crop', 'align' ); ?>
				</label>
			</td>
			<td>
				<button type="button" class="button align-delete-size-btn">
					<?php esc_html_e( 'Delete', 'align' ); ?>
				</button>
			</td>
		</tr>
		<?php endforeach; ?>

		<?php
		// 2. Custom Sizes
		if ( ! empty( $custom_sizes ) ) :
			foreach ( $custom_sizes as $index => $size ) :
				$label  = isset( $size['label'] ) ? $size['label'] : '';
				$name   = isset( $size['name'] ) ? $size['name'] : sanitize_key( $label );
				$width  = isset( $size['width'] ) ? $size['width'] : 0;
				$height = isset( $size['height'] ) ? $size['height'] : 0;
				$crop   = ! empty( $size['crop'] );
		?>
		<tr class="align-size-row" data-size-type="custom" data-size-name="<?php echo esc_attr( $name ); ?>">
			<td>
				<strong><?php echo esc_html( $label ); ?></strong>
				<br><code style="font-size: 11px; color: #666;"><?php echo esc_html( $name ); ?></code>
				<input type="hidden" name="align_custom_image_sizes[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $label ); ?>">
				<input type="hidden" name="align_custom_image_sizes[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $name ); ?>">
			</td>
			<td>
				<input type="number" name="align_custom_image_sizes[<?php echo esc_attr( $index ); ?>][width]" value="<?php echo esc_attr( $width ); ?>" class="small-text" min="0">
			</td>
			<td>
				<input type="number" name="align_custom_image_sizes[<?php echo esc_attr( $index ); ?>][height]" value="<?php echo esc_attr( $height ); ?>" class="small-text" min="0">
			</td>
			<td>
				<input type="hidden" name="align_custom_image_sizes[<?php echo esc_attr( $index ); ?>][crop]" value="0">
				<label title="<?php esc_attr_e( 'When enabled, images will be cropped to exact dimensions rather than scaled proportionally.', 'align' ); ?>" style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; user-select: none;">
					<input type="checkbox" name="align_custom_image_sizes[<?php echo esc_attr( $index ); ?>][crop]" value="1" <?php checked( true, $crop ); ?>>
					<?php esc_html_e( 'Hard Crop', 'align' ); ?>
				</label>
			</td>
			<td>
				<button type="button" class="button align-delete-size-btn"><?php esc_html_e( 'Delete', 'align' ); ?></button>
			</td>
		</tr>
		<?php
			endforeach;
		endif;
		?>
	</tbody>
</table>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	function getNonce() {
		var nonceEl = document.getElementById('align_settings_nonce') || document.querySelector('input[name="align_settings_nonce"]');
		return nonceEl ? nonceEl.value : '';
	}

	function escapeHtml(str) {
		if (typeof str !== 'string') return '';
		return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
	}

	// 0. Accordion Toggle Logic (Default: Closed)
	var sizesCard   = document.getElementById('align-media-sizes-card');
	var sizesHeader = document.getElementById('align-media-sizes-header');
	var sizesBody   = document.getElementById('align-media-sizes-body');
	var toggleLabel = document.getElementById('align-media-sizes-toggle-label');

	function toggleSizesAccordion(forceOpen) {
		if (!sizesCard || !sizesBody) return;
		var isOpen = sizesCard.classList.contains('is-open');
		var shouldOpen = (typeof forceOpen === 'boolean') ? forceOpen : !isOpen;

		if (shouldOpen) {
			sizesBody.style.display = 'block';
			sizesCard.classList.add('is-open');
			if (toggleLabel) toggleLabel.textContent = 'Hide Sizes';
		} else {
			sizesBody.style.display = 'none';
			sizesCard.classList.remove('is-open');
			if (toggleLabel) toggleLabel.textContent = 'Show Sizes';
		}
	}

	if (sizesHeader) {
		sizesHeader.addEventListener('click', function(e) {
			// Do not toggle accordion if clicking on buttons or interactive elements
			if (e.target && e.target.closest('button, a, input, label')) {
				return;
			}
			toggleSizesAccordion();
		});
	}

	// 1. Quick Add Custom Size & Instant AJAX Save
	var toggleAddBtn = document.getElementById('align-toggle-add-size-btn');
	var addPanel     = document.getElementById('align-new-size-panel');
	var cancelAddBtn = document.getElementById('align-cancel-new-size-btn');
	var saveAddBtn   = document.getElementById('align-save-new-size-btn');
	var labelInput   = document.getElementById('align-new-size-label');
	var widthInput   = document.getElementById('align-new-size-width');
	var heightInput  = document.getElementById('align-new-size-height');
	var cropInput    = document.getElementById('align-new-size-crop');
	var feedbackBox  = document.getElementById('align-new-size-feedback');
	var tbody        = document.getElementById('align-media-sizes-tbody');

	function showFeedback(msg, isError) {
		if (!feedbackBox) return;
		feedbackBox.style.display = 'block';
		feedbackBox.style.backgroundColor = isError ? '#fee2e2' : '#dcfce7';
		feedbackBox.style.color = isError ? '#991b1b' : '#166534';
		feedbackBox.style.border = '1px solid ' + (isError ? '#f87171' : '#86efac');
		feedbackBox.textContent = msg;
	}

	function hideFeedback() {
		if (feedbackBox) feedbackBox.style.display = 'none';
	}

	if (toggleAddBtn && addPanel) {
		toggleAddBtn.addEventListener('click', function(e) {
			e.stopPropagation();
			// Open accordion first if closed
			if (sizesCard && !sizesCard.classList.contains('is-open')) {
				toggleSizesAccordion(true);
				addPanel.style.display = 'block';
				hideFeedback();
				if (labelInput) labelInput.focus();
				return;
			}

			if (addPanel.style.display === 'none' || !addPanel.style.display) {
				addPanel.style.display = 'block';
				hideFeedback();
				if (labelInput) labelInput.focus();
			} else {
				addPanel.style.display = 'none';
			}
		});
	}

	if (cancelAddBtn && addPanel) {
		cancelAddBtn.addEventListener('click', function() {
			addPanel.style.display = 'none';
			hideFeedback();
			if (labelInput) labelInput.value = '';
			if (widthInput) widthInput.value = '0';
			if (heightInput) heightInput.value = '0';
			if (cropInput) cropInput.checked = false;
		});
	}

	if (saveAddBtn) {
		saveAddBtn.addEventListener('click', function() {
			var label  = (labelInput ? labelInput.value : '').trim();
			var width  = parseInt(widthInput ? widthInput.value : '0', 10) || 0;
			var height = parseInt(heightInput ? heightInput.value : '0', 10) || 0;
			var crop   = cropInput && cropInput.checked ? 1 : 0;

			if (!label) {
				showFeedback('Please provide a display label.', true);
				if (labelInput) labelInput.focus();
				return;
			}

			if (width === 0 && height === 0) {
				showFeedback('Width or Height must be greater than 0.', true);
				return;
			}

			var btnText = saveAddBtn.querySelector('.align-save-btn-text');
			saveAddBtn.disabled = true;
			if (btnText) btnText.textContent = 'Saving...';
			hideFeedback();

			var formData = new FormData();
			formData.append('action', 'align_add_custom_image_size');
			formData.append('label', label);
			formData.append('width', width);
			formData.append('height', height);
			formData.append('crop', crop);
			formData.append('security', getNonce());

			fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', {
				method: 'POST',
				body: formData
			})
			.then(function(res) { return res.json(); })
			.then(function(res) {
				saveAddBtn.disabled = false;
				if (btnText) btnText.textContent = 'Save Size';

				if (res && res.success && res.data && res.data.size) {
					var s = res.data.size;
					var idx = res.data.index;

					var newRow = document.createElement('tr');
					newRow.className = 'align-size-row';
					newRow.setAttribute('data-size-type', 'custom');
					newRow.setAttribute('data-size-name', s.name);
					newRow.style.backgroundColor = '#dcfce7';
					newRow.style.transition = 'background-color 1.5s ease';

					newRow.innerHTML =
						'<td>' +
							'<strong>' + escapeHtml(s.label) + '</strong>' +
							'<br><code style="font-size: 11px; color: #666;">' + escapeHtml(s.name) + '</code>' +
							'<input type="hidden" name="align_custom_image_sizes[' + idx + '][label]" value="' + escapeHtml(s.label) + '">' +
							'<input type="hidden" name="align_custom_image_sizes[' + idx + '][name]" value="' + escapeHtml(s.name) + '">' +
						'</td>' +
						'<td>' +
							'<input type="number" name="align_custom_image_sizes[' + idx + '][width]" value="' + s.width + '" class="small-text" min="0">' +
						'</td>' +
						'<td>' +
							'<input type="number" name="align_custom_image_sizes[' + idx + '][height]" value="' + s.height + '" class="small-text" min="0">' +
						'</td>' +
						'<td>' +
							'<input type="hidden" name="align_custom_image_sizes[' + idx + '][crop]" value="0">' +
							'<label title="When enabled, images will be cropped to exact dimensions rather than scaled proportionally." style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; user-select: none;">' +
								'<input type="checkbox" name="align_custom_image_sizes[' + idx + '][crop]" value="1" ' + (s.crop ? 'checked' : '') + '> ' +
								'Hard Crop' +
							'</label>' +
						'</td>' +
						'<td>' +
							'<button type="button" class="button align-delete-size-btn">Delete</button>' +
						'</td>';

					tbody.appendChild(newRow);

					// Reset inputs & hide panel
					if (labelInput) labelInput.value = '';
					if (widthInput) widthInput.value = '0';
					if (heightInput) heightInput.value = '0';
					if (cropInput) cropInput.checked = false;
					addPanel.style.display = 'none';

					setTimeout(function() {
						newRow.style.backgroundColor = '';
					}, 1500);
				} else {
					var err = (res && res.data && res.data.message) ? res.data.message : 'Error adding image size.';
					showFeedback(err, true);
				}
			})
			.catch(function(err) {
				console.error('[Align MediaSizes] Add size error:', err);
				saveAddBtn.disabled = false;
				if (btnText) btnText.textContent = 'Save Size';
				showFeedback('Network connection error.', true);
			});
		});
	}

	// 2. Instant AJAX Deletion (for both Standard & Custom sizes in single table)
	if (tbody) {
		tbody.addEventListener('click', function(e) {
			var deleteBtn = e.target.closest('.align-delete-size-btn');
			var row       = e.target.closest('tr');

			if (!row || !deleteBtn) return;

			var sizeType = row.getAttribute('data-size-type') || 'standard';
			var sizeName = row.getAttribute('data-size-name');

			if (!sizeName) {
				row.remove();
				return;
			}

			if (!confirm('Are you sure you want to delete size "' + sizeName + '"?')) {
				return;
			}

			deleteBtn.disabled = true;
			deleteBtn.innerText = 'Deleting...';

			var formData = new FormData();
			formData.append('action', 'align_delete_image_size');
			formData.append('size_type', sizeType);
			formData.append('size_name', sizeName);
			formData.append('security', getNonce());

			fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', {
				method: 'POST',
				body: formData
			})
			.then(function(res) { return res.json(); })
			.then(function(res) {
				if (res && res.success) {
					row.style.transition = 'all 0.3s ease';
					row.style.opacity = '0';
					row.style.transform = 'scale(0.95)';
					setTimeout(function() {
						row.remove();
					}, 300);
				} else {
					alert(res && res.data && res.data.message ? res.data.message : 'Error deleting size.');
					deleteBtn.disabled = false;
					deleteBtn.innerText = 'Delete';
				}
			})
			.catch(function(err) {
				console.error('[Align MediaSizes] Delete error:', err);
				deleteBtn.disabled = false;
				deleteBtn.innerText = 'Delete';
			});
		});
	}
});
</script>
