<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-pgr-utils.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-persian-date.php';

final class JalaliTest extends TestCase {

	public function test_parses_gravity_forms_date_orders() {
		$this->assertSame(
			array( 'year' => 1403, 'month' => 12, 'day' => 30 ),
			PGR_Persian_Date::parse_date( '۱۴۰۳/۱۲/۳۰', 'ymd_slash' )
		);
		$this->assertSame(
			array( 'year' => 1403, 'month' => 12, 'day' => 30 ),
			PGR_Persian_Date::parse_date( '30/12/1403', 'dmy' )
		);
		$this->assertSame(
			array( 'year' => 1403, 'month' => 12, 'day' => 30 ),
			PGR_Persian_Date::parse_date( '12/30/1403', 'mdy' )
		);
		$this->assertFalse( PGR_Persian_Date::parse_date( '1403', 'ymd_slash' ) );
	}

	public function test_validates_month_lengths_and_leap_years() {
		$this->assertTrue( PGR_Persian_Date::is_valid_date( 1403, 12, 30 ) );
		$this->assertFalse( PGR_Persian_Date::is_valid_date( 1404, 12, 30 ) );
		$this->assertTrue( PGR_Persian_Date::is_valid_date( 1404, 1, 31 ) );
		$this->assertFalse( PGR_Persian_Date::is_valid_date( 1404, 7, 31 ) );
		$this->assertFalse( PGR_Persian_Date::is_valid_date( 1404, 13, 1 ) );
	}
}
