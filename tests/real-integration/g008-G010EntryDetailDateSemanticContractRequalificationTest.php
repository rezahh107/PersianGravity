<?php

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

if ( ! class_exists( 'GFCommon', false ) ) {
	final class GFCommon {
		public static function get_default_date_format() {
			return 'F j, Y';
		}
	}
}

final class G010EntryDetailDateSemanticContractRequalificationTest extends TestCase {

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_fifth_shared_date_call_is_converted_before_post_render_count_can_reject_it(): void {
		$root = dirname( __DIR__, 2 );

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', $root . '/' );
		}
		define( 'PGR_PATH', $root . '/' );
		define( 'GRAVITY_FLOW_VERSION', '3.1.0' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow/gravityflow.php' );

		require_once $root . '/includes/class-pgr-gregorian-jalali-converter.php';
		require_once $root . '/includes/class-pgr-jalali-presentation.php';
		require_once $root . '/includes/class-pgr-gravity-flow-compatibility-diagnostics.php';
		require_once $root . '/includes/class-pgr-gravity-flow-entry-detail-jalali-presentation-adapter.php';

		$adapter = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();
		$format  = $adapter->filter_entry_detail_date_format( '' );

		$this->assertNotSame( '', $format, 'The exact-qualified baseline must arm the existing family marker.' );

		$converted = array();
		for ( $index = 0; $index < 5; ++$index ) {
			$converted[] = $adapter->filter_marked_date(
				'PGRJALALIENTRYDETAIL:March 21, 2030',
				$format,
				gmmktime( 0, 3 + $index, 0, 3, 21, 2030 ),
				true
			);
		}

		$this->assertSame(
			array_fill( 0, 5, '۱۴۰۹/۰۱/۰۱' ),
			$converted,
			'Call-count reconciliation cannot be a pre-conversion semantic safety contract: a fifth marked call has already converted.'
		);

		$adapter->finalize_date_family_diagnostics( array(), array(), null );
		$this->assertSame(
			array(),
			PGR_Gravity_Flow_Compatibility_Diagnostics::request_snapshot(),
			'The post-render observer notices only that the family is not exactly four calls; it cannot undo the fifth conversion.'
		);
	}

	public function test_current_production_guard_still_precedes_marker_ownership_and_avoids_operational_or_diagnostic_authority(): void {
		$root   = dirname( __DIR__, 2 );
		$source = (string) file_get_contents( $root . '/includes/class-pgr-gravity-flow-entry-detail-jalali-presentation-adapter.php' );

		$method_start = strpos( $source, 'public function filter_entry_detail_date_format' );
		$method_end   = strpos( $source, 'public function filter_marked_date', $method_start );
		$this->assertNotFalse( $method_start );
		$this->assertNotFalse( $method_end );

		$method = substr( $source, $method_start, $method_end - $method_start );
		$guard  = strpos( $method, '! $this->is_exact_qualified_date_family_host()' );
		$arm    = strpos( $method, '$this->active_marker_format = $this->marked_format( $native_format );' );

		$this->assertNotFalse( $guard );
		$this->assertNotFalse( $arm );
		$this->assertLessThan( $arm, $guard, 'Exact repository-qualified host admission must run before marker ownership is armed.' );

		$this->assertStringContainsString( "['target_version']", $source );
		$this->assertStringNotContainsString( "add_filter( 'gravityflow_step_due_date_timestamp'", $source );
		$this->assertStringNotContainsString( "add_filter( 'gravityflow_step_expiration_timestamp'", $source );
		$this->assertStringNotContainsString( '::status_snapshot(', $source );
		$this->assertStringNotContainsString( 'pgr_gravityflow_compatibility_latest', $source );
	}
}
