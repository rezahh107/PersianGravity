<?php
/**
 * Fail-closed observation of one real ordinary submission.
 * Persist bounded evidence, restore source state and remove disposable MU.
 */
defined( 'ABSPATH' ) || exit;
$dir = getenv( 'WU008_ARTIFACT_DIR' );
$events = get_option( 'pgr_g011_preprocess_probe_events', array() );
$baseline = get_option( 'pgr_g011_preprocess_probe_baseline', array() );
$cfg = get_option( 'pgr_g011_preprocess_probe_active', array() );
$form = GFAPI::get_form( (int) ( $baseline['form_id'] ?? 0 ) );
$browser = json_decode( (string) file_get_contents( $dir . '/g011-preprocess-browser.json' ), true );
$pre_count = 0;
$early_seen_marker = false;
$late_seen_marker = false;
$confirmed_selected = false;
$observed = array();
foreach ( is_array( $events ) ? $events : array() as $event ) {
	$kind = $event['kind'] ?? '';
	$data = $event['data'] ?? array();
	if ( 'pre_process' === $kind && ! empty( $data['changed_occurrences'] ) ) {
		++$pre_count;
	}
	if ( 'third_party_gform_pre_replace_merge_tags' === $kind && ! empty( $data['marker_visible'] ) ) {
		if ( 1 === (int) ( $data['priority'] ?? -1 ) ) {
			$early_seen_marker = true;
		}
		if ( 999 === (int) ( $data['priority'] ?? -1 ) ) {
			$late_seen_marker = true;
		}
	}
	if ( 'confirmation' === $kind && ! empty( $data['marker_visible_before_restore'] ) &&
		'message' === ( $data['selected_type'] ?? null ) ) {
		$confirmed_selected = true;
	}
}
$persisted_unchanged = is_array( $form ) &&
	( $baseline['confirmations_hash'] ?? null ) === hash( 'sha256', wp_json_encode( $form['confirmations'] ?? array() ) );
$observed = array(
	'contract' => 'G011_PREPROCESS_DECISIVE_NATIVE_OBSERVER_FALSIFIER',
	'head' => getenv( 'WU008_PGR_SHA' ),
	'host_version' => GFForms::$version,
	'host_sha256' => getenv( 'WU008_GF_SHA256' ),
	'source_filter_callsites' => $baseline['preprocess_calls'] ?? array(),
	'pre_process_changed_in_actual_submission' => $pre_count > 0,
	'early_third_party_observed_marker' => $early_seen_marker,
	'late_third_party_observed_marker' => $late_seen_marker,
	'selected_confirmation_observed_marker_before_cleanup' => $confirmed_selected,
	'persisted_form_confirmations_unchanged' => $persisted_unchanged,
	'browser' => $browser['rendered'] ?? null,
	'event_counts' => array_count_values( array_map(
		static function ( $x ) { return (string) ( $x['kind'] ?? 'unknown' ); },
		is_array( $events ) ? $events : array()
	) ),
	'events' => array_slice( is_array( $events ) ? $events : array(), 0, 30 ),
	'conclusion' => 'PREPROCESS_MARKERS_EXPOSED_TO_INDEPENDENT_NATIVE_MERGE_CALLBACKS',
);
file_put_contents( $dir . '/g011-preprocess-falsifier.json',
	wp_json_encode( $observed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
$saved = get_option( 'pgr_g011_preprocess_probe_saved_state' );
if ( is_array( $saved ) ) {
	update_option( 'pgr_modules', $saved );
}
delete_option( 'pgr_g011_preprocess_probe_saved_state' );
delete_option( 'pgr_g011_preprocess_probe_active' );
delete_option( 'pgr_g011_preprocess_probe_events' );
delete_option( 'pgr_g011_preprocess_probe_baseline' );
$mu = WP_CONTENT_DIR . '/mu-plugins/pgr-g011-preprocess-qual.php';
if ( file_exists( $mu ) ) {
	unlink( $mu );
}
if ( ! $pre_count || ! $early_seen_marker || ! $late_seen_marker || ! $confirmed_selected ||
	! $persisted_unchanged || empty( $browser['rendered']['ordinary_submission'] ) ||
	! empty( $browser['rendered']['marker_leaked_to_visible_dom'] ) ) {
	throw new RuntimeException( 'G011 pre_process falsifier not fully observed; see bounded evidence.' );
}
echo "G011 pre_process marker-input observer falsifier CONFIRMED, stop qualification\n";
