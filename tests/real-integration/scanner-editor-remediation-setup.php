<?php
/**
 * Deterministic real-browser fixture for the Structured Scanner Form Editor fix.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$artifact_dir = getenv( 'PGR_SCANNER_ARTIFACT_DIR' );
$expected_sha = getenv( 'PGR_REMEDIATION_SHA' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir || ! is_string( $expected_sha ) || '' === $expected_sha ) {
	throw new RuntimeException( 'PGR_SCANNER_ARTIFACT_DIR and PGR_REMEDIATION_SHA are required.' );
}
wp_mkdir_p( $artifact_dir );

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$assert( 'fa_IR' === get_locale(), 'WordPress site locale must be fa_IR.' );
$assert( 'fa_IR' === determine_locale(), 'Effective runtime locale must be fa_IR.' );
$assert( class_exists( 'GFAPI' ), 'Gravity Forms runtime API is unavailable.' );
$assert( class_exists( 'PGR_GF_Field_Structured_Scanner' ), 'Structured Scanner field class is unavailable.' );
$assert( defined( 'PGR_PATH' ), 'PersianGravity runtime path is unavailable.' );

$gf_data = get_file_data( WP_PLUGIN_DIR . '/gravityforms/gravityforms.php', array( 'Version' => 'Version' ) );
$assert( isset( $gf_data['Version'] ) && '3.1.1.1' === (string) $gf_data['Version'], 'Gravity Forms runtime version mismatch.' );

$git_output = array();
$git_status = 0;
exec( 'git -C ' . escapeshellarg( rtrim( PGR_PATH, '/' ) ) . ' rev-parse HEAD 2>/dev/null', $git_output, $git_status );
$actual_sha = 0 === $git_status && ! empty( $git_output ) ? trim( (string) $git_output[0] ) : '';
$assert( $actual_sha === $expected_sha, 'Installed PersianGravity git SHA mismatch.' );

$form = array(
	'title'       => 'Scanner Editor Remediation',
	'description' => 'Focused GF 3.1.1.1 browser fixture for Structured Scanner editor integration.',
	'fields'      => array(
		array( 'id' => 1, 'label' => 'Mapping Target', 'type' => 'text' ),
		array( 'id' => 2, 'label' => 'Runtime Email', 'type' => 'email' ),
		array( 'id' => 3, 'label' => 'Runtime Notes', 'type' => 'textarea' ),
		array(
			'id'               => 4,
			'label'            => 'Structured Scanner',
			'type'             => 'pgr_structured_scanner',
			'scanner_profile'  => 'sayad_v01',
			'scanner_mappings' => array(),
		),
	),
	'button'      => array( 'type' => 'text', 'text' => 'Submit' ),
);
$form_id = GFAPI::add_form( $form );
if ( is_wp_error( $form_id ) ) {
	throw new RuntimeException( $form_id->get_error_message() );
}
$form_id = (int) $form_id;
$stored  = GFAPI::get_form( $form_id );
$assert( is_array( $stored ), 'Created form could not be read back.' );

$scanner = null;
foreach ( (array) rgar( $stored, 'fields' ) as $field ) {
	if ( is_object( $field ) && 'pgr_structured_scanner' === $field->type ) {
		$scanner = $field;
		break;
	}
}
$assert( is_object( $scanner ), 'Structured Scanner fixture field was not materialized by Gravity Forms.' );
$assert( 'sayad_v01' === $scanner->scanner_profile, 'Structured Scanner fixture profile mismatch.' );

$manifest = array(
	'form_id'                    => $form_id,
	'scanner_field_id'           => (int) $scanner->id,
	'mapping_target_field_id'    => 1,
	'gravityforms_version'       => (string) $gf_data['Version'],
	'wordpress_version'          => get_bloginfo( 'version' ),
	'locale'                     => determine_locale(),
	'rtl'                        => is_rtl(),
	'remediation_sha_expected'   => $expected_sha,
	'remediation_sha_installed'  => $actual_sha,
	'form_builder_url'           => admin_url( 'admin.php?page=gf_edit_forms&id=' . $form_id . '&pgr_scanner_remediation=1' ),
	'login_url'                  => wp_login_url( admin_url() ),
	'provider_identity'          => array(
		'msgid'       => 'Send To Email Address',
		'translation' => 'ارسال به نشانی ایمیل',
	),
);
file_put_contents(
	rtrim( $artifact_dir, '/' ) . '/runtime-manifest.json',
	wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);

echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
