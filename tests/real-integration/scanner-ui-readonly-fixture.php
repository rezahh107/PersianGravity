<?php
/**
 * Read-only Scanner UI qualification: creates a disposable GF Form Builder fixture.
 * Never writes Scanner Registry data or submits a form.
 */
defined( 'ABSPATH' ) || exit;
$root = getenv( 'WU008_WP_PATH' );
$dir = getenv( 'WU008_ARTIFACT_DIR' );
if ( ! $root || ! $dir || realpath( ABSPATH ) !== realpath( $root ) ) {
	throw new RuntimeException( 'Refusing Scanner UI fixture outside disposable WU008 root.' );
}
if ( ! class_exists( 'GFAPI' ) || ! class_exists( 'PGR_GF_Field_Structured_Scanner' ) ) {
	throw new RuntimeException( 'Authentic licensed Gravity Forms/Scanner runtime unavailable.' );
}
if ( ! is_array( PGR_Scanner_Profile_Registry::get( 'wu008_ui_long_label' ) ) ) {
	throw new RuntimeException( 'Existing WU008 profile fixture missing; do not mutate Registry.' );
}
$registry_before = serialize( get_option( PGR_Scanner_Profile_Registry::OPTION ) );
$form = array(
	'title' => 'Read Only Scanner UI Geometry Fixture',
	'description' => 'Disposable real Gravity Forms Form Builder UI inspection; no submissions.',
	'fields' => array(
		array( 'id' => 1, 'label' => 'Destination Text', 'type' => 'text' ),
		array( 'id' => 2, 'label' => 'Destination Notes', 'type' => 'textarea' ),
		array(
			'id' => 3,
			'label' => 'Structured Scanner',
			'type' => 'pgr_structured_scanner',
			'scanner_profile' => 'sayad_v01',
			'scanner_mappings' => array(),
		),
	),
	'button' => array( 'type' => 'text', 'text' => 'Submit' ),
);
$id = GFAPI::add_form( $form );
if ( is_wp_error( $id ) || ! is_int( $id ) || $id <= 0 ) {
	throw new RuntimeException( 'Could not create disposable real Form Builder fixture.' );
}
$persisted = GFAPI::get_form( $id );
if ( ! is_array( $persisted ) || count( $persisted['fields'] ?? array() ) !== 3 ) {
	throw new RuntimeException( 'Native GFAPI did not persist expected Form Builder fixture.' );
}
if ( $registry_before !== serialize( get_option( PGR_Scanner_Profile_Registry::OPTION ) ) ) {
	throw new RuntimeException( 'Registry changed during GF fixture setup.' );
}
$installed = WP_PLUGIN_DIR . '/persian-gravityforms/';
$release_manifest = json_decode( file_get_contents( $installed . 'release-manifest.json' ), true, 512, JSON_THROW_ON_ERROR );
$expected = getenv( 'WU008_PGR_SHA' );
if ( ( $release_manifest['source_commit'] ?? null ) !== $expected ) {
	throw new RuntimeException( 'Installed release-manifest source identity does not match checked-out source.' );
}
$evidence = array(
	'evidence_class' => 'REAL_LICENSED_GF_BUILDER_READONLY_GEOMETRY_FIXTURE',
	'form_id' => $id,
	'scanner_field_id' => 3,
	'form_builder_url' => admin_url( 'admin.php?page=gf_edit_forms&id=' . $id ),
	'admin_url' => admin_url(),
	'login_url' => wp_login_url( admin_url() ),
	'expected_source_commit' => $expected,
	'installed_release_manifest' => $release_manifest,
	'installed_main_php_sha256' => hash_file( 'sha256', $installed . 'persian-gravityforms.php' ),
	'installed_scanner_css_sha256' => hash_file( 'sha256', $installed . 'assets/css/pgr-scanner-editor.css' ),
	'installed_scanner_runtime_css_sha256' => hash_file( 'sha256', $installed . 'assets/css/pgr-structured-scanner.css' ),
	'gf_plugin_main_sha256' => hash_file( 'sha256', WP_PLUGIN_DIR . '/gravityforms/gravityforms.php' ),
	'wordpress' => get_bloginfo( 'version' ),
	'gravityforms' => GFForms::$version,
	'php' => PHP_VERSION,
	'registry_unchanged' => true,
	'registry_snapshot_sha256' => hash( 'sha256', $registry_before ),
	'data' => 'GFAPI Form ID 1-3 fields and pre-existing WU008 isolated Scanner profile; no form submissions',
);
$evidence_dir = $dir . '/scanner-ui-readonly-evidence';
wp_mkdir_p( $evidence_dir );
file_put_contents( $evidence_dir . '/fixture.json', wp_json_encode( $evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
echo 'SCANNER_UI_FORM_FIXTURE ' . $id . "\n";
