// Observation-only native AG Grid resize qualification. No product/vendor edits.
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';
const mode=process.argv[2];
if(!['off','on'].includes(mode))throw Error('Explicit OFF/ON required');
const m=JSON.parse(fs.readFileSync(process.env.WU008_MANIFEST_PATH,'utf8'));
if(m.persiangravity_source_commit!==process.env.WU008_PGR_SHA||
  m.versions?.gravityflow!=='3.1.0')throw Error('Exact installed WU008 host/Head mismatch');
const dir=process.env.G014_RESIZE_PROBE_DIR;
if(!dir)throw Error('Missing resize evidence directory');
fs.mkdirSync(dir,{recursive:true});
const browser=await chromium.launch({headless:true});
const report={schema:'g014-native-resize-investigation-v1',
  head:process.env.WU008_PGR_SHA,flowVersion:m.versions.gravityflow,
  mode,viewport:{width:1280,height:900},cases:[]};
const scope='[data-js="gflow-inbox"]';
const observedRowIds=page=>page.locator(scope+' .ag-center-cols-container .ag-row').evaluateAll(ns=>
  ns.map(n=>({id:Number(n.getAttribute('row-id')),top:n.getBoundingClientRect().top,
    visible:n.getBoundingClientRect().height>0}))
    .filter(n=>n.visible&&Number.isSafeInteger(n.id)).sort((a,b)=>a.top-b.top).map(n=>n.id));
const inspect=async(page,col)=>page.evaluate(col=>{
  const host=document.querySelector('[data-js="gflow-inbox"]');
  const h=host?.querySelector('.ag-header-cell[col-id="'+col+'"]');
  const handle=h?.querySelector('.ag-header-cell-resize');
  const scroll=host?.querySelector('.ag-body-horizontal-scroll-viewport');
  const config=window.gflow_config?.grids?.[host?.dataset.gridId]?.grid_options;
  const definition=config?.columnDefs?.find(c=>c.field===col);
  const rect=n=>{if(!n)return null;const r=n.getBoundingClientRect();return {
    x:r.x,y:r.y,left:r.left,right:r.right,top:r.top,bottom:r.bottom,
    width:r.width,height:r.height};};
  const style=n=>{if(!n)return null;const s=getComputedStyle(n);return{
    direction:s.direction,display:s.display,visibility:s.visibility,
    pointerEvents:s.pointerEvents,cursor:s.cursor,position:s.position,
    opacity:s.opacity,zIndex:s.zIndex,minWidth:s.minWidth,maxWidth:s.maxWidth,
    boxSizing:s.boxSizing,transform:s.transform,overflow:s.overflow};};
  const at=(x,y)=>{const n=document.elementFromPoint(x,y);return n?{
    tag:n.tagName,cls:typeof n.className==='string'?n.className:'',
    col:n.closest('.ag-header-cell')?.getAttribute('col-id')||null,
    isHandle:!!n.closest('.ag-header-cell-resize'),
    path:[n,n.parentElement,n.parentElement?.parentElement].filter(Boolean)
      .map(e=>e.tagName+'.'+(typeof e.className==='string'?e.className:''))}:null;};
  const hr=rect(handle);
  const center=hr&&at(hr.left+hr.width/2,hr.top+hr.height/2);
  const left=hr&&at(hr.left+Math.max(1,hr.width*0.1),hr.top+hr.height/2);
  const right=hr&&at(hr.right-Math.max(1,hr.width*0.1),hr.top+hr.height/2);
  return {
    gridId:host?.dataset.gridId,rtl:!!host?.querySelector('.ag-root-wrapper.ag-rtl'),
    configuredRows:config?.rowData?.length,definition:definition?{
      field:definition.field,resizable:definition.resizable,
      minWidth:definition.minWidth,maxWidth:definition.maxWidth,
      width:definition.width,suppressMovable:definition.suppressMovable}:null,
    header:rect(h),handle:hr,headerStyle:style(h),handleStyle:style(handle),
    headerClasses:h?.className,headerInlineStyle:h?.getAttribute('style'),
    handleClasses:handle?.className,handleInlineStyle:handle?.getAttribute('style'),
    centerHit:center,leftHit:left,rightHit:right,
    scrollLeft:scroll?.scrollLeft,scrollRange:scroll?scroll.scrollWidth-scroll.clientWidth:null,
    activeElement:document.activeElement?.className||null,
    events:window.__g014ResizeEvents?.slice(-30)||[],
    mutations:window.__g014ResizeMutations?.slice(-30)||[]
  };
},col);
async function arm(page){
  await page.evaluate(()=>{
    window.__g014ResizeEvents=[];
    window.__g014ResizeMutations=[];
    const host=document.querySelector('[data-js="gflow-inbox"]');
    const describe=e=>({type:e.type,
      x:Math.round(e.clientX??0),y:Math.round(e.clientY??0),
      button:e.button,buttons:e.buttons,pointerType:e.pointerType||null,
      target:{tag:e.target?.tagName,cls:typeof e.target?.className==='string'?e.target.className:'',
        col:e.target?.closest?.('.ag-header-cell')?.getAttribute('col-id')||null,
        handle:!!e.target?.closest?.('.ag-header-cell-resize')},
      hit:(()=>{const t=document.elementFromPoint(e.clientX,e.clientY);return{
        cls:typeof t?.className==='string'?t.className:'',
        col:t?.closest?.('.ag-header-cell')?.getAttribute('col-id')||null,
        handle:!!t?.closest?.('.ag-header-cell-resize')}})()});
    for(const name of ['pointerdown','pointermove','pointerup','mousedown','mousemove','mouseup']){
      document.addEventListener(name,e=>{
        if(window.__g014ResizeEvents.length<120)
          window.__g014ResizeEvents.push(describe(e));
      },{capture:true});
    }
    const observer=new MutationObserver(list=>{
      for(const m of list)if(window.__g014ResizeMutations.length<120){
        if(m.target.classList?.contains('ag-header-cell')||
           m.target.classList?.contains('ag-header-cell-resize'))
          window.__g014ResizeMutations.push({
            attribute:m.attributeName,col:m.target.closest('.ag-header-cell')?.getAttribute('col-id'),
            value:m.target.getAttribute(m.attributeName)});
      }
    });
    observer.observe(host,{subtree:true,attributes:true,attributeFilter:['style','class']});
  });
}
async function prepare(page,surface){
  const url=surface==='frontend'?m.gravityflow_frontend_inbox_url:m.gravityflow_inbox_url;
  const response=await page.goto(url,{waitUntil:'domcontentloaded'});
  if(!response?.ok())throw Error('Inbox URL not operational: '+response?.status());
  await page.waitForFunction(()=>{
    const host=document.querySelector('[data-js="gflow-inbox"]');
    const opts=window.gflow_config?.grids?.[host?.dataset.gridId]?.grid_options;
    return opts?.rowData?.length===36&&
      host?.querySelectorAll('.ag-header-cell').length>=5&&
      host?.querySelectorAll('.ag-center-cols-container .ag-row').length===20;
  },null,{timeout:20000});
  if(surface==='frontend'){
    // Reproduce the exact pre-resize production sequence: two sorts, raw filter,
    // keyboard-correct beta isolation, clear, next page and previous page.
    const header=page.locator(scope+' .ag-header-cell[col-id="id"]').first();
    await header.click();await header.click();
    const input=page.locator('[data-js="gflow-inbox-search"]').first();
    await input.click();await input.pressSequentially('1900275300');
    await page.waitForFunction(()=>document.querySelectorAll(
      '[data-js="gflow-inbox"] .ag-center-cols-container .ag-row').length===1&&
      document.querySelector('[data-js="gflow-inbox"] .ag-row')?.getAttribute('row-id')==='27',
      null,{timeout:12000});
    await input.fill('');
    await page.waitForFunction(()=>document.querySelectorAll(
      '[data-js="gflow-inbox"] .ag-center-cols-container .ag-row').length===20,
      null,{timeout:12000});
    const before=await observedRowIds(page);
    const next=page.locator(scope+' .ag-paging-button[ref="btNext"]').first();
    await next.click();
    await page.waitForFunction(previous=>{
      const r=document.querySelector('[data-js="gflow-inbox"] .ag-row');
      return r&&!previous.includes(Number(r.getAttribute('row-id')));
    },before,{timeout:12000});
    await page.locator(scope+' .ag-paging-button[ref="btPrevious"]').first().click();
    await page.waitForFunction(id=>document.querySelector(
      '[data-js="gflow-inbox"] .ag-row')?.getAttribute('row-id')===String(id),
      before[0],{timeout:12000});
  }
}
async function attempt(page,column,dx){
  const ret={column,deltaX:dx,stages:{},status:'INCOMPLETE'};
  const handle=page.locator(scope+' .ag-header-cell[col-id="'+column+'"] .ag-header-cell-resize').first();
  await handle.waitFor({timeout:10000});
  await arm(page);
  ret.stages.before=await inspect(page,column);
  const b=await handle.boundingBox();
  if(!b)throw Error('Missing real resize handle bounding box');
  const x=b.x+b.width/2,y=b.y+b.height/2;
  ret.intendedPointer={start:{x,y},end:{x:x+dx,y}};
  await page.mouse.move(x,y);
  ret.stages.hover=await inspect(page,column);
  await page.mouse.down();
  ret.stages.down=await inspect(page,column);
  await page.mouse.move(x+dx/2,y,{steps:4});
  ret.stages.middle=await inspect(page,column);
  await page.mouse.move(x+dx,y,{steps:4});
  ret.stages.endMove=await inspect(page,column);
  await page.mouse.up();
  ret.stages.up=await inspect(page,column);
  await page.waitForTimeout(400);
  ret.stages.stable=await inspect(page,column);
  ret.widths=Object.fromEntries(Object.entries(ret.stages).map(([k,s])=>[k,s.header?.width??null]));
  ret.eventTrace=ret.stages.stable.events;
  ret.mutationTrace=ret.stages.stable.mutations;
  ret.status='OBSERVED';
  return ret;
}
try{
  const context=await browser.newContext({viewport:report.viewport});
  const auth=await context.newPage();
  const login=await auth.goto(m.login_url,{waitUntil:'domcontentloaded'});
  if(!login?.ok())throw Error('WordPress login failed');
  await auth.locator('#user_login').fill('runtime_admin');
  await auth.locator('#user_pass').fill(process.env.WU008_ADMIN_PASSWORD);
  await Promise.all([auth.waitForURL(u=>!u.pathname.includes('wp-login.php')),
    auth.locator('#wp-submit').click()]);
  await auth.close();
  const cases=[
    {surface:'frontend',column:'created_by',dx:55,tag:'original'},
    {surface:'frontend',column:'created_by',dx:-55,tag:'opposite'},
    {surface:'frontend',column:'workflow_step',dx:55,tag:'resizable-control'},
    {surface:'frontend',column:'id',dx:55,tag:'locked-negative'},
    {surface:'admin',column:'created_by',dx:55,tag:'admin-comparison'},
  ];
  for(const spec of cases){
    const page=await context.newPage();
    const item={...spec,status:'INCOMPLETE'};
    report.cases.push(item);
    try{
      await prepare(page,spec.surface);
      const result=await attempt(page,spec.column,spec.dx);
      Object.assign(item,result);
    }catch(e){item.status='ERROR';item.error=String(e?.stack||e);}
    finally{await page.close();}
  }
  await context.close();
}finally{
  fs.writeFileSync(path.join(dir,'resize-'+mode+'.json'),JSON.stringify(report,null,2)+'\n');
  await browser.close();
}
console.log('G014_NATIVE_RESIZE_OBSERVATIONS '+mode+' '+JSON.stringify(
  report.cases.map(c=>({tag:c.tag,status:c.status,column:c.column,dx:c.deltaX,
    widths:c.widths,hit:c.stages?.before?.centerHit,style:c.stages?.before?.handleStyle,
    down:c.eventTrace?.filter(e=>['pointerdown','mousedown'].includes(e.type)),
    moves:c.eventTrace?.filter(e=>e.type==='pointermove').length,
    up:c.eventTrace?.filter(e=>['pointerup','mouseup'].includes(e.type)),
    error:c.error}))));
if(report.cases.some(c=>c.status!=='OBSERVED'))
  throw Error('Native resize observation incomplete; inspect JSON artifact');
