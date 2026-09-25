<?php
/** Capture pre/post machine semantics for the GravityView G-008 qualification. */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$artifact_dir  = getenv( 'WU008_ARTIFACT_DIR' );
$manifest_path = getenv( 'WU008_MANIFEST_PATH' );
$mode          = getenv( 'WU008_G008_GV_MODE' );
$phase         = getenv( 'WU008_G008_GV_PHASE' );
if (
	! is_string( $artifact_dir ) || '' === $artifact_dir ||
	! is_string( $manifest_path ) || '' === $manifest_path ||
	! in_array( $mode, array( 'enabled', 'disabled', 'version-drift', 'english' ), true ) ||
	! in_array( $phase, array( 'pre', 'post' ), true )
) {
	throw new RuntimeException( 'GravityView state requires artifact/manifest paths, a valid mode, and pre|post phase.' );
}
if ( ! defined( 'GV_PLUGIN_VERSION' ) || '3.3.4' !== GV_PLUGIN_VERSION ) {
	throw new RuntimeException( 'GravityView state requires exact GravityView 3.3.4.' );
}

$manifest = json_decode( (string) file_get_contents( $manifest_path ), true, 512, JSON_THROW_ON_ERROR );
$form_id  = (int) $manifest['g008_gravityview_form_id'];
$view_id  = (int) $manifest['g008_gravityview_view_id'];

if ( 'pre' === $phase ) {
	update_option( 'wu008_g008_gravityview_target_view_id', $view_id );
	update_option( 'wu008_g008_gravityview_force_version_mismatch', 'version-drift' === $mode );
	if ( 'english' === $mode ) {
		delete_option( 'WPLANG' );
	} else {
		update_option( 'WPLANG', 'fa_IR' );
	}
	$enabled = 'disabled' !== $mode;
	if ( ! PGR_Module_Registry::set_enabled( 'jalali_presentation', $enabled ) ) {
		throw new RuntimeException( 'Could not configure jalali_presentation for GravityView qualification.' );
	}
}

wp_set_current_user( 1 );
$rest_settings = get_option( 'gravityformsaddon_gravityformswebapi_settings', array() );
if ( ! is_array( $rest_settings ) || empty( $rest_settings['enabled'] ) ) {
	throw new RuntimeException( 'Gravity Forms REST API v2 is not enabled.' );
}

global $wpdb;
$entry_table = GFFormsModel::get_entry_table_name();
$entries     = array();
foreach ( $manifest['g008_gravityview_entries'] as $fixture ) {
	$entry_id = (int) $fixture['id'];
	$entry    = GFAPI::get_entry( $entry_id );
	if ( is_wp_error( $entry ) ) {
		throw new RuntimeException( $entry->get_error_message() );
	}
	$db_row = $wpdb->get_row(
		$wpdb->prepare( "SELECT id, form_id, date_created, date_updated, status FROM {$entry_table} WHERE id = %d", $entry_id ),
		ARRAY_A
	);
	$rest_response = rest_do_request( new WP_REST_Request( 'GET', '/gf/v2/entries/' . $entry_id ) );
	$rest_data     = $rest_response->get_data();
	if ( 200 !== $rest_response->get_status() || ! is_array( $rest_data ) ) {
		throw new RuntimeException( 'Gravity Forms REST entry read failed for GravityView fixture ' . $entry_id . '.' );
	}
	$entries[] = array(
		'id'                 => $entry_id,
		'gfapi_date_created' => (string) $entry['date_created'],
		'gfapi_date_updated' => (string) $entry['date_updated'],
		'gfapi_marker'       => (string) rgar( $entry, '1' ),
		'db'                 => $db_row,
		'rest_date_created'  => (string) ( $rest_data['date_created'] ?? '' ),
		'rest_date_updated'  => (string) ( $rest_data['date_updated'] ?? '' ),
		'rest_marker'        => (string) ( $rest_data['1'] ?? '' ),
	);
}

$state = array(
	'schema_version'       => '1.0.0',
	'evidence_class'       => 'AUTHENTIC_GRAVITYVIEW_MACHINE_STATE',
	'mode'                 => $mode,
	'phase'                => $phase,
	'gravityview_version'  => GV_PLUGIN_VERSION,
	'gravityforms_version' => GFForms::$version,
	'locale'               => determine_locale(),
	'rtl'                  => is_rtl(),
	'site_timezone'        => wp_timezone_string(),
	'php_timezone'         => date_default_timezone_get(),
	'module_enabled'       => PGR_Module_Registry::is_enabled( 'jalali_presentation' ),
	'forced_version_drift' => (bool) get_option( 'wu008_g008_gravityview_force_version_mismatch', false ),
	'view_id'              => $view_id,
	'form_id'              => $form_id,
	'view_form_id'         => (int) get_post_meta( $view_id, '_gravityview_form_id', true ),
	'view_template'        => (string) get_post_meta( $view_id, '_gravityview_directory_template', true ),
	'view_fields_sha256'   => hash( 'sha256', wp_json_encode( get_post_meta( $view_id, '_gravityview_directory_fields', true ) ) ),
	'entries'              => $entries,
);

file_put_contents(
	$artifact_dir . '/g008-gravityview-state-' . $mode . '-' . $phase . '.json',
	wp_json_encode( $state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);
echo wp_json_encode( $state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
