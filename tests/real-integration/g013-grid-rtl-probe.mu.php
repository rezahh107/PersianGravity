<?php
/**
 * PRI-FND-005 disposable WU008-only source-proven Gravity Flow JS config filter experiment.
 * Not product code; copy only into a disposable WordPress mu-plugins folder, then remove.
 */
add_filter( 'gravityflow_js_config_shared', static function ( $config ) {
    $keys = is_array( $config ) ? array_keys( $config ) : array();
    $changed = 0;
    $before = array();
    $rtl = is_rtl();
    if ( is_array( $config ) && isset( $config['grids'] ) && is_array( $config['grids'] ) ) {
        foreach ( $config['grids'] as $key => &$grid ) {
            if ( ! is_array( $grid ) || ! isset( $grid['grid_options'] ) || ! is_array( $grid['grid_options'] ) ) {
                continue;
            }
            $before[] = array( 'hadOption' => array_key_exists( 'enableRtl', $grid['grid_options'] ),
                'before' => $grid['grid_options']['enableRtl'] ?? null );
            $grid['grid_options']['enableRtl'] = $rtl;
            ++$changed;
        }
        unset( $grid );
    }
    $destination = getenv( 'WU008_ARTIFACT_DIR' );
    if ( is_string( $destination ) && $destination !== '' ) {
        $entry = array( 'hook' => current_filter(), 'priority' => PHP_INT_MAX,
            'sharedConfigKeys' => array_values( array_filter( $keys, 'is_string' ) ),
            'isAdmin' => is_admin(), 'siteLocale' => get_locale(), 'userLocale' => get_user_locale(),
            'isRtl' => $rtl, 'modifiedGridCount' => $changed, 'before' => $before );
        file_put_contents( $destination . '/g013-flow-filter-invocations.jsonl',
            wp_json_encode( $entry ) . "\n", FILE_APPEND | LOCK_EX );
    }
    return $config;
}, PHP_INT_MAX, 1 );
