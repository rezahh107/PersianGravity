<?php
/**
 * Exact Gravity Forms G-008 runtime assertions.
 *
 * Executed only inside the disposable licensed-package workflow.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress bootstrap is required.\n" );
	exit( 1 );
}

$artifact_dir = getenv( 'G008_ARTIFACT_DIR' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir ) {
	fwrite( STDERR, "G008_ARTIFACT_DIR is required.\n" );
	exit( 1 );
}

$fail = static function ( $message ) {
	fwrite( STDERR, '[G008] ' . $message . "\n" );
	exit( 1 );
};

if ( ! class_exists( 'GFForms' ) || '3.1.1.1' !== GFForms::$version ) {
	$fail( 'Exact Gravity Forms 3.1.1.1 is not active.' );
}
if ( ! class_exists( 'PGR_Module_Registry' ) || ! PGR_Module_Registry::is_enabled( 'jalali_presentation' ) ) {
	$fail( 'jalali_presentation is not enabled.' );
}
if ( ! class_exists( 'PGR_Jalali_Presentation' ) || ! class_exists( 'PGR_GF_Jalali_Presentation_Adapter' ) ) {
	$fail( 'G-008 runtime classes are not loaded.' );
}

$callback = array( 'PGR_GF_Jalali_Presentation_Adapter', 'filter_entry_list_value' );
if ( false === has_filter( 'gform_entries_field_value', $callback ) ) {
	$fail( 'Entries List display filter is not registered.' );
}

global $wp_filter;
$adapter_hooks = array();
foreach ( (array) $wp_filter as $hook_name => $hook ) {
	if ( ! is_object( $hook ) || ! isset( $hook->callbacks ) || ! is_array( $hook->callbacks ) ) {
		continue;
	}
	foreach ( $hook->callbacks as $callbacks ) {
		foreach ( $callbacks as $definition ) {
			$registered = $definition['function'] ?? null;
			if ( ! is_array( $registered ) ) {
				continue;
			}

			$callback_owner = $registered[0] ?? null;
			$is_adapter     = is_string( $callback_owner )
				? 'PGR_GF_Jalali_Presentation_Adapter' === $callback_owner
				: is_object( $callback_owner ) && 'PGR_GF_Jalali_Presentation_Adapter' === get_class( $callback_owner );

			if ( $is_adapter ) {
				$adapter_hooks[] = (string) $hook_name;
			}
		}
	}
}
$adapter_hooks = array_values( array_unique( $adapter_hooks ) );
sort( $adapter_hooks );
if ( array( 'gform_entries_field_value' ) !== $adapter_hooks ) {
	$fail( 'G-008 registered outside its single admitted display hook: ' . implode( ',', $adapter_hooks ) );
}

$entry_list_file = GFCommon::get_base_path() . '/entry_list.php';
if ( ! is_readable( $entry_list_file ) ) {
	$fail( 'Exact GF entry_list.php source is unavailable.' );
}
$entry_list_source = file_get_contents( $entry_list_file );
if ( ! is_string( $entry_list_source ) || false === strpos( $entry_list_source, 'gform_entries_field_value' ) || false === strpos( $entry_list_source, 'date_created' ) ) {
	$fail( 'Exact GF 3.1.1.1 source does not prove the expected bounded Entries List seam.' );
}

update_option( 'timezone_string', 'Asia/Tehran' );
if ( 'Asia/Tehran' !== wp_timezone_string() ) {
	$fail( 'WordPress site timezone was not applied.' );
}

$form_id = GFAPI::add_form(
	array(
		'title'  => 'G008 Jalali Presentation Runtime',
		'fields' => array(),
	)
);
if ( is_wp_error( $form_id ) || ! is_int( $form_id ) ) {
	$fail( 'Could not create the runtime Gravity Forms fixture form.' );
}

$raw_newer = '2026-03-20 20:30:00';
$raw_older = '2026-03-19 20:30:00';
$newer_id  = GFAPI::add_entry(
	array(
		'form_id'      => $form_id,
		'status'       => 'active',
		'date_created' => $raw_newer,
	)
);
$older_id  = GFAPI::add_entry(
	array(
		'form_id'      => $form_id,
		'status'       => 'active',
		'date_created' => $raw_older,
	)
);
if ( is_wp_error( $newer_id ) || is_wp_error( $older_id ) || ! is_int( $newer_id ) || ! is_int( $older_id ) ) {
	$fail( 'Could not create deterministic runtime entries.' );
}

$newer = GFAPI::get_entry( $newer_id );
$older = GFAPI::get_entry( $older_id );
if ( is_wp_error( $newer ) || is_wp_error( $older ) ) {
	$fail( 'Could not read deterministic runtime entries.' );
}
if ( $raw_newer !== rgar( $newer, 'date_created' ) || $raw_older !== rgar( $older, 'date_created' ) ) {
	$fail( 'GFAPI raw date_created did not preserve the supplied UTC values.' );
}

$display          = apply_filters( 'gform_entries_field_value', 'native', $form_id, 'date_created', $newer );
$expected_display = '۱۴۰۵/۰۱/۰۱، ۰۰:۰۰';
if ( $expected_display !== $display ) {
	$fail( 'Entries List filter did not produce the expected site-timezone Jalali value: ' . (string) $display );
}
if ( $raw_newer !== rgar( GFAPI::get_entry( $newer_id ), 'date_created' ) ) {
	$fail( 'Presentation mutated GFAPI date_created.' );
}

$webapi_file = GFCommon::get_base_path() . '/includes/webapi/webapi.php';
if ( ! is_readable( $webapi_file ) ) {
	$fail( 'Exact GF webapi.php source is unavailable.' );
}
$webapi_source = file_get_contents( $webapi_file );
if (
	! is_string( $webapi_source ) ||
	false === strpos( $webapi_source, 'gravityformsaddon_gravityformswebapi_settings' ) ||
	false === strpos( $webapi_source, 'is_v2_enabled' ) ||
	false === strpos( $webapi_source, "get_setting( 'enabled'" )
) {
	$fail( 'Exact GF 3.1.1.1 source does not prove the REST v2 enablement contract.' );
}
$rest_settings = get_option( 'gravityformsaddon_gravityformswebapi_settings', array() );
if ( ! is_array( $rest_settings ) || empty( $rest_settings['enabled'] ) ) {
	$fail( 'Gravity Forms REST API v2 was not explicitly enabled for the runtime proof.' );
}

wp_set_current_user( 1 );
$rest_request  = new WP_REST_Request( 'GET', '/gf/v2/entries/' . $newer_id );
$rest_response = rest_do_request( $rest_request );
$rest_data     = $rest_response->get_data();
if ( 200 !== $rest_response->get_status() || ! is_array( $rest_data ) || $raw_newer !== (string) ( $rest_data['date_created'] ?? '' ) ) {
	$fail( 'Gravity Forms REST API date_created was changed or could not be proven.' );
}

$unrelated = apply_filters( 'gform_entries_field_value', '1405-01-01', $form_id, '1', $newer );
if ( '1405-01-01' !== $unrelated ) {
	$fail( 'An unrelated entry value was converted.' );
}

$sorted = GFAPI::get_entries(
	$form_id,
	array( 'status' => 'active' ),
	array(
		'key'       => 'date_created',
		'direction' => 'DESC',
	)
);
if ( is_wp_error( $sorted ) || count( $sorted ) < 2 || $newer_id !== (int) $sorted[0]['id'] || $older_id !== (int) $sorted[1]['id'] ) {
	$fail( 'Native date_created sorting semantics changed or could not be proven.' );
}

$filtered = GFAPI::get_entries(
	$form_id,
	array(
		'status'     => 'active',
		'start_date' => '2026-03-20',
		'end_date'   => '2026-03-21',
	)
);
if ( is_wp_error( $filtered ) || 1 !== count( $filtered ) || $newer_id !== (int) $filtered[0]['id'] ) {
	$fail( 'Native date_created date-range filtering semantics changed or could not be proven.' );
}

global $wpdb;
$table      = GFFormsModel::get_entry_table_name();
$stored_raw = $wpdb->get_var( $wpdb->prepare( "SELECT date_created FROM {$table} WHERE id = %d", $newer_id ) );
if ( $raw_newer !== $stored_raw ) {
	$fail( 'Database date_created storage was mutated.' );
}

$manifest = array(
	'gravity_forms_version'             => GFForms::$version,
	'persian_gravity_version'           => defined( 'PGR_VERSION' ) ? PGR_VERSION : null,
	'wordpress_version'                 => get_bloginfo( 'version' ),
	'php_version'                       => PHP_VERSION,
	'site_timezone'                     => wp_timezone_string(),
	'form_id'                           => $form_id,
	'entry_id'                          => $newer_id,
	'raw_date_created'                  => $raw_newer,
	'display_date_created'              => $display,
	'expected_display'                  => $expected_display,
	'database_raw_date_created'         => $stored_raw,
	'sorted_entry_ids'                  => array( (int) $sorted[0]['id'], (int) $sorted[1]['id'] ),
	'filtered_entry_ids'                => array_map( 'intval', wp_list_pluck( $filtered, 'id' ) ),
	'gf_entries_list_seam_file'         => $entry_list_file,
	'gf_entries_list_filter_registered' => true,
	'adapter_registered_hooks'          => $adapter_hooks,
	'rest_api_enabled'                  => true,
	'rest_raw_date_created'             => (string) $rest_data['date_created'],
);

if ( false === file_put_contents( $artifact_dir . '/g008-runtime.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) ) {
	$fail( 'Could not write runtime evidence.' );
}

echo wp_json_encode( $manifest, JSON_UNESCAPED_UNICODE ) . "\n";
