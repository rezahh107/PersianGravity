import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
const manifestPath = process.env.WU008_MANIFEST_PATH;
const adminPassword = process.env.WU008_ADMIN_PASSWORD;
const mode = process.env.WU008_G008_MODE;
if (!artifactDir || !manifestPath || !adminPassword || !['enabled', 'disabled'].includes(mode)) {
  throw new Error('Schedule browser requires artifact/manifest/admin password and enabled|disabled mode.');
}
const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const branches = manifest.g008_flow_schedule_branches ?? [];
if (branches.length !== 4) throw new Error('Expected four schedule branch fixtures.');

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 1100 } });
const diagnostics = { consoleErrors: [], pageErrors: [], requestFailures: [] };
page.on('console', (message) => {
  if (message.type() === 'error') diagnostics.consoleErrors.push(message.text().slice(0, 1200));
});
page.on('pageerror', (error) => diagnostics.pageErrors.push(String(error?.stack || error).slice(0, 3000)));
page.on('requestfailed', (request) => diagnostics.requestFailures.push({ url: request.url(), error: request.failure()?.errorText || 'unknown' }));

const loginResponse = await page.goto(manifest.login_url, { waitUntil: 'domcontentloaded' });
if (!loginResponse?.ok()) throw new Error(`Login page failed: ${loginResponse?.status()}`);
await page.fill('#user_login', 'runtime_admin');
await page.fill('#user_pass', adminPassword);
await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.click('#wp-submit')]);
await page.locator('#wpadminbar').waitFor({ timeout: 10000 });

const observations = [];
for (const branch of branches) {
  const response = await page.goto(branch.entry_url, { waitUntil: 'domcontentloaded' });
  if (!response?.ok()) throw new Error(`Schedule branch ${branch.key} request failed: ${response?.status()}`);
  await page.locator('.gravityflow-status-box').waitFor({ timeout: 15000 });
  const scheduledLocator = page.locator('.gravityflow-status-box-field-scheduled-date .gravityflow-status-box-field-value');
  const count = await scheduledLocator.count();
  const rendered = count === 1 ? ((await scheduledLocator.textContent())?.trim() ?? '') : null;
  const probe = await page.evaluate(() => window.pgrG008ScheduleProbe ?? null);
  if (!probe || Number(probe.entry_id) !== Number(branch.entry_id) || probe.branch !== branch.key) {
    throw new Error(`Schedule branch ${branch.key} getter probe identity is missing.`);
  }
  if (probe.nested_date_i18n !== 0) throw new Error(`Schedule branch ${branch.key} re-entered getter through date_i18n.`);

  if (branch.key === 'date_field_empty') {
    if (count !== 0 || rendered !== null || branch.expected_display !== null || branch.schedule_timestamp !== false || branch.is_queued !== false) {
      throw new Error('Empty date-field schedule branch did not remain native absent/non-queued.');
    }
  } else {
    if (count !== 1 || rendered !== branch.expected_display || branch.is_queued !== true) {
      throw new Error(`Schedule branch ${branch.key} rendered value drifted: ${JSON.stringify({ rendered, expected: branch.expected_display, count })}`);
    }
    if (!Number.isInteger(probe.total) || probe.total < 1) {
      throw new Error(`Schedule branch ${branch.key} did not exercise the authentic render getter path.`);
    }
  }

  const bodyText = await page.locator('body').innerText();
  if (bodyText.includes('PGRG008ENTRYDETAILMARKER')) throw new Error(`Schedule branch ${branch.key} leaked Entry Detail marker.`);
  observations.push({
    key: branch.key,
    entry_id: Number(branch.entry_id),
    schedule_type: branch.schedule_type,
    schedule_timestamp: branch.schedule_timestamp,
    step_timestamp: branch.step_timestamp,
    date_field_value: branch.date_field_value,
    is_queued: branch.is_queued,
    expected_display: branch.expected_display,
    rendered,
    scheduled_field_count: count,
    getter_probe: probe,
  });
}

if (diagnostics.pageErrors.length || diagnostics.requestFailures.length) {
  throw new Error(`Schedule browser diagnostics failed: ${JSON.stringify(diagnostics)}`);
}

const evidence = {
  schema_version: '1.0.0',
  evidence_class: 'AUTHENTIC_G008_FLOW_SCHEDULE_BRANCH_BROWSER',
  mode,
  exact_persiangravity_commit: process.env.WU008_PGR_SHA || null,
  exact_persiangravity_package_sha256: process.env.WU008_PGR_PACKAGE_SHA256 || null,
  exact_gravityflow_version: process.env.WU008_FLOW_VERSION || null,
  exact_gravityflow_package_sha256: process.env.WU008_FLOW_SHA256 || null,
  site_timezone: manifest.g008_flow_site_timezone || null,
  php_default_timezone: manifest.g008_flow_php_default_timezone || null,
  observations,
  diagnostics,
};
fs.writeFileSync(path.join(artifactDir, `g008-flow-schedule-browser-${mode}.json`), `${JSON.stringify(evidence, null, 2)}\n`);
await browser.close();
console.log(`G008_FLOW_SCHEDULE_BROWSER_${mode.toUpperCase()} PASS`);
