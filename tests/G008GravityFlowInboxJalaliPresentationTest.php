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
if ( ! class_exists( 'Gravity_Flow_API' ) ) {
	final class Gravity_Flow_API {
		public static $current_step;

		public function __construct( $form_id ) {
			unset( $form_id );
		}

		public function get_current_step( $entry ) {
			unset( $entry );
			return self::$current_step;
		}
	}
}

require_once dirname( __DIR__ ) . '/includes/class-pgr-gregorian-jalali-converter.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-jalali-presentation.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-inbox-jalali-presentation-adapter.php';

final class G008GravityFlowInboxJalaliPresentationTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pgr_test_timezone']       = 'Asia/Tehran';
		$GLOBALS['pgr_test_filters']        = array();
		Gravity_Flow_API::$current_step = null;
	}

	private function due_step( $timestamp, $enabled = true ) {
		return new class( $timestamp, $enabled ) {
			public $due_date;
			private $timestamp;

			public function __construct( $timestamp, $enabled ) {
				$this->timestamp = $timestamp;
				$this->due_date  = $enabled;
			}

			public function get_due_date_timestamp() {
				return $this->timestamp;
			}
		};
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

	public function test_due_date_uses_current_steps_authoritative_utc_epoch_and_site_timezone(): void {
		Gravity_Flow_API::$current_step = $this->due_step( 1774044900 );
		$entry                           = array( 'id' => 9, 'workflow_step' => 4 );
		$original                        = $entry;
		$adapter                         = new PGR_Gravity_Flow_Inbox_Jalali_Presentation_Adapter();

		$this->assertSame(
			'۱۴۰۵/۰۱/۰۱، ۰۱:۴۵',
			$adapter->filter_inbox_value( 'native due', 1, 'due_date_human_readable', $entry )
		);
		$this->assertSame( $original, $entry );
	}

	public function test_raw_compare_identities_and_unrelated_values_are_never_converted(): void {
		Gravity_Flow_API::$current_step = $this->due_step( 1774044900 );
		$entry = array(
			'date_created'       => '2026-03-20 22:15:00',
			'workflow_timestamp' => '1774132200',
		);
		$adapter = new PGR_Gravity_Flow_Inbox_Jalali_Presentation_Adapter();

		$this->assertSame( 1774044900, $adapter->filter_inbox_value( 1774044900, 1, 'date_created', $entry ) );
		$this->assertSame( 1774132200, $adapter->filter_inbox_value( 1774132200, 1, 'last_updated', $entry ) );
		$this->assertSame( 1774044900, $adapter->filter_inbox_value( 1774044900, 1, 'due_date', $entry ) );
		$this->assertSame( '1405-01-01', $adapter->filter_inbox_value( '1405-01-01', 1, 'pgr_jalali_date', $entry ) );
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

		Gravity_Flow_API::$current_step = $this->due_step( 'not-a-timestamp' );
		$this->assertSame( 'native due', $adapter->filter_inbox_value( 'native due', 1, 'due_date_human_readable', array( 'id' => 9 ) ) );
		Gravity_Flow_API::$current_step = $this->due_step( 0 );
		$this->assertSame( 'native due', $adapter->filter_inbox_value( 'native due', 1, 'due_date_human_readable', array( 'id' => 9 ) ) );
		Gravity_Flow_API::$current_step = $this->due_step( 4866566400 );
		$this->assertSame( 'native due', $adapter->filter_inbox_value( 'native due', 1, 'due_date_human_readable', array( 'id' => 9 ) ) );
		Gravity_Flow_API::$current_step = $this->due_step( 1774044900, false );
		$this->assertSame( '-', $adapter->filter_inbox_value( '-', 1, 'due_date_human_readable', array( 'id' => 9 ) ) );
		$this->assertSame( 'native due', $adapter->filter_inbox_value( 'native due', 0, 'due_date_human_readable', array( 'id' => 9 ) ) );
	}

	public function test_adapter_registers_only_the_inbox_presentation_filter(): void {
		$adapter = new PGR_Gravity_Flow_Inbox_Jalali_Presentation_Adapter();
		$adapter->hooks();

		$this->assertArrayHasKey( 'gravityflow_inbox_field_value', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_step_due_date_timestamp', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gform_get_entries_args_entry_list', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gform_search_criteria_entry_list', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_inbox_filter', $GLOBALS['pgr_test_filters'] );
	}

	public function test_runtime_version_gate_reuses_existing_product_registry_and_host_identity_authorities(): void {
		$products = require dirname( __DIR__ ) . '/includes/localization/products.php';
		$this->assertSame( '3.1.0', $products['gravityflow']['target_version'] );
		$this->assertSame( 'gravityflow', $products['gravityflow']['product'] );
		$this->assertSame( $products['gravityflow']['target_version'], GRAVITY_FLOW_VERSION );
		$this->assertSame( $products['gravityflow']['product'], dirname( GRAVITY_FLOW_PLUGIN_BASENAME ) );

		$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-inbox-jalali-presentation-adapter.php' );
		$this->assertStringContainsString( "PGR_PATH . 'includes/localization/products.php'", $source );
		$this->assertStringContainsString( "HOST_VERSION_CONSTANT = 'GRAVITY_FLOW_VERSION'", $source );
		$this->assertStringContainsString( "HOST_BASENAME_CONSTANT = 'GRAVITY_FLOW_PLUGIN_BASENAME'", $source );
		$this->assertStringContainsString( "constant( self::HOST_VERSION_CONSTANT )", $source );
		$this->assertStringNotContainsString( "'3.1.0'", $source );
		$this->assertStringNotContainsString( "'gravityflow'", $source );
	}
}
