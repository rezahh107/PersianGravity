<?php
/**
 * Gravity Forms validation for Iranian National ID.
 *
 * @package PersianGravityFormsRefactor
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds validation for fields marked with CSS class `pgr-national-id`.
 */
class PGR_National_ID {

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	public function hooks() : void {
		add_filter( 'gform_field_validation', array( $this, 'validate_field' ), 10, 4 );
	}

	/**
	 * Validate a Gravity Forms field.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $result Validation result.
	 * @param string $value  Field value.
	 * @param array  $form   Form object.
	 * @param object $field  Field object.
	 * @return array
	 */
	public function validate_field( $result, $value, $form, $field ) {
		if ( empty( $field->cssClass ) || false === strpos( (string) $field->cssClass, 'pgr-national-id' ) ) {
			return $result;
		}
		$value = sanitize_text_field( (string) $value );
		if ( '' === $value ) {
			return $result;
		}
		if ( ! PGR_Utils::is_valid_iran_national_id( $value ) ) {
			$result['is_valid'] = false;
			$result['message']  = esc_html__( 'Invalid Iranian national ID.', 'persian-gravityforms-refactor' );
		}
		return $result;
	}
}
