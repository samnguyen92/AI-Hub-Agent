<?php
/**
 * View template for Tab 8: Import HTML ZIP in Align Admin Settings
 *
 * @package Align
 */

if ( ! defined( "ABSPATH" ) ) {
	exit;
}

$ai_zip_cfg   = class_exists( 'Align_AI_Connection' ) ? Align_AI_Connection::get_config() : array();
$ai_zip_key   = $ai_zip_cfg['api_key'] ?? get_option( 'align_ai_api_key', get_option( 'align_openrouter_api_key', '' ) );
$ai_zip_model = $ai_zip_cfg['model'] ?? get_option( 'align_ai_model', get_option( 'align_openrouter_model', 'google/gemini-2.5-flash' ) );
$ai_zip_prov  = $ai_zip_cfg['provider'] ?? 'openrouter';
?>
<div class="align-settings-card">
	<div class="align-card-header">
		<div>
			<h3 class="align-card-title"><?php esc_html_e( "HTML Project ZIP Importer", "align" ); ?></h3>
			<p class="align-card-desc"><?php esc_html_e( "Automatically detect project structure, convert images to WebP via Cloud API, isolate scoped CSS/JS, and generate clean Gutenberg Custom Block Pages.", "align" ); ?></p>
		</div>
		<div>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( "admin-ajax.php?action=align_download_sample_template_zip" ), "align_settings_nonce", "nonce" ) ); ?>" class="button button-secondary" style="font-weight: 600;" download="align-sample-template.zip">
				<?php esc_html_e( "Download Sample Template (.zip)", "align" ); ?>
			</a>
		</div>
	</div>

	<div class="align-card-body">
		<!-- Permission Health Status Banner -->
		<div id="align-perm-banner" style="display: none; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; font-size: 13px; align-items: center; justify-content: space-between; border: 1px solid transparent;">
			<div style="display: flex; align-items: center; gap: 8px;">
				<span id="align-perm-icon" class="dashicons" style="font-size: 18px; width: 18px; height: 18px;"></span>
				<span id="align-perm-msg" style="font-weight: 500;"></span>
			</div>
			<button type="button" id="align-perm-fix-btn" class="button button-small" style="display: none; font-weight: 600;">
				<?php esc_html_e( "View Fix Commands", "align" ); ?>
			</button>
		</div>

		<!-- Folder Structure Tip Box Accordion -->
		<div id="align-zip-accordion" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 18px; overflow: hidden;">
			<div id="align-zip-accordion-toggle" style="padding: 10px 16px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; user-select: none; background: #f8fafc;">
				<span style="font-weight: 600; color: #334155; font-size: 13px;"><?php esc_html_e( "Recommended ZIP Folder Hierarchy", "align" ); ?></span>
				<div style="display: flex; align-items: center; gap: 4px; color: #0284c7; font-size: 12px; font-weight: 600;">
					<span id="align-zip-accordion-label"><?php esc_html_e( "View Guide", "align" ); ?></span>
					<span id="align-zip-accordion-icon" class="dashicons dashicons-arrow-right-alt2" style="font-size: 14px; width: 14px; height: 14px;"></span>
				</div>
			</div>
			<div id="align-zip-accordion-body" style="display: none; padding: 14px 16px; border-top: 1px solid #e2e8f0; background: #ffffff;">
				<p style="margin: 0 0 10px 0; font-size: 12.5px; color: #64748b;">
					<?php esc_html_e( "Standard directory boilerplate for rapid development & zero-friction import:", "align" ); ?>
				</p>
				<pre style="margin: 0; font-family: monospace; font-size: 12px; line-height: 1.6; color: #334155; background: #f8fafc; padding: 12px 14px; border-radius: 4px; border: 1px solid #e2e8f0; overflow-x: auto;">
my-project.zip
├── assets/             (Shared CSS, JS, fonts, logo)
├── header/ & footer/   (Global parts: parts/*.html)
├── reuse-blocks/       (Reusable patterns: [reuse-block id="..."])
├── index.html          (Page "Home")
├── about.html          (Page "About")
├── services/
│   ├── index.html      (Page "Services" - Parent)
│   ├── web-design/
│   │   └── index.html  (Page "Web Design" - Child of Services)
│   └── seo.html        (Page "SEO" - Child of Services)
├── blog/               (Posts: *.html → post, index.html → page "blog")
│   ├── index.html      (Page "Blog" - Static Archive)
│   ├── hello-world.html(Post "Hello World" - Align Builder)
│   └── second-post/
│       └── index.html  (Post "Second Post" - Align Builder)
├── vi/                 (Language folder: mirrors root structure → /vi/...)
│   ├── index.html      (Root Page "vi" content)
│   ├── services/       (Child pages under /vi/services/...)
│   ├── blog/           (Posts under /vi/<slug>/)
│   └── header/         (Localized template part: header-vi)
└── contact/
    └── index.html      (Page "Contact")</pre>
			</div>
		</div>

		<!-- Drag & Drop Upload Zone -->
		<div id="align-zip-dropzone" style="border: 2px dashed #cbd5e1; border-radius: 8px; padding: 32px 20px; text-align: center; background: #f8fafc; cursor: pointer; transition: all 0.2s ease; margin-bottom: 18px;">
			<input type="file" id="align-zip-file-input" accept=".zip" style="display: none;">
			<div style="font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 4px;">
				<?php esc_html_e( "Drag and drop your project .zip here, or click to browse", "align" ); ?>
			</div>
			<p style="margin: 0; color: #64748b; font-size: 12.5px;"><?php esc_html_e( "Supports project zip archives up to 100MB", "align" ); ?></p>
			<div id="align-zip-selected-info" style="display: none; margin-top: 12px; font-weight: 600; font-size: 13px; color: #0284c7; background: #e0f2fe; padding: 6px 14px; border-radius: 20px; align-items: center; gap: 6px; display: inline-flex;">
				<span id="align-zip-filename"></span>
			</div>
		</div>

		<!-- Options Box -->
		<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px 18px; margin-bottom: 18px;">
			<div style="display: flex; flex-direction: column; gap: 10px;">
				<label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e293b; cursor: pointer;">
					<input type="checkbox" id="align-import-opt-ai-refactor" value="1">
					<span style="color: #7c3aed; font-weight: 700;"><?php esc_html_e( 'AI Semantic Refactor', 'align' ); ?></span> 
					<span style="color: #64748b;">— <?php esc_html_e( 'Auto-detect Header, Footer & Reusable Blocks with Data-Overwrite (2-Stage Pipeline)', 'align' ); ?></span>
				</label>

				<!-- AI Provider Connection Status Box -->
				<div id="align-ai-provider-settings" style="display: none; margin: 2px 0 4px 24px; padding: 10px 14px; background: #faf5ff; border: 1px solid #e9d5ff; border-radius: 6px;">
					<input type="hidden" id="align-ai-openrouter-key" value="<?php echo esc_attr( $ai_zip_key ); ?>">
					<input type="hidden" id="align-ai-openrouter-model" value="<?php echo esc_attr( $ai_zip_model ); ?>">
					<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
						<div>
							<span style="font-size: 12.5px; font-weight: 600; color: #6b21a8;">
								<?php
								if ( ! empty( $ai_zip_key ) ) {
									printf( esc_html__( 'Connected to %s (%s)', 'align' ), esc_html( ucfirst( $ai_zip_prov ) ), esc_html( $ai_zip_model ) );
								} else {
									esc_html_e( 'AI Key Not Configured yet', 'align' );
								}
								?>
							</span>
						</div>
						<a href="#connect-api" class="align-switch-tab-link" data-target-tab="connect-api" style="font-size: 12px; font-weight: 600; color: #7c3aed; text-decoration: underline;">
							<?php esc_html_e( 'Configure API & AI in Connect API tab →', 'align' ); ?>
						</a>
					</div>
				</div>

				<label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e293b; cursor: pointer;">
					<input type="checkbox" id="align-import-opt-overwrite" checked value="1">
					<span><strong><?php esc_html_e( 'Fresh Re-create', 'align' ); ?></strong> <span style="color: #64748b;">— <?php esc_html_e( 'Purge existing pages & media attachments', 'align' ); ?></span></span>
				</label>
				<label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e293b; cursor: pointer;">
					<input type="checkbox" id="align-import-opt-webp" checked value="1">
					<span><strong><?php esc_html_e( 'Convert WebP Cloud', 'align' ); ?></strong> <span style="color: #64748b;">— <?php esc_html_e( 'Auto WebP conversion with fallback', 'align' ); ?></span></span>
				</label>
				<label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1e293b; cursor: pointer;">
					<input type="checkbox" id="align-import-opt-minify" checked value="1">
					<span><strong><?php esc_html_e( 'Minify Payloads', 'align' ); ?></strong> <span style="color: #64748b;">— <?php esc_html_e( 'Compress HTML, CSS, JS payloads', 'align' ); ?></span></span>
				</label>
			</div>
		</div>

		<!-- Action Toolbar -->
		<div style="margin-bottom: 18px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
			<div style="display: flex; align-items: center; gap: 10px;">
				<button type="button" id="align-start-import-btn" class="button button-primary" disabled>
					<?php esc_html_e( "Start Import HTML ZIP", "align" ); ?>
				</button>
				<span id="align-import-spinner" class="spinner" style="float: none; margin: 0;"></span>
			</div>
			<button type="button" id="align-quick-reset-btn" class="button button-danger">
				<?php esc_html_e( "Reset Site Data", "align" ); ?>
			</button>
		</div>

		<!-- Live Progress Card -->
		<div id="align-import-progress-wrap" style="display: none; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px 20px; margin-bottom: 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
			<!-- Header & Stats -->
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
				<div>
					<div id="align-progress-stage-text" style="font-weight: 700; font-size: 14px; color: #0f172a;">Preparing project archive...</div>
					<div id="align-progress-detail-text" style="font-size: 12px; color: #64748b; margin-top: 2px;">Initializing session and reading archive catalog...</div>
				</div>
				<div style="display: flex; align-items: center; gap: 12px;">
					<div id="align-progress-timer" style="font-size: 12px; font-weight: 600; color: #64748b; background: #f1f5f9; padding: 4px 10px; border-radius: 12px;">00:00</div>
					<div id="align-progress-pct-badge" style="font-weight: 800; font-size: 15px; color: #0284c7; background: #e0f2fe; padding: 3px 10px; border-radius: 12px;">0%</div>
				</div>
			</div>

			<!-- Step Flow Pills (Clean, numbered, no emojis) -->
			<div id="align-import-steps-bar" style="display: flex; align-items: center; gap: 6px; margin-bottom: 14px; overflow-x: auto; padding-bottom: 4px;">
				<div class="align-step-pill" id="align-step-pill-scan">
					<span class="align-step-name">1. Scan ZIP</span>
				</div>
				<div class="align-step-arrow">›</div>
				<div class="align-step-pill" id="align-step-pill-ai" style="display: none;">
					<span class="align-step-name">2. AI Refactor</span>
				</div>
				<div class="align-step-arrow" id="align-step-arrow-ai" style="display: none;">›</div>
				<div class="align-step-pill" id="align-step-pill-reset">
					<span class="align-step-name">3. Reset Site</span>
				</div>
				<div class="align-step-arrow">›</div>
				<div class="align-step-pill" id="align-step-pill-media">
					<span class="align-step-name">4. Media <span id="align-pill-media-count" style="font-weight: 700; opacity: 0.85;">(0/0)</span></span>
				</div>
				<div class="align-step-arrow">›</div>
				<div class="align-step-pill" id="align-step-pill-parts">
					<span class="align-step-name">5. Parts & Blocks</span>
				</div>
				<div class="align-step-arrow">›</div>
				<div class="align-step-pill" id="align-step-pill-pages">
					<span class="align-step-name">6. Pages <span id="align-pill-pages-count" style="font-weight: 700; opacity: 0.85;">(0/0)</span></span>
				</div>
				<div class="align-step-arrow">›</div>
				<div class="align-step-pill" id="align-step-pill-finalize">
					<span class="align-step-name">7. Finalize</span>
				</div>
			</div>

			<!-- Progress Bar Track -->
			<div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; position: relative;">
				<div id="align-progress-bar-fill" style="width: 0%; height: 100%; background: #0284c7; border-radius: 4px; transition: width 0.35s ease;"></div>
			</div>
		</div>

		<!-- Unified Results Section -->
		<div id="align-import-results-container" style="display: none; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; margin-bottom: 20px;">
			<div style="background: #f0fdf4; border-bottom: 1px solid #bbf7d0; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
				<div style="font-weight: 600; font-size: 13px; color: #15803d;">
					<span id="align-import-summary-msg">Import Completed!</span>
				</div>
				<button type="button" id="align-toggle-logs-btn" class="button button-small" style="font-size: 11px; color: #475569; display: inline-flex; align-items: center; gap: 4px;">
					<span>Terminal Logs</span>
					<span id="align-logs-caret" class="dashicons dashicons-arrow-down-alt2" style="font-size: 12px; width: 12px; height: 12px;"></span>
				</button>
			</div>

			<div id="align-import-log-container" style="display: none; background: #0f172a; padding: 12px 16px; color: #94a3b8; font-family: monospace; font-size: 12px; border-bottom: 1px solid #1e293b; max-height: 160px; overflow-y: auto; line-height: 1.6;">
				<div id="align-import-log-content"></div>
			</div>

			<div style="overflow-x: auto;">
				<table class="align-results-table" style="width: 100%; border-collapse: collapse; text-align: left;">
					<thead>
						<tr>
							<th style="width: 35%;"><?php esc_html_e( "Page Name", "align" ); ?></th>
							<th style="width: 25%;"><?php esc_html_e( "Permalink (Slug)", "align" ); ?></th>
							<th style="width: 15%;"><?php esc_html_e( "Post ID", "align" ); ?></th>
							<th style="width: 25%; text-align: right;"><?php esc_html_e( "Actions", "align" ); ?></th>
						</tr>
					</thead>
					<tbody id="align-import-pages-tbody"></tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<style>
	.align-results-table th { background: #f8fafc; color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase; padding: 10px 14px; border-bottom: 1px solid #e2e8f0; }
	.align-results-table td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; font-size: 13px; color: #1e293b; }
	
	/* Step Pills Styling */
	.align-step-pill { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 6px; font-size: 11.5px; font-weight: 600; color: #64748b; background: #f8fafc; border: 1px solid #e2e8f0; white-space: nowrap; transition: all 0.2s ease; }
	.align-step-pill.is-active { color: #0284c7; background: #f0f9ff; border-color: #38bdf8; font-weight: 700; }
	.align-step-pill.is-done { color: #15803d; background: #f0fdf4; border-color: #86efac; }
	.align-step-arrow { color: #cbd5e1; font-size: 14px; font-weight: bold; }
</style>

<!-- Permission Warning Alert Modal -->
<div id="align-perm-modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); z-index: 100000; align-items: center; justify-content: center;">
	<div style="background: #ffffff; border-radius: 8px; width: 100%; max-width: 520px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3); overflow: hidden; border: 1px solid #fecaca; margin: 20px;">
		<div style="padding: 14px 18px; background: #fef2f2; border-bottom: 1px solid #fee2e2; display: flex; align-items: center; justify-content: space-between;">
			<div style="font-weight: 700; color: #991b1b; font-size: 15px;">
				<?php esc_html_e( "Server Write Permission Required", "align" ); ?>
			</div>
			<button type="button" id="align-perm-modal-close" style="background: none; border: none; font-size: 20px; color: #9ca3af; cursor: pointer; line-height: 1;">&times;</button>
		</div>
		<div style="padding: 18px 20px;">
			<p style="margin: 0 0 10px 0; font-size: 13px; color: #334155; line-height: 1.5;">
				<?php esc_html_e( "The web server does not have write permissions to upload media files or save style assets on hosting:", "align" ); ?>
			</p>
			<div id="align-perm-issues-list" style="margin-bottom: 12px; font-size: 12px; color: #b91c1c; background: #fff1f2; padding: 10px 12px; border-radius: 6px; border: 1px solid #fecdd3; line-height: 1.5;"></div>
			<div style="font-weight: 600; font-size: 12px; color: #475569; margin-bottom: 6px;"><?php esc_html_e( "Grant permissions via SSH / Terminal or Hosting Control Panel:", "align" ); ?></div>
			<pre id="align-perm-commands-box" style="margin: 0 0 16px 0; background: #0f172a; color: #38bdf8; font-family: monospace; font-size: 12px; padding: 12px; border-radius: 6px; overflow-x: auto; line-height: 1.6; user-select: all;"></pre>
			<div style="display: flex; justify-content: flex-end; gap: 10px; align-items: center;">
				<button type="button" id="align-perm-modal-cancel" class="button" style="height: 34px; padding: 0 14px;"><?php esc_html_e( "Close", "align" ); ?></button>
				<button type="button" id="align-perm-recheck-btn" class="button button-primary" style="height: 34px; padding: 0 14px; font-weight: 600;">
					<?php esc_html_e( "Re-check Permissions", "align" ); ?>
				</button>
			</div>
		</div>
	</div>
</div>

<!-- Factory Reset Confirmation Modal -->
<div id="align-reset-modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); z-index: 100000; align-items: center; justify-content: center;">
	<div style="background: #ffffff; border-radius: 8px; width: 100%; max-width: 480px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden; border: 1px solid #fee2e2; margin: 20px;">
		<div style="padding: 14px 18px; background: #fff5f5; border-bottom: 1px solid #fee2e2; display: flex; align-items: center; justify-content: space-between;">
			<div style="font-weight: 700; color: #b91c1c; font-size: 15px;"><?php esc_html_e( "Factory Reset Site Data", "align" ); ?></div>
			<button type="button" id="align-reset-modal-close" style="background: none; border: none; font-size: 22px; color: #9ca3af; cursor: pointer;">&times;</button>
		</div>
		<div style="padding: 16px 20px;">
			<p style="margin: 0 0 10px 0; font-size: 13px; color: #374151;">Destructive action: Deletes all Pages, Posts, Reusable Blocks, Media attachments & Custom assets.</p>
			<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; margin-bottom: 14px;">
				<label for="align-reset-confirm-input" style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">Type <strong style="color: #dc2626; font-family: monospace;">DELETE</strong> below:</label>
				<input type="text" id="align-reset-confirm-input" placeholder="Type DELETE" style="font-family: monospace; font-size: 14px; padding: 6px 10px; border-radius: 4px; border: 1px solid #cbd5e1; outline: none; width: 100%; box-sizing: border-box;" autocomplete="off" />
			</div>
			<div style="display: flex; justify-content: flex-end; gap: 8px;">
				<button type="button" id="align-reset-modal-cancel" class="button"><?php esc_html_e( "Cancel", "align" ); ?></button>
				<button type="button" id="align-reset-modal-confirm" class="button button-primary" style="background: #dc2626; border-color: #b91c1c; color: #fff; font-weight: 600;" disabled><span id="align-reset-btn-text"><?php esc_html_e( "Confirm Reset (5s)", "align" ); ?></span></button>
			</div>
		</div>
	</div>
</div>

<script>
<?php readfile( __DIR__ . '/tab-import-zip.js' ); ?>
</script>
