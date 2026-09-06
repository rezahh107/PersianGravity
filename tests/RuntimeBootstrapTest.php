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
	public function test_gravity_forms_runtime_initializes_once_and_registers_all_custom_fields() {
		eval( 'class GFForms { public static $version = "3.1.0"; }' );
		eval( 'class GF_Field { public $id = 1; public $size = ""; public $isRequired = false; public $failed_validation = false; public $validation_message = ""; public $errorMessage = ""; public $type = ""; public $forceEnglish = true; public $displayOnly = false; public function is_entry_detail(){return false;} public function is_form_editor(){return false;} public function get_field_placeholder_attribute(){return "";} }' );
		eval( 'class GF_Fields { public static $registered = array(); public static function register( $field ) { self::$registered[] = $field; } }' );

		require dirname( __DIR__ ) . '/persian-gravityforms.php';
		do_action( 'gform_loaded' );
		do_action( 'gform_loaded' );

		$this->assertCount( 3, GF_Fields::$registered );
		$this->assertInstanceOf( PGR_GF_Field_National_ID::class, GF_Fields::$registered[0] );
		$this->assertInstanceOf( PGR_GF_Field_Jalali_Date::class, GF_Fields::$registered[1] );
		$this->assertInstanceOf( PGR_GF_Field_Structured_Scanner::class, GF_Fields::$registered[2] );
		$this->assertFalse( method_exists( PGR_Persian_Date::class, 'hooks' ) );

		$field = GF_Fields::$registered[0];
		$field->validate( '0013546244', array() );
		$this->assertFalse( $field->failed_validation );
		$field->validate( '0013546245', array() );
		$this->assertTrue( $field->failed_validation );
	}

	#[RunInSeparateProcess]
	public function test_structured_scanner_is_display_only_and_returns_no_persisted_or_export_value() {
		eval( 'class GFForms { public static $version = "3.1.0"; }' );
		eval( 'class GF_Field { public $id = 7; public $size = ""; public $isRequired = false; public $failed_validation = false; public $validation_message = ""; public $errorMessage = ""; public $type = ""; public $forceEnglish = true; public $displayOnly = false; public function is_entry_detail(){return false;} public function is_form_editor(){return false;} public function get_field_placeholder_attribute(){return "";} }' );
		eval( 'class GF_Fields { public static $registered = array(); public static function register( $field ) { self::$registered[] = $field; } }' );

		require dirname( __DIR__ ) . '/persian-gravityforms.php';
		do_action( 'gform_loaded' );

		$scanner = GF_Fields::$registered[2];

		$this->assertTrue( $scanner->displayOnly );
		$this->assertSame( 'sayad_v01', $scanner->scanner_profile );
		$this->assertSame( '', $scanner->get_value_save_entry( 'raw', array(), 'input_7', 1, array() ) );
		$this->assertSame( '', $scanner->get_value_entry_detail( 'raw' ) );
		$this->assertSame( '', $scanner->get_value_entry_list( 'raw', array(), '7', array(), array() ) );
		$this->assertSame( '', $scanner->get_value_merge_tag( 'raw', '7', array(), array(), '', 'raw', false, false, 'text', false ) );
		$this->assertSame( '', $scanner->get_value_export( array( '7' => 'raw' ) ) );

		$form   = array( 'id' => 1, 'fields' => array( $scanner ) );
		$markup = $scanner->get_field_input( $form );

		$this->assertStringContainsString( '<textarea ', $markup );
		$this->assertStringContainsString( 'data-pgr-scanner-capture="1"', $markup );
		$this->assertStringContainsString( 'data-pgr-mappings="{}"', $markup );
		$this->assertStringContainsString( '</textarea>', $markup );
		$this->assertStringNotContainsString( '<input type="text" class="pgr-structured-scanner__capture"', $markup );
		$this->assertStringNotContainsString( 'name="input_7"', $markup );
		$this->assertStringNotContainsString( '>raw<', $markup );
	}

	#[RunInSeparateProcess]
	public function test_native_date_fields_do_not_activate_persian_gravity_assets() {
		eval( 'class GFForms { public static $version = "3.1.0"; }' );
		eval( 'class GF_Field { public $id = 1; public $size = ""; public $isRequired = false; public $failed_validation = false; public $validation_message = ""; public $errorMessage = ""; public $type = ""; public $forceEnglish = true; public $displayOnly = false; public function is_entry_detail(){return false;} public function is_form_editor(){return false;} public function get_field_placeholder_attribute(){return "";} }' );
		eval( 'class GF_Fields { public static $registered = array(); public static function register( $field ) { self::$registered[] = $field; } }' );

		require dirname( __DIR__ ) . '/persian-gravityforms.php';
		do_action( 'gform_loaded' );

		$native_date = (object) array( 'type' => 'date' );
		PGR_Core::enqueue_field_assets( array( 'fields' => array( $native_date ) ), false );
		$this->assertSame( array(), $GLOBALS['pgr_test_enqueued'] );
		$this->assertSame( array(), $GLOBALS['pgr_test_styles'] );

		$national_id = (object) array( 'type' => 'pgr_national_id', 'forceEnglish' => true );
		PGR_Core::enqueue_field_assets( array( 'fields' => array( $national_id ) ), false );
		$this->assertSame( array( 'pgr-frontend' ), $GLOBALS['pgr_test_enqueued'] );
		$this->assertSame( array(), $GLOBALS['pgr_test_styles'] );
	}

	#[RunInSeparateProcess]
	public function test_scanner_assets_are_loaded_only_for_forms_containing_scanner_field() {
		eval( 'class GFForms { public static $version = "3.1.0"; }' );
		eval( 'class GF_Field { public $id = 1; public $size = ""; public $isRequired = false; public $failed_validation = false; public $validation_message = ""; public $errorMessage = ""; public $type = ""; public $forceEnglish = true; public $displayOnly = false; public function is_entry_detail(){return false;} public function is_form_editor(){return false;} public function get_field_placeholder_attribute(){return "";} }' );
		eval( 'class GF_Fields { public static $registered = array(); public static function register( $field ) { self::$registered[] = $field; } }' );

		require dirname( __DIR__ ) . '/persian-gravityforms.php';
		do_action( 'gform_loaded' );

		$scanner = (object) array( 'type' => 'pgr_structured_scanner' );
		PGR_Core::enqueue_field_assets( array( 'fields' => array( $scanner ) ), false );

		$this->assertSame(
			array( 'pgr-structured-scanner-core', 'pgr-structured-scanner' ),
			$GLOBALS['pgr_test_enqueued']
		);
		$this->assertSame( array( 'pgr-structured-scanner-style' ), $GLOBALS['pgr_test_styles'] );
	}
}
