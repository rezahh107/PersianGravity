<?php

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class ModuleUsageTest extends TestCase {

	#[RunInSeparateProcess]
	public function test_custom_field_use_blocks_disable() {
		eval( 'class GFAPI { public static function get_forms($active = null, $trash = false) { return array(array("id"=>1,"fields"=>array((object) array("type"=>"pgr_national_id")))); } }' );
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-registry.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-usage.php';
		$result = PGR_Module_Usage::inspect( 'national_id' );
		$this->assertSame( PGR_Module_Usage::USED, $result['status'] );
		$this->assertSame( 1, $result['count'] );
	}

	#[RunInSeparateProcess]
	public function test_unused_custom_field_can_be_disabled() {
		eval( 'class GFAPI { public static function get_forms($active = null, $trash = false) { return array(array("id"=>1,"fields"=>array((object) array("type"=>"text")))); } }' );
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-registry.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-usage.php';
		$result = PGR_Module_Usage::inspect( 'jalali_date' );
		$this->assertSame( PGR_Module_Usage::UNUSED, $result['status'] );
	}

	#[RunInSeparateProcess]
	public function test_digit_normalization_form_use_is_detected() {
		eval( 'class GFAPI { public static function get_forms($active = null, $trash = false) { return array(array("id"=>2,"pgr_normalize_digits"=>1,"fields"=>array())); } }' );
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-registry.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-usage.php';
		$result = PGR_Module_Usage::inspect( 'digit_normalization' );
		$this->assertSame( PGR_Module_Usage::USED, $result['status'] );
	}

	#[RunInSeparateProcess]
	public function test_iranian_address_use_is_detected_from_address_type() {
		eval( 'class GFAPI { public static function get_forms($active = null, $trash = false) { return array(array("id"=>3,"fields"=>array((object) array("type"=>"address","addressType"=>"iran")))); } }' );
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-registry.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-usage.php';
		$result = PGR_Module_Usage::inspect( 'iranian_address' );
		$this->assertSame( PGR_Module_Usage::USED, $result['status'] );
	}

	#[RunInSeparateProcess]
	public function test_currency_is_conservatively_unknown() {
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-registry.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-usage.php';
		$result = PGR_Module_Usage::inspect( 'iranian_currency' );
		$this->assertSame( PGR_Module_Usage::UNKNOWN, $result['status'] );
	}

	#[RunInSeparateProcess]
	public function test_gravity_forms_unavailable_is_unknown() {
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-registry.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-usage.php';
		$result = PGR_Module_Usage::inspect( 'national_id' );
		$this->assertSame( PGR_Module_Usage::UNKNOWN, $result['status'] );
	}
}
