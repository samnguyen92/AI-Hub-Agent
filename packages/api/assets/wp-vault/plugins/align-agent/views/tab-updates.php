<?php
/**
 * Align Agent Gateway - Tab: Updates & Version Management
 *
 * @package Align_Agent
 * @subpackage Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$has_backup      = Align_Agent_Git_Updater::has_backup();
$backup_date     = get_option( 'align_agent_git_last_backup_date', '' );
$backup_size     = (int) get_option( 'align_agent_git_last_backup_size', 0 );
$has_backup      = ! empty( $backup_date ) && Align_Agent_Git_Updater::has_backup();
$backup_size_fmt = $backup_size > 0 ? size_format( $backup_size, 2 ) : '';
$last_updated    = get_option( 'align_agent_git_last_updated', '' );
$current_token   = Align_Agent_Git_Updater::get_token();
$repo_name       = basename( Align_Agent_Git_Updater::REPO );
?>
<div class="align-agent-card">
	<div class="align-agent-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
		<div>
			<h2 class="align-agent-card-title"><?php esc_html_e( 'Updates & Version Management', 'align-agent' ); ?></h2>
			<p class="align-agent-card-desc">
				<?php esc_html_e( 'Pull latest releases directly from GitHub repository with automated safety snapshots and one-click rollback.', 'align-agent' ); ?>
			</p>
		</div>
		<div style="display: flex; gap: 8px; align-items: center;">
			<button type="button" class="button button-secondary align-agent-btn" id="align-agent-git-test-btn" style="height: 32px !important; font-size: 12.5px !important;">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Verify Token', 'align-agent' ); ?>
			</button>

			<?php if ( $has_backup ) : ?>
			<button type="button" class="button button-danger align-agent-btn" id="align-agent-git-revert-btn" style="height: 32px !important; font-size: 12.5px !important; background: transparent !important; border: 1px solid #fecaca !important; color: #dc2626 !important;">
				<span class="dashicons dashicons-undo"></span>
				<?php esc_html_e( 'Revert to Backup', 'align-agent' ); ?>
			</button>
			<?php endif; ?>

			<button type="button" class="button button-danger align-agent-btn" id="align-agent-vault-purge-btn" style="height: 32px !important; font-size: 12.5px !important; background: transparent !important; border: 1px solid #fecaca !important; color: #dc2626 !important;">
				<span class="dashicons dashicons-trash"></span>
				<?php esc_html_e( 'Purge Backups', 'align-agent' ); ?>
			</button>

			<button type="button" class="button button-primary align-agent-btn-primary" id="align-agent-git-pull-btn" style="height: 32px !important; font-size: 12.5px !important;">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Update Plugin Now', 'align-agent' ); ?>
			</button>
		</div>
	</div>

	<div class="align-agent-card-body">
		<!-- Status & Meta Grid -->
		<div class="align-meta-grid">
			<div>
				<div class="align-meta-item-label"><?php esc_html_e( 'Repository', 'align-agent' ); ?></div>
				<code class="align-repo-badge"><?php echo esc_html( $repo_name ); ?></code>
			</div>
			<div>
				<div class="align-meta-item-label"><?php esc_html_e( 'Installed Version', 'align-agent' ); ?></div>
				<div style="font-size: 12.5px; font-weight: 600; color: #0f172a;">v<?php echo esc_html( defined( 'ALIGN_AGENT_VERSION' ) ? ALIGN_AGENT_VERSION : '1.0.0' ); ?></div>
			</div>
			<div>
				<div class="align-meta-item-label"><?php esc_html_e( 'Safety Snapshot', 'align-agent' ); ?></div>
				<div style="font-size: 12.5px; font-weight: 600; color: <?php echo $has_backup ? '#16a34a' : '#64748b'; ?>;">
					<?php echo ( $has_backup && ! empty( $backup_date ) ) ? esc_html( $backup_date ) . ( ! empty( $backup_size_fmt ) ? ' (' . esc_html( $backup_size_fmt ) . ')' : '' ) : esc_html__( 'Auto-saved before update', 'align-agent' ); ?>
				</div>
			</div>
			<div>
				<div class="align-meta-item-label"><?php esc_html_e( 'Last Updated', 'align-agent' ); ?></div>
				<div style="font-size: 12.5px; font-weight: 600; color: #334155;">
					<?php echo ! empty( $last_updated ) ? esc_html( $last_updated ) : esc_html__( 'Never', 'align-agent' ); ?>
				</div>
			</div>
		</div>

		<form method="post" action="">
			<?php wp_nonce_field( 'align_agent_save_nonce', 'align_agent_nonce_field' ); ?>

			<table class="form-table align-agent-table" style="margin-top: 0;">
				<tbody>
					<tr>
						<th scope="row" style="width: 200px; font-size: 13px; font-weight: 600; color: #334155;">
							<label for="align_agent_git_token"><?php esc_html_e( 'GitHub Token (PAT)', 'align-agent' ); ?></label>
						</th>
						<td>
							<div class="align-agent-input-group">
								<input type="text" name="align_agent_git_token" id="align_agent_git_token" class="regular-text align-agent-mono-input" style="width: 100%; max-width: 520px; height: 32px; border-radius: 6px; border: 1px solid #cbd5e1; font-family: monospace; font-size: 12.5px;" value="<?php echo esc_attr( $current_token ); ?>" placeholder="github_pat_...">
							</div>
							<p class="description" style="font-size: 12px; color: #64748b; margin-top: 6px;">
								<?php esc_html_e( 'Fine-Grained Personal Access Token. Default token is provided and editable at any time.', 'align-agent' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="align-agent-actions" style="margin-top: 14px; margin-bottom: 20px;">
				<input type="submit" name="align_agent_save_settings" class="button button-primary align-agent-btn-primary" value="<?php esc_attr_e( 'Save Token', 'align-agent' ); ?>" style="height: 32px !important; line-height: 30px !important;">
			</div>
		</form>

		<!-- Live Terminal Console -->
		<div style="margin-top: 16px;">
			<div style="background: #0f172a; color: #94a3b8; padding: 8px 14px; border-radius: 6px 6px 0 0; font-size: 12px; font-weight: 600; display: flex; justify-content: space-between; align-items: center;">
				<span><?php esc_html_e( 'Live Git Terminal Console', 'align-agent' ); ?></span>
				<span style="font-size: 11px; color: #64748b;">Fine-Grained PAT Engine</span>
			</div>
			<div style="background: #1e293b; color: #f8fafc; border-radius: 0 0 6px 6px; padding: 14px 18px; font-family: monospace; font-size: 12.5px; line-height: 1.7; min-height: 100px; max-height: 280px; overflow-y: auto;" id="align-agent-console">
				<div style="color: #94a3b8;">[<?php echo esc_html( current_time( 'g:i:s A' ) ); ?>] <span style="color: #38bdf8; font-weight: 600;">[Align GitUpdater]</span> Ready. Click "Verify Token" to test API connection or "Update Plugin Now" to deploy.</div>
			</div>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var gitTestBtn    = document.getElementById('align-agent-git-test-btn');
	var gitPullBtn    = document.getElementById('align-agent-git-pull-btn');
	var gitRevertBtn  = document.getElementById('align-agent-git-revert-btn');
	var vaultPurgeBtn = document.getElementById('align-agent-vault-purge-btn');
	var gitTokenInput = document.getElementById('align_agent_git_token');
	var consoleBox   = document.getElementById('align-agent-console');
	var ajaxUrl      = (window.alignAgentData && window.alignAgentData.ajaxUrl) ? window.alignAgentData.ajaxUrl : '/wp-admin/admin-ajax.php';
	var nonce        = (window.alignAgentData && window.alignAgentData.nonce) ? window.alignAgentData.nonce : '';

	function logTerminal(text, type = 'info') {
		if (!consoleBox) return;
		var now = new Date();
		var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
		var colors = { info: '#72aee6', success: '#00d084', error: '#cf2e2e', warning: '#f0b849' };
		var color = colors[type] || '#f0f0f1';
		var line = document.createElement('div');
		line.innerHTML = '<span style="color:#8c8f94;">[' + timeStr + ']</span> <span style="color:' + color + '; font-weight:600;">[Align GitUpdater]</span> ' + text;
		consoleBox.appendChild(line);
		consoleBox.scrollTop = consoleBox.scrollHeight;
	}

	if (gitTestBtn) {
		gitTestBtn.addEventListener('click', function() {
			var token = gitTokenInput ? gitTokenInput.value.trim() : '';
			logTerminal('Verifying Fine-Grained PAT token with GitHub API...', 'info');
			gitTestBtn.disabled = true;

			var fd = new FormData();
			fd.append('action', 'align_agent_git_test');
			fd.append('security', nonce);
			fd.append('token', token);

			fetch(ajaxUrl, { method: 'POST', body: fd })
				.then(function(r) { return r.json(); })
				.then(function(res) {
					if (res && res.success) {
						logTerminal(res.data ? res.data.message : 'Connected successfully!', 'success');
						alert('Verified: ' + (res.data ? res.data.message : 'Connected successfully!'));
					} else {
						var msg = res && res.data ? res.data.message : 'Verification failed';
						logTerminal('Verification Error: ' + msg, 'error');
						alert('Verification Error: ' + msg);
					}
				})
				.catch(function(err) {
					logTerminal('Network Error: ' + err.message, 'error');
					alert('Network Error: ' + err.message);
				})
				.finally(function() {
					gitTestBtn.disabled = false;
				});
		});
	}

	if (gitPullBtn) {
		gitPullBtn.addEventListener('click', function() {
			if (!confirm('Are you sure you want to pull the latest version of Align Agent from GitHub? A safety snapshot will be saved first.')) {
				return;
			}
			var token = gitTokenInput ? gitTokenInput.value.trim() : '';
			logTerminal('Creating safety backup snapshot of current plugin files...', 'info');
			logTerminal('Requesting repository zipball from GitHub API...', 'info');
			gitPullBtn.disabled = true;

			var fd = new FormData();
			fd.append('action', 'align_agent_git_pull');
			fd.append('security', nonce);
			fd.append('token', token);

			fetch(ajaxUrl, { method: 'POST', body: fd })
				.then(function(r) { return r.json(); })
				.then(function(res) {
					if (res && res.success) {
						logTerminal('Files cleanly replaced & OPcache flushed.', 'info');
						logTerminal('Align Agent successfully updated from GitHub!', 'success');
						alert('Success: ' + (res.data ? res.data.message : 'Updated successfully!'));
						setTimeout(function() { location.reload(); }, 1200);
					} else {
						var msg = res && res.data ? res.data.message : 'Update failed';
						logTerminal('Update Error: ' + msg, 'error');
						alert('Update Error: ' + msg);
					}
				})
				.catch(function(err) {
					logTerminal('Error: ' + err.message, 'error');
					alert('Error: ' + err.message);
				})
				.finally(function() {
					gitPullBtn.disabled = false;
				});
		});
	}

	if (gitRevertBtn) {
		gitRevertBtn.addEventListener('click', function() {
			if (!confirm('Are you sure you want to REVERT Align Agent back to the previous snapshot backup?')) {
				return;
			}
			logTerminal('Restoring Align Agent from safety backup snapshot...', 'warning');
			gitRevertBtn.disabled = true;

			var fd = new FormData();
			fd.append('action', 'align_agent_git_revert');
			fd.append('security', nonce);

			fetch(ajaxUrl, { method: 'POST', body: fd })
				.then(function(r) { return r.json(); })
				.then(function(res) {
					if (res && res.success) {
						logTerminal('Plugin restored from previous backup snapshot successfully!', 'success');
						alert('Success: ' + (res.data ? res.data.message : 'Restored successfully!'));
						setTimeout(function() { location.reload(); }, 1200);
					} else {
						var msg = res && res.data ? res.data.message : 'Revert failed';
						logTerminal('Revert Error: ' + msg, 'error');
						alert('Revert Error: ' + msg);
					}
				})
				.catch(function(err) {
					logTerminal('Error: ' + err.message, 'error');
					alert('Error: ' + err.message);
				})
				.finally(function() {
					gitRevertBtn.disabled = false;
				});
		});
	}

	if (vaultPurgeBtn) {
		vaultPurgeBtn.addEventListener('click', function() {
			if (!confirm('Are you sure you want to permanently purge all Global Vault backups? This action cannot be undone.')) {
				return;
			}
			logTerminal('Purging all Global Vault backup snapshots...', 'warning');
			vaultPurgeBtn.disabled = true;

			var fd = new FormData();
			fd.append('action', 'align_agent_vault_purge');
			fd.append('security', nonce);

			fetch(ajaxUrl, { method: 'POST', body: fd })
				.then(function(r) { return r.json(); })
				.then(function(res) {
					if (res && res.success) {
						var msg = res.data && res.data.message ? res.data.message : 'Backups purged successfully.';
						logTerminal(msg, 'success');
						alert(msg);
					} else {
						var err = res && res.data && res.data.message ? res.data.message : 'Purge failed';
						logTerminal('Purge Error: ' + err, 'error');
						alert('Purge Error: ' + err);
					}
				})
				.catch(function(err) {
					logTerminal('Error: ' + err.message, 'error');
					alert('Error: ' + err.message);
				})
				.finally(function() {
					vaultPurgeBtn.disabled = false;
				});
		});
	}
});
</script>
