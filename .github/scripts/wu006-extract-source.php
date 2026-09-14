<?php
if ( $argc < 2 ) { fwrite(STDERR, "usage: php extract.php <root> [domain]\n"); exit(2); }
$root = rtrim($argv[1], '/');
$domain = $argv[2] ?? 'gk-gravityview';
$sigs = [
 '__'=>[0,null,null,1], '_e'=>[0,null,null,1], 'esc_html__'=>[0,null,null,1], 'esc_html_e'=>[0,null,null,1], 'esc_attr__'=>[0,null,null,1], 'esc_attr_e'=>[0,null,null,1],
 '_x'=>[0,1,null,2], '_ex'=>[0,1,null,2], 'esc_html_x'=>[0,1,null,2], 'esc_attr_x'=>[0,1,null,2],
 '_n'=>[0,null,1,3], '_n_noop'=>[0,null,1,2], '_nx'=>[0,3,1,4], '_nx_noop'=>[0,2,1,3],
 'translate'=>[0,null,null,1], 'translate_with_gettext_context'=>[0,1,null,2]
];
function pgr_wu006_lit($tokens){$parts=[];foreach($tokens as $t){if(is_array($t)){if(in_array($t[0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true))continue;if($t[0]===T_CONSTANT_ENCAPSED_STRING){$q=$t[1][0];$b=substr($t[1],1,-1);$v=$q==="'"?str_replace(["\\\\","\\'"],["\\","'"],$b):stripcslashes($b);$parts[]=$v;continue;}return null;}if(trim($t)==='')continue;if($t==='.'||$t==='('||$t===')')continue;return null;}return implode('',$parts);}
$ids=[];$dynamic=[];$calls=0;
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
foreach($it as $f){if(!$f->isFile()||strtolower($f->getExtension())!=='php')continue;$path=str_replace('\\','/',substr($f->getPathname(),strlen($root)+1));$code=file_get_contents($f->getPathname());$toks=token_get_all($code);$n=count($toks);
for($i=0;$i<$n;$i++){$t=$toks[$i];if(!is_array($t)||$t[0]!==T_STRING)continue;$fn=$t[1];if(!isset($sigs[$fn]))continue;$j=$i+1;while($j<$n&&is_array($toks[$j])&&in_array($toks[$j][0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true))$j++;if($j>=$n||$toks[$j]!=='(')continue;$k=$i-1;while($k>=0&&is_array($toks[$k])&&in_array($toks[$k][0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true))$k--;if($k>=0&&((is_array($toks[$k])&&in_array($toks[$k][0],[T_OBJECT_OPERATOR,T_DOUBLE_COLON,T_FUNCTION,T_NEW],true))||$toks[$k]==='\\'))continue;
$depth=0;$args=[];$cur=[];$end=null;for($p=$j;$p<$n;$p++){$x=$toks[$p];$s=is_array($x)?$x[1]:$x;if($s==='('){$depth++;if($depth>1)$cur[]=$x;continue;}if($s===')'){$depth--;if($depth===0){$args[]=$cur;$end=$p;break;}$cur[]=$x;continue;}if($s===','&&$depth===1){$args[]=$cur;$cur=[];continue;}$cur[]=$x;}if($end===null)continue;
[$mi,$ci,$pi,$di]=$sigs[$fn]; $d=isset($args[$di])?pgr_wu006_lit($args[$di]):null; if($d!==$domain)continue; $calls++;
$msg=isset($args[$mi])?pgr_wu006_lit($args[$mi]):null; $ctx=$ci!==null&&isset($args[$ci])?pgr_wu006_lit($args[$ci]):''; $pl=$pi!==null&&isset($args[$pi])?pgr_wu006_lit($args[$pi]):'';
if($msg===null||($ci!==null&&$ctx===null)||($pi!==null&&$pl===null)){ $dynamic[]=['function'=>$fn,'reference'=>$path.':'.$t[2]]; continue; }
$ctx=$ctx??'';$pl=$pl??'';$hash=hash('sha256',$ctx."\x1f".$msg."\x1f".$pl); if(!isset($ids[$hash]))$ids[$hash]=['identity_sha256'=>$hash,'msgctxt'=>$ctx,'msgid'=>$msg,'msgid_plural'=>$pl,'references'=>[]];$ids[$hash]['references'][]=$path.':'.$t[2];
}}
ksort($ids,SORT_STRING);foreach($ids as &$r){$r['references']=array_values(array_unique($r['references']));sort($r['references'],SORT_STRING);}unset($r);
$hashes=array_keys($ids);$keyset=hash('sha256',implode("\n",$hashes).(empty($hashes)?'':"\n"));$refs=[];foreach($ids as $h=>$r)foreach($r['references'] as $ref)$refs[]=$h."\x1f".$ref;sort($refs,SORT_STRING);$refhash=hash('sha256',implode("\n",$refs).(empty($refs)?'':"\n"));$ctxc=0;$plc=0;foreach($ids as $r){if($r['msgctxt']!=='')$ctxc++;if($r['msgid_plural']!=='')$plc++;}
$out=['domain'=>$domain,'canonical_message_count'=>count($ids),'context_message_count'=>$ctxc,'plural_message_count'=>$plc,'source_reference_count'=>count($refs),'canonical_keyset_sha256'=>$keyset,'source_reference_index_sha256'=>$refhash,'literal_call_count'=>$calls-count($dynamic),'dynamic_non_literal_calls'=>$dynamic,'identities'=>$ids];
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),"\n";
