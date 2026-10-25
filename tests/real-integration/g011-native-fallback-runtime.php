<?php
/**
 * Read-only native fallback verification with module disabled or non-Persian site.
 */
if ( ! defined( 'ABSPATH' ) || ! class_exists( 'GFForms' ) || '3.1.1.1' !== GFForms::$version ) {
    exit( 1 );
}
$fail = static function( $why ) {
    fwrite( STDERR, "G011_FALLBACK_FAIL {$why}\n" );
    exit( 1 );
};
$dir = getenv( 'G011_ARTIFACT_DIR' );
$manifest = is_string( $dir ) && is_readable( $dir . '/g011-enabled-runtime.json' ) ? json_decode( file_get_contents( $dir . '/g011-enabled-runtime.json' ), true ) : null;
if ( ! is_array( $manifest ) ) {
    $fail( 'Missing independently verified entry fixture.' );
}
$form  = GFAPI::get_form( (int) $manifest['form_id'] );
$entry = GFAPI::get_entry( (int) $manifest['entry_id'] );
if ( ! is_array( $form ) || ! is_array( $entry ) ) {
    $fail( 'Missing exact native GFAPI fixture.' );
}
$mode = getenv( 'G011_FALLBACK_MODE' );
if ( 'module_disabled' === $mode ) {
    if ( PGR_Module_Registry::is_enabled( 'jalali_presentation' ) || class_exists( 'PGR_GF_Confirmation_Date_Adapter', false ) ) {
        $fail( 'Disabled adapter still loaded.' );
    }
} elseif ( 'english' === $mode ) {
    if ( 'en_US' !== determine_locale() || ! PGR_Module_Registry::is_enabled( 'jalali_presentation' ) ) {
        $fail( 'Non-Persian case is not actually active.' );
    }
} else {
    $fail( 'Unknown fallback mode.' );
}
$native_date = GFCommon::replace_variables( '{date_created}', $form, $entry );
$confirmation = GFFormDisplay::handle_confirmation( $form, $entry, false );
if ( ! is_string( $confirmation ) || ! str_contains( $confirmation, 'created=' . $native_date . '</p>' ) ) {
    $fail( 'Native Gregorian formatted confirmation did not survive.' );
}
if ( str_contains( $confirmation, 'PGRG011' ) ) {
    $fail( 'Internal marker leaked in fallback.' );
}
if ( rgar( GFAPI::get_entry( (int) $manifest['entry_id'] ), 'date_created' ) !== $manifest['utc_date_created'] ) {
    $fail( 'Entry raw UTC changed.' );
}
echo 'G011_FALLBACK_' . strtoupper( $mode ) . "_PASS\n";
