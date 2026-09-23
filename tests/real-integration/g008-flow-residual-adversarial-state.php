<?php
/**
 * Operational/storage and Timeline mutation qualification for residual G-008.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$artifact_dir  = getenv( 'WU008_ARTIFACT_DIR' );
$manifest_path = getenv( 'WU008_MANIFEST_PATH' );
$mode          = getenv( 'WU008_G008_MODE' );
if (
	! is_string( $artifact_dir ) || '' === $artifact_dir ||
	! is_string( $manifest_path ) || '' === $manifest_path ||
	! in_array( $mode, array( 'enabled', 'disabled' ), true )
) {
	throw new RuntimeException( 'Residual adversarial state requires artifact/manifest paths and enabled|disabled mode.' );
}
if ( ! defined( 'GRAVITY_FLOW_VERSION' ) || '3.1.0' !== GRAVITY_FLOW_VERSION ) {
	throw new RuntimeException( 'Residual adversarial state requires exact Gravity Flow 3.1.0.' );
}
if ( 'UTC' !== date_default_timezone_get() || 'Asia/Tehran' !== wp_timezone_string() ) {
	throw new RuntimeException( 'Residual adversarial state timezone contract drifted.' );
}

$manifest = json_decode( (string) file_get_contents( $manifest_path ), true, 512, JSON_THROW_ON_ERROR );
wp_set_current_user( 1 );
$form_id  = (int) $manifest['g008_flow_form_id'];
$entry_id = (int) $manifest['g008_flow_entry_detail_candidate_entry_id'];
$entry    = GFAPI::get_entry( $entry_id );
if ( is_wp_error( $entry ) ) {
	throw new RuntimeException( $entry->get_error_message() );
}

$module_enabled = class_exists( 'PGR_Module_Registry', false ) && PGR_Module_Registry::is_enabled( 'jalali_presentation' );
if ( ( 'enabled' === $mode ) !== $module_enabled ) {
	throw new RuntimeException( 'Residual adversarial state module state mismatch.' );
}

$api          = new Gravity_Flow_API( $form_id );
$current_step = $api->get_current_step( $entry );
if ( ! $current_step ) {
	throw new RuntimeException( 'Residual candidate current step is unavailable.' );
}

$due_timestamp        = (int) $current_step->get_due_date_timestamp();
$expiration_timestamp = (int) $current_step->get_expiration_timestamp();
$overdue              = (bool) $current_step->is_overdue();
$expired              = (bool) $current_step->is_expired();

global $wpdb;
$entry_table = GFFormsModel::get_entry_table_name();
$notes_table = GFFormsModel::get_entry_notes_table_name();
$db_entry = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT id, form_id, date_created, created_by, status FROM {$entry_table} WHERE id = %d",
		$entry_id
	),
	ARRAY_A
);
$stored_notes = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT id, entry_id, user_id, user_name, date_created, value, note_type, sub_type FROM {$notes_table} WHERE entry_id = %d AND note_type = %s ORDER BY id ASC",
		$entry_id,
		'gravityflow'
	),
	ARRAY_A
);

$rest_request  = new WP_REST_Request( 'GET', '/gf/v2/entries/' . $entry_id );
$rest_response = rest_do_request( $rest_request );
$rest_data     = $rest_response->get_data();
if ( 200 !== $rest_response->get_status() || ! is_array( $rest_data ) ) {
	throw new RuntimeException( 'Residual candidate REST entry contract could not be proven.' );
}

$search_criteria = array(
	'status'        => 'active',
	'field_filters' => array(
		array(
			'key'      => '1',
			'value'    => 'entry-detail-candidate',
			'operator' => 'is',
		),
	),
);
$sorting = array(
	'key'       => 'date_created',
	'direction' => 'ASC',
);
$paging  = array( 'offset' => 0, 'page_size' => 20 );
$total   = 0;
$queried = GFAPI::get_entries( $form_id, $search_criteria, $sorting, $paging, $total );
if ( is_wp_error( $queried ) ) {
	throw new RuntimeException( $queried->get_error_message() );
}
$query_ids = array_map( 'intval', wp_list_pluck( $queried, 'id' ) );

// Produce an authentic Flow Status CSV with dates enabled to prove that the
// Entry Detail marker never leaks into export paths.
if ( ! class_exists( 'Gravity_Flow_Status', false ) ) {
	require_once WP_PLUGIN_DIR . '/gravityflow/includes/pages/class-status.php';
}
$export_name = 'g008-entry-detail-residual-export-' . $mode;
$export_args = Gravity_Flow_Status::get_defaults();
$export_args['format']             = 'csv';
$export_args['file_name']          = $export_name;
$export_args['display_all']        = true;
$export_args['last_updated']       = true;
$export_args['due_date']           = true;
$export_args['constraint_filters'] = array(
	'form_id'    => $form_id,
	'start_date' => '',
	'end_date'   => '',
);
Gravity_Flow_Status::render( $export_args );
$upload_dir  = wp_upload_dir();
$export_path = trailingslashit( $upload_dir['basedir'] ) . $export_name . '.csv';
if ( ! is_readable( $export_path ) ) {
	throw new RuntimeException( 'Residual Flow Status CSV was not produced.' );
}
$csv = (string) file_get_contents( $export_path );
@unlink( $export_path );
if ( false !== strpos( $csv, 'PGRG008ENTRYDETAILMARKER' ) ) {
	throw new RuntimeException( 'Entry Detail marker leaked into Flow Status CSV.' );
}

// Canonical timeline data as consumed without an experimental filter.
$canonical_timeline = Gravity_Flow_Common::get_timeline_notes( $entry );
$canonical = array_map(
	static function ( $note ) {
		return array(
			'id'           => (int) $note->id,
			'date_created' => (string) $note->date_created,
			'value'        => (string) $note->value,
			'note_type'    => isset( $note->note_type ) ? (string) $note->note_type : 'initial',
		);
	},
	$canonical_timeline
);
$native_text_timeline = Gravity_Flow_Common::get_timeline( $entry );

// Experiment A: clone display data and add a separate date-display property.
// If downstream ignores it, no safe separate display property exists.
$display_property_filter = static function ( $notes ) {
	$result = array();
	foreach ( $notes as $note ) {
		$copy = clone $note;
		$copy->pgr_jalali_display_date = '۱۴۰۵/۰۱/۰۱';
		$result[] = $copy;
	}
	return $result;
};
add_filter( 'gravityflow_timeline_notes', $display_property_filter, PHP_INT_MAX, 2 );
$display_property_notes = Gravity_Flow_Common::get_timeline_notes( $entry );
$display_property_text  = Gravity_Flow_Common::get_timeline( $entry );
remove_filter( 'gravityflow_timeline_notes', $display_property_filter, PHP_INT_MAX );

$display_property_canonical = array_map(
	static function ( $note ) {
		return array(
			'id'                     => (int) $note->id,
			'date_created'           => (string) $note->date_created,
			'value'                  => (string) $note->value,
			'pgr_jalali_display_date'=> isset( $note->pgr_jalali_display_date ) ? (string) $note->pgr_jalali_display_date : null,
		);
	},
	$display_property_notes
);

// Experiment B: clone and mutate the only downstream-consumed date property.
// This must change rendered output while storage remains untouched, proving
// that date_created itself is the Gregorian representation contract.
$date_created_filter = static function ( $notes ) {
	$result = array();
	foreach ( $notes as $note ) {
		$copy = clone $note;
		$copy->date_created = '2031-01-02 03:04:05';
		$result[] = $copy;
	}
	return $result;
};
add_filter( 'gravityflow_timeline_notes', $date_created_filter, PHP_INT_MAX, 2 );
$mutated_notes = Gravity_Flow_Common::get_timeline_notes( $entry );
$mutated_text  = Gravity_Flow_Common::get_timeline( $entry );
remove_filter( 'gravityflow_timeline_notes', $date_created_filter, PHP_INT_MAX );

$mutated_canonical = array_map(
	static function ( $note ) {
		return array(
			'id'           => (int) $note->id,
			'date_created' => (string) $note->date_created,
			'value'        => (string) $note->value,
		);
	},
	$mutated_notes
);

$storage_after = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT id, entry_id, user_id, user_name, date_created, value, note_type, sub_type FROM {$notes_table} WHERE entry_id = %d AND note_type = %s ORDER BY id ASC",
		$entry_id,
		'gravityflow'
	),
	ARRAY_A
);

$expected_stored = $manifest['g008_flow_timeline_stored_notes'];
$normalize_stored = static function ( $rows ) {
	return array_map(
		static function ( $row ) {
			return array(
				'id'           => (int) $row['id'],
				'date_created' => (string) $row['date_created'],
				'value'        => (string) $row['value'],
				'note_type'    => (string) $row['note_type'],
				'sub_type'     => (string) $row['sub_type'],
			);
		},
		$rows
	);
};
$stored_before_normalized = $normalize_stored( $stored_notes );
$stored_after_normalized  = $normalize_stored( $storage_after );
$expected_normalized      = $normalize_stored( $expected_stored );

$timeline_order = array_map( static function ( $row ) { return (int) $row['id']; }, $canonical );
$expected_order = array_map( 'intval', wp_list_pluck( $manifest['g008_flow_timeline_multi_note'], 'id' ) );

if ( $stored_before_normalized !== $expected_normalized || $stored_after_normalized !== $expected_normalized ) {
	throw new RuntimeException( 'Timeline experiments mutated authentic stored note rows.' );
}
if ( $timeline_order !== $expected_order ) {
	throw new RuntimeException( 'Timeline canonical note ordering drifted.' );
}
if ( $display_property_text !== $native_text_timeline ) {
	throw new RuntimeException( 'Unexpected downstream consumption of separate Timeline display property.' );
}
if ( $mutated_text === $native_text_timeline ) {
	throw new RuntimeException( 'Mutating Timeline date_created did not alter downstream rendering as expected.' );
}
if (
	array_map( static function ( $row ) { return $row['id']; }, $display_property_canonical ) !== $timeline_order ||
	array_map( static function ( $row ) { return $row['value']; }, $display_property_canonical ) !== array_map( static function ( $row ) { return $row['value']; }, $canonical )
) {
	throw new RuntimeException( 'Timeline display-property experiment changed IDs/order/bodies.' );
}
if (
	array_map( static function ( $row ) { return $row['id']; }, $mutated_canonical ) !== $timeline_order ||
	array_map( static function ( $row ) { return $row['value']; }, $mutated_canonical ) !== array_map( static function ( $row ) { return $row['value']; }, $canonical )
) {
	throw new RuntimeException( 'Timeline date_created experiment changed IDs/order/bodies.' );
}

$state = array(
	'schema_version'               => '1.0.0',
	'evidence_class'               => 'AUTHENTIC_G008_RESIDUAL_OPERATIONAL_AND_TIMELINE_STATE',
	'mode'                         => $mode,
	'exact_persiangravity_commit'  => getenv( 'WU008_PGR_SHA' ) ?: null,
	'exact_gravityflow_version'    => GRAVITY_FLOW_VERSION,
	'exact_gravityflow_package_sha256' => getenv( 'WU008_FLOW_SHA256' ) ?: null,
	'site_timezone'                => wp_timezone_string(),
	'php_default_timezone'         => date_default_timezone_get(),
	'candidate_entry'              => array(
		'gfapi'                  => array(
			'id'                 => (int) $entry['id'],
			'form_id'            => (int) $entry['form_id'],
			'date_created'       => (string) $entry['date_created'],
			'workflow_timestamp' => (int) gform_get_meta( $entry_id, 'workflow_timestamp' ),
		),
		'database'               => $db_entry,
		'rest'                   => array(
			'id'                 => (int) $rest_data['id'],
			'form_id'            => (int) $rest_data['form_id'],
			'date_created'       => (string) $rest_data['date_created'],
			'workflow_timestamp' => (int) $rest_data['workflow_timestamp'],
		),
		'workflow_step'          => (int) gform_get_meta( $entry_id, 'workflow_step' ),
		'workflow_final_status'  => (string) gform_get_meta( $entry_id, 'workflow_final_status' ),
		'due_timestamp'          => $due_timestamp,
		'expiration_timestamp'   => $expiration_timestamp,
		'overdue'                => $overdue,
		'expired'                => $expired,
	),
	'query'                        => array(
		'total' => (int) $total,
		'ids'   => $query_ids,
	),
	'csv'                          => array(
		'sha256'        => hash( 'sha256', $csv ),
		'marker_absent' => false === strpos( $csv, 'PGRG008ENTRYDETAILMARKER' ),
	),
	'timeline'                     => array(
		'stored_before'                  => $stored_before_normalized,
		'stored_after'                   => $stored_after_normalized,
		'canonical'                      => $canonical,
		'native_text_sha256'             => hash( 'sha256', $native_text_timeline ),
		'display_property_ignored'       => $display_property_text === $native_text_timeline,
		'display_property_records'       => $display_property_canonical,
		'date_created_is_consumed'       => $mutated_text !== $native_text_timeline,
		'date_created_mutated_records'   => $mutated_canonical,
		'mutated_text_sha256'            => hash( 'sha256', $mutated_text ),
		'storage_equal_after_experiments'=> $stored_after_normalized === $expected_normalized,
		'ids_order_bodies_preserved'     => true,
	),
);
file_put_contents(
	$artifact_dir . '/g008-residual-adversarial-state-' . $mode . '.json',
	wp_json_encode( $state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "
"
);
echo wp_json_encode( $state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "
";
