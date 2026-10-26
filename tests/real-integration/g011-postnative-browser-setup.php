<?php
/**
 * Disposable genuine-browser fixtures for selected Text confirmations.
 * Runs once through WP-CLI after the existing G-011 integration setup.
 */
defined( 'ABSPATH' ) || exit;
$fail = static function ( string $why ): void {
	throw new RuntimeException( 'G011_BROWSERQUAL_SETUP: ' . $why );
};
$dir = getenv( 'WU008_ARTIFACT_DIR' );
if ( ! is_dir( $dir ) || '3.1.1.1' !== GFForms::$version ||
	'fa_IR' !== determine_locale() ||
	'542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b' !== getenv( 'WU008_GF_SHA256' ) ) {
	$fail( 'Exact licensed host or locale not available' );
}
update_option( 'timezone_string', 'Asia/Tehran' );
$fixed = wp_date( 'Y/m/d' );
$tag = '{date_created:format:Y/m/d}';
// Exactly seven occurrences, with no special author markers or Merge Tags.
$message = '<div class="multi-g011" data-day="' . $tag . '">' .
	'<p class="human-a">Submitted on <strong>' . $tag .
	'</strong> (fixed G=' . $fixed . ').</p>' .
	'<p class="machine">receipt-' . $tag . '.tar.gz report_' . $tag .
	'-v2.pdf ../record/' . $tag . '/detail</p>' .
	'<a id="g011-q-link" href="/download/' . $tag . '?keep=1">File</a>' .
	'<p class="human-b">Checked at <em>' . $tag .
	'</em> (fixed G=' . $fixed . ').</p></div>';
$fields = array(
	array( 'id' => 1, 'type' => 'text', 'label' => 'G011 condition', 'isRequired' => true ),
	array( 'id' => 2, 'type' => 'text', 'label' => 'G011 isolated mode', 'isRequired' => true ),
);
$form_id = GFAPI::add_form( array(
	'title' => 'WU008 G011 Post-native Browser Test Only',
	'fields' => $fields,
	'button' => array( 'type' => 'text', 'text' => 'Submit' ),
) );
if ( ! is_int( $form_id ) ) {
	$fail( 'GFAPI::add_form failed for targeted Text form' );
}
$form = GFAPI::get_form( $form_id );
$form['confirmations'] = array(
	'g011-q-default' => array(
		'id' => 'g011-q-default', 'name' => 'Selected Text',
		'type' => 'message', 'isDefault' => true, 'isActive' => true,
		'disableAutoformat' => true, 'message' => $message,
	),
	'g011-q-conditional' => array(
		'id' => 'g011-q-conditional', 'name' => 'Conditional Text',
		'type' => 'message', 'isDefault' => false, 'isActive' => true,
		'disableAutoformat' => true,
		'message' => '<p id="g011-q-conditional">CONDITIONAL_NATIVE {date_created:format:Y/m/d}</p>',
		'conditionalLogic' => array(
			'actionType' => 'show', 'logicType' => 'all',
			'rules' => array( array( 'fieldId' => 1, 'operator' => 'is', 'value' => 'special' ) ),
		),
	),
);
if ( true !== GFAPI::update_form( $form ) ) {
	$fail( 'Failed to persist Text confirmations' );
}
$form = GFAPI::get_form( $form_id );
$selected = array_values( array_filter(
	$form['confirmations'] ?? array(),
	static function ( $c ) {
		return 'message' === ( $c['type'] ?? null ) &&
			! empty( $c['isDefault'] ) && ! empty( $c['isActive'] );
	}
) );
if ( 1 !== count( $selected ) ||
	$selected[0]['id'] !== 'g011-q-default' ||
	$selected[0]['message'] !== $message ) {
	$fail( 'Persisted GF Confirmation identity and byte-level template mismatch' );
}
$make_page = static function ( string $title, int $id, string $ajax ) use ( $fail ): string {
	$page = wp_insert_post( array(
		'post_title' => $title,
		'post_type' => 'page',
		'post_status' => 'publish',
		'post_content' => sprintf(
			'[gravityform id="%d" title="false" description="false" ajax="%s"]',
			$id, $ajax
		),
	) );
	if ( is_wp_error( $page ) || ! is_int( $page ) ) {
		$fail( 'Unable to create test page ' . $title );
	}
	return get_permalink( $page );
};
$urls = array(
	'ordinary' => $make_page( 'G011 BrowserQual Ordinary', $form_id, 'false' ),
	'ajax' => $make_page( 'G011 BrowserQual AJAX', $form_id, 'true' ),
);
$unrelated_id = GFAPI::add_form( array(
	'title' => 'G011 Unrelated Form Negative Control',
	'fields' => array( array( 'id' => 1, 'type' => 'text', 'label' => 'Value', 'isRequired' => true ) ),
	'button' => array( 'type' => 'text', 'text' => 'Submit' ),
) );
if ( ! is_int( $unrelated_id ) ) {
	$fail( 'Unrelated Form creation failed' );
}
$unrelated = GFAPI::get_form( $unrelated_id );
$unrelated['confirmations'] = array(
	'unrelated' => array(
		'id' => 'g011-q-other', 'type' => 'message',
		'isDefault' => true, 'isActive' => true,
		'message' => '<p id="g011-q-unrelated">UNRELATED_NATIVE_KEEP</p>',
		'disableAutoformat' => true,
	),
);
if ( true !== GFAPI::update_form( $unrelated ) ) {
	$fail( 'Unrelated confirmation setup failed' );
}
$urls['unrelated'] = $make_page( 'G011 BrowserQual Unrelated', $unrelated_id, 'false' );
$transport = array();
$destination_id = wp_insert_post( array(
	'post_title' => 'G011 BrowserQual Page Destination',
	'post_type' => 'page', 'post_status' => 'publish',
	'post_content' => '<p id="g011-q-destination">TRANSPORT_NATIVE_DESTINATION</p>',
) );
if ( ! is_int( $destination_id ) ) {
	$fail( 'Destination page creation failed' );
}
foreach ( array( 'page', 'redirect' ) as $type ) {
	$id = GFAPI::add_form( array(
		'title' => 'G011 BrowserQual ' . $type,
		'fields' => array(
			array( 'id' => 1, 'type' => 'text', 'label' => 'Transport', 'isRequired' => true ),
		),
		'button' => array( 'type' => 'text', 'text' => 'Submit' ),
	) );
	if ( ! is_int( $id ) ) {
		$fail( 'Transport form creation failed' );
	}
	$tf = GFAPI::get_form( $id );
	$tf['confirmations'] = array(
		'transport' => array(
			'id' => 'g011-q-transport-' . $type,
			'type' => $type, 'isDefault' => true, 'isActive' => true,
			'pageId' => $destination_id,
			'url' => home_url( '/?g011_transport=redirect' ),
			'queryString' => 'day={date_created:format:Y/m/d}&keep=1',
		),
	);
	if ( true !== GFAPI::update_form( $tf ) ) {
		$fail( 'Transport confirmation persistence failed' );
	}
	$transport[$type] = array(
		'form_id' => $id,
		'url' => $make_page( 'G011 BrowserQual ' . $type, $id, 'false' ),
	);
}
$cfg = array( 'form_id' => $form_id, 'default_id' => $selected[0]['id'] );
update_option( 'wu008_g011_browser_qual_cfg', $cfg, false );
$manifest = array(
	'contract' => 'G011_POSTNATIVE_REAL_BROWSER_V1',
	'head' => getenv( 'WU008_PGR_SHA' ),
	'host' => GFForms::$version,
	'host_sha256' => getenv( 'WU008_GF_SHA256' ),
	'form_id' => $form_id,
	'form_sha256' => hash( 'sha256', wp_json_encode( $form ) ),
	'default_id' => $selected[0]['id'],
	'conditional_id' => 'g011-q-conditional',
	'authoritative_timezone' => wp_timezone_string(),
	'fixed_gregorian_literal' => $fixed,
	'urls' => $urls,
	'unrelated_form_id' => $unrelated_id,
	'transport' => $transport,
);
file_put_contents( $dir . '/g011-browserqual-fixture.json',
	wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
echo "G011 BrowserQual real persisted form and transport fixtures ready\n";
