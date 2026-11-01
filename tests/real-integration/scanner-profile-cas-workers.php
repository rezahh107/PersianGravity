<?php
/**
 * WU008: two independent real WP-CLI/PHP worker processes in MariaDB.
 *
 * Never run outside the disposable WU008 WordPress root. This verifies an
 * actual concurrently suspended PHP process (A) while independent worker B
 * replaces A's profile, then observes the conditional stale-write rejection.
 * It does NOT prove persistent Redis/object-cache coherence.
 */
defined( 'ABSPATH' ) || exit;

$root     = getenv( 'WU008_WP_PATH' );
$artifact = getenv( 'WU008_ARTIFACT_DIR' );
$role     = getenv( 'WU008_CAS_ROLE' );
if ( ! $root || ! $artifact || realpath( $root ) !== realpath( ABSPATH ) ||
	! in_array( $role, array( 'setup', 'a', 'b', 'verify' ), true ) ||
	! class_exists( 'PGR_Scanner_Profile_Registry' ) ) {
	throw new RuntimeException( 'WU008 CAS worker requires an isolated runtime and authorized role.' );
}

$evidence_dir = getenv( 'WU008_CAS_EVIDENCE_SUBDIR' ) ?: 'scanner-cas-worker-evidence';
if ( ! in_array( $evidence_dir, array( 'scanner-cas-worker-evidence', 'scanner-redis-cas-worker-evidence' ), true ) ) {
	throw new RuntimeException( 'WU008 CAS evidence location is not an approved test-only directory.' );
}
$using_redis = 'scanner-redis-cas-worker-evidence' === $evidence_dir;
if ( $using_redis && ! wp_using_ext_object_cache() ) {
	throw new RuntimeException( 'WU008 Redis case cannot use non-persistent WP cache.' );
}
$dir = $artifact . '/' . $evidence_dir;
$id  = 'alpha';
// The marker is a process barrier, not merely a file-existence test.
// Publish complete bytes through an atomic rename so worker B can never
// observe an empty, partially-written PID from worker A.
$wait_for = static function ( $filename ) {
	$end = microtime( true ) + 25;
	while ( microtime( true ) < $end ) {
		clearstatcache( true, $filename );
		if ( is_file( $filename ) ) {
			$value = file_get_contents( $filename );
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				return $value;
			}
		}
		usleep( 25000 );
	}
	throw new RuntimeException( 'CAS worker barrier missing complete marker: ' . basename( $filename ) );
};
$must_write = static function ( $filename, $value ) {
	$tmp = $filename . '.pending-' . getmypid() . '-' . bin2hex( random_bytes( 4 ) );
	if ( ! is_string( $value ) || '' === trim( $value ) ||
		strlen( $value ) !== file_put_contents( $tmp, $value, LOCK_EX ) ||
		! rename( $tmp, $filename ) ) {
		@unlink( $tmp );
		throw new RuntimeException( 'CAS worker cannot atomically publish evidence marker.' );
	}
};
$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( 'WU008 real-worker CAS proof: ' . $message );
	}
};
$before_file = $dir . '/old-fingerprint';
$ready_file  = $dir . '/a-ready';
$done_file   = $dir . '/b-done';
$a_file      = $dir . '/a-result';

if ( 'setup' === $role ) {
	$assert( ! is_dir( $dir ) && null === PGR_Scanner_Profile_Registry::get( $id ), 'fixture already exists' );
	if ( ! mkdir( $dir, 0700 ) ) {
		throw new RuntimeException( 'Cannot create isolated CAS evidence directory.' );
	}
	$a = array(
		'id'      => $id,
		'label'   => 'Alpha',
		'enabled' => true,
		'parser'  => array(
			'type' => 'segments_v1',
			'separator' => 'newline',
			'trim' => true,
			'normalize_digits' => true,
		),
		'outputs' => array( array( 'key' => 'original', 'label' => 'Original', 'required' => true ) ),
	);
	$result = PGR_Scanner_Profile_Registry::save_custom_profile( $a );
	$assert( $result['success'], 'cannot create real A in MariaDB' );
	$fingerprint = PGR_Scanner_Profile_Registry::snapshot_fingerprint( PGR_Scanner_Profile_Registry::get( $id ) );
	$assert( 1 === preg_match( '/^[a-f0-9]{64}$/', $fingerprint ), 'missing unique A snapshot' );
	if ( $using_redis ) {
		$assert( wp_cache_set( 'wu008_cross_process_redis', $fingerprint, 'options', 180 ),
			'cross-process Redis sentinel cannot be saved' );
		$assert( is_array( get_option( 'pgr_scanner_profiles' ) ), 'get_option cannot read warmed Registry' );
	}
	$must_write( $before_file, $fingerprint );
	return;
}

$fp = trim( $wait_for( $before_file ) );
$assert( 1 === preg_match( '/^[a-f0-9]{64}$/', $fp ), 'invalid initial fingerprint' );
if ( $using_redis ) {
	$assert( $fp === wp_cache_get( 'wu008_cross_process_redis', 'options' ),
		'persisted Redis sentinel was not shared between real WP-CLI PHP processes' );
}

if ( 'a' === $role ) {
	$hook_seen = false;
	add_action( 'update_option', static function ( $option ) use ( $id, $ready_file, $done_file, $wait_for, $assert, $must_write, &$hook_seen ) {
		if ( 'pgr_scanner_profiles' !== $option ) {
			return;
		}
		$in_delete = false;
		foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 14 ) as $frame ) {
			if ( 'PGR_Scanner_Profile_Registry' === ( $frame['class'] ?? '' ) &&
				'delete_custom_profile' === ( $frame['function'] ?? '' ) ) {
				$in_delete = true;
				break;
			}
		}
		if ( ! $in_delete ) {
			return;
		}
		$hook_seen = true;
		$must_write( $ready_file, (string) getmypid() );
		$other_pid = (int) trim( $wait_for( $done_file ) );
		$assert( $other_pid > 0 && $other_pid !== getmypid(), 'no independent concurrent B PHP worker' );
	}, 10, 1 );
	$deleted = PGR_Scanner_Profile_Registry::delete_custom_profile( $id, $fp );
	$after   = PGR_Scanner_Profile_Registry::get( $id );
	$assert( $hook_seen && ! $deleted && is_array( $after ) &&
		'replacement' === $after['outputs'][0]['key'], 'stale A write deleted or changed B' );
	$must_write( $a_file, wp_json_encode( array(
		'worker_a_pid' => getmypid(),
		'stale_delete_rejected' => true,
		'replacement_preserved' => true,
	) ) );
	return;
}

if ( 'b' === $role ) {
	$a_pid = (int) trim( $wait_for( $ready_file ) );
	$assert( $a_pid > 0 && $a_pid !== getmypid(), 'worker A not concurrently alive' );
	$deleted = PGR_Scanner_Profile_Registry::delete_custom_profile( $id, $fp );
	$assert( $deleted && null === PGR_Scanner_Profile_Registry::get( $id ), 'worker B could not delete original A' );
	$b = array(
		'id'      => $id,
		'label'   => 'Alpha',
		'enabled' => true,
		'parser'  => array(
			'type' => 'segments_v1',
			'separator' => 'newline',
			'trim' => false,
			'normalize_digits' => false,
		),
		'outputs' => array( array( 'key' => 'replacement', 'label' => 'Replacement', 'required' => true ) ),
	);
	$created = PGR_Scanner_Profile_Registry::save_custom_profile( $b );
	$assert( $created['success'] &&
		'replacement' === PGR_Scanner_Profile_Registry::get( $id )['outputs'][0]['key'], 'B replacement not persisted' );
	$must_write( $done_file, (string) getmypid() );
	return;
}

$a = json_decode( $wait_for( $a_file ), true, 512, JSON_THROW_ON_ERROR );
$b_pid = (int) trim( $wait_for( $done_file ) );
$assert( $a['worker_a_pid'] > 0 && $b_pid > 0 && $a['worker_a_pid'] !== $b_pid &&
	$a['stale_delete_rejected'] && $a['replacement_preserved'], 'workers were not independently proven' );
$current = PGR_Scanner_Profile_Registry::get( $id );
$assert( is_array( $current ) && 'replacement' === $current['outputs'][0]['key'] &&
	$fp !== PGR_Scanner_Profile_Registry::snapshot_fingerprint( $current ), 'replacement not intact in third PHP process' );
if ( $using_redis ) {
	$cached = get_option( 'pgr_scanner_profiles' );
	$assert( is_array( $cached ) && ( $cached['schema_version'] ?? null ) === 2 &&
		count( $cached['profiles'] ) === 1 &&
		'replacement' === $cached['profiles'][0]['outputs'][0]['key'],
		'actual Redis-backed get_option returned stale Registry after B CAS' );
}
$report = array(
	'evidence_class' => 'WU008_REAL_INDEPENDENT_PHP_WORKERS_MARIADB_CAS',
	'old_confirmation' => 'rejected',
	'new_incarnation' => 'preserved',
	'worker_a_pid' => $a['worker_a_pid'],
	'worker_b_pid' => $b_pid,
	'workers_distinct' => true,
	'worker_a_suspended_during_b_write' => true,
	'persistent_object_cache' => $using_redis ? 'REDIS_2_8_0_CROSS_PROCESS_PROVEN' : 'NOT_PROVEN',
	'exact_head' => getenv( 'WU008_PGR_SHA' ),
	'fresh_get_option_reflects_B' => $using_redis,
);
$must_write( $dir . '/report.json', wp_json_encode( $report, JSON_PRETTY_PRINT ) . "\n" );
$assert( PGR_Scanner_Profile_Registry::delete_custom_profile(
	$id,
	PGR_Scanner_Profile_Registry::snapshot_fingerprint( $current )
) && null === PGR_Scanner_Profile_Registry::get( $id ), 'CAS workers cleanup failed' );
if ( $using_redis ) { wp_cache_delete( 'wu008_cross_process_redis', 'options' ); }
