<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/admin/class-pgr-admin.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-address.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-currency.php';

final class ProductServicesTest extends TestCase {

	public function test_settings_are_reduced_to_sanitized_active_values() {
		$admin = new PGR_Admin();
		$this->assertSame(
			array( 'default_force_english' => 1 ),
			$admin->sanitize_settings( array( 'default_force_english' => 'yes', 'legacy' => 'drop-me' ) )
		);
		$this->assertSame( array( 'default_force_english' => 0 ), $admin->sanitize_settings( array() ) );
	}

	public function test_iran_address_type_and_provinces_are_generic_and_bounded() {
		$address = new PGR_Address();
		$types   = $address->add_iran_address_type( array(), 1 );
		$this->assertArrayHasKey( 'iran', $types );
		$this->assertCount( 32, $types['iran']['states'] );
		$this->assertContains( 'تهران', $types['iran']['states'] );
	}

	public function test_iranian_currencies_are_added_without_payment_logic() {
		$currency   = new PGR_Currency();
		$currencies = $currency->add_iranian_currencies( array() );
		$this->assertSame( 'IRR', $currencies['IRR']['code'] );
		$this->assertSame( 'IRT', $currencies['IRT']['code'] );
		$this->assertSame( 0, $currencies['IRT']['decimals'] );
	}
}
