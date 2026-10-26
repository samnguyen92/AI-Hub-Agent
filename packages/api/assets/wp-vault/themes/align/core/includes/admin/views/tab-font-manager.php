<?php
/**
 * Tab Content: Typography & 3-Slot Font Manager (Font 1, Font 2, Font 3).
 *
 * Adheres strictly to Align Settings Style Guide:
 * - Card Design System (.align-settings-card)
 * - Zero Emoji Policy
 * - 100% Transparent Button Standard (.button-primary, .button-secondary, .button-danger)
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$slots             = class_exists( 'Align_Font_Manager' ) ? Align_Font_Manager::get_slots() : array();
$supported_weights = class_exists( 'Align_Font_Manager' ) ? Align_Font_Manager::SUPPORTED_WEIGHTS : array();
?>

<style>
.align-font-dropzone:hover {
	border-color: #0284c7 !important;
	background: #e0f2fe !important;
}
.align-font-slot-card {
	transition: box-shadow 0.2s ease, border-color 0.2s ease;
}
.align-font-slot-card:hover {
	border-color: #cbd5e1;
	box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
</style>

<div class="align-settings-card">
	<div class="align-card-header">
		<div>
			<h3 class="align-card-title"><?php esc_html_e( 'Typography & Fonts', 'align' ); ?></h3>
			<p class="align-card-desc">
				<?php esc_html_e( 'Upload font files (.woff2, .woff, .ttf, .otf). The system automatically assigns font-primary-1..n, compiles @font-face rules, generates CSS variables, and coordinates preloading.', 'align' ); ?>
			</p>
		</div>
	</div>
	<div class="align-card-body" style="padding: 24px;">
		<div class="align-font-slots-grid" id="align-font-slots-container" style="display: flex; flex-direction: column; gap: 24px;">

			<?php
			$slot_count = count( $slots );
			foreach ( $slots as $slot_key => $slot ) :
				$slot_num = 1;
				if ( preg_match( '/^font_(\d+)$/', $slot_key, $m ) ) {
					$slot_num = (int) $m[1];
				}
				$default_name  = 'font-primary-' . $slot_num;
				$name          = ! empty( $slot['name'] ) ? $slot['name'] : $default_name;
				$original_name = ! empty( $slot['original_name'] ) ? $slot['original_name'] : '';
				$fallback      = ! empty( $slot['fallback'] ) ? $slot['fallback'] : 'sans-serif';
				$loading       = ! empty( $slot['loading'] ) ? $slot['loading'] : ( $slot_num <= 2 ? 'preload' : 'normal' );
				$files         = isset( $slot['files'] ) && is_array( $slot['files'] ) ? $slot['files'] : array();
				$css_var_name  = '--font-primary-' . $slot_num;
			?>
			<div class="align-font-slot-card" data-slot="<?php echo esc_attr( $slot_key ); ?>" data-slot-num="<?php echo esc_attr( $slot_num ); ?>" style="border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff; padding: 20px;">
				<!-- Slot Header -->
				<div style="display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 16px; flex-wrap: wrap;">
					<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
						<h4 style="margin: 0; font-size: 15px; font-weight: 700; color: #0f172a;">
							Font <?php echo esc_html( $slot_num ); ?>
						</h4>
						<code class="align-font-slug-badge" style="background: #f1f5f9; color: #0284c7; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">
							<?php echo esc_html( $name ); ?>
						</code>
						<?php if ( ! empty( $original_name ) ) : ?>
							<span class="align-font-orig-name" style="font-size: 12.5px; color: #64748b; font-weight: 500;">
								(<?php echo esc_html( $original_name ); ?>)
							</span>
						<?php endif; ?>
					</div>

					<!-- Copy CSS & Actions -->
					<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
						<code style="background: #f8fafc; border: 1px solid #cbd5e1; color: #334155; padding: 4px 10px; border-radius: 6px; font-size: 12px;">font-family: var(<?php echo esc_html( $css_var_name ); ?>);</code>
						<button type="button" class="button button-secondary align-copy-css-btn" data-copy="font-family: var(<?php echo esc_attr( $css_var_name ); ?>);" style="height: 32px; border-radius: 6px; font-size: 12px; font-weight: 600;">
							<?php esc_html_e( 'Copy CSS', 'align' ); ?>
						</button>
						<?php if ( $slot_num > 2 ) : ?>
							<button type="button" class="button button-danger align-remove-slot-btn" data-slot="<?php echo esc_attr( $slot_key ); ?>" style="height: 32px; border-radius: 6px; font-size: 12px; font-weight: 600; padding: 0 10px;" title="<?php esc_attr_e( 'Remove slot', 'align' ); ?>">
								&times;
							</button>
						<?php endif; ?>
					</div>
				</div>

				<!-- Direct Upload Dropzone (Upload-First) -->
				<div class="align-font-dropzone" data-slot="<?php echo esc_attr( $slot_key ); ?>" style="border: 2px dashed #bae6fd; border-radius: 8px; padding: 18px 20px; text-align: center; background: #f0f9ff; cursor: pointer; transition: all 0.2s ease; margin-bottom: 16px;">
					<div style="font-size: 13.5px; font-weight: 600; color: #0284c7; margin-bottom: 4px;">
						+ <?php esc_html_e( 'Upload Font Files (.woff2, .woff, .ttf, .otf)', 'align' ); ?>
					</div>
					<div style="font-size: 12px; color: #64748b;">
						<?php esc_html_e( 'Click to select single or multiple font weights at once. Weights (400, 500, 600, 700...) and styles are auto-detected.', 'align' ); ?>
					</div>
				</div>

				<!-- Hidden meta inputs for font name and original name -->
				<input type="hidden" name="align_font_slots[<?php echo esc_attr( $slot_key ); ?>][name]" value="<?php echo esc_attr( $name ); ?>" class="align-font-name-input" />
				<input type="hidden" name="align_font_slots[<?php echo esc_attr( $slot_key ); ?>][original_name]" value="<?php echo esc_attr( $original_name ); ?>" class="align-font-original-name-input" />

				<!-- Files / Weights Table -->
				<div class="align-font-files-wrapper">
					<div class="align-font-files-list" style="display: flex; flex-direction: column; gap: 8px;">
						<?php if ( ! empty( $files ) ) : ?>
							<?php foreach ( $files as $f_idx => $file_data ) : ?>
								<div class="align-font-file-row" style="display: flex; gap: 10px; align-items: center; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px;">
									<select name="align_font_slots[<?php echo esc_attr( $slot_key ); ?>][files][<?php echo esc_attr( $f_idx ); ?>][weight]" style="height: 32px; width: 145px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 12px; font-weight: 500;">
										<?php foreach ( $supported_weights as $w_val => $w_label ) : ?>
											<option value="<?php echo esc_attr( $w_val ); ?>" <?php selected( (string) ($file_data['weight'] ?? '400'), (string) $w_val ); ?>>
												<?php echo esc_html( $w_label ); ?>
											</option>
										<?php endforeach; ?>
									</select>

									<select name="align_font_slots[<?php echo esc_attr( $slot_key ); ?>][files][<?php echo esc_attr( $f_idx ); ?>][style]" style="height: 32px; width: 95px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 12px; font-weight: 500;">
										<option value="normal" <?php selected( $file_data['style'] ?? 'normal', 'normal' ); ?>>Normal</option>
										<option value="italic" <?php selected( $file_data['style'] ?? 'normal', 'italic' ); ?>>Italic</option>
									</select>

									<input type="text" name="align_font_slots[<?php echo esc_attr( $slot_key ); ?>][files][<?php echo esc_attr( $f_idx ); ?>][url]" value="<?php echo esc_attr( $file_data['url'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Font file URL or /wp-content/...', 'align' ); ?>" class="align-font-file-url widefat" style="height: 32px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 12.5px; flex: 1;" />

									<button type="button" class="button button-danger align-remove-font-row-btn" style="height: 32px; border-radius: 6px; font-size: 12.5px; font-weight: 600; padding: 0 10px;" title="<?php esc_attr_e( 'Remove file row', 'align' ); ?>">
										&times;
									</button>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>

				<!-- Live Preview Box -->
				<div class="align-font-preview-box" style="margin-top: 16px; padding: 14px 16px; background: #f8fafc; border-radius: 6px; border: 1px dashed #cbd5e1; <?php echo empty( $files ) ? 'display: none;' : ''; ?>">
					<span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.5px;">Live Preview:</span>
					<p class="align-font-preview-text" style="margin: 6px 0 0; font-family: '<?php echo esc_attr( $name ); ?>', <?php echo esc_attr( $fallback ); ?>; font-size: 18px; color: #0f172a; line-height: 1.4;">
						The quick brown fox jumps over the lazy dog. 0123456789
					</p>
				</div>

				<!-- Collapsible Advanced Options (Default Closed) -->
				<div class="align-font-advanced-wrap" style="margin-top: 14px; border-top: 1px solid #f1f5f9; padding-top: 10px;">
					<div class="align-font-advanced-toggle" style="cursor: pointer; display: flex; align-items: center; justify-content: space-between; font-size: 12px; font-weight: 600; color: #64748b; user-select: none;">
						<span style="display: flex; align-items: center; gap: 6px;">
							<?php esc_html_e( 'Advanced Options (Fallback, Alias, Loading Strategy)', 'align' ); ?>
							<span class="align-accordion-arrow" style="font-size: 10px; transition: transform 0.2s ease;">&#9660;</span>
						</span>
					</div>
					<div class="align-font-advanced-panel" style="display: none; margin-top: 12px; background: #f8fafc; padding: 14px 16px; border-radius: 6px; border: 1px solid #e2e8f0;">
						<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px;">
							<div>
								<label style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 5px;">
									<?php esc_html_e( 'Font Family Alias', 'align' ); ?>
								</label>
								<input type="text" name="align_font_slots[<?php echo esc_attr( $slot_key ); ?>][name]" value="<?php echo esc_attr( $name ); ?>" placeholder="<?php echo esc_attr( $default_name ); ?>" class="align-font-name-advanced-input widefat" style="height: 32px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 12.5px;" />
							</div>
							<div>
								<label style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 5px;">
									<?php esc_html_e( 'Generic Fallback', 'align' ); ?>
								</label>
								<select name="align_font_slots[<?php echo esc_attr( $slot_key ); ?>][fallback]" class="align-font-fallback-select widefat" style="height: 32px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 12.5px;">
									<option value="sans-serif" <?php selected( $fallback, 'sans-serif' ); ?>>sans-serif</option>
									<option value="serif" <?php selected( $fallback, 'serif' ); ?>>serif</option>
									<option value="monospace" <?php selected( $fallback, 'monospace' ); ?>>monospace</option>
								</select>
							</div>
							<div>
								<label style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 5px;">
									<?php esc_html_e( 'Loading Strategy', 'align' ); ?>
								</label>
								<select name="align_font_slots[<?php echo esc_attr( $slot_key ); ?>][loading]" class="widefat" style="height: 32px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 12.5px;">
									<option value="preload" <?php selected( $loading, 'preload' ); ?>><?php esc_html_e( 'Preload (Top 1 Priority)', 'align' ); ?></option>
									<option value="normal" <?php selected( $loading, 'normal' ); ?>><?php esc_html_e( 'Normal (On-Demand)', 'align' ); ?></option>
									<option value="delay" <?php selected( $loading, 'delay' ); ?>><?php esc_html_e( 'Delay (User Interaction)', 'align' ); ?></option>
								</select>
							</div>
						</div>
					</div>
				</div>
			</div>
			<?php endforeach; ?>

		</div>

		<!-- Add New Font Slot Button -->
		<div style="margin-top: 20px;">
			<button type="button" id="align-add-font-slot-btn" class="button button-secondary" style="height: 32px; border-radius: 6px; font-size: 12.5px; font-weight: 600;">
				+ <?php esc_html_e( 'Add Another Font Slot (font-primary-n)', 'align' ); ?>
			</button>
		</div>
	</div>
</div>

<script>
<?php readfile( __DIR__ . '/tab-font-manager.js' ); ?>
</script>
