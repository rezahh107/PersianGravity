<?php
/*
Plugin Name: گرویتی فرم فارسی
Description: بسته کامل بومی ساز گرویتی فرم برای ایرانیان - به همراه امکانات جانبی
Plugin URI: https://wordpress.org/plugins/persian-gravity-forms/
Version: 2.9.0-beta
Author: گرویتی فرم فارسی
Author URI: https://profiles.wordpress.org/persianscript/
Domain Path: /languages/
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'GF_PERSIAN_VERSION' ) ) {
	define( 'GF_PERSIAN_VERSION', '2.9.0-beta' );
}

if ( ! defined( 'GF_PERSIAN_SLUG' ) ) {
	define( 'GF_PERSIAN_SLUG', 'persian' );
}

if ( ! defined( 'GF_PERSIAN_DIR' ) ) {
	define( 'GF_PERSIAN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'GF_PERSIAN_URL' ) ) {
	define( 'GF_PERSIAN_URL', plugins_url( '', __FILE__ ) . '/' );
}

// Composer autoload
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Bootstrap plugin services
add_action('plugins_loaded', function() {
    if (class_exists('PersianGravityForms\\Core\\Plugin')) {
        PersianGravityForms\Core\Plugin::init();
    }
    // Legacy core loader for backward compatibility
    if (file_exists(__DIR__ . '/includes/class-core.php')) {
        require_once __DIR__ . '/includes/class-core.php';
    }
});
