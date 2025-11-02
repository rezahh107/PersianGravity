<?php
/**
 * Installer/Activator for Persian Gravity Forms Refactor.
 *
 * @package PersianGravityFormsRefactor
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles install/uninstall routines.
 */
class PGR_Installer {

	/**
	 * On plugin activation.
	 *
	 * @since 1.0.0
	 */
	public static function activate() : void {
		// DB upgrades or option initialization go here.
		// Example baseline option.
		add_option( 'pgr_version', '1.0.0' );
	}

	/**
	 * On plugin deactivation.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() : void {
		// No-op for now.
	}

	/**
	 * On plugin uninstall.
	 *
	 * @since 1.0.0
	 */
	public static function uninstall() : void {
		// Run only when WordPress calls uninstall.
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			return;
		}
		delete_option( 'pgr_version' );
	}
}
