import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {spawnSync} from 'node:child_process';

const HEAD='0123456789abcdef0123456789abcdef01234567';
const workflow=fs.readFileSync(new URL('../../.github/workflows/wu008-real-integration.yml',import.meta.url),'utf8');
const match=workflow.match(/          \/\/ WU008_EVIDENCE_GATE_BEGIN\n([\s\S]*?)          \/\/ WU008_EVIDENCE_GATE_END/);
assert.ok(match,'Publisher inline gate missing');
const gate=match[1].split('\n').map(line=>line.trim()?line.replace(/^          /,''):line).join('\n');
assert.ok(workflow.indexOf('WU008_EVIDENCE_GATE_BEGIN')<workflow.indexOf('git push origin'),'Gate must precede push');
assert.match(workflow,/WU008_EVIDENCE_EXPECTED_HEAD: \$\{\{ github\.event\.pull_request\.head\.sha \}\}/);

const jpeg=Buffer.from('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAkGBwgHBgkIBwgKCgkLDRYPDQwMDRsUFRAWIB0iIiAdHx8kKDQsJCYxJx8fLT0tMTU3Ojo6Iys/RD84QzQ5Ojf/2wBDAQoKCg0MDRoPDxo3JR8lNzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzc3Nzf/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAIRAxEAPwD3GiiigD//2Q==','base64');
const locales=['fa_IR','en_US'];
const normal=['overview','profiles-list','profiles-new','profiles-edit','profiles-built-in','settings','system-status','module-confirm'];
const disabled=['overview-disabled','profiles-disabled-warning','settings-disabled-notice','system-status-disabled'];
function fixture(){
  const dir=fs.mkdtempSync(path.join(os.tmpdir(),'wu008-evidence-falsification-'));
  const images=[],manifests=new Map();
  for(const locale of locales)for(const mode of ['normal','disabled']){
    const cases=[];
    for(const width of [1280,390,320]){
      const names=width!==320?(mode==='normal'?normal:disabled):
        mode==='normal'?['overview','profiles-edit','system-status','module-confirm']:
        ['profiles-disabled-warning','settings-disabled-notice'];
      for(const name of names){
        const filename='ui-'+mode+'-'+locale+'-'+name+'-'+width+'.jpg';
        images.push(filename);
        fs.writeFileSync(path.join(dir,filename),jpeg);
        cases.push({name,width,height:width===1280?900:width===390?844:700,dpr:1,locale,mode,
          state:name==='system-status'&&width===390?'VISUAL_FINDING':'CAPTURED_NO_DETECTED_OVERFLOW',
          measured:{viewport:{width}},screenshot:filename});
      }
    }
    const negative_control=mode==='normal'?
      {mode:'ISOLATED_HTML_NOT_PRODUCT',state:'DETECTED',detected:true,viewport_width:320,document_width:800}:null;
    const manifest={exact_head:HEAD,locale,mode,status:'CAPTURED',
      evidence_class:'AUTHENTIC_LICENSED_WU008_ADMIN_BROWSER',
      runtime:{wordpress:'6.8.3',php:'8.2.35',versions:{persiangravity:'4.10.0'}},
      cases,negative_control};
    const file='manifest-'+mode+'-'+locale+'.json';
    manifests.set(file,manifest);
    fs.writeFileSync(path.join(dir,file),JSON.stringify(manifest));
    if(mode==='normal')fs.writeFileSync(path.join(dir,'ui-negative-control-'+locale+'-320.jpg'),jpeg);
  }
  fs.writeFileSync(path.join(dir,'CONTACT_SHEET.md'),
    images.map(name=>'[<img src="'+name+'" alt="capture" />]('+name+')').join('\n'));
  return {dir,images,manifests};
}
function save(f,name){fs.writeFileSync(path.join(f.dir,name),JSON.stringify(f.manifests.get(name)));}
function execute(f){
  const result=spawnSync(process.execPath,['--input-type=module'],{
    input:gate,encoding:'utf8',env:{...process.env,
      WU008_EVIDENCE_SOURCE_DIR:f.dir,WU008_EVIDENCE_EXPECTED_HEAD:HEAD}});
  const pushMarker=path.join(f.dir,'.would-push');
  // This is the same control-flow dependency as set -e in the publisher step:
  // a failing gate prevents any subsequent shell command (including git push).
  if(result.status===0)fs.writeFileSync(pushMarker,'allowed');
  return {result,pushAllowed:fs.existsSync(pushMarker)};
}
function mutation(name,change,diagnostic){
  test(name,()=>{
    const f=fixture();
    try{
      change(f);
      const {result,pushAllowed}=execute(f);
      assert.notEqual(result.status,0,'Incomplete artifact unexpectedly accepted');
      assert.equal(pushAllowed,false,'Evidence reached simulated publication');
      assert.match(result.stderr,new RegExp(diagnostic,'i'),result.stderr);
    }finally{fs.rmSync(f.dir,{recursive:true,force:true});}
  });
}
test('complete four manifests, 60 JPEGs, two controls and VISUAL_FINDING pass',()=>{
  const f=fixture();
  try{
    assert.equal(f.images.length,60);
    const {result,pushAllowed}=execute(f);
    assert.equal(result.status,0,result.stderr);
    assert.equal(pushAllowed,true);
    assert.match(result.stdout,/manifests=4, product_jpegs=60, negative_controls=2/);
  }finally{fs.rmSync(f.dir,{recursive:true,force:true});}
});
mutation('missing product JPEG',f=>fs.unlinkSync(path.join(f.dir,f.images[0])),'missing required evidence file');
mutation('missing isolated control JPEG',f=>fs.unlinkSync(path.join(f.dir,'ui-negative-control-fa_IR-320.jpg')),'missing required evidence file');
mutation('empty JPEG',f=>fs.writeFileSync(path.join(f.dir,f.images[0]),''),'empty/invalid JPEG');
mutation('invalid JPEG bytes',f=>fs.writeFileSync(path.join(f.dir,f.images[0]),Buffer.from('INVALID'.repeat(40))),'empty/invalid JPEG');
mutation('truncated JPEG',f=>fs.writeFileSync(path.join(f.dir,f.images[0]),jpeg.subarray(0,200)),'truncated JPEG EOI');
mutation('duplicate screenshot ref',f=>{const m=f.manifests.get('manifest-normal-fa_IR.json');m.cases[1].screenshot=m.cases[0].screenshot;save(f,'manifest-normal-fa_IR.json');},'wrong/duplicate screenshot reference');
mutation('altered screenshot ref',f=>{const m=f.manifests.get('manifest-normal-fa_IR.json');m.cases[0].screenshot='ui-other.jpg';save(f,'manifest-normal-fa_IR.json');},'wrong/duplicate screenshot reference');
mutation('missing manifest',f=>fs.unlinkSync(path.join(f.dir,'manifest-disabled-en_US.json')),'missing required evidence file');
mutation('corrupt manifest JSON',f=>fs.writeFileSync(path.join(f.dir,'manifest-normal-en_US.json'),'[oops'),'invalid manifest JSON');
mutation('Head mismatch',f=>{const m=f.manifests.get('manifest-normal-en_US.json');m.exact_head='f'.repeat(40);save(f,'manifest-normal-en_US.json');},'PR Head mismatch');
mutation('locale mismatch',f=>{const m=f.manifests.get('manifest-normal-en_US.json');m.locale='fa_IR';save(f,'manifest-normal-en_US.json');},'locale/mode mismatch');
mutation('mode mismatch',f=>{const m=f.manifests.get('manifest-normal-en_US.json');m.mode='disabled';save(f,'manifest-normal-en_US.json');},'locale/mode mismatch');
mutation('unresolved contact sheet link',f=>{const p=path.join(f.dir,'CONTACT_SHEET.md');fs.writeFileSync(p,fs.readFileSync(p,'utf8').replace(']('+f.images[0]+')','](missing.jpg)'));},'unresolved/unexpected contact-sheet screenshot link');
mutation('swapped contact sheet thumbnail target',f=>{
  const p=path.join(f.dir,'CONTACT_SHEET.md');
  const content=fs.readFileSync(p,'utf8');
  fs.writeFileSync(p,content.replace(']('+f.images[0]+')',']('+f.images[1]+')')
    .replace(']('+f.images[1]+')',']('+f.images[0]+')'));
},'duplicate contact-sheet image reference|contact sheet thumbnail/link mismatch');
mutation('path traversal in screenshot',f=>{const m=f.manifests.get('manifest-normal-fa_IR.json');m.cases[0].screenshot='../private.jpg';save(f,'manifest-normal-fa_IR.json');},'unsafe screenshot filename');
mutation('unexpected evidence file',f=>fs.writeFileSync(path.join(f.dir,'extraneous.json'),'{}'),'unexpected artifact content');
mutation('unexpected nested directory',f=>fs.mkdirSync(path.join(f.dir,'nested')),'unexpected/nonregular artifact entry');
mutation('CAPTURED but missing case',f=>{const m=f.manifests.get('manifest-normal-fa_IR.json');m.cases.pop();save(f,'manifest-normal-fa_IR.json');},'incomplete scenario count');
mutation('CAPTURED but duplicated case',f=>{const m=f.manifests.get('manifest-normal-fa_IR.json');m.cases[1]={...m.cases[0]};save(f,'manifest-normal-fa_IR.json');},'duplicate scenario');
mutation('CAPTURED with failed case',f=>{const m=f.manifests.get('manifest-normal-fa_IR.json');m.cases[0].state='CAPTURE_FAILED';save(f,'manifest-normal-fa_IR.json');},'incomplete capture state');
mutation('negative control claims missed overflow',f=>{const m=f.manifests.get('manifest-normal-fa_IR.json');m.negative_control.detected=false;save(f,'manifest-normal-fa_IR.json');},'overflow negative control');
mutation('inconsistent runtime identity',f=>{const m=f.manifests.get('manifest-normal-en_US.json');m.runtime.php='8.5.0';save(f,'manifest-normal-en_US.json');},'inconsistent installed runtime versions');
