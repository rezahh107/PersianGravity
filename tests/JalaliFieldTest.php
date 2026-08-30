<?php

use PHPUnit\Framework\TestCase;

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
require_once dirname( __DIR__ ) . '/includes/fields/class-gf-field-jalali-date.php';

final class JalaliFieldTest extends TestCase {

	public function test_field_owns_validation_and_canonical_persistence() {
		$field = new PGR_GF_Field_Jalali_Date();
		$field->jalali_format = 'ymd_slash';

		$field->validate( '۱۴۰۴/۰۲/۳۱', array() );
		$this->assertFalse( $field->failed_validation );
		$this->assertSame( '1404-02-31', $field->get_value_save_entry( '۱۴۰۴/۰۲/۳۱', array(), '', 0, array() ) );

		$field->failed_validation = false;
		$field->validate( '1404/07/31', array() );
		$this->assertTrue( $field->failed_validation );
		$this->assertSame( '', $field->get_value_save_entry( '1404/07/31', array(), '', 0, array() ) );
	}

	public function test_empty_value_is_not_reinterpreted_by_custom_validator() {
		$field = new PGR_GF_Field_Jalali_Date();
		$field->isRequired = true;
		$field->validate( '', array() );
		$this->assertFalse( $field->failed_validation, 'Gravity Forms remains responsible for required-field semantics before custom validation.' );
		$this->assertSame( '', $field->get_value_save_entry( '', array(), '', 0, array() ) );
	}

	public function test_entry_merge_tag_and_export_output_retain_jalali_semantics() {
		$field = new PGR_GF_Field_Jalali_Date();
		$field->id = 7;
		$field->jalali_format = 'dmy_dot';

		$this->assertSame( '31.02.1404', $field->get_value_entry_detail( '1404-02-31', '', false, 'text' ) );
		$this->assertSame(
			'31.02.1404',
			$field->get_value_merge_tag( '1404-02-31', '7', array(), array(), '', '1404-02-31', false, false, 'text', false )
		);
		$this->assertSame( '31.02.1404', $field->get_value_export( array( '7' => '1404-02-31' ) ) );
	}

	public function test_field_exposes_only_dedicated_jalali_type_and_scalar_input() {
		$field = new PGR_GF_Field_Jalali_Date();
		$this->assertSame( 'pgr_jalali_date', $field->type );
		$this->assertNotContains( 'date_type_setting', $field->get_form_editor_field_settings() );
		$this->assertStringContainsString( 'type="text"', $field->get_field_input( array( 'id' => 1 ), '1404/02/31' ) );
	}
}
