import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const root = process.env.WU008_ARTIFACT_DIR;
const runtime = JSON.parse(fs.readFileSync(process.env.WU008_MANIFEST_PATH, 'utf8'));
const head = process.env.WU008_PGR_SHA;
const lang = process.env.PGR_DECISION_LOCALE;
const phase = process.env.PGR_DECISION_PHASE;
if (!root || !/^[a-f0-9]{40}$/.test(head||'') || runtime.persiangravity_source_commit!==head ||
    !['fa_IR','en_US'].includes(lang) || !['used','unused'].includes(phase)) {
  throw Error('Authenticated exact-Head WU008 and explicit locale/fixture phase required');
}
const out=path.join(root,'module-disable-evidence');
fs.mkdirSync(out,{recursive:true});
const browser=await chromium.launch({headless:true});
const report={exact_head:head,locale:lang,phase,status:'INCOMPLETE',
  runtime:{php:runtime.php_version,versions:runtime.versions,chromium:browser.version(),wordpress:'6.8.3'},
  cases:[],limits:['Automated focus and layout probes are not screen-reader or human usability acceptance']};
const key=phase+'-'+lang;
const confirmLabel=lang==='fa_IR'?'تأیید غیرفعال‌سازی':'Confirm disable';
const cancelLabel=lang==='fa_IR'?'لغو و بازگشت':'Cancel and return';
const home=new URL('admin.php?page=persian-gravityforms',runtime.admin_url).href;
const submit='form[action$="admin-post.php"]';
function check(ok,msg){if(!ok)throw Error('MODULE_DECISION: '+msg);}
async function measure(page){
  return page.evaluate(()=>{
    const d=document.querySelector('.pgr-module-decision');
    const grid=document.querySelector('.pgr-module-grid');
    const r=d?.getBoundingClientRect();
    const actions=d?.querySelector('.pgr-module-decision__actions')?.getBoundingClientRect();
    return {viewport:innerWidth,document_scroll_width:document.documentElement.scrollWidth,
      direction:getComputedStyle(document.documentElement).direction,
      decision_top:r?.top??null,grid_top:grid?.getBoundingClientRect().top??null,
      decision_width:r?.width??null,action_width:actions?.width??null,
      focusables:d?.querySelectorAll('button,a').length??0};
  });
}
async function capture(page,item,stage,width){
  const name='decision-'+key+'-'+width+'-'+stage+'.jpg';
  await page.screenshot({path:path.join(out,name),type:'jpeg',quality:84,fullPage:true});
  item.images.push(name);
}
async function login(page){
  const response=await page.goto(runtime.login_url,{waitUntil:'domcontentloaded'});
  check(response?.ok(),'Login page missing');
  await page.locator('#user_login').fill('runtime_admin');
  await page.locator('#user_pass').fill(process.env.WU008_ADMIN_PASSWORD);
  await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('#wp-submit').click()]);
  check(await page.locator('#wpadminbar').count()===1,'Authentication failed');
}
async function view(page){
  const response=await page.goto(home,{waitUntil:'domcontentloaded'});
  check(response?.ok(),'Overview not available');
  check(await page.locator('.pgr-module-grid').count()===1,'Native overview missing');
}
function card(page,id){return page.locator('article.pgr-module-card').filter({has:page.locator('input[name="module_id"][value="'+id+'"]')});}
async function toggle(page,id,action){
  const target=card(page,id);
  check(await target.count()===1,'Module card count wrong for '+id);
  const button=target.locator('button[type="submit"]');
  if(action)check((await target.locator('input[name="enable"]').inputValue())===action,'Wrong toggle direction');
  await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),button.click()]);
  return new URL(page.url());
}
function notice(url,type){check(url.searchParams.get('pgr_module_notice')===type,'Expected notice '+type+', got '+url.search);}
let failed=false;
try {
  for(const width of [1280,390,320]){
    const height=width===1280?900:width===390?844:700;
    const context=await browser.newContext({viewport:{width,height},deviceScaleFactor:1});
    const page=await context.newPage();
    const record={width,height,status:'FAILED',images:[],checks:[]};
    try {
      await login(page);
      await view(page);
      check(await page.locator('.pgr-module-decision').count()===0,'Normal Overview displays a decision');
      check(await page.locator('.pgr-module-card').count()===7,'Normal module grid lost a module');
      check(await card(page,'iranian_currency').count()===1,'Currency missing from normal grid');
      check(await card(page,'national_id').count()===1,'National ID missing from normal grid');
      const intro=await measure(page);
      check(intro.document_scroll_width<=width,'Normal Overview horizontal overflow');
      record.normal=intro;
      await capture(page,record,'normal',width);
      if(phase==='used'){
        // A real server-side first POST is required to create the pending ticket.
        let url=await toggle(page,'iranian_currency','0');
        notice(url,'confirm');
        const decision=page.locator('.pgr-module-decision');
        check(await decision.count()===1,'No top-level UNKNOWN decision');
        check(await decision.locator('input[name="confirm"][value="1"]').count()===1,'No explicit second POST');
        const token=await decision.locator('input[name="confirm_token"]').inputValue();
        check(token.length>=20,'No server-generated ticket');
        check(await decision.locator('button[type=submit]').innerText()===confirmLabel,'Unclear dangerous action label');
        check(await decision.getByRole('link',{name:cancelLabel}).count()===1,'Cancel missing');
        check(await page.locator('.pgr-module-card').count()===6,'Decision module duplicated in grid');
        check(await card(page,'iranian_currency').count()===0,'Target module duplicated');
        check(await page.locator('.pgr-warning-note').count()===0,'Competing old warning retained');
        check(await decision.locator('.notice-warning').count()===1,'Missing or duplicated risk notice');
        const body=await decision.innerText();
        for(const name of ['Iranian Rial / Toman','ریال و تومان ایران','IRR','IRT']){
          check(body.includes(name),'Missing bilingual identity or description '+name);
        }
        check(body.includes(lang==='fa_IR'?'هنوز وضعیت ماژول تغییر نکرده است':'No module state has changed yet'),'Missing explicit no-change statement');
        check((await page.locator('.pgr-module-decision').boundingBox()).y <
          (await page.locator('.pgr-module-grid').boundingBox()).y,'Decision appears below module grid');
        const layout=await measure(page);
        check(layout.document_scroll_width<=width && layout.decision_width<=width,'Decision overflows viewport');
        record.decision=layout;
        const confirmButton=decision.locator('button[type=submit]');
        await confirmButton.focus();
        await page.keyboard.press('Tab');
        check(await decision.getByRole('link',{name:'Cancel and return'}).evaluate(el=>document.activeElement===el),
          'Cancel is not next in keyboard order');
        const focus=await decision.getByRole('link',{name:'Cancel and return'}).evaluate(el=>({
          outline:getComputedStyle(el).outlineStyle,boxShadow:getComputedStyle(el).boxShadow
        }));
        check(focus.outline!=='none'||focus.boxShadow!=='none','No visible cancel focus indicator');
        record.focus=focus;
        await capture(page,record,'decision',width);
        // Forged GET and mismatched query fields cannot summon a valid decision.
        for(const query of [
          '?pgr_module_notice=confirm&pgr_module=iranian_currency&pgr_confirm_module=national_id&pgr_confirm_token='+token,
          '?pgr_module_notice=confirm&pgr_module=national_id&pgr_confirm_module=national_id&pgr_confirm_token='+token,
          '?pgr_module_notice=confirm&pgr_module=iranian_currency&pgr_confirm_module=iranian_currency&pgr_confirm_token=forged',
        ]){
          const forged=await page.goto(home+'&'+query.slice(1),{waitUntil:'domcontentloaded'});
          check(forged?.ok(),'Forged GET request failed to reach Overview');
          check(await page.locator('.pgr-module-decision').count()===0,'Forged/mismatched GET produced actionable decision');
          check(await page.locator('input[name="confirm"][value="1"]').count()===0,'Forged GET exposed a confirmed POST');
        }
        record.checks.push('forged_get_rejected','mismatched_get_rejected');
        // Revisit genuine link, then exercise a non-mutating cancel.
        await page.goto(url.href,{waitUntil:'domcontentloaded'});
        check(await decision.count()===1,'Valid first-stage ticket no longer displayed');
        await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),
          decision.getByRole('link',{name:'Cancel and return'}).click()]);
        check(!new URL(page.url()).searchParams.has('pgr_confirm_module'),'Cancel retained pending query');
        check(await decision.count()===0 && await card(page,'iranian_currency').count()===1,'Cancel did not restore normal grid');
        check(await card(page,'iranian_currency').locator('input[name="enable"]').inputValue()==='0','Cancel changed currency state');
        await capture(page,record,'cancel',width);
        await page.goto(url.href,{waitUntil:'domcontentloaded'});
        check(await page.locator('.pgr-module-decision').count()===0,
          'Cancel failed to revoke the original confirmation ticket');
        await view(page);
        record.checks.push('cancel_no_change','cancel_revoked_token');
        // A saved USED form must block even a crafted confirmed POST.
        url=await toggle(page,'national_id','0');
        notice(url,'blocked');
        check(await card(page,'national_id').locator('input[name="enable"]').inputValue()==='0','USED disable was not blocked');
        await view(page);
        const nationalForm=card(page,'national_id').locator(submit);
        await nationalForm.evaluate((el,token)=>{
          for(const [name,value] of [['confirm','1'],['confirm_token',token]]){
            const field=document.createElement('input');field.type='hidden';field.name=name;field.value=value;el.appendChild(field);
          }
        },token);
        await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),
          nationalForm.locator('button[type=submit]').click()]);
        notice(new URL(page.url()),'blocked');
        check(await card(page,'national_id').locator('input[name="enable"]').inputValue()==='0',
          'USED became disabled by crafted POST');
        record.checks.push('used_blocked','used_forged_confirm_blocked');
        // Invalid CSRF nonce never disables currency.
        if(width===1280){
          await view(page); await toggle(page,'iranian_currency','0');
          const pending=page.locator('.pgr-module-decision');
          await pending.locator('input[name="_wpnonce"]').evaluate(el=>el.value='invalid-nonce');
          await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),pending.locator('button[type=submit]').click()]);
          check((await page.locator('body').innerText()).includes('Are you sure') ||
            (await page.locator('body').innerText()).includes('expired') ||
            (await page.locator('body').innerText()).includes('اطمینان'),
            'Invalid nonce did not produce WordPress refusal');
          await view(page);
          check(await card(page,'iranian_currency').locator('input[name="enable"]').inputValue()==='0',
            'Invalid nonce disabled currency');
          record.checks.push('invalid_nonce_rejected');
        }
        // Actual second POST, followed by actual server-side state verification via UI.
        await view(page); url=await toggle(page,'iranian_currency','0'); notice(url,'confirm');
        const active=page.locator('.pgr-module-decision');
        await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),
          active.locator('button[type=submit]').click()]);
        url=new URL(page.url());notice(url,'disabled');
        check(url.searchParams.get('pgr_module')==='iranian_currency','Success attributed to wrong module');
        check(await card(page,'iranian_currency').locator('input[name="enable"]').inputValue()==='1',
          'Actual UNKNOWN confirmation did not disable currency');
        check((await page.locator('.notice-success').first().innerText()).includes('Iranian Rial / Toman'),
          'Success notice does not identify affected module');
        await capture(page,record,'confirmed',width);
        await toggle(page,'iranian_currency','1');
        check(await card(page,'iranian_currency').locator('input[name="enable"]').inputValue()==='0','Currency not restored');
        record.checks.push('unknown_second_post_disabled','currency_restored');
        // Unauthenticated request never succeeds.
        if(width===1280){
          const guest=await browser.newContext();
          try{
            const res=await guest.request.post(new URL('admin-post.php',runtime.admin_url).href,{
              form:{action:'pgr_module_toggle',module_id:'iranian_currency',enable:'0',confirm:'1'}
            });
            check(!res.ok(),'Unauthenticated POST incorrectly returned success');
            record.checks.push('unauthorized_post_rejected');
          }finally{await guest.close();}
          await view(page);
          check(await card(page,'iranian_currency').locator('input[name="enable"]').inputValue()==='0',
            'Unauthenticated request changed module');
        }
      }else{
        // USED fixture has been deleted by WP-CLI; national_id is now UNUSED.
        let url=await toggle(page,'national_id','0');notice(url,'disabled');
        check(await page.locator('.pgr-module-decision').count()===0,'UNUSED incorrectly requires second stage');
        check(await card(page,'national_id').locator('input[name="enable"]').inputValue()==='1',
          'UNUSED direct disable failed');
        await capture(page,record,'disabled',width);
        await toggle(page,'national_id','1');
        check(await card(page,'national_id').locator('input[name="enable"]').inputValue()==='0',
          'National ID state not restored');
        record.checks.push('unused_direct_disabled','national_id_restored');
      }
      record.status='PASS';
    }catch(e){failed=true;record.error=String(e.stack||e);}
    finally{report.cases.push(record);await context.close();}
  }
}finally{
  report.status=failed?'INCOMPLETE':'CAPTURED';
  fs.writeFileSync(path.join(out,'decision-'+key+'.json'),JSON.stringify(report,null,2)+'\n');
  await browser.close();
}
if(failed)throw Error('Licensed module-disable UX failed: '+key);
console.log('MODULE_DISABLE_DECISION_PASS '+key+' cases='+report.cases.length);
