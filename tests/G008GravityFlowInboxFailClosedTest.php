<?php

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class G008GravityFlowInboxFailClosedTest extends TestCase {

	private function load_adapter( $with_facade = true ) {
		if ( ! defined( 'PGR_PATH' ) ) {
			define( 'PGR_PATH', dirname( __DIR__ ) . '/' );
		}
		require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-compatibility-diagnostics.php';
		if ( $with_facade ) {
			require_once dirname( __DIR__ ) . '/includes/class-pgr-gregorian-jalali-converter.php';
			require_once dirname( __DIR__ ) . '/includes/class-pgr-jalali-presentation.php';
		}
		require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-inbox-jalali-presentation-adapter.php';
		$GLOBALS['pgr_test_timezone'] = 'Asia/Tehran';
	}

	private function assert_observation( $capability_id, $state, $reason ) {
		$snapshot = PGR_Gravity_Flow_Compatibility_Diagnostics::request_snapshot();
		$this->assertArrayHasKey( $capability_id, $snapshot );
		$this->assertSame( $state, $snapshot[ $capability_id ]['state'] );
		$this->assertSame( $reason, $snapshot[ $capability_id ]['reason_id'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_version_only_drift_with_intact_inbox_contract_remains_available(): void {
		define( 'GRAVITY_FLOW_VERSION', '9.9.9-synthetic' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow/gravityflow.php' );
		$this->load_adapter();

		$entry = array(
			'id'                 => 9,
			'form_id'            => 1,
			'date_created'       => '2026-03-20 22:15:00',
			'workflow_timestamp' => '1774132200',
		);
		$adapter = new PGR_Gravity_Flow_Inbox_Jalali_Presentation_Adapter();

		$this->assertSame(
			'۱۴۰۵/۰۱/۰۱، ۰۱:۴۵',
			$adapter->filter_inbox_value( 'native created', 1, 'date_created_human_readable', $entry )
		);
		$this->assertSame(
			'۱۴۰۵/۰۱/۰۲، ۰۲:۰۰',
			$adapter->filter_inbox_value( 'native updated', 1, 'last_updated_human_readable', $entry )
		);
		$this->assertSame( 1774044900, $adapter->filter_inbox_value( 1774044900, 1, 'due_date', $entry ) );
		$this->assertSame(
			'۱۴۰۵/۰۱/۰۱، ۰۱:۴۵',
			$adapter->filter_inbox_value( 'native due', 1, 'due_date_human_readable', $entry )
		);

		foreach ( array( 'gravityflow.inbox.date-created', 'gravityflow.inbox.last-updated', 'gravityflow.inbox.due-date' ) as $capability_id ) {
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

		$adapter = new PGR_Gravity_Flow_Inbox_Jalali_Presentation_Adapter();
		$this->assertSame(
			'native',
			$adapter->filter_inbox_value(
				'native',
				1,
				'last_updated_human_readable',
				array( 'workflow_timestamp' => '1774132200' )
			)
		);
		$this->assert_observation(
			'gravityflow.inbox.last-updated',
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

		$adapter = new PGR_Gravity_Flow_Inbox_Jalali_Presentation_Adapter();
		$this->assertFalse( class_exists( 'PGR_Jalali_Presentation', false ) );
		$this->assertSame(
			'native',
			$adapter->filter_inbox_value(
				'native',
				1,
				'last_updated_human_readable',
				array( 'workflow_timestamp' => '1774132200' )
			)
		);
		$this->assert_observation(
			'gravityflow.inbox.last-updated',
			PGR_Gravity_Flow_Compatibility_Diagnostics::STATE_DEGRADED,
			PGR_Gravity_Flow_Compatibility_Diagnostics::REASON_CONVERSION_UNAVAILABLE
		);
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_due_contract_drift_is_isolated_from_other_inbox_capabilities(): void {
		define( 'GRAVITY_FLOW_VERSION', '3.1.0' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow/gravityflow.php' );
		$this->load_adapter();

		$entry = array(
			'id'                 => 9,
			'form_id'            => 1,
			'date_created'       => '2026-03-20 22:15:00',
			'workflow_timestamp' => '1774132200',
		);
		$adapter = new PGR_Gravity_Flow_Inbox_Jalali_Presentation_Adapter();

		$this->assertSame( '1774044900', $adapter->filter_inbox_value( '1774044900', 1, 'due_date', $entry ) );
		$this->assertSame( 'native due', $adapter->filter_inbox_value( 'native due', 1, 'due_date_human_readable', $entry ) );
		$this->assertSame(
			'۱۴۰۵/۰۱/۰۱، ۰۱:۴۵',
			$adapter->filter_inbox_value( 'native created', 1, 'date_created_human_readable', $entry )
		);
		$this->assertSame(
			'۱۴۰۵/۰۱/۰۲، ۰۲:۰۰',
			$adapter->filter_inbox_value( 'native updated', 1, 'last_updated_human_readable', $entry )
		);

		$this->assert_observation(
			'gravityflow.inbox.due-date',
			PGR_Gravity_Flow_Compatibility_Diagnostics::STATE_DEGRADED,
			PGR_Gravity_Flow_Compatibility_Diagnostics::REASON_SOURCE_INVALID
		);
		foreach ( array( 'gravityflow.inbox.date-created', 'gravityflow.inbox.last-updated' ) as $capability_id ) {
			$this->assert_observation(
				$capability_id,
				PGR_Gravity_Flow_Compatibility_Diagnostics::STATE_AVAILABLE,
				PGR_Gravity_Flow_Compatibility_Diagnostics::REASON_CONTRACT_SATISFIED
			);
		}
	}
}
