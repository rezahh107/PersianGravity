<?php

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter() {}
}
if ( ! class_exists( 'GFCommon', false ) ) {
	final class GFCommon {
		public static function get_default_date_format() {
			return 'F j, Y';
		}
	}
}

final class G008GravityFlowEntryDetailFailClosedTest extends TestCase {

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_synthetic_version_only_drift_keeps_intact_composed_contract_eligible(): void {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
		define( 'PGR_PATH', dirname( __DIR__ ) . '/' );
		define( 'GRAVITY_FLOW_VERSION', '99.0.0-synthetic' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow/gravityflow.php' );

		require_once dirname( __DIR__ ) . '/includes/class-pgr-gregorian-jalali-converter.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-jalali-presentation.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-entry-detail-jalali-presentation-adapter.php';

		$adapter = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();
		$format  = $adapter->filter_entry_detail_date_format( '' );

		$this->assertSame( '\\P\\G\\R\\J\\A\\L\\A\\L\\I\\E\\N\\T\\R\\Y\\D\\E\\T\\A\\I\\L\\:F j, Y', $format );
		$this->assertSame(
			'۱۴۰۹/۰۱/۰۱',
			$adapter->filter_marked_date(
				'PGRJALALIENTRYDETAIL:March 21, 2030',
				$format,
				gmmktime( 0, 3, 0, 3, 21, 2030 ),
				true
			)
		);
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_gravity_flow_plugin_identity_drift_never_arms_marker_format(): void {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
		define( 'PGR_PATH', dirname( __DIR__ ) . '/' );
		define( 'GRAVITY_FLOW_VERSION', '3.1.0' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow-next/gravityflow.php' );

		require_once dirname( __DIR__ ) . '/includes/class-pgr-gregorian-jalali-converter.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-jalali-presentation.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-entry-detail-jalali-presentation-adapter.php';

		$adapter = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();
		$this->assertSame( '', $adapter->filter_entry_detail_date_format( '' ) );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_malformed_host_version_observation_never_arms_marker_format(): void {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
		define( 'PGR_PATH', dirname( __DIR__ ) . '/' );
		define( 'GRAVITY_FLOW_VERSION', 'not a version' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow/gravityflow.php' );

		require_once dirname( __DIR__ ) . '/includes/class-pgr-gregorian-jalali-converter.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-jalali-presentation.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-entry-detail-jalali-presentation-adapter.php';

		$adapter = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();
		$this->assertSame( '', $adapter->filter_entry_detail_date_format( '' ) );
	}
}
