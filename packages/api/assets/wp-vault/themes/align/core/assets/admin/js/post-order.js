/**
 * Align Post Order - Inline List Table Drag & Drop Reorder
 *
 * Appends a small drag handle icon directly inside the checkbox column
 * of post list tables (`edit.php`, `upload.php`).
 *
 * PHP localized object: AlignPostOrder
 *   - post_type          {string}
 *   - archive_sort_nonce {string}
 *   - ajax_url           {string}
 */

/* global AlignPostOrder, jQuery */

(function ($) {
    'use strict';

    /**
     * Get URL query parameter.
     *
     * @param {string} name - Parameter name.
     * @returns {string|undefined}
     */
    function getQueryParam(name) {
        var match = window.location.search
            .substring(1)
            .split('&')
            .map(function (p) { return p.split('='); })
            .find(function (p) { return p[0] === name; });
        return match ? match[1] : undefined;
    }

    $(function () {
        if (typeof AlignPostOrder === 'undefined') { return; }

        var $list = $('table.wp-list-table #the-list');
        if (!$list.length) { return; }

        // Append small drag handle icon inside checkbox cell for each row
        $list.find('tr[id^="post-"]').each(function () {
            var $checkCell = $(this).find('.check-column');

            if ($checkCell.length && !$checkCell.find('.align-drag-handle').length) {
                $checkCell.append('<span class="align-drag-handle dashicons dashicons-menu" title="Kéo thả để sắp xếp"></span>');
            }
        });

        // Initialize sortable using the drag handle icon or check column
        $list.sortable({
            items: 'tr',
            handle: '.align-drag-handle, .check-column',
            axis: 'y',
            cursor: 'grabbing',
            opacity: 0.85,
            placeholder: {
                element: function (currentItem) {
                    var cols = currentItem.children('td:visible, th:visible').length;
                    return $('<tr class="align-order-placeholder"><td colspan="' + cols + '">&nbsp;</td></tr>')[0];
                },
                update: function () { /* no-op */ }
            },
            helper: function (e, tr) {
                var $originals = tr.children();
                var $helper = tr.clone();
                $helper.children().each(function (index) {
                    $(this).width($originals.eq(index).width());
                });
                return $helper;
            },
            update: function () {
                var paged = getQueryParam('paged') || 1;

                var order = $list.find('tr[id^="post-"]').map(function () {
                    var id = $(this).attr('id').replace(/^post-/, '');
                    return 'post[]=' + id;
                }).get().join('&');

                if (!order) { return; }

                var $handles = $list.find('.align-drag-handle');
                $handles.css('opacity', '0.4');

                $.ajax({
                    type: 'POST',
                    url: AlignPostOrder.ajax_url,
                    data: {
                        action: 'align_update_post_order_archive',
                        post_type: AlignPostOrder.post_type,
                        order: order,
                        paged: paged,
                        per_page: getQueryParam('per_page') || 20,
                        ALIGN_archive_sort_nonce: AlignPostOrder.archive_sort_nonce
                    },
                    cache: false,
                    success: function () {
                        $handles.css('opacity', '1');
                    },
                    error: function () {
                        $handles.css('opacity', '1');
                    }
                });
            }
        });
    });

}(jQuery));
