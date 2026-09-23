<?php
/**
 * Deterministic Gravity Flow Inbox fixtures for G-008 system-date admission.
 *
 * Test-only: uses public product APIs and never patches vendor code.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$artifact_dir  = getenv( 'WU008_ARTIFACT_DIR' );
$manifest_path = getenv( 'WU008_MANIFEST_PATH' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir || ! is_string( $manifest_path ) || '' === $manifest_path ) {
	throw new RuntimeException( 'WU008 artifact and manifest paths are required.' );
}
if ( ! defined( 'GRAVITY_FLOW_VERSION' ) || '3.1.0' !== GRAVITY_FLOW_VERSION ) {
	throw new RuntimeException( 'G008 Flow Inbox qualification requires exact Gravity Flow 3.1.0.' );
}
if ( 'UTC' !== date_default_timezone_get() ) {
	throw new RuntimeException( 'WordPress/PHP default timezone must remain UTC for the qualified Gravity Flow native date() path.' );
}

update_option( 'timezone_string', 'Asia/Tehran' );
update_option( 'gmt_offset', 3.5 );
update_option( 'gravityformsaddon_gravityformswebapi_settings', array( 'enabled' => '1' ) );
if ( 'Asia/Tehran' !== wp_timezone_string() ) {
	throw new RuntimeException( 'Deterministic site timezone was not established.' );
}

$form = array(
	'title'  => 'G008 Gravity Flow Inbox System Dates',
	'fields' => array(
		array(
			'id'    => 1,
			'label' => 'Qualification Marker',
			'type'  => 'text',
		),
	),
);
$form_id = GFAPI::add_form( $form );
if ( is_wp_error( $form_id ) ) {
	throw new RuntimeException( $form_id->get_error_message() );
}

$api     = new Gravity_Flow_API( $form_id );
$step_id = $api->add_step(
	array(
		'step_name'       => 'G008 Inbox Approval',
		'step_type'       => 'approval',
		'description'     => 'Deterministic G008 runtime step.',
		'type'            => 'select',
		'assignees'       => array( 'user_id|1' ),
		'assignee_policy' => 'any',
	)
);
if ( ! $step_id ) {
	throw new RuntimeException( 'Could not create deterministic Gravity Flow approval step.' );
}

$fixtures = array(
	array(
		'key'                => 'alpha',
		'date_created'       => '2026-03-20 22:15:00',
		'workflow_timestamp' => 1774132200,
		'expected_created'   => '۱۴۰۵/۰۱/۰۱، ۰۱:۴۵',
		'expected_updated'   => '۱۴۰۵/۰۱/۰۲، ۰۲:۰۰',
	),
	array(
		'key'                => 'beta',
		'date_created'       => '2026-03-18 20:00:00',
		'workflow_timestamp' => 1773882000,
		'expected_created'   => '۱۴۰۴/۱۲/۲۷، ۲۳:۳۰',
		'expected_updated'   => '۱۴۰۴/۱۲/۲۸، ۰۴:۳۰',
	),
	array(
		'key'                => 'gamma',
		'date_created'       => '2026-03-22 01:00:00',
		'workflow_timestamp' => 1774224000,
		'expected_created'   => '۱۴۰۵/۰۱/۰۲، ۰۴:۳۰',
		'expected_updated'   => '۱۴۰۵/۰۱/۰۳، ۰۳:۳۰',
	),
);

$runtime_entries = array();
foreach ( $fixtures as $fixture ) {
	$entry_id = GFAPI::add_entry(
		array(
			'form_id'      => $form_id,
			'created_by'   => 1,
			'date_created' => $fixture['date_created'],
			'1'            => $fixture['key'],
		)
	);
	if ( is_wp_error( $entry_id ) ) {
		throw new RuntimeException( $entry_id->get_error_message() );
	}

	$api->process_workflow( $entry_id );
	gform_update_meta( $entry_id, 'workflow_timestamp', $fixture['workflow_timestamp'] );

	$entry = GFAPI::get_entry( $entry_id );
	if ( is_wp_error( $entry ) ) {
		throw new RuntimeException( $entry->get_error_message() );
	}
	$current_step = $api->get_current_step( $entry );
	if ( ! $current_step ) {
		throw new RuntimeException( 'Fixture did not enter an authentic Gravity Flow step.' );
	}
	$assignee_keys = array_map(
		static function ( $assignee ) {
			return $assignee->get_key();
		},
		$current_step->get_assignees()
	);
	sort( $assignee_keys );

	$native_last_updated_source = date( 'Y-m-d H:i:s', $fixture['workflow_timestamp'] );
	$native_date_created        = Gravity_Flow_Common::format_date( $fixture['date_created'], '', true, true );
	$native_last_updated        = $fixture['date_created'] !== $native_last_updated_source
		? Gravity_Flow_Common::format_date( $native_last_updated_source, '', true, true )
		: '-';

	$runtime_entries[] = array(
		'key'                     => $fixture['key'],
		'id'                      => (int) $entry_id,
		'date_created'            => (string) $entry['date_created'],
		'workflow_timestamp'      => (int) gform_get_meta( $entry_id, 'workflow_timestamp' ),
		'workflow_step'           => (int) gform_get_meta( $entry_id, 'workflow_step' ),
		'workflow_final_status'   => (string) gform_get_meta( $entry_id, 'workflow_final_status' ),
		'assignees'               => $assignee_keys,
		'expected_created_jalali' => $fixture['expected_created'],
		'expected_updated_jalali' => $fixture['expected_updated'],
		'expected_created_native' => $native_date_created,
		'expected_updated_native' => $native_last_updated,
	);
}

$page_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'G008 Gravity Flow Inbox System Dates',
		'post_name'    => 'g008-gravityflow-inbox-system-dates',
		'post_content' => sprintf( '[gravityflow page="inbox" form="%d" last_updated="true" due_date="false" display_filter="true"]', (int) $form_id ),
	),
	true
);
if ( is_wp_error( $page_id ) ) {
	throw new RuntimeException( $page_id->get_error_message() );
}

$status_page_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'G008 Gravity Flow Status System Dates',
		'post_name'    => 'g008-gravityflow-status-system-dates',
		'post_content' => sprintf( '[gravityflow page="status" form="%d" last_updated="true" due_date="false"]', (int) $form_id ),
	),
	true
);
if ( is_wp_error( $status_page_id ) ) {
	throw new RuntimeException( $status_page_id->get_error_message() );
}

require_once PGR_PATH . 'includes/class-pgr-module-registry.php';
if ( ! PGR_Module_Registry::set_enabled( 'jalali_presentation', true ) ) {
	throw new RuntimeException( 'Could not enable jalali_presentation for the next HTTP request.' );
}

$manifest = json_decode( (string) file_get_contents( $manifest_path ), true, 512, JSON_THROW_ON_ERROR );
$manifest['schema_version']                 = '1.5.0';
$manifest['g008_flow_inbox_url']            = add_query_arg( 'page_id', (int) $page_id, home_url( '/' ) );
$manifest['g008_flow_status_url']           = add_query_arg( 'page_id', (int) $status_page_id, home_url( '/' ) );
$manifest['g008_flow_form_id']              = (int) $form_id;
$manifest['g008_flow_step_id']              = (int) $step_id;
$manifest['g008_flow_site_timezone']        = wp_timezone_string();
$manifest['g008_flow_php_default_timezone'] = date_default_timezone_get();
$manifest['g008_flow_entries']              = $runtime_entries;
file_put_contents(
	$manifest_path,
	wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);

file_put_contents(
	$artifact_dir . '/g008-flow-inbox-fixture-baseline.json',
	wp_json_encode(
		array(
			'evidence_class'       => 'AUTHENTIC_GRAVITY_FLOW_FIXTURE_BASELINE',
			'gravityflow_version'  => GRAVITY_FLOW_VERSION,
			'site_timezone'        => wp_timezone_string(),
			'php_default_timezone' => date_default_timezone_get(),
			'form_id'              => (int) $form_id,
			'step_id'              => (int) $step_id,
			'entries'              => $runtime_entries,
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	) . "\n"
);

echo wp_json_encode( array( 'form_id' => (int) $form_id, 'step_id' => (int) $step_id, 'entry_count' => count( $runtime_entries ) ) ) . "\n";
