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

	public function test_persian_locale_registers_passive_format_seams_before_behavioral_admission(): void {
		$adapter = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();
		$adapter->hooks();

		$this->assertArrayHasKey( 'option_date_format', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayHasKey( 'option_time_format', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'date_i18n', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_timeline_notes', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_step_due_date_timestamp', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_step_schedule_timestamp', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_step_expiration_timestamp', $GLOBALS['pgr_test_filters'] );
	}

	public function test_non_persian_locale_registers_no_timeline_calendar_hooks(): void {
		$GLOBALS['pgr_test_locale'] = 'en_US';
		$adapter                    = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();
		$adapter->hooks();

		$this->assertArrayNotHasKey( 'option_date_format', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'option_time_format', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'date_i18n', $GLOBALS['pgr_test_filters'] );
	}

	public function test_unrelated_format_calls_remain_native_and_never_arm(): void {
		$adapter = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();

		$this->assertSame( 'F j, Y', $adapter->filter_date_format( 'F j, Y', 'date_format' ) );
		$this->assertSame( 'Y-m-d', $adapter->filter_date_format( 'Y-m-d', 'date_format' ) );
		$this->assertSame( 'U', $adapter->filter_date_format( 'U', 'date_format' ) );
		$this->assertSame( 'd/m/Y', $adapter->filter_date_format( 'd/m/Y', 'date_format' ) );
		$this->assertSame( 'F j, Y', $adapter->filter_date_format( 'F j, Y', 'not_date_format' ) );
		$this->assertSame( 'g:i a', $adapter->filter_time_format( 'g:i a', 'time_format' ) );
		$this->assertSame( 'g:i a', $adapter->filter_time_format( 'g:i a', 'not_time_format' ) );
		$this->assertArrayNotHasKey( 'date_i18n', $GLOBALS['pgr_test_filters'] );
	}

	public function test_unowned_date_i18n_call_is_byte_for_byte_native(): void {
		$adapter = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();
		$this->assertSame(
			'March 21, 2030 11:59',
			$adapter->filter_date_i18n( 'March 21, 2030 11:59', 'F j, Y H:i', 1900269060, true )
		);
	}

	public function test_controlled_version_only_drift_does_not_change_canonical_product_identity(): void {
		$adapter    = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();
		$reflection = new ReflectionClass( $adapter );
		$method     = $reflection->getMethod( 'resolve_product_slug' );

		$this->assertSame( 'gravityflow', $method->invoke( $adapter, '3.1.0', '3.1.1.1', 'gravityflow/gravityflow.php' ) );
		$this->assertSame( 'gravityflow', $method->invoke( $adapter, '3.1.1', '3.1.1.1', 'gravityflow/gravityflow.php' ) );
		$this->assertSame( 'gravityflow', $method->invoke( $adapter, '3.1.0', '3.2.0', 'gravityflow/gravityflow.php' ) );
		$this->assertSame( 'gravityflow', $method->invoke( $adapter, '4.0.0-beta.1', '3.3.0', 'gravityflow/gravityflow.php' ) );

		$this->assertNull( $method->invoke( $adapter, '', '3.1.1.1', 'gravityflow/gravityflow.php' ) );
		$this->assertNull( $method->invoke( $adapter, '3.1.0', 'bad version', 'gravityflow/gravityflow.php' ) );
		$this->assertNull( $method->invoke( $adapter, '3.1.0', '3.1.1.1', 'other/gravityflow.php' ) );
		$this->assertNull( $method->invoke( $adapter, '3.1.0', '3.1.1.1', '../gravityflow/gravityflow.php' ) );
	}

	public function test_host_identity_result_is_request_cached_without_version_equality_or_source_hashing(): void {
		$reflection = new ReflectionClass( PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter::class );
		$method     = $reflection->getMethod( 'has_qualified_host_identity' );
		$property   = $reflection->getProperty( 'host_contract_valid' );
		$adapter    = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();

		$property->setValue( $adapter, true );
		$this->assertTrue( $method->invoke( $adapter ) );
		$property->setValue( $adapter, false );
		$this->assertFalse( $method->invoke( $adapter ) );
	}

	public function test_time_glyph_shaper_changes_ascii_only_and_preserves_existing_persian_digits(): void {
		$adapter    = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();
		$reflection = new ReflectionClass( $adapter );
		$method     = $reflection->getMethod( 'shape_ascii_digits' );

		$this->assertSame( '۱۲:۰۱ ق.ظ', $method->invoke( $adapter, '12:01 ق.ظ' ) );
		$this->assertSame( '۱۱:۵۹ ب.ظ', $method->invoke( $adapter, '11:59 ب.ظ' ) );
		$this->assertSame( 'already ۱۲:۰۱', $method->invoke( $adapter, 'already ۱۲:۰۱' ) );
	}

	public function test_time_capture_reuses_only_one_exact_active_calendar_row_context(): void {
		$adapter    = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();
		$reflection = new ReflectionClass( $adapter );
		$contexts   = $reflection->getProperty( 'contexts' );
		$resolve    = $reflection->getMethod( 'active_calendar_context_for_time_path' );
		$order      = $reflection->getMethod( 'note_identity_order' );

		$raw   = '2030-03-20 20:31:00';
		$note  = (object) array( 'id' => 7, 'date_created' => $raw, 'note_type' => 'gravityflow' );
		$notes = array( $note );
		$entry = array( 'id' => 12, 'form_id' => 3, 'date_created' => $raw );
		$form  = array( 'id' => 3 );
		$context = array(
			'note' => $note,
			'note_snapshot' => get_object_vars( $note ),
			'notes' => $notes,
			'notes_order' => $order->invoke( $adapter, $notes ),
			'entry' => $entry,
			'entry_key' => '3:12',
			'form' => $form,
			'raw' => $raw,
			'timestamp' => 1900281660,
			'native_format' => 'F j, Y',
			'event_kind' => 'stored',
			'print_inherited' => false,
		);
		$marked = '\\P\\G\\R\\T\\I\\M\\E\\L\\I\\N\\E\\a\\b\\c\\X' . 'F j, Y';
		$contexts->setValue( $adapter, array( $marked => $context ) );

		$path    = array_fill( 0, 8, array() );
		$path[5] = array( 'args' => array( $note, 'Runtime Admin' ) );
		$path[6] = array( 'args' => array( $notes ) );
		$path[7] = array( 'args' => array( $entry, $form ) );

		$owned = $resolve->invoke( $adapter, $path );
		$this->assertIsArray( $owned );
		$this->assertSame( $marked, $owned['format'] );
		$this->assertSame( $context, $owned['context'] );

		$mutated = clone $note;
		$mutated->value = 'changed';
		$bad_path       = $path;
		$bad_path[5]    = array( 'args' => array( $mutated, 'Runtime Admin' ) );
		$this->assertNull( $resolve->invoke( $adapter, $bad_path ) );

		$contexts->setValue( $adapter, array( $marked => $context, $marked . '\\2' => $context ) );
		$this->assertNull( $resolve->invoke( $adapter, $path ) );
	}

	public function test_time_digits_require_same_row_calendar_admission_and_identity(): void {
		$adapter    = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();
		$reflection = new ReflectionClass( $adapter );
		$contexts   = $reflection->getProperty( 'time_contexts' );
		$host       = $reflection->getProperty( 'host_contract_valid' );
		$product    = $reflection->getProperty( 'flow_product_slug' );
		$shape      = $reflection->getMethod( 'shape_timeline_time_digits' );

		$host->setValue( $adapter, true );
		$product->setValue( $adapter, 'gravityflow' );
		$raw   = '2030-03-20 20:31:00';
		$note  = (object) array( 'id' => 7, 'date_created' => $raw, 'note_type' => 'gravityflow' );
		$notes = array( $note );
		$entry = array( 'id' => 12, 'form_id' => 3, 'date_created' => $raw );
		$form        = array( 'id' => 3 );
		$timestamp   = 1900269060;
		$key         = spl_object_id( $note );
		$date_format = '\\P\\G\\R\\T\\I\\M\\E\\L\\I\\N\\E\\a\\b\\c\\X' . 'F j, Y';
		$context = array(
			'note' => $note,
			'note_snapshot' => get_object_vars( $note ),
			'notes' => $notes,
			'notes_order' => array( $key ),
			'entry' => $entry,
			'entry_key' => '3:12',
			'form' => $form,
			'raw' => $raw,
			'timestamp' => $timestamp,
			'time_format' => 'g:i a',
			'date_format' => $date_format,
			'event_kind' => 'stored',
			'print_inherited' => false,
			'calendar_admitted' => false,
		);
		$path = array(
			array( 'function' => 'date_i18n' ),
			array( 'class' => 'GFCommon', 'type' => '::', 'function' => 'format_date', 'args' => array( $raw, false, $date_format, true ) ),
			array( 'class' => 'Gravity_Flow_Common', 'type' => '::', 'function' => 'format_date', 'args' => array( $raw, '', false, true ) ),
			array( 'class' => 'Gravity_Flow_Entry_Detail', 'type' => '::', 'function' => 'get_note_header', 'args' => array( 'Runtime Admin', $raw ) ),
			array( 'class' => 'Gravity_Flow_Entry_Detail', 'type' => '::', 'function' => 'get_note_body', 'args' => array( $note, 'Runtime Admin' ) ),
			array( 'class' => 'Gravity_Flow_Entry_Detail', 'type' => '::', 'function' => 'notes_grid', 'args' => array( $notes ) ),
			array( 'class' => 'Gravity_Flow_Entry_Detail', 'type' => '::', 'function' => 'timeline', 'args' => array( $entry, $form ) ),
		);

		$contexts->setValue( $adapter, array( $key => $context ) );
		$this->assertSame( '12:01 ق.ظ', $shape->invoke( $adapter, '12:01 ق.ظ', 'g:i a', $timestamp, true, $path ) );
		$this->assertSame( array(), $contexts->getValue( $adapter ) );

		$context['calendar_admitted'] = true;
		$contexts->setValue( $adapter, array( $key => $context ) );
		$this->assertSame( '۱۲:۰۱ ق.ظ', $shape->invoke( $adapter, '12:01 ق.ظ', 'g:i a', $timestamp, true, $path ) );
	}

	public function test_nearest_chain_cannot_borrow_outer_reentrant_or_noncontiguous_frames(): void {
		$reflection = new ReflectionClass( PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter::class );
		$method     = $reflection->getMethod( 'nearest_contiguous_chain' );
		$expected   = array( 'date_i18n', 'GFCommon::format_date', 'Gravity_Flow_Common::format_date' );
		$adapter    = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();

		$nested = array(
			array( 'function' => 'date_i18n' ),
			array( 'class' => 'Other', 'type' => '::', 'function' => 'format_date' ),
			array( 'function' => 'date_i18n' ),
			array( 'class' => 'GFCommon', 'type' => '::', 'function' => 'format_date' ),
			array( 'class' => 'Gravity_Flow_Common', 'type' => '::', 'function' => 'format_date' ),
		);
		$this->assertNull( $method->invoke( $adapter, $nested, $expected ) );
		$this->assertSame( array_slice( $nested, 2 ), $method->invoke( $adapter, array_slice( $nested, 2 ), $expected ) );
	}

	public function test_note_identity_order_distinguishes_duplicate_timestamps_and_reordering(): void {
		$reflection = new ReflectionClass( PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter::class );
		$method     = $reflection->getMethod( 'note_identity_order' );
		$adapter    = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();
		$first      = (object) array( 'id' => 10, 'date_created' => '2030-03-20 20:31:00' );
		$second     = (object) array( 'id' => 11, 'date_created' => '2030-03-20 20:31:00' );
		$forward    = $method->invoke( $adapter, array( $first, $second ) );
		$reverse    = $method->invoke( $adapter, array( $second, $first ) );

		$this->assertCount( 2, $forward );
		$this->assertNotSame( $forward[0], $forward[1] );
		$this->assertNotSame( $forward, $reverse );
	}

	public function test_print_inheritance_is_proven_only_by_same_live_timeline_stack(): void {
		$adapter    = new PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter();
		$reflection = new ReflectionClass( $adapter );
		$method     = $reflection->getMethod( 'is_print_inheritance_trace' );
		$timeline   = array( 'class' => 'Gravity_Flow_Entry_Detail', 'type' => '::', 'function' => 'timeline' );
		$print      = array( 'class' => 'Gravity_Flow_Print_Entries', 'type' => '::', 'function' => 'render' );

		$this->assertTrue( $method->invoke( $adapter, array( $timeline, array( 'function' => 'apply_filters' ), $print ) ) );
		$this->assertFalse( $method->invoke( $adapter, array( $timeline ) ) );
		$this->assertFalse( $method->invoke( $adapter, array( $print ) ) );
		$this->assertFalse( $method->invoke( $adapter, array( $print, $timeline ) ) );
	}

	public function test_owned_marker_is_unique_one_shot_material_and_stripped_from_fallback(): void {
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

	public function test_runtime_source_removes_version_and_source_fingerprint_activation_oracles_but_ci_keeps_them(): void {
		$root      = dirname( __DIR__ );
		$source    = (string) file_get_contents( $root . '/includes/class-pgr-gravity-flow-timeline-jalali-presentation-adapter.php' );
		$setup     = (string) file_get_contents( $root . '/tests/real-integration/g008-timeline-production-setup.php' );
		$reconcile = (string) file_get_contents( $root . '/tests/real-integration/g008-timeline-print-reconcile.mjs' );

		$this->assertStringContainsString( "HOST_PLUGIN_BASENAME = 'gravityflow/gravityflow.php'", $source );
		$this->assertStringContainsString( 'private const FIRST_CHAIN', $source );
		$this->assertStringContainsString( 'private const TIME_FORMAT_CHAIN', $source );
		$this->assertStringContainsString( 'private const SECOND_CHAIN', $source );
		$this->assertStringContainsString( 'PGR_Jalali_Presentation::format_date', $source );
		$this->assertStringContainsString( 'is_print_inheritance_trace', $source );
		$this->assertStringContainsString( 'PGR_Gravity_Flow_Compatibility_Diagnostics::record', $source );

		$this->assertStringNotContainsString( 'SOURCE_FINGERPRINTS', $source );
		$this->assertStringNotContainsString( "hash_file( 'sha256'", $source );
		$this->assertStringNotContainsString( "PGR_PATH . 'includes/localization/products.php'", $source );
		$this->assertStringNotContainsString( "['target_version']", $source );
		$this->assertStringNotContainsString( "private const FLOW_VERSION = '3.1.0'", $source );
		$this->assertStringNotContainsString( 'private const GF_VERSION', $source );

		$this->assertStringContainsString( "'source_fingerprints'", $setup );
		$this->assertStringContainsString( "hash_file( 'sha256'", $setup );
		$this->assertStringContainsString( 'expectedFingerprints', $reconcile );
		$this->assertStringContainsString( 'source fingerprint mismatch', $reconcile );
	}

	public function test_production_source_contains_no_rejected_mutation_or_broad_rewrite(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-timeline-jalali-presentation-adapter.php' );
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
