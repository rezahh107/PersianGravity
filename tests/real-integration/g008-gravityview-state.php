<?php
/**
 * Capture GravityView G-008 machine-semantics state for one qualification mode.
 */

defined( 'ABSPATH' ) || exit;

$artifact_dir  = getenv( 'WU008_ARTIFACT_DIR' );
$manifest_path = getenv( 'WU008_MANIFEST_PATH' );
$mode          = getenv( 'WU008_GV_MODE' );
if (
	! is_string( $artifact_dir ) || '' === $artifact_dir ||
	! is_string( $manifest_path ) || '' === $manifest_path ||
	! in_array( $mode, array( 'enabled', 'disabled', 'english', 'drift' ), true )
) {
	throw new RuntimeException( 'GravityView state requires artifact/manifest paths and a recognized mode.' );
}

$manifest = json_decode( file_get_contents( $manifest_path ), true );
if ( ! is_array( $manifest ) || empty( $manifest['g008_gravityview']['entries'] ) ) {
	throw new RuntimeException( 'GravityView qualification manifest is incomplete.' );
}
$fixture_path = $artifact_dir . '/g008-gravityview-fixture-baseline.json';
$baseline     = json_decode( file_get_contents( $fixture_path ), true );
if ( ! is_array( $baseline ) ) {
	throw new RuntimeException( 'GravityView fixture baseline is malformed.' );
}
if ( 'Asia/Tehran' !== wp_timezone_string() || 'UTC' !== date_default_timezone_get() ) {
	throw new RuntimeException( 'GravityView state timezone identity drifted.' );
}

wp_set_current_user( 1 );
$form_id        = (int) $manifest['g008_gravityview']['form_id'];
$module_enabled = class_exists( 'PGR_Module_Registry', false ) && PGR_Module_Registry::is_enabled( 'jalali_presentation' );
$probe_mode     = get_option( 'wu008_gv_qualification_mode', 'native' );

$expected_module = 'disabled' !== $mode;
if ( $expected_module !== $module_enabled ) {
	throw new RuntimeException( 'GravityView state module state does not match qualification mode.' );
}
if ( $mode !== $probe_mode ) {
	throw new RuntimeException( 'GravityView qualification prototype mode drifted.' );
}

global $wpdb;
$entry_table = GFFormsModel::get_entry_table_name();
$entries     = array();

foreach ( $manifest['g008_gravityview']['entries'] as $fixture ) {
	$entry_id = (int) $fixture['id'];
	$entry    = GFAPI::get_entry( $entry_id );
	if ( is_wp_error( $entry ) ) {
		throw new RuntimeException( $entry->get_error_message() );
	}

	$db_row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT date_created, date_updated FROM {$entry_table} WHERE id = %d",
			$entry_id
		),
		ARRAY_A
	);
	if ( ! is_array( $db_row ) ) {
		throw new RuntimeException( 'GravityView fixture database row is missing.' );
	}

	$rest_request  = new WP_REST_Request( 'GET', '/gf/v2/entries/' . $entry_id );
	$rest_response = rest_do_request( $rest_request );
	$rest_data     = $rest_response->get_data();
	if (
		200 !== $rest_response->get_status() ||
		! is_array( $rest_data ) ||
		! array_key_exists( 'date_created', $rest_data ) ||
		! array_key_exists( 'date_updated', $rest_data )
	) {
		throw new RuntimeException( 'Gravity Forms REST entry contract could not be proven for GravityView fixture ' . $entry_id . '.' );
	}

	$current = array(
		'id'                 => $entry_id,
		'token'              => (string) rgar( $entry, '1' ),
		'gfapi_date_created' => (string) rgar( $entry, 'date_created' ),
		'gfapi_date_updated' => (string) rgar( $entry, 'date_updated' ),
		'db_date_created'    => (string) $db_row['date_created'],
		'db_date_updated'    => (string) $db_row['date_updated'],
		'rest_date_created'  => (string) $rest_data['date_created'],
		'rest_date_updated'  => (string) $rest_data['date_updated'],
	);

	foreach ( array( 'gfapi_date_created', 'db_date_created', 'rest_date_created' ) as $key ) {
		if ( (string) $fixture['date_created'] !== $current[ $key ] ) {
			throw new RuntimeException( 'date_created machine semantics changed for fixture ' . $entry_id . ' at ' . $key . '.' );
		}
	}
	foreach ( array( 'gfapi_date_updated', 'db_date_updated', 'rest_date_updated' ) as $key ) {
		if ( (string) $fixture['date_updated'] !== $current[ $key ] ) {
			throw new RuntimeException( 'date_updated machine semantics changed for fixture ' . $entry_id . ' at ' . $key . '.' );
		}
	}
	if ( (string) $fixture['token'] !== $current['token'] ) {
		throw new RuntimeException( 'Unrelated fixture content changed for entry ' . $entry_id . '.' );
	}

	$entries[] = $current;
}

$sort_ids = static function ( string $key, string $direction ) use ( $form_id ): array {
	$result = GFAPI::get_entries(
		$form_id,
		array( 'status' => 'active' ),
		array(
			'key'       => $key,
			'direction' => $direction,
		),
		array(
			'offset'    => 0,
			'page_size' => 50,
		)
	);
	if ( is_wp_error( $result ) ) {
		throw new RuntimeException( $result->get_error_message() );
	}
	return array_map(
		static function ( $entry ) {
			return (int) $entry['id'];
		},
		$result
	);
};

$state = array(
	'schema_version'            => '1.0.0',
	'mode'                      => $mode,
	'exact_persiangravity_head' => (string) getenv( 'WU008_PGR_SHA' ),
	'exact_gravityview_version' => '3.3.4',
	'exact_gravityview_sha256'  => (string) getenv( 'WU008_VIEW_SHA256' ),
	'locale'                    => determine_locale(),
	'site_timezone'             => wp_timezone_string(),
	'php_timezone'              => date_default_timezone_get(),
	'module_enabled'            => $module_enabled,
	'prototype_mode'            => $probe_mode,
	'entries'                   => $entries,
	'gfapi_sort'                => array(
		'date_created_asc'  => $sort_ids( 'date_created', 'ASC' ),
		'date_created_desc' => $sort_ids( 'date_created', 'DESC' ),
		'date_updated_asc'  => $sort_ids( 'date_updated', 'ASC' ),
		'date_updated_desc' => $sort_ids( 'date_updated', 'DESC' ),
	),
	'prototype_trace'           => get_option( 'wu008_gv_qualification_trace', array() ),
);

file_put_contents(
	$artifact_dir . '/g008-gravityview-state-' . $mode . '.json',
	wp_json_encode( $state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);

echo wp_json_encode( $state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
