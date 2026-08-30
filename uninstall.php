<?php
/**
 * Uninstall cleanup for Persian Gravity Forms.
 *
 * @package PersianGravityForms
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'pgr_settings' );
