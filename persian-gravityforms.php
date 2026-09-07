<?php
/**
 * Plugin Name: Persian Gravity Forms
 * Description: Generic Persian and Iranian enhancements for Gravity Forms.
 * Version: 4.2.0
 * Requires at least: 6.7
 * Requires PHP: 8.2
 * Author: PGR Team
 * Text Domain: persian-gravityforms
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'PGR_VERSION', '4.2.0' );
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
 * Initialize non-disableable WordPress admin infrastructure independently of Gravity Forms.
 *
 * @return void
 */
function pgr_initialize_admin() {
	static $initialized = false;

	if ( $initialized ) {
		return;
	}

	require_once PGR_PATH . 'includes/class-pgr-module-registry.php';
	require_once PGR_PATH . 'includes/class-pgr-module-usage.php';
	require_once PGR_PATH . 'includes/class-pgr-scanner-profile-registry.php';
	require_once PGR_PATH . 'admin/class-pgr-admin.php';
	require_once PGR_PATH . 'admin/class-pgr-help-catalog.php';
	require_once PGR_PATH . 'admin/class-pgr-product-admin.php';

	$admin = new PGR_Product_Admin( new PGR_Admin() );
	$admin->hooks();

	$initialized = true;
}
add_action( 'plugins_loaded', 'pgr_initialize_admin', 5 );

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

	require_once PGR_PATH . 'includes/class-pgr-module-registry.php';

	$national_enabled = PGR_Module_Registry::is_enabled( 'national_id' );
	$digits_enabled   = PGR_Module_Registry::is_enabled( 'digit_normalization' );
	$jalali_enabled   = PGR_Module_Registry::is_enabled( 'jalali_date' );
	$address_enabled  = PGR_Module_Registry::is_enabled( 'iranian_address' );
	$currency_enabled = PGR_Module_Registry::is_enabled( 'iranian_currency' );
	$scanner_enabled  = PGR_Module_Registry::is_enabled( 'structured_scanner' );

	if ( $national_enabled || $digits_enabled ) {
		require_once PGR_PATH . 'includes/class-pgr-utils.php';
	}
	if ( $address_enabled ) {
		require_once PGR_PATH . 'includes/class-pgr-address.php';
	}
	if ( $currency_enabled ) {
		require_once PGR_PATH . 'includes/class-pgr-currency.php';
	}
	if ( $jalali_enabled ) {
		require_once PGR_PATH . 'includes/class-pgr-persian-date.php';
		require_once PGR_PATH . 'includes/fields/class-gf-field-jalali-date.php';
	}
	if ( $scanner_enabled ) {
		require_once PGR_PATH . 'includes/class-pgr-scanner-profile-registry.php';
		require_once PGR_PATH . 'includes/fields/class-gf-field-structured-scanner.php';
	}
	if ( $national_enabled ) {
		require_once PGR_PATH . 'includes/fields/class-gf-field-national-id.php';
	}

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
