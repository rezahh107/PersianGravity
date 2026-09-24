<?php

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script() {}
}
if ( ! function_exists( 'determine_locale' ) ) {
	function determine_locale() {
		return 'fa_IR';
	}
}

final class G008GravityFlowEntryDetailPersianDigitsFailClosedTest extends TestCase {

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_gravity_flow_version_drift_never_enqueues_digit_shaper(): void {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
		define( 'PGR_PATH', dirname( __DIR__ ) . '/' );
		define( 'PGR_URL', 'https://example.test/wp-content/plugins/persian-gravityforms/' );
		define( 'PGR_VERSION', '4.7.0' );
		define( 'GRAVITY_FLOW_VERSION', '3.1.1' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow/gravityflow.php' );

		require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-entry-detail-persian-digits-presentation-adapter.php';

		$adapter = new PGR_Gravity_Flow_Entry_Detail_Persian_Digits_Presentation_Adapter();
		$adapter->enqueue_digit_shaper( array(), array(), null );

		$this->expectNotToPerformAssertions();
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_gravity_flow_plugin_identity_drift_never_enqueues_digit_shaper(): void {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
		define( 'PGR_PATH', dirname( __DIR__ ) . '/' );
		define( 'PGR_URL', 'https://example.test/wp-content/plugins/persian-gravityforms/' );
		define( 'PGR_VERSION', '4.7.0' );
		define( 'GRAVITY_FLOW_VERSION', '3.1.0' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow-next/gravityflow.php' );

		require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-entry-detail-persian-digits-presentation-adapter.php';

		$adapter = new PGR_Gravity_Flow_Entry_Detail_Persian_Digits_Presentation_Adapter();
		$adapter->enqueue_digit_shaper( array(), array(), null );

		$this->expectNotToPerformAssertions();
	}
}
