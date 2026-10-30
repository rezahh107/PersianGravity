import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';
import { snapshotDiagnostics, evaluateOperationDiagnostics, formatDiagnosticGateFailure } from './browser-diagnostics.mjs';

const dir = process.env.WU008_ARTIFACT_DIR;
const manifestPath = process.env.WU008_MANIFEST_PATH;
const password = process.env.WU008_ADMIN_PASSWORD;
const head = process.env.WU008_PGR_SHA;
const locale = process.env.WU008_ADMIN_UI_LOCALE;
const mode = process.env.WU008_ADMIN_UI_MODE;
if (!dir || !manifestPath || !password || !head ||
    !['fa_IR', 'en_US'].includes(locale) || !['normal', 'disabled'].includes(mode)) {
  throw Error('WU008 authenticated runtime, Head, explicit locale and UI mode required.');
}
const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
if (manifest.persiangravity_source_commit !== head ||
    manifest.versions?.gravityforms !== '3.1.1.1' ||
    manifest.versions?.persiangravity !== process.env.WU008_PGR_VERSION) {
  throw Error('Exact installed source/Gravity Forms identity mismatch.');
}
const base = new URL(manifest.admin_url);
const url = (slug, extra = '') => new URL('admin.php?page=' + slug + extra, base).href;
const profile = 'persian-gravityforms-scanner-profiles';
const overview = 'persian-gravityforms';
const output = path.join(dir, 'admin-ui-evidence');
fs.mkdirSync(output, { recursive: true });
const browser = await chromium.launch({ headless: true });
const evidence = {
  schema_version: 1, evidence_class: 'AUTHENTIC_LICENSED_WU008_ADMIN_BROWSER',
  exact_head: head, mode, locale, expected_direction: locale === 'fa_IR' ? 'rtl' : 'ltr',
  runtime: {
    wordpress: '6.8.3', php: manifest.php_version,
    versions: manifest.versions, playwright: '1.55.0',
    chromium: browser.version(), device_scale_factor: 1,
  },
  cases: [], negative_control: null, not_proven: [
    'Screen reader output and full accessibility audit',
    'Actual save/edit/delete/duplicate profile interactions',
    'Second-stage module-disable confirmation and live data effects',
    'Other plugin versions, user roles and production environment',
  ],
};
const origin = new URL(manifest.admin_url).origin;
const assert = (truth, message) => { if (!truth) throw Error(message); };
function metrics() {
  const root = document.documentElement;
  const wrap = document.querySelector('.pgr-admin');
  const style = (element) => element ? getComputedStyle(element) : null;
  const font = (element) => ({
    family: style(element)?.fontFamily ?? null,
    size: style(element)?.fontSize ?? null,
    direction: style(element)?.direction ?? null,
  });
  const width = window.innerWidth;
  const offenders = [...document.querySelectorAll('.pgr-admin *')].filter((element) => {
    const r = element.getBoundingClientRect();
    const css = getComputedStyle(element);
    return r.width > 0 && css.position !== 'fixed' &&
      (r.right > width + 2 || r.left < -2);
  }).slice(0, 16).map((element) => ({
    element: element.tagName.toLowerCase(),
    class_name: String(element.className || '').slice(0, 90),
    text: (element.textContent || '').trim().slice(0, 55),
    left: Math.round(element.getBoundingClientRect().left),
    right: Math.round(element.getBoundingClientRect().right),
  }));
  return {
    viewport: { width, height: window.innerHeight },
    document_scroll_width: root.scrollWidth, document_client_width: root.clientWidth,
    horizontal_overflow: root.scrollWidth > width + 2,
    out_of_view_elements: offenders,
    html_lang: root.lang, html_dir: root.dir,
    effective_root_direction: style(root)?.direction,
    effective_fonts: { body: font(document.body), page: font(wrap), heading: font(wrap?.querySelector('h1')) },
    plugin_style_loaded: [...document.styleSheets].some((sheet) =>
      (sheet.href || '').includes('/assets/css/pgr-admin.css')),
  };
}
const allCases = mode === 'normal'
  ? [
      ['overview', url(overview), 'article.pgr-module-card', 7],
      ['profiles-list', url(profile), 'article.pgr-profile-card', 2],
      ['profiles-new', url(profile, '&action=new'), '.pgr-profile-form', 1],
      ['profiles-edit', url(profile, '&action=edit&profile=wu008_ui_long_label'), '.pgr-profile-form', 1],
      ['profiles-built-in', url(profile, '&action=edit&profile=sayad_v01'), '.pgr-output-summary', 1],
      ['settings', url('persian-gravityforms-settings'), 'form', 1],
      ['system-status', url('persian-gravityforms-system-status'), '.pgr-card-grid', 1],
      ['module-confirm', url(overview), '.pgr-warning-note[role="alert"]', 1],
    ]
  : [
      ['overview-disabled', url(overview), 'article.pgr-module-card', 7],
      ['profiles-disabled-warning', url(profile), '.notice-warning', 1],
      ['settings-disabled-notice', url('persian-gravityforms-settings'), '.notice-info', 1],
      ['system-status-disabled', url('persian-gravityforms-system-status'), '[data-state="DISABLED"]', 2],
    ];
let failed = false;
try {
  for (const width of [1280, 390, 320]) {
    const included = allCases.filter(([name]) => width !== 320 ||
      ['overview', 'profiles-edit', 'system-status', 'module-confirm',
       'profiles-disabled-warning', 'settings-disabled-notice'].includes(name));
    if (included.length === 0) continue;
    const height = width === 1280 ? 900 : width === 390 ? 844 : 700;
    const context = await browser.newContext({
      viewport: { width, height }, deviceScaleFactor: 1,
    });
    const page = await context.newPage();
    const diagnostics = { pageErrors: [], requestFailures: [], requestsStarted: 0 };
    const sequences = new WeakMap();
    page.on('pageerror', e => diagnostics.pageErrors.push(String(e)));
    page.on('request', request => {
      diagnostics.requestsStarted++;
      sequences.set(request, diagnostics.requestsStarted);
    });
    page.on('requestfailed', request => diagnostics.requestFailures.push({
      url: request.url(), method: request.method(),
      error: request.failure()?.errorText || 'unknown',
      requestSequence: sequences.get(request) || null,
    }));
    try {
      let response = await page.goto(manifest.login_url, { waitUntil: 'domcontentloaded' });
      assert(response?.ok(), 'WordPress login endpoint unavailable');
      await page.locator('#user_login').fill('runtime_admin');
      await page.locator('#user_pass').fill(password);
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
        page.locator('#wp-submit').click(),
      ]);
      assert(await page.locator('#wpadminbar').count() === 1, 'WordPress authentication failed');
      for (const [name, target, selector, minCount] of included) {
        const record = { name, width, height, dpr: 1, locale, mode, state: 'NOT_VERIFIED' };
        const baseline = snapshotDiagnostics(diagnostics);
        try {
          response = await page.goto(target, { waitUntil: 'domcontentloaded' });
          assert(response?.ok(), 'Admin route failed: ' + page.url());
          assert(!page.url().includes('wp-login.php'), 'Authentication lost');
          assert((await page.locator('body').getAttribute('class') || '').includes('wp-admin'), 'Not WordPress admin');
          assert(await page.locator('.pgr-admin').count() === 1, 'Real PersianGravity admin root missing');
          if (name === 'module-confirm') {
            const card = page.locator('article.pgr-module-card').filter({ hasText: 'iranian_currency' });
            assert(await card.count() === 1, 'Currency module card missing');
            assert(await card.locator('input[name="enable"]').inputValue() === '0',
              'Currency module must be enabled before first-stage warning');
            await Promise.all([
              page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
              card.locator('button[type="submit"]').click(),
            ]);
            const resultUrl = new URL(page.url());
            assert(resultUrl.searchParams.get('pgr_module_notice') === 'confirm' &&
              resultUrl.searchParams.get('pgr_confirm_module') === 'iranian_currency',
              'Real first-stage UNKNOWN disable confirmation not reached');
            assert(await page.locator('article.pgr-module-card').filter({hasText:'iranian_currency'})
              .locator('input[name="confirm"][value="1"]').count() === 1,
              'Second-stage confirmation form absent');
            // Deliberately NEVER submit the confirmation.
          }
          assert(await page.locator(selector).count() >= minCount,
            'Expected authentic UI surface missing: ' + selector);
          if (name === 'profiles-list' || name === 'profiles-edit') {
            assert((await page.locator('.pgr-admin').innerText()).includes('LongLabel_12345'),
              'Synthetic long-label fixture missing');
          }
          const measured = await page.evaluate(metrics);
          assert(measured.plugin_style_loaded, 'Actual product stylesheet not loaded');
          assert(measured.effective_root_direction === evidence.expected_direction,
            'Effective document direction mismatches locale');
          assert(measured.html_lang.toLowerCase().replace('_','-') ===
            (locale === 'fa_IR' ? 'fa-ir' : 'en-us'), 'WordPress locale mismatch');
          const gate = evaluateOperationDiagnostics(name, diagnostics, baseline, [origin]);
          assert(gate.ok, formatDiagnosticGateFailure(gate));
          const filename = 'ui-' + mode + '-' + locale + '-' + name + '-' + width + '.jpg';
          await page.screenshot({ path: path.join(output, filename), type: 'jpeg', quality: 86, fullPage: true });
          Object.assign(record, {
            state: measured.horizontal_overflow || measured.out_of_view_elements.length
              ? 'VISUAL_FINDING' : 'CAPTURED_NO_DETECTED_OVERFLOW',
            measured, screenshot: filename, interaction: name === 'module-confirm'
              ? 'FIRST_STAGE_REAL_FORM_POST_ONLY' : 'READ_ONLY_NAVIGATION',
          });
        } catch (error) {
          failed = true;
          record.state = 'CAPTURE_FAILED';
          record.error = String(error?.stack || error);
          await page.screenshot({
            path: path.join(output, 'failure-' + mode + '-' + locale + '-' + name + '-' + width + '.jpg'),
            type: 'jpeg', quality: 80, fullPage: true,
          }).catch(() => {});
        }
        evidence.cases.push(record);
      }
      if (width === 320 && mode === 'normal') {
        const control = await context.newPage();
        await control.setContent('<!doctype html><html><body style="margin:0"><main style="width:800px;height:80px">WU008 isolated negative overflow control</main></body></html>');
        const negative = await control.evaluate(() => ({
          document_width: document.documentElement.scrollWidth,
          viewport_width: innerWidth,
          detected: document.documentElement.scrollWidth > innerWidth + 2,
        }));
        assert(negative.detected && negative.document_width >= 800,
          'Overflow detector failed the isolated 800px negative control');
        evidence.negative_control = { ...negative, mode: 'ISOLATED_HTML_NOT_PRODUCT', state: 'DETECTED' };
        await control.screenshot({
          path: path.join(output, 'ui-negative-control-' + locale + '-320.jpg'),
          type: 'jpeg', quality: 75, fullPage: true,
        });
        await control.close();
      }
    } catch (error) {
      failed = true;
      evidence.cases.push({ name: 'AUTHENTICATED_CONTEXT_' + width, state: 'CAPTURE_FAILED',
        error: String(error?.stack || error), width });
    } finally {
      await context.close();
    }
  }
} finally {
  evidence.status = failed ? 'INCOMPLETE' : 'CAPTURED';
  fs.writeFileSync(path.join(output, 'manifest-' + mode + '-' + locale + '.json'),
    JSON.stringify(evidence, null, 2) + '\n');
  await browser.close();
}
if (failed) throw Error('WU008 admin UI capture incomplete; see durable capture manifest.');
console.log('WU008_ADMIN_UI_CAPTURED ' + mode + ' ' + locale + ' ' + evidence.cases.length);
