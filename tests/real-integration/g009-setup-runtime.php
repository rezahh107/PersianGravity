<?php
/**
 * Extend the existing WU008 runtime with G-009 qualification fixtures.
 *
 * This script is test-only. It does not register PersianGravity production hooks.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$artifact_dir  = getenv( 'WU008_ARTIFACT_DIR' );
$manifest_path = getenv( 'WU008_MANIFEST_PATH' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir || ! is_string( $manifest_path ) || '' === $manifest_path ) {
	throw new RuntimeException( 'WU008 artifact and manifest paths are required.' );
}

$manifest = json_decode( (string) file_get_contents( $manifest_path ), true, 512, JSON_THROW_ON_ERROR );
if ( ! is_array( $manifest ) ) {
	throw new RuntimeException( 'WU008 runtime manifest is invalid.' );
}

$flow_page_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'WU008 Gravity Flow Frontend Inbox',
		'post_name'    => 'wu008-gravityflow-inbox',
		'post_content' => '[gravityflow page="inbox" due_date="true"]',
	),
	true
);
if ( is_wp_error( $flow_page_id ) ) {
	throw new RuntimeException( $flow_page_id->get_error_message() );
}

$manifest['schema_version']                 = '1.2.0';
$manifest['gravityflow_frontend_inbox_url'] = add_query_arg( 'page_id', (int) $flow_page_id, home_url( '/' ) );
$manifest['gravityflow_frontend_page_id']   = (int) $flow_page_id;
$manifest['package_sha256']                 = array(
	'gravityforms'  => (string) getenv( 'WU008_GF_SHA256' ),
	'gravityflow'   => (string) getenv( 'WU008_FLOW_SHA256' ),
	'gravityview'   => (string) getenv( 'WU008_VIEW_SHA256' ),
	'persiangravity' => (string) getenv( 'WU008_PGR_PACKAGE_SHA256' ),
);
$manifest['persiangravity_source_commit']   = (string) getenv( 'WU008_PGR_SHA' );
$manifest['persiangravity_source_tree']     = (string) getenv( 'WU008_PGR_TREE' );

foreach ( $manifest['package_sha256'] as $product => $sha256 ) {
	if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $sha256 ) ) {
		throw new RuntimeException( 'Missing or invalid exact package SHA-256 for ' . $product . '.' );
	}
}
if ( 1 !== preg_match( '/^[a-f0-9]{40}$/', $manifest['persiangravity_source_commit'] ) ) {
	throw new RuntimeException( 'Current PersianGravity source commit is not bound in the manifest.' );
}
if ( 1 !== preg_match( '/^[a-f0-9]{40}$/', $manifest['persiangravity_source_tree'] ) ) {
	throw new RuntimeException( 'Current PersianGravity source tree is not bound in the manifest.' );
}

file_put_contents(
	$manifest_path,
	wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);

echo wp_json_encode(
	array(
		'gravityflow_frontend_inbox_url' => $manifest['gravityflow_frontend_inbox_url'],
		'persiangravity_source_commit'   => $manifest['persiangravity_source_commit'],
		'persiangravity_source_tree'     => $manifest['persiangravity_source_tree'],
	),
	JSON_UNESCAPED_SLASHES
) . "\n";
