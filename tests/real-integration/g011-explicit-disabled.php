<?php
/** Fresh-request disabled-module proof in WU008. */
defined( 'ABSPATH' ) || exit;
$dir = getenv( 'WU008_ARTIFACT_DIR' );
$fixture = json_decode( (string) file_get_contents( $dir . '/g011-explicit-production-runtime.json' ), true );
if (
	PGR_Module_Registry::is_enabled( 'jalali_presentation' ) ||
	class_exists( 'PGR_GF_Explicit_Jalali_Merge_Tags', false ) ||
	false !== has_filter( 'gform_replace_merge_tags', array( 'PGR_GF_Explicit_Jalali_Merge_Tags', 'replace' ) )
) {
	throw new RuntimeException( 'G011 disabled module still registers handler.' );
}
$form = GFAPI::get_form( (int) $fixture['form_id'] );
$entry = GFAPI::get_entry( (int) $fixture['entry_id'] );
$sample = GFCommon::replace_variables( 'A={pgr_jdate_created} | B={pgr_jdate_field_2}', $form, $entry );
if ( 'A={pgr_jdate_created} | B={pgr_jdate_field_2}' !== $sample ) {
	throw new RuntimeException( 'G011 disabled module changed explicit tags.' );
}
if ( 'USED' !== PGR_Module_Usage::inspect( 'jalali_presentation' )['status'] ) {
	throw new RuntimeException( 'G011 disabled usage guard overlooked persisted tags.' );
}
file_put_contents( $dir . '/g011-explicit-disabled.json', wp_json_encode( array(
	'status' => 'PASS', 'head' => getenv( 'WU008_PGR_SHA' ),
	'handler_loaded' => false, 'unresolved_tags_retained' => true,
	'persisted_usage' => 'USED',
), JSON_PRETTY_PRINT ) );
echo "G011 disabled new-request PASS\n";
