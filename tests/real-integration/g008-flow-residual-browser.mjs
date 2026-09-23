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

await login();

const detailResponse = await page.goto(manifest.g008_flow_entry_detail_url, { waitUntil: 'domcontentloaded' });
if (!detailResponse?.ok()) throw new Error(`Entry Detail request failed: ${detailResponse?.status()}`);
await page.locator('.gravityflow-timeline').first().waitFor({ timeout: 15000 });
const detailTimeline = normalize(await page.locator('.gravityflow-timeline .gravityflow-note-meta').allTextContents());
if (JSON.stringify(detailTimeline) !== JSON.stringify(manifest.g008_flow_timeline_native)) {
  throw new Error(`Entry Detail timeline native timestamps drifted: ${JSON.stringify({ detailTimeline, expected: manifest.g008_flow_timeline_native })}`);
}
const dueField = page.locator('.gravityflow-status-box-field-due-date .gravityflow-status-box-field-value').first();
const detailDue = await dueField.count() ? (await dueField.textContent())?.trim() ?? '' : null;
await page.screenshot({ path: path.join(artifactDir, `g008-flow-residual-entry-detail-${mode}.png`), fullPage: true });

const printResponse = await page.goto(manifest.g008_flow_print_url, { waitUntil: 'domcontentloaded' });
if (!printResponse?.ok()) throw new Error(`Gravity Flow Print request failed: ${printResponse?.status()}`);
await page.locator('.gravityflow-timeline').first().waitFor({ timeout: 15000 });
const printTimeline = normalize(await page.locator('.gravityflow-timeline .gravityflow-note-meta').allTextContents());
if (JSON.stringify(printTimeline) !== JSON.stringify(manifest.g008_flow_timeline_native)) {
  throw new Error(`Print timeline native timestamps drifted: ${JSON.stringify({ printTimeline, expected: manifest.g008_flow_timeline_native })}`);
}
await page.screenshot({ path: path.join(artifactDir, `g008-flow-residual-print-${mode}.png`), fullPage: true });

if (diagnostics.pageErrors.length || diagnostics.requestFailures.length) {
  throw new Error(`Residual browser diagnostics failed: ${JSON.stringify(diagnostics)}`);
}

const evidence = {
  schema_version: '1.0.0',
  evidence_class: 'AUTHENTIC_GRAVITY_FLOW_RESIDUAL_NO_ADMISSION_BROWSER',
  mode,
  exact_persiangravity_commit: process.env.WU008_PGR_SHA || null,
  exact_persiangravity_package_sha256: process.env.WU008_PGR_PACKAGE_SHA256 || null,
  exact_gravityflow_version: process.env.WU008_FLOW_VERSION || null,
  exact_gravityflow_package_sha256: process.env.WU008_FLOW_SHA256 || null,
  entry_id: Number(manifest.g008_flow_residual_entry_id),
  entry_detail: {
    url: manifest.g008_flow_entry_detail_url,
    due_date_native: detailDue,
    timeline_native: detailTimeline,
  },
  print: {
    url: manifest.g008_flow_print_url,
    timeline_native: printTimeline,
    propagation: 'native underlying Timeline rendering reused; no independent Print date adapter',
  },
  dispositions: {
    'gravityflow.entry-detail.schedule-due-expiration': 'FINAL_NO_ADMISSION_GRAVITY_FLOW_3_1_0',
    'gravityflow.timeline-history': 'FINAL_NO_ADMISSION_GRAVITY_FLOW_3_1_0',
    'gravityflow.print': 'FINAL_NO_ADMISSION_GRAVITY_FLOW_3_1_0',
  },
  diagnostics,
};
fs.writeFileSync(path.join(artifactDir, `g008-flow-residual-browser-${mode}.json`), `${JSON.stringify(evidence, null, 2)}\n`);
await browser.close();
console.log(`G008_FLOW_RESIDUAL_BROWSER_${mode.toUpperCase()} PASS`);
