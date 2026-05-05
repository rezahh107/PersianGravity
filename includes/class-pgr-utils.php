<?php
/**
 * Utility functions for Persian Gravity Forms.
 *
 * @package PersianGravityFormsRefactor
 * @since   3.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PGR_Utils
 * 
 * Provides helper methods for digit normalization and Iranian National ID validation.
 */
class PGR_Utils {

	/**
	 * Mapping of Persian and Arabic digits to English digits.
	 *
	 * @var array<string,string>
	 */
	private static $digit_map = array(
		'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
		'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
		'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
	);

	/**
	 * Cache for validated national IDs (per request).
	 *
	 * @var array<string,bool>
	 */
	private static $valid_nid_cache = array();

	/**
	 * Normalize Persian/Arabic digits to English digits.
	 *
	 * @since 3.0.0
	 *
	 * @param mixed $str Input string or any other type.
	 * @return mixed String with normalized digits, or original non-string value.
	 */
	public static function normalize_digits( $str ) {
		if ( ! is_string( $str ) ) {
			return $str;
		}
		// Use strtr for better performance than str_replace with arrays.
		return strtr( $str, self::$digit_map );
	}

	/**
	 * Validate an Iranian National ID (کد ملی) – 10 digits with mod 11 checksum.
	 * Results are cached per request to avoid redundant calculations.
	 *
	 * @since 3.0.0
	 *
	 * @param string $nid National ID string.
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid_iran_national_id( string $nid ): bool {
		// Normalize and remove non-digits
		$normalized = self::normalize_digits( $nid );
		$clean_nid = preg_replace( '/\D+/', '', $normalized );

		// Check cache
		if ( isset( self::$valid_nid_cache[ $clean_nid ] ) ) {
			return self::$valid_nid_cache[ $clean_nid ];
		}

		// Length must be exactly 10
		if ( strlen( $clean_nid ) !== 10 ) {
			self::$valid_nid_cache[ $clean_nid ] = false;
			return false;
		}

		// Reject all identical digits (e.g., 1111111111, 0000000000)
		if ( preg_match( '/^(\d)\1{9}$/', $clean_nid ) ) {
			self::$valid_nid_cache[ $clean_nid ] = false;
			return false;
		}

		// Algorithm: sum of each digit multiplied by weight (10 down to 2)
		$check_digit = (int) substr( $clean_nid, 9, 1 );
		$sum = 0;
		for ( $i = 0; $i < 9; $i++ ) {
			$sum += (int) $clean_nid[ $i ] * ( 10 - $i );
		}
		$remainder = $sum % 11;
		$calculated = ( $remainder < 2 ) ? $remainder : 11 - $remainder;

		$is_valid = ( $calculated === $check_digit );
		self::$valid_nid_cache[ $clean_nid ] = $is_valid;
		return $is_valid;
	}

	/**
	 * Format a National ID with dashes (XXX-XXXXXX-X).
	 * Returns original if not 10 digits.
	 *
	 * @since 3.0.0
	 *
	 * @param string $nid National ID (digits only or already formatted).
	 * @return string Formatted ID or unchanged if invalid length.
	 */
	public static function format_national_id( string $nid ): string {
		$clean = preg_replace( '/\D+/', '', $nid );
		if ( strlen( $clean ) !== 10 ) {
			return $nid;
		}
		return substr( $clean, 0, 3 ) . '-' . substr( $clean, 3, 6 ) . '-' . substr( $clean, 9, 1 );
	}

	/**
	 * Clear validation cache (useful for testing).
	 *
	 * @since 3.0.0
	 */
	public static function clear_cache(): void {
		self::$valid_nid_cache = array();
	}
}
