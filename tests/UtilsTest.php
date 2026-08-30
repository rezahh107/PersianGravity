<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-pgr-utils.php';

final class UtilsTest extends TestCase {

	public function test_normalizes_persian_arabic_and_mixed_digits() {
		$this->assertSame( '0123456789', PGR_Utils::normalize_digits( '۰۱۲۳۴۵۶۷۸۹' ) );
		$this->assertSame( '0123456789', PGR_Utils::normalize_digits( '٠١٢٣٤٥٦٧٨٩' ) );
		$this->assertSame( 'A1234', PGR_Utils::normalize_digits( 'A1۲٣4' ) );
		$this->assertSame( '', PGR_Utils::normalize_digits( '' ) );
		$this->assertSame( 123, PGR_Utils::normalize_digits( 123 ) );
	}

	public function test_normalizes_national_id_without_accepting_malformed_input() {
		$this->assertSame( '0013546244', PGR_Utils::normalize_national_id( '۰۰۱-۳۵۴۶۲۴-۴' ) );
		$this->assertSame( '0013546244', PGR_Utils::normalize_national_id( '٠٠١ ٣٥٤٦٢٤ ٤' ) );
		$this->assertNull( PGR_Utils::normalize_national_id( '001354624x' ) );
		$this->assertNull( PGR_Utils::normalize_national_id( '123' ) );
		$this->assertNull( PGR_Utils::normalize_national_id( '' ) );
	}

	public function test_validates_iranian_national_id_checksum() {
		$this->assertTrue( PGR_Utils::is_valid_iran_national_id( '0013546244' ) );
		$this->assertTrue( PGR_Utils::is_valid_iran_national_id( '۱۲۳۴۵۶۷۸۹۱' ) );
		$this->assertFalse( PGR_Utils::is_valid_iran_national_id( '0013546245' ) );
		$this->assertFalse( PGR_Utils::is_valid_iran_national_id( '1111111111' ) );
		$this->assertFalse( PGR_Utils::is_valid_iran_national_id( 'invalid' ) );
	}
}
