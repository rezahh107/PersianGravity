<?php
/**
 * Uninstall handler.
 *
 * @package PersianGravityFormsRefactor
 * @since   1.0.0
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'pgr_version' );
