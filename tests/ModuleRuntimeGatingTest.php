<?php

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class ModuleRuntimeGatingTest extends TestCase {

	#[RunInSeparateProcess]
	public function test_disabled_national_id_is_not_loaded_registered_or_hooked() {
		$this->define_gravity_forms_stubs();
		$GLOBALS['pgr_test_options']['pgr_modules'] = $this->states_with_disabled( 'national_id' );
		require dirname( __DIR__ ) . '/persian-gravityforms.php';
		do_action( 'gform_loaded' );

		$this->assertFalse( class_exists( 'PGR_GF_Field_National_ID', false ) );
		$this->assertNotContains( 'pgr_national_id', $this->registered_types() );
		$this->assertArrayNotHasKey( 'gform_value_pre_duplicate_check', $GLOBALS['pgr_test_filters'] );

		PGR_Core::enqueue_field_assets( array( 'fields' => array( (object) array( 'type' => 'pgr_national_id', 'forceEnglish' => true ) ) ), false );
		$this->assertNotContains( 'pgr-frontend', $GLOBALS['pgr_test_enqueued'] );
	}

	#[RunInSeparateProcess]
	public function test_disabled_jalali_is_not_loaded_or_registered() {
		$this->define_gravity_forms_stubs();
		$GLOBALS['pgr_test_options']['pgr_modules'] = $this->states_with_disabled( 'jalali_date' );
		require dirname( __DIR__ ) . '/persian-gravityforms.php';
		do_action( 'gform_loaded' );
		$this->assertFalse( class_exists( 'PGR_GF_Field_Jalali_Date', false ) );
		$this->assertNotContains( 'pgr_jalali_date', $this->registered_types() );
	}

	#[RunInSeparateProcess]
	public function test_disabled_address_currency_and_digits_omit_owned_hooks() {
		$this->define_gravity_forms_stubs();
		$GLOBALS['pgr_test_options']['pgr_modules'] = array(
			'schema_version' => 1,
			'states' => array(
				'national_id' => true,
				'jalali_date' => true,
				'iranian_address' => false,
				'digit_normalization' => false,
				'iranian_currency' => false,
				'structured_scanner' => true,
			),
		);
		require dirname( __DIR__ ) . '/persian-gravityforms.php';
		do_action( 'gform_loaded' );

		$this->assertArrayNotHasKey( 'gform_address_types', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gform_predefined_choices', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gform_currencies', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gform_form_settings_fields', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gform_save_field_value', $GLOBALS['pgr_test_filters'] );
	}

	#[RunInSeparateProcess]
	public function test_disabled_scanner_is_not_loaded_registered_or_enqueued() {
		$this->define_gravity_forms_stubs();
		$GLOBALS['pgr_test_options']['pgr_modules'] = $this->states_with_disabled( 'structured_scanner' );
		require dirname( __DIR__ ) . '/persian-gravityforms.php';
		do_action( 'gform_loaded' );

		$this->assertFalse( class_exists( 'PGR_GF_Field_Structured_Scanner', false ) );
		$this->assertNotContains( 'pgr_structured_scanner', $this->registered_types() );
		PGR_Core::enqueue_field_assets( array( 'fields' => array( (object) array( 'type' => 'pgr_structured_scanner' ) ) ), false );
		$this->assertNotContains( 'pgr-structured-scanner-core', $GLOBALS['pgr_test_enqueued'] );
		$this->assertNotContains( 'pgr-structured-scanner-style', $GLOBALS['pgr_test_styles'] );

		$bootstrap = file_get_contents( dirname( __DIR__ ) . '/persian-gravityforms.php' );
		$this->assertStringContainsString( "require_once PGR_PATH . 'includes/class-pgr-scanner-profile-registry.php';", $bootstrap );
		$this->assertStringContainsString( 'pgr_initialize_admin', $bootstrap );
	}

	private function define_gravity_forms_stubs() {
		eval( 'class GFForms { public static $version = "3.1.0"; }' );
		eval( 'class GF_Field { public $id = 1; public $size = ""; public $isRequired = false; public $failed_validation = false; public $validation_message = ""; public $errorMessage = ""; public $type = ""; public $forceEnglish = true; public $displayOnly = false; public function is_entry_detail(){return false;} public function is_form_editor(){return false;} public function get_field_placeholder_attribute(){return "";} }' );
		eval( 'class GF_Fields { public static $registered = array(); public static function register( $field ) { self::$registered[] = $field; } }' );
	}

	private function states_with_disabled( $disabled ) {
		$ids = array( 'national_id', 'jalali_date', 'iranian_address', 'digit_normalization', 'iranian_currency', 'structured_scanner' );
		$states = array();
		foreach ( $ids as $id ) {
			$states[ $id ] = $id !== $disabled;
		}
		return array( 'schema_version' => 1, 'states' => $states );
	}

	private function registered_types() {
		return array_map(
			static function ( $field ) {
				return $field->type;
			},
			GF_Fields::$registered
		);
	}
}
