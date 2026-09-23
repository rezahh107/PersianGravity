<?php
/**
 * Deterministic Gravity Flow Inbox fixtures for G-008 system-date admission.
 *
 * Test-only: uses public product APIs/hooks and never patches vendor code.
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

$api = new Gravity_Flow_API( $form_id );
$step_id = $api->add_step(
	array(
		'step_name'                => 'G008 Inbox Approval With Due Date',
		'step_type'                => 'approval',
		'description'              => 'Deterministic G008 runtime step with host-owned due date.',
		'type'                     => 'select',
		'assignees'                => array( 'user_id|1' ),
		'assignee_policy'          => 'any',
		'due_date'                 => true,
		'due_date_type'            => 'delay',
		'due_date_delay_offset'    => 1,
		'due_date_delay_unit'      => 'days',
		'due_date_highlight_type'  => 'color',
		'due_date_highlight_color' => '#cc0000',
	)
);
if ( ! $step_id ) {
	throw new RuntimeException( 'Could not create deterministic Gravity Flow approval step with due date.' );
}

$no_due_step_id = $api->add_step(
	array(
		'step_name'       => 'G008 Inbox Approval Without Due Date',
		'step_type'       => 'approval',
		'description'     => 'Deterministic G008 native no-due-date sentinel step.',
		'type'            => 'select',
		'assignees'       => array( 'user_id|1' ),
		'assignee_policy' => 'any',
		'due_date'        => false,
	)
);
if ( ! $no_due_step_id ) {
	throw new RuntimeException( 'Could not create deterministic Gravity Flow no-due-date approval step.' );
}

$fixtures = array(
	array(
		'key'                 => 'alpha',
		'date_created'        => '2026-03-20 22:15:00',
		'workflow_timestamp'  => 1774132200,
		'due_timestamp'       => 1774044900,
		'expected_overdue'    => true,
		'expected_created'    => '۱۴۰۵/۰۱/۰۱، ۰۱:۴۵',
		'expected_updated'    => '۱۴۰۵/۰۱/۰۲، ۰۲:۰۰',
		'expected_due'        => '۱۴۰۵/۰۱/۰۱، ۰۱:۴۵',
		'use_no_due_step'     => false,
	),
	array(
		'key'                 => 'beta',
		'date_created'        => '2026-03-18 20:00:00',
		'workflow_timestamp'  => 1773882000,
		'due_timestamp'       => 1900275300,
		'expected_overdue'    => false,
		'expected_created'    => '۱۴۰۴/۱۲/۲۷، ۲۳:۳۰',
		'expected_updated'    => '۱۴۰۴/۱۲/۲۸، ۰۴:۳۰',
		'expected_due'        => '۱۴۰۹/۰۱/۰۱، ۰۱:۴۵',
		'use_no_due_step'     => false,
	),
	array(
		'key'                 => 'gamma',
		'date_created'        => '2026-03-22 01:00:00',
		'workflow_timestamp'  => 1774224000,
		'due_timestamp'       => null,
		'expected_overdue'    => false,
		'expected_created'    => '۱۴۰۵/۰۱/۰۲، ۰۴:۳۰',
		'expected_updated'    => '۱۴۰۵/۰۱/۰۳، ۰۳:۳۰',
		'expected_due'        => '-',
		'use_no_due_step'     => true,
	),
);

$runtime_entries = array();
$due_map         = array();
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
	if ( $fixture['use_no_due_step'] ) {
		gform_update_meta( $entry_id, 'workflow_step', $no_due_step_id );
	} else {
		$due_map[ (int) $entry_id ] = (int) $fixture['due_timestamp'];
	}

	$runtime_entries[] = array(
		'key'                     => $fixture['key'],
		'id'                      => (int) $entry_id,
		'date_created'            => $fixture['date_created'],
		'workflow_timestamp'      => (int) $fixture['workflow_timestamp'],
		'expected_created_jalali' => $fixture['expected_created'],
		'expected_updated_jalali' => $fixture['expected_updated'],
		'expected_due_jalali'     => $fixture['expected_due'],
		'expected_due_timestamp'  => null === $fixture['due_timestamp'] ? 0 : (int) $fixture['due_timestamp'],
		'expected_overdue'        => (bool) $fixture['expected_overdue'],
		'use_no_due_step'         => (bool) $fixture['use_no_due_step'],
	);
}

update_option( 'pgr_g008_due_fixture_map', $due_map, false );
wp_mkdir_p( WPMU_PLUGIN_DIR );
$fixture_plugin = <<<'PHP'
<?php
/**
 * Test-only deterministic due-date authority plus presentation re-entry probe.
 *
 * Counts are request-local. If production presentation code incorrectly invokes
 * the operational due-date getter while gravityflow_inbox_field_value is active,
 * the callback returns a different timestamp so the browser regression detects
 * both the extra invocation and the wrong source instant.
 */
$GLOBALS['pgr_wu008_due_filter_evidence'] = array(
	'total'                       => 0,
	'nested_inbox_presentation'   => 0,
	'by_entry'                    => array(),
);

add_filter(
	'gravityflow_step_due_date_timestamp',
	static function ( $timestamp, $due_date_type, $step ) {
		unset( $due_date_type );
		$map = get_option( 'pgr_g008_due_fixture_map', array() );
		if ( ! is_array( $map ) || ! is_object( $step ) || ! is_callable( array( $step, 'get_entry_id' ) ) ) {
			return $timestamp;
		}

		$entry_id = (int) $step->get_entry_id();
		if ( $entry_id <= 0 ) {
			return $timestamp;
		}

		$evidence = &$GLOBALS['pgr_wu008_due_filter_evidence'];
		++$evidence['total'];
		if ( ! isset( $evidence['by_entry'][ $entry_id ] ) ) {
			$evidence['by_entry'][ $entry_id ] = 0;
		}
		++$evidence['by_entry'][ $entry_id ];

		$value = isset( $map[ $entry_id ] ) ? (int) $map[ $entry_id ] : $timestamp;
		if ( doing_filter( 'gravityflow_inbox_field_value' ) ) {
			++$evidence['nested_inbox_presentation'];
			return is_int( $value ) ? $value + DAY_IN_SECONDS : $value;
		}

		return $value;
	},
	PHP_INT_MAX,
	3
);

add_action(
	'wp_footer',
	static function () {
		$mode = isset( $_GET['pgr_g008_mode'] ) ? (string) $_GET['pgr_g008_mode'] : '';
		if ( ! in_array( $mode, array( 'enabled', 'disabled' ), true ) ) {
			return;
		}

		$evidence = $GLOBALS['pgr_wu008_due_filter_evidence'];
		ksort( $evidence['by_entry'] );
		$evidence['mode'] = $mode;

		echo '<script>window.pgrG008DueInvocationEvidence=' . wp_json_encode( $evidence ) . ';</script>';
	},
	PHP_INT_MAX
);
PHP;
file_put_contents( WPMU_PLUGIN_DIR . '/pgr-wu008-g008-due-date-fixture.php', $fixture_plugin . "\n" );

$runtime_due_filter = static function ( $timestamp, $due_date_type, $step ) use ( $due_map ) {
	unset( $due_date_type );
	if ( ! is_object( $step ) || ! is_callable( array( $step, 'get_entry_id' ) ) ) {
		return $timestamp;
	}
	$entry_id = (int) $step->get_entry_id();
	return isset( $due_map[ $entry_id ] ) ? (int) $due_map[ $entry_id ] : $timestamp;
};
add_filter( 'gravityflow_step_due_date_timestamp', $runtime_due_filter, PHP_INT_MAX, 3 );

foreach ( $runtime_entries as &$runtime_entry ) {
	$entry = GFAPI::get_entry( $runtime_entry['id'] );
	if ( is_wp_error( $entry ) ) {
		throw new RuntimeException( $entry->get_error_message() );
	}
	$current_step = $api->get_current_step( $entry );
	if ( ! $current_step ) {
		throw new RuntimeException( 'Fixture did not resolve an authentic Gravity Flow current step.' );
	}
	$assignee_keys = array_map(
		static function ( $assignee ) {
			return $assignee->get_key();
		},
		$current_step->get_assignees()
	);
	sort( $assignee_keys );

	$native_last_updated_source = date( 'Y-m-d H:i:s', $runtime_entry['workflow_timestamp'] );
	$native_date_created        = Gravity_Flow_Common::format_date( $runtime_entry['date_created'], '', true, true );
	$native_last_updated        = $runtime_entry['date_created'] !== $native_last_updated_source
		? Gravity_Flow_Common::format_date( $native_last_updated_source, '', true, true )
		: '-';

	$due_enabled = ! empty( $current_step->due_date );
	$due_raw     = $due_enabled ? (int) $current_step->get_due_date_timestamp() : 0;
	$due_native  = $due_enabled
		? Gravity_Flow_Common::format_date( date( 'Y-m-d H:i:s', $due_raw ), '', true, true )
		: '-';
	$overdue     = $due_enabled ? (bool) $current_step->is_overdue() : false;

	if ( $due_raw !== (int) $runtime_entry['expected_due_timestamp'] ) {
		throw new RuntimeException( 'Host due-date raw authority did not match deterministic fixture for entry ' . $entry['id'] . '.' );
	}
	if ( $overdue !== (bool) $runtime_entry['expected_overdue'] ) {
		throw new RuntimeException( 'Host overdue classification did not match deterministic fixture for entry ' . $entry['id'] . '.' );
	}

	$runtime_entry['workflow_step']           = (int) gform_get_meta( $entry['id'], 'workflow_step' );
	$runtime_entry['workflow_final_status']   = (string) gform_get_meta( $entry['id'], 'workflow_final_status' );
	$runtime_entry['assignees']               = $assignee_keys;
	$runtime_entry['due_date_enabled']        = $due_enabled;
	$runtime_entry['due_date_timestamp']      = $due_raw;
	$runtime_entry['overdue']                 = $overdue;
	$runtime_entry['expected_created_native'] = $native_date_created;
	$runtime_entry['expected_updated_native'] = $native_last_updated;
	$runtime_entry['expected_due_native']     = $due_native;
}
unset( $runtime_entry );
remove_filter( 'gravityflow_step_due_date_timestamp', $runtime_due_filter, PHP_INT_MAX );

$page_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'G008 Gravity Flow Inbox System Dates',
		'post_name'    => 'g008-gravityflow-inbox-system-dates',
		'post_content' => sprintf( '[gravityflow page="inbox" form="%d" last_updated="true" due_date="true" display_filter="true"]', (int) $form_id ),
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
$manifest['schema_version']                 = '1.6.0';
$manifest['g008_flow_inbox_url']            = add_query_arg( 'page_id', (int) $page_id, home_url( '/' ) );
$manifest['g008_flow_status_url']           = add_query_arg( 'page_id', (int) $status_page_id, home_url( '/' ) );
$manifest['g008_flow_form_id']              = (int) $form_id;
$manifest['g008_flow_step_id']              = (int) $step_id;
$manifest['g008_flow_no_due_step_id']       = (int) $no_due_step_id;
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
			'no_due_step_id'       => (int) $no_due_step_id,
			'due_fixture_method'   => 'gravityflow_step_due_date_timestamp supported test-only override plus request-local invocation/re-entry probe; production adapter does not hook this filter',
			'entries'              => $runtime_entries,
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	) . "\n"
);

echo wp_json_encode( array( 'form_id' => (int) $form_id, 'step_id' => (int) $step_id, 'no_due_step_id' => (int) $no_due_step_id, 'entry_count' => count( $runtime_entries ) ) ) . "\n";
