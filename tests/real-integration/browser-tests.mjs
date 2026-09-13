import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';
import {
  evaluateOperationDiagnostics,
  formatDiagnosticGateFailure,
  snapshotDiagnostics,
} from './browser-diagnostics.mjs';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
const manifestPath = process.env.WU008_MANIFEST_PATH;
const adminPassword = process.env.WU008_ADMIN_PASSWORD;
if (!artifactDir || !manifestPath || !adminPassword) {
  throw new Error('WU008_ARTIFACT_DIR, WU008_MANIFEST_PATH, and WU008_ADMIN_PASSWORD are required.');
}

const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const requiredNavigationFields = ['page_url', 'login_url', 'admin_url', 'gravityflow_inbox_url'];
for (const field of requiredNavigationFields) {
  if (typeof manifest[field] !== 'string' || manifest[field] === '') {
    throw new Error(`Runtime manifest is missing required navigation field: ${field}`);
  }
}

const runtimeOrigins = [...new Set(requiredNavigationFields.map((field) => new URL(manifest[field]).origin))];
if (runtimeOrigins.length !== 1) {
  throw new Error(`Manifest application URLs must share one runtime origin, got: ${runtimeOrigins.join(', ')}`);
}

const results = [];
const diagnostics = { console: [], pageErrors: [], requestFailures: [] };

function record(id, name, status, details = null) {
  results.push({ id, name, status, details });
}

async function pageState(page) {
  return {
    url: page.url(),
    title: await page.title().catch(() => ''),
    body: await page.locator('body').innerText().then((text) => text.slice(0, 1800)).catch(() => ''),
  };
}

async function runCheck(page, id, name, fn) {
  const baseline = snapshotDiagnostics(diagnostics);
  let details = null;
  let operationError = null;

  try {
    details = await fn();
  } catch (error) {
    operationError = error;
  }

  await page.waitForTimeout(50).catch(() => {});
  const gate = evaluateOperationDiagnostics(id, diagnostics, baseline, runtimeOrigins);

  if (operationError || !gate.ok) {
    record(id, name, 'FAIL', {
      error: operationError ? String(operationError?.stack || operationError) : formatDiagnosticGateFailure(gate),
      blockingDiagnostics: gate.blocking,
      page: await pageState(page),
    });
    return false;
  }

  record(id, name, 'PASS', details);
  return true;
}

function normalizePathname(pathname) {
  return pathname.endsWith('/') ? pathname : `${pathname}/`;
}

async function assertAuthenticatedAdminSurface(page) {
  const adminBoundary = new URL(manifest.admin_url);
  const current = new URL(page.url());
  const adminPath = normalizePathname(adminBoundary.pathname);

  if (current.origin !== adminBoundary.origin || !normalizePathname(current.pathname).startsWith(adminPath)) {
    throw new Error(`Expected authenticated admin URL under ${manifest.admin_url}, got ${page.url()}`);
  }
  if (/wp-login\.php/i.test(current.pathname)) {
    throw new Error(`Admin navigation redirected to login: ${page.url()}`);
  }

  await page.locator('#wpadminbar').waitFor({ state: 'attached', timeout: 10000 });
  await page.locator('#adminmenu').waitFor({ state: 'attached', timeout: 10000 });
  const bodyClass = await page.locator('body').getAttribute('class');
  if (!bodyClass || !/(^|\s)wp-admin(\s|$)/.test(bodyClass)) {
    throw new Error(`Authenticated WordPress admin shell was not proven; body class=${bodyClass}`);
  }

  return { url: page.url(), bodyClass };
}

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
page.on('console', (msg) => diagnostics.console.push({ type: msg.type(), text: msg.text().slice(0, 1200) }));
page.on('pageerror', (error) => diagnostics.pageErrors.push(String(error?.stack || error).slice(0, 3000)));
page.on('requestfailed', (request) => diagnostics.requestFailures.push({
  url: request.url().slice(0, 1000),
  method: request.method(),
  error: request.failure()?.errorText || 'unknown',
}));

try {
  await runCheck(page, 'WU008-BROWSER-001', 'authentic Gravity Forms frontend renders RTL and provider validation text', async () => {
    const response = await page.goto(manifest.page_url, { waitUntil: 'domcontentloaded' });
    if (!response || !response.ok()) throw new Error(`Frontend response failed: ${response?.status()}`);
    await page.locator(`#gform_${manifest.form_id}`).waitFor({ timeout: 20000 });
    const html = await page.locator('html').evaluate((el) => ({ dir: el.getAttribute('dir'), lang: el.getAttribute('lang') }));
    if (html.dir !== 'rtl') throw new Error(`Expected html dir=rtl, got ${html.dir}`);
    await page.screenshot({ path: path.join(artifactDir, 'gravityforms-initial.png'), fullPage: true });

    const submit = page.locator(`#gform_${manifest.form_id} input[type="submit"], #gform_${manifest.form_id} button[type="submit"]`).first();
    await submit.click();
    await page.waitForLoadState('domcontentloaded');
    await page.locator(`#gform_wrapper_${manifest.form_id}`).waitFor({ timeout: 20000 });
    const bodyText = await page.locator('body').innerText();
    if (!bodyText.includes('مشکلی با این ارسال پیش آمده است.')) {
      throw new Error('Authentic Gravity Forms validation summary did not use the admitted Persian provider translation.');
    }
    await page.screenshot({ path: path.join(artifactDir, 'gravityforms-validation.png'), fullPage: true });
    return { html, providerValidationObserved: true, url: page.url() };
  });

  const authPassed = await runCheck(page, 'WU008-BROWSER-AUTH', 'manifest-derived login establishes an authenticated WordPress admin surface', async () => {
    const loginResponse = await page.goto(manifest.login_url, { waitUntil: 'domcontentloaded' });
    if (!loginResponse || !loginResponse.ok()) throw new Error(`Login response failed: ${loginResponse?.status()}`);
    await page.fill('#user_login', 'runtime_admin');
    await page.fill('#user_pass', adminPassword);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      page.click('#wp-submit'),
    ]);

    const adminResponse = await page.goto(manifest.admin_url, { waitUntil: 'domcontentloaded' });
    if (!adminResponse || !adminResponse.ok()) throw new Error(`Admin response failed after login: ${adminResponse?.status()}`);
    return assertAuthenticatedAdminSurface(page);
  });

  if (authPassed) {
    await runCheck(page, 'WU008-BROWSER-002', 'authentic Gravity Flow Inbox loads in fa_IR/RTL with provider text', async () => {
      const response = await page.goto(manifest.gravityflow_inbox_url, { waitUntil: 'domcontentloaded' });
      if (!response || !response.ok()) throw new Error(`Gravity Flow Inbox response failed: ${response?.status()}`);
      await assertAuthenticatedAdminSurface(page);
      await page.waitForFunction(
        () => document.body?.innerText.includes('کاری در انتظار نیست') || document.body?.innerText.includes('جستجو در کارهای من'),
        null,
        { timeout: 15000 },
      );
      const htmlDir = await page.locator('html').getAttribute('dir');
      const bodyText = await page.locator('body').innerText();
      if (htmlDir !== 'rtl') throw new Error(`Gravity Flow admin html dir is ${htmlDir}, expected rtl.`);
      if (!bodyText.includes('کاری در انتظار نیست') && !bodyText.includes('جستجو در کارهای من')) {
        throw new Error('No admitted Gravity Flow Persian provider text was observed in the authentic Inbox UI.');
      }
      if (/There has been a critical error|Fatal error/i.test(bodyText)) throw new Error('Gravity Flow page contains a fatal/critical error.');
      await page.screenshot({ path: path.join(artifactDir, 'gravityflow-inbox.png'), fullPage: true });
      return { rtl: true, providerTextObserved: true, url: page.url() };
    });

    await runCheck(page, 'WU008-BROWSER-003', 'authentic GravityView admin surface loads without runtime failure in RTL', async () => {
      const adminResponse = await page.goto(manifest.admin_url, { waitUntil: 'domcontentloaded' });
      if (!adminResponse || !adminResponse.ok()) throw new Error(`Admin response failed before GravityView discovery: ${adminResponse?.status()}`);
      await assertAuthenticatedAdminSurface(page);

      const adminBoundary = new URL(manifest.admin_url);
      const candidates = await page.locator('#adminmenu a[href]').evaluateAll((links, boundary) => links
        .map((link) => ({ href: link.href, text: (link.textContent || '').trim() }))
        .filter((item) => {
          try {
            const url = new URL(item.href);
            return url.origin === boundary.origin
              && url.pathname.startsWith(boundary.pathname)
              && url.searchParams.get('post_type') === 'gravityview';
          } catch {
            return false;
          }
        }), { origin: adminBoundary.origin, pathname: adminBoundary.pathname });

      if (candidates.length === 0) throw new Error('GravityView admin surface was not discoverable from the authenticated WordPress admin navigation.');
      const target = candidates[0].href;
      const response = await page.goto(target, { waitUntil: 'domcontentloaded' });
      if (!response || !response.ok()) throw new Error(`GravityView admin response failed: ${response?.status()}`);
      await assertAuthenticatedAdminSurface(page);

      const current = new URL(page.url());
      if (current.searchParams.get('post_type') !== 'gravityview') {
        throw new Error(`Wrong GravityView product surface after navigation: ${page.url()}`);
      }
      const htmlDir = await page.locator('html').getAttribute('dir');
      const bodyText = await page.locator('body').innerText();
      if (htmlDir !== 'rtl') throw new Error(`GravityView admin html dir is ${htmlDir}, expected rtl.`);
      if (/There has been a critical error|Fatal error/i.test(bodyText)) throw new Error('GravityView page contains a fatal/critical error.');
      await page.screenshot({ path: path.join(artifactDir, 'gravityview-admin.png'), fullPage: true });
      return { target, rtl: true };
    });
  } else {
    record('WU008-BROWSER-002', 'authentic Gravity Flow Inbox loads in fa_IR/RTL with provider text', 'FAIL', {
      error: 'Authentication precondition failed; product assertion was not executed.',
    });
    record('WU008-BROWSER-003', 'authentic GravityView admin surface loads without runtime failure in RTL', 'FAIL', {
      error: 'Authentication precondition failed; product assertion was not executed.',
    });
  }

  await runCheck(page, 'WU008-BROWSER-004', 'direct runtime provider/fallback evidence is exact and fail-closed', async () => {
    if (manifest.versions.gravityforms !== '3.1.1.1') throw new Error('Manifest Gravity Forms version mismatch.');
    if (manifest.versions.gravityflow !== '3.1.0') throw new Error('Manifest Gravity Flow version mismatch.');
    if (manifest.versions.gravityview !== '3.3.4') throw new Error('Manifest GravityView version mismatch.');
    if (manifest.versions.persiangravity !== '4.2.0') throw new Error('Manifest PersianGravity version mismatch.');
    if (manifest.fallback.provider_collision !== 'مشکلی با این ارسال پیش آمده است.') throw new Error('Provider precedence evidence mismatch.');
    if (manifest.fallback.upstream_only !== 'WU008_UPSTREAM_ONLY_PASS') throw new Error('Upstream-only fallback evidence mismatch.');
    if (manifest.provider.gravityview !== 'این نما در زباله‌دان است. %1$sبرای بازیابی نما کلیک کنید%2$s.') throw new Error('GravityView provider runtime evidence mismatch.');
    if (manifest.rtl !== true || manifest.locale !== 'fa_IR') throw new Error('Runtime locale/RTL evidence mismatch.');
    return { versions: manifest.versions, fallback: manifest.fallback, provider: manifest.provider };
  });
} finally {
  const failed = results.filter((result) => result.status !== 'PASS');
  fs.writeFileSync(path.join(artifactDir, 'browser-results.json'), JSON.stringify({
    schema_version: '1.1.0',
    suite: 'WU008 real licensed integration',
    exact_persiangravity_commit: process.env.WU008_PGR_SHA || null,
    results,
    diagnostics,
  }, null, 2) + '\n');
  if (failed.length > 0) {
    await page.screenshot({ path: path.join(artifactDir, 'browser-failure-final.png'), fullPage: true }).catch(() => {});
  }
  await browser.close();
}

for (const result of results) console.log(`${result.status} ${result.id} ${result.name}`);
if (results.some((result) => result.status !== 'PASS')) process.exit(1);
