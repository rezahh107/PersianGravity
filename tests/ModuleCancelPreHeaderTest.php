<?php
/**
 * Verify that Cancel is owned exclusively by the Overview pre-header load hook.
 */
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class ModuleCancelPreHeaderTest extends TestCase {
	#[RunInSeparateProcess]
	public function test_cancel_runs_only_on_canonical_overview_load_hook_before_flushed_headers() {
		eval( 'class PGR_Admin { const CAPABILITY = "manage_options"; const MENU_SLUG = "persian-gravityforms"; const PROFILES_SLUG = "pgr-scanner-profiles"; const SETTINGS_SLUG = "pgr-settings"; const STATUS_SLUG = "pgr-system-status"; }' );
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-registry.php';
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-usage.php';
		require_once dirname( __DIR__ ) . '/admin/class-pgr-product-admin.php';

		function get_current_screen() { return null; }
		function add_menu_page( $title, $menu, $capability, $slug, $callback, $icon, $position ) {
			return 'toplevel_page_' . $slug;
		}
		function add_submenu_page( $parent, $title, $menu, $capability, $slug, $callback ) {
			return $slug === $parent ? 'toplevel_page_' . $parent : 'persian-gravity_page_' . $slug;
		}
		function get_current_user_id() { return (int) $GLOBALS['pgr_cancel_test_user']; }
		function wp_unslash( $value ) { return $value; }
		function sanitize_key( $value ) { return strtolower( (string) $value ); }
		function get_transient( $key ) { return $GLOBALS['pgr_cancel_test_transients'][ $key ] ?? false; }
		function delete_transient( $key ) {
			unset( $GLOBALS['pgr_cancel_test_transients'][ $key ] );
			return true;
		}
		function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
		function wp_safe_redirect( $url ) {
			if ( ! empty( $GLOBALS['pgr_cancel_test_headers_flushed'] ) ) {
				throw new RuntimeException( 'REDIRECT_AFTER_HEADERS' );
			}
			throw new RuntimeException( 'REDIRECT_302:' . $url );
		}

		$handler = new PGR_Product_Admin( new PGR_Admin() );
		$handler->add_menu();
		$overview = 'load-toplevel_page_persian-gravityforms';

		$cancel_handlers = array();
		foreach ( $GLOBALS['pgr_test_actions'] as $hook => $priorities ) {
			foreach ( $priorities as $callbacks ) {
				foreach ( $callbacks as $item ) {
					if ( is_array( $item[0] ) && $item[0][0] === $handler &&
						'handle_overview_cancel' === $item[0][1] ) {
						$cancel_handlers[] = $hook;
					}
				}
			}
		}
		$this->assertSame( array( $overview ), $cancel_handlers,
			'Cancel must be registered only on the exact Overview load hook, not render/admin_init.' );

		$token = '123e4567-e89b-42d3-a456-426614174000';
		$GLOBALS['pgr_cancel_test_user'] = 7;
		$GLOBALS['pgr_cancel_test_transients'] = array(
			'pgr_disable_confirm_7' => array( 'module_id' => 'iranian_currency', 'token' => $token ),
			'pgr_disable_confirm_8' => array( 'module_id' => 'iranian_currency', 'token' => 'other-user-token' ),
		);
		$GLOBALS['pgr_test_options'] = array(
			'pgr_modules' => array( 'schema_version' => 1, 'states' => array( 'iranian_currency' => true ) ),
		);
		$GLOBALS['pgr_cancel_test_headers_flushed'] = false;
		$_GET = array( 'pgr_cancel_confirm' => $token );
		try {
			do_action( $overview );
			$this->fail( 'Valid Cancel must redirect from the pre-header load hook.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame(
				'REDIRECT_302:https://example.test/wp-admin/admin.php?page=persian-gravityforms',
				$exception->getMessage()
			);
		}
		$this->assertArrayNotHasKey( 'pgr_disable_confirm_7', $GLOBALS['pgr_cancel_test_transients'] );
		$this->assertArrayHasKey( 'pgr_disable_confirm_8', $GLOBALS['pgr_cancel_test_transients'] );
		$this->assertTrue( PGR_Module_Registry::is_enabled( 'iranian_currency' ) );

		// Once the admin header is flushed, render must not attempt another redirect.
		$GLOBALS['pgr_cancel_test_headers_flushed'] = true;
		$_GET = array( 'pgr_cancel_confirm' => $token );
		if ( ! defined( 'PGR_VERSION' ) ) {
			define( 'PGR_VERSION', '4.10.0' );
		}
		function get_user_locale() { return 'en_US'; }
		function esc_html_e( $text ) { echo esc_html( $text ); }
		function esc_url( $url ) { return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ); }
		function wp_nonce_field( $action ) { echo '<input name="_wpnonce" value="unit">'; }
		ob_start();
		try {
			$handler->render_overview_page();
			$html = ob_get_clean();
		} catch ( RuntimeException $exception ) {
			ob_end_clean();
			throw $exception;
		}
		$this->assertStringContainsString( 'pgr-module-grid', $html,
			'Rendering with a stale cancellation query must remain read-only.' );
		$this->assertTrue( PGR_Module_Registry::is_enabled( 'iranian_currency' ) );
	}

	#[RunInSeparateProcess]
	public function test_invalid_foreign_expired_and_mismatched_cancel_tickets_leave_transients_and_modules_untouched() {
		eval( 'class PGR_Admin { const CAPABILITY = "manage_options"; const MENU_SLUG = "persian-gravityforms"; const PROFILES_SLUG = "pgr-scanner-profiles"; const SETTINGS_SLUG = "pgr-settings"; const STATUS_SLUG = "pgr-system-status"; }' );
		require_once dirname( __DIR__ ) . '/includes/class-pgr-module-registry.php';
		require_once dirname( __DIR__ ) . '/admin/class-pgr-product-admin.php';
		function get_current_screen() { return null; }
		function add_menu_page( $title, $menu, $capability, $slug, $callback, $icon, $position ) { return 'toplevel_page_' . $slug; }
		function add_submenu_page( $parent, $title, $menu, $capability, $slug, $callback ) { return $slug === $parent ? 'toplevel_page_' . $parent : 'other_' . $slug; }
		function get_current_user_id() { return (int) $GLOBALS['pgr_cancel_test_user']; }
		function wp_unslash( $value ) { return $value; }
		function sanitize_key( $value ) { return strtolower( (string) $value ); }
		function get_transient( $key ) { return $GLOBALS['pgr_cancel_test_transients'][ $key ] ?? false; }
		function delete_transient( $key ) { unset( $GLOBALS['pgr_cancel_test_transients'][ $key ] ); return true; }
		function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
		function wp_safe_redirect( $url ) { throw new RuntimeException( 'REDIRECT_302:' . $url ); }
		$handler = new PGR_Product_Admin( new PGR_Admin() );
		$handler->add_menu();
		$hook = 'load-toplevel_page_persian-gravityforms';
		$GLOBALS['pgr_test_options'] = array(
			'pgr_modules' => array( 'schema_version' => 1, 'states' => array( 'iranian_currency' => true ) ),
		);
		$GLOBALS['pgr_cancel_test_user'] = 7;
		foreach ( array(
			array( 'foreign user', 8, 'valid-for-7', true ),
			array( 'mismatched token', 7, 'wrong', true ),
			array( 'expired or missing', 7, 'expired', false ),
			array( 'malformed array', 7, array( 'token' ), true ),
		) as $case ) {
			$GLOBALS['pgr_cancel_test_user'] = $case[1];
			$GLOBALS['pgr_cancel_test_transients'] = $case[3]
				? array( 'pgr_disable_confirm_7' => array( 'module_id' => 'national_id', 'token' => 'valid-for-7' ) )
				: array();
			$before = $GLOBALS['pgr_cancel_test_transients'];
			$_GET = array( 'pgr_cancel_confirm' => $case[2] );
			if ( 'malformed array' === $case[0] ) {
				// An invalid GET value is rejected by query_key without sanitization.
				$_GET['pgr_cancel_confirm'] = array( 'token' );
			}
			try {
				do_action( $hook );
				if ( ! is_array( $case[2] ) ) {
					$this->fail( $case[0] . ' must redirect to clean Overview.' );
				}
			} catch ( RuntimeException $exception ) {
				$this->assertSame(
					'REDIRECT_302:https://example.test/wp-admin/admin.php?page=persian-gravityforms',
					$exception->getMessage(), $case[0]
				);
			}
			$this->assertSame( $before, $GLOBALS['pgr_cancel_test_transients'], $case[0] );
			$this->assertTrue( PGR_Module_Registry::is_enabled( 'iranian_currency' ), $case[0] );
		}
	}
}
