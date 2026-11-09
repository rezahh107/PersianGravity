<?php
/**
 * Stable read-only G-014 capability/status seam for optional consumers.
 *
 * Available as soon as PersianGravity is loaded, even when the RTL switch is
 * OFF or Gravity Forms/Gravity Flow is unavailable. This does not observe the
 * browser's actual AG Grid mode and does not grant access to Inbox data.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read PersianGravity's native Gravity Flow Inbox RTL capability and intent.
 *
 * No persistence, filter registration, host initialization or permission
 * bypass occurs here. Only non-sensitive state enums are returned; any
 * user-facing administrative presentation must check manage_options.
 *
 * Contract v1 (stable keys and lowercase values):
 * - capability: supported (this source provides G-014);
 * - requested: on | off | unknown (invalid stored value);
 * - host: admitted | absent | unsupported | unknown;
 * - request_rtl: bool | null (null before WordPress locale is ready);
 * - grid_mode: not_observed (must be checked in the actual browser).
 *
 * Calling before plugins_loaded may return host=unknown, not a guessed absence.
 * The sole supported host is Gravity Flow 3.1.0 with its canonical basename.
 *
 * @return array<string,bool|int|string|null>
 */
function pgr_gravity_flow_inbox_rtl_status() {
	$settings = get_option( 'pgr_settings', array() );

	if ( ! is_array( $settings ) ) {
		$requested = 'unknown';
	} elseif ( ! array_key_exists( 'gravity_flow_inbox_rtl', $settings ) ||
		0 === $settings['gravity_flow_inbox_rtl'] ) {
		$requested = 'off';
	} elseif ( 1 === $settings['gravity_flow_inbox_rtl'] ) {
		$requested = 'on';
	} else {
		$requested = 'unknown';
	}

	$has_version  = defined( 'GRAVITY_FLOW_VERSION' );
	$has_basename = defined( 'GRAVITY_FLOW_PLUGIN_BASENAME' );

	if ( $has_version && $has_basename ) {
		$host = '3.1.0' === (string) constant( 'GRAVITY_FLOW_VERSION' ) &&
			'gravityflow/gravityflow.php' === str_replace( '\\', '/', (string) constant( 'GRAVITY_FLOW_PLUGIN_BASENAME' ) )
			? 'admitted'
			: 'unsupported';
	} elseif ( $has_version || $has_basename ) {
		$host = 'unknown';
	} elseif ( function_exists( 'did_action' ) && did_action( 'plugins_loaded' ) ) {
		$host = 'absent';
	} else {
		$host = 'unknown';
	}

	$request_rtl = function_exists( 'did_action' ) && did_action( 'init' ) && function_exists( 'is_rtl' )
		? (bool) is_rtl()
		: null;

	return array(
		'api_version' => 1,
		'capability'  => 'supported',
		'requested'   => $requested,
		'host'        => $host,
		'request_rtl' => $request_rtl,
		'grid_mode'   => 'not_observed',
	);
}
