<?php
/**
 * Native Gravity Forms confirmation baseline, independent of G-011 adapters.
 * Reuses the existing WU008 disposable licensed WordPress installation.
 *
 * @package PersianGravityForms
 */
defined( 'ABSPATH' ) || exit;

$artifact_dir = getenv( 'WU008_ARTIFACT_DIR' );
if ( ! is_string( $artifact_dir ) || ! is_dir( $artifact_dir ) ||
	! class_exists( 'GFAPI' ) || ! class_exists( 'GF_Field_Date' ) ||
	'3.1.1.1' !== GFForms::$version
) {
	throw new RuntimeException( 'Native GF confirmation baseline requires the locked WU008 host.' );
}

// The host, not a transient field clone, owns this dateFormat definition.
$formats = array(
	'mdy'       => '03/21/2026',
	'mdy_dash'  => '03-21-2026',
	'mdy_dot'   => '03.21.2026',
	'dmy'       => '21/03/2026',
	'dmy_dash'  => '21-03-2026',
	'dmy_dot'   => '21.03.2026',
	'ymd_slash' => '2026/03/21',
	'ymd_dash'  => '2026-03-21',
	'ymd_dot'   => '2026.03.21',
);
$evidence = array(
	'contract'    => 'WU008_NATIVE_GF_CONFIRMATION_BASELINE_V1',
	'head'        => getenv( 'WU008_PGR_SHA' ),
	'gf_version'  => GFForms::$version,
	'gf_sha256'   => getenv( 'WU008_GF_SHA256' ),
	'level'       => 'LICENSED_GFAPI_PHP_ONLY',
	'adapter_used' => false,
	'cases'       => array(),
);
$render_with_spy = static function ( array $form, array $entry, bool $mutating ): array {
	$count = 0;
	$spy   = static function ( $source ) use ( &$count, $mutating ) {
		++$count;
		return $mutating ? str_replace( 'WU008_MARKER', 'WU008_MARKER_' . $count, $source ) : $source;
	};
	add_filter( 'gform_pre_replace_merge_tags', $spy, 998, 1 );
	try {
		$copy = $form;
		$html = GFFormDisplay::handle_confirmation( $copy, $entry, false );
	} finally {
		remove_filter( 'gform_pre_replace_merge_tags', $spy, 998 );
	}
	return array( 'html' => $html, 'callbacks' => $count );
};

foreach ( $formats as $format => $raw_date ) {
	$form_id = GFAPI::add_form(
		array(
			'title' => 'WU008 Native GF ' . $format,
			'fields' => array(
				array( 'id' => 1, 'type' => 'text', 'label' => 'Name' ),
				array( 'id' => 2, 'type' => 'date', 'label' => 'Visit',
					'dateType' => 'datepicker', 'dateFormat' => $format ),
			),
		)
	);
	if ( ! is_int( $form_id ) || $form_id <= 0 ) {
		throw new RuntimeException( 'GFAPI add_form failed: ' . $format );
	}
	$form = GFAPI::get_form( $form_id );
	if ( ! is_array( $form ) ) {
		throw new RuntimeException( 'GFAPI get_form failed: ' . $format );
	}
	$message = '<p>WU008_MARKER Native: {Visit:2}; END_' . $format . '</p>';
	$form['confirmations'] = array(
		'native-baseline' => array(
			'id' => 'native-baseline',
			'name' => 'Native baseline',
			'type' => 'message',
			'isDefault' => true,
			'isActive' => true,
			'disableAutoformat' => true,
			'message' => $message,
		),
	);
	if ( true !== GFAPI::update_form( $form ) ) {
		throw new RuntimeException( 'GFAPI update_form failed: ' . $format );
	}
	$form = GFAPI::get_form( $form_id );
	$field = null;
	foreach ( (array) ( $form['fields'] ?? array() ) as $candidate ) {
		if ( $candidate instanceof GF_Field_Date && 2 === (int) $candidate->id ) {
			$field = $candidate;
		}
	}
	// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Gravity Forms API property.
	if ( null === $field || $field->dateFormat !== $format ) {
		throw new RuntimeException( 'Persisted Date field format was not recovered: ' . $format );
	}
	$entry_id = GFAPI::add_entry(
		array(
			'form_id' => $form_id,
			'status' => 'active',
			'date_created' => '2026-03-20 20:30:00',
			'1' => 'synthetic',
			'2' => $raw_date,
		)
	);
	if ( ! is_int( $entry_id ) || $entry_id <= 0 ) {
		throw new RuntimeException( 'GFAPI add_entry failed: ' . $format );
	}
	$entry = GFAPI::get_entry( $entry_id );
	if ( ! is_array( $entry ) || ( $entry['2'] ?? null ) !== $raw_date ) {
		throw new RuntimeException( 'GFAPI changed the Date input: ' . $format );
	}
	$components = GFCommon::parse_date( $entry['2'], $format );
	if ( ! is_array( $components ) ||
		'2026' !== (string) ( $components['year'] ?? '' ) ||
		'03' !== (string) ( $components['month'] ?? '' ) ||
		'21' !== (string) ( $components['day'] ?? '' )
	) {
		throw new RuntimeException( 'Native Date parsing failed: ' . $format );
	}

	// Both executions use the *recovered* GFAPI Form/Entry and native
	// GFFormDisplay; one callback is read-only, one visibly side-effecting.
	$plain = $render_with_spy( $form, $entry, false );
	$mutated = $render_with_spy( $form, $entry, true );
	$expected_plain = '<p>WU008_MARKER Native: ' . $raw_date . '; END_' . $format . '</p>';
	$expected_mutated = '<p>WU008_MARKER_1 Native: ' . $raw_date . '; END_' . $format . '</p>';
	$stored_entry = GFAPI::get_entry( $entry_id );
	$stored_form = GFAPI::get_form( $form_id );
	$unchanged = is_array( $stored_entry ) && $stored_entry === $entry &&
		is_array( $stored_form ) &&
		$stored_form['fields'] === $form['fields'] &&
		$stored_form['confirmations'] === $form['confirmations'];
	// GFFormDisplay wraps the selected message in a native form-specific
	// confirmation container. Compare the *entire* native output for callback
	// side effects while asserting the exact Date span inside that wrapper.
	$good = is_string( $plain['html'] ) &&
		1 === substr_count( $plain['html'], $expected_plain ) &&
		is_string( $mutated['html'] ) &&
		1 === substr_count( $mutated['html'], $expected_mutated ) &&
		$mutated['html'] === str_replace( 'WU008_MARKER', 'WU008_MARKER_1', $plain['html'] ) &&
		1 === $plain['callbacks'] &&
		1 === $mutated['callbacks'] &&
		$unchanged;
	$evidence['cases'][ $format ] = array(
		'status'           => $good ? 'PASS' : 'FAIL',
		'date_format'      => $format,
		'stored_raw_value' => $entry['2'],
		'native_sha256'    => is_string( $plain['html'] ) ? hash( 'sha256', $plain['html'] ) : null,
		'mutated_sha256'   => is_string( $mutated['html'] ) ? hash( 'sha256', $mutated['html'] ) : null,
		'callback_count'   => array( 'read_only' => $plain['callbacks'], 'non_idempotent' => $mutated['callbacks'] ),
		'form_entry_unchanged' => $unchanged,
	);
	if ( ! $good ) {
		throw new RuntimeException( 'Native GF date/callback baseline failed: ' . $format );
	}
}
$evidence['result'] = 'PASS';
$output = $artifact_dir . '/gf-native-confirmation-baseline.json';
if ( false === file_put_contents( $output, wp_json_encode( $evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ) {
	throw new RuntimeException( 'Cannot persist native GF baseline evidence.' );
}
echo "WU008 native GF confirmation baseline: 9/9 persisted formats + callback parity\n";
