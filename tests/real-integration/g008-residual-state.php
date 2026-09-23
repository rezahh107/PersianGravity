<?php
/** Capture residual G-008 operational/storage state for enabled/disabled comparison. */
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }

$artifact_dir  = getenv( 'WU008_ARTIFACT_DIR' );
$manifest_path = getenv( 'WU008_MANIFEST_PATH' );
$mode          = getenv( 'WU008_G008_MODE' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir || ! is_string( $manifest_path ) || '' === $manifest_path || ! in_array( $mode, array( 'enabled', 'disabled' ), true ) ) {
	throw new RuntimeException( 'Residual state requires artifact/manifest paths and enabled|disabled mode.' );
}
if ( ! defined( 'GRAVITY_FLOW_VERSION' ) || '3.1.0' !== GRAVITY_FLOW_VERSION || ! class_exists( 'GFForms' ) || '3.1.1.1' !== (string) GFForms::$version ) {
	throw new RuntimeException( 'Residual state exact product identity drifted.' );
}
if ( 'Asia/Tehran' !== wp_timezone_string() || 'UTC' !== date_default_timezone_get() ) {
	throw new RuntimeException( 'Residual state timezone identity drifted.' );
}

wp_set_current_user( 1 );
$manifest = json_decode( (string) file_get_contents( $manifest_path ), true, 512, JSON_THROW_ON_ERROR );

$callback_registered = static function ( string $hook, string $class, string $method ): bool {
	if ( ! isset( $GLOBALS['wp_filter'][ $hook ] ) ) { return false; }
	foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$fn = $callback['function'];
			if ( is_array( $fn ) && isset( $fn[0], $fn[1] ) && is_object( $fn[0] ) && $fn[0] instanceof $class && $method === $fn[1] ) { return true; }
		}
	}
	return false;
};

$production_entry_detail_adapter_registered = class_exists( 'PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter', false )
	&& $callback_registered( 'gravityflow_date_format_entry_detail', 'PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter', 'filter_date_format' )
	&& $callback_registered( 'date_i18n', 'PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter', 'filter_date_i18n' );

$candidate = $manifest['g008_entry_detail_candidate'] ?? null;
$schedule  = $manifest['g008_schedule_branches'] ?? null;
$timeline  = $manifest['g008_timeline_multinote'] ?? null;
if ( ! is_array( $candidate ) || ! is_array( $candidate['entries'] ?? null ) || ! is_array( $schedule ) || ! is_array( $schedule['entries'] ?? null ) || ! is_array( $timeline ) ) {
	throw new RuntimeException( 'Residual manifest fixture is incomplete.' );
}

$rest_settings = get_option( 'gravityformsaddon_gravityformswebapi_settings', array() );
if ( ! is_array( $rest_settings ) || empty( $rest_settings['enabled'] ) ) {
	throw new RuntimeException( 'Gravity Forms REST API v2 is required for residual state proof.' );
}

global $wpdb;
$entry_table = GFFormsModel::get_entry_table_name();
$meta_table  = GFFormsModel::get_entry_meta_table_name();
$notes_table = GFFormsModel::get_lead_notes_table_name();

$candidate_api = new Gravity_Flow_API( (int) $candidate['form_id'] );
$candidate_state = array();
foreach ( $candidate['entries'] as $fixture ) {
	$entry_id = (int) $fixture['id'];
	$entry = GFAPI::get_entry( $entry_id );
	if ( is_wp_error( $entry ) ) { throw new RuntimeException( $entry->get_error_message() ); }
	$step = $candidate_api->get_current_step( $entry );
	if ( ! $step ) { throw new RuntimeException( 'Residual candidate current step missing.' ); }
	$due = (int) $step->get_due_date_timestamp();
	$expiration = (int) $step->get_expiration_timestamp();
	if ( $due !== (int) $fixture['due_timestamp'] || $expiration !== (int) $fixture['expiration_timestamp'] ) {
		throw new RuntimeException( 'Residual candidate operational timestamp authority drifted.' );
	}
	$assignees = array_map( static function ( $assignee ) { return $assignee->get_key(); }, $step->get_assignees() );
	sort( $assignees );
	$db_date_created = $wpdb->get_var( $wpdb->prepare( "SELECT date_created FROM {$entry_table} WHERE id=%d", $entry_id ) );
	$db_workflow_timestamp = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$meta_table} WHERE entry_id=%d AND meta_key=%s", $entry_id, 'workflow_timestamp' ) );
	$rest = rest_do_request( new WP_REST_Request( 'GET', '/gf/v2/entries/' . $entry_id ) );
	$rest_data = $rest->get_data();
	if ( 200 !== $rest->get_status() || ! is_array( $rest_data ) ) { throw new RuntimeException( 'Residual candidate REST read failed.' ); }
	$candidate_state[] = array(
		'id' => $entry_id,
		'gfapi_date_created' => (string) $entry['date_created'],
		'db_date_created' => (string) $db_date_created,
		'rest_date_created' => (string) ( $rest_data['date_created'] ?? '' ),
		'workflow_timestamp' => (int) gform_get_meta( $entry_id, 'workflow_timestamp' ),
		'db_workflow_timestamp' => (int) $db_workflow_timestamp,
		'rest_workflow_timestamp' => (int) ( $rest_data['workflow_timestamp'] ?? 0 ),
		'workflow_step' => (int) gform_get_meta( $entry_id, 'workflow_step' ),
		'workflow_final_status' => (string) gform_get_meta( $entry_id, 'workflow_final_status' ),
		'assignees' => $assignees,
		'due_timestamp' => $due,
		'expiration_timestamp' => $expiration,
		'is_overdue' => (bool) $step->is_overdue(),
		'is_expired' => (bool) $step->is_expired(),
	);
}

$schedule_api = new Gravity_Flow_API( (int) $schedule['form_id'] );
$schedule_state = array();
foreach ( $schedule['entries'] as $fixture ) {
	$entry = GFAPI::get_entry( (int) $fixture['id'] );
	if ( is_wp_error( $entry ) ) { throw new RuntimeException( $entry->get_error_message() ); }
	$step = $schedule_api->get_current_step( $entry );
	if ( ! $step ) { throw new RuntimeException( 'Schedule current step missing.' ); }
	$timestamp = (int) $step->get_schedule_timestamp();
	if ( $timestamp !== (int) $fixture['scheduled_timestamp'] ) { throw new RuntimeException( 'Schedule timestamp drifted.' ); }
	$schedule_state[] = array(
		'id' => (int) $entry['id'],
		'step_id' => (int) $fixture['step_id'],
		'workflow_step' => (int) gform_get_meta( $entry['id'], 'workflow_step' ),
		'workflow_final_status' => (string) gform_get_meta( $entry['id'], 'workflow_final_status' ),
		'schedule_type' => (string) $step->schedule_type,
		'scheduled' => (bool) $step->scheduled,
		'is_queued' => (bool) $step->is_queued(),
		'schedule_timestamp' => $timestamp,
		'validate_schedule' => (bool) $step->validate_schedule(),
	);
}

$timeline_entry = GFAPI::get_entry( (int) $timeline['entry_id'] );
if ( is_wp_error( $timeline_entry ) ) { throw new RuntimeException( $timeline_entry->get_error_message() ); }
$timeline_notes = Gravity_Flow_Common::get_timeline_notes( $timeline_entry );
$timeline_runtime = array_map(
	static function ( $note ) {
		return array( 'id' => (int) $note->id, 'date_created' => (string) $note->date_created, 'value' => (string) $note->value );
	},
	$timeline_notes
);
$stored_rows = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT id, date_created, value, note_type, sub_type FROM {$notes_table} WHERE lead_id=%d AND note_type=%s ORDER BY id ASC",
		(int) $timeline['entry_id'],
		'gravityflow'
	),
	ARRAY_A
);

$state = array(
	'schema_version' => '1.0.0',
	'evidence_class' => 'G008_RESIDUAL_AUTHENTIC_OPERATIONAL_STATE',
	'mode' => $mode,
	'exact_persiangravity_commit' => getenv( 'WU008_PGR_SHA' ) ?: null,
	'exact_persiangravity_package_sha256' => getenv( 'WU008_PGR_PACKAGE_SHA256' ) ?: null,
	'exact_gravityflow_version' => GRAVITY_FLOW_VERSION,
	'exact_gravityflow_package_sha256' => getenv( 'WU008_FLOW_SHA256' ) ?: null,
	'site_timezone' => wp_timezone_string(),
	'php_default_timezone' => date_default_timezone_get(),
	'production_entry_detail_adapter_registered' => $production_entry_detail_adapter_registered,
	'candidate' => $candidate_state,
	'schedule' => $schedule_state,
	'timeline' => array( 'runtime' => $timeline_runtime, 'stored_rows' => $stored_rows ),
);
file_put_contents( $artifact_dir . '/g008-residual-state-' . $mode . '.json', wp_json_encode( $state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
echo wp_json_encode( $state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
