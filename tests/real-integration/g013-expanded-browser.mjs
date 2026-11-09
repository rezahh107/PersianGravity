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
  page.on('pageerror',()=>errors.push('PAGE_ERROR'));
  const entry={name,width,status:'INCONCLUSIVE',font:null,geometry:[],observed:[],screenshot:null};
  try{
    await login(page);
    await body(page,entry);
    guard(!errors.length,'Real browser page exception');
    entry.font=await verifyFont(page);
    if(profile==='BASELINE')guard(!entry.font.loadedFace&&!entry.font.hasVazir,'Baseline font contamination');
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
  await goto(page,fixture.crop_url);
  const id=fixture.crop_form_id;
  const root='#field_'+id+'_1';
  const text=page.locator('#input_'+id+'_2');
  await page.locator(root+' .gpfup__droparea').waitFor({state:'visible',timeout:15000});
  await text.waitFor({state:'visible'});
  const input=page.locator(root+' input[type=file]').first();
  const portal='#gpfup-cropper-portal-'+id+'-1';
  guard(await page.locator(portal).count()===1,'Authentic FUP crop portal absent');
  const image=await page.evaluate(()=>{
    const canvas=document.createElement('canvas');
    canvas.width=240;canvas.height=180;
    const context=canvas.getContext('2d');
    context.fillStyle='#f0f0f0';context.fillRect(0,0,240,180);
    context.fillStyle='#333';context.fillRect(30,30,180,120);
    return canvas.toDataURL('image/png').split(',')[1];
  });
  const source={name:'g013-disposable.png',mimeType:'image/png',buffer:Buffer.from(image,'base64')};
  async function open(){
    await input.setInputFiles(source);
    const modal=page.locator(portal+' .cropper__lightbox');
    await modal.waitFor({state:'visible',timeout:20000});
    await page.locator(portal+' .vue-advanced-cropper').first().waitFor({state:'visible',timeout:15000});
    const cancel=page.locator(portal+' button.gpfup__cancel');
    const save=page.locator(portal+' button.gpfup__crop');
    await cancel.waitFor({state:'visible'});
    await save.waitFor({state:'visible'});
    const cg=await measure(page,portal+' button.gpfup__cancel');
    const sg=await measure(page,portal+' button.gpfup__crop');
    guard(isOnscreen(cg)&&isOnscreen(sg),'Cropper action clipped at viewport');
    entry.geometry.push({id:'crop_cancel',...cg},{id:'crop_save',...sg});
    return {modal,cancel,save};
  }
  let controls=await open();
  await page.keyboard.press('Escape');
  await controls.modal.waitFor({state:'hidden',timeout:12000});
  await page.locator(root+' .gpfup__filename').first().waitFor({state:'detached',timeout:20000}).catch(async()=>{
    guard(await page.locator(root+' .gpfup__filename').count()===0,'Canceled crop was not discarded');
  });
  entry.observed.push('REAL_CROP_MODAL','ESCAPE_CANCEL_DISCARDS');
  controls=await open();
  await controls.save.click();
  await controls.modal.waitFor({state:'hidden',timeout:15000});
  await page.locator(root+' .gpfup__progress-container').first().waitFor({state:'hidden',timeout:40000});
  await page.locator(root+' .gpfup__filename').first().waitFor({state:'visible',timeout:20000});
  guard((await page.locator(root+' .gpfup__filename').first().innerText()).includes('g013-disposable.png'),
    'Genuine cropped upload filename missing');
  await page.locator(root+' .gpfup__edit').first().click();
  await page.locator(portal+' .cropper__lightbox').waitFor({state:'visible',timeout:12000});
  await page.locator(portal+' button.gpfup__cancel').click();
  await page.locator(portal+' .cropper__lightbox').waitFor({state:'hidden',timeout:12000});
  entry.observed.push('REAL_CROP_SAVED_AND_UPLOADED','RERENDERED_PREVIEW_REEDIT');
  guard((await text.inputValue())==='','G013 real invalid-control text already nonempty');
  await page.locator('#gform_submit_button_'+id).click();
  const validation=page.locator('#field_'+id+'_2.gfield_error,#gform_wrapper_'+id+' .gform_validation_errors');
  await validation.first().waitFor({state:'visible',timeout:20000});
  guard(await page.locator(root+' .gpfup__filename').count()>0,
    'Cropped image did not survive real validation rerender');
  entry.observed.push('AUTHENTIC_GF_SERVER_VALIDATION','POST_VALIDATION_FILE_REHYDRATED');
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
  const date=page.locator('.react-datepicker__input-container input:visible').first();
  if(!await date.count())throw Error('Real datepicker in GravityView View-block editor not visible');
  await date.click();
  await page.locator('.react-datepicker:visible').first().waitFor({state:'visible',timeout:10000});
  await page.keyboard.press('Escape');
  entry.observed.push('REAL_GV_DATEPICKER_POPUP');
}
try{
  for(const width of widths){
    await runCase('perks-admin',width,perks);
    await runCase('fup-crop',width,crop);
    await runCase('gv-block-editor',width,editor);
  }
  report.status=report.cases.every(c=>c.status==='PASS')?'PASS':'NOT_PROVEN';
}finally{
  fs.writeFileSync(path.join(dir,key+'.json'),JSON.stringify(report,null,2)+'\n');
  await browser.close();
}
if(report.status!=='PASS')throw Error('G013 expanded authentic browser coverage incomplete: '+profile+' '+locale);
console.log('G013_EXPANDED_BROWSER_PASS '+profile+' '+locale+' six authentic scenarios');
