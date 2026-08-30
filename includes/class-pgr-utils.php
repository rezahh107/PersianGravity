<?php
/**
 * Pure Persian/Iranian value helpers.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Utils {

	/**
	 * Convert Persian and Arabic-Indic digits to ASCII digits.
	 *
	 * @param mixed $value Input value.
	 * @return mixed
	 */
	public static function normalize_digits( $value ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		return strtr(
			$value,
			array(
				'۰' => '0',
				'۱' => '1',
				'۲' => '2',
				'۳' => '3',
				'۴' => '4',
				'۵' => '5',
				'۶' => '6',
				'۷' => '7',
				'۸' => '8',
				'۹' => '9',
				'٠' => '0',
				'١' => '1',
				'٢' => '2',
				'٣' => '3',
				'٤' => '4',
				'٥' => '5',
				'٦' => '6',
				'٧' => '7',
				'٨' => '8',
				'٩' => '9',
			)
		);
	}

	/**
	 * Normalize a National ID to exactly ten ASCII digits.
	 * Spaces and dashes are accepted as presentation separators; other characters are rejected.
	 *
	 * @param mixed $value Submitted value.
	 * @return string|null
	 */
	public static function normalize_national_id( $value ) {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$value = trim( self::normalize_digits( $value ) );

		if ( '' === $value || ! preg_match( '/^[0-9\s-]+$/', $value ) ) {
			return null;
		}

		$value = preg_replace( '/[\s-]+/', '', $value );

		if ( ! is_string( $value ) || 10 !== strlen( $value ) || ! ctype_digit( $value ) ) {
			return null;
		}

		return $value;
	}

	/**
	 * Validate an Iranian National ID using its Mod-11 checksum.
	 *
	 * @param mixed $value National ID.
	 * @return bool
	 */
	public static function is_valid_iran_national_id( $value ) {
		$code = self::normalize_national_id( $value );

		if ( null === $code || preg_match( '/^(\d)\1{9}$/', $code ) ) {
			return false;
		}

		$sum = 0;
		for ( $index = 0; $index < 9; $index++ ) {
			$sum += (int) $code[ $index ] * ( 10 - $index );
		}

		$remainder = $sum % 11;
		$expected  = $remainder < 2 ? $remainder : 11 - $remainder;

		return $expected === (int) $code[9];
	}
}
