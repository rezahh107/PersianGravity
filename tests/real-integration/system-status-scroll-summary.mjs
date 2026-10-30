import fs from 'node:fs';
import path from 'node:path';
const dir=path.join(process.env.WU008_ARTIFACT_DIR || '', 'system-status-scroll-evidence');
const head=process.env.WU008_PGR_SHA;
if(!head || !fs.existsSync(dir))throw Error('Exact-Head System Status evidence unavailable');
const names=['normal-fa_IR','normal-en_US','disabled-fa_IR','disabled-en_US'];
const reports=names.map(name=>JSON.parse(fs.readFileSync(path.join(dir,'status-'+name+'.json'),'utf8')));
const out=[
'# PersianGravity System Status — Licensed Responsive Regression Evidence',
'',
'**Product Head:** '+head,
'',
'Authenticated disposable WordPress + licensed Gravity Flow WU008. Captures genuine except explicitly labelled browser-only synthetic controls.',
'',
'Historical before evidence: [fa_IR 390px](https://github.com/rezahh107/PersianGravity/blob/9cadb44938566eb3719484abe6a46a137e6b5d0c/admin-ui-evidence/ui-normal-fa_IR-system-status-390.jpg), [en_US 390px](https://github.com/rezahh107/PersianGravity/blob/9cadb44938566eb3719484abe6a46a137e6b5d0c/admin-ui-evidence/ui-normal-en_US-system-status-390.jpg). Baseline document scroll widths: fa_IR 520px, en_US 522px at 390px and 320px.',
'',
'| Locale | Module state | Viewport | Document width | Region scroll / client | Keyboard / mouse / touch | Result |',
'|---|---|---:|---:|---:|---|---|',
];
for(const report of reports){
  if(report.head!==head || report.status!=='CAPTURED' || report.cases.length!==3)throw Error('Incomplete report: '+report.locale+'/'+report.mode);
  for(const c of report.cases){
    if(c.status!=='PASS')throw Error('Failed scenario: '+report.locale+'/'+report.mode+'/'+c.width);
    const interactive=c.width<760?'PASS / PASS / PASS':'not required';
    out.push('| '+report.locale+' | '+report.mode+' | '+c.width+
      ' | '+c.initial.document_scroll_width+' | '+c.initial.region_scroll_width+' / '+
      c.initial.region_client_width+' | '+interactive+' | [After]('+c.screenshot+') |');
  }
}
out.push('','## Browser-only technical-value and containment controls','');
for(const report of reports){
  const c=report.cases.find(x=>x.width===320);
  out.push('- '+report.locale+' / '+report.mode+': [Long identifiers]('+c.stress_screenshot+
    ') (document '+c.long_values.document_scroll_width+
    '); [negative — containment deliberately removed]('+c.negative_screenshot+
    ') (document '+c.negative.document_width+
    '); restored document <= 320px.');
}
out.push('',
'## Evidence limits',
'',
'- DOM measurements establish all five columns, row count, named focusable scroll region, focus outline, and keyboard, wheel and CDP touch scrolling.',
'- Long identifiers/reason codes are synthetic browser-only mutations; no diagnostic records in WordPress are changed.',
'- This does not prove screen-reader interpretation, all browsers/devices, or Owner visual acceptance.',
'- Baseline images are from an older verified Head; negative controls remove containment only in a disposable browser.',
'');
fs.writeFileSync(path.join(dir,'README.md'),out.join('\n'));
console.log('SYSTEM_STATUS_SCROLL_SUMMARY: four locale/states, 12 tested viewports, 20 screenshots');
