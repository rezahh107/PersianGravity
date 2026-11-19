'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');

// The browser script registers DOM listeners at load time, so the pure
// canonicalization helper is mirrored here to keep this test DOM-free.
function toCanonical(value, format) {
	const map = { '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9' };
	const normalized = String(value).replace(/[۰-۹]/g, (digit) => map[digit]).trim();
	const separator = format.includes('_dash') ? '-' : (format.includes('_dot') ? '.' : '/');
	const parts = normalized.split(separator);
	if (parts.length !== 3) {
		return null;
	}
	let [year, month, day] = parts;
	if (format === 'mdy') {
		[month, day, year] = parts;
	} else if (format.startsWith('dmy')) {
		[day, month, year] = parts;
	}
	return `${year.padStart(4, '0')}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
}

test('canonicalizes every supported Jalali input format', () => {
	assert.equal(toCanonical('1404/05/10', 'ymd_slash'), '1404-05-10');
	assert.equal(toCanonical('1404-5-1', 'ymd_dash'), '1404-05-01');
	assert.equal(toCanonical('10.05.1404', 'dmy_dot'), '1404-05-10');
	assert.equal(toCanonical('05/10/1404', 'mdy'), '1404-05-10');
	assert.equal(toCanonical('۱۴۰۴/۰۵/۱۰', 'ymd_slash'), '1404-05-10');
});

test('rejects values with the wrong separator', () => {
	assert.equal(toCanonical('1404-05-10', 'ymd_slash'), null);
});

test('canonical strings compare in calendar order', () => {
	assert.ok(toCanonical('1404/05/10', 'ymd_slash') < toCanonical('1404/11/01', 'ymd_slash'));
	assert.ok(toCanonical('1399/12/30', 'ymd_slash') < toCanonical('1400/01/01', 'ymd_slash'));
});
