<?php
/**
 * G-014: contract tests for opt-in real Gravity Flow Inbox RTL initialization.
 *
 * @package PersianGravityForms
 */

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\TestCase;

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
	public function test_admitted_flow_binds_once_and_sets_only_missing_authentic_inbox_direction() {
		$this->load_integration();
		define( 'GRAVITY_FLOW_VERSION', '3.1.0' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow/gravityflow.php' );
		$GLOBALS['pgr_test_options']['pgr_settings'] = array( 'default_force_english' => 1, 'gravity_flow_inbox_rtl' => 1 );
		$adapter = new PGR_Gravity_Flow_Inbox_RTL();
		$adapter->hooks();
		$this->assertCount( 1, $GLOBALS['pgr_test_filters']['gravityflow_js_config_shared'][10] );

		$original = array(
			'other_settings' => array( 'untouched' => true ),
			'grids' => array(
				'inbox_default' => array( 'grid_options' => array( 'rowData' => array( 1 ) ) ),
				'inbox_assigned' => array( 'grid_options' => array( 'enableRtl' => false, 'rowData' => array() ) ),
				'inbox_previous' => array( 'grid_options' => array( 'enableRtl' => true ) ),
				'inbox_malformed' => array( 'grid_options' => 'invalid' ),
				'other_grid' => array( 'grid_options' => array() ),
				'inboxDefault' => array( 'grid_options' => array() ),
				'inbox_extra' => array( 'metadata' => true ),
			),
		);
		$GLOBALS['pgr_test_is_rtl'] = true;
		$actual = $adapter->inbox_grid_config( $original );
		$this->assertTrue( $actual['grids']['inbox_default']['grid_options']['enableRtl'] );
		$this->assertFalse( $actual['grids']['inbox_assigned']['grid_options']['enableRtl'] );
		$this->assertTrue( $actual['grids']['inbox_previous']['grid_options']['enableRtl'] );
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
