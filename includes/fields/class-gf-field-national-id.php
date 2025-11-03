<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'GFForms' ) ) {
    return;
}

class GF_Field_National_ID extends GF_Field {

    public $type = 'pgr_national_id';

    // PHP 8.2+: avoid dynamic properties
    public $showSeparator  = false;
    public $forceEnglish   = false;

    public $notDigitError  = '';
    public $qtyDigitError  = '';
    public $isInvalidError = '';

    // Privacy toggles
    public $noStore        = false;
    public $maskOnExport   = false;
    public $hashValue      = false;

    public function get_form_editor_button() {
        return array(
            'group' => 'advanced_fields',
            'text'  => esc_html__( 'کد ملی', 'persian-gravityforms-refactor' ),
        );
    }

    public function get_form_editor_field_title() {
        return esc_html__( 'کد ملی', 'persian-gravityforms-refactor' );
    }

    public function get_form_editor_field_settings() {
        return array(
            'conditional_logic_field_setting',
            'prepopulate_field_setting',
            'error_message_setting',
            'label_setting',
            'label_placement_setting',
            'admin_label_setting',
            'size_setting',
            'rules_setting',
            'visibility_setting',
            'duplicate_setting',
            'default_value_setting',
            'placeholder_setting',
            'description_setting',
            'css_class_setting',
        );
    }

    public function get_form_editor_inline_script_on_page_render() {
        // Keep this lean; admin JS adds UI and binds SetFieldProperty(...)
        return "jQuery(document).on('gform_load_field_settings', function(e, field){ if(field.type!=='pgr_national_id')return; });";
    }

    public function validate( $value, $form ) {
        if ( rgblank( $value ) ) {
            return;
        }
        if ( method_exists( 'PGR_Utils', 'normalize_digits' ) ) {
            $value = PGR_Utils::normalize_digits( $value );
        }
        $value = preg_replace( '/[^0-9]/', '', (string) $value );
        $value = (string) apply_filters( 'pgr_national_id_normalize_value', $value, $form, $this );

        $is_valid = false;
        if ( method_exists( 'PGR_Utils', 'is_valid_iran_national_id' ) ) {
            $is_valid = (bool) PGR_Utils::is_valid_iran_national_id( $value );
        }
        $is_valid = (bool) apply_filters( 'pgr_national_id_is_valid', $is_valid, $value, $form, $this );

        if ( ! $is_valid ) {
            $this->failed_validation = true;
            $message = rgar( $this, 'isInvalidError' );
            if ( rgblank( $message ) ) {
                $message = esc_html__( 'کد ملی وارد شده معتبر نیست.', 'persian-gravityforms-refactor' );
            }
            $this->validation_message = $message;
        }
    }

    public function get_field_input( $form, $value = '', $entry = null ) {
        $id              = absint( $this->id );
        $form_id         = absint( rgar( $form, 'id' ) );
        $is_entry_detail = $this->is_entry_detail();
        $is_form_editor  = $this->is_form_editor();

        $input_id   = $is_entry_detail || $is_form_editor ? "input_{$id}" : "input_{$form_id}_{$id}";
        $tabindex   = $this->get_tabindex();
        $placeholder = $this->get_field_placeholder_attribute();

        $required = $this->isRequired ? 'aria-required="true"' : '';
        $invalid  = $this->failed_validation ? 'aria-invalid="true"' : 'aria-invalid="false"';
        $disabled = $is_form_editor ? 'disabled="disabled"' : '';

        $css_class = esc_attr( trim( $this->size . ' gfield_pgr_national_id' ) );

        $value = esc_attr( $value );

        // HTML constraint pattern & maxlength (with/without separator)
        $pattern   = $this->showSeparator ? 'pattern="^(\d{10}|\d{3}-\d{6}-\d{1})$"' : 'pattern="^\d{10}$"';
        $maxlength = $this->showSeparator ? 'maxlength="12"' : 'maxlength="10"';

        $attrs = array(
            'type="text"',
            'name="input_' . $id . '"',
            'id="' . esc_attr( $input_id ) . '"',
            'value="' . $value . '"',
            'class="' . $css_class . '"',
            $tabindex,
            $placeholder,
            $required,
            $invalid,
            'inputmode="numeric"',
            $pattern,
            $maxlength,
            'data-field-type="pgr_national_id"',
            'data-show-separator="' . ( $this->showSeparator ? '1' : '0' ) . '"',
            'data-force-english="' . ( $this->forceEnglish ? '1' : '0' ) . '"',
            'aria-describedby="gfield_description_' . $form_id . '_' . $id . '"',
            $disabled,
        );
        $attrs = array_filter( $attrs );
        $input = '<input ' . implode( ' ', $attrs ) . ' />';

        return '<div class="ginput_container ginput_container_text ginput_container_pgr_national_id">' . $input . '</div>';
    }
}
