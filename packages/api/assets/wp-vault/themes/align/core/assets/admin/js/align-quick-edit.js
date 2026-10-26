/**
 * Align Theme - Quick Edit Universal Password Lock
 *
 * Implements dedicated Password Protection card in WordPress Quick Edit.
 * Provides cleartext password display, live status badges, and intuitive
 * empty-detection for password removal.
 *
 * Adheres strictly to Align Coding Standards (< 500 lines, English logs/comments).
 */

(function ($) {
	'use strict';

	if (typeof inlineEditPost === 'undefined') {
		return;
	}

	// Preserve native WordPress inlineEditPost.edit function
	var _wp_inline_edit = inlineEditPost.edit;

	inlineEditPost.edit = function (id) {
		// Execute core WordPress inline edit behavior first
		_wp_inline_edit.apply(this, arguments);

		var postId = 0;
		if (typeof id === 'object') {
			postId = parseInt(this.getId(id), 10);
		} else {
			postId = parseInt(id, 10);
		}

		if (postId <= 0) {
			return;
		}

		var $editRow = $('#edit-' + postId);
		if (!$editRow.length) {
			return;
		}

		// Suppress native WordPress password input, label, and OR separator
		$editRow.find('.inline-edit-password-input').each(function () {
			$(this).closest('label').hide();
		});
		$editRow.find('.inline-edit-or').hide();

		var $lockWrap = $editRow.find('.align-quick-edit-password-wrap');
		var $statusGroup = $editRow.find('.inline-edit-group:has(select[name="_status"])');
		if (!$statusGroup.length) {
			$statusGroup = $editRow.find('.inline-edit-status').closest('.inline-edit-group');
		}
		if (!$statusGroup.length) {
			$statusGroup = $editRow.find('.inline-edit-col-right .inline-edit-group:first');
		}

		// Position dedicated password card directly below status/visibility group
		if ($statusGroup.length && $lockWrap.length) {
			$statusGroup.after($lockWrap);
			$editRow.find('fieldset.align-quick-edit-password-fieldset').hide();
			$lockWrap.show();
		}

		// Read post lock state and plain password
		var $postRow = $('#post-' + postId);
		var $lockData = $postRow.find('.align-pw-lock-data');
		if (!$lockData.length) {
			$lockData = $editRow.prevAll('#post-' + postId).find('.align-pw-lock-data');
		}
		var currentPassword = $lockData.first().attr('data-password') || '';

		var $input = $lockWrap.find('.align-qe-password-input');
		var $badge = $lockWrap.find('.align-qe-badge');

		// Set clean placeholder and pre-fill actual password value
		$input.attr('placeholder', 'Password');
		$input.val(currentPassword);
		$input.attr('value', currentPassword);
		$lockWrap.data('initial-password', currentPassword);

		// Set initial badge status
		$badge.removeClass('align-qe-badge-set align-qe-badge-none align-qe-badge-new align-qe-badge-remove');
		if (currentPassword.length > 0) {
			$badge.addClass('align-qe-badge-set').text('Password Set');
		} else {
			$badge.addClass('align-qe-badge-none').text('No Password');
		}
	};

	// Handle typing in password input to reflect dynamic state
	$(document).on('input', '.align-qe-password-input', function () {
		var $input = $(this);
		var $wrap = $input.closest('.align-quick-edit-password-wrap');
		var $badge = $wrap.find('.align-qe-badge');
		var initialPass = String($wrap.data('initial-password') || '');
		var val = $input.val().trim();

		$badge.removeClass('align-qe-badge-set align-qe-badge-none align-qe-badge-new align-qe-badge-remove');

		if (val.length > 0) {
			if (val === initialPass) {
				$badge.addClass('align-qe-badge-set').text('Password Set');
			} else {
				$badge.addClass('align-qe-badge-new').text('New Password');
			}
		} else {
			if (initialPass.length > 0) {
				$badge.addClass('align-qe-badge-remove').text('Will Remove on Save');
			} else {
				$badge.addClass('align-qe-badge-none').text('No Password');
			}
		}
	});

})(jQuery);
