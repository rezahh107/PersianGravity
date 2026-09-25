<?php
/**
 * Create deterministic exact-package GravityView G-008 qualification fixtures.
 *
 * Test-only: installs a disposable MU probe; no production adapter is registered.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$artifact_dir  = getenv( 'WU008_ARTIFACT_DIR' );
$manifest_path = getenv( 'WU008_MANIFEST_PATH' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir || ! is_string( $manifest_path ) || '' === $manifest_path ) {
	throw new RuntimeException( 'GravityView qualification requires WU008 artifact/manifest paths.' );
}
if ( ! defined( 'GV_PLUGIN_VERSION' ) || '3.3.4' !== GV_PLUGIN_VERSION ) {
	throw new RuntimeException( 'GravityView qualification requires exact GravityView 3.3.4.' );
}
if ( ! class_exists( 'GFForms' ) || '3.1.1.1' !== GFForms::$version ) {
	throw new RuntimeException( 'GravityView qualification requires exact Gravity Forms 3.1.1.1.' );
}
if ( ! class_exists( 'PGR_Jalali_Presentation', false ) || ! class_exists( 'PGR_Module_Registry', false ) ) {
	throw new RuntimeException( 'PersianGravity G-008 conversion authority is unavailable.' );
}

$manifest = json_decode( (string) file_get_contents( $manifest_path ), true, 512, JSON_THROW_ON_ERROR );
if ( ! is_array( $manifest ) ) {
	throw new RuntimeException( 'WU008 runtime manifest is invalid.' );
}

update_option( 'timezone_string', 'Asia/Tehran' );
update_option( 'date_format', 'Y-m-d' );
update_option( 'time_format', 'H:i' );
if ( 'Asia/Tehran' !== wp_timezone_string() || 'UTC' !== date_default_timezone_get() ) {
	throw new RuntimeException( 'GravityView qualification timezone contract is not Tehran-site/UTC-PHP.' );
}

$rest_settings = get_option( 'gravityformsaddon_gravityformswebapi_settings', array() );
if ( ! is_array( $rest_settings ) ) {
	$rest_settings = array();
}
$rest_settings['enabled'] = true;
update_option( 'gravityformsaddon_gravityformswebapi_settings', $rest_settings );

$form_id = GFAPI::add_form(
	array(
		'title'  => 'WU008 GravityView G008 Qualification',
		'fields' => array(
			array(
				'id'    => 1,
				'label' => 'Qualification Marker',
				'type'  => 'text',
			),
		),
	)
);
if ( is_wp_error( $form_id ) || ! is_int( $form_id ) ) {
	throw new RuntimeException( 'Could not create GravityView qualification form.' );
}

$fixtures = array(
	array(
		'marker'       => 'GV-A-BOUNDARY literal 2026-03-20',
		'date_created' => '2026-03-20 20:29:00',
		'date_updated' => '2026-03-22 10:00:00',
	),
	array(
		'marker'       => 'GV-B-BOUNDARY user 1405/01/01',
		'date_created' => '2026-03-20 20:30:00',
		'date_updated' => '2026-03-19 10:00:00',
	),
	array(
		'marker'       => 'GV-C-UPDATED-BOUNDARY user@example.invalid',
		'date_created' => '2026-03-19 12:00:00',
		'date_updated' => '2026-03-20 20:30:00',
	),
);

foreach ( $fixtures as &$fixture ) {
	$entry_id = GFAPI::add_entry(
		array(
			'form_id'      => $form_id,
			'status'       => 'active',
			'1'            => $fixture['marker'],
			'date_created' => $fixture['date_created'],
			'date_updated' => $fixture['date_updated'],
		)
	);
	if ( is_wp_error( $entry_id ) || ! is_int( $entry_id ) ) {
		throw new RuntimeException( 'Could not create deterministic GravityView qualification entry.' );
	}
	$fixture['id'] = $entry_id;

	foreach ( array( 'date_created', 'date_updated' ) as $date_key ) {
		$source = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $fixture[ $date_key ], new DateTimeZone( 'UTC' ) );
		if ( false === $source ) {
			throw new RuntimeException( 'Could not parse deterministic UTC fixture source.' );
		}
		$fixture[ 'expected_' . $date_key . '_jalali' ] = PGR_Jalali_Presentation::format_datetime( $source );
		$fixture[ 'wrong_utc_' . $date_key . '_jalali' ] = PGR_Jalali_Presentation::format_datetime( $source, new DateTimeZone( 'UTC' ) );
		$fixture[ 'expected_' . $date_key . '_native' ] = GVCommon::format_date( $fixture[ $date_key ], 'format=Y-m-d H:i' );
	}
}
unset( $fixture );

if (
	$fixtures[0]['expected_date_created_jalali'] === $fixtures[0]['wrong_utc_date_created_jalali'] ||
	$fixtures[1]['expected_date_created_jalali'] === $fixtures[1]['wrong_utc_date_created_jalali'] ||
	$fixtures[2]['expected_date_updated_jalali'] === $fixtures[2]['wrong_utc_date_updated_jalali']
) {
	throw new RuntimeException( 'Boundary fixtures do not distinguish UTC from Tehran presentation.' );
}

$field_base = array(
	'show_label'        => '1',
	'custom_label'      => '',
	'custom_class'      => '',
	'only_loggedin'     => '0',
	'only_loggedin_cap' => 'read',
	'show_as_link'      => '0',
	'search_filter'     => '1',
);
$directory_fields = array(
	'directory_table-columns' => array(
		'wu008-id'      => array_replace( $field_base, array( 'id' => 'id', 'label' => 'Entry ID' ) ),
		'wu008-marker'  => array_replace( $field_base, array( 'id' => '1', 'label' => 'Qualification Marker' ) ),
		'wu008-created' => array_replace( $field_base, array( 'id' => 'date_created', 'label' => 'Date Created', 'date_display' => 'Y-m-d H:i' ) ),
		'wu008-updated' => array_replace( $field_base, array( 'id' => 'date_updated', 'label' => 'Date Updated', 'date_display' => 'Y-m-d H:i' ) ),
	),
);

$view_id = wp_insert_post(
	array(
		'post_type'   => 'gravityview',
		'post_status' => 'publish',
		'post_title'  => 'WU008 GravityView System Dates',
	),
	true
);
if ( is_wp_error( $view_id ) ) {
	throw new RuntimeException( $view_id->get_error_message() );
}
update_post_meta( $view_id, '_gravityview_form_id', $form_id );
update_post_meta( $view_id, '_gravityview_directory_template', 'default_table' );
update_post_meta(
	$view_id,
	'_gravityview_template_settings',
	array(
		'page_size'          => '50',
		'show_only_approved' => '0',
		'hide_empty'         => '0',
		'sort_field'         => 'date_created',
		'sort_direction'     => 'ASC',
		'sort_columns'       => '1',
		'start_date'         => '',
		'end_date'           => '',
	)
);
update_post_meta( $view_id, '_gravityview_directory_fields', $directory_fields );
update_post_meta( $view_id, '_gravityview_directory_widgets', array() );

$page_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'WU008 GravityView G008 Surface',
		'post_name'    => 'wu008-gravityview-g008',
		'post_content' => sprintf( '[gravityview id="%d"]', (int) $view_id ),
	),
	true
);
if ( is_wp_error( $page_id ) ) {
	throw new RuntimeException( $page_id->get_error_message() );
}

$probe_source = dirname( __FILE__ ) . '/g008-gravityview-probe.php';
$mu_dir       = WPMU_PLUGIN_DIR;
$mu_target    = trailingslashit( $mu_dir ) . 'wu008-g008-gravityview-probe.php';
wp_mkdir_p( $mu_dir );
if ( ! is_readable( $probe_source ) || false === copy( $probe_source, $mu_target ) ) {
	throw new RuntimeException( 'Could not install qualification-only GravityView MU probe.' );
}

update_option( 'wu008_g008_gravityview_target_view_id', (int) $view_id );
update_option( 'wu008_g008_gravityview_force_version_mismatch', false );
if ( ! PGR_Module_Registry::set_enabled( 'jalali_presentation', true ) ) {
	throw new RuntimeException( 'Could not enable jalali_presentation for GravityView qualification.' );
}

$manifest['schema_version']                   = '1.4.0';
$manifest['g008_gravityview_form_id']         = (int) $form_id;
$manifest['g008_gravityview_view_id']         = (int) $view_id;
$manifest['g008_gravityview_page_id']         = (int) $page_id;
$manifest['g008_gravityview_page_url']        = add_query_arg( 'page_id', (int) $page_id, home_url( '/' ) );
$manifest['g008_gravityview_entries']         = $fixtures;
$manifest['g008_gravityview_site_timezone']   = wp_timezone_string();
$manifest['g008_gravityview_php_timezone']    = date_default_timezone_get();
$manifest['g008_gravityview_probe_installed'] = basename( $mu_target );

file_put_contents(
	$manifest_path,
	wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);
file_put_contents(
	$artifact_dir . '/g008-gravityview-fixture.json',
	wp_json_encode(
		array(
			'evidence_class' => 'EXACT_GRAVITYVIEW_3_3_4_QUALIFICATION_FIXTURE',
			'view_id'        => (int) $view_id,
			'form_id'        => (int) $form_id,
			'page_url'       => $manifest['g008_gravityview_page_url'],
			'entries'        => $fixtures,
			'site_timezone'  => wp_timezone_string(),
			'php_timezone'   => date_default_timezone_get(),
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	) . "\n"
);

echo wp_json_encode(
	array(
		'view_id'  => (int) $view_id,
		'form_id'  => (int) $form_id,
		'page_url' => $manifest['g008_gravityview_page_url'],
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
) . "\n";
