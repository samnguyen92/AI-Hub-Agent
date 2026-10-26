<?php
/**
 * Align Settings - Rank Math Redirection Bulk Importer View
 *
 * Implements clean, transparent button UI for bulk importing 301/302 redirects
 * into Rank Math SEO with live dry-run validation preview and loop detection.
 * Follows Align Settings Style Guide.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ensure importer class is loaded.
if ( ! class_exists( 'Align_Redirection_Importer' ) ) {
	$importer_file = dirname( __DIR__ ) . '/class-align-redirection-importer.php';
	if ( file_exists( $importer_file ) ) {
		require_once $importer_file;
	}
}

$is_rm_active = class_exists( 'Align_Redirection_Importer' ) && Align_Redirection_Importer::is_rank_math_active();
$counts       = array();

if ( $is_rm_active ) {
	try {
		$counts = \RankMath\Redirections\DB::get_counts();
		if ( ! is_array( $counts ) ) {
			$counts = array();
		}
	} catch ( \Throwable $e ) {
		$is_rm_active = false;
		$counts       = array();
		error_log( '[Align Redirection] Rank Math DB::get_counts() failed: ' . $e->getMessage() );
	}
}

$active_count = intval( $counts['active'] ?? 0 );
$nonce        = wp_create_nonce( 'align_settings_nonce' );
$export_nonce = wp_create_nonce( 'align_export_redirections_nonce' );
?>

<div class="align-settings-card">
	<div class="align-card-header">
		<div>
			<h3 class="align-card-title"><?php esc_html_e( 'Bulk Redirection Importer (Rank Math SEO)', 'align' ); ?></h3>
			<p class="align-card-desc"><?php esc_html_e( 'Paste a list of URL redirection rules from Excel, Google Sheets, or CSV to import directly into Rank Math.', 'align' ); ?></p>
		</div>
		<div class="align-card-header-actions" style="display:flex; gap:8px; align-items:center;">
			<?php if ( $is_rm_active ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=align_export_redirections&_wpnonce=' . $export_nonce ) ); ?>"
					class="button button-secondary" id="align-redirections-export-btn" download>
					<?php esc_html_e( 'Export CSV Backup', 'align' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<div class="align-card-body">
		<?php if ( ! $is_rm_active ) : ?>
			<div class="align-notice-box align-notice-box--warning" style="padding:12px 16px; background:#fffbeb; border:1px solid #fef3c7; border-radius:6px; color:#b45309; font-size:13px; line-height:1.5;">
				<strong><?php esc_html_e( 'Notice:', 'align' ); ?></strong> <?php esc_html_e( 'The Rank Math Redirections module is currently inactive. Please ensure Rank Math SEO is installed and Redirections is enabled in Rank Math Dashboard.', 'align' ); ?>
			</div>
		<?php else : ?>

			<!-- Format Hint & Options Grid -->
			<div class="align-redirection-grid" style="display:grid; grid-template-columns: 2fr 1fr; gap:20px; margin-bottom:16px;">
				<div>
					<label for="align-redirection-paste" style="display:block; font-size:12.5px; font-weight:600; color:#334155; margin-bottom:6px;">
						<?php esc_html_e( 'Paste Redirection Rules (One per line)', 'align' ); ?> <span class="required" style="color:#ef4444;">*</span>
					</label>
					<textarea id="align-redirection-paste" rows="8" class="large-text code" style="width:100%; font-family:SFMono-Regular,Consolas,monospace; font-size:12px; line-height:1.6; padding:10px 12px; border-radius:6px; border:1px solid #cbd5e1;"
						placeholder="<?php esc_attr_e( "/old-page/            /new-page/            301\n/insurance-broker/    /advisory-services/   301\nhttps://site.com/old/ /insights/            301", 'align' ); ?>"></textarea>
					<p class="description" style="font-size:11.5px; color:#64748b; margin-top:5px;">
						<?php esc_html_e( 'Supported separators: Tab (from Excel/Sheets), Comma (CSV), Semicolon, or Space. Format: [Source URL] [Destination URL] [Optional HTTP Code]. Domain will be automatically stripped.', 'align' ); ?>
					</p>
				</div>

				<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:14px 16px;">
					<h4 style="margin:0 0 10px; font-size:12.5px; font-weight:600; color:#1e293b;"><?php esc_html_e( 'Import Configuration', 'align' ); ?></h4>

					<div style="margin-bottom:12px;">
						<label for="align-redirection-default-code" style="display:block; font-size:11.5px; font-weight:600; color:#475569; margin-bottom:4px;">
							<?php esc_html_e( 'Default HTTP Status Code', 'align' ); ?>
						</label>
						<select id="align-redirection-default-code" style="width:100%; height:30px; font-size:12px; border-radius:5px;">
							<option value="301" selected>301 — Permanent Move</option>
							<option value="302">302 — Temporary Move</option>
							<option value="307">307 — Temporary Redirect</option>
							<option value="410">410 — Content Deleted</option>
							<option value="451">451 — Unavailable Legal</option>
						</select>
					</div>

					<div style="margin-bottom:14px;">
						<label style="display:flex; align-items:flex-start; gap:6px; font-size:12px; color:#334155; cursor:pointer;">
							<input type="checkbox" id="align-redirection-overwrite" value="1" style="margin-top:2px;">
							<span><?php esc_html_e( 'Overwrite existing rules in Rank Math if source URL already exists', 'align' ); ?></span>
						</label>
					</div>

					<div>
						<label style="display:block; font-size:11.5px; font-weight:600; color:#475569; margin-bottom:4px;">
							<?php esc_html_e( 'Or Upload CSV File', 'align' ); ?>
						</label>
						<input type="file" id="align-redirection-file" accept=".csv,text/csv,text/plain" style="font-size:11px; width:100%;">
					</div>
				</div>
			</div>

			<!-- Actions -->
			<div style="display:flex; align-items:center; gap:10px; margin-bottom:16px;">
				<button type="button" class="button button-primary" id="align-redirection-preview-btn">
					<?php esc_html_e( 'Analyze & Preview Rules', 'align' ); ?>
				</button>
				<button type="button" class="button button-secondary" id="align-redirection-clear-btn">
					<?php esc_html_e( 'Clear', 'align' ); ?>
				</button>
				<span id="align-redirection-status" style="font-size:12px; color:#64748b;"></span>
			</div>

			<!-- Dry Run Preview Container (Hidden by default) -->
			<div id="align-redirection-preview-wrap" style="display:none; margin-top:20px; border-top:1px solid #e2e8f0; padding-top:16px;">
				<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
					<div>
						<h4 style="margin:0; font-size:13px; font-weight:600; color:#0f172a;">
							<?php esc_html_e( 'Dry-Run Analysis Preview', 'align' ); ?>
						</h4>
						<p id="align-redirection-summary" style="margin:4px 0 0; font-size:12px; color:#64748b;"></p>
					</div>
					<button type="button" class="button button-primary" id="align-redirection-execute-btn" style="height:32px; padding:0 16px;">
						<?php esc_html_e( 'Import to Rank Math Now', 'align' ); ?>
					</button>
				</div>

				<div style="max-height:360px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:6px;">
					<table class="wp-list-table widefat fixed striped" id="align-redirection-preview-table" style="border:none;">
						<thead>
							<tr>
								<th style="width:45px;">#</th>
								<th style="width:36%;"><?php esc_html_e( 'Source Pattern', 'align' ); ?></th>
								<th style="width:36%;"><?php esc_html_e( 'Destination URL', 'align' ); ?></th>
								<th style="width:65px;"><?php esc_html_e( 'Code', 'align' ); ?></th>
								<th><?php esc_html_e( 'Status & Notes', 'align' ); ?></th>
							</tr>
						</thead>
						<tbody id="align-redirection-preview-tbody">
							<!-- Populated via JavaScript -->
						</tbody>
					</table>
				</div>
			</div>

		<?php endif; ?>
	</div>
</div>

<!-- Active Rank Math Redirections List Accordion (Default closed) -->
<?php if ( $is_rm_active ) : ?>
<div class="align-settings-card is-accordion" id="align-active-redirections-card" style="margin-top:16px;">
	<div class="align-card-header is-accordion" id="align-active-redirections-header" style="cursor:pointer; display:flex; align-items:center; justify-content:space-between;">
		<div>
			<h3 class="align-card-title"><?php esc_html_e( 'Active Rank Math Redirections', 'align' ); ?> (<?php echo esc_html( $active_count ); ?>)</h3>
			<p class="align-card-desc"><?php esc_html_e( 'View and manage existing redirection rules stored in your database.', 'align' ); ?></p>
		</div>
		<div style="display:flex; align-items:center; gap:8px;">
			<span class="align-accordion-arrow" style="font-size:12px; color:#94a3b8; transition:transform 0.2s ease;">▼</span>
		</div>
	</div>

	<div class="align-card-body" id="align-active-redirections-body" style="display:none;">
		<div style="display:flex; gap:8px; margin-bottom:12px; align-items:center;">
			<input type="search" id="align-redirections-search-input" placeholder="<?php esc_attr_e( 'Search source or target URL…', 'align' ); ?>" style="width:260px; height:30px; font-size:12px; border-radius:5px;">
			<button type="button" class="button button-secondary" id="align-redirections-search-btn"><?php esc_html_e( 'Search', 'align' ); ?></button>
			<button type="button" class="button button-secondary" id="align-redirections-refresh-btn"><?php esc_html_e( 'Refresh', 'align' ); ?></button>
		</div>

		<div style="max-height:420px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:6px;">
			<table class="wp-list-table widefat fixed striped" id="align-active-redirections-table" style="border:none;">
				<thead>
					<tr>
						<th style="width:50px;">ID</th>
						<th style="width:38%;"><?php esc_html_e( 'Source Path', 'align' ); ?></th>
						<th style="width:38%;"><?php esc_html_e( 'Destination', 'align' ); ?></th>
						<th style="width:60px;"><?php esc_html_e( 'Code', 'align' ); ?></th>
						<th style="width:60px;"><?php esc_html_e( 'Hits', 'align' ); ?></th>
						<th style="width:80px;"><?php esc_html_e( 'Actions', 'align' ); ?></th>
					</tr>
				</thead>
				<tbody id="align-active-redirections-tbody">
					<tr>
						<td colspan="6" style="text-align:center; padding:16px; color:#94a3b8;">
							<?php esc_html_e( 'Click header to open and load redirections…', 'align' ); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>
<?php endif; ?>

<!-- 404 URL Monitor & Quick Redirect Accordion (Default closed) -->
<div class="align-settings-card is-accordion" id="align-404-monitor-card" style="margin-top:16px;">
	<div class="align-card-header is-accordion" id="align-404-monitor-header" style="cursor:pointer; display:flex; align-items:center; justify-content:space-between;">
		<div>
			<h3 class="align-card-title"><?php esc_html_e( '404 URL Monitor & Quick Redirect', 'align' ); ?> <span id="align-404-count-badge" style="font-size:12px; font-weight:normal; color:#64748b;"></span></h3>
			<p class="align-card-desc"><?php esc_html_e( 'Track 404 errors encountered by visitors and create 301 redirects with one click.', 'align' ); ?></p>
		</div>
		<div style="display:flex; align-items:center; gap:8px;">
			<span class="align-accordion-arrow" style="font-size:12px; color:#94a3b8; transition:transform 0.2s ease;">▼</span>
		</div>
	</div>

	<div class="align-card-body" id="align-404-monitor-body" style="display:none;">
		<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
			<div style="display:flex; gap:8px; align-items:center;">
				<input type="search" id="align-404-search-input" placeholder="<?php esc_attr_e( 'Search 404 path or referer…', 'align' ); ?>" style="width:260px; height:30px; font-size:12px; border-radius:5px;">
				<button type="button" class="button button-secondary" id="align-404-search-btn"><?php esc_html_e( 'Search', 'align' ); ?></button>
				<button type="button" class="button button-secondary" id="align-404-refresh-btn"><?php esc_html_e( 'Refresh', 'align' ); ?></button>
			</div>
			<div>
				<button type="button" class="button button-danger" id="align-404-clear-btn"><?php esc_html_e( 'Clear All 404 Logs', 'align' ); ?></button>
			</div>
		</div>

		<div style="max-height:420px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:6px;">
			<table class="wp-list-table widefat fixed striped" id="align-404-table" style="border:none;">
				<thead>
					<tr>
						<th style="width:36%;"><?php esc_html_e( 'Requested 404 URL', 'align' ); ?></th>
						<th style="width:55px; text-align:center;"><?php esc_html_e( 'Hits', 'align' ); ?></th>
						<th style="width:28%;"><?php esc_html_e( 'Referer', 'align' ); ?></th>
						<th style="width:130px;"><?php esc_html_e( 'Last Detected', 'align' ); ?></th>
						<th style="width:170px; text-align:right;"><?php esc_html_e( 'Actions', 'align' ); ?></th>
					</tr>
				</thead>
				<tbody id="align-404-tbody">
					<tr>
						<td colspan="5" style="text-align:center; padding:16px; color:#94a3b8;">
							<?php esc_html_e( 'Click header to open and load 404 logs…', 'align' ); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>
