<?php
/**
 * Bounded on-demand module usage inspection for safe disable operations.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Files.LineLength.TooLong

final class PGR_Module_Usage {

	const UNUSED  = 'UNUSED';
	const USED    = 'USED';
	const UNKNOWN = 'UNKNOWN';

	/**
	 * Inspect current form configuration for one module.
	 *
	 * @param string $module_id Module ID.
	 * @return array{status:string,count:int}
	 */
	public static function inspect( $module_id ) {
		if ( ! PGR_Module_Registry::exists( $module_id ) ) {
			return array(
				'status' => self::UNKNOWN,
				'count'  => 0,
			);
		}

		if ( 'iranian_currency' === $module_id ) {
			return array(
				'status' => self::UNKNOWN,
				'count'  => 0,
			);
		}

		if ( ! class_exists( 'GFAPI' ) || ! method_exists( 'GFAPI', 'get_forms' ) ) {
			return array(
				'status' => self::UNKNOWN,
				'count'  => 0,
			);
		}

		try {
			$forms = GFAPI::get_forms( null, false );
		} catch ( Throwable $exception ) {
			unset( $exception );
			return array(
				'status' => self::UNKNOWN,
				'count'  => 0,
			);
		}

		if ( ! is_array( $forms ) ) {
			return array(
				'status' => self::UNKNOWN,
				'count'  => 0,
			);
		}

		$count         = 0;
		$indeterminate = false;
		foreach ( $forms as $form ) {
			$usage = self::form_uses_module( $form, $module_id );
			if ( self::USED === $usage ) {
				++$count;
			} elseif ( self::UNKNOWN === $usage ) {
				$indeterminate = true;
			}
		}

		return array(
			'status' => $count > 0 ? self::USED : ( $indeterminate ? self::UNKNOWN : self::UNUSED ),
			'count'  => $count,
		);
	}

	/**
	 * Determine whether one form configuration uses a module.
	 *
	 * @param mixed  $form      Gravity Forms form meta.
	 * @param string $module_id Module ID.
	 * @return string USED, UNUSED or UNKNOWN.
	 */
	private static function form_uses_module( $form, $module_id ) {
		if ( ! is_array( $form ) ) {
			return self::UNKNOWN;
		}

		if ( 'jalali_presentation' === $module_id ) {
			// Form metadata contains saved Confirmation/Notification/field text.
			// Broad prefix is deliberate: unresolved or future dedicated tags
			// also block an unsafe disable; no Entry/SQL scan is needed.
			$metadata = wp_json_encode( $form );
			if ( ! is_string( $metadata ) ) {
				return self::UNKNOWN;
			}
			if ( false !== strpos( $metadata, '{pgr_jdate_' ) ) {
				return self::USED;
			}
		}

		if ( 'digit_normalization' === $module_id && ! empty( $form['pgr_normalize_digits'] ) ) {
			return self::USED;
		}

		$types = array(
			'national_id'        => 'pgr_national_id',
			'jalali_date'        => 'pgr_jalali_date',
			'structured_scanner' => 'pgr_structured_scanner',
		);

		$fields        = $form['fields'] ?? null;
		$indeterminate = ! is_array( $fields );
		foreach ( is_array( $fields ) ? $fields : array() as $field ) {
			$type = is_object( $field ) ? ( $field->type ?? null ) : ( is_array( $field ) ? ( $field['type'] ?? null ) : null );
			if ( ! is_string( $type ) || '' === $type ) {
				$indeterminate = true;
				continue;
			}

			if ( isset( $types[ $module_id ] ) && $types[ $module_id ] === $type ) {
				return self::USED;
			}

			if ( 'iranian_address' === $module_id && 'address' === $type ) {
				if ( is_object( $field ) ) {
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- addressType is defined by the Gravity Forms public field API.
					$address_type = $field->addressType ?? '';
				} elseif ( is_array( $field ) ) {
					$address_type = $field['addressType'] ?? '';
				} else {
					$address_type = '';
				}

				if ( ! is_string( $address_type ) ) {
					$indeterminate = true;
					continue;
				}
				if ( 'iran' === $address_type ) {
					return self::USED;
				}
			}
		}

		// GFAPI::get_form() supplies an ID and a normalized fields array.
		// Missing identity or unusable field metadata cannot prove non-use.
		$id = $form['id'] ?? null;
		if ( ! is_int( $id ) && ! ( is_string( $id ) && ctype_digit( $id ) ) ) {
			$indeterminate = true;
		}
		if ( ! $indeterminate && (int) $id < 1 ) {
			$indeterminate = true;
		}
		return $indeterminate ? self::UNKNOWN : self::UNUSED;
	}
}
