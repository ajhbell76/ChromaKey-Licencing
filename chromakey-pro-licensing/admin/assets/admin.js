jQuery(function ($) {
	$(document).on('click', '.ckp-reissue-key', function () {
		return confirm(
			'This will generate a new licence key. The old key will stop working immediately.\n\n' +
			'The customer will be emailed their new key.\n\nContinue?'
		);
	});

	$(document).on('click', '.ckp-copy-key', function () {
		var key = $(this).data('key');
		var $btn = $(this);
		if (navigator.clipboard) {
			navigator.clipboard.writeText(key).then(function () {
				$btn.text('Copied!');
				setTimeout(function () { $btn.text('Copy'); }, 2000);
			});
		} else {
			// Fallback for older browsers.
			var $temp = $('<input>').val(key).appendTo('body').select();
			document.execCommand('copy');
			$temp.remove();
			$btn.text('Copied!');
			setTimeout(function () { $btn.text('Copy'); }, 2000);
		}
	});
});
