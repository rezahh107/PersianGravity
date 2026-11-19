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

	function separatorFor(format) {
		if (format.indexOf('_dash') !== -1) {
			return '-';
		}
		if (format.indexOf('_dot') !== -1) {
			return '.';
		}
		return '/';
	}

	function pad(number, size) {
		var text = String(number);
		while (text.length < size) {
			text = '0' + text;
		}
		return text;
	}

	function toCanonical(value, format) {
		var parts = normalizeDigits(value).trim().split(separatorFor(format));
		if (parts.length !== 3) {
			return null;
		}

		var year;
		var month;
		var day;

		if (format === 'mdy') {
			year = parts[2];
			month = parts[0];
			day = parts[1];
		} else if (format.indexOf('dmy') === 0) {
			year = parts[2];
			month = parts[1];
			day = parts[0];
		} else {
			year = parts[0];
			month = parts[1];
			day = parts[2];
		}

		if (!/^\d{1,4}$/.test(year) || !/^\d{1,2}$/.test(month) || !/^\d{1,2}$/.test(day)) {
			return null;
		}

		return pad(year, 4) + '-' + pad(month, 2) + '-' + pad(day, 2);
	}

	function showError(input, message) {
		var container = input.closest('.gfield') || input.parentNode;
		var existing = container.querySelector('.pgr-jalali-range-error');
		if (!existing) {
			existing = document.createElement('div');
			existing.className = 'gfield_description validation_message pgr-jalali-range-error';
			container.appendChild(existing);
		}
		existing.textContent = message;
		input.setAttribute('aria-invalid', 'true');
	}

	function clearError(input) {
		var container = input.closest('.gfield') || input.parentNode;
		var existing = container.querySelector('.pgr-jalali-range-error');
		if (existing) {
			existing.parentNode.removeChild(existing);
		}
		input.setAttribute('aria-invalid', 'false');
	}

	function checkInput(input) {
		var value = input.value;
		if (!value || !value.trim()) {
			clearError(input);
			return true;
		}

		var canonical = toCanonical(value, input.getAttribute('data-pgr-jalali-format') || 'ymd_slash');
		var min = input.getAttribute('data-pgr-jalali-min') || '';
		var max = input.getAttribute('data-pgr-jalali-max') || '';

		if (canonical === null) {
			clearError(input);
			return true;
		}

		if ((min && canonical < min) || (max && canonical > max)) {
			showError(input, 'Please enter a date between ' + (min || '…') + ' and ' + (max || '…') + '.');
			return false;
		}

		clearError(input);
		return true;
	}

	document.addEventListener('submit', function (event) {
		var form = event.target;
		if (!form || !form.querySelectorAll) {
			return;
		}

		var inputs = form.querySelectorAll('input[data-pgr-jalali-min], input[data-pgr-jalali-max]');
		var valid = true;

		Array.prototype.forEach.call(inputs, function (input) {
			if (!checkInput(input)) {
				valid = false;
			}
		});

		if (!valid) {
			event.preventDefault();
			event.stopPropagation();
		}
	}, true);

	document.addEventListener('change', function (event) {
		var input = event.target;
		if (input && input.matches && input.matches('input[data-pgr-jalali-min], input[data-pgr-jalali-max]')) {
			checkInput(input);
		}
	});

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = { toCanonical: toCanonical };
	}
}());
