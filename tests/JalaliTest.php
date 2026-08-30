<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-pgr-utils.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-persian-date.php';

final class JalaliTest extends TestCase {

	public static function format_cases() {
		return array(
			'mdy'       => array( 'mdy', '02/31/1404', '02/31/1404' ),
			'dmy'       => array( 'dmy', '31/02/1404', '31/02/1404' ),
			'dmy_dash'  => array( 'dmy_dash', '31-02-1404', '31-02-1404' ),
			'dmy_dot'   => array( 'dmy_dot', '31.02.1404', '31.02.1404' ),
			'ymd_slash' => array( 'ymd_slash', '1404/02/31', '1404/02/31' ),
			'ymd_dash'  => array( 'ymd_dash', '1404-02-31', '1404-02-31' ),
			'ymd_dot'   => array( 'ymd_dot', '1404.02.31', '1404.02.31' ),
		);
	}

	#[DataProvider( 'format_cases' )]
	public function test_all_formats_round_trip_through_canonical_storage( $format, $input, $display ) {
		$canonical = PGR_Persian_Date::canonicalize( $input, $format );
		$this->assertSame( '1404-02-31', $canonical );
		$this->assertSame( $display, PGR_Persian_Date::format_canonical( $canonical, $format ) );
	}

	public function test_accepts_ascii_persian_and_arabic_indic_digits() {
		$this->assertSame( '1404-02-31', PGR_Persian_Date::canonicalize( '1404/02/31', 'ymd_slash' ) );
		$this->assertSame( '1404-02-31', PGR_Persian_Date::canonicalize( '۱۴۰۴/۰۲/۳۱', 'ymd_slash' ) );
		$this->assertSame( '1404-02-31', PGR_Persian_Date::canonicalize( '١٤٠٤/٠٢/٣١', 'ymd_slash' ) );
	}

	public function test_validates_jalali_calendar_boundaries() {
		$this->assertTrue( PGR_Persian_Date::is_valid_date( 1404, 2, 31 ) );
		$this->assertFalse( PGR_Persian_Date::is_valid_date( 1404, 7, 31 ) );
		$this->assertTrue( PGR_Persian_Date::is_valid_date( 1403, 12, 30 ) );
		$this->assertFalse( PGR_Persian_Date::is_valid_date( 1404, 12, 30 ) );
		$this->assertFalse( PGR_Persian_Date::is_valid_date( 1404, 13, 1 ) );
	}

	public function test_malformed_partial_and_wrong_separator_inputs_fail() {
		$this->assertNull( PGR_Persian_Date::canonicalize( '1404', 'ymd_slash' ) );
		$this->assertNull( PGR_Persian_Date::canonicalize( '1404/02', 'ymd_slash' ) );
		$this->assertNull( PGR_Persian_Date::canonicalize( '1404-02-31', 'ymd_slash' ) );
		$this->assertNull( PGR_Persian_Date::canonicalize( '1404/07/31', 'ymd_slash' ) );
		$this->assertSame( '', PGR_Persian_Date::canonicalize( '', 'ymd_slash' ) );
	}

	public function test_canonical_storage_is_strict_ascii_ymd_and_never_gregorian_converted() {
		$this->assertSame( '1404-02-31', PGR_Persian_Date::canonicalize( '۱۴۰۴/۰۲/۳۱', 'ymd_slash' ) );
		$this->assertSame(
			array( 'year' => 1404, 'month' => 2, 'day' => 31 ),
			PGR_Persian_Date::parse_canonical( '1404-02-31' )
		);
		$this->assertSame( '1404-02-31', PGR_Persian_Date::canonicalize( '1404/02/31', 'ymd_slash' ) );
	}
}
