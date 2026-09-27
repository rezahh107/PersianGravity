<?php

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class G008GravityFlowStatusFailClosedTest extends TestCase {

	private function load_adapter( $with_facade = true ) {
		if ( ! defined( 'PGR_PATH' ) ) {
			define( 'PGR_PATH', dirname( __DIR__ ) . '/' );
		}
		require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-compatibility-diagnostics.php';
		if ( $with_facade ) {
			require_once dirname( __DIR__ ) . '/includes/class-pgr-gregorian-jalali-converter.php';
			require_once dirname( __DIR__ ) . '/includes/class-pgr-jalali-presentation.php';
		}
		require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-status-jalali-presentation-adapter.php';
		$GLOBALS['pgr_test_timezone'] = 'Asia/Tehran';
	}

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

	private function assert_observation( $capability_id, $state, $reason ) {
		$snapshot = PGR_Gravity_Flow_Compatibility_Diagnostics::request_snapshot();
		$this->assertArrayHasKey( $capability_id, $snapshot );
		$this->assertSame( $state, $snapshot[ $capability_id ]['state'] );
		$this->assertSame( $reason, $snapshot[ $capability_id ]['reason_id'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_version_only_drift_with_intact_status_contract_remains_available(): void {
		define( 'GRAVITY_FLOW_VERSION', '9.9.9-synthetic' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow/gravityflow.php' );
		$this->load_adapter();

		$adapter = new PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter();
		$this->assertSame(
			'۱۴۰۵/۰۱/۰۱، ۰۱:۴۵',
			$this->status_call( $adapter, 'native', 'date_created', array( 'date_created' => '2026-03-20 22:15:00' ) )
		);
		$this->assertSame(
			'۱۴۰۵/۰۱/۰۲، ۰۲:۰۰',
			$this->status_call( $adapter, 'native', 'workflow_timestamp', array( 'workflow_timestamp' => '1774132200' ) )
		);

		foreach ( array( 'gravityflow.status.date-created', 'gravityflow.status.workflow-timestamp' ) as $capability_id ) {
			$this->assert_observation(
				$capability_id,
				PGR_Gravity_Flow_Compatibility_Diagnostics::STATE_AVAILABLE,
				PGR_Gravity_Flow_Compatibility_Diagnostics::REASON_CONTRACT_SATISFIED
			);
		}
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_gravity_flow_plugin_identity_drift_returns_native_value_and_is_classified(): void {
		define( 'GRAVITY_FLOW_VERSION', '3.1.0' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow-next/gravityflow.php' );
		$this->load_adapter();

		$adapter = new PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter();
		$this->assertSame( 'native', $this->status_call( $adapter, 'native', 'workflow_timestamp', array( 'workflow_timestamp' => '1774132200' ) ) );
		$this->assert_observation(
			'gravityflow.status.workflow-timestamp',
			PGR_Gravity_Flow_Compatibility_Diagnostics::STATE_UNAVAILABLE,
			PGR_Gravity_Flow_Compatibility_Diagnostics::REASON_HOST_UNQUALIFIED
		);
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_missing_presentation_facade_returns_native_value_and_is_classified(): void {
		define( 'GRAVITY_FLOW_VERSION', '3.1.0' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow/gravityflow.php' );
		$this->load_adapter( false );

		$adapter = new PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter();
		$this->assertFalse( class_exists( 'PGR_Jalali_Presentation', false ) );
		$this->assertSame( 'native', $this->status_call( $adapter, 'native', 'date_created', array( 'date_created' => '2026-03-20 22:15:00' ) ) );
		$this->assert_observation(
			'gravityflow.status.date-created',
			PGR_Gravity_Flow_Compatibility_Diagnostics::STATE_DEGRADED,
			PGR_Gravity_Flow_Compatibility_Diagnostics::REASON_CONVERSION_UNAVAILABLE
		);
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_missing_table_proof_affects_only_the_target_status_capability(): void {
		define( 'GRAVITY_FLOW_VERSION', '3.1.0' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow/gravityflow.php' );
		$this->load_adapter();

		$adapter = new PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter();
		$entry   = array(
			'id'                 => 42,
			'form_id'            => 1,
			'date_created'       => '2026-03-20 22:15:00',
			'workflow_timestamp' => '1774132200',
		);

		$this->assertSame( 'native export', $adapter->filter_status_value( 'native export', 1, 'date_created', $entry ) );
		$this->assertSame(
			'۱۴۰۵/۰۱/۰۲، ۰۲:۰۰',
			$this->status_call( $adapter, 'native updated', 'workflow_timestamp', $entry )
		);

		$this->assert_observation(
			'gravityflow.status.date-created',
			PGR_Gravity_Flow_Compatibility_Diagnostics::STATE_DEGRADED,
			PGR_Gravity_Flow_Compatibility_Diagnostics::REASON_CONTEXT_UNAVAILABLE
		);
		$this->assert_observation(
			'gravityflow.status.workflow-timestamp',
			PGR_Gravity_Flow_Compatibility_Diagnostics::STATE_AVAILABLE,
			PGR_Gravity_Flow_Compatibility_Diagnostics::REASON_CONTRACT_SATISFIED
		);
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_missing_target_timezone_returns_native_value_and_is_classified(): void {
		define( 'GRAVITY_FLOW_VERSION', '3.1.0' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow/gravityflow.php' );
		$this->load_adapter();

		$GLOBALS['pgr_test_timezone'] = 'Invalid/Timezone';
		$adapter = new PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter();
		$this->assertSame( 'native', $this->status_call( $adapter, 'native', 'date_created', array( 'date_created' => '2026-03-20 22:15:00' ) ) );
		$this->assert_observation(
			'gravityflow.status.date-created',
			PGR_Gravity_Flow_Compatibility_Diagnostics::STATE_DEGRADED,
			PGR_Gravity_Flow_Compatibility_Diagnostics::REASON_CONVERSION_UNAVAILABLE
		);
	}
}
