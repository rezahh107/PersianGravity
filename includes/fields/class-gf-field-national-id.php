<?php
/**
 * Gravity Forms Custom Field: Iranian National ID
 *
 * @package PersianGravityFormsRefactor
 * @since   3.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PGR_GF_Field_National_ID
 * 
 * A complete Gravity Forms custom field for Iranian National ID with validation,
 * formatting, optional city display, and front-end live validation.
 */
class PGR_GF_Field_National_ID extends GF_Field {

	/**
	 * Field type identifier.
	 *
	 * @var string
	 */
	public $type = 'pgr_national_id';

	/**
	 * Show city based on national ID (requires cities database).
	 *
	 * @var bool
	 */
	public $showLocation = false;

	/**
	 * Auto-separate digits with dash (XXX-XXXXXX-X).
	 *
	 * @var bool
	 */
	public $showSeperator = false;

	/**
	 * Convert Persian/Arabic digits to English automatically.
	 *
	 * @var bool
	 */
	public $forceEnglish = false;

	/**
	 * Custom error messages.
	 *
	 * @var string
	 */
	public $notDigitError   = '';
	public $qtyDigitError   = '';
	public $isInvalidError  = '';
	public $duplicateError  = '';

	/**
	 * Return the field title in form editor.
	 *
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return esc_html__( 'Iranian National ID', 'persian-gravityforms-refactor' );
	}

	/**
	 * Return the field button data in form editor.
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
	 * Return the settings available in form editor.
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
			'default_value_setting',
			'conditional_logic_field_setting',
			'prepopulate_field_setting',
			'error_message_setting',
			'label_placement_setting',
			'admin_label_setting',
			'visibility_setting',
			'pgr_national_id_settings', // custom group
		);
	}

	/**
	 * Register custom settings with Gravity Forms.
	 *
	 * @return void
	 */
	public static function register_settings() {
		add_action( 'gform_field_standard_settings', array( __CLASS__, 'add_settings' ), 10, 2 );
		add_action( 'gform_editor_js', array( __CLASS__, 'editor_js' ) );
		add_filter( 'gform_tooltips', array( __CLASS__, 'tooltips' ) );
	}

	/**
	 * Output custom settings HTML in form editor.
	 *
	 * @param int $position Position of settings.
	 * @param int $form_id  Form ID.
	 * @return void
	 */
	public static function add_settings( $position, $form_id ) {
		if ( 50 !== $position ) {
			return;
		}
		?>
		<li class="pgr_national_id_setting field_setting">
			<input type="checkbox" id="pgr_show_location" onclick="SetFieldProperty('showLocation', this.checked);" />
			<label for="pgr_show_location" class="inline">
				<?php esc_html_e( 'Show city based on national ID', 'persian-gravityforms-refactor' ); ?>
				<?php gform_tooltip( 'pgr_tooltip_show_location' ); ?>
			</label>
			<br />
			<input type="checkbox" id="pgr_show_seperator" onclick="SetFieldProperty('showSeperator', this.checked);" />
			<label for="pgr_show_seperator" class="inline">
				<?php esc_html_e( 'Auto-separate digits with dash', 'persian-gravityforms-refactor' ); ?>
				<?php gform_tooltip( 'pgr_tooltip_show_seperator' ); ?>
			</label>
			<br />
			<input type="checkbox" id="pgr_force_english" onclick="SetFieldProperty('forceEnglish', this.checked);" />
			<label for="pgr_force_english" class="inline">
				<?php esc_html_e( 'Convert Persian/Arabic digits to English', 'persian-gravityforms-refactor' ); ?>
				<?php gform_tooltip( 'pgr_tooltip_force_english' ); ?>
			</label>
			<br /><br />
			<label for="pgr_not_digit_error"><?php esc_html_e( 'Error: Non-digit characters', 'persian-gravityforms-refactor' ); ?></label>
			<input type="text" id="pgr_not_digit_error" onkeyup="SetFieldProperty('notDigitError', this.value);" class="fieldwidth-3" />
			<br />
			<label for="pgr_qty_digit_error"><?php esc_html_e( 'Error: Invalid length', 'persian-gravityforms-refactor' ); ?></label>
			<input type="text" id="pgr_qty_digit_error" onkeyup="SetFieldProperty('qtyDigitError', this.value);" class="fieldwidth-3" />
			<br />
			<label for="pgr_is_invalid_error"><?php esc_html_e( 'Error: Invalid checksum', 'persian-gravityforms-refactor' ); ?></label>
			<input type="text" id="pgr_is_invalid_error" onkeyup="SetFieldProperty('isInvalidError', this.value);" class="fieldwidth-3" />
			<br />
			<label for="pgr_duplicate_error"><?php esc_html_e( 'Error: Duplicate entry', 'persian-gravityforms-refactor' ); ?></label>
			<input type="text" id="pgr_duplicate_error" onkeyup="SetFieldProperty('duplicateError', this.value);" class="fieldwidth-3" />
		</li>
		<?php
	}

	/**
	 * JavaScript for settings binding in form editor.
	 *
	 * @return void
	 */
	public static function editor_js() {
		?>
		<script type="text/javascript">
			jQuery(document).bind('gform_load_field_settings', function(event, field, form) {
				jQuery('#pgr_show_location').prop('checked', field.showLocation == true);
				jQuery('#pgr_show_seperator').prop('checked', field.showSeperator == true);
				jQuery('#pgr_force_english').prop('checked', field.forceEnglish == true);
				jQuery('#pgr_not_digit_error').val(field.notDigitError || '');
				jQuery('#pgr_qty_digit_error').val(field.qtyDigitError || '');
				jQuery('#pgr_is_invalid_error').val(field.isInvalidError || '');
				jQuery('#pgr_duplicate_error').val(field.duplicateError || '');
			});
		</script>
		<?php
	}

	/**
	 * Add tooltips for custom settings.
	 *
	 * @param array $tooltips Existing tooltips.
	 * @return array Modified tooltips.
	 */
	public static function tooltips( $tooltips ) {
		$tooltips['pgr_tooltip_show_location']  = esc_html__( 'Show the city associated with the national ID (requires cities database).', 'persian-gravityforms-refactor' );
		$tooltips['pgr_tooltip_show_seperator'] = esc_html__( 'Automatically format national ID as XXX-XXXXXX-X.', 'persian-gravityforms-refactor' );
		$tooltips['pgr_tooltip_force_english']  = esc_html__( 'Convert Persian/Arabic digits to English silently.', 'persian-gravityforms-refactor' );
		return $tooltips;
	}

	/**
	 * Return the field input HTML.
	 *
	 * @param array  $form  Form object.
	 * @param string $value Field value.
	 * @param array  $entry Entry object (optional).
	 * @return string
	 */
	public function get_field_input( $form, $value = '', $entry = null ) {
		$form_id         = absint( $form['id'] );
		$is_entry_detail = GFCommon::is_entry_detail();
		$is_form_editor  = GFCommon::is_form_editor();

		$id          = absint( $this->id );
		$field_id    = $is_entry_detail || $is_form_editor || 0 === $form_id ? "input_$id" : 'input_' . $form_id . "_$id";
		$class       = $this->size . ( $is_entry_detail ? '_admin' : '' );
		$disabled    = $is_form_editor ? "disabled='disabled'" : '';
		$max_length  = $this->showSeperator ? 12 : 10;
		$placeholder = $this->get_field_placeholder_attribute();
		$required    = $this->isRequired ? 'aria-required="true"' : '';
		$invalid     = $this->failed_validation ? 'aria-invalid="true"' : 'aria-invalid="false"';
		$tabindex    = $this->get_tabindex();
		$logic_event = ! $is_form_editor && ! $is_entry_detail ? $this->get_conditional_logic_event( 'keyup' ) : '';

		$html_attrs = " {$placeholder} {$required} {$invalid} maxlength='{$max_length}'";

		// Data attributes for frontend script (pgr-frontend.js)
		$data_attrs = '';
		if ( $this->forceEnglish ) {
			$data_attrs .= ' data-pgr-force-english="true"';
		}
		// Enable live validation if either location or auto-formatting is active
		if ( $this->showLocation || $this->showSeperator ) {
			$data_attrs .= ' data-pgr-live-validation="true"';
		}

		$input = sprintf(
			'<div class="ginput_container ginput_container_pgr_national_id">
				<input name="input_%d" id="%s" type="text" value="%s" class="pgr_national_id %s" %s %s %s %s %s />
			</div>',
			$id,
			$field_id,
			esc_attr( $value ),
			esc_attr( $class ),
			$tabindex,
			$logic_event,
			$html_attrs,
			$disabled,
			$data_attrs
		);

		if ( ! $is_form_editor && ! $is_entry_detail && $this->showLocation ) {
			$input .= '<span class="pgr_national_id_location" id="pgr_location_' . $id . '"></span>';
		}

		return $input;
	}

	/**
	 * Validate the field value.
	 *
	 * @param string|array $value Field value.
	 * @param array        $form  Form object.
	 * @return void
	 */
	public function validate( $value, $form ) {
		if ( $this->isRequired && empty( $value ) ) {
			$this->failed_validation  = true;
			$this->validation_message = $this->errorMessage ?: esc_html__( 'This field is required.', 'gravityforms' );
			return;
		}
		if ( empty( $value ) ) {
			return;
		}

		$clean = preg_replace( '/\D/', '', PGR_Utils::normalize_digits( $value ) );

		if ( ! $this->is_valid_national_id( $clean ) ) {
			$this->failed_validation = true;
			$this->validation_message = $this->get_validation_error_message( $clean );
			return;
		}

		if ( $this->noDuplicates && $this->is_duplicate( $clean, $form['id'] ) ) {
			$this->failed_validation = true;
			$this->validation_message = ! empty( $this->duplicateError ) ? $this->duplicateError : esc_html__( 'This National ID has already been submitted.', 'persian-gravityforms-refactor' );
		}
	}

	/**
	 * Core validation logic using mod 11 algorithm.
	 *
	 * @param string $code National ID (10 digits).
	 * @return bool
	 */
	private function is_valid_national_id( $code ) {
		if ( 10 !== strlen( $code ) ) {
			return false;
		}
		if ( preg_match( '/^(\d)\1{9}$/', $code ) ) {
			return false;
		}
		$check = (int) $code[9];
		$sum = 0;
		for ( $i = 0; $i < 9; $i++ ) {
			$sum += (int) $code[ $i ] * ( 10 - $i );
		}
		$remainder = $sum % 11;
		$calculated = $remainder < 2 ? $remainder : 11 - $remainder;
		return $calculated === $check;
	}

	/**
	 * Get appropriate error message based on validation failure type.
	 *
	 * @param string $raw Cleaned but possibly invalid value.
	 * @return string
	 */
	private function get_validation_error_message( $raw ) {
		if ( ! ctype_digit( $raw ) ) {
			return ! empty( $this->notDigitError ) ? $this->notDigitError : esc_html__( 'Only numeric digits are allowed.', 'persian-gravityforms-refactor' );
		}
		if ( 10 !== strlen( $raw ) ) {
			return ! empty( $this->qtyDigitError ) ? $this->qtyDigitError : esc_html__( 'National ID must be exactly 10 digits.', 'persian-gravityforms-refactor' );
		}
		return ! empty( $this->isInvalidError ) ? $this->isInvalidError : esc_html__( 'Invalid Iranian National ID.', 'persian-gravityforms-refactor' );
	}

	/**
	 * Check for duplicate entry.
	 *
	 * @param string $value   Normalized national ID.
	 * @param int    $form_id Form ID.
	 * @return bool
	 */
	private function is_duplicate( $value, $form_id ) {
		global $wpdb;
		$meta_table = GFFormsModel::get_entry_meta_table_name();
		$entry_table = GFFormsModel::get_entry_table_name();
		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$meta_table} m 
			INNER JOIN {$entry_table} e ON e.id = m.entry_id 
			WHERE m.meta_key = %s AND m.meta_value = %s AND e.form_id = %d AND e.status = 'active'",
			$this->id,
			$value,
			$form_id
		);
		return (int) $wpdb->get_var( $sql ) > 0;
	}

	/**
	 * Save normalized value to entry meta.
	 *
	 * @param string|array $value    Field value.
	 * @param array        $form     Form object.
	 * @param string       $input_id Input ID.
	 * @param array        $entry    Entry object.
	 * @return string Normalized 10-digit ID.
	 */
	public function get_value_save_entry( $value, $form, $input_id, $entry ) {
		$clean = preg_replace( '/\D/', '', PGR_Utils::normalize_digits( $value ) );
		$len = strlen( $clean );
		if ( 8 === $len ) {
			$clean = '00' . $clean;
		} elseif ( 9 === $len ) {
			$clean = '0' . $clean;
		}
		return $clean;
	}

	/**
	 * Display formatted value in entry detail.
	 *
	 * @param string|array $value   Field value.
	 * @param array        $entry   Entry object.
	 * @param array        $form    Form object.
	 * @param string       $context Display context.
	 * @return string Formatted ID (XXX-XXXXXX-X).
	 */
	public function get_value_entry_detail( $value, $entry = array(), $form = array(), $context = 'text' ) {
		$clean = preg_replace( '/\D/', '', $value );
		if ( 10 !== strlen( $clean ) ) {
			return $value;
		}
		return substr( $clean, 0, 3 ) . '-' . substr( $clean, 3, 6 ) . '-' . substr( $clean, 9 );
	}

	/**
	 * Return value for merge tag.
	 *
	 * @param string|array $value    Field value.
	 * @param array        $entry    Entry object.
	 * @param array        $form     Form object.
	 * @param string       $modifier Merge tag modifier.
	 * @return string
	 */
	public function get_value_merge_tag( $value, $entry, $form, $modifier ) {
		return $this->get_value_entry_detail( $value, $entry, $form );
	}
}

// Register the field when Gravity Forms is loaded.
add_action( 'gform_loaded', function() {
	if ( class_exists( 'GF_Fields' ) ) {
		GF_Fields::register( new PGR_GF_Field_National_ID() );
		PGR_GF_Field_National_ID::register_settings();
	}
}, 5 );
