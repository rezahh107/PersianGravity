/**
 * Read-only, authentic WU008 WordPress/GF Form Builder UI qualification.
 * The PHP fixture is created before the observer; browser never POSTs, saves,
 * updates form properties or changes Registry state. CSS perturbation is in-memory.
 */
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import {chromium} from 'playwright';

const out=path.join(process.env.WU008_ARTIFACT_DIR||'', 'scanner-ui-qualification-evidence');
const head=process.env.WU008_PGR_SHA;
const locale=process.env.WU008_SCANNER_AUDIT_LOCALE;
const password=process.env.WU008_ADMIN_PASSWORD;
const root=process.env.WU008_WP_PATH;
const environment=JSON.parse(fs.readFileSync(process.env.WU008_MANIFEST_PATH,'utf8'));
const fixture=JSON.parse(fs.readFileSync(path.join(out,'fixture.json'),'utf8'));
const check=(ok,msg)=>{if(!ok) throw Error('SCANNER_UI_QUALIFICATION: '+msg);};
check(['fa_IR','en_US'].includes(locale),'invalid locale');
check(head&&head===environment.persiangravity_source_commit &&
  head===fixture.source_commit_installed,'plugin package provenance mismatch');
check(environment.versions.gravityforms==='3.1.1.1' &&
  fixture.gravityforms==='3.1.1.1' && fixture.wordpress==='6.8.3','host mismatch');
const hash=b=>crypto.createHash('sha256').update(b).digest('hex');
const base=new URL(environment.admin_url);
const profileUrl=new URL('admin.php?page=persian-gravityforms-scanner-profiles',base).href;
const urlSuffix=extra=>profileUrl+extra;
const builderUrl=new URL('admin.php?page=gf_edit_forms&id='+fixture.form_id,base).href;
const browsers=[{width:1280,height:900},{width:390,height:844},{width:320,height:700}];
const statuses=['list','create','edit','built-in','builder'];
const snapshot=()=>({
  viewport:{width:innerWidth,height:innerHeight,dpr:devicePixelRatio,zoom:visualViewport?.scale??null},
  page:{lang:document.documentElement.lang,dir:getComputedStyle(document.documentElement).direction,
    scrollWidth:document.documentElement.scrollWidth,clientWidth:document.documentElement.clientWidth,
    overflow:document.documentElement.scrollWidth>document.documentElement.clientWidth+1,
    fontsReady:document.fonts.status},
  nodes:(()=>{
    const selectors={
      center:'.pgr-structured-scanner',
      header:'.pgr-structured-scanner__header',
      lead:'.pgr-structured-scanner__lead',
      privacy:'.pgr-structured-scanner__privacy',
      textarea:'.pgr-structured-scanner__capture',
      actions:'.pgr-structured-scanner__actions',
      button:'.pgr-structured-scanner__focus',
      status:'.pgr-structured-scanner__status',
      left:'.pgr_scanner_setting',
      mappingList:'[data-pgr-scanner-mapping-list]',
      mappingRow:'.pgr-scanner-editor-mapping-row',
      mappingLabel:'.pgr-scanner-editor-mapping-label',
      mappingSelect:'[data-pgr-scanner-mapping]',
      mappingDescription:'.pgr_scanner_setting .description',
      nativeControl:'#field_1',
      profileCard:'.pgr-profile-card',
      profileTitle:'.pgr-profile-card h2',
      editForm:'.pgr-profile-form',
      outputRow:'.pgr-output-row',
      adminHeader:'.pgr-admin__header',
    };
    const nodes={};
    for(const [key,selector] of Object.entries(selectors)){
      nodes[key]=[...document.querySelectorAll(selector)].slice(0,12).map(e=>{
        const r=e.getBoundingClientRect(),c=getComputedStyle(e);
        const textRanges=[];
        if(['lead','privacy','status','mappingDescription'].includes(key)){
          const walker=document.createTreeWalker(e,NodeFilter.SHOW_TEXT);
          let t;while((t=walker.nextNode()) && textRanges.length<20){
            if(!t.textContent.trim())continue;
            const range=document.createRange();range.selectNodeContents(t);
            textRanges.push(...[...range.getClientRects()].slice(0,10).map(v=>({x:v.x,y:v.y,w:v.width,h:v.height})));
          }
        }
        const ancestors=[];
        for(let a=e.parentElement;a&&ancestors.length<6;a=a.parentElement){
          const z=a.getBoundingClientRect();
          ancestors.push({tag:a.tagName,class:String(a.className||'').slice(0,90),
            clientWidth:a.clientWidth,scrollWidth:a.scrollWidth,
            rect:{x:z.x,y:z.y,w:z.width,h:z.height}});
        }
        return {tag:e.tagName,class:String(e.className||'').slice(0,120),
          text:(e.textContent||'').trim().slice(0,140),
          rect:{x:r.x,y:r.y,w:r.width,h:r.height,right:r.right,bottom:r.bottom},
          clientWidth:e.clientWidth,scrollWidth:e.scrollWidth,clientHeight:e.clientHeight,scrollHeight:e.scrollHeight,
          textRanges,ancestors,
          css:{display:c.display,position:c.position,direction:c.direction,
            gridTemplateColumns:c.gridTemplateColumns,flexWrap:c.flexWrap,
            width:c.width,minWidth:c.minWidth,maxWidth:c.maxWidth,
            overflow:c.overflow,overflowX:c.overflowX,overflowY:c.overflowY,
            whiteSpace:c.whiteSpace,wordBreak:c.wordBreak,overflowWrap:c.overflowWrap,
            boxSizing:c.boxSizing,float:c.float,clear:c.clear,
            fontFamily:c.fontFamily,fontSize:c.fontSize,fontWeight:c.fontWeight,
            lineHeight:c.lineHeight,letterSpacing:c.letterSpacing,
            margin:c.margin,padding:c.padding,transform:c.transform,zIndex:c.zIndex,
            color:c.color,backgroundColor:c.backgroundColor},
        };
      });
    }
    return nodes;
  })(),
});
const styleProvenance=()=> {
  const names=['.pgr-structured-scanner__lead','.pgr-structured-scanner__privacy',
    '.pgr-structured-scanner__capture','.pgr-structured-scanner__focus',
    '.pgr-structured-scanner__status'];
  const targets=names.map(n=>document.querySelector(n));
  const matched=[];
  for(const sheet of document.styleSheets){
    let rules;try { rules=sheet.cssRules; } catch(e){ continue; }
    const walk=(items,depth=0)=>{
      if(depth>3 || matched.length>300)return;
      for(const rule of items){
        if(rule.cssRules){walk(rule.cssRules,depth+1);continue;}
        const sel=rule.selectorText;
        if(!sel)continue;
        try {
          const on=targets.map((t,i)=>t&&t.matches(sel)?names[i]:null).filter(Boolean);
          if(on.length) matched.push({owner:sheet.href||'INLINE',selector:sel,
             matches:on,css:rule.style?.cssText?.slice(0,450)||''});
        }catch(e){}
      }
    };
    walk(rules);
  }
  return matched;
};
const overlap=(a,b)=> {
  if(!a?.rect||!b?.rect)return 0;
  const x=Math.max(0,Math.min(a.rect.right,b.rect.right)-Math.max(a.rect.x,b.rect.x));
  const y=Math.max(0,Math.min(a.rect.bottom,b.rect.bottom)-Math.max(a.rect.y,b.rect.y));
  return Number((x*y).toFixed(2));
};
const collision=m=>{
  const n=m.nodes,pairs=[['lead','textarea'],['privacy','textarea'],
    ['lead','button'],['privacy','button'],['status','button'],
    ['lead','status'],['privacy','status']];
  const collisions=[];
  for(const [a,b] of pairs){
    const area=overlap(n[a]?.[0],n[b]?.[0]);
    if(area>2)collisions.push({a,b,area});
    for(const box of n[a]?.[0]?.textRanges||[]){
      const actual={rect:{x:box.x,y:box.y,right:box.x+box.w,bottom:box.y+box.h}};
      const textOverlap=overlap(actual,n[b]?.[0]);
      if(textOverlap>2)collisions.push({a:a+' text',b,area:textOverlap});
    }
  }
  return collisions;
};
const keyboard=async page=>{
  await page.evaluate(()=>document.activeElement?.blur?.());
  const results=[];
  for(let i=0;i<24;i++){
    await page.keyboard.press('Tab');
    results.push(await page.evaluate(()=>{
      const e=document.activeElement,s=e?getComputedStyle(e):null,r=e?.getBoundingClientRect();
      return e?{tag:e.tagName,name:e.getAttribute('name'),aria:e.getAttribute('aria-label'),
        text:(e.textContent||'').trim().slice(0,55),
        rect:r?{x:r.x,y:r.y,w:r.width,h:r.height}:null,
        focusVisible:e.matches(':focus-visible'),outline:s?.outline,boxShadow:s?.boxShadow}:null;
    }));
  }
  return results;
};
const browser=await chromium.launch({headless:true});
const report={schema:1,classification:'AUTHENTIC_WU008_SCANNER_READONLY_UI',
  exact_test_head:head,original_merged_main:'810ed1eabf956f7073ea7f568fc7d1c24a69b661',
  locale,wordpress:fixture.wordpress,gravityforms:fixture.gravityforms,
  installed_package:environment.persiangravity_source_commit,
  installed_source_tree:fixture.source_tree,versions:environment.versions,
  form:fixture,playwright:'1.55.0',chromium:browser.version(),cases:[],
  no_form_submission:true,no_registry_mutation_by_observer:true};
try{
  for(const {width,height} of browsers){
    const context=await browser.newContext({viewport:{width,height},deviceScaleFactor:1});
    const page=await context.newPage();const errors=[];
    page.on('pageerror',e=>errors.push(String(e)));
    let resp=await page.goto(environment.login_url,{waitUntil:'domcontentloaded'});
    check(resp?.ok(),'login unavailable');
    await page.locator('#user_login').fill('runtime_admin');
    await page.locator('#user_pass').fill(password);
    await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('#wp-submit').click()]);
    check(await page.locator('#wpadminbar').count()===1,'login not authenticated');
    const states=[
      ['list',profileUrl,'.pgr-profile-card'],
      ['create',urlSuffix('&action=new'),'.pgr-profile-form'],
      ['edit',urlSuffix('&action=edit&profile=wu008_ui_long_label'),'.pgr-profile-form'],
      ['built-in',urlSuffix('&action=edit&profile=sayad_v01'),'.pgr-output-summary'],
      ['builder',builderUrl,'.pgr-structured-scanner'],
    ];
    for(const [state,target,selector] of states){
      const item={state,width,height,locale,status:'NOT_VERIFIED',errors:[]};
      try{
        resp=await page.goto(target,{waitUntil:'domcontentloaded'});
        check(resp?.ok()&&!page.url().includes('wp-login.php'),'route not authenticated: '+state);
        check(await page.locator(selector).count()>0,'authentic product target absent: '+state);
        await page.evaluate(()=>document.fonts.ready);
        if(state==='builder'){
          const id=fixture.scanner_field_id;
          await page.locator('#field_'+id).waitFor({state:'attached'});
          await page.evaluate(id=>document.querySelector('#gfield_edit_'+id)?.click(),id);
          await page.locator('#advanced_tab_toggle').click();
          await page.locator('[data-pgr-scanner-mapping]').first().waitFor({state:'visible'});
          check(await page.locator('[data-pgr-scanner-mapping]').count()===7,'seven real mapping controls unavailable');
          const link=page.locator('link[href*="/assets/css/pgr-scanner-editor.css"]').first();
          check(await link.count()===1,'owned scanner editor stylesheet not served');
          const href=await link.getAttribute('href');
          const served=await page.request.get(href);
          check(served.ok(),'scanner css HTTP failure');
          const local=fs.readFileSync(path.join(root,'wp-content/plugins/persian-gravityforms/assets/css/pgr-scanner-editor.css'));
          const servedHash=hash(await served.body()),installedHash=hash(local);
          check(servedHash===installedHash,'served scanner CSS bytes != installed CSS bytes');
          item.plugin_css_proof={url:new URL(href).pathname,served_sha256:servedHash,installed_sha256:installedHash,identical:true};
          item.matched_css_rules=await page.evaluate(styleProvenance);
        }
        const m=await page.evaluate(snapshot);
        const actualDir=m.page.dir;
        check(actualDir===(locale==='fa_IR'?'rtl':'ltr'),'authentic WordPress locale direction mismatch');
        check(m.page.lang.toLowerCase().startsWith(locale==='fa_IR'?'fa':'en'),'authentic WordPress locale lang mismatch');
        item.before={...m,centerCollisions:state==='builder'?collision(m):[]};
        if(state==='list')check((await page.locator('.pgr-admin').innerText()).includes('LongLabel_12345'),'persisted long name missing');
        if(state==='edit')check((await page.locator('input[name="profile[label]"]').inputValue()).includes('LongLabel_12345'),'persisted edit fixture missing');
        if(state==='create'||state==='edit')item.native_validity={
          formValid:await page.locator('.pgr-profile-form').evaluate(el=>el.checkValidity()),
          requiredCount:await page.locator('.pgr-profile-form [required]').count()};
        const filename='scanner-ui-'+locale+'-'+state+'-'+width+'.png';
        await page.screenshot({path:path.join(out,filename),type:'png',fullPage:true});
        item.screenshot=filename;
        item.keyboard=await keyboard(page);
        if(state==='builder'){
          item.one_variable={control:'Set only mapping-row gridTemplateColumns to minmax(0, 1fr) in browser memory'};
          await page.evaluate(()=>{
            document.querySelectorAll('.pgr-scanner-editor-mapping-row')
              .forEach(e=>e.style.gridTemplateColumns='minmax(0, 1fr)');
          });
          const after=await page.evaluate(snapshot);
          item.one_variable.after={...after,centerCollisions:collision(after)};
          item.one_variable.before_left_overflow=m.nodes.mappingList[0]?.scrollWidth>m.nodes.mappingList[0]?.clientWidth+1;
          item.one_variable.after_left_overflow=after.nodes.mappingList[0]?.scrollWidth>after.nodes.mappingList[0]?.clientWidth+1;
          const centralKeys=['center','lead','privacy','textarea','actions','button','status'];
          item.one_variable.center_rect_delta=centralKeys.map(k=>{
            const a=m.nodes[k]?.[0]?.rect,b=after.nodes[k]?.[0]?.rect;
            return {key:k,dx:a&&b?Number((b.x-a.x).toFixed(2)):null,
              dy:a&&b?Number((b.y-a.y).toFixed(2)):null,
              dw:a&&b?Number((b.w-a.w).toFixed(2)):null,
              dh:a&&b?Number((b.h-a.h).toFixed(2)):null};
          });
          const alt='scanner-ui-'+locale+'-builder-grid-one-column-'+width+'.png';
          await page.screenshot({path:path.join(out,alt),type:'png',fullPage:true});
          item.one_variable.screenshot=alt;
        }
        check(errors.length===0,'uncaught browser JS errors: '+errors.join(' / '));
        item.status='CAPTURED_UNREVIEWED';
      }catch(e){item.status='FAILED';item.error=String(e?.stack||e);}
      item.errors=[...errors]; report.cases.push(item);
      check(item.status==='CAPTURED_UNREVIEWED',item.error||'case incomplete');
    }
    await context.close();
  }
}finally{
  await browser.close();
  fs.writeFileSync(path.join(out,'manifest-'+locale+'.json'),JSON.stringify(report,null,2)+'\n');
}
check(report.cases.length===15,'incomplete locale matrix');
const all=['fa_IR','en_US'].every(l=>fs.existsSync(path.join(out,'manifest-'+l+'.json')));
if(all){
  const manifests=['fa_IR','en_US'].map(l=>JSON.parse(fs.readFileSync(path.join(out,'manifest-'+l+'.json'),'utf8')));
  const lines=['# Structured Scanner — authentic read-only UI evidence','',
    'WordPress 6.8.3 + Gravity Forms 3.1.1.1 + exact tested plugin package; no mock Form Builder.', '',
    'Production source comparison requires separate isolated pre-PR106 setup; this branch tests current product bytes only.','',
    '| Locale | State | Width | Original screenshot | Grid one-column control |','|---|---|---:|---|---|'];
  for(const x of manifests)for(const c of x.cases){
    lines.push('| '+x.locale+' | '+c.state+' | '+c.width+' | ['+c.screenshot+']('+c.screenshot+') | '+
       (c.one_variable?'['+c.one_variable.screenshot+']('+c.one_variable.screenshot+')':'—')+' |');
  }
  lines.push('','## Measurements',
    'See [fa_IR manifest](manifest-fa_IR.json), [en_US manifest](manifest-en_US.json), and [fixture identity](fixture.json).',
    'All computed styles, DOM boxes, font readiness, scroll widths, text rects, keyboard focus and CSSOM rule matches are retained.','','',
    'No Registry writes or form submissions were made by the browser.');
  fs.writeFileSync(path.join(out,'CONTACT_SHEET.md'),lines.join('\n'));
}
console.log('SCANNER_UI_READONLY_CAPTURED '+locale+' '+report.cases.length);
