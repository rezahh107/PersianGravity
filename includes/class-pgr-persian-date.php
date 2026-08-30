<?php
/**
 * Bounded Jalali date support for Gravity Forms date fields.
 *
 * Storage semantics: the submitted Jalali date remains in the field's configured
 * Gravity Forms date order and is normalized to ASCII digits before persistence.
 * No implicit Gregorian conversion is performed.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Persian_Date {

	/**
	 * Register current Gravity Forms hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'gform_enqueue_scripts', array( $this, 'enqueue_datepicker' ), 10, 2 );
		add_action( 'gform_field_standard_settings', array( $this, 'render_field_setting' ), 10, 2 );
		add_action( 'gform_editor_js', array( $this, 'render_editor_script' ) );
		add_filter( 'gform_field_validation', array( $this, 'validate_field' ), 10, 4 );
		add_filter( 'gform_save_field_value', array( $this, 'normalize_saved_value' ), 10, 5 );
	}

	/**
	 * Replace the jQuery UI datepicker implementation only for a form containing
	 * a date field explicitly configured for Jalali input.
	 *
	 * @param array $form    Current form.
	 * @param bool  $is_ajax Whether the form uses AJAX.
	 * @return void
	 */
	public function enqueue_datepicker( $form, $is_ajax ) {
		unset( $is_ajax );

		if ( ! $this->form_uses_jalali( $form ) ) {
			return;
		}

		wp_deregister_script( 'jquery-ui-datepicker' );
		wp_register_script(
			'jquery-ui-datepicker',
			PGR_URL . 'assets/js/jalali-datepicker.min.js',
			array( 'jquery', 'jquery-ui-core' ),
			PGR_VERSION,
			true
		);
		wp_enqueue_script( 'jquery-ui-datepicker' );
	}

	/**
	 * Add a Jalali opt-in property to Gravity Forms date fields.
	 *
	 * @param int $position Settings position.
	 * @param int $form_id  Current form ID.
	 * @return void
	 */
	public function render_field_setting( $position, $form_id ) {
		unset( $form_id );

		if ( 25 !== $position ) {
			return;
		}
		?>
		<li class="pgr_jalali_setting field_setting">
			<input type="checkbox" id="pgr_jalali" onclick="SetFieldProperty('pgrJalali', this.checked);" />
			<label for="pgr_jalali" class="inline">
				<?php esc_html_e( 'Use Jalali (Persian) calendar', 'persian-gravityforms' ); ?>
			</label>
		</li>
		<?php
	}

	/**
	 * Attach the custom field setting to Gravity Forms date fields.
	 *
	 * @return void
	 */
	public function render_editor_script() {
		?>
		<script type="text/javascript">
			jQuery(function($) {
				if (typeof fieldSettings !== 'undefined' && typeof fieldSettings.date === 'string' && fieldSettings.date.indexOf('.pgr_jalali_setting') === -1) {
					fieldSettings.date += ', .pgr_jalali_setting';
				}
				$(document).on('gform_load_field_settings', function(event, field) {
					$('#pgr_jalali').prop('checked', field.pgrJalali === true);
				});
			});
		</script>
		<?php
	}

	/**
	 * Validate a Jalali date field without changing unrelated Gravity Forms dates.
	 *
	 * @param array    $result Current validation result.
	 * @param mixed    $value  Submitted value.
	 * @param array    $form   Current form.
	 * @param GF_Field $field  Current field.
	 * @return array
	 */
	public function validate_field( $result, $value, $form, $field ) {
		unset( $form );

		if ( ! is_object( $field ) || 'date' !== $field->type || empty( $field->pgrJalali ) ) {
			return $result;
		}

		if ( empty( $result['is_valid'] ) || '' === trim( (string) $value ) ) {
			return $result;
		}

		$parts = self::parse_date( (string) $value, (string) $field->dateFormat );
		if ( false === $parts || ! self::is_valid_date( $parts['year'], $parts['month'], $parts['day'] ) ) {
			$result['is_valid'] = false;
			$result['message']  = esc_html__( 'Enter a valid Jalali (Persian) date.', 'persian-gravityforms' );
		}

		return $result;
	}

	/**
	 * Normalize digits before a Jalali field is persisted.
	 *
	 * @param mixed    $value    Value about to be saved.
	 * @param array    $entry    Current entry.
	 * @param GF_Field $field    Current field.
	 * @param array    $form     Current form.
	 * @param mixed    $input_id Current input ID.
	 * @return mixed
	 */
	public function normalize_saved_value( $value, $entry, $field, $form, $input_id ) {
		unset( $entry, $form, $input_id );

		if ( ! is_object( $field ) || 'date' !== $field->type || empty( $field->pgrJalali ) || ! is_string( $value ) ) {
			return $value;
		}

		return PGR_Utils::normalize_digits( $value );
	}

	/**
	 * Determine whether a form contains at least one Jalali date field.
	 *
	 * @param array $form Current form.
	 * @return bool
	 */
	public function form_uses_jalali( $form ) {
		foreach ( (array) rgar( $form, 'fields' ) as $field ) {
			if ( is_object( $field ) && 'date' === $field->type && ! empty( $field->pgrJalali ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Parse the three date components using Gravity Forms' configured date order.
	 *
	 * @param string $value  Date value.
	 * @param string $format Gravity Forms date format.
	 * @return array|false
	 */
	public static function parse_date( $value, $format ) {
		$value = trim( PGR_Utils::normalize_digits( $value ) );

		if ( ! preg_match( '/^(\d{1,4})[^\d]+(\d{1,2})[^\d]+(\d{1,4})$/', $value, $matches ) ) {
			return false;
		}

		$first  = (int) $matches[1];
		$second = (int) $matches[2];
		$third  = (int) $matches[3];

		if ( 'mdy' === $format ) {
			return array( 'year' => $third, 'month' => $first, 'day' => $second );
		}

		if ( 'dmy' === $format ) {
			return array( 'year' => $third, 'month' => $second, 'day' => $first );
		}

		return array( 'year' => $first, 'month' => $second, 'day' => $third );
	}

	/**
	 * Validate a Jalali calendar date.
	 *
	 * The leap-year rule is the compact 33-year rule already used by the retained
	 * Persian calendar implementation, isolated here and covered by tests.
	 *
	 * @param int $year  Jalali year.
	 * @param int $month Jalali month.
	 * @param int $day   Jalali day.
	 * @return bool
	 */
	public static function is_valid_date( $year, $month, $day ) {
		$year  = (int) $year;
		$month = (int) $month;
		$day   = (int) $day;

		if ( $year < 1 || $month < 1 || $month > 12 || $day < 1 ) {
			return false;
		}

		if ( $month <= 6 ) {
			$max_day = 31;
		} elseif ( $month <= 11 ) {
			$max_day = 30;
		} else {
			$max_day = self::is_leap_year( $year ) ? 30 : 29;
		}

		return $day <= $max_day;
	}

	/**
	 * Return whether a Jalali year is leap under the retained JDF 33-year rule.
	 *
	 * @param int $year Jalali year.
	 * @return bool
	 */
	public static function is_leap_year( $year ) {
		$remainder = (int) $year % 33;
		return ( $remainder % 4 ) - 1 === (int) ( $remainder * 0.05 );
	}
}
