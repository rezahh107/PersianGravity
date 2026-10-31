import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {spawnSync} from 'node:child_process';
import {inflateSync} from 'node:zlib';
import {EventEmitter} from 'node:events';
import {observePageEvents} from './vazir-page-events.mjs';
import {PROFILES,SURFACES,WIDTHS,SCREENSHOTS,VAZIR_SHA,
  validateEvidence,writeComparison} from './vazir-evidence-check.mjs';

const HEAD='0123456789abcdef0123456789abcdef01234567';
const originalGateWouldAccept = pages => pages.length===12; // historical guard at reviewed Head
const jpegByWidth={
  1280:inflateSync(Buffer.from('eNrtzL0JgEAYg+EcIhxYqKDzWAgHOpdDOI6FQ/jTuMnnaaetjcX7pAshttihog9dkHOSi5HtamVfTcp8MqTxtpTNqu//hyTWVfFuZZv8tW00CgAAAAAAAH+Q23oCLaZRng==','base64')),
  390:inflateSync(Buffer.from('eNr7f+P/AwYBLzdPNwZGRgYGRiBk+H+bwZnhP6XgAAM3B7MPYxsjoyDD/yMMomDzUQAzUFhEAF2U4f8tBg6QWnuGGQyjYBSMglEwCmCF400AMn9R2Q==','base64')),
};
const workflow=fs.readFileSync(new URL('../../.github/workflows/wu008-real-integration.yml',import.meta.url),'utf8');
const marker=workflow.match(/          \/\/ G013_EVIDENCE_GATE_BEGIN\n([\s\S]*?)          \/\/ G013_EVIDENCE_GATE_END/);
assert.ok(marker,'missing independent publisher gate');
const publisher=marker[1].split('\n').map(x=>x.startsWith('          ')?x.slice(10):x).join('\n');
const beforeToken=workflow.slice(workflow.indexOf('      - name: Independently validate G013'),
  workflow.indexOf('      - name: Publish verified evidence with exact commit links'));
assert.ok(beforeToken.includes('G013_EVIDENCE_GATE_BEGIN')&&!beforeToken.includes('GH_TOKEN:'));
assert.ok(workflow.indexOf('G013_EVIDENCE_GATE_BEGIN')<workflow.indexOf('git push origin'));
assert.ok(!publisher.includes('require(')&&!publisher.includes('import(')&&
  !publisher.includes('exec('),'must not execute downloaded scripts');
const read=(f,n)=>JSON.parse(fs.readFileSync(path.join(f.dir,n),'utf8'));
const write=(f,n,v)=>fs.writeFileSync(path.join(f.dir,n),JSON.stringify(v,null,2)+'\n');
function makeFixture() {
  const dir=fs.mkdtempSync(path.join(os.tmpdir(),'g013-falsification-'));
  const f={dir};
  write(f,'official-release.json',{status:'PASS',version:'1.5.0',release_tag:'v1.5.0',
    entrypoint:'vazir-font-wp/vazir-font-wp.php',zip_sha256:VAZIR_SHA});
  write(f,'vazir-settings.json',{version:'1.5.0',active:true,options:{
    enable_frontend:true,enable_admin:true,enable_gravity_forms:true,
    font_weights:['300','400','500','700','900']}});
  for(const key of PROFILES){
    const withFont=key.startsWith('with-vazir');
    const locale=key.endsWith('fa_IR')?'fa_IR':'en_US';
    const pages=[];
    for(const width of WIDTHS)for(const name of SURFACES){
      const positive=withFont&&locale==='fa_IR'&&name==='overview';
      const body={family:positive?'Vazirmatn, Arial':'Arial',direction:locale==='fa_IR'?'rtl':'ltr',
        width,height:800,overflowX:'visible'};
      const image=SCREENSHOTS.includes(name)?key+'-'+name+'-'+width+'.jpg':undefined;
      pages.push({name,width,viewport:width,document_width:width+(withFont&&name==='settings'?12:0),
        direction:locale==='fa_IR'?'rtl':'ltr',body,headline:null,label:null,input:null,button:null,
        faces:positive?[{family:'Vazirmatn',weight:'400',status:'loaded'}]:[],
        font_network:positive?[{url:'vazirmatn-400.woff2',status:200}]:[],
        page_errors:[],...(image?{image}:{})});
      if(image)fs.writeFileSync(path.join(dir,image),jpegByWidth[width]);
    }
    write(f,key+'.json',{schema:'g013-vazir-coexistence-v1',status:'PASS',exact_head:HEAD,
      profile:withFont?'WITH_VAZIR':'BASELINE',locale, persiangravity_version:'4.10.0',
      vazir_version:withFont?'1.5.0':null,expected_vazir_sha256:withFont?VAZIR_SHA:null,
      environment:{chromium:'Chrome 140',wordpress:'6.8.3'},pages});
  }
  writeComparison(dir,HEAD);
  return f;
}
function remove(f){fs.rmSync(f.dir,{recursive:true,force:true});}
function publish(f){
  return spawnSync(process.execPath,['--input-type=module'],{
    input:publisher,encoding:'utf8',
    env:{...process.env,WU008_VAZIR_EVIDENCE:f.dir,WU008_VAZIR_HEAD:HEAD}});
}
function expectReject(name,mutate){
  test(name,()=>{
    const f=makeFixture();
    try {
      mutate(f);
      assert.throws(()=>validateEvidence(f.dir,HEAD),/G013_EVIDENCE_REJECTED/);
      const result=publish(f);
      assert.notEqual(result.status,0,'publisher accepted falsified data: '+name);
    } finally {remove(f);}
  });
}
test('18-per-profile is real producer contract, historical 12 guard rejected it',()=>{
  const f=makeFixture();
  try {
    for(const key of PROFILES){
      const d=read(f,key+'.json');
      assert.equal(d.pages.length,18);
      assert.equal(originalGateWouldAccept(d.pages),false);
    }
    const pairs=validateEvidence(f.dir,HEAD);
    assert.equal(pairs.length,36);
    assert.equal(new Set(pairs.map(x=>x.locale+'@'+x.page+'@'+x.width)).size,36);
    assert.equal(fs.readdirSync(f.dir).filter(x=>x.endsWith('.jpg')).length,32);
    const result=publish(f);
    assert.equal(result.status,0,result.stderr);
  }finally{remove(f);}
});
expectReject('12-case historical mismatch rejected, never silently reduces coverage',f=>{
  const d=read(f,PROFILES[0]+'.json');d.pages=d.pages.slice(0,12);write(f,PROFILES[0]+'.json',d);
});
expectReject('missing browser case',f=>{
  const d=read(f,PROFILES[0]+'.json');d.pages.pop();write(f,PROFILES[0]+'.json',d);
});
expectReject('duplicate browser case with preserved count',f=>{
  const d=read(f,PROFILES[0]+'.json');d.pages[1]=d.pages[0];write(f,PROFILES[0]+'.json',d);
});
expectReject('extra browser case',f=>{
  const d=read(f,PROFILES[0]+'.json');d.pages.push({...d.pages[0],name:'alien'});
  write(f,PROFILES[0]+'.json',d);
});
expectReject('fabricated comparison array with correct length',f=>{
  const d=read(f,'comparison.json');d.comparisons=Array(36).fill({...d.comparisons[0]});
  write(f,'comparison.json',d);
});
expectReject('changed classification inconsistent with numbers',f=>{
  const d=read(f,'comparison.json');d.comparisons[0].classification='PRESENT_IN_BOTH';
  write(f,'comparison.json',d);
});
expectReject('stale PR Head',f=>{
  const d=read(f,'comparison.json');d.exact_head='b'.repeat(40);write(f,'comparison.json',d);
});
expectReject('wrong official release identity',f=>{
  const d=read(f,'official-release.json');d.zip_sha256='0'.repeat(64);
  write(f,'official-release.json',d);
});
expectReject('bad Vazir settings',f=>{
  const d=read(f,'vazir-settings.json');d.options.enable_admin=false;
  write(f,'vazir-settings.json',d);
});
expectReject('corrupt JPEG missing EOI boundary',f=>{
  const filename=PROFILES[0]+'-overview-1280.jpg';
  const b=fs.readFileSync(path.join(f.dir,filename));
  fs.writeFileSync(path.join(f.dir,filename),b.subarray(0,b.length-2));
});
expectReject('desktop success cannot substitute for absent mobile font',f=>{
  const key='with-vazir-fa_IR',d=read(f,key+'.json');
  for(const p of d.pages)if(p.width===390){
    p.faces=[];p.font_network=[];p.body.family='Arial';
  }
  write(f,key+'.json',d);
});
expectReject('BASELINE and WITH_VAZIR are distinct controls',f=>{
  const key='baseline-fa_IR',d=read(f,key+'.json');
  d.pages[0].body.family='Vazirmatn';write(f,key+'.json',d);
});
test('browser events belong to originating page and viewport',()=>{
  const page=new EventEmitter();
  const first=observePageEvents(page);
  page.emit('response',{url:()=>'/wp-content/plugins/vazir-font-wp/assets/fonts/vazirmatn-400.woff2',
    status:()=>200});
  page.emit('pageerror',Error('first-page-error'));
  assert.equal(first.snapshot().font_network.length,1);
  first.stop();
  const second=observePageEvents(page);
  page.emit('pageerror',Error('second-page-error'));
  assert.deepEqual(second.snapshot().font_network,[]);
  assert.deepEqual(second.snapshot().page_errors,['Error: second-page-error']);
  second.stop();
  assert.equal(page.listenerCount('response'),0);
  assert.equal(page.listenerCount('pageerror'),0);
});
