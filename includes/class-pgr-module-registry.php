<?php
/**
 * Bounded source-owned PersianGravity module registry.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Files.LineLength.TooLong

final class PGR_Module_Registry {

	const OPTION         = 'pgr_modules';
	const SCHEMA_VERSION = 1;

	/**
	 * Return the complete bounded product module catalog.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function all() {
		return array(
			'national_id' => array(
				'id'              => 'national_id',
				'default_enabled' => true,
				'label_fa'        => 'کد ملی ایران',
				'label_en'        => 'Iranian National ID',
				'description_fa'  => 'اعتبارسنجی سمت سرور، ذخیره استاندارد و نرمال‌سازی ارقام برای کد ملی ایران.',
				'description_en'  => 'Server-authoritative Iranian National ID validation, canonical storage, and digit normalization.',
				'help_topic'      => 'national-id',
				'field_types'     => array( 'pgr_national_id' ),
			),
			'jalali_date' => array(
				'id'              => 'jalali_date',
				'default_enabled' => true,
				'label_fa'        => 'تاریخ جلالی',
				'label_en'        => 'Jalali Date',
				'description_fa'  => 'فیلد مستقل تاریخ جلالی با اعتبارسنجی سمت سرور و ذخیره استاندارد جلالی.',
				'description_en'  => 'Dedicated Jalali date field with server validation and canonical Jalali storage.',
				'help_topic'      => 'jalali-date',
				'field_types'     => array( 'pgr_jalali_date' ),
			),
			'iranian_address' => array(
				'id'              => 'iranian_address',
				'default_enabled' => true,
				'label_fa'        => 'نشانی ایران',
				'label_en'        => 'Iranian Address',
				'description_fa'  => 'نوع نشانی ایران و فهرست استان‌های ایران برای Gravity Forms.',
				'description_en'  => 'Iranian address type and province choices for Gravity Forms.',
				'help_topic'      => 'iranian-address',
				'field_types'     => array(),
			),
			'digit_normalization' => array(
				'id'              => 'digit_normalization',
				'default_enabled' => true,
				'label_fa'        => 'نرمال‌سازی ارقام',
				'label_en'        => 'Digit Normalization',
				'description_fa'  => 'تبدیل ارقام فارسی و عربی به ASCII پیش از ذخیره مقادیر فرم.',
				'description_en'  => 'Form-level Persian and Arabic digit normalization before entry persistence.',
				'help_topic'      => 'digit-normalization',
				'field_types'     => array(),
			),
			'iranian_currency' => array(
				'id'              => 'iranian_currency',
				'default_enabled' => true,
				'label_fa'        => 'ریال و تومان ایران',
				'label_en'        => 'Iranian Rial / Toman',
				'description_fa'  => 'تعریف ارزهای IRR و IRT با صفر رقم اعشار برای Gravity Forms.',
				'description_en'  => 'IRR and IRT currency definitions with zero decimal places for Gravity Forms.',
				'help_topic'      => 'iranian-currency',
				'field_types'     => array(),
			),
			'structured_scanner' => array(
				'id'              => 'structured_scanner',
				'default_enabled' => true,
				'label_fa'        => 'اسکنر ساختاریافته',
				'label_en'        => 'Structured Scanner',
				'description_fa'  => 'کنترل‌گر موقت اسکنر برای تجزیه ورودی ساختاریافته و نگاشت خروجی به فیلدهای عادی.',
				'description_en'  => 'Transient scanner controller that parses structured input and maps outputs to ordinary fields.',
				'help_topic'      => 'structured-scanner',
				'field_types'     => array( 'pgr_structured_scanner' ),
			),
		);
	}

	/**
	 * Return one module definition.
	 *
	 * @param string $id Module ID.
	 * @return array<string,mixed>|null
	 */
	public static function get( $id ) {
		$modules = self::all();
		return isset( $modules[ $id ] ) ? $modules[ $id ] : null;
	}

	/**
	 * Determine whether a module ID is known.
	 *
	 * @param string $id Module ID.
	 * @return bool
	 */
	public static function exists( $id ) {
		return null !== self::get( $id );
	}

	/**
	 * Return normalized persisted states for every known module.
	 *
	 * @return array<string,bool>
	 */
	public static function get_states() {
		$defaults = self::default_states();
		$stored   = get_option( self::OPTION, null );

		if (
			! is_array( $stored ) ||
			! isset( $stored['schema_version'], $stored['states'] ) ||
			self::SCHEMA_VERSION !== (int) $stored['schema_version'] ||
			! is_array( $stored['states'] )
		) {
			return $defaults;
		}

		$states = $defaults;
		foreach ( $stored['states'] as $id => $enabled ) {
			if ( ! self::exists( (string) $id ) || ! is_bool( $enabled ) ) {
				continue;
			}
			$states[ (string) $id ] = $enabled;
		}

		return $states;
	}

	/**
	 * Return whether a known module is enabled.
	 *
	 * Unknown modules are fail-closed.
	 *
	 * @param string $id Module ID.
	 * @return bool
	 */
	public static function is_enabled( $id ) {
		if ( ! self::exists( $id ) ) {
			return false;
		}
		$states = self::get_states();
		return ! empty( $states[ $id ] );
	}

	/**
	 * Persist one known module state without storing source metadata.
	 *
	 * @param string $id      Module ID.
	 * @param bool   $enabled New state.
	 * @return bool
	 */
	public static function set_enabled( $id, $enabled ) {
		if ( ! self::exists( $id ) ) {
			return false;
		}

		$states        = self::get_states();
		$states[ $id ] = (bool) $enabled;
		$value         = array(
			'schema_version' => self::SCHEMA_VERSION,
			'states'         => $states,
		);

		if ( null === get_option( self::OPTION, null ) ) {
			return (bool) add_option( self::OPTION, $value, '', true );
		}

		return (bool) update_option( self::OPTION, $value, true );
	}

	/**
	 * Source-defined defaults.
	 *
	 * @return array<string,bool>
	 */
	private static function default_states() {
		$states = array();
		foreach ( self::all() as $id => $module ) {
			$states[ $id ] = ! empty( $module['default_enabled'] );
		}
		return $states;
	}
}
