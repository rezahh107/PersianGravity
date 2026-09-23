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

	private function due_step( $timestamps, $enabled = true ) {
		$timestamps = is_array( $timestamps ) ? array_values( $timestamps ) : array( $timestamps );

		return new class( $timestamps, $enabled ) {
			public $due_date;
			public $calls = 0;
			private $timestamps;

			public function __construct( $timestamps, $enabled ) {
				$this->timestamps = $timestamps;
				$this->due_date   = $enabled;
			}

			public function get_due_date_timestamp() {
				$index = min( $this->calls, count( $this->timestamps ) - 1 );
				++$this->calls;
				return $this->timestamps[ $index ] ?? null;
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

	public function test_due_date_reuses_captured_native_raw_timestamp_without_reentering_operational_getter(): void {
		$step                            = $this->due_step( array( 1774044900, 1774131300 ) );
		Gravity_Flow_API::$current_step = $step;
		$entry                           = array( 'id' => 9, 'form_id' => 1, 'workflow_step' => 4 );
		$original                        = $entry;
		$adapter                         = new PGR_Gravity_Flow_Inbox_Jalali_Presentation_Adapter();

		// Simulate Gravity Flow's already-computed raw due_date value. A second
		// getter call returns a different timestamp, so the pre-repair adapter
		// both increments calls and formats the wrong instant.
		$native_raw = $step->get_due_date_timestamp();
		$this->assertSame( 1774044900, $native_raw );
		$this->assertSame( 1, $step->calls );
		$this->assertSame( $native_raw, $adapter->filter_inbox_value( $native_raw, 1, 'due_date', $entry ) );

		$this->assertSame(
			'۱۴۰۵/۰۱/۰۱، ۰۱:۴۵',
			$adapter->filter_inbox_value( 'native due', 1, 'due_date_human_readable', $entry )
		);
		$this->assertSame( 1, $step->calls, 'Presentation must add zero operational due-date getter executions.' );
		$this->assertSame( $original, $entry );
	}

	public function test_raw_compare_identities_and_unrelated_values_are_never_converted(): void {
		$entry = array(
			'id'                 => 9,
			'form_id'            => 1,
			'date_created'       => '2026-03-20 22:15:00',
			'workflow_timestamp' => '1774132200',
		);
		$adapter = new PGR_Gravity_Flow_Inbox_Jalali_Presentation_Adapter();

		$this->assertSame( 1774044900, $adapter->filter_inbox_value( 1774044900, 1, 'date_created', $entry ) );
		$this->assertSame( 1774132200, $adapter->filter_inbox_value( 1774132200, 1, 'last_updated', $entry ) );
		$this->assertSame( 1774044900, $adapter->filter_inbox_value( 1774044900, 1, 'due_date', $entry ) );
		$this->assertSame( '1405-01-01', $adapter->filter_inbox_value( '1405-01-01', 1, 'pgr_jalali_date', $entry ) );
	}

	public function test_due_date_capture_is_one_shot_row_bound_and_exact_order_bound(): void {
		$adapter = new PGR_Gravity_Flow_Inbox_Jalali_Presentation_Adapter();
		$entry_a = array( 'id' => 9, 'form_id' => 1 );
		$entry_b = array( 'id' => 10, 'form_id' => 1 );

		$this->assertSame( 1774044900, $adapter->filter_inbox_value( 1774044900, 1, 'due_date', $entry_a ) );
		$this->assertSame(
			'۱۴۰۵/۰۱/۰۱، ۰۱:۴۵',
			$adapter->filter_inbox_value( 'native a', 1, 'due_date_human_readable', $entry_a )
		);
		$this->assertSame(
			'native a second',
			$adapter->filter_inbox_value( 'native a second', 1, 'due_date_human_readable', $entry_a ),
			'Consumed raw authority must not be reusable.'
		);

		$this->assertSame( 1774044900, $adapter->filter_inbox_value( 1774044900, 1, 'due_date', $entry_a ) );
		$this->assertSame( 1774132200, $adapter->filter_inbox_value( 1774132200, 1, 'due_date', $entry_b ) );
		$this->assertSame(
			'۱۴۰۵/۰۱/۰۲، ۰۲:۰۰',
			$adapter->filter_inbox_value( 'native b', 1, 'due_date_human_readable', $entry_b )
		);
		$this->assertSame(
			'native a after reorder',
			$adapter->filter_inbox_value( 'native a after reorder', 1, 'due_date_human_readable', $entry_a ),
			'Row-reordered capture must fail closed instead of retaining stale authority.'
		);

		$this->assertSame( 1774044900, $adapter->filter_inbox_value( 1774044900, 1, 'due_date', $entry_a ) );
		$this->assertSame( 'native unrelated', $adapter->filter_inbox_value( 'native unrelated', 1, 'unrelated', $entry_a ) );
		$this->assertSame(
			'native after intervening identity',
			$adapter->filter_inbox_value( 'native after intervening identity', 1, 'due_date_human_readable', $entry_a ),
			'Any identity between raw due_date and its display companion invalidates the proof.'
		);
	}

	public function test_due_date_malformed_or_mismatched_raw_authority_fails_closed_without_bleeding_rows(): void {
		$adapter = new PGR_Gravity_Flow_Inbox_Jalali_Presentation_Adapter();
		$entry   = array( 'id' => 9, 'form_id' => 1 );

		$this->assertSame( '1774044900', $adapter->filter_inbox_value( '1774044900', 1, 'due_date', $entry ) );
		$this->assertSame( 'native string raw', $adapter->filter_inbox_value( 'native string raw', 1, 'due_date_human_readable', $entry ) );

		$this->assertSame( 1774044900, $adapter->filter_inbox_value( 1774044900, 2, 'due_date', $entry ) );
		$this->assertSame( 'native wrong form', $adapter->filter_inbox_value( 'native wrong form', 2, 'due_date_human_readable', $entry ) );

		$this->assertSame( -1, $adapter->filter_inbox_value( -1, 1, 'due_date', $entry ) );
		$this->assertSame( 'native negative', $adapter->filter_inbox_value( 'native negative', 1, 'due_date_human_readable', $entry ) );
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

		$due_entry = array( 'id' => 9, 'form_id' => 1 );
		$this->assertSame( 'native due', $adapter->filter_inbox_value( 'native due', 1, 'due_date_human_readable', $due_entry ) );

		$this->assertSame( 0, $adapter->filter_inbox_value( 0, 1, 'due_date', $due_entry ) );
		$this->assertSame( '-', $adapter->filter_inbox_value( '-', 1, 'due_date_human_readable', $due_entry ) );

		$this->assertSame( 0, $adapter->filter_inbox_value( 0, 1, 'due_date', $due_entry ) );
		$this->assertSame( 'native due', $adapter->filter_inbox_value( 'native due', 1, 'due_date_human_readable', $due_entry ) );

		$this->assertSame( 4866566400, $adapter->filter_inbox_value( 4866566400, 1, 'due_date', $due_entry ) );
		$this->assertSame( 'native due', $adapter->filter_inbox_value( 'native due', 1, 'due_date_human_readable', $due_entry ) );
		$this->assertSame( 'native due', $adapter->filter_inbox_value( 'native due', 0, 'due_date_human_readable', $due_entry ) );
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
		$this->assertStringContainsString( "DUE_DATE_RAW_ID = 'due_date'", $source );
		$this->assertStringNotContainsString( 'get_due_date_timestamp()', $source );
		$this->assertStringNotContainsString( 'new Gravity_Flow_API', $source );
		$this->assertStringNotContainsString( "'3.1.0'", $source );
		$this->assertStringNotContainsString( "'gravityflow'", $source );
	}

	public function test_due_date_presentation_does_not_reenter_operational_getter_after_native_raw_and_display_resolution(): void {
		$step = new class() {
			public $due_date = true;
			public $calls    = 0;

			public function get_due_date_timestamp() {
				++$this->calls;
				return 1774044900 + ( ( $this->calls - 1 ) * 3600 );
			}
		};
		Gravity_Flow_API::$current_step = $step;
		$entry                           = array( 'id' => 9, 'workflow_step' => 4 );
		$adapter                         = new PGR_Gravity_Flow_Inbox_Jalali_Presentation_Adapter();

		$native_raw = $step->get_due_date_timestamp();
		$this->assertSame(
			$native_raw,
			$adapter->filter_inbox_value( $native_raw, 1, 'due_date', $entry )
		);

		$step->get_due_date_timestamp(); // Native due_date_human_readable calculation.

		$this->assertSame(
			'۱۴۰۵/۰۱/۰۱، ۰۱:۴۵',
			$adapter->filter_inbox_value( 'native due', 1, 'due_date_human_readable', $entry )
		);
		$this->assertSame( 2, $step->calls, 'Presentation must add zero operational due-date getter invocations.' );
	}

}
