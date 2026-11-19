<?php
/**
 * PRS-LAB G015: read-only observer for an isolated licensed WordPress/GF host.
 * Does not print secrets or licensed code.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit( 3 );
}
$record = array(
    'probe' => 'G015_REAL_GF_CONTRACT_V1',
    'source_sha' => getenv('WU008_PGR_SHA') ?: '',
    'gf_version' => class_exists('GFForms') ? GFForms::$version : null,
    'checks' => array(),
);
$check = static function ($name, $value) use (&$record) {
    $record['checks'][$name] = $value;
};
if (!class_exists('PGR_Jalali_Range') || !class_exists('PGR_GF_Field_Jalali_Date')) {
    $check('prerequisite', 'MISSING_G015_CLASS');
    echo 'PGR_PRS_LAB_G015=' . wp_json_encode($record) . "\n";
    exit(2);
}
$editor_field = new PGR_GF_Field_Jalali_Date(array(
    'id' => 7, 'type' => 'pgr_jalali_date',
    'jalaliMin' => '1400/01/01', 'jalaliMax' => '1405/01/01',
));
$editor_field->validate('1406/01/01', array('id'=>991));
$check('camel_editor_outside_rejected', (bool) $editor_field->failed_validation);
$check('camel_editor_property', (string) $editor_field->jalaliMax);
$check('snake_validator_property', (string) $editor_field->jalali_max);
$snake_control = new PGR_GF_Field_Jalali_Date(array(
    'id' => 8, 'type' => 'pgr_jalali_date',
    'jalali_min' => '1400/01/01', 'jalali_max' => '1405/01/01',
));
$snake_control->validate('1406/01/01', array('id'=>991));
$check('snake_control_outside_rejected', (bool) $snake_control->failed_validation);
$check('upper_equality_accepted', PGR_Jalali_Range::is_within('1404-05-10','','1404-05-10'));
$check('interior_control_accepted', PGR_Jalali_Range::is_within('1404-05-09','','1404-05-10'));
$check('above_max_control_rejected', !PGR_Jalali_Range::is_within('1404-05-11','','1404-05-10'));
$check('installed_preset_file_exists', is_readable(PGR_PATH . 'tools/jalali/range-presets.json'));
$check('installed_adult_preset_resolved', '' !== PGR_Jalali_Range::resolve_bound('preset:adult_birthdate'));
$probe_form_id=994000001;
$first=new PGR_GF_Field_Jalali_Date(array('id'=>1,'jalaliMin'=>'1400/01/01'));
$second=new PGR_GF_Field_Jalali_Date(array('id'=>2,'jalaliMin'=>'1410/01/01'));
$one=PGR_Jalali_Range::bounds_for_field($probe_form_id,$first);
$two=PGR_Jalali_Range::bounds_for_field($probe_form_id,$second);
$check('two_field_bounds_independent', $one['min'] !== $two['min']);
$check('two_field_bounds_seen', array('first'=>$one['min'],'second'=>$two['min']));
$record['isolation']='licensed_GF_disposable_WP';
echo 'PGR_PRS_LAB_G015=' . wp_json_encode($record, JSON_UNESCAPED_SLASHES) . "\n";
