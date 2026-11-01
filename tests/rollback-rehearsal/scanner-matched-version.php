<?php
/**
 * Disposable-only matched historical binary + pre-migration Registry rollback.
 *
 * Four fresh WP-CLI processes run against the same real MariaDB/GF installation.
 * Never invoke on a live site. No private backup or GF form contents are published.
 */
defined( 'ABSPATH' ) || exit;

$phase = getenv( 'PGR_ROLLBACK_PHASE' );
$root = getenv( 'PGR_SCANNER_WP_PATH' );
$private = getenv( 'PGR_ROLLBACK_PRIVATE_DIR' );
$artifacts = getenv( 'PGR_SCANNER_ARTIFACT_DIR' );
$expected_head = getenv( 'PGR_REMEDIATION_SHA' );
if ( ! in_array( $phase, array( 'seed_legacy', 'migrate_and_change', 'restore_backup', 'verify_legacy' ), true )
    || ! is_string( $root ) || realpath( $root ) !== realpath( ABSPATH )
    || ! is_string( $private ) || ! is_dir( $private )
    || ! is_string( $artifacts ) || ! is_dir( $artifacts )
    || ! preg_match( '/^[a-f0-9]{40}$/', (string) $expected_head )
    || ! class_exists( 'PGR_Scanner_Profile_Registry' ) || ! class_exists( 'GFAPI' ) ) {
    throw new RuntimeException( 'Matched rollback is permitted only in the bounded disposable harness.' );
}

global $wpdb;
$option = 'pgr_scanner_profiles';
$assert = static function ( $truth, $message ) {
    if ( ! $truth ) {
        throw new RuntimeException( 'Matched rollback: ' . $message );
    }
};
$raw = static function () use ( $wpdb, $option ) {
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $option ) );
    if ( null === $row && '' !== $wpdb->last_error ) {
        throw new RuntimeException( 'Registry raw DB read failed: ' . $wpdb->last_error );
    }
    return $row ? (string) $row->option_value : null;
};
$invalidate = static function () use ( $option ) {
    foreach ( array( $option, 'alloptions', 'notoptions' ) as $key ) {
        wp_cache_delete( $key, 'options' );
    }
};
$fixture = static function () {
    return array(
        'id' => 'rollback_legacy', 'label' => 'Historical / قدیمی', 'enabled' => true,
        'parser' => array(
            'type' => 'segments_v1', 'separator' => 'newline',
            'trim' => true, 'normalize_digits' => true,
        ),
        'outputs' => array( array( 'key' => 'serial', 'label' => 'Serial', 'required' => true ) ),
    );
};
$read_snapshot = static function () use ( $private ) {
    $file = $private . '/baseline.json';
    if ( ! is_file( $file ) ) {
        throw new RuntimeException( 'Missing separately captured legacy baseline.' );
    }
    return json_decode( file_get_contents( $file ), true, 512, JSON_THROW_ON_ERROR );
};
$field_hash = static function ( $form_id ) use ( $assert ) {
    $form = GFAPI::get_form( (int) $form_id );
    $assert( is_array( $form ) && isset( $form['fields'][0] ), 'mapped GF form disappeared' );
    $scanner = $form['fields'][0];
    $assert( $scanner->type === 'pgr_structured_scanner' &&
        $scanner->scanner_profile === 'rollback_legacy' &&
        $scanner->scanner_mappings['serial'] === '2', 'mapped GF form semantics differ' );
    return hash( 'sha256', wp_json_encode( $scanner ) );
};

if ( 'seed_legacy' === $phase ) {
    $assert( 1 === PGR_Scanner_Profile_Registry::SCHEMA_VERSION, 'historical plugin is not v1' );
    $assert( PGR_VERSION === '4.10.0', 'unexpected historical plugin version' );
    $assert( null === $raw(), 'disposable Registry is not initially absent' );
    $old = PGR_Scanner_Profile_Registry::save_custom_profile( $fixture() );
    $assert( ! empty( $old['success'] ), 'authentic historical v1 profile creation failed' );
    $stored = $raw();
    $value = maybe_unserialize( $stored );
    $assert( is_string( $stored ) && is_array( $value ) &&
        ( $value['schema_version'] ?? null ) === 1 &&
        count( $value['profiles'] ) === 1, 'legacy plugin did not persist genuine v1' );

    $form_id = GFAPI::add_form( array(
        'title' => 'Matched rollback fixture',
        'fields' => array(
            array( 'id' => 1, 'label' => 'Scanner', 'type' => 'pgr_structured_scanner',
                'scanner_profile' => 'rollback_legacy',
                'scanner_mappings' => array( 'serial' => '2' ) ),
            array( 'id' => 2, 'label' => 'Target', 'type' => 'text' ),
        ),
        'button' => array( 'type' => 'text', 'text' => 'Submit' ),
    ) );
    $assert( ! is_wp_error( $form_id ) && is_numeric( $form_id ), 'historical GFAPI fixture creation failed' );
    $baseline = array(
        'backup_sha256' => hash( 'sha256', $stored ),
        'form_id' => (int) $form_id,
        'field_sha256' => $field_hash( $form_id ),
        'legacy_profile' => $value['profiles'][0],
        'historical_version' => PGR_VERSION,
    );
    $assert( strlen( $stored ) === file_put_contents( $private . '/v1-option.raw', $stored ), 'cannot write private v1 backup' );
    chmod( $private . '/v1-option.raw', 0600 );
    $assert( false !== file_put_contents( $private . '/baseline.json',
        wp_json_encode( $baseline, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ), 'cannot write private baseline' );
    chmod( $private . '/baseline.json', 0600 );
    echo "MATCHED_ROLLBACK_LEGACY_SEEDED\n";
} elseif ( 'migrate_and_change' === $phase ) {
    $assert( 2 === PGR_Scanner_Profile_Registry::SCHEMA_VERSION, 'candidate v2 not installed' );
    $baseline = $read_snapshot();
    $saved = file_get_contents( $private . '/v1-option.raw' );
    $assert( $saved === $raw() &&
        hash_equals( $baseline['backup_sha256'], hash( 'sha256', $saved ) ), 'v1 raw backup does not match current Registry' );
    $backup = PGR_Scanner_Profile_Registry::pre_migration_backup();
    $assert( is_array( $backup ) && $backup['raw'] === $saved &&
        hash_equals( $backup['sha256'], $baseline['backup_sha256'] ), 'candidate did not accept authentic old-version backup' );
    $migrated = PGR_Scanner_Profile_Registry::migrate_v1_to_v2( $baseline['backup_sha256'] );
    $assert( ! empty( $migrated['success'] ) && 'migrated' === $migrated['reason'], 'v1->v2 migration failed' );
    $v2 = maybe_unserialize( $raw() );
    $assert( ( $v2['schema_version'] ?? null ) === 2 &&
        count( $v2['profiles'] ?? array() ) === 1, 'v2 readback differs' );
    $existing = PGR_Scanner_Profile_Registry::get( 'rollback_legacy' );
    $assert( is_array( $existing ) &&
        ( $existing['incarnation_id'] ?? null ) !== null &&
        $existing['revision'] === 1 &&
        PGR_Scanner_Profile_Registry::validate_profile( $existing )['profile'] === $baseline['legacy_profile'],
        'historic v1 data changed during migration' );
    $assert( $baseline['field_sha256'] === $field_hash( $baseline['form_id'] ),
        'GFAPI form mapping changed across migration' );

    $updated = $fixture();
    $updated['label'] = 'Changed after backup';
    $edit = PGR_Scanner_Profile_Registry::save_custom_profile(
        $updated, 'rollback_legacy', $existing['revision'], $existing['incarnation_id']
    );
    $assert( ! empty( $edit['success'] ), 'post-backup profile edit failed' );
    $new = $fixture();
    $new['id'] = 'rollback_after_backup';
    $new['label'] = 'New after backup';
    $created = PGR_Scanner_Profile_Registry::save_custom_profile( $new );
    $assert( ! empty( $created['success'] ) &&
        null !== PGR_Scanner_Profile_Registry::get( 'rollback_after_backup' ), 'post-backup create failed' );
    $assert( 'Changed after backup' === PGR_Scanner_Profile_Registry::get( 'rollback_legacy' )['label'],
        'post-backup edit not visible' );
    $assert( $baseline['field_sha256'] === $field_hash( $baseline['form_id'] ),
        'GFAPI metadata was mutated by Registry writes' );
    echo "MATCHED_ROLLBACK_MIGRATED_AND_POST_BACKUP_CHANGES_VERIFIED\n";
} elseif ( 'restore_backup' === $phase ) {
    $assert( 2 === PGR_Scanner_Profile_Registry::SCHEMA_VERSION, 'restore must occur before loading old plugin' );
    $baseline = $read_snapshot();
    $saved = file_get_contents( $private . '/v1-option.raw' );
    $assert( hash_equals( $baseline['backup_sha256'], hash( 'sha256', $saved ) ),
        'private backup failed independent re-verification' );
    $assert( ( maybe_unserialize( $raw() )['schema_version'] ?? null ) === 2 &&
        null !== PGR_Scanner_Profile_Registry::get( 'rollback_after_backup' ),
        'expected post-backup v2 state absent before restoration' );
    // STOP WRITERS is simulated by a serial WP-CLI orchestration boundary.
    // Only the isolated test site is affected. No production downgrade endpoint.
    $written = $wpdb->update( $wpdb->options,
        array( 'option_value' => $saved ), array( 'option_name' => $option ),
        array( '%s' ), array( '%s' ) );
    $invalidate();
    $assert( 1 === $written && $raw() === $saved && get_option( $option ) === maybe_unserialize( $saved ),
        'v1 restoration failed database/cache readback' );
    $assert( $baseline['field_sha256'] === $field_hash( $baseline['form_id'] ),
        'GFAPI metadata modified during restoration' );
    echo "MATCHED_ROLLBACK_V1_BYTES_RESTORED_BEFORE_PLUGIN_DOWNGRADE\n";
} elseif ( 'verify_legacy' === $phase ) {
    $assert( 1 === PGR_Scanner_Profile_Registry::SCHEMA_VERSION &&
        PGR_VERSION === '4.10.0', 'historical binary not restored' );
    $baseline = $read_snapshot();
    $saved = file_get_contents( $private . '/v1-option.raw' );
    $restored = $raw();
    $assert( $saved === $restored &&
        hash_equals( $baseline['backup_sha256'], hash( 'sha256', $restored ) ),
        'exact historical Registry backup was not restored' );
    $profile = PGR_Scanner_Profile_Registry::get( 'rollback_legacy' );
    $assert( is_array( $profile ) &&
        $profile === $baseline['legacy_profile'], 'historical plugin cannot read original v1 content byte-semantically' );
    $assert( null === PGR_Scanner_Profile_Registry::get( 'rollback_after_backup' ) &&
        $profile['label'] === 'Historical / قدیمی',
        'post-backup changes incorrectly survived full v1 Registry rollback' );
    $assert( $baseline['field_sha256'] === $field_hash( $baseline['form_id'] ),
        'GFAPI form mapping not preserved under original plugin' );
    $report = array(
        'result' => 'PASS',
        'evidence_class' => 'MATCHED_HISTORICAL_PLUGIN_V1_BACKUP_ROLLBACK',
        'candidate_product_head' => $expected_head,
        'historical_release' => 'v4.10.0',
        'historical_release_tag_commit' => '97b9fedb4b8d2743ce645c60a8a467fac0de6e76',
        'historical_zip_sha256' => '8d7a61d77f9ef7f59ac39336767a65f125c36df01f85ae7854fe7b80fa1d9f91',
        'wordpress' => get_bloginfo( 'version' ),
        'gravityforms' => GFForms::$version,
        'checks' => array(
            'historical_plugin_created_real_v1' => true,
            'real_gfapi_mapping_before_backup' => true,
            'verified_raw_v1_backup_sha256' => true,
            'real_candidate_migrated_historical_v1' => true,
            'v2_kept_original_profile_fields' => true,
            'v2_post_backup_edit_and_create_observed' => true,
            'v1_backup_restored_before_old_plugin_boot' => true,
            'old_plugin_binary_reinstalled_and_v1_read' => true,
            'original_v1_raw_data_exactly_restored' => true,
            'original_gfapi_mapping_unchanged_through_all_phases' => true,
            'post_backup_registry_edits_and_creates_lost' => true,
        ),
        'post_backup_gfapi_edits' => 'NOT_EXERCISED; only unchanged GFAPI fields were tested',
        'production_restore' => 'NOT_ATTEMPTED',
        'host_specific_restore' => 'NOT_PROVEN',
        'private_backup_published' => false,
    );
    $path = $artifacts . '/matched-version-rollback-report.json';
    $assert( false !== file_put_contents( $path, wp_json_encode( $report,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" ),
        'cannot publish redacted rollback report' );
    echo "MATCHED_VERSION_ROLLBACK_PASS\n";
}
