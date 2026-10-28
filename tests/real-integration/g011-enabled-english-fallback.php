<?php
/** Dedicated fresh-request enabled-module English fallback on exact GF host. */
defined( 'ABSPATH' ) || exit;
if ( '3.1.1.1' !== GFForms::$version || 'en_US' !== get_locale() ||
	! PGR_Module_Registry::is_enabled( 'jalali_presentation' ) ) {
	throw new RuntimeException( 'G011 English fallback requires enabled module and en_US host.' );
}
if ( ! class_exists( 'PGR_GF_Explicit_Jalali_Merge_Tags', false ) ||
	false === has_filter( 'gform_replace_merge_tags', array( 'PGR_GF_Explicit_Jalali_Merge_Tags', 'replace' ) ) ) {
	throw new RuntimeException( 'G011 enabled English handler not registered.' );
}
$dir     = getenv( 'WU008_ARTIFACT_DIR' );
$fixture = json_decode( (string) file_get_contents( $dir . '/g011-explicit-production-runtime.json' ), true );
$form    = GFAPI::get_form( (int) $fixture['form_id'] );
$entry   = GFAPI::get_entry( (int) $fixture['entry_id'] );
$before_form = wp_json_encode( $form );
$before_entry = wp_json_encode( $entry );
$tags = array(
	'{pgr_jdate_created}', '{pgr_jdate_created_date}', '{pgr_jdate_created_time}',
	'{pgr_jdate_updated}', '{pgr_jdate_updated_date}', '{pgr_jdate_updated_time}',
	'{pgr_jdate_payment}', '{pgr_jdate_payment_date}', '{pgr_jdate_payment_time}',
	'{pgr_jdate_today}', '{pgr_jdate_today_date}', '{pgr_jdate_today_time}',
	'{pgr_jdate_field_2}',
);
foreach ( $tags as $tag ) {
	$actual = GFCommon::replace_variables( $tag, $form, $entry );
	if ( $actual !== $tag ) {
		throw new RuntimeException( 'G011 en_US changed explicitly unsupported tag: ' . $tag . ' => ' . $actual );
	}
}
$native = GFCommon::replace_variables( 'Name={Name:1} | Visit={Visit:2}', $form, $entry );
if ( 'Name=ordinary | Visit=03/20/2026' !== $native ) {
	throw new RuntimeException( 'G011 en_US broke native Gravity Forms Merge Tags.' );
}
if ( $before_form !== wp_json_encode( GFAPI::get_form( (int) $fixture['form_id'] ) ) ||
	$before_entry !== wp_json_encode( GFAPI::get_entry( (int) $fixture['entry_id'] ) ) ) {
	throw new RuntimeException( 'G011 en_US fallback modified persisted Form/Entry.' );
}
file_put_contents( $dir . '/g011-enabled-english-fallback.json', wp_json_encode( array(
	'head' => getenv( 'WU008_PGR_SHA' ), 'gf_version' => GFForms::$version,
	'locale' => get_locale(), 'module_enabled' => true, 'handler_registered' => true,
	'status' => 'PASS', 'unresolved_tokens' => count( $tags ),
	'native_merge_tags_preserved' => true, 'stored_unchanged' => true,
), JSON_PRETTY_PRINT ) );
echo "G011 en_US enabled-module fallback PASS " . count( $tags ) . "\n";
