<?php
/** After browser submissions: independently verify actual persisted Form/Entry values. */
defined( 'ABSPATH' ) || exit;
$dir      = getenv( 'WU008_ARTIFACT_DIR' );
$fixture  = json_decode( (string) file_get_contents( $dir . '/g011-destination-fixture.json' ), true );
$enabled  = json_decode( (string) file_get_contents( $dir . '/g011-destination-browser-enabled.json' ), true );
$disabled = json_decode( (string) file_get_contents( $dir . '/g011-destination-browser-disabled.json' ), true );
if ( ! is_array( $fixture ) || ! is_array( $enabled ) || ! is_array( $disabled ) ) {
	throw new RuntimeException( 'G011 destination browser evidence missing.' );
}
$form     = GFAPI::get_form( (int) $fixture['form_id'] );
$baseline = GFAPI::get_entry( (int) $fixture['baseline_entry_id'] );
if ( $fixture['form_hash'] !== hash( 'sha256', wp_json_encode( $form ) ) ||
	$fixture['baseline_entry_hash'] !== hash( 'sha256', wp_json_encode( $baseline ) ) ) {
	throw new RuntimeException( 'G011 destination browser mutated persisted Form or baseline Entry.' );
}
$count = 0;
foreach ( array( $enabled, $disabled ) as $batch ) {
	if ( 'PASS' !== ( $batch['status'] ?? '' ) || 4 !== count( $batch['results'] ?? array() ) ) {
		throw new RuntimeException( 'G011 destination submission results are incomplete.' );
	}
	foreach ( $batch['results'] as $result ) {
		$entry = GFAPI::get_entry( (int) $result['entry_id'] );
		if ( ! is_array( $entry ) ||
			(int) $entry['form_id'] !== (int) $fixture['form_id'] ||
			( $entry['1'] ?? null ) !== $result['route'] ||
			( $entry['2'] ?? null ) !== '03/20/2026' ) {
			throw new RuntimeException( 'G011 destination submission stored unexpected Entry data.' );
		}
		++$count;
	}
}
if ( 8 !== $count ) {
	throw new RuntimeException( 'G011 destination submission count mismatch.' );
}
file_put_contents( $dir . '/g011-destination-storage.json', wp_json_encode( array(
	'head' => getenv( 'WU008_PGR_SHA' ), 'status' => 'PASS',
	'baseline_form_unchanged' => true, 'baseline_entry_unchanged' => true,
	'browser_submissions_verified' => $count,
), JSON_PRETTY_PRINT ) );
echo "G011 real destination Form/Entry persistence PASS\n";
