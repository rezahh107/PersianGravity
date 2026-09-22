<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'PGR_PATH' ) ) {
	define( 'PGR_PATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'GRAVITY_FLOW_VERSION' ) ) {
	define( 'GRAVITY_FLOW_VERSION', '3.1.0' );
}

require_once dirname( __DIR__ ) . '/includes/class-pgr-gregorian-jalali-converter.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-jalali-presentation.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-inbox-jalali-presentation-adapter.php';

final class G008GravityFlowInboxJalaliPresentationTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pgr_test_timezone'] = 'Asia/Tehran';
		$GLOBALS['pgr_test_filters']  = array();
	}

	public function test_date_created_uses_authoritative_utc_entry_value_and_preserves_entry(): void {
		$entry = array(
			'id'                 => 9,
			'date_created'       => '2026-03-20 22:15:00',
			'workflow_timestamp' => '1774132200',
		);
		$original = $entry;
		$adapter  = new PGR_Gravity_Flow_Inbox_Jalali_Presentation_Adapter();

		$this->assertSame(
			'۱۴۰۵/۰۱/۰۱، ۰۱:۴۵',
			$adapter->filter_inbox_value( 'native submitted', 1, 'date_created_human_readable', $entry )
		);
		$this->assertSame( $original, $entry );
	}

	public function test_last_updated_uses_authoritative_unix_timestamp_and_site_timezone(): void {
		$entry = array(
			'date_created'       => '2026-03-20 22:15:00',
			'workflow_timestamp' => '1774132200',
		);
		$adapter = new PGR_Gravity_Flow_Inbox_Jalali_Presentation_Adapter();

		$this->assertSame(
			'۱۴۰۵/۰۱/۰۲، ۰۲:۰۰',
			$adapter->filter_inbox_value( 'native updated', 1, 'last_updated_human_readable', $entry )
		);
	}

	public function test_raw_compare_identities_and_unrelated_values_are_never_converted(): void {
		$entry = array(
			'date_created'       => '2026-03-20 22:15:00',
			'workflow_timestamp' => '1774132200',
		);
		$adapter = new PGR_Gravity_Flow_Inbox_Jalali_Presentation_Adapter();

		$this->assertSame( 1774044900, $adapter->filter_inbox_value( 1774044900, 1, 'date_created', $entry ) );
		$this->assertSame( 1774132200, $adapter->filter_inbox_value( 1774132200, 1, 'last_updated', $entry ) );
		$this->assertSame( '1405-01-01', $adapter->filter_inbox_value( '1405-01-01', 1, 'pgr_jalali_date', $entry ) );
		$this->assertSame( 'native', $adapter->filter_inbox_value( 'native', 1, 'due_date_human_readable', $entry ) );
	}

	public function test_native_sentinel_malformed_missing_and_out_of_range_sources_fail_closed(): void {
		$adapter = new PGR_Gravity_Flow_Inbox_Jalali_Presentation_Adapter();

		$this->assertSame(
			'-',
			$adapter->filter_inbox_value(
				'-',
				1,
				'last_updated_human_readable',
				array( 'date_created' => '2026-03-20 22:15:00', 'workflow_timestamp' => '1774044900' )
			)
		);
		$this->assertSame(
			'native',
			$adapter->filter_inbox_value( 'native', 1, 'date_created_human_readable', array( 'date_created' => 'not-a-date' ) )
		);
		$this->assertSame(
			'native',
			$adapter->filter_inbox_value( 'native', 1, 'last_updated_human_readable', array( 'workflow_timestamp' => 'not-a-timestamp' ) )
		);
		$this->assertSame(
			'native',
			$adapter->filter_inbox_value( 'native', 1, 'date_created_human_readable', array( 'date_created' => '2124-03-20 00:00:00' ) )
		);
		$this->assertSame(
			'native',
			$adapter->filter_inbox_value( 'native', 1, 'last_updated_human_readable', array( 'workflow_timestamp' => '4866566400' ) )
		);
	}

	public function test_adapter_registers_only_the_inbox_presentation_filter(): void {
		$adapter = new PGR_Gravity_Flow_Inbox_Jalali_Presentation_Adapter();
		$adapter->hooks();

		$this->assertArrayHasKey( 'gravityflow_inbox_field_value', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gform_get_entries_args_entry_list', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gform_search_criteria_entry_list', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_inbox_filter', $GLOBALS['pgr_test_filters'] );
	}

	public function test_runtime_version_gate_reuses_the_existing_product_registry_authority(): void {
		$products = require dirname( __DIR__ ) . '/includes/localization/products.php';
		$this->assertSame( '3.1.0', $products['gravityflow']['target_version'] );
		$this->assertSame( 'GRAVITY_FLOW_VERSION', $products['gravityflow']['runtime_version_constant'] );
		$this->assertSame( $products['gravityflow']['target_version'], GRAVITY_FLOW_VERSION );

		$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-inbox-jalali-presentation-adapter.php' );
		$this->assertStringContainsString( "PGR_PATH . 'includes/localization/products.php'", $source );
		$this->assertStringContainsString( "HOST_VERSION_CONSTANT = 'GRAVITY_FLOW_VERSION'", $source );
		$this->assertStringContainsString( "constant( self::HOST_VERSION_CONSTANT )", $source );
		$this->assertStringNotContainsString( "'3.1.0'", $source );
		$this->assertStringNotContainsString( "'gravityflow'", $source );
	}
}
