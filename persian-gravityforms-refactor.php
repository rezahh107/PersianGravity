
<?php
/**
 * Plugin bootstrap file for Persian Gravity Forms Refactor.
 *
 * @package PersianGravityFormsRefactor
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'includes/autoload.php';

// Register activation and deactivation hooks
register_activation_hook( __FILE__, array( 'PGR_Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PGR_Installer', 'deactivate' ) );

// Load text domain
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'persian-gravityforms-refactor', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}, 0 );

// Register National ID field
add_action('gform_loaded', function() {
    if (!class_exists('GF_Fields')) return;
    require_once PGR_PATH . 'includes/fields/class-gf-field-national-id.php';
    GF_Fields::register(new GF_Field_National_ID());
}, 5);

// Load the core plugin functionality
PGR_Core::instance();
