import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
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
const decisionTitle=lang==='fa_IR'?'تأیید غیرفعال‌سازی ماژول':'Confirm module disable';
const decisionRisk=lang==='fa_IR'
  ?'مشخص نشده است که این ماژول در فرم‌ها یا قابلیت‌های موجود استفاده نمی‌شود. غیرفعال‌سازی آن ممکن است بر عملکرد موجود اثر بگذارد.'
  :'Safe non-use could not be established. Disabling this module could affect existing forms or functionality.';
const decisionPending=lang==='fa_IR'
  ?'هنوز وضعیت ماژول تغییر نکرده است. فقط در صورت پذیرش این احتمال، تأیید کنید.'
  :'No module state has changed yet. Confirm only if you accept the possible consequences.';
const invalidConfirmationNotice=lang==='fa_IR'
  ?'این درخواست تأیید دیگر معتبر نیست. از کارت ماژول، درخواست جدیدی آغاز کنید.'
  :'This disable confirmation is no longer valid. Start a new request from the module card.';
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
// Structural regression oracle for authentic rendered WordPress rows.
const expectedIds=['national_id','jalali_date','jalali_presentation','iranian_address',
  'digit_normalization','iranian_currency','structured_scanner'];
async function inventoryAudit(page,target=null){
  return page.evaluate(({expectedIds,target})=>{
    const errors=[],ids=[];
    const rows=[...document.querySelectorAll('.pgr-module-list > article.pgr-module-card')];
    if(document.querySelectorAll('.pgr-module-grid.pgr-module-list[role=list]').length!==1)errors.push('list_missing');
    for(const row of rows){
      const d=row.querySelector('.pgr-module-card__details'),c=row.querySelector('.pgr-module-card__controls');
      const h=d?.querySelector('.pgr-module-card__heading h2'),second=d?.querySelector('.pgr-module-card__heading .pgr-module-card__secondary');
      const desc=d?.querySelector(':scope > p:not(.pgr-module-card__secondary):not(.pgr-module-card__feature)');
      const desc2=d?.querySelector(':scope > p.pgr-module-card__secondary');
      const id=d?.querySelector(':scope > code[dir=ltr]')?.textContent?.trim();
      ids.push(id);
      if(!h?.textContent?.trim()||!second?.textContent?.trim()||!desc?.textContent?.trim()||!desc2?.textContent?.trim())
        errors.push('bilingual_content_missing:'+id);
      const forms=[...(c?.querySelectorAll('form[action$="admin-post.php"]')||[])],
        form=forms[0],button=form?.querySelector('button[type=submit]'),
        help=c?.querySelector('a.button[href*="persian-gravityforms-help"]'),
        state=c?.querySelector('.pgr-badge[data-state]')?.dataset.state,
        action=form?.querySelector('input[name=enable]')?.value;
      if(forms.length!==1||!button||!help||form?.querySelector('input[name=module_id]')?.value!==id)
        errors.push('missing_or_wrong_action:'+id);
      if(!['AVAILABLE','DISABLED'].includes(state)||(state==='AVAILABLE'?action!=='0':action!=='1'))
        errors.push('state_action_mismatch:'+id);
      for(const element of [button,help]){
        const refs=(element?.getAttribute('aria-labelledby')||'').trim().split(/\s+/);
        if(!element?.id||!h?.id||!refs.includes(element.id)||!refs.includes(h.id)||
          !refs.every(v=>document.getElementById(v)))
          errors.push('missing_associated_accessible_label:'+id);
      }
      if(id==='jalali_presentation'&&(!d.textContent.includes('{pgr_jdate_created_date}')||
        !help.href.includes('#jalali-presentation')))errors.push('g011_example_help_lost');
      const rr=row.getBoundingClientRect(),rc=c?.getBoundingClientRect();
      if(!rc||rc.left<rr.left-1||rc.right>rr.right+1||rc.top<rr.top-1||rc.bottom>rr.bottom+1)
        errors.push('action_outside_row:'+id);
    }
    if(JSON.stringify(ids)!==JSON.stringify(expectedIds.filter(id=>id!==target)))
      errors.push('incorrect_inventory_order_or_module_identity');
    const decisions=[...document.querySelectorAll('.pgr-module-decision')];
    if(decisions.length!==(target?1:0))errors.push('duplicate_or_missing_decision');
    if(target && (decisions[0]?.querySelector('input[name=module_id]')?.value!==target||
      decisions[0]?.querySelectorAll('input[name=confirm][value="1"]').length!==1||
      decisions[0]?.querySelectorAll('.notice-warning[role=alert]').length!==1))
      errors.push('wrong_target_or_decision_form');
    if(rows.length+decisions.length!==7)errors.push('lost_or_duplicated_module_identity');
    if(document.documentElement.scrollWidth>innerWidth)errors.push('horizontal_document_overflow');
    return {errors,ids,rows:rows.length,decisions:decisions.length,
      scrollWidth:document.documentElement.scrollWidth,width:innerWidth,
      states:rows.map(row=>({id:row.querySelector('code[dir=ltr]')?.textContent?.trim(),
        state:row.querySelector('.pgr-badge')?.dataset.state}))};
  },{expectedIds,target});
}
function assertInventory(result,context){check(result.errors.length===0,context+': '+result.errors.join(', '));}
function setDisposableG011(enabled){
  check(process.env.WU008_WP_PATH==='/tmp/wu008-wordpress'&&process.env.WU008_WP_CLI==='/tmp/wp-cli.phar',
    'Refusing state change outside pinned disposable WU008');
  const v=enabled?'true':'false';
  execFileSync('php',[process.env.WU008_WP_CLI,'--path='+process.env.WU008_WP_PATH,
    'eval','PGR_Module_Registry::set_enabled("jalali_presentation",'+v+'); if(PGR_Module_Registry::is_enabled("jalali_presentation")!=='+v+') throw new RuntimeException("G011 state not set");'],
    {timeout:20000,stdio:'pipe'});
}
let failed=false;
try {
  for(const width of [1280,782,768,390,320]){
    const height=width>=768?900:width===390?844:700;
    const context=await browser.newContext({viewport:{width,height},deviceScaleFactor:1});
    const page=await context.newPage();
    const record={width,height,status:'FAILED',images:[],checks:[]};
    let originalG011=null;
    try {
      await login(page);
      await view(page);
      const original=await inventoryAudit(page);
      assertInventory(original,'original WU008 inventory');
      originalG011=original.states.find(x=>x.id==='jalali_presentation')?.state==='AVAILABLE';
      record.original_g011_enabled=originalG011;
      if(originalG011){
        setDisposableG011(false);
        await view(page);
      }
      if (phase==='used' && width===1280) {
        // Prove the temporary MU guard really commits the WordPress admin
        // header early on this server. A legitimate Cancel must redirect
        // before this guard can run.
        const probe=await page.goto(home+'&pgr_wu008_probe_flush=1',{waitUntil:'domcontentloaded'});
        check(probe?.status()===200 &&
          await page.locator('#pgr-wu008-header-flush-probe').count()===1,
          'Forced early admin header flush instrumentation not active');
        record.checks.push('early_header_flush_probe_active');
        await view(page);
      }
      check(await page.locator('.pgr-module-decision').count()===0,'Normal Overview displays a decision');
      check(await page.locator('.pgr-module-card').count()===7,'Normal module grid lost a module');
      check(await card(page,'iranian_currency').count()===1,'Currency missing from normal grid');
      check(await card(page,'national_id').count()===1,'National ID missing from normal grid');
      const normalInventory=await inventoryAudit(page);
      assertInventory(normalInventory,'normal');
      check(normalInventory.states.filter(x=>x.state==='DISABLED').map(x=>x.id).join(',')==='jalali_presentation',
        'Expected default six-enabled/one-disabled WordPress state');
      record.normal_inventory=normalInventory;
      record.checks.push('seven_bilingual_module_rows');
      const intro=await measure(page);
      check(intro.document_scroll_width<=width,'Normal Overview horizontal overflow');
      record.normal=intro;
      await capture(page,record,'normal',width);
      // Verify focus navigation of a real row, not just a CSS selector or snapshot.
      const firstRow=page.locator('.pgr-module-list > article.pgr-module-card').first();
      await firstRow.locator('button[type=submit]').focus();
      await page.keyboard.press('Tab');
      const rowFocus=await firstRow.locator('a.button').evaluate(el=>({
        focused:document.activeElement===el,
        outline:getComputedStyle(el).outlineStyle,
        boxShadow:getComputedStyle(el).boxShadow
      }));
      check(rowFocus.focused,'Module toggle is not followed by its own Help in tab order');
      check(rowFocus.outline!=='none'||rowFocus.boxShadow!=='none',
        'Module Help has no visible keyboard focus');
      record.row_focus=rowFocus;
      record.checks.push('module_row_keyboard_focus_order');
      if(phase==='used'){
        // Verify actual WordPress all-enabled inventory, then always restore the G011 opt-in default.
        try{
          setDisposableG011(true);
          await view(page);
          const allEnabled=await inventoryAudit(page);
          assertInventory(allEnabled,'all enabled');
          check(allEnabled.states.every(x=>x.state==='AVAILABLE'),'All-enabled registry mismatch');
          await capture(page,record,'all-enabled',width);
          record.checks.push('all_enabled_inventory');
        }finally{
          setDisposableG011(false);
          await view(page);
          assertInventory(await inventoryAudit(page),'default restored');
        }
        if(width===320){
          await page.evaluate(()=>{
            const d=document.querySelector('.pgr-module-card__details');
            d.querySelector('h2').textContent+='کلمه‌بلند'.repeat(100);
            d.querySelector(':scope > p:not(.pgr-module-card__secondary):not(.pgr-module-card__feature)').textContent+='UnbrokenTranslatedLongContent'.repeat(70);
          });
          assertInventory(await inventoryAudit(page),'long translated content');
          await capture(page,record,'long',width);
          record.checks.push('long_content_no_overflow');
          await view(page);
        }
        if(width===1280){
          await page.locator('.pgr-module-card__controls button[type=submit]').first().evaluate(node=>node.remove());
          check((await inventoryAudit(page)).errors.some(x=>x.startsWith('missing_or_wrong_action')),
            'Negative control did not detect a missing module action');
          await view(page);
          record.checks.push('negative_missing_action_detected');
        }
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
        const decisionInventory=await inventoryAudit(page,'iranian_currency');
        assertInventory(decisionInventory,'one decision plus six module rows');
        record.decision_inventory=decisionInventory;
        record.checks.push('decision_seven_total_distinct_module_identities');
        if(width===1280){
          await decision.evaluate(node=>node.parentElement.append(node.cloneNode(true)));
          check((await inventoryAudit(page,'iranian_currency')).errors.includes('duplicate_or_missing_decision'),
            'Negative control failed to catch duplicate decision');
          await page.goto(url.href,{waitUntil:'domcontentloaded'});
          assertInventory(await inventoryAudit(page,'iranian_currency'),'restored decision');
          record.checks.push('negative_duplicate_decision_detected');
        }
        check(await page.locator('.pgr-warning-note').count()===0,'Competing old warning retained');
        check(await decision.locator('.notice-warning').count()===1,'Missing or duplicated risk notice');
        const body=await decision.innerText();
        for(const name of ['Iranian Rial / Toman','ریال و تومان ایران','IRR','IRT']){
          check(body.includes(name),'Missing bilingual identity or description '+name);
        }
        check(await decision.locator('h2').innerText()===decisionTitle,'Translated decision heading changed');
        check(body.includes(decisionRisk),'Translated decision risk warning missing or changed');
        check(body.includes(decisionPending),'Translated no-state-change warning missing or changed');
        record.checks.push('gettext_decision_heading_risk_pending_actions');
        check((await page.locator('.pgr-module-decision').boundingBox()).y <
          (await page.locator('.pgr-module-grid').boundingBox()).y,'Decision appears below module grid');
        const layout=await measure(page);
        check(layout.document_scroll_width<=width && layout.decision_width<=width,'Decision overflows viewport');
        record.decision=layout;
        const confirmButton=decision.locator('button[type=submit]');
        await confirmButton.focus();
        await page.keyboard.press('Tab');
        check(await decision.getByRole('link',{name:cancelLabel}).evaluate(el=>document.activeElement===el),
          'Cancel is not next in keyboard order');
        const focus=await decision.getByRole('link',{name:cancelLabel}).evaluate(el=>({
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
          check(await page.locator('.pgr-module-grid').count()===1,'Forged GET left the Overview page');
          assertInventory(await inventoryAudit(page),'invalid decision shows all seven modules');
          const warnings=await page.locator('.notice.notice-warning').allInnerTexts();
          const ownNotices=warnings.filter(text=>text.includes(invalidConfirmationNotice));
          check(ownNotices.length===1,
            'Expected exactly one translated invalid-confirmation notice: '+JSON.stringify({
              url:page.url(),warnings:warnings.slice(0,8)
            }));
        }
        record.checks.push('forged_get_rejected','mismatched_get_rejected','gettext_invalid_confirmation_notice');
        // Revisit genuine link, then exercise a non-mutating cancel.
        await page.goto(url.href,{waitUntil:'domcontentloaded'});
        check(await decision.count()===1,'Valid first-stage ticket no longer displayed');
        const cancelHref=await decision.getByRole('link',{name:cancelLabel}).getAttribute('href');
        check(cancelHref && cancelHref.includes('pgr_cancel_confirm='),'Preserved Cancel GET URL missing token');
        const [cancelResponse]=await Promise.all([
          page.waitForResponse(response=>response.url().includes('pgr_cancel_confirm=')),
          page.waitForNavigation({waitUntil:'domcontentloaded'}),
          decision.getByRole('link',{name:cancelLabel}).click()
        ]);
        check(cancelResponse.status()===302,
          'Cancel did not return HTTP 302 before WordPress admin header flush: '+cancelResponse.status());
        const location=cancelResponse.headers()['location'];
        check(location && new URL(location,cancelResponse.url()).href===home,
          'Cancel Location must be exact clean Overview, got '+String(location));
        check(!new URL(page.url()).searchParams.has('pgr_confirm_module'),'Cancel retained pending query');
        record.checks.push('cancel_http302_clean_location_before_header_flush');
        check(await decision.count()===0 && await card(page,'iranian_currency').count()===1,'Cancel did not restore normal grid');
        check(await card(page,'iranian_currency').locator('input[name="enable"]').inputValue()==='0','Cancel changed currency state');
        assertInventory(await inventoryAudit(page),'cancel restored complete inventory');
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
          const [denied]=await Promise.all([
            page.waitForNavigation({waitUntil:'domcontentloaded'}),
            pending.locator('button[type=submit]').click()
          ]);
          check(denied?.status()===403,'Invalid nonce did not receive the WordPress HTTP 403 refusal');
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
        assertInventory(await inventoryAudit(page),'confirmed state inventory');
        await capture(page,record,'confirmed',width);
        await toggle(page,'iranian_currency','1');
        check(await card(page,'iranian_currency').locator('input[name="enable"]').inputValue()==='0','Currency not restored');
        record.checks.push('unknown_second_post_disabled','currency_restored');
        // Unauthenticated request never succeeds.
        if(width===1280){
          const guest=await browser.newContext();
          try{
            const res=await guest.request.post(new URL('admin-post.php',runtime.admin_url).href,{
              maxRedirects:0,
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
        assertInventory(await inventoryAudit(page),'UNUSED direct disabled inventory');
        await capture(page,record,'disabled',width);
        await toggle(page,'national_id','1');
        check(await card(page,'national_id').locator('input[name="enable"]').inputValue()==='0',
          'National ID state not restored');
        record.checks.push('unused_direct_disabled','national_id_restored');
      }
      record.status='PASS';
    }catch(e){failed=true;record.error=String(e.stack||e);}
    finally{
      if(originalG011!==null){
        try{
          // Never leave the licensed WU008 host's original G011 state changed.
          setDisposableG011(originalG011);
          record.original_g011_restored=true;
        }catch(error){
          failed=true;
          record.status='FAILED';
          record.error=(record.error||'')+'; G011 original state restoration failed: '+String(error);
        }
      }
      report.cases.push(record);
      await context.close();
    }
  }
}finally{
  report.status=failed?'INCOMPLETE':'CAPTURED';
  fs.writeFileSync(path.join(out,'decision-'+key+'.json'),JSON.stringify(report,null,2)+'\n');
  await browser.close();
}
if(failed)throw Error('Licensed module-disable UX failed: '+key);
console.log('MODULE_DISABLE_DECISION_PASS '+key+' cases='+report.cases.length);
