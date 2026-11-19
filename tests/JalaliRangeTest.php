<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'PGR_PATH' ) ) {
	define( 'PGR_PATH', dirname( __DIR__ ) . '/' );
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) { return $GLOBALS['pgr_test_transients'][ $key ] ?? false; }
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $expiration ) { $GLOBALS['pgr_test_transients'][ $key ] = $value; return $expiration > 0; }
}

if ( ! class_exists( 'GF_Field' ) ) {
	class GF_Field {
		public $id = 7;
		public $size = '';
		public $isRequired = false;
		public $failed_validation = false;
		public $validation_message = '';
		public $errorMessage = '';

		public function is_entry_detail() {
			return false;
		}

		public function is_form_editor() {
			return false;
		}

		public function get_field_placeholder_attribute() {
			return '';
		}
	}
}

require_once dirname( __DIR__ ) . '/includes/class-pgr-utils.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-persian-date.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-gregorian-jalali-converter.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-jalali-range.php';
require_once dirname( __DIR__ ) . '/includes/fields/class-gf-field-jalali-date.php';

final class JalaliRangeTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pgr_test_transients'] = array();
		$GLOBALS['pgr_test_filters']['pgr_jalali_today'] = array(
			10 => array(
				array(
					static function () {
						return '1404-05-10';
					},
					1,
				),
			),
		);
	}

	protected function tearDown(): void {
		unset( $GLOBALS['pgr_test_filters']['pgr_jalali_today'] );
	}

	public function test_absolute_bounds_resolve_to_canonical_storage() {
		$this->assertSame( '1400-01-01', PGR_Jalali_Range::resolve_bound( '1400/01/01' ) );
		$this->assertSame( '1400-01-01', PGR_Jalali_Range::resolve_bound( '۱۴۰۰-۰۱-۰۱' ) );
		$this->assertSame( '', PGR_Jalali_Range::resolve_bound( '1400/07/31' ) );
		$this->assertSame( '', PGR_Jalali_Range::resolve_bound( '' ) );
	}

	public function test_relative_bounds_resolve_against_today() {
		$this->assertSame( '1404-05-10', PGR_Jalali_Range::resolve_bound( 'today' ) );
		$this->assertSame( '1404-05-17', PGR_Jalali_Range::resolve_bound( 'today+7d' ) );
		$this->assertSame( '1404-04-10', PGR_Jalali_Range::resolve_bound( 'today-1m' ) );
		$this->assertSame( '1386-05-10', PGR_Jalali_Range::resolve_bound( 'today-18y' ) );
	}

	public function test_presets_resolve_through_shipped_preset_file() {
		$this->assertSame( '1386-05-10', PGR_Jalali_Range::resolve_bound( 'preset:adult_birthdate' ) );
		$this->assertSame( '1404-05-11', PGR_Jalali_Range::resolve_bound( 'preset:tomorrow' ) );
		$this->assertSame( '', PGR_Jalali_Range::resolve_bound( 'preset:unknown' ) );
	}

	public function test_range_check_is_inclusive_of_bounds() {
		$this->assertTrue( PGR_Jalali_Range::is_within( '1404-05-10', '1404-01-01', '1404-12-29' ) );
		$this->assertTrue( PGR_Jalali_Range::is_within( '1404-01-01', '1404-01-01', '' ) );
		$this->assertFalse( PGR_Jalali_Range::is_within( '1403-12-29', '1404-01-01', '' ) );
		$this->assertFalse( PGR_Jalali_Range::is_within( '1405-02-01', '', '1404-12-29' ) );
	}

	public function test_field_rejects_dates_outside_configured_range() {
		$field                = new PGR_GF_Field_Jalali_Date();
		$field->jalali_format = 'ymd_slash';
		$field->jalali_min    = '1404/01/01';
		$field->jalali_max    = 'today';

		$field->validate( '1404/03/15', array() );
		$this->assertFalse( $field->failed_validation );

		$field->failed_validation = false;
		$field->validate( '1403/12/01', array() );
		$this->assertTrue( $field->failed_validation );

		$field->failed_validation = false;
		$field->validate( '1404/06/01', array() );
		$this->assertTrue( $field->failed_validation );
	}

	public function test_rendered_input_exposes_resolved_bounds_for_the_browser() {
		$field            = new PGR_GF_Field_Jalali_Date();
		$field->jalaliMin = 'today-18y';
		$field->jalaliMax = 'today';

		$html = $field->get_field_input( array( 'id' => 3 ), '' );

		$this->assertStringContainsString( 'data-pgr-jalali-min="1386-05-10"', $html );
		$this->assertStringContainsString( 'data-pgr-jalali-max="1404-05-10"', $html );
	}

	public function test_unbounded_field_keeps_previous_validation_behavior() {
		$field = new PGR_GF_Field_Jalali_Date();
		$field->validate( '1404/02/31', array() );
		$this->assertFalse( $field->failed_validation );
	}
}
