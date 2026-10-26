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
$previous_locale = get_option( 'WPLANG', '' );
foreach ( $expected_modes as $mode ) {
	$path = $dir . '/g011-browserqual-' . $mode . '.json';
	$info = is_file( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null;
	$check = array(
		'server_evidence_exists' => is_array( $info ),
		'browser_case_pass' => 'PASS' === ( $browser['cases'][$mode]['status'] ?? null ),
	);
	if ( is_array( $info ) ) {
		$entry = GFAPI::get_entry( (int) $info['entry_id'] );
		$check['stored_entry_exists'] = is_array( $entry );
		$check['stored_entry_byte_equivalent'] = is_array( $entry ) &&
			hash( 'sha256', wp_json_encode( $entry ) ) === $info['entry_snapshot_sha256'];
		$check['stored_form_byte_equivalent'] =
			$info['form_snapshot_sha256'] === $fixture['form_sha256'];
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
				$check['independent_jalali_expected'] = $expected_jalali === $info['jalali'];
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
