<?php
/**
 * G-011: native Gravity Forms Text Confirmation date presentation.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

/**
 * This class deliberately does not modify the global merge-tag resolver.
 * It decorates only tokens inside GFFormDisplay::get_confirmation_message(),
 * lets Gravity Forms resolve them natively, then replaces those exact spans
 * at that same confirmation's final display filter.
 */
final class PGR_GF_Confirmation_Date_Adapter {

	/** @var array<string, array<string, mixed>> */
	private $pending = array();

	/** Register only the two exact-host display-seam callbacks. */
	public function hooks(): void {
		add_filter( 'gform_pre_replace_merge_tags', array( $this, 'mark_confirmation_tokens' ), 10, 3 );
		add_filter( 'gform_confirmation', array( $this, 'present_confirmation' ), 10, 4 );
	}

	/**
	 * Decorate safe date tokens only when the direct caller is the selected
	 * Text Confirmation message renderer. The same GF hook is global elsewhere.
	 *
	 * @param mixed $text  Source text before native replacement.
	 * @param mixed $form  Active form.
	 * @param mixed $entry Authoritative entry.
	 * @return mixed
	 */
	public function mark_confirmation_tokens( $text, $form, $entry ) {
		if ( ! $this->is_applicable() || ! is_string( $text ) || ! is_array( $form ) || ! is_array( $entry ) ) {
			return $text;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Required to confine GF's global seam to its exact confirmation caller.
		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 6 );
		if (
			! isset( $trace[3], $trace[4] ) ||
			'GFCommon' !== ( $trace[3]['class'] ?? '' ) ||
			'replace_variables' !== ( $trace[3]['function'] ?? '' ) ||
			'GFFormDisplay' !== ( $trace[4]['class'] ?? '' ) ||
			'get_confirmation_message' !== ( $trace[4]['function'] ?? '' )
		) {
			return $text;
		}

		// Do not reinterpret JavaScript, CSS, HTML attribute values or URLs.
		if ( false !== stripos( $text, '<script' ) || false !== stripos( $text, '<style' ) || false !== stripos( $text, '<textarea' ) ) {
			return $text;
		}
		$segments = preg_split( '/(<[^>]*>)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $segments ) || implode( '', $segments ) !== $text ) {
			return $text;
		}

		foreach ( $segments as $index => $segment ) {
			if ( str_starts_with( $segment, '<' ) ) {
				continue;
			}
			$segments[ $index ] = preg_replace_callback(
				'/\{(?:date_created|date_updated|payment_date|today)(?::[^{}]*)?\}|\{[^{}:]+:[0-9]+(?::[^{}]*)?\}/u',
				function ( $matched ) use ( $form, $entry, $segment ) {
					$tag    = $matched[0];
					$offset = strpos( $segment, $tag );
					if ( false !== $offset ) {
						$before = substr( $segment, 0, $offset );
						// Bare URLs and transport-like assignments are not display text.
						if ( preg_match( '~https?://[^\s]*$~u', $before ) || preg_match( '/[?&][A-Za-z0-9_-]+=$/', $before ) ) {
							return $tag;
						}
					}
					$spec = $this->supported_tag( $tag, $form, $entry );
					if ( null === $spec ) {
						return $tag;
					}
					try {
						$id = bin2hex( random_bytes( 12 ) );
					} catch ( Throwable $exception ) {
						unset( $exception );
						return $tag;
					}
					$start = 'PGRG011S' . $id . 'X';
					$end   = 'PGRG011E' . $id . 'X';
					if ( false !== strpos( $segment, $start ) || false !== strpos( $segment, $end ) ) {
						return $tag;
					}
					$this->pending[ $id ] = array(
						'start' => $start,
						'end'   => $end,
						'spec'  => $spec,
						'form'  => (int) ( $form['id'] ?? 0 ),
						'entry' => (int) ( $entry['id'] ?? 0 ),
					);
					return $start . $tag . $end;
				},
				$segment
			);
			if ( ! is_string( $segments[ $index ] ) ) {
				return $text;
			}
		}

		return implode( '', $segments );
	}

	/**
	 * Replace only the marked spans actually resolved by native GF, retaining
	 * its own escaping and unrelated HTML. Leave unsupported values native.
	 *
	 * @param mixed $confirmation Rendered confirmation string or URL descriptor.
	 * @param mixed $form         Form metadata.
	 * @param mixed $entry        Entry data.
	 * @param mixed $ajax         AJAX flag.
	 * @return mixed
	 */
	public function present_confirmation( $confirmation, $form, $entry, $ajax ) {
		unset( $ajax );
		$pending       = $this->pending;
		$this->pending = array();

		if ( ! $this->is_applicable() || ! is_string( $confirmation ) || ! is_array( $form ) || ! is_array( $entry ) ) {
			return $confirmation;
		}

		foreach ( $pending as $marker ) {
			$start = $marker['start'];
			$end   = $marker['end'];
			$match = '/' . preg_quote( $start, '/' ) . '(.*?)' . preg_quote( $end, '/' ) . '/us';

			$confirmation = preg_replace_callback(
				$match,
				function ( $m ) use ( $marker, $form, $entry ) {
					$native = $m[1];
					if (
						(int) ( $form['id'] ?? 0 ) !== $marker['form'] ||
						(int) ( $entry['id'] ?? 0 ) !== $marker['entry']
					) {
						return $native;
					}
					$converted = $this->present_value( $marker['spec'], $native, $form, $entry );
					return null === $converted ? $native : $converted;
				},
				$confirmation
			);
			if ( ! is_string( $confirmation ) ) {
				return $confirmation;
			}
			// Fail native if an upstream formatter stripped only one delimiter.
			$confirmation = str_replace( array( $start, $end ), '', $confirmation );
		}
		return $confirmation;
	}

	/** Exact licensed host and Persian-site checks; all other situations fall back. */
	private function is_applicable(): bool {
		return class_exists( 'GFForms', false )
			&& '3.1.1.1' === GFForms::$version
			&& function_exists( 'determine_locale' )
			&& 'fa_IR' === determine_locale()
			&& class_exists( 'PGR_Jalali_Presentation', false )
			&& function_exists( 'wp_timezone' );
	}

	/**
	 * Admit only known native date objects and supported modifiers.
	 *
	 * @return array<string, mixed>|null
	 */
	private function supported_tag( string $tag, array $form, array $entry ): ?array {
		if ( preg_match( '/^\{(date_created|date_updated|payment_date|today)(?::([^{}]*))?\}$/', $tag, $matches ) ) {
			$kind     = $matches[1];
			$modifier = $matches[2] ?? '';
			if ( in_array( $modifier, array( 'raw', 'timestamp', 'human', 'diff', 'urlencode' ), true ) ) {
				return null;
			}
			if ( '' !== $modifier && 'time' !== $modifier && ! str_starts_with( $modifier, 'format:' ) ) {
				return null;
			}
			$raw = 'today' === $kind ? null : ( $entry[ $kind ] ?? null );
			if ( 'today' !== $kind && ( ! is_string( $raw ) || '' === $raw ) ) {
				return null;
			}
			$source = 'today' === $kind ? new DateTimeImmutable( 'now', wp_timezone() ) : $this->parse_utc( $raw );
			return null === $source ? null : array(
				'kind'     => $kind,
				'source'   => $source,
				'modifier' => $modifier,
			);
		}
		if ( ! preg_match( '/^\{[^{}:]+:([0-9]+)(?::([^{}]*))?\}$/u', $tag, $matches ) ) {
			return null;
		}
		$field_id = (int) $matches[1];
		$modifier = $matches[2] ?? '';
		if ( ! in_array( $modifier, array( '', 'year', 'month', 'day', 'ymd', 'dmy' ), true ) ) {
			return null;
		}
		foreach ( (array) ( $form['fields'] ?? array() ) as $field ) {
			if ( ! $field instanceof GF_Field || (int) $field->id !== $field_id || 'date' !== $field->type ) {
				continue;
			}
			$raw = $entry[ (string) $field_id ] ?? null;
			if ( ! is_string( $raw ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
				return null;
			}
			$source = DateTimeImmutable::createFromFormat( '!Y-m-d', $raw, wp_timezone() );
			if ( false === $source || $source->format( 'Y-m-d' ) !== $raw ) {
				return null;
			}
			return array(
				'kind'        => 'date_field',
				'source'      => $source,
				'modifier'    => $modifier,
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- GF native field property.
				'date_format' => (string) ( $field->dateFormat ?? '' ),
			);
		}
		return null;
	}

	private function parse_utc( string $raw ): ?DateTimeImmutable {
		$source = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $raw, new DateTimeZone( 'UTC' ) );
		$errors = DateTimeImmutable::getLastErrors();
		if (
			false === $source ||
			( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ||
			$source->format( 'Y-m-d H:i:s' ) !== $raw
		) {
			return null;
		}
		return $source;
	}

	/** Format only proven date tokens, without affecting native time and escaping. */
	private function present_value( array $spec, string $native, array $form, array $entry ): ?string {
		if ( '' === $native ) {
			return null;
		}
		$local  = $spec['source']->setTimezone( wp_timezone() );
		$jalali = PGR_Jalali_Presentation::format_date(
			(int) $local->format( 'Y' ),
			(int) $local->format( 'm' ),
			(int) $local->format( 'd' )
		);
		if ( null === $jalali ) {
			return null;
		}
		$parts = explode( '/', $jalali );
		if ( 3 !== count( $parts ) ) {
			return null;
		}
		$modifier = $spec['modifier'];
		if ( 'date_field' === $spec['kind'] ) {
			switch ( $modifier ) {
				case 'year':
					return $parts[0];
				case 'month':
					return $parts[1];
				case 'day':
					return $parts[2];
				case 'dmy':
					return $parts[2] . '/' . $parts[1] . '/' . $parts[0];
				case 'ymd':
					return $jalali;
				case '':
					$format = $spec['date_format'];
					if ( 'ymd' === $format ) {
						return $jalali;
					}
					if ( 'dmy' === $format ) {
						return $parts[2] . '/' . $parts[1] . '/' . $parts[0];
					}
					if ( 'mdy' === $format ) {
						return $parts[1] . '/' . $parts[2] . '/' . $parts[0];
					}
			}
			return null;
		}

		if ( '' === $modifier ) {
			return $jalali;
		}
		if ( 'time' === $modifier ) {
			// Keep the *actual native* local-time suffix, not a guessed time format.
			$native_date = GFCommon::replace_variables( '{' . $spec['kind'] . '}', $form, $entry );
			if ( ! is_string( $native_date ) || ! str_starts_with( $native, $native_date ) ) {
				return null;
			}
			return $jalali . substr( $native, strlen( $native_date ) );
		}
		if ( str_starts_with( $modifier, 'format:' ) ) {
			return $this->present_format( substr( $modifier, 7 ), $local, $parts, $native );
		}
		return null;
	}

	/** PHP/WP date-format subset checked against the exact native rendered value. */
	private function present_format( string $format, DateTimeImmutable $local, array $parts, string $native ): ?string {
		if ( '' === $format || strlen( $format ) > 90 || false !== strpos( $format, ':' ) ) {
			return null;
		}
		// Never silently change GF's own formatting/escaping interpretation.
		if ( $native !== $local->format( $format ) ) {
			return null;
		}
		$chars = str_split( $format );
		$map   = array(
			'Y' => $parts[0],
			'y' => implode( '', array_slice( preg_split( '//u', $parts[0], -1, PREG_SPLIT_NO_EMPTY ), -2 ) ),
			'm' => $parts[1],
			'n' => preg_replace( '/^۰(?=.)/u', '', $parts[1] ),
			'd' => $parts[2],
			'j' => preg_replace( '/^۰(?=.)/u', '', $parts[2] ),
		);

		$allowed_time = array( 'H', 'G', 'h', 'g', 'i', 's', 'a', 'A' );
		$output       = '';
		$escaped      = false;
		foreach ( $chars as $char ) {
			if ( $escaped ) {
				$output .= $char;
				$escaped = false;
				continue;
			}
			if ( '\\' === $char ) {
				$escaped = true;
				continue;
			}
			if ( isset( $map[ $char ] ) ) {
				$output .= $map[ $char ];
			} elseif ( in_array( $char, $allowed_time, true ) ) {
				$output .= str_replace( str_split( '0123456789' ), array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ), $local->format( $char ) );
			} elseif ( preg_match( '/^[a-zA-Z]$/', $char ) ) {
				// Textual Gregorian month/day and unsupported tokens: native fallback.
				return null;
			} else {
				$output .= $char;
			}
		}
		return $escaped ? null : $output;
	}
}
