<?php
/**
 * Utility helpers.
 *
 * @package PersianGravityFormsRefactor
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Utility functions container.
 */
class PGR_Utils {

	/**
	 * Sanitize boolean-like values.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public static function sanitize_bool( $value ) : bool {
		return (bool) filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Sanitize array of text values.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $arr Array or scalar.
	 * @return array
	 */
	public static function sanitize_text_array( $arr ) : array {
		$arr = is_array( $arr ) ? $arr : array( $arr );
		return array_map( 'sanitize_text_field', $arr );
	}

	/**
	 * Replace Arabic/Persian digits with ASCII digits.
	 *
	 * @since 1.0.0
	 *
	 * @param string $str Input string.
	 * @return string
	 */
	public static function normalize_digits( $str ) : string {
		$mapping = array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
			'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
			'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		);
		return strtr( (string) $str, $mapping );
	}

	/**
	 * Validates Iranian National ID (کد ملی).
	 *
	 * @since 1.0.0
	 *
	 * @param string $nid National ID.
	 * @return bool
	 */
	public static function is_valid_iran_national_id( $nid ) : bool {
		$nid = self::normalize_digits( preg_replace( '/\D+/', '', (string) $nid ) );
		if ( 10 !== strlen( $nid ) ) {
			return false;
		}
		if ( preg_match( '/^(\d)\1{9}$/', $nid ) ) {
			return false; // all digits same.
		}
		$sum = 0;
		for ( $i = 0; $i <= 8; $i++ ) {
			$sum += intval( $nid[ $i ], 10 ) * ( 10 - $i );
		}
		$rem = $sum % 11;
		$check = intval( $nid[9], 10 );
		return ( ( $rem < 2 ) && ( $check === $rem ) ) || ( ( $rem >= 2 ) && ( $check === ( 11 - $rem ) ) );
	}
}
