// PRI-FND-005: source inventory only. Never export licensed vendor source.
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

const root=path.join(process.env.WU008_WP_PATH||'', 'wp-content/plugins/gravityflow');
const packageFile=path.join(process.env.WU008_PACKAGE_DIR||'',process.env.WU008_FLOW_FILE||'');
const output=path.join(process.env.WU008_ARTIFACT_DIR||'', 'g013-flow-source-contract.json');
if(!fs.existsSync(root)||!fs.existsSync(packageFile))throw Error('Authentic installed Flow/package missing');
const hash=crypto.createHash('sha256').update(fs.readFileSync(packageFile)).digest('hex');
if(hash!=='ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404') throw Error('Flow ZIP drift');
const tokens=['enableRtl','grid_options','gflow_config','createGrid','new agGrid.Grid','gridOptions','wp_localize_script','wp_add_inline_script','is_rtl','get_locale','get_user_locale','gravityflow_inbox','ag-rtl','ag-ltr','register_grid'];
const counts=Object.fromEntries(tokens.map(t=>[t,0]));
const found=[], hooks=[], allFiles=[], maxPerToken=75;
function scan(dir){
 for(const ent of fs.readdirSync(dir,{withFileTypes:true})){
  const full=path.join(dir,ent.name);
  if(ent.isSymbolicLink())continue;
  if(ent.isDirectory()){scan(full);continue;}
  if(!ent.isFile()||!/\.(?:php|js)$/.test(ent.name)||fs.statSync(full).size>2*1024*1024)continue;
  const rel=path.relative(root,full).split(path.sep).join('/');
  const body=fs.readFileSync(full,'utf8');
  const lines=body.split(/\r?\n/);
  allFiles.push(rel);
  let scope='(top-level)',klass=null;
  for(let i=0;i<lines.length;i++){
    const text=lines[i],trim=text.trim();
    const classMatch=trim.match(/\bclass\s+([A-Za-z0-9_]+)/);
    if(classMatch)klass=classMatch[1];
    const fn=trim.match(/\bfunction\s+([A-Za-z0-9_]+)\s*\(/);
    if(fn)scope=(klass?klass+'::':'')+fn[1];
    for(const token of tokens){
      if(!text.includes(token))continue;
      counts[token]++;
      if(counts[token]<=maxPerToken)found.push({file:rel,line:i+1,token,scope,
        context: /(?:inbox|grid|flow)/i.test(rel)?'flow-inbox-or-grid':'other',
        mode: text.includes('apply_filters')?'FILTER':text.includes('do_action')?'ACTION':text.includes('wp_localize_script')?'LOCALIZE':text.includes('wp_add_inline_script')?'INLINE_SCRIPT':text.includes('enableRtl')&&text.includes('=')?'RTL_OPTION_REFERENCE':'REFERENCE'});
    }
    const expr=/\b(apply_filters|do_action|add_filter|add_action)\s*\(\s*['"]([^'"]+)['"]/g;
    for(const m of text.matchAll(expr)){
      if(/inbox|grid|flow|rtl|script/i.test(m[2])&&hooks.length<250)
        hooks.push({file:rel,line:i+1,kind:m[1],name:m[2],scope});
    }
  }
 }
}
scan(root);
const outputObject={schema:'g013-flow-source-contract-v1',expected_package_sha256:hash,
  head:process.env.WU008_PGR_SHA,source_file_count:allFiles.length,counts,
  matches:found,hooks};
fs.writeFileSync(output,JSON.stringify(outputObject,null,2)+'\n');
console.log('G013_FLOW_SOURCE_INDEX '+JSON.stringify({head:outputObject.head,sha256:hash,
 files:allFiles.length,counts,interesting:found.filter(x=>['enableRtl','grid_options','gflow_config','createGrid','new agGrid.Grid','gridOptions','is_rtl'].includes(x.token)).slice(0,150),hooks:hooks.filter(x=>/inbox|grid|rtl/i.test(x.name)).slice(0,90)}));
