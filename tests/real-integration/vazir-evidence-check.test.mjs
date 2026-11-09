import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {spawnSync} from 'node:child_process';
import {inflateSync} from 'node:zlib';
import {EventEmitter} from 'node:events';
import {fileURLToPath} from 'node:url';
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
        width,height:800,weight:'400',overflowX:'visible'};
      const image=SCREENSHOTS.includes(name)?key+'-'+name+'-'+width+'.jpg':undefined;
      pages.push({name,width,viewport:width,document_width:width+(withFont&&name==='settings'?12:0),
        direction:locale==='fa_IR'?'rtl':'ltr',body,headline:null,label:null,input:null,button:null,
        faces:positive?[{family:'Vazirmatn',weight:'400',status:'loaded'}]:[],
        font_network:positive?[{url:'vazirmatn-400.woff2',status:200}]:[],
        page_errors:[],console_errors:[],...(image?{image}:{}),
        ...(['gravity-flow','flow-frontend'].includes(name)?{grid_mode:{
          expected:locale==='fa_IR'?'rtl':'ltr',actual:locale==='fa_IR'?'rtl':'ltr',
          status:'PASS'}}:{})});
      if(image)fs.writeFileSync(path.join(dir,image),jpegByWidth[width]);
    }
    write(f,key+'.json',{schema:'g013-vazir-coexistence-v1',status:'PASS',exact_head:HEAD,
      profile:withFont?'WITH_VAZIR':'BASELINE',locale, persiangravity_version:'4.10.0',
      vazir_version:withFont?'1.5.0':null,expected_vazir_sha256:withFont?VAZIR_SHA:null,
      grid_acceptance:'PASS',environment:{chromium:'140.0.7339.16',wordpress:'6.8.3',
        db_snapshot_sha256:'a'.repeat(64)},pages});
  }
  writeComparison(dir,HEAD);
  return f;
}
function remove(f){fs.rmSync(f.dir,{recursive:true,force:true});}
// Execute the workflow's actual independent runner-local G013 gate.
function publish(f,{withoutSemanticClosure=false}={}){
  let source=publisher;
  if(withoutSemanticClosure){
    // NEGATIVE CONTROL ONLY: recreate the old weaker embedded gate by removing
    // exactly the new independently maintained semantic closure in the CHILD.
    const block=source.match(/\/\/ G013_PUBLISHER_SEMANTIC_BEGIN\n[\s\S]*?\/\/ G013_PUBLISHER_SEMANTIC_END\n/);
    assert.ok(block,'cannot independently isolate semantic closure for mutation control');
    source=source.replace(block[0],'');
  }
  return spawnSync(process.execPath,['--input-type=module'],{
    input:source,encoding:'utf8',
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
expectReject('incorrect native Grid mode disguised as valid RTL',f=>{
  const key='with-vazir-fa_IR',d=read(f,key+'.json');
  const p=d.pages.find(p=>p.name==='gravity-flow');
  p.grid_mode.actual='ltr';p.grid_mode.status='FAIL';d.grid_acceptance='FAIL';
  write(f,key+'.json',d);
});
expectReject('missing mobile Grid mode is not an RTL PASS',f=>{
  const key='baseline-fa_IR',d=read(f,key+'.json');
  const p=d.pages.find(p=>p.name==='gravity-flow'&&p.width===390);
  p.grid_mode.actual=null;p.grid_mode.status='NOT_PROVEN';d.grid_acceptance='NOT_PROVEN';
  write(f,key+'.json',d);
});
expectReject('uncaught JS error cannot be PASS',f=>{
  const key='baseline-en_US',d=read(f,key+'.json');
  d.pages[0].page_errors=['uncaught error'];write(f,key+'.json',d);
});
expectReject('browser console error cannot be PASS',f=>{
  const key='with-vazir-en_US',d=read(f,key+'.json');
  d.pages[0].console_errors=['console.error'];write(f,key+'.json',d);
});
expectReject('non-200 official font cannot be PASS',f=>{
  const key='with-vazir-fa_IR',d=read(f,key+'.json');
  d.pages.find(p=>p.name==='overview'&&p.width===390).font_network[0].status=404;
  write(f,key+'.json',d);
});
expectReject('split network/font proof across distinct pages is not same-page evidence',f=>{
  const key='with-vazir-fa_IR',d=read(f,key+'.json');
  for(const width of WIDTHS) {
    const overview=d.pages.find(p=>p.name==='overview'&&p.width===width);
    const settings=d.pages.find(p=>p.name==='settings'&&p.width===width);
    settings.font_network=overview.font_network;
    overview.font_network=[];
  }
  write(f,key+'.json',d);
});
expectReject('README external link injection rejected by producer and publisher',f=>{
  fs.appendFileSync(path.join(f.dir,'README.md'),'\n[other](https://example.invalid/)\n');
});
expectReject('different restored WordPress DB snapshot is rejected',f=>{
  const key='with-vazir-en_US',d=read(f,key+'.json');
  d.environment.db_snapshot_sha256='b'.repeat(64);write(f,key+'.json',d);
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
  page.emit('console',{type:()=> 'error',text:()=> 'console-error'});
  assert.deepEqual(second.snapshot().console_errors,['console-error']);
  second.stop();
  assert.equal(page.listenerCount('response'),0);
  assert.equal(page.listenerCount('pageerror'),0);
  assert.equal(page.listenerCount('console'),0);
});


// Durable CI tests of the FULL independently inlined Publisher gate, not a
// separate approximation of its validation logic. The original positive fixture
// is checked before any malicious mutation is applied.
test('G013 publication regression: complete clean fixture passes independent full publisher',()=>{
  const f=makeFixture();
  try {
    const pairs=validateEvidence(f.dir,HEAD);
    assert.equal(pairs.length,36);
    assert.equal(fs.readdirSync(f.dir).filter(n=>n.endsWith('.jpg')).length,32);
    for(const key of PROFILES)assert.equal(read(f,key+'.json').pages.length,18);
    const result=publish(f);
    assert.equal(result.status,0,result.stderr);
  }finally{remove(f);}
});
function publisherSemanticRegression(name,mutate,reason){
  test(name,()=>{
    const f=makeFixture();
    try {
      assert.equal(validateEvidence(f.dir,HEAD).length,36,'input must start as complete positive fixture');
      mutate(f);
      assert.throws(()=>validateEvidence(f.dir,HEAD),/G013_EVIDENCE_REJECTED/);
      const broken=publish(f,{withoutSemanticClosure:true});
      assert.equal(broken.status,0,
        'negative control must demonstrate legacy publisher accepted the mutated, otherwise valid fixture: '+broken.stderr);
      const corrected=publish(f);
      assert.notEqual(corrected.status,0,'actual embedded Publisher accepted unsafe input');
      assert.match(corrected.stderr,/G013_PUBLISH_REJECTED/);
      assert.match(corrected.stderr,reason,'Publisher must reject for intended semantic class');
    }finally{remove(f);}
  });
}
publisherSemanticRegression('G013 Publisher regression: five legal weights plus unauthorized string rejected',f=>{
  const d=read(f,'vazir-settings.json');
  d.options.font_weights.push('CANARY_DO_NOT_PUBLISH_456');
  write(f,'vazir-settings.json',d);
},/font.weights|font weights/i);
publisherSemanticRegression('G013 Publisher regression: five legal weights plus unauthorized object rejected',f=>{
  const d=read(f,'vazir-settings.json');
  d.options.font_weights.push({token:'CANARY_DO_NOT_PUBLISH_456'});
  write(f,'vazir-settings.json',d);
},/font.weights|font weights/i);
publisherSemanticRegression('G013 Publisher regression: comparison Grid PASS with suffix rejected',f=>{
  const d=read(f,'comparison.json');
  d.grid_acceptance['baseline-fa_IR']='PASS CANARY_DO_NOT_PUBLISH_987';
  write(f,'comparison.json',d);
},/comparison grid status|comparison.grid_acceptance/i);

test('G013 database snapshot digest must be a string at both public boundaries',()=>{
  const f=makeFixture();
  try {
    for(const key of PROFILES){
      const filename=key+'.json';
      const d=read(f,filename);
      d.environment.db_snapshot_sha256=[[d.environment.db_snapshot_sha256]];
      write(f,filename,d);
    }
    assert.throws(()=>validateEvidence(f.dir,HEAD),
      /G013_EVIDENCE_REJECTED: G013_PUBLIC_BOUNDARY_REJECTED: .* DB snapshot/);
    const result=publish(f);
    assert.notEqual(result.status,0);
    assert.match(result.stderr,/G013_PUBLISH_REJECTED: DB snapshot identity\b/);
  } finally {remove(f);}
});

// Independent runner-local evidence boundary: mixed valid + malicious JSON must never pass.
expectReject('additional sensitive property in otherwise valid Vazir settings rejected',f=>{
  const d=read(f,'vazir-settings.json');
  d.options.authorization_token='SENSITIVE_MARKER_DO_NOT_PUBLISH';write(f,'vazir-settings.json',d);
});
expectReject('additional sensitive marker in profile page rejected',f=>{
  const key='with-vazir-fa_IR',d=read(f,key+'.json');
  d.pages[0].faces[0].api_secret='SENSITIVE_MARKER_DO_NOT_PUBLISH';write(f,key+'.json',d);
});
expectReject('additional sensitive marker in nested RTL diagnostics rejected',f=>{
  const key='baseline-en_US',d=read(f,key+'.json');
  d.pages[0].rtl_diagnostic={html:{lang:'en-US',dir_attr:'ltr',computed:'ltr'},
    body:{dir_attr:null,computed:'ltr'},visible_inbox:[],grid_mode:null,grid_nodes:0,
    loaded_stylesheets:[],viewport:{width:1280,height:900},
    document_width:1280,body_width:1280,
    debug_private:'SENSITIVE_MARKER_DO_NOT_PUBLISH'};
  write(f,key+'.json',d);
});
expectReject('additional sensitive marker in comparison JSON rejected',f=>{
  const d=read(f,'comparison.json');d.provenance_secret='SENSITIVE_MARKER_DO_NOT_PUBLISH';
  write(f,'comparison.json',d);
});
expectReject('additional sensitive marker in official release JSON rejected',f=>{
  const d=read(f,'official-release.json');d.private_key='SENSITIVE_MARKER_DO_NOT_PUBLISH';
  write(f,'official-release.json',d);
});
test('exact v1.5.0 Vazir default CSS fallback stack is admissible at both independent boundaries',()=>{
  const f=makeFixture();
  try {
    const name='with-vazir-fa_IR';
    const d=read(f,name+'.json');
    d.pages.find(p=>p.name==='overview'&&p.width===1280).body.family=
      "'Vazirmatn', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Liberation Sans', sans-serif";
    write(f,name+'.json',d);
    writeComparison(f.dir,HEAD);
    assert.equal(validateEvidence(f.dir,HEAD).length,36);
    const published=publish(f);
    assert.equal(published.status,0,published.stderr);
  }finally{remove(f);}
});
expectReject('unattributed custom CSS font stays rejected after pinned fallback admission',f=>{
  const name='with-vazir-fa_IR',d=read(f,name+'.json');
  d.pages[0].body.family="'Vazirmatn', UnattributedCustomFamily, Arial";
  write(f,name+'.json',d);
});
expectReject('unsafe free-text font family in complete profile rejected',f=>{
  const key='baseline-en_US',d=read(f,key+'.json');
  d.pages[0].body.family='Arial; token=SENSITIVE_MARKER_DO_NOT_PUBLISH';write(f,key+'.json',d);
});
function workflowShell(name){
  const start=workflow.indexOf('      - name: '+name);
  assert.ok(start>=0,name);
  const step=workflow.slice(start).split(/\n(?=      - |  [a-z][\w-]*:\n)/,1)[0];
  assert.ok(step.includes('        run: |\n'),name);
  return step.split('        run: |\n')[1].replace(/^          /gm,'');
}
const stageName='Stage privacy-checked bounded evidence for upload';
const runtimeName='G013 real official Vazir v1.5.0 isolated profile qualification';
const reportName='Report sanitized G013 result';
const markerText='SYNTHETIC_G013_PRIVATE_CANARY';
const runtimeScript=workflowShell(runtimeName);
const reportScript=workflowShell(reportName);

test('G013 starts after unrelated staging, and public consumers have no raw G013 input',()=>{
  const index=name=>workflow.indexOf('      - name: '+name);
  assert.ok(index(stageName)<index(runtimeName));
  assert.ok(index(runtimeName)<index(reportName));
  assert.ok(index(reportName)<index('Upload bounded runtime evidence only'));
  const stage=workflow.slice(index(stageName),index(runtimeName));
  const upload=workflow.slice(index('Upload bounded runtime evidence only'),workflow.indexOf('\n  publish-admin-ui-evidence:'));
  assert.match(stage,/if: always\(\)/);
  assert.match(upload,/if: always\(\)/);
  assert.match(upload,/path: \/tmp\/wu008-upload\b/);
  const publicPublisher=workflow.slice(workflow.indexOf('\n  publish-admin-ui-evidence:'));
  assert.doesNotMatch(publicPublisher,/G013|g013|vazir-coexistence-evidence/);
});

function nativeGridFailure(f){
  for(const key of ['baseline-fa_IR','with-vazir-fa_IR']){
    const doc=read(f,key+'.json');
    for(const p of doc.pages)if(p.grid_mode){
      p.grid_mode.actual='ltr';
      p.grid_mode.status='FAIL';
    }
    doc.grid_acceptance='FAIL';
    write(f,key+'.json',doc);
  }
  const report=read(f,'comparison.json');
  report.grid_acceptance['baseline-fa_IR']='FAIL';
  report.grid_acceptance['with-vazir-fa_IR']='FAIL';
  write(f,'comparison.json',report);
}

function isolationScenario(name,mutate,{outcome,rtl='NOT_PROVEN',runtimeExit=0,missing=false}){
  test(name,()=>{
    const f=makeFixture();
    const root=fs.mkdtempSync(path.join(os.tmpdir(),'g013-workflow-test-'));
    try {
      mutate(f);
      const shared=path.join(root,'shared');
      const upload=path.join(root,'upload');
      const server=path.join(root,'wu008-wp-server.log');
      const stepOutput=path.join(root,'outputs');
      const stepSummary=path.join(root,'summary');
      const harness=path.join(root,'tests/real-integration');
      fs.mkdirSync(harness,{recursive:true});
      fs.mkdirSync(path.join(shared,'vazir-coexistence-evidence'),{recursive:true});
      fs.writeFileSync(path.join(shared,'vazir-coexistence-evidence','old.jpg'),markerText);
      fs.writeFileSync(path.join(shared,'permitted.txt'),'unrelated evidence\n');
      fs.writeFileSync(server,'unrelated log\n');
      fs.writeFileSync(stepOutput,'');
      const original=name=>fileURLToPath(new URL(name,import.meta.url));
      fs.copyFileSync(original('./g013-public-evidence-contract.mjs'),path.join(harness,'g013-public-evidence-contract.mjs'));
      fs.writeFileSync(path.join(harness,'g013-run-licensed.sh'),`set -euo pipefail
for key in GITHUB_ENV GITHUB_OUTPUT GITHUB_PATH GITHUB_STEP_SUMMARY GITHUB_STATE; do
  test -z "${'$'}{!key+x}"
done
printf '%s\\n' "$TEST_CANARY"
printf '%s\\n' "$TEST_CANARY" >&2
printf '%s\\n' "$TEST_CANARY" >> "$TEST_SERVER"
mkdir -p "$WU008_ARTIFACT_DIR" "$WU008_PACKAGE_DIR"
printf '%s\\n' "$TEST_CANARY" > "$WU008_PACKAGE_DIR/diagnostics.json"
if [[ "$TEST_MISSING" == 1 ]]; then exit 0; fi
cp -a "$TEST_FIXTURE" "$WU008_ARTIFACT_DIR/vazir-coexistence-evidence"
if [[ "$TEST_RUNTIME_EXIT" != 0 ]]; then exit "$TEST_RUNTIME_EXIT"; fi
node "$TEST_PRODUCER" "$WU008_ARTIFACT_DIR/vazir-coexistence-evidence" check
`);
      const env={PATH:process.env.PATH,LC_ALL:'C.UTF-8',RUNNER_TEMP:root,WU008_ARTIFACT_DIR:shared,
        WU008_PGR_SHA:HEAD,TEST_FIXTURE:f.dir,TEST_SERVER:server,TEST_CANARY:markerText,
        TEST_PRODUCER:original('./vazir-evidence-check.mjs'),TEST_RUNTIME_EXIT:String(runtimeExit),
        TEST_MISSING:missing?'1':'0',GITHUB_OUTPUT:stepOutput,GITHUB_STEP_SUMMARY:stepSummary,
        GITHUB_ENV:path.join(root,'github-env'),GITHUB_PATH:path.join(root,'github-path'),
        GITHUB_STATE:path.join(root,'github-state')};
      const shell=(source,extra={})=>spawnSync('bash',['--noprofile','--norc','-eo','pipefail'],{
        cwd:root,input:source,encoding:'utf8',env:{...env,...extra}});
      const stageScript=workflowShell(stageName).replaceAll('/tmp/wu008-upload',upload)
        .replaceAll('/tmp/wu008-wp-server.log',server);
      const stage=shell(stageScript);
      assert.equal(stage.status,0,stage.stderr);
      const run=shell(runtimeScript);
      assert.equal(run.stdout+run.stderr,'');
      assert.equal(run.status,runtimeExit||
        (['PASS','PAIR_PASS_NATIVE_RTL_FAIL'].includes(outcome)?0:1));
      const result=shell(reportScript,{G013_HEAD:HEAD,G013_OUTCOME:run.status===0?'success':'failure',
        G013_RTL:fs.readFileSync(stepOutput,'utf8').match(/^rtl=(PASS|FAIL)\n$/)?.[1]??''});
      const line=`G013 head=${HEAD} qualification=${outcome} fa_IR_RTL=${rtl}\n`;
      assert.equal(result.status,0,result.stderr);
      assert.equal(result.stdout,line);
      assert.equal(fs.readFileSync(stepSummary,'utf8'),line);
      const assertPublicSnapshot=()=>{
        assert.deepEqual(fs.readdirSync(upload).sort(),['permitted.txt','wu008-wp-server.log']);
        assert.equal(fs.readFileSync(path.join(upload,'permitted.txt'),'utf8'),'unrelated evidence\n');
        assert.equal(fs.readFileSync(path.join(upload,'wu008-wp-server.log'),'utf8'),'unrelated log\n');
      };
      assertPublicSnapshot();
      const privateRoot=fs.readdirSync(root).find(item=>item.startsWith('g013.'));
      assert.ok(privateRoot);
      assert.ok(fs.readFileSync(path.join(root,privateRoot,'diagnostics.log'),'utf8').includes(markerText));
      if(outcome==='PASS' && rtl==='PASS'){
        // Negative control: a late snapshot copies G013's shared HTTP diagnostic.
        assert.equal(shell(stageScript).status,0);
        assert.throws(assertPublicSnapshot,assert.AssertionError);
      }
    } finally {remove(f);fs.rmSync(root,{recursive:true,force:true});}
  });
}

isolationScenario('valid G013 evidence emits only the sanitized PASS summary',()=>{},
  {outcome:'PASS',rtl:'PASS'});
isolationScenario('G013 JPEG comment payload stays in the runner even after both evidence gates pass',f=>{
  const file=path.join(f.dir,'baseline-fa_IR-overview-1280.jpg');
  const bytes=fs.readFileSync(file);
  const comment=Buffer.from(markerText);
  const header=Buffer.from([0xff,0xfe,0,comment.length+2]);
  fs.writeFileSync(file,Buffer.concat([bytes.subarray(0,2),header,comment,bytes.subarray(2)]));
},{outcome:'PASS',rtl:'PASS'});
isolationScenario('sensitive G013 JSON and diagnostics cannot enter public output',f=>{
  const doc=read(f,'vazir-settings.json');
  doc.options.password=markerText;
  write(f,'vazir-settings.json',doc);
},{outcome:'FAIL'});
test('matched pre-existing Persian ag-ltr stays FAIL in both profiles while the font pair is valid',()=>{
  const f=makeFixture();
  try {
    nativeGridFailure(f);
    assert.equal(read(f,'baseline-fa_IR.json').grid_acceptance,'FAIL');
    assert.equal(read(f,'with-vazir-fa_IR.json').grid_acceptance,'FAIL');
    assert.equal(validateEvidence(f.dir,HEAD).length,36);
    assert.equal(publish(f).status,0,'independent publisher must retain the paired pre-existing finding');
  }finally{remove(f);}
});
isolationScenario('G013 paired fa_IR ag-ltr is reported, never mislabeled as native RTL PASS',
  nativeGridFailure,{outcome:'PAIR_PASS_NATIVE_RTL_FAIL',rtl:'FAIL'});
expectReject('same incorrect English native Grid mode cannot count as a valid LTR control',f=>{
  for(const key of ['baseline-en_US','with-vazir-en_US']){
    const d=read(f,key+'.json');
    for(const p of d.pages)if(p.grid_mode){p.grid_mode.actual='rtl';p.grid_mode.status='FAIL';}
    d.grid_acceptance='FAIL';write(f,key+'.json',d);
  }
  const d=read(f,'comparison.json');
  d.grid_acceptance['baseline-en_US']='FAIL';
  d.grid_acceptance['with-vazir-en_US']='FAIL';write(f,'comparison.json',d);
});
isolationScenario('malformed G013 evidence fails without raw fallback',f=>{
  fs.writeFileSync(path.join(f.dir,'comparison.json'),'{'+markerText);
},{outcome:'FAIL'});
isolationScenario('missing G013 evidence fails the independent gate even with a zero runtime exit',()=>{},
  {outcome:'FAIL',missing:true});
isolationScenario('G013 diagnostic failure keeps its exact exit code and does not leak output',()=>{},
  {outcome:'FAIL',runtimeExit:23});

test('skipped G013 and unexpected report values cannot become PASS or reflect input',()=>{
  const root=fs.mkdtempSync(path.join(os.tmpdir(),'g013-workflow-summary-'));
  try {
    const result=spawnSync('bash',['-e','-o','pipefail'],{input:reportScript,encoding:'utf8',
      env:{...process.env,G013_HEAD:HEAD,G013_OUTCOME:'skipped',G013_RTL:'PASS '+markerText,
        GITHUB_STEP_SUMMARY:path.join(root,'summary')}});
    assert.equal(result.status,0,result.stderr);
    assert.equal(result.stdout,`G013 head=${HEAD} qualification=NOT_PROVEN fa_IR_RTL=NOT_PROVEN\n`);
  } finally {fs.rmSync(root,{recursive:true,force:true});}
});

expectReject('browser.version() mixed valid version and unapproved text',f=>{
 const key='baseline-en_US',d=read(f,key+'.json');
 d.environment.chromium='140.0.7339.16 SENSITIVE_TOKEN_MARKER_20261105';write(f,key+'.json',d);
});
expectReject('FontFace.weight mixed valid weight and unapproved text',f=>{
 const key='with-vazir-fa_IR',d=read(f,key+'.json');
 d.pages.find(p=>p.faces.length).faces[0].weight='400 HIDDENTOKEN9876543210';write(f,key+'.json',d);
});
expectReject('pinned Gravity Forms version mixed with unapproved text',f=>{
 const key='baseline-en_US',d=read(f,key+'.json');
 d.environment.gravity={gravityforms:'3.1.1.1 SENSITIVE_TOKEN_MARKER_20261105'};
 write(f,key+'.json',d);
});
test('G013 keyboard focus oracle checks actual activeElement, not a surviving Tom Select wrapper',()=>{
  const producer=fs.readFileSync(new URL('./vazir-coexistence-browser.mjs',import.meta.url),'utf8');
  assert.match(producer,/document\.activeElement===el/);
  assert.match(producer,/root\.contains\(document\.activeElement\)/);
  assert.doesNotMatch(producer,/focus_verified:\s*await\s+page\.locator\([^\n]*\.count\(\)/);
});
expectReject('forged Advanced Select focus result cannot pass both evidence boundaries',f=>{
  const key='with-vazir-fa_IR',d=read(f,key+'.json');
  const p=d.pages.find(p=>p.name==='gravityperks'&&p.width===390);
  p.interaction={type:'authentic-file-upload-and-tom-select',
    file_preview:true,selected_value:'NONEMPTY',focus_verified:false};
  write(f,key+'.json',d);
});
expectReject('unapproved activeElement tag',f=>{
 const key='baseline-en_US',d=read(f,key+'.json');
 d.pages[0].active_control='HIDDENTOKEN1234';write(f,key+'.json',d);
});
test('dotted browser versions and CSS FontFace weight ranges preserve real representation',()=>{
 const f=makeFixture();
 try{
   for(const key of PROFILES){
     const d=read(f,key+'.json');
     d.environment.chromium='140.0.7339.16';
     d.environment.php='8.2.35';
     d.pages[0].body.weight='450.5';
     if(key==='with-vazir-fa_IR')d.pages.find(p=>p.faces.length).faces[0].weight='100 900';
     write(f,key+'.json',d);
   }
   const pairs=validateEvidence(f.dir,HEAD);
   assert.equal(pairs.length,36);
   assert.equal(publish(f).status,0,'publisher rejected legitimate CSS/browser values');
 }finally{remove(f);}
});
