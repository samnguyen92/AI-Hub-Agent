/**
 * Auto-expand Document Overview (Block Tree / List View) when a block is selected
 *
 * @package Align
 */
(function(wp) {
    if (!wp || !wp.data || !wp.data.subscribe) return;

    var lastSelectedId = null;

    wp.data.subscribe(function() {
        var blockEditorSelect = wp.data.select('core/block-editor');
        if (!blockEditorSelect) return;

        var selectedId = blockEditorSelect.getSelectedBlockClientId();
        if (selectedId && selectedId !== lastSelectedId) {
            lastSelectedId = selectedId;
            
            setTimeout(function() {
                try {
                    var editorSelect = wp.data.select('core/editor');
                    var editorDispatch = wp.data.dispatch('core/editor');

                    // If List View (Block Tree) is available and not open, auto-open it
                    if (editorDispatch && editorDispatch.setIsListViewOpened && editorSelect && typeof editorSelect.isListViewOpened === 'function') {
                        if (!editorSelect.isListViewOpened()) {
                            editorDispatch.setIsListViewOpened(true);
                        }
                    }
                } catch (e) {
                    // Ignore minor API variation warnings
                }
            }, 50);
        }
    });
})(window.wp);
