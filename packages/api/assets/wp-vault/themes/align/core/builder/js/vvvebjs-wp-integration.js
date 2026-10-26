/**
 * VvvebJs WordPress Integration Master Bundle Loader
 *
 * @package Align
 */

document.addEventListener("DOMContentLoaded", function() {
    const modules = window.AlignBuilderModules || {};

    // 0. Ensure WordPress Components, Blocks, Sections, and Styles are loaded in the sidebar
    if (window.Vvveb && window.Vvveb.Builder) {
        if (typeof window.Vvveb.Builder.loadControlGroups === "function") {
            window.Vvveb.Builder.loadControlGroups();
        }
        if (typeof window.Vvveb.Builder.loadBlockGroups === "function") {
            window.Vvveb.Builder.loadBlockGroups();
        }
        if (typeof window.Vvveb.Builder.loadSectionGroups === "function") {
            window.Vvveb.Builder.loadSectionGroups();
        }
        if (typeof window.Vvveb.Builder.loadStylesGroups === "function") {
            window.Vvveb.Builder.loadStylesGroups();
        }
    }

    // 1. Initialize WordPress Branding & Environment
    if (modules.WpInit && typeof modules.WpInit.init === "function") {
        modules.WpInit.init();
    }

    // 2. Initialize WordPress Media Modal Bridge
    if (modules.WpMediaModal && typeof modules.WpMediaModal.init === "function") {
        modules.WpMediaModal.init();
    }

    // 3. Initialize REST Save Handler
    if (modules.WpSaveHandler && typeof modules.WpSaveHandler.init === "function") {
        modules.WpSaveHandler.init();
    }

    // 4. Initialize Create / Delete Modals & Hooks
    if (modules.WpItemsCrud && typeof modules.WpItemsCrud.init === "function") {
        modules.WpItemsCrud.init();
    }

    // 5. Initialize WordPress Pages & Templates FileManager Router
    if (modules.WpFilemanager && typeof modules.WpFilemanager.init === "function") {
        modules.WpFilemanager.init();
    }

    // 6. Initialize Reusable Block Content Overwrite Inspector Module
    if (modules.WpReuseBlockOverwrite && typeof modules.WpReuseBlockOverwrite.init === "function") {
        modules.WpReuseBlockOverwrite.init();
    }

    // 7. Initialize Revision History & Comparison Controller
    if (modules.WpRevisionHandler && typeof modules.WpRevisionHandler.init === "function") {
        modules.WpRevisionHandler.init();
    }

    // 8. Initialize WordPress Toolbar Tooltips (Zero-Dangling & Unified Tooltips)
    if (modules.WpToolbarTooltips && typeof modules.WpToolbarTooltips.init === "function") {
        modules.WpToolbarTooltips.init();
    }
});
