<?php
/**
 * Tab 3: Minify Output View
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<table class="form-table">
	<tr>
		<th scope="row"><?php esc_html_e( 'HTML Output Minification', 'align' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="align_enable_html_minify" value="1" <?php checked( 1, get_option( 'align_enable_html_minify', 0 ) ); ?>>
				<?php esc_html_e( 'Enable HTML output minification on frontend.', 'align' ); ?>
			</label>
		</td>
	</tr>
</table>

