<?php
/**
 * WU008-only deterministic check/delete interleaving probe.
 *
 * Copy into the DISPOSABLE WordPress mu-plugins directory for one authenticated
 * admin-post. It intentionally reproduces an unsafe result; never ship with the
 * production plugin and never enable on a real site.
 */
defined( 'ABSPATH' ) || exit;

$wu008_root     = getenv( 'WU008_WP_PATH' );
$wu008_artifact = getenv( 'WU008_ARTIFACT_DIR' );
if (
	! $wu008_root || ! $wu008_artifact ||
	realpath( ABSPATH ) !== realpath( $wu008_root ) ||
	! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ||
	! isset( $_POST['action'], $_POST['profile_id'] ) ||
	! is_string( $_POST['action'] ) || 'pgr_scanner_profile_delete' !== $_POST['action'] ||
	! is_string( $_POST['profile_id'] ) || 'alpha' !== $_POST['profile_id']
) {
	return;
}

$wu008_probe = null;
$wu008_probe = static function ( $pre ) use ( &$wu008_probe, $wu008_artifact ) {
	$in_delete = false;
	foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 ) as $call ) {
		if ( 'PGR_Scanner_Profile_Registry' === ( $call['class'] ?? '' ) &&
			'delete_custom_profile' === ( $call['function'] ?? '' ) ) {
			$in_delete = true;
			break;
		}
	}
	if ( ! $in_delete ) {
		return $pre;
	}
	remove_filter( 'pre_option_pgr_scanner_profiles', $wu008_probe, 10 );
	if ( ! current_user_can( 'manage_options' ) ||
		! class_exists( 'PGR_Scanner_Profile_Registry' ) ) {
		throw new RuntimeException( 'WU008 race hook crossed an unauthorized/unloaded boundary.' );
	}
	$start = microtime( true );
	$original = PGR_Scanner_Profile_Registry::get( 'alpha' );
	if ( null === $original || 'Alpha' !== $original['label'] ||
		'original' !== $original['outputs'][0]['key'] ) {
		throw new RuntimeException( 'WU008 race hook expected genuine A at delete read.' );
	}
	$old_fingerprint = PGR_Scanner_Profile_Registry::snapshot_fingerprint( $original );
	$removed = PGR_Scanner_Profile_Registry::delete_custom_profile( 'alpha' );
	$replacement = $original;
	$replacement['parser']['trim'] = false;
	$replacement['parser']['normalize_digits'] = false;
	$replacement['outputs'] = array(
		array( 'key' => 'replacement', 'label' => 'Replacement', 'required' => true ),
	);
	$created = PGR_Scanner_Profile_Registry::save_custom_profile( $replacement );
	$current = PGR_Scanner_Profile_Registry::get( 'alpha' );
	if ( ! $removed || ! $created['success'] || null === $current ||
		'replacement' !== $current['outputs'][0]['key'] ) {
		throw new RuntimeException( 'WU008 real Registry mutation did not persist B.' );
	}
	$marker = array(
		'probe' => 'WU008_REGISTRY_PRE_DELETE_READ_INTERLEAVING',
		'original_was_read' => true,
		'replacement_persisted_before_delete_read' => true,
		'replacement_content_differs' =>
			$old_fingerprint !== PGR_Scanner_Profile_Registry::snapshot_fingerprint( $current ),
		'mutation_elapsed_ms' => round( ( microtime( true ) - $start ) * 1000, 3 ),
		'mechanism' => 'pre_option callback intercepted Registry delete method second get_option read',
		'actual_parallel_workers' => false,
	);
	$location = rtrim( $wu008_artifact, '/\\' ) . '/scanner-incarnation-evidence/interleaving-marker.json';
	if ( ! is_dir( dirname( $location ) ) ||
		false === file_put_contents( $location, wp_json_encode( $marker, JSON_PRETTY_PRINT ) ) ) {
		throw new RuntimeException( 'WU008 interleaving evidence could not be written.' );
	}
	// Return false to let the *real* get_option read the newly persisted B.
	return $pre;
};
add_filter( 'pre_option_pgr_scanner_profiles', $wu008_probe, 10, 1 );
