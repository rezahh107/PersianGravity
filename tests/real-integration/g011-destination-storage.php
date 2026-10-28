<?php
/** Fresh-process persisted baseline, then post-submission Form/Entry verification. */
defined( 'ABSPATH' ) || exit;
$dir     = getenv( 'WU008_ARTIFACT_DIR' );
$fixture = json_decode( (string) file_get_contents( $dir . '/g011-destination-fixture.json' ), true );
if ( ! is_array( $fixture ) || '3.1.1.1' !== GFForms::$version ) {
	throw new RuntimeException( 'G011 destination host or fixture mismatch.' );
}
$form     = GFAPI::get_form( (int) $fixture['form_id'] );
$baseline = GFAPI::get_entry( (int) $fixture['baseline_entry_id'] );
if ( ! is_array( $form ) || ! is_array( $baseline ) ) {
	throw new RuntimeException( 'G011 source Form/Entry unavailable.' );
}
$baseline_file = $dir . '/g011-destination-stored-baseline.json';
if ( 'baseline' === getenv( 'WU008_G011_STORAGE_PHASE' ) ) {
	// Reading after a fresh WordPress bootstrap avoids comparing the setup
	// request's mutable GFAPI object with rehydrated persisted metadata.
	file_put_contents( $baseline_file, wp_json_encode( array(
		'head' => getenv( 'WU008_PGR_SHA' ),
		'form' => $form, 'entry' => $baseline,
	), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	echo "G011 fresh-process persisted baseline recorded\n";
	return;
}
$reference = json_decode( (string) file_get_contents( $baseline_file ), true );
if ( ! is_array( $reference ) || $reference['head'] !== getenv( 'WU008_PGR_SHA' ) ) {
	throw new RuntimeException( 'G011 fresh-process baseline absent or wrong Head.' );
}
$old_form  = $reference['form'];
$old_entry = $reference['entry'];
$new_form  = json_decode( wp_json_encode( $form ), true );
$new_entry = json_decode( wp_json_encode( $baseline ), true );
$form_equal  = $old_form === $new_form;
$entry_equal = $old_entry === $new_entry;
if ( ! $form_equal || ! $entry_equal ) {
	$changed_keys = static function ( $old, $current ) {
		$keys = array_unique( array_merge( array_keys( $old ), array_keys( $current ) ) );
		return array_values( array_filter( $keys, static function ( $key ) use ( $old, $current ) {
			return ( $old[ $key ] ?? null ) !== ( $current[ $key ] ?? null );
		} ) );
	};
	file_put_contents( $dir . '/g011-destination-storage-diagnostic.json', wp_json_encode( array(
		'form_equal' => $form_equal, 'entry_equal' => $entry_equal,
		'form_changed_keys' => $changed_keys( $old_form, $new_form ),
		'entry_changed_keys' => $changed_keys( $old_entry, $new_entry ),
	), JSON_PRETTY_PRINT ) );
	throw new RuntimeException( 'G011 destination persisted Form/Entry differs after submissions; see diagnostic.' );
}
$enabled  = json_decode( (string) file_get_contents( $dir . '/g011-destination-browser-enabled.json' ), true );
$disabled = json_decode( (string) file_get_contents( $dir . '/g011-destination-browser-disabled.json' ), true );
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
