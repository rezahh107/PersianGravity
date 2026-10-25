<?php
/**
 * Install two disposable frontend pages exercising one configured Text Confirmation.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}
$dir = getenv( 'G011_ARTIFACT_DIR' );
$path = is_string( $dir ) ? $dir . '/g011-enabled-runtime.json' : '';
$state = is_readable( $path ) ? json_decode( file_get_contents( $path ), true ) : null;
if ( ! is_array( $state ) || ! isset( $state['form_id'] ) ) {
    fwrite( STDERR, "G011 browser fixture missing runtime form ID.\n" );
    exit( 1 );
}
$form_id = (int) $state['form_id'];
$pages = array();
foreach ( array( 'ordinary' => false, 'ajax' => true ) as $mode => $is_ajax ) {
    $shortcode = '[gravityform id="' . $form_id . '" title="false" description="false" ajax="' . ( $is_ajax ? 'true' : 'false' ) . '"]';
    $page = wp_insert_post(
        array(
            'post_title'   => 'G011 Test ' . $mode,
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => $shortcode,
        ),
        true
    );
    if ( is_wp_error( $page ) || ! is_int( $page ) ) {
        fwrite( STDERR, "G011 browser fixture page creation failed.\n" );
        exit( 1 );
    }
    $pages[ $mode ] = get_permalink( $page );
}
$manifest = array( 'form_id' => $form_id, 'pages' => $pages );
file_put_contents( $dir . '/g011-browser-fixture.json', wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES ) );
echo "G011_BROWSER_FIXTURE_PASS\n";
