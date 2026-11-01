import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { chromium } from 'playwright';

const root = process.env.WU008_ARTIFACT_DIR;
const loginPassword = process.env.WU008_ADMIN_PASSWORD;
const head = process.env.WU008_PGR_SHA;
const locale = process.env.SCANNER_UI_LOCALE;
if (!root || !loginPassword || !head || !['fa_IR','en_US'].includes(locale)) {
  throw Error('Exact WU008 environment, credentials, source Head and locale are mandatory.');
}
const out = path.join(root, 'scanner-ui-readonly-evidence');
const fixture = JSON.parse(fs.readFileSync(path.join(out, 'fixture.json'), 'utf8'));
if (fixture.evidence_class !== 'REAL_LICENSED_GF_BUILDER_READONLY_GEOMETRY_FIXTURE' ||
    fixture.expected_source_commit !== head || fixture.wordpress !== '6.8.3' ||
    fixture.gravityforms !== '3.1.1.1' || fixture.registry_unchanged !== true) {
  throw Error('Authentic installed package, GF, form fixture or Registry preflight invalid.');
}
const browser = await chromium.launch({headless:true});
const report = {
  schema:1, class:'AUTHENTIC_LICENSED_GF_SCANNER_READONLY_UI', head, locale,
  wp:fixture.wordpress, gf:fixture.gravityforms, php:fixture.php,
  chromium:browser.version(), playwright:'1.55.0',
  device_scale_factor:1, source:fixture,
  screenshots:[], cases:[], read_only:true, registry_mutation:false,
  form_submissions:0, baseline_comparison:'NOT_RUN_SEPARATE_RESTORED_ENV_REQUIRED',
};
const states = [
  ['list','persian-gravityforms-scanner-profiles',''],
  ['create','persian-gravityforms-scanner-profiles','&action=new'],
  ['edit','persian-gravityforms-scanner-profiles','&action=edit&profile=wu008_ui_long_label'],
  ['form-builder',null,null],
];
const baseUrl = new URL(fixture.admin_url);
const targetUrl = (slug,suffix) => new URL('admin.php?page='+slug+suffix,baseUrl).href;
const geom = () => {
  const rect = (el) => {
    if (!el) return null;
    const r=el.getBoundingClientRect(),s=getComputedStyle(el);
    return {tag:el.tagName.toLowerCase(),id:el.id||null,className:String(el.className||'').slice(0,130),
      x:r.x,y:r.y,left:r.left,right:r.right,top:r.top,bottom:r.bottom,width:r.width,height:r.height,
      scrollWidth:el.scrollWidth,clientWidth:el.clientWidth,offsetWidth:el.offsetWidth,
      direction:s.direction,font:s.fontFamily,fontSize:s.fontSize,lineHeight:s.lineHeight,
      whiteSpace:s.whiteSpace,overflow:s.overflow,overflowX:s.overflowX,
      minWidth:s.minWidth,maxWidth:s.maxWidth,gridTemplateColumns:s.gridTemplateColumns,
      display:s.display,position:s.position,zIndex:s.zIndex,boxSizing:s.boxSizing,
      flex:s.flex,transform:s.transform,visibility:s.visibility};
  };
  const first = (s)=>document.querySelector(s);
  const all=(s)=>[...document.querySelectorAll(s)];
  const overlaps=(a,b)=>{
    if(!a||!b)return null;
    const w=Math.min(a.right,b.right)-Math.max(a.left,b.left);
    const h=Math.min(a.bottom,b.bottom)-Math.max(a.top,b.top);
    return {intersects:w>1&&h>1,overlapX:Math.max(0,w),overlapY:Math.max(0,h)};
  };
  const specific={
    lead:first('.pgr-structured-scanner__lead'),
    capture:first('.pgr-structured-scanner__capture'),
    buttons:first('.pgr-structured-scanner__actions'),
    status:first('.pgr-structured-scanner__status'),
    focus:first('.pgr-structured-scanner__focus'),
    privacy:first('.pgr-structured-scanner__privacy'),
    header:first('.pgr-structured-scanner__header'),
    mappingGrid:first('.pgr-scanner-editor-mappings'),
    sidebar:first('#sidebar, #field_settings, .gform-form-toolbar__sidebar, .gform-form-editor__sidebar'),
    nativeTextarea:first('#field_2 textarea, #field_2 .ginput_container_textarea, .ginput_container_textarea textarea'),
  };
  const preview={};
  for(const name of ['lead','capture','buttons','status','focus','privacy','header']) preview[name]=rect(specific[name]);
  const centerPairs=[['lead','capture'],['capture','buttons'],['focus','status'],['privacy','capture'],['header','lead']];
  const collisions=Object.fromEntries(centerPairs.map(([a,b])=>[a+'__'+b,overlaps(preview[a],preview[b])]));
  const mappings=all('.pgr-scanner-editor-mapping-row').map(x=>({row:rect(x),
    children:[...x.children].map(y=>rect(y)), grid:rect(x.closest('.pgr-scanner-editor-mappings'))}));
  const grid=rect(specific.mappingGrid);
  const sidebar=rect(specific.mappingGrid?.closest('#field_settings, #sidebar, .gform-form-editor__sidebar, .gform-form-toolbar__sidebar')||specific.sidebar);
  const clipped=mappings.flatMap((m,i)=>m.children.filter(c=>c && (
    (grid && (c.left<grid.left-1||c.right>grid.right+1))||
    (sidebar && (c.left<sidebar.left-1||c.right>sidebar.right+1))
  )).map(c=>({row:i,child:c})));
  const documentWidths={scrollWidth:document.documentElement.scrollWidth,clientWidth:document.documentElement.clientWidth,
    bodyScrollWidth:document.body.scrollWidth,innerWidth:innerWidth};
  const focusables=all('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]').filter(el=>{
    const s=getComputedStyle(el),r=el.getBoundingClientRect();
    return s.visibility!=='hidden'&&s.display!=='none'&&r.height>0&&r.width>0&&el.tabIndex>=0;
  }).slice(0,65).map(x=>({tag:x.tagName,id:x.id||null,role:x.getAttribute('role'),
    label:x.getAttribute('aria-label'),tabindex:x.tabIndex,text:(x.innerText||'').trim().slice(0,40)}));
  const inherited=(el)=>{const result=[];for(let n=el,i=0;n&&i<7;n=n.parentElement,i++){
    const s=getComputedStyle(n);
    result.push({tag:n.tagName,id:n.id||null,className:String(n.className||'').slice(0,100),
      direction:s.direction,font:s.fontFamily,fontSize:s.fontSize,lineHeight:s.lineHeight,
      textAlign:s.textAlign,whiteSpace:s.whiteSpace,overflow:s.overflow,
      width:s.width,display:s.display,position:s.position});
  }return result;};
  const cascade=(el)=>{if(!el)return [];
    const rules=[];
    for(const sheet of document.styleSheets){
      let list;try{list=sheet.cssRules;}catch{continue;}
      const walk=(group)=>{for(const rule of group){if(rule.type===CSSRule.STYLE_RULE){
        try{if(el.matches(rule.selectorText))rules.push({origin:sheet.href||'inline',selector:rule.selectorText,
          properties:Array.from(rule.style).filter(name=>/^(?:font|line-height|margin|padding|width|min-width|max-width|display|grid|overflow|direction|text-align|white-space|box-sizing|position)/.test(name)).slice(0,20)});}catch{}
      }else if(rule.cssRules){try{walk(rule.cssRules);}catch{}}}};
      try{walk(list);}catch{}
    }
    return rules.slice(-65);
  };
  const assetLinks=all('link[rel=stylesheet],script[src]').map(x=>x.href||x.src).filter(Boolean);
  const html=document.documentElement;
  return {html:{lang:html.lang,dir:html.dir,effectiveDirection:getComputedStyle(html).direction,
      bodyClass:document.body.className},
    viewport:{width:innerWidth,height:innerHeight,dpr:devicePixelRatio,visualScale:visualViewport?.scale??null,
      outerWidth,outerHeight},fonts:{status:document.fonts.status,body:getComputedStyle(document.body).fontFamily},
    documentWidths,preview,collisions,
    mapping:{grid,sidebar,rows:mappings,clippedChildren:clipped,overflow:grid?grid.scrollWidth>grid.clientWidth+1:false,
      minimumColumns:mappings[0]?.row?.gridTemplateColumns||null},
    nativeControl:rect(specific.nativeTextarea),focusables,
    centerComputed:{lead:inherited(specific.lead),capture:inherited(specific.capture),
      buttons:inherited(specific.buttons),status:inherited(specific.status)},
    centerCascade:{lead:cascade(specific.lead),capture:cascade(specific.capture),
      buttons:cascade(specific.buttons),status:cascade(specific.status)},
    stylesheets:assetLinks.filter(x=>x.includes('gravityforms')||x.includes('persian-gravityforms')).slice(0,100),
    textInkBoxes:Object.fromEntries(['lead','privacy','status'].map(key=>{
      const node=specific[key];if(!node)return [key,[]];
      const range=document.createRange();range.selectNodeContents(node);
      return [key,[...range.getClientRects()].map(r=>({left:r.left,right:r.right,top:r.top,bottom:r.bottom,width:r.width,height:r.height}))];
    }))};
};
function delta(a,b) {
  const x=a?.preview||{}, y=b?.preview||{}, movement={};
  for(const k of Object.keys(x))movement[k]=x[k]&&y[k]?{dx:y[k].x-x[k].x,dy:y[k].y-x[k].y,
    dwidth:y[k].width-x[k].width,dheight:y[k].height-x[k].height}:null;
  return {mappingOverflowBefore:a.mapping.overflow,mappingOverflowAfter:b.mapping.overflow,
    clippedBefore:a.mapping.clippedChildren.length,clippedAfter:b.mapping.clippedChildren.length,
    centerBefore:a.collisions,centerAfter:b.collisions,centerMovement:movement};
}
try{
  for(const width of [1280,390,320]){
    const height=width===1280?900:width===390?844:700;
    const context=await browser.newContext({viewport:{width,height},deviceScaleFactor:1,locale:locale==='fa_IR'?'fa-IR':'en-US'});
    const page=await context.newPage();
    const failures=[];page.on('pageerror',e=>failures.push(String(e.message).slice(0,300)));
    const login=await page.goto(fixture.login_url,{waitUntil:'domcontentloaded'});
    if(!login?.ok())throw Error('Real WP login failed HTTP: '+login?.status());
    await page.locator('#user_login').fill('runtime_admin');
    await page.locator('#user_pass').fill(loginPassword);
    await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('#wp-submit').click()]);
    await page.locator('#wpadminbar').waitFor({state:'attached'});
    for(const [state,slug,extra] of states){
      const record={state,width,height,locale,authentic:false,errors:[],diagnostics:[]};
      let failed=false;
      try{
        const url=state==='form-builder'?fixture.form_builder_url:targetUrl(slug,extra);
        const response=await page.goto(url,{waitUntil:'domcontentloaded'});
        record.httpStatus=response?.status()??null;record.actualUrl=page.url();
        if(!response?.ok()||page.url().includes('wp-login.php'))throw Error('Native authenticated page did not load');
        await page.locator('#wpadminbar').waitFor({state:'attached'});
        if(state==='form-builder'){
          await page.locator('#field_3').waitFor({state:'attached',timeout:20000});
          await page.waitForFunction(()=>typeof window.PGRScannerEditor==='object' &&
            typeof window.PGRScannerEditor.setProfile==='function',null,{timeout:15000});
          record.realGfFormModel=await page.evaluate(()=>({present:typeof window.form==='object',
            scanner:window.form?.fields?.some(f=>f.type==='pgr_structured_scanner')||false}));
          const clicked=await page.evaluate(()=>{
            const b=document.querySelector('#gfield_edit_3');if(!b)return false;b.click();return true;
          });
          if(!clicked)throw Error('Native GF scanner edit button missing');
          if(!(await page.locator('#advanced_tab_toggle').isVisible())){
            await page.locator('#field_3').click({force:true});
          }
          await page.locator('#advanced_tab_toggle').waitFor({state:'visible',timeout:10000});
          await page.evaluate(()=>{const tab=document.querySelector('#advanced_tab_toggle');if(tab?.getAttribute('aria-expanded')!=='true')tab?.click();});
          await page.locator('#advanced_tab').waitFor({state:'visible',timeout:10000});
          await page.locator('#pgr_scanner_profile').waitFor({state:'visible',timeout:10000});
          await page.locator('[data-pgr-scanner-mapping="qr_version"]').waitFor({state:'visible',timeout:10000});
          if(await page.locator('.pgr-scanner-editor-mapping-row').count()!==7)
            throw Error('Native Scanner seven-row mapping grid not loaded');
        }else if(state==='list'){
          await page.locator('.pgr-admin').waitFor({state:'attached'});
          if(!(await page.locator('.pgr-admin').innerText()).includes('LongLabel_12345'))throw Error('Existing real WU008 synthetic Registry fixture absent');
        }else if(state==='create' || state==='edit'){
          await page.locator('.pgr-profile-form').waitFor({state:'attached'});
          if(state==='edit'&& !(await page.locator('input[name="profile[label]"]').inputValue()).includes('LongLabel_12345'))
            throw Error('Existing persisted fixture not populated in real edit UI');
        }
        await page.evaluate(async()=>{await document.fonts.ready;});
        if(state==='form-builder'){
          const cssUrl=await page.evaluate(()=>[...document.querySelectorAll('link[href]')]
            .map(x=>x.href).find(x=>x.includes('/assets/css/pgr-scanner-editor.css'))||null);
          if(!cssUrl)throw Error('Installed plugin Scanner editor CSS was not served in actual Form Builder');
          const cssResponse=await context.request.get(cssUrl);
          if(!cssResponse.ok())throw Error('Served plugin Scanner CSS request failed: '+cssResponse.status());
          const hash=crypto.createHash('sha256').update(await cssResponse.body()).digest('hex');
          record.servedCss={url:cssUrl,sha256:hash,
            matchesInstalledBytes:hash===fixture.installed_scanner_css_sha256};
          if(!record.servedCss.matchesInstalledBytes)throw Error('Served Scanner CSS bytes differ from installed verified package');
        }
        const measured=await page.evaluate(geom);
        record.authentic=(await page.locator('body').getAttribute('class')||'').includes('wp-admin');
        const expectedDir=locale==='fa_IR'?'rtl':'ltr';
        if(measured.html.effectiveDirection!==expectedDir)record.diagnostics.push('Direction mismatch: '+measured.html.effectiveDirection);
        if(measured.html.lang.toLowerCase().replace('_','-')!==(locale==='fa_IR'?'fa-ir':'en-us'))
          record.diagnostics.push('Native locale mismatch: '+measured.html.lang);
        if(measured.fonts.status!=='loaded')record.diagnostics.push('Fonts not loaded at measurement boundary');
        if(state==='form-builder'&&!record.realGfFormModel?.scanner)throw Error('Real GF Form Builder Scanner model missing');
        record.baseline=measured;
        record.keyboardFocus=[];
        for(let k=0;k<10;k++){
          await page.keyboard.press('Tab');
          record.keyboardFocus.push(await page.evaluate(()=>{
            const a=document.activeElement,s=a&&getComputedStyle(a);
            return {tag:a?.tagName||null,id:a?.id||null,kind:a?.getAttribute('type')||null,
              focusVisible:a?.matches(':focus-visible')||false,
              outlineStyle:s?.outlineStyle||null,outlineWidth:s?.outlineWidth||null,
              boxShadow:s?.boxShadow||null,tabIndex:a?.tabIndex??null};
          }));
        }
        const shot=locale+'-'+state+'-'+width+'.png';
        await page.screenshot({path:path.join(out,shot),type:'png',fullPage:true,animations:'disabled'});
        record.screenshot=shot;
        report.screenshots.push(shot);
        if(state==='form-builder'){
          const control=await page.evaluate(()=>{
            const rows=[...document.querySelectorAll('.pgr-scanner-editor-mapping-row')];
            const old=rows.map(r=>r.style.getPropertyValue('grid-template-columns'));
            const priority=rows.map(r=>r.style.getPropertyPriority('grid-template-columns'));
            rows.forEach(r=>r.style.setProperty('grid-template-columns','minmax(0, 1fr)','important'));
            return {count:rows.length,old,priority};
          });
          record.oneVariable={mutation:'mapping rows grid-template-columns only, in browser memory',control};
          await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
          const after=await page.evaluate(geom);
          record.oneVariable.after=after;
          record.oneVariable.delta=delta(measured,after);
          const afterShot=locale+'-form-builder-'+width+'-grid-one-column.png';
          await page.screenshot({path:path.join(out,afterShot),type:'png',fullPage:true,animations:'disabled'});
          record.oneVariable.screenshot=afterShot;
          report.screenshots.push(afterShot);
          await page.evaluate((c)=>{document.querySelectorAll('.pgr-scanner-editor-mapping-row').forEach((r,i)=>{
            if(c.old[i])r.style.setProperty('grid-template-columns',c.old[i],c.priority[i]);else r.style.removeProperty('grid-template-columns');
          });},control);
          record.oneVariable.restored=true;
          const runtimeCssUrl=record.servedCss.url.replace('pgr-scanner-editor.css','pgr-structured-scanner.css');
          const css=await context.request.get(runtimeCssUrl);
          if(!css.ok())throw Error('Installed Scanner preview stylesheet unavailable to browser');
          const runtimeHash=crypto.createHash('sha256').update(await css.body()).digest('hex');
          if(runtimeHash!==fixture.installed_scanner_runtime_css_sha256)
            throw Error('Scanner preview stylesheet served bytes differ from installed runtime');
          record.cssAttribution={
            independentControl:'only append exact installed preview CSS in browser memory after grid is restored',
            url:runtimeCssUrl,sha256:runtimeHash,
            originallyLoaded:measured.stylesheets.some(s=>s.includes('pgr-structured-scanner.css'))
          };
          if(record.cssAttribution.originallyLoaded)
            throw Error('Preview CSS already present; missing-style causal hypothesis invalid');
          await page.evaluate(url=>new Promise((resolve,reject)=>{
            const link=document.createElement('link');link.rel='stylesheet';link.href=url;
            link.dataset.pgrReadOnlyTest='1';
            link.onload=resolve;link.onerror=()=>reject(Error('Preview CSS browser load failed'));
            document.head.append(link);
          }),runtimeCssUrl);
          await page.evaluate(async()=>{await document.fonts.ready;
            await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));});
          record.cssAttribution.after=await page.evaluate(geom);
          record.cssAttribution.delta=delta(measured,record.cssAttribution.after);
          const cssShot=locale+'-form-builder-'+width+'-preview-css-only.png';
          await page.screenshot({path:path.join(out,cssShot),type:'png',fullPage:true,animations:'disabled'});
          record.cssAttribution.screenshot=cssShot;report.screenshots.push(cssShot);
          await page.evaluate(()=>document.querySelector('link[data-pgr-read-only-test="1"]')?.remove());
          record.cssAttribution.restored=true;
        }
        record.pageErrors=failures.splice(0);
      }catch(e){
        failed=true;record.errors.push(String(e.stack||e).slice(0,900));
        record.pageErrors=failures.splice(0);
        const failureShot=locale+'-'+state+'-'+width+'-failure.png';
        await page.screenshot({path:path.join(out,failureShot),type:'png',fullPage:true}).then(()=>{
          report.screenshots.push(failureShot);record.screenshot=failureShot;
        }).catch(()=>{});
      }
      record.qualification=failed?'BLOCKED_CAPTURED_FAILURE':'CAPTURED_FOR_REVIEW';
      report.cases.push(record);
    }
    await context.close();
  }
}finally{await browser.close();}
const failures=report.cases.filter(x=>x.qualification==='BLOCKED_CAPTURED_FAILURE');
const contact=['# Authentic Scanner UI read-only qualification — '+locale,'',
  'Source: real WordPress 6.8.3 / Gravity Forms 3.1.1.1 / PersianGravity installed package; Head `'+head+'`.',
  'No profile save/delete, no form submissions, no Registry changes by this qualification script.',
  'Each screenshot is full-page original PNG; JSON contains DOM geometry and the real computed cascade.',
  '','| State | 1280px | 390px | 320px |','|---|---|---|---|'];
for(const state of states.map(x=>x[0])){
  contact.push('| '+state+' | '+[1280,390,320].map(w=>{
    const c=report.cases.find(x=>x.state===state&&x.width===w);
    return c?.screenshot?'[Original PNG]('+c.screenshot+') — '+c.qualification:'Not captured';
  }).join(' | ')+' |');
}
contact.push('','## One-variable browser-memory mapping control','');
for(const w of [1280,390,320]){
  const c=report.cases.find(x=>x.state==='form-builder'&&x.width===w);
  contact.push('- '+w+'px: '+(c?.oneVariable?.screenshot?'[Single-column PNG]('+c.oneVariable.screenshot+')':'NOT_VERIFIED')+
    '; '+(c?.cssAttribution?.screenshot?'[Preview CSS-only PNG]('+c.cssAttribution.screenshot+')':'Preview CSS NOT_VERIFIED'));
}
contact.push('','## Limits','No pre/post PR106 equivalence is claimed without distinct isolated database/byte-matched environments.',
  'Viewport changes and in-browser temporary column override are not persistent changes.',
  'Save failure is not re-executed because this qualification prohibits form submission.','');
fs.writeFileSync(path.join(out,'geometry-'+locale+'.json'),JSON.stringify(report,null,2)+'\n');
fs.writeFileSync(path.join(out,'CONTACT-'+locale+'.md'),contact.join('\n')+'\n');
const pngs=report.screenshots.map(name=>({name,sha256:crypto.createHash('sha256').update(fs.readFileSync(path.join(out,name))).digest('hex')}));
fs.writeFileSync(path.join(out,'SHA256-'+locale+'.json'),JSON.stringify(pngs,null,2)+'\n');
console.log('SCANNER_NATIVE_UI_READONLY '+locale+' cases='+report.cases.length+' captured='+report.screenshots.length+' blocked='+failures.length);
if(report.cases.length!==12||failures.length)process.exitCode=1;
