<?php
/**
 * Plugin Name: Persian Gravity Forms Refactor
 * Description: Persian national ID field for Gravity Forms with server-side validation and privacy options. Simplified build (no province/city feature).
 * Version: 1.0.4
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: RH Team
 * License: GPLv2 or later
 * Text Domain: persian-gravityforms-refactor
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

// Constants
if ( ! defined( 'PGR_VERSION' ) ) define( 'PGR_VERSION', '1.0.4' );
if ( ! defined( 'PGR_FILE' ) )    define( 'PGR_FILE', __FILE__ );
if ( ! defined( 'PGR_PATH' ) )    define( 'PGR_PATH', plugin_dir_path( __FILE__ ) );
if ( ! defined( 'PGR_URL' ) )     define( 'PGR_URL', plugin_dir_url( __FILE__ ) );

// i18n
add_action( 'plugins_loaded', function () {
    load_plugin_textdomain( 'persian-gravityforms-refactor', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}, 0 );

// Admin assets only on GF screens
add_action( 'admin_enqueue_scripts', function () {
    if ( ! function_exists( 'get_current_screen' ) ) return;
    $screen = get_current_screen();
    $is_gf  = $screen && ( false !== strpos( $screen->id, 'gravityforms' ) || false !== strpos( $screen->id, 'gf_edit_forms' ) );
    if ( ! $is_gf ) return;
    wp_register_script( 'pgr-admin', PGR_URL . 'assets/js/pgr-admin.js', array( 'jquery' ), PGR_VERSION, true );
    wp_register_style( 'pgr-admin', PGR_URL . 'assets/css/pgr-admin.css', array(), PGR_VERSION );
    wp_enqueue_script( 'pgr-admin' );
    wp_enqueue_style( 'pgr-admin' );
} );

// Frontend assets only when form has our field (no location feature)
add_filter( 'gform_pre_render',      'pgr_enqueue_frontend_assets' );
add_filter( 'gform_pre_validation',  'pgr_enqueue_frontend_assets' );
function pgr_enqueue_frontend_assets( $form ) {
    if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) return $form;
    $has_nid = false;
    foreach ( $form['fields'] as $field ) {
        if ( isset( $field->type ) && $field->type === 'pgr_national_id' ) { $has_nid = true; break; }
    }
    if ( $has_nid && ! is_admin() ) {
        wp_register_script( 'pgr-frontend', PGR_URL . 'assets/js/pgr-frontend.js', array( 'jquery' ), PGR_VERSION, true );
        if ( function_exists( 'wp_script_add_data' ) ) { wp_script_add_data( 'pgr-frontend', 'strategy', 'defer' ); }
        wp_enqueue_script( 'pgr-frontend' );
        wp_register_style( 'pgr-frontend', PGR_URL . 'assets/css/pgr-frontend.css', array(), PGR_VERSION );
        wp_enqueue_style( 'pgr-frontend' );
    }
    return $form;
}

// Privacy filters
add_filter( 'gform_save_field_value', function ( $value, $entry, $field, $form ) {
    if ( $field && isset( $field->type ) && $field->type === 'pgr_national_id' ) {
        $digits = preg_replace( '/[^0-9]/', '', (string) $value );
        if ( ! empty( $field->hashValue ) ) return hash( 'sha256', $digits . AUTH_SALT );
        if ( ! empty( $field->noStore ) )  return '';
        return $digits;
    }
    return $value;
}, 10, 4 );

add_filter( 'gform_export_field_value', function ( $value, $form_id, $field_id ) {
    if ( ! class_exists( 'GFAPI' ) ) return $value;
    $field = GFAPI::get_field( $form_id, $field_id );
    if ( $field && $field->type === 'pgr_national_id' && ! empty( $field->maskOnExport ) ) {
        $digits = preg_replace( '/\D+/', '', (string) $value );
        return strlen( $digits ) === 10 ? substr( $digits, 0, 3 ) . '******' . substr( $digits, -1 ) : $value;
    }
    return $value;
}, 10, 3 );

// Register custom field on GF load (no duplicate in field class)
add_action( 'gform_loaded', function () {
    if ( ! class_exists( 'GF_Fields' ) ) return;
    require_once PGR_PATH . 'includes/fields/class-gf-field-national-id.php';
    GF_Fields::register( new GF_Field_National_ID() );
}, 5 );
