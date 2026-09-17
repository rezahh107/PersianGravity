<?php
/**
 * Runtime localization registry: established products plus the G-007 bounded family.
 *
 * @package PersianGravity
 */

defined( 'ABSPATH' ) || exit;

return array_merge(
	require __DIR__ . '/products.php',
	require __DIR__ . '/g007-products.php'
);
