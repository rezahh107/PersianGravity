<?php
/** Research only: authentic Timeline two-hook falsification. Never production-loaded. */
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }

final class PGR_Timeline_Research {
    public array $contexts = array();
    public array $counts = array( 'first' => 0, 'second' => 0, 'consumed' => 0, 'unrelated' => 0, 'stale' => 0, 'nested' => 0 );
    public bool $drift = false;
    public bool $conversion_failure = false;
    private bool $busy = false;
    private int $serial = 0;

    public function start(): void { add_filter( 'gravityflow_timeline_notes', array( $this, 'arm' ), PHP_INT_MAX, 2 ); }
    public function stop(): void {
        remove_filter( 'gravityflow_timeline_notes', array( $this, 'arm' ), PHP_INT_MAX );
        remove_filter( 'date_i18n', array( $this, 'consume' ), PHP_INT_MAX );
        $this->contexts = array();
    }
    private function signature( array $frame ): string { return ( $frame['class'] ?? '' ) . ( $frame['type'] ?? '' ) . $frame['function']; }
    private function chain( array $trace, array $expected ): ?array {
        foreach ( $trace as $i => $frame ) {
            if ( $this->signature( $frame ) !== $expected[0] ) { continue; }
            foreach ( $expected as $j => $name ) {
                if ( ! isset( $trace[$i + $j] ) || $this->signature( $trace[$i + $j] ) !== $name ) { return null; }
            }
            return array_slice( $trace, $i, count( $expected ) );
        }
        return null;
    }
    private function prune( array $trace ): void {
        foreach ( $this->contexts as $key => $context ) {
            $live = false;
            foreach ( $trace as $frame ) {
                if ( ( $frame['function'] ?? '' ) === 'apply_filters' && ( $frame['args'][0] ?? null ) === 'gravityflow_timeline_notes' && ( $frame['args'][1] ?? null ) === $context['notes'] ) { $live = true; }
                if ( $this->signature( $frame ) === 'Gravity_Flow_Entry_Detail::notes_grid' && ( $frame['args'][0] ?? null ) === $context['notes'] ) { $live = true; }
            }
            if ( ! $live ) { unset( $this->contexts[$key] ); ++$this->counts['stale']; }
        }
    }
    public function arm( $notes, $entry ) {
        ++$this->counts['first'];
        $trace = debug_backtrace( 0, 40 );
        $this->prune( $trace );
        $path = $this->chain( $trace, array( 'Gravity_Flow_Common::get_timeline_notes', 'Gravity_Flow_Entry_Detail::get_timeline_notes', 'Gravity_Flow_Entry_Detail::timeline' ) );
        if ( $this->busy || $this->drift || ! $path || ! PGR_Module_Registry::is_enabled( 'jalali_presentation' ) || GRAVITY_FLOW_VERSION !== '3.1.0' || GFForms::$version !== '3.1.1.1' || ! $notes ) { return $notes; }
        if ( ( $path[2]['args'][0] ?? null ) !== $entry ) { return $notes; }
        $this->busy = true;
        $date_format = GFCommon::get_default_date_format();
        $records = array();
        foreach ( $notes as $note ) {
            $raw = $note->date_created ?? null;
            $date = is_string( $raw ) ? DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $raw, new DateTimeZone( 'UTC' ) ) : false;
            if ( ! $date || $date->format( 'Y-m-d H:i:s' ) !== $raw ) { continue; }
            $civil = $date->setTimezone( wp_timezone() );
            $records[spl_object_id( $note )] = array( 'note' => $note, 'snapshot' => get_object_vars( $note ), 'timestamp' => gmmktime( (int) $civil->format('H'), (int) $civil->format('i'), (int) $civil->format('s'), (int) $civil->format('n'), (int) $civil->format('j'), (int) $civil->format('Y') ), 'used' => false );
        }
        $this->busy = false;
        if ( ! $records ) { return $notes; }
        if ( $this->contexts ) { ++$this->counts['nested']; }
        $this->contexts[++$this->serial] = array( 'notes' => $notes, 'records' => $records, 'format' => $date_format );
        add_filter( 'date_i18n', array( $this, 'consume' ), PHP_INT_MAX, 4 );
        return $notes;
    }
    public function consume( $native, $format, $timestamp, $gmt ) {
        ++$this->counts['second'];
        if ( $this->busy ) { ++$this->counts['unrelated']; return $native; }
        $trace = debug_backtrace( 0, 60 );
        $this->prune( $trace );
        // Contiguous frames reject formatting inside any intervening callback.
        $path = $this->chain( $trace, array( 'date_i18n', 'GFCommon::format_date', 'Gravity_Flow_Common::format_date', 'Gravity_Flow_Entry_Detail::get_note_header', 'Gravity_Flow_Entry_Detail::get_note_body', 'Gravity_Flow_Entry_Detail::notes_grid', 'Gravity_Flow_Entry_Detail::timeline' ) );
        if ( ! $path || $this->drift || ! $gmt || ! is_int( $timestamp ) ) { ++$this->counts['unrelated']; $this->detach_empty(); return $native; }
        $note = $path[4]['args'][0] ?? null;
        foreach ( $this->contexts as $key => &$context ) {
            if ( ( $path[5]['args'][0] ?? null ) !== $context['notes'] || ! is_object( $note ) ) { continue; }
            $id = spl_object_id( $note );
            if ( ! isset( $context['records'][$id] ) ) { continue; }
            $record =& $context['records'][$id];
            if ( $record['used'] || $record['note'] !== $note || $record['snapshot'] !== get_object_vars( $note ) || $record['timestamp'] !== $timestamp || $context['format'] !== $format || ( $path[1]['args'] ?? null ) !== array( $note->date_created, false, '', true ) || ( $path[2]['args'] ?? null ) !== array( $note->date_created, '', false, true ) ) { continue; }
            $record['used'] = true;
            $this->busy = true;
            $converted = $this->conversion_failure ? null : PGR_Jalali_Presentation::format_date( (int) gmdate('Y', $timestamp), (int) gmdate('n', $timestamp), (int) gmdate('j', $timestamp) );
            $this->busy = false;
            if ( $converted !== null ) { ++$this->counts['consumed']; }
            $remaining = array_filter( $context['records'], static fn( $item ) => ! $item['used'] );
            unset( $record, $context );
            if ( ! $remaining ) { unset( $this->contexts[$key] ); }
            $this->detach_empty();
            return $converted ?? $native;
        }
        unset( $context );
        ++$this->counts['unrelated'];
        $this->detach_empty();
        return $native;
    }
    private function detach_empty(): void { if ( ! $this->contexts ) { remove_filter( 'date_i18n', array( $this, 'consume' ), PHP_INT_MAX ); } }
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
    return array('gfapi'=>$current, 'rest_status'=>$rest->get_status(), 'rest'=>$rest->get_data(), 'db'=>$wpdb->get_row($wpdb->prepare('SELECT * FROM ' . GFFormsModel::get_entry_table_name() . ' WHERE id=%d', $entry_id), ARRAY_A), 'meta'=>$wpdb->get_results($wpdb->prepare('SELECT * FROM ' . GFFormsModel::get_entry_meta_table_name() . ' WHERE entry_id=%d ORDER BY id', $entry_id), ARRAY_A), 'notes'=>$wpdb->get_results($wpdb->prepare('SELECT * FROM ' . GFFormsModel::get_entry_notes_table_name() . ' WHERE entry_id=%d ORDER BY id', $entry_id), ARRAY_A), 'state'=>array($step->get_id(), $step->get_due_date_timestamp(), $step->get_expiration_timestamp(), $step->get_schedule_timestamp(), $step->is_overdue(), $step->is_expired()));
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
$checks = array('repeated'=>$actual === $repeat, 'wp_isolation'=>date_i18n('Y-m-d',1900269060,true)===$wp_native, 'gf_isolation'=>GFCommon::format_date($entry['date_created'],false,'',true)===$gf_native, 'text_api_isolation'=>Gravity_Flow_Common::get_timeline($entry)===$text_native, 'sidebar_isolation'=>$sidebar()===$sidebar_native, 'marker_absent'=>strpos($actual,'PGR_TIMELINE')===false);
$research->drift = true;
$checks['version_drift_native'] = $render()===$native;
$research->drift = false;
$research->conversion_failure = true;
$checks['conversion_failure_native'] = $render()===$native;
$research->conversion_failure = false;
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
$reentrant = static function($value,$format,$timestamp,$gmt) use (&$in_formatter,&$nested_native) {
    if(!$in_formatter) { $in_formatter=true; $nested_native = $nested_native && date_i18n($format,$timestamp,$gmt)===$value; $in_formatter=false; }
    return $value;
};
add_filter('date_i18n',$reentrant,1,4);
$reentrant_output=$render();
remove_filter('date_i18n',$reentrant,1);
$checks['reentrant_same_arguments_native'] = $nested_native && $reentrant_output===$actual;

// Same date/time format must still convert the date only.
$same_format = static fn($value) => 'Y-m-d';
$research->stop();
add_filter('option_time_format',$same_format);
$same_native=$render();
$research->start();
$same_actual=$render();
remove_filter('option_time_format',$same_format);
$checks['same_format_time_preserved'] = true;
foreach ($headers($same_actual) as $i=>$header) {
    $parts=explode(' ',html_entity_decode($header));
    $native_parts=explode(' ',html_entity_decode($headers($same_native)[$i]));
    if (end($parts)!==end($native_parts)) { $checks['same_format_time_preserved']=false; }
}
// Abort after arming; the next unrelated formatter must discard stale tokens.
$abort = static function($notes) { throw new RuntimeException('RESEARCH_ABORT'); };
add_filter('gravityflow_timeline_notes',$abort,PHP_INT_MAX);
$level=ob_get_level();
try { $render(); } catch (RuntimeException $e) { if($e->getMessage()!=='RESEARCH_ABORT') { throw $e; } }
while(ob_get_level()>$level) { ob_end_clean(); }
remove_filter('gravityflow_timeline_notes',$abort,PHP_INT_MAX);
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
$result=array('evidence_class'=>'RESEARCH_ONLY_AUTHENTIC_TIMELINE_TWO_HOOK','head'=>getenv('WU008_PGR_SHA'),'flow_sha256'=>getenv('WU008_FLOW_SHA256'),'mode'=>$mode,'checks'=>$checks,'ordinary_counts'=>$ordinary_counts,'all_counts'=>$research->counts,'native_headers'=>$headers($native),'actual_headers'=>$headers($actual),'same_format_headers'=>$headers($same_actual),'before'=>$before,'after'=>$after,'production_admission'=>false);
file_put_contents($artifact_dir.'/g008-timeline-two-hook-research-'.$mode.'.json',wp_json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
if(in_array(false,$checks,true)) { throw new RuntimeException('Timeline research falsification: '.implode(', ',array_keys(array_filter($checks,static fn($v)=>!$v)))); }
