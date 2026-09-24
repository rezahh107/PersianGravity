<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-timeline-jalali-presentation-adapter.php';

final class G008GravityFlowTimelineJalaliPresentationTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pgr_test_filters'] = array();
	}

	public function test_registers_only_first_timeline_seam_until_an_authentic_row_arms(): void {
		$adapter = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();
		$adapter->hooks();

		$this->assertArrayHasKey( 'option_date_format', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'date_i18n', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_timeline_notes', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_step_due_date_timestamp', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_step_schedule_timestamp', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_step_expiration_timestamp', $GLOBALS['pgr_test_filters'] );
	}

	public function test_unrelated_option_date_format_call_never_arms(): void {
		$adapter = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();

		$this->assertSame( 'F j, Y', $adapter->filter_date_format( 'F j, Y', 'date_format' ) );
		$this->assertSame( 'Y-m-d', $adapter->filter_date_format( 'Y-m-d', 'date_format' ) );
		$this->assertArrayNotHasKey( 'date_i18n', $GLOBALS['pgr_test_filters'] );
	}

	public function test_unsupported_u_and_other_profiles_remain_native(): void {
		$adapter = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();

		$this->assertSame( 'U', $adapter->filter_date_format( 'U', 'date_format' ) );
		$this->assertSame( 'd/m/Y', $adapter->filter_date_format( 'd/m/Y', 'date_format' ) );
		$this->assertSame( 'F j, Y', $adapter->filter_date_format( 'F j, Y', 'not_date_format' ) );
	}

	public function test_unowned_date_i18n_call_is_byte_for_byte_native(): void {
		$adapter = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();

		$this->assertSame(
			'March 21, 2030 11:59',
			$adapter->filter_date_i18n( 'March 21, 2030 11:59', 'F j, Y H:i', 1900269060, true )
		);
	}

	public function test_source_fingerprint_contract_accepts_only_exact_qualified_set(): void {
		$reflection = new ReflectionClass( PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter::class );
		$expected   = $reflection->getConstant( 'SOURCE_FINGERPRINTS' );
		$method     = $reflection->getMethod( 'fingerprints_match' );
		$adapter    = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();

		$this->assertIsArray( $expected );
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

	public function test_nearest_chain_cannot_borrow_an_outer_reentrant_frame(): void {
		$reflection = new ReflectionClass( PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter::class );
		$method     = $reflection->getMethod( 'nearest_contiguous_chain' );
		$expected   = array( 'date_i18n', 'GFCommon::format_date', 'Gravity_Flow_Common::format_date' );
		$adapter    = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();

		$nested_mismatch = array(
			array( 'function' => 'date_i18n' ),
			array( 'class' => 'Other', 'type' => '::', 'function' => 'format_date' ),
			array( 'function' => 'date_i18n' ),
			array( 'class' => 'GFCommon', 'type' => '::', 'function' => 'format_date' ),
			array( 'class' => 'Gravity_Flow_Common', 'type' => '::', 'function' => 'format_date' ),
		);
		$this->assertNull( $method->invoke( $adapter, $nested_mismatch, $expected ) );

		$contiguous = array_slice( $nested_mismatch, 2 );
		$this->assertSame( $contiguous, $method->invoke( $adapter, $contiguous, $expected ) );
	}

	public function test_note_identity_order_distinguishes_duplicate_timestamps_and_reordering(): void {
		$reflection = new ReflectionClass( PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter::class );
		$method     = $reflection->getMethod( 'note_identity_order' );
		$adapter    = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();
		$first      = (object) array( 'id' => 10, 'date_created' => '2030-03-20 20:31:00' );
		$second     = (object) array( 'id' => 11, 'date_created' => '2030-03-20 20:31:00' );

		$forward = $method->invoke( $adapter, array( $first, $second ) );
		$reverse = $method->invoke( $adapter, array( $second, $first ) );
		$this->assertCount( 2, $forward );
		$this->assertNotSame( $forward[0], $forward[1] );
		$this->assertNotSame( $forward, $reverse );
	}

	public function test_owned_marker_is_escaped_unique_and_stripped_from_fallback(): void {
		$reflection = new ReflectionClass( PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter::class );
		$create     = $reflection->getMethod( 'create_marked_format' );
		$strip      = $reflection->getMethod( 'strip_owned_markers' );
		$property   = $reflection->getProperty( 'marker_literals' );
		$adapter    = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();
		$marker     = $create->invoke( $adapter, 'F j, Y' );

		$this->assertIsArray( $marker );
		$this->assertStringStartsWith( '\\P\\G\\R\\T\\I\\M\\E\\L\\I\\N\\E', $marker['format'] );
		$this->assertStringEndsWith( 'F j, Y', $marker['format'] );
		$property->setValue( $adapter, array( $marker['format'] => $marker['literal'] ) );
		$this->assertSame( 'March 21, 2030', $strip->invoke( $adapter, $marker['literal'] . 'March 21, 2030' ) );
	}

	public function test_production_source_contains_no_rejected_mutation_or_rewrite_mechanism(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-timeline-jalali-presentation-adapter.php' );

		$this->assertStringContainsString( "add_filter( 'option_date_format'", $source );
		$this->assertStringContainsString( "add_filter( 'date_i18n'", $source );
		$this->assertStringContainsString( 'PGR_Jalali_Presentation::format_date', $source );
		$this->assertStringNotContainsString( "add_filter( 'gravityflow_timeline_notes'", $source );
		$this->assertStringNotContainsString( '->date_created =', $source );
		$this->assertStringNotContainsString( 'ob_start(', $source );
		$this->assertStringNotContainsString( 'preg_replace(', $source );
	}
}
