<?php
/**
 * One disposable generalized G-011 post-native method candidate.
 *
 * Uses accepted production G-011 classification and presentation rules
 * through ReflectionMethod, without changing production or shared filters.
 * Native expected values are derived from typed authoritative source values;
 * GFCommon::format_date is permitted (not a confirmation render/merge pass).
 *
 * Nothing is inserted in templates, no second confirmation rendering, no
 * GFCommon::replace_variables and no fixed anchor widths/counts/labels/IDs.
 */
defined( 'ABSPATH' ) || exit;
final class WU008_G011_Heterogeneous_Alignment {
	public static function align( $native, $form, $entry ): array {
		$fail = static function ( string $reason ) use ( $native ): array {
			return array( 'output' => $native, 'admitted' => false, 'reason' => $reason, 'spans' => array() );
		};
		if ( ! is_string( $native ) || ! is_array( $form ) || ! is_array( $entry ) ||
			'message' !== ( $form['confirmation']['type'] ?? null ) ||
			! is_string( $form['confirmation']['message'] ?? null ) ) {
			return $fail( 'not_selected_text' );
		}
		$template = $form['confirmation']['message'];
		if ( ! preg_match_all( '/\{[^{}]+\}/u', $template, $matches, PREG_OFFSET_CAPTURE ) ) {
			return $fail( 'no_tokens' );
		}
		// Production-tested source grammar and context classification reused.
		$adapter = 'PGR_GF_Confirmation_Jalali_Adapter';
		$visible = new ReflectionMethod( $adapter, 'visible_text_ranges' );
		$inside = new ReflectionMethod( $adapter, 'containing_text_range' );
		$opaque = new ReflectionMethod( $adapter, 'opaque_text_token' );
		$present = new ReflectionMethod( $adapter, 'present_tag' );
		$ranges = $visible->invoke( null, $template );
		if ( ! is_array( $ranges ) ) {
			return $fail( 'malformed_source_html' );
		}
		$tokens = $matches[0];
		$spans = array();
		$previous_end = 0;
		foreach ( $tokens as $index => $occurrence ) {
			$tag = $occurrence[0];
			$offset = $occurrence[1];
			$length = strlen( $tag );
			$range = $inside->invoke( null, $offset, $length, $ranges );
			if ( null === $range ||
				$opaque->invoke( null, $template, $offset, $length, $range ) ) {
				continue;
			}
			$jalali = $present->invoke( null, $tag, $form, $entry );
			if ( ! is_string( $jalali ) ) {
				continue; // Native-only/unsupported token: no transformation.
			}
			$expected_native = self::native_expected( $tag, $form, $entry );
			if ( ! is_string( $expected_native ) || '' === $expected_native ) {
				return $fail( 'supported_tag_native_representation_unproven' );
			}
			// Exact maximal static substrings bounded by adjacent source
			// tokens. Not fixed-length windows; fail closed on transformations
			// of these substrings by GF or another plugin.
			$left_boundary = $index === 0 ? 0 :
				$tokens[ $index - 1 ][1] + strlen( $tokens[ $index - 1 ][0] );
			$right_boundary = isset( $tokens[ $index + 1 ] ) ?
				$tokens[ $index + 1 ][1] : strlen( $template );
			$left = substr( $template, $left_boundary, $offset - $left_boundary );
			$right = substr( $template, $offset + $length,
				$right_boundary - $offset - $length );
			if ( '' === $left || '' === $right ||
				substr_count( $native, $left ) !== 1 ||
				substr_count( $native, $right ) !== 1 ) {
				return $fail( 'missing_or_nonunique_static_boundaries' );
			}
			$start = strpos( $native, $left ) + strlen( $left );
			$end = strpos( $native, $right );
			if ( $end < $start || substr( $native, $start, $end - $start ) !== $expected_native ) {
				return $fail( 'native_value_mismatch_or_crossing' );
			}
			if ( $start < $previous_end || $end <= $start ) {
				return $fail( 'reordered_or_overlapping_spans' );
			}
			$spans[] = array(
				'source_offset' => $offset, 'tag' => $tag,
				'start' => $start, 'end' => $end,
				'native' => $expected_native, 'jalali' => $jalali,
			);
			$previous_end = $end;
		}
		if ( ! $spans ) {
			return $fail( 'no_admitted_occurrence' );
		}
		$output = '';
		$cursor = 0;
		foreach ( $spans as $span ) {
			$output .= substr( $native, $cursor, $span['start'] - $cursor ) .
				esc_html( $span['jalali'] );
			$cursor = $span['end'];
		}
		$output .= substr( $native, $cursor );
		return array(
			'output' => $output, 'admitted' => true,
			'reason' => 'typed_source_unique_static_alignment',
			'spans' => $spans,
		);
	}

	private static function native_expected( string $tag, array $form, array $entry ): ?string {
		if ( preg_match( '/^\{(date_created|date_updated|payment_date|today)(?::(.*))?\}$/s',
			$tag, $match ) ) {
			$name = $match[1];
			$modifier = $match[2] ?? '';
			// This bounded discriminator covers two actual accepted native
			// representations. Other formats do not become wrong guesses.
			if ( ! in_array( $modifier, array( 'format:Y/m/d', 'time:format:Y/m/d' ), true ) ) {
				return null;
			}
			if ( 'today' === $name ) {
				$utc = new DateTimeImmutable( '@' . time() );
			} else {
				$raw = $entry[$name] ?? null;
				if ( ! is_string( $raw ) ) {
					return null;
				}
				$utc = DateTimeImmutable::createFromFormat(
					'!Y-m-d H:i:s', $raw, new DateTimeZone( 'UTC' )
				);
				$errors = DateTimeImmutable::getLastErrors();
				if ( false === $utc || $utc->format( 'Y-m-d H:i:s' ) !== $raw ||
					( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) ) {
					return null;
				}
			}
			if ( 'format:Y/m/d' === $modifier ) {
				return $utc->setTimezone( wp_timezone() )->format( 'Y/m/d' );
			}
			// Host-native formatting primitive used already by existing
			// G-011's :time presentation (NOT a merge-tag/re-render call).
			return GFCommon::format_date(
				gmdate( 'Y-m-d H:i:s', $utc->getTimestamp() ), false, 'Y/m/d', true
			);
		}
		if ( ! preg_match( '/^\{[^{}]*:(\d+):(?:year|month|day)\}$/', $tag, $match ) ) {
			return null;
		}
		$id = (int) $match[1];
		$modifier = substr( $tag, strrpos( $tag, ':' ) + 1, -1 );
		foreach ( (array) ( $form['fields'] ?? array() ) as $field ) {
			if ( ! $field instanceof GF_Field_Date || (int) $field->id !== $id ) {
				continue;
			}
			$raw = $entry[(string) $id] ?? null;
			if ( ! is_string( $raw ) ) {
				return null;
			}
			$parts = GFCommon::parse_date( $raw, $field->dateFormat ?: 'mdy' );
			if ( ! is_array( $parts ) ||
				! ctype_digit( (string) ( $parts[$modifier] ?? '' ) ) ) {
				return null;
			}
			$year = (int) ( $parts['year'] ?? 0 );
			$month = (int) ( $parts['month'] ?? 0 );
			$day = (int) ( $parts['day'] ?? 0 );
			if ( ! checkdate( $month, $day, $year ) ) {
				return null;
			}
			return 'year' === $modifier ?
				sprintf( '%04d', $year ) : sprintf( '%02d', (int) $parts[$modifier] );
		}
		return null;
	}
}
