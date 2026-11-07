<?php
/** Read-only G-014 contract tests. The real vendor/browser is tested separately. */

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class G014RTLStatusContractTest extends TestCase {
	private function load_contract() {
		require_once dirname( __DIR__ ) . '/includes/pgr-gravity-flow-inbox-rtl-status.php';
		require_once dirname( __DIR__ ) . '/admin/class-pgr-admin.php';
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_default_off_without_host_and_no_side_effects() {
		$this->load_contract();
		$GLOBALS['pgr_test_fired_actions']['plugins_loaded'] = 1;
		$GLOBALS['pgr_test_fired_actions']['init'] = 1;
		$this->assertSame(
			array(
				'api_version' => 1,
				'capability' => 'supported',
				'requested' => 'off',
				'host' => 'absent',
				'request_rtl' => false,
				'grid_mode' => 'not_observed',
			),
			pgr_gravity_flow_inbox_rtl_status()
		);
		$this->assertArrayNotHasKey( 'pgr_settings', $GLOBALS['pgr_test_options'] );
		$this->assertArrayNotHasKey( 'gravityflow_js_config_shared', $GLOBALS['pgr_test_filters'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_unknown_lifecycle_invalid_settings_and_valid_on() {
		$this->load_contract();
		$this->assertSame( 'unknown', pgr_gravity_flow_inbox_rtl_status()['host'] );
		$this->assertNull( pgr_gravity_flow_inbox_rtl_status()['request_rtl'] );
		foreach ( array( false, true, '1', array( 'gravity_flow_inbox_rtl' => '1' ), array( 'gravity_flow_inbox_rtl' => true ) ) as $invalid ) {
			$GLOBALS['pgr_test_options']['pgr_settings'] = $invalid;
			$this->assertSame( 'unknown', pgr_gravity_flow_inbox_rtl_status()['requested'] );
			$this->assertSame( $invalid, $GLOBALS['pgr_test_options']['pgr_settings'] );
		}
		$GLOBALS['pgr_test_options']['pgr_settings'] = array( 'gravity_flow_inbox_rtl' => 0 );
		$this->assertSame( 'off', pgr_gravity_flow_inbox_rtl_status()['requested'] );
		$GLOBALS['pgr_test_options']['pgr_settings'] = array( 'gravity_flow_inbox_rtl' => 1 );
		$this->assertSame( 'on', pgr_gravity_flow_inbox_rtl_status()['requested'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_unsupported_version_and_partial_host_are_not_admitted() {
		$this->load_contract();
		define( 'GRAVITY_FLOW_VERSION', '3.2.0' );
		$GLOBALS['pgr_test_fired_actions']['plugins_loaded'] = 1;
		$this->assertSame( 'unknown', pgr_gravity_flow_inbox_rtl_status()['host'] );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow/gravityflow.php' );
		$this->assertSame( 'unsupported', pgr_gravity_flow_inbox_rtl_status()['host'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_admitted_host_and_mixed_site_user_locale_reads_request_not_site() {
		$this->load_contract();
		define( 'GRAVITY_FLOW_VERSION', '3.1.0' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow\gravityflow.php' );
		$GLOBALS['pgr_test_fired_actions']['init'] = 1;
		$GLOBALS['pgr_test_options']['pgr_settings'] = array( 'gravity_flow_inbox_rtl' => 1 );
		$GLOBALS['pgr_test_locale'] = 'en_US';
		$GLOBALS['pgr_test_is_rtl'] = true;
		$this->assertSame( 'admitted', pgr_gravity_flow_inbox_rtl_status()['host'] );
		$this->assertTrue( pgr_gravity_flow_inbox_rtl_status()['request_rtl'] );
		$GLOBALS['pgr_test_locale'] = 'fa_IR';
		$GLOBALS['pgr_test_is_rtl'] = false;
		$this->assertFalse( pgr_gravity_flow_inbox_rtl_status()['request_rtl'] );
		$this->assertSame( 'not_observed', pgr_gravity_flow_inbox_rtl_status()['grid_mode'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_warning_is_admin_only_and_contains_actual_explanation() {
		$this->load_contract();
		$GLOBALS['pgr_test_fired_actions']['init'] = 1;
		$GLOBALS['pgr_test_is_rtl'] = true;
		$admin = new PGR_Admin();
		$GLOBALS['pgr_test_can_manage_options'] = false;
		ob_start();
		$admin->render_gravity_flow_inbox_rtl();
		$this->assertSame( '', ob_get_clean() );
		$GLOBALS['pgr_test_can_manage_options'] = true;
		ob_start();
		$admin->render_gravity_flow_inbox_rtl();
		$html = ob_get_clean();
		foreach ( array( 'هشدار سازگاری RTL:', 'جزئیات فنی و روش بررسی', 'not_observed', 'حالت A', 'حالت B', 'فعلاً فعال نکن' ) as $expected ) {
			$this->assertStringContainsString( $expected, $html );
		}
		$this->assertStringNotContainsString( 'checked="checked"', $html );
		unset( $GLOBALS['pgr_test_can_manage_options'] );
	}
}
