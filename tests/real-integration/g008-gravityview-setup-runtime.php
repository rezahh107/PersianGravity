<?php
/**
 * Build deterministic GravityView G-008 qualification fixtures.
 */

defined( 'ABSPATH' ) || exit;

$artifact_dir  = getenv( 'WU008_ARTIFACT_DIR' );
$manifest_path = getenv( 'WU008_MANIFEST_PATH' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir || ! is_string( $manifest_path ) || '' === $manifest_path ) {
	throw new RuntimeException( 'WU008 GravityView artifact and manifest paths are required.' );
}

$manifest = json_decode( file_get_contents( $manifest_path ), true );
if ( ! is_array( $manifest ) ) {
	throw new RuntimeException( 'WU008 runtime manifest is malformed.' );
}
if ( ! class_exists( 'GFAPI' ) || ! class_exists( 'GFForms' ) || '3.1.1.1' !== (string) GFForms::$version ) {
	throw new RuntimeException( 'Exact Gravity Forms 3.1.1.1 runtime is required.' );
}
if ( ! post_type_exists( 'gravityview' ) ) {
	throw new RuntimeException( 'GravityView post type is unavailable.' );
}

$original_date_format = get_option( 'date_format' );
update_option( 'timezone_string', 'Asia/Tehran' );
update_option( 'gmt_offset', 3.5 );
update_option( 'date_format', 'Y-m-d' );
update_option( 'gravityformsaddon_gravityformswebapi_settings', array( 'enabled' => '1' ) );
if ( 'Asia/Tehran' !== wp_timezone_string() || 'UTC' !== date_default_timezone_get() ) {
	throw new RuntimeException( 'GravityView qualification requires PHP UTC and site Asia/Tehran.' );
}

if (
	! class_exists( 'PGR_Module_Registry' ) ||
	! PGR_Module_Registry::is_enabled( 'jalali_presentation' ) ||
	! class_exists( 'PGR_Jalali_Presentation', false )
) {
	throw new RuntimeException( 'jalali_presentation must be enabled before the GravityView fixture request so its typed facade loads at gform_loaded.' );
}

$form = array(
	'title'  => 'WU008 GravityView G008 System Dates',
	'fields' => array(
		array(
			'id'       => 1,
			'type'     => 'text',
			'label'    => 'Fixture Token',
			'required' => false,
		),
	),
	'button' => array(
		'type' => 'text',
		'text' => 'Submit',
	),
);
$form_id = GFAPI::add_form( $form );
if ( is_wp_error( $form_id ) ) {
	throw new RuntimeException( $form_id->get_error_message() );
}
$form_id = (int) $form_id;

$fixture_specs = array(
	array(
		'key'          => 'alpha',
		'date_created' => '2026-03-20 20:29:00',
		'date_updated' => '2026-03-22 20:31:00',
		'token'        => 'GV-ALPHA user text 2026-03-20 20:29:00 must stay native',
	),
	array(
		'key'          => 'bravo',
		'date_created' => '2026-03-20 20:31:00',
		'date_updated' => '2026-03-20 20:31:00',
		'token'        => 'GV-BRAVO user text 2026-03-20 20:31:00 must stay native',
	),
	array(
		'key'          => 'charlie',
		'date_created' => '2026-03-21 20:31:00',
		'date_updated' => '2026-03-21 20:31:00',
		'token'        => 'GV-CHARLIE user text 2026-03-21 20:31:00 must stay native',
	),
);

$fixtures = array();
foreach ( $fixture_specs as $spec ) {
	$entry_id = GFAPI::add_entry(
		array(
			'form_id'      => $form_id,
			'status'       => 'active',
			'created_by'   => 1,
			'date_created' => $spec['date_created'],
			'date_updated' => $spec['date_updated'],
			'1'            => $spec['token'],
		)
	);
	if ( is_wp_error( $entry_id ) ) {
		throw new RuntimeException( $entry_id->get_error_message() );
	}
	$entry_id = (int) $entry_id;

	// GFAPI may normalize system properties; establish deterministic fixture values through the host model.
	GFFormsModel::update_lead_property( $entry_id, 'date_created', $spec['date_created'] );
	GFFormsModel::update_lead_property( $entry_id, 'date_updated', $spec['date_updated'] );

	if ( class_exists( 'GravityView_Entry_Approval' ) ) {
		GravityView_Entry_Approval::update_approved( $entry_id, 1, $form_id );
	}

	$created = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $spec['date_created'], new DateTimeZone( 'UTC' ) );
	$updated = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $spec['date_updated'], new DateTimeZone( 'UTC' ) );
	if ( false === $created || false === $updated || ! class_exists( 'PGR_Jalali_Presentation', false ) ) {
		throw new RuntimeException( 'Typed Jalali presentation authority is unavailable for fixture generation.' );
	}

	$native_created = GVCommon::format_date( $spec['date_created'], 'format=Y-m-d H:i:s' );
	$native_updated = GVCommon::format_date( $spec['date_updated'], 'format=Y-m-d H:i:s' );
	$local_created  = $created->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' );
	$local_updated  = $updated->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' );
	if ( $native_created !== $local_created || $native_updated !== $local_updated ) {
		throw new RuntimeException( 'GravityView native formatter timezone semantics differ from the authoritative UTC-to-site-time fixture contract.' );
	}

	$fixtures[] = array(
		'key'                     => $spec['key'],
		'id'                      => $entry_id,
		'token'                   => $spec['token'],
		'date_created'            => $spec['date_created'],
		'date_updated'            => $spec['date_updated'],
		'expected_created_native' => $native_created,
		'expected_updated_native' => $native_updated,
		'expected_created_jalali' => PGR_Jalali_Presentation::format_datetime( $created ),
		'expected_updated_jalali' => PGR_Jalali_Presentation::format_datetime( $updated ),
	);
}

$view_id = wp_insert_post(
	array(
		'post_type'   => 'gravityview',
		'post_status' => 'publish',
		'post_title'  => 'WU008 G008 GravityView System Dates',
	),
	true
);
if ( is_wp_error( $view_id ) ) {
	throw new RuntimeException( $view_id->get_error_message() );
}
$view_id = (int) $view_id;

$field_base = array(
	'show_label'        => '1',
	'custom_label'      => '',
	'custom_class'      => '',
	'show_as_link'      => '0',
	'search_filter'     => '0',
	'only_loggedin'     => '0',
	'only_loggedin_cap' => 'read',
);
update_post_meta( $view_id, '_gravityview_form_id', $form_id );
update_post_meta( $view_id, '_gravityview_directory_template', 'default_table' );
update_post_meta(
	$view_id,
	'_gravityview_template_settings',
	array(
		'page_size'      => '25',
		'sort_columns'   => '1',
		'sort_field'     => 'date_created',
		'sort_direction' => 'ASC',
	)
);
update_post_meta(
	$view_id,
	'_gravityview_directory_fields',
	array(
		'directory_table-columns' => array(
			'wu008_token' => array_merge(
				$field_base,
				array(
					'id'    => '1',
					'label' => 'Fixture Token',
				)
			),
			'wu008_created' => array_merge(
				$field_base,
				array(
					'id'           => 'date_created',
					'label'        => 'Date Created',
					'date_display' => 'Y-m-d H:i:s',
				)
			),
			'wu008_updated' => array_merge(
				$field_base,
				array(
					'id'           => 'date_updated',
					'label'        => 'Date Updated',
					'date_display' => 'Y-m-d H:i:s',
				)
			),
		),
	)
);
if ( ! class_exists( '\\GravityKit\\GravityView\\REST\\InspectorRoute' ) ) {
	throw new RuntimeException( 'GravityView exact search-bar host API is unavailable.' );
}
$search_request = new WP_REST_Request( 'POST', '' );
$search_request->set_param( 'id', $view_id );
$search_request->set_body(
	wp_json_encode(
		array(
			'zone'   => 'header',
			'fields' => array(
				array(
					'field_id' => 'date_created',
					'input'    => 'date',
					'label'    => 'Date Created Search',
				),
				array(
					'field_id' => 'date_updated',
					'input'    => 'date',
					'label'    => 'Date Updated Search',
				),
			),
		)
	)
);
$search_request->set_header( 'content-type', 'application/json' );
$search_response = ( new \\GravityKit\\GravityView\\REST\\InspectorRoute() )->add_search_bar( $search_request );
if ( is_wp_error( $search_response ) ) {
	throw new RuntimeException( 'GravityView host search-bar API rejected the qualification fixture: ' . $search_response->get_error_code() . ' ' . $search_response->get_error_message() );
}
if ( ! $search_response instanceof WP_REST_Response ) {
	throw new RuntimeException( 'GravityView host search-bar API returned an unexpected response type.' );
}
$search_bar_result = $search_response->get_data();
if (
	! is_array( $search_bar_result )
	|| empty( $search_bar_result['widget_area'] )
	|| empty( $search_bar_result['widget_slot'] )
	|| 2 !== count( (array) ( $search_bar_result['fields'] ?? array() ) )
) {
	throw new RuntimeException( 'GravityView host search-bar API did not persist the two exact system-date fields.' );
}
$stored_widgets = GVCommon::get_directory_widgets( $view_id );
if (
	! isset( $stored_widgets[ $search_bar_result['widget_area'] ][ $search_bar_result['widget_slot'] ] )
	|| 'search_bar' !== (string) ( $stored_widgets[ $search_bar_result['widget_area'] ][ $search_bar_result['widget_slot'] ]['id'] ?? '' )
) {
	throw new RuntimeException( 'GravityView host search-bar API persisted an unexpected widget identity.' );
}

$page_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'WU008 G008 GravityView Date Qualification',
		'post_name'    => 'wu008-g008-gravityview-dates',
		'post_content' => sprintf( '[gravityview id="%d"]', $view_id ),
	),
	true
);
if ( is_wp_error( $page_id ) ) {
	throw new RuntimeException( $page_id->get_error_message() );
}
$page_id = (int) $page_id;

update_option( 'wu008_gv_qualification_view_id', $view_id, false );
update_option( 'wu008_gv_qualification_mode', 'native', false );
delete_option( 'wu008_gv_qualification_trace' );

$baseline = array(
	'schema_version'             => '1.0.0',
	'exact_persiangravity_head'  => (string) getenv( 'WU008_PGR_SHA' ),
	'exact_gravityview_version'  => '3.3.4',
	'exact_gravityview_sha256'   => (string) getenv( 'WU008_VIEW_SHA256' ),
	'site_timezone'              => wp_timezone_string(),
	'php_timezone'               => date_default_timezone_get(),
	'original_date_format'        => is_string( $original_date_format ) ? $original_date_format : '',
	'qualification_date_format'   => (string) get_option( 'date_format' ),
	'form_id'                    => $form_id,
	'view_id'                    => $view_id,
	'page_id'                    => $page_id,
	'page_url'                   => get_permalink( $page_id ),
	'search_bar'                 => $search_bar_result,
	'entries'                    => $fixtures,
);
file_put_contents(
	$artifact_dir . '/g008-gravityview-fixture-baseline.json',
	wp_json_encode( $baseline, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);

$manifest['g008_gravityview'] = array(
	'form_id'  => $form_id,
	'view_id'  => $view_id,
	'page_id'  => $page_id,
	'page_url' => get_permalink( $page_id ),
	'entries'  => $fixtures,
);
file_put_contents(
	$manifest_path,
	wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);

echo wp_json_encode( $baseline, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
