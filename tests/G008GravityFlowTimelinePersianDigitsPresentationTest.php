<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-timeline-persian-digits-presentation-adapter.php';

final class G008GravityFlowTimelinePersianDigitsPresentationTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pgr_test_filters']  = array();
		$GLOBALS['pgr_test_locale']   = 'fa_IR';
		$GLOBALS['pgr_test_timezone'] = 'Asia/Tehran';
	}

	public function test_exact_cached_contract_and_persian_locale_register_only_time_presentation_seams(): void {
		$adapter    = new PGR_Gravity_Flow_Timeline_Persian_Digits_Presentation_Adapter();
		$reflection = new ReflectionClass( $adapter );
		$property   = $reflection->getProperty( 'host_contract_valid' );
		$property->setValue( $adapter, true );

		$adapter->hooks();

		$this->assertArrayHasKey( 'option_time_format', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayHasKey( 'date_i18n', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_timeline_notes', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_step_due_date_timestamp', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_step_schedule_timestamp', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_step_expiration_timestamp', $GLOBALS['pgr_test_filters'] );
	}

	public function test_wrong_locale_registers_no_time_digit_presentation_hook(): void {
		$GLOBALS['pgr_test_locale'] = 'en_US';
		$adapter                    = new PGR_Gravity_Flow_Timeline_Persian_Digits_Presentation_Adapter();
		$reflection                 = new ReflectionClass( $adapter );
		$property                   = $reflection->getProperty( 'host_contract_valid' );
		$property->setValue( $adapter, true );

		$adapter->hooks();

		$this->assertArrayNotHasKey( 'option_time_format', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'date_i18n', $GLOBALS['pgr_test_filters'] );
	}

	public function test_missing_exact_host_contract_registers_no_time_digit_presentation_hook(): void {
		$adapter = new PGR_Gravity_Flow_Timeline_Persian_Digits_Presentation_Adapter();
		$adapter->hooks();

		$this->assertArrayNotHasKey( 'option_time_format', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'date_i18n', $GLOBALS['pgr_test_filters'] );
	}

	public function test_unrelated_time_format_and_date_i18n_calls_fail_closed(): void {
		$adapter    = new PGR_Gravity_Flow_Timeline_Persian_Digits_Presentation_Adapter();
		$reflection = new ReflectionClass( $adapter );
		$property   = $reflection->getProperty( 'host_contract_valid' );
		$property->setValue( $adapter, true );

		$this->assertSame( 'g:i a', $adapter->capture_time_format( 'g:i a', 'time_format' ) );
		$this->assertSame( '12:01 ق.ظ', $adapter->shape_timeline_time_digits( '12:01 ق.ظ', 'g:i a', 1900269060, true ) );
	}

	public function test_ascii_time_digits_are_shaped_without_corrupting_existing_persian_digits(): void {
		$adapter    = new PGR_Gravity_Flow_Timeline_Persian_Digits_Presentation_Adapter();
		$reflection = new ReflectionClass( $adapter );
		$method     = $reflection->getMethod( 'shape_ascii_digits' );

		$this->assertSame( '۱۲:۰۱ ق.ظ', $method->invoke( $adapter, '12:01 ق.ظ' ) );
		$this->assertSame( '۱۱:۵۹ ب.ظ', $method->invoke( $adapter, '11:59 ب.ظ' ) );
		$this->assertSame( 'already ۱۲:۰۱', $method->invoke( $adapter, 'already ۱۲:۰۱' ) );
	}

	public function test_host_identity_resolves_only_exact_versions_through_approved_manifest_shape(): void {
		$adapter    = new PGR_Gravity_Flow_Timeline_Persian_Digits_Presentation_Adapter();
		$reflection = new ReflectionClass( $adapter );
		$method     = $reflection->getMethod( 'resolve_product_slug' );
		$products   = array(
			array(
				'product'        => 'host-flow',
				'target_version' => '3.1.0',
			),
		);

		$this->assertSame( 'host-flow', $method->invoke( $adapter, '3.1.0', '3.1.1.1', 'host-flow/plugin.php', $products ) );
		$this->assertNull( $method->invoke( $adapter, '3.1.1', '3.1.1.1', 'host-flow/plugin.php', $products ) );
		$this->assertNull( $method->invoke( $adapter, '3.1.0', '3.2.0', 'host-flow/plugin.php', $products ) );
		$this->assertNull( $method->invoke( $adapter, '3.1.0', '3.1.1.1', 'other/plugin.php', $products ) );
		$this->assertNull( $method->invoke( $adapter, '3.1.0', '3.1.1.1', '../host-flow/plugin.php', $products ) );
	}

	public function test_source_fingerprint_contract_accepts_only_exact_qualified_set(): void {
		$adapter    = new PGR_Gravity_Flow_Timeline_Persian_Digits_Presentation_Adapter();
		$reflection = new ReflectionClass( $adapter );
		$expected   = $reflection->getConstant( 'SOURCE_FINGERPRINTS' );
		$method     = $reflection->getMethod( 'fingerprints_match' );

		$this->assertSame(
			array(
				'flow_entry_detail' => 'a7634c5604184502457bcb22cdf1ade892e84c888cc60996aea8a810ced7680a',
				'flow_common'       => 'a8844f4b6ac37eed1a2cc1e904418f6ed8c6b4480e982c5a809e895a6f9e0cc8',
				'flow_print'        => 'df969bf8a37ed4f5619e0e8b2a753dd1133fa0c7551b158d740248c67fcb95c6',
				'gf_common'         => 'ac4ed495ee02a119a4fd08c77279f0db0905e6f20f59c472d5e6bffc801ca355',
			),
			$expected
		);
		$this->assertTrue( $method->invoke( $adapter, $expected ) );

		$drift = $expected;
		$drift['flow_common'] = str_repeat( '0', 64 );
		$this->assertFalse( $method->invoke( $adapter, $drift ) );
	}

	public function test_nearest_chain_cannot_borrow_outer_frames(): void {
		$adapter    = new PGR_Gravity_Flow_Timeline_Persian_Digits_Presentation_Adapter();
		$reflection = new ReflectionClass( $adapter );
		$method     = $reflection->getMethod( 'nearest_contiguous_chain' );
		$expected   = array( 'date_i18n', 'GFCommon::format_date', 'Gravity_Flow_Common::format_date' );

		$nested_mismatch = array(
			array( 'function' => 'date_i18n' ),
			array( 'class' => 'Other', 'type' => '::', 'function' => 'format_date' ),
			array( 'function' => 'date_i18n' ),
			array( 'class' => 'GFCommon', 'type' => '::', 'function' => 'format_date' ),
			array( 'class' => 'Gravity_Flow_Common', 'type' => '::', 'function' => 'format_date' ),
		);

		$this->assertNull( $method->invoke( $adapter, $nested_mismatch, $expected ) );
		$this->assertSame(
			array_slice( $nested_mismatch, 2 ),
			$method->invoke( $adapter, array_slice( $nested_mismatch, 2 ), $expected )
		);
	}

	public function test_note_identity_order_distinguishes_duplicate_timestamps(): void {
		$adapter    = new PGR_Gravity_Flow_Timeline_Persian_Digits_Presentation_Adapter();
		$reflection = new ReflectionClass( $adapter );
		$method     = $reflection->getMethod( 'note_identity_order' );
		$first      = (object) array( 'id' => 10, 'date_created' => '2030-03-20 20:31:00' );
		$second     = (object) array( 'id' => 11, 'date_created' => '2030-03-20 20:31:00' );

		$forward = $method->invoke( $adapter, array( $first, $second ) );
		$reverse = $method->invoke( $adapter, array( $second, $first ) );
		$this->assertCount( 2, $forward );
		$this->assertNotSame( $forward[0], $forward[1] );
		$this->assertNotSame( $forward, $reverse );
	}

	public function test_production_source_is_header_only_and_never_rewrites_timeline_body_or_machine_state(): void {
		$source = (string) file_get_contents(
			dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-timeline-persian-digits-presentation-adapter.php'
		);

		$this->assertStringContainsString( "'option_time_format'", $source );
		$this->assertStringContainsString( "'date_i18n'", $source );
		$this->assertStringContainsString( "'Gravity_Flow_Entry_Detail::get_note_header'", $source );
		$this->assertStringContainsString( "'Gravity_Flow_Entry_Detail::get_note_body'", $source );
		$this->assertStringContainsString( "'Gravity_Flow_Entry_Detail::notes_grid'", $source );
		$this->assertStringContainsString( "'Gravity_Flow_Entry_Detail::timeline'", $source );
		$this->assertStringContainsString( "'fa_IR' !== determine_locale()", $source );
		$this->assertStringNotContainsString( '->value', $source );
		$this->assertStringNotContainsString( "'gravityflow_timeline_notes'", $source );
		$this->assertStringNotContainsString( 'wp_enqueue_script', $source );
		$this->assertStringNotContainsString( 'querySelector', $source );
		$this->assertStringNotContainsString( 'get_due_date_timestamp()', $source );
		$this->assertStringNotContainsString( 'get_schedule_timestamp()', $source );
		$this->assertStringNotContainsString( 'get_expiration_timestamp()', $source );
		$this->assertStringNotContainsString( 'ob_start(', $source );
	}
}
