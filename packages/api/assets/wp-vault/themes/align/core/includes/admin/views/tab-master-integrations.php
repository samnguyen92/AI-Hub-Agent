<?php
/**
 * Align Settings - Master Tab: Integrations Hub
 *
 * Serves as container for third-party integrations (Telegram, Webhooks, etc.).
 * Follows Align Settings Style Guide.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="align-master-tab-wrap" id="align-wrap-integrations">
	<div class="align-settings-card">
		<div class="align-card-header">
			<div>
				<h3 class="align-card-title"><?php esc_html_e( 'Third-Party Integrations', 'align' ); ?></h3>
				<p class="align-card-desc"><?php esc_html_e( 'Connect and configure external platforms, messaging bots (Telegram), and notification webhooks.', 'align' ); ?></p>
			</div>
		</div>
		<div class="align-card-body">
			<div style="padding: 36px 20px; text-align: center; color: #64748b;">
				<span class="dashicons dashicons-rest-api" style="font-size: 36px; width: 36px; height: 36px; color: #94a3b8; margin-bottom: 12px; display: inline-block;"></span>
				<h4 style="margin: 0 0 6px 0; font-size: 15px; font-weight: 600; color: #334155;"><?php esc_html_e( 'Integrations Hub', 'align' ); ?></h4>
				<p style="margin: 0 auto; max-width: 480px; font-size: 13px; line-height: 1.5; color: #64748b;">
					<?php esc_html_e( 'Third-party integrations such as Telegram bot notifications and webhooks will be available here.', 'align' ); ?>
				</p>
			</div>
		</div>
	</div>
</div>
