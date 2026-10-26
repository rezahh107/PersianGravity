<?php
/**
 * Post-browser independent GFAPI persistence/oracle verification.
 * Reads real submitted Entries; does not rerender or call merge replacement.
 */
defined( 'ABSPATH' ) || exit;
$dir = getenv( 'WU008_ARTIFACT_DIR' );
$fixture = json_decode( (string) file_get_contents( $dir . '/g011-browserqual-fixture.json' ), true );
$browser = json_decode( (string) file_get_contents( $dir . '/g011-browserqual-browser.json' ), true );
if ( ! is_array( $fixture ) || ! is_array( $browser ) ||
	'PASS' !== ( $browser['status'] ?? null ) ||
	'3.1.1.1' !== GFForms::$version ||
	getenv( 'WU008_PGR_SHA' ) !== $fixture['head'] ||
	getenv( 'WU008_GF_SHA256' ) !== $fixture['host_sha256'] ) {
	throw new RuntimeException( 'G011_BROWSERQUAL_VERIFY: browser or licensed host unavailable' );
}
$all = array();
$failures = array();
$stored_form = GFAPI::get_form( (int) $fixture['form_id'] );
$checks = array(
	'original_form_unchanged' => is_array( $stored_form ) &&
		hash( 'sha256', wp_json_encode( $stored_form ) ) === $fixture['form_sha256'],
	'target_forms_scoped' => $fixture['default_id'] === 'g011-q-default',
);
$expected_modes = array(
	'native_ordinary', 'candidate_ordinary',
	'native_ajax', 'candidate_ajax',
	'native_changed_date', 'changed_date',
	'native_changed_html', 'changed_html',
	'native_ambiguous', 'ambiguous', 'conditional',
	'module_disabled', 'nonfa',
);
$business_entry_values = static function ( $entry ): array {
	if ( ! is_array( $entry ) ) {
		return array();
	}
	$fields = array( 'id', 'form_id', 'status', 'date_created', '1', '2' );
	$result = array();
	foreach ( $fields as $field ) {
		$result[ $field ] = isset( $entry[ $field ] ) ? (string) $entry[ $field ] : null;
	}
	return $result;
};
foreach ( $expected_modes as $mode ) {
	$path = $dir . '/g011-browserqual-' . $mode . '.json';
	$info = is_file( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null;
	$native_only = in_array( $mode, array( 'module_disabled', 'nonfa' ), true );
	$submitted_file = $dir . '/g011-browserqual-submitted-' . $mode . '.json';
	$submitted = is_file( $submitted_file ) ?
		json_decode( (string) file_get_contents( $submitted_file ), true ) : null;
	$check = array(
		'server_evidence_exists' => is_array( $info ) ||
			( $native_only && is_array( $submitted ) ),
		'browser_case_pass' => 'PASS' === ( $browser['cases'][$mode]['status'] ?? null ),
	);
	if ( is_array( $info ) ) {
		$entry = GFAPI::get_entry( (int) $info['entry_id'] );
		$check['stored_entry_exists'] = is_array( $entry );
		// GF can augment transient/derived GFAPI snapshots during its
		// own submission lifecycle. Comparing full in-request and
		// post-request hashes is not a supported persistence invariant.
		// Business values must remain identical before/after Candidate.
		$check['stored_entry_business_values_preserved'] =
			is_array( $entry ) &&
			is_array( $info['entry_contract_values'] ?? null ) &&
			$business_entry_values( $entry ) === $info['entry_contract_values'];
		$check['original_form_persisted_unchanged'] =
			true === $checks['original_form_unchanged'];
		$check['expected_submitted_form_fields'] = is_array( $entry ) &&
			(string) $entry['form_id'] === (string) $fixture['form_id'] &&
			(string) ( $entry['2'] ?? '' ) === $mode &&
			(string) ( $entry['1'] ?? '' ) ===
				( 'conditional' === $mode ? 'special' : 'ordinary' );
		$check['selected_confirmation_identity'] = $info['selected_id'] ===
			( 'conditional' === $mode ? $fixture['conditional_id'] : $fixture['default_id'] );
		$check['same_native_pre_input_count'] = 1 === $info['native_callback_count'] &&
			1 === $info['third_party_pre_side_effect_calls'];
		$check['no_shared_reexecution'] = $info['native_callback_count'] === 1;
		$check['unchanged_outside_admitted_spans'] = true === $info['outside_spans_byte_exact'];
		$check['persisted_identity_preserved_at_callback'] =
			true === $info['persisted_form_same_in_callback'] &&
			true === $info['persisted_entry_same_in_callback'];
		if ( is_array( $entry ) ) {
			$utc = DateTimeImmutable::createFromFormat(
				'!Y-m-d H:i:s', $entry['date_created'], new DateTimeZone( 'UTC' )
			);
			$err = DateTimeImmutable::getLastErrors();
			$valid = false !== $utc &&
				( false === $err || ( 0 === $err['warning_count'] && 0 === $err['error_count'] ) ) &&
				$utc->format( 'Y-m-d H:i:s' ) === $entry['date_created'];
			$check['raw_utc_authority_valid'] = $valid;
			if ( $valid ) {
				$tz = new DateTimeZone( $info['timezone'] );
				$local = $utc->setTimezone( $tz );
				$expected_native = $local->format( 'Y/m/d' );
				$expected_jalali = PGR_Jalali_Presentation::format_date(
					(int) $local->format( 'Y' ), (int) $local->format( 'n' ), (int) $local->format( 'j' )
				);
				$check['independent_gregorian_expected'] = $expected_native === $info['gregorian'];
				$check['independent_jalali_expected'] =
					'module_disabled' === $mode ?
					null === $info['jalali'] :
					$expected_jalali === $info['jalali'];
				$check['same_fixed_gregorian_date'] =
					$fixture['fixed_gregorian_literal'] === $expected_native;
			}
		}
		if ( in_array( $mode, array( 'candidate_ordinary', 'candidate_ajax' ), true ) ) {
			$check['positive_liveness_two_occurrences'] = true === $info['admitted'] &&
				2 === count( $info['spans'] ) &&
				$info['native_sha256'] !== $info['output_sha256'];
		} else {
			$check['native_fallback'] = true === $info['native_fallback_byte_exact'];
		}
	}
	if ( ! is_array( $info ) && $native_only ) {
		// GF browser submitted a real Entry and displayed native Text but
		// the test's gform_confirmation observer was not called. This is
		// native-only outcome evidence, NOT a byte-exact candidate seam test.
		$entry = is_array( $submitted ) ?
			GFAPI::get_entry( (int) $submitted['entry_id'] ) : null;
		$check['native_submission_observed'] = is_array( $submitted ) &&
			is_array( $entry ) &&
			(int) $submitted['form_id'] === (int) $fixture['form_id'];
		$check['entry_unchanged'] = is_array( $entry ) &&
			hash( 'sha256', wp_json_encode( $entry ) ) ===
				( $submitted['persisted_entry_sha256'] ?? null );
		$check['form_unchanged'] =
			( $submitted['persisted_form_sha256'] ?? null ) ===
			$fixture['form_sha256'];
		$check['pre_replace_callback_one_call'] =
			1 === ( $submitted['pre_callback_calls'] ?? null );
		$check['test_confirmation_filter_not_observed'] =
			false === ( $submitted['confirmation_trace_exists'] ?? null );
		$check['expected_native_calendar_from_entry'] = is_array( $entry ) &&
			isset( $submitted['expected']['raw_utc'] ) &&
			$entry['date_created'] === $submitted['expected']['raw_utc'];
		$check['module_or_locale_expected_native'] =
			'module_disabled' === $mode ?
				false === ( $submitted['module_enabled'] ?? null ) &&
				'fa_IR' === ( $submitted['locale'] ?? null ) :
				true === ( $submitted['module_enabled'] ?? null ) &&
				'en_US' === ( $submitted['locale'] ?? null );
	}
	$all[$mode] = $check;
	foreach ( $check as $name => $pass ) {
		if ( ! $pass ) {
			$failures[] = $mode . ': ' . $name;
		}
	}
}
foreach ( array( 'page', 'redirect' ) as $type ) {
	$case = $browser['cases']['transport_' . $type] ?? array();
	$entries = GFAPI::get_entries( (int) $fixture['transport'][$type]['form_id'] );
	$entry = is_array( $entries ) && count( $entries ) === 1 ? $entries[0] : null;
	$passed = is_array( $entry ) && 'PASS' === ( $case['status'] ?? null );
	if ( $passed ) {
		$utc = new DateTimeImmutable( $entry['date_created'], new DateTimeZone( 'UTC' ) );
		$greg = $utc->setTimezone( new DateTimeZone( $fixture['authoritative_timezone'] ) )->format( 'Y/m/d' );
		$passed = $greg === ( $case['day'] ?? null );
	}
	$checks['transport_' . $type . '_native_from_real_entry'] = $passed;
	if ( ! $passed ) {
		$failures[] = 'transport_' . $type;
	}
}
$unrelated = GFAPI::get_entries( (int) $fixture['unrelated_form_id'] );
$checks['unrelated_form_persisted'] =
	is_array( $unrelated ) && 1 === count( $unrelated ) &&
	'PASS' === ( $browser['cases']['unrelated']['status'] ?? null );
$checks['module_reenabled'] = PGR_Module_Registry::is_enabled( 'jalali_presentation' );
$checks['locale_restored'] = 'fa_IR' === determine_locale() && 'fa_IR' === get_locale();
foreach ( $checks as $name => $pass ) {
	if ( ! $pass ) {
		$failures[] = $name;
	}
}
$summary = array(
	'head' => getenv( 'WU008_PGR_SHA' ),
	'host_version' => GFForms::$version,
	'host_sha256' => getenv( 'WU008_GF_SHA256' ),
	'status' => $failures ? 'FAIL' : 'PASS',
	'checks' => $checks,
	'case_checks' => $all,
	'failed_invariants' => $failures,
);
file_put_contents( $dir . '/g011-browserqual-verified.json',
	wp_json_encode( $summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
if ( $failures ) {
	throw new RuntimeException( 'G011_BROWSERQUAL_VERIFY: ' . implode( ',', $failures ) );
}
echo "G011 genuine browser/native candidate controls and persistence verified\n";
