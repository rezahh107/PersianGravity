<?php
/**
 * Plugin Name: Persian Gravity Forms
 * Description: Generic Persian and Iranian enhancements for Gravity Forms.
 * Version: 4.0.0
 * Requires at least: 6.7
 * Requires PHP: 8.2
 * Author: PGR Team
 * Text Domain: persian-gravityforms
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'PGR_VERSION', '4.0.0' );
define( 'PGR_FILE', __FILE__ );
define( 'PGR_PATH', plugin_dir_path( __FILE__ ) );
define( 'PGR_URL', plugin_dir_url( __FILE__ ) );
define( 'PGR_MIN_GF_VERSION', '3.0' );

/**
 * Load this plugin's own translations using the standard WordPress mechanism.
 *
 * @return void
 */
function pgr_load_textdomain() {
	load_plugin_textdomain( 'persian-gravityforms', false, dirname( plugin_basename( PGR_FILE ) ) . '/languages' );
}
add_action( 'init', 'pgr_load_textdomain' );

/**
 * Initialize the Gravity Forms-dependent runtime exactly once.
 *
 * @return void
 */
function pgr_initialize() {
	static $initialized = false;

	if ( $initialized ) {
		return;
	}

	if ( ! class_exists( 'GFForms' ) || ! class_exists( 'GF_Field' ) || ! class_exists( 'GF_Fields' ) ) {
		return;
	}

	if ( version_compare( GFForms::$version, PGR_MIN_GF_VERSION, '<' ) ) {
		return;
	}

	require_once PGR_PATH . 'includes/class-pgr-utils.php';
	require_once PGR_PATH . 'includes/class-pgr-address.php';
	require_once PGR_PATH . 'includes/class-pgr-currency.php';
	require_once PGR_PATH . 'includes/class-pgr-persian-date.php';
	require_once PGR_PATH . 'includes/fields/class-gf-field-national-id.php';
	require_once PGR_PATH . 'admin/class-pgr-admin.php';
	require_once PGR_PATH . 'includes/class-pgr-core.php';

	PGR_Core::init();
	$initialized = true;
}
add_action( 'gform_loaded', 'pgr_initialize', 5 );

/**
 * Show an administrative dependency notice when Gravity Forms is unavailable
 * or older than the supported plugin baseline.
 *
 * @return void
 */
function pgr_dependency_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	if ( ! class_exists( 'GFForms' ) ) {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Persian Gravity Forms requires Gravity Forms to be installed and active.', 'persian-gravityforms' )
		);
		return;
	}

	if ( version_compare( GFForms::$version, PGR_MIN_GF_VERSION, '<' ) ) {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			sprintf(
				/* translators: %s: minimum Gravity Forms version. */
				esc_html__( 'Persian Gravity Forms requires Gravity Forms %s or newer.', 'persian-gravityforms' ),
				esc_html( PGR_MIN_GF_VERSION )
			)
		);
	}
}
add_action( 'admin_notices', 'pgr_dependency_notice' );
