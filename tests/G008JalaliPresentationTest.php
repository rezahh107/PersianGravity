<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-pgr-gregorian-jalali-converter.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-jalali-presentation.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-gf-jalali-presentation-adapter.php';

final class G008JalaliPresentationTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pgr_test_timezone'] = 'UTC';
	}

	public static function official_calendar_1405_cases(): array {
		$fixture = require __DIR__ . '/fixtures/g008-official-calendar-1405.php';
		return $fixture['cases'];
	}

	#[DataProvider( 'official_calendar_1405_cases' )]
	public function test_official_1405_golden_cases( string $gregorian, string $jalali ): void {
		list( $year, $month, $day ) = array_map( 'intval', explode( '-', $gregorian ) );
		$result = PGR_Gregorian_Jalali_Converter::to_jalali( $year, $month, $day );
		$this->assertNotNull( $result );
		$this->assertSame(
			$jalali,
			sprintf( '%04d-%02d-%02d', $result['year'], $result['month'], $result['day'] )
		);
	}

	public function test_known_borkowski_reference_vector_and_gregorian_leap_boundary(): void {
		$this->assertSame(
			array( 'year' => 1395, 'month' => 1, 'day' => 23 ),
			PGR_Gregorian_Jalali_Converter::to_jalali( 2016, 4, 11 )
		);
		$this->assertSame(
			array( 'year' => 1398, 'month' => 12, 'day' => 10 ),
			PGR_Gregorian_Jalali_Converter::to_jalali( 2020, 2, 29 )
		);
	}

	public function test_round_trip_is_consistent_across_validated_product_range(): void {
		$start = new DateTimeImmutable( PGR_Jalali_Presentation::VALIDATED_PRODUCT_MIN, new DateTimeZone( 'UTC' ) );
		$end   = new DateTimeImmutable( PGR_Jalali_Presentation::VALIDATED_PRODUCT_MAX, new DateTimeZone( 'UTC' ) );

		for ( $date = $start; $date <= $end; $date = $date->modify( '+1 day' ) ) {
			$gregorian = array(
				'year'  => (int) $date->format( 'Y' ),
				'month' => (int) $date->format( 'n' ),
				'day'   => (int) $date->format( 'j' ),
			);
			$jalali = PGR_Gregorian_Jalali_Converter::to_jalali( $gregorian['year'], $gregorian['month'], $gregorian['day'] );
			$this->assertNotNull( $jalali, $date->format( 'Y-m-d' ) );
			$this->assertSame(
				$gregorian,
				PGR_Gregorian_Jalali_Converter::to_gregorian( $jalali['year'], $jalali['month'], $jalali['day'] ),
				$date->format( 'Y-m-d' )
			);
		}
	}

	public function test_product_range_boundaries_and_native_fallback_contract(): void {
		$this->assertSame( '۱۱۷۸/۱۰/۱۱', PGR_Jalali_Presentation::format_date( 1800, 1, 1 ) );
		$this->assertSame( '۱۵۰۲/۱۲/۲۹', PGR_Jalali_Presentation::format_date( 2124, 3, 19 ) );
		$this->assertNull( PGR_Jalali_Presentation::format_date( 1799, 12, 31 ) );
		$this->assertNull( PGR_Jalali_Presentation::format_date( 2124, 3, 20 ) );
		$this->assertNull( PGR_Jalali_Presentation::format_date( 2026, 2, 30 ) );
	}

	public function test_existing_facade_is_the_typed_feature_detectable_consumer_contract(): void {
		$this->assertTrue( class_exists( 'PGR_Jalali_Presentation', false ) );
		$this->assertTrue( is_callable( array( 'PGR_Jalali_Presentation', 'format_datetime' ) ) );
		$this->assertTrue( is_callable( array( 'PGR_Jalali_Presentation', 'format_date' ) ) );

		$method = new ReflectionMethod( 'PGR_Jalali_Presentation', 'format_datetime' );
		$parameters = $method->getParameters();
		$this->assertCount( 2, $parameters );
		$this->assertSame( 'DateTimeInterface', (string) $parameters[0]->getType() );
		$this->assertSame( '?DateTimeZone', (string) $parameters[1]->getType() );
		$this->assertSame( '?string', (string) $method->getReturnType() );
	}

	public function test_repeatability_is_deterministic(): void {
		$first = PGR_Gregorian_Jalali_Converter::to_jalali( 2026, 3, 21 );
		for ( $index = 0; $index < 100; $index++ ) {
			$this->assertSame( $first, PGR_Gregorian_Jalali_Converter::to_jalali( 2026, 3, 21 ) );
		}
	}

	public function test_datetime_applies_target_timezone_before_calendar_conversion(): void {
		$source = new DateTimeImmutable( '2026-03-20 22:15:00', new DateTimeZone( 'UTC' ) );
		$this->assertSame(
			'۱۴۰۵/۰۱/۰۱، ۰۱:۴۵',
			PGR_Jalali_Presentation::format_datetime( $source, new DateTimeZone( 'Asia/Tehran' ) )
		);
		$this->assertSame(
			'۱۴۰۴/۱۲/۲۹، ۲۲:۱۵',
			PGR_Jalali_Presentation::format_datetime( $source, new DateTimeZone( 'UTC' ) )
		);
	}

	public function test_datetime_can_cross_previous_local_day_without_guessing_offset(): void {
		$source = new DateTimeImmutable( '2026-03-21 00:30:00', new DateTimeZone( 'UTC' ) );
		$this->assertSame(
			'۱۴۰۴/۱۲/۲۹، ۱۷:۳۰',
			PGR_Jalali_Presentation::format_datetime( $source, new DateTimeZone( 'America/Los_Angeles' ) )
		);
	}

	public function test_historical_timezone_uses_iana_database(): void {
		$source = new DateTimeImmutable( '1978-08-04 19:30:00', new DateTimeZone( 'UTC' ) );
		$local  = DateTimeImmutable::createFromInterface( $source )->setTimezone( new DateTimeZone( 'Asia/Tehran' ) );
		$this->assertSame( '1978-08-05', $local->format( 'Y-m-d' ) );
		$this->assertSame(
			PGR_Jalali_Presentation::format_date( 1978, 8, 5 ) . '، ' . $this->persian_digits( $local->format( 'H:i' ) ),
			PGR_Jalali_Presentation::format_datetime( $source, new DateTimeZone( 'Asia/Tehran' ) )
		);
	}

	public function test_date_only_path_does_not_timezone_shift(): void {
		$GLOBALS['pgr_test_timezone'] = 'Pacific/Kiritimati';
		$this->assertSame( '۱۴۰۵/۰۱/۰۱', PGR_Jalali_Presentation::format_date( 2026, 3, 21 ) );
	}

	public function test_adapter_is_bounded_to_date_created_and_preserves_machine_value(): void {
		$GLOBALS['pgr_test_timezone'] = 'Asia/Tehran';
		$entry = array(
			'id'           => 9,
			'date_created' => '2026-03-20 22:15:00',
			'1'            => 'untouched',
		);
		$original = $entry;

		$this->assertSame(
			'۱۴۰۵/۰۱/۰۱، ۰۱:۴۵',
			PGR_GF_Jalali_Presentation_Adapter::filter_entry_list_value( 'native', 1, 'date_created', $entry )
		);
		$this->assertSame( 'native-field', PGR_GF_Jalali_Presentation_Adapter::filter_entry_list_value( 'native-field', 1, '1', $entry ) );
		$this->assertSame( $original, $entry );
	}

	public function test_adapter_never_reinterprets_jalali_domain_field_values_as_gregorian(): void {
		$entry = array(
			'date_created' => '2026-03-21 00:00:00',
			'7'            => '1405-01-01',
		);
		$this->assertSame(
			'1405-01-01',
			PGR_GF_Jalali_Presentation_Adapter::filter_entry_list_value( '1405-01-01', 1, '7', $entry )
		);
		$this->assertSame(
			'1405-01-01',
			PGR_GF_Jalali_Presentation_Adapter::filter_entry_list_value( '1405-01-01', 1, 'pgr_jalali_date', $entry )
		);
	}

	public function test_adapter_falls_back_for_malformed_or_out_of_range_raw_values(): void {
		$this->assertSame(
			'native',
			PGR_GF_Jalali_Presentation_Adapter::filter_entry_list_value( 'native', 1, 'date_created', array( 'date_created' => 'not-a-date' ) )
		);
		$this->assertSame(
			'native',
			PGR_GF_Jalali_Presentation_Adapter::filter_entry_list_value( 'native', 1, 'date_created', array( 'date_created' => '2124-03-20 00:00:00' ) )
		);
	}

	public function test_adapter_registers_only_the_documented_entry_list_display_filter(): void {
		$adapter = new PGR_GF_Jalali_Presentation_Adapter();
		$adapter->hooks();
		$this->assertArrayHasKey( 'gform_entries_field_value', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gform_save_field_value', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gform_get_entries_args_entry_list', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gform_search_criteria_entry_list', $GLOBALS['pgr_test_filters'] );
	}

	private function persian_digits( string $value ): string {
		return strtr( $value, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
	}
}
