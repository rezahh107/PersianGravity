<?php
/**
 * Plugin Name: Persian Gravity Forms (Refactor)
 * Description: افزودن فیلد «کد ملی ایران» و ابزارهای فارسی‌سازی برای Gravity Forms — با تنظیمات سراسری و کنترل در سطح هر فرم.
 * Version: 3.0.0
 * Author: PGR Team
 * Text Domain: persian-gravityforms-refactor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PGR_VERSION', '3.0.0' );
define( 'PGR_FILE', __FILE__ );
define( 'PGR_PATH', plugin_dir_path( __FILE__ ) );
define( 'PGR_URL', plugin_dir_url( __FILE__ ) );

// -----------------------------------------------------------------------------
// بررسی وجود Gravity Forms
// -----------------------------------------------------------------------------
add_action( 'admin_init', function() {
    if ( ! class_exists( 'GFCommon' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>' .
                 esc_html__( 'Persian Gravity Forms requires Gravity Forms to be installed and active.', 'persian-gravityforms-refactor' ) .
                 '</p></div>';
        } );
    }
} );

if ( ! class_exists( 'GFCommon' ) ) {
    return;
}

// -----------------------------------------------------------------------------
// Autoloader (PSR-4 style)
// -----------------------------------------------------------------------------
require_once PGR_PATH . 'includes/autoload.php';

// -----------------------------------------------------------------------------
// راه‌اندازی هسته اصلی پلاگین (کلاس PGR_Core)
// -----------------------------------------------------------------------------
add_action( 'plugins_loaded', [ 'PGR_Core', 'instance' ] );
