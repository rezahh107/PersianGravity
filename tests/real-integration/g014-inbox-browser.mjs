// G-014 — authentic Gravity Flow Inbox post-initialization RTL verification.
// Evidence remains separate from WU008's baseline screenshot publisher.
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';
import { settingsNavigationUrl, classifyInboxReadiness } from './g014-browser-contract.mjs';
import { exerciseNativeInboxInteractions } from './g014-inbox-interactions.mjs';

const [mode, siteLocale, userLocale] = process.argv.slice(2);
if (!['off','on'].includes(mode) || !['fa_IR','en_US'].includes(siteLocale) ||
    !['fa_IR','en_US'].includes(userLocale)) throw Error('Explicit opt-in and both locales required');
for(const k of ['WU008_ADMIN_PASSWORD','WU008_MANIFEST_PATH','WU008_PGR_SHA','G014_EVIDENCE_DIR']) {
  if(!process.env[k]) throw Error('Missing authentic WU008 configuration: '+k);
}
const m=JSON.parse(fs.readFileSync(process.env.WU008_MANIFEST_PATH,'utf8'));
if(m.persiangravity_source_commit!==process.env.WU008_PGR_SHA||
   m.versions?.gravityflow!=='3.1.0')throw Error('Exact source/Gravity Flow package mismatch');
const origin=new URL(m.admin_url).origin;
const pages=[{name:'admin',url:m.gravityflow_inbox_url,effective:userLocale},
  {name:'frontend',url:m.gravityflow_frontend_inbox_url,effective:siteLocale}];
for(const p of pages)if(!p.url||new URL(p.url).origin!==origin)throw Error('Untrusted fixture URL');
const key=[mode,siteLocale,userLocale].join('-');
const dir=process.env.G014_EVIDENCE_DIR;
fs.mkdirSync(dir,{recursive:true});
const browser=await chromium.launch({headless:true});
const report={schema:'g014-inbox-rtl-v1',pgr_head:process.env.WU008_PGR_SHA,
  flow_version:m.versions.gravityflow,mode,siteLocale,userLocale,browser:browser.version(),
  status:'INCOMPLETE',results:[]};
try{
for(const width of [1280,390]){
  const context=await browser.newContext({viewport:{width,height:width===1280?900:844}});
  const page=await context.newPage();
  const errors=[],network=[];
  page.on('pageerror',e=>errors.push(String(e).slice(0,210)));
  page.on('requestfailed',r=>network.push({path:new URL(r.url()).pathname,reason:r.failure()?.errorText}));
  const response=await page.goto(m.login_url,{waitUntil:'domcontentloaded'});
  if(!response?.ok())throw Error('WordPress login unavailable');
  await page.locator('#user_login').fill('runtime_admin');
  await page.locator('#user_pass').fill(process.env.WU008_ADMIN_PASSWORD);
  await Promise.all([page.waitForURL(u=>!u.pathname.includes('wp-login.php')),
    page.locator('#wp-submit').click()]);
  // Verify the actual Settings page reflects persistent state, not only CLI intent.
  const settings=await page.goto(settingsNavigationUrl(m.admin_url),{waitUntil:'domcontentloaded'});
  if(!settings?.ok())throw Error('PersianGravity Settings page failed');
  const toggle=page.locator('input[name="pgr_settings[gravity_flow_inbox_rtl]"]').first();
  if(!(await toggle.count())||(await toggle.isChecked())!==(mode==='on'))
    throw Error('PersianGravity opt-in checkbox does not reflect saved state');

  for(const spec of pages){
    const item={name:spec.name,width,effective_locale:spec.effective,status:'INCOMPLETE'};
    report.results.push(item);
    const e0=errors.length,n0=network.length;
    try{
      const rsp=await page.goto(spec.url,{waitUntil:'domcontentloaded'});
      if(!rsp?.ok())throw Error('Inbox unavailable: '+spec.name+' '+rsp?.status());
      await page.locator('[data-js="gflow-inbox"]').first().waitFor({timeout:18000});
      const measure=()=> {
        const host=document.querySelector('[data-js="gflow-inbox"]');
        const parent=host?.closest('.gflow-inbox');
        const root=host?.querySelector('.ag-root-wrapper');
        const visible=x=>{if(!x)return false;const s=getComputedStyle(x),r=x.getBoundingClientRect();
          return r.width>0&&r.height>0&&s.visibility!=='hidden'&&s.display!=='none'};
        const gridId=host?.dataset.gridId||null;
        const opts=gridId?window.gflow_config?.grids?.[gridId]?.grid_options:null;
        const headers=[...host?.querySelectorAll('.ag-header-cell')||[]].filter(visible);
        const rows=[...host?.querySelectorAll('.ag-center-cols-container .ag-row')||[]].filter(visible);
        const loadingNodes=[...parent?.querySelectorAll(
          '.ag-overlay-loading-center,.ag-overlay-loading-wrapper,.spinner.is-active,[aria-busy="true"],[data-js="gflow-inbox-loader"]'
        )||[]].filter(visible);
        const errorNodes=[...parent?.querySelectorAll('.ag-overlay-error,[role="alert"].ag-error')||[]].filter(visible);
        const emptyNodes=[...host?.querySelectorAll('.ag-overlay-no-rows-center,.ag-overlay-no-rows-wrapper')||[]].filter(visible);
        const bar=host?.querySelector('.ag-body-horizontal-scroll-viewport');
        const r=root?.getBoundingClientRect(),h=headers[0]?.getBoundingClientRect();
        return {
          hostVisible:visible(host),rootVisible:visible(root),
          hasConfig:!!opts,configIdentityMatches:!!gridId&&parent?.dataset.gridId===gridId,
          hasRootMode:!!root&&(root.classList.contains('ag-rtl')!==root.classList.contains('ag-ltr')),
          loadingActive:loadingNodes.length>0,errorOverlay:errorNodes.length>0,
          emptyOverlay:emptyNodes.length>0,
          html_lang:document.documentElement.lang,html_direction:getComputedStyle(document.documentElement).direction,
          inbox_direction:host?getComputedStyle(host).direction:null,
          grid_class:root?.className||null,grid_id:gridId,
          grid_rtl:root?.classList.contains('ag-rtl')===true,
          grid_ltr:root?.classList.contains('ag-ltr')===true,
          has_option:opts?Object.prototype.hasOwnProperty.call(opts,'enableRtl'):false,
          enableRtl:opts?.enableRtl??null,rows:rows.length,headers:headers.length,
          configRows:Array.isArray(opts?.rowData)?opts.rowData.length:-1,
          columnDefs:Array.isArray(opts?.columnDefs)?opts.columnDefs.length:0,
          first_header_left:h?Math.round(h.left-r.left):null,
          first_header_right:h?Math.round(r.right-h.right):null,
          scroll_range:bar?bar.scrollWidth-bar.clientWidth:0,
          scroll_left:bar?.scrollLeft??null,doc_width:document.documentElement.scrollWidth
        };
      };
      // Poll an actual boolean readiness result; never await a truthy snapshot object.
      // Operational empty is valid in general, but this fixture requires real rows.
      let last, readiness;
      for(let attempt=0;attempt<140;attempt++){
        last=await page.evaluate(measure);
        readiness=classifyInboxReadiness(last,{requireRows:true});
        if(readiness.ready)break;
        if(readiness.state==='error')throw Error('Native Grid error: '+JSON.stringify(last));
        await page.waitForTimeout(250);
      }
      item.first=last;item.readiness=readiness;
      if(!readiness?.ready)throw Error('Inbox Grid not ready: '+JSON.stringify({readiness,last}));
      item.final=await page.evaluate(measure);
      const effectiveRtl=spec.effective==='fa_IR';
      const finalState=classifyInboxReadiness(item.final,{requireRows:true});
      if(!finalState.ready)
        throw Error('Inbox Grid lost readiness: '+JSON.stringify({finalState,final:item.final}));
      const final=item.final;
      if(mode==='off'){
        if(final.has_option||final.grid_rtl)throw Error('Disabled mode changed native grid state');
      }else{
        if(!final.has_option||final.enableRtl!==effectiveRtl||
          final.grid_rtl!==effectiveRtl||final.grid_ltr===effectiveRtl)
          throw Error('Wrong AG Grid initial RTL mode for effective request locale');
      }
      item.scroll=await page.evaluate(async rtl=>{
        const bar=document.querySelector('[data-js="gflow-inbox"] .ag-body-horizontal-scroll-viewport');
        if(!bar)return {exists:false};
        const range=bar.scrollWidth-bar.clientWidth,start=bar.scrollLeft;
        bar.scrollLeft=rtl?-Math.max(60,range):Math.max(60,range);
        await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));
        const moved=bar.scrollLeft;
        bar.scrollLeft=start;
        await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));
        return {exists:true,range,start,moved,restored:bar.scrollLeft};
      },mode==='on'&&effectiveRtl);
      if(width===390&&mode==='on'&&
        (!item.scroll.exists||item.scroll.range<=20||
         Math.abs(item.scroll.moved-item.scroll.start)<=10||
         Math.abs(item.scroll.restored-item.scroll.start)>2))
         throw Error('Mobile AG Grid horizontal scrolling failed');
      const header=page.locator('[data-js="gflow-inbox"] .ag-header-cell:visible').first();
      await header.focus();
      const before=await header.getAttribute('col-id');
      await page.keyboard.press(mode==='on'&&effectiveRtl?'ArrowLeft':'ArrowRight');
      const keyboard=await page.evaluate(()=>({inGrid:!!document.activeElement?.closest('[data-js="gflow-inbox"]'),
        column:document.activeElement?.closest('.ag-header-cell')?.getAttribute('col-id')||null}));
      item.keyboard={from:before,to:keyboard.column,inGrid:keyboard.inGrid};
      if(!keyboard.inGrid||!keyboard.column||keyboard.column===before)
        throw Error('Directional header keyboard navigation not demonstrated');
      if(errors.length!==e0||network.length!==n0)
        throw Error('New browser error/network failure in '+spec.name);
      const name=key+'-'+spec.name+'-'+width+'.jpg';
      await page.screenshot({path:path.join(dir,name),type:'jpeg',quality:74,fullPage:true});
      item.image=name;
      // One bounded fully populated native interaction suite per OFF/ON mode.
      // All 32 cases still independently qualify initial direction/readiness.
      if(width===1280&&spec.name==='frontend'&&siteLocale==='fa_IR'&&userLocale==='fa_IR')
        item.interactions=await exerciseNativeInboxInteractions(page, m.g008_flow_entries?.find(x=>x.key==='beta')?.expected_due_timestamp, m.g008_flow_entries?.find(x=>x.key==='beta')?.id);
      item.status='PASS';
    }catch(e){item.status='FAIL';item.reason=String(e);item.errors=errors.slice(e0);item.network=network.slice(n0);throw e}
  }
  await context.close();
}
if(report.results.length!==4||report.results.some(x=>x.status!=='PASS'))throw Error('Incomplete G014 matrix');
report.status='PASS';
console.log('G014_REAL_FLOW_RTL_PASS '+key+' actual cases='+report.results.length);
}catch(e){report.status='FAIL';report.error=String(e);throw e}
finally{fs.writeFileSync(path.join(dir,key+'.json'),JSON.stringify(report,null,2)+'\n');await browser.close()}
