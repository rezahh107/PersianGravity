<?php
/**
 * Disposable WU008-only Form Builder observation fixture.
 * Creates a separate test Form through GFAPI; never writes Scanner Registry.
 */
defined('ABSPATH') || exit(1);
$dir = getenv('WU008_ARTIFACT_DIR');
$expected = getenv('WU008_PGR_SHA');
if (!$dir || !preg_match('/^[a-f0-9]{40}$/', (string)$expected) ||
    get_locale() !== 'fa_IR' || !class_exists('GFAPI') ||
    !class_exists('PGR_GF_Field_Structured_Scanner')) {
    throw new RuntimeException('Missing authentic WU008 runtime or Scanner field');
}
$package = json_decode(file_get_contents(PGR_PATH . 'release-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
if (($package['source_commit'] ?? '') !== $expected) {
    throw new RuntimeException('Installed production package SHA does not match test head');
}
$form = array(
    'title' => 'Scanner UI Readonly Qualification Fixture',
    'description' => 'Disposable form, never submitted during observation',
    'fields' => array(
        array('id'=>1, 'label'=>'Native Mapping Target', 'type'=>'text'),
        array('id'=>2, 'label'=>'Native Email', 'type'=>'email'),
        array('id'=>3, 'label'=>'Native Notes', 'type'=>'textarea'),
        array('id'=>4, 'label'=>'Structured Scanner', 'type'=>'pgr_structured_scanner',
              'scanner_profile'=>'sayad_v01', 'scanner_mappings'=>array()),
    ),
    'button' => array('type'=>'text','text'=>'Submit'),
);
$id=GFAPI::add_form($form);
if (is_wp_error($id)) {
    throw new RuntimeException($id->get_error_message());
}
$stored=GFAPI::get_form($id);
if (!is_array($stored) || count($stored['fields'] ?? array()) !== 4 ||
    $stored['fields'][3]->type !== 'pgr_structured_scanner' ||
    $stored['fields'][3]->scanner_profile !== 'sayad_v01') {
    throw new RuntimeException('Authentic GFAPI form readback mismatched');
}
$proof=array(
    'kind'=>'WU008_DISPOSABLE_FORM_FIXTURE_NOT_SUBMITTED',
    'source_commit_installed'=>$package['source_commit'],
    'source_tree'=>$package['source_tree'] ?? null,
    'wordpress'=>get_bloginfo('version'),
    'gravityforms'=>GFForms::$version,
    'form_id'=>(int)$id,
    'scanner_field_id'=>4,
    'native_control_field_id'=>1,
    'profile_id'=>'sayad_v01',
    'output_count'=>count(PGR_Scanner_Profile_Registry::get('sayad_v01')['outputs']),
    'registry_state_mutated_by_fixture'=>false,
);
$folder=rtrim($dir,'/').'/scanner-ui-qualification-evidence';
wp_mkdir_p($folder);
file_put_contents($folder.'/fixture.json', wp_json_encode($proof, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
echo 'SCANNER_UI_FIXTURE_READY form_id=' . (int)$id . PHP_EOL;
