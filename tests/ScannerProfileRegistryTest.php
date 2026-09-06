<?php

use PHPUnit\Framework\TestCase;

final class ScannerProfileRegistryTest extends TestCase {

	public function test_sayad_v01_has_the_exact_ordered_structural_outputs() {
		require_once dirname( __DIR__ ) . '/includes/class-pgr-scanner-profile-registry.php';

		$this->assertSame(
			array(
				'qr_version',
				'owner_type',
				'owner_identifier',
				'iban',
				'bank_branch',
				'cheque_serial',
				'sayad_id',
			),
			PGR_Scanner_Profile_Registry::output_keys( 'sayad_v01' )
		);
	}

	public function test_scanner_v01_target_types_are_bounded_to_reliable_scalar_fields() {
		require_once dirname( __DIR__ ) . '/includes/class-pgr-scanner-profile-registry.php';

		$this->assertSame( array( 'text', 'hidden' ), PGR_Scanner_Profile_Registry::supported_target_types() );
		$this->assertNull( PGR_Scanner_Profile_Registry::get( 'unknown_profile' ) );
	}
}
