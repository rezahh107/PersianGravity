<?php
/**
 * Explicit human-readable Jalali Merge Tags for Gravity Forms.
 *
 * Native merge tags and all persistence remain under Gravity Forms ownership.
 *
 * @package PersianGravityForms
 */
defined( 'ABSPATH' ) || exit;

final class PGR_GF_Explicit_Jalali_Merge_Tags {

	/** Register only the documented post-native custom-tag replacement seam. */
	public function hooks(): void {
		add_filter( 'gform_replace_merge_tags', array( __CLASS__, 'replace' ), 10, 7 );
	}

	/**
	 * Replace only the deliberately namespaced tags. No second native render.
	 *
	 * @param mixed $text       Rendered Merge Tag text.
	 * @param mixed $form       The host form.
	 * @param mixed $entry      The host entry.
	 * @param bool  $url_encode The host requested URL encoding.
	 * @param bool  $esc_html   The host requested HTML escaping.
	 * @param bool  $nl2br      The host requested newline formatting.
	 * @param string $format    Host output format.
	 * @return mixed
	 */
	public static function replace( $text, $form, $entry, $url_encode, $esc_html, $nl2br, $format ) {
		unset( $nl2br );
		if (
			! is_string( $text ) || false === strpos( $text, '{pgr_jdate_' ) ||
			! is_array( $form ) || ! is_array( $entry ) ||
			'fa_IR' !== get_locale() ||
			! class_exists( 'PGR_Jalali_Presentation', false )
		) {
			return $text;
		}

		return preg_replace_callback(
			'/\{pgr_jdate_(created|updated|payment|today)(?:_(date|time))?\}|\{pgr_jdate_field_([1-9][0-9]*)\}/',
			static function ( $match ) use ( $form, $entry, $url_encode, $esc_html, $format ) {
				$value = isset( $match[3] ) && '' !== $match[3]
					? self::date_field( $form, $entry, (int) $match[3] )
					: self::system_date( $entry, $match[1], $match[2] ?? '' );

				// Null means unsafe/unavailable: leave the explicit token visible.
				if ( null === $value ) {
					return $match[0];
				}
				if ( $url_encode || 'url' === $format ) {
					return urlencode( $value );
				}
				return $esc_html ? esc_html( $value ) : $value;
			},
			$text
		);
	}

	/**
	 * Convert an authoritative system instant, using the existing G-008 facade.
	 */
	private static function system_date( array $entry, string $source, string $part ): ?string {
		if ( 'today' === $source ) {
			$instant = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		} else {
			$key = array(
				'created' => 'date_created',
				'updated' => 'date_updated',
				'payment' => 'payment_date',
			)[ $source ] ?? '';
			$raw = $entry[ $key ] ?? null;
			if ( ( null === $raw || '' === $raw ) && 'payment' === $source ) {
				return '';
			}
			if ( ! is_string( $raw ) ) {
				return null;
			}
			$instant = self::parse_utc( $raw );
			if ( null === $instant ) {
				return null;
			}
		}

		$presented = PGR_Jalali_Presentation::format_datetime( $instant );
		if ( null === $presented ) {
			return null;
		}
		$pieces = explode( '، ', $presented, 2 );
		if ( 'date' === $part ) {
			return $pieces[0];
		}
		if ( 'time' === $part ) {
			return $pieces[1] ?? null;
		}
		return $presented;
	}

	/**
	 * Read a persisted native Gregorian Date field; never infer from text shape.
	 */
	private static function date_field( array $form, array $entry, int $id ): ?string {
		foreach ( (array) ( $form['fields'] ?? array() ) as $field ) {
			if ( ! $field instanceof GF_Field_Date || (int) $field->id !== $id ) {
				continue;
			}
			$raw = $entry[ (string) $id ] ?? null;
			if ( ! is_string( $raw ) || '' === $raw ) {
				return null;
			}
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- GF Date property.
			$parts = GFCommon::parse_date( $raw, $field->dateFormat );
			if ( ! is_array( $parts ) || ! isset( $parts['year'], $parts['month'], $parts['day'] ) ) {
				return null;
			}
			$year = (int) $parts['year'];
			$month = (int) $parts['month'];
			$day = (int) $parts['day'];
			return checkdate( $month, $day, $year )
				? PGR_Jalali_Presentation::format_date( $year, $month, $day )
				: null;
		}
		return null;
	}

	/** Parse only strict UTC Gravity Forms Entry/system timestamps. */
	private static function parse_utc( string $raw ): ?DateTimeImmutable {
		$instant = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $raw, new DateTimeZone( 'UTC' ) );
		$errors  = DateTimeImmutable::getLastErrors();
		if (
			false === $instant ||
			( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) ||
			$instant->format( 'Y-m-d H:i:s' ) !== $raw
		) {
			return null;
		}
		return $instant;
	}
}
