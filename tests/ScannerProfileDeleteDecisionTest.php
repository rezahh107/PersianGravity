<?php
/**
 * Characterize the exact Scanner deletion decision at the WordPress request boundary.
 */
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class ScannerProfileDeleteDecisionTest extends TestCase {
	#[RunInSeparateProcess]
	public function test_profile_specific_get_render_and_post_security() {
		// Intentionally isolated stubs; real authenticated WP/Gravity Forms is tested in WU008.
		function wp_unslash( $value ) { return $value; }
		function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
		function esc_html_e( $text ) { echo esc_html( $text ); }
		function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
		function esc_url( $url ) { return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ); }
		function wp_nonce_field( $action ) {
			echo '<input name="_wpnonce" value="' . esc_attr( 'nonce-' . $action ) . '">';
		}
		function check_admin_referer( $action ) {
			if ( ( $_POST['_wpnonce'] ?? null ) !== 'nonce-' . $action ) {
				throw new RuntimeException( 'INVALID_NONCE' );
			}
		}
		function add_query_arg( $args, $url ) {
			return $url . '&' . http_build_query( $args );
		}
		function wp_safe_redirect( $url ) {
			throw new RuntimeException( 'REDIRECT:' . $url );
		}
		function wp_die( $message ) {
			throw new RuntimeException( 'DENIED:' . $message );
		}

		require_once dirname( __DIR__ ) . '/includes/class-pgr-scanner-profile-registry.php';
		require_once dirname( __DIR__ ) . '/admin/class-pgr-admin.php';
		$GLOBALS['pgr_test_options'] = array();
		$make = static function ( $id, $label ) {
			return array(
				'id'      => $id,
				'label'   => $label,
				'enabled' => true,
				'parser'  => array( 'type' => 'segments_v1', 'separator' => 'newline', 'trim' => true, 'normalize_digits' => true ),
				'outputs' => array( array( 'key' => 'one', 'label' => 'One', 'required' => true ) ),
			);
		};
		$alpha = 'Alpha & <script>unsafe</script>';
		$this->assertTrue( PGR_Scanner_Profile_Registry::save_custom_profile( $make( 'alpha', $alpha ) )['success'] );
		$this->assertTrue( PGR_Scanner_Profile_Registry::save_custom_profile( $make( 'beta', 'Beta' ) )['success'] );
		$admin = new PGR_Admin();

		$_GET = array();
		ob_start();
		$admin->render_profiles_page();
		$list = ob_get_clean();
		$this->assertStringContainsString( 'action=delete&amp;profile=alpha', $list );
		$this->assertStringNotContainsString( 'name="action" value="pgr_scanner_profile_delete"', $list );
		$this->assertSame( 2, count( PGR_Scanner_Profile_Registry::custom_profiles() ) );

		$_GET = array( 'action' => 'delete', 'profile' => 'alpha' );
		ob_start();
		$admin->render_profiles_page();
		$decision = ob_get_clean();
		$this->assertStringContainsString( 'Confirm Scanner Profile deletion', $decision );
		$this->assertStringContainsString( 'Alpha &amp; &lt;script&gt;unsafe&lt;/script&gt;', $decision );
		$this->assertStringContainsString( '<code dir="ltr">alpha</code>', $decision );
		$this->assertStringContainsString( 'does not rewrite existing form mappings', $decision );
		$this->assertStringContainsString( 'may become unavailable', $decision );
		$fingerprint_a = PGR_Scanner_Profile_Registry::snapshot_fingerprint( PGR_Scanner_Profile_Registry::get( 'alpha' ) );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $fingerprint_a );
		$this->assertStringContainsString( 'name="confirmed_profile_fingerprint" value="' . $fingerprint_a . '"', $decision );
		$this->assertStringContainsString( 'nonce-pgr_scanner_profile_delete_alpha_' . $fingerprint_a, $decision );
		$this->assertStringContainsString( 'Confirm delete', $decision );
		$this->assertStringContainsString( 'Cancel without deleting', $decision );
		$this->assertStringNotContainsString( 'Create Profile', $decision );
		$this->assertSame( 2, count( PGR_Scanner_Profile_Registry::custom_profiles() ), 'GET must not mutate.' );

		foreach ( array( '', 'missing', 'sayad_v01' ) as $id ) {
			$_GET = array( 'action' => 'delete', 'profile' => $id );
			ob_start();
			$admin->render_profiles_page();
			$unavailable = ob_get_clean();
			$this->assertStringContainsString( 'deletion unavailable', $unavailable );
			$this->assertStringNotContainsString( '<form', $unavailable );
		}
		$_GET = array( 'action' => array( 'delete' ), 'profile' => array( 'alpha' ) );
		ob_start();
		$admin->render_profiles_page();
		$malformed = ob_get_clean();
		$this->assertStringContainsString( 'Scanner Profiles', $malformed );
		$this->assertSame( 2, count( PGR_Scanner_Profile_Registry::custom_profiles() ) );

		$valid = array(
			'profile_id'                   => 'alpha',
			'confirmed_profile_label'       => $alpha,
			'confirmed_profile_fingerprint' => $fingerprint_a,
			'_wpnonce'                      => 'nonce-pgr_scanner_profile_delete_alpha_' . $fingerprint_a,
		);
		foreach ( array(
			array_replace( $valid, array( 'profile_id' => 'beta' ) ),
			array_replace( $valid, array( '_wpnonce' => '' ) ),
			array_replace( $valid, array( 'confirmed_profile_fingerprint' => str_repeat( '0', 64 ) ) ),
		) as $invalid ) {
			$_POST = $invalid;
			try {
				$admin->handle_profile_delete();
				$this->fail( 'Invalid profile-bound nonce must be refused.' );
			} catch ( RuntimeException $e ) {
				$this->assertSame( 'INVALID_NONCE', $e->getMessage() );
			}
		}
		foreach ( array(
			array_replace( $valid, array( 'confirmed_profile_label' => 'stale label' ) ),
			array_replace( $valid, array( 'profile_id' => array( 'alpha' ) ) ),
			array_replace( $valid, array( 'confirmed_profile_fingerprint' => '' ) ),
			array( 'profile_id' => 'sayad_v01', 'confirmed_profile_label' => 'Built-in', 'confirmed_profile_fingerprint' => $fingerprint_a, '_wpnonce' => 'nonce-pgr_scanner_profile_delete_sayad_v01_' . $fingerprint_a ),
		) as $invalid ) {
			$_POST = $invalid;
			try {
				$admin->handle_profile_delete();
				$this->fail( 'Invalid deletion request must be refused.' );
			} catch ( RuntimeException $e ) {
				$this->assertStringContainsString( 'pgr_notice=delete_error', $e->getMessage() );
			}
		}
		$this->assertNotNull( PGR_Scanner_Profile_Registry::get( 'alpha' ) );
		$this->assertNotNull( PGR_Scanner_Profile_Registry::get( 'beta' ) );

		// Non-executable metadata is still part of the user-confirmed snapshot.
		foreach ( array( 'enabled', 'output_label', 'profile_label' ) as $change ) {
			$changed = $make( 'alpha', $alpha );
			if ( 'enabled' === $change ) {
				$changed['enabled'] = false;
			} elseif ( 'output_label' === $change ) {
				$changed['outputs'][0]['label'] = 'Changed output label';
			} else {
				// Display label changes also invalidate the previously confirmed snapshot.
				$changed['label'] = 'Different profile label';
			}
			$this->assertTrue( PGR_Scanner_Profile_Registry::save_custom_profile( $changed, 'alpha' )['success'] );
			$this->assertNotSame( $fingerprint_a, PGR_Scanner_Profile_Registry::snapshot_fingerprint( PGR_Scanner_Profile_Registry::get( 'alpha' ) ) );
			$_POST = $valid;
			try {
				$admin->handle_profile_delete();
				$this->fail( 'Previously confirmed state must not delete modified profile.' );
			} catch ( RuntimeException $e ) {
				$this->assertStringContainsString( 'pgr_notice=delete_error', $e->getMessage() );
			}
			$this->assertNotNull( PGR_Scanner_Profile_Registry::get( 'alpha' ) );
			$this->assertTrue( PGR_Scanner_Profile_Registry::save_custom_profile( $make( 'alpha', $alpha ), 'alpha' )['success'] );
			$this->assertSame( $fingerprint_a, PGR_Scanner_Profile_Registry::snapshot_fingerprint( PGR_Scanner_Profile_Registry::get( 'alpha' ) ) );
		}

		// An old confirmation cannot delete a differently defined new incarnation.
		$this->assertTrue( PGR_Scanner_Profile_Registry::delete_custom_profile( 'alpha' ) );
		$new_incarnation = $make( 'alpha', $alpha );
		$new_incarnation['outputs'][0]['key'] = 'replacement';
		$new_incarnation['parser']['trim'] = false;
		$this->assertTrue( PGR_Scanner_Profile_Registry::save_custom_profile( $new_incarnation )['success'] );
		$fingerprint_b = PGR_Scanner_Profile_Registry::snapshot_fingerprint( PGR_Scanner_Profile_Registry::get( 'alpha' ) );
		$this->assertNotSame( $fingerprint_a, $fingerprint_b );
		$_POST = $valid;
		try {
			$admin->handle_profile_delete();
			$this->fail( 'Old A nonce must not delete new B.' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'pgr_notice=delete_error', $e->getMessage() );
		}
		$this->assertSame( array( 'replacement' ), PGR_Scanner_Profile_Registry::output_keys( 'alpha' ) );

		// A genuinely fresh B-specific confirmation may still delete B.
		$valid['confirmed_profile_fingerprint'] = $fingerprint_b;
		$valid['_wpnonce'] = 'nonce-pgr_scanner_profile_delete_alpha_' . $fingerprint_b;
		$_POST = $valid;
		try {
			$admin->handle_profile_delete();
			$this->fail( 'Successful delete must redirect.' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'pgr_notice=deleted', $e->getMessage() );
		}
		$this->assertNull( PGR_Scanner_Profile_Registry::get( 'alpha' ) );
		$this->assertNotNull( PGR_Scanner_Profile_Registry::get( 'beta' ) );
		$this->assertNotNull( PGR_Scanner_Profile_Registry::get( 'sayad_v01' ) );
		$_POST = $valid;
		try {
			$admin->handle_profile_delete();
			$this->fail( 'Replayed POST must be refused.' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'pgr_notice=delete_error', $e->getMessage() );
		}
	}
}
