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
if ( ! class_exists( 'Gravity_Flow_Status_Table', false ) ) {
	class Gravity_Flow_Status_Table {
		public function filter_field_value( $adapter, $value, $column_name, $entry ) {
			return $adapter->filter_status_value( $value, 1, $column_name, $entry );
		}
	}
}

require_once dirname( __DIR__ ) . '/includes/class-pgr-gregorian-jalali-converter.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-jalali-presentation.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-status-jalali-presentation-adapter.php';

final class G008GravityFlowStatusJalaliPresentationTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pgr_test_timezone'] = 'Asia/Tehran';
		$GLOBALS['pgr_test_filters']  = array();
	}

	public function test_date_created_uses_authoritative_utc_entry_value_and_preserves_entry(): void {
		$entry    = array( 'date_created' => '2026-03-20 22:15:00', 'workflow_timestamp' => '1774132200' );
		$original = $entry;
		$adapter  = new PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter();
		$table    = new Gravity_Flow_Status_Table();

		$this->assertSame(
			'۱۴۰۵/۰۱/۰۱، ۰۱:۴۵',
			$table->filter_field_value( $adapter, 'native created', 'date_created', $entry )
		);
		$this->assertSame( $original, $entry );
	}

	public function test_workflow_timestamp_uses_authoritative_unix_instant_and_site_timezone(): void {
		$entry   = array( 'date_created' => '2026-03-20 22:15:00', 'workflow_timestamp' => '1774132200' );
		$adapter = new PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter();
		$table   = new Gravity_Flow_Status_Table();

		$this->assertSame(
			'۱۴۰۵/۰۱/۰۲، ۰۲:۰۰',
			$table->filter_field_value( $adapter, 'native updated', 'workflow_timestamp', $entry )
		);
	}

	public function test_direct_filter_invocation_and_unrelated_columns_remain_native(): void {
		$entry   = array( 'date_created' => '2026-03-20 22:15:00', 'workflow_timestamp' => '1774132200' );
		$adapter = new PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter();
		$table   = new Gravity_Flow_Status_Table();

		$this->assertSame(
			'native export',
			$adapter->filter_status_value( 'native export', 1, 'date_created', $entry ),
			'CSV/export invokes the host filter directly and must remain native.'
		);
		$this->assertSame(
			'native due',
			$table->filter_field_value( $adapter, 'native due', 'due_date', $entry )
		);
	}

	public function test_missing_malformed_and_out_of_range_sources_fail_closed(): void {
		$adapter = new PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter();
		$table   = new Gravity_Flow_Status_Table();

		$this->assertSame( 'native', $table->filter_field_value( $adapter, 'native', 'date_created', array() ) );
		$this->assertSame( 'native', $table->filter_field_value( $adapter, 'native', 'date_created', array( 'date_created' => 'not-a-date' ) ) );
		$this->assertSame( 'native', $table->filter_field_value( $adapter, 'native', 'workflow_timestamp', array( 'workflow_timestamp' => 'not-a-timestamp' ) ) );
		$this->assertSame( 'native', $table->filter_field_value( $adapter, 'native', 'date_created', array( 'date_created' => '2124-03-20 00:00:00' ) ) );
		$this->assertSame( 'native', $table->filter_field_value( $adapter, 'native', 'workflow_timestamp', array( 'workflow_timestamp' => '4866566400' ) ) );
	}

	public function test_adapter_registers_only_the_status_presentation_filter(): void {
		$adapter = new PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter();
		$adapter->hooks();

		$this->assertArrayHasKey( 'gravityflow_field_value_status_table', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_inbox_field_value', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gform_get_entries_args_entry_list', $GLOBALS['pgr_test_filters'] );
	}

	public function test_runtime_version_gate_reuses_product_registry_and_context_is_exact(): void {
		$products = require dirname( __DIR__ ) . '/includes/localization/products.php';
		$this->assertSame( '3.1.0', $products['gravityflow']['target_version'] );
		$this->assertSame( 'gravityflow', $products['gravityflow']['product'] );

		$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-status-jalali-presentation-adapter.php' );
		$this->assertStringContainsString( "PGR_PATH . 'includes/localization/products.php'", $source );
		$this->assertStringContainsString( "STATUS_TABLE_CLASS = 'Gravity_Flow_Status_Table'", $source );
		$this->assertStringContainsString( "STATUS_TABLE_FILTER_METHOD = 'filter_field_value'", $source );
		$this->assertStringContainsString( 'debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 8 )', $source );
		$this->assertStringNotContainsString( "'3.1.0'", $source );
		$this->assertStringNotContainsString( "'gravityflow'", $source );
	}
}
