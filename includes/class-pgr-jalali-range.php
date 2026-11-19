<?php
/**
 * Optional earliest/latest constraints for the dedicated Jalali Date field.
 *
 * Bounds are Jalali calendar dates. An administrator may enter an absolute
 * Jalali date (YYYY/MM/DD or YYYY-MM-DD), a relative expression such as
 * `today`, `today+7d`, `today-1m` or `today-18y`, or a named preset
 * (`preset:adult_birthdate`). Relative expressions are resolved against the
 * current Jalali date of the site.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Jalali_Range {

	/**
	 * Sanity floor applied when a field has no explicit earliest date, so that
	 * obvious typos such as 0140/01/01 are caught before submission.
	 */
	const DEFAULT_MIN = '1300-01-01';

	/**
	 * Resolved bounds are reused for a day to keep form rendering cheap.
	 */
	const CACHE_TTL = 86400;

	/**
	 * Loaded preset map.
	 *
	 * @var array|null
	 */
	private static $presets = null;

	/**
	 * Return today's Jalali date as canonical ASCII YYYY-MM-DD.
	 *
	 * @return string
	 */
	public static function today() {
		$override = apply_filters( 'pgr_jalali_today', null );
		if ( is_string( $override ) && false !== PGR_Persian_Date::parse_canonical( $override ) ) {
			return $override;
		}

		$parts = PGR_Gregorian_Jalali_Converter::to_jalali( (int) gmdate( 'Y' ), (int) gmdate( 'n' ), (int) gmdate( 'j' ) );
		if ( null === $parts ) {
			return '';
		}

		return self::canonical_from_parts( $parts );
	}

	/**
	 * Resolve an administrator bound expression to canonical Jalali storage.
	 * An empty string means "no bound".
	 *
	 * @param mixed $expression Bound expression.
	 * @return string
	 */
	public static function resolve_bound( $expression ) {
		$expression = is_string( $expression ) ? trim( PGR_Utils::normalize_digits( $expression ) ) : '';

		if ( '' === $expression ) {
			return '';
		}

		if ( 0 === strpos( $expression, 'preset:' ) ) {
			$name    = substr( $expression, 7 );
			$presets = self::presets();
			return isset( $presets[ $name ] ) && is_string( $presets[ $name ] ) ? self::resolve_bound( $presets[ $name ] ) : '';
		}

		if ( preg_match( '/^today(?:([+-])(\d{1,3})([dmy]))?$/', $expression, $matches ) ) {
			return self::resolve_relative( $matches );
		}

		foreach ( array( 'ymd_slash', 'ymd_dash' ) as $format ) {
			$canonical = PGR_Persian_Date::canonicalize( $expression, $format );
			if ( is_string( $canonical ) && '' !== $canonical ) {
				return $canonical;
			}
		}

		return '';
	}

	/**
	 * Return the resolved bounds rendered for one field.
	 *
	 * @param int    $form_id Form ID.
	 * @param object $field   Jalali Date field.
	 * @return array{min:string,max:string}
	 */
	public static function bounds_for_field( $form_id, $field ) {
		$key    = 'pgr_jalali_range_' . absint( $form_id );
		$cached = get_transient( $key );
		if ( is_array( $cached ) && isset( $cached['min'], $cached['max'] ) ) {
			return $cached;
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Gravity Forms field property persisted by the editor.
		$min_expression = isset( $field->jalaliMin ) ? (string) $field->jalaliMin : '';
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Gravity Forms field property persisted by the editor.
		$max_expression = isset( $field->jalaliMax ) ? (string) $field->jalaliMax : '';

		$bounds = array(
			'min' => self::resolve_bound( '' !== $min_expression ? $min_expression : self::DEFAULT_MIN ),
			'max' => self::resolve_bound( $max_expression ),
		);

		set_transient( $key, $bounds, self::CACHE_TTL );

		return $bounds;
	}

	/**
	 * Return whether a canonical Jalali value is inside inclusive bounds.
	 * Canonical values are zero-padded, so string order is calendar order.
	 *
	 * @param string $canonical Canonical Jalali value.
	 * @param string $min       Canonical earliest date or empty.
	 * @param string $max       Canonical latest date or empty.
	 * @return bool
	 */
	public static function is_within( $canonical, $min, $max ) {
		if ( '' !== $min && $canonical < $min ) {
			return false;
		}

		if ( '' !== $max && $canonical >= $max ) {
			return false;
		}

		return true;
	}

	/**
	 * Return the named relative presets shipped with the plugin.
	 *
	 * @return array
	 */
	public static function presets() {
		if ( null !== self::$presets ) {
			return self::$presets;
		}

		self::$presets = array();
		$path          = PGR_PATH . 'tools/jalali/range-presets.json';

		if ( ! is_readable( $path ) ) {
			return self::$presets;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local plugin-owned JSON.
		$decoded = json_decode( (string) file_get_contents( $path ), true );
		if ( is_array( $decoded ) && isset( $decoded['presets'] ) && is_array( $decoded['presets'] ) ) {
			self::$presets = $decoded['presets'];
		}

		return self::$presets;
	}

	/**
	 * Apply a relative offset to today's Jalali date.
	 *
	 * @param array $matches Regex matches: sign, amount, unit.
	 * @return string
	 */
	private static function resolve_relative( array $matches ) {
		$today = PGR_Persian_Date::parse_canonical( self::today() );
		if ( false === $today ) {
			return '';
		}

		if ( empty( $matches[1] ) ) {
			return self::canonical_from_parts( $today );
		}

		$amount = (int) $matches[2] * ( '-' === $matches[1] ? -1 : 1 );

		if ( 'd' === $matches[3] ) {
			$gregorian = PGR_Gregorian_Jalali_Converter::to_gregorian( $today['year'], $today['month'], $today['day'] );
			if ( null === $gregorian ) {
				return '';
			}

			$date = new DateTimeImmutable( sprintf( '%04d-%02d-%02d', $gregorian['year'], $gregorian['month'], $gregorian['day'] ) );
			$date = $date->modify( sprintf( '%+d day', $amount ) );
			$back = PGR_Gregorian_Jalali_Converter::to_jalali( (int) $date->format( 'Y' ), (int) $date->format( 'n' ), (int) $date->format( 'j' ) );

			return null === $back ? '' : self::canonical_from_parts( $back );
		}

		$year  = $today['year'];
		$month = $today['month'];

		if ( 'm' === $matches[3] ) {
			$index = ( $year * 12 + ( $month - 1 ) ) + $amount;
			$year  = intdiv( $index, 12 );
			$month = ( $index % 12 ) + 1;
		} else {
			$year += $amount;
		}

		$candidate = sprintf( '%04d-%02d-%02d', $year, $month, $today['day'] );
		return false === PGR_Persian_Date::parse_canonical( $candidate ) ? '' : $candidate;
	}

	/**
	 * Build canonical storage from converter parts.
	 *
	 * @param array $parts Jalali year/month/day.
	 * @return string
	 */
	private static function canonical_from_parts( array $parts ) {
		return sprintf( '%04d-%02d-%02d', $parts['year'], $parts['month'], $parts['day'] );
	}
}
