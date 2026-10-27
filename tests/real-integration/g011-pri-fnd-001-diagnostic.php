<?php
/**
 * PRI-FND-001: one-shot diagnostic on the pinned, authentic GF runtime.
 * Test-only, disposable form/entry values. No vendor or production edits.
 */
defined( 'ABSPATH' ) || exit;
if (
	'3.1.1.1' !== GFForms::$version ||
	'542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b' !== getenv( 'WU008_GF_SHA256' )
) {
	throw new RuntimeException( 'PRI-FND-001 exact licensed host package mismatch.' );
}
$pri_evidence = array(
	'contract' => 'PRI-FND-001-DIAGNOSTIC-V1',
	'host_version' => GFForms::$version,
	'host_sha256' => getenv( 'WU008_GF_SHA256' ),
	'head' => getenv( 'WU008_PGR_SHA' ),
	'vendor_method_provenance' => array(),
	'cases' => array(),
);
foreach ( array(
	'GFCommon::parse_date' => array( 'GFCommon', 'parse_date' ),
	'GF_Field_Date::get_value_merge_tag' => array( 'GF_Field_Date', 'get_value_merge_tag' ),
) as $title => $method ) {
	if ( ! method_exists( $method[0], $method[1] ) ) {
		continue;
	}
	$ref = new ReflectionMethod( $method[0], $method[1] );
	$source_lines = file( $ref->getFileName() );
	if ( false === $source_lines ) {
		throw new RuntimeException( 'PRI-FND-001 cannot establish vendor source provenance.' );
	}
	$pri_evidence['vendor_method_provenance'][ $title ] = array(
		'basename' => basename( $ref->getFileName() ),
		'start_line' => $ref->getStartLine(),
		'end_line' => $ref->getEndLine(),
		'method_sha256' => hash( 'sha256', implode(
			'', array_slice( $source_lines, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1 )
		) ),
	); // No licensed vendor source bytes exported.
}
$expected_ref = new ReflectionMethod( 'PGR_GF_Confirmation_Jalali_Adapter', 'expected_native_tag' );
$jalali_ref = new ReflectionMethod( 'PGR_GF_Confirmation_Jalali_Adapter', 'present_tag' );
$pri_formats = array(
	'mdy' => '03/21/2026',
	'mdy_dash' => '03-21-2026',
	'mdy_dot' => '03.21.2026',
	'dmy' => '21/03/2026',
	'dmy_dash' => '21-03-2026',
	'dmy_dot' => '21.03.2026',
	'ymd_slash' => '2026/03/21',
	'ymd_dash' => '2026-03-21',
	'ymd_dot' => '2026.03.21',
);
foreach ( $pri_formats as $format => $stored ) {
	$test_form = $form; // Actual configured GF fields, with one cloned metadata change.
	$found = false;
	foreach ( $test_form['fields'] as $idx => $field ) {
		if ( $field instanceof GF_Field_Date && 2 === (int) $field->id ) {
			$test_form['fields'][ $idx ] = clone $field;
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- GF public property.
			$test_form['fields'][ $idx ]->dateFormat = $format;
			$found = true;
		}
	}
	if ( ! $found ) {
		throw new RuntimeException( 'PRI-FND-001 configured GF Date-field missing.' );
	}
	$tag = '{Visit:2}';
	$prefix = 'PRI-' . $format . ': ';
	$suffix = '; end-' . $format . '</p>';
	$test_form['confirmations'] = array(
		'pri' => array(
			'id' => 'pri', 'name' => 'Diagnostic', 'type' => 'message',
			'isDefault' => true, 'isActive' => true, 'disableAutoformat' => true,
			'message' => '<p>' . $prefix . $tag . $suffix,
		),
	);
	$entry_id = GFAPI::add_entry( array(
		'form_id' => (int) $form_id,
		'status' => 'active',
		'date_created' => '2026-03-20 20:30:00',
		'1' => 'synthetic-diagnostic',
		'2' => $stored,
	) );
	$entry_actual = is_wp_error( $entry_id ) ? null : GFAPI::get_entry( $entry_id );
	if ( ! is_array( $entry_actual ) ) {
		throw new RuntimeException( 'PRI-FND-001 could not read synthetic persisted Entry.' );
	}
	$parse = GFCommon::parse_date( (string) $entry_actual['2'], $format );
	$proposed = $jalali_ref->invoke( null, $tag, $test_form, $entry_actual );
	$expected = $expected_ref->invoke( null, $tag, $test_form, $entry_actual );
	$outputs = array();
	$hook_counts = array();
	$before_adapter = array();
	$original_option = get_option( 'pgr_modules' );
	try {
		foreach ( array( 'native' => false, 'enabled' => true ) as $mode => $on ) {
			$states = $original_option;
			$states['states']['jalali_presentation'] = $on;
			update_option( 'pgr_modules', $states );
			$callbacks = 0;
			$incoming = null;
			$spy = static function ( $text ) use ( &$callbacks ) {
				++$callbacks;
				return $text;
			};
			$intercept = static function ( $value ) use ( &$incoming ) {
				$incoming = $value;
				return $value;
			};
			add_filter( 'gform_pre_replace_merge_tags', $spy, 998, 1 );
			add_filter( 'gform_confirmation', $intercept, 19, 1 );
			try {
				$form_copy = $test_form;
				$outputs[ $mode ] = GFFormDisplay::handle_confirmation( $form_copy, $entry_actual, false );
			} finally {
				remove_filter( 'gform_pre_replace_merge_tags', $spy, 998 );
				remove_filter( 'gform_confirmation', $intercept, 19 );
			}
			$before_adapter[ $mode ] = $incoming;
			$hook_counts[ $mode ] = $callbacks;
		}
	} finally {
		update_option( 'pgr_modules', $original_option );
	}
	$received = $before_adapter['enabled'];
	$pos = is_string( $received ) ? strpos( $received, $prefix ) : false;
	$start = false === $pos ? null : $pos + strlen( $prefix );
	$end = null === $start ? false : strpos( $received, $suffix, $start );
	$actual_value = false === $end || null === $start ? null : substr( $received, $start, $end - $start );
	// Reproduce the precise failing predicate from the unmodified Adapter.
	$reason = null === $proposed ? 'present_tag_null -> skip occurrence / no spans' :
		( null === $expected ? 'expected_native_tag_null -> full native fallback' :
		( null === $actual_value ? 'static_anchor_not_found' :
		( $actual_value !== $expected ? 'invalid_span_guard -> substr(native,span_start,strlen(expected)) !== expected' :
		( $outputs['enabled'] === $outputs['native'] ? 'no_conversion_despite_matching_native' : 'converted' ) ) ) );
	// Probe documented host formatting primitives separately from the one
	// already completed confirmation render. These calls are test-only.
	$host_format_probes = array();
	foreach ( array( 'default' => null, 'field_dateFormat' => $format, 'mdy' => 'mdy' ) as $name => $display_format ) {
		try {
			$host_format_probes[ 'GFCommon_date_display_' . $name ] = null === $display_format ?
				GFCommon::date_display( $entry_actual['2'] ) :
				GFCommon::date_display( $entry_actual['2'], $display_format );
		} catch ( Throwable $problem ) {
			$host_format_probes[ 'GFCommon_date_display_' . $name ] = array(
				'exception_class' => get_class( $problem ),
				'message' => $problem->getMessage(),
			);
		}
	}
	foreach ( $test_form['fields'] as $field ) {
		if ( $field instanceof GF_Field_Date && 2 === (int) $field->id ) {
			$host_format_probes['GF_Field_Date_get_value_merge_tag_signature'] =
				( new ReflectionMethod( $field, 'get_value_merge_tag' ) )->getNumberOfParameters();
			try {
				$host_format_probes['GF_Field_Date_merge_raw_input'] = $field->get_value_merge_tag(
					$entry_actual['2'], '2', $entry_actual, $test_form, '', $entry_actual['2'],
					false, true, 'html', false
				);
			} catch ( Throwable $problem ) {
				$host_format_probes['GF_Field_Date_merge_raw_input'] = array(
					'exception_class' => get_class( $problem ),
					'message' => $problem->getMessage(),
				);
			}
			break;
		}
	}
	$pri_evidence['cases'][ $format ] = array(
		'raw_entry_value_from_GFAPI' => $entry_actual['2'],
		'selected_dateFormat' => $format,
		'selected_merge_tag' => $tag,
		'GFCommon_parse_date_direct_result' => $parse,
		'host_read_only_format_probes' => $host_format_probes,
		'adapter_expected_native' => $expected,
		'adapter_proposed_jalali' => $proposed,
		'actual_native_value_in_selected_text' => $actual_value,
		'GF_native_output' => $outputs['native'],
		'GF_enabled_output' => $outputs['enabled'],
		'GF_before_adapter_output_enabled' => $received,
		'entry_unchanged' => $entry_actual['2'] === ( GFAPI::get_entry( $entry_id )['2'] ?? null ),
		'merge_callback_count_native' => $hook_counts['native'],
		'merge_callback_count_enabled' => $hook_counts['enabled'],
		'fallback_branch_and_reason' => $reason,
		'converted' => $outputs['native'] !== $outputs['enabled'],
	);
}
// Independent consistency trial: BOTH the form's dateFormat and the Entry
// exist as persisted GFAPI records before native Confirmation selection.
$pri_evidence['persisted_form_cases'] = array();
foreach ( $pri_formats as $format => $stored ) {
	$persisted_form_id = GFAPI::add_form( array(
		'title' => 'PRI Diagnostic ' . $format,
		'fields' => array(
			array( 'id' => 1, 'type' => 'text', 'label' => 'Name' ),
			array(
				'id' => 2, 'type' => 'date', 'label' => 'Visit',
				'dateType' => 'datepicker', 'dateFormat' => $format,
			),
		),
	) );
	if ( ! is_int( $persisted_form_id ) || $persisted_form_id <= 0 ) {
		throw new RuntimeException( 'PRI-FND-001 cannot create persisted disposable GF form: ' . $format );
	}
	$record = GFAPI::get_form( $persisted_form_id );
	if ( ! is_array( $record ) ) {
		throw new RuntimeException( 'PRI-FND-001 persisted form unreadable: ' . $format );
	}
	$tag = '{Visit:2}';
	$prefix = 'PRI-persisted-' . $format . ': ';
	$suffix = '; end-persisted-' . $format . '</p>';
	$record['confirmations'] = array(
		'pri-default' => array(
			'id' => 'pri-default', 'name' => 'Persisted Default', 'type' => 'message',
			'isDefault' => true, 'isActive' => true, 'disableAutoformat' => true,
			'message' => '<p>' . $prefix . $tag . $suffix,
		),
	);
	if ( true !== GFAPI::update_form( $record ) ) {
		throw new RuntimeException( 'PRI-FND-001 persisted form update failed: ' . $format );
	}
	$record = GFAPI::get_form( $persisted_form_id );
	$persisted_format = null;
	foreach ( $record['fields'] as $field ) {
		if ( $field instanceof GF_Field_Date && 2 === (int) $field->id ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- GF API field property.
			$persisted_format = $field->dateFormat;
		}
	}
	$entry_id = GFAPI::add_entry( array(
		'form_id' => $persisted_form_id, 'status' => 'active',
		'date_created' => '2026-03-20 20:30:00',
		'1' => 'synthetic-persisted', '2' => $stored,
	) );
	$entry = is_wp_error( $entry_id ) ? null : GFAPI::get_entry( $entry_id );
	if ( ! is_array( $entry ) || $persisted_format !== $format || $entry['2'] !== $stored ) {
		throw new RuntimeException( 'PRI-FND-001 form/entry persistence mismatch: ' . $format );
	}
	$parse = GFCommon::parse_date( $entry['2'], $persisted_format );
	$proposed = $jalali_ref->invoke( null, $tag, $record, $entry );
	$expected = $expected_ref->invoke( null, $tag, $record, $entry );
	$outputs = array();
	$before = array();
	$counts = array();
	$original_option = get_option( 'pgr_modules' );
	try {
		foreach ( array( 'native' => false, 'enabled' => true ) as $mode => $on ) {
			$states = $original_option;
			$states['states']['jalali_presentation'] = $on;
			update_option( 'pgr_modules', $states );
			$shared_count = 0;
			$incoming = null;
			$shared = static function ( $text ) use ( &$shared_count ) {
				++$shared_count;
				return $text;
			};
			$pre = static function ( $value ) use ( &$incoming ) {
				$incoming = $value;
				return $value;
			};
			add_filter( 'gform_pre_replace_merge_tags', $shared, 998, 1 );
			add_filter( 'gform_confirmation', $pre, 19, 1 );
			try {
				$copy = $record;
				$outputs[ $mode ] = GFFormDisplay::handle_confirmation( $copy, $entry, false );
			} finally {
				remove_filter( 'gform_pre_replace_merge_tags', $shared, 998 );
				remove_filter( 'gform_confirmation', $pre, 19 );
			}
			$before[ $mode ] = $incoming;
			$counts[ $mode ] = $shared_count;
		}
	} finally {
		update_option( 'pgr_modules', $original_option );
	}
	$rendered = $before['enabled'];
	$start_pos = is_string( $rendered ) ? strpos( $rendered, $prefix ) : false;
	$start = false === $start_pos ? null : $start_pos + strlen( $prefix );
	$end = null === $start ? false : strpos( $rendered, $suffix, $start );
	$native_span = null === $start || false === $end ? null : substr( $rendered, $start, $end - $start );
	$reason = null === $proposed ? 'present_tag_null -> no eligible conversion' :
		( null === $expected ? 'expected_native_tag_null -> fallback' :
		( $native_span !== $expected ? 'invalid_span_guard -> native expected mismatch' :
		( $outputs['enabled'] === $outputs['native'] ? 'unexpected_native_fallback' : 'converted' ) ) );
	$pri_evidence['persisted_form_cases'][ $format ] = array(
		'persisted_form_id' => $persisted_form_id,
		'persisted_entry_id' => $entry_id,
		'GF_before_adapter_output_enabled' => $before['enabled'],
		'GFAPI_persisted_field_dateFormat' => $persisted_format,
		'GFAPI_persisted_raw_Entry' => $entry['2'],
		'GFCommon_parse_date' => $parse,
		'adapter_expected_native' => $expected,
		'adapter_proposed_jalali' => $proposed,
		'host_selected_native_date_span' => $native_span,
		'GF_native_output' => $outputs['native'],
		'GF_enabled_output' => $outputs['enabled'],
		'module_off_merge_callback_count' => $counts['native'],
		'module_on_merge_callback_count' => $counts['enabled'],
		'GFAPI_Entry_unchanged' => $entry['2'] === ( GFAPI::get_entry( $entry_id )['2'] ?? null ),
		'fallback_branch_and_reason' => $reason,
		'converted' => $outputs['native'] !== $outputs['enabled'],
	);
}
$path = getenv( 'WU008_ARTIFACT_DIR' ) . '/g011-pri-fnd-001-diagnostic.json';
file_put_contents( $path, wp_json_encode(
	$pri_evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
) );
echo 'PRI-FND-001: exact-host nine-format diagnostic captured without changing production.' . "\n";
