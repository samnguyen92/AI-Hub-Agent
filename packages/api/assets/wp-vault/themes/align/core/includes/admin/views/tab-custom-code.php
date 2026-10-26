<?php
/**
 * Tab 4: Custom Code View (Header CSS, JS, Tracking Code, Body Open, Footer HTML)
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<table class="form-table align-custom-code-table">
	<tr>
		<th scope="row"><?php esc_html_e( 'Skip for Administrators', 'align' ); ?></th>
		<td>
			<label for="align_custom_code_skip_admins">
				<input type="checkbox" name="align_custom_code_skip_admins" id="align_custom_code_skip_admins" value="1" <?php checked( 1, (int) get_option( 'align_custom_code_skip_admins', 0 ) ); ?> />
				<?php esc_html_e( 'Do not output tracking tags, body open, and footer HTML code for logged-in administrators.', 'align' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Prevents analytics and marketing tags from skewing stats while managing the website. Header CSS and Custom JS are still executed to maintain layout fidelity.', 'align' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="align_custom_css"><?php esc_html_e( 'Header CSS', 'align' ); ?></label></th>
		<td>
			<textarea name="align_custom_css" id="align_custom_css" rows="4" class="large-text code align-code-textarea" placeholder="/* Add CSS rules here, with or without &lt;style&gt; tag */"><?php echo esc_textarea( get_option( 'align_custom_css', '' ) ); ?></textarea>
			<p class="description"><?php echo wp_kses( __( 'Custom CSS styles (Accepts pure CSS or with <code>&lt;style&gt;</code> tag). Rendered in <code>&lt;head&gt;</code>.', 'align' ), array( 'code' => array() ) ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="align_custom_js"><?php esc_html_e( 'JS', 'align' ); ?></label></th>
		<td>
			<textarea name="align_custom_js" id="align_custom_js" rows="4" class="large-text code align-code-textarea" placeholder="// Add JS code here, with or without &lt;script&gt; tag"><?php echo esc_textarea( get_option( 'align_custom_js', '' ) ); ?></textarea>
			<p class="description"><?php echo wp_kses( __( 'Custom JavaScript (Accepts pure JS or with <code>&lt;script&gt;</code> tag / external scripts). Executed before <code>&lt;/body&gt;</code>.', 'align' ), array( 'code' => array() ) ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="align_tracking_code"><?php esc_html_e( 'Tracking Code', 'align' ); ?></label></th>
		<td>
			<textarea name="align_tracking_code" id="align_tracking_code" rows="4" class="large-text code align-code-textarea" placeholder="<!-- Google Analytics, Meta Pixel, etc. -->"><?php echo esc_textarea( get_option( 'align_tracking_code', '' ) ); ?></textarea>
			<p class="description"><?php esc_html_e( 'HTML/JS tracking snippets (e.g. Google Analytics, GTM, Meta Pixel) inserted into <head>.', 'align' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="align_body_open_code"><?php esc_html_e( 'Body Open Code', 'align' ); ?></label></th>
		<td>
			<textarea name="align_body_open_code" id="align_body_open_code" rows="4" class="large-text code align-code-textarea" placeholder="<!-- GTM (noscript), body tracking, etc. -->"><?php echo esc_textarea( get_option( 'align_body_open_code', '' ) ); ?></textarea>
			<p class="description"><?php esc_html_e( 'HTML/JS code inserted right after opening <body> tag (e.g. Google Tag Manager noscript fallback).', 'align' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="align_footer_html_code"><?php esc_html_e( 'Footer HTML', 'align' ); ?></label></th>
		<td>
			<textarea name="align_footer_html_code" id="align_footer_html_code" rows="4" class="large-text code align-code-textarea" placeholder="<!-- Live chat widgets, external scripts, modal HTML -->"><?php echo esc_textarea( get_option( 'align_footer_html_code', '' ) ); ?></textarea>
			<p class="description"><?php esc_html_e( 'HTML/JS code inserted right before closing </body> tag (e.g. chat widgets, third-party embeds, external scripts).', 'align' ); ?></p>
		</td>
	</tr>
</table>
