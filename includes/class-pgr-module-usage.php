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
			return array( 'status' => self::UNKNOWN, 'count' => 0 );
		}

		if ( 'iranian_currency' === $module_id ) {
			return array( 'status' => self::UNKNOWN, 'count' => 0 );
		}

		if ( ! class_exists( 'GFAPI' ) || ! method_exists( 'GFAPI', 'get_forms' ) ) {
			return array( 'status' => self::UNKNOWN, 'count' => 0 );
		}

		try {
			$forms = GFAPI::get_forms( null, false );
		} catch ( Throwable $exception ) {
			unset( $exception );
			return array( 'status' => self::UNKNOWN, 'count' => 0 );
		}

		if ( ! is_array( $forms ) ) {
			return array( 'status' => self::UNKNOWN, 'count' => 0 );
		}

		$count = 0;
		foreach ( $forms as $form ) {
			if ( self::form_uses_module( $form, $module_id ) ) {
				++$count;
			}
		}

		return array(
			'status' => $count > 0 ? self::USED : self::UNUSED,
			'count'  => $count,
		);
	}

	/**
	 * Determine whether one form configuration uses a module.
	 *
	 * @param mixed  $form      Gravity Forms form meta.
	 * @param string $module_id Module ID.
	 * @return bool
	 */
	private static function form_uses_module( $form, $module_id ) {
		if ( ! is_array( $form ) ) {
			return false;
		}

		if ( 'digit_normalization' === $module_id ) {
			return ! empty( $form['pgr_normalize_digits'] );
		}

		$types = array(
			'national_id'        => 'pgr_national_id',
			'jalali_date'        => 'pgr_jalali_date',
			'structured_scanner' => 'pgr_structured_scanner',
		);

		foreach ( (array) rgar( $form, 'fields', array() ) as $field ) {
			$type = is_object( $field ) ? (string) ( $field->type ?? '' ) : ( is_array( $field ) ? (string) ( $field['type'] ?? '' ) : '' );

			if ( isset( $types[ $module_id ] ) && $types[ $module_id ] === $type ) {
				return true;
			}

			if ( 'iranian_address' === $module_id && 'address' === $type ) {
				$address_type = is_object( $field ) ? (string) ( $field->addressType ?? '' ) : ( is_array( $field ) ? (string) ( $field['addressType'] ?? '' ) : '' );
				if ( 'iran' === $address_type ) {
					return true;
				}
			}
		}

		return false;
	}
}
