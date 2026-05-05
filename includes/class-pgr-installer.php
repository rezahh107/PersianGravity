<?php
/**
 * Installer/Activator for Persian Gravity Forms Refactor.
 *
 * @package PersianGravityFormsRefactor
 * @since   3.0.0
 */

defined( 'ABSPATH' ) || exit;

class PGR_Installer {

    public static function activate() : void {
        // PHP version check
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            deactivate_plugins( plugin_basename( PGR_FILE ) );
            wp_die( esc_html__( 'Persian Gravity Forms Refactor requires PHP 7.4 or newer.', 'persian-gravityforms-refactor' ) );
        }

        // WordPress version check
        global $wp_version;
        if ( version_compare( $wp_version, '5.8', '<' ) ) {
            deactivate_plugins( plugin_basename( PGR_FILE ) );
            wp_die( esc_html__( 'Persian Gravity Forms Refactor requires WordPress 5.8 or newer.', 'persian-gravityforms-refactor' ) );
        }

        // Store plugin version
        update_option( 'pgr_version', '3.0.0' );
        
        // Clean up old options from previous versions (if any)
        delete_option( 'pgr_installed' );
    }

    public static function uninstall() : void {
        if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
            return;
        }

        delete_option( 'pgr_version' );
        delete_option( 'pgr_settings' );
        delete_option( 'pgr_installed' );
    }
}
