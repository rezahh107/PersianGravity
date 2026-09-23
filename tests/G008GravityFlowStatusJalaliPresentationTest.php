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

require_once dirname( __DIR__ ) . '/includes/class-pgr-gregorian-jalali-converter.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-jalali-presentation.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-status-jalali-presentation-adapter.php';

final class G008GravityFlowStatusJalaliPresentationTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pgr_test_timezone'] = 'Asia/Tehran';
		$GLOBALS['pgr_test_filters']  = array();
	}

	private function table_value( $adapter, $value, $column_name, $entry ) {
		$this->assertSame(
			array( 'format' => 'table' ),
			$adapter->capture_status_context( array( 'format' => 'table' ) )
		);
		return $adapter->filter_status_value( $value, 1, $column_name, $entry );
	}

	public function test_date_created_uses_authoritative_utc_entry_value_and_preserves_entry(): void {
		$entry    = array( 'date_created' => '2026-03-20 22:15:00', 'workflow_timestamp' => '1774132200' );
		$original = $entry;
		$adapter  = new PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter();

		$this->assertSame(
			'۱۴۰۵/۰۱/۰۱، ۰۱:۴۵',
			$this->table_value( $adapter, 'native created', 'date_created', $entry )
		);
		$this->assertSame( $original, $entry );
	}

	public function test_workflow_timestamp_uses_authoritative_unix_instant_and_site_timezone(): void {
		$entry   = array( 'date_created' => '2026-03-20 22:15:00', 'workflow_timestamp' => '1774132200' );
		$adapter = new PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter();

		$this->assertSame(
			'۱۴۰۵/۰۱/۰۲، ۰۲:۰۰',
			$this->table_value( $adapter, 'native updated', 'workflow_timestamp', $entry )
		);
	}

	public function test_unknown_csv_and_unrelated_contexts_remain_native(): void {
		$entry   = array( 'date_created' => '2026-03-20 22:15:00', 'workflow_timestamp' => '1774132200' );
		$adapter = new PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter();

		$this->assertSame(
			'native unknown',
			$adapter->filter_status_value( 'native unknown', 1, 'date_created', $entry )
		);
		$this->assertSame(
			array( 'format' => 'csv' ),
			$adapter->capture_status_context( array( 'format' => 'csv' ) )
		);
		$this->assertSame(
			'native export',
			$adapter->filter_status_value( 'native export', 1, 'date_created', $entry )
		);
		$this->assertSame(
			'native due',
			$this->table_value( $adapter, 'native due', 'due_date', $entry )
		);
		$this->assertSame(
			'native missing context',
			$adapter->capture_status_context( 'native missing context' )
		);
		$this->assertSame(
			'native after malformed context',
			$adapter->filter_status_value( 'native after malformed context', 1, 'workflow_timestamp', $entry )
		);
	}

	public function test_missing_malformed_and_out_of_range_sources_fail_closed(): void {
		$adapter = new PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter();

		$this->assertSame( 'native', $this->table_value( $adapter, 'native', 'date_created', array() ) );
		$this->assertSame( 'native', $this->table_value( $adapter, 'native', 'date_created', array( 'date_created' => 'not-a-date' ) ) );
		$this->assertSame( 'native', $this->table_value( $adapter, 'native', 'workflow_timestamp', array( 'workflow_timestamp' => 'not-a-timestamp' ) ) );
		$this->assertSame( 'native', $this->table_value( $adapter, 'native', 'date_created', array( 'date_created' => '2124-03-20 00:00:00' ) ) );
		$this->assertSame( 'native', $this->table_value( $adapter, 'native', 'workflow_timestamp', array( 'workflow_timestamp' => '4866566400' ) ) );
	}

	public function test_adapter_registers_only_status_context_and_presentation_filters(): void {
		$adapter = new PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter();
		$adapter->hooks();

		$this->assertArrayHasKey( 'gravityflow_status_args', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayHasKey( 'gravityflow_field_value_status_table', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_inbox_field_value', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gform_get_entries_args_entry_list', $GLOBALS['pgr_test_filters'] );
	}

	public function test_runtime_version_gate_reuses_product_registry_and_native_status_context(): void {
		$products = require dirname( __DIR__ ) . '/includes/localization/products.php';
		$this->assertSame( '3.1.0', $products['gravityflow']['target_version'] );
		$this->assertSame( 'gravityflow', $products['gravityflow']['product'] );

		$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-status-jalali-presentation-adapter.php' );
		$this->assertStringContainsString( "PGR_PATH . 'includes/localization/products.php'", $source );
		$this->assertStringContainsString( "'gravityflow_status_args'", $source );
		$this->assertStringContainsString( "STATUS_TABLE_FORMAT = 'table'", $source );
		$this->assertStringNotContainsString( 'debug_backtrace', $source );
		$this->assertStringNotContainsString( "'3.1.0'", $source );
		$this->assertStringNotContainsString( "'gravityflow'", $source );
	}
}
