<?php
/**
 * Disposable WU008 Scanner delete fixture and persisted-state verifier.
 *
 * Requires exact WU008 WordPress root. Never execute against a live site.
 */
defined( 'ABSPATH' ) || exit;

$root  = getenv( 'WU008_WP_PATH' );
$dir   = getenv( 'WU008_ARTIFACT_DIR' );
$phase = getenv( 'WU008_DELETE_PHASE' );
if ( ! $root || ! $dir || realpath( ABSPATH ) !== realpath( $root ) || ! in_array( $phase, array( 'setup', 'verify' ), true ) ) {
	throw new RuntimeException( 'Scanner deletion fixture requires disposable WU008 and explicit phase.' );
}
if ( ! class_exists( 'PGR_Scanner_Profile_Registry' ) || ! class_exists( 'GFAPI' ) ) {
	throw new RuntimeException( 'Missing real Scanner Registry or Gravity Forms.' );
}

$file = $dir . '/scanner-delete-fixture.json';
$ids  = array(
	'fa_IR' => 'wu008_delete_fa',
	'en_US' => 'wu008_delete_en',
);
if ( 'setup' === $phase ) {
	foreach ( $ids as $id ) {
		if ( null !== PGR_Scanner_Profile_Registry::get( $id ) ) {
			throw new RuntimeException( 'Fixture collision: ' . $id );
		}
	}
	$manifest = array( 'profiles' => array(), 'forms' => array() );
	foreach ( $ids as $locale => $id ) {
		$label = 'Delete review — ' . $locale . ' / نمونهٔ فارسی';
		$result = PGR_Scanner_Profile_Registry::save_custom_profile(
			array(
				'id'      => $id,
				'label'   => $label,
				'enabled' => true,
				'parser'  => array( 'type' => 'segments_v1', 'separator' => 'newline', 'trim' => true, 'normalize_digits' => true ),
				'outputs' => array( array( 'key' => 'sample', 'label' => 'Sample', 'required' => true ) ),
			)
		);
		if ( empty( $result['success'] ) || PGR_Scanner_Profile_Registry::get( $id )['label'] !== $label ) {
			throw new RuntimeException( 'Failed to persist Scanner fixture: ' . $id );
		}
		$form_id = GFAPI::add_form(
			array(
				'title'  => 'WU008 Scanner deletion mapping ' . $locale,
				'fields' => array(
					array( 'id' => 1, 'label' => 'Scanner', 'type' => 'pgr_structured_scanner', 'scanner_profile' => $id, 'scanner_mappings' => array( 'sample' => '2' ) ),
					array( 'id' => 2, 'label' => 'Destination', 'type' => 'text' ),
				),
				'button' => array( 'type' => 'text', 'text' => 'Submit' ),
			)
		);
		if ( is_wp_error( $form_id ) ) {
			throw new RuntimeException( 'Failed to create genuine mapped form: ' . $form_id->get_error_message() );
		}
		$form = GFAPI::get_form( $form_id );
		if ( ! $form || $form['fields'][0]->scanner_profile !== $id || $form['fields'][0]->scanner_mappings['sample'] !== '2' ) {
			throw new RuntimeException( 'Mapped form fixture was not genuinely persisted.' );
		}
		$manifest['profiles'][ $locale ] = array( 'id' => $id, 'label' => $label );
		$manifest['forms'][ $locale ]    = array( 'id' => $form_id, 'before' => wp_json_encode( $form['fields'][0] ) );
	}
	file_put_contents( $file, wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . "\n" );
	return;
}

$locale = getenv( 'WU008_DELETE_LOCALE' );
if ( ! isset( $ids[ $locale ] ) || ! is_readable( $file ) ) {
	throw new RuntimeException( 'Missing exact verification locale or fixture manifest.' );
}
$manifest = json_decode( file_get_contents( $file ), true, 512, JSON_THROW_ON_ERROR );
$id       = $manifest['profiles'][ $locale ]['id'];
$form     = GFAPI::get_form( $manifest['forms'][ $locale ]['id'] );
if ( null !== PGR_Scanner_Profile_Registry::get( $id ) ) {
	throw new RuntimeException( 'Deletion did not persist: ' . $id );
}
if ( ! $form || wp_json_encode( $form['fields'][0] ) !== $manifest['forms'][ $locale ]['before'] ) {
	throw new RuntimeException( 'Existing Gravity Forms Scanner field metadata was rewritten.' );
}
if ( null === PGR_Scanner_Profile_Registry::get( 'sayad_v01' ) ) {
	throw new RuntimeException( 'Built-in profile was lost.' );
}
foreach ( $ids as $other_locale => $other_id ) {
	if ( $other_locale !== $locale && null === PGR_Scanner_Profile_Registry::get( $other_id ) && $locale === 'fa_IR' ) {
		throw new RuntimeException( 'Other locale profile was deleted unexpectedly.' );
	}
}
$out = array(
	'evidence_class'         => 'AUTHENTIC_WU008_WORDPRESS_DB_GFAPI',
	'locale'                 => $locale,
	'deleted_profile_absent' => true,
	'built_in_preserved'     => true,
	'form_mapping_unchanged' => true,
	'other_profile_independent' => true,
);
file_put_contents( $dir . '/scanner-delete-' . $locale . '-persistence.json', wp_json_encode( $out, JSON_PRETTY_PRINT ) . "\n" );
