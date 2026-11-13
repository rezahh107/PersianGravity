import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const profile=process.argv[2], locale=process.argv[3];
if(!['BASELINE','WITH_VAZIR'].includes(profile)||!['fa_IR','en_US'].includes(locale))
  throw Error('Unknown authentic G013 expanded profile');
for(const key of ['WU008_ARTIFACT_DIR','WU008_MANIFEST_PATH','WU008_ADMIN_PASSWORD','WU008_PGR_SHA'])
  if(!process.env[key])throw Error('Missing exact WU008 host '+key);
const manifest=JSON.parse(fs.readFileSync(process.env.WU008_MANIFEST_PATH,'utf8'));
const fixture=JSON.parse(fs.readFileSync(path.join(process.env.WU008_ARTIFACT_DIR,'g013-extended-fixture.json'),'utf8'));
const expectedHead=process.env.WU008_PGR_SHA;
if(fixture.schema!=='g013-real-extended-fixture-v2'||fixture.head!==expectedHead||
  manifest.persiangravity_source_commit!==expectedHead)
  throw Error('Expanded fixture/head mismatch');
const origin=new URL(manifest.admin_url).origin;
for(const url of [fixture.editor_url,fixture.perks_admin_url,manifest.login_url])
  if(new URL(url).origin!==origin)throw Error('Expanded browser disallowed origin');
const dir=path.join(process.env.WU008_ARTIFACT_DIR,'g013-acceptance-private');
fs.mkdirSync(dir,{recursive:true});
const key=profile.toLowerCase().replace('_','-')+'-'+locale;
const browser=await chromium.launch({headless:true});
const report={schema:'g013-extended-browser-v2',head:expectedHead,profile,locale,
  environment:{wordpress:'6.8.3',php:manifest.php_version,gravity:manifest.versions,
    chromium:browser.version(),playwright:'1.55.0',snapshot:process.env.WU008_G013_DB_SNAPSHOT_SHA},
  cases:[],status:'INCOMPLETE'};
const widths=[1280,390];
function guard(ok,msg){if(!ok)throw Error(msg);}
async function login(page){
  const r=await page.goto(manifest.login_url,{waitUntil:'domcontentloaded'});
  guard(r?.ok(),'G013 expanded login HTTP failed');
  await page.locator('#user_login').fill('runtime_admin');
  await page.locator('#user_pass').fill(process.env.WU008_ADMIN_PASSWORD);
  await Promise.all([page.waitForURL(u=>!u.pathname.includes('wp-login.php'),
    {waitUntil:'domcontentloaded',timeout:20000}),page.locator('#wp-submit').click()]);
}
async function goto(page,url){
  const r=await page.goto(url,{waitUntil:'domcontentloaded'});
  guard(r?.ok(),'G013 expanded authenticated HTTP failed');
}
async function measure(page,selector){
  return await page.locator(selector).first().evaluate(el=>{
    const r=el.getBoundingClientRect(),s=getComputedStyle(el);
    return {left:Math.round(r.left),right:Math.round(r.right),
      width:Math.round(r.width),height:Math.round(r.height),
      viewport:innerWidth,overflowX:s.overflowX,
      scrollWidth:el.scrollWidth,clientWidth:el.clientWidth};
  });
}
async function verifyFont(page){
  return page.evaluate(async()=>{
    await document.fonts.ready;
    const family=getComputedStyle(document.body).fontFamily;
    const faces=[...document.fonts].filter(x=>/vazirmatn/i.test(x.family)&&x.status==='loaded').length;
    return {hasVazir:/vazirmatn/i.test(family),loadedFace:faces>0,
      documentWidth:document.documentElement.scrollWidth};
  });
}
async function runCase(name,width,body){
  const context=await browser.newContext({viewport:{width,height:width===1280?900:844},
    deviceScaleFactor:1});
  const page=await context.newPage();
  const errors=[];
  page.on('pageerror',err=>{
    const name=['TypeError','ReferenceError','SyntaxError','RangeError'].includes(err?.name)?err.name:'OtherError';
    const msg=String(err?.message||'');
    const subtype=/Cannot read properties of undefined/i.test(msg)?'UNDEFINED_READ':
      /Cannot read properties of null/i.test(msg)?'NULL_READ':
      /is not a function/i.test(msg)?'NOT_FUNCTION':'OTHER';
    const firstFrame=String(err?.stack||'').split('\n').find(x=>/https?:\/\/|blob:/i.test(x))||'';
    const owner=/\/wp-content\/plugins\/gravityview/i.test(firstFrame)?'GRAVITYVIEW':
      /\/wp-content\/plugins\/persiangravity/i.test(firstFrame)?'PERSIANGRAVITY':
      /\/wp-includes\//i.test(firstFrame)?'WP_CORE':
      /\/wp-admin\//i.test(firstFrame)?'WP_ADMIN':
      /\/wp-content\/plugins\//i.test(firstFrame)?'OTHER_PLUGIN':'UNKNOWN';
    errors.push({name,subtype,owner}); // Never emit raw JS exception text or URLs.
  });
  const entry={name,width,status:'INCONCLUSIVE',font:null,geometry:[],observed:[],screenshot:null};
  try{
    await login(page);
    await body(page,entry);
    guard(!errors.length,'Real browser page exception');
    entry.font=await verifyFont(page);
    if(profile==='BASELINE')guard(!entry.font.loadedFace&&!entry.font.hasVazir,'Baseline font contamination');
    if(profile==='WITH_VAZIR'&&locale==='fa_IR')
      guard(entry.font.loadedFace&&entry.font.hasVazir,'Authentic Persian Vazirmatn not computed and loaded');
    entry.status='PASS';
    const image=key+'-'+name+'-'+width+'.jpg';
    await page.screenshot({path:path.join(dir,image),type:'jpeg',quality:75,fullPage:true});
    entry.screenshot=image;
  }catch(err){
    entry.status='NOT_PROVEN';
    entry.failure_class=err?.name==='TimeoutError'?'WAIT_OR_UNAVAILABLE':'UNSATISFIED_REAL_BEHAVIOR';
    // The detailed error is private, never included in the public G013 artifact.
    const details=key+'-'+name+'-'+width+'.private-error.txt';
    fs.writeFileSync(path.join(dir,details),String(err?.stack||err).slice(0,1500)+'\n');
  }finally{
    if(entry.selectionDiagnostic){
      entry.selectionDiagnostic.pageErrors=errors.length;
      entry.selectionDiagnostic.pageErrorKind=errors.length===0?'NONE':errors[0].name;
      entry.selectionDiagnostic.pageErrorSubtype=errors.length===0?'NONE':errors[0].subtype;
      entry.selectionDiagnostic.pageErrorOwner=errors.length===0?'NONE':errors[0].owner;
    }
    if(name==='gv-block-editor'){
      const diag=entry.selectionDiagnostic||{};
      const observed=diag.localizedAfterWait||diag.localizedAfterOpen||
        diag.localizedAtClick||diag.localizedBeforeOpen||{};
      const admissible=new Set(['PRESENT','ABSENT','UNKNOWN','INVALID_SHAPE','UNAVAILABLE','NOT_REACHED']);
      const state=value=>admissible.has(value)?value:'NOT_REACHED';
      const count=value=>Number.isInteger(value)&&value>=0&&value<=100000?value:'UNKNOWN';
      // Public CI log: closed enums and bounded counts only. Never log View IDs,
      // labels, localized objects, raw errors, secrets, or private URLs.
      console.log('G013_LOCALIZED_OPTION '+[
        'profile='+key,'width='+width,
        'source='+state(observed.sourceState),
        'shape='+state(observed.shapeState),
        'views='+count(observed.viewCount),
        'malformed='+count(observed.malformedCount),
        'target='+state(observed.targetState),
        'menu='+state(observed.menuState),
        'options='+count(observed.optionCount),
        'visible='+count(observed.visibleOptionCount),
        'ui_target='+state(observed.optionTargetState),
        'enabled='+state(observed.optionEnabledState),
        'page_errors='+count(errors.length)
      ].join(' '));
    }
    report.cases.push(entry);
    await context.close();
  }
}
function isOnscreen(g){
  return g.width>0&&g.height>0&&g.left>=-1&&g.right<=g.viewport+1;
}
async function perks(page,entry){
  await goto(page,fixture.perks_admin_url);
  const manage=page.locator('h2.nav-tab-wrapper a[href="#manage"]').first();
  const install=page.locator('h2.nav-tab-wrapper a[href="#install"]').first();
  await manage.waitFor({state:'visible',timeout:15000});
  await install.waitFor({state:'visible',timeout:15000});
  const mg=await measure(page,'h2.nav-tab-wrapper a[href="#manage"]');
  const ig=await measure(page,'h2.nav-tab-wrapper a[href="#install"]');
  guard(isOnscreen(mg)&&isOnscreen(ig),'Real Perks admin nav is clipped');
  await install.focus();
  guard(await install.evaluate(el=>el===document.activeElement),'Perks tab not keyboard focusable');
  await page.keyboard.press('Enter');
  await page.locator('div#install.tab-container').waitFor({state:'visible',timeout:12000});
  guard(await install.evaluate(el=>el.classList.contains('nav-tab-active')),'Perks install tab did not activate');
  await manage.focus();
  await page.keyboard.press('Enter');
  await page.locator('div#manage.tab-container').waitFor({state:'visible',timeout:12000});
  guard(await manage.evaluate(el=>el.classList.contains('nav-tab-active')),'Perks manage tab did not restore');
  entry.geometry.push({id:'manage_tab',...mg},{id:'install_tab',...ig});
  entry.observed.push('REAL_PERKS_STANDALONE_SETTINGS','KEYBOARD_TAB_TOGGLE','MANAGE_RESTORED');
}
// Classify known Playwright actionability reasons. Never expose the raw
// message: Playwright errors can include selectors and private DOM excerpts.
function playwrightActionCategory(err){
  if(err?.name!=='TimeoutError')return 'OTHER';
  const s=String(err?.message||'');
  return /intercepts pointer events/i.test(s)?'INTERCEPTED':
    /element is not stable/i.test(s)?'UNSTABLE':
    /element is not visible|not visible/i.test(s)?'NOT_VISIBLE':
    /outside of the viewport/i.test(s)?'OFFSCREEN':
    /element is detached|detached from the DOM/i.test(s)?'DETACHED':
    /scrolling into view/i.test(s)?'SCROLL':'UNCLASSIFIED';
}
// Only bounded categories/counts enter diagnostics. The actual clientId is
// used for same-runtime identity comparisons, never written to diagnostics.
async function selectedCategory(page,clientId){
  return page.evaluate(id=>{
    try{
      const selected=wp.data.select('core/block-editor').getSelectedBlockClientId();
      return selected===id?'TARGET':selected?'OTHER':'NONE';
    }catch{return 'UNAVAILABLE';}
  },clientId);
}
async function editor(page,entry){
  entry.stage='EDITOR_NAVIGATE';
  await goto(page,fixture.editor_url);
  entry.stage='EDITOR_BLOCK_WAIT';
  await page.waitForFunction(()=>{
    try{return window.wp?.data?.select('core/block-editor')?.getBlocks()
      ?.some(block=>block.name==='gk-gravityview-blocks/view');}catch{return false;}
  },null,{timeout:25000,polling:400});
  const clientId=await page.evaluate(()=>{
    const block=wp.data.select('core/block-editor').getBlocks()
      .find(b=>b.name==='gk-gravityview-blocks/view');
    return typeof block?.clientId==='string'?block.clientId:null;
  });
  guard(clientId&&/^[a-z0-9-]{8,80}$/i.test(clientId),'Actual GravityView block identity missing');
  entry.stage='EDITOR_BLOCK_FOUND';
  entry.observed.push('REAL_GV_GUTENBERG_BLOCK');
  const blockState=await page.evaluate(()=>{
    const editor=wp.data.select('core/editor');
    const all=wp.data.select('core/block-editor').getBlocks();
    let ready='UNKNOWN',postType='UNKNOWN';
    try{ready=typeof editor?.isEditorReady==='function'
      ?(editor.isEditorReady()===true?'YES':'NO'):'UNKNOWN';}catch{}
    try{const type=editor?.getCurrentPostType?.();postType=type==='page'?'PAGE':type?'OTHER':'UNKNOWN';}catch{}
    return {rootCount:all.length,targetCount:all.filter(b=>b.name==='gk-gravityview-blocks/view').length,
      ready,postType};
  });
  const selectionDiagnostic={schema:'g013-gutenberg-selection-v1',...blockState,
    clientIdValidated:true,selectionBefore:await selectedCategory(page,clientId),
    selectionAfterCanvas:'UNAVAILABLE',selectionAfterList:'UNAVAILABLE',
    candidateFrames:0,canvasMatches:0,canvasVisible:0,canvasPointerHit:0,
    canvasClick:'NOT_ATTEMPTED',listVisible:false,
    listItems:-1,listRoleTreeitem:-1,listRoleButton:-1,listRoleGroup:-1,
    listClassItems:-1,listExactTargetMatches:-1,listTargetTreeitems:-1,
    targetVisible:'UNKNOWN',targetBox:'UNKNOWN',targetHit:'UNKNOWN',
    targetHidden:'UNKNOWN',targetInteractive:'UNKNOWN',canvasLayouts:-1,
    allFrames:-1,pageErrors:0,pageErrorKind:'NONE',listClick:'NOT_ATTEMPTED',
    frameKind:'NONE',frameLayouts:0,frameTargets:0,frameVisibleTargets:0,
    frameEvaluation:'NONE',targetHiddenReason:'UNKNOWN',frameParentHit:'UNKNOWN',
    frameParentBox:'UNKNOWN',canvasAction:'UNKNOWN',listAction:'UNKNOWN',
    dialogDismiss:'NOT_ATTEMPTED',dialogAfter:'UNKNOWN',
    selectScope:'UNKNOWN',selectInspector:'UNKNOWN',
    selectExpandedFocus:'UNKNOWN',selectExpandedClick:'UNKNOWN',selectExpandedTimeout:'UNKNOWN',
    selectMenusAtFocusPage:-1,selectMenusAtFocusFrame:-1,
    selectVisibleAtFocusPage:-1,selectVisibleAtFocusFrame:-1,
    selectMenusAfterPage:-1,selectMenusAfterFrame:-1,
    selectVisibleAfterPage:-1,selectVisibleAfterFrame:-1,
    selectVisibleTimeoutPage:-1,selectVisibleTimeoutFrame:-1,
    selectTargetOptionsPage:-1,selectTargetOptionsFrame:-1,
    selectFirstVisible:'UNKNOWN',
    pageErrorSubtype:'NONE',pageErrorOwner:'NONE',frameBlocker:'UNKNOWN',framePointer:'UNKNOWN'};
  entry.selectionDiagnostic=selectionDiagnostic;
  // The editor can render its canvas in an isolated iframe. Do not assume
  // the first about:blank frame is the actual Gutenberg canvas.
  const frames=page.frames().filter(f=>f!==page.mainFrame()&&
    (f.url()==='about:blank'||f.url().startsWith(origin+'/')||
      // Authenticated WU008 proved the sole real editor canvas is owned by
      // this exact-origin blob: frame; foreign blob URLs are NOT admitted.
      f.url().startsWith('blob:'+origin+'/')));
  const scopes=[page,...frames];
  selectionDiagnostic.candidateFrames=frames.length;
  selectionDiagnostic.allFrames=page.frames().length-1;
  selectionDiagnostic.canvasLayouts=await page.locator('.block-editor-block-list__layout').count();
  // The original admitted scopes exclude blob: frames. Diagnose their real
  // provenance before deciding whether the owning canvas is being missed.
  // No frame from an unapproved origin is read or used for interaction.
  for(const frame of page.frames()){
    if(frame===page.mainFrame())continue;
    const u=frame.url();
    const kind=u==='about:blank'?'ABOUT':u.startsWith('blob:'+origin+'/')?'OWN_BLOB':
      u.startsWith(origin+'/')?'OWN_HTTP':u.startsWith('blob:')?'FOREIGN_BLOB':'FOREIGN';
    selectionDiagnostic.frameKind=kind;
    if(!['ABOUT','OWN_BLOB','OWN_HTTP'].includes(kind))continue;
    try{
      const state=await frame.evaluate(id=>({
        layoutCount:document.querySelectorAll('.block-editor-block-list__layout').length,
        targets:[...document.querySelectorAll('[data-block]')].filter(el=>el.getAttribute('data-block')===id).length,
        visible:[...document.querySelectorAll('[data-block]')].filter(el=>el.getAttribute('data-block')===id&&
          el.getBoundingClientRect().width>0&&el.getBoundingClientRect().height>0&&
          getComputedStyle(el).display!=='none').length
      }),clientId);
      selectionDiagnostic.frameLayouts+=state.layoutCount;
      selectionDiagnostic.frameTargets+=state.targets;
      selectionDiagnostic.frameVisibleTargets+=state.visible;
      selectionDiagnostic.frameEvaluation='OK';
    }catch{selectionDiagnostic.frameEvaluation='ERROR';}
  }
  const selectedNow=()=>page.evaluate(id=>{
    try{return wp.data.select('core/block-editor').getSelectedBlockClientId()===id;}
    catch{return false;}
  },clientId);
  entry.stage='EDITOR_CANVAS_LOCATE';
  let realBlock=null;
  for(const scope of scopes){
    const candidates=scope.locator('[data-block="'+clientId+'"]');
    const matches=await candidates.count();
    selectionDiagnostic.canvasMatches+=matches;
    for(let i=0;i<matches;i++){
      const candidate=candidates.nth(i);
      if(!await candidate.isVisible())continue;
      selectionDiagnostic.canvasVisible++;
      // Hit test stays inside the matching canvas document, including iframes.
      try{
        const hit=await candidate.evaluate(el=>{
          const r=el.getBoundingClientRect();
          if(r.width<=0||r.height<=0)return false;
          const x=Math.max(0,Math.min(innerWidth-1,r.left+r.width/2));
          const y=Math.max(0,Math.min(innerHeight-1,r.top+r.height/2));
          const top=document.elementFromPoint(x,y);
          return !!top&&(top===el||el.contains(top));
        });
        if(hit)selectionDiagnostic.canvasPointerHit++;
      }catch{/* Diagnostic-only; no inferred hit. */}
      if(!realBlock){
        realBlock=candidate;
        // An inner-frame hit alone cannot prove that the parent document
        // permits a pointer to reach its iframe. Diagnose the actual parent.
        if(scope!==page){
          try{
            const parent=await scope.evaluate(id=>{
              const iframe=window.frameElement;
              if(!iframe)return {hit:'UNKNOWN',box:'UNKNOWN',blocker:'UNKNOWN',pointer:'UNKNOWN'};
              const child=document.querySelector('[data-block="'+id+'"]');
              if(!child)return {hit:'UNKNOWN',box:'UNKNOWN',blocker:'UNKNOWN',pointer:'UNKNOWN'};
              const a=iframe.getBoundingClientRect(),b=child.getBoundingClientRect();
              const x=a.left+b.left+b.width/2,y=a.top+b.top+b.height/2;
              const top=iframe.ownerDocument.elementFromPoint(x,y);
              const hit=!top?'NONE':top===iframe||iframe.contains(top)?'FRAME':'OTHER';
              const host=iframe.ownerDocument.documentElement;
              const vw=host.clientWidth,vh=host.clientHeight;
              const box=a.width<=0||a.height<=0?'ZERO':
                a.right<=0||a.bottom<=0||a.left>=vw||a.top>=vh?'OFFSCREEN':
                a.left<0||a.top<0||a.right>vw||a.bottom>vh?'PARTIAL':'IN_VIEW';
              const blocker=!top?'NONE':top===iframe?'IFRAME':
                top.closest('[role="dialog"],[role="alertdialog"],.components-modal__frame')?'DIALOG':
                top.closest('.components-popover')?'POPOVER':
                top.closest('.components-notice')?'NOTICE':
                top.closest('.interface-interface-skeleton__header,.editor-header')?'HEADER':
                top.closest('.interface-interface-skeleton__sidebar')?'SIDEBAR':
                top.closest('#wpadminbar')?'ADMINBAR':
                top.closest('.edit-post-visual-editor,.block-editor-block-list__layout')?'EDITOR':'OTHER';
              const pointer=getComputedStyle(iframe).pointerEvents==='none'?'BLOCKED':'ENABLED';
              return {hit,box,blocker,pointer};
            },clientId);
            selectionDiagnostic.frameParentHit=parent.hit;
            selectionDiagnostic.frameParentBox=parent.box;
            selectionDiagnostic.frameBlocker=parent.blocker;
            selectionDiagnostic.framePointer=parent.pointer;
          }catch{/* Bounded unknown if frame introspection is unavailable. */}
        }
      }
    }
  }
  if(realBlock){
    // Real WU008 demonstrated that a Dialog, not a missing block, intercepts
    // the pointer in all four profiles. Try only a native Escape key; do not
    // force-click, hide the dialog, or programmatically select the block.
    if(selectionDiagnostic.frameBlocker==='DIALOG'){
      entry.stage='EDITOR_DIALOG_DISMISS';
      selectionDiagnostic.dialogDismiss='ESC_SENT';
      await page.keyboard.press('Escape');
      const after=await realBlock.evaluate(el=>new Promise(resolve=>{
        const started=Date.now();
        const watch=()=>{
          const iframe=el.ownerDocument.defaultView?.frameElement;
          if(!iframe)return resolve('UNKNOWN');
          const a=iframe.getBoundingClientRect(),b=el.getBoundingClientRect();
          const top=iframe.ownerDocument.elementFromPoint(a.left+b.left+b.width/2,a.top+b.top+b.height/2);
          const state=top?.closest('[role="dialog"],[role="alertdialog"],.components-modal__frame')
            ?'DIALOG':top===iframe||iframe.contains(top)?'FRAME':top?'OTHER':'NONE';
          if(state!=='DIALOG'||Date.now()-started>=3500)return resolve(state);
          requestAnimationFrame(watch);
        };
        watch();
      }));
      selectionDiagnostic.dialogAfter=after;
      selectionDiagnostic.dialogDismiss=after==='DIALOG'?'BLOCKED':'CLEARED';
      guard(after!=='DIALOG'&&after!=='UNKNOWN','Real Gutenberg modal not dismissed by Escape');
    }
    entry.stage='EDITOR_CANVAS_CLICK';
    // An inaccessible canvas element is not a success; try the native List
    // View route below and require its own real selection if click fails.
    try{await realBlock.click({timeout:5000});selectionDiagnostic.canvasClick='COMPLETED';}
    catch(err){
      selectionDiagnostic.canvasClick=err?.name==='TimeoutError'?'TIMEOUT':'ERROR';
      selectionDiagnostic.canvasAction=playwrightActionCategory(err);
    }
  }
  selectionDiagnostic.selectionAfterCanvas=await selectedCategory(page,clientId);
  if(!(await selectedNow())){
    // This fixture contains exactly one GravityView block. Use WordPress's
    // built-in List View keyboard shortcut and click its real tree item.
    entry.stage='EDITOR_LIST_OPEN';
    await page.keyboard.press('Shift+Alt+O');
    const tree=page.locator('.block-editor-list-view-tree').first();
    await tree.waitFor({state:'visible',timeout:10000});
    selectionDiagnostic.listVisible=true;
    // The real WP 6.8.3 List View contains an identity-bearing item but
    // no role=treeitem. Count roles for diagnosis, not selection authority.
    // Never print DOM, attributes, labels, post contents or client IDs.
    const listState=await tree.evaluate((root,id)=>{
      const count=selector=>root.querySelectorAll(selector).length;
      const matches=[...root.querySelectorAll('[data-block],[data-block-client-id]')]
        .filter(el=>el.getAttribute('data-block')===id||el.getAttribute('data-block-client-id')===id);
      return {listItems:count('[role="treeitem"]'),
        listRoleTreeitem:count('[role="treeitem"]'),
        listRoleButton:count('[role="button"]'),listRoleGroup:count('[role="group"]'),
        listClassItems:count('.block-editor-list-view-tree__item'),
        listExactTargetMatches:matches.length,
        listTargetTreeitems:new Set(matches.map(el=>el.closest('[role="treeitem"]')).filter(Boolean)).size};
    },clientId);
    Object.assign(selectionDiagnostic,listState);
    entry.stage='EDITOR_LIST_ITEM';
    // Source of truth is the observed exact clientId, not a guessed role or
    // position in a tree that may contain other UI elements.
    const exactMatches=tree.locator('[data-block="'+clientId+'"],[data-block-client-id="'+clientId+'"]');
    guard(await exactMatches.count()===1,'Gutenberg List View lacks a unique identity-matched block item');
    // Safe, observed geometry/accessibility categories before the genuine
    // pointer click. No DOM, CSS strings, URLs or clientIds enter reports.
    const target=exactMatches.first();
    const probe=await target.evaluate(el=>{
      const r=el.getBoundingClientRect(),s=getComputedStyle(el);
      let hidden=false,hiddenReason='NONE';
      for(let node=el;node;node=node.parentElement){
        const c=getComputedStyle(node);
        if(node.hidden){hidden=true;hiddenReason='HTML';break;}
        if(node.getAttribute('aria-hidden')==='true'){hidden=true;hiddenReason='ARIA';break;}
        if(c.display==='none'||c.visibility==='hidden'||c.visibility==='collapse'){
          hidden=true;hiddenReason='CSS';break;
        }
      }
      const box=r.width<=0||r.height<=0?'ZERO':
        (r.right<=0||r.bottom<=0||r.left>=innerWidth||r.top>=innerHeight)?'OFFSCREEN':
        (r.left<0||r.top<0||r.right>innerWidth||r.bottom>innerHeight)?'PARTIAL':'IN_VIEW';
      const x=r.left+r.width/2,y=r.top+r.height/2;
      const top=document.elementFromPoint(x,y);
      const hit=!top?'NONE':top===el?'SELF':el.contains(top)?'CHILD':'OTHER';
      const interactive='button,a[href],[role="button"],[role="treeitem"]';
      const interact=el.matches(interactive)?'SELF':el.closest(interactive)?'PARENT':
        el.querySelector(interactive)?'CHILD':'NONE';
      return {targetBox:box,targetHit:hit,targetHidden:hidden?'YES':'NO',
        targetHiddenReason:hiddenReason,targetInteractive:interact};
    });
    Object.assign(selectionDiagnostic,probe);
    selectionDiagnostic.targetVisible=await target.isVisible()?'YES':'NO';
    entry.stage='EDITOR_LIST_CLICK';
    try{
      await exactMatches.first().click({timeout:8000});
      selectionDiagnostic.listClick='COMPLETED';
    }catch(err){
      selectionDiagnostic.listClick=err?.name==='TimeoutError'?'TIMEOUT':'ERROR';
      selectionDiagnostic.listAction=playwrightActionCategory(err);
      throw err;
    }finally{
      selectionDiagnostic.selectionAfterList=await selectedCategory(page,clientId);
    }
  }
  if(selectionDiagnostic.selectionAfterList==='UNAVAILABLE')
    selectionDiagnostic.selectionAfterList=await selectedCategory(page,clientId);
  await page.waitForFunction(id=>{
    try{return wp.data.select('core/block-editor').getSelectedBlockClientId()===id;}
    catch{return false;}
  },clientId,{timeout:12000,polling:250});
  entry.stage='EDITOR_BLOCK_SELECTED';
  const sidebarOpen=await page.evaluate(()=>{
    const store=wp.data.select('core/edit-post');
    return typeof store?.isEditorSidebarOpened==='function'
      ?store.isEditorSidebarOpened():null;
  });
  if(sidebarOpen===false){
    entry.stage='EDITOR_SIDEBAR_OPEN';
    const toggle=page.locator(
      'button.editor-header__settings-toggle,button.edit-post-header__settings,.edit-post-header__settings button'
    ).first();
    guard(await toggle.isVisible(),'Real Gutenberg Settings sidebar toggle unavailable');
    await toggle.click();
    await page.waitForFunction(()=>{
      try{return wp.data.select('core/edit-post')?.isEditorSidebarOpened?.()===true;}
      catch{return false;}
    },null,{timeout:10000,polling:250});
  }
  entry.stage='EDITOR_SELECT_LOOKUP';
  let chosen=null;
  for(const scope of scopes){
    const candidate=scope.locator('[class$="-control"] input[role="combobox"]').first();
    try{
      await candidate.waitFor({state:'visible',timeout:5000});
      chosen={scope,input:candidate};break;
    }catch{/* Missing control remains a failing real-browser oracle below. */}
  }
  guard(chosen,'Actual detached GravityView React Select not rendered');
  // GravityView 3.3.4 populates this object through wp_localize_script at
  // editor load. The genuine ViewSelector passes these entries to react-select.
  // Observe the actual selector document; never inject or mutate options.
  const probeLocalized=async(menuId=null)=>{
    try{return await chosen.scope.evaluate(({targetId,menuId})=>{
      const unavailable={sourceState:'UNAVAILABLE',shapeState:'UNAVAILABLE',
        viewCount:-1,malformedCount:-1,targetState:'UNKNOWN',
        menuState:menuId?'UNAVAILABLE':'NOT_REACHED',optionCount:-1,
        visibleOptionCount:-1,optionTargetState:menuId?'UNKNOWN':'NOT_REACHED',
        optionEnabledState:menuId?'UNKNOWN':'NOT_REACHED'};
      if(!Object.prototype.hasOwnProperty.call(window,'gkGravityViewBlocks')||
          !window.gkGravityViewBlocks||typeof window.gkGravityViewBlocks!=='object')
        return unavailable;
      const source=window.gkGravityViewBlocks;
      if(!Object.prototype.hasOwnProperty.call(source,'views'))
        return {...unavailable,sourceState:'PRESENT'};
      if(!Array.isArray(source.views))
        return {...unavailable,sourceState:'PRESENT',shapeState:'INVALID_SHAPE'};
      const views=source.views;
      const malformed=views.filter(x=>!x||typeof x!=='object'||
        typeof x.value!=='string'||typeof x.label!=='string').length;
      const target=views.find(x=>x&&typeof x==='object'&&x.value===targetId);
      const result={...unavailable,sourceState:'PRESENT',
        shapeState:malformed?'INVALID_SHAPE':'PRESENT',
        viewCount:views.length,malformedCount:malformed,
        targetState:target?'PRESENT':malformed?'UNKNOWN':'ABSENT'};
      if(!menuId)return result;
      const menu=document.getElementById(menuId);
      if(!menu)return result;
      const options=[...menu.querySelectorAll('[role="option"]')];
      const visible=el=>{
        const rect=el.getBoundingClientRect(),style=getComputedStyle(el);
        return rect.width>0&&rect.height>0&&style.display!=='none'&&
          style.visibility!=='hidden';
      };
      const norm=value=>String(value||'').replace(/\s+/g,' ').trim();
      const active=options.filter(visible);
      const matches=target&&typeof target.label==='string'?
        active.filter(el=>norm(el.textContent).includes(norm(target.label))):[];
      return {...result,menuState:'PRESENT',optionCount:options.length,
        visibleOptionCount:active.length,
        optionTargetState:!target?'UNKNOWN':matches.length?'PRESENT':'ABSENT',
        optionEnabledState:!matches.length?'UNKNOWN':
          matches.some(el=>el.getAttribute('aria-disabled')!=='true'&&
            !el.hasAttribute('disabled'))?'PRESENT':'ABSENT'};
    },{targetId:String(fixture.view_id),menuId});}
    catch{return {sourceState:'UNAVAILABLE',shapeState:'UNKNOWN',
      viewCount:-1,malformedCount:-1,targetState:'UNKNOWN',
      menuState:menuId?'UNAVAILABLE':'NOT_REACHED',optionCount:-1,
      visibleOptionCount:-1,optionTargetState:'UNKNOWN',optionEnabledState:'UNKNOWN'};}
  };
  selectionDiagnostic.localizedBeforeOpen=await probeLocalized();
  entry.stage='EDITOR_SELECT_FOCUS';
  await chosen.input.focus();
  guard(await chosen.input.evaluate(el=>el===document.activeElement),'GravityView React Select focus failed');
  // Read only fixed booleans/counts. A portal may live outside the input's
  // DOM scope; do not assume the selected scope owns the menu.
  const expanded=async()=>{
    const v=await chosen.input.getAttribute('aria-expanded');
    return v==='true'?'TRUE':v==='false'?'FALSE':v===null?'MISSING':'OTHER';
  };
  const menus=async scope=>{
    try{return await scope.evaluate(()=>{
      const visible=el=>{const r=el.getBoundingClientRect(),s=getComputedStyle(el);
        return r.width>0&&r.height>0&&s.display!=='none'&&s.visibility!=='hidden';};
      const popup=[...document.querySelectorAll('[role="listbox"],[class$="-menu"]')];
      const target=[...document.querySelectorAll('[role="option"]')]
        .filter(el=>el.textContent?.includes('WU008 G008 GravityView System Dates'));
      return {all:popup.length,shown:popup.filter(visible).length,target:target.length};
    });}catch{return {all:-1,shown:-1,target:-1};}
  };
  const scopeCounts=async()=>{
    const main=await menus(page),inFrames={all:0,shown:0,target:0};
    for(const f of frames){const x=await menus(f);
      if(x.all<0){inFrames.all=-1;inFrames.shown=-1;inFrames.target=-1;break;}
      inFrames.all+=x.all;inFrames.shown+=x.shown;inFrames.target+=x.target;
    }
    return {main,frames:inFrames};
  };
  selectionDiagnostic.selectScope=chosen.scope===page?'MAIN':'FRAME';
  selectionDiagnostic.selectInspector=await chosen.input.evaluate(el=>
    el.closest('.interface-interface-skeleton__sidebar,.block-editor-block-inspector,.edit-post-sidebar')?'YES':'NO');
  selectionDiagnostic.selectExpandedFocus=await expanded();
  const focused=await scopeCounts();
  Object.assign(selectionDiagnostic,{selectMenusAtFocusPage:focused.main.all,
    selectMenusAtFocusFrame:focused.frames.all,selectVisibleAtFocusPage:focused.main.shown,
    selectVisibleAtFocusFrame:focused.frames.shown});
  entry.stage='EDITOR_DROPDOWN_WAIT';
  await chosen.input.click();
  selectionDiagnostic.selectExpandedClick=await expanded();
  guard(selectionDiagnostic.selectExpandedClick==='TRUE','Real React Select did not expand');
  selectionDiagnostic.localizedAtClick=await probeLocalized();
  const clicked=await scopeCounts();
  Object.assign(selectionDiagnostic,{selectMenusAfterPage:clicked.main.all,
    selectMenusAfterFrame:clicked.frames.all,selectVisibleAfterPage:clicked.main.shown,
    selectVisibleAfterFrame:clicked.frames.shown});
  // Authenticated WU008: three visible menus coexisted, but the old .first()
  // selected an unrelated hidden menu. Bind the popup to the real combobox
  // via its ARIA relationship, rejecting absent or ambiguous associations.
  const controls=await chosen.input.getAttribute('aria-controls');
  const owns=await chosen.input.getAttribute('aria-owns');
  const linkedId=controls||owns;
  selectionDiagnostic.selectLinkVia=controls?'CONTROLS':owns?'OWNS':'MISSING';
  const validLink=typeof linkedId==='string'&&/^[A-Za-z][A-Za-z0-9_-]{0,127}$/.test(linkedId);
  if(linkedId&&!validLink)selectionDiagnostic.selectLinkVia='INVALID';
  guard(validLink,'Real React Select lacks a usable ARIA-linked listbox identity');
  const linked=chosen.scope.locator('[id="'+linkedId+'"]');
  selectionDiagnostic.selectLinkCount=await linked.count();
  guard(selectionDiagnostic.selectLinkCount===1,'React Select ARIA listbox association not unique');
  const dropdown=linked.first();
  const role=await dropdown.getAttribute('role');
  selectionDiagnostic.selectLinkRole=role==='listbox'?'LISTBOX':role?'OTHER':'MISSING';
  selectionDiagnostic.localizedAfterOpen=await probeLocalized(linkedId);
  // The real combobox controls exactly one wrapper, but that wrapper has no
  // role attribute. Do not confuse its role with the descendant options.
  // Actual selectable options, visibility and persisted viewId remain required.
  try{await dropdown.waitFor({state:'visible',timeout:15000});}
  finally{
    selectionDiagnostic.selectExpandedTimeout=await expanded();
    const last=await scopeCounts();
    selectionDiagnostic.selectVisibleTimeoutPage=last.main.shown;
    selectionDiagnostic.selectVisibleTimeoutFrame=last.frames.shown;
    selectionDiagnostic.selectTargetOptionsPage=last.main.target;
    selectionDiagnostic.selectTargetOptionsFrame=last.frames.target;
    selectionDiagnostic.selectFirstVisible=await dropdown.count()&&await dropdown.isVisible()?'YES':'NO';
    selectionDiagnostic.selectLinkVisible=selectionDiagnostic.selectFirstVisible;
    selectionDiagnostic.selectLinkOptions=await dropdown.locator('[role="option"]').count();
    selectionDiagnostic.selectLinkTarget=await dropdown.locator('[role="option"]')
      .filter({hasText:'WU008 G008 GravityView System Dates'}).count();
  }
  const cg=await chosen.input.evaluate(el=>{const r=el.getBoundingClientRect();
    return {left:Math.round(r.left),right:Math.round(r.right),width:Math.round(r.width),
      height:Math.round(r.height),viewport:innerWidth,overflowX:getComputedStyle(el).overflowX,
      scrollWidth:el.scrollWidth,clientWidth:el.clientWidth};});
  const dg=await dropdown.evaluate(el=>{const r=el.getBoundingClientRect();
    return {left:Math.round(r.left),right:Math.round(r.right),width:Math.round(r.width),
      height:Math.round(r.height),viewport:innerWidth,overflowX:getComputedStyle(el).overflowX,
      scrollWidth:el.scrollWidth,clientWidth:el.clientWidth};});
  guard(isOnscreen(cg)&&isOnscreen(dg),'GV detached menu clipped outside owning viewport');
  entry.geometry.push({id:'react_select',...cg},{id:'react_portal',...dg});
  entry.stage='EDITOR_OPTION_WAIT';
  const matchingOptions=dropdown.locator('[role="option"]')
    .filter({hasText:'WU008 G008 GravityView System Dates'});
  try{await matchingOptions.first().waitFor({state:'visible',timeout:10000});}
  finally{selectionDiagnostic.localizedAfterWait=await probeLocalized(linkedId);}
  guard(await matchingOptions.count()===1,'The ARIA-linked React Select menu lacks a unique real target View option');
  const option=matchingOptions.first();
  entry.stage='EDITOR_VIEW_COMMIT';
  await option.click();
  await page.waitForFunction(id=>{
    const all=wp.data.select('core/block-editor').getBlocks();
    return all.some(b=>b.name==='gk-gravityview-blocks/view' && String(b.attributes.viewId)===String(id));
  },fixture.view_id,{timeout:10000});
  entry.observed.push('REAL_REACT_SELECT_PORTAL','GUTENBERG_VIEW_SELECTION');
  // GravityView 3.3.4 registers its real date controls under the
  // collapsed "Entries Settings" Gutenberg Inspector PanelBody.
  // Open actual panels through native UI rather than conjuring the picker.
  entry.stage='EDITOR_DATEPICKER_LOOKUP';
  const date=page.locator('.react-datepicker__input-container input:visible').first();
  if(!await date.count()){
    const toggles=page.locator('button.components-panel__body-toggle');
    for(let index=0;index<await toggles.count();index++){
      const toggle=toggles.nth(index);
      if(await toggle.isVisible() && await toggle.getAttribute('aria-expanded')==='false'){
        await toggle.click();
        if(await date.count())break;
      }
    }
  }
  if(!await date.isVisible())throw Error('Actual GravityView Entries Settings datepicker not reached');
  entry.stage='EDITOR_DATEPICKER_CLICK';
  await date.click();
  await page.locator('.react-datepicker:visible').first().waitFor({state:'visible',timeout:10000});
  entry.stage='EDITOR_DATEPICKER_CLOSE';
  await page.keyboard.press('Escape');
  entry.stage='EDITOR_COMPLETE';
  entry.observed.push('REAL_GV_DATEPICKER_POPUP');
}
try{
  for(const width of widths){
    await runCase('perks-admin',width,perks);
    await runCase('gv-block-editor',width,editor);
  }
  report.status=report.cases.every(c=>c.status==='PASS')?'PASS':'NOT_PROVEN';
}finally{
  fs.writeFileSync(path.join(dir,key+'.json'),JSON.stringify(report,null,2)+'\n');
  await browser.close();
}
if(report.status!=='PASS')throw Error('G013 expanded authentic browser coverage incomplete: '+profile+' '+locale);
console.log('G013_EXPANDED_BROWSER_PASS '+profile+' '+locale+' four authentic scenarios');
