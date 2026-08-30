<?php
/**
 * Minimal WordPress test doubles for unit/runtime-characterization tests.
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['pgr_test_actions']  = array();
$GLOBALS['pgr_test_filters']  = array();
$GLOBALS['pgr_test_options']  = array();
$GLOBALS['pgr_test_enqueued'] = array();

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['pgr_test_actions'][ $hook ][ $priority ][] = array( $callback, $accepted_args );
}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['pgr_test_filters'][ $hook ][ $priority ][] = array( $callback, $accepted_args );
}
function do_action( $hook, ...$args ) {
	foreach ( $GLOBALS['pgr_test_actions'][ $hook ] ?? array() as $callbacks ) {
		foreach ( $callbacks as $item ) {
			call_user_func_array( $item[0], array_slice( $args, 0, $item[1] ) );
		}
	}
}
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugin_dir_url( $file ) { return 'https://example.test/wp-content/plugins/persian-gravity/'; }
function plugin_basename( $file ) { return 'persian-gravity/' . basename( $file ); }
function load_plugin_textdomain() { return true; }
function current_user_can() { return true; }
function esc_html__( $text ) { return $text; }
function esc_attr__( $text ) { return $text; }
function __( $text ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_js( $text ) { return addslashes( (string) $text ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function absint( $value ) { return abs( (int) $value ); }
function rgar( $array, $key, $default = null ) { return is_array( $array ) && array_key_exists( $key, $array ) ? $array[ $key ] : $default; }
function get_option( $key, $default = false ) { return $GLOBALS['pgr_test_options'][ $key ] ?? $default; }
function register_setting() {}
function add_settings_section() {}
function add_settings_field() {}
function add_options_page() {}
function checked( $checked, $current = true, $echo = true ) {
	$result = $checked == $current ? 'checked="checked"' : '';
	if ( $echo ) { echo $result; }
	return $result;
}
function settings_fields() {}
function do_settings_sections() {}
function submit_button() {}
function wp_enqueue_script( $handle ) { $GLOBALS['pgr_test_enqueued'][] = $handle; }
function delete_option( $key ) { unset( $GLOBALS['pgr_test_options'][ $key ] ); }
