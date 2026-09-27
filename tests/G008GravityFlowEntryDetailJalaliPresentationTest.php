<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'PGR_PATH' ) ) {
	define( 'PGR_PATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'GRAVITY_FLOW_VERSION' ) ) {
	define( 'GRAVITY_FLOW_VERSION', '3.1.0' );
}
if ( ! defined( 'GRAVITY_FLOW_PLUGIN_BASENAME' ) ) {
	define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow/gravityflow.php' );
}
if ( ! class_exists( 'GFCommon' ) ) {
	final class GFCommon {
		public static function get_default_date_format() {
			return $GLOBALS['pgr_test_default_date_format'] ?? 'F j, Y';
		}
	}
}

require_once dirname( __DIR__ ) . '/includes/class-pgr-gregorian-jalali-converter.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-jalali-presentation.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-compatibility-diagnostics.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-entry-detail-jalali-presentation-adapter.php';

final class G008GravityFlowEntryDetailJalaliPresentationTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pgr_test_filters']             = array();
		$GLOBALS['pgr_test_actions']             = array();
		$GLOBALS['pgr_test_default_date_format'] = 'F j, Y';
		$this->reset_diagnostics();
	}

	public function test_registers_only_the_two_presentation_hooks_plus_diagnostics_post_render_observer(): void {
		$adapter = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();
		$adapter->hooks();

		$this->assertArrayHasKey( 'gravityflow_date_format_entry_detail', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayHasKey( 'date_i18n', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayHasKey( 'gravityflow_below_workflow_info_entry_detail', $GLOBALS['pgr_test_actions'] );
		$this->assertArrayNotHasKey( 'gravityflow_step_due_date_timestamp', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_step_schedule_timestamp', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_step_expiration_timestamp', $GLOBALS['pgr_test_filters'] );
		$this->assertArrayNotHasKey( 'gravityflow_timeline_notes', $GLOBALS['pgr_test_filters'] );
	}

	public function test_empty_entry_detail_format_receives_unique_escaped_marker_and_native_date_format(): void {
		$adapter = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();
		$format  = $adapter->filter_entry_detail_date_format( '' );

		$this->assertSame( '\\P\\G\\R\\J\\A\\L\\A\\L\\I\\E\\N\\T\\R\\Y\\D\\E\\T\\A\\I\\L\\:F j, Y', $format );
	}

	public function test_nonempty_host_format_override_is_preserved_and_disarms_ownership_without_marker_leak(): void {
		$adapter = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();
		$marked  = $adapter->filter_entry_detail_date_format( '' );

		$this->assertSame( 'Y-m-d', $adapter->filter_entry_detail_date_format( 'Y-m-d' ) );
		$this->assertSame(
			'March 21, 2030',
			$adapter->filter_marked_date(
				'PGRJALALIENTRYDETAIL:March 21, 2030',
				$marked,
				1900281780,
				true
			)
		);
	}

	public function test_wrong_owned_marked_format_falls_native_without_marker_leak(): void {
		$adapter = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();
		$adapter->filter_entry_detail_date_format( '' );

		$this->assertSame(
			'March 21, 2030',
			$adapter->filter_marked_date(
				'PGRJALALIENTRYDETAIL:March 21, 2030',
				'\\P\\G\\R\\J\\A\\L\\A\\L\\I\\E\\N\\T\\R\\Y\\D\\E\\T\\A\\I\\L\\:Y-m-d',
				1900281780,
				true
			)
		);
	}

	public function test_missing_native_default_format_fails_closed_without_fabricating_per_field_diagnostics(): void {
		$GLOBALS['pgr_test_default_date_format'] = '';
		$adapter                                = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();

		$this->assertSame( '', $adapter->filter_entry_detail_date_format( '' ) );
		$this->assertSame( array(), PGR_Gravity_Flow_Compatibility_Diagnostics::request_snapshot() );
	}

	public function test_marked_local_timestamp_with_offset_uses_local_civil_date_without_second_timezone_conversion(): void {
		$adapter   = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();
		$format    = $adapter->filter_entry_detail_date_format( '' );
		$timestamp = gmmktime( 0, 3, 0, 3, 21, 2030 );

		$this->assertSame(
			'۱۴۰۹/۰۱/۰۱',
			$adapter->filter_marked_date(
				'PGRJALALIENTRYDETAIL:March 21, 2030',
				$format,
				$timestamp,
				true
			)
		);
	}

	public function test_complete_four_call_render_records_truthful_available_state_for_all_four_date_capabilities(): void {
		$adapter = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();
		$format  = $adapter->filter_entry_detail_date_format( '' );
		$dates   = array(
			array( 'March 20, 2030', gmmktime( 23, 59, 0, 3, 20, 2030 ) ),
			array( 'March 21, 2030', gmmktime( 0, 1, 0, 3, 21, 2030 ) ),
			array( 'March 21, 2030', gmmktime( 0, 3, 0, 3, 21, 2030 ) ),
			array( 'March 21, 2030', gmmktime( 23, 59, 0, 3, 21, 2030 ) ),
		);

		foreach ( $dates as $date ) {
			$result = $adapter->filter_marked_date(
				'PGRJALALIENTRYDETAIL:' . $date[0],
				$format,
				$date[1],
				true
			);
			$this->assertStringNotContainsString( 'PGRJALALIENTRYDETAIL:', $result );
		}

		$adapter->finalize_date_family_diagnostics( array(), array(), null );
		$this->assert_entry_detail_observations( 'AVAILABLE', 'PGR-GFLOW-CONTRACT-SATISFIED' );

		$this->assertSame(
			'March 21, 2030',
			$adapter->filter_marked_date(
				'PGRJALALIENTRYDETAIL:March 21, 2030',
				$format,
				gmmktime( 0, 3, 0, 3, 21, 2030 ),
				true
			)
		);
	}

	public function test_complete_uniform_source_failure_uses_shared_source_invalid_diagnostic(): void {
		$adapter = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();
		$format  = $adapter->filter_entry_detail_date_format( '' );

		for ( $index = 0; $index < 4; ++$index ) {
			$this->assertSame(
				'March 21, 2030',
				$adapter->filter_marked_date(
					'PGRJALALIENTRYDETAIL:March 21, 2030',
					$format,
					'not-an-integer',
					true
				)
			);
		}

		$adapter->finalize_date_family_diagnostics( array(), array(), null );
		$this->assert_entry_detail_observations( 'DEGRADED', 'PGR-GFLOW-SOURCE-INVALID' );
	}

	public function test_mixed_four_call_results_do_not_fabricate_per_field_attribution(): void {
		$adapter = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();
		$format  = $adapter->filter_entry_detail_date_format( '' );

		$adapter->filter_marked_date( 'PGRJALALIENTRYDETAIL:March 20, 2030', $format, gmmktime( 23, 59, 0, 3, 20, 2030 ), true );
		$adapter->filter_marked_date( 'PGRJALALIENTRYDETAIL:March 21, 2030', $format, 'bad', true );
		$adapter->filter_marked_date( 'PGRJALALIENTRYDETAIL:March 21, 2030', $format, gmmktime( 0, 3, 0, 3, 21, 2030 ), true );
		$adapter->filter_marked_date( 'PGRJALALIENTRYDETAIL:March 21, 2030', $format, gmmktime( 23, 59, 0, 3, 21, 2030 ), true );
		$adapter->finalize_date_family_diagnostics( array(), array(), null );

		$this->assertSame( array(), PGR_Gravity_Flow_Compatibility_Diagnostics::request_snapshot() );
	}

	public function test_partial_render_does_not_fabricate_missing_date_capabilities(): void {
		$adapter = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();
		$format  = $adapter->filter_entry_detail_date_format( '' );

		$adapter->filter_marked_date( 'PGRJALALIENTRYDETAIL:March 20, 2030', $format, gmmktime( 23, 59, 0, 3, 20, 2030 ), true );
		$adapter->filter_marked_date( 'PGRJALALIENTRYDETAIL:March 21, 2030', $format, gmmktime( 0, 1, 0, 3, 21, 2030 ), true );
		$adapter->finalize_date_family_diagnostics( array(), array(), null );

		$this->assertSame( array(), PGR_Gravity_Flow_Compatibility_Diagnostics::request_snapshot() );
	}

	public function test_unrelated_date_i18n_format_is_byte_for_byte_native(): void {
		$adapter = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();

		$this->assertSame(
			'2030-03-21 00:03',
			$adapter->filter_marked_date( '2030-03-21 00:03', 'Y-m-d H:i', 1900262580, true )
		);
	}

	public function test_unrelated_date_i18n_literal_collision_is_untouched_when_format_is_not_owned(): void {
		$adapter = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();
		$native  = 'User text PGRJALALIENTRYDETAIL: must remain byte-for-byte native';

		$this->assertSame(
			$native,
			$adapter->filter_marked_date( $native, 'Y-m-d H:i', 1900262580, true )
		);
	}

	public function test_unexpected_date_i18n_gmt_semantics_fail_closed_without_marker_leak(): void {
		$adapter = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();
		$format  = $adapter->filter_entry_detail_date_format( '' );

		$this->assertSame(
			'March 21, 2030',
			$adapter->filter_marked_date(
				'PGRJALALIENTRYDETAIL:March 21, 2030',
				$format,
				1900281780,
				false
			)
		);
	}

	public function test_out_of_range_marked_date_falls_back_without_marker_leak(): void {
		$adapter   = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();
		$format    = $adapter->filter_entry_detail_date_format( '' );
		$timestamp = gmmktime( 8, 30, 0, 3, 20, 2124 );

		$this->assertSame(
			'March 20, 2124',
			$adapter->filter_marked_date(
				'PGRJALALIENTRYDETAIL:March 20, 2124',
				$format,
				$timestamp,
				true
			)
		);
	}

	public function test_malformed_timestamp_falls_back_without_marker_leak(): void {
		$adapter = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();
		$format  = $adapter->filter_entry_detail_date_format( '' );

		$this->assertSame(
			'March 21, 2030',
			$adapter->filter_marked_date(
				'PGRJALALIENTRYDETAIL:March 21, 2030',
				$format,
				'not-a-timestamp',
				true
			)
		);
	}

	public function test_marker_is_removed_even_when_owned_native_output_shape_is_unexpected(): void {
		$adapter = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();
		$format  = $adapter->filter_entry_detail_date_format( '' );

		$this->assertSame(
			'native March 21, 2030',
			$adapter->filter_marked_date(
				'native PGRJALALIENTRYDETAIL:March 21, 2030',
				$format,
				'not-a-timestamp',
				true
			)
		);
	}

	public function test_runtime_gate_reuses_repository_version_authority_and_never_touches_operational_getters(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-entry-detail-jalali-presentation-adapter.php' );

		$this->assertStringContainsString( "HOST_VERSION_CONSTANT = 'GRAVITY_FLOW_VERSION'", $source );
		$this->assertStringContainsString( "HOST_BASENAME_CONSTANT = 'GRAVITY_FLOW_PLUGIN_BASENAME'", $source );
		$this->assertStringContainsString( "HOST_PLUGIN_BASENAME = 'gravityflow/gravityflow.php'", $source );
		$this->assertStringContainsString( "'gravityflow_date_format_entry_detail'", $source );
		$this->assertStringContainsString( "'date_i18n'", $source );
		$this->assertStringContainsString( "'gravityflow_below_workflow_info_entry_detail'", $source );
		$this->assertStringContainsString( 'true !== $gmt', $source );
		$this->assertStringContainsString( "PGR_PATH . 'includes/localization/products.php'", $source );
		$this->assertStringContainsString( "['target_version']", $source );
		$this->assertStringContainsString( 'is_exact_qualified_date_family_host()', $source );
		$this->assertStringNotContainsString( 'get_due_date_timestamp()', $source );
		$this->assertStringNotContainsString( 'get_schedule_timestamp()', $source );
		$this->assertStringNotContainsString( 'get_expiration_timestamp()', $source );
		$this->assertStringNotContainsString( "'3.1.0'", $source );
		$this->assertStringNotContainsString( "add_filter( 'gravityflow_timeline_notes'", $source );
		$this->assertStringNotContainsString( '::status_snapshot(', $source );
		$this->assertStringNotContainsString( 'pgr_gravityflow_compatibility_latest', $source );
	}

	private function assert_entry_detail_observations( $state, $reason ) {
		$snapshot = PGR_Gravity_Flow_Compatibility_Diagnostics::request_snapshot();
		foreach (
			array(
				'gravityflow.entry-detail.submitted',
				'gravityflow.entry-detail.last-updated',
				'gravityflow.entry-detail.due-date',
				'gravityflow.entry-detail.expiration',
			) as $capability_id
		) {
			$this->assertArrayHasKey( $capability_id, $snapshot );
			$this->assertSame( $state, $snapshot[ $capability_id ]['state'], $capability_id );
			$this->assertSame( $reason, $snapshot[ $capability_id ]['reason_id'], $capability_id );
		}
	}

	private function reset_diagnostics(): void {
		$observations = new ReflectionProperty( PGR_Gravity_Flow_Compatibility_Diagnostics::class, 'request_observations' );
		$observations->setAccessible( true );
		$observations->setValue( null, array() );

		$shutdown = new ReflectionProperty( PGR_Gravity_Flow_Compatibility_Diagnostics::class, 'shutdown_registered' );
		$shutdown->setAccessible( true );
		$shutdown->setValue( null, false );
	}
}
