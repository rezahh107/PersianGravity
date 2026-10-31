import fs from 'node:fs';
import path from 'node:path';

export const HEAD_RE = /^[0-9a-f]{40}$/;
export const VAZIR_SHA = '95fdc3774d6079c34c04331bb8acf980a699e02a215e322742dccbc4109802f5';
export const PROFILES = ['baseline-fa_IR','baseline-en_US','with-vazir-fa_IR','with-vazir-en_US'];
export const SURFACES = ['overview','settings','scanner','system-status','gravity-form','gravity-flow','flow-frontend','gravityview','gravityperks'];
export const WIDTHS = [1280,390];
export const SCREENSHOTS = ['overview','gravity-form','gravityview','gravityperks'];
const fail = message => { throw Error('G013_EVIDENCE_REJECTED: ' + message); };
const record = value => value && typeof value === 'object' && !Array.isArray(value);
const finite = value => typeof value === 'number' && Number.isFinite(value);
const equal = (a,b) => JSON.stringify(a) === JSON.stringify(b);
const caseKey = p => p.name + '@' + p.width;
const expect = (condition,message) => { if (!condition) fail(message); };
const file = (dir,name) => path.join(dir,name);
const read = (dir,name) => {
  try { return JSON.parse(fs.readFileSync(file(dir,name),'utf8')); }
  catch { fail('invalid JSON ' + name); }
};

export function classify(b,v,width) {
  expect(finite(b) && finite(v) && WIDTHS.includes(width), 'unmeasurable comparison');
  return v > width ? (b > width ? 'PRESENT_IN_BOTH' : 'WITH_VAZIR_ONLY_OVERFLOW')
    : (b > width ? 'PREEXISTING_BASELINE_ONLY' : 'NO_DOCUMENT_OVERFLOW_OBSERVED');
}
export function expectedPairs(docs) {
  const output=[];
  for (const locale of ['fa_IR','en_US']) {
    const baseline=docs.get('baseline-'+locale), withFont=docs.get('with-vazir-'+locale);
    expect(baseline && withFont, 'missing paired profile ' + locale);
    const withMap=new Map(withFont.pages.map(p=>[caseKey(p),p]));
    for (const b of baseline.pages) {
      const v=withMap.get(caseKey(b));
      expect(v, 'unpaired browser case ' + locale + '/' + caseKey(b));
      output.push({locale,page:b.name,width:b.width,
        baseline_document_width:b.document_width,with_vazir_document_width:v.document_width,
        baseline_family:b.body.family,with_vazir_family:v.body.family,
        classification:classify(b.document_width,v.document_width,b.width)});
    }
  }
  expect(output.length===36, 'comparison matrix must contain exactly 36 pairs');
  return output;
}
function validateProfile(d,key,head) {
  const isVazir=key.startsWith('with-vazir');
  const locale=key.endsWith('fa_IR')?'fa_IR':'en_US';
  expect(record(d) && d.schema==='g013-vazir-coexistence-v1' && d.status==='PASS' &&
    d.exact_head===head && d.locale===locale &&
    d.profile===(isVazir?'WITH_VAZIR':'BASELINE'), 'profile identity ' + key);
  expect(isVazir ? (d.vazir_version==='1.5.0' && d.expected_vazir_sha256===VAZIR_SHA) :
    (d.vazir_version===null && d.expected_vazir_sha256===null), 'profile plugin identity ' + key);
  expect(typeof d.persiangravity_version==='string' && d.persiangravity_version.length>0 &&
    record(d.environment) && typeof d.environment.chromium==='string', 'runtime identity ' + key);
  expect(Array.isArray(d.pages) && d.pages.length===18, '18 observations required ' + key);
  const expected=new Set(SURFACES.flatMap(name=>WIDTHS.map(width=>name+'@'+width)));
  const visited=new Set();
  const byWidth=new Map(WIDTHS.map(width=>[width,[]]));
  for (const p of d.pages) {
    const id=caseKey(p);
    expect(record(p) && expected.has(id) && !visited.has(id), 'missing/duplicate/extra case '+key+'/'+id);
    visited.add(id);byWidth.get(p.width).push(p);
    expect(p.viewport===p.width && finite(p.document_width) && p.document_width>=1 &&
      p.document_width<=200000 && ['rtl','ltr'].includes(p.direction) &&
      record(p.body) && typeof p.body.family==='string' && p.body.family.length>0 &&
      ['rtl','ltr'].includes(p.body.direction) &&
      finite(p.body.width) && finite(p.body.height) && p.body.width>=0 && p.body.height>=0,
      'invalid geometry/style ' + key+'/'+id);
    expect(Array.isArray(p.font_network) && Array.isArray(p.page_errors) && Array.isArray(p.faces),
      'missing page-scoped network/errors/faces ' + key+'/'+id);
    expect(p.font_network.every(x=>record(x) && (x.status==='FAILED' ||
      Number.isInteger(x.status) && x.status>=100 && x.status<=599) &&
      typeof x.url==='string' && /^[a-z0-9_.-]+\.woff2$/i.test(x.url)) &&
      p.page_errors.every(x=>typeof x==='string' && x.length<=170) &&
      p.faces.every(x=>record(x) && typeof x.family==='string' &&
        typeof x.weight==='string' && ['unloaded','loading','loaded','error'].includes(x.status)),
      'malformed font or error observation ' + key+'/'+id);
    const image=SCREENSHOTS.includes(p.name) ? key+'-'+p.name+'-'+p.width+'.jpg' : null;
    expect((p.image||null)===image, 'incorrect screenshot mapping ' + key+'/'+id);
    if (!isVazir) {
      expect(!p.faces.some(x=>/vazirmatn/i.test(x.family) && x.status==='loaded') &&
        !p.font_network.some(x=>x.status===200) &&
        ![p.body,p.headline,p.label,p.input,p.button].some(x=>x && /vazirmatn/i.test(x.family)),
        'BASELINE typography contaminated ' + key+'/'+id);
    }
  }
  expect(visited.size===18, 'missing browser cases ' + key);
  // Real browser caching and selector-specific exclusions mean not every page must
  // fetch a font. Each viewport must nevertheless demonstrate independent loading,
  // delivery and actual computed use, never borrow a desktop-only observation.
  if (isVazir && locale==='fa_IR') for (const width of WIDTHS) {
    const pages=byWidth.get(width);
    expect(pages.some(p=>p.faces.some(x=>/vazirmatn/i.test(x.family) && x.status==='loaded')) &&
      pages.some(p=>p.font_network.some(x=>x.status===200)) &&
      pages.some(p=>[p.body,p.headline,p.label,p.input,p.button].some(
        x=>x && /vazirmatn/i.test(x.family))),
      'required mobile/desktop real font evidence ' + key+'/'+width);
  }
}
function jpegDimensions(bytes,name) {
  expect(bytes.length>=100 && bytes[0]===255 && bytes[1]===216, 'corrupt JPEG SOI '+name);
  let pos=2,frame=null,scan=false,ended=false;
  while(pos<bytes.length) {
    expect(bytes[pos]===255, 'corrupt JPEG marker '+name);
    while(bytes[pos]===255)pos++;
    const marker=bytes[pos++];
    if(marker===0xd9){ended=true;break;}
    if(marker===0x01 || marker>=0xd0&&marker<=0xd7)continue;
    expect(pos+2<=bytes.length,'truncated JPEG segment '+name);
    const length=bytes.readUInt16BE(pos);
    expect(length>=2 && pos+length<=bytes.length,'bad JPEG segment '+name);
    if([0xc0,0xc1,0xc2,0xc3,0xc5,0xc6,0xc7,0xc9,0xca,0xcb,0xcd,0xce,0xcf].includes(marker)) {
      expect(length>=8 && !frame,'bad JPEG frame '+name);
      frame={height:bytes.readUInt16BE(pos+3),width:bytes.readUInt16BE(pos+5)};
      expect(frame.height>0 && frame.width>0,'empty JPEG frame '+name);
    }
    pos+=length;
    if(marker===0xda) {
      scan=true;
      while(pos<bytes.length-1){
        if(bytes[pos]!==255){pos++;continue;}
        const next=bytes[pos+1];
        if(next===0 || next>=0xd0&&next<=0xd7){pos+=2;continue;}
        if(next===255){pos++;continue;}
        break;
      }
    }
  }
  expect(frame && scan && ended && pos===bytes.length,'incomplete/corrupt JPEG '+name);
  return frame;
}
function validateFiles(dir,sheet) {
  const required=new Set(['README.md','comparison.json','official-release.json','vazir-settings.json']);
  for (const key of PROFILES) {
    required.add(key+'.json');
    for(const width of WIDTHS)for(const name of SCREENSHOTS)required.add(key+'-'+name+'-'+width+'.jpg');
  }
  const entries=fs.readdirSync(dir,{withFileTypes:true});
  expect(entries.length===required.size,'missing/extra files');
  const linked=[...sheet.matchAll(/\]\(([a-z0-9_-]+\.jpg)\)/gi)].map(x=>x[1]);
  expect(linked.length===32 && new Set(linked).size===32,'missing/duplicate screenshot links');
  for(const entry of entries) {
    expect(required.has(entry.name) && entry.isFile() && !entry.isSymbolicLink(),
      'untrusted artifact type/name '+entry.name);
    const bytes=fs.readFileSync(file(dir,entry.name));
    expect(bytes.length>=50 && bytes.length<=4000000,'bad file size '+entry.name);
    if(entry.name.endsWith('.jpg')) {
      expect(linked.includes(entry.name),'unlinked JPEG '+entry.name);
      const dims=jpegDimensions(bytes,entry.name);
      const width=Number(entry.name.match(/-(1280|390)\.jpg$/)?.[1]);
      expect(dims.width>=width && dims.height>0,'JPEG geometry '+entry.name);
    }
  }
}
export function validateEvidence(dir,head,{comparison=true,files=true}={}) {
  expect(HEAD_RE.test(head||''),'exact Head missing/malformed');
  expect(fs.existsSync(dir) && fs.lstatSync(dir).isDirectory() &&
    !fs.lstatSync(dir).isSymbolicLink(),'unsafe evidence directory');
  const release=read(dir,'official-release.json'),settings=read(dir,'vazir-settings.json');
  expect(record(release) && release.status==='PASS' && release.version==='1.5.0' &&
    release.zip_sha256===VAZIR_SHA && release.release_tag==='v1.5.0' &&
    release.entrypoint==='vazir-font-wp/vazir-font-wp.php', 'official release identity');
  expect(record(settings) && settings.version==='1.5.0' && settings.active===true &&
    record(settings.options) && ['enable_frontend','enable_admin','enable_gravity_forms']
      .every(key=>settings.options[key]===true) &&
    Array.isArray(settings.options.font_weights) &&
    ['300','400','500','700','900'].every(w=>settings.options.font_weights.includes(w)),
    'incompatible Vazir settings');
  const docs=new Map();
  for (const key of PROFILES) {
    const d=read(dir,key+'.json');
    validateProfile(d,key,head);
    docs.set(key,d);
  }
  const versions=new Set([...docs.values()].map(d=>d.persiangravity_version));
  expect(versions.size===1,'incompatible runtime plugin versions');
  const expected=expectedPairs(docs);
  if(comparison) {
    const report=read(dir,'comparison.json');
    expect(record(report) && report.schema==='g013-vazir-compare-v1' &&
      report.status==='PASS' && report.exact_head===head &&
      report.vazir_version==='1.5.0' && report.vazir_sha256===VAZIR_SHA &&
      equal(report.profiles,PROFILES) && Array.isArray(report.comparisons) &&
      report.comparisons.length===36,'incomplete/stale comparison');
    const actual=new Map();
    for(const item of report.comparisons) {
      expect(record(item) && ['fa_IR','en_US'].includes(item.locale) &&
        SURFACES.includes(item.page) && WIDTHS.includes(item.width),
        'unknown comparison identity');
      const key=item.locale+'@'+item.page+'@'+item.width;
      expect(!actual.has(key),'duplicate comparison '+key);actual.set(key,item);
    }
    for(const item of expected) {
      const key=item.locale+'@'+item.page+'@'+item.width;
      expect(actual.has(key) && equal(actual.get(key),item),
        'fabricated/inconsistent paired measurement '+key);
    }
  }
  if(files)validateFiles(dir,fs.readFileSync(file(dir,'README.md'),'utf8'));
  return expected;
}
export function writeComparison(dir,head) {
  const pairs=validateEvidence(dir,head,{comparison:false,files:false});
  const output={schema:'g013-vazir-compare-v1',exact_head:head,status:'PASS',profiles:PROFILES,
    vazir_version:'1.5.0',vazir_sha256:VAZIR_SHA,comparisons:pairs,
    interpretation:'Measured observations only; font differences are expected, not automatically defects.',
    historic_system_status:'PR #96 historical findings predate PR #97 responsive repair.',
    unproven:['Owner live settings','unreached vendor portals','screen-reader speech','other vendor versions']};
  fs.writeFileSync(file(dir,'comparison.json'),JSON.stringify(output,null,2)+'\n');
  const lines=['# G013 real Vazir coexistence — browser evidence','',
    'Exact PersianGravity Head: '+head,'Official Vazir v1.5.0: '+VAZIR_SHA,
    'Four authenticated profiles, nine surfaces, two viewports: 72 observations / 36 paired comparisons.','',
    'Screenshots (1280px and 390px):',''];
  for(const key of PROFILES)for(const width of WIDTHS)for(const name of SCREENSHOTS){
    const image=key+'-'+name+'-'+width+'.jpg';
    lines.push('- ['+image+']('+image+')');
  }
  lines.push('','Measurements: comparison.json. These files do not prove browser authenticity independently.');
  fs.writeFileSync(file(dir,'README.md'),lines.join('\n')+'\n');
  validateEvidence(dir,head);
  return output;
}
if(process.argv[1] && import.meta.url===new URL('file://'+path.resolve(process.argv[1])).href) {
  const dir=process.argv[2],operation=process.argv[3];
  const head=process.env.WU008_PGR_SHA || process.env.WU008_EVIDENCE_EXPECTED_HEAD;
  if(operation==='compare'){
    writeComparison(dir,head);
    console.log('G013_COMPARISON_PASS: 4 profiles, 72 observations, 36 paired comparisons, 32 JPEGs');
  } else if(operation==='check'){
    validateEvidence(dir,head);
    console.log('G013_EVIDENCE_GATE_PASS: exact Head, 72 observations, 36 valid comparisons, 32 JPEGs');
  } else fail('unknown operation: compare or check required');
}
