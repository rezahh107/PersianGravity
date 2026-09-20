<?php
/**
 * Typed Jalali presentation facade for authoritative Gregorian/system dates.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Jalali_Presentation {

	/** jalaali-js documents Intl agreement for Gregorian years 1800..2256. */
	public const REFERENCE_CROSSCHECK_MIN = '1800-01-01';
	public const REFERENCE_CROSSCHECK_MAX = '2256-12-31';

	/**
	 * Product-supported range proven by the repository's current oracle stack.
	 *
	 * ICU 77.1 first diverges from the Borkowski/jalaali-js lineage at
	 * Gregorian 2124-03-20, so V1 fails native after the preceding day.
	 */
	public const VALIDATED_PRODUCT_MIN = '1800-01-01';
	public const VALIDATED_PRODUCT_MAX = '2124-03-19';

	/**
	 * Format an authoritative instant/datetime as Persian-facing Jalali output.
	 *
	 * The source DateTimeInterface carries its explicit source timezone. The
	 * instant is first moved to the target/site timezone; only then is the local
	 * Gregorian civil date converted. Local time-of-day is preserved.
	 *
	 * Null means the caller must retain its native presentation.
	 */
	public static function format_datetime(
		DateTimeInterface $source,
		?DateTimeZone $target_timezone = null
	): ?string {
		if ( null === $target_timezone ) {
			if ( ! function_exists( 'wp_timezone' ) ) {
				return null;
			}
			$target_timezone = wp_timezone();
			if ( ! $target_timezone instanceof DateTimeZone ) {
				return null;
			}
		}

		try {
			$local = DateTimeImmutable::createFromInterface( $source )->setTimezone( $target_timezone );
		} catch ( Throwable $exception ) {
			unset( $exception );
			return null;
		}

		$date = self::format_date(
			(int) $local->format( 'Y' ),
			(int) $local->format( 'n' ),
			(int) $local->format( 'j' )
		);
		if ( null === $date ) {
			return null;
		}

		return $date . '، ' . self::to_persian_digits( $local->format( 'H:i' ) );
	}

	/**
	 * Format a known Gregorian civil date without timezone shifting it.
	 *
	 * Null means the caller must retain its native presentation.
	 */
	public static function format_date( int $year, int $month, int $day ): ?string {
		if (
			! checkdate( $month, $day, $year ) ||
			! self::is_in_validated_product_range( $year, $month, $day )
		) {
			return null;
		}

		$jalali = PGR_Gregorian_Jalali_Converter::to_jalali( $year, $month, $day );
		if ( null === $jalali ) {
			return null;
		}

		return self::to_persian_digits(
			sprintf( '%04d/%02d/%02d', $jalali['year'], $jalali['month'], $jalali['day'] )
		);
	}

	/**
	 * Determine whether a Gregorian civil date is inside V1's evidence-backed range.
	 */
	public static function is_in_validated_product_range( int $year, int $month, int $day ): bool {
		if ( ! checkdate( $month, $day, $year ) ) {
			return false;
		}

		$key = sprintf( '%04d-%02d-%02d', $year, $month, $day );
		return $key >= self::VALIDATED_PRODUCT_MIN && $key <= self::VALIDATED_PRODUCT_MAX;
	}

	/**
	 * Convert ASCII digits to Persian digits for the bounded numeric profile.
	 */
	private static function to_persian_digits( string $value ): string {
		return strtr(
			$value,
			array(
				'0' => '۰',
				'1' => '۱',
				'2' => '۲',
				'3' => '۳',
				'4' => '۴',
				'5' => '۵',
				'6' => '۶',
				'7' => '۷',
				'8' => '۸',
				'9' => '۹',
			)
		);
	}
}
