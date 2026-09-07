<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-pgr-module-registry.php';

final class ModuleRegistryTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pgr_test_options'] = array();
		$GLOBALS['pgr_test_option_autoload'] = array();
	}

	public function test_missing_option_keeps_all_current_modules_enabled() {
		$states = PGR_Module_Registry::get_states();
		$this->assertCount( 6, $states );
		foreach ( $states as $enabled ) {
			$this->assertTrue( $enabled );
		}
	}

	public function test_valid_disabled_state_is_respected_without_affecting_siblings() {
		$GLOBALS['pgr_test_options'][ PGR_Module_Registry::OPTION ] = array(
			'schema_version' => 1,
			'states' => array(
				'national_id' => false,
				'jalali_date' => true,
			),
		);

		$this->assertFalse( PGR_Module_Registry::is_enabled( 'national_id' ) );
		$this->assertTrue( PGR_Module_Registry::is_enabled( 'jalali_date' ) );
		$this->assertTrue( PGR_Module_Registry::is_enabled( 'structured_scanner' ) );
	}

	public function test_unknown_stored_id_and_malformed_values_are_ignored() {
		$GLOBALS['pgr_test_options'][ PGR_Module_Registry::OPTION ] = array(
			'schema_version' => 1,
			'states' => array(
				'unknown_module' => false,
				'national_id' => 'no',
			),
		);

		$this->assertTrue( PGR_Module_Registry::is_enabled( 'national_id' ) );
		$this->assertFalse( PGR_Module_Registry::exists( 'unknown_module' ) );
	}

	public function test_malformed_option_fails_to_defaults() {
		$GLOBALS['pgr_test_options'][ PGR_Module_Registry::OPTION ] = 'broken';
		$this->assertTrue( PGR_Module_Registry::is_enabled( 'national_id' ) );
		$this->assertTrue( PGR_Module_Registry::is_enabled( 'structured_scanner' ) );
	}

	public function test_unknown_module_cannot_be_toggled() {
		$this->assertFalse( PGR_Module_Registry::set_enabled( 'unknown_module', false ) );
		$this->assertArrayNotHasKey( PGR_Module_Registry::OPTION, $GLOBALS['pgr_test_options'] );
	}

	public function test_persistence_contains_only_schema_and_boolean_states() {
		$this->assertTrue( PGR_Module_Registry::set_enabled( 'national_id', false ) );
		$stored = $GLOBALS['pgr_test_options'][ PGR_Module_Registry::OPTION ];
		$this->assertSame( array( 'schema_version', 'states' ), array_keys( $stored ) );
		$this->assertFalse( $stored['states']['national_id'] );
		$this->assertArrayNotHasKey( 'label_fa', $stored );
		$this->assertTrue( (bool) $GLOBALS['pgr_test_option_autoload'][ PGR_Module_Registry::OPTION ] );
	}

	public function test_every_module_has_bilingual_metadata_and_help_topic() {
		foreach ( PGR_Module_Registry::all() as $module ) {
			$this->assertNotSame( '', $module['label_fa'] );
			$this->assertNotSame( '', $module['label_en'] );
			$this->assertNotSame( '', $module['description_fa'] );
			$this->assertNotSame( '', $module['description_en'] );
			$this->assertNotSame( '', $module['help_topic'] );
		}
	}
}
