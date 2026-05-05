<?php
/**
 * Persian date helpers for Gravity Forms.
 * Adds Jalali (Shamsi) calendar support to date fields.
 *
 * @package PersianGravityFormsRefactor
 * @since   3.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PGR_Persian_Date
 * 
 * Localizes Gravity Forms date picker to Jalali calendar and validates Jalali dates.
 */
class PGR_Persian_Date {

	/**
	 * Cache for forms that need Jalali date picker.
	 *
	 * @var array
	 */
	private static $jalali_forms = array();

	/**
	 * Register hooks.
	 *
	 * @since 3.0.0
	 */
	public function hooks(): void {
		// Replace datepicker script with Jalali version only when needed
		add_action( 'gform_enqueue_scripts', array( $this, 'maybe_replace_datepicker' ), 10, 2 );
		
		// Add Jalali setting to date fields in form editor
		add_action( 'gform_field_standard_settings', array( $this, 'add_jalali_setting' ), 10, 2 );
		add_action( 'gform_editor_js', array( $this, 'jalali_editor_js' ) );
		add_filter( 'gform_tooltips', array( $this, 'add_tooltips' ) );
		
		// Validate Jalali dates on submission
		add_filter( 'gform_field_validation', array( $this, 'validate_jalali_date' ), 10, 4 );
		
		// Save Jalali date value (as Gregorian in database, or keep as Jalali)
		add_filter( 'gform_save_field_value', array( $this, 'save_jalali_date' ), 10, 4 );
	}

	/**
	 * Replace the standard jQuery UI Datepicker with Jalali version if form has Jalali date fields.
	 *
	 * @since 3.0.0
	 *
	 * @param array $form    Form object.
	 * @param bool  $is_ajax Whether form is submitted via AJAX.
	 */
	public function maybe_replace_datepicker( array $form, bool $is_ajax ): void {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		$has_jalali = $this->form_has_jalali_date_field( $form );
		if ( ! $has_jalali ) {
			return;
		}

		// Store form ID for later use
		self::$jalali_forms[] = $form['id'];

		// Dequeue original datepicker
		wp_dequeue_script( 'jquery-ui-datepicker' );
		wp_deregister_script( 'jquery-ui-datepicker' );

		// Enqueue Jalali datepicker script (Persian calendar)
		wp_register_script(
			'pgr-jalali-datepicker',
			PGR_URL . 'assets/js/jalali-datepicker.min.js',
			array( 'jquery', 'jquery-ui-core' ),
			PGR_VERSION,
			true
		);
		wp_enqueue_script( 'pgr-jalali-datepicker' );

		// Localize month and day names
		wp_localize_script( 'pgr-jalali-datepicker', 'pgr_jalali_i18n', array(
			'months'       => $this->get_jalali_months(),
			'monthsShort'  => $this->get_jalali_months_short(),
			'days'         => $this->get_jalali_days(),
			'daysShort'    => $this->get_jalali_days_short(),
			'firstDay'     => 6, // Saturday is first day of week in Iran
			'dateFormat'   => 'yy/mm/dd',
		) );
	}

	/**
	 * Check if form has any date field with Jalali setting enabled.
	 *
	 * @since 3.0.0
	 *
	 * @param array $form Form object.
	 * @return bool
	 */
	private function form_has_jalali_date_field( array $form ): bool {
		foreach ( $form['fields'] as $field ) {
			if ( $field->type === 'date' && ! empty( $field->isJalali ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Add "Jalali Calendar" checkbox to date field settings in form editor.
	 *
	 * @since 3.0.0
	 *
	 * @param int $position Position of settings.
	 * @param int $form_id  Form ID.
	 */
	public function add_jalali_setting( int $position, int $form_id ): void {
		if ( $position !== 25 ) {
			return;
		}
		?>
		<li class="pgr_jalali_setting field_setting">
			<input type="checkbox" id="pgr_enable_jalali" />
			<label for="pgr_enable_jalali" class="inline">
				<?php esc_html_e( 'Enable Jalali (Persian) calendar', 'persian-gravityforms-refactor' ); ?>
				<?php gform_tooltip( 'pgr_jalali_tooltip' ); ?>
			</label>
		</li>
		<?php
	}

	/**
	 * JavaScript for binding Jalali setting in form editor.
	 *
	 * @since 3.0.0
	 */
	public function jalali_editor_js(): void {
		?>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				// Add setting to date fields
				fieldSettings['date'] += ', .pgr_jalali_setting';
				
				// Load saved setting when field is selected
				$(document).bind('gform_load_field_settings', function(event, field, form) {
					$('#pgr_enable_jalali').prop('checked', field.isJalali === true);
				});
				
				// Save setting when checkbox changes
				$(document).on('change', '#pgr_enable_jalali', function() {
					SetFieldProperty('isJalali', $(this).is(':checked'));
				});
			});
		</script>
		<?php
	}

	/**
	 * Add tooltip for Jalali setting.
	 *
	 * @since 3.0.0
	 *
	 * @param array $tooltips Existing tooltips.
	 * @return array Modified tooltips.
	 */
	public function add_tooltips( array $tooltips ): array {
		$tooltips['pgr_jalali_tooltip'] = esc_html__(
			'Enable Persian (Jalali/Shamsi) calendar for this date field. Months will be Farvardin, Ordibehesht, etc.',
			'persian-gravityforms-refactor'
		);
		return $tooltips;
	}

	/**
	 * Validate Jalali date values on submission.
	 *
	 * @since 3.0.0
	 *
	 * @param array  $result Validation result.
	 * @param string $value  Field value.
	 * @param array  $form   Form object.
	 * @param object $field  Field object.
	 * @return array Modified validation result.
	 */
	public function validate_jalali_date( array $result, $value, array $form, $field ): array {
		// Only validate if this is a date field with Jalali enabled
		if ( $field->type !== 'date' || empty( $field->isJalali ) ) {
			return $result;
		}

		// Skip if already invalid or empty
		if ( ! $result['is_valid'] || empty( $value ) ) {
			return $result;
		}

		// Parse Jalali date
		$parsed = $this->parse_jalali_date( $value, $field->dateFormat );
		if ( ! $parsed ) {
			$result['is_valid'] = false;
			$result['message']  = esc_html__( 'Please enter a valid Jalali (Persian) date.', 'persian-gravityforms-refactor' );
			return $result;
		}

		// Optional: Check min/max date constraints (convert to Jalali)
		// This can be added later if needed

		return $result;
	}

	/**
	 * Save Jalali date as Gregorian in database (optional).
	 * For compatibility, we keep Jalali format as entered.
	 *
	 * @since 3.0.0
	 *
	 * @param mixed  $value    Field value.
	 * @param array  $form     Form object.
	 * @param string $input_id Input ID.
	 * @param array  $entry    Entry object.
	 * @return mixed
	 */
	public function save_jalali_date( $value, array $form, string $input_id, array $entry ) {
		// If needed, convert Jalali to Gregorian before saving
		// For now, return as-is (store Jalali string)
		return $value;
	}

	/**
	 * Parse a Jalali date string based on date format.
	 *
	 * @since 3.0.0
	 *
	 * @param string $date_string Date string.
	 * @param string $format      GF date format (mdy, dmy, ymd, etc.).
	 * @return array|false Array with year, month, day or false if invalid.
	 */
	private function parse_jalali_date( string $date_string, string $format ) {
		// Normalize digits first
		$normalized = PGR_Utils::normalize_digits( $date_string );
		
		// Remove non-digit separators
		preg_match( '/(\d+)[^\d]+(\d+)[^\d]+(\d+)/', $normalized, $matches );
		if ( count( $matches ) !== 4 ) {
			return false;
		}
		
		list( , $part1, $part2, $part3 ) = $matches;
		
		switch ( $format ) {
			case 'mdy':
				$month = (int) $part1;
				$day   = (int) $part2;
				$year  = (int) $part3;
				break;
			case 'dmy':
				$day   = (int) $part1;
				$month = (int) $part2;
				$year  = (int) $part3;
				break;
			case 'ymd_slash':
			case 'ymd_dash':
			case 'ymd_dot':
			default:
				$year  = (int) $part1;
				$month = (int) $part2;
				$day   = (int) $part3;
				break;
		}
		
		// Validate Jalali date using checkdate equivalent
		if ( ! $this->check_jalali_date( $year, $month, $day ) ) {
			return false;
		}
		
		return array( 'year' => $year, 'month' => $month, 'day' => $day );
	}

	/**
	 * Check if a Jalali date is valid.
	 *
	 * @since 3.0.0
	 *
	 * @param int $year  Jalali year (e.g., 1400).
	 * @param int $month Jalali month (1-12).
	 * @param int $day   Jalali day (1-31 depending on month).
	 * @return bool
	 */
	private function check_jalali_date( int $year, int $month, int $day ): bool {
		if ( $year < 1 || $month < 1 || $month > 12 || $day < 1 ) {
			return false;
		}
		// Days in each Jalali month
		$days_in_month = array( 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29 );
		// Adjust for leap years (month 12 has 30 days in leap years)
		if ( $this->is_jalali_leap_year( $year ) ) {
			$days_in_month[11] = 30;
		}
		return $day <= $days_in_month[ $month - 1 ];
	}

	/**
	 * Check if a Jalali year is a leap year.
	 * Leap years occur when (year + 2346) % 33 is one of 1, 5, 9, 13, 17, 22, 26, 30.
	 *
	 * @since 3.0.0
	 *
	 * @param int $year Jalali year.
	 * @return bool
	 */
	private function is_jalali_leap_year( int $year ): bool {
		$leap_offsets = array( 1, 5, 9, 13, 17, 22, 26, 30 );
		$remainder = ( $year + 2346 ) % 33;
		return in_array( $remainder, $leap_offsets, true );
	}

	/**
	 * Get Jalali month names (full).
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	private function get_jalali_months(): array {
		return array(
			'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
			'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
		);
	}

	/**
	 * Get abbreviated Jalali month names.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	private function get_jalali_months_short(): array {
		return array(
			'فر', 'ار', 'خر', 'تی', 'مر', 'شه',
			'مه', 'آب', 'آذ', 'دی', 'به', 'اس'
		);
	}

	/**
	 * Get Jalali day names (full).
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	private function get_jalali_days(): array {
		return array( 'شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه' );
	}

	/**
	 * Get abbreviated Jalali day names.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	private function get_jalali_days_short(): array {
		return array( 'ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج' );
	}
}
