<?php

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class RuntimeBootstrapTest extends TestCase {

	#[RunInSeparateProcess]
	public function test_bootstrap_is_safe_when_gravity_forms_is_absent() {
		require dirname( __DIR__ ) . '/persian-gravityforms.php';

		$this->assertTrue( function_exists( 'pgr_initialize' ) );
		pgr_initialize();
		$this->assertFalse( class_exists( 'PGR_Core', false ) );
	}

	#[RunInSeparateProcess]
	public function test_gravity_forms_runtime_initializes_once_and_registers_both_custom_fields() {
		eval( 'class GFForms { public static $version = "3.1.0"; }' );
		eval( 'class GF_Field { public $id = 1; public $size = ""; public $isRequired = false; public $failed_validation = false; public $validation_message = ""; public $errorMessage = ""; public $type = ""; public $forceEnglish = true; public function is_entry_detail(){return false;} public function is_form_editor(){return false;} public function get_field_placeholder_attribute(){return "";} }' );
		eval( 'class GF_Fields { public static $registered = array(); public static function register( $field ) { self::$registered[] = $field; } }' );

		require dirname( __DIR__ ) . '/persian-gravityforms.php';
		do_action( 'gform_loaded' );
		do_action( 'gform_loaded' );

		$this->assertCount( 2, GF_Fields::$registered );
		$this->assertInstanceOf( PGR_GF_Field_National_ID::class, GF_Fields::$registered[0] );
		$this->assertInstanceOf( PGR_GF_Field_Jalali_Date::class, GF_Fields::$registered[1] );
		$this->assertFalse( method_exists( PGR_Persian_Date::class, 'hooks' ) );

		$field = GF_Fields::$registered[0];
		$field->validate( '0013546244', array() );
		$this->assertFalse( $field->failed_validation );
		$field->validate( '0013546245', array() );
		$this->assertTrue( $field->failed_validation );
	}

	#[RunInSeparateProcess]
	public function test_native_date_fields_do_not_activate_persian_gravity_assets() {
		eval( 'class GFForms { public static $version = "3.1.0"; }' );
		eval( 'class GF_Field { public $id = 1; public $size = ""; public $isRequired = false; public $failed_validation = false; public $validation_message = ""; public $errorMessage = ""; public $type = ""; public $forceEnglish = true; public function is_entry_detail(){return false;} public function is_form_editor(){return false;} public function get_field_placeholder_attribute(){return "";} }' );
		eval( 'class GF_Fields { public static $registered = array(); public static function register( $field ) { self::$registered[] = $field; } }' );

		require dirname( __DIR__ ) . '/persian-gravityforms.php';
		do_action( 'gform_loaded' );

		$native_date = (object) array( 'type' => 'date' );
		PGR_Core::enqueue_field_assets( array( 'fields' => array( $native_date ) ), false );
		$this->assertSame( array(), $GLOBALS['pgr_test_enqueued'] );

		$national_id = (object) array( 'type' => 'pgr_national_id', 'forceEnglish' => true );
		PGR_Core::enqueue_field_assets( array( 'fields' => array( $national_id ) ), false );
		$this->assertSame( array( 'pgr-frontend' ), $GLOBALS['pgr_test_enqueued'] );
	}
}
