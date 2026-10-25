<?php
/**
 * G-011: confirmation-exclusive Jalali presentation for native date merge tags.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_GF_Confirmation_Jalali_Adapter {

	/** Register only the post-selection, post-replacement confirmation seam. */
	public function hooks(): void {
		add_filter( 'gform_confirmation', array( __CLASS__, 'filter_confirmation' ), 20, 4 );
	}

	/**
	 * Reconstruct only a selected Text confirmation. Page/Redirect query
	 * strings are intentionally native until their transport is qualified.
	 *
	 * The original GF rendering remains authoritative. We accept the
	 * reconstructed rendering only if restoring its original date merge-tag
	 * values yields byte-for-byte the original confirmation (including HTML).
	 * This protects unrelated tags, formatting and host extensions.
	 *
	 * @param mixed $confirmation Native rendered confirmation.
	 * @param mixed $form         Selected form/confirmation.
	 * @param mixed $entry        Native entry.
	 * @param mixed $ajax         Native AJAX flag.
	 * @return mixed
	 */
	public static function filter_confirmation( $confirmation, $form, $entry, $ajax ) {
		unset( $ajax );

		if (
			! is_string( $confirmation ) ||
			! is_array( $form ) ||
			! is_array( $entry ) ||
			! PGR_Module_Registry::is_enabled( 'jalali_presentation' ) ||
			'fa_IR' !== determine_locale() ||
			! class_exists( 'PGR_Jalali_Presentation', false ) ||
			! class_exists( 'GFFormDisplay', false ) ||
			! class_exists( 'GFCommon', false ) ||
			'message' !== rgar( $form['confirmation'] ?? array(), 'type' )
		) {
			return $confirmation;
		}

		$selected = $form['confirmation'];
		$template = $selected['message'] ?? null;
		if ( ! is_string( $template ) || false === strpos( $template, '{' ) ) {
			return $confirmation;
		}

		preg_match_all( '/\{[^{}]+\}/u', $template, $matches );
		$tags         = array_unique( $matches[0] );
		$replacements = array();
		$native       = array();
		$marker_index = 0;
		$nl2br        = ! rgar( $selected, 'disableAutoformat' );

		foreach ( $tags as $tag ) {
			$jalali = self::present_tag( $tag, $form, $entry );
			if ( null === $jalali ) {
				continue;
			}

			// Native replacement, escaping and filters remain the oracle.
			$native_value = GFCommon::replace_variables( $tag, $form, $entry, false, true, $nl2br, 'html' );
			if ( ! is_string( $native_value ) || $native_value === $tag ) {
				continue;
			}

			++$marker_index;
			$marker               = 'PGRG011_' . str_replace( '-', '', wp_generate_uuid4() ) . '_' . $marker_index . '_END';
			$replacements[ $tag ] = $marker;
			$native[ $marker ]    = $native_value;
		}

		if ( empty( $replacements ) ) {
			return $confirmation;
		}

		$selected['message'] = strtr( $template, $replacements );
		$probe               = GFFormDisplay::get_confirmation_message( $selected, $form, $entry );
		if ( ! is_string( $probe ) || strtr( $probe, $native ) !== $confirmation ) {
			return $confirmation;
		}

		$presented = array();
		foreach ( $replacements as $tag => $marker ) {
			$value = self::present_tag( $tag, $form, $entry );
			if ( null === $value ) {
				return $confirmation;
			}
			$presented[ $marker ] = esc_html( $value );
		}

		return strtr( $probe, $presented );
	}

	/** Translate only recognized system and native Gregorian Date-field tags. */
	private static function present_tag( string $tag, array $form, array $entry ): ?string {
		if ( preg_match( '/^\{(date_created|date_updated|payment_date|today)(?::(.*))?\}$/s', $tag, $match ) ) {
			return self::present_system_date( $match[1], $match[2] ?? '', $entry );
		}

		if ( ! preg_match( '/^\{[^{}]*:(\d+)(?::([^{}]+))?\}$/s', $tag, $match ) ) {
			return null;
		}

		$id       = (int) $match[1];
		$modifier = $match[2] ?? '';
		$field    = null;
		foreach ( (array) rgar( $form, 'fields', array() ) as $candidate ) {
			if ( $candidate instanceof GF_Field_Date && (int) $candidate->id === $id ) {
				$field = $candidate;
				break;
			}
		}

		if ( null === $field || ! isset( $entry[ (string) $id ] ) || ! is_string( $entry[ (string) $id ] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Gravity Forms public Date-field property.
		$input_format = empty( $field->dateFormat ) ? 'mdy' : $field->dateFormat;
		$parts        = GFCommon::parse_date( $entry[ (string) $id ], $input_format );
		if (
			! is_array( $parts ) ||
			! ctype_digit( (string) ( $parts['year'] ?? '' ) ) ||
			! ctype_digit( (string) ( $parts['month'] ?? '' ) ) ||
			! ctype_digit( (string) ( $parts['day'] ?? '' ) )
		) {
			return null;
		}
		$date = PGR_Jalali_Presentation::format_date(
			(int) $parts['year'],
			(int) $parts['month'],
			(int) $parts['day']
		);
		if ( null === $date ) {
			return null;
		}

		$allowed = array( 'year', 'month', 'day', 'ymd', 'ymd_dash', 'ymd_dot', 'ymd_slash', 'mdy', 'mdy_dash', 'mdy_dot', 'mdy_slash', 'dmy', 'dmy_dash', 'dmy_dot', 'dmy_slash' );
		if ( '' !== $modifier && ! in_array( $modifier, $allowed, true ) ) {
			return null;
		}
		$output_format = '' === $modifier ? $input_format : $modifier;
		if ( ! in_array( $output_format, $allowed, true ) ) {
			return null;
		}

		$jalali = explode( '/', $date );
		if ( 3 !== count( $jalali ) ) {
			return null;
		}
		list( $year, $month, $day ) = $jalali;
		if ( 'year' === $output_format ) {
			return $year;
		}
		if ( 'month' === $output_format ) {
			return $month;
		}
		if ( 'day' === $output_format ) {
			return $day;
		}

		$separator = str_ends_with( $output_format, '_dash' ) ? '-' : ( str_ends_with( $output_format, '_dot' ) ? '.' : '/' );
		$order     = substr( $output_format, 0, 3 );
		if ( 'ymd' === $order ) {
			return implode( $separator, array( $year, $month, $day ) );
		}
		if ( 'dmy' === $order ) {
			return implode( $separator, array( $day, $month, $year ) );
		}
		return implode( $separator, array( $month, $day, $year ) );
	}

	/** Interpret the exact system-date modifier grammar from GF 3.1.1.1. */
	private static function present_system_date( string $name, string $modifiers, array $entry ): ?string {
		$segments      = explode( ':', str_replace( '\:', '__PGR_COLON__', $modifiers ) );
		$format        = null;
		$time          = false;
		$segment_count = count( $segments );
		for ( $i = 0; $i < $segment_count; ++$i ) {
			$part = $segments[ $i ];
			if ( '' === $part && 0 === $i ) {
				continue;
			}
			if ( 'time' === $part ) {
				$time = true;
				continue;
			}
			if ( 'format' === $part && isset( $segments[ $i + 1 ] ) ) {
				$format = str_replace( '__PGR_COLON__', ':', $segments[ ++$i ] );
				continue;
			}
			return null; // human/diff/raw/timestamp/urlencode/unknown: native.
		}

		if ( 'today' === $name ) {
			$source = new DateTimeImmutable( '@' . time() );
		} else {
			$raw = $entry[ $name ] ?? null;
			if ( ! is_string( $raw ) || '' === $raw ) {
				return null;
			}
			$source = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $raw, new DateTimeZone( 'UTC' ) );
			$errors = DateTimeImmutable::getLastErrors();
			if (
				false === $source ||
				( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) ||
				$source->format( 'Y-m-d H:i:s' ) !== $raw
			) {
				return null;
			}
		}

		$local = $source->setTimezone( wp_timezone() );
		$date  = PGR_Jalali_Presentation::format_date( (int) $local->format( 'Y' ), (int) $local->format( 'n' ), (int) $local->format( 'j' ) );
		if ( null === $date ) {
			return null;
		}

		$date_format = null === $format || '' === $format ? GFCommon::get_default_date_format() : $format;
		$presented   = self::format_calendar( $date, $date_format, $source->getTimestamp() );
		if ( null === $presented ) {
			return null;
		}

		if ( $time ) {
			// Preserve the native translated connector and time without owning
			// another product's translation domain.
			$native_local_date = date_i18n( $date_format, GFCommon::get_local_timestamp( $source->getTimestamp() ), true );
			$native_full       = GFCommon::format_date( gmdate( 'Y-m-d H:i:s', $source->getTimestamp() ), false, $date_format, true );
			if (
				! is_string( $native_full ) ||
				'' === $native_local_date ||
				1 !== substr_count( $native_full, $native_local_date )
			) {
				return null;
			}
			$presented = str_replace( $native_local_date, $presented, $native_full );
		}
		return $presented;
	}

	/**
	 * Format calendrical tokens from G-008; delegate time and weekday tokens
	 * to native WP formatting. Unsupported calendar-dependent tokens fall back.
	 */
	private static function format_calendar( string $date, string $format, int $timestamp ): ?string {
		$parts = explode(
			'/',
			strtr(
				$date,
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
				)
			)
		);
		if ( 3 !== count( $parts ) ) {
			return null;
		}
		list( $year, $month, $day ) = array_map( 'intval', $parts );
		$months   = array( 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' );
		$calendar = array(
			'Y' => sprintf( '%04d', $year ),
			'y' => sprintf( '%02d', $year % 100 ),
			'm' => sprintf( '%02d', $month ),
			'n' => (string) $month,
			'd' => sprintf( '%02d', $day ),
			'j' => (string) $day,
			'F' => $months[ $month - 1 ],
		);
		$result  = '';
		$escaped = false;
		foreach ( str_split( $format ) as $character ) {
			if ( $escaped ) {
				$result .= $character;
				$escaped = false;
				continue;
			}
			if ( '\\' === $character ) {
				$escaped = true;
				continue;
			}
			if ( isset( $calendar[ $character ] ) ) {
				$result .= $calendar[ $character ];
				continue;
			}
			if ( false !== strpos( 'aABgGhHisueIOPTZNwDl', $character ) ) {
				$result .= date_i18n( $character, GFCommon::get_local_timestamp( $timestamp ), true );
				continue;
			}
			if ( ctype_alpha( $character ) ) {
				return null; // e.g. Gregorian week/year, month abbreviations.
			}
			$result .= $character;
		}
		if ( $escaped ) {
			return null;
		}
		return strtr(
			$result,
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
