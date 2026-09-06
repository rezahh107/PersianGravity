const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );

const scanner = require( '../../assets/js/pgr-structured-scanner-core.js' );

const outputs = scanner.SAYAD_V01_OUTPUTS;

function payload( values, separator = '\n' ) {
	return values.join( separator );
}

function validValues( suffix = '' ) {
	return [
		'01' + suffix,
		'1' + suffix,
		'0000000000' + suffix,
		'IR000000000000000000000000' + suffix,
		'00-000' + suffix,
		'000000' + suffix,
		'0000000000000000' + suffix,
	];
}

function descriptors() {
	return outputs.map( ( output, index ) => ( {
		id: String( 10 + index ),
		type: index % 2 === 0 ? 'text' : 'hidden',
		displayOnly: false,
	} ) );
}

function mappings() {
	const result = {};
	outputs.forEach( ( output, index ) => {
		result[ output ] = String( 10 + index );
	} );
	return result;
}

function applyForTest( state, plan ) {
	if ( ! plan.ok ) {
		return state;
	}
	const next = { ...state };
	plan.updates.forEach( ( update ) => {
		next[ update.targetId ] = update.value;
	} );
	return next;
}

test( 'valid synthetic seven-segment payload returns ordered named outputs', () => {
	const result = scanner.parseScan( payload( validValues() ), 'sayad_v01' );

	assert.equal( result.ok, true );
	assert.deepEqual( result.outputs, outputs );
	assert.equal( result.data.qr_version, '01' );
	assert.equal( result.data.owner_identifier, '0000000000' );
	assert.equal( result.data.sayad_id, '0000000000000000' );
} );

test( 'LF payload is parsed deterministically', () => {
	const result = scanner.parseScan( payload( validValues(), '\n' ), 'sayad_v01' );

	assert.equal( result.ok, true );
	assert.equal( result.data.bank_branch, '00-000' );
} );

test( 'CRLF payload is normalized to the same seven segments', () => {
	const result = scanner.parseScan( payload( validValues(), '\r\n' ), 'sayad_v01' );

	assert.equal( result.ok, true );
	assert.equal( result.data.cheque_serial, '000000' );
} );

test( 'Persian digits are normalized to ASCII without losing leading zeroes', () => {
	const values = validValues();
	values[ 2 ] = '۰۰۰۱۲۳۴۵۶۷';
	values[ 6 ] = '۰۰۰۰۰۰۰۰۰۰۰۰۰۰۰۱';

	const result = scanner.parseScan( payload( values ), 'sayad_v01' );

	assert.equal( result.ok, true );
	assert.equal( result.data.owner_identifier, '0001234567' );
	assert.equal( result.data.sayad_id, '0000000000000001' );
} );

test( 'Arabic digits are normalized to ASCII without numeric coercion', () => {
	const values = validValues();
	values[ 0 ] = '٠١';
	values[ 5 ] = '٠٠٠١٢٣';

	const result = scanner.parseScan( payload( values ), 'sayad_v01' );

	assert.equal( result.ok, true );
	assert.equal( result.data.qr_version, '01' );
	assert.equal( result.data.cheque_serial, '000123' );
} );

test( 'outer whitespace is trimmed while meaningful internal content is preserved', () => {
	const values = validValues();
	values[ 4 ] = '  00 - 000  ';

	const result = scanner.parseScan(
		'\n  ' + payload( values ) + '  \n',
		'sayad_v01'
	);

	assert.equal( result.ok, true );
	assert.equal( result.data.bank_branch, '00 - 000' );
} );

test( 'six segments fail with INVALID_SEGMENT_COUNT', () => {
	const result = scanner.parseScan( payload( validValues().slice( 0, 6 ) ), 'sayad_v01' );

	assert.deepEqual( result, {
		ok: false,
		code: 'INVALID_SEGMENT_COUNT',
		expected: 7,
		received: 6,
	} );
} );

test( 'eight segments fail with INVALID_SEGMENT_COUNT', () => {
	const result = scanner.parseScan( payload( validValues().concat( 'extra' ) ), 'sayad_v01' );

	assert.deepEqual( result, {
		ok: false,
		code: 'INVALID_SEGMENT_COUNT',
		expected: 7,
		received: 8,
	} );
} );

test( 'an empty required segment fails with EMPTY_SEGMENT', () => {
	const values = validValues();
	values[ 3 ] = '   ';

	const result = scanner.parseScan( payload( values ), 'sayad_v01' );

	assert.equal( result.ok, false );
	assert.equal( result.code, 'EMPTY_SEGMENT' );
	assert.equal( result.segment, 4 );
	assert.equal( Object.prototype.hasOwnProperty.call( result, 'payload' ), false );
} );

test( 'unknown profile is rejected without parsing', () => {
	const result = scanner.parseScan( payload( validValues() ), 'unknown_profile' );

	assert.deepEqual( result, { ok: false, code: 'PROFILE_NOT_FOUND' } );
} );

test( 'valid mapping produces one complete updateSet', () => {
	const parsed = scanner.parseScan( payload( validValues() ), 'sayad_v01' );
	const plan = scanner.buildUpdatePlan( parsed, mappings(), descriptors(), '99' );

	assert.equal( plan.ok, true );
	assert.equal( plan.updates.length, 7 );
	assert.deepEqual(
		plan.updates.map( ( update ) => update.targetId ),
		[ '10', '11', '12', '13', '14', '15', '16' ]
	);
} );

test( 'missing target descriptor fails before any updateSet is returned', () => {
	const parsed = scanner.parseScan( payload( validValues() ), 'sayad_v01' );
	const incompleteDescriptors = descriptors().filter( ( descriptor ) => descriptor.id !== '13' );
	const plan = scanner.buildUpdatePlan( parsed, mappings(), incompleteDescriptors, '99' );

	assert.equal( plan.ok, false );
	assert.equal( plan.code, 'TARGET_NOT_FOUND' );
	assert.equal( Object.prototype.hasOwnProperty.call( plan, 'updates' ), false );
} );

test( 'unsupported target type fails', () => {
	const parsed = scanner.parseScan( payload( validValues() ), 'sayad_v01' );
	const targets = descriptors();
	targets[ 3 ] = { id: '13', type: 'number', displayOnly: false };

	const plan = scanner.buildUpdatePlan( parsed, mappings(), targets, '99' );

	assert.equal( plan.ok, false );
	assert.equal( plan.code, 'UNSUPPORTED_TARGET' );
} );

test( 'duplicate destination mapping fails', () => {
	const parsed = scanner.parseScan( payload( validValues() ), 'sayad_v01' );
	const duplicateMappings = mappings();
	duplicateMappings.sayad_id = duplicateMappings.iban;

	const plan = scanner.buildUpdatePlan( parsed, duplicateMappings, descriptors(), '99' );

	assert.equal( plan.ok, false );
	assert.equal( plan.code, 'DUPLICATE_TARGET' );
	assert.equal( Object.prototype.hasOwnProperty.call( plan, 'updates' ), false );
} );

test( 'self-target mapping fails', () => {
	const parsed = scanner.parseScan( payload( validValues() ), 'sayad_v01' );
	const selfMappings = mappings();
	selfMappings.qr_version = '99';
	const targets = descriptors().concat( { id: '99', type: 'text', displayOnly: false } );

	const plan = scanner.buildUpdatePlan( parsed, selfMappings, targets, '99' );

	assert.equal( plan.ok, false );
	assert.equal( plan.code, 'SELF_TARGET' );
} );

test( 'a late mapping failure never exposes a partial updateSet', () => {
	const parsed = scanner.parseScan( payload( validValues() ), 'sayad_v01' );
	const targets = descriptors().filter( ( descriptor ) => descriptor.id !== '16' );

	const plan = scanner.buildUpdatePlan( parsed, mappings(), targets, '99' );

	assert.equal( plan.ok, false );
	assert.equal( plan.code, 'TARGET_NOT_FOUND' );
	assert.equal( Object.prototype.hasOwnProperty.call( plan, 'updates' ), false );
} );

test( 'second valid scan builds a complete replacement updateSet', () => {
	const first = scanner.parseScan( payload( validValues( '-A' ) ), 'sayad_v01' );
	const second = scanner.parseScan( payload( validValues( '-B' ) ), 'sayad_v01' );

	const firstPlan = scanner.buildUpdatePlan( first, mappings(), descriptors(), '99' );
	const secondPlan = scanner.buildUpdatePlan( second, mappings(), descriptors(), '99' );

	assert.equal( firstPlan.ok, true );
	assert.equal( secondPlan.ok, true );
	assert.equal( secondPlan.updates.length, firstPlan.updates.length );
	assert.ok( secondPlan.updates.every( ( update ) => update.value.endsWith( '-B' ) ) );
} );

test( 'failed second scan cannot modify state produced by the first scan', () => {
	const first = scanner.parseScan( payload( validValues() ), 'sayad_v01' );
	const firstPlan = scanner.buildUpdatePlan( first, mappings(), descriptors(), '99' );
	const afterFirst = applyForTest( {}, firstPlan );

	const invalidSecond = scanner.parseScan( payload( validValues().slice( 0, 6 ) ), 'sayad_v01' );
	const failedPlan = scanner.buildUpdatePlan( invalidSecond, mappings(), descriptors(), '99' );
	const afterFailedSecond = applyForTest( afterFirst, failedPlan );

	assert.equal( failedPlan.ok, false );
	assert.equal( Object.prototype.hasOwnProperty.call( failedPlan, 'updates' ), false );
	assert.deepEqual( afterFailedSecond, afterFirst );
} );

test( 'Enter keeps one through six segments in progress', () => {
	for ( let count = 1; count <= 6; count++ ) {
		const raw = payload( validValues().slice( 0, count ) );
		const decision = scanner.decideCaptureAction( raw, 'sayad_v01', 'enter' );

		assert.equal( decision.action, 'continue' );
		assert.deepEqual( decision.parsed, scanner.parseScan( raw, 'sayad_v01' ) );
	}
} );

test( 'Enter finalizes only when the existing parser accepts the complete payload', () => {
	const raw = payload( validValues() );
	const decision = scanner.decideCaptureAction( raw, 'sayad_v01', 'enter' );

	assert.equal( decision.action, 'finalize' );
	assert.equal( decision.parsed.ok, true );
	assert.deepEqual( decision.parsed, scanner.parseScan( raw, 'sayad_v01' ) );
} );

test( 'Tab explicitly finalizes incomplete input so the invalid result can be surfaced', () => {
	const raw = payload( validValues().slice( 0, 4 ) );
	const decision = scanner.decideCaptureAction( raw, 'sayad_v01', 'tab' );

	assert.equal( decision.action, 'finalize' );
	assert.equal( decision.parsed.ok, false );
	assert.equal( decision.parsed.code, 'INVALID_SEGMENT_COUNT' );
} );

test( 'idle leaves incomplete keyboard accumulation untouched', () => {
	const raw = payload( validValues().slice( 0, 5 ) );
	const decision = scanner.decideCaptureAction( raw, 'sayad_v01', 'idle' );

	assert.equal( decision.action, 'continue' );
	assert.equal( decision.parsed.ok, false );
} );

test( 'idle may finalize a complete parser-valid payload', () => {
	const raw = payload( validValues() );
	const decision = scanner.decideCaptureAction( raw, 'sayad_v01', 'idle' );

	assert.equal( decision.action, 'finalize' );
	assert.equal( decision.parsed.ok, true );
} );

test( 'paste is explicit finalization and preserves parser failure semantics', () => {
	const raw = payload( validValues().slice( 0, 6 ), '\r\n' );
	const decision = scanner.decideCaptureAction( raw, 'sayad_v01', 'paste' );

	assert.equal( decision.action, 'finalize' );
	assert.equal( decision.parsed.ok, false );
	assert.deepEqual( decision.parsed, scanner.parseScan( raw, 'sayad_v01' ) );
} );

test( 'delimiter-less collapsed payload remains rejected by the parser', () => {
	const raw = validValues().join( '' );
	const result = scanner.parseScan( raw, 'sayad_v01' );

	assert.equal( result.ok, false );
	assert.equal( result.code, 'INVALID_SEGMENT_COUNT' );
} );
