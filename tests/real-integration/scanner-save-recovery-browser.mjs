import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const root = process.env.WU008_ARTIFACT_DIR;
const password = process.env.WU008_ADMIN_PASSWORD;
const head = process.env.WU008_PGR_SHA;
const mode = process.env.SCANNER_SAVE_MODE || 'baseline';
const locale = process.env.SCANNER_SAVE_LOCALE || 'fa_IR';
if (!root || !password || !head || !['baseline', 'repaired'].includes(mode) || !['fa_IR', 'en_US'].includes(locale)) {
  throw new Error('Pinned WU008 environment and explicitly selected qualification mode required');
}
const fixture = JSON.parse(fs.readFileSync(path.join(root, 'scanner-ui-readonly-evidence', 'fixture.json'), 'utf8'));
if (fixture.expected_source_commit !== head || fixture.wordpress !== '6.8.3' || fixture.gravityforms !== '3.1.1.1') {
  throw new Error('Cannot qualify an unmatched installed WordPress/Gravity Forms/plugin build');
}
const dir = path.join(root, 'scanner-save-recovery-evidence');
fs.mkdirSync(dir, { recursive: true });
const getState = (expr) => execFileSync('php', [
  process.env.WU008_WP_CLI,
  '--path=' + process.env.WU008_WP_PATH,
  'eval', expr
], { encoding: 'utf8', timeout: 30000 }).trim();
const registryHash = () => getState('echo hash("sha256", serialize(get_option(PGR_Scanner_Profile_Registry::OPTION)));');
const gfHash = () => getState('echo hash("sha256", serialize(GFAPI::get_form(' + Number(fixture.form_id) + ')));');
const current = id => getState('$p=PGR_Scanner_Profile_Registry::get(' + JSON.stringify(id) +
  '); echo wp_json_encode($p ? array("label"=>$p["label"],"revision"=>$p["revision"],"incarnation_id"=>$p["incarnation_id"]) : null);');
const base = new URL(fixture.admin_url);
const url = suffix => new URL('admin.php?page=persian-gravityforms-scanner-profiles' + suffix, base).href;
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
const report = { evidence_class: 'AUTHENTIC_SCANNER_SAVE_RECOVERY', head, mode, locale,
  wordpress: fixture.wordpress, gravityforms: fixture.gravityforms,
  scenarios: [], persisting_registry_data: 'DISPOSABLE_WU008_ONLY' };
const screenshotStates = [];
const capture = async name => {
  for (const width of [1280, 390, 320]) {
    await page.setViewportSize({ width, height: 900 });
    const filename = `${locale}-${name}-${width}.png`;
    await page.screenshot({ path: path.join(dir, filename), fullPage: true });
    screenshotStates.push({filename, width,
      direction: await page.evaluate(() => getComputedStyle(document.documentElement).direction),
      horizontalOverflow: await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth) });
  }
  await page.setViewportSize({width: 1280, height: 900});
};
const evidence = (name, before, after, details) => report.scenarios.push({ name, registry_before: before,
  registry_after: after, gf_mapping_unchanged: details.gf_mapping_unchanged, observed: details.observed });
try {
  await page.goto(fixture.login_url);
  await page.locator('#user_login').fill('runtime_admin');
  await page.locator('#user_pass').fill(password);
  await Promise.all([page.waitForNavigation(), page.locator('#wp-submit').click()]);
  await page.goto(url('&action=new'));
  await page.locator('.pgr-profile-form').waitFor();
  const observedDir = await page.evaluate(() => getComputedStyle(document.documentElement).direction);
  if (observedDir !== (locale === 'fa_IR' ? 'rtl' : 'ltr')) throw Error('Locale/direction mismatch: ' + locale + '/' + observedDir);
  const before = registryHash(), gfBefore = gfHash();
  await page.locator('input[name="profile[id]"]').fill('sayad_v01');
  await page.locator('input[name="profile[label]"]').fill('Unsaved synthetic browser create value');
  await page.locator('input[name="profile[outputs][0][key]"]').fill('example_key');
  await page.locator('input[name="profile[outputs][0][label]"]').fill('Unsaved synthetic output');
  await Promise.all([page.waitForNavigation(), page.locator('.pgr-profile-form input[type="submit"]').click()]);
  if (mode === 'baseline') {
    if (!page.url().includes('pgr_notice=save_error') || await page.locator('.pgr-profile-form').count() !== 0) {
      throw new Error('Old failure path did not redirect to list and discard editable POST form as expected');
    }
    await capture('before-invalid-create');
    evidence('invalid_create_before', before, registryHash(), {
      gf_mapping_unchanged: gfHash() === gfBefore, observed: 'REDIRECT_LIST_GENERIC_NOTICE_NO_EDITABLE_POST_VALUES' });
    if (registryHash() !== before || gfHash() !== gfBefore) throw new Error('Failed create changed Registry or GFAPI mapping');
  } else {
    if (!page.url().includes('page=persian-gravityforms-scanner-profiles') ||
        await page.locator('.pgr-profile-form').count() !== 1 ||
        await page.locator('input[name="profile[label]"]').inputValue() !== 'Unsaved synthetic browser create value' ||
        await page.locator('input[name="profile[id]"]').inputValue() !== 'sayad_v01') {
      console.error('SCANNER_SAVE_CREATE_DIAGNOSTIC', JSON.stringify({
        url: page.url(), formCount: await page.locator('.pgr-profile-form').count(),
        displayName: await page.locator('input[name="profile[label]"]').count()
          ? await page.locator('input[name="profile[label]"]').inputValue() : null,
        submittedId: await page.locator('input[name="profile[id]"]').count()
          ? await page.locator('input[name="profile[id]"]').inputValue() : null,
        errorNotice: await page.locator('.notice-error').allInnerTexts(),
        pageHeading: await page.locator('h1').allInnerTexts(),
      }));
      throw new Error('Failed Create did not retain original editable submission on same WordPress screen');
    }
    if (registryHash() !== before || gfHash() !== gfBefore) throw new Error('Failed Create changed persisted state');
    await capture('after-invalid-create');
    const err = await page.locator('#pgr-profile-save-error').innerText();
    if (locale === 'fa_IR' ? !/[آ-ی]/.test(err) : !err.includes('not saved')) throw Error('Expected translated actionable save error missing');
    evidence('invalid_create_after', before, registryHash(), {
      gf_mapping_unchanged: gfHash() === gfBefore, observed: 'INLINE_EDITABLE_RETAINED_VALUES_AND_ERROR' });
    await page.locator('input[name="profile[id]"]').fill('wu008_save_recovery_' + (locale === 'fa_IR' ? 'fa' : 'en'));
    await Promise.all([page.waitForNavigation(), page.locator('.pgr-profile-form input[type="submit"]').click()]);
    if (!page.url().includes('pgr_notice=saved') || !JSON.parse(current('wu008_save_recovery_' + (locale === 'fa_IR' ? 'fa' : 'en')))) {
      throw new Error('Corrected create retry did not persist exactly once');
    }
    evidence('corrected_create_retry', before, registryHash(), {
      gf_mapping_unchanged: gfHash() === gfBefore, observed: 'RETRY_SAVED' });
    await page.goto(url('&action=edit&profile=wu008_ui_long_label'));
    await page.locator('.pgr-profile-form').waitFor();
    const editBefore = registryHash();
    const originalKey = await page.locator('input[name="profile[outputs][0][key]"]').inputValue();
    await page.locator('input[name="profile[label]"]').fill('Unsaved synthetic edit label');
    await page.locator('input[name="profile[outputs][0][label]"]').fill('Unsaved synthetic edit output');
    await page.locator('input[name="profile[outputs][0][key]"]').fill('changed_immutable_key');
    await Promise.all([page.waitForNavigation(), page.locator('.pgr-profile-form input[type="submit"]').click()]);
    if (await page.locator('.pgr-profile-form').count() !== 1 ||
        await page.locator('input[name="profile[label]"]').inputValue() !== 'Unsaved synthetic edit label' ||
        await page.locator('input[name="profile[outputs][0][key]"]').inputValue() !== 'changed_immutable_key' ||
        registryHash() !== editBefore) throw new Error('Rejected edit lost values or changed Registry');
    await capture('after-invalid-edit');
    evidence('invalid_edit_retention', editBefore, registryHash(), {
      gf_mapping_unchanged: gfHash() === gfBefore, observed: 'IMMUTABLE_CONTRACT_REJECTED_WITH_EDITABLE_VALUES' });
    await page.locator('input[name="profile[outputs][0][key]"]').fill(originalKey);
    await Promise.all([page.waitForNavigation(), page.locator('.pgr-profile-form input[type="submit"]').click()]);
    if (!page.url().includes('pgr_notice=saved') || !JSON.parse(current('wu008_ui_long_label'))?.label.includes('Unsaved synthetic edit label'))
      throw new Error('Corrected edit retry failed');
    evidence('corrected_edit_retry', editBefore, registryHash(), {
      gf_mapping_unchanged: gfHash() === gfBefore, observed: 'RETRY_EDIT_SAVED' });
    await page.goto(url('&action=edit&profile=wu008_ui_long_label'));
    await page.locator('.pgr-profile-form').waitFor();
    await page.locator('input[name="profile[label]"]').fill('Stale unsaved client label');
    const concurrent = getState('$p=PGR_Scanner_Profile_Registry::get("wu008_ui_long_label"); $p["label"]="Newer committed server label"; $r=PGR_Scanner_Profile_Registry::save_custom_profile($p,$p["id"],$p["revision"],$p["incarnation_id"]); if(!$r["success"])throw new Exception("Concurrent edit preparation failed"); echo "ok";');
    if (concurrent !== 'ok') throw new Error('Concurrent revision preparation failed');
    const newerHash = registryHash();
    await Promise.all([page.waitForNavigation(), page.locator('.pgr-profile-form input[type="submit"]').click()]);
    if (await page.locator('.pgr-profile-form').count() !== 1 ||
        await page.locator('input[name="profile[label]"]').inputValue() !== 'Stale unsaved client label' ||
        JSON.parse(current('wu008_ui_long_label'))?.label !== 'Newer committed server label' ||
        registryHash() !== newerHash) throw new Error('Stale edit incorrectly overwrote newer Registry state or lost unsaved values');
    await capture('after-stale-edit');
    evidence('stale_edit_conflict', newerHash, registryHash(), {
      gf_mapping_unchanged: gfHash() === gfBefore, observed: 'REFUSED_STALE_CAS_SAVED_NEWER_VERSION_UNCHANGED' });
  }
  if (report.scenarios.some(x => !x.gf_mapping_unchanged)) throw new Error('GFAPI mapping changed');
  report.screenshots = screenshotStates;
  report.status = 'PASS';
} finally {
  fs.writeFileSync(path.join(dir, 'report-' + locale + '.json'), JSON.stringify(report, null, 2) + '\n');
  await browser.close();
}
