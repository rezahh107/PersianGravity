<?php
/**
 * WU008-only real WordPress/MariaDB recovery & SQL failure qualification.
 *
 * Executes against a disposable WordPress installation only. This fixture may
 * temporarily swap the Scanner option from v2 to v1 and restores its exact
 * initial bytes in a finally block. Never run against production.
 */
defined( 'ABSPATH' ) || exit;

$root     = getenv( 'WU008_WP_PATH' );
$artifact = getenv( 'WU008_ARTIFACT_DIR' );
$head     = getenv( 'WU008_PGR_SHA' );
if ( ! $root || ! $artifact || ! preg_match( '/^[a-f0-9]{40}$/', (string) $head ) ||
	realpath( $root ) !== realpath( ABSPATH ) ||
	! class_exists( 'PGR_Scanner_Profile_Registry' ) || ! class_exists( 'GFAPI' ) ) {
	throw new RuntimeException( 'Scanner recovery qualification requires exact disposable WU008 runtime.' );
}

global $wpdb;
$option = 'pgr_scanner_profiles';
$assert = static function ( $valid, $message ) {
	if ( ! $valid ) {
		throw new RuntimeException( 'WU008 Scanner recovery: ' . $message );
	}
};
$read_raw = static function () use ( $wpdb, $option ) {
	$row = $wpdb->get_row(
		$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $option )
	);
	if ( null === $row && ! empty( $wpdb->last_error ) ) {
		throw new RuntimeException( 'Scanner recovery: DB read failed.' );
	}
	return $row ? $row->option_value : null;
};
$invalidate = static function () use ( $option ) {
	foreach ( array( $option, 'alloptions', 'notoptions' ) as $key ) {
		wp_cache_delete( $key, 'options' );
	}
};
$initial_raw = $read_raw();
$initial     = null === $initial_raw ? null : maybe_unserialize( $initial_raw );
$assert( null === $initial || (
	is_array( $initial ) && ( $initial['schema_version'] ?? null ) === 2 &&
	isset( $initial['profiles'] ) && array() === $initial['profiles']
), 'refuse to stage migration over pre-existing nonempty or invalid Scanner data' );
$report_dir = $artifact . '/scanner-registry-recovery-evidence';
$assert( ! file_exists( $report_dir ), 'recovery evidence already exists' );
$assert( mkdir( $report_dir, 0700 ), 'cannot create recovery evidence directory' );

$profile = PGR_Scanner_Profile_Registry::validate_profile(
	array(
		'id'      => 'wu008_recovery',
		'label'   => 'Legacy recoverable / قدیمی',
		'enabled' => true,
		'parser'  => array(
			'type'             => 'segments_v1',
			'separator'        => 'newline',
			'trim'             => true,
			'normalize_digits' => true,
		),
		'outputs' => array(
			array( 'key' => 'serial', 'label' => 'Serial', 'required' => true ),
		),
	)
);
$assert( $profile['valid'], 'legacy fixture must be valid' );
$legacy = array(
	'schema_version' => 1,
	'profiles'       => array( $profile['profile'] ),
);

$form_id = null;
$failed_once = false;
$query_filter = null;
$checks = array();
try {
	// This is test-only seed data; the production candidate never auto-migrates.
	if ( null === $initial_raw ) {
		$assert( add_option( $option, $legacy, '', false ), 'could not seed original v1 option' );
	} else {
		$assert( update_option( $option, $legacy, false ), 'could not seed original v1 option' );
	}
	$seed_raw = $read_raw();
	$assert( is_string( $seed_raw ) && maybe_unserialize( $seed_raw ) === $legacy, 'v1 seed differs in MariaDB' );
	$backup = PGR_Scanner_Profile_Registry::pre_migration_backup();
	$assert( is_array( $backup ) && $seed_raw === $backup['raw'] &&
		hash_equals( hash( 'sha256', $seed_raw ), $backup['sha256'] ), 'raw v1 backup mismatch' );
	$checks['verified_raw_backup'] = true;

	$created = PGR_Scanner_Profile_Registry::save_custom_profile( array(
		'id' => 'blocked_until_migration', 'label' => 'Blocked', 'enabled' => true,
		'parser' => $profile['profile']['parser'], 'outputs' => $profile['profile']['outputs'],
	) );
	$assert( ! $created['success'] && $read_raw() === $seed_raw, 'unmigrated v1 write was not blocked' );
	$checks['no_automatic_migration'] = true;

	$form_id = GFAPI::add_form(
		array(
			'title'  => 'WU008 Scanner recovery migration form',
			'fields' => array(
				array(
					'id' => 1, 'label' => 'Scanner', 'type' => 'pgr_structured_scanner',
					'scanner_profile' => 'wu008_recovery',
					'scanner_mappings' => array( 'serial' => '2' ),
				),
				array( 'id' => 2, 'label' => 'Destination', 'type' => 'text' ),
			),
			'button' => array( 'type' => 'text', 'text' => 'Submit' ),
		)
	);
	$assert( ! is_wp_error( $form_id ) && is_numeric( $form_id ), 'GFAPI form creation failed' );
	$form = GFAPI::get_form( $form_id );
	$assert( is_array( $form ), 'GFAPI form load failed' );
	$mapping_before = wp_json_encode( $form['fields'][0] );

	$invalid_hash = PGR_Scanner_Profile_Registry::migrate_v1_to_v2( str_repeat( '0', 64 ) );
	$assert( ! $invalid_hash['success'] && $read_raw() === $seed_raw, 'incorrect backup hash changed v1' );
	$checks['wrong_backup_hash_rejected'] = true;

	// Intercept only the candidate's SQL UPDATE using WordPress' actual wpdb
	// query filter. Returning false exercises a real wpdb write failure.
	$query_filter = static function ( $query ) use ( $wpdb, $option, &$failed_once ) {
		if ( ! $failed_once && str_starts_with( trim( $query ), "UPDATE {$wpdb->options} SET option_value" )
			&& str_contains( $query, $option ) ) {
			$failed_once = true;
			return false;
		}
		return $query;
	};
	add_filter( 'query', $query_filter, 10, 1 );
	$failure = PGR_Scanner_Profile_Registry::migrate_v1_to_v2( $backup['sha256'] );
	remove_filter( 'query', $query_filter, 10 );
	$query_filter = null;
	$assert( $failed_once && ! $failure['success'] &&
		$read_raw() === $seed_raw && get_option( $option ) === $legacy,
		'failed wpdb SQL was reported as success or changed v1/cache' );
	$checks['real_wpdb_query_failure_preserved_v1'] = true;

	$migration = PGR_Scanner_Profile_Registry::migrate_v1_to_v2( $backup['sha256'] );
	$assert( $migration['success'] && 'migrated' === $migration['reason'], 'retry migration did not succeed' );
	$v2 = get_option( $option );
	$assert( is_array( $v2 ) && ( $v2['schema_version'] ?? null ) === 2 &&
		count( $v2['profiles'] ) === 1 &&
		isset( $v2['profiles'][0]['incarnation_id'], $v2['profiles'][0]['revision'] ) &&
		1 === $v2['profiles'][0]['revision'],
		'new v2 option or cache not visible' );
	$got = PGR_Scanner_Profile_Registry::get( 'wu008_recovery' );
	$assert( is_array( $got ) && 'wu008_recovery' === $got['id'] &&
		$got['parser'] === $profile['profile']['parser'] &&
		$got['outputs'] === $profile['profile']['outputs'] &&
		$got['label'] === $profile['profile']['label'], 'legacy fields changed during migration' );
	$form = GFAPI::get_form( $form_id );
	$assert( is_array( $form ) && $mapping_before === wp_json_encode( $form['fields'][0] ),
		'GFAPI mapping changed during migration' );
	$checks['lossless_v2_migration_gfapi_and_cache_readback'] = true;

	$repeat = PGR_Scanner_Profile_Registry::migrate_v1_to_v2( $backup['sha256'] );
	$assert( $repeat['success'] && 'already_v2' === $repeat['reason'] &&
		$got['incarnation_id'] === PGR_Scanner_Profile_Registry::get( 'wu008_recovery' )['incarnation_id'],
		'idempotent repeat migration replaced original identity' );
	$checks['repeated_migration_idempotent'] = true;

	$post = array(
		'id'      => 'wu008_new_after_backup',
		'label'   => 'Post backup profile',
		'enabled' => true,
		'parser'  => $profile['profile']['parser'],
		'outputs' => $profile['profile']['outputs'],
	);
	$new = PGR_Scanner_Profile_Registry::save_custom_profile( $post );
	$assert( $new['success'], 'post-backup write could not be created' );
	$assert( null !== PGR_Scanner_Profile_Registry::get( 'wu008_new_after_backup' ),
		'post-backup profile absent before recovery simulation' );

	// *Disposable-only* pre-migration raw backup restoration. This is not a
	// general v2->v1 downgrade API, nor a preservation of post-backup writes.
	$restored = $wpdb->update(
		$wpdb->options,
		array( 'option_value' => $backup['raw'] ),
		array( 'option_name' => $option ),
		array( '%s' ),
		array( '%s' )
	);
	$invalidate();
	$assert( 1 === $restored && $read_raw() === $seed_raw &&
		null === PGR_Scanner_Profile_Registry::get( 'wu008_new_after_backup' ) &&
		null !== PGR_Scanner_Profile_Registry::get( 'wu008_recovery' ),
		'raw recovery fixture did not restore legacy bytes or expose post-backup loss' );
	$checks['raw_backup_restored_with_post_backup_changes_lost'] = true;

	$retry = PGR_Scanner_Profile_Registry::migrate_v1_to_v2( $backup['sha256'] );
	$assert( $retry['success'] && null !== PGR_Scanner_Profile_Registry::get( 'wu008_recovery' ),
		'backup restoration cannot be remigrated' );
	$checks['restored_backup_can_remigrate'] = true;
} finally {
	if ( null !== $query_filter ) {
		remove_filter( 'query', $query_filter, 10 );
	}
	if ( null !== $form_id && ! is_wp_error( $form_id ) ) {
		GFAPI::delete_form( $form_id );
	}
	if ( null === $initial_raw ) {
		$wpdb->delete( $wpdb->options, array( 'option_name' => $option ), array( '%s' ) );
	} else {
		$wpdb->update( $wpdb->options, array( 'option_value' => $initial_raw ),
			array( 'option_name' => $option ), array( '%s' ), array( '%s' ) );
	}
	$invalidate();
}
$assert( $read_raw() === $initial_raw, 'initial Registry bytes were not restored after recovery test' );
$checks['original_test_registry_restored_byte_exact'] = true;
$output = array(
	'evidence_class'                 => 'WU008_REAL_WPDB_MIGRATION_AND_RECOVERY',
	'exact_head'                     => $head,
	'wordpress'                      => get_bloginfo( 'version' ),
	'checks'                         => $checks,
	'matched_version_plugin_restore' => 'NOT_PROVEN',
	'production_restore'             => 'NOT_ATTEMPTED',
	'persistent_cache'               => 'NOT_PROVEN',
	'backup_contents_in_artifact'     => false,
);
$written = file_put_contents(
	$report_dir . '/report.json',
	wp_json_encode( $output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . "\n"
);
$assert( false !== $written, 'cannot write recovery report' );
echo 'WU008_SCANNER_RECOVERY_PASS ' . implode( ',', array_keys( $checks ) ) . "\n";
