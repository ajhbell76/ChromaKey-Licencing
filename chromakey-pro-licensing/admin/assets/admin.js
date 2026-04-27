jQuery(function ($) {
	// Copy-to-clipboard for generated licence keys (added in WP-3).
	$(document).on('click', '.ckp-copy-key', function () {
		var key = $(this).data('key');
		navigator.clipboard.writeText(key).then(function () {
			// Temporary visual feedback.
			var $btn = $(this);
			$btn.text('Copied!');
			setTimeout(function () { $btn.text('Copy'); }, 2000);
		}.bind(this));
	});
});
