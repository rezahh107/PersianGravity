<?php
/**
 * Bounded G-011 production-path integration on the exact licensed WU008 host.
 *
 * Synthetic data only. Produces a browser fixture and bounded PHP evidence.
 */
defined( 'ABSPATH' ) || exit;
if ( '3.1.1.1' !== GFForms::$version || 'fa_IR' !== get_locale() ) {
	throw new RuntimeException( 'G011 requires locked Gravity Forms and fa_IR.' );
}
$dir = getenv( 'WU008_ARTIFACT_DIR' );
if ( ! is_dir( $dir ) ) {
	throw new RuntimeException( 'G011 artifact directory missing.' );
}
update_option( 'timezone_string', 'Asia/Tehran' );
PGR_Module_Registry::set_enabled( 'jalali_presentation', true );
require_once dirname( __DIR__, 2 ) . '/includes/class-pgr-gregorian-jalali-converter.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-pgr-jalali-presentation.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-pgr-gf-explicit-jalali-merge-tags.php';
// Module was enabled after WP bootstrap in this CLI request only.
( new PGR_GF_Explicit_Jalali_Merge_Tags() )->hooks();

$form_id = GFAPI::add_form( array(
	'title' => 'WU008 G011 explicit production-path fixture',
	'fields' => array(
		array( 'id' => 1, 'type' => 'text', 'label' => 'Name' ),
		array( 'id' => 2, 'type' => 'date', 'label' => 'Visit', 'dateType' => 'datepicker', 'dateFormat' => 'mdy' ),
		array( 'id' => 3, 'type' => 'pgr_jalali_date', 'label' => 'Already Jalali' ),
	),
) );
if ( ! is_int( $form_id ) || $form_id < 1 ) {
	throw new RuntimeException( 'G011 form create failed.' );
}
$form = GFAPI::get_form( $form_id );
$common = 'Created={pgr_jdate_created} | CreatedDate={pgr_jdate_created_date} | ' .
	'CreatedTime={pgr_jdate_created_time} | Updated={pgr_jdate_updated} | ' .
	'Payment={pgr_jdate_payment} | Today={pgr_jdate_today} | Visit={pgr_jdate_field_2} | ' .
	'Name={Name:1} | Native={Visit:2} | Raw={entry:date_created} | ' .
	'Jalali={Already Jalali:3} | Wrong={pgr_jdate_field_1} | ' .
	'Unknown={pgr_jdate_field_99} | Untouched=keep &amp; safe';
$form['confirmations'] = array(
	'default' => array(
		'id' => 'default', 'name' => 'Default', 'type' => 'message', 'isDefault' => true,
		'isActive' => true, 'disableAutoformat' => true, 'message' => '<p>G011_DEFAULT ' . $common . '</p>',
	),
	'conditional' => array(
		'id' => 'conditional', 'name' => 'Special', 'type' => 'message', 'isDefault' => false,
		'isActive' => true, 'disableAutoformat' => true, 'message' => '<p>G011_CONDITIONAL ' . $common . '</p>',
		'conditionalLogic' => array( 'actionType' => 'show', 'logicType' => 'all',
			'rules' => array( array( 'fieldId' => 1, 'operator' => 'is', 'value' => 'special' ) ) ),
	),
);
$form['notifications'] = array(); // Public browser submissions must not send mail.
if ( true !== GFAPI::update_form( $form ) ) {
	throw new RuntimeException( 'G011 form update failed.' );
}
$form = GFAPI::get_form( $form_id );
$entry_id = GFAPI::add_entry( array(
	'form_id' => $form_id, 'status' => 'active', 'date_created' => '2026-03-20 21:30:00',
	'date_updated' => '2026-03-20 21:30:00', 'payment_date' => '2026-03-20 21:30:00',
	'1' => 'ordinary', '2' => '03/20/2026', '3' => '1404-12-29',
) );
$entry = GFAPI::get_entry( $entry_id );
if ( ! is_array( $entry ) || '03/20/2026' !== $entry['2'] ) {
	throw new RuntimeException( 'G011 persisted source missing.' );
}
$stored_form = wp_json_encode( GFAPI::get_form( $form_id ) );
$before = GFAPI::get_entry( $entry_id );
$native = 0;
$spy = static function ( $text ) use ( &$native ) {
	if ( is_string( $text ) && false !== strpos( $text, 'G011_' ) ) {
		++$native;
	}
	return $text;
};
add_filter( 'gform_pre_replace_merge_tags', $spy, 998, 1 );
try {
	$form_copy = $form;
	$html = GFFormDisplay::handle_confirmation( $form_copy, $entry, false );
} finally {
	remove_filter( 'gform_pre_replace_merge_tags', $spy, 998 );
}
$expected_instant = '۱۴۰۵/۰۱/۰۱، ۰۱:۰۰';
if (
	1 !== $native || ! is_string( $html ) ||
	false === strpos( $html, 'Created=' . $expected_instant ) ||
	false === strpos( $html, 'Updated=' . $expected_instant ) ||
	false === strpos( $html, 'CreatedDate=۱۴۰۵/۰۱/۰۱' ) ||
	false === strpos( $html, 'CreatedTime=۰۱:۰۰' ) ||
	false === strpos( $html, 'Visit=۱۴۰۴/۱۲/۲۹' ) ||
	false === strpos( $html, 'Native=03/20/2026' ) ||
	false === strpos( $html, 'Wrong={pgr_jdate_field_1}' ) ||
	false === strpos( $html, 'Unknown={pgr_jdate_field_99}' )
) {
	throw new RuntimeException( 'G011 selected native confirmation mismatch.' );
}
$payment_raw = $entry['payment_date'] ?? null;
if ( is_string( $payment_raw ) && '2026-03-20 21:30:00' === $payment_raw &&
	false === strpos( $html, 'Payment=' . $expected_instant ) ) {
	throw new RuntimeException( 'G011 persisted payment date mismatch.' );
}

$notification = array(
	'id' => 'g011-test', 'name' => 'G011 no-send', 'event' => 'form_submission',
	'toType' => 'email', 'to' => 'test@example.com', 'from' => 'test@example.com',
	'fromName' => 'Fixture', 'subject' => 'G011', 'message' => $common, 'isActive' => true,
);
$sent = array();
$abort = static function ( $email ) use ( &$sent ) {
	$sent[] = $email['message'] ?? '';
	$email['abort_email'] = true;
	return $email;
};
$block = static function () { return true; };
$sync = static function () { return false; };
add_filter( 'gform_pre_send_email', $abort, 1, 1 );
add_filter( 'pre_wp_mail', $block, 1, 1 );
add_filter( 'gform_is_asynchronous_notifications_enabled', $sync, 999, 1 );
try {
	GFAPI::send_notification( $notification, $form, $entry );
} finally {
	remove_filter( 'gform_pre_send_email', $abort, 1 );
	remove_filter( 'pre_wp_mail', $block, 1 );
	remove_filter( 'gform_is_asynchronous_notifications_enabled', $sync, 999 );
}
if ( 1 !== count( $sent ) || false === strpos( $sent[0], 'Visit=۱۴۰۴/۱۲/۲۹' ) ) {
	throw new RuntimeException( 'G011 native Notification replacement mismatch.' );
}
$missing = $entry;
$missing['payment_date'] = null;
$missing_text = GFCommon::replace_variables( 'Empty=[{pgr_jdate_payment}]', $form, $missing );
if ( 'Empty=[]' !== $missing_text ) {
	throw new RuntimeException( 'G011 missing payment behavior mismatch.' );
}
$invalid = $entry;
$invalid['date_updated'] = '2026-02-30 12:00:00';
$invalid_text = GFCommon::replace_variables(
	'Invalid={pgr_jdate_updated} Outside={pgr_jdate_field_2}', $form,
	array_merge( $invalid, array( '2' => '03/20/2220' ) )
);
if ( false === strpos( $invalid_text, 'Invalid={pgr_jdate_updated}' ) ||
	false === strpos( $invalid_text, 'Outside={pgr_jdate_field_2}' ) ) {
	throw new RuntimeException( 'G011 invalid/out-of-range fail-closed mismatch.' );
}
if ( $before !== GFAPI::get_entry( $entry_id ) || $stored_form !== wp_json_encode( GFAPI::get_form( $form_id ) ) ) {
	throw new RuntimeException( 'G011 render mutated stored entry/form.' );
}
$usage = PGR_Module_Usage::inspect( 'jalali_presentation' );
if ( PGR_Module_Usage::USED !== $usage['status'] ) {
	throw new RuntimeException( 'G011 form metadata failed disable usage guard.' );
}

// Qualification only: verify host-owned Query String composition without
// admitting destination-page rendering or rewriting any transport handlers.
$qualified_redirect = GFFormDisplay::get_confirmation_url(
	array(
		'type' => 'redirect', 'url' => 'https://example.invalid/thanks?fixed=one%26two',
		'queryString' => 'j={pgr_jdate_created_date}&name={Name:1}&repeat=first&repeat=second',
	),
	$form,
	$entry
);
if (
	false === strpos( $qualified_redirect, 'j=' . rawurlencode( '۱۴۰۵/۰۱/۰۱' ) ) ||
	false === strpos( $qualified_redirect, 'fixed=one%26two' ) ||
	false === strpos( $qualified_redirect, 'name=Ada' ) ||
	false === strpos( $qualified_redirect, 'repeat=first&repeat=second' )
) {
	throw new RuntimeException( 'G011 GF Query String transport composition mismatch.' );
}

$pages = array();
foreach ( array( 'ordinary' => 'false', 'ajax' => 'true' ) as $mode => $ajax ) {
	$page_id = wp_insert_post( array(
		'post_title' => 'G011 synthetic ' . $mode, 'post_status' => 'publish',
		'post_type' => 'page',
		'post_content' => '[gravityform id="' . $form_id . '" title="false" description="false" ajax="' . $ajax . '"]',
	) );
	if ( is_wp_error( $page_id ) || ! $page_id ) {
		throw new RuntimeException( 'G011 page create failed.' );
	}
	$pages[ $mode ] = get_permalink( $page_id );
}
$evidence = array(
	'head' => getenv( 'WU008_PGR_SHA' ), 'gf_version' => GFForms::$version,
	'gf_sha256' => getenv( 'WU008_GF_SHA256' ), 'result' => 'PASS',
	'level' => 'LICENSED_GFAPI_PHP', 'form_id' => $form_id, 'entry_id' => $entry_id,
	'pages' => $pages, 'native_confirmation_callback_count' => $native,
	'notification_attempts_aborted' => count( $sent ),
	'stored_data_unchanged' => true, 'disable_usage' => $usage,
	'created_expected' => $expected_instant, 'visit_expected' => '۱۴۰۴/۱۲/۲۹',
	'payment_persisted' => $payment_raw,
	'gf_redirect_query_string_php' => 'PASS',
);
file_put_contents( $dir . '/g011-explicit-production-runtime.json', wp_json_encode( $evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
echo "G011 production-path PHP PASS\n";
