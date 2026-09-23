<?php
/**
 * Adversarial G-008 residual fixtures and isolated Entry Detail two-hook prototype.
 *
 * Test-only. Uses public host APIs/hooks plus deterministic fixture storage updates;
 * never patches vendor files.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$artifact_dir  = getenv( 'WU008_ARTIFACT_DIR' );
$manifest_path = getenv( 'WU008_MANIFEST_PATH' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir || ! is_string( $manifest_path ) || '' === $manifest_path ) {
	throw new RuntimeException( 'Residual adversarial setup requires artifact and manifest paths.' );
}
if ( ! defined( 'GRAVITY_FLOW_VERSION' ) || '3.1.0' !== GRAVITY_FLOW_VERSION ) {
	throw new RuntimeException( 'Residual adversarial setup requires exact Gravity Flow 3.1.0.' );
}
if ( 'UTC' !== date_default_timezone_get() || 'Asia/Tehran' !== wp_timezone_string() ) {
	throw new RuntimeException( 'Residual adversarial setup requires PHP UTC and site Asia/Tehran.' );
}

$manifest = json_decode( (string) file_get_contents( $manifest_path ), true, 512, JSON_THROW_ON_ERROR );
$form_id  = (int) $manifest['g008_flow_form_id'];
$api      = new Gravity_Flow_API( $form_id );

$candidate_step_id = $api->add_step(
	array(
		'step_name'                 => 'G008 Entry Detail Two Hook Candidate',
		'step_type'                 => 'approval',
		'description'               => 'Exact-version isolated Entry Detail date presentation prototype fixture.',
		'type'                      => 'select',
		'assignees'                 => array( 'user_id|1' ),
		'assignee_policy'           => 'any',
		'due_date'                  => true,
		'due_date_type'             => 'delay',
		'due_date_delay_offset'     => 1,
		'due_date_delay_unit'       => 'days',
		'expiration'                => true,
		'expiration_type'           => 'delay',
		'expiration_delay_offset'   => 3650,
		'expiration_delay_unit'     => 'days',
		'status_expiration'         => 'rejected',
		'destination_expired'       => 'complete',
		'destination_rejected'      => 'complete',
		'destination_approved'      => 'complete',
	)
);
if ( ! $candidate_step_id ) {
	throw new RuntimeException( 'Could not create Entry Detail candidate step.' );
}

$make_candidate_entry = static function ( $key, $date_created, $workflow_timestamp ) use ( $form_id, $candidate_step_id ) {
	$entry_id = GFAPI::add_entry(
		array(
			'form_id'      => $form_id,
			'created_by'   => 1,
			'date_created' => $date_created,
			'1'            => $key,
		)
	);
	if ( is_wp_error( $entry_id ) ) {
		throw new RuntimeException( $entry_id->get_error_message() );
	}
	gform_update_meta( $entry_id, 'workflow_step', (int) $candidate_step_id );
	gform_update_meta( $entry_id, 'workflow_final_status', 'pending' );
	gform_update_meta( $entry_id, 'workflow_timestamp', (int) $workflow_timestamp );
	return (int) $entry_id;
};

$candidate_entry_id = $make_candidate_entry( 'entry-detail-candidate', '2026-03-20 20:29:00', 1774038660 );
$range_entry_id     = $make_candidate_entry( 'entry-detail-range-fallback', '2124-03-20 00:00:00', 4866580800 );

$candidate_map = array(
	$candidate_entry_id => array(
		'due'        => 1774038660 + 120,
		'expiration' => 1774124940,
	),
	$range_entry_id => array(
		'due'        => 4866584400,
		'expiration' => 4866670800,
	),
);
update_option( 'pgr_wu008_entry_detail_candidate_map', $candidate_map, false );

// Add multiple genuine Gravity Forms notes of Gravity Flow note_type, then pin
// their stored UTC timestamps for deterministic order/header assertions.
$note_specs = array(
	array(
		'date_created' => '2026-03-20 19:58:00',
		'value'        => 'Stored event A; user text contains date-looking 2026-03-20 and 1405/01/01.',
		'user_name'    => 'Runtime Admin',
	),
	array(
		'date_created' => '2026-03-20 20:31:00',
		'value'        => 'Stored event B; body must remain byte-for-byte unchanged.',
		'user_name'    => 'Runtime Admin',
	),
	array(
		'date_created' => '2026-03-21 20:29:00',
		'value'        => 'Stored event C; another date-looking token 2030-03-21 must stay text.',
		'user_name'    => 'Runtime Admin',
	),
);
$notes_table = GFFormsModel::get_entry_notes_table_name();
$note_rows   = array();
global $wpdb;
foreach ( $note_specs as $spec ) {
	$note_id = GFFormsModel::add_note(
		$candidate_entry_id,
		1,
		$spec['user_name'],
		$spec['value'],
		'gravityflow',
		'wu008-g008'
	);
	if ( ! $note_id ) {
		throw new RuntimeException( 'Could not create authentic stored Gravity Flow note fixture.' );
	}
	$updated = $wpdb->update(
		$notes_table,
		array( 'date_created' => $spec['date_created'] ),
		array( 'id' => (int) $note_id ),
		array( '%s' ),
		array( '%d' )
	);
	if ( false === $updated ) {
		throw new RuntimeException( 'Could not pin deterministic stored note timestamp.' );
	}
	$note_rows[] = array(
		'id'           => (int) $note_id,
		'date_created' => $spec['date_created'],
		'value'        => $spec['value'],
		'note_type'    => 'gravityflow',
		'sub_type'     => 'wu008-g008',
	);
}

wp_mkdir_p( WPMU_PLUGIN_DIR );
$prototype_plugin = <<<'PHP'
<?php
/**
 * Test-only isolated prototype for the proposed Entry Detail format-marker +
 * date_i18n composition and deterministic operational timestamp probes.
 */
defined( 'ABSPATH' ) || exit;

function pgr_wu008_ed_candidate_exact_host() {
	if ( ! isset( $_GET['pgr_g008_candidate_case'] ) ) {
		return false;
	}
	$case = (string) $_GET['pgr_g008_candidate_case'];
	if ( ! in_array( $case, array( 'exact', 'failure', 'range' ), true ) ) {
		return false;
	}
	if (
		! defined( 'GRAVITY_FLOW_VERSION' ) ||
		'3.1.0' !== GRAVITY_FLOW_VERSION ||
		! defined( 'GRAVITY_FLOW_PLUGIN_BASENAME' ) ||
		'gravityflow/gravityflow.php' !== str_replace( '\\', '/', GRAVITY_FLOW_PLUGIN_BASENAME )
	) {
		return false;
	}
	return class_exists( 'PGR_Module_Registry', false )
		&& PGR_Module_Registry::is_enabled( 'jalali_presentation' )
		&& class_exists( 'PGR_Jalali_Presentation', false )
		&& class_exists( 'GFCommon', false );
}

function pgr_wu008_ed_candidate_literal() {
	return 'PGRG008ENTRYDETAILMARKER:';
}

function pgr_wu008_ed_candidate_format() {
	$literal = pgr_wu008_ed_candidate_literal();
	$escaped = '';
	foreach ( str_split( $literal ) as $character ) {
		$escaped .= '\\' . $character;
	}
	return $escaped . GFCommon::get_default_date_format();
}

$GLOBALS['pgr_wu008_ed_candidate_evidence'] = array(
	'format_hook_calls'             => 0,
	'marker_date_i18n_calls'        => 0,
	'unrelated_date_i18n_calls'     => 0,
	'due_getter_calls'              => 0,
	'expiration_getter_calls'       => 0,
	'nested_due_getter_calls'       => 0,
	'nested_expiration_getter_calls'=> 0,
	'observations'                  => array(),
);

add_filter(
	'gravityflow_date_format_entry_detail',
	static function ( $format ) {
		++$GLOBALS['pgr_wu008_ed_candidate_evidence']['format_hook_calls'];
		if ( '' !== $format || ! pgr_wu008_ed_candidate_exact_host() ) {
			return $format;
		}
		return pgr_wu008_ed_candidate_format();
	},
	PHP_INT_MAX,
	1
);

add_filter(
	'date_i18n',
	static function ( $date, $format, $timestamp, $gmt ) {
		$evidence = &$GLOBALS['pgr_wu008_ed_candidate_evidence'];
		$marker_format = pgr_wu008_ed_candidate_exact_host() ? pgr_wu008_ed_candidate_format() : null;
		if ( ! is_string( $marker_format ) || $format !== $marker_format ) {
			++$evidence['unrelated_date_i18n_calls'];
			return $date;
		}

		++$evidence['marker_date_i18n_calls'];
		$literal = pgr_wu008_ed_candidate_literal();
		$fallback = is_string( $date ) && 0 === strpos( $date, $literal )
			? substr( $date, strlen( $literal ) )
			: wp_date( GFCommon::get_default_date_format(), (int) $timestamp, new DateTimeZone( 'UTC' ) );

		$case = isset( $_GET['pgr_g008_candidate_case'] ) ? (string) $_GET['pgr_g008_candidate_case'] : 'exact';
		$formatted = null;
		$local_civil = is_numeric( $timestamp ) ? gmdate( 'Y-m-d H:i:s', (int) $timestamp ) : null;
		if ( 'failure' !== $case && is_string( $local_civil ) ) {
			$year  = (int) substr( $local_civil, 0, 4 );
			$month = (int) substr( $local_civil, 5, 2 );
			$day   = (int) substr( $local_civil, 8, 2 );
			$formatted = PGR_Jalali_Presentation::format_date( $year, $month, $day );
		}

		$output = null === $formatted ? $fallback : $formatted;
		$evidence['observations'][] = array(
			'timestamp_with_offset' => is_numeric( $timestamp ) ? (int) $timestamp : null,
			'gmt'                   => (bool) $gmt,
			'local_civil'           => $local_civil,
			'native_prefixed'       => $date,
			'native_fallback'       => $fallback,
			'jalali'                => $formatted,
			'output'                => $output,
			'case'                  => $case,
		);
		return $output;
	},
	PHP_INT_MAX,
	4
);

add_filter(
	'gravityflow_step_due_date_timestamp',
	static function ( $timestamp, $type, $step ) {
		unset( $type );
		if ( ! is_object( $step ) || ! is_callable( array( $step, 'get_entry_id' ) ) ) {
			return $timestamp;
		}
		$entry_id = (int) $step->get_entry_id();
		$map = get_option( 'pgr_wu008_entry_detail_candidate_map', array() );
		if ( ! isset( $map[ $entry_id ]['due'] ) ) {
			return $timestamp;
		}
		++$GLOBALS['pgr_wu008_ed_candidate_evidence']['due_getter_calls'];
		if ( doing_filter( 'date_i18n' ) ) {
			++$GLOBALS['pgr_wu008_ed_candidate_evidence']['nested_due_getter_calls'];
		}
		return (int) $map[ $entry_id ]['due'];
	},
	PHP_INT_MAX,
	3
);

add_filter(
	'gravityflow_step_expiration_timestamp',
	static function ( $timestamp, $type, $step ) {
		unset( $type );
		if ( ! is_object( $step ) || ! is_callable( array( $step, 'get_entry_id' ) ) ) {
			return $timestamp;
		}
		$entry_id = (int) $step->get_entry_id();
		$map = get_option( 'pgr_wu008_entry_detail_candidate_map', array() );
		if ( ! isset( $map[ $entry_id ]['expiration'] ) ) {
			return $timestamp;
		}
		++$GLOBALS['pgr_wu008_ed_candidate_evidence']['expiration_getter_calls'];
		if ( doing_filter( 'date_i18n' ) ) {
			++$GLOBALS['pgr_wu008_ed_candidate_evidence']['nested_expiration_getter_calls'];
		}
		return (int) $map[ $entry_id ]['expiration'];
	},
	PHP_INT_MAX,
	3
);

add_action(
	'wp_footer',
	static function () {
		if ( ! isset( $_GET['pgr_g008_candidate_case'] ) ) {
			return;
		}
		$probe_timestamp = strtotime( '2026-03-20 20:31:00 UTC' );
		$unrelated_wp = date_i18n( 'Y-m-d H:i', GFCommon::get_local_timestamp( $probe_timestamp ), true );
		$unrelated_gf = GFCommon::format_date( '2026-03-20 20:31:00', false, 'Y-m-d', true );
		$evidence = $GLOBALS['pgr_wu008_ed_candidate_evidence'];
		$evidence['case'] = (string) $_GET['pgr_g008_candidate_case'];
		$evidence['unrelated_wp_date_i18n'] = $unrelated_wp;
		$evidence['unrelated_gf_format_date'] = $unrelated_gf;
		echo '<script>window.pgrG008EntryDetailCandidateEvidence=' . wp_json_encode( $evidence ) . ';</script>';
	},
	PHP_INT_MAX
);
PHP;
file_put_contents( WPMU_PLUGIN_DIR . '/pgr-wu008-g008-entry-detail-candidate.php', $prototype_plugin . "
" );

// Build native expectations under the same operational timestamp authority.
$runtime_due_filter = static function ( $timestamp, $type, $step ) use ( $candidate_map ) {
	unset( $type );
	if ( ! is_object( $step ) || ! is_callable( array( $step, 'get_entry_id' ) ) ) {
		return $timestamp;
	}
	$entry_id = (int) $step->get_entry_id();
	return isset( $candidate_map[ $entry_id ]['due'] ) ? (int) $candidate_map[ $entry_id ]['due'] : $timestamp;
};
$runtime_expiration_filter = static function ( $timestamp, $type, $step ) use ( $candidate_map ) {
	unset( $type );
	if ( ! is_object( $step ) || ! is_callable( array( $step, 'get_entry_id' ) ) ) {
		return $timestamp;
	}
	$entry_id = (int) $step->get_entry_id();
	return isset( $candidate_map[ $entry_id ]['expiration'] ) ? (int) $candidate_map[ $entry_id ]['expiration'] : $timestamp;
};
add_filter( 'gravityflow_step_due_date_timestamp', $runtime_due_filter, PHP_INT_MAX, 3 );
add_filter( 'gravityflow_step_expiration_timestamp', $runtime_expiration_filter, PHP_INT_MAX, 3 );

$candidate_entries = array();
foreach ( array( $candidate_entry_id, $range_entry_id ) as $entry_id ) {
	$entry = GFAPI::get_entry( $entry_id );
	if ( is_wp_error( $entry ) ) {
		throw new RuntimeException( $entry->get_error_message() );
	}
	$step = $api->get_current_step( $entry );
	if ( ! $step ) {
		throw new RuntimeException( 'Entry Detail candidate current step could not be resolved.' );
	}
	$due = (int) $step->get_due_date_timestamp();
	$expiration = (int) $step->get_expiration_timestamp();
	$candidate_entries[] = array(
		'id'                         => $entry_id,
		'date_created'               => (string) $entry['date_created'],
		'workflow_timestamp'         => (int) gform_get_meta( $entry_id, 'workflow_timestamp' ),
		'workflow_step'              => (int) gform_get_meta( $entry_id, 'workflow_step' ),
		'workflow_final_status'      => (string) gform_get_meta( $entry_id, 'workflow_final_status' ),
		'due_timestamp'              => $due,
		'expiration_timestamp'       => $expiration,
		'overdue'                    => (bool) $step->is_overdue(),
		'expired'                    => (bool) $step->is_expired(),
		'expected_submitted_native'  => Gravity_Flow_Common::format_date( $entry['date_created'], '', false, true ),
		'expected_updated_native'    => Gravity_Flow_Common::format_date( (int) gform_get_meta( $entry_id, 'workflow_timestamp' ), '', false, true ),
		'expected_due_native'        => Gravity_Flow_Common::format_date( $due, '', false, false ),
		'expected_expiration_native' => Gravity_Flow_Common::format_date( $expiration, '', false, true ),
	);
}
remove_filter( 'gravityflow_step_due_date_timestamp', $runtime_due_filter, PHP_INT_MAX );
remove_filter( 'gravityflow_step_expiration_timestamp', $runtime_expiration_filter, PHP_INT_MAX );

$timeline_notes = Gravity_Flow_Common::get_timeline_notes( GFAPI::get_entry( $candidate_entry_id ) );
$timeline_fixture = array();
foreach ( $timeline_notes as $note ) {
	$timeline_fixture[] = array(
		'id'             => (int) $note->id,
		'date_created'   => (string) $note->date_created,
		'value'          => (string) $note->value,
		'note_type'      => isset( $note->note_type ) ? (string) $note->note_type : 'initial',
		'expected_header'=> Gravity_Flow_Common::format_date( $note->date_created, '', false, true ),
	);
}
if ( count( $timeline_fixture ) < 4 ) {
	throw new RuntimeException( 'Multi-note Timeline fixture did not include initial + three stored events.' );
}

$base_entry_url = $manifest['g008_flow_entry_detail_url'];
$base_print_url = $manifest['g008_flow_print_url'];
$candidate_url = add_query_arg(
	array(
		'lid'                    => $candidate_entry_id,
		'pgr_g008_candidate_case'=> 'exact',
	),
	$base_entry_url
);
$range_url = add_query_arg(
	array(
		'lid'                    => $range_entry_id,
		'pgr_g008_candidate_case'=> 'range',
	),
	$base_entry_url
);
$candidate_print_url = add_query_arg( 'lid', $candidate_entry_id, $base_print_url );

$manifest['schema_version']                         = '1.7.0';
$manifest['g008_flow_entry_detail_candidate_step_id'] = (int) $candidate_step_id;
$manifest['g008_flow_entry_detail_candidate_entry_id'] = $candidate_entry_id;
$manifest['g008_flow_entry_detail_range_entry_id']     = $range_entry_id;
$manifest['g008_flow_entry_detail_candidate_url']      = $candidate_url;
$manifest['g008_flow_entry_detail_range_url']          = $range_url;
$manifest['g008_flow_entry_detail_candidate_print_url']= $candidate_print_url;
$manifest['g008_flow_entry_detail_candidate_entries']  = $candidate_entries;
$manifest['g008_flow_timeline_multi_note']             = $timeline_fixture;
$manifest['g008_flow_timeline_stored_notes']           = $note_rows;
file_put_contents(
	$manifest_path,
	wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "
"
);

file_put_contents(
	$artifact_dir . '/g008-flow-residual-adversarial-fixture.json',
	wp_json_encode(
		array(
			'evidence_class'       => 'AUTHENTIC_GRAVITY_FLOW_RESIDUAL_ADVERSARIAL_FIXTURE',
			'gravityflow_version'  => GRAVITY_FLOW_VERSION,
			'site_timezone'        => wp_timezone_string(),
			'php_default_timezone' => date_default_timezone_get(),
			'candidate_step_id'    => (int) $candidate_step_id,
			'candidate_entries'    => $candidate_entries,
			'stored_notes'         => $note_rows,
			'timeline_order'       => wp_list_pluck( $timeline_fixture, 'id' ),
			'timeline_fixture'     => $timeline_fixture,
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	) . "
"
);

echo wp_json_encode(
	array(
		'candidate_entry_id' => $candidate_entry_id,
		'range_entry_id'     => $range_entry_id,
		'stored_note_ids'    => wp_list_pluck( $note_rows, 'id' ),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
) . "
";
