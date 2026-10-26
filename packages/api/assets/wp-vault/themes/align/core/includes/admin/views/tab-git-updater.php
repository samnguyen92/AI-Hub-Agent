<?php
/**
 * Tab: Dedicated Git Updates Hub for Align Theme & Ecosystem Plugins
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$last_updated    = get_option( 'align_git_last_updated', '' );
$backup_date     = get_option( 'align_git_last_backup_date', '' );
$backup_size     = (int) get_option( 'align_git_last_backup_size', 0 );
$has_backup      = ! empty( $backup_date ) && file_exists( Align_Git_Updater::get_backup_file() );
$backup_size_fmt = $backup_size > 0 ? size_format( $backup_size, 2 ) : '';
$site_url        = home_url();
$theme_path      = wp_normalize_path( trailingslashit( get_theme_root() ) . 'align' );
$current_os      = PHP_OS;

$current_token   = Align_Git_Updater::get_token();
$current_repo    = get_option( 'align_git_repository', Align_Git_Updater::DEFAULT_REPO );
$current_branch  = get_option( 'align_git_branch', Align_Git_Updater::DEFAULT_BRANCH );

// Detect active Align ecosystem plugins
$align_plugins = [
	'align-files' => [
		'name'       => 'Align Files',
		'repo'       => 'bibikeo1996/Align-Files',
		'file'       => 'align-files/align-files.php',
		'installed'  => is_dir( WP_PLUGIN_DIR . '/align-files' ),
		'version'    => defined( 'ALIGN_FILES_VERSION' ) ? ALIGN_FILES_VERSION : '1.0.0',
	],
	'align-seo-checker' => [
		'name'       => 'Align SEO Checker',
		'repo'       => 'bibikeo1996/Align-SEO-Checker',
		'file'       => 'align-seo-checker/align-seo-checker.php',
		'installed'  => is_dir( WP_PLUGIN_DIR . '/align-seo-checker' ),
		'version'    => '1.1.9',
	],
	'align-deploy' => [
		'name'       => 'Align Deploy',
		'repo'       => 'bibikeo1996/Align-Deploy',
		'file'       => 'align-deploy/align-deploy.php',
		'installed'  => is_dir( WP_PLUGIN_DIR . '/align-deploy' ),
		'version'    => defined( 'ALIGN_DEPLOY_VERSION' ) ? ALIGN_DEPLOY_VERSION : '1.0.0',
	],
	'align-agent' => [
		'name'       => 'Align Agent Gateway',
		'repo'       => 'bibikeo1996/Align-Agent',
		'file'       => 'align-agent/align-agent.php',
		'installed'  => is_dir( WP_PLUGIN_DIR . '/align-agent' ),
		'version'    => defined( 'ALIGN_AGENT_VERSION' ) ? ALIGN_AGENT_VERSION : '1.0.0',
	],
];
?>

<!-- 1. GitHub Fine-Grained Token & Global Auth Card -->
<div class="align-settings-card" style="margin-bottom: 20px;">
	<div class="align-card-header">
		<div>
			<h3 class="align-card-title"><?php esc_html_e( 'GitHub Access Token & Authentication', 'align' ); ?></h3>
			<p class="align-card-desc"><?php esc_html_e( 'Configure your Fine-Grained Personal Access Token (PAT) with repository read/download permissions.', 'align' ); ?></p>
		</div>
		<div style="display: flex; align-items: center; gap: 8px;">
			<button type="button" class="button button-secondary" id="align-git-test-btn">
				<span class="dashicons dashicons-yes-alt" style="font-size: 16px; width: 16px; height: 16px; line-height: 16px;"></span>
				<span><?php esc_html_e( 'Verify Token Connection', 'align' ); ?></span>
			</button>
			<span class="spinner" id="align-git-test-spinner" style="float: none; margin: 0;"></span>
		</div>
	</div>

	<div class="align-card-body">
		<table class="form-table" role="presentation" style="margin-top: 0;">
			<tr>
				<th scope="row" style="width: 220px; font-size: 13px; font-weight: 600; color: #334155;">
					<label for="align_git_token"><?php esc_html_e( 'GitHub Token (PAT)', 'align' ); ?></label>
				</th>
				<td>
					<input type="text"
						name="align_git_token"
						id="align_git_token"
						value="<?php echo esc_attr( $current_token ); ?>"
						class="large-text"
						placeholder="github_pat_..."
						autocomplete="off"
						style="width: 100%; max-width: 620px; height: 34px; border-radius: 6px; border: 1px solid #cbd5e1; font-family: monospace; font-size: 12.5px; padding: 0 10px;">
					<p class="description" style="font-size: 12px; color: #64748b; margin-top: 6px;">
						<?php esc_html_e( 'Fine-Grained Personal Access Token (PAT) used for downloading theme and plugin updates. Saved token is displayed plainly so you can edit it at any time.', 'align' ); ?>
					</p>
				</td>
			</tr>
		</table>
	</div>
</div>

<!-- 2. Align Theme Core Update Card -->
<div class="align-settings-card" style="margin-bottom: 20px;">
	<div class="align-card-header">
		<div>
			<h3 class="align-card-title"><?php esc_html_e( 'Align Theme Core (Align-Real-Builder)', 'align' ); ?></h3>
			<p class="align-card-desc"><?php esc_html_e( 'Pull framework core updates directly with pre-update safety snapshots and atomic replacement.', 'align' ); ?></p>
		</div>
		<div style="display: flex; align-items: center; gap: 8px;">
			<?php if ( $has_backup ) : ?>
			<button type="button" class="button button-danger" id="align-git-revert-btn">
				<?php esc_html_e( 'Revert to Backup', 'align' ); ?>
			</button>
			<?php endif; ?>

			<button type="button" class="button button-primary" id="align-git-pull-btn">
				<?php esc_html_e( 'Update Theme Now', 'align' ); ?>
			</button>
			<span class="spinner" id="align-git-spinner" style="float: none; margin: 0;"></span>
		</div>
	</div>

	<div class="align-card-body">
		<!-- Repository & Branch Grid -->
		<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 14px; margin-bottom: 16px;">
			<div>
				<label for="align_git_repository" style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;"><?php esc_html_e( 'Theme Repository', 'align' ); ?></label>
				<input type="text"
					name="align_git_repository"
					id="align_git_repository"
					value="<?php echo esc_attr( basename( $current_repo ) ); ?>"
					class="regular-text"
					style="width: 100%; height: 32px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
			</div>
			<div>
				<label for="align_git_branch" style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;"><?php esc_html_e( 'Branch', 'align' ); ?></label>
				<input type="text"
					name="align_git_branch"
					id="align_git_branch"
					value="<?php echo esc_attr( $current_branch ); ?>"
					class="regular-text"
					style="width: 100%; height: 32px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
			</div>
		</div>

		<!-- Status Grid -->
		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px;">
			<div>
				<div style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: #64748b; margin-bottom: 3px;"><?php esc_html_e( 'Theme Path', 'align' ); ?></div>
				<code style="font-size: 12px; color: #0f172a; word-break: break-all;"><?php echo esc_html( $theme_path ); ?></code>
			</div>
			<div>
				<div style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: #64748b; margin-bottom: 3px;"><?php esc_html_e( 'Last Updated', 'align' ); ?></div>
				<div style="font-size: 12.5px; font-weight: 600; color: #334155;">
					<?php echo ! empty( $last_updated ) ? esc_html( $last_updated ) : esc_html__( 'Never', 'align' ); ?>
				</div>
			</div>
			<div>
				<div class="align-meta-item-label" style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: #64748b; margin-bottom: 3px;"><?php esc_html_e( 'Safety Backup', 'align' ); ?></div>
				<div style="font-size: 12.5px; font-weight: 600; color: <?php echo $has_backup ? '#16a34a' : '#64748b'; ?>;">
					<?php echo ( $has_backup && ! empty( $backup_date ) ) ? esc_html( $backup_date ) . ( ! empty( $backup_size_fmt ) ? ' (' . esc_html( $backup_size_fmt ) . ')' : '' ) : esc_html__( 'Auto-created before update', 'align' ); ?>
				</div>
			</div>
		</div>

		<!-- Live Terminal Status Console -->
		<div id="align-git-console-wrap">
			<div style="background: #0f172a; color: #94a3b8; padding: 8px 14px; border-radius: 6px 6px 0 0; font-size: 12px; font-weight: 600; display: flex; justify-content: space-between; align-items: center;">
				<span><?php esc_html_e( 'Live Git Terminal Console', 'align' ); ?></span>
				<span style="font-size: 11px; color: #64748b;">Fine-Grained PAT Engine</span>
			</div>
			<div style="background: #1e293b; color: #f8fafc; border-radius: 0 0 6px 6px; padding: 14px 18px; font-family: monospace; font-size: 12.5px; line-height: 1.7; min-height: 90px; max-height: 280px; overflow-y: auto;" id="align-git-console">
				<div style="color: #94a3b8;">[<?php echo esc_html( current_time( 'g:i:s A' ) ); ?>] <span style="color: #38bdf8; font-weight: 600;">[Align GitUpdater]</span> Ready. Ready to verify or pull updates via GitHub API.</div>
			</div>
		</div>
	</div>
</div>

<!-- 3. Align Ecosystem Plugins Update Hub -->
<div class="align-settings-card">
	<div class="align-card-header">
		<div>
			<h3 class="align-card-title"><?php esc_html_e( 'Align Ecosystem Plugins Updates', 'align' ); ?></h3>
			<p class="align-card-desc"><?php esc_html_e( 'Manage Git updates for all 4 official Align plugins using the shared Fine-Grained Token.', 'align' ); ?></p>
		</div>
	</div>

	<div class="align-card-body" style="padding: 0;">
		<table class="wp-list-table widefat fixed striped" style="border: none; box-shadow: none;">
			<thead>
				<tr>
					<th style="font-size: 12px; font-weight: 700; color: #334155;"><?php esc_html_e( 'Plugin Name', 'align' ); ?></th>
					<th style="font-size: 12px; font-weight: 700; color: #334155;"><?php esc_html_e( 'Repository', 'align' ); ?></th>
					<th style="font-size: 12px; font-weight: 700; color: #334155;"><?php esc_html_e( 'Version', 'align' ); ?></th>
					<th style="font-size: 12px; font-weight: 700; color: #334155; text-align: right;"><?php esc_html_e( 'Action', 'align' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $align_plugins as $p_slug => $p_data ) : ?>
				<tr>
					<td style="vertical-align: middle; font-weight: 600; color: #0f172a;">
						<?php echo esc_html( $p_data['name'] ); ?>
					</td>
					<td style="vertical-align: middle; font-family: monospace; font-size: 12px; color: #0284c7;">
						<code style="font-size: 12px; font-weight: 600; color: #0284c7; background: #e0f2fe; padding: 2px 8px; border-radius: 4px; font-family: monospace;"><?php echo esc_html( basename( $p_data['repo'] ) ); ?></code>
					</td>
					<td style="vertical-align: middle; font-size: 12.5px; color: #475569;">
						v<?php echo esc_html( $p_data['version'] ); ?>
					</td>
					<td style="vertical-align: middle; text-align: right;">
						<div style="display: inline-flex; gap: 6px; justify-content: flex-end;">
							<?php if ( Align_Plugin_Updater::has_backup( $p_slug ) ) : ?>
							<button type="button"
								class="button button-danger align-plugin-revert-btn"
								data-plugin="<?php echo esc_attr( $p_slug ); ?>"
								style="height: 30px; font-size: 12px;">
								<?php esc_html_e( 'Revert', 'align' ); ?>
							</button>
							<?php endif; ?>
							<button type="button"
								class="button button-secondary align-plugin-update-btn"
								data-plugin="<?php echo esc_attr( $p_slug ); ?>"
								data-repo="<?php echo esc_attr( $p_data['repo'] ); ?>"
								style="height: 30px; font-size: 12px;">
								<?php esc_html_e( 'Update Plugin', 'align' ); ?>
							</button>
						</div>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<script>
<?php include __DIR__ . '/tab-git-updater.js'; ?>
</script>
