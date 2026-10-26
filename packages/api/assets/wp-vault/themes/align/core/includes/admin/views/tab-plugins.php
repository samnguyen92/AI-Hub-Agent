<?php
/**
 * Tab 7: Plugins & Enhancements View
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lenis_enabled = (int) get_option( 'align_enable_lenis_scroll', 0 ) === 1;
$splide_enabled = (int) get_option( 'align_enable_splidejs', 0 ) === 1;
$delay_enabled  = (int) get_option( 'align_enable_delay_assets', 0 ) === 1;
?>
<style>
.align-plugins-wrap .form-table th {
	width: 220px;
	vertical-align: top;
	padding: 20px 10px 20px 0;
}
.align-plugins-wrap .form-table td {
	padding: 16px 10px 24px 0;
}
.align-plugin-details {
	margin-top: 10px;
	border: 1px solid #dcdcde;
	border-radius: 6px;
	background: #ffffff;
	overflow: hidden;
	transition: all 0.2s ease;
}
.align-plugin-details[open] {
	border-color: #cbd5e1;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}
.align-plugin-summary {
	display: flex;
	align-items: center;
	gap: 6px;
	padding: 8px 14px;
	background: #f8fafc;
	color: #334155;
	font-size: 13px;
	font-weight: 500;
	cursor: pointer;
	user-select: none;
	outline: none;
	transition: background 0.15s ease, color 0.15s ease;
}
.align-plugin-summary:hover {
	background: #f1f5f9;
	color: #0f172a;
}
.align-plugin-summary::-webkit-details-marker {
	display: none;
}
.align-plugin-summary .dashicons-arrow-right-alt2 {
	transition: transform 0.2s ease;
	font-size: 16px;
	width: 16px;
	height: 16px;
	color: #64748b;
}
.align-plugin-details[open] .align-plugin-summary .dashicons-arrow-right-alt2 {
	transform: rotate(90deg);
}
.align-plugin-body {
	padding: 14px 16px;
	border-top: 1px solid #e2e8f0;
	font-size: 13px;
	color: #334155;
	background: #ffffff;
}
.align-plugin-body pre {
	background: #1e293b;
	color: #f8fafc;
	padding: 10px 12px;
	border-radius: 4px;
	font-size: 12px;
	overflow-x: auto;
	margin: 6px 0 0 0;
	line-height: 1.5;
}
.align-conditional-box {
	animation: alignFadeIn 0.2s ease-in-out;
}
@keyframes alignFadeIn {
	from { opacity: 0; transform: translateY(-4px); }
	to { opacity: 1; transform: translateY(0); }
}
</style>

<div class="align-plugins-wrap" style="max-width: 1000px;">
	<table class="form-table">
		<!-- 1. Smooth Scroll (Lenis) -->
		<tr>
			<th scope="row">
				<strong><?php esc_html_e( 'Smooth Scroll (Lenis)', 'align' ); ?></strong>
			</th>
			<td>
				<label style="font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; margin-bottom: 4px;">
					<input type="checkbox" name="align_enable_lenis_scroll" value="1" <?php checked( true, $lenis_enabled ); ?>>
					<?php esc_html_e( 'Enable Lenis Smooth Scroll', 'align' ); ?>
				</label>
				<p class="description" style="margin: 0 0 6px 0;">
					<?php esc_html_e( 'Enqueues Lenis v1.1.18 smooth-scroll library and initializes it across the website with standard inertia scrolling.', 'align' ); ?>
				</p>

				<!-- Developer Integration Accordion (Visible only when enabled) -->
				<div id="align-lenis-options" class="align-conditional-box" style="<?php echo $lenis_enabled ? '' : 'display: none;'; ?>">
					<details class="align-plugin-details">
						<summary class="align-plugin-summary">
							<span class="dashicons dashicons-arrow-right-alt2"></span>
							<span><?php esc_html_e( 'Developer Integration & API Guide (window.lenis)', 'align' ); ?></span>
						</summary>
						<div class="align-plugin-body" style="border-left: 3px solid #3b82f6;">
							<ul style="margin: 0; padding-left: 18px; line-height: 1.6;">
								<li><strong><?php esc_html_e( 'Access Instance', 'align' ); ?>:</strong> <?php esc_html_e( 'The initialized Lenis instance is available globally via', 'align' ); ?> <code>window.lenis</code>.</li>
								<li><strong><?php esc_html_e( 'Scroll to Target', 'align' ); ?>:</strong> <code>window.lenis.scrollTo('#section-id', { offset: 0, duration: 1.2 });</code></li>
								<li><strong><?php esc_html_e( 'Stop / Resume Scroll (Modals/Popups)', 'align' ); ?>:</strong> <code>window.lenis.stop();</code> / <code>window.lenis.start();</code></li>
								<li><strong><?php esc_html_e( 'Prevent Scroll Hijacking', 'align' ); ?>:</strong> <?php esc_html_e( 'Add attribute', 'align' ); ?> <code>data-lenis-prevent</code> <?php esc_html_e( 'to any scrollable modal, dropdown or inner container so Lenis will not capture its internal scrolling.', 'align' ); ?></li>
								<li><strong><?php esc_html_e( 'GSAP ScrollTrigger Sync Example', 'align' ); ?>:</strong>
									<pre>// Sync Lenis with GSAP ScrollTrigger
if (window.lenis && typeof ScrollTrigger !== 'undefined') {
    window.lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add((time) => { window.lenis.raf(time * 1000); });
    gsap.ticker.lagSmoothing(0);
}</pre>
								</li>
							</ul>
						</div>
					</details>
				</div>
			</td>
		</tr>

		<!-- 2. Splide.js Slider Library -->
		<tr>
			<th scope="row">
				<strong><?php esc_html_e( 'Splide.js Slider', 'align' ); ?></strong>
			</th>
			<td>
				<label style="font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; margin-bottom: 4px;">
					<input type="checkbox" name="align_enable_splidejs" value="1" <?php checked( true, $splide_enabled ); ?>>
					<?php esc_html_e( 'Enable Splide.js & Auto-scroll Extension', 'align' ); ?>
				</label>
				<p class="description" style="margin: 0 0 6px 0;">
					<?php esc_html_e( 'Globally enqueues Splide.js v4.1.4 (CSS & JS) and the Auto-scroll extension so you can initialize sliders/carousels anywhere on the frontend.', 'align' ); ?>
				</p>

				<!-- Splide Quick Example Accordion (Visible only when enabled) -->
				<div id="align-splide-options" class="align-conditional-box" style="<?php echo $splide_enabled ? '' : 'display: none;'; ?>">
					<details class="align-plugin-details">
						<summary class="align-plugin-summary">
							<span class="dashicons dashicons-arrow-right-alt2"></span>
							<span><?php esc_html_e( 'Quick Usage & Initialization Example', 'align' ); ?></span>
						</summary>
						<div class="align-plugin-body" style="border-left: 3px solid #10b981;">
							<p style="margin: 0 0 6px 0;"><?php esc_html_e( 'Initialize a slider on any container element when DOM is ready:', 'align' ); ?></p>
							<pre>// Initialize a slider when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Splide !== 'undefined') {
        new Splide('.splide', {
            type    : 'loop',
            perPage : 3,
            gap     : '1rem',
            autoplay: true
        }).mount(window.splide ? window.splide.Extensions : {});
    }
});</pre>
						</div>
					</details>
				</div>
			</td>
		</tr>

		<!-- 3. Delay JS & CSS Optimization -->
		<tr>
			<th scope="row">
				<strong><?php esc_html_e( 'Delay JS & CSS', 'align' ); ?></strong>
			</th>
			<td>
				<label style="font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; margin-bottom: 4px;">
					<input type="checkbox" name="align_enable_delay_assets" value="1" <?php checked( true, $delay_enabled ); ?>>
					<?php esc_html_e( 'Enable Delay JS & CSS Loading', 'align' ); ?>
				</label>
				<p class="description" style="margin: 0 0 6px 0;">
					<?php esc_html_e( 'Delays loading and execution of non-critical JavaScript and CSS assets until user interaction (scroll, touch, click, mousemove) or a 4s fallback timeout to maximize PageSpeed / Core Web Vitals scores.', 'align' ); ?>
				</p>

				<!-- Delay Sub-Options (Visible only when Delay is enabled) -->
				<div id="align-delay-options" class="align-conditional-box" style="<?php echo $delay_enabled ? '' : 'display: none;'; ?> margin-top: 14px;">
					<!-- Delay Custom URLs Textarea -->
					<div class="align-delay-custom-urls" style="margin-bottom: 12px;">
						<label for="align_delay_custom_urls" style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px; color: #1e293b;">
							<?php esc_html_e( 'Delay Custom URLs & Plugin Assets (JS & CSS):', 'align' ); ?>
						</label>
						<textarea
							name="align_delay_custom_urls"
							id="align_delay_custom_urls"
							rows="4"
							class="large-text code"
							style="height: 95px; font-family: monospace; font-size: 12px; line-height: 1.4; resize: vertical;"
							placeholder="plugins/contact-form-7/&#10;plugins/woocommerce/assets/js/&#10;https://example.com/external-script.js&#10;my-custom-style.css"
						><?php echo esc_textarea( get_option( 'align_delay_custom_urls', '' ) ); ?></textarea>
						<p class="description" style="margin-top: 4px; font-size: 12px;">
							<?php esc_html_e( 'Enter URLs, folder paths, or keyword filenames (one per line) for third-party scripts or stylesheets you want to delay. The engine will match and convert them to data-delay="true".', 'align' ); ?>
						</p>
					</div>

					<!-- Delay Optimization Accordion -->
					<details class="align-plugin-details">
						<summary class="align-plugin-summary">
							<span class="dashicons dashicons-arrow-right-alt2"></span>
							<span><?php esc_html_e( 'Role Bypass & Manual Trigger Details', 'align' ); ?></span>
						</summary>
						<div class="align-plugin-body" style="border-left: 3px solid #f59e0b;">
							<p style="margin: 0 0 8px 0;">
								<strong><?php esc_html_e( 'Auto-Bypass for Non-Subscriber Roles:', 'align' ); ?></strong>
								<?php esc_html_e( 'Delay optimization is applied to Guests and Subscribers. It is automatically bypassed for logged-in Administrators, Editors, Authors, and Contributors to guarantee an uninhibited editing and visual preview experience.', 'align' ); ?>
							</p>
							<p style="margin: 0;">
								<strong><?php esc_html_e( 'Manual Trigger Helper:', 'align' ); ?></strong>
								<?php esc_html_e( 'If your custom script needs to trigger asset loading immediately:', 'align' ); ?> <code>window._alignTriggerAssetsLoad && window._alignTriggerAssetsLoad();</code>
							</p>
						</div>
					</details>
				</div>
			</td>
		</tr>
	</table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	function bindPluginToggle(checkboxName, targetId) {
		var cb = document.querySelector('input[name="' + checkboxName + '"]');
		var target = document.getElementById(targetId);
		if (!cb || !target) return;
		cb.addEventListener('change', function() {
			target.style.display = this.checked ? 'block' : 'none';
		});
	}

	bindPluginToggle('align_enable_lenis_scroll', 'align-lenis-options');
	bindPluginToggle('align_enable_splidejs', 'align-splide-options');
	bindPluginToggle('align_enable_delay_assets', 'align-delay-options');
});
</script>
