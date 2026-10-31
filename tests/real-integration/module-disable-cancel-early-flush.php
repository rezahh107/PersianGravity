<?php
/**
 * WU008 disposable early-header-flush probe.
 *
 * Install/remove only within the pinned, disposable WU008 WordPress root.
 * With the old render-time Cancel, admin_head flushes headers before it tries
 * wp_safe_redirect(). With the repaired load-hook Cancel, admin_head never runs.
 */
defined( 'ABSPATH' ) || exit;

$root  = getenv( 'WU008_WP_PATH' );
$phase = getenv( 'PGR_CANCEL_FLUSH_PHASE' );
if ( ! $root || realpath( ABSPATH ) !== realpath( $root ) ||
	! in_array( $phase, array( 'install', 'remove' ), true ) ) {
	throw new RuntimeException( 'Refusing Cancel header-flush fixture outside disposable WU008.' );
}
$directory = WP_CONTENT_DIR . '/mu-plugins';
$file      = $directory . '/pgr-wu008-cancel-header-flush.php';
if ( 'remove' === $phase ) {
	if ( is_file( $file ) ) {
		unlink( $file );
	}
	echo 'WU008_CANCEL_FLUSH_GUARD_REMOVED';
	return;
}
if ( is_file( $file ) ) {
	throw new RuntimeException( 'Refusing to overwrite an existing MU guard.' );
}
if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
	throw new RuntimeException( 'Unable to create disposable MU plugin directory.' );
}
$guard = <<<'PHP'
<?php
/*
 * Disposable WU008-only output-flush instrumentation.
 * Triggers on the tested Overview cancel URL and on an explicit probe URL.
 * Deliberately sends headers while admin_head is running.
 */
add_action( 'admin_head', static function () {
	if ( ! isset( $_GET['page'] ) ||
		'persian-gravityforms' !== $_GET['page'] ||
		( ! isset( $_GET['pgr_cancel_confirm'] ) && ! isset( $_GET['pgr_wu008_probe_flush'] ) ) ) {
		return;
	}
	echo '<span id="pgr-wu008-header-flush-probe">WU008_HEADERS_FLUSHED</span>';
	while ( ob_get_level() > 0 ) {
		ob_end_flush();
	}
	flush();
}, 1 );
PHP;
if ( false === file_put_contents( $file, $guard ) ) {
	throw new RuntimeException( 'Failed to install WU008 Cancel flush guard.' );
}
echo 'WU008_CANCEL_FLUSH_GUARD_INSTALLED';
