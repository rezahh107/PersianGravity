
<?php
/**
 * Installer/Activator for Persian Gravity Forms Refactor.
 *
 * @package PersianGravityFormsRefactor
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class PGR_Installer {

    public static function activate() : void {
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            deactivate_plugins( plugin_basename( PGR_FILE ) );
            wp_die( esc_html__( 'Persian Gravity Forms Refactor requires PHP 7.4 or newer.', 'persian-gravityforms-refactor' ) );
        }

        global $wp_version;
        if ( version_compare( $wp_version, '5.8', '<' ) ) {
            deactivate_plugins( plugin_basename( PGR_FILE ) );
            wp_die( esc_html__( 'Persian Gravity Forms Refactor requires WordPress 5.8 or newer.', 'persian-gravityforms-refactor' ) );
        }

        add_option( 'pgr_version', '1.0.0' );
    }

    public static function uninstall() : void {
        if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
            return;
        }

        delete_option( 'pgr_version' );
        delete_option( 'pgr_installed' );
    }
}
