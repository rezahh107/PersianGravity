import { observePageEvents } from './vazir-page-events.mjs';
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const profile = process.argv[2];
const locale = process.argv[3];
const allowed = ['BASELINE', 'WITH_VAZIR'];
if (!allowed.includes(profile) || !['fa_IR','en_US'].includes(locale)) {
  throw Error('Explicit BASELINE/WITH_VAZIR and fa_IR/en_US required');
}
for (const key of ['WU008_ARTIFACT_DIR','WU008_MANIFEST_PATH','WU008_ADMIN_PASSWORD','WU008_PGR_SHA','WU008_PGR_VERSION']) {
  if (!process.env[key]) throw Error('Missing authentic WU008 environment: ' + key);
}
const manifest = JSON.parse(fs.readFileSync(process.env.WU008_MANIFEST_PATH, 'utf8'));
if (manifest.persiangravity_source_commit !== process.env.WU008_PGR_SHA ||
  manifest.versions?.persiangravity !== process.env.WU008_PGR_VERSION) {
  throw Error('Incorrect installed PersianGravity exact Head or version');
}
const root = path.join(process.env.WU008_ARTIFACT_DIR, 'vazir-coexistence-evidence');
fs.mkdirSync(root, {recursive:true});
const normalized = profile.toLowerCase().replace('_','-');
const prefix = normalized + '-' + locale;
const origin = new URL(manifest.admin_url).origin;
for (const value of [manifest.page_url,manifest.login_url,manifest.gravityflow_inbox_url,
  manifest.gravityflow_frontend_inbox_url, manifest.gravityperks_frontend_url, manifest.g008_gravityview?.page_url]) {
  if(!value)throw Error('Missing existing authenticated Gravity fixture URL');
  if (new URL(value).origin !== origin) throw Error('Untrusted WU008 application origin');
}
const adminPage = (slug) => new URL('admin.php?page=' + slug, manifest.admin_url).href;
const pages = [
  {name:'overview',url:adminPage('persian-gravityforms'),selector:'.pgr-module-card'},
  {name:'settings',url:adminPage('persian-gravityforms-settings'),selector:'.pgr-panel'},
  {name:'scanner',url:adminPage('persian-gravityforms-scanner-profiles'),selector:'.pgr-admin'},
  {name:'system-status',url:adminPage('persian-gravityforms-system-status'),selector:'.pgr-compatibility-status'},
  {name:'gravity-form',url:manifest.page_url,selector:'.gform_wrapper'},
  {name:'gravity-flow',url:manifest.gravityflow_inbox_url,selector:'body'},
  {name:'flow-frontend',url:manifest.gravityflow_frontend_inbox_url,selector:'body'},
  {name:'gravityview',url:manifest.g008_gravityview.page_url,selector:'body'},
  {name:'gravityperks',url:manifest.gravityperks_frontend_url,selector:'body'},
];
const browser = await chromium.launch({headless:true});
const result = {schema:'g013-vazir-coexistence-v1',exact_head:process.env.WU008_PGR_SHA,
  persiangravity_version:process.env.WU008_PGR_VERSION,
  profile,locale,vazir_version:profile==='WITH_VAZIR'?'1.5.0':null,
  expected_vazir_sha256:profile==='WITH_VAZIR'?
    '95fdc3774d6079c34c04331bb8acf980a699e02a215e322742dccbc4109802f5':null,
  environment:{wordpress:'6.8.3',php:manifest.php_version,gravity:manifest.versions,
    chromium:browser.version(),playwright:'1.55.0',db_snapshot_sha256:process.env.WU008_G013_DB_SNAPSHOT_SHA||null},
  status:'INCOMPLETE',grid_acceptance:'NOT_PROVEN',pages:[],known_preexisting:['PR #96 historical six System Status overflow findings'],
  limitations:['Same disposable WordPress host; profiles run sequentially with plugin activation and state restoration.',
    'Not proof of Owner deployed Vazir settings, screen-reader speech, all possible GravityView editor portals or third-party frontends.']};
try {
  for (const width of [1280,390]) {
    const context = await browser.newContext({viewport:{width,height:width===1280?900:844},deviceScaleFactor:1});
    const page = await context.newPage();
    const response = await page.goto(manifest.login_url,{waitUntil:'domcontentloaded'});
    if (!response?.ok()) throw Error('Real WordPress login page inaccessible');
    await page.locator('#user_login').fill('runtime_admin');
    await page.locator('#user_pass').fill(process.env.WU008_ADMIN_PASSWORD);
    await Promise.all([page.waitForURL(u=>!u.pathname.includes('wp-login.php')),
      page.locator('#wp-submit').click()]);
    for (const spec of pages) {
      const events=observePageEvents(page);
      const call = await page.goto(spec.url,{waitUntil:'domcontentloaded'});
      if (!call?.ok()) throw Error('Authenticated page unavailable: ' + spec.name + ' ' + call?.status());
      await page.locator(spec.selector).first().waitFor({state:'attached',timeout:12000});
      // Existing exact-vendor fixtures are already created by earlier WU008 steps.
      // Exercise their actual dynamic controls rather than asserting static CSS only.
      let interaction=null;
      if(spec.name==='gravity-form'){
        // Actual GF 3.1.1.1 server validation on the same page used for
        // BASELINE/WITH_VAZIR captures. This is not a synthetic DOM error.
        const id=Number(manifest.form_id);
        if(!Number.isSafeInteger(id)||id<1)
          throw Error('Missing real Gravity Forms required-field fixture');
        const field=page.locator('#input_'+id+'_1');
        const submit=page.locator('#gform_submit_button_'+id);
        await field.waitFor({state:'visible',timeout:15000});
        await submit.waitFor({state:'visible',timeout:15000});
        if(await field.inputValue()!=='')
          throw Error('GF required-field negative control was not empty');
        await field.focus();
        if(!await field.evaluate(el=>document.activeElement===el))
          throw Error('GF required field did not receive real keyboard focus');
        await page.keyboard.press('Tab');
        if(!await submit.evaluate(el=>document.activeElement===el))
          throw Error('GF Tab order did not reach the submit control');
        await page.keyboard.press('Enter');
        const error=page.locator('#field_'+id+'_1.gfield_error, #gform_wrapper_'+id+' .gform_validation_errors');
        await error.first().waitFor({state:'visible',timeout:20000});
        if(await field.inputValue()!=='' || await error.count()===0)
          throw Error('GF server-side required-field validation not observed');
        // No valid submission/entry is created; the page is deliberately
        // captured in its authentic failed-validation state for both profiles.
      }
      if(spec.name==='gravityview'){
        const tokens=manifest.g008_gravityview.entries.map(x=>x.token);
        await page.waitForFunction(
          expected=>expected.every(token=>document.body?.innerText.includes(token)),
          tokens,{timeout:20000});
        interaction={type:'authentic-gravityview',tokens_seen:tokens.length};
      }
      if(spec.name==='gravityperks'){
        const formId=Number(manifest.gravityperks_form_id);
        const fileId=Number(manifest.gravityperks_file_upload_field_id);
        const selectId=Number(manifest.gravityperks_advanced_select_field_id);
        if(![formId,fileId,selectId].every(Number.isInteger))throw Error('Missing genuine GP add-on fixture');
        const fileRoot='#field_'+formId+'_'+fileId;
        const selectRoot='#field_'+formId+'_'+selectId;
        await page.locator(fileRoot+' .gpfup__droparea').waitFor({timeout:15000});
        await page.locator(selectRoot+' .ts-wrapper').waitFor({timeout:15000});
        const input=page.locator(fileRoot+' input[type=file]').first();
        await input.setInputFiles({name:'vazir-probe.txt',mimeType:'text/plain',
          buffer:Buffer.from('G013 disposable WU008 coexistence fixture')});
        await page.locator(fileRoot+' .gpfup__filename').first().waitFor({state:'visible',timeout:30000});
        // File Upload Pro 1.5.13 renders the filename before Plupload DONE.
        await page.locator(fileRoot+' .gpfup__progress-container').first()
          .waitFor({state:'hidden',timeout:30000});
        await page.waitForTimeout(100);
        await page.locator(fileRoot+' .gpfup__filename').first().waitFor({state:'visible',timeout:15000});
        const search=page.locator(selectRoot+' .ts-control input').first();
        await search.focus();
        // The previous .ts-control count() checked existence, not focus.
        // Verify that an actual editable control received keyboard focus.
        const focusedBefore=await search.evaluate(el=>document.activeElement===el);
        if(!focusedBefore)throw Error('Advanced Select did not receive focus');
        await search.fill('');
        await search.pressSequentially('Beta');
        await page.locator(selectRoot+' .ts-dropdown .option:visible').first().waitFor({timeout:10000});
        await page.keyboard.press('ArrowDown');
        await page.keyboard.press('Enter');
        const selected=await page.locator('#input_'+formId+'_'+selectId).inputValue();
        if(!selected)throw Error('Advanced Select interaction did not synchronize native value');
        // Pressing ArrowDown/Enter must not strand focus outside Tom Select.
        const focusedAfter=await page.evaluate(selector=>{
          const root=document.querySelector(selector);
          return Boolean(root&&root.contains(document.activeElement)&&
            getComputedStyle(document.activeElement).visibility!=='hidden');
        },selectRoot+' .ts-wrapper');
        if(!focusedAfter)throw Error('Advanced Select lost focus after keyboard selection');
        interaction={type:'authentic-file-upload-and-tom-select',
          file_preview:true,selected_value: selected ? 'NONEMPTY' : 'EMPTY',
          focus_verified:focusedBefore&&focusedAfter};
      }
      if(['gravity-flow','flow-frontend'].includes(spec.name)) {
        // A visible shell does not prove AG Grid initialization completed.
        const ready=()=> {
          const host=document.querySelector('[data-js="gflow-inbox"]');
          const root=host?.querySelector('.ag-root-wrapper,.ag-root');
          const visible=e=>{if(!e)return false;const r=e.getBoundingClientRect(),s=getComputedStyle(e);
            return r.width>0&&r.height>0&&s.display!=='none'&&s.visibility!=='hidden';};
          const headers=[...document.querySelectorAll('[data-js="gflow-inbox"] .ag-header-cell')].filter(visible);
          const rows=[...document.querySelectorAll('[data-js="gflow-inbox"] .ag-center-cols-container .ag-row')].filter(visible);
          const empty=[...document.querySelectorAll('[data-js="gflow-inbox"] .ag-overlay-no-rows-center,[data-js="gflow-inbox"] .ag-overlay-no-rows-wrapper')].filter(visible);
          const loading=[...document.querySelectorAll('[data-js="gflow-inbox"] .ag-overlay-loading-center,[data-js="gflow-inbox"] .ag-overlay-loading-wrapper,[data-js="gflow-inbox"] [aria-busy="true"]')].filter(visible);
          const id=host?.dataset?.gridId||'inbox_default';
          const cfg=window.gflow_config?.grids?.[id]?.grid_options;
          return {ready:Boolean(root&&visible(root)&&headers.length&&!loading.length&&
            (rows.length||(empty.length&&Array.isArray(cfg?.rowData)&&cfg.rowData.length===0))),
            rows:rows.length,headers:headers.length,empty:empty.length,loading:loading.length,
            mode:root?.className||null,enableRtl:cfg?.enableRtl??null};
        };
        await page.waitForFunction(()=> {
          const host=document.querySelector('[data-js="gflow-inbox"]');
          const root=host?.querySelector('.ag-root-wrapper,.ag-root');
          const visible=e=>{if(!e)return false;const b=e.getBoundingClientRect(),s=getComputedStyle(e);
            return b.width>0&&b.height>0&&s.display!=='none'&&s.visibility!=='hidden';};
          const header=[...document.querySelectorAll('[data-js="gflow-inbox"] .ag-header-cell')].some(visible);
          const row=[...document.querySelectorAll('[data-js="gflow-inbox"] .ag-center-cols-container .ag-row')].some(visible);
          const empty=[...document.querySelectorAll('[data-js="gflow-inbox"] .ag-overlay-no-rows-center,[data-js="gflow-inbox"] .ag-overlay-no-rows-wrapper')].some(visible);
          const loading=[...document.querySelectorAll('[data-js="gflow-inbox"] .ag-overlay-loading-center,[data-js="gflow-inbox"] .ag-overlay-loading-wrapper,[data-js="gflow-inbox"] [aria-busy="true"]')].some(visible);
          const id=host?.dataset?.gridId||'inbox_default';
          const data=window.gflow_config?.grids?.[id]?.grid_options?.rowData;
          return Boolean(root&&visible(root)&&header&&!loading&&(row||(empty&&Array.isArray(data)&&data.length===0)));
        },null,{timeout:30000,polling:250});
        const first=await page.evaluate(ready);
        await page.waitForTimeout(350);
        const second=await page.evaluate(ready);
        if(!first.ready||!second.ready||
          !['rows','headers','empty','loading','mode','enableRtl'].every(k=>first[k]===second[k]))
          throw Error('Authentic AG Grid was not stably ready '+spec.name+'/'+width);
      }
      const data = await page.evaluate(async () => {
        await document.fonts.ready;
        const css = (sel) => {
          const el=document.querySelector(sel);
          if(!el) return null;
          const st=getComputedStyle(el);
          const r=el.getBoundingClientRect();
          return {family:st.fontFamily,weight:st.fontWeight,direction:st.direction,
            width:Math.round(r.width),height:Math.round(r.height),overflowX:st.overflowX};
        };
        const faces=Array.from(document.fonts).filter(x=>/vazirmatn/i.test(x.family))
          .map(x=>({family:x.family,weight:x.weight,status:x.status}));
        const el=document.documentElement;
        const visibleInbox=['[data-js="gflow-inbox"]','.gravityflow-inbox','.ag-root-wrapper',
          '.wrap','#wpbody-content'].map(selector=>{
          const node=document.querySelector(selector);
          if(!node)return null;
          const rect=node.getBoundingClientRect();
          if(!rect.width||!rect.height)return null;
          const s=getComputedStyle(node);
          return {selector,direction:s.direction,css_direction:s.direction,
            rect:{x:Math.round(rect.x),y:Math.round(rect.y),
              width:Math.round(rect.width),height:Math.round(rect.height)},
            scroll_width:node.scrollWidth,client_width:node.clientWidth};
        }).filter(Boolean);
        const nodes=[...document.querySelectorAll('.ag-root-wrapper,.ag-root,.ag-theme-alpine')];
        const gridNode=nodes.find(n=>n.getBoundingClientRect().width>0 &&
          (n.classList.contains('ag-rtl')||n.classList.contains('ag-ltr')));
        const gridMode=gridNode?(gridNode.classList.contains('ag-rtl')?'rtl':'ltr'):null;
        const rtl_diagnostic={
          html:{lang:el.lang,dir_attr:el.getAttribute('dir'),computed:getComputedStyle(el).direction},
          body:{dir_attr:document.body?.getAttribute('dir'),computed:getComputedStyle(document.body).direction},
          visible_inbox:visibleInbox,grid_mode:gridMode,grid_nodes:nodes.length,
          loaded_stylesheets:[...document.styleSheets].map(sheet=>sheet.href)
            .filter(href=>typeof href==='string' && /gravity|persian|vazir|wp-admin/i.test(href))
            .map(href=>{try{return new URL(href).pathname.split('/').slice(-4).join('/');}catch{return 'unknown';}}),
          viewport:{width:innerWidth,height:innerHeight},
          document_width:el.scrollWidth,body_width:document.body?.scrollWidth||0,
        };
        return {rtl_diagnostic,direction:getComputedStyle(el).direction,viewport:innerWidth,
          document_width:el.scrollWidth,body:css('body'),headline:css('h1'),
          label:css('.gfield_label, .pgr-panel h2, .pgr-module-card h3'),
          input:css('input:not([type=hidden]), textarea, select'),
          button:css('button, input[type=submit]'),icon:css('.dashicons, .dashicons-before'),
          faces,active_control:document.activeElement?.tagName||null};
      });
      const observed = {name:spec.name,width, ...data,interaction,
        ...events.snapshot()};
      if(observed.font_network.some(x=>x.status!==200))
        throw Error('WOFF2 response not HTTP 200 '+spec.name+' '+width);
      if(observed.page_errors.length||observed.console_errors.length)
        throw Error('Real browser JS/console error '+spec.name+' '+width);
      // Do not infer UI behavior from the HTML root: genuine Gravity Forms
      // admin CSS may set html computed LTR despite a visible RTL Inbox.
      // A wrong native AG Grid mode is an explicit failure, not a successful RTL.
      if (['gravity-flow','flow-frontend'].includes(spec.name)) {
        const expected=locale==='fa_IR'?'rtl':'ltr',actual=data.rtl_diagnostic.grid_mode;
        observed.grid_mode={expected,actual,status:actual===null?'NOT_PROVEN':
          actual===expected?'PASS':'FAIL'};
        if(observed.grid_mode.status!=='PASS')
          console.error('G013_ACTUAL_GRID_MODE_FINDING '+JSON.stringify({
            profile,locale,surface:spec.name,width,...observed.grid_mode,
            html_direction:data.rtl_diagnostic.html.computed}));
      }
      if(locale==='fa_IR'&&data.direction!=='rtl')
        observed.rtl_diagnostic.html_root_ltr_anomaly=true;
      if (['overview','gravity-form','gravityview','gravityperks'].includes(spec.name)) {
        const image=prefix + '-' + spec.name + '-' + width + '.jpg';
        await page.screenshot({path:path.join(root,image),type:'jpeg',quality:76,fullPage:true});
        observed.image=image;
      }
      result.pages.push(observed);
      events.stop();
    }
    await context.close();
  }
  for (const width of [1280,390]) {
    const cases=result.pages.filter(p=>p.width===width);
    const samePage=cases.some(p=>p.faces?.some(f=>/vazirmatn/i.test(f.family)&&f.status==='loaded')&&
      p.font_network?.some(f=>f.status===200)&&
      [p.body,p.headline,p.label,p.input,p.button].some(x=>x&&/vazirmatn/i.test(x.family)));
    const contaminated=cases.some(p=>p.faces?.some(f=>/vazirmatn/i.test(f.family)&&f.status==='loaded')||
      p.font_network?.some(f=>f.status===200)||
      [p.body,p.headline,p.label,p.input,p.button].some(x=>x&&/vazirmatn/i.test(x.family)));
    if(profile==='WITH_VAZIR'&&locale==='fa_IR'&&!samePage)
      throw Error('Same-page real WOFF2, loaded face and computed family missing at '+width);
    if(profile==='BASELINE'&&contaminated)
      throw Error('BASELINE contaminated at '+width);
  }
  if (result.pages.length!==18) throw Error('Incomplete actual browser case matrix');
  // Capture completeness is separate from native RTL correctness.
  result.grid_acceptance=result.pages.some(p=>p.grid_mode?.status==='FAIL')?'FAIL':
    result.pages.some(p=>p.grid_mode?.status==='NOT_PROVEN')?'NOT_PROVEN':'PASS';
  result.status='PASS';
  console.log('G013_PROFILE_CAPTURED '+profile+' '+locale+' GRID='+result.grid_acceptance);
} catch(error) {
  result.status='FAIL';
  result.error=String(error).slice(0,450);
  throw error;
} finally {
  fs.writeFileSync(path.join(root,prefix+'.json'),JSON.stringify(result,null,2)+'\n');
  await browser.close();
}
