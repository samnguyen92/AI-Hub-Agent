<?php
/**
 * Align Settings - Translation Matrix Dashboard View
 *
 * Provides comprehensive visual monitoring of translation coverage (EN ↔ ZH)
 * across all WordPress Pages and Posts with quick 1-click translate/edit links.
 * Follows Align Settings Style Guide.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$languages    = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_languages() : array();
$default_lang = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en';
?>

<div class="align-settings-card">
	<div class="align-card-header">
		<div>
			<h3 class="align-card-title"><?php esc_html_e( 'Translation Matrix Dashboard', 'align' ); ?></h3>
			<p class="align-card-desc"><?php esc_html_e( 'Track translation status across all pages and articles. Identify missing translations and jump straight into editing.', 'align' ); ?></p>
		</div>
		<div class="align-card-header-actions" style="display:flex; gap:8px; align-items:center;">
			<button type="button" class="button button-secondary" id="align-matrix-refresh-btn">
				<?php esc_html_e( 'Refresh Matrix', 'align' ); ?>
			</button>
		</div>
	</div>

	<div class="align-card-body">
		<!-- Summary Metrics Bar -->
		<div class="align-matrix-metrics" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin-bottom:16px;">
			<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px 14px;">
				<div style="font-size:11px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.04em;">
					<?php esc_html_e( 'Total Content Clusters', 'align' ); ?>
				</div>
				<div id="align-matrix-metric-total" style="font-size:22px; font-weight:700; color:#0f172a; margin-top:4px;">-</div>
			</div>

			<div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:12px 14px;">
				<div style="font-size:11px; font-weight:600; color:#166534; text-transform:uppercase; letter-spacing:0.04em;">
					<?php esc_html_e( 'Fully Translated', 'align' ); ?>
				</div>
				<div id="align-matrix-metric-complete" style="font-size:22px; font-weight:700; color:#15803d; margin-top:4px;">-</div>
			</div>

			<div style="background:#fef2f2; border:1px solid #fecaca; border-radius:6px; padding:12px 14px;">
				<div style="font-size:11px; font-weight:600; color:#991b1b; text-transform:uppercase; letter-spacing:0.04em;">
					<?php esc_html_e( 'Missing Translations', 'align' ); ?>
				</div>
				<div id="align-matrix-metric-missing" style="font-size:22px; font-weight:700; color:#dc2626; margin-top:4px;">-</div>
			</div>
		</div>

		<!-- Filter Bar -->
		<div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:14px; justify-content:space-between;">
			<div style="display:flex; gap:8px; align-items:center;">
				<select id="align-matrix-type-filter" style="height:30px; font-size:12px; border-radius:5px;">
					<option value="all"><?php esc_html_e( 'All Post Types', 'align' ); ?></option>
					<option value="page"><?php esc_html_e( 'Pages Only', 'align' ); ?></option>
					<option value="post"><?php esc_html_e( 'Blog Posts Only', 'align' ); ?></option>
				</select>

				<select id="align-matrix-status-filter" style="height:30px; font-size:12px; border-radius:5px;">
					<option value="all"><?php esc_html_e( 'All Translation States', 'align' ); ?></option>
					<option value="missing"><?php esc_html_e( 'Missing Translations Only', 'align' ); ?></option>
					<option value="complete"><?php esc_html_e( 'Complete Only', 'align' ); ?></option>
				</select>
			</div>

			<div style="display:flex; gap:8px; align-items:center;">
				<input type="search" id="align-matrix-search-input" placeholder="<?php esc_attr_e( 'Filter by title…', 'align' ); ?>" style="width:220px; height:30px; font-size:12px; border-radius:5px;">
			</div>
		</div>

		<!-- Matrix Table -->
		<div style="max-height:480px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:6px;">
			<table class="wp-list-table widefat fixed striped" id="align-matrix-table" style="border:none;">
				<thead>
					<tr>
						<th style="width:30%;"><?php esc_html_e( 'English Content (Primary)', 'align' ); ?></th>
						<th style="width:85px;"><?php esc_html_e( 'Type', 'align' ); ?></th>
						<?php foreach ( $languages as $l ) : ?>
							<?php if ( $l['code'] !== $default_lang ) : ?>
								<th><?php echo esc_html( $l['name'] ); ?> (<?php echo esc_html( strtoupper( $l['code'] ) ); ?>)</th>
							<?php endif; ?>
						<?php endforeach; ?>
						<th style="width:130px;"><?php esc_html_e( 'Actions', 'align' ); ?></th>
					</tr>
				</thead>
				<tbody id="align-matrix-tbody">
					<tr>
						<td colspan="4" style="text-align:center; padding:24px; color:#94a3b8;">
							<?php esc_html_e( 'Loading translation matrix data…', 'align' ); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>
