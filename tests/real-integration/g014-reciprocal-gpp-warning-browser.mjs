/**
 * Focused real-browser reciprocal GPP warning contract (NOT a Grid harness).
 * WordPress 6.8.3, GF 3.1.1.1, Flow 3.1.0, GPP PR #174 pinned source.
 * Tests only authentic Settings saves and read-only integration outcomes.
 * Report contains synthetic enums; no passwords, cookies, user data or HTML.
 */
import fs from 'node:fs';
import path from 'node:path';
import {spawnSync} from 'node:child_process';
import {chromium} from 'playwright';

const expectedGpp='c8cf9fd7bb615075402b78eaa1f01dd33d855d28';
const required=['WU008_WP_CLI','WU008_WP_PATH','WU008_BASE_URL','WU008_ADMIN_PASSWORD','WU008_PGR_SHA','WU008_ARTIFACT_DIR','PGR_G014_GPP_REF'];
for(const k of required)if(!process.env[k])throw Error('ENVIRONMENT_MISSING_'+k);
if(process.env.PGR_G014_GPP_REF!==expectedGpp)throw Error('GPP_SOURCE_IDENTITY_MISMATCH');
const origin=new URL(process.env.WU008_BASE_URL).origin;
const output=path.join(process.env.WU008_ARTIFACT_DIR,'g014-reciprocal-warning-evidence');
fs.mkdirSync(output,{recursive:true});
const report={schema:'pgr-g014-reciprocal-warning-v1',source_pgr:process.env.WU008_PGR_SHA,
 source_gpp:expectedGpp,wordpress:'6.8.3',gravityforms:'3.1.1.1',gravityflow:'3.1.0',
 test_class:'REAL_WORDPRESS_SETTINGS_BROWSER',data_class:'SYNTHETIC_NON_PII',cases:[],status:'NOT_PROVEN'};
const requireTrue=(v,msg)=>{if(!v)throw Error(msg)};
function wp(...args){
 const v=spawnSync('php',[process.env.WU008_WP_CLI,'--path='+process.env.WU008_WP_PATH,...args],{encoding:'utf8',env:process.env,timeout:30000});
 if(v.status!==0||v.error)throw Error('WP_CLI_FAILED_'+args[0]+'_exit_'+v.status);
 return v.stdout.trim();
}
const evaluate=code=>wp('eval',code);
const snapshot=()=>JSON.parse(evaluate(`echo wp_json_encode(['pgr'=>pgr_gravity_flow_inbox_rtl_status(),'gpp'=>function_exists('gpp_gravity_flow_inbox_axis_status')?gpp_gravity_flow_inbox_axis_status():null,'site'=>get_locale()]);`));
const checks=(id,expected,actual)=>{requireTrue(actual===expected,'ASSERT_'+id+'_'+JSON.stringify({expected,actual})); report.cases.push({id,status:'PASS',observed:actual});};
const pgrUrl=origin+'/wp-admin/admin.php?page=persian-gravityforms-settings';
const gppUrl=origin+'/wp-admin/admin.php?page=gf_settings&subview=gravity-presentation-profiles';
async function login(page,user,password){
 const rsp=await page.goto(origin+'/wp-login.php',{waitUntil:'domcontentloaded'});
 requireTrue(rsp?.ok(),'LOGIN_FORM_MISSING');
 await page.locator('#user_login').fill(user);
 await page.locator('#user_pass').fill(password);
 await Promise.all([page.waitForURL(u=>!u.pathname.includes('wp-login.php'),{waitUntil:'domcontentloaded',timeout:30000}),page.locator('#wp-submit').click()]);
}
function locales(site,user){
 requireTrue(['fa_IR','en_US'].includes(site)&&['fa_IR','en_US'].includes(user),'UNSUPPORTED_LOCALE');
 if(site==='fa_IR')wp('option','update','WPLANG','fa_IR');
 else wp('option','delete','WPLANG');
 evaluate(`$u=get_user_by('login','runtime_admin');if(!$u)throw new RuntimeException('Admin unavailable');update_user_meta($u->ID,'locale','${user}');`);
 const r=JSON.parse(evaluate(`$u=get_user_by('login','runtime_admin');echo wp_json_encode(['site'=>get_locale(),'user'=>get_user_locale($u)]);`));
 requireTrue(r.site===site&&r.user===user,'LOCALE_READBACK_MISMATCH');
}
async function pgrSave(page,mode){
 const rsp=await page.goto(pgrUrl,{waitUntil:'domcontentloaded'});
 requireTrue(rsp?.ok(),'PGR_SETTINGS_UNAVAILABLE');
 const toggle=page.locator('input[name="pgr_settings[gravity_flow_inbox_rtl]"]');
 requireTrue(await toggle.count()===1,'PGR_CHECKBOX_MISSING');
 const before=snapshot().gpp?.effective;
 await toggle.setChecked(mode==='on');
 await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('form[action="options.php"] input[type="submit"]').first().click()]);
 await page.goto(pgrUrl,{waitUntil:'domcontentloaded'});
 requireTrue(await toggle.isChecked()===(mode==='on'),'PGR_SAVE_NOT_RESTORED');
 requireTrue(snapshot().pgr.requested===mode,'PGR_SAVE_NOT_PERSISTED');
 requireTrue(snapshot().gpp?.effective===before,'PGR_SAVE_CHANGED_GPP_SETTING');
}
async function gppSave(page,mode){
 const prior=snapshot().pgr.requested;
 const rsp=await page.goto(gppUrl,{waitUntil:'domcontentloaded'});
 requireTrue(rsp?.ok(),'GPP_SETTINGS_UNAVAILABLE');
 const select=page.locator('select[name="_gform_setting_inbox_axis_ltr"]');
 requireTrue(await select.count()===1,'GPP_SWITCH_NOT_VISIBLE');
 await select.selectOption(mode);
 const submit=page.locator('#gform-settings button[type="submit"], #gform-settings input[type="submit"]').last();
 await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),submit.click()]);
 await page.goto(gppUrl,{waitUntil:'domcontentloaded'});
 requireTrue(await select.inputValue()===mode&&snapshot().gpp?.effective===mode,'GPP_SAVE_NOT_RESTORED');
 requireTrue(snapshot().pgr.requested===prior,'GPP_SAVE_CHANGED_PGR_SETTING');
}
async function warning(page,expected,problem){
 const rsp=await page.goto(pgrUrl,{waitUntil:'domcontentloaded'});
 requireTrue(rsp?.ok(),'PGR_SETTINGS_NOT_READABLE');
 const box=page.locator('#pgr-g014-gpp-warning');
 requireTrue(await box.count()===1,'RECIPROCAL_WARNING_MISSING');
 const actual=await box.getAttribute('data-pgr-gpp-compatibility');
 const issue=await box.getAttribute('data-pgr-gpp-problem');
 requireTrue(actual===expected&&issue===problem,'RECIPROCAL_WARNING_WRONG_'+JSON.stringify({actual,issue,expected,problem}));
 requireTrue(await box.locator('details summary').count()===1,'ACCESSIBLE_EVIDENCE_DETAIL_MISSING');
 requireTrue(await page.locator('#pgr-g014-compatibility [role="group"][aria-label]').count()===2,'ORIGINAL_MODE_SCHEMATICS_MISSING');
 const details=await box.textContent(); // Includes collapsed accessible <details> copy.
 requireTrue(details.includes('Inbox 1')&&details.includes('Inbox 2'),'INBOX1_INBOX2_DISTINCTION_MISSING');
 report.cases.push({id:'warning-'+problem+'-'+report.cases.length,status:'PASS',verdict:actual,issue});
}
async function main(){
 const originalPgrOption=wp('eval','echo base64_encode(serialize(get_option("pgr_settings",array())));');
 requireTrue(/^[A-Za-z0-9+/=]+$/.test(originalPgrOption),'ORIGINAL_PGR_OPTION_SNAPSHOT_MALFORMED');
 // Restore the exact pre-test admin locale: leaving fa_IR would contaminate
 // later WU008 English G008 timeline assertions even when WPLANG is en_US.
 const originalLocale=JSON.parse(evaluate(`$u=get_user_by('login','runtime_admin');if(!$u)throw new RuntimeException('Missing synthetic admin');echo wp_json_encode(['exists'=>metadata_exists('user',$u->ID,'locale'),'value'=>get_user_meta($u->ID,'locale',true),'site'=>get_option('WPLANG',null)]);`));
 requireTrue(typeof originalLocale.value==='string'&&(originalLocale.site===null||typeof originalLocale.site==='string'),'ORIGINAL_LOCALE_SNAPSHOT_MALFORMED');
 const browser=await chromium.launch({headless:true});
 report.chromium=browser.version();
 const context=await browser.newContext({viewport:{width:1280,height:800}});
 const page=await context.newPage();
 let gppActive=true,flowActive=true;
 try{
  locales('fa_IR','fa_IR');
  await login(page,'runtime_admin',process.env.WU008_ADMIN_PASSWORD);
  const rows=[
   ['off','off','known_failure','ltr_grid_without_axis'],
   ['off','on','known_failure','native_admin_mobile_scroll'],
   ['on','off','known_failure','rtl_column_order_without_axis'],
   ['on','on','incompatible','rtl_with_ltr_axis']
  ];
  for(const [pgr,gpp,verdict,issue] of rows){
   await gppSave(page,gpp);
   await pgrSave(page,pgr);
   const s=snapshot();
   requireTrue(s.pgr.host==='admitted'&&s.gpp?.api_version===1&&s.gpp?.capability==='supported'&&s.gpp?.scope==='gravity_flow.inbox_1','STATUS_CONTRACT_UNSUPPORTED');
   await warning(page,verdict,issue);
  }
  // Mixed language: effective request locale must not be confused with site.
  locales('en_US','en_US');
  await warning(page,'unknown','none');
  locales('en_US','fa_IR');
  await warning(page,'known_failure','native_admin_mobile_touch');
  locales('fa_IR','en_US');
  await warning(page,'incompatible','rtl_with_ltr_axis');

  // GPP's native Add-On settings nonce must also reject a forged request.
  await page.goto(gppUrl,{waitUntil:'domcontentloaded'});
  const axis=page.locator('select[name="_gform_setting_inbox_axis_ltr"]');
  requireTrue(await axis.inputValue()==='on','GPP_NONCE_CONTROL_NEEDS_AXIS_ON');
  const gppNonce=page.locator('#gform-settings input[name="gform_settings_save_nonce"]');
  requireTrue(await gppNonce.count()===1,'GPP_NATIVE_NONCE_MISSING');
  await axis.selectOption('off');
  await gppNonce.evaluate(n=>n.remove());
  await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('#gform-settings button[type="submit"], #gform-settings input[type="submit"]').last().click()]);
  checks('invalid-nonce-no-gpp-write','on',snapshot().gpp?.effective);

  // A missing nonce must never mutate PGR's option.
  await page.goto(pgrUrl,{waitUntil:'domcontentloaded'});
  const toggle=page.locator('input[name="pgr_settings[gravity_flow_inbox_rtl]"]');
  requireTrue(await toggle.isChecked(),'NONCE_CONTROL_NEEDS_PGR_ON');
  const nonce=page.locator('form[action="options.php"] input[name="_wpnonce"]');
  requireTrue(await nonce.count()===1,'NATIVE_SETTINGS_NONCE_MISSING');
  await toggle.uncheck();
  await nonce.evaluate(n=>n.remove());
  await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('form[action="options.php"] input[type="submit"]').first().click()]);
  checks('invalid-nonce-no-pgr-write','on',snapshot().pgr.requested);

  // Missing/inactive provider must give UNKNOWN without fatal or foreign writes.
  wp('plugin','deactivate','gravity-presentation-profiles');gppActive=false;
  checks('inactive-gpp-removed','absent',snapshot().gpp===null?'absent':'present');
  await warning(page,'unknown','none');
  wp('plugin','activate','gravity-presentation-profiles');gppActive=true;
  checks('gpp-recovered','supported',snapshot().gpp?.capability);

  // Unsupported Flow version/presence is UNKNOWN even with GPP ON.
  wp('plugin','deactivate','gravityflow');flowActive=false;
  await warning(page,'unknown','none');
  wp('plugin','activate','gravityflow');flowActive=true;
  checks('flow-host-recovered','admitted',snapshot().pgr.host);

  // WordPress must reject settings for a subscriber, not only an anonymous user.
  const denied=await browser.newContext();
  try{
   const anon=await denied.newPage();
   await anon.goto(pgrUrl,{waitUntil:'domcontentloaded'});
   checks('anonymous-access-denied',true,anon.url().includes('wp-login.php'));
  }finally{await denied.close();}
  // A logged-in subscriber cannot read either plugin settings or trigger UI.
  if(process.env.WU008_VIEWER_PASSWORD){
   const viewerContext=await browser.newContext();
   try{
    const viewer=await viewerContext.newPage();
    await login(viewer,'g014_reciprocal_viewer',process.env.WU008_VIEWER_PASSWORD);
    await viewer.goto(pgrUrl,{waitUntil:'domcontentloaded'});
    checks('subscriber-pgr-settings-denied',0,await viewer.locator('#pgr-g014-gpp-warning, #pgr-g014-rtl-checkbox').count());
    await viewer.goto(gppUrl,{waitUntil:'domcontentloaded'});
    checks('subscriber-gpp-settings-denied',0,await viewer.locator('select[name="_gform_setting_inbox_axis_ltr"]').count());
   }finally{await viewerContext.close();}
  }else throw Error('ENVIRONMENT_VIEWER_PASSWORD_MISSING');
  report.cases.push({id:'source-read-only-and-independent-switches',status:'PASS'});
  report.status='PASS';
 }catch(e){report.status='FAIL';report.failure=String(e.message||e).slice(0,900);process.exitCode=1;}
 finally{
  try{if(!gppActive)wp('plugin','activate','gravity-presentation-profiles');}catch{}
  try{if(!flowActive)wp('plugin','activate','gravityflow');}catch{}

  const cleanupFailures=[];
  try{wp('eval',`update_option("pgr_settings",unserialize(base64_decode("${originalPgrOption}"),["allowed_classes"=>false]));`);}catch(e){cleanupFailures.push('pgr_settings');}
  try{
   const stored=Buffer.from(originalLocale.value,'utf8').toString('base64');
   evaluate(`$u=get_user_by('login','runtime_admin');if(!$u)throw new RuntimeException('Admin disappeared');if(${originalLocale.exists?'true':'false'}){update_user_meta($u->ID,'locale',base64_decode('${stored}'));}else{delete_user_meta($u->ID,'locale');}`);
   if(originalLocale.site===null)wp('option','delete','WPLANG');
   else wp('option','update','WPLANG',originalLocale.site);
   const restored=JSON.parse(evaluate(`$u=get_user_by('login','runtime_admin');echo wp_json_encode(['exists'=>metadata_exists('user',$u->ID,'locale'),'value'=>get_user_meta($u->ID,'locale',true),'site'=>get_option('WPLANG',null)]);`));
   if(restored.exists!==originalLocale.exists||restored.value!==originalLocale.value||restored.site!==originalLocale.site)
    throw Error('Original locale restore readback mismatch');
   report.cases.push({id:'original-admin-locale-and-site-restored',status:'PASS'});
  }catch(e){cleanupFailures.push('locale_restore_'+String(e.message||e).slice(0,90));}
  if(cleanupFailures.length){report.status='FAIL';report.failure='TEST_CLEANUP_NOT_PROVEN: '+cleanupFailures.join(',');process.exitCode=1;}
  await context.close();await browser.close();
  fs.writeFileSync(path.join(output,'report.json'),JSON.stringify(report,null,2)+'\n');
  if(report.status!=='PASS')console.error('PGR_G014_RECIPROCAL_BROWSER_FAIL '+report.failure);
  else console.log('PGR_G014_RECIPROCAL_BROWSER_PASS cases='+report.cases.length);
 }
}
await main();
