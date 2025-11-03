<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( class_exists( 'GF_Field' ) && ! class_exists( 'PGR_GF_Field_National_ID' ) ) :

class PGR_GF_Field_National_ID extends GF_Field {

    public $type = 'pgr_national_id';

    public function get_form_editor_field_title() {
        return esc_attr__( 'National ID (Iran)', 'persian-gravityforms-refactor' );
    }

    public function get_form_editor_button() {
        return array(
            'group' => 'advanced_fields',
            'text'  => $this->get_form_editor_field_title(),
        );
    }

    public function get_form_editor_field_settings() {
        // We intentionally do NOT include any "separator" setting (removed per requirements).
        return array(
            'label_setting',
            'description_setting',
            'placeholder_setting',
            'css_class_setting',
            'conditional_logic_field_setting',
            'error_message_setting',
            'visibility_setting',
        );
    }

    public function is_conditional_logic_supported() {
        return true;
    }

    public function get_field_input( $form, $value = '', $entry = null ) {

        $id        = (int) $this->id;
        $form_id   = (int) $form['id'];
        $is_admin  = is_admin();
        $input_id  = $this->is_form_editor() || $this->is_entry_detail() ? "input_$id" : "input_{$form_id}_{$id}";

        $value        = esc_attr( $value );
        $placeholder  = esc_attr( $this->placeholder );
        $tabindex     = $this->get_tabindex();
        $required     = $this->isRequired ? 'required' : '';

        // Use data attributes to control behavior; separator feature is deliberately removed.
        $forceEnglish   = 'true'; // default is on; may be overridden later if you add per-field props
        $liveValidation = 'true';

        $input = sprintf(
            '<input name="input_%1$d" id="%2$s" type="text" class="medium" value="%3$s" placeholder="%4$s" %5$s %6$s inputmode="numeric" pattern="\d*" data-pgr-force-english="%7$s" data-pgr-live-validation="%8$s" aria-describedby="%2$s_desc" />',
            $id,
            esc_attr( $input_id ),
            $value,
            $placeholder,
            $tabindex,
            $required,
            esc_attr( $forceEnglish ),
            esc_attr( $liveValidation )
        );
        $desc = '<small id="'. esc_attr( $input_id ) .'_desc" class="pgr-hint">'. esc_html__( 'Enter the 10-digit National ID without dashes.', 'persian-gravityforms-refactor' ) .'</small>';

        return sprintf( "<div class='ginput_container ginput_container_text'>%s%s</div>", $input, $desc );
    }

    public function validate( $value, $form ) {
        if ( ! class_exists( 'PGR_Utils' ) ) { return; }
        $v = is_array( $value ) ? rgget( 0, $value ) : $value;
        $v = PGR_Utils::normalize_digits( (string) $v );
        $v = preg_replace( '/\D+/', '', $v );

        if ( $v === '' ) {
            if ( $this->isRequired ) {
                $this->failed_validation  = true;
                $this->validation_message = $this->errorMessage ? $this->errorMessage : esc_html__( 'This field is required.', 'gravityforms' );
            }
            return;
        }

        if ( ! PGR_Utils::is_valid_iran_national_id( $v ) ) {
            $this->failed_validation  = true;
            $this->validation_message = $this->errorMessage ? $this->errorMessage : esc_html__( 'Please enter a valid Iranian National ID.', 'persian-gravityforms-refactor' );
            return;
        }
    }
}

endif;
