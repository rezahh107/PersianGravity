<?php
/**
 * Capture authentic Gravity Flow Status operational state for G-008 admission.
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
	throw new RuntimeException( 'G008 Status state requires artifact/manifest paths and enabled|disabled mode.' );
}
if ( ! defined( 'GRAVITY_FLOW_VERSION' ) || '3.1.0' !== GRAVITY_FLOW_VERSION ) {
	throw new RuntimeException( 'G008 Status qualification requires exact Gravity Flow 3.1.0.' );
}
if ( 'UTC' !== date_default_timezone_get() || 'Asia/Tehran' !== wp_timezone_string() ) {
	throw new RuntimeException( 'Qualified Status timezone contract drifted.' );
}

$status_file = WP_PLUGIN_DIR . '/gravityflow/includes/pages/class-status.php';
if ( ! class_exists( 'Gravity_Flow_Status_Table', false ) ) {
	require_once $status_file;
}
if ( ! class_exists( 'Gravity_Flow_Status_Table', false ) || ! class_exists( 'Gravity_Flow_Status', false ) ) {
	throw new RuntimeException( 'Exact Gravity Flow Status runtime could not be loaded.' );
}

$manifest = json_decode( (string) file_get_contents( $manifest_path ), true, 512, JSON_THROW_ON_ERROR );
$form_id  = (int) $manifest['g008_flow_form_id'];
wp_set_current_user( 1 );

$has_status_callback = static function ( $hook, $method ) {
	if ( ! class_exists( 'PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter', false ) ) {
		return false;
	}
	if ( ! isset( $GLOBALS['wp_filter'][ $hook ] ) ) {
		return false;
	}
	foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$function = $callback['function'];
			if (
				is_array( $function ) &&
				isset( $function[0], $function[1] ) &&
				$function[0] instanceof PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter &&
				$method === $function[1]
			) {
				return true;
			}
		}
	}
	return false;
};

$context_reset_hook_registered = $has_status_callback( 'gravityflow_status_args', 'reset_status_context' );
$table_proof_hook_registered  = $has_status_callback( 'gravityflow_entry_url_status_table', 'mark_status_table_entry' );
$value_hook_registered        = $has_status_callback( 'gravityflow_field_value_status_table', 'filter_status_value' );
$adapter_registered           = $context_reset_hook_registered && $table_proof_hook_registered && $value_hook_registered;
if ( ( 'enabled' === $mode ) !== $adapter_registered ) {
	throw new RuntimeException( 'Status adapter registration did not match jalali_presentation module state.' );
}

$status_query = static function ( $orderby, $order, $start_date = '', $end_date = '' ) use ( $form_id ) {
	$original_request = $_REQUEST;
	$_REQUEST         = array(
		'orderby' => $orderby,
		'order'   => $order,
	);
	$args = array(
		'constraint_filters' => array(
			'form_id'    => $form_id,
			'start_date' => $start_date,
			'end_date'   => $end_date,
		),
		'display_all'  => true,
		'last_updated' => true,
		'due_date'     => false,
		'per_page'     => 50,
	);
	$table = new Gravity_Flow_Status_Table( $args );
	$table->prepare_items();
	$ids      = array_map( 'intval', wp_list_pluck( $table->items, 'id' ) );
	$total    = (int) $table->get_pagination_arg( 'total_items' );
	$_REQUEST = $original_request;
	return array(
		'ids'   => $ids,
		'total' => $total,
	);
};

$status_queries = array(
	'default'                 => $status_query( 'date_created', 'desc' ),
	'date_created_asc'        => $status_query( 'date_created', 'asc' ),
	'date_created_desc'       => $status_query( 'date_created', 'desc' ),
	'workflow_timestamp_asc'  => $status_query( 'workflow_timestamp', 'asc' ),
	'workflow_timestamp_desc' => $status_query( 'workflow_timestamp', 'desc' ),
	'civil_day_2026_03_21'    => $status_query( 'date_created', 'asc', '2026-03-21', '2026-03-21' ),
);

$rest_settings = get_option( 'gravityformsaddon_gravityformswebapi_settings', array() );
if ( ! is_array( $rest_settings ) || empty( $rest_settings['enabled'] ) ) {
	throw new RuntimeException( 'Gravity Forms REST API v2 is not enabled for G008 Status proof.' );
}

global $wpdb;
$entry_table = GFFormsModel::get_entry_table_name();
$meta_table  = GFFormsModel::get_entry_meta_table_name();
$api         = new Gravity_Flow_API( $form_id );
$entries     = array();

foreach ( $manifest['g008_flow_entries'] as $fixture ) {
	$entry_id = (int) $fixture['id'];
	$entry    = GFAPI::get_entry( $entry_id );
	if ( is_wp_error( $entry ) ) {
		throw new RuntimeException( $entry->get_error_message() );
	}
	$current_step = $api->get_current_step( $entry );
	$assignees    = array();
	if ( $current_step ) {
		$assignees = array_map(
			static function ( $assignee ) {
				return $assignee->get_key();
			},
			$current_step->get_assignees()
		);
		sort( $assignees );
	}

	$db_date_created = $wpdb->get_var(
		$wpdb->prepare( "SELECT date_created FROM {$entry_table} WHERE id = %d", $entry_id )
	);
	$db_workflow_timestamp = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT meta_value FROM {$meta_table} WHERE entry_id = %d AND meta_key = %s",
			$entry_id,
			'workflow_timestamp'
		)
	);

	$rest_request  = new WP_REST_Request( 'GET', '/gf/v2/entries/' . $entry_id );
	$rest_response = rest_do_request( $rest_request );
	$rest_data     = $rest_response->get_data();
	if (
		200 !== $rest_response->get_status() ||
		! is_array( $rest_data ) ||
		! array_key_exists( 'date_created', $rest_data ) ||
		! array_key_exists( 'workflow_timestamp', $rest_data )
	) {
		throw new RuntimeException( 'Gravity Forms REST entry contract could not be proven for Status fixture ' . $entry_id . '.' );
	}

	$entries[] = array(
		'id'                         => $entry_id,
		'gfapi_date_created'         => (string) $entry['date_created'],
		'gfapi_workflow_timestamp'   => (int) gform_get_meta( $entry_id, 'workflow_timestamp' ),
		'db_date_created'            => (string) $db_date_created,
		'db_workflow_timestamp'      => (int) $db_workflow_timestamp,
		'rest_date_created'          => (string) $rest_data['date_created'],
		'rest_workflow_timestamp'    => (int) $rest_data['workflow_timestamp'],
		'workflow_step'              => (int) gform_get_meta( $entry_id, 'workflow_step' ),
		'workflow_final_status'      => (string) gform_get_meta( $entry_id, 'workflow_final_status' ),
		'assignees'                  => $assignees,
	);
}

$original_request = $_REQUEST;
$_REQUEST         = array();
$export_name      = 'g008-flow-status-' . $mode;
$export_args      = Gravity_Flow_Status::get_defaults();
$export_args['format']             = 'csv';
$export_args['file_name']          = $export_name;
$export_args['display_all']        = true;
$export_args['last_updated']       = true;
$export_args['due_date']           = false;
$export_args['constraint_filters'] = array(
	'form_id'    => $form_id,
	'start_date' => '',
	'end_date'   => '',
);
Gravity_Flow_Status::render( $export_args );
$_REQUEST = $original_request;

$upload_dir  = wp_upload_dir();
$export_path = trailingslashit( $upload_dir['basedir'] ) . $export_name . '.csv';
if ( ! is_readable( $export_path ) ) {
	throw new RuntimeException( 'Authentic Gravity Flow Status CSV was not produced.' );
}
$csv = (string) file_get_contents( $export_path );
@unlink( $export_path );

$csv_raw_sources_present = true;
$csv_jalali_absent       = true;
foreach ( $manifest['g008_flow_entries'] as $fixture ) {
	if (
		false === strpos( $csv, (string) $fixture['date_created'] ) ||
		false === strpos( $csv, (string) $fixture['workflow_timestamp'] )
	) {
		$csv_raw_sources_present = false;
	}
	if (
		false !== strpos( $csv, (string) $fixture['expected_created_jalali'] ) ||
		false !== strpos( $csv, (string) $fixture['expected_updated_jalali'] )
	) {
		$csv_jalali_absent = false;
	}
}
if ( ! $csv_raw_sources_present || ! $csv_jalali_absent ) {
	throw new RuntimeException( 'Status CSV did not preserve native raw system-date values.' );
}

$late_table_to_csv_native = false;
$late_csv_to_table_jalali = false;
$late_csv_to_table_native = false;

if ( 'enabled' === $mode ) {
	$late_table_to_csv_name = 'g008-flow-status-late-table-to-csv';
	$late_table_to_csv_args = Gravity_Flow_Status::get_defaults();
	$late_table_to_csv_args['format']             = 'table';
	$late_table_to_csv_args['file_name']          = $late_table_to_csv_name;
	$late_table_to_csv_args['display_all']        = true;
	$late_table_to_csv_args['last_updated']       = true;
	$late_table_to_csv_args['due_date']           = false;
	$late_table_to_csv_args['constraint_filters'] = array(
		'form_id'    => $form_id,
		'start_date' => '',
		'end_date'   => '',
	);

	$table_to_csv_mutator = static function ( $args ) {
		if ( is_array( $args ) ) {
			$args['format'] = 'csv';
		}
		return $args;
	};
	add_filter( 'gravityflow_status_args', $table_to_csv_mutator, PHP_INT_MAX, 1 );
	try {
		Gravity_Flow_Status::render( $late_table_to_csv_args );
	} finally {
		remove_filter( 'gravityflow_status_args', $table_to_csv_mutator, PHP_INT_MAX );
	}

	$late_table_to_csv_path = trailingslashit( $upload_dir['basedir'] ) . $late_table_to_csv_name . '.csv';
	if ( ! is_readable( $late_table_to_csv_path ) ) {
		throw new RuntimeException( 'Late table-to-csv mutation did not reach authentic CSV export.' );
	}
	$late_table_to_csv = (string) file_get_contents( $late_table_to_csv_path );
	@unlink( $late_table_to_csv_path );

	$late_table_to_csv_native = true;
	foreach ( $manifest['g008_flow_entries'] as $fixture ) {
		if (
			false === strpos( $late_table_to_csv, (string) $fixture['date_created'] ) ||
			false === strpos( $late_table_to_csv, (string) $fixture['workflow_timestamp'] ) ||
			false !== strpos( $late_table_to_csv, (string) $fixture['expected_created_jalali'] ) ||
			false !== strpos( $late_table_to_csv, (string) $fixture['expected_updated_jalali'] )
		) {
			$late_table_to_csv_native = false;
			break;
		}
	}

	$late_csv_to_table_args = Gravity_Flow_Status::get_defaults();
	$late_csv_to_table_args['format']             = 'csv';
	$late_csv_to_table_args['display_all']        = true;
	$late_csv_to_table_args['last_updated']       = true;
	$late_csv_to_table_args['due_date']           = false;
	$late_csv_to_table_args['constraint_filters'] = array(
		'form_id'    => $form_id,
		'start_date' => '',
		'end_date'   => '',
	);

	$csv_to_table_mutator = static function ( $args ) {
		if ( is_array( $args ) ) {
			$args['format'] = 'table';
		}
		return $args;
	};
	add_filter( 'gravityflow_status_args', $csv_to_table_mutator, PHP_INT_MAX, 1 );
	ob_start();
	try {
		Gravity_Flow_Status::render( $late_csv_to_table_args );
		$late_csv_to_table_html = (string) ob_get_contents();
	} finally {
		ob_end_clean();
		remove_filter( 'gravityflow_status_args', $csv_to_table_mutator, PHP_INT_MAX );
	}

	$late_csv_to_table_jalali = true;
	foreach ( $manifest['g008_flow_entries'] as $fixture ) {
		if (
			false === strpos( $late_csv_to_table_html, (string) $fixture['expected_created_jalali'] ) ||
			false === strpos( $late_csv_to_table_html, (string) $fixture['expected_updated_jalali'] )
		) {
			$late_csv_to_table_jalali = false;
			break;
		}
	}

	if ( ! $late_table_to_csv_native || ! $late_csv_to_table_jalali ) {
		throw new RuntimeException( 'Late Status format mutation falsification failed in enabled mode.' );
	}
} else {
	$late_table_to_csv_native = true;
	$late_csv_to_table_native = true;
}

$state = array(
	'schema_version'               => '1.0.0',
	'evidence_class'               => 'AUTHENTIC_GRAVITY_FLOW_STATUS_OPERATIONAL_STATE',
	'mode'                         => $mode,
	'gravityflow_version'          => GRAVITY_FLOW_VERSION,
	'site_timezone'                => wp_timezone_string(),
	'php_default_timezone'         => date_default_timezone_get(),
	'context_reset_hook_registered' => $context_reset_hook_registered,
	'table_proof_hook_registered'   => $table_proof_hook_registered,
	'value_hook_registered'         => $value_hook_registered,
	'adapter_registered'           => $adapter_registered,
	'status_queries'               => $status_queries,
	'entries'                      => $entries,
	'csv_sha256'                   => hash( 'sha256', $csv ),
	'csv_raw_sources_present'      => $csv_raw_sources_present,
	'csv_jalali_absent'             => $csv_jalali_absent,
	'late_table_to_csv_native'       => $late_table_to_csv_native,
	'late_csv_to_table_jalali'       => $late_csv_to_table_jalali,
	'late_csv_to_table_native'       => $late_csv_to_table_native,
);
file_put_contents(
	$artifact_dir . '/g008-flow-status-state-' . $mode . '.json',
	wp_json_encode( $state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);
echo wp_json_encode( $state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
