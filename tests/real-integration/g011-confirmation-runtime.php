<?php
/**
 * Authentic G-011 confirmation lifecycle qualification on the locked WU008 host.
 * The artifact contains no licensed source, entry personal data, or ZIP bytes.
 */
defined( 'ABSPATH' ) || exit;

$fail = static function ( string $reason ): void {
	throw new RuntimeException( 'G-011: ' . $reason );
};
$dir = getenv( 'WU008_ARTIFACT_DIR' );
if ( ! is_string( $dir ) || ! is_dir( $dir ) ) {
	$fail( 'Missing evidence directory.' );
}
if (
	! class_exists( 'GFForms' ) ||
	'3.1.1.1' !== GFForms::$version ||
	! class_exists( 'PGR_GF_Confirmation_Jalali_Adapter' ) ||
	false === has_filter( 'gform_confirmation', array( 'PGR_GF_Confirmation_Jalali_Adapter', 'filter_confirmation' ) )
) {
	$fail( 'Expected authentic host, adapter and confirmation hook.' );
}

// Exact source assertions: no excerpts, only bounded contract evidence.
$host = WP_PLUGIN_DIR . '/gravityforms/';
$display = file_get_contents( $host . 'form_display.php' );
$common = file_get_contents( $host . 'common.php' );
if (
	! is_string( $display ) ||
	! is_string( $common ) ||
	false === strpos( $display, 'function handle_confirmation(' ) ||
	false === strpos( $display, 'function get_confirmation_message(' ) ||
	false === strpos( $display, 'function get_confirmation_url(' ) ||
	false === strpos( $display, "'gform_confirmation'" ) ||
	false === strpos( $common, "'gform_pre_replace_merge_tags'" )
) {
	$fail( 'Verified GF source no longer proves the expected entrypoints.' );
}
if ( 'fa_IR' !== determine_locale() ) {
	$fail( 'Initial WordPress locale is not Persian.' );
}
update_option( 'timezone_string', 'Asia/Tehran' );
update_option( 'date_format', 'Y/m/d' );
update_option( 'time_format', 'H:i' );

$form_id = GFAPI::add_form(
	array(
		'title' => 'G011 Confirmation Lifecycle',
		'fields' => array(
			array( 'id' => 1, 'type' => 'text', 'label' => 'Name', 'isRequired' => true ),
			array( 'id' => 2, 'type' => 'date', 'label' => 'Visit', 'dateType' => 'datepicker', 'dateFormat' => 'mdy' ),
		),
		'button' => array( 'type' => 'text', 'text' => 'Submit' ),
	)
);
if ( is_wp_error( $form_id ) || ! is_int( $form_id ) ) {
	$fail( 'Could not create real Gravity Forms form.' );
}
$form = GFAPI::get_form( $form_id );
$body = '<strong>G011_SELECTED</strong> Created={date_created:format:Y/m/d}; iso={date_created:format:Y-m-d H\:i}; updated={date_updated:format:Y/m/d}; year={Visit:2:year}; ymd={Visit:2:ymd_dash}; raw={date_created:raw}; payment={payment_date}; relative={date_created:human}; other=2026-03-21 &amp; keep.';
$form['confirmations'] = array(
	'default' => array(
		'id' => 'g011-default', 'name' => 'Default', 'isActive' => true, 'isDefault' => true,
		'type' => 'message', 'message' => $body, 'disableAutoformat' => true,
	),
	'conditional' => array(
		'id' => 'g011-conditional', 'name' => 'Conditional', 'isActive' => true, 'isDefault' => false,
		'type' => 'message', 'message' => '<strong>G011_CONDITIONAL</strong> {date_created:format:Y/m/d}',
		'disableAutoformat' => true,
		'conditionalLogic' => array(
			'actionType' => 'show', 'logicType' => 'all',
			'rules' => array( array( 'fieldId' => 1, 'operator' => 'is', 'value' => 'special' ) ),
		),
	),
);
if ( is_wp_error( GFAPI::update_form( $form ) ) ) {
	$fail( 'Unable to save configured confirmation.' );
}
$form = GFAPI::get_form( $form_id );
$entry_id = GFAPI::add_entry(
	array( 'form_id' => $form_id, 'status' => 'active', 'date_created' => '2026-03-20 20:30:00', '1' => 'ordinary', '2' => '03/21/2026' )
);
if ( is_wp_error( $entry_id ) || ! is_int( $entry_id ) ) {
	$fail( 'Unable to create deterministic Entry.' );
}
$raw = GFAPI::get_entry( $entry_id );
if ( is_wp_error( $raw ) || '2026-03-20 20:30:00' !== ( $raw['date_created'] ?? null ) ) {
	$fail( 'Gregorian/UTC persisted value changed.' );
}
$entry = $raw;
$entry['date_updated'] = '2026-03-22 20:30:00';
$entry['payment_date'] = '';
$expected_native = GFCommon::replace_variables( '{date_created:format:Y/m/d}', $form, $entry, false, true, false, 'html' );
$submitted = $form;
$text = GFFormDisplay::handle_confirmation( $submitted, $entry, false );
$text_checks = array(
	'string' => is_string( $text ),
	'selected' => is_string( $text ) && false !== strpos( $text, 'G011_SELECTED' ),
	'jalali' => is_string( $text ) && false !== strpos( $text, '۱۴۰۵/۰۱/۰۱' ),
	'iso' => is_string( $text ) && false !== strpos( $text, '۱۴۰۵-۰۱-۰۱ ۰۰:۰۰' ),
	'year' => is_string( $text ) && false !== strpos( $text, 'year=۱۴۰۵' ),
	'ymd' => is_string( $text ) && false !== strpos( $text, 'ymd=۱۴۰۵-۰۱-۰۱' ),
	'untouched' => is_string( $text ) && false !== strpos( $text, 'other=2026-03-21' ),
);
$failed_text = array_keys( array_filter( $text_checks, static function ( $value ) { return ! $value; } ) );
if ( $failed_text ) {
	$fail( 'Selected Text contract failed checks: ' . implode( ',', $failed_text ) );
}
$ajax_form = $form;
$ajax = GFFormDisplay::handle_confirmation( $ajax_form, $entry, true );
if ( ! is_string( $ajax ) || false === strpos( $ajax, '۱۴۰۵/۰۱/۰۱' ) ) {
	$fail( 'AJAX Text confirmation not presented.' );
}
$conditional_entry = $entry;
$conditional_entry['1'] = 'special';
$conditional_form = $form;
$conditional_result = GFFormDisplay::handle_confirmation( $conditional_form, $conditional_entry, false );
if (
	! is_string( $conditional_result ) ||
	false === strpos( $conditional_result, 'G011_CONDITIONAL' ) ||
	false !== strpos( $conditional_result, 'G011_SELECTED' ) ||
	false === strpos( $conditional_result, '۱۴۰۵/۰۱/۰۱' )
) {
	$fail( 'Conditional confirmation was not selected/presented exclusively.' );
}
// Independently exercise native time composition and a populated payment date.
$extra_form = $form;
$extra_form['confirmations'] = array(
	'single' => array(
		'id' => 'g011-extra',
		'name' => 'Extra system dates',
		'isDefault' => true,
		'type' => 'message',
		'message' => 'clock={date_created:time:format:Y/m/d}; payment={payment_date:format:Y/m/d}; updated={date_updated:format:Y/m/d}',
		'disableAutoformat' => true,
	),
);
$payment_entry = $entry;
$payment_entry['payment_date'] = '2026-03-22 20:30:00';
$extra_output = GFFormDisplay::handle_confirmation( $extra_form, $payment_entry, false );
if (
	! is_string( $extra_output ) ||
	false === strpos( $extra_output, 'clock=۱۴۰۵/۰۱/۰۱' ) ||
	false === strpos( $extra_output, '۰۰:۰۰' ) && false === strpos( $extra_output, '00:00' ) ||
	false === strpos( $extra_output, 'payment=۱۴۰۵/۰۱/۰۳' ) ||
	false === strpos( $extra_output, 'updated=۱۴۰۵/۰۱/۰۳' )
) {
	$fail( 'Native system time composition or nonempty payment/update date failed.' );
}

$notification_after = GFCommon::replace_variables( '{date_created:format:Y/m/d}', $form, $entry, false, true, false, 'html' );
if ( $expected_native !== $notification_after || false !== strpos( $notification_after, '۱۴۰۵/' ) ) {
	$fail( 'Shared Merge Tag rendering was affected outside confirmation.' );
}

// Leave transport/machine-bearing Page and Redirect query strings native.
$page_id = wp_insert_post( array( 'post_title' => 'G011 Destination', 'post_type' => 'page', 'post_status' => 'publish' ) );
foreach ( array( 'page', 'redirect' ) as $type ) {
	$transport = array(
		'id' => 'g011-transport', 'name' => 'Transport', 'type' => $type,
		'pageId' => $page_id,
		'url' => home_url( '/?existing=x%2By' ),
		'queryString' => 'day={date_created:format:Y-m-d}&raw={date_created:raw}&keep=a%26b',
		'isDefault' => true,
	);
	$transport_form = $form;
	$transport_form['confirmations'] = array( 'transport' => $transport );
	$native_url = GFFormDisplay::get_confirmation_url( $transport, $transport_form, $entry );
	$transport_result = GFFormDisplay::handle_confirmation( $transport_form, $entry, false );
	if ( ! is_array( $transport_result ) || ( $transport_result['redirect'] ?? null ) !== $native_url ) {
		$fail( 'Native Page/Redirect transport changed for ' . $type );
	}
}

// Module-disabled and English controls in the same authentic runtime.
$states = get_option( 'pgr_modules' );
if ( ! is_array( $states ) ) {
	$fail( 'Module state option missing.' );
}
$states['states']['jalali_presentation'] = false;
update_option( 'pgr_modules', $states );
$disabled_form = $form;
$disabled = GFFormDisplay::handle_confirmation( $disabled_form, $entry, false );
$states['states']['jalali_presentation'] = true;
update_option( 'pgr_modules', $states );
if ( ! is_string( $disabled ) || false !== strpos( $disabled, 'year=۱۴۰۵' ) ) {
	$fail( 'Disabled presentation failed native fallback.' );
}
switch_to_locale( 'en_US' );
$english_form = $form;
$english = GFFormDisplay::handle_confirmation( $english_form, $entry, false );
restore_previous_locale();
if ( ! is_string( $english ) || false !== strpos( $english, 'year=۱۴۰۵' ) ) {
	$fail( 'English confirmation was converted.' );
}
if ( GFAPI::get_entry( $entry_id )['date_created'] !== '2026-03-20 20:30:00' ) {
	$fail( 'GFAPI raw date changed after confirmation.' );
}

// Independently falsify global markerization against the authentic GF host.
// Both native attributes must remain byte-identical, while both visible
// occurrences of the same tag must change calendar.
$context_tag = '{date_created:format:Y/m/d}';
$context_message = '<div class="keep"><a href="/?day=' . $context_tag . '&amp;keep=1" data-day="' .
	$context_tag . '"><strong>Context ' . $context_tag . '</strong></a> Again ' . $context_tag .
	' Created=' . $context_tag . ' /archive/' . $context_tag . ' ./archive/' . $context_tag .
	' ../archive/' . $context_tag . ' archive/' . $context_tag .
	' ../../history/' . $context_tag . ' https://example.test/archive/' . $context_tag .
	' ' . $context_tag . '/detail ' . $context_tag . '#details receipt-' . $context_tag . '.pdf</div>';
$context_confirmation = array(
	'id' => 'g011-context', 'name' => 'Context', 'type' => 'message',
	'isDefault' => true, 'isActive' => true, 'message' => $context_message,
	'disableAutoformat' => true,
);
$context_form = $form;
$context_form['confirmations'] = array( 'context' => $context_confirmation );
$context_native = GFFormDisplay::get_confirmation_message( $context_confirmation, $context_form, $entry );
$context_result = GFFormDisplay::handle_confirmation( $context_form, $entry, false );
if (
	! is_string( $context_native ) || ! is_string( $context_result ) ||
	! preg_match( '/href="([^"]+)"/', $context_native, $native_href ) ||
	! preg_match( '/href="([^"]+)"/', $context_result, $actual_href ) ||
	! preg_match( '/data-day="([^"]+)"/', $context_native, $native_day ) ||
	! preg_match( '/data-day="([^"]+)"/', $context_result, $actual_day ) ||
	$native_href[1] !== $actual_href[1] ||
	$native_day[1] !== $actual_day[1] ||
	false === strpos( $context_result, '<strong>Context ۱۴۰۵/۰۱/۰۱</strong>' ) ||
	false === strpos( $context_result, 'Again ۱۴۰۵/۰۱/۰۱' ) ||
	false === strpos( $context_result, 'class="keep"' ) ||
	$context_result === str_replace( $expected_native, '۱۴۰۵/۰۱/۰۱', $context_native )
) {
	$fail( 'Mixed human/attribute context violated native byte identity or falsification.' );
}

// Every bare relative-URI token must remain byte-equivalent to the
// independently rendered native GF output, despite repeated visible tags.
$native_relative_fragments = array(
	' /archive/' . $expected_native . ' ',
	' ./archive/' . $expected_native . ' ',
	' ../archive/' . $expected_native . ' ',
	' archive/' . $expected_native . ' ',
	' ../../history/' . $expected_native . ' ',
	' https://example.test/archive/' . $expected_native . ' ',
	' ' . $expected_native . '/detail ',
	' ' . $expected_native . '#details ',
	' receipt-' . $expected_native . '.pdf',
);
foreach ( $native_relative_fragments as $fragment ) {
	if ( false === strpos( $context_native, $fragment ) || false === strpos( $context_result, $fragment ) ) {
		$fail( 'Relative URI token no longer preserves native date replacement.' );
	}
}
if ( false === strpos( $context_result, ' Created=۱۴۰۵/۰۱/۰۱ ' ) ) {
	$fail( 'Legitimate Created= human label lost Jalali conversion.' );
}

// Prove that the repaired Adapter adds ZERO shared Merge Tag callback calls.
// The spy intentionally has a counting side effect but returns input unchanged.
$probe_form = $form;
$probe_form['confirmations'] = array(
	'probe' => array(
		'id' => 'g011-callback-probe', 'name' => 'Callback probe',
		'type' => 'message', 'isDefault' => true, 'isActive' => true,
		'message' => 'PGR_CALLBACK_PROBE Created={date_created:format:Y/m/d}',
		'disableAutoformat' => true,
	),
);
$probe_states = get_option( 'pgr_modules' );
if ( ! is_array( $probe_states ) || ! isset( $probe_states['states'] ) ) {
	$fail( 'Callback probe requires module state.' );
}
$probe_calls = 0;
$spy = static function ( $text ) use ( &$probe_calls ) {
	++$probe_calls;
	return $text;
};
add_filter( 'gform_pre_replace_merge_tags', $spy, 999, 1 );
$probe_states['states']['jalali_presentation'] = false;
update_option( 'pgr_modules', $probe_states );
$probe_copy = $probe_form;
$native_probe_output = GFFormDisplay::handle_confirmation( $probe_copy, $entry, false );
$native_probe_calls = $probe_calls;
$probe_calls = 0;
$probe_states['states']['jalali_presentation'] = true;
update_option( 'pgr_modules', $probe_states );
$probe_copy = $probe_form;
$jalali_probe_output = GFFormDisplay::handle_confirmation( $probe_copy, $entry, false );
$jalali_probe_calls = $probe_calls;
remove_filter( 'gform_pre_replace_merge_tags', $spy, 999 );
if (
	! is_string( $native_probe_output ) ||
	! is_string( $jalali_probe_output ) ||
	$native_probe_calls < 1 ||
	$jalali_probe_calls !== $native_probe_calls ||
	false === strpos( $jalali_probe_output, '۱۴۰۵/۰۱/۰۱' )
) {
	$fail( 'Repaired Adapter replayed shared callback or lost positive Jalali output.' );
}

// A non-idempotent third-party callback mutates the message once.
// Its effects must be identical in native/enabled modes; since the changed
// prefix invalidates source-to-output alignment, the entire received native
// value must survive unchanged, with no extra Merge Tag replacement.
$side_effect_calls = 0;
$mutator = static function ( $text ) use ( &$side_effect_calls ) {
	++$side_effect_calls;
	return str_replace( 'PGR_CALLBACK_PROBE', 'PGR_CALLBACK_PROBE_' . $side_effect_calls, $text );
};
add_filter( 'gform_pre_replace_merge_tags', $mutator, 999, 1 );
$probe_states['states']['jalali_presentation'] = false;
update_option( 'pgr_modules', $probe_states );
$probe_copy = $probe_form;
$native_side_effect_output = GFFormDisplay::handle_confirmation( $probe_copy, $entry, false );
$native_side_effect_calls = $side_effect_calls;
$side_effect_calls = 0;
$probe_states['states']['jalali_presentation'] = true;
update_option( 'pgr_modules', $probe_states );
$probe_copy = $probe_form;
$jalali_side_effect_output = GFFormDisplay::handle_confirmation( $probe_copy, $entry, false );
$jalali_side_effect_calls = $side_effect_calls;
remove_filter( 'gform_pre_replace_merge_tags', $mutator, 999 );
if (
	! is_string( $native_side_effect_output ) ||
	! is_string( $jalali_side_effect_output ) ||
	$native_side_effect_output !== $jalali_side_effect_output ||
	$native_side_effect_calls < 1 ||
	$jalali_side_effect_calls !== $native_side_effect_calls
) {
	$fail( 'Non-idempotent callback changed source anchor; native fallback or call-count parity failed.' );
}

$callback_observation = array(
	'count_only_native' => $native_probe_calls,
	'count_only_jalali' => $jalali_probe_calls,
	'non_idempotent_native' => $native_side_effect_calls,
	'non_idempotent_jalali' => $jalali_side_effect_calls,
	'non_idempotent_output_native' => true,
);

// Owner-approved G011 Revision 1.1 positive-preservation discriminators.
// Use genuine configured selected Text confirmations on the licensed host.
// No GF replacement happens inside the production Adapter; these matched
// native-only and enabled executions are independent test controls.
$matrix_entry = $entry;
$matrix_entry['date_updated'] = '2026-03-22 20:30:00';
$matrix_entry['payment_date'] = '2026-03-22 20:30:00';
$matrix_positive = array(
	'created_default' => array( '{date_created}', '۱۴۰۵/۰۱/۰۱' ),
	'created_format' => array( '{date_created:format:Y/m/d}', '۱۴۰۵/۰۱/۰۱' ),
	'created_escaped_colon' => array( '{date_created:format:Y-m-d H\:i}', '۱۴۰۵-۰۱-۰۱ ۰۰:۰۰' ),
	'created_escaped_year' => array( '{date_created:format:\Y Y}', 'Y ۱۴۰۵' ),
	'created_time' => array( '{date_created:time:format:Y/m/d}', '۱۴۰۵/۰۱/۰۱' ),
	'updated' => array( '{date_updated:format:Y/m/d}', '۱۴۰۵/۰۱/۰۳' ),
	'payment' => array( '{payment_date:format:Y/m/d}', '۱۴۰۵/۰۱/۰۳' ),
	'format_calendar_tokens' => array( '{date_created:format:Y|y|m|n|d|j|F}', '۱۴۰۵|۰۵|۰۱|۱|۰۱|۱|فروردین' ),
	'field_year' => array( '{Visit:2:year}', '۱۴۰۵' ),
	'field_month' => array( '{Visit:2:month}', '۰۱' ),
	'field_day' => array( '{Visit:2:day}', '۰۱' ),
	'field_default' => array( '{Visit:2}', '۰۱/۰۱/۱۴۰۵' ),
	'field_ymd' => array( '{Visit:2:ymd}', '۱۴۰۵/۰۱/۰۱' ),
	'field_mdy' => array( '{Visit:2:mdy}', '۰۱/۰۱/۱۴۰۵' ),
	'field_dmy' => array( '{Visit:2:dmy}', '۰۱/۰۱/۱۴۰۵' ),
	'field_ymd_dash' => array( '{Visit:2:ymd_dash}', '۱۴۰۵-۰۱-۰۱' ),
	'field_ymd_dot' => array( '{Visit:2:ymd_dot}', '۱۴۰۵.۰۱.۰۱' ),
	'field_ymd_slash' => array( '{Visit:2:ymd_slash}', '۱۴۰۵/۰۱/۰۱' ),
	'field_mdy_dash' => array( '{Visit:2:mdy_dash}', '۰۱-۰۱-۱۴۰۵' ),
	'field_mdy_dot' => array( '{Visit:2:mdy_dot}', '۰۱.۰۱.۱۴۰۵' ),
	'field_mdy_slash' => array( '{Visit:2:mdy_slash}', '۰۱/۰۱/۱۴۰۵' ),
	'field_dmy_dash' => array( '{Visit:2:dmy_dash}', '۰۱-۰۱-۱۴۰۵' ),
	'field_dmy_dot' => array( '{Visit:2:dmy_dot}', '۰۱.۰۱.۱۴۰۵' ),
	'field_dmy_slash' => array( '{Visit:2:dmy_slash}', '۰۱/۰۱/۱۴۰۵' ),
);
$matrix_native = array(
	'raw_system' => '{date_created:raw}',
	'timestamp_system' => '{date_created:timestamp}',
	'human_relative' => '{date_created:human}',
	'diff_relative' => '{date_created:diff}',
	'raw_entry' => '{entry:date_created}',
	'unsupported_week' => '{date_created:format:W}',
	'date_field_raw' => '{Visit:2:raw}',
	'date_field_urlencode' => '{Visit:2:urlencode}',
);
$matrix_record = array();
$run_matrix = static function ( string $name, string $token, array $given_entry, bool $on ) use ( $form ): string {
	$states = get_option( 'pgr_modules' );
	$states['states']['jalali_presentation'] = $on;
	update_option( 'pgr_modules', $states );
	$selection = $form;
	$selection['confirmations'] = array(
		'matrix-default' => array(
			'id' => 'matrix-default',
			'name' => 'Matrix Default',
			'type' => 'message',
			'isDefault' => true,
			'isActive' => true,
			'disableAutoformat' => true,
			'message' => '<p>G011-' . $name . '=' . $token . '; distinct-end-' . $name . '</p>',
		),
	);
	$html = GFFormDisplay::handle_confirmation( $selection, $given_entry, false );
	if ( ! is_string( $html ) ) {
		throw new RuntimeException( 'G-011 matrix non-string rendered: ' . $name );
	}
	return $html;
};
$matrix_original_state = get_option( 'pgr_modules' );
try {
	foreach ( $matrix_positive as $case => $config ) {
		list( $token, $expected ) = $config;
		$native_out = $run_matrix( $case, $token, $matrix_entry, false );
		$jalali_out = $run_matrix( $case, $token, $matrix_entry, true );
		$native_tag_still_present = false !== strpos( $native_out, $token );
		$good = ! $native_tag_still_present &&
			false !== strpos( $jalali_out, $expected ) &&
			$jalali_out !== $native_out &&
			false !== strpos( $jalali_out, 'distinct-end-' . $case . '</p>' );
		$matrix_record[ $case ] = array(
			'status' => $good ? 'PASS' : 'FAIL',
			'native_sha256' => hash( 'sha256', $native_out ),
			'jalali_sha256' => hash( 'sha256', $jalali_out ),
		);
		if ( ! $good ) {
			$fail( 'Matrix positive not proven: ' . $case .
				'; tag_native=' . ( $native_tag_still_present ? 'yes' : 'no' ) .
				'; converted=' . ( $jalali_out === $native_out ? 'no' : 'yes' ) .
				'; expected_present=' . ( false !== strpos( $jalali_out, $expected ) ? 'yes' : 'no' ) );
		}
	}
	foreach ( $matrix_native as $case => $token ) {
		$native_out = $run_matrix( $case, $token, $matrix_entry, false );
		$jalali_out = $run_matrix( $case, $token, $matrix_entry, true );
		$good = $jalali_out === $native_out;
		$matrix_record[ $case ] = array( 'status' => $good ? 'NATIVE_PASS' : 'FAIL' );
		if ( ! $good ) {
			$fail( 'Matrix native exclusion modified: ' . $case );
		}
	}
} finally {
	update_option( 'pgr_modules', $matrix_original_state );
}
file_put_contents(
	$dir . '/g011-production-preservation.json',
	wp_json_encode(
		array(
			'head' => getenv( 'WU008_PGR_SHA' ),
			'gf' => GFForms::$version,
			'gf_sha256' => getenv( 'WU008_GF_SHA256' ),
			'positive_cases' => count( $matrix_positive ),
			'native_cases' => count( $matrix_native ),
			'case_status' => $matrix_record,
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
	)
);

// Browser-facing configured forms (ordinary and AJAX, real HTTP submission).
// GFAPI persists confirmations using each confirmation's ID as the array key.
require_once __DIR__ . '/g011-default-confirmation.php';

$browser_form = GFAPI::get_form( $form_id );
if ( ! is_array( $browser_form ) ) {
	$fail( 'Browser fixture could not re-read its GFAPI form.' );
}
$selected_default = pgr_g011_select_default_text_confirmation( $browser_form );
$default_key      = $selected_default['key'];

// Reproduce the historical defect on the actual host: the original hard-coded
// key is absent after GFAPI has persisted and rekeyed the confirmations.
if ( array_key_exists( 'default', $browser_form['confirmations'] ) ) {
	$fail( 'Original hard-coded default-key regression is not reproduced on this host.' );
}

$browser_message = '<strong>G011_BROWSER_OK</strong> {today:format:Y/m/d} | {date_created:format:Y/m/d} | 2026-03-21 unchanged <a id="g011-browser-link" href="/?day={date_created:format:Y/m/d}&amp;keep=1" data-created="{date_created:format:Y/m/d}" data-preserve="fixed">Visible {date_created:format:Y/m/d}</a> <span id="g011-relative-paths">Created={date_created:format:Y/m/d} /archive/{date_created:format:Y/m/d} ./archive/{date_created:format:Y/m/d} ../archive/{date_created:format:Y/m/d} archive/{date_created:format:Y/m/d}</span> <span id="g011-tagled">{date_created:format:Y/m/d}/detail {date_created:format:Y/m/d}#details receipt-{date_created:format:Y/m/d}.pdf</span>';
$expected_confirmations = $browser_form['confirmations'];
$expected_confirmations[ $default_key ]['message'] = $browser_message;
$browser_form['confirmations'][ $default_key ]['message'] = $browser_message;

if ( true !== GFAPI::update_form( $browser_form ) ) {
	$fail( 'GFAPI rejected the browser confirmation message update.' );
}

// The persisted, re-read GFAPI state is the acceptance authority, not the
// in-memory fixture or an assumed array key.
$browser_persisted = GFAPI::get_form( $form_id );
if ( ! is_array( $browser_persisted ) ) {
	$fail( 'Browser confirmation could not be re-read after update.' );
}
$persisted_default = pgr_g011_select_default_text_confirmation( $browser_persisted );
if (
	$default_key !== $persisted_default['key'] ||
	$selected_default['confirmation']['id'] !== $persisted_default['confirmation']['id'] ||
	$browser_message !== $persisted_default['confirmation']['message'] ||
	$expected_confirmations !== $browser_persisted['confirmations']
) {
	$fail( 'GFAPI did not persist the selected default message without altering native confirmation metadata.' );
}

$urls = array();
foreach ( array( 'ordinary' => 'false', 'ajax' => 'true' ) as $key => $mode ) {
	$page = wp_insert_post(
		array(
			'post_title' => 'G011 ' . $key,
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_content' => sprintf( '[gravityform id="%d" title="false" description="false" ajax="%s"]', $form_id, $mode ),
		)
	);
	$urls[ $key ] = get_permalink( $page );
}
file_put_contents(
	$dir . '/g011-confirmation.json',
	wp_json_encode(
		array(
			'contract' => 'G011_TEXT_SELECTED_POST_REPLACEMENT',
			'host_version' => GFForms::$version,
			'package_sha256' => getenv( 'WU008_GF_SHA256' ),
			'head' => getenv( 'WU008_PGR_SHA' ),
			'text_selected' => true,
			'contextual_native_attributes' => true,
			'contextual_native_relative_paths' => true,
			'callback_reexecution_probe' => $callback_observation,
			'tag_led_native_paths' => true,
			'text_ajax_handler' => true,
			'conditional_selected' => true,
			'page_redirect_native' => true,
			'negative_notification' => true,
			'module_disabled_native' => true,
			'english_native' => true,
			'raw_gregorian' => true,
			'form_id' => $form_id,
			'urls' => $urls,
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
	)
);
echo "G011 exact-host fixture OK\n";
