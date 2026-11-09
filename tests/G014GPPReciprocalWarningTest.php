<?php
/**
 * G-014 reciprocal GPP warning facts: data/scope/permission tests.
 *
 * Every case uses separate PHP process so the optional GPP function can be
 * genuinely absent in negative tests.
 */

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\TestCase;

final class G014GPPReciprocalWarningTest extends TestCase {
	private function boot() {
		require_once dirname( __DIR__ ) . '/includes/pgr-gravity-flow-inbox-rtl-status.php';
		require_once dirname( __DIR__ ) . '/admin/class-pgr-admin.php';
	}

	private function admitted_host() {
		define( 'GRAVITY_FLOW_VERSION', '3.1.0' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow/gravityflow.php' );
		$GLOBALS['pgr_test_fired_actions']['plugins_loaded'] = 1;
		$GLOBALS['pgr_test_fired_actions']['init'] = 1;
		$GLOBALS['pgr_test_site_locale'] = 'fa_IR';
		$GLOBALS['pgr_test_locale'] = 'fa_IR';
		$GLOBALS['pgr_test_is_rtl'] = true;
	}

	private function fake_gpp() {
		if ( ! function_exists( 'gpp_gravity_flow_inbox_axis_status' ) ) {
			function gpp_gravity_flow_inbox_axis_status() {
				if ( ! empty( $GLOBALS['pgr_test_gpp_throws'] ) ) {
					throw new \RuntimeException( 'Simulated unavailable optional GPP interface' );
				}
				return $GLOBALS['pgr_test_gpp_response'] ?? null;
			}
		}
	}

	private function gpp( $mode ) {
		$GLOBALS['pgr_test_gpp_response'] = array(
			'api_version' => 1,
			'capability' => 'supported',
			'requested' => $mode,
			'effective' => $mode,
			'scope' => 'gravity_flow.inbox_1',
			'grid_mode' => 'not_observed',
		);
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_absent_plugin_is_unknown_and_no_other_option_access() {
		$this->boot();
		$this->admitted_host();
		$this->assertFalse( function_exists( 'gpp_gravity_flow_inbox_axis_status' ) );
		$before = $GLOBALS['pgr_test_options'];
		$result = PGR_GPP_Inbox_RTL_Compatibility::facts();
		$this->assertSame( 'absent', $result['gpp']['interface'] );
		$this->assertSame( 'unknown', $result['compatibility'] );
		$this->assertSame( 'off', $result['pgr']['requested'] );
		$this->assertSame( 'not_observed', $result['grid_mode'] );
		$this->assertSame( $before, $GLOBALS['pgr_test_options'] );

		$admin = new PGR_Admin();
		ob_start();
		$admin->render_gravity_flow_inbox_rtl();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'data-pgr-gpp-compatibility="unknown"', $html );
		$this->assertStringContainsString( 'Inbox 2', $html );
		$this->assertStringContainsString( 'Technical details and verification', $html );
		$this->assertStringNotContainsString( 'checked="checked"', $html );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_four_independent_combinations_reclassify_on_real_settings_change() {
		$this->boot();
		$this->admitted_host();
		$this->fake_gpp();
		$cases = array(
			array( 0, 'off', 'ltr_grid_without_axis', 'known_failure', '390' ),
			array( 0, 'on', 'native_admin_mobile_scroll', 'known_failure', '390' ),
			array( 1, 'off', 'rtl_column_order_without_axis', 'known_failure', '1280' ),
			array( 1, 'on', 'rtl_with_ltr_axis', 'incompatible', '1280' ),
		);
		$admin = new PGR_Admin();
		foreach ( $cases as $case ) {
			list( $pgr, $gpp, $problem, $verdict, $viewport ) = $case;
			$GLOBALS['pgr_test_options']['pgr_settings'] = array( 'gravity_flow_inbox_rtl' => $pgr );
			$this->gpp( $gpp );
			$prior = $GLOBALS['pgr_test_options'];
			$facts = PGR_GPP_Inbox_RTL_Compatibility::facts();
			$this->assertSame( $verdict, $facts['compatibility'] );
			$this->assertSame( $problem, $facts['problem'] );
			$this->assertSame( $gpp, $facts['gpp']['effective'] );
			$this->assertSame( 'not_observed', $facts['grid_mode'] );
			ob_start();
			$admin->render_gravity_flow_inbox_rtl();
			$html = ob_get_clean();
			$this->assertStringContainsString( 'data-pgr-gpp-compatibility="' . $verdict . '"', $html );
			$this->assertStringContainsString( 'data-pgr-gpp-problem="' . $problem . '"', $html );
			$this->assertStringContainsString( $viewport, $html );
			$this->assertStringContainsString( 'GPP PR #174', $html );
			$this->assertStringContainsString( 'role="status"', $html );
			$this->assertSame( $prior, $GLOBALS['pgr_test_options'], 'Warning must never write either plugin options.' );
		}
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_malformed_version_shape_effective_and_throwing_gpp_are_unknown() {
		$this->boot();
		$this->admitted_host();
		$this->fake_gpp();
		$GLOBALS['pgr_test_options']['pgr_settings'] = array( 'gravity_flow_inbox_rtl' => 1 );
		$this->gpp( 'on' );
		$valid = $GLOBALS['pgr_test_gpp_response'];
		$invalid = array(
			null, false, 'enabled', array(),
			array_merge( $valid, array( 'api_version' => 2 ) ),
			array_merge( $valid, array( 'api_version' => '1' ) ),
			array_merge( $valid, array( 'capability' => 'unsupported' ) ),
			array_merge( $valid, array( 'scope' => 'other' ) ),
			array_merge( $valid, array( 'grid_mode' => 'ag-rtl' ) ),
			array_merge( $valid, array( 'requested' => 'off', 'effective' => 'on' ) ),
			array_merge( $valid, array( 'effective' => 'maybe' ) ),
			array_merge( $valid, array( 'requested' => 'unknown', 'effective' => 'off' ) ),
		);
		foreach ( $invalid as $response ) {
			$GLOBALS['pgr_test_gpp_response'] = $response;
			$facts = PGR_GPP_Inbox_RTL_Compatibility::facts();
			$this->assertSame( 'unsupported', $facts['gpp']['interface'] );
			$this->assertSame( 'unknown', $facts['compatibility'] );
		}
		$GLOBALS['pgr_test_gpp_throws'] = true;
		$this->assertSame( 'error', PGR_GPP_Inbox_RTL_Compatibility::facts()['gpp']['interface'] );
		$this->assertSame( 'unknown', PGR_GPP_Inbox_RTL_Compatibility::facts()['compatibility'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_host_lifecycle_and_mixed_locales_do_not_invent_scope() {
		$this->boot();
		$this->fake_gpp();
		$this->gpp( 'on' );
		$GLOBALS['pgr_test_options']['pgr_settings'] = array( 'gravity_flow_inbox_rtl' => 1 );
		$GLOBALS['pgr_test_site_locale'] = 'fa_IR';
		$GLOBALS['pgr_test_is_rtl'] = true;
		$this->assertSame( 'unknown', PGR_GPP_Inbox_RTL_Compatibility::facts()['compatibility'] );
		define( 'GRAVITY_FLOW_VERSION', '3.2.0' );
		define( 'GRAVITY_FLOW_PLUGIN_BASENAME', 'gravityflow/gravityflow.php' );
		$GLOBALS['pgr_test_fired_actions']['init'] = 1;
		$this->assertSame( 'unknown', PGR_GPP_Inbox_RTL_Compatibility::facts()['compatibility'] );
		$GLOBALS['pgr_test_site_locale'] = 'en_US';
		$this->assertSame( 'unknown', PGR_GPP_Inbox_RTL_Compatibility::facts()['compatibility'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_admin_permission_denial_and_native_settings_sanitizer_only() {
		$this->boot();
		$this->admitted_host();
		$this->fake_gpp();
		$this->gpp( 'on' );
		$GLOBALS['pgr_test_can_manage_options'] = false;
		ob_start();
		( new PGR_Admin() )->render_gravity_flow_inbox_rtl();
		$this->assertSame( '', ob_get_clean() );
		$admin = new PGR_Admin();
		$in = array( 'gravity_flow_inbox_rtl' => '1', 'gpp_inbox_axis_ltr' => 'off', 'default_force_english' => 1 );
		$this->assertSame(
			array( 'default_force_english' => 1, 'gravity_flow_inbox_rtl' => 1 ),
			$admin->sanitize_settings( $in ),
			'No foreign setting must be accepted by PGR save.'
		);
		$this->assertSame( array(), $GLOBALS['pgr_test_options'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_locale_scopes_are_distinct_and_unknown_remains_unknown() {
		$this->boot();
		$this->admitted_host();
		$this->fake_gpp();
		$GLOBALS['pgr_test_options']['pgr_settings'] = array( 'gravity_flow_inbox_rtl' => 1 );
		$this->gpp( 'off' );
		$GLOBALS['pgr_test_site_locale'] = 'en_US';
		$GLOBALS['pgr_test_locale'] = 'en_US';
		$GLOBALS['pgr_test_is_rtl'] = false;
		$this->assertSame( 'unknown', PGR_GPP_Inbox_RTL_Compatibility::facts()['compatibility'] );
		$GLOBALS['pgr_test_locale'] = 'ar';
		$GLOBALS['pgr_test_is_rtl'] = true;
		$this->assertSame( 'unknown', PGR_GPP_Inbox_RTL_Compatibility::facts()['compatibility'] );
		$GLOBALS['pgr_test_locale'] = 'fa_IR';
		$this->assertSame( 'native_admin_mobile_touch', PGR_GPP_Inbox_RTL_Compatibility::facts()['problem'] );
		$GLOBALS['pgr_test_site_locale'] = 'fa_IR';
		$GLOBALS['pgr_test_locale'] = 'en_US';
		$GLOBALS['pgr_test_is_rtl'] = false;
		$this->assertSame( 'rtl_column_order_without_axis', PGR_GPP_Inbox_RTL_Compatibility::facts()['problem'] );
		$this->gpp( 'on' );
		$GLOBALS['pgr_test_gpp_response']['requested'] = 'unknown';
		$this->assertSame( 'unknown', PGR_GPP_Inbox_RTL_Compatibility::facts()['compatibility'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_arabic_and_hebrew_rtl_admin_are_not_persian_evidence() {
		$this->boot();
		$this->admitted_host();
		$this->fake_gpp();
		$GLOBALS['pgr_test_site_locale'] = 'en_US';
		$GLOBALS['pgr_test_is_rtl'] = true;
		foreach ( array( 'ar', 'he_IL', 'ur' ) as $admin_locale ) {
			$GLOBALS['pgr_test_locale'] = $admin_locale;
			foreach ( array( 0, 1 ) as $pgr ) {
				$GLOBALS['pgr_test_options']['pgr_settings'] = array( 'gravity_flow_inbox_rtl' => $pgr );
				foreach ( array( 'off', 'on' ) as $gpp ) {
					$this->gpp( $gpp );
					$facts = PGR_GPP_Inbox_RTL_Compatibility::facts();
					$this->assertSame( 'unknown', $facts['compatibility'], $admin_locale );
					$this->assertSame( 'none', $facts['problem'], $admin_locale );
					$this->assertSame( 'not_observed', $facts['grid_mode'] );
				}
			}
		}
		$GLOBALS['pgr_test_locale'] = 'fa_IR';
		$GLOBALS['pgr_test_options']['pgr_settings'] = array( 'gravity_flow_inbox_rtl' => 1 );
		$this->gpp( 'on' );
		$this->assertSame( 'native_admin_mobile_touch', PGR_GPP_Inbox_RTL_Compatibility::facts()['problem'] );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_persian_frontend_provenance_survives_non_persian_admin_locale() {
		$this->boot();
		$this->admitted_host();
		$this->fake_gpp();
		$GLOBALS['pgr_test_site_locale'] = 'fa_IR';
		$GLOBALS['pgr_test_options']['pgr_settings'] = array( 'gravity_flow_inbox_rtl' => 1 );
		$this->gpp( 'off' );
		foreach ( array( 'en_US', 'ar', 'he_IL' ) as $admin_locale ) {
			$GLOBALS['pgr_test_locale'] = $admin_locale;
			$GLOBALS['pgr_test_is_rtl'] = true;
			$this->assertSame( 'rtl_column_order_without_axis', PGR_GPP_Inbox_RTL_Compatibility::facts()['problem'] );
		}
	}
}
