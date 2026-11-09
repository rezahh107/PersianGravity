/** Fail-closed independent report gate; checks every matrix cell and identity. */
import fs from 'node:fs';
import path from 'node:path';
const d=process.env.G014_OUTPUT_DIR;
if(!d)throw Error('G014_OUTPUT_DIR is required');
const p=path.join(d,'report.json');
const r=JSON.parse(fs.readFileSync(p,'utf8'));
const subject='311c2f5d188aec7254e11d37a351510736bd97de';
const gpp='a159e22c54f102ff2f73c365a97ade6beb90b63e';
const flow='ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404';
const check=(ok,msg)=>{if(!ok)throw Error('G014_EVIDENCE_REJECT '+msg)};
check(r.subject===subject&&r.gpp===gpp&&r.flow_sha256===flow,'source identities drifted');
check(r.harness===process.env.HARNESS_SHA&&r.harness!==r.subject,'harness / subject confused');
check(r.schema==='g014-gpp-runtime-v1'&&r.data_class==='SYNTHETIC_NON_PII','schema/synthetic-only contract');
check(r.status==='PASS'&&typeof r.browser==='string'&&r.browser.length>2,'no completed browser PASS');
const expected=[];
for(const site of ['fa_IR','en_US'])for(const user of ['fa_IR','en_US'])for(const [i,mode] of ['off','on','off'].entries())
  for(const width of [1280,390])for(const surface of ['admin','frontend'])expected.push(`${site}-${user}-${i}-${mode}-${surface}-${width}`);
check(Array.isArray(r.scenarios)&&r.scenarios.length===48,'incomplete 48-cell matrix');
check(new Set(r.scenarios.map(x=>x.id)).size===48,'duplicate scenario IDs');
for(const id of expected){
  const s=r.scenarios.find(x=>x.id===id);
  check(s?.status==='PASS',`missing/failing scenario ${id}`);
  check(s?.grid?.row_count>0&&s.grid.config_rows>=20,`not genuinely populated ${id}`);
  check(s.grid.native_mode==='ag-rtl'||s.grid.native_mode==='ag-ltr',`no actual grid class ${id}`);
  const effective=s.surface==='admin'?s.user:s.site;
  const expectedRtl=s.mode==='on'&&effective==='fa_IR';
  check(s.grid.native_mode===(expectedRtl?'ag-rtl':'ag-ltr'),`mode/control mismatch ${id}`);
  check(s.mode==='on'?s.grid.has_option&&s.grid.enableRtl===expectedRtl:!s.grid.has_option,`real constructor config incorrect ${id}`);
  check(Array.isArray(s.grid.columns)&&s.grid.columns.length===5,`five-column composition unproved ${id}`);
  check(s.aligned?.checked>=4&&s.aligned.max_px<=4,`header/row aligned proof missing ${id}`);
  check(Array.isArray(s.grid.axis)&&s.grid.axis.length===4&&s.grid.axis.every(x=>x.exists),`four physical-axis nodes absent ${id}`);
  check(s.surface==='admin'||s.grid.gpp_surface===true,`GPP actual frontend surface missing ${id}`);
  if(s.width===390){
    check(s.scroll?.range>20&&Math.abs(s.scroll.moved-s.scroll.start)>8&&Math.abs(s.scroll.restored-s.scroll.start)<=2,`mobile scroll not exercised ${id}`);
    check(s.touch?.status==='PASS',`real mobile touch not exercised ${id}`);
  }
  check(s.key?.start&&s.key?.end&&s.key.start!==s.key.end,`keyboard unproved ${id}`);
  check(typeof s.image==='string'&&/^[a-zA-Z0-9_-]+\.jpg$/.test(s.image),'unsafe screenshot name '+id);
  const image=path.join(d,s.image);
  check(fs.statSync(image).size>1000&&fs.statSync(image).size<2500000,'screenshot missing/bounded '+id);
}
check(Array.isArray(r.controls)&&r.controls.length===13,'missing real Settings API transitions');
check(r.controls.some(x=>x.id==='native-on-with-gpp-absent'&&x.status==='PASS'&&x.mode==='ag-rtl'&&x.gpp_surface===false&&x.restored==='off'),'independent native ON/GPP-absent control unproved');
for(const site of ['fa_IR','en_US'])for(const user of ['fa_IR','en_US'])for(const [i,mode] of ['off','on','off'].entries()){
  const id=`save-${site}-${user}-${i}-${mode}`;
  const c=r.controls.find(x=>x.id===id);
  check(c?.status==='PASS'&&c.saved.after.requested===mode&&c.saved.after.saved===(mode==='on'?1:0),`Settings API persistence missing ${id}`);
}
for(const i of [0,1,2]){
  const mode=['off','on','off'][i],id=`fa_IR-fa_IR-${i}-${mode}-frontend-1280`;
  const a=r.scenarios.find(x=>x.id===id)?.advanced;
  check(a?.sort?.first&&a?.filter?.result?.length===1&&a?.pagination?.changed,'sort/filter/paging interaction unproven '+id);
  check(a?.drag_resize_restore?.after?.length===5,'drag/resize/reload unproven '+id);
  check(a?.transition_state,'cross-mode persisted-state evidence missing '+id);
  if(i<2)check(a?.live_refresh?.after>a.live_refresh.before,'Live Refresh not demonstrated '+id);
}
const names=fs.readdirSync(d);
check(!names.some(x=>/\.zip$|\.php$|\.log$|\.sql$|cookie|token|password|vendor|credential/i.test(x)),'sensitive/unexpected upload entry');
console.log(`G014_GPP_EVIDENCE_GATE PASS subject=${subject} harness=${r.harness} gpp=${gpp} 48 browser cells, 12 real UI saves`);
