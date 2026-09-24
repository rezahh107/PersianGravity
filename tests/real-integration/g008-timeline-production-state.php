<?php
/** Fresh exact-Head production evidence for the G-008 Flow Timeline adapter. */
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }

$artifact_dir  = getenv( 'WU008_ARTIFACT_DIR' );
$manifest_path = getenv( 'WU008_MANIFEST_PATH' );
$mode          = getenv( 'WU008_G008_MODE' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir || ! is_string( $manifest_path ) || '' === $manifest_path || ! in_array( $mode, array( 'enabled', 'disabled' ), true ) ) {
	throw new RuntimeException( 'Timeline production state requires artifact/manifest paths and enabled|disabled mode.' );
}
if (
	! defined( 'GRAVITY_FLOW_VERSION' ) || '3.1.0' !== GRAVITY_FLOW_VERSION ||
	! class_exists( 'GFForms' ) || '3.1.1.1' !== (string) GFForms::$version ||
	'ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404' !== getenv( 'WU008_FLOW_SHA256' ) ||
	'542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b' !== getenv( 'WU008_GF_SHA256' ) ||
	'Asia/Tehran' !== wp_timezone_string() || 'UTC' !== date_default_timezone_get()
) {
	throw new RuntimeException( 'Timeline production exact package/runtime identity drifted.' );
}

$manifest = json_decode( (string) file_get_contents( $manifest_path ), true, 512, JSON_THROW_ON_ERROR );
$fixture  = $manifest['g008_flow_timeline_multi_note'] ?? null;
if ( ! is_array( $fixture ) || count( $fixture ) < 5 ) {
	throw new RuntimeException( 'Timeline production boundary fixture is incomplete.' );
}

// Bind production admission to the exact source files qualified by research.
$flow_root = WP_PLUGIN_DIR . '/gravityflow';
$gf_reflection = new ReflectionClass( 'GFForms' );
$gf_main = $gf_reflection->getFileName();
if ( ! is_string( $gf_main ) || '' === $gf_main ) {
	throw new RuntimeException( 'Gravity Forms runtime source location is unavailable.' );
}
$source_paths = array(
	'flow_entry_detail' => $flow_root . '/includes/pages/class-entry-detail.php',
	'flow_common'       => $flow_root . '/includes/class-common.php',
	'flow_print'        => $flow_root . '/includes/pages/class-print-entries.php',
	'gf_common'         => dirname( $gf_main ) . '/common.php',
);
$source_expected = array(
	'flow_entry_detail' => 'a7634c5604184502457bcb22cdf1ade892e84c888cc60996aea8a810ced7680a',
	'flow_common'       => 'a8844f4b6ac37eed1a2cc1e904418f6ed8c6b4480e982c5a809e895a6f9e0cc8',
	'flow_print'        => 'df969bf8a37ed4f5619e0e8b2a753dd1133fa0c7551b158d740248c67fcb95c6',
	'gf_common'         => 'ac4ed495ee02a119a4fd08c77279f0db0905e6f20f59c472d5e6bffc801ca355',
);
$source_actual = array();
foreach ( $source_paths as $key => $path ) {
	if ( ! is_readable( $path ) ) { throw new RuntimeException( 'Qualified source file is unreadable: ' . $key ); }
	$source_actual[ $key ] = hash_file( 'sha256', $path );
}
if ( $source_actual !== $source_expected ) {
	throw new RuntimeException( 'Qualified Timeline source fingerprint drifted: ' . wp_json_encode( $source_actual ) );
}

$callback_count = static function ( $hook, $class, $method ) {
	$count = 0;
	if ( ! isset( $GLOBALS['wp_filter'][ $hook ] ) ) { return 0; }
	foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$fn = $callback['function'];
			if ( is_array( $fn ) && isset( $fn[0], $fn[1] ) && is_object( $fn[0] ) && $fn[0] instanceof $class && $method === $fn[1] ) { ++$count; }
		}
	}
	return $count;
};
$enabled = 'enabled' === $mode;
$module_enabled = class_exists( 'PGR_Module_Registry', false ) && PGR_Module_Registry::is_enabled( 'jalali_presentation' );
if ( $enabled !== $module_enabled ) { throw new RuntimeException( 'Timeline production module mode mismatch.' ); }
$timeline_class = 'PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter';
$pre_hook_state = array(
	'class_loaded'       => class_exists( $timeline_class, false ),
	'option_date_format' => $callback_count( 'option_date_format', $timeline_class, 'filter_date_format' ),
	'date_i18n'          => $callback_count( 'date_i18n', $timeline_class, 'filter_date_i18n' ),
);
if ( $enabled ) {
	if ( ! $pre_hook_state['class_loaded'] || 1 !== $pre_hook_state['option_date_format'] || 0 !== $pre_hook_state['date_i18n'] ) {
		throw new RuntimeException( 'Enabled Timeline adapter did not start with the exact lazy hook contract.' );
	}
} elseif ( $pre_hook_state['class_loaded'] || 0 !== $pre_hook_state['option_date_format'] || 0 !== $pre_hook_state['date_i18n'] ) {
	throw new RuntimeException( 'Disabled Timeline module loaded or registered owned production hooks.' );
}

wp_set_current_user( 1 );
require_once WP_PLUGIN_DIR . '/gravityflow/includes/pages/class-entry-detail.php';
require_once WP_PLUGIN_DIR . '/gravityflow/includes/pages/class-print-entries.php';
$entry_id = (int) $manifest['g008_flow_entry_detail_candidate_entry_id'];
$entry    = GFAPI::get_entry( $entry_id );
$form     = GFAPI::get_form( (int) $entry['form_id'] );
if ( is_wp_error( $entry ) || ! is_array( $form ) ) { throw new RuntimeException( 'Timeline production Entry/form fixture could not be loaded.' ); }

$headers = static function ( $html ) {
	preg_match_all( '/<div class="gravityflow-note-meta">(.*?)<\/div>/s', (string) $html, $matches );
	return array_values( $matches[1] ?? array() );
};
$bodies = static function ( $html ) {
	preg_match_all( '/<div class="gravityflow-note-body">(.*?)<\/div>/s', (string) $html, $matches );
	return array_map( static function ( $value ) { return trim( wp_strip_all_tags( html_entity_decode( $value ) ) ); }, $matches[1] ?? array() );
};
$render = static function () use ( $entry, $form ) {
	ob_start();
	Gravity_Flow_Entry_Detail::timeline( $entry, $form );
	return (string) ob_get_clean();
};
$render_entry = static function ( $target_entry, $target_form ) {
	ob_start();
	Gravity_Flow_Entry_Detail::timeline( $target_entry, $target_form );
	return (string) ob_get_clean();
};
$render_print = static function () use ( $entry_id ) {
	$old_get = $_GET;
	$_GET['lid'] = (string) $entry_id;
	$_GET['timelines'] = '1';
	ob_start();
	Gravity_Flow_Print_Entries::render();
	$html = (string) ob_get_clean();
	$_GET = $old_get;
	return $html;
};

// Full raw/state snapshot before and after presentation rendering.
$snapshot = static function () use ( $entry_id, $form ) {
	global $wpdb;
	$current = GFAPI::get_entry( $entry_id );
	$api     = new Gravity_Flow_API( (int) $form['id'] );
	$step    = $api->get_current_step( $current );
	if ( ! $step ) { throw new RuntimeException( 'Timeline snapshot current step missing.' ); }
	$assignments = array_map( static function ( $assignee ) { return $assignee->get_key(); }, $step->get_assignees() );
	sort( $assignments );
	$rest = rest_do_request( new WP_REST_Request( 'GET', '/gf/v2/entries/' . $entry_id ) );
	return array(
		'entry'       => $current,
		'db_entry'    => $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GFFormsModel::get_entry_table_name() . ' WHERE id=%d', $entry_id ), ARRAY_A ),
		'db_meta'     => $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . GFFormsModel::get_entry_meta_table_name() . ' WHERE entry_id=%d ORDER BY id', $entry_id ), ARRAY_A ),
		'db_notes'    => $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . GFFormsModel::get_entry_notes_table_name() . ' WHERE entry_id=%d ORDER BY id', $entry_id ), ARRAY_A ),
		'form'        => GFAPI::get_form( (int) $form['id'] ),
		'feeds'       => gravity_flow()->get_feeds( (int) $form['id'] ),
		'rest_status' => $rest->get_status(),
		'rest'        => $rest->get_data(),
		'workflow'    => array(
			'step'                 => (int) $step->get_id(),
			'final_status'         => (string) gform_get_meta( $entry_id, 'workflow_final_status' ),
			'assignments'          => $assignments,
			'due_timestamp'        => (int) $step->get_due_date_timestamp(),
			'expiration_timestamp' => (int) $step->get_expiration_timestamp(),
			'schedule_timestamp'   => (int) $step->get_schedule_timestamp(),
			'overdue'              => (bool) $step->is_overdue(),
			'expired'              => (bool) $step->is_expired(),
		),
	);
};

$before = $snapshot();
$wp_native   = date_i18n( 'Y-m-d H:i', 1900269060, true );
$gf_native   = GFCommon::format_date( $entry['date_created'], false, 'Y-m-d', true );
$text_native = Gravity_Flow_Common::get_timeline( $entry );
$sidebar = static function () use ( $entry, $form ) {
	$step = ( new Gravity_Flow_API( (int) $form['id'] ) )->get_current_step( $entry );
	ob_start();
	gravity_flow()->workflow_entry_detail_status_box( $form, $entry, $step, array() );
	return (string) ob_get_clean();
};
$sidebar_native = $sidebar();

$actual = $render();
$repeat = $render();
$actual_headers = $headers( $actual );
$actual_bodies  = $bodies( $actual );
$expected_native_headers = array_map( static function ( $item ) { return (string) $item['expected_header']; }, $fixture );
$checks = array(
	'repeated'                 => $actual === $repeat,
	'marker_absent'            => false === strpos( $actual, 'PGRTIMELINE' ),
	'generic_wp_isolation'     => date_i18n( 'Y-m-d H:i', 1900269060, true ) === $wp_native,
	'unrelated_gf_isolation'   => GFCommon::format_date( $entry['date_created'], false, 'Y-m-d', true ) === $gf_native,
	'text_api_isolation'       => Gravity_Flow_Common::get_timeline( $entry ) === $text_native,
	'workflow_info_isolation'  => $sidebar() === $sidebar_native,
	'header_count'             => count( $actual_headers ) === count( $fixture ),
	'duplicate_fixture_distinct'=> count( array_unique( array_map( 'intval', $manifest['g008_flow_timeline_duplicate_ids'] ?? array() ) ) ) >= 2,
);

$checks['calendar_and_time'] = true;
foreach ( $fixture as $index => $item ) {
	$header = isset( $actual_headers[ $index ] ) ? html_entity_decode( wp_strip_all_tags( $actual_headers[ $index ] ) ) : '';
	if ( $enabled ) {
		if ( 0 !== strpos( $header, (string) $item['expected_jalali_date'] ) ) { $checks['calendar_and_time'] = false; }
		$tail = (string) ( $item['expected_native_time_tail'] ?? '' );
		if ( '' !== $tail && false === strpos( $header, $tail ) ) { $checks['calendar_and_time'] = false; }
	} elseif ( $header !== html_entity_decode( wp_strip_all_tags( (string) $item['expected_header'] ) ) ) {
		$checks['calendar_and_time'] = false;
	}
}
$checks['disabled_native'] = $enabled || array_map( static function ( $value ) { return html_entity_decode( wp_strip_all_tags( $value ) ); }, $actual_headers ) === array_map( static function ( $value ) { return html_entity_decode( wp_strip_all_tags( $value ) ); }, $expected_native_headers );
$checks['enabled_changed'] = ! $enabled || $actual_headers !== $expected_native_headers;
foreach ( $manifest['g008_flow_timeline_stored_notes'] ?? array() as $stored ) {
	if ( ! in_array( trim( (string) $stored['value'] ), $actual_bodies, true ) ) { $checks['stored_bodies_preserved'] = false; break; }
	$checks['stored_bodies_preserved'] = true;
}

// Out-of-range facade null must preserve the exact native Timeline header.
$range_entry = GFAPI::get_entry( (int) $manifest['g008_flow_entry_detail_range_entry_id'] );
$range_form  = GFAPI::get_form( (int) $range_entry['form_id'] );
$range_notes = Gravity_Flow_Common::get_timeline_notes( $range_entry );
$range_expected = array_map( static function ( $note ) { return Gravity_Flow_Common::format_date( $note->date_created, '', false, true ); }, $range_notes );
$range_actual = $headers( $render_entry( $range_entry, $range_form ) );
$checks['conversion_null_native'] = $range_actual === $range_expected && false === strpos( implode( '', $range_actual ), 'PGRTIMELINE' );

// Unsupported U and pre_option short-circuit retain native behavior.
$u_filter = static function () { return 'U'; };
add_filter( 'option_date_format', $u_filter, 10 );
$u_expected = array_map( static function ( $note ) { return Gravity_Flow_Common::format_date( $note->date_created, '', false, true ); }, Gravity_Flow_Common::get_timeline_notes( $entry ) );
$u_actual = $headers( $render() );
remove_filter( 'option_date_format', $u_filter, 10 );
$checks['u_native'] = $u_actual === $u_expected;

$pre_filter = static function () { return 'Y-m-d'; };
add_filter( 'pre_option_date_format', $pre_filter, 10 );
$pre_expected = array_map( static function ( $note ) { return Gravity_Flow_Common::format_date( $note->date_created, '', false, true ); }, Gravity_Flow_Common::get_timeline_notes( $entry ) );
$pre_actual = $headers( $render() );
remove_filter( 'pre_option_date_format', $pre_filter, 10 );
$checks['pre_option_native'] = $pre_actual === $pre_expected;

// Nested authentic Timeline rendering before outer rows must not cross-borrow tokens.
$nesting = false;
$nested_output = null;
$nested_filter = static function ( $notes ) use ( &$nesting, &$nested_output, $render ) {
	if ( ! $nesting ) { $nesting = true; $nested_output = $render(); $nesting = false; }
	return $notes;
};
add_filter( 'gravityflow_timeline_notes', $nested_filter, PHP_INT_MAX );
$nested_outer = $render();
remove_filter( 'gravityflow_timeline_notes', $nested_filter, PHP_INT_MAX );
$checks['nested_timeline_isolation'] = $nested_outer === $actual && $nested_output === $actual;

// Same-argument date_i18n re-entry may not borrow the outer Timeline context.
$in_reentry = false;
$nested_reentry_clean = true;
$reentrant = static function ( $value, $format, $timestamp, $gmt ) use ( &$in_reentry, &$nested_reentry_clean ) {
	if ( ! $in_reentry && is_string( $format ) && false !== strpos( $format, '\\P\\G\\R\\T\\I\\M\\E\\L\\I\\N\\E' ) ) {
		$in_reentry = true;
		$nested = date_i18n( $format, $timestamp, $gmt );
		$nested_reentry_clean = $nested_reentry_clean && is_string( $nested ) && false === strpos( $nested, 'PGRTIMELINE' );
		$in_reentry = false;
	}
	return $value;
};
add_filter( 'date_i18n', $reentrant, 1, 4 );
$reentrant_output = $render();
remove_filter( 'date_i18n', $reentrant, 1 );
$checks['same_argument_reentry_isolation'] = $nested_reentry_clean && $reentrant_output === $actual;

// Abort after the production first hook arms; next calls must prune stale context.
$abort = static function () { throw new RuntimeException( 'PGR_TIMELINE_TEST_ABORT' ); };
add_filter( 'option_date_format', $abort, PHP_INT_MAX );
$level = ob_get_level();
try { $render(); } catch ( RuntimeException $exception ) { if ( 'PGR_TIMELINE_TEST_ABORT' !== $exception->getMessage() ) { throw $exception; } }
while ( ob_get_level() > $level ) { ob_end_clean(); }
remove_filter( 'option_date_format', $abort, PHP_INT_MAX );
$checks['abort_cleanup'] = false === strpos( (string) date_i18n( 'Y-m-d', 1900269060, true ), 'PGRTIMELINE' ) && $render() === $actual;

// A late format mutation after arming must never produce Jalali or leak marker.
$suffix = static function ( $value ) { return $value . '!'; };
add_filter( 'option_date_format', $suffix, PHP_INT_MAX );
$late = $render();
remove_filter( 'option_date_format', $suffix, PHP_INT_MAX );
$late_headers = $headers( $late );
$checks['late_format_mismatch_native'] = false === strpos( $late, 'PGRTIMELINE' );
if ( $enabled ) {
	foreach ( $late_headers as $index => $header ) {
		if ( isset( $fixture[ $index ] ) && 0 === strpos( html_entity_decode( wp_strip_all_tags( $header ) ), (string) $fixture[ $index ]['expected_jalali_date'] ) ) {
			$checks['late_format_mismatch_native'] = false;
		}
	}
}

$print = $render_print();
$print_headers = $headers( $print );
$print_bodies  = $bodies( $print );
$checks['print_inherits_timeline'] = $print_headers === $actual_headers;
$checks['print_marker_absent'] = false === strpos( $print, 'PGRTIMELINE' );
foreach ( $manifest['g008_flow_timeline_stored_notes'] ?? array() as $stored ) {
	if ( ! in_array( trim( (string) $stored['value'] ), $print_bodies, true ) ) { $checks['print_bodies_preserved'] = false; break; }
	$checks['print_bodies_preserved'] = true;
}

$after = $snapshot();
$checks['raw_state_equal'] = $before === $after && 200 === (int) $before['rest_status'];
$post_hook_state = array(
	'class_loaded'       => class_exists( $timeline_class, false ),
	'option_date_format' => $callback_count( 'option_date_format', $timeline_class, 'filter_date_format' ),
	'date_i18n'          => $callback_count( 'date_i18n', $timeline_class, 'filter_date_i18n' ),
);
$checks['hook_lifecycle'] = $enabled
	? ( 1 === $post_hook_state['option_date_format'] && 1 === $post_hook_state['date_i18n'] )
	: ( ! $post_hook_state['class_loaded'] && 0 === $post_hook_state['option_date_format'] && 0 === $post_hook_state['date_i18n'] );

$result = array(
	'schema_version'                    => '1.0.0',
	'evidence_class'                    => 'AUTHENTIC_G008_TIMELINE_PRODUCTION_ADMISSION',
	'mode'                              => $mode,
	'exact_persiangravity_commit'       => getenv( 'WU008_PGR_SHA' ) ?: null,
	'exact_persiangravity_package_sha256'=> getenv( 'WU008_PGR_PACKAGE_SHA256' ) ?: null,
	'exact_gravityflow_version'         => GRAVITY_FLOW_VERSION,
	'exact_gravityflow_package_sha256'  => getenv( 'WU008_FLOW_SHA256' ),
	'exact_gravityforms_version'        => (string) GFForms::$version,
	'exact_gravityforms_package_sha256' => getenv( 'WU008_GF_SHA256' ),
	'source_fingerprints'               => $source_actual,
	'site_timezone'                     => wp_timezone_string(),
	'php_default_timezone'              => date_default_timezone_get(),
	'pre_hook_state'                    => $pre_hook_state,
	'post_hook_state'                   => $post_hook_state,
	'checks'                            => $checks,
	'headers'                           => $actual_headers,
	'print_headers'                     => $print_headers,
	'before'                            => $before,
	'after'                             => $after,
);
file_put_contents( $artifact_dir . '/g008-timeline-production-' . $mode . '.json', wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
if ( in_array( false, $checks, true ) ) {
	throw new RuntimeException( 'Timeline production evidence failed: ' . implode( ', ', array_keys( array_filter( $checks, static function ( $value ) { return ! $value; } ) ) ) );
}
echo 'G008_TIMELINE_PRODUCTION_' . strtoupper( $mode ) . " PASS\n";
