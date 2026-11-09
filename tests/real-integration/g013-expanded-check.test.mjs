import test from 'node:test';
import assert from 'node:assert/strict';
import {validateReport,claims,names,safeSummary} from './g013-expanded-check.mjs';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

const head='a'.repeat(40),snapshot='b'.repeat(64);
const key='baseline-fa_IR';
function valid(){
  const cases=[];
  for(const name of names)for(const width of [1280,390]){
    const ids=name==='perks-admin'?['manage_tab','install_tab']:
      name==='fup-crop'?['crop_cancel','crop_save']:['react_select','react_portal'];
    cases.push({name,width,status:'PASS',font:{loadedFace:false,hasVazir:false,documentWidth:width},
      observed:[...claims[name]],
      geometry:ids.map(id=>({id,left:5,right:width-5,width:width-10,height:38,viewport:width,
        scrollWidth:width-10,clientWidth:width-10})),
      screenshot:key+'-'+name+'-'+width+'.jpg'});
  }
  return {schema:'g013-extended-browser-v1',head,profile:'BASELINE',locale:'fa_IR',
    environment:{wordpress:'6.8.3',playwright:'1.55.0',snapshot,
      gravity:{gravityforms:'3.1.1.1',gravityflow:'3.1.0',gravityview:'3.3.4',
        gravityperks:'2.3.16',gpfileuploadpro:'1.5.13',gpadvancedselect:'1.1.21'}},
    cases,status:'PASS'};
}
test('clean complete authentic-structure fixture admitted',()=>{
  assert.doesNotThrow(()=>validateReport(valid(),key,head));
});
for(const [name,mutate] of [
  ['missed real crop save',d=>d.cases[2].observed.splice(2,1)],
  ['unexecuted editor portal',d=>d.cases[4].status='NOT_PROVEN'],
  ['forged snapshot',d=>d.environment.snapshot=''],
  ['wrong GravityView installed version',d=>d.environment.gravity.gravityview='3.3.5'],
  ['missing mobile scenario',d=>d.cases.pop()],
  ['duplicate full scene',d=>d.cases[0]=d.cases[1]],
  ['clipped crop control',d=>d.cases[2].geometry[0].right=4500],
  ['fabricated screenshot path',d=>d.cases[2].screenshot='../../token.txt'],
  ['changed locale',d=>d.locale='en_US'],
]){
  test('reject '+name,()=>{
    const d=valid(); mutate(d);
    assert.throws(()=>validateReport(d,key,head),/G013_EXTENDED_EVIDENCE_REJECTED/);
  });
}

test('real Persian WITH_VAZIR scenarios reject missing computed or loaded Vazirmatn',()=>{
  const d=valid();d.profile='WITH_VAZIR';
  for(const c of d.cases){c.font.hasVazir=true;c.font.loadedFace=true;}
  assert.doesNotThrow(()=>validateReport(d,'with-vazir-fa_IR',head));
  d.cases[0].font.loadedFace=false;
  assert.throws(()=>validateReport(d,'with-vazir-fa_IR',head),/G013_EXTENDED_EVIDENCE_REJECTED/);
});
test('sanitized failure output exposes only an allowlisted scenario identity',()=>{
  const tmp=fs.mkdtempSync(path.join(os.tmpdir(),'g013-safe-'));
  try{
    const d=valid();d.status='NOT_PROVEN';d.cases[0].status='NOT_PROVEN';
    d.cases[0].observed=['secret-token-never-print'];
    fs.writeFileSync(path.join(tmp,'baseline-fa_IR.json'),JSON.stringify(d));
    const line=safeSummary(tmp);
    assert.match(line,/baseline-fa_IR=NOT_PROVEN@perks-admin-1280/);
    assert.ok(!line.includes('secret-token-never-print'));
  }finally{fs.rmSync(tmp,{recursive:true,force:true});}
});
