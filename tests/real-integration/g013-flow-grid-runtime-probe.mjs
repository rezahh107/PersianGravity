// PRI-FND-005 disposable pre-initialization gravityflow_js_config_shared filter runtime proof.
// Exercises actual licensed WU008 Admin and Frontend Inbox, no vendor or product modification.
import fs from 'node:fs';
import path from 'node:path';
import {chromium} from 'playwright';

const locale=process.argv[2];
if(!['fa_IR','en_US'].includes(locale))throw Error('Locale');
for(const k of ['WU008_ARTIFACT_DIR','WU008_MANIFEST_PATH','WU008_ADMIN_PASSWORD','WU008_PGR_SHA'])
  if(!process.env[k])throw Error('Missing '+k);
const manifest=JSON.parse(fs.readFileSync(process.env.WU008_MANIFEST_PATH,'utf8'));
if(manifest.persiangravity_source_commit!==process.env.WU008_PGR_SHA)
  throw Error('PGR exact Head mismatch');
const base=new URL(manifest.admin_url).origin;
const surfaces=[
  {name:'admin',url:manifest.gravityflow_inbox_url},
  {name:'frontend-g008-rows',url:manifest.g008_flow_inbox_url},
  {name:'frontend-public',url:manifest.gravityflow_frontend_inbox_url},
];
for(const s of surfaces)if(!s.url||new URL(s.url).origin!==base)throw Error('Invalid fixture '+s.name);
const dir=path.join(process.env.WU008_ARTIFACT_DIR,'g013-flow-filter-runtime');
fs.mkdirSync(dir,{recursive:true});
const expected=locale==='fa_IR',browser=await chromium.launch({headless:true});
const report={schema:'g013-shared-config-filter-v1',head:process.env.WU008_PGR_SHA,
  gravityflowVersion:manifest.versions.gravityflow,locale,expectedRtl:expected,results:[]};
try{
  for(const width of [1280,390]){
    const ctx=await browser.newContext({viewport:{width,height:width===1280?900:844},deviceScaleFactor:1});
    const page=await ctx.newPage(),jsErrors=[],networkFailures=[],httpErrors=[];
    page.on('pageerror',e=>jsErrors.push(String(e).slice(0,160)));
    page.on('requestfailed',r=>networkFailures.push({path:new URL(r.url()).pathname,failure:r.failure()?.errorText||null}));
    page.on('response',r=>{if(r.status()>=400)httpErrors.push({path:new URL(r.url()).pathname,status:r.status()});});
    try{
      const login=await page.goto(manifest.login_url,{waitUntil:'domcontentloaded'});
      if(!login?.ok())throw Error('Login HTTP');
      await page.locator('#user_login').fill('runtime_admin');
      await page.locator('#user_pass').fill(process.env.WU008_ADMIN_PASSWORD);
      await Promise.all([page.waitForURL(url=>!url.pathname.includes('wp-login.php')),
        page.locator('#wp-submit').click()]);
      for(const surface of surfaces){
        const record={locale,width,surface:surface.name,status:'INCOMPLETE'};
        report.results.push(record);
        const errorBefore=jsErrors.length,failedBefore=networkFailures.length,httpBefore=httpErrors.length;
        try {
          const resp=await page.goto(surface.url,{waitUntil:'domcontentloaded'});
          if(!resp?.ok())throw Error('HTTP '+resp?.status());
          const grid=page.locator('[data-js="gflow-inbox"]').first();
          await grid.waitFor({state:'attached',timeout:16000});
          const watch=()=> {
            const root=document.querySelector('[data-js="gflow-inbox"] .ag-root-wrapper,[data-js="gflow-inbox"] .ag-root');
            const visible=e=>{if(!e)return false;const s=getComputedStyle(e),r=e.getBoundingClientRect();
              return r.width>0&&r.height>0&&s.display!=='none'&&s.visibility!=='hidden';};
            const rows=[...document.querySelectorAll('.ag-center-cols-container .ag-row')].filter(visible);
            const headers=[...document.querySelectorAll('.ag-header-cell')].filter(visible);
            const empties=[...document.querySelectorAll('.ag-overlay-no-rows-center,.ag-overlay-no-rows-wrapper')].filter(visible);
            const loading=[...document.querySelectorAll('.ag-overlay-loading-center,.ag-overlay-loading-wrapper,[aria-busy="true"],.spinner.is-active')].filter(visible);
            const wrap=document.querySelector('[data-js="gflow-inbox"]');
            const id=wrap?.dataset?.gridId||'inbox_default';
            const cfg=window.gflow_config?.grids?.[id]?.grid_options;
            const data=Array.isArray(cfg?.rowData)?cfg.rowData:null;
            return {ready:Boolean(root&&visible(root)&&headers.length&&
              (rows.length||(empties.length&&data?.length===0))&&!loading.length),
              locale:document.documentElement.lang,
              rootDir:getComputedStyle(document.documentElement).direction,
              wrapperDir:root?getComputedStyle(root).direction:null,
              className:root?.className||null,
              agRtl:root?.classList.contains('ag-rtl')===true,
              agLtr:root?.classList.contains('ag-ltr')===true,
              enableRtl:cfg?.enableRtl??null,gridId:id,
              headerCount:headers.length,rowCount:rows.length,emptyCount:empties.length,
              loadingCount:loading.length,
              firstHeader:headers[0]?(()=>{const a=headers[0].getBoundingClientRect(),b=root.getBoundingClientRect();
                return {left:Math.round(a.left),right:Math.round(a.right),
                  offsetLeft:Math.round(a.left-b.left),offsetRight:Math.round(b.right-a.right),
                  width:Math.round(a.width)}})():null,
              gridBounds:root?(()=>{const r=root.getBoundingClientRect();return {left:Math.round(r.left),right:Math.round(r.right),width:Math.round(r.width)}})():null,
              horizontal:[...document.querySelectorAll('.ag-center-cols-viewport,.ag-body-horizontal-scroll-viewport')].slice(0,2).map(e=>({
                className:typeof e.className==='string'?e.className:null,
                clientWidth:e.clientWidth,scrollWidth:e.scrollWidth,scrollLeft:e.scrollLeft,
                scrollRange:e.scrollWidth-e.clientWidth})),
              configKeys:cfg?Object.keys(cfg).filter(k=>/rtl|direction/i.test(k)):[],
            };
          };
          await page.waitForFunction(watch,null,{timeout:35000,polling:250});
          const first=await page.evaluate(watch);
          await page.waitForTimeout(350);
          const second=await page.evaluate(watch);
          const stable=first.ready&&second.ready&&
            ['rowCount','headerCount','emptyCount','loadingCount','enableRtl','className'].every(k=>first[k]===second[k]);
          record.first=first;record.stable=stable;record.final=second;

          // Reversible physical horizontal scrollbar probe; restore exact original position.
          record.scrollInteraction=await page.evaluate(async()=>{
            const bar=document.querySelector('[data-js="gflow-inbox"] .ag-body-horizontal-scroll-viewport');
            if(!bar)return {present:false};
            const range=bar.scrollWidth-bar.clientWidth,start=bar.scrollLeft;
            const frame=()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));
            const test=async target=>{bar.scrollLeft=target;await frame();return bar.scrollLeft;};
            const positive=await test(Math.max(60,range)),negative=await test(-Math.max(60,range));
            const restored=await test(start);
            return {present:true,range,start,positive,negative,restored,
              moved:range>20&&(Math.abs(positive-start)>10||Math.abs(negative-start)>10),
              restoredExactly:Math.abs(restored-start)<2,
              direction:getComputedStyle(bar).direction};
          });
          // Explicitly check animated/spinning/loading overlays; headers+rows alone are insufficient.
          const overlaySnapshot=()=> {
            const host=document.querySelector('[data-js="gflow-inbox"]');
            if(!host)return {indicators:[],animations:[]};
            const visible=e=>{const s=getComputedStyle(e),r=e.getBoundingClientRect();
              return r.width>4&&r.height>4&&s.display!=='none'&&s.visibility!=='hidden'&&
                Number(s.opacity)>0.05;};
            const nodes=[...host.querySelectorAll('*')];
            const described=e=>{const s=getComputedStyle(e),r=e.getBoundingClientRect();
              return {tag:e.tagName.toLowerCase(),className:String(e.className||'').slice(0,160),
                width:Math.round(r.width),height:Math.round(r.height),
                animation:s.animationName,role:e.getAttribute('role'),
                busy:e.getAttribute('aria-busy')};};
            const indicators=nodes.filter(e=>visible(e)&&(
              /loading|spinner|progress|busy|overlay-loading|spinner-/i.test(String(e.className||''))||
              e.getAttribute('aria-busy')==='true'||
              e.getAttribute('role')==='progressbar')).slice(0,35).map(described);
            const animations=nodes.filter(e=>visible(e)&&
              getComputedStyle(e).animationName!=='none').slice(0,25).map(described);
            return {indicators,animations};
          };
          let clearSamples=0;
          record.overlayChecks=[];
          for(let attempt=0;attempt<36;attempt++){
            const overlay=await page.evaluate(overlaySnapshot);
            if(attempt===0||attempt%6===0||overlay.indicators.length)record.overlayChecks.push({
              attempt,indicators:overlay.indicators,animations:overlay.animations});
            clearSamples=overlay.indicators.length===0?clearSamples+1:0;
            if(clearSamples>=4)break;
            await page.waitForTimeout(200);
          }
          record.spinnerClear=clearSamples>=4;
          record.overlayBeforeScreenshot=await page.evaluate(overlaySnapshot);

          const headers=page.locator('.ag-header-cell:visible');
          if(await headers.count()){
            await headers.first().focus();
            const startCol=await headers.first().getAttribute('col-id');
            record.keyboard={firstColumnId:startCol,focusFirst:await headers.first().evaluate(e=>e===document.activeElement)};
            await page.keyboard.press('ArrowRight');
            record.keyboard.arrowRight=await page.evaluate(()=>({
              activeInGrid:!!document.activeElement?.closest('[data-js="gflow-inbox"]'),
              className:String(document.activeElement?.className||'').slice(0,100)}));
            await page.keyboard.press('ArrowLeft');
            record.keyboard.arrowLeft=await page.evaluate(()=>({
              activeInGrid:!!document.activeElement?.closest('[data-js="gflow-inbox"]'),
              className:String(document.activeElement?.className||'').slice(0,100)}));
            await headers.first().focus();
            const forwardKey=expected?'ArrowLeft':'ArrowRight';
            await page.keyboard.press(forwardKey);
            record.keyboard.directional=await page.evaluate(({forwardKey,startCol})=>({
              key:forwardKey,startCol,
              endCol:document.activeElement?.closest('.ag-header-cell')?.getAttribute('col-id')||null,
              insideGrid:!!document.activeElement?.closest('[data-js="gflow-inbox"]')
            }),{forwardKey,startCol});
          }
          record.screenshot='g013-filter-'+surface.name+'-'+locale+'-'+width+'.jpg';
          await page.screenshot({path:path.join(dir,record.screenshot),type:'jpeg',quality:75,fullPage:true});
          const configCorrect=stable&&second.enableRtl===expected&&
            (expected?second.agRtl&&!second.agLtr:second.agLtr&&!second.agRtl);
          const physicalScroll=width!==390||record.scrollInteraction?.moved&&record.scrollInteraction?.restoredExactly;
          record.status=!configCorrect?'CONFIG_NOT_PROVEN':
            !record.spinnerClear||record.overlayBeforeScreenshot.indicators.length?'LOADING_NOT_RESOLVED':
            !physicalScroll?'HORIZONTAL_SCROLL_NOT_PROVEN':'GRID_AND_SCROLL_READY';
        } catch(e) {record.status='PROBE_FAILURE';record.error=String(e).slice(0,260);}
        finally{
          record.jsErrors=jsErrors.slice(errorBefore);
          record.networkFailures=networkFailures.slice(failedBefore);
          record.httpErrors=httpErrors.slice(httpBefore);
          console.log('G013_FILTER_RUNTIME '+JSON.stringify({locale,width,surface:surface.name,status:record.status,
            rtl:record.final?.enableRtl,agRtl:record.final?.agRtl,agLtr:record.final?.agLtr,
            ready:record.stable,rows:record.final?.rowCount,headers:record.final?.headerCount,
            firstHeader:record.final?.firstHeader,error:record.error||null,
            js:record.jsErrors.length,network:record.networkFailures.length,http:record.httpErrors.length}));
        }
      }
    }finally{await ctx.close();}
  }
}finally{
  fs.writeFileSync(path.join(dir,'results-'+locale+'.json'),JSON.stringify(report,null,2)+'\n');
  await browser.close();
}
