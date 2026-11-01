<?php
/**
 * Minimal WordPress test doubles for unit/runtime-characterization tests.
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['pgr_test_actions']         = array();
$GLOBALS['pgr_test_filters']         = array();
$GLOBALS['pgr_test_options']         = array();
$GLOBALS['pgr_test_option_autoload'] = array();
$GLOBALS['pgr_test_enqueued']        = array();
$GLOBALS['pgr_test_styles']          = array();
$GLOBALS['pgr_test_inline_scripts']  = array();
$GLOBALS['pgr_test_inline_styles']   = array();
$GLOBALS['pgr_test_style_states']    = array();
$GLOBALS['pgr_test_locale']          = 'en_US';
$GLOBALS['pgr_test_is_rtl']          = false;
$GLOBALS['pgr_test_timezone']        = 'UTC';

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['pgr_test_actions'][ $hook ][ $priority ][] = array( $callback, $accepted_args ); }
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['pgr_test_filters'][ $hook ][ $priority ][] = array( $callback, $accepted_args ); }
function apply_filters( $hook, $value, ...$args ) {
	$callbacks = $GLOBALS['pgr_test_filters'][ $hook ] ?? array();
	ksort( $callbacks, SORT_NUMERIC );
	foreach ( $callbacks as $entries ) {
		foreach ( $entries as $entry ) {
			$value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $args ), 0, $entry[1] ) );
		}
	}
	return $value;
}
function sanitize_option( $option, $value ) { unset( $option ); return $value; }
function do_action( $hook, ...$args ) { foreach ( $GLOBALS['pgr_test_actions'][ $hook ] ?? array() as $callbacks ) { foreach ( $callbacks as $item ) { call_user_func_array( $item[0], array_slice( $args, 0, $item[1] ) ); } } }
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
function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
function absint( $value ) { return abs( (int) $value ); }
function rgar( $array, $key, $default = null ) { return is_array( $array ) && array_key_exists( $key, $array ) ? $array[ $key ] : $default; }
function get_option( $key, $default = false ) { return $GLOBALS['pgr_test_options'][ $key ] ?? $default; }
function add_option( $key, $value, $deprecated = '', $autoload = null ) { unset( $deprecated ); if ( array_key_exists( $key, $GLOBALS['pgr_test_options'] ) ) { return false; } $GLOBALS['pgr_test_options'][ $key ] = $value; $GLOBALS['pgr_test_option_autoload'][ $key ] = $autoload; return true; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['pgr_test_options'][ $key ] = $value; $GLOBALS['pgr_test_option_autoload'][ $key ] = $autoload; return true; }
function register_setting() {}
function add_settings_section() {}
function add_settings_field() {}
function add_options_page() {}
function checked( $checked, $current = true, $echo = true ) { $result = $checked == $current ? 'checked="checked"' : ''; if ( $echo ) { echo $result; } return $result; }
function settings_fields() {}
function do_settings_sections() {}
function submit_button() {}
function wp_enqueue_script( $handle ) { $GLOBALS['pgr_test_enqueued'][] = $handle; }
function wp_enqueue_style( $handle ) { $GLOBALS['pgr_test_styles'][] = $handle; }
function wp_add_inline_script( $handle, $data, $position = 'after' ) { $GLOBALS['pgr_test_inline_scripts'][] = array( 'handle' => $handle, 'data' => $data, 'position' => $position ); return true; }
function wp_add_inline_style( $handle, $data ) { $GLOBALS['pgr_test_inline_styles'][] = array( 'handle' => $handle, 'data' => $data ); return true; }
function wp_style_is( $handle, $status = 'enqueued' ) { return ! empty( $GLOBALS['pgr_test_style_states'][ $handle ][ $status ] ); }
function determine_locale() { return $GLOBALS['pgr_test_locale']; }
function is_rtl() { return (bool) $GLOBALS['pgr_test_is_rtl']; }
function wp_timezone() { return new DateTimeZone( $GLOBALS['pgr_test_timezone'] ); }
function delete_option( $key ) { unset( $GLOBALS['pgr_test_options'][ $key ], $GLOBALS['pgr_test_option_autoload'][ $key ] ); }

/**
 * Isolated SQL CAS double. The real WPDB/MariaDB behavior MUST also be
 * exercised by WU008; this double only protects decision-level regressions.
 */
class PGR_Test_Scanner_WPDB {
	public $options = 'wp_options';
	public $last_error = '';
	public $prepared = array();

	public function prepare( $sql, ...$args ) {
		$this->prepared = array( 'sql' => $sql, 'args' => $args );
		return serialize( $this->prepared );
	}
	public function get_row( $query ) {
		$statement = unserialize( $query );
		$key = $statement['args'][0] ?? '';
		if ( ! array_key_exists( $key, $GLOBALS['pgr_test_options'] ) ) {
			return null;
		}
		return (object) array( 'option_value' => maybe_serialize( $GLOBALS['pgr_test_options'][ $key ] ) );
	}
	public function insert( $table, $fields, $formats ) {
		unset( $table, $formats );
		if ( ! empty( $GLOBALS['pgr_test_fail_write'] ) ||
			array_key_exists( $fields['option_name'], $GLOBALS['pgr_test_options'] ) ) {
			return false;
		}
		$GLOBALS['pgr_test_options'][ $fields['option_name'] ] = maybe_unserialize( $fields['option_value'] );
		$GLOBALS['pgr_test_option_autoload'][ $fields['option_name'] ] = false;
		return 1;
	}
	public function query( $query ) {
		$statement = unserialize( $query );
		if ( ! empty( $GLOBALS['pgr_test_interleave'] ) ) {
			$interleave = $GLOBALS['pgr_test_interleave'];
			unset( $GLOBALS['pgr_test_interleave'] );
			$interleave();
		}
		if ( ! empty( $GLOBALS['pgr_test_fail_write'] ) ) {
			return false;
		}
		list( $next, $key, $expected ) = $statement['args'];
		if ( ! array_key_exists( $key, $GLOBALS['pgr_test_options'] ) ||
			maybe_serialize( $GLOBALS['pgr_test_options'][ $key ] ) !== $expected ) {
			return 0;
		}
		$GLOBALS['pgr_test_options'][ $key ] = maybe_unserialize( $next );
		return 1;
	}
}
function maybe_serialize( $value ) { return is_array( $value ) || is_object( $value ) ? serialize( $value ) : $value; }
function maybe_unserialize( $value ) { $decoded = @unserialize( $value ); return false === $decoded ? $value : $decoded; }
function wp_cache_delete( $key, $group = '' ) { unset( $GLOBALS['pgr_test_cache'][ $group ][ $key ] ); return true; }
