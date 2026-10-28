<?php
/** G-011 real Page/Redirect fixture on the locked licensed GF host. */
defined( 'ABSPATH' ) || exit;
if ( '3.1.1.1' !== GFForms::$version || 'fa_IR' !== get_locale() || ! PGR_Module_Registry::is_enabled( 'jalali_presentation' ) ) {
	throw new RuntimeException( 'G011 destination fixture requires the enabled locked host.' );
}
$dir = getenv( 'WU008_ARTIFACT_DIR' );
if ( ! is_dir( $dir ) || ! shortcode_exists( 'g011_destination_receipt' ) ) {
	throw new RuntimeException( 'G011 destination receipt shortcode is unavailable.' );
}
$destination_id = wp_insert_post( array(
	'post_type' => 'page', 'post_status' => 'publish',
	'post_title' => 'G011 WU008 server receipt',
	'post_content' => '[g011_destination_receipt]',
), true );
if ( is_wp_error( $destination_id ) || ! $destination_id ) {
	throw new RuntimeException( 'G011 destination page creation failed.' );
}
$destination_url = get_permalink( $destination_id );
if ( ! is_string( $destination_url ) || '' === $destination_url ) {
	throw new RuntimeException( 'G011 destination permalink is unavailable.' );
}
// The destination permalink's own page_id is an existing Page query parameter.
// Redirect additionally carries pre-existing parameters and a repeated key.
$redirect_url = $destination_url . ( false === strpos( $destination_url, '?' ) ? '?' : '&' ) . 'existing=one%26two&repeat=base';
$query = 'jalali={pgr_jdate_field_2}&created={pgr_jdate_created_date}&native={Visit:2}' .
	'&person={Name:1}&entry={entry_id}&repeat=first&repeat=second&literal=keep%26safe&space=two%20words';
$form_id = GFAPI::add_form( array(
	'title'  => 'WU008 G011 Page Redirect destination proof',
	'fields' => array(
		array( 'id' => 1, 'type' => 'text', 'label' => 'Name', 'isRequired' => true ),
		array( 'id' => 2, 'type' => 'date', 'label' => 'Visit', 'dateType' => 'datepicker', 'dateFormat' => 'mdy', 'isRequired' => true ),
	),
) );
if ( ! is_int( $form_id ) || $form_id < 1 ) {
	throw new RuntimeException( 'G011 destination form creation failed.' );
}
$form = GFAPI::get_form( $form_id );
$form['confirmations'] = array(
	'default' => array(
		'id' => 'default', 'name' => 'Unmatched is a failure', 'type' => 'message',
		'isDefault' => true, 'isActive' => true, 'message' => 'G011_WRONG_DEFAULT_CONFIRMATION',
	),
	'page' => array(
		'id' => 'page', 'name' => 'Page destination', 'type' => 'page', 'pageId' => $destination_id,
		'isDefault' => false, 'isActive' => true, 'queryString' => $query,
		'conditionalLogic' => array( 'actionType' => 'show', 'logicType' => 'all',
			'rules' => array( array( 'fieldId' => 1, 'operator' => 'is', 'value' => 'page' ) ) ),
	),
	'redirect' => array(
		'id' => 'redirect', 'name' => 'Redirect destination', 'type' => 'redirect', 'url' => $redirect_url,
		'isDefault' => false, 'isActive' => true, 'queryString' => $query,
		'conditionalLogic' => array( 'actionType' => 'show', 'logicType' => 'all',
			'rules' => array( array( 'fieldId' => 1, 'operator' => 'is', 'value' => 'redirect' ) ) ),
	),
);
$form['notifications'] = array(); // Do not send emails from public browser submissions.
if ( true !== GFAPI::update_form( $form ) ) {
	throw new RuntimeException( 'G011 destination form update failed.' );
}
$form = GFAPI::get_form( $form_id );
$baseline_id = GFAPI::add_entry( array(
	'form_id' => $form_id, 'status' => 'active', '1' => 'baseline', '2' => '03/20/2026',
) );
$baseline = GFAPI::get_entry( $baseline_id );
if ( ! is_array( $baseline ) || '03/20/2026' !== ( $baseline['2'] ?? null ) ) {
	throw new RuntimeException( 'G011 destination baseline entry creation failed.' );
}
$pages = array();
foreach ( array( 'ordinary' => 'false', 'ajax' => 'true' ) as $mode => $ajax ) {
	$page_id = wp_insert_post( array(
		'post_type' => 'page', 'post_status' => 'publish',
		'post_title' => 'G011 destination submit ' . $mode,
		'post_content' => '[gravityform id="' . $form_id . '" title="false" description="false" ajax="' . $ajax . '"]',
	), true );
	if ( is_wp_error( $page_id ) || ! $page_id ) {
		throw new RuntimeException( 'G011 destination source page creation failed.' );
	}
	$pages[ $mode ] = get_permalink( $page_id );
}
$fixture = array(
	'head'                  => getenv( 'WU008_PGR_SHA' ),
	'gf_version'            => GFForms::$version,
	'form_id'               => $form_id,
	'destination_id'        => $destination_id,
	'destination_url'       => $destination_url,
	'pages'                 => $pages,
	'baseline_entry_id'     => $baseline_id,
	'form_hash'             => hash( 'sha256', wp_json_encode( GFAPI::get_form( $form_id ) ) ),
	'baseline_entry_hash'   => hash( 'sha256', wp_json_encode( $baseline ) ),
);
file_put_contents( $dir . '/g011-destination-fixture.json', wp_json_encode( $fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
echo "G011 destination fixture ready\n";
