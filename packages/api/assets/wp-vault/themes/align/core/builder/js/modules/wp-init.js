/**
 * Align Theme Builder - WordPress Environment & Branding Initializer
 */

window.AlignBuilderModules = window.AlignBuilderModules || {};

window.AlignBuilderModules.WpInit = {
    init: function() {
        const wpConfig = window.VvvebWpConfig || {};

        // Pre-populate Reusable Block overrides from saved server state
        window._alignActiveReuseOverrides = (wpConfig.savedReusableOverrides && typeof wpConfig.savedReusableOverrides === 'object')
            ? JSON.parse(JSON.stringify(wpConfig.savedReusableOverrides))
            : {};

        // Initialize Dark Mode (Default to 'dark' if not set in localStorage)
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
        const darkModeIcon = document.querySelector('.btn-dark-mode i');
        if (darkModeIcon) {
            if (savedTheme === 'dark') {
                darkModeIcon.classList.remove('la-sun');
                darkModeIcon.classList.add('la-moon');
            } else {
                darkModeIcon.classList.remove('la-moon');
                darkModeIcon.classList.add('la-sun');
            }
        }

        // Render dynamic WordPress site icon or theme screenshot into #wp-site-icon
        const siteIconUrl = wpConfig.siteIconUrl;
        const logoUrl = siteIconUrl || wpConfig.themeScreenshotUrl;
        if (logoUrl) {
            const iconImg = document.getElementById("wp-site-icon");
            if (iconImg) {
                iconImg.src = logoUrl;
                iconImg.style.display = "inline-block";
                const fallbackBadge = document.getElementById("fallback-align-badge");
                if (fallbackBadge) fallbackBadge.style.display = "none";
            }
        }

        // Hydrate top panel site icon: only use siteIconUrl, otherwise preserve WordPress fallback icon
        const topPanelSiteIcon = document.getElementById("top-panel-site-icon");
        const topPanelFallback = document.getElementById("top-panel-site-icon-fallback");
        if (topPanelSiteIcon) {
            if (siteIconUrl) {
                topPanelSiteIcon.src = siteIconUrl;
                topPanelSiteIcon.style.display = "inline-block";
                if (topPanelFallback) {
                    topPanelFallback.style.display = "none";
                }
            } else {
                topPanelSiteIcon.style.display = "none";
                if (topPanelFallback) {
                    topPanelFallback.style.display = "inline-block";
                }
            }
        }

        if (siteIconUrl) {
            const faviconLinks = document.querySelectorAll("link[rel*='icon']");
            if (faviconLinks.length > 0) {
                faviconLinks.forEach(function(link) {
                    const href = link.getAttribute("href") || "";
                    if (href === "favicon.ico" || href.indexOf("core/builder/favicon.ico") !== -1) {
                        link.href = siteIconUrl;
                    }
                });
            } else {
                const newFavicon = document.createElement("link");
                newFavicon.rel = "icon";
                newFavicon.href = siteIconUrl;
                document.head.appendChild(newFavicon);
            }
            console.log('[Align UI] Dynamic favicon applied from siteIconUrl:', siteIconUrl);
        }

        // Dynamic Browser Tab Title & Dynamic Block Pill Indicator
        if (wpConfig.isDynamicBlock) {
            const dynPill = document.getElementById('top-panel-dynamic-pill');
            if (dynPill) {
                dynPill.style.display = 'inline-flex';
                dynPill.style.cursor = 'pointer';
                dynPill.title = 'Edit this dynamic block in Align Studio';
                dynPill.addEventListener('click', function() {
                    let studioUrl = (wpConfig.dynamicStudioUrl || 'admin.php?page=align-studio');
                    studioUrl = studioUrl.replace('align-dynamic-studio', 'align-studio');
                    studioUrl += (studioUrl.includes('?') ? '&' : '?') + 'post_id=' + wpConfig.activePostId;
                    window.open(studioUrl, '_blank');
                });
            }
            const blockId = wpConfig.activePostId;
            const scContainer = document.getElementById('top-panel-shortcode-container');
            const scTextElem  = document.getElementById('top-panel-shortcode-text');
            if (scContainer && scTextElem && blockId) {
                scTextElem.textContent = '[reuse-block id="' + blockId + '"]';
                scContainer.style.display = 'inline-flex';
            }
            document.title = 'Dynamic Block Studio ‹ ' + (wpConfig.siteTitle || 'WordPress');
        } else if (wpConfig.builderTitle) {
            document.title = wpConfig.builderTitle + ' ‹ ' + (wpConfig.siteTitle || 'WordPress');
        }

        // Initialize Back to CMS Dashboard Link
        const backCmsBtn = document.getElementById('back-to-cms-btn') || document.querySelector('.btn-back-cms');
        if (backCmsBtn && wpConfig.adminDashboardUrl) {
            backCmsBtn.href = wpConfig.adminDashboardUrl;
            console.log('[Align UI] CMS Dashboard link initialized:', wpConfig.adminDashboardUrl);
        }
    }
};
