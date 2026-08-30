(function () {
	'use strict';

	var digitMap = {
		'۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4',
		'۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
		'٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4',
		'٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9'
	};

	function normalizeDigits(value) {
		return String(value).replace(/[۰-۹٠-٩]/g, function (digit) {
			return digitMap[digit] || digit;
		});
	}

	document.addEventListener('input', function (event) {
		var input = event.target;

		if (!input || !input.matches || !input.matches('[data-pgr-normalize-digits="1"]')) {
			return;
		}

		var normalized = normalizeDigits(input.value);
		if (normalized === input.value) {
			return;
		}

		var start = input.selectionStart;
		var end = input.selectionEnd;
		input.value = normalized;

		if (typeof start === 'number' && typeof end === 'number' && input.setSelectionRange) {
			input.setSelectionRange(start, end);
		}
	});
}());
