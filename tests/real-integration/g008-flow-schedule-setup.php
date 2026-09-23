<?php
/**
 * Authentic Gravity Flow 3.1.0 scheduled-step branch fixtures for G-008.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$artifact_dir  = getenv( 'WU008_ARTIFACT_DIR' );
$manifest_path = getenv( 'WU008_MANIFEST_PATH' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir || ! is_string( $manifest_path ) || '' === $manifest_path ) {
	throw new RuntimeException( 'G008 schedule setup requires artifact and manifest paths.' );
}
if ( ! defined( 'GRAVITY_FLOW_VERSION' ) || '3.1.0' !== GRAVITY_FLOW_VERSION ) {
	throw new RuntimeException( 'G008 schedule setup requires exact Gravity Flow 3.1.0.' );
}
if ( 'UTC' !== date_default_timezone_get() || 'Asia/Tehran' !== wp_timezone_string() ) {
	throw new RuntimeException( 'G008 schedule setup requires PHP UTC and site Asia/Tehran.' );
}

$manifest = json_decode( (string) file_get_contents( $manifest_path ), true, 512, JSON_THROW_ON_ERROR );
$base_entry_url = $manifest['g008_flow_entry_detail_url'];

$build = static function ( $key, $schedule_settings, $date_field_value = '' ) use ( $base_entry_url ) {
	$form = array(
		'title'  => 'WU008 G008 Schedule ' . $key,
		'fields' => array(
			array(
				'id'    => 1,
				'label' => 'Branch',
				'type'  => 'text',
			),
			array(
				'id'         => 2,
				'label'      => 'Schedule Source Date',
				'type'       => 'date',
				'dateFormat' => 'ymd_dash',
			),
		),
	);
	$form_id = GFAPI::add_form( $form );
	if ( is_wp_error( $form_id ) ) {
		throw new RuntimeException( $form_id->get_error_message() );
	}

	$api = new Gravity_Flow_API( (int) $form_id );
	$step = array_merge(
		array(
			'step_name'       => 'Schedule branch ' . $key,
			'step_type'       => 'approval',
			'type'            => 'select',
			'assignees'       => array( 'user_id|1' ),
			'assignee_policy' => 'any',
			'scheduled'       => true,
		),
		$schedule_settings
	);
	$step_id = $api->add_step( $step );
	if ( ! $step_id ) {
		throw new RuntimeException( 'Could not create schedule branch step: ' . $key );
	}

	$entry_id = GFAPI::add_entry(
		array(
			'form_id'      => (int) $form_id,
			'created_by'   => 1,
			'date_created' => '2026-03-20 20:30:00',
			'1'            => $key,
			'2'            => $date_field_value,
		)
	);
	if ( is_wp_error( $entry_id ) ) {
		throw new RuntimeException( $entry_id->get_error_message() );
	}
	$api->process_workflow( (int) $entry_id );

	$entry = GFAPI::get_entry( (int) $entry_id );
	$current_step = $api->get_current_step( $entry );
	if ( ! $current_step || (int) $current_step->get_id() !== (int) $step_id ) {
		throw new RuntimeException( 'Schedule branch current step did not resolve for ' . $key );
	}

	// Pin the delay source timestamp after authentic workflow initialization.
	if ( 'delay' === $current_step->schedule_type ) {
		$fixed_step_timestamp = 1774038600; // 2026-03-20 20:30:00 UTC.
		gform_update_meta( (int) $entry_id, 'workflow_step_timestamp_' . (int) $step_id, $fixed_step_timestamp );
		$current_step = $api->get_current_step( GFAPI::get_entry( (int) $entry_id ) );
		if ( (int) $current_step->get_step_timestamp() !== $fixed_step_timestamp ) {
			throw new RuntimeException( 'Could not pin delay branch workflow step timestamp.' );
		}
	}

	$schedule_timestamp = $current_step->get_schedule_timestamp();
	$is_queued          = (bool) $current_step->is_queued();
	$expected_display   = null;
	if ( $is_queued ) {
		switch ( $current_step->schedule_type ) {
			case 'date':
				$expected_display = (string) $current_step->schedule_date;
				break;
			case 'date_field':
			case 'delay':
			default:
				$expected_display = get_date_from_gmt( date( 'Y-m-d H:i:s', (int) $schedule_timestamp ) );
				break;
		}
	}

	return array(
		'key'                 => $key,
		'form_id'             => (int) $form_id,
		'entry_id'            => (int) $entry_id,
		'step_id'             => (int) $step_id,
		'entry_url'           => add_query_arg( 'lid', (int) $entry_id, $base_entry_url ),
		'schedule_type'       => (string) $current_step->schedule_type,
		'scheduled'           => (bool) $current_step->scheduled,
		'schedule_date'       => isset( $current_step->schedule_date ) ? (string) $current_step->schedule_date : '',
		'schedule_date_field' => isset( $current_step->schedule_date_field ) ? (string) $current_step->schedule_date_field : '',
		'date_field_value'    => (string) rgar( $entry, '2' ),
		'step_timestamp'      => (int) $current_step->get_step_timestamp(),
		'schedule_timestamp'  => false === $schedule_timestamp ? false : (int) $schedule_timestamp,
		'is_queued'           => $is_queued,
		'expected_display'    => $expected_display,
		'workflow_final_status'=> (string) gform_get_meta( (int) $entry_id, 'workflow_final_status' ),
	);
};

$branches = array();
$branches[] = $build(
	'date',
	array(
		'schedule_type' => 'date',
		'schedule_date' => '2030-03-21',
	)
);
$branches[] = $build(
	'date_field',
	array(
		'schedule_type'                    => 'date_field',
		'schedule_date_field'              => '2',
		'schedule_date_field_offset'       => '0',
		'schedule_date_field_offset_unit'  => 'hours',
		'schedule_date_field_before_after' => 'after',
	),
	'2030-03-22'
);
$branches[] = $build(
	'delay',
	array(
		'schedule_type'         => 'delay',
		'schedule_delay_offset' => '20000',
		'schedule_delay_unit'   => 'hours',
	)
);
$branches[] = $build(
	'date_field_empty',
	array(
		'schedule_type'                    => 'date_field',
		'schedule_date_field'              => '2',
		'schedule_date_field_offset'       => '0',
		'schedule_date_field_offset_unit'  => 'hours',
		'schedule_date_field_before_after' => 'after',
	),
	''
);

$schedule_entry_map = array();
foreach ( $branches as $branch ) {
	$schedule_entry_map[ (int) $branch['entry_id'] ] = (string) $branch['key'];
}
update_option( 'pgr_wu008_g008_schedule_entry_map', $schedule_entry_map, false );

wp_mkdir_p( WPMU_PLUGIN_DIR );
$schedule_probe = <<<'PHP'
<?php
defined( 'ABSPATH' ) || exit;
$GLOBALS['pgr_wu008_g008_schedule_probe'] = array(
	'total' => 0,
	'by_entry' => array(),
	'nested_date_i18n' => 0,
);
add_filter(
	'gravityflow_step_schedule_timestamp',
	static function ( $timestamp, $type, $step ) {
		if ( ! is_object( $step ) || ! is_callable( array( $step, 'get_entry' ) ) ) {
			return $timestamp;
		}
		$step_entry = $step->get_entry();
		$entry_id   = is_array( $step_entry ) ? absint( rgar( $step_entry, 'id' ) ) : 0;
		$map = get_option( 'pgr_wu008_g008_schedule_entry_map', array() );
		if ( ! isset( $map[ $entry_id ] ) ) {
			return $timestamp;
		}
		++$GLOBALS['pgr_wu008_g008_schedule_probe']['total'];
		if ( ! isset( $GLOBALS['pgr_wu008_g008_schedule_probe']['by_entry'][ $entry_id ] ) ) {
			$GLOBALS['pgr_wu008_g008_schedule_probe']['by_entry'][ $entry_id ] = 0;
		}
		++$GLOBALS['pgr_wu008_g008_schedule_probe']['by_entry'][ $entry_id ];
		if ( doing_filter( 'date_i18n' ) ) {
			++$GLOBALS['pgr_wu008_g008_schedule_probe']['nested_date_i18n'];
		}
		return $timestamp;
	},
	PHP_INT_MAX,
	3
);
add_action(
	'wp_footer',
	static function () {
		$entry_id = isset( $_GET['lid'] ) ? absint( $_GET['lid'] ) : 0;
		$map = get_option( 'pgr_wu008_g008_schedule_entry_map', array() );
		if ( ! isset( $map[ $entry_id ] ) ) {
			return;
		}
		$evidence = $GLOBALS['pgr_wu008_g008_schedule_probe'];
		$evidence['entry_id'] = $entry_id;
		$evidence['branch'] = (string) $map[ $entry_id ];
		echo '<script>window.pgrG008ScheduleProbe=' . wp_json_encode( $evidence ) . ';</script>';
	},
	PHP_INT_MAX
);
PHP;
file_put_contents( WPMU_PLUGIN_DIR . '/pgr-wu008-g008-schedule-probe.php', $schedule_probe . "\n" );

foreach ( $branches as $branch ) {
	if ( 'date_field_empty' === $branch['key'] ) {
		if ( false !== $branch['schedule_timestamp'] || $branch['is_queued'] || null !== $branch['expected_display'] ) {
			throw new RuntimeException( 'Empty date-field schedule branch did not preserve native false/absence semantics.' );
		}
		continue;
	}
	if ( ! is_int( $branch['schedule_timestamp'] ) || ! $branch['is_queued'] || ! is_string( $branch['expected_display'] ) || '' === $branch['expected_display'] ) {
		throw new RuntimeException( 'Scheduled branch did not reach authentic queued rendering state: ' . $branch['key'] );
	}
}

$manifest['schema_version']               = '1.8.0';
$manifest['g008_flow_schedule_branches']  = $branches;
file_put_contents(
	$manifest_path,
	wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "
"
);
file_put_contents(
	$artifact_dir . '/g008-flow-schedule-fixture.json',
	wp_json_encode(
		array(
			'schema_version'       => '1.0.0',
			'evidence_class'       => 'AUTHENTIC_G008_FLOW_SCHEDULE_BRANCH_FIXTURE',
			'gravityflow_version'  => GRAVITY_FLOW_VERSION,
			'site_timezone'        => wp_timezone_string(),
			'php_default_timezone' => date_default_timezone_get(),
			'branches'             => $branches,
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	) . "
"
);
echo wp_json_encode( array( 'branches' => $branches ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "
";
