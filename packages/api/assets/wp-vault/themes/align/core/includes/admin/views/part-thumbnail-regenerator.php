<?php
/**
 * Thumbnail Regenerator Accordion Partial View.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$has_checkpoint = (bool) get_option( 'align_last_regenerated_id', false );
?>
<!-- Force Thumbnail Regenerator Accordion Card -->
<div class="align-settings-card align-regen-accordion is-accordion" id="align-regen-dashboard">
	<div class="align-card-header" id="align-regen-header" style="cursor: pointer; user-select: none;" title="<?php esc_attr_e( 'Click to toggle Force Regenerate Thumbnails', 'align' ); ?>">
		<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
			<div>
				<h3 class="align-card-title"><?php esc_html_e( 'Force Regenerate Thumbnails', 'align' ); ?></h3>
				<p class="align-card-desc"><?php esc_html_e( 'Thoroughly regenerate image thumbnails according to the active sizes above and purge orphaned files from disk.', 'align' ); ?></p>
			</div>
			<?php if ( $has_checkpoint ) : ?>
			<span style="background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">
				<?php esc_html_e( 'Checkpoint available', 'align' ); ?>
			</span>
			<?php endif; ?>
		</div>
		<div class="align-accordion-toggle">
			<span id="align-regen-toggle-label"><?php esc_html_e( 'Toggle Tool', 'align' ); ?></span>
			<span class="dashicons dashicons-arrow-down-alt2 align-regen-toggle-icon" style="font-size: 16px; width: 16px; height: 16px;"></span>
		</div>
	</div>

	<div class="align-card-body align-regen-body" id="align-regen-body" style="display: none;">
		<!-- Stats Grid -->
		<div class="align-regen-stats-grid">
			<div class="align-regen-stat-box">
				<div class="align-regen-stat-label"><?php esc_html_e( 'Total Images', 'align' ); ?></div>
				<div class="align-regen-stat-val" id="align-regen-stat-total">0</div>
			</div>
			<div class="align-regen-stat-box is-success">
				<div class="align-regen-stat-label"><?php esc_html_e( 'Regenerated', 'align' ); ?></div>
				<div class="align-regen-stat-val" id="align-regen-stat-success">0</div>
			</div>
			<div class="align-regen-stat-box is-deleted">
				<div class="align-regen-stat-label"><?php esc_html_e( 'Old Sizes Purged', 'align' ); ?></div>
				<div class="align-regen-stat-val" id="align-regen-stat-deleted">0</div>
			</div>
			<div class="align-regen-stat-box is-error">
				<div class="align-regen-stat-label"><?php esc_html_e( 'Errors / Failed', 'align' ); ?></div>
				<div class="align-regen-stat-val" id="align-regen-stat-error">0</div>
			</div>
		</div>

		<!-- Progress Bar -->
		<div class="align-regen-progress-wrapper" style="margin: 18px 0;">
			<div class="align-regen-progress-header" style="display: flex; justify-content: space-between; font-size: 12.5px; font-weight: 600; color: #475569; margin-bottom: 6px;">
				<span id="align-regen-progress-percent">0%</span>
				<span id="align-regen-progress-count">0 / 0</span>
			</div>
			<div class="align-regen-progress-bar-bg" style="background: #f1f5f9; border-radius: 4px; height: 14px; overflow: hidden;">
				<div class="align-regen-progress-bar-fill" id="align-regen-progress-fill" style="background: #0284c7; height: 100%; width: 0%; transition: width 0.3s;"></div>
			</div>
		</div>

		<!-- Action Controls -->
		<div class="align-regen-actions-bar" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 18px;">
			<button type="button" class="button button-primary" id="align-regen-start-btn">
				<span class="align-btn-text"><?php esc_html_e( 'Regenerate All Thumbnails', 'align' ); ?></span>
			</button>

			<button type="button" class="button button-secondary" id="align-regen-resume-btn" style="<?php echo $has_checkpoint ? 'display: inline-flex;' : 'display: none;'; ?>">
				<span class="align-btn-text"><?php esc_html_e( 'Resume Last Job', 'align' ); ?></span>
			</button>

			<button type="button" class="button button-danger" id="align-regen-cancel-btn" style="display: none;">
				<span class="align-btn-text"><?php esc_html_e( 'Cancel Process', 'align' ); ?></span>
			</button>

			<button type="button" class="button button-secondary" id="align-regen-retry-btn" style="display: none;">
				<span class="align-btn-text"><?php esc_html_e( 'Retry Failed Items', 'align' ); ?></span>
			</button>

			<?php if ( $has_checkpoint ) : ?>
			<button type="button" class="button button-link-delete" id="align-regen-reset-checkpoint-btn" style="margin-left: auto;">
				<?php esc_html_e( 'Clear Checkpoint', 'align' ); ?>
			</button>
			<?php endif; ?>
		</div>

		<!-- Real-time Console Log -->
		<div class="align-regen-console-wrapper">
			<div class="align-regen-console-header" style="display: flex; justify-content: space-between; align-items: center; background: #0f172a; color: #94a3b8; padding: 8px 14px; border-radius: 6px 6px 0 0; font-size: 12px; font-weight: 600;">
				<span><?php esc_html_e( 'Live Processing Console', 'align' ); ?></span>
				<button type="button" class="align-regen-console-clear-btn" id="align-regen-clear-log-btn" style="background: none; border: 0; color: #94a3b8; cursor: pointer; font-size: 11px;">
					<?php esc_html_e( 'Clear Log', 'align' ); ?>
				</button>
			</div>
			<div class="align-regen-console-body" id="align-regen-console-body" style="background: #1e293b; color: #f8fafc; font-family: monospace; font-size: 12px; padding: 12px 14px; max-height: 220px; overflow-y: auto; border-radius: 0 0 6px 6px; line-height: 1.6;">
				<div class="align-regen-log-entry">
					<span class="align-regen-log-badge badge-info" style="color: #38bdf8; font-weight: 700;">[READY]</span>
					<span><?php esc_html_e( 'Click "Regenerate All Thumbnails" to start.', 'align' ); ?></span>
				</div>
			</div>
		</div>
	</div>
</div>
