
<?php
/**
 * Custom GF Field for National ID validation and formatting
 *
 * @package PersianGravityFormsRefactor
 * @since   1.0.0
 */
defined( 'ABSPATH' ) || exit;

class GF_Field_National_ID extends GF_Field {
    public $type = 'pgr_national_id';

    public function get_form_editor_button() {
        return [
            'group' => 'advanced_fields',
            'text'  => __('کد ملی', 'persian-gravityforms-refactor')
        ];
    }

    public function get_field_input($form, $value = '', $entry = null) {
        return '<input name="input_' . $this->id . '" type="text" value="' . esc_attr( $value ) . '" class="gf-national-id" />';
    }

    public function validate($value, $form) {
        if ( ! PGR_Utils::is_valid_iran_national_id( $value ) ) {
            return new WP_Error( 'invalid_nid', __('کد ملی معتبر نیست.', 'persian-gravityforms-refactor') );
        }
        return true;
    }

    public function get_field_settings_js() {
        return [
            'showLocation' => 'boolean',
            'showSeparator' => 'boolean',
            'forceEnglish' => 'boolean',
            'notDigitError' => 'string',
            'qtyDigitError' => 'string',
            'isInvalidError' => 'string',
        ];
    }
}

add_action('gform_loaded', function() {
    if ( ! class_exists( 'GF_Fields' ) ) return;
    require_once PGR_PATH . 'includes/fields/class-gf-field-national-id.php';
    GF_Fields::register( new GF_Field_National_ID() );
}, 5);
