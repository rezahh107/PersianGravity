<?php
/**
 * Disposable REAL Gravity Forms form for USED / UNUSED module-disable checks.
 * This file only runs inside the WU008 pinned WordPress installation.
 */
defined( 'ABSPATH' ) || exit;
$root  = getenv( 'WU008_WP_PATH' );
$dir   = getenv( 'WU008_ARTIFACT_DIR' );
$phase = getenv( 'PGR_DECISION_FIXTURE_PHASE' );
if ( ! $root || ! $dir || realpath( ABSPATH ) !== realpath( $root ) ||
	! in_array( $phase, array( 'setup', 'release', 'cleanup' ), true ) || ! class_exists( 'GFAPI' ) ) {
	throw new RuntimeException( 'Refusing module-decision fixture outside disposable licensed WU008 runtime.' );
}
$file = $dir . '/module-decision-fixture-private.json';
if ( 'setup' === $phase ) {
	if ( file_exists( $file ) ) {
		throw new RuntimeException( 'Refusing to overwrite existing module-decision fixture.' );
	}
	$states = array(
		'iranian_currency' => PGR_Module_Registry::is_enabled( 'iranian_currency' ),
		'national_id' => PGR_Module_Registry::is_enabled( 'national_id' ),
	);
	if ( ! $states['iranian_currency'] || ! $states['national_id'] ) {
		throw new RuntimeException( 'Expected modules not enabled at WU008 fixture start.' );
	}
	$form_id = GFAPI::add_form( array(
		'title'  => 'WU008 disposable USED national_id regression',
		'fields' => array(
			array( 'id' => 1, 'type' => 'pgr_national_id', 'label' => 'Disposable NID' ),
		),
	) );
	if ( ! is_int( $form_id ) || $form_id < 1 ) {
		throw new RuntimeException( 'GFAPI could not add the disposable USED form.' );
	}
	$inspection = PGR_Module_Usage::inspect( 'national_id' );
	if ( PGR_Module_Usage::USED !== $inspection['status'] ) {
		GFAPI::delete_form( $form_id );
		throw new RuntimeException( 'Saved form is not actually classified USED.' );
	}
	file_put_contents( $file, wp_json_encode( array( 'form_id' => $form_id, 'original_states' => $states ) ) );
	echo 'WU008_DECISION_FIXTURE_USED ' . $form_id;
} else {
	if ( ! file_exists( $file ) ) {
		throw new RuntimeException( 'Module-decision fixture metadata missing at cleanup.' );
	}
	$data = json_decode( file_get_contents( $file ), true, 512, JSON_THROW_ON_ERROR );
	$form_id = (int) $data['form_id'];
	$form = GFAPI::get_form( $form_id );
	if ( $form ) {
		$result = GFAPI::delete_form( $form_id );
		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( 'Failed to clean up disposable GF form.' );
		}
	}
	if ( 'release' === $phase ) {
		if ( PGR_Module_Usage::UNUSED !== PGR_Module_Usage::inspect( 'national_id' )['status'] ) {
			throw new RuntimeException( 'Disposed USED fixture does not lead to UNUSED.' );
		}
		echo 'WU008_DECISION_FIXTURE_UNUSED';
		return;
	}
	foreach ( $data['original_states'] as $id => $enabled ) {
		PGR_Module_Registry::set_enabled( $id, $enabled );
		if ( PGR_Module_Registry::is_enabled( $id ) !== $enabled ) {
			throw new RuntimeException( 'Could not restore original module state for ' . $id );
		}
	}
	echo 'WU008_DECISION_FIXTURE_RESTORED';
}
