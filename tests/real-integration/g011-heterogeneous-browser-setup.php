<?php
/**
 * Test-only genuine browser pages, reusing original heterogeneous GF Form.
 * Never modifies form fields, confirmation markup or Entry definitions.
 */
defined( 'ABSPATH' ) || exit;
$dir = getenv( 'WU008_ARTIFACT_DIR' );
$host = json_decode( (string) file_get_contents( $dir . '/g011-heterogeneous-method.json' ), true );
$native = json_decode( (string) file_get_contents( $dir . '/g011-heterogeneous-native-host.json' ), true );
if ( 'PASS' !== ( $host['status'] ?? null ) ||
	'PASS' !== ( $native['status'] ?? null ) ||
	GFForms::$version !== '3.1.1.1' ||
	getenv( 'WU008_GF_SHA256' ) !== $native['GF_SHA256'] ) {
	throw new RuntimeException( 'G011 heterogeneous browser: exact-host qualification must pass first' );
}
$id = (int) $native['form_id'];
$form = GFAPI::get_form( $id );
$confirmations = array_values( $form['confirmations'] ?? array() );
$ids = array_column( $confirmations, 'id' );
if ( count( $confirmations ) !== 2 ||
	! in_array( 'g011-het-default', $ids, true ) ||
	! in_array( 'g011-het-conditional', $ids, true ) ) {
	throw new RuntimeException( 'G011 heterogeneous browser: selected source form contract drift' );
}
update_option( 'wu008_g011_het_browser_form_id', $id, false );
$urls = array();
foreach ( array( 'ordinary' => false, 'ajax' => true ) as $name => $ajax ) {
	$page_id = wp_insert_post( array(
		'post_title' => 'G011 Heterogeneous ' . $name,
		'post_type' => 'page',
		'post_status' => 'publish',
		'post_content' => sprintf(
			'[gravityform id="%d" title="false" description="false" ajax="%s"]',
			$id, $ajax ? 'true' : 'false'
		),
	) );
	if ( ! is_int( $page_id ) || $page_id <= 0 ) {
		throw new RuntimeException( 'G011 heterogeneous browser: cannot create page' );
	}
	$urls[$name] = get_permalink( $page_id );
}
file_put_contents( $dir . '/g011-heterogeneous-browser-fixture.json',
	wp_json_encode( array(
		'head' => getenv( 'WU008_PGR_SHA' ),
		'host' => GFForms::$version,
		'gf_sha256' => getenv( 'WU008_GF_SHA256' ),
		'form_id' => $id,
		'confirmation_ids' => $ids,
		'urls' => $urls,
		'field_input' => '03/21/2026',
		'form_confirmations_sha256' => hash( 'sha256', wp_json_encode( $form['confirmations'] ) ),
	), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
echo "G011 heterogeneous genuine-browser fixtures ready\n";