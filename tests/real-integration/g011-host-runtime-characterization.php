<?php
/**
 * G-011 exact-host confirmation characterization. No PersianGravity runtime change.
 */
if ( ! defined( 'ABSPATH' ) || ! class_exists( 'GFForms' ) || '3.1.1.1' !== GFForms::$version ) {
    exit( 1 );
}
$fail = static function( $why ) {
    fwrite( STDERR, "G011_CHAR_FAIL: {$why}\n" );
    exit( 1 );
};
update_option( 'timezone_string', 'Asia/Tehran' );

$form_id = GFAPI::add_form( array(
    'title' => 'G011 Confirmation Contract Probe',
    'fields' => array(
        new GF_Field_Date( array(
            'id' => 1,
            'label' => 'Gregorian date',
            'type' => 'date',
            'dateType' => 'datefield',
            'dateFormat' => 'ymd',
        ) ),
        new GF_Field_Text( array( 'id' => 2, 'label' => 'Plain text' ) ),
    ),
) );
if ( ! is_int( $form_id ) ) { $fail( 'cannot add form' ); }
$form = GFAPI::get_form( $form_id );
if ( ! is_array( $form ) ) { $fail( 'cannot load form' ); }
$entry_id = GFAPI::add_entry( array(
    'form_id' => $form_id,
    'date_created' => '2026-03-20 20:30:00',
    'date_updated' => '2026-03-20 21:30:00',
    'payment_date' => '',
    'payment_status' => '',
    '1' => '2026-03-21',
    '2' => '2026-03-21',
    'status' => 'active',
) );
if ( ! is_int( $entry_id ) ) { $fail( 'cannot add entry' ); }
$entry = GFAPI::get_entry( $entry_id );
if ( ! is_array( $entry ) ) { $fail( 'cannot load entry' ); }

$tokens = array(
    '{date_created}', '{date_created:raw}', '{date_created:timestamp}',
    '{date_created:human}', '{date_created:diff}', '{date_created:time}',
    '{date_created:format:Y-m-d}', '{date_created:format:Y-m-d H:i:s}',
    '{date_updated}', '{payment_date}', '{today}', '{today:format:Y-m-d}',
    '{entry:date_created}', '{Gregorian date:1}', '{Gregorian date:1:year}',
    '{Gregorian date:1:month}', '{Gregorian date:1:day}',
    '{Gregorian date:1:ymd}', '{Gregorian date:1:dmy}',
    '{Plain text:2}',
);
$native = array();
foreach ( $tokens as $token ) {
    $native[ $token ] = GFCommon::replace_variables( $token, $form, $entry );
}

$events = array();
add_filter( 'gform_pre_replace_merge_tags', static function( $text ) use ( &$events ) {
    $frames = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 11 );
    $events[] = array(
        'filter' => 'gform_pre_replace_merge_tags',
        'stack' => array_map( static function( $frame ) { return ( $frame['class'] ?? '' ) . '::' . ( $frame['function'] ?? '' ); }, $frames ),
        'text_has_date_tag' => false !== strpos( $text, '{date_created}' ),
    );
    return $text;
}, 999 );

add_filter( 'gform_confirmation', static function( $confirmation, $actual_form, $actual_entry, $ajax ) use ( &$events ) {
    $events[] = array(
        'filter' => 'gform_confirmation',
        'type' => is_array( $confirmation ) ? 'array' : gettype( $confirmation ),
        'is_ajax' => (bool) $ajax,
        'keys' => is_array( $confirmation ) ? array_keys( $confirmation ) : array(),
        'has_native_datetime' => is_string( $confirmation ) && false !== strpos( $confirmation, '2026' ),
    );
    return $confirmation;
}, 999, 4 );

$confirmation = array(
    'id' => 'g011default',
    'name' => 'Default Confirmation',
    'isDefault' => true,
    'isActive' => true,
    'type' => 'message',
    'message' => 'HEADER <b>{date_created}</b> | {today} | {Gregorian date:1} | {Plain text:2} TAIL',
);
$form['confirmations'] = array( 'g011default' => $confirmation );
GFAPI::update_form( $form );
$form = GFAPI::get_form( $form_id );
ob_start();
$result = GFFormDisplay::handle_confirmation( $form, $entry, false );
$rendered = ob_get_clean();
$reports = array(
    'vendor_version' => GFForms::$version,
    'module_version' => null,
    'form_id' => $form_id,
    'entry_id' => $entry_id,
    'native_tokens' => $native,
    'confirmation_events' => $events,
    'rendered_type' => gettype( $result ),
    'rendered_result_shape' => is_array( $result ) ? array_keys( $result ) : array(),
    'rendered_output_length' => strlen( $rendered ),
    'confirmation_definition' => array( 'type' => $confirmation['type'], 'isDefault' => true ),
);
$out = getenv( 'G011_ARTIFACT_DIR' );
if ( ! is_string( $out ) || ! is_dir( $out ) ) { $fail( 'artifact path missing' ); }
file_put_contents( $out . '/host-runtime-characterization.json', wp_json_encode( $reports, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
echo "G011_NATIVE_TOKENS " . wp_json_encode( $native, JSON_UNESCAPED_UNICODE ) . "\n";
echo "G011_CONFIRMATION_EVENTS " . wp_json_encode( $events, JSON_UNESCAPED_UNICODE ) . "\n";
echo "G011_HANDLE_RESULT " . gettype( $result ) . " output_length=" . strlen( $rendered ) . "\n";
echo "G011_HOST_RUNTIME_CHARACTERIZATION_PASS\n";
