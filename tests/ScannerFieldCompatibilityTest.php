<?php

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class ScannerFieldCompatibilityTest extends TestCase {
	private function boot_scanner_runtime() {
		eval( 'class GFForms { public static $version = "3.1.0"; }' );
		eval( 'class GF_Field { public $id = 7; public $size = ""; public $isRequired = false; public $failed_validation = false; public $validation_message = ""; public $errorMessage = ""; public $type = ""; public $forceEnglish = true; public $displayOnly = false; public function is_entry_detail(){return false;} public function is_form_editor(){return false;} public function get_field_placeholder_attribute(){return "";} }' );
		eval( 'class GF_Fields { public static $registered = array(); public static function register( $field ) { self::$registered[] = $field; } }' );
		require dirname( __DIR__ ) . '/persian-gravityforms.php';
		do_action( 'gform_loaded' );
		return GF_Fields::$registered[2];
	}

	private function runtime_mappings( $scanner ) {
		$method = new ReflectionMethod( PGR_GF_Field_Structured_Scanner::class, 'runtime_mappings' );
		$method->setAccessible( true );
		return $method->invoke( $scanner );
	}

	#[RunInSeparateProcess]
	public function test_existing_positional_sayad_mappings_keep_their_original_output_meaning() {
		$scanner = $this->boot_scanner_runtime();
		$scanner->scanner_profile = 'sayad_v01';
		$scanner->scanner_mappings = array( '10', '11', '12', '13', '14', '15', '16' );
		$this->assertSame( array( 'qr_version' => '10', 'owner_type' => '11', 'owner_identifier' => '12', 'iban' => '13', 'bank_branch' => '14', 'cheque_serial' => '15', 'sayad_id' => '16' ), $this->runtime_mappings( $scanner ) );
	}

	#[RunInSeparateProcess]
	public function test_key_based_mapping_is_order_independent_and_unknown_keys_fail_closed() {
		$scanner = $this->boot_scanner_runtime();
		$scanner->scanner_profile = 'sayad_v01';
		$scanner->scanner_mappings = array( 'sayad_id' => '16', 'qr_version' => '10', 'iban' => '13' );
		$this->assertSame( array( 'sayad_id' => '16', 'qr_version' => '10', 'iban' => '13' ), $this->runtime_mappings( $scanner ) );
		$scanner->scanner_mappings['renamed_output'] = '99';
		$this->assertNull( $this->runtime_mappings( $scanner ) );
	}

	#[RunInSeparateProcess]
	public function test_php_serializes_enabled_custom_runtime_profiles_before_scanner_core() {
		$scanner = $this->boot_scanner_runtime();
		$profile = array(
			'id' => 'customer_card_v1', 'label' => 'Customer card', 'enabled' => true,
			'parser' => array( 'type' => 'segments_v1', 'separator' => 'newline', 'trim' => true, 'normalize_digits' => true ),
			'outputs' => array( array( 'key' => 'customer_id', 'label' => 'Customer ID', 'required' => true ), array( 'key' => 'note', 'label' => 'Note', 'required' => false ) ),
		);
		$this->assertTrue( PGR_Scanner_Profile_Registry::save_custom_profile( $profile )['success'] );
		PGR_Core::enqueue_field_assets( array( 'fields' => array( $scanner ) ), false );
		$this->assertCount( 1, $GLOBALS['pgr_test_inline_scripts'] );
		$this->assertSame( 'pgr-structured-scanner-core', $GLOBALS['pgr_test_inline_scripts'][0]['handle'] );
		$this->assertSame( 'before', $GLOBALS['pgr_test_inline_scripts'][0]['position'] );
		$this->assertStringContainsString( 'window.PGRScannerProfiles = ', $GLOBALS['pgr_test_inline_scripts'][0]['data'] );
		$this->assertStringContainsString( 'customer_card_v1', $GLOBALS['pgr_test_inline_scripts'][0]['data'] );
		$this->assertStringContainsString( 'sayad_v01', $GLOBALS['pgr_test_inline_scripts'][0]['data'] );
	}

	#[RunInSeparateProcess]
	public function test_frontend_message_attributes_cover_all_runtime_states() {
		$scanner = $this->boot_scanner_runtime();
		$markup = $scanner->get_field_input( array( 'id' => 1, 'fields' => array( $scanner ) ) );
		$this->assertStringContainsString( 'data-pgr-message-ready=', $markup );
		$this->assertStringContainsString( 'data-pgr-message-processing=', $markup );
		$this->assertStringContainsString( 'data-pgr-message-success=', $markup );
		$this->assertStringContainsString( 'data-pgr-message-invalid=', $markup );
		$this->assertStringContainsString( 'data-pgr-message-configuration=', $markup );
		$this->assertStringNotContainsString( 'data-pgr-message-segment-count=', $markup );
		$runtime_js = file_get_contents( dirname( __DIR__ ) . '/assets/js/pgr-structured-scanner.js' );
		$this->assertStringNotContainsString( 'pgrMessageSegmentCount', $runtime_js );
		$this->assertStringContainsString( 'pgrMessageConfiguration', $runtime_js );
	}

	public function test_all_referenced_scanner_admin_assets_are_shipped() {
		$root = dirname( __DIR__ );
		$this->assertFileExists( $root . '/assets/css/pgr-admin.css' );
		$this->assertFileExists( $root . '/assets/js/pgr-admin-profiles.js' );
		$this->assertFileExists( $root . '/assets/css/pgr-scanner-editor.css' );
		$this->assertFileExists( $root . '/assets/js/pgr-structured-scanner-core.js' );
		$this->assertFileExists( $root . '/assets/js/pgr-structured-scanner.js' );
	}
}
