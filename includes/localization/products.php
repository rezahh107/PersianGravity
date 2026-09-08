<?php
/**
 * Explicit product boundary. No executable product callbacks.
 *
 * Target versions are owner-supplied, NOT package inspection claims.
 * Empty script maps deliberately disable unverified JavaScript surfaces.
 *
 * @package PersianGravity
 */

defined( 'ABSPATH' ) || exit;

return array(
	'gravityforms'   => array(
		'product'        => 'gravityforms',
		'target_version' => '3.1.1.1',
		'prefix'         => 'gravityforms',
		'scripts'        => array(),
	),
	'gravityflow'    => array(
		'product'        => 'gravityflow',
		'target_version' => '3.1.0',
		'prefix'         => 'gravityflow',
		'scripts'        => array(),
	),
	'gk-gravityview' => array(
		'product'        => 'gravityview',
		'target_version' => '3.3.4',
		'prefix'         => 'gravityview',
		'scripts'        => array(),
	),
);
