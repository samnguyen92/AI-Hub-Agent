<?php
/**
 * Align Settings - Bulk AI Translator & SEO Generator Partial View
 *
 * Adheres strictly to Align Settings Style Guide:
 * - Card Design System (.align-settings-card)
 * - 100% Transparent Button Standard (.button-primary, .button-secondary, .button-danger)
 * - Zero Emojis Policy
 * - Responsive typography-first table
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$all_langs    = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_languages() : array();
$default_lang = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en';
$target_langs = array();

foreach ( $all_langs as $lang_item ) {
	if ( $lang_item['code'] !== $default_lang ) {
		$target_langs[] = $lang_item;
	}
}
?>
<div class="align-settings-card" id="align-bulk-translator-card">
	<div class="align-card-header">
		<div>
			<h3 class="align-card-title"><?php esc_html_e( 'Bulk AI Translator & SEO Assistant', 'align' ); ?></h3>
			<p class="align-card-desc"><?php esc_html_e( 'Batch translate Pages and Posts to another language with AI, followed by automatic SEO metadata generation and Rank Math synchronization.', 'align' ); ?></p>
		</div>
	</div>

	<div class="align-card-body">
		<!-- Configuration Controls -->
		<table class="form-table align-bulk-config-table" style="margin-top: 0;">
			<tbody>
				<tr>
					<th scope="row">
						<label for="align_bulk_target_lang"><?php esc_html_e( 'Target Language', 'align' ); ?></label>
					</th>
					<td>
						<?php if ( empty( $target_langs ) ) : ?>
							<p class="description" style="color: #dc2626;">
								<?php esc_html_e( 'No secondary languages configured. Please add a language in the Active Languages tab first.', 'align' ); ?>
							</p>
						<?php else : ?>
							<select id="align_bulk_target_lang" style="min-width: 220px; height: 32px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
								<?php foreach ( $target_langs as $t_lang ) : ?>
									<option value="<?php echo esc_attr( $t_lang['code'] ); ?>"
										data-script="<?php echo esc_attr( $t_lang['script'] ?? 'default' ); ?>"
										data-prompt="<?php echo esc_attr( $t_lang['prompt_hint'] ?? '' ); ?>">
										<?php echo esc_html( $t_lang['name'] . ' (' . strtoupper( $t_lang['code'] ) . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Select the destination language for bulk translation.', 'align' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="align_bulk_custom_instructions"><?php esc_html_e( 'Translation Style & Instructions', 'align' ); ?></label>
					</th>
					<td>
						<div style="max-width: 720px;">
							<textarea id="align_bulk_custom_instructions" rows="3" style="width: 100%; font-family: monospace; font-size: 12px; border-radius: 6px; border: 1px solid #cbd5e1; padding: 7px 9px; line-height: 1.45;" placeholder="<?php esc_attr_e( 'Specific style guidelines, target audience, tone, or terminology rules for this translation run...', 'align' ); ?>"></textarea>
							<div style="display: flex; gap: 10px; align-items: center; margin-top: 5px; flex-wrap: wrap;">
								<p class="description" style="margin: 0; flex: 1;">
									<?php esc_html_e( 'Optional. Automatically pre-filled from language settings if configured. You can modify instructions for this run.', 'align' ); ?>
								</p>
								<button type="button" class="button button-secondary" id="align-bulk-load-hk-preset-btn" style="height: 24px; line-height: 22px; font-size: 11px; padding: 0 8px;">
									<?php esc_html_e( 'Load HK Corporate Preset', 'align' ); ?>
								</button>
							</div>
						</div>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Content Types', 'align' ); ?></th>
					<td>
						<fieldset style="display: flex; gap: 18px; align-items: center; flex-wrap: wrap;">
							<label style="font-weight: 500; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
								<input type="checkbox" id="align_bulk_type_page" value="page" checked="checked" />
								<span><?php esc_html_e( 'Pages', 'align' ); ?></span>
							</label>
							<label style="font-weight: 500; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
								<input type="checkbox" id="align_bulk_type_post" value="post" checked="checked" />
								<span><?php esc_html_e( 'Posts', 'align' ); ?></span>
							</label>
							<label style="font-weight: 500; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
								<input type="checkbox" id="align_bulk_type_templates" value="wp_template" checked="checked" />
								<span><?php esc_html_e( 'Layout Templates', 'align' ); ?></span>
							</label>
							<label style="font-weight: 500; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
								<input type="checkbox" id="align_bulk_type_parts" value="wp_template_part" checked="checked" />
								<span><?php esc_html_e( 'Template Parts', 'align' ); ?></span>
							</label>
							<label style="font-weight: 500; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
								<input type="checkbox" id="align_bulk_type_blocks" value="wp_block" checked="checked" />
								<span><?php esc_html_e( 'Reusable Blocks & CTAs', 'align' ); ?></span>
							</label>
						</fieldset>
						<p class="description"><?php esc_html_e( 'Choose which content types, layout templates, and template parts should be scanned and translated. Internal links will be automatically rewritten to the target language.', 'align' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Translation Scope', 'align' ); ?></th>
					<td>
						<fieldset style="display: flex; flex-direction: column; gap: 8px;">
							<label style="font-weight: 500; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
								<input type="radio" name="align_bulk_scope" value="missing_or_untranslated" checked="checked" />
								<span><strong><?php esc_html_e( 'Missing & Untranslated Content', 'align' ); ?></strong> — <?php esc_html_e( 'Translate items that are missing or exist but still contain untranslated source language content (Recommended).', 'align' ); ?></span>
							</label>
							<label style="font-weight: 500; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
								<input type="radio" name="align_bulk_scope" value="missing_only" />
								<span><strong><?php esc_html_e( 'Missing Pages Only', 'align' ); ?></strong> — <?php esc_html_e( 'Only translate items that do not have a translation record created yet.', 'align' ); ?></span>
							</label>
							<label style="font-weight: 500; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
								<input type="radio" name="align_bulk_scope" value="all" />
								<span><strong><?php esc_html_e( 'All Content (Force Re-translate)', 'align' ); ?></strong> — <?php esc_html_e( 'Re-translate and overwrite existing translations with fresh AI translation.', 'align' ); ?></span>
							</label>
						</fieldset>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'AI SEO Generation', 'align' ); ?></th>
					<td>
						<label style="font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
							<input type="checkbox" id="align_bulk_enable_seo" value="1" checked="checked" />
							<span><?php esc_html_e( 'Automatically generate AI SEO metadata and sync to Rank Math after translating content.', 'align' ); ?></span>
						</label>
						<p class="description" style="margin-top: 4px;">
							<?php esc_html_e( 'Once content translation finishes, the system analyzes the translated text to extract primary focus keywords, SEO title, meta description, and URL slug in the target language.', 'align' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<!-- Action Buttons Bar -->
		<div style="margin-top: 20px; padding-top: 18px; border-top: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
			<div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
				<button type="button" class="button button-secondary" id="align-bulk-scan-btn">
					<?php esc_html_e( 'Scan Translatable Content', 'align' ); ?>
				</button>
				<button type="button" class="button button-secondary" id="align-bulk-scan-urls-btn" title="<?php esc_attr_e( 'Scan translated pages for unlocalized internal links without using AI tokens', 'align' ); ?>">
					<?php esc_html_e( 'Scan URL Problems', 'align' ); ?>
				</button>
				<button type="button" class="button button-primary" id="align-bulk-start-btn" disabled="disabled">
					<?php esc_html_e( 'Start Bulk Translation', 'align' ); ?>
				</button>
				<button type="button" class="button button-secondary" id="align-bulk-pause-btn" style="display: none;">
					<?php esc_html_e( 'Pause', 'align' ); ?>
				</button>
				<button type="button" class="button button-secondary" id="align-bulk-resume-btn" style="display: none;">
					<?php esc_html_e( 'Resume', 'align' ); ?>
				</button>
				<button type="button" class="button button-danger" id="align-bulk-stop-btn" style="display: none;">
					<?php esc_html_e( 'Stop', 'align' ); ?>
				</button>
			</div>

			<div id="align-bulk-stats-summary" style="font-size: 12.5px; font-weight: 600; color: #475569; display: none;">
				<span id="align-bulk-stat-completed" style="color: #059669;">0</span> / <span id="align-bulk-stat-total">0</span> <?php esc_html_e( 'Processed', 'align' ); ?>
				<span style="margin: 0 6px; color: #cbd5e1;">|</span>
				<span id="align-bulk-stat-failed" style="color: #dc2626;">0</span> <?php esc_html_e( 'Failed', 'align' ); ?>
			</div>
		</div>

		<!-- URL Problems Alert & Batch Fixer -->
		<div id="align-bulk-url-issues-wrap" style="display: none; margin-top: 16px; padding: 12px 16px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
			<div style="display: flex; align-items: center; gap: 10px;">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
				<span id="align-bulk-url-issues-text" style="font-size: 13px; font-weight: 600; color: #92400e;">
					<?php esc_html_e( 'Found unlocalized internal links.', 'align' ); ?>
				</span>
			</div>
			<div style="display: flex; align-items: center; gap: 8px;">
				<button type="button" class="button button-primary" id="align-bulk-fix-urls-btn" style="height: 28px; line-height: 26px; font-size: 12px;">
					<?php esc_html_e( 'Fix All URL Problems Now', 'align' ); ?>
				</button>
				<button type="button" class="button button-secondary" id="align-bulk-dismiss-urls-btn" style="height: 28px; line-height: 26px; font-size: 12px;">
					<?php esc_html_e( 'Dismiss', 'align' ); ?>
				</button>
			</div>
		</div>

		<!-- Progress Bar Wrap -->
		<div id="align-bulk-progress-wrap" style="display: none; margin-top: 16px;">
			<div style="background: #e2e8f0; border-radius: 6px; height: 12px; overflow: hidden; position: relative;">
				<div id="align-bulk-progress-bar" style="background: #0284c7; height: 100%; width: 0%; transition: width 0.25s ease;"></div>
			</div>
			<div style="display: flex; justify-content: space-between; align-items: center; margin-top: 6px;">
				<span id="align-bulk-progress-label" style="font-size: 12px; color: #64748b; font-weight: 500;">
					<?php esc_html_e( 'Ready to start.', 'align' ); ?>
				</span>
				<span id="align-bulk-progress-percent" style="font-size: 12px; color: #0f172a; font-weight: 600;">
					0%
				</span>
			</div>
		</div>

		<!-- Real-time Status Table -->
		<div id="align-bulk-table-wrap" style="display: none; margin-top: 20px;">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
				<div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
					<h4 style="margin: 0; font-size: 13px; font-weight: 700; color: #0f172a;">
						<?php esc_html_e( 'Queue & Execution Status', 'align' ); ?>
					</h4>
					<div id="align-bulk-filter-pills" style="display: inline-flex; align-items: center; gap: 6px;">
						<button type="button" class="align-bulk-filter-pill is-active" data-filter="all"><?php esc_html_e( 'All', 'align' ); ?> (<span id="align-bulk-count-all">0</span>)</button>
						<button type="button" class="align-bulk-filter-pill" data-filter="needs_trans"><?php esc_html_e( 'Needs Translation', 'align' ); ?> (<span id="align-bulk-count-needs">0</span>)</button>
						<button type="button" class="align-bulk-filter-pill" data-filter="translated"><?php esc_html_e( 'Already Translated', 'align' ); ?> (<span id="align-bulk-count-done">0</span>)</button>
					</div>
				</div>
				<input type="search" id="align-bulk-search" placeholder="<?php esc_attr_e( 'Filter by title or ID...', 'align' ); ?>" style="height: 30px; min-width: 200px; font-size: 12px; border-radius: 6px; border: 1px solid #cbd5e1; padding: 0 10px;" />
			</div>
			<div style="max-height: 380px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 6px;">
				<table class="widefat striped" id="align-bulk-status-table" style="border: none; margin: 0;">
					<thead>
						<tr>
							<th style="width: 36px; text-align: center;">
								<input type="checkbox" id="align-bulk-select-all" title="<?php esc_attr_e( 'Select All / Deselect All', 'align' ); ?>" checked="checked" style="margin: 0;" />
							</th>
							<th style="width: 55px;"><?php esc_html_e( 'ID', 'align' ); ?></th>
							<th><?php esc_html_e( 'Source Title', 'align' ); ?></th>
							<th style="width: 105px;"><?php esc_html_e( 'Type', 'align' ); ?></th>
							<th style="width: 150px;"><?php esc_html_e( 'Translation Status', 'align' ); ?></th>
							<th style="width: 170px;"><?php esc_html_e( 'AI SEO Status', 'align' ); ?></th>
							<th style="width: 110px; text-align: right;"><?php esc_html_e( 'Actions', 'align' ); ?></th>
						</tr>
					</thead>
					<tbody id="align-bulk-table-body">
						<!-- Dynamically populated via JS -->
					</tbody>
				</table>
			</div>
		</div>

		<!-- Live Log Stream -->
		<div id="align-bulk-log-wrap" style="display: none; margin-top: 16px;">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
				<span style="font-size: 11.5px; font-weight: 600; color: #64748b;"><?php esc_html_e( 'Execution Log', 'align' ); ?></span>
				<button type="button" id="align-bulk-clear-log-btn" style="background: none; border: none; font-size: 11.5px; color: #0284c7; cursor: pointer; padding: 0;">
					<?php esc_html_e( 'Clear Log', 'align' ); ?>
				</button>
			</div>
			<div id="align-bulk-log-console" style="background: #0f172a; color: #e2e8f0; font-family: monospace; font-size: 11.5px; padding: 10px 12px; border-radius: 6px; max-height: 140px; overflow-y: auto; line-height: 1.5;">
			</div>
		</div>
	</div>
</div>

<style>
/* Bulk Translator Badges & Styling */
.align-bulk-badge { display: inline-flex; align-items: center; padding: 2px 7px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
.align-bulk-badge.is-pending { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
.align-bulk-badge.is-working { background: #f0f9ff; color: #0284c7; border: 1px solid #bae6fd; }
.align-bulk-badge.is-success { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
.align-bulk-badge.is-untranslated { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
.align-bulk-badge.is-error   { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.align-bulk-badge.is-skipped { background: #f8fafc; color: #94a3b8; border: 1px solid #e2e8f0; }
.align-bulk-table-link { font-size: 11.5px; color: #0284c7; text-decoration: none; font-weight: 600; margin-left: 6px; }
.align-bulk-table-link:hover { text-decoration: underline; }
.align-bulk-filter-pill { background: transparent; border: 1px solid #cbd5e1; color: #64748b; font-size: 11.5px; font-weight: 600; padding: 2px 9px; border-radius: 9999px; cursor: pointer; transition: all 0.15s ease; }
.align-bulk-filter-pill:hover { border-color: #94a3b8; color: #334155; }
.align-bulk-filter-pill.is-active { background: #f0f9ff; border-color: #bae6fd; color: #0284c7; }
</style>
