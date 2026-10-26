<?php
/**
 * Independent post-browser persisted source/date verification. Does NOT
 * trigger a confirmation render or shared merge-tag replacement.
 */
defined( 'ABSPATH' ) || exit;
$dir = getenv( 'WU008_ARTIFACT_DIR' );
$f = json_decode( (string) file_get_contents( $dir . '/g011-heterogeneous-browser-fixture.json' ), true );
$b = json_decode( (string) file_get_contents( $dir . '/g011-heterogeneous-browser.json' ), true );
if ( ! is_array( $f ) || ! is_array( $b ) || ( $b['status'] ?? null ) !== 'PASS' ||
	GFForms::$version !== '3.1.1.1' ||
	$f['gf_sha256'] !== getenv( 'WU008_GF_SHA256' ) ||
	$f['head'] !== getenv( 'WU008_PGR_SHA' ) ) {
	throw new RuntimeException( 'G011 heterogeneous browser final: exact host or browser preconditions failed' );
}
$form = GFAPI::get_form( (int) $f['form_id'] );
$form_ok = is_array( $form ) &&
	$f['form_confirmations_sha256'] === hash( 'sha256', wp_json_encode( $form['confirmations'] ) );
$checks = array( 'form_confirmations_persisted_unchanged' => $form_ok );
$cases = array();
foreach ( array( 'ordinary' => 'special', 'ajax' => 'normal' ) as $route => $selection ) {
	$obs = json_decode( (string) file_get_contents(
		$dir . '/g011-hetero-browser-' . $selection . '.json'
	), true );
	$entry = is_array( $obs ) ? GFAPI::get_entry( (int) $obs['entry_id'] ) : null;
	$expected_id = $selection === 'special' ? 'g011-het-conditional' : 'g011-het-default';
	$good = array(
		'actual_entry' => is_array( $entry ) && (int) $entry['form_id'] === (int) $f['form_id'],
		'selected_confirmation_id' => is_array( $obs ) &&
			$obs['selected_confirmation'] === $expected_id &&
			$b['cases'][$route]['selected_confirmation'] === $expected_id,
		'persisted_selector' => is_array( $entry ) && (string) $entry['1'] === $selection,
		'persisted_date_is_gregorian_field' => false,
		'actual_utc_date_preserved' => is_array( $entry ) &&
			(string) $entry['date_created'] === (string) ( $obs['raw_utc'] ?? '' ),
		'local_calendar_oracle' => false,
		'positive_browser_case' => $b['cases'][$route]['status'] === 'PASS',
		'one_shared_callback' => is_array( $obs ) && $obs['shared_merge_callback_count'] === 1,
		'unowned_bytes_exact' => is_array( $obs ) && true === $obs['checks']['byte_exact_unowned'],
	);
	if ( is_array( $entry ) && is_array( $obs ) ) {
		$parsed = GFCommon::parse_date( $entry['2'], 'mdy' );
		$good['persisted_date_is_gregorian_field'] =
			(int) ( $parsed['year'] ?? 0 ) === 2026 &&
			(int) ( $parsed['month'] ?? 0 ) === 3 &&
			(int) ( $parsed['day'] ?? 0 ) === 21;
		$instant = DateTimeImmutable::createFromFormat(
			'!Y-m-d H:i:s', $entry['date_created'], new DateTimeZone( 'UTC' )
		);
		if ( $instant instanceof DateTimeImmutable ) {
			$local = $instant->setTimezone( new DateTimeZone( $obs['wp_timezone'] ) );
			$jalali = PGR_Jalali_Presentation::format_date(
				(int) $local->format( 'Y' ), (int) $local->format( 'n' ),
				(int) $local->format( 'j' )
			);
			$good['local_calendar_oracle'] =
				$local->format( 'Y/m/d' ) === $obs['local_gregorian'] &&
				$jalali === $obs['expected_jalali'] &&
				$b['cases'][$route]['human']['full'] === $jalali;
		}
	}
	$cases[$route] = array(
		'status' => in_array( false, $good, true ) ? 'FAIL' : 'PASS',
		'checks' => $good, 'failed' => array_keys( array_filter(
			$good, static function ( $v ) { return ! $v; }
		) ),
		'actual_entry_id' => is_array( $entry ) ? (int) $entry['id'] : null,
	);
}
$passed = ! in_array( false, $checks, true ) &&
	! array_filter( $cases, static function ( $case ) { return $case['status'] !== 'PASS'; } );
file_put_contents( $dir . '/g011-heterogeneous-browser-verified.json',
	wp_json_encode( array(
		'status' => $passed ? 'PASS' : 'FAIL',
		'head' => getenv( 'WU008_PGR_SHA' ),
		'GF' => GFForms::$version,
		'GF_SHA256' => getenv( 'WU008_GF_SHA256' ),
		'checks' => $checks, 'cases' => $cases,
	), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
);
if ( ! $passed ) {
	throw new RuntimeException( 'G011 heterogeneous browser: post-submission Form/Entry oracle failed' );
}
echo "G011 heterogeneous browser persisted Entries and Form verified\n";