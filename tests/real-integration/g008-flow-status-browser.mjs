import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
const manifestPath = process.env.WU008_MANIFEST_PATH;
const adminPassword = process.env.WU008_ADMIN_PASSWORD;
const mode = process.env.WU008_G008_MODE;
if (!artifactDir || !manifestPath || !adminPassword || !['enabled', 'disabled'].includes(mode)) {
  throw new Error('G008 Status browser proof requires artifact/manifest/admin password and enabled|disabled mode.');
}

const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
if (!manifest.g008_flow_status_url || !Array.isArray(manifest.g008_flow_entries) || manifest.g008_flow_entries.length !== 3) {
  throw new Error('G008 Flow Status manifest fixture is incomplete.');
}

const expectedById = new Map(manifest.g008_flow_entries.map((entry) => [Number(entry.id), entry]));
const expectedIds = manifest.g008_flow_entries.map((entry) => Number(entry.id));
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

function statusUrl(params = {}) {
  const url = new URL(manifest.g008_flow_status_url);
  for (const [key, value] of Object.entries(params)) url.searchParams.set(key, value);
  return url.toString();
}

async function readStatusRows(params = {}) {
  const response = await page.goto(statusUrl(params), { waitUntil: 'domcontentloaded' });
  if (!response?.ok()) throw new Error(`Status request failed: ${response?.status()}`);
  const table = page.locator('#gravityflow-status-list table.wp-list-table.entries').first();
  await table.waitFor({ timeout: 15000 });
  const rows = await table.locator('tbody tr').evaluateAll((nodes) => nodes.map((node) => {
    const hrefs = [...node.querySelectorAll('a')].map((link) => link.getAttribute('href') || '');
    const idMatch = hrefs.map((href) => href.match(/[?&]lid=(\d+)/)).find(Boolean);
    return {
      id: idMatch ? Number(idMatch[1]) : null,
      date_created: node.querySelector('.column-date_created')?.textContent?.trim() ?? '',
      workflow_timestamp: node.querySelector('.column-workflow_timestamp')?.textContent?.trim() ?? '',
    };
  }).filter((row) => Number.isInteger(row.id)));
  return rows;
}

function sortExpected(key, direction) {
  const multiplier = direction === 'asc' ? 1 : -1;
  return [...manifest.g008_flow_entries].sort((a, b) => {
    if (key === 'workflow_timestamp') return (Number(a[key]) - Number(b[key])) * multiplier;
    return String(a[key]).localeCompare(String(b[key])) * multiplier;
  }).map((entry) => Number(entry.id));
}

await login();

const defaultRows = await readStatusRows();
if (defaultRows.length !== expectedIds.length) {
  throw new Error(`Expected ${expectedIds.length} Status rows, received ${defaultRows.length}.`);
}
for (const row of defaultRows) {
  const fixture = expectedById.get(row.id);
  if (!fixture) throw new Error(`Unexpected Status row ${row.id}.`);
  const expectedCreated = mode === 'enabled' ? fixture.expected_created_jalali : fixture.expected_created_native;
  const expectedUpdated = mode === 'enabled' ? fixture.expected_updated_jalali : fixture.expected_updated_native;
  if (row.date_created !== expectedCreated) {
    throw new Error(`${mode} Status date_created mismatch for ${row.id}: ${row.date_created}`);
  }
  if (row.workflow_timestamp !== expectedUpdated) {
    throw new Error(`${mode} Status workflow_timestamp mismatch for ${row.id}: ${row.workflow_timestamp}`);
  }
}

const sort = {};
for (const key of ['date_created', 'workflow_timestamp']) {
  for (const direction of ['asc', 'desc']) {
    const rows = await readStatusRows({ orderby: key, order: direction });
    const ids = rows.map((row) => row.id);
    const expected = sortExpected(key, direction);
    if (JSON.stringify(ids) !== JSON.stringify(expected)) {
      throw new Error(`Status ${key} ${direction} sort changed: ${JSON.stringify({ ids, expected })}`);
    }
    sort[`${key}_${direction}`] = ids;
  }
}

const filteredRows = await readStatusRows({ 'start-date': '2026-03-21', 'end-date': '2026-03-21' });
const alphaId = Number(manifest.g008_flow_entries.find((entry) => entry.key === 'alpha').id);
const filteredIds = filteredRows.map((row) => row.id);
if (JSON.stringify(filteredIds) !== JSON.stringify([alphaId])) {
  throw new Error(`Status civil-day filter changed: ${JSON.stringify(filteredIds)}`);
}

if (diagnostics.pageErrors.length || diagnostics.requestFailures.length) {
  throw new Error(`Status browser diagnostics failed: ${JSON.stringify(diagnostics)}`);
}

const evidence = {
  schema_version: '1.0.0',
  evidence_class: 'AUTHENTIC_GRAVITY_FLOW_STATUS_BROWSER',
  mode,
  exact_persiangravity_commit: process.env.WU008_PGR_SHA || null,
  exact_persiangravity_package_sha256: process.env.WU008_PGR_PACKAGE_SHA256 || null,
  exact_gravityflow_version: process.env.WU008_FLOW_VERSION || null,
  exact_gravityflow_package_sha256: process.env.WU008_FLOW_SHA256 || null,
  site_timezone: manifest.g008_flow_site_timezone,
  php_default_timezone: manifest.g008_flow_php_default_timezone,
  rows: defaultRows,
  sort,
  civil_day_filter: {
    start_date: '2026-03-21',
    end_date: '2026-03-21',
    visible_entry_ids: filteredIds,
  },
  diagnostics,
};
fs.writeFileSync(path.join(artifactDir, `g008-flow-status-browser-${mode}.json`), `${JSON.stringify(evidence, null, 2)}\n`);
await page.screenshot({ path: path.join(artifactDir, `g008-flow-status-${mode}.png`), fullPage: true });
await browser.close();
console.log(`G008_FLOW_STATUS_BROWSER_${mode.toUpperCase()} PASS`);
