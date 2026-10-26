/**
 * Align Theme Settings - Admin Layout JS (Master & Sub-Tab Architecture)
 *
 * Manages 4 Master Hubs: Builder & Studio, SEO & Languages, Performance & Media, Code & Tools.
 * Supports deep-linking, legacy tab redirection, sub-navigation pills, and local persistence.
 *
 * @package Align
 */
document.addEventListener('DOMContentLoaded', function() {
	// Ensure all notices stay cleanly above the nav tabs bar
	function repositionNotices() {
		const navBar = document.querySelector('.align-nav-tabs-bar');
		if (!navBar) return;
		const wrap = navBar.closest('.align-settings-wrap') || document.querySelector('.align-settings-wrap');
		if (!wrap) return;

		const candidates = [];
		const wpbody = document.getElementById('wpbody-content');
		if (wpbody) {
			candidates.push(...wpbody.querySelectorAll('.notice, .updated, .error'));
		}
		candidates.push(...wrap.querySelectorAll('.notice, .updated, .error'));

		const uniqueNodes = Array.from(new Set(candidates));
		const seen = new Set();
		let processedAny = false;

		uniqueNodes.forEach(notice => {
			if (notice.closest('.align-tab-content') || (notice.parentElement && notice.parentElement.closest('.notice, .updated, .error'))) {
				return;
			}

			const key = notice.textContent.replace(/\s+/g, ' ').trim();
			if (seen.has(key)) {
				notice.remove();
				processedAny = true;
				return;
			}
			seen.add(key);

			wrap.insertBefore(notice, navBar);
			processedAny = true;
		});

		if (processedAny) {
			console.log('[Align Settings UI] Repositioned notices and removed duplicates');
		}
	}
	repositionNotices();
	setTimeout(repositionNotices, 50);
	setTimeout(repositionNotices, 300);

	const navTabs = document.querySelectorAll('.align-nav-tab');
	const tabContents = document.querySelectorAll('.align-tab-content');

	// Map old 10 tab IDs to 4 Master Tabs and their Subtabs
	const tabAliasMap = {
		// Master Tab 1: Builder & AI Studio
		'builder':         { master: 'builder-studio', subtab: 'builder-settings' },
		'visual-builder':  { master: 'builder-studio', subtab: 'builder-settings' },
		'builder-settings':{ master: 'builder-studio', subtab: 'builder-settings' },
		'api':             { master: 'api', subtab: null },
		'ai-endpoint':     { master: 'api', subtab: null },
		'rest-api':        { master: 'api', subtab: null },
		'connect-api':     { master: 'builder-studio', subtab: 'builder-connect-api' },
		'import-zip':      { master: 'builder-studio', subtab: 'builder-import-zip' },

		// Master Tab 2: SEO & Multi-Language
		'languages':       { master: 'seo-languages', subtab: 'seo-languages-active' },
		'seo-languages':   { master: 'seo-languages', subtab: 'seo-languages-active' },
		'redirection':     { master: 'seo-languages', subtab: 'seo-languages-redirections' },
		'redirections':    { master: 'seo-languages', subtab: 'seo-languages-redirections' },
		'matrix':          { master: 'seo-languages', subtab: 'seo-languages-matrix' },
		'translation':     { master: 'seo-languages', subtab: 'seo-languages-matrix' },
		'bulk-translate':  { master: 'seo-languages', subtab: 'seo-languages-bulk' },
		'bulk':            { master: 'seo-languages', subtab: 'seo-languages-bulk' },
		'bulk-seo':        { master: 'seo-languages', subtab: 'seo-languages-bulk-seo' },
		'seo-generator':   { master: 'seo-languages', subtab: 'seo-languages-bulk-seo' },

		// Master Tab 3: Performance & Media
		'media':           { master: 'performance', subtab: 'perf-media' },
		'webp':            { master: 'performance', subtab: 'perf-media' },
		'media-sizes':     { master: 'performance', subtab: 'perf-media' },
		'plugins':         { master: 'performance', subtab: 'perf-plugins' },
		'minify':          { master: 'performance', subtab: 'perf-minify' },

		// Master Tab 4: Code & System Tools
		'custom-code':     { master: 'system-tools', subtab: 'sys-custom-code' },
		'fonts':           { master: 'system-tools', subtab: 'sys-load-asset' },
		'font-manager':    { master: 'system-tools', subtab: 'sys-load-asset' },
		'typography':      { master: 'system-tools', subtab: 'sys-load-asset' },
		'load-asset':      { master: 'system-tools', subtab: 'sys-load-asset' },
		'git-updater':     { master: 'system-tools', subtab: 'sys-git-updater' },

		// Master Tab: Site Protection
		'site-protect':    { master: 'site-protect', subtab: null },
		'site-protection': { master: 'site-protect', subtab: null },
		'sys-site-protect':{ master: 'site-protect', subtab: null },
		'security':        { master: 'site-protect', subtab: null },

		// Master Tab 6: Integrations
		'integrations':    { master: 'integrations', subtab: null },
		'integration':     { master: 'integrations', subtab: null },
		'telegram':        { master: 'integrations', subtab: null }
	};

	/**
	 * Activate Master Tab and optional Sub-Tab
	 */
	function activateTab(tabId, targetSubtab = null) {
		if (!tabId) return false;
		let cleanId = tabId.replace('#', '').replace(/^tab-/, '');

		// Handle slash deep-linking: e.g. seo-languages/redirections
		if (cleanId.includes('/')) {
			const parts = cleanId.split('/');
			cleanId = parts[0];
			targetSubtab = parts[1];
		}

		cleanId = cleanId.replace(/[^a-zA-Z0-9_-]/g, '');

		// Check aliases
		if (tabAliasMap[cleanId]) {
			const mapped = tabAliasMap[cleanId];
			cleanId = mapped.master;
			if (!targetSubtab) {
				targetSubtab = mapped.subtab;
			}
		}

		const targetContent = document.getElementById('tab-' + cleanId);
		const targetNav = document.querySelector('.align-nav-tab[data-tab="' + cleanId + '"]');

		if (targetContent && targetNav) {
			navTabs.forEach(t => t.classList.remove('is-active'));
			tabContents.forEach(c => c.style.display = 'none');

			targetNav.classList.add('is-active');
			targetContent.style.display = 'block';

			// Expand layout width for API tab 2-column workspace
			const wrap = document.querySelector('.align-settings-wrap');
			if (wrap) {
				if (cleanId === 'api') {
					wrap.classList.add('is-wide-layout');
				} else {
					wrap.classList.remove('is-wide-layout');
				}
			}

			// Save active master tab state
			try {
				localStorage.setItem('align_active_tab', cleanId);
			} catch (e) {}

			// Activate Sub-Tab if present
			if (targetSubtab) {
				activateSubtab(targetContent, targetSubtab);
			} else {
				// Restore saved subtab for this master tab
				let savedSubtab = null;
				try {
					savedSubtab = localStorage.getItem('align_subtab_' + cleanId);
				} catch (e) {}
				if (savedSubtab) {
					activateSubtab(targetContent, savedSubtab);
				}
			}

			return true;
		}
		return false;
	}

	/**
	 * Activate Sub-Tab inside a Master Tab
	 */
	function activateSubtab(masterContainer, subtabId) {
		if (!masterContainer || !subtabId) return;
		let cleanSub = subtabId.replace(/^subtab-/, '');

		// Resolve short aliases for subtab
		if (cleanSub === 'redirections' || cleanSub === 'redirection') cleanSub = 'seo-languages-redirections';
		if (cleanSub === 'matrix' || cleanSub === 'translations')      cleanSub = 'seo-languages-matrix';
		if (cleanSub === 'languages' || cleanSub === 'active')         cleanSub = 'seo-languages-active';
		if (cleanSub === 'bulk' || cleanSub === 'bulk-translate')      cleanSub = 'seo-languages-bulk';
		if (cleanSub === 'site-protect' || cleanSub === 'site-protection' || cleanSub === 'security') cleanSub = 'sys-site-protect';
		if (cleanSub === 'media' || cleanSub === 'webp' || cleanSub === 'media-sizes') cleanSub = 'perf-media';

		const targetPill = masterContainer.querySelector('.align-subnav-pill[data-subtab="' + cleanSub + '"]');
		const targetContent = masterContainer.querySelector('#subtab-' + cleanSub);

		if (targetPill && targetContent) {
			const allPills = masterContainer.querySelectorAll('.align-subnav-pill');
			const allContents = masterContainer.querySelectorAll('.align-subtab-content');

			allPills.forEach(p => p.classList.remove('is-active'));
			allContents.forEach(c => c.style.display = 'none');

			targetPill.classList.add('is-active');
			targetContent.style.display = 'block';

			// Save subtab state
			const masterId = masterContainer.id.replace(/^tab-/, '');
			try {
				localStorage.setItem('align_subtab_' + masterId, cleanSub);
			} catch (e) {}
		}
	}

	// Master Tab Click Handler
	navTabs.forEach(tab => {
		tab.addEventListener('click', function(e) {
			e.preventDefault();
			const target = this.getAttribute('data-tab');
			if (activateTab(target)) {
				if (history.pushState) {
					history.pushState(null, null, '#' + target);
				} else {
					window.location.hash = target;
				}
			}
		});
	});

	// Sub-Tab Click Delegation
	document.addEventListener('click', function(e) {
		const pill = e.target.closest('.align-subnav-pill');
		if (!pill) return;
		e.preventDefault();

		const masterContainer = pill.closest('.align-tab-content') || pill.closest('.align-master-tab-wrap');
		const subtabId = pill.getAttribute('data-subtab');
		if (masterContainer && subtabId) {
			activateSubtab(masterContainer, subtabId);

			// Update hash
			const masterNav = document.querySelector('.align-nav-tab.is-active');
			const masterId = masterNav ? masterNav.getAttribute('data-tab') : '';
			if (masterId && history.pushState) {
				history.pushState(null, null, '#' + masterId + '/' + subtabId);
			}
		}
	});

	// Handle cross-tab switch links
	document.addEventListener('click', function(e) {
		const switchLink = e.target.closest('.align-switch-tab-link');
		if (switchLink) {
			e.preventDefault();
			const target = switchLink.getAttribute('data-target-tab');
			if (activateTab(target)) {
				if (history.pushState) {
					history.pushState(null, null, '#' + target);
				} else {
					window.location.hash = target;
				}
			}
		}
	});

	// Restore tab state from URL Hash or localStorage
	function restoreTab() {
		const hash = window.location.hash;
		if (hash && activateTab(hash)) {
			return;
		}
		let savedTab = null;
		try {
			savedTab = localStorage.getItem('align_active_tab');
		} catch (e) {}

		if (savedTab && activateTab(savedTab)) {
			if (history.replaceState) {
				history.replaceState(null, null, '#' + savedTab);
			}
			return;
		}

		// Fallback default: Site Protection (Fast access on/off)
		activateTab('site-protect');
	}

	restoreTab();

	window.addEventListener('hashchange', function() {
		if (window.location.hash) {
			activateTab(window.location.hash);
		}
	});

	// -------------------------------------------------------------------------
	// ACCORDION: .is-accordion cards (used in Security Panel and other tabs)
	// -------------------------------------------------------------------------
	document.querySelectorAll('.align-settings-card.is-accordion').forEach(function(card) {
		// If card already has a dedicated accordion toggle indicator or controller, skip generic injection
		if (card.querySelector('.align-accordion-toggle') || card.hasAttribute('data-accordion-manual')) {
			return;
		}

		var header = card.querySelector('.align-card-header');
		var body   = card.querySelector('.align-card-body');
		if (!header || !body) return;

		// Add arrow indicator to header.
		var arrow = document.createElement('span');
		arrow.innerHTML = '&#9660;';
		arrow.style.cssText = 'font-size:11px;color:#94a3b8;margin-left:8px;display:inline-block;transition:transform .2s ease;flex-shrink:0;';
		arrow.className = 'align-accordion-arrow';
		header.style.cursor = 'pointer';
		header.appendChild(arrow);

		function toggleAccordion(open) {
			if (open) {
				body.style.display = 'block';
				arrow.style.transform = 'rotate(180deg)';
				header.setAttribute('aria-expanded', 'true');
			} else {
				body.style.display = 'none';
				arrow.style.transform = 'rotate(0deg)';
				header.setAttribute('aria-expanded', 'false');
			}
		}

		header.addEventListener('click', function(e) {
			if (e.target && e.target.closest('button, a, input, label')) {
				return;
			}
			var isOpen = body.style.display !== 'none';
			toggleAccordion(!isOpen);
		});

		header.addEventListener('keydown', function(e) {
			if (e.key === 'Enter' || e.key === ' ') {
				e.preventDefault();
				var isOpen = body.style.display !== 'none';
				toggleAccordion(!isOpen);
			}
		});
	});
});
