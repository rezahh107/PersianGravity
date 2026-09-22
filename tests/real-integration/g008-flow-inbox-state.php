<?php
/**
 * Capture same-fixture operational state with Jalali presentation enabled/disabled.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$artifact_dir  = getenv( 'WU008_ARTIFACT_DIR' );
$manifest_path = getenv( 'WU008_MANIFEST_PATH' );
$mode          = getenv( 'WU008_G008_MODE' );
if ( ! in_array( $mode, array( 'enabled', 'disabled' ), true ) ) {
	throw new RuntimeException( 'WU008_G008_MODE must be enabled or disabled.' );
}
$manifest = json_decode( (string) file_get_contents( $manifest_path ), true, 512, JSON_THROW_ON_ERROR );
$form_id  = (int) $manifest['g008_flow_form_id'];
wp_set_current_user( 1 );

$adapter_registered = false;
if ( isset( $GLOBALS['wp_filter']['gravityflow_inbox_field_value'] ) ) {
	foreach ( $GLOBALS['wp_filter']['gravityflow_inbox_field_value']->callbacks as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$function = $callback['function'];
			if (
				is_array( $function ) &&
				isset( $function[0], $function[1] ) &&
				$function[0] instanceof PGR_Gravity_Flow_Inbox_Jalali_Presentation_Adapter &&
				'filter_inbox_value' === $function[1]
			) {
				$adapter_registered = true;
			}
		}
	}
}
if ( ( 'enabled' === $mode ) !== $adapter_registered ) {
	throw new RuntimeException( 'Presentation adapter registration did not match module state.' );
}

$total   = 0;
$queried = Gravity_Flow_API::get_inbox_entries(
	array(
		'form_id'    => $form_id,
		'user_id'    => 1,
		'filter_key' => 'workflow_user_id_1',
		'paging'     => array( 'page_size' => 50 ),
	),
	$total
);
$query_ids = array_map( 'intval', wp_list_pluck( $queried, 'id' ) );
sort( $query_ids );

$entries = array();
$api     = new Gravity_Flow_API( $form_id );
foreach ( $manifest['g008_flow_entries'] as $fixture ) {
	$entry = GFAPI::get_entry( (int) $fixture['id'] );
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
	$entries[] = array(
		'id'                    => (int) $entry['id'],
		'date_created'          => (string) $entry['date_created'],
		'workflow_timestamp'    => (int) gform_get_meta( $entry['id'], 'workflow_timestamp' ),
		'workflow_step'         => (int) gform_get_meta( $entry['id'], 'workflow_step' ),
		'workflow_final_status' => (string) gform_get_meta( $entry['id'], 'workflow_final_status' ),
		'assignees'             => $assignees,
	);
}

$state = array(
	'evidence_class'       => 'AUTHENTIC_GRAVITY_FLOW_OPERATIONAL_STATE',
	'mode'                 => $mode,
	'gravityflow_version'  => defined( 'GRAVITY_FLOW_VERSION' ) ? GRAVITY_FLOW_VERSION : null,
	'adapter_registered'   => $adapter_registered,
	'site_timezone'        => wp_timezone_string(),
	'php_default_timezone' => date_default_timezone_get(),
	'query_total'          => (int) $total,
	'query_ids'            => $query_ids,
	'entries'              => $entries,
);
file_put_contents(
	$artifact_dir . '/g008-flow-inbox-state-' . $mode . '.json',
	wp_json_encode( $state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);
echo wp_json_encode( $state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
