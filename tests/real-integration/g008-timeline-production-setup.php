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
	$time_tail     = $civil->format( 'g:i' );
	$persian_time  = strtr(
		$time_tail,
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
	if ( ! is_string( $native_header ) || false === strpos( $native_header, $time_tail ) ) {
		throw new RuntimeException( 'Native Timeline fixture did not expose the independently expected ASCII time token.' );
	}
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
		'expected_native_time_tail' => $time_tail,
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
			'option_date_format_hooks' => pgr_wu008_timeline_callback_count( 'option_date_format', $class, 'filter_date_format' ),
			'date_i18n_hooks'          => pgr_wu008_timeline_callback_count( 'date_i18n', $class, 'filter_date_i18n' ),
			'flow_version'             => defined( 'GRAVITY_FLOW_VERSION' ) ? GRAVITY_FLOW_VERSION : null,
			'gf_version'               => class_exists( 'GFForms', false ) ? (string) GFForms::$version : null,
			'source_fingerprints'      => $hashes,
		);
		$artifact_dir = getenv( 'WU008_ARTIFACT_DIR' );
		if ( is_string( $artifact_dir ) && '' !== $artifact_dir ) {
			$probe_mode = $evidence['module_enabled'] ? 'enabled' : 'disabled';
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
