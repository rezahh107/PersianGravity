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

$browser_message = '<strong>G011_BROWSER_OK</strong> {today:format:Y/m/d} | {date_created:format:Y/m/d} | 2026-03-21 unchanged';
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
