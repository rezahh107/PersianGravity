<?php
/**
 * Independent token-by-token G-011 qualification against installed Gravity Forms.
 * This file is test-only and writes no Form/Entry data.
 */
defined( 'ABSPATH' ) || exit;
if ( '3.1.1.1' !== GFForms::$version || 'fa_IR' !== get_locale() ||
	! PGR_Module_Registry::is_enabled( 'jalali_presentation' ) ) {
	throw new RuntimeException( 'G011 matrix requires enabled fa_IR / exact licensed Gravity Forms.' );
}
$dir     = getenv( 'WU008_ARTIFACT_DIR' );
$fixture = json_decode( (string) file_get_contents( $dir . '/g011-explicit-production-runtime.json' ), true );
$form    = GFAPI::get_form( (int) $fixture['form_id'] );
$entry   = GFAPI::get_entry( (int) $fixture['entry_id'] );
if ( ! is_array( $form ) || ! is_array( $entry ) ) {
	throw new RuntimeException( 'G011 matrix persisted fixture unavailable.' );
}
$before_form  = wp_json_encode( $form );
$before_entry = wp_json_encode( $entry );
$datetime     = '۱۴۰۵/۰۱/۰۱، ۰۱:۰۰';
$date         = '۱۴۰۵/۰۱/۰۱';
$time         = '۰۱:۰۰';
$cases        = array(
	'{pgr_jdate_created}'      => $datetime,
	'{pgr_jdate_created_date}' => $date,
	'{pgr_jdate_created_time}' => $time,
	'{pgr_jdate_updated}'      => $datetime,
	'{pgr_jdate_updated_date}' => $date,
	'{pgr_jdate_updated_time}' => $time,
	'{pgr_jdate_payment}'      => $datetime,
	'{pgr_jdate_payment_date}' => $date,
	'{pgr_jdate_payment_time}' => $time,
	'{pgr_jdate_field_2}'      => '۱۴۰۴/۱۲/۲۹',
);
$results = array();
foreach ( $cases as $token => $expected ) {
	$actual = GFCommon::replace_variables( $token, $form, $entry );
	if ( $actual !== $expected ) {
		throw new RuntimeException( 'G011 exact tag failed: ' . $token . ', actual=' . $actual );
	}
	$results[] = array( 'tag' => $token, 'expected' => $expected, 'actual' => $actual, 'status' => 'PASS' );
}
// Today must be checked against WordPress' local civil date at the time of
// each isolated render, not an unbounded previously captured clock value.
$digits = array( '0'=>'۰', '1'=>'۱', '2'=>'۲', '3'=>'۳', '4'=>'۴',
	'5'=>'۵', '6'=>'۶', '7'=>'۷', '8'=>'۸', '9'=>'۹' );
$today_oracle = static function () use ( $digits ) {
	$local   = new DateTimeImmutable( 'now', wp_timezone() );
	$jalali  = PGR_Gregorian_Jalali_Converter::to_jalali(
		(int) $local->format( 'Y' ), (int) $local->format( 'n' ), (int) $local->format( 'j' )
	);
	if ( ! is_array( $jalali ) ) {
		throw new RuntimeException( 'G011 today oracle source unsupported.' );
	}
	$date = strtr( sprintf( '%04d/%02d/%02d', $jalali['year'], $jalali['month'], $jalali['day'] ), $digits );
	$time = strtr( $local->format( 'H:i' ), $digits );
	return array(
		'{pgr_jdate_today}' => $date . '، ' . $time,
		'{pgr_jdate_today_date}' => $date,
		'{pgr_jdate_today_time}' => $time,
	);
};
foreach ( array( '{pgr_jdate_today}', '{pgr_jdate_today_date}', '{pgr_jdate_today_time}' ) as $token ) {
	$before = $today_oracle();
	$actual = GFCommon::replace_variables( $token, $form, $entry );
	$after  = $today_oracle();
	if ( $actual !== $before[$token] && $actual !== $after[$token] ) {
		throw new RuntimeException( 'G011 current-time tag failed: ' . $token . ' actual=' . $actual );
	}
	$results[] = array( 'tag' => $token, 'actual' => $actual, 'status' => 'PASS', 'oracle' => 'bounded site clock' );
}
$negative = array(
	array( 'missing payment', '{pgr_jdate_payment}', array( 'payment_date' => null ), '' ),
	array( 'missing payment date', '{pgr_jdate_payment_date}', array( 'payment_date' => null ), '' ),
	array( 'missing payment time', '{pgr_jdate_payment_time}', array( 'payment_date' => null ), '' ),
	array( 'missing created', '{pgr_jdate_created}', array( 'date_created' => null ), '{pgr_jdate_created}' ),
	array( 'missing updated', '{pgr_jdate_updated_date}', array( 'date_updated' => null ), '{pgr_jdate_updated_date}' ),
	array( 'invalid created', '{pgr_jdate_created}', array( 'date_created' => '2026-02-30 12:00:00' ), '{pgr_jdate_created}' ),
	array( 'invalid updated', '{pgr_jdate_updated_time}', array( 'date_updated' => 'not a timestamp' ), '{pgr_jdate_updated_time}' ),
	array( 'invalid payment', '{pgr_jdate_payment}', array( 'payment_date' => '2026-02-30 12:00:00' ), '{pgr_jdate_payment}' ),
	array( 'out-of-range created', '{pgr_jdate_created_date}', array( 'date_created' => '1700-01-01 00:00:00' ), '{pgr_jdate_created_date}' ),
	array( 'out-of-range payment', '{pgr_jdate_payment_time}', array( 'payment_date' => '2220-01-01 00:00:00' ), '{pgr_jdate_payment_time}' ),
	array( 'invalid native Date', '{pgr_jdate_field_2}', array( '2' => '02/30/2026' ), '{pgr_jdate_field_2}' ),
	array( 'missing native Date', '{pgr_jdate_field_2}', array( '2' => '' ), '{pgr_jdate_field_2}' ),
	array( 'out-of-range native Date', '{pgr_jdate_field_2}', array( '2' => '03/20/2220' ), '{pgr_jdate_field_2}' ),
	array( 'wrong field type', '{pgr_jdate_field_1}', array(), '{pgr_jdate_field_1}' ),
	array( 'already Jalali field', '{pgr_jdate_field_3}', array(), '{pgr_jdate_field_3}' ),
	array( 'unknown field', '{pgr_jdate_field_99}', array(), '{pgr_jdate_field_99}' ),
);
foreach ( $negative as $case ) {
	$variant = array_replace( $entry, $case[2] );
	$actual  = GFCommon::replace_variables( $case[1], $form, $variant );
	if ( $actual !== $case[3] ) {
		throw new RuntimeException( 'G011 negative ' . $case[0] . ' failed: ' . $actual );
	}
	$results[] = array( 'case' => $case[0], 'tag' => $case[1], 'actual' => $actual, 'status' => 'PASS' );
}
$native = GFCommon::replace_variables( 'Name={Name:1} | Native={Visit:2} | Raw={entry:date_created}', $form, $entry );
if ( false === strpos( $native, 'Name=ordinary' ) ||
	false === strpos( $native, 'Native=03/20/2026' ) ||
	false === strpos( $native, 'Raw=2026-03-20 21:30:00' ) ) {
	throw new RuntimeException( 'G011 token matrix affected native Merge Tags.' );
}
if ( $before_form !== wp_json_encode( GFAPI::get_form( (int) $fixture['form_id'] ) ) ||
	$before_entry !== wp_json_encode( GFAPI::get_entry( (int) $fixture['entry_id'] ) ) ) {
	throw new RuntimeException( 'G011 token matrix mutated persisted Form/Entry.' );
}
file_put_contents( $dir . '/g011-token-matrix.json', wp_json_encode( array(
	'head' => getenv( 'WU008_PGR_SHA' ), 'gf_version' => GFForms::$version,
	'status' => 'PASS', 'checks' => $results, 'native' => $native,
	'stored_unchanged' => true,
), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
echo "G011 explicit system/date token matrix PASS " . count( $results ) . "\n";
