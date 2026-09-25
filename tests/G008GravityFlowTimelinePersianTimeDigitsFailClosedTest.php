<?php

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class G008GravityFlowTimelinePersianTimeDigitsFailClosedTest extends TestCase {

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_gravity_flow_version_drift_never_enqueues_timeline_time_shaper(): void {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
		define( 'PGR_PATH', dirname( __DIR__ ) . '/' );
		define( 'PGR_URL', 'https://example.test/wp-content/plugins/persian-gravityforms/' );
		define( 'PGR_VERSION', '4.7.0' );
		define( 'GRAVITY_FLOW_VERSION', '3.1.1' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow/gravityflow.php' );

		require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-timeline-persian-time-digits-presentation-adapter.php';

		$GLOBALS['pgr_test_locale']   = 'fa_IR';
		$GLOBALS['pgr_test_enqueued'] = array();
		$adapter                     = new PGR_Gravity_Flow_Timeline_Persian_Time_Digits_Presentation_Adapter();
		$adapter->enqueue_time_digit_shaper();

		$this->assertSame( array(), $GLOBALS['pgr_test_enqueued'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_gravity_flow_plugin_identity_drift_never_enqueues_timeline_time_shaper(): void {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
		define( 'PGR_PATH', dirname( __DIR__ ) . '/' );
		define( 'PGR_URL', 'https://example.test/wp-content/plugins/persian-gravityforms/' );
		define( 'PGR_VERSION', '4.7.0' );
		define( 'GRAVITY_FLOW_VERSION', '3.1.0' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow-next/gravityflow.php' );

		require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-timeline-persian-time-digits-presentation-adapter.php';

		$GLOBALS['pgr_test_locale']   = 'fa_IR';
		$GLOBALS['pgr_test_enqueued'] = array();
		$adapter                     = new PGR_Gravity_Flow_Timeline_Persian_Time_Digits_Presentation_Adapter();
		$adapter->enqueue_time_digit_shaper();

		$this->assertSame( array(), $GLOBALS['pgr_test_enqueued'] );
	}
}
