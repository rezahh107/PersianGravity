import fs from 'node:fs';
import path from 'node:path';
const root=process.env.WU008_ARTIFACT_DIR,head=process.env.WU008_PGR_SHA;
if(!root||!/^[a-f0-9]{40}$/.test(head||''))throw Error('Missing exact-Head WU008 output');
const dir=path.join(root,'module-disable-evidence');
const names=['used-fa_IR','used-en_US','unused-fa_IR','unused-en_US'];
const reports=names.map(n=>JSON.parse(fs.readFileSync(path.join(dir,'decision-'+n+'.json'),'utf8')));
const lines=[
'# PersianGravity — Module Disable Decision (Authenticated WU008)',
'',
'**Source commit:** '+head,
'',
'Before: [historical authenticated WU008 Overview confirmation](https://github.com/rezahh107/PersianGravity/blob/9cadb44938566eb3719484abe6a46a137e6b5d0c/admin-ui-evidence/ui-normal-fa_IR-module-confirm-390.jpg) — old warning at top and confirmation inside the lower module card.',
'',
'After: genuine logged-in WordPress screenshots and server responses. All module toggles and the disposable GF form were restored. Browser-only crafted requests are explicitly negative controls.',
'',
'| Locale | Server fixture | Width | Outcomes | Screenshots |',
'|---|---|---:|---|---|',
];
for(const report of reports){
  if(report.status!=='CAPTURED'||report.exact_head!==head||report.cases.length!==3)
    throw Error('Incomplete module-disable evidence report: '+report.locale+' '+report.phase);
  for(const c of report.cases){
    if(c.status!=='PASS')throw Error('Failed module-disable case '+report.locale+' '+c.width);
    lines.push('| '+report.locale+' | '+report.phase+' | '+c.width+'px | '+
      c.checks.join(', ')+' | '+c.images.map(n=>'[Image]('+n+')').join(' · ')+' |');
  }
}
lines.push('',
'**Security and interaction checks:** real UNKNOWN first POST and confirmed second POST, no module change before consent, top decision with bilingual identification, exact keyboard focus and mobile widths, cancel, USED with real GF metadata, stale/forged GET, forged USED POST, invalid nonce and unauthenticated POST. UNUSED direct POST remains single-stage.',
'',
'**Limits:** this is automated browser and actual server evidence, not a human usability study, full screen-reader audit, or deployment approval. The before captures are from the independently verified historical Head; the after captures are from the exact commit above.',
'');
fs.writeFileSync(path.join(dir,'README.md'),lines.join('\n'));
console.log('MODULE_DISABLE_DECISION_EVIDENCE: four reports, twelve cases, linked screenshots');
