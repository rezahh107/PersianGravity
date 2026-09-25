<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class ModuleRuntimeGatingTest extends TestCase {

	public static function jalali_state_cases(): array {
		return array(
			'field on / presentation on'   => array( true, true ),
			'field on / presentation off'  => array( true, false ),
			'field off / presentation on'  => array( false, true ),
			'field off / presentation off' => array( false, false ),
		);
	}

	#[DataProvider( 'jalali_state_cases' )]
	#[RunInSeparateProcess]
	public function test_jalali_field_and_presentation_runtime_states_are_independent( bool $field_enabled, bool $presentation_enabled ) {
		$this->define_gravity_forms_stubs();
		$GLOBALS['pgr_test_options']['pgr_modules'] = array(
			'schema_version' => 1,
			'states'         => array(
				'national_id'         => false,
				'jalali_date'         => $field_enabled,
				'jalali_presentation' => $presentation_enabled,
				'iranian_address'     => false,
				'digit_normalization' => false,
				'iranian_currency'    => false,
				'structured_scanner'  => false,
			),
		);

		require dirname( __DIR__ ) . '/persian-gravityforms.php';
		do_action( 'gform_loaded' );

		$this->assertSame( $field_enabled, class_exists( 'PGR_Persian_Date', false ) );
		$this->assertSame( $field_enabled, class_exists( 'PGR_GF_Field_Jalali_Date', false ) );
		$this->assertSame( $field_enabled, in_array( 'pgr_jalali_date', $this->registered_types(), true ) );
		$this->assertSame( $presentation_enabled, class_exists( 'PGR_Gregorian_Jalali_Converter', false ) );
		$this->assertSame( $presentation_enabled, class_exists( 'PGR_Jalali_Presentation', false ) );
		$this->assertSame( $presentation_enabled, class_exists( 'PGR_GF_Jalali_Presentation_Adapter', false ) );
		$this->assertSame( $presentation_enabled, class_exists( 'PGR_Gravity_Flow_Inbox_Jalali_Presentation_Adapter', false ) );
		$this->assertSame( $presentation_enabled, class_exists( 'PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter', false ) );
		$this->assertSame( $presentation_enabled, class_exists( 'PGR_GravityView_Jalali_Presentation_Adapter', false ) );
		$this->assertSame( $presentation_enabled, isset( $GLOBALS['pgr_test_filters']['gform_entries_field_value'] ) );
		$this->assertSame( $presentation_enabled, isset( $GLOBALS['pgr_test_filters']['gravityflow_inbox_field_value'] ) );
		$this->assertSame( $presentation_enabled, isset( $GLOBALS['pgr_test_filters']['gravityflow_status_args'] ) );
		$this->assertSame( $presentation_enabled, isset( $GLOBALS['pgr_test_filters']['gravityflow_entry_url_status_table'] ) );
		$this->assertSame( $presentation_enabled, isset( $GLOBALS['pgr_test_filters']['gravityflow_field_value_status_table'] ) );
		$this->assertSame( $presentation_enabled, isset( $GLOBALS['pgr_test_filters']['gravityview/template/field/date_created/output'] ) );
		$this->assertSame( $presentation_enabled, isset( $GLOBALS['pgr_test_filters']['gravityview/template/field/date_updated/output'] ) );
	}

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
	public function test_disabled_presentation_is_not_loaded_or_hooked() {
		$this->define_gravity_forms_stubs();
		$GLOBALS['pgr_test_options']['pgr_modules'] = $this->states_with_disabled( 'jalali_presentation' );
		require dirname( __DIR__ ) . '/persian-gravityforms.php';
		do_action( 'gform_loaded' );

		$this->assertFalse( class_exists( 'PGR_Jalali_Presentation', false ) );
		$this->assertFalse( class_exists( 'PGR_GF_Jalali_Presentation_Adapter', false ) );
		$this->assertFalse( class_exists( 'PGR_Gravity_Flow_Inbox_Jalali_Presentation_Adapter', false ) );
		$this->assertFalse( class_exists( 'PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter', false ) );
		$this->assertFalse( class_exists( 'PGR_GravityView_Jalali_Presentation_Adapter', false ) );
		$this->assertArrayNotHasKey( 'gform_entries_field_value', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_inbox_field_value', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_status_args', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_entry_url_status_table', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_field_value_status_table', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityview/template/field/date_created/output', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityview/template/field/date_updated/output', $GLOBALS['pgr_test_filters'] );
	}

	#[RunInSeparateProcess]
	public function test_disabled_address_currency_and_digits_omit_owned_hooks() {
		$this->define_gravity_forms_stubs();
		$GLOBALS['pgr_test_options']['pgr_modules'] = array(
			'schema_version' => 1,
			'states' => array(
				'national_id' => true,
				'jalali_date' => true,
				'jalali_presentation' => false,
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
		$states = array_fill_keys( $ids, true );
		$states['jalali_presentation'] = false;
		$states[ $disabled ] = false;
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
