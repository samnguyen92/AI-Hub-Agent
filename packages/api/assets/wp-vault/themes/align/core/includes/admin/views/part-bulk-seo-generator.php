<?php
/**
 * Align Settings - Bulk AI SEO Generator Partial View
 *
 * Adheres strictly to Align Settings Style Guide:
 * - Card Design System (.align-settings-card)
 * - 100% Transparent Button Standard (.button-primary, .button-secondary, .button-danger)
 * - Zero Emojis Policy
 * - Responsive typography-first table & real-time score badges
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$all_langs    = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_languages() : array();
$default_lang = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en';
?>
<div class="align-settings-card" id="align-bulk-seo-card">
	<div class="align-card-header">
		<div>
			<h3 class="align-card-title"><?php esc_html_e( 'Bulk AI SEO Generator & Rank Math Optimizer', 'align' ); ?></h3>
			<p class="align-card-desc"><?php esc_html_e( 'Batch generate and optimize SEO metadata for Pages and Posts, aligning with Rank Math criteria to achieve 80+ Green SEO scores.', 'align' ); ?></p>
		</div>
	</div>

	<div class="align-card-body">
		<!-- Metric Summary Badges -->
		<div class="align-seo-metrics-bar" style="display: flex; gap: 14px; margin-bottom: 20px; flex-wrap: wrap;">
			<div class="align-seo-metric-box" style="flex: 1; min-width: 140px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 16px;">
				<div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 600;"><?php esc_html_e( 'Total Audited', 'align' ); ?></div>
				<div id="align-seo-stat-total" style="font-size: 24px; font-weight: 700; color: #0f172a; margin-top: 4px;">0</div>
			</div>
			<div class="align-seo-metric-box" style="flex: 1; min-width: 140px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 12px 16px;">
				<div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #166534; font-weight: 600;"><?php esc_html_e( 'Optimized (80-100)', 'align' ); ?></div>
				<div id="align-seo-stat-opt" style="font-size: 24px; font-weight: 700; color: #15803d; margin-top: 4px;">0</div>
			</div>
			<div class="align-seo-metric-box" style="flex: 1; min-width: 140px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 6px; padding: 12px 16px;">
				<div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #92400e; font-weight: 600;"><?php esc_html_e( 'Moderate (50-79)', 'align' ); ?></div>
				<div id="align-seo-stat-mod" style="font-size: 24px; font-weight: 700; color: #b45309; margin-top: 4px;">0</div>
			</div>
			<div class="align-seo-metric-box" style="flex: 1; min-width: 140px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 12px 16px;">
				<div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #991b1b; font-weight: 600;"><?php esc_html_e( 'Needs Optimization (<50)', 'align' ); ?></div>
				<div id="align-seo-stat-needs" style="font-size: 24px; font-weight: 700; color: #dc2626; margin-top: 4px;">0</div>
			</div>
		</div>

		<!-- Configuration Controls -->
		<table class="form-table align-bulk-seo-config-table" style="margin-top: 0; margin-bottom: 20px;">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Content Types', 'align' ); ?></th>
					<td>
						<fieldset style="display: flex; gap: 18px; align-items: center; flex-wrap: wrap;">
							<label style="font-weight: 500; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
								<input type="checkbox" id="align_seo_type_page" value="page" checked="checked" />
								<span><?php esc_html_e( 'Pages', 'align' ); ?></span>
							</label>
							<label style="font-weight: 500; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
								<input type="checkbox" id="align_seo_type_post" value="post" checked="checked" />
								<span><?php esc_html_e( 'Posts', 'align' ); ?></span>
							</label>
						</fieldset>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="align_seo_filter_lang"><?php esc_html_e( 'Language Filter', 'align' ); ?></label>
					</th>
					<td>
						<select id="align_seo_filter_lang" style="min-width: 220px; height: 32px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
							<option value="all"><?php esc_html_e( 'All Languages', 'align' ); ?></option>
							<?php foreach ( $all_langs as $l_item ) : ?>
								<option value="<?php echo esc_attr( $l_item['code'] ); ?>">
									<?php echo esc_html( $l_item['name'] . ' (' . strtoupper( $l_item['code'] ) . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Filter content to optimize by a specific language or process across all languages.', 'align' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'SEO Optimization Scope', 'align' ); ?></th>
					<td>
						<fieldset style="display: flex; flex-direction: column; gap: 8px;">
							<label style="font-weight: 500; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
								<input type="radio" name="align_seo_scope" value="needs_optimization" checked="checked" />
								<span><strong><?php esc_html_e( 'Needs Optimization', 'align' ); ?></strong> — <?php esc_html_e( 'Include items with Rank Math score below 80 or missing SEO metadata (Recommended).', 'align' ); ?></span>
							</label>
							<label style="font-weight: 500; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
								<input type="radio" name="align_seo_scope" value="missing_only" />
								<span><strong><?php esc_html_e( 'Missing SEO Only', 'align' ); ?></strong> — <?php esc_html_e( 'Only items with no Focus Keyword configured.', 'align' ); ?></span>
							</label>
							<label style="font-weight: 500; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
								<input type="radio" name="align_seo_scope" value="all" />
								<span><strong><?php esc_html_e( 'All Content', 'align' ); ?></strong> — <?php esc_html_e( 'Force audit and re-evaluate all items regardless of current score.', 'align' ); ?></span>
							</label>
						</fieldset>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Options', 'align' ); ?></th>
					<td>
						<div style="display: flex; gap: 24px; align-items: center; flex-wrap: wrap;">
							<div style="font-weight: 500; font-size: 12.5px; display: inline-flex; align-items: center; gap: 6px; color: #64748b;">
								<span class="dashicons dashicons-lock" style="font-size: 16px; width: 16px; height: 16px; color: #10b981;"></span>
								<span><?php esc_html_e( 'URL Slugs Permanently Locked (Preserves permalinks & prevents broken links)', 'align' ); ?></span>
							</div>
							<div style="display: inline-flex; align-items: center; gap: 8px;">
								<label for="align_seo_batch_size" style="font-size: 13px; font-weight: 500;"><?php esc_html_e( 'Batch Size:', 'align' ); ?></label>
								<select id="align_seo_batch_size" style="height: 30px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 12px;">
									<option value="1">1 item / step (Safest)</option>
									<option value="2" selected="selected">2 items / step (Balanced)</option>
									<option value="3">3 items / step (Fast)</option>
								</select>
							</div>
						</div>
					</td>
				</tr>
			</tbody>
		</table>

		<!-- Action & Search Toolbar -->
		<div class="align-seo-toolbar" style="display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; background: #f8fafc; padding: 10px 14px; border-radius: 6px; border: 1px solid #e2e8f0;">
			<div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
				<button type="button" id="align-seo-btn-scan" class="button button-secondary">
					<?php esc_html_e( 'Scan & Audit Content', 'align' ); ?>
				</button>
				<button type="button" id="align-seo-btn-run" class="button button-primary" style="display: none;">
					<?php esc_html_e( 'Optimize Selected', 'align' ); ?>
				</button>
				<button type="button" id="align-seo-btn-pause" class="button button-secondary" style="display: none;">
					<?php esc_html_e( 'Pause', 'align' ); ?>
				</button>
				<button type="button" id="align-seo-btn-stop" class="button button-danger" style="display: none;">
					<?php esc_html_e( 'Stop', 'align' ); ?>
				</button>
			</div>

			<div style="display: flex; gap: 10px; align-items: center;">
				<input type="text" id="align-seo-search-input" placeholder="<?php esc_attr_e( 'Filter by title or slug...', 'align' ); ?>" style="width: 220px; height: 32px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 12.5px; padding: 0 10px;" />
				<span id="align-seo-selected-count" style="font-size: 12.5px; color: #64748b; font-weight: 500;"></span>
			</div>
		</div>

		<!-- Progress Bar Container -->
		<div id="align-seo-progress-wrap" style="display: none; margin-bottom: 18px;">
			<div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px;">
				<span id="align-seo-progress-text"><?php esc_html_e( 'Optimizing SEO metadata...', 'align' ); ?></span>
				<span id="align-seo-progress-percent">0%</span>
			</div>
			<div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
				<div id="align-seo-progress-bar" style="width: 0%; height: 100%; background: #0284c7; border-radius: 4px; transition: width 0.3s ease;"></div>
			</div>
		</div>

		<!-- Results Table -->
		<div class="align-seo-table-wrap" style="border: 1px solid #e2e8f0; border-radius: 6px; overflow-x: auto; background: #fff;">
			<table class="wp-list-table widefat fixed striped" id="align-seo-table" style="border: none; margin: 0;">
				<thead>
					<tr>
						<td style="width: 38px; text-align: center; vertical-align: middle;">
							<input type="checkbox" id="align-seo-select-all" checked="checked" />
						</td>
						<th style="width: 55px;"><?php esc_html_e( 'ID', 'align' ); ?></th>
						<th style="width: 28%;"><?php esc_html_e( 'Title / Permalink', 'align' ); ?></th>
						<th style="width: 75px;"><?php esc_html_e( 'Type', 'align' ); ?></th>
						<th style="width: 65px;"><?php esc_html_e( 'Lang', 'align' ); ?></th>
						<th style="width: 22%;"><?php esc_html_e( 'Focus Keyword', 'align' ); ?></th>
						<th style="width: 110px; text-align: center;"><?php esc_html_e( 'SEO Score', 'align' ); ?></th>
						<th style="width: 120px; text-align: right;"><?php esc_html_e( 'Action', 'align' ); ?></th>
					</tr>
				</thead>
				<tbody id="align-seo-table-body">
					<tr>
						<td colspan="8" style="text-align: center; padding: 24px; color: #64748b;">
							<?php esc_html_e( 'Click "Scan & Audit Content" to load pages and posts for SEO optimization.', 'align' ); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Terminal Live Log Output -->
		<div style="margin-top: 20px;">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
				<label for="align-seo-log-area" style="font-size: 12px; font-weight: 600; color: #475569;"><?php esc_html_e( 'Execution & Optimization Log', 'align' ); ?></label>
				<button type="button" id="align-seo-clear-log" class="button button-secondary" style="height: 24px; line-height: 22px; font-size: 11px; padding: 0 8px;">
					<?php esc_html_e( 'Clear Log', 'align' ); ?>
				</button>
			</div>
			<textarea id="align-seo-log-area" readonly="readonly" rows="6" style="width: 100%; font-family: monospace; font-size: 12px; background: #0f172a; color: #38bdf8; border: 1px solid #1e293b; border-radius: 6px; padding: 10px; line-height: 1.5; resize: vertical;" placeholder="<?php esc_attr_e( 'Process logs will be output here...', 'align' ); ?>"></textarea>
		</div>
	</div>
</div>
