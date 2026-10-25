<?php
/**
 * Authentic licensed GF 3.1.1.1 Text Confirmation runtime regression assertions.
 * These run only on a disposable WordPress installation in CI.
 */
if ( ! defined( 'ABSPATH' ) || ! class_exists( 'GFForms' ) || '3.1.1.1' !== GFForms::$version ) {
    fwrite( STDERR, "G011 exact Gravity Forms 3.1.1.1 unavailable.\n" );
    exit( 1 );
}
$fail = static function( string $reason ): void {
    fwrite( STDERR, "G011_CONFIRMATION_FAIL {$reason}\n" );
    exit( 1 );
};
if ( ! class_exists( 'PGR_Jalali_Presentation' ) || ! class_exists( 'PGR_GF_Confirmation_Date_Adapter' ) || ! PGR_Module_Registry::is_enabled( 'jalali_presentation' ) ) {
    $fail( 'Presentation module is not enabled.' );
}
update_option( 'timezone_string', 'Asia/Tehran' );
if ( 'fa_IR' !== determine_locale() ) {
    $fail( 'Site must be Persian for this bounded lane.' );
}
$form_id = GFAPI::add_form( array(
    'title' => 'G011 Exact Text Confirmation',
    'fields' => array(
        new GF_Field_Date( array( 'id' => 1, 'label' => 'Gregorian date', 'type' => 'date', 'dateType' => 'datefield', 'dateFormat' => 'ymd' ) ),
        new GF_Field_Text( array( 'id' => 2, 'label' => 'Plain text', 'type' => 'text' ) ),
    ),
) );
if ( ! is_int( $form_id ) ) {
    $fail( 'GFAPI did not add the fixture form.' );
}
$entry_id = GFAPI::add_entry( array(
    'form_id' => $form_id,
    'date_created' => '2026-03-20 20:30:00',
    'date_updated' => '2026-03-20 21:30:00',
    'payment_date' => '',
    '1' => '2026-03-21',
    '2' => '2026-03-21',
    'status' => 'active',
) );
if ( ! is_int( $entry_id ) ) {
    $fail( 'GFAPI did not add entry.' );
}
$entry = GFAPI::get_entry( $entry_id );
$form  = GFAPI::get_form( $form_id );
if ( ! is_array( $entry ) || ! is_array( $form ) ) {
    $fail( 'Native GFAPI could not retrieve fixture.' );
}
$raw_entry = $entry;
$native_regular = GFCommon::replace_variables( '{date_created}', $form, $entry );
$native_human   = GFCommon::replace_variables( '{date_created:human}', $form, $entry );
$native_diff    = GFCommon::replace_variables( '{date_created:diff}', $form, $entry );
$native_timestamp = GFCommon::replace_variables( '{date_created:timestamp}', $form, $entry );
if ( ! is_string( $native_regular ) || ! is_string( $native_human ) || ! is_string( $native_diff ) ) {
    $fail( 'Native GF merge tags unavailable.' );
}
$tags = array(
    'created' => '{date_created}',
    'updated' => '{date_updated}',
    'payment' => '{payment_date}',
    'today' => '{today}',
    'formatted' => '{date_created:format:Y-m-d}',
    'time' => '{date_created:time}',
    'year' => '{Gregorian date:1:year}',
    'month' => '{Gregorian date:1:month}',
    'day' => '{Gregorian date:1:day}',
    'ymd' => '{Gregorian date:1:ymd}',
    'dmy' => '{Gregorian date:1:dmy}',
    'field' => '{Gregorian date:1}',
    'raw' => '{date_created:raw}',
    'timestamp' => '{date_created:timestamp}',
    'human' => '{date_created:human}',
    'diff' => '{date_created:diff}',
    'entry' => '{entry:date_created}',
    'plain' => '{Plain text:2}',
);
$message = '<p>Unrelated <b>HTML</b> is unchanged.</p>';
foreach ( $tags as $name => $tag ) {
    $message .= '<p data-key="' . $name . '">' . $name . '=' . $tag . '</p>';
}
$message .= '<div data-native="{date_created}">Attribute must be native.</div>';
$default = array( 'id' => 'g011default', 'name' => 'Default Confirmation', 'isDefault' => true, 'isActive' => true, 'type' => 'message', 'message' => $message );
$form['confirmations'] = array( 'g011default' => $default );
$ok = GFAPI::update_form( $form );
if ( is_wp_error( $ok ) ) {
    $fail( 'GFAPI could not persist confirmation.' );
}
$form = GFAPI::get_form( $form_id );
// Obtain the exact same native Confirmation output without our adapter.
// This is the proof that opaque/unsupported merge tags remain native.
global $wp_filter;
$temporarily_removed = array();
foreach ( array( 'gform_pre_replace_merge_tags', 'gform_confirmation' ) as $hook_name ) {
    foreach ( (array) ( $wp_filter[ $hook_name ]->callbacks ?? array() ) as $priority => $callbacks ) {
        foreach ( $callbacks as $definition ) {
            $callback = $definition['function'] ?? null;
            if ( ! is_array( $callback ) || ! isset( $callback[0] ) || ! $callback[0] instanceof PGR_GF_Confirmation_Date_Adapter ) {
                continue;
            }
            $temporarily_removed[] = array( $hook_name, $callback, $priority, $definition['accepted_args'] );
            remove_filter( $hook_name, $callback, $priority );
        }
    }
}
if ( 2 !== count( $temporarily_removed ) ) {
    $fail( 'Expected exactly two presentation adapter hooks.' );
}
$native_confirmation = GFFormDisplay::handle_confirmation( $form, $entry, false );
foreach ( $temporarily_removed as $item ) {
    add_filter( $item[0], $item[1], $item[2], $item[3] );
}
if ( ! is_string( $native_confirmation ) ) {
    $fail( 'Could not render native comparison.' );
}
if ( ! preg_match( '/<p data-key="plain">plain=(.*?)<\/p>/us', $native_confirmation, $native_plain_match ) ) {
    $fail( 'Could not locate the native unrelated field value.' );
}
$native_plain_confirmation = $native_plain_match[1];
$native_time = GFCommon::replace_variables( '{date_created:time}', $form, $entry );
if ( ! is_string( $native_time ) || ! str_starts_with( $native_time, $native_regular ) ) {
    $fail( 'Native time suffix cannot be characterized.' );
}
$native_time_suffix = substr( $native_time, strlen( $native_regular ) );
$observed = array();
foreach ( array( false, true ) as $ajax ) {
    $html = GFFormDisplay::handle_confirmation( $form, $entry, $ajax );
    if ( ! is_string( $html ) ) {
        $fail( 'Unexpected native confirmation output type.' );
    }
    $expected = array(
        'created' => '۱۴۰۵/۰۱/۰۱',
        'updated' => '۱۴۰۵/۰۱/۰۱',
        'payment' => '',
        'formatted' => '۱۴۰۵-۰۱-۰۱',
        'year' => '۱۴۰۵',
        'month' => '۰۱',
        'day' => '۰۱',
        'ymd' => '۱۴۰۵/۰۱/۰۱',
        'dmy' => '۰۱/۰۱/۱۴۰۵',
        'field' => '۱۴۰۵/۰۱/۰۱',
        'raw' => '2026-03-20 20:30:00',
        'timestamp' => $native_timestamp,
        'human' => $native_human,
        'diff' => $native_diff,
        'entry' => '2026-03-20 20:30:00',
        'plain' => $native_plain_confirmation,
    );
    foreach ( $expected as $name => $value ) {
        if ( ! str_contains( $html, $name . '=' . $value . '</p>' ) ) {
            $fail( ( $ajax ? 'AJAX' : 'ordinary' ) . ' unexpected ' . $name . ' value: ' . substr( $html, 0, 1200 ) );
        }
    }
    if (
        ! str_contains( $html, 'Unrelated <b>HTML</b> is unchanged.' ) ||
        ! str_contains( $html, 'data-native="' . $native_regular . '"' ) ||
        ! str_contains( $html, 'time=۱۴۰۵/۰۱/۰۱' . $native_time_suffix . '</p>' ) ||
        str_contains( $html, 'PGRG011' )
    ) {
        $fail( 'Unrelated HTML, native attribute, time suffix or marker cleanup regressed.' );
    }
    $today = new DateTimeImmutable( 'now', wp_timezone() );
    $today_jalali = PGR_Jalali_Presentation::format_date( (int) $today->format( 'Y' ), (int) $today->format( 'm' ), (int) $today->format( 'd' ) );
    if ( ! str_contains( $html, 'today=' . $today_jalali . '</p>' ) ) {
        $fail( 'Today not formatted in Persian.' );
    }
    $observed[ $ajax ? 'ajax' : 'ordinary' ] = array( 'output_length' => strlen( $html ), 'passed_fields' => count( $expected ) + 2 );
}
if ( $raw_entry !== GFAPI::get_entry( $entry_id ) ) {
    $fail( 'Presentation altered persisted entry returned by GFAPI.' );
}
if ( GFCommon::replace_variables( '{date_created}', $form, $entry ) !== $native_regular ) {
    $fail( 'Global merge-tag consumer changed.' );
}
$manifest = array(
    'form_id' => $form_id,
    'entry_id' => $entry_id,
    'gravity_forms_version' => GFForms::$version,
    'wordpress_version' => get_bloginfo( 'version' ),
    'persiangravity_version' => PGR_VERSION,
    'utc_date_created' => $raw_entry['date_created'],
    'native_date_created' => $native_regular,
    'native_unrelated_field_confirmation' => $native_plain_confirmation,
    'ordinary_ajax' => $observed,
    'machine_values_unchanged' => true,
);
$out = getenv( 'G011_ARTIFACT_DIR' );
if ( ! is_string( $out ) || ! is_dir( $out ) || false === file_put_contents( $out . '/g011-enabled-runtime.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) ) {
    $fail( 'Could not save evidence.' );
}
echo "G011_ENABLED_RUNTIME_PASS\n";
