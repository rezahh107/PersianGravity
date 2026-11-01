/**
 * Independent read-only Gravity Forms tabs-lifecycle qualification.
 * Direct click is compared with a genuine Playwright pointer click after
 * native load and bounded initialization wait, in both real GF forms.
 */
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { chromium } from 'playwright';

const root=process.env.WU008_ARTIFACT_DIR, head=process.env.WU008_PGR_SHA;
const password=process.env.WU008_ADMIN_PASSWORD, locale=process.env.SCANNER_UI_LOCALE;
if(!root||!password||!/^[a-f0-9]{40}$/.test(head)||!['fa_IR','en_US'].includes(locale))
  throw Error('Trusted WU008 credentials, SHA and locale required');
const fixture=JSON.parse(fs.readFileSync(path.join(root,'scanner-ui-readonly-evidence/fixture.json'),'utf8'));
const prior=JSON.parse(fs.readFileSync(path.join(root,'scanner-ui-readonly-evidence/geometry-'+locale+'.json'),'utf8'));
if(fixture.expected_source_commit!==head||fixture.installed_release_manifest?.source_commit!==head||
   prior.head!==head||prior.source?.expected_source_commit!==head||prior.locale!==locale||
   prior.native_host_controls?.length!==3)throw Error('Original evidence is not bound to exact Head');
const output=path.join(root,'scanner-tabs-lifecycle-evidence');fs.mkdirSync(output,{recursive:true});
const report={schema:1,evidence_class:'AUTHENTIC_GF_TABS_LIFECYCLE_DIAGNOSTIC',exact_head:head,locale,
  source:{wordpress:fixture.wordpress,gravityforms:fixture.gravityforms,
    preview_css_sha256:fixture.installed_scanner_runtime_css_sha256},
  form_submissions:0,scanner_registry_mutations:0,cases:[],
  limits:['Native-only form still loads PersianGravity','A timed-out readiness wait is not initialization proof']};
const widths=[1280,390,320];
const tabsError=x=>String(x).includes('cannot call methods on tabs prior to initialization');
const state=()=>{
  const j=window.jQuery;
  return {atMs:performance.now(),documentReady:document.readyState,tabsFunction:typeof j?.fn?.tabs==='function',
    nodes:['#field_settings','.sidebar.ui-tabs','#field_settings_container','#settings_tab_item'].map(selector=>{
      const el=document.querySelector(selector);
      return {selector,present:!!el,initialized:!!(j&&el&&j.data(el,'ui-tabs'))};
    }),active:document.querySelector('#settings_tab_item')?.getAttribute('aria-selected')==='true',
    visiblePanelWidth:document.querySelector('#field_settings_container')?.getBoundingClientRect().width??null};
};
const geometry=()=>{
  const box=el=>{if(!el)return null;const r=el.getBoundingClientRect();
    return {x:r.x,y:r.y,left:r.left,right:r.right,top:r.top,bottom:r.bottom,width:r.width,height:r.height};};
  const scanner=box(document.querySelector('.pgr-structured-scanner'));
  const preview=Object.fromEntries(['lead','capture','buttons','status','focus','privacy','header'].map(k=>[
    k,box(document.querySelector('.pgr-structured-scanner__'+(k==='buttons'?'actions':k)))]));
  const ink=Object.fromEntries(['lead','privacy','status'].map(k=>{
    const el=document.querySelector('.pgr-structured-scanner__'+k);
    if(!el)return [k,[]];const r=document.createRange();r.selectNodeContents(el);
    return [k,[...r.getClientRects()].map(x=>box({getBoundingClientRect:()=>x}))];
  }));
  const overlaps=(a,b)=>a&&b&&Math.min(a.right,b.right)-Math.max(a.left,b.left)>1&&
    Math.min(a.bottom,b.bottom)-Math.max(a.top,b.top)>1;
  const collision=(a,b)=>a.some(x=>b.some(y=>overlaps(x,y)));
  const mapping=document.querySelector('.pgr-scanner-editor-mappings');
  return {scanner,preview,inkCollisions:{
    leadPrivacy:collision(ink.lead,ink.privacy),
    privacyCapture:collision(ink.privacy,preview.capture?[preview.capture]:[]),
    focusStatus:collision(ink.status,preview.focus?[preview.focus]:[])},
    mappingCount:document.querySelectorAll('.pgr-scanner-editor-mapping-row').length,
    mappingWidth:mapping?.getBoundingClientRect().width??null,
    tabsSelected:document.querySelector('#settings_tab_item')?.getAttribute('aria-selected')==='true'};
};
const relative=g=>g?.scanner?Object.fromEntries(Object.entries(g.preview).map(([k,v])=>[k,v&&{
  relativeX:+(v.x-g.scanner.x).toFixed(2),relativeY:+(v.y-g.scanner.y).toFixed(2),
  width:+v.width.toFixed(2),height:+v.height.toFixed(2)}])):null;
function differences(a,b){
  const x=relative(a),y=relative(b);if(!x||!y)return {comparable:false};
  const keys=['lead','capture','buttons','status','focus','privacy','header'];
  const changes=Object.fromEntries(keys.map(k=>[k,x[k]&&y[k]?
    Object.fromEntries(Object.keys(x[k]).map(p=>[p,+(y[k][p]-x[k][p]).toFixed(2)])):null]));
  const values=Object.values(changes).flatMap(v=>v?Object.values(v).map(Math.abs):[]);
  return {comparable:true,changes,maxAbsDelta:Math.max(0,...values)};
}
const browser=await chromium.launch({headless:true});
try {
 for(const width of widths){
  const height=width===1280?900:width===390?844:700;
  const context=await browser.newContext({viewport:{width,height},deviceScaleFactor:1,
    locale:locale==='fa_IR'?'fa-IR':'en-US'});
  const login=await context.newPage();
  const loginResponse=await login.goto(fixture.login_url,{waitUntil:'domcontentloaded'});
  if(!loginResponse?.ok())throw Error('Login endpoint unavailable');
  await login.locator('#user_login').fill('runtime_admin');
  await login.locator('#user_pass').fill(password);
  await Promise.all([login.waitForNavigation({waitUntil:'domcontentloaded'}),login.locator('#wp-submit').click()]);
  await login.locator('#wpadminbar').waitFor({state:'attached'});await login.close();
  for(const kind of ['scanner','native']){
   let direct=null;
   for(const mode of ['direct','synchronized']){
    const r={width,locale,kind,mode,status:'NOT_VERIFIED',events:[],errors:[]};
    const p=await context.newPage();let phase='navigation';const t0=Date.now();
    p.on('domcontentloaded',()=>r.events.push({name:'domcontentloaded',elapsedMs:Date.now()-t0}));
    p.on('load',()=>r.events.push({name:'load',elapsedMs:Date.now()-t0}));
    p.on('pageerror',e=>r.errors.push({phase,elapsedMs:Date.now()-t0,
      message:String(e.message).slice(0,300),stack:String(e.stack||e).slice(0,1600)}));
    try{
     const url=kind==='scanner'?fixture.form_builder_url:fixture.native_form_builder_url;
     const nav=await p.goto(url,{waitUntil:'domcontentloaded'});
     if(!nav?.ok()||p.url().includes('wp-login.php'))throw Error('Authentic Form Builder unavailable');
     await p.locator('#wpadminbar').waitFor({state:'attached'});
     await p.locator('#field_3').waitFor({state:'attached',timeout:15000});
     await p.waitForFunction(kind=>window.form?.fields?.some(f=>
       kind==='scanner'?f.type==='pgr_structured_scanner':f.type==='textarea'),kind,{timeout:15000});
     if(kind==='scanner')await p.waitForFunction(()=>typeof window.PGRScannerEditor?.setProfile==='function',null,{timeout:15000});
     r.model=await p.evaluate(()=>({scanner:window.form.fields.some(f=>f.type==='pgr_structured_scanner'),
       nativeTextarea:window.form.fields.some(f=>f.type==='textarea')}));
     if(r.model.scanner!==(kind==='scanner')||!r.model.nativeTextarea)throw Error('Wrong GF model for control');
     if(mode==='synchronized')await p.waitForLoadState('load');
     r.navigation=await p.evaluate(()=>({readyState:document.readyState,
       domContentLoadedEnd:performance.getEntriesByType('navigation')[0]?.domContentLoadedEventEnd??null,
       loadEnd:performance.getEntriesByType('navigation')[0]?.loadEventEnd??null}));
     r.afterNavigation=await p.evaluate(state);
     // Browser-only transparent tab-call recorder: original method executes unchanged.
     await p.evaluate(()=>{
       const j=window.jQuery,original=j?.fn?.tabs;window.__pgrTabsCalls=[];
       if(typeof original!=='function')return;
       j.fn.tabs=function(...args){window.__pgrTabsCalls.push({atMs:performance.now(),
         method:typeof args[0]==='string'?args[0]:'initialize',
         targets:[...this].slice(0,5).map(el=>({id:el.id||null,
           className:String(el.className||'').slice(0,80),
           initialized:!!j.data(el,'ui-tabs')}))});
         return original.apply(this,args);};
       window.__pgrRestoreTabs=()=>{j.fn.tabs=original;};
     });
     if(mode==='synchronized'){
       const targetCall=direct?.tabsCalls?.find(x=>x.method==='option'&&x.targets?.length);
       const actualTarget=targetCall?.targets?.[0];
       // GF 3.1.1.1 calls tabs('option') on its id-less .sidebar.ui-tabs.
       // Do not confuse this with #settings_tab_item (a separate control).
       const selector=actualTarget?.id?'#'+actualTarget.id:
         actualTarget?.className?.split(' ')?.includes('sidebar')&&
         actualTarget?.className?.split(' ')?.includes('ui-tabs')?'.sidebar.ui-tabs':null;
       r.waitedTarget=selector;r.readiness='NOT_ESTABLISHED';
       if(selector)try{
         await p.waitForFunction(sel=>{
           const el=document.querySelector(sel),j=window.jQuery;
           return !!(el&&j&&j.data(el,'ui-tabs'));
         },selector,{timeout:3500});
         r.readiness='INSTANCE_READY';
       }catch{r.readiness='INSTANCE_NOT_READY_WITHIN_BOUND';}
       await p.waitForTimeout(250);
     }
     r.beforeClick=await p.evaluate(state);
     phase='click';r.events.push({name:'before-click',elapsedMs:Date.now()-t0});
     if(mode==='direct'){
       if(!await p.evaluate(()=>{const b=document.querySelector('#gfield_edit_3');if(!b)return false;b.click();return true;}))
         throw Error('EditField button missing');
     }else{
       // A genuine pointer action requires revealing the hover-only GF editor
       // edit button first. Keep a separately recorded native field-card fallback.
       await p.locator('#field_3').hover({timeout:7000});
       try {
         await p.locator('#gfield_edit_3').click({timeout:4000});
         r.userAction='pointer-hover-then-edit-button';
       } catch(e) {
         r.editButtonUnavailable=String(e.message).slice(0,500);
         await p.locator('#field_3').click({timeout:7000});
         r.userAction='pointer-field-card-fallback';
       }
     }
     r.events.push({name:'after-click',elapsedMs:Date.now()-t0});
     r.afterClick=await p.evaluate(state);
     await p.waitForFunction(()=>document.querySelector('#field_3')?.classList.contains('field_selected')===true,
       null,{timeout:10000});
     await p.evaluate(()=>{const t=document.querySelector('#settings_tab_item');
       if(t?.getAttribute('aria-selected')!=='true')t?.querySelector('a')?.click();});
     await p.waitForFunction(()=>{const t=document.querySelector('#settings_tab_item'),
       c=document.querySelector('#field_settings_container');return t?.getAttribute('aria-selected')==='true'&&
       c?.getBoundingClientRect().width>100&&getComputedStyle(c).display!=='none';},null,{timeout:10000});
     if(kind==='scanner'){
       await p.locator('#advanced_tab_toggle').waitFor({state:'visible',timeout:10000});
       await p.evaluate(()=>{const t=document.querySelector('#advanced_tab_toggle');
         if(t?.getAttribute('aria-expanded')!=='true')t?.click();});
       await p.locator('#advanced_tab').waitFor({state:'visible',timeout:10000});
       await p.locator('[data-pgr-scanner-mapping="qr_version"]').waitFor({state:'visible',timeout:10000});
       if(await p.locator('.pgr-scanner-editor-mapping-row').count()!==7)throw Error('Mapping not mounted');
     }
     await p.evaluate(async()=>{await document.fonts.ready;
       await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));});
     r.afterPanel=await p.evaluate(state);
     if(kind==='scanner'){
       r.geometry=await p.evaluate(geometry);
       const base=prior.cases.find(c=>c.state==='form-builder'&&c.width===width);
       if(!base?.baseline)throw Error('No exact-Head geometry baseline');
       r.priorGeometry={oldInk:base.baseline.inkCollisions,
         oldCssInk:base.cssAttribution?.after?.inkCollisions,
         baselineDrift:differences(base.baseline,r.geometry)};
       if(mode==='synchronized'){
         const cssUrl=await p.evaluate(()=>[...document.querySelectorAll('link[href]')]
           .map(l=>l.href).find(url=>url.includes('/assets/css/pgr-scanner-editor.css'))||null);
         if(!cssUrl)throw Error('Installed plugin editor CSS unavailable');
         const previewCss=cssUrl.replace('pgr-scanner-editor.css','pgr-structured-scanner.css');
         const css=await context.request.get(previewCss);
         if(!css.ok()||crypto.createHash('sha256').update(await css.body()).digest('hex')!==
            fixture.installed_scanner_runtime_css_sha256)throw Error('Preview CSS bytes not source-bound');
         const already=await p.evaluate(()=>[...document.querySelectorAll('link[href]')]
           .some(x=>x.href.includes('pgr-structured-scanner.css')));
         if(already)throw Error('Preview stylesheet already present: invalid causal control');
         await p.evaluate(url=>new Promise((resolve,reject)=>{
           const l=document.createElement('link');l.rel='stylesheet';l.href=url;
           l.dataset.pgrTabsDiagnosticCss='1';l.onload=resolve;l.onerror=()=>reject(Error('CSS load failed'));
           document.head.append(l);}),previewCss);
         await p.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
         r.css={after:await p.evaluate(geometry),
           matchingInstalledBytes:true,originallyLoaded:false};
         r.css.delta=differences(r.geometry,r.css.after);
         await p.evaluate(()=>document.querySelector('link[data-pgr-tabs-diagnostic-css="1"]')?.remove());
         r.css.restored=true;
       }
     }
     r.status='OBSERVED';
    }catch(e){r.failure=String(e.stack||e).slice(0,1600);}
    finally{
      r.tabsCalls=await p.evaluate(()=>{window.__pgrRestoreTabs?.();return window.__pgrTabsCalls||[];}).catch(()=>[]);
      r.final=await p.evaluate(state).catch(()=>null);
      r.tabsError=r.errors.some(e=>tabsError(e.message));
      if(mode==='direct')direct=r;
      if(mode==='synchronized')r.comparison={directTabsError:direct?.tabsError??null,
        synchronizedTabsError:r.tabsError,
        directTabTargets:direct?.tabsCalls?.filter(x=>x.method==='option')??[],
        widgetReadyBeforeClick:r.readiness==='INSTANCE_READY'};
      report.cases.push(r);await p.close();
    }
   }
  }
  await context.close();
 }
}finally{await browser.close();}
fs.writeFileSync(path.join(output,'tabs-'+locale+'.json'),JSON.stringify(report,null,2)+'\n');
const failed=report.cases.filter(x=>x.status!=='OBSERVED');
console.log('GF_TABS_LIFECYCLE '+locale+' cases='+report.cases.length+' failed='+failed.length+
  ' directErrors='+report.cases.filter(x=>x.mode==='direct'&&x.tabsError).length+
  ' synchronizedErrors='+report.cases.filter(x=>x.mode==='synchronized'&&x.tabsError).length+
  ' ready='+report.cases.filter(x=>x.readiness==='INSTANCE_READY').length);
if(report.cases.length!==12||failed.length)process.exitCode=1;
