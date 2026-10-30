<?php
/**
 * Synthetic Scanner Profile fixture for disposable WU008 UI verification only.
 * Never run against a production WordPress installation.
 */
defined( 'ABSPATH' ) || exit;
$root = getenv( 'WU008_WP_PATH' );
$dir  = getenv( 'WU008_ARTIFACT_DIR' );
if ( ! $root || ! $dir || realpath( ABSPATH ) !== realpath( $root ) ) {
	throw new RuntimeException( 'Refusing UI fixture outside the exact disposable WU008 WordPress root.' );
}
if ( ! class_exists( 'PGR_Scanner_Profile_Registry' ) || ! class_exists( 'GFAPI' ) ) {
	throw new RuntimeException( 'Actual PersianGravity and Gravity Forms runtime required.' );
}
$id = 'wu008_ui_long_label';
if ( null !== PGR_Scanner_Profile_Registry::get( $id ) ) {
	throw new RuntimeException( 'Synthetic fixture already exists; refusing to overwrite data.' );
}
$long = str_repeat( 'آزمایش نمایش متن طولانی و نام انگلیسی LongLabel_12345 / ', 5 );
$fixture = array(
	'id' => $id,
	'label' => $long,
	'kind' => 'custom',
	'enabled' => true,
	'read_only' => false,
	'parser' => array(
		'type' => 'segments_v1',
		'separator' => 'newline',
		'trim' => true,
		'normalize_digits' => true,
	),
	'outputs' => array(
		array( 'key' => 'long_label_field', 'label' => $long, 'required' => true ),
	),
);
$result = PGR_Scanner_Profile_Registry::save_custom_profile( $fixture );
if ( ! is_array( $result ) || empty( $result['success'] ) ) {
	throw new RuntimeException( 'Registry rejected the isolated long-label profile fixture.' );
}
$stored = PGR_Scanner_Profile_Registry::get( $id );
if ( ! is_array( $stored ) || $stored['label'] !== $long || count( $stored['outputs'] ) !== 1 ) {
	$raw_option = get_option( PGR_Scanner_Profile_Registry::OPTION, null );
	$info = array(
		'getter_returned' => is_array( $stored ),
		'getter_label_bytes' => is_array( $stored ) ? strlen( (string) $stored['label'] ) : null,
		'expected_label_bytes' => strlen( $long ),
		'getter_output_count' => is_array( $stored ) ? count( $stored['outputs'] ) : null,
		'raw_option_is_array' => is_array( $raw_option ),
		'raw_schema_version' => is_array( $raw_option ) ? ( $raw_option['schema_version'] ?? null ) : null,
		'raw_profiles_count' => is_array( $raw_option ) ? count( $raw_option['profiles'] ?? array() ) : null,
		'raw_first_profile_id' => is_array( $raw_option ) ? ( $raw_option['profiles'][0]['id'] ?? null ) : null,
	);
	throw new RuntimeException( 'Isolated fixture persistence could not be verified: ' . wp_json_encode( $info ) );
}
file_put_contents( $dir . '/ui-evidence-fixture.json', wp_json_encode( array(
	'evidence_class' => 'DISPOSABLE_REAL_REGISTRY_SYNTHETIC_FIXTURE',
	'id' => $id,
	'label_characters' => mb_strlen( $long ),
	'verified' => true,
), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . "\n" );
