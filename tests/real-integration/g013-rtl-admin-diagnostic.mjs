// G013 PRI-FND-005: verification-only, authentic WU008 browser evidence.
// Does not modify the product, licensed vendors or G013 original RTL assertion.
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const locale=process.argv[2];
if(!['fa_IR','en_US'].includes(locale))throw Error('Locale required');
for(const key of ['WU008_ARTIFACT_DIR','WU008_MANIFEST_PATH','WU008_ADMIN_PASSWORD','WU008_PGR_SHA'])
  if(!process.env[key])throw Error('Missing WU008 '+key);
const manifest=JSON.parse(fs.readFileSync(process.env.WU008_MANIFEST_PATH,'utf8'));
if(manifest.persiangravity_source_commit!==process.env.WU008_PGR_SHA)
  throw Error('Current installed Head does not match PR Head');
const target=manifest.gravityflow_inbox_url;
if(!target||new URL(target).origin!==new URL(manifest.admin_url).origin)
  throw Error('Missing authentic same-origin Admin Inbox');
const dest=path.join(process.env.WU008_ARTIFACT_DIR,'g013-rtl-verification');
fs.mkdirSync(dest,{recursive:true});
const browser=await chromium.launch({headless:true});
const report={schema:'g013-admin-rtl-diagnostic-v1',exact_head:process.env.WU008_PGR_SHA,
  profile:'BASELINE',locale,chromium:browser.version(),cases:[]};

const diagnostic = () => {
  const el=document.documentElement,body=document.body;
  const grid=document.querySelector('[data-js="gflow-inbox"]');
  const root=grid?.querySelector('.ag-root-wrapper,.ag-root');
  const styleOf=node=>node?getComputedStyle(node):null;
  const rectOf=node=>{if(!node)return null;const r=node.getBoundingClientRect();
    return {left:Math.round(r.left),right:Math.round(r.right),top:Math.round(r.top),
      width:Math.round(r.width),height:Math.round(r.height)};};
  const geom=(selector,limit=8)=>[...document.querySelectorAll(selector)].slice(0,limit).map(node=>({
    className:typeof node.className==='string'?node.className.slice(0,160):null,
    direction:styleOf(node).direction,rect:rectOf(node),scrollWidth:node.scrollWidth,
    clientWidth:node.clientWidth,tabIndex:node.tabIndex,
  }));
  const visible=node=>{if(!node)return false;const s=getComputedStyle(node),r=node.getBoundingClientRect();
    return s.display!=='none'&&s.visibility!=='hidden'&&Number(s.opacity)!==0&&r.width>0&&r.height>0;};
  const spinner=[...document.querySelectorAll('.ag-overlay-loading-wrapper,.ag-overlay-loading-center,.spinner.is-active,[aria-busy="true"],.ag-loading,.ag-overlay-loading-center')].filter(visible);
  const headers=[...document.querySelectorAll('.ag-header-cell')].filter(visible);
  const rows=[...document.querySelectorAll('.ag-center-cols-container .ag-row,.ag-center-cols-viewport .ag-row')].filter(visible);
  const empty=[...document.querySelectorAll('.ag-overlay-no-rows-center,.ag-overlay-no-rows-wrapper')].filter(visible);
  const gridId=grid?.dataset?.gridId||'inbox_default';
  const cfg=window.gflow_config?.grids?.[gridId]?.grid_options;
  const configRows=Array.isArray(cfg?.rowData)?cfg.rowData.length:null;
  const noError=!document.querySelector('.ag-overlay-error,.ag-error');
  const initialized=Boolean(root&&rectOf(root)?.width>0&&headers.length);
  const hasContent=rows.length>0||(empty.length>0&&configRows===0);
  const ready=initialized&&hasContent&&spinner.length===0&&noError;
  const summary={html:{lang:el.lang,dirAttribute:el.getAttribute('dir'),computed:styleOf(el).direction},
    body:{dirAttribute:body?.getAttribute('dir'),computed:styleOf(body)?.direction},
    inbox:{computed:styleOf(grid)?.direction,rect:rectOf(grid),className:grid?.className||null},
    agGrid:{computed:styleOf(root)?.direction,className:root?.className||null,
      agRtl:root?.classList.contains('ag-rtl')||false,
      agLtr:root?.classList.contains('ag-ltr')||false,
      gridId,config:{enableRtl:cfg?.enableRtl??null,domLayout:cfg?.domLayout??null,
        rowCount:configRows,columnCount:Array.isArray(cfg?.columnDefs)?cfg.columnDefs.length:null},
      headerCount:headers.length,rowCount:rows.length,emptyCount:empty.length,
      loadingCount:spinner.length,initialized,ready,
      headers:geom('.ag-header-cell'),cells:geom('.ag-center-cols-container .ag-cell'),
      rowsGeometry:geom('.ag-center-cols-container .ag-row',3),
      horizontal:{viewport:geom('.ag-center-cols-viewport',1),
        container:geom('.ag-center-cols-container',1),
        scrollbar:geom('.ag-body-horizontal-scroll',1)},
    },
    document:{scrollWidth:el.scrollWidth,clientWidth:el.clientWidth,
      viewportWidth:innerWidth,bodyScrollWidth:body?.scrollWidth},
    focus:{activeTag:document.activeElement?.tagName,activeClass:document.activeElement?.className||null}};
  return summary;
};
const cssProbe=async()=> {
  return await page.evaluate(async()=>{
    const root=document.documentElement, before=getComputedStyle(root).direction;
    const owner=s=>s.ownerNode;
    const pathName=u=>{if(!u)return null;try{return new URL(u,location.href).pathname;}catch{return null;}};
    const sheets=[...document.styleSheets];
    const candidates=[];
    const cssRules=[];
    function scan(list,href,depth=0) {
      if(depth>4||!list)return;
      for(const rule of [...list]){
        if(rule.style&&rule.style.getPropertyValue('direction')){
          let applies=false;
          try {applies=Boolean(rule.selectorText)&&root.matches(rule.selectorText);}catch{}
          if(applies||/\bhtml\b|:root/.test(rule.selectorText||''))
            cssRules.push({source:href,selector:(rule.selectorText||'').slice(0,240),
              value:rule.style.getPropertyValue('direction'),
              important:rule.style.getPropertyPriority('direction'),applies});
        }
        try{if(rule.cssRules)scan(rule.cssRules,href,depth+1);}catch{}
      }
    }
    for(const [index,sheet] of sheets.entries()){
      const o=owner(sheet),href=pathName(sheet.href),id=o?.id||null;
      try{scan(sheet.cssRules,href||'inline:'+index);}catch(err){cssRules.push({source:href,error:String(err).slice(0,130)});}
      candidates.push({index,href,id,tag:o?.tagName||null,disabled:sheet.disabled});
    }
    const changes=[];
    for(const s of candidates){
      const sheet=sheets[s.index];
      if(!sheet||sheet.disabled)continue;
      const old=sheet.disabled;
      try {
        sheet.disabled=true;
        await new Promise(resolve=>requestAnimationFrame(resolve));
        const disabled=getComputedStyle(root).direction;
        if(disabled!==before)changes.push({index:s.index,id:s.id,href:s.href,from:before,to:disabled});
      } finally {
        sheet.disabled=old;
        await new Promise(resolve=>requestAnimationFrame(resolve));
      }
    }
    const restored=getComputedStyle(root).direction;
    return {before,restored,restorationProven:restored===before,
      sheets:candidates,matchingRules:cssRules.slice(0,90),individuallyCausalSheets:changes};
  });
};

let page;
try{
  for(const width of [1280,390]){
    const ctx=await browser.newContext({viewport:{width,height:width===1280?900:844},deviceScaleFactor:1});
    page=await ctx.newPage();
    const errors=[],failed=[],responses=[];
    page.on('pageerror',e=>errors.push(String(e).slice(0,240)));
    page.on('requestfailed',r=>failed.push({path:new URL(r.url()).pathname,error:r.failure()?.errorText||null}));
    page.on('response',r=>{if(r.status()>=400)responses.push({path:new URL(r.url()).pathname,status:r.status()});});
    const record={width,locale,status:'INCOMPLETE'};
    report.cases.push(record);
    try {
      const login=await page.goto(manifest.login_url,{waitUntil:'domcontentloaded'});
      if(!login?.ok())throw Error('WP login inaccessible');
      await page.locator('#user_login').fill('runtime_admin');
      await page.locator('#user_pass').fill(process.env.WU008_ADMIN_PASSWORD);
      await Promise.all([page.waitForURL(url=>!url.pathname.includes('wp-login.php')),
        page.locator('#wp-submit').click()]);
      const resp=await page.goto(target,{waitUntil:'domcontentloaded'});
      if(!resp?.ok())throw Error('Admin Inbox HTTP '+resp?.status());
      await page.locator('[data-js="gflow-inbox"]').waitFor({state:'attached',timeout:15000});
      record.initial=await page.evaluate(diagnostic);
      let lastSignature='',stable=0;
      // Require two repeated stable ready snapshots, not just a mounted placeholder.
      try {
        await page.waitForFunction(()=>{
          const root=document.querySelector('[data-js="gflow-inbox"] .ag-root-wrapper,[data-js="gflow-inbox"] .ag-root');
          if(!root||root.getBoundingClientRect().width===0)return false;
          const shown=e=>{if(!e)return false;const s=getComputedStyle(e),r=e.getBoundingClientRect();
            return s.display!=='none'&&s.visibility!=='hidden'&&r.width>0&&r.height>0;};
          if([...document.querySelectorAll('.ag-overlay-loading-wrapper,.ag-overlay-loading-center,.spinner.is-active,[aria-busy="true"]')].some(shown))return false;
          if(![...document.querySelectorAll('.ag-header-cell')].some(shown))return false;
          const row=[...document.querySelectorAll('.ag-center-cols-container .ag-row')].some(shown);
          const empty=[...document.querySelectorAll('.ag-overlay-no-rows-center,.ag-overlay-no-rows-wrapper')].some(shown);
          const wrapper=document.querySelector('[data-js="gflow-inbox"]');
          const id=wrapper?.dataset?.gridId||'inbox_default';
          const data=window.gflow_config?.grids?.[id]?.grid_options?.rowData;
          return row||(empty&&Array.isArray(data)&&data.length===0);
        },null,{timeout:30000,polling:250});
        const a=await page.evaluate(diagnostic);
        await page.waitForTimeout(300);
        const b=await page.evaluate(diagnostic);
        const signature=o=>JSON.stringify({ready:o.agGrid.ready,headers:o.agGrid.headerCount,
          rows:o.agGrid.rowCount,empty:o.agGrid.emptyCount,scroll:o.document.scrollWidth});
        stable=signature(a)===signature(b)?2:0;
        record.readySnapshots=[a,b];
        record.stable=stable===2&&b.agGrid.ready;
      }catch(error){
        record.readyTimeout=String(error).slice(0,250);
        record.stable=false;
      }
      record.final=await page.evaluate(diagnostic);
      record.css=await cssProbe();
      record.afterCssRestore=await page.evaluate(diagnostic);
      record.focus=await page.evaluate(()=>{
        const header=[...document.querySelectorAll('.ag-header-cell')].find(e=>e.getBoundingClientRect().width>0);
        header?.focus();
        return {focused:document.activeElement===header,tabindex:header?.tabIndex??null,
          direction:header?getComputedStyle(header).direction:null};
      });
      if(record.focus.focused) {
        await page.keyboard.press('ArrowRight');
        record.focus.afterArrowRight=await page.evaluate(()=>({
          tag:document.activeElement?.tagName,
          className:String(document.activeElement?.className||'').slice(0,100),
          insideGrid:Boolean(document.activeElement?.closest('[data-js="gflow-inbox"]'))}));
      }
      record.screenshot='admin-inbox-'+locale+'-'+width+'.jpg';
      await page.screenshot({path:path.join(dest,record.screenshot),type:'jpeg',quality:78,fullPage:true});
      record.status=record.stable?'READY_CAPTURED':'NOT_READY';
    }catch(error){record.status='FAILED_DIAGNOSTIC';record.error=String(error).slice(0,300);}
    finally{
      record.pageErrors=errors;record.failedRequests=failed;record.errorResponses=responses;
      fs.writeFileSync(path.join(dest,'admin-inbox-'+locale+'-'+width+'.json'),JSON.stringify(record,null,2)+'\n');
      await ctx.close();
    }
    console.log('G013_ADMIN_RTL_DIAGNOSTIC '+JSON.stringify({locale,width,status:record.status,
      initial:record.initial?.html,final:record.final?.html,body:record.final?.body,
      grid:record.final?.agGrid&&{direction:record.final.agGrid.computed,
        className:record.final.agGrid.className,
        headers:record.final.agGrid.headerCount,rows:record.final.agGrid.rowCount,
        empty:record.final.agGrid.emptyCount,loading:record.final.agGrid.loadingCount},
      css:record.css?.individuallyCausalSheets,restored:record.css?.restorationProven,
      focused:record.focus?.focused,errors:record.pageErrors.length}));
  }
}finally{
  fs.writeFileSync(path.join(dest,'index-'+locale+'.json'),JSON.stringify(report,null,2)+'\n');
  await browser.close();
}
