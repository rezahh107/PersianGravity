import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const baseUrl = process.env.WU008_BASE_URL;
const artifactDir = process.env.WU008_ARTIFACT_DIR;
const manifestPath = process.env.WU008_MANIFEST_PATH;
if (!baseUrl || !artifactDir || !manifestPath) {
  throw new Error('WU008_BASE_URL, WU008_ARTIFACT_DIR, and WU008_MANIFEST_PATH are required.');
}

const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const results = [];
const diagnostics = { console: [], pageErrors: [], requestFailures: [] };

function record(id, name, status, details = null) {
  results.push({ id, name, status, details });
}

async function runCheck(id, name, fn) {
  try {
    record(id, name, 'PASS', await fn());
  } catch (error) {
    record(id, name, 'FAIL', { error: String(error?.stack || error) });
  }
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
  await runCheck('WU008-BROWSER-001', 'authentic Gravity Forms frontend renders RTL and provider validation text', async () => {
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
    return { html, providerValidationObserved: true };
  });

  await page.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'domcontentloaded' });
  await page.fill('#user_login', 'runtime_admin');
  await page.fill('#user_pass', 'wu008-runtime-pass-2026');
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.click('#wp-submit'),
  ]);

  await runCheck('WU008-BROWSER-002', 'authentic Gravity Flow Inbox loads in fa_IR/RTL with provider text', async () => {
    const response = await page.goto(manifest.gravityflow_inbox_url, { waitUntil: 'domcontentloaded' });
    if (!response || !response.ok()) throw new Error(`Gravity Flow Inbox response failed: ${response?.status()}`);
    await page.waitForTimeout(1200);
    const htmlDir = await page.locator('html').getAttribute('dir');
    const bodyText = await page.locator('body').innerText();
    if (htmlDir !== 'rtl') throw new Error(`Gravity Flow admin html dir is ${htmlDir}, expected rtl.`);
    if (!bodyText.includes('کاری در انتظار نیست') && !bodyText.includes('جستجو در کارهای من')) {
      throw new Error('No admitted Gravity Flow Persian provider text was observed in the authentic Inbox UI.');
    }
    if (/There has been a critical error|Fatal error/i.test(bodyText)) throw new Error('Gravity Flow page contains a fatal/critical error.');
    await page.screenshot({ path: path.join(artifactDir, 'gravityflow-inbox.png'), fullPage: true });
    return { rtl: true, providerTextObserved: true };
  });

  await runCheck('WU008-BROWSER-003', 'authentic GravityView admin surface loads without runtime failure in RTL', async () => {
    await page.goto(`${baseUrl}/wp-admin/`, { waitUntil: 'domcontentloaded' });
    const candidates = await page.locator('#adminmenu a').evaluateAll((links) => links
      .map((link) => ({ href: link.href, text: (link.textContent || '').trim() }))
      .filter((item) => /gravityview|post_type=gravityview/i.test(item.href) || /gravityview/i.test(item.text)));
    if (candidates.length === 0) throw new Error('GravityView admin menu surface was not discoverable after exact plugin activation.');
    const target = candidates[0].href;
    const response = await page.goto(target, { waitUntil: 'domcontentloaded' });
    if (!response || !response.ok()) throw new Error(`GravityView admin response failed: ${response?.status()}`);
    await page.waitForTimeout(800);
    const htmlDir = await page.locator('html').getAttribute('dir');
    const bodyText = await page.locator('body').innerText();
    if (htmlDir !== 'rtl') throw new Error(`GravityView admin html dir is ${htmlDir}, expected rtl.`);
    if (/There has been a critical error|Fatal error/i.test(bodyText)) throw new Error('GravityView page contains a fatal/critical error.');
    await page.screenshot({ path: path.join(artifactDir, 'gravityview-admin.png'), fullPage: true });
    return { target, rtl: true };
  });

  await runCheck('WU008-BROWSER-004', 'direct runtime provider/fallback evidence is exact and fail-closed', async () => {
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
    schema_version: '1.0.0',
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
