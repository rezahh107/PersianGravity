<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-pgr-gregorian-jalali-converter.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-jalali-presentation.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-timeline-jalali-presentation-adapter.php';

final class G008GravityFlowTimelineJalaliPresentationTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pgr_test_filters'] = array();
		$GLOBALS['pgr_test_locale']  = 'fa_IR';
	}

	public function test_missing_exact_host_contract_registers_no_generic_timeline_hook(): void {
		$adapter = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();
		$adapter->hooks();

		$this->assertArrayNotHasKey( 'option_date_format', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'date_i18n', $GLOBALS['pgr_test_filters'] );
	}

	public function test_non_persian_locale_registers_no_timeline_calendar_hooks(): void {
		$GLOBALS['pgr_test_locale'] = 'en_US';
		$adapter                    = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();
		$reflection                 = new ReflectionClass( $adapter );
		$property                   = $reflection->getProperty( 'host_contract_valid' );
		$property->setValue( $adapter, true );

		$adapter->hooks();

		$this->assertArrayNotHasKey( 'option_date_format', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'date_i18n', $GLOBALS['pgr_test_filters'] );
	}

	public function test_exact_cached_host_contract_registers_only_first_seam_until_row_arms(): void {
		$adapter    = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();
		$reflection = new ReflectionClass( $adapter );
		$property   = $reflection->getProperty( 'host_contract_valid' );
		$property->setValue( $adapter, true );
		$adapter->hooks();

		$this->assertArrayHasKey( 'option_date_format', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'option_time_format', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'date_i18n', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_timeline_notes', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_step_due_date_timestamp', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_step_schedule_timestamp', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_step_expiration_timestamp', $GLOBALS['pgr_test_filters'] );
	}


	public function test_persian_locale_registers_bounded_time_format_capture_without_extra_date_hook(): void {
		$GLOBALS['pgr_test_locale'] = 'fa_IR';
		$adapter                    = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();
		$reflection                 = new ReflectionClass( $adapter );
		$property                   = $reflection->getProperty( 'host_contract_valid' );
		$property->setValue( $adapter, true );
		$adapter->hooks();

		$this->assertArrayHasKey( 'option_date_format', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayHasKey( 'option_time_format', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'date_i18n', $GLOBALS['pgr_test_filters'] );
	}

	public function test_unrelated_time_format_call_fails_closed_byte_for_byte(): void {
		$GLOBALS['pgr_test_locale'] = 'fa_IR';
		$adapter                    = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();
		$reflection                 = new ReflectionClass( $adapter );
		$property                   = $reflection->getProperty( 'host_contract_valid' );
		$property->setValue( $adapter, true );

		$this->assertSame( 'g:i a', $adapter->filter_time_format( 'g:i a', 'time_format' ) );
		$this->assertSame( 'g:i a', $adapter->filter_time_format( 'g:i a', 'not_time_format' ) );
	}

	public function test_time_glyph_shaper_changes_ascii_only_and_preserves_existing_persian_digits(): void {
		$adapter    = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();
		$reflection = new ReflectionClass( $adapter );
		$method     = $reflection->getMethod( 'shape_ascii_digits' );

		$this->assertSame( '۱۲:۰۱ ق.ظ', $method->invoke( $adapter, '12:01 ق.ظ' ) );
		$this->assertSame( '۱۱:۵۹ ب.ظ', $method->invoke( $adapter, '11:59 ب.ظ' ) );
		$this->assertSame( 'already ۱۲:۰۱', $method->invoke( $adapter, 'already ۱۲:۰۱' ) );
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


	public function test_time_digits_require_the_same_row_calendar_admission(): void {
		$GLOBALS['pgr_test_locale'] = 'fa_IR';
		$adapter                    = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();
		$reflection                 = new ReflectionClass( $adapter );
		$contexts                   = $reflection->getProperty( 'time_contexts' );
		$host_contract              = $reflection->getProperty( 'host_contract_valid' );
		$product_slug               = $reflection->getProperty( 'flow_product_slug' );
		$shape                      = $reflection->getMethod( 'shape_timeline_time_digits' );

		$host_contract->setValue( $adapter, true );
		$product_slug->setValue( $adapter, 'gravityflow' );

		$raw   = '2030-03-20 20:31:00';
		$note  = (object) array(
			'id'           => 7,
			'date_created' => $raw,
			'note_type'    => 'gravityflow',
		);
		$notes = array( $note );
		$entry = array(
			'id'           => 12,
			'form_id'      => 3,
			'date_created' => $raw,
		);
		$form      = array( 'id' => 3 );
		$timestamp = 1900269060;
		$key       = spl_object_id( $note );
		$context   = array(
			'note'              => $note,
			'note_snapshot'     => get_object_vars( $note ),
			'notes'             => $notes,
			'notes_order'       => array( $key ),
			'entry'             => $entry,
			'entry_key'         => '3:12',
			'form'              => $form,
			'raw'               => $raw,
			'timestamp'         => $timestamp,
			'time_format'       => 'g:i a',
			'event_kind'        => 'stored',
			'calendar_admitted' => false,
		);
		$path      = array(
			array( 'function' => 'date_i18n' ),
			array( 'class' => 'GFCommon', 'type' => '::', 'function' => 'format_date', 'args' => array( $raw, false, '', true ) ),
			array( 'class' => 'Gravity_Flow_Common', 'type' => '::', 'function' => 'format_date', 'args' => array( $raw, '', false, true ) ),
			array( 'class' => 'Gravity_Flow_Entry_Detail', 'type' => '::', 'function' => 'get_note_header', 'args' => array( 'Runtime Admin', $raw ) ),
			array( 'class' => 'Gravity_Flow_Entry_Detail', 'type' => '::', 'function' => 'get_note_body', 'args' => array( $note, 'Runtime Admin' ) ),
			array( 'class' => 'Gravity_Flow_Entry_Detail', 'type' => '::', 'function' => 'notes_grid', 'args' => array( $notes ) ),
			array( 'class' => 'Gravity_Flow_Entry_Detail', 'type' => '::', 'function' => 'timeline', 'args' => array( $entry, $form ) ),
		);

		$contexts->setValue( $adapter, array( $key => $context ) );
		$this->assertSame( '12:01 ق.ظ', $shape->invoke( $adapter, '12:01 ق.ظ', 'g:i a', $timestamp, true, $path ) );

		$context['calendar_admitted'] = true;
		$contexts->setValue( $adapter, array( $key => $context ) );
		$this->assertSame( '۱۲:۰۱ ق.ظ', $shape->invoke( $adapter, '12:01 ق.ظ', 'g:i a', $timestamp, true, $path ) );
	}

	public function test_host_identity_resolves_only_exact_versions_through_approved_manifest_shape(): void {
		$reflection = new ReflectionClass( PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter::class );
		$method     = $reflection->getMethod( 'resolve_product_slug' );
		$adapter    = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();
		$products   = array(
			array(
				'product' => 'host-flow',
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
		$reflection = new ReflectionClass( PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter::class );
		$expected   = $reflection->getConstant( 'SOURCE_FINGERPRINTS' );
		$method     = $reflection->getMethod( 'fingerprints_match' );
		$adapter    = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();

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

	public function test_host_contract_result_is_request_cached_after_first_resolution(): void {
		$reflection = new ReflectionClass( PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter::class );
		$method     = $reflection->getMethod( 'is_exact_supported_host' );
		$property   = $reflection->getProperty( 'host_contract_valid' );
		$adapter    = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();

		$property->setValue( $adapter, true );
		$this->assertTrue( $method->invoke( $adapter ) );
		$property->setValue( $adapter, false );
		$this->assertFalse( $method->invoke( $adapter ) );
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
		$first      = $create->invoke( $adapter, 'F j, Y' );
		$second     = $create->invoke( $adapter, 'F j, Y' );

		$this->assertIsArray( $first );
		$this->assertIsArray( $second );
		$this->assertNotSame( $first['format'], $second['format'] );
		$this->assertStringStartsWith( '\\P\\G\\R\\T\\I\\M\\E\\L\\I\\N\\E', $first['format'] );
		$this->assertStringEndsWith( 'F j, Y', $first['format'] );
		$property->setValue( $adapter, array( $first['format'] => $first['literal'] ) );
		$this->assertSame( 'March 21, 2030', $strip->invoke( $adapter, $first['literal'] . 'March 21, 2030' ) );
	}

	public function test_production_source_contains_no_rejected_mutation_or_foreign_domain_literal(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-timeline-jalali-presentation-adapter.php' );

		$this->assertStringContainsString( "add_filter( 'option_date_format'", $source );
		$this->assertStringContainsString( "'fa_IR' !== determine_locale()", $source );
		$this->assertStringContainsString( "add_filter( 'option_time_format'", $source );
		$this->assertStringContainsString( "'fa_IR' === determine_locale()", $source );
		$this->assertStringContainsString( "add_filter( 'date_i18n'", $source );
		$this->assertStringContainsString( "PGR_PATH . 'includes/localization/products.php'", $source );
		$this->assertStringContainsString( 'PGR_Jalali_Presentation::format_date', $source );
		$this->assertStringContainsString( "hash_file( 'sha256'", $source );
		$this->assertStringNotContainsString( "'gravityflow'", $source );
		$this->assertStringNotContainsString( "add_filter( 'gravityflow_timeline_notes'", $source );
		$this->assertStringNotContainsString( '->value', $source );
		$this->assertStringNotContainsString( 'wp_enqueue_script', $source );
		$this->assertStringNotContainsString( 'querySelector', $source );
		$this->assertStringNotContainsString( 'get_due_date_timestamp()', $source );
		$this->assertStringNotContainsString( 'get_schedule_timestamp()', $source );
		$this->assertStringNotContainsString( 'get_expiration_timestamp()', $source );
		$this->assertStringNotContainsString( '->date_created =', $source );
		$this->assertStringNotContainsString( 'ob_start(', $source );
		$this->assertStringNotContainsString( 'preg_replace(', $source );
	}
}
