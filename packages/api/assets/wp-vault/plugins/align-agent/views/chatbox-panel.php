<?php
/**
 * Align Agent - Chatbox Slide-in Drawer View
 *
 * Renders the Floating Action Button (FAB), blur backdrop, and full 100vh slide-in drawer
 * inside the WordPress Admin via admin_footer.
 *
 * @package Align_Agent
 * @subpackage Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$align_builder_active = class_exists( 'Align_Builder_Crud_Handler' );
?>
<button type="button" id="align-chatbox-fab" class="align-chatbox-fab" data-action="toggle-chatbox" data-step="open-chatbox" aria-label="<?php esc_attr_e( 'Open Align Agent Assistant', 'align-agent' ); ?>" aria-controls="align-agent-chatbox" aria-expanded="false">
	<span class="dashicons dashicons-format-chat" aria-hidden="true"></span>
</button>

<div id="align-chatbox-backdrop" class="align-chatbox-backdrop" data-action="close-chatbox" aria-hidden="true" hidden></div>

<aside id="align-agent-chatbox" class="align-agent-chatbox" role="dialog" aria-modal="true" aria-labelledby="align-chatbox-title" aria-hidden="true">
	<header class="align-chatbox-header">
		<div class="align-chatbox-header-titles">
			<h2 id="align-chatbox-title" class="align-chatbox-title"><?php esc_html_e( 'Align Agent Assistant', 'align-agent' ); ?></h2>
			<span class="align-chatbox-subtitle"><?php esc_html_e( 'CMS Page Management', 'align-agent' ); ?></span>
		</div>
		<div class="align-chatbox-header-actions">
			<button type="button" class="button button-danger" id="align-chatbox-clear-btn" data-action="clear-session" title="<?php esc_attr_e( 'Clear session and start a new chat', 'align-agent' ); ?>">
				<span class="dashicons dashicons-trash" aria-hidden="true"></span>
				<span><?php esc_html_e( 'Clear', 'align-agent' ); ?></span>
			</button>
			<button type="button" class="button align-chatbox-icon-btn align-chatbox-close-btn" id="align-chatbox-close-btn" data-action="close-chatbox" title="<?php esc_attr_e( 'Close Align Agent Assistant', 'align-agent' ); ?>" aria-label="<?php esc_attr_e( 'Close Align Agent Assistant', 'align-agent' ); ?>">
				<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
			</button>
		</div>
	</header>

	<div class="align-chatbox-dropzone" id="align-chatbox-dropzone" aria-hidden="true">
		<span class="dashicons dashicons-upload" aria-hidden="true"></span>
		<p><?php esc_html_e( 'Drop images to attach', 'align-agent' ); ?></p>
	</div>

	<?php if ( ! $align_builder_active ) : ?>
		<div class="align-chatbox-warning" role="status">
			<?php esc_html_e( 'Align Theme Builder is not active. Chatbox is operating in read-only inspection mode (write tools are disabled).', 'align-agent' ); ?>
		</div>
	<?php endif; ?>

	<div id="align-chatbox-messages" class="align-chatbox-messages" role="log" aria-live="polite" aria-relevant="additions">
		<div class="align-chatbox-empty" id="align-chatbox-empty">
			<p><?php esc_html_e( 'Ask Align Agent to inspect, draft, or edit pages.', 'align-agent' ); ?></p>
		</div>
	</div>

	<form id="align-chatbox-form" class="align-chatbox-footer" data-action="chat-form">
		<div id="align-chatbox-attachments" class="align-chatbox-attachments" style="display: none;"></div>
		<input type="file" id="align-chatbox-file-input" accept="image/jpeg,image/png,image/webp,image/gif" multiple style="display: none;">
		<label for="align-chatbox-input" class="screen-reader-text"><?php esc_html_e( 'Message Align Agent', 'align-agent' ); ?></label>
		<textarea
			id="align-chatbox-input"
			class="align-chatbox-textarea"
			rows="2"
			placeholder="<?php esc_attr_e( 'Ask Align Agent... (Enter to send)', 'align-agent' ); ?>"
		></textarea>
		<div class="align-chatbox-actions">
			<button type="button" class="button button-secondary align-chatbox-icon-btn" id="align-chatbox-upload-btn" data-action="upload-media" title="<?php esc_attr_e( 'Attach Image', 'align-agent' ); ?>" aria-label="<?php esc_attr_e( 'Attach Image', 'align-agent' ); ?>">
				<span class="dashicons dashicons-format-image" aria-hidden="true"></span>
			</button>
			<button type="submit" class="button button-primary" id="align-chatbox-send-btn" data-action="send-chat">
				<span><?php esc_html_e( 'Send', 'align-agent' ); ?></span>
			</button>
		</div>
	</form>
</aside>
