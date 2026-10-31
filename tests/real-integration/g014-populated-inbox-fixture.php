<?php
/**
 * G-014 only: build enough assigned native tasks for real paging/state checks.
 * Runs after all existing WU008 baselines in disposable licensed WordPress.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit( 1 );
}
$manifest_path = getenv( 'WU008_MANIFEST_PATH' );
$manifest = is_string( $manifest_path ) && is_readable( $manifest_path )
    ? json_decode( file_get_contents( $manifest_path ), true, 512, JSON_THROW_ON_ERROR ) : null;
$form_id = (int) ( $manifest['g008_flow_form_id'] ?? 0 );
if ( $form_id < 1 || ! defined( 'GRAVITY_FLOW_VERSION' ) ||
    '3.1.0' !== GRAVITY_FLOW_VERSION || ! class_exists( 'Gravity_Flow_API' ) ) {
    throw new RuntimeException( 'G014 populated fixture requires licensed Flow 3.1.0 and G008 native form.' );
}
$api = new Gravity_Flow_API( $form_id );
$ids = array();
for ( $index = 1; $index <= 30; $index++ ) {
    $entry_id = GFAPI::add_entry(
        array(
            'form_id' => $form_id,
            'created_by' => 1,
            'date_created' => gmdate( 'Y-m-d H:i:s', strtotime( '2026-03-23 00:00:00 UTC' ) + $index * HOUR_IN_SECONDS ),
            '1' => sprintf( 'G014-PAGING-%02d', $index ),
        )
    );
    if ( is_wp_error( $entry_id ) || ! is_numeric( $entry_id ) ) {
        throw new RuntimeException( 'G014 could not create a native assigned task.' );
    }
    $api->process_workflow( $entry_id );
    $entry = GFAPI::get_entry( $entry_id );
    $step = is_wp_error( $entry ) ? false : $api->get_current_step( $entry );
    if ( ! $step ) {
        throw new RuntimeException( 'G014 entry lacks current Gravity Flow step: ' . $entry_id );
    }
    $keys = array_map( static function ( $assignee ) { return $assignee->get_key(); }, $step->get_assignees() );
    if ( ! in_array( 'user_id|1', $keys, true ) ) {
        throw new RuntimeException( 'G014 entry lacks authentic Inbox assignee.' );
    }
    $ids[] = (int) $entry_id;
}
if ( count( $ids ) !== 30 ) {
    throw new RuntimeException( 'Incomplete G014 pagination fixture.' );
}
echo 'G014_POPULATED_NATIVE_FIXTURE ' . count( $ids ) . " assigned tasks\n";
