import fs from 'node:fs';
import path from 'node:path';
import {execFileSync} from 'node:child_process';
import {chromium} from 'playwright';

// Qualification-only. It tests the exact production Registry and handler without patching them.
const root = process.env.WU008_WP_PATH;
const wpcli = process.env.WU008_WP_CLI;
const dir = process.env.WU008_ARTIFACT_DIR;
const manifest = process.env.WU008_MANIFEST_PATH;
const sha = process.env.WU008_PGR_SHA;
const password = process.env.WU008_ADMIN_PASSWORD;
const reviewed = '11e2c5974d37878ec6f2398aa99d5dc147629e11';
if (!root || !wpcli || !dir || !manifest || !password || !/^[0-9a-f]{40}$/.test(sha || '')) {
  throw Error('SCANNER_INCAR: disposable WU008 runtime/identity absent');
}
const runtime = JSON.parse(fs.readFileSync(manifest, 'utf8'));
if (runtime.persiangravity_source_commit !== sha) throw Error('SCANNER_INCAR: runtime source mismatch');
const cases = ['unchanged', 'missing', 'changed_label', 'same_label', 'enabled_only', 'output_label_only', 'fresh_b', 'identical_content'];
const out = path.join(dir, 'scanner-incarnation-evidence');
fs.mkdirSync(out, {recursive: true});
const assert = (cond, detail) => {if (!cond) throw Error('SCANNER_INCAR: '+detail);};
const invoke = (action, scenario) => {
  const output = execFileSync('php',
    [wpcli, '--path='+root, 'eval-file', 'tests/real-integration/scanner-profile-incarnation-fixture.php'],
    {cwd:process.cwd(),encoding:'utf8',env:{...process.env,WU008_INCAR_ACTION:action,WU008_INCAR_CASE:scenario}});
  const state=JSON.parse(output.trim());
  assert(state.action===action && state.case===scenario, 'out-of-order real WP state: '+action+'/'+scenario);
  return state;
};
const base = new URL(runtime.admin_url);
const listUrl = new URL('admin.php?page=persian-gravityforms-scanner-profiles',base).href;
const postUrl = new URL('admin-post.php',base).href;
const decisionUrl = listUrl + '&action=delete&profile=alpha';
const browser = await chromium.launch({headless:true});
const report = {
  evidence_class:'AUTHENTIC_WORDPRESS_GFAPI_SCANNER_INCAR_QUALIFICATION',
  qualification_head:sha, reviewed_production_head:reviewed,
  wordpress:'6.8.3', php:runtime.php_version, versions:runtime.versions,
  chromium:browser.version(), session:'same_authenticated_browser_context',
  nonce:'genuine_wp_nonce_field_and_admin_post_check_admin_referer_not_stubbed',
  cases:[], limitations:[
    'Disposable WordPress only, not production; no production plugin source changes',
    'WP-CLI uses the canonical Registry API for independent deletion and recreation',
    'No raw session cookies or nonces are stored in evidence',
    'Runtime observation alone does not establish whether incarnation protection is Owner-approved policy',
    'Identical-content recreation is not distinguishable by a deterministic content fingerprint',
    'A replacement racing between fresh state check and Registry deletion remains unverified'
  ]
};
try {
  const context = await browser.newContext({viewport:{width:1024,height:850}});
  const page = await context.newPage();
  let response = await page.goto(runtime.login_url,{waitUntil:'domcontentloaded'});
  assert(response?.ok(),'WordPress login screen unavailable');
  await page.locator('#user_login').fill('runtime_admin');
  await page.locator('#user_pass').fill(password);
  await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('#wp-submit').click()]);
  assert(await page.locator('#wpadminbar').count()===1,'WordPress administrator authentication absent');
  const sessionCookies = await context.cookies();
  assert(sessionCookies.some(x=>x.name.startsWith('wordpress_logged_in_')),'No genuine logged-in WordPress cookie');

  for (const scenario of cases) {
    const item = {case:scenario, steps:[], state:'STARTED'};
    report.cases.push(item);
    const created = invoke('create_a',scenario);
    assert(created.exists && created.id==='alpha' && created.label==='Alpha' &&
      created.trim===true && created.keys.join(',')==='original', 'A not stored by Registry');
    item.steps.push('genuine_registry_created_A');
    response=await page.goto(decisionUrl,{waitUntil:'domcontentloaded'});
    assert(response?.ok() && !page.url().includes('wp-login.php'),'auth session or real decision unavailable');
    const decision=page.locator('.pgr-profile-delete-decision');
    assert(await decision.count()===1,'original server-rendered decision missing');
    assert((await decision.locator('code[dir="ltr"]').textContent()).trim()==='alpha','wrong selected ID');
    assert((await decision.textContent()).includes('Alpha'),'original displayed label missing');
    const payload=await decision.locator('form').evaluate(f=>Object.fromEntries(new FormData(f)));
    assert(payload.action==='pgr_scanner_profile_delete' && payload.profile_id==='alpha' &&
      payload.confirmed_profile_label==='Alpha' && /^[a-f0-9]{64}$/.test(payload.confirmed_profile_fingerprint || '') &&
      typeof payload._wpnonce==='string' && payload._wpnonce.length>5,
      'real WordPress snapshot-bound identity/nonce not captured');
    item.steps.push('original_GET_decision_and_nonce_preserved_in_memory');
    item.nonce_present=true;
    item.images=[];
    const initial=scenario+'-original-confirmation.jpg';
    await page.screenshot({path:path.join(out,initial),type:'jpeg',quality:80,fullPage:true});
    item.images.push(initial);

    let beforePost=created;
    if (scenario!=='unchanged') {
      const deletion=invoke('delete_a',scenario);
      assert(!deletion.exists && deletion.gfapi_mapping_unchanged, 'independent legitimate delete A failed');
      item.steps.push('independent_canonical_registry_delete_A');
      beforePost=deletion;
    }
    if (scenario!=='unchanged' && scenario!=='missing') {
      const recreated=invoke('recreate_b',scenario);
      const expectedLabel=scenario==='changed_label'?'Beta':'Alpha';
      assert(recreated.exists && recreated.label===expectedLabel && recreated.gfapi_mapping_unchanged,
        'B was not persisted as a valid same-ID replacement');
      if (scenario==='identical_content') {
        assert(recreated.enabled===true && recreated.trim===true && recreated.normalize===true &&
          recreated.keys.join(',')==='original' && recreated.outputs?.[0]?.label==='Original',
          'identical-content recreation test invalid');
      } else if (scenario==='enabled_only') {
        assert(recreated.enabled===false && recreated.keys.join(',')==='original' &&
          recreated.trim===true, 'enabled-only change did not persist');
      } else if (scenario==='output_label_only') {
        assert(recreated.enabled===true && recreated.keys.join(',')==='original' &&
          recreated.outputs?.[0]?.label==='Replacement output label', 'output-label-only change did not persist');
      } else {
        assert(recreated.trim===false && recreated.normalize===false &&
          recreated.keys.join(',')==='replacement', 'different executable incarnation did not persist');
      }
      beforePost=recreated;
      item.steps.push('different_executable_definition_B_persisted_with_same_alpha_ID');
      const recreatedShot=scenario+'-recreated-B-before-old-POST.jpg';
      await page.goto(decisionUrl,{waitUntil:'domcontentloaded'});
      assert(await page.locator('.pgr-profile-delete-decision').count()===1,'B real decision missing');
      await page.screenshot({path:path.join(out,recreatedShot),type:'jpeg',quality:80,fullPage:true});
      item.images.push(recreatedShot);
    }
    item.before_stale_post=beforePost;
    // The exact POST form data obtained from A is submitted after independent Registry mutation.
    // Browser context, authenticated cookie and the original nonce are unchanged.
    const staleResponse=await page.request.post(postUrl,{form:payload,maxRedirects:10});
    const location=new URL(staleResponse.url());
    const notice=location.searchParams.get('pgr_notice');
    const after=invoke('inspect',scenario);
    assert(after.gfapi_mapping_unchanged===true,'real mapped Gravity Forms field changed');
    const expectedNotice=['unchanged','identical_content'].includes(scenario)?'deleted':'delete_error';
    assert(notice===expectedNotice,
      'Unexpected original nonce POST response: '+scenario+' actual '+notice+' expected '+expectedNotice);
    const expectedExist=!['unchanged','missing','identical_content'].includes(scenario);
    assert(after.exists===expectedExist,
      'Unexpected Registry state after old A POST '+scenario+': '+JSON.stringify(after));
    if (expectedExist) {
      assert(after.label===beforePost.label && after.enabled===beforePost.enabled &&
        after.trim===beforePost.trim && after.normalize===beforePost.normalize &&
        JSON.stringify(after.outputs)===JSON.stringify(beforePost.outputs),
        'recreated/modified B was changed by A confirmation: '+scenario);
    }
    item.steps.push('old_A_nonce_post_sent_via_same_authenticated_WordPress_session');
    item.response={status:staleResponse.status(),notice};
    item.after_post=after;
    item.original_nonce_still_verified=(notice==='deleted' || notice==='delete_error');
    item.b_deleted_by_A_confirmation=scenario==='same_label'?(!after.exists):null;
    if (scenario==='fresh_b') {
      // An original A POST failed. A newly displayed B confirmation must still work.
      await page.goto(decisionUrl,{waitUntil:'domcontentloaded'});
      const refreshed=page.locator('.pgr-profile-delete-decision form');
      assert(await refreshed.count()===1,'fresh B GET confirmation unavailable');
      const newPayload=await refreshed.evaluate(form=>Object.fromEntries(new FormData(form)));
      assert(newPayload.profile_id==='alpha' && newPayload.confirmed_profile_label==='Alpha' &&
        /^[a-f0-9]{64}$/.test(newPayload.confirmed_profile_fingerprint || '') &&
        newPayload.confirmed_profile_fingerprint!==payload.confirmed_profile_fingerprint,
        'Fresh B nonce is not distinct from stale A snapshot');
      const newResponse=await page.request.post(postUrl,{form:newPayload,maxRedirects:10});
      const freshNotice=new URL(newResponse.url()).searchParams.get('pgr_notice');
      assert(freshNotice==='deleted','fresh B confirmation did not delete B');
      const afterFresh=invoke('inspect',scenario);
      assert(!afterFresh.exists && afterFresh.gfapi_mapping_unchanged===true,
        'fresh B deletion persistence or GFAPI mappings invalid');
      item.fresh_b={notice:freshNotice,deleted:true,gfapi_mapping_unchanged:true};
      item.steps.push('new_B_specific_confirmation_nonce_legitimately_deleted_B');
    }
    response=await page.goto(listUrl,{waitUntil:'domcontentloaded'});
    assert(response?.ok(),'post-state admin list unavailable');
    const finalShot=scenario==='fresh_b'?'fresh_b-after-new-POST.jpg':scenario+'-after-old-POST.jpg';
    await page.screenshot({path:path.join(out,finalShot),type:'jpeg',quality:80,fullPage:true});
    item.images.push(finalShot);
    const cleaned=invoke('cleanup',scenario);
    assert(!cleaned.exists && cleaned.gfapi_mapping_unchanged,'qualification cleanup failed');
    item.state='OBSERVED';
  }
  const byCase=Object.fromEntries(report.cases.map(x=>[x.case,x]));
  assert(byCase.unchanged.response.notice==='deleted' && !byCase.unchanged.after_post.exists,
    'normal unchanged confirmation control invalid');
  assert(byCase.missing.response.notice==='delete_error' && !byCase.missing.after_post.exists,
    'missing-profile guard invalid');
  assert(byCase.changed_label.response.notice==='delete_error' && byCase.changed_label.after_post.exists,
    'changed-label guard invalid');
  for (const name of ['same_label','enabled_only','output_label_only','fresh_b']) {
    assert(byCase[name].response.notice==='delete_error' && byCase[name].after_post.exists,
      'snapshot guard did not protect '+name);
  }
  assert(byCase.fresh_b.fresh_b?.deleted === true && byCase.fresh_b.fresh_b?.gfapi_mapping_unchanged === true,
    'fresh B legitimate confirmation was blocked');
  assert(byCase.identical_content.response.notice==='deleted' &&
    byCase.identical_content.before_stale_post.exists && !byCase.identical_content.after_post.exists,
    'identical content limitation was not honestly exercised');
  report.finding='BOUNDED_SNAPSHOT_REPAIR_VERIFIED';
  report.state='QUALIFICATION_COMPLETE';
  const lines=[
    '# Same-ID Scanner Profile recreation — independent WU008 runtime qualification','',
    'Reviewed production Head: \`'+reviewed+'\`',
    'Isolated test-only qualification Head: \`'+sha+'\`','',
    'Genuine logged-in WordPress/Chromium; original A nonce remains in the same session.',
    'Independent Registry API deletion/recreation; original mapped GF field checked through GFAPI.',
    'No source production code modified. All scenarios are disposable.','',
    '| Scenario | Original confirmation POST | Registry after POST |',
    '|---|---|---|',
    ...report.cases.map(c=>'| '+c.case+' | '+c.response.notice+' | '+(c.after_post.exists?c.after_post.label+' / '+c.after_post.keys.join(','):'absent')+' |'),
    '', 'Fresh B confirmation outcome: '+byCase.fresh_b.fresh_b.notice,
    '', '**Observed disposition:** '+report.finding,'',
    'Raw nonce and authenticated cookies are never saved.',''
  ];
  for(const c of report.cases) {
    lines.push('## '+c.case,'');
    for(const n of c.images) lines.push('['+n+']('+n+')','');
  }
  fs.writeFileSync(path.join(out,'README.md'),lines.join('\n'));
} finally {
  await browser.close();
  fs.writeFileSync(path.join(out,'report.json'),JSON.stringify(report,null,2)+'\n');
}
if(report.state!=='QUALIFICATION_COMPLETE')throw Error('SCANNER_INCAR: qualification incomplete');
console.log('SCANNER_INCAR_QUALIFICATION_COMPLETE disposition='+report.finding+
  ' cases='+report.cases.length+' source='+sha);
