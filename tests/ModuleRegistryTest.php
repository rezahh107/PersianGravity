<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-pgr-module-registry.php';

final class ModuleRegistryTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pgr_test_options'] = array();
		$GLOBALS['pgr_test_option_autoload'] = array();
	}

	public function test_missing_option_preserves_legacy_defaults_and_keeps_presentation_opt_in() {
		$states = PGR_Module_Registry::get_states();
		$this->assertCount( 7, $states );
		$this->assertFalse( $states['jalali_presentation'] );

		foreach ( array( 'national_id', 'jalali_date', 'iranian_address', 'digit_normalization', 'iranian_currency', 'structured_scanner' ) as $id ) {
			$this->assertTrue( $states[ $id ], $id );
		}
	}

	public function test_existing_six_module_state_inherits_new_disabled_default_without_schema_migration() {
		$GLOBALS['pgr_test_options'][ PGR_Module_Registry::OPTION ] = array(
			'schema_version' => 1,
			'states'         => array(
				'national_id'         => false,
				'jalali_date'         => true,
				'iranian_address'     => true,
				'digit_normalization' => true,
				'iranian_currency'    => true,
				'structured_scanner'  => true,
			),
		);

		$this->assertFalse( PGR_Module_Registry::is_enabled( 'national_id' ) );
		$this->assertTrue( PGR_Module_Registry::is_enabled( 'jalali_date' ) );
		$this->assertFalse( PGR_Module_Registry::is_enabled( 'jalali_presentation' ) );
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
		$this->assertFalse( PGR_Module_Registry::is_enabled( 'jalali_presentation' ) );
		$this->assertFalse( PGR_Module_Registry::exists( 'unknown_module' ) );
	}

	public function test_malformed_option_fails_to_source_defaults() {
		$GLOBALS['pgr_test_options'][ PGR_Module_Registry::OPTION ] = 'broken';
		$this->assertTrue( PGR_Module_Registry::is_enabled( 'national_id' ) );
		$this->assertTrue( PGR_Module_Registry::is_enabled( 'structured_scanner' ) );
		$this->assertFalse( PGR_Module_Registry::is_enabled( 'jalali_presentation' ) );
	}

	public function test_unknown_module_cannot_be_toggled() {
		$this->assertFalse( PGR_Module_Registry::set_enabled( 'unknown_module', false ) );
		$this->assertArrayNotHasKey( PGR_Module_Registry::OPTION, $GLOBALS['pgr_test_options'] );
	}

	public function test_persistence_contains_only_schema_and_boolean_states() {
		$this->assertTrue( PGR_Module_Registry::set_enabled( 'jalali_presentation', true ) );
		$stored = $GLOBALS['pgr_test_options'][ PGR_Module_Registry::OPTION ];
		$this->assertSame( array( 'schema_version', 'states' ), array_keys( $stored ) );
		$this->assertTrue( $stored['states']['jalali_presentation'] );
		$this->assertTrue( $stored['states']['jalali_date'] );
		$this->assertArrayNotHasKey( 'label_fa', $stored );
		$this->assertTrue( (bool) $GLOBALS['pgr_test_option_autoload'][ PGR_Module_Registry::OPTION ] );
	}

	public function test_jalali_date_and_presentation_states_are_independent() {
		$this->assertTrue( PGR_Module_Registry::set_enabled( 'jalali_date', false ) );
		$this->assertFalse( PGR_Module_Registry::is_enabled( 'jalali_date' ) );
		$this->assertFalse( PGR_Module_Registry::is_enabled( 'jalali_presentation' ) );

		$this->assertTrue( PGR_Module_Registry::set_enabled( 'jalali_presentation', true ) );
		$this->assertFalse( PGR_Module_Registry::is_enabled( 'jalali_date' ) );
		$this->assertTrue( PGR_Module_Registry::is_enabled( 'jalali_presentation' ) );
	}

	public function test_all_four_jalali_module_state_combinations_are_representable() {
		foreach (
			array(
				array( true, true ),
				array( true, false ),
				array( false, true ),
				array( false, false ),
			) as $case
		) {
			$GLOBALS['pgr_test_options'][ PGR_Module_Registry::OPTION ] = array(
				'schema_version' => 1,
				'states'         => array(
					'jalali_date'         => $case[0],
					'jalali_presentation' => $case[1],
				),
			);
			$this->assertSame( $case[0], PGR_Module_Registry::is_enabled( 'jalali_date' ) );
			$this->assertSame( $case[1], PGR_Module_Registry::is_enabled( 'jalali_presentation' ) );
		}
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
