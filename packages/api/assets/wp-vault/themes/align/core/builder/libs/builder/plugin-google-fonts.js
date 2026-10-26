/*
 * VvvebJs Default System & Web Fonts Integration
 */

const systemFonts = {
    "System Default": "system-ui, -apple-system, sans-serif",
    "Inter": "Inter, sans-serif",
    "Roboto": "Roboto, sans-serif",
    "Open Sans": "Open Sans, sans-serif",
    "Arial": "Arial, sans-serif",
    "Helvetica": "Helvetica, sans-serif",
    "Times New Roman": "Times New Roman, serif",
    "Georgia": "Georgia, serif",
    "Courier New": "Courier New, monospace",
    "Verdana": "Verdana, sans-serif",
    "Tahoma": "Tahoma, sans-serif",
    "Trebuchet MS": "Trebuchet MS, sans-serif"
};

document.addEventListener("DOMContentLoaded", function() {
    setTimeout(function() {
        if (typeof Vvveb !== "undefined" && Vvveb.FontsManager) {
            Vvveb.FontsManager.addFontList("system", "Standard Fonts", systemFonts);
        }
    }, 500);
});
