<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'PGR_PATH' ) ) {
	define( 'PGR_PATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'PGR_URL' ) ) {
	define( 'PGR_URL', 'https://example.test/wp-content/plugins/persian-gravityforms/' );
}
if ( ! defined( 'PGR_VERSION' ) ) {
	define( 'PGR_VERSION', '4.8.0' );
}
if ( ! defined( 'GRAVITY_FLOW_VERSION' ) ) {
	define( 'GRAVITY_FLOW_VERSION', '3.1.0' );
}
if ( ! defined( 'GRAVITY_FLOW_PLUGIN_BASENAME' ) ) {
	define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow/gravityflow.php' );
}
if ( ! class_exists( 'GFCommon', false ) ) {
	final class GFCommon {
		public static function get_default_date_format() {
			return 'F j, Y';
		}
	}
}

require_once dirname( __DIR__ ) . '/includes/class-pgr-gregorian-jalali-converter.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-jalali-presentation.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-entry-detail-jalali-presentation-adapter.php';
require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-entry-detail-persian-digits-presentation-adapter.php';

final class G010GravityFlowEntryDetailContractIsolationTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pgr_test_locale']   = 'en_US';
		$GLOBALS['pgr_test_enqueued'] = array();
	}

	public function test_date_conversion_failure_does_not_disable_qualified_digit_enqueue(): void {
		$GLOBALS['pgr_test_locale'] = 'fa_IR';

		$date_adapter  = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();
		$digit_adapter = new PGR_Gravity_Flow_Entry_Detail_Persian_Digits_Presentation_Adapter();
		$format        = $date_adapter->filter_entry_detail_date_format( '' );
		$out_of_range  = gmmktime( 8, 30, 0, 3, 20, 2124 );

		$this->assertSame(
			'March 20, 2124',
			$date_adapter->filter_marked_date(
				'PGRJALALIENTRYDETAIL:March 20, 2124',
				$format,
				$out_of_range,
				true
			)
		);

		$digit_adapter->enqueue_digit_shaper( array(), array(), null );
		$this->assertSame(
			array( 'pgr-gravity-flow-entry-detail-persian-digits' ),
			$GLOBALS['pgr_test_enqueued']
		);
	}

	public function test_digit_locale_failure_does_not_disable_qualified_date_conversion(): void {
		$GLOBALS['pgr_test_locale'] = 'en_US';

		$date_adapter  = new PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter();
		$digit_adapter = new PGR_Gravity_Flow_Entry_Detail_Persian_Digits_Presentation_Adapter();
		$format        = $date_adapter->filter_entry_detail_date_format( '' );

		$digit_adapter->enqueue_digit_shaper( array(), array(), null );
		$this->assertSame( array(), $GLOBALS['pgr_test_enqueued'] );

		$this->assertSame(
			'۱۴۰۹/۰۱/۰۱',
			$date_adapter->filter_marked_date(
				'PGRJALALIENTRYDETAIL:March 21, 2030',
				$format,
				gmmktime( 0, 3, 0, 3, 21, 2030 ),
				true
			)
		);
	}
}
