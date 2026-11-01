<?php
/**
 * Scanner v2 decision regressions. In-memory SQL double only; WP 6.8.3 / MariaDB
 * and real independent PHP workers require separate WU008 runtime proof.
 */
use PHPUnit\Framework\TestCase;

final class ScannerProfileStorageAtomicityTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['pgr_test_options'] = array();
		$GLOBALS['pgr_test_option_autoload'] = array();
		$GLOBALS['pgr_test_fail_write'] = false;
		unset( $GLOBALS['pgr_test_interleave'] );
		$GLOBALS['wpdb'] = new PGR_Test_Scanner_WPDB();
		require_once dirname( __DIR__ ) . '/includes/class-pgr-scanner-profile-registry.php';
	}

	private function profile( $id = 'alpha' ) {
		return array(
			'id' => $id, 'label' => 'Alpha', 'enabled' => true,
			'parser' => array( 'type' => 'segments_v1', 'separator' => 'newline', 'trim' => true, 'normalize_digits' => true ),
			'outputs' => array( array( 'key' => 'one', 'label' => 'One', 'required' => true ) ),
		);
	}

	private function fingerprint( $id = 'alpha' ) {
		return PGR_Scanner_Profile_Registry::snapshot_fingerprint( PGR_Scanner_Profile_Registry::get( $id ) );
	}

	public function test_same_content_recreation_rejects_old_confirmation() {
		$this->assertTrue( PGR_Scanner_Profile_Registry::save_custom_profile( $this->profile() )['success'] );
		$old = $this->fingerprint();
		$this->assertTrue( PGR_Scanner_Profile_Registry::delete_custom_profile( 'alpha', $old ) );
		$this->assertTrue( PGR_Scanner_Profile_Registry::save_custom_profile( $this->profile() )['success'] );
		$this->assertNotSame( $old, $this->fingerprint() );
		$this->assertFalse( PGR_Scanner_Profile_Registry::delete_custom_profile( 'alpha', $old ) );
		$this->assertTrue( PGR_Scanner_Profile_Registry::delete_custom_profile( 'alpha', $this->fingerprint() ) );
	}

	public function test_interleaving_after_delete_read_is_a_conflict_not_a_second_delete() {
		$this->assertTrue( PGR_Scanner_Profile_Registry::save_custom_profile( $this->profile() )['success'] );
		$original = $this->fingerprint();
		$GLOBALS['pgr_test_interleave'] = function () use ( $original ) {
			$this->assertTrue( PGR_Scanner_Profile_Registry::delete_custom_profile( 'alpha', $original ) );
			$replacement = $this->profile();
			$replacement['outputs'][0]['key'] = 'replacement';
			$this->assertTrue( PGR_Scanner_Profile_Registry::save_custom_profile( $replacement )['success'] );
		};
		$this->assertFalse( PGR_Scanner_Profile_Registry::delete_custom_profile( 'alpha', $original ) );
		$this->assertSame( array( 'replacement' ), PGR_Scanner_Profile_Registry::output_keys( 'alpha' ) );
	}

	public function test_failed_writes_never_claim_success() {
		$GLOBALS['pgr_test_fail_write'] = true;
		$this->assertFalse( PGR_Scanner_Profile_Registry::save_custom_profile( $this->profile() )['success'] );
		$GLOBALS['pgr_test_fail_write'] = false;
		$this->assertTrue( PGR_Scanner_Profile_Registry::save_custom_profile( $this->profile() )['success'] );
		$before = $this->fingerprint();
		$GLOBALS['pgr_test_fail_write'] = true;
		$this->assertFalse( PGR_Scanner_Profile_Registry::delete_custom_profile( 'alpha', $before ) );
		$this->assertSame( $before, $this->fingerprint() );
	}

	public function test_explicit_lossless_v1_migration_is_idempotent_and_preserves_old_data() {
		$legacy = array( 'schema_version' => 1, 'profiles' => array( PGR_Scanner_Profile_Registry::validate_profile( $this->profile() )['profile'] ) );
		$GLOBALS['pgr_test_options']['pgr_scanner_profiles'] = $legacy;
		$backup = PGR_Scanner_Profile_Registry::pre_migration_backup();
		$this->assertNotNull( $backup );
		$this->assertFalse( PGR_Scanner_Profile_Registry::save_custom_profile( $this->profile( 'other' ) )['success'] );
		$this->assertSame( '', $this->fingerprint() );
		$this->assertFalse( PGR_Scanner_Profile_Registry::migrate_v1_to_v2( str_repeat( '0', 64 ) )['success'] );
		$this->assertSame( $legacy, $GLOBALS['pgr_test_options']['pgr_scanner_profiles'] );
		$this->assertTrue( PGR_Scanner_Profile_Registry::migrate_v1_to_v2( $backup['sha256'] )['success'] );
		$this->assertSame( 'already_v2', PGR_Scanner_Profile_Registry::migrate_v1_to_v2( $backup['sha256'] )['reason'] );
		$this->assertSame( $legacy['profiles'][0]['outputs'], PGR_Scanner_Profile_Registry::get( 'alpha' )['outputs'] );
		$this->assertSame( 'alpha', PGR_Scanner_Profile_Registry::get( 'alpha' )['id'] );
		$this->assertNotSame( '', $this->fingerprint() );
	}

	public function test_invalid_legacy_records_are_never_silently_dropped() {
		$GLOBALS['pgr_test_options']['pgr_scanner_profiles'] = array( 'schema_version' => 1, 'profiles' => array( $this->profile(), array( 'id' => 'broken' ) ) );
		$this->assertNull( PGR_Scanner_Profile_Registry::pre_migration_backup() );
		$this->assertFalse( PGR_Scanner_Profile_Registry::migrate_v1_to_v2( str_repeat( 'a', 64 ) )['success'] );
		$this->assertCount( 2, $GLOBALS['pgr_test_options']['pgr_scanner_profiles']['profiles'] );
	}

	public function test_stale_edit_cannot_overwrite_recreated_profile_even_when_revision_is_one() {
		$this->assertTrue( PGR_Scanner_Profile_Registry::save_custom_profile( $this->profile() )['success'] );
		$old = PGR_Scanner_Profile_Registry::get( 'alpha' );
		$this->assertTrue( PGR_Scanner_Profile_Registry::delete_custom_profile( 'alpha', $this->fingerprint() ) );
		$this->assertTrue( PGR_Scanner_Profile_Registry::save_custom_profile( $this->profile() )['success'] );
		$new = PGR_Scanner_Profile_Registry::get( 'alpha' );
		$this->assertSame( 1, $new['revision'] );
		$this->assertSame( 1, $old['revision'] );
		$this->assertNotSame( $old['incarnation_id'], $new['incarnation_id'] );
		$edit = $this->profile();
		$edit['label'] = 'Wrong stale edit';
		$this->assertSame(
			array( 'edit_conflict' ),
			PGR_Scanner_Profile_Registry::save_custom_profile( $edit, 'alpha', $old['revision'], $old['incarnation_id'] )['errors']
		);
		$this->assertSame( 'Alpha', PGR_Scanner_Profile_Registry::get( 'alpha' )['label'] );
	}

	public function test_migration_write_failure_leaves_v1_recoverable_and_retryable() {
		$legacy = array(
			'schema_version' => 1,
			'profiles' => array( PGR_Scanner_Profile_Registry::validate_profile( $this->profile() )['profile'] ),
		);
		$GLOBALS['pgr_test_options']['pgr_scanner_profiles'] = $legacy;
		$backup = PGR_Scanner_Profile_Registry::pre_migration_backup();
		$this->assertNotNull( $backup );
		$GLOBALS['pgr_test_fail_write'] = true;
		$failed = PGR_Scanner_Profile_Registry::migrate_v1_to_v2( $backup['sha256'] );
		$this->assertFalse( $failed['success'] );
		$this->assertSame( $legacy, $GLOBALS['pgr_test_options']['pgr_scanner_profiles'] );
		$GLOBALS['pgr_test_fail_write'] = false;
		$this->assertTrue( PGR_Scanner_Profile_Registry::migrate_v1_to_v2( $backup['sha256'] )['success'] );
		$this->assertSame( 'alpha', PGR_Scanner_Profile_Registry::get( 'alpha' )['id'] );
	}

	public function test_migration_declines_altered_backup_and_preserves_preexisting_records() {
		$legacy = array(
			'schema_version' => 1,
			'profiles' => array( PGR_Scanner_Profile_Registry::validate_profile( $this->profile() )['profile'] ),
		);
		$GLOBALS['pgr_test_options']['pgr_scanner_profiles'] = $legacy;
		$backup = PGR_Scanner_Profile_Registry::pre_migration_backup();
		$this->assertNotNull( $backup );
		$GLOBALS['pgr_test_options']['pgr_scanner_profiles']['profiles'][0]['label'] = 'Newer valid v1 data';
		$this->assertFalse( PGR_Scanner_Profile_Registry::migrate_v1_to_v2( $backup['sha256'] )['success'] );
		$this->assertSame( 'Newer valid v1 data', $GLOBALS['pgr_test_options']['pgr_scanner_profiles']['profiles'][0]['label'] );
	}


	public function test_explicit_edit_revision_rejects_stale_form_and_preserves_identity() {
		$this->assertTrue( PGR_Scanner_Profile_Registry::save_custom_profile( $this->profile() )['success'] );
		$prior = PGR_Scanner_Profile_Registry::get( 'alpha' );
		$edited = $this->profile();
		$edited['label'] = 'Changed';
		$this->assertTrue( PGR_Scanner_Profile_Registry::save_custom_profile( $edited, 'alpha', $prior['revision'], $prior['incarnation_id'] )['success'] );
		$this->assertFalse( PGR_Scanner_Profile_Registry::save_custom_profile( $this->profile(), 'alpha', $prior['revision'] )['success'] );
		$now = PGR_Scanner_Profile_Registry::get( 'alpha' );
		$this->assertSame( $prior['incarnation_id'], $now['incarnation_id'] );
		$this->assertSame( 'Changed', $now['label'] );
		$this->assertSame( $prior['revision'] + 1, $now['revision'] );
	}
}
