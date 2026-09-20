<?php
/**
 * G-008 disabled-state runtime assertions in a fresh WordPress request.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$artifact_dir = getenv( 'G008_ARTIFACT_DIR' );
$manifest_path = is_string( $artifact_dir ) ? $artifact_dir . '/g008-runtime.json' : '';
if ( '' === $manifest_path || ! is_readable( $manifest_path ) ) {
	throw new RuntimeException( 'G-008 runtime manifest is unavailable.' );
}

$manifest = json_decode( (string) file_get_contents( $manifest_path ), true );
if ( ! is_array( $manifest ) || empty( $manifest['entry_id'] ) || empty( $manifest['form_id'] ) ) {
	throw new RuntimeException( 'G-008 runtime manifest is malformed.' );
}

if ( PGR_Module_Registry::is_enabled( 'jalali_presentation' ) ) {
	throw new RuntimeException( 'jalali_presentation must be disabled for this check.' );
}
if ( class_exists( 'PGR_Jalali_Presentation', false ) || class_exists( 'PGR_GF_Jalali_Presentation_Adapter', false ) ) {
	throw new RuntimeException( 'Disabled G-008 runtime classes were unexpectedly loaded.' );
}
if ( false !== has_filter( 'gform_entries_field_value', array( 'PGR_GF_Jalali_Presentation_Adapter', 'filter_entry_list_value' ) ) ) {
	throw new RuntimeException( 'Disabled G-008 display filter is still registered.' );
}

$entry = GFAPI::get_entry( (int) $manifest['entry_id'] );
if ( is_wp_error( $entry ) || (string) $manifest['raw_date_created'] !== rgar( $entry, 'date_created' ) ) {
	throw new RuntimeException( 'Disabling G-008 changed raw date_created.' );
}

$native = apply_filters( 'gform_entries_field_value', 'G008_NATIVE_SENTINEL', (int) $manifest['form_id'], 'date_created', $entry );
if ( 'G008_NATIVE_SENTINEL' !== $native ) {
	throw new RuntimeException( 'Disabled G-008 did not preserve the native display pipeline.' );
}

echo "G008_DISABLED_NATIVE_PASS\n";
