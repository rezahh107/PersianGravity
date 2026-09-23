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

function wu008_g009_plugin_version( $relative_main_file ) {
	$path = WP_PLUGIN_DIR . '/' . $relative_main_file;
	if ( ! is_readable( $path ) ) {
		throw new RuntimeException( 'Missing plugin main file: ' . $relative_main_file );
	}
	$data = get_file_data( $path, array( 'Version' => 'Version' ) );
	return isset( $data['Version'] ) ? (string) $data['Version'] : '';
}

$expected_versions = array(
	'gravityperks'       => '2.3.16',
	'gp-file-upload-pro' => '1.5.13',
	'gp-advanced-select' => '1.1.21',
);
$main_files = array(
	'gravityperks'       => 'gravityperks/gravityperks.php',
	'gp-file-upload-pro' => 'gp-file-upload-pro/gp-file-upload-pro.php',
	'gp-advanced-select' => 'gp-advanced-select/gp-advanced-select.php',
);
foreach ( $expected_versions as $product => $expected_version ) {
	$actual_version = wu008_g009_plugin_version( $main_files[ $product ] );
	if ( $actual_version !== $expected_version ) {
		throw new RuntimeException( sprintf( '%s runtime version mismatch: expected %s, got %s.', $product, $expected_version, $actual_version ) );
	}
	$manifest['versions'][ $product ] = $actual_version;
}
if ( ! class_exists( 'GravityPerks' ) || ! class_exists( 'GP_File_Upload_Pro' ) || ! class_exists( 'GP_Advanced_Select' ) ) {
	throw new RuntimeException( 'Expected Gravity Perks family runtime classes are not active.' );
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

$perks_form = array(
	'title'       => 'WU008 Gravity Perks G009',
	'description' => 'Authentic File Upload Pro and Advanced Select browser fixture.',
	'fields'      => array(
		array(
			'id'             => 1,
			'label'          => 'Upload technical file',
			'type'           => 'fileupload',
			'multipleFiles'  => true,
			'gpfupEnable'    => true,
			'allowedExtensions' => 'txt,pdf,jpg,png',
		),
		array(
			'id'          => 2,
			'label'       => 'Advanced Select',
			'type'        => 'select',
			'gpadvsEnable' => true,
			'placeholder' => 'Choose a value',
			'choices'     => array(
				array( 'text' => 'Alpha ID-123', 'value' => 'alpha-id-123' ),
				array( 'text' => 'Beta user@example.invalid', 'value' => 'beta-email' ),
				array( 'text' => 'گزینه فارسی', 'value' => 'fa-option' ),
			),
		),
	),
	'button' => array(
		'type' => 'text',
		'text' => 'Submit',
	),
);
$perks_form_id = GFAPI::add_form( $perks_form );
if ( is_wp_error( $perks_form_id ) ) {
	throw new RuntimeException( $perks_form_id->get_error_message() );
}

$perks_page_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'WU008 Gravity Perks Frontend',
		'post_name'    => 'wu008-gravity-perks-frontend',
		'post_content' => sprintf( '[gravityform id="%d" title="false" description="false" ajax="false"]', (int) $perks_form_id ),
	),
	true
);
if ( is_wp_error( $perks_page_id ) ) {
	throw new RuntimeException( $perks_page_id->get_error_message() );
}

$manifest['schema_version']                    = '1.3.0';
$manifest['gravityflow_frontend_inbox_url']    = add_query_arg( 'page_id', (int) $flow_page_id, home_url( '/' ) );
$manifest['gravityflow_frontend_page_id']      = (int) $flow_page_id;
$manifest['gravityperks_frontend_url']          = add_query_arg( 'page_id', (int) $perks_page_id, home_url( '/' ) );
$manifest['gravityperks_frontend_page_id']      = (int) $perks_page_id;
$manifest['gravityperks_form_id']               = (int) $perks_form_id;
$manifest['gravityperks_file_upload_field_id']  = 1;
$manifest['gravityperks_advanced_select_field_id'] = 2;
$manifest['package_sha256']                    = array(
	'gravityforms'       => (string) getenv( 'WU008_GF_SHA256' ),
	'gravityflow'        => (string) getenv( 'WU008_FLOW_SHA256' ),
	'gravityview'        => (string) getenv( 'WU008_VIEW_SHA256' ),
	'gravityperks'       => (string) getenv( 'WU008_PERKS_SHA256' ),
	'gp-file-upload-pro' => (string) getenv( 'WU008_FUP_SHA256' ),
	'gp-advanced-select' => (string) getenv( 'WU008_ADVS_SHA256' ),
	'persiangravity'     => (string) getenv( 'WU008_PGR_PACKAGE_SHA256' ),
);
$manifest['persiangravity_source_commit']      = (string) getenv( 'WU008_PGR_SHA' );
$manifest['persiangravity_source_tree']        = (string) getenv( 'WU008_PGR_TREE' );

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
		'gravityperks_frontend_url'      => $manifest['gravityperks_frontend_url'],
		'gravityperks_form_id'           => $manifest['gravityperks_form_id'],
		'persiangravity_source_commit'   => $manifest['persiangravity_source_commit'],
		'persiangravity_source_tree'     => $manifest['persiangravity_source_tree'],
	),
	JSON_UNESCAPED_SLASHES
) . "\n";
