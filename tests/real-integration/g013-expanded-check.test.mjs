import test from 'node:test';
import assert from 'node:assert/strict';
import {validateReport,validateAll,claims,names,safeSummary} from './g013-expanded-check.mjs';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

const head='a'.repeat(40),snapshot='b'.repeat(64),key='baseline-fa_IR';
function valid(){
  const cases=[];
  for(const name of names)for(const width of [1280,390]){
    const ids=name==='perks-admin'?['manage_tab','install_tab']:['react_select','react_portal'];
    cases.push({name,width,status:'PASS',font:{loadedFace:false,hasVazir:false,documentWidth:width},
      observed:[...claims[name]],
      geometry:ids.map(id=>({id,left:5,right:width-5,width:width-10,height:38,viewport:width,
        scrollWidth:width-10,clientWidth:width-10})),
      screenshot:key+'-'+name+'-'+width+'.jpg'});
  }
  return {schema:'g013-extended-browser-v2',head,profile:'BASELINE',locale:'fa_IR',
    environment:{wordpress:'6.8.3',playwright:'1.55.0',snapshot,
      gravity:{gravityforms:'3.1.1.1',gravityflow:'3.1.0',gravityview:'3.3.4',
        gravityperks:'2.3.16',gpadvancedselect:'1.1.21'}},
    cases,status:'PASS'};
}
test('editor activation uses a real block click and checks selected state',()=>{
  const source=fs.readFileSync(new URL('./g013-expanded-browser.mjs',import.meta.url),'utf8');
  assert.match(source,/await realBlock\.click\(\{timeout:5000\}\)/);
  assert.match(source,/keyboard\.press\('Shift\+Alt\+O'\)/);
  assert.match(source,/items\.first\(\)\.click\(\)/);
  assert.match(source,/getSelectedBlockClientId\(\)===id/);
  assert.match(source,/getSelectedBlockClientId\(\)===id/);
  assert.match(source,/isEditorSidebarOpened/);
});
test('safe Gutenberg selection diagnostic is bounded and strips arbitrary data',()=>{
  const tmp=fs.mkdtempSync(path.join(os.tmpdir(),'g013-selection-safe-'));
  try{
    const d=valid();d.status='NOT_PROVEN';d.cases[2].status='NOT_PROVEN';
    d.cases[2].stage='EDITOR_LIST_ITEM';
    d.cases[2].selectionDiagnostic={schema:'g013-gutenberg-selection-v1',
      rootCount:1,targetCount:1,ready:'YES',postType:'PAGE',clientIdValidated:true,
      candidateFrames:2,canvasMatches:1,canvasVisible:1,canvasPointerHit:0,
      canvasClick:'COMPLETED',listVisible:true,listItems:5,listRoleTreeitem:5,
      listRoleButton:1,listRoleGroup:1,listClassItems:5,
      listExactTargetMatches:1,listTargetTreeitems:1,
      selectionBefore:'NONE',selectionAfterCanvas:'NONE',selectionAfterList:'TARGET',
      listClick:'COMPLETED',privateElement:'SECRET_CANARY',clientId:'SECRET_CANARY'};
    fs.writeFileSync(path.join(tmp,'baseline-fa_IR.json'),JSON.stringify(d));
    const safe=safeSummary(tmp);
    assert.match(safe,/items=5,roles=5\/1\/1,classItems=5,identity=1\/1/);
    assert.match(safe,/selection=NONE\/NONE\/TARGET/);
    assert.ok(!safe.includes('SECRET_CANARY'));
    d.cases[2].selectionDiagnostic.listItems='private DOM';
    d.cases[2].selectionDiagnostic.selectionAfterCanvas='SECRET_CANARY';
    fs.writeFileSync(path.join(tmp,'baseline-fa_IR.json'),JSON.stringify(d));
    const negative=safeSummary(tmp);
    assert.match(negative,/items=\?/);
    assert.match(negative,/selection=NONE\/\?\/TARGET/);
    assert.ok(!negative.includes('private DOM'));
    assert.ok(!negative.includes('SECRET_CANARY'));
  }finally{fs.rmSync(tmp,{recursive:true,force:true});}
});
test('approved Perks/GV cases are complete without FUP admission',()=>{
  assert.deepEqual(names,['perks-admin','gv-block-editor']);
  const d=valid();
  assert.doesNotThrow(()=>validateReport(d,key,head));
  d.environment.gravity.gpfileuploadpro='1.5.13'; // optional historical WU008 metadata
  assert.doesNotThrow(()=>validateReport(d,key,head));
});
for(const [name,mutate] of [
  ['unexecuted GV editor portal',d=>d.cases[2].status='NOT_PROVEN'],
  ['missing Perks keyboard toggle',d=>d.cases[0].observed.pop()],
  ['forged snapshot',d=>d.environment.snapshot=''],
  ['wrong GravityView version',d=>d.environment.gravity.gravityview='3.3.5'],
  ['missing mobile scenario',d=>d.cases.pop()],
  ['duplicate scene',d=>d.cases[0]=d.cases[1]],
  ['clipped editor portal',d=>d.cases[2].geometry[0].right=4500],
  ['unsafe screenshot path',d=>d.cases[2].screenshot='../../secret.txt'],
  ['changed locale',d=>d.locale='en_US'],
  ['obsolete excluded scenario',d=>d.cases[0].name='fup-crop'],
])test('reject '+name,()=>{
  const d=valid();mutate(d);
  assert.throws(()=>validateReport(d,key,head),/G013_EXTENDED_EVIDENCE_REJECTED/);
});
test('real Persian WITH_VAZIR requires computed and loaded Vazirmatn',()=>{
  const d=valid();d.profile='WITH_VAZIR';
  for(const c of d.cases){c.font.hasVazir=true;c.font.loadedFace=true;
    c.screenshot='with-vazir-fa_IR-'+c.name+'-'+c.width+'.jpg';}
  assert.doesNotThrow(()=>validateReport(d,'with-vazir-fa_IR',head));
  d.cases[0].font.loadedFace=false;
  assert.throws(()=>validateReport(d,'with-vazir-fa_IR',head),/G013_EXTENDED_EVIDENCE_REJECTED/);
});
test('safe summary never exposes raw browser errors',()=>{
  const tmp=fs.mkdtempSync(path.join(os.tmpdir(),'g013-safe-'));
  try{
    const d=valid();d.status='NOT_PROVEN';d.cases[0].status='NOT_PROVEN';
    d.cases[0].observed=['private-canary'];d.cases[0].stage='UNTRUSTED_VALUE';
    fs.writeFileSync(path.join(tmp,'baseline-fa_IR.json'),JSON.stringify(d));
    const line=safeSummary(tmp);
    assert.match(line,/baseline-fa_IR=NOT_PROVEN@perks-admin-1280/);
    assert.ok(!line.includes('private-canary')&&!line.includes('UNTRUSTED_VALUE'));
    d.cases[0].name='gv-block-editor';d.cases[0].stage='EDITOR_SELECT_LOOKUP';
    fs.writeFileSync(path.join(tmp,'baseline-fa_IR.json'),JSON.stringify(d));
    assert.match(safeSummary(tmp),/NOT_PROVEN@gv-block-editor-1280:EDITOR_SELECT_LOOKUP/);
    d.cases[0].stage='SECRET_INFORMATION';
    fs.writeFileSync(path.join(tmp,'baseline-fa_IR.json'),JSON.stringify(d));
    assert.ok(!safeSummary(tmp).includes('SECRET_INFORMATION'));
    assert.ok(!safeSummary(tmp).includes('EDITOR_SELECT_LOOKUP'));
  }finally{fs.rmSync(tmp,{recursive:true,force:true});}
});
test('16 real-scope scenes reject WITH_VAZIR-only overflow',()=>{
  const dir=fs.mkdtempSync(path.join(os.tmpdir(),'g013-pair-overflow-'));
  try{
    for(const locale of ['fa_IR','en_US'])for(const withFont of [false,true]){
      const k=(withFont?'with-vazir-':'baseline-')+locale;
      const d=valid();d.profile=withFont?'WITH_VAZIR':'BASELINE';d.locale=locale;
      for(const c of d.cases){
        c.screenshot=k+'-'+c.name+'-'+c.width+'.jpg';
        if(withFont&&locale==='fa_IR'){c.font.hasVazir=true;c.font.loadedFace=true;}
      }
      fs.writeFileSync(path.join(dir,k+'.json'),JSON.stringify(d));
    }
    assert.deepEqual(validateAll(dir,head,{files:false}),{profiles:4,scenarios:16});
    const p=path.join(dir,'with-vazir-fa_IR.json'),d=JSON.parse(fs.readFileSync(p,'utf8'));
    d.cases.find(c=>c.name==='gv-block-editor'&&c.width===390).font.documentWidth=405;
    fs.writeFileSync(p,JSON.stringify(d));
    assert.throws(()=>validateAll(dir,head,{files:false}),/WITH_VAZIR-only document overflow/);
  }finally{fs.rmSync(dir,{recursive:true,force:true});}
});
test('corrupt screenshot bytes fail',()=>{
  const dir=fs.mkdtempSync(path.join(os.tmpdir(),'g013-bytes-'));
  try{
    const d=valid();
    for(const c of d.cases)fs.writeFileSync(path.join(dir,c.screenshot),'not a jpeg');
    assert.throws(()=>validateReport(d,key,head,{files:true,dir}),/corrupt genuine screenshot/);
  }finally{fs.rmSync(dir,{recursive:true,force:true});}
});
