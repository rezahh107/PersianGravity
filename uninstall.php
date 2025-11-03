<?php
/**
 * Uninstall cleanup for Persian Gravity Forms Refactor
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Respect a guard option: only full cleanup if explicitly enabled
$delete_all = (bool) get_option( 'pgr_delete_on_uninstall', false );
// Always clean transient caches
delete_transient( 'pgr_cities_cache' );

if ( ! $delete_all ) {
    return;
}

// Remove plugin options if any are added in future
delete_option( 'pgr_delete_on_uninstall' );
