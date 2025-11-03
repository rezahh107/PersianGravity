<?php
/**
 * Plugin Name: Persian Gravity Forms (Refactor)
 * Description: افزودن فیلد «کد ملی ایران» و ابزارهای فارسی‌سازی برای Gravity Forms — با تنظیمات سراسری و کنترل در سطح هر فرم.
 * Version: 2.0.0
 * Author: PGR Team
 * Text Domain: persian-gravityforms-refactor
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'PGR_VERSION', '2.0.0' );
define( 'PGR_FILE', __FILE__ );
define( 'PGR_PATH', plugin_dir_path( __FILE__ ) );
define( 'PGR_URL',  plugin_dir_url( __FILE__ ) );

// -----------------------------------------------------------------------------
// Autoload simple PSR-4 style for PGR_ classes.
// -----------------------------------------------------------------------------
require_once PGR_PATH . 'includes/autoload.php';

// -----------------------------------------------------------------------------
// Boot Admin (settings page + ajax tools) only in admin.
// -----------------------------------------------------------------------------
add_action( 'plugins_loaded', function () {
    if ( is_admin() ) {
        if ( class_exists( 'PGR_Admin' ) ) {
            ( new PGR_Admin() )->hooks();
        }
    }
}, 5 );

// -----------------------------------------------------------------------------
/**
 * Register/enqueue assets in admin when editing Gravity Forms.
 */
add_action( 'admin_enqueue_scripts', function( $hook ) {
    // Load only on GF-related admin screens
    $is_gf_screen = ( isset( $_GET['page'] ) && strpos( sanitize_text_field( $_GET['page'] ), 'gf' ) === 0 );
    if ( ! $is_gf_screen ) { return; }

    wp_register_script( 'pgr-admin', PGR_URL . 'assets/js/pgr-admin.js', array( 'jquery' ), PGR_VERSION, true );

    // Pass global defaults for newly-created fields
    $opt = get_option( 'pgr_settings', array() );
    wp_localize_script( 'pgr-admin', 'PGRDefaults', array(
        'forceEnglish'   => ! empty( $opt['default_force_english'] ),
        'liveValidation' => ! empty( $opt['enable_nid_validation'] ),
    ) );

    wp_enqueue_script( 'pgr-admin' );
} );

// -----------------------------------------------------------------------------
/**
 * Frontend scripts for live validation/normalize.
 */
add_action( 'wp_enqueue_scripts', function() {
    wp_register_script( 'pgr-frontend', PGR_URL . 'assets/js/pgr-frontend.js', array(), PGR_VERSION, true );
    wp_enqueue_script( 'pgr-frontend' );
} );

// -----------------------------------------------------------------------------
/**
 * Register custom field with Gravity Forms after it has loaded.
 */
add_action( 'gform_loaded', function() {
    if ( class_exists( 'GF_Fields' ) && class_exists( 'PGR_GF_Field_National_ID' ) ) {
        GF_Fields::register( new PGR_GF_Field_National_ID() );
    }
} );

// -----------------------------------------------------------------------------
/**
 * Per-form toggle: Persian formatting (normalize digits) on submit.
 */
add_filter( 'gform_form_settings', function( $settings, $form ) {
    $checked = ! empty( $form['pgr_enable'] ) ? 'checked' : '';
    $row  = '<tr>';
    $row .= '<th><label for="pgr_enable">'. esc_html__( 'Persian formatting', 'persian-gravityforms-refactor' ) .'</label></th>';
    $row .= '<td><label>';
    $row .= '<input id="pgr_enable" type="checkbox" name="pgr_enable" value="1" '. $checked .' /> ';
    $row .= esc_html__( 'Normalize Persian/Arabic digits on submit for this form', 'persian-gravityforms-refactor' );
    $row .= '</label><p class="description">'. esc_html__( 'If enabled, all text inputs will be normalized to English digits on submission.', 'persian-gravityforms-refactor' ) .'</p>';
    $row .= '</td></tr>';

    if ( isset( $settings['Form Basics'] ) ) {
        $settings['Form Basics'] .= $row;
    }
    return $settings;
}, 10, 2 );

add_filter( 'gform_pre_form_settings_save', function( $form ) {
    $form['pgr_enable'] = isset( $_POST['pgr_enable'] ) ? 1 : 0;
    return $form;
} );

add_action( 'gform_pre_submission', function( $form ) {
    if ( empty( $form['pgr_enable'] ) ) { return; }
    if ( ! class_exists( 'PGR_Utils' ) ) { return; }

    foreach ( $_POST as $k => $v ) {
        if ( is_string( $v ) ) {
            $_POST[ $k ] = PGR_Utils::normalize_digits( $v );
        } elseif ( is_array( $v ) ) {
            array_walk_recursive( $v, function( &$vv ) {
                if ( is_string( $vv ) ) {
                    $vv = PGR_Utils::normalize_digits( $vv );
                }
            } );
            $_POST[ $k ] = $v;
        }
    }
} );

// -----------------------------------------------------------------------------
// Require the field class explicitly (autoload also covers it; this is defensive)
require_once PGR_PATH . 'includes/fields/class-gf-field-national-id.php';
