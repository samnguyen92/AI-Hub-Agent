document.addEventListener('DOMContentLoaded', function() {
	const pullBtn       = document.getElementById('align-git-pull-btn');
	const testBtn       = document.getElementById('align-git-test-btn');
	const revertBtn     = document.getElementById('align-git-revert-btn');
	const spinner       = document.getElementById('align-git-spinner');
	const testSpinner   = document.getElementById('align-git-test-spinner');
	const consoleLog    = document.getElementById('align-git-console');
	const nonceInput    = document.getElementById('align_settings_nonce');
	const tokenInput    = document.getElementById('align_git_token');
	const repoInput     = document.getElementById('align_git_repository');
	const branchInput   = document.getElementById('align_git_branch');

	function logMessage(text, type = 'info') {
		if (!consoleLog) return;
		const now = new Date();
		const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
		const colorMap = {
			info: '#72aee6',
			success: '#00d084',
			error: '#cf2e2e',
			warning: '#f0b849'
		};
		const color = colorMap[type] || '#f0f0f1';
		const line = document.createElement('div');
		line.innerHTML = `<span style="color:#8c8f94;">[${timeStr}]</span> <span style="color:${color}; font-weight:600;">[Align GitUpdater]</span> ${text}`;
		consoleLog.appendChild(line);
		consoleLog.scrollTop = consoleLog.scrollHeight;
	}

	// 1. Verify Token Connection Handler
	if (testBtn) {
		testBtn.addEventListener('click', function(e) {
			e.preventDefault();
			testBtn.disabled = true;
			if (testSpinner) testSpinner.classList.add('is-active');

			const nonce = nonceInput ? nonceInput.value : '';
			const token = tokenInput ? tokenInput.value.trim() : '';
			const repo = repoInput ? repoInput.value.trim() : '';
			const branch = branchInput ? branchInput.value.trim() : '';

			logMessage(`Verifying Fine-Grained Token against ${repo} (${branch})...`, 'info');

			const formData = new URLSearchParams();
			formData.append('action', 'align_git_test_connection');
			formData.append('align_settings_nonce', nonce);
			formData.append('token', token);
			formData.append('repository', repo);
			formData.append('branch', branch);

			fetch(ajaxurl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: formData.toString()
			})
			.then(res => res.json())
			.then(res => {
				testBtn.disabled = false;
				if (testSpinner) testSpinner.classList.remove('is-active');

				if (res.success && res.data) {
					logMessage(`Connection Verified! Latest SHA: ${res.data.sha} | Author: ${res.data.author} | Branch: ${res.data.branch}`, 'success');
					alert('Connection Verified: ' + res.data.message + ' (Commit: ' + res.data.sha + ')');
				} else {
					throw new Error(res.data?.message || 'Authentication rejected by GitHub.');
				}
			})
			.catch(err => {
				testBtn.disabled = false;
				if (testSpinner) testSpinner.classList.remove('is-active');
				logMessage(`Verification Failed: ${err.message}`, 'error');
				alert('Verification Failed: ' + err.message);
			});
		});
	}

	// 2. Update Theme Handler
	if (pullBtn) {
		pullBtn.addEventListener('click', function(e) {
			e.preventDefault();
			if (!confirm('Are you sure you want to pull and update Align Theme Core from GitHub? A safety backup snapshot will be saved automatically first.')) {
				return;
			}

			pullBtn.disabled = true;
			if (spinner) spinner.classList.add('is-active');
			if (revertBtn) revertBtn.disabled = true;

			const nonce = nonceInput ? nonceInput.value : '';
			const token = tokenInput ? tokenInput.value.trim() : '';
			const repo = repoInput ? repoInput.value.trim() : '';
			const branch = branchInput ? branchInput.value.trim() : '';

			logMessage('Creating safety backup snapshot of current theme...', 'info');
			logMessage(`Connecting to GitHub repository: ${repo} (branch: ${branch})...`, 'info');

			const pullData = new URLSearchParams();
			pullData.append('action', 'align_git_pull_code');
			pullData.append('align_settings_nonce', nonce);
			pullData.append('token', token);
			pullData.append('repository', repo);
			pullData.append('branch', branch);

			fetch(ajaxurl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: pullData.toString()
			})
			.then(res => res.json())
			.then(res => {
				pullBtn.disabled = false;
				if (spinner) spinner.classList.remove('is-active');
				if (revertBtn) revertBtn.disabled = false;

				if (res.success && res.data) {
					logMessage('Maintenance mode enabled, safety snapshot saved & files cleanly replaced.', 'info');
					logMessage('Align Theme Core updated successfully!', 'success');
					alert('Success: ' + res.data.message);
					location.reload();
				} else {
					throw new Error(res.data?.message || 'Update failed.');
				}
			})
			.catch(err => {
				pullBtn.disabled = false;
				if (spinner) spinner.classList.remove('is-active');
				if (revertBtn) revertBtn.disabled = false;
				logMessage(`Update Failed: ${err.message}`, 'error');
				alert('Update Failed: ' + err.message);
			});
		});
	}

	// 3. Revert Theme Handler
	if (revertBtn) {
		revertBtn.addEventListener('click', function(e) {
			e.preventDefault();
			if (!confirm('Are you sure you want to REVERT Align Theme back to previous backup snapshot?')) {
				return;
			}

			revertBtn.disabled = true;
			const nonce = nonceInput ? nonceInput.value : '';
			logMessage('Restoring theme from persistent backup snapshot...', 'warning');

			const revertData = new URLSearchParams();
			revertData.append('action', 'align_git_revert_backup');
			revertData.append('align_settings_nonce', nonce);

			fetch(ajaxurl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: revertData.toString()
			})
			.then(res => res.json())
			.then(res => {
				revertBtn.disabled = false;
				if (res.success) {
					logMessage('Theme restored from backup successfully!', 'success');
					alert('Success: ' + res.data.message);
					location.reload();
				} else {
					throw new Error(res.data?.message || 'Revert failed.');
				}
			})
			.catch(err => {
				revertBtn.disabled = false;
				logMessage(`Revert Failed: ${err.message}`, 'error');
				alert('Revert Failed: ' + err.message);
			});
		});
	}

	// 4. Ecosystem Plugins Update Handler
	document.querySelectorAll('.align-plugin-update-btn').forEach(btn => {
		btn.addEventListener('click', function(e) {
			e.preventDefault();
			const pSlug = this.dataset.plugin;
			const pRepo = this.dataset.repo;
			const pName = this.closest('tr').querySelector('td').textContent.trim();

			if (!confirm(`Are you sure you want to pull and update ${pName} from ${pRepo}?`)) {
				return;
			}

			btn.disabled = true;
			btn.textContent = 'Updating...';
			const token = tokenInput ? tokenInput.value.trim() : '';
			const nonce = nonceInput ? nonceInput.value : '';

			logMessage(`[${pName}] Requesting Git pull for ${pRepo}...`, 'info');

			const formData = new URLSearchParams();
			formData.append('action', 'align_git_pull_plugin');
			formData.append('plugin_slug', pSlug);
			formData.append('repository', pRepo);
			formData.append('token', token);
			formData.append('align_settings_nonce', nonce);

			fetch(ajaxurl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: formData.toString()
			})
			.then(res => res.json())
			.then(res => {
				btn.disabled = false;
				btn.textContent = 'Update Plugin';

				if (res.success) {
					logMessage(`[${pName}] Updated successfully!`, 'success');
					alert(`Success: ${pName} updated cleanly!`);
					location.reload();
				} else {
					throw new Error(res.data?.message || 'Plugin update failed.');
				}
			})
			.catch(err => {
				btn.disabled = false;
				btn.textContent = 'Update Plugin';
				logMessage(`[${pName}] Error: ${err.message}`, 'error');
				alert(`Update Error: ${err.message}`);
			});
		});
	});

	// 5. Ecosystem Plugins Revert Handler
	document.querySelectorAll('.align-plugin-revert-btn').forEach(btn => {
		btn.addEventListener('click', function(e) {
			e.preventDefault();
			const pSlug = this.dataset.plugin;
			const pName = this.closest('tr').querySelector('td').textContent.trim();

			if (!confirm(`Are you sure you want to REVERT ${pName} back to its previous snapshot backup?`)) {
				return;
			}

			btn.disabled = true;
			btn.textContent = 'Reverting...';
			const nonce = nonceInput ? nonceInput.value : '';

			logMessage(`[${pName}] Requesting restore from snapshot backup...`, 'warning');

			const formData = new URLSearchParams();
			formData.append('action', 'align_git_revert_plugin');
			formData.append('plugin_slug', pSlug);
			formData.append('align_settings_nonce', nonce);

			fetch(ajaxurl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: formData.toString()
			})
			.then(res => res.json())
			.then(res => {
				btn.disabled = false;
				btn.textContent = 'Revert';

				if (res.success) {
					logMessage(`[${pName}] Reverted successfully!`, 'success');
					alert(`Success: ${res.data.message}`);
					location.reload();
				} else {
					throw new Error(res.data?.message || 'Plugin revert failed.');
				}
			})
			.catch(err => {
				btn.disabled = false;
				btn.textContent = 'Revert';
				logMessage(`[${pName}] Revert Error: ${err.message}`, 'error');
				alert(`Revert Error: ${err.message}`);
			});
		});
	});
});
