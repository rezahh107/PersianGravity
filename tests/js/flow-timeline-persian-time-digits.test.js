const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const adapter = require( '../../assets/js/pgr-flow-timeline-persian-time-digits.js' );

test( 'only ASCII clock tokens are shaped while arbitrary digits and Persian digits stay unchanged', () => {
	assert.equal( adapter.shapeTimeTokens( '۱۴۰۹/۰۱/۰۱ در 12:01 ق.ظ' ), '۱۴۰۹/۰۱/۰۱ در ۱۲:۰۱ ق.ظ' );
	assert.equal( adapter.shapeTimeTokens( '۱۴۰۸/۱۲/۳۰ در 11:59 ب.ظ' ), '۱۴۰۸/۱۲/۳۰ در ۱۱:۵۹ ب.ظ' );
	assert.equal( adapter.shapeTimeTokens( 'ID 2030 at 23:07 and 1405/01/01' ), 'ID 2030 at ۲۳:۰۷ and 1405/01/01' );
	assert.equal( adapter.shapeTimeTokens( 'already ۱۲:۰۱ and body 2026-03-20' ), 'already ۱۲:۰۱ and body 2026-03-20' );
	assert.equal( adapter.shapeTimeTokens( 1201 ), 1201 );
} );

test( 'missing exact Timeline headers fails closed', () => {
	const doc = {
		querySelectorAll( selector ) {
			assert.equal( selector, adapter.HEADER_SELECTOR );
			return [];
		},
	};

	assert.equal( adapter.run( doc ), 0 );
} );

test( 'only exact header text nodes change while body text and machine values remain native', () => {
	const machine = {
		id: 'gravityflow-note-2030',
		href: 'https://example.test/?page=gf_entries&lid=56&at=12:01',
		value: '12:01',
		'data-created': '2030-03-20 20:31:00',
		'aria-label': 'Timeline 12:01',
	};
	const headerNodes = [
		{ nodeValue: '۱۴۰۹/۰۱/۰۱ در 12:01 ق.ظ', parentElement: { closest: () => null } },
		{ nodeValue: 'event 2030', parentElement: { closest: () => null } },
		{ nodeValue: 'hidden 11:59', parentElement: { closest: () => ( {} ) } },
	];
	const bodyNode = { nodeValue: 'User body 2026-03-20 at 12:01 must stay ASCII.' };
	let index = 0;
	const header = {};
	const doc = {
		defaultView: { NodeFilter: { SHOW_TEXT: 4 } },
		querySelectorAll( selector ) {
			assert.equal( selector, adapter.HEADER_SELECTOR );
			return [ header ];
		},
		createTreeWalker( receivedHeader, showText ) {
			assert.equal( receivedHeader, header );
			assert.equal( showText, 4 );
			return {
				nextNode() {
					return headerNodes[ index++ ] || null;
				},
			};
		},
	};

	const beforeMachine = JSON.stringify( machine );
	assert.equal( adapter.run( doc ), 1 );
	assert.equal( headerNodes[ 0 ].nodeValue, '۱۴۰۹/۰۱/۰۱ در ۱۲:۰۱ ق.ظ' );
	assert.equal( headerNodes[ 1 ].nodeValue, 'event 2030' );
	assert.equal( headerNodes[ 2 ].nodeValue, 'hidden 11:59' );
	assert.equal( bodyNode.nodeValue, 'User body 2026-03-20 at 12:01 must stay ASCII.' );
	assert.equal( JSON.stringify( machine ), beforeMachine );
} );

test( 'Entry Detail and inherited Print selectors are both exact header-only seams', () => {
	assert.match( adapter.HEADER_SELECTOR, /\.gravityflow-timeline > \.inside/ );
	assert.match( adapter.HEADER_SELECTOR, /#view-container > \.gravityflow-note/ );
	assert.doesNotMatch( adapter.HEADER_SELECTOR, /gravityflow-note-body(?:['", ]|$)/ );
	assert.match( adapter.HEADER_SELECTOR, /gravityflow-note-meta/ );
} );
