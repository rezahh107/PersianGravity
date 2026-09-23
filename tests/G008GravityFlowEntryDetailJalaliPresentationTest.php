<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'PGR_PATH' ) ) {
	define( 'PGR_PATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'GRAVITY_FLOW_VERSION' ) ) {
	define( 'GRAVITY_FLOW_VERSION', '3.1.0' );
}
if ( ! defined( 'GRAVITY_FLOW_PLUGIN_BASENAME' ) ) {
	define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow/gravityflow.php' );
}
if ( ! class_exists( 'GFCommon' ) ) {
	final class GFCommon {
		public static function get_default_date_format() {
			return 'F j, Y';
		}
	}
}

require_once dirname( __DIR__ ) . '/includes/class-pgr-gregorian-jalali-converter.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-jalali-presentation.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-entry-detail-jalali-presentation-adapter.php';

final class G008GravityFlowEntryDetailJalaliPresentationTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pgr_test_filters'] = array();
	}

	public function test_registers_only_the_two_qualified_presentation_hooks(): void {
		$adapter = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();
		$adapter->hooks();

		$this->assertArrayHasKey( 'gravityflow_date_format_entry_detail', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayHasKey( 'date_i18n', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_step_due_date_timestamp', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_step_schedule_timestamp', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_step_expiration_timestamp', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_timeline_notes', $GLOBALS['pgr_test_filters'] );
	}

	public function test_empty_entry_detail_format_receives_unique_escaped_marker_and_native_date_format(): void {
		$adapter = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();
		$format  = $adapter->filter_entry_detail_date_format( '' );

		$this->assertSame( '\\P\\G\\R\\J\\A\\L\\A\\L\\I\\E\\N\\T\\R\\Y\\D\\E\\T\\A\\I\\L\\:F j, Y', $format );
	}

	public function test_nonempty_host_format_override_is_preserved(): void {
		$adapter = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();

		$this->assertSame( 'Y-m-d', $adapter->filter_entry_detail_date_format( 'Y-m-d' ) );
	}

	public function test_marked_local_timestamp_with_offset_uses_local_civil_date_without_second_timezone_conversion(): void {
		$adapter   = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();
		$format    = $adapter->filter_entry_detail_date_format( '' );
		$timestamp = gmmktime( 0, 3, 0, 3, 21, 2030 );

		$this->assertSame(
			'۱۴۰۹/۰۱/۰۱',
			$adapter->filter_marked_date(
				'PGRJALALIENTRYDETAIL:March 21, 2030',
				$format,
				$timestamp,
				true
			)
		);
	}

	public function test_unrelated_date_i18n_format_is_byte_for_byte_native(): void {
		$adapter = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();

		$this->assertSame(
			'2030-03-21 00:03',
			$adapter->filter_marked_date( '2030-03-21 00:03', 'Y-m-d H:i', 1900262580, true )
		);
	}

	public function test_out_of_range_marked_date_falls_back_without_marker_leak(): void {
		$adapter   = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();
		$format    = $adapter->filter_entry_detail_date_format( '' );
		$timestamp = gmmktime( 8, 30, 0, 3, 20, 2124 );

		$this->assertSame(
			'March 20, 2124',
			$adapter->filter_marked_date(
				'PGRJALALIENTRYDETAIL:March 20, 2124',
				$format,
				$timestamp,
				true
			)
		);
	}

	public function test_malformed_timestamp_falls_back_without_marker_leak(): void {
		$adapter = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();
		$format  = $adapter->filter_entry_detail_date_format( '' );

		$this->assertSame(
			'March 21, 2030',
			$adapter->filter_marked_date(
				'PGRJALALIENTRYDETAIL:March 21, 2030',
				$format,
				'not-a-timestamp',
				true
			)
		);
	}

	public function test_marker_is_removed_even_when_native_output_shape_is_unexpected(): void {
		$adapter = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();
		$format  = $adapter->filter_entry_detail_date_format( '' );

		$this->assertSame(
			'native March 21, 2030',
			$adapter->filter_marked_date(
				'native PGRJALALIENTRYDETAIL:March 21, 2030',
				$format,
				'not-a-timestamp',
				true
			)
		);
	}

	public function test_runtime_gate_reuses_existing_product_registry_and_does_not_touch_operational_getters(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-entry-detail-jalali-presentation-adapter.php' );

		$this->assertStringContainsString( "PGR_PATH . 'includes/localization/products.php'", $source );
		$this->assertStringContainsString( "HOST_VERSION_CONSTANT = 'GRAVITY_FLOW_VERSION'", $source );
		$this->assertStringContainsString( "HOST_BASENAME_CONSTANT = 'GRAVITY_FLOW_PLUGIN_BASENAME'", $source );
		$this->assertStringContainsString( "'gravityflow_date_format_entry_detail'", $source );
		$this->assertStringContainsString( "'date_i18n'", $source );
		$this->assertStringNotContainsString( 'get_due_date_timestamp()', $source );
		$this->assertStringNotContainsString( 'get_schedule_timestamp()', $source );
		$this->assertStringNotContainsString( 'get_expiration_timestamp()', $source );
		$this->assertStringNotContainsString( "'3.1.0'", $source );
		$this->assertStringNotContainsString( "add_filter( 'gravityflow_timeline_notes'", $source );
	}
}
