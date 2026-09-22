<?php

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class G008GravityFlowInboxFailClosedTest extends TestCase {

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_gravity_flow_version_drift_returns_native_value(): void {
		if ( ! defined( 'PGR_PATH' ) ) {
			define( 'PGR_PATH', dirname( __DIR__ ) . '/' );
		}
		define( 'GRAVITY_FLOW_VERSION', '3.1.1' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow/gravityflow.php' );
		require_once dirname( __DIR__ ) . '/includes/class-pgr-gregorian-jalali-converter.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-jalali-presentation.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-inbox-jalali-presentation-adapter.php';
		$GLOBALS['pgr_test_timezone'] = 'Asia/Tehran';

		$adapter = new PGR_Gravity_Flow_Inbox_Jalali_Presentation_Adapter();
		$this->assertSame(
			'native',
			$adapter->filter_inbox_value(
				'native',
				1,
				'date_created_human_readable',
				array( 'date_created' => '2026-03-20 22:15:00' )
			)
		);
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_gravity_flow_plugin_identity_drift_returns_native_value(): void {
		if ( ! defined( 'PGR_PATH' ) ) {
			define( 'PGR_PATH', dirname( __DIR__ ) . '/' );
		}
		define( 'GRAVITY_FLOW_VERSION', '3.1.0' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow-next/gravityflow.php' );
		require_once dirname( __DIR__ ) . '/includes/class-pgr-gregorian-jalali-converter.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-jalali-presentation.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-inbox-jalali-presentation-adapter.php';
		$GLOBALS['pgr_test_timezone'] = 'Asia/Tehran';

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
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_missing_presentation_facade_returns_native_value(): void {
		if ( ! defined( 'PGR_PATH' ) ) {
			define( 'PGR_PATH', dirname( __DIR__ ) . '/' );
		}
		define( 'GRAVITY_FLOW_VERSION', '3.1.0' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow/gravityflow.php' );
		require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-inbox-jalali-presentation-adapter.php';

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
	}
}
