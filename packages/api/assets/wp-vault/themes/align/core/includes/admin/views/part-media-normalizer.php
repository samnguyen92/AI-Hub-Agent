<?php
/**
 * Media Filename Normalizer Accordion Partial View.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$has_checkpoint = (bool) get_option( 'align_media_normalizer_checkpoint', false );
?>
<!-- Media Filename Normalizer Accordion Card -->
<div class="align-settings-card align-normalizer-accordion is-accordion" id="align-normalizer-dashboard">
	<div class="align-card-header" id="align-normalizer-header" style="cursor: pointer; user-select: none;" title="<?php esc_attr_e( 'Click to toggle Media Filename Normalizer', 'align' ); ?>">
		<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
			<div>
				<h3 class="align-card-title"><?php esc_html_e( 'Media Filename Normalizer', 'align' ); ?></h3>
				<p class="align-card-desc"><?php esc_html_e( 'Convert filenames to clean lowercase kebab-case. Renames files on disk and synchronizes all database metadata.', 'align' ); ?></p>
			</div>
			<?php if ( $has_checkpoint ) : ?>
			<span style="background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">
				<?php esc_html_e( 'Checkpoint available', 'align' ); ?>
			</span>
			<?php endif; ?>
		</div>
		<div class="align-accordion-toggle">
			<span id="align-normalizer-toggle-label"><?php esc_html_e( 'Toggle Tool', 'align' ); ?></span>
			<span class="dashicons dashicons-arrow-down-alt2 align-normalizer-toggle-icon" style="font-size: 16px; width: 16px; height: 16px;"></span>
		</div>
	</div>

	<div class="align-card-body align-normalizer-body" id="align-normalizer-body" style="display: none;">
		<!-- Stats Grid -->
		<div class="align-normalizer-stats-grid">
			<div class="align-normalizer-stat-box">
				<div class="align-normalizer-stat-label"><?php esc_html_e( 'Total Media', 'align' ); ?></div>
				<div class="align-normalizer-stat-val" id="align-normalizer-stat-total">0</div>
			</div>
			<div class="align-normalizer-stat-box is-changed">
				<div class="align-normalizer-stat-label"><?php esc_html_e( 'Formatted', 'align' ); ?></div>
				<div class="align-normalizer-stat-val" id="align-normalizer-stat-changed">0</div>
			</div>
			<div class="align-normalizer-stat-box is-clean">
				<div class="align-normalizer-stat-label"><?php esc_html_e( 'Already Clean', 'align' ); ?></div>
				<div class="align-normalizer-stat-val" id="align-normalizer-stat-clean">0</div>
			</div>
			<div class="align-normalizer-stat-box is-error">
				<div class="align-normalizer-stat-label"><?php esc_html_e( 'Errors / Failed', 'align' ); ?></div>
				<div class="align-normalizer-stat-val" id="align-normalizer-stat-error">0</div>
			</div>
		</div>

		<!-- Progress Bar -->
		<div class="align-normalizer-progress-wrapper" style="margin: 18px 0;">
			<div class="align-normalizer-progress-header" style="display: flex; justify-content: space-between; font-size: 12.5px; font-weight: 600; color: #475569; margin-bottom: 6px;">
				<span id="align-normalizer-progress-percent">0%</span>
				<span id="align-normalizer-progress-count">0 / 0</span>
			</div>
			<div class="align-normalizer-progress-bar-bg">
				<div class="align-normalizer-progress-bar-fill" id="align-normalizer-progress-fill"></div>
			</div>
		</div>

		<!-- Action Controls -->
		<div class="align-normalizer-actions-bar" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 18px;">
			<button type="button" class="button button-primary" id="align-normalizer-start-btn">
				<span class="align-btn-text"><?php esc_html_e( 'Format All Media', 'align' ); ?></span>
			</button>

			<button type="button" class="button button-secondary" id="align-normalizer-resume-btn" style="<?php echo $has_checkpoint ? 'display: inline-flex;' : 'display: none;'; ?>">
				<span class="align-btn-text"><?php esc_html_e( 'Resume Last Job', 'align' ); ?></span>
			</button>

			<button type="button" class="button button-danger" id="align-normalizer-cancel-btn" style="display: none;">
				<span class="align-btn-text"><?php esc_html_e( 'Cancel Process', 'align' ); ?></span>
			</button>

			<button type="button" class="button button-secondary" id="align-normalizer-retry-btn" style="display: none;">
				<span class="align-btn-text"><?php esc_html_e( 'Retry Failed Items', 'align' ); ?></span>
			</button>

			<?php if ( $has_checkpoint ) : ?>
			<button type="button" class="button button-link-delete" id="align-normalizer-reset-checkpoint-btn" style="margin-left: auto;">
				<?php esc_html_e( 'Clear Checkpoint', 'align' ); ?>
			</button>
			<?php endif; ?>
		</div>

		<!-- Real-time Console Log -->
		<div class="align-normalizer-console-wrapper">
			<div class="align-normalizer-console-header">
				<span><?php esc_html_e( 'Live Processing Console', 'align' ); ?></span>
				<button type="button" class="align-normalizer-console-clear-btn" id="align-normalizer-clear-log-btn">
					<?php esc_html_e( 'Clear Log', 'align' ); ?>
				</button>
			</div>
			<div class="align-normalizer-console-body" id="align-normalizer-console-body">
				<div class="align-normalizer-log-entry"><?php esc_html_e( 'Ready to format media filenames.', 'align' ); ?></div>
			</div>
		</div>
	</div>
</div>
