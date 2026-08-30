<?php
/**
 * Dedicated Gravity Forms field for Jalali (Persian) calendar dates.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_GF_Field_Jalali_Date extends GF_Field {

	/** @var string */
	public $type = 'pgr_jalali_date';

	/** @var string */
	public $jalali_format = PGR_Persian_Date::DEFAULT_FORMAT;

	/**
	 * Register field-owned editor settings.
	 *
	 * @return void
	 */
	public static function register_editor_hooks() {
		add_action( 'gform_field_advanced_settings', array( __CLASS__, 'render_format_setting' ), 10, 2 );
	}

	/**
	 * Field title in the form editor.
	 *
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return esc_attr__( 'Jalali Date', 'persian-gravityforms' );
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
			'conditional_logic_field_setting',
			'error_message_setting',
			'label_placement_setting',
			'admin_label_setting',
			'visibility_setting',
			'pgr_jalali_format_setting',
		);
	}

	/**
	 * Jalali scalar values can participate in conditional logic.
	 *
	 * @return bool
	 */
	public function is_conditional_logic_supported() {
		return true;
	}

	/**
	 * Render the field-owned Jalali format selector.
	 *
	 * @param int $position Settings position.
	 * @param int $form_id  Current form ID.
	 * @return void
	 */
	public static function render_format_setting( $position, $form_id ) {
		unset( $form_id );

		if ( 50 !== $position ) {
			return;
		}
		?>
		<li class="pgr_jalali_format_setting field_setting">
			<label for="pgr_jalali_format" class="section_label">
				<?php esc_html_e( 'Jalali date format', 'persian-gravityforms' ); ?>
			</label>
			<select id="pgr_jalali_format" onchange="SetFieldProperty('jalali_format', this.value);">
				<option value="ymd_slash">YYYY/MM/DD</option>
				<option value="ymd_dash">YYYY-MM-DD</option>
				<option value="ymd_dot">YYYY.MM.DD</option>
				<option value="dmy">DD/MM/YYYY</option>
				<option value="dmy_dash">DD-MM-YYYY</option>
				<option value="dmy_dot">DD.MM.YYYY</option>
				<option value="mdy">MM/DD/YYYY</option>
			</select>
		</li>
		<?php
	}

	/**
	 * Initialize the custom field format setting in the editor.
	 *
	 * @return string
	 */
	public function get_form_editor_inline_script_on_page_render() {
		$default_format = wp_json_encode( PGR_Persian_Date::DEFAULT_FORMAT );

		return "
			function SetDefaultValues_pgr_jalali_date(field) {
				field.label = '" . esc_js( $this->get_form_editor_field_title() ) . "';
				field.jalali_format = {$default_format};
			}
			jQuery(document).on('gform_load_field_settings', function(event, field) {
				if (field.type === 'pgr_jalali_date') {
					jQuery('#pgr_jalali_format').val(field.jalali_format || {$default_format});
				}
			});
		";
	}

	/**
	 * Render a scalar textual Jalali input. No native Date modes are emulated.
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
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Gravity Forms GF_Field API property; external framework identifier cannot be renamed.
		$required    = $this->isRequired ? ' aria-required="true"' : '';
		$invalid     = $this->failed_validation ? ' aria-invalid="true"' : ' aria-invalid="false"';
		$placeholder = $this->get_field_placeholder_attribute();

		return sprintf(
			'<div class="ginput_container ginput_container_pgr_jalali_date"><input name="input_%1$d" id="%2$s" type="text" value="%3$s" class="%4$s" inputmode="numeric" autocomplete="off"%5$s%6$s%7$s %8$s /></div>',
			absint( $this->id ),
			esc_attr( $field_id ),
			esc_attr( $value ),
			esc_attr( trim( 'pgr_jalali_date ' . $this->size ) ),
			$required,
			$invalid,
			$disabled,
			$placeholder
		);
	}

	/**
	 * Perform authoritative Jalali validation. Empty required handling remains Gravity Forms' responsibility.
	 *
	 * @param mixed $value Submitted value.
	 * @param array $form  Current form.
	 * @return void
	 */
	public function validate( $value, $form ) {
		unset( $form );

		if ( null === $value || ( is_string( $value ) && '' === trim( $value ) ) ) {
			return;
		}

		if ( null === PGR_Persian_Date::canonicalize( $value, $this->get_jalali_format() ) ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Gravity Forms GF_Field API property; external framework identifier cannot be renamed.
			$error_message = $this->errorMessage;

			$this->failed_validation  = true;
			$this->validation_message = ! empty( $error_message )
				? $error_message
				: esc_html__( 'Enter a valid Jalali (Persian) date.', 'persian-gravityforms' );
		}
	}

	/**
	 * Persist only canonical ASCII YYYY-MM-DD while retaining Jalali semantics.
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

		$canonical = PGR_Persian_Date::canonicalize( $value, $this->get_jalali_format() );
		return null === $canonical ? '' : $canonical;
	}

	/**
	 * Format canonical Jalali storage for entry detail and {all_fields} output.
	 *
	 * @param mixed  $value    Stored value.
	 * @param string $currency Currency code.
	 * @param bool   $use_text Whether to use choice text.
	 * @param string $format   Output format.
	 * @param string $media    Output media.
	 * @return string
	 */
	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
		unset( $currency, $use_text, $media );

		$display = $this->format_stored_value( $value );
		return 'html' === $format ? esc_html( $display ) : $display;
	}

	/**
	 * Format canonical Jalali storage when this field's merge tag is processed.
	 *
	 * @param mixed  $value      Processed value.
	 * @param string $input_id   Field/input ID.
	 * @param array  $entry      Entry object.
	 * @param array  $form       Form object.
	 * @param string $modifier   Merge tag modifier.
	 * @param mixed  $raw_value  Raw stored value.
	 * @param bool   $url_encode Whether URL encoding was requested.
	 * @param bool   $esc_html   Whether HTML escaping was requested.
	 * @param string $format     Output format.
	 * @param bool   $nl2br      Whether nl2br was requested.
	 * @return string
	 */
	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
		unset( $input_id, $entry, $form, $modifier, $format, $nl2br );

		$source  = is_string( $raw_value ) && '' !== $raw_value ? $raw_value : $value;
		$display = $this->format_stored_value( $source );

		if ( $url_encode ) {
			return rawurlencode( $display );
		}

		return $esc_html ? esc_html( $display ) : $display;
	}

	/**
	 * Format canonical Jalali storage for exports and framework add-ons.
	 *
	 * @param array  $entry    Entry object.
	 * @param string $input_id Input ID.
	 * @param bool   $use_text Whether choice text is requested.
	 * @param bool   $is_csv   Whether this is a CSV export.
	 * @return string
	 */
	public function get_value_export( $entry, $input_id = '', $use_text = false, $is_csv = false ) {
		unset( $use_text, $is_csv );

		$key   = '' === (string) $input_id ? (string) $this->id : (string) $input_id;
		$value = rgar( $entry, $key, '' );
		return $this->format_stored_value( $value );
	}

	/**
	 * Return the field-owned format with a deterministic default.
	 *
	 * @return string
	 */
	private function get_jalali_format() {
		return PGR_Persian_Date::normalize_format( $this->jalali_format );
	}

	/**
	 * Format a stored canonical value without invoking Gregorian date APIs.
	 *
	 * @param mixed $value Stored value.
	 * @return string
	 */
	private function format_stored_value( $value ) {
		$display = PGR_Persian_Date::format_canonical( $value, $this->get_jalali_format() );
		return null === $display ? (string) $value : $display;
	}
}
