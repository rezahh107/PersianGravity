<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'PGR_PATH' ) ) {
	define( 'PGR_PATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'PGR_URL' ) ) {
	define( 'PGR_URL', 'https://example.test/wp-content/plugins/persian-gravityforms/' );
}
if ( ! defined( 'PGR_VERSION' ) ) {
	define( 'PGR_VERSION', '4.7.0' );
}
if ( ! defined( 'GRAVITY_FLOW_VERSION' ) ) {
	define( 'GRAVITY_FLOW_VERSION', '3.1.0' );
}
if ( ! defined( 'GRAVITY_FLOW_PLUGIN_BASENAME' ) ) {
	define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow/gravityflow.php' );
}

require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-timeline-persian-time-digits-presentation-adapter.php';

final class G008GravityFlowTimelinePersianTimeDigitsPresentationTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pgr_test_actions']  = array();
		$GLOBALS['pgr_test_enqueued'] = array();
		$GLOBALS['pgr_test_locale']   = 'en_US';
	}

	public function test_registers_only_the_source_authenticated_timeline_presentation_signal(): void {
		$adapter = new PGR_Gravity_Flow_Timeline_Persian_Time_Digits_Presentation_Adapter();
		$adapter->hooks();

		$this->assertArrayHasKey( 'pgr_gravity_flow_timeline_header_presented', $GLOBALS['pgr_test_actions'] );
		$this->assertArrayNotHasKey( 'gravityflow_timeline_notes', $GLOBALS['pgr_test_actions'] );
		$this->assertArrayNotHasKey( 'wp_footer', $GLOBALS['pgr_test_actions'] );
		$this->assertArrayNotHasKey( 'wp_enqueue_scripts', $GLOBALS['pgr_test_actions'] );
	}

	public function test_exact_host_and_persian_locale_enqueue_header_shaper_once(): void {
		$GLOBALS['pgr_test_locale'] = 'fa_IR';
		$adapter                    = new PGR_Gravity_Flow_Timeline_Persian_Time_Digits_Presentation_Adapter();
		$adapter->hooks();

		do_action( 'pgr_gravity_flow_timeline_header_presented' );
		do_action( 'pgr_gravity_flow_timeline_header_presented' );

		$this->assertSame(
			array( 'pgr-gravity-flow-timeline-persian-time-digits' ),
			$GLOBALS['pgr_test_enqueued']
		);
	}

	public function test_non_persian_locale_preserves_native_ascii_time_presentation(): void {
		$GLOBALS['pgr_test_locale'] = 'en_US';
		$adapter                    = new PGR_Gravity_Flow_Timeline_Persian_Time_Digits_Presentation_Adapter();
		$adapter->hooks();

		do_action( 'pgr_gravity_flow_timeline_header_presented' );

		$this->assertSame( array(), $GLOBALS['pgr_test_enqueued'] );
	}

	public function test_timeline_calendar_adapter_emits_signal_only_after_successful_conversion_guard(): void {
		$source = (string) file_get_contents(
			dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-timeline-jalali-presentation-adapter.php'
		);

		$guard  = strpos( $source, 'if ( null === $formatted )' );
		$signal = strpos( $source, "do_action( 'pgr_gravity_flow_timeline_header_presented' )" );
		$return = strpos( $source, 'return $formatted;', $signal );

		$this->assertNotFalse( $guard );
		$this->assertNotFalse( $signal );
		$this->assertNotFalse( $return );
		$this->assertLessThan( $signal, $guard );
		$this->assertLessThan( $return, $signal );
	}

	public function test_source_does_not_hook_host_operational_or_timeline_data_seams(): void {
		$source = (string) file_get_contents(
			dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-timeline-persian-time-digits-presentation-adapter.php'
		);

		$this->assertStringContainsString( "'pgr_gravity_flow_timeline_header_presented'", $source );
		$this->assertStringContainsString( "'fa_IR' !== determine_locale()", $source );
		$this->assertStringContainsString( "PGR_PATH . 'includes/localization/products.php'", $source );
		$this->assertStringNotContainsString( "'gravityflow_timeline_notes'", $source );
		$this->assertStringNotContainsString( 'get_due_date_timestamp()', $source );
		$this->assertStringNotContainsString( 'get_schedule_timestamp()', $source );
		$this->assertStringNotContainsString( 'get_expiration_timestamp()', $source );
	}
}
