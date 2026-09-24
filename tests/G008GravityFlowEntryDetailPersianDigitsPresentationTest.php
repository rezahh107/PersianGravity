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

require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-entry-detail-persian-digits-presentation-adapter.php';

final class G008GravityFlowEntryDetailPersianDigitsPresentationTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pgr_test_actions']  = array();
		$GLOBALS['pgr_test_enqueued'] = array();
		$GLOBALS['pgr_test_locale']   = 'en_US';
	}

	public function test_registers_only_the_bounded_post_render_presentation_hook(): void {
		$adapter = new PGR_Gravity_Flow_Entry_Detail_Persian_Digits_Presentation_Adapter();
		$adapter->hooks();

		$this->assertArrayHasKey( 'gravityflow_below_workflow_info_entry_detail', $GLOBALS['pgr_test_actions'] );
		$this->assertArrayNotHasKey( 'gravityflow_step_due_date_timestamp', $GLOBALS['pgr_test_actions'] );
		$this->assertArrayNotHasKey( 'gravityflow_step_schedule_timestamp', $GLOBALS['pgr_test_actions'] );
		$this->assertArrayNotHasKey( 'gravityflow_step_expiration_timestamp', $GLOBALS['pgr_test_actions'] );
		$this->assertArrayNotHasKey( 'gravityflow_timeline_notes', $GLOBALS['pgr_test_actions'] );
	}

	public function test_exact_host_and_persian_locale_enqueue_text_node_adapter_once(): void {
		$GLOBALS['pgr_test_locale'] = 'fa_IR';
		$adapter                    = new PGR_Gravity_Flow_Entry_Detail_Persian_Digits_Presentation_Adapter();

		$adapter->enqueue_digit_shaper( array(), array(), null );
		$adapter->enqueue_digit_shaper( array(), array(), null );

		$this->assertSame(
			array( 'pgr-gravity-flow-entry-detail-persian-digits' ),
			$GLOBALS['pgr_test_enqueued']
		);
	}

	public function test_non_persian_locale_preserves_native_presentation(): void {
		$GLOBALS['pgr_test_locale'] = 'en_US';
		$adapter                    = new PGR_Gravity_Flow_Entry_Detail_Persian_Digits_Presentation_Adapter();

		$adapter->enqueue_digit_shaper( array(), array(), null );

		$this->assertSame( array(), $GLOBALS['pgr_test_enqueued'] );
	}

	public function test_source_never_hooks_operational_state_or_timeline_seams(): void {
		$source = (string) file_get_contents(
			dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-entry-detail-persian-digits-presentation-adapter.php'
		);

		$this->assertStringContainsString( "'gravityflow_below_workflow_info_entry_detail'", $source );
		$this->assertStringContainsString( "'fa_IR' !== determine_locale()", $source );
		$this->assertStringContainsString( "PGR_PATH . 'includes/localization/products.php'", $source );
		$this->assertStringNotContainsString( 'get_due_date_timestamp()', $source );
		$this->assertStringNotContainsString( 'get_schedule_timestamp()', $source );
		$this->assertStringNotContainsString( 'get_expiration_timestamp()', $source );
		$this->assertStringNotContainsString( "'gravityflow_timeline_notes'", $source );
		$this->assertStringNotContainsString( "'3.1.0'", $source );
	}
}
