import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';
import { classifyPortalProbe, inspectCropSurfaceInPage, classifyCropSurface, classifyNativeUpload, classifyCropSaveTimeout } from './g013-expanded-check.mjs';

const profile=process.argv[2], locale=process.argv[3];
if(!['BASELINE','WITH_VAZIR'].includes(profile)||!['fa_IR','en_US'].includes(locale))
  throw Error('Unknown authentic G013 expanded profile');
for(const key of ['WU008_ARTIFACT_DIR','WU008_MANIFEST_PATH','WU008_ADMIN_PASSWORD','WU008_PGR_SHA'])
  if(!process.env[key])throw Error('Missing exact WU008 host '+key);
const manifest=JSON.parse(fs.readFileSync(process.env.WU008_MANIFEST_PATH,'utf8'));
const fixture=JSON.parse(fs.readFileSync(path.join(process.env.WU008_ARTIFACT_DIR,'g013-extended-fixture.json'),'utf8'));
const expectedHead=process.env.WU008_PGR_SHA;
if(fixture.schema!=='g013-real-extended-fixture-v1'||fixture.head!==expectedHead||
  manifest.persiangravity_source_commit!==expectedHead)
  throw Error('Expanded fixture/head mismatch');
const origin=new URL(manifest.admin_url).origin;
for(const url of [fixture.crop_url,fixture.editor_url,fixture.perks_admin_url,manifest.login_url])
  if(new URL(url).origin!==origin)throw Error('Expanded browser disallowed origin');
const dir=path.join(process.env.WU008_ARTIFACT_DIR,'g013-acceptance-private');
fs.mkdirSync(dir,{recursive:true});
const key=profile.toLowerCase().replace('_','-')+'-'+locale;
const browser=await chromium.launch({headless:true});
const report={schema:'g013-extended-browser-v1',head:expectedHead,profile,locale,
  environment:{wordpress:'6.8.3',php:manifest.php_version,gravity:manifest.versions,
    chromium:browser.version(),playwright:'1.55.0',snapshot:process.env.WU008_G013_DB_SNAPSHOT_SHA},
  cases:[],status:'INCOMPLETE'};
const diagMode=process.env.G013_CROP_DIAG_MODE||'';
if(diagMode&&!['A','B'].includes(diagMode))throw Error('Invalid private Crop A/B diagnostic mode');
const widths=diagMode?[1280]:[1280,390];
function guard(ok,msg){if(!ok)throw Error(msg);}

// Test-only observation. Nothing returned contains image bytes, private paths,
// user records, file names, URLs, DOM HTML, or vendor objects.
function inspectDiagnosticCropStateInPage({formId,fieldId,readyOnly=false}){
  const instance=window['GPFUP_'+formId+'_'+fieldId];
  const store=instance?.$store;
  const queue=instance?.vm?[instance.vm]:[],seen=new Set();
  let editor=null;
  while(queue.length&&seen.size<64){
    const item=queue.shift();
    if(!item||seen.has(item))continue;
    seen.add(item);
    if(item.$options?.name==='GPFUPCropper'&&
      String(item.formId)===String(formId)&&String(item.fieldId)===String(fieldId))editor=item;
    for(const child of item.$children||[])queue.push(child);
  }
  const ref=editor?.$refs?.cropper;
  let canvasState=!ref?'REF_MISSING':'METHOD_MISSING';
  if(ref&&typeof ref.getResult==='function'){
    try{
      const canvas=ref.getResult()?.canvas;
      canvasState=!canvas?'CANVAS_MISSING':
        canvas.width>0&&canvas.height>0?'CANVAS_READY':'CANVAS_INVALID';
    }catch{canvasState='GET_RESULT_EXCEPTION';}
  }
  const imageLoaded=ref?.imageLoaded===true;
  const genuineReady=!!ref&&imageLoaded&&canvasState==='CANVAS_READY';
  if(readyOnly)return genuineReady;
  const all=store?.getters?.currentAddedFiles;
  const currentIndex=store?.getters?.currentImageIndex;
  const modal=document.querySelector('.cropper__lightbox');
  const style=modal&&getComputedStyle(modal);
  return {
    atMs:Math.round(performance.now()),
    instanceFound:!!instance,storeFound:!!store,
    editorOpen:store?.state?.editor?.open===true,
    currentFilePresent:!!store?.state?.editor?.currentFile,
    cropperRefPresent:!!ref,imageLoaded,canvasState,genuineReady,
    addedCount:Array.isArray(all)?Math.min(100,all.length):-1,
    currentIndex:Number.isInteger(currentIndex)?Math.max(-1,Math.min(100,currentIndex)):-1,
    modalStillVisible:!!modal?.isConnected&&modal?.getBoundingClientRect().width>0&&
      style?.display!=='none'&&style?.visibility!=='hidden'&&Number(style?.opacity)>0
  };
}

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
  page.on('pageerror',()=>errors.push('PAGE_ERROR'));
  const entry={name,width,status:'INCONCLUSIVE',font:null,geometry:[],observed:[],screenshot:null};
  if(diagMode)entry.diagnostic={mode:diagMode,scenario:'baseline-fa_IR/fup-crop/1280'};
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
async function crop(page,entry){
  const id=fixture.crop_form_id;
  const portalId='gpfup-cropper-portal-'+id+'-1';
  // Observe only the exact portal element's creation/removal. No DOM serialization.
  await page.addInitScript(targetId=>{
    const counters={added:0,removed:0},selector='#'+targetId;
    const has=node=>node.nodeType===1&&
      (node.id===targetId||!!node.querySelector?.(selector));
    new MutationObserver(records=>{
      for(const record of records){
        for(const node of record.addedNodes)if(has(node))counters.added=Math.min(100,counters.added+1);
        for(const node of record.removedNodes)if(has(node))counters.removed=Math.min(100,counters.removed+1);
      }
    }).observe(document,{subtree:true,childList:true});
    Object.defineProperty(window,'__g013PortalWitness',{value:counters,configurable:false});
  },portalId);
  await goto(page,fixture.crop_url);
  const root='#field_'+id+'_1';
  const text=page.locator('#input_'+id+'_2');
  await page.locator(root+' .gpfup__droparea').waitFor({state:'visible',timeout:15000});
  await text.waitFor({state:'visible'});
  entry.stage='CROP_PAGE_READY';
  const input=page.locator(root+' input[type=file]').first();
  const portal='#'+portalId;
  entry.stage='PORTAL_LOOKUP';
  // The original exact-one selector remains the acceptance oracle.
  const portalCount=await page.locator(portal).count();
  if(portalCount!==1){
    const witness=await page.evaluate(({formId,fieldId,portalId,portalCount})=>{
      const init=window['GPFUP_FORM_INIT_'+formId];
      const configs=Array.isArray(init)?init.filter(x=>
        String(x.formId)===String(formId)&&String(x.fieldId)===String(fieldId)):[];
      const instance=window['GPFUP_'+formId+'_'+fieldId];
      const queue=instance?.vm?[instance.vm]:[],seen=new Set();
      let mount=null;
      while(queue.length&&seen.size<64){
        const item=queue.shift();
        if(!item||seen.has(item))continue;
        seen.add(item);
        if(item.$options?.name==='MountingPortal'){mount=item;break;}
        for(const child of (item.$children||[]))queue.push(child);
      }
      const target=mount?.portalTarget?.$el,counts=window.__g013PortalWitness;
      return {
        selectorCount:Math.min(100,portalCount),
        added:Number.isInteger(counts?.added)?Math.min(100,counts.added):0,
        removed:Number.isInteger(counts?.removed)?Math.min(100,counts.removed):0,
        localizedCrop:configs.length===1&&configs[0].enableCrop===true,
        instanceFound:!!instance,
        instanceCrop:instance?.enableCrop===true,
        instanceCropRequired:instance?.cropRequired===true,
        vueMounted:!!instance?.vm,
        mountTarget:!!mount?.portalTarget,
        targetConnected:target?.isConnected===true,
        targetRetainsId:target?.id===portalId,
        otherPortalCount:Math.min(100,document.querySelectorAll('[id^="gpfup-cropper-portal-"]').length)
      };
    },{formId:id,fieldId:1,portalId,portalCount});
    entry.portal_probe=witness; // Private runner-only file, never public.
    entry.stage=classifyPortalProbe(witness);
  }
  // The old ID check is diagnostics only: target-slim replaces this node.
  // Functional acceptance below must validate the mounted field-owned surface.
  const image=await page.evaluate(()=>{
    const canvas=document.createElement('canvas');
    canvas.width=240;canvas.height=180;
    const context=canvas.getContext('2d');
    context.fillStyle='#f0f0f0';context.fillRect(0,0,240,180);
    context.fillStyle='#333';context.fillRect(30,30,180,120);
    return canvas.toDataURL('image/png').split(',')[1];
  });

  const source={name:'g013-disposable.png',mimeType:'image/png',buffer:Buffer.from(image,'base64')};
  entry.stage='IMAGE_PREPARED';
  const siblings='#field_'+id+'_3';
  const siblingInput=page.locator(siblings+' input[type=file]').first();
  entry.stage='SIBLING_INPUT_WAIT';
  await page.locator(siblings+' .gpfup__droparea').waitFor({state:'visible',timeout:15000});
  async function inspect(fieldId){
    return classifyCropSurface(await page.evaluate(inspectCropSurfaceInPage,
      {formId:id,fieldId}));
  }
  async function uploaded(fieldId){
    // GF 3.1.1.1 writes this field only after a server-acknowledged upload.
    // Report counts/booleans only, never stored paths, URLs or file names.
    return page.evaluate(({formId,fieldId})=>{
      const el=document.querySelector('#gform_uploaded_files_'+formId);
      if(!el)return {present:false,count:-1,confirmed:false};
      try{
        const data=JSON.parse(el.value||'{}');
        const list=data['input_'+fieldId];
        const valid=Array.isArray(list)?list:[];
        return {present:true,count:valid.length,confirmed:valid.some(f=>
          f&&typeof f.uploaded_filename==='string'&&f.uploaded_filename.length>0)};
      }catch{return {present:true,count:-1,confirmed:false};}
    },{formId:id,fieldId});
  }
  async function checkClosed(fieldId){
    await page.locator('.cropper__lightbox:visible').first().waitFor({state:'hidden',timeout:12000});
    guard((await page.locator('.cropper__lightbox:visible').count())===0,
      'Cropper still visible after dismissal');
    guard((await page.locator('#field_'+id+'_'+fieldId+' .gpfup__filename').count())===0,
      'Canceled required crop was uploaded');
    guard(classifyNativeUpload(await uploaded(fieldId),'EMPTY')==='PASS',
      'Canceled required crop still has authoritative GF uploaded-file record');
  }
  async function verifyOpen(fieldId){
    const which=fieldId===3?'SIBLING':'PRIMARY';
    const modal=page.locator('.cropper__lightbox:visible');
    entry.stage=which+'_MODAL_WAIT';
    await modal.first().waitFor({state:'visible',timeout:20000});
    entry.stage=which+'_MODAL_VISIBLE';
    // This is the real Vue-mounted modal; the original portal ID is never used.
    // Multiple active modals fail before any first()/nth() can disguise them.
    guard((await modal.count())===1,'More than one active Crop modal');
    entry.stage=which+'_CROPPER_WAIT';
    await modal.locator('.vue-advanced-cropper').waitFor({state:'visible',timeout:15000});
    entry.stage=which+'_ACTIONS_WAIT';
    const cancel=modal.locator('button.gpfup__cancel');
    const save=modal.locator('button.gpfup__crop');
    await cancel.waitFor({state:'visible'});
    await save.waitFor({state:'visible'});
    // Playwright considers opacity:0 "visible", while the vendor's real Vue
    // transition begins at opacity:0 and our functional oracle rejects it.
    // Wait for the *actual painted modal*, never skip the owning-field check.
    entry.stage=which+'_TRANSITION_WAIT';
    await page.waitForFunction(()=>{
      const painted=[...document.querySelectorAll('.cropper__lightbox')]
        .filter(el=>{
          const r=el.getBoundingClientRect(),s=getComputedStyle(el);
          return el.isConnected&&r.width>0&&r.height>0&&
            s.display!=='none'&&s.visibility!=='hidden'&&Number(s.opacity)>0;
        });
      return painted.length===1;
    },null,{timeout:5000,polling:50});
    entry.stage=which+'_OWNER_CHECK';
    const observation=await page.evaluate(inspectCropSurfaceInPage,{formId:id,fieldId});
    const outcome=classifyCropSurface(observation);
    if(outcome!=='READY'){
      // Only fixed classification codes, not raw DOM or runtime objects.
      const issue=outcome==='CROP_UNINITIALIZED'?'UNINITIALIZED':
        outcome==='CROP_NOT_REQUIRED'?'NOT_REQUIRED':
        outcome==='CROP_MOUNT_UNAVAILABLE'?
          (observation.mountCount!==1?'MOUNT_COUNT':'TARGET_DETACHED'):
        outcome==='CROP_MODAL_ABSENT'?'MODAL_ABSENT':
        outcome==='CROP_MODAL_DUPLICATE'?'MODAL_DUPLICATE':
        outcome==='CROP_WRONG_FIELD'?'FIELD_MISMATCH':
        outcome==='CROP_SURFACE_HIDDEN'?'CROPPER_HIDDEN':'ACTION_UNREACHABLE';
      entry.stage=which+'_OWNER_'+issue;
    }
    guard(outcome==='READY','Wrong/incomplete real Cropper: '+outcome);
    const cg=await measure(page,'.cropper__lightbox:visible button.gpfup__cancel');
    const sg=await measure(page,'.cropper__lightbox:visible button.gpfup__crop');
    guard(isOnscreen(cg)&&isOnscreen(sg),'Real Cropper actions clipped at viewport');
    entry.geometry.push({id:'crop_cancel',...cg},{id:'crop_save',...sg});
    return {modal,cancel,save};
  }
  // Independent second real upload field: a modal owned by field 3 must not
  // be accepted as field 1. No vendor code or live site state is modified.
  entry.stage='MODAL_OPENING';
  guard((await inspect(1))==='CROP_MODAL_ABSENT','Missing-modal negative control failed');
  entry.stage='SIBLING_SELECT_FILE';
  await siblingInput.setInputFiles(source);
  const sibling=await verifyOpen(3);
  guard((await inspect(1))==='CROP_WRONG_FIELD',
    'Cross-field modal was incorrectly attributed to field 1');
  // A temporary duplicate inside this disposable browser is a negative
  // control only. It must never be used as a successful vendor interaction.
  const duplicate=page.locator('[data-g013-duplicate-probe]');
  await page.evaluate(()=>{
    const el=document.createElement('div');
    el.className='cropper__lightbox';
    el.dataset.g013DuplicateProbe='';
    el.style.cssText='position:fixed;top:0;left:0;width:1px;height:1px';
    document.body.appendChild(el);
  });
  try{
    guard((await inspect(3))==='CROP_MODAL_DUPLICATE',
      'Duplicate-modal negative control was accepted');
  }finally{
    await duplicate.evaluateAll(nodes=>nodes.forEach(el=>el.remove()));
  }
  guard((await inspect(3))==='READY','Real modal not restored after negative control');
  await sibling.cancel.click();
  await checkClosed(3);
  entry.observed.push('REAL_MULTI_FIELD_MODAL_ISOLATION');

  async function open(){
    entry.stage='PRIMARY_SELECT_FILE';
    await input.setInputFiles(source);
    const controls=await verifyOpen(1);
    entry.stage='CROP_MODAL_SHOWN';
    entry.stage='CROPPER_RENDERED';
    entry.stage='ACTIONS_VISIBLE';
    entry.stage='ACTIONS_REACHABLE';
    return controls;
  }
  if(diagMode){
    const unready=await page.evaluate(inspectDiagnosticCropStateInPage,
      {formId:id,fieldId:1,readyOnly:true});
    entry.diagnostic.negativeControlRejected=(unready===false);
    guard(unready===false,'Diagnostic negative readiness control unexpectedly passed');
  }
  let controls=await open();
  entry.stage='ESCAPE_DISMISS';
  await page.keyboard.press('Escape');
  await controls.modal.waitFor({state:'hidden',timeout:12000});
  entry.stage='ESCAPE_CLOSED';
  await checkClosed(1);
  entry.observed.push('REAL_CROP_MODAL','ESCAPE_CANCEL_DISCARDS');

  // Cancel button is an independent route from Escape.
  entry.stage='CROP_RETRY';
  controls=await open();
  await controls.cancel.click();
  await checkClosed(1);
  entry.observed.push('CANCEL_BUTTON_DISCARDS');
  controls=await open();
  // Diagnostic-only: read the genuine vue-advanced-cropper result and
  // vendor's batch index; do not alter vendor state or expose image bytes.
  async function inspectSaveState(){
    return page.evaluate(({formId,fieldId})=>{
      const instance=window['GPFUP_'+formId+'_'+fieldId],store=instance?.$store;
      const queue=instance?.vm?[instance.vm]:[],seen=new Set();
      let editor=null;
      while(queue.length&&seen.size<64){
        const item=queue.shift();if(!item||seen.has(item))continue;
        seen.add(item);
        if(item.$options?.name==='GPFUPCropper'&&
          String(item.formId)===String(formId)&&
          String(item.fieldId)===String(fieldId))editor=item;
        for(const child of item.$children||[])queue.push(child);
      }
      const cropper=editor?.$refs?.cropper;
      let canvasReady=false;
      if(cropper&&typeof cropper.getResult==='function'){
        try{const canvas=cropper.getResult()?.canvas;
          canvasReady=!!canvas&&canvas.width>0&&canvas.height>0;
        }catch{}
      }
      const added=store?.getters?.currentAddedFiles;
      const index=store?.getters?.currentImageIndex;
      const modal=document.querySelector('.cropper__lightbox');
      const style=modal&&getComputedStyle(modal);
      return {instanceFound:!!instance,storeFound:!!store,
        editorOpen:store?.state?.editor?.open===true,
        currentFilePresent:!!store?.state?.editor?.currentFile,
        addedCount:Array.isArray(added)?Math.min(100,added.length):-1,
        currentIndex:Number.isInteger(index)?Math.max(-1,Math.min(100,index)):-1,
        cropperRefPresent:!!cropper,canvasReady,
        modalStillVisible:!!modal?.isConnected&&modal?.getBoundingClientRect().width>0&&
          style?.display!=='none'&&style?.visibility!=='hidden'&&Number(style?.opacity)>0};
    },{formId:id,fieldId:1});
  }
  if(diagMode==='B'){
    entry.stage='CROP_SAVE_GENUINE_READINESS_WAIT';
    await page.waitForFunction(inspectDiagnosticCropStateInPage,
      {formId:id,fieldId:1,readyOnly:true},{timeout:15000,polling:50});
    entry.diagnostic.readinessPassed=true;
  }
  if(diagMode){
    entry.diagnostic.before=await page.evaluate(inspectDiagnosticCropStateInPage,
      {formId:id,fieldId:1});
    // Native capture listener observes the real click before Vue's Save handler.
    // It never calls getResult(), mutates the editor, or intercepts the event.
    await controls.save.evaluate((button,{formId,fieldId})=>{
      window.__g013CropClickBoundary=null;
      button.addEventListener('click',()=>{
        const instance=window['GPFUP_'+formId+'_'+fieldId];
        const q=instance?.vm?[instance.vm]:[],seen=new Set();
        let editor=null;
        while(q.length&&seen.size<64){
          const item=q.shift();
          if(!item||seen.has(item))continue;
          seen.add(item);
          if(item.$options?.name==='GPFUPCropper'&&
            String(item.formId)===String(formId)&&String(item.fieldId)===String(fieldId))editor=item;
          for(const child of item.$children||[])q.push(child);
        }
        const ref=editor?.$refs?.cropper;
        window.__g013CropClickBoundary={
          observed:true,atMs:Math.round(performance.now()),
          refPresent:!!ref,imageLoaded:ref?.imageLoaded===true,
          editorOpen:instance?.$store?.state?.editor?.open===true
        };
      },{capture:true,once:true});
    },{formId:id,fieldId:1});
  }
  entry.stage='CROP_SAVE';
  const preSave=await inspectSaveState();
  entry.crop_save_preflight=preSave; // private report only
  if(diagMode)entry.diagnostic.preflight=preSave;
  try {
    await controls.save.click();
  } finally {
    if(diagMode)entry.diagnostic.clickBoundary=await page.evaluate(()=>
      window.__g013CropClickBoundary||{observed:false});
  }
  entry.stage='CROP_SAVE_MODAL_WAIT';
  try{
    await controls.modal.waitFor({state:'hidden',timeout:15000});
  }catch(err){
    // Read only bounded properties from the genuine field's Vuex store.
    // Never serialize files, page HTML, URLs, credentials or vendor instances.
    const probe=await inspectSaveState();
    entry.crop_save_probe=probe; // stays in the private per-run report
    if(diagMode)entry.diagnostic.afterTimeout=await page.evaluate(
      inspectDiagnosticCropStateInPage,{formId:id,fieldId:1});
    entry.stage=classifyCropSaveTimeout({...probe,preCanvasReady:preSave.canvasReady});
    throw err; // Preserve original functional failure, never turn it into PASS.
  }
  if(diagMode)entry.diagnostic.afterModalClosed=await page.evaluate(
    inspectDiagnosticCropStateInPage,{formId:id,fieldId:1});
  entry.stage='UPLOAD_PROGRESS_WAIT';
  await page.locator(root+' .gpfup__progress-container').first().waitFor({state:'hidden',timeout:40000});
  entry.stage='UPLOAD_FILE_WAIT';
  await page.locator(root+' .gpfup__filename').first().waitFor({state:'visible',timeout:20000});
  entry.stage='UPLOAD_FILE_NAME_CHECK';
  guard((await page.locator(root+' .gpfup__filename').first().innerText()).includes('g013-disposable.png'),
    'Genuine cropped upload filename missing');
  entry.stage='UPLOAD_NATIVE_CHECK';
  guard(classifyNativeUpload(await uploaded(1),'CONFIRMED')==='PASS',
    'Genuine upload was not confirmed by native GF uploaded-files state');
  if(diagMode)entry.diagnostic.nativeUploadConfirmed=
    classifyNativeUpload(await uploaded(1),'CONFIRMED')==='PASS';
  entry.stage='UPLOAD_COMPLETE';
  entry.stage='FILE_VISIBLE';
  entry.stage='REEDIT_OPENING';
  await page.locator(root+' .gpfup__edit').first().click();
  let reedit=await verifyOpen(1);
  await reedit.cancel.click();
  await reedit.modal.waitFor({state:'hidden',timeout:12000});
  guard((await page.locator(root+' .gpfup__filename').count())>0,
    'Canceling reedit discarded an already uploaded image');
  entry.observed.push('REAL_CROP_SAVED_AND_UPLOADED','RERENDERED_PREVIEW_REEDIT');
  entry.stage='VALIDATION_SUBMIT';
  guard((await text.inputValue())==='','G013 real invalid-control text already nonempty');
  await page.locator('#gform_submit_button_'+id).click();
  const validation=page.locator('#field_'+id+'_2.gfield_error,#gform_wrapper_'+id+' .gform_validation_errors');
  await validation.first().waitFor({state:'visible',timeout:20000});
  guard(await page.locator(root+' .gpfup__filename').count()>0,
    'Cropped image did not survive real validation rerender');
  guard(classifyNativeUpload(await uploaded(1),'CONFIRMED')==='PASS',
    'Native GF record did not survive validation rerender');
  entry.observed.push('AUTHENTIC_GF_SERVER_VALIDATION','POST_VALIDATION_FILE_REHYDRATED');
  // Re-edit after the genuine server validation rerender, not just before it.
  await page.locator(root+' .gpfup__edit').first().click();
  reedit=await verifyOpen(1);
  await reedit.cancel.click();
  await reedit.modal.waitFor({state:'hidden',timeout:12000});
  guard(await page.locator(root+' .gpfup__filename').count()>0,
    'Post-validation reedit lost the saved image');
  guard(classifyNativeUpload(await uploaded(1),'CONFIRMED')==='PASS',
    'Post-validation reedit lost native GF upload confirmation');
  entry.stage='VALIDATION_RERENDERED';
}
async function editor(page,entry){
  await goto(page,fixture.editor_url);
  await page.waitForFunction(()=>{
    try{return window.wp?.data?.select('core/block-editor')?.getBlocks()
      ?.some(block=>block.name==='gk-gravityview-blocks/view');}catch{return false;}
  },null,{timeout:25000,polling:400});
  const selected=await page.evaluate(()=>{
    const blocks=wp.data.select('core/block-editor').getBlocks();
    return blocks.some(b=>b.name==='gk-gravityview-blocks/view');
  });
  guard(selected,'Real registered View block not in Gutenberg editor');
  entry.observed.push('REAL_GV_GUTENBERG_BLOCK');
  const frame=page.frames().find(f=>f!==page.mainFrame()&&/editor-canvas|post\.php|about:blank/.test(f.url()));
  const scopes=frame?[frame,page]:[page];
  let chosen=null;
  for(const scope of scopes){
    const candidate=scope.locator('[class$="-control"] input[role="combobox"]').first();
    if(await candidate.count() && await candidate.isVisible()){chosen={scope,input:candidate};break;}
  }
  guard(chosen,'Actual detached GravityView React Select not rendered');
  await chosen.input.focus();
  guard(await chosen.input.evaluate(el=>el===document.activeElement),'GravityView React Select focus failed');
  await chosen.input.click();
  const dropdown=chosen.scope.locator('[role="listbox"],[class$="-menu"]').first();
  await dropdown.waitFor({state:'visible',timeout:15000});
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
  const option=dropdown.locator('[role="option"]').filter({hasText:'WU008 G008 GravityView System Dates'}).first();
  await option.waitFor({state:'visible',timeout:10000});
  await option.click();
  await page.waitForFunction(id=>{
    const all=wp.data.select('core/block-editor').getBlocks();
    return all.some(b=>b.name==='gk-gravityview-blocks/view' && String(b.attributes.viewId)===String(id));
  },fixture.view_id,{timeout:10000});
  entry.observed.push('REAL_REACT_SELECT_PORTAL','GUTENBERG_VIEW_SELECTION');
  // GravityView 3.3.4 registers its real date controls under the
  // collapsed "Entries Settings" Gutenberg Inspector PanelBody.
  // Open actual panels through native UI rather than conjuring the picker.
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
  await date.click();
  await page.locator('.react-datepicker:visible').first().waitFor({state:'visible',timeout:10000});
  await page.keyboard.press('Escape');
  entry.observed.push('REAL_GV_DATEPICKER_POPUP');
}
try{
  for(const width of widths){
    if(!diagMode)await runCase('perks-admin',width,perks);
    await runCase('fup-crop',width,crop);
    if(!diagMode)await runCase('gv-block-editor',width,editor);
  }
  report.status=report.cases.every(c=>c.status==='PASS')?'PASS':'NOT_PROVEN';
}finally{
  fs.writeFileSync(path.join(dir,key+'.json'),JSON.stringify(report,null,2)+'\n');
  await browser.close();
}
if(report.status!=='PASS')throw Error('G013 expanded authentic browser coverage incomplete: '+profile+' '+locale);
if(!diagMode)console.log('G013_EXPANDED_BROWSER_PASS '+profile+' '+locale+' six authentic scenarios');
