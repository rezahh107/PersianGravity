/**
 * Evidence-only authenticated capture of PersianGravity administration.
 * Runs solely against WU008's disposable, exact-Head licensed runtime.
 */
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const required = ['WU008_ARTIFACT_DIR', 'WU008_MANIFEST_PATH', 'WU008_ADMIN_PASSWORD',
  'WU008_PGR_SHA', 'WU008_WP_CLI', 'WU008_WP_PATH'];
for (const key of required) {
  if (!process.env[key]) throw Error('Missing WU008 evidence prerequisite: ' + key);
}
const output = process.env.WU008_ARTIFACT_DIR;
const head = process.env.WU008_PGR_SHA;
const manifest = JSON.parse(fs.readFileSync(process.env.WU008_MANIFEST_PATH, 'utf8'));
if (manifest.persiangravity_source_commit !== head ||
    manifest.versions?.persiangravity !== process.env.WU008_PGR_VERSION ||
    manifest.versions?.gravityforms !== process.env.WU008_GF_VERSION) {
  throw Error('Installed plugin identity or licensed Gravity Forms differs from exact PR Head');
}
const adminBase = new URL(manifest.admin_url);
const safeOrigin = adminBase.origin;
const pages = [
  { name: 'overview', slug: 'persian-gravityforms', selector: '.pgr-module-card', minCount: 7 },
  { name: 'scanner-profiles', slug: 'persian-gravityforms-scanner-profiles', selector: '.pgr-profile-card', minCount: 1 },
  { name: 'settings', slug: 'persian-gravityforms-settings', selector: '.pgr-panel form[action="options.php"]', minCount: 1 },
  { name: 'system-status', slug: 'persian-gravityforms-system-status', selector: '.pgr-card-grid .pgr-card', minCount: 11 },
];
const viewports = {
  fa: [[1280, 900], [768, 800], [390, 800], [320, 800]],
  en: [[1280, 900], [390, 800]],
};
const evidence = {
  schema_version: '1.0.0', suite: 'PersianGravity admin UI evidence, not acceptance',
  source_sha: head, installed_plugin: { version: manifest.versions.persiangravity, source_sha: manifest.persiangravity_source_commit },
  environment: { wordpress: null, php: manifest.php_version, gravityforms: manifest.versions.gravityforms,
    gravityflow: manifest.versions.gravityflow, playwright: null, chromium: null },
  captures: [], missing: [], controls: [], anomalies: [], limitations: [
    'Images and DOM geometry are not keyboard or assistive-technology acceptance.',
    'Captures are from a disposable authenticated WU008 runtime, not a production installation.',
    'Visual overlap/clipping heuristics are observations, not a complete UX audit.',
  ], status: 'INCOMPLETE',
};
fs.mkdirSync(output, { recursive: true });
const save = () => fs.writeFileSync(path.join(output, 'pgr-admin-ux-capture.json'),
  JSON.stringify(evidence, null, 2) + '\n');
const assert = (condition, message) => { if (!condition) throw Error(message); };
const cli = (code) => execFileSync('php', [process.env.WU008_WP_CLI,
  '--path=' + process.env.WU008_WP_PATH, '--quiet', 'eval', code],
{ encoding: 'utf8', timeout: 45000 }).trim();
const stateCode = String.raw`$u=get_user_by("login","runtime_admin"); if (!$u) throw new Exception("Missing test user");
wp_set_current_user($u->ID);
echo wp_json_encode(array(
"site"=>get_locale(), "site_setting"=>get_option("WPLANG",""),
"user_locale"=>get_user_meta($u->ID,"locale",true),
"user_locale_exists"=>metadata_exists("user",$u->ID,"locale"),
"effective_user"=>get_user_locale($u->ID),
"modules"=>get_option("pgr_modules","__missing__"),
"profiles"=>get_option("pgr_scanner_profiles","__missing__"),
"settings"=>get_option("pgr_settings","__missing__"),
"wordpress"=>get_bloginfo("version"),"php"=>PHP_VERSION));`;
const state = () => JSON.parse(cli(stateCode));
const b64 = (value) => Buffer.from(JSON.stringify(value), 'utf8').toString('base64');
const restoreOption = (name, value) => {
  const encoded = b64(value);
  cli(value === '__missing__' ? 'delete_option("' + name + '");' :
    'update_option("' + name + '",json_decode(base64_decode("' + encoded + '"),true));');
};
const setUserLocale = (value, present = true) => {
  const encoded = b64(value);
  cli('$u=get_user_by("login","runtime_admin");' +
    (present ? 'update_user_meta($u->ID,"locale",json_decode(base64_decode("' + encoded + '"),true));'
      : 'delete_user_meta($u->ID,"locale");'));
};
const setLocale = (locale) => {
  cli('update_option("WPLANG","' + locale + '");');
  setUserLocale(locale);
  const check = state();
  assert(check.site === locale && check.effective_user === locale,
    'WordPress site and authenticated-user locale did not both switch to ' + locale);
  return check;
};
const urlFor = (item, args = {}) => {
  const url = new URL('admin.php', adminBase);
  url.searchParams.set('page', item.slug);
  for (const [key, value] of Object.entries(args)) url.searchParams.set(key, value);
  return url.href;
};
const shortUrl = (value) => {
  try {
    const u = new URL(value);
    if (u.origin !== safeOrigin) return 'EXTERNAL_REQUEST_REDACTED';
    return u.pathname + (u.searchParams.has('page') ? '?page=' + u.searchParams.get('page') : '');
  } catch { return 'UNPARSEABLE_URL'; }
};
const dimensions = (buffer) => {
  assert(buffer.subarray(1, 4).toString() === 'PNG', 'Screenshot is not a PNG');
  return { width: buffer.readUInt32BE(16), height: buffer.readUInt32BE(20), bytes: buffer.length };
};
let browser, context, page, original, currentLocale, runningErrors = [];
const note = (message) => { evidence.anomalies.push(message); console.error('CAPTURE WARNING ' + message); };
const go = async (url) => {
  runningErrors = [];
  const response = await page.goto(url, { waitUntil: 'domcontentloaded' });
  assert(response?.ok(), 'HTTP failure at ' + shortUrl(page.url()));
  await page.locator('#wpadminbar').waitFor({ state: 'attached' });
  await page.locator('#adminmenu').waitFor({ state: 'attached' });
  assert((await page.locator('body').getAttribute('class') || '').includes('wp-admin'), 'Not WordPress admin');
  assert(!page.url().includes('wp-login.php'), 'Login session missing');
};
const settle = async () => {
  await page.locator('#wpbody-content .wrap.pgr-admin').waitFor({ state: 'visible' });
  await page.evaluate(async () => {
    await document.fonts.ready;
    await Promise.all([...document.querySelectorAll('.pgr-admin img')].map(async (img) => {
      if (!img.complete) await new Promise((resolve) => { img.addEventListener('load', resolve, { once: true }); img.addEventListener('error', resolve, { once: true }); });
      if (img.complete && img.naturalWidth && img.decode) await img.decode().catch(() => {});
    }));
    await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
  });
};
const verify = async (item, lang, expectedState = 'default') => {
  assert(new URL(page.url()).origin === safeOrigin, 'Admin host origin differs from runtime manifest');
  assert(new URL(page.url()).searchParams.get('page') === item.slug, 'Wrong admin page slug');
  const { htmlLang, dir, title, primaryHeading, primaryDirection, moduleCount, matching } = await page.evaluate((slug) => {
    const root = document.querySelector('#wpbody-content .wrap.pgr-admin');
    const module = [...document.querySelectorAll('article.pgr-module-card')].find((card) =>
      card.querySelector('code[dir="ltr"]')?.textContent?.trim() === 'national_id');
    return {
      htmlLang: document.documentElement.lang,
      dir: document.documentElement.dir || getComputedStyle(document.body).direction,
      title: root?.querySelector('h1')?.textContent?.trim() || '',
      primaryHeading: module?.querySelector('h2')?.textContent?.trim() || '',
      primaryDirection: module?.querySelector('h2')?.getAttribute('dir') || null,
      moduleCount: document.querySelectorAll('article.pgr-module-card').length,
      matching: document.querySelectorAll(slug).length,
    };
  }, item.selector);
  const expectedDirection = lang === 'fa' ? 'rtl' : 'ltr';
  assert(htmlLang.toLowerCase().startsWith(lang), 'Wrong HTML language: ' + htmlLang);
  assert(dir === expectedDirection, 'Wrong HTML direction: ' + dir);
  assert(title.length > 0, 'Product page heading missing');
  assert(matching >= item.minCount || expectedState !== 'default',
    'Expected authentic page content missing: ' + item.name);
  if (item.name === 'overview') {
    assert(moduleCount === 7, 'Expected seven module cards');
    assert(primaryDirection === expectedDirection, 'Overview primary language not using user locale');
    assert(lang === 'fa' ? /[\u0600-\u06ff]/u.test(primaryHeading) :
      /^[^\u0600-\u06ff]+$/u.test(primaryHeading), 'Rendered primary label language mismatch');
  }
  if (item.name === 'system-status') {
    assert(await page.locator('.pgr-compatibility-status').count() === 1,
      'Expected real Gravity Flow compatibility section is absent');
  }
  if (expectedState === 'unknown-warning') {
    assert(await page.locator('.pgr-warning-note[role="alert"]').count() === 1,
      'UNKNOWN warning did not render from product');
    assert(new URL(page.url()).searchParams.get('pgr_confirm_module') === 'iranian_currency',
      'Wrong module warning target');
  }
  if (expectedState === 'national-id-inactive') {
    assert(await page.locator('.notice.notice-info.inline').count() === 1,
      'Inactive National ID informational notice missing');
  }
  if (expectedState === 'new-empty' || expectedState === 'new-populated') {
    assert(await page.locator('form.pgr-profile-form').count() === 1, 'Native new profile form missing');
  }
  return { html_lang: htmlLang, direction: dir, heading: title, primary_language: primaryHeading };
};
const measure = async () => page.evaluate(() => {
  const root = document.querySelector('#wpbody-content .wrap.pgr-admin');
  const rect = (el) => {
    const r = el.getBoundingClientRect();
    return { x: +r.x.toFixed(1), y: +r.y.toFixed(1),
      width: +r.width.toFixed(1), height: +r.height.toFixed(1),
      right: +r.right.toFixed(1) };
  };
  const visible = (el) => {
    const s = getComputedStyle(el);
    return !!el.getClientRects().length && s.visibility !== 'hidden' && s.display !== 'none';
  };
  const sample = (selector, max = 80) => [...root.querySelectorAll(selector)].filter(visible)
    .slice(0, max).map((el) => ({
      tag: el.tagName.toLowerCase(),
      type: el.getAttribute('type') || null, name: el.getAttribute('name') || null,
      label: (el.innerText || el.getAttribute('aria-label') || '').trim().slice(0, 85),
      bounds: rect(el),
      overflow: el.scrollWidth > el.clientWidth + 2,
      beyond_viewport: el.getBoundingClientRect().right > window.innerWidth + 2 ||
        el.getBoundingClientRect().left < -2,
    }));
  const text = sample('h1,h2,h3,.pgr-badge,.widefat,th,td,.pgr-output-row', 100);
  const controls = sample('button,a,input,select,textarea', 150);
  const tables = sample('table', 15);
  const css = root ? getComputedStyle(root) : null;
  return {
    viewport_width: window.innerWidth,
    document_width: document.documentElement.scrollWidth,
    horizontal_overflow_px: Math.max(0, document.documentElement.scrollWidth - window.innerWidth),
    main_content_bounds: root ? rect(root) : null,
    css: css ? { font_family: css.fontFamily, font_size: css.fontSize, direction: css.direction } : null,
    loaded_fonts: [...document.fonts].filter((font) => font.status === 'loaded').slice(0, 25)
      .map((font) => ({ family: font.family, weight: font.weight })),
    controls, text, tables,
    potential_clipping: [...text, ...controls].filter((el) => el.overflow || el.beyond_viewport).slice(0, 80),
  };
});
const capture = async (item, lang, width, height, stateName = 'default') => {
  await page.setViewportSize({ width, height });
  await settle();
  const verification = await verify(item, lang, stateName);
  const geometry = await measure();
  const filename = item.name + '-' + lang + '-' + width + '-' + stateName + '.png';
  const screenshot = await page.screenshot({ path: path.join(output, filename), fullPage: true });
  const image = dimensions(screenshot);
  assert(image.bytes > 1200 && image.width >= width,
    'Empty or wrong-width full-page screenshot: ' + filename);
  const record = {
    status: 'CAPTURED', page: item.name, state: stateName, locale: lang === 'fa' ? 'fa_IR' : 'en_US',
    effective_user_locale: currentLocale.effective_user, site_locale: currentLocale.site,
    source_sha: head, plugin_version: manifest.versions.persiangravity,
    url: shortUrl(page.url()), timestamp_utc: new Date().toISOString(),
    viewport: { width, height }, device_pixel_ratio: await page.evaluate(() => devicePixelRatio),
    screenshot: filename, screenshot_dimensions: image,
    verification, geometry, browser_issues: [...runningErrors],
  };
  if (width === 1280 || width === 390) {
    const viewportFilename = filename.replace(/\.png$/, '.viewport.png');
    const viewportImage = dimensions(await page.screenshot({ path: path.join(output, viewportFilename) }));
    assert(viewportImage.width === width && viewportImage.height === height,
      'Viewport screenshot dimensions mismatch: ' + viewportFilename);
    record.viewport_screenshot = viewportFilename;
    record.viewport_dimensions = viewportImage;
  }
  evidence.captures.push(record);
  console.log('CAPTURED ' + filename);
  return record;
};
const missing = (pageName, stateName, reason) => {
  evidence.missing.push({ page: pageName, state: stateName, status: 'NOT_CAPTURED', reason: String(reason) });
  note('NOT_CAPTURED ' + pageName + '/' + stateName + ': ' + reason);
};
const visit = async (item, lang, args = {}) => {
  await go(urlFor(item, args));
  await settle();
};
const optional = async (item, name, callback) => {
  try { await callback(); } catch (error) { missing(item.name, name, error?.message || String(error)); }
};
const contactSheet = async (name, records) => {
  const cards = records.filter((r) => r.viewport_screenshot).map((r) => {
    const src = fs.readFileSync(path.join(output, r.viewport_screenshot)).toString('base64');
    return '<section><h2>' + r.page + ' / ' + r.state + ' / ' + r.locale + ' / ' +
      r.viewport.width + '</h2><img src="data:image/png;base64,' + src + '"></section>';
  }).join('');
  const sheet = await browser.newPage({ viewport: { width: 1100, height: 850 }, deviceScaleFactor: 1 });
  await sheet.setContent('<!doctype html><html><head><meta charset="utf-8"><style>' +
    'body{margin:20px;background:#f3f4f6;font:16px Arial,sans-serif;color:#111}' +
    'h1{font-size:25px}main{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}' +
    'section{background:white;border:1px solid #cbd5e1;border-radius:8px;padding:12px;overflow:hidden}' +
    'h2{font-size:14px;margin:0 0 10px}img{width:100%;height:350px;object-fit:contain;object-position:top;background:#fff}' +
    '</style></head><body><h1>PersianGravity / ' + name +
    ' — review index only (see individual full-page PNGs)</h1><main>' + cards + '</main></body></html>');
  await sheet.evaluate(async () => { await Promise.all([...document.images].map((img) => img.decode())); await document.fonts.ready; });
  const filename = 'pgr-admin-' + name + '-contact-sheet.png';
  const image = dimensions(await sheet.screenshot({ path: path.join(output, filename), fullPage: true }));
  evidence.contact_sheets ??= [];
  evidence.contact_sheets.push({ filename, count: records.filter((r) => r.viewport_screenshot).length, dimensions: image });
  await sheet.close();
};
let failure;
try {
  original = state();
  assert(original.wordpress === '6.8.3', 'Unexpected WordPress version');
  assert(original.php.startsWith('8.2.'), 'Unexpected PHP major/minor');
  evidence.environment.wordpress = original.wordpress;
  evidence.environment.php = original.php;
  evidence.environment.playwright = JSON.parse(fs.readFileSync('node_modules/playwright/package.json', 'utf8')).version;
  browser = await chromium.launch({ headless: true });
  evidence.environment.chromium = browser.version();
  context = await browser.newContext({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1 });
  page = await context.newPage();
  page.on('pageerror', (error) => runningErrors.push({ kind: 'pageerror', message: String(error.message).slice(0, 300) }));
  page.on('console', (message) => { if (message.type() === 'error') runningErrors.push({ kind: 'console-error', message: message.text().slice(0, 300).replace(/https?:\/\/\S+/g, '[URL]') }); });
  page.on('requestfailed', (request) => {
    if (request.url().startsWith(safeOrigin)) runningErrors.push({ kind: 'request-failed', url: shortUrl(request.url()), error: request.failure()?.errorText });
  });
  page.on('response', (response) => {
    if (response.status() >= 400 && response.url().startsWith(safeOrigin)) {
      runningErrors.push({ kind: 'http-error', url: shortUrl(response.url()), status: response.status() });
    }
  });
  await go(manifest.login_url);
  await page.locator('#user_login').fill('runtime_admin');
  await page.locator('#user_pass').fill(process.env.WU008_ADMIN_PASSWORD);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.locator('#wp-submit').click(),
  ]);
  await visit(pages[0], 'fa');
  evidence.controls.push({ name: 'authenticated-admin', status: 'PASS' });

  // Deliberately incorrect expected identity/locale must be rejected by verifier.
  currentLocale = setLocale('fa_IR');
  await visit(pages[0], 'fa');
  for (const [name, item, lang] of [
    ['wrong-page-identity', pages[1], 'fa'], ['wrong-expected-locale', pages[0], 'en'],
  ]) {
    let rejected = false;
    try { await verify(item, lang); } catch { rejected = true; }
    assert(rejected, 'Negative verification unexpectedly accepted ' + name);
    evidence.controls.push({ name, status: 'PASS', intentionally_rejected: true });
  }

  // The baseline captures preserve actual module states and runtime diagnostics.
  for (const lang of ['fa', 'en']) {
    currentLocale = setLocale(lang === 'fa' ? 'fa_IR' : 'en_US');
    for (const item of pages) {
      for (const [width, height] of viewports[lang]) {
        await visit(item, lang);
        await capture(item, lang, width, height);
      }
    }
  }
  currentLocale = setLocale('fa_IR');

  // UNKNOWN is always returned by the real iranian_currency usage inspector.
  await optional(pages[0], 'unknown-warning', async () => {
    for (const width of [1280, 390]) {
      await page.setViewportSize({ width, height: width === 1280 ? 900 : 800 });
      await visit(pages[0], 'fa');
      const card = page.locator('article.pgr-module-card').filter({ has: page.locator('code[dir="ltr"]', { hasText: /^iranian_currency$/ }) });
      assert(await card.count() === 1, 'iranian_currency card unavailable');
      assert(await card.locator('input[name="enable"]').getAttribute('value') === '0',
        'Expected enabled module; cannot demonstrate real UNKNOWN first-step');
      const before = state().modules;
      await Promise.all([
        page.waitForURL(/pgr_confirm_module=iranian_currency/),
        card.locator('form button[type="submit"]').click(),
      ]);
      assert(JSON.stringify(state().modules) === JSON.stringify(before), 'First-step changed persisted module state');
      await capture(pages[0], 'fa', width, width === 1280 ? 900 : 800, 'unknown-warning');
    }
  });
  const scanner = pages[1];
  await optional(scanner, 'built-in-inspect', async () => {
    for (const width of [1280, 390]) {
      await visit(scanner, 'fa', { action: 'edit', profile: 'sayad_v01' });
      assert(await page.locator('.pgr-definition-list code').count() >= 2, 'Built-in inspection view absent');
      await capture(scanner, 'fa', width, width === 1280 ? 900 : 800, 'built-in-inspect');
    }
  });
  await optional(scanner, 'new-empty', async () => {
    for (const width of [1280, 390]) {
      await visit(scanner, 'fa', { action: 'new' });
      assert(await page.locator('input[name="profile[id]"]').inputValue() === '', 'New form is not empty');
      await capture(scanner, 'fa', width, width === 1280 ? 900 : 800, 'new-empty');
    }
  });
  await optional(scanner, 'new-populated', async () => {
    for (const width of [1280, 390]) {
      await visit(scanner, 'fa', { action: 'new' });
      await page.locator('input[name="profile[label]"]').fill('پروفایل آزمایشی ثبت‌نشده');
      await page.locator('input[name="profile[id]"]').fill('review_fixture_unpersisted');
      await page.locator('input[name="profile[outputs][0][key]"]').fill('tracking_id');
      await page.locator('input[name="profile[outputs][0][label]"]').fill('شناسه پیگیری');
      await page.locator('[data-pgr-add-output]').click();
      await page.locator('input[name="profile[outputs][1][key]"]').fill('branch_code');
      await page.locator('input[name="profile[outputs][1][label]"]').fill('کد شعبه');
      await page.locator('[data-pgr-add-output]').click();
      await page.locator('input[name="profile[outputs][2][key]"]').fill('amount');
      await page.locator('input[name="profile[outputs][2][label]"]').fill('مبلغ');
      assert(await page.locator('[data-pgr-output-row]').count() === 3, 'Multiple synthetic output rows missing');
      await capture(scanner, 'fa', width, width === 1280 ? 900 : 800, 'new-populated');
    }
  });
  missing(scanner.name, 'saved-custom-edit', 'No disposable profile fixture through a supported save mechanism was provisioned; no persisted profile was created for evidence capture.');

  await optional(pages[2], 'national-id-inactive', async () => {
    const before = state().modules;
    try {
      cli('PGR_Module_Registry::set_enabled("national_id",false);');
      for (const width of [1280, 390]) {
        await visit(pages[2], 'fa');
        await capture(pages[2], 'fa', width, width === 1280 ? 900 : 800, 'national-id-inactive');
      }
    } finally {
      restoreOption('pgr_modules', before);
      assert(JSON.stringify(state().modules) === JSON.stringify(before), 'Module state not restored after inactive-National-ID state');
    }
  });
} catch (error) {
  failure = String(error?.stack || error);
  evidence.fatal = failure;
  note('Setup, identity, baseline or capture failure: ' + String(error?.message || error));
} finally {
  // Restore original site/user locale and persisted module state even on failure.
  try {
    if (original) {
      restoreOption('pgr_modules', original.modules);
      restoreOption('WPLANG', original.site_setting);
      setUserLocale(original.user_locale, original.user_locale_exists);
      const after = state();
      for (const key of ['site_setting', 'user_locale', 'user_locale_exists', 'modules', 'profiles', 'settings']) {
        assert(JSON.stringify(after[key]) === JSON.stringify(original[key]), 'Failed restoration/integrity check: ' + key);
      }
      evidence.controls.push({ name: 'locale-and-persisted-options-restored', status: 'PASS' });
    }
  } catch (error) {
    evidence.controls.push({ name: 'locale-and-persisted-options-restored', status: 'FAIL', reason: String(error) });
    failure ||= String(error);
  }
  if (browser) {
    try {
      await contactSheet('desktop', evidence.captures.filter((r) => r.viewport.width >= 768));
      await contactSheet('mobile', evidence.captures.filter((r) => r.viewport.width <= 390));
    } catch (error) { note('Contact sheet unavailable: ' + String(error)); failure ||= String(error); }
    await browser.close();
  }
  const expected = 4 * (4 + 2);
  evidence.baseline_count = evidence.captures.filter((r) => r.state === 'default').length;
  evidence.expected_baseline_count = expected;
  evidence.status = !failure && evidence.baseline_count === expected &&
    evidence.controls.every((c) => c.status === 'PASS') ? 'CAPTURED_WITH_LIMITATIONS' : 'FAIL';
  evidence.inventory = {
    captured: evidence.captures.map((r) => r.screenshot),
    not_captured: evidence.missing,
  };
  save();
}
if (evidence.status === 'FAIL') throw Error('Admin UX evidence capture incomplete; inspect pgr-admin-ux-capture.json');
console.log('Admin UI capture complete; screenshots are review evidence, NOT UI/UX acceptance.');
