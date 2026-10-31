import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const dir = process.env.WU008_ARTIFACT_DIR;
const manifestPath = process.env.WU008_MANIFEST_PATH;
const exactHead = process.env.WU008_PGR_SHA;
const locale = process.env.WU008_DELETE_LOCALE;
const password = process.env.WU008_ADMIN_PASSWORD;
if (!dir || !manifestPath || !password || !/^[a-f0-9]{40}$/.test(exactHead || '') ||
    !['fa_IR', 'en_US'].includes(locale)) {
  throw Error('Scanner delete proof requires genuine WU008 head, login and explicit locale.');
}
const runtime = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const fixture = JSON.parse(fs.readFileSync(path.join(dir, 'scanner-delete-fixture.json'), 'utf8'));
const subject = fixture.profiles[locale];
if (runtime.persiangravity_source_commit !== exactHead || !subject) throw Error('Wrong tested source or fixture');
const base = new URL(runtime.admin_url);
const listUrl = new URL('admin.php?page=persian-gravityforms-scanner-profiles', base).href;
const decisionUrl = listUrl + '&action=delete&profile=' + encodeURIComponent(subject.id);
const postUrl = new URL('admin-post.php', base).href;
const out = path.join(dir, 'scanner-delete-evidence');
fs.mkdirSync(out, { recursive: true });
const browser = await chromium.launch({ headless: true });
const assert = (ok, why) => { if (!ok) throw Error('SCANNER_DELETE: ' + why); };
const report = { evidence_class: 'AUTHENTIC_LICENSED_WU008_SCANNER_DELETE', exact_head: exactHead,
  locale, wordpress: '6.8.3', php: runtime.php_version, versions: runtime.versions,
  chromium: browser.version(), profile_id: subject.id, cases: [], limitations: [
    'Browser behavior is not proof of human comprehension or assistive technology narration',
    'Disposable installed packages and runtime only, not production data or production deployment'
  ] };
let screenshots = [];
const shot = async (page, stage, width) => {
  const filename = locale + '-' + width + '-' + stage + '.jpg';
  await page.screenshot({path: path.join(out, filename), type: 'jpeg', quality: 83, fullPage: true});
  screenshots.push(filename);
  return filename;
};
async function login(page) {
  const response = await page.goto(runtime.login_url, {waitUntil:'domcontentloaded'});
  assert(response?.ok(), 'Login route failed');
  await page.locator('#user_login').fill('runtime_admin');
  await page.locator('#user_pass').fill(password);
  await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}), page.locator('#wp-submit').click()]);
  assert(await page.locator('#wpadminbar').count() === 1, 'Authenticated WordPress UI unavailable');
}
async function open(page, url) {
  const response = await page.goto(url, {waitUntil:'domcontentloaded'});
  assert(response?.ok(), 'Admin route failed: ' + url);
  assert(!(page.url().includes('wp-login.php')), 'Session dropped');
}
async function noOverflow(page, width) {
  const measure = await page.evaluate(() => ({
    scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth,
    direction: getComputedStyle(document.documentElement).direction,
    css: [...document.styleSheets].some(x => (x.href || '').includes('/assets/css/pgr-admin.css'))
  }));
  assert(measure.scroll <= width + 2, 'Horizontal overflow: ' + JSON.stringify(measure));
  assert(measure.css, 'Plugin CSS absent');
  assert(measure.direction === (locale === 'fa_IR' ? 'rtl' : 'ltr'), 'Wrong document direction');
  return measure;
}

function rgb(css) {
  const channels = css.match(/^rgba?\(\s*(\d+)\s*[, ]+\s*(\d+)\s*[, ]+\s*(\d+)/);
  assert(channels !== null, 'Unrecognized computed CSS color: ' + css);
  return channels.slice(1,4).map(Number);
}
function luminance(css) {
  const channels = rgb(css).map(c => {
    const s = c / 255;
    return s <= 0.04045 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
}
function contrast(a, b) {
  const high = Math.max(luminance(a), luminance(b));
  const low = Math.min(luminance(a), luminance(b));
  return Math.round(((high + 0.05) / (low + 0.05)) * 100) / 100;
}
function isDestructiveRed(css) {
  const [r, g, b] = rgb(css);
  return r >= 100 && r > g * 1.8 && r > b * 1.6;
}
function isNeutral(css) {
  const channels = rgb(css);
  return Math.max(...channels) - Math.min(...channels) <= 24;
}
async function computedButton(locator) {
  return locator.evaluate(el => {
    const style = getComputedStyle(el);
    return {
      background: style.backgroundColor, foreground: style.color, border: style.borderTopColor,
      outline: style.outlineStyle, outlineWidth: parseFloat(style.outlineWidth),
      focusVisible: el.matches(':focus-visible')
    };
  });
}
function assertAccessibleButton(style, phase) {
  const ratio = contrast(style.foreground, style.background);
  assert(ratio >= 4.5, phase + ' text contrast < 4.5: ' + JSON.stringify({style, ratio}));
  return ratio;
}
function assertDestructiveButton(style, phase) {
  const ratio = assertAccessibleButton(style, phase);
  assert(isDestructiveRed(style.background), phase + ' is not filled destructive red (original WordPress blue-on-blue failure): ' +
    JSON.stringify(style));
  return ratio;
}
function assertNeutralCancel(style, phase) {
  const ratio = assertAccessibleButton(style, phase);
  assert(isNeutral(style.background), phase + ' Cancel background is not neutral: ' + JSON.stringify(style));
  return ratio;
}
function assertVisibleFocus(style, phase) {
  assert(style.focusVisible && style.outline !== 'none' && style.outlineWidth >= 2,
    phase + ' has no visible keyboard focus outline: ' + JSON.stringify(style));
}

const seenErrors = [];
try {
  for (const width of [1280, 390, 320]) {
    const context = await browser.newContext({
      viewport: {width, height: width === 1280 ? 900 : width === 390 ? 844 : 700},
      deviceScaleFactor: 1,
      javaScriptEnabled: width !== 320
    });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(String(error)));
    const caseInfo = {width, js_enabled: width !== 320, checks: [], screenshots: [], state: 'STARTED'};
    report.cases.push(caseInfo);
    await login(page);
    await open(page, listUrl);
    const selected = page.locator('article.pgr-profile-card').filter({
      has: page.locator('code[dir="ltr"]', {hasText: subject.id})
    });
    assert(await selected.count() === 1, 'Selected persisted custom card missing');
    assert(await selected.locator('a[href*="action=delete"]').count() === 1, 'Delete must be a GET link, not a POST');
    assert(await selected.locator('form input[value="pgr_scanner_profile_delete"]').count() === 0,
      'Destructive POST still present in profile list');
    const builtin = page.locator('article.pgr-profile-card').filter({has: page.locator('code[dir="ltr"]', {hasText:'sayad_v01'})});
    assert(await builtin.count() === 1 && await builtin.locator('a[href*="action=delete"]').count() === 0,
      'Built-in gained a deletion control');
    caseInfo.screenshots.push(await shot(page,'list',width));
    await Promise.all([
      page.waitForNavigation({waitUntil:'domcontentloaded'}),
      selected.locator('a[href*="action=delete"]').click()
    ]);
    const currentUrl = new URL(page.url());
    assert(currentUrl.searchParams.get('action') === 'delete' &&
      currentUrl.searchParams.get('profile') === subject.id, 'Delete selection did not navigate to the exact ID');
    const decision = page.locator('.pgr-profile-delete-decision');
    if (await decision.count() !== 1) {
      const adminText = (await page.locator('.pgr-admin').allTextContents()).join(' ').slice(0, 700);
      throw Error('SCANNER_DELETE: Focused server-rendered decision missing; url=' + currentUrl.href +
        '; admin_text=' + JSON.stringify(adminText));
    }
    assert(await decision.locator('h2').count() === 1, 'Decision heading missing');
    assert((await decision.textContent()).includes(subject.label), 'Exact display name missing');
    assert((await decision.locator('code[dir="ltr"]').textContent()).trim() === subject.id,
      'Immutable ID missing or incorrect');
    const warning = (await page.locator('#pgr-profile-delete-warning').textContent()).toLowerCase();
    assert(locale === 'fa_IR' ? (warning.includes('نگاشت') && warning.includes('قابل استفاده')) :
      (warning.includes('mapping') && warning.includes('unavailable')),
      'Localized mappings consequence not explained');
    assert(await page.locator('a:has-text("Create Profile")').count() === 0, 'Create competes with Delete');
    assert(await decision.locator('form input[name="action"][value="pgr_scanner_profile_delete"]').count() === 1,
      'Dedicated confirm form missing');
    const actionForm = decision.locator('form');
    const data = await actionForm.evaluate(form => Object.fromEntries(new FormData(form)));
    assert(data.profile_id === subject.id && data.confirmed_profile_label === subject.label && !!data._wpnonce,
      'Wrong profile identity or nonce in POST');
    assert(await actionForm.locator('button[type="submit"]').count() === 1, 'Explicit confirm action missing');
    assert(await decision.locator('a.button').count() === 1, 'Cancel navigation missing');
    const focused = actionForm.locator('button[type="submit"]');
    const cancel = decision.locator('a.button');
    const confirmDefault = await computedButton(focused);
    const cancelDefault = await computedButton(cancel);
    // These are real Chromium computed styles with WordPress core CSS loaded, not source-only assertions.
    const defaultRatio = assertDestructiveButton(confirmDefault, 'Confirm/default');
    const cancelRatio = assertNeutralCancel(cancelDefault, 'Cancel/default');
    const backgroundRatio = contrast(confirmDefault.background, cancelDefault.background);
    assert(backgroundRatio >= 3, 'Confirm/Cancel backgrounds indistinguishable: ' +
      JSON.stringify({confirmDefault,cancelDefault,backgroundRatio}));
    await focused.hover();
    await page.waitForTimeout(200); // Allow native WordPress hover transition to settle.
    const confirmHover = await computedButton(focused);
    const hoverRatio = assertDestructiveButton(confirmHover, 'Confirm/hover');
    assert(contrast(confirmDefault.background,confirmHover.background) >= 1.1,
      'Confirm hover fails to produce a visible state change');
    await cancel.hover();
    await page.waitForTimeout(200);
    const cancelHover = await computedButton(cancel);
    const cancelHoverRatio = assertNeutralCancel(cancelHover, 'Cancel/hover');

    await focused.focus();
    await page.keyboard.press('Tab');
    assert(await cancel.evaluate(el => document.activeElement === el),
      'Cancel is not the next keyboard action');
    const cancelFocus = await computedButton(cancel);
    assertVisibleFocus(cancelFocus, 'Cancel/focus');
    const cancelFocusRatio = assertNeutralCancel(cancelFocus, 'Cancel/focus');
    await page.keyboard.press('Shift+Tab');
    assert(await focused.evaluate(el => document.activeElement === el),
      'Keyboard Shift+Tab did not return to Confirm');
    const confirmFocus = await computedButton(focused);
    assertVisibleFocus(confirmFocus, 'Confirm/focus');
    const focusRatio = assertDestructiveButton(confirmFocus, 'Confirm/focus');
    assert(contrast(confirmFocus.background,cancelFocus.background) >= 3,
      'Keyboard-focused destructive action is indistinguishable from Cancel');
    await page.keyboard.press('Tab');
    assert(await cancel.evaluate(el => document.activeElement === el),
      'Cancel did not retain its expected keyboard order');
    caseInfo.visual = {
      authority: 'chromium-computed-style-wordpress',
      confirm: {default: confirmDefault, hover: confirmHover, focus: confirmFocus},
      cancel: {default: cancelDefault, hover: cancelHover, focus: cancelFocus},
      contrast: {confirmDefault:defaultRatio, confirmHover:hoverRatio, confirmFocus:focusRatio,
        cancelDefault:cancelRatio, cancelHover:cancelHoverRatio, cancelFocus:cancelFocusRatio,
        backgrounds:backgroundRatio}
    };
    caseInfo.screenshots.push(await shot(page,'confirmation',width));
    caseInfo.measure = await noOverflow(page,width);
    caseInfo.checks.push('exact_name_and_id', 'mapping_warning', 'no_competing_create', 'explicit_actions',
      'keyboard_order_and_visible_focus', 'destructive_visual_default_hover_focus_contrast',
       'server_rendered_get', 'no_horizontal_overflow');
    // GET requests may route to the admin_post hook, but must never mutate registry state.
    const forgedGet = await page.request.get(postUrl + '?action=pgr_scanner_profile_delete&profile_id=' +
      encodeURIComponent(subject.id) + '&_wpnonce=' + encodeURIComponent(data._wpnonce));
    assert([200,302,400,403].includes(forgedGet.status()), 'Unexpected GET status');
    await open(page, decisionUrl);
    assert(await page.locator('.pgr-profile-delete-decision').count() === 1, 'Destructive GET deleted the profile');
    caseInfo.checks.push('destructive_get_no_mutation');
    // Cancel performs ordinary GET and must not submit or delete.
    await Promise.all([
      page.waitForNavigation({waitUntil:'domcontentloaded'}),
      page.locator('.pgr-profile-delete-decision a.button').click()
    ]);
    assert(new URL(page.url()).searchParams.get('action') !== 'delete', 'Cancel did not exit the decision');
    assert(await page.locator('article.pgr-profile-card').filter({
      has: page.locator('code[dir="ltr"]', {hasText: subject.id})
    }).count() === 1, 'Cancel did not return to the selected persisted profile');
    caseInfo.screenshots.push(await shot(page,'cancel',width));
    caseInfo.checks.push('cancel_without_deleting');
    await open(page, listUrl + '&action=delete&profile=missing_wu008');
    assert(await page.locator('.pgr-profile-delete-decision').count() === 0 &&
      await page.locator('.pgr-admin form').count() === 0, 'Missing profile exposes POST');
    caseInfo.screenshots.push(await shot(page,'missing',width));
    await open(page, listUrl + '&action=delete&profile=sayad_v01');
    assert(await page.locator('.pgr-profile-delete-decision').count() === 0 &&
      await page.locator('.pgr-admin form').count() === 0, 'Built-in exposes POST');
    caseInfo.screenshots.push(await shot(page,'built-in',width));
    caseInfo.checks.push('stale_missing_fail_closed', 'built_in_non_deletable');
    if (width === 320) {
      // All earlier captures are non-mutating. Use the narrow no-JS browser to verify POST safety.
      const responseWrong = await page.request.post(postUrl, {form:{...data, profile_id: fixture.profiles[locale === 'fa_IR' ? 'en_US' : 'fa_IR'].id}});
      assert(responseWrong.status() === 403, 'Cross-profile nonce forgery was accepted');
      const responseNoNonce = await page.request.post(postUrl, {form:{...data, _wpnonce:''}});
      assert(responseNoNonce.status() === 403, 'Missing nonce accepted');
      const responseWrongLabel = await page.request.post(postUrl, {form:{...data, confirmed_profile_label:'unexpected label'}});
      assert(responseWrongLabel.url().includes('pgr_notice=delete_error'), 'Wrong visible name accepted');
      const responseBuiltIn = await page.request.post(postUrl, {form:{...data, profile_id:'sayad_v01'}});
      assert(responseBuiltIn.status() === 403, 'Built-in forgery accepted');
      const anonymous = await browser.newContext();
      const anonResponse = await anonymous.request.post(postUrl, {form:data});
      assert([400,401,403].includes(anonResponse.status()), 'Anonymous destructive POST accepted');
      await anonymous.close();
      await open(page, decisionUrl);
      assert(await page.locator('.pgr-profile-delete-decision').count() === 1,
        'Negative controls changed stored data');
      caseInfo.checks.push('wrong_id_nonce_rejected','missing_nonce_rejected','wrong_label_rejected',
        'built_in_post_rejected','anonymous_post_rejected','no_js_navigation_and_form');
      const submitted = await actionForm.count(); // Refresh the form locator after navigation.
      assert(submitted === 1, 'Final confirmation form unavailable');
      await Promise.all([
        page.waitForNavigation({waitUntil:'domcontentloaded'}),
        actionForm.locator('button[type="submit"]').click()
      ]);
      assert(page.url().includes('pgr_notice=deleted'), 'Real delete did not report success');
      assert(await page.locator('article.pgr-profile-card').filter({hasText: subject.id}).count() === 0,
        'Deleted custom card still exists');
      caseInfo.screenshots.push(await shot(page,'deleted',width));
      const stale = await page.request.post(postUrl, {form:data});
      assert(stale.url().includes('pgr_notice=delete_error'), 'Stale confirmation was accepted');
      await open(page, decisionUrl);
      assert(await page.locator('.pgr-profile-delete-decision').count() === 0, 'Deleted item still has confirmation');
      caseInfo.screenshots.push(await shot(page,'stale',width));
      caseInfo.checks.push('real_post_deleted', 'repeat_post_refused', 'stale_get_refused');
    }
    assert(errors.length === 0, 'Browser exception: ' + errors.join('; '));
    await noOverflow(page,width);
    caseInfo.state = 'PASS';
    seenErrors.push(...errors);
    await context.close();
  }
  report.state = 'PASS';
} finally {
  await browser.close();
  report.screenshots = screenshots;
  fs.writeFileSync(path.join(out, locale + '-report.json'), JSON.stringify(report,null,2) + '\n');
}
if (report.state !== 'PASS') throw Error('Scanner decision qualification incomplete');
console.log('Scanner deletion ' + locale + ' PASS: ' + report.cases.length + ' real browser viewports');
