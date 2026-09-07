<?php

use PHPUnit\Framework\TestCase;

final class ScannerProfileRegistryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		require_once dirname( __DIR__ ) . '/includes/class-pgr-scanner-profile-registry.php';
		$GLOBALS['pgr_test_options'] = array();
		$GLOBALS['pgr_test_option_autoload'] = array();
	}

	private function custom_profile( $id = 'custom_v1' ) {
		return array(
			'id' => $id, 'label' => 'Custom profile', 'enabled' => true,
			'parser' => array( 'type' => 'segments_v1', 'separator' => 'newline', 'trim' => true, 'normalize_digits' => true ),
			'outputs' => array(
				array( 'key' => 'first_value', 'label' => 'First value', 'required' => true ),
				array( 'key' => 'second_value', 'label' => 'Second value', 'required' => false ),
			),
		);
	}

	public function test_sayad_v01_has_the_exact_ordered_structural_outputs() {
		$this->assertSame( array( 'qr_version', 'owner_type', 'owner_identifier', 'iban', 'bank_branch', 'cheque_serial', 'sayad_id' ), PGR_Scanner_Profile_Registry::output_keys( 'sayad_v01' ) );
	}

	public function test_scanner_v1_target_types_are_bounded_to_reliable_scalar_fields() {
		$this->assertSame( array( 'text', 'hidden' ), PGR_Scanner_Profile_Registry::supported_target_types() );
		$this->assertNull( PGR_Scanner_Profile_Registry::get( 'unknown_profile' ) );
	}

	public function test_valid_custom_profile_is_persisted_in_versioned_non_autoloaded_option() {
		$result = PGR_Scanner_Profile_Registry::save_custom_profile( $this->custom_profile() );
		$this->assertTrue( $result['success'] );
		$this->assertSame( 1, $GLOBALS['pgr_test_options']['pgr_scanner_profiles']['schema_version'] );
		$this->assertCount( 1, $GLOBALS['pgr_test_options']['pgr_scanner_profiles']['profiles'] );
		$this->assertFalse( $GLOBALS['pgr_test_option_autoload']['pgr_scanner_profiles'] );
		$this->assertSame( 'custom_v1', PGR_Scanner_Profile_Registry::get( 'custom_v1' )['id'] );
	}

	public function test_runtime_profiles_include_only_valid_enabled_bounded_definitions() {
		$enabled = $this->custom_profile( 'enabled_v1' );
		$disabled = $this->custom_profile( 'disabled_v1' );
		$disabled['enabled'] = false;
		$this->assertTrue( PGR_Scanner_Profile_Registry::save_custom_profile( $enabled )['success'] );
		$this->assertTrue( PGR_Scanner_Profile_Registry::save_custom_profile( $disabled )['success'] );
		$ids = array_column( PGR_Scanner_Profile_Registry::runtime_profiles(), 'id' );
		$this->assertContains( 'sayad_v01', $ids );
		$this->assertContains( 'enabled_v1', $ids );
		$this->assertNotContains( 'disabled_v1', $ids );
	}

	public function test_invalid_profile_shapes_fail_closed() {
		$invalid = $this->custom_profile(); $invalid['parser']['type'] = 'regex';
		$this->assertFalse( PGR_Scanner_Profile_Registry::save_custom_profile( $invalid )['success'] );
		$invalid = $this->custom_profile(); $invalid['outputs'][1]['key'] = 'first_value';
		$this->assertContains( 'duplicate_output_key', PGR_Scanner_Profile_Registry::save_custom_profile( $invalid )['errors'] );
		$invalid = $this->custom_profile(); $invalid['outputs'] = array();
		$this->assertContains( 'zero_outputs', PGR_Scanner_Profile_Registry::save_custom_profile( $invalid )['errors'] );
	}

	public function test_profile_id_is_immutable() {
		$profile = $this->custom_profile();
		$this->assertTrue( PGR_Scanner_Profile_Registry::save_custom_profile( $profile )['success'] );
		$profile['id'] = 'renamed_v1';
		$this->assertContains( 'immutable_id', PGR_Scanner_Profile_Registry::save_custom_profile( $profile, 'custom_v1' )['errors'] );
	}

	public function test_existing_profile_output_contract_cannot_be_reordered_renamed_removed_or_redefined() {
		$profile = $this->custom_profile();
		$this->assertTrue( PGR_Scanner_Profile_Registry::save_custom_profile( $profile )['success'] );
		$reordered = $profile; $reordered['outputs'] = array_reverse( $reordered['outputs'] );
		$this->assertSame( array( 'immutable_contract' ), PGR_Scanner_Profile_Registry::save_custom_profile( $reordered, 'custom_v1' )['errors'] );
		$renamed = $profile; $renamed['outputs'][0]['key'] = 'renamed_value';
		$this->assertSame( array( 'immutable_contract' ), PGR_Scanner_Profile_Registry::save_custom_profile( $renamed, 'custom_v1' )['errors'] );
		$removed = $profile; array_pop( $removed['outputs'] );
		$this->assertSame( array( 'immutable_contract' ), PGR_Scanner_Profile_Registry::save_custom_profile( $removed, 'custom_v1' )['errors'] );
		$required_changed = $profile; $required_changed['outputs'][1]['required'] = true;
		$this->assertSame( array( 'immutable_contract' ), PGR_Scanner_Profile_Registry::save_custom_profile( $required_changed, 'custom_v1' )['errors'] );
	}

	public function test_existing_profile_label_and_enabled_state_can_change_without_contract_drift() {
		$profile = $this->custom_profile();
		$this->assertTrue( PGR_Scanner_Profile_Registry::save_custom_profile( $profile )['success'] );
		$profile['label'] = 'Renamed display label'; $profile['enabled'] = false; $profile['outputs'][0]['label'] = 'Renamed output label';
		$result = PGR_Scanner_Profile_Registry::save_custom_profile( $profile, 'custom_v1' );
		$this->assertTrue( $result['success'] );
		$this->assertSame( 'Renamed display label', PGR_Scanner_Profile_Registry::get( 'custom_v1' )['label'] );
		$this->assertFalse( PGR_Scanner_Profile_Registry::get( 'custom_v1' )['enabled'] );
	}

	public function test_malformed_stored_profiles_and_built_in_collisions_are_skipped() {
		$GLOBALS['pgr_test_options']['pgr_scanner_profiles'] = array( 'schema_version' => 1, 'profiles' => array( array( 'id' => 'broken' ), array_merge( $this->custom_profile( 'sayad_v01' ), array( 'id' => 'sayad_v01' ) ) ) );
		$this->assertSame( array(), PGR_Scanner_Profile_Registry::custom_profiles() );
		$this->assertSame( 'built_in', PGR_Scanner_Profile_Registry::get( 'sayad_v01' )['kind'] );
	}
}
