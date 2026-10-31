<?php

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class ModuleUsageTest extends TestCase {

	#[RunInSeparateProcess]
	public function test_custom_field_use_blocks_disable() {
		eval( 'class GFAPI { public static function get_forms($active = null, $trash = false) { return array(array("id"=>1,"fields"=>array((object) array("type"=>"pgr_national_id")))); } }' );
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-registry.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-usage.php';
		$result = PGR_Module_Usage::inspect( 'national_id' );
		$this->assertSame( PGR_Module_Usage::USED, $result['status'] );
		$this->assertSame( 1, $result['count'] );
	}

	#[RunInSeparateProcess]
	public function test_unused_custom_field_can_be_disabled() {
		eval( 'class GFAPI { public static function get_forms($active = null, $trash = false) { return array(array("id"=>1,"fields"=>array((object) array("type"=>"text")))); } }' );
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-registry.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-usage.php';
		$result = PGR_Module_Usage::inspect( 'jalali_date' );
		$this->assertSame( PGR_Module_Usage::UNUSED, $result['status'] );
	}

	#[RunInSeparateProcess]
	public function test_presentation_disable_detects_persisted_confirmation_and_notification_tags() {
		eval( 'class GFAPI { public static function get_forms($active = null, $trash = false) { return array(array("id"=>1,"confirmations"=>array(array("message"=>"{pgr_jdate_created}"))),array("id"=>2,"notifications"=>array(array("message"=>"{pgr_jdate_field_2}")))); } }' );
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-registry.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-usage.php';
		$result = PGR_Module_Usage::inspect( 'jalali_presentation' );
		$this->assertSame( PGR_Module_Usage::USED, $result['status'] );
		$this->assertSame( 2, $result['count'] );
	}

	#[RunInSeparateProcess]
	public function test_presentation_without_tags_can_be_disabled() {
		eval( 'class GFAPI { public static function get_forms($active = null, $trash = false) { return array(array("id"=>1,"fields"=>array(),"confirmations"=>array(array("message"=>"{date_created}")))); } }' );
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-registry.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-usage.php';
		$this->assertSame( PGR_Module_Usage::UNUSED, PGR_Module_Usage::inspect( 'jalali_presentation' )['status'] );
	}

	#[RunInSeparateProcess]
	public function test_digit_normalization_form_use_is_detected() {
		eval( 'class GFAPI { public static function get_forms($active = null, $trash = false) { return array(array("id"=>2,"pgr_normalize_digits"=>1,"fields"=>array())); } }' );
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-registry.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-usage.php';
		$result = PGR_Module_Usage::inspect( 'digit_normalization' );
		$this->assertSame( PGR_Module_Usage::USED, $result['status'] );
	}

	#[RunInSeparateProcess]
	public function test_iranian_address_use_is_detected_from_address_type() {
		eval( 'class GFAPI { public static function get_forms($active = null, $trash = false) { return array(array("id"=>3,"fields"=>array((object) array("type"=>"address","addressType"=>"iran")))); } }' );
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-registry.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-usage.php';
		$result = PGR_Module_Usage::inspect( 'iranian_address' );
		$this->assertSame( PGR_Module_Usage::USED, $result['status'] );
	}

	#[RunInSeparateProcess]
	public function test_currency_is_conservatively_unknown() {
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-registry.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-usage.php';
		$result = PGR_Module_Usage::inspect( 'iranian_currency' );
		$this->assertSame( PGR_Module_Usage::UNKNOWN, $result['status'] );
	}

	#[RunInSeparateProcess]
	public function test_gravity_forms_unavailable_is_unknown_for_form_owned_modules() {
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-registry.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-usage.php';
		$result = PGR_Module_Usage::inspect( 'national_id' );
		$this->assertSame( PGR_Module_Usage::UNKNOWN, $result['status'] );
	}

	#[RunInSeparateProcess]
	public function test_indeterminate_member_classification_and_used_precedence() {
		eval( 'class GFAPI { public static $forms = array(); public static $throw = false; public static function get_forms($active = null, $trash = false) { if (self::$throw) { throw new RuntimeException("GFAPI failure"); } return self::$forms; } }' );
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-registry.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-usage.php';

		$valid        = array( 'id' => 1, 'fields' => array(), 'confirmations' => array() );
		$native       = array_replace( $valid, array( 'confirmations' => array( array( 'message' => '{date_created}' ) ) ) );
		$used         = array_replace( $valid, array( 'confirmations' => array( array( 'message' => '{pgr_jdate_created}' ) ) ) );
		$notification = array_replace( $valid, array( 'notifications' => array( array( 'message' => '{pgr_jdate_field_2}' ) ) ) );
		$malformed    = array(
			null,
			false,
			'broken',
			(object) $valid,
			array(),
			array( 'id' => 1 ),
			array( 'fields' => array() ),
			array_replace( $valid, array( 'fields' => array( false ) ) ),
			array_replace( $valid, array( 'fields' => array( array( 'type' => null ) ) ) ),
		);
		$cases = array(
			array( 'empty', array(), 'jalali_presentation', 'UNUSED', 0 ),
			array( 'valid native', array( $native ), 'jalali_presentation', 'UNUSED', 0 ),
			array( 'valid dedicated', array( $used ), 'jalali_presentation', 'USED', 1 ),
			array( 'notification', array( $notification ), 'jalali_presentation', 'USED', 1 ),
			array( 'mixed unused/invalid', array( $native, false ), 'jalali_presentation', 'UNKNOWN', 0 ),
			array( 'mixed used/invalid', array( $used, false ), 'jalali_presentation', 'USED', 1 ),
			array( 'two used/invalid', array( $used, false, $notification ), 'jalali_presentation', 'USED', 2 ),
			array( 'top-level invalid', null, 'jalali_presentation', 'UNKNOWN', 0 ),
			array( 'field used', array( array( 'id' => 2, 'fields' => array( (object) array( 'type' => 'pgr_national_id' ) ) ) ), 'national_id', 'USED', 1 ),
			array( 'field unused', array( $valid ), 'national_id', 'UNUSED', 0 ),
			array( 'field invalid', array( array_replace( $valid, array( 'fields' => array( false ) ) ) ), 'national_id', 'UNKNOWN', 0 ),
			array( 'digit invalid', array( array( 'id' => 1 ) ), 'digit_normalization', 'UNKNOWN', 0 ),
			array( 'address invalid', array( array_replace( $valid, array( 'fields' => array( array( 'type' => 'address', 'addressType' => array() ) ) ) ) ), 'iranian_address', 'UNKNOWN', 0 ),
		);
		foreach ( $malformed as $index => $member ) {
			$cases[] = array( 'malformed ' . $index, array( $member ), 'jalali_presentation', 'UNKNOWN', 0 );
		}

		$recursive         = $valid;
		$recursive['self']  = &$recursive;
		$cases[] = array( 'invalid JSON is indeterminate', array( $recursive ), 'jalali_presentation', 'UNKNOWN', 0 );
		$cases[] = array( 'mixed used and unserializable', array( $used, $recursive ), 'jalali_presentation', 'USED', 1 );
		$cases[] = array( 'mixed unused and unserializable', array( $native, $recursive ), 'jalali_presentation', 'UNKNOWN', 0 );
		$cases[] = array( 'used only', array( $used ), 'jalali_presentation', 'USED', 1 );
		$cases[] = array( 'malformed only', array( $recursive ), 'jalali_presentation', 'UNKNOWN', 0 );

		foreach ( $cases as $case ) {
			GFAPI::$forms = $case[1];
			GFAPI::$throw = false;
			$result       = PGR_Module_Usage::inspect( $case[2] );
			$this->assertSame( $case[3], $result['status'], $case[0] );
			$this->assertSame( $case[4], $result['count'], $case[0] . ' count' );
		}
		GFAPI::$throw = true;
		$this->assertSame( 'UNKNOWN', PGR_Module_Usage::inspect( 'jalali_presentation' )['status'], 'GFAPI exception' );
	}

	#[RunInSeparateProcess]
	public function test_admin_disable_uses_tri_state_result_without_bypass() {
		eval( 'class GFAPI { public static $forms = array(); public static function get_forms($active = null, $trash = false) { return self::$forms; } }' );
		eval( 'class PGR_Admin { const CAPABILITY = "manage_options"; const MENU_SLUG = "persian-gravityforms"; }' );
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-registry.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-usage.php';
		require_once dirname( __DIR__ ) . '/admin/class-pgr-product-admin.php';

		// Interrupt the actual admin redirect before its exit so this test can
		// observe the selected branch and the registry state in one process.
		function check_admin_referer( $action ) { return true; }
		function wp_unslash( $value ) { return $value; }
		function sanitize_key( $value ) { return strtolower( (string) $value ); }
		function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
		function add_query_arg( $args, $url ) { return $url . '&' . http_build_query( $args ); }
		function wp_safe_redirect( $url ) { throw new RuntimeException( $url ); }
		function get_current_user_id() { return 7; }
		function wp_generate_uuid4() { return '123e4567-e89b-42d3-a456-426614174000'; }
		function set_transient( $key, $value, $expiration ) { $GLOBALS['pgr_test_transients'][ $key ] = $value; return $expiration > 0; }
		function get_transient( $key ) { return $GLOBALS['pgr_test_transients'][ $key ] ?? false; }
		function delete_transient( $key ) { unset( $GLOBALS['pgr_test_transients'][ $key ] ); return true; }

		$handler = new PGR_Product_Admin( new PGR_Admin() );
		$valid   = array( 'id' => 1, 'fields' => array() );
		$cases   = array(
			array( 'USED', array( array_replace( $valid, array( 'confirmations' => array( array( 'message' => '{pgr_jdate_created}' ) ) ) ) ), false, 'blocked', true, false ),
			array( 'UNKNOWN', array( false, $valid ), false, 'confirm', true, false ),
			array( 'UNUSED', array( $valid ), false, 'disabled', false, false ),
			array( 'UNKNOWN forged confirmation', array( false ), true, 'invalid', true, false ),
			array( 'UNKNOWN valid confirmation', array( false ), true, 'disabled', false, true ),
			array( 'USED stale confirmation', array( array_replace( $valid, array( 'confirmations' => array( array( 'message' => '{pgr_jdate_created}' ) ) ) ) ), true, 'blocked', true, true ),
		);
		foreach ( $cases as $case ) {
			$GLOBALS['pgr_test_options'] = array(
				'pgr_modules' => array( 'schema_version' => 1, 'states' => array( 'jalali_presentation' => true ) ),
			);
			GFAPI::$forms               = $case[1];
			$GLOBALS['pgr_test_transients'] = array();
			if ( $case[5] ) {
				$GLOBALS['pgr_test_transients']['pgr_disable_confirm_7'] = array(
					'module_id' => 'jalali_presentation',
					'token' => '123e4567-e89b-42d3-a456-426614174000',
				);
			}
			$_SERVER['REQUEST_METHOD']  = 'POST';
			$_POST = array(
				'module_id' => 'jalali_presentation',
				'enable'    => '0',
				'confirm'   => $case[2] ? '1' : '0',
				'confirm_token' => $case[5] ? '123e4567-e89b-42d3-a456-426614174000' : '',
			);
			$url = '';
			try {
				$handler->handle_module_toggle();
				$this->fail( $case[0] . ' did not redirect' );
			} catch ( RuntimeException $exception ) {
				$url = $exception->getMessage();
			}
			$this->assertStringContainsString( 'pgr_module_notice=' . $case[3], $url, $case[0] );
			$this->assertSame( $case[4], PGR_Module_Registry::is_enabled( 'jalali_presentation' ), $case[0] );
			if ( 'UNKNOWN' === $case[0] ) {
				$this->assertStringContainsString( 'pgr_confirm_token=123e4567-e89b-42d3-a456-426614174000', $url );
				$this->assertSame( 'jalali_presentation', $GLOBALS['pgr_test_transients']['pgr_disable_confirm_7']['module_id'] );
			}
			if ( 'UNKNOWN valid confirmation' === $case[0] ) {
				$this->assertArrayNotHasKey( 'pgr_disable_confirm_7', $GLOBALS['pgr_test_transients'] );
			}
		}
	}
	#[RunInSeparateProcess]
	public function test_confirmation_get_requires_real_fresh_user_bound_unknown_intent_and_cancel_revokes_it() {
		if ( ! defined( 'PGR_VERSION' ) ) {
			define( 'PGR_VERSION', '4.10.0' );
		}
		eval( 'class GFAPI { public static $forms = array(); public static function get_forms($active = null, $trash = false) { return self::$forms; } }' );
		eval( 'class PGR_Admin { const CAPABILITY = "manage_options"; const MENU_SLUG = "persian-gravityforms"; }' );
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-registry.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-usage.php';
		require_once dirname( __DIR__ ) . '/admin/class-pgr-product-admin.php';
		function wp_unslash( $value ) { return $value; }
		function sanitize_key( $value ) { return strtolower( (string) $value ); }
		function get_current_user_id() { return 7; }
		function get_transient( $key ) { return $GLOBALS['pgr_test_transients'][ $key ] ?? false; }
		function delete_transient( $key ) { unset( $GLOBALS['pgr_test_transients'][ $key ] ); return true; }
		function get_user_locale() { return $GLOBALS['pgr_test_locale']; }
		function esc_html_e( $text ) { echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
		function esc_url( $url ) { return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ); }
		function wp_nonce_field( $action ) { echo '<input name="_wpnonce" value="unit-nonce">'; }
		function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
		function add_query_arg( $key, $value, $url ) { return $url . '&' . rawurlencode( $key ) . '=' . rawurlencode( $value ); }
		function wp_safe_redirect( $url ) { throw new RuntimeException( $url ); }
		GFAPI::$forms = array( false ); // Real classification is UNKNOWN, not cached from GET.
		$GLOBALS['pgr_test_options'] = array(
			'pgr_modules' => array( 'schema_version' => 1, 'states' => array( 'iranian_currency' => true ) ),
		);
		$token = '123e4567-e89b-42d3-a456-426614174000';
		$GLOBALS['pgr_test_transients'] = array(
			'pgr_disable_confirm_7' => array( 'module_id' => 'iranian_currency', 'token' => $token ),
		);
		$handler = new PGR_Product_Admin( new PGR_Admin() );
		$_GET = array( 'pgr_module_notice' => 'confirm', 'pgr_module' => 'iranian_currency',
			'pgr_confirm_module' => 'iranian_currency', 'pgr_confirm_token' => $token );
		ob_start();
		$handler->render_overview_page();
		$html = ob_get_clean();
		$this->assertSame( 1, substr_count( $html, 'class="pgr-panel pgr-module-decision"' ) );
		$this->assertSame( 6, substr_count( $html, '<article class="pgr-card pgr-module-card">' ) );
		$this->assertSame( 1, substr_count( $html, 'name="confirm" value="1"' ) );
		$this->assertStringContainsString( 'Confirm disable', $html );
		$this->assertStringContainsString( 'Cancel and return', $html );
		$this->assertStringContainsString( 'ریال و تومان ایران', $html );
		$this->assertStringContainsString( 'Iranian Rial / Toman', $html );
		foreach ( array(
			array( 'pgr_confirm_token', 'forged' ),
			array( 'pgr_confirm_module', 'national_id' ),
			array( 'pgr_module', 'national_id' ),
		) as $invalid ) {
			$_GET[ $invalid[0] ] = $invalid[1];
			ob_start();
			$handler->render_overview_page();
			$html = ob_get_clean();
			$this->assertStringNotContainsString( 'class="pgr-panel pgr-module-decision"', $html );
			$this->assertStringNotContainsString( 'name="confirm" value="1"', $html );
			$_GET[ $invalid[0] ] = 'pgr_confirm_token' === $invalid[0] ? $token : 'iranian_currency';
		}
		GFAPI::$forms = array( array( 'id' => 1, 'fields' => array( (object) array( 'type' => 'text' ) ) ) );
		// Currency remains UNKNOWN, but the module mismatch and ticket requirements still apply.
		$GLOBALS['pgr_test_transients'] = array();
		ob_start();
		$handler->render_overview_page();
		$html = ob_get_clean();
		$this->assertStringNotContainsString( 'class="pgr-panel pgr-module-decision"', $html );
		$this->assertTrue( PGR_Module_Registry::is_enabled( 'iranian_currency' ) );
		$GLOBALS['pgr_test_transients']['pgr_disable_confirm_7'] = array( 'module_id' => 'iranian_currency', 'token' => $token );
		$_GET = array( 'pgr_cancel_confirm' => $token );
		try {
			$handler->handle_overview_cancel();
			$this->fail( 'Pre-header Cancel must safely redirect' );
		} catch ( RuntimeException $exception ) {
			$this->assertStringContainsString( 'admin.php?page=persian-gravityforms', $exception->getMessage() );
		}
		$this->assertArrayNotHasKey( 'pgr_disable_confirm_7', $GLOBALS['pgr_test_transients'] );
		$this->assertTrue( PGR_Module_Registry::is_enabled( 'iranian_currency' ) );
		$_GET = array();
		ob_start();
		$handler->render_overview_page();
		$html = ob_get_clean();
		$this->assertSame( 7, substr_count( $html, '<article class="pgr-card pgr-module-card">' ) );
		$this->assertStringNotContainsString( 'class="pgr-panel pgr-module-decision"', $html );
	}

}
