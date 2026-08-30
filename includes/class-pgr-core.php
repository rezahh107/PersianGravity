<?php
/**
 * Canonical runtime coordinator for Persian Gravity Forms.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Core {

	/** @var bool */
	private static $initialized = false;

	/**
	 * Initialize all supported plugin capabilities exactly once.
	 *
	 * @return void
	 */
	public static function init() {
		if ( self::$initialized ) {
			return;
		}

		GF_Fields::register( new PGR_GF_Field_National_ID() );
		PGR_GF_Field_National_ID::register_editor_hooks();

		GF_Fields::register( new PGR_GF_Field_Jalali_Date() );
		PGR_GF_Field_Jalali_Date::register_editor_hooks();

		$admin = new PGR_Admin();
		$admin->hooks();

		$address = new PGR_Address();
		$address->hooks();

		$currency = new PGR_Currency();
		$currency->hooks();

		add_filter( 'gform_form_settings_fields', array( __CLASS__, 'form_settings_fields' ), 10, 2 );
		add_filter( 'gform_save_field_value', array( __CLASS__, 'normalize_form_value' ), 10, 5 );
		add_filter( 'gform_value_pre_duplicate_check', array( __CLASS__, 'normalize_duplicate_value' ), 10, 3 );
		add_action( 'gform_enqueue_scripts', array( __CLASS__, 'enqueue_field_assets' ), 10, 2 );

		self::$initialized = true;
	}

	/**
	 * Add the Persian digit-normalization setting using the current Gravity Forms Settings API.
	 *
	 * @param array $fields Form settings fields.
	 * @param array $form   Current form.
	 * @return array
	 */
	public static function form_settings_fields( $fields, $form ) {
		unset( $form );

		if ( ! isset( $fields['form_options']['fields'] ) || ! is_array( $fields['form_options']['fields'] ) ) {
			return $fields;
		}

		$fields['form_options']['fields'][] = array(
			'type'    => 'radio',
			'name'    => 'pgr_normalize_digits',
			'label'   => esc_html__( 'Persian digit normalization', 'persian-gravityforms' ),
			'tooltip' => esc_html__( 'Convert Persian and Arabic digits to ASCII digits before entry values are saved.', 'persian-gravityforms' ),
			'choices' => array(
				array(
					'label' => esc_html__( 'Enabled', 'persian-gravityforms' ),
					'value' => '1',
				),
				array(
					'label' => esc_html__( 'Disabled', 'persian-gravityforms' ),
					'value' => '0',
				),
			),
		);

		return $fields;
	}

	/**
	 * Normalize a saved Gravity Forms value when the form setting is enabled.
	 * Server-side normalization is authoritative; JavaScript is never required.
	 *
	 * @param mixed         $value    Value about to be saved.
	 * @param array         $entry    Current entry.
	 * @param GF_Field|null $field    Current field.
	 * @param array         $form     Current form.
	 * @param mixed         $input_id Current input ID.
	 * @return mixed
	 */
	public static function normalize_form_value( $value, $entry, $field, $form, $input_id ) {
		unset( $entry, $field, $input_id );

		if ( empty( $form['pgr_normalize_digits'] ) || ! is_string( $value ) ) {
			return $value;
		}

		return PGR_Utils::normalize_digits( $value );
	}

	/**
	 * Normalize National ID values before Gravity Forms performs its built-in no-duplicates check.
	 *
	 * @param mixed    $value   Submitted value.
	 * @param GF_Field $field   Current field.
	 * @param int      $form_id Current form ID.
	 * @return mixed
	 */
	public static function normalize_duplicate_value( $value, $field, $form_id ) {
		unset( $form_id );

		if ( ! is_object( $field ) || 'pgr_national_id' !== $field->type || ! is_string( $value ) ) {
			return $value;
		}

		$normalized = PGR_Utils::normalize_national_id( $value );
		return null === $normalized ? PGR_Utils::normalize_digits( $value ) : $normalized;
	}

	/**
	 * Load the small client-side digit normalizer only when a National ID field
	 * explicitly opts into typing-time normalization.
	 *
	 * @param array $form    Current form.
	 * @param bool  $is_ajax Whether AJAX is enabled.
	 * @return void
	 */
	public static function enqueue_field_assets( $form, $is_ajax ) {
		unset( $is_ajax );

		foreach ( (array) rgar( $form, 'fields' ) as $field ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Persisted Gravity Forms custom-field property; renaming would change stored field configuration.
			if ( is_object( $field ) && 'pgr_national_id' === $field->type && ! empty( $field->forceEnglish ) ) {
				wp_enqueue_script(
					'pgr-frontend',
					PGR_URL . 'assets/js/pgr-frontend.js',
					array(),
					PGR_VERSION,
					true
				);
				return;
			}
		}
	}
}
