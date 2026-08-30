<?php
/**
 * Gravity Forms field for Iranian National ID values.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_GF_Field_National_ID extends GF_Field {

	/** @var string */
	public $type = 'pgr_national_id';

	/** @var bool */
	public $forceEnglish = true;

	/**
	 * Register the custom field-editor setting.
	 *
	 * @return void
	 */
	public static function register_editor_hooks() {
		add_action( 'gform_field_advanced_settings', array( __CLASS__, 'render_force_english_setting' ), 10, 2 );
	}

	/**
	 * Field title in the form editor.
	 *
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return esc_attr__( 'Iranian National ID', 'persian-gravityforms' );
	}

	/**
	 * Place the field in Gravity Forms advanced fields.
	 *
	 * @return array
	 */
	public function get_form_editor_button() {
		return array(
			'group' => 'advanced_fields',
			'text'  => $this->get_form_editor_field_title(),
		);
	}

	/**
	 * Supported field settings.
	 *
	 * @return array
	 */
	public function get_form_editor_field_settings() {
		return array(
			'label_setting',
			'description_setting',
			'css_class_setting',
			'placeholder_setting',
			'size_setting',
			'rules_setting',
			'duplicate_setting',
			'conditional_logic_field_setting',
			'error_message_setting',
			'label_placement_setting',
			'admin_label_setting',
			'visibility_setting',
			'pgr_force_english_setting',
		);
	}

	/**
	 * National IDs can participate in conditional logic as scalar text values.
	 *
	 * @return bool
	 */
	public function is_conditional_logic_supported() {
		return true;
	}

	/**
	 * Render the client normalization field setting.
	 *
	 * @param int $position Settings position.
	 * @param int $form_id  Current form ID.
	 * @return void
	 */
	public static function render_force_english_setting( $position, $form_id ) {
		unset( $form_id );

		if ( 50 !== $position ) {
			return;
		}
		?>
		<li class="pgr_force_english_setting field_setting">
			<input type="checkbox" id="pgr_force_english" onclick="SetFieldProperty('forceEnglish', this.checked);" />
			<label for="pgr_force_english" class="inline">
				<?php esc_html_e( 'Normalize Persian/Arabic digits while typing', 'persian-gravityforms' ); ?>
			</label>
		</li>
		<?php
	}

	/**
	 * Add field-editor initialization for defaults and the custom setting.
	 *
	 * @return string
	 */
	public function get_form_editor_inline_script_on_page_render() {
		$settings      = get_option( PGR_Admin::OPTION, array( 'default_force_english' => 1 ) );
		$force_english = empty( $settings['default_force_english'] ) ? 'false' : 'true';
		$label         = wp_json_encode( $this->get_form_editor_field_title() );

		return "
			function SetDefaultValues_pgr_national_id(field) {
				field.label = {$label};
				field.forceEnglish = {$force_english};
			}
			jQuery(document).on('gform_load_field_settings', function(event, field) {
				if (field.type === 'pgr_national_id') {
					jQuery('#pgr_force_english').prop('checked', field.forceEnglish !== false);
				}
			});
		";
	}

	/**
	 * Render an accessible scalar input.
	 *
	 * @param array      $form  Current form.
	 * @param string     $value Current value.
	 * @param array|null $entry Current entry.
	 * @return string
	 */
	public function get_field_input( $form, $value = '', $entry = null ) {
		unset( $entry );

		$form_id         = absint( rgar( $form, 'id' ) );
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();
		$is_admin        = $is_entry_detail || $is_form_editor;
		$field_id        = $is_admin || 0 === $form_id ? 'input_' . absint( $this->id ) : 'input_' . $form_id . '_' . absint( $this->id );
		$disabled        = $is_form_editor ? ' disabled="disabled"' : '';
		$required        = $this->isRequired ? ' aria-required="true"' : '';
		$invalid         = $this->failed_validation ? ' aria-invalid="true"' : ' aria-invalid="false"';
		$force_english   = $this->forceEnglish ? ' data-pgr-normalize-digits="1"' : '';

		return sprintf(
			'<div class="ginput_container ginput_container_pgr_national_id"><input name="input_%1$d" id="%2$s" type="text" value="%3$s" class="%4$s" inputmode="numeric" maxlength="10" autocomplete="off"%5$s%6$s%7$s%8$s /></div>',
			absint( $this->id ),
			esc_attr( $field_id ),
			esc_attr( $value ),
			esc_attr( trim( 'pgr_national_id ' . $this->size ) ),
			$required,
			$invalid,
			$disabled,
			$force_english
		);
	}

	/**
	 * Perform authoritative server-side National ID validation.
	 * Gravity Forms runs required/no-duplicates checks before this method.
	 *
	 * @param mixed $value Submitted value.
	 * @param array $form  Current form.
	 * @return void
	 */
	public function validate( $value, $form ) {
		unset( $form );

		if ( ! PGR_Utils::is_valid_iran_national_id( $value ) ) {
			$this->failed_validation = true;
			$this->validation_message = ! empty( $this->errorMessage )
				? $this->errorMessage
				: esc_html__( 'Enter a valid 10-digit Iranian National ID.', 'persian-gravityforms' );
		}
	}

	/**
	 * Save exactly ten normalized ASCII digits.
	 *
	 * @param mixed  $value      Submitted value.
	 * @param array  $form       Current form.
	 * @param string $input_name Input name.
	 * @param int    $lead_id    Entry ID.
	 * @param array  $lead       Entry object.
	 * @return string
	 */
	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {
		unset( $form, $input_name, $lead_id, $lead );

		$normalized = PGR_Utils::normalize_national_id( $value );
		return null === $normalized ? '' : $normalized;
	}

	/**
	 * Format the stored value for human-readable entry detail output.
	 *
	 * @param mixed  $value    Stored value.
	 * @param string $currency Currency code.
	 * @param bool   $use_text Whether to use choice text.
	 * @param string $format   Output format.
	 * @param string $media    Output media.
	 * @return string
	 */
	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
		unset( $currency, $use_text, $format, $media );

		$normalized = PGR_Utils::normalize_national_id( (string) $value );
		if ( null === $normalized ) {
			return esc_html( (string) $value );
		}

		return esc_html( substr( $normalized, 0, 3 ) . '-' . substr( $normalized, 3, 6 ) . '-' . substr( $normalized, 9, 1 ) );
	}
}
