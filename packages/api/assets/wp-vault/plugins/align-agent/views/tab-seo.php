<?php
/**
 * Align Agent Gateway - Tab: SEO Provider Configuration
 *
 * Renders the radio selection card for 'align_agent_seo_provider'
 * (rank_math, yoast, theme_default, others) and displays live environment
 * detection status and non-blocking configuration warnings.
 *
 * @package Align_Agent
 * @subpackage Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_seo_provider = class_exists( 'Align_Agent_Seo_Adapters' ) ? Align_Agent_Seo_Adapters::get_provider() : 'theme_default';
$seo_warnings         = class_exists( 'Align_Agent_Seo_Adapters' ) ? Align_Agent_Seo_Adapters::get_config_warnings( $current_seo_provider ) : array();
$has_rank_math        = defined( 'RANK_MATH_VERSION' );
$has_yoast            = defined( 'WPSEO_VERSION' );
$has_align_seo        = class_exists( 'Align_Seo' );

$active_tool_map = array(
	'rank_math'     => 'save_seo_rank_math',
	'yoast'         => 'save_seo_yoast',
	'theme_default' => 'save_seo_theme_default',
	'others'        => __( 'None (Manual Table Mode)', 'align-agent' ),
);
$active_write_tool = $active_tool_map[ $current_seo_provider ] ?? 'save_seo_theme_default';
?>
<div class="align-agent-card align-settings-card">
	<div class="align-agent-card-header">
		<h2 class="align-agent-card-title"><?php esc_html_e( 'SEO Provider & Meta Routing', 'align-agent' ); ?></h2>
		<p class="align-agent-card-desc">
			<?php esc_html_e( 'Select the authoritative SEO source for this site. Align Agent registers only the single write tool matching your selection so the AI never writes to the wrong plugin meta keys.', 'align-agent' ); ?>
		</p>
	</div>

	<div class="align-agent-card-body">
		<?php if ( ! empty( $seo_warnings ) ) : ?>
			<?php foreach ( $seo_warnings as $warn_text ) : ?>
				<div class="notice notice-warning inline" style="margin: 0 0 16px 0; padding: 10px 14px; border-left: 4px solid #f59e0b; background: #fffbeb; color: #92400e;">
					<p style="margin: 0; font-size: 13px; line-height: 1.5;"><?php echo esc_html( $warn_text ); ?></p>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>

		<!-- Live Environment Detection Summary -->
		<div class="align-meta-grid" style="margin-bottom: 18px;">
			<div>
				<div class="align-meta-item-label"><?php esc_html_e( 'Rank Math SEO', 'align-agent' ); ?></div>
				<div style="font-size: 12.5px; font-weight: 600; color: <?php echo $has_rank_math ? '#15803d' : '#475569'; ?>;">
					<?php echo $has_rank_math ? esc_html( 'Active (v' . RANK_MATH_VERSION . ')' ) : esc_html__( 'Not detected', 'align-agent' ); ?>
				</div>
			</div>
			<div>
				<div class="align-meta-item-label"><?php esc_html_e( 'Yoast SEO', 'align-agent' ); ?></div>
				<div style="font-size: 12.5px; font-weight: 600; color: <?php echo $has_yoast ? '#15803d' : '#475569'; ?>;">
					<?php echo $has_yoast ? esc_html( 'Active (v' . WPSEO_VERSION . ')' ) : esc_html__( 'Not detected', 'align-agent' ); ?>
				</div>
			</div>
			<div>
				<div class="align-meta-item-label"><?php esc_html_e( 'Theme Default (Align_Seo)', 'align-agent' ); ?></div>
				<div style="font-size: 12.5px; font-weight: 600; color: <?php echo $has_align_seo ? '#15803d' : '#475569'; ?>;">
					<?php echo $has_align_seo ? esc_html__( 'Available in Theme Core', 'align-agent' ) : esc_html__( 'Not loaded', 'align-agent' ); ?>
				</div>
			</div>
			<div>
				<div class="align-meta-item-label"><?php esc_html_e( 'Registered AI Write Tool', 'align-agent' ); ?></div>
				<code class="align-repo-badge"><?php echo esc_html( $active_write_tool ); ?></code>
			</div>
		</div>

		<form method="post" action="#tab-seo">
			<?php wp_nonce_field( 'align_agent_save_nonce', 'align_agent_nonce_field' ); ?>

			<table class="form-table align-agent-table">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Active SEO Source', 'align-agent' ); ?></th>
						<td>
							<fieldset style="display: flex; flex-direction: column; gap: 12px;">
								<legend class="screen-reader-text"><span><?php esc_html_e( 'Active SEO Source', 'align-agent' ); ?></span></legend>

								<label style="display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 6px; background: #ffffff; cursor: pointer;">
									<input type="radio" name="align_agent_seo_provider" value="rank_math" <?php checked( $current_seo_provider, 'rank_math' ); ?> style="margin-top: 3px;">
									<span>
										<strong style="display: block; font-size: 13.5px; color: #0f172a;"><?php esc_html_e( 'Rank Math SEO', 'align-agent' ); ?></strong>
										<span style="display: block; font-size: 12.5px; color: #475569; margin-top: 2px;">
											<?php esc_html_e( 'Registers save_seo_rank_math. Writes rank_math_title, rank_math_description, rank_math_focus_keyword, rank_math_robots, and rank_math_facebook_* / rank_math_og_* post meta.', 'align-agent' ); ?>
										</span>
									</span>
								</label>

								<label style="display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 6px; background: #ffffff; cursor: pointer;">
									<input type="radio" name="align_agent_seo_provider" value="yoast" <?php checked( $current_seo_provider, 'yoast' ); ?> style="margin-top: 3px;">
									<span>
										<strong style="display: block; font-size: 13.5px; color: #0f172a;"><?php esc_html_e( 'Yoast SEO', 'align-agent' ); ?></strong>
										<span style="display: block; font-size: 12.5px; color: #475569; margin-top: 2px;">
											<?php esc_html_e( 'Registers save_seo_yoast. Writes _yoast_wpseo_title, _yoast_wpseo_metadesc, _yoast_wpseo_focuskw, _yoast_wpseo_meta-robots-noindex, and _yoast_wpseo_opengraph-* post meta.', 'align-agent' ); ?>
										</span>
									</span>
								</label>

								<label style="display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 6px; background: #ffffff; cursor: pointer;">
									<input type="radio" name="align_agent_seo_provider" value="theme_default" <?php checked( $current_seo_provider, 'theme_default' ); ?> style="margin-top: 3px;">
									<span>
										<strong style="display: block; font-size: 13.5px; color: #0f172a;"><?php esc_html_e( 'Align Theme Default (Align_Seo)', 'align-agent' ); ?></strong>
										<span style="display: block; font-size: 12.5px; color: #475569; margin-top: 2px;">
											<?php esc_html_e( 'Registers save_seo_theme_default. Writes _align_seo_* post meta and prints title, description, OpenGraph, and Twitter Card tags directly in <head> when Rank Math and Yoast are inactive.', 'align-agent' ); ?>
										</span>
									</span>
								</label>

								<label style="display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 6px; background: #ffffff; cursor: pointer;">
									<input type="radio" name="align_agent_seo_provider" value="others" <?php checked( $current_seo_provider, 'others' ); ?> style="margin-top: 3px;">
									<span>
										<strong style="display: block; font-size: 13.5px; color: #0f172a;"><?php esc_html_e( 'Other SEO Plugin (Manual Table)', 'align-agent' ); ?></strong>
										<span style="display: block; font-size: 12.5px; color: #475569; margin-top: 2px;">
											<?php esc_html_e( 'Disables all SEO write tools. Align Agent outputs a formatted 4-row Markdown table (Title, Description, Focus keyword, Social image 1200x630 URL) for you to paste into your custom SEO plugin.', 'align-agent' ); ?>
										</span>
									</span>
								</label>
							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="align-agent-actions">
				<input type="submit" name="align_agent_save_settings" class="button button-primary align-agent-btn-primary" value="<?php esc_attr_e( 'Save SEO Settings', 'align-agent' ); ?>">
			</div>
		</form>
	</div>
</div>
