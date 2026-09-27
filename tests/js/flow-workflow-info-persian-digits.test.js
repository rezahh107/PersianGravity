const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const adapter = require( '../../assets/js/pgr-flow-entry-detail-persian-digits.js' );

test( 'ASCII digits are shaped as Persian glyphs without parsing the value', () => {
	assert.equal( adapter.shapeDigits( '11:04 / Entry 56' ), '۱۱:۰۴ / Entry ۵۶' );
	assert.equal( adapter.shapeDigits( '۱۴۰۵/۰۶/۲۵ در 11:04 ق.ظ' ), '۱۴۰۵/۰۶/۲۵ در ۱۱:۰۴ ق.ظ' );
	assert.equal( adapter.shapeDigits( 'already ۱۲۳' ), 'already ۱۲۳' );
	assert.equal( adapter.shapeDigits( 'user-authored text without digits' ), 'user-authored text without digits' );
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

test( 'only qualified field text nodes change while excluded descendants and machine state stay native', () => {
	const attributes = {
		id: 'entry-56',
		href: 'https://example.test/?page=gf_entries&id=7&lid=56',
		value: '56',
		'data-entry-id': '56',
		'aria-label': 'Entry 56',
	};
	const controls = {
		inputValue: '56',
		selectValue: '7',
		textareaValue: 'note 56',
	};
	const visibleParent = { closest: () => null };
	const nodes = [
		{ nodeValue: 'Entry ID: 56', parentElement: visibleParent },
		{ nodeValue: ' at 11:04', parentElement: visibleParent },
		{ nodeValue: 'plain author text', parentElement: visibleParent },
		{ nodeValue: 'hidden attr 56', parentElement: { closest: () => ( {} ) } },
		{ nodeValue: 'aria hidden 56', parentElement: { closest: () => ( {} ) } },
		{ nodeValue: 'editable 56', parentElement: { closest: () => ( {} ) } },
		{ nodeValue: 'textarea 56', parentElement: { closest: () => ( {} ) } },
		{ nodeValue: 'select 56', parentElement: { closest: () => ( {} ) } },
		{ nodeValue: 'option 56', parentElement: { closest: () => ( {} ) } },
		{ nodeValue: 'script 56', parentElement: { closest: () => ( {} ) } },
		{ nodeValue: 'style 56', parentElement: { closest: () => ( {} ) } },
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
		{
			nodeValue: 'visibility hidden 56',
			parentElement: {
				closest: () => null,
				ownerDocument: {
					defaultView: {
						getComputedStyle: () => ( { display: 'block', visibility: 'hidden' } ),
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

	const machineBefore = JSON.stringify( { attributes, controls } );
	assert.equal( adapter.run( doc ), 2 );
	assert.equal( nodes[ 0 ].nodeValue, 'Entry ID: ۵۶' );
	assert.equal( nodes[ 1 ].nodeValue, ' at ۱۱:۰۴' );
	assert.equal( nodes[ 2 ].nodeValue, 'plain author text' );
	for ( const node of nodes.slice( 3 ) ) {
		assert.match( node.nodeValue, /56$/ );
	}
	assert.equal( JSON.stringify( { attributes, controls } ), machineBefore );
} );

test( 'browser adapter source keeps the exact root and excluded descendant contract', () => {
	const fs = require( 'node:fs' );
	const path = require( 'node:path' );
	const source = fs.readFileSync(
		path.join( __dirname, '../../assets/js/pgr-flow-entry-detail-persian-digits.js' ),
		'utf8'
	);

	assert.match( source, /#gravityflow-status-box-container > #submitcomment > #minor-publishing\.gravityflow-status-box/ );
	assert.match( source, /\.gravityflow-status-box-field/ );
	for ( const token of [ 'script', 'style', 'textarea', 'select', 'option', 'template', 'noscript', '[hidden]', '[aria-hidden="true"]', '[contenteditable="true"]' ] ) {
		assert.ok( source.includes( token ), `missing excluded descendant: ${ token }` );
	}
	assert.doesNotMatch( source, /querySelectorAll\(\s*['"]\*['"]\s*\)/ );
	assert.doesNotMatch( source, /setAttribute|\.value\s*=|\.href\s*=/ );
} );
