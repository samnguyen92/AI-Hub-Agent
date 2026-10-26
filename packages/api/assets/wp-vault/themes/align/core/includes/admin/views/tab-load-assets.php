<?php
/**
 * Tab Content: Load Custom Assets (CSS, JS & Font).
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$loaded_assets = class_exists( 'Align_Admin_Assets' )
	? Align_Admin_Assets::get_effective_loaded_assets()
	: get_option( 'align_loaded_assets', array() );
if ( ! is_array( $loaded_assets ) ) {
	$loaded_assets = array();
}
$delay_enabled = (int) get_option( 'align_enable_delay_assets', 0 ) === 1;

if ( ! function_exists( 'align_is_custom_editable_asset' ) ) {
	/**
	 * Check if an asset is an editable CSS/JS file (not font, external CDN, or core plugin).
	 *
	 * @param string $url Asset URL.
	 * @param bool   $is_core Whether the asset is a core plugin asset.
	 * @return bool True if editable asset file.
	 */
	function align_is_custom_editable_asset( $url, $is_core = false ) {
		if ( empty( $url ) || ! is_string( $url ) || $is_core ) {
			return false;
		}
		$clean = strtolower( strtok( $url, '?#' ) );
		$ext   = strtolower( pathinfo( $clean, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'css', 'js' ), true ) ) {
			return false;
		}
		if ( preg_match( '#^https?://#i', $url ) || 0 === strpos( $url, '//' ) ) {
			$site = site_url();
			if ( 0 !== strpos( $url, $site ) ) {
				return false;
			}
		}
		if ( strpos( $clean, '/align/core/' ) !== false ) {
			return false;
		}
		return true;
	}
}

if ( ! function_exists( 'align_get_theme_editor_url' ) ) {
	/**
	 * Compute Theme File Editor URL for a custom editable asset file.
	 *
	 * @param string $url Asset URL.
	 * @param bool   $is_core Whether the asset is a core plugin asset.
	 * @return string Admin theme editor URL or empty string.
	 */
	function align_get_theme_editor_url( $url, $is_core = false ) {
		if ( ! align_is_custom_editable_asset( $url, $is_core ) ) {
			return '';
		}
		if ( preg_match( '#^https?://#i', $url ) || 0 === strpos( $url, '//' ) ) {
			$site = site_url();
			$url  = substr( $url, strlen( $site ) );
		}
		if ( preg_match( '#/wp-content/themes/([^/]+)/(.+)$#i', $url, $m ) ) {
			return admin_url( 'theme-editor.php?file=' . urlencode( $m[2] ) . '&theme=' . urlencode( $m[1] ) );
		}
		$rel = ltrim( $url, '/' );
		return ! empty( $rel ) ? admin_url( 'theme-editor.php?file=' . urlencode( $rel ) . '&theme=' . urlencode( get_stylesheet() ) ) : '';
	}
}

if ( ! function_exists( 'align_get_asset_filename' ) ) {
	/**
	 * Extract concise filename with extension from an asset URL.
	 *
	 * @param string $url Asset URL.
	 * @return string Basename or original URL.
	 */
	function align_get_asset_filename( $url ) {
		if ( empty( $url ) || ! is_string( $url ) ) {
			return '';
		}
		$clean    = strtok( $url, '?#' );
		$basename = wp_basename( $clean );
		return ! empty( $basename ) ? $basename : $url;
	}
}
?>

<style>
/* Filter Bar */
.align-asset-filters-bar {
	display: flex;
	gap: 8px;
	margin-bottom: 14px;
	align-items: center;
	flex-wrap: wrap;
}
.align-asset-filter-btn {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	background: #f1f5f9;
	border: 1px solid #cbd5e1;
	color: #475569;
	padding: 5px 12px;
	border-radius: 20px;
	font-size: 12px;
	font-weight: 600;
	cursor: pointer;
	transition: all 0.15s ease-in-out;
	outline: none;
	line-height: 1.4;
}
.align-asset-filter-btn:hover {
	background: #e2e8f0;
	color: #1e293b;
	border-color: #94a3b8;
}
.align-asset-filter-btn.is-active {
	background: #0284c7;
	border-color: #0284c7;
	color: #ffffff;
}
.align-asset-filter-btn .align-filter-count {
	display: inline-block;
	background: rgba(0, 0, 0, 0.08);
	color: inherit;
	padding: 1px 7px;
	border-radius: 10px;
	font-size: 11px;
	font-weight: 700;
}
.align-asset-filter-btn.is-active .align-filter-count {
	background: rgba(255, 255, 255, 0.25);
	color: #ffffff;
}

/* Toolbar & Action Buttons */
.align-btn-toolbar { margin-bottom: 14px; display: flex; gap: 10px; align-items: center; }
.align-btn-toolbar .button {
	display: inline-flex !important; align-items: center !important; justify-content: center !important;
	height: 34px !important; padding: 0 14px !important; font-size: 13px !important; font-weight: 500 !important;
	line-height: 1 !important; box-sizing: border-box !important;
}
.align-btn-toolbar .button .dashicons {
	font-size: 18px !important; width: 18px !important; height: 18px !important;
	line-height: 18px !important; margin-right: 6px !important; margin-top: 0 !important; vertical-align: middle !important;
}
@keyframes align-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
.dashicons.spin { animation: align-spin 1s infinite linear; }
.align-select-single-file-btn {
	display: inline-flex !important; align-items: center !important; justify-content: center !important;
	height: 30px !important; width: 34px !important; padding: 0 !important; flex-shrink: 0;
}
.align-select-single-file-btn .dashicons {
	font-size: 16px !important; width: 16px !important; height: 16px !important;
	line-height: 16px !important; margin-top: 0 !important;
}
.align-remove-asset-row-btn {
	display: inline-flex !important; align-items: center !important; justify-content: center !important;
	padding: 4px !important; text-decoration: none !important;
}
.align-remove-asset-row-btn .dashicons {
	font-size: 18px !important; width: 18px !important; height: 18px !important; line-height: 18px !important;
}
.align-edit-asset-file-btn {
	display: inline-flex !important; align-items: center !important; justify-content: center !important;
	height: 30px !important; width: 34px !important; padding: 0 !important; flex-shrink: 0; color: #475569 !important;
}
.align-edit-asset-file-btn .dashicons {
	font-size: 16px !important; width: 16px !important; height: 16px !important; line-height: 16px !important; margin: 0 !important;
}
.align-edit-asset-file-btn[hidden],
.align-edit-asset-file-btn.is-hidden,
.align-core-asset-row .align-edit-asset-file-btn,
.align-asset-row[data-type="font"] .align-edit-asset-file-btn,
.align-asset-row[data-is-core="1"] .align-edit-asset-file-btn {
	display: none !important;
}


/* Table and Drag Handle */
#align-assets-table td { vertical-align: middle; }
.align-drag-cell {
	width: 36px;
	text-align: center;
	vertical-align: middle;
	user-select: none;
}
.align-asset-drag-handle {
	color: #94a3b8;
	font-size: 18px;
	width: 18px;
	height: 18px;
	line-height: 18px;
	cursor: grab;
	display: inline-block;
	vertical-align: middle;
	transition: color 0.15s ease;
}
.align-asset-drag-handle:hover {
	color: #0284c7;
}
.align-asset-drag-handle:active {
	cursor: grabbing;
}
.align-asset-row.align-asset-dragging {
	opacity: 0.45;
	background: #f1f5f9 !important;
	outline: 1px dashed #0284c7;
}
.align-asset-row.align-drop-above > td {
	border-top: 2px solid #0284c7 !important;
}
.align-asset-row.align-drop-below > td {
	border-bottom: 2px solid #0284c7 !important;
}
</style>

<div class="align-settings-card">
	<div class="align-card-header">
		<div>
			<h3 class="align-card-title"><?php esc_html_e( 'Custom Assets & Font Preload', 'align' ); ?></h3>
			<p class="align-card-desc">
				<?php esc_html_e( 'Upload or register custom CSS, JS, and Font files directly into child theme assets with sync, defer, or async loading strategies. Drag rows to reorder.', 'align' ); ?>
			</p>
		</div>
		<div class="align-btn-toolbar" style="margin-bottom: 0;">
			<button type="button" class="button button-primary" id="align-add-media-assets-btn" style="font-weight: 600;">
				<?php esc_html_e( 'Upload Asset Files', 'align' ); ?>
			</button>
			<button type="button" class="button button-secondary" id="align-add-manual-asset-btn" style="font-weight: 600;">
				<?php esc_html_e( 'Add Manual Row', 'align' ); ?>
			</button>
		</div>
	</div>

	<div class="align-card-body">
		<!-- 3-Type Filter Bar -->
		<div class="align-asset-filters-bar">
			<button type="button" class="align-asset-filter-btn is-active" data-filter="all">
				<span><?php esc_html_e( 'All', 'align' ); ?></span>
				<span class="align-filter-count">0</span>
			</button>
			<button type="button" class="align-asset-filter-btn" data-filter="css">
				<span>CSS</span>
				<span class="align-filter-count">0</span>
			</button>
			<button type="button" class="align-asset-filter-btn" data-filter="js">
				<span>JS</span>
				<span class="align-filter-count">0</span>
			</button>
			<button type="button" class="align-asset-filter-btn" data-filter="font">
				<span>Font</span>
				<span class="align-filter-count">0</span>
			</button>
		</div>

		<!-- Hidden native file inputs for OS file picker window -->
		<input type="file" id="align-native-file-input" accept=".css,.js,.woff2,.woff,.ttf,.otf,.eot" multiple style="display:none;" />
		<input type="file" id="align-single-file-input" accept=".css,.js,.woff2,.woff,.ttf,.otf,.eot" style="display:none;" />

		<!-- Auto Font CSS Banner -->
		<?php
		$font_slots = class_exists( 'Align_Font_Manager' ) ? Align_Font_Manager::get_slots() : array();
		$has_font_assets = false;
		foreach ( $loaded_assets as $a ) {
			if ( 'font' === ( $a['type'] ?? '' ) ) {
				$has_font_assets = true;
				break;
			}
		}
		if ( $has_font_assets && ! empty( $font_slots ) ) :
		?>
		<div class="align-font-css-banner" style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 6px; padding: 10px 16px; margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
			<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
				<span style="font-size: 12.5px; font-weight: 600; color: #0369a1;"><?php esc_html_e( 'Auto Font CSS:', 'align' ); ?></span>
				<?php
				$f_idx = 1;
				foreach ( $font_slots as $f_slot ) :
					if ( empty( $f_slot['files'] ) ) continue;
					$f_name = 'font-primary-' . $f_idx;
					$f_orig = ! empty( $f_slot['original_name'] ) ? ' (' . $f_slot['original_name'] . ')' : '';
				?>
					<code style="background: #ffffff; padding: 3px 8px; border-radius: 4px; font-size: 12px; color: #0284c7; border: 1px solid #e0f2fe;">
						font-family: var(--<?php echo esc_html( $f_name ); ?>);<?php echo esc_html( $f_orig ); ?>
					</code>
				<?php
					$f_idx++;
				endforeach;
				?>
			</div>
			<button type="button" class="button button-secondary align-copy-css-btn" data-copy="font-family: var(--font-primary-1);" style="height: 28px; font-size: 12px; font-weight: 600;">
				<?php esc_html_e( 'Copy font-primary-1', 'align' ); ?>
			</button>
		</div>
		<?php endif; ?>

		<table class="widefat striped" id="align-assets-table">
			<thead>
				<tr>
					<th style="width: 36px; text-align: center;" title="<?php esc_attr_e( 'Drag to reorder asset loading position', 'align' ); ?>">
						<span style="color: #94a3b8; font-size: 13px; font-weight: 600;">#</span>
					</th>
				<th style="width: 30%;"><?php esc_html_e( 'File Name', 'align' ); ?></th>
				<th style="width: 10%;"><?php esc_html_e( 'Type', 'align' ); ?></th>
				<th style="width: 13%;"><?php esc_html_e( 'Location', 'align' ); ?></th>
				<th style="width: 18%;" id="align-col-header-loading"><?php esc_html_e( 'Loading / Font Weight', 'align' ); ?></th>
				<th style="width: 8%; text-align: center;"><?php esc_html_e( 'Is Inline', 'align' ); ?></th>
				<th style="width: 7%; text-align: center;"><?php esc_html_e( 'Status', 'align' ); ?></th>
				<th style="width: 7%; text-align: center;"><?php esc_html_e( 'Action', 'align' ); ?></th>
			</tr>
		</thead>
		<tbody id="align-assets-tbody">
			<?php
			if ( empty( $loaded_assets ) ) :
			?>
				<tr class="align-no-assets-row">
					<td colspan="8" style="text-align: center; color: #64748b; padding: 24px;">
						<?php esc_html_e( 'No assets added yet. Click "Upload / Add Asset Files" or "Add Manual Row" to begin.', 'align' ); ?>
					</td>
				</tr>
			<?php
			else :
				foreach ( $loaded_assets as $index => $asset ) :
					$url          = isset( $asset['url'] ) ? esc_url( $asset['url'] ) : '';
					$type         = isset( $asset['type'] ) ? strtolower( $asset['type'] ) : 'css';
					$location     = isset( $asset['location'] ) ? strtolower( $asset['location'] ) : 'header';
					$loading      = isset( $asset['loading'] ) ? strtolower( $asset['loading'] ) : 'normal';
					$enabled      = ! empty( $asset['enabled'] ) ? 1 : 0;
					$is_inline    = ! empty( $asset['is_inline'] ) ? 1 : 0;
					$is_core      = ! empty( $asset['is_core'] ) || strpos( $url, '/align/core/assets/plugins/' ) !== false;
					$plugin_title = isset( $asset['plugin_title'] ) ? $asset['plugin_title'] : '';
					$font_weight  = isset( $asset['font_weight'] ) ? $asset['font_weight'] : ( class_exists( 'Align_Font_Manager' ) ? Align_Font_Manager::detect_weight_from_filename( $url ) : '400' );
			?>
				<tr class="align-asset-row <?php echo $is_core ? 'align-core-asset-row' : ''; ?>" data-type="<?php echo esc_attr( $type ); ?>" data-is-core="<?php echo $is_core ? '1' : '0'; ?>">
					<td class="align-drag-cell">
						<span class="dashicons dashicons-menu align-asset-drag-handle" title="<?php esc_attr_e( 'Drag to reorder asset position', 'align' ); ?>"></span>
					</td>
					<td>
						<div style="display: flex; gap: 8px; align-items: center;">
							<input type="hidden" name="align_loaded_assets[<?php echo $index; ?>][url]" value="<?php echo esc_attr( $url ); ?>" class="align-asset-url-input" />
							<span class="align-asset-filename" title="<?php echo esc_attr( $url ); ?>" style="font-weight: 500; color: #1e293b; font-size: 13px; word-break: break-all; line-height: 1.4; flex: 1;"><?php echo esc_html( align_get_asset_filename( $url ) ); ?></span>
							<?php
							$editor_url  = align_get_theme_editor_url( $url, $is_core );
							$is_editable = ! empty( $editor_url ) && ! $is_core && 'font' !== $type;
							if ( $is_editable ) :
							?>
								<a href="<?php echo esc_url( $editor_url ); ?>" target="_blank" class="button align-edit-asset-file-btn" title="<?php esc_attr_e( 'Edit Custom File in Theme File Editor (New Tab)', 'align' ); ?>" rel="noopener noreferrer">
									<span class="dashicons dashicons-edit"></span>
								</a>
							<?php endif; ?>
							<?php if ( $is_core ) : ?>
								<input type="hidden" name="align_loaded_assets[<?php echo $index; ?>][is_core]" value="1" />
							<?php else : ?>
								<button type="button" class="button align-select-single-file-btn" title="<?php esc_attr_e( 'Upload Native File', 'align' ); ?>">
									<span class="dashicons dashicons-upload"></span>
								</button>
							<?php endif; ?>
						</div>
					</td>
					<td>
						<input type="hidden" name="align_loaded_assets[<?php echo $index; ?>][type]" value="<?php echo esc_attr( $type ); ?>" class="align-asset-type-select" />
						<span class="align-asset-type-text" style="font-weight: 600; color: #475569; font-size: 12.5px; text-transform: uppercase;"><?php echo esc_html( strtoupper( $type ) ); ?></span>
					</td>
					<td>
						<select name="align_loaded_assets[<?php echo $index; ?>][location]" class="align-asset-location-select widefat">
							<option value="header" <?php selected( $location, 'header' ); ?>><?php esc_html_e( 'Header', 'align' ); ?></option>
							<option value="footer" <?php selected( $location, 'footer' ); ?>><?php esc_html_e( 'Footer', 'align' ); ?></option>
						</select>
					</td>
					<td>
						<?php if ( 'font' === $type ) :
							$supported_weights = class_exists( 'Align_Font_Manager' ) ? Align_Font_Manager::SUPPORTED_WEIGHTS : array( '400' => 'Regular (400)' );
						?>
							<select name="align_loaded_assets[<?php echo $index; ?>][font_weight]" class="align-asset-font-weight-select widefat" style="height: 32px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 12px; font-weight: 500;">
								<?php foreach ( $supported_weights as $w_val => $w_label ) : ?>
									<option value="<?php echo esc_attr( $w_val ); ?>" <?php selected( (string) $font_weight, (string) $w_val ); ?>>
										<?php echo esc_html( $w_label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<input type="hidden" name="align_loaded_assets[<?php echo $index; ?>][loading]" value="normal" />
						<?php else : ?>
							<select name="align_loaded_assets[<?php echo $index; ?>][loading]" class="align-asset-loading-select widefat" <?php disabled( 'css' === $type && ! $delay_enabled ); ?>>
								<?php if ( 'css' === $type ) : ?>
									<option value="normal" <?php selected( $loading, 'normal' ); ?>><?php esc_html_e( 'Normal (Sync)', 'align' ); ?></option>
									<?php if ( $delay_enabled ) : ?>
										<option value="delay" <?php selected( $loading, 'delay' ); ?>><?php esc_html_e( 'Delay', 'align' ); ?></option>
									<?php endif; ?>
								<?php else : ?>
									<option value="normal" <?php selected( $loading, 'normal' ); ?>><?php esc_html_e( 'Normal (Sync)', 'align' ); ?></option>
									<option value="defer" <?php selected( $loading, 'defer' ); ?>><?php esc_html_e( 'Defer', 'align' ); ?></option>
									<option value="async" <?php selected( $loading, 'async' ); ?>><?php esc_html_e( 'Async', 'align' ); ?></option>
									<?php if ( $delay_enabled ) : ?>
										<option value="delay" <?php selected( $loading, 'delay' ); ?>><?php esc_html_e( 'Delay', 'align' ); ?></option>
									<?php endif; ?>
								<?php endif; ?>
							</select>
						<?php endif; ?>
					</td>
					<td style="text-align: center; vertical-align: middle;">
						<input type="checkbox" name="align_loaded_assets[<?php echo $index; ?>][is_inline]" value="1" <?php checked( $is_inline, 1 ); ?> <?php disabled( 'font' === $type ); ?> class="align-asset-inline-checkbox" title="<?php esc_attr_e( 'Inline style/script directly into HTML markup', 'align' ); ?>" />
					</td>
					<td style="text-align: center; vertical-align: middle;">
						<?php if ( $is_core ) : ?>
							<input type="checkbox" checked="checked" disabled="disabled" style="cursor: not-allowed; opacity: 0.8;" title="<?php esc_attr_e( 'Enabled via Plugins tab', 'align' ); ?>" />
							<input type="hidden" name="align_loaded_assets[<?php echo $index; ?>][enabled]" value="1" />
						<?php else : ?>
							<input type="checkbox" name="align_loaded_assets[<?php echo $index; ?>][enabled]" value="1" <?php checked( $enabled, 1 ); ?> />
						<?php endif; ?>
					</td>
					<td style="text-align: center; vertical-align: middle;">
						<?php if ( $is_core ) : ?>
							<span class="dashicons dashicons-lock" style="color: #94a3b8; font-size: 18px; width: 18px; height: 18px; line-height: 18px;" title="<?php esc_attr_e( 'Core plugin asset cannot be deleted directly (disable in Plugins tab)', 'align' ); ?>"></span>
						<?php else : ?>
							<button type="button" class="button button-link-delete align-remove-asset-row-btn" title="<?php esc_attr_e( 'Remove Row', 'align' ); ?>">
								<span class="dashicons dashicons-trash"></span>
							</button>
						<?php endif; ?>
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
window.alignNonce = '<?php echo esc_js( wp_create_nonce( "align_settings_nonce" ) ); ?>';
window.alignIsGlobalDelayEnabled = <?php echo (int) get_option( 'align_enable_delay_assets', 0 ) === 1 ? 'true' : 'false'; ?>;
window.alignThemeStylesheet = '<?php echo esc_js( get_stylesheet() ); ?>';
<?php readfile( __DIR__ . '/tab-load-assets.js' ); ?>
</script>
