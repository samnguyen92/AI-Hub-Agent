/**
 * Align Theme Builder - Reusable Block Content Overwrite Master Module
 *
 * Coordinates reusable block detection, REST API field fetching, and
 * Inspector Content Overwrite accordion rendering.
 *
 * @package Align
 */

window.AlignBuilderModules = window.AlignBuilderModules || {};

if (!window.AlignBuilderModules.WpReuseBlockOverwrite) {
    window.AlignBuilderModules.WpReuseBlockOverwrite = {
        fieldsCache: {},
        init: function() {
            console.log('[AlignBuilder] Reusable Block Overwrite initialized.');
        }
    };
}
