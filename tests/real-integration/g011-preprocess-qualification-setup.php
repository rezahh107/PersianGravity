<?php
/**
 * Exact-host G-011 pre-process setup and bounded source-lifecycle inventory.
 * Test-only, run under the disposable WU008 WP-CLI environment.
 */
defined( 'ABSPATH' ) || exit;
$dir = getenv( 'WU008_ARTIFACT_DIR' );
$manifest = json_decode( (string) file_get_contents( $dir . '/g011-confirmation.json' ), true );
if (
	! is_array( $manifest ) ||
	'3.1.1.1' !== GFForms::$version ||
	getenv( 'WU008_PGR_SHA' ) !== ( $manifest['head'] ?? null )
) {
	throw new RuntimeException( 'Exact G-011 host/Head mismatch.' );
}
$form = GFAPI::get_form( (int) $manifest['form_id'] );
if ( ! is_array( $form ) || ! is_array( $form['confirmations'] ?? null ) ) {
	throw new RuntimeException( 'No authentic persisted Form.' );
}
$needle = 'Created={date_created:format:Y/m/d}';
$found = false;
foreach ( $form['confirmations'] as $conf ) {
	if ( 'message' === ( $conf['type'] ?? null ) && false !== strpos( (string) ( $conf['message'] ?? '' ), $needle ) ) {
		$found = true;
		break;
	}
}
if ( ! $found ) {
	throw new RuntimeException( 'Selected browser fixture lacks exact human date tag.' );
}
$vendor = WP_PLUGIN_DIR . '/gravityforms/';
$hits = array();
$iter = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $vendor, FilesystemIterator::SKIP_DOTS ) );
foreach ( $iter as $file ) {
	if ( ! $file->isFile() || 'php' !== pathinfo( $file->getFilename(), PATHINFO_EXTENSION ) ) {
		continue;
	}
	$source = file_get_contents( $file->getPathname() );
	if ( false === $source || false === strpos( $source, 'gform_pre_process' ) ) {
		continue;
	}
	$lines = explode( "\n", $source );
	foreach ( $lines as $index => $line ) {
		if ( strpos( $line, 'gform_pre_process' ) !== false && ( strpos( $line, 'apply_filters' ) !== false || strpos( $line, 'gf_apply_filters' ) !== false ) ) {
			$hits[] = array( 'file' => str_replace( $vendor, '', $file->getPathname() ), 'line' => $index + 1,
				'call_site_sha256' => hash( 'sha256', trim( $line ) ) );
		}
	}
}
if ( empty( $hits ) ) {
	throw new RuntimeException( 'No exact GF gform_pre_process native filter call site observed.' );
}
$pre_state = get_option( 'pgr_modules' );
if ( ! is_array( $pre_state ) || ! isset( $pre_state['states'] ) ) {
	throw new RuntimeException( 'Cannot isolate module state.' );
}
update_option( 'pgr_g011_preprocess_probe_saved_state', $pre_state, false );
$state = $pre_state;
$state['states']['jalali_presentation'] = false; // isolate native callbacks.
update_option( 'pgr_modules', $state );
$marker = 'PGR_G011_PREPROCESS_Q_OPAQUE_6EFC42_END';
update_option( 'pgr_g011_preprocess_probe_active', array(
	'form_id' => (int) $form['id'], 'marker' => $marker ), false );
update_option( 'pgr_g011_preprocess_probe_events', array(), false );
update_option( 'pgr_g011_preprocess_probe_baseline', array(
	'form_id' => (int) $form['id'],
	'confirmations_hash' => hash( 'sha256', wp_json_encode( $form['confirmations'] ) ),
	'form_hash' => hash( 'sha256', wp_json_encode( $form ) ),
	'preprocess_calls' => $hits,
	'head' => getenv( 'WU008_PGR_SHA' ),
	'host_version' => GFForms::$version,
	'host_sha256' => getenv( 'WU008_GF_SHA256' ),
), false );
$source = __DIR__ . '/g011-preprocess-mu-qualification.php';
$target = WP_CONTENT_DIR . '/mu-plugins/pgr-g011-preprocess-qual.php';
if ( ! is_dir( dirname( $target ) ) && ! wp_mkdir_p( dirname( $target ) ) ) {
	throw new RuntimeException( 'Cannot create disposable MU directory.' );
}
if ( ! copy( $source, $target ) ) {
	throw new RuntimeException( 'Cannot install disposable MU fixture.' );
}
echo "G011 pre_process source/fixture prepared on exact licensed host\n";
