'use strict';
const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const vm = require( 'node:vm' );
const source = fs.readFileSync( require( 'node:path' ).join( __dirname, '../../assets/js/pgr-admin-help.js' ), 'utf8' );
function run( clipboard ) {
	const code = { textContent: '{pgr_jdate_created_date}' };
	const status = { textContent: '' };
	const sample = { querySelector: ( selector ) => selector === 'pre code' ? code : status };
	const button = { dataset: { pgrCopySuccess: 'Copied.', pgrCopyFailure: 'Copy failed.' }, closest: ( selector ) => selector.startsWith( '.pgr-help' ) ? button : sample, focus() {} };
	let listener;
	const document = { activeElement: button, body: { appendChild() {} }, createElement: () => ( { value: '', style: {}, setAttribute() {}, select() {}, remove() {} } ), execCommand: () => false, addEventListener: ( name, callback ) => { if ( name === 'click' ) { listener = callback; } } };
	vm.runInNewContext( source, { navigator: { clipboard }, document } );
	listener( { target: button } );
	return status;
}
test( 'copy uses the unmodified tag', async () => {
	let received;
	const status = run( { writeText: async ( s ) => { received = s; } } );
	await new Promise( ( ok ) => setImmediate( ok ) );
	assert.equal( received, '{pgr_jdate_created_date}' );
	assert.equal( status.textContent, 'Copied.' );
} );
test( 'copy denial exposes failure instead of fake success', async () => {
	const status = run( { writeText: async () => { throw Error( 'denied' ); } } );
	await new Promise( ( ok ) => setImmediate( ok ) );
	assert.equal( status.textContent, 'Copy failed.' );
} );
