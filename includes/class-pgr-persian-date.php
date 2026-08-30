<?php
/**
 * Pure Jalali parsing, validation, persistence, and display helpers.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Persian_Date {

	const DEFAULT_FORMAT = 'ymd_slash';

	/**
	 * Return supported Jalali presentation/input format identifiers.
	 *
	 * @return array
	 */
	public static function supported_formats() {
		return array(
			'mdy',
			'dmy',
			'dmy_dash',
			'dmy_dot',
			'ymd_slash',
			'ymd_dash',
			'ymd_dot',
		);
	}

	/**
	 * Normalize an unknown format identifier to a supported value.
	 *
	 * @param mixed $format Format identifier.
	 * @return string
	 */
	public static function normalize_format( $format ) {
		$format = is_string( $format ) ? $format : '';
		return in_array( $format, self::supported_formats(), true ) ? $format : self::DEFAULT_FORMAT;
	}

	/**
	 * Parse a Jalali date according to the dedicated field format.
	 *
	 * @param mixed  $value  User-facing Jalali date.
	 * @param string $format Format identifier.
	 * @return array|false
	 */
	public static function parse_input( $value, $format ) {
		if ( ! is_string( $value ) ) {
			return false;
		}

		$value  = trim( PGR_Utils::normalize_digits( $value ) );
		$format = self::normalize_format( $format );

		if ( '' === $value ) {
			return false;
		}

		$separator = self::separator_for_format( $format );
		$pattern   = '/^(\d{1,4})' . preg_quote( $separator, '/' ) . '(\d{1,2})' . preg_quote( $separator, '/' ) . '(\d{1,4})$/';

		if ( ! preg_match( $pattern, $value, $matches ) ) {
			return false;
		}

		$first  = (int) $matches[1];
		$second = (int) $matches[2];
		$third  = (int) $matches[3];

		if ( 'mdy' === $format ) {
			$parts = array(
				'year'  => $third,
				'month' => $first,
				'day'   => $second,
			);
		} elseif ( 0 === strpos( $format, 'dmy' ) ) {
			$parts = array(
				'year'  => $third,
				'month' => $second,
				'day'   => $first,
			);
		} else {
			$parts = array(
				'year'  => $first,
				'month' => $second,
				'day'   => $third,
			);
		}

		return self::is_valid_date( $parts['year'], $parts['month'], $parts['day'] ) ? $parts : false;
	}

	/**
	 * Convert accepted Jalali input to the sole persisted representation.
	 *
	 * @param mixed  $value  User-facing Jalali date.
	 * @param string $format Format identifier.
	 * @return string|null
	 */
	public static function canonicalize( $value, $format ) {
		if ( null === $value || ( is_string( $value ) && '' === trim( $value ) ) ) {
			return '';
		}

		$parts = self::parse_input( $value, $format );
		if ( false === $parts ) {
			return null;
		}

		return sprintf( '%04d-%02d-%02d', $parts['year'], $parts['month'], $parts['day'] );
	}

	/**
	 * Parse canonical ASCII YYYY-MM-DD Jalali storage without Gregorian interpretation.
	 *
	 * @param mixed $value Stored Jalali value.
	 * @return array|false
	 */
	public static function parse_canonical( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches ) ) {
			return false;
		}

		$parts = array(
			'year'  => (int) $matches[1],
			'month' => (int) $matches[2],
			'day'   => (int) $matches[3],
		);

		return self::is_valid_date( $parts['year'], $parts['month'], $parts['day'] ) ? $parts : false;
	}

	/**
	 * Format canonical Jalali storage for human-readable output.
	 *
	 * @param mixed  $canonical Canonical Jalali value.
	 * @param string $format    Presentation format identifier.
	 * @return string|null
	 */
	public static function format_canonical( $canonical, $format ) {
		$parts = self::parse_canonical( $canonical );
		if ( false === $parts ) {
			return null;
		}

		$format    = self::normalize_format( $format );
		$separator = self::separator_for_format( $format );
		$year      = sprintf( '%04d', $parts['year'] );
		$month     = sprintf( '%02d', $parts['month'] );
		$day       = sprintf( '%02d', $parts['day'] );

		if ( 'mdy' === $format ) {
			return $month . $separator . $day . $separator . $year;
		}

		if ( 0 === strpos( $format, 'dmy' ) ) {
			return $day . $separator . $month . $separator . $year;
		}

		return $year . $separator . $month . $separator . $day;
	}

	/**
	 * Validate a Jalali calendar date.
	 *
	 * @param int $year  Jalali year.
	 * @param int $month Jalali month.
	 * @param int $day   Jalali day.
	 * @return bool
	 */
	public static function is_valid_date( $year, $month, $day ) {
		$year  = (int) $year;
		$month = (int) $month;
		$day   = (int) $day;

		if ( $year < 1 || $month < 1 || $month > 12 || $day < 1 ) {
			return false;
		}

		if ( $month <= 6 ) {
			$max_day = 31;
		} elseif ( $month <= 11 ) {
			$max_day = 30;
		} else {
			$max_day = self::is_leap_year( $year ) ? 30 : 29;
		}

		return $day <= $max_day;
	}

	/**
	 * Return whether a Jalali year is leap under the retained bounded 33-year rule.
	 *
	 * @param int $year Jalali year.
	 * @return bool
	 */
	public static function is_leap_year( $year ) {
		$remainder = (int) $year % 33;
		return ( $remainder % 4 ) - 1 === (int) ( $remainder * 0.05 );
	}

	/**
	 * Return the separator owned by a format identifier.
	 *
	 * @param string $format Normalized format identifier.
	 * @return string
	 */
	private static function separator_for_format( $format ) {
		if ( false !== strpos( $format, '_dash' ) ) {
			return '-';
		}

		if ( false !== strpos( $format, '_dot' ) ) {
			return '.';
		}

		return '/';
	}
}
