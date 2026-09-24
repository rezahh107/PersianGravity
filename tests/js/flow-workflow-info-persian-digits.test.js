const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const adapter = require( '../../assets/js/pgr-flow-entry-detail-persian-digits.js' );

test( 'ASCII digits are shaped as Persian glyphs without parsing the value', () => {
	assert.equal( adapter.shapeDigits( '11:04 / Entry 56' ), '۱۱:۰۴ / Entry ۵۶' );
	assert.equal( adapter.shapeDigits( '۱۴۰۵/۰۶/۲۵ در 11:04 ق.ظ' ), '۱۴۰۵/۰۶/۲۵ در ۱۱:۰۴ ق.ظ' );
	assert.equal( adapter.shapeDigits( 'already ۱۲۳' ), 'already ۱۲۳' );
	assert.equal( adapter.shapeDigits( 1104 ), 1104 );
} );

test( 'missing exact workflow-info container fails closed', () => {
	const doc = {
		querySelector( selector ) {
			assert.equal(
				selector,
				'#gravityflow-status-box-container > #submitcomment > #minor-publishing.gravityflow-status-box'
			);
			return null;
		},
	};

	assert.equal( adapter.run( doc ), 0 );
} );

test( 'only field text nodes change while link and machine attributes stay byte-for-byte native', () => {
	const attributes = {
		id: 'entry-56',
		href: 'https://example.test/?page=gf_entries&id=7&lid=56',
		value: '56',
		'data-entry-id': '56',
		'aria-label': 'Entry 56',
	};
	const nodes = [
		{ nodeValue: 'Entry ID: 56', parentElement: { closest: () => null } },
		{ nodeValue: ' at 11:04', parentElement: { closest: () => null } },
		{ nodeValue: 'hidden 56', parentElement: { closest: () => ( {} ) } },
		{
			nodeValue: 'css hidden 56',
			parentElement: {
				closest: () => null,
				ownerDocument: {
					defaultView: {
						getComputedStyle: () => ( { display: 'none', visibility: 'visible' } ),
					},
				},
				getClientRects: () => [],
			},
		},
	];
	let index = 0;
	const field = {};
	const root = {
		querySelectorAll( selector ) {
			assert.equal( selector, '.gravityflow-status-box-field' );
			return [ field ];
		},
	};
	const doc = {
		defaultView: { NodeFilter: { SHOW_TEXT: 4 } },
		querySelector: () => root,
		createTreeWalker( receivedField, showText ) {
			assert.equal( receivedField, field );
			assert.equal( showText, 4 );
			return {
				nextNode() {
					return nodes[ index++ ] || null;
				},
			};
		},
	};

	const before = JSON.stringify( attributes );
	assert.equal( adapter.run( doc ), 2 );
	assert.equal( nodes[ 0 ].nodeValue, 'Entry ID: ۵۶' );
	assert.equal( nodes[ 1 ].nodeValue, ' at ۱۱:۰۴' );
	assert.equal( nodes[ 2 ].nodeValue, 'hidden 56' );
	assert.equal( nodes[ 3 ].nodeValue, 'css hidden 56' );
	assert.equal( JSON.stringify( attributes ), before );
} );
