<?php
/** Research only: authentic Timeline two-hook falsification. Never production-loaded. */
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }

final class PGR_Timeline_Research {
    public array $contexts = array();
    public array $counts = array('first'=>0,'second'=>0,'consumed'=>0,'unrelated'=>0,'stale'=>0,'nested'=>0,'armed'=>0,'initial_consumed'=>0,'stored_consumed'=>0);
    public bool $drift = false;
    public bool $conversion_failure = false;
    private array $markers = array();
    private int $serial = 0;
    private function signature(array $frame): string { return ($frame['class']??'').($frame['type']??'').$frame['function']; }
    private function chain(array $trace,array $names): ?array {
        foreach($trace as $i=>$frame) {
            if($this->signature($frame)!==$names[0]) { continue; }
            foreach($names as $j=>$name) { if(!isset($trace[$i+$j]) || $this->signature($trace[$i+$j])!==$name) { return null; } }
            return array_slice($trace,$i,count($names));
        }
        return null;
    }
    private function prune(array $trace): void {
        foreach($this->contexts as $key=>$context) {
            $live=false;
            foreach($trace as $frame) {
                if($this->signature($frame)==='Gravity_Flow_Entry_Detail::get_note_body' && ($frame['args'][0]??null)===$context['note']) { $live=true; }
            }
            if(!$live) { unset($this->contexts[$key]); ++$this->counts['stale']; }
        }
    }
    public function start(): void {
        if(PGR_Module_Registry::is_enabled('jalali_presentation')) { add_filter('option_date_format',array($this,'arm'),PHP_INT_MAX,2); }
    }
    public function stop(): void {
        remove_filter('option_date_format',array($this,'arm'),PHP_INT_MAX);
        remove_filter('date_i18n',array($this,'consume'),PHP_INT_MAX);
        $this->contexts=array(); $this->markers=array();
    }
    public function clean($value) { return is_string($value) ? str_replace(array_values($this->markers),'',$value) : $value; }
    public function arm($format,$option) {
        ++$this->counts['first'];
        $trace=debug_backtrace(0,60); $this->prune($trace);
        $path=$this->chain($trace,array('get_option','GFCommon::get_default_date_format','GFCommon::format_date','Gravity_Flow_Common::format_date','Gravity_Flow_Entry_Detail::get_note_header','Gravity_Flow_Entry_Detail::get_note_body','Gravity_Flow_Entry_Detail::notes_grid','Gravity_Flow_Entry_Detail::timeline'));
        if(!$path || $this->drift || $option!=='date_format' || !is_string($format) || !in_array(trim($format),array('F j, Y','Y-m-d'),true) || GRAVITY_FLOW_VERSION!=='3.1.0' || GFForms::$version!=='3.1.1.1') { return $format; }
        $note=$path[5]['args'][0]??null; $notes=$path[6]['args'][0]??null; $entry=$path[7]['args'][0]??null;
        if(!is_object($note) || !is_array($notes) || !in_array($note,$notes,true) || !is_array($entry)) { return $format; }
        $raw=$note->date_created??null;
        if(($path[2]['args']??null)!==array($raw,false,'',true) || ($path[3]['args']??null)!==array($raw,'',false,true)) { return $format; }
        if((int)$note->id===0 ? $raw!==($entry['date_created']??null) : (($note->note_type??null)!=='gravityflow' || (int)$note->id<1)) { return $format; }
        $utc=is_string($raw)?DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$raw,new DateTimeZone('UTC')):false;
        if(!$utc || $utc->format('Y-m-d H:i:s')!==$raw) { return $format; }
        $civil=$utc->setTimezone(wp_timezone());
        $timestamp=gmmktime((int)$civil->format('H'),(int)$civil->format('i'),(int)$civil->format('s'),(int)$civil->format('n'),(int)$civil->format('j'),(int)$civil->format('Y'));
        $literal='PGRTL'.bin2hex(random_bytes(16)).(++$this->serial).'X'; $escaped='';
        foreach(str_split($literal) as $char) { $escaped.='\\'.$char; }
        $marked=$escaped.trim($format);
        if($this->contexts) { ++$this->counts['nested']; }
        $this->contexts[$marked]=array('note'=>$note,'snapshot'=>get_object_vars($note),'notes'=>$notes,'entry'=>$entry,'timestamp'=>$timestamp);
        $this->markers[$marked]=$literal; ++$this->counts['armed'];
        add_filter('date_i18n',array($this,'consume'),PHP_INT_MAX,4);
        return $marked;
    }
    public function consume($native,$format,$timestamp,$gmt) {
        ++$this->counts['second'];
        $fallback=$this->clean($native);
        $trace=debug_backtrace(0,60); $this->prune($trace);
        $path=$this->chain($trace,array('date_i18n','GFCommon::format_date','Gravity_Flow_Common::format_date','Gravity_Flow_Entry_Detail::get_note_header','Gravity_Flow_Entry_Detail::get_note_body','Gravity_Flow_Entry_Detail::notes_grid','Gravity_Flow_Entry_Detail::timeline'));
        if(!$path || !isset($this->contexts[$format])) { ++$this->counts['unrelated']; return $fallback; }
        $context=$this->contexts[$format];
        // A matching marked invocation is one-shot even if validation fails.
        unset($this->contexts[$format]);
        $note=$path[4]['args'][0]??null;
        if($this->drift || $gmt!==true || !is_int($timestamp) || $timestamp!==$context['timestamp'] || $note!==$context['note'] || get_object_vars($note)!==$context['snapshot'] || ($path[5]['args'][0]??null)!==$context['notes'] || ($path[6]['args'][0]??null)!==$context['entry'] || !in_array(($path[1]['args']??null),array(array($note->date_created,false,'',true),array($note->date_created,false,$format,true)),true) || ($path[2]['args']??null)!==array($note->date_created,'',false,true)) { ++$this->counts['unrelated']; return $fallback; }
        $converted=$this->conversion_failure?null:PGR_Jalali_Presentation::format_date((int)gmdate('Y',$timestamp),(int)gmdate('n',$timestamp),(int)gmdate('j',$timestamp));
        if($converted!==null) { ++$this->counts['consumed']; ++$this->counts[(int)$note->id===0?'initial_consumed':'stored_consumed']; }
        return $converted??$fallback;
    }
}

$artifact_dir = getenv('WU008_ARTIFACT_DIR');
$mode = getenv('WU008_G008_MODE');
$manifest = json_decode(file_get_contents(getenv('WU008_MANIFEST_PATH')), true, 512, JSON_THROW_ON_ERROR);
if ( GRAVITY_FLOW_VERSION !== '3.1.0' || getenv('WU008_FLOW_SHA256') !== 'ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404' || GFForms::$version !== '3.1.1.1' ) { throw new RuntimeException('Exact package gate failed'); }
$enabled = PGR_Module_Registry::is_enabled('jalali_presentation');
if ( $enabled !== ( $mode === 'enabled' ) ) { throw new RuntimeException('Mode mismatch'); }
wp_set_current_user(1);
require_once WP_PLUGIN_DIR . '/gravityflow/includes/pages/class-entry-detail.php';
require_once WP_PLUGIN_DIR . '/gravityflow/includes/pages/class-print-entries.php';
$entry_id = (int) $manifest['g008_flow_entry_detail_candidate_entry_id'];
$entry = GFAPI::get_entry($entry_id);
$form = GFAPI::get_form($entry['form_id']);
$render = static function () use ($entry, $form) { ob_start(); Gravity_Flow_Entry_Detail::timeline($entry, $form); return ob_get_clean(); };
$headers = static function ($html) { preg_match_all('/<div class="gravityflow-note-meta">(.*?)<\/div>/s', $html, $m); return $m[1]; };
$snapshot = static function () use ($entry_id, $form) {
    global $wpdb;
    $current = GFAPI::get_entry($entry_id);
    $step = (new Gravity_Flow_API($form['id']))->get_current_step($current);
    $rest = rest_do_request(new WP_REST_Request('GET', '/gf/v2/entries/' . $entry_id));
    return json_decode(wp_json_encode(array('form'=>GFAPI::get_form($form['id']), 'feeds'=>gravity_flow()->get_feeds($form['id']), 'gfapi'=>$current, 'rest_status'=>$rest->get_status(), 'rest'=>$rest->get_data(), 'db'=>$wpdb->get_row($wpdb->prepare('SELECT * FROM ' . GFFormsModel::get_entry_table_name() . ' WHERE id=%d', $entry_id), ARRAY_A), 'meta'=>$wpdb->get_results($wpdb->prepare('SELECT * FROM ' . GFFormsModel::get_entry_meta_table_name() . ' WHERE entry_id=%d ORDER BY id', $entry_id), ARRAY_A), 'notes'=>$wpdb->get_results($wpdb->prepare('SELECT * FROM ' . GFFormsModel::get_entry_notes_table_name() . ' WHERE entry_id=%d ORDER BY id', $entry_id), ARRAY_A), 'state'=>array($step->get_id(), $step->get_due_date_timestamp(), $step->get_expiration_timestamp(), $step->get_schedule_timestamp(), $step->is_overdue(), $step->is_expired()))),true,512,JSON_THROW_ON_ERROR);
};
$before = $snapshot();
$native = $render();
$wp_native = date_i18n('Y-m-d',1900269060,true);
$gf_native = GFCommon::format_date($entry['date_created'],false,'',true);
$text_native = Gravity_Flow_Common::get_timeline($entry);
$sidebar = static function () use ($entry, $form) { ob_start(); gravity_flow()->workflow_entry_detail_status_box($form,$entry,(new Gravity_Flow_API($form['id']))->get_current_step($entry),array()); return ob_get_clean(); };
$sidebar_native = $sidebar();
$research = new PGR_Timeline_Research();
$research->start();
$actual = $render();
$repeat = $render();
$ordinary_counts = $research->counts;
$checks = array('repeated'=>$actual === $repeat, 'wp_isolation'=>date_i18n('Y-m-d',1900269060,true)===$wp_native, 'gf_isolation'=>GFCommon::format_date($entry['date_created'],false,'',true)===$gf_native, 'text_api_isolation'=>Gravity_Flow_Common::get_timeline($entry)===$text_native, 'sidebar_isolation'=>$sidebar()===$sidebar_native, 'marker_absent'=>strpos($actual,'PGRTL')===false);
$research->drift = true;
$checks['version_drift_native'] = $render()===$native;
$research->drift = false;
$research->conversion_failure = true;
$checks['conversion_failure_native'] = $render()===$native;
$research->conversion_failure = false;
$range_entry=GFAPI::get_entry((int)$manifest['g008_flow_entry_detail_range_entry_id']);
$research->stop(); ob_start(); Gravity_Flow_Entry_Detail::timeline($range_entry,$form); $range_native=ob_get_clean();
$research->start(); ob_start(); Gravity_Flow_Entry_Detail::timeline($range_entry,$form); $range_actual=ob_get_clean();
$checks['unsupported_range_native']=$range_native===$range_actual;

// Inject unrelated formatting and a nested authentic Timeline before outer rows.
$nesting = false;
$nested_output = null;
$nested_isolation = true;
$nested_filter = static function ($notes) use (&$nesting, &$nested_output, &$nested_isolation, $render, $wp_native, $gf_native, $entry) {
    if (!$nesting) { $nesting=true; $nested_isolation = date_i18n('Y-m-d',1900269060,true)===$wp_native && GFCommon::format_date($entry['date_created'],false,'',true)===$gf_native; $nested_output=$render(); $nesting=false; }
    return $notes;
};
add_filter('gravityflow_timeline_notes',$nested_filter,PHP_INT_MAX);
$nested_outer=$render();
remove_filter('gravityflow_timeline_notes',$nested_filter,PHP_INT_MAX);
$checks['nested_before_rows'] = $nested_outer===$actual && $nested_output===$actual && $nested_isolation;
// A nested formatter with identical arguments must not borrow an outer frame.
$in_formatter=false; $nested_native=true;
$reentrant = static function($value,$format,$timestamp,$gmt) use (&$in_formatter,&$nested_native,$research) {
    if(!$in_formatter) { $in_formatter=true; $nested_native = $nested_native && date_i18n($format,$timestamp,$gmt)===$research->clean($value); $in_formatter=false; }
    return $value;
};
add_filter('date_i18n',$reentrant,1,4);
$reentrant_output=$render();
remove_filter('date_i18n',$reentrant,1);
$checks['reentrant_same_arguments_native'] = $nested_native && $reentrant_output===$actual;
// Reenter after a format token is armed, while the outer row is still active.
$in_time_option=false; $nested_during_format=null;
$during_format=static function($value) use (&$in_time_option,&$nested_during_format,$render) {
    if(!$in_time_option) { $in_time_option=true; $nested_during_format=$render(); $in_time_option=false; }
    return $value;
};
add_filter('option_time_format',$during_format,PHP_INT_MAX);
$outer_during_format=$render();
remove_filter('option_time_format',$during_format,PHP_INT_MAX);
$checks['nested_armed_row_isolation']=$outer_during_format===$actual && $nested_during_format===$actual;
// Call-header helpers outside the authentic Timeline path must remain native.
$research->stop(); $header_native=Gravity_Flow_Entry_Detail::get_note_header('control',$entry['date_created']);
$research->start(); $checks['missing_timeline_context_native']=Gravity_Flow_Entry_Detail::get_note_header('control',$entry['date_created'])===$header_native;
// Later filters may alter the format; only cleanup, never conversion, is allowed.
$suffix=static fn($value)=>$value.'!';
$research->stop(); add_filter('option_date_format',$suffix,PHP_INT_MAX); $suffix_native=$render(); remove_filter('option_date_format',$suffix,PHP_INT_MAX);
$research->start(); add_filter('option_date_format',$suffix,PHP_INT_MAX); $suffix_actual=$render(); remove_filter('option_date_format',$suffix,PHP_INT_MAX);
$checks['late_format_mismatch_native']=$suffix_actual===$suffix_native;


// Same date/time format must still convert the date only.
$same_format = static fn($value) => 'Y-m-d';
$research->stop();
add_filter('option_date_format',$same_format,10); add_filter('option_time_format',$same_format);
$same_native=$render();
$research->start();
$same_actual=$render();
remove_filter('option_date_format',$same_format,10); remove_filter('option_time_format',$same_format);
$checks['same_format_time_preserved'] = true;
foreach ($headers($same_actual) as $i=>$header) {
    $parts=explode(' ',html_entity_decode($header));
    $native_parts=explode(' ',html_entity_decode($headers($same_native)[$i]));
    if (end($parts)!==end($native_parts)) { $checks['same_format_time_preserved']=false; }
}

// WP has special U semantics; unsupported profiles and short-circuits stay native.
$research->stop(); $unix_format=static fn($value)=>'U'; add_filter('option_date_format',$unix_format,10); $unix_native=$render();
$research->start(); $checks['unsupported_format_native']=$render()===$unix_native; remove_filter('option_date_format',$unix_format,10);
$research->stop(); $pre_format=static fn($value)=>'Y-m-d'; add_filter('pre_option_date_format',$pre_format,10); $pre_native=$render();
$research->start(); $checks['option_short_circuit_native']=$render()===$pre_native; remove_filter('pre_option_date_format',$pre_format,10);
// Abort after arming; the next unrelated formatter must discard stale tokens.
$abort = static function($notes) { throw new RuntimeException('RESEARCH_ABORT'); };
add_filter('option_date_format',$abort,PHP_INT_MAX);
$level=ob_get_level();
try { $render(); } catch (RuntimeException $e) { if($e->getMessage()!=='RESEARCH_ABORT') { throw $e; } }
while(ob_get_level()>$level) { ob_end_clean(); }
remove_filter('option_date_format',$abort,PHP_INT_MAX);
$checks['abort_cleanup_native'] = date_i18n('Y-m-d',1900269060,true)===$wp_native && count($research->contexts)===0;
$checks['recovery_after_abort'] = $render()===$actual;
// Authentic Print calls the same Timeline, with no separate adapter.
$_GET['lid']=(string)$entry_id; $_GET['timelines']='1';
ob_start(); Gravity_Flow_Print_Entries::render(); $print=ob_get_clean();
$checks['print_headers_equal'] = $headers($print)===$headers($actual);
$research->stop();
$after=$snapshot();
$checks['raw_storage_api_state_equal'] = $before===$after && $before['rest_status']===200;
$checks['disabled_native'] = $enabled || $actual===$native;
$checks['enabled_changed'] = !$enabled || $actual!==$native;
$notes=Gravity_Flow_Common::get_timeline_notes($entry);
$expected=array();
foreach($notes as $note) {
    $civil=(new DateTimeImmutable($note->date_created,new DateTimeZone('UTC')))->setTimezone(wp_timezone());
    // Fixed independent oracle for fixture civil days, not the converter under test.
    $oracle=array('2030-03-20'=>'۱۴۰۸/۱۲/۳۰','2030-03-21'=>'۱۴۰۹/۰۱/۰۱');
    $expected[]=$oracle[$civil->format('Y-m-d')]??null;
}
$checks['correct_jalali']=true;
if($enabled) { foreach($headers($actual) as $i=>$header) { if(!$expected[$i] || strpos($header,$expected[$i])!==0) { $checks['correct_jalali']=false; } } }
$checks['ids_bodies_order_preserved'] = preg_replace('/<div class="gravityflow-note-meta">.*?<\/div>/s','',$actual)===preg_replace('/<div class="gravityflow-note-meta">.*?<\/div>/s','',$native);
$result=array('evidence_class'=>'RESEARCH_ONLY_AUTHENTIC_TIMELINE_FORMAT_MARKER_TWO_HOOK','head'=>getenv('WU008_PGR_SHA'),'flow_sha256'=>getenv('WU008_FLOW_SHA256'),'mode'=>$mode,'checks'=>$checks,'ordinary_counts'=>$ordinary_counts,'all_counts'=>$research->counts,'native_headers'=>$headers($native),'actual_headers'=>$headers($actual),'same_format_headers'=>$headers($same_actual),'before'=>$before,'after'=>$after,'production_admission'=>false);
file_put_contents($artifact_dir.'/g008-timeline-two-hook-research-'.$mode.'.json',wp_json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
if(in_array(false,$checks,true)) { throw new RuntimeException('Timeline research falsification: '.implode(', ',array_keys(array_filter($checks,static fn($v)=>!$v)))); }
