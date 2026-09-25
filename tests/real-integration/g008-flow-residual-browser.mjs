import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
const manifestPath = process.env.WU008_MANIFEST_PATH;
const adminPassword = process.env.WU008_ADMIN_PASSWORD;
const mode = process.env.WU008_G008_MODE;
if (!artifactDir || !manifestPath || !adminPassword || !['enabled', 'disabled'].includes(mode)) {
  throw new Error('Residual G008 browser proof requires artifact/manifest/admin password and enabled|disabled mode.');
}

const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
if (!manifest.g008_flow_entry_detail_url || !manifest.g008_flow_print_url || !Array.isArray(manifest.g008_flow_timeline_native)) {
  throw new Error('Residual Entry Detail/Print fixture is incomplete.');
}
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
const diagnostics = { consoleErrors: [], pageErrors: [], requestFailures: [] };
page.on('console', (message) => {
  if (message.type() === 'error') diagnostics.consoleErrors.push(message.text().slice(0, 1200));
});
page.on('pageerror', (error) => diagnostics.pageErrors.push(String(error?.stack || error).slice(0, 3000)));
page.on('requestfailed', (request) => diagnostics.requestFailures.push({ url: request.url(), error: request.failure()?.errorText || 'unknown' }));

async function login() {
  const response = await page.goto(manifest.login_url, { waitUntil: 'domcontentloaded' });
  if (!response?.ok()) throw new Error(`Login page failed: ${response?.status()}`);
  await page.fill('#user_login', 'runtime_admin');
  await page.fill('#user_pass', adminPassword);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.click('#wp-submit'),
  ]);
  await page.locator('#wpadminbar').waitFor({ timeout: 10000 });
}

function normalize(values) {
  return values.map((value) => value.trim()).filter(Boolean);
}

function assertModePresentation(actual, label) {
  const native = manifest.g008_flow_timeline_native;
  if (actual.length !== native.length || actual.length === 0) {
    throw new Error(`${label} Timeline row count drifted.`);
  }
  if (mode === 'disabled') {
    if (JSON.stringify(actual) !== JSON.stringify(native)) {
      throw new Error(`${label} disabled Timeline did not return exact native host timestamps: ${JSON.stringify({ actual, native })}`);
    }
    return;
  }
  for (const header of actual) {
    if (!/^[۰-۹]{4}\/[۰-۹]{2}\/[۰-۹]{2}/u.test(header)) {
      throw new Error(`${label} enabled Timeline did not expose bounded Jalali presentation: ${header}`);
    }
  }
  if (JSON.stringify(actual) === JSON.stringify(native)) {
    throw new Error(`${label} enabled Timeline unexpectedly remained native.`);
  }
}

await login();

const detailResponse = await page.goto(manifest.g008_flow_entry_detail_url, { waitUntil: 'domcontentloaded' });
if (!detailResponse?.ok()) throw new Error(`Entry Detail request failed: ${detailResponse?.status()}`);
await page.locator('.gravityflow-timeline').first().waitFor({ timeout: 15000 });
const detailTimeline = normalize(await page.locator('.gravityflow-timeline .gravityflow-note-meta').allTextContents());
const detailBody = await page.locator('body').innerText();
if (detailBody.includes('PGRTIMELINE')) throw new Error('Residual Entry Detail leaked a Timeline marker.');
assertModePresentation(detailTimeline, 'Entry Detail');
await page.screenshot({ path: path.join(artifactDir, `g008-flow-residual-entry-detail-${mode}.png`), fullPage: true });

const printResponse = await page.goto(manifest.g008_flow_print_url, { waitUntil: 'domcontentloaded' });
if (!printResponse?.ok()) throw new Error(`Gravity Flow Print request failed: ${printResponse?.status()}`);
await page.locator('#view-container .gravityflow-note-meta').first().waitFor({ timeout: 15000 });
const printTimeline = normalize(await page.locator('#view-container .gravityflow-note-meta').allTextContents());
const printBody = await page.locator('body').innerText();
if (printBody.includes('PGRTIMELINE')) throw new Error('Residual Print leaked a Timeline marker.');
assertModePresentation(printTimeline, 'Print');
if (JSON.stringify(printTimeline) !== JSON.stringify(detailTimeline)) {
  throw new Error('Residual Print did not inherit the exact Entry Detail Timeline presentation.');
}
await page.screenshot({ path: path.join(artifactDir, `g008-flow-residual-print-${mode}.png`), fullPage: true });

if (diagnostics.pageErrors.length || diagnostics.requestFailures.length) {
  throw new Error(`Residual browser diagnostics failed: ${JSON.stringify(diagnostics)}`);
}

const evidence = {
  schema_version: '2.0.0',
  evidence_class: 'AUTHENTIC_GRAVITY_FLOW_RESIDUAL_BROWSER',
  mode,
  exact_persiangravity_commit: process.env.WU008_PGR_SHA || null,
  exact_persiangravity_package_sha256: process.env.WU008_PGR_PACKAGE_SHA256 || null,
  exact_gravityflow_version: process.env.WU008_FLOW_VERSION || null,
  exact_gravityflow_package_sha256: process.env.WU008_FLOW_SHA256 || null,
  site_timezone: manifest.g008_flow_site_timezone || null,
  php_default_timezone: manifest.g008_flow_php_default_timezone || null,
  entry_id: Number(manifest.g008_flow_residual_entry_id),
  entry_detail: {
    url: manifest.g008_flow_entry_detail_url,
    timeline: detailTimeline,
    presentation: mode === 'enabled' ? 'JALALI' : 'NATIVE',
  },
  print: {
    url: manifest.g008_flow_print_url,
    timeline: printTimeline,
    relation: 'PRINT_INHERITS_VERIFIED_TIMELINE_PRESENTATION',
  },
  diagnostics,
};
fs.writeFileSync(path.join(artifactDir, `g008-flow-residual-browser-${mode}.json`), `${JSON.stringify(evidence, null, 2)}\n`);
await browser.close();
console.log(`G008_FLOW_RESIDUAL_BROWSER_${mode.toUpperCase()} PASS`);
