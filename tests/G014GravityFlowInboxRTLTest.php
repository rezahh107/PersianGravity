<?php
/**
 * G-014: contract tests for opt-in real Gravity Flow Inbox RTL initialization.
 *
 * @package PersianGravityForms
 */

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\TestCase;

/**
 * Unit double for the public native Task producer seam; actual vendor source
 * is separately exercised in the WU008 licensed integration job.
 */
final class PGR_G014_Task_Double {
	public function get_unique_grid_id_from_args( $args ) {
		return $GLOBALS['pgr_g014_produced_ids'][ $args['fixture'] ?? '' ] ?? '';
	}
}

final class PGR_G014_Container_Double {
	public function get( $key ) {
		return new PGR_G014_Task_Double();
	}
}

final class PGR_G014_Flow_Double {
	public static function get_instance() {
		return new self();
	}

	public function container() {
		return new PGR_G014_Container_Double();
	}
}

final class PGR_G014_Provider_Double {
	public const TASK_MODEL = 'inbox.task';
}

final class G014GravityFlowInboxRTLTest extends TestCase {
	/**
	 * @return void
	 */
	private function load_integration() {
		require_once dirname( __DIR__ ) . '/admin/class-pgr-admin.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-gravity-flow-inbox-rtl.php';
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_off_and_malformed_settings_register_no_hook_and_do_not_touch_config() {
		$this->load_integration();
		$config = array(
			'grids' => array(
				'inbox_default' => array( 'grid_options' => array( 'rowData' => array( 1 ) ) ),
			),
		);
		foreach ( array( null, false, array(), array( 'gravity_flow_inbox_rtl' => 0 ),
			array( 'gravity_flow_inbox_rtl' => '1' ),
			array( 'gravity_flow_inbox_rtl' => true ) ) as $value ) {
			$GLOBALS['pgr_test_options']['pgr_settings'] = $value;
			$this->assertFalse( PGR_Gravity_Flow_Inbox_RTL::is_enabled() );
			$adapter = new PGR_Gravity_Flow_Inbox_RTL();
			$adapter->hooks();
			$this->assertSame( $config, $adapter->inbox_grid_config( $config ) );
		}
		$this->assertArrayNotHasKey( 'gravityflow_js_config_shared', $GLOBALS['pgr_test_filters'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_unadmitted_vendor_and_wrong_basename_do_not_register() {
		$this->load_integration();
		$GLOBALS['pgr_test_options']['pgr_settings'] = array( 'gravity_flow_inbox_rtl' => 1 );
		define( 'GRAVITY_FLOW_VERSION', '9.9.9' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'other/gravityflow.php' );
		$adapter = new PGR_Gravity_Flow_Inbox_RTL();
		$adapter->hooks();
		$this->assertArrayNotHasKey( 'gravityflow_js_config_shared', $GLOBALS['pgr_test_filters'] );
		$this->assertSame( array( 'grids' => array() ), $adapter->inbox_grid_config( array( 'grids' => array() ) ) );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_admitted_flow_uses_native_producer_and_preserves_other_grids() {
		$this->load_integration();
		define( 'GRAVITY_FLOW_VERSION', '3.1.0' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow/gravityflow.php' );
		class_alias( PGR_G014_Flow_Double::class, 'Gravity_Flow' );
		class_alias( PGR_G014_Provider_Double::class, 'Gravity_Flow\\Gravity_Flow\\Inbox\\Inbox_Service_Provider' );

		$GLOBALS['pgr_test_options']['pgr_settings'] = array( 'gravity_flow_inbox_rtl' => 1 );
		$GLOBALS['pgr_g014_produced_ids'] = array(
			'frontend-a' => 'user_id_1_inbox_shortcode_15_4de0e646',
			'frontend-b' => 'user_id_1_inbox_shortcode_15_4ef4e6b3',
			'admin' => 'user_id_1_inbox_default_admin_1a2b3c4d',
			'block' => 'user_id_1_inbox_block_15_1234abcd',
		);
		$grid = static function ( $fixture, $extra = array() ) {
			return array(
				'current_user_token' => '',
				'grid_options' => array_merge(
					array(
						'columnDefs' => array( array( 'field' => 'id' ) ),
						'rowData' => array(),
						'pagination' => true,
						'paginationPageSize' => 20,
						'searchArgs' => array( 'fixture' => $fixture ),
					),
					$extra
				),
			);
		};

		$adapter = new PGR_Gravity_Flow_Inbox_RTL();
		$adapter->hooks();
		$this->assertCount( 1, $GLOBALS['pgr_test_filters']['gravityflow_js_config_shared'][11] );

		$original = array(
			'other_settings' => array( 'untouched' => true ),
			'grids' => array(
				'user_id_1_inbox_shortcode_15_4de0e646' => $grid( 'frontend-a' ),
				'user_id_1_inbox_shortcode_15_4ef4e6b3' => $grid( 'frontend-b', array( 'enableRtl' => false ) ),
				'user_id_1_inbox_default_admin_1a2b3c4d' => $grid( 'admin' ),
				'user_id_1_inbox_block_15_1234abcd' => $grid( 'block', array( 'enableRtl' => true ) ),
				'user_id_1_inbox_shortcode_16_4de0e646' => $grid( 'frontend-a' ),
				'status_grids' => $grid( 'frontend-a' ),
				'inbox_default' => $grid( 'frontend-a' ),
				'user_id_1_inbox_shortcode_15_4de0e647' => $grid( 'frontend-a' ),
				'user_id_1_inbox_shortcode_15_4de0e646_missing_structure' => array( 'grid_options' => array( 'searchArgs' => array( 'fixture' => 'frontend-a' ) ) ),
			),
		);
		$GLOBALS['pgr_test_is_rtl'] = true;
		$actual = $adapter->inbox_grid_config( $original );
		$this->assertTrue( $actual['grids']['user_id_1_inbox_shortcode_15_4de0e646']['grid_options']['enableRtl'] );
		$this->assertTrue( $actual['grids']['user_id_1_inbox_default_admin_1a2b3c4d']['grid_options']['enableRtl'] );
		$this->assertFalse( $actual['grids']['user_id_1_inbox_shortcode_15_4ef4e6b3']['grid_options']['enableRtl'] );
		$this->assertTrue( $actual['grids']['user_id_1_inbox_block_15_1234abcd']['grid_options']['enableRtl'] );
		foreach ( array(
			'user_id_1_inbox_shortcode_16_4de0e646', 'status_grids', 'inbox_default',
			'user_id_1_inbox_shortcode_15_4de0e647',
			'user_id_1_inbox_shortcode_15_4de0e646_missing_structure',
		) as $key ) {
			$this->assertSame( $original['grids'][ $key ], $actual['grids'][ $key ] );
		}
		$this->assertSame( $actual, $adapter->inbox_grid_config( $actual ) );
		$this->assertSame( $original['other_settings'], $actual['other_settings'] );

		$GLOBALS['pgr_test_is_rtl'] = false;
		$english = $adapter->inbox_grid_config( $original );
		$this->assertFalse( $english['grids']['user_id_1_inbox_shortcode_15_4de0e646']['grid_options']['enableRtl'] );
		$this->assertFalse( $english['grids']['user_id_1_inbox_default_admin_1a2b3c4d']['grid_options']['enableRtl'] );
		$this->assertSame( $original, $original );
	}
x_previous']['grid_options']['enableRtl'] );
		foreach ( array( 'inbox_malformed', 'other_grid', 'inboxDefault', 'inbox_extra' ) as $key ) {
			$this->assertSame( $original['grids'][ $key ], $actual['grids'][ $key ] );
		}
		$this->assertSame( $original['other_settings'], $actual['other_settings'] );
		$this->assertSame( $original, $original );

		$GLOBALS['pgr_test_is_rtl'] = false;
		$english = $adapter->inbox_grid_config( $original );
		$this->assertFalse( $english['grids']['inbox_default']['grid_options']['enableRtl'] );
		$this->assertSame( $original['grids']['inbox_previous'], $english['grids']['inbox_previous'] );
		$this->assertSame( $actual, $adapter->inbox_grid_config( $actual ) );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_malformed_shared_config_does_not_create_or_mutate_vendor_state() {
		$this->load_integration();
		define( 'GRAVITY_FLOW_VERSION', '3.1.0' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow/gravityflow.php' );
		$GLOBALS['pgr_test_options']['pgr_settings'] = array( 'gravity_flow_inbox_rtl' => 1 );
		$adapter = new PGR_Gravity_Flow_Inbox_RTL();
		foreach ( array( null, false, 'grids', array(), array( 'grids' => null ), array( 'grids' => 'bad' ) ) as $bad ) {
			$this->assertSame( $bad, $adapter->inbox_grid_config( $bad ) );
		}
	}
}
