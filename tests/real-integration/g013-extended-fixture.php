<?php
/**
 * G013 extended authenticated browser fixtures. Runs only inside isolated WU008,
 * after the original evidence has been staged and before private DB snapshots.
 * No production code or vendor package changes.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}
$dir = getenv( 'WU008_ARTIFACT_DIR' );
$input = getenv( 'WU008_MANIFEST_PATH' );
if ( ! is_string( $dir ) || ! is_string( $input ) || ! is_readable( $input ) ) {
	throw new RuntimeException( 'G013 requires genuine WU008 paths.' );
}
$manifest = json_decode( (string) file_get_contents( $input ), true, 512, JSON_THROW_ON_ERROR );
$view_id = (int) ( $manifest['g008_gravityview']['view_id'] ?? 0 );
if ( $view_id < 1 || 'gravityview' !== get_post_type( $view_id ) ||
	! class_exists( 'GFAPI' ) || ! class_exists( 'GP_File_Upload_Pro' ) ) {
	throw new RuntimeException( 'G013 exact licensed GravityView/FUP fixture missing.' );
}
$block = WP_Block_Type_Registry::get_instance()->get_registered( 'gk-gravityview-blocks/view' );
if ( ! $block || ! array_key_exists( 'viewId', $block->get_attributes() ) ) {
	throw new RuntimeException( 'G013 exact GravityView View block registration missing.' );
}
$crop = array(
	'title' => 'G013 Disposable Real Image Crop',
	'fields' => array(
		array(
			'id' => 1, 'label' => 'Disposable image', 'type' => 'fileupload',
			'multipleFiles' => true, 'gpfupEnable' => true,
			'gpfupEnableCrop' => true, 'gpfupCropRequired' => true,
			'allowedExtensions' => 'png,jpg,jpeg', 'isRequired' => true,
		),
		array(
			'id' => 2, 'label' => 'Required validation control',
			'type' => 'text', 'isRequired' => true,
		),
	),
	'button' => array( 'type' => 'text', 'text' => 'Submit' ),
);
$form_id = GFAPI::add_form( $crop );
if ( is_wp_error( $form_id ) || (int) $form_id < 1 ) {
	throw new RuntimeException( 'G013 real Crop GFAPI form creation failed.' );
}
$field = GFAPI::get_field( (int) $form_id, 1 );
if ( ! is_object( $field ) || ! rgar( (array) $field, 'gpfupEnable' ) ||
	! rgar( (array) $field, 'gpfupEnableCrop' ) || ! rgar( (array) $field, 'gpfupCropRequired' ) ) {
	throw new RuntimeException( 'G013 crop feature flags not persisted in genuine GFAPI.' );
}
$crop_post = wp_insert_post(
	array(
		'post_type' => 'page', 'post_status' => 'publish',
		'post_title' => 'G013 Disposable Crop Browser',
		'post_content' => sprintf( '[gravityform id="%d" title="false" description="false" ajax="false"]', (int) $form_id ),
	), true
);
$editor_post = wp_insert_post(
	array(
		'post_type' => 'page', 'post_status' => 'publish',
		'post_title' => 'G013 Disposable GravityView Block',
		'post_content' => '<!-- wp:gk-gravityview-blocks/view {"viewId":""} /-->',
	), true
);
if ( is_wp_error( $crop_post ) || is_wp_error( $editor_post ) ||
	(int) $crop_post < 1 || (int) $editor_post < 1 ) {
	throw new RuntimeException( 'G013 genuine WordPress fixture page creation failed.' );
}
$spec = array(
	'schema' => 'g013-real-extended-fixture-v1',
	'head' => (string) getenv( 'WU008_PGR_SHA' ),
	'crop_form_id' => (int) $form_id,
	'crop_url' => get_permalink( $crop_post ),
	'editor_page_id' => (int) $editor_post,
	'editor_url' => admin_url( 'post.php?post=' . (int) $editor_post . '&action=edit' ),
	'view_id' => $view_id,
	'perks_admin_url' => admin_url( 'admin.php?page=gwp_perks' ),
);
file_put_contents( $dir . '/g013-extended-fixture.json',
	wp_json_encode( $spec, JSON_UNESCAPED_SLASHES ) . "\n" );
