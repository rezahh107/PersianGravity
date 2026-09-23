<?php

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class G008GravityFlowStatusFailClosedTest extends TestCase {

	private function status_call( $adapter, $value, $column, $entry ) {
		$entry = array_merge(
			array(
				'id'      => 42,
				'form_id' => 1,
			),
			$entry
		);
		$adapter->reset_status_context( array( 'format' => 'table' ) );
		$adapter->mark_status_table_entry( 'native-url', 1, 42, $entry );
		return $adapter->filter_status_value( $value, 1, $column, $entry );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_gravity_flow_version_drift_returns_native_value(): void {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
		define( 'PGR_PATH', dirname( __DIR__ ) . '/' );
		define( 'GRAVITY_FLOW_VERSION', '3.1.1' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow/gravityflow.php' );
		require_once dirname( __DIR__ ) . '/includes/class-pgr-gregorian-jalali-converter.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-jalali-presentation.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-status-jalali-presentation-adapter.php';
		$GLOBALS['pgr_test_timezone'] = 'Asia/Tehran';

		$adapter = new PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter();
		$this->assertSame( 'native', $this->status_call( $adapter, 'native', 'date_created', array( 'date_created' => '2026-03-20 22:15:00' ) ) );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_gravity_flow_plugin_identity_drift_returns_native_value(): void {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
		define( 'PGR_PATH', dirname( __DIR__ ) . '/' );
		define( 'GRAVITY_FLOW_VERSION', '3.1.0' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow-next/gravityflow.php' );
		require_once dirname( __DIR__ ) . '/includes/class-pgr-gregorian-jalali-converter.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-jalali-presentation.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-status-jalali-presentation-adapter.php';
		$GLOBALS['pgr_test_timezone'] = 'Asia/Tehran';

		$adapter = new PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter();
		$this->assertSame( 'native', $this->status_call( $adapter, 'native', 'workflow_timestamp', array( 'workflow_timestamp' => '1774132200' ) ) );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_missing_presentation_facade_returns_native_value(): void {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
		define( 'PGR_PATH', dirname( __DIR__ ) . '/' );
		define( 'GRAVITY_FLOW_VERSION', '3.1.0' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow/gravityflow.php' );
		require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-status-jalali-presentation-adapter.php';

		$adapter = new PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter();
		$this->assertFalse( class_exists( 'PGR_Jalali_Presentation', false ) );
		$this->assertSame( 'native', $this->status_call( $adapter, 'native', 'date_created', array( 'date_created' => '2026-03-20 22:15:00' ) ) );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_missing_target_timezone_returns_native_value(): void {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
		define( 'PGR_PATH', dirname( __DIR__ ) . '/' );
		define( 'GRAVITY_FLOW_VERSION', '3.1.0' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow/gravityflow.php' );
		require_once dirname( __DIR__ ) . '/includes/class-pgr-gregorian-jalali-converter.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-jalali-presentation.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-status-jalali-presentation-adapter.php';

		$GLOBALS['pgr_test_timezone'] = 'Invalid/Timezone';
		$adapter = new PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter();
		$this->assertSame( 'native', $this->status_call( $adapter, 'native', 'date_created', array( 'date_created' => '2026-03-20 22:15:00' ) ) );
	}
}
