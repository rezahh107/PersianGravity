<?php
/**
 * Extend the existing WU008 Timeline fixture for production-admission evidence.
 *
 * Test-only fixture setup. Production code never mutates host Entry/note data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$artifact_dir  = getenv( 'WU008_ARTIFACT_DIR' );
$manifest_path = getenv( 'WU008_MANIFEST_PATH' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir || ! is_string( $manifest_path ) || '' === $manifest_path ) {
	throw new RuntimeException( 'Timeline production setup requires artifact and manifest paths.' );
}
if (
	! defined( 'GRAVITY_FLOW_VERSION' ) ||
	'3.1.0' !== GRAVITY_FLOW_VERSION ||
	! class_exists( 'GFForms' ) ||
	'3.1.1.1' !== (string) GFForms::$version ||
	'Asia/Tehran' !== wp_timezone_string() ||
	'UTC' !== date_default_timezone_get()
) {
	throw new RuntimeException( 'Timeline production setup exact runtime contract drifted.' );
}

$manifest = json_decode( (string) file_get_contents( $manifest_path ), true, 512, JSON_THROW_ON_ERROR );
$entry_id = (int) ( $manifest['g008_flow_entry_detail_candidate_entry_id'] ?? 0 );
if ( $entry_id <= 0 ) {
	throw new RuntimeException( 'Timeline production setup candidate Entry is missing.' );
}

$duplicate_value     = 'Stored event D; duplicate timestamp must remain a distinct Timeline row.';
$duplicate_timestamp = '2030-03-20 20:31:00';
$notes_table         = GFFormsModel::get_entry_notes_table_name();
global $wpdb;

$note_id = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT id FROM {$notes_table} WHERE entry_id = %d AND note_type = %s AND sub_type = %s AND value = %s ORDER BY id ASC LIMIT 1",
		$entry_id,
		'gravityflow',
		'wu008-g008-duplicate',
		$duplicate_value
	)
);
if ( $note_id <= 0 ) {
	$note_id = (int) GFFormsModel::add_note(
		$entry_id,
		1,
		'Runtime Admin',
		$duplicate_value,
		'gravityflow',
		'wu008-g008-duplicate'
	);
}
if ( $note_id <= 0 ) {
	throw new RuntimeException( 'Could not create duplicate-timestamp stored Timeline note.' );
}

$updated = $wpdb->update(
	$notes_table,
	array( 'date_created' => $duplicate_timestamp ),
	array( 'id' => $note_id ),
	array( '%s' ),
	array( '%d' )
);
if ( false === $updated ) {
	throw new RuntimeException( 'Could not pin duplicate Timeline timestamp.' );
}

$duplicate_row = array(
	'id'           => $note_id,
	'date_created' => $duplicate_timestamp,
	'value'        => $duplicate_value,
	'note_type'    => 'gravityflow',
	'sub_type'     => 'wu008-g008-duplicate',
);
$stored = isset( $manifest['g008_flow_timeline_stored_notes'] ) && is_array( $manifest['g008_flow_timeline_stored_notes'] )
	? $manifest['g008_flow_timeline_stored_notes']
	: array();
$stored = array_values(
	array_filter(
		$stored,
		static function ( $row ) use ( $note_id ) {
			return (int) ( $row['id'] ?? 0 ) !== $note_id;
		}
	)
);
$stored[] = $duplicate_row;
$manifest['g008_flow_timeline_stored_notes'] = $stored;

$entry = GFAPI::get_entry( $entry_id );
if ( is_wp_error( $entry ) ) {
	throw new RuntimeException( $entry->get_error_message() );
}
$notes = Gravity_Flow_Common::get_timeline_notes( $entry );
if ( count( $notes ) < 5 ) {
	throw new RuntimeException( 'Production Timeline fixture requires initial event plus four stored events.' );
}

$oracle = array(
	'2030-03-20' => '۱۴۰۸/۱۲/۳۰',
	'2030-03-21' => '۱۴۰۹/۰۱/۰۱',
);
$timeline        = array();
$duplicate_count = 0;
foreach ( $notes as $note ) {
	$raw = isset( $note->date_created ) ? (string) $note->date_created : '';
	try {
		$source = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $raw, new DateTimeZone( 'UTC' ) );
		$errors = DateTimeImmutable::getLastErrors();
		if (
			false === $source ||
			( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ||
			$source->format( 'Y-m-d H:i:s' ) !== $raw
		) {
			throw new RuntimeException( 'Malformed Timeline fixture timestamp.' );
		}
		$civil = $source->setTimezone( wp_timezone() );
	} catch ( Throwable $exception ) {
		throw new RuntimeException( 'Timeline fixture contains malformed raw date.', 0, $exception );
	}

	$local_day = $civil->format( 'Y-m-d' );
	if ( ! isset( $oracle[ $local_day ] ) ) {
		throw new RuntimeException( 'Timeline fixture escaped the fixed independent Jalali oracle.' );
	}

	$native_header = Gravity_Flow_Common::format_date( $raw, '', false, true );
	$gmt_time      = mysql2date( 'G', $raw );
	$local_time    = GFCommon::get_local_timestamp( $gmt_time );
	$native_time   = date_i18n( GFCommon::get_default_time_format(), $local_time, true );
	$persian_time  = strtr(
		$native_time,
		array(
			'0' => '۰',
			'1' => '۱',
			'2' => '۲',
			'3' => '۳',
			'4' => '۴',
			'5' => '۵',
			'6' => '۶',
			'7' => '۷',
			'8' => '۸',
			'9' => '۹',
		)
	);
	if ( $duplicate_timestamp === $raw ) {
		++$duplicate_count;
	}

	$timeline[] = array(
		'id'                        => (int) $note->id,
		'date_created'              => $raw,
		'value'                     => (string) $note->value,
		'note_type'                 => isset( $note->note_type ) ? (string) $note->note_type : 'initial',
		'expected_header'           => $native_header,
		'expected_jalali_date'      => $oracle[ $local_day ],
		'expected_native_time'      => $native_time,
		'expected_persian_time'     => $persian_time,
		'event_kind'                => 0 === (int) $note->id ? 'initial' : 'stored',
	);
}
if ( $duplicate_count < 2 ) {
	throw new RuntimeException( 'Duplicate timestamp scenario was not established.' );
}

$duplicate_ids = array_values(
	array_map(
		static function ( $item ) {
			return (int) $item['id'];
		},
		array_filter(
			$timeline,
			static function ( $item ) use ( $duplicate_timestamp ) {
				return $item['date_created'] === $duplicate_timestamp;
			}
		)
	)
);
if ( count( $duplicate_ids ) < 2 || count( array_unique( $duplicate_ids ) ) !== count( $duplicate_ids ) ) {
	throw new RuntimeException( 'Duplicate timestamp fixture does not preserve distinct note IDs.' );
}

$manifest['schema_version']                   = '1.8.0';
$manifest['g008_flow_timeline_multi_note']    = $timeline;
$manifest['g008_flow_timeline_duplicate_raw'] = $duplicate_timestamp;
$manifest['g008_flow_timeline_duplicate_ids'] = $duplicate_ids;
file_put_contents(
	$manifest_path,
	wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);

$evidence = array(
	'evidence_class'      => 'AUTHENTIC_G008_TIMELINE_PRODUCTION_BOUNDARY_FIXTURE',
	'entry_id'            => $entry_id,
	'event_count'         => count( $timeline ),
	'stored_event_count'  => count( $stored ),
	'duplicate_timestamp' => $duplicate_timestamp,
	'duplicate_ids'       => $duplicate_ids,
	'timeline'            => $timeline,
);
file_put_contents(
	$artifact_dir . '/g008-timeline-production-fixture.json',
	wp_json_encode( $evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);

// Install a read-only test observer. It records the production hook lifecycle
// and exact source fingerprints but never changes date presentation.
wp_mkdir_p( WPMU_PLUGIN_DIR );
$probe = <<<'PHP'
<?php
defined( 'ABSPATH' ) || exit;
function pgr_wu008_timeline_callback_count( $hook, $class, $method ) {
	$count = 0;
	if ( ! isset( $GLOBALS['wp_filter'][ $hook ] ) ) {
		return 0;
	}
	foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$fn = $callback['function'];
			if ( is_array( $fn ) && isset( $fn[0], $fn[1] ) && is_object( $fn[0] ) && $fn[0] instanceof $class && $method === $fn[1] ) {
				++$count;
			}
		}
	}
	return $count;
}

$GLOBALS['pgr_wu008_timeline_call_observations'] = array(
	'time_format'     => array(),
	'date_i18n'       => array(),
	'post_date_i18n'  => array(),
);

function pgr_wu008_timeline_stack_signatures() {
	$signatures = array();
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Test-only exact runtime evidence.
	foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 16 ) as $frame ) {
		$signatures[] = ( isset( $frame['class'] ) ? (string) $frame['class'] : '' )
			. ( isset( $frame['type'] ) ? (string) $frame['type'] : '' )
			. ( isset( $frame['function'] ) ? (string) $frame['function'] : '' );
	}
	return $signatures;
}

function pgr_wu008_timeline_adapter_state() {
	$class = 'PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter';
	if ( ! isset( $GLOBALS['wp_filter']['option_time_format'] ) ) {
		return null;
	}

	$adapter = null;
	foreach ( $GLOBALS['wp_filter']['option_time_format']->callbacks as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$fn = $callback['function'];
			if ( is_array( $fn ) && isset( $fn[0], $fn[1] ) && is_object( $fn[0] ) && $fn[0] instanceof $class && 'filter_time_format' === $fn[1] ) {
				$adapter = $fn[0];
				break 2;
			}
		}
	}
	if ( ! is_object( $adapter ) ) {
		return null;
	}

	$reflection = new ReflectionObject( $adapter );
	$snapshot   = array();
	foreach ( array( 'contexts', 'time_contexts' ) as $property_name ) {
		$property = $reflection->getProperty( $property_name );
		$property->setAccessible( true );
		$value = $property->getValue( $adapter );
		if ( 'contexts' === $property_name ) {
			$snapshot['date_context_count'] = is_array( $value ) ? count( $value ) : null;
			continue;
		}

		$snapshot['time_contexts'] = array();
		foreach ( is_array( $value ) ? $value : array() as $key => $context ) {
			$note = $context['note'] ?? null;
			$snapshot['time_contexts'][] = array(
				'key'               => $key,
				'note_id'           => is_object( $note ) && isset( $note->id ) ? (int) $note->id : null,
				'raw'               => $context['raw'] ?? null,
				'timestamp'         => $context['timestamp'] ?? null,
				'time_format'       => $context['time_format'] ?? null,
				'event_kind'        => $context['event_kind'] ?? null,
				'calendar_admitted' => $context['calendar_admitted'] ?? null,
			);
		}
	}
	return $snapshot;
}

function pgr_wu008_timeline_time_gate_diagnostics( $format, $timestamp, $gmt ) {
	$class = 'PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter';
	if ( ! isset( $GLOBALS['wp_filter']['option_time_format'] ) ) {
		return null;
	}

	$adapter = null;
	foreach ( $GLOBALS['wp_filter']['option_time_format']->callbacks as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$fn = $callback['function'];
			if ( is_array( $fn ) && isset( $fn[0], $fn[1] ) && is_object( $fn[0] ) && $fn[0] instanceof $class && 'filter_time_format' === $fn[1] ) {
				$adapter = $fn[0];
				break 2;
			}
		}
	}
	if ( ! is_object( $adapter ) ) {
		return null;
	}

	$trace = debug_backtrace( 0, 20 );
	$path  = null;
	foreach ( $trace as $index => $frame ) {
		$signature = ( isset( $frame['class'] ) ? (string) $frame['class'] : '' )
			. ( isset( $frame['type'] ) ? (string) $frame['type'] : '' )
			. ( isset( $frame['function'] ) ? (string) $frame['function'] : '' );
		if ( 'date_i18n' === $signature && isset( $trace[ $index + 6 ] ) ) {
			$path = array_slice( $trace, $index, 7 );
			break;
		}
	}
	if ( null === $path ) {
		return null;
	}

	$reflection = new ReflectionObject( $adapter );
	$property   = $reflection->getProperty( 'time_contexts' );
	$property->setAccessible( true );
	$contexts = $property->getValue( $adapter );

	$note = $path[4]['args'][0] ?? null;
	$key  = is_object( $note ) ? spl_object_id( $note ) : null;
	if ( null === $key || ! is_array( $contexts ) || ! isset( $contexts[ $key ] ) ) {
		return array( 'context_present' => false );
	}

	$context = $contexts[ $key ];
	$notes   = $path[5]['args'][0] ?? null;
	$entry   = $path[6]['args'][0] ?? null;
	$form    = $path[6]['args'][1] ?? null;

	$order_method  = $reflection->getMethod( 'note_identity_order' );
	$entry_method  = $reflection->getMethod( 'entry_key' );
	$args_method   = $reflection->getMethod( 'second_path_arguments_match' );
	$event_method  = $reflection->getMethod( 'event_kind' );
	foreach ( array( $order_method, $entry_method, $args_method, $event_method ) as $method ) {
		$method->setAccessible( true );
	}

	return array(
		'context_present'        => true,
		'format_matches'         => ( $context['time_format'] ?? null ) === $format,
		'calendar_admitted'      => true === ( $context['calendar_admitted'] ?? null ),
		'gmt_true'               => true === $gmt,
		'timestamp_is_int'       => is_int( $timestamp ),
		'timestamp_matches'      => ( $context['timestamp'] ?? null ) === $timestamp,
		'note_same'              => ( $context['note'] ?? null ) === $note,
		'note_snapshot_same'     => is_object( $note ) && ( $context['note_snapshot'] ?? null ) === get_object_vars( $note ),
		'notes_is_array'         => is_array( $notes ),
		'notes_same'             => ( $context['notes'] ?? null ) === $notes,
		'notes_order_same'       => is_array( $notes ) && ( $context['notes_order'] ?? null ) === $order_method->invoke( $adapter, $notes ),
		'entry_is_array'         => is_array( $entry ),
		'entry_same'             => ( $context['entry'] ?? null ) === $entry,
		'entry_key_same'         => is_array( $entry ) && ( $context['entry_key'] ?? null ) === $entry_method->invoke( $adapter, $entry ),
		'form_same'              => ( $context['form'] ?? null ) === $form,
		'path_arguments_match'   => $args_method->invoke( $adapter, $path, $context, $format ),
		'event_kind_same'        => is_object( $note ) && is_array( $entry ) && ( $context['event_kind'] ?? null ) === $event_method->invoke( $adapter, $note, $entry, $context['raw'] ?? '' ),
		'gfcommon_args'          => $path[1]['args'] ?? null,
		'flow_common_args'       => $path[2]['args'] ?? null,
	);
}


add_action(
	'wp_loaded',
	static function () {
		add_filter(
			'option_date_format',
			static function ( $format, $option = null ) {
				$stack = pgr_wu008_timeline_stack_signatures();
				if ( ! in_array( 'Gravity_Flow_Entry_Detail::get_note_header', $stack, true ) ) {
					return $format;
				}

				static $post_observer_registered = false;
				if ( ! $post_observer_registered ) {
					add_filter(
						'date_i18n',
						static function ( $date, $date_format, $timestamp, $gmt ) {
							$inner_stack = pgr_wu008_timeline_stack_signatures();
							if ( in_array( 'Gravity_Flow_Entry_Detail::get_note_header', $inner_stack, true ) ) {
								$GLOBALS['pgr_wu008_timeline_call_observations']['post_date_i18n'][] = array(
									'output'        => $date,
									'format'        => $date_format,
									'timestamp'     => $timestamp,
									'gmt'           => $gmt,
									'stack'         => $inner_stack,
									'adapter_state' => pgr_wu008_timeline_adapter_state(),
								);
							}
							return $date;
						},
						PHP_INT_MAX,
						4
					);
					$post_observer_registered = true;
				}

				return $format;
			},
			PHP_INT_MAX,
			2
		);

		add_filter(
			'option_time_format',
			static function ( $format, $option = null ) {
				$stack = pgr_wu008_timeline_stack_signatures();
				if ( in_array( 'Gravity_Flow_Entry_Detail::get_note_header', $stack, true ) ) {
					$GLOBALS['pgr_wu008_timeline_call_observations']['time_format'][] = array(
						'format'        => $format,
						'option'        => $option,
						'stack'         => $stack,
						'adapter_state' => pgr_wu008_timeline_adapter_state(),
					);
				}
				return $format;
			},
			PHP_INT_MAX,
			2
		);

		add_filter(
			'date_i18n',
			static function ( $date, $format, $timestamp, $gmt ) {
				$stack = pgr_wu008_timeline_stack_signatures();
				if ( in_array( 'Gravity_Flow_Entry_Detail::get_note_header', $stack, true ) ) {
					$GLOBALS['pgr_wu008_timeline_call_observations']['date_i18n'][] = array(
						'output'           => $date,
						'format'           => $format,
						'timestamp'        => $timestamp,
						'gmt'              => $gmt,
						'stack'            => $stack,
						'time_gate_checks' => pgr_wu008_timeline_time_gate_diagnostics( $format, $timestamp, $gmt ),
					);
				}
				return $date;
			},
			PHP_INT_MAX,
			4
		);
	}
);
add_action(
	'wp_footer',
	static function () {
		$class     = 'PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter';
		$flow_root = WP_PLUGIN_DIR . '/gravityflow';
		$gf_main   = class_exists( 'GFForms', false ) ? ( new ReflectionClass( 'GFForms' ) )->getFileName() : null;
		$paths     = array(
			'flow_entry_detail' => $flow_root . '/includes/pages/class-entry-detail.php',
			'flow_common'       => $flow_root . '/includes/class-common.php',
			'flow_print'        => $flow_root . '/includes/pages/class-print-entries.php',
			'gf_common'         => is_string( $gf_main ) ? dirname( $gf_main ) . '/common.php' : '',
		);
		$hashes = array();
		foreach ( $paths as $key => $path ) {
			$hashes[ $key ] = is_readable( $path ) ? hash_file( 'sha256', $path ) : null;
		}
		$evidence = array(
			'class_loaded'             => class_exists( $class, false ),
			'module_enabled'           => class_exists( 'PGR_Module_Registry', false ) && PGR_Module_Registry::is_enabled( 'jalali_presentation' ),
			'locale'                   => function_exists( 'determine_locale' ) ? determine_locale() : null,
			'option_date_format_hooks' => pgr_wu008_timeline_callback_count( 'option_date_format', $class, 'filter_date_format' ),
			'option_time_format_hooks' => pgr_wu008_timeline_callback_count( 'option_time_format', $class, 'filter_time_format' ),
			'date_i18n_hooks'          => pgr_wu008_timeline_callback_count( 'date_i18n', $class, 'filter_date_i18n' ),
			'flow_version'             => defined( 'GRAVITY_FLOW_VERSION' ) ? GRAVITY_FLOW_VERSION : null,
			'gf_version'               => class_exists( 'GFForms', false ) ? (string) GFForms::$version : null,
			'source_fingerprints'      => $hashes,
			'call_observations'        => $GLOBALS['pgr_wu008_timeline_call_observations'] ?? array(),
		);
		$artifact_dir = getenv( 'WU008_ARTIFACT_DIR' );
		if ( is_string( $artifact_dir ) && '' !== $artifact_dir ) {
			$probe_mode = ! $evidence['module_enabled']
				? 'disabled'
				: ( 'fa_IR' === $evidence['locale'] ? 'enabled' : 'english' );
			file_put_contents(
				trailingslashit( $artifact_dir ) . 'g008-timeline-production-hook-' . $probe_mode . '.json',
				wp_json_encode( $evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
			);
		}
		echo '<script>window.pgrG008TimelineProductionEvidence=' . wp_json_encode( $evidence ) . ';</script>';
	},
	PHP_INT_MAX
);
PHP;
file_put_contents( WPMU_PLUGIN_DIR . '/pgr-wu008-g008-timeline-production-probe.php', $probe . "\n" );

echo wp_json_encode( $evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
