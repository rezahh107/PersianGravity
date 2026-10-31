import fs from 'node:fs';
import path from 'node:path';
const dir=process.argv[2];
const head=process.env.WU008_PGR_SHA || process.env.WU008_EVIDENCE_EXPECTED_HEAD;
if (!dir || !head || !/^[0-9a-f]{40}$/.test(head)) throw Error('G013 exact Head and evidence path required');
const required=new Set(['README.md','comparison.json','official-release.json','vazir-settings.json']);
for(const profile of ['baseline','with-vazir']) for(const locale of ['fa_IR','en_US']) {
  const key=profile+'-'+locale;
  required.add(key+'.json');
  for(const width of [1280,390])for(const page of ['overview','gravity-form','gravityview','gravityperks'])
    required.add(key+'-'+page+'-'+width+'.jpg');
}
const fail=msg=>{throw Error('G013_EVIDENCE_REJECTED: '+msg);};
if(!fs.existsSync(dir)||!fs.lstatSync(dir).isDirectory()||fs.lstatSync(dir).isSymbolicLink())fail('directory');
function readJson(file){try{return JSON.parse(fs.readFileSync(path.join(dir,file),'utf8'));}catch{fail('invalid JSON '+file);}}
function inspect(){
  const actual=fs.readdirSync(dir,{withFileTypes:true});
  if(actual.length!==required.size)fail('missing or surplus evidence files');
  for(const e of actual){
    if(!required.has(e.name)||!e.isFile()||e.isSymbolicLink())fail('unexpected artifact '+e.name);
    const size=fs.statSync(path.join(dir,e.name)).size;
    if(size<50||size>4000000)fail('bad file size '+e.name);
    if(e.name.endsWith('.jpg')){
      const data=fs.readFileSync(path.join(dir,e.name));
      if(data[0]!==0xff||data[1]!==0xd8||data.at(-2)!==0xff||data.at(-1)!==0xd9)
        fail('corrupt JPEG markers '+e.name);
    }
  }
  const sheet=fs.readFileSync(path.join(dir,'README.md'),'utf8');
  const release=readJson('official-release.json');
  const settings=readJson('vazir-settings.json');
  if(release.status!=='PASS'||release.version!=='1.5.0'||
     release.zip_sha256!=='95fdc3774d6079c34c04331bb8acf980a699e02a215e322742dccbc4109802f5')
    fail('verified release provenance');
  if(settings.version!=='1.5.0'||settings.active!==true||
     !['enable_frontend','enable_admin','enable_gravity_forms'].every(key=>settings.options?.[key]===true))
    fail('authentic Vazir settings');
  const report=readJson('comparison.json');
  if(report.schema!=='g013-vazir-compare-v1'||report.exact_head!==head||
      report.status!=='PASS'||report.profiles?.length!==4)fail('comparison identity');
  for(const profile of ['baseline','with-vazir'])for(const locale of ['fa_IR','en_US']){
    const key=profile+'-'+locale;
    const d=readJson(key+'.json');
    if(d.schema!=='g013-vazir-coexistence-v1'||d.exact_head!==head||
       d.profile!==(profile==='baseline'?'BASELINE':'WITH_VAZIR')||
       d.locale!==locale||d.status!=='PASS'||d.pages?.length!==18)
      fail('profile identity/real matrix '+key);
    if(profile==='with-vazir'&&
       (d.vazir_version!=='1.5.0'||
        d.expected_vazir_sha256!=='95fdc3774d6079c34c04331bb8acf980a699e02a215e322742dccbc4109802f5'))
      fail('official plugin provenance '+key);
    const pairs=new Set();
    for(const p of d.pages){
      const id=p.name+'-'+p.width;
      if(pairs.has(id)||!['overview','settings','scanner','system-status','gravity-form','gravity-flow','flow-frontend','gravityview','gravityperks'].includes(p.name)
        ||![1280,390].includes(p.width)||p.document_width<1||!p.body?.family)
        fail('invalid browser case '+key+' '+id);
      pairs.add(id);
      if(p.image && (!required.has(p.image)||!sheet.includes(']('+p.image+')')))
        fail('unreferenced capture '+p.image);
    }
    if(pairs.size!==18)fail('duplicate/incomplete case '+key);
    for(const width of [1280,390])for(const page of ['overview','gravity-form','gravityview','gravityperks']){
      const file=key+'-'+page+'-'+width+'.jpg';
      if(!sheet.includes(']('+file+')'))fail('missing image link '+file);
    }
    if(!report.profiles.includes(key))fail('profile omitted from comparison '+key);
  }
  return report;
}
if(process.argv[3]==='check'){
  inspect();
  console.log('G013_EVIDENCE_GATE_PASS exact Head '+head+'; four profiles, 72 browser cases, 32 screenshots');
}else if(process.argv[3]==='compare'){
  const profiles=['baseline-fa_IR','baseline-en_US','with-vazir-fa_IR','with-vazir-en_US'];
  const docs=profiles.map(p=>readJson(p+'.json'));
  for(let i=0;i<docs.length;i++){
    if(docs[i].status!=='PASS'||docs[i].exact_head!==head||docs[i].pages?.length!==12)
      fail('unqualified browser profile '+profiles[i]);
  }
  const changes=[];
  for(let localeIndex=0;localeIndex<2;localeIndex++){
    const base=docs[localeIndex],withFont=docs[2+localeIndex];
    for(const b of base.pages){
      const v=withFont.pages.find(p=>p.width===b.width&&p.name===b.name);
      if(!v)fail('missing comparable real page');
      changes.push({locale:b.locale||base.locale,page:b.name,width:b.width,
        baseline_document_width:b.document_width,with_vazir_document_width:v.document_width,
        baseline_family:b.body?.family,with_vazir_family:v.body?.family,
        classification:v.document_width>b.width&&b.document_width<=b.width?
          'WITH_VAZIR_ONLY_OVERFLOW':v.document_width>b.width&&b.document_width>b.width?
          'PRESENT_IN_BOTH':b.document_width>b.width?'PREEXISTING_BASELINE_ONLY':
          'NO_DOCUMENT_OVERFLOW_OBSERVED'});
    }
  }
  const output={schema:'g013-vazir-compare-v1',exact_head:head,status:'PASS',profiles,
    vazir_version:'1.5.0',vazir_sha256:'95fdc3774d6079c34c04331bb8acf980a699e02a215e322742dccbc4109802f5',
    comparisons:changes,interpretation:'Measured observations only; font differences are expected, not automatically defects.',
    historic_system_status:'Preserve PR #96 six historical findings; PR #97 later responsive repair already merged before G013 base.',
    unproven:['Owner live deployment settings','unreached GravityView editor portals and dynamic Perks UI','screen-reader speech','other vendor/plugin versions']};
  fs.writeFileSync(path.join(dir,'comparison.json'),JSON.stringify(output,null,2)+'\n');
  const lines=['# G013 real Vazir coexistence — verified browser comparison','',
    'Exact PersianGravity Head: '+head,'Vazir release: v1.5.0 (official SHA-256 '+output.vazir_sha256+')',
    'Four authenticated real WordPress/Chromium profiles: BASELINE and WITH_VAZIR, fa_IR/en_US.','',
    'Profile screenshots (1280px and 390px):',''];
  for(const p of profiles)for(const width of [1280,390])for(const name of ['overview','gravity-form','gravityview','gravityperks']){
    const img=p+'-'+name+'-'+width+'.jpg';
    lines.push('- ['+img+']('+img+')');
  }
  lines.push('', 'Measured results: comparison.json. '+output.interpretation,
    'No new product fix, release or compatibility claim is implied.',
    'Settings/activated plugin state was verified in the disposable runner; no live Owner site was read.');
  fs.writeFileSync(path.join(dir,'README.md'),lines.join('\n')+'\n');
  inspect();
  console.log('G013_COMPARISON_PASS profiles=4 real_cases=72');
}else fail('unknown operation; use compare or check');
